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
  unquoted raw fragment. Originally off by default (back-compat with every not-yet-converted
  caller); now **on by default** since the global flip (see "Global default flip" below) - this
  line is kept for historical context, not as current behavior.
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

## Usage guide

Practical reference for writing or fixing code against `Db\SqlFragment` - the design rationale
above explains *why* it exists, this is *how* to actually use it day to day.

### The one rule: when to wrap, when not to

Only wrap a value **your own code builds itself**, from inputs you already trust (a hardcoded
keyword, a matched/validated column name, a `$db->quote()`-escaped value, an internally-computed
number). **Never** wrap a value that is merely passed through from a caller's own parameter - doing
so launders whatever the caller handed you into looking like a deliberately-built trusted fragment,
which defeats the entire mechanism for exactly the bug class it exists to catch (see the "Third
lesson" below - this was a real, shipped mistake, found and reverted during this project).

If you're not sure which case you're in, ask: "did I just build this string myself in the lines
above, or did it arrive as an argument?" Built-it-yourself -> wrap it. Arrived-as-an-argument ->
leave it alone; the *caller* is responsible for wrapping it before handing it to you, if it's really
meant to be a raw fragment at all.

### Basic usage

A raw fragment at an integer array key, the historical "no column name given" convention:

```php
$filter = [
    'account_type' => 'u',                                  // normal column => value, always safe
    Api\Db\SqlFragment::raw('account_id > 0'),               // hardcoded literal - safe to wrap
    Api\Db\SqlFragment::raw('account_lid '.$like.' '.$db->quote($name)), // quoted value - safe to wrap
];
$db->select('egw_accounts', '*', $filter, __LINE__, __FILE__);
```

Everything else about `select()`/`update()`/`delete()`/`insert()`'s `$where`/`$data` arrays is
unchanged - normal `'column' => $value'` entries are always quoted automatically regardless of
`SqlFragment`, strict mode or not.

### Db's own fragment-building helpers

`Api\Db` still exposes its original builder methods - `expression()`, `concat()`, `group_concat()`,
`regexp_replace()`, `strpos()`, `unix_timestamp()`, `from_unixtime()`, `date_format()`, `to_double()`,
`to_int()`, `to_varchar()`, `column_data_implode()` - each now returns a `Db\SqlFragment` instead of
a plain string (they're thin stubs delegating to the static `Db\SqlFragment::*` equivalents). Using
them is unaffected by this whole project: carry on casting/concatenating the result as before, or
pass it straight into a `$where`/`$data` array - it's already a `SqlFragment`, no extra wrapping
needed:

```php
$where = [
    'cat_id' => $cat_id,
    $db->expression('addressbook', ['contact_owner' => $owner], ' OR ', ['contact_private' => 0]),
];
```

### Opting in (forcing strict mode)

Pass `true` as `__construct()`'s second argument (for a brand-new connection) or call
`requireSqlFragmentObjects(true)` on a clone:

```php
$this->db = (clone $GLOBALS['egw']->db)->requireSqlFragmentObjects(true);
```

This is the idiom every converted pilot app (calendar, infolog, resources, addressbook/Contacts,
timesheet, projectmanager, tracker) already uses - now redundant with the global default but kept
for apps that want to stay strict even if an admin opts the whole instance (or the whole install)
out, and harmless to leave in place. An explicit `true` always wins over both opt-out mechanisms
below, since it's set directly on the instance itself.

### Two ways to opt out

**1. Per-`Db`-instance** - for one specific app/class that still uses the legacy unwrapped
convention throughout, rather than auditing every call site in it. Clone the shared connection and
opt out explicitly on the clone, same shape as opting in, just inverted:

```php
$this->db = (clone $GLOBALS['egw']->db)->requireSqlFragmentObjects(false);
```

Or, if constructing a brand-new connection rather than cloning, pass `false` as `__construct()`'s
second argument: `new Api\Db($db_data, false)`. Scoped to that one instance only - every other app
sharing the main `$GLOBALS['egw']->db` stays enforced. Used for apps never covered by this
conversion project and realistically never going to be (wiki, phpbrain, ranking, schulmanager - see
the app tracker below) - a deliberate, permanent, auditable exception per app, not a bug. Prefer
this over the global opt-out below whenever the problem is isolated to one app you can identify -
it protects everything else on the install that the global switch would otherwise also expose.

**2. Global, install-wide, via `header.inc.php`** - for an admin who hit a 3rd-party/unconverted app
they don't control and can't patch themselves. Set in that instance's own `header.inc.php`:

```php
$GLOBALS['egw_info']['server']['opt_out_sql_fragment'] = true;
```

This reverts the *entire* install back to the old, unenforced behavior - every `Api\Db` instance
that doesn't explicitly force `true` (see "Opting in" above), not just the one app that's actually
broken. At the admin's own risk, meant as a stop-gap until that app's own maintainer fixes it or
(better) gets opted out individually per instance as in option 1 above - not a long-term setting to
just leave on. Resolved automatically, with no code changes needed anywhere: `Api\Db::__construct()`
reads this config value itself for every new instance (see "Global default flip" below for exactly
how). A class that was built with `new Api\Db($data, true)` or opted in via
`requireSqlFragmentObjects(true)` stays strict regardless of this setting - the global opt-out only
affects instances that never explicitly chose a side.

### Gotchas found the hard way (read before touching any of this)

- **A nested `column_data_implode()`/`select()`/`update()`/`delete()` call needs its *own* wrapping,
  independent of what happens to its result afterward.** Wrapping the *outer* expression in
  `SqlFragment::raw()` does not exempt an *inner* call built from an unwrapped array - each call
  enforces its own `$array` argument. Real bug, found live: `kanban/src/Hooks.php::get_actions()`
  wrapped its outer expression correctly but built it from `$db->column_data_implode(' OR ', $where)`
  where `$where`'s own entries were still bare strings - that inner call threw regardless of the
  outer wrap.
- **A plain string `$where`/`$data` (not an array) bypasses the mechanism entirely, by design** -
  `column_data_implode()`'s first line is `if (!is_array($array)) return $array;`. This is the
  historical, still-legal "raw SQL string" calling convention, unrelated to the integer-key
  convention `SqlFragment` guards.
- **`expression()`'s variadic string arguments are spliced in verbatim, unconditionally** - per its
  own docblock, a `string` argument is raw by definition (`case 'string': $sql .= $arg;`). Only its
  `array` arguments go through the guarded integer-key convention.
- **`select()`'s `$join` parameter is raw SQL, no quoting, ever** - its own docblock says so
  outright. This was the actual sink of GHSA-3f9x-47f8-j6fw (the addressbook `order`-parameter SQL
  injection) - `Storage::cf_filter()` built `$join` as a plain concatenated string. Fix that class of
  bug by validating whatever feeds `$join` before it gets there, not by anything in `SqlFragment`.
- **`insert()`'s own `$data` does *not* honour the integer-key-as-raw-fragment convention** when
  called with `$use_key` as `'VALUES'` or `false` (its single- and multi-row forms) - the branch in
  `columnDataImplode()` requires `$use_key === true` exactly, so an int-keyed entry there just gets
  `$db->quote()`'d as an ordinary value instead of spliced raw. Not a security bug (the opposite,
  actually - it's safe by accident), but a correctness footgun: a `SqlFragment::raw()`-wrapped value
  passed to `insert()` will silently get mangled through `quote()`, not spliced in as SQL. Only
  `update()`/`select()`/`delete()`'s `$where` (always `$use_key === true`) honour the convention.
- **Enforcement is per-`Db`-*instance*, not per-app** - even a fully "converted" app is only
  protected for code that actually reaches *its own* strict clone. Nothing stops a new call site
  (future code, a shared library, an EPL hook) from reaching a *different*, non-strict instance
  instead - several of this project's own "lessons" below are exactly this shape of gap, found via
  live breakage, not by any mechanical check. The 2026-11-09 global default flip closes this for the
  *shared* instance specifically, but the same risk still applies to any other instance an app
  explicitly opts out.
- **Don't trust a "wrapped, no opt-in point yet" entry in the tracker below as proof of
  correctness.** Flipping the global default on 2026-11-09 turned a large number of these from
  "future-proofing, never actually exercised under strict mode" into "live, enforced, for the first
  time" - and immediately surfaced genuine, previously-invisible misses in code that had been marked
  done for months (`Accounts\Sql::name2id()`, `Session::login_blocked()` - hit on every single login
  - `Contacts.php`, `Sharing.php`, `Vfs/Sharing.php`, `Storage\History`, `Mail\Credentials`, plus
  live breakage in webauthn, kanban, records and `Vfs::checkLock()`). Being listed as "wrapped" means
  someone wrapped what they found at the time, not that strict mode was ever actually exercised
  against it.

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
| api (every file under `api/src/` using `Api\Db` directly, not just `Api\Storage` subclasses - see the full-sweep note below) | **Done** for the pieces with their own `Db` clone (`Contacts\Sql`, used by addressbook/Accounts - see below); every other file found building its own raw fragment (`Link\Storage`, `Sharing`, `Mail\Account`, `Accounts\Import`, `Auth\Sql`, `Preferences`, `Session`, `Storage\Customfields`, `Storage\History`, `Acl`, `Translation`, `Egw\Applications`) had it wrapped too, but these are all static classes or always use the shared global `Db` instance with no per-instance clone point, so `requireSqlFragmentObjects()` isn't enabled for them yet |
| addressbook | **Done** - via `Contacts\Sql`'s opt-in (addressbook_bo/`Api\Contacts` share that backend); `Contacts\Storage`'s own `tid != deleted` filters converted too |
| admin | **Own fragments wrapped** - `admin_acl`/`admin_accesslog`/`admin_customfields`/`admin_cmd_change_account_id`/`admin_cmd_delete_account`; all reach shared/non-strict `Db` instances, no opt-in point |
| aiassistant | **Own fragments wrapped** (`So.php`); no opt-in point (plain class, shared `Db`) |
| archive | N/A - no `Api\Db` usage found |
| developer | **Own fragments wrapped** - `Langfiles.php` (a real `Api\Storage\Base` subclass, missed by the original scoping grep) and `TranslationTools.php`; not opted in yet |
| emailadmin | N/A - no `Api\Db` usage found (only `setup/tables_update.inc.php`) |
| etemplate | N/A - template engine, no own SO layer |
| filemanager | **Own fragments wrapped** (`filemanager_shares.inc.php`, delegates to `Vfs\Sharing`/`Api\Sharing` - already wrapped in the api/src sweep) |
| home | N/A - no `Api\Db` usage found (only `setup/tables_update.inc.php`) |
| importexport | **Own fragments wrapped** - `importexport_definitions_bo.inc.php`/`importexport_definitions_ui.inc.php` |
| infolog | **Done** - `requireSqlFragmentObjects(true)` enabled on its own `Db` clone (`Infolog\Storage::__construct()`) |
| kdots | N/A - UI framework, no own SO layer |
| mail | N/A - no `Api\Db` usage found outside `api/src/Mail/Account.php` (already covered in the api/src sweep); IMAP/JMAP-based, not its own SQL storage |
| notifications | **Own fragments wrapped** - `notifications_push`/`notifications_ajax`/`notifications_popup.inc.php` |
| phpgwapi | N/A - deprecated legacy compat shim |
| preferences (app) | N/A - no `Api\Db` usage found |
| resources | **Done** - `requireSqlFragmentObjects(true)` enabled on its own `Db` clone (`resources_so::__construct()`) |
| timesheet | **Done** - `requireSqlFragmentObjects(true)` enabled on its own `Db` clone (`timesheet_bo::__construct()`) |

### Default composer packages (`egroupware/*` in `composer.json`, public `EGroupware/*` repos)

Each of these ships as its **own separate git repository** (confirmed the hard way mid-session - a
`git status`/`git diff` from the main repo's root silently shows nothing for them). Commit per-app,
in that app's own checkout, not in the main repo.

| App | Status |
|---|---|
| activesync | N/A - no `Api\Db` usage found |
| aitools | **Own fragments wrapped** (`TriggerState.php`) |
| bookmarks | **Own fragments wrapped** (`bookmarks_so.inc.php`, a real `Api\Storage` subclass missed by the original scoping grep) |
| collabora | **Own fragment wrapped** (`Wopi.php`) |
| news_admin | **Own fragments wrapped** (`news_admin_bo.inc.php`, a real `Api\Storage\Base` subclass missed by the original scoping grep) |
| openid | **Own fragments wrapped** - `Repositories/AccessTokenRepository.php`/`ClientRepository.php` (real `Api\Storage\Base` subclasses) and `RefreshTokenRepository.php` |
| projectmanager | **Done** - `requireSqlFragmentObjects(true)` enabled on each of its 4 independent SO classes' own `Db` clone (`projectmanager_so`, `projectmanager_elements_so`, `projectmanager_constraints_so`, `projectmanager_pricelist_so`) |
| rag | **Own fragments wrapped, see the critical cross-app finding below** - `Embedding.php`/`Embedding/Base.php` |
| registration | **Own fragment wrapped** (`registration_bo.inc.php`) |
| rocketchat | N/A - no `Api\Db` usage found |
| smallpart | **Own fragments wrapped** - `So.php`/`Overlay.php`/`Bo.php` |
| status | N/A - no `Api\Db` usage found |
| swoolepush | N/A - no `Api\Db` usage found |
| tracker | **Done** - `requireSqlFragmentObjects(true)` enabled on both `tracker_so` and `tracker_escalations`'s own `Db` clone |
| webauthn | **Done** (follow-up commit `83ee929`) - two more raw `pubkey_deleted` fragments found live-breaking login (`findOneByCredentialId()`/`findAllForUserEntity()`), missed by the original wrap; no opt-in point (static-ish repository class, shared `Db`), so this only mattered once the global default flip (below) made it live |

`projectmanager` and `tracker` are now full pilots too - `requireSqlFragmentObjects(true)` enabled on
each of their own `Db` clone(s), per the user's explicit follow-up instruction (see the Ninth lesson
below for what that flip surfaced). Every other app in this table is still wrap-only (no opt-in point
found, or not attempted yet) - good next candidates if continuing this project, since their fragments
are already wrapped and ready.

### EPL (commercial, private `EGroupwareGmbH/*` repos)

| App | Status |
|---|---|
| stylite (EPL core functions) | **Own fragments wrapped, several were live cross-app regressions - see the Tenth/Eleventh lessons below** (commit `922d0c6`); one wrap (`stylite_license_bo.inc.php::concurrent_users()`) had to be reverted (`8264b95`) because the file's bytes are pinned by two hard-coded tamper-detection hashes (`class.stylite_license.inc.php`'s `md5_file()` check, `StreamWrapper::t()`'s `sha1_file()` check) - re-applied together with regenerated hashes via the repo's own `inc/license.php` maintenance script (commit `c88d054`/`49b9e98` on master/26) |
| esyncpro | **Own fragment wrapped** (`esyncpro_ui.inc.php`) |
| invoices | **Own fragments wrapped** (`ApiHandler.php`/`Bo.php`) |
| kanban | **Own fragments wrapped** (`Bo.php`/`Hooks.php`/`Bo/Column.php`); **follow-up fix** (commit `5d79282`) - `Hooks::get_actions()`'s *inner* `column_data_implode()` call still had bare strings in its own `$where` array, even though the outer expression was already wrapped (see the "nested call" gotcha above); live-broke the addressbook list page once the global flip landed |
| licenses | **Own fragment wrapped** (`licenses_ui.inc.php`) |
| managementserver | **Own fragments wrapped, every file with `Api\Db` usage swept** (commit `63788ed`); future-proofing only, no opt-in point anywhere in this app - see the Eleventh lesson below |
| records | **Done** (follow-up commit `d51f788`) - 4 more fragments found live-breaking the records list page the moment the global flip landed (`records_ui::get_rows()`'s default status/date `col_filter` entries - the status filter fires unconditionally on *every* unfiltered view - and `records_export_csv`'s two range-filter entries), missed by the earlier conversion; no `26` branch for this repo, so master-only by necessity |
| vidopro | N/A - no `Api\Db` usage found |

Not covered by either list above (separate repos, but neither a default composer package nor EPL -
`guacamole`, `knowledgebase`, `phpbrain`, `profitbricks`, `ranking`, `schulmanager`, `untissync`,
`wiki`): out of scope for this project unless/until requested - until 2026-11-09, when the global
default flip (below) made several of these live-break during the user's own testing. Resolved
per-app, not uniformly - see "Global default flip" below for the distinction between "fix properly"
(untissync, actively used by real schools) and "opt out, no audit" (wiki, phpbrain, ranking,
schulmanager - dead or never-maintained code, auditing every raw fragment would be pure waste).
`guacamole`/`profitbricks`/`knowledgebase` were checked and found to have no bare-fragment usage at
all (either no direct `Api\Db` access, or only ever normal `column => value` arrays) - nothing to do.
`managementserver` (EPL, already swept once, see the EPL table above) was deliberately **not**
re-audited proactively - smaller direct-access footprint than the others and partially covered
already; left for a reactive fix if and when it actually breaks, rather than repeating the
Eleventh lesson's expensive manual sweep pre-emptively.

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
- **Full `api/src` sweep** (prompted directly by the user asking "did you convert all usages in
  api/src?" - the answer at that point was no, only the files touched by the addressbook/infolog
  investigations): every file under `api/src/` that uses `Api\Db` directly (`$this->db->`,
  `self::$db->`, `static::$db->`, or `$GLOBALS['egw']->db->` - not just `Api\Storage` subclasses,
  since plenty of core classes talk to the database without going through that base class at all)
  was enumerated and individually checked. Found and fixed real gaps in `Accounts\Import` (a
  `modified>=` filter reaching `Contacts\Sql`'s now-strict backend directly through the account
  SQL/LDAP source, bypassing the `Contacts\Storage` composition layer entirely - would have thrown
  on the next account import run), `Auth\Sql` (the case-insensitive-username LIKE fallback, used on
  every login attempt once case-sensitive usernames are off), `Preferences`, `Session`,
  `Storage\Customfields` (four separate sites, including one where a *helper method's own return
  value* - `commasep_match()` - needed wrapping at its call sites rather than inside the helper,
  since the helper's documented contract is "returns a plain SQL string" for other potential
  callers), `Storage\History` (three sites, one of them a dedicated `dateRangeFilter()` helper
  wrapped at its source since building fragments is that method's sole purpose), `Acl`,
  `Translation`, and `Egw\Applications` (`read_installed_apps()` - on the hot path of every page
  load, verified safe via the full `api/tests/` suite of 1094 tests still passing). None of these
  classes have `requireSqlFragmentObjects()` enabled anywhere yet (all either static or always using
  the shared global `Db` instance), so none of this was a live break - pure future-proofing ahead of
  whenever a clone mechanism or the eventual global opt-out flip happens. `Accounts.php`,
  `Categories.php`, `Contacts.php` (its raw hand-built SQL strings executed via `$db->query()`
  bypass the array-based mechanism entirely - a different, unrelated pattern this project doesn't
  touch), `Contacts\Ldap.php` (LDAP, not SQL), `Mail\Smtp\Sql`/`Stalwart`, `Mail\Notifications`,
  `Asyncservice`, `Config`, `Egw.php`, `Db\Backup`, `Db\CallbackIterator`, `DateTime`, `Vfs.php`
  (its only match was a commented-out line), `Vfs\Sharing`, and `Vfs\Sqlfs\StreamWrapper` were all
  checked and have nothing to convert. `Vfs/Sqlfs/*`'s actual SQL backend beyond `StreamWrapper.php`
  was not separately swept.
- **"The other default apps" sweep** (user's explicit instruction to continue, after the api/src
  sweep): admin, aiassistant, developer, filemanager, importexport, notifications, aitools,
  bookmarks, collabora, news_admin, openid, projectmanager, rag, registration, smallpart, tracker,
  webauthn - see the tracker tables above for per-app detail. archive, emailadmin, home, mail (its
  own account SQL already covered via `api/src/Mail/Account.php`), preferences (app), activesync,
  rocketchat, status, and swoolepush were checked and have no `Api\Db` usage of their own to
  convert.

  **Sixth lesson - the original "extends Api\Storage" scoping grep had false negatives**: it used
  `extends Api\\Storage\b` / `extends Storage\\Base\b` and missed `tracker_so` (`extends Api\Storage`
  - should have matched, didn't - likely a regex-engine quirk across this many files run in one
  pass), `tracker_escalations` (`extends Api\Storage\Base2`, a variant base class not covered by the
  pattern at all), `developer/src/Langfiles.php`, `registration_so`, `bookmarks_so`,
  `news_admin_bo`, and `openid/src/Repositories/{AccessTokenRepository,ClientRepository}.php` (all
  genuine `Api\Storage`/`Api\Storage\Base` subclasses). These were only found because the
  file-by-file fork audits read every file's actual class declaration rather than trusting the
  earlier grep's file list - a reminder that a single repo-wide structural grep is not a substitute
  for reading each file when precision matters.

  **Seventh lesson - a shared/composed helper's by-reference output needs the same wrap discipline
  as a direct `$filter[]` append, found via a live, cross-app risk**:
  `Rag\Embedding::search2criteria($app, $criteria, $order_by, $extra_cols, &$filter)` appends two
  raw fragments directly into the caller's `$filter` array by reference, and is called from
  `Api\Storage::process_search()` - the shared base *every* `Api\Storage` app uses - whenever a
  plain-string search runs and RAG's `available()` check says yes for that app. Since calendar,
  timesheet, resources, infolog, and addressbook/Accounts (via `Contacts\Sql`) all now opt into
  `requireSqlFragmentObjects()`, a real free-text search on any of them, with RAG actually
  configured for that app, would have thrown `InvalidArgument` in production - purely latent in this
  session's test environment because RAG wasn't enabled there (`class_exists()` on the autoloadable
  class still returns true regardless, so the gate that actually matters is `available()`'s
  app-specific config check, not whether the RAG app is "installed"). Found only because a fork
  doing a routine file audit happened to trace what `process_search()` calls, not through any test
  failure - worth remembering that a dependency's *conditional* code path can carry the same risk as
  code in the app being converted, and grepping only the app's own directory would never surface it.

  **Eighth lesson - a dedicated separate git repo per default/composer app**: each of
  activesync/aitools/bookmarks/collabora/news_admin/openid/projectmanager/rag/registration/
  rocketchat/smallpart/status/swoolepush/tracker/webauthn (and developer, aiassistant) is its own
  git repository nested inside the main `egroupware/egroupware` checkout - `git status`/`git diff`
  run from the main repo's root show nothing for files changed only inside one of these, even though
  the files themselves are right there on disk and fully editable. Discovered only after finishing
  all the edits for this round, when a routine "which pre-existing test failures are really
  pre-existing" `git stash` from the main repo's root silently did nothing to `projectmanager/`.
  Each of these needed its own `git status`/`git add`/`git commit` run from inside that app's own
  directory - 13 separate commits for this round alone, instead of one in the main repo.
- **Projectmanager/tracker full pilots** (user's explicit follow-up instruction): enabling
  `requireSqlFragmentObjects(true)` immediately surfaced 3 more missed fragments in projectmanager
  that the wrap-only sweep's fork audit hadn't caught (`sync_all()`'s own status filter, an inline
  `pe_id` OR-condition in `constraints_so::search()`, and - the big one -
  `projectmanager_so::search()`'s ACL-join filter, hit on *every single search* once the ACL join is
  active, not some rare branch). Tracker's wrap-only pass, by contrast, needed zero follow-up fixes -
  its full test suite passed clean the moment strict mode was flipped on. No consistent predictor
  found for which apps will need a second pass; re-running the full test suite immediately after
  flipping the flag, not just trusting the earlier wrap sweep, is the only reliable check.

  **Ninth lesson - not every `$filter[]`/`$criteria[]` site should be wrapped, even when strict mode
  flags it**: `projectmanager_so::search()` has two sites (`$filter[] = $criteria;` for a non-array,
  non-string `$criteria`, and the `is_array($query) ? array_merge($filter, $query) : ...` branch
  consuming `parse_search()`'s own return) that were deliberately left unwrapped despite being
  *adjacent* to fixed ORIGINATOR sites in the same method. Both are genuine PASS-THROUGH: the first
  is the caller's own `$criteria` parameter verbatim, the second is `parse_search()`'s array return,
  whose own int-keyed entries are pass-through by Storage\Base's own documented contract (see the
  third lesson). Wrapping either would launder potentially-unvalidated input into looking trusted -
  exactly the over-wrap bug the third lesson already found and fixed once. The test suite passing at
  0 `Db\SqlFragment`-related errors does not by itself prove these two sites are unreachable with bad
  input; it was cross-checked against `parse_search()`'s source (which itself already enforces
  strict mode via `column_data_implode()` when it collapses its array into a string) before deciding
  not to touch them, not inferred from the test run alone.

  **Tenth lesson - EPL apps are a separate, invisible checkout with live cross-app regressions once a
  default app opts in**: the EPL (commercial, `EGroupwareGmbH/*`) apps are *also* each their own
  separate git repo, same as the Eighth lesson's composer packages, confirmed the same way (nothing
  shows in the main repo's `git status`). Two of them, `stylite/docker/dist/*` and `stylite/docker/src/*`,
  are additionally vendored *build-artifact copies* of other apps (kanban/invoices/esyncpro/policy/
  stylite itself) bundled for the Docker image - confirmed byte-identical via `diff -q` against the
  canonical top-level app repos - and must never be edited; only the canonical copies matter. More
  importantly, stylite's EPL integration layer (`PMCalendarIntegration.php`, `Cti/Storage.php`,
  `stylite_infolog_calendar_integration.inc.php`, the `Vfs/Links/*` plugins for projectmanager/
  timesheet/tracker/addressbook) builds its *own* raw filter arrays and feeds them straight into
  calendar/timesheet/tracker/projectmanager/addressbook's `search()`/`get_rows()`/union-query
  machinery - all of which are now strict-mode pilots. Once a default app opts into
  `requireSqlFragmentObjects(true)`, any separate app/repo's unwrapped fragment reaching that app's
  query layer is a *live* regression, not future-proofing, even though neither side's code changed.
  This was found to be true repeatedly, confirmed end-to-end by re-running
  `calendar/timesheet/tracker/projectmanager/infolog/addressbook` tests together (0
  `Db\SqlFragment`-related errors) after fixing stylite's integration layer.

  **Eleventh lesson - forks still miss things, even on narrow, well-specified batches**: across the
  5 small EPL apps and stylite, fork-written enumeration reports had real gaps found only by directly
  re-reading the flagged files after applying the reported fixes: `kanban/src/Bo/Column.php` (bare
  elements inside an `Array(...)` literal, no `[] =` syntax), `records_fields.inc.php` (a `delete()`
  call not in the fork's list at all), and - worst case - `managementserver_search.inc.php`, where a
  fork's report listed only 2 of 4 actual unwrapped sites in the same `switch` block. Continued in
  `managementserver`'s own file-by-file sweep (not fork-assisted, read directly): found further
  misses the same way in `managementserver_instance.inc.php` (`instance_id != N`) and
  `managementserver_sync.inc.php`/`_session.inc.php`/`_wopi.inc.php` (several `$where[]`/`$query[]`
  bare elements per file, none pre-flagged). `managementserver` has no opt-in point anywhere (every
  `Db` instance is either the shared global or an unscoped clone) and ships no test suite, so this
  pass was syntax-checked (`php -l`) only, not test-verified - pure future-proofing, same as the
  other wrap-only apps. One wrap (`mail-proxy.php`'s account/hash lookup) was left out of the commit
  because it landed inside a much larger pre-existing uncommitted rewrite of that function from
  elsewhere in the shared checkout (see `feedback_shared_checkout_stale_uncommitted_work` in memory) -
  committing it would have mixed unrelated, unreviewed logic changes into this project's commit; the
  wrap itself is still present on disk, just not yet committed.

### Open / next

- All apps/files previously listed here as "still using the raw-fragment convention" are now
  wrapped, including all 8 EPL apps (see the tracker tables, the "other default apps" sweep entry,
  and the Tenth/Eleventh lessons above). What's left, lower priority: the various
  `*/setup/tables_update.inc.php`/`setup/default_records.inc.php` schema-migration files across every
  app (one-time migration code, not live request-handling - out of scope per this project's
  established policy), and `mail-proxy.php`'s one leftover un-committed wrap (see Eleventh lesson).
- `projectmanager` and `tracker` are now full pilots too (`requireSqlFragmentObjects(true)` enabled
  on each of their own `Db` clones - see the tables above). Every other app in the composer-packages
  table is still wrap-only (no opt-in point found, or not attempted yet) - good next candidates if
  continuing this project, since their fragments are already wrapped and ready.
- No 26 backport for any of this (explicit decision) - the actual vulnerability is already closed on
  both branches via the two entry-point patches; this is defense-in-depth architecture that should
  soak on master first.

### Global default flip (opt-out model) - DONE 2026-11-09, master only

Committed as `8d2923697b` ("Api\Db: flip Db\SqlFragment enforcement to opt-out by default"), pushed
to master. **Not backported to 26** - deliberately master-only for now, same soak-before-26 policy
as the rest of this project, and especially warranted here given how much live breakage the flip
itself surfaced the same day (see the findings list below) - this needs to prove out longer before
going anywhere near the release branch.

The global flip happened sooner than "Open/next" above originally anticipated, once a live security
review (the GHSA-3f9x-47f8-j6fw addressbook `order`-parameter SQL injection fix, same day) surfaced
that the per-app opt-in model leaves every app/class still using `$GLOBALS['egw']->db` directly -
the large majority, per the tracker tables above - with **zero** enforcement, regardless of whether
its own fragments were ever wrapped. ralf's explicit call: flip the default now; it is up to a
3rd-party/unconverted app's own maintainer to either fix it or opt out, not EGroupware's job to keep
it silently unenforced forever.

- **`Api\Db::$requireSqlFragmentObjects` now defaults to `true`** (was `false`), including on the
  shared `$GLOBALS['egw']->db` instance itself - closing the structural gap the per-instance opt-in
  model could never close (nothing stopped new code from reaching the always-non-strict shared
  instance even inside an already-"converted" app). `requireSqlFragmentObjects()`'s own docblock and
  the property's doc comment updated to describe the opt-OUT convention
  (`(clone $GLOBALS['egw']->db)->requireSqlFragmentObjects(false)` for code that still needs the
  legacy, unwrapped convention).
- **Global admin escape hatch**: `$GLOBALS['egw_info']['server']['opt_out_sql_fragment'] = true;`,
  settable in an instance's own `header.inc.php`, restores the old, unenforced behavior
  install-wide, at the admin's own risk, until the 3rd-party/old app in question is fixed. Resolved
  centrally in `Api\Db::__construct(?array $db_data=null, ?bool $use_sql_fragment=null)` itself
  (`$this->requireSqlFragmentObjects($use_sql_fragment ?? empty($GLOBALS['egw_info']['server']['opt_out_sql_fragment']))`),
  per ralf's explicit follow-up design: no individual construction site needs to call
  `requireSqlFragmentObjects()` itself or be touched at all - every `new Api\Db(...)`/`new
  Db\Deprecated(...)` anywhere (the shared `$GLOBALS['egw']->db`, `setup_cmd`'s db-swap points,
  `setup_cmd_showheader`/`setup_cmd_database`'s standalone instances, `khs-egw-migration.php`, any
  future one) now gets this resolution automatically, for free. A known-good/converted class that
  wants to stay strict regardless of the admin's opt-out passes `true` explicitly as the 2nd
  constructor argument (`new Api\Db($data, true)`) instead of calling `requireSqlFragmentObjects()`
  after the fact - the existing 7 pilots' clone-based `(clone ...)->requireSqlFragmentObjects(true)`
  idiom still works unchanged too (clone doesn't re-run `__construct()`, so that call is still the
  only way to flip an already-existing clone). Manually verified all three resolution paths against
  the real framework bootstrap (opt-out set + no override → false; opt-out set + forced true → true;
  opt-out unset → true, the default).
- The 7 existing per-app opt-in clones (calendar, infolog, resources, addressbook/Contacts\Sql,
  timesheet, projectmanager, tracker) keep their explicit `->requireSqlFragmentObjects(true)` calls -
  now redundant (the class default is already `true`) but harmless; not touched, to keep this
  change's diff minimal and avoid re-touching already-verified apps.
- **Real, previously-missed raw fragments found and fixed by actually running `api/tests/Db` +
  `api/tests/Storage` under the new default** (none of these were caught by the earlier per-app
  pilot/sweep rounds, since none of them sat behind any app's own opt-in point - they all reach the
  shared instance directly):
  - `Accounts\Sql::name2id()` (api/src/Accounts/Sql.php) - 3 fragments (the case-insensitive
    `account_lid` LIKE condition, the `account_id IS NOT NULL` dedup condition, and the alias-lookup
    `account_id<0`/`account_id>0` branch) - hit on every login resolving a username to an account id.
  - `Session::login_blocked()` (api/src/Session.php) - 4 fragments (3x the `li > $block_time` recency
    condition across its 3 union legs, plus the `loginid LIKE` alias check) - hit on **every single
    login attempt** (the brute-force-lockout check itself).
  - `Session::update_notification_heartbeat()` - 1 fragment (`lo IS NULL`).
  - `Session::notifications_active()` - 2 fragments (`session_dla > ...`, `notification_heartbeat > ...`) -
    the exact same pattern as the stylite `concurrent_users()` fix earlier the same day, but in core
    api, not EPL.
  - `Contacts::search_n_fileas()`-equivalent bulk-repair loop (api/src/Contacts.php) - 4 fragments
    (`n_fileas`/`n_fn` IS NULL/empty-string conditions) - a genuinely different pattern than the
    `Contacts.php` raw-`$db->query()` usage the earlier api/src sweep explicitly excepted; this one is
    the array-based convention, in scope, just missed.
  - `Sharing::` Collabora-share cleanup (api/src/Sharing.php) - 2 fragments (`share_expires <`,
    `share_writable IN (...)`).
  - `Vfs\Sharing::delete()`/`vfs_hook()` (api/src/Vfs/Sharing.php) - 2 fragments (both `share_path
    LIKE` conditions).
  - `Storage\History::get_rows()`'s caller-supplied `'filter'` ingestion (api/src/Storage/History.php)
    now wraps an incoming plain string itself (narrow, audited trust boundary - only one real caller,
    `calendar_uiforms::setup_history()`, never client-reachable, see the method's own long-standing
    comment) rather than requiring every caller to wrap it, keeping both the old plain-string
    convention and the new explicit-SqlFragment one working; `calendar_uiforms::setup_history()`
    itself updated to wrap its own literal too, for consistency with the rest of the codebase.
  - Several test files' own fixtures needed updating for the new default (not app bugs): they either
    deliberately exercise the legacy unwrapped convention directly (now need an explicit
    `->requireSqlFragmentObjects(false)` clone - `SqlFragmentTest`, `ExpressionTest`,
    `InsertUpdateDeleteTest`) or exercise `Storage\Base`'s documented caller-supplied raw-fragment
    pass-through (now need to wrap their own literal, same as any other caller would -
    `SaveDeleteTest`'s three tests, `RequireSqlFragmentObjectsTest`, which was also renamed/inverted
    to test opt-OUT clone isolation instead of opt-IN).
  - `api/src/Mail/Credentials.php::read()`/`delete()` - 2 fragments (the `cred_type & N > 0` bitmask
    condition, duplicated in both methods) - hit on **every logout** (`Session::removeRememberMeTokenOnLogout()`
    reads credentials on the way out), found live by the user testing logout right after the flip.
  - `api/src/Vfs.php::checkLock()` - 1 fragment (the `depth > 0` `lock_path LIKE` condition) - hit on
    every filemanager listing (lock-checked per displayed row), live-broke the filemanager list page.
- **Verification scope and a known limitation**: `api/tests/Db` + `api/tests/Storage` (471 tests) run
  clean under the new default except one pre-existing, unrelated failure (`ContactsTest`: "bad login
  or password" - confirmed via a temporary revert of just the default-flip line that this reproduces
  identically with the OLD default too, so NOT caused by this change; root cause found separately:
  self-inflicted `egw_access_log` pollution from running login-based tests very many times in one
  session, tripping `Session::login_blocked()`'s own real lockout - needs those rows cleared or the
  block window to pass, not a code fix). A full `api/tests` run was NOT usable as a signal once that
  lockout tripped (cascades through every test via the shared `LoggedInTest` base). Beyond the
  automated suite, ralf manually tested the live instance after the flip and found several more
  fragments breaking real pages (webauthn login, the addressbook/filemanager/records list pages,
  logout) - all found and fixed the same day, see the per-app tracker entries above and the two
  core-api fragments listed just above this. **Not yet run**:
  calendar/infolog/resources/addressbook/timesheet/projectmanager/tracker's own full suites, or any
  EPL app's - the global flip is a superset of what those apps' own opt-in already enforced, so no
  regression is expected there, but this has not been empirically re-verified post-flip.
- **Never-covered legacy/dead apps, handled per the user's explicit instructions once they started
  live-breaking during testing** - two different treatments depending on whether an app is still
  relied on:
  - **wiki** (deprecated, see `project_wiki_app_deprecated` in memory), **phpbrain**, **ranking**:
    opted out wholesale (clone the shared `Db` and call `requireSqlFragmentObjects(false)` at the
    one construction point each app's class hierarchy shares - `sowiki`/`soWikiPage`'s shared `$db`,
    `sokb::__construct()`, `ranking\So::get_rang_db()`) rather than auditing dozens of raw fragments
    in code nobody maintains any more. `ranking_calendar_integration.inc.php`'s few fragments were
    wrapped individually instead, since that file reaches *calendar's own* strict `Db` clone directly
    - opting out ranking's own instance wouldn't have helped there.
  - **schulmanager**: ralf's call was "opt out, or we ignore it, it's no longer in use" - opted out
    across all 11 independent SO-layer files (no shared base constructor to do it once).
  - **untissync**: ralf's explicit correction - "should be fixed, not opt-out, as it's actively used
    by some of our schools". Properly wrapped all 25 raw fragments individually across 7 files
    instead (commit `bdb5f81`, backported to untissync's own `26` branch as `1118b67`, both pushed).
    Also fixed a real, independent bug found along the way: `untissync_bo::syncRooms()` interpolated
    an external Untis-API value straight into a SQL string unescaped (`"name = '".$val['name']."'"`)
    - now properly quoted via `$db->quote()` before wrapping. Caught and corrected a self-inflicted
    mistake during this fix too: the first pass used a Python script to apply the edits, which
    silently converted these files' original CRLF line endings to LF, producing a ~1700-line
    spurious diff across 7 files - reverted and redone with targeted edits preserving the original
    line endings.
  - **guacamole**, **profitbricks**, **knowledgebase**: checked, found clean (no bare-fragment usage
    at all, or no direct `Api\Db` access) - nothing to do despite being flagged as actively used.
- **Not yet done, open**: a broader sweep for more of the same shape of gap (api/src classes/static
  classes previously marked "wrapped, no opt-in point, future-proofing only" - now LIVE enforcement,
  not future-proofing - so any mistake in that earlier wrapping is no longer latent). The findings
  above were only what `api/tests/Db`+`api/tests/Storage`'s existing coverage, plus the user's own
  live testing, happened to exercise; `managementserver` (EPL, partially swept already, deliberately
  not re-audited - see the app tracker above) and any other composer-package/EPL app with thin or no
  test coverage are the likely remaining risk, same as every per-app pilot before this one kept
  finding one or two more misses on first real exercise.

**Unrelated, but found while tracing `Api\Storage\Base::search2criteria()` callers for this
project**: `search2criteria()`'s own first line (`if (empty($_pattern) || is_array($_pattern) ||
...) return $_pattern;`) returns a non-string pattern completely unprocessed, which several callers
then wove straight into generated SQL - the same vulnerability class as GHSA-vx89-j2x7-5wp2
(calendar's original finding), independently present in `timesheet/src/ApiHandler.php`'s REST
filter handling, `infolog_bo::searchInfolog()`'s free-text search, and `Api\Accounts\Sql::search()`.
This is a standalone, pre-existing bug with zero dependency on `Db\SqlFragment` - fixed with plain
`is_string()` guards (commit `77355c16a1`), pushed to master and backported to 26 immediately,
independent of this longer-running project.
