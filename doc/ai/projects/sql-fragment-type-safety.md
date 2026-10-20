# SQL fragment type safety (`Api\Db\SqlFragment`)

## App conversion tracker

One row per app that ships by default (core monorepo + `egroupware/*` composer packages) or is an
EPL (commercial, `EGroupwareGmbH/*`) app. "Converted" means: every raw SQL fragment *that app's own
code* builds uses `Db\SqlFragment`, and (where the app has its own `Api\Db` clone to scope it to)
`requireSqlFragmentObjects(true)` is enabled for it. An app with no own SO-layer query building (pure
UI/config apps) is marked N/A rather than chased for conversion.

### Core (part of the main `egroupware/egroupware` repo)

| App | Status |
|---|---|
| calendar | **Done** (pilot, commit `196de24beb`) - `requireSqlFragmentObjects(true)` enabled on its own `Db` clone |
| api (`Api\Storage`/`Storage\Base`, `Contacts\Sql`/`Contacts\Storage`, `Accounts\Sql`) | **Done** for the pieces with their own `Db` clone (`Contacts\Sql`, used by addressbook/Accounts - see below); `Link\Storage`, `Sharing`, `Mail\Account` had their own fragments wrapped too but are static classes with no clean per-instance clone point, so `requireSqlFragmentObjects()` isn't enabled for them yet |
| addressbook | **Done** - via `Contacts\Sql`'s opt-in (addressbook_bo/`Api\Contacts` share that backend); `Contacts\Storage`'s own `tid != deleted` filters converted too |
| admin | Not started |
| aiassistant | Not started |
| archive | Not started |
| developer | Not started |
| emailadmin | Not started |
| etemplate | N/A - template engine, no own SO layer |
| filemanager | Not started |
| home | Not started |
| importexport | Not started |
| infolog | **Done** - `requireSqlFragmentObjects(true)` enabled on its own `Db` clone (`Infolog\Storage::__construct()`) |
| kdots | N/A - UI framework, no own SO layer |
| mail | Not started |
| notifications | Not started |
| phpgwapi | N/A - deprecated legacy compat shim |
| preferences | Not started |
| resources | **Done** - `requireSqlFragmentObjects(true)` enabled on its own `Db` clone (`resources_so::__construct()`) |
| timesheet | **Done** - `requireSqlFragmentObjects(true)` enabled on its own `Db` clone (`timesheet_bo::__construct()`) |

### Default composer packages (`egroupware/*` in `composer.json`, public `EGroupware/*` repos)

| App | Status |
|---|---|
| activesync | Not started |
| aitools | Not started |
| bookmarks | Not started |
| collabora | Not started |
| news_admin | Not started |
| openid | Not started |
| projectmanager | Not started |
| rag | Not started |
| registration | Not started |
| rocketchat | Not started |
| smallpart | Not started |
| status | Not started |
| swoolepush | Not started |
| tracker | Not started |
| webauthn | Not started |

### EPL (commercial, private `EGroupwareGmbH/*` repos)

| App | Status |
|---|---|
| stylite (EPL core functions) | Not started |
| esyncpro | Not started |
| invoices | Not started |
| kanban | Not started |
| licenses | Not started |
| managementserver | Not started |
| records | Not started |
| vidopro | Not started |

Not covered by either list above (separate repos, but neither a default composer package nor EPL -
`guacamole`, `knowledgebase`, `phpbrain`, `profitbricks`, `ranking`, `schulmanager`, `untissync`,
`wiki`): out of scope for this project unless/until requested.

## Motivation

GHSA-vx89-j2x7-5wp2: `calendar.calendar_ui.ajax_get` let a client-supplied array (an integer-keyed
JSON object decodes to a PHP array with an integer key) reach `calendar_so::read()`'s
`$where = $ids` assignment, skipping the calendar ACL and private-event redaction on the way down.
`Db::column_data_implode()` then treated an integer array key as "this is a pre-built, trusted raw
SQL fragment, splice it in unquoted" - a convention used pervasively and legitimately throughout the
codebase (`$where[] = 'cal_start >= '.$ts;`), but indistinguishable, from inside `Api\Db`, from an
attacker-influenced array that merely happens to contain an integer key.

The two actual entry points (`calendar_ui::ajax_get()`, `calendar_uilist::action()`) were patched
directly first (commits `b2fe5e684b`, `affb118589`, backported to 26) by rejecting non-scalar event
ids before they reach `calendar_bo::read()`. This project is the follow-up: closing the vulnerability
*class* at its actual source, `column_data_implode()`, so the next similarly-shaped bug anywhere in
the codebase fails loudly instead of silently.

## Design

- **`Api\Db\SqlFragment`** (new, `api/src/Db/SqlFragment.php`) - an immutable value object wrapping a
  raw SQL string. `__toString()` returns the SQL. Implements read-only `ArrayAccess` (offset access
  only, writes throw `\LogicException`) so it survives being treated like a string by legacy code
  doing eg. `$field[0]` - found necessary when `Storage\Base::is_cf()` did exactly that.
  Deliberately stays a `SqlFragment` instance through every intermediate step (stored in an array,
  passed to another method, etc.) rather than being cast back to a string early - casting back to a
  string anywhere along the way would reintroduce the exact bug this class exists to prevent, since
  by the time it reached `column_data_implode()` it would again be an indistinguishable plain string
  at an integer key.
- Api\Db's own fragment-building methods (`expression()`, `concat()`, `group_concat()`,
  `regexp_replace()`, `strpos()`, `unix_timestamp()`, `from_unixtime()`, `date_format()`,
  `to_double()`, `to_int()`, `to_varchar()`, `column_data_implode()`) moved to static, fully-typed
  methods on `Db\SqlFragment` (each takes the originating `Db $db` as its first parameter, since the
  SQL dialect/driver state isn't separable from the connection). `Api\Db` keeps thin stub methods
  delegating to them, returning the typed object (not a string).
  `quote()`/`name_quote()` deliberately NOT moved - they're leaf value/identifier quoters, not
  fragment combinators, and converting them carries far higher blast-radius risk for no benefit.
- **`Api\Db::requireSqlFragmentObjects(?bool $require = null)`** - per-instance getter (no arg) /
  fluent setter (`true`/`false`). Once enabled, `column_data_implode()`'s integer-key branch throws
  `Db\Exception\InvalidArgument` for a value that isn't a `SqlFragment`, instead of using it as an
  unquoted raw fragment. Off by default (back-compat with every not-yet-converted caller).
  `Db\Exception\InvalidArgument` deliberately extends plain `\InvalidArgumentException`, **not**
  `Db\Exception` - a generic `catch (Db\Exception)` around a query must not silently swallow it.
- **Opt-in granularity decision**: per-`Api\Db`-*instance*, not a global static flag and not a
  per-call parameter. Rationale (from the design discussion): a global flag can't be enabled for one
  converted app while others still share `$GLOBALS['egw']->db` unconverted; a per-call parameter
  would need threading through every call site even ones already safe. An app that has fully
  converted its own raw-fragment building clones the shared connection once (in its SO-layer
  constructor) and calls `requireSqlFragmentObjects(true)` on the clone - enforcement then applies
  only to that app's own queries.
- Found and fixed one core-framework instance of the same unguarded pattern while converting
  calendar: `Db::replaceAsUpdate()` (the REPLACE-avoidance UPDATE fallback inside `insert()`,
  reachable by any app with a unique-keyed upsert) built its own `"col=DEFAULT"` raw fragment the
  same unguarded way - now `SqlFragment::raw()`-wrapped too.

## TEMPORARY: per-instance clone pattern - revert when enforcement becomes the default

Each app that opts in constructs its own clone of the shared `Api\Db` connection, since cloning (not
mutating the shared global instance) is what gives per-app scoping. **This is a stopgap** for the
transition period while only some apps have converted. Once `requireSqlFragmentObjects()` becomes the
default (opt-out model - the end state this project is working towards, flipped once *all* raw
fragment call sites across the codebase have been converted), every one of these clones becomes
redundant and should be reverted back to using `$GLOBALS['egw']->db` directly.

**Clone locations to revert at that point** (keep this list current as more apps convert):

- `calendar/inc/class.calendar_so.inc.php` `__construct()` (~line 151):
  `$this->db = (clone $GLOBALS['egw']->db)->requireSqlFragmentObjects(true);` → revert to
  `$this->db = $GLOBALS['egw']->db;`

## Status

### Done

- **Infrastructure** (commit `a46f539449`, master only): `Db\SqlFragment`, `Db\Exception\InvalidArgument`,
  `Api\Db::requireSqlFragmentObjects()`, the `replaceAsUpdate()` fix, stub conversions, full test
  coverage (`api/tests/Db/SqlFragmentTest.php` + updated `ExpressionTest.php`/`PortabilityTest.php`).
- **Calendar pilot** (commit `196de24beb`, master only): every raw SQL fragment calendar builds
  itself converted - `calendar_so.inc.php` (61 sites: `search()`, `read()`, and everything else),
  `calendar_boupdate.inc.php`'s `find_event()` (11 sites - its `$query` array flows into
  `calendar_so::search()`'s `$params['query']`, which merges straight into `$where`), and
  `calendar_uilist.inc.php`'s NextMatch column-filter builder (2 sites - flows in via
  `$params['sql_filter']` as an array). `requireSqlFragmentObjects(true)` enabled on calendar's own
  `Db` clone. Verified: full `calendar/tests/` suite (637 tests) green, exactly matching the
  pre-existing baseline (50 known CalDAV/REST real-HTTP-infra errors + 5 known unrelated
  `ResetParticipantStatusTest` date-flake), stable across repeat runs.

  **Finding-the-full-set lesson**: a simple grep for `$where[] =`/`$query[] =`/`$col_filter[] =`
  missed several real sites - raw fragments can also appear as bare (no `=>`) elements *inline*
  inside an `array(...)` literal passed directly as a `select()`/`update()`/`delete()`/`insert()`
  argument (eg. `array('cal_id' => $id, 'cal_recur_date != 0')`), as elements of a differently-named
  variable (`$where2`, `$event[]` as the `$data` argument to `update()`), or as an unkeyed entry
  inside an array passed to `expression()`. Converting an app for real requires reading every
  `$this->db->select/insert/update/delete(` call site's arguments directly, not just grepping for
  the append idiom.

  **Second lesson, learned the hard way (commit `06d4f00748`, a live CI break on master)**:
  "converted" also means *every file that reaches that class's Db instance*, not just the files
  that obviously look like its SO layer. `calendar_groupdav.inc.php` (the CalDAV HTTP handler) built
  the exact same `[$column => $id, 'raw sql']` shape in three places, reaching `calendar_so`'s
  now-strict `Db` clone - invisible to local PHPUnit (which never exercises the groupdav HTTP layer)
  and only surfaced as uniform 401s in CI's real-HTTP CalDAV tests. Before enabling enforcement for
  an app, grep that app's *entire* directory for `$this->bo->`/`$this->so->`/`$this->db->` call
  sites, not just its `*_so.inc.php`/`*_bo.inc.php` files.

- **`Api\Storage` / `Api\Storage\Base`** (commit `c0a028f33c`, corrected by `ca94a23f2a`): own
  fragment-building converted - `search2criteria()`'s two returns, `parse_search()`'s several
  int-keyed appends, `search()`'s `$db_filter` building (`Storage::process_search()` has two more
  of its own). Added `Db\SqlFragment::raw()` idempotency (returns an already-wrapped instance
  unchanged) so these call sites don't need an `instanceof` check first.

  **Third lesson, a self-inflicted one (commit `ca94a23f2a`)**: the first pass at this also
  auto-wrapped the "direct sql updates"/"keep sql fragments"/"copy direct SQL parts from $keys"
  int-key passthroughs in `save()`/`delete()`/`read()`/`search()` - but those take the raw value
  straight from the *caller's own* `$keys`/`$filter`/`$criteria` parameter, not something
  `Storage\Base` builds itself. Auto-wrapping a plain string there launders an attacker-controlled
  value into looking like a deliberately-built trusted fragment *before*
  `column_data_implode()`'s check ever sees it - making `requireSqlFragmentObjects()` a no-op for
  exactly the bug class it exists to catch. Fixed by reverting those passthrough points to plain,
  unwrapped passthrough; a caller that wants a raw fragment to survive strict mode must wrap it
  itself before calling in. Regression-tested in `api/tests/Storage/RequireSqlFragmentObjectsTest.php`
  (confirmed to fail against the pre-fix code by temporarily reintroducing it). The rule going
  forward: only wrap a value a method *constructs itself* from validated/trusted inputs (a matched
  column name, a `quote()`-escaped value, a hardcoded keyword) - never a value merely passed through
  from a caller's own parameter.

  `requireSqlFragmentObjects()` is opt-in per app (see tracker above); per the second lesson above,
  before any app extending `Api\Storage` opts in, that app's *entire* directory needs the same
  full-file sweep calendar's CalDAV handler taught us to do, not just its `*_bo`/`*_so` classes.
- **Timesheet pilot** (second app, commit `167b5ae74e`): every raw SQL fragment timesheet builds
  itself converted - `timesheet_bo.inc.php`'s `search()` (status filters) and `checkConflicts()`'s
  `$where` array (the `ts_status`/`cat_id`/`ts_start` range conditions, inline *and* appended - the
  first lesson above about inline array-literal elements applies here too), `get_last_end()`'s
  `ts_start BETWEEN ...` condition, and `timesheet_ui.inc.php`'s `date_filter()` result assigned
  into `$query['col_filter'][0]` (an ORIGINATOR case - built server-side by
  `Api\DateTime::sql_filter()` from internally-resolved, quoted date values, not client SQL) and
  `timesheet_export_csv.inc.php`'s range-filter builder. `requireSqlFragmentObjects(true)` enabled
  on timesheet's own `Db` clone in `timesheet_bo::__construct()` (unlike `calendar_so`,
  `Api\Storage::__construct()` defaults `$no_clone=true`, so this is a new clone, not flipping a
  flag on an existing one). Verified: full `timesheet/tests/` suite green except one pre-existing,
  unrelated failure (`TimesheetUiGetRowsTest::testPartialMonthSumIsMarkedPartial` - a date-dependent
  partial-month-sum flag bug, reproduces identically with strict mode on or off, not investigated
  further here) and the 3 known `LinkedFilterToolCallTest` output-buffer "risky" warnings.
- **Resources pilot** (third app, commit `a2f42ce0b6`): `resources_bo::get_rows()`'s two
  self-built filter conditions (`accessory_of != -1`, `deleted IS NOT NULL`) converted.
  `requireSqlFragmentObjects(true)` enabled on resources' own `Db` clone in
  `resources_so::__construct()` (the actual `Api\Storage` subclass; `resources_bo` is a thin
  composition wrapper holding `$this->so`, same shape as infolog). New regression test
  (`resources/tests/ResourcesBoGetRowsSqlFragmentTest.php`, no prior test coverage existed for this
  app) confirmed to fail pre-fix, pass post-fix.
- **Api/Addressbook pilot** (fourth pilot, commit `0c91c84904`): the first to cover a backend
  *shared across apps* - `Api\Contacts\Sql` (addressbook's SQL backend) is also reached via
  `Api\Accounts\Sql` for account/group lookups (`addressbook_bo`'s "accounts as contacts"
  integration). Converted `Contacts\Sql`'s own fragments (cat_id/shared_by/shared_with filters, the
  ACL owner/private/shared OR-condition, the negated account_id filter, list_etag updates) and
  `Contacts\Storage`'s own `tid != deleted` filters (the composition layer one level up, easy to
  miss since it's not the `*_so`/`*_bo` naming pattern). `requireSqlFragmentObjects(true)` enabled
  on `Contacts\Sql`'s own `Db` clone.

  **Fourth lesson - shared-backend blast radius**: because this backend is shared, *every caller*
  that builds its own filter arrays against it needed the same treatment, not just addressbook's
  own code - found via real test failures, not by reading alone: `Api\Accounts\Sql::search()`'s
  own `'groups'`/`'both'`/account_id-negation filters (reached through `Api\Contacts::search()`),
  and `addressbook_groupdav.inc.php`'s CardDAV REPORT prop-filter parser and sync-collection-report
  token filter (exactly the shape that broke calendar's CalDAV handler in the first pilot - this
  project's "sweep the whole app directory" lesson keeps paying for itself). Also found and fixed:
  `Api\Storage::process_search()` had two of its own raw fragments (auto-id and ambiguous-column
  matching, lines handling `$criteria[$this->autoinc_id]` and a string-keyed column rename) missed
  in the very first `Api\Storage` conversion - latent until a real caller (`addressbook_groupdav`'s
  CardDAV multiget) exercised that exact branch under strict mode. New regression test
  `addressbook/tests/ContactsSqlFragmentTest.php`; one assertion in it (temporarily reverting the
  "search all addressbooks" ACL fragment wrap) confirmed to fail pre-fix, pass post-fix. A
  `in_array("account_type='g'", $filter)` check in `Contacts\Sql::search()` (used to decide a
  MySQL-only sort optimization) had to be changed to `in_array(..., array_map('strval', $filter))`,
  since `Accounts\Sql` now hands that literal in as a `Db\SqlFragment` object, not a plain string -
  a reminder that wrapping a value changes its *identity*, not just its type-safety, for any
  caller-side code that compares filter array contents by value.

  `Link\Storage`, `Sharing`, and `Mail\Account` had their own raw fragments wrapped too (found via
  the same sweep), but are static classes assigning `$GLOBALS['egw']->db` directly with no
  per-instance clone point - `requireSqlFragmentObjects()` isn't enabled for them; the wraps are
  inert (SqlFragment casts to the same string either way) until a clone mechanism is added.
- **Infolog pilot** (fifth pilot, cross-referencing the separate
  `doc/ai/projects/infolog-storage-migration.md`, which already moved infolog's SO layer onto
  `Api\Storage` and made `Infolog\Storage::$db` public specifically so `infolog_bo` could build
  fragments against it): converted `Infolog\Storage`'s own fragments (custom-field NOT-IN filters
  in `read()`/`readRows()`, the attendee `info_res_deleted`/`account_id NOT IN` conditions,
  `info_etag=info_etag+1`, `anzSubs()`'s status filter, `users_with_open_entries()`'s date-range
  condition) and the single real enforcement boundary in `infolog_bo::searchInfolog()`: the whole
  ACL/status/date/responsible/cat_id `$filtermethod` string is built up via `.=` concatenation
  (never touching an array until the very end), then placed into `$filter[0]` - that one line is
  the wrap that matters; the intermediate `$query['col_filter'][]` entries that feed it get
  stringified via `.=` before ever reaching an array-based enforcement point, so they were left
  unwrapped (would be pure cosmetic consistency, not a functional requirement). Also wrapped a
  second `#`-prefixed-customfield NOT-IN filter in `infolog_bo.inc.php` itself (`$this->so->db`
  being public let the BO layer build this one directly, same NOT-IN pattern as the SO layer's).
  `requireSqlFragmentObjects(true)` enabled on infolog's own `Db` clone in
  `Infolog\Storage::__construct()`.

  **Fifth lesson - `expression()`'s bare-array argument is the same convention, found by a real
  test failure**: `Api\Storage::cf_filter()` (shared by every `Api\Storage` app, not just infolog)
  passes a *bare single-element array* - `array($raw_condition_string)`, no `=>` - directly as the
  `$where`-like argument to `$this->db->expression()` for "text"-type customfield filters.
  `expression()` runs that array through the exact same `column_data_implode()` machinery as
  `select()`/`update()`'s own `$where`/`$data` arrays, so the same int-key convention applies
  there too - invisible to a grep for `[] =`/inline-array-literal patterns scoped to
  `select`/`insert`/`update`/`delete` calls, since this one goes through `expression()` instead.
  `cf_filter()` also had two more unwrapped `$filter[]` appends (auto-id and ambiguous-column
  matching, the same shape as `process_search()`'s own pair above) found the same way. Caught by
  `infolog/tests/SearchCustomFieldFilterTest.php` (pre-existing, not written for this project) -
  a reminder that an app's *existing* test suite can be as valuable as a purpose-built regression
  test for finding these, provided it's actually run after opting in.

### Open / next

- **Other apps/files still using the raw-fragment convention**, not yet converted (found via
  `grep -rln "db->expression(\|db->column_data_implode(\|db->concat(\|..." `, excluding tests):
  `admin/inc/class.admin_accesslog.inc.php`, `admin/inc/class.admin_acl.inc.php`,
  `admin/inc/class.admin_customfields.inc.php`, `developer/src/Langfiles.php`,
  `notifications/inc/class.notifications_push.inc.php`,
  plus the various `*/setup/tables_update.inc.php` schema-migration files (lower priority - one-time
  migration code, not live request-handling).
  `api/src/Storage/Customfields.php` and `api/src/Vfs.php` were checked and have nothing to
  convert (`self::$db` is set but never used for a query in the former; the latter's actual SQL
  backend lives in `Vfs/Sqlfs/*`, out of scope here, not checked yet).
- The global default flip (opt-out model) is explicitly a *later* step per the original design ask -
  not scheduled, waits until enough of the above is converted that flipping it wouldn't break
  everything still unconverted.
- No 26 backport for any of this (explicit decision) - the actual vulnerability is already closed on
  both branches via the two entry-point patches; this is defense-in-depth architecture that should
  soak on master first.

**Unrelated, but found while tracing `Api\Storage\Base::search2criteria()` callers for this
project**: `search2criteria()`'s own first line (`if (empty($_pattern) || is_array($_pattern) ||
...) return $_pattern;`) returns a non-string pattern completely unprocessed, which several callers
then wove straight into generated SQL - the same vulnerability class as GHSA-vx89-j2x7-5wp2
(calendar's original finding), independently present in `timesheet/src/ApiHandler.php`'s REST
filter handling, `infolog_bo::searchInfolog()`'s free-text search, and `Api\Accounts\Sql::search()`.
This is a standalone, pre-existing bug with zero dependency on `Db\SqlFragment` - fixed with plain
`is_string()` guards (commit `77355c16a1`), pushed to master and backported to 26 immediately,
independent of this longer-running project.
