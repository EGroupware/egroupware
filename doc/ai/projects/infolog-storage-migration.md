# InfoLog: replace `infolog_so` with `Api\Storage`

## Status: Phase 0 complete; Phase 1's ACL relocation done (2026-08-19, uncommitted -
pending smoke test), `Infolog\Storage`/persistence swap not yet started

This doc captures the research behind, and the proposed plan for, replacing InfoLog's
hand-rolled SQL storage backend (`infolog/inc/class.infolog_so.inc.php`, 1168 lines/17
methods, instantiated as `infolog_bo::$so`) with the generic `Api\Storage`
(`api/src/Storage.php`) class already used by several other apps. Follows the analysis/
discipline style of [[mail-bo-decoupling]] (read that doc for the extraction discipline
this one reuses: method-inventory tables, coupling/risk tiers, "no wrapper unless a
separate-repo consumer needs it", case-insensitive dead-code checks, no flag-day cutover).

Primary motivations (from the requester):
1. Do this safely — categorize every `infolog_bo` method touching `$this->so`, build a
   test harness at that boundary, *then* swap the backend.
2. `Api\Storage` (+ `Api\Etemplate`) can hand back `Api\DateTime` objects instead of raw
   timestamps and auto-converts between storage/user timezones — use that to delete
   InfoLog's own manual timezone-conversion code, not re-implement it.
3. The new storage class should reuse what `Api\Storage` already provides (custom
   fields, timezone handling) rather than re-porting InfoLog's parallel implementation
   of the same things.
4. `infolog_bo` has consumers beyond the UI (CalDAV/REST, ActiveSync/z-push, cross-app
   callers) — the swap must not break any of them.

## 1. Current architecture

### `infolog_so` — full method inventory

| Method | Purpose | Date/timestamp handling |
|---|---|---|
| `__construct($grants)` | init db, user, `tz_offset`, load CFs | reads legacy hour-offset pref into `$this->tz_offset`, used only by `dateFilter()` |
| `is_responsible[_user]()` | attendee/responsible membership check | none |
| `check_access()` | ACL incl. private/responsible logic | none |
| `responsible_filter()` / `aclFilter()` / `statusFilter()` | SQL fragment builders | none |
| `dateFilter()` | SQL date-range fragment (upcoming/today/overdue/date/enddate/limit) | **manual tz math** — `mktime(-$this->tz_offset, ...)`, legacy hour-offset, not a real `DateTimeZone` |
| `init()` | reset `$this->data` | none |
| `read()` | read one entry incl. responsible/cc, extra-table CFs, `info_uid` autogen | CF date-time → `new Api\DateTime($val, Api\DateTime::$user_timezone)`; main-table timestamps returned as raw ints untouched |
| `delete()` | delete row + extra + users rows, unlink, recurse | none |
| `get_children()` / `anzSubs()` | id/owner maps, sub-entry counts | none |
| `change_delete_owner()` | hook_delete_account reassignment | none |
| `write()` | insert/update main row, uid/caldav_name, CF write, responsible/cc upsert | CF date-time write: manual `new Api\DateTime($val, server_tz)->setTimezone(UTC)` + `'Z'` suffix — **this exact sequence is already duplicated verbatim in `Api\Storage::save_customfields()`** |
| `search()` | the big one: ACL/status/date filters, category, free-text/RAG, CF column filter (incl. multi-select), CF sort, links-by-app/id, count/found-rows, CF hydration | timestamps left raw; CF date-time hydration same pattern as `read()` |
| `users_with_open_entries()` | owners/responsibles with entries due within 4 days (async notify) | raw `time()`/`ABS(col-time())` in SQL, server-time ints |

Vestigial: `$cfcolfilter` in `search()` is incremented but never read — confirm during
extraction, likely dead.

**Note (decision #4, §4 below)**: `is_responsible[_user]()`, `check_access()`,
`responsible_filter()`/`aclFilter()`, and `statusFilter()`'s ACL-flavored role in the table
above are explicitly **not** going into `Infolog\Storage` — they move to `infolog_bo`, which
should own ACL the same way every other app's BO layer does, on top of an ACL-blind storage
class.

### `infolog_bo` — methods touching `$this->so`, grouped

| Group | Representative methods | Coupling to `infolog_bo` state | Extraction risk |
|---|---|---|---|
| **Single-entry read/ACL** | `read()`, `check_access()`, `is_responsible()`, `link_id2from()`, `set_link_cache()` | Medium — `$this->grants`, `self::$access_cache`, archived/history config | Medium |
| **Search/list** | `search()`, `getctag()`, `link_query()`, `pm_icons()`, `cal_to_include()`, `getParentID()` | High — `$this->timestamps`, `limit_modified_n_month` retry loop, dual-direction tz conversion, ACL post-filter | **High** — the `search()` monster; must reproduce retry-widening + all-day semantics or accept behavior change |
| **Save/update** | `write()`, `write_check_links()` | Very High — nearly every instance property | **High** — largest, most branchy method (defaults engine + ACL + tz + link/CF/notification orchestration interleaved) |
| **Delete** | `delete()` | Medium-High — recursion, ACL, `Link::unlink`, tracking | Medium |
| **Custom fields / validation** | required-CF check in `write()`, `has_customfields()` | Low-Medium | Low |
| **Categories** | `find_or_add_categories()`, `get_categories()` | Low | Low |
| **Links** | `link_id2from()`, `get_pm_id()`, `write_check_links()` | Medium | Medium — arguably out of scope (link/project logic, not persistence) |
| **History/tracking/notifications** | `delete()`/`write()`'s `infolog_tracking` calls, `async_notification()` | High — session impersonation in `async_notification()` | High but orthogonal to persistence |
| **iCal/SyncML matching** | `findInfo()` | Medium-High — 3 sequential `so->search()` passes, own tz normalization | Medium-High |
| **Async housekeeping** | `change_delete_owner()`, `users_with_open_entries()`/`async_notification()` | High — swaps `$GLOBALS['egw_info']['user']` | **Leave as-is**, same posture as mail's session-lifecycle group |

`write()` and `search()` are the two methods that matter most for this migration — they
contain nearly all of the storage coupling *and* nearly all of the manual date math.

### Manual timezone/date code this migration should delete, not port

1. `infolog_so::dateFilter()` + its `tz_offset` setup — legacy hour-offset `mktime()`
   math, not a real timezone.
2. `infolog_so::read()`/`search()` — duplicated CF date-time hydration (same code,
   two places).
3. `infolog_so::write()` — CF date-time persistence; **`Api\Storage::save_customfields()`
   already implements the identical convention natively** (`api/src/Storage.php`
   ~L278-284) — drop the private copy entirely rather than port it.
4. `infolog_bo::time2time()` — the central conversion: builds `Api\DateTime`, does a
   **midnight check** (`format('Hi') == '0000'`) to decide whether to *keep the same
   calendar date* when changing timezone (all-day semantics) vs. a straight
   `setTimezone()`. Caches zones in `self::$tz_cache`.
5. `infolog_bo::search()` — near-duplicate of #4's midnight-check logic, inlined
   per-row instead of calling `time2time()` (copy-paste-with-variation, same pattern
   the mail project's discipline notes call out for consolidation), plus separate
   query-side `Api\DateTime::user2server()` conversion.
6. `infolog_bo::async_notification()` — raw `date('Y-m-d', time()+24*60*60*N)` filter
   strings feeding `so::dateFilter()`'s regex/`mktime` parsing.
7. `infolog_bo::findInfo()` — `format('Ymd')` string-equality date-only comparisons.

**The semantic gap that matters most**: `Api\Storage\Base::db2data()`/`data2db()` (the
generic mechanism behind `$this->timestamps`) do a plain `Api\DateTime::server2user()`/
`user2server()` — there is **no built-in equivalent of `time2time()`'s "keep the same
calendar date" all-day handling**. A straight swap to `Api\Storage`'s automatic
conversion is a behavior change for all-day tasks viewed across timezones, not a pure
refactor. Needs an explicit decision (see §4).

### Custom fields — currently duplicated, `Api\Storage` already covers it

Both `infolog_so` and `infolog_bo` independently call
`Api\Storage\Customfields::get('infolog')` in their constructors. `infolog_so`'s
`read()`/`write()`/`search()` all hand-roll CF read/write/filter (date-time conversion,
multi-value JSON-vs-CSV encoding, `select`-type `INSTR` filtering, CF-based sort with
`to_int()`/`to_double()` casts) instead of delegating. `Api\Storage` provides all of this
generically (`read_customfields()`, `save_customfields()`, `cf_match()`/
`cf_multimatch()`/`cf_filter()`) the moment it's constructed with an extra-table — no
extra wiring needed. This hand-rolled layer should be deleted, not ported.

### Schema (`infolog/setup/tables_current.inc.php`)

`egw_infolog`: `info_startdate`, `info_enddate`, `info_datecompleted`, `info_created`,
`info_datemodified` are all `type='int', meta='timestamp'` — **stored as raw unix ints**,
not SQL `TIMESTAMP` columns, so `Api\Storage::convert_all_timestamps()`'s automatic
column-type detection won't pick them up; they'd need the manual
`$this->timestamps = [...]` array (same pattern as `timesheet/src/Events.php:28`).
`egw_infolog_extra` is the standard 3-column CF table. `egw_infolog_users.info_res_modified`
is a genuine SQL `timestamp` column (auto `current_timestamp`), unlike the others.

## 2. `Api\Storage` capability summary

- `Api\Storage extends Storage\Base`. `Storage\Base` is the plain SQL↔array mapper with
  **no** CF awareness; `Storage` adds CF support on top (opt in just by passing an
  extra-table to the constructor — no separate wiring).
- **Timestamps**: `$this->timestamps` (internal column names) + `$this->timestamp_type`
  (`null`/`'ts'`/`'string'`/`'object'`) control the read/write conversion pipeline
  (`db2data()`/`data2db()`, calling `Api\DateTime::server2user()`/`user2server()`).
  `convert_all_timestamps()` auto-populates `$this->timestamps` from schema columns
  typed `timestamp` — **does not apply to InfoLog's `int`-typed date columns**, so they
  need the manual-array approach. Passing `timestamp_type='object'` (constructor's last
  param, or `set_times()`) makes `read()`/`search()` return real `Api\DateTime` objects.
  CF date-times are always `Api\DateTime` objects already, stored as UTC-with-`Z`-suffix
  strings in the extra table.
- **No ACL/owner/private support** — confirmed nothing in `Storage\Base`/`Storage`
  implements permission filtering; that stays entirely at the `infolog_bo` level,
  unchanged, regardless of backend (matches how `timesheet_bo` does its own
  `$this->grants` on top of `Api\Storage`).
- **Search**: `search()`/`get_rows()` are the workhorses; `Storage::process_search()`
  (protected) is *explicitly documented as reusable* by a subclass/composed instance
  needing a custom search — exactly InfoLog's situation (ACL/status/date filters,
  attendee joins, category, RAG free-text).
- **Composition vs inheritance — both are real, supported patterns in this codebase,
  and InfoLog itself already uses composition once**:
  - Inheritance: `resources_so extends Api\Storage`; `timesheet_bo extends Api\Storage`
    directly (the *bo* class itself is the Storage subclass).
  - Composition: `admin_cmd::$sql = new Api\Storage\Base(...)`; and — already inside
    `infolog_so::search()` today (`class.infolog_so.inc.php:969`) — a throwaway
    `new Api\Storage('infolog', ...)` instantiated purely to reuse `search2criteria()`.
  - Best model for the `Api\DateTime`-object goal: `Api\Auth\Token extends Storage\Base`
    and `Timesheet\Events extends Storage\Base`, both constructed with
    `timestamp_type='object'` and (for `Events`) a manual `$timestamps` array — callers
    get real `Api\DateTime` instances with zero manual tz math anywhere in app code.

## 3. Consumer map (beyond the UI)

| Consumer | Relationship | Date handling |
|---|---|---|
| `infolog_ical` (`class.infolog_ical.inc.php`) | **`extends infolog_bo`** — real inheritance, not composition | calls inherited `time2time()` directly |
| `infolog_groupdav` (CalDAV/REST) | holds `$this->bo` | **Already `Api\DateTime`-native** — always requests `date_format='object'` (JsTask) or `'server'` from `bo->read()`/`search()`, never does raw math itself. One shim (`modifiedServerTs()`) exists only to paper over `search()`'s dual-format return, deletable once return shape is unified. |
| `infolog_zpush` (ActiveSync) | composes `$this->infolog = new infolog_bo()` | Currently zero local date logic (confirmed by grep — no `strtotime`/`gmdate`/`mktime`/`DateTime` in the file), 100% reliant on `read($id, ..., 'server')` / default `write()` with `$user2server=true`, and no test coverage today. **Correction (per ralf, 2026-08-19): not a "preserve exactly" risk — a modernization opportunity.** `calendar_zpush.inc.php` already reads `calendar_bo` with `'date_format' => 'DateTime'` and assigns the resulting `Api\DateTime` objects straight into `SyncAppointment::$starttime`/`$endtime`/`$dtstamp` (`calendar_zpush.inc.php:205,375-382`) — the z-push library itself supports this: `STREAMER_TYPE_DATE` properties in `vendor/egroupware/z-push-dev`'s `syncobject.php` explicitly branch on `instanceof \DateTimeInterface` (line ~531-535) rather than requiring a raw timestamp. `SyncTask` (`synctask.php`) has the same-shaped date properties (`duedate`/`utcduedate`/`startdate`/`utcstartdate`) using the same `STREAMER_TYPE_DATE` mechanism, so `infolog_zpush` should be upgraded to the identical pattern: read `infolog_bo` with an object date-format and assign/consume `Api\DateTime` objects directly instead of raw ints, exactly like `calendar_zpush` already does. This removes `infolog_zpush`'s manual reliance on the `'server'`/`$user2server` int contract entirely, rather than needing to preserve it. |
| `api/src/CalDAV/JsCalendar.php` (JMAP/JSCalendar) | calls `bo->read($entry, false, 'object')` | **Best-behaved** — fully `Api\DateTime`-native already. |
| `addressbook_ui`, `filemanager/src/Jobs.php`, `timesheet_ui` | `enums`, `write()`, `read()` | no date fields touched |
| `mail_compose::import_mail()` | `write($entry)` | caller does no manual math (check `infolog_bo::import_mail()` itself during refactor) |
| `aiassistant/src/Bo.php` | `write($task_data)` | **manual `time()`/`strtotime()` on the caller side** before `write()` — scattered date logic outside bo, low-risk (new module) but should be flagged |
| Link registry (`link_query`/`title`/`titles`/`file_access`, `cal_to_include`, `pm_icons`) | hook dispatch strings `infolog.infolog_bo.*` | date-safe except `cal_to_include()` (already `Api\DateTime`-native) |
| Async timer `infolog.infolog_bo.async_notification` | `Api\Async::set_timer()` | raw `date()`/`time()` filter-string construction (item #6 above) — in scope for cleanup |

### Existing test baseline

`infolog/tests/`: `ContactTest`, `StatusTest`, `DoubleLinkPMTest`,
`SetProjectManagerTest`, `ProjectTemplateTest`, `ExportCsvContactFilterTest`, plus
`CalDAV/` and `REST/` subdirs (`CalDAVImportTest`, `SyncCollectionReportTest` — cover
create/update/malformed-iCal/ACL-denial and sync-collection paging, both XML and JSON
REST variants).

**Coverage gap**: no dedicated tests for `infolog_zpush` (ActiveSync) at all, and no
isolated unit coverage of `time2time()`/the all-day semantic. This is exactly the
consumer with zero defensive code, so it's the top priority for new test coverage
before touching the storage backend (see Phase 0 below).

## 4. Design decisions (settled 2026-08-19)

1. **Composition, not `infolog_bo extends Api\Storage`.** Decided: a new
   `Infolog\Storage extends Api\Storage` class, instantiated by `infolog_bo` as
   `$this->so = new Infolog\Storage(...)` — i.e. keep the existing composition shape
   (`infolog_bo` already holds `$this->so`), just swap what's behind it. Reasoning:
   `infolog_bo` already defines `read()`/`write()`/`search()`/`delete()` with different
   signatures/semantics than `Api\Storage`'s `read()`/`save()`/`search()`/`delete()` —
   making `infolog_bo` itself extend `Api\Storage` would create name collisions/
   shadowing footguns for no benefit. This also matches the already-recorded lesson
   from the mail refactor (composition over inheritance for backend-swap situations)
   and the fact that `infolog_so` already composes a throwaway `Api\Storage` instance
   today.
2. **All-day date semantics across timezones.** Decided: keep a thin all-day-aware
   wrapper on top of `Api\Storage`'s `Api\DateTime` objects, preserving current
   behavior for all-day items viewed cross-timezone exactly as `time2time()` does
   today — do not let the plain `server2user`/`user2server` conversion silently shift
   displayed dates for all-day items when the viewer's TZ differs from the creator's.
3. **External contract stability during the swap.** Decided: Phase 1 changes *only*
   what's behind `$this->so`; `infolog_bo`'s existing `read()`/`write()`/`search()`
   signatures, `date_format` param, and `$user2server` flag keep working exactly as
   today for every existing caller. This is about not breaking anyone mid-swap, not
   about freezing `infolog_bo`'s contract forever — see the `infolog_zpush` correction
   below, which is a genuine, low-risk *addition* (an object-based `date_format` path
   already exists and is proven via `calendar_zpush`) that can land alongside Phase 1
   rather than waiting for a hypothetical Phase 3.
4. **ACL logic moves to the BO layer, not preserved in the new storage class.** Decided
   (ralf, 2026-08-19): `infolog_so`'s ACL-flavored methods — `check_access()`,
   `is_responsible()`/`is_responsible_user()`, `aclFilter()`, `responsible_filter()`, and
   the ACL half of `statusFilter()`/`dateFilter()`'s SQL-fragment role — are explicitly
   **not** something to carry into `Infolog\Storage`. This was a pre-existing InfoLog
   quirk, not a pattern worth preserving: every other app in this codebase
   (`timesheet_bo`, `calendar_boupdate`, etc.) builds its `$this->grants` and does ACL
   filtering/gating at the BO layer, on top of a storage class that has no ACL awareness
   at all — which also matches §2's finding that `Api\Storage`/`Storage\Base` provide
   **no** ACL primitives to begin with. `Infolog\Storage` should be a plain persistence
   class (read/write/delete/search over columns + cfs, no ACL SQL baked in); `infolog_bo`
   should own `$this->grants`, `check_access()`, `is_responsible()`, and any
   ACL-derived search filtering/gating itself, calling into `Infolog\Storage::search()`
   with plain column/value criteria (or a caller-supplied SQL fragment it built itself)
   rather than delegating ACL-fragment construction to the storage class. This is a
   real, deliberate architecture change from today's split, not just a mechanical
   relocation — call it out explicitly in the Phase 1 write-up/PR, since it moves
   security-relevant logic to a different file than where reviewers currently expect it.

## 5. Proposed phased plan

### Phase 0 — test harness (do first, before any `so` swap)

New tests targeting `infolog_bo`'s public methods directly, using the existing
`infolog/tests/` conventions (`LoggedInTest`-based, per `doc/ai/testing.md`).

**Landed 2026-08-19** — `infolog/tests/DateHandlingTest.php` (5 tests, all against
`infolog_bo::read()`/`write()` directly, using the same `setTimezones()`/`Api\DateTime::init()`
technique as `calendar/tests/TimezoneTest.php`):
- classic default (`'ts'`) read/write round trip (baseline)
- `read($id, true, 'object')` returns real `Api\DateTime` instances, consistent with the
  `'ts'`-format read of the same entry
- **confirmed by test**: `write()` already accepts `Api\DateTime` objects on date fields,
  not just ints — `time2time()`'s `new Api\DateTime($values[$key], $tz)` already handles a
  `DateTimeInterface` input transparently (`Api\DateTime::__construct()`'s `'object'` case).
  This is the load-bearing fact the "Alongside Phase 1" `infolog_zpush` modernization below
  depends on, and it required no code change — just a test proving it's already true.
- `time2time()`'s all-day ("keep the same calendar date across timezones") semantics hold
  under a client-timezone change — the regression net for design decision #2.
- a timed (non-all-day) entry correctly does NOT get the all-day treatment, and follows the
  viewer's timezone for wall-clock display while keeping the same UTC instant. **Note**:
  EGroupware's `'ts'` format is deliberately not a UTC epoch (see `Api\DateTime::format()`'s
  own docblock) — it's only comparable between two objects in the *same* timezone; use
  `'utc'` (`::getTimestamp()`) to compare instants across timezones, as this test does.

**Landed 2026-08-19** — `infolog/tests/ZpushTaskTest.php` (4 tests, calling
`infolog_zpush::GetMessage()`/`StatMessage()`/`ChangeMessage()` directly against a real
`infolog_bo` entry — not the z-push protocol/WBXML layer; only `activesync_backend` is
mocked, for its `splitID()` method). Needed a small shared bootstrap
(`infolog/tests/ZpushTestBootstrap.php`, non-namespaced on purpose) that stubs the global
`ZLog` class before requiring z-push-dev's own bundled vendor autoload — `infolog_zpush.inc.php`
calls `ZLog::Write()` unconditionally, and the real `ZLog` needs a `LOGBACKEND_CLASS` constant
that's only defined by the full z-push server bootstrap (`activesync/index.php`), which a unit
test must not run.
- `GetMessage()` maps `info_startdate`/`info_enddate` onto `SyncTask::$startdate`/`$duedate`
  as the plain server-time ints `infolog_bo::read($id, true, 'server')` returns, unconverted.
- `StatMessage()`'s `'mod'` matches `info_datemodified` in the same server-time format.
- an unmodified `GetMessage()`→`ChangeMessage()` round trip preserves dates exactly, both
  when the client and server timezone match, and when they differ.

  **Bug found and fixed 2026-08-19**: `GetMessage()` reads dates via `'server'` format, but
  `ChangeMessage()` used to hand that same raw int straight to `infolog_bo::write()` with its
  *default* `$user2server=true`, so `write()` wrongly treated an already-server-time int as
  user-time and converted it again. This meant **every** ActiveSync task sync where the
  device's timezone differed from the server's silently shifted task dates, not just edits -
  found via `testUnmodifiedGetChangeRoundTripPreservesDatesWhenTimezonesDiffer()` (initially
  written as a "locks down the drift" regression net, per ralf's explicit "fix it now"
  instruction it was turned into a fix-verifying test instead once the fix landed).
  Fix (`infolog/inc/class.infolog_zpush.inc.php`, `ChangeMessage()`): read the existing entry
  via `read($id, true, 'server')` (matching `GetMessage()`'s format) and call
  `write($infolog, true, true, false)` for edits of an existing entry, so `$infolog` stays
  server-time-consistent throughout instead of mixing representations. New-entry creation
  (empty `$id`) deliberately keeps the old `$user2server=true` default, since there is no
  prior server-time read to be consistent with there - not touched, to keep this a minimal,
  targeted fix rather than a rewrite of untested territory. `testEditedRoundTripAppliesTheChange()`
  guards against a fix that only coincidentally passes the no-op case.

**Landed 2026-08-19** — `infolog/tests/SearchDateFilterTest.php` (4 tests, against
`infolog_bo::search()` directly). Fixtures are placed several days away from the "today"/
"tomorrow" boundary `dateFilter()` computes from the live clock, so ordinary test-run timing
jitter can't flip a result; assertions check containment of specific known fixture ids (not
the whole result set), since this runs against a real, possibly non-empty dev database, and
`$query['start']` is deliberately left unset so `search()` returns everything matching
unbounded, sidestepping pagination risk entirely.
- `'upcoming'`/`'today'`/`'overdue'` — each dispatched to `infolog_so::dateFilter()`.
- `'bydate'` — **confirmed by test to be handled entirely inside `infolog_bo::search()`
  itself**, not `infolog_so::dateFilter()`: that method's filter regex happens to match the
  substring `"date"` inside `"bydate"`, but its `'date'` case unconditionally returns `''`
  on that code path (`$today` is never set when no explicit date suffix is given) - so
  `dateFilter()` contributes nothing for `'bydate'`, and the real filtering is
  `infolog_bo::search()`'s own `$query['startdate']`/`$query['enddate']` → `info_startdate`
  range `col_filter`.

**Landed 2026-08-19** — `infolog/tests/CustomFieldDateTimeTest.php` (3 tests, registering a
real temporary `'date-time'`-typed custom field via `Api\Storage\Customfields::update()`/
`save()` in `setUpBeforeClass()`/`tearDownAfterClass()`, since both `infolog_bo` and
`infolog_so` cache `Customfields::get('infolog')` once in their own constructor - the cf
must exist before `new infolog_bo()` runs, not just before the write/read call).
- confirms a date-time cf gets folded into `infolog_bo::$timestamps` alongside the main-table
  date columns (`infolog_bo::__construct()`'s cf-config-processing step, see §1 above).
- **confirmed by test**: a date-time cf follows the exact same `$date_format` contract as
  `info_startdate`/etc, not a cf-specific one — the default plain `read($id)` (format `'ts'`)
  returns a plain int for the cf too; `read($id, true, 'object')` is needed to get an
  `Api\DateTime` instance, exactly like the main-table columns.
- the same all-day ("keep the same calendar date across timezones") semantics
  `DateHandlingTest.php` locks down for main-table columns also hold for a date-time cf,
  confirming `time2time()` treats every entry in `$this->timestamps` uniformly regardless of
  whether it's a real column or a cf.

Phase 0's originally-scoped test coverage is now complete. Optional, not required to start
Phase 1: extending the existing CalDAV/REST tests to add explicit `date_format='object'`
(JsCalendar-shaped) coverage if a gap is found there — that path already has substantial
existing coverage per §3's consumer map, so this is a "check for gaps," not a "build from
scratch," item.

The *new* object-based `infolog_zpush` path (once it's upgraded per "Alongside Phase 1" below —
`GetMessage()` returning `Api\DateTime`-valued `SyncTask` properties, `ChangeMessage()` accepting
them back) still needs its own tests once that upgrade actually happens; not written yet since
the production code it would test doesn't exist yet.

### Phase 1

#### ACL relocation — **done 2026-08-19, uncommitted** (per decision #4)

Moved from `infolog_so` to `infolog_bo`, in `infolog/inc/class.infolog_so.inc.php` and
`infolog/inc/class.infolog_bo.inc.php`:
- `is_responsible()`/`is_responsible_user()` — merged into `infolog_bo`'s existing
  (narrower) `is_responsible($info)` by giving it back the optional `$user` parameter
  the `infolog_so` version had (backward compatible - existing 1-arg callers unaffected).
- `check_access()`'s array-only ACL decision logic — the so-side scalar/instance-cache
  branch was dead code from `infolog_bo`'s only call site (which always passes an array)
  and was dropped, not ported; the rest became a new `protected
  infolog_bo::checkAccessGrants()`, called from the two spots `infolog_bo::check_access()`
  used to call `$this->so->check_access(...)`.
- `aclFilter()` — moved to `infolog_bo::aclFilter()`, using `$this->so->db`/
  `$this->so->info_table`/`$this->so->users_table` for the raw-SQL building blocks
  (`infolog_bo` has no `$this->db` of its own, per decision #1's composition choice) and
  `$this->so->responsible_filter()` (stayed on `infolog_so` - a neutral SQL-fragment
  helper, not an ACL decision by itself) for the responsible-user sub-fragment.
- `infolog_so::search()`'s signature gained a third param, `$acl_filter=null` -
  it no longer builds the ACL fragment itself; **defaults to `'0=1'` (matches nothing)
  when omitted and `$no_acl` is false, not `'1=1'`, so a caller that forgets to pass it
  fails closed, not open.** Every one of `infolog_bo`'s 5 internal `$this->so->search(...)`
  call sites (the main `search()` wrapper, `async_notification()`, and `findInfo()`'s 3
  passes) was updated to compute and pass its own `$this->aclFilter(...)`.
- **External caller fixed**: `infolog_groupdav.inc.php` called `infolog_so::is_responsible_user()`
  directly (a static call bypassing `infolog_bo` entirely) - repointed to
  `infolog_bo::is_responsible_user()`. Found via a case-insensitive repo-wide grep for
  every moved method name, per the mail-bo-decoupling discipline this project follows -
  this is exactly the kind of near-miss that discipline exists to catch.
- **New regression tests**: `infolog/tests/AclCheckAccessTest.php` (4 tests) - owner
  always granted; a stranger with no grant/not responsible denied; a responsible non-owner
  gets implicit READ but not implicit EDIT (unless `$implicit_edit`); `aclFilter('own')`-
  scoped search includes an owned entry. The negative/responsible cases call the new
  `checkAccessGrants()` directly via reflection with an explicit, test-controlled
  `$grants` array, rather than going through the live `check_access()` wrapper's real
  `Acl::get_grants()` lookup - this dev database has real, pre-existing ACL grants
  (confirmed: demo has granted the `sysop`/Default-group role
  `READ|ADD|EDIT`=7 over demo's own entries, unrelated to this change) that would have
  made a live-grants-based negative test fail non-deterministically depending on what
  this particular box's ACL data happens to contain - exactly what `doc/ai/testing.md`
  warns about testing against.
- Full `infolog/tests/` suite: 53 tests / 407 assertions, all green except the same 4
  pre-existing `CalDAV`/`REST` `No DB host set!` errors from before this change (confirmed
  unrelated - those test files are untouched, tracked, pre-existing failures in this
  environment).
- **Not yet done**: `infolog_so` still has `statusFilter()`/`dateFilter()` (not ACL, left
  in place) and all its persistence internals (`read`/`write`/`delete`/`search`'s SQL,
  CF handling) - `Infolog\Storage`/the `Api\Storage` swap below has not been started.

#### `Infolog\Storage`/persistence swap — not started

- New `Infolog\Storage extends Api\Storage` (proposed location `infolog/src/Storage.php`,
  matching the `timesheet/src/Events.php` app-`src/` convention) — a **plain persistence
  class with no ACL awareness at all**, per decision #4. It reimplements `infolog_so`'s
  non-ACL public surface (`read`/`write`/`search`/`delete`/`get_children`/
  `change_delete_owner`/`anzSubs`/`users_with_open_entries`), delegating to inherited
  `Api\Storage` methods wherever possible.
- **Moved to `infolog_bo`, not carried into `Infolog\Storage`** (decision #4):
  `check_access()` (already lives on `infolog_bo` today, calling `so->check_access()` -
  the `so`-side half folds into `infolog_bo` instead), `is_responsible()`/
  `is_responsible_user()`, `aclFilter()`, `responsible_filter()`, and the status/date
  SQL-fragment role of `statusFilter()`/`dateFilter()` (their non-ACL date-shortcut math
  can stay a helper, but building the ACL/status/date WHERE fragments and handing them to
  `search()` becomes `infolog_bo`'s job, matching how `timesheet_bo`/`calendar_boupdate`
  build `$this->grants`-derived filtering themselves on top of an ACL-blind storage
  class). `infolog_bo::search()` calls `Infolog\Storage::search()` with plain criteria
  (or a `infolog_bo`-built SQL filter fragment passed through `Api\Storage::search()`'s
  existing `$filter`/`col_filter`/free-form-criteria params - it doesn't need a new
  extension point for this, just to stop asking the storage class to build the fragment
  itself).
- **Delete, don't port**: the hand-rolled CF read/write logic (→ inherited
  `read_customfields()`/`save_customfields()`/`cf_filter()`/`cf_match()`), the duplicate
  CF-date-time UTC-`Z` logic (already native to `Api\Storage`).
- Keep InfoLog-specific, non-ACL SQL (responsible/attendee joins, category/free-text/RAG
  search, CF multi-select filter, links-by-app/id) as overrides of `Api\Storage`'s
  `process_search()`/`search()`, per its documented reuse pattern.
- Internally use `timestamp_type='object'` + a manual `$this->timestamps` array
  (`info_startdate`/`enddate`/`datecompleted`/`created`/`datemodified` — `int` columns,
  not DB-type `timestamp`) so date fields become `Api\DateTime` objects internally.
- `infolog_bo`'s `read()`/`write()`/`search()` translate that `Api\DateTime` back into
  whatever the caller's existing contract expects (raw int / `date_format` param) — no
  consumer sees a behavior change in Phase 1 (per decision #3).
- Re-verify `infolog_ical` (real inheritance of `infolog_bo`) specifically, since it's
  not just a caller.
- Verification: a comparison test running old `infolog_so` and new `Infolog\Storage`
  against the same fixture data, asserting identical `read()`/`search()` results,
  before switching — same discipline as the mail JMAP dual-path work (add new path
  alongside old, verify parity, then cut over — never a flag-day rewrite). Given the ACL
  relocation is a real behavior-surface move (not just a mechanical one), this parity
  check needs to run through `infolog_bo`'s ACL-gated methods, not just the raw storage
  class, to prove ACL enforcement itself didn't change, only where it lives. Run Phase 0
  tests + full `infolog/tests` + CalDAV/REST suites; all must stay green.

### Alongside Phase 1 — modernize `infolog_zpush` to the object-based date contract

Not gated on the SO swap itself, but natural to do once `infolog_bo` reliably hands
back `Api\DateTime` objects via `date_format='object'`: update `infolog_zpush.inc.php`
to read/write `SyncTask`'s `duedate`/`utcduedate`/`startdate`/`utcstartdate` as
`Api\DateTime` objects directly, following `calendar_zpush.inc.php`'s existing pattern
(`'date_format' => 'DateTime'` on read, direct assignment to `SyncAppointment`
properties) — the z-push library's `STREAMER_TYPE_DATE` properties already accept
`\DateTimeInterface` objects (`syncobject.php`), so `SyncTask` needs no library-side
change. This removes `infolog_zpush`'s last remaining reliance on raw-int semantics
entirely, rather than just avoiding breaking it.

### Phase 2 — clean up `infolog_bo`'s own manual date math

Only after Phase 1 is confirmed stable:
- Replace `time2time()`'s manual midnight-check/tz-cache logic with direct
  `Api\DateTime` formatting, keeping the all-day-preserving semantics as an explicit
  small helper if decision #2 says to keep them.
- Migrate `async_notification()`'s raw `date()`/`time()` filter-string construction to
  `Api\DateTime`.

### Phase 3 (not started, out of scope unless separately requested)

Modernizing `infolog_bo`'s own external contract (always return `Api\DateTime`, drop
`date_format`) — a bigger, consumer-visible change touching `infolog_zpush`/
`infolog_groupdav`/`aiassistant`/`mail_compose` call sites. Needs real z-push test
coverage in place first (built in Phase 0) and explicit go-ahead before starting.

## Explicitly not proposed

- Any change to `infolog_bo`'s external contract in Phase 1 or 2.
- Touching `aiassistant`'s/`mail_compose`'s/`addressbook`'s/`timesheet`'s caller-side
  code beyond what's needed to keep them passing — they already use the stable
  `read()`/`write()` surface.
- A parallel rewrite / flag-day cutover. Old and new `so` must produce equivalent
  `read()`/`search()` results (verified by a comparison test) before switching.

## Scale

| File | Lines | Methods |
|---|---|---|
| `infolog/inc/class.infolog_so.inc.php` | 1168 | 17 |
| `infolog/inc/class.infolog_bo.inc.php` | 2479 | ~40 |
