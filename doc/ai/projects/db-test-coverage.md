# Api\Db test coverage

Goal: build a real test suite for `api/src/Db.php` (2402 lines, 52 methods) - the core database
abstraction class everything else in EGroupware depends on. Follow-on to
[storage-test-coverage.md](storage-test-coverage.md) (read that first for conventions/methodology
reused here: phased mapping-then-implementation, "found and documented but not silently fixed"
discipline unless something is actively breaking things, shared-dev-database cleanup discipline,
and the `docker exec -e EGW_ADMIN_PASSWORD=...` correction near its top if any test here needs admin
rights - most won't).

## Starting state

`api/tests/Db/SchemaTest.php` (435 lines, 12 tests) covers `Db\Schema` (table/column/index DDL) -
solid, not revisited here. The core `Db` class itself has **zero dedicated test file**. A large
amount of indirect coverage already exists via the just-finished `api/tests/Storage/*.php` and
`invoices/tests/*.php` suites (both use real `Api\Db` connections heavily through `Storage\Base`) -
this doc's gap list accounts for that and focuses on what's genuinely undertested even so, especially
at the low level (`Db::quote()`'s exact matrix in isolation, not just "some `save()` calls happened
to quote things correctly").

## Two real bugs found during mapping - not yet fixed, flagged for a decision

1. **`_connect()`'s capability-reuse gap** (`Db.php:452-623`). Confirmed still live by re-reading the
   current code. `_connect()` only calls `set_capabilities()` (line 555) when opening a genuinely NEW
   ADOdb connection (`!isset(self::$ADOdb)` or differing connection params); when reusing the pooled
   `self::$ADOdb` link (line 596, `$this->Link_ID = self::$ADOdb;`), `set_capabilities()` is never
   called on `$this` - `$this->capabilities` stays at the class-default array (including the
   MySQL-wrong `CAPABILITY_CAST_AS_VARCHAR => 'CAST(%s AS varchar)'` instead of the MySQL-corrected
   `'CAST(%s AS char)'`). Already forced a real workaround this session in
   `api/tests/Storage/SearchTest.php`. Reachable any time a second `new Db(...)` is constructed
   against the same connection params within one process.
2. **`Db\Schema::RefreshTable()`'s transaction leak** (`api/src/Db/Schema.php:668-793`) - the SAME bug
   class, same mechanism, as the already-fixed, already-CI-breaking
   `Customfields::getSerial()` bug (see storage-test-coverage.md Phase 3). Its `!$Ok` failure path
   (line 787-791) calls `transaction_abort()` (ADOdb `FailTrans()` - only flags failure) then
   `return False;` **without ever calling `transaction_commit()`** to finalize/rollback via
   `CompleteTrans()`. Leaves the transaction open on `$GLOBALS['egw_setup']->db` indefinitely.
   Reachable during any schema upgrade that hits `RefreshTable()` (column/index/PK changes) and fails
   partway through (rename/create/copy/drop). Only one call site checked so far - a broader grep for
   the same `transaction_abort()`-without-following-`transaction_commit()` pattern across the repo is
   worth doing before considering this exhaustively found.

Both are real, concrete, reproducible - not speculative. Given #2 is the identical bug class that
already broke CI once this session (see `5c4a592de8`), the same fix (add the missing
`transaction_commit()` call) is the obvious remedy. #1 needs a judgment call: fix `_connect()` first
(e.g. call `set_capabilities()` unconditionally, or copy `$GLOBALS['egw']->db->capabilities` in the
reuse branch), or write a regression test documenting current behavior first.

## Per-group inventory and gaps

### `quote()` (1581-1678) - HIGHEST PRIORITY, zero direct coverage

The last line of defense against SQL injection for every value this class writes. Full `$type`
dispatch: `int`/`auto` (int-cast, safe by construction; `DateTime` objects special-cased via
`DateTime::user2server()`), `bool` (mysql: 1/0, else true/false), `float`/`decimal`, `vector`
(json_encode+`qStr()` or `bin2hex()`), `blob` (`BlobEncode()`), `date`/`timestamp` (via `qstr()`),
default/string path relies entirely on ADOdb's `Link_ID->qstr((string)$value)`. Indirectly exercised
only for common string/int/timestamp paths via `Storage\Base` saves - never directly, never
adversarially.

Untested, in priority order:

1. **Injection-scenario matrix** (mirroring `SanitizeOrderByTest.php`'s style): values containing the
   DB's own quote-escape sequences doubled/tripled, SQL comment sequences (`--`, `/*`), NUL bytes, a
   value that IS the `$glue` string (for the array/`implode($glue,...)` path). `quote()`'s safety
   currently rests entirely on trusting ADOdb's `qstr()`, untested from this class's own suite.
2. **The `$not_null=false && is_null($value)` → literal `'NULL'`** vs. the default `$not_null=true` +
   null value falling through to `(string)null` = `''` - a real, easy-to-misuse footgun: a PHP `null`
   silently becomes an empty string, not SQL `NULL`, unless the caller explicitly passes
   `$not_null=false`.
3. **`$length` truncation via `mb_substr()`** - needs a multi-byte-boundary test (truncating
   mid-multibyte-char must not corrupt encoding).
4. **4-byte-UTF8 (astral plane, real emoji) → U+FFFD replacement-char substitution** for
   MySQL/MariaDB - untested, easy to verify with a real emoji string.
5. **Array value → `implode($glue, $value)`** before falling through to string-quoting - untested
   directly (only exercised via `column_data_implode()`'s own, different array-handling path).
6. A generic (non-`DateTime`) object reaching the final `(string)$value` cast - invokes
   `__toString()`; untested whether this degrades gracefully or produces `"Array"`-style garbage for
   a non-stringable object.

### `name_quote()` (1524-1565) - untested, real sharp edge

Identifies "SQL expression" values (containing any space, or starting with `CASE `) and returns them
**unquoted, as-is** - a deliberate escape hatch, but means any key/column-name containing a space
bypasses identifier-quoting entirely. Column names are normally trusted (from app code, not raw user
input), but `column_data_implode()` calls `name_quote($key)` on every array key it's given - worth a
direct test locking down the space-bypass behavior as a documented contract. Also untested: dotted
`table.column` per-segment quoting, the always-quote-for-postgres-uppercase / literal `'index'`
keyword special cases.

### `insert()`/`update()`/`delete()` (1986-2231)

- **`insert()`'s `$where`-driven branch** (1994-2019): MySQL uses `REPLACE` if `$where` matches the
  full PK or a unique key, else does a `SELECT COUNT(*)` existence-check then delegates to `update()`.
  Genuinely complex, not directly tested - `Storage\Base::save()` mostly hits the simple
  unconditional-insert path, not this decision tree. High-value gap.
- **Multi-row bulk insert** (`$data[0]` is array → `INSERT INTO ... VALUES (...),(...),...`) - genuine
  gap, nothing in `Storage\Base` exercises this.
- `delete()`'s `$limit` param (MySQL-specific `LIMIT N` on DELETE) - testable on this box, worth one
  test, flag as DB-specific/not-Postgres-portable.
- `log_updates` as an array-of-table-names (vs. bare `true`) - opt-in per-table audit logging;
  untested that logging fires only for matching tables and correctly restores the temporarily-swapped
  value afterward. Moderate value/complexity (needs a scratch log file).
- **Skip/environment-gate**: `$use_prepared_statement`'s `_bindInputArray` branch and `update()`'s
  `sapdb`/`maxdb` blob-column-exclusion branch - both MaxDB-specific, unreachable/untestable in this
  MySQL dev environment.

### `select()`/`union()` (2305-2383)

`select()` is **already thoroughly covered indirectly** via `Storage\Base::search()`/`SearchTest.php`
- no need to re-test the common path directly.

`union()` is a genuine, cheap, high-value gap - the Storage project explicitly deferred UNION coverage
*through `Storage\Base::search()`* as "stateful static vars, higher setup cost", but a **direct
`Db::union()` call bypassing `Storage\Base` entirely** sidesteps that complexity completely:

- Single-select input: doesn't wrap in `UNION` at all - does string surgery instead
  (`'SELECT DISTINCT'.substr($union[0],6)`, stripping the literal `SELECT` and re-prefixing). Fragile
  by construction (any change to `select()`'s SQL-string format could silently break this offset
  assumption), zero test coverage.
- Multi-select input: parenthesized `(...)\nUNION\n(...)` join, plus `$order_by` appending with an
  already-has-"ORDER BY"-prefix skip check.
- Cross-cutting with `query()`'s readonly-mode regex guard (`/^\(?(SELECT|SET|SHOW)/i`) - the leading
  `\(?` exists specifically to accommodate `union()`'s parenthesized multi-select output; worth
  confirming both shapes are correctly allowed through.

### `expression()` (2251-2281) - genuinely distinct from `Storage\Base::parse_search()`, untested directly

A separate, lower-level variadic WHERE-fragment builder: interleaves raw string literals, arrays
(AND'ed via `column_data_implode()`), and a boolean/null "skip the next 2 arguments" gate
(`$ignore_next`). Non-obvious calling convention, worth testing directly. Confirmed as a real,
directly-used API surface this session - `Api\Mail\Account::search()`/`identities()` call
`$db->expression()` directly, not just internally via `insert`/`update`/`delete`/`select`.

### `column_data_implode()` (1702-1791) - shared engine under every write/where-clause

Heavily indirectly exercised, but several untested standalone branches:

- The `$or_null` handling: an array value containing a literal `null` member (e.g.
  `['col' => [1, 2, null]]`) produces `(col IN (1,2) OR col IS NULL)` - genuinely tricky, valuable,
  currently untested.
- `$only`'s three modes (unset / array-allowlist / `True`-means-"any column present in
  `$column_definitions`") - untested as a matrix.
- Single-item-array-unwraps-to-scalar optimization (`count($data) <= 1`).
- The integer-key raw-SQL-fragment passthrough (`is_int($key) && $use_key===True`) - confirm whether
  this is the SAME mechanism `Storage\Base::update()`'s raw-SQL-fragment feature (already tested,
  `SaveDeleteTest::testUpdateWithRawSqlFragment`) delegates to, or a distinct code path needing its
  own direct test.
- The "nothing known about column '$key'!" `InvalidSql`-throwing guard for an unrecognized key -
  untested, cheap, a real safety net against typo'd/malicious column names.

### `set_column_definitions()`/`set_app()`/`get_table_definitions()` (1802-1939)

- **`set_app()`'s global-db-protection guard**: throws `WrongParameter` if called on
  `$GLOBALS['egw']->db` itself for any app other than `'api'` - a real safety mechanism. Untested,
  cheap, valuable.
- **`get_table_definitions()`'s process-wide static cache** (`self::$all_app_data`, keyed by `$app`) -
  constantly exercised indirectly, but never explicitly tested for cross-app-cache-leak risk (same
  family already flagged multiple times in the Storage project: `query_list()`, `Api\Cache`-backed
  customfields, `Merge`'s export-limit caches). Worth one direct test proving app A's table defs don't
  leak into app B's lookup in the same process. Also untested: the `$app === true` "scan every
  directory under `EGW_INCLUDE_ROOT`" fallback branch (expensive, first-call-only - confirm subsequent
  calls hit cache, not re-scan).

### `query()`/`limit_query()` (774-900)

- **`readonly` mode**: `$this->readonly = true` + a non-SELECT/SET/SHOW query → returns `0`, sets
  `Error`/`Errno`, **without executing** - a real safety feature, untested, high value.
- `$Query_String == ''` → returns `0` immediately, no DB round-trip.
- `InvalidSql` vs. generic `Db\Exception` discrimination by ADOdb/mysqli error code (1064/1062/1054 vs
  everything else) - cheap and safe to trigger via a deliberately malformed query, confirms
  `$e->details` carries the original SQL text for logging.
- `log_updates === true` (global) debug-backtrace logging to `log_updates_to` - valuable, moderate
  complexity (scratch log file).
- **Judgment call, likely skip**: the `$reconnect=true` retry-once-on-"server has gone away" path -
  genuinely hard to test safely without killing a live connection mid-test on a shared dev DB.

### DB-portability abstraction functions (`concat`, `group_concat`, `regexp_replace`, `strpos`,
`unix_timestamp`, `from_unixtime`, `date_format`, `to_double`, `to_int`, `to_varchar`) - bigger
opportunity than expected

**Key finding**: `concat()` is the ONLY one that needs a live connection (delegates to
`$this->Link_ID->concat()`). Every other one is a **pure string-builder that only switches on
`$this->Type`** (and, for `group_concat()`/`regexp_replace()`, `$this->ServerInfo['version']`), with
**no internal `connect()` call at all**. This means every branch (mysql/postgres/mssql) of every one
of these is fully unit-testable without any live DB connection whatsoever - construct a bare
`new Api\Db()`, set `->Type = 'pgsql'`/`'mssql'`/`'mysqli'` (and `->ServerInfo` where needed), call the
method, assert on the returned SQL string. **Highest value-to-cost ratio found in this whole mapping
pass** - portability code is exactly where untested cross-engine bugs hide, and testing it costs
nothing (no fixture, no DB round-trip). `strpos()` has a `die()` for an unknown type - test only via a
data provider of known-good types, don't exercise the `die()` path directly.

### Introspection (`get_last_insert_id`, `affected_rows`, `metadata`, `table_names`, `index_names`,
`pkey_columns`)

Almost entirely indirectly covered already (every `Storage\Base` construction reads table metadata).
`affected_rows()` is called by production code but nothing currently asserts on it explicitly - a
cheap, direct test (update N rows, assert `affected_rows()===N`) adds real value.

Two minor code-smell quirks worth locking down as characterization tests (not fixing): (1)
`get_last_insert_id()` `echo`s an HTML "not yet implemented" message on the false path instead of
throwing/logging; (2) `index_names()` is **only implemented for postgres** - for mysql (the DB type
actually in use here) it unconditionally `echo`s "not yet implemented" and returns `[]`.

### Transactions & locking

`Db`'s own `transaction_begin()`/`_commit()`/`_abort()`/`row_lock()`/`commit_lock()`/
`rollback_lock()` are correctly-paired thin wrappers over ADOdb - no bug in `Db.php` itself (the two
bugs found live in `Db\Schema` and `Storage\Customfields`, not here). Genuinely testable: begin+commit
persists, begin+abort+commit rolls back (per ADOdb's `FailTrans()`-then-`CompleteTrans()` semantics),
`row_lock()`/`commit_lock()`/`rollback_lock()` respect `self::$tablealiases`.

### Timezone/timestamp handling

`to_timestamp()`/`from_timestamp()` need a live connection - likely already indirectly exercised via
`Storage\Base`'s date handling, but a direct round-trip test is still cheap. `setTimeZone()` -
re-confirmed still dead code (zero callers anywhere in the repo). Not worth chasing given no caller
exists.

### Deliberately excluded

- `create_database()` - creates real databases/users via `CREATE DATABASE`/`CREATE USER`/`GRANT`, far
  too destructive/environment-invasive for a shared dev DB.
- `galera_cluster_health()` - needs a real Galera cluster.
- `__wakeup()` - needs a real PHP session + `$GLOBALS['egw_domain']`, low value (session-restore
  plumbing, not core DB logic).
- MaxDB/SapDB-specific branches throughout `insert()`/`update()` - unreachable on this MySQL box.
- `query()`'s connection-drop-and-retry scenario - too disruptive to a shared dev DB to safely trigger.

## Prioritized gap list (highest value first, combining both mapping passes)

1. **`quote()`'s injection/type matrix** - highest stakes, zero existing direct coverage.
2. **`readonly` mode write-blocking** in `query()` - cheap, safety-critical.
3. **DB-portability functions** (`date_format`/`unix_timestamp`/`from_unixtime`/`to_double`/`to_int`/
   `to_varchar`/`regexp_replace`/`group_concat`) across mysql/postgres/mssql - no live connection
   needed, highest value-to-cost ratio found.
4. **`insert()`'s REPLACE-vs-check-then-update branch** for MySQL unique-key `$where`.
5. **`Db::union()` directly** (bypassing `Storage\Base`) - both single- and multi-select branches.
6. **`column_data_implode()`'s `$or_null`/array-with-null-member IN-clause logic** and the
   unrecognized-column `InvalidSql` guard.
7. **`expression()`'s boolean-skip-next-2-args calling convention**.
8. **`set_app()`'s global-db-protection guard** and **`get_table_definitions()`'s cross-app cache
   isolation**.
9. **`name_quote()`'s space/`CASE`-bypass contract.**
10. Transaction begin/commit/abort pairing, multi-row bulk `insert()`, `delete()`'s `$limit`,
    `log_updates` (both per-table-array and global-bool forms), `affected_rows()`,
    `strip_array_keys()` - moderate value, cheap.
11. Lower priority: `to_timestamp()`/`from_timestamp()` round trip, `get_last_insert_id()`/
    `index_names()`'s echo-based quirks (real but minor).

## Phase plan / status

- [x] **Phase 0 - Mapping** (this doc). Two real bugs found (`_connect()`/`set_capabilities()` reuse
      gap; `Db\Schema::RefreshTable()`'s transaction leak, same class as the already-fixed
      `Customfields::getSerial()` bug) - flagged for a decision before Phase implementation proceeds.
- [x] **Phase 1 - `quote()`/`name_quote()` security matrix + `query()`'s `readonly` mode/error
      discrimination**. `api/tests/Db/QuoteTest.php` (commit `6ac8e38023`, 28 tests) - injection
      round-trip matrix (12 adversarial values), the null/`$not_null` footgun, `mb_substr()`
      character-boundary truncation, astral-plane->U+FFFD replacement, array+glue implode, object
      `__toString()` degradation, `name_quote()`'s space/`CASE`-prefix passthrough + reserved-word
      quoting, `query()`'s readonly mode, error classification. **No bugs found** in `quote()`/
      `name_quote()` - every adversarial value round-tripped byte-identical through a real query.
      **Real finding, documented not fixed**: in this actual runtime mysqli is NOT in
      exception-throwing mode, so `query()`'s code-based `InvalidSql`-vs-generic-`Db\Exception`
      classification is effectively dead code here - every failure (including an unclassified error
      code like 1146/unknown-table) falls through the unconditional `!$rs` fallback and comes back as
      `InvalidSql` regardless. Also `$e->details` is never populated via this path - the SQL text
      appears in `getMessage()` instead. Tests locked down to match actual behavior.
- [x] **Phase 2 - DB-portability functions** (no live connection needed - cheap, high value).
      `api/tests/Db/PortabilityTest.php` (commit `03244a91f9`, 42 tests) - `group_concat()`,
      `regexp_replace()`, `strpos()` (mysql/pgsql/mssql only, `die()` path on unknown type
      deliberately not exercised), `unix_timestamp()`/`from_unixtime()`, `date_format()` (incl. the
      mssql `DATEPART`-splicing logic and empty-segment cleanup for adjacent placeholders),
      `to_double()`/`to_int()`/`to_varchar()` across their engine branches. **Correction to the
      original mapping**: `group_concat()`/`regexp_replace()` aren't fully connection-free after all
      - both call `quote()` for a sub-value, which needs a live `Link_ID` for its string path -
      worked around with one real connected `Db` instance whose `->Type`/`->ServerInfo` get
      overridden per test case. **No bugs found** - one quirk documented (not a bug): `to_varchar()`
      has no mysql-specific branch, unlike `to_double()`/`to_int()`.
- [ ] **Phase 3 - `insert()`/`update()`/`delete()` edge cases + `column_data_implode()` +
      `expression()`**.
- [ ] **Phase 4 - `union()`, transactions/locking, `set_app()`/`get_table_definitions()` cache
      isolation, introspection (`affected_rows()` etc.), `strip_array_keys()`**.
