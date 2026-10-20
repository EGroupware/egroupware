# SQL fragment type safety (`Api\Db\SqlFragment`)

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

### Open / next

- **`Api\Storage` / `Api\Storage\Base`** - in progress. This is the shared SO-layer base class many
  apps extend (addressbook, infolog, timesheet, etc. via `Contacts\Sql`, `Storage\Base` subclasses) -
  converting it is higher-leverage than any single app, but also used far more widely, so needs the
  same careful "read every call site" approach as calendar, not just the obvious append patterns.
- **Other apps/files still using the raw-fragment convention**, not yet converted (found via
  `grep -rln "db->expression(\|db->column_data_implode(\|db->concat(\|..." `, excluding tests):
  `addressbook/inc/class.addressbook_groupdav.inc.php`, `admin/inc/class.admin_accesslog.inc.php`,
  `admin/inc/class.admin_acl.inc.php`, `admin/inc/class.admin_customfields.inc.php`,
  `api/src/Link/Storage.php`, `api/src/Mail/Account.php`, `api/src/Sharing.php`,
  `api/src/Storage/Customfields.php`, `api/src/Vfs.php`, `developer/src/Langfiles.php`,
  `infolog/inc/class.infolog_bo.inc.php`, `infolog/src/Storage.php`,
  `notifications/inc/class.notifications_push.inc.php`, `timesheet/inc/class.timesheet_bo.inc.php`,
  plus the various `*/setup/tables_update.inc.php` schema-migration files (lower priority - one-time
  migration code, not live request-handling).
- The global default flip (opt-out model) is explicitly a *later* step per the original design ask -
  not scheduled, waits until enough of the above is converted that flipping it wouldn't break
  everything still unconverted.
- No 26 backport for any of this (explicit decision) - the actual vulnerability is already closed on
  both branches via the two entry-point patches; this is defense-in-depth architecture that should
  soak on master first.
