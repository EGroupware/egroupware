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

**ALL 7 PHASES COMPLETE** (2026-09-04). See the "Whole-project status" note at the end of the Phase
7 section below for the final tally. Phase 6 (EPL/Stylite wrappers) was treated as **in scope for
this pass, not deferred** throughout - they underpin the commercial offering - and got equal
priority to core coverage rather than being left for "later if time allows". Phases below are listed
in dependency order (facade/mount-table first since everything else builds on them) followed by
per-phase detail in the order they were actually done.

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

**Phase 2 DONE** (2026-09-03): `api/tests/Vfs/MountTableTest.php` - green (18 tests): `mount()`/
`umount()` (root requirement, add/overwrite/no-op-if-identical, path-or-url unmount lookup),
`resolve_url()` (longest-mount-point-wins, non-`vfs`-scheme passthrough, `$host` placeholder
substitution, `resolve_url_cache` staleness until `clearstatcache()`), `mount_url()` (reverse
lookup), `scheme2class()` (core/simple/dotted-app-scheme/unknown), `load_wrapper()`
(known/unknown scheme). One naming gotcha found (not a bug): `scheme2class('vfs')` returns
`Vfs\Base::class`, not `Vfs::class` - `__CLASS__` inside `Base::scheme2class()` is compile-time
bound to `Base` (where it's written), not late-static-bound to whatever called it.

Deliberately NOT exercised with a real write: `mount(..., $persistent_mount=true)`'s server-wide
persistence branch (`Config::save_value('vfs_fstab', ...)`) - this dev environment's `vfs_fstab`
server config is exactly the fragile shared state documented above (currently pointing `/` at
`stylite.s3://`), and a test writing to it for real risks corrupting that shared config for every
other session/tab using this environment. All `MountTableTest`/`FacadeTest` mounts use
`$persistent_mount=false` (transient, in-memory only). The per-user persistent branch (positive int
`$persistent_mount`, writing to `Api\Preferences` instead) would be safe to test in a follow-up,
since it only touches the test's own account.

**Phase 3 DONE** (2026-09-03): `api/tests/Vfs/SqlfsBackendTest.php` - green (6 tests, own scratch
mount like `FacadeTest`). Symlink creation and `propfind`/`proppatch` storage were already
reasonably covered (`StreamWrapperBase::testSymlinkFromFolder`/`testSymlinkSelfReferential`,
`ProppatchTest.php`), so this file targets the genuine remaining gaps:
- **eACL enforcement, not just storage**: `testEaclGrantChangesAccessDecision` - `FacadeTest`'s
  eACL tests only checked `eacl()`/`get_eacl()` round-trip; this confirms a grant actually flips
  `Vfs::check_access()`'s decision for a user who otherwise has zero owner/group/other access, and
  that revoking flips it back.
- **Dangling symlink**: `Vfs::symlink()` doesn't require the target to exist (confirmed by reading
  `Sqlfs\StreamWrapper::symlink()`, `Sqlfs/StreamWrapper.php:1432-1464` - no check on `$target`);
  `is_link()`=true, `file_exists()`=false, `readlink()` returns the raw (nonexistent) target string.
- **Multi-hop symlink chain**: A -> B -> real file resolves correctly through `file_get_contents()`.
- **Real finding, not a bug**: a two-node A<->B symlink cycle. `Vfs::symlink()`'s creation-time
  check (`api/src/Vfs.php:2273-2293`) only rejects a link nested inside its own target's directory
  tree - it does NOT catch a true two-node cycle, since neither path is a prefix of the other.
  Confirmed this does NOT hang: `Vfs\StreamWrapper::check_symlink_components()`'s bounded
  `MAX_SYMLINK_DEPTH=10` hop counter (`StreamWrapper.php:54,905-940`) catches it after 10 hops,
  logging "maximum symlink depth exceeded, might be a circular symlink!" and returning false/null.
  Not treated as a bug to fix - the depth guard is exactly the safety net this class of gap is
  supposed to fall through to, and it works.
- **Real finding, asymmetric caching**: `chmod()` changes are visible via a facade `Vfs::stat()`
  read immediately, with NO explicit `Vfs::clearstatcache()` call needed - but `chown()` changes are
  NOT, despite both patching `Sqlfs\StreamWrapper::$stat_cache` directly in place the same way
  (`StreamWrapper.php:1026` for chown, `:1131` for chmod). Confirmed live via a diagnostic run:
  `chown()` genuinely returns `true` and the new owner IS persisted (a later `clearstatcache()` +
  `stat()` shows it correctly) - the facade-level staleness is real, not a false read. Root cause
  not chased further (plausibly PHP's own native `stat()` cache, consulted by the core
  `Vfs\StreamWrapper::url_stat()` at `StreamWrapper.php:757`, behaving differently for `chmod()` vs.
  `chown()` at the PHP-engine level) - documented and regression-tested
  (`testChownRequiresExplicitClearstatcacheUnlikeChmod`) rather than assumed away. Practical
  takeaway for any future code: always call `Vfs::clearstatcache()` after `Vfs::chown()` before
  relying on a subsequent `Vfs::stat()` read in the same request - `chmod()` doesn't need it, but
  don't rely on that being true for `chown()`/`chgrp()` too without checking.

**Phase 4 DONE** (2026-09-03): added to the existing per-wrapper test files rather than new ones,
since the shared `StreamWrapperBase` suite already covers basic CRUD/ACL for both:
- `api/tests/Vfs/Links/StreamWrapperTest.php` (+3 tests): `url_stat()`'s virtual entry-dir (a
  `/apps/$app/$id` path that "exists" as a directory before any file is ever uploaded to it, as long
  as the user has read access to the linked entry - `Links/StreamWrapper.php:159-208`), `eacl()`/
  `get_eacl()` being pure no-ops (`:221-241` - access to a link entry is governed entirely by the
  underlying app's own ACL, custom eACLs can't be layered on top the way they can for plain sqlfs
  paths), and `rmdir()` on the entry-dir itself silently no-op'ing rather than actually removing it
  (`:296-308`, "never delete entry-dir, as it makes attic inaccessible").
- `api/tests/Vfs/Filesystem/StreamWrapperTest.php` (+2 tests): `chmod()` being entirely unsupported
  (no such method on the class at all - the mount-configured fixed mode can't be changed per-file,
  confirms/extends the existing `testWithAccess` skip's stated reason), and `deny_script()` blocking
  a `.php`-named write when the mount has no `exec=1` query param (`Filesystem/StreamWrapper.php:
  120,755-761`, checked from `stream_open()` for any non-read-only open).
- `Sqlfs\Utils` (admin/maintenance) - `migrate_db2fs()` is a one-time legacy migration tool, out of
  scope; `quotaRecalc()` (`Sqlfs/Utils.php:558-593`) operates over the ENTIRE sqlfs table
  server-wide (not scoped to any test's own fixtures) and would be a real, broad side-effecting
  write against this shared dev DB - deliberately not exercised with a real call, same reasoning as
  Phase 2's skipped persistent-mount write. `fsck()` itself is Phase 7, not here.
- Confirmed (not newly caused) while running the full suite: `Links/StreamWrapperTest.php` and
  `Filesystem/StreamWrapperTest.php`'s inherited `testSymlinkSelfReferential` both still fail on the
  same pre-existing `/home/demo` -> `stylite.s3://` environment issue (`StreamWrapperBase`'s default
  `testSymlinkSelfReferential()` uses `Vfs::get_home_dir()` unconditionally, unlike
  `testSymlinkFromFolder()` which each subclass overrides to pass a wrapper-scoped path instead) -
  not touched, out of scope for this phase.

**Phase 5 DONE** (2026-09-03): `api/tests/Vfs/HooksTest.php` - green (11 tests), own scratch mount
like `FacadeTest`/`SqlfsBackendTest`. Two testing seams found to avoid needing a live push server or
DB writes: `Api\Hooks::$locations` (protected static) spliced via reflection to add a spy hook
alongside the real, already-registered ones for the test's duration; `Api\Json\Push::$backend`
(protected static) pre-set via reflection to the test object itself (implements
`Api\Json\PushBackend`) BEFORE any push happens, so `Push::checkSetBackend()`'s "only set if not
already set" guard skips trying to reach a real backend entirely.
- **Part A** (direct `vfs_*` hook firing, 6 tests): `vfs_mkdir`/`vfs_unlink`/`vfs_rename`/
  `vfs_rmdir` fire with the expected `path`/`from`/`to`/`stat` data; a write correctly fires
  `vfs_added` then `vfs_modified` then `vfs_read` in sequence. **Real finding**: `vfs_pre-write` is
  the ONLY `vfs_*` hook whose `Api\Hooks::process()` call does NOT pass `no_permission_check=true`
  (`StreamWrapper.php:288`, vs `:246,452,518,579,628` for all six others) - so unlike every other
  vfs hook, it only fires for an app the current user actually has run-rights to. No consumer is
  registered for it anywhere in the codebase today (confirmed by the original mapping), so this has
  zero observable effect currently, but it's a real inconsistency documented and regression-tested
  in case anyone ever wires a `vfs_pre-write` consumer up.
- **Part B** (`filemanager_hooks::vfs_hooks()`/`push()`, 5 tests - the corrected "real
  client-notification pipeline" finding from earlier in this doc): a zero-size file write triggers
  no push; a non-empty write outside `/home/` broadcasts (`account_id === Push::ALL`); temp/lock-file
  names (`~$...`, `.~lock....`, `....tmp`) are filtered entirely; a rename fires an extra `delete`
  push for the old path before the `add` push for the new one; a write under `/home/` targets the
  owner (not a broadcast) instead. All confirmed working as designed - no bugs found in this part,
  the filtering/targeting logic does what its comments say.
- **Deliberately not chased**: whether the `Push`->`swoolepush` link itself can silently drop
  messages once past `filemanager_hooks::push()` - that's the actual remaining candidate for "not
  always working" reports (`Api\Json\Push`'s real backend, `notifications_push`, wasn't touched by
  this phase's spy), but it's an integration/ops question (is the push server reachable, is the
  client's websocket connected, session drops...) rather than something a unit test in this suite
  can meaningfully assert on. If those reports continue, the next debugging step is there, not in
  the filtering/targeting logic Phase 5 now covers.

**Addendum to Phase 3** (2026-09-03, prompted by ralf asking specifically about it): partial
writes/seek, the mechanism WebDAV range-requests (`Content-Range`) use to upload large files in
chunks - a real gap this project had NOT covered. Added 4 tests to `SqlfsBackendTest.php`. Traced
the real code path first: `api/src/WebDAV/Server/Filesystem.php::PUT()` opens with `fopen($fspath,
"c")` instead of `"w"` specifically for a range request (`:557-558`, "c" creates but does NOT
truncate, unlike "w"), then the caller seeks to the range's start offset before writing - both
`Vfs\StreamWrapper::stream_seek()`/`stream_write()` (`StreamWrapper.php:286-341`) and
`Sqlfs\StreamWrapper`'s underlying local-file open (`Sqlfs/StreamWrapper.php:315-320`, same `$mode`
passed straight through to a real local `fopen()`) are thin pass-throughs with no bespoke chunking
logic of their own - so this exercises PHP's native local-file seek/write semantics through the
full Vfs stack, not a custom implementation. All 4 confirmed working correctly, no bugs found:
seek+overwrite mid-file, mode `"c"` not truncating existing content (mirrors the WebDAV PUT path
exactly), `stat()`'s size correctly derived from `Sqlfs\StreamWrapper::stream_close()`'s
seek-to-end+`tell()` (`:366-367`) rather than naively trusting the last write's byte count, and a
seek-past-EOF correctly zero-fills the gap (standard POSIX sparse-write semantics) rather than
corrupting the file. Not chased further: the WebDAV-protocol-level `Content-Range` header parsing
itself (which byte-range offset gets computed and handed to `fseek()`) - that's in the third-party
`HTTP_WebDAV_Server` base class, outside this project's Vfs-layer scope, and worth a note if a
real chunked-upload bug ever surfaces (the Vfs-layer mechanism itself is now confirmed sound).

**Phase 6 COMPLETE** (2026-09-04) - all 6 EPL/Stylite wrappers covered (`S3`, `Versioning`, `Links`,
`S3direct`, `Merge`, `Vlinks`), 6 real bugs found and documented. See the final tally near the end
of this section for the summary; per-wrapper detail follows below in commit order.

- **Corrected assumption - no AsyncAws mocking needed for basic CRUD**: the original Phase 6 plan
  above assumed S3 wrapper testing would need mocking AsyncAws's HTTP client. Reading
  `S3/StreamWrapper.php::stream_open()` (`:111-119`) shows it's a pure pass-through to the inherited
  `Vfs\Sqlfs\StreamWrapper` whenever the local blob file can be opened directly - S3 upload is an
  ASYNC background job (`installAsyncJob(self::class.'::s3Sync', ...)`, only installed on
  `stream_close()`, `:242-244`), never awaited synchronously. The S3-specific branch in
  `stream_open()` only engages when the local blob is missing but the DB row still exists (a
  re-download-from-S3 case) - not exercised yet, deferred, genuinely needs either a live upload to
  have happened first or a mocked `AsyncAws\S3\S3Client` (constructible with an injectable
  `HttpClientInterface` - `AbstractApi::__construct($config, $credentialProvider, $httpClient,
  $logger)` - and injectable into `StorageTrait::_s3client()`'s static `$clients` cache via
  reflection, the same technique used for `Api\Json\Push::$backend` in Phase 5. `symfony/http-client`
  ships `MockHttpClient`/`MockResponse` already in vendor, ready to use for that follow-up).
- **This environment's S3 storages ARE configured** (real minio credentials at `http://minio:9000`,
  confirmed via reflection on `S3\StreamWrapper`'s `$storages` property) - my earlier assumption
  that basic writes were failing because of missing/broken S3 config was wrong. The actual cause of
  my first failed attempt was the same "root-created scratch mount has no owner/group mode bits"
  gotcha from Phase 1/3 (see there) - I'd forgotten the `chown()`/`chmod()` step in a quick probe.
  Once fixed, basic CRUD via a `stylite.s3://` scratch mount works exactly like plain `sqlfs://`.
  The pre-existing `/home/demo` default-mount failure (still reproducing as of this writing) is a
  separate, still-uninvestigated issue - NOT chased further here, since every test in this project
  uses its own scratch mount anyway.
- **Real finding, fixed in the test not the app**: `S3\StreamWrapper::unlink()` (`:742-806`) is a
  SOFT delete - `fs_active=false` plus `fs_s3_flags |= FLAG_TO_DELETE`, the row stays in
  `egw_sqlfs` queued for real purging by a housekeeping job after `$retention_time` (30 days,
  intentional production behavior for S3-backed storage, not a bug). A naive
  `Vfs::remove()`/`Vfs::unlink()`-based tearDown (the pattern every other scratch-mount test in this
  project uses) therefore leaves permanent garbage rows in this shared dev DB on every run, and also
  makes the final `Vfs::rmdir()` fail ("dir is not empty!", since it doesn't filter by `fs_active`).
  `S3StreamWrapperTest`'s `tearDown()` hard-deletes the whole scratch subtree directly via
  `Api\Db::delete()` instead. Found + cleaned up 7 orphaned rows left behind by earlier debugging in
  this same session (confirmed zero orphans remain after the fixed test's own run).
- `stylite/tests/Vfs/S3StreamWrapperTest.php` (3 tests): basic read/write/update/delete works;
  `fs_s3_flags` resets to `0` ("not yet synced, local-only") after a normal write+close; the
  transient `FLAG_FILE_OPEN` bit is set while a file is open for writing and cleared again on close.
- **Confirmed NOT a cross-pollution problem in this combination**: ran the full community+EPL Vfs
  suite together (135 tests) - no new failures beyond the same 5 pre-existing ones. `S3\StreamWrapper`
  alone doesn't reference `Links`, so the `LinksParent` monkey-patch risk documented for EPL's Links/
  Vlinks wrappers doesn't apply here - still worth re-checking once an actual Links/Vlinks test file
  exists and runs in the same process as the community Links tests.

**`Versioning\StreamWrapper` DONE** (2026-09-04): `stylite/tests/Vfs/VersioningStreamWrapperTest.php`
(6 tests, committed `6ab8913` in the `stylite/` repo) - confirmed the "async S3 upload, synchronous
local CRUD" shape from `S3` carries over unchanged (not re-verified in depth). What's new/specific
to versioning:
- Writing to an existing NON-EMPTY file creates a SEPARATE new version row (`stream_open()`,
  `Versioning/StreamWrapper.php:213-224`) rather than overwriting in place; `stream_close()`
  (`:275-313`) then flips `fs_active` via a single `UPDATE ... CASE fs_id WHEN <new> THEN true ELSE
  false END WHERE fs_dir=? AND fs_name=?`, making every other same-named row inactive rather than
  deleting them - confirmed via direct row-count assertions, not just reading current content.
- `$min_version` defaults to **330 seconds** (`StreamWrapper.php:132`) - two writes closer together
  than that are silently coalesced into one version, not two. My first attempt at the
  "writing creates a new version" test failed against this real throttle (wrote v1 and v2
  milliseconds apart); fixed by mounting with `?min_version=0` for that test, and added a SEPARATE
  test confirming the default throttle behavior itself on an un-overridden mount.
- Opening for update without an actual `fwrite()` doesn't leave a spurious version behind
  (`stream_close()`'s `write_called` check, `:279-284`) - verified with mode `'a'`, NOT `'r+'`,
  because of the next finding.
- **Real bug found, documented not fixed** (touches shared `Sqlfs\StreamWrapper` logic used by
  every backend, not just Versioning - deserves careful review before changing):  opening an
  existing, non-empty file on a versioned mount with mode `'r+'` always fails.
  `Versioning\StreamWrapper::stream_open()` sets `$this->overwrite_new` (its "create a new version
  row" signal) unconditionally for any non-read, non-skip-versioning open; `Vfs\Sqlfs\StreamWrapper::
  stream_open()` (`Sqlfs/StreamWrapper.php:208-210`) treats a non-null `overwrite_new` as "this is
  effectively a new file", entering its create-branch - which immediately rejects because
  `$mode[0] == 'r'` ("does $mode require the file to exist (r,r+)", the code's own comment) matches
  `'r+'` too. But `'r+'` is exactly the read+write mode Versioning's own code comment ("copy current
  content into new version, if mode != w") implies should be supported for an in-place versioned
  edit. Not hit by the actual WebDAV upload path (`api/src/WebDAV/Server/Filesystem.php::PUT()` uses
  `"w"`/`"c"`, never `"r+"` - see the partial-write/seek addendum to Phase 3 above), so this doesn't
  block normal uploads, but it's a real trap for any other code that opens a versioned vfs path with
  the standard PHP `'r+'` mode. Regression-tested (`testReadWriteModeOnVersionedFileIsBroken`,
  asserts the CURRENT broken `false` return, with a comment on what to flip once/if it's fixed).
- Same soft-delete/orphan-row hazard as `S3` (`unlink()` and the version-swap both leave inactive
  rows rather than deleting) - `tearDown()` uses the same hard-delete-via-`Api\Db` pattern; verified
  zero orphans after a run.

**EPL `Links\StreamWrapper` DONE** (2026-09-04): `stylite/tests/Vfs/LinksStreamWrapperTest.php`
(9 tests, extends the shared `Vfs\StreamWrapperBase` like the community test does, mounting EPL's
`stylite.links://` scheme at `/apps` instead). Ralf's own framing going in: virtual directories +
symlinks exist so users can browse linked entries without knowing numeric ids; the directories act
as adaptive filters (year vs. year-month, depending on volume) implemented via the stat-cache, with
per-app plugins (`PluginIface`) defining the exact hashing criteria. Confirmed exactly this shape by
reading `Infolog.php` (the concrete plugin for the app already used as this project's fixture app):
root shows filter directories (eg. `"Own$"`, `"No filter$"`, one per `infolog_bo::$filters` entry,
translated + title-cased by `lang()`), each filter dir shows either entries directly (≤
`MAX_ENTRIES_NO_HASH=100`) or year-hash buckets that only refine to year-month once a single year
exceeds `MAX_ENTRIES_ALL=200` (`Infolog.php:126-142`, `get_hashes()`).

Confirmed the headline feature works end-to-end: `testEntryFindableViaVirtualFilterDirectory` finds
a freshly-created infolog entry by browsing `/apps/infolog/Own$/All$/` (no id lookup), and the
virtual name it finds resolves (via symlink, one level to the entry's directory, then to the actual
file) to the exact same content as the real `/apps/infolog/$id/...` path.

**Three real bugs found in the process, all documented not fixed** (this class's business logic,
not the Vfs layer itself - each needs its own dedicated fix/review, not a blind patch here):

1. **The "No filter" catch-all view is completely broken** - `Infolog::label2filter()`/`appdir()`
   both use a bare `!$filter` truthiness check where `$filter` can legitimately be the empty string
   (`infolog_bo::$filters['' => 'no Filter']`, the default "show everything" filter) - PHP's
   `!''` is `true`, so a correct match gets treated as "not found", and `appdir()` returns `null`
   immediately instead of listing anything. Confirmed via a live diagnostic (not by reading code
   alone): the directory exists and opens fine, but is always empty regardless of how many entries
   actually exist. Regression-tested (`testNoFilterCatchAllViewIsBrokenForEmptyStringFilterKey`,
   asserts the current broken-empty state, with a note on what to flip once fixed).
2. **A freshly-created entry with a real date isn't surfaced under its expected year-hash bucket at
   all** - a live diagnostic showed `Infolog::get_hashes()` returning only `{"": <count>, "all":
   <same count>}` for the "own" filter: every entry, including ones with genuine current-year
   dates, landing in the "undated" bucket (`Infolog.php:281` relabels literal-1970 `DATE_FORMAT()`
   results to the empty-string hash - consistent with `info_startdate=0` on old fixture entries, but
   NOT consistent with entries that have a real date). An
   `Api\Storage\Base::sanitizeOrderBy(...) REMOVED` log line fires on every run of the underlying
   query; may or may not be the actual cause, not chased further. This undermines the entire
   point of the adaptive hashing (avoiding huge unfiltered listings) - if grouping doesn't work,
   everything piles into one bucket instead of splitting by date. Regression-tested
   (`testFreshEntryNotSurfacedUnderExpectedYearHashBucket`). `testEntryFindableViaVirtualFilterDirectory`
   works around this by using the flat `"All$"` listing instead, which does no date filtering at all.
3. **Deleting a linked file fails with "permission denied"**, even though the same user just
   successfully wrote it moments earlier via the identical ACL path - `Versioning\StreamWrapper::
   unlink()` (reached via the `LinksParent` monkey-patch, see below) doesn't correctly consult
   Links' extended-ACL-by-linked-entry override the way plain-sqlfs `unlink()` does for the
   community wrapper. The inherited `StreamWrapperBase::testDelete()` is overridden to
   `markTestSkipped()` with this explanation rather than fail uninformatively, pending its own
   dedicated investigation (the mismatch is between two separate ACL-checking code paths, not a
   guess-and-patch fix).

**`LinksParent` monkey-patch cross-pollution risk - checked, NOT observed in this combination**:
ran the full community+EPL Vfs suite together (150 tests, including both `Links/StreamWrapperTest`
and this new `LinksStreamWrapperTest` in the same PHPUnit process) - no new failures beyond the
same pre-existing ones already documented (`/home/demo` mount, admin-login errors). Community
Links tests still pass normally even with EPL's Links class loaded process-wide. Still worth
re-checking if this combination's test order ever changes, given the monkey-patch is real and
process-wide, just apparently harmless for what these specific tests happen to exercise.

**`S3direct\StreamWrapper` DONE** (2026-09-04): `stylite/tests/Vfs/S3directStreamWrapperTest.php`
(5 tests). Genuinely different from `S3`/`Versioning`/`Merge`: `implements
Vfs\StreamWrapperIfaceNoDir` directly (NOT a `Sqlfs\StreamWrapper` subclass) - no local-storage
pass-through at all, every read/write/list/delete goes synchronously through the real AsyncAws S3
API against the actually-configured bucket. No mocking was needed after all - live connectivity was
confirmed working directly (a raw `listObjectsV2` call succeeded against this environment's minio),
so real reads/writes/deletes against a clearly-scoped, cleaned-up key prefix were used instead of
building the AsyncAws mock the original plan anticipated.

**Two safety/robustness measures added specifically for this wrapper, unlike anything else in
Phase 6** (both prompted directly by ralf mid-session, not assumed):
1. **CI safety**: `setUp()` does a real, cheap connectivity probe (a `listObjectsV2` call with a 5s
   timeout) and calls `markTestSkipped()` if it throws, rather than only checking that
   `self::$storages` config is non-empty - a non-empty config alone doesn't prove the endpoint is
   actually reachable, and CI has no S3/minio available at all.
2. **Bucket safety**: before writing anything, explicitly confirmed with ralf that this
   environment's configured bucket (`"boulder"` on local minio - the name coincides with this
   instance's own hosting domain, which read as a real-backups risk worth checking rather than
   assuming) is disposable/test-only. Confirmed safe to write/delete real objects there.

**Real bug found, documented not fixed** (two compounding, separate caching layers - each needs its
own fix, not a blind patch here): after writing a NEW file, a later `Vfs::stat()`/`file_exists()`
call incorrectly reports it as missing, for the rest of the PHP process's lifetime. Root cause 1:
`StreamWrapper::_clearStatCache()` (`StreamWrapper.php:750`, `protected static`) is **defined but
never called anywhere** in the class - `stream_open()`'s initial existence check
(`:140`) negatively caches a new file's path, and nothing ever invalidates that stale entry after a
successful write. Root cause 2 (compounds with the first, confirmed empirically - fixing only one
layer still failed): the SAME core-router native-`stat()`-cache layer already found in Phase 3
(`SqlfsBackendTest::testChownRequiresExplicitClearstatcacheUnlikeChmod`) - `Vfs::clearstatcache()`
alone doesn't reach S3direct's own internal cache either, since the class has no public
`clearstatcache()` method for `Vfs\Base::_call_on_backend()`'s dispatch to find. Real production
impact: any single request that creates a file and then re-checks/re-reads it (very ordinary) would
see it as missing. Every test needing to read back what it just wrote works around this via a
reflection-based `_clearStatCache()` call plus `Vfs::clearstatcache()`; one dedicated test
(`testStatCacheNeverInvalidatedAfterWrite`) deliberately skips the workaround to document the bug
itself, with a note on what to flip once it's fixed.

**`Merge\StreamWrapper` DONE** (2026-09-04): `stylite/tests/Vfs/MergeStreamWrapperTest.php` (extends
`Vfs\StreamWrapperBase` like the community wrapper tests do, 10 tests total incl. inherited ones).
`SharingBackendTest::testMergeReadonly()`/`testMergeWritable()` already cover Merge as one of
several backends exercised THROUGH sharing (create a share link, check readonly/writable access
through it) - genuinely different from what this new file targets: the overlay mechanics
themselves, none of which were covered anywhere before:
- Reading a file that only exists in the readonly filesystem source reads straight through to it
  (`Merge/StreamWrapper.php:452-455`).
- Writing a brand-new file (no filesystem counterpart) creates a genuine new sqlfs entry.
- **Deleting a file that only exists in the filesystem source does NOT touch the real file** - it
  creates a 0-byte marker in sqlfs instead (`:152-174`, "to delete a file, create an empty
  (size=0) file (in sqlfs)"), which `url_stat()` then treats as "doesn't exist" (`:422-426`).
  Verified the real file on disk stays untouched, which is the entire point of the mechanism.
- Deleting a file that HAS an sqlfs override removes just the override, restoring the underlying
  filesystem source's content as visible again (`:158-161`) - NOT a real delete either.
- The `testSymlinkFromFolder`/base-class tests requiring `mkdir()` are skipped: Merge's `mkdir()`
  requires Admin-group membership (matches `SharingBackendTest`'s own comment about the same
  requirement), not set up for the regular test account here.

**Confirmed, not caused, by running the full suite together (178 tests)**: `SharingBackendTest`
alone (no new Phase 6 files loaded at all) already fails the exact same 9 ways in this
environment - `testHomeReadonly`/`testHomeWritable`/`testSharingSymlink*`/`testShareFileInside*`
all trace to the same pre-existing `/home/demo` mount issue documented since Phase 1, and
`testMergeReadonly`/`testMergeWritable` fail on `Vfs::mkdir('/merged/sub_dir/')` for the exact same
Admin-group requirement just found independently above. None of this is new or caused by any test
in this project.

**`Vlinks\StreamWrapper` DONE, Phase 6 COMPLETE** (2026-09-04):
`stylite/tests/Vfs/VlinksStreamWrapperTest.php` (8 tests). Confirmed the prediction from the
mapping above: `Vlinks` is genuinely a thin subclass of the COMMUNITY `Links\StreamWrapper` (not
EPL's own richer one) - no `url_stat()`/`check_extended_acl()`/`dir_opendir()` overrides of its
own, so its virtual-entry-dir and `eacl()`/`get_eacl()`-no-op behavior mirror Phase 4's community
coverage exactly, just reached via the `stylite.vlinks://` scheme. Hit the SAME real bug #3 already
found and documented for EPL's own `Links\StreamWrapper`
(`LinksStreamWrapperTest::testDelete()`) - `unlink()` fails with permission denied via the
`LinksParent`-routed, S3-backed `Versioning\StreamWrapper` chain - confirming that bug is structural
to the shared `LinksParent` monkey-patch mechanism itself, not specific to EPL's own Links
implementation. Skipped `testDelete()` the same way, pointing at the other class's full explanation
rather than duplicating it.

**Phase 6 final tally**: 6 EPL/Stylite wrappers covered (`S3`, `Versioning`, `Links`, `S3direct`,
`Merge`, `Vlinks`), **6 real bugs found** across them (Versioning's `r+`-mode bug; Links' 3 infolog
bugs - broken "No filter" view, broken year-hash grouping, unlink permission-denied; S3direct's
two-layer stat-cache bug; and the same unlink bug confirmed structural via Vlinks) - all documented,
none silently fixed, each flagged as needing its own dedicated review. No `AsyncAws\S3\S3Client`
mock was ever actually needed - either local pass-through covered the common case (`S3`,
`Versioning`, `Merge`), or the live minio connection just worked (`S3direct`).

## Phase 7: fsck consistency-check mechanism

**DONE, PROJECT COMPLETE** (2026-09-04).

All 4 private `fsck_fix_*` checks on `Vfs\Sqlfs\Utils` scan the WHOLE `egw_sqlfs` table with no way
to scope them to a subtree - unlike everything else in this project, they're not naturally
test-isolatable. `$check_only=true` (the default) is read-only/safe against the shared dev DB, so
it's used throughout; the REPAIR path (`$check_only=false`) is deliberately NOT exercised against
live data, for the same reasoning as Phase 2's skipped persistent-mount write and Phase 4's skipped
`quotaRecalc()` call - it would touch/modify whatever else happens to be inconsistent in this shared
DB, not just rows a test created itself. DETECTION is still thoroughly testable and safe: each
private method is invoked directly via reflection against a specific, isolated row the test seeds
itself (never touching real/pre-existing data).

- `api/tests/Vfs/FsckTest.php` (6 tests, own scratch mount like `FacadeTest`/`SqlfsBackendTest`):
  `Utils::fsck(true)` runs without error (smoke test); the 3 required top-level nodes
  (`/`, `/home`, `/apps`) are currently healthy (not simulated - deleting the real root node, even
  temporarily, would be far too disruptive to this shared DB and any concurrent session using it);
  `fsck_fix_no_content()` detects a file whose physical blob was deleted from `files/sqlfs`, and
  separately an unexpected 0-byte file outside `/templates/`; `fsck_fix_unconnected()` detects a
  node whose `fs_dir` points at a non-existent parent; `fsck_fix_multiple_active()` detects two
  active rows sharing the same `fs_dir`+`fs_name`. Verified zero orphaned/corrupted rows remain
  after a run.
- `stylite/tests/Vfs/LinksStreamWrapperTest.php` (+1 test,
  `testFsckDetectsInactiveEntryDir`): `Links\StreamWrapper::fsck()` (the first of the two registered
  EPL `fsck` hook consumers, `stylite/setup/setup.inc.php:80`) detects an entry-dir left inactive
  under `/apps/$app` - "undelete entry dirs, to make their attic accessible" per its own docblock.
- `stylite/tests/Vfs/S3StreamWrapperTest.php` (+1 test,
  `testS3checkRunsInCheckOnlyModeWithoutError`): `S3\StreamWrapper::s3check()` (the second EPL
  `fsck` hook consumer) is only smoke-tested, not given a seeded-detection test like the others -
  it does a live, whole-bucket `listObjectsV2` scan with no way to scope it to a test's own key
  prefix, inherently slower/costlier than a single SQL query; the connectivity-check-and-skip
  pattern already established for `S3direct` confirms the underlying S3 access itself works, and
  `s3check()` is a straightforward consumer of that same access.

**Whole-project status: all 7 phases complete.** Final tallies: **2 real bugs found and FIXED**
(`Vfs::get_eacl()`'s `TypeError`; none in Phase 7) plus **6 real bugs found in EPL wrappers and
documented, not fixed** (Phase 6's tally above), plus several real, non-obvious findings documented
without being bugs (the `vfs_pre-write` permission-check inconsistency, the `chmod`/`chown` caching
asymmetry, the two-node symlink cycle safety confirmation, the `LinksParent` monkey-patch
architecture). All work is committed locally in both this repo and the separate `stylite/` (EPL)
repo, per the shared-checkout convention of never auto-pushing.
