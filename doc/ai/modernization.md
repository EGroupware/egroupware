# Incremental Modernization

Unlike the dedicated efforts under `doc/ai/projects/` (a specific goal, with a start and an end),
these are standing rules for **whenever you touch a section of code, anywhere in the repo** - fix these
opportunistically as part of the change you're already making, even if the ticket didn't ask for it.
Don't go out of your way to hunt for these in unrelated files, and don't turn an unrelated bugfix into
a drive-by cleanup of a whole file - just don't leave the exact lines you're editing worse than you
found them, and prefer the modern form for anything new.

## Client-side (TypeScript/JavaScript)

- **No new jQuery.** Don't introduce `jQuery.proxy`, `$(...)`, `.attr()`/`.addClass()`/etc. in new
  code. Existing jQuery usage may remain until it's naturally touched, but once you're editing a
  function that uses it, replace it with plain JS/TS in the same change (arrow functions for
  `this`-binding instead of `var self = this`/`.bind(this)`, native DOM APIs -
  `querySelector`/`classList`/`addEventListener`/`createElement` - instead of jQuery wrappers). See
  `doc/ai/projects/jsapi-modernization.md`'s jQuery-removal table for the common swaps and the few
  cases (jQuery UI dialogs, arbitrary-method-by-name dispatch) that need a real rewrite rather than a
  mechanical one.
- **`egw.request()`, `egw.jsonq()`, and `egw.json(...).sendRequest()` serve different purposes -
  picking the right one is not "always prefer X".**
  - **`egw.request(menuaction, params).then(data => ...)`** - a single, immediate, non-queued request.
    Use it when you need the result promptly, or when the handler needs to read/write session state.
  - **`egw.jsonq(...)`** - queues the call and automatically bundles multiple queued calls into a
    single server-side request a short moment later. **Preferable over `egw.request()`** whenever you
    don't need the result immediately and the call doesn't need session state - the bundling is a real
    efficiency win. The one hard rule: **never** use it for a handler that *writes* session state (e.g.
    `Api\Link::set_data()`) - the server commits the session *before* dispatching queued jobs (so other
    queued requests aren't blocked on the session lock), silently discarding that write. It surfaces
    later as a confusing "not found" error when something tries to read the write back.
  - **`egw.json(...).sendRequest()`** - deprecated for ordinary async use (prefer `egw.request()`/
    `egw.jsonq()` above instead), but still the right tool for a genuinely **synchronous** request
    (`sendRequest(false)`), e.g. an `onbeforeunload`/close handler that can't wait for an async round
    trip. Easy to forget the trailing `.sendRequest()` call entirely, which silently means the request
    is never sent at all.
- **Use `egw_utils.ts`'s exported `deepExtend()`** as the replacement for `jQuery.extend(true, ...)`
  (`import {deepExtend} from './egw_utils'` from within `api/js/jsapi`, or `egw.deepExtend(...)`/
  `this.deepExtend(...)` from app code that already has an `egw` instance). Don't specify `true` as the
  first argument - unlike jQuery's signature, `deepExtend(out, ...sources)` is always a deep merge. For
  a *shallow* clone/merge (`jQuery.extend({}, x)` / `jQuery.extend(target, extra)`), use `{...x}` spread
  or `Object.assign(target, extra)` instead - don't reach for `deepExtend()` there, it does more work
  than needed.

## Server-side (PHP)

- **Do not trigger new PHP undefined-index/undefined-property/undefined-variable warnings.** Guard new
  array/property access with `?? null` (or an explicit default), `isset()`/`array_key_exists()`, or a
  null-safe chain (`$obj?->prop`) rather than relying on PHP's warning-and-continue behavior. When
  you're already working on a function and notice it's tripping one of these warnings on an existing
  line, fix that line too as part of the same change - don't leave it for later.
