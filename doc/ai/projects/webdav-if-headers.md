# WebDAV If-header conditions project

## Origin

Started while auditing `WebDAV/Server.php`'s request-header handling as a follow-up to an
unrelated, already-fixed security advisory in `Sharing.php` (see that file's own commit history -
not detailed here). That audit turned up that the `If:` header's per-resource condition checking
(`_check_uri_condition()`, RFC 4918 §10.4) was a stub that unconditionally returned `true` for
every ETag/lock-token condition - never actually implemented, for any WebDAV consumer in this
codebase. That became this project's scope.

## Architecture

- `HTTP_WebDAV_Server` (`api/src/WebDAV/Server.php`, global namespace, ported from PEAR's
  `HTTP/WebDAV/Server`) - base class, owns `_if_header_lexer()`/`_if_header_parser()` (tokenizes
  the raw `If:` header) and `_check_if_header_conditions()` (the single call site, runs
  unconditionally for any request carrying an `If:` header, before method dispatch).
- Two independent subclass trees consume it:
  - `EGroupware\Api\Vfs\WebDAV` (`api/src/Vfs/WebDAV.php`) → `HTTP_WebDAV_Server_Filesystem` →
    `HTTP_WebDAV_Server`. Used by `webdav.php` and shares (`Sharing.php`).
  - `EGroupware\Api\CalDAV` (`api/src/CalDAV.php`) → `HTTP_WebDAV_Server` directly. Used by
    CalDAV/CardDAV/GroupDAV.
- `checkLock($path)` was **already** correctly overridden in both trees before this project
  (`Vfs\WebDAV::checkLock()` → `Vfs::checkLock($path)`; `CalDAV::checkLock()` → parses the path
  into `(app, id)` via `_parse_path()`, then `Vfs::checkLock(Vfs::app_entry_lock_path($app, $id))`)
  - used for LOCK/UNLOCK responses and the separate, already-working `_check_lock_status()` write
    gate. This is why lock-token conditions turned out to be free for both trees once
    `_check_uri_condition()` did real work.
- ETags are **not** unified across the two trees: `Vfs\WebDAV` computes one from `stat()`
  (ino:mtime:size) for PROPFIND's `getetag` property; `CalDAV\Handler::get_etag()` is a completely
  separate, DB/entry-based mechanism feeding CalDAV's own, already-correct RFC 7232
  `If-Match`/`If-None-Match` handling. Confirmed via grep that nothing anywhere overrode
  `_check_uri_condition()` before this project - the RFC 4918-style `If:` header condition
  checking was genuine dead code for both trees.
- `_check_lock_status($path)` (gates writes to locked resources via a raw `HTTP_IF` substring
  match) is a **separate**, already-correct mechanism, untouched by this whole project - so none
  of this was a security hole, only a compliance/correctness gap (a conditional request using
  `If:` conditions could get the wrong 412-vs-200 answer, not bypass a lock).

## Phase 1 - real condition checking for VFS/shares

`_check_uri_condition($uri, $condition)`: resolves the (possibly Tagged-list) resource-URI to a
local path via new `_resolve_if_header_uri()` (mirrors `_copymove()`'s `Destination`-resolution
logic - host/SCRIPT_NAME/traversal checks - kept as a deliberately separate copy, not shared, to
avoid touching the already-reviewed COPY/MOVE path), then delegates to `$this->checkLock($path)`
for a state-token condition or `$this->currentEtag($path)` (new method, added to `Vfs\WebDAV` only,
shared with PROPFIND's `getetag` computation so the two can't drift apart) for an ETag condition.

Also fixed a latent, previously-harmless bug in `_if_header_parser()`: the ETag condition branches
had a stray trailing `>` (copy-pasted from the URI branch), never noticed because nothing had ever
parsed the condition strings back apart before `_check_uri_condition()` existed.

Pushed master `0fdbce6766`, backported 26 `e350a9e370`.

## Phase 2 - CalDAV/CardDAV

Original plan: reuse the lock-token half "for free" (already-correct `checkLock()` override),
explicitly skip adding ETag support (redundant with CalDAV's own `If-Match`/`If-None-Match`).

What actually shipping Phase 1 revealed: `_check_uri_condition()` lives in the **shared base
class**, so it started running for CalDAV/CardDAV requests the moment Phase 1 merged - lock-token
conditions did just work, but ETag conditions silently flipped from always-satisfied (the old
stub) to always-failing (`412`), since `currentEtag()` was never added there. A real, live
regression for one release cycle before being caught and fixed in the same session.

Fix: `_check_uri_condition()`'s ETag branch now returns `true` immediately (bypassing negation -
important, since routing a not-applicable condition through the shared `$not ? !$met : $met` flip
would turn `Not [...]` false) when `!method_exists($this, "currentEtag")`, restoring the old no-op
for any subclass that doesn't implement it.

Pushed master `82fe5a518f`, backported 26 `e5fdbf3dd6`.

## Status

**DONE.** Both phases shipped to master and 26.

Coverage: `api/tests/Vfs/IfHeaderConditionTest.php` - 18 tests (2 environment-skipped: this dev
container's `/` mount is a real, currently-write-broken S3 backend, see
[[project_vfs_test_coverage]]), covering:
- `StubbedWebDAV` (extends `Vfs\WebDAV`, `checkLock()`/`currentEtag()` overridden with known
  values): ETag match/mismatch/weak/negation, lock-token match/wrong/unlocked, Tagged-list
  resource-scoping, untagged-applies-to-current-resource, foreign-host and traversal URI
  rejection (real `WebDAV`, not stubbed - resolution failure happens before `checkLock()`/
  `currentEtag()` would ever be called).
- `StubbedNoEtagWebDAV` (extends bare `HTTP_WebDAV_Server`, only `checkLock()` implemented, no
  `currentEtag()` at all - the actual CalDAV/CardDAV shape): lock-token match/wrong still work,
  ETag condition (plain and negated) treated as satisfied - this is the Phase 2 regression guard.
- Real-file-based (skipped in this environment): actual `stat()`-based ETag and a real `Vfs::lock()`
  token, end to end.

Verification method used throughout: revert-test each diff (stash source, confirm the
intentionally-broken assertions - and only those - fail; restore) rather than trusting green alone.

Regression test note: `opaquelocktoken:` conditions must use the RFC 2518 §6.4 UUID shape
(`xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`) - `_check_if_header_conditions()` has a pre-existing,
unrelated format check (litmus-tested) that 423s anything else before it ever reaches
`_check_uri_condition()`; caught this the hard way debugging an initially-failing test that used
an arbitrary stub token string.

No further phases planned.
