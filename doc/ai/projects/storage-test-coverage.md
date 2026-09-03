# Api\Storage test coverage

Goal: map the current behavior of `api/src/Storage.php` and every class under `api/src/Storage/`
(the generalized SQL storage engine most apps' `bo`/`so` classes are built on), and build a full
test suite for it. Started because the area had no test coverage at all... except it did, mostly
undocumented as a single body of work. This doc is that missing map, plus the phased plan to close
the real gaps. Comparable in scope to
[accounts-import-test-coverage.md](accounts-import-test-coverage.md) - expect this to span more
than one session.

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
  (needs `EGW_ADMIN_PASSWORD` for its admin-fixture setup - not runnable on a box without that env
  var, unrelated to this project).

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

### Zero-usage classes - confirm scope before investing heavily

Grepped the whole repo (excluding `vendor/`): **`Db2DataIterator`, `RowsIterator`, `Json`,
`JsonCF`, and `JsonTrait` have no call sites anywhere in this checkout.** Either EPL/Stylite-only
(the usual blind spot - see the `[[epl_stylite_blind_spot]]` memory) or genuinely-unused
infrastructure. Cheap to unit-test (no DB needed for most of it), so still worth doing, but lower
priority than anything above, and not worth a live-DB round-trip test (would require adding a JSON
column to the `test` fixture for zero real-world payoff).

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
- [ ] **Phase 2 - `History.php` + `Tracking.php`**: `changed_fields()`, notification dispatch via
      mock, `History::search()`/`add()`/`delete()`/`needs_diff()`, `track()` contract.
- [ ] **Phase 3 - `Customfields.php` gaps**: `format()`, reordering logic, `save()` deletion
      semantics, `getSerial()`.
- [ ] **Phase 4 - `Merge.php` gaps**: command placeholders (IF/NELF/LETTERPREFIX/pagerepeat),
      number/date formatting, `is_implemented()`, `contact_replacements()`, `replace()`'s plain +
      YAML paths. Document `merge()`/`merge_file()` (real office documents) as a separate,
      lower-priority follow-up phase.
- [ ] **Phase 5 - zero-usage classes**: `JsonTrait` pure-array tests, `Db2DataIterator` (incl. the
      documented data2db/db2data question), `RowsIterator`.
