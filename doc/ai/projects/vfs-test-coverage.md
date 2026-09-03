# Vfs test coverage

Goal: build real test coverage for the VFS subsystem - the mount-tab of stream-wrappers EGroupware
uses for all file storage (`filemanager`, attachments, shares, WebDAV, ...). Follow-on to
[storage-test-coverage.md](storage-test-coverage.md) and [db-test-coverage.md](db-test-coverage.md)
(read those first for conventions reused here: phased mapping-then-implementation, "found and
documented but not silently fixed" discipline unless something is actively breaking things,
shared-dev-checkout commit-but-don't-push discipline, and `docker exec -e EGW_ADMIN_PASSWORD=...`
needing the var passed through explicitly). Comparable in scope to `storage-test-coverage.md` -
expect this to span multiple sessions.

## Shape of the system (mapped 2026-09-03)

Four layers, each needing distinct test treatment:

1. **`Api\Vfs` facade** (`api/src/Vfs.php`, ~50 static methods) - the public API almost all app code
   calls: `fopen/opendir/scandir/copy/find/remove/stat/lstat/is_dir/is_link/file_exists/mkdir/rmdir
   /unlink/rename/touch/chmod/chown/chgrp/symlink/readlink/is_readable/is_writable/is_executable
   /check_access/eacl/get_eacl/propfind/proppatch/lock/unlock/checkLock/parse_url/concat/build_url
   /basename/dirname/mime_content_type/mime_icon/hsize/mode2int/int2mode/scheme2class/load_wrapper
   /clearstatcache/...`. **Zero dedicated facade test file exists** - only incidental coverage via
   wrapper/sharing tests calling a handful of these.

2. **`Vfs\Base` mount table** (`api/src/Vfs/Base.php`) - the "fstab": stored via `Api\Config`
   (server-wide, key `vfs_fstab`) and/or `Api\Preferences` (`common.vfs_fstab`, per-user), NOT a DB
   table. `mount()`/`umount()` (`Base.php:84-231`) manage it; `resolve_url()` (`:272-407`) is the
   core resolver - longest-matching mount-point wins, placeholders (`$user/$pass/$host/$home`)
   substituted, results cached in `$resolve_url_cache` (`:257`) and `$symlink_cache` (`:414`), both
   invalidated by `Base::clearstatcache()` (`:507`). `scheme2class()`/`load_wrapper()` (`:518-591`)
   map a scheme to its wrapper class by convention, with a native-PHP-wrapper passthrough (this is
   how `smb://` works - see EPL section). **No dedicated test file** for any of this.

3. **Stream-wrappers implementing `StreamWrapperIface`/`StreamWrapperIfaceNoDir`**
   (`api/src/Vfs/StreamWrapperIface{,NoDir}.php`):
   - `Vfs\StreamWrapper` (`StreamWrapper.php`, 1074 lines) - the `vfs://` router; delegates to
     backends via native PHP calls on the resolved backend URL. Fires the notification hooks
     (`vfs_read/added/modified/pre-write/unlink/rename/mkdir/rmdir`, `:246-628`).
   - `Vfs\Sqlfs\StreamWrapper` (2089 lines) - the actual default backend (SQL metadata + ACL/eACL +
     disk blobs). Owns `chown/chmod/readlink/symlink/propfind/proppatch` and extended-ACL storage.
   - `Vfs\Links\StreamWrapper` (455 lines) - `/apps/$app/$id` virtual dirs, ACL-by-linked-entry.
   - `Vfs\Sharing\StreamWrapper` (133 lines) - `sharing://` → scoped `vfs://` URL translation for
     share links.
   - `Vfs\Filesystem\StreamWrapper` (807 lines) - real-OS-path mounts with fixed synthetic
     user/group/mode.
   - `Vfs\Sqlfs\Utils` (612 lines) - admin/maintenance subclass of Sqlfs wrapper.

   **Existing tests**: `api/tests/Vfs/StreamWrapperBase.php` (shared fixture, 6 test methods) +
   `Vfs/StreamWrapperTest.php`, `Vfs/Filesystem/StreamWrapperTest.php`, `Vfs/Links/StreamWrapperTest.php`,
   `Vfs/Sharing/StreamWrapperTest.php` (per-wrapper subclasses) cover basic read/write/delete/access/
   symlink. Sharing additionally has `SharingBase.php`, `SharingHooksTest.php` (3 tests, the ONLY
   hook coverage that exists), `SharingACLTest.php` (5), `SharingBackendTest.php` (13, cross-backend),
   `AnonymousSharingTest.php` (1). `ProppatchTest.php` (3) covers WebDAV props via the facade.
   Not covered anywhere: owner/group/filemode changes, symlink creation (only resolution is touched
   incidentally), extended ACL, most of the notification hooks, `Sqlfs\Utils`.

4. **EPL/Stylite wrappers** - CORRECTED location: earlier mapping missed that `/Volumes/htdocs/egroupware/stylite/` (right inside this working tree, own `.git`, origin `epl.git`) is a live, **more current** checkout (HEAD `2eea75760` 2026-09-03) than `/Users/ralf/epl-26-checkout` (HEAD `864af4d24` 2026-06-15) - use `stylite/src/Vfs/` here as the working reference, not the other checkout. Wrappers found at `stylite/src/Vfs/`:
   `S3\StreamWrapper` (1150 lines, extends `Sqlfs\StreamWrapper`), `S3direct\StreamWrapper` (902
   lines, own stat cache), `Merge\StreamWrapper` (625 lines, extends S3), `Versioning\StreamWrapper`
   (1159 lines, extends S3), `Links\StreamWrapper` (EPL's own, 1306 lines, distinct from the Api one),
   `Vlinks\StreamWrapper` (73 lines). **Zero test coverage for any of these.**

   **SMB is a non-finding**: there is no EGroupware/EPL SMB stream-wrapper class anywhere. `smb://`
   is handled entirely by the native PHP `smbclient` PECL extension, which self-registers with PHP;
   `Base::load_wrapper()` just lets any already-registered native scheme pass through
   (`Base.php:518-546`). The only EGroupware-side code touching SMB is two defensive workarounds in
   `Vfs\StreamWrapper` (`:565-567`, `:1006`) and mount-URL construction in
   `filemanager_admin`/`filemanager/cli.php`. There's nothing meaningful to unit-test here beyond
   those two workaround branches and the URL-building - do not plan a dedicated SMB wrapper-test
   phase, it doesn't exist as our code.

## `fsck` consistency-check mechanism (new finding, zero coverage)

`Vfs\Sqlfs\Utils::fsck($check_only=true)` (`api/src/Vfs/Sqlfs/Utils.php:111-149`) is the sqlfs
backend's self-check/repair routine: runs `fsck_fix_required_nodes()` (`:158`, ensures `/`, `/home`,
`/apps` exist), `fsck_fix_multiple_active()` (`:452`), `fsck_fix_unconnected()` (`:368`),
`fsck_fix_no_content()` (`:262`), then **fires an `Api\Hooks::process(['location' => 'fsck',
'check_only' => ...])`** (`:127-138`) so other stream-wrappers can plug in their own backend-specific
consistency checks, merging their returned messages into the result; if fixes were applied it also
triggers `quotaRecalc()` (`:146`). Registered `fsck` hook consumers, both in
`stylite/setup/setup.inc.php:80-81`:
- `Stylite\Vfs\Links\StreamWrapper::fsck()` (`stylite/src/Vfs/Links/StreamWrapper.php:1273`)
- `Stylite\Vfs\S3\StreamWrapper::s3check()` (`stylite/src/Vfs/S3/StreamWrapper.php:1018`)

Triggered via `filemanager/fsck.php` (CLI), `filemanager_admin::fsck()` (admin UI, `class.
filemanager_admin.inc.php:327`), or the bottom-of-file CLI entrypoint in `Utils.php:611` itself.
**Zero test coverage exists anywhere** for any of `Utils::fsck()`, its four private repair methods,
or either EPL hook consumer - real, self-contained, DB-repair logic that's a natural fit for direct
testing (seed known-corrupt fixture state, run `fsck($check_only=true)` and assert detection, then
`fsck($check_only=false)` and assert repair). Added as Phase 7 below.

## Notification hook finding (relevant to "not always working" reports) - CORRECTED

Initial mapping (via grep scoped to `api/src/Vfs*`) missed the real consumer - it lives in the
`filemanager` app, not core Vfs. Corrected picture:

`Vfs\StreamWrapper` fires `vfs_read/added/modified/pre-write/unlink/rename/mkdir/rmdir` hooks
(`Api\Hooks::process`, `StreamWrapper.php:246-628`). Two consumers are registered:
- `Vfs\Sharing::vfsUpdate()` (`api/setup/setup.inc.php:70-72`) - `vfs_unlink/rename/rmdir` only,
  expires/updates share records. Already has direct test coverage (`SharingHooksTest.php`).
- **`filemanager_hooks::vfs_hooks()`** (`filemanager/inc/class.filemanager_hooks.inc.php:310-331`,
  wired for `vfs_added/modified/unlink/rename/rmdir/mkdir` in `filemanager/setup/setup.inc.php:31-36`
  - note `vfs_read`/`vfs_pre-write` have no consumer at all) - **this is the actual client-notification
  pipeline** the "not always working" reports are about. It filters out temp/lock files and
  zero-size files (`:319-330`, "some WebDAV clients create zero-size files prior to every real
  update"), then `push()` (`:340-436`) computes: home-dir paths broadcast only to
  owner+group-members (via `$GLOBALS['egw']->accounts->members()`), everything else broadcasts to
  `Push::ALL`; an `acl` array merging owner/group/eACL-owners is attached; `vfs_rename` does an
  extra `delete`-type push for the old path before falling through to the `add` case. Sends via
  `Api\Json\Push` (`api/src/Json/Push.php`) → presumably the `swoolepush/` server (separate
  easyswoole-based process in this repo) for actual client delivery, not confirmed how PHP-side
  `Push` reaches that process yet - worth confirming, since that link is a plausible place for
  "not always working" to break (e.g. push-server not running/connected, silently swallowed).
  **Zero existing test coverage** for `filemanager_hooks::vfs_hooks()`/`push()` (only
  `FilemanagerMimeTypeTest.php` exists in `filemanager/tests/`, unrelated).

This is real, testable logic with several plausible bug sources (the zero-size-file filter dropping
legitimate small-file updates, the home-dir owner/group-member targeting, the eACL-merge, the
rename double-push) and belongs in this project given it's the direct answer to "reports this isn't
always working". Added as Phase 5 below, scoped to what's unit-testable (hook firing + `push()`'s
targeting/payload logic via a fake/spy `Push`); actually confirming end-to-end delivery through the
`swoolepush` process is a separate, likely-manual/integration concern - flag it, don't assume it's
in scope for a PHPUnit suite.

## Proposed phasing

1. **Facade unit tests** (`api/src/Vfs.php`) - highest value, most central. Pure-function group first
   (`parse_url/concat/build_url/basename/dirname/mode2int/int2mode/hsize/int_size/mime_icon` - no
   fixture needed, fast wins), then fixture-backed group (`stat/lstat/is_dir/is_link/file_exists/
   is_readable/is_writable/is_executable/check_access/mkdir/rmdir/unlink/rename/touch/copy/remove/
   find/chmod/chown/chgrp/symlink/readlink/eacl/get_eacl/lock/unlock/checkLock/scheme2class/
   load_wrapper/clearstatcache`).
2. **Mount table** (`Vfs\Base`) - `mount()`/`umount()` persistence (server config vs. per-user prefs),
   `resolve_url()` correctness incl. longest-match/placeholder substitution, `resolve_url_cache` and
   `symlink_cache` correctness + invalidation, `scheme2class()` dotted-scope and legacy-fallback
   paths, `load_wrapper()` failure paths.
3. **Core wrapper deep coverage** - extend existing `StreamWrapperBase`-derived tests for
   `Vfs\StreamWrapper` + `Vfs\Sqlfs\StreamWrapper`: owner/group/filemode changes, symlink creation +
   self-referential/broken-symlink edge cases, extended ACL (`eACL`) storage+enforcement,
   `propfind`/`proppatch` storage side, stat-cache behavior.
4. **Secondary Api/Vfs wrappers** - gap-fill `Links\StreamWrapper` (ACL-by-linked-entry correctness),
   `Filesystem\StreamWrapper` (fixed user/group/mode enforcement), `Sqlfs\Utils` (admin/maintenance
   paths); `Sharing\StreamWrapper` is already reasonably covered, gap-fill only.
5. **Hooks** - direct coverage that `vfs_added/modified/read/pre-write/mkdir/rmdir/unlink/rename` all
   fire with correct data on the right operations (currently only unlink/rename/rmdir are indirectly
   tested via `SharingHooksTest`); PLUS `filemanager_hooks::vfs_hooks()`/`push()`
   (`filemanager/inc/class.filemanager_hooks.inc.php`) - the real client-notification pipeline (see
   corrected finding above), currently zero coverage: temp/lock-file and zero-size filtering,
   home-dir owner/group-member targeting vs. broadcast-to-all, eACL-merge into the `acl` payload,
   the rename extra-delete-push. Test via a spy/fake on `Api\Json\Push` rather than a real
   `swoolepush` round-trip. Document (don't chase) whether the `Push`→`swoolepush` link itself can
   silently drop messages - that's an integration/ops question, not a unit-test target.
6. **EPL/Stylite wrappers** (`stylite/src/Vfs/`, live checkout inside this working tree - see
   corrected location above) - `S3`, `S3direct`, `Merge`, `Versioning`, `Links` (EPL), `Vlinks`.
   **Priority, not deferred**: these back the commercial offering, ralf confirmed they matter as
   much as core coverage. Test-infra check done (2026-09-03): `stylite/tests/` is auto-picked-up by
   `doc/phpunit.xml`'s `<directory>../*/tests/</directory>` "Apps" testsuite - no separate PHPUnit
   config needed. Only 2 existing test files there (`FirewallTest.php`, `PMCalendarIntegrationTest.php`,
   both plain `PHPUnit\Framework\TestCase`, no shared base yet) - nothing Vfs-specific, and no
   convention blocks reusing `api/tests/LoggedInTest` or `StreamWrapperBase`-style fixtures from core.
   `Links`(EPL)/`Vlinks` have no external-service dependency - straightforward, same fixture style as
   `Api\Vfs\Links`. `S3`/`S3direct`/`Merge`/`Versioning` all ultimately go through
   `AsyncAws\S3` (`stylite/src/Vfs/S3/StreamWrapper.php:20,549` - `AsyncAws\S3\ValueObject\AwsObject`,
   `self::_s3client($bucket)`) and there's **no local S3 test backend today** (no MinIO in
   `doc/docker`, no test bucket config) - needs a decision: mock at the AsyncAws HTTP-client layer
   (e.g. Symfony `MockHttpClient`, no external service needed) vs. spinning up a real MinIO container
   for integration-style tests. Mocking is the safer default to unblock Phase 6 without new infra;
   flag MinIO as a stretch option if mocking proves too shallow.

   **CI note**: `.github/workflows/testing.yml` only does one public `actions/checkout` (`:26,411`)
   and never fetches the private `epl.git` repo, so `stylite/tests/` is invisible to CI - it only
   runs when a developer has the private checkout present locally (as here). Phase 6/7-EPL results
   can therefore only ever be verified locally, never confirmed green by CI - any "verified passing"
   note for that work in this doc means "passed in a local run with `stylite/` checked out", not a
   CI-backed guarantee.
7. **`fsck` consistency-check mechanism** - `Vfs\Sqlfs\Utils::fsck()` and its 4 private repair
   methods (core, `api/src/Vfs/Sqlfs/Utils.php`), plus the two EPL hook consumers
   (`Stylite\Vfs\Links\StreamWrapper::fsck()`, `Stylite\Vfs\S3\StreamWrapper::s3check()`) - see
   finding above. Zero coverage anywhere; natural fit for seed-corrupt-state → assert-detect →
   assert-repair style tests. Depends on Phase 6's EPL test-infra check for the two hook-consumer
   tests; the core `Utils::fsck()` part can proceed independently.

Explicitly out of scope: writing a dedicated SMB stream-wrapper test suite (no such code exists,
see above); fixing the client-notification gap or confirming `Push`→`swoolepush` delivery (separate
investigation).

## Status

Phase 6 (EPL/Stylite wrappers) is confirmed **in scope for this pass, not deferred** - they underpin
the commercial offering. Phases are listed in dependency order (facade/mount-table first since
everything else builds on them) but Phase 6/7's EPL halves carry equal priority to their core
counterparts, not "do this last if time allows".

**Phase 1 DONE** (2026-09-03), committed `705510147c`:
- `api/tests/Vfs/PathHelpersTest.php` - green (45 tests): `parse_url/concat/build_url/
  basename/dirname/mode2int/int2mode/hsize/int_size`, all pure-function, bare `TestCase`.
- `api/tests/Vfs/FacadeTest.php` - green (31 tests): mkdir/rmdir/touch/unlink/copy/rename/
  remove/stat/lstat/is_dir/is_link/file_exists/clearstatcache/chmod/chown/chgrp/is_readable/
  is_writable/is_executable/eacl/get_eacl/scheme2class/load_wrapper/find/lock/unlock/checkLock.
  Deliberately mounts its own scratch `sqlfs://` area per test (see "Local dev environment gotchas"
  below) rather than relying on whatever `/`/`/home` happen to be mounted to. 76 tests total, 213
  assertions.
- **Real bug found + fixed**: `Vfs::get_eacl()` (`api/src/Vfs.php:884-911`) crashed with a
  `TypeError` (`usort(): Argument #1 ($array) must be of type array, false given`) whenever the
  backend has no persisted eACL for a path AND a session-only eACL needs merging in - `$eacls[] =
  $eacl` on a `false` base left `$eacls` as `false`. This isn't a corner case: the notification-hook
  pipeline (`filemanager_hooks::vfs_hooks()` -> `push()`) calls `get_eacl()` on every mkdir/rmdir, so
  once ANY session-only eACL exists (`Vfs::eacl($url,...,$session_only=true)`, which never expires
  until the session ends or something explicitly clears it), every later notification for a
  different, unrelated path with no persisted eACL of its own would crash. Fixed by defaulting
  `$eacls` to `[]` when the backend returns non-array. Regression-covered by
  `FacadeTest::testEaclSessionOnly` + `testEaclSetGetDelete` (which also clears the session eACL in
  `tearDown()`, since it isn't scoped to the test's own scratch mount and otherwise leaks into every
  later test in the same PHPUnit process).
- Two other real behavior gotchas found and just worked around in the test (not bugs -
  `Vfs::copy()` returns the dest stat array not a plain boolean; `Vfs::chgrp()`'s target and
  `stat()['gid']` differ in sign, since `Accounts::name2id()` returns group ids negative but `gid`
  is unsigned).

**Local dev environment gotchas found along the way** (both confirmed NOT caused by this session,
and NOT present in CI):
1. This container's default `/` vfs mount is persisted server-wide to `stylite.s3://` (mirrors
   production/IONOS hosting; ralf uses local minio for it locally). At one point its local
   S3-download cache (`/var/lib/egroupware/<domain>/files/sqlfs/01/{29,37,39}`) had phantom/corrupted
   directory entries (`readdir()` lists them, `stat()` returns ENOENT) - a Docker-Desktop-for-Mac
   bind-mount (gRPC-FUSE/virtiofs) glitch, cleared by ralf via a host-side `rm -rf`. This still
   breaks the pre-existing `StreamWrapperTest.php` (relies on the default `/home` mount) as of this
   writing - separate from anything Phase 1 touches, since `FacadeTest.php` mounts its own scratch
   `sqlfs://` area instead of relying on the default mount.
2. `docker exec` defaults to root for this image, not `www-data` (which is what PHP-FPM actually
   runs as) - confirmed via [[feedback_docker_exec_phpunit_as_www_data]] as a known recurring
   source of "root wrote something www-data can't read" bugs here (packaging scripts historically).
   Always use `docker exec -u www-data egroupware ...` for phpunit and any other vfs-writing
   command going forward.
3. `Vfs::mount(..., $persistent_mount=false)` (a transient, session-only mount) gets silently wiped
   if followed by `Vfs\StreamWrapper::init_static()` - that unconditionally reloads `self::$fstab`
   from the server-persisted config whenever one exists (`StreamWrapper.php:1041-1044`). Harmless in
   a stock/CI environment (no persisted override to reload), but breaks any transient mount in an
   environment with one (like this one, for `stylite.s3://`). `FacadeTest::setUp()` mounts without
   the following `init_static()` call for exactly this reason - existing helpers
   (`StreamWrapperBase::mountFilesystem()`/`mountVersioned()`/`mountLinks()`) still call it and could
   theoretically hit the same issue in an environment with a persisted fstab override, worth keeping
   in mind if they ever misbehave locally.
4. A directory created while `Vfs::$is_root = true` ends up owned `uid=0/gid=0` with NO owner/group
   mode bits at all (`Sqlfs\StreamWrapper`'s root-created-node convention) - the regular test user
   then has zero access to it. `FacadeTest::setUp()` explicitly `chown()`s + `chmod()`s its scratch
   mount-point back to the real test user after creating it, matching the existing
   `StreamWrapperBase::mountMerge()` convention.

**Next**: Phase 2 (`Vfs\Base` mount table - `mount()`/`umount()` persistence, `resolve_url()`,
`resolve_url_cache`/`symlink_cache`, `scheme2class()`/`load_wrapper()` edge cases).
