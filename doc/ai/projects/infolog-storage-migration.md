# InfoLog: replace `infolog_so` with `Api\Storage`

## Status: Phase 0 and Phase 1 (ACL relocation + `Infolog\Storage`/persistence swap) done,
committed, and pushed; the CI regression the ACL relocation caused is fixed and **confirmed
green in CI** (run 32293037489, `phpunit` job passed 2026-08-19). `infolog_so.inc.php` is now
a permanent zero-logic compatibility shim (`class infolog_so extends \EGroupware\Infolog\Storage {}`)
rather than something planned for deletion - ralf's explicit call, since an installation's hook
registration can't be assumed refreshed. Remaining open work: `search()`'s bespoke SQL rewrite,
Phase 2 (TZ/date-math cleanup), the z-push object-based modernization, and Phase 3 - see
"Not yet done" and the Phase 2/3 sections below.

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

#### ACL relocation — CI regression found and fixed, 2026-08-19 (committed, CI-confirmed)

The ACL-relocation commit (`0d7f5bab72`, already pushed) broke CI:
`EGroupware\Infolog\CalDAVImportTest::testImportDeniedForForeignCollectionWithoutAcl` and its
REST counterpart both went from passing to `Failed asserting that 201 matches expected 403` -
a real authorization bypass letting a user with `run` rights but no ACL grant create a task in
another user's InfoLog collection via CalDAV/REST PUT. Traced (see `gh run view` on the CI job,
plus a dedicated investigation comparing old `infolog_so::check_access()` against the new
`infolog_bo::checkAccessGrants()`) to a genuinely subtle root cause, **not** a simple porting
mistake:

- `infolog_ical::importVTODO()` (line ~585) and its JSON-path mirror in
  `infolog_groupdav.inc.php` (line ~725) each had: for a brand-new task (no `info_owner` yet),
  `if ($this->check_access($taskData, Acl::ADD)) { $taskData['info_owner'] = $user; } elseif
  (...) { $taskData['info_responsible'][] = $user; }` - where `$user` is the *target collection
  owner* resolved from the URL, not the acting/session user.
- The OLD `infolog_so::check_access()` had a branch, `if (is_array($info) && !$info['info_owner'])
  $info = $info['info_id'];`, which for this ownerless-array case fell through to
  `$info = $this->data` (the SO instance's own cached data) - and that cache, freshly set by
  `init()` at construction time, holds `info_owner = $this->user` (the SO's own, i.e. the
  *acting*, user). So `$owner == $user` compared the acting user against themselves and was
  **always true for any authenticated caller** - not a real grants check at all, just an
  accidental self-match. This judged-dead branch was dropped when ACL moved to `infolog_bo`
  (see decision #4 above) - it was not actually dead for this call site.
- That accidental `true` had a real, load-bearing side effect: it made `importVTODO()` always
  stamp `info_owner = $user` (the real target collection owner) on new tasks. That correct
  stamp is what let the **actual**, correct, untouched security gate downstream in
  `infolog_bo::write()` (`check_access(0, Acl::EDIT/ADD, $values['info_owner'])`) correctly
  reject cross-user writes - it checks the acting user's grants *against the real target
  owner*.
- The new `checkAccessGrants()` correctly returns `false` for a data-only array with no owner
  (there is no legitimate owner to compare against) - objectively the right answer to the
  question actually being asked. But removing the accidental `true` means `importVTODO()` now
  takes the `elseif` branch instead, leaving `info_owner` **unset**. `write()`'s gate then
  degenerates: `check_access(0, Acl::ADD, null)`'s `$other` param is falsy, so it defaults
  `$owner = $user` (the *acting* user checking rights over *themselves* - trivially always
  true) instead of checking rights over the real target owner. The real gate never fires, and
  the write silently succeeds, owned by the acting user, regardless of the URL's target
  collection.
- **Conclusion**: `importVTODO()`'s `check_access($taskData, Acl::ADD)` call was never a
  meaningful gate - given an ownerless array, it could only ever evaluate to an accidental
  self-match, so it was `true` for literally any authenticated caller, always. The `elseif`
  branch (add as responsible instead of owner) was practically dead code for this entire
  quirk's lifetime, for an unrelated, pre-existing reason that has nothing to do with this
  migration.
- **Fix** (ralf's explicit choice over restoring the old accidental behavior): removed the
  conditional entirely in both `infolog_ical::importVTODO()` and the JSON-path mirror in
  `infolog_groupdav.inc.php` - new tasks now **unconditionally** get `info_owner` stamped to
  the target collection owner, with `infolog_bo::write()`'s own (correct, untouched) ACL gate
  as the sole enforcement point. This is behaviorally identical to what the old code *actually
  did in practice* (since its gate was always true anyway), just without the fragile
  mechanism. `infolog_ical.inc.php`'s now-unused `use EGroupware\Api\Acl;` import removed too.
- **Second, related but non-triggering bug fixed in the same pass**: `Infolog\Storage`'s
  constructor set `$this->user` *after* calling `parent::__construct()` - but
  `Storage\Base::__construct()` calls `$this->init()` internally, and `Infolog\Storage::init()`
  reads `$this->user` to seed `$this->data['info_owner']`. That first `init()` call therefore
  always saw `$this->user` as still null. Didn't affect the CI regression itself (the new
  `checkAccessGrants()` never reads `$this->so->data` at all), but is a real correctness gap in
  the same area - fixed by moving the `$this->user`/`$this->tz_offset` assignments before the
  `parent::__construct()` call.
- **Verified**: local `infolog/tests/` couldn't exercise this specific test at first
  (`CalDAVImportTest`/`SyncCollectionReportTest` need DB credentials
  `CalDAVTest.php::getSetup()` couldn't resolve in this sandbox - separately fixed, see
  "CalDAVTest::getSetup() domain resolution" below) - confirmed instead via a CI re-run
  after pushing: `testImportDeniedForForeignCollectionWithoutAcl` and its REST counterpart
  both pass in CI run 32293037489 (`phpunit` job green, 2026-08-19).

#### `CalDAVTest::getSetup()` domain resolution — fixed and committed, 2026-08-19

Separately from the ACL fix, `api/tests/CalDAVTest.php::getSetup()` (used by
`createUser()` to spin up fixture accounts for CalDAV/REST tests) looked up
`$GLOBALS['egw_domain'][$_REQUEST['domain']]` using the literal domain string from
`phpunit.xml` (`EGW_DOMAIN=default`), but this repo's `header.inc.php` commonly only
defines real, named domains (e.g. `boulder.egroupware.org`) - no domain literally named
`default` needs to exist. Every other test path (`LoggedInTest::load_egw()`) already
resolves this correctly via `Api\Session::search_instance()`, which falls back to matching
`HTTP_HOST`/`SERVER_NAME` or, failing that, the first configured domain, instead of a
literal string match. `getSetup()` now does the same resolution. Verified the resolved
domain/`db_host`/`db_name` matches exactly what the rest of the already-passing suite
resolves to via `load_egw()` - not diverting fixture-account creation to a different
environment. This fix alone unblocked several previously-`No DB host set!`-erroring test
files locally (`infolog/tests/` went from 53 to 68 runnable tests).

Separately, `CalDAVImportTest`'s own `runCaldavRequest()` helper has an unrelated,
pre-existing limitation in this sandbox: it calls `Api\CalDAV::runRequest()`, which
simulates the request in-process and reports status via PHP's `http_response_code()`
(`api/src/CalDAV.php:2800`) - that returns `false`/`0` here rather than a real code, for
reasons not further investigated (out of scope - a CLI-harness quirk, not a app bug). 6 of
`CalDAVImportTest`'s tests still can't be exercised locally because of this; CI (which runs
against a full web server, not this in-process simulation) is unaffected and is the
authoritative signal.

#### `Infolog\Storage`/persistence swap — **implemented, committed, and pushed 2026-08-19**

`infolog/src/Storage.php` (`EGroupware\Infolog\Storage extends Api\Storage`) now exists and is
wired in: `infolog_bo::__construct()`/`async_notification()` construct it instead of
`infolog_so`. `infolog_so.inc.php` was left in place at this point (untouched beyond the
earlier ACL removal) - later turned into a permanent zero-logic compatibility shim, see
"`infolog_so.inc.php` → zero-logic compatibility shim" below.

What actually landed, vs. the original bullets above (kept for history - reality diverged in a
few places once real PHP/DB constraints showed up):

- **CF delegation, done, with one correction**: `read()`/`write()`/`search()` delegate
  *registered* custom fields to the inherited `read_customfields()`/`save_customfields()` -
  exactly the "don't re-implement it" goal. But `infolog_ical.inc.php` turned out to rely on
  an **unregistered** `#`-prefixed convention (`"##propertyname"`, storing raw iCal X-property
  data as a pseudo-cf not present in `Api\Storage\Customfields::get('infolog')`) that
  `read_customfields()`/`save_customfields()` silently ignore (they only iterate
  `$this->customfields`). Missing this would have silently dropped CalDAV/iCal extended
  properties on every read/write/search. Fixed by keeping the *exact* old raw insert/delete
  (write) and raw select (read/search) logic as a fallback specifically for `#`-prefixed keys
  not found in `$this->customfields`, alongside the new delegation for registered ones.
- **`timestamp_type`/`$this->timestamps` NOT used for main-table columns** - deliberately
  simpler than the original bullet: main-table date columns stay raw ints exactly as before
  (no `Api\DateTime` objects internally), so `infolog_bo`'s `time2time()`/`date2usertime()`
  needed zero changes and decision #3's "no consumer sees a behavior change" holds with less
  moving at once. Switching main-table columns to `Api\DateTime` internally is explicitly
  deferred to Phase 2 (cleaning up `infolog_bo`'s own manual date math), not bundled in here.
- **`search()` renamed to `searchInfolog()`** on the new class - not kept as an override of
  `Api\Storage::search()`. PHP fatals if an overriding method isn't parameter-compatible with
  its parent (arity, by-ref-ness), and this method's `&$query` by-reference parameter (every
  `infolog_bo` call site relies on `$query['total']`/`$query['start']` being written back) is
  fundamentally incompatible with `Api\Storage::search($criteria, ...)`'s by-value contract -
  matching arity would need reproducing 11 meaningless parameters. Renaming avoids the
  override entirely. All 5 `infolog_bo` call sites updated accordingly.
- **Three other signature-compatibility fatals fixed the same way** (found by iterating -
  PHP's override-compatibility check reports one at a time): `read($where)` needed
  `$extra_cols=''`/`$join=''` added (unused, just present) to match
  `Api\Storage::read($keys,$extra_cols='',$join='')`'s arity; `delete($info_id,...)` needed
  `$info_id=null` (was required) to match `Api\Storage::delete($keys=null,...)`;
  `init()` needed a `$keys=array()` param to match `Storage\Base::init($keys=array())`. None
  of these change real behavior (`infolog_bo` never calls with fewer/different args), it's
  purely about satisfying PHP's inheritance signature check.
- **`$this->db` widened to public** on `Infolog\Storage` (`Storage\Base` declares it
  `protected`) - `infolog_bo::aclFilter()` needs `$this->so->db` directly to build raw ACL SQL
  (per decision #1, `infolog_bo` has no `$this->db` of its own).
- **Real bug found, fixed correctly on the second pass**: `Db::update()`/`insert()`/`delete()`/
  `select()` resolve a table's column definitions via an explicit `$app` parameter (their own
  `get_table_definitions($app, $table)` call), not via any state cached on the db *object*
  itself. `Storage\Base::save()`/`delete()` always pass `$this->app` explicitly on every such
  call - confirmed by reading `api/src/Storage/Base.php:655,698,707,816` - which is exactly why
  `$no_clone=true` (`Api\Storage`'s own default, used safely by many other apps) is fine for
  code that goes through those inherited methods. This class's *own* ported `read()`/`write()`/
  `delete()`/etc. methods (verbatim from `infolog_so`, which instead cloned the db object and
  called `set_app('infolog')` on the clone once, relying on that implicit context for every
  call after) had several `Db::update()`/`insert()`/`delete()`/`select()` calls missing that
  explicit `$app` argument - silently producing an **empty SET clause** on affected `update()`
  calls (a real SQL error caught immediately by `AclCheckAccessTest.php`, not a silent
  data-loss bug - but would have been one without that test). First fix attempt used
  `$no_clone=false` to restore the old implicit-clone behavior - technically worked, but ralf
  correctly pushed back that it papers over the real gap rather than matching the idiomatic,
  already-established pattern. Properly fixed by reverting to `$no_clone`'s default (`true`)
  and instead auditing every `$this->db->select()/update()/insert()/delete()` call in this
  class, adding the explicit `'infolog'` argument wherever it was missing (roughly a dozen call
  sites across `read()`/`get_children()`/`delete()`/`write()`/`searchInfolog()`/
  `users_with_open_entries()`) - `query()` calls need no such argument, since raw SQL has no
  `$data`/`$where` array to filter against a schema in the first place.
- **Real bug found and fixed**: `aclFilter()`'s cache (`infolog_bo::$acl_filter`, added when
  ACL moved off `infolog_so`) is keyed only by filter-type+user, not by grants/user - safe on
  `infolog_so` because `async_notification()` re-instantiated a *fresh* `so` per impersonated
  user (implicitly clearing its cache), but `aclFilter()` now lives on `infolog_bo`, whose
  `$this` persists across that whole per-user loop. Without clearing it explicitly, a later
  impersonated user could get served an earlier user's cached ACL SQL fragment. Fixed by
  adding `$this->acl_filter = array();` right where the `so` used to get recreated.
- Keep InfoLog-specific, non-ACL SQL (responsible/attendee joins, category/free-text/RAG
  search, CF multi-select filter, links-by-app/id) exactly as `infolog_so` had it - **not**
  rewritten to use `Api\Storage`'s generic `search()`/`process_search()` machinery. This
  remains deliberately out of scope for this pass (see "Not yet done").
- **Verification so far**: full `infolog/tests/` suite - 53 tests / 407 assertions, all green
  except the same 4 pre-existing, unrelated `CalDAV`/`REST` `No DB host set!` errors from
  before any of this work. Also ran, green: `importexport/tests/ImportexportBasicImportCsvRegressionTest.php`
  (real `write()`/`delete()` via the new backend through the CSV import path);
  `api/tests/Vfs/SharingBackendTest.php` (its `infolog_bo::write()` call via `make_infolog()`
  succeeds - the test's own failures are pre-existing VFS filesystem-permission issues in this
  sandbox, unrelated to InfoLog, confirmed by grepping the failures for "infolog" and finding
  only the expected pre-existing `Rag\Embedding::logError()` noise, not a storage error).
  `api/tests/Vfs/Links/StreamWrapperTest.php`'s admin-only test case couldn't be run (needs
  `EGW_ADMIN_PASSWORD`, not supplied this session) - not a regression signal either way; that
  file also uses `infolog_so` directly (unaffected by this swap regardless).
- **Not yet done, explicitly out of scope for this pass**:
  - Rewriting `search()`'s (now `searchInfolog()`'s) internals to use `Api\Storage`'s generic
    `search()`/`process_search()` machinery instead of the ported-as-is bespoke SQL. Still the
    "High risk, the search() monster" item from §1/§2 - unstarted.
  - The comparison/parity test between old `infolog_so` and new `Infolog\Storage` originally
    planned here didn't end up happening as a separate artifact - the existing
    `infolog/tests/` suite (pre-existing tests plus this project's Phase 0 additions) already
    exercises `read`/`write`/`search`/`delete` through `infolog_bo` extensively and passed
    unchanged before/after the swap, which is the parity check in practice. A dedicated
    old-vs-new comparison test remains a nice-to-have, not done.
  - Phase 2's date-math cleanup (per decision #3, deliberately deferred - main-table columns
    still raw ints, `time2time()` untouched).

#### `infolog_so.inc.php` → zero-logic compatibility shim, 2026-08-20 (ralf's explicit call)

Revised plan: `infolog_so.inc.php` is **not** going away - ralf's call, since "there's no
guarantee the new hooks have been registered" (an installation's hooks table caches its
registration string at the last setup/upgrade run; an already-running instance won't get the
new one until its next upgrade). `class.infolog_so.inc.php` now contains nothing but
`class infolog_so extends \EGroupware\Infolog\Storage {}` plus a docblock pointing anyone
tempted to add logic there at the real class instead.

- `infolog/setup/setup.inc.php`'s `deleteaccount` hook now points directly at
  `EGroupware\Infolog\Storage::change_delete_owner` (was `infolog.infolog_so.change_delete_owner`).
  Investigated `Api\Hooks::single()`'s two dispatch paths first (`api/src/Hooks.php`): a
  `Class::method` hook value is checked with `is_callable()` and dispatched as a genuinely
  *static* call - which silently no-ops (no error, no log) for a non-static method - while the
  legacy dotted `app.class.method` format goes through `ExecMethod2()`, which instantiates the
  class and calls the method as an *instance* method. Every other namespaced hook already in
  this codebase (`api/setup/setup.inc.php:60`, `mail/setup/setup.inc.php`, etc.) uses the
  `Class::method` static form exclusively - no precedent anywhere for combining a namespaced
  class with the dotted format. So `Storage::change_delete_owner()` was converted to `static`
  (instantiates `new self()` internally; had exactly one caller - the hook itself, confirmed by
  grep - so nothing else depends on instance semantics) to match that established convention,
  rather than introduce an untested hybrid syntax.
- The `infolog_so` shim still resolves the *old*, cached dotted hook string correctly even
  after that method became static: PHP allows calling a static method via an instance-shaped
  callable array (`[$obj, 'method']`), which is exactly what `ExecMethod2()` uses. Verified
  directly: `ExecMethod2('infolog.infolog_so.change_delete_owner', $args)` and
  `call_user_func('EGroupware\Infolog\Storage::change_delete_owner', $args)` both dispatch
  correctly, and `new \infolog_so()`'s inherited `read()`/`write()` work identically to calling
  them on `\EGroupware\Infolog\Storage` directly.
- `api/tests/Vfs/Links/StreamWrapperTest.php:86` (the one remaining direct `new \infolog_so()`
  call outside the shim's own file) updated to `new \EGroupware\Infolog\Storage()` - the only
  test that still had a live dependency on the old class name.

### `searchInfolog()` — delegate custom-field `col_filter` to `Api\Storage::cf_filter()`, 2026-08-20

First scoped slice of "the `search()` monster" (§1/§2's highest-risk remaining item, "start at
the top" per ralf). Deliberately narrow: only the `#`-prefixed custom-field entries in
`col_filter` are delegated to `Api\Storage::cf_filter()`; everything else in `searchInfolog()`
(responsible/cc joins, category filter, free-text/RAG search, ACL/status/date fragments,
pagination, `sortbycf`, action-link filtering) stays exactly as ported - no big-bang rewrite.

- Wrote `infolog/tests/SearchCustomFieldFilterTest.php` (4 tests: single-value select-cf match,
  multi-value select-cf match-any, exact text-cf match, text-cf case-insensitivity) *before* the
  change, run once against the unmodified code to establish a baseline.
- Wrong assumption caught by that baseline run: expected `cf_filter()`'s case-insensitive `LIKE`
  for `text`-type cfs to be a behavior change from the old code's exact `=` match. It isn't -
  MySQL's default column collation (`_ci`) already makes a plain `=` comparison
  case-insensitive on this schema, so both the old and new code paths were already
  case-insensitive. Test corrected to document the true (pre-existing) behavior rather than
  leaving a wrong assumption baked into a comment/assertion.
- Implementation in `infolog/src/Storage.php::searchInfolog()`: moved the `$join` initialization
  earlier (it was previously built later, right before the free-text search block, but
  `cf_filter()` needs it initialized before that point so it can append its own JOIN
  fragments); collected `#`-prefixed `col_filter` entries into `$cf_col_filter` and called
  `$this->cf_filter($cf_col_filter, $join, '')`; removed the old hand-rolled
  correlated-IN-subquery CF filter block entirely.
- **`extra_join_filter` table-alias mismatch**: `cf_filter()`'s JOIN fragment
  (`$this->extra_join_filter`) is built once at `Api\Storage::__construct()` time using the real
  table name, but `searchInfolog()`'s `FROM` clause aliases the table as `main`. Fixed by
  temporarily string-replacing `$this->table_name.'.'` with `'main.'` in a saved/restored copy of
  `$this->extra_join_filter` around the `cf_filter()` call (same reason the free-text/RAG search
  block further down needs a throwaway `Api\Storage` instance with `table_name` overridden to
  `'main'`).
- **Mutation-safety fix**: the first version of this change removed the `#`-prefixed entries
  from `$query['col_filter']` via `unset()` after copying them into `$cf_col_filter`, on the
  reasoning that the main `col_filter` loop shouldn't also try to handle them. That's unsafe:
  `searchInfolog(&$query, ...)` takes `$query` by reference, and `infolog_bo::search()`'s
  `limit_modified_n_month` retry loop can call `searchInfolog()` again with that *same* `$query`
  variable - destructively removing the CF entries on the first call would silently drop the CF
  filter on any retry. Fixed by leaving `$query['col_filter']` untouched and instead relying on
  the main loop's existing `preg_match('/^[a-z_0-9]+$/i', $col)` guard, which already rejects any
  `#`-prefixed column before it reaches the `switch`/`db->expression()` code - so no separate
  skip was even needed there, just not mutating the shared array.
- **Verification**: `SearchCustomFieldFilterTest.php` re-run against the modified code - 4/4
  pass. Full `infolog/tests/` suite - 72 tests / 538 assertions, same 6 pre-existing
  `CalDAVImportTest` failures (unrelated `http_response_code()` CLI-harness limitation) and same
  pre-existing `ProjectTemplateTest` risky-test warning as the established baseline, confirmed by
  test name - no new regressions. Also re-ran
  `importexport/tests/ImportexportBasicImportCsvRegressionTest.php` (green) and
  `api/tests/Vfs/SharingBackendTest.php` (same pre-existing, unrelated VFS
  filesystem-permission failures on this dev box as before, not an InfoLog/storage error).
- **Not yet done** (at the time of this section; `sortbycf` was done in the very next
  increment, see below): free-text/RAG search still calls `search2criteria()` directly rather
  than through `process_search()`'s orchestration (not a duplication, just not centralized);
  responsible/cc joins, category filtering, ACL/status/date fragment building, and action-link
  filtering remain deliberately bespoke - no generic `Api\Storage` equivalent exists for
  InfoLog's specific join/aggregation shape.

### `searchInfolog()` — delegate `sortbycf` to `Api\Storage::order_by_cf()`, 2026-08-20

Second slice of the `search()` rewrite, immediately following the `cf_filter()` one above.
Unlike `cf_filter()`, the CF-order-by logic wasn't exposed as a standalone reusable method on
`Api\Storage` - it was an inline block inside `process_search()`
(`api/src/Storage.php`, now extracted). Ralf's explicit call on how to handle that: extract a
shared method rather than duplicate the logic in InfoLog, accepting the bigger blast radius
(shared base class, used by every `Api\Storage`-derived app, not just InfoLog).

- **Refactor**: extracted the inline "order by a `#name`-prefixed customfield" block out of
  `Api\Storage::process_search()` into a new `protected function order_by_cf(&$order_by, &$join,
  &$extra_cols)`, called from the same place `process_search()` used it inline. Verbatim
  extraction, no logic change - same string manipulation, same `extra_order`-aliased JOIN, same
  `int`/`float`/default type-cast switch, same postgres-only `$extra_cols` handling.
- **New test for the refactor itself**: `SearchCustomFieldFilterTest::testGenericSearchOrdersBySelectCf()`
  calls the generic *inherited* `Api\Storage::search()` directly on an `Infolog\Storage`
  instance (not `searchInfolog()`), ordering by a `select`-type cf - this is the only test
  anywhere in the framework that exercises CF-based `order_by`, so it locks down the refactor's
  behavior across every app that extends `Api\Storage`, not just InfoLog.
- **`infolog/src/Storage.php::searchInfolog()` changes**: replaced the old `cfsortcrit`
  correlated-subquery approach (a `(SELECT DISTINCT info_extra_value FROM egw_infolog_extra sub2
  WHERE sub2.info_id=main.info_id AND info_extra_name=...)` added as an extra SELECT column,
  referenced via a placeholder token in the `ORDER BY` list) with a call to
  `$this->order_by_cf($order_by, $join, $extra_order_cols)`, using the same
  `extra_join_order`-table-alias-swap trick already used for `cf_filter()`'s `extra_join_filter`
  (aliasing `$this->table_name.'.'` to `'main.'` for the duration of the call, since
  `extra_join_order` is also built once at construct time against the real table name).
- **Order-building restructured to support delegation**: the old code built one shared trailing
  direction for the whole `ORDER BY` list (`implode(',',$order) . ' ' . $query['sort']` - only
  the *last* field in a multi-field order actually got that direction in the resulting SQL, a
  latent quirk, harmless in practice since `$query['order']` is realistically always a single
  field). `order_by_cf()` expects each comma-separated criterion to carry its own direction
  suffix (it inspects the last space-separated token per segment), so each field now gets
  `' '.$query['sort']` appended individually before the list is joined - a side-effect
  improvement for the (unused in practice) multi-field case, not just a delegation requirement.
- **Removed**: the `$sortbycf` variable, the `cfsortcrit`/`$info_customfield` correlated
  subquery block, and its splice into the `SELECT` column list - all dead code once ordering
  moved to the JOIN-based approach.
- **Verification**: two new tests exercising the real `infolog_bo::search()` /
  `searchInfolog()` path directly (`testSearchInfologOrdersBySelectCfAscending`/`...Descending`)
  - both green. Full `infolog/tests/` suite - 75 tests / 544 assertions, same 6 pre-existing
  `CalDAVImportTest` failures and same pre-existing `ProjectTemplateTest` risky warning as the
  established baseline (test names checked, no new regressions). `api/tests/Storage/` suite -
  same pre-existing `BaseTest`  "No DB host set!" CLI-harness error as always, nothing new.
  `importexport/tests/ImportexportBasicImportCsvRegressionTest.php` and `addressbook/tests/` -
  both green (chosen as the broadest sanity check for the shared `Api\Storage` base-class
  refactor, since both apps exercise generic `search()`/`process_search()` heavily).
- **Not yet done**: free-text/RAG search consolidation into `process_search()`'s orchestration;
  responsible/cc joins, category filtering, ACL/status/date fragment building, and action-link
  filtering remain deliberately bespoke.
- **Bigger idea raised by ralf**: could the responsible/cc row-multiplication be avoided higher
  up so InfoLog could use `Api\Storage::search()`/`process_search()` directly (or a thin
  extension of it) instead of `searchInfolog()`'s fully bespoke query builder? Research and a
  phased plan for this - see the next section.

### Research — eliminating `searchInfolog()`'s row-duplicating JOINs, 2026-08-20

Ralf asked to research and plan (not yet implement) how to avoid the responsible/cc
row-duplication so `Api\Storage::search()` could be used for InfoLog's main query, since that's
the biggest remaining obstacle to using the generic machinery directly instead of a bespoke
query builder.

**Correcting a premise first**: ralf described the current design as "first retrieves all IDs
and then queries the full rows for these IDs." Checked thoroughly (`infolog_bo::search()`,
`searchInfolog()`, `read()`, `infolog_ui::get_rows()`) - that two-pass shape does **not** exist
anywhere in InfoLog today. Both `read()` and `searchInfolog()` use a single query with
`LEFT JOIN egw_infolog_users` (aliased `attendees` for display) + `GROUP_CONCAT()`/`GROUP BY` to
aggregate the 1:N responsible-delegate/cc-attendee relationship into the result row - confirmed
via git history that this was already `infolog_so`'s design well before this migration started,
not something introduced by it.

**Where the real precedent lives**: `calendar_so` - structurally the closest analog, since
`egw_cal_user` (participants) is 1:N per event exactly like `egw_infolog_users` is 1:N per info
entry. Both `calendar_so::read()` (`calendar/inc/class.calendar_so.inc.php:561-579`) and
`calendar_so::search()` (`:1240-1310`) use the two-pass shape ralf described: the main query
never joins the participants table at all; a separate batch query
(`... WHERE cal_id IN (<ids from the main query's result>) ...`, no join to `egw_cal_events`)
fetches every participant row for the whole result set in one go, merged into
`$events[$id]['participants']` in PHP. This is the concrete, already-battle-tested model to
copy for InfoLog's responsible/cc, not something to invent from scratch.

**Why the row-duplication currently blocks a bare `Api\Storage::search()` call**: `process_search()`'s
only row-multiplication safeguard is `DISTINCT` (auto-added when `$join` includes `extra_join`,
`api/src/Storage.php:700-713`) - it works *only* because the customfield extra-table join is
used exclusively for filtering/sorting, never to project a value into the output row, so
duplicate rows are byte-identical and collapse under `DISTINCT`. InfoLog's case is fundamentally
different: the joined columns (`account_id`, `info_res_attendee`) **are** the desired output
(`info_responsible`/`info_cc`) - `DISTINCT` can't collapse rows that differ in the very column
being selected. `GROUP_CONCAT`/`GROUP BY` is the only single-query way to do that, which is
exactly why the current code needs it and why it can't be replaced by a bare call to the
generic `search()` as-is. Teaching `Api\Storage` a generic "aggregate a 1:N join into an
array/CSV column" mechanism would be a new capability added to the shared base class, not an
extraction of something that already exists (like `cf_filter()`/`order_by_cf()` were) - a
materially bigger and riskier change than either of those two increments.

**Proposed design** - decouple `egw_infolog_users` into its own read-path helper, mirroring
`calendar_so`, instead of teaching `Api\Storage` a new aggregation capability:

- New `read_responsible(array $info_ids): array` on `Infolog\Storage`: one plain
  `SELECT * FROM egw_infolog_users WHERE info_id IN (...) AND info_res_deleted IS NULL` - no
  join to `egw_infolog` at all - returning `[info_id => ['info_responsible' => [...], 'info_cc' => [...]]]`,
  mirroring `read_customfields()`'s per-id return shape (already established for CFs) and
  `calendar_so`'s participant-fetch shape.
- `read()`: drop its `LEFT JOIN egw_infolog_users`/`GROUP_CONCAT`, call `read_responsible([$info_id])`
  instead and merge the result - symmetric with how CF hydration already works via
  `read_customfields()`.
- `searchInfolog()`: drop *both* `users_table` `LEFT JOIN`s and the `GROUP BY` from the main
  query entirely.
  - `col_filter['info_responsible']` (the *only* place `info_responsible` is used as a filter -
    never as an `order_by` target) becomes an `EXISTS (SELECT 1 FROM egw_infolog_users WHERE
    info_id=main.info_id AND ...)`-style fragment instead of a `JOIN` + `OR ... IS NULL AND
    owner-match` condition - semantically equivalent, no row multiplication, no `GROUP BY`
    needed to support it either. Currently has **zero** test coverage (not even before this
    migration) - needs a dedicated test locking down today's behavior (plain user match,
    group/membership expansion, the owner-fallback-when-no-delegation-row-exists branch, the
    `+deleted` variant) *before* touching it, same discipline as the `cf_filter()`/`order_by_cf()`
    work.
  - After the main query returns matching rows, call `read_responsible()` once for the whole
    result batch and merge `info_responsible`/`info_cc` into each row - mirroring
    `calendar_so::search()`'s post-query participant merge exactly.
  - Side benefit, not just enablement: once the join is gone, the main query no longer needs
    `egw_infolog` aliased as `main` at all (that alias only ever existed to disambiguate
    `info_id` against the `users_table` joins) - which also retires the
    `extra_join_filter`/`extra_join_order` alias-swapping workarounds the `cf_filter()`/
    `order_by_cf()` increments had to introduce specifically to cope with that alias.
    `cf_filter()`/`order_by_cf()` could then reference the real table name directly, no swap
    needed - removing debt those two increments introduced, not just avoiding new debt.
  - With the join gone, what's left of `searchInfolog()` (action-link filtering, category
    filter, free-text/RAG search, parent/subs filtering, pagination) is plain WHERE-fragments
    and criteria `process_search()` already knows how to carry (raw SQL fragments as
    numeric-keyed criteria elements) - close enough to `Api\Storage::search()`'s
    `$criteria`/`$filter`/`$join`/`$order_by` contract that delegating the main query to the
    *inherited* `search()` looks realistic, with only link/category/RAG/pid-filtering and the
    responsible-batch-merge left as InfoLog-specific pre/post-processing around that call - but
    this is its own, separate, larger follow-up phase (see plan below), not bundled with the
    join removal itself.

**What does NOT change**: `write()`'s persistence of `info_responsible`/`info_cc` into
`egw_infolog_users` (a separate, already-correct code path); `users_with_open_entries()` (a
different, `DISTINCT account_id`-only query shape with no row-multiplication issue);
`change_delete_owner()` (doesn't touch read/search).

**Proposed phased plan**:
1. Add `read_responsible()`; switch `read()` to use it, dropping its `JOIN`+`GROUP_CONCAT`. Test:
   existing `read()` coverage plus a new case with multiple responsible/cc entries on one entry.
2. In `searchInfolog()`: replace the responsible-filter `JOIN` with an `EXISTS`-fragment; drop
   the display `JOIN`+`GROUP_CONCAT`+`GROUP BY`; call `read_responsible()` on the result batch.
   Test: a new `SearchResponsibleTest.php` covering every `col_filter['info_responsible']` path
   (plain user, group/membership expansion, owner-fallback, `+deleted`), run against the
   *current* code first to lock down today's behavior (zero existing coverage), then against the
   change.
3. (Separate, later phase, only after 1+2 are solid on their own) Explore delegating
   `searchInfolog()`'s remaining FROM/WHERE/ORDER BY assembly to the inherited
   `Api\Storage::search()`/`process_search()`, now that the row-multiplying join and the `main`
   alias workaround are gone.

Ralf's go-ahead: implement phase 1+2 now (see below); phase 3 (delegating to the inherited
`Api\Storage::search()`) stays a separate, later step.

#### Phase 1 — `read_responsible()` + `read()`, 2026-08-20

- Added `Infolog\Storage::read_responsible(array $info_ids): array` - one plain `SELECT * FROM
  egw_infolog_users WHERE info_id IN (...) AND info_res_deleted IS NULL`, no join, returning
  `[info_id => ['info_responsible' => int[], 'info_cc' => string]]`.
- **Behavior-matching details, found while writing this**: `info_cc` must stay a
  comma-separated *string* (not an array) - `infolog_ui.inc.php`/`infolog_tracking.inc.php` both
  `explode()`/`preg_split()` it as a string. `GROUP_CONCAT()` silently skips `NULL` values (but
  keeps `''` ones), so `read_responsible()` filters out `null` `info_res_attendee` rows before
  imploding, to match.
- `read()` now calls `read_responsible()` instead of its own `LEFT JOIN`+`GROUP_CONCAT()`/
  `GROUP BY`.
- **Verified difference from old behavior (intentional, confirmed harmless)**: old `read()`
  returned `info_responsible` values as numeric *strings* (`explode()` of a `GROUP_CONCAT()`
  result, never cast) and `info_cc` as `null` (not `''`) when an entry has no cc at all (a
  `GROUP_CONCAT()` over zero `LEFT JOIN`-ed rows is `NULL`). `read_responsible()` returns `int`s
  and `''` respectively. Checked every caller
  (`infolog_bo`/`infolog_ical`/`infolog_groupdav`/`infolog_ui`/`infolog_tracking`/
  `infolog_import_infologs_csv`/`infolog_export_csv`/`infolog_datasource`) - all comparisons are
  loose (`==`/`array_intersect()`/`array_search()`/`empty()`/truthiness), never `===` or strict
  array functions, so neither difference changes behavior anywhere. Confirmed empirically too:
  wrote `infolog/tests/ReadResponsibleTest.php` (4 tests: single responsible, multiple
  responsible, `info_cc` string shape, no responsible/cc at all), ran it against the *old* code
  first (`git stash` on just `Storage.php`) - 2 of 4 failed exactly on those two differences,
  confirming they're real but (per the caller audit) safe - then against the new code, 4/4 green.
- Full `infolog/tests/` suite: 79 tests / 557 assertions, same 6 pre-existing `CalDAVImportTest`
  failures and same pre-existing `ProjectTemplateTest` risky warning as the established
  baseline - no new regressions.

#### Phase 2 — `searchInfolog()`'s responsible JOIN removal, 2026-08-21

- New `infolog/tests/SearchResponsibleTest.php` (5 tests: direct delegation match, delegation-to-
  someone-else does NOT fall back to owner, no-delegation owner-fallback, fallback doesn't match
  a different user, a *retracted* (soft-deleted) delegation still falls back to owner) - run
  against the pre-Phase-2 code first (`git stash` on `Storage.php` only) to lock down today's
  behavior (zero prior coverage) - 5/5 green - then against the change - 5/5 green.
- `searchInfolog()`'s `col_filter['info_responsible']` case: replaced the
  `responsible_filter($data) OR $this->users_table.account_id IS NULL AND <owner match>`
  condition (relying on the JOIN) with `EXISTS (SELECT 1 FROM egw_infolog_users WHERE
  info_id=main.info_id AND <responsible_filter($data)>) OR NOT EXISTS (SELECT 1 FROM
  egw_infolog_users WHERE info_id=main.info_id<+deleted-aware filter>) AND <owner match>` -
  same semantics, no JOIN needed. `responsible_filter()` itself (the membership-expansion logic)
  is unchanged, just called from inside the `EXISTS` instead of relying on an outer JOIN.
- Dropped both `LEFT JOIN egw_infolog_users`/`attendees` joins, the `GROUP_CONCAT()` columns, and
  the default `GROUP BY main.info_id` (kept the pre-existing `$query['append']`/`$query['having']`
  escape hatches for callers that supply their own - unused by any current caller, kept for
  signature/contract compatibility only). The non-mysql total-count query's now-dangling
  reference to the removed `$group_by` variable was fixed too (it's simply not needed any more,
  since the query no longer multiplies rows).
- Added a batch `read_responsible()` call right after the main query executes (only in the
  default-columns path - the early-return-with-custom-`$query['cols']` path never selected
  `info_responsible`/`info_cc` before either, so nothing changes there), merging
  `info_responsible`/`info_cc` into every returned row - mirrors `calendar_so::search()`'s
  post-query participant merge exactly.
- **Real, unplanned complication found via the test suite**: `infolog_bo::aclFilter()` - the
  ACL SQL builder applied on *every* search via `$acl_filter`, not just the
  `col_filter['info_responsible']` case - also referenced `{$this->so->users_table}.account_id
  IS NULL`/`IS NOT NULL` directly (twice) and called the bare `responsible_filter()` (twice
  more), all assuming the now-removed JOIN was present in the query. Removing the JOIN broke
  ACL filtering outright (`Unknown column 'egw_infolog_users.account_id'`), caught immediately
  by the existing `AclCheckAccessTest.php`/`SearchCustomFieldFilterTest.php` suites. Flagged to
  ralf before proceeding, since this expanded the change into ACL-sensitive code beyond what was
  originally planned; ralf's call: fix `aclFilter()` too rather than revert or half-measure it.
  Fixed the same way - two local closures/fragments (`$active_delegation_exists`,
  `$responsible_exists($users)`) building `EXISTS`/`NOT EXISTS` subqueries against
  `egw_infolog_users`, hardcoding `main.info_id` (safe: every `aclFilter()` caller feeds its
  result straight into `searchInfolog()`, whose `FROM` clause always aliases the table `main`) -
  substituted into all 4 call sites, `responsible_filter()` itself untouched.
- **Pre-existing, unrelated bug found while touching this code, fixed on ralf's explicit
  request (2026-08-21)**: `aclFilter()`'s `$filter == 'user'` branch (used by
  `infolog_groupdav`/`infolog_zpush`/the calendar-include-todos hook to view a *specific other*
  user's tasks) built one of its `Db::expression()` arguments as
  `array('info_owner' => $f_user,)." AND ...` - concatenating a string directly onto an array
  literal with `.`, which PHP silently converts to the literal string `"Array"` (with an
  `E_WARNING`, confirmed via `php -r`) instead of passing a real column-data array to
  `expression()` as a separate argument. This predated this migration (present in the very
  first commit of the migrated code). Fix: split into two separate `expression()` arguments
  (`),"..."` instead of `)."..."`) so the array is processed as real column data. New
  `infolog/tests/AclUserFilterTest.php` (3 tests: no SQL error, owner-with-no-delegation match,
  delegation match) run against the *pre-fix* code first - all 3 failed with the exact predicted
  `Unknown column 'Array' in 'WHERE'` SQL error, confirming the bug and that this test catches
  it - then against the fix - 3/3 green.
- Full `infolog/tests/` suite: 87 tests / 573 assertions, same 6 pre-existing `CalDAVImportTest`
  failures and same pre-existing `ProjectTemplateTest` risky warning as the established
  baseline - no new regressions. Also re-ran
  `importexport/tests/ImportexportBasicImportCsvRegressionTest.php` (green).

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
