# Rocket.Chat integration: notifications and ticket threads

## Motivation

* Use Rocket.Chat **instead of email** for general EGroupware notifications, including mobile
  notifications pushed to the iOS/Android Rocket.Chat app.
* Tracker: use the tracker **fully through Rocket.Chat**: get new tickets assigned to you
  and simply reply to them, including commands like `!status pending`.

## Background

The `rocketchat` app today is only a *client*: it embeds the Rocket.Chat UI, and
`Hooks.php` uses `Api\Restapi` for status/avatar/user lookups as the logged-in user.
It never talks to Rocket.Chat on its own behalf. Goals:

1. A **notification backend** delivering EGroupware notifications into Rocket.Chat, per
   recipient (like email). Default: the user's room with the bot user "EGroupware".
   Optionally a separate room per app, created on the first notification.
2. **Tracker**: per user and queue a private room (e.g. "Tracker-<Queue>") containing only the
   tickets the user is involved in (assigned ...), each ticket as a thread with all its
   comments; replies in the thread become ticket comments; text commands in a thread:
   `!status pending`, `!assign +nathan` (`+` adds, without `+` replaces), more to come.

No code has been written yet - this doc is the research result plus the phased plan.

## Decisions taken / constraints

* **Rocket.Chat Community, self hosted** (a controller starts/stops it depending on active
  users). Since Rocket.Chat 7.0 Community enables **no private Apps-Engine apps**
  (docs contradict themselves, do not rely on it). => **No Rocket.Chat-side app.** Everything
  is done by EGroupware with the REST API and **one bot user "EGroupware"**.
* Real `/slash` commands exist only in Apps-Engine apps (an unregistered `/xyz` is intercepted
  by the client). => Commands are text commands with a `!` prefix. An app can be added later
  as an additional *source* of the same normalised command call (see Phase 6).
* Replies are **polled** (`chat.syncThreadMessages`), a delay of ~1 minute is fine for
  tickets. This avoids outgoing webhooks, which need a trigger word and do not document a
  `tmid` in their payload (unverified).
* **Identity**: Rocket.Chat username == EGroupware `account_lid` (case-insensitive), as both
  authenticate against the same LDAP or via OpenID Connect. "Allow Username Change" is off.
  No email matching, no account-linking. A Rocket.Chat user without matching EGroupware
  account gets no rooms and can not run commands. Never match on display name.
  Commands always run with *that user's* EGroupware rights, never the bot's.
* **No `only_public` SSRF guard** for the Rocket.Chat client: the URL is set by an admin in
  the site-config, and on-premise installs often resolve to the docker0 bridge IP.
* **Rocket.Chat is woken for notifications**: the point is that Rocket.Chat notifies users who
  are not connected (push, email), so a pending notification may start a stopped instance.
  The controller stops it again at the next full hour if nobody is online.
* **Rooms are private and per user** (bot + that user only), see Design 3. No public/shared
  queue rooms: they would put every ticket of a queue in front of everybody (noise), and
  restricted queues would need membership sync.

## Instance lifecycle (managementserver/server/rocketchat.php)

Read from the controller code, not yet tested against the live system:

* A stopped instance has no ingress, so requests land in `rocketchat.php`, which answers
  `/api/info`, `/websocket` and `/_timesync` (also with `/rocketchat/` prefix) with
  `{"powered":"off"}` and does **not** start anything. **Every other path starts the instance**:
  it creates secret, deployment, service and ingress, waits (`http_wait`, up to 500s - a start
  takes multiple minutes) and then answers `302` to the same URL plus `?powered=on`.
  => A bot `POST /api/v1/chat.postMessage` to a stopped instance starts it, but blocks for
  minutes and ends in a redirect (a POST turned into a GET): that call is lost.
* `stop-idle` stops instances with 0 `usersSessions` connections that are older than 30 min.
  Bot REST calls open no websocket session, so they neither keep an instance alive nor
  count as "active": a woken instance is stopped again at the next run if no user connects.
* Instances can be expired (`rc_expires`, `instance_expired`) or in trial: the controller then
  answers an HTML error page with 404, not JSON. The client must treat that as "unavailable".
* `rocketchat.php` does not call `ignore_user_abort()`: if the client or ingress drops the
  connection during the multi-minute start, the start may be aborted half way.

Consequences for the client / queue:

* Always probe `GET /api/info` first (never starts anything). `powered: off` and the queue holds
  entries => **wake**, otherwise send.
* Wake = a plain `GET` (never POST) to a non-exempt path, done by the async job, never inline in a
  user request: either hold the connection (long timeout, ~8 min) or, better, add
  `ignore_user_abort(true)` to the controller so a short request can trigger the start, and
  poll `/api/info` until `powered` is gone. Then flush the queue. Single-flight: only one wake
  in progress at a time (lock in `Api\Cache`), otherwise every minute adds another.
* Polling for replies must **not** wake: it checks `/api/info` and skips when powered off.
* Replies written by users arrive while they are connected (instance up). Messages written right
  before the last user disconnects and the instance is stopped are picked up from the cursor
  (`last_synced_ts`) at the next start, commands are answered late then.
* After an unplanned stop with a queue still holding entries, the wake simply repeats.

## Current code (relevant parts)

* `rocketchat/src/Api/Restapi.php`: raw `curl_init`, `CURLOPT_TIMEOUT => 1` (too short for
  create/post), errors flattened into `success`/`message` (HTTP status lost), per-user
  login whose token lives in the session (no bot concept).
* `notifications/inc/class.notifications.inc.php` loads backends by name
  `notifications_<backend>` from `notifications/inc/`, they must implement
  `notifications_iface::send()`. It is called **once per recipient**, honouring each user's
  notification chain; an exception makes the framework continue with the next backend.
* `tracker_tracking` (`tracker/inc/`) is where ticket changes are tracked and notified (creator,
  assignees, CC ... per existing tracker notification preferences).
* `Api\Asyncservice` provides (minute-granular) cron jobs.
* New-style REST client: `Api\CalDAV\RestClientTrait::api()` (used by `Api\Jmap`, `CalDAV\Sync`):
  headers, redirects, timeouts, TLS verification, `Api\Exception\Http` with status, `only_public`.

## Design

### 1. REST client (`rocketchat/src/Api/`)

New class on `RestClientTrait`, `only_public: false`, sensible timeouts (connect short, total
~10s), typed methods: `info`, `me`, `usersInfo`, `imCreate`, `groupsCreate`, `groupsInfo`,
`postMessage(roomId, text, tmid?, attachments?)`, `syncThreadMessages`, `getMessage`,
`deleteMessage`, `setTopic`. Auth via `X-User-Id` / `X-Auth-Token` headers.

* **Bot mode**: user id + personal access token of the "EGroupware" bot user, in the app's
  site-config (stored like other secrets there, never sent to the client).
* **User mode**: existing per-user login (credentials / openid), token kept in the session.
* `Restapi` stays as a thin wrapper while `Hooks.php`/`Ui.php` use it, then goes away.
* Rate limiter is per endpoint with no bot exemption: queue + back off on HTTP 429. Fan-out
  (one post per involved user) makes that likely, so the queue paces itself.

### 2. Storage

* `egw_rocketchat_rooms`: `account_id`, `key` (`bot` | `app:<app>` | `app:tracker:<queue id>`),
  `rc_room_id`, `rc_room_name`, `type`. Rooms are looked up by this mapping, created lazily,
  and an existing room with the expected name is adopted instead of failing "already exists".
* `egw_rocketchat_threads`: `account_id`, `app`, `entry_id`, `rc_room_id`, `rc_tmid` (root message
  id), `last_synced_ts`. One thread **per involved user** of an entry. The mapping lives in
  EGroupware: it is the only durable source, `customFields` would need admin enabling.
* `egw_rocketchat_queue`: pending outgoing posts (see 3).

### 3. Rooms and threads

Everything is a private room of exactly **bot + one user** (`groups.create`, or `im.create` for
the plain bot chat). That removes any membership/ACL sync: whoever is in the room is the user
the content was addressed to, and they are only addressed if the tracker would notify them anyway.

| Room | Created | Content |
|---|---|---|
| user's DM with "EGroupware" | default | all notifications, flat messages |
| `egw-<app>-<user>` | optional per app, on first notification | that app's notifications |
| `Tracker-<Queue>` (`egw-tracker-<queue>-<user>`) | on first ticket of the user in that queue | one thread per ticket, all comments |

* Room names: `[0-9a-zA-Z-_.]+` by default, unique per server => technical name, friendly name
  in topic/description. Whether a friendly `fname` can be set via the API needs a spike.
* A ticket thread exists for every user the tracker notifies (creator/assignee/CC per existing
  tracker notification preferences, i.e. "assigned to me" by default). When a user is added
  (`!assign +nathan`), their thread is created with a short summary and current state, when
  removed, posting stops (the thread stays as history).
* Every ticket event is **fanned out** to each involved user's thread. A comment written by
  user A (in the web UI or in A's thread) appears in the other users' threads as a normal
  message ("Ralf: ..."), but not again in A's own.
* Optional later: a read-only per-queue overview room with one message per *new* ticket and no
  threads, for people who want to watch a queue without being assigned.

### 4. Outgoing queue

Posts are not sent inline: they are inserted into the queue table and sent by an async job
(and opportunistically after the request). The job: probe `/api/info`; powered off => wake
(see "Instance lifecycle"); on errors / 429 retry with backoff; keep order per thread (root
message before replies, ids needed for `tmid`). Survives Rocket.Chat being off for hours.
Entries older than a configurable age are dropped, so an expired instance does not grow the
queue forever.

### 5. Notification backend

`notifications/inc/class.notifications_rocketchat.inc.php`, thin: formats subject, text and
link, enqueues. Delivery is **per recipient, like email**: the framework's per-recipient
`send()` fits directly. Target room per section 3 (bot chat by default, per-app room if
enabled). Users enable it in their notification chain preferences like any other backend.
Recipients without matching Rocket.Chat user throw, so the framework continues with the next
backend of their chain. For tracker events the backend does not post flat messages but
hands over to the thread logic (section 6): the same recipient list and preferences, compact
text.

### 6. Tracker

* Per-queue setting to switch Rocket.Chat threads on/off, per user via notification chain.
* New ticket / user newly involved: root message (title, requester, link, key fields) -> store
  `rc_tmid`. Changes and comments: thread replies (compact change summary).
  Restricted comments (`reply_visible`) are only sent to those allowed to see them.
* Inbound: an async job polls `syncThreadMessages` for the threads of open tickets (only recent
  and not-closed, cursor = `last_synced_ts`) **only if the instance is up**.
  * Plain reply -> ticket comment, authored by the mapped EGroupware user.
  * `!command args` -> command handler, which applies the change through the regular tracker
    save path. The thread shows the normal change message as for a change in the web UI
    (e.g. "Ralf set the ticket to pending"), not the raw command. The raw `!command` message
    can be removed by the bot (needs `delete-message`) or marked with a reaction; errors
    (no permission, unknown user/status) are answered in the thread.
  * Skip the bot's own messages and messages already imported (store message ids).

### 7. Commands

Parser + registry (`!status <name>`, `!assign [+]user...`, later `!priority`, `!close`, ...),
each command: resolve actor (username -> `account_lid`), check the actor's tracker rights via
`tracker_bo` (never bypass ACL), apply through `tracker_bo->save()`, so history and
notifications behave as usual. `+nathan` resolves against EGroupware accounts allowed as
assignee in that queue, not against Rocket.Chat names.

### 8. Command list (to be extended by reviewers)

Typed as a reply in a ticket thread, prefix `!`. The list is expected to grow while we use the
tracker via Rocket.Chat: reviewers, please add the commands that matter to you.

| Command | Effect | Status |
|---|---|---|
| `!status <name>` | set status, e.g. `!status pending` | proposed |
| `!assign <user>...` | replace assignees (`!assign nathan`) | proposed |
| `!assign +<user>...` | add to assignees (`!assign +nathan`) | proposed |
| `!help` | list available commands | proposed |
| | | *add yours here: command, effect, example* |

Ideas, not yet decided: `!priority`, `!close`, `!reopen`, `!resolution`, `!queue <name>` (move),
`!due <date>`, `!cc +user`, `!time <duration>` (timesheet entry), `!private <text>`
(restricted comment).

## Phases

0. **Spikes** (against our Community instance): bot role permissions (`create-p`, `create-d`
   / `im.create`, invite, post, `delete-message`), friendly room names, rate-limit defaults,
   `chat.syncThreadMessages` behaviour and cursor, thread posting with `tmid`, the wake sequence
   against the controller (with and without `ignore_user_abort`).
1. **REST client refactor** on `RestClientTrait` + tests, wrapper for the old class.
2. **Bot config, tables, queue + async sender incl. wake.**
3. **Notification backend** (bot chat, optional per-app rooms).
4. **Tracker threads**: per-user queue rooms, root message + fan-out.
5. **Inbound**: polling, replies -> comments, command registry (`!status`, `!assign`).
6. *Optional*: Apps-Engine app for real `/` commands on installs that can run private apps,
   as another source of the same "command + actor + thread" call. Per-queue overview room.

## Open points

* Fan-out cost: a ticket with N involved users means N posts per change (rate limit, queue
  length). Probably fine for tickets, to be measured in the spike.
* Whether `ignore_user_abort(true)` can be added to the controller (it is in the same repo
  area, but runs in production), otherwise the wake has to hold the connection.
* Who counts as "involved": default = whoever the tracker notifies (creator, assignees, CC,
  per their preferences) or strictly assignees? Strictly "assigned to me" is quieter.
* Whether other apps (infolog, calendar) get threads/commands later: keep the thread table
  app-generic (`app` + `entry_id`).
* Backports: master only until it has soaked, then decide about 26.
