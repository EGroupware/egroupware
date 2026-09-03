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
5. Replace `egw.json(...).sendRequest()` with `egw.request(...)`, **except** for a genuinely
   synchronous call (`sendRequest(false)`, e.g. an `onbeforeunload`/window-close handler that can't
   wait for an async round trip) - those must stay on `egw.json(...).sendRequest(false)`, since
   `egw.request()` is always async. See `doc/ai/modernization.md`'s note on `egw.request()`/
   `egw.jsonq()`/`egw.json(...).sendRequest()` for the full picture (incl. why `egw.jsonq()` is a
   *third*, non-interchangeable option - queued/bundled, and never for a handler that writes session
   state - not part of this specific goal, but don't reach for it as a swap-in here either).
6. Replace `function(...) {...}` expressions and closures (`var self = this` / `var that = this`)
   with arrow functions, wherever the enclosing scope's `this` should flow through naturally. Skip
   this where the code genuinely needs its own dynamic `this` (an explicit `.call(otherThis, ...)`/
   `.apply(...)`, or a callback contract that depends on being invoked as a method to get its `this`).

This is file-by-file (one app at a time), not a single sweep. Each app gets its own entry further down
with status and notable findings. Don't go beyond the app.ts file itself unless a fix requires a small,
clearly-scoped change elsewhere (e.g. an import path).

## Status per app

| App | Status | Notes |
|---|---|---|
| mail | done | Modernized in earlier, separate sessions (not part of this doc's pass) - see `project_mail_ts_cleanup`/`project_mail_jquery_removal` memory. `var`/jQuery-free, 0 TS errors, all legacy `et2_*` imports already gone. |
| infolog | done | See below |
| timesheet | done | See below |
| addressbook | done | Large file (1826 lines, 98 `var`, 12 jQuery uses, 31 TS errors) - see below. Also covers `addressbook/js/CRM.ts` (the CRM-sidebox view class `app.ts` loads via `import "./CRM"`) as a judgment-call companion-file inclusion, not a separate request. |
| tracker | done | 558 lines, 3 `var`, 2 jQuery uses, 12 TS errors - see below. `tracker/` is its own nested git repo (see note below), not part of the main `egroupware` repo - commit/push separately. |
| status | done | 848 lines, 0 `var`, 3 jQuery uses (incl. 2x `jQuery.extend`), 7 TS errors - see below. `status/` is also its own nested git repo - same note. |
| admin | done | Largest file in this series (2227 -> 2330 lines), ~96 `var`, 21 jQuery uses, 14 TS errors, 13 `sendRequest()` sites, 17 `function(`/`self`/`that` closures - see below. |
| developer | done | Small (57 lines). `developer/` is its own nested git repo. |
| esyncpro | done | Small (77 lines). `esyncpro/` is its own nested git repo. |
| aitools | done | Small (92 lines). `aitools/` is its own nested git repo. Note: that repo's working tree also has an unrelated stray change to `src/Hooks.php` (a commented-out trigger check in `notifyAll()`) - Ralf's own in-progress work, not part of this pass, not touched. |
| bookmarks | done | 218 lines. `bookmarks/` is its own nested git repo. |
| collabora | done | Largest of this batch (961 lines). `collabora/` is its own nested git repo. |
| filemanager | done | `filemanager/js/app.ts` (19 lines) was already fully modern - just an import + `app.classes.filemanager` registration, nothing to change. `filemanager/js/filemanager.ts` (the real app-controller file it delegates to) is also done - see below. |
| resources | done | 159 -> 160 lines, main repo. 0 `var`, 1 jQuery use, 4 TS errors, 0 `sendRequest()`, 1 `function(){}.bind(this)` - see below. |
| webauthn | done | 161 -> 163 lines. `webauthn/` is its own nested git repo. 0 `var`/jQuery/`sendRequest()`, 4 TS errors, 1 `function(){}` - see below. |
| guacamole | done | 109 -> 107 lines. `guacamole/` is its own nested git repo. Already 0 `var`/jQuery/`sendRequest()`/`function(){}`; only the ambient-global import/redundant `declare global` cleanup and 2 TS errors needed fixing - see below. |
| rag | done | 72 -> 73 lines. `rag/` is its own nested git repo. Already 0 `var`/jQuery/`sendRequest()`/`function(){}`; only the ambient-global import and 1 TS error needed fixing - see below. |
| preferences | done | 62 -> 62 lines, main repo. Already 0 `var`/jQuery/`sendRequest()`/`function(){}`; only the ambient-global import, a leftover unnecessary `@ts-ignore`, and 1 TS error needed fixing - see below. |
| openid | done | 33 -> 34 lines. `openid/` is its own nested git repo. Already 0 `var`/jQuery/`sendRequest()`/`function(){}`; only the ambient-global import and 1 TS error needed fixing - see below. |
| projectmanager | done | `projectmanager/js/app.ts`, 1239 -> 1315 lines. `projectmanager/` is its own nested git repo. 11 `var`, 14 jQuery hits, 12 `function(){}`, 3 `sendRequest()`, 10 TS errors - see below. |
| invoices | done | `invoices/js/app.ts`, 828 -> 837 lines. `invoices/` is its own nested git repo. Already 0 `var`/jQuery/`sendRequest()`/`function(){}`/`self`/`that`; only legacy-widget-import cleanup and 13 TS errors needed fixing - see below. |
| rocketchat | done | `rocketchat/js/app.ts`, 626 -> 669 lines. `rocketchat/` is its own nested git repo. 3 `var`, ~14 jQuery hits, 4 TS errors, 4 `egw.json(...).sendRequest()` sites, 8 `function(){}`/`self` closures - see below. |
| news_admin | done | `news_admin/js/app.ts`, 438 lines. `news_admin/` is its own nested git repo. 36 `var`, 9 jQuery hits, 11 TS errors, 0 `sendRequest()`, 4 `function(){}`/1 `var that` closure - see below. |
| policy | done | `policy/js/app.ts`, 389 -> 403 lines. `policy/` is its own nested git repo. Already 0 `var` (all `let` already); 5 jQuery hits, 4 TS errors, 5 `sendRequest()` sites, 13 `function(){}`/4 `var self` closures - see below. |
| importexport | done | `importexport/js/app.ts`, 325 -> 336 lines, main repo. 10 `var`, 13 jQuery hits (incl. a side-effect `import "jquery.min.js"`), 12 TS errors, 0 `sendRequest()`, 2 `jQuery.proxy(function(){...}, this)` closures - see below. |
| stylite | done | `stylite/js/app.ts`, 1054 -> 1099 lines. `stylite/` is its own nested git repo. 2 `var`, 8 jQuery hits, 20 TS errors, 2 `egw.json(...).sendRequest()` sites, 31 `function(`/5 `self`/`that` closures - see below. |
| smallpart | done | Most TS errors of any file in this project (4028 lines, 1 `var` - a comment only, 24 jQuery uses, 167 TS errors) - see below. `smallpart/` is also its own nested git repo - same note. |
| calendar | done | Largest file in this project by line count, and by far the most `var`/jQuery usage of any file here (4525 lines, 253 `var`, ~90 jQuery uses, 97 TS errors, 14 `sendRequest()` sites) - see below. |
| home | done | `home/js/app.ts`, 538 -> 538 lines, main repo. 0 `var`, 2 jQuery hits, 11 TS errors, 2 `sendRequest()` sites, 2 live `function(` occurrences (plus a dead, fully commented-out gridster-era block that doesn't count) - see below. Found and fixed a genuine live `ReferenceError` crash in `set_default()`. |

**Nested git repos:** `tracker/`, `status/` and `smallpart/` (and presumably other apps) are checked out as their own independent git repositories inside the main `egroupware` working tree - the root `.gitignore` explicitly excludes them (`/tracker/`, `status/`, `/smallpart/`) so the main repo never sees their contents. `git status`/`git diff` run from the repo root show nothing for files under these paths even when they're genuinely modified - `cd` into the app directory first (each has its own `.git/`) to see/commit/push those changes. Matches the existing `feedback_git_installs_independent_trees` memory ("don't composer-update root to pull an app-repo fix").

## Workflow used per file

1. `grep -n "\bvar \b"`, `grep -n "jQuery"`, and read the full file once to see the legacy-widget
   imports.
2. `node_modules/.bin/tsc --noEmit -p tsconfig.json`, then grep the output for the file's path, to get
   a baseline list of real TS errors (the whole-repo build has ~5000 pre-existing errors in other
   files - only the target file's own lines matter).
3. Fix imports (goal 1), `var`->`const`/`let` (goal 2), and jQuery (goal 4) mechanically.
4. Fix each TS error from step 2 individually (goal 3), then re-run tsc to confirm the file is clean
   and that no new errors were introduced.
5. `grep -n "sendRequest"` for goal 5, `grep -n "function\s*("` and `var self\|var that` for goal 6,
   and apply both mechanically.

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

### `egw.json(...).sendRequest()` -> `egw.request()`

- `actionCallback()`'s `egw.json(...).sendRequest(true)` (fire-and-forget async) -> `egw.request(...)`.
- `confirm_delete_edit()`'s `egw.json(...).sendRequest(false)` **kept as-is** - genuinely synchronous
  (comment explains why: the delete must complete before `window.close()`), and `egw.request()` is
  always async, so there's no equivalent swap for this one.

### function/closures -> arrow functions

- The one remaining plain `function () {...}` (in `et2_ready()`'s mailvelope-decrypt-hover callback,
  no `this` usage) -> arrow function. The `var that = this` closure in
  `infolog_print_preview_onload()` was already converted earlier as part of the jQuery removal above
  (arrow functions naturally close over `this`, so it and the jQuery swap were done together).

### Not touched (out of scope)

- `calendar/js/app.ts` has the same `_inst`-private and `postSubmit()`-arg-count errors - left alone,
  this pass is infolog-only.
- No behavior changes were made beyond the mechanical DOM-API/type swaps above - e.g. the
  `DOMSubtreeModified`-based print-detection in `infolog_print_preview_onload()` is still exactly as
  fragile/deprecated as before, just no longer wrapped in jQuery.

## timesheet/js/app.ts (done)

Already jQuery-free before this pass (0 hits). Only 7 `var`s and 11 TS errors to fix.

### Legacy widget imports

- `et2_grid` (`et2_widget_grid`) is only ever used for one type cast (`<et2_grid>_widget.getParent()`)
  - switched to `import type`, but **not** replaced with a web-component equivalent: unlike
    `et2_selectbox`/`et2_date` (zero-member compat shims over a real `Et2*` class), `et2_grid` is
    itself the actual legacy grid widget implementation (`extends et2_DOMWidget`) - there is no newer
    `Et2Grid` web component to migrate to, so goal 1 doesn't apply here.
- Both `import '../../api/js/jsapi/egw_global'` (bare side-effect import) and `import {egw} from
  ".../egw_global"` (named import) removed, same reasoning as infolog's `egw` fix - `egw`/`app` are
  ambient globals via `declare global {}` in `egw_global.d.ts`; the real `egw_global.js` module does
  technically export `egw`, but tsconfig's `include` only picks up `.ts`/`.d.ts` files (not `.js`), so
  TS only ever sees the ambient `.d.ts` declaration regardless of whether the import is written - the
  import was actively wrong for TS (`TS2305`) while contributing nothing at runtime beyond what the
  ambient global already provides.

### TS errors fixed (11 total)

- **0-arg `this.filter_change()` call** from `et2_ready()`: same pattern as infolog - made both params
  optional (`filter_change(ev?, filter?)`).
- **`this.nm` union type** (`Et2Nextmatch | et2_nextmatch`): same fix as infolog, `const nm =
  <Et2Nextmatch>this.nm` once per method, used for `.activeFilters.startdate` (legacy `et2_nextmatch`'s
  `ActiveFilters` interface has no `startdate` field, only `Et2Nextmatch`'s `Record<string, any>`
  return type allows it), `.updateComplete`, and `.style` (3x in `filter2_change()`).
- **`Et2DateTimeReadonly.value` doesn't exist on the type** (5 sites in `editEventTime()`): a **latent
  bug in the shared `Et2Date/Et2DateReadonly.ts` widget itself**, not something introduced here - its
  `value` property is declared only via Lit's old-style `static get properties() { return {value:
  String} }`, with no typed class field/accessor, so TypeScript has no way to know instances have a
  `.value` at all (confirmed: `Et2DateReadonly.ts` itself has ~10 pre-existing `TS2339` errors for its
  own `this.value` accesses). Left the shared widget alone (fixing its typing is a separate, larger
  change with its own blast radius, not part of an app.ts modernization pass) and worked around locally
  with `<any>` casts at each `.value` access site in this file, matching the `<any>` casting style the
  surrounding code already used for the same widget.

### `egw.json(...).sendRequest()` -> `egw.request()`

All 3 uses were fire-and-forget/awaited async (`sendRequest(true)`), no synchronous ones present -
straightforward 1:1 swaps: `pm_id_changed()`'s pricelist fetch, `ts_start_changed()`'s last-end-time
fetch (chained `.finally()` preserved), and `ajax_action()`.

### function/closures -> arrow functions

- `pm_id_changed()`'s `function(value) {...}` callback passed to the pricelist fetch -> arrow function,
  done together with its `egw.json().sendRequest()` -> `egw.request().then()` conversion above (same
  edit).

### Not touched (out of scope)

- `Et2DateReadonly.ts`'s missing `value` typing (see above) - a real bug worth fixing on its own, but
  out of scope for an app.ts-only pass.

## addressbook/js/app.ts (done)

Largest file done so far (1826 lines). Baseline: 98 `var`s, 12 jQuery uses, 31 TS errors, 11
`egw.json(...).sendRequest()` call sites, several `function(){}`/`var self`/`var that` closures.

### Legacy widget imports

- `et2_selectbox` (only 1 type cast + 1 JSDoc `@param` mention, same zero-member compat-shim shape as
  infolog's) -> `import type {Et2Select}`.
- `egw`/`LitElement`/`Et2SelectCountry`/`Et2SelectState`/`EgwActionObject`/`Et2Template`/
  `Et2TreeDropdown`/`PushData` were all imported as *value* imports despite being used only as type
  annotations/casts throughout the file - switched all to `import type`, same reasoning as infolog's
  `Et2Select`/`Et2Nextmatch` fix. `Et2Dialog`, `etemplate2`, `et2_createWidget`, `egw_getActionManager`
  stayed value imports (all called/instantiated as values: `new Et2Dialog(...)`, `Et2Dialog.confirm()`,
  `etemplate2.getById()`, etc).
- `import {egw} from ".../egw_global"` removed - the now-familiar ambient-global fix from
  infolog/timesheet.
- `egwAction`/`egwActionObject` (lowercase, used as the real TS types for the `action()` method's
  params) had no import at all (`TS2304: Cannot find name`) - turns out these are real, concrete
  exported classes in `api/js/egw_action/egw_action.ts` (`export class egwAction extends EgwAction`,
  similarly for `egwActionObject`), not ambient/global types - added
  `import type {egwAction, egwActionObject} from ".../egw_action/egw_action"`.

### TS errors fixed (31 total)

- **`this.et2._inst`** (3 sites: `check_value()`, `account_change()`, plus one inside `view_actions()`
  via `et2.widgetContainer._inst.submit()`) - same `getInstanceManager()` fix as infolog/timesheet. The
  `view_actions()` one simplified further: since `et2` there is already `_widget.getInstanceManager()`
  (the etemplate2 instance itself, which has its own public `.submit()`), `et2.widgetContainer._inst
  .submit()` collapsed to plain `et2.submit()` rather than round-tripping through the widget.
- **`getValues(_root, skip_reset_dirty)`'s missing 2nd arg cascading into 10 more errors**: fixing the
  `_inst` calls above (which resolves through `getInstanceManager()`, whose return type TS infers as
  effectively `any` due to a recursive/self-referential branch with no explicit return type - see
  timesheet's note on the same inference gap) *also* silently fixed the paired "Expected 2 arguments,
  got 1" errors on those same `.getValues(this.et2)` calls, and the resulting "`values.n_prefix` doesn't
  exist on type `{}`" downstream errors (10 of them, from `values` losing its broken `{}` type once the
  call itself type-checked cleanly) - one root-cause fix, many errors resolved as a side effect.
- **`view.widgetContainer._children[0]`**: `_children` is `private` on `Et2WidgetClass`; the public
  equivalent is `getChildren()`. `.set_value(...)` on the result still needed an `<any>` cast (return
  type is a widget union with no common `set_value`).
- **`CrmParams.crm_list`'s union type vs. a loose `egw.preference()` string**: cast to the interface's
  own property type (`<CrmParams["crm_list"]>...`) instead of a bare `<string>`, so the cast still
  enforces the union shape rather than just silencing the error.
- **`etemplate2.app_obj` is `private`, and `EgwApp` doesn't know about per-app extensions
  (`.addressbook`)**: `app_obj` is accessed the same way (directly, despite being private) from a dozen+
  other files across the repo (`calendar/js/et2_widget_*.ts`, `filemanager/js/filemanager.ts`,
  `addressbook/js/CRM.ts`, several `api/js/etemplate/*.ts`) - a widely-relied-on convention, not a typo.
  Rather than touch the shared `etemplate2.ts` (out of scope) or the dozen other call sites (out of
  scope), cast to `<any>` at this one call site.
- **0-arg-callback `this` context lost**: `egwAction` (from `api/js/egw_action/egw_action.ts`)'s
  `getActionById()` return type doesn't declare a `checked` property - it's set dynamically at runtime
  via `updateAction()`, same class of gap as `app.stylite`/`et2_grid` elsewhere in this project. `<any>`
  cast at the one strictly-typed call site (`action()`'s `_action.parent.getActionById(...)`) - the other
  4 `.checked` reads elsewhere in the file go through an untyped `action` parameter already, so never
  triggered the error.
- **`.item` on a `SelectOption`**: `_add_new_list_prompt()` read `?.item` where `SelectOption`
  (`FindSelectOptions.ts` -> `SearchResult`) only ever declared `children` - the type comment literally
  says *"the item's children, called `item` in some legacy code"*. This wasn't just a TS annoyance: the
  sibling method `rename_list()` (same file, does the equivalent lookup) already correctly used
  `.children`, so `.item` was a real latent bug (always `undefined`, silently falling through to the
  `?? filter.select_options` fallback) - fixed to match.
- **`.text` on a `SearchResult`**: `rename_list()`'s dialog-title fallback `value.label || value.text` -
  `SearchResult` only has `.label`, no `.text`. Unlike `.item` above, there's no sibling method to
  confirm this is a bug rather than a deliberate defensive fallback for some other, untyped code path
  that might set `.text` - left the runtime behavior alone and cast to `<any>` (`value.label ||
  (<any>value).text`) rather than deleting what might be a real (if untyped) fallback.
- **`country.value` (`Et2SelectCountry`) is `protected`**: `Et2InputWidget`'s `value` field is
  `protected`, only accessible within the class hierarchy, not from app.ts. `getValue()` is the public
  accessor - swapped in `regionSetCountry()`.
- **`new Promise((resolve) => ...)`'s inferred `Promise<unknown>`**: `_getEmails()`'s `let awaited =
  await all` came back typed `unknown` (no `.length`) because the `Promise` executor's `resolve` had no
  declared type, so TS fell back to `unknown`. Added an explicit `new Promise<any[]>(...)` instead of
  relying on inference.
- **`app.status.*` (`getEntireList()`/`inviteToCall()`/`makeCall()`)**: same `app` typed as
  `{[propName: string]: EgwApp}` gap as infolog's `app.stylite` - `status` is another EPL/stylite-only
  app extension. Same `<any>` cast pattern, 3 call sites.
- **`refreshCallback: function() {...this.appName...}`**: not fixed the same way as the other
  `function(){}` literals below. No caller of `.refreshCallback()` was found anywhere in-repo (it may be
  dead code, or invoked by something outside the TS/JS tree), but `tabLinkHandler()`
  (`kdots/js/EgwFramework.ts`) spreads this whole options object onto the tab it creates, so *if*
  something does eventually call it as `tab.refreshCallback()`, `this` needs to be that tab object (for
  its `appName`, camelCase, unrelated to `EgwApp.appname`) - an arrow function would instead capture the
  outer `openCRMview()` call's `this` (the `AddressbookApp` instance, no `appName`). Since the claim
  couldn't be confirmed against an actual call site, treated it as goal 6's documented exception and left
  it as a plain `function` rather than risk a behavior change on unverified grounds - cast the one
  `.app_obj.addressbook` access inside it to `<any>` instead. Worth a follow-up look at whether
  `refreshCallback` is called from anywhere (a non-TS caller, or truly dead code).

### jQuery removed

- `jQuery('table.editphones').css('display', ...)` (2x, `showphones()`/`hidephones()`) ->
  `document.querySelectorAll<HTMLElement>(...).forEach(...)`, same pattern as infolog's `tr.hiddenRow`.
- `jQuery(dom).nextAll('.et2_container').attr('id')` (`view_set_list()`, finding the next
  `.et2_container` sibling by walking forward from the current template's `DOMContainer`) -> a small
  `while` loop over `nextElementSibling` checking `.matches('.et2_container')`. No native one-liner
  equivalent for "first following sibling matching a selector" - jQuery's `.nextAll(sel)` walks *all*
  following siblings and filters, so the native replacement has to do the same walk manually.
- `jQuery(field).val()` (`nm_compare_field()`, where `field` is already a plain `document.getElementById`
  result) -> `field.value` directly - `.val()` was pure overhead here, not doing anything jQuery-specific.
- `jQuery.isEmptyObject(x)` (4x, `getState()`/`setState()`) -> `Object.keys(x ?? {}).length === 0`
  (the `?? {}` preserves jQuery's null/undefined-safe behavict - `Object.keys(null)` throws,
  `jQuery.isEmptyObject(null)` returns `true`).
- `jQuery.proxy(function(x) {...}, this)` (3x: `add_new_list()`, `adb_mail_vcard()`'s outer callback) ->
  plain arrow functions, which close over `this` natively - this removes the jQuery dependency and
  satisfies goal 6 in the same edit, exactly like `pm_id_changed()`'s conversion in timesheet.
- Two stale `@param {jQuery.event}`/`{jQuery.Event}` JSDoc tags corrected to `{Event}`.

### `egw.json(...).sendRequest()` -> `egw.request()`

- 9 of the 11 `sendRequest()` sites were async (`sendRequest(true)` or bare `sendRequest()`, which
  defaults to async per `Json`'s constructor) - straightforward swaps to `egw.request(...).then(...)`,
  several combined with a `function(){}` -> arrow conversion for the callback (see below) since the
  callback needed correct `this`.
- 1 site (`rename_list()`'s `ajax_get_list_owner` lookup) is genuinely synchronous
  (`sendRequest(false)`) - **kept as `egw.json(...).sendRequest(false)`**, with a comment added
  explaining why (the dialog built right after needs `data.owner` already populated,
  and `egw.request()` is always async so there's no equivalent swap).
- The last (`view_actions()`'s `et2.widgetContainer._inst.submit()`) wasn't actually a `sendRequest()`
  call at all - see the `_inst` fix above.

### function/closures -> arrow functions

- `add_new_list()`'s and `adb_mail_vcard()`'s `jQuery.proxy(function(x) {this...}, this)` wrappers ->
  arrow functions (done together with the jQuery removal above, same edit).
- `geoLocationExec()`'s `var self = this` + two `function(){...self...}` callbacks (a
  `navigator.geolocation.getCurrentPosition()` callback and an `egw.json()` callback, neither of which
  invokes its callback with a useful `this`) -> both converted to arrow functions using `this` directly,
  and the now-unused `self` variable removed.
- `_add_new_list_prompt()`'s and `rename_list()`'s dialog `callback: function(button, values) {...}` and
  their nested `egw.json(...)` result callbacks -> arrow functions. Checked first that neither callback
  body actually used `this` (both only touch closured locals and `egw`/`Et2Dialog` globals), so this is
  behavior-preserving - unlike the `refreshCallback` case below, `Et2Dialog.transformAttributes()`'s
  `callback` has no documented `this`-binding contract to lose.
- `rename_list()` also had a `const self = this;` that turned out to be **entirely unused, dead code**
  (the file's only other `self`-usage was the unrelated `geoLocationExec()` one above) - removed instead
  of converting, since there was nothing to convert.
- Several no-`this`, no-context predicate/mapper callbacks with no closure-capture reason to keep as
  `function` (`can_merge()`'s `.filter()`, `setState()`'s `.find()`, `_getEmails()`'s and
  `adb_mail_vcard()`'s inner `.map()` callbacks) -> arrow functions too, for consistency, even though
  leaving them as-is would have been equally correct.
- `refreshCallback: function() {...}` (`openCRMview()`) - **kept as a plain function**, see the TS-errors
  section above: this is goal 6's documented "callback contract that depends on being invoked as a
  method to get its `this`" exception, not an oversight. Added a comment explaining why, so a future
  pass doesn't "helpfully" convert it.

### A `var`->`const` conversion that needed care, not just find/replace

`_confirmdialog_callback()`'s inner dialog callback reads an outer `content` array that, in the original
code, was declared with `var content = []` *inside* an `if` block **textually after** the (already
earlier-defined) function that reads it. That's legal with `var` (function-scoped, hoisted across the
whole method) but would be a real `ReferenceError` with a naive `const content = []` left in place inside
the `if` block, since `const`/`let` are block-scoped - the earlier-defined nested closure would no longer
see it at all. Fixed by moving the `let content = [];` declaration to the top of the method (still
outside/before the `if`, so it's populated before the closure that reads it can actually fire - the
closure only runs later, asynchronously, after a dialog confirm) rather than mechanically swapping `var`
for `const` in place.

### Not touched (out of scope)

- `mailCheckbox()`'s and `addEmail()`'s `action.getManager().getActionById(...).checked` reads (4 sites)
  go through an untyped `action` parameter and never triggered a TS error, so were left as-is rather than
  adding redundant `<any>` casts.
- `etemplate2.app_obj`'s `private` modifier and `EgwApp`'s missing per-app-extension typing (see above) -
  real gaps worth fixing centrally, but out of scope for an app.ts-only pass, and too widely relied-upon
  elsewhere to fix as a "small, clearly-scoped" side change.

## addressbook/js/CRM.ts (done)

Folded into the addressbook pass since `addressbook/js/app.ts` loads it directly (`import "./CRM"`) and
it's the CRM-sidebox view class for the same app - not something Ralf asked for by name, a judgment call
to cover a small, tightly-coupled companion file while already in this area. Much smaller and already
mostly modern: 0 `var`, 1 jQuery use, 0 `sendRequest()` calls, 2 `function(){}` expressions, 2 TS errors.

### Legacy widget imports

No legacy `et2_*` widgets here at all - `Et2Nextmatch`/`Et2Datagrid`/`etemplate2`/`PushData` were all
already imported from their real (web-component or modern TS) locations, just as unnecessary *value*
imports for symbols only ever used as types - switched all to `import type`, same reasoning as
addressbook's own `app.ts` pass. `Et2DatagridUpdateTypes` stayed a value import (`Et2DatagridUpdateTypes
.DELETE` is a real runtime property access, not just a type). `import {egw} from ".../egw_global.js"`
removed - same ambient-global fix as every other file in this pass (note this one imported the `.js`
file, not `.d.ts`/no extension like elsewhere - same underlying non-issue either way, since tsconfig's
`include` doesn't pick up `.js` for module resolution regardless of which extension the import spells
out).

### TS errors fixed (2 total)

- **`import {egw} from ".../egw_global.js"`** - the familiar `TS2305` ambient-global fix.
- **`<Et2Nextmatch>app_obj.et2.getDOMWidgetById('nm')`** (`TS2352`, "conversion... may be a mistake"):
  `Et2Template.getDOMWidgetById()` (`api/js/etemplate/Et2Template/Et2Template.ts`) is declared to return
  `typeof Et2Widget | null` - the **constructor/class itself**, not a widget *instance* - almost
  certainly a typo for `Et2Widget | null` in that shared file. This is a real, pre-existing bug, but
  already has an established repo-wide workaround rather than a fix: `api/js/etemplate/
  et2_widget_placeholder.ts` alone has 20+ call sites all cast the same way, through `<unknown>` first
  (`<Et2Select><unknown>...getDOMWidgetById(...)`). Applied the identical `<Et2Nextmatch><unknown>...`
  pattern here rather than touching the shared file (out of scope, and evidently a live, working
  convention the rest of the codebase already leans on).

### jQuery removed

- `jQuery(node).on('clear', function() {...}.bind(this))` -> `node.addEventListener('clear', () =>
  {...})` - the arrow function replaces both the jQuery event binding and the `.bind(this)`, satisfying
  goals 4 and 6 in one edit (same combined pattern used repeatedly in `app.ts`).

### function/closures -> arrow functions

- `_override_push()`'s `app_obj.push = function(pushData) {return false;};` (overriding another
  object's method with a no-op, no `this` used) -> `app_obj.push = (pushData) => false;`.
- The `jQuery(...).on('clear', function(){...}.bind(this))` callback above, converted together with its
  jQuery removal.

## tracker/js/app.ts (done)

558 lines, 3 `var`, 2 jQuery uses, 12 TS errors (baseline count includes some multi-line detail lines).

### Legacy widget imports

- `et2_nextmatch`/`et2_button`/`et2_selectbox` are **real, distinct legacy implementations** (not
  zero-member shims - `et2_nextmatch extends et2_DOMWidget`, `et2_button extends et2_baseWidget`), *and*
  this file passes them as **runtime values** to `iterateOver(_callback, _context, _type)`'s `_type`
  filter parameter (an `instanceof`-style check), not just as TS type annotations. Left all three as
  value imports, unconverted - swapping to their web-component namesakes (`Et2Nextmatch`/`Et2Button`/
  `Et2Select`) would silently *broaden* the `instanceof` match (a subclass instance also passes an
  `instanceof` check against its superclass), a real behavior change, not just a type-safety one. This
  is a new "goal 1 doesn't apply" reason distinct from timesheet's `et2_grid` (no replacement exists at
  all) - here a replacement exists, but using it would change matching semantics.
- `et2_template` was imported but **never referenced anywhere in the file** - a genuinely dead import,
  removed entirely.
- `et2_htmlarea`/`et2_checkbox`/`et2_selectAccount` (all only used for type casts) -> `import type`.
  `et2_selectAccount` turned out to already be a plain `export type et2_selectAccount = Et2SelectAccount`
  alias (not a class) in its own source file - the previous value import was doubly wrong.
- `import "./Et2TrackerAssigned.ts"` (a side-effect-only import registering the custom element) needed
  to stay *as well as* gaining a companion `import type {Et2TrackerAssigned} from "./Et2TrackerAssigned"`
  for the type - `import type` alone would have been fully erased at compile time, silently dropping the
  `customElements.define()` registration this file depends on. (Also: the `.ts` extension had to be
  dropped from the *type* import specifically - `TS2691`, import paths can't end in `.ts` - even though
  the pre-existing side-effect import spells it out with `.ts` and doesn't error.)
- `egw` ambient-global import removed, same as every other file in this pass.

### TS errors fixed (12 total, some multi-line)

- **0-arg `this.filter_change()` call**: same optional-params fix as infolog/timesheet.
- **`this.nm.activeFilters.startdate = null`**: unlike every other app converted so far, tracker's `nm`
  is **evidence of genuinely still being the legacy `et2_nextmatch`**, not `Et2Nextmatch` - confirmed by
  `viewEntry()` a few methods down, which calls `nm.getController()._indexMap` and gets back
  jQuery-wrapped row nodes (`.find()`/`.removeClass()`), APIs that only exist on the legacy
  implementation. Casting to `Et2Nextmatch` here (the pattern used everywhere else) would have been
  actively *wrong*, not just unverified - used `<any>` instead, with a comment explaining why this app
  differs.
- **`filter.closest('egw-app').filtersDrawer`**: `.closest()` on an `Element` returns generic `Element`;
  `filtersDrawer` is a real getter on `EgwFrameworkApp` (`kdots/js/EgwFrameworkApp.ts`), the framework
  shell custom element behind the `egw-app` tag. Added a type-only import and cast.
- **`this.et2.node.baseURI`**: a **real, previously-silent bug**, not just a typing gap - `Et2Template`
  (the type of `this.et2`) extends `Et2Widget(LitElement)`, i.e. it *is* an `HTMLElement`/`Node` itself
  and has `.baseURI` natively; there's no `.node` sub-property and never has been since `Et2Template`
  became a real custom element. `typeof this.et2.node !== 'undefined'` has therefore always evaluated to
  `false`, meaning `edit_popup()`'s entire body (focusing the popup window, resizing it for
  mail-composed trackers) has been dead code. Fixed by reading `this.et2.baseURI` directly - restores
  the original intent, not just silences the type error. No other file in the repo references `.et2.node`
  (checked), so this wasn't a load-bearing pattern used elsewhere.
- **`et2-link-list` querySelectorAll results typed as generic `Element`**: `this.et2.querySelectorAll(...)`
  (where `this.et2` is strongly-typed `Et2Template`) returns real `Element[]`, unlike the *other*,
  untyped `widget.getInstanceManager().widgetContainer.querySelectorAll(...)` chain two lines above
  (which resolves through the effectively-`any` `getInstanceManager()` return type and so never
  errored). Used the generic-argument form, `querySelectorAll<Et2LinkList>(...)`, rather than a cast.
- **`Et2TrackerAssigned` type not found**: see the import fix above.
- **`viewEntry()` override not assignable to `EgwApp.viewEntry()`** (`TS2416`): the base method's
  inferred return type is `Promise<Et2Dialog>` (from its docblock/body), but tracker's override called
  `super.viewEntry(...)` and discarded the result, so its own inferred return type was `void` - not
  assignable. Fixed by capturing `super.viewEntry(...)`'s return value and `return`-ing it at the end of
  the override, *after* the synchronous unseen-class cleanup that has to keep running immediately
  (unchanged execution order/timing - `return`-ing a promise doesn't await it).
- **`dialog.getComplete()`'s destructured `value` typed as generic `Object`**: `Et2Dialog.getComplete()`
  is declared `Promise<[number, Object]>` - too generic for `value.reply_message`. Annotated the
  destructured callback params directly (`([button, value] : [number, any])`) rather than touching the
  shared dialog class.

### jQuery removed

- `jQuery('#tracker_index_col_filter_tr_tracker__chzn').hide()` -> `document.getElementById(...)` +
  `style.display = 'none'`.
- `nm_indexes[i].row._nodes[0].find('.tracker_unseen')` / `node.removeClass(...)` in `viewEntry()` -
  **left untouched**, see "Not touched" below.

### function/closures -> arrow functions

- The `iterateOver(function(widget) {...}, this, et2_selectbox)` callbacks (`et2_ready()`'s
  `'tracker.escalations'` case, `getState()`) explicitly pass `this` as `iterateOver`'s own `_context`
  argument - but since that `this` is always *exactly* the same object the arrow function's lexical
  `this` would already resolve to (both come from the same enclosing method), converting to an arrow is
  safe and makes the now-redundant explicit `_context` argument harmless rather than necessary. This is
  a useful refinement on the goal-6 exception: passing `otherThis` explicitly to `.call()`/as a context
  argument only blocks arrow conversion when that `otherThis` is *not* the lexically-enclosing `this`
  (e.g. `tabLinkHandler`'s tab object in addressbook's `refreshCallback` case) - when it *is* the same
  object, the explicit binding was just belt-and-braces already, and an arrow is equivalent.
- `tprint()`'s `popup.onload = function(){this.print();}` relies on the browser setting `this` to
  `popup` when it fires the `onload` event - genuinely the goal-6 exception. Rather than leaving it as a
  commented plain `function`, rewrote it to reference the already-in-scope `popup` closure variable
  directly (`popup.onload = () => popup.print();`) - cleaner than either option alone, and removes the
  dynamic-`this` dependency entirely instead of just documenting it.

### Not touched (out of scope)

- `viewEntry()`'s jQuery-object row lookup (`nm_indexes[i].row._nodes[0].find(...)`/`.removeClass(...)`)
  is tied to legacy `et2_nextmatch`'s own internal row representation (its `_indexMap`/`row._nodes`
  structure appears to store rows as jQuery-wrapped nodes, not raw DOM elements) - not a simple
  `jQuery(x).method()` call that swaps 1:1 to a native API without deeper research into that internal
  contract, and it wasn't flagged as a TS error either. Left alone rather than guess.
- `comment_add_vfs()`'s `Promise.all([wait, wait])` (where `wait` is already an array of promises) looks
  like a real bug - almost certainly meant `Promise.all(wait)` - `Promise.all` on an array containing two
  non-thenable arrays resolves immediately rather than waiting for the actual link-list updates. Not
  fixed: this line isn't touched by any of the 6 goals (no `var`, jQuery, TS error, or `function(){}`
  involved), so it's outside this pass's scope per `doc/ai/modernization.md`'s "don't turn an unrelated
  bugfix into a drive-by cleanup" rule - flagged here for whoever picks it up next.
- `multiple_assigned()`'s `_widget.getParent()._children[0]` accesses the same `private _children` field
  as addressbook's fixed cases, but doesn't error here (the containing expression's type resolves
  loosely enough that TS doesn't catch it) - left alone since goal 3 only covers the file's *actual*
  reported errors, not every instance of a pattern already fixed elsewhere.

## status/js/app.ts (done)

848 lines, 0 `var` (already clean), 3 jQuery uses, 7 TS errors, 9 `egw.json(...).sendRequest()` sites (all
async, none kept sync), and the heaviest concentration of `let self = this` + `function(){}` closures
found in this pass so far (`_controllRingTone()` alone nests 3 levels of them).

### Legacy widget imports

- `et2_grid`/`et2_button` (both only used for type casts, both real distinct legacy implementations with
  no web-component replacement - `et2_grid` confirmed already in the timesheet section) -> `import type`,
  same reasoning as timesheet, not renamed to `Et2Grid`/`Et2Button`.
- `egw` ambient-global import removed, same as every other file.

### TS errors fixed (7 total)

- **`getDOMWidgetById('end')`/`getDOMWidgetById('add')`'s `.set_disabled(...)`**: the exact same
  `Et2Template.getDOMWidgetById()` return-type bug (`typeof Et2Widget` instead of an instance) found and
  worked around in `addressbook/js/CRM.ts` - same `<any>` cast fix applied here, two more confirmed
  occurrences of that shared framework bug.
- **`app.rocketchat?.isRCActive(...)`/`.restapi_call(...)`** (3 call sites: `isOnline()`, twice in
  `makeCall()`): the familiar `app` / `{[propName:string]: EgwApp}` EPL-blind-spot pattern
  (`app.stylite` in infolog, `app.status` in addressbook) - `rocketchat` is EPL's Rocket.Chat
  integration app. Same `<any>` cast pattern.
- **`app.status.openCall(...)`** (`videoconference_countdown_join()`): same root cause, but since
  `openCall()` is defined on *this very class* (`statusApp`), cast to `<statusApp>` instead of `<any>` -
  more precise than the generic EPL-blind-spot workaround, since we actually know the real shape here.

### jQuery removed

- `jQuery('body').one('click', function(){...})` (`et2_ready()`, ring-tone unlock on first user
  interaction) -> `document.body.addEventListener('click', () => {...}, {once: true})` - native
  `{once: true}` is the direct equivalent of jQuery's `.one()`.
- `jQuery.extend(true, fav[f], _content[i])` / `jQuery.extend(true, list[l], _content[i])`
  (`mergeContent()`) -> `egw.deepExtend(fav[f], _content[i])` / `egw.deepExtend(list[l], _content[i])`,
  per `doc/ai/modernization.md`'s `deepExtend()` rule - dropped the `true` first argument since
  `deepExtend()` is unconditionally a deep merge, unlike jQuery's signature.

### `egw.json(...).sendRequest()` -> `egw.request()`

All 9 sites were async (`sendRequest()`/`sendRequest(true)`, nothing genuinely synchronous) -
straightforward swaps across `handle_actions()`, `refresh()`, `makeCall()`, `receivedCall()`,
`videoconference_invite()`, `videoconference_endMeeting()`, `inviteToCall()`, `videoconference_countdown_join()`,
and `vc_deleteRecording()`.

### function/closures -> arrow functions

- Removed **7** separate `let self = this;` declarations (`et2_ready()`, `push()`, `add_to_fav()`,
  `refresh()`, `makeCall()`, `scheduled_receivedCall()`, `receivedCall()`, `_controllRingTone()`,
  `didNotPickUp()`, `_phoneMissedCallback()` - several methods each had their own) by converting every
  `function(){...self...}` closure that used them to an arrow function referencing `this` directly.
  Checked each one first for genuine dynamic-`this` needs (Dialog `callback:`, `Et2Dialog.show_dialog()`
  callbacks, `setTimeout`/`Promise.then()` callbacks) - all of them only ever read `self`, never relied
  on their own call-time `this`, so all were safe to convert.
- **One genuine exception found**, inside `_controllRingTone()`: its `initiate()` method called
  `this.stop()` where `this` was deliberately the *returned `{start, stop, initiate}` object* (a sibling
  method call, object-literal-method style) - not the outer `self`/app instance. A naive arrow-conversion
  here would have broken it (an arrow's `this` would resolve to the app instance, which has no `.stop()`
  method). Fixed by pulling `stop` out as its own named `const stop = () => {...}` *before* the returned
  object literal, referencing it directly from both `start`'s `setTimeout` callback and `initiate()` -
  avoids the self-reference problem entirely while still converting every function to an arrow.
- `dialog.transformAttributes({callback: ...})` and `Et2Dialog.show_dialog(callback, ...)` callbacks
  across `add_to_fav()`, `makeCall()`, `scheduled_receivedCall()`, `receivedCall()`,
  `videoconference_invite()`, `videoconference_endMeeting()`, `didNotPickUp()`, `_phoneMissedCallback()` -
  all confirmed (via `Et2Dialog.ts`'s own `_callback.call(this, ...)`/`this.callback(...)` invocation
  sites) to bind `this` to the *dialog*, not the app - exactly why every one of them already used
  `self`/closured variables instead of `this`. Confirms (again) that a manual `self =
  this`/`that = this` capture already present in code being touched is reliable evidence the enclosing
  callback mechanism rebinds `this` - and that arrow conversion is *safe precisely because* of that
  existing workaround, not despite it.
- `onclick: function() { window.focus(); }` (2x, `Notification` options in `scheduled_receivedCall()`/
  `receivedCall()`) -> `onclick: () => window.focus()` - no `this` used, converted for consistency even
  though native `Notification.onclick`'s own dynamic-`this` contract was never actually relied upon here.

### Not touched (out of scope)

- No new "not touched" findings beyond what's already covered above for this file.

## admin/js/app.ts (done)

Largest file done so far (2227 -> 2330 lines). Baseline: ~96 `var`s, 21 jQuery uses, 14 TS errors,
13 `egw.json(...).sendRequest()` call sites, 17 `function(`/`var self`/`var that` closures. A
previous partial attempt had already fixed some `_inst` sites and most of the legacy-widget import
goal before this pass picked it up fresh.

### Legacy widget imports

- Imports were already mostly converted by the prior partial attempt: `Et2SelectAccount`,
  `EgwAction`, `EgwActionObject`, `Et2Button`, `LitElement`, `Et2Template`, `EgwFrameworkApp`,
  `egwAction`/`egwActionObject` were all already `import type` (type-only usage confirmed for each -
  casts/annotations, never instantiated). `Et2Dialog`, `etemplate2`, `loadWebComponent` correctly
  stayed value imports (`new Et2Dialog(...)`, `Et2Dialog.confirm()`/`.show_dialog()`,
  `etemplate2.getById()`, `loadWebComponent("et2-dialog", ...)`). `import {egw}`/ambient-global import
  was already absent (comment-only, matching every other file in this pass).
- `et2_nextmatch` (`et2_extension_nextmatch.ts`) stayed a **value** import, unconverted - it's a real,
  distinct legacy widget implementation (not a compat shim), and this file passes it as a runtime
  `instanceof`-style filter value to `iterateOver(_callback, _context, _type)` in `getNextmatch()`,
  same reasoning as tracker's `et2_nextmatch`/`et2_button`/`et2_selectbox`.
- **`et2_DOMWidget`** (was `import type`, used only as `copyClipboard(_widget : et2_DOMWidget, ...)`'s
  param type) - removed entirely and the param retyped `any` (see TS-errors section below): the type
  was actively wrong for how the method is really called, not just incomplete.
- **`Et2Nextmatch`** (was `import type`, used only for one cast in `load()`) - removed entirely: the
  cast itself was wrong (see next section) - `admin.index.xet` still uses the legacy `<nextmatch
  id="nm">` tag, not `<et2-nextmatch>`, so `this.nm`/`this.accounts` are genuinely `et2_nextmatch` at
  runtime here, the same situation as tracker/js/app.ts, not the `Et2Nextmatch` web component the rest
  of this project's apps (infolog/timesheet/addressbook) use.

### TS errors fixed (14 total)

- **`this.et2._inst`** (6 sites: `_acl_delete()`'s dialog callback, 3x in `_acl_dialog()`,
  `emailadminActiveAccounts()`, `wizard_detect()`) - same `getInstanceManager()` fix as every other
  file in this pass. One site (`_acl_delete()`'s callback, originally `var callback = function(...)
  {...}.bind(this)`) had been silently exempt from the TS baseline count because a plain `function`
  expression's `this` types as implicit `any` - converting it to an arrow function (goal 6) made the
  same pre-existing `_inst` access newly type-checked, so it needed the identical fix as part of that
  conversion, not a separate one.
- **`Et2Nextmatch.disabled`/`.resize()` don't exist** (`load()`, 3 sites): the cast at `const nm =
  <Et2Nextmatch>this.nm` was simply the wrong type - see the import-removal note above. Both members
  are real on the legacy `et2_nextmatch` (`disabled` inherited from the base widget chain, `resize()`
  its own method using `this.dataview.resize(...)`) - fixed by casting to `<et2_nextmatch>` instead,
  with a comment explaining why (admin.index.xet not yet migrated to `<et2-nextmatch>`).
- **`content.tabs` doesn't exist on type `{}`** (`acl_reopen_dialog()`): `let content = {};` inferred
  an empty-object type with no properties; `delete(content.tabs)` a few lines later doesn't type-check
  against it. Fixed with an explicit `let content : any = {};` (the value is reassigned from
  `this.acl_dialog.get_value()`, an untyped field, right after anyway).
- **`Et2Dialog.confirm()` "Expected 3-4 arguments, but got 2"** (`check_owner()`): the static method's
  signature is `confirm(_senders, _dialogMsg, _titleMsg, _postSubmit?)` - only the 4th param is
  optional, but the call site only ever passed 2 args (relying on the method's own internal
  `typeof _titleMsg != "undefined"` runtime check to treat a missing 3rd arg as `''`). Rather than
  touch the shared `Et2Dialog.ts` signature, passed an explicit `''` as the 3rd arg at the one call
  site - matches the runtime behavior exactly, no shared-file change.
- **`app.policy.confirm` doesn't exist on type `EgwApp`** (`cf_type_delete()`): `policy` is its own
  nested-git-repo app (like `tracker`/`status`, see their notes above), invisible to this file's
  types - the same EPL/stylite-blind-spot pattern as `app.stylite`/`app.status`/`app.rocketchat`
  elsewhere in this project. Cast to `<any>` at the one `.confirm` read (the `app.policy` reads either
  side of it don't error, since `EgwApp` itself is a valid read of the indexed `app` type).
- **`_widget.get_value` doesn't exist on `et2_DOMWidget`, plus a `getDOMNode()`/`_widget`
  `HTMLElement`-vs-`et2_DOMWidget` "no overlap" comparison** (`copyClipboard()`, 3 errors): the
  declared param type was simply wrong for how the method is actually called -
  `admin/templates/default/token.edit.xet`'s two call sites pass an `<et2-textbox>` custom element
  (`this` from its own `onclick`) and a raw `document.querySelector()` result (which, for that same
  `<et2-textbox>` id, is *also* the live web-component instance, not a plain inert `Element`, since
  Et2Widget-based components host their behavior directly on the DOM node) - never a legacy
  `et2_DOMWidget`. The method's own body already runtime-guards with `typeof _widget.get_value ===
  'function'`, the same idiom `et2_core_baseWidget.ts`'s `egw_getFormValue()` uses for the same
  "widget may or may not have this method" uncertainty - retyped the param `any` to match that
  already-defensive runtime shape instead of forcing an incorrect static type, and removed the now
  fully unused `et2_DOMWidget` import (see above).

### jQuery removed

- `jQuery(iframe.getDOMNode()).off('load.admin').bind('load.admin', function(){...})` (`et2_ready()`'s
  `admin.index` case) -> `removeEventListener`/`addEventListener('load', ...)` on the iframe's own DOM
  node, with the handler stored in a new private `_adminIframeLoadHandler` instance field so it can be
  removed again before re-adding (the native equivalent of jQuery's per-namespace `off()`/`bind()`,
  kept even though the iframe node is actually fresh on every call in practice). The handler itself was
  also converted `function(){...this...}` -> arrow function referencing the iframe node directly
  (`iframeNode`) instead of relying on jQuery's `this`-as-target-element binding.
- `jQuery(ajax_target.getDOMNode().children).each(function(){...})` + a following
  `jQuery(ajax_target.getDOMNode()).empty()` (`load()`) -> `Array.from(...).forEach(...)` +
  `.replaceChildren()` (the native no-arg equivalent of jQuery's `.empty()`).
- `jQuery(this.ajax_target.getDOMNode()).append(htmlString)` (`_ajax_load_callback()`) - **caused a real
  regression, found and fixed after the fact** (see "Regression found post-merge" below). The first
  attempt swapped this for `.insertAdjacentHTML('beforeend', htmlString)`, reasoned only about the
  text-node-vs-markup-parsing difference from native `Element.append(string)` and missed a second,
  unrelated difference: jQuery's `.append(htmlString)` parses the markup in a detached, context-free
  fragment before moving the resulting nodes in, while `insertAdjacentHTML()` parses directly in the
  live DOM context. The final fix parses into a detached `<div>` first (matching jQuery's actual
  behavior) and moves its children in via `appendChild()` in a loop - see below for why that distinction
  mattered here specifically.

#### Regression found post-merge: "Could not find target node" on every admin ajax_target screen

Reported by Ralf: opening any app's site-configuration from Admin (originally noticed via calendar's,
confirmed to affect every app's, plus other admin screens like the password-reset dialog) rendered a
blank content pane with a console error `Could not find target node admin-site-config` (or
`admin-<other-screen>`). Root-caused live against a running instance (`boulder.egroupware.org`) rather
than by re-reading the diff, since static analysis of the diff alone didn't turn up the cause - the
`_ajax_load_callback()`/`load()` logic itself was (correctly) unchanged by the modernization pass, so
the bug had to be in a behavior difference of one of the jQuery->native swaps, not in changed control
flow:

- `admin.index`'s `ajax_target` container lives inside `admin.index`'s own `<form id="admin-index">`
  wrapper (confirmed via `ajax_target.getDOMNode().closest('form')` in a live console).
- The server's ajax response for an admin screen load includes a `<form id="$appname-...-config">...
  </form>` fragment (confirmed via a live network capture) - the exact node a *separate*, generic
  `et2_load`-type response item then looks up by that same id via `document.getElementById()`
  (`etemplate2.ts`, shared/out of scope for this pass) to mount the real template content into.
- `insertAdjacentHTML('beforeend', htmlString)` parses `htmlString` using the target element's own
  position in the live document as parsing context. Per the HTML5 parsing spec, a `<form>` start tag is
  ignored when a "form pointer" is already active (i.e. an ancestor `<form>` exists) - nested forms
  aren't valid HTML, so the browser silently drops the inner `<form id="...">` open tag while keeping
  its children unwrapped. Verified live with a minimal repro
  (`node.insertAdjacentHTML('beforeend', '<form id="probe"><input></form>')` inside `ajax_target` ->
  `document.getElementById('probe')` is `null`, the `<input>` lands as a bare child of `ajax_target`
  instead) and confirmed the fix (parse into a detached `<div>` first, no form pointer active there,
  then `appendChild()` each resulting child node into the live DOM - `appendChild()` moves an
  already-built node without re-parsing/re-validating nesting) resolves it, also verified live.
- This is why none of the earlier `jQuery(...).append()` -> `insertAdjacentHTML()` swaps elsewhere in
  this whole project (`collabora/js/app.ts`, `importexport/js/app.ts`) hit the same bug: both of those
  insert small, hardcoded, `<form>`-free markup (a bare `<iframe>`, a bare loading `<div>`), which the
  nested-form parsing restriction never applies to regardless of context. The bug only bites when the
  inserted markup can itself contain a `<form>` **and** the insertion target lives inside an ancestor
  `<form>` - both true here, true nowhere else in this project.
- General takeaway for future `jQuery(...).append(htmlString)` -> native swaps: `insertAdjacentHTML()`
  is only a safe substitute when either the inserted markup is known not to contain a `<form>`, or the
  insertion point is known not to be inside one. Otherwise, parse into a detached container first (a
  `document.createElement('div')` + `.innerHTML = htmlString`, then move its children in) to match
  jQuery's actual (detached-fragment-first) behavior.
- `jQuery(this.et2.parentNode).trigger('show.et2_nextmatch')` (`group_list()`) ->
  `this.et2.parentNode.dispatchEvent(new Event('show'))`. The listening side
  (`et2_extension_nextmatch.ts`, shared/out-of-scope) uses `jQuery(...).on('show.et2_nextmatch', ...)`
  - jQuery's dot-namespace is a jQuery-only bookkeeping concept for filtering its own
  `trigger()`/`off()` calls, not part of the real native event type; jQuery's `.on()` still just calls
  `addEventListener('show', ...)` under the hood, so a plain native `'show'` event still reaches that
  handler.
- `jQuery.extend({}, x)` (2x, `acl()`'s row-click content default, `account()`'s registry params) ->
  object-spread `{...x}`, per `doc/ai/modernization.md`'s shallow-clone rule.
- `jQuery.extend(content, {...})` (`_acl_dialog()`) -> `Object.assign(content, {...})` (shallow merge
  into an existing target, same rule).
- `jQuery.map(select_owner.options.select_options, function(val, i){...})` (`check_owner()`) ->
  `Object.values(...).map(...).filter(label => typeof label !== 'undefined')` -
  `Object.values()` handles select_options being a plain object rather than an array (same as
  `jQuery.map()` accepting both), and the `.filter()` reproduces jQuery.map()'s behavior of dropping
  `null`/`undefined` callback results from the output array (the callback here has no `return` on some
  paths).
- `jQuery(...).toggle(bool)` (3x, `cf_type_change()`) -> `el.style.display = bool ? '' : 'none'`.
- `jQuery('#popupMainDiv')`/`jQuery('.et2_container')` + their `.outerWidth(true)`/`.outerHeight(true)`/
  `.width()`/`.height()` calls (`wizard_popup_resize()`) -> `document.getElementById`/
  `document.querySelector` + two new small private static helpers, `_outerSize()` (offsetWidth/Height +
  margin, jQuery's `outerWidth(true)`/`outerHeight(true)` equivalent) and `_contentSize()` (clientWidth/
  Height minus padding, jQuery's `.width()`/`.height()` equivalent) - the original combined expression
  (`et2_outer + (main_div_outer - main_div_content)`, i.e. et2's border-box+margin size plus main_div's
  own padding+border+margin) is preserved exactly, just factored through named helpers instead of
  jQuery's dimension API.
- `jQuery('.emailadmin_manual').fadeToggle()` (`wizard_manual()`, original comment: "not sure how to to
  this et2-isch") -> plain `style.display` toggle over `document.querySelectorAll(...)`. This drops the
  fade *animation* (no native one-liner equivalent without adding CSS transitions, out of scope for a
  TS-only pass) but preserves the show/hide behavior - documented in a comment, same as the pattern
  Ralf's already accepted for other hard-to-replicate jQuery effects in this project.
- `jQuery('#admin-mailwizard_output').hide()` / `jQuery('td.emailadmin_progress').show()`
  (`wizard_detect()`) -> `style.display = 'none'` / `style.display = ''` over
  `getElementById`/`querySelectorAll`.
- 3 stale `@param {jQuery.Event}` JSDoc tags (`aclGroup()`/`deleteGroup()`/`changeGroup()`) corrected to
  `{Event}`.

### `egw.json(...).sendRequest()` -> `egw.request()`

- 6 of the 13 sites were genuinely async (explicit `sendRequest(true)`, or no `_async` arg at all,
  which defaults to async) with no other complication - converted to `egw.request(...).then(...)`:
  `group()`'s delete case, `cf_type_delete()`'s type-delete request, `smime_generateKey()`,
  `smime_showGenerateKeyDialog()`'s keypair-creation request, `login_background_update()`, and
  `changeGroup()`.
- **6 sites use a `false` passed as the 5th (`_async`) argument to `egw.json(...)` itself**, rather
  than to `sendRequest()` - functionally identical to `sendRequest(false)` (per `JsonRequest`'s
  constructor: `sendRequest()`'s own arg only *overrides* the constructor's `_async` when given, so a
  bare `.sendRequest()` after `egw.json(..., false, ...)` stays synchronous) - **kept as
  `egw.json(...).sendRequest()`**, each with a comment pointing out the 5th-arg mechanism, since
  `egw.request()` is always async and there's no equivalent swap: `_acl_delete()`'s callback,
  `_acl_dialog()`'s `ajax_get_app_list` fetch, and 3 more inside `_acl_dialog()`'s save callback
  (`ajax_change_acl`, called up to 3 times depending on what changed).
- `deleteGroup()`'s `.sendRequest(false)` (already explicitly commented `// false = synchronious
  request` before this pass) - **kept as-is**, the original/simplest form of the same exception.
- **`load()`'s ajax-template-load request was deliberately left unconverted despite being async
  (`sendRequest(true)` with a callback)** - `egw.request()` cannot replicate it: `Json.request()`
  (`egw_json.ts`) always constructs its `JsonRequest` with `_context` set to the calling egw instance
  (`new JsonRequest(_menuaction, _parameters, null, this, true, this, this, self)`), and takes no
  `_callback` parameter at all - both incompatible with this call's `[..., this._ajax_load_callback,
  null, true, this]`, whose `null` context is called out in the code's own pre-existing comment as
  load-bearing ("It's important that the context is null, or etemplate2 won't load the template
  properly") - a non-null context would flow into `handleResponse()`'s `et2_load`-type response-plugin
  dispatch as a fallback `this` (`plugin.context ? plugin.context : this.context`), which is exactly
  what that comment warns against. Documented in a new comment at the call site. This is a new category
  of `sendRequest()` conversion exception beyond "genuinely synchronous" - a callback+null-context
  combination `egw.request()`'s fixed `(menuaction, params) => Promise` shape structurally cannot
  reproduce.

### function/closures -> arrow functions

- `_acl_delete()`'s `var callback = function(_button_id, _value){...}.bind(this)` -> arrow function
  (drops the now-redundant `.bind(this)`); `_acl_dialog()`'s `ajax_get_app_list` callback and its
  `sel_options.acl_appname.sort(function(a,b){...})` comparator -> arrows (neither used `this`).
- `getNextmatch()`'s `iterateOver(function(_widget){...}, this, et2_nextmatch)` -> arrow, per the
  tracker-established refinement: the explicit `this` passed as `_context` is always the same object
  an arrow's lexical `this` would resolve to here (both come from the same enclosing method), so the
  explicit binding was just belt-and-braces already.
- `submit_statistic()`'s `var that = this` + `var submit = function(){...that...}` and its own
  `Et2Dialog.show_dialog(function(_button){...submit()...})` callback -> both converted to arrows,
  `that` removed entirely (arrow's own `this` used directly).
- `emailadminActiveAccounts()`'s `var callbackDialog = function(btn){...}` and its nested
  `Et2Dialog.long_task(function(_val,_resp){...})` callback -> arrows (neither used `this`).
- `smime_generateKey()`'s `var self = this` + `function(_defaults){...self...}` callback -> the
  `function` converted to an arrow using `this` directly and `self` removed entirely, since this
  method's own enclosing scope has no other non-arrow frame between it and the callback (unlike the
  next case).
- **`smime_showGenerateKeyDialog()`'s `var self = this` was *kept*, not removed**, despite converting
  its own nested `function(_data){...self...}` callback to an arrow - a genuine exception, but a subtle
  one: the *immediately* enclosing scope here is `dialog.transformAttributes({callback(_button_id,
  _value){...}})`'s `callback` **method** (ES6 concise-method syntax, not `function(...)` and not an
  arrow - already correctly left alone, since Et2Dialog's `transformAttributes()`/`show_dialog()`
  callback contract binds that method's own `this` to the *dialog*, the same documented contract as
  status's/addressbook's Dialog-callback exception). Converting the *inner* `function(_data){...}` to
  an arrow is safe **only** because it keeps using `self` (never its own `this`) - if `self` had been
  removed and replaced with bare `this`, the arrow's lexical `this` would resolve through the enclosing
  `callback` method to the *dialog*, not this `AdminApp` instance, silently breaking
  `self.smimeKeyCreated`/`self.smime_setKeyState(...)`/etc. This is a new nested-scope wrinkle on the
  established "an existing `self`/`that` capture is reliable evidence of a dynamic-`this` contract"
  rule: it's evidence for the *enclosing* frame's contract, and an inner callback nested inside that
  frame still needs its own `self`-style indirection even after becoming an arrow, if the immediately
  enclosing scope isn't itself arrow/lexically-transparent for `this`.
- `login_background_update()`'s `function(_data){...}` callback -> arrow (no `this` used).
- `changeGroup()`'s `function(_msg){...}` callback (originally passed an explicit `_context` of `this`
  as `egw.json()`'s 4th arg, but never referenced `this` in its body) -> arrow, converted together with
  its `egw.request()` swap above.
- `deleteGroup()`'s `Et2Dialog.show_dialog(function(button){...})` callback -> arrow (uses only
  closured `egw`/`account_id`/`_widget`, never its own `this`).
- **`cf_type_delete()`'s outer `var callback = function(button, value){...}.bind(widget)` was *kept* as
  a plain `function`** - a genuine, explicitly-documented goal-6 exception: `.bind(widget)` deliberately
  rebinds `this` to the `widget` parameter (used via `this.eTemplate`/`this.getRoot()`/`this.getInstanceManager()`
  inside), not the enclosing `AdminApp` instance - an arrow function ignores `.bind()`'s `this`-argument
  entirely, so converting this one would silently break every `this.*` reference inside it. Its own
  inner pieces (the `for` loop, the delete-type request) were still modernized (`var`->`let`/`const`,
  `jQuery.extend`->spread, `sendRequest()`->`egw.request()`), just not the outer function's own
  keyword/binding.

### Not touched (out of scope)

- `load()`'s `ajax_exec` request (see above) - left as `egw.json(...).sendRequest()`, a new documented
  exception category (callback + load-bearing null-context, not just synchronicity).
- The dead code at the tail of `observer()`'s `case 'admin':` (an unconditional `if`/`else` above it
  already returns in both branches, so `this.egw.invalidate_account(...)` and everything below it can
  never execute) - pre-existing, not touched by any of the 6 goals (no `var`, jQuery, TS error, or
  `function(){}` uniquely required deleting it), so left alone per `doc/ai/modernization.md`'s "don't
  turn an unrelated bugfix into a drive-by cleanup" rule. `var`s inside it were still converted to
  `let`/`const` for goal 2 (including fixing a `var nm` redeclared a second time in the same
  now-block-scoped `switch` body - changed to a plain reassignment, not a second declaration, since
  `let`/`const` don't allow redeclaring the same binding the way `var` silently permitted) - flagged
  here for whoever eventually looks at deleting it.
- `admin.index.xet` still using the legacy `<nextmatch>` tag rather than `<et2-nextmatch>` (see the
  import-removal note above) - a template migration, out of scope for an app.ts-only pass.

## developer/js/app.ts, esyncpro/js/app.ts, aitools/js/app.ts, bookmarks/js/app.ts, collabora/js/app.ts (done)

These five are each their own nested git repo (same shape as `tracker`/`status` - root `.gitignore`
excludes them, `cd` into each to see/commit/push). All five are much smaller than the files above
(57-961 lines) and came out of the same pass together. Verified for all five: `var`, jQuery,
`egw.json(...).sendRequest()`, and `function(...)`/`var self`/`var that` all at 0 (no undocumented
exceptions), and 0 TS errors each (`node_modules/.bin/tsc --noEmit -p tsconfig.json`, filtered to each
file's own path). Unlike the larger files above, the specific per-fix root causes for these five weren't
individually written up here - flag any of these for a closer look if a bug surfaces in one later,
rather than assuming the reasoning was captured.

`aitools/`'s working tree also has an unrelated, unstaged change to `src/Hooks.php` (comments out
`notifyAll()`'s trigger-check condition) - confirmed to be Ralf's own in-progress work, not part of
this pass, left untouched.

## filemanager/js/app.ts and filemanager/js/filemanager.ts (done)

`filemanager/js/app.ts` (19 lines) is just an import + `app.classes.filemanager` registration - the
real code lives in `filemanager/js/filemanager.ts` (1944 lines, `filemanagerAPP`), loaded that way
specifically "to ensure proper loading/cache-invalidation for Collabora extending filemanagerAPP"
(the file's own comment) - `collabora/js/app.ts` (done separately, its own nested repo) subclasses
`filemanagerAPP`. Nothing needed changing in `app.ts` itself.

`filemanager.ts`: `var`/jQuery/`sendRequest` (bar one confirmed-genuine `sendRequest(false)`) all
cleared, file TS-clean. Goal 6 finished in a follow-up pass: 3 of 6 `function(...)` sites flagged
earlier as likely-missed conversions were confirmed safe and converted to arrows (`paste_exec`, and
two `iterateOver(function(widget){...}, null, ...)` callbacks - none referenced `this`, and the latter
two were already invoked with an explicit `null` context, so behavior is identical either way; also
converted a third, similarly-`this`-free `Et2Dialog.show_dialog(function(_button){...})` callback found
in the same pass). The remaining 2 (`Et2Dialog.show_prompt()`'s callback and its nested
`iterateOver()` callback, both reading `this.my_data`) are a genuine, verified exception - confirmed by
reading `Et2Dialog.show_prompt()`'s implementation (`api/js/etemplate/Et2Dialog/Et2Dialog.ts`), which
wraps the callback and invokes it via `_callback.call(this, ...)` with its own `this` (carrying
`my_data`) - an arrow function would ignore that `.call()`-supplied context entirely and capture the
outer method's `this` instead, a real behavior change. Added a comment explaining why, matching the
project's established convention for documented exceptions.

#### Unrelated pre-existing bug found via a user report, not caused by this pass

Reported by Ralf: dragging files to a new folder in Filemanager threw `Uncaught TypeError:
(egw.preference || []) is not iterable` in `drop()`. Git history shows the offending line
(`[...(<string[]>egw.preference('drop_history', this.appname) || [])]`) hasn't been touched since a
2021-era refactor - confirmed via a live console check that `egw.preference('drop_history',
'filemanager')` can come back as a plain `{"0":...,"1":...}` object rather than a real array (depends
on how the server last JSON-encoded that particular preference), which a spread can't iterate. Fixed
with `Object.values(egw.preference(...) || [])` - same defensive pattern already used elsewhere in this
project for preference/select-option values that may be an object or an array. Verified live against
the real (non-array-shaped) preference data before and after the fix.

A second, identical copy of the exact same line existed in `paste_enabled()` (used to build the
"Einfügen" context-menu submenu's target-folder list) - reported separately by Ralf
(`egw_action: enabled callback of action 'file_drop_symlink_paste' threw ... TypeError: (egw.preference
|| []) is not iterable`) after the first fix was already live, since it's a distinct code path that
happens to read the same preference the same unsafe way. Fixed identically. Prompted a repo-wide sweep
(`grep -n "\[\.\.\."` across all app.ts/filemanager.ts files touched in this project) for the same
raw-spread-over-a-possibly-object-shaped-value pattern - see `doc/ai/modernization.md`'s new standing
rule for what the sweep found and fixed (`resources/js/app.ts`'s `app.calendar.state.owner` spread had
the identical latent risk, fixed the same way) and confirmed safe (`filemanager.ts`'s
`[...new Set(clipboard.type)]`, which round-trips through the client's own `sessionStorage`, never
through a server JSON-encode step, so it can't hit this particular shape mismatch).

## calendar/js/app.ts (done)

The largest file in this project by line count, and by far the most `var`/jQuery usage of any file here
- 4525 lines, 253 `var`, ~90 jQuery uses, 97 TS errors, 14 `sendRequest()` sites (smallpart has more TS
errors - 167 - but far less `var`/jQuery to modernize). Several genuinely new categories of goal-6
exception turned up that weren't seen in any smaller file. Browser-verified (see bottom of this
section), not just tsc/build-clean.

### Legacy widget imports

- `et2_date`, `et2_selectbox`, `et2_nextmatch`, `et2_button`, `et2_template`, `et2_grid`, `et2_iframe`,
  `et2_number` - all used only for type casts/annotations in this file -> `import type`. `et2_grid` and
  `et2_iframe`/`et2_button`/`et2_number`/`et2_nextmatch` are genuine distinct legacy implementations with
  no (or an unsafe-to-substitute) web-component equivalent, same reasoning as timesheet's `et2_grid` and
  tracker's `et2_nextmatch`/`et2_button` - `import type` still applies since this file only ever uses
  them as types, never as runtime values.
- `et2_widget`, `et2_valueWidget`, `et2_IInput` - kept as **value** imports: all three are passed as
  genuine runtime `instanceOf`/`iterateOver`-type-filter arguments. `et2_IInput` looked like it might be
  interface-only but is actually dual-declared (`export interface et2_IInput {...}` + `export const
  et2_IInput = "et2_IInput"`) specifically so it can be used this way - confirmed via
  `et2_core_inheritance.ts`'s `instanceOf()` and the same pattern already used elsewhere in
  `et2_extension_nextmatch.ts` itself.
- `et2_container` removed entirely (not even `import type`): `sidebox_et2`'s declared type was stale -
  `etemplate2.widgetContainer` is really `Et2Template` (confirmed in `etemplate2.ts`), not the legacy
  `et2_container`. Retyped the field as `Et2Template` (newly imported, type-only) and dropped the one
  now-unnecessary `<et2_container>` cast, which TS was already flagging as a mistake (TS2352).
- `egw`/`egw_getFramework` import removed - the familiar ambient-global fix from every prior file in
  this series.
- `egwAction`/`egwActionObject` had no import at all (`TS2304`) - added `import type` from
  `api/js/egw_action/egw_action.ts`, same as addressbook.
- `Et2CalendarOwner` (used in `participantOnChange()`) doesn't exist anywhere in the repo - a typo for
  the already-imported `CalendarOwner` (this file's own `./CalendarOwner.ts`, confirmed by every other
  cast in the file correctly using `CalendarOwner`). Fixed the cast; not an import problem.

### TS errors fixed (97 total) - selected highlights, not exhaustive

- **`app.calendar.X` (`state`, `update_state`, `_scroll_disabled`, `date`, `merge`,
  `state_update_in_progress`, `_fetch_data`, `sidebox_hooked_templates`, `videoconference_getRecordings`,
  ~35 sites)**: the familiar `app`-typed-as-`{[propName:string]: EgwApp}` blind spot, but here it's the
  app referencing **itself** by name instead of `this` - mostly leftover habit/inconsistency from before
  arrow functions were used (the same method often mixes `this.state` and `app.calendar.state` in the
  same block). Resolved case by case:
  - Where the reference is in a plain class method (no nested non-arrow function breaking `this`):
    replaced with `this.X` directly (`toolbar_action`, `filter_change`, `sidebox_merge`,
    `_fetch_data`'s response handler, etc.) - a real fix, not just casting the error away.
  - Where `this` is genuinely dynamic (see `scroll_animate` below) and can't be relied on: cast to
    `<CalendarApp>app.calendar` at each site instead, since `app.calendar` *is* this exact class - a more
    precise version of the `<statusApp>app.status` pattern from `status/js/app.ts`.
  - `_scroll_disabled` had no class field at all (silently bolted on at runtime) - added a real declared
    field (`_scroll_disabled : boolean = false;`).
  - `videoconference_getRecordings` on `app.status` is EPL's Rocket.Chat/videoconference status
    extension - same "other app's EPL extension, cast to `<any>`" pattern as infolog/addressbook/status.
- **`this.state` field typing**: `state = {date: new Date(), view: ..., owner: ..., ...}` was too
  narrowly inferred (e.g. `date: Date` made `typeof this.state.date == "string"` narrow to `never`).
  Added an explicit type with `date: Date | string` and a `[key: string]: any` index signature (the
  state object accumulates many more ad-hoc properties - `startdate`, `filter`, `weekend`, `cat_id`, ...
  - throughout the class; enumerating all of them wasn't worth it for an app.ts-only pass). One
    root-cause fix that resolved ~6 separate errors.
- **`super.merge(action, selected)` in `sidebox_merge()`** (`TS2339`, `Property 'merge' does not exist on
  type 'EgwApp'`): not a typing gap - a **real, previously-silent bug**. `git log -p` on
  `api/js/jsapi/egw_app.ts` shows `EgwApp.merge()` was renamed to `mergeAction()` (and made `async`) at
  some point; this call site was never updated, so `super.merge(...)` would throw
  `TypeError: ... .merge is not a function` the moment a user tries "merge to document" from listview.
  Fixed to `super.mergeAction(action, selected)` - the real fix, not a cast.
- **`et2_date.set_hours`/`.set_minutes()` in `set_alarmOptions_WD()`**: these methods don't exist on
  `Et2Date` (confirmed - grepped the whole repo, no such methods anywhere) - another **real,
  previously-silent bug**: every call to `set_alarmOptions_WD()` that reached this branch has always
  thrown and aborted before setting the alarm time/label. Rather than just `<any>`-casting past it,
  computed a local midnight-based `Date` and used that for the alarm-relative-to-midnight calculation
  instead of mutating the real `start` field widget (which is what the original, broken code appeared to
  intend, but would have been a separate, worse side effect - clobbering the user's actual event start
  time - had the method calls not been broken all along). Documented the reasoning inline since this is
  a judgment call, not a mechanical fix.
- **`et2_grid`'s `cells` (private, no public accessor)** in `set_alarmOptions_WD()`: unlike
  `_children`/`_inst` elsewhere, `et2_widget_grid.ts` has no public way to reach a cell's widget at all -
  cast the `alarm` variable itself to `<any>` rather than adding an accessor to the shared file.
- **`participant.value` (protected) in `participantOnChange()`**: same class of fix as addressbook's
  `Et2SelectCountry.value` - swapped to the public `getValueAsArray()` accessor (which also fixed the
  paired "`.length` doesn't exist" errors, since `.value`'s declared type has no array shape).
- **`sprintf()`'s untyped 0-arg signature**: `egw_action_common.ts` declares `export function sprintf()
  {...}` (relies on `arguments` internally, no typed params) - every call with format args errors under
  TS. This isn't calendar-specific (confirmed the same function is called with args from
  `smallpart/js/app.ts` too), so rather than touch the shared file, aliased it once at the top:
  `import {sprintf as _sprintf} from ...; const sprintf : (format: string, ...args: any[]) => string =
  _sprintf;` - a single 2-line fix instead of `<any>`-casting all 10 call sites individually.
- **`_autorefresh_timer`'s type**: `setInterval()`/`setTimeout()` return `NodeJS.Timeout` here rather
  than `number` (an ambient-types resolution quirk, not calendar-specific) - retyped the field
  `ReturnType<typeof setInterval>` instead of forcing `number`.
- **Redeclared `var` inside `switch` blocks (no per-case braces)**: `toolbar_action()`'s `case 'add'`/
  `case 'today'` both declared `tempDate`/`today`, and `setState()`'s `case 'month'`/`case 'day'`/
  `default` all declared `end` - all in the *same* switch's shared block scope, which is fine for `var`
  but is a redeclaration error for `let`/`const`. Wrapped each case body in its own `{ }` block (doesn't
  affect the intentional `case 'month':` fallthrough into `case 'weekN':`, since fallthrough only cares
  about the absence of `break`, not braces).
- **Several "declared inside an `if`, read again in a sibling branch or after a loop" var-hoisting
  cases** (`cal_open()`'s `js_integration_data`, `setState()`'s `val`/`loading`/`last_format`) - same
  "needed care" treatment as addressbook's `content` fix: hoisted the bare declaration above the
  conditional/loop, matching the exact scope `var` was already relying on, with a comment explaining why.

### jQuery removed (~90 uses) - the trickiest category in this file

Most were the same mechanical swaps as every prior file (`.hide()`/`.show()` -> `style.display`,
`.css()` -> `style.*`, `.toggleClass()`/`.hasClass()` -> `classList`, `jQuery.extend()` ->
`Object.assign()`, `jQuery.map()` -> `Object.values()`, `jQuery.isEmptyObject()` ->
`Object.keys(x??{}).length===0`, `:visible` -> `.checkVisibility()`, `jQuery(fn)` (the `$(document)
.ready(fn)` shorthand) -> a `document.readyState` check). Three patterns were new to this file:

- **jQuery's dot-namespaced custom events as a dedup/pub-sub mechanism** (`'show.calendar'`,
  `'hide.calendar'`), used in `observer()`, `push_infolog()`, and `_set_autorefresh()`:
  - Two sites did `.off('show.calendar').one('show.calendar', fn)` purely to make sure only the
    *latest* queued handler fires if the method runs again before the tab becomes visible. Replaced with
    a small private helper, `_bindShowOnce(el, handler)`, backed by one tracked field
    (`_pending_show_handler`) that removes any previous handler before adding the new one with
    `{once: true}` - a faithful native equivalent, not just a syntax swap.
  - `_set_autorefresh()`'s `'hide.calendar'`/`'show.calendar'` handlers both **unconditionally**
    self-removed (`jQuery(e.target).off(e)`) immediately after running every time - i.e. they were
    already behaviorally identical to `.one()`/`{once: true}`, just implemented the hard way. Simplified
    to plain `addEventListener(type, handler, {once: true})`, no tracking needed.
  - `destroy()`'s `jQuery('body').off('.calendar')` and a `jQuery(window).off('resize.calendar'+id)` in
    the same method are both **dead code** - `git log -S` on the corresponding `.on(...)` binds shows the
    live handlers they were meant to clean up were removed from the codebase in 2022, but the cleanup
    calls were never removed. Deleted both (and the now-fully-unused surrounding `if` block/local var)
    rather than "converting" no-op cleanup for handlers that no longer exist.
- **`widget.div`/`widget.loader` are jQuery objects** (set by `et2_widget_timegrid.ts`/
  `et2_widget_view.ts`, outside this pass's scope) - `.css("height","")` -> `.div[0].style.height=""`,
  `.loader.show()`/`.hide()` -> `.loader[0].style.display = ''/'none'`, i.e. unwrap via `[0]` rather than
  touching the widget files that create these jQuery-wrapped properties.
- **`_scroll()`'s `scroll_animate` function relies on `jQuery(this)` silently failing** when `this` isn't
  a DOM node (see the goal-6 section below - `this` is sometimes the `CalendarApp` instance, sometimes a
  DOM element) - replaced with an explicit `this instanceof Element ? this.closest(...) : undefined`,
  which is clearer than relying on jQuery's forgiving-selector-engine side effect and behaves identically.

### `egw.json(...).sendRequest()` -> `egw.request()` (14 sites)

13 of 14 were straightforward async swaps (several combined with a `function` -> arrow conversion for
the callback, same as prior files). One - `_unlock()`, bound to `beforeunload` - is a **new exception
category**, not literally `sendRequest(false)`: it's constructed with `"keepalive"` as the async mode
(`egw.json(url, params, null, this, "keepalive", null)`), which makes `sendRequest()` use
`navigator.sendBeacon` under the hood specifically so the request survives the page unload - functionally
the same *purpose* as the doc's `sendRequest(false)` exception (an unload handler that can't rely on a
normal async round trip), just a different mechanism. `egw.request()` has no keepalive/sendBeacon mode,
so there's no equivalent swap - left as-is with a comment explaining why, generalizing the existing
exception rather than adding a contradictory one.

### function/closures -> arrow functions

The large majority converted cleanly (many `iterateOver(function(w){...}, this, SomeType)` calls where
the explicit context arg equals the arrow's lexical `this` anyway, per the tracker/addressbook precedent
of that being safe). Two new exception shapes worth recording:

- **`scroll_animate` (`_scroll()`)**: called with `this` = the `CalendarApp` instance from the keyboard
  shortcuts (`scroll_animate.call(this, ...)`) *and* with `this` = a plain DOM element from the swipe
  handler (`scroll_animate.call(event.target.closest(...), ...)`) - two genuinely different, both
  load-bearing, `this` values depending on the call site. Left as a plain `function`, matching goal 6's
  documented exception, with a comment. The *nested* callbacks inside it that don't use `this` at all
  were still converted to arrows for consistency, same as addressbook's no-context-needed predicates.
- **`_et2_view_init()`'s `jQuery.proxy(function(){...}, <index>)`**: `this` here is deliberately bound to
  a plain number (a splice index), not any object - a `jQuery.proxy(fn, otherThis)` case where
  `otherThis` is neither the enclosing method's `this` nor a widget, just data smuggled through the
  binding mechanism. Kept as a plain function, converted to `fn.bind(index)` for the jQuery removal
  (goal 4) without touching the binding itself (goal 6 exception).
- Also worth noting: several `var view`/`index`/`name` values were captured into an ad-hoc
  `jQuery.extend({}, {view, index, name})` "context object" purely to survive `var`'s lack of
  per-iteration scoping inside a `for...in` loop (a workaround now-obsolete once those loop variables
  became `const`/`let`, which *do* get a fresh per-iteration binding) - removed the whole capture-object
  workaround and let the closure reference `view`/`index` directly, converting that callback to a
  faithful `{once: true}`-bound arrow in the same edit.

### Not touched (out of scope) - confirmed pre-existing, not introduced by this pass

- **`cal_delete()`'s shadowed `cal_event`**: `let cal_event = ...` at the method's top, then a *second*,
  separate `let cal_event = ...` declared inside `if(matches){...}` - shadows the outer binding within
  that block only, so the intended update to the series-level event data is silently discarded once the
  block ends. A real logic bug, but untouched by any of the 6 goals (it was already valid `let`, not
  `var`) - flagged here, not fixed.
- **`comment_add_vfs()`-style `Promise.all([wait,wait])` equivalents**: none found in this file (checked,
  unlike tracker) - no new finding of that shape here.
- A pre-existing, unrelated bug surfaced during browser verification (see below): `_update_events()`'s
  `multiple_owner` computation (`typeof state.owner != 'string' && state.owner.length > 1 && ...`) throws
  if `state.owner` is ever `undefined` (`typeof undefined != 'string'` is `true`, so the `&&` chain
  proceeds to `undefined.length`). Confirmed byte-for-byte identical logic in the pre-modernization
  source (only `var` -> `const`), so this is not a regression - flagged for whoever next touches
  `_update_events()`.

### Browser verification

Built (`rollup -c`) and exercised live against a real EGroupware instance (day/week/multi-week/list/
planner views via the toolbar and via direct `update_state()` calls, Today/Previous navigation, the
weekend toggle, and switching away to another app and back). Confirmed via a `git stash`/rebuild/reload
round-trip that the one console error seen throughout (`et2_calendar_event._update`: "The provided
string color doesn't have a correct format", in `et2_widget_event.ts`, unrelated to this file) reproduces
identically on the pre-modernization code - a pre-existing bug, not a regression. No other errors
surfaced under normal navigation; the `state.owner` bug above only appeared when driving `update_state()`
directly from the console with a partial, hand-built state object, not through any real UI path.

### Follow-up: bare `egw.X` vs `this.egw.X` (not part of the 6-goal pass, a separate ask)

After the pass above, went through this file's ~98 remaining bare `egw.` call sites (as opposed to
`this.egw.`) by hand, checking each against its module's registration
(`MODULE_GLOBAL`/`MODULE_WND_LOCAL`/`MODULE_APP_LOCAL` in the relevant `api/js/jsapi/egw_*.ts` file) -
this is *not* a purely stylistic distinction: `egw(appname, wnd)` (what produces `this.egw`, per
`EgwApp`'s constructor) returns a real, distinct clone via `egw_core.ts`'s `createEgwInstance()`, with
window/app-local modules merged in on top of the shared base object bare `egw` resolves to. Found and
fixed **19 genuinely miscategorized sites** (used bare `egw.X` where the module is `MODULE_WND_LOCAL`,
so `this.egw.X` is the correct/safe one) across three modules:

- **`loading_prompt`** (`Message` class, `egw_message.ts`) - 7 sites (`et2_ready()` x2, `setState()`,
  two `window.setTimeout()` arrows, `_update_events()`, `_fetch_data()`'s response handler). One of
  these - `et2_ready()`'s handling of `'calendar.edit'`/`'calendar.add'` - runs *inside the add/edit
  popup itself*; a popup adopts its opener's `window.egw` (a documented, previously-hit real bug - see
  `et2-reconnect-egw-rejoin-fix` memory), so the bare version there could plausibly have been
  hiding/showing the wrong window's loading spinner.
- **`open`/`open_link`/`link`** (`Open` class, `egw_open.ts`) - 7 sites across `linkHandler()`,
  `toolbar_action()`'s `'add'` case, `cal_open()` (x3), and `sidebox_merge()` (x2 on one line) - these
  decide what window a new popup opens relative to.
- **`request`/`message`/`callFunc`** (`Json` class, `egw_json.ts`) - 5 sites: `cal_delete()`'s two
  `egw.request(...)` calls, and `joinVideoConference()`'s `egw.request(...)`/`egw.message(...)`/
  `egw.callFunc(...)`. Every *other* `.request()`/`.json()`/`.jsonq()` call in this file already
  correctly used `this.egw` - these 5 were the only exceptions.

Left the other ~79 sites as bare `egw.X` - confirmed genuinely safe either way, not just untouched out
of caution:
- `dataGetUIDdata`/`dataStoreUID`/`dataHasUID`/`dataKnownUIDs`/`dataSearchUIDs`/`dataDeleteUID` all live
  on `DataStorage` (`egw_data.ts`), registered `MODULE_GLOBAL` - one shared cache regardless of which
  egw object you go through. (`dataFetch` is the one data-family method on the *app-local* `Data` class,
  and its one call site already correctly used `this.egw.dataFetch(...)`.)
- `preference`/`set_preference`/`user`/`lang`/`config`/`grants`/`app` are all on `MODULE_GLOBAL` classes.
- `egw.jsonq(...)` (`push_calendar()`) - `Jsonq` is `MODULE_GLOBAL` too (a deliberately shared queue,
  matching this doc's own note that it's a distinct, non-window-scoped mechanism from `.request()`).

**Verified before/after** for each of the three fixed modules, not just tsc/build-clean: rebuilt and
re-exercised the day/week view switches (`loading_prompt`, already covered above); spied on
`this.egw.open`/`.link`/`.open_link` from the console and confirmed `toolbar_action({id:'add'})` and
`sidebox_merge()` (with a fake merge-target widget) call them with the expected arguments; spied on
`this.egw.request`/`.message`/`.callFunc` and confirmed `joinVideoConference()` (called directly with
fake video-conference data) invokes them correctly for both the success and error-response branches.
`cal_delete()`'s two sites weren't separately live-tested (reaching them requires clicking through a
real delete-confirmation dialog against live data, which risks actually deleting something) - relied
instead on tsc plus the structurally-identical arrow-function verification from the other four sites.
No new console errors after the fix; the one pre-existing color-format error was still the only one seen.

## resources/js/app.ts, webauthn/js/app.ts, guacamole/js/app.ts, rag/js/app.ts, preferences/js/app.ts, openid/js/app.ts (done)

Six small files done together in one pass (main repo: `resources`, `preferences`; own nested git repos:
`webauthn`, `guacamole`, `rag`, `openid` - same shape as `tracker`/`status`/etc., `cd` into each to
see/commit/push). All six were already close to fully modern - none had `var`, jQuery, `sendRequest()`,
or a `function(){}`/`self`/`that` closure needing conversion except one apiece in `resources`/`webauthn`
- most of the work was the by-now-familiar ambient-global import removal (`egw`/`app` are declared in
`egw_global.d.ts`'s `declare global {}`, unconditionally included via tsconfig's `**/*.d.ts` - importing
them from `egw_global` is both wrong for TS, `TS2305`, and unnecessary) plus a handful of small
per-file TS-error fixes. None of the six use any legacy `et2_*` widget imports at all, so goal 1 was a
no-op for this batch.

### resources/js/app.ts

Baseline: 0 `var`, 1 jQuery use, 4 TS errors, 0 `sendRequest()`, 1 `function(){}.bind(this)`.

- Removed `import {egw} from ".../egw_global"` (unused named import, `egw` is ambient) - same fix as
  every other file below.
- **`app.calendar.state`/`.update_state()` don't exist on type `EgwApp`** (3 sites: `view_calendar()`'s
  `show_calendar` closure, `sidebox_change()`'s `owner` merge, and its `update_state()` call): `app` is
  typed `{classes: any, [propName: string]: EgwApp}`, so `app.calendar` types as plain `EgwApp` - the
  same EPL/stylite-blind-spot-shaped gap as `app.stylite`/`app.status`/`app.rocketchat`/`app.policy`
  elsewhere in this project, except `calendar` is a real main-repo app with its own exported, properly
  typed class (`export class CalendarApp extends EgwApp` in `calendar/js/app.ts`, confirmed to declare
  both `state` and `update_state()`). Rather than the usual `<any>` cast, imported `import type
  {CalendarApp} from "../../calendar/js/app"` and cast `<CalendarApp>app.calendar` at each site - matches
  an existing precedent already in the codebase (`calendar/js/et2_widget_planner.ts:2004` does the exact
  same `<CalendarApp>app.calendar` cast), and is more precise than a blanket `<any>` since a real typed
  class already exists.
- jQuery: `jQuery.extend([], app.calendar.state.owner) || []` (`sidebox_change()`) -> **originally**
  converted to a spread, `[...((<CalendarApp>app.calendar).state.owner || [])]` - also dropped the
  now-visibly-dead `|| []` after the original `jQuery.extend()` call (that call always returns its own
  first-arg target, so the fallback could never actually fire; the new spread's own `|| []` on the
  *input* replaces it correctly). **Corrected in a later pass**: a spread requires a real iterable, but
  `jQuery.extend([], x)` tolerates `x` being a plain `{"0":...,"1":...}` object (copies key-by-key onto
  the array target) - the same shape `app.calendar.state` can come back as after a server round-trip
  (found via the `filemanager/js/filemanager.ts` `drop_history` regression - see that file's section and
  `doc/ai/modernization.md` for the general rule). Changed to `Object.values((<CalendarApp>app.calendar)
  .state.owner || [])`, which tolerates both shapes like the original jQuery call did.
- `view_calendar()`'s `let show_calendar = function(res_ids) {...}.bind(this);` -> arrow function
  (`const show_calendar = (res_ids) => {...};`), dropping the now-redundant `.bind(this)` - the callback
  only ever reads `this.egw`/enclosing locals, never relies on being invoked as a method.

### webauthn/js/app.ts

Baseline: 0 `var`/jQuery/`sendRequest()`, 4 TS errors, 1 `function(){}`.

- Removed `import { app } from '../../api/js/jsapi/egw_global'` (ambient-global fix).
- **`navigator.credentials.create()`'s resolved `data` typed as generic `Credential`** (3 errors:
  `.rawId`, `.response.clientDataJSON`, `.response.attestationObject` don't exist on that base type):
  annotated the `.then()` callback param as `(data : PublicKeyCredential)` for the first (registration
  always resolves a `PublicKeyCredential` per the WebAuthn spec), which fixed `.rawId`/`.id`/`.type`.
  `.response` still typed as the more generic base `AuthenticatorResponse` (which only declares
  `clientDataJSON`, not `attestationObject`) even off a `PublicKeyCredential` - added a second,
  file-local cast (`const response = <AuthenticatorAttestationResponse>data.response`) with a comment
  explaining why (registration/`create()` always returns an *attestation* response, DOM lib's typing is
  just more generic than the runtime guarantee), used for both `.clientDataJSON`/`.attestationObject`
  reads instead of casting at each property access.
- `register()`'s `Uint8Array.from(..., function(c){ return c.charCodeAt(0); })` -> arrow
  (`(c) => c.charCodeAt(0)`) - matches the two sibling `Uint8Array.from()` calls a few lines above/below
  that already used an arrow for the identical mapper.

### guacamole/js/app.ts

Baseline: already 0 `var`/jQuery/`sendRequest()`/`function(){}` - only 2 TS errors and the ambient-global
import needed fixing.

- Removed `import {egw, app} from "../../api/js/jsapi/egw_global"` (`TS2305` x2, ambient-global fix) -
  **and** the file's own local `declare global { var framework; }`, which duplicated `framework`'s
  existing ambient declaration in `egw_global.d.ts` (`var framework : any;`) - not itself a TS error
  (compatible redeclaration), but dead weight once the file was already being touched for the identical
  fix on `egw`/`app`, so folded into one comment covering all three ambient globals rather than leaving
  a redundant local re-declaration behind.

### rag/js/app.ts

Baseline: already 0 `var`/jQuery/`sendRequest()`/`function(){}` - only 1 TS error (`import {app} from
".../egw_global"`, `TS2305`) and the ambient-global import needed fixing. `et2_ready()`'s
`super.et2_ready.apply(this, arguments)` and `search()`'s `this.nm.applyFilters(...)` were already
clean/typed with no errors, nothing else to change.

### preferences/js/app.ts

Baseline: already 0 `var`/jQuery/`sendRequest()`/`function(){}` - only 1 TS error and the ambient-global
import needed fixing.

- Removed `import {egw} from "../../api/js/jsapi/egw_global"` - this one was a genuinely **unused**
  import even before the ambient-global fix (only `this.egw`, the instance property, is used in the
  file; the bare `egw` global was never referenced) - same ambient-global reasoning applies regardless.
- Removed a leftover `// @ts-ignore` sitting directly above `app.classes.preferences = PreferencesApp;`
  - with `app` correctly resolving as the ambient global (same as every other file in this batch, none
    of which needed a `@ts-ignore` for their own `app.classes.X = ...` line), the suppressed error no
    longer exists, so the directive was pure vestigial noise, not a documented exception for anything
    in the 6 goals - removed rather than left in place.
- `Et2Dialog` (imported as a value, `import {Et2Dialog} from ".../Et2Dialog/Et2Dialog"`) is never
  referenced anywhere in the file - a pre-existing, unrelated dead import (not a legacy `et2_*` name
  needing goal-1 conversion, already the modern web-component class) - left alone, out of scope for
  this pass (`noUnusedLocals` isn't enabled in this repo's `tsconfig.json`, so it wasn't flagged as a
  TS error either).

### openid/js/app.ts

Smallest file in this batch (33 -> 34 lines). Baseline: already 0 `var`/jQuery/`sendRequest()`/
`function(){}` - only 1 TS error (`import {app} from ".../egw_global"`, `TS2305`) and the ambient-global
import needed fixing. `(<AdminApp>app.admin)?.enableAppToolbar(et2, name)` was already correctly typed
via the existing `import type {AdminApp} from "../../admin/js/app"` - no change needed there.

## projectmanager/js/app.ts (done)

1239 -> 1315 lines. `projectmanager/` is its own nested git repo (same shape as `tracker`/`status` -
`cd` into it to see/commit/push). Baseline: 11 `var`, 14 jQuery hits, 12 `function(){}`, 3
`sendRequest()` (all async, none genuinely synchronous), 10 TS errors.

### Legacy widget imports

- `et2_nextmatch` (`et2_extension_nextmatch.ts`) and `et2_gantt` (`et2_widget_gantt.ts`) both stayed
  **value** imports, unconverted - both are real, distinct legacy implementations (not zero-member
  compat shims), and this file passes both as runtime `instanceof`-style filter values to
  `iterateOver(_callback, _context, _type)` all over the file (`show()`, `setState()`, `getState()`,
  `getNextmatch()`), plus `et2_nextmatch` is also used as a real `instanceOf(et2_nextmatch)` runtime
  check in `element_add_app_change_handler()`. Same reasoning as tracker's/admin's identical exception.
- `Et2LinkAdd` and `EgwFrameworkApp`/`FilterInfo` were imported as *value* imports despite being used
  only as type annotations (`widget : Et2LinkAdd`, `fwApp : EgwFrameworkApp`, `: FilterInfo` return
  type) - switched all three to `import type`, same reasoning as every other file in this project.
  `EgwApp`, `etemplate2` correctly stayed value imports (`extends EgwApp`,
  `etemplate2.getByApplication(...)`).
- `import {egw, egw_getFramework} from ".../egw_global"` removed entirely - the familiar
  ambient-global fix (ambient `var egw`/`function egw_getFramework()` inside `egw_global.d.ts`'s
  `declare global {}`, not real exported module members).

### TS errors fixed (10 total)

- **`register_app_refresh(...)` - `TS2304: Cannot find name`**: unlike `egw`/`egw_getFramework`, this
  one is a *genuinely undeclared* ambient global - `window.register_app_refresh` is set at runtime by
  `api/js/jsapi/jsapi.js`, but (unlike `egw_getFramework`) never gets a matching `declare function` in
  `egw_global.d.ts`, and no other `.ts` file in the repo calls it. Rather than add it to the shared
  `.d.ts` (out of scope) or cast to `<any>` at the one call site, added a small local
  `declare function register_app_refresh(...)` at the top of this file - a legitimate, file-scoped
  ambient declaration that doesn't touch the shared file.
- **`app.projectmanager.show()`/`.show_filemanager()`/`.linkHandler()` don't exist on type `EgwApp`**
  (4 sites: `et2_ready()` x3, `linkHandler()`'s own retry `setTimeout`): `app` is typed
  `{classes: any, [propName: string]: EgwApp}`, so `app.projectmanager` widens back to the *base*
  `EgwApp` type even though it's genuinely this same `ProjectmanagerApp` instance - the familiar
  EPL-blind-spot-shaped gap, but here for the app's own class rather than a sibling app. Rather than
  `<any>`-cast (the usual EPL-blind-spot fix, appropriate when the real shape is genuinely unknown),
  cast to `<ProjectmanagerApp>app.projectmanager` at each site instead - more precise, matching
  status/js/app.ts's established refinement ("cast to `<statusApp>` instead of `<any>` - more precise,
  since we actually know the real shape here"). Deliberately did **not** replace these with `this.X()` -
  even though `this` is normally the same object, `app.projectmanager` and `this` could theoretically
  diverge if the app were torn down and recreated while an old closure (e.g. the `linkHandler()` retry
  timeout) was still pending, so the cast preserves the original global-registry lookup instead of
  silently changing what's being called.
- **`this.et2._inst`** (2 sites: `p_element_delete()`, `erole_refresh()`'s default case) - same
  `getInstanceManager()` fix as every other file in this project.
- **`nm.instanceOf(...)` doesn't exist on type `Et2WidgetClass | et2_widget`**
  (`element_add_app_change_handler()`): `getParent()`'s return type is a union where only the legacy
  `et2_widget` side declares `instanceOf()`. Typed the walking variable `let nm : any` instead of
  casting at every step of the `while` loop - simplest fix for a loop that reassigns `nm` on each
  iteration while climbing the widget tree.

### jQuery removed

- `jQuery.proxy(this.linkHandler, this)` (constructor, passed to `register_app_refresh()`) ->
  `this.linkHandler.bind(this)` - the direct native equivalent for proxying an existing method
  reference (as opposed to wrapping an inline function, which gets an arrow instead elsewhere in this
  project).
- `jQuery(et2.DOMContainer).one('clear', function(){...})` (`et2_ready()`) ->
  `et2.DOMContainer.addEventListener('clear', () => {...}, {once: true})` - confirmed `'clear'` is a
  real native event, dispatched by `etemplate2.clear()` itself (`this.DOMContainer.dispatchEvent(new
  Event("clear", {bubbles: true}))` in `etemplate2.ts`), not a jQuery-only concept, same as admin's
  `'show.et2_nextmatch'` finding elsewhere in this project.
- `jQuery(et2.DOMContainer).siblings('.et2_container').length` (`et2_ready()`) ->
  `Array.from(et2.DOMContainer.parentElement?.children ?? []).some(el => el !== et2.DOMContainer &&
  el.matches('.et2_container'))` - no native one-liner for "has a sibling matching selector", so a
  small manual scan over `parentElement.children`, same class of manual-walk fix as addressbook's
  `.nextAll()` conversion.
- `jQuery(et2.DOMContainer).hide()` / `jQuery(this.views[view].etemplate.DOMContainer).hide()` /
  `jQuery(this.views[what].etemplate.DOMContainer).show()` (3x, `et2_ready()`/`show()`) -> direct
  `.style.display = 'none'` / `= ''` on the already-native `DOMContainer` (`HTMLElement`, confirmed via
  `etemplate2.ts`'s own `get DOMContainer()` getter - never actually jQuery-wrapped).
- `jQuery.isEmptyObject(state.state)` (`setState()`) -> `Object.keys(state.state ?? {}).length === 0`,
  the established repo-wide swap.
- `jQuery.isArray(pm_id)` (`add_new()`) -> `Array.isArray(pm_id)`.
- `jQuery(target).closest('div').parent('div').find('table.egwLinkMoreOptions')` +
  `jQuery(element).css('display')`/`.fadeIn('medium')`/`.fadeOut('medium')` (`toggleDiv()`) -> a manual
  `.closest('div')` + parent-is-a-`<div>` check + `.querySelector(...)`, and a plain
  `getComputedStyle(element).display`/`style.display` toggle (drops the fade animation - same accepted
  tradeoff as admin's `fadeToggle()` case, documented in a comment). Also corrected the stale
  `@param {string} target jQuery selector` JSDoc tag while touching this method: `target` is actually
  never populated at all at runtime - `Et2Widget._handleClick()` (`api/js/etemplate/Et2Widget/
  Et2Widget.ts`) only ever invokes `onclick` handlers with `(event, widget)`, so `toggleDiv`'s 3rd
  parameter is always `undefined` in practice (a pre-existing, out-of-scope latent bug, not touched
  beyond documenting it and guarding it defensively with `target instanceof Element`).
- `jQuery('a:contains("...")', sidebox.parentsUntil('#egw_fw_sidemenu,#tdSidebox').last())` +
  `.off('click.projectmanager')`/`.on('click.projectmanager', click)` (`_bind_sidebox()`), plus the two
  related `.off('.projectmanager')` teardown call sites (`destroy()`, and the `'clear'` handler in
  `et2_ready()`) - the biggest rewrite in this file. `sidebox` itself is typed `JQuery` in the shared,
  out-of-scope `api/js/jsapi/egw_app.ts` (`sidebox : JQuery`), so this couldn't be a 1:1 mechanical
  swap. Replaced with: a new `_findSideboxLink(label)` helper that manually walks
  `sideboxNode.parentElement` up to the `#egw_fw_sidemenu`/`#tdSidebox` boundary (native equivalent of
  `.parentsUntil(selector).last()`) and then scans that container's `<a>` elements for one whose
  `textContent` includes the translated label (native equivalent of `:contains()`); a new
  `_sideboxClickHandlers : Map<HTMLElement, EventListener>` instance field that `_bind_sidebox()`
  populates via `addEventListener`/`removeEventListener` instead of jQuery's namespaced
  `off()`/`on()`; and a new `_clearSideboxHandlers()` helper (used by both `destroy()` and the
  `'clear'` handler) that unbinds and forgets everything the map is tracking. This is a deliberate
  behavior refinement, not just a mechanical swap: the original `.off('.projectmanager')` calls swept
  *any* element carrying that jQuery namespace, while the new tracked-map approach only ever removes
  handlers this file itself added - functionally equivalent for every actual call site in this file
  (nothing else binds a `'click.projectmanager'`-namespaced handler), but more precise. Same
  handler-tracking-field pattern as admin's `_adminIframeLoadHandler`.

### `egw.json(...).sendRequest()` -> `egw.request()`

All 3 sites were async (`sendRequest(true)`), no genuinely synchronous ones present - straightforward
swaps: `show()`'s gantt-project-data fetch (callback -> `.then()`), `ignore_action()`'s and
`change_status()`'s fire-and-forget calls (both had `_callback: null` already, so the swap simplified to
a bare `egw.request(menuaction, params)` with no `.then()` needed).

### function/closures -> arrow functions

- All 12 `function(...) {...}` expressions converted to arrow functions - `_bind_sidebox()` click
  callbacks (`et2_ready()` x2), the `'clear'` event callback, the gantt-project `.map()` callback, the
  `egw.request().then()` callback, 6x `iterateOver(function(...){...}, this, ...)` callbacks across
  `show()`/`setState()`/`getState()`/`getNextmatch()`, and `linkHandler()`'s retry `setTimeout`
  callback. None needed their own dynamic `this` - the `iterateOver()` calls were verified against
  `et2_core_widget.ts`'s implementation (`_callback.call(_context, this)`), confirming the explicit
  `this` passed as `_context` is always the same object an arrow's lexical `this` would already resolve
  to (same established refinement as tracker's `iterateOver()` conversions). No `var self`/`var that`
  closures were present to remove.

### Not touched (out of scope)

- `toggleDiv()`'s `target` parameter being permanently `undefined` at runtime (see above) - a
  pre-existing, unrelated latent bug, documented via the corrected JSDoc and a defensive
  `instanceof Element` guard, not "fixed" (no call site was changed to actually pass a target).
- `p_element_delete()`'s `content`/`id` being read unconditionally after an `if(template)` block that's
  the only place either is assigned (so both are `undefined` - and immediately throw on
  `content.data['caller']` - whenever `template` is falsy) is exactly the same latent fragility
  addressbook's `_confirmdialog_callback()` var-hoisting note flagged elsewhere in this project;
  preserved as-is (hoisted the `let content, id;` declarations above the `if`, matching `var`'s
  original hoisting behavior) rather than "fixing" the underlying fragility.

## news_admin/js/app.ts (done)

438 lines. `news_admin/` is its own nested git repo (root `.gitignore` excludes it) - commit/push
separately. Baseline: 36 `var`, 9 jQuery uses, 11 TS errors, 0 `sendRequest()` sites, 4
`function(){}`/1 `var that = this` closure. Structurally very close to `infolog/js/app.ts` (same
author/era) - most fixes reused that file's exact established patterns.

### Legacy widget imports

No legacy `et2_*` widget imports at all - only `EgwApp` (base class), `nm_open_popup` (a plain function,
not a widget class, imported from `et2_extension_nextmatch_actions`), and `Et2Dialog` (instantiated/
static methods called: `Et2Dialog.show_dialog()`, `.YES_BUTTON`, `.BUTTONS_YES_NO_CANCEL`,
`.WARNING_MESSAGE`) - all three already correct value imports, nothing to change. `news_admin.index.xet`
still uses the legacy `<nextmatch id="nm">` tag (confirmed via `grep`), but since this file never
imports/casts an `et2_nextmatch` type (only calls `getWidgetById('nm')`/`getRoot().getWidgetById('nm')`
with no type annotation), that template fact didn't end up mattering for this file.

### TS errors fixed (11 total)

- **`document.getElementById(ab_id)`/`(info_cc)` typed as generic `HTMLElement`** (7 errors,
  `add_email_from_ab()`): the code treats the first as a `<select>` (`.options`, `.value`) and the second
  as a text input (`.value` accumulation) - cast to `<HTMLSelectElement>`/`<HTMLInputElement>`
  respectively at the point of assignment, matching the "cast at the query, not scattered at each
  access" style used elsewhere in this project.
- **`ab.onchange()` called with 0 arguments** (`TS2554`): `HTMLElement.onchange`'s declared type takes an
  `Event` param. The call is deliberately 0-arg at runtime (JS doesn't enforce arg count) - rather than
  synthesize an `Event` that changes nothing about behavior, cast the call itself to `<any>`
  (`(<any>ab).onchange();`) to preserve the exact existing runtime call.
- **`this.et2._inst`** (`edit_actions()`, 1 site): the familiar `getInstanceManager()` fix, same as every
  other file in this project.

### jQuery removed

- `jQuery('#delete_sub').get(0) || jQuery('[id*="delete_sub"]').get(0)` (2x, `confirm_delete_2()`/
  `confirm_delete()`) -> `document.getElementById('delete_sub') || document.querySelector<HTMLElement>(
  '[id*="delete_sub"]')`.
- `jQuery(_senders[i].iface.node).hasClass(...)` / `jQuery(_senders[i].iface.getDOMNode()).hasClass(...)`
  -> `.node.classList.contains(...)` / `.getDOMNode().classList.contains(...)`.
- `jQuery("tr.hiddenRow").css("display", ...)` (2x, `add_email_from_ab()`) ->
  `document.querySelectorAll<HTMLElement>(...).forEach(row => row.style.display = ...)`, the exact
  pattern already established in `infolog/js/app.ts`.
- `jQuery('#news_admin-edit-print').bind('load'/'DOMSubtreeModified')/.unbind(...)` + `var that = this`
  (`news_admin_print_preview_onload()`) -> plain `addEventListener`/`removeEventListener` +
  arrow functions closing over `this` directly - copied verbatim from `infolog/js/app.ts`'s
  `infolog_print_preview_onload()`, which has the identical structure (same original author).

### `egw.json(...).sendRequest()` -> `egw.request()`

None present in this file - nothing to do for this goal.

### function/closures -> arrow functions

- `confirm_delete_2()`'s `var callbackDeleteDialog = function (button_id) {...}` -> arrow (empty
  YES_BUTTON branch, no `this` used).
- The 3 `function(){}` expressions inside `news_admin_print_preview_onload()`'s jQuery calls were
  converted together with the jQuery removal above (arrow functions replacing both the jQuery binding
  and the `var that = this` capture in one edit).

### Not touched (out of scope)

- No additional findings beyond what's covered above.

## policy/js/app.ts (done)

389 -> 403 lines. `policy/` is its own nested git repo - commit/push separately. Baseline: already 0
`var` (all declarations were `let` already); 5 jQuery hits, 4 TS errors, 5 `egw.json(...).sendRequest()`
sites, 13 `function(...){}` expressions incl. 4 separate `let self = this;` closures.

### Legacy widget imports

- `et2_nextmatch` stayed a **value** import, unconverted - same reasoning as tracker's/admin's
  `et2_nextmatch`: it's a real, distinct legacy implementation, and this file passes it as a runtime
  `instanceof`-style filter value to `iterateOver(_callback, _context, _type)` in
  `_override_print_dialogs()`, not just as a TS type annotation.
- `etemplate2` and `Et2Dialog` were already correct value imports (`etemplate2.getById(...)`, `new
  Et2Dialog(...)`/static `Et2Dialog.YES_BUTTON`/`BUTTONS_YES_NO`/`OK_BUTTON`). Nothing else to change -
  no `import type` conversions were needed in this file at all.

### TS errors fixed (4 total)

- **`app.policy.setup_cmds_template` doesn't exist on type `EgwApp`** (2 sites, `setup_cmds_template()`'s
  deferred-retry callback): `app`'s indexed type (`{[propName:string]: EgwApp}`) doesn't know about this
  app's own extra methods - cast to `<policyAPP>` (the file's own class) at both read sites, same
  "we actually know the real shape here" reasoning `status/js/app.ts`'s `<statusApp>` cast used for
  `app.status.openCall()`.
- **`app.admin._acl_dialog` doesn't exist on type `EgwApp`** (`acl()`): `admin` is a different, real
  main-repo app (not EPL), but still hits the same `app`-indexing blind spot since `AdminApp`'s extra
  methods aren't imported/visible here. `<any>` cast at the one call site (unlike the `app.policy` case
  above, there was no reason to believe the real shape locally, so the generic EPL-blind-spot-style cast
  was used instead of importing `AdminApp`'s type across nested-repo boundaries).
- **`this.appname` inside `private static _acl_callback(_data)`** (`TS2339`, "does not exist on type
  `typeof policyAPP`"): a genuine mismatch between the method's `static` declaration and how it's
  actually invoked - `acl()` passes `policyAPP._acl_callback` as `egw.json()`'s callback with an
  *instance* (`this`) as the context argument, and `JsonRequest` invokes callbacks via
  `this.callback.call(this.context, res)` (confirmed by reading `egw_json.ts`), so `this` inside
  `_acl_callback` is always a `policyAPP` instance at runtime, never the class itself. Fixed with an
  explicit `this : policyAPP` parameter type (TS's documented way to declare a function's real calling
  convention) instead of leaving the misleading default `static` `this` type - a comment explains the
  actual invocation path.

### jQuery removed

- `jQuery('#policy-edit_tabs').addClass('inactive_blur')` -> `document.getElementById(...)
  ?.classList.add(...)`.
- `jQuery('body').on('load', 'form', callback)` (`setup_cmds_template()`'s deferred-retry path) -> a
  manual delegated listener (`document.body.addEventListener('load', event => { if (event.target
  instanceof Element && event.target.closest('form')) callback(); })`). Documented in a comment that
  this - like the original jQuery version - practically never fires in real browsers, since `'load'`
  doesn't bubble on `<form>` elements; the `setTimeout(callback, 1000)` fallback right below it is what
  actually does the work. Preserved rather than "fixed" (e.g. by switching to a capture-phase listener,
  which would be a real behavior change from "never fires" to "sometimes fires") since that's out of
  scope for a mechanical jQuery-removal pass.
- `jQuery.extend({}, egw.dataGetUIDdata(...).data)` (`acl()`, shallow clone) -> object-spread `{...}`.
- `jQuery.extend(action.data, value)` (`confirm()`, shallow merge into existing target) ->
  `Object.assign(action.data, value)`.

### `egw.json(...).sendRequest()` -> `egw.request()`

- 4 of 5 sites were genuinely async - converted to `egw.request(...).then(...)`:
  `policyEditDialog()`'s save-dialog callback (`sendRequest(true)`), `action()`'s delete/enable/disable
  case (bare `sendRequest()`), and both of `dry_run()`'s calls (its own `ajax_dryRun` fetch and the
  nested execute-confirmation dialog's `ajax_action` call, both bare `sendRequest()`).
- `acl()`'s delete-case call (`egw.json(className+'::ajax_change_acl', [ids],
  policyAPP._acl_callback,this,**false**,this)`) - **kept as `egw.json(...).sendRequest()`**, a new
  confirmed instance of admin's already-documented "5th arg to `egw.json()` itself is `false`" exception
  category: the explicit `false` 5th argument (not a `sendRequest()` argument) makes the request
  synchronous regardless of the bare `.sendRequest()` call after it - commented at the call site.

### function/closures -> arrow functions

- `add()`'s `let self = this` + `function(_data){...self...}` callback -> arrow using `this` directly,
  `self` removed.
- `setup_cmds_template()`'s retry `function(){}` (the deferred callback) -> arrow, converted together
  with its jQuery removal above.
- `policyEditDialog()`'s `cb = _callback || function(){}`, `run_callback = function(){}` (later
  reassigned to `function(_data){...}.bind(this)`), and the dialog's own `callback: function(_button_id,
  _value){...self...}` -> all converted to arrows. The dialog callback needed a closer look: it's the
  actual `transformAttributes({callback: ...})` value that `Et2Dialog` invokes via `.call(dialogThis,
  ...)`, and the *original* code already used `self` (not `this`) inside it precisely because of that -
  but since this callback has no *further* nested non-arrow scope between it and `policyEditDialog()`'s
  own `this` (unlike admin's `smime_showGenerateKeyDialog()` case, which has an intervening
  method-syntax `callback(...)` frame), converting the whole thing to an arrow and replacing `self` with
  `this` is safe: an arrow's lexical `this` here resolves to exactly what `self` already captured.
  `run_callback`'s own `.bind(this)` became redundant and was dropped for the same reason.
- `action()`'s `let self = this` (used in 3 separate `function(_data){...self...}` callbacks across the
  `edit`/`delete-enable-disable`/`default` cases) -> all 3 converted to arrows using `this` directly,
  `self` removed.
- `refreshPolicies()`'s `let self = this` (its `.forEach()` callback was *already* an arrow function,
  just referencing `self` unnecessarily instead of `this`) -> `self` removed, callback now reads `this`
  directly.
- `confirm()`'s `let callback = function(_button, value){...}` (passed as `Et2Dialog`'s `callback:`, but
  never referenced `this` in its body - only closured `action`/`senders`/`target`) -> arrow, safe
  regardless of `Et2Dialog`'s own `this`-rebinding contract since the body never depended on it.
- `_override_print_dialogs()`'s `iterateOver(function(nm){...}, this, et2_nextmatch)` outer callback and
  the `nm._create_print_dialog = function(value, callback){...}` it assigns (plus its inner
  `Object.keys(...).map(function(key){...})`) -> all 3 converted to arrows. The outer one follows the
  by-now-established `iterateOver()` refinement (explicit `this` context argument is always the same
  object an arrow's lexical `this` would resolve to here); the inner two never used `this` at all.

### Not touched (out of scope)

- No additional findings beyond what's covered above.

## importexport/js/app.ts (done)

325 -> 336 lines, **main repo** (not a nested git repo, unlike most other files in this project).
Baseline: 10 `var`, 13 jQuery hits (including a top-of-file side-effect `import
"../../vendor/bower-asset/jquery/dist/jquery.min.js"`), 12 TS errors, 0 `egw.json(...).sendRequest()`
sites, 2 `jQuery.proxy(function(){...}, this)` closures.

### Legacy widget imports

- No legacy `et2_*` widget imports - only `EgwApp` (base class) and, previously, a broken `import {egw}
  from ".../egw_global"` (see below). Nothing to convert for goal 1.
- Removed the side-effect `import "../../vendor/bower-asset/jquery/dist/jquery.min.js"` entirely once
  all jQuery usage was gone (see jQuery section) - also dropped the adjacent dead, already-commented-out
  `//import ".../jquery-ui.js"` line, since it only existed to explain the jQuery import being removed.
- `import {egw} from ".../egw_global"` removed - the familiar ambient-global fix (`egw`/`app` are
  declared in `egw_global.d.ts`'s `declare global {}`, unconditionally included via tsconfig, so the
  import was both wrong for TS - `TS2305`, no such export - and unnecessary), matching every other file
  in this project.

### TS errors fixed (12 total)

- **`this.et2.getDOMWidgetById(...)` typed as `typeof Et2Widget` instead of an instance** (9 sites
  across `et2_ready()`, `export_preview()`, `import_preview()`, `_doProgressUpdate()`,
  `_closeProgress()`): the same `getDOMWidgetById()` framework return-type bug documented in
  `addressbook/js/CRM.ts`'s section above (a likely typo for `Et2Widget | null` in
  `Et2Template.ts`/`et2_core_baseWidget.ts`). The established workaround elsewhere casts through
  `<unknown>` to a *specific* widget subclass (`<Et2Nextmatch><unknown>...`, `<Et2Select><unknown>...`),
  but this file only ever calls generic DOM-ish members (`.getDOMNode()`, `.classList`, `.querySelector`,
  `.insertBefore`) with no single concrete widget subtype to name, and `Et2Widget` itself turned out to
  be **unusable as a cast target** - it's a mixin *function* (`export const Et2Widget =
  dedupeMixin(...)`) with no matching type/interface declaration anywhere in the codebase, so even
  `<Et2Widget><unknown>...` fails with `TS2749: 'Et2Widget' refers to a value, but is being used as a
  type here` (confirmed this is a **pre-existing, repo-wide instance of the same gap** -
  `et2_core_widget.ts`'s own `getWidgetById(): Et2Widget | et2_widget | null` declaration and
  `egw_app.ts` both already have this exact error in the baseline whole-repo `tsc` output; the one place
  it "works", `Et2Filterbox.ts`'s `(element : Et2Widget) => {...}`, is silently covered by a pre-existing
  `@ts-ignore` one line above it, not a real fix). Used a plain `<any>` cast instead at all 9 sites -
  consistent with this project's established fallback for framework properties with no real matching
  type anywhere (`app.stylite`/`_children`/etc.) - with a comment explaining the root cause once per
  method.
- **`progress_record`'s `.value`, `sl-progress-bar`'s `.indeterminate`/`.value`, `import_log`'s
  `.value`/`.shadowRoot`** (`_doProgressUpdate()`): none of these are declared anywhere in the widget
  type hierarchy (`sl-progress-bar` isn't an etemplate widget at all, per the code's own existing
  comment) - `<any>`-typed locals (`record`/`bar`/`log`), same reasoning as the `getDOMWidgetById()` cast
  above, documented together in one comment.

### jQuery removed

- `jQuery(...).attr('disabled','disabled')` (2x, `et2_ready()`) -> `.getDOMNode().setAttribute(
  'disabled', 'disabled')` (kept the literal attribute-string form rather than switching to the
  `.disabled = true` boolean property, to stay a 1:1 behavioral swap).
- `jQuery('input[value="filter"]').parent().hide()` -> `document.querySelectorAll<HTMLElement>(
  'input[value="filter"]').forEach(el => { if(el.parentElement) el.parentElement.style.display =
  'none'; })` - jQuery's `.parent()` operates over *all* matched elements, so the native replacement
  iterates too rather than assuming a single match.
- `jQuery('div.filters').hide()` -> `document.querySelectorAll<HTMLElement>('div.filters').forEach(el
  => el.style.display = 'none')`, the by-now-standard pattern from `infolog/js/app.ts` onward.
- `jQuery(...).parent().show()` / `.empty().append(htmlString)` (`export_preview()`) ->
  `.parentElement.style.display = ''` / `.replaceChildren()` + `.insertAdjacentHTML('beforeend',
  htmlString)` (native `.append(string)` would insert literal text, not parsed markup - same
  distinction `admin/js/app.ts`'s section already documented for the identical jQuery `.append()` swap).
- `jQuery(...).show()` / `.empty().text(...)` / `.removeClass()`/`.addClass()` (`import_preview()`) ->
  `.style.display = ''` / direct `.textContent =` assignment / `.classList.remove()`/`.add()`.
- `jQuery(this.et2.getWidgetById("preview_box")).hide()` (`closePreview()`) -> `.style.display =
  'none'` on the widget cast to `HTMLElement` (the widget's declared type is a union with the legacy
  `et2_widget`, which has no `.style`, but at runtime this is always the modern web-component instance).
- **`.show(100, callback)`'s 100ms animation was dropped, not reproduced** (`export_preview()`/
  `import_preview()`) - jQuery's animated show has no simple native one-liner equivalent (a real
  CSS-transition-based rewrite is out of scope for a mechanical jQuery-removal pass), so both methods now
  show immediately and invoke the callback body right away instead of after the animation completes.
  Same category of documented, intentional behavior-simplification as `admin/js/app.ts`'s already-noted
  `.fadeToggle()` drop ("not sure how to do this et2-ish" in the original code's own comment there).
  Commented at both call sites.

### `egw.json(...).sendRequest()` -> `egw.request()`

None present in this file - nothing to do for this goal.

### function/closures -> arrow functions

- Both `jQuery.proxy(function(){...}, this)` callbacks (`export_preview()`/`import_preview()`) were
  removed entirely along with the `.show(100, callback)` animation calls they were passed to (see
  jQuery section above) - their bodies (`widget.clicked = true/false` +
  `widget.getInstanceManager().submit(...)`) now just run inline in the method body instead of inside a
  callback, since there's no longer an animation to wait for.

### Not touched (out of scope)

- No additional findings beyond what's covered above.

## stylite/js/app.ts (done)

`stylite/js/app.ts`, 1054 -> 1099 lines. `stylite/` is its own nested git repo (root `.gitignore`
excludes it) - same commit/push-separately note as `tracker`/`status`/etc. Baseline: 2 `var`, 8 jQuery
hits, 20 TS errors, 2 `egw.json(...).sendRequest()` sites, 31 `function(...) {...}` expressions, 5
`let self`/`let that` closures.

### Legacy widget imports

- `et2_nextmatch`: `stylite.calls.xet`'s `nm` widget still uses the legacy `<nextmatch id="nm">` tag,
  not `<et2-nextmatch>` - so `this.et2?.getWidgetById('nm')` genuinely IS a legacy `et2_nextmatch`
  instance at runtime here, same situation as `tracker`/`admin`'s `et2_nextmatch`. Unlike those two,
  though, it's only ever used for type casts in this file (never passed as a runtime `instanceof`
  filter), so switched to `import type {et2_nextmatch}` - kept the legacy name (a cast to the
  web-component `Et2Nextmatch` would be wrong here), just made the import type-only.
- `et2_selectbox` (`class et2_selectbox extends Et2Select {}` compat shim): **kept as a value import,
  unconverted** - `et2_ready()`'s `'stylite.placetel.sipUser'` case passes it as a runtime
  `instanceof`-style filter value to `iterateOver(_callback, _context, et2_selectbox)`, exactly the
  tracker/admin precedent (a real behavior change to broaden the match if swapped to `Et2Select`).
- `et2_textbox` (real legacy implementation, `extends et2_inputWidget`, no shim/replacement) - only used
  for one type-union cast (`et2_selectbox|et2_textbox`) with no runtime filter usage anywhere - switched
  to `import type`, same reasoning as timesheet's `et2_grid`.
- `et2_DOMWidget` (real legacy abstract base class) - only used for type casts, never instantiated -
  switched to `import type`.
- `et2_button` (real legacy implementation, `extends et2_baseWidget`, no shim) -> **replaced with
  `import type {Et2Button}`**: unlike `et2_selectbox` above, it's only ever used as a type annotation
  here (`placetelIntegration()`'s `_widget` param), never a runtime filter value, and the one live
  caller (`stylite/templates/default/config.xet`) already passes an `<et2-button>` web component -
  `config.old.xet` (the only template still using the old-style button that would produce a real legacy
  `et2_button` instance) is unreferenced from any PHP/template/JS in the repo, confirmed dead.
- `et2_image` (`export type et2_image = Et2Image` deprecated alias) -> `import type {Et2Image}` directly
  (only used as a type, `showQRCode()`'s `_widget` param).
- `et2_tree`: the old import (`.../et2_widget_tree`) pointed at a module that **no longer exists at
  all** - the tree widget has fully migrated to the `Et2Tree` web component with no compat shim left
  behind, so the import was simply broken (`TS2307`), not just stale. Fixed to `import type {Et2Tree}
  from ".../Et2Tree/Et2Tree"` (only used as a type, `pm_calendar_integration_sidebox_tree_change()`'s
  `widget` param).
- `et2_taglist` (`export type et2_taglist = Et2Select` deprecated alias) -> `import type {Et2Select}`
  directly (only used as a type, `pm_calendar_integration_sidebox_taglist_change()`'s `taglist` param) -
  reuses the same `Et2Select` type import already needed for `et2_tree`'s neighbor conversion above.
- `Et2Textarea` was already imported from its real web-component path, but as a *value* import despite
  being used only for one type cast (`mailvelopeCompose()`) - switched to `import type`.
- `egw`/`egw_getFramework` from `egw_global` removed entirely - the familiar ambient-global fix (see
  `mail/js/app.ts`'s identical comment, reused verbatim here since this file also uses both names).
- `EgwApp`, `etemplate2`, `egw_getAppObjectManager`, `Et2Dialog`, `EplLinkSearch`, `StyliteGantt` were
  all already correct as value imports (extended/instantiated/called directly) - untouched.

### TS errors fixed (20 total)

- **`egw_fw_class_application` (`TS2304`, plus cascading `TS2341`/`TS2339` on the same line)**:
  `callHistory()`'s `refreshCallback` cast `(<egw_fw_class_application><unknown>this).appName` referenced
  a type name that doesn't exist anywhere in the repo. This is the exact same shape as addressbook's
  `openCRMview()` `refreshCallback` case (see that section above) - no in-repo caller of
  `callHistory()`'s `refreshCallback` was found either, but `tabLinkHandler()`/the tab-opening machinery
  spreads the options object onto the tab, so `this.appName` needs to stay whatever the eventual caller
  invokes it as. Since `refreshCallback` stays a plain `function` (goal 6 exception, see below), `this`
  is already implicit `any` inside it - the bogus cast was simply unnecessary. Removed it (`this.appName`
  directly) and cast just the `.app_obj.stylite` access to `<any>`, identical to addressbook's fix.
- **`copyClipboard()`'s `_widget.get_value` doesn't exist on `et2_DOMWidget`** (2 sites): same root cause
  as admin's `copyClipboard()` - the declared type was simply wrong. The one real caller
  (`stylite.placetel.sipUser.xet`) passes an `<et2-description>` web component, never a legacy
  `et2_DOMWidget`; the method's own `typeof _widget.get_value === 'function'` check is already the
  defensive runtime guard for that uncertainty. Retyped the param `any`, matching admin's exact fix.
- **`Et2Dialog.getComplete()`'s destructured `value.number`** (`callDialog()`): same `Promise<[number,
  Object]>` gap tracker hit - annotated the destructured callback params `[button, value] : [number,
  any]` instead of touching the shared dialog class.
- **`this.et2._inst`** (5 sites: `toggleEncrypt()`'s dialog callback, 4x in `mailvelopeCompose()`) - same
  `getInstanceManager()` fix as every other file in this project. One of the `mailvelopeCompose()` sites
  (`self.et2._inst.submit = function() {...}`) is itself installing a NEW function as the etemplate2
  instance's `.submit()` method - fixing `_inst` there didn't change the fact that inner function has to
  stay a plain `function` (see goal 6 below).
- **`editor.value` protected on `Et2InputWidgetInterface`** (`mailvelopeCompose()`): considered
  `editor.getValue()` (the public accessor, used elsewhere in this project for the same protected-`value`
  pattern), but rejected it here - `getValue()` returns `null` while the widget is `readonly`/`disabled`,
  which `info_des` may genuinely be at this point, so swapping would risk silently breaking the
  PGP-quote-parsing logic that follows. Cast through `<any>` instead to keep reading the raw field with
  identical runtime behavior, with a comment explaining why `getValue()` wasn't used.
- **`integration_preference.split(",")` - `Property 'split' does not exist on type 'never'`**
  (`_pm_calendar_integration_sidebox_change_project()`): the initial cast `<string[]>this.egw
  .preference(...)` was simply too narrow for what the following code already handles - a
  `typeof integration_preference == "string"` check on a value statically typed `string[]` narrows to
  `never` inside that branch (a `string[]` can never satisfy `typeof x === "string"`). Widened the cast
  to `<string[]|string>`, matching what the code's own runtime branching already assumes.
- **`action.checked = true`** (`Property 'checked' does not exist on type 'EgwAction'`) and
  **`app.calendar.update_state(state)`** (`Property 'update_state' does not exist on type 'EgwApp'`):
  same two established blind-spot patterns as elsewhere in this project - `checked` is set dynamically
  via `updateAction()` at runtime, not on `EgwAction`'s static type (same as addressbook's
  `_action.parent.getActionById(...).checked` fix); `app.calendar` types as a generic `EgwApp` (the
  indexed-type gap), even though `calendar` is a first-party app here, not an EPL one - same `<any>`-cast
  pattern as `app.stylite`/`app.status`/`app.rocketchat`/`app.policy` elsewhere. Both cast to `<any>` at
  their one call site each.
- **`action.execute()` - "Expected 1-2 arguments, but got 0"**: `EgwAction.execute(_senders, _target?)`
  (`api/js/egw_action/EgwAction.ts`) has no default for `_senders`, but was being called with 0 args
  (relying on it being `undefined` at runtime either way). Passed an explicit `action.execute(undefined)`
  to preserve the exact pre-existing runtime behavior while satisfying the argument-count check, rather
  than touching the shared `EgwAction` class.
- **A new cascading error surfaced only after converting `addPhoneUser()`'s `.then(function(_data)
  {...}.bind(this))` to a plain arrow `.then((_data) => {...})`**: with the `.bind(this)` gone,
  `Promise.all([...]).then(...)`'s callback parameter now type-checks precisely against the tuple type
  `[any, any]` (from the two `Promise.all()` members) instead of losing precision through `.bind()`'s
  looser return type - `_data.title`/`.content`/`.sel_options`/`.template` (read after `_data = _data[0]`
  narrows it to a single element) then no longer matched. Added an explicit `(_data : any)` parameter
  annotation rather than fighting the tuple inference, since the code's own `Array.isArray(_data)` check
  already treats `_data` as possibly either shape.

### jQuery removed

- `copyClipboard()`'s `jQuery(_widget.getDOMNode()).val(value).select()` / the `jQuery(...)
  .appendTo(...).val(value).select()` + `input.remove()` pair -> plain `HTMLInputElement` `.value`
  assignment, `.select()`, and native `Element.appendChild()`/`.remove()` - same conditional structure
  preserved exactly (including the pre-existing, slightly redundant inner ternary), just jQuery-free.
- `mailvelopeCompose()`'s `jQuery((<et2_DOMWidget>this.et2.getWidgetById('encrypt')).getDOMNode())
  .addClass('toolbar_toggle')` -> `.getDOMNode()?.classList.add('toolbar_toggle')`.
- `mailvelopeGetCheckRecipients()`'s `jQuery.map(_emails, function(_email, _account_id) { return
  _email; })` -> `Object.values(_emails)`, the established repo-wide swap for this exact pattern.
- `updateRules()`'s `jQuery.extend({test: this.firewallTestData()}, _rules)` (shallow, no `true` first
  arg) -> `Object.assign({test: this.firewallTestData()}, _rules)`, per `doc/ai/modernization.md`'s
  shallow-merge rule.
- 3 stale `@param {jQuery.Event}`/`{egwAction|jQuery.Event}` JSDoc tags (`toggleEncrypt()`,
  `onchangeResponsible()`, `firewallAction()`) corrected to `{Event}`/`{egwAction|Event}`.

### `egw.json(...).sendRequest()` -> `egw.request()`

Both sites (`voicemail()`'s 'delete' and 'call' cases, `EGroupware\Stylite\Calls::ajax_action`) were
bare `sendRequest()` (async by default, fire-and-forget, no callback) - straightforward 1:1 swaps to
`egw.request(...)`. No genuinely synchronous site in this file.

### function/closures -> arrow functions

- 27 of the 31 `function(...) {...}` expressions converted to arrow functions across `et2_ready()`,
  `voicemail()`, `addPhoneUser()`, `keyTypeChange()`, `showQRCode()`, `placetelIntegration()`,
  `mailvelopeOnSubmit()`, `mailvelopeGetCheckRecipients()`, `onchangeResponsible()`,
  `decrypt_hover()` (the `.mailvelopeOpenKeyring().then()` chain and `close:`), `firewallAction()`, and
  `firewallTest()`. Most already used an explicit `.bind(this)` to force `this` to the enclosing method's
  `this` (overriding `Et2Dialog`'s own dialog-`this` callback contract, confirmed via the same
  `Et2Dialog.ts` invocation sites this project has verified repeatedly) - converting to an arrow
  preserves that exact forced binding since an arrow's lexical `this` resolves to the same value the
  `.bind(this)` was forcing it to. The rest (`iterateOver()` callbacks, plain `.then()`/`.catch()`
  callbacks with no `.bind()` at all) were checked to confirm they never relied on their own dynamic
  `this` before converting.
- 4 genuine exceptions kept as plain `function`, each with a comment explaining why:
  - `callHistory()`'s `refreshCallback: function() {...}` - same addressbook `openCRMview()` precedent
    (no confirmed in-repo caller, but the tab-opening machinery may invoke it as `tab.refreshCallback()`,
    needing `this.appName` from the tab object).
  - `mailvelopeCompose()`'s `self.et2.getInstanceManager().submit = function() {...}` - installed as the
    etemplate2 instance's own `.submit()` method, so it needs its own call-time `arguments` (forwarded
    via `.apply()`) - an arrow would capture `mailvelopeCompose()`'s `arguments` instead. Its own `this`
    will be rebound to the etemplate2 instance when later invoked as `instance.submit(...)`, which is
    exactly why it reads the closured `self` (the app instance) rather than `this`. This is the same
    nested-scope wrinkle admin's `smime_showGenerateKeyDialog()` documented: an outer frame can become
    arrow-transparent while an inner one genuinely can't, and the inner one still needs `self`-style
    indirection because of it.
  - `decrypt_hover()`'s `open: function(event, tooltip) {... this.clientHeight ...}` - **verified**, not
    just inferred: `TooltipOptions.open` (`api/js/jsapi/egw_tooltip.ts`) is explicitly typed `(this:
    Node, ...) => any`, and the function genuinely reads that `this` (`this.clientHeight`). Its sibling
    `close:` callback, which never reads `this` at all, WAS converted to an arrow - only `open` needed to
    stay plain.
  - `var self`/`var that` closures: 5 found (`mailvelopeCompose()`, `mailvelopeOnSubmit()`,
    `mailvelopeGetCheckRecipients()`, `onchangeResponsible()`, `decrypt_hover()`). 3 removed entirely
    (their functions all converted to arrows using `this` directly - `mailvelopeOnSubmit()`,
    `mailvelopeGetCheckRecipients()`, `onchangeResponsible()`). 2 kept, both for the same reason: a
    nested plain-`function` inside them (documented above) can't use `this` for the app instance, so the
    enclosing `self` capture is still needed even though the outer functions around it became arrows
    (`mailvelopeCompose()`'s `self`, `decrypt_hover()`'s `self`).

### Not touched (out of scope)

- No further out-of-scope findings beyond what's already documented above (the `et2_DOMWidget`/
  `et2_button` type gaps and `egw_fw_class_application` typo were all fixed as part of the required TS
  cleanup, not skipped).

## invoices/js/app.ts (done)

828 -> 837 lines. `invoices/` is its own nested git repo. Already fully modern on 5 of the 6 goals
before this pass: 0 `var`, 0 jQuery, 0 `egw.json(...).sendRequest()`, 0 `function(...) {...}`
expressions, 0 `var self`/`var that` closures. Only legacy-widget-import cleanup (goal 1) and 13 TS
errors (goal 3) needed fixing.

### Legacy widget imports

- `import {app, egw} from "../../api/js/jsapi/egw_global"` removed - the familiar ambient-global fix
  used throughout this project (`egw`/`app` are declared `var` inside `declare global {}` in
  `egw_global.d.ts`, unconditionally included via tsconfig's `**/*.d.ts`), even though this file uses
  both as real values (`egw.open_link(...)`, `egw.link(...)`, `app.classes.invoices = ...`) - ambient
  globals don't need or allow an import either way.
- `import {EgwApp, PushData} from '../../api/js/jsapi/egw_app'` split: `EgwApp` stayed a value import
  (`extends EgwApp`), `PushData` (only used as `push(pushData : PushData)`'s param type) switched to
  `import type`.
- `et2_grid` (only used for 2 type casts, `<et2_grid>this.et2.getWidgetById(...)`) was already
  `import type` - correctly left alone, same "no web-component replacement exists" reasoning as
  timesheet's/status's `et2_grid`. Every other widget import (`Et2LinkEntry`, `Et2Number`, `Et2Select`,
  `Et2InputWidgetInterface`, `Et2Textbox`, `Et2Button`, `Et2Date`) was already correctly `import type`
  (type-only usage confirmed for each); `etemplate2` correctly stayed a value import
  (`etemplate2.getByTemplate(...)`).
- Added a new `import type {EgwFrameworkApp} from "../../kdots/js/EgwFrameworkApp"` for a TS-error fix
  (see below) - not a legacy-widget migration, but the same "type-only, so `import type`" reasoning.

### TS errors fixed (13 total)

- **`template?.iterateOver(...)`'s missing 2nd arg + `template?.content`'s missing property** (2
  errors, `setTradepartyByContact()`): `template` walks up the widget tree via repeated
  `.getParent()`/`.getRoot()` calls and gets reassigned to `null`, ending up typed as a
  `Et2WidgetClass | et2_widget` union that has no `.content` member and makes `iterateOver()`'s
  `_context` argument (required, not optional - `iterateOver(_callback, _context, _type?)` in
  `et2_core_widget.ts`) newly visible as missing. One root-cause fix for both: retyped the `let
  template = ...` declaration itself as `let template : any = ...` (with a comment explaining why),
  rather than casting at each of the several places `template` is used - matches the "type loosely at
  the declaration, once" style used for similar widget-tree-walking locals elsewhere in this project.
- **`_positions.invoice_charge_total`/`invoice_allowance_total`/`invoice_line_total` (on `_positions`)
  and the same 3 properties on `_invoice`** (6 errors, `updatePositions()`): the method's params were
  declared `_positions : Array<object>` and `_invoice : object`, but the body attaches/reads extra named
  properties directly on both (a legacy array-as-dict pattern from the server push payload, plus
  `_positions.unshift(...)`/`_positions[0]` array usage) - `object`/`Array<object>` don't have an index
  signature, so property-by-name access always fails even though bracket access with a *dynamic* string
  key silently doesn't error under this repo's `noImplicitAny: false`. Retyped both params `any` -
  they're genuinely loosely-shaped push data, not a case where a real interface was just missing.
- **`_widget.valueAsNumber` doesn't exist on `Et2Button`** (`positionAllowance()`): the param is typed
  `Et2Button|Et2Number`, but this specific branch (`_widget.id` matching the `[percent]` field) only
  ever reaches this code with the `Et2Number` percent widget, never the button - cast to
  `<Et2Number>_widget` at that one access, same single-cast-at-usage pattern used throughout this
  project for a param typed as a union of "whichever widget triggered this handler".
- **`this.nm.activeFilters.startdate = null`** (`dateFilterChange()`): `invoices/templates/default/
  index.xet` still uses the legacy `<nextmatch id="nm">` tag (not `<et2-nextmatch>`), so `this.nm` is
  genuinely `et2_nextmatch` at runtime here - **exactly** tracker's own precedent for this identical
  line/property (`et2_nextmatch`'s `ActiveFilters` interface has no `startdate` member at all, unlike
  the web-component `Et2Nextmatch`'s `Record<string, any>`, so even the "cast to the legacy type"
  fix used elsewhere in this app wouldn't help) - used `<any>` instead, with the same comment style
  tracker used.
- **`filter.closest('egw-app').filtersDrawer`** (`dateFilterChange()`): `.closest()` returns generic
  `Element`; `filtersDrawer` is a real getter on `EgwFrameworkApp` (`kdots/js/EgwFrameworkApp.ts`) -
  added the type-only import and cast, identical to tracker's fix for the same line shape.

### Not touched (out of scope)

- Nothing else needed touching - the file was already clean on goals 2, 4, 5, and 6 before this pass.

## rocketchat/js/app.ts (done)

626 -> 669 lines. `rocketchat/` is its own nested git repo. Baseline: 3 `var`, ~14 jQuery hits
(incl. `jQuery.ajax`/`jQuery.extend`), 4 TS errors, 4 `egw.json(...).sendRequest()` sites, 8
`function(...) {...}`/`var self`/`let self` closures - the heaviest closure-capture concentration
since status/app.ts.

### Legacy widget imports

No legacy `et2_*` widgets at all in this file - `Et2Dialog` and `rocketchat_realtime_api` were already
correct value imports (both instantiated/called: `new Et2Dialog(...)`, `Et2Dialog.alert()`/
`.show_dialog()`, `new rocketchat_realtime_api(...)`), `statusApp` was already a correct `import type`
(only ever used for `<statusApp>app.status` casts). Only change: `import {egw, app} from
".../egw_global"` removed - the standard ambient-global fix (see infolog's/invoices's write-ups) - even
though this file calls `egw(window)` (the callable-interface form) and assigns `app.classes.rocketchat`
as real values; ambient globals cover both without an import either way.

### TS errors fixed (4 total)

- **`egw`/`app` "no exported member"** (2 errors): the ambient-global import fix above.
- **`_resolve()` "Expected 1 arguments, but got 0"** (2 errors, `_isRocketchatLoaded()` and
  `getUpdates()`'s inner `checkApi()`): both are `new Promise((_resolve, _reject) => {...})` calls with
  no explicit type argument, so TS infers the executor's `resolve` from usage - and without an explicit
  `void`-inclusive type argument, `resolve()` requires an argument even where the code's own logic
  legitimately resolves with nothing. Fixed by giving each `Promise` an explicit type argument matching
  what it's actually resolved with: `Promise<string|void>` for `_isRocketchatLoaded()` (resolves either
  `"setup"`/`'setup'` or nothing), `Promise<void>` for `checkApi()` (never resolves with a value).

### A `function(...)` conversion that fixed a real latent bug, not just a type/style issue

`_isRocketchatLoaded()`'s `return new Promise (function(_resolve, _reject){...})` used a plain
`function` expression as the Promise executor - which is called directly by the `Promise` constructor
(`executor(resolve, reject)`), never as a method, so its own `this` was always `undefined` in this
ES-module's (implicitly strict-mode) scope. Every `this.chatbox`/`this.mainframe`/`this.install_info()`
access inside therefore always threw, landing in the `catch(e){ _resolve('setup'); }` block - meaning
this method has always unconditionally resolved `"setup"` regardless of whether the Rocket.Chat iframe
actually shows a setup wizard. Converting to an arrow function (`(_resolve, _reject) => {...}`) makes
`this` correctly resolve to the `RocketchatApp` instance, restoring the evidently-intended DOM check -
same class of finding as tracker's `edit_popup()`/`this.et2.node` fix (a real bug surfaced and fixed
while doing the mechanical goal-6 conversion, not merely a cosmetic rewrite).

### jQuery removed

- `jQuery('#rocketchat-index')` passed as `loading_prompt()`'s `_node` argument (2x) -> the plain
  selector string `'#rocketchat-index'` passed directly - `egw_message.ts`'s `loading_prompt(_id, _stat,
  _msg, _node, _mode)` already accepts `string|JQuery|HTMLElement` for `_node` and does
  `document.querySelector(_node)` itself when given a string, so wrapping it in `jQuery(...)` first was
  pure overhead.
- `jQuery(this.mainframe).on('load', function(){...})` / `jQuery(this.chatbox).on('load',
  function(){...})` (`et2_ready()`) -> `this.mainframe.addEventListener('load', () => {...})` /
  `this.chatbox.addEventListener('load', () => {...})`, done together with converting every nested
  `function(){...self...}` callback inside to an arrow using `this` directly (see below) - the whole
  `var self = this` capture in `et2_ready()` was removed as a result.
- `jQuery('.setup-wizard', frame.contentWindow.document).length > 0` / `jQuery('[class*="SetupWizard"]',
  ...).length > 0` / `jQuery('body', ...).length > 0` (`_isRocketchatLoaded()`) ->
  `frame.contentWindow.document.querySelectorAll(...).length > 0`, mechanical 1:1 swaps.
- `jQuery('.setup-wizard', frame.contentWindow.document)` used bare as an `if(...)` condition
  (`messageHandler()`, **not** `.length`-checked like the 3 uses above) -> **a behavior fix, not just a
  mechanical swap**: a jQuery object is *always* truthy regardless of match count, so this condition was
  always true whenever `frame`/`frame.contentWindow` existed, unconditionally re-attaching the
  setup-wizard click handler on every single `message` event regardless of whether the setup wizard was
  actually showing. Replaced with `frame.contentWindow.document.querySelector('.setup-wizard')` (truthy
  only on an actual match), documented with a comment. Also replaced the `.off().on('click', ...)`
  jQuery idiom it guarded with a persisted `private _setupWizardClickHandler` arrow-function class
  field plus `removeEventListener`/`addEventListener` (the native equivalent of jQuery's
  namespaced-rebind pattern), matching admin/app.ts's established `_adminIframeLoadHandler` precedent -
  needed because `messageHandler()` can fire repeatedly, and plain `addEventListener` without a matching
  `removeEventListener` would otherwise pile up duplicate handlers. The handler body's own
  `this.postMessage(...)` was also a **second latent bug** fixed the same way as the Promise-executor
  one above: jQuery's `.on('click', function(e){...})` binds `this` to the clicked DOM element, not the
  app, so `this.postMessage` would have thrown if ever reached - now correctly `this` = the
  `RocketchatApp` instance via the stored arrow-function field.
- `jQuery.extend({externalCommand: _cmd}, _params)` (`postMessage()`) -> object-spread
  `{externalCommand: _cmd, ..._params}`, per the shallow-merge rule (`jQuery.extend(target, extra)` with
  only 2 args is a shallow merge, not `deepExtend()`'s territory).
- `jQuery('span.fw_avatar_stat', '#topmenu_info_user_avatar').attr({class, title})` /
  `jQuery('tr#'+id+' span.stat1', '#egw_fw_sidebar_r').attr({class, title})`
  (`_subscriptionsInterval()`) -> `document.querySelectorAll<HTMLElement>('#topmenu_info_user_avatar
  span.fw_avatar_stat')...forEach(el => {el.className = ...; el.title = ...;})` (context+selector
  combined into one native selector string, matching jQuery's `jQuery(selector, context)` semantics),
  same pattern for the sidebar row selector.
- `jQuery('#rc_status_select').val(...).trigger('liszt:updated')` -> `document.getElementById(...)` (cast
  to `HTMLSelectElement`) + `.value = ...` + `.dispatchEvent(new Event('liszt:updated'))` - a plain
  native event, not jQuery's dot-namespace convention (`liszt:updated` is the real event name the
  chosen/liszt select-replacement library listens for).
- `jQuery.ajax(url + 'api/info').done(...).fail(...)` (`getUpdates()`'s `checkApi()`) -> `fetch(url +
  'api/info').then(...)`/`.catch(...)`, with an explicit `if (!_response.ok) throw _response;` before
  parsing JSON - `fetch()` only rejects on network failure, not on a non-2xx HTTP status, unlike
  jQuery's `.fail()`, so the manual check preserves the original "any non-2xx counts as failure"
  behavior.
- `jQuery(framework.activeApp.tab.closeButton).trigger('click')` (`close_app()`) ->
  `framework.activeApp.tab.closeButton.click()` (native `Element.click()` is the direct equivalent for
  simulating a click, no `dispatchEvent()` ceremony needed).
- `jQuery.ajax({url, success(...), error(...)})` (`install()`) -> `fetch('/rocketchat/').then(async
  (_response) => {...}).catch(() => {...})`, checking `_response.status` inside `.then()` (matching the
  original success/error split by status code) and reading the error body via `await _response.text()`
  only on the non-2xx path.

### `egw.json(...).sendRequest()` -> `egw.request()`

- 3 of the 4 sites were genuinely async with no complications - converted to
  `egw.request(...).then(...)`: `handle_actions()`'s `'linkto'`/`'unlinkto'` link/unlink calls (both
  originally passed `self`/`true`/`self` as context/async/sender - all now unnecessary, `egw.request()`
  always uses the calling `this` and is always async), and `getUpdates()`'s `ajax_getServerUrl` fetch.
- `restapi_call()`'s `sendRequest(true,'POST', (_err) => {...})` **kept as-is**, a new documented
  exception: `egw.request()` has no error-callback parameter at all (only `(menuaction, params) =>
  Promise`), and this call needs one both to show a custom `egw.message()` instead of the framework's
  default error dialog, and to `_reject()` the wrapping `Promise` - `egw.request()`'s own promise never
  rejects on a server-side error (it only ever resolves, per `Json.request()`'s implementation), so
  there's no way to reproduce the `_reject()` half via `.catch()` either.

### function/closures -> arrow functions

- `et2_ready()`'s `var self = this` plus every `function(){...self...}` inside both switch cases
  (the `'load'` handlers and their nested `._isRocketchatLoaded().then(...)` success/error callbacks,
  including one more level of nested `window.setTimeout(function(){...})`) -> all converted to arrows
  using `this` directly; `self` removed entirely. None of these relied on their own dynamic `this` -
  they were only ever invoked as plain callbacks (jQuery `.on()`/`.then()`/`setTimeout`), never as a
  method or via `.call()`/`.apply()`.
- `_isRocketchatLoaded()`'s executor - see the dedicated bug-fix note above.
- `handle_actions()`'s outer `const self = this` removed - after converting both nested `egw.json(...)`
  callbacks to arrows (done together with their `egw.request()` swap above), `self` had no remaining
  uses. The `dialog.transformAttributes({callback(button, value){...}})` **concise method itself was
  kept as-is** (documented as a goal-6 exception: `Et2Dialog`'s callback contract binds `this` to the
  dialog, the same precedent already established for admin/status/rocketchat's own `linkto`/`unlinkto`
  dialogs).
- `_subscriptionsInterval()`'s `const self = this` - **a pure "belt-and-braces" alias, not a real
  `this`-binding fix**: every use of `self` in this method was already inside an arrow function
  (`window.setInterval(() => ...)`, `.then((_data) => ...)`, `.subscribeToNotifyLogged('user-status',
  (_data) => ...)`), so `self` and `this` were always identical values - removed the declaration and
  replaced all `self.` reads with `this.` directly, same "redundant self" finding as addressbook's
  `rename_list()`.
- `notifyMe()`'s `let self = this` plus its `onclick() {...self...}` concise-method Notification
  callback -> `onclick: () => {...}` using `this` directly, `self` removed. `egw_notification.ts`'s
  `notification()` assigns `options.onclick` straight onto a real `Notification` instance's own
  `.onclick` property, which *would* normally bind `this` to that instance when it fires - but the body
  here never used `this` (only `self`), so this is the exact same documented exception status/app.ts
  already established for the identical `Notification.onclick` pattern: safe to convert since the
  dynamic-`this` contract was never actually relied upon.
- `install()`'s `var w = window` (kept, just `var`->`const` - still needed inside the converted
  callback), `var self = this` (removed), and its `install_info(function(){...self...})` callback ->
  arrow using `this` directly. This one's conversion is *necessary*, not just stylistic:
  `install_info()` invokes its `_callback` via a bare `callback.call()` with **no thisArg**, so a plain
  `function`'s own `this` would always be `undefined` regardless of caller - only an arrow (whose `this`
  is fixed to `install()`'s own lexical scope, unaffected by `.call()`'s thisArg) reliably works here.

### Not touched (out of scope)

- Nothing else found - the file's remaining logic (`_shouldCallCustomOAuth()`, `chatPopupLookup()`,
  `_userStatusNum2String()`, `onLogout()`, `isRCActive()`) was already free of all 6 goals' issues.

## smallpart/js/app.ts (done)

Largest file in this project by far: 4028 lines baseline (4056 after), 1 `var` (a comment mentioning
"var", no actual `var` declarations), 24 jQuery uses, 167 TS errors, 14 `egw.json(...).sendRequest()`
sites, ~28 `function(...)` occurrences.

### Legacy widget imports

- Zero-member shims renamed to their web-component counterparts, same pattern as infolog/addressbook:
  `et2_selectbox` -> `Et2Select` (reused the file's existing `Et2Select` value import rather than adding
  a new one), `et2_template` -> `Et2Template`.
- `et2_checkbox` (`class et2_checkbox extends Et2Checkbox {}`, another zero-member shim) -> `Et2Checkbox`,
  kept as a **value** import (not `import type`) because it's passed as a runtime value to
  `iterateOver(_callback, _context, _type)`'s `_type` filter parameter in 3 places (`et2_ready()`'s
  question-init, `childrenChecked()`, `checkMaxAnswers()`) - see the genuine-bug writeup below for why
  this rename was load-bearing, not just cosmetic.
- `et2_selectbox_ro` and `et2_taglist` are plain type aliases in their own source files
  (`export type et2_selectbox_ro = Et2SelectReadonly`, etc - the same "doubly wrong value import"
  pattern tracker found for `et2_selectAccount`) - switched to `import type`, kept their original names
  (minimal-footprint, matching tracker's precedent).
- **`et2_tabbox` correction (2026-09-02 follow-up)**: originally lumped in with the two aliases above and
  kept under its own alias name. The user asked to verify in the *browser* (not the .xet source) whether
  smallpart's tabs are really the modern `Et2Tabs` before removing the import outright. Checked
  `customElements.get('et2-tabbox').name` and the live widget's `.constructor.name` on the running
  page - both `Et2Tabs`. Unlike `et2_button`/`et2_box` below, `et2_widget_tabs.ts` has **no**
  `et2_register_widget()` call at all (confirmed by reading the whole file) - it is *purely* the
  `@deprecated` type alias, with zero surviving legacy implementation; `Et2Tabs` itself
  `customElements.define("et2-tabbox", Et2Tabs)`, i.e. it took over the legacy tag name directly rather
  than registering a new `et2-tabs` tag. Import switched to `import type {Et2Tabs} from
  ".../Layout/Et2Tabs/Et2Tabs"` and the one cast site updated - see the corrected bug-fix entry below,
  since this also reversed an earlier misdiagnosis.
- `et2_grid`, `et2_widget`, `et2_inputWidget`, `et2_DOMWidget`, `et2_video`, `et2_countdown`,
  `et2_arrayMgr` are genuinely distinct legacy implementations (own base classes, no web-component
  replacement) and only used as types here -> `import type`, no rename, same reasoning as timesheet's/
  status's `et2_grid`. `et2_countdown` re-confirmed live on 2026-09-02: `getDOMWidgetById('timer')`'s
  `.constructor.name` on a running test-taking page is literally `"et2_countdown"` (not even an
  `HTMLElement` - no `.tagName`), i.e. still a plain legacy JS object, not a custom element.
- **`et2_button`/`et2_box`/`et2_details`/`et2_description`/`et2_textbox` correction (2026-09-02
  follow-up, resolved)**: the original entry claimed these had "no web-component replacement", matching
  `et2_grid`. Wrong for all five - discovered while re-checking `et2_tabbox` prompted a full re-audit of
  every remaining `et2_*` import. `Et2Box`/`Et2Description` **do** exist and, checked live, are what
  every actual `et2-box`/`et2-description` element on the page resolves to (`document.querySelectorAll` +
  `.constructor.name` over the whole live student-comments page: every single instance was the modern
  class, zero legacy ones). Switched both, plus `et2_description`'s two cast sites (`questionTime()`'s
  `task`/`volume`) and `et2_box`'s one cast site (`smallpart.student.comments_list`'s parent, live-confirmed
  `Et2VBox` - which extends `Et2Box`).
  `et2_details`'s one cast site (`questionTime()`'s `task.getParent()`) was initially left alone since the
  live parent was `Et2VBox`, not `Et2Details` - but tracing *why* resolved it for real: `video[video_question]`
  (the only definition of that widget id, in `student.index.xet`) is wrapped in a plain `<et2-vbox>`, with
  no `<details>` anywhere nearby - the `et2_details` cast was simply wrong from the start, not a
  modernization-era regression. Retyped to `Et2VBox` (already imported for another cast) and dropped the
  now-unused `et2_details` import entirely.
  `et2_button`/`et2_textbox` were initially left as "plausibly still legacy" on the theory that widgets
  inside a still-legacy `et2_grid` row stay legacy - **that theory itself was wrong**, found by tracing the
  actual widget-factory resolution code rather than guessing further: `et2_core_widget.ts`'s
  `createElementFromNode()` (called directly by `et2_widget_grid.ts`'s own row-rendering code, e.g. line
  467) resolves a cell's class purely from `_node.nodeName.toLowerCase()` - the *literal* tag string
  written in the `.xet` - checking the legacy `et2_registry` (keyed by short unprefixed names like
  `"textbox"`/`"button"`/`"int"`) first, and falling through to `customElements.get(_nodeName)` (the
  *exact* tag as written) whenever that lookup misses. Every grid cell in `question.xet` (and every
  standalone widget in every other `.xet` this file touches) is written with the modern `et2-` prefix
  (`<et2-checkbox>`, `<et2-textbox>`, `<et2-number>`, `<et2-button>`) - and an "et2-"-prefixed tag can
  never match a legacy registry keyed by unprefixed names, so it *always* falls through to the modern
  customElements branch, **regardless of grid nesting**. This generalizes (and explains) the original
  `et2_checkbox`/`Et2Checkbox` bug-fix below: it was never really about "checkbox has no legacy
  registration" specifically, it's that grid-nesting was never the deciding factor for *any* widget here.
  `et2_grid` itself stays genuinely legacy for the opposite reason: `question.xet` writes the *unprefixed*
  `<grid id="answers">`, which does match the legacy registry directly.
  Verified every remaining `et2_button`/`et2_textbox` cast site against its defining `.xet` tag this way:
  `button[import]`/`button[save]`/`button[apply]` are `<et2-button>` (`course.xet`/`question.xet`) ->
  `Et2Button`; `revertMarks`/`deleteMarks` are `<et2-button-icon>` (`student.index.xet`) -> `Et2ButtonIcon`
  (a `ButtonMixin(SlIconButton)` sibling of `Et2Button`, not a subclass of it - `Et2Button` itself would
  have been the wrong modern class here too); `course_groups`/`${row}[participant_group]`/`${row}[score]`
  are all `<et2-number>` (extends `Et2Textbox`) and `marks`/`answer_data[marks]` are `<et2-textbox>` - all
  -> `Et2Textbox`. All casts and the two method-param types (`changeCourseGroups`/`confirmOverwrite`/
  `checkMinAnswers`'s `_widget`) updated; `et2_button`/`et2_textbox` imports removed entirely.
  **This also uncovered a real, previously-invisible runtime bug**: `defaultPoints()`'s
  `(<et2_textbox>w).set_blur(default_points)` called a method that only ever existed on the *legacy*
  `et2_textbox` (a jQuery-based `placeholder`-attribute setter with a pre-HTML5 fallback, confirmed via
  `et2_widget_textbox.ts`) - since the real runtime widget is `Et2Number`/`Et2Textbox` (per the tracing
  above), this call has always been `w.set_blur is not a function`, silently throwing whenever a teacher
  actually used "Score per answer" assessment and the per-answer score placeholder was recalculated. Fixed
  by setting `.placeholder` directly (the modern, native equivalent - browsers have supported the HTML5
  placeholder attribute natively for over a decade, so the old fallback branch was already dead weight).
  Not verified against a live multiplechoice question with real answer rows (none was reachable in this
  dev instance's existing test data, and the "Add" control on the course's test/question list didn't
  respond in this environment) - confidence here rests on the widget-factory source trace, which is
  read directly off the code that makes the decision, not inferred from behavior.
- `et2_smallpart_videobar`, `et2_smallpart_cl_measurement_L` are the app's own custom widgets, used as
  genuine runtime **values** (static property/method access - `et2_smallpart_videobar
  .course_options_cognitive_load_measurement`, `et2_smallpart_cl_measurement_L.MODE_CALIBRATION`) - kept
  as value imports, unchanged.
- `et2_smallpart_videooverlay`, `et2_smallpart_videooverlay_slider_controller` (both the app's own
  widgets, only used for casts) -> `import type`. Confirmed safe despite each having its own
  side-effect-registration side file: `et2_smallpart_videooverlay`'s registration is still covered by the
  existing `import './et2_widget_videooverlay'` side-effect import in this same file;
  `et2_smallpart_videooverlay_slider_controller`'s registration is covered transitively, since
  `et2_widget_videooverlay.ts` (side-effect-imported here) itself already side-effect-imports the
  slider-controller module.
- `SmallPartFlagTime` (already had a side-effect import at the top for its custom-element registration) -
  its separate class import switched to `import type`, side-effect import kept, same pattern as tracker's
  `Et2TrackerAssigned`.
- `SmallPartMediaRecorder`, `Et2File`, `SmallPartLiveFeedbackReport` had no import at all - the first two
  were genuine `TS2304: Cannot find name` errors (see below) and the third was a case-mismatched typo
  (`smallPartLiveFeedbackReport`, never resolving to the real exported `SmallPartLiveFeedbackReport`) -
  all three added as `import type` (only ever used for casts).
- `import {egw} from ".../egw_global"` removed - the familiar ambient-global fix.
- `MarksWithArea` (from the app's own `./mark_helpers`, only used as a type) split off from the existing
  `MarkArea` (a real value, static methods) into its own `import type`, and `PushData` similarly split
  off from the existing `EgwApp` value import - same "type-only symbol riding along on a value import"
  pattern addressbook's entry established for non-`et2_*` symbols too.

### TS errors fixed (167 total)

By far the largest batch in this project; grouping by root cause:

- **`this.et2.getDOMWidgetById()`'s `typeof Et2Widget | null` return-type bug** (the same bug CRM.ts and
  status found) accounted for the single biggest chunk - roughly 60 of the 167 errors, spread across
  ~50 call sites. Two different fixes depending on what the existing code already did:
  - Sites with **no cast at all**, directly chaining a property/method onto the raw result
    (`this.et2.getDOMWidgetById('x').set_disabled(...)`) -> wrapped in a single `<any>` cast, matching
    status's exact precedent (`(<any>this.et2.getDOMWidgetById('add')).set_disabled(true)`) rather than
    the `<unknown>` dance, since there's no more-specific widget type to reach for at these call sites.
  - Sites that **already had a specific-type cast** (`<et2_smallpart_cl_measurement_L>this.et2
    .getDOMWidgetById(...)`, `<et2_grid>...`, `<et2_countdown>...`, etc) -> inserted `<unknown>` in the
    middle (`<Type><unknown>this.et2.getDOMWidgetById(...)`), the established CRM.ts/
    `et2_widget_placeholder.ts` workaround.
- **`querySelectorAll(...)` returning generic `Element`** (dozens of sites): used the generic-argument
  form (`querySelectorAll<HTMLElement>(...)`) wherever the preceding call was itself properly typed;
  where the preceding call was untyped/`any` (e.g. chained off another `getDOMWidgetById()`/
  `getWidgetById()` result), putting the generic directly on it re-triggered the exact TS2347 "untyped
  function calls may not accept type arguments" pattern from infolog's `getDOMNode()` note - fixed the
  same way infolog did, by typing the intermediate variable/casting the source first instead of putting
  `<T>` on the untyped call itself (one spot needed `Array.from<HTMLElement>(x.querySelectorAll(...))`
  instead, for the same reason).
- **`getElementsByClassName(...)[0]`/`querySelector(...)` needing `.style`/`.hidden`/`.disabled`/
  `.dataset`/`.contentWindow`**: same generic-argument or `<HTMLElement>`/`<HTMLOptionElement>` cast
  pattern as above, applied per call site.
- **`et2_countdown`'s `onAlarm`/`.timer`**: `onAlarm` is declared only via the widget's dynamic
  `_attributes` registration object (`onAlarm: {type: "js", ...}`), never as a real class field, so TS
  can't see it even after a correct `<et2_countdown>` cast - worked around with a local `<any>` cast,
  same class of gap as `app.stylite`/`et2_grid.cells` elsewhere in this project. `.timer` is a genuinely
  `private` field with no public reset/stop accessor on the class - `<any>` cast, no shared-widget
  change.
- **`et2_smallpart_videooverlay`'s `_elementSlider` is `private`**: has a public `getElementSlider()`
  accessor already defined on the class (confirmed by grep) - swapped all 4 direct `._elementSlider`
  accesses to `.getElementSlider()` instead of casting.
- **`et2_grid.cells` and `et2_widget._children` are `private`/`protected`**: `_children` ->
  `getChildren()` (established pattern); `cells` has no public accessor at all -> `<any>` cast.
- **`SmallPartMediaRecorder`'s `disableMediaSelectors`/`uploadingIsfinished`**: `disableMediaSelectors`
  is a Lit reactive property declared only via `static get properties()`-style registration (same class
  of gap as `et2_countdown.onAlarm`), `uploadingIsfinished` is `private` - both `<any>` cast, no
  shared-widget change.
- **Two genuinely fictional type names**: `pseudoFunction` (cast target for a jQuery custom-selector
  callback) and `et2_widget_video_recorder` (cast target for the `lf_recorder` widget, used at 2 call
  sites) don't exist anywhere in the repo - the first was simply removed (the cast was doing nothing
  valid anyway), the second was replaced with the widget's real class name, `SmallPartMediaRecorder`
  (confirmed via the template: `<smallpart-media-recorder id="lf_recorder">`, and the class's own
  `record()` method matching the call site).
- **Mistyped widget casts in `student_playControl()`**: `volume` and `playback` were both cast to
  `et2_smallpart_videobar` (evidently copy-pasted from the neighboring `videobar` line) even though
  they're a `<et2-description>` label and an `<et2-select>` dropdown respectively per the template -
  retyped to `et2_description`/`Et2Select`, which is what their actual usage (`.set_value()`,
  `.select_options`) already expected. (`et2_description` itself was later corrected to `Et2Description`
  - see the import-list correction above.)
- **`CommentType` missing three real fields**: `comment_cat_sub`, `account_lid` (a login-id, distinct
  from the numeric `account_id`, used to build vfs attachment paths) and `comment_updated` (a
  `DateTime`, used by the "new comments" filter) are all genuinely read/written throughout the file but
  were never declared on the file's own `CommentType` interface - added all three as optional fields,
  rather than casting every access site individually.
- **Fixing the `Et2Template` import unlocked two previously-hidden errors**: once the import resolved
  (previously `TS2304: Cannot find name`), TS could properly type-check `container.addEventListener
  ("sl-show", (event) => {...})` for the first time - `event.target` surfaced as `EventTarget` (needing
  an `Element` cast for `.localName`) and a `NodeListOf<Element>` spread surfaced a `[Symbol.iterator]`
  gap (fixed with `Array.from(...)` instead of `[...x]`) - both previously masked because the whole
  expression was `any` while the import was broken, the reverse of the usual "one fix resolves many
  cascading errors" pattern - here one fix revealed two new, real ones.
- Assorted one-off casts/type-widenings: `let courses/old_videos/group_options/options/tags/rows`
  retyped from an under-specified `Array<object>`/`{}` to `Array<any>`/a concrete element type to match
  how they're actually used; `student_playControl(_status: string)` widened to `string|Et2Select`
  (confirmed via the template: `onchange="app.smallpart.student_playControl(widget)"` on the playback
  `<et2-select>`, vs. plain string ids from every `<et2-button-icon onclick=...>`); `questionTime()`'s
  `_widget` param retyped from legacy `et2_inputWidget` to `Et2InputWidgetInterface` (confirmed via the
  template's `<et2-date-duration onchange="app.smallpart.questionTime">` bindings - a modern widget, not
  legacy).

### Genuine bugs found and fixed

- **`iterateOver(..., et2_checkbox)` always matched zero widgets** (3 call sites: `et2_ready()`'s
  question-init, `childrenChecked()`, `checkMaxAnswers()`'s readonly-disable loop) - this is what made
  converting `et2_checkbox` from a type-only import to a **value** import matter, not just cosmetics.
  `et2_checkbox` (`class et2_checkbox extends Et2Checkbox {}`) is never actually registered anywhere
  (`et2_widget_checkbox.ts` has no `et2_register_widget()` call) and never instantiated - confirmed via
  `et2_createWidget()`/the XML-parsing widget factory, which falls back to the `et2-checkbox` custom
  element (a real `Et2Checkbox` instance) whenever no legacy registry entry exists for the tag.
  `Et2WidgetClass.iterateOver()`'s `_type` filter does a plain `this instanceof _type` check for a
  function/class argument (confirmed in `Et2Widget.ts`) - so `instanceof et2_checkbox` is **always
  false** for any real widget, since real widgets are always `Et2Checkbox` instances, never
  `et2_checkbox`. Net effect: the whole `max_answers` (max number of checked multiple-choice answers)
  feature has been silently non-functional - `childrenChecked()` always returned 0, so
  `checkMaxAnswers()` could never detect "too many checked" or disable the remaining checkboxes. Fixed
  by using `Et2Checkbox` (the real class) as the `_type` filter value at all 3 sites, which is also what
  the type-only-import analysis called for anyway. **(2026-09-02 follow-up: this generalizes** - it was
  never about checkbox specifically having no legacy registration, it's that `createElementFromNode()`
  resolves purely off the literal `.xet` tag string, and every widget tag in this app's templates is
  already written with the modern `et2-` prefix; see the `et2_button`/`et2_textbox` correction below for
  the full mechanism trace.)
- **`defaultPoints()`'s `(<et2_textbox>w).set_blur(default_points)` called a method that doesn't exist on
  the real runtime widget** (found 2026-09-02, while re-verifying `et2_textbox`'s import classification):
  `set_blur()` is legacy-`et2_textbox`-only (a jQuery placeholder-attribute setter with a dead pre-HTML5
  fallback, confirmed in `et2_widget_textbox.ts`) - the answer-score field it's called on
  (`${row}[score]` in `question.xet`) is written `<et2-number>`, which - per the widget-factory trace
  below - is always the modern `Et2Number`/`Et2Textbox`, which has no `set_blur()` at all. This has
  silently thrown `TypeError` whenever a teacher used "Score per answer" assessment and the per-answer
  score placeholder got recalculated - a real, previously-invisible bug, not a modernization-era
  regression. Fixed by setting the modern `.placeholder` property directly.
- **`course_selection.change(course_selection.getDOMNode(), course_selection, 'manage')`**
  (`pushParticipants()`, triggered when the current user is kicked/unsubscribed from a course they're
  viewing): `.change()` doesn't exist anywhere on `Et2Select`/`Et2InputWidget` (confirmed via grep
  across the whole hierarchy) - would throw `TypeError` at runtime. The sibling method
  `courseSelection(_node, _widget)` (used everywhere else for this exact "navigate away because
  'manage' was selected" flow) takes 2 args and checks `_widget.getValue() === 'manage'`. Fixed by
  setting the value first and calling the real method: `course_selection.set_value('manage');
  this.courseSelection(course_selection.getDOMNode(), course_selection);`.
- **RETRACTED (2026-09-02) - `(<et2_tabbox>...).show("participants_tab")` was never actually broken.**
  The original entry here claimed `.show()` "doesn't exist on `Et2Tabs` either" and swapped it for
  `setActiveTab(...)`. Re-investigated while fixing `et2_tabbox` above: `.show()` genuinely exists on
  `Et2Tabs` at runtime (inherited from the Shoelace `SlTabGroup` mixin base - confirmed live,
  `typeof widget.show === 'function'`) - TS just can't see it through the mixin function's return type,
  the same class of blind spot as `et2_countdown.onAlarm`/`app.stylite` elsewhere in this project.
  Worse, `setActiveTab()` (the method the "fix" swapped to) is itself marked `@deprecated use
  this.show(name: string)` in `Et2Tabs.ts`'s own source, and its string-argument branch just calls
  `this.show(tab)` internally - so the swap didn't fix a bug, it silently traded a correct, preferred
  call for a working-but-deprecated wrapper around the exact same call, based on a TS error that should
  have been worked around with a cast instead. Reverted to `.show("participants_tab")` with a `<any>`
  cast (matching the established pattern for this class of gap) and a comment pointing at `Et2Tabs.ts`'s
  own `@ts-ignore` on the identical call, so a future pass doesn't re-introduce the same swap.
- **`self.et2`/`self.egw` referring to the global `Window.self`, not the app instance**: two spots
  (`livefeedback_sessionRefreshed()`, and one branch of `livefeedbackCommentSubmit()`'s `.then()`) used
  `self.xxx` without ever declaring a local `self`/`that` - both are plain top-level methods/arrow
  callbacks with no enclosing closure providing that binding, so `self` fell through to the ambient
  `Window.self`, which has no `.et2`/`.egw`. Both would throw at runtime whenever invoked (a
  push-notification handler and a livefeedback-session-ended callback, respectively). Fixed by using
  `this` directly - both are directly-invoked/arrow contexts where `this` is already correctly the app
  instance.

### jQuery removed (24 uses)

- The dominant pattern by far was `jQuery(smallpartApp.commentRowsQuery, container)` (jQuery-wrapped
  comment-row elements, used with `.filter()`/`.each()`/`.find()` across 8 different comment-filtering
  methods) -> `container.querySelectorAll(commentRowsQuery)` (or a combined selector string for the
  simple class-based `.filter()` calls, e.g. `commentRowsQuery + ':not(.cat-lf, .cat-lfc)'`), with
  `.each(function(){...this...})` callbacks converted to `.forEach(row => {...row...})` arrows in the
  same edit (satisfying goal 6 together, same combined pattern used repeatedly elsewhere in this
  project).
- `student_searchFilter()`'s custom `jQuery.expr[':'].containsCaseInsensitive` pseudo-selector
  registration has no native equivalent at all (browsers have no API for registering custom CSS
  pseudo-selectors) - rewrote the whole per-row "does this row contain a case-insensitively-matching,
  optionally-class-restricted descendant" check as a plain `Array.from(row.querySelectorAll('*'))
  .some(...)` walk, preserving the exact same matching semantics (including the "unless search-all is
  checked, only `.et2_smallpart_comment` elements count" restriction) rather than trying to keep the
  selector-string API shape.
- `jQuery.extend(target, source)` (2-arg, shallow) -> `Object.assign(target, source)` (4 sites);
  `jQuery.extend([], data)` (shallow-copy-into-array) -> `[...data]` (2 sites) - none of these were the
  3-arg `jQuery.extend(true, ...)` deep-merge form this project's `egw.deepExtend()` rule targets, so
  `Object.assign`/spread is the correct native equivalent here, not `deepExtend()`.
- `student_onmouseoverFilter()`'s `jQuery(elem).on('mouseenter', fn).on('mouseleave', fn)`/
  `.off('mouseenter mouseleave')` toggle (enabling/disabling a pause-on-hover behavior) needed more than
  a 1:1 rename: native `removeEventListener` requires the *same* function reference used in
  `addEventListener`, but the original per-call `function(){}` literals were recreated fresh every time
  the method ran, so a naive rewrite would silently leak listeners on "off". Fixed by hoisting the two
  handlers out as stable, `this`-bound class-field arrow functions (`private _onmouseoverEnter = () =>
  {...}`) so add/remove always target the same reference.
- `_student_highlightSelectedComment()`'s `jQuery(node).find(...)`/`.addClass()`/`.removeClass()`/
  `[0].scrollTop` -> plain `querySelector`/`querySelectorAll(...).forEach(...)`/`classList.add`/
  `.remove`/`.scrollTop` on the raw elements (no more `[0]` indexing needed once it's not jQuery-wrapped).
- `jQuery(_widget.parentNode).closest('tr')` -> native `.closest('tr')` (already exists on `Element`,
  just needed a cast since `parentNode` is typed `Node`); `.siblings().removeClass(...)` has no native
  one-liner - replaced with `Array.from(tr.parentElement.children).filter(el => el !== tr)
  .forEach(...)`, same "manual walk, no 1:1 equivalent" reasoning as addressbook's `.nextAll()` fix.
- `jQuery('.scoreCol').hide()`/`.show()` -> `document.querySelectorAll('.scoreCol').forEach(el =>
  el.style.display = 'none'/'')`.
- `jQuery(window).on('resize', function(){self._student_resize()})` -> `window.addEventListener
  ('resize', () => {this._student_resize()})`, done together with removing the now-unnecessary
  `let self = this` (see below).
- Six stale `JQuery.Event` **type annotations** (actual TS param types on 6 onchange/onclick handler
  methods, not JSDoc comments here) corrected to `Event` - confirmed none of the 6 method bodies ever
  read the event param at all.

### `egw.json(...).sendRequest()` -> `egw.request()`

- 11 of 14 sites were plain async (`sendRequest()` with no args, the default) - straightforward swaps.
- 3 sites are genuinely **not** swappable: `sendRequest('keepalive')` (not `sendRequest(false)`, but the
  same "no equivalent in `egw.request()`" situation) - used exactly where the `beforeunload` handler
  needs the request to survive page unload via the fetch keepalive flag (`record_watched()`,
  `set_video_position()`, and one inline CLM-measurement call, all reachable from the `beforeunload`
  listener). `egw.request()` has no keepalive option at all, only plain async - kept as
  `egw.json(...).sendRequest('keepalive')` with a comment explaining why, extending this project's
  sync/async exception to cover the keepalive variant too.

### function/closures -> arrow functions

- Removed **6** `let self = this`/`const self = this` declarations: 5 because their only uses were
  inside plain functions or arrows that never actually needed dynamic `this` (the `resize`/
  `beforeunload` handlers above; `student_playVideo()`'s two `play_video(...)` callbacks - confirmed via
  `et2_widget_videobar.ts`'s own `play_video()` that `ended_callback.call()` passes no `this` and
  `_onTagCallback.call(this, ...)` passes the *video DOM element*, neither of which the callback bodies
  ever read, so converting to arrows changes nothing observable; `_student_fetchAccountData()`'s nested
  `egw.accountData(..., function(_d){...})` pair, whose 5th-arg `_context` (`egw(window)`) is likewise
  never read inside; `_student_commentsFiltering()`'s callbacks, which were already arrows using `self`
  redundantly) - and 1 that was entirely **dead** code (`student_livefeedbackSubCatClick()` - `self` was
  declared but never referenced anywhere in the method), removed outright, same as addressbook's
  `rename_list()` finding.
- `Et2Dialog.show_prompt(function(button, nickname){...this.user...this.changeNickname()...}
  .bind(this), ...)` (`changeNickname()`) - already explicitly `.bind(this)`-locked (a real, deliberate
  goal-6 exception marker), so the dialog's own `_callback.call(this_dialog, ...)` rebinding was already
  neutralized by the bind. Converted to a plain arrow function and dropped the now-redundant
  `.bind(this)` - same result, less code, matching tracker's `tprint()` precedent of removing the
  dynamic-`this` dependency entirely rather than just documenting it.
- `iterateOver(function(_widget){...this...}, this, ...)` (3 sites: `et2_ready()`'s question-init,
  `childrenChecked()`, `checkMaxAnswers()`) - `this` is passed explicitly as `iterateOver`'s own
  `_context` argument and is the *same* object an arrow's lexical `this` would resolve to (both from the
  same enclosing method) - the exact refinement tracker's entry already established. Converted to
  arrows.
- `confirmOverwrite()`'s `dialog.transformAttributes({callback: function(_button){...widget...}})` -
  body only touches the closured `widget` local, never `this` - converted to arrow despite the
  dialog-callback `this`-rebinding contract, since nothing depends on it here.
- **8 confirmed, documented exceptions remain**: `dialog.transformAttributes({callback:
  function(...){...self...}})`/`Et2Dialog.show_dialog(function(...){...self...})` pairs across
  `_student_setPostCLQuestions()`, `_student_clm_l_start()`, `student_deleteComment()`,
  `livefeedback_timerStop()` (5 sites), `videobar.set_marking_enabled(true, function(){...self...})` (2
  sites, confirmed via `et2_widget_videobar.ts`'s own `this.marking_callback(...)` invocation, which
  rebinds `this` to the videobar), and one `setTimeout(function(){this.querySelector(...)}.bind(d), ...)`
  with an explicit `.bind(d)` - all verified via the same "manual `self`/explicit `.bind()` already
  present is reliable evidence of real `this`-rebinding" reasoning as status's entry. The 4 methods with
  a dialog-callback exception still needed a live `self` capture for exactly that one nested callback
  each, so `let self = this` had to stay there (with a one-line comment explaining why) even where the
  *surrounding* arrow-based code in the same method no longer needed it.

### Not touched (out of scope)

- `livefeedback_timerStart()`/`livefeedback_timerOnPulse()`'s `let lf_report = this.et2
  .getWidgetById('lf_report')` and its `.sessionStartTime`/`.sessionEndTime`/`.comments` property
  assignments don't error (the containing expression resolves loosely enough that TS doesn't flag it) -
  left alone, matching tracker's precedent that goal 3 only covers the file's actual reported errors,
  not every instance of an already-fixed-elsewhere pattern.
- `mergeVideo()`'s `const that = app.smallpart;` with its own pre-existing developer comment ("not sure
  why this is not defined/the window, binding it in the constructor also did not help") - only the
  *type* was fixed (cast to `<smallpartApp>`); the underlying self-reference-via-global-registry pattern
  itself was left exactly as-is, not refactored, since it already works correctly at runtime and isn't
  part of any of the 6 goals.

### Two more genuine bugs found via live browser testing (2026-09-02, post-completion)

Neither is a modernization-goal fix (not TS/jQuery/var/sendRequest/closure related) and neither is in
`app.ts` - both were found by actually clicking through the live app after the pass above, and fixed as
small, clearly-scoped exceptions to the "app.ts only" rule since they're real crashes, not style issues:

- **`et2_ready()`'s unguarded `.getWidgetById('smallpart.student.comments_list').getWidgetById
  ('add_comment').hidden = ...`** threw `Cannot set properties of null (setting 'hidden')` - the inner
  `getWidgetById('add_comment')` lookup, scoped to a lazily-loaded named sub-template, can legitimately
  return `null`. Pre-existing (confirmed via `git show HEAD` - present before this whole project's pass
  even started), not introduced by any of the above. Fixed with a null-guard (`if (widget) widget.hidden
  = ...`), and applied the same guard to the sibling `add_comment`-in-`playControlBar` lookup one line
  above for consistency (same pattern, same file, same block).
- **`MultiVideo.play()` in `api/js/etemplate/CustomHtmlElements/multi-video.ts`** (a shared, cross-app
  widget - not smallpart's own code, but the video player smallpart's `et2_widget_videobar.ts` sits on
  top of) - `return this.__getActiveVideo()[0]?.node?.play();` silently returns `undefined` (via the
  optional-chain short-circuit) whenever there's no active video node to play - which is exactly what
  happens when a course's video material is missing/broken (as this dev instance's "Test from mp4"
  course happened to be). Every caller (`et2_widget_video.ts`'s `play_video()`, and
  `et2_smallpart_videobar.play_video()` on top of that) unconditionally chains `.then()` onto the result,
  assuming the native `HTMLMediaElement.play()` contract (always a Promise) - so a missing video crashed
  with `Cannot read properties of undefined (reading 'then')` on every attempt to resume playback (e.g.
  clicking "Cancel" on the add-comment form, which calls `student_cancelAndContinue()` ->
  `student_playVideo()` -> `play_video()`). Fixed at the root cause by making `play()` always return a
  Promise: `?? Promise.resolve()`. Verified live (traced the actual DOM element - `videobar.video[0]`'s
  `tagName` is `MULTI-VIDEO`, not a native `<video>`, confirming this exact code path) and reproduced the
  crash and its fix end-to-end (add comment -> Cancel -> previously crashed, now resumes cleanly).

### Tests

Reviewed for a suitably pure, standalone piece of logic to regression-test (the "genuine bug found"
angle this project's workflow calls out) - none of the fixed bugs are pure functions:
`checkMaxAnswers()`/`childrenChecked()` (the `Et2Checkbox` `iterateOver` bug) and
`courseSelection()`/`changeCourseGroups()` (the two nonexistent-method bugs) are all methods tightly
coupled to `this.et2`'s live widget tree, exactly the kind of heavyweight DOM-fixture test this
project's workflow says not to force. No test file added, consistent with every other app.ts done in
this project so far (none of which added one either).

## home/js/app.ts (done)

538 -> 538 lines, main repo (not a nested git repo). Baseline: 0 `var` (already clean), 2 jQuery hits, 11
TS errors, 2 `egw.json(...).sendRequest()` sites, 2 live `function(` occurrences, 0 `self`/`that`
closure-captures. Small/modest file, comparable to timesheet/tracker - but it hid a genuine, previously
unreachable runtime crash (see below), which only surfaced by actually clicking the feature live per this
project's now-standard verify-live discipline.

### Legacy widget imports

- **`et2_portlet`** (`set_default()`'s "select all" branch, `iterateOver(_callback, this, et2_portlet)`)
  had **no import at all** (`TS2304: Cannot find name`) - and this is the classification-trap lesson
  playing out for real, not just in theory. `et2_widget_portlet.ts` does define a real, distinct legacy
  `et2_portlet extends et2_valueWidget` class, registered under the *unprefixed* short name `"portlet"`.
  But `home/templates/default/index.xet` writes the tag as `<et2-portlet ...>` (prefixed) for every real
  portlet on the page, and `Et2Portlet.ts` (`api/js/etemplate/Et2Portlet/Et2Portlet.ts`, `extends
  Et2Widget(SlCard)` - a completely unrelated class hierarchy from the legacy one) registers
  `customElements.define("et2-portlet", Et2Portlet)`. So every actual portlet widget on the page is an
  `Et2Portlet` instance, never an instance of legacy `et2_portlet` - meaning `iterateOver(..., et2_portlet)`
  could never have matched anything even if `et2_portlet` had been imported. The right fix per the
  classification-trap lesson isn't to add the legacy import (goal 1 would then immediately want it
  replaced anyway, since a real `.xet`-driven check shows it's dead weight) - it's to use the already-
  imported modern `Et2Portlet` class instead, which is what the `instanceof`-style filter is actually
  supposed to match. See "Genuine bugs found and fixed" below - this was live-verified as a real crash,
  not just a hypothetical.
- **`Sortable`** (`sortablejs`'s default export, used only for one field's type annotation, `private
  sortable : Sortable`) - the field itself is never assigned or read anywhere in the file (dead field,
  left alone per this project's restraint principle - it's not one of the 6 goals to prune unused fields,
  and removing it risks guessing wrong about a future/external use). Switched the import to `import type
  Sortable from "sortablejs/modular/sortable.complete.esm.js"` since it's genuinely type-only here.
- **`egwAction`** (`get_portlet_tag(action : egwAction)`'s param type) had no import (`TS2304`) - same
  real, concrete exported class from `api/js/egw_action/egw_action.ts` every other app in this project has
  found - added `import type {egwAction}`. (`egwActionObject` only appears in a JSDoc `@param` comment on
  `set_default()`, never a real type annotation, so it needed no import.)
- `et2_createWidget`, `EgwApp`, `etemplate2`, `Et2Portlet`, `Et2PortletFavorite`, `loadWebComponent`, and
  `Et2Dialog` were all already correctly value imports (each is instantiated/called as a value somewhere
  in the file: `et2_createWidget("dialog", ...)`, `extends EgwApp`, `etemplate2.getByApplication()`, `new
  _class(...)` where `_class` can resolve to `Et2Portlet`/`Et2PortletFavorite`, `loadWebComponent(...)`,
  `Et2Dialog.OK_BUTTON`/`.BUTTONS_OK_CANCEL`) - nothing to change on any of them. The side-effect-only
  imports (`Et2PortletLink`/`List`/`Note`/`Weather`, `calendar/js/Et2PortletCalendar`) that register their
  custom elements also stayed as-is.

### TS errors fixed (11 total)

- **`attrs.row`/`attrs.col` don't exist** (4 sites, 2 each in `add()` and `add_from_drop()`): both
  methods build an `attrs` object literal from a fixed initial shape (`{id, class, settings}` /
  `{id, class, dropped_data}`) and then conditionally bolt on `row`/`col` a few lines later depending on
  whether the action carries position info. There's no shared interface for this ad-hoc, dynamically-grown
  bag of properties (checked `Et2Portlet.ts` - it reads `attrs.row`/`attrs.col`/`attrs.settings` just as
  loosely, no formal type either) - annotated both locals `let attrs : any = {...}` rather than inventing
  a one-off interface for a shape that's only ever used internally in these two methods.
- **`portlet.loadingFinished()` called with 0 args, expects 1** (2 sites): `Et2Widget.loadingFinished
  (promises : Promise<any>[])` (`api/js/etemplate/Et2Widget/Et2Widget.ts:1226`) defensively handles
  `promises === undefined` at runtime (`if(typeof promises === "undefined") promises = [];`) - the
  deprecated-vs-preferred trap's sibling case: not a deprecated-alternative-method situation, but the
  same underlying shape (a shared widget method that's runtime-safe with 0 args but not typed that way).
  Rather than widen the shared method's signature (out of scope, same reasoning as every prior pass that
  left `Et2Widget`/`Et2Template` alone), passed `portlet.loadingFinished([])` at both call sites - matches
  what the method does internally anyway.
- **`this.add(action)` called with 1 arg, `add()` expects 2** (`add_from_drop()`'s "actions got confused
  drop vs popup" fallback): `add(action, source)`'s `source` param is never referenced anywhere in `add()`'s
  own body (confirmed by reading the whole method) - made it optional (`add(action, source?)`) rather than
  force the fallback call site to pass a placeholder, same pattern as `filter_change(ev?, filter?)` in
  every earlier app in this project.
- **`et2_portlet` (`TS2304`)**: see "Legacy widget imports" above and "Genuine bugs found and fixed" below
  - fixed by swapping to `Et2Portlet`, not by adding the legacy import.
- **`egwAction` (`TS2304`)**: see "Legacy widget imports" above.
- **`customElements.get(classname).class` doesn't exist on `CustomElementConstructor`**
  (`_get_portlet_code()`): checked whether any `Et2Portlet*` subclass defines a static `class` property
  anywhere in the repo (`grep -rn "static class"` across `home/js/*.ts` and `calendar/js/Et2PortletCalendar.ts`)
  - none do, so this fallback branch is dead at runtime regardless of the type error (confirmed
  `_get_portlet_code()` itself has zero callers anywhere in the repo - see "Not touched" below). Cast to
  `<any>` rather than invent a real shape for a codepath nothing reaches.
- **`Object.keys(col_map[row]).at(-1)` - `Array.prototype.at()` needs `lib: es2022`** (`_do_ordering()`):
  this repo's `tsconfig.json` targets `es2020` (`lib: ["es2020", "dom"]`), a shared, repo-wide setting -
  out of scope to bump for one file. Swapped to the `es2020`-safe equivalent, `Object.keys(col_map[row])
  .slice(-1)[0]`, same negative-last-element semantics without needing the newer lib.

### jQuery removed

- `jQuery(this.portlet_container.getDOMNode()).offset().top` (2x, `add()`/`add_from_drop()`, used to find
  the portlet container's page-relative top offset for placing a newly-added portlet near the menu/drop
  position) -> `this.portlet_container.getDOMNode().getBoundingClientRect().top + window.scrollY`. jQuery's
  `.offset()` is page/document-relative (accounts for scroll); `getBoundingClientRect()` alone is
  viewport-relative, so the native replacement needs the explicit `+ window.scrollY` to match - a
  same-value swap, not just a same-shaped one.

### `egw.json(...).sendRequest()` -> `egw.request()`

Both sites (`set_default()`'s "remove default" and "add default" branches) were fire-and-forget async
(`sendRequest(true)`) - straightforward 1:1 swaps to `egw.request(...)`, no genuinely-synchronous calls in
this file.

### function/closures -> arrow functions

- `set_default()`'s `iterateOver(function(portlet) {...}, this, et2_portlet)` callback explicitly passes
  `this` as `iterateOver`'s own `_context` argument - the same tracker-established exception-to-the-
  exception: since that `this` is exactly the same object the arrow's lexical `this` would already resolve
  to (both come from `set_default()` itself), converting to an arrow is safe, and the callback never used
  `this` anyway (only the closured `portlet_ids` and the `portlet` param). Converted together with the
  `et2_portlet` -> `Et2Portlet` bug fix above (same line).
- The dialog's `callback: function(button_id, value) {...}` (`set_default()`'s "add default" branch) -
  checked first that the body never uses `this` (only `button_id`, `value`, and the closured
  `portlet_ids`/`group`/`egw` - all locals/globals, no `this.` anywhere) - safe to convert regardless of
  whatever `this` `Et2Dialog` itself binds the callback to, same reasoning as addressbook's dialog-callback
  conversions.
- The two `function(){...}` occurrences inside the fully commented-out gridster-era block at the bottom of
  the file (`init: function(portlet) {...}` and its nested `getCurrentPosition(function(position) {...})`)
  are inside a `/* ... */` block comment, not live code - don't count toward goal 6 and weren't touched
  (nothing to convert; it's not code).

### Genuine bugs found and fixed

- **`set_default()`'s "select all" branch threw `ReferenceError: et2_portlet is not defined` on every
  click, unconditionally** - confirmed live in the browser (not just from reading the TS error). This is
  the "Set as default" context-menu action available when right-clicking the general portlet background
  (as opposed to a specific portlet), which takes the `selected[0].id == 'home.index'` branch and is
  therefore the *only* way to reach `iterateOver(..., et2_portlet)` at runtime. Verified the crash on the
  unmodified code first (git-stashed the fix, rebuilt, reloaded, clicked "Set as default" from the
  background context menu -> `ReferenceError: et2_portlet is not defined` at `set_default`, confirmed via
  console stack trace), then restored the fix and confirmed the same click no longer throws and instead
  proceeds to build `portlet_ids` and open the confirmation dialog. This means the entire "set all
  portlets as default for a group" admin feature was completely non-functional before this fix - it could
  never have collected any portlet IDs (or even gotten that far without crashing) since the file was
  written with the `et2-portlet` tag prefix in `index.xet` (i.e., probably since `Et2Portlet` was
  introduced as a web component, given `iterateOver`'s `_type` filter would have simply matched nothing,
  silently, before this specific `ReferenceError` was even possible - `et2_portlet` being *unimported* is
  what turns a silent no-op into a hard crash, and TS's `TS2304` for that exact name is what surfaced it
  here).

### Not touched (out of scope)

- **`set_default()`'s dialog renders empty** - only discovered *because* the crash above is now fixed
  (the dialog is created a few lines after the now-working `iterateOver` call, so it was previously
  unreachable from the "select all" path - though it's reachable from the per-portlet "remove default"-less
  branch too, and behaves the same there). Live-verified: `et2_createWidget("dialog", {template:
  "home.set_default", buttons: Et2Dialog.BUTTONS_OK_CANCEL, ...})` does create a `<legacy-dialog>` element
  (confirmed via `document.querySelector('legacy-dialog')`), but its content stays genuinely empty - no
  title, no OK/Cancel buttons, no rendered `<et2-vbox>`/`<et2-description>`/`<et2-select-account>` from the
  `home.set_default` sub-template that's defined right in `home/templates/default/index.xet` (confirmed the
  template markup itself is fine and inline in the same file, not a separate lazy-fetched template - no
  network request for it ever fires, consistent with that). This isn't a goal-1-through-6 issue (no TS
  error, no jQuery, no `var`, nothing this pass's mechanical checks would catch) and digging into *why*
  `et2_createWidget("dialog", {...})` fails to populate a templated legacy dialog is a much larger
  investigation into `Et2Dialog`/legacy-dialog-factory internals, well beyond an app.ts-scoped pass -
  flagged here as a real, currently-live bug for whoever picks up the dialog/legacy-widget-factory code
  next, not fixed.
- **`_get_portlet_code()`** has zero callers anywhere in the repo (checked `home/js/`, `api/js/`,
  `calendar/js/`, and any `.php` references) - likely dead code, same shape as addressbook's
  `refreshCallback` finding. Left in place rather than deleted: it's not blocking any of the 6 goals (its
  one `<any>`-cast fix above was mechanical), and per this project's restraint principle, deleting a whole
  method on a "looks unused" read is a bigger, less certain call than removing a single dead import line.
- The two-portlet-container jQuery `.offset()` fix and the `attrs : any` typing both touch the same two
  methods (`add()`/`add_from_drop()`) - no interaction/ordering issue between them, but noting it since a
  future re-read of the diff might wonder why both changes land in the same few lines.
