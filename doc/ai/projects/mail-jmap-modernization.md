# Mail: JMAP Modernization

## Goal

Move mail's row-fetch, body rendering, and bulk actions from PHP/IMAP round-trips to JMAP, for both
kinds of account:

- **Real JMAP** (Stalwart) - the client and server talk JMAP directly.
- **Plain IMAP** - no real JMAP server exists, so a local shim (`mail/jmap.php` + `JmapShim`)
  implements just enough of the JMAP protocol against a raw IMAP connection, so the client-side code
  can stay backend-agnostic.

This is a long-running, incremental effort (not a rewrite) - each phase adds a JMAP-native path
alongside the existing classic implementation and falls back to it, rather than replacing it. Treat
any "done" claim below as scoped to what's explicitly listed; large parts of the mail code
(`mail_zpush.inc.php`, folder/mailbox administration, `mail_compose.inc.php`/
`mail_integration.inc.php`) are classic IMAP by design, not by omission - see "Deliberately out of
scope" below before proposing to convert them.

## The two backends

| | Real JMAP (Stalwart) | Local shim (plain IMAP) |
|---|---|---|
| Client talks to | Stalwart's real JMAP server directly (`jmap-jam` npm client) | `mail/jmap.php` front controller |
| Server-side JMAP logic | none needed - Stalwart provides it | `EGroupware\Mail\JmapShim` (`mail/src/JmapShim.php`), implements `Mailbox/query`/`Email/query`/`Email/get`/`Email/set` etc. against `Horde_Imap_Client_Socket` directly |
| Message id | Stalwart's real, opaque `Email.id` string | synthetic, built from the real per-mailbox IMAP UID |
| Folder id | Stalwart's real JMAP `Mailbox.id` | the real IMAP folder path, base64-encoded |
| Auth | JMAP session bootstrap token (`mail_ui::ajax_jmapBootstrap()`) | ordinary EGroupware session cookie, same-origin |

Because the shim's ids are UID-based, shim-sourced rows land in the *classic* row-id shape
automatically (see below) - existing actions work with zero extra translation. Stalwart-sourced rows
need an opaque-id-aware code path everywhere they're used.

## Row-id scheme

Two shapes, disambiguated purely by whether the last colon-separated segment is numeric:

- **Classic**: `accountID::profileID::base64(folder)::numericUID`
- **JMAP**: `accountID::profileID::folderID::emailID` (folderID = raw JMAP Mailbox id, emailID =
  opaque JMAP Email.id, never numeric)

`Api\Mail::splitRowID($rowID)` is the single entry point for parsing either shape; it delegates to
`Api\Mail\Imap::splitRowID()` (classic) or the `Api\Mail\Imap\Jmap` override (JMAP-aware). The result
is an `Api\Mail\RowIdParts` object (`api/src/Mail/RowIdParts.php`), not a plain array:

- `profileID`, `folderID`, `emailID`, `is_jmap` are **eager** - free, no I/O, known from the row-id
  string alone.
- `folder` (real IMAP path) and `msgUID` (real numeric UID) are **lazy** - for a JMAP row, reading
  *either* one triggers a real IMAP `SEARCH ... EMAILID <id>` (`Imap\Jmap::emailId2uid()`), and both
  values come back from that *same* search call (cached after first access). There is no way to get
  the real folder path without also paying for the real UID, or vice versa, for a JMAP row.

Callers that only need `emailID`/`folderID`/`profileID` pay nothing extra. Callers that need the real
folder path for any reason (e.g. `reopen()`, `folderExists()`) always trigger the full resolution,
even if they never read `msgUID` directly.

## Server-side architecture - read this before touching `Api\Mail`

Two *different* relationships exist here and they are easy to confuse:

1. **`Api\Mail\Imap\Jmap` really does `extends Api\Mail\Imap`** (plain PHP class inheritance). It
   overrides a handful of methods (`jmapClient()`, `store()`/`copy()` for push side effects,
   `splitRowID()`, `enablePush()`/`pushCallback()`, `emailId2uid()`/`emailId2uidByPath()`) and
   inherits everything else - real IMAP - unchanged.
2. **`Api\Mail` (the `mail_bo` class every app actually calls) is *not* related to `Api\Mail\Imap` by
   inheritance at all.** `Api\Mail::getInstance()` always constructs a literal `new Mail(...)`, never
   a subclass. Backend-specificity lives entirely in the `$icServer` **property** it composes.
   `Api\Mail`'s own methods (`getSortedList()`, `getHeaders()`, `flagMessages()`, `deleteMessages()`,
   `getMessageHeader()`, `getMessageBody()`, `getMessageAttachments()`, `getAttachment()`,
   `getAttachmentByCID()`, `moveMessages()`, ...) call low-level Horde primitives directly on
   `$this->icServer` - none of them are inherited from `Imap`/`Imap\Jmap`.

**Practical consequence**: adding a same-named method to `Api\Mail\Imap\Jmap` does **not** make
`Api\Mail`'s method of that name JMAP-native - it's a silent no-op, since nothing on `Api\Mail` ever
calls it. JMAP-native dispatch for `Api\Mail`'s own methods has to live *inside* those methods,
gated by `$this->icServer instanceof Mail\Imap\Jmap`. This mistake was made once, shipped, and only
caught by live testing (the "JMAP path" simply never fired) - see `feedback-api-mail-composition-not-inheritance`
in the AI memory system if you want the incident detail.

### Dispatch pattern used throughout `Api\Mail`

Every JMAP-native method follows the same shape - a private `jmap<MethodName>()` helper, called at
the top of the real method:

```php
function getMessageHeader($_uid, $_partID = '', ...)
{
    if (($jmapResult = $this->jmapGetMessageHeader($_uid, $_partID, ...)) !== null)
    {
        return $jmapResult;
    }
    // ... unchanged classic implementation below ...
}
```

`jmap<MethodName>()` returns `null` when "not applicable, use classic" (wrong backend, unsupported
option, id shape doesn't match) and a real result (possibly `false`/an empty array, a valid result in
its own right) otherwise. **Automatic dispatch by id shape is only safe for methods that act on an id
already handed to them** (`is_numeric()` reliably distinguishes a real IMAP UID from an opaque JMAP
`Email.id` - never ambiguous). It is **not** safe for methods that *generate* ids, like
`getSortedList()` (its return value flows into several other callers that assume real numeric UIDs
come back) - that one is an explicit opt-in, `Api\Mail::jmapAwareSortedList()`, called only by
callers that can handle an opaque id coming back (e.g. `tracker_mailhandler::check_mail()`).
`getSortedList()` itself never changes behavior for any caller that doesn't explicitly opt in.

### The bail-to-classic trap: silent empty data, not an exception

Several `jmap<MethodName>()` helpers legitimately bail to classic for reasons unrelated to the id's
own validity (a sub-part request, a `text/calendar` part, a TNEF/winmail.dat attachment). If the
opaque JMAP id reaches the classic implementation unresolved, **`Horde_Imap_Client_Ids::add()` does
not error on a non-numeric id - it silently resolves to an empty id set.** The classic "fallback"
then silently returns empty/wrong data instead of a real result, with nothing to indicate failure.

Fix, applied to every fetch method that can bail this way: `Api\Mail::jmapResolveUid($_uid, $_folder)`
(private) + `Api\Mail\Imap\Jmap::emailId2uidByPath($emailId, $folderPath)` (public) - resolves the
real UID via IMAP `SEARCH ... EMAILID` on demand, right before the classic body would otherwise use
`$_uid` directly. Only pays the extra search cost in this rare bail case. **Any new JMAP-dispatched
method added to `Api\Mail` that can bail to classic for a content/option reason (not just an id-shape
reason) must call `jmapResolveUid()` before falling through**, or it will reproduce this bug silently.

## Client-side architecture

This is the part of the project with the biggest user-visible payoff - it's what actually removed
most of the PHP/IMAP round-trips from ordinary mail use (opening the list, reading a message, moving/
flagging/deleting). Everything here runs the same TypeScript code for both backends; the only
backend-specific piece is which server `MailJmap` talks to (Stalwart directly, or `mail/jmap.php` for
the shim).

### Row listing

- `mail/js/jmap.ts` - `MailJmap` class, the JMAP client for both backends (via `jmap-jam` for
  Stalwart, via `mail/jmap.php` for the shim - same client code either way). `MailJmap.fetchRows()` is
  the row-listing entry point.
- `egw.dataRegisterFetch()`/`dataUnregisterFetch()` (`api/js/jsapi/egw_data.js`) - generic NextMatch
  extension point: an app's `.ts` registers a per-prefix row-fetch callback that gets first refusal on
  `dataFetch()`, falling back to the classic `ajax_get_rows()` path only if it returns falsy.
  `MailJmap.fetchRows()` is mail's registered callback for the `mail` prefix.
- `MailJmap.refreshRows()` handles NextMatch's single-row "refresh" fetch (`egw.dataRefreshUID()`,
  fired after row actions and push "update" events) - parses each refreshed row-id back via
  `messageReference()`, groups by profile, does a plain `Email/get` by id (no `Email/query` needed in
  principle - see the local-shim exception below).
- `mail_ui::get_rows()` (the old, fully server-side PHP row-fetch) has been **removed entirely**, not
  just bypassed. Three real, non-obvious things depended on it that had to be ported first:
  1. **NextMatch's row-id cache prefix.** `et2_extension_nextmatch.ts`'s `_setupData()` derives
     `dataStorePrefix` (which `egw.dataFetch()` uses to find the registered callback) from
     `this.options.settings.get_rows`. With no `get_rows` PHP setting, this silently comes up empty -
     rows render as a completely empty list, no error anywhere. Needs a fallback derived from
     `this.options.template` instead (see `feedback-nextmatch-no-get-rows` in the AI memory system -
     applies to any app that drops its `get_rows` setting, not just mail).
  2. **The single-row refresh fetch** (`MailJmap.refreshRows()` above) had no JMAP implementation
     before removal - this was the one remaining real reason `get_rows()` couldn't just be deleted.
  3. **`enablePush()`** - not just a UX nicety. For Stalwart, this is the actual JMAP push-subscription
     registration/renewal (2-day expiry, `api/jmapPush.php`); for plain IMAP, the opt-in Dovecot
     mailbox-metadata push-token mechanism. Ported to `mail_ui::ajax_enablePush()`, triggered
     fire-and-forget from `MailJmap.enablePushOnce()` - once per profile per JMAP-token lifetime
     (~hourly), not on every row fetch like the old code did.

### Message body rendering

- `MailJmap.fetchBody()` fetches `bodyStructure`/`textBody`/`htmlBody`/`attachments`/`bodyValues` in
  one `Email/get` call (`fetchAllBodyValues:true`), sanitizes with DOMPurify, sets the result as
  `iframe.srcdoc` - eliminates the full page-navigation round trip (`loadEmailBody`) the classic path
  used for the common case. Both the preview panel and the "view message" popup share this via
  `MailApp.loadMessageBody()` (`mail/js/app.ts`).
- **Special cases stay server-rendered, detected client-side from `bodyStructure` *before* fetching
  any body content** (`MailJmap.isSpecialCase()`) - no MIME-parsing library needed since JMAP's
  `bodyStructure` already gives full parsed structure:
  - **PGP/MIME** (`multipart/encrypted`) is the one exception that also moved client-side:
    `findPgpPart()` locates RFC 3156's 2nd sub-part (the ciphertext) via JMAP blob download, rendered
    through the existing `mailvelopeDisplay()` plain-text path unchanged.
  - **S/MIME, TNEF/winmail.dat, and calendar meeting-invites** stay routed through the legacy
    `mail_ui::displayMessage()` PHP path - decrypt/decode logic (`Horde_Crypt_Smime`, TNEF decode) is
    unchanged, transport-agnostic; only how the raw bytes reach it changed to JMAP (see server-side
    section above: `Api\Mail\Jmap::downloadBlob()` / `JmapShim::fetchRawMessage()`/`fetchRawPart()`).
  - Deliberately not reimplemented: `Api\Mail\Smime::resolveMessage()` and `JmapShim::structureToHtml()`
    are new, shared orchestration/HTML-assembly - explicitly *not* built by calling into
    `mail_ui`/`Api\Mail`'s existing display methods, to keep this code path independent of the legacy
    one it's replacing pieces of.
- **Inline `cid:` images**: Stalwart downloads the blob directly and uses an object URL; the local
  shim needed a **new JMAP Blob-download endpoint** (`mail/jmap.php`'s `?download=1` route →
  `JmapShim::download()`), since the shim previously had no blob store at all. The shim's blobId is
  self-describing (`urlsafeB64(mailbox) + ':' + uid + ':' + partId`) rather than a real per-account
  blob registry lookup.

### Attachments

- **Listing**: `mail_ui::resolveAttachmentsJmap()`/`jmapAttachmentsToLegacy()` replace the classic
  IMAP-bodyStructure-parse for listing, for both backends.
- **Download**: `MailJmap.downloadAttachment()` (client-side, `client.downloadBlob()` for both
  backends) intercepts the classic download action; `Api\Mail\Imap\Jmap::downloadBlobAccount()`
  (static) serves the `Link::set_data()`-token-based download URL used elsewhere in the UI
  (`mime_data`/`invoice_data`), Stalwart-only - the local shim's existing IMAP-based attachment
  serving stays as-is since there's no protocol win there.
- **winmail.dat/TNEF listing**: `mail_ui::resolveWinmailJmap()` fetches the raw bytes via JMAP and
  decodes via `Mail::tnef_decoder()` (made `static` for this - it never touched IMAP state), building
  the attachment array via `JmapShim::tnefAttachments()` (ported logic, not a call into
  `Api\Mail::getMessageAttachments()`'s TNEF loop). Per-file *download* of a TNEF child still goes
  through the legacy IMAP `Api\Mail::getAttachmentByCID()`/`getAttachment()` path (now JMAP-native
  server-side too, see above).
- **`is_winmail` composite-key discipline**: TNEF children are addressed by a composite key
  (`uid@partID@mimeId`, called `is_winmail`/`winmailFlag` depending on context), not the outer MIME
  part id - `createAttachmentBlock()` (the shared listing/link-generation helper every caller uses,
  including the JMAP-native listing) threads this consistently. Code that forgets to thread it through
  (a real bug class found and fixed in `mail_zpush.inc.php` and `mail_integration.inc.php` during a
  related audit) gets back the raw, still-packed winmail.dat bytes instead of the requested child,
  regardless of JMAP vs classic.

### Bulk actions (move / delete / flag / copy / purge)

- **Explicit selection** (a specific set of checked rows): `MailJmap.moveMessages()`/
  `deleteMessages()` (`Email/set` `mailboxIds`/`destroy`), `copyMessages()` (JMAP PatchObject
  `"mailboxIds/<id>": true`, add-without-removing unlike move), `setSystemFlag()` (read/flagged
  toggle) and the pre-existing custom-label/MDN flag paths. `app.ts`'s `mail_callMove()`/
  `mail_deleteMessages()`/`mail_flagMessages()` intercept with a JMAP fast path, falling back to the
  classic `ajax_*` action for anything not covered.
- **Select-all-matching-filter**: `moveAllMatching()`/`deleteAllMatching()`/`destroyIds()` (paginated)
  and `purgeFolder(profileID, 'trash'|'junk')` (empty trash/junk - reuses `deleteAllMatching()` with a
  filter that has only `selectedFolder` set, matching the classic "delete everything in this folder"
  semantics exactly). Built on the same `filterToQuery()`/`buildFilter()` model the ordinary row
  listing already uses.
- **`read`/`flagged` system-flag toggles were deliberately scoped narrower than labels/custom flags**:
  JMAP-native only for an **explicit selection**, not "select all matching filter" - the classic
  path's `all` handling for these two is filter-aware (clicking "Read" while viewing the Unseen filter
  means "mark this filtered view as read", not a per-row toggle), and replicating that nuance wasn't
  needed to close the reported gap. `toggleForAll()` (the JMAP select-all path used by labels/custom
  flags) was deliberately not extended to `read`/`flagged` - they still route to classic for `all`.
- **`mark_as_deleted` was removed entirely** (not converted) - an explicit call that the "flag as
  deleted" workflow (vs. move-to-trash) was ancient and rarely used. The preference option, the
  branch in `Mail::deleteMessages()`, the `undelete` toolbar action, and the `compress_folder`
  tree-action case are all gone, along with `mail_zpush.inc.php`'s now-dead reference to it.
- `mail_ui::ajax_jmapBootstrap()` supplies `trashFolder`/`junkFolder` so the client can resolve
  "move to trash"/purge targets without a dedicated endpoint.

### Local shim internals (plain IMAP accounts)

- `mail/jmap.php` - thin front controller: session bootstrap, the `?download=1` blob route, and
  `mail_jmap_unauthorized()` (kept as a plain function since `autocreate_session_callback` references
  it by string name). Forwards everything else to `JmapShim::dispatch()`/`JmapShim::session()`.
- `EGroupware\Mail\JmapShim` (`mail/src/JmapShim.php`) - implements just enough of `Mailbox/query`/
  `Email/query`/`Email/get`/`Email/set` (plus RFC 8620 §3.7 result-reference resolution, since the
  client batches requests the same way it would against a real JMAP server) directly against
  `Mail\Account::read($acc_id)->imapServer()` - deliberately bypasses `mail_ui`/`Api\Mail` (mail_bo)
  entirely, talking to Horde's raw IMAP client itself.
- Auth is the ordinary EGroupware session cookie (same-origin fetch) - **never** the real PHP session
  id handed to client JS as a bootstrap token (see `feedback-no-session-id-as-token` in memory - a
  hard rule, not just this project's convention).
- `acc_id="0"` is a reserved, in-PHP fixture/demo account (`JmapShim::demoFixture()`) for client-side
  testing without touching a real mailbox.
- One known, unresolved limitation: blob upload/`Email/import` for the local shim has a real
  limitation (`uploadUrl` has no `{accountId}` RFC 8620 template placeholder) - message *creation* via
  the shim doesn't fully work; query/get/set do. Matters if local-shim message creation is ever made
  JMAP-native (not currently attempted anywhere).

### Client-side-specific gotchas

- **Row shape must match `mail_ui::header2gridelements()`'s convention, not just "have the right
  data"** - `email2row()` (`mail/js/jmap.ts`) has to split `fromaddress`/`toaddress` into a first
  address plus `additionalfromaddress`/`additionaltoaddress` arrays (not one joined string), and
  `ccaddress`/`bccaddress` as arrays - `Et2Email.set_value()`/`mail_preview()` depend on this exact
  shape or recipients render as one garbled string instead of separate tags.
- **Dates need client-side timezone conversion for both backends now, not just Stalwart.** Real JMAP
  servers emit true UTC per RFC 8621; eTemplate's own grid convention is "user-timezone digits with a
  literal `Z` suffix" (not real UTC) - `MailJmap.jmapUtcToUserTz()` (`Intl.DateTimeFormat` with the
  user's IANA timezone preference) does this client-side for both backends uniformly.
  `JmapShim::imapDate()` was changed to emit real UTC too (previously it pre-baked user-timezone
  digits server-side), so both backends now agree on the wire format and share one client-side
  conversion path - don't special-case the shim here again.
- **`egw.jsonq()`/bare `egw.json()` without `.sendRequest()` are unsafe for anything that needs to
  read session-written state back** - see "Known invariants / gotchas" below; this bit the
  eml/message-rfc822 attachment click path specifically (`Api\Link::set_data()` token silently
  dropped, then `Link::get_data()` failing later with a bare native `alert()`).

## Current status (as of 2026-08-09)

**JMAP-native today** (Stalwart accounts; local-shim accounts already had zero extra IMAP cost for
most of this since IMAP was already native there):

- Client-side: row listing, message body rendering (incl. inline `cid:` images, PGP/MIME), bulk
  move/delete/flag (single-row and select-all-matching-filter), folder purge, attachment
  listing/download, copy-to-folder, view raw header, MDN, subject-edit-in-place.
- Server-side `Api\Mail`: `getHeaders()`, `flagMessages()`, `deleteMessages()`, `getMessageHeader()`,
  `getMessageRawHeader()`, `getMessageRawBody()`, `getMessageBody()`, `getMessageAttachments()`,
  `getAttachment()`, `getAttachmentByCID()`, `getMessageEnvelope()`, `moveMessages()` (same-account
  only). Explicit opt-in `jmapAwareSortedList()` for listing/search.
- `tracker` app's `check_mail()` polling pipeline (list → header → body → attachments → flag →
  delete) is JMAP-native end-to-end for Stalwart-backed queues, via `jmapAwareSortedList()` plus the
  automatic dispatch above.

**Known, intentional bail-to-classic conditions** (not bugs, a documented scope boundary): a
sub-part-scoped fetch request, a message containing a `text/calendar` part, a TNEF/winmail.dat
attachment, cross-account or copy-without-delete moves, S/MIME (decrypted server-side, JMAP only for
the raw-byte transport). Each of these falls through to the unchanged classic IMAP implementation,
correctly resolving a real UID first (see "bail-to-classic trap" above).

## Deliberately out of scope

- **`mail_zpush.inc.php`** (ActiveSync) - investigated and declined. Z-Push treats each message's
  `id` as a persisted identity every already-paired device remembers across syncs; that id is a real
  IMAP UID today for both backends. Switching a Stalwart message's effective id to an opaque JMAP id
  would desync every paired device on its next sync. This constraint is structural, not a tooling
  limitation - don't re-propose a wholesale migration without addressing device-sync-id stability
  first.
- **`mail_compose.inc.php` / `mail_integration.inc.php`** - audited (2026-08-09). The obvious-looking
  fix (swap the eagerly-resolved `msgUID` for the free `emailID` in the compose flow) turns out to
  have no benefit: the real IMAP folder *path* is needed regardless of protocol, for `reopen()`,
  `folderExists()`, `isDraftFolder()` (none JMAP-aware), and resolving that path for a JMAP row
  triggers the exact same IMAP search that also produces `msgUID` for free. Swapping the id would
  just trade an already-working classic IMAP FETCH for an equally-costly JMAP call, with no
  round-trip reduction. `Api\Mail::getAttachmentByCID()` *was* a real, worthwhile gap (found during
  this audit, since fixed) - it's called with an opaque id from `Mail::get_mailcontent()`'s
  embedded-attachment fallback (a genuinely live, already-shipped tracker code path), independent of
  whether mail_compose itself changes.
- **Folder/mailbox administration** (`getFolderStatus`, `renameFolder`, `deleteFolder`, `getQuotaRoot`,
  namespace listing, ...) - a separate, larger concern, not started.
- **Making `Api\Mail::reopen()` itself JMAP-aware** - `reopen()` unconditionally does a real IMAP
  folder-status check (`folderIsSelectable()` → `getFolderStatus()` → `getMailboxes()`, real LIST)
  and a real SELECT (`icServer->openMailbox()`), regardless of backend - it's the actual reason
  `mail_compose` can't avoid IMAP even for an all-JMAP-native fetch chain. A real folder-path lookup
  via pure JMAP already exists (`Api\Mail\Jmap::folderId2path()`, a `Mailbox/get` call, cached) and is
  simply not used by anything that also needs `reopen()`. Making `reopen()` lazy for JMAP-backed
  connections is possible in principle (a `jmapAwareReopen()` opt-in, same pattern as
  `jmapAwareSortedList()`) but has 43 call sites across 6 files to consider - scoped, not started.
- **`Api\Mail\Imap\Jmap` as composition instead of inheritance** - a deeper redesign was proposed:
  stop `Jmap extends Imap`, instead compose a lazily-constructed private `Mail\Imap` and proxy via
  `__call()`, so stateful setup (login/reopen/select) can be deferred until a call genuinely needs
  real IMAP, with a single choke point to log/count remaining IMAP dependency. Architecturally sound
  (mirrors the existing `Api\Mail\Sieve\Connection` interface + `ManageSieve`/`Jmap` precedent) but
  **not a safe drop-in**: a full repo sweep found 2 confirmed fatal `TypeError` breaks
  (`mail_acl.inc.php:getSubfolders()`, `mail/src/ApiHandler.php:getVacation()`, both have a real
  `Mail\Imap $imap` parameter type-hint), several silent `instanceof Mail\Imap` breaks (most severe:
  `tracker_mailhandler::check_mail()`'s entire processing block is gated by one), and - the dominant
  risk - 113 property-access call sites relying on `Mail\Imap`'s public properties / magic `__get()`
  (`ImapServerId`, `acc_folder_*`, `acc_imap_*`, ...), which a `__call()`-only proxy would silently
  turn into `null`. The worst single item: `api/src/Mail.php` alone has 74 occurrences of
  `$icServer->ImapServerId` used as a cache key (folder/header/body/status caches) - if that silently
  became `null`, every Stalwart account's caches would collide. A real future project, but multi-session
  and foundational - needs its own `__get()`/`__isset()` proxy design, not just `__call()`, plus fixing
  every confirmed break as part of the same change. Not started.

## Known invariants / gotchas

- **IMAP UIDs are per-mailbox, not per-message.** A message moved/copied to another folder gets a
  brand-new UID there. Never verify a move by checking whether the destination folder contains a row
  whose id ends in the *same* UID as before - it won't, by design. Verify by `Message-ID` header,
  subject, or (for a real JMAP server) the JMAP `Email.id`, which genuinely is stable across a move.
- **RFC 8621's `Email` object is already pre-classified server-side** - `textBody`/`htmlBody`/
  `bodyValues`/`attachments` come back already decoded and separated. There's no need to replicate
  `Horde_Mime_Part`/`bodyStructure`-walking logic for the JMAP path; that's what made the original,
  much larger effort estimate for `getMessageBody()`/`getMessageAttachments()` wrong once actually
  attempted.
- **Stalwart's IMAP-compatibility layer has an indexing lag for freshly-JMAP-imported messages.**
  `Email/get`/`Email/query` (real JMAP) are immediately consistent after `uploadBlob()`+
  `emailImport()`; the IMAP-compat `SEARCH ... EMAILID` extension (used by `emailId2uid()`) may not
  find that same message for several seconds, while it works instantly for a pre-existing message.
  This is a live-test-environment characteristic, not a code defect - use a pre-existing message id
  when trying to prove an id-resolution code path works, not a message created in the same test run.
- **`egw.jsonq()` is unsafe for handlers that write session state** (e.g. `Api\Link::set_data()`) -
  its `api.queue` batching commits the session early so other queued calls aren't blocked, silently
  discarding any session write that happens after. `egw.json().sendRequest()` does *not* have this
  problem (no batching, no early commit) and remains safe for session writes, but is deprecated in
  favor of `egw.request(menuaction, params).then(...)` for new code - prefer `egw.request()`, but the
  actual hard rule is just "never `jsonq()` a handler that writes session state".

## Test coverage

- `vendor/bin/phpunit --bootstrap doc/phpunit_bootstrap.php mail/tests/` (48 tests) +
  `api/tests/Mail/CustomLabelsTest.php`.
- `tracker/tests/MailHandlerTest.php` (separate `tracker` git repo, gitignored from this repo - run
  with `vendor/bin/phpunit -c doc/phpunit.xml tracker/tests/MailHandlerTest.php`). Covers
  `process_message2()`/`is_automail2()`/`forward_message2()` via a mock/double `Api\Mail`, not the
  outer `check_mail()` polling loop itself (still untested).
- Live dev-install testing account cheat sheet (`boulder.egroupware.org`): `acc_id=1` is
  Stalwart/real-JMAP, `acc_id=85` is the plain-IMAP account exercising the local shim. Don't assume
  `acc_id=42` still works - it's been retired for this purpose.

## Where to look next

- `api/src/Mail.php` - `jmap<MethodName>()` helpers, `jmapResolveUid()`, `jmapMessageIds()`,
  `jmapAwareSortedList()`.
- `api/src/Mail/Imap/Jmap.php` - Stalwart connection overrides, `emailId2uid()`/`emailId2uidByPath()`,
  `splitRowID()`.
- `api/src/Mail/Jmap.php` - low-level JMAP protocol client (`emailGet()`, `emailQuery()`,
  `downloadBlob()`/`uploadBlob()`, `folderId2path()`, ...).
- `api/src/Mail/RowIdParts.php` - the lazy `folder`/`msgUID` resolution wrapper.
- `mail/src/JmapShim.php` + `mail/jmap.php` - local shim server-side logic and front controller.
- `mail/js/jmap.ts` - client-side `MailJmap` class.
- `tracker/inc/class.tracker_mailhandler.inc.php` (separate repo) - `check_mail()`'s JMAP-aware
  polling pipeline.
