# pdf-player - pdfjs-dist library update

## Goal

`api/js/etemplate/CustomHtmlElements/pdf-player.ts` (used by smallpart/ViDoTeach to page through a
PDF like a video, via the legacy `et2_video` widget) used `@bundled-es-modules/pdfjs-dist@2.5.207-rc1`,
a years-stale ESM-wrapper mirror of Mozilla's real `pdfjs-dist`. Flagged initially because its
bundled `pdf.js` triggers a build-time "Use of eval" warning. **Done** - now on real `pdfjs-dist`
`5.4.624` (not the newest `6.3.289` - see "Why 5.4.624, not 6.x" below), zero `eval()` calls; see
"Status" for the full change list.

Initial investigation found:

* The wrapper package is effectively abandoned - last published 2023-05-12, latest `2.16.106`, one
  never-promoted `3.6.172-alpha.1` from the same day. Checked: `2.16.106` still has the exact same
  guarded, Node.js-only `eval("require")(...)` call - bumping the wrapper would not remove the
  eval warning.
* Mozilla's real `pdfjs-dist` ships native ESM (`build/pdf.mjs`), and has **zero**
  `eval()`/`Function()` calls in either the main bundle or the worker in any recent version -
  checked directly.
* Node engine requirement for `pdfjs-dist@6.x`'s own tooling (`>=22.13/24`) is a non-issue - this
  repo already runs Node 26.

**Decision (Ralf): add test coverage for pdf-player first, before touching the pdfjs-dist version**
- same approach as the `celtic/lti` update (see `smallpart-lti-library-update.md`), and for the same
  reason: a harness in place first turns "did the update break anything" into a fast, deterministic
  check instead of manual re-verification. This paid off directly: the harness is what caught the
  version-6-breaks-every-browser problem below, well before any live/manual check would have.

## Test harness (done)

`api/js/etemplate/CustomHtmlElements/test/pdf-player.test.ts` - 7 tests, exercising real pdf.js
(no mocking of pdf.js itself): initial render into a real `<canvas>`, page-count/duration reporting,
`nextPage()`/`prevPage()` navigation, the past-the-end `ended` state, `play()`'s interval-driven
auto-advance and self-pause, `disconnectedCallback()` cleanup (`pdf.destroy()`, interval cleared),
and the load-error path (`egw.message(..., 'error')`). Fixture PDFs are built inline as raw bytes
(no xref/startxref table, relying on pdf.js's own brute-force object-scan recovery path) rather than
checking in a binary fixture file.

Run: `npm run jstest -- api/js/etemplate/CustomHtmlElements/test/pdf-player.test.ts` (stable green,
verified across repeated runs).

### Bugs found and fixed alongside the harness (commit `2e8de67a8c`)

* **`src` property getter/setter were dead, broken code.** `get src()` returned `this.src` -
  infinite recursion / stack overflow. `set src()` called `.forEach()` on `this._wrapper.children`,
  an `HTMLCollection` (no such method - only `NodeList`, from `querySelectorAll()`, has it) -
  `TypeError`. Neither was ever reachable in production: `et2_video.ts` drives this element
  exclusively through the `'src'` **attribute** (jQuery `.attr('src', ...)` ->
  `attributeChangedCallback()` -> `__buildPDFView()` directly), never the property. Fixed (added a
  backing `_src` field, fixed the `HTMLCollection` iteration) and now covered by a regression test.

### Found, documented, NOT fixed (out of scope - pre-existing, real behavior)

* **No render-cancellation**: `__render()`'s `page.render()` call is fire-and-forget - `set
  currentTime()` neither awaits nor cancels the previous render before starting a new one. Calling
  `nextPage()`/`prevPage()` again before the prior render on the same `<canvas>` finishes throws
  pdf.js's own `"Cannot use the same canvas during multiple render() operations"`. Confirmed while
  writing the test (had to add explicit waits between page turns to avoid it) - a real user
  double-clicking next/prev fast enough could hit this in production today, independent of the
  library version. Worth a follow-up if anyone reports it live.
* **`playbackRate` getter/setter don't round-trip**: `set playbackRate(seconds)` stores
  `seconds*1000` (ms) internally; the getter returns that raw ms value rather than dividing back
  down. Never explicitly set/read anywhere found in this codebase.

### pdf.js (2.5.207, at the time the harness was first written) test-environment quirks

* `GlobalWorkerOptions.workerSrc` is set by `pdf-player.ts` itself to a page-relative path
  (`'node_modules/...'`, no leading slash) - works from a page served at the app root, 404s from a
  test file nested several directories deep. Test file overrides it to the root-relative form
  (`/node_modules/...`), matching how other test config in this repo handles other `node_modules`
  assets. Still needed after the version bump (now pointing at `pdf.worker.mjs`).
* `disableWorker: true` (per-`getDocument()`-call) was needed to make rendering deterministic under
  test with the OLD version, but is NOT sufficient by itself for a `getDocument()` call whose input
  fails to parse at all - that path still hits an internal check for a *configured* `workerSrc`
  before it gets far enough to skip actually using one. Need both `workerSrc` set AND
  `disableWorker: true`. **Moot now**: `disableWorker` was removed from `getDocument()`'s options
  entirely by the time of `5.4.624` - every load now goes through a real Worker, same as
  production, and this turned out to be simpler AND more reliable than the disableWorker-based
  workarounds below (no residual "Transport destroyed"/"Cannot use the same canvas" console noise
  after the version bump, unlike before it).
* `sinon.useFakeTimers()` must not be installed until AFTER `getDocument()`'s promise has resolved -
  with the OLD version's `disableWorker` mode, its main-thread "fake worker" message loop used a
  real `setTimeout()` internally, which froze forever under a fake clock installed too early. Kept
  this ordering after the version bump too even though it's no longer strictly necessary (a real
  Worker has its own independent event loop, unaffected by the main thread's fake timers) - no
  reason to test that assumption.
* A rejected `getDocument()` promise for a garbage-`data` document under the OLD version's
  `disableWorker` behaved inconsistently in ad-hoc testing (sometimes a clean fast rejection,
  sometimes an indefinite hang with zero console output) - turned out to be masking a real bug in
  the test's own `egw.message` stub, not a pdf.js quirk: the spy was attached to
  `window.egw().message`, but `pdf-player.ts`'s bare `egw.message(...)` call resolves to
  `window.egw.message` - a *different* property. `Object.assign()` copies the function by value at
  setup time, not as a live reference, so the spy never saw the real call. Fixed by spying
  `window.egw.message` directly; the garbage-`data` rejection itself was fine (and fast) all along,
  with or without `disableWorker`.

## Status: done (2026-09-19)

Real `pdfjs-dist` is now installed, `~5.4.624` (pinned range, see below - not the newest `6.3.289`).

### Why `5.4.624`, not `6.x` - the actual blocker

Bumping to `6.3.289` first: TypeScript typecheck and the test suite both ran, but the harness
immediately caught a hard runtime crash on **every** page render, in **both** Chromium and Firefox:

```
TypeError: this[#methodPromises].getOrInsertComputed is not a function
  at #cacheSimpleMethod (pdfjs-dist/build/pdf.js .../api.js) ...
```

`Map.prototype.getOrInsertComputed` is a genuinely bleeding-edge JS method - pdfjs-dist 6.x (and
5.5.207+) uses it unconditionally, with no fallback. Checked directly: Playwright 1.56.1's bundled
Chromium **141** and Firefox **142** (both current, non-legacy browser builds as of this check) do
**not** implement it. Node.js itself does (v26.5.1 here) - this is purely a browser-engine gap, and
since no shipping browser supports it yet, `6.3.289` would have broken PDF viewing for essentially
every real user, not just this test environment. This is *worse* than the eval() warning the whole
update was meant to fix.

Bisected the pdfjs-dist version history (downloading and grepping each tarball's `build/pdf.mjs`
for `getOrInsertComputed`) to find the cutoff: `5.4.394` through `5.4.624` are clean; `5.5.207`
onward all use it. `5.4.624` is the last release in the safe line, confirmed still zero-`eval()` and
otherwise structurally identical (native ESM, `build/pdf.mjs` + `build/pdf.worker.mjs`, same
`getDocument()`/`getPage()`/`render()`/`numPages` public API used by `pdf-player.ts`). Pinned with a
**tilde** range (`~5.4.624`, not caret `^5.4.624`) specifically so `npm update` can never silently
cross into `5.5.x` and reintroduce this - caret would have allowed that (same major version).

**Revisit this pin once real browsers ship `Map.prototype.getOrInsertComputed`** (check
caniuse/MDN, or just re-run this harness against the latest `pdfjs-dist` periodically - a version
that still crashes on real render is impossible to miss with it in place).

### Changes made

* `package.json`/`package-lock.json`: `@bundled-es-modules/pdfjs-dist` removed, `pdfjs-dist: ~5.4.624`
  added (pulls in `@napi-rs/canvas` as an optional, platform-specific, Node-only dependency - not
  used/bundled for the browser code path, harmless).
* `pdf-player.ts`: `import pdfjs from "@bundled-es-modules/pdfjs-dist/build/pdf"` (default import) ->
  `import * as pdfjs from "pdfjs-dist"` (namespace import) - v5/v6 only has named exports, no
  default. `workerSrc` updated from `pdf.worker.js` to `pdf.worker.mjs` (the worker is loaded as a
  real ES module now - `PDFWorker.create()` constructs it with `new Worker(url, {type: "module"})`).
  `pdf._pdfInfo.numPages` (reaching into a private field) -> `pdf.numPages` (the public getter,
  confirmed present in both old and new versions - no reason it was ever necessary to use the
  private one).
* `tsconfig.json`: added `"skipLibCheck": true`. Without it, typecheck fails with ~40 cascading
  errors *inside pdfjs-dist's own shipped `.d.ts` files* (`Uint8Array<ArrayBufferLike>` generic
  typing, `MapIterator`/`SetIterator`) - both require TypeScript 5.7+ lib definitions, and this repo
  pins TypeScript `^4.9.5` (a repo-wide dependency, not something to bump for one widget).
  `skipLibCheck` only skips checking `.d.ts` files' internal consistency; it does not weaken
  checking of this repo's own `.ts` source. The one pre-existing, unrelated `pdf-player.ts` type
  error (`_resolve()` call in `play()`, line ~378) was confirmed present before this change too
  (checked via `git stash`) - left alone, same as the ~5000 other pre-existing repo-wide TS errors
  tracked in `app-ts-modernization.md`.
* `pdf-player.test.ts`: removed all `disableWorker: true` usage (the option no longer exists in
  `getDocument()` as of this version - see the quirks list above); `workerSrc` override updated to
  `pdf.worker.mjs`.

### Verification

* `smallpart/tests`... n/a (this is an `api/` widget, not smallpart's own code).
* `npm run jstest -- api/js/etemplate/CustomHtmlElements/test/pdf-player.test.ts`: all 7 tests green,
  stable across 5+ repeated runs, on both Chromium and Firefox - and with noticeably *less* console
  noise than under the old version (the real-Worker path doesn't have the same fire-and-forget-
  render race the old `disableWorker` main-thread mode did).
* `npm run jstest -- --group api` (the full `api` app group, 155 files / 2502 tests): all green on
  both browsers - confirms the `skipLibCheck` tsconfig change and the dependency swap don't affect
  anything else in the app group.
* `npx tsc --noEmit -p .`: zero new errors from this change; only the one pre-existing, unrelated
  `pdf-player.ts` error remains.
* **Not done**: no live/manual check of an actual ViDoTeach course's PDF material in a real browser
  session. The jstest suite exercises real pdf.js end-to-end (real parsing, real `<canvas>`
  rendering) against a synthetic fixture PDF, which is why this bump was safe to make with fairly
  high confidence without it - but a live check against a real PDF, through the real `et2_video`
  widget, in a real course, is still the strongest possible confirmation and hasn't been done.

## Also researched: is pdf-player a "web component"?

Technically yes (native Custom Elements v1 + Shadow DOM, has been since it was written), but it
does **not** follow this repo's now-standard `Et2Widget`/Lit-based web-component conventions (see
`doc/etemplate2/pages/tutorials/web-component-authoring.md`): no `LitElement`, no `@customElement`
decorator, no reactive `@property()`, manual DOM/style construction in the constructor, no `et2-`
tag-name or `et2-`-namespaced custom events (`loadedmetadata`/`timeupdate` deliberately mimic
`HTMLMediaElement` instead). Same pattern as its sibling `multi-video.ts`. It's never instantiated
directly from an `.xet` template - only imperatively created by the legacy (also non-modern)
`et2_video` widget - so it never needs to participate in etemplate's own attribute-binding/widget
registry.

Estimated conversion effort:
* **Small (rewrite pdf-player.ts's internals in Lit, keep it a plain custom element)**: a few hours.
  Mechanical - `render()` template for the `<canvas>`+wrapper, `static get styles()` for the CSS,
  keep the `HTMLMediaElement`-mimicking property API as-is (would need re-verifying against Lit's
  property/attribute reflection rules). Existing tests would need `await el.updateComplete` added
  after property sets. Low value on its own - nothing currently needs pdf-player as anything other
  than a DOM node `et2_video` creates and pokes.
* **Medium/larger (promote to a real `et2-pdf-player` `Et2Widget`, usable directly from `.xet`)**:
  the above, plus rippling the tag-name/API change through `et2_video.ts` (itself legacy, not
  modern either) and renaming events to the `et2-` namespace. Real regression risk to a working,
  if legacy, production feature - not attempted, not currently justified by any stated need.

Not attempted as part of this update - independent of, and not required for, the pdfjs-dist bump
above.
