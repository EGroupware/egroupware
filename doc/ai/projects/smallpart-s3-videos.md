# smallPART video S3 storage

## Motivation

Ticket #89091 ("S3 ToDos") item 4: smallPART (ViDoTeach) videos are plain files under
`$files_dir/smallpart/Video/<course_id>/<video_hash>.<video_type>`, served directly by nginx
(`doc/docker/nginx.conf`'s `location /egroupware/smallpart/Resources/Videos { alias ...; }`) - no
`egw_sqlfs` row, no PHP involved in serving at all. The ticket asks for S3 support here, "like we
currently do it with files using the stylite.s3-SW", but videos must keep being served without
streaming through PHP (performance - Range requests, sendfile, zero DB/decrypt overhead per byte).

No code has been written yet - this doc is the architecture/options discussion, per `AGENTS.md`'s
"develop a plan before making changes".

## Current architecture (confirmed by reading the code)

- **Storage**: `smallpart/src/Bo.php::videoPath()` builds the path; `addVideo()`/`updateVideo()`
  `copy()`/`stream_copy_to_stream()` the upload there directly. No chunked/resumable upload.
- **Serving**: `videoSrc()` returns `/smallpart/Resources/Videos/Video/<course_id>/<video_hash>.<ext>`;
  nginx's `alias` maps that straight to the local file. Zero PHP/DB per playback byte.
- **Access control**: none at the nginx layer - `video_hash` is `Api\Auth::randomstring(64)`, a
  64-char random string. Security-through-unguessable-URL, not a real per-request check. Whatever
  we build must preserve at least this level of protection.
- **Table**: `egw_smallpart_videos` (`smallpart/setup/tables_current.inc.php`). No column currently
  tracks "when was this video's *file* last (re-)written" - `video_date` is set once via
  `default current_timestamp` at row creation and is shown to users as the lesson date
  (`JsObjects.php`), never touched by `updateVideo()` even though that can replace the file (new
  `video_hash`) on an existing row.

## Why a literal copy of `Stylite\Vfs\S3\StreamWrapper` doesn't work

That class gets "S3 as a transparent cache-through tier" by having *every* read go through its
`stream_open()` (download+decrypt on a local-cache miss, before returning a readable handle) - fine
for general VFS access patterns, incompatible with nginx serving bytes directly with no PHP in the
loop. The design below keeps nginx's static `alias` as the only thing that ever serves video bytes,
and moves the "is it there, make it there if not" check to a point *before* nginx is asked to serve
anything - never into the serving path itself.

## Reusable pieces

`stylite/src/Vfs/S3/StorageTrait.php` (shared by `Vfs\S3\StreamWrapper` and `Vfs\S3direct\StreamWrapper`)
is already decoupled from VFS/stream-wrapper specifics:

- `self::$storages` comes from `Api\Config::read('stylite')['s3_storages']` - a plain admin config
  array, not VFS-specific. "Use the same storage config" is free: a new class just
  `use StorageTrait;` and calls `_storageFromConfig()`. Same bucket/credentials as regular VFS/backup
  objects.
- `_runAndResolveOnAll('putObject', ..., $first=false, ...)` already auto-switches to multipart
  upload above 64MB and uploads to multiple storages generically, tracking per-storage success via a
  bitmask - exactly the "multiple storage redundancy, like the S3 SW" requirement, with no new design
  needed beyond reusing the same `fs_s3_flags`-shaped bitmask convention and `_storageMaskAll()`.
- `_s3client()`, `_fopenObjectRead()` (get+decrypt in one call), `_key()` (AES key
  encode/decode) are all storage-shape-agnostic.
- Key *prefixing* already has precedent: `S3direct\StreamWrapper::migrate($path, $prefix, ...)` does
  `Key = ($prefix ? "$prefix/" : '') . basename($path)` for backups. Video objects use the same
  pattern in the *same bucket*: `Key = 'smallpart-video/' . $video_hash` (keyed by `video_hash`, not
  `video_id`, so a re-upload - which always gets a fresh `video_hash` - naturally gets a fresh,
  non-colliding S3 object instead of needing overwrite semantics).
- `class_exists('EGroupware\Stylite\SmallPart\...')` guards in core `smallpart` code calling
  into EPL-only logic is an established pattern already used in `smallpart/src/Hooks.php:78` (checks
  for `vidopro`'s `Merge` class) - no new mechanism needed to keep this an EPL-exclusive feature while
  the check itself "lives in the regular smallpart code" (no transparent stream-wrapper interception
  needed; we don't need to support video access outside the smallpart UI).

## Decided design

### Schema (new columns on `egw_smallpart_videos`)

| column | type | purpose |
|---|---|---|
| `video_s3_flags` | smallint, default 0 | bitmask, same shape as `fs_s3_flags`: one bit per configured storage ("uploaded to storage N"), a `NOT_IN_FILESYSTEM` bit (local copy evicted), and a `DOWNLOADING` bit (see locking below) |
| `video_aes_key` | varbinary, nullable | raw AES key, same scheme as `fs_aes_key` - generated on first upload to S3 |
| `video_s3_lru` | timestamp, nullable | **new, not present in the VFS S3 SW** - set to `now()` on every "about to play" check (see below), NULL until first touch |
| `video_s3_lock_started` | timestamp, nullable | set when the `DOWNLOADING` bit is claimed; used for stale-lock timeout/reclaim |
| `video_s3_uploaded` *(open, see below)* | timestamp, nullable | fallback sort key for eviction when `video_s3_lru IS NULL` - needed because `video_date` doesn't track file-writes (see "Current architecture" above) |

The `video_s3_lru` idea is a real improvement over `S3\StreamWrapper` itself, whose own
`s3RemoveFromFilesystem()` comment admits *"we have no separate access-time"* and evicts purely by
`fs_modified` - meaning an old-but-popular VFS file gets evicted and re-fetched on every single
housekeeping sweep forever. The LRU column fixes that here (a popular old video's clock resets on
every real touch) - worth considering backporting the same idea into the VFS SW later, out of scope
for this ticket.

### Jobs (reusing `Api\Asyncservice`, same pattern as `S3\StreamWrapper::installAsyncJob()`)

- **Migration/sync** (frequent, e.g. every few minutes): scan for rows where `video_s3_flags`
  doesn't yet have all configured storage bits set (`_storageMaskAll()`), encrypt+upload via
  `_runAndResolveOnAll`, set the flag bits + `video_aes_key` if new. Once code+schema are deployed,
  this naturally migrates every existing video over time with no separate one-off migration step.
- **Housekeeping** (daily, off-peak, mirrors `s3RemoveFromFilesystem()`): evict local files that are
  fully uploaded to all storages **and** `COALESCE(video_s3_lru, video_s3_uploaded) < now() - $caching_time`,
  setting `NOT_IN_FILESYSTEM`.
- **Deletion**: direct/immediate (decided - no retention/undelete requirement for videos, unlike
  VFS). Video/course delete calls `_s3DeleteKeys()` across all storages synchronously. No
  `FLAG_TO_DELETE`/`s3ToDelete()`-style scheduled purge needed.

### Playback-time cache check (replaces the originally-considered nginx `error_page`/`X-Accel-Redirect` fallback)

Moving the "is it cached" check into something the student UI calls before trusting a `video_src`
avoids any nginx config change at all (none in `doc/docker/nginx.conf`, none needed in any on-prem
customer's nginx) - nginx's `alias` stays exactly as it is today, on every install.

There are two distinct call sites, found by tracing the actual player code in `smallpart/js/app.ts`:

1. **Normal per-video page load** - opening a specific video is a full etemplate request;
   `readVideo()`/`videoSrc()` run server-side at that exact moment
   (`content.data.video.video_src` arrives already baked into the response, consumed in
   `student_playVideo()`). The check can go directly inside `videoSrc()`/`readVideo()` server-side -
   zero new round-trip.
2. **Client-side video switching without a fresh server round-trip** - `app.ts:3327`
   (`ltiVideoSelection()`) shows a whole course's video metadata already sitting client-side in the
   etemplate content array-manager (`getArrayMgr('content').getEntry('videos')`); switching there
   calls `video.set_src(videos[video_id].video_src)` directly against already-loaded (possibly
   stale) data, no server call. **This path needs a dedicated ajax method** to confirm/refresh
   presence and touch the LRU before trusting the cached `video_src`.

Both call sites should funnel into one method on the new EPL class, not duplicate logic. Keep the
common case cheap: if the file is already local, just
`UPDATE egw_smallpart_videos SET video_s3_lru=now() WHERE video_id=?` and return (a few ms, no S3
touch, no decrypt). Only a genuine cold miss does real work. The ajax path (#2) should show a
distinguishable loading state for that case, since a multi-second synchronous re-download would
otherwise look like a hang right before playback.

### Concurrency: two students opening the same evicted video at once

Real correctness issue, not just wasted work: nginx serves straight off the final path with **zero
coordination** with our PHP process. Writing the downloaded bytes directly into
`<video_hash>.<type>` risks a concurrent nginx request reading a **partial/truncated file**
mid-download (e.g. a second tab that already has the old `video_src` cached, never calling the
check).

Fix, two parts:

1. **Atomic publish**: download+decrypt into a staging path (`<video_hash>.<type>.part`, same
   directory/filesystem) and `rename()` into the final name only once fully written - POSIX
   `rename()` is atomic on the same filesystem, so nginx only ever sees "not there" or "fully there".
2. **Claim via DB compare-and-swap, not filesystem locking.** Hosting's `$files_dir` is NFS, shared
   across pods/k8s nodes (confirmed) - `O_EXCL`/`flock()` have known historical reliability gaps on
   network filesystems, so the DB (already the single source of truth across nodes for everything
   else here) is the right place for the actual lock:
   - Claim: `UPDATE egw_smallpart_videos SET video_s3_flags = video_s3_flags | DOWNLOADING, video_s3_lock_started = now() WHERE video_id = ? AND video_s3_flags & DOWNLOADING = 0`.
     `rowCount() === 1` → won, download+decrypt to staging, `rename()` into place, clear the bit.
     `0` → lost, poll (cheap `SELECT` every few hundred ms) for the bit to clear, at which point the
     final file is guaranteed present (rename already happened) - no `filesize()` polling needed,
     and this correctly covers a stall during decrypt too, not just during download.
   - Stale-lock recovery (crashed mid-download): a waiter whose poll shows `video_s3_lock_started`
     older than an expected-max-download-time threshold (reuse the bandwidth-based formula
     `S3\StreamWrapper::s3Sync()` already has: `5 + size_mb/bandwidth`) re-claims with
     `AND video_s3_lock_started < now() - $timeout` in the same CAS, taking over from the dead
     process.

Two concurrent claims racing is harmless either way (DB `UPDATE ... WHERE flag=0` is atomic), so no
separate distributed-lock service is needed.

## Open questions (not yet decided)

1. **`video_s3_uploaded` column vs. `filemtime()`**: a dedicated column keeps the housekeeping query
   pure SQL; `stat()`-ing each candidate at scan time avoids a 4th column, costs a syscall per row at
   the small batch sizes (`LIMIT 10`-style) housekeeping already uses. Leaning toward the column for
   consistency with the rest of the design, not decided.
2. **Re-upload cleanup**: `updateVideo()` giving a new `video_hash` means a brand-new S3 key per
   re-upload - the old object becomes orphaned. Delete synchronously when swapping, or a periodic
   sweep for keys with no matching `video_hash` row? Not decided.
3. **Grace period for brand-new uploads**: should a just-uploaded video skip eviction eligibility for
   some minimum window even if `$caching_time` would otherwise apply? Lower priority now that the
   nginx-fallback latency concern is gone (a cold miss just costs one ajax round-trip, not a broken
   nginx flow), but still a possible UX smoothing.
4. **Multi-storage cost tradeoff**: confirmed wanted (like the VFS S3 SW), but not yet decided whether
   it should be forced-on or admin-configurable per the existing `s3_storages` config shape (which
   already supports N storages generically either way).

## Status

Design/discussion phase only - no code, no schema change, no implementation written yet.
