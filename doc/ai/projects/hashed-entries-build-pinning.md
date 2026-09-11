# Content-hashed rollup entries + per-document build pinning

Originally a scoping/design doc (deleted 2026-09-09 as "scratch, not a reference doc" once the design
landed - restored 2026-09-11 as a proper reference doc, since ticket #124112 showed there was still
follow-up work worth tracking here). The design section below is unchanged from Nathan's original;
[Implementation](#implementation) and everything after it is new.

## What this is actually for

Not for fixing a crash. The crash is already handled: `88bf63dd2f` catches the
`TypeError: Failed to construct 'HTMLElement': Illegal constructor` that comes out of a
build-graph mismatch and surfaces it as a "please reload" prompt instead of a blank tab and an
uncaught rejection.

The point of this project is that **the user is still forced into a reload that the rest of the
design deliberately set out to spare them**. There is no downtime window — rebuilds land while
users in other timezones are mid-workday — and the whole intent of the build-epoch mechanism (12h
drift threshold, notify once, main window only, never force) is that someone keeps working on the
build they already loaded until *they* choose to reload. The failure below takes that choice away.
The harm is the loss of control over *when*, not the `TypeError`.

That framing matters for review: a change that made the error handling nicer would not address this.

## The gap

Three defences exist against build drift in a live session. Two of them hold:

1. `window.egw_import()` dedup (`api/js/jsapi/egw_files.ts`, the `Files` constructor) — a *re*-import
   of a URL already seen this document, under a new cache-buster, resolves to the already-loaded
   module instead of fetching a second copy.
2. The build-epoch poll (`api/js/jsapi/egw.js`) — a quiet 15-minute poll that offers a reload once
   drift exceeds 12h.

The third case is not covered. Opening an app **not yet opened this session**, after a rebuild:

- The app's entry (`<app>/js/app.min.js`) is a key `egw_import` has never seen, so dedup correctly
  lets it load.
- That freshly-compiled entry carries a *static* import of a specific content-hashed shared chunk
  (`import {...} from '../../chunks/etemplate2-<hash>.js'`), baked in at build time. There is no
  cache-buster to strip — the hash *is* the filename — so this is a genuinely different file, not a
  stale reload dedup could recognise.
- Each such chunk inlines its own copy of Lit's `ReactiveElement`/`LitElement`. The custom element
  registry is still bound to the *first* copy's classes (guarded by `if (!customElements.get(...))`
  in `et2_core_widget.ts`), so constructing a widget from the second copy fails the browser's
  upgrade check.

Background, both live repros, and the rejected alternatives are in the memory file
`illegal_constructor_stale_chunk_mismatch.md`.

## The two halves

**Hashing entries does not fix anything on its own.** A newly-hashed entry still hard-references the
newest chunk. Hashing is only what makes an *old build addressable*: today `app.min.js` is a single
mutable path that gets overwritten in place, so there is no way to ask for yesterday's.

**Pinning is the fix.** Once a document resolves every entry through one build's manifest, an app
opened later in that document loads the entry that references the chunk *already resident*. Nothing to
collide, nothing to reload.

Both halves or neither.

## Why the enabling half already works

Entries are the only mutable thing left in an otherwise content-addressed graph:

- Chunks are already content-hashed, and old ones are still on disk under their own names.
- `chunks/` GC is 48h by **atime** (`rollup.config.js:28-32`) — a live session keeps its own chunks
  alive just by fetching them. Verified: 11+ coexisting versions of `chunks/etemplate2-*.js` on disk.
- Rollup already knows the entry-to-hash mapping in `generateBundle`; a manifest can be emitted
  beside `build-epoch.json` (written at `rollup.config.js:227`).
- `rollup.config.js:82-84` already carries the `// TODO: Hashed entries, when server supports` next
  to the commented-out `entryFileNames: '[name]-[hash].js'`.

Scale: 35 `app.min.js` entries plus `api/js/jsapi/egw.min` and `api/js/etemplate/etemplate2` = 37.
~4.1 MB of entry JS per build, ~12 MB including sourcemaps. `chunks/` is currently 172 files / 528 MB.

## Findings that changed the original scope

**`Framework::getImportMap()` has zero callers.** Not "wired but returning `[]`" — nothing anywhere
emits a `<script type="importmap">`. `Bundle::getImportMap()` feeds `Framework::getImportMap()`
(`api/src/Framework.php:1245`), which feeds nothing. `IncludeMgr::getImportMap()`
(`api/src/Framework/IncludeMgr.php:434`) likewise has no callers. Last touched by `31c70e013c` /
`3add958afa`, then left disconnected at both ends.

**An import map is not needed at all** (decided — see [Decisions made](#decisions-made)). The client never resolves entry URLs from a map
today — the server hands it *fully resolved* URLs, via `data-include` on `#egw_script_id` (page load,
`Framework::_get_js()`) and `Json\Msg::includeScript()` (ajax_exec). Both funnel through
`Bundle::js_includes()`, so a manifest lookup there covers most of the surface with **zero client
change**. The two remaining client sites are cheaply served by a `data-manifest` attribute on
`#egw_script_id`, which rides the existing popup bootstrap: `egw_open.ts:816` copies an explicit
allowlist `['data-url','data-app','data-epoch','data-include']`, so this is one word added to an
array. An import map in a `document.write`-derived `about:blank` popup would have to be injected
before the module script — doable, but fussy for no gain.

**Hashing would silently disable defence #1.** `egw_import`'s dedup keys on `removeTS(url)` — same
filename, different query string. Once the hash *is* the filename, two builds' `app.min` are
different keys and the dedup never fires again. Its key must become the *logical* entry name (reverse
manifest lookup). `removeTS` itself stays — it is still used for `#files`/`included()` tracking of
non-entry scripts and CSS (`egw_files.ts:176`, `:181`, `:291`).

**The server surface is larger than `js_includes()`.** Three more sites construct or probe the
literal path:

- `api/src/Framework.php:1726-1727` and `api/src/Etemplate.php:284-285` —
  `file_exists(EGW_SERVER_ROOT.'/'.$app.'/js/app.min.js')`, false once the file is hashed.
- `api/src/Framework.php:1163` — the `egw.min.js` `<script>` tag, hardcoded with its own
  `filemtime()`, bypassing `js_includes()` entirely.
- `api/src/Framework.php:177` also carries `/api/js/jsapi/egw.min.js` in a list.

**Three live cache-busting schemes collapse into one.** `filemtime` in `Bundle::js_includes()`
(`api/src/Framework/Bundle.php:54-58`, comment: "use cache-buster only for entry-points / app.js, as
the have no hash"), day-granularity `((new Date).valueOf()/86400000|0)` in `egw_json.ts:956`, and
hash-as-identity for chunks. A **fourth** exists at `api/js/etemplate/etemplate2.ts:696` but is
inside a `/* */` block — dead, and it has a units bug (`/86400`, i.e. 86.4s, not day granularity).
Delete it rather than migrate it.

## The work

**Build** — `entryFileNames` becomes a function flattening the input key's slashes into a
collision-free name across all 35 apps (`infolog/js/app.min` → `chunks/infolog-app.min-<hash>.js`).
Emit `build-manifest.json` (logical path → hashed path) from the existing `writeBundle` hook, which
already runs there for `build-epoch.json`. Small.

**Server** — one `Bundle::resolveEntry()` helper plus a manifest loader. `js_includes()` uses it
instead of appending `filemtime`; the two `file_exists()` probes and the hardcoded `egw.min.js` tag
go through it; `_get_js()` gains `data-manifest` (see below) and the build id. Contained, touching `Framework.php`,
`Bundle.php` and `Etemplate.php`. No session changes.

Because the lookup is keyed by *logical* path, every existing caller keeps passing logical paths and
never learns where the file physically lives. This indirection is required for hashing regardless of
where entries are written — which is what makes the location question cheap (see below).

**Client** — `egw_json.ts:956` (lazy `app.<name>` loader) and `egw_open.ts:844,848` (popup
`data-include` patching) resolve through the manifest instead of concatenating; `'data-manifest'`
added to the `egw_open.ts:816` allowlist; `egw_import`'s dedup key switches to the logical name.

**Fallback** — if a pinned entry is missing, resolve against the current manifest and let the
existing caught-error + reload prompt fire. That degrades exactly to today's accepted floor, which is
the right thing to say in the commit message.

## Not-installed and not-permitted apps

Neither a manifest nor an import map causes any download — both are passive lookup tables consulted
only when something already decided to load a file. What actually triggers an entry fetch is
unchanged by this project: `data-include`, `Json\Msg::includeScript()`, and the lazy
`applyFunc` loader, all of which are already permission-gated server-side. So hashing and pinning
introduce **no new eager fetching**, and an app a user cannot reach is still never downloaded.

There is a real issue, though, and it is disclosure rather than bandwidth. Rollup's
`addAppsConfig()` globs *directories on disk* containing `js/app.ts` or `js/app.js` — that is
"what is checked out", not "what is installed in this instance" and certainly not "what this user
may access". A verbatim manifest would therefore hand every client a list of every built
app, including ones that are installed-but-forbidden or merely present on disk. `data-include`
leaks nothing of the sort today, so this would be a genuinely new disclosure.

**Filter the map to the user's own apps when stamping it into the page**, against
`$GLOBALS['egw_info']['user']['apps']` (the permission-filtered list, intersected with installed
apps at `api/src/Session.php:1452`). There is an established precedent to copy: `Link::registry()`
already gates exactly this way at `api/src/Link.php:326-327` before the registry is sent to the
client. Filtering is close to free, follows an existing pattern, and shrinks the stamped map as a side effect — a typical user has a fraction of the 35 built apps.

Two consequences to handle rather than discover later:

- `resolveEntry()` must tolerate a miss. An installed app with no `app.ts`/`app.js` is legitimately
  absent from the manifest, and after filtering so is every app outside the user's list. A miss
  falls back to the literal unhashed path (or to `false` where the caller is one of the
  `file_exists()` probes), which is exactly today's behaviour. It must not be an error.
- A user granted access to a new app *mid-document* has no entry in the stamped map, so they take
  the fallback path and may get the reload prompt. Rare, and it degrades to the accepted floor.
- Server-side resolution needs no map on the client at all - only the two client-side concat sites
  do, which is why the stamped map exists and why it is filtered.

**Deliberate non-goal: do not add `<link rel="modulepreload">` for manifest entries.** A manifest
makes that a tempting two-line addition later, and it would do precisely the thing this section
says the design avoids — download every app's bundle up front, permitted or not.

## Decisions made

**Hashed entries go in `chunks/`.** No new top-level directory, no `.gitignore` change (`chunks/` is
already ignored at line 30), no change to the GC sweep at all.

Two rejected alternatives, recorded so they are not re-litigated:

- *Hashed in place* (`infolog/js/app.min-<hash>.js` next to the source) — nothing sweeps app source
  directories, so this needs a **new** deletion loop walking 35 of them. That is the one part of this
  change that can destroy something real if a pattern is wrong, and it would replace a GC that
  already works. It also breaks `.gitignore`: `*/js/app.min.js` (line 75) does not match
  `app.min-<hash>.js`, so every build would leave 30+ untracked files in `git status` until three new
  patterns are added.
- *A sibling `entries/` directory* — rejected on naming twice over. In rollup's own data model an
  entry **is** a chunk (`generateBundle` returns `OutputChunk` objects; entry-ness is an `isEntry`
  flag, and `entryFileNames`/`chunkFileNames` are two naming templates for one artifact type), so
  `chunks/` is not a misnomer. And in *this* codebase "entry" means a record — an InfoLog entry, a
  calendar entry — so the word is actively misleading here.

Do **not** rename `chunks/` itself as part of this. That would invalidate every live session's
resident chunk paths and force exactly the one-time reload for everyone that this project exists to
avoid.

**Pinning granularity: per document. Explicitly NOT per session.**

The pin must not survive a reload. We are telling users to reload at their convenience, so a reload
has to actually deliver the new build — a session-stored snapshot would persist across F5, serve the
same build again, and make the prompt a lie. The one action we ask the user to take would silently
do nothing.

Per document is also the technically correct unit: the colliding resource is the per-document custom
element registry, and a page reload creates a new document with an empty registry. That is precisely
the moment it is safe — and required — to re-pin to the newest build.

Concretely:

- On a **full page render**, the server resolves entries against the *current* manifest and stamps
  that build's id onto `#egw_script_id` alongside the existing `data-epoch`.
- For the rest of that document's life, every `ajax_exec` resolves against **that** build id, echoed
  back by the client on each request, so an app opened later loads the entry referencing the chunk
  already resident.
- A reload starts a new document, picks up the newest manifest, and the user genuinely gets the new
  build.

**Nothing is stored in the session.** An earlier draft of this doc proposed a login-time session
snapshot; it is wrong for the reason above and should not be revived.

Old manifests are kept as versioned files **in `chunks/`** (`build-manifest-<id>.json`), which means
the existing 48h atime sweep GCs them alongside the very chunks they describe, with no new cleanup
code. A build still in use keeps its own manifest alive by being read on each request.

**No import map. The manifest reaches the client as a `data-manifest` attribute.**

An import map's real advantage is that resolution moves to the browser, so client code keeps writing
logical specifiers and the concat sites become *correct* rather than rewritten. That barely pays off
here: there are only two such sites, and both already build their URL by concatenating a cache-buster
they have to drop anyway, so they are being touched either way.

The cost, meanwhile, lands on the most fragile path in this area. An import map must appear before
any module load in the document — fine in `_get_js()`, but `egw_open.ts` builds its popup as a
hand-written `about:blank` document, a path with documented history of silent failure (a cloned
script carrying a root-relative `src` that never fetched at all, found live 2026-09-06). Injecting a
correctly-ordered import map there is new fussy code in exactly the wrong place. The attribute is one
word added to an allowlist that already works (`egw_open.ts:816`).

Two lesser points in the attribute's favour: it fails legibly (a lookup miss you can log, rather than
a browser module-resolution error), and per-user filtering is natural because the map is generated
per render anyway.

**This would flip** if the codebase moves toward many more client-initiated dynamic imports of
first-party modules by logical name — an import map scales there and a hand-rolled lookup does not.
The two are not exclusive: the *manifest* is the durable artifact and how it reaches the client is a
swappable detail, so an import map can be added later as pure convenience once the popup bootstrap is
less delicate.

**The dead `getImportMap()` trio gets deleted in its own commit, not folded into this series.**
`Framework::getImportMap()` (`api/src/Framework.php:1245`), `Bundle::getImportMap()`
(`api/src/Framework/Bundle.php:80`) and `IncludeMgr::getImportMap()`
(`api/src/Framework/IncludeMgr.php:434`) have no callers and are dead today, independently of this
work. Bundling unrelated cleanup would muddy an already broad diff.

**No dev escape hatch.** Moving the pin to the document dissolved the concern: on a dev box under
`rollup -cw` an open tab holds its build until reloaded, and a reload picks up the newest one
immediately — which is what a watcher is for. Worth a sanity check during implementation; no config
flag planned. (This was only a live concern while the pin was session-scoped.)

## Risks and things that will bite

- **Defence #1 regressing silently.** If `egw_import`'s dedup key is not moved to the logical name,
  hashing removes an existing protection without any visible failure. Easy to miss in review.
- **Long-lived documents vs. the 48h GC.** The relevant window is now document lifetime, not session
  lifetime. An always-open framework tab that loaded all its apps on day one has not touched those
  chunks since, so on day three its pinned build may have been swept. It then takes the fallback and
  gets the reload prompt - the accepted floor, and no worse than today.
- **Disk.** ~4.1 MB of entry JS (~12 MB with maps) per build joins the GC pool, on top of the current
  528 MB.
- **Testing this bug class requires care.** Two concurrent `rollup -cw` watchers will rebuild off
  each other's output and make the referenced chunk hash oscillate with no source edits, corrupting
  any verification run. Use a one-shot `npx rollup -c` and confirm all entries agree on one hash
  before touching the browser. See the memory file's "Test methodology gotcha".

## Implementation

Nathan (`nathan`) picked this up and landed it in full, plus the crash-hardening work either side of
it. In commit order (see [Commits](#commits) for the complete, exact list):

1. **`f06e9a756b`** first hardened the *pre-existing* crash: guarded `customElements.define()` against
   redefining an already-registered tag (so a mid-session rebuild degraded to "keep the old copy"
   instead of an uncaught `DOMException`), fixed a `popups.push`/`popups.add` bug in popup/opener
   reconnect, and added the build-epoch marker + dismissible "reload for updates" notice this whole
   project builds on.
2. **`3ad40c67a5`** and **`88bf63dd2f`** closed the two gaps the original design doc called "defence
   #1" (entry re-import dedup) and the async side of crash-hardening: a rejected `et2_load` (or any
   other) response-plugin promise - eg. `etemplate2.handle_load()` building a freshly opened tab's
   widget tree - no longer disappears as a silent unhandled rejection; it's logged and the user is
   told to reload.
3. **`ebb3845754`** moved the "reload for updates" notice earlier: as soon as a stale-chunk
   redefinition is *skipped* by the `f06e9a756b` guard (not only once something later crashes because
   of it), so the user has already been offered a reload before hitting the harder failure.
4. **`d4a0c21dbd`** then removed that same `customElements.define()` guard again, reasoning that
   `88bf63dd2f`'s `.catch()` handlers now cover every promise chain the collision propagates through,
   so the guard was just delaying the crash to a "messier, deeper failure point" instead of preventing
   it. **Ticket #124112 (see below) shows this reasoning was only partly right** - some of those
   deeper failure points turned out not to be covered.
5. **`a77de36152`** did the actual hashing + pinning design from this doc: rollup entries
   (`app.min.js`, `egw.min.js`, `etemplate2.js`) hashed like chunks already are, each page gets a
   `window.egw_manifest` fixed at render (the "pin"), and `egw_import()`'s dedup key moved to the
   logical name so hashing didn't silently disable it - closing the exact "defence #1 regressing
   silently" risk this doc flagged above.
6. **`b4a8c8507f`** hardened the build side against two concurrent `rollup -cw` watchers clobbering
   each other's `build-epoch.json`/`build-manifest.json`.
7. **`9f933543ea`** fixed two integration bugs the hashing/pinning change exposed: a gitignored
   pre-rollup `<app>/js/app.js` surviving deploys and 404ing on its long-gone chunk imports (now
   upgraded to its `.min` sibling), and popups coming up blank because a popup adopted its opener's
   `egw_import()` closure and evaluated modules into the *opener's* realm - `egw_import()` moved into
   `egw.js` (loaded once per document) to fix that structurally.

The planning doc itself was deleted the same day as step 5 landed (`d0c5068cfa`, "scratch, not a
reference doc") - reasonable at the time, but it left `AGENTS.md`'s pointer to this file dangling for
two days until this restore.

## Ticket #124112 follow-up (2026-09-11)

Filed by Ralf relaying reports from Ingo and Stefan against `pole.egroupware.org`, the day after
`9f933543ea` landed. Three symptoms, investigated by Claude:

1. **`TypeError: Failed to construct 'HTMLElement': Illegal constructor`** opening `addressbook.index`
   (Ingo) and, in a case Ingo added later the same day, changing folders in `filemanager` (uncaught
   this time, not routed through the `88bf63dd2f` `.catch()` at all - worth a closer look, not yet
   done). This is the residual, by-design failure mode step 2 above already turns into a "please
   reload" prompt rather than a blank tab - expected given an in-progress rebuild, not itself a new
   bug. Matches the ticket's own "we no longer get white/completely stalled pages" observation.

   Investigating it did turn up a real, independent bug though: `egw_json.ts`'s `handleResponse()`
   logged the *wrong* plugin/response type for one of these rejections (Ingo's log says
   `type "css"` while the actual stack trace inside the same message is `Et2Template` construction
   from the `et2_load` plugin). Root cause: `res` and `plugin` were declared `var` inside the
   response-dispatch loop, so the async `promise.catch()` closure - firing after the whole loop had
   already finished - captured whatever `res`/`plugin` were left at by the *last* processed response
   item, not the one that actually failed. **Fixed** (see [Commits](#commits)): both changed to
   block-scoped bindings (`const res`, `let plugin`) so each iteration gets its own. One knock-on fix
   needed: the final `this.callback.call(this.context, res)` after the loop relied on `res` leaking
   out via `var`-hoisting to mean "the last response entry" - now reads
   `data.response[data.response.length - 1]` explicitly instead. Verified with the full
   `api/js/jsapi/test/*.test.ts` suite (499 tests, was 456 when this doc's design was written - Firefox
   + Chromium, all green) and `npm run typecheck` (no new errors).

2. **"CRMView object is missing"** opening a CRM view from addressbook/infolog (Stefan). Downstream
   symptom of the same collision as #1, not an independent bug: `addressbook/js/CRM.ts`'s
   `app.classes.crm = CRMView;` is a module-eval side effect, so if that chunk's import silently fails
   for the same reason as #1, `app.classes.crm` never gets set and `CRMView.view_ready()`
   (`CRM.ts:87-96`) logs `egw.debug("error", "CRMView object is missing")` and returns - console-only,
   no user-facing message, so it reads as an unrelated, permanent break rather than "reload will fix
   this too". Ingo's later filemanager addition (below) turned out to be the same family via a
   *different* app: filemanager's templates call `app.filemanager.change_dir(...)` from legacy inline
   `onclick` handlers, and `app.filemanager` is set the same way `app.classes.crm` is (a module-eval
   side effect an app's own chunk failing to load skips). **Fixed** - rather than patching `CRM.ts`
   specifically, `etemplate2.load()`'s generic `app.classes[appname]` check (the one place *every*
   app's instantiation goes through, `etemplate2.ts:722-725`) now shows the same "please reload"
   message alongside its existing debug warning, covering CRM, filemanager, and any other app hit the
   same way.

3. **Green + red "reload" messages stacking, reload only helping briefly.** Green is
   `egw_import.notifyUpdateAvailable()` (type `'info'`, rendered green/"success" by
   `EgwFrameworkMessage.TYPE_MAP`); red is the `.catch()`-driven "Please reload the EGroupware
   desktop" messages, always type `'error'`. `EgwFramework.message()` dedupes by a hash of the exact
   message *text* (`kdots/js/EgwFramework.ts:955-972`), so two different strings both stay on screen
   - not a bug, just a rough edge. **Fixed**: each of the red-message call sites (the two in
   `egw_json.ts` that are specifically about a build-staleness rejection, plus the new one in
   `etemplate2.ts` from item 2 above) now checks `egw_import.updateAvailableNotified` first and skips
   its own message if the green notice already covers this document - the debug log still always
   fires either way, so nothing is lost for diagnosis. `handleError()`'s generic ajax-failure message
   was deliberately left alone: it's not specific to a build-staleness rejection, so gating it on that
   flag would hide a real, distinct failure. "Reload only helps briefly" turned out to be unrelated
   to this project: Ralf confirmed only one JS rebuild landed on `pole.egroupware.org` that morning,
   before any of this was reported - not a multi-deploy window. A separate, unrelated (and
   since-resolved) infrastructure problem was independently causing requests for all sorts of files -
   not specifically JS/chunks - to 404 around the same time. A fresh reload *should* be fully
   self-consistent per the per-document pin, so the recurring symptom Ingo/Stefan saw is more likely
   that unrelated 404 incident than a residual pinning bug - the two problems overlapping in time is
   probably what made this look worse/more persistent than a single stale-chunk race would.

Ingo's filemanager addition also included an *uncaught* variant of #1 (`reactive-element.js:6 Uncaught
(in promise) TypeError...` with no `egw_debug.ts:387` frame, meaning it never reached the
`88bf63dd2f` `.catch()` at all) plus the `change_dir` symptom item 2 above now covers. There is only
one `new Et2Template(...)` call site in the codebase (`etemplate2.ts:746`, inside `load()`, reached
only via `handle_load()`'s `.catch()`-wrapped promise chain), so on paper this rejection should
always be caught the same way the addressbook one was - why this specific instance wasn't is **not
yet root-caused**; needs a live repro (not safe to guess-fix). Possibly a browser console-timing quirk
(rejection logged before the `.catch()` attaches) rather than a real gap, but unconfirmed.

## Status

Design implemented and live (steps 1-7 above). Three of ticket #124112's four findings fixed: the
`egw_json.ts` misleading-log-target bug (item 1), the silent `app.classes.X`-missing failure mode
(item 2, covers both the CRMView and filemanager cases), and the green+red message stacking (item 3).
"Reload only helps briefly" turned out to be a red herring for this project: Ralf confirmed only one
JS rebuild landed that morning, and an unrelated (now resolved) infrastructure issue was separately
404ing requests for all sorts of files at the same time - not a sign of a residual pinning gap. Still
open: root-causing the filemanager "Illegal constructor" that bypassed the `88bf63dd2f` catch net
entirely (uncaught, unlike the addressbook case) - needs a cleaner live repro before it can be
fixed rather than guessed at. Nathan or whoever picks this back up should start there.

## Commits

Chronological. `*` prefix on the subject means it went out in the user-facing changelog.

| Commit | Date (UTC-6) | Author | Subject |
|---|---|---|---|
| `f06e9a756b` | 2026-08-14 | nathan | `* Api: Don't let JS rebuilds crash open sessions` |
| `93768af9cf` | 2026-08-17 | nathan | `Ignore build epoch for git` |
| `3ad40c67a5` | 2026-09-02 | nathan | `Api: dedupe egw_import() to stop a mid-session rebuild crashing reopened tabs` |
| `88bf63dd2f` | 2026-09-04 | nathan | `Api: don't let a failed async response plugin crash a JSON request silently` |
| `ebb3845754` | 2026-09-08 | nathan | `Api: notify about a pending reload as soon as a stale-chunk redefinition is skipped` |
| `b3fb197f34` | 2026-09-08 | nathan | `Doc: scope hashed rollup entries + per-document build pinning` (this doc, first version) |
| `d4a0c21dbd` | 2026-09-09 | nathan | `Api: remove now-counterproductive customElements.define() redefinition guard` |
| `a77de36152` | 2026-09-09 | nathan | `* Api: hash rollup entries and pin an open document to its own build` |
| `d0c5068cfa` | 2026-09-09 | nathan | `doc: remove hashed-entries-build-pinning planning doc` (this doc, deleted) |
| `b4a8c8507f` | 2026-09-09 | nathan | `Api: guard rollup -cw's build-epoch/build-manifest writes against stale rebuilds` |
| `9f933543ea` | 2026-09-10 | nathan | `Api: fix hashed-entry 404s and blank popups` |
| *(ticket #124112 filed 2026-09-11 09:08 UTC)* | | | |
| `125468ea58` | 2026-09-11 | Claude | `Api: fix misleading plugin/type in a stale JSON-response-handler log message` |
| `f2833b1a47` | 2026-09-11 | Claude | `Doc: restore hashed-entries-build-pinning.md, document ticket #124112 follow-up` (this doc, restored) |
| `bdafdb7e89` | 2026-09-11 | Claude | `Api: tell the user to reload when an app's JS object never loaded` |
| `1fda0de2c1` | 2026-09-11 | Claude | `Api: don't stack a 2nd "please reload" prompt when one is already up` |
| *(pending)* | 2026-09-11 | Claude | `Doc: update hashed-entries-build-pinning.md for the item 2/3 fixes` (this doc, this update) |
