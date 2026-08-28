# Et2Datagrid / Et2Nextmatch test-timing audit

A checklist of test-suite findings from auditing `api/js/etemplate/Et2Datagrid/test/` and
`api/js/etemplate/Et2Nextmatch/test/` after fixing a real stuck-placeholder-row bug (commit
`303d783a0f`). The goal of the audit: find tests that would give false reassurance about this bug
class - either because they can't actually exercise the code path they claim to, or because they
pass on both correct and broken behavior.

## The bug this audit follows up on (commit `303d783a0f`)

**Symptom**: after several rapid successive filter/date-range changes interleaved with scrolling
(e.g. set date range to "This year" -> scroll to bottom -> "This month" -> scroll to bottom ->
"This week"), Et2Datagrid could get permanently stuck showing shimmer/loading placeholder rows
past the real row count. `total`/`rows` end up correct (e.g. `total: 7, rows: 7`), but the DOM
still renders extra placeholder `<tr>` elements past that count, and they never resolve.

**Real root cause**: `@lit-labs/virtualizer`'s internal render range
(`Virtualizer._first`/`_last`, inside `virtualizer[virtualizerRef]._layout`) can get left stale
after several rapid successive `items` array shrinks (e.g. 246 -> 28 -> 7 rows from three quick
filter/date-range changes while scrolling) - the layout's incremental reflow path (triggered by
the plain `items` setter) doesn't always re-measure/re-clamp the range fully. A full-remeasure
reflow (`layout._scheduleLayoutUpdate()` followed by `layout.reflowIfNeeded()`) fixes it reliably.
Et2Datagrid already had a `_scheduleVirtualizerLayoutSync()` helper that nudges the virtualizer
(`virtualizer._hostElementSizeChanged()`) after data changes, but it was only ever called for
`embeddedVirtualized` (nested/child) grids, never for the top-level grid where this reproduced -
and even where it was called, `_hostElementSizeChanged()` alone doesn't force the needed full
re-measure.

**Fix** (`Et2Datagrid.ts`):
1. `_scheduleVirtualizerLayoutSync()` now also calls `virtualizer._layout._scheduleLayoutUpdate()`
   + `.reflowIfNeeded()` (both feature-detected, matching the existing defensive-access style for
   this undocumented `@lit-labs/virtualizer` internal).
2. That method is now called unconditionally after every `_fetchPage()` completes (previously
   gated behind `if(this.embeddedVirtualized)`), since a row-count shrink can happen on any grid,
   not just embedded ones.

**Secondary hardening** (`Et2DatagridRequestQueue.ts`, kept even though it wasn't the actual cause
of the reproducible bug): added a `MAX_DISPATCH_DELAY_MS` (500ms) maxWait to the chunk-fetch
dispatch debounce, so a queued request can't theoretically be deferred forever if newer chunk
requests keep re-arming the single shared debounce timer. This only matters while `total` is
still unresolved (the "don't request past total" guard is skipped in that state) - a real but
apparently much harder-to-hit-in-practice edge case than the virtualizer-range bug above.

**Methodology note - two false leads during investigation, both browser-automation artifacts**:
an early repro attempt (via CDP-driven browser automation) showed `total: null` forever,
`loading: false`, everything looking totally stuck. That specific manifestation turned out to be a
`document.hidden`-driven artifact - CDP-controlled tabs can report `document.hidden === true` while
still accepting clicks, which pauses `requestAnimationFrame` and fakes a "stuck UI" repro that does
NOT reproduce on a genuinely visible/focused tab (confirmed via `document.visibilityState`). A
near-identical false lead occurred in a prior session investigating a different bug in this same
component family (Vfs breadcrumb double-reload), also traced to rAF being fully paused in a hidden
tab. **Takeaway for any future "stuck placeholder/loading row" investigation in
Et2Datagrid/Et2Nextmatch**: check the live virtualizer's `_first`/`_last`/`_items.length` directly
(`grid._virtualize._first`, `._last`, `._items.length` via the `_virtualize` private getter) before
assuming it's the request-queue/fetch layer, and always verify `document.visibilityState`/
`document.hidden` on the automation tab before trusting any "stuck" repro - get a genuinely
focused tab before concluding a fix is/isn't needed.

Work through items one at a time, in the order listed (roughly priority order within each
section). Mark an item done inline (`- [x]`) with a one-line note on what changed, or - if a real
test genuinely isn't feasible for that spot - convert it to a **documented limitation** instead
(see "When a real test isn't feasible" below) rather than leaving it silently unaddressed.

## Ground rules learned from this audit

- **No test in either suite fakes/replaces `requestAnimationFrame` or `setTimeout` outright.**
  `sinon.useFakeTimers()` appears only in `Et2Nextmatch.actions.test.ts` for long-press-menu timers,
  which is the correct tool there. So the dominant hidden-tab failure mode across both suites is a
  loud 3s mocha timeout, not a silent false pass - that's the safer direction to fail in, but it
  still means a real bug in this area could currently hide behind either an all-green suite or a
  vague timeout that gets misdiagnosed as environment flakiness.
- **Don't rename/restructure based on a shallow keyword match.** The initial pass flagged
  `Et2Nextmatch.filters.test.ts`'s ~25 "placeholder" hits as a naming trap. On closer look those are
  all the *empty-state* placeholder feature (`el.placeholder` text, `placeholderActions` for the
  empty-grid context menu) - a real, distinct, correctly-tested feature that happens to share the
  word "placeholder" with the *loading-placeholder* shimmer rows this bug was about. That's a
  legitimate terminology overlap, not a defect. Item 16 below is scoped down accordingly - a small
  doc/comment clarification, not a test change.
- **CI runs Playwright with `concurrency: 1` per browser** (`web-test-runner.config.mjs`), so normal
  CI test runs are genuinely foregrounded. Anything marked "hang-fragile only" below is a risk for
  local/manual runs through a CDP-driven or backgrounded browser (i.e. exactly how we found the
  false leads), not for CI as configured today.

## When a real test isn't feasible

If an item can't get a real, throttle-proof test (e.g. it would require restructuring a whole file's
fixture setup for one edge case), do this instead of skipping it silently:

1. Add a short comment at the relevant test/helper explaining what it does *not* cover and why.
2. Add a one-line docblock note at the top of the file's coverage summary (if it has one).
3. Record it below with a `documented, not tested` resolution instead of `- [x]` alone, so the next
   audit doesn't waste time reproving the same gap.

---

## A. Coverage gaps for the fix itself (commit `303d783a0f`) - do these first

- [ ] **A1. No test file exists for `Et2DatagridRequestQueue`.** The `MAX_DISPATCH_DELAY_MS`/
  `_oldestQueuedAt` cap we added is unreachable from the existing suite: 9 tests in
  `Et2Datagrid.test.ts` force `_requestDispatchDelayMs = 0` (making the cap a no-op), and none
  re-queues continuously for 500ms to trigger the forced flush.
  Create `api/js/etemplate/Et2Datagrid/test/Et2DatagridRequestQueue.test.ts` covering: normal
  debounce/coalesce behavior, the `MAX_DISPATCH_DELAY_MS` forced-flush path (fake-clock-free, using
  real repeated `queueRequest()`/`scheduleProcessing()` calls timed against `Date.now()`), and
  `_oldestQueuedAt` resetting correctly on `flush()`/`clear()`.

- [ ] **A2. The virtualizer-reflow fix has no real assertion.** Only caller in tests is
  [Et2Datagrid.test.ts:4135](../../api/js/etemplate/Et2Datagrid/test/Et2Datagrid.test.ts) (`"shrinks
  stale embedded spacer height after final total is known"`), which asserts spacer *height* -
  something the pre-existing `_hostElementSizeChanged()` call already delivered. Reverting the new
  `_scheduleLayoutUpdate()`/`reflowIfNeeded()` block, or reverting the `_fetchPage()` call site back
  to `if(this.embeddedVirtualized)`-only, leaves every existing test green.
  Add a test on a **real, non-embedded** grid: mount with a large `total`, scroll into an unloaded
  range, `reload()` twice in quick succession with a provider resolving to a much smaller row count,
  then assert directly on `el._rowsBody[virtualizerRef]._layout._last < el._virtualRowCount()` and
  that no `[data-et2dg-placeholder]` element remains. Model the assertion style (not the fixture) on
  the timer-free layout test at
  [Et2Datagrid.test.ts:2868-2887](../../api/js/etemplate/Et2Datagrid/test/Et2Datagrid.test.ts) - real
  `_layout`/`_first`/`_last` access, no rAF polling loop needed if you drive the reflow synchronously
  the way that test does.

- [ ] **A3. No test does rapid successive reloads while scrolled, anywhere in either suite.** This is
  the actual repro shape (filter/date-range change -> scroll -> change again -> scroll again).
  `Et2Datagrid.test.ts:3197`'s `"renders later child rows and following parent rows through the
  shared scrollport"` is the closest existing test (real scroll, real debounce, asserts placeholders
  get replaced by real rows) but never *shrinks* the row count via reload while scrolled. Once A2
  exists this item may be satisfied by it - check before adding a separate test.

## B. Real false-reassurance risks (not just gaps - these can pass on broken behavior today)

- [ ] **B1. Vacuous negative assertion.**
  [Et2Datagrid.test.ts:4226](../../api/js/etemplate/Et2Datagrid/test/Et2Datagrid.test.ts) asserts
  `calls.length === 0` right after `await el.updateComplete` (a microtask) - before the debounced
  dispatch (a macrotask, `window.setTimeout`) could possibly have fired. It would pass even if the
  initial render wrongly queued a fetch. Fix: insert the same
  `await new Promise(r => window.setTimeout(r, 0))` idiom the test already uses 8 lines later, before
  this assertion.

- [ ] **B2. Row-upgrade-queue drain is only ever invoked synchronously.**
  [Et2Datagrid.test.ts:3752](../../api/js/etemplate/Et2Datagrid/test/Et2Datagrid.test.ts) and
  [:905](../../api/js/etemplate/Et2Datagrid/test/Et2Datagrid.test.ts) both call
  `_processRowUpgradeQueue()`/`_upgradeRenderedRows()` directly, skipping
  `scheduleRowUpgradeQueue()`'s own `requestAnimationFrame` scheduling
  (`Et2DatagridRowRenderer.ts:323`) entirely. Nothing in either suite would fail if that scheduling
  call were deleted - which is exactly the mechanism behind the prior session's false "stuck queue"
  lead. Add (or extend an existing test) to seed a row via the real `MutationObserver` path and wait
  on the real rAF chain rather than calling the drain method directly, so a regression in "does
  anything ever schedule the drain" would be caught.

- [ ] **B3. `Et2Datagrid.test.ts`'s `ResizeObserver` stub is benign only by import-order luck.**
  [Et2Datagrid.test.ts:265-309](../../api/js/etemplate/Et2Datagrid/test/Et2Datagrid.test.ts) installs
  a no-op `ResizeObserverStub` in a `before()` hook, but `@lit-labs/virtualizer` already captured the
  real `window.ResizeObserver` at module-import time (which runs before `before()`), so virtualizer
  instances are unaffected today. If anyone moves the stub earlier, or the virtualizer ever switches
  to lazy RO acquisition, ~40 embedded-height tests would start measuring a virtualizer that can
  never see a size change - and might still report green since `waitForEmbeddedHostHeight`'s boolean
  return isn't asserted everywhere it's called. Add a code comment at the stub's install site
  spelling out this load-order dependency, so a future refactor doesn't break it silently.

- [ ] **B4. Negative `fetchCalls === 0` asserts can't distinguish "never queued" from "queued but
  never dispatched."** [Et2Datagrid.test.ts:4435](../../api/js/etemplate/Et2Datagrid/test/Et2Datagrid.test.ts)
  and [:4486](../../api/js/etemplate/Et2Datagrid/test/Et2Datagrid.test.ts) rely on a single zeroed-delay
  macrotask to prove nothing was fetched, but `loadMore()`'s early-return guard is what actually makes
  today's result correct - the timing assertion is incidental. Add
  `assert.isFalse(el._requestQueue.isPendingOrQueued(key))` (timer-independent) alongside the existing
  `fetchCalls === 0` check.

- [ ] **B5. `Et2Nextmatch.filters.test.ts` is structurally blind to this whole bug class.** Zero rAF,
  zero setTimeout anywhere in the file; every filter test passes `{reload: false}` (see
  [:203-207](../../api/js/etemplate/Et2Nextmatch/test/Et2Nextmatch.filters.test.ts),
  [:244](../../api/js/etemplate/Et2Nextmatch/test/Et2Nextmatch.filters.test.ts),
  [:1110](../../api/js/etemplate/Et2Nextmatch/test/Et2Nextmatch.filters.test.ts), others), so
  `applyFilters()` is never tested together with a real `reload()`; no test applies more than one
  filter change; no test combines a filter change with scrolling. This is the file whose name most
  implies coverage of "what happens when filters change," so its blind spot here is worth closing
  even if A2/A3 land in `Et2Datagrid.test.ts` instead - at minimum, add one test here that removes
  `{reload: false}`, drives a real `reload()`, and asserts the child datagrid ends up with correct
  `total`/rows (a lighter-weight companion to A2/A3, at the Nextmatch level).

- [ ] **B6. `Et2Nextmatch.actions.test.ts` globally disables `ResizeObserver` for all 58 tests in the
  file** ([:23-26](../../api/js/etemplate/Et2Nextmatch/test/Et2Nextmatch.actions.test.ts),
  [:194-238](../../api/js/etemplate/Et2Nextmatch/test/Et2Nextmatch.actions.test.ts)), so no
  size/layout-driven behavior can run anywhere in it, despite several docblocks (lines 629, 812,
  1531, 1667-1670, 2520) saying "virtualized." Unlike B3, this one has no lucky import-order escape
  hatch documented yet - check whether it does, and if not, note in the file's top-level comment that
  virtualizer-range/layout regressions cannot be caught here by design, and belong in
  `Et2Datagrid.test.ts` instead.

- [ ] **B7. `Et2Nextmatch.filters.test.ts:1304`/`:1415` touch the fix's `embeddedVirtualized` branch
  without covering it.** Both stub `reload`/`fetchPage`, so the post-fetch block containing
  `_reconcileRowRenderState()` + `_scheduleVirtualizerLayoutSync()` never executes. Lower priority
  than B5/A2 - only worth revisiting if those don't end up giving equivalent coverage.

- [ ] **B8. The stale-fetch/query-signature race has no test at all.** Every `dataFetch` stub across
  `Et2NextmatchDataProvider.test.ts` resolves synchronously, so the interleaving that
  `_fetchPage()`'s `stale`/`discarded` handling exists for (a response arriving after the query
  changed) is never exercised. The file already has the right technique for this - the manual
  `releaseFetch` gate pattern used at
  [:428](../../api/js/etemplate/Et2Nextmatch/test/Et2NextmatchDataProvider.test.ts) and
  [:836](../../api/js/etemplate/Et2Nextmatch/test/Et2NextmatchDataProvider.test.ts) - reuse it: start a
  fetch, change the query/filters before releasing it, release it, and assert the stale response was
  discarded (rows/total unchanged, `console.warn("Stale fetch discarded", ...)` fired or not merged).

## C. Lower-priority / documentation-only items

- [ ] **C1. `Et2NextmatchDataProvider.test.ts:148`'s out-of-order-resolution test uses real 1/5/15ms
  `setTimeout`s.** Correct technique (fake timers would weaken it - it needs genuine interleaving),
  but background-tab clamping (timers round up to ~1000ms) shrinks its safety margin from ~200x to
  ~3x against the 3s mocha timeout, and Chrome's intensive throttling (tab hidden 5+ minutes) would
  fail it outright. Consider replacing the three fixed delays with the same manual `releaseFetch`
  gate pattern used elsewhere in this file (see B8) to make it timer-free and throttle-proof without
  losing the interleaving it's testing for.

- [ ] **C2. `Et2NextmatchDataProvider.test.ts:530`'s docblock over-promises.** The comment block
  (lines 512-529) discusses virtualizer-hidden behavior and 5-minute cache eviction in detail: the
  test itself only asserts a call count. Either trim the docblock to what's actually asserted, or add
  the assertion the docblock implies.

- [ ] **C3. Over-generous retry loops turn assertion failures into 3s timeouts instead of clear
  failure messages.** `Et2Datagrid.test.ts` lines
  [951-960](../../api/js/etemplate/Et2Datagrid/test/Et2Datagrid.test.ts),
  [3278-3287](../../api/js/etemplate/Et2Datagrid/test/Et2Datagrid.test.ts),
  [3980-3985](../../api/js/etemplate/Et2Datagrid/test/Et2Datagrid.test.ts),
  [4137-4141](../../api/js/etemplate/Et2Datagrid/test/Et2Datagrid.test.ts). Lower priority - a
  diagnosability nice-to-have, not a false-reassurance risk (these fail loudly, just unhelpfully).

- [ ] **C4. Document the rAF/hidden-tab hang risk once, near the shared test helpers.** ~50 rAF
  awaits plus the 4 polling helpers in `Et2Datagrid.test.ts` (lines 63-127) and
  `Et2Nextmatch.actions.test.ts`'s `waitForRenderedDatagridRow()` (lines 92-105) will hang and hit the
  mocha timeout if ever run in a hidden/backgrounded tab. Not a risk under the current CI config
  (`concurrency: 1`, genuinely foregrounded), but add a short comment at each helper's definition so
  the next person who sees one of these tests "hang" locally checks `document.hidden` before assuming
  a product regression - this is exactly what fooled us twice this session.

## D. Reviewed, no action needed

Recorded so a future audit doesn't re-check these:

- `Et2NextmatchColumnPreferences.test.ts`, `ColumnSelection.test.ts`, `CustomfieldsHeader.test.ts`,
  `Et2Nextmatch.compat.test.ts`, `Et2Nextmatch.refresh.test.ts`, `Et2Nextmatch.rowStylesheets.test.ts`
  - synchronous or microtask/`updateComplete`-only, no rAF/setTimeout/scroll involvement.
- `Et2DatagridColumnManager.test.ts`, `Et2DatagridColumnPreferences.test.ts`,
  `Et2DatagridColumnState.test.ts`, `Et2Datagrid.rows.test.ts`, `Et2Datagrid.templateHandlers.test.ts`,
  `Et2Datagrid.selection.test.ts` - same; `selection.test.ts`'s "recycling" tests simulate it via
  direct `_renderVirtualRow()` calls into a scratch `<tbody>`, deliberately with no real
  scrolling/timers, which is the right design for what they test.
- `Et2Datagrid.rowHydration.benchmark.ts` - filename doesn't match the `*.test.ts` glob, isn't run by
  CI at all; not in scope for this audit.
- The three `sinon.useFakeTimers()` long-press-menu tests in `Et2Nextmatch.actions.test.ts` (around
  lines 1986, 2124, 2199) - fake timers are the correct tool for testing a `setTimeout`-scheduled
  long-press threshold. Just don't extend this pattern to any test that renders a live grid, since
  sinon's fake clock also fakes `requestAnimationFrame` and would silently swallow any pending
  virtualizer rAF work from an earlier test in the same file.
- **Item 16 from the original pass (`Et2Nextmatch.filters.test.ts` "placeholder" hits)** - not a
  defect. `placeholder`/`placeholderActions` there are the real empty-state-grid feature (text +
  context-menu actions when a list has zero rows), correctly tested. Distinct from the loading-row
  `dg-row-placeholder`/`data-et2dg-placeholder` shimmer rows this whole audit is about - a
  terminology overlap, not a naming mistake. No test change needed; if it's ever confusing in
  practice, a one-line clarifying comment at the top of the "placeholder" describe block would be
  enough.
