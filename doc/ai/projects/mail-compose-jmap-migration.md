# Mail: move compose to client-side JMAP-first, S/MIME + TNEF as server-side services

## Status: Step 0 done + live-verified against real Stalwart (2026-08-27), NOT yet committed.
Companion to [[mail-jmap-imap-inversion]].

**Step 0 done (2026-08-27):** `Api\Mail\Jmap\Identity`/`EmailSubmission` type classes built (thin
passthroughs, `JMAP_SUBMISSION` capability registered on `Api\Mail\Jmap\Http`). Rather than a
throwaway CLI script, wired directly into `mail/src/ApiHandler.php`'s existing REST `/mail` send
endpoint as a narrowly-guarded parallel branch (real-JMAP accounts only, no attachments/reply yet -
everything else still falls through to the existing classic `mail_compose::send()` path unchanged) -
ralf's suggestion, doubling as both the live-verify mechanism and real progress on a genuine
consumer, not just a smoke test. **Live-verified end-to-end against the real Stalwart test account**
(acc_id=1, `nonadmin` user) via direct `curl` calls to the REST endpoint: `Identity/get` found the
right identity, Drafts/Sent mailboxes resolved by role, `Email/set` created the draft,
`EmailSubmission/set` submitted it with the Drafts→Sent `onSuccessUpdateEmail` patch - confirmed
delivered to both an external (`rb@egroupware.org`) and local (`ralf@boulder.egroupware.org`)
recipient, and the Sent-folder copy correctly landed with the right content. One live bug found+
fixed: the Sent copy needs `keywords/$seen => true` in the `onSuccessUpdateEmail` patch too (found
via ralf noticing it showed unseen) - a sent message isn't new/incoming, same `\Seen` convention
`saveAsDraft()` already uses for drafts.

Ralf's framing: compose is the single biggest area of the mail app not yet touched by any JMAP work
(folder tree, message list/browsing, and Phase 1's `Api\Jmap` hierarchy inversion all bypass it
entirely). It's "currently full server-side and not (yet) using any JMAP for sending, nor the mail
composition from a previous mail (reply, forwarding etc.)". The question to resolve: should compose
stay server-side (continuation of the Phase 1 style - restructure PHP internals), or move
client-side JMAP-first (the strategy that would make mail eventually a complete JMAP-first
rewrite)? Some parts need to stay **server-side** as callable services rather than move to the
browser: S/MIME de/encryption (private key material) and winmail.dat/TNEF decryption.

**Decided direction (ralf, 2026-08-27): client-side JMAP-first**, matching the pattern already
proven by the folder-tree and message-list migrations - JMAP-native in the browser via the existing
`MailJmap` client (`mail/js/jmap.ts`), with `mail_compose.inc.php` staying in place as the classic
fallback for when JMAP isn't reachable (same pattern `FolderHandler.php` already is for folders).

## Current state (researched 2026-08-27, 3 parallel investigations)

### Server-side (`mail/inc/class.mail_compose.inc.php`, 4219 lines) - 100% classic IMAP+SMTP today

Confirmed via grep: **zero** references to `instanceof Mail\Imap\Jmap`/`jmapClient`/`JmapShim`/
`Api\Mail\Jmap` anywhere in this file. Every path is raw IMAP fetch/append/flag + Horde SMTP.

- **Entry/dispatch**: single `compose()` method (332), `getComposeFrom()` (1651-1777) switches on
  `$_GET['from']` - `composefromdraft`/`composeasnew` → `getDraftData()`; `reply`/`reply_all`/
  `reply_single`/`reply_attachments` → `getReplyData()`; `forward` (`inline`/`asmail`, multi-source)
  → `getForwardData()`; `merge` → `Mail::importMessageToMergeAndSend()` then recurses as
  `composefromdraft` (this is the same method `Storage/Merge.php` calls - see "side benefit" below).
- **Reply/forward loading** (`getReplyData()` 2309-2538, `getForwardData()` 2026-2110): IMAP
  part-ID fetch (`getMessageEnvelope()`/`getMessageBody($_uid, $_partID, ...)`), HTML quoting via
  `<blockquote type="cite">`, plain-text quoting via `BodyDecoding::wordwrap()` with
  signature-stripping heuristics, inline-image `cid:` rewriting via `BodyHandler::
  resolveInlineImages()`. Attachment carry-forward is explicit per-attachment
  (`addMessageAttachment()` 2158, uid/partID/folder-addressed), decoding TNEF via
  `decode_winmail()` (2779/2784) when the source attachment is a `winmail.dat`.
- **Drafts** (`saveAsDraft()` 3032-3106): builds MIME via `createMessage()`, raw IMAP
  `appendMessage()` with `\Seen \Draft` flags into `getDraftFolder()`. Client-triggered autosave
  (JS timer calls `saveAsDraft` with `$action='autosaving'`), skips oversized attachments.
- **Attachments**: `addAttachment()` (2110-2153, temp-dir or `vfs://` staged, "share instead of
  attach" via `Vfs\Sharing::ATTACH`), `getAttachment()` (2172-2200) serves it back for preview.
  Cross-app attaching is outbound-only from this file's perspective - `Api\Hooks::single
  ('mail_import')` + `Framework::popup()` (3600-3618) hands a temp `.eml` to the target app;
  `mail_integration.inc.php`'s `integrate()` is the consumer, not called from here.
- **Addressing**: `resolveEmailAddressList()` (2999, expands distribution lists), identity via
  `Mail\Account::read_identity()`/`get_preferred_identity()` (4184).
- **Send** (`send()` 3108-3416, the largest method): builds MIME, optional S/MIME sign/encrypt (see
  below), `Api\Mailer::send()` (SMTP, 3390) inside try/catch, then raw IMAP `appendMessage()`
  (3450/3487) to Sent + any configured folders - **identical for every backend**, no JMAP
  `EmailSubmission` anywhere. Draft cleanup + answered/forwarded flagging follow (3505-3563).
- **S/MIME**: `Mail\Smime extends Horde_Crypt_Smime` (`api/src/Mail/Smime.php:20`) - already
  self-contained (cert gen/CSR/encrypt/sign/decrypt-with-candidate-certs). Hooked at `_encrypt()`
  (4096), called from `send()` (3335-3363) only when the user toggles `smime_sign`/`smime_encrypt` -
  opt-in per send, applied to the built `$mail` object before `Mailer::send()`. **Already the most
  service-like piece of the file.**
- **TNEF/winmail.dat**: receive-side only (confirmed via grep - no outgoing TNEF encoding exists) -
  narrow decode-on-demand utility (`decode_winmail()`), only touched when carrying forward a
  reply/forward's original attachments.

### Client-side (`mail/js/compose.ts`, 758 lines) - thin server-postback controller, little to reuse

- Owns almost no real logic: UI-only field-expander/toolbar state, and one interesting existing
  precedent - **Mailvelope (PGP) client-side encrypt-before-submit** (`submitAction()` 466-489:
  encrypts body in-browser via `mailvelope_editor.encrypt()`, then still does a full ETemplate2 form
  `submit()`). Only two explicit `egw.json()` calls in the whole file, both
  `mail.mail_compose.ajax_saveAsDraft` (640/661) - everything else (including the actual send) is a
  full-form ETemplate2 postback, server re-renders the whole screen each time.
- **No client-side attachment blob upload** - `uploadStart()`/`uploadFinish()`/`vfsUpload()`
  (69-132) wrap a classic upload widget whose real work happens via full-form submit after browser
  upload completes. `downloadBlob` is used elsewhere in `mail/js/` (reading), `uploadBlob` is used
  nowhere yet.
- **Reply/forward content is never fetched client-side** - server pre-renders quoted body/headers
  into the initial ETemplate2 content before the compose window opens.
- **No draft state tracked client-side** (no draft-id/dirty-flag/autosave-timer object) - `saveAsDraft()`
  round-trips the entire form content every time.
- **The real reusable asset**: `mail/js/jmap.ts`'s `MailJmap` class (3861 lines) - one jmap-jam
  client per account, already working uniformly across both backends (real-JMAP/Stalwart and
  IMAP-shim via `mail/jmap.php`), with a mature reading/mutation API (`getRows`/`fetchBody`/
  `fetchRawHeader`/`downloadAttachment`/`resolveInlineImages`/`setLabel`/`setSystemFlag`/
  `moveMessages`/`deleteMessages`/mailbox CRUD). **Zero compose-related methods today** - no
  `Email/set` create, no `EmailSubmission`, no `Identity` fetch. A rewrite extends this class/
  pattern rather than inventing new client-side JMAP plumbing.

### JMAP capabilities (RFC 8621) and what Phase 1 already built

`Email/set` create (mailboxIds → Drafts, `$draft` keyword) + attachments via blob ids; `Identity/get`
(§6, from/replyTo/bcc/signature per identity); `EmailSubmission/set` (§7, `{emailId, identityId,
envelope?}`, `onSuccessUpdateEmail`/`onSuccessDestroyEmail` to relocate/remove the Drafts copy after
submission - SMTP relay + Sent-folder placement happen server-side, invisible to the client); Blob
upload (RFC 8620 §6.3) for attachments.

- **Already reusable**: `Api\Jmap\Http::uploadBlob()`/`downloadBlob()` (`api/src/Jmap/Http.php:
  479-504`) are fully generic Core, work today for real-JMAP attachments with zero new code.
  `Api\Mail\Jmap\Imap::roleFor()` (597-628) already resolves RFC 8621 roles (`drafts`/`sent`/etc.)
  from IMAP SPECIAL-USE or the account's configured folders, for **both** backends - client-side
  code can already find the Drafts mailbox by `role` today, no new work needed.
- **Not started at all**: `api/src/Mail/Jmap/Http.php:49-54`'s `$types` registers only `mailbox`/
  `email`/`thread`/`quota` - no `Identity`/`EmailSubmission` type class exists on either backend.
- **IMAP-shim gap, concretely**: `Imap::emailSet()` (1612) handles only `update`/`destroy` - no
  `create` branch at all (confirmed via its own docblock). The only "add a message" primitive is
  `emailImport()` (1792, raw-MIME import - IMAP-APPEND shaped, not incremental-property-patch
  shaped like a real JMAP draft create). **Zero SMTP/Mailer references anywhere in `Imap.php`** -
  sending is completely absent from the shim today. A shim-side `EmailSubmission/set` emulation
  needs to be built new: locate the draft's MIME (via blob or `emailImport()`-created message),
  `Api\Mailer::send()` over the existing `Api\Mail\Smtp` transport, `emailImport()` a Sent-folder
  copy (replicating `onSuccessUpdateEmail`), then clean up/flag the Drafts copy (replicating
  `onSuccessDestroyEmail`). Every piece this depends on (SMTP transport, Mailer, role resolution)
  already exists - it's wiring, not new low-level plumbing, but is real work.

## Target architecture

**Two-tier split, matching ralf's framing:**

1. **Client-side JMAP-first**: everything that's pure data manipulation against the account's
   mailbox - drafting (create/update/delete), addressing/identity selection, attachment
   management, reply/forward loading + quoting, inline-image resolution, and the final send
   (`EmailSubmission/set`). Uniform client code against both backends, same as `MailJmap` already
   achieves for browsing.
2. **Server-side services**: S/MIME sign/encrypt/decrypt and TNEF/winmail.dat decode stay
   server-side (private key material and legacy binary decoding have no business in the browser),
   but get **extracted from `mail_compose.inc.php`'s internal state** into narrow, stateless,
   blob-in/blob-out (or MIME-in/MIME-out) callable endpoints the client invokes explicitly at the
   right moment - not embedded deep in a send()/getForwardData() call chain. Both are already close
   to this shape today (`Mail\Smime` is already self-contained; TNEF decode is already a narrow
   utility) - this is extraction/exposure work, not a redesign.

### New pieces needed

- **`Identity` type class** (both backends) - real-JMAP: thin passthrough to Stalwart's own
  `Identity/get` (same pattern as Phase 1's `Mailbox`/`Email`/etc.). IMAP-shim: new - synthesize
  identities from EGroupware's existing `Mail\Account`/`read_identity()`/`get_preferred_identity()`
  config (no JMAP concept exists there today, but the underlying data already does).
- **`EmailSubmission` type class** - real-JMAP: thin passthrough. IMAP-shim: the one genuinely new
  substantial piece (see gap analysis above) - SMTP send + Sent-append + Drafts cleanup emulation.
- **`Email/set` create path on the IMAP-shim** (`Imap::emailSet()` currently update/destroy-only) -
  **open question, not decided**: does draft *editing* need true incremental create/update
  (matching real JMAP semantics exactly), or is "`emailImport()` a whole new MIME message, delete
  the old draft" an acceptable, much simpler first version? Leaning toward the latter for a first
  cut - drafts are already whole-message-replace today via `saveAsDraft()`'s raw `appendMessage()`,
  so this isn't a regression, just carrying the existing semantics into the new shape.
- **Attachment blob storage on the IMAP-shim** - `uploadBlob()`/`downloadBlob()` are
  real-JMAP-HTTP-only (RFC 8620 Core over `Api\Jmap\Http`); the shim has no equivalent blob concept.
  Needs a temp-vfs-backed blob-id scheme (mirrors what `addAttachment()`'s temp/vfs staging already
  does server-side today) feeding into `emailImport()`'s MIME assembly at send/save-draft time.
- **S/MIME service endpoint** - extract `Mail\Smime`'s existing sign/encrypt/decrypt calls out of
  `send()`'s inline hook into a standalone ajax/JSON method: given a blobId (or raw MIME) plus
  cert/passphrase context, returns the signed/encrypted result. Client calls this explicitly as a
  pre-`EmailSubmission` step when the user has S/MIME toggled on - works identically for both
  backends since it operates on MIME/blobs, not on the IMAP connection.
- **TNEF service endpoint** - extract `decode_winmail()` into a standalone callable: given a
  winmail.dat attachment reference (blobId or message+part), returns the decoded sub-attachment
  list. Called client-side only when a `winmail.dat` attachment is encountered while loading a
  reply/forward's original message (or, per current receive-side-only usage, potentially reused for
  plain message display too - not scoped here).
- **Client-side compose rewrite** (`mail/js/compose.ts` + extensions to `mail/js/jmap.ts`'s
  `MailJmap`): real draft/attachment/addressing state management, reply/forward loading via
  `Email/get` + client-side quoting (HTML blockquote / text wordwrap - portable, not IMAP-coupled
  logic) + inline-image resolution via blob download URLs (attachments are already blobId-addressed
  in JMAP shape, so `cid:` rewriting becomes "point at the blob URL" instead of server-side
  part-ID rewriting), attachment upload via `uploadBlob()` (shim needs the new blob scheme above),
  save-as-draft via `Email/set`, send via [S/MIME service if toggled →] `EmailSubmission/set`.

### Side benefit

`getComposeFrom()`'s `merge` branch already calls `Mail::importMessageToMergeAndSend()` - the same
method `Storage/Merge.php` calls (flagged in the Phase 2 consumer screening as blocked on the same
fetch/send problem). Building `EmailSubmission`/blob-upload infra for compose is very likely the
shared infrastructure that later unblocks `Storage/Merge.php` too, not a separate effort.

## Ralf's architecture framing (2026-08-27) - confirms/refines the above

Current controller shape, as ralf sees it: `mail_compose.compose()` is "a pure client-side
triggered action acting on a selected email (reply, forward etc) or none (compose new)". For
everything other than plain new-message compose, it fetches one source email and derives what the
user edits from it ("fetch + derive"). Optional file attaching is currently server-side upload or
from vfs. Then: composition of the final mail to send **and** store in the Sent folder. Explicit
principle for the migration: **break the implementation into small testable parts, each leaving a
working result** - not a big-bang rewrite. This directly confirms new-message-compose (no source
email, no "fetch + derive" step) as the smallest, simplest first slice - reply/forward is strictly
additive on top of it, not a parallel track.

## Decisions (2026-08-27)

- **Step 0 backend order: real-JMAP (Stalwart) first.** Thin passthrough to Stalwart's own
  `Identity`/`EmailSubmission` proves the client-side flow end-to-end with the least new server
  code; the IMAP-shim's much larger new-emulation piece comes after the shape is proven, not
  before. (Trade-off accepted: Phase 1 found several bugs only via live cross-backend testing, so
  the shim will get its own careful live-verification pass once built, not be assumed correct by
  analogy.)
- **IMAP-shim draft semantics: reimport-and-replace**, not incremental update. Matches
  `saveAsDraft()`'s existing whole-message-replace behavior exactly - no semantics change, smaller
  first version. Revisit true incremental update later only if a real need surfaces.
- **`EmailSubmission`/blob infra designed for both compose AND `Storage/Merge.php` from day one** -
  not compose-scoped-only. Concretely: keep the `EmailSubmission`/blob-upload interface shaped
  around "submit a MIME message for delivery + Sent-folder placement", not compose-specific
  concepts (draft id, compose-window state) - so `Storage/Merge.php`'s
  `importMessageToMergeAndSend()` can call the same primitive later without a second design pass.

## Rollout strategy: parallel, not fallback (ralf, 2026-08-27)

Not "JMAP-first with a classic fallback triggered on failure" (the folder-tree migration's
pattern) - instead the new client-side JMAP compose is a genuinely **separate, parallel entry
point** alongside the existing classic one, both fully available at once: a mail can be opened in
either the old server-side-controlled compose or the new one, user's/tester's choice. Two direct
benefits: (1) trivial side-by-side comparison while building each step, instead of having to force
a JMAP-down condition to see the old behavior; (2) existing test users on master keep using the
proven classic path completely undisturbed while the new one is still being built out - no
regression risk to today's users at any point during this whole migration. Removes the need for any
"detect JMAP-unreachable, fall back" logic/design work entirely - that open question from the first
draft of this doc is dropped, not deferred. Cutover (making the new path the default, then
eventually retiring the classic one) becomes a separate, later decision made only once the new path
has fully proven itself feature-complete - not part of this phasing at all.

## Phasing - small testable slices, each with a working result

Given this is the largest, most send-reliability/security-sensitive area touched by any of this
work so far, and per ralf's explicit small-testable-parts principle: every step below should be
independently live-verifiable side-by-side against the existing classic compose before the next
step starts.

0. **Real-JMAP `Identity` + `EmailSubmission` type classes** (`api/src/Mail/Jmap/Identity.php`,
   `EmailSubmission.php`, both thin passthroughs via `Api\Jmap\Type`'s existing generic `get`/
   `set`) + registering them in `Api\Mail\Jmap\Http`'s `$types`. No client-side change yet -
   testable server-side/via a script: fetch identities for a real Stalwart test account, submit a
   trivial pre-existing draft, confirm it sends + lands in Sent.
1. **Client-side new-message compose, real-JMAP only, plain text, no attachments, no S/MIME**:
   `Email/set` create (native on real-JMAP, no new server code) + `EmailSubmission/set` send, wired
   into a minimal new compose.ts flow (or a throwaway test harness first, if that's faster to
   verify against Stalwart before touching the real UI). Working result: send a plain email from
   the browser through the new path against the Stalwart test account, verify delivery + Sent copy.
2. **IMAP-shim `EmailSubmission` emulation** (the one genuinely new substantial backend piece -
   locate draft MIME → `Api\Mailer::send()` via `Api\Mail\Smtp` → `emailImport()` a Sent-copy →
   Drafts cleanup) + shim `Identity` synthesis from `Mail\Account`. Working result: same plain-text
   new-message send, now working against an IMAP-shim test account too.
3. **Attachments**: blob upload for real-JMAP (already exists via `Api\Jmap\Http::uploadBlob()`,
   just needs client wiring) then the new shim blob scheme. Working result: send with an attached
   file, both backends.
4. **Client-side reply/forward "fetch + derive"**: `Email/get`-based original-content loading,
   client-side quoting, inline-image resolution via blob URLs, attachment carry-forward. Working
   result: reply/reply-all/forward produce a correctly pre-filled compose window and send
   correctly, both backends.
5. **Draft save/reopen**: `Email/set` create+update on real-JMAP, reimport-and-replace on the shim.
   Working result: save mid-edit, close, reopen, edit again, send - both backends.
6. **S/MIME service extraction + client wiring**: pull `Mail\Smime`'s existing logic out of
   `send()`'s internals into the standalone endpoint, client calls it before `EmailSubmission/set`
   when toggled on. Working result: signed/encrypted send works through the new path.
7. **TNEF service extraction + client wiring**: pull `decode_winmail()` out similarly, called when
   step 4's attachment carry-forward meets a `winmail.dat` source attachment.
8. **`Storage/Merge.php` adoption**: switch mail-merge send to the same `EmailSubmission`/blob
   primitive built in steps 0-3, now that it's proven via compose.
9. **Retire classic compose** - the last step, not before: once the new path has full feature
   parity and has been used regression-free side-by-side with the classic path for a real stretch
   of time. Any regression found along the way is debugged by opening the same mail in both compose
   windows and comparing directly, made possible precisely because the classic path was never
   touched/removed earlier.
