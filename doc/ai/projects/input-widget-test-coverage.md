# Et2InputWidget shared test suite: coverage + rollout

## Status (2026-09-10): ALL 6 PHASES DONE and green (1975 tests, `npm run jstest -- --group api`).

`Et2InputWidget/test/InputBasicTests.ts` is the shared contract suite meant to run on every input
widget (disabled/hidden, round-trip values, required, label/help-text/prefix/suffix slots). Before
this pass it only checked `readonly` and a rigid "value out === value in" round trip, was wired
into just 8 of the ~30+ widgets using the `Et2InputWidget` mixin, and 3 of those 8 had their call
commented out entirely because the suite was too rigid to pass (`Et2Number.test.ts`,
`Et2Description.test.ts`, `Et2VfsPath.test.ts`).

**Phase 1** - built `Et2Widget/test/WidgetSlotTests.ts` (`widgetSlotTests()`), a standalone helper
for the label/help-text/prefix/suffix `part=` convention, usable by non-`Et2InputWidget` widgets
too (proved out against `Et2Description`, which has no `readonly`/`required`/`get_value` at all).

**Phase 2** - redesigned `InputBasicTests.ts`: added `Disabled` and `Hidden` test groups (neither
existed before), made the round-trip/empty-value checks configurable (`expectedValue`/
`emptyValue`/`checkEmptyDisplay`), added a `skip` escape hatch, and wired in `widgetSlotTests()`
for the default `label`/`help-text` check. Verified against the 5 previously-passing call sites
(`Et2Textbox`, `Et2Date`, `Et2Email`, `Et2SelectBasic`, `Et2HtmlArea`).

**Phase 3** - fixed the 3 previously-broken call sites using the new options.

**Bugs found and fixed along the way** (the whole point of the exercise - a green suite with real
coverage surfaces these instead of hiding them):

1. **`hidden` did nothing on most input widgets** (Et2Textbox, Et2Date, Et2Select, Et2HtmlArea
   confirmed). A subclassed Shoelace component's own `:host {display: ...}` is author-level CSS
   that outranks the browser's native `[hidden]` UA rule at equal specificity. Fixed with
   `:host([hidden]) {display: none;}` in `Et2Widget.ts`'s shared base styles -
   `Et2MenuItem.ts:14-27` already hit and documented this exact issue, same fix.
2. **Et2Email's label/help-text never hid when empty.** It hand-builds Shoelace's form-control
   markup/class names but never got the "hidden unless has-label/has-help-text" base rule real
   Shoelace components ship with. Fixed in `Et2Email.styles.ts` (same pattern already used by
   `Et2HtmlArea.ts:168-175` for its own help-text).
3. **Et2Number's `getValue()` ignored `readonly`/`disabled` entirely** - its own override
   completely bypassed the base `Et2InputWidget` contract (`null` when readonly/disabled). Fixed
   in `Et2Number.ts`.
4. **Et2SwitchIcon's value didn't survive an unrelated re-render** (found via `Hidden > still
   returns its value`, Phase 4). `set value()` wrote straight to the child `<sl-switch>`'s
   `.checked`, bypassing `this.checked` - the property the template's own
   `.checked=${live(this.checked)}` binding re-asserts on *every* render. Any later re-render
   (eg. from `hidden` toggling) silently wiped the value back to `undefined`. Fixed by adding a
   real `checked` property and routing `value` through it, in `Et2SwitchIcon.ts`.
5. **Et2Listbox's `set value()` could silently no-op** (found via its round-trip test, Phase 5).
   Once `hasUpdated`, the `value` getter derives its result from which `<sl-menu-item>`s are
   currently checked in the DOM - so calling `this.requestUpdate("value", oldValue)` right after
   mutating `__value` reads `this.value` (for `oldValue`) *before* the DOM has re-rendered, gets
   back the stale pre-update result, and Lit's `hasChanged()` sees "no change" against the
   about-to-be-identical current read and skips the update entirely - the very update that would
   have made the getter's result change. Fixed by using a plain unconditional `this.requestUpdate()`
   in `Et2Listbox.ts`, which always schedules the render regardless of `hasChanged()`.

**Phase 4** - rolling `inputBasicTests()`/`widgetSlotTests()` out to widgets that already have a
test file but no call. Done so far: `Et2Colorpicker`, `Et2Switch`, `Et2SwitchIcon`, `Et2Textarea`,
`Et2TreeDropdown` (full `inputBasicTests()`); `Et2Filterbox`, `Et2File` (`widgetSlotTests()` only -
see below). Also fixed a test-env gap while at it: `Et2SwitchIcon.test.ts` never imported the raw
`sl-switch` custom element registration, so the internal `<sl-switch>` never upgraded (`shadowRoot`
stayed `null`) - same class of gotcha already called out in `Et2Email.test.ts`
("Et2Tag's editor is an et2-textbox; without this it never upgrades") and `Et2FileItem.test.ts`.

New `InputBasicTestOptions` fields found necessary during rollout, beyond what Phase 1-3 designed:
- The `skip` list now takes `"label"` and `"help-text"` *separately* (was one bucket) - Et2Switch/
  Et2SwitchIcon support help-text (a real Shoelace form-control part) but their "label" is their
  own text content (default slot), not a `form-control-label` part, like a checkbox.
- The `test_value !== ""` sanity guard in the `Required` group used to be `assert.isNotEmpty()`,
  which chokes on non-string primitives - boolean-valued widgets (`Et2Switch`) need `true` as their
  test value. Now a plain truthiness/definedness check.

**Widgets given `widgetSlotTests()` only, not the full `inputBasicTests()` contract** - their
`value` isn't a plain settable thing the generic round-trip/required contract fits naturally,
without building substantial fake-backend scaffolding disproportionate to this pass:
- `Et2Filterbox` - `value` only means anything once a real filter template with a live et2
  instance manager is attached; its own test file uses fake templates with no instance manager.
- `Et2File` - `value` is a `{tempFileName: FileInfo}` map produced by real Resumable upload
  interactions, not a plain settable string/object a user fills in directly.

### `.et2-label-fixed` check (added after Phase 4's first pass)

`widgetSlotTests()`'s "label" check now also verifies `.et2-label-fixed` (`Et2Widget.ts`) actually
gives the label part a fixed width, by adding the class and confirming `getComputedStyle(node)
.width` is `8 * fontSize` (the default `var(--label-width, 8em)`, no override). This is the whole
reason the `part=` convention matters, so it's worth checking the hook actually works, not just
that the part exists. New `skipLabelFixed` option (`inputBasicTests()`: `skip: ["label-fixed"]`) -
every use needs a real, documented reason:
- `Et2Description` - label part is `<slot part="form-control-label">` directly, `display:
  contents`, so `width` has no box to apply to at all.
- `Et2Filterbox` - overrides `--label-width` itself (`min(20rem, 30%)`, `100%` on narrow screens)
  for its own drawer layout. The hook works, it just doesn't use the generic 8em default.

**Measurement gotcha found writing this check:** use `getComputedStyle(node).width`, not
`node.getBoundingClientRect().width` - confirmed empirically they can disagree (by a different,
inconsistent amount per browser) for reasons unrelated to the `width` property itself (flex-layout
sizing noise). `getComputedStyle().width` reliably reflects exactly what the CSS rule set;
`getBoundingClientRect()` measures the actual rendered box, which is a different, noisier question.

Both still get real `label`/`help-text` (`Et2File`) or all 4 slots (`Et2Filterbox`, which declares
`label`/`help-text`/`prefix`/`suffix` in its own `hasSlotController`) part= coverage.

**Deliberately left alone, both phases:**
- `Et2VfsSelectDialog` (`Et2VfsDialog.test.ts`) - its whole test body is already commented out with
  "Cannot use automatic testing as Et2Dialog still uses old widgets, which break all the includes."
  Pre-existing, documented limitation, not something this pass changes.
- `Et2Tree` (`Et2Tree.test.ts`) - no label/help-text chrome at all (confirmed: no
  `form-control-label`/`@csspart` anywhere in `Et2Tree.ts`) and its existing tests are entirely
  about a `select_options`-mangling regression, not value entry. It's a rendering primitive
  `Et2TreeDropdown` (already covered) wraps for actual user-facing use - no natural fit for either
  helper.

**Phase 5** - widgets with *no* test file at all: 16 new test files, one per widget -
`Et2Checkbox`, `Et2CheckboxReadonly`, `Et2Diff`, `Et2DateDuration`, `Et2DateRange`,
`Et2HtmlAreaReadonly`, `Et2Password`, `Et2Hidden`, `Et2UrlFax`, `Et2UrlPhone`, `Et2LinkAdd`,
`Et2LinkEntry`, `Et2LinkTo`, `Et2VfsSelectButton`, `Et2Listbox`, `Et2DropdownButton`. Notable
per-widget quirks (beyond the bug fixes already listed above and the general technique notes
below):
- **"required" skipped on `Et2Diff` and `Et2LinkEntry`** - both have a structured "empty" value
  (a `"--- diff\n+++ diff\n"` header that's always prepended, and a `{app, id: ""}` object,
  respectively) that never equals `null`/`''`, so `isValid()`'s required check (built for
  primitives) can never catch a blank required field. Same underlying gap as `Et2DateDuration`'s
  `emptyNot0` case from Phase 3/4. Not fixed - not clearly wrong either (a blank diff or a blank
  link genuinely isn't the same concept as an empty string), just documented as a known contract
  gap for whoever next needs required-on-these to actually work.
- **`Et2HtmlAreaReadonly`/`Et2CheckboxReadonly` are permanently `readonly=true`** (set in their own
  constructor, never a toggle) - `expectedValue`/`emptyValue: null` and `skip: ["required"]`,
  since the base `getValue()` contract makes every round-trip trivially `null` regardless of
  `.value`. Still worth running (confirms the permanent-readonly contract holds), just not deeply
  informative about the widget's real rendering - covered instead by hand-written tests in the
  same file.
- **`Et2Hidden`**: `skip: ["disabled"]` - `:host` is unconditionally `display: none` by design (a
  genuine `<input type="hidden">`), so "disabled stays visible" (the assertion that exists
  specifically to distinguish disabled from hidden) doesn't apply; it's never visible regardless.
- **`Et2LinkTo` needed the deepest fixture setup** of the whole rollout: its `connectedCallback()`
  unconditionally does `this.getInstanceManager().DOMContainer.addEventListener(...)`, so
  `getInstanceManager` has to be stubbed on the *prototype* before `fixture()` connects it (an
  instance-level stub after connection is too late). Its `render()` also eagerly constructs a
  nested `Et2VfsSelectDialog` (via `Et2LinkPasteDialog`) which needs `egw().link_app_list`,
  `.langRequireApp`, `.getLocalStorageItem`, `.tooltipBind` on top of the usual `.lang`/
  `.tooltipUnbind`/`.image` - found by iterating on the actual `TypeError`s one at a time.
  `Et2VfsSelectButton` needed the identical egw() surface for the same reason (same nested dialog).

## Technique notes for `widgetSlotTests()` (worth knowing before touching it again)

- **Check the `part=` convention, not "is text visible somewhere".** Every widget that wants
  `.et2-label-fixed`-style CSS to work has to expose `part="form-control-label"` (help-text:
  `form-control-help-text`, also `prefix`/`suffix`). Checking for that specific part is a stronger
  signal than checking for visible text - it also verifies the widget hasn't silently lost the
  styling hook while still rendering plain unstyleable text.
- **`display: contents` breaks `checkVisibility()`.** `Et2Description` uses `<slot
  part="form-control-label">` directly as the part carrier - `display: contents`, so
  `checkVisibility()` is always false regardless of content (confirmed empirically, not per spec
  reasoning). `isPartShown()` skips the visibility check for `display: contents` nodes and relies
  on content presence instead.
- **`exportparts` is invisible to `querySelector`.** `Et2Select` (and `Et2Toolbar`) don't render
  the part themselves - they wrap a child Shoelace component and re-expose its part with
  `exportparts="form-control-label, ..."`. That's a pure CSS/`::part()` mechanism; the attribute
  is never copied outward, so a single-level `shadowRoot.querySelector` can't find it.
  `deepQueryPart()` recurses into nested shadow roots to find the real element.
- **The part may carry an inner `<slot>` rather than be one.** Shoelace's own prefix/suffix
  pattern is `<span part="prefix"><slot name="prefix"></slot></span>` - light-DOM content shows up
  via that inner `<slot>`'s `assignedNodes()`, not via `.textContent` on the outer span (slot
  assignment isn't reflected in an ancestor's `.textContent`).
- **A fresh `before()` fixture may not mean "no label".** Several widgets' own test fixtures set a
  label in the template as a realistic usage example (`Et2Date`, `Et2Select`). The "shows nothing
  when not set" check clears the property explicitly first rather than trusting ambient state.
- **Some widgets need a second update cycle to settle.** Confirmed on Et2Email: one
  `await element.updateComplete` after a property change isn't always enough. `settle()` awaits
  twice.
- **Never `assignedNodes({flatten: true})` for an emptiness check.** Confirmed empirically on
  Et2DateRange: when the part *is* the `<slot>` itself and its fallback content is a multi-line
  template (`<slot name="help-text" part="form-control-help-text">\n  ${this.helpText}\n</slot>`),
  `flatten: true` returns the slot's own whitespace-only fallback text nodes as "assigned" even
  though nothing was ever externally slotted - a false positive for "shows nothing when not set".
  Plain `assignedNodes()` (no flatten) only returns genuinely externally-assigned nodes;
  `isPartShown()` also filters out whitespace-only text nodes from the result for good measure.
- **Nested custom elements need an explicit registration import in the test file.** A widget's own
  module importing a class as a *value* (eg. `import {SlSwitch} from "@shoelace-style/shoelace"`,
  used only in a type position like a getter's return type) does not reliably register that
  custom element's tag - confirmed repeatedly (`Et2SwitchIcon`'s nested `<sl-switch>`,
  `Et2DateDuration`'s `<sl-select>`/`<sl-option>` and `<et2-number>`, `Et2Listbox`/
  `Et2DropdownButton`'s `<sl-menu-item>` and friends). Symptom: `<tag>.property` throws "is not a
  function", or a lifecycle method that expects the child to already be an upgraded custom element
  throws reading a property off `undefined`. Fix: import the concrete component path directly in
  the test file, eg. `import "@shoelace-style/shoelace/dist/components/switch/switch.js";` or
  `import "../../Et2Textbox/Et2Number";` - same class of gotcha already called out in
  `Et2Email.test.ts` ("Et2Tag's editor is an et2-textbox...") and `Et2FileItem.test.ts`.

## Rollout scope - all done

**In scope for `inputBasicTests()`** (real, user-settable value), all now covered: `Et2Checkbox`,
`Et2CheckboxReadonly`, `Et2Colorpicker`, `Et2Date`/`Et2DateDuration`/`Et2DateRange`, `Et2Diff`,
`Et2Email`, `Et2File` (slots only, see above), `Et2Filterbox` (slots only, see above),
`Et2HtmlArea`/`Et2HtmlAreaReadonly`, `Et2LinkAdd`/`Et2LinkEntry`/`Et2LinkTo`,
`Et2Switch`/`Et2SwitchIcon`, `Et2Textarea`, `Et2Textbox`/`Et2Hidden`/`Et2Number`/`Et2Password`, the
`Et2Url`/`Et2InvokerMixin` family (`Et2UrlEmail`/`Et2UrlFax`/`Et2UrlPhone`), `Et2VfsPath`/
`Et2VfsSelectButton` (`Et2VfsSelectDialog` excluded, see below), and the
`Et2WidgetWithSelectMixin` family (`Et2Select`, `Et2Listbox`, `Et2TreeDropdown`,
`Et2DropdownButton`; `Et2Tree` itself excluded, see below). Meaningful `Et2Select` subclasses that
override value handling (`Et2SelectAccount`, `Et2SelectCategory`, etc.) were not individually
audited in this pass - a reasonable next slice if this project resumes.

**Out of scope for `inputBasicTests()`, still candidates for standalone `widgetSlotTests()`** (no
real user-settable value): `Et2Description`/`Et2Label` (done - confirmed not an `Et2InputWidget` at
all), `Et2Button`/`Et2ButtonIcon`, `Et2Toolbar` (not attempted this pass - real candidates if
someone wants label coverage on them later).

**Fully out of scope:** `Et2Filterbox`'s internal Nextmatch header pieces (`ColumnSelection`,
`CustomFilterHeader`), `Layout/Et2Tabs`, `Et2Widget.ts` itself, `Et2VfsSelectDialog` (pre-existing
"cannot use automatic testing" limitation), `Et2Tree` (no label chrome, not real value entry).

## Phase 6 - done

Added a section to `doc/ai/testing.md` ("Every input widget's test file should call
`inputBasicTests()`") pointing at this doc and both test-helper files, so new widgets keep getting
covered without having to rediscover any of the above.
