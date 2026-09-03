# Et2* Widgets: `static get properties()` → `@property()` Decorators

## Goal

Every `Et2*`/webcomponent widget still declaring its Lit reactive properties the old way —

    static get properties() { return {...super.properties, foo: {type: String}}; }

— gets converted to real, `tsc`-visible class fields:

    @property({type: String}) foo = "";

matching the convention already used by `Et2File.ts`, `Et2Button.ts`, `Et2Avatar.ts`, etc. This is a
**compile-time-safety fix, not a runtime behaviour change**: Lit registers the identical reactive
property either way. The bug class this closes is real and already bit us once - see
`Et2Iframe.ts`'s `this.options.name` incident (`widget-migration-status.md`'s `iframe` row) - a
`static get properties()` file gives `tsc` nothing to check, so a typo'd or wrong property name in
`render()`/a setter/an external consumer is silently accepted as `any`.

No config change is required first: `tsconfig.json` already has `useDefineForClassFields: false` +
`experimentalDecorators: true` (the combination legacy Lit decorators need - if it were `true`, a
class-field initializer would shadow the decorator-installed accessor via `Object.defineProperty` at
construction time and silently break reactivity). `Et2File.ts` already proves this setting works
correctly in this codebase, so that classic Lit/TS footgun does not apply here.

## Inventory

**40 files** in `api/js/etemplate/` still declare a real `static get properties()`. (The initial
`grep -rl "static get properties"` hit 42; `Et2Tree/Et2Tree.ts` is a **false positive** - only a
stale comment (`// @ts-ignore from static get properties`) survives, the class is already fully
decorator-based; `Et2Datagrid/test/Et2Datagrid.test.ts` is a test fixture, not a widget.)

Of those 40:
- **35** are pure legacy (zero `@property` anywhere in the file).
- **5** are mixed (already partly converted, but a `static get properties()` remnant survives):
  `Et2InputWidget.ts`, `Et2Widget.ts`, `Et2Select/Et2Select.ts`, `Et2Select/Select/Et2SelectReadonly.ts`,
  `Et2DropdownButton.ts`.
- **4** are no-op overrides - `{...super.properties}` with nothing of their own added - pure dead
  code: `Et2Date/Et2DateTime.ts`, `Et2Date/Et2DateTimeOnly.ts`, `Et2DropdownButton/Et2DropdownButton.ts`,
  `Layout/RowLimitedMixin.ts`. These can simply be **deleted**, not converted.
- **744 of the repo's 4465 `npm run typecheck` errors (~17%)** are directly attributable to lines
  inside these 40 files - a concrete, measurable chunk of the compiler-error backlog this closes.
- **19 of 40** have no `test/*.test.ts` directory at all; the rest do.
- **15 of 40** have at least one property with a **hand-written `get`/`set` accessor colliding with
  the property name** - the case flagged in the task as needing care. Good news: this is not a new
  pattern to invent. The codebase already has a precedented fix, used 3 times in already-migrated
  files (`Et2Button.ts:51`, `Et2Avatar.ts:187`, `Et2Select/Et2WidgetWithSelectMixin.ts:88`): put
  `@property({..., noAccessor: true})` directly on the existing `get`/`set`, don't add a class field.

Outside `api/js/etemplate/`: no old-pattern custom elements in `kdots/`, `rocketchat/` (which has no
custom elements of its own at all), or any other in-repo app `js/` directory. The one exception is
**`smallpart/`** - its own nested git repo (own `.git`, no own `tsconfig.json`/`package.json`, but
covered by the root `tsconfig.json`'s `**/js/**/*.ts` include glob, and it does show up in
`npm run typecheck` output, under both `smallpart/js/...` and the `vendor/egroupware/smallpart/js/...`
copy). **Out of scope for this project entirely** - it's a separate nested repo on someone else's
release cadence, not just a separately-committed batch. Not touching it.

### Full table - `api/js/etemplate/`

| File | Own props | Collision(s) | Tests? | tsc errors now |
|---|---|---|---|---|
| Et2Widget/Et2Widget.ts *(base, 41 extenders)* | ~12 | class, parentId, statustext, data, actions | 2 files | 42 |
| Et2InputWidget/Et2InputWidget.ts *(base, 32 extenders)* | 9 | label (`noAccessor` already set) | 1 file | 60 |
| Et2Select/Et2Select.ts | 2 | - | 8 files | 63 |
| Layout/Et2Tabs/Et2Tabs.ts | 5 | value | none | 68 |
| Et2Textbox/Et2Password.ts | 2 | - | 3 files | 59 |
| Et2Button/ButtonMixin.ts *(mixin)* | 4 | image | 2 files | 35 |
| Layout/Et2Split/Et2Split.ts | 3 | orientation | none | 32 |
| Et2Favorites/Et2Favorites.ts | 3 | - | none | 32 |
| Et2Date/Et2Date.ts | 4 | placement | 4 files | 30 |
| Et2Select/Select/Et2SelectReadonly.ts | 1 | value, select_options | none | 29 |
| Et2Description/Et2Description.ts | 6 | value | 1 file | 25 |
| Et2Date/Et2DateRange.ts | 2 | - | 4 files | 25 |
| Et2Portlet/Et2Portlet.ts | 5 | - | none | 23 |
| Et2Link/Et2LinkList.ts | 2 | - | 3 files | 22 |
| Et2Url/Et2InvokerMixin.ts *(mixin)* | 3 | - | 1 file | 20 |
| Et2Switch/Et2Switch.ts | 2 | - | 2 files | 18 |
| Et2Iframe/Et2Iframe.ts | 7 | - | 1 file | 15 |
| Et2DropdownButton/Et2DropdownButton.ts | 0 (delete) | - | none | 13 |
| Et2Textarea/Et2Textarea.ts | 2 | width, height | none | 12 |
| Et2Date/Et2DateReadonly.ts | 1 | - | 4 files | 12 |
| Et2Checkbox/Et2CheckboxReadonly.ts | 5 | - | none | 12 |
| Expose/ExposeMixin.ts *(mixin)* | 3 | - | none | 11 |
| Et2Link/Et2LinkSearch.ts | 1 | - | 3 files | 11 |
| Et2Url/Et2Url.ts | 2 | - | 1 file | 10 |
| Et2Link/Et2LinkAppSelect.ts | 3 | onlyApp, applicationList | 3 files | 10 |
| Et2Button/Et2ButtonTimestamper.ts | 3 | - | 2 files | 9 |
| Et2Select/Select/Et2SelectNumber.ts | 5 | - | none | 7 |
| Et2Date/Et2DateSince.ts | 1 | - | 4 files | 7 |
| Et2Spinner/Et2Spinner.ts | 1 | - | none | 5 |
| Et2Link/Et2Link.ts | 7 | value | 3 files | 5 |
| Et2Avatar/Et2AvatarGroup.ts | 1 | - | none | 5 |
| Expose/Et2DescriptionExpose.ts | 2 | - | none | 4 |
| Layout/RowLimitedMixin.ts *(mixin)* | 0 (delete) | - | none | 3 |
| Et2Select/Select/Et2SelectCountry.ts | 1 | - | none | 3 |
| Layout/Et2Tabs/Et2TabPanel.ts | 1 | - | none | 2 |
| Et2Select/Select/Et2SelectState.ts | 1 | countryCode | none | 2 |
| Et2Date/Et2DateTimeOnly.ts | 0 (delete) | - | 4 files | 2 |
| Et2Date/Et2DateTime.ts | 0 (delete) | - | 4 files | 1 |
| Et2Nextmatch/Headers/CustomFilterHeader.ts | 2 | - | none | 0 |
| Et2Nextmatch/ColumnSelection.ts | 3 | value, columns, autoRefresh | 10 files | 0 |

*(Property/error counts are "roughly" per the task's own instruction - extracted mechanically, not
hand-verified line by line; good enough to plan batches, not to cite in a commit message.)*

## Risk assessment

**Not a pure no-op for every file.** Three real footguns, none of them the
`useDefineForClassFields` one:

1. **Getter/setter collisions (15 files).** A hand-written `get`/`set` for the same name as a
   `static get properties()` entry must become `@property({..., noAccessor: true})` placed directly
   on the accessor - not a separate class-field declaration, which would produce a duplicate member
   error or silently shadow the accessor. Precedented 3x already in this repo; not a new invention,
   but easy to get wrong by pattern-matching on `Et2File.ts` instead (which has no collisions).
   **Highest-risk subset**: collision **and** zero test coverage - `Et2SelectState.ts`,
   `Et2SelectReadonly.ts`, `Et2Split.ts`, `Layout/Et2Tabs/Et2Tabs.ts`. Consider a quick
   characterization test before converting these four, same philosophy as
   `et2select-searchmixin-removal.md` §8.
2. **Mixins** (`ButtonMixin`, `ExposeMixin`, `Et2InvokerMixin`, `RowLimitedMixin`) apply to multiple
   host classes - a mistake ripples across every consumer. `Et2Select/Et2WidgetWithSelectMixin.ts`
   already proves `@property()` works fine inside a mixin factory function, so this is a blast-radius
   concern, not a new technical risk.
3. **Base classes** `Et2Widget.ts` (41 direct extenders) and `Et2InputWidget.ts` (32 extenders) carry
   the most collisions and the most pre-existing tsc errors (42 and 60) - highest value, but do them
   **last**, once the pattern is proven on leaf widgets, and smoke-test broadly afterward.

**Explicitly out of scope, flagged for a separate pass:** 32 of these 40 files register with
`customElements.define("et2-x", X)` + `// @ts-ignore` instead of the `@customElement("et2-x")`
decorator `Et2File.ts` uses. (Of the rest: `Et2Select.ts` and `CustomFilterHeader.ts` already use the
decorator; the other 6 - `ButtonMixin`, `Et2InvokerMixin`, `ExposeMixin`, `RowLimitedMixin`,
`Et2InputWidget.ts`, `Et2Widget.ts` - are mixins/base classes that never call `customElements.define`
themselves, so the check doesn't apply to them.) Same modernization spirit, unrelated
compiler-safety win, don't bundle it into this migration - it changes a different thing (registration
timing/class identity) and would make each diff harder to review as "just the property fix."

One live bug found while checking this: `Et2Select.ts:1307-1310` still has a leftover
`customElements.define("et2-select", Et2Select)` call guarded by
`if (typeof customElements.get("et2-select") === "undefined")`, dating from before it was converted
to `@customElement('et2-select')` (line 98). Since the decorator registers the element the moment the
class statement executes, the guard is always false by the time this later code runs - it's dead code.
It's the only file among the 51 already-`@customElement`-converted files with this leftover. Worth a
one-line standalone cleanup, not part of this migration.

## `@state()` candidates found in passing

While inventorying properties for the conversion above, a few looked like they'd be better declared
`@state()` (internal reactive field, no attribute reflection, not meant to be set by template markup
or external/consumer JS) rather than `@property()` (real public API). Evidence gathered by grepping
for `.xet` attribute usage and external JS reads/writes, excluding each widget's own file and test.

**Real candidates - convert to `@state()` instead of `@property()`:**
- `Et2CheckboxReadonly.ts`'s `checked` - no `.xet` ever sets `checked=`, not in
  `getDetachedAttributes()`'s push list (only `value`/`class`/`statustext` are), no external write
  found; only read inside its own `render()`.
- `Et2InvokerMixin.ts`'s `_invokerLabel`, `_invokerTitle`, `_invokerAction` - already
  underscore-prefixed, zero `.xet`/external-app usage, set only by cooperating sibling subclasses
  (`Et2Url`, `Et2UrlEmail`, `Et2UrlPhone`, `Et2UrlFax`, `Et2Password`) constructing their own invoker
  button. Internal to the mixin family, not public API.
- `Et2Spinner.ts`'s `style` - the whole widget has zero consumers repo-wide, and the property shadows
  the native `HTMLElement.style` accessor, which is worth fixing independent of the `@state` question.

**Good precedent already in the repo**: `Et2Select.ts`'s `_tagsHidden`/`_optionsActivated` are already
correctly `@state()` - use that as the template when converting the above.

**Not a state question - dead/orphaned wiring, flag separately, don't fold into this migration:**
- `ColumnSelection.ts`'s `autoRefresh` - no external `.autoRefresh` set anywhere, and
  `footerTemplate()`, the only place that touches it, is never called from `render()`.
- `ExposeMixin.ts`'s `mediaContentFunction` - actually read via a different, never-assigned field
  (`this.__mediaContentFunction`); looks like broken wiring.
- `Et2Iframe.ts`'s `needed` - declared, never read anywhere in the class, no setter, no external
  reference; looks like copy/paste leftover from an input widget.

**Documented-but-currently-unused public config, keep as `@property()`:** `Et2DateRange.relative`/
`value`, `Et2Date.inline`, `Et2DateSince.units`, `Et2ButtonTimestamper.format`/`timezone`,
`Et2Url.trailingSlash`, `CustomFilterHeader.widgetOptions`, `Et2Portlet.editTemplate`,
`Et2Link.linkHook`/`targetApp`/`extraLinkTarget`/`breakTitle`, `Et2Iframe.seamless`/`fullscreen`/
`allow`. These have JSDoc, `set_X()` methods, or `reflect:true` signaling real public-API intent -
just no live caller in the current templates yet. "Unused today" isn't the same signal as "internal
bookkeeping," so leave these as `@property()`.

## Batching / order

**Batch 0 - delete the no-ops (near zero risk, ~30 min).**
`Et2DateTime.ts`, `Et2DateTimeOnly.ts`, `Et2DropdownButton.ts`, `RowLimitedMixin.ts` - their
`static get properties()` adds nothing over `super.properties`; just remove the override. Good
warm-up: proves the before/after `tsc`-diff workflow on trivial cases before anything else.

**Batch 1 - leaf widgets, no collisions, has tests (~half a day).**
`Et2DateRange`, `Et2DateReadonly`, `Et2DateSince`, `Et2ButtonTimestamper`, `Et2Switch`, `Et2Password`,
`Et2Url`, `Et2InvokerMixin`, `Et2LinkSearch`, `Et2LinkList`, `CustomFilterHeader`, `ColumnSelection`,
**`Et2Iframe`** (the file that started this - 7 props, no collision). Straightforward
mechanical conversion; the existing test suite is the regression net.

**Batch 2 - leaf widgets with a collision but test coverage (~half a day).**
`Et2Date`, `Et2Link`, `Et2LinkAppSelect`, `ButtonMixin`. Apply the `noAccessor` idiom; tests still
catch regressions.

**Batch 3 - leaf widgets, no test coverage (~half a day, budget extra time for manual smoke tests).**
`Et2AvatarGroup`, `Et2CheckboxReadonly`, `Et2Favorites`, `Et2Portlet`, `Et2Spinner`, `Et2Textarea`,
`Et2SelectNumber`, `Et2SelectCountry`, `Et2TabPanel`, `Et2DescriptionExpose`, `ExposeMixin`,
`Et2Description`. Manually exercise each widget in a real template (attribute set, JS set, readonly)
per `widget-migration-status.md`'s convention, since there's no automated net.

**Batch 4 - collision + no tests, the genuine highest-risk leaves (~half a day, write a
characterization test first).**
`Et2SelectState`, `Et2SelectReadonly`, `Et2Split`, `Layout/Et2Tabs/Et2Tabs`, `Et2Select.ts` itself
(huge consumer set even though 0 own collisions).

**Batch 5 - base classes, last (~1 day, wide manual smoke test).**
`Et2InputWidget.ts`, then `Et2Widget.ts`. Highest value (102 tsc errors between them) and highest
blast radius (every input widget / every widget respectively). Do only after Batches 1-4 have proven
the workflow; smoke-test broadly across apps afterward (a form save/load in any app exercises most of
`Et2Widget`'s properties).

## Verification, per file or per batch

1. `npm run typecheck 2>&1 | grep <file>` before/after - expect the file's own error count to hit 0
   (every property it references now really exists) and no *new* errors elsewhere (a converted base
   class can surface previously-hidden typos in subclasses - that's a real bug the migration found,
   fix it, don't treat it as a migration failure).
2. Run that widget's `test/*.test.ts` (`npx web-test-runner --group <name>` or the file directly) -
   must stay green. `@property()` registers the same reactive property Lit already had, so a
   passing suite before conversion should still pass after, except where §Risk item 1 applies.
3. For the 19 files with no test directory, manual smoke test in a real template: set the property
   via a `.xet` attribute, via JS (`widget.prop = x`), and check readonly rendering if applicable.
4. Track the running total against the 4465-error baseline (same convention as
   `et2select-searchmixin-removal.md`'s "60 → 117 tests" line) - expect roughly 744 fewer once all 40
   `api/js/etemplate` files are done, modulo new errors surfaced per point 1.

## Steps

1. Batch 0 - delete the 4 no-op overrides.
2. Batches 1-4 - convert leaf widgets and mixins, in the order above, one file (or tightly related
   pair, e.g. the `Et2Date*` family) per commit, `tsc` + tests green before moving on.
3. Batch 5 - `Et2InputWidget.ts`, then `Et2Widget.ts`, each as its own commit, wide smoke test after.
4. Delete this doc once the table above is empty (`et2select-searchmixin-removal.md` convention).
