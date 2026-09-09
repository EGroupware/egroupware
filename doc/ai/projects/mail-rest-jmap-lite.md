# Mail: JMAP-lite REST endpoints for folders + emails

## Status: Planning (2026-09-09), nothing implemented yet

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

**Phase 2 (deferred)**:
- `GET .../emails/<emailId>/raw` - raw `message/rfc822` download (symmetrical to the existing `POST
  /mail[/<id>]/view` upload; no download today).
- Search across all folders (`Email/query` without an implicit `inMailbox`).
- `Mailbox.myRights` for shim-backed accounts, if/when useful - would need real work in `Imap.php`
  (computing rights from IMAP `MYRIGHTS`), not just exposing an existing field.

**Explicitly not planned**: `Email/set`, `Mailbox/set`, JMAP session/capabilities resource, method
batching, `/changes`, push.

## Implementation plan (files to touch, for the follow-up implementation task)

- `api/src/Mail/Account.php` - add `jmapSession(): Api\Jmap\Base` factory (encapsulates the `Http` vs.
  `Imap` choice in one place for this project to call; existing 32 inline `instanceof` sites untouched).
- `api/src/Mail/Jmap/Imap.php` - fix the `mailboxIds` gap in `emailFromFetch()`/`emailGet()` (see
  "Backend parity").
- `mail/src/ApiHandler.php` - new regex routes + handler methods (`listFolders()`, `getFolder()`,
  `listEmails()`, `getEmail()`, `getAttachment()`) following the existing dispatch pattern in `get()`;
  parses `properties`/`filter[...]`/`sort`/`position`/`limit` query params into the session `get()`/
  `query()` call shapes, and wraps results in this API's `{"responses": {...}}` envelope. No object
  reshaping beyond that envelope.
- `doc/REST-CalDAV-CardDAV/Mail.md` / `doc/openapi/mail.json` - already updated in this planning pass to
  match the design above (see git history of those two files alongside this doc).
- Tests: extend `mail/tests/REST/` with a read-only test class exercising all five endpoints against
  **both** a JMAP-native account (acc_id=1, Stalwart) and a shimmed account (acc_id=85 or 42, Dovecot) -
  per [[mail-test-coverage]]'s own priority note ("JMAP/shim over classic `Api\Mail`"), and specifically
  assert the documented backend-parity gaps (`myRights`/`totalThreads`/`unreadThreads` absent on shim,
  `mailboxIds` now present on both after the fix above) rather than assuming silent parity.

## Related

[[mail-jmap-modernization]], [[mail-bo-decoupling]], [[mail-folder-tree-jmap]], [[mail-threaded-view]],
the JMAP-native mail ACL work (planned in memory `project_mail_jmap_acl_plan`, not yet its own
doc/ai/projects file), [[mail-test-coverage]]
