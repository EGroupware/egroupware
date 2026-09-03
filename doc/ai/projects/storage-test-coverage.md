# Api\Storage test coverage

Goal: map the current behavior of `api/src/Storage.php` and every class under `api/src/Storage/`
(the generalized SQL storage engine most apps' `bo`/`so` classes are built on), and build a full
test suite for it. Started because the area had no test coverage at all... except it did, mostly
undocumented as a single body of work. This doc is that missing map, plus the phased plan to close
the real gaps. Comparable in scope to
[accounts-import-test-coverage.md](accounts-import-test-coverage.md) - expect this to span more
than one session.

**STATUS: All 5 phases done** (2026-09-03). `api/tests/Storage/` went from 92 tests to 259, all
green - **including `ContactsTest`**: it was never actually environment-limited, `EGW_ADMIN_PASSWORD`
just needs to be passed through explicitly on every `docker exec` call
(`docker exec -e EGW_ADMIN_PASSWORD="$EGW_ADMIN_PASSWORD" egroupware ...`) since `docker exec` does
NOT forward host env vars by default even when the var is set in the calling shell - a real gap in
this project's own testing methodology throughout most of this session, not a genuine limitation of
the box. Corrected after the fact; every "pre-existing `EGW_ADMIN_PASSWORD`-unavailable" note
elsewhere in this doc (and in `invoices/tests/BoTest.php`'s file-access tests) was wrong for the same
reason - re-verified clean with the flag. Along the way: fixed
two broken/fragile pre-existing tests (`BaseTest`'s domain resolution, its `t_modified` clock-skew
assertion); extended the `egw_test` fixture three times (uc column, bool column, JSON column);
found and fixed one real CI-breaking production bug (`Customfields::getSerial()` leaking an open DB
transaction on its error path - see Phase 3); found and documented (deliberately not fixed) several
more real quirks/bugs across `Db.php`, `Storage\Base`, `Storage\History`, `Storage\Merge`,
`Storage\Customfields`, and `Storage\RowsIterator` - see each phase below for specifics. Corrected
one of this doc's own earlier findings mid-project (`Json`/`JsonCF`/`JsonTrait` were wrongly called
"zero usage" - they're used by the gitignored `invoices/` app, a real blind spot in the original
grep). Everything is pushed to `origin/master` as of commit `5c4a592de8` (the getSerial() fix) with
Phase 5's three commits (`25f70f4996`, `469b53910f`, `a7fd4b48bd`) plus this doc's remaining updates
still local-only pending the next push.

## Starting state (found, not assumed - see `git log -- api/tests/Storage/`)

Already committed and passing before this project started:

- `api/tests/Storage/BaseTest.php` - `Base::save()` (insert path) + `read()` round trip against the
  `egw_test` fixture table.
- `api/tests/Storage/SanitizeOrderByTest.php` - thorough, arguably-complete coverage of
  `Base::sanitizeOrderBy()` (SQL-injection regression suite for CVE-2024-40614/CVE-2026-22243).
- `api/tests/Storage/CustomfieldsTest.php` - CF CRUD, private-field ACL enforcement,
  `get_options_from_file()` happy path, invalid-name sanitization.
- `api/tests/Storage/TrackingTest.php` - only `sanitize_custom_message()`.
- `api/tests/Storage/MergeTest.php` (+ `TestMerge.php` helper) - `merge_string()` for
  `text/plain` (full) and `text/html` (currently **skipped**, see file comment), plus the
  split-placeholder-tag bug regression.
- `api/tests/Storage/ContactsTest.php` - one `Api\Contacts`/`Storage`-adjacent ACL regression test
  (needs `EGW_ADMIN_PASSWORD` for its admin-fixture setup, passed through explicitly on `docker exec`
  - see the STATUS note above).

**Genuinely zero coverage**: `History.php`, `Base2.php`, `Db2DataIterator.php`, `RowsIterator.php`,
`Json.php`, `JsonCF.php`, `JsonTrait.php`, and most of `Base.php`'s/`Tracking.php`'s own surface
(see below - `Base.php` only has save+read tested; `Tracking.php` only has the message-sanitizer).

## Environment fixes made while establishing a baseline

- **`BaseTest::setUpBeforeClass()` was broken on this box** (and any box where the domain isn't
  literally named `"default"`): it indexed `$GLOBALS['egw_domain'][$GLOBALS['EGW_DOMAIN']]`
  directly, but `EGW_DOMAIN` is the literal string `"default"` per `doc/phpunit.xml` - never a
  real key. Every other test (`LoggedInTest::load_egw()`) resolves the domain via
  `Api\Session::search_instance()`, whose fallback-to-first-configured-domain logic is what
  actually makes it work. Fixed `BaseTest.php` to use the same resolution. Fixes `Db\Exception\
  Connection: No DB host set!` on any install with real domain names (e.g. this box's
  `boulder.egroupware.org`).
- **`testSaveInternalState()`'s `t_modified` assertion compared the wrong clocks**: it compared the
  raw DB `current_timestamp` default against PHP's `new DateTime('now')` with a 1-second delta.
  `Api\Db::setTimeZone()` (meant to align the DB session timezone with the app's) is **dead code -
  zero callers anywhere in the repo** (confirmed via grep), so the DB session runs on whatever
  timezone the DB server itself defaults to, which can differ from the PHP container's by whole
  hours (confirmed: 2h skew on this box). Fixed by comparing against a `NOW()` queried from the
  same DB connection instead of PHP's clock. The original test author already knew this class of
  problem existed - `testReadFromDb()` explicitly `unset()`s `t_modified` before comparing.

Both fixes are narrow, in the test files only - no production code changed.

## Per-class inventory and gaps

### `Storage\Base` (1830 lines) + `Storage\Base2` (111 lines) - highest priority

The shared backbone for most apps' list/search/CRUD. No DB-mocking seam exists anywhere in this
codebase for `Storage\Base` - every real test needs the live `egw_test` fixture DB (see
`BaseTest`'s bootstrap pattern). `Base2` is a small, self-contained magic-property (`__get`/`__set`
+ `'id'` alias + `as_array()`) wrapper, fully testable via construction alone.

**Fixture constraint (blocks several tests below until addressed)**: `egw_test`
(`api/tests/fixtures/apps/test/setup/tables_current.inc.php`) has no unique-key (`uc`) column and
no bool column. `not_unique()`, `read()`'s unique-key fallback, and `db2data()`'s bool
auto-conversion cannot be tested without extending this fixture schema (a `tables_update.inc.php` +
version bump, `test` app version currently `17.1.001`).

Untested, in priority order:

1. **`parse_search()`** (via `search()`) - wildcard `*`/`?` -> SQL `%`/`_`, `!`-negation (incl.
   `IS NULL OR NOT LIKE` composition), empty-string/nullable-column handling, dotted
   `table.column` typed criteria, array values -> `IN(...)`. Highest usage, zero coverage - this is
   the SQL-building logic behind every list/search box in the product.
2. **`search()`'s `$filter` param**, especially the `!''`-only-for-safe-column-names regex guard -
   an injection-attempt test in the same style as `SanitizeOrderByTest`, since that guard is what
   stands between a crafted filter key and raw SQL concatenation.
3. **`search2criteria()`** - free-text token parser: quoted phrases, `AND`/`OR`/`NOT` (literal +
   `lang()`-translated - force `en`), `+`/`-` prefixes, `#123` exact-id shortcut, numeric-column
   equality shortcut, CF search delegation (`Api\Storage`-only branch), relevance ordering.
4. **`save()`**'s two branches (autoinc-insert vs.  update-or-insert), the `$extra_where`
   optimistic-locking return-`true` path (etag-style concurrent-edit detection, real apps depend on
   this), the `USER_TIMEZONE_READ` staleness-reload hack (undocumented outside a comment).
5. **`update()`** - partial-field update, raw-SQL-fragment integer keys, no-op-when-empty.
6. **`delete()`** - scalar id / explicit `$keys` / fallback to `$this->data`, `$only_return_query`.
7. **`Base2`** - `id` alias, silent-ignore of unknown properties (a real footgun for app-code
   typos), `as_array()`.
8. **`get_default_search_columns()`**, **`_get_columns()`** (`AS alias` extraction, `DISTINCT`,
   `'*'` expansion), **`query_list()`** (note: static **process-wide** cache keyed by serialized
   args - a cross-test leak risk, same family as the documented `Acl` static-cache issue).
9. Lower priority / environment-gated: `fix_group_by_columns()` (PostgreSQL-only, dead on a MySQL
   run - document as a known gap, don't force), `search_return_iterator`/UNION-query building
   (stateful static vars, higher setup cost).

### `Storage\History` (484 lines) + `Storage\Tracking` (1400 lines) - second priority

`History` writes to the core `egw_history_log` table (always installed, no app-specific schema
needed - no fixture blocker). `Tracking` is abstract; a concrete test subclass already exists
(`api/tests/Storage/TestTracking.php`) but only exposes `sanitize_custom_message()` - needs
extending to set `app`/`id_field`/`field2history` and reach the real methods. **Testability seam
found**: the `$notification_class` constructor param lets a test inject a mock notification class,
so `do_notifications()`/`send_notification()` are fully testable without a live mail backend.

Priority order:

1. **`Tracking::changed_fields()`** - pure, no DB, highest value. Empty≡empty skip, `DateTime`
   1-second-precision compare, array-order-insensitive compare, CRLF-only-diff ignored, 1:N-relation
   compaction diff, `##`-prefixed always-changed passthrough (vCard/iCal X-attribute path), `#cf`
   unset-vs-empty distinction.
2. **`Tracking::do_notifications()` + `send_notification()`** via the mock `notification_class` -
   creator notify (incl. group-creator expansion), assigned user/group notify with
   `assignment_changed` correctness, copy addresses (`@`-filtered), self-notify suppression,
   dedup-by-email across all three branches, `$check2pref` mapping incl. the `'assignment'`-only
   special value.
3. **`History::search()`** - the "no `history_record_id` filter -> empty array" guard (prevents
   full-table scans), filter-key auto-prefixing, `$order`/`$sort` validation/fallback (SQL-injection
   -adjacent, same family as `SanitizeOrderByTest`), DateTime encode/decode round-trip.
4. **`History::add()`/`delete()`** - no-op on equal values (loose `==` compare), `delete(null)`
   wiping *all* rows for the app (confirm intended, then lock down), `delete_field()` scoping.
5. **`History::needs_diff()`** - pure function, full input matrix incl. the PGP-position edge case
   (`strpos(...) == 0` - only true if the PGP marker is at position 0; confirm intended vs. actual
   before calling it a bug).
6. **`Tracking::track()`** - confirm/lock the "new entry (`$old===null`) never calls
   `save_history()`" contract, `$skip_notification` short-circuit, `$changes`-gating of
   `update_links()`.
7. **`History::get_rows()`** - CF privacy filter (private/`#`-prefixed CFs excluded), diff-vs-full-
   value branch selection; filemanager-attachment-merge branch only if feasible without excessive
   VFS setup, else document as deferred/integration-only.
8. Lower priority / integration-shaped, consider deferring: `update_links()` (needs real `Api\Link`
   writes), `get_body()`/`format_line()` (snapshot-ish, lower bug yield), `get_link()`/
   `get_notification_link()` (mostly `Api\Link` passthrough).
9. **Noted but not yet a test**: `send_notification()` swaps `$GLOBALS['egw_info']['user']` to the
   notified recipient with **no try/finally** around the restore - an exception mid-flight
   (`get_body()`/`get_subject()`/etc.) would leak that swapped global user state into the rest of
   the request. Worth a "throwing get_config" regression test once the mock scaffolding exists.

### `Storage\Merge` (3747 lines) gaps - business-critical mail-merge engine

Already covered: see "starting state" above. Gaps, all reachable via `merge_string()` + the
existing `TestMerge` subclass (no DB/office-file fixtures needed unless noted):

- `$$IF field~compare~then~else$$` command (untested: match/no-match/`~EMPTY~`/nested-placeholder
  compare value/bare-placeholder-name args).
- `$$NELF field$$` / `$$NENVLF field$$` (line-feed-if-nonempty / line-feed-only).
- `$$LETTERPREFIX$$` / `$$LETTERPREFIXCUSTOM ...$$`.
- `$$pagerepeat$$` multi-entry repeat + the "2+ ids but no pagerepeat tag" error path.
- Numeric (`number_format()`, `:decimals,thousands$` variants) and date (`date_fields`, `/date$$`,
  `/time$$`, `:FORMAT$$`) placeholder formatting.
- `cf_link_to_expand()` (linked-CF expansion incl. multi-value `select-account`) - needs
  `LoggedInTest` + `Customfields::update()`, no office files.
- `get_app()`/`get_app_class()`/`get_app_replacements()` app-name resolution.
- `get_links()`/`get_all_links()` placeholder family - needs `Api\Link::link()` fixtures.
- `is_export_limit_excepted()`/`getExportLimit()` - note the **process-static caches**
  (`$is_excepted`, `$exportLimitStore`), same cross-test-leak family as `query_list()` above.
- `is_implemented()` - pure static mimetype/extension matrix, trivially unit-testable.
- `contact_replacements()` - full contact-field-to-placeholder mapping.
- `replace()`'s plain-substitution and YAML `{{name/regex/replacement}}` paths (skip the
  `tidy`-extension-dependent HTML-cleanup path unless `tidy` is confirmed available).
- Deferred/lower priority: `merge()`/`merge_file()`/`download()` (needs real `.odt`/`.docx` zip
  fixtures - nontrivial, separate phase), `share_placeholder()` (EPL/stylite-only on this install),
  `merge_entries()`/`ajax_merge_multiple()` (app-registry-dependent, better as an addressbook-
  specific integration test than a generic `Merge` test), `$$label$$` tiling (complex, `text/plain`
  smoke test only).

### `Storage\Customfields` (705 lines) gaps

Already covered: field CRUD, private-field ACL incl. give/remove-access mutation flows,
`get_options_from_file()` happy path + not-found, invalid-name sanitization.

Priority order:

1. **`format()`** - completely untested despite being pure-ish and high-value: `select-account`
   (single + `rows>1` multi-value), `checkbox`, `select`/`radio` (incl. `values['@']` file-reference
   reuse and the `#$val` unknown-option fallback), `date`/`date-time`, `htmlarea` (tag-strip +
   CRLF conversion), link-types default branch.
2. **`update()`'s field-reordering logic** (auto `cf_order` renumbering by 10s when `order` changes
   or isn't a multiple of 10) - real, easy-to-break business logic, entirely untested.
3. **`save()`'s deletion semantics** (`NOT cf_name IN (...)` - a bug here silently deletes field
   definitions) - untested.
4. **`getSerial()`** - serial-number generation: first-call default, increment-last-digit-group via
   `SERIAL_PREG`, zero-padding preservation (`0009`->`0010`), `Api\Db\Exception` on no-cf_id-row.
   Lock-intent (`FOR UPDATE`) doesn't need a concurrency test, just documenting it's there.
5. `get_options_from_file()` remaining branches (non-`.json` rejected, `header.inc.php` blocked,
   malformed JSON).
6. `get_link_types()`, `update_links()` (needs `Api\Link` fixtures), `get_account_cfs()`/
   `get_email_cfs()` (simple filters, low effort), `handle_files()`/`handle_file()` (VFS
   side-effects, use the `mountFilesystem()` helper pattern already in `CustomfieldsTest`).
7. **Caching gotcha for future test-writers**: `Api\Cache::getInstance()`/`setInstance()` is keyed
   by `__CLASS__` - very likely process-wide, same risk class as the documented `Acl` static-cache
   leak. `invalidate_cache()` is called by `update()`/`save()` so tests that always go through those
   self-clean, but a test reading cached `get()` results across two account contexts without an
   intervening `update()`/`save()` should call `invalidate_cache($app)` explicitly.

### Usage corrections (this section's original "zero usage" claim was wrong for Json*)

**CORRECTION**: the original grep behind this section only searched the tracked repo. `Json`/
`JsonCF`/`JsonTrait` ARE genuinely used - by `invoices/` (present on disk, but `.gitignore`'d, line
33: `/invoices/` - a real installed app invisible to a repo-wide grep, same blind-spot class as EPL
apps but not actually EPL). `invoices/src/Bo.php` extends `Api\Storage\JsonCF` directly and also
constructs a plain `Api\Storage\Json` instance for a `positions` sub-storage. **Treat `Json`/
`JsonCF`/`JsonTrait` as real, in-use infrastructure, not low-priority speculative coverage** - a
live-DB round-trip test against a real JSON column is worth doing after all, not just the pure-array
trait tests. Lesson: when a class has "zero usage" in a repo-wide grep, check `.gitignore` for other
installed-but-untracked app directories before concluding it's genuinely unused - don't assume EPL
is the only blind spot.

`Db2DataIterator` and `RowsIterator` remain unconfirmed as used anywhere - re-checked against every
directory present on disk (tracked AND `.gitignore`'d apps alike), only the class definitions and
`Base.php`'s own dead `search_return_iterator` path reference `Db2DataIterator`; nothing references
`RowsIterator` at all. Note: `Api\Mail\Account`'s iterator-returning methods (`identities()`,
per its own "most methods return iterators" docblock) use a DIFFERENT class,
`Api\Db\CallbackIterator` (outside the `Storage\` namespace, not covered by this project's mapping
at all) - worth a mention for whoever eventually maps `Api\Db\CallbackIterator`'s own coverage, but
out of scope here.

- **`Db2DataIterator`** - possible real bug found: `current()` calls
  `$this->storage->data2db($data)` (user->server direction) but the class name/docblock both say it
  applies `db2data` (server->user) - `search_return_iterator` (its only construction site in
  `Base::search()`) is itself dead (`false`, never set `true` anywhere in the main repo), so this is
  currently unreachable in practice. **Flag to Ralf, don't silently "fix"** - write a regression-
  style test that documents current behavior either way, per his call.
  Test scenarios: full `foreach` iteration via a fake `Base`-shaped storage + `ArrayIterator`,
  `$rs=null` safety (no crash), the data2db/db2data documentation test above.
- **`RowsIterator`** - pages through any duck-typed `get_rows($query, &$rows, &$readonlys)` object,
  `CHUNK_SIZE=500`. Test with a fake `get_rows` source across: single page (<500), exact 2-page
  boundary (500 then 0), multi-page (500 then <500), zero rows, `key()` with/without an explicit
  `$key` column, `rewind()` mid-iteration (restarts from page 1, does NOT replay a cached chunk),
  non-array/non-int-keyed entries stripped, constructor throws `WrongParameter` without `get_rows`.
- **`JsonTrait`** (used by `Json extends Base` and `JsonCF extends Api\Storage`, both thin
  constructors) - `data2db()`/`db2data()` round-trip is pure-array logic, no DB needed: JSON-blob
  encoding excludes null/int-keyed/real-DB-column/`USER_TIMEZONE_READ` keys and respects
  `column_preg`; `db2data()`'s existing-key-wins-over-blob-key merge order. Magic accessors
  (`__get`/`__set`/`__isset`/`__unset` incl. `'id'`->`autoinc_id` alias and the `__set('data',...)`
  recursion guard).

## Phase plan / status

- [x] **Phase 0 - Mapping** (this doc). Environment fixed: `BaseTest` domain resolution,
      `t_modified` clock-skew assertion.
- [x] **Phase 1 - `Base.php`/`Base2.php` core CRUD** (highest blast-radius). `egw_test` fixture
      extended (`t_uniq` uc column + `t_active` bool column, `test` app v17.1.001 -> 17.1.002,
      commit `5e68a7176b`). Landed as three files, 41 new tests total, all green:
      - `api/tests/Storage/SearchTest.php` (commit `e2cf834489`) - `parse_search()`, `search()`'s
        `$filter` injection guard, `search2criteria()`, `get_default_search_columns()`,
        `query_list()`'s stale-cache behavior.
      - `api/tests/Storage/SaveDeleteTest.php` + `Base2Test.php` (commit `c744532261`) - `save()`'s
        update-or-insert branch, `$extra_where` optimistic locking, the `USER_TIMEZONE_READ`
        staleness-reload hack, `update()`, `delete()`, `not_unique()`, `Base2`.
      - Deferred (documented, not done): the autoinc-less-table branch of `save()` (fixture only has
        an autoinc table), `search2criteria()`'s full AND/OR/NOT/quoted-phrase matrix, `_get_columns()`
        directly, UNION-query/`search_return_iterator` paths.
      - **Found, not fixed (out of scope for a test-only pass)**: `Api\Db::connect()` only calls
        `set_capabilities()` when opening a NEW physical connection - reusing the pooled static
        `self::$ADOdb` link skips it, so a second `Api\Db` instance built later in the same process
        can silently keep the wrong default capabilities (e.g. invalid `CAST(%s AS varchar)` instead
        of `AS char` for MySQL/MariaDB). `SearchTest.php` works around it locally in
        `setUpBeforeClass()`. Worth a real fix in `Db.php` at some point - ask Ralf before touching
        shared `api/` behavior.
      - Also documented (not fixed): `update()`'s docblock claims `merge=false` reduces
        `$this->data` to just `$fields` - the actual code leaves `$this->data` untouched. Locked
        down via test + comment, not "corrected" to match the docblock.
- [x] **Phase 2 - `History.php` + `Tracking.php`**. 41 new tests, all green:
      - `api/tests/Storage/HistoryTest.php` (commit `e04d8e1ac7`, 23 tests) - `add()`/`delete()`/
        `delete_field()`, `search()`'s no-record_id guard + filter-key prefixing + order/sort
        SQL-safety + DateTime round-trip, `needs_diff()` full matrix, `get_rows()` basic shape +
        private-CF filtering. Deferred: `get_rows()`'s filemanager-merge branch (too much VFS
        setup), `share_email`/sharing-integration in `add()`.
      - `api/tests/Storage/TrackingBehaviorTest.php` + extended `TestTracking.php` (commit
        `6952bbfffc`, 18 tests) - `changed_fields()` full matrix, `track()`'s new-entry-never-writes-
        history contract (confirmed true) + `$skip_notification`, `do_notifications()` orchestration
        (self-suppression, dedup, `assignment_changed`, copy-address filtering) via a
        `TestTrackingNotifyRecorder` mock. Deferred: group-member expansion, `$check2pref`/
        `'assignment'`-only preference paths (needs real preference-row fixtures on a shared DB,
        judged too risky for this pass), the missing-try/finally regression test (item 9).
      - **Found, not fixed**: `History::needs_diff()`'s PGP guard (`strpos($value, BEGIN_PGP) == 0`)
        uses loose comparison, and `strpos()` returns `false` on no-match - `false == 0` is `true`
        in PHP, so "starts with the PGP marker" and "PGP marker absent entirely" both pass the
        check. Locked down via test, not corrected.
      - **Testing-infrastructure gotchas worth remembering** (not Tracking.php bugs):
        `do_notifications()`'s self-notify suppression reads `$this->user`, only ever set by
        `track()` - a direct `do_notifications()` call must set it manually. Real accounts on this
        shared dev box commonly have no email configured - pick an account with a real email for
        notification tests, don't grab an arbitrary "other" account.
- [x] **Phase 3 - `Customfields.php` gaps**. Extended `api/tests/Storage/CustomfieldsTest.php`
      (32 tests total now, all green - 12 marked "risky" by PHPUnit, all pre-existing
      `format()`-side-effect noise, not a real problem, see below). Two parallel agents landed on
      the same file concurrently and had to reconcile - handled cleanly, see commits
      `70c0b85cc8` and `0b9699eff7`.
      - `format()` - all type branches (`select-account` incl. multi-value, `checkbox`, `select`/
        `radio` incl. unknown-value fallback and `values['@']` file-reference reuse, `date`/
        `date-time`, `htmlarea`, link-type default branch via `Api\Link::title()`).
      - `get_options_from_file()` remaining branches (wrong extension, malformed JSON,
        `header.inc.php` guard).
      - `get_link_types()`, `get_account_cfs()`, `get_email_cfs()`.
      - `update()`'s `cf_order` renumbering-by-10s logic.
      - `save()`'s deletion semantics (a CF omitted from the array gets deleted).
      - `getSerial()` (increment, zero-padding preservation, `Api\Db\Exception` on missing row) -
        note the assertion was hardened to check a *relative* `+1` increment rather than hardcoded
        absolute values, after one transient flake under concurrent shared-DB test activity
        (an `Api\Cache` race, not a `getSerial()` bug - see commit `0b9699eff7`).
      - `update_links()` (create-on-set, remove-on-change-away).
      - Deferred: `handle_files()`/`handle_file()` (VFS side-effects, judged too much fixture setup
        for this pass).
      - **Found AND FIXED** (unlike the other findings in this doc, which are documented but left
        alone): `getSerial()`'s "no matching row" error path called `transaction_abort()`
        (ADOdb `FailTrans()` - only flags the transaction to fail) and threw, without ever calling
        `transaction_commit()` (ADOdb `CompleteTrans()` - the only thing that actually issues
        COMMIT/ROLLBACK). This left the transaction open indefinitely on the shared connection.
        Latent until this test suite existed - nothing had ever exercised this error path before.
        Landed on the shared dev box's origin/master by another concurrent session's push, it
        broke CI for real: the leaked transaction on the long-lived PHPUnit CI process cascaded
        into "Lock wait timeout exceeded" across ~57 unrelated later tests (two full CI runs,
        IDs `33728241471`/`33728610034`, both red with identical 33-errors/24-failures signatures).
        Fixed in commit `6ddde910bb` (add the missing `transaction_commit()` call, matching the
        working `transaction_abort()`-then-`transaction_commit()` idiom already used correctly
        elsewhere, e.g. `setup/admin_account.php`). Verified via a live `information_schema.
        innodb_trx` check that no transaction remains open on the connection after the fix.
      - **Found, not fixed**: (1) `format()`'s `htmlarea` branch only converts *opening*
        `<br>`/`<p>` tags to CRLF - the regex never matches a leading `/`, so closing tags get
        silently dropped by the trailing `strip_tags()` with no separator (consecutive
        `<p>A</p><p>B</p>` blocks get one CRLF between them, not two). (2)
        `get_options_from_file()`'s explicit `"header.inc.php"` basename check is dead code in
        practice - any real `header.inc.php` already fails the preceding `.json`-extension check
        first. (3) `format()` unconditionally calls `restore_error_handler()` with no matching
        `set_error_handler()` anywhere in the method - a real global-state side effect, correctly
        flagged "risky" by PHPUnit on every test that calls it; left as-is/documented rather than
        worked around, since suppressing PHPUnit's risky-detection would hide the same real issue
        from future callers.
- [x] **Phase 4 - `Merge.php` gaps**. 44 new tests landed across two commits (three parallel agents
      touched `MergeTest.php`/`TestMerge.php` this phase - all reconciled cleanly, no lost work; see
      the collision notes below).
      - `31b583c737` - `$$IF field~compare~then~else$$` (match/no-match/`EMPTY`/bare-placeholder
        args), `$$NELF$$`/`$$NENVLF$$`, `$$LETTERPREFIX$$`/`$$LETTERPREFIXCUSTOM$$`, `$$pagerepeat$$`
        with 2+ ids, `Merge::number_format()`, `/date$$`/`/time$$` auto-placeholders,
        `is_implemented()`'s full mimetype/extension matrix.
      - `8f4babf8dc` - `get_app()`/`get_app_class()`/`get_app_replacements()`, `contact_replacements()`,
        `cf_link_to_expand()` (`select-account` single + multi-value), `get_links()` (via reflection,
        real `Api\Link` fixture), `replace()`'s YAML `$$name/regex/replacement$$` transform.
      - **Found and fixed a real latent bug** (not just documented - this one blocked the tests
        themselves from running at all): `TestMerge`'s fixture had a `private $replacements`
        property that shadowed `Merge.php`'s same-named *dynamic* property (`Merge.php` never
        declares `$replacements` as a real class property, only sets it transiently inside
        `process_commands()`) - `Merge`'s own methods couldn't write to it, throwing "Cannot access
        private property," but only once a test actually exercised `$$IF`/`$$NELF`/`$$NENVLF`/
        `$$LETTERPREFIXCUSTOM$$` (no prior test did). Renamed the fixture's field to
        `$testReplacements`.
      - **Found, not forced**: `$$pagerepeat$$`'s "missing tag" error path is unreachable for
        `text/plain` specifically - an early performance short-circuit returns unconverted content
        with no placeholders at all, and `text/plain`'s own "nohead" auto-detection silently treats
        any placeholder-bearing template as an implicit repeat block instead of erroring. Documented,
        not faked with an artificial test.
      - Deferred: `:decimals,thousands$$`/`:FORMAT$$` numeric/date placeholder variants (only run
        inside `replace()`'s `$is_xml`-gated block - unreachable from `text/plain`, this file's
        convention), `get_all_links()` (too many Stylite-conditional branches for this pass),
        `merge()`/`merge_file()`/`download()` against real `.odt`/`.docx` fixtures (separate,
        lower-priority follow-up phase - not attempted).
      - **Collision notes**: three agents worked this file/pair concurrently at different points.
        One extracted only its own already-green additions into a standalone commit and restored
        the other's in-progress work byte-for-byte rather than risk landing broken shared code or
        losing anything (`8f4babf8dc`); the last one merged cleanly on top and verified via
        `git status`/`git log` before committing (`31b583c737`). No work was lost across either
        collision.
- [x] **Phase 5**. `egw_test` fixture extended again (`t_json` text column, test app v17.1.002 ->
      17.1.003, commit `25f70f4996`). 30 new tests landed across three files, all green:
      - `api/tests/Storage/JsonTest.php` (commit `469b53910f`, 16 tests) - `data2db()`/`db2data()`
        round-trip incl. `column_preg` filtering and existing-key-wins merge order, magic accessors,
        a real live `save()`/`read()` round trip through `Json` against `t_json` (verified via a raw
        SQL row check too, not just `read()`), `JsonCF` composing `JsonTrait` correctly through the
        `Base`->`Api\Storage`/`Customfields` hierarchy via reflection (a full `JsonCF` live round
        trip is deferred - needs a customfields extra_table fixture the `test` app doesn't have).
        No new bugs found - `Json`/`JsonCF`/`JsonTrait` behave exactly as documented.
      - `api/tests/Storage/Db2DataIteratorTest.php` + `RowsIteratorTest.php` (commit `a7fd4b48bd`,
        14 tests) - full iteration correctness, `total` exposure, `$rs=null` safety,
        `IteratorAggregate` unwrapping, the already-known `data2db`-not-`db2data` discrepancy locked
        down as a regression test (not fixed); `RowsIterator`'s full chunk-boundary/rewind/
        key-column/non-row-stripping/constructor-throw matrix.
      - **Found, not fixed**: `RowsIterator::next()` increments `$this->start` by `CHUNK_SIZE`
        immediately after *every* successful `get_rows()` call, including the first - so `key()`'s
        value for page 1's first row is already `500`, not `0`, one `CHUNK_SIZE` ahead of what the
        class docblock implies. No confirmed caller anywhere in this checkout, so left as
        documented-not-changed behavior per this project's established practice for unconfirmed-
        usage classes.
      - Two more near-miss shared-checkout collisions this phase, both caught and recovered cleanly
        the same way as earlier phases (verified via `git show --stat` after each commit) - no work
        lost, either the agents' own or other concurrent sessions'.
