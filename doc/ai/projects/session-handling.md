# Session handling: non-blocking sessions + Redis Sentinel migration

## Status: Phase 1 done and pushed (2026-08-24/25); Phase 2 (Redis Sentinel session backend) fully designed, nothing implemented yet

Ralf asked to (1) stop PHP's default session-file locking from serializing concurrent AJAX
requests from the same browser tab, (2) understand why a 2022 attempt at this broke Collabora
editing, and (3) migrate SaaS hosting's session backend from 2x memcached to the new Redis
Sentinel cluster, to survive K8s node reboots and (if feasible) support partial-field writes
instead of full-document rewrites. Phase 1 covers (1)+(2) for the existing file/memcached-native
backends; Phase 2 covers (3), and turned out to unlock a cleaner solution to (1) as well, for
Redis-backed installs specifically.

## Phase 1 (done, commits `c5eb6b9cc1`, `0cec64f766`, `cc2a706581`, `7f428a4ea0`)

- `json.php` now closes the session right after `header.inc.php`'s bootstrap (`verify_session()`
  included) instead of holding it for the whole request - concurrent AJAX requests from one
  browser tab no longer serialize behind each other's full duration.
- Root cause of the 2022 Collabora breakage (`fe4d0dbbe3`, reverted 3 days later by
  `49ac54b365`): `Session::update_dla()` wrote the timeout timestamp raw into `$_SESSION`,
  bypassing that attempt's write-tracking buffer entirely, because `read_and_close` closed the
  session *before* `verify()`/`update_dla()` even ran - so the timeout stopped advancing and
  `verify()`'s own timeout check eventually killed the session mid-edit. Fixed this time by
  closing *after* full bootstrap completes instead of via `read_and_close` mode, so
  `update_dla()`'s write always happens on a normally-open session - no special-casing needed.
  A dropped `ini_set('session.use_cookies', 0)` (WOPI's cookie-less session reattachment) was
  the second, unrelated 2022 regression; already back in `Session::init_handler()` today, pinned
  by `SessionInitHandlerTest.php`.
- `Api\Cache::setSession()`/`getSession()`/`unsetSession()` buffer writes made while the session
  is closed (`Cache::$closed_session_writes`), replayed by `Cache::flush_session_writes()`
  (registered via `Egw::on_shutdown()`, runs in the pre-close pass right before the response is
  flushed to the client). The deprecated by-reference `getSession()` pattern still works via a
  snapshot-diff closure for third-party/uninverted call sites; new code should read once and
  call `setSession()` explicitly.
- `api/src/loader.php`'s session-cache write (`EGW_INFO_CACHE`/`EGW_OBJECT_CACHE`, previously
  unconditional on every request) is now change-tracked - skipped when nothing changed.
- 8 of the 10 in-repo deprecated-fallback `getSession()` call sites migrated to the explicit
  pattern (`calendar_timezones`, `Auth::check_password_change()`, `mail_zpush::$profileID`,
  `Mail\Imap::$supports_keywords`, `Mail\Imap\Jmap`'s 3 properties). `Accounts::$cache` stays on
  the fallback deliberately - see "Deferred: Accounts.php" below.
- Live-verified against a real Collabora WOPI round-trip and a reproducible concurrency test (5
  concurrent `api/avatar.php` requests racing a slow AJAX call): went from non-deterministically
  blocking (~400ms) to consistently <50ms every run.

### Deferred: `Accounts.php`

`Api\Accounts::$cache` was assessed and **deliberately left on the deprecated fallback** -
`self::$use_session_cache = ($backend !== 'sql')`, so the session-binding branch never engages
for the common SQL-backend case (confirmed: even the `ads.test` domain's `account_repository` is
`sql`, `auth_type=ads` only affects login authentication, not account storage/caching - so there
is no real backend available in this dev environment to test the risky branch against at all).
3 real mutation sites (`search()`, `name2id()`, `split_accounts()`) plus 2 reset sites need
handling, and one pre-existing dormant bug (`split_accounts()`'s `isset($cache)` check, which
PHP's reference-to-nonexistent-array-key auto-vivification makes false far more often than the
code seems to intend) would need to be preserved exactly, not "fixed" alongside the migration.
Given all new installs use periodic LDAP/AD->SQL import rather than a live LDAP/ADS/Univention
backend, ralf decided this isn't worth the risk for the payoff (2026-08-25).

## Phase 2: Redis Sentinel session backend - design complete, not implemented

### Why the backend needs custom code on **two** independent surfaces, not one

1. **The main app** (`Api\Session`) - currently 100% `php.ini` config
   (`session.save_handler=files`), zero EGroupware code wraps it.
2. **`swoolepush`** - `swoolepush/src/Session.php` is a small dispatcher
   (`swoolepush/src/Session/Backend.php` interface, `Files.php`/`Memcached.php` implementations)
   that validates a WebSocket handshake's `sessionid` cookie via a lightweight, no-EGroupware-
   bootstrap readonly check (`server.php`'s `'open'` handler: `new SwoolePush\Session($sessionid);
   $session->exists()`), purely from `session.save_handler`. It throws
   `RuntimeException("Not implemented session.save_handler=$handler!")` for anything it doesn't
   recognize - so switching the main app to `redis` would reject every push connection unless
   this dispatcher also learns a `Session\Redis` backend.

Both need Sentinel-aware master discovery and a Redis client; the design below is shared between
them.

### Redis Sentinel is not solvable with the native `session.save_handler=redis`

Confirmed via phpredis GitHub issues/discussions: the built-in redis session handler has **no
Sentinel failover support** - only a fixed host, or a static `failover=` peer list, neither of
which is Sentinel-based master discovery. phpredis does ship a `RedisSentinel` class (since
5.2.0) with `getMasterAddrByName()`/`slaves()`, which is the building block for hand-rolled
discovery - this is what the custom handler uses.

(The chart's dormant `haproxy.enabled: false` option - "Automatically proxies to Redis master" -
would let the *native* handler work unmodified by hiding Sentinel behind a stable endpoint. Not
chosen for the main app, since a custom handler is needed anyway for patch-writes; worth
remembering as a fallback if the custom handler ever proves troublesome.)

### Storage layout: one Redis **Hash** per session, not a JSON document

Key `sess:$sessionid`, one field per `"$app:$location"` (matching
`$_SESSION[EGW_APPSESSION_VAR][$app][$location]`), value = the same PHP-`serialize()`'d blob
already stored today. `Cache::setSession($app, $location, $data)` -> one `HSET` on that key.

Redis 8.8 (the production version, `redis:8.8.0-alpine` per `k8s-farm/stalwart/redis-values.yaml`)
ships the ReJSON module bundled (confirmed live: `MODULE LIST` shows `ReJSON` alongside
`search`/`timeseries`/`bf`/`vectorset` - Redis 8 merged these into core, no separate
"redis-stack" image needed), so a JSON-document design (`JSON.SET key '$.path' value` for
per-field patches) is genuinely available and was tested. **Decided against it**:
- `JSON.SET` on a nested path silently no-ops (`false`, no error) if the parent object doesn't
  exist yet - confirmed live. Since `Cache::setSession()` is called for arbitrary,
  dynamically-first-seen `$app` values, a nested `$.app.location` design would silently drop the
  very first write for any new app key. A flat top-level path (`$["app:location"]`) sidesteps
  this (also confirmed live: works first-try), but at that point it's not using any JSON-specific
  capability - just a JSON object as a hash with extra steps.
- PHP's `serialize()` output isn't guaranteed valid UTF-8 (arbitrary binary), and `json_encode()`
  fails outright on invalid UTF-8 - would need base64 (~33% size overhead) to store existing
  session values safely inside JSON strings. A plain Hash keeps values as opaque bytes, exactly
  like today, zero re-encoding risk.

A Hash gets identical per-field patch semantics with none of that risk. (Minor point in JSON's
favor: `JSON.GET` is more readable for manual ops/debugging than `HGETALL` of serialized blobs -
not enough to outweigh the above.)

### Reads and writes both go through the Sentinel-appointed master - no replica reads

The original research's "read from replicas" stretch goal is **not** part of this design.
Redis's replication is asynchronous, so a replica can lag behind the master by anywhere from
sub-millisecond to much more under load. That's a real correctness risk for this workload
specifically, not theoretical:
- `swoolepush`'s whole job is validating a session **immediately** after it was created (browser
  logs in, opens the WebSocket within moments) - a lagging-replica read would see "session
  doesn't exist" for a session that does, on the master.
- `login.php` already has an existing, deliberate fix for exactly this class of race:
  `login.php:305-306` calls `$GLOBALS['egw']->session->commit_session()` **before**
  `Egw::redirect_link()`, with the comment "committing the session, before redirecting might fix
  race-condition in session creation" - i.e. block until the write is confirmed, *then* respond,
  so the browser's next request (following the redirect) is guaranteed to read a backend that
  already has the data. This only continues to work if (a) the custom handler's `write()` is
  genuinely synchronous/blocking (waits for the master's ack, no pipelining-without-waiting, no
  deferred/background flush) and (b) the next read hits the *same* node the write went to - i.e.
  master-only reads. `register_session()` (login's session-creation code) writes straight to
  `$_SESSION`, not through `Cache::setSession()`, so it's unaffected by the real-time-write
  feature below either way - it's covered end-to-end by the one synchronous `write()` call
  `commit_session()` triggers, same as today.

Given Redis is nowhere near a throughput bottleneck for session traffic the way a full SQL query
load might be, there's little to gain from replica reads and a concrete correctness risk to
avoid. Revisit only with a specific design (e.g. `WAIT` after writes, or only routing
reads-known-to-be-old to replicas) if it's ever actually needed.

### No session locking needed - and why

PHP's native session lock exists to protect exactly one pattern: read the whole document, mutate
in memory, write the whole document back. Two concurrent requests each doing that race to
last-write-wins on the *entire* document, silently erasing each other's unrelated changes -
hence PHP serializing all requests sharing a session via an exclusive lock.

Once every write is a field-scoped `HSET`, that failure mode doesn't exist: two concurrent
requests patching different fields never touch each other's data, however much they overlap in
time. Two requests patching the *same* field just resolve to last-write-wins on that one field -
already how `session_dla` (touched on nearly every authenticated request) implicitly behaves
today, harmlessly. So the custom handler's `open()`/`close()` can be true no-ops with respect to
locking - there's nothing to protect.

Consequence: Phase 1's whole "close early, buffer writes outside `$_SESSION`, reopen and reapply
at shutdown" machinery exists specifically to survive the *native* lock. A Redis-backed session
using this handler never holds that lock in the first place, so it never needs the early-close
dance either. That machinery stays necessary for file-based (default/on-prem) installs - Redis-
backed SaaS installs get a more direct benefit than what Phase 1 built for everyone else.

### `write()`: diff-based, so "full write at creation, patches after" is emergent, not special-cased

The handler's `SessionHandlerInterface::write($id, $data)` (PHP's contract is always
whole-blob - there is no "write only this key" hook at that layer) diffs the incoming, freshly
re-serialized `$_SESSION` against the last snapshot it handed back (from `read()`, or its own
last `write()`), and only emits `HSET`/`HDEL` for the fields that actually differ. At session
creation there is no prior snapshot (`read()` returned nothing), so *everything* differs -
naturally producing a bulk write (one `hMSet()`/burst of `HSET`s) without any "am I creating or
updating?" branch in the code. Every write after that only patches what changed. This also means
Phase 1's write-tracking (`Cache::$closed_session_writes`, already tracking exactly which
`$app`/`$location` pairs were touched per request) composes for free with this handler - no
changes needed to `Cache.php` itself.

Because `write()` can be invoked more than once per request (Phase 1's early-close pattern can
trigger `session_write_close()` twice), the handler must update its own "last known state"
snapshot after *every* `write()`, not just diff against the original `read()`.

### Real-time writes (decided 2026-08-25): `Cache::setSession()` patches immediately, not at shutdown

Rather than batching every `Cache::setSession()` call until the end-of-request `write()` diff,
the handler exposes a `patch($app, $location, $data)` method that `Cache::setSession()` calls
directly (when it detects the active handler supports it), firing the `HSET` right away -
visible to other concurrent requests sooner, and safer against losing an update if the process
dies mid-request. `patch()` must also update the handler's own snapshot for that field, so the
end-of-request diff doesn't redundantly re-send it (harmless if it did - `HSET` is idempotent -
just wasted work).

Anything that mutates `$_SESSION` directly (bypassing `Cache::setSession()` - this does exist in
the codebase) is still only caught by the diff-based `write()` at end of request, same as before.
So there end up being two callers into the same underlying "patch this field" primitive: the
real-time one from `Cache::setSession()`, and the end-of-request diff as a catch-all.

### `swoolepush`'s `Session\Redis` backend

Implements the existing `Session\Backend` interface (`exists()`/`open()`), same shape as
`Session\Memcached`/`Session\Files`. Needs `RedisSentinel::getMasterAddrByName()` to find the
current master, then a plain `HGETALL sess:$id` + reconstruct (mirroring `session_decode()`'s
existing role in the other two backends). Should carry forward `Memcached.php`'s production
hardening: reconnect-on-failure path, and a "backend unreachable too long -> `exit(1)` to force a
K8s restart" tripwire (`Session\Memcached::BACKEND_DOWN_TIMEOUT`).

**Coroutine-safety, confirmed empirically against a local Redis (no production cluster touched):**
- `Swoole\Coroutine\Redis` (the old dedicated coroutine client class) is **not compiled into**
  the deployed swoole build (`egroupware-push`, swoole 6.2.2) - `class_exists` is false.
- The modern pattern, `Swoole\Runtime::enableCoroutine()`, transparently hooks plain phpredis
  calls to be non-blocking under Swoole's coroutine scheduler. Verified live: 10 concurrent
  coroutines each issuing a 1-second blocking `BLPOP` finished in ~1.03-1.08s total (not ~10s)
  under both `SWOOLE_HOOK_ALL` and the much narrower `SWOOLE_HOOK_TCP` alone. **`SWOOLE_HOOK_TCP`
  is sufficient** - use that, not `SWOOLE_HOOK_ALL`, to avoid affecting unrelated blocking I/O
  (file access, curl, etc.) elsewhere in `server.php`/`PushServer.php`. This is a new mechanism
  for this codebase (the existing `Memcached` backend manages its own async I/O via
  `easyswoole/memcache-pool` instead of runtime hooking) - needs its own call to
  `Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_TCP)` once at server bootstrap.

### Client library: phpredis (ext-redis), not Predis or Relay

- phpredis (C extension): ~2-6x faster than Predis for basic ops (multiple independent
  benchmarks), has `RedisSentinel` built in.
- Predis (pure PHP, no extension): only wins when extensions can't be installed at all - not our
  situation.
- Relay (newer C extension, phpredis-compatible, adds an in-memory cache layer + compression):
  the caching layer targets repeated reads of the same hot keys - doesn't clearly help session
  data, which is written far more than re-read by the same process. Extra operational surface
  (separate PECL extension, less ubiquitous) for speculative benefit on this workload - skip
  unless a real benchmark says otherwise.

**Deployment state, confirmed by checking both containers directly (2026-08-25):**
- `egroupware-push` already has `ext-redis` (phpredis 6.3.0) and `ext-swoole` (6.2.2) - nothing
  to install there.
- `egroupware` (main php-fpm container) does **not** have `ext-redis` - needs adding to
  `doc/docker/fpm/Dockerfile` before the main-app handler can be used.

### Local test environment (per ralf's explicit preference: local Redis, not the production cluster)

A `redis-research` container (`public.ecr.aws/docker/library/redis:8.8.0-alpine`, matching
production's exact image/tag, on the `dev_default` docker network) was used for all validation
above - hash-patch mechanics, the JSON auto-vivification footgun, and the Swoole coroutine-hook
test. No production cluster access was used. A local Sentinel setup (multi-node, to test the
`RedisSentinel` master-discovery/reconnect path for real) is deliberately deferred - "only if
really necessary" - until the single-instance mechanics above are solid.

### Production topology facts (from `/Users/ralf/k8s-farm/stalwart/redis-values.yaml`)

- Chart: `dandydev/redis-ha`, image `redis:8.8.0-alpine`, `replicas: 3`, `masterGroupName:
  mymaster`, Sentinel port `26379`, Redis port `6379`, Sentinel `quorum: 2`.
- `auth: false` today (no `requirepass`) - simplifies the client connection code for now, but
  don't hardcode that assumption; it may change.
- `haproxy.enabled: false` - the dormant "native handler could work unmodified" fallback
  mentioned above.

## Not yet done (tracked here, not silently skipped)

- No code written yet for either `Api\Session\Redis` (main app) or `SwoolePush\Session\Redis` -
  this doc is the design, implementation hasn't started.
- No local Sentinel test setup - single-instance-only validation so far, per ralf's preference.
- `RedisSentinel`-based master discovery/reconnect-on-failover has not been exercised at all yet
  (needs at least a local Sentinel setup to test meaningfully).
- Replica-read support: explicitly decided against for now (see above), not merely deferred by
  oversight.
- Whether other session-creation-adjacent flows (app-password/token login, OAuth, 2FA
  completion) have the same explicit "commit before responding" protection `login.php` does -
  not audited; `login.php`'s existing fix was found while investigating this specific question,
  not as part of a full sweep.
- HAProxy-in-front-of-Sentinel (native-handler-can-work-unmodified) alternative - not prototyped,
  still just a noted fallback option.

## Testing (once implementation starts)

- A regression test proving `commit_session()`'s synchronous-write guarantee holds: after
  `commit_session()` returns, a *fresh* connection/read (not relying on any in-process state)
  must see the just-written data - guards against the handler ever becoming accidentally
  async/deferred and silently reintroducing the `login.php` race this design relies on staying
  fixed.
- Hash-patch mutation tests mirroring the `getSession()`-migration tests already added this
  session (`AuthPasswordChangeSessionTest.php`, `TimezonesSessionCacheTest.php`,
  `ImapSupportsKeywordsSessionTest.php`, `JmapSessionPersistenceTest.php`) - assert a `patch()`
  call lands the right `HSET`, and that `write()`'s diff only touches fields that actually
  changed.
- `swoolepush` side: an integration-style test against a local Redis (real Hash reads, not
  mocked) proving `Session\Redis::exists()`/`open()` correctly reconstruct `$_SESSION` from the
  Hash.
