# Mail: JMAP-lite REST endpoints for folders + emails

## Status: Phase 1 implemented + partially live-verified (2026-09-09); Phase 2 started

**Live-verified (ralf, 2026-09-09)**: `GET /mail/folders` and `GET /mail/folders/<folderId>/emails`
both confirmed returning correct data against a real running instance. `GET .../folders/<folderId>`,
`GET .../emails/<emailId>` and the attachment-download endpoint are NOT yet live-verified - still
resting on unit tests only.

ralf wants to extend the existing simple mail REST API
([`doc/REST-CalDAV-CardDAV/Mail.md`](../../REST-CalDAV-CardDAV/Mail.md),
[`doc/openapi/mail.json`](../../openapi/mail.json)) with **read-only** endpoints to list mail folders
and list/retrieve emails inside a folder, using JMAP's own `Mailbox`/`Email` data model (RFC 8620/8621).
Not a full JMAP implementation - no `/jmap` session negotiation, no method batching, no
`state`/`changes`, no push, no write methods. Just a handful of `GET` endpoints under the existing
`groupdav.php` REST surface.

**Design mandate (ralf, 2026-09-09): proxy, don't translate.** "I would stay as close to the JMAP
data-model as possible, so we can use JMAP or the shim to get the objects, without any extra
processing" / "for JMAP we can just proxy the responses from the JMAP server". This reverses this doc's
first draft, which planned a hand-picked field subset + a translation layer sitting on the older,
IMAP-flavoured `Api\Mail` facade. Research (below) found that's unnecessary: this codebase already has a
uniform, polymorphic JMAP session contract that produces genuine JMAP-shaped `Mailbox`/`Email` PHP
arrays for **every** backend today - real JMAP for Stalwart, and a local server-side emulation
("JmapShim") for every plain-IMAP account (Dovecot, Cyrus, even OAuth-authenticated ones like the
outlook.com test account). The new REST layer's job shrinks to: pick the right session for the account,
call it, and wrap the result in this REST API's own envelope - not reshape the object itself.

## Goal / non-goals

**Goal**: a client can, with plain `curl`/HTTP GET + Basic Auth or a token (same auth as the rest of
`/mail`), discover a mail account's folders and read the emails in a folder, receiving genuinely
JMAP-shaped JSON - proxied from the account's real JMAP session (Stalwart) or its local IMAP emulation
(JmapShim), not reshaped into a custom schema.

**Non-goals**:
- Any JMAP protocol machinery itself (session resource, capabilities negotiation, method-call batching,
  `state`/`Email changes`, push/WebSocket).
- Write operations (mark read/unread, flag, move, delete, send-from-draft, ...) - read-only.
- Reinventing an id scheme, a field subset, or a translation layer - see "Architecture" below for why
  none of that is needed.

## Architecture: reuse the existing polymorphic JMAP session contract

Every mail account already has, or can have, an `Api\Jmap\Base`-typed **session** object with a uniform
per-type accessor contract - `$session->mailbox->get($ids, $properties)`, `->query($filter, $sort)`,
`$session->email->get(...)`, `->query(...)` - regardless of backend:

- **`Api\Mail\Jmap\Http`** (`api/src/Mail/Jmap/Http.php`) - real JMAP-over-HTTP, for Stalwart-classed
  accounts (`acc_imap_type` is `Mail\Imap\Jmap`/`Mail\Imap\Stalwart`). Its `Mailbox`/`Email` per-type
  classes (`api/src/Mail/Jmap/{Mailbox,Email}.php`) have no `get()`/`query()` override - they use
  `Api\Jmap\Type`'s generic passthrough (`api/src/Jmap/Type.php:39-78`), which just forwards to
  `$jmap->call()` - a real `Mailbox/get`/`Email/get`/... round-trip against Stalwart. **Whatever
  properties Stalwart returns is what this API returns.** No PHP-side shaping at all.
- **`Api\Mail\Jmap\Imap`** (`api/src/Mail/Jmap/Imap.php`, aliased `JmapShim` in its own tests, formerly
  `EGroupware\Mail\JmapShim`) - a **local, server-side JMAP-shaped emulation for plain IMAP accounts**,
  backed by real `Horde_Imap_Client_Socket` `search()`/`fetch()`/`store()` calls. Its own per-type
  classes (`api/src/Mail/Jmap/Imap/{Mailbox,Email}.php`) *extend* the Http-side classes and *override*
  `get()`/`query()`/`set()` to call `Imap`'s own static methods (`mailboxNode()`, `emailFromFetch()`,
  ...) directly instead of an HTTP round-trip - but the **caller-visible contract and result shape are
  the same `Mailbox`/`Email` PHP arrays**, genuinely JMAP-flavoured (id, name, parentId, role,
  totalEmails, unreadEmails, ... for Mailbox; id, keywords, size, receivedAt, subject, from/to/cc/bcc,
  hasAttachment, preview, bodyStructure, bodyValues, ... for Email), not a bespoke shape.
- Coverage: `Account::imapServer()` (`api/src/Mail/Account.php:753-764`) instantiates whatever class
  `acc_imap_type` names, with no hardcoding to a specific IMAP product - so `Imap`/JmapShim already
  covers **every** non-Stalwart account uniformly, including OAuth-authenticated external accounts
  (confirmed: acc_id=64, the outlook.com test account, has plain `acc_imap_type = Api\Mail\Imap`, so it
  goes through the shim exactly like Dovecot does - OAuth vs password is transparent at Horde's socket
  layer). There is no third "unsupported backend" bucket for this API to special-case.

**New implementation work needed** (small, all at the "pick a session and call it" layer, none of it a
translation layer over the response shape):

1. **A single reusable session factory.** Today the `Http` vs. `Imap` choice is inlined via
   `instanceof Mail\Imap\Jmap` checks at ~32 call sites across `mail/src`/`api/src/Mail.php` (e.g.
   `mail/src/Ui/AttachmentJmap.php:626,667,701,741,784`, `api/src/Mail/Imap/Jmap.php:366` for the
   `Http`-building side) - there's no single method returning "the `Api\Jmap\Base` for this account"
   today. Add one (e.g. `Api\Mail\Account::jmapSession(): Api\Jmap\Base`), used by the new REST layer.
   Worth flagging as generally useful cleanup beyond just this project, but not required to touch the
   existing 32 call sites - just don't duplicate a 33rd inline `instanceof` check.
2. **Known, documented gaps between the two backends' `Mailbox`/`Email` objects** (see "Backend parity"
   below) - this API surfaces them as-is (that's what "proxy" means), except one gap worth actually
   fixing at the source rather than leaking to REST clients:
   - `Imap\Email`'s `emailFromFetch()` (`Imap.php:3021-3074`) never populates `mailboxIds` - the shim
     tracks "current mailbox" out-of-band via query context instead of as a per-email JMAP property.
     Real JMAP (Stalwart) always includes it. **Fix in the shim itself**
     (`Api\Mail\Jmap\Imap::emailFromFetch()`/`emailGet()`), not in the new REST layer - closes the gap
     for every future shim consumer, not just this API, and keeps the REST layer a pure proxy.
3. **A small routing/plumbing layer in `Mail\ApiHandler`** - new regex routes, argument parsing
   (`properties`, `filter[...]`, `sort`, `position`/`limit` query params → the session's `get()`/
   `query()` argument shapes), and this REST API's own envelope (see "Response envelope" below). This
   is unavoidable REST-surface work, not object-shape translation.

## Backend parity (documented, not hidden)

Since this API proxies rather than normalizes, callers see real differences between a Stalwart-backed
account and a shim-backed one - documented explicitly (in both this doc and the updated `Mail.md`) so
nobody mistakes a gap for a REST API bug:

**`Mailbox`**: Stalwart/`Http` returns the full RFC 8621 object (`id, name, parentId, role, sortOrder,
totalEmails, unreadEmails, totalThreads, unreadThreads, myRights, isSubscribed`, plus `shareWith` since
`Http` always declares `mail:share`). The shim's `mailboxNode()` (`Imap.php:875-918`) returns a narrower
set - **no** `myRights`, `totalThreads`, `unreadThreads` - plus two non-standard extras with no RFC 8621
counterpart: `hasChildren`, `aclCapable`. Both are left in place (not stripped) - "proxy" includes
backend-specific extras, not just backend-specific gaps.

**`Email`**: for the common list-view property set (id, keywords, size, receivedAt, subject,
from/to/cc/bcc, hasAttachment, preview) the two backends already converge closely - `preview` is
computed differently (a real 800-byte body peek on the shim vs. whatever Stalwart computes
server-side) but is the same JMAP field either way. `threadId` is supported by **both** (shim computes
it via a real IMAP `THREAD` command, gated behind explicitly requesting the `threadId` property -
`Imap.php:1320-1414,1639`) - so, unlike this doc's first draft assumed, threading data does not need to
wait for [[mail-threaded-view]] to land; it's already available on request. `mailboxIds` is the one gap
being fixed at the source (see above) rather than merely documented.

**`Email` body** (`bodyStructure`, `textBody`, `htmlBody`, `bodyValues`, `attachments`): **already fully
supported on both backends today**, in genuine JMAP shape, and already the real production path (this
is exactly what `mail/js/jmap.ts`'s `MailJmap.fetchBody()` already requests from either backend when a
user opens a message - `mail/js/jmap.ts:2653-2654`: `properties: ['bodyStructure', 'textBody',
'htmlBody', 'attachments', 'bodyValues']`, `fetchAllBodyValues: true`). This doc's first draft assumed a
flattened `textBody`/`htmlBody`-as-plain-string simplification was needed to avoid real JMAP's
`bodyStructure`/`bodyValues`-by-`partId` indirection - **that assumption was wrong**; the real shape is
already available for free from both `Http::emailGet()` and `Imap\Email::get()`/`Imap::emailGet()`, so
the single-email endpoint returns genuine JMAP body objects, not a simplified flattening.

Note: `mail/src/Ui/MessageDisplayHandler.php` is **not** this path - it's the classic server-rendered
message-iframe fallback, and only has a JMAP-native fast path for two special cases (S/MIME, TNEF/
winmail); an ordinary message falls through to classic `Api\Mail` MIME parsing there. Irrelevant to this
project - the new REST endpoint calls the session's `email->get()` directly, same as `jmap.ts` does, not
through `MessageDisplayHandler`.

**Attachment content**: both backends already produce self-describing/opaque `blobId`s per attachment
part (shim: `base64(mailbox) . ':' . $uid . ':' . $partId`, self-decoding; Stalwart: its own opaque blob
id), and a **backend-uniform byte-fetch method already exists**:
`EGroupware\Mail\Ui\AttachmentJmap::fetchBlobBytes(string $acc_id, string $blobId, ...): ?string`
(`mail/src/Ui/AttachmentJmap.php:617-644`) - dispatches to `$icServer->jmapClient()->downloadBlob()`
(RFC 8620 §6.2, real JMAP) for Stalwart, or `Imap::fetchRawPart()`/`fetchRawMessage()` for the shim. The
new attachment-download endpoint calls this directly - no new blob-fetch mechanism needed. (It currently
lives under the `Mail\Ui` app namespace as a convenience wrapper rather than on `Http`/`Imap`
themselves - fine to keep calling as-is; moving/generalizing it is optional cleanup, not required.)

## IDs: no new scheme needed

Both `Http` and `Imap` already produce their own `id` values as part of their real `Mailbox`/`Email`
objects - Stalwart's genuine opaque JMAP ids for `Http`; for the shim, `Mailbox.id = base64(folder
path)` and `Email.id = <IMAP UID>` (documented in `Imap.php`'s own header: "Row-id compatibility:
emailID here is a plain IMAP UID and folderID is base64(folder path), same as
`mail_ui::generateRowID()`'s classic scheme"). **This REST API doesn't synthesize an id at all** - it
just proxies whatever `id` the session's `get()`/`query()` call already returns, and accepts that same
value back in a subsequent request's URL. This doc's first draft proposed extracting a folder-id
fragment out of `Ui::generateRowID()`/`generateJmapRowID()` for a new REST-specific id scheme - not
needed: the shim already exposes exactly that value as its own `Mailbox.id`/`Email.id` fields via the
JMAP contract itself, and the REST layer never needs to touch `Ui.php` at all.

As before: **treat every `id`/`parentId`/`mailboxIds` key/`blobId` as fully opaque.** Never construct,
parse, or reuse one outside a request to this same account through this same API.

**One real wrinkle found during implementation**: the shim's `Mailbox.id`/`parentId` are plain
`base64_encode()` (`Mailbox::getMailboxId()`/`mailboxNode()`, `Api\Mail\Jmap\Imap.php`) - not
URL-path-segment-safe, since that alphabet includes `+`/`/`/`=`. `ApiHandler::urlSafeId()` re-keys
these (and `Email.mailboxIds`' keys) through a url-safe alphabet substitution before they ever reach a
JSON response, so what a client sees as `id` is always exactly what it can put back in a URL. The
**reverse** direction (`fromUrlSafeId()`, decoding a folder id back out of an incoming URL) is
deliberately **not** applied unconditionally: a genuine real-JMAP id (Stalwart) is allowed by RFC 8620
§1.2 to contain literal `-`/`_` as ordinary characters, and blindly reversing those back to `+`/`/`
would corrupt such an id even though the *encode* direction never touched it in the first place
(`urlSafeId()` is a true no-op for any id containing no `+`/`/`, which every real JMAP id satisfies by
construction - but that no-op-ness doesn't invert safely, since the output is indistinguishable from
an id that legitimately contains `-`/`_`). Fixed by gating the decode step on
`ApiHandler::isRealJmapSession($session)`: only ever decode when the session is the local
`JmapShim` (`Api\Mail\Jmap\Imap`), pass a real-JMAP (`Http`) folder id straight through unchanged in
both directions. `getFolder()`/`listEmails()` are the two call sites; `Email.id`/`blobId` never needed
this at all (a plain IMAP UID or an already-self-describing-url-safe `blobId` either way).

## `properties` and filter/sort: pass through, don't allow-list

Matching "no extra processing": a `properties` query parameter (comma-separated) is forwarded verbatim
as the `properties` argument to the session's `get()` call - exactly like real JMAP's own `properties`
argument. No server-side fixed field subset to maintain. Similarly, `Email/query`'s filter/sort query
params map 1:1 onto JMAP `FilterCondition`/`Comparator` keys (`filter[before]`, `filter[after]`,
`filter[hasAttachment]`, `filter[text]`, `filter[keyword]`, `filter[notKeyword]`, `sort=receivedAt
desc`) - built into the `$filter`/`$sort` arrays passed straight to `$session->email->query(...)`, one
field at a time, no reinterpretation.

## Endpoints

All under the existing `/mail[/<id>]/...` prefix (`<id>` = identity id, optional, same meaning as
existing endpoints).

#### `GET /mail[/<id>]/folders` - list folders

Query params: `properties` (comma-separated, forwarded to `Mailbox/get`), `subscribedOnly` (boolean,
default `true`).

Response (house-style envelope, matching the rest of this REST API - see "Response envelope" below):
```json
{
  "responses": {
    "/mail/folders/<folderId>": { "id": "<folderId>", "name": "INBOX", "role": "inbox", "...": "..." },
    "/mail/folders/<folderId2>": { "id": "<folderId2>", "name": "Sent", "role": "sent", "...": "..." }
  }
}
```
Object shape: whatever the account's JMAP session returns for the requested `properties` - see "Backend
parity" for what's present on each backend when `properties` is omitted (all standard properties).

#### `GET /mail[/<id>]/folders/<folderId>` - get a single folder

Un-wrapped `Mailbox` object (single-resource `GET` convention, same as existing `GET /mail/<id>`).

#### `GET /mail[/<id>]/folders/<folderId>/emails` - list emails in a folder

Query params: `properties` (comma-separated, forwarded to `Email/get`; default a reasonable list-view
set - id, mailboxIds, keywords, size, receivedAt, sentAt, subject, from, to, cc, bcc, hasAttachment,
preview - a *default*, not a restriction, since `properties` is client-overridable), `position`
(default `0`), `limit` (default `50`, max `200`), `sort` (default `receivedAt desc`), `filter[before]`,
`filter[after]`, `filter[hasAttachment]`, `filter[text]`, `filter[keyword]`, `filter[notKeyword]`.

Response:
```json
{
  "responses": {
    "/mail/folders/<folderId>/emails/<emailId>": { "id": "<emailId>", "subject": "...", "...": "..." }
  },
  "position": 0,
  "total": 137
}
```

#### `GET /mail[/<id>]/folders/<folderId>/emails/<emailId>` - get one email (full)

Query params: `properties` (default adds `bodyStructure`, `textBody`, `htmlBody`, `attachments`,
`bodyValues` to the list-view default, with `fetchAllBodyValues: true`).

Un-wrapped `Email` object - genuine JMAP body shape (`bodyStructure`/`bodyValues` by `partId`), not a
flattened simplification.

#### `GET /mail[/<id>]/folders/<folderId>/emails/<emailId>/attachments/<blobId>` - download attachment content

New in this revision (pulled forward from "Phase 2" - see Phasing). Returns the raw attachment bytes
(`Content-Type`/`Content-Disposition` from the attachment's `type`/`name`), via
`AttachmentJmap::fetchBlobBytes()`. `folderId`/`emailId` in the path are for discoverability/consistency
with how the client found the `blobId` (in the email object's own `attachments[]` list) - the underlying
fetch only actually needs the account + `blobId`.

No inline-`cid:`-rewriting in `htmlBody` - matching genuine JMAP client behaviour, a client resolves an
inline image by matching `attachments[].cid` and fetching that blob through this same endpoint. This
resolves this doc's original "inline images" open question without adding a proxy/rewrite step: the
attachment endpoint alone is sufficient.

## Response envelope (decided, 2026-09-09)

House style: `{"responses": {"<resource-path>": {...}, ...}}` for collection listings (matching
existing `GET /mail`, `Calendar.md`, `Addressbook.md`), plain unwrapped object for a single-resource
`GET`. `position`/`total` ride alongside `responses` as sibling keys for the emails-list endpoint,
mirroring how `Addressbook.md`'s own sync listing adds `more-results`/`sync-token` siblings.

## Phasing

**Phase 1 (this project's deliverable)**: all five endpoints above - folders list/get, emails
list/get, **and** attachment download (moved up from the first draft's Phase 2, per ralf: needed so a
client can resolve inline images itself, the genuine-JMAP way).

**Phase 2, `GET .../emails/<emailId>/raw` - done for free (2026-09-09)**: no separate endpoint needed
after all. `Email.blobId` (RFC 8621 §4.1.1, the whole raw RFC 5322 message) is just another blobId -
the existing `.../attachments/<blobId>` endpoint already downloads it correctly on both backends (the
shim's own blobId scheme already represents "whole message" as an empty-partId blobId,
`base64(mailbox):uid:`, per `Api\Mail\Jmap\Imap.php:3153`; `AttachmentJmap::fetchBlobBytes()` already
branches on that). Closed two small gaps to make it actually usable: added `blobId` to
`DEFAULT_EMAIL_BODY_PROPERTIES` (wasn't returned by default from `GET .../emails/<emailId>`), and
`getAttachment()` now recognizes a request for the email's own top-level `blobId` specifically and
sets `Content-Type: message/rfc822` + a `<subject>.eml` filename, instead of the generic attachment
fallback. No new route, no new PHP method.

**Phase 2 (still deferred)**:
- Search across all folders (`Email/query` without an implicit `inMailbox`) - scoped and shipped for
  the mail UI instead, see [[mail-cross-folder-search]]; REST exposure for this endpoint specifically
  not started.
- `Mailbox.myRights` for shim-backed accounts, if/when useful - would need real work in `Imap.php`
  (computing rights from IMAP `MYRIGHTS`), not just exposing an existing field.

**Explicitly not planned**: `Email/set`, `Mailbox/set`, JMAP session/capabilities resource, method
batching, `/changes`, push.

## Implementation (2026-09-09) - what actually landed

- `api/src/Jmap/Type.php` - widened the generic `get()`/`query()` contract to also carry
  `fetchAllBodyValues` (get) and `position`/`limit`/`calculateTotal` (query) - RFC 8620 §5.5/§4.3
  arguments the original two-argument signatures had no room for. Zero production callers existed yet
  (confirmed via grep), so this was a safe widen, not a breaking change. `Http`'s `Mailbox`/`Email`
  classes need no further change (no override, they already used the generic default). The shim's
  `Imap\Mailbox`/`Imap\Email` overrides got their signatures widened to match (LSP compatibility) -
  `Imap\Mailbox::query()` ignores the new params (folder listing has no paging concept yet, see
  "Backend parity"); `Imap\Email::query()` forwards `position`/`limit` into `Imap::emailQuery()`'s
  `$args` (which already supported them internally, just unreachable through the `Type` contract before
  this); `Imap\Email::get()`'s `$fetchAllBodyValues` stays unused - the shim already always computes
  full body values whenever any body property is requested, no partial-fetch mode to opt into.
- `api/src/Mail/Jmap/Imap.php` - fixed the `mailboxIds` gap in `emailFromFetch()` (unconditional, cheap
  - `$imap`/`$mailbox` were already in scope, no extra IMAP round trip; uses the exact same
  `base64(canonicalPath())` id scheme as `Mailbox::getMailboxId()`, so it always matches a real
  `Mailbox.id` from the same session).
- `api/src/Mail/Account.php` - added `jmapSession(): Api\Jmap\Base` factory (the `Http` vs. `Imap`
  choice in one place for this project to call; the existing ~32 inline `instanceof` sites elsewhere in
  the codebase are untouched, out of scope).
- `mail/src/ApiHandler.php` - the 5 route handlers (`listFolders()`, `getFolder()`, `listEmails()`,
  `getEmail()`, `getAttachment()`), following the existing regex-dispatch pattern in `get()`; the
  `properties`/`filter[...]`/`sort`/`position`/`limit` query-param parsing helpers; `urlSafeId()`/
  `fromUrlSafeId()`/`isRealJmapSession()` (see the "IDs" section's wrinkle above); `jsonMailbox()`/
  `jsonEmail()` (id re-keying only, no other reshaping); `listAllFolders()` (the recursive per-level
  tree flatten). Error responses use plain `\Exception($msg, $httpCode)` (404/400), matching this
  file's own existing convention for REST-facing errors - **not** `Api\Exception\NotFound`/
  `WrongParameter` (those default to non-HTTP internal codes, e.g. `NotFound`'s default is `2`; this
  file's own `put()` already works around that by hardcoding `'404 Not Found'` rather than trusting
  `handleException()`'s generic code passthrough for that class).
- `doc/REST-CalDAV-CardDAV/Mail.md` / `doc/openapi/mail.json` - updated to match (see git history).
- `mail/tests/ApiHandlerJmapRestTest.php` - new unit tests (no live IMAP/JMAP/DB needed) for every pure-
  logic piece above: the id-transform asymmetry (including a test that documents/pins the exact
  wrinkle described above), `queryEmailFilter()`/`queryEmailSort()` parsing, `jsonMailbox()`/
  `jsonEmail()` re-keying, `listAllFolders()`'s recursive walk (against a small fake `Api\Jmap\Base`/
  `Type` double), `Type::query()`/`get()`'s widened argument-building (against a fake `Base` capturing
  `call()` args), and the `mailboxIds` fix in `emailFromFetch()` (against a mocked non-`INBOX` mailbox,
  same `mockImap()`-style pattern `JmapShimMailboxGetTest.php` already uses).

**Not done yet / explicitly deferred**:
- Live REST-level verification against a running instance (the style `mail/tests/REST/
  MailAccountPatchTest.php` already uses for the *existing* mail REST endpoints, real HTTP round-trip
  via `RestBase`/Guzzle) - needs a live dev/docker instance with acc_id=1 (Stalwart) and acc_id=85/42
  (Dovecot/shim) per [[mail-test-coverage]]'s own account notes; not run in this session.
  Specifically worth checking live: the `Mailbox/query filter:{parentId:null}` top-level semantics
  against real Stalwart (spec reading says it should match top-level mailboxes, per this doc's own
  earlier caveat) and the `hasAttachment` filter's documented no-op on the shim.
- Phase 2 items (raw `.eml` download, cross-folder search, shim `myRights`) - unchanged from the
  original plan, still deferred.

## Related

[[mail-jmap-modernization]], [[mail-bo-decoupling]], [[mail-folder-tree-jmap]], [[mail-threaded-view]],
the JMAP-native mail ACL work (planned in memory `project_mail_jmap_acl_plan`, not yet its own
doc/ai/projects file), [[mail-test-coverage]], [[mail-cross-folder-search]] (Phase 2's "search across
all folders" item, scoped for the UI first, REST exposure deferred as a second step)
