# Writing timing-sensitive tests here

Guidelines distilled from an audit of `Et2Datagrid.test.ts`/`Et2Nextmatch/test/*.test.ts` for false
reassurance around the `requestAnimationFrame`/`setTimeout`-driven virtualizer, scroll, and debounce
behavior in `Et2Datagrid`/`Et2Nextmatch` (commit `303d783a0f` fixed a real stuck-placeholder-row bug
in this area). Read this before adding or reviewing a test that touches scrolling, the request
debounce/queue, row-count changes, or embedded/virtualized child grids in either component.

## Don't fake `requestAnimationFrame` or `setTimeout` for virtualizer/scroll/debounce behavior

Real timers are the correct tool for anything that renders a live grid. `sinon.useFakeTimers()` also
fakes `requestAnimationFrame`, so it will silently swallow any pending virtualizer rAF work - reserve
it for isolated `setTimeout`-only logic (e.g. a long-press-menu threshold) in a test that never
mounts a live grid. A loud 3s mocha timeout from a real hung `await` is a much safer failure mode
than a fake clock quietly no-oping the very behavior under test.

## Prefer a manual "release gate" over real fixed-delay timers for interleaving

When a test needs genuine out-of-order/interleaved resolution (e.g. a stale-fetch race, or UID
registrations resolving in a different order than the server declared), don't stagger it with real
`setTimeout(fn, 1)/(fn, 5)/(fn, 15)`-style delays - background-tab timer clamping and Chrome's
intensive throttling shrink that safety margin unpredictably. Instead store each pending callback (in
a `Map`/array) and have the test invoke them itself, in whatever order the test wants to prove. This
keeps the real interleaving the test needs while being both timer-free and throttle-proof.

## Never nest a retrying poll loop inside another retrying poll loop

A `for` loop that calls an already-internally-retrying helper (e.g. `waitForDatagridRow()`, itself up
to 20 rAF frames) multiplies the worst-case wait - 30 outer x 20 inner can exceed the 3s mocha
timeout before the test's own trailing `assert` ever gets a chance to produce a clear failure message.
The test then fails with an opaque "Timeout of 3000ms exceeded" instead of the message it was written
to give. Flatten to one bounded loop that checks the DOM/state directly instead.

## A `ResizeObserver` stub does not disable virtualizer's own resize handling

`@lit-labs/virtualizer` captures `window.ResizeObserver` into a module-scoped variable at *import*
time - before any test file's `before()` hook can run - and uses that captured reference for its own
internal `_hostElementRO`/`_childrenRO`. So installing a no-op `ResizeObserver` stub in a test file's
`before()` does **not** block virtualizer's own resize-driven range/layout behavior; it only affects
code that constructs a *new* `ResizeObserver` at runtime after the stub is installed - in this
codebase, that's specifically `Et2Datagrid`'s own `_embeddedChildGridResizeObserver` (the auto-resync
of an embedded child grid's reserved height on resize). Don't assume "this file stubs
`ResizeObserver`" means size/layout regressions can't be caught there - check what's actually
neutered before writing that into a comment or a review note.

## A shared word is not automatically a naming defect

`Et2Nextmatch.filters.test.ts`'s many "placeholder" hits looked like a naming trap at first glance,
but they're the real, correctly-tested empty-state-grid feature (`el.placeholder` text,
`placeholderActions`) - a legitimate terminology overlap with the *loading*-placeholder shimmer rows
this whole area is about, not a defect. Check what each usage actually is before renaming/restructuring
based on a keyword match.

## When a real test isn't feasible for a spot

Don't skip it silently:

1. Add a short comment at the relevant test/helper explaining what it does *not* cover and why.
2. Add a one-line docblock note at the top of the file's coverage summary, if it has one.
3. Point to where the behavior actually **is** covered, if it is (e.g. at a different layer/file), so
   the gap doesn't get "rediscovered" as unowned by a future pass.

## Hidden/backgrounded automation tabs produce fake "stuck" results

See `doc/ai/testing.md`'s "Hidden/backgrounded tabs produce fake UI bugs" section (canonical) - this
component is a repeat offender there. Not a risk under CI as configured (Playwright runs with
`concurrency: 1`, genuinely foregrounded), but if a test "hangs" locally, check
`document.hidden`/`document.visibilityState` on the automation tab before concluding anything is (or
isn't) broken.

## Even test-only changes deserve a live, focused-browser spot check

Automated headless runs here are real Chromium/Firefox via Playwright, but they only exercise the
mocked `dataProvider` fixtures these tests construct - which structurally cannot reproduce a real
request-timing race (two rapid real user actions racing a real fetch). Before calling test-authoring
work in this area verified, drive the core scenario once in a real, genuinely-focused browser tab
against a real dev instance, not just the test suite. See `doc/ai/testing.md`.
