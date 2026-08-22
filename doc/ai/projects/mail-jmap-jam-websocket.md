# Mail: jmap-jam WebSocket transport extension

## Goal

Add JMAP-over-WebSocket (RFC 8887) transport to `jmap-jam` (the npm client `mail/js/jmap.ts`'s
`MailJmap` uses for Stalwart), as a **drop-in extension**, `mail/js/jmap-jam-websocket.ts`, so
existing call sites (`client.request(...)`, `client.requestMany(...)`, `client.api.Entity.op(...)`)
transparently get routed over a persistent WebSocket when the server advertises support, falling
back to jmap-jam's normal HTTP transport otherwise. `node_modules/jmap-jam` itself stays untouched,
so this could plausibly be upstreamed later. **Wiring this into `MailJmap`/mail-app is an explicit
later step** - this file has no mail-app dependency and isn't used anywhere yet.

Note: this is unrelated to EGroupware's existing *server-side* JMAP push (Stalwart's
`api/jmapPush.php` subscription, the Dovecot mailbox-metadata push-token mechanism for the local
shim, both fronted by `mail_ui::ajax_enablePush()`) - that stays exactly as-is. `MailJmap` doesn't
use jmap-jam's `connectEventSource()` today either. A WS-native push channel is a separate,
additive, future capability - see "Push" below.

## RFC 8887 summary (JMAP over WebSocket)

- Subprotocol name: `"jmap"`.
- Discovery: session's `capabilities["urn:ietf:params:jmap:websocket"]` =
  `{ url: "wss://...", supportsPush: boolean }`. Capability absent -> no WS support for that
  session/account, ever - stay on HTTP.
- Client -> server frames, tagged by `@type`:
  - `Request`: `{ "@type": "Request", id?, using, methodCalls, createdIds? }` - same shape as the
    HTTP JMAP Request body plus `@type`/`id`. `id` is the client-generated correlation token; a real
    client must always send one, since multiple requests can be in flight concurrently and responses
    may arrive out of order.
  - `WebSocketPushEnable`: `{ "@type": "WebSocketPushEnable", dataTypes: string[] | null, pushState?: string }`.
  - `WebSocketPushDisable`: `{ "@type": "WebSocketPushDisable" }`.
- Server -> client frames:
  - `Response`: `{ "@type": "Response", requestId?, methodResponses, sessionState, createdIds? }` -
    `requestId` echoes the client's `id`.
  - `RequestError`: `{ "@type": "RequestError", requestId?, type, status, detail, ... }` - a
    ProblemDetails for a request-level failure (distinct from a per-method-call `"error"` Invocation,
    which still arrives inside a normal `Response`). No `requestId` -> unparseable/undecodable frame,
    can't be correlated to any pending promise.
  - `StateChange`: `{ "@type": "StateChange", changed: { [accountId]: { [DataType]: state } }, pushState? }` -
    unsolicited, only sent after a `WebSocketPushEnable`.
- One socket carries many concurrent requests, correlated purely by `id`/`requestId`; ordering is not
  guaranteed. `maxConcurrentRequests` still bounds how many should be outstanding at once.
- Auth: handshake is authenticated the same way as any HTTP request (RFC 7235 credentials on the
  initiating HTTP request) - **but browsers' `WebSocket` constructor cannot set an `Authorization`
  header**, so this needs a concrete answer for how Stalwart expects it (token-in-URL, cookie, other)
  before `#connect()` can be finalized. Open question, see below.
- No auto-reconnect/backoff guidance in the RFC - that's client policy, our call.

## Why this can't be a thin override of jmap-jam

`JamClient.request()`/`requestMany()` (`node_modules/jmap-jam/src/client.ts`) call the bare global
`fetch()` directly inside the method body - there is no injectable transport seam (no `this.fetch`,
no protected hook). A subclass can't redirect just the network call; monkey-patching `globalThis.fetch`
per-call is concurrency-unsafe (two calls in flight would race over the same global patch) and is
explicitly out.

The small amount of glue logic those methods run *before* calling fetch - capability inference
(`getCapabilitiesForMethodCalls`), per-invocation error/result unwrapping (`getErrorFromInvocation`,
`getResultsForMethodCalls`), and for `requestMany()`, the Proxy-based draft/result-reference builder
(`buildRequestsFromDrafts`/`InvocationDraft`) - lives in `capabilities.ts`/`helpers.ts`/
`request-drafts.ts`, none of which are re-exported from the package's public entry point
(`src/index.ts` only re-exports `client.ts` + `contracts.ts`). The published package's `exports`
field also only exposes `"./dist/index.js"` - no deep-import path is a supported (or reliably
resolvable, under strict `exports` semantics + a bundler that honors it) integration point, and
reaching into `dist/` internals would make every jmap-jam version bump a silent breakage risk.

**Consequence**: `request()` and `requestMany()` have to be fully overridden in the subclass, with
the pre-fetch glue logic reimplemented rather than reused. This is the one place the extension can't
be pure composition - but it's a small, stable amount of logic (~120 lines total, direct translations
of RFC 8620 SS3.7 mechanics, not a fork of jmap-jam's actual JMAP semantics). Worth proposing to
jmap-jam upstream alongside (or instead of) this extension: export those three helpers from the
public entry so a subclass never needs to duplicate them.

Everything else is inherited unchanged and needs no work: `session` loading/caching, the
`capabilities` map, `getPrimaryAccount()`, `uploadBlob()`/`downloadBlob()` (blob transport stays HTTP
always - RFC 8887 doesn't cover blob upload/download), `connectEventSource()`, and the `api` Proxy
fluent surface (`jam.api.Email.get(...)` etc.) - it already calls `this.request(...)` internally, so
it benefits from the override automatically.

## Design

### Class shape

```ts
export class JamWebSocketClient extends JamClient {
  constructor(config: ClientConfig & { webSocketUrl?: string; pushDataTypes?: "*" | Entity[] | null });
  onPush(callback: (stateChange: StateChangePayload) => void): () => void;  // returns unsubscribe
  get transport(): "websocket" | "http";  // observability/debugging
}
```

- Constructor still calls `super(config)`, then kicks off connecting in the background once
  `this.session` resolves and the session's capabilities include
  `urn:ietf:params:jmap:websocket`. Never blocks the constructor or the first request.
- `config.webSocketUrl` is an optional override, mainly so the local IMAP shim
  (`mail/jmap.php`/`JmapShim.php`) could get its own `ws://` endpoint later - not attempted in this
  file, just leaving the seam open.

### Connection lifecycle

- Open `new WebSocket(url, "jmap")`, wait for `open`. On failure/close, mark not-ready and retry with
  capped backoff (e.g. 1s / 5s / 30s, then give up and log once - not per-request spam); HTTP is used
  for everything meanwhile.
- Each pending request tracked in a `Map<id, {resolve, reject}>`, `id` generated via a per-connection
  monotonic counter (matching jmap-jam's own `"r1"`-style single-invocation id).
- Incoming frames dispatched by `@type`:
  - `Response` -> resolve the matching pending entry with the same `[Data, Meta]` tuple shape
    jmap-jam's `request()` normally resolves with, so the override is a true drop-in.
  - `RequestError` -> reject the matching pending entry with the ProblemDetails (matches jmap-jam's
    existing throw-on-4xx/5xx behavior for HTTP).
  - `StateChange` -> fan out to registered `onPush()` callbacks.
  - Anything with a missing/unmatched `requestId`, or an undecodable frame -> logged and dropped
    (nothing safe to resolve/reject).
- On socket close/error while requests are pending: **reject the in-flight ones** (recommended
  default - see open question 2) rather than silently re-issuing them over HTTP, since a lost
  response for a non-idempotent call (e.g. `Email/set` create) can't be safely guessed at. New calls
  made while reconnecting go over HTTP.

### `request()` override

1. Compute `using` via a local reimplementation of `getCapabilitiesForMethodCalls` (same
   entity-prefix-regex logic against the inherited, public `this.capabilities` map).
2. If WS is ready: send `{ "@type": "Request", id, using, methodCalls: [[method, args, "r1"]], createdIds }`,
   await the matching `Response`/`RequestError`, apply the same per-invocation error check jmap-jam
   does (`getErrorFromInvocation` reimplementation), return `[data, meta]`.
   - `Meta.response` is typed as a real Fetch `Response`, which doesn't exist for a WS frame.
     Confirmed via grep that `mail/js/jmap.ts` never reads `meta.response` today, so this is a
     low-stakes typing gap - will document the deviation rather than manufacture a fake `Response`.
3. Otherwise: `return super.request(invocation, options)` unchanged.

### `requestMany()` override

Same pattern, but needs a local reimplementation of the draft/result-reference builder
(`InvocationDraft`-equivalent) to turn the caller's `draftsFn` into concrete `methodCalls` - this is
the single biggest chunk of new code (~100 lines), but self-contained and independently testable
(pure data transform, no transport involved). This matters more than it might look: a grep of
`mail/js/jmap.ts` shows `requestMany()` (not `request()`) is `MailJmap`'s dominant call pattern
already - a WS transport that only covered `request()` would carry almost none of the real future
mail-app traffic.

### Push (`onPush`)

- `client.onPush(cb)` registers a callback; first registration sends `WebSocketPushEnable`
  (`dataTypes: "*"` or `config.pushDataTypes`) once WS is ready; unregistering the last callback sends
  `WebSocketPushDisable`.
- Purely additive and forward-looking - unrelated to and doesn't replace the existing server-side
  push plumbing (see "Goal" above).

### Detection / capability gate

`#supportsWebSocket()`: session capability present AND `typeof WebSocket !== "undefined"` (guards
non-browser/test environments, same pattern already used elsewhere in the codebase for
environment-conditional browser APIs).

## Phasing

1. **Phase 1** (standalone, unit-testable, no mail-app wiring): `JamWebSocketClient` with `request()`
   WS support + HTTP fallback + reconnect; `requestMany()` initially just delegates to
   `super.requestMany(...)` (always HTTP) - ships something real and testable quickly.
2. **Phase 2**: `requestMany()` WS support (the draft/ref-resolution reimplementation).
3. **Phase 3**: `onPush()` push channel.
4. **Phase 4** (explicitly later, per your instruction): wire into `mail/js/jmap.ts`'s `MailJmap`
   (swap `new JamClient(...)` for `new JamWebSocketClient(...)`); decide whether/how the local shim
   gets its own WS endpoint (currently HTTP-only - real work in `JmapShim.php`, out of scope here).

## Testing

`mail/js/test/*.test.ts` pattern (`npx web-test-runner mail/js/test/*.test.ts --node-resolve`), with
a small hand-rolled fake `WebSocket` (the protocol surface is small enough not to need a dependency)
driving: capability-detected-and-connects, request/response correlation including out-of-order
responses, `RequestError` rejection, fallback-to-HTTP when the capability is absent, fallback-to-HTTP
when the WS fails to connect, reconnect-then-resume, push callback fan-out.

## Decisions

1. **Auth on the WS handshake** - unknown how Stalwart expects it; no existing reference anywhere in
   this repo (`grep -rin websocket mail/ api/src/Mail/` only turns up EGroupware's unrelated generic
   app-push websocket in `mail/js/app.ts`). Deferred to live investigation against `acc_id=1` during
   Phase 1 implementation - `#connect()` will need a pluggable "how do I attach credentials to the WS
   URL" seam (most likely a token-in-URL query param, since that's what jmap-jam already does for
   `bearerToken` conceptually) so the answer can be swapped in without a redesign.
2. **Fallback-on-disconnect semantics** - confirmed: reject in-flight requests on unexpected close
   (the safe default). New requests made while reconnecting go over HTTP.
3. Proceeding straight to Phase 1 implementation.

## Implementation notes (found while building Phase 1)

- `jmap-rfc-types` (the package the `Invocation`/`ProblemDetails` RFC 8620 shapes actually come from)
  ships `.ts` sources with no compiled `.d.ts` and a package.json `exports` map pointing straight at
  `.ts` files - this repo's tsconfig (`moduleResolution: "node"`, classic resolution) can't resolve
  that at all. Confirmed this is pre-existing and unrelated to this file: `jmap-jam`'s own
  `dist/index.d.ts` already fails to resolve `jmap-rfc-types` the same way under this repo's
  `npm run typecheck` (204 pre-existing errors from `type-fest`/`jmap-rfc-types`, present already just
  from `mail/js/jmap.ts`'s existing `import JamClient from "jmap-jam"`, confirmed via `git stash`).
  Not something to fix as part of this task. Consequence: `Invocation`/`ProblemDetails` are declared
  locally in `jmap-jam-websocket.ts` (tiny, stable RFC 8620 shapes) instead of imported - everything
  else needed (`ClientConfig`, `GetArgs`, `GetResponseData`, `LocalInvocation`, `Meta`, `Methods`,
  `RequestOptions`) comes from jmap-jam's own public, correctly-resolving `dist/index.d.ts`.
- `meta.response` (typed as a non-optional Fetch `Response` in jmap-jam) is `undefined` for a
  WebSocket-sourced result - confirmed via grep that nothing in `mail/js/jmap.ts` reads it today.

### Phase 2 (`requestMany()` over WS)

- Reimplemented jmap-jam's own (unexported) `request-drafts.ts`: a local `Draft` class (the
  `InvocationDraft` equivalent), a `DraftsProxy` type built from jmap-jam's *public* `Requests` type
  (for entity/operation name autocomplete), and `buildRequestsFromDrafts()` (the
  `$ref()`/`ResultReference` resolution logic) - same restriction as jmap-jam's own version: a ref can
  only point at a call declared earlier in the same drafts object.
- `requestMany()`'s all-or-nothing error handling is reproduced exactly: if *any* method call in the
  batch response is an `"error"` pseudo-invocation, the whole call rejects with the array of
  `ProblemDetails` (not a partial result) - matches jmap-jam's HTTP behavior so callers don't need to
  branch on which transport ran.
- **Typing tradeoff, found while wiring this up**: overriding `requestMany()` with a precisely-typed
  return (mirroring jmap-jam's own conditional-mapped-type inference over the caller's drafts) is a
  TypeScript override-compatibility dead end here - the base method's return type is a mapped type
  over an *unconstrained* generic parameter (`Returning`), and no concrete replacement type (even one
  built from `any`) satisfies that check once instantiated in a subclass override. Landed on
  `Promise<any>` for `requestMany()`'s return type specifically (documented inline) - callers still
  get normal `const [{x}] = await client.requestMany(...)` destructuring, they just lose the
  compile-time shape check on the result. The `draftsFn` *parameter* type stays properly typed
  (`DraftsProxy`/`DraftHandle`, both exported) since that direction of the override check isn't
  affected the same way - just needed a plain structural `DraftHandle = {$ref: (path) => unknown}`
  type instead of exposing the `Draft` class itself, so it doesn't collide with jmap-jam's
  differently-branded `InvocationDraft` class during the compatibility check.
- HTTP fallback for `requestMany()` just calls `super.requestMany(draftsFn, options)` with the
  caller's original `draftsFn`, unmodified (cast away the static type mismatch with `as any`) - jmap-jam's
  base implementation then drives that same `draftsFn` with *its own* proxy/`InvocationDraft`, so
  there's no cross-contamination between the two implementations' internals; they just both happen to
  implement the same `{entity}.{operation}(args)` + `.$ref()` duck type.

### Phase 3 (`onPush()`)

- `onPush(callback)` registers a callback and, on first registration, sends `WebSocketPushEnable`
  (`dataTypes: null` for the default `pushDataTypes: "*"`, or the caller's explicit list); returns an
  unsubscribe function that sends `WebSocketPushDisable` once the last callback unsubscribes. A second
  registration while already enabled is a no-op on the wire (checked via `#pushEnabled`).
- `StateChange` frames are dispatched to every registered callback (stripped of the transport-only
  `@type`, matching the same "callers shouldn't have to care which transport ran" principle used for
  `RequestError` in Phase 1) and update `#lastPushState`.
- **Reconnect carries `pushState` forward**: `#handleClose()` resets `#pushEnabled = false`, and the
  next connection's "open" handler re-sends `WebSocketPushEnable` (with `#lastPushState`) if there are
  still active `onPush()` callbacks - per RFC 8887, a cached `pushState` on re-enable makes the server
  immediately deliver anything that changed while disconnected, rather than silently missing it.
- Confirmed no HTTP-transport equivalent exists or is planned - `onPush()` callbacks simply never fire
  while `transport === "http"` (no capability, not yet connected, or reconnecting). This is
  purely additive: EGroupware's existing server-side JMAP push and `mail/js/app.ts`'s unrelated
  generic app-level push websocket are both untouched (confirmed via `grep -rin websocket mail/
  api/src/Mail/` again while wiring this up - no collision).

## Test coverage

`mail/js/test/JamWebSocketClient.test.ts` (`npx web-test-runner mail/js/test/*.test.ts
--node-resolve`, passes on Chromium + Firefox, no regressions in the other mail JS tests - 110 total)
- a hand-rolled `FakeWebSocket` (readyState/addEventListener/send/close plus
simulateOpen()/simulateMessage()/simulateClose() test helpers) drives:

- `request()`: HTTP fallback when the session lacks the capability, WebSocket connect +
  request/response id correlation, `RequestError` rejection with a clean `ProblemDetails` shape (no
  leaked `@type`/`requestId`), reject-in-flight-without-HTTP-retry on an unexpected close.
- `requestMany()`: batched calls sent/resolved keyed by the caller's own ids, `$ref()` resolution into
  a real JMAP result reference (asserting the exact wire shape, including the `#`-prefixed key and
  `resultOf`), all-or-nothing rejection when any one call in the batch errors, and HTTP-fallback
  delegation when the capability is absent.
- `onPush()`: enable-on-first-registration/disable-on-last-unsubscribe (with a second concurrent
  registration verified as a wire no-op), `StateChange` fan-out to every callback, and - using
  `sinon.useFakeTimers()`/`clock.tickAsync()` to drive the real reconnect-backoff timer deterministically -
  push re-enable with the carried-over `pushState` after a simulated disconnect/reconnect.

Reconnect-backoff timing itself isn't otherwise asserted beyond that one case (the delays are fixed
constants, not worth more real-time-dependent tests here).

## WS-handshake auth against Stalwart - resolved (blocked upstream)

Investigated directly against Stalwart's own source (`stalwartlabs/stalwart`, `main` branch, checked
2026-08-21), not guessed at:

- The `GET /jmap/ws` upgrade route authenticates via the exact same code path as every other JMAP
  HTTP endpoint: `crates/http/src/request.rs` calls `self.authenticate_headers(&req, &session)`
  *before* handing off to `upgrade_websocket_connection()` - no WebSocket-specific auth branch exists.
- `authenticate_headers()`/`HttpHeaders::authorization()`
  (`crates/http/src/auth/authenticate.rs`) reads **only** the standard `Authorization` HTTP header
  (`Basic` or `Bearer`) off the request - verbatim: `self.headers().get(header::AUTHORIZATION)`. No
  query-string, cookie, or `Sec-WebSocket-Protocol` fallback exists anywhere in that file.
- Our own `access_token` (`Mail\Imap\Stalwart::accessToken()`, `api/src/Mail/Imap/Stalwart.php`) is a
  real Stalwart-issued OAuth2 Bearer token (RFC 6749 password grant against Stalwart's own
  `/auth/token`), already sent as a plain `Authorization: Bearer` header for ordinary HTTP JMAP
  requests - so the token itself isn't the problem, only how to present it during a WS handshake.

**This is a genuine, currently-unresolved gap in Stalwart itself, not something fixable from our
client code alone**: browsers' native `WebSocket` constructor cannot set an `Authorization` header (or
any custom header) on the handshake request, and Stalwart has no alternate credential path for a WS
upgrade. Confirmed this is a known, actively-discussed gap upstream, not just our own observation:

- [stalwartlabs/stalwart#2677](https://github.com/stalwartlabs/stalwart/issues/2677) - a feature
  request proposing exactly this problem/fix (short-lived "ticket" auth: `POST /jmap/ws/ticket` with a
  normal `Authorization` header returns a ticket, then `GET /jmap/ws?ticket=...` needs no header - the
  same pattern Apache James JMAP already uses). Filed 2026-01-19, closed same day with `stateReason:
  COMPLETED` after a maintainer redirected it to Discussions per repo policy (Issues are reserved for
  maintainer-approved work).
- [stalwartlabs/stalwart#2678](https://github.com/stalwartlabs/stalwart/pull/2678) - a full
  implementation of the above (`WsTicket` grant type, `oauth.expiry.ws-ticket` config,
  `supportsTicketAuth`/`ticketUrl` session-capability advertisement) - closed unmerged the same day,
  redirected to Discussions rather than reviewed.
- [stalwartlabs/stalwart#2680 (Discussion)](https://github.com/stalwartlabs/stalwart/discussions/2680) -
  the same proposal restated for Discussion, linking a working fork
  ([HMB-research/stalwart](https://github.com/HMB-research/stalwart)) that has it implemented. Zero
  maintainer replies as of 2026-08-21 (7 months later) - no consensus, no timeline.
- Confirmed nothing has landed on `main` since: `WsTicket`/`supportsTicketAuth`/`ws/ticket` all return
  zero hits in the current source, and `CHANGELOG.md`'s only WebSocket entry since is an unrelated
  case-insensitivity fix during upgrade.

**Decision (2026-08-21)**: option (b) - an nginx-side workaround, since ralf's infra already fronts
Stalwart with nginx. Chose the simpler of the two designs discussed (a real access-token query
param, not a short-lived single-use ticket) since TLS + disabling `access_log` for exactly this one
path closes the two exposure vectors that mattered (proxy logs, and - corrected mid-discussion - a
`new WebSocket(url)` call is a JS API call, not a navigation, so it was never going to show up in
browser history the way I'd first implied). Residual exposure (this browser tab's own DevTools
Network panel, or any other logging middlebox further upstream) was judged acceptable.

**nginx changes** (`/Users/ralf/dev/nginx.conf`, ralf's dev box - both his own vhost and the ones on
hosting need the same change before this can be tested end-to-end):
- A `map $arg_access_token $stalwart_ws_auth { default $http_authorization; "~.+" "Bearer
  $arg_access_token"; }` at the http level - falls back to whatever real `Authorization` header the
  client sent directly when there's no `access_token` param, so it's safe to reference
  unconditionally.
- A new `location ^~ /jmap/ws { ...; proxy_set_header Authorization $stalwart_ws_auth; access_log
  off; ...}` added *before* the existing broad Stalwart location/passthrough in both the
  `boulder.egroupware.org`/`office.egroupware.org` server block and the dedicated
  `boulder-stalwart.egroupware.org` passthrough server. Deliberately scoped to exactly `/jmap/ws`
  (`^~` stops nginx from also checking the broader regex location) - doing this on the broad Stalwart
  location instead would have overridden `Authorization` on every ordinary JMAP HTTP request too,
  silently breaking them (they already send a real header directly, not a query param).
- Applied and reloaded on ralf's dev box; the equivalent hosting-side change is still pending (his
  own words: "change my own nginx proxy and the ones in our hosting first, so we can test").

**Client-side wiring** (`mail/js/jmap.ts`): `JamWebSocketClient` (not `JamClient`) is now constructed
in `ensureToken()`'s token-refresh callback, with a generic `transformWebSocketUrl` hook (added to
`JamWebSocketClientConfig` - see below) supplying `?access_token=<token.access_token>` on the
discovered `wss://` URL. The outgoing client's `.close()` is called before replacing it on each
token refresh (~hourly), rather than leaving its WebSocket to be garbage-collected. `transformWebSocketUrl`
itself carries **zero** EGroupware/Stalwart-specific knowledge in `jmap-jam-websocket.ts` - it's a
plain `(url: string) => string` config hook, generic on purpose (RFC 8887 doesn't define how a
handshake gets authenticated in a browser; that's entirely a deployment convention, not something
this file should have an opinion about).

Still pending before this can be tested live: the hosting-side nginx change (ralf's).

## Two real Babel/Rollup miscompilations found while wiring this up

This repo's actual build (`rollup.config.js`'s custom transform: `@babel/preset-typescript` +
`@babel/preset-env` + `@babel/plugin-transform-class-properties` `{loose: false}` +
`@babel/plugin-proposal-decorators`) has **two distinct, real miscompilation bugs** for an async
class method combined with private class elements - neither is a `tsc` problem (both typecheck
clean), and neither is even a `babel.transform()`-throws problem (both return success) - the
*output* of the successful transform is itself broken, in two different ways depending on the exact
shape of the method:

1. **An async method that both calls `super.someMethod(...)` *and* references any
   `#privateField`/`#privateMethod` of its own class** (regardless of whether those two things
   happen in the same branch): the async-to-generator transform needs to hoist `super.foo` into a
   `_superprop_getRequest = () => super.request` arrow binding *outside* the
   `_asyncToGenerator(function* () {...})` wrapper (the only place `super` remains legal, since it
   can't cross a non-arrow function boundary) - that hoisting is correct with no private fields
   present, but with `@babel/plugin-transform-class-properties` also processing the same class, the
   binding ends up emitted *inside* the generator instead, which is invalid JS. This one **fails the
   build**: Rollup's own acorn-based module parser rejects the output outright, with the
   misleading-sounds-like-a-source-bug message `Error: 'super' keyword outside a method`, pointing
   (via sourcemap) at the method's opening brace.
2. **An *ordinary* (non-`#`) async method that references a true `#privateField`** (no `super`
   involved at all this time): Babel captures `_this = this` *inside* the generator function instead
   of hoisting it outside, and that generator is then invoked bare (`_asyncToGenerator(function*(){
   ...})()`, no explicit receiver) - so `this` is `undefined` inside it at runtime (strict mode), and
   every `this.#field` access throws. This one **does not fail the build at all** - `tsc`,
   `babel.transform()`, and even Rollup's acorn re-parse all accept the output as valid JavaScript;
   it only fails live, when the code actually runs. Found this way: after fixing bug 1 by splitting
   `request()`/`requestMany()` into a `super`-calling dispatcher plus a plain-`private` (TS-only, not
   `#`) helper doing the real work, the *build* went green, but a live browser test against real
   Stalwart (`acc_id=1`) threw `TypeError: Private element is not present on this object` the moment
   `request()` actually exercised the WebSocket path - the helper method was exactly this pattern:
   ordinary method, `async`, touching `this.#nextId`/`this.#pending`.

Both isolated with minimal repros, confirmed against the real class shape by actually *executing*
compiled output in a `vm.Script` (not just parsing it) to verify runtime `this` binding, not only
syntax. True `#`-private *methods* (as opposed to ordinary methods that merely *reference* private
fields) turned out to use a third, different, unaffected compilation pattern - Babel threads `this`
through explicit `.call(this, ...)`/`.apply(this, arguments)` at every hop for those, which sidesteps
both bugs. That's the basis of the fix.

**Fix applied, entirely local to `jmap-jam-websocket.ts`, no rollup/babel config touched**:
`request()` and `requestMany()` are now plain, deliberately **non-`async`** methods that just return
a `Promise` from one of two branches (`super.xxx(...)`, or the private helper's call) - since
they're not `async`, Babel never wraps them in a generator at all, which sidesteps bug 1
categorically (no generator scope exists for `super`/`this` capture to go wrong in). The actual
WebSocket-transport work moved into true `#`-private methods (`#requestOverWebSocket()` /
`#requestManyOverWebSocket()`, real hash-privacy, not TS-only) - being genuinely `#`-private, calling
them compiles through the `.call`/`.apply`-threaded pattern that sidesteps bug 2. Verified four ways:
`babel.transform()` + `acorn.parse()` of the output (both clean), a full `npx rollup -c` build
(succeeds, no errors, only pre-existing unrelated warnings), and - the only check that actually
proves runtime correctness - a live browser test against real Stalwart (see Phase 4 below).

## Status

**Phase 1, 2, and 3 done**: `mail/js/jmap-jam-websocket.ts` - `request()` and `requestMany()` both run
over WebSocket with transparent HTTP fallback (capability absent, not yet connected, or disconnected)
and capped-backoff reconnect; in-flight requests reject (never silently retried) on an unexpected
close; `onPush()` exposes RFC 8887's push channel with reconnect-safe `pushState` carry-over.

**Phase 4 done and live-verified (2026-08-21)**: `mail/js/jmap.ts` constructs `JamWebSocketClient`
with a `transformWebSocketUrl` hook appending `?access_token=`; nginx (ralf's dev box, matching
`dev/nginx.conf`'s `boulder.egroupware.org`/`boulder-stalwart.egroupware.org` blocks) converts that
back into a real `Authorization` header for `/jmap/ws` only. Verified end-to-end via claude-in-chrome
against real Stalwart (`acc_id=1` on `boulder.egroupware.org`):
- `window.app._jmap.clients['1'].transport === "websocket"` - the connection actually opens.
- `client.request(["Core/echo", {...}])` round-trips correctly over it.
- `client.requestMany((t) => {...})` with a `$ref()`-chained `Mailbox/query` → `Mailbox/get` also
  round-trips correctly (returned 6 real mailboxes, "Inbox" first) - the heavier, more
  representative-of-real-usage code path.
- `acc_id=42` (the local IMAP shim, no websocket capability) correctly stays on `transport ===
  "http"`, confirming the fallback side of "transparent" also holds up live, not just in unit tests.

Two real Babel/Rollup miscompilations (see above) were found and fixed along the way, entirely within
`jmap-jam-websocket.ts` - no shared build config was touched. The equivalent hosting-side
(`stalwart.egroupware.org` on the k8s farm, fronted by nginx on `farmA`) nginx change was also applied
and reloaded, though no boulder account currently routes through it, so it hasn't been live-tested
itself - the dev-box test above is the one that actually exercised the full JamWebSocketClient code
path against a real Stalwart server.

**Not yet done**: the graceful-fallback-if-the-nginx-piece-is-missing behavior, while designed in from
Phase 1 and exercised by the `acc_id=42`/no-capability test above, has not specifically been tested
by *removing* the nginx `/jmap/ws` block on an account that *does* have the capability (i.e.
simulating "capability present, but the auth workaround isn't deployed yet") - worth doing before
recommending this configuration to anyone else. The hosting-side (`stalwart.egroupware.org`/farmA)
nginx change is applied and reloaded but not yet live-tested itself, since no boulder account
currently routes through it. `onPush()` adoption (Phase 5, below) is done, but only actually exercises
live on an instance where `Api\Json\Push::onlyFallback()` is true - not the case on `boulder`, which
has a working push-server, so Phase 5's live testing was necessarily partial (see its own section).

## Phase 5: adopting `onPush()` for real accounts (done, 2026-08-21)

Resolved the "server can't know in advance whether a given browser's WebSocket will succeed" concern
(see the now-superseded open question this section replaces) with ralf's proposed design: use
`Api\Json\Push::onlyFallback()` as the gate. It returns `true` specifically when no real push-server
backend is registered (the `push-backends` hook comes back empty, e.g. EPL's real backend isn't
installed) and the code falls through to the bare `notifications_push` class - i.e. "no working push
for this instance already." Since client-side WS push is only ever *attempted* in that case, there's
no regression path: if a given browser's WebSocket also fails, the account had no working push before
either way.

### Server-side

- `ProfileHandler::jmapBootstrap()` (`mail/src/Ui/ProfileHandler.php`) adds
  `$bootstrap['enableWsPush'] = Api\Json\Push::onlyFallback();` to the bootstrap payload.
- No change to `mail_ui::ajax_enablePush()`/`Api\Mail\Imap\Jmap::enablePush()`/`pushCallback()` -
  the classic path is completely untouched; it just doesn't get called when `enableWsPush` is true.

### Client-side (`mail/js/jmap.ts`)

- `JmapToken.enableWsPush` carries the flag; `enablePushOnce()` branches on it - `true` calls the new
  `enableWsPush()` instead of `mail.mail_ui.ajax_enablePush`, reusing the *same* `pushEnabled` guard
  map (once per profile per token lifetime) for both paths.
- `enableWsPush()` seeds a baseline `Email`/`Mailbox` state per JMAP accountId via one cheap
  `Email/get`/`Mailbox/get` call with `ids: []` (RFC 8620: a `Foo/get` response's `state` reflects the
  server's current state for that type regardless of which/how many ids were requested - free
  baseline, no data transferred), *then* registers `client.onPush(...)`. `JamWebSocketClient` is
  constructed with `pushDataTypes: ['Email', 'Mailbox']`, matching
  `Api\Mail\Imap\Jmap::SUBSCRIBTION_TYPES`'s scope exactly.
- `handleWsPush()`/`processWsPushStates()` diff each `StateChange`'s new per-dataType state against
  the cached baseline (updating the baseline unconditionally either way) and only do real work when
  something actually changed since last time.
- `buildWsPushPayload()` is the client-side equivalent of `Api\Mail\Imap\Jmap::pushCallback()`'s
  `"StateChange"` case, deliberately mirroring `Api\Mail\Jmap::getChanges()` property-for-property:
  one `requestMany()` batch chaining `{Email,Mailbox}/changes` into `{Email,Mailbox}/get` via
  `$ref()` (`/created`, `/updated`, `/destroyed`) - exactly the pattern Phase 2 was built for.
  `buildEmailPush()`/`buildMailboxPush()` turn each resulting item into the same `{app, id, type,
  acl}` envelope shape `pushCallback()` builds, then calls **the existing, already-tested
  `MailApp.push()`** with it (`this.app.push(pushData)`) - reusing the real UI-refresh/notification
  code instead of duplicating it.
- **Deliberately uses this client's own native JMAP row-id shape**
  (`accountId::profileID::folderId::emailId`, see `messageReference()`) for `pushData.id`, **not**
  the classic `accountId::profileID::base64(folder)::uid` shape `pushCallback()` builds server-side
  via `emailId2uid()`. This matters: `nm.refresh(pushData.id, ...)` looks the row up by this id, and
  Stalwart rows are cached under the native JMAP shape (see "Row-id scheme" in
  [[project-mail-jmap-modernization]]) - the classic shape would silently fail to match any row.
  Given `pushCallback()` produces the classic shape unconditionally even for Stalwart accounts, this
  suggests the *existing* server-side push path's row-refresh has likely been silently non-functional
  for real JMAP accounts since the row-id modernization landed - a pre-existing gap, not something
  fixed here (out of scope, and only reachable when `enableWsPush` is false, i.e. a working
  push-server already exists).
- `folderId2path()` is the client-side equivalent of `Api\Mail\Jmap::folderId2path()`, but
  **one `Mailbox/get` per ancestor level** rather than that PHP method's batched 4-level `$ref()`
  chain: `$ref()` resolves a JSON pointer to a single scalar (a parent's own id) into the *value* of
  the next call's `ids` argument, but `Mailbox/get`'s `ids` must be an array - there's no
  JSON-pointer-only way to wrap a resolved scalar back into a one-element array. Found via a real bug
  in an earlier draft of this method (attempted the batched approach, a bare-string `ids` value broke
  against a real server) - simplified rather than chasing a more complex batching scheme, since this
  only ever runs on push events with aggressive per-folderId caching, not a hot path. Live-verified
  against real Stalwart's flat 6-mailbox test account (top-level resolution, incl. the
  `Inbox`→`INBOX` case-normalization); multi-level chaining covered by unit tests only, since that
  account has no nested folders.
- **`MailApp.push()` fix** (`mail/js/app.ts`, one line): its folder-tree-badge derivation computed
  `folder` by re-deriving it from `pushData.id`'s own third `::`-segment via `atob(...)`, assuming
  the classic `base64(path)` shape unconditionally - silently produces garbage for the native JMAP
  row-id shape (which puts a raw, non-base64 JMAP folderId there instead). Fixed to just use
  `pushData.acl.folder` directly - already computed, already a real path, already relied on a few
  lines later for the Trash/Junk/Drafts/Sent notification-suppression check - removing a
  shape-dependent re-derivation of a value already available. Backend-agnostic fix: benefits the
  classic server-side push path too, not just this new one.

### Test coverage

`mail/js/test/MailJmapWsPush.test.ts` - `folderId2path()` (root-level, multi-level chain, "folder
gone" → null, per-profile caching) using a real `JamWebSocketClient` driven by a hand-rolled fake
JMAP server (`FakeWebSocket` + a `respondToMailboxGet()` helper), and `processWsPushStates()`
(baseline-seeding on first event, no-op on unchanged state, fetch-and-push on a real change, asserted
against the exact expected `pushData` shape). `npx web-test-runner mail/js/test/*.test.ts
--node-resolve` - 117 total, all passing, no regressions.

### Known limitation, inherited from the classic path

Mailbox-type pushes never set `acl.event`, so they fall through to `push()`'s `default:` branch
calling `nm.refresh(pushData.id, ...)` on an id that was never a NextMatch row to begin with (mailbox
folders aren't grid rows) - a harmless no-op, but not a meaningful refresh either. `pushCallback()`
has this exact same shape server-side, so this is a faithfully-ported pre-existing characteristic,
not a new gap - not fixed here.

## Phase 6: heartbeat / dead-connection detection (done, 2026-08-22)

Prompted by ralf hitting a real "zombie" WebSocket live while debugging something unrelated: the
connection had stopped working (no requests completing, no pushes arriving) but `readyState` kept
reporting `OPEN` - a proxy or NAT device between the browser and Stalwart had silently dropped the
underlying TCP connection without either side ever seeing a close/error event, which is a known,
common failure mode for long-lived idle WebSocket connections through middleboxes (exactly the shape
of infrastructure this deployment already has, given the nginx workaround in Phase 1). Nothing in
this transport could detect that before this - it would just hang forever on the next request sent
over it (`#send()`'s promise never resolves), never triggering `#handleClose()`'s reconnect logic
since no close/error event was ever going to fire.

Browsers give JS no access to the WebSocket protocol's own ping/pong control frames, so the fix is an
application-level heartbeat instead: `JamWebSocketClient` now sends a `Core/echo` request over an
otherwise-idle connection every `heartbeatIntervalMs` (default 30s, configurable, `0` disables it
entirely), skipping the probe whenever any other frame (a real request's response, a push
`StateChange`, or a previous heartbeat's own reply) already arrived more recently than a full
interval - `#lastActivity` is updated unconditionally at the top of `#handleMessage()`, so a busy
connection never gets redundant traffic. `Core/echo` was chosen deliberately: it's part of the
mandatory JMAP Core capability (RFC 8620 §3.3, "MUST support"), so every compliant server already has
to answer it - no Stalwart-specific or otherwise non-standard ping mechanism needed, and it reuses
the exact same `#pending`/`#handleMessage()` request-tracking `#send()` already provides, rather than
inventing a parallel correlation scheme.

If no response (success *or* error - either still proves the round trip works) arrives within
`heartbeatTimeoutMs` (default 10s), the socket is force-closed via `this.#socket?.close()`. This
hands off to the *existing* `#handleClose()`/`RECONNECT_DELAYS_MS` reconnect-backoff path unchanged -
a timed-out heartbeat is treated exactly like a real close/error, no separate reconnect logic needed.

Implementation notes:
- `#startHeartbeat()`/`#stopHeartbeat()` bracket the connection lifecycle: started in the "open"
  handler (alongside resetting `#reconnectAttempt`), stopped in both `#handleClose()` and the public
  `close()` - so heartbeating is only ever active while `transport === "websocket"`, matching
  `onPush()`'s own transport-boundedness.
- `#sendHeartbeat()` bypasses the `request()`/`#requestOverWebSocket()` wrapper (which builds
  `[data, Meta]` tuples and throws on JMAP-level errors) and calls the lower-level `#send()` directly
  - the heartbeat only cares that *a* response frame arrived, not its content, so a `RequestError`
  reply is deliberately swallowed rather than surfaced anywhere.
- No `super`/`async`+`#private` combination is involved in any of the three new methods, so neither
  of the two Babel/Rollup miscompilation bugs from Phase 4 (see above / [[feedback-babel-async-super-
  private-field-bug]]) applies here - confirmed via a clean `npx rollup -c` build.

### Test coverage

4 new tests in `JamWebSocketClient.test.ts` (`describe("JamWebSocketClient heartbeat")`), using
`sinon.useFakeTimers()` the same way the existing "re-enables push … after a reconnect" test does:
probe-after-idle-interval + reply counts as activity, probe-suppressed-by-recent-real-traffic,
force-close-and-reconnect-on-timeout (asserts both the forced `readyState === CLOSED` and that the
*existing* reconnect-backoff picks it up afterwards, i.e. no new/duplicate reconnect path was built),
and `heartbeatIntervalMs: 0` fully disabling it. `npx web-test-runner mail/js/test/*.test.ts
--node-resolve` - 123 total, all passing (up from 117 before this phase). Not yet live-verified against a real induced-zombie connection (hard to simulate deliberately without
actually pulling a cable/killing a proxy mid-session) - covered by the fake-timer unit tests only for
now.
