# WebDAV server test-coverage project

## Goal

Add real, HTTP-level test coverage for EGroupware's WebDAV server (`webdav.php`,
`filemanager` app), covering `PROPFIND`, `GET` (incl. byte-range requests), `PUT`
(incl. `Content-Range` chunked uploads) and `DELETE`. Follow-up to
[vfs-test-coverage.md](vfs-test-coverage.md), which covered the Vfs subsystem
itself in-process; this project covers the HTTP protocol layer sitting on top
of it, reusing the CalDAV/REST test infrastructure since all three go through
the same `HTTP\WebDAV_Server` machinery and real over-the-wire requests.

## Architecture mapping

- `webdav.php` - entry point, sets `currentapp` (`filemanager`, or `api` for
  `/etemplates/`, `/apps/<app>/`, `/home/<user>/.tmp/` paths), calls
  `Vfs\WebDAV::ServeRequest()`.
- `Vfs\WebDAV` (`api/src/Vfs/WebDAV.php`) extends `HTTP_WebDAV_Server_Filesystem`
  (`api/src/WebDAV/Server/Filesystem.php`) extends `HTTP_WebDAV_Server`
  (`api/src/WebDAV/Server.php`, the base HTTP method dispatcher, ported from
  PEAR's `HTTP/WebDAV/Server`, not sabre/dav). `$base = Vfs::PREFIX`
  (`vfs://default`), so `$options['path']` is a plain Vfs path (e.g.
  `/home/demo/file.txt`) and every method handler goes through `Vfs::*`.
- Per-method override chain (only the methods this project covers):
  - **PROPFIND**: `Server.php::http_PROPFIND()` parses the request →
    `Server/Filesystem.php::PROPFIND()` walks the filesystem → `Vfs\WebDAV::PROPFIND()`
    (line 380) adds egroupware-specific properties via `Vfs::propfind()`
    (customfields under `Vfs::DEFAULT_PROP_NAMESPACE` + `#` prefix) and ctime/mtime/atime.
  - **GET**: `Server.php::http_GET()` (line 1279) parses `Range:` header via
    `_get_ranges()` (single range, suffix range `-N`, and multi-range →
    `multipart/byteranges`) → `Server/Filesystem.php::GET()` opens the file
    stream → `Vfs\WebDAV::GET()` (line 682) adds `autoindex()` for directories
    and `Api\Header\Content::safe()` (XSS mitigation: forces safe content-type
    for js/css/html).
  - **PUT**: `Server.php::http_PUT()` (line 1714) parses `Content-Range:` header
    (single range only, `bytes start-end/total`) for chunked-upload support →
    `Server/Filesystem.php::PUT()` opens with `"c"` mode (not `"w"`, so a range
    PUT doesn't truncate) and fires the `vfs_pre-write` quota hook → base class
    writes the request body at the range's offset → `Vfs\WebDAV::PUT()` (line 912)
    only adds a `Transfer-Encoding: chunked` rejection for non-nginx FastCGI.
  - **DELETE**: `Server.php::http_DELETE()` → `Vfs\WebDAV::DELETE()` (line 81,
    fully reimplemented, not inherited from Filesystem.php) - recursive
    `Vfs::remove()` for directories (catches `Exception\ProtectedDirectory`),
    plain `unlink()` for files.
- Locking (`checkLock()` → `Vfs::checkLock()`) is a no-op unless a WebDAV lock
  actually exists; not exercised by this project (no LOCK/UNLOCK requests made).

## Why HTTP-level, not in-process (key difference from the Vfs project)

Unlike every `api/tests/Vfs/*` test, `webdav.php` runs as a **separate PHP
process** behind the real webserver - `CalDAVTest`/`RestBase`'s pattern of real
`GuzzleHttp\Client` requests against `http://localhost/egroupware/...` is the
only viable approach; nothing in the test process's PHP memory (like a
non-persistent scratch `Vfs::mount()`) is visible to that other process. So:

- **Reuse `CalDAVTest` wholesale** for the boilerplate that's identical
  (`getClient()`/`auth()`, `createUser()`/`createUsersACL()`/`addAcl()`,
  `assertHttpStatus()`, account cleanup in `tearDownAfterClass()`, the whole
  `setup`-bootstrap machinery) - same relationship `RestBase extends CalDAVTest`
  already has. Only `url()` differs (points at `webdav.php` instead of
  `groupdav.php`); everything else needing `$this->url()` internally
  (`putResource()`/`reportSyncCollection()`/etc., all CalDAV/CardDAV-specific)
  simply isn't called from WebDAV tests.
- **No scratch mounts.** Each test works under a freshly `createUser()`'d
  account's own `/home/<lid>/` - `setup::add_account()` already fires
  `Vfs\Hooks::addaccount` to create + `chown()` that dir, so it's writable
  out of the box and isolated per test class (cleanup piggybacks on the
  existing account-deletion teardown, same as CalDAV tests). Requires granting
  `filemanager` `run` ACL in addition to `CalDAVTest::createUser()`'s default
  grants (`groupdav`/`calendar`/`infolog`/`addressbook`).
- The dev environment's default `/` mount is `stylite.s3://` (see
  [[project_vfs_test_coverage]] finding #1) - `/home/<lid>/` therefore exercises
  the S3 pass-through backend transparently; that's fine (matches production
  shape) and not specifically targeted by this project.

## Phases

1. **Base infra**: `api/tests/WebDAVTest.php` extends `CalDAVTest`. Overrides
   `url()` (mirrors `getCaldavBaseUrl()`'s env-override pattern, `/webdav.php`
   suffix instead of `/groupdav.php`). Helpers: `webdavCollection($user)`,
   `putFile()`/`getFileResponse()`/`propfind()`/`deleteResource()` (path
   already inherited), a `Content-Range` chunked-PUT helper, and a
   `multistatusResponses()` XML parser (same `D:response`/`D:href`/`D:status`
   XPath shape already proven in `CalDAVTest::reportSyncCollection()`).
2. **PROPFIND**: Depth 0 (single resource) vs Depth 1 (collection listing);
   properties on a file vs a directory (`resourcetype`, `getcontentlength`,
   `getetag`, `getlastmodified`, `displayname`); 404 on a missing path;
   customfield/egroupware-specific property namespace round-trip.
3. **GET (+ range)**: full-file GET (status, `Content-Type`, `Content-Length`,
   body); directory GET (autoindex HTML, `Content-Type: text/html`); 404 on
   missing file; single byte-range (`Range: bytes=0-99`); suffix range
   (`bytes=-100`); combined with a large-enough fixture file to make ranges
   meaningful; 416 on an out-of-bounds range (if the server returns one -
   verify actual behavior rather than assuming, per this session's established
   habit of checking real behavior before writing the assertion).
4. **PUT (+ Content-Range chunked upload)**: create (201) vs overwrite (204);
   PUT to a path whose parent doesn't exist (409); PUT to an existing directory
   (403); a realistic chunked-upload sequence - multiple sequential `PUT`s each
   with `Content-Range: bytes X-Y/total` against the same resource, assembling
   one file, verifying the final content and size match; an out-of-order or
   overlapping chunk (verify actual, not assumed, behavior).
5. **DELETE**: delete a file (204) then confirm 404 on subsequent GET; delete a
   directory recursively (verify children gone too); delete a non-existent
   path (404); delete without write permission via a second, non-owning test
   user (403), reusing `createUsersACL()`'s multi-user pattern from the
   existing CalDAV tests.

## Status

**All 5 phases complete**, plus a follow-up fix round (2026-09-04, same day).
25 tests across `api/tests/WebDAV/{PropfindTest,GetTest,PutTest,DeleteTest}.php`
plus the shared `api/tests/WebDAVTest.php` base class, all green together.

Of the 4 bugs found (below), ralf's decisions on each:
- **GET bugs #2 and #3: FIXED** in `api/src/WebDAV/Server.php` (shared with
  CalDAV/CardDAV - re-ran `api/tests/CalDAV/` + `calendar/tests/CalDAV/`
  after the fix, 84 tests still green, no regression). Tests updated to
  assert the now-correct 206/416 behavior.
- **PROPFIND `Depth: infinity` (#1): intentionally NOT going to be supported.**
  Ralf: "something I don't want to support, it will blow up anyway on a real
  installation" (unscoped whole-tree recursion over `egw_sqlfs` with no depth
  limit). Test stays as documentation of the deliberate limitation, not a bug
  to chase.
- **Recursive DELETE on Versioning-backed mounts (#4): undecided**, left
  as-is/documented for now.

### Real bugs found

1. **PROPFIND `Depth: infinity` doesn't recurse - by design, not going to be fixed.**
   `Server/Filesystem.php::PROPFIND()` has a literal `// TODO recursion needed
   if "Depth: infinite"` - depth `"infinity"` is treated identically to depth
   `1` (`!empty($options["depth"])` is true for both), so nested collections
   are never descended into, contrary to RFC 4918.
   `PropfindTest::testDepthInfinityDoesNotActuallyRecurse()`.
2. **FIXED - GET suffix byte-range (`Range: bytes=-N`) never returned 206.** In
   `Server.php::http_GET()`'s range handling, the suffix-range branch (reached
   when a range has no `start` key) did `fseek()`+`fpassthru()` but - unlike
   the two branches just above it - never called `http_status("206 Partial
   content")`, so the response body was correctly partial but the status
   stayed whatever was set earlier (200 OK). Fixed by adding the missing
   `$this->http_status($status = "206 Partial content");` call.
   `GetTest::testSuffixByteRangeReturnsLastNBytes()`.
3. **FIXED - GET range start past EOF never returned 416.** The out-of-bounds
   check (`fseek($stream, $range['start']); if (feof($stream)) { 416 }`) was
   ineffective: PHP's `feof()` only becomes true after a *failed read*, not
   merely from seeking past a stream's physical end, so the check never
   triggered - falling through to a 206 response with an empty body instead
   of a 416. Fixed by checking `$range['start'] >= $options['size']` directly
   (when the size is known) BEFORE the `fseek()`, instead of relying on
   `feof()` after it. `GetTest::testRangeStartBeyondFileSizeReturns416()`.
4. **Recursive directory DELETE fails on Versioning/soft-delete-backed mounts
   - undecided, not fixed yet.**
   `Vfs\WebDAV::DELETE()` (api/src/Vfs/WebDAV.php:81) calls `Vfs::remove()` and
   requires the directory's OWN entry in the returned array to be `true` for a
   204 - but on this dev environment's default mount (`stylite.s3://` →
   `Versioning\StreamWrapper`, see [[project_vfs_test_coverage]]), removing a
   child is a SOFT delete (`fs_active=0`), and `Sqlfs\StreamWrapper::rmdir()`'s
   "is this directory empty" check does not exclude inactive rows - so
   immediately after correctly soft-deleting the one child, the directory's own
   `rmdir()` sees a "non-empty" directory and fails, and the WHOLE request
   reports 403, even though the child WAS actually removed. Traced via a
   temporary debug instrumentation of `DELETE()` (added and removed again, not
   shipped). A plain hard-delete `sqlfs://` mount would not hit this.
   `DeleteTest::testDeleteDirectoryWithChildrenFailsOnThisVersionedBackend()`.

### Other real findings (not bugs, still worth knowing)

- **DELETE without permission returns 404, not 403.** `Vfs\WebDAV::DELETE()`
  checks `file_exists($path)` first; a user with no read access at all to
  another user's home directory sees `file_exists()` as false too, so the
  request looks exactly like a missing path before any permission check runs.
  This is sound behavior (doesn't confirm a private path's existence to an
  unauthorized user), just not the naively-expected 403.
  `DeleteTest::testDeleteWithoutPermissionReturns404NotExposingExistence()`.
- **PUT with `Content-Range` correctly supports out-of-order chunks.** Each
  chunk carries its own absolute byte offset and the underlying VFS stream
  wrapper (sqlfs-backed, via local pass-through) correctly supports
  arbitrary-offset seeks/writes even on a brand-new file (no "must write
  sequentially" requirement) - `PutTest::testOutOfOrderChunkedUploadStillAssemblesCorrectly()`.
- **A fixed, reused `account_lid` across separate test RUNS is unsafe here.**
  An account_lid reused from an earlier (especially interrupted) run can carry
  over stale mail-credential state that changes how
  `setup::add_account()`'s `changepassword` hook behaves - observed as an
  `Api\Exception\Http` ("Could not resolve host: jmap") escaping a
  `catch(\Exception $e)` that should have caught it (in
  `Mail\Hooks::run_plugin_hooks()`), aborting `createUser()`. Root cause not
  fully chased (orthogonal to WebDAV; likely a Stalwart/JMAP-discovery cache
  keyed in a way that behaves differently for a previously-used vs.
  brand-new lid) - worked around, not fixed, via `WebDAVTest::randomLid()`,
  which every phase's `setUpBeforeClass()` uses instead of a fixed literal.
- **`Content-Length` is legitimately absent from `text/*` GET responses.**
  `Server.php::http_GET()` only sends an explicit `Content-Length` header
  `if (!self::use_compression())`, and compression stays on for `text/*`
  mimetypes (turned off only for binary content, to avoid double-compressing
  things like zip files) - not a bug, just something to know before asserting
  on that header.

### Environment note

Every phase's tests run over REAL HTTP (`GuzzleHttp\Client`) against
`webdav.php` through `egroupware-nginx` (this session's docker-exec-based
testing needed `EGW_URL=http://egroupware-nginx/egroupware`, since the
`egroupware` container's own `localhost` does not route to nginx). Test
cleanup uses `WebDAVTest::hardDeleteHomeTree()` (direct DB deletion) rather
than trusting `Vfs::remove()`/WebDAV DELETE for teardown, for the same
Versioning/soft-delete reason as finding #4 above.
