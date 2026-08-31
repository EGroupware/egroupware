# Api\Accounts\Import test coverage (LDAP/ADS account sync)

## Status: ALL 5 PHASES DONE and green (2026-08-31), 37 tests total in `api/tests/Accounts/`, stable
across reruns. Phase 1: harness (`ImportTestCase.php` + `Fixtures/FakeLdapAccountsBackend.php` +
`Fixtures/FakeContactsSource.php`) + `ImportInitialUsersTest.php` (5 tests: 3 config-validation error
paths + create + no-op-rerun). Phase 2: `Fixtures/FakeAdsAccountsBackend.php` + `ImportGroupsTest.php`
(6 tests: group+member create incl. primary-group remap, name-collision skip, the Ads-specific
`getMembers()` path, group update, local-groups membership preservation, a `univention`-sourced run) +
`ImportDeletionDetectionTest.php` (3 tests: user deletion-candidate detection incl. the `anonymous`
carve-out, group deletion-candidate detection - dry-run only, see "Deletion tests are dry-run-only" below).
Phase 3: `ImportIncrementalTest.php` (4 tests: modified-filter-driven partial sync, deletion structurally
disabled on incremental, Ads always-full-groups vs. Ldap respecting the filter - a deliberate contrast
pair) + `ImportDnRegexpTest.php` (1 test, pins down a behavior Ralf later confirmed intentional - see
"Two things flagged as suspicious, confirmed intentional" below) + `ImportSchedulingTest.php` (8 tests:
`installAsyncJob()`'s frequency->cron-shape mapping, incl. a data provider, against the real `egw_async`
table with careful skip-if-real-job-exists + cancel-in-finally safety). Phase 4: `ImportAliasesTest.php`
(5 tests: add/remove diffing against a real `Api\Mail\Smtp\Sql` backend, dry_run log-only, LDIF export
for `ldap`+`ads` sources - `univention`'s LDIF variant not covered, same attribute-switch pattern as the
other two, lower priority). Phase 5: `ImportWritebackTest.php` (5 tests: the
`account_import_update_source` push-to-source path via `hookEditAccount()` - guard/no-op cases,
`addaccount`, `editaccount`, `deleteaccount`; `editaccountcontact` not covered, see "Phase 5" below).
All against the real test DB with fixture-backed LDAP/ADS/contacts sources, no live LDAP/ADS server.
Found+fixed **four real production bugs** along the way, plus a `$save_state` testability parameter
added mid-Phase-3 - see "Bugs found" for detail.

## Two things flagged as suspicious, confirmed intentional by Ralf (2026-08-31)

1. **`account_import_dn_regexp` + `account_import_delete` interaction** (`ImportDnRegexpTest.php` pins
   down the actual behavior: a regexp-excluded-but-still-present account IS treated as a delete
   candidate). Ralf: the regexp exists so the LDAP/ADS query can target a single **parent** DN instead of
   needing multiple separate DN queries - the regexp is a client-side post-filter over that broader query,
   not a separate "which subtree do we manage" concept. Given that design, an account excluded by the
   regexp genuinely is being treated as "not part of what this run is managing" - the delete-candidate
   behavior the test pins down is a natural (accepted) consequence, not a bug.
2. **`Import::firstRunToday()` never reads `account_import_time`.** Ralf: intentional - it's deliberately
   *not* trying to reconstruct "was this specific invocation the one scheduled at `account_import_time`"
   from the configured frequency/time; it's simply "is this the first run of the day", independent of
   what the configured schedule actually looks like. `ImportSchedulingTest.php` still only covers
   `installAsyncJob()` (not `firstRunToday()` itself, whose pass/fail boundary is wall-clock-dependent at
   test time) - now confirmed as "not worth chasing a flaky test for", not left open as a question.

## `run()`'s `$save_state` parameter (added 2026-08-31, mid-Phase-3)

While planning Phase 3's `installAsyncJob()` coverage, found that `run()` **unconditionally** persists two
things to this install's real, shared state on every call, with no existing way to opt out:
`Api\Config::save_value('account_import_lastrun', ...)` (runs even when `dry_run=true` - the dry_run guard
only skips the *log line*, not the config write itself) and, for a non-dry-run initial run,
`installAsyncJob(...)` (which unconditionally cancels the real `'AccountsImport'` async/cron timer first,
then only reinstalls it if `account_import_frequency` is configured `> 0`). Every Phase 1/2 test that
called `run()` without knowing this had already been silently overwriting this shared dev box's real
`account_import_lastrun` config value with test-run timestamps (confirmed: found it at `1788181060` /
2026-08-31 12:57:40 UTC, a clear test-run artifact, not a real prior sync time). Ralf's call when this was
flagged mid-turn: this persistence is intentional production behavior, but a new parameter should let tests
switch it off. Added `bool $save_state=true` as `run()`'s 4th parameter - `false` skips both the config
write and the `installAsyncJob()` call, regardless of `dry_run`. Every test call site now passes
`save_state: false` (`ImportTestCase`'s docblock states this as a hard requirement for any future test).
The `account_import_frequency`/async-job side was checked and confirmed harmless in practice (this box has
no periodic import configured - `account_import_frequency` reads `NULL`, and no `'AccountsImport'` row
exists in `egw_async`), but the `account_import_lastrun` drift is real, already happened, and not
undone (there's no way to know its true original value at this point - it's low-stakes bookkeeping, not
destroyed data, but worth Ralf knowing about in case he was relying on it for real ADS testing on this box).

`stylite/tests/MockAccounts.php` is a pre-existing but unrelated/unused sketch (mocks the `Api\Accounts`
*frontend* facade for a different consumer, not the LDAP/ADS import path) - it is not wired into anything
and does not by itself solve the problem below. This doc replaces it as the design for a real mock.

## Deletion tests are dry-run-only, by design

`Import::run()`'s "no longer existing user(s)" query (`$where = ['account_type' => 'u']` [+
`'account_status' => 'A'` for `deactivate`]) reads **every** real `account_type='u'` row in the shared
`egw_accounts` table - completely unscoped, no tie to any particular test's fixtures. On this shared, live
dev database, actually letting `account_import_delete='yes'`/`'deactivate'` run for real would delete/
deactivate every genuine account this test's tiny fake source doesn't happen to mention - caught this
before running anything, confirmed with Ralf. His call: don't test that deletion *executes*; test that the
*candidate-detection* logic correctly finds what should be deleted. `dry_run=true` only ever logs under
that code path (`if ($dry_run) { $this->logger("Dry-run: would ..."); ... }` - no
`admin_cmd_delete_account` call, no `account_status` UPDATE), so `ImportDeletionDetectionTest` uses it
exclusively: pre-create real SQL-only accounts (simulating a prior import's leftovers), run with
`dry_run=true`, and assert on which login-ids the resulting "Dry-run: would delete/deactivate N ..." log
line does and doesn't mention - incl. a dedicated check that `anonymous` never appears there (Import.php's
own carve-out, line ~625). This is real coverage of the matching/diffing logic without the blast radius.
The same reasoning and pattern applies to `Import::groups()`'s own (separate) group-deletion query
(`$GLOBALS['egw']->db->select(Sql::TABLE, ..., ['account_type' => 'g'], ...)`, equally unscoped) -
`ImportDeletionDetectionTest` covers both user- and group-level candidate detection this way. See
[[feedback_test_detection_not_execution_for_unscoped_destructive_paths]] for the general principle.

## Phase 5: write-back (`hookEditAccount()`) testability

`Import::hookEditAccount()` is a plain `public static function` - no `Import` instance involved at all, so
`ImportTestCase::buildImport()`'s reflection-based harness (built for `run()`) doesn't apply here. Instead
`ImportWritebackTest.php` defines `TestableWritebackImport extends Import`, overriding just the 2 factory
methods (`accountsFactory()`/`contactsFactory()`) to return fixture-backed frontends instead of building
real, live-connecting ones - and calls `TestableWritebackImport::hookEditAccount($data)` directly. This is
exactly the seam the early `self::`->`static::` tweak (approved by Ralf back in Phase 1 planning) was for:
`hookEditAccount()`'s internal calls are `static::accountsFactory(...)`, so late static binding correctly
dispatches to the subclass's override when called via `TestableWritebackImport::`. No `ReflectionClass`
needed for the `Import` side at all here - just inheritance.

Two things needed for this that Phases 1-4 never touched:

- **`FakeLdapAccountsBackend`'s `save()`/`delete()`/`name2id()`/`id2name()`**, stubbed "not implemented"
  through Phase 1-4 (the pull-sync path never calls them on the *source* backend), are now implemented for
  real - incl. `save()` simulating "LDAP/AD assigns a new uidNumber/UUID/DN on create" when no `account_id`
  is given, exactly the shape `hookEditAccount()`'s `addaccount` case exercises.
- **`hookEditAccount()` reads config via `Api\Config::read('phpgwapi')` directly - NOT
  `$GLOBALS['egw_info']['server']`** like `run()` does. `ImportTestCase::setImportConfig()` has zero effect
  on it. Found this by two tests initially passing for the wrong reason (they asserted "nothing happened",
  and happened to pass because this box's *real* `account_import_update_source` was already off - not
  because the test's own config override was doing anything). `Api\Config`'s cache (`self::$configs`) is
  `private static` with no public setter for arbitrary overrides (`Api\Config::save_value()` would write to
  the real shared DB config row - same class of risk flagged elsewhere in this project), so
  `ImportWritebackTest` reaches it via `ReflectionProperty` instead, with the same backup/restore-in-tearDown
  discipline as `setImportConfig()`.

**A same-value gotcha, found via a real assertion failure, not by reasoning ahead of time:** for an
already-synced account being edited, the SQL `account_id` and the source's own id must be **the same
number** - `hookEditAccount()`'s "was this a formerly-local account?" check
(`Api\Accounts::getInstance()->id2name($account['account_id'], 'account_uuid')`) looks the caller-supplied
id up in the REAL SQL table, not some independent source-side id space. An earlier draft of
`testEditAccountUpdatesExistingSourceEntry` used two different ids (a realistic-looking but wrong
assumption, given `Import::run()`'s own pull-sync side has a comment explicitly tolerating SQL/source id
mismatch right after a fresh `addaccount`) and silently exercised the wrong branch ("treat as new") instead
of the intended "update existing" one.

Not covered: `editaccountcontact` (contact-data write-back, going through `Api\Contacts::backendSave()`
instead of the accounts backend) - left for a future pass; also note its own exception-recovery branch
recurses via `self::hookEditAccount(...)` (not `static::`), which would silently reset late static binding
back to the real `Import` class mid-call if ever exercised through a subclass like this one - a real trap
for testing that specific branch, not yet worked around.

## Bugs found while building the harness

### 1. `Import::run()` clobbered `$GLOBALS['egw']->accounts` to null

`run()`'s catch block and its normal-completion path both did `$GLOBALS['egw']->accounts = $frontend;`,
intending to restore whatever the global accounts frontend was before the run - but `$frontend` was never
assigned anywhere inside `run()` (it's only ever a *local* variable inside the unrelated `__construct()`,
which doesn't share scope with `run()`). Every call to `run()` - success, failure, real cron `async()` runs
included - silently set `$GLOBALS['egw']->accounts` to `null` (via PHP's undefined-variable-is-null
coercion) for the remainder of the process/request. Fixed by capturing
`$frontend = $GLOBALS['egw']->accounts;` at the actual top of `run()`, before any of the
accountsFactory()/contactsFactory() swapping that happens further down (including via the addaccount/
editaccount hooks `run()` itself fires). This is exactly the kind of bug the reflection-based harness was
built to surface: the very first test that called a real `Api\Contacts()` *after* a `run()` call fataled
with "Call to a member function memberships() on null" inside `Contacts\Storage::__construct()`.

### 2. `Mail\Smtp\Stalwart::updateGroup()` crashed on any group with no email

Found via `ImportGroupsTest`'s group-creation tests: `Import::groups()` fires the `addgroup` hook
(`api/setup/setup.inc.php`: `'EGroupware\Api\Mail\Hooks::updategroup'`) for every group it creates -
unconditionally, same as `addaccount`, regardless of whether the test cares about mail at all.
`Mail\Hooks::run_plugin_hooks()` calls `$smtp->$method($data)` with the **lowercase** literal
`'updategroup'` - which, thanks to PHP's case-insensitive method dispatch, actually invokes
`Stalwart::updateGroup()` (capital G) - see [[feedback_php_case_insensitive_method_callables]], the exact
bug class that memory already warns about, caught red-handed this time. That method
(`api/src/Mail/Smtp/Stalwart.php` line ~60-66) fell back to `Api\Accounts::id2name($data['account_id'])`
(default `$which='account_lid'`) when `$data['account_email']` wasn't set - i.e. it used the group's
**login name** as if it were an email address. That bogus "email" (no `@`) then reached
`mailingList()`'s `[$name, $domain] = explode('@', $email);`, leaving `$domain` null, which
`domainId(string $domain)`'s strict type hint turned into a `TypeError` - live network calls to this box's
real Stalwart server aside, ANY group creation with no explicit email hits this, not just via `Import`
(this dev box's `GroupCommandTest::testAddGroup` independently errors here too, confirmed pre-existing and
unrelated to this session's other changes). Fixed with the same one-line correction the file already uses
elsewhere for the identical lookup (`api/src/Mail/Smtp/Stalwart.php` line ~431):
`Api\Accounts::id2name($data['account_id'], 'account_email')`. Out of scope for this project to go further
into `Stalwart`/mailing-list territory - flagging here since it's exactly the kind of thing this box's real,
live Stalwart integration means group-creation tests will keep bumping into.

### 3. `Import::groups()`'s dry-run group-deletion logging used stale variables from an earlier loop

Found by close reading while writing the group deletion-candidate test (not by a crash - this one's a
silent-wrong-answer bug, the more dangerous kind). The group-deletion `foreach` in `Import::groups()` is a
**separate** loop from the main per-group sync `foreach` above it - but its dry-run branch logged
`"...group '$group[account_lid]' (#$sql_id)"`, reusing `$group`/`$sql_id`, which by that point are just
whatever they were left holding by the *last iteration of the earlier, unrelated loop* - not the group
actually being considered for deletion in the current iteration (`$account_lid`/`$account_id`, the
deletion loop's own loop variables). It also did `$delete++` instead of `$deleted++` - incrementing the
*string* config value (`'yes'`/`'no'`/`'deactivate'`) via PHP's Perl-style string auto-increment (harmless
but pointless) instead of the actual counter, so `groups()`'s returned `'deleted'` count silently stayed 0
for every dry-run group deletion. Fixed by using the loop's own `$account_lid`/`$account_id` and the
correct `$deleted` counter (`api/src/Accounts/Import.php`, the "delete the groups not returned" block).
Verified the fix matters: temporarily reverted it and confirmed `ImportDeletionDetectionTest`'s new group
test fails against the buggy code (wrong group name/id logged, `deleted` count wrong), then restored it.

### 4. Alias sync could crash with a duplicate-contact insert, from a missing cache invalidation

Found via `ImportAliasesTest`'s very first real test run - not a design question, a genuine crash:
`Api\Db\Exception\InvalidSql: ... Duplicate entry '...' for key 'egw_addressbook_account_id'`. Sequence:
`Import::run()`'s main per-account loop invalidates the `Api\Accounts` cache for `$account_id` right after
creating the SQL **account** row (line ~369) - but NOT again after creating the SQL **contact** row a bit
later in the same iteration (contact creation is what actually populates `account_email`, since
`Accounts\Sql::read()` gets it via a JOIN, not a real `egw_accounts` column - see the Phase 1 fixture-shape
note above). If `account_import_aliases` is on, `aliasImport()` runs right after that contact creation and
calls `Api\Mail\Smtp\Sql::setUserData()`, which itself calls `$this->accounts->id2name($id, 'account_email')`
- a call that hits the *stale* (pre-contact, empty-email) cache entry. Seeing a "different" email than what
it's about to set, `setUserData()` redundantly calls `Api\Accounts::save()` to "fix" the email - which
itself re-saves the linked contact (`Accounts.php` ~line 852) via a **fresh** `INSERT` (its own cached read
of the account didn't know a contact already existed either), colliding with the contact `Import::run()`
had already created moments earlier on `egw_addressbook`'s `account_id` unique constraint. Fixed with one
more `Api\Accounts::cache_invalidate($account_id)` call, right before the `aliasImport()` call site, so it
always sees fresh post-contact-creation data. This is a real production bug (reachable any time a brand-new
account is created with `account_import_aliases` on, real LDAP/ADS source or not) - verified it matters by
reverting the fix and confirming the exact same crash reproduces, then restored it.

**Decisions (2026-08-31):** make the `self::`->`static::` tweak in `Import`'s factory methods; new test/
fixture code lives under `api/tests/Accounts/`; `Univention` is in scope for Phase 1 alongside `ldap`/`ads`
(not deferred); the write-back path (`hookEditAccount`) is an explicit separate follow-up, not this round.

Ralf asked for test coverage of `Api\Accounts\Import` (LDAP/ADS/Univention -> SQL account sync) and its 3
run modes (initial/full, incremental, daily catch-up full), covering the full configuration-option space,
**without** standing up a live LDAP/AD server. This requires mocking at the account/contact *backend
object* level (the `Ldap`/`Ads`/`Sql` classes' shared read/save/search/... contract), not at the LDAP wire
protocol level - see "Why not mock the LDAP protocol" below.

## Code map

- **`Api\Accounts\Import`** (`api/src/Accounts/Import.php`, 1427 lines) - the sync orchestrator. Single
  public entry point `run(bool $initial_import, bool $dry_run, ?string $export_ldif)`, called either
  directly (setup UI) or via `async()` (cron, see "3 sync types" below). Everything else is protected.
- Import holds **4 backend-shaped objects**, all built in the constructor via two `protected static`
  factories and never swappable afterwards:
  - `$this->accounts` - the *source* (`Ldap`, `Ads`, or `Univention extends Ldap`) accounts backend.
  - `$this->accounts_sql` - the *destination* (`Sql`) accounts backend, always SQL.
  - `$this->contacts` - the *source* contacts storage object (`Api\Contacts\Ldap`, reached via
    `Api\Contacts::$so_accounts ?: $frontend->somain` - see "Contacts coupling" below).
  - `$this->contacts_sql` - the *destination* (`Api\Contacts\Sql`) contacts storage object.
- **Backend contract** all 3 account backends (`Sql`, `Ldap`, `Ads`; `Univention extends Ldap`) implement
  and that `Import` actually calls: `read($id)`, `save(&$data, $force_create=false)`, `delete($id)`,
  `search($param)`, `name2id($name,$which,$type)`, `id2name($id,$which)`, `memberships($id)`,
  `members($id)`, `set_memberships($groups,$id)`, `update_lastlogin($id,$ip)`. `Ads` additionally exposes
  `getMembers($group)` (used instead of `members()` for AD groups, see `Import::groups()` line ~974-981).
  This contract is the thing to fake - **not** individual `ldap_*` PHP calls.
- Side effects `Import::run()`/`groups()`/`setMembers()`/`deleteAccount()` trigger beyond the 4 backend
  objects: `Api\Hooks::process(['location' => 'addaccount'|'editaccount'|'addgroup'|'editgroup', ...])`
  (fires for **every app**, not just enabled ones - see "Hook feedback loop" below), `\admin_cmd_delete_account`
  (requires an admin session, see `doc/ai/testing.md`'s admin-test-account section), `Api\Asyncservice`
  (installs/cancels the cron timer), `Api\Config::save_value('account_import_lastrun', ...)`, and a
  file-log write to `{files_dir}/setup/account-import.log` (`Import::logger()`, unconditional for
  info/error/fatal regardless of `account_import_loglevel`).

## The 3 sync types -> code paths

| Type | How it's invoked | Effect inside `run()` |
|---|---|---|
| **Initial / full** | Setup UI calls `run(true, ...)` directly, or the first `async()` call of the day when delete/deactivate is configured (`firstRunToday()` - see below) | No `modified>=` filter on the LDAP/contacts search; queries **all** accounts/groups; `$sql_users`/`$sql_groups` diffing against what's *not* returned drives create/update/delete |
| **Incremental** | `async()` on every cron tick except the first-of-the-day one, i.e. `run(false, ...)` | Adds `'modified>=' . account_import_lastrun` to the contacts filter (line 268) and, for groups, to the LDAP group filter (`groups()` line 855-859) - only changed entries come back. **Deletion is forced off** (`run()` line 213-217: `if (!$initial_import && $delete !== 'no') $delete = 'no';`) - incremental runs structurally cannot see or act on deletions |
| **Daily full catch-up (for deletes)** | `Import::async()` (the cron entry point) itself decides: `$import->run(in_array($delete, ['yes','deactivate']) && self::firstRunToday())` (line 1143) | This is **the same code path as "initial/full"** (`$initial_import=true`) - there is no 3rd distinct method. `firstRunToday()` (line 1164) just checks whether `time()` falls within the first `account_import_frequency`-hours slot of the day, using the configured `account_import_time`/`account_import_frequency`. If delete is `no`, `async()` *always* passes `false` (pure incremental, forever) - the daily full-sync-for-deletes only exists when delete/deactivate is configured |

So functionally there are only 2 branches in `run()` (`$initial_import` true/false); "daily full sync"
is a scheduling decision (`firstRunToday()` gating `async()`'s call to `run(true, ...)`), not separate
logic. Tests should target: `run(true)`, `run(false)`, and `firstRunToday()`/`async()`'s dispatch logic
as 3 separate concerns - the last one is pure date arithmetic and needs no LDAP mock at all.

AD groups are always treated as "full" regardless of `$initial_import` (line 223: `$source === 'ads' ?
null : lastrun` - comment says AD doesn't reliably update group `modifytimestamp` when only membership
changes) - this is a source-specific exception worth its own test.

## Config-option matrix

All read from `$GLOBALS['egw_info']['server'][...]` (via `Api\Config`), mostly in `Import::run()`/
`groups()`. One row per option; "combines with" flags known interaction risk, not just co-occurrence.

| Option | Values | Effect | Combines with |
|---|---|---|---|
| `account_import_source` | `ldap`\|`ads`\|`univention` | Selects source backend class + drives `aliasImport()`'s attribute names (`mail` vs `proxyaddresses` vs `mailalternativeaddress`) | everything - it's the top-level axis |
| `account_import_type` | `users`\|`users+groups`\|`users+local+groups` | `groups`: also sync groups + memberships, remap primary-group id. `local`: preserve membership in SQL-only groups not present in the source (`groups()` computes `array_diff($sql_groups, $groups)`) | `account_import_delete` (groups delete is forced `no` when `local`, `run()` line 224) |
| `account_import_delete` | `yes`\|`deactivate`\|`no` | Users not seen: hard-delete via `admin_cmd_delete_account`, deactivate (`account_status=null`), or ignore. Groups: only hard-delete or ignore (no deactivate concept for groups) | forced to `no` whenever `!$initial_import` (incremental never deletes); `type=...+local+groups` forces group-delete to `no` too |
| `account_import_lastrun` | timestamp (internal, `Api\Config`) | Gates incremental filtering; **required** to be already set for `run(false,...)` - `run()` throws `InvalidArgumentException` otherwise (line 205-208) | must exercise "first incremental run ever" (unset) as an explicit error-path test |
| `account_import_aliases` | bool | Turns on `aliasImport()` per account (adds/removes `Api\Mail\Smtp\Sql` alternate addresses); `$export_ldif` param overrides this to emit an LDIF diff instead of writing | `account_import_source` (attribute name mapping differs per source) |
| `account_import_dn_regexp` | regexp or empty | Client-side post-filter on `$contact['dn']` / `$group['account_dn']`, applied *after* the source query - deliberately, so the query itself can target one **parent** DN instead of issuing multiple separate DN queries. Entries that don't match `continue` before `unset($sql_users[$account_id])`, so they DO show up as delete candidates if absent this run - confirmed intentional by Ralf given the "query broad, filter narrow" design, see `ImportDnRegexpTest.php` | `account_import_delete` |
| `account_import_loglevel` | `info`\|`detail`\|`debug` | Filters what reaches the callback logger *and* the file log for `debug`/`detail` messages; `info`/`error`/`fatal` always logged | n/a, but affects how much a test can assert via a captured logger callback |
| `account_import_frequency` / `account_import_time` | float hours / `HH:MM` | Drives `installAsyncJob()`'s cron-timer shape and `firstRunToday()`'s window check | `account_import_delete` (see sync-type table) |
| `account_import_update_source` | bool | **Write-back** direction (`hookEditAccount()`, hooked to `addaccount`/`editaccount`/`editaccountcontact`/`deleteaccount`): local SQL edits get pushed back to the source. Separate feature from `run()`'s pull-sync, but reachable *during* a `run()` because `run()` itself calls `Api\Hooks::process(['location'=>'addaccount',...])` when creating an SQL account, and `Import::hookEditAccount` is registered on that same hook (`api/setup/setup.inc.php` lines 59-62) | **must be off in every `run()`-focused test unless it's specifically testing this path** - see [[feedback_account_import_breaks_tests]], same class of hazard, self-inflicted this time since `Import` is both trigger and handler |
| `account_repository` | usually `sql` during a running install | Temporarily monkey-patched by `accountsFactory()`/`contactsFactory()` per source, then restored; `run()` also checks it to decide whether to fire `addaccount`/`editaccount` hooks at all (skipped during a `sql`->other migration, line 369/429) | affects whether hooks fire at all, separate from whether `update_source` writes back |
| `default_group_lid` | comma/semicolon list | Only used in `type=users`-only mode, to set new users' primary group + memberships | `account_import_type` (`groups` modes compute primary group from the source instead) |

## Why not mock the LDAP protocol

`Ldap::__construct()`, `Ads::__construct()`, and `Contacts\Ldap::__construct()` (when no `$ds` is passed)
all call `Api\Ldap::factory()` and connect immediately - there is no existing pluggable transport, driver
interface, or fake-server hook anywhere in `api/src/Ldap.php` (checked - `factory()` has no test seam), and
nothing in the repo currently mocks it (checked `api/tests`, `stylite/tests`). Building a real fake LDAP
wire server (or wiring in a userland LDAP emulator) is possible in principle but is exactly the "live
LDAP/ADS server" Ralf asked to avoid, and it would only test `Ldap`/`Ads`'s own query-building - not
`Import`'s sync/diff/config logic, which is the actual thing lacking coverage. **Target the backend-object
contract instead** (the method list above), which is also the boundary the class comments already call out
("`@access internal only use the interface provided by the accounts class`" on `Ldap`).

## Testability obstacles found

1. **No injection seam in `Import`.** `accountsFactory()`/`contactsFactory()` are `protected static`,
   called via `self::` (not `static::`) from both the constructor and `hookEditAccount()`. Because they're
   called via `self::`, a subclass overriding them would **not** be picked up by the parent's own methods
   (PHP's `self::` is early/static-bound to the defining class, unlike `static::`) - so "subclass Import and
   override the factory" does **not** work as a mocking strategy without also changing `self::` ->
   `static::` at those ~4 call sites. That's a tiny, low-risk, semantically-neutral change (no behavior
   difference for the existing non-test code path) but it *is* a production code edit - flagging for
   Ralf's sign-off rather than doing it silently.
2. **Process-lifetime static cache.** `accountsFactory()`/`contactsFactory()` each hold `static $cache =
   []`, keyed by backend name (`'ldap'`, `'sql'`, ...) - reused across every call in the whole PHP process.
   Under PHPUnit's shared-process model (`doc/ai/testing.md`) this means a real LDAP-backed object built by
   one test would leak into a later test's `Import` instance unless carefully reset via reflection, or
   unless we never call through the real factories in test builds at all (see proposed approach below,
   which sidesteps this entirely by never calling the real factories).
3. **`Contacts\Storage`'s `so_accounts`** (`api/src/Contacts/Storage.php` line 288) is `new $class()` with
   zero constructor args and zero DI seam - same live-connect problem as `Ldap`/`Ads`, one level removed
   (reached via `Api\Contacts`, not `Api\Accounts`).
4. **Hook feedback loop** (see `account_import_update_source` row above) - real risk of a test silently
   exercising the write-back path when only pull-sync was intended.
5. **`admin_cmd_delete_account`** (used by `deleteAccount()` when `account_import_delete=yes`) requires an
   admin session per `doc/ai/testing.md`'s admin-test-account section - delete-path tests need
   `switchUser($EGW_ADMIN_USER, ...)` around the `run()` call, then switch back.
6. **File logging side effect** - `Import::logger()` always attempts to write to
   `{files_dir}/setup/account-import.log` for info/error/fatal regardless of the injected `$logger`
   callable. Point `files_dir` at a throwaway dir for the test run, or accept the write (it's harmless,
   just noise) - not worth changing production code for.
7. **`Api\Accounts::id2name()`/CLI reliability** - `hookEditAccount()` and `aliasImport()`'s logging both
   call `Api\Accounts::id2name()`; per [[feedback_accounts_singleton_broken_in_phpunit_cli]] this is
   unreliable in bare CLI PHPUnit runs - since these tests need a real `LoggedInTest`-bootstrapped session
   anyway (for `admin_cmd_delete_account`, ACL, etc.), this should already be covered, but worth an explicit
   check once the harness exists.

## Proposed test architecture (Phase 1: built, in `api/tests/Accounts/`)

**Core idea:** never call `Import`'s real constructor. Build an `Import` instance via
`(new \ReflectionClass(Import::class))->newInstanceWithoutConstructor()`, then use `ReflectionProperty` to
set `accounts`, `accounts_sql`, `contacts`, `contacts_sql`, `frontend_sql`, `contacts_sql_frontend`, and
`_logger` directly - `accounts_sql`/`contacts_sql`/`frontend_sql`/`contacts_sql_frontend` are the *real*
`Sql` backends (`Api\Accounts\Sql`, `Api\Contacts\Sql`) running against the test DB via `LoggedInTest`
(exercises the real create/update/delete/member-management SQL logic, which is exactly what we want
covered); `accounts`/`contacts` are new small fixture-backed fake classes standing in for the *source*.
This required **zero changes to `Import.php`** to get started - the `self::`->`static::` tweak above was
made anyway (approved), but reflection bypasses the constructor (and its factory calls) entirely either way.
Implemented as `ImportTestCase` (the harness base class - `buildImport()`, `setImportConfig()`,
`deleteAfterTest()`, `realAccountId()`/`realAccountRead()`).

**Correction vs. the original plan below:** `FakeLdapAccountsBackend` does **not** need to `extends
Api\Accounts\Ldap` - nothing in `Import` type-checks its source backend against `Ldap`/`Univention`, only
against `Ads::class` (the `is_a($this->accounts, Ads::class)` check in `Import::groups()`, for the
`getMembers()` vs `members()` choice). A plain duck-typed class covers `ldap`/`univention`; only the
Phase-2 `FakeAdsAccountsBackend` needs a real `extends Api\Accounts\Ads`. Likewise the fake contacts source
doesn't need to extend `Contacts\Ldap` - nothing type-checks it either. This is a meaningful simplification
over the original plan (no need to neutralize a live-connecting parent constructor for the common case).

Two new fixture classes built so far (`api/tests/Accounts/Fixtures/`):

- **`FakeLdapAccountsBackend`** (plain class, see correction above) implementing the backend contract
  listed above with in-memory fixture arrays instead of `$this->ds`/`ldap_*` calls. Supports per-account
  `account_uuid`/`account_dn` (for the UUID-then-lid-then-id_conflict lookup chain in `run()` lines
  340-354), `modified` timestamps (for incremental filtering), and a `memberOf` map for `members()`/
  `memberships()`. For the "users only" sync path actually exercised so far, `read()` is the *only* method
  called on the source accounts backend at all - `search()`/`memberships()`/`members()` are stubs that
  throw until Phase 2's groups tests need them.
- **`FakeAdsAccountsBackend extends Api\Accounts\Ads`** - same idea, but needs `getMembers()` (not
  `members()`) for the group-member path, and AD's always-full-groups behavior needs no special fixture
  support (it's driven by `$source==='ads'` in `Import`, not by the backend).
- **`FakeContactsSource`** (plain class, see correction above) - the piece needing the most careful
  mapping; built so far only for the "users only, no aliases" path: `search()` (matches
  `Contacts\Ldap::search()`'s parameter order, honors the `modified>=` incremental filter element, sets
  `$start[2] = ''` to end pagination after one page) and `read()` (used only when `account_import_type`
  includes "groups"). The exact key set a real `Contacts\Ldap` row carries for `aliases`/`dn`-regexp
  scenarios still needs mapping in a later phase - see the `email`-shape gotcha below for what's confirmed
  so far.

Real (non-faked) collaborators every test still needs, via `LoggedInTest`: `Api\Hooks::process()` (with
`account_import_update_source` off unless testing write-back), `Api\Asyncservice` (real, or assert on its
DB row rather than mocking), `admin_cmd_delete_account` (real, needs admin session for delete-path tests).

## Gotchas found while building Phase 1 (beyond the `$frontend` bug above)

- **Fixture contact rows use `email`, not `contact_email`.** `Contacts\Sql`'s app-facing field name for
  the email column is `email` (`Contacts\Sql::read()`/`data_merge()` operate on app field names via
  `db_cols`); `contact_email` is only the *raw SQL column name*, used solely inside
  `Accounts\Sql::read()`'s hand-written `JOIN ... contact_email AS account_email`. Putting `contact_email`
  in a fixture contact row is silently ignored by `Contacts\Sql::save()` (unknown field) and then shows up
  as a **permanent phantom diff** on every rerun (present in the fixture, never in the saved row) - exactly
  the kind of subtly-wrong-looking-plausible mistake worth documenting so it isn't repeated in later phases.
- **This dev box's real `account_import_*` config leaks into any test that doesn't override it.** A test
  that doesn't explicitly set `account_import_dn_regexp` picks up whatever this shared install's *real*
  LDAP/ADS config has configured for it - since fixture contacts here carry no `dn` at all, a real non-empty
  regexp silently `preg_match()`s against `null` and skips every account, with zero errors reported (`run()`
  just reports "All accounts are up-to-date" with 0 processed). `ImportTestCase::buildImport()` now forces
  `account_import_dn_regexp => ''` and `account_import_aliases => false` by default (alongside
  `account_import_update_source => false`) precisely so a future test can't silently inherit real config -
  override any of the three explicitly when a test actually needs to exercise that path.
- **`$GLOBALS['egw']->accounts`'s session cache cannot be trusted for a just-written row in the same CLI
  process** - confirms [[feedback_accounts_singleton_broken_in_phpunit_cli]] applies to more than
  `id2name()`: `name2id()` returned nothing for an account a real `Accounts\Sql::save()` call had just
  created, and `Accounts\Sql::delete()`'s own internal `id2name()` call (used to find the linked contact to
  clean up) has the exact same problem when driven through a frontend-less/cache-blind path. Verification
  and cleanup in `ImportTestCase` go through `realAccountId()`/`realAccountRead()` (direct DB queries) and
  a properly-linked `(new Api\Accounts('sql'))->backend` for deletes, never the cached frontend.

## Test case plan (first pass - not exhaustive)

Group by what's under test:

1. **Pure logic, no fixtures needed:** `firstRunToday()` across frequency/time combinations;
   `installAsyncJob()`'s frequency->cron-timer-shape mapping (`36h+` -> `day: */N`, `24-36h` -> daily,
   `1-24h` -> `hour: */N`, `<1h` -> `min: */N`); config-validation error paths (`InvalidArgumentException`
   for bad `account_import_source`/`_type`/`_delete`, and incremental-without-`lastrun`).
2. **Initial import, users only:** create-from-empty, update-existing (diff computation - verify
   `array_diff_assoc` picks up real changes and ignores LDAP-only/empty fields), up-to-date no-op,
   `account_id` conflict (offset-then-give-up-to-`MAX_INTEGER` path), account-vs-group name collision
   (`elseif ($account_id < 0)` branch), `dry_run=true` (assert **zero** SQL writes + correct counters).
3. **Initial import, `users+groups` / `users+local+groups`:** group create/update/uptodate, primary-group
   remap when source/SQL group ids differ, local-group membership preservation (`local` variant), group
   name collides with existing user (skip+error), UUID-based group re-identification after a name change.
4. **Deletion (`account_import_delete`):** `yes` (verify `admin_cmd_delete_account` invoked, with admin
   session), `deactivate` (verify `account_status` cleared, not deleted), `no` (verify untouched); groups
   have no `deactivate` option - only yes/no; anonymous user is never deleted even if absent from source
   (line 625) - dedicated test.
5. **Incremental:** only-changed entries processed (verify unchanged fixture accounts produce zero writes
   even if present), delete forced off regardless of config, `lastrun`-unset error path, AD's
   always-full-groups override (`source=ads` ignores `lastrun` for groups even on incremental).
6. **Daily full catch-up dispatch:** `async()`'s `firstRunToday() && delete-enabled` gate, purely via
   mocking/asserting `firstRunToday()`'s time math plus checking which `run()` args `async()` would pass
   (may need a thin seam - `async()` is `public static` and constructs its own `Import` - possibly easiest
   tested by asserting `firstRunToday()` in isolation plus one `run(true)` / `run(false)` behavioral test
   each, rather than driving `async()` itself end-to-end).
7. **Aliases (`account_import_aliases`):** add/remove diffing per source (`ldap`/`ads`/`univention`
   attribute names), `$export_ldif` mode (assert LDIF content, not SQL writes), `dry_run` (log-only).
8. **`account_import_dn_regexp`:** matching vs non-matching DNs, and specifically the "does a
   non-matching-but-still-present account get incorrectly counted/treated as deleted" edge case flagged
   in the config matrix above - confirm actual behavior against current code before deciding if it's a bug
   to fix or a documented limitation.
9. **Write-back (`account_import_update_source` + `hookEditAccount`):** deliberately separate test class/
   group from the pull-sync tests above (per obstacle #4); covers `addaccount`/`editaccount`/
   `editaccountcontact`/`deleteaccount` hook payloads round-tripping to the fake source backend's
   `save()`/`delete()`, plus the "former local account being turned into a synced one" branch (line
   1357-1362) and the GUID-validation-exception recovery branch (line 1397-1416).

## Decisions (were open questions, resolved 2026-08-31)

1. Make the tiny `self::` -> `static::` change in `Import`'s 2 factory methods (obstacle #1) - approved.
2. New fixture/mock classes and test files live under `api/tests/Accounts/` (mirroring `api/src/Accounts/`).
3. `Univention` (currently a thin `extends Ldap` override) is in scope for Phase 1, alongside `ldap`/`ads`
   - not deferred. Its `save()`/`name2id()`/`id2name()` overrides (`api/src/Accounts/Univention.php`) still
   need a detailed read before its fake backend is built.
4. The write-back path (`hookEditAccount`, item 9 above) is an explicit **separate follow-up**, not bundled
   into this round - it's a real, currently zero-coverage feature per
   [[feedback_account_import_breaks_tests]], but a structurally separate sync direction.

## Suggested phasing

- **Phase 1: DONE.** `ImportTestCase` + `FakeLdapAccountsBackend` + `FakeContactsSource` +
  `ImportInitialUsersTest` (5 tests: 3 config-validation error paths, create-from-source,
  no-op-on-unchanged-rerun). Found+fixed the `$frontend` bug above along the way.
- **Phase 2: DONE.** `FakeAdsAccountsBackend extends Api\Accounts\Ads` (the one fixture that genuinely
  needs the `extends`, for `getMembers()` + the `is_a(..., Ads::class)` check) + `ImportGroupsTest`
  (group+member create incl. primary-group remap, name-collision-with-existing-user skip, the Ads
  `getMembers()` path, group update, local-groups membership preservation
  (`account_import_type=users+local+groups`, the `array_diff($sql_groups, $groups)` path in
  `Import::groups()`), and a `univention`-sourced run - `Univention extends Ldap` and `Import` never
  type-checks against it specifically (confirmed behaviorally by that test passing; its own
  `save()`/`name2id()`/`id2name()` overrides in `api/src/Accounts/Univention.php` still haven't been read
  in detail, but nothing in `Import`'s pull-sync path calls those on the source backend at all, so that
  gap is Phase 5/write-back's problem, not this one's) + `ImportDeletionDetectionTest` (dry-run-only
  deletion *candidate-detection* coverage for both users and groups - see "Deletion tests are
  dry-run-only" above; deletion actually *executing* is deliberately not covered against this shared DB).
  Found+fixed the `Stalwart::updateGroup()` and the group-deletion stale-variable bugs above along the way.
- **Phase 3: DONE.** `ImportIncrementalTest` (modified-filter-driven partial contact sync, deletion
  structurally disabled on any incremental run, Ads-always-full-groups vs. Ldap-respects-the-filter
  contrast pair) + `ImportDnRegexpTest` (pins down, doesn't fix, the "does a non-matching-but-still-present
  entry get miscounted as a delete candidate" question - answer: yes, it does) +
  `ImportSchedulingTest` (`installAsyncJob()`'s frequency->cron-shape data-provider coverage against the
  real `egw_async` table, with a skip-if-a-real-job-already-exists guard and cancel-in-finally cleanup).
  `Import::firstRunToday()` itself deliberately left unverified - see "Two things flagged as suspicious,
  confirmed intentional" above (both items there were raised, then settled, during this phase). Added the
  `run($save_state=false)` testability parameter here too, once the `account_import_lastrun`
  persistent-side-effect problem surfaced while scoping this phase - see that section above.
- **Phase 4: DONE.** `ImportAliasesTest` (add/remove diffing against a real `Api\Mail\Smtp\Sql` backend,
  dry_run log-only, LDIF export for `ldap`+`ads`). Found+fixed bug #4 above (the alias-triggered
  duplicate-contact crash) along the way. Also had to correct a wrong initial assumption about
  `$export_ldif`'s semantics mid-phase: it's **not** an import preview - it diffs SQL's current alias
  state against what the source shows, to generate a write-back diff to push TO LDAP/AD (matching
  `run()`'s own docblock, re-read more carefully after the first LDIF test's assertions failed in a
  telling way - empty attribute values, because a brand-new account has nothing in SQL yet to diff). Not
  covered: `univention`'s LDIF variant (same attribute-switch pattern as `ldap`/`ads`, lower priority -
  would follow the same shape as the two done).
- **Phase 5: DONE.** `ImportWritebackTest` (`TestableWritebackImport` subclass overriding
  `accountsFactory()`/`contactsFactory()`, no `Import` instance/harness needed since
  `hookEditAccount()` is a plain static method) - guard/no-op cases (`account_import_update_source`
  off; the `caller_method` self-loop guard), `addaccount` (incl. the real uuid/dn write-back into
  SQL), `editaccount` (update of an already-synced entry), `deleteaccount`. See "Phase 5: write-back
  testability" above for the `Api\Config` reflection gotcha and the same-id gotcha found along the
  way. Not covered: `editaccountcontact`.
