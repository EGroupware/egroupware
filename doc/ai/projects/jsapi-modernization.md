# JS API Modernization (`api/js/jsapi`)

## Goal

`api/js/jsapi` is the legacy `egw()` composition engine and its ~20 sibling modules
(`egw_config.ts`, `egw_json.ts`, `egw_links.ts`, ...) - the foundation every app's `egw`/`egw(app,wnd)`
object is built from. This was a two-phase, multi-session effort:

1. **TS-typing port** - every `egw_*.js` module ported to `.ts` and given types via declaration
   merging (`export interface XModule {}` + `declare global { interface IegwGlobal extends XModule {} }`),
   while deliberately preserving each module's *internal* shape byte-for-behavior: still an anonymous
   `egw.extend(name, FLAGS, function(){ var...; return {...}; })` factory closure, just with types
   bolted on top.
2. **Modernization** (this doc's main subject) - converting what's *inside* each module: the factory
   closure body became a real `class X implements XModule`, for IDE navigation (go-to-implementation,
   outline view), refactor-rename, and true private state - **without touching the public composition
   contract** (`egw(app, wnd)`, `egw.extend()`, the `MODULE_GLOBAL`/`APP_LOCAL`/`WND_LOCAL` flags) that
   ~270 external call sites across every app depend on.

Both phases are complete as of 2026-08-09. This doc covers the design decisions from phase 2, since
that's where the interesting constraints live, plus what got explicitly left out of both phases and
what's still tracked-but-unfixed.

## The technical constraint that shapes everything

`egw_core.ts`'s three merge functions (`mergeGlobalModule`/`mergeAppLocalModule`/`mergeWndLocalModule`)
all funnel through one helper:

```ts
function mergeObjects(_to : any, _from : any) : void
{
    for (var key in _from) { _to[key] = _from[key]; }
}
```

`for...in` walks the prototype chain, but **standard ES class methods are non-enumerable** on the
prototype (`class Foo { bar() {} }` - `bar` doesn't show up in `for...in`). A module's factory
returning a real class instance with regular methods would silently lose every method at merge time.

This was a deliberate decision made up front, not a workaround discovered later: don't "fix" the
engine to walk prototype chains just to support plain class methods - that's speculative complexity
for no real gain, and `egw_core.ts` itself was intentionally left untouched throughout the whole
modernization phase.

**The resulting rule: every interface-exposed method must be an arrow-function class field**
(`methodName = (...) => {...}`), never a plain `methodName() {}` method. Arrow fields are own,
enumerable instance properties - indistinguishable from what the old plain-object-literal factory
already produced, so the merge behaves identically.

## The conversion pattern

```ts
// Before
egw.extend('debug', egw.MODULE_GLOBAL, function() : DebugModule
{
    "use strict";
    var DEBUGLEVEL = 3;
    function log_on_client(_level) { ... }
    return {
        debug_level: function() { return DEBUGLEVEL; },
        debug: function(_level, ...) { ... },
    };
});

// After
class Debug implements DebugModule
{
    #DEBUGLEVEL = 3;                                    // true-private state, see below

    private log_on_client(_level : string) : boolean    // internal helper: regular method is fine,
    { ... }                                              // never merged/enumerated

    debug_level = () : number => this.#DEBUGLEVEL;       // public interface member: arrow field
    debug = (_level : string, ..._args : any[]) : void => { ... };
}

egw.extend('debug', egw.MODULE_GLOBAL, () => new Debug());
```

Conventions applied to every module:

- **Class name** = the module's concept, PascalCase, no `Module`/`Impl` suffix (`Debug`, `Timer`,
  `Links`) - the exported *interface* already carries the `XModule` suffix, and the file already
  disambiguates (`egw_debug.ts`).
- **`implements XModule`** - the hand-written interface doesn't change at all; only the factory body's
  shape changes.
- **Public interface methods -> arrow class fields** (required for the merge, see above).
- **Internal state -> true `#private` fields** (not TS `private` - see the dedicated section below).
- **Internal helper functions -> regular `private` methods** when they touch instance state (never
  part of the merged surface, no enumerability constraint) - or **plain module-scope functions**
  outside the class when they're genuinely stateless (no `this`/instance-state dependency at all), e.g.
  `egw_calendar.ts`'s `convertPhpDateTimeFormat`, `egw_data.ts`'s `clearCache`, `egw_timer.ts`'s
  `formatTime`/`formatUTCTime`/`getTimes`, `egw_links.ts`'s `urlencode`. Privatizing something that
  doesn't touch instance state buys nothing and risks an accidental name clash with a same-named public
  method.
- **`egw.extend()` call becomes a one-line factory**: `egw.extend('name', egw.MODULE_X, (_app, _wnd) => new ClassName(_app, _wnd));`
  - only the constructor params the module actually uses are kept (many classes drop an unused `_app`).

## Dynamic `this` vs. lexical `this` - the trickiest part

Some original methods use `this` to reach *other* modules' contributed methods/state dynamically
(`this.debug(...)`, `this.lang(...)`, self-recursion like `this.langRequire(...)`), where `this` must
resolve to **whichever `egw(app,wnd)` instance the caller used** - not be lexically bound to the
converted class instance. This is directly visible in code like `egw_lang.ts`'s
`if (this !== egw && apps.length > 0)` inside `langRequire()`.

Four patterns cover every case found across all ~21 modules:

1. **No dynamic dispatch, only own state** -> plain arrow field (`methodName = (...) => { this.#field }`).
2. **Dynamic dispatch, no own state needed** -> plain `function` field
   (`methodName = function(this: any, ...) { this.otherModuleMethod(...) }`) - preserves whatever
   `this` the caller invoked through.
3. **Both dynamic dispatch AND own private state** -> the self-capture IIFE pattern:
   ```ts
   methodName = ((self : ClassName) => function(this : any, ...args) {
       // self.#privateField for own state, this.xxx(...) for dynamic dispatch
   })(this);
   ```
4. **DOM event listeners / library callbacks with their own `this`-binding rules** (a browser
   `addEventListener` callback, a jQuery UI dialog button handler) - stay plain `function`s with a
   `self`-capture, since `this` there is neither the calling egw instance nor the class instance, it's
   whatever the browser/library binds it to.

Getting this wrong silently breaks the *specific* call site that needed dynamic dispatch, not the
whole module - which is why every conversion commit's message spells out, per method, which pattern
was used and why (see `git log --oneline -- api/js/jsapi/egw_*.ts` for the individual writeups).

## The `#private` vs TS `private` bug - read this before writing more jsapi classes

**TypeScript's `private` keyword is compile-time only.** A `private fieldName = value;` class **field**
(as opposed to a `private methodName() {}` **method**) compiles to a completely ordinary, enumerable
own property at runtime - `mergeObjects`'s `for...in` copies it onto every `egw(app,wnd)` instance right
alongside the real interface methods.

This wasn't hypothetical: `egw_user.ts`'s `private request: Promise<any> = null;` field got merged and
silently overwrote the real `egw.request()` method (contributed by `egw_json.ts`), breaking every
caller of `egw.request(...)` with `TypeError: egw.request is not a function`. It wasn't caught by
`typecheck` - only by the full test suite actually failing. Nine already-converted modules had to be
retrofitted once this was found.

**The same bug hides in `constructor(private _wnd : Window)`** - TypeScript's parameter-property
shorthand *also* compiles to a plain `this._wnd = _wnd;` assignment. TS has no `#`-prefixed parameter
property, so this form is easy to write without noticing the risk.

**The fix, applied everywhere**: use true JS `#private` fields for all internal module state. They are
never enumerable and never accessible outside the class under any circumstances - categorically
eliminating the leak, with no need to avoid same-named public methods either (`#accountData` next to a
public `accountData()` method is fine, since `#` fields live in a completely separate namespace).

```ts
// Wrong - looks private, isn't at runtime
class Foo { private count = 0; constructor(private _wnd: Window) {} }

// Right
class Foo {
    #count = 0;
    #wnd : Window;
    constructor(_wnd : Window) { this.#wnd = _wnd; }
}
```

## jQuery removal (folded into the modernization pass)

A standing repo rule (see `AGENTS.md`) is no *new* jQuery usage in TS/JS. Mid-way through this phase
the user asked for a retroactive audit of every already-modernized module too. Patterns applied where
a clean native equivalent existed:

| jQuery | Native replacement |
|---|---|
| `jQuery.extend(true, ...)` | the new shared `deepExtend()`, extracted to `egw_utils.ts` (see below) |
| `jQuery.extend({}, x)` (shallow) | `{...x}` spread |
| `jQuery.extend(params, extra)` (merge into) | `Object.assign(params, extra)` |
| `jQuery.isArray(x)` | `Array.isArray(x)` |
| `jQuery.inArray(x, arr) != -1` | `arr.includes(x)` |
| `jQuery.isEmptyObject(x)` | `Object.keys(x).length === 0` |
| `jQuery(selector)` | `document.querySelector`/`querySelectorAll` |
| `jQuery(elem).addClass()/.attr()/.on()/.append()` | `classList.add()`/`setAttribute()`/property assignment/`addEventListener()`/`appendChild()` |
| `jQuery(htmlString).appendTo(...)` (HTML-fragment parsing) | a `<template>` element + `Array.from(template.content.childNodes)`, replicating jQuery's "clone for every target but the last" semantics by hand |
| `jQuery(window).outerWidth()/.outerHeight()` | the window's own `.outerWidth`/`.outerHeight` properties directly (what jQuery reads internally anyway) |

**`deepExtend()`** was a `jQuery.extend(true, ...)` alternative that already existed inside
`egw_links.ts`, reachable only via `this.deepExtend(...)`/`egw.deepExtend(...)` (requiring the `links`
module to be registered first). Moved to a plain exported function in `egw_utils.ts`
(`export function deepExtend(out, ...arguments_)`), directly `import`able with zero module-registration
ordering dependency. `egw_links.ts`'s own `deepExtend()` method now just delegates to it, kept only for
external callers already using `egw.deepExtend(...)`/`this.deepExtend(...)` (e.g. `mail/js/app.ts`,
`Et2Favorites/Favorite.ts`).

Three call sites were deliberately **not** converted - see "Postponed" below.

## Current status - all modules converted

Every `egw.extend()` module under `api/js/jsapi/` is now a real class:

`egw_debug.ts` (pilot) - `egw_config.ts` - `egw_lang.ts` - `egw_images.ts` - `egw_css.ts` -
`egw_store.ts` - `egw_ready.ts` - `egw_calendar.ts` - `egw_notification.ts` - `egw_tooltip.ts` -
`egw_preferences.ts` - `egw_user.ts` - `egw_utils.ts` - `egw_open.ts` - `egw_files.ts` - `egw_json.ts` -
`egw_jsonq.ts` - `egw_data.ts` (two independent classes, `Data`+`DataStorage`) - `egw_message.ts` -
`egw_links.ts` - `egw_timer.ts`.

`egw_json.ts` also gained a genuinely new class, `JsonRequest` (the per-request object returned by
`egw.json()`/`egw.request()`) - previously an ES5-style `function` constructor with
`.prototype.method = ...` assignments, now a real class. Unlike every `egw.extend()` module,
`JsonRequest` instances are never merged into `egw`, so its `this` is ordinary, boring class-instance
`this` throughout - no dynamic-dispatch/self-capture concerns at all. It still needs to reach into the
*owning* `Json` module instance's shared state though (the plugin registries and the one push-websocket
connection are per-window, shared across every request) - `#private` fields can't be reached across
classes even with a reference to the instance, so `Json` exposes a small set of `get`/`set` accessors
(non-enumerable, same as any prototype method) for `JsonRequest` to go through.

## Deliberately out of scope (both phases)

- **`egw_inheritance.js`, `jsapi.js`, `app_base.js`, `egw.js`** - not ported to TS or converted to a
  class at all. Investigated repo-wide: `egw_inheritance.js` only defines the old `window.Class`/
  `window.Interface` classical-inheritance system (2011-era) and isn't an `egw.extend()` module - it
  never touches the `egw` object. Its only real consumer is `app_base.js` (`AppJS = Class.extend(...)`),
  the predecessor to the modern `EgwApp` TS class. A repo-wide grep for `Class.extend(`, `new Interface(`,
  `extends AppJS`, `AppJS.extend(` found no other real hits - every `app.ts` still mentioning "AppJS" did
  so only in a stale `@augments AppJS` JSDoc comment (since fixed to `@augments EgwApp`). `app_base.js`
  exists purely for backward compat with third-party/custom apps that might still define
  `app.classes.foo = AppJS.extend(...)` in their own external code - nothing in-repo needs it, so it's
  staying exactly as-is. `egw.js` is a large page-bootstrap IIFE (DOM script-tag attributes, jQuery,
  dynamic legacy includes, websocket/popup handling); several converted modules (e.g. `egw_json.ts`)
  still `import './egw.js'` purely for its side effects.
  - `egw_inheritance.js` was however removed from `egw_modules.js`'s import list (it was being
    force-bundled into every page's core `egw.min.js` for no reason) - `app_base.js` imports it
    directly itself, so it's still fully available for `app_base.js`'s own consumers.
- **`egw_tail.ts`** - a `DOMContentLoaded` page-controller (log-tailing UI), not an `egw.extend()`
  module at all. Doesn't fit the class-conversion pattern (there's no factory-closure/merge concern to
  begin with), left as-is.

## Postponed jQuery removal

Three call sites were left on jQuery rather than mechanically rewritten, because each is a genuine
protocol/library dependency rather than incidental DOM manipulation with a clean native swap:

1. **`egw_debug.ts`'s `show_log()` dialog** - the whole log-viewer dialog is built with jQuery UI
   (`jQuery(...).dialog(...)`). jQuery UI is no longer bundled with EGroupware at all, so this is
   already broken in production today, not just stale: the guard checks `jQuery.ui.dialog` without
   first checking `jQuery.ui` exists, so it throws a `TypeError` instead of degrading gracefully (see
   `EgwDebug.test.ts`'s `KNOWN BUG` for `show_log()`, listed below). Needs a real rewrite - likely to
   Et2Dialog or a native `<dialog>` element - not a mechanical port, since there's no jQuery-UI-dialog
   API to swap 1:1.
2. **`egw_utils.ts`'s `getHiddenDimensions()`** - real traversal/measurement logic
   (`.parents().andSelf().not(":visible")`, `.outerWidth()`/`.width()`/`.offset()`) with no 1:1 native
   substitution, and it's the exact function tied to an already-documented Chromium-only bug (see
   `EgwUtils.test.ts`'s `KNOWN BUG`, listed below) - a careless rewrite risks silently fixing or
   worsening that bug rather than preserving it. Needs a dedicated pass: `getBoundingClientRect()`-based
   measurement plus a manual ancestor-visibility walk, written and tested carefully enough to reproduce
   the exact same cross-browser behavior (bug included) before it's trusted as a drop-in.
3. **`egw_json.ts`'s `"jquery"` response plugin** - a generic "call any jQuery method by name with
   server-supplied args" bridge (`jQueryObject[res.data.func].apply(jQueryObject, res.data.parms)`) for
   legacy server-emitted JSON response actions. This is a *protocol* feature - arbitrary method dispatch
   by string name - not DOM manipulation with an equivalent; there's no way to keep the "any method, by
   name" capability without keeping jQuery itself, and old PHP code may still emit `"jquery"`-type
   responses that would silently stop working if this were dropped.

## Known bugs/quirks preserved (not fixed) - a separate stream by design

Throughout both phases, "modernization"/"typing" commits are strictly behavior-preserving - verified by
the existing test suite passing **unchanged** (see "Test coverage" below). Any genuine bug found along
the way gets a dedicated `KNOWN BUG`/`KNOWN QUIRK`-prefixed test documenting it (so a future fix has a
regression test ready) rather than being silently fixed inside an unrelated commit. As of 2026-08-09,
these are still open:

**`EgwCore.test.ts`**
- `module()` rebuilds (and leaks) a fresh module instance on every call for a window with no prior
  `egw(app, window)` instance yet, instead of caching it. Once a real instance exists for that window,
  caching works correctly from then on.

**`EgwData.test.ts`**
- The queued-refresh path in `dataRegisterUID()` (its `setTimeout`-deferred call into `dataFetch()`)
  crashes silently inside the timer when `_context` is `null` - `dataFetch()` unconditionally
  dereferences `_context.lastModification` with no null check, unlike `parseServerResponse()` elsewhere
  in the same file which does guard it.
- The 200-item known-uid cap (`KNOWN_UID_LIMIT`) never actually truncates anything -
  `knownUids > KNOWN_UID_LIMIT` compares an array to a number (always false), and even where it would
  apply, `.slice()`'s result is never reassigned back.

**`EgwDebug.test.ts`**
- `debug('log', ...)` never reaches `console.log` - `DEBUGLEVEL` defaults to 3, one below the threshold
  (4) required for the "log" level.
- `debug('navigation', ...)` produces no console output at all - none of `debug()`'s four
  level-specific `if`s match the string `"navigation"`.
- Nothing is ever written to `localStorage`, at any level - `LOCAL_LOG_LEVEL` is hardcoded to 0 ("off").
- `show_log()` throws instead of degrading gracefully (see "Postponed jQuery removal" #1 above).

**`EgwNotification.test.ts`**
- `notification()` constructs a browser `Notification` synchronously without waiting for
  `Notification.requestPermission()`'s callback to resolve.
- If the user then grants permission, a *second*, duplicate `Notification` gets created via the
  function's own recursive retry.

**`EgwMisc.test.ts`** (covers `egw_images.ts`)
- The `navbar` global icon-override map is only consulted when `_app === 'api'` - the exact same
  override entry is silently skipped for every other app
  (`images.global !== undefined && (_name !== 'navbar' || _app === 'api')`).

**`EgwLinks.test.ts`**
- In `mime_open()`, a string path not starting with `/` leaves `data.path` completely unset - the
  `else if (_path[0] !== '/') {}` branch is empty, and the `mime_url` case that would otherwise set it
  is itself guarded by `if (path)`.
- `link()` correctly alerts about a relative URL not starting with a slash, but then builds a malformed
  URL anyway - no separator gets inserted between the app-name prefix and the webserver-URL prefix,
  producing something like `https://example.testinfolog/foo.php`.

**`EgwTail.test.ts`**
- `refresh_log()` escapes `<` in appended log content but leaves `>` completely unescaped.

**`EgwUtils.test.ts`**
- `getCache()`'s "does this key start with `/`" check is a no-op comma expression
  (`(_attr[0] === '/', _attr.indexOf('/', 1) !== -1)` evaluates to just the second half) - any ordinary
  key containing a later `/` gets misinterpreted as a `/regex/flags` literal and throws on the invalid
  flags.
- `getHiddenDimensions()` only restores `display:none` correctly on Chromium (see "Postponed jQuery
  removal" #2 above) - a `this.styles` typo (should be `this.style`) means the correct capture branch
  never runs; Firefox falls through to the `computedStyleMap()` branch and leaves the element visible
  afterward.
- `copyTextToClipboard()` throws instead of falling back to `window` when the Clipboard API is
  available but no `target_element` was passed - the `??` fallback's right-hand side unconditionally
  dereferences `target_element.ownerDocument`.

## Test coverage

- `npm run --silent typecheck` - must show no *new* errors attributable to a touched file (compare
  total error count before/after via `git stash`, since the repo has a large pre-existing baseline
  unrelated to jsapi).
- `npm run jstest -- 'api/js/jsapi/test/*.test.ts'` - 456 tests across 19 files, run on both Firefox and
  Chromium. **Zero test-file changes** is the behavior-preservation signal for a pure modernization
  commit - a test needing an edit means something observable shifted, which should stop the commit and
  get investigated, not "fixed" by editing the test.
  covers exactly the KNOWN BUG/KNOWN QUIRK behavior documented above, unchanged.
- `npx rollup -c` - a real build, periodically re-checked. Expected warnings (pre-existing, unrelated to
  jsapi): a couple of top-level-`this`-rewrite notices, several circular-dependency notices across
  `et2_core_widget.ts`/`egw_action.ts`/`etemplate2.ts`/calendar/kanban, and one `eval`-use warning from
  a bundled PDF.js dependency.

## Where to look next

- Any of the three postponed jQuery items above, if someone wants to pick one up - each needs a
  dedicated, careful pass (not a mechanical swap), see the reasoning in that section.
- Any of the KNOWN BUG items, if actually worth fixing - each already has a regression test ready
  (currently asserting the *buggy* behavior), so a fix is "flip the assertion to the correct behavior,
  make the test pass" plus removing the `KNOWN BUG`/`KNOWN QUIRK` prefix from the test name.
- `egw_inheritance.js`/`jsapi.js`/`app_base.js`/`egw.js` remain a distinct, harder, deliberately-deferred
  tier if a full legacy-code cleanup is ever wanted - not scoped or estimated here.
