# Push fallback: SSE / long-polling as a swoolepush alternative

## Motivation

`swoolepush` (a persistent Swoole websocket daemon) is EGroupware's "real" push
transport. It requires a long-running PHP process, which is not available on
plain shared hosting or a tarball-in-docroot install (no daemon manager, often
no shell access, sometimes no websocket support through the front-end proxy
either). On those installs the system already falls back to a SQL-backed
polling mechanism, but that fallback is coarse-grained, partially wired up,
and not detected/driven client-side. This project is about closing that gap:
give the no-swoole case a low-latency, PHP-FPM-friendly delivery path
(long-polling, primarily), and make the client detect "no working push" on
its own instead of trusting a static server-computed flag.

No code has been written yet - this doc is the architecture map plus the
phased plan, per `AGENTS.md`'s "develop a plan before making changes".

## How push works today

### Backend abstraction (`api/src/Json/Push.php`, `PushBackend.php`)

- `Api\Json\Push extends Msg` (`api/src/Json/Msg.php`) - `Msg` is the same
  base class `Api\Json\Response` uses, and every "message" method
  (`alert()`, `message()`, `apply()`, `call()`, ...) funnels through
  `addGeneric($method, $data)`. That means **any** response call can be
  carried over push, not just a fixed set of notification messages.
- `Push::checkSetBackend()` walks `self::$backends` (seeded with
  `notifications_push` as the always-last default) plus whatever the
  `push-backends` hook contributes, instantiates the first one that doesn't
  throw, and caches it. `Push::onlyFallback()` just checks
  `self::$backend instanceof \notifications_push`.
- `swoolepush/src/Hooks.php::push_backends()` returns `Backend::class`
  (`swoolepush/src/Backend.php`), prepended ahead of `notifications_push` when
  that app is installed. `Backend::__construct()` already self-heals: if the
  swoole daemon can't be reached, it tracks `failedAttempts()` with an
  exponential `backoffTime()` (60s-3600s, `Api\Cache`-backed) and throws after
  3 failures, so `checkSetBackend()`'s try/catch drops straight through to
  `notifications_push`. **This part - server-side reachability of the swoole
  daemon - is already handled.** The gap is client-side reachability of the
  websocket endpoint, which nothing currently probes (see below).
- `swoolepush/src/Hooks.php::notify_all()` is the hook `Api\Link::notify()`
  calls for every entry change, instance-wide. It builds an ACL-safe `$extra`
  subset from the app's `push_data` link-registry entry and does
  `(new Push(Push::ALL))->apply('egw.push', [[app, id, type, acl, account_id]])`
  - a **broadcast** (`account_id = 0`), not per-recipient. Per-recipient
  relevance filtering happens client-side using the `grants` blob sent at
  login (`Hooks::framework_header()`, `api/js/jsapi/app_base.js`'s
  `push()` method, per-app overrides e.g. calendar). This client-side
  filtering path is transport-agnostic already (see next section) and needs
  no rework.

### The SQL fallback is already generic, not notifications-specific

`notifications/inc/class.notifications_push.inc.php` implements
`PushBackend` against `egw_notificationpopup` (`notify_type = 'push'`, a
different type than the notification-popup rows which use `'base'`):

- `addGeneric($account_id, $key, $data)` inserts one row per account id, or
  a single `account_id = 0` row for broadcast (`Push::ALL`) - `get()`'s
  `SELECT ... account_id IN (0, $my_account_id)` already matches both, so
  broadcast fan-out is already correct and cheap.
- `get()` (called unconditionally from `Api\Json\Request::handleRequest()`,
  `api/src/Json/Request.php:158-163`, on **every** JSON/ajax request, as long
  as the user has the `notifications` app and the class exists) replays every
  queued row since `already_send` (a per-session `Api\Cache` value) via
  `call_user_func_array([Json\Response::get(), $message['method']], ...)`.
  Because `Response` and `Push` share the same `Msg` base, this reconstructs
  the exact same `apply()`/`call()`/etc. the websocket would have sent, and
  the client dispatches it through the same code path either way (e.g.
  `app_base.js`'s `push()`). **The delivery mechanism itself is already
  fully generic and already reuses the client's existing dispatch - nothing
  new needs inventing there.**
- `cleanup_push_msgs()` rate-limits deletes of rows older than
  `Api\Session::heartbeat_limit()` to once/hour/instance.
- `online()` reports accounts as online if their session's
  `notification_heartbeat` column is set and recent -
  `Api\Session::update_notification_heartbeat()` (`api/src/Session.php:2020`)
  is the only writer, and it only fires when
  `$GLOBALS['egw_info']['flags']['currentapp'] == 'notifications'`
  (`Session.php:1438-1442`) - i.e. **only while the notifications app's own
  poll loop is actually running for that session.** A user with the
  notifications app disabled is never "online" for fallback purposes even
  while actively using the browser tab.

### What actually delivers fallback messages to the browser today

`notifications/js/notificationajaxpopup.js` is the only client-side driver:

- On load it schedules `get_notifications()` (calls
  `notifications.notifications_ajax.get_notifications`) after 10s, then every
  `POLL_INTERVAL` (from `data-poll-interval` on `#notifications_script_id`,
  admin-configurable, default-ish 60s), doubling the interval on ajax failure.
- `notifications_ajax::get_notifications()` (`notifications/inc/class.notifications_ajax.inc.php:100`)
  answers `{isPushServer: true}` when
  `!Api\Json\Push::onlyFallback()` (cached 900s, `Api\Cache`) - purely a
  **server config fact** ("is a real backend class instantiable"), not "did
  *this browser's* websocket actually connect.
- `run_notifications()` in the JS (`notificationajaxpopup.js:107-121`): if the
  response says `isPushServer`, it **stops rescheduling itself** -
  permanently, for the life of that page, regardless of whether the
  websocket the client is separately trying to open ever reaches `OPEN`.

Meanwhile the real websocket connection is wired up entirely separately:
`api/js/jsapi/egw.js:704` calls `egw.json('websocket', {}, undefined, this).openWebSocket(...)`
using the `websocket-url`/`websocket-tokens`/`websocket-account_id` extras
`swoolepush/src/Hooks.php::framework_header()` injects unconditionally
whenever that app is installed and the request isn't `login.php`.
`egw_json.ts`'s `Json.openWebSocket()`/`#websocket` state and
`Egw::pushAvailable()` (`egw_json.ts:912-917`, `readyState === OPEN &&`
`reconnectTime === MIN_RECONNECT_TIME`) are the one place that reflects
*actual* live connectivity - but today only `filemanager/js/filemanager.ts`
and (for the unrelated per-account mail JMAP websocket, see below)
`mail/src/Ui/ProfileHandler.php` read it. **It is not connected to the
notification poll loop's stop/start decision at all.**

### Consequences of the two signals disagreeing

If `swoolepush` is installed and reachable server-side (so
`onlyFallback() === false`, `isPushServer: true` sent to the client) but a
particular browser's websocket never reaches `OPEN` (corporate proxy blocks
`wss:`, TLS-terminating reverse proxy doesn't forward the Upgrade, etc.),
that tab's notification poll loop stops on the very first response and never
restarts. From then on the only way that tab receives *any* push-carried
message (notification popups, `egw.push` entry-change broadcasts, import
progress, etc.) is by accident - piggybacked on whatever unrelated ajax call
the user happens to trigger next, since `notifications_push::get()` still
runs on every request. On a genuinely swoole-less shared-hosting install this
specific divergence doesn't occur (the app usually isn't installed at all, so
`onlyFallback()` is `true` from the start and the poll loop never stops) -
but it is a real, already-possible bug on today's code, and the planned
client-side detection work fixes both cases with the same mechanism.

### Other consumers of `Push::onlyFallback()` and how they degrade

| Caller | Behaviour on fallback |
|---|---|
| `calendar_boupdate`, `infolog_ui`, `timesheet_ui` | skip the lean "push-only" single-entry refresh, force a full app refresh message instead - correct, just coarser |
| `admin_accesslog` | switches its "who's online" query from `Push::online()` (asks the daemon) to `notification_heartbeat > limit` (see the heartbeat gap above - undercounts non-notifications users) |
| `mail/src/Ui/ProfileHandler.php` | decides whether to also try the **separate**, per-account JMAP websocket subsystem (`mail/js/jmap-jam-websocket.ts`, its own design doc `doc/ai/projects/mail-jmap-jam-websocket.md`) - unrelated transport, out of scope here, but worth reusing its auto-detection precedent |
| `importexport_import_ui::sendUpdate()` | **silently no-ops** (`error_log()`s and returns) instead of queueing a best-effort update through the fallback - import/export progress bars simply never update on a swoole-less install today. Concrete, fixable bug found while mapping this. |

## The actual gap to close

1. **Client-side auto-detection.** Nothing today asks "did my websocket
   actually connect" before deciding to stop polling. Detection must be a
   live client fact (`Egw.pushAvailable()`-equivalent, after a bounded
   connect attempt/timeout), not the static server flag.
2. **Delivery latency and coverage in fallback mode.** Bounded by whatever
   `POLL_INTERVAL` is (default tens of seconds) and by whether the
   notifications app happens to be enabled and its script loaded for that
   user - not by whether the account actually needs timely delivery of
   calendar/infolog/mail/import-progress push messages.
3. **Fallback delivery is coupled to the notifications app's UI**, when the
   message queue itself (`notifications_push`) is already fully generic.
   Delivery should be driven by core (`api/js/jsapi`), independent of
   whether that particular user has the notifications app.
4. **The notifications client is not a normal app.ts at all.**
   `notifications/js/notificationajaxpopup.js` is a standalone jQuery script
   injected into every page by `notifications/inc/hook_after_navbar.inc.php`
   (raw `<script type="module">` + hand-built `#egwpopup` markup), completely
   outside the `EgwApp`/`app.classes[appname]` lifecycle every other app uses
   (`api/js/jsapi/egw_app.ts`, e.g. `infolog/js/app.ts`'s
   `class InfologApp extends EgwApp`). It conflates two responsibilities -
   driving its own poll loop *and* rendering the bell/popup UI - in one
   untestable jQuery blob, and today's push-grant/testability conventions
   (`api/js/jsapi/test/EgwAppPushGrantCheck.test.ts` and its per-app
   siblings, which test `EgwApp` subclass logic directly on the prototype
   with a minimal `this`, no live DOM needed) don't apply to it at all
   because it isn't a class. Ralf wants this ported to a real,
   `EgwApp`-based, plain-DOM, TypeScript `notifications/js/app.ts`, with
   testability as a hard requirement, not an afterthought.

## Proposed plan

**Phase 1 - port the notifications client to a real, testable app.ts.**
Rewrite `notificationajaxpopup.js` as `notifications/js/app.ts`,
`class NotificationsApp extends EgwApp`, same shape as every other app
(constructor calling `super('notifications')`, `et2_ready()`/`destroy()`,
etc.), using plain DOM APIs (`createElement`/`classList`/event listeners) in
place of every current `jQuery(...)` call - no new jQuery per the repo-wide
rule, and this file currently has the most jQuery usage of any UI this
project touches. Concretely:
  - Split the two responsibilities the old script conflates: this class owns
    *rendering* (`#egwpopup` popup list, the navbar bell/count, mark
    seen/delete) and *reacts to* delivered messages; it does not itself own
    poll-scheduling once Phase 3's core driver exists (today it must, since
    nothing else drives delivery yet - see the sequencing note below).
  - Testability, using the existing prototype-testing pattern (no live DOM
    needed for logic tests, per `EgwAppPushGrantCheck.test.ts`): a
    `notifications/js/test/` suite covering message parsing/dedup, seen/
    delete accounting, poll backoff math, and the push-available/fallback
    branch - each exercised directly against the class prototype with a
    minimal mock `this`/`egw`. Anything that genuinely needs to touch the
    rendered DOM should reuse this repo's existing DOM-test-harness
    conventions (the same iframe/stub approach already used elsewhere for
    widgets that touch real elements) rather than inventing a new one.
  - **Open lifecycle question:** every other `EgwApp` subclass is
    instantiated lazily by `etemplate2.ts` (`app[appname] = new
    app.classes[appname]()`) when a template belonging to that app loads -
    but notifications has no owning template; it must exist on every page
    regardless of which app is open. Two options: (a) keep
    `hook_after_navbar.inc.php`'s always-emitted bootstrap, but have it
    explicitly do the `app.classes.notifications` instantiation itself
    (smallest change, keeps today's "always on every page" behaviour
    untouched), or (b) give notifications a minimal always-loaded template so
    the standard lazy-instantiation path "just works" with no special case
    (more consistent with "a real app.ts like all the others", but a bigger,
    probably unnecessary change for a bell icon). Leaning (a) - flagging for
    Ralf's call before writing code.
  - This phase should land **before** Phases 2-3 below, since it establishes
    the test harness and the class the rest of this project's client-side
    work hangs off of, and removing the jQuery/DOM tangle first makes the
    push-availability wiring in Phase 2 far easier to test in isolation.

**Phase 2 - unify the client-side push-availability signal.** In
`egw_json.ts`, give the websocket connect attempt a bounded timeout and a
small state machine that answers one question definitively: "is push
currently available in this tab". Fire an event (or callback list) on every
transition. Point `filemanager.ts` and the new `NotificationsApp` (Phase 1)
at this single signal instead of `pushAvailable()` ad hoc in one place and
the server's stale `isPushServer` flag in the other.

**Phase 3 - generic fallback poller in core, not the notifications app.**
Add a small driver in `api/js/jsapi` (app-agnostic - it delivers
calendar/infolog/mail/import-progress pushes too, not just notification
popups, so it must run independently of whether the user's account even has
the `notifications` app enabled) that starts when Phase 2's signal says
"push unavailable" and stops when it says "available". `NotificationsApp`
becomes a *consumer* of this driver's delivered messages for its own popup
UI, rather than owning the poll loop itself as it must in the interim
(Phase 1) state. The driver hits a dedicated ajax endpoint (new, or a
focused method split off `notifications_ajax`/exposed via `Api\Json\Push`
itself) that:
- `session_write_close()`s immediately (`notifications_ajax` already does
  this - keep it, it's why the current poll never blocks sibling tabs).
- Reuses `notifications_push::get()`'s existing replay logic unchanged.
- Optionally **holds** the request open for a bounded window (e.g. a
  server-side loop doing a cheap indexed `MAX(notify_id)` check every 1-2s,
  returning the moment something is queued or a ~15-20s cap is hit), so the
  client can immediately re-issue -> classic bounded long-polling, latency in
  the low single-digit seconds instead of a full poll interval, no protocol
  or infra changes needed.

**Phase 4 - concurrency safety valve.** Every held request in Phase 3 ties up
one PHP-FPM worker for up to its wait window - fundamentally different from
swoole's coroutines, which don't. Before shipping the "hold the request"
behaviour, add an `Api\Cache`-backed counter of currently-held fallback
requests instance-wide, and degrade to immediate-return (classic short poll)
once a configured budget is hit, so this feature can never itself exhaust
`pm.max_children` and start queuing ordinary page loads. Document a
recommended `pm.max_children` floor for admins who rely on this.

**Phase 5 - fix the concretely-degraded consumer found while mapping this.**
`importexport_import_ui::sendUpdate()` should queue a best-effort update via
the same fallback path instead of silently no-op'ing when
`Push::onlyFallback()` is true. Audit other `onlyFallback()` call sites for
the same silent-no-op pattern while there.

**Phase 6 - SSE, auto-detected via a self-probing single request, with
automatic fallback to the existing long-poll.** Supersedes the original
"secondary, optional, admin opt-in" sketch above - the actual concern
(many hosts' proxies silently buffer output, defeating streaming with no
error, just silence) is better solved by *probing* for it empirically than
by asking an admin to guess/confirm it up front. Revised design, worked out
with Ralf:

- **One endpoint, one shared concurrency budget - no second counter/config.**
  `ajax_poll()` gains an `$sse` flag. It still claims a slot from the exact
  same Phase 4 counter (`Push::class`/`ajax_poll_active`) regardless of
  transport requested. No slot available -> falls straight into the
  existing degraded path (plain JSON, `degraded: true`), whether or not SSE
  was requested - one degrade path, not two. This is deliberately *not* a
  separate, smaller SSE-specific budget: the resource question that matters
  is "how many users have a held connection open right now", which is the
  same number whether each holder's slot is one long-lived SSE stream or a
  20s long-poll that keeps re-cycling - a continuously-active user ties up
  roughly one worker either way. SSE reduces total *request* volume for the
  same steady-state demand, it doesn't increase the per-user worker cost.
- Slot claimed AND `$sse` requested -> the server tries to switch into
  streaming mode: disable output buffering as best it can
  (`X-Accel-Buffering: no`, `ob_end_flush()`+`flush()`), then immediately
  emit+flush a tiny `event: ack` chunk before doing anything else, and keep
  the held connection open afterwards - delivering each subsequent batch as
  its own SSE event instead of returning after one, bounded by a longer
  max-hold duration than the plain long-poll's 20s (needs picking - probably
  minutes, still finite; PHP's `max_execution_time` still applies to a
  streaming response) and a periodic `connection_aborted()` check so a
  closed/navigated-away tab's slot gets released promptly.
- **Client races ONE request against a short timer, no separate probe
  step**: issues a single `sse=true` request, and whichever of these
  happens first decides the outcome:
  - an `ack` event arrives -> commit to SSE for the rest of this page's
    lifetime, and mark `egw.pushAvailable()` true via the same
    `Json.notePushConnected()` hook Phase 2 built (a working SSE stream is,
    to every other consumer, indistinguishable from the swoole websocket -
    both mean "real-time delivery is working right now").
  - a normal JSON response arrives first (degraded or not) -> handled
    exactly like today's plain long-poll response already is, no new branch
    needed on the client either.
  - neither arrives within ~2-3s -> the connection is silently hanging
    somewhere in the stack (the exact "proxy buffers everything" failure
    mode this whole phase exists to detect) - abort it, and stay on plain
    long-polling for the rest of this page's lifetime. No per-request
    re-probing in this first version - if conditions change (admin fixes
    the proxy config), a full page reload picks that up, same as any other
    build/config change already requires today.
- Target scale, Ralf's framing: this whole project (not just Phase 6) is
  meant to work out of the box for small installs (~20 concurrent users,
  matching the existing default budget) and, with the admin turning the one
  existing knob up, roughly double that - anything bigger is expected to
  mean a real swoolepush/dedicated-infrastructure deployment, not tuning
  this fallback further.
- Not yet resolved, to pick during implementation: the SSE max-hold
  duration; whether `notifications_push::get()`'s per-delivery
  session-reopen is fine to keep doing repeatedly over one long-held SSE
  connection (it already tolerates being called many times per request in
  the plain long-poll loop, so likely yes, but worth confirming under an
  actually long hold) and how the client parses the raw SSE
  framing (`event:`/`data:` lines, blank-line message boundaries) without
  pulling in a new dependency.

**Phase 7 - retire the duplicate flags.** Once Phase 2's unified signal is in
place client-side, drop `notifications_ajax::$isPushServer` /
`data.isPushServer` in favour of it, so there is exactly one place that
answers "is push available right now".

## Open questions before implementation starts

- Phase 1's lifecycle question above: bootstrap `NotificationsApp` explicitly
  from `hook_after_navbar.inc.php` (option a), or give it a minimal
  always-loaded template so the standard `app.classes` lazy-instantiation
  path applies unmodified (option b)?
- Where should the new Phase 3 ajax endpoint live - `notifications` app
  (closest to the existing table/logic) or promoted into `api` core (closer
  to where `Push`/`PushBackend` already live, and where Phase 3 says delivery
  conceptually belongs)?
- Concrete long-poll tick/cap durations and the Phase 4 concurrency budget
  need picking against a real shared-hosting `pm.max_children`, not just
  guessed - needs either a target host to test against or a documented
  assumption.
- Whether Phase 5's importexport fix should ride along with this project or
  be filed as its own small independent fix (it's a real bug, but unrelated
  to the detection/long-poll work otherwise).

## Status

**Phase 1 done.** `notifications/js/notificationajaxpopup.js` (jQuery, no
class, injected via a raw `<script src>` from `hook_after_navbar.inc.php`)
is replaced by `notifications/js/app.ts` - `class NotificationsApp extends
EgwApp`, plain DOM APIs, no jQuery, with `notifications/js/test/
NotificationsApp.test.ts` (26 tests, both Firefox and Chromium) covering the
pure/stateful logic per this doc's testability requirement.

Notable findings/decisions from implementing Phase 1:

- **No kdots framework change was needed after all**, despite the initial
  hunch that one would be (this doc's earlier open question). Investigating
  concretely (rather than assuming the `status` app's hidden-tab/template
  mechanism was the right model) found that this repo's rollup build already
  auto-discovers *any* `<app>/js/app.ts` (`rollup.config.js`'s
  `addAppsConfig()`) and hashes its output like every other entry
  (`api/js/build-manifest.json`), with zero rollup config changes required.
  `notifications` has no owning template/tab at all (unlike `status`, which
  genuinely renders content into `EgwFramework.ts`'s `<slot name="status">`)
  - its bell/popup DOM is unconditionally server-rendered into every page's
    topmenu already (`Api\Framework::_get_notification_bell()` /
    `kdots_framework::topmenu()`), so a fake hidden app-tab would have been
    the wrong model to copy. What *is* needed, and is now wired up, is
    resolving the hashed chunk: `hook_after_navbar.inc.php` now emits a tiny
    inline bootstrap calling `window.egw_import('/notifications/js/app.min.js')`
    - the same logical-path/manifest-resolving helper
      `etemplate2.ts`/`egw_json.ts` already use for every template-owning
    app - instead of a hand-built `<script src>` URL, which would 404 under
    the hashed-entries build (the literal `notifications/js/app.min.js` file
    no longer exists on disk; only its hashed chunk does).
  - `notifications/js/app.ts` self-bootstraps (async, gated behind the
    user's `notification_chain` preference, same intent as the old script)
    from its own top-level `window.egw_ready.then(...)`, since - unlike
    every other `app.ts` - nothing else naturally sequences its construction.
- **Found and fixed a latent race as a side effect**: `api/js/jsapi/
  egw_json.ts`'s `applyFunc()` already has a generic "app.<name> not yet
  instantiated" fallback (auto-`egw_import()`s that app's entry, then
  `new app.classes[name]()`) used whenever a push/ajax response calls
  `app.<name>.<method>` before that app object exists. Because notifications
  had no rollup entry before this port, that generic path could never help
  it - an early `app.notifications.append()` (a real server push, not just
  the client's own poll) was silently dropped instead, relying on the next
  natural poll to catch up. Now that it has an entry, that path can
  auto-construct `NotificationsApp` on demand. To make that safe, all static
  UI wiring (bell/header/delete-all/seen-all click handlers) moved into the
  constructor itself and construction is idempotent
  (`window.app.notifications ||= new NotificationsApp()`) - whichever path
  constructs first (the bootstrap block or this fallback) ends up fully
  wired, with no double-binding of event listeners. `egw_json.ts`'s own
  comment there (previously citing notifications by name as *the* example of
  an app that "never will" have a rollup entry) and
  `kdots_framework::_get_notification_bell()`'s docblock were updated to
  match.
- `app.notifications.append(rows, browserNotify?, total?)` and
  `app.notifications.tabToggle(appname)` are called from outside this file
  (server-side push, resp. `kdots/js/EgwFramework.ts`'s per-tab notification
  badge) - their names/signatures were kept stable across the port.
- Minor incidental fixes while porting (behaviour-preserving, not scope
  creep): `Et2Dialog.BUTTON_YES_NO` (used by the delete-all-notifications
  confirmation dialog) doesn't exist as a constant - only `BUTTONS_YES_NO`
  does - so the old code silently fell through to `show_dialog()`'s own
  default anyway; now references the real constant. The popup open/close
  toggle animation (jQuery's `.toggle('slide')`) is now a plain, unanimated
  show/hide - `.toggle('slide')`'s slide effect may already have been
  silently inert (see [[feedback_jquery_ui_no_longer_bundled]]-equivalent:
  jQuery UI is no longer bundled in this repo), not verified either way.

**Phase 2 done.** Unified the client-side push-availability signal in
`api/js/jsapi/egw_json.ts`'s `Json` class (the "json" module):

- `egw.pushAvailable()` (existing method, kept) now returns a debounced
  state instead of the raw instantaneous `readyState===OPEN &&
  reconnectTime===MIN_RECONNECT_TIME` check it used to compute inline. New
  `egw.onPushAvailabilityChange((available) => ...)` (new method) subscribes
  to transitions and returns an unsubscribe function - added to the
  `JsonModule` interface alongside `pushAvailable()`.
- `openWebSocket()`'s *initial* connection attempt is now bounded
  (`CONNECT_TIMEOUT_MS = 8000` - a proxy that accepts the TCP connection but
  never completes/refuses the WS upgrade previously left it hanging in
  `CONNECTING` state indefinitely, with no signal at all); on timeout it
  force-closes the socket, which drives it through the normal
  onclose/backoff/retry path unchanged.
- A lost connection doesn't flip `pushAvailable()`/fire a "false" transition
  immediately - it only does after `PUSH_UNAVAILABLE_GRACE_MS = 10000` of
  staying down (a single dropped ping reconnects within
  `MIN_RECONNECT_TIME`=1s today and would otherwise flap any consumer that
  starts/stops a poller on every blip). A *clean* close (nothing will
  reconnect it) still flips immediately, no grace period. Becoming available
  again (a real open/message) is still immediate either way.
- `notifications/js/app.ts`'s `run_notifications()` now checks
  `this.egw.pushAvailable()` (both before polling at all, and again after
  the response comes back) instead of the server-supplied, per-response
  `isPushServer` flag, and the constructor subscribes via
  `onPushAvailabilityChange()` to resume polling the moment push stops being
  available - the one transition nothing else would notice, since a poll
  that isn't running can't reschedule itself. The server-side
  `isPushServer`/`Push::onlyFallback()` flag itself is untouched for now -
  removing it is Phase 7, once nothing client-side still reads it.
  `filemanager.ts`'s existing `pushAvailable()` calls needed no changes at
  all - they're automatically pointed at the new, steadier signal for free.
- New coverage: `api/js/jsapi/test/EgwJsonPushAvailability.test.ts` (8
  tests) loads the real, unmodified `egw_json.ts` against a new
  `FakeWebSocket` (added to `EgwJsonHarness.ts`) plus sinon fake timers
  targeting the iframe's own realm (`{global: env.window}` - undocumented in
  `@types/sinon`'s bundled `.d.ts` but a real, working
  `@sinonjs/fake-timers` `install()` option), covering the connect-timeout,
  the grace-period debounce (both the "reconnects in time, no flap" and the
  "stays down, does flip" cases), immediate-on-clean-close, and
  subscribe/unsubscribe. `notifications/js/test/NotificationsApp.test.ts`
  gained 1 test (skip polling outright while already available) and one
  existing test was adapted from the old `isPushServer`-response shape to
  the new `pushAvailable()`-based one. Both suites, plus the full `api`
  jstest group (2510 tests total now), green on Firefox+Chromium.

**Phase 3 done.** Generic bounded long-poll fallback driver, server + client:

- **Server**: `notifications_push::get()` (`notifications/inc/class.notifications_push.inc.php`)
  now returns `bool` (true if it actually delivered something this call) instead of void - its
  one existing caller (`Api\Json\Request::handle()`'s per-response piggyback) ignores the return
  value, unaffected. Two new thin accessors, `alreadySend()`/`maxId()`, peek at the same
  session/instance cache `get()` itself reads, without ever touching the session - added so a
  polling loop can cheaply check "is there anything new" on every tick without paying `get()`'s
  `session_start()`/`session_write_close()` round-trip each time (confirmed safe: per
  `api/tests/CacheSessionWriteTrackingTest.php`, `Api\Cache::getSession()`/`setSession()` already
  work correctly on a closed session via `$_SESSION`'s in-memory state + a buffered-write replay
  on next reopen - `get()`'s own manual session dance is specifically what persists a delivery to
  storage so a *different* request/tab sees it, not something every read needs).
  `Api\Json\Push::ajax_poll()` (`api/src/Json/Push.php`) is the new bounded long-poll entry point:
  releases the session lock immediately (`commit_session()`, same as
  `notifications_ajax::get_notifications()` already does), then loops for up to
  `AJAX_POLL_MAX_WAIT_SECONDS=20`, checking `alreadySend()`/`maxId()` every
  `AJAX_POLL_TICK_USECONDS=1s` and only calling the (more expensive) `get()` when they disagree
  (or on the very first call ever, to seed the baseline) - so a poll with nothing new touches the
  session at most once total (to seed), not once per tick. Reachable via menuaction
  `EGroupware\Api\Json\Push::ajax_poll` - resolves to `$appName='api'` in
  `Request::handleRequest()`, which is always allowed, so this needs no app permission and works
  for every logged-in user (unlike the older per-response piggyback, gated behind
  `apps['notifications']`).
- **Client**: new `api/js/jsapi/egw_push_fallback.ts` (wired into `egw_modules.js`, so it's part
  of the always-loaded core bundle, not the notifications app) - a small self-instantiating driver
  that starts long-polling `ajax_poll()` the moment `egw.pushAvailable()` is false (checked once at
  load, then again on every `egw.onPushAvailabilityChange()` transition), immediately re-issues on
  every successful return (the actual long-poll loop), and backs off exponentially (2s doubling to
  60s) instead of hammering the endpoint on a failure. One instance per window/popup, matching
  `egw_json.ts`'s one-websocket-per-window granularity.
- **Test coverage**: `api/tests/Json/PushPollTest.php` (5 PHPUnit tests, real DB/session via
  `LoggedInTest`) - `get()`'s true/false return, `alreadySend()`/`maxId()` staying in sync without
  a session reopen, and `ajax_poll()` returning essentially instantly (not sleeping a full tick)
  when something's already queued; deliberately does NOT test the "nothing ever arrives, wait the
  full 20s" path (same bounded-sleep-loop shape, no additional risk, just ~20s of wall clock for
  no new coverage). `api/js/jsapi/test/EgwPushFallback.test.ts` (5 tests, real
  `egw_push_fallback.ts` loaded into the same `EgwJsonHarness` iframe Phase 2's tests use, with a
  fake `window.fetch()`) - starts on load when unavailable, doesn't poll at all when already
  available, re-issues on success, backs off on failure, and stops/resumes across a live
  availability transition. Found and fixed a real off-by-one while writing that last test: the
  backoff computation doubled the delay *before* using it, so the very first retry waited 4s
  instead of the documented/intended 2s. `EgwJsonHarness.ts` gained a reusable `FakeWebSocket`
  export and an exported `loadScript()` helper (both already existed internally; Phase 2 only
  needed the former, Phase 3's client test needed the latter too). Full `api` jstest group now
  2515 tests; `api/tests/Json/` (25) and the directly-adjacent `api/tests/Vfs/HooksTest.php` (12,
  reflects on `Api\Json\Push::$backend`) re-run clean. All green, both browsers where applicable.
- **Known limitation, not addressed by this phase**: `ajax_poll()` only ever finds anything when
  `notifications_push` (the SQL fallback `PushBackend`) is the currently ACTIVE backend for the
  whole instance (`Push::checkSetBackend()` picks exactly one backend and only that one's
  `addGeneric()` calls get written anywhere) - the common, primary target case this whole project
  is about (no swoolepush daemon at all). It does NOT help the narrower case flagged back in the
  original mapping (see "Consequences of the two signals disagreeing" above): a real push backend
  configured and reachable *server-side*, but unreachable from one specific browser - that
  browser's `egw.pushAvailable()` correctly says false (Phase 2), but nothing is ever written to
  the SQL table for `ajax_poll()` to find, since the active backend is the real one, not
  `notifications_push`. Fixing that would need `Api\Json\Push::addGeneric()` to dual-write to the
  SQL queue as a standing backup regardless of which backend is "active" - a larger, more invasive
  change to core `Push` dispatch, deliberately deferred rather than folded into this phase; flagged
  as a possible future refinement, not planned as a numbered phase.
- **Security note, checked not (re)introduced**: `ajax_poll()`'s double-colon menuaction form
  derives `$appName` directly from the class's own namespace (`EGroupware\Api\Json\Push` ->
  `'api'`), not from an independently-supplied string the way the dotted `app.class.method` form
  can be - so it isn't exposed to GHSA-76q5-2jm8-x8c3's spoofing vector (declaring `appName=api`
  while naming a *different* app's privileged class); `checkMenuAction()`'s cross-app check only
  ever runs on that dotted form regardless. The exemption this method DOES rely on (any
  `EGroupware\Api\*`-namespaced static method matching the ajax-prefix naming convention needs no
  app permission at all) is pre-existing policy for the whole namespace, not something newly
  introduced here.

**Phase 4 done.** Concurrency safety valve for `ajax_poll()`
(`api/src/Json/Push.php`):

- An `Api\Cache::incrementCache(Api\Cache::INSTANCE, Push::class, 'ajax_poll_active', ...)`
  counter (atomic on APCu/memcached via their native `increment()`/`decrement()`, best-effort
  elsewhere - same guarantee level `swoolepush\Backend::failedAttempts()` already relies on for
  its own instance-cache counter, not a new category of risk) - claim-then-check, not
  check-then-claim, so the claim itself is the atomic operation. A call that pushes the count
  above the budget (`AJAX_POLL_DEFAULT_MAX_CONCURRENT = 20`, admin-overridable via notifications'
  config screen - new `ajax_poll_max_concurrent` field, `notifications/templates/default/
  config.xet`) immediately releases its claim and degrades to a single, immediate,
  non-holding check (`notifications_push::get()` once, no wait loop) instead of joining the held
  queue - this method can now never itself be the reason `pm.max_children` gets exhausted. The
  counter key carries its own expiration (`AJAX_POLL_MAX_WAIT_SECONDS + 10`) as a self-healing
  backstop in case a worker dies mid-hold without ever reaching the `finally` block that
  decrements it normally.
- A degraded call reports `Response::data(['degraded' => true])`, so the client
  (`egw_push_fallback.ts`) knows to back off to a slower fixed cadence
  (`DEGRADED_RETRY_MS = 15000`) instead of immediately re-issuing into a tight loop against an
  endpoint that just responded instantly *because* it's already over budget - re-issuing
  immediately there would only add to the same load, defeating the whole point of degrading.
- **Sizing guidance** (Ralf's call, informed by this repo's own default container's
  `pm.max_children=80`): keep `pm.max_children` at at least 2x the configured budget, so held
  polls can never claim more than ~50% of the worker pool even at the ceiling - still a safe
  default; up to ~2/3 of the pool is acceptable too for installs that want a higher ceiling. The
  default budget of 20 needs `pm.max_children >= 40` under the safe (50%) rule, comfortably under
  this repo's own container's 80.
- New tests: `api/tests/Json/PushPollTest.php` gained 3 (counter returns to 0 after a normal
  call; degrades and reports it once the budget is exhausted, releasing the slot it briefly
  claimed to check; does NOT degrade for the call that exactly fills the budget) - 8 tests total
  now, all green. `api/js/jsapi/test/EgwPushFallback.test.ts` gained 1 (backs off to
  `DEGRADED_RETRY_MS`, not an immediate re-issue, on a degraded response) - 6 tests total, green
  on both browsers. Full `api` jstest group re-run clean (2516 tests) after one transient,
  non-reproducible single-browser flake that 3 isolated reruns of the changed files and a repeat
  full-group run never reproduced - treated as environmental noise, not a regression.

**Phase 6 done.** SSE, auto-detected via the single-request race design worked
out with Ralf (see the "Proposed plan" section above for the full protocol),
implemented and tested:

- **Server** (`api/src/Json/Push.php`): `ajax_poll($sse=false)` now branches
  into `ajaxPollWait()` (the existing plain bounded wait, unchanged
  behaviour, now also checking `connection_aborted()` on every tick so a
  client that already gave up - eg. the SSE ack-timeout race aborting this
  exact request - doesn't keep a slot held for the rest of
  `AJAX_POLL_MAX_WAIT_SECONDS` for nothing) or `ajaxPollSse()` (new). Both
  claim from the exact same Phase 4 counter/budget - deliberately not a
  second, SSE-specific one: the resource question is "how many users have a
  held connection open right now", the same number regardless of which
  transport each holder is on (Ralf's call, and the more correct model, not
  just the simpler one - see the "Proposed plan" section). `ajaxPollSse()`
  disables buffering as best it can (`X-Accel-Buffering: no`,
  `ob_end_flush()`), suppresses the normal end-of-request JSON send
  (`Request::isJSONRequest(false)`, the same escape hatch json.php's own
  early-exit paths already use), emits+flushes an `event: ack` chunk
  immediately, then keeps delivering onto the same connection (checking
  `connection_aborted()` every tick) for up to `AJAX_POLL_SSE_MAX_WAIT_SECONDS`
  (120s) before ending cleanly so the client reconnects. Documented, not
  fixed: a narrow (sub-millisecond), pre-existing-shaped race where the
  older per-response piggyback in `Request::handle()` could advance
  `already_send` past a message that arrives in the brief window right as
  an SSE connection ends, without ever having delivered it - see the
  method's own docblock for why this wasn't worth a more invasive fix.
- **Client** (`api/js/jsapi/egw_push_fallback.ts`): tries SSE first via
  `#trySse()`, racing one request against `SSE_ACK_TIMEOUT_MS` (3s); falls
  back to the existing plain long-poll for the rest of the page's lifetime
  if nothing survives end-to-end, reconnects directly (no re-probe) on a
  routine stream end, and marks `egw.pushAvailable()` true/false through
  the exact same signal Phase 2 built (`egw.notePushConnected()`/
  `notePushStopped()`, both promoted from internal-only to part of the
  public `JsonModule` interface for this - `egw_json.ts`).
- **Three real bugs found while testing this** (all fixed):
  1. The exponential backoff in `#trySse()`'s/`#poll()`'s error path doubled
     the delay *before* using it, so the very first retry waited double
     `MIN_RETRY_MS` instead of `MIN_RETRY_MS` itself.
  2. **Self-abort feedback loop**: `PushFallback` subscribes to
     `onPushAvailabilityChange()` to stop itself when push becomes available
     via some *other* source (the websocket). Once SSE also reports through
     that exact same signal, a successful SSE connection's own
     `notePushConnected()` call reached that same listener and immediately
     called `#stop()` on the connection that had just succeeded. Fixed with
     a `#sseActive` guard: the listener only stops the fallback for an
     availability change it did not cause itself.
  3. **Wrong callback reused for stream chunks**: the one `JsonRequest`
     created for the initial (possibly non-streaming) response was also
     being reused to dispatch every subsequent SSE message.
     `handleResponse()`'s generic "last entry" fallback invokes its
     callback for any response carrying a non-`data` entry - which every
     real delivered message is - so every message pushed down a working
     stream also re-triggered `#handlePollResponse()` and started a
     redundant plain long-poll running *alongside* the perfectly good SSE
     stream. Fixed with a separate, callback-less `JsonRequest` used only
     for dispatching stream chunks.
  4. (Also, Phase 4, found while reasoning through reusing that response for
     SSE's non-stream fallback, not new to this phase): `handleResponse()`
     can invoke a response's callback *twice* - once via the 'data' plugin
     with the real payload, once more via the same generic fallback - when
     a response carries both a delivered message and a `Response::data()`
     entry, which `ajax_poll()`'s degraded path can produce. Fixed with a
     `#once()` wrapper so only the first (correct) invocation is acted on.
- **Test coverage**: `api/js/jsapi/test/EgwPushFallback.test.ts` grew to 11
  tests (from 6) - the four SSE-specific ones (commits on ack, falls back on
  ack-timeout, dispatches a real stream message, reconnects directly on a
  clean stream end) plus a regression test for bug #4 above, all requiring a
  new `FakeSseStream`/`resolveStream()` addition to `EgwJsonHarness.ts`
  (incl. wiring the fake reader to reject a pending/future `.read()` on the
  request's `AbortSignal` firing, matching real `fetch()` behaviour - needed
  for the ack-timeout test, and only discovered to be necessary by first
  writing that test without it and watching it hang). Full `api` jstest
  group now 2521 tests, green on both browsers;
  `api/tests/Json/`/`api/tests/Vfs/HooksTest.php`/`notifications` groups
  re-run clean.

Phase 5 (the importexport fix) not started.

**Phase 7 done** (2026-09-21): retired the now-superseded
`notifications_ajax::$isPushServer` flag/`data.isPushServer` response
entry. Since Phase 1's port to `notifications/js/app.ts`, the client has
only ever driven its poll/stop decision off `egw.pushAvailable()` (Phase
2's unified, actually-live signal) - nothing client-side read
`data.isPushServer` any more, confirmed by grep before removing it.
Removed: the `$isPushServer` property, its `Api\Cache::getInstance('notifications',
'isPushServer', ...)` 900s-cached lookup of `!Push::onlyFallback()`, and
the `if ($this->isPushServer) $this->response->data(['isPushServer' =>
true]);` line, all in `notifications_ajax::__construct()`/
`get_notifications()`. Also updated a stale doc-comment in `app.ts` that
still referenced the removed branch. `Api\Json\Push::onlyFallback()`
itself is untouched - `calendar_boupdate`/`infolog_ui`/`timesheet_ui`/
`admin_accesslog`/`mail/ProfileHandler`/`importexport` all still have
their own, legitimate, unrelated reasons to read it directly. No test
referenced `isPushServer` anywhere (grep confirmed), so nothing needed
updating; `php -l` clean, `notifications` jstest group re-run clean
(28/28, both browsers), TS typecheck clean for both touched files.

## Mail notification-check polling (follow-up found while discussing Phase 6)

Not one of the original 7 phases - found while double-checking with Ralf
whether `NotificationsApp` still needs its own polling once a reliable push
transport is available (it already stops entirely once `egw.pushAvailable()`
- that part was already correct, built in Phase 2).

**The finding**: `notifications_ajax::get_notifications()` (what the client
polls) does two unrelated things - deliver already-queued messages (now
redundant once push works), and call `Api\Hooks::process('check_notify')`,
which for `mail` is the *only* trigger for `mail_hooks::
notification_check_mailbox()` - the code that actually checks IMAP and
*creates* the "you've got new mail" notification for accounts with
`notify_folders` configured. Neither Dovecot's Lua push nor JMAP's push
currently create that notification themselves - both only refresh the mail
app's UI (`egw.push`-style row-change events), confirmed by reading both all
the way through:
- Dovecot's Lua script posts straight to `swoolepush/server.php`'s `PUT`
  handler, which pushes directly to connected swoole sockets - bypassing
  `Api\Json\Push` (and therefore this whole project's fallback machinery)
  entirely, and never constructing a `notifications` object.
- JMAP's push (`Jmap::pushCallback()`, via Stalwart's webhook) does run
  through the full framework (real session/DB), but also only ever builds
  an `egw.push`-style `app: 'mail'` row-refresh payload.
- Ralf: Dovecot's path can't reasonably be fixed for this at all (server-to-
  swoole-daemon, no framework/session context); JMAP's *could* eventually
  build the actual notification itself, but JMAP mail servers are rare
  enough in practice to deprioritize for now.

So gating the keep-alive poll on `pushAvailable()` at all would have been a
regression (silently dropping "new mail" notifications for any
push-available account with `notify_folders` configured) - it doesn't yet
mean anything for this purpose.

**Implemented now** (the safe, no-functional-regression piece):
- `mail_hooks::needsNotificationCheckPolling()` (`mail/inc/
  class.mail_hooks.inc.php`) - cheap, config-only (no IMAP connection):
  does the current user have ≥1 mail account with `notify_folders`
  configured, full stop, independent of push type/availability.
- `notifications/inc/hook_after_navbar.inc.php` emits a new
  `data-mail-check-interval` attribute (180s, matching
  `notification_check_mailbox()`'s own internal rate limit) when that's
  true.
- `notifications/js/app.ts`'s `run_notifications()`: no longer just
  "stop entirely while `pushAvailable()`" - if `mailCheckInterval` is also
  set, it keeps polling (still calling the same `get_notifications()`,
  which is dedup-safe for delivery) at that slower cadence instead of
  stopping, purely to keep `check_notify` alive. New tests in
  `notifications/js/test/NotificationsApp.test.ts` (28 total now, was 27).
- New `mail/tests/NotificationCheckPollingTest.php` (2 PHPUnit tests, real
  session + a real account's `notify_folders` row, always restored in
  `tearDown()`).
- **Found and fixed a real, pre-existing bug while writing that PHPUnit
  test** (not introduced by this change): `Api\Mail\Notifications::read()`'s
  cache-hit path, when called with the array form of `$account_id` (its own
  default - `[0, $my_account_id]`, also used by `Mail\Account::read()`),
  never matched the cache's `{account_id => folders[]}` shape the way the
  DB-row-shaped path does, silently preferring the empty `account_id=0`
  default over a just-`write()`-ten per-user override sitting right next to
  it in the same request's cache. Fixed by extending the same "fix up
  account_specific when notify_folder gets removed" pattern the existing
  code already had for the *scalar* `$account_id` case to the array case
  too. Only manifests within a single request that writes then re-reads
  (eg. saving mail notification preferences) - `notification_check_mailbox()`
  itself never writes, so its own steady-state polling was unaffected.

**Logged as a follow-up, not built** (Ralf: deprioritized, JMAP mail
servers are rare): extend `Jmap::pushCallback()` to construct the actual
`notify_folders` notification itself when a `MessageNew`/`MessageAppend`
event lands in a configured folder (reusing/refactoring the
notification-building logic currently stuck inside
`notification_check_mailbox()`), which would let JMAP accounts with
`notify_folders` configured *also* skip the keep-alive poll once built and
confirmed working. Dovecot-push accounts can't get this treatment at all
(see above) and will always need the keep-alive poll regardless.

## Live regression: PushFallback ran on the login page (found + fixed)

Found live by Ralf shortly after Phase 6 shipped: the login page kept
reloading itself in a loop, with a console error `"framework.callOnLogout
is not a function!"` while handling a response from `ajax_poll()`.

**Root cause**: `egw_push_fallback.ts` lives in the always-loaded core
bundle (`egw_modules.js`), unlike `notifications/js/app.ts`, which is only
ever emitted server-side for a logged-in user with that app
(`hook_after_navbar.inc.php`'s own `if
($GLOBALS['egw_info']['user']['apps']['notifications'])` guard). Nothing
stopped `PushFallback` from self-instantiating and immediately calling
`ajax_poll()` on the login page too, where there is no session at all.
`json.php`'s session-verification failure path,
`login_redirect()`, then:
1. `apply('framework.callOnLogout')` - throws, since the login page never
   loads `EgwFramework`/`window.framework` at all (caught+logged per-entry
   by `handleResponse()`, not fatal on its own).
2. `redirect(.../login.php?cd=10)` - actually navigates. The fresh page
   load re-instantiates `PushFallback`, which immediately repeats the same
   round-trip - a reload loop.

**Fix**: `PushFallback`'s constructor now returns immediately when
`window.egw_appName === 'login'`, before even touching `egw_ready` - the
exact same guard, for the exact same reason, `egw.js`'s own build-epoch
poll already uses (found live 2026-09-19, per that code's own comment:
submitting the login form is itself a full page navigation, so there's
nothing a background connection could usefully do there anyway). This
class of "don't run on the login page" bug is apparently easy to introduce
for anything living in the core bundle rather than an app-gated
server-emitted script - worth checking for in any *future* addition to
`egw_modules.js` too, not just this one.

New regression test: `api/js/jsapi/test/EgwPushFallback.test.ts` (now 12,
was 11) - sets `window.egw_appName = 'login'` before loading the script and
asserts zero fetch calls ever happen. Full `api` jstest group re-run clean
(2522 tests, both browsers).

## Live regression: notifications/js/app.ts inclusion violated CSP (found + fixed)

Found live by Ralf right after the login-page fix above shipped: the
browser console showed a CSP violation -
`Executing inline script violates the following Content Security Policy
directive 'script-src 'self' 'unsafe-eval' blob: https://www.youtube.com'.
Either the 'unsafe-inline' keyword, a hash (...), or a nonce (...) is
required to enable inline execution. The action has been blocked.` -
and `notifications/js/app.ts` never loaded at all.

**Root cause**: Phase 1's own fix for getting `app.ts` loaded (it has no
owning template/tab, so nothing would otherwise trigger the usual
etemplate2.ts dynamic import) was itself wrong.
`hook_after_navbar.inc.php` emitted a literal
`<script type="module" id="notifications_script_id" data-poll-interval=
"..." ...>window.egw_import('/notifications/js/app.min.js');</script>` -
an *inline* script, whose executable content (the `window.egw_import(...)`
call) CSP's `script-src` blocks outright (no `'unsafe-inline'`, no
nonce/hash for arbitrary per-request content like this). This was never
caught by any jstest, since the test harness's fake DOM has no CSP
enforcement at all - it only showed up live, in a real browser, against
the real site header.

**Fix**: use the same mechanism every other app already uses to include
its own rollup-built bundle - `Api\Framework::includeJS('/notifications/js/
app.min.js')` (exact pattern grepped from e.g.
`calendar/inc/class.calendar_owner_etemplate_widget.inc.php` and
`mail/compose.php`). This registers the file with
`Framework`'s `$js_include_mgr`, which the framework's own header
rendering later turns into an *external* `<script type="text/javascript"
src="...">` tag (`Framework::get_script_links()`) - satisfying
`script-src 'self'` without needing any inline content at all. Per Ralf's
own description: "it sends the include-request to client-side, which
requests it not contradicting the CSP."

`includeJS()` has no way to attach `id`/`data-*` attributes to the
`<script src>` tag it produces, so the `data-poll-interval`/
`data-mail-check-interval`/`data-langRequire` values `app.ts`'s
constructor reads via `document.getElementById('notifications_script_id')`
now live on a separate, non-executing `<div id="notifications_script_id"
style="display:none" data-...>` element instead - `app.ts` never assumed
the element was a `<script>` in the first place, only that it had that id
and those attributes, so no client-side change was needed.

Verified: `php -l` clean; TS typecheck clean for this file (unrelated
pre-existing errors remain in stylite/wiki/tracker/untissync, untouched);
`notifications` jstest group re-run clean (28/28, both browsers); full
`api` jstest group re-run clean (2522/2522, both browsers, 0 failed).

**Worth remembering**: jstest's fake-DOM harness cannot catch a CSP
violation - it doesn't enforce CSP at all. Anything that emits its own
`<script>` tag server-side (rather than going through
`Framework::includeJS()`/`get_script_links()`) needs a *live* check
against the real site, not just a green test suite, before being
considered done.

## Live regression: `includeJS()` from `after_navbar` was too late, bell never loaded at all (found + fixed)

Found live by Ralf right after the CSP fix above shipped: the navbar's
notification bell (2nd icon, top right) showed no notification count at
all, and clicking it did nothing - no popup ever opened. Verified
reproducible on a second instance too, ruling out a one-off/caching fluke.

**Root cause**: the CSP fix above was correct about *how* to include a
script without violating CSP, but wrong about *where* to call it from.
`Api\Framework\Ajax::header()` calls `_get_header()` (line 267), which
calls `_get_js()`, which resolves `Api\Framework::get_script_links()` into
`$extra['include']` - the array actually baked into the page's rendered
`<head>`/bootstrap data. Only *after* that, at line 282, does it call
`_get_after_navbar()` (`Api\Hooks::process('after_navbar', ...)`). Since
`hook_after_navbar.inc.php` was the only place calling
`Api\Framework::includeJS('/notifications/js/app.min.js')`, that
registration always happened one step too late to ever be seen by
`get_script_links()` for a real, top-level page - `app.ts` never actually
loaded, so nothing ever attached the bell's click handler or ran the
poll loop that would populate its badge count. This was silently invisible
in every previous check (jstest doesn't render a real page through
`Ajax::header()` at all; the earlier CSP fix's own live verification only
confirmed the network *request itself* stopped violating CSP, not that
the resulting `<script>` tag was actually present in a rendered page).

**Fix**: split the responsibility. `notifications/inc/hook_framework_header.inc.php`
(new file, registered via a new `framework_header` hook entry in
`notifications/setup/setup.inc.php`) now does the one thing that has to
happen early - `Api\Framework::includeJS('/notifications/js/app.min.js')`
- guarded the same way `swoolepush`'s own `framework_header` hook is (skip
entirely for popup windows, via the hook's own `$args['popup']`; skip if
the user doesn't have the notifications app, matching every other guard
in this app). `hook_after_navbar.inc.php` keeps doing the other thing it
always did - echoing the `#notifications_script_id`
config-carrying `<div>` and the `#egwpopup` markup - since DOM placement
*after* the navbar was always the actual reason for that hook's name, and
was never itself the problem.

Regression test: `notifications/tests/FrameworkHeaderHookTest.php` (new,
`LoggedInTest`-based, 3 tests) - asserts notifications registers a
`framework_header` hook at all, that invoking it (the same way
`Ajax::header()` does, in the same order) makes
`Framework::get_script_links()` actually contain
`notifications/js/app.min.js`, and that a popup-window call (`'popup' =>
true`) does *not* register it. All 3 pass; re-ran the full `notifications`
PHPUnit dir + `mail/tests/NotificationCheckPollingTest.php` +
`api/tests/Json/PushPollTest.php` clean (13 tests, 36 assertions) and the
`notifications` jstest group clean (28/28, both browsers) to check for
collateral damage from the Phase 7 `isPushServer` removal done just
before this.

**Worth remembering**: this codebase has (at least) two distinct
framework-hook timings that look interchangeable but aren't -
`framework_header` fires *before* `Api\Framework::_get_js()`/
`get_script_links()` gets consulted, `after_navbar` fires *after*.
Anything that needs `Framework::includeJS()` to actually reach a real,
non-AJAX page must use `framework_header` (or an equivalently early hook)
- `after_navbar` is for DOM *placement* only, never for registering a
script to be included. Also: forcing a NEW hook registration to take
effect requires `Api\Hooks::read(true)` (normally triggered by
Admin > Applications, or automatically after the existing 3600s instance-cache TTL expires) -
worth remembering as a manual step after any change that adds a
`$setup_info[...]['hooks']` entry to `setup.inc.php`, not just this one.
