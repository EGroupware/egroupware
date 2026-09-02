# App.ts Modernization

## Goal

Go through each app's `$app/js/app.ts` and modernize it:

1. Replace imports of legacy `et2_*` widgets with their web-component counterparts (e.g.
   `et2_selectbox`/`et2_widget_selectbox` -> `Et2Select`/`Et2Select/Et2Select.ts`). Check whether the
   widget is only ever used as a TS type (annotation/cast) - if so, import it with `import type {...}`
   instead of a value import.
2. Replace `var` with `const`/`let`.
3. Fix all TS compiler errors/warnings for the file (checked with `node_modules/.bin/tsc --noEmit -p
   tsconfig.json`, filtered to the file's own path).
4. Replace jQuery usage with native DOM methods.

This is file-by-file (one app at a time), not a single sweep. Each app gets its own entry below with
status and notable findings. Don't go beyond the app.ts file itself unless a fix requires a small,
clearly-scoped change elsewhere (e.g. an import path).

## Workflow used per file

1. `grep -n "\bvar \b"`, `grep -n "jQuery"`, and read the full file once to see the legacy-widget
   imports.
2. `node_modules/.bin/tsc --noEmit -p tsconfig.json`, then grep the output for the file's path, to get
   a baseline list of real TS errors (the whole-repo build has ~5000 pre-existing errors in other
   files - only the target file's own lines matter).
3. Fix imports (goal 1), `var`->`const`/`let` (goal 2), and jQuery (goal 4) mechanically.
4. Fix each TS error from step 2 individually (goal 3), then re-run tsc to confirm the file is clean
   and that no new errors were introduced.

## Status per app

| App | Status | Notes |
|---|---|---|
| infolog | done | See below |

## infolog/js/app.ts (done)

### Legacy widget imports replaced

- `et2_selectbox` (`et2_widget_selectbox`, a zero-member `class et2_selectbox extends Et2Select {}`
  compat shim) -> `import type {Et2Select} from ".../Et2Select/Et2Select"` (only used for 2 type casts).
- `et2_date` (`et2_widget_date`, same shim pattern, `class et2_date extends Et2Date {}`) -> `import
  type {Et2Date} from ".../Et2Date/Et2Date"` (only used for 1 type cast).
- `Et2Select` and `Et2Nextmatch` were already imported from their web-component paths, but as *value*
  imports even though the file only ever uses them as type annotations/casts - switched both to
  `import type`.
- `import {egw} from "../../api/js/jsapi/egw_global"` was removed entirely: `egw_global.d.ts` declares
  `var egw` inside `declare global {}`, so `egw` is an ambient global, not a real exported module
  member - the import was both wrong (no such export; TS2305) and unnecessary. Confirmed `mail/js/app.ts`
  already established this exact pattern (comment + no import) - copied the same comment here.

### TS errors fixed (14 total)

- **`this.et2._inst`** (3 call sites): `_inst` is a `private` field on `Et2WidgetClass`, not accessible
  from app.ts even though it "worked" at runtime pre-TS-strictness. `Et2Widget` exposes a public
  `getInstanceManager()` accessor that returns the exact same `etemplate2` instance - replaced all 3
  sites (`observer()`, `edit_actions()`, `submit_if_not_empty()`) with that call. (`calendar/js/app.ts`
  has the identical pre-existing `_inst` errors, not touched - out of scope for this pass.)
- **`this.nm` union type** (`Et2Nextmatch | et2_nextmatch` from `EgwApp.nm`): `et2_nextmatch` (legacy)
  lacks `updateComplete`/`columnPreferenceName`, which only exist on the web-component `Et2Nextmatch`.
  Cast once per method (`const nm = <Et2Nextmatch>this.nm`) in `filter_change()`/`filter2_change()`
  rather than casting at every property access.
- **0-arg calls to functions with required params**: `filter_change()` and `toggleEncrypt()` are both
  called with no arguments from `et2_ready()` as a deliberate no-op-safe pattern (the functions already
  null-check their params). Made the params optional (`filter_change(ev?, filter?)`,
  `toggleEncrypt(_event?, _widget?, _node?)`) rather than force callers to pass placeholder args.
- **`postSubmit()` needs a `button` arg**: `etemplate2.postSubmit(button)` only uses `button` if truthy
  (`if(button) this._set_button(button, values)`), so it's effectively optional at runtime but not
  declared with `?` in `etemplate2.ts` (shared file, left alone). Passed the already-fetched `action`
  select widget as `button` at the one infolog call site instead of widening the shared method's
  signature.
- **`app.stylite.onchangeResponsible`**: `app` is typed `{classes: any, [propName: string]: EgwApp}`,
  so `app.stylite` types as plain `EgwApp`, which doesn't know about EPL/stylite's extra methods (EPL
  is a separate, closed-source repo - see `feedback_epl_stylite_blind_spot`). Cast once to `<any>` and
  reused the local var, rather than casting at both the null-check and the call site.
- **`querySelector('#delete_sub')` returning bare `Element`**: assigning `.disabled` needs a
  `HTMLButtonElement`. Used the generic form `querySelector<HTMLButtonElement>(...)` instead of a cast,
  since it's a query call not an existing-value cast.
- **`timesheet_list()`'s `extras.link_id`**: was `false` (inferred `boolean`) then reassigned a
  `string` a few lines later - TS didn't actually flag this (no strictNullChecks/strict mode in this
  repo's `tsconfig.json`), but fixed it anyway with an explicit `<string|boolean>false` while touching
  the surrounding `var`->`const`/`let` cleanup, since it's a real latent type looseness.
- **Explicit generic type args on an untyped/`any` call** (`TS2347`): `getDOMNode()` returns `any`, and
  TS disallows `<T>` type arguments on a call whose callee type is `any` even though the call itself is
  unchecked. Fix is to type the *variable* (`const info_des_dom : HTMLElement = ...getDOMNode()`)
  instead of putting the generic on the untyped call.

### jQuery removed

- `jQuery("tr.hiddenRow").css("display", ...)` (2x) -> `document.querySelectorAll<HTMLElement>(...)
  .forEach(row => row.style.display = ...)`.
- `jQuery('#infolog-edit-print').bind('load'/'DOMSubtreeModified')/.unbind(...)` -> plain
  `addEventListener`/`removeEventListener` on the element from `document.getElementById(...)`. Also
  dropped the `var that = this` closure-capture pattern in favor of arrow functions (native
  `this`-binding). `DOMSubtreeModified` itself is a deprecated-but-still-implemented native DOM event,
  not a jQuery API - swapping the *binding* mechanism doesn't change the (already fragile) detection
  logic, so behavior is preserved as-is.
- `jQuery.map(action_id, val => val)` (coercing an array-like object to a real array) ->
  `Object.values(action_id)` (equivalent for this case: own enumerable values, order-preserving).
- `jQuery(dom).children('span').hide()` -> `dom.querySelectorAll<HTMLElement>(':scope > span')
  .forEach(span => span.style.display = 'none')` (`:scope >` is the native equivalent of jQuery's
  `.children(selector)` - direct children only, not all descendants).
- Two stale `@param {jQuery.Event}` JSDoc tags corrected to `{Event}` now that nothing jQuery-specific
  flows through those params.

### Not touched (out of scope)

- `calendar/js/app.ts` has the same `_inst`-private and `postSubmit()`-arg-count errors - left alone,
  this pass is infolog-only.
- No behavior changes were made beyond the mechanical DOM-API/type swaps above - e.g. the
  `DOMSubtreeModified`-based print-detection in `infolog_print_preview_onload()` is still exactly as
  fragile/deprecated as before, just no longer wrapped in jQuery.
