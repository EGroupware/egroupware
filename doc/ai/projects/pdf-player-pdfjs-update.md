# pdf-player - pdfjs-dist library update

## Goal

`api/js/etemplate/CustomHtmlElements/pdf-player.ts` (used by smallpart/ViDoTeach to page through a
PDF like a video, via the legacy `et2_video` widget) uses `@bundled-es-modules/pdfjs-dist@2.5.207-rc1`,
a years-stale ESM-wrapper mirror of Mozilla's real `pdfjs-dist`. Flagged initially because its
bundled `pdf.js` triggers a build-time "Use of eval" warning. Investigated (see chat history) and
found:

* The wrapper package is effectively abandoned - last published 2023-05-12, latest `2.16.106`, one
  never-promoted `3.6.172-alpha.1` from the same day. Checked: `2.16.106` still has the exact same
  guarded, Node.js-only `eval("require")(...)` call - bumping the wrapper would not remove the
  eval warning.
* Mozilla's real `pdfjs-dist` is at `v6.3.289` (2026), ships native ESM (`build/pdf.mjs`), and has
  **zero** `eval()`/`Function()` calls in either the main bundle or the worker - checked directly.
* Node engine requirement for `pdfjs-dist@6.x`'s own tooling (`>=22.13/24`) is a non-issue - this
  repo already runs Node 26.

**Decision (Ralf): add test coverage for pdf-player first, before touching the pdfjs-dist version**
- same approach as the `celtic/lti` update (see `smallpart-lti-library-update.md`), and for the same
  reason: a harness in place first turns "did the update break anything" into a fast, deterministic
  check instead of manual re-verification.

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

### pdf.js (2.5.207) test-environment quirks worth knowing for next time

* `GlobalWorkerOptions.workerSrc` is set by `pdf-player.ts` itself to a page-relative path
  (`'node_modules/...'`, no leading slash) - works from a page served at the app root, 404s from a
  test file nested several directories deep. Test file overrides it to the root-relative form
  (`/node_modules/...`), matching how other test config in this repo handles other `node_modules`
  assets.
* `disableWorker: true` (per-`getDocument()`-call) is needed to make rendering deterministic under
  test, but is NOT sufficient by itself for a `getDocument()` call whose input fails to parse at
  all - that path still hits an internal check for a *configured* `workerSrc` before it gets far
  enough to skip actually using one. Need both `workerSrc` set AND `disableWorker: true`.
* `sinon.useFakeTimers()` must not be installed until AFTER `getDocument()`'s promise has resolved -
  pdf.js's `disableWorker` mode schedules its main-thread "fake worker" message loop via a real
  `setTimeout()` internally, which freezes forever under a fake clock installed too early.
* A rejected `getDocument()` promise for a garbage-`data` document under `disableWorker` behaved
  inconsistently in ad-hoc testing (sometimes a clean fast rejection, sometimes an indefinite hang
  with zero console output) in a way not fully root-caused - the final error-path test instead spies
  on the module-global `egw.message` stub (fixed a real bug in that stub along the way: it must spy
  `window.egw.message`, not `window.egw().message` - `Object.assign()` copies the function by value,
  not as a live reference, and `pdf-player.ts`'s bare `egw.message(...)` resolves to the former).

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

Not started - test harness and the two production fixes above are the only code changes made so
far.
