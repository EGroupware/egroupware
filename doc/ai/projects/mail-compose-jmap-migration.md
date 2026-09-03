# Mail: move compose to client-side JMAP-first, S/MIME + TNEF as server-side services

## Status: Steps 0, 1, 3, and (out of order) the draft-save half of Step 5 done + live-verified
against real Stalwart (2026-08-27); Step 4's reply, reply-all, reply-with-attachments (incl.
attachment carry-forward), single-message inline forward, forward-as-attachment (single or
multiple messages), "compose as new", and inline-image resolution for the quoted body (incl. its
own send/draft-save side - blob re-upload, caching, and now a proper text/plain alternative) are
ALL done + live-verified (2026-08-31, incl. against the IMAP-shim backend - see its own write-up
below). **Step 2 (IMAP-shim EmailSubmission emulation) is now done + live-verified too
(2026-08-31)** - see its own write-up and the "Step 2 live-testing fixes" section below for the 4
real bugs found getting there. Step 4 is now functionally complete except reply-all/forward into an
already-open compose popup (the `setCompose()` case, deliberately deferred - see its own write-up
below).

**Step 2 (IMAP-shim EmailSubmission emulation) built + live-verified 2026-08-31 - real SMTP
send + real mailbox mutation, confirmed working (draft-save, actual send, Bcc handling, old-draft
cleanup, attachments).** Full design write-up below; the 4 bugs found live-testing it are their own
section further down ("Step 2 live-testing fixes"). `mail/js/jmap.ts`'s `resolveComposeContext()`
no longer throws `JmapUnsupportedBackendError` for `token.isLocal` at all, so `sendNewEmail()`/
`saveDraft()` now route shim accounts through this new server-side code instead of falling back to
classic. `uploadAttachment()`'s own `token.isLocal` throw was ALSO removed once the shim's own
`Imap::upload()` blob endpoint was confirmed working - see "Direct-to-JMAP attachment upload"
below for the full story (a genuinely new locally-staged attachment is fully JMAP-native for both
backends now, no remaining classic-fallback gap here).

**Design, from research + a live design discussion with ralf**: plain IMAP has no "create a
message from JSON properties" or "submit an existing message" capability at all - both had to be
built from scratch server-side, in `Api\Mail\Jmap\Imap` (there is no real JMAP server to pass
through to for the shim, unlike `Api\Mail\Jmap\Http`'s pure-passthrough `EmailSubmission`/
`Identity` classes for Stalwart).
- **`Email/set` 'create'** (previously silently dropped `$args['create']` entirely - `emailSet()`
  only ever handled update/destroy) - new `Imap::buildMailerFromEmailProperties()` translates RFC
  8621 Email properties (to/cc/bcc/subject/inReplyTo/references/bodyValues + either the
  htmlBody/textBody shortcut or a full bodyStructure) into `Api\Mailer` builder calls
  (`setFrom()`/`addAddress()`/`setBody()`/`setHtmlBody(html, null, false)`/`addStringAttachment()`/
  `addEmbeddedImage()`), the shim's own equivalent of classic `mail_compose::createMessage()`.
  `bodyStructure` is walked with a single generic recursive walker (a leaf with a `blobId` is an
  attachment/inline image, a leaf with a `partId` and no `blobId` is body text, looked up in
  `bodyValues`) - correctly handles every nesting depth `draftEmailProperties()` (mail/js/jmap.ts)
  can produce (multipart/mixed > multipart/related > multipart/alternative) without needing
  separate cases per shape. The finished Mailer's `getRaw()` gets appended into the target mailbox
  via a new `Imap::appendRawMessage()` helper, extracted (no behaviour change) from the existing
  `Email/import`/`emailImport()`'s own append logic so both can share it without a wasteful
  "upload as a blob, then immediately read it straight back" round trip.
- **`EmailSubmission/set`** (didn't exist in `Imap::dispatch()`'s switch at all - fell through to
  `Unsupported method` for every shim account). Ralf's explicit design call, after discussion: reuse
  the SAME message already created by `Email/set create` for both the actual SMTP transmission and
  the Sent-folder copy, rather than rebuilding it a second time from the client's original create-
  time properties (nothing server-side retains those anyway once the message is stored) - re-fetch
  the Draft's own JMAP properties via the EXISTING `emailGet()` (identical to any other `Email/get`
  call), rebuild via the same `buildMailerFromEmailProperties()`, `send()`, THEN reuse that SAME
  Mailer's own `getRaw()` (not a second rebuild) for the Sent copy - guaranteeing it always exactly
  matches what was actually transmitted, mirroring classic `mail_compose::send()`'s own
  `$mail->send(); ... $mail->getRaw()` sequence exactly. Old Draft is deleted outright afterward
  (not "moved" - the freshly re-serialized, just-sent Mailer's own bytes are the correct copy, not
  necessarily byte-identical to the stored draft).
- **Bcc handling** (ralf raised this explicitly, a real correctness/privacy question): a compliant
  JMAP server strips `Bcc:` from what's actually transmitted (while still using those addresses as
  SMTP envelope recipients) but keeps the full header in the Sent-folder copy of the same Email
  object - standard email convention, not JMAP-specific. Found the exact existing mechanism for
  this already used by classic `mail_compose.inc.php`: `Api\Mailer::forceBccHeader()` ("normally
  Bcc is only added to recipients while sending, but not added visible as header... should only be
  called AFTER calling send, or when NOT calling send at all") - confirmed classic calls it
  identically: right before `getRaw()`+`appendMessage()` for a pure draft-save (no `send()` at
  all), or right AFTER `$mail->send()` for an actual send. `emailSubmissionSet()` follows the exact
  same ordering: `send()` first (Horde's own already-correct Bcc-stripping-for-transmission kicks
  in automatically, same as classic has always relied on), `forceBccHeader()` after, then
  `getRaw()` for the Sent copy.
- **`from`/identity bug caught before live-testing**: the first draft of
  `buildMailerFromEmailProperties()` took an explicit `$identityId` param and resolved it via
  `Account::read_identity()` - but RFC 8621's Email object has no separate "identity" concept of
  its own, and `draftEmailProperties()` (mail/js/jmap.ts) always sets a real `from` property
  directly from whichever identity the "From" dropdown actually has selected. Using
  `$identityId`/the account's own default identity instead would have silently ignored the user's
  own "From" selection for every shim send. Fixed: `buildMailerFromEmailProperties()` now reads
  `$email['from']` directly when present (both for the original create AND the resubmit, since
  `emailSubmissionSet()`'s own re-fetch via `emailGet()` naturally carries the Draft's already-
  stored From header through unchanged) - the `$identityId` param was removed entirely, not just
  left unused.
- **No `mailboxId` on EmailSubmission/set** (not a standard property, the client doesn't send one) -
  the Draft's current mailbox, its Sent target, and the flags to set on the Sent copy are all
  derived from `onSuccessUpdateEmail`'s own patch object instead (`mailboxIds/<id>: null` = "the
  mailbox being removed from", i.e. wherever it currently lives; `mailboxIds/<id>: true` = target;
  `keywords/<x>: true` = flags for the Sent copy) - reusing data the client already sends for this
  exact purpose rather than inventing a new, non-standard field.

**Live-tested 2026-08-31, all green**: draft-save (create + `$seen`/`$draft` flags + old-draft
destroy-on-resave), an actual self-addressed send (Sent-folder copy headers/body correct, Bcc
present in storage but NOT in the actually-received copy, old Draft deleted afterward), and sending
with a genuinely new locally-staged attachment (see "Direct-to-JMAP attachment upload" below).

**Step 2 live-testing fixes (2026-08-31)** - 4 real bugs, found in immediate sequence against a
shim account, fixed as they came up:
1. `appendRawMessage()` (the new helper `emailSet()`'s 'create' handling and
   `emailSubmissionSet()` both call) was extracted with the WRONG type hint on its `$mailbox`
   param - `\Horde_Imap_Client_Mailbox`, when `hordeMailbox()` (the only thing that ever produces
   the value passed in) actually returns a plain `string`. First error hit on the very first
   SaveAsDraft test. Fixed the type hint (and its docblock) to `string`.
2. Immediately next: `Argument #3 ($raw) must be of type string, resource given` -
   `Api\Mailer::getRaw()` defaults to `$stream=true` (returns a stream resource, for
   `Horde_Mime_Mail`'s own internal use), but `appendRawMessage()` needs a plain string. Fixed both
   call sites (`emailSet()`'s create handling, `emailSubmissionSet()`'s Sent-folder append) to call
   `getRaw(false)`.
3. Saved drafts showed up unread. Classic `mail_compose.inc.php` always saves a draft with
   `'\Seen \Draft'` flags (never leaves it unread) - `mail/js/jmap.ts`'s `createDraftEmail()` only
   ever sent `keywords: {'$draft': true}`, missing `$seen`. Fixed by adding `'$seen': true` too -
   this is a client-side gap affecting both backends equally, not shim-specific (real JMAP has the
   same "IMAP APPEND with no `\Seen` flag creates an unread message" semantics).
4. Repeated SaveAsDraft clicks kept accumulating NEW drafts instead of replacing the old one.
   `saveDraft()`'s own "destroy the previous draft copy" `Email/set destroy` call never included the
   shim's own local-only `mailboxId` extension (real JMAP needs no such hint to resolve an id to
   destroy; the shim does, to know which IMAP folder to search) - `emailSet()` threw
   `InvalidArgumentException` for it, silently swallowed by `saveDraft()`'s own best-effort
   `catch`. Fixed by passing `mailboxId: draftsId` on that destroy call, but ONLY for `token.isLocal`
   accounts (a real JMAP server might reject an argument it doesn't recognize).
5. The actual send test's Sent-folder copy was badly malformed: an empty leading
   `application/octet-stream` part, then the text/plain AND text/html bodies both showing up as
   separate `Content-Disposition: attachment; filename=attachment` parts instead of a real message
   body - and the message never arrived (almost certainly rejected as malformed/spam).
   `buildMailerFromEmailProperties()`'s `$collect()` walker used `empty($part['blobId'])` as its
   "is this body text or an attachment" test, but `bodyPartToJmap()` (`emailGet()`'s own RFC
   8621-correct behaviour) sets a `blobId` on EVERY part, body text included - RFC 8621 gives every
   body part a blobId for individual-part download, not just attachments. So whenever the email
   came from a re-fetch (`emailSubmissionSet()`'s path, which calls `emailGet()`), both real body
   parts fell into the attachments bucket instead of `setBody()`/`setHtmlBody()`. Fixed by dropping
   the `blobId`-emptiness check entirely - `isset($bodyValues[$partId])` alone is the correct,
   already-exclusive signal (`emailBodyFields()` only ever populates `bodyValues` for the text/html
   body partIds, explicitly excluding them from its own attachments loop).

**Direct-to-JMAP attachment upload built + live-verified 2026-08-31 (ralf: "yes, go ahead with (a)
for both backends")** - a genuinely NEW locally-selected attachment (paperclip button or drag/drop)
now uploads straight to its JMAP blob store the moment it's added, for BOTH backends, never through
the classic chunked-upload-to-EGroupware-temp-storage-then-postback path at all (that postback -
`this.et2.getInstanceManager().submit()` - was found live to silently discard unsent body/mimeType
edits by re-running `bootstrapReply()` from scratch, same class of bug already fixed for the
mimeType toggle). `uploadAttachment()`'s `token.isLocal` throw was removed too (the shim gained a
matching `Api\Mail\Jmap\Imap::upload()` blob endpoint in Step 2), so both real-JMAP (straight to
Stalwart) and the shim (to `Imap::upload()`) now share one code path -
`MailCompose.uploadLocalAttachmentViaJmap()` calling `MailJmap.uploadAttachment()`.
**Real debugging detour**: the first attempt was written entirely against the WRONG widget class -
`api/js/etemplate/et2_widget_file.ts` (the legacy `et2_file`, registered for the literal `<file>`
XET tag) - and relied on returning `false` from its `onStart()` to cancel the upload. That never
took effect; the browser's own network-request initiator stack (traced live by ralf, pointing at
`Et2File.ts`) revealed the `<file>` XET tag is actually pre-processed into `<et2-file>`, the MODERN
`Et2File` custom element, whose cancellation contract is completely different: `resumableFileAdded()`
fires a cancelable `et2-add` CustomEvent per file (`event.detail` is that file's own `FileInfo`,
`.file` the native browser `File`) and checks `event.preventDefault()`, not a return value. Rewrote
`uploadStart()` against the real contract once this was found - confirmed working via DND and the
toolbar paperclip button both.
**Follow-up gap found+fixed the same day**: the user is free to switch the "From" identity to a
*different account* after attachments are already uploaded - a blobId only ever exists on the
account it was uploaded to, meaningless (or outright rejected) referenced under a different
account's `Email/set create` (ralf: "we need to fix this before we can send the mail"). Fixed in
`uploadAttachmentsViaJmap()`: an attachment's own `jmapProfileID` (set by both
`carryForwardAttachments()` and the new direct-upload path) is compared against the compose's
current target account at send/save time - a mismatch triggers `MailJmap.
reuploadAttachmentForAccount()` (download from the original account, upload fresh to the new one),
cached per source/target pair so switching back and forth doesn't re-upload on every autosave.
Surfaced one more shim bug live-testing this exact cross-account scenario (DND-attach on a
non-JMAP/shim account, switch to a real-JMAP identity, send): `Api\Mail\Jmap\Imap::download()` (the
public blob-download endpoint `client.downloadBlob()` hits) only ever understood the
self-describing `mailbox:uid:partId` blobId shape, never the `upload:<token>` shape a fresh
`Imap::upload()` produces - re-downloading a fresh upload's own bytes 404'd ("Failed to download
blob"). Fixed by delegating to the already-`upload:`-aware `readUploadedBlob()` for that shape.
**"Attach from VFS" gap RESOLVED (2026-08-31, `62a3288eb4`)**: `vfsUpload()`/
`selectFromVFSForCompose()` now go through `MailCompose`'s own attachment-resolution path
(`attachment.jmapVfsPath`) instead of a classic postback - a bare path reference is passed through
untouched (zero bytes moved client-side, ralf's explicit design call) for the shim, which reads it
directly server-side at message-build time (`Api\Mail\Jmap\Imap::buildMailerFromEmailProperties()`);
only a real-JMAP target does the WebDAV-fetch-then-upload round trip, via
`MailJmap.uploadVfsAttachment()`, cached per path/target-account pair the same way
`reuploadAttachmentForAccount()` already caches cross-account blob reuploads.

**Classic-mode attachment race FIXED (2026-09-03)**: the direct-to-JMAP fix above only ever engages
for `isJmapMode` compose. Everyone else (the default) still goes through the old chunked-upload-
then-postback path - `uploadStart()` just reveals the attachments box, the browser upload runs via
`Et2File`'s own resumable/chunked transfer, and only once it finishes does `uploadFinish()` fire a
SEPARATE `getInstanceManager().submit()` postback whose response is what actually merges the file
into `content.attachments` (`mail_compose.inc.php`'s `uploadForCompose`→`attachments` merge). There
was no synchronization between that postback and the user's own Send/Save-as-Draft click: clicking
either one before the postback lands took a snapshot of `content.attachments` that simply didn't
have the new file yet - sent/saved successfully, silently missing the attachment, no error anywhere
(`sendOK` only checks body/subject/recipients). "Save as draft first" appeared to "fix" it only
because users naturally waited for the file to visibly appear in the attachments grid - which is the
same postback completing - before clicking Send. Fixed in `mail/js/compose.ts` by tracking
`pendingAttachmentUploads` (incremented per file in `uploadStart()`'s classic/non-JMAP branch, reset
in `uploadFinish()`) and gating `submitAction()`/`saveAsDraft()` on `waitForPendingUploads()` before
either builds its payload - same "wait for a promise before submitting" pattern `integrateSubmit()`
already uses for cross-app integration pickers. This only covers the browser-upload window; the
(much shorter) postback-round-trip tail after `uploadFinish()` fires is not separately gated, since
`etemplate2.submit()` doesn't expose a completion promise to hook into cheaply and the residual race
window there is negligible in practice.

**Test coverage: partially added (2026-08-31, `0d06e43872`)** - 8 PHPUnit tests for
`buildMailerFromEmailProperties()` (`ImapBuildMailerTest.php`), the shim's core message-building
transform and the piece where the real body-vs-attachment bug above actually lived: a direct
regression test for that bug, plus the `upload:<token>` and `vfsPath` attachment shapes,
from/threading-header handling, and empty Cc/Bcc omission. Still NOT covered (needs a live or
mocked `Horde_Imap_Client_Socket`): `appendRawMessage()`, `emailSet()`'s `'create'` handling,
`emailSubmissionSet()`, and the `mailbox:uid:partId` blobId branch of
`download()`/`readUploadedBlob()` - everything else from this session (Step 2's remaining shim
methods, `reuploadAttachmentForAccount()`, "compose as new" below) is still live-verified only.

**"Compose as new" (`composeasnew`) built + live-verified 2026-08-31** (ralf: "I run into a mail
reply/forward mode we missed before... most clients call it Compose as new") - a distinct compose
mode from a browser client's own naming this project's earlier Step 4 mapping had missed entirely.
New `MailCompose.bootstrapComposeAsNew()` re-fetches the source message via `MailJmap.
fetchForReply()` (reused as-is - it already returns everything needed) and copies its
to/cc/bcc/subject/mimeType/body/attachments **verbatim**, matching classic `getDraftData()`'s own
"reopen this message as if it were still being composed" behaviour: unlike a reply/forward, there
is no quoting/attribution (`quoteOriginalMessage()` never runs here) and no RFC 5322 threading
headers (this is a fresh message, not a reply-thread continuation) - and, uniquely to this mode, the
original's own Bcc is carried forward too (`JmapReplyContext` gained a `bcc` field, fetched but used
only by this caller). A non-empty body skips signature insertion entirely (matching classic's own
`$suppressSigOnTop = true` for this case - the content already carries whatever signature it
originally had). Gated the same way as reply/forward (`app.ts`'s `composeMessage()`, `mail_compose.
inc.php`'s `$jmapReplySkip`).
**Real bug found live, same day, also affecting reply-all's own cc population**:
`fieldExpanderInit()` (the "..." expander that hides an empty Cc/Bcc/Folder/Reply-to row) runs
ONCE, early, right after the popup's own template load - it judges each row's visibility from
whatever value that row's widget has AT THAT MOMENT, which for a client-only JMAP bootstrap is
still empty (the actual to/cc/bcc population happens later, async, after this already ran). A
client-populated Cc/Bcc value stayed hidden behind its own expander despite having real content in
it (ralf: "it's something is set there... we should also show them if we put an address there").
Fixed by re-running `fieldExpanderInit()` (already a public method, no new logic needed) right after
`bootstrapComposeAsNew()`/`bootstrapReply()`'s `reply_all` branch populate cc/bcc - it re-evaluates
every row against its now-current value.

**Plain-text-mode JMAP-native send confirmed live-verified 2026-08-31** (bare `text/plain` part,
no unnecessary `multipart/alternative` - correctly uses the `textBody` shortcut, matching classic
behavior for this case). One real bug found getting there, while live-testing plain-text mode for
the first time this session:
Toggling the "HTML" checkbox off/on used to trigger a full classic postback
   (`case 'mimeType': this.et2.getInstanceManager().submit();`, pre-existing/unchanged) - for a
   JMAP-mode reply/forward specifically, that reload re-runs `bootstrapReply()` from scratch (same
   URL, still `&jmap=1&from=reply&id=...`), silently resetting `mimeType` back to the ORIGINAL
   message's own mimeType and discarding the user's own toggle entirely. Fixed (ralf: "I think we
   want the server-side roundtrip to go away"): `submitOnChange()` now handles `mimeType` entirely
   client-side when `isJmapMode`, same treatment identity-switching already got earlier - new
   `switchMimeTypeClientSide()` converts the current body (`MailJmap.htmlToPlainText()` for
   HTML->plain, a plain `<pre>`-wrapped escape for plain->HTML) and toggles which body container
   is visible directly (that swap is normally driven by `is_plain`/`is_html` content flags, one-shot
   expression bindings that don't react to a client-only mimeType change either).
   **Also solves a longstanding, previously-unfixable UX complaint for free** (ralf: "if I'm not
   happy with the conversion, I (un)check the HTML checkbox again and expect it to go back to the
   previous display, but instead another conversion makes it even worse") - new
   `lastMimeTypeConversion` field remembers the last conversion's `{before, after}` pair; toggling
   straight back (without editing in between) restores the exact pre-conversion content instead of
   running a second, further-degrading conversion on top of an already-lossy result.

**`.eml` misrouting bug: RESOLVED 2026-08-31** (originally left open, root mechanism never found
despite two investigation passes). Rather than keep chasing the actual triggering mechanism for
`mail.mail_ui.importMessageFromVFS2DraftAndDisplay`, sidestepped it entirely (ralf: "we could
probably use our mail view popup, it does the same thing and we fixed it to work client-side") -
`displayJmapBlobAttachment()` now opens `mail_ui::displayMessage()` directly for a forward-as-
attachment entry (which IS the original message itself, not a generic blob) via a new
`jmapSourceRowId` field threaded through `fetchForForwardAsAttachment()`/`carryForwardAttachments()`
- matching `app.displayAttachment()`'s own existing `MESSAGE/RFC822` case exactly, no blob download
involved at all for this case anymore. **2 more real bugs found live getting this actually working
end to end**:
1. `mail_ui::displayMessage()` unconditionally accessed `Mail\RowIdParts`' lazy `msgUID`/`folder`
   keys - for a Stalwart opaque-id row this forces `Imap\Jmap::emailId2uid()`, a real raw IMAP
   `EMAILID` search (exactly the "time-consuming fallback" `RowIdParts`' own docblock says JMAP-
   native callers should never have to pay for). That search came back empty against Stalwart even
   for a message that opens fine via a direct `Email/get`, causing a long timeout then a false
   "message could not be displayed" error - despite `$uid` never being used for anything beyond
   that one check (`$content['mail_id']` is always the original, unresolved row-id regardless).
   Fixed by skipping the whole classic-resolution block (Drafts/Templates redirect + the error-
   check) entirely for a JMAP-native row (`$hA['is_jmap']`) - one accepted trade-off (ralf): a
   Draft/Template message opened this way shows read-only instead of redirecting to compose, not
   reachable via forward-as-attachment in practice.
2. Even once the popup opened and loaded correctly, the forwarded message never showed up in the
   RECIPIENT's own "Attachments" list at all once actually delivered - `Horde_Mime_Part::
   addMimeHeaders()` hard-codes "message/* parts require no additional header information" (RFC
   2046 [5.2.1]) and unconditionally skips `Content-Disposition` for ANY part whose primary type is
   "message", regardless of which `Api\Mailer` method builds it. New `Rfc822AttachmentPart` (a
   narrow `Horde_Mime_Part` subclass re-adding just that one header, reusing the same header object
   `setDisposition()` already populated) + `Imap::addAttachmentPart()`, a small shared helper both
   attachment-processing branches now go through - transparent for every other type, only
   `message/rfc822` takes the new path. Regression test added
   (`ImapBuildMailerTest::testMessageRfc822AttachmentGetsAttachmentDisposition`).
Both confirmed live: the popup opens correctly and the attachment shows up in the recipient's inbox
for a newly-forwarded test message (an already-sent message from before this fix keeps its
original, disposition-less bytes - re-viewing it proves nothing about the fix).

**3 more rounds of "same symptom, different mechanism" chasing a real bounce/NDM's own nested
`.eml`, all resolved 2026-08-31**:
1. **Root cause finally found, in shared framework code**: `Et2Description._handleClick()`
   (`api/js/etemplate/Et2Description/Et2Description.ts`) passes the widget's own `mime` attribute
   to `egw.open_link()` on every click, even when `href`/`mimeData` already resolved to a perfectly
   correct, caller-specific URL. `open_link()` (`api/js/jsapi/egw_open.ts`) then unconditionally
   looks up the GENERIC, type-keyed mime registry for that type and - unless the already-provided
   link happens to textually match *that specific* registry entry's own menuaction - silently
   overwrites it. Broadened the "already wrapped, don't touch it" check: a link that already
   specifies ANY menuaction is, by definition, already resolved by its caller. Ran the entire JS
   suite (1342 tests, both browsers) since this is genuinely shared, app-wide code - all green.
   Regression test added to `EgwOpen.test.ts`.
2. **Same symptom persisted for the bounce/NDM case specifically - a real, DIFFERENT mechanism**:
   `AttachmentJmap::createAttachmentBlock()` unconditionally tries to register a `mime_data` token
   (`Api\Link::set_data()`, for `AttachmentJmap::fetchBlobBytes()`) whenever a `blobId` is present -
   for `message/rfc822` specifically that succeeds, so the correctly-built `mime_url` (from this
   same method's own dedicated `MESSAGE/RFC822` switch case) never got set at all, entirely unused.
   `Et2Description._handleClick()` prefers `mimeData` over `href`, and a bare token isn't a URL, so
   fix (1) above didn't help this specific case. `message/rfc822` (and, same "dedicated special
   popup" reasoning, vcard/calendar) now always take the `mime_url` branch regardless of whether
   `mime_data` ended up set - matching `mail/js/app.ts`'s own `resolveAttachmentViewUrls()`, which
   already excludes exactly these types from its own client-side `mime_url` resolution for the same
   reason. Regression test added to `CreateAttachmentBlockTest.php` - the existing
   `testForwardedMessageOpensMailDisplayNotDownload()` only ever checked `windowName` (set
   unconditionally regardless of this bug), never which of `mime_url`/`mime_data` actually
   survived.
3. **Routing now correct (opens instantly, no more timeout) - but the body/iframe stayed empty**:
   the body iframe (`mail_ui::displayMessage()`'s own server-rendered `mailDisplayBodySrc` `src=`)
   always goes through the classic `MessageDisplayHandler::get_load_email_data()`, which needs a
   real numeric IMAP UID - `Mail::splitRowID()`'s raw IMAP EMAILID search can't always resolve that
   reliably against Stalwart (confirmed live: a 20s timeout, then an empty response). Unlike fixes
   (1)/(2) in Step 4's own earlier `.eml` chain, this method genuinely NEEDED that UID for normal
   body rendering (its own narrow JMAP-native fast path, `tryJmapNativeSpecialCase()`, is scoped
   only to S/MIME/TNEF) - and it turned out this silently affected the standalone desktop message-
   view POPUP for perfectly normal TOP-LEVEL messages too: unlike the main list's own JMAP-native
   preview pane ("we fixed it to work client-side"), `app.ts`'s `display()` never actually called
   `loadMessageBody()` at all, relying purely on this same classic iframe `src`.
   **First attempt (2026-08-31, superseded same day)**: expose server-side parsing as its own
   read-only operation, since JMAP has no "parse this blob as if it were a real Email" verb
   (`Email/get` only works on a real, listed email id) even though both backends are already fully
   CAPABLE of parsing arbitrary RFC822 content. `Imap::parseRawMessageAsEmail()` (pure: raw bytes
   in, JMAP-shaped Email properties out, reusing `Horde_Mime_Part::parseMessage()`) +
   `parseBlobAsEmail()` (fetches the blob first via `AttachmentJmap::fetchBlobBytes()`), exposed as
   `mail_ui::ajax_parseBlobAsEmail()`, with 4 PHPUnit tests. Live-tested against a real bounce/NDM
   and it silently fell through to the classic `loadEmailBody` fallback (no console error - one of
   `fetchBodyFromMessagePart()`'s own early-return checks failed quietly).
   **Final approach (ralf, same day, "I'm thinking as the body of the NDM is empty, your first
   suggestion to simply display the whole part as text might have been the best approach" -
   live-confirmed, "ok, I see now the headers as text, let's leave it like that")**: the nested
   original message's body is routinely empty/near-empty anyway (the MTA only forwards headers, or
   a truncated snippet), so the structured-parse round-trip bought nothing. Deleted the server-side
   parse machinery entirely (`parseBlobAsEmail()`/`parseRawMessageAsEmail()`,
   `ajax_parseBlobAsEmail()`, `ParseRawMessageAsEmailTest.php`). `MailJmap.fetchBodyFromMessagePart()`
   now looks up the sub-part's own blobId (a normal `Email/get` on the CONTAINING message,
   already-listed in its own `attachments` property), then downloads it client-side and renders it
   as plain `<pre>` text - the same `downloadPartText()`/`textToHtml()` mechanism `fetchBody()`
   already uses for its own PGP body path, no PHP round-trip at all. Nested attachments are
   no longer independently listed at all (a narrower limitation than the first attempt's, but this
   whole case is a fallback view for an edge case, not the common path).

**S/MIME/TNEF read-side "fast path" extraction (2026-08-31)**: `MailJmap.fetchBody()` used to bail
straight to the classic full-page iframe load (`mail.mail_ui.loadEmailBody`) for S/MIME/TNEF
messages (`isSpecialCase()`/`SPECIAL_CASE_TYPES`). That classic fallback was, underneath, already
JMAP-native for both (`MessageDisplayHandler::tryJmapNativeSpecialCase()` →
`JmapImap::resolveSmime()`/`Imap\Jmap::resolveSmimeJmap()` for decrypt+verify,
`JmapImap::resolveTnef()`/`Imap\Jmap::resolveTnefJmap()` for TNEF decode, both backends,
passphrase via `Mail\Smime\PassphraseMissing` + session-cached `smime_passphrase`) - it just wasn't
reachable except via a whole extra page load. New `MessageDisplayHandler::resolveSpecialCaseBody()`
is a lean, self-contained (rowId in, JSON out - own `Mail\Account::read()`/`imapServer()`
resolution, same pattern `AttachmentJmap::resolveWinmailJmap()` already used, not dependent on
`$this->ui->mail_bo` already being on the right profile) counterpart reusing the exact same
`resolveSmime()`/`resolveTnef()` primitives, exposed as `mail.mail_ui.ajax_resolveSpecialCaseBody()`.
`fetchBody()` now calls it for any special-case message and only falls back to the classic iframe
load if it returns null. S/MIME verify/encryption badges (`app.mail.setSmimeFlags()`, previously
server-`Push`ed) now travel back in the same JSON response and get applied client-side in
`app.ts`'s `loadMessageBody()`.

**Performance bug found+fixed live 2026-09-01** (ralf: S/MIME body "not shown" on a real
sign+encrypt send/receive round trip - `page_generation_time: 20.07` on the network tab gave it
away): `resolveSpecialCaseBody()` read `$idParts['msgUID']`/`['folder']` unconditionally at the
top, before even checking whether the row is a Stalwart one - `Mail::splitRowID()` returns a
`RowIdParts` object (`api/src/Mail/RowIdParts.php`) where those two keys are resolved LAZILY, on
first read, via a real IMAP EMAILID search (`Imap\Jmap::emailId2uid()`) specifically because that
search is the exact "20s timeout" cost this whole JMAP-native migration exists to avoid - for a
Stalwart row neither key is ever actually needed (only `emailID` is), so simply reading them
paid that cost on every single call regardless of backend. Fixed: checks `profileID`/backend type
first, only touches `msgUID`/`folder` inside the shim branch where they're genuinely used.

**Fast-path passphrase dialog built same day**, once the performance bug above made clear the
classic fallback isn't just slower but genuinely unusable as a passphrase-prompt substitute for a
Stalwart row (`loadEmailBody()`'s own `Mail::splitRowID()` read has the exact same
lazy-key-forcing bug, one level up - see below). `resolveSpecialCaseBody()` now takes an explicit
`$passphrase`/`$passExpMinutes`, propagating a still-needed one as a real
`Mail\Smime\PassphraseMissing` throw (`mail.mail_ui.ajax_resolveSpecialCaseBody()` shapes it into
`{needsPassphrase, message}`, same convention `ajax_smimeEncryptEmailProperties()` already used
send-side) instead of collapsing it into a bare `null`. `MailJmap.fetchBody()` throws a
`JmapSmimePassphraseError` on that signal; `MailApp.loadMessageBody()` catches it, shows a new
`smimeViewPassDialog()` (same `etemplate.password` eTemplate/dialog shape as compose's own
`smimePassDialog()`, but generic - calls back with `(passphrase, passExpMinutes)` instead of
assuming a compose-send retry), and retries the SAME fast path with the entered passphrase - only
on confirmed decrypt success does `resolveSpecialCaseBody()` cache it
(`Api\Cache::setSession('mail', 'smime_passphrase', ...)`) for next time, same "never cache before
it's proved ok" principle as the send-side.

**`loadEmailBody()` had the SAME lazy-key-forcing performance bug**, one level up from
`resolveSpecialCaseBody()`: it read `$uidA['folder']`/`$uidA['msgUID']` unconditionally before
ever calling `get_load_email_data()`/`tryJmapNativeSpecialCase()`, which is why the classic
fallback wasn't a viable passphrase-prompt substitute (it paid the 20s cost - or timed out
entirely - before it could even attempt the S/MIME resolver). Left as a known follow-up when this
was first written, but **fixed the same day, `cec0df7f34`** ("fix classic loadEmailBody() 20s
Stalwart lookup, S/MIME passphrase caching race, popup badge color") - `loadEmailBody()` now
branches on `$uidA['is_jmap']` and passes `$messageID`/`$folder` as closures for a Stalwart row
(only calling them for the genuinely-classic code path past `tryJmapNativeSpecialCase()` in
`get_load_email_data()`), matching `resolveSpecialCaseBody()`'s own fix - re-verified live 2026-09-03
(this section had gone stale, still describing it as unfixed after the fact).

**Passphrase-remember-duration was never actually reaching the server on the send side either**
(ralf: "I have not seen the cache-timeout in the passphrase dialog been send to server-side, nor
it been used there") - `smimePassDialog()`'s own `egw.set_preference('mail', 'smime_pass_exp',
...)` call is `jsonq()`-queued, which can still be in flight when the very next request (the send
retry) already reads `$GLOBALS['egw_info']['user']['preferences']['mail']['smime_pass_exp']`
server-side - a race, not a hard guarantee. Both `smimeEncryptEmailProperties()` (send) and
`resolveSpecialCaseBody()` (view) now take an explicit `$passExpMinutes` instead: the dialogs
stash the entered value (`MailCompose.smimePassExpMinutes` for send, threaded straight through
the view-side retry call for view) and pass it as a real request parameter. The preference write
stays too, purely so the dialog pre-fills with whatever was last entered.

**Also fixed same session**: `mail_compose.inc.php`'s classic forward-attachment path called a
`decode_winmail()` method on `Api\Mail` that never existed (a live fatal error waiting to happen
for anyone forwarding a specific attachment extracted from a winmail.dat/TNEF blob) - repointed to
the real `Mail::tnef_decoder()` static decoder, the same one `getMessageAttachments()`/
`getAttachment()` already use for the equivalent classic-IMAP-side decode.

**S/MIME sign/encrypt on send (phasing Step 6) - server-side primitive + endpoint built
(2026-08-31), NOT client-wired, NOT live-tested.** New `JmapImap::smimeEncryptEmailProperties()`:
builds a full `Api\Mailer` from JMAP Email properties via the existing (now-backend-uniform, see
below) `buildMailerFromEmailProperties()`, signs/encrypts via the same `Api\Mailer::
smimeEncrypt()`/`Mail\Smime` primitives classic `mail_compose::_encrypt()` already uses, then
serializes just the resulting body ENTITY (`multipart/signed` or `application/pkcs7-mime`, both
single self-contained MIME entities via `Horde_Mime_Part::toString()` - `_base`'s own encoded
bytes, not the whole message) back to raw bytes. Exposed as `mail.mail_ui.
ajax_smimeEncryptEmailProperties()`, which uploads that as a blob (`AttachmentJmap::
uploadBlobBytes()`, new backend-uniform upload-side counterpart to `fetchBlobBytes()` - Stalwart
via `Api\Jmap::uploadBlob()`, shim via new `Imap::uploadBytes()`) and returns the blobId, per the
2026-08-27 design decision: the client swaps that single blobId into `Email/set`'s bodyStructure
in place of the multipart structure it would otherwise build.

Backend-uniform only as of this same session: `buildMailerFromEmailProperties()`'s attachment-blob
resolution (`readUploadedBlob()`) previously only understood `upload:<token>` or the shim's own
`mailboxB64:uid:partId` blobId shapes - silently dropping any attachment on a real-JMAP/Stalwart
account (whose blobIds are opaque, server-assigned tokens). `readUploadedBlob()` now checks for a
Stalwart `icServer` first and routes through `jmapClient()->downloadBlob()` in that case (ralf,
explicitly chosen over a separate uniform builder in `mail/src/Ui`, since this makes the existing
shim-only method genuinely uniform rather than adding a parallel one).

**Client wiring built (2026-09-01), NOT YET LIVE-TESTED against either backend.**
`jmapEligible()`'s `blockingToggle` no longer includes `smime_sign`/`smime_encrypt` - S/MIME is
handled inside `trySendViaJmap()` itself now. `MailJmap.sendNewEmail()` gained `smimeType`/
`passphrase` params: when a `smimeType` is given, new `smimeEncryptBody()` resolves inline images
(`resolveOutgoingInlineImages()`, so an HTML body's `cid:` images are already real blob
attachments, not dangling client-only `blob:` URLs, before the server ever signs/encrypts
anything) and builds the SAME Email properties `createDraftEmail()` would otherwise send
(`draftEmailProperties()`), hands them to `ajax_smimeEncryptEmailProperties()`, and returns
`{type, blobId}`. `createDraftEmail()` gained an optional `bodyOverride` param that, when given,
drops `bodyValues`/`textBody`/`htmlBody` entirely and sets `bodyStructure: {type, blobId}` - a
single opaque part - in their place; `from`/`to`/`cc`/`bcc`/`subject`/`inReplyTo`/`references`
stay exactly as `draftEmailProperties()` would otherwise build them, only the body's own MIME
shape changes. Drafts stay unaffected (unsigned/unencrypted, matching classic - S/MIME only
applies at actual send time, same reasoning `jmapEligible(forSend)`'s own docblock already gives
for the cross-app-integration toggles).

**Passphrase UX reuses the existing dialog rather than building a new one**: a still-needed
passphrase throws new `JmapSmimePassphraseError` (compared by `constructor.name`, not
`instanceof`, same cross-realm reasoning as `isUnsupportedBackendError()` - `this.app.jmap` can be
the opener window's own instance), caught in `trySendViaJmap()` to call `this.app.
smimePassDialog()` - the SAME dialog the classic path already shows, unmodified. Works because
that dialog's own submit handler already just sets the `smime_passphrase` widget value and calls
`this.compose.submitAction(false)` again - `trySendViaJmap()` re-reads that widget on the retry, no
new retry loop needed. Both the compose popup's own `app`/`et2` and that dialog's `self.et2`/
`self.compose` resolve to the SAME window-local instances (each popup gets its own full app
registry), so no cross-window widget-lookup mismatch.

**Live-verified 2026-09-01/02**: sign+encrypt and encrypt-only both confirmed end-to-end against
real Stalwart (send, receive, decrypt round-trip). encrypt-only/sign+encrypt live verification
against the IMAP-shim account is still outstanding.

**Sign-only (TYPE_SIGN) built 2026-09-02** - was deliberately deferred (see `extractSmimeBodyBlob()`'s
old docblock): `multipart/signed`'s `protocol`/`micalg`/`boundary` Content-Type parameters can't be
expressed via a `{type, blobId}` bodyStructure leaf the way TYPE_ENCRYPT/TYPE_SIGN_ENCRYPT's opaque
`application/pkcs7-mime` can - RFC 8621 §4.1.4's `EmailBodyPart.type` is bare "type/subtype" only,
and confirmed via the RFC text itself that `headers` MUST NOT be given on `Email/set create` at all
(no override escape hatch either). Fix: `smimeEncryptEmailProperties()` now returns the WHOLE raw
message (`Api\Mailer::getRaw()`) for TYPE_SIGN instead of just the body entity
(`{whole: true, raw}` vs the existing `{type, raw}`); the client (`MailJmap.importWholeMessageDraft()`)
uploads it as one blob and creates the Draft via `Email/import` (RFC 8621 §4.8, stores raw bytes
verbatim) instead of `Email/set create`. Both backends' native `Email/import` handle this
uniformly - no Stalwart-side PHP changes needed.

The IMAP-shim's own `emailSubmissionSet()` (send emulation) needed one more fix: it normally
*rebuilds* the Mailer from the draft's `emailGet()` properties at send time (so the Sent-copy always
matches what's actually transmitted) - safe for an opaque encrypted blob, but rebuilding a
`multipart/signed` body would silently produce a different (unsigned) body and invalidate the
signature regardless (which covers the exact byte-for-byte MIME framing of the content part). Now
detects a `multipart/signed` top-level bodyStructure and, in that case only, keeps the rebuilt
Mailer's headers/addresses (safe to rebuild) but injects the ORIGINAL stored raw bytes as the base
part (`Horde_Mime_Part::parseMessage()` + `Mailer::setBasePart()`) instead of letting
`buildMailerFromEmailProperties()` reconstruct the body - `Horde_Mime_Mail::send()` still handles
Bcc-stripping-for-the-wire-but-not-storage automatically either way. Not yet live-tested against
either backend - next up for ralf to verify.

**Sign-only LIVE-VERIFIED end-to-end 2026-09-02** against real Stalwart, after fixing two more real
bugs found getting there (both genuine `Api\Mailer`/addressbook bugs, not JMAP-layer bugs - see
their own project-doc-adjacent commits):

1. **`Api\Mailer::getRaw()` double-wrap bug** - the actual root cause of a garbled received body
   (rendered like a bounce/NDM): `getRaw()` only re-synced the message's top-level headers
   (Content-Type/Message-ID/Date/etc.) from the base MIME part when `getBasePart()` THREW (ie. no
   base part set yet) - but `smimeEncrypt()` sets `$this->_base` DIRECTLY
   (`$this->_base = $smime->signMIMEPart(...)`), bypassing that check entirely, so for TYPE_SIGN
   (which calls `getRaw()` right after `smimeEncrypt()`, with no real `send()` in between) the sync
   never happened at all - the signed multipart's own content ended up nested, headers and all, as
   the "body" of an outer envelope still carrying whatever `$this->_headers` had BEFORE signing
   (missing Content-Type entirely, defaulting to `text/plain`). New `Api\Mailer::$_headersSynced`
   flag tracks whether `_send()` (a real send, or `getRaw()`'s own null-transport fallback) has
   actually run against the CURRENT `$_base`, reset to `false` by `smimeEncrypt()` whenever it
   reassigns `$_base` - `getRaw()` now checks that flag instead of `getBasePart()`'s throw.
   encrypt-only/sign+encrypt were never affected (they extract the body entity directly via
   `getBasePart()`/`getContents()`, never call `getRaw()` at all).

2. **Addressbook cert desync, unrelated to the migration itself but blocking real-world testing**:
   `addressbook_bo::get_smime_keys()`/`set_smime_keys()` are a SEPARATE store (a VFS-file-backed
   contact field) from `Mail\Smime::get_acc_smime()`'s own account credential/p12 - normally the
   same certificate, kept in sync only by `admin_mail.inc.php`'s cert-generate/import/upload calls
   to `set_smime_keys()`. Found live: an addressbook-write ACL failure during a real key rotation
   (since separately fixed) left the addressbook holding the OLD certificate indefinitely, with
   nothing to notice or fix it - outgoing signing kept embedding the stale cert (invalid signature
   for recipients) and encrypting-to-self kept using the stale PUBLIC key (silently "worked" only
   because the account's own retained `extracerts` happened to still include that exact old key).
   Two fixes, both silently self-healing whenever the passphrase happens to be available (ralf:
   session-write instability elsewhere - separate, actively-changing work - made relying on
   `get_acc_smime()`'s session-cached-passphrase fallback alone unreliable):
   - `admin_mail::edit()`: opportunistic check+resync on every view/save of the CURRENT user's own
     mail account (ralf: "if there is a p12 for the current user (not admin impersonated), we
     should check it has a matching public key... user should simply save the mail-account to
     update/refresh").
   - New shared `Mail\Smime::resyncAddressbookCert()`, called at the moment a passphrase is
     actually PROVEN to work (decrypt success in `resolveSpecialCaseBody()`/
     `tryJmapNativeSpecialCase()`, sign/encrypt success in `smimeEncryptEmailProperties()`) -
     immune to session-cache timing entirely, since the passphrase is still a local variable there.
     Deliberately silent (no toast) unlike the account-settings version, since it fires from
     otherwise-unrelated view/send actions.

**PHPUnit coverage added 2026-09-02** (`api/tests/Mail/SmimeMailerTest.php`) for the sign/encrypt
send-side chain: `Api\Mailer::smimeEncrypt()`/`getRaw()` + `Mail\Smime::resolveMessage()`/
`decrypt()` round-trips, using real certificates generated on the fly
(`Smime::generate_certificate()`, same pattern as `SmimeTest.php`'s own pure-openssl tests) rather
than a stored account credential - `acc_id=1` is only ever used to satisfy `Api\Mailer`'s
constructor, same precedent as `Jmap/ImapBuildMailerTest.php`. Includes a regression test for the
`getRaw()` double-wrap bug above, live-verified to actually fail against the pre-fix code.

**IMAP-shim encrypt-only/sign+encrypt: found + fixed a real bug investigating live testing there**
(ralf: "look into" whether it actually works against the shim account - it hadn't been tried,
only against Stalwart). `buildMailerFromEmailProperties()` routed `createDraftEmail()`'s
`{type: application/pkcs7-mime, blobId}` body-swap through the generic
`$collect()`/`addAttachmentPart()` attachment path (no matching `bodyValues` entry), and
`Api\Mailer::_send()` unconditionally wraps that together with an always-truthy empty placeholder
body part in a fresh `multipart/mixed` container - confirmed via a real shim-sent encrypted mail:
`multipart/mixed` containing an empty `application/octet-stream` leaf, a second empty
"attachment" leaf, and the real pkcs7-mime bytes as a THIRD, attachment-dispositioned part,
instead of the message's own top-level Content-Type simply being `application/pkcs7-mime`. Fixed:
this shape (a bodyStructure with no `subParts` and a `blobId`) is now detected early and set
directly as the Mailer's base part via `setBasePart()`, bypassing the attachment path entirely.
Stalwart is unaffected (`Email/set create` is handled natively there). Regression test added to
`Jmap/ImapBuildMailerTest.php`. **Live-verified 2026-09-02**: ralf sent a real sign+encrypt
message to himself via the shim account, received it correctly.

**Sign-only via the shim: two more real bugs found + fixed 2026-09-02, live verification pending.**
After the
double-wrap and encoding fixes above, a real shim-sent sign-only message STILL arrived flagged
"verification failed - this message may have been tampered with" - reproduced entirely in-process
(no IMAP/SMTP needed - see `SmimeMailerTest::testResendingAStoredSignedMessageKeepsAValidSignature()`)
by byte-comparing the originally-signed message against the same message after
`emailSubmissionSet()`'s own parse-then-reserialize resend step:
1. `signMIMEPart()` signs the content part while it still carries a stale `STATUS_BASEPART` flag
   (left over from `getBasePart()`'s own earlier "build the initial unsigned message" step) -
   `Horde_Mime_Part::addMimeHeaders()` bakes an extra "MIME-Version: 1.0" header into what gets
   signed whenever that flag is set, a header a nested sub-part should never carry per RFC 2045.
   A freshly re-parsed copy of the same content has no such in-memory-only flag, so it correctly
   omits that header - different signed bytes the moment a signed message is stored then
   re-parsed. Fixed by clearing the flag right before signing.
2. `Horde_Mime_Mail::send()`'s own flowed-text handling sets a mixed-case "DelSp" Content-Type
   parameter name; `Horde_Mime_Part::parseMessage()` lowercases parameter names on read
   (case-insensitive per RFC 2045, but still a byte-level difference). Fixed by normalizing all
   Content-Type parameter names to lowercase, recursively, right before signing.

Both fixed in `Api\Mailer::smimeEncrypt()` rather than the forked Horde packages
(`vendor/egroupware/*`) to avoid a package release cycle for what's ultimately a narrow
interaction between two Horde quirks - ralf confirmed those packages ARE real, separately
releasable git repos if a Horde-level fix is ever preferred instead.

Companion to [[mail-jmap-imap-inversion]].

**HTML sends were missing a text/plain alternative entirely, built 2026-08-31, not yet
live-tested.** Confirmed via `mail_compose.inc.php`'s own `createMessage()`: classic ALWAYS builds
a real `multipart/alternative` for an HTML compose - `$_mailObject->setBody($this->
convertHTMLToText($body, true, true))` (plain-text version, via the same ~260-line
`Api\Mail\Html::convertHTMLToText()` engine also used for signature conversion) followed by
`setHtmlBody($body, null, false)` - the trailing `false` meaning "don't auto-generate an
alternative, a real one was already supplied." Every JMAP-native HTML send/save this whole session
was missing this - `draftEmailProperties()` only ever sent a bare `text/html` part (or the
`htmlBody` RFC 8621 §4.1.4 convenience-view shortcut for the no-attachment case), no plain-text
counterpart at all. Fixed: `draftEmailProperties()` now always builds a real `multipart/alternative`
(text/plain + text/html) for HTML mode - the `textBody`-shortcut path now only applies to the
plain-text, no-attachment, no-inline-image case (HTML always needs the real bodyStructure now, same
as an attachment/inline image already forced it for either mode). Conversion itself
(`MailJmap.htmlToPlainText()`) went through two iterations same day: a first pass was a naive
DOMParser-based version (ralf: "I think we should keep that client-side, for now something like
[naive] is sufficient, we can later look into a decent library"), immediately superseded once ralf
found the `html-to-text` npm package ("does exactly what we need, incl. configurable handling of
links and inline images, we probably want to wire that in directly") - now a thin wrapper over
`html-to-text`'s own `convert()` (pure-JS/htmlparser2-based, no Node-only APIs, bundles for the
browser the same as this file's other npm deps - `@types/html-to-text` added as a devDependency
since the package ships no types of its own), `wordwrap: 78`. Still NOT the sophisticated
server-side `Api\Mail\Html::convertHTMLToText()` engine classic uses for this same purpose (entity/
charset edge cases, quoting conventions tuned for this codebase specifically) - that stays
server-side, untouched, only used for signature conversion. Full nesting once inline images/
attachments are added too: `multipart/mixed` (attachments) > `multipart/related` (inline images) >
`multipart/alternative` (text/plain + text/html) - each layer only appears when actually needed for
that specific message.

**Send/draft-save side of inline images built + live-verified 2026-08-31** (real `multipart/related`
sent with a correct `Content-ID`/`Content-Disposition: inline` image part, confirmed from the raw
Sent-folder source). The display-side fix below (resolveInlineCidImages())
is only half the story - a `blob:` URL is only ever valid within the browser tab that created it,
so sending/saving one verbatim just gives the recipient a broken image (ralf: "sending has to
re-wire and reference as attachments"). New `MailJmap.resolveOutgoingInlineImages()` (called from
`createDraftEmail()`, shared by send AND draft-save) re-uploads each `blob:`-referenced image as a
real JMAP blob under a fresh Content-ID and rewrites `src="blob:..."` back to `src="cid:..."`;
`draftEmailProperties()` nests the result in a proper `multipart/related` alongside the body
(wrapped in `multipart/mixed` too if there are also regular attachments). Real bug found+fixed
live: the first attempt tried `fetch(blobUrl)` to get the bytes back and was blocked by this
deployment's own CSP (`connect-src` doesn't allow `blob:`, even though `<img src="blob:...">`
display itself is fine under the separate `img-src` directive) -
`TypeError: Failed to fetch. Refused to connect because it violates the document's Content
Security Policy.` Fixed by keeping the actual `Blob` object around from the moment it's first
created (`inlineImageBlobs`, a new `MailJmap` field) instead of ever needing to fetch the URL back
- sidesteps the CSP question entirely rather than needing it loosened. Second issue, caught before
live-testing rather than found live: `resolveOutgoingInlineImages()` would otherwise re-upload the
SAME image as a brand-new blob on every autosave tick (ralf: "we probably already have to upload
inline images to JMAP blob store and cache their Ids, so we remember to... re-use them for the
submission") - `saveDraft()`'s own "destroy the previous draft copy" step only destroys the
previous draft Email, not any blob it referenced, so each fresh re-upload would silently orphan
the one before it. Fixed with a second new field, `inlineImageUploads` (keyed the same way as
`inlineImageBlobs`), caching the uploaded `{blobId, cid, ...}` for reuse by every later
autosave/send of the same compose session - not deleted/revoked after one use, since later calls
re-read the SAME still-`blob:`-referencing widget content each time (this rewrite only ever
touches a COPY of the body for the outgoing payload, never the live editor widget itself).
Confirmed (ralf): Stalwart runs its own job cleaning up unreferenced blobs, so the one remaining
edge case (compose closed before ever autosaving even once) needs no special handling - JMAP has
no blob-delete primitive to call anyway (RFC 8620 §6's Upload has no matching Destroy).
**Same problem, same fix, also applied to regular (locally-staged) attachments** (ralf: "the same
is also true for attachments") - `compose.ts`'s `uploadAttachmentsViaJmap()` gained its own
analogous cache, `uploadedAttachmentBlobs` (keyed by the attachment's own stable `tmp_name`) -
carryForwardAttachments() entries (`jmapBlobId` already set) never reach this cache at all, since
they're already a stable, permanent reference to the original message's own blob with nothing to
upload in the first place.

**Inline-image resolution for the quoted body built + live-verified 2026-08-31 (against the
shim backend, on real test mail with inline images).** Browsers have no native support for the
`cid:` URI scheme outside a real MIME message context - left unresolved, an inline image in a
quoted reply/forward would just show as a broken image once inserted into the compose editor.
Classic `getReplyData()`'s own fix is `BodyHandler::resolveInlineImages()` (a `mail_ui::
displayImage()` menuaction link, or a `data:` URI for small images). New
`MailJmap.resolveInlineCidImages()` does the equivalent client-side: replaces `src="cid:..."`
references in the quoted HTML with real `blob:` URLs via the same `downloadBlob()` call
`resolveInlineImages()` (the message-VIEW's own identical problem) already uses - confirmed
uniform across both backends there already (Stalwart's blobId downloads directly; the shim's is
self-describing, resolved via its own `mail/jmap.php` "download" branch). Unlike the message-view
path, no defer-then-patch-after-render dance is needed here at all: `fetchForReply()`'s quoted
body is still a plain string at this point, not yet attached to any DOM/iframe, so resolution
happens directly on the string before it's ever inserted into the editor. Only handles `src="cid:
..."` (the dominant case) - deliberately not porting classic's CSS `url(cid:...)`/`background=
"cid:..."` handling too (rare in practice, e.g. an old-style HTML signature background).

**Confirmed working against the IMAP-shim backend 2026-08-31 (no code change needed)**: ralf
tested reply against a shim account (acc_id=42) and initially suspected server-side classic
fallback, since NO shim (`jmap.php`) request showed in the compose popup's own Network tab.
Root cause: `MailApp.jmap` reuses `window.opener.app.mail.jmap` (the WebSocket-sharing fix from
earlier this session) - so `fetchForReply()`'s function body, its `fetch()` calls, and its
`console.log()` output all execute in the **opener (main list) window's** own JS realm, not the
popup's - diagnostic logging (added, then removed once confirmed) showed the fetch actually
succeeding via `jmap.php` (isLocal path), visible only in the opener tab's own DevTools. Nothing
was broken - a devtools-visibility gotcha, not a bug.

**Gap above RESOLVED by Step 2 (2026-08-31)**: now that `EmailSubmission/set` exists for the shim
and `uploadAttachment()`'s `token.isLocal` throw is gone too, a shim send/save never falls back to
classic at all for reply-with-attachments/forward - the `{jmapBlobId, tmp_name}` shape is handled
JMAP-natively end to end, so the classic `createMessage()` mismatch described below can no longer
be reached. Left here for the historical record of what the gap was before Step 2 landed.

**Reply-all built + live-verified 2026-08-31 (ralf: "tested reply-all with a few recipients, works
fine")**: `_action.id === 'reply_all'` is now JMAP-mode
eligible too (`app.ts` gating, `$jmapReplySkip` extended). Reuses `bootstrapReply()`'s whole
fetch/quote/threading-header/identity-matching machinery unchanged - only the to/cc computation
differs, matching classic `getReplyData()`'s own mode='all' 3-loop algorithm exactly: the
reply-to-or-from target is ALWAYS included in `to` (not just replyTo-if-present like plain reply -
if Reply-To differs from From, BOTH end up in `to`, same as classic), original `to` (minus the
account's own addresses) also goes into `to`, original `cc` (same exclusions, plus anything already
in `to`) goes into `cc`. `selectIdentityForRecipients()` (already fetching every identity for
identity-matching) now also returns that list so `bootstrapReply()` can build the "own addresses"
exclusion set from it (every identity's email, not just the currently-selected one) - no extra JMAP
round-trip needed. No attachment carry-forward for reply-all, matching classic (only
`reply_attachments` carries attachments, never combined with 'all' in the classic code either -
confirmed: `getComposeFrom()`'s `reply_attachments` case always calls `getReplyData('attachments',
...)`, never `'all'`).

**Merge-into-already-open-popup, deliberately deferred (researched 2026-08-31, not built)**: a
multi-message or forward-as-attachment action, when a compose popup is ALREADY open,
`egw.openWithinWindow()` shows a picker and, if an existing popup is chosen, calls a LIVE JS method
directly into that OTHER already-loaded window (`popups[i].app['mail']['setCompose'](...)`) instead
of a URL/page load - server-side this decodes `appendix_data` (`mail_compose.inc.php:356-377`) into
the SAME classic uid/folder-addressed `.eml`-attachment mechanism (`_get_uids_as_attachments()`,
`addMessageAttachment(..., 'MESSAGE/RFC822', ...)`) my JMAP blobId-based forward-as-attachment
already replaces for the fresh-popup case. No existing precedent for one window reaching DOWN into
an already-open popup's JMAP state (only the reverse - a compose popup reaching UP to
`window.opener.app.mail.jmap`) - `MailCompose.isJmapMode` is `private readonly`, would need new
public cross-window state exposed. Ralf: defer rather than build now. The picker's own "New" option
still correctly takes the JMAP-aware fresh-popup path (`openUp()`) - only "merge into an existing
one" stays classic-only.

**Unrelated latent bug found, deliberately left as-is**: `egw.openWithinWindow()`'s own `openUp()`
switches to a raw POST `<form>` submission when the built URL exceeds 2083 chars, bypassing
`$_GET` entirely - `$jmapReplySkip` (reads `$_GET['jmap']`) would silently miss that case, falling
back to the slower classic path (never breaks, just loses the speed-up) - could already affect the
shipped multi-message forward-as-attachment given enough forwarded messages. Ralf: not worth fixing
for this edge case.

**Forward-as-attachment built + live-verified 2026-08-31**: one or more messages, each
attached whole as `message/rfc822` rather than quoted inline - matches classic
`getForwardData()`'s own asmail branch (one `addMessageAttachment(..., 'MESSAGE/RFC822', ...)` call
per forwarded message), but via a JMAP blobId reference instead of a classic uid/partID/folder
address. New `MailJmap.fetchForForwardAsAttachment()` fetches just `subject`/`blobId`/`size` per
message (RFC 8621 §4.1.1's `Email.blobId` - the raw RFC 5322 octets, top-level on the Email object,
already used by `fetchRawSource()`'s "view source" feature) - no quoted-body fetch at all, unlike
`fetchForReply()`. `compose.ts`'s new `bootstrapForwardAsAttachment()` builds one
`{blobId, name: subject+'.eml', type:'message/rfc822', size}` `JmapAttachment` per message and
reuses `carryForwardAttachments()` unchanged - same blobId-reference-not-reupload mechanism, same
UI. No quoted body, no to/cc/threading-headers at all (a forward-as-attachment is otherwise a
genuinely blank new message) - still applies the normal new-message signature though (classic never
suppresses it for this mode either). Subject for multiple messages: classic's own loop overwrites
`sessionData['subject']` once per message, so ITS final subject is just the LAST message's own
subject - an accident of the loop, not deliberate; this uses the FIRST message's subject instead
(ralf, 2026-08-31, a deliberate small improvement over that classic quirk).

**Reachability, architecturally distinct from everything else in Step 4**: `composeMessage()`'s own
`case 'forward'/'forwardinline'/'forwardasattach'` branch, for `_elems.length>1 || _action.id ===
'forwardasattach'`, calls `egw.openWithinWindow()` (`api/js/jsapi/egw_open.ts`) instead of the plain
`egw().open()` every other JMAP-mode path uses, and **returns before ever reaching the shared
jmap-gating code** further down `composeMessage()` - so that branch now sets its own
`settings.jmap='1'` flag directly, gated on `jmapComposeEnabled`, before its own `return`.
`egw.openWithinWindow()` itself: if NO compose popup is already open, it falls through to the exact
same `egw.open()` URL-param mechanism (so `jmap=1` behaves identically to every other case here); if
one or more ARE already open, it shows a picker and, if an existing one is chosen, calls a live JS
method (`popups[i].app['mail']['setCompose'](...)`) directly into that ALREADY-LOADED window instead
of a URL/page load at all - that other window's own `isJmapMode` was fixed at ITS OWN original load
time and is completely unrelated to this new forward action, so the `jmap=1` flag is simply never
read in that case (a structurally different "merge attachments into a live, already-running
popup's JS state" problem, out of scope of anything built in Step 4 so far - always falls back to
the unchanged classic path in that specific case).

**Step 4 first slice (reply) done+live-verified (2026-08-31)**: `MailCompose.bootstrapReply()` +
`MailJmap.fetchForReply()`/`quoteOriginalMessage()`/`selectIdentityForRecipients()` - client-side
`Email/get` fetch of the original message, quoted-attribution block, RFC 5322 §3.6.4 threading
headers, and identity-for-recipient matching, all replacing `getReplyData()`'s server-side
computation for JMAP-mode reply. `mail_compose.inc.php` skips `getComposeFrom()`'s classic raw-IMAP
fetch entirely for this case (`$jmapReplySkip`) rather than keeping it as a "safety net" - ralf,
after measuring it added ~20s to every reply open for no benefit: "there's no reason to assume the
JMAP side would fail, but IMAP somehow succeeds." Threading headers are RFC 5322-*more correct* than
the classic path, not just a port: `In-Reply-To` = original's own `messageId`; `References` =
original's own `references` (if any) *with its own messageId appended* - `getReplyData()` is missing
that append step for a message that's itself already part of a thread.
5 real bugs found+fixed via live testing:
1. Client-side `set_value()` calls during bootstrap mark the form dirty exactly like a real user
   edit (unlike classic server-rendered content, which the dirty-tracker treats as its clean
   baseline) - closing an untouched reply popup wrongly showed the "unsaved changes" prompt. Fixed
   by extracting a new shared public `Et2Template`/`etemplate2.resetDirty()` method (per ralf's
   request: "maybe that code fragment in etemplate2.ts should be extracted in a small helper" -
   both `load()`'s own pre-existing internal reset-after-load and `bootstrapCompose()`'s new
   client-side-fill case now call the same method).
2. `MailJmap.jmapUtcToUserTz()`'s output (a "Z"-suffixed ISO string encoding LOCAL time, meant only
   to feed a UTC-getter-based formatter) was showing up verbatim, un-formatted, in the reply quote's
   attribution block. Fixed by wrapping with `Et2Date.ts`'s exported `formatDateTime()` - the one
   place in the client-side API that does user-preference date formatting (ralf independently
   arrived at the same diagnosis before the fix landed: "I was about to suggest the Et2Date widget
   can do the formatting, nothing else e.g. in the client-side api deals with date-formatting
   client-side").
3. **Regression, unrelated to today's reply work**: previewing/opening a message via the JMAP-native
   fast path no longer marked it read - `app.ts`'s `preview()`/`openMessage()` both carried a
   long-stale comment ("When body is requested, mail is marked as read by the mail server") that
   assumed the classic raw-IMAP fetch's implicit `\Seen`-on-fetch side effect; the JMAP-native
   `fetchBody()` (a pure `Email/get`) has none. Fixed by adding a real
   `jmap.setSystemFlag([...], '$seen', true)` call at both sites (ralf: "I beleave we forgot setting
   unseen messages to seen, if they were loaded into preview or view-popup purly client-side").
4. **Send failure, "Identity not found." red toast, no matching string anywhere client-side** -
   `EmailSubmission/set`'s `identityId` is validated by Stalwart against *its own* Identity objects
   (opaque ids like `'b'`/`'c'`), but `resolveComposeContext()`'s `identity.id` had become
   EGroupware's own synthesized `ident_id` (a plain numeric string) once identity resolution moved
   to `getIdentities()` for display/signature purposes - Stalwart legitimately rejected the
   submission with its own terse `SetError` description, surfaced unmodified via
   `describeSetError()`. Fixed by resolving a *second*, separate id in `resolveComposeContext()`:
   Stalwart's own native `Identity/get` (direct-to-backend, only fetched when `needSent`), matched
   against the display identity **by email address** (the only field guaranteed to line up between
   the two unsynced systems) - `sendNewEmail()` now passes that `submissionIdentityId`, while
   `createDraftEmail()`/`draftEmailProperties()` keep using the EGroupware-synthesized identity for
   the actual `from`/signature content. `saveDraft()` (never calls `EmailSubmission/set`) is
   unaffected and doesn't pay for the extra fetch.
5. Empty `Cc`/`Bcc` still produced a real, blank `Cc:`/`Bcc:` header in the sent copy -
   `addressesToJmap()` returns `[]` (not `undefined`) for an empty array/string input, and an empty
   array is still a *present* JMAP `Email` property. Fixed in `draftEmailProperties()` by omitting
   `to`/`cc`/`bcc` from the object entirely when the resolved list is empty, instead of always
   including the (possibly-empty) result.
Not yet built: inline-image resolution for the quoted body, reply-all, forward (inline/as-
attachment) - see the mapping below, still accurate for what's left.

**Attachment carry-forward built + live-verified 2026-08-31**: "Reply With Attachments"
(`_action.id === 'reply_attachments'`, a real existing menu item under Reply,
`mail_ui.inc.php:1242`) is now JMAP-mode eligible too (`app.ts`'s `composeMessage()` gating, and
`$jmapReplySkip` in `mail_compose.inc.php` extended to cover it alongside plain `reply` - same
"skip the always-discarded classic raw-IMAP fetch" reasoning). `MailJmap.fetchForReply()` now also
fetches the `attachments` property (RFC 8621 §4.1.4's own server-computed convenience list, already
excludes the primary text/html body - no manual `bodyStructure` tree walk needed) and filters it
exactly like classic `getForwardData()` filters `getMessageAttachments()`: a cid-referenced inline
image is excluded unless its own disposition is `attachment`. Genuinely no download+reupload
round-trip needed at all - Stalwart's blobs are content-addressed and referenceable by `blobId`
directly in a new `Email/set create`'s `bodyStructure`, as long as the blob belongs to the same
account, which it does (same original message, same mailbox). `compose.ts`'s new
`carryForwardAttachments()` pushes each into the attachments grid's own array-manager entry
(mirroring `checkSharingFilemode()`'s already-established "mutate array manager, re-push into grid
widget" pattern, not a new mechanism) with a synthetic `tmp_name` (`"jmap:" + blobId"`, since the
grid row template's delete button embeds `tmp_name` as its row key) and a `jmapBlobId` marker field;
`uploadAttachmentsViaJmap()` now checks for that marker and passes the blob straight through instead
of fetching+reuploading it (only a genuinely locally-staged file, without the marker, still goes
through the classic-upload-widget fetch+reupload path).

**7 more real bugs found+fixed via live testing, getting the attachments UI actually visible/usable**:
1. `displayUploadedFile()` (compose.ts) crashed clicking a carried-forward attachment -
   `attgrid.file.replace()`, since carry-forward entries have no `.file` at all (that's a classic
   locally-staged-upload field). Added a `jmapBlobId` branch, downloading the blob directly
   (`MailJmap.downloadBlobUrl()`, same `downloadBlob()` primitive the inline-cid-image resolution
   already uses) and opening it in a sized `egw.openPopup()` - same convention `displayUploadedFile()`
   already uses for every other type, deliberately NOT the Expose lightbox the message-view/preview
   uses (compose has never used Expose for its own attachment list, confirmed in
   `app.displayAttachment()` too - unifying that is a separate follow-up ralf flagged as "would be
   nice", not done here).
2. Both the attachments `<et2-details>` AND, one level up, the whole `.et2_file.mailUploadSection`
   box (`@no_griddata`, `empty($content['attachments'])` server-side) start disabled for an
   initially-empty content - `uploadStart()`'s existing un-disable only ever touched the details, since
   a real upload always re-renders the whole popup from non-empty content anyway. `set_disabled(false)`
   on both directly, since `disabled` is a one-shot expression evaluated only at initial render, not
   reactive to a later array-manager mutation.
3. Un-disabling the whole box also exposed its OTHER child, the "Send files as" filemode/expiration/
   password row - meaningless (and actively misleading, since `jmapEligible()` requires
   `filemode==='attach'`) for bare JMAP blob references. Gave it `id="filemodeRow"` and explicitly
   re-disabled it.
4. The collapsed-details summary preview (a single static row, `@attachments[0][...]`-bound) stayed
   permanently empty no matter what - traced all the way to `et2_core_widget.ts`'s
   `checkCreateNamespace()`: ANY widget with an `id` always gets its own array-manager
   perspective/namespace, and expandName()'s single-`@` prefix resolves relative to *that* namespace,
   not the true document root - so simply giving the grid an id (to reach it via `getWidgetById()`)
   silently broke its own root-scoped bindings. Reverted that id; a plain `loadFromXML()` rebuild via
   tree-walking (no id) also didn't help (this grid's own delegated array manager apparently isn't the
   same live instance `this.et2.setArrayMgr('content', ...)` updates - not confirmed why, not worth
   more digging for a cosmetic preview). Ended up just hiding that classic grid outright
   (`set_disabled(true)`, found via tree-walking from the id="attachments" grid's own existing id, no
   new id added) and replacing it with two new, fully JS-driven sibling widgets:
   `attachmentsSummaryName` (filename, grows via `style="flex:1"`, not bold) and `attachmentsMoreText`
   (a `+N` badge, `align="right" class="et2_bold"`, same convention as `app.ts`'s own
   `attachmentsBlockTitle = _data.length > 1 ? \`+${_data.length-1}\` : ''` for a received message's
   own attachment preview).
5. `detailsWidget.open = true` (forcing the details open on load) was wrong - ralf: "it should only
   open on hover, not permanent on first load" (`toggleOnHover="true"` already does that natively).
   Removed.
6. Hiding `filemodeRow` collapsed the gap between the attachments details box and the editor below it
   (that row's own height/margin was the only spacing there). Added an explicit `margin-bottom` to
   `.attachments` in the template's own `<styles>` block instead of relying on filemodeRow for it.
7. Adding attachments via `carryForwardAttachments()`'s `set_value()` creates brand-new row widgets
   (incl. an `et2_IInput` delete button per row) asynchronously - `set_value()` itself doesn't await
   their creation/upgrade, so `bootstrapCompose()`'s trailing `resetDirty()` call could run before they
   settle and never give them a clean baseline, tripping the close-prompt on an untouched popup. Fixed
   by deferring that final `resetDirty()` call by one macrotask (`await new Promise(r =>
   setTimeout(r, 0))`).

Removing a carried-forward attachment via the grid's "Delete" button was NOT separately verified -
still relies on the classic full-postback deletion cycle unchanged (`mail_compose.inc.php`'s generic
`$_content['attachments']['delete']` filter-by-`tmp_name` handling), which should work since it only
ever filters whatever `$_content['attachments']` the client currently holds - not confirmed live.

**Single-message inline forward built + live-verified 2026-08-31**: reuses the exact same
`bootstrapReply()`/`fetchForReply()`/`quoteOriginalMessage()`/`selectIdentityForRecipients()`/
`carryForwardAttachments()` machinery as reply-with-attachments - ralf: "same thing as reply with
attachments, just not setting To" (and, worth noting explicitly since forward-as-attachment/batch-
forward take a completely different code path in `composeMessage()` - "obviously only true for the
inline forward, not forward as attachment"). `bootstrapReply()` gained a `mode : 'reply' |
'reply_attachments' | 'forward'` param: `forward` skips setting `to`/`cc` and the RFC 5322 threading
headers (forwarding isn't a reply-thread continuation), uses classic `getForwardData()`'s own
unconditional `"[FWD] " + subject` prefix instead of "Re: ", and always carries attachments forward
(matching classic's own non-asmail `getForwardData()` branch, which populates attachments
unconditionally - no "reply_attachments"-style separate menu action needed for forward, it's just
what inline forward always does). `app.ts`'s `composeMessage()` gates JMAP-mode on
`settings.from === 'forward' && settings.mode === 'forwardinline'` (checked post-switch, since that's
already normalized regardless of which of `forward`/`forwardinline`/`forwardasattach` triggered it -
a batch or forwardasattach forward returns early via `egw.openWithinWindow()` before ever reaching
that check). `mail_compose.inc.php`'s `$jmapReplySkip` extended to `forward` too. classic
`getForwardData()`'s own composition (call `getReplyData()` for body/quote, then discard its
to/cc/in-reply-to side effects) is exactly why this shift-of-machinery reuse works cleanly. No new
bugs found - fell out of the reply-with-attachments work basically for free.

**Step 3 (attachments) done (2026-08-27, `c549213d28`), not yet live-tested.** `sendNewEmail()`/
`saveDraft()` now accept an attachments list, building a real `multipart/mixed` `bodyStructure` by
hand when present - `attachments`/`htmlBody`/`textBody` are RFC 8621 §4.1.4 convenience views the
server derives from `bodyStructure` on read, not independently settable on create, so the plain
`htmlBody`/`textBody` shortcut only applies to the no-attachment case. New `uploadAttachment()`
wraps jmap-jam's own `uploadBlob()` (RFC 8620 §6.3, unaffected by the WebSocket transport - blob
upload is always a plain HTTP POST). Found (via code reading, not yet live) a real jmap-jam pitfall
worth remembering: `uploadBlob(accountId, body, fetchInit)` shallow-spreads `fetchInit`'s own
`headers` over its base fetch options, so passing a `Content-Type` header there would silently
replace (not merge with) the `Authorization` header it already sets - retagging the `Blob`'s own
`.type` instead lets `fetch()` derive `Content-Type` automatically, sidestepping the whole issue.
`compose.ts`'s `jmapEligible()` now allows attachments except a "share instead of attach" filemode
(a `Vfs\Sharing` link, not a real upload - out of scope) or one carried forward from an original
message (needs reply/forward, Step 4, not built yet) - `uploadAttachmentsViaJmap()` fetches each
classically-staged attachment's raw bytes via the same menuaction `displayUploadedFile()` already
uses to preview one, reusing the existing upload widget/staging entirely, no new upload UI.

**Step 1 done+live-verified (2026-08-27):** `MailJmap.sendNewEmail()` (`mail/js/jmap.ts`) - plain
new-message compose, client-side, real-JMAP accounts only - plus the "jmapCompose" app-toolbar
toggle (persisted as an implicit `mail`/`jmapCompose` preference) wiring `compose.ts`'s
`trySendViaJmap()` into the existing compose popup's Send action as a narrowly-guarded parallel
path (no attachments/integration/S-MIME - falls through to the unchanged classic postback
otherwise). Confirmed working end-to-end via live WS frame inspection: `Identity/get` + `Mailbox/get`
by role, `Email/set` create in Drafts, `EmailSubmission/set` submit with the Drafts→Sent
`onSuccessUpdateEmail` patch, delivered correctly, Sent copy correctly placed and marked read, popup
closes cleanly without a spurious "unsaved changes" prompt.

**6 real bugs found+fixed getting there** (ralf live-testing + WS frame inspection each time):
1. Address-widget values can be a full `"Display Name <addr>"` string (autocomplete-selected), not
   a bare address - `{email: "Name <addr>"}` isn't a valid JMAP `EmailAddress`, Stalwart rejected
   submission with `noRecipients`. Fixed both sides (client-side small parser; server-side switched
   to `Api\Mail::parseAddressList()`, this codebase's existing RFC 822 parser).
2. A 7th and 8th instance of the [[project_jmap_imap_fallthrough_cleanup]] bug class surfaced
   live during testing (`Imap\Jmap::emailId2uid()`/`emailId2uidByPath()`, `Api\Mail::flagMessages()`)
   - fixed. Also surfaced an important systemic discovery: `Api\Mail::getInstance()` caches a failed
   profile as "defunct" for 5s, so ANY one unguarded-fallthrough failure also breaks other,
   unrelated calls on the same profile for the next few seconds (eg. opening a new compose window
   right after an unrelated flag-update failure).
3. The `jmapCompose` toggle was silently defeated whenever a message was selected/previewed before
   clicking Compose - the JMAP-mode flag was gated on `!settings.id`, but `settings.id` gets
   backfilled from the current selection for unrelated reasons even for a genuine new-message
   trigger. Fixed to gate on the caller's original intent (captured before that backfill runs).
4. **`JamWebSocketClient.requestMany()` (`mail/js/jmap-jam-websocket.ts`) silently dropped the
   actually-requested result on a callId collision** - Stalwart echoes the SAME callId for an
   implicit companion method call triggered by `onSuccessUpdateEmail`/`onSuccessDestroyEmail` (eg.
   an `Email/set` response alongside the triggering `EmailSubmission/set`), and naive
   `Object.fromEntries()`-based "last response wins" per callId then discarded the real result -
   found via ralf inspecting raw WS frames after a confusing generic "Failed to send message"
   despite Stalwart's response showing success. This is very likely also what caused the original,
   earlier-session "sent but missing from Sent folder" mystery. Fixed with a regression test
   (`mail/js/test/JamWebSocketClient.test.ts`) - a general jmap-jam-transport-layer fix, not
   specific to sending.
5. `window.close()` after a successful JMAP send tripped ETemplate's own "unsaved changes"
   `beforeunload` prompt, since the form never went through ETemplate's own `submit()`. Fixed with
   `skip_close_prompt()`, the same mechanism `et2_widget_button.ts` already uses for this.

**Step 5 (draft save/autosave) done+fixed the 9th fallthrough site (2026-08-27, `b7e41bb43f`)**:
initially deferred ("Not sure we want to follow that up now"), but once it started surfacing on
basically every compose session (after the WS-sharing fix made sessions live long enough to hit the
2-minute autosave mark), ralf asked for the real fix: `MailJmap.saveDraft()` - creates a
`$draft`-keyword Email on first save, updates it in place on later autosave ticks (tracked via
`compose.ts`'s `jmapDraftEmailId`) instead of accumulating a new draft every 2 minutes. Same
eligibility scope as `sendNewEmail()` (no attachments/integration/S-MIME/mailvelope), gated on the
`jmapCompose` toggle - classic autosave/save keeps working unchanged otherwise. Refactored
`sendNewEmail()`/`saveDraft()` onto shared private helpers (`resolveComposeContext()`, address
parsing, `draftEmailProperties()`, `createDraftEmail()`) rather than duplicating identity/mailbox
resolution a second time. `compose.ts`'s `saveAsDraft()` tries the JMAP path first for all three triggers (autosave, "Save as
Draft", and "Save as Draft and Print" - `print()` only needs *a* valid row-id, and the client-side
one built below is format-compatible, so there was no real reason to special-case it out, per ralf
2026-08-27, `17ee5b304d`), falling back to the unchanged classic postback (now
`saveAsDraftClassic()`) otherwise; on
success it builds a client-side row-id and syncs `content.data.lastDrafted`/the `lastDrafted`
widget, matching what `savingDraft_response()` already does for the classic path.

**WebSocket sharing fixed (2026-08-27, `e23aca3580`)**: ralf noted the compose popup opened its own
separate WebSocket/`MailJmap` instance instead of reusing the opener/main window's - wasteful (2
Stalwart connections for one user session) and would matter for anything push-related.
`MailApp.jmap` now reuses `window.opener.app.mail.jmap` when reachable+not closed (re-checked on
every access, not cached once) - the same `window.opener.app.mail.*` pattern already used elsewhere
in `app.ts` (`nmOwner()`, `customLabels`), mirroring how `window.egw` itself is already
cross-window-shared. Surfaced a real subtlety: a popup runs its own separately-loaded JS bundle, so
an error thrown by the (now possibly cross-window) jmap instance is an instance of a *different*
realm's `JmapUserError`/`JmapUnsupportedBackendError` class - `instanceof` silently never matches
even for a genuine one. Fixed every affected check (4 in `app.ts`, 1 in `compose.ts`) to compare
`e?.constructor?.name` instead - same bug class as [[feedback_cross_realm_instanceof]].

**Step 4 mapped, not yet built (2026-08-27)** - ralf: "reply/forward is more complicated as it might
sound... nothing we want to just start" - classic `mail_compose.inc.php` researched in depth before
any client-side design commitment. Key findings:
- **Signature**: `Mail\Account::read_identity($id)['ident_signature']` (one HTML string per
  identity) run through `Mail::merge()` for placeholder substitution (eg. sender's own name) -
  server-side contact-data resolution, can't fully move client-side. Placement governed by two
  plain prefs (`insertSignatureAtTopOfMessage`: below/above/append-at-send-only,
  `disableRulerForSignatureSeparation`).
- **The actual fragility**: identity switching mid-compose is today a full server postback
  (`mail_compose.inc.php:730-862`) that has to *relocate* the already-rendered signature inside the
  edited body via `<!-- HTMLSIGBEGIN/END -->` comment markers, falling back to a fuzzy
  `preg_quote()`+regex match of the cleaned old signature text if the markers got broken by editing,
  and silently giving up (stale/duplicate signature left behind) if even that fails.
- **Reply's "extra header"**: not a one-liner - a real From/To/Cc/Date block wrapped in
  `<fieldset class="originalMessage"><legend>original message</legend>...`, built in
  `getReplyData()` (`Api\Html::fieldset()`).
- **Reply-with-attachments vs forward**: only ONE attachment-carry-forward mechanism exists in the
  whole file. `reply_attachments` is a PHP `switch` fallthrough - calls `getForwardData()` first
  purely for its side effect of populating `$this->sessionData['attachments']`, then falls through
  into `getReplyData()` which overwrites body/headers but leaves attachments alone. Not two
  features, one mechanism composed via a fallthrough trick.
- **Plain-text signatures**: always authored as HTML - `convertHTMLToText()` (thin wrapper around
  the shared, non-trivial ~260-line `Api\Mail\Html::convertHTMLToText()` engine used all over the
  mail app, not signature-specific) converts at compose-open time for plain-text mode, including the
  same `Mail::merge()` contact-data resolution.

**Proposed client-side architecture** (pending ralf's review before implementation starts):
1. **Decided (2026-08-27, ralf)**: `Identity/get` (RFC 8621 §6.2 already defines `textSignature`/
   `htmlSignature` natively) always routes through EGroupware's own `Account::identities()`-based
   synthesis - for BOTH backends, not just the shim - rather than ever passing through to
   Stalwart's native Identity/get. Confirmed live: we don't sync any identity/signature data to
   Stalwart today (`Api\Mail\Smtp\Stalwart`'s account provisioning only pushes the mailbox's
   `description`/full name, `Stalwart.php:218` - no email/replyTo/bcc/signature, no concept of
   EGroupware's multiple-identities-per-mailbox model), so Stalwart's own Identity/get would return
   nothing usable anyway. This also resolves the "does Stalwart support our above/below signature
   placement preference" worry from a different angle than expected: RFC 8621's Identity object has
   no placement/position field at all, on *any* JMAP server - a JMAP server only ever hands back
   signature *text*; where that text goes in the composed body is entirely a *client* decision when
   building the `Email/set` create. Our client-side compose was always going to implement that
   placement logic itself regardless of backend, so there's no dependency on server support for it
   either way.
   **Code-shape consequence**: `Api\Mail\Jmap\Identity` (`api/src/Mail/Jmap/Identity.php`) is
   currently a bare `Type` subclass with no overrides - `get()` falls through to `Type`'s generic
   implementation, a genuine passthrough to Stalwart via `$this->jmap->call()`. Needs an actual
   `get()` override (declining `set()`, identities aren't user-editable through compose) that
   synthesizes RFC 8621 Identity objects from `Account::identities()` unconditionally, for both
   backends - the same implementation Step 2's shim Identity class was going to need anyway, so
   Steps 0 and 2's Identity pieces merge into one shared implementation rather than two. Clean
   escape hatch if Stalwart-native sync ever happens later: remove the override, it reverts to
   being a real passthrough - no other code needs to change.
   Also directly solves the HTML→plain-text signature conversion problem (`Api\Mail\Html::
   convertHTMLToText()`, needs server-side `Mail::merge()` contact-data resolution) for free -
   since this is server-side PHP synthesizing the response either way, it can just call the
   existing converter and return both `htmlSignature` and pre-converted `textSignature` in the same
   response, no separate conversion endpoint needed.
2. **Identity switching stops needing the marker-relocate hack entirely** (not just "port it
   client-side") - once the client holds the raw signature text per identity, switching identity is
   "re-run the same compose function against the *original* body already held in memory," no
   regex search-and-replace of rendered HTML at all. This eliminates the single most fragile piece
   of the classic implementation outright.
3. Port the reply attribution fieldset-block shape 1:1 from `getReplyData()`'s actual output -
   independent of signature logic, don't conflate the two.
4. One shared client-side `carryForwardAttachments(originalEmailId)` helper (blob-based, reusing
   Step 3's upload/blobId flow), called from both reply-with-attachments and forward - matching the
   classic code's real shared-mechanism shape instead of the fallthrough-trick composition.
5. Plain-text signature conversion **stays a server-side concern**, same class of decision as
   S/MIME/TNEF (mature shared utility, merge-field resolution needs server-side contact data, no UX
   reason for it to be interactive) - concretely, `Identity/get` returns the server-pre-converted
   `textSignature` alongside `htmlSignature`, so the client never calls a conversion endpoint at
   all, just picks whichever variant matches the current compose mimeType.

**Future phase, beyond Step 9 (ralf, 2026-08-27): eliminate the ETemplate postback cycle from
compose entirely.** Once send/save/draft/attachments/identity-switch are all JMAP-driven
client-side (Steps 1-5 complete), routing any of it through a server round-trip is vestigial - keep
`.xet` templates purely as a widget-tree/layout definition (as any other ETemplate2 screen does),
but stop using `mail_compose.compose()`'s current job of pre-computing reply/forward content
(quoting, attribution header, signature, attachment list) server-side into the initial content
array. Instead: the compose window opens with an essentially empty/generic template shell, and
client JS populates everything after the JMAP fetches (`Identity/get`, `Email/get` for reply/
forward) resolve - the compose *type* (`from=reply`/`forward`/`composefromdraft`/etc.) becomes a
client-side JMAP fetch parameter instead of driving server-side branching in `getComposeFrom()`.
One structural piece likely can't fully disappear: opening the popup at all still needs some
initial PHP hit to produce a URL for `egw.openPopup()` - but that hit could return the *same*
static, content-free template every time, regardless of compose type. Positioned as its own phase
after Step 4 proves client-side quoting/signature/attachments for real, likely right alongside or
just before Step 9 (retire classic compose) - not something to fold into Step 4 itself.

**Refinement (ralf, 2026-08-27, via Nathan)**: even that residual server hit may not be needed on
every open - `Et2Template` can be instantiated directly client-side and populated via its own
`load(newContent, ...)` method (confirmed real: `api/js/etemplate/Et2Template/Et2Template.ts:282`,
`public async load(newContent?, newSelectOptions?, newReadonlys?, newModifications?)`) - so compose
could supply data straight from JMAP fetches instead of a server-computed content array. `load()`'s
own docblock: "Asks the server if we don't have that template on the client yet" - so the template
*definition* (the `.xet`'s compiled widget-tree shape) may still need one fetch the first time, but
gets cached client-side after that; only the *data* needs to come from somewhere on every open, and
that's the part JMAP fetches replace. Not yet researched: how to construct/attach the `Et2Template`
instance itself outside the normal server-rendered-page bootstrap flow, or whether any precedent for
that already exists elsewhere in the codebase - worth investigating properly once this phase is
actually reached, not blocking anything now.

**First slice already built ahead of this phase (2026-08-27)**: `mail_compose::compose()` got a
narrow, single-purpose version of this idea now, pulled forward specifically for the identity/
signature piece rather than waiting for the full future phase - see the "Identity/get routing
decided" note above. A new `$jmapModeNewCompose` guard (`$actionToProcess === 'compose'` - i.e.
`getComposeFrom()` was never invoked, a genuinely blank new message - `&& $_GET['jmap'] === '1'`)
skips the classic signature-insertion block entirely, mirroring `mail_ui::displayMessage()`'s
existing "minimal content, client builds the rest" pattern for exactly that one piece. Client-side,
`MailCompose.bootstrapSignature()` (called from `setEtemplate()`) and `updateSignatureForIdentity()`
(replacing the classic full-postback `submitOnChange` handler for the "From" dropdown when
`isJmapMode`) now own signature insertion entirely - `MailJmap.getIdentities()` +
`composeBodyWithSignature()` (`mail/js/jmap.ts`), tracked via a plain substring
(`insertedSignatureBlock`/`signaturePlacement`) rather than the classic marker/regex relocate hack,
since the client already holds enough state to just strip its own last insertion back out safely
(falls back to leaving content untouched, never guesses/duplicates, if it can't confidently locate
it - eg. the user edited in/around it). 10 new unit tests
(`mail/js/test/ComposeBodyWithSignature.test.ts`), full JS suite still 155/155, PHP suites still
56/56 - not yet live-tested in the browser.

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
  backends since it operates on MIME/blobs, not on the IMAP connection. **Decided (2026-08-27):**
  the endpoint uploads its own result and returns a `blobId`, not raw bytes - it's server-side PHP
  either way (private key material), so it can call `Api\Jmap::uploadBlob()` (post-rename name)
  itself rather than shipping the signed/encrypted MIME back to the client just for the client to
  re-upload it. Client-side then swaps a single `application/pkcs7-mime` blobId into the
  `Email/set` `bodyStructure` in place of the multipart structure it would otherwise build - no new
  client-side upload path needed for the S/MIME case, reusing the same blob-reference shape
  attachments already use.
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
- **Draft semantics: reimport-and-replace, for BOTH backends** (revised 2026-08-27 - originally
  decided shim-only, assuming real-JMAP could do true incremental update instead). Found live:
  Stalwart rejects `Email/set update` touching body/header properties on an existing Email
  ("Invalid property or value") even though the identical properties are accepted on `create` -
  **Stalwart's blob store is write-once, an Email's content can never be modified in place, only
  deleted** (ralf). Matches `saveAsDraft()`'s existing classic whole-message-replace behavior
  anyway - no semantics change there, and no backend-specific branching needed either.
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
2. **DONE + live-verified 2026-08-31.** IMAP-shim `EmailSubmission` emulation (the one genuinely
   new substantial backend piece - locate draft MIME → `Api\Mailer::send()` via `Api\Mail\Smtp` →
   `emailImport()` a Sent-copy → Drafts cleanup) + shim `Identity` synthesis from `Mail\Account`.
   Working result: same plain-text new-message send, now working against an IMAP-shim test account
   too - see the "Step 2" write-up and its own "live-testing fixes" section near the top of this doc
   for the design and the 5 bugs found getting there.
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
10. **Eliminate the ETemplate postback cycle from compose** (ralf, 2026-08-27, future phase - see
    its own write-up above near Step 4's mapping): once nothing about compose depends on server-side
    processing anymore, stop having `mail_compose.compose()` pre-compute reply/forward content into
    the initial ETemplate content array - the popup opens with a generic, content-free template
    shell instead, and client JS populates everything from JMAP fetches. `.xet` templates keep their
    role as pure layout/widget-tree definitions; they stop being a request/response transport.
    Positioned after Step 4 proves client-side quoting/signature/attachments, likely alongside or
    just before Step 9.

## Attachment-listing + TNEF-as-one-of-several-attachments follow-up (2026-09-02, BUILT, not yet live-tested)

Real bug report: a TNEF (`winmail.dat`) meeting invite mixed in alongside an ordinary text body was
shown as an inert, undecoded `winmail.dat` attachment - not its unpacked `.ics`. Root cause: this
gap predates the compose migration - `mail-jmap-modernization`'s own `AttachmentJmap::
resolveWinmailJmap()`/`ajax_resolveWinmail` only ever handled "the whole message IS a TNEF blob"
(triggered client-side, `mail/js/app.ts`'s `renderMessageInto()`, only when a classically-built
`attachmentsBlock[0]` happened to already be winmail-flagged); the JMAP-native attachment-listing
path (`resolveAttachmentsJmap()`/`jmapAttachmentsToLegacy()`) had zero TNEF awareness at all and
just passed an `application/ms-tnef` part through as an opaque, undecoded entry. The classic
(non-JMAP) `Api\Mail::getMessageAttachments()` path never had this gap - it already decodes TNEF
wherever it sits among a message's other attachments.

Also prompted an architecture question (ralf): attachment listing for a JMAP-native row was still
a server-side PHP→JMAP round trip (`ajax_fetchAttachments`) even though the row list itself is
already fetched client-side JMAP-direct - an extra hop for metadata the browser could fetch itself.

Fix, in the same "client-side JMAP metadata, server-side presentation" split used throughout this
project:
- `MailJmap.fetchAttachmentsMetadata(rowId)` (`mail/js/jmap.ts`) - new client-side `Email/get`
  (`properties: ['attachments']`) fetch, same pattern as `fetchBodyFromMessagePart()`/
  `fetchForForwardAsAttachment()`. Returns the raw RFC 8621 `EmailBodyPart[]`, or `null` on any
  failure (non-JMAP-native account, etc).
- `AttachmentJmap::resolveAttachmentsJmap()` gained an optional `$jmapAttachments` param - when
  given (the client's own fetch), its own `Email/get`/`structureGet()+emailBodyFields()` round trip
  is skipped entirely, going straight to `jmapAttachmentsToLegacy()`/`createAttachmentBlock()` (the
  Link::set_data() download-token building, still inherently server-side/session-bound).
  `ajax_fetchAttachments()` (`mail_ui.inc.php`) passes an optional `$_attachments` array through.
- `AttachmentJmap::resolveWinmailJmap()` gained optional `$partID`/`$blobId` params - when both are
  given (the client already found the TNEF entry itself), the "search the whole attachments list"
  step is skipped, going straight to a `fetchBlobBytes($acc_id, $blobId, ...)` fetch (backend-uniform
  for both Stalwart and the shim) instead of the old bespoke Stalwart-downloadBlob()-vs-shim-
  fetchRawPart() branching. `ajax_resolveWinmail()` passes `$_partId`/`$_blobId` through. Still
  returns only the TNEF's own unpacked sub-attachments, same as before.
- `MailApp.resolveJmapAttachmentsBlock(rowId)` (`mail/js/app.ts`, new, called from
  `renderMessageInto()` in place of the old direct `ajax_fetchAttachments` call): fetches metadata
  client-side first; if it contains a `application/ms-tnef`/`winmail.dat` entry, fetches the
  OTHER (sibling) attachments' presentation block and the TNEF's own decoded sub-attachments'
  block in parallel, then splices the decoded sub-attachments into the sibling list IN PLACE OF the
  one raw TNEF entry (renumbering `attachment_number` afterward to avoid collisions between the two
  separately-PHP-built blocks) - preserving any other, non-TNEF attachments in the same message
  untouched. Falls back to the old single-call classic resolution whenever the client-side metadata
  fetch fails, or the TNEF decode itself fails.

Not touched: `createAttachmentBlock()`'s per-file download tokens (still a classic IMAP fetch at
click time - pre-existing, documented scope limit, unrelated to this bug); the OTHER
`renderMessageInto()` branch (a classically-built `attachmentsBlock[0]` already winmail-flagged) -
left as-is, orthogonal fallback for non-JMAP-native accounts.

**Status**: implemented + live-verified server-side against the real Stalwart test account (acc_id=1),
`php -l` clean, no new TS errors vs baseline.

Live-testing (throwaway PHPUnit test, real ticket-88311 `.eml` imported into acc_id=1 via
`Email/import`, plus a synthetic second case with a genuine `sibling.txt` attachment alongside the
TNEF) caught one real bug before it shipped: `resolveWinmailJmap()`'s pre-existing top-of-function
guard (`if (!$uid || !$mailbox || !$acc_id) return null;`) ran unconditionally, *before* branching
on the new `$partID`/`$blobId` fast path - forcing the same costly lazy `$idParts['msgUID']`/
`['folder']` IMAP-EMAILID resolution `resolveAttachmentsJmap()`'s analogous `$jmapAttachments` fast
path deliberately avoids, and for a Stalwart row that resolution returned `NULL`, so the guard fired
and the decode never ran at all - the exact backend the bug report came from. Fixed by moving
`$uid`/`$mailbox` resolution into the local-shim-only branch (mirroring `resolveAttachmentsJmap()`'s
already-correct pattern exactly); `tnefAttachments()`'s per-sub-attachment `is_winmail` fingerprint
falls back to `emailID` instead of `msgUID` when the latter was never resolved (Stalwart) - the
fingerprint stays unique per message, though per-file download of one of these decoded
sub-attachments remains not-JMAP-native for Stalwart either way, pre-existing/documented scope limit.

Re-run after the fix: both live tests pass. The real KEYENCE reproduction decoded into **three**
sub-attachments, not just the expected meeting invite - the TNEF also embedded two PDF catalogs
(`text/calendar` "KEYENCE: Terminvereinbarung 3D-Drucker" + two `application/pdf` entries) - all
three now show correctly instead of the opaque `winmail.dat`. The synthetic sibling case confirmed
both halves: `resolveAttachmentsJmap($rowID, null, false, $siblingsOnly)` correctly resolved just
`sibling.txt` without touching the TNEF entry, and `resolveWinmailJmap()` still decoded correctly
with a non-TNEF sibling present.

Not yet done: exercising the actual browser/JS layer (`resolveJmapAttachmentsBlock()` in app.ts) end
to end, and the ordinary (no-TNEF) attachment-listing path against the local IMAP shim - only the
Stalwart/PHP side was live-verified above.

## S/MIME message-list attachment-icon false positive (2026-09-02, DONE, live-verified vs Stalwart)

Real bug report (ralf): "we should NOT show an attachment icon in the list for s/mime signed or
encrypted messages, not having real attachments, that is confusing the user."

Root cause: the row-list attachment icon (`attachment_icon`/`attachments` fields, rendered by
`mail/templates/{default,mobile}/index.xet`'s `<et2-image src="$row_cont[attachment_icon]">`) comes
from exactly one place for BOTH backends - `MailJmap.email2row()` (`mail/js/jmap.ts`), built from
the JMAP `hasAttachment` boolean (Stalwart's own, or the shim's `Imap::structureHasAttachment()`).
Neither backend's `hasAttachment` excludes the S/MIME wrapper part itself: a signed message's
`application/pkcs7-signature` part conventionally carries `Content-Disposition: attachment`, and an
encrypted message's `application/pkcs7-mime` part is emitted `Content-Disposition: inline` by this
codebase's own `Horde_Crypt_Smime` (`vendor/egroupware/crypt/lib/Horde/Crypt/Smime.php`) - both
match `structureHasAttachment()`'s qualifying conditions, and (per RFC 8621, and almost certainly in
practice) Stalwart's own `hasAttachment` the same way. `mail/tests/HasAttachmentTest.php` had zero
S/MIME cases - never caught.

Fix - detect "this message is ENTIRELY an S/MIME wrapper" client-side, uniformly for both backends,
using the RFC 8621 §4.1.3 generic header-property mechanism already established for MDN detection
(`MDN_HEADER_PROPERTY`, `mail/js/jmap.ts`/`api/src/Mail/Jmap/Imap.php`):
- New `MailJmap.CONTENT_TYPE_HEADER_PROPERTY` (`mail/js/jmap.ts`) - `'header:content-type'` (bare
  form, deliberately NOT `:asText` or explicit `:asRaw` - see below), added to the same properties
  arrays as `MDN_HEADER_PROPERTY` (4 call sites: `getRows()`, `getThreadedRows()`, `refreshRows()`,
  one more `getRows()`-adjacent site).
- New `MailJmap.isSmimeWrapperOnly(contentTypeHeader)` (`mail/js/jmap.ts`) - parses the raw header
  text: `application/(x-)?pkcs7-mime` (encrypted, or opaque-signed) at the top level, or
  `multipart/signed` with a `protocol="application/(x-)?pkcs7-signature"` parameter (detached-
  signed) - matching `Api\Mail\Smime::$SMIME_TYPES` (`api/src/Mail/Smime.php`). Used in
  `email2row()` to override `hasAttachment` to `false` for the icon fields only when true.
- Shim support (`api/src/Mail/Jmap/Imap.php`): new `Imap::CONTENT_TYPE_HEADER_PROPERTY` constant
  (same string, must match verbatim), a `$wantContentType` flag in `emailGet()` mirroring
  `$wantMdn` exactly, one more `$query->headers('contenttype', ['Content-Type'], ['cache' => true,
  'peek' => true])` call (cheap - `$query->structure()`/`envelope()`/etc. are already fetched
  unconditionally for every row regardless), and `emailFromFetch()` populating the property from
  `$data->getHeaders('contenttype', HEADER_PARSE)` via the existing `firstHeaderValue()` helper -
  the exact same shape as the MDN header, just a different header name.

**Two real surprises found only by testing live against the real Stalwart server** (a mocked/
hand-built JMAP response would never have caught either):
1. `header:content-type:asText` always comes back `null` on Stalwart - confirmed by also checking
   `header:message-id:asText`/`header:subject:asText` on the SAME message (both returned correct
   values), so it's not a general `header:` mechanism failure, just this one header. Content-Type is
   a "structured" RFC 5322 header; "asText" is specified for unstructured free-text ones, and
   Stalwart evidently declines to serve it for Content-Type specifically.
2. `header:content-type:asRaw` (the form that DOES return a value) comes back under a DIFFERENT
   response key than requested - the bare `"header:content-type"`, not `"header:content-type:asRaw"`.
   RFC 8621 §4.1.3 defines the bare form as equivalent to (and, evidently, canonicalized to) `:asRaw`
   when no form suffix is given; requesting the explicit `:asRaw` suffix still gets the value, just
   echoed back under the canonical bare key - a client that (reasonably) expects the server to echo
   back the literal property string it asked for, the same way `:asText` already behaves, silently
   gets `undefined`/`null` instead. Fixed by requesting the bare form (`'header:content-type'`)
   outright, matching the actual canonical response key.

**Live-tested** (throwaway PHPUnit test, real signed/encrypted messages built via this codebase's own
`Api\Mailer::smimeEncrypt()`/`Horde_Crypt_Smime`, imported into the real Stalwart test account,
acc_id=1): signed and encrypted messages both now correctly suppress the icon
(`isSmimeWrapperOnly=true` → effective icon `false`); a plain control message with a genuine
attachment (`multipart/mixed`, real .txt attachment) is unaffected (`isSmimeWrapperOnly=false` →
icon still shown) - confirming no regression for ordinary attachments.

**Shim side live-verified too (2026-09-03, ralf)**: confirmed working against a real IMAP-shim
account in the browser - icon correctly suppressed for S/MIME messages there as well, matching the
Stalwart behaviour above. (Previously only code-review parity with the shipped/tested
`$wantMdn`/MDN-header path - no accessible shim account in the phpunit test environment at the
time this was written - `emailGet()`'s `$wantContentType`/`$query->headers('contenttype', ...)`
and `emailFromFetch()`'s branch turned out to work as expected.)

**Known accepted limitation** (deliberate, matches this project's per-row-listing cost discipline
elsewhere): only suppresses the icon when the ENTIRE message is nothing but the S/MIME wrapper. A
signed message whose signed payload is itself `multipart/mixed` with a genuine extra attachment
would still lose the icon - detecting that would need a full `bodyStructure` fetch at list time (for
the local shim, `Imap::emailBodyFields()`'s `bodyValues` computation currently piggybacks on any
`bodyStructure` request unconditionally, and even for Stalwart itself, `bodyStructure` is real
per-message tree data, not a cheap single header) - the same "avoid extra per-row IMAP/JMAP cost at
list time" tradeoff already made throughout this project. Matches Thunderbird/Outlook's own observed
behavior for the same case.

## Attachments-always-become-sharing-links bug report + JMAP-compose attachment feature-parity gap (2026-09-02, DONE, live-verified vs Stalwart)

Real bug report (a test user, via ralf): attachments in sent mail always arrive as VFS sharing
links, not real attachments. Investigation found two SEPARATE things, not one:

**1. Likely explanation, but NOT a regression** - classic compose (`mail_compose.inc.php:508-546`)
has ALWAYS auto-switched `filemode` from `attach` to `link` (or `readonly` for a directory) whenever
the running total of `uploadForCompose` uploads exceeds `Api\Config::read('mail')
['attachment_limit_mb']` (default `self::$maxAttachmentSizeDefault`, 26 MB) - working as designed,
unrelated to this migration. Whether this explains the specific report depends on the test user's
attachment size/mode, not yet confirmed with them directly.

**2. Real bug, this migration's own gap**: `mail/js/compose.ts`'s JMAP-mode attachment paths
(`carryForwardAttachments()`/`uploadLocalAttachmentViaJmap()`/`vfsUpload()`, all funneling through
`mergeAttachmentEntries()`) build rows with NO real local `.file` at all - just a bare
`{jmapBlobId, jmapProfileID}` (an already-uploaded JMAP blob reference) or `{jmapVfsPath}` (a VFS
path never downloaded client-side). `jmapEligible()`'s own attachment check only excludes
classic-shaped `{uid, folder/partID}` rows, NOT these - so a reply/forward-with-attachments or a
fresh local/VFS upload, all done in JMAP mode, stays "JMAP-eligible" and normally sends fine via
`trySendViaJmap()`. But whenever that JMAP send attempt itself doesn't go through - a blocking
cross-app toggle (`to_tracker`/`to_infolog`/`to_calendar`), or an account whose backend doesn't
actually support JMAP-native send - `jmapEligible()`/`trySendViaJmap()` return `false` and compose
falls through to the CLASSIC postback with these still-JMAP-shaped rows in `content.attachments`.
Classic's own attachment-embed loop (`mail_compose.inc.php`'s `createMessage()`) had no branch for
either shape: `isset($attachment['file'])` is false, `parse_url(null, ...)` warns, and
`addAttachment()` gets a bogus `temp_dir/''` path - silently dropping (or erroring on) the
attachment instead of sending it, matching the "always a link, never the real thing" symptom from a
slightly different angle (the file goes missing rather than becoming an explicit link).

Fix, in `mail_compose::createMessage()`'s existing `elseif ($_formData['filemode'] ==
Vfs\Sharing::ATTACH)` branch (`mail_compose.inc.php` ~2860-2925):
- `!empty($attachment['jmapBlobId'])` - fetched via `AttachmentJmap::fetchBlobBytes()` (the same
  backend-uniform blob-fetch primitive the TNEF-as-attachment fix above uses) and embedded via
  `addStringAttachment()`. A blob that's gone (deleted/expired) throws the same
  `Api\Exception\WrongUserinput` shape the pre-existing uid/folder branch already used for its own
  "message no longer available" case, rather than silently producing a broken message.
- `!empty($attachment['jmapVfsPath'])` - this one already IS a real VFS file, just never had `file`
  set - resolved to `Vfs::PREFIX.$attachment['jmapVfsPath']` and handed to the EXISTING
  `addAttachment()` call, identical to the pre-existing `isset($attachment['file']) && parse_url(...)
  == 'vfs'` branch right below it.
- `mail_compose::compose()` now also exposes `$content['attachmentLimitMb']` (same config/default as
  classic) so the client can read it - added for the next point.

**Feature-parity gap, also addressed**: `compose.ts`'s JMAP-mode upload paths had ZERO size-based
awareness at all (classic's own switch-to-link behavior never applied to them, per the bug's own
diagnosis above). A full port isn't clean here - classic's "switch to link" needs the file to
actually exist in VFS already, which a bare uploaded JMAP blob never does (a real fix would need
materializing the blob into VFS first, a materially bigger feature, not built). Implemented instead:
`MailCompose.totalAttachmentSizeMb()` (pure, static, unit-tested) sums `content.attachments`' sizes;
`mergeAttachmentEntries()` (the single choke point every JMAP-mode attachment path already funnels
through) calls it on every merge and warns via the same translated message classic already shows
(new phrase, `mail/lang/egw_{en,de}.lang`: "The total size of the attachments exceeds the limit of
%1 MB" - without classic's own ". Switched to download link" suffix, since nothing actually switches
here) - honest about the limitation rather than silently pretending to be as smart as classic.

**Live-tested** (throwaway PHPUnit test against the real Stalwart test account, acc_id=1): a
`jmapBlobId` row (a real uploaded JMAP blob) and a `jmapVfsPath` row (a real VFS file) both now embed
their actual content correctly via `mail_compose::createMessage()` (verified: filename AND real byte
content both present in the resulting raw MIME - not just the filename) - previously either would
have been silently dropped or produced a warning/error. A missing/expired `jmapBlobId` correctly
throws `WrongUserinput` instead of producing a broken or empty attachment.

**Test coverage added**:
- `mail/js/test/ComposeAttachmentSizeLimit.test.ts` (4 cases) - `MailCompose.totalAttachmentSizeMb()`
  unit tests (empty, summed, VFS-placeholder-`size:0` ignored, missing `size` treated as 0), same
  pure-static-function-only pattern `ComposeBodyWithSignature.test.ts` already established (this
  whole area's other methods are too DOM/et2-entangled to unit test without a much bigger harness -
  matches the pre-existing gap the bug investigation itself flagged).
- The `createMessage()` PHPUnit live test (3 cases: jmapBlobId embed, jmapVfsPath embed, missing-blob
  throws) was NOT committed to the repo test suite - scratch-only, run manually, since it requires a
  real Stalwart round trip. Kept as a template for a future proper `LoggedInTest`-based fixture; see
  this doc's earlier TNEF/S-MIME live-test sections for the same pattern already used twice this
  session.

**"Materializing a JMAP blob into VFS" turned out unnecessary - DONE 2026-09-03 anyway, via
Share-as-link attachments**: the assumption above was that auto-switching needed a real VFS file to
share from. It doesn't - `_getAttachmentLinks()` already shares straight from a plain temp-dir path
(`parse_url($attachment['file'],PHP_URL_SCHEME) != 'vfs'` falls back to
`$GLOBALS['egw_info']['server']['temp_dir'].'/'.basename($path)`, the exact same convention a
classic upload already relies on), and `ajax_getAttachmentLinksBody()`'s
`resolveJmapAttachmentsToFiles()` (see "Share-as-link attachments" above) already downloads any
JMAP-mode attachment shape into exactly such a temp file before sharing it - no VFS materialization
step needed at all. With that plumbing in place, `warnAttachmentSizeLimit()` now auto-switches
`filemode` to `Vfs\Sharing::LINK` when the total exceeds `attachmentLimitMb` (same message wording
as classic, "...Switched to download link") instead of only warning - `currentEmailFields()`'s
`forSend` handling picks up the new filemode on the next send automatically, same as if the user
had chosen it manually. Not separately live-tested (would need staging >25MB of attachments via
browser automation) - relies on the same, already-verified, `ajax_getAttachmentLinksBody()` path.

**Still not done / deliberately out of scope**: enforcing the size limit as a hard block rather than
a warning; the classic size-limit switch's own directory-attachment handling
(`is_dir($upload['file'])`, `mail_compose.inc.php:531-536`) has no client-side-VFS-attach equivalent
to port at all, since the new VFS picker only ever selects files, never a whole directory
(unverified against every VFS picker widget still in use, but no directory-selection code path was
found in `compose.ts`).

## `jmapEligible()` blockers survey + to_infolog/to_tracker/to_calendar cross-app integration (2026-09-02, DONE, live-verified vs Stalwart)

ralf asked, apart from the (soon-to-be-removed) jmapCompose toggle itself, what else makes
`jmapEligible()` return `false`, and for each: postponed pure-client work, or a genuine server-side
dependency? Answered and then implemented the one real server-side blocker:

1. **`this.app.mailvelope_editor` (PGP compose active)** - pure client backlog. Mailvelope encrypts
   entirely in the browser extension before send ever runs; `MailJmap` has zero handling for an
   `openpgp`-mode body at all (classic's `createMessage()` has a dedicated `case 'openpgp':
   $_mailObject->setOpenPgpBody(...)` branch with no JMAP-native equivalent) - just an unwritten
   body-mode branch, no new server capability needed. NOT implemented this round.
2. **`filemode !== 'attach'`** (share-as-link chosen) - **implemented 2026-09-03**, see its own
   write-up below ("Share-as-link attachments").
3. **A classic `{uid, partID/folder}`-shaped attachment row** - a safety net for content
   `mail_compose.inc.php` still renders classically server-side that compose.ts's own JMAP
   bootstrap never took over. The one concretely identified case: true draft continuation (editing
   an already-saved draft, saving back to the SAME draft) has no JMAP bootstrap at all yet -
   `bootstrapComposeAsNew()` only covers "reopen as a fresh new message". Mostly client work
   (reusing `carryForwardAttachments()`'s existing carry-forward machinery), plus needs checking
   whether JMAP-native draft-save supports "update this specific draft" or only "create new". NOT
   implemented this round.
4. **`blockingToggle` (to_tracker/to_infolog/to_calendar)** - the one GENUINE server-side gap:
   classic's implementation (`mail_compose.inc.php`, formerly ~3698-3735) runs AFTER building a
   classic `Api\Mailer`, using its `getRaw()` output - a JMAP-native send never builds one at all,
   so there was no raw `.eml` to hand off. **Implemented below.**

### Design

Classic's flow, unchanged and still exactly what a real popup ends up doing: `Framework::popup()`
opens `/index.php?menuaction=<app>.<class>.mail_import&egw_data=<token>&app=<app>[&entry_id=<id>]`
- `<token>` (`Link::set_data(null, 'mail_integration::integrate', [...], true)`) is a DEFERRED
callback the target app's own `mail_import` handler invokes later (via `Link::get_data()`, once its
popup actually loads with a real `$_GET['app']`/`$_GET['rowid']` context) to get back
`$mailaddresses/$subject/$body/$attachments/$date/$rawMail/$icServerID` - `mail_integration::
integrate()`/`get_integrate_data()` themselves needed ZERO changes, since every attachment handed to
them below already looks exactly like a classic upload (a real, already-existing `file` path).

New pieces, all in the JMAP-native send path only:
- `MailJmap.sendNewEmail()` (`mail/js/jmap.ts`) now returns `{emailId, mailboxId}` (was `void`) - the
  just-sent message's own id and which mailbox (Sent) it landed in, needed to fetch its raw source
  back afterward.
- `MailCompose.jmapEligible()` (`mail/js/compose.ts`) - the `to_tracker`/`to_infolog`/`to_calendar`
  check and its now-pointless `forSend` param are both gone; these toggles no longer block JMAP
  eligibility at all.
- `MailCompose.integrateSentMessage()` (`mail/js/compose.ts`, called from `trySendViaJmap()` right
  after a successful send, wrapped in its own try/catch so a failure here is never misreported as a
  send failure - the mail already went out) - a no-op unless at least one toggle is checked.
  Otherwise: builds a synthetic rowId from `{emailId, mailboxId}` (`'mail::'+account_id+'::'+
  profileID+'::'+mailboxId+'::'+emailId`, the exact same shape every other mail row already uses),
  fetches the raw source via the EXISTING `MailJmap.fetchRawSource()` (zero new client-side JMAP
  code needed - "view source" already built this), and calls the new
  `mail.mail_compose.ajax_integrateSent()` with `to`/`cc`/`bcc`/subject/body/attachments (reusing
  `currentEmailFields()`'s own already-uploaded-to-the-right-account `{blobId,...}`/`{vfsPath,...}`
  attachment shape - the exact same array the message itself was actually sent with, so no
  per-row profileID handling is needed unlike the TNEF-style fixes elsewhere in this doc) + the raw
  eml + which toggles were checked + the `to_integrate_ids` entry, if any.
- `mail_compose::ajax_integrateSent()` (`mail/inc/class.mail_compose.inc.php`, new) - the one place
  with real new logic: resolves each attachment (`{blobId,...}` via `AttachmentJmap::
  fetchBlobBytes()`, the same backend-uniform primitive the TNEF-as-attachment fix uses; `{vfsPath,
  ...}` via `Vfs::PREFIX.$vfsPath`, already a real VFS file) into a real temp file, writes the raw
  eml to another, fills `mailaddresses['from']` from the sending account's own identity (server-side
  - compose.ts has no separate "from identity" field to read one from), HTML-converts the body if
  needed, then for each checked app key calls `Api\Hooks::single()`/`Link::set_data()`/
  `Framework::popup()` - IDENTICAL to classic's own block, just with client-supplied inputs instead
  of `$this->sessionData`. `Framework::popup()` opens the popup itself via the framework's own
  JSON-response-apply mechanism (`Json\Response::get()->apply('egw.open_link', ...)` for a JSON
  request) - no client-side popup-opening code needed at all.

**Live-tested** (throwaway PHPUnit test against the real Stalwart test account, acc_id=1, calling
`ajax_integrateSent()` directly): a `blobId`-shaped attachment resolves to a real temp file with the
correct content; the popup URL correctly targets the app's own registered `mail_import` menuaction
(`infolog.infolog_ui.mail_import`) with the right popup size from its hook registration; the
`Link::set_data()` token's raw stored params (inspected via `Api\Cache::getSession(Link::class,
$id)` directly, NOT `Link::get_data()` - that method actually EXECUTES the stored call, which needs
a real `$_GET['app']`/`$_GET['rowid']` context this test doesn't simulate) contain the correct
mailaddresses (including the server-filled `from`), subject, body, attachment, and raw eml path;
checking multiple app keys at once (`to_infolog`+`to_tracker`) correctly opens a popup per key.

Live-tested by ralf against a real InfoLog entry (2026-09-02): confirmed working once his own
"Save mail as" preference (`saveAsOptions`) was set to `add_raw`/`no_attachments` - the raw `.eml`
is only ever attached for those two option values, pre-existing/shared logic
(`mail_integration.inc.php:244-256`), unrelated to this feature; the default (`text`) never included
it, for classic sends either.

**Shim backend confirmed working too (2026-09-03, ralf)** - a real end-to-end browser run against
the IMAP-shim backend (toggle checked, sent, InfoLog/Tracker/Calendar entry created and pre-filled
correctly) worked fine, same as the Stalwart case above. `to_integrate_ids` "link into an existing
entry" path still only PHPUnit-verified, not separately exercised live.

### FIXED (2026-09-03): Share-as-link attachments (jmapEligible() blocker #2)

Previously, choosing a "Send files as" mode other than `attach` (Vfs\Sharing's `link`/`share_ro`/
`share_rw`) made `jmapEligible()` return `false` unconditionally, forcing the WHOLE send to fall
back to the classic postback just to get share-link generation - even though every other aspect of
the compose (recipients, body, other attachments) was otherwise fully JMAP-eligible.

**Fix**: new `mail_compose::ajax_getAttachmentLinksBody()` - a JMAP-native equivalent of
`createMessage()`'s own `$attachment_links = $this->_getAttachmentLinks(...)` call plus the
html/plain splice-into-body logic right after it (same placeholder-matching kept for parity, though
a JMAP-mode TinyMCE-composed body realistically only ever hits the final plain-append case, never
classic's own `<fieldset class="attachments...`/`<!-- HTMLSIGBEGIN -->` markers). Attachments arrive
in `MailCompose.uploadAttachmentsViaJmap()`'s own already-normalized shape (`{blobId,...}` or
`{vfsPath,...}`) - a new shared helper, `resolveJmapAttachmentsToFiles()` (extracted from
`ajax_integrateSent()`'s own identical inline resolution loop, itself refactored to call it too, no
behaviour change there), turns each into a real local `file` path (`AttachmentJmap::
fetchBlobBytes()` + a temp file, or `Vfs::PREFIX.$vfsPath` directly) - the one thing
`_getAttachmentLinks()` actually needs; it has no idea what a JMAP blob is.

Client side (`mail/js/compose.ts`): `jmapEligible()` no longer treats a non-'attach' filemode as a
blocker at all. `currentEmailFields()` gained a `forSend` param (true only from `trySendViaJmap()`,
matching classic `createMessage()`'s own `$_autosaving` guard on this exact logic - a draft save
still uploads/keeps real attachments, only an actual transmitted send gets them replaced with
links, generated fresh each send) - when `forSend` and the filemode isn't `attach`, it calls the new
endpoint, replaces `body` with its result, and drops `attachments` entirely (shared as links
INSTEAD of attached, matching classic's exact semantics, not "both"). `mergeAttachmentEntries()`'s
carry-forward population no longer force-disables `filemodeRow` either - that disabling predated
this fix and assumed a carry-forward `{jmapBlobId,...}` attachment could never be shared as a link;
it now can, via the exact same blob-to-temp-file resolution as any other JMAP-mode attachment shape.

**Live-verified 2026-09-03** (throwaway PHPUnit test against the real Stalwart test account,
acc_id=1): a real uploaded JMAP blob correctly produced a genuine `Vfs\Sharing` link
(`http://.../share.php/<token>`) spliced into the HTML body alongside the original content;
`filemode='attach'` confirmed as a true no-op (body unchanged).

### Regression report + hardening (2026-09-03, same day): "all attachments now sent as links"

A 3rd-party developer (achelper, a PDF-workflow app integrating via the `mail_compose_prepare` hook
- see its own write-up further below) reported that, after this fix, EVERY attachment in their
hook-driven compose (a plain classic `{file,name,type,size}`-shaped array injected server-side, no
`filemode` set by their own code at all) now gets sent as a share link instead of a real attachment.

Investigated at length without finding an actual bug in the conversion logic itself - it was
verified to match classic `createMessage()`'s own long-standing "`filemode != attach` -> link only,
never both" semantics EXACTLY (that gating condition, `elseif ($_formData['filemode'] ==
Vfs\Sharing::ATTACH)`, predates this whole JMAP migration project, confirmed via `git log -L` back
to at least 2024) - and a fresh compose's `filemode` widget was confirmed live to default to
`'attach'` (the first `Vfs\Sharing::$modes` entry), not empty/falsy. Could not conclusively
reproduce achelper's exact scenario (no live access to their app), so could not pin down exactly
HOW `filemode` ends up resolving away from `'attach'` for their case without the user ever touching
the "Send files as" dropdown - candidates considered and ruled out: `warnAttachmentSizeLimit()`'s
new auto-switch (only ever called from client-driven `mergeAttachmentEntries()`, never for
server-rendered/hook-injected attachments), a `composeCache` "sticky" filemode (not part of that
cache), and `checkSharingFilemode()`'s own early-return on `no_griddata` (achelper's own
`forward`-mode branch correctly sets `no_griddata = false` when it injects attachments, so the
filemode row IS enabled for that case, ruling out a hidden-control explanation too).

Given the mechanism couldn't be pinned down with confidence but the regression is real and active,
applied a hardening fix that's safe regardless of the exact root cause: `MailCompose.
explicitShareModeChosen` (new boolean, default `false`) - only ever set `true` inside
`checkSharingFilemode()`'s genuine `onchange` branch (a real user click, confirmed by the existing
"Filemode has been switched to %1" alert already gating that same branch) or by
`warnAttachmentSizeLimit()`'s own auto-switch (EGroupware's own deliberate choice, treated the
same as a real user pick). `currentEmailFields()`'s share-as-link conversion now ALSO requires this
flag, in addition to `filemode !== 'attach'` - a non-'attach' value the user never consciously chose
(from ANY source: a hook, a stale default, a rendering quirk) is now treated as `'attach'`
regardless of what it actually is. This can only make the feature MORE conservative than before
(strictly narrows an already-narrow condition) - the only behavior it removes is a legitimate
account-level "always default to link mode" preference silently taking effect without the user
re-clicking it each compose, which no persisted-default mechanism was found to actually exist for.

**Not yet live-verified against achelper's own reproduction** - ralf to confirm with the 3rd-party
developer once this ships. If attachments STILL become links after this, that would newly prove
`explicitShareModeChosen` itself is somehow getting set `true` incorrectly (a more specific, now
much narrower bug to chase) rather than the original open-ended "why is filemode not attach"
question.

### FIXED (2026-09-03): "Zielordner Drafts existiert nicht" opening an attached/standalone .eml

Original report: clicking the InfoLog-attached `.eml` opens `mail.mail_ui.
importMessageFromVFS2DraftAndDisplay`, which imports the file into the CURRENTLY ACTIVE mail
profile's Drafts folder so it can be displayed like a real message - a generic "view a standalone
.eml" mechanism (also used for double-clicking an `.eml` in filemanager), NOT specific to this
feature. Root cause: `ImportHandler::importMessageToFolder()` called `Api\Mail::folderExists($_folder,
true)` (-> `$this->icServer->mailboxExist($folder)`) and `Api\Mail::appendMessage()` (->
`$this->icServer->append()`) unconditionally - both raw-IMAP-socket calls inherited unguarded from
the base Horde class, the exact same "JMAP/IMAP fallthrough" bug class already tracked/partially
patched elsewhere in this codebase (10 other sites fixed, systemic fix deferred) - for a Stalwart
account these hang/misconnect against the JMAP(S) endpoint exactly like every other site in that
class. Also explains the SECOND, previously-separate report below: on the local IMAP shim these
calls technically work (real IMAP underneath) but pay a full classic round trip (parse the file,
open a connection, check the folder, append, redirect, re-fetch) - both reports turned out to be the
same underlying gap.

**Fix** (`mail/src/Ui/ImportHandler.php`): `importMessageToFolder()` now branches on `$icServer
instanceof Mail\Imap\Jmap` (true for BOTH Stalwart and the shim - `Imap\Stalwart extends Imap\Jmap`)
and materializes the message via genuine `Email/import` (RFC 8621 §4.8) instead: `jmapClient()->
uploadBlob($mailObject->getRaw(false), 'message/rfc822')` (a plain string, NOT the default
stream - `uploadBlob()` requires a string body) then `jmapClient()->emailImport($blobId, $_folder)`
- both already existed and are uniformly available for either backend via the same `jmapClient()`
(the shim's own local JMAP dispatcher implements the identical HTTP-JMAP surface, see
`Api\Mail\Jmap\Imap`). `getMailboxId($_folder)` is called explicitly first (needed for the returned
rowID anyway) so a genuinely-missing folder still gets the same
"Destination Folder %2 does not exist" message as the classic path, rather than a raw JMAP
exception. Changed the method's return contract from a bare uid to a ready-to-use rowID string
(`mail::accountID::profileID::mailboxId::emailId` for JMAP, unchanged `Api\Mail::createRowID()`
shape for classic) - callers no longer need to know which shape applies; both callers
(`ImportHandler::importMessageFromVFS2DraftAndDisplay()` and `mail_ui::importMessage()`) updated
accordingly.

**Live-verified 2026-09-03** (throwaway PHPUnit test against the real Stalwart test account,
acc_id=1): a real `.eml` imported via `importMessageToFolder()` returned a correct
`mail::12795::1::d::eyaaaabg`-shaped rowID; re-fetching that exact emailID via `emailGet()`
confirmed the subject matches what was imported. Also confirmed the resulting rowID renders
correctly via `mail.mail_ui.displayMessage&mode=display` (the real redirect target) - no
"Zielordner ... existiert nicht" error, no hang.

**Residual, separate, LOW-priority gap noticed while testing** (not fixed, likely never hit in
real usage): reaching `mail_ui::displayMessage()` via a bare top-level navigation (not a real
popup) surfaced that `mail.mail_ui.loadEmailBody`'s server-rendered body iframe (`get_load_email_data()`)
still falls through to genuinely classic raw-IMAP resolution for an ORDINARY (non-S/MIME/non-TNEF)
message on a JMAP account - `tryJmapNativeSpecialCase()` only short-circuits the special-case
types, returning `null` for a plain message, and the code past it needs a real IMAP UID/mailbox. In
real usage this is very likely never reached at all: `app.ts`'s `loadMessageBody()` already fetches
every message body (special-case or not) via the genuine JMAP-native `MailJmap.fetchBody()`
client-side, and only falls back to this server-rendered iframe if THAT throws - which is why this
has apparently never surfaced as "Stalwart mail bodies are slow/broken" despite the underlying
fallthrough existing. Only reachable via a bare/JS-less navigation (like this test) or a genuine
`fetchBody()` failure. Not chased further - out of scope for this fix, and a proper fix would mean
extending `resolveSpecialCaseBody()`'s own "client-first fast path" pattern to ordinary bodies too.

## FIXED (2026-09-03): the ACTUAL root cause of achelper's "all attachments sent as links" report - `filemodeRow`'s `id` namespaced the classic postback

Found the real, primary root cause via a 3rd-party patch (achelper's own developer, using their
own Claude session) against a fork of this repo - the `explicitShareModeChosen` hardening above was
a legitimate, independent safety net, but NOT the actual mechanism behind their report.

**Root cause**: `53bc6ba94e` (2026-08-31, THIS project's own "Step 4: JMAP-native reply..." commit)
gave the `<et2-hbox>`/`<et2-vbox>` wrapping the "Send files as" (`filemode`) select an
`id="filemodeRow"`, purely so `compose.ts` could find and disable it by id at the time. Verified
directly against `Et2Widget.checkCreateNamespace()`
(`api/js/etemplate/Et2Widget/Et2Widget.ts:1581`): `if (typeof entry === 'object' && entry !== null
|| this.id)` - a non-empty `id` ALONE (regardless of whether the content array actually has a key by
that name) makes a widget open its own array-manager "perspective" for its children. So
`filemodeRow`'s child `<et2-select id="filemode">` got scoped under `content['filemodeRow']
['filemode']` instead of top-level `content['filemode']` - invisible to JMAP-mode's own
`getWidgetById('filemode')?.get_value()` (that reads the live widget instance directly, unaffected
by array-manager namespacing - which is exactly why this went unnoticed for a week: it ONLY breaks
a CLASSIC form postback, never a JMAP-native send). On a classic postback, `$_formData['filemode']`
came back genuinely missing - and `createMessage()`'s `elseif ($_formData['filemode'] ==
Vfs\Sharing::ATTACH)` branch (the ONLY one that actually embeds a plain `{file,...}`-shaped
attachment) requires an exact string match, so "missing" silently meant "share as link", matching
`_getAttachmentLinks()`'s own `if ($filemode == Vfs\Sharing::ATTACH) return '';` guard failing the
same way - links generated, nothing actually attached. achelper's own hook-driven compose (business
data from InfoLog/PDF templates, its own custom `mode`/`template` GET params, not this project's
`from`/`id`) never engages ANY of the JMAP-mode bootstrap paths at all (see the hook-survival
write-up below) - it always goes through a genuinely classic postback, hitting this bug on every
single send with any attachment.

The same patch also found a second, independent, more far-reaching bug in the SAME
`mail_compose_prepare` hook-merge block (`mail_compose.inc.php`, right after the `Api\Hooks::
process()` call): `$preserv = array_merge($readonlys, $hook['preserv']);` and `$sel_options =
array_merge($readonlys, $hook['sel_options']);` both merged onto `$readonlys` instead of `$preserv`/
`$sel_options` themselves - a copy-paste bug (present since long before this migration project,
unrelated to `53bc6ba94e`) that silently discarded EVERY field `compose()` had already built into
`$preserv` (attachments, composeID, is_html/is_plain, mimeType, serverID, mode, in-reply-to,
references, ...) and every `$sel_options` entry the hook itself didn't happen to echo back - for
ANY compose where ANY app has a `mail_compose_prepare` hook registered, hook-driven or not.

**Fix** (ported the applicable parts of the 3rd-party patch, verified against this codebase first):
- `mail/templates/{default,mobile}/compose.xet`: `id="filemodeRow"` -> `class="filemodeRow"` (no
  code depended on the id specifically - JMAP-mode compose already reads `filemode` directly, and
  the earlier "Share-as-link attachments" fix removed the only `getWidgetById('filemodeRow')` call).
- `mail_compose.inc.php`'s hook-merge block: `$preserv`/`$sel_options` now correctly merge onto
  themselves (`array_merge($preserv, $hook['preserv'] ?? [])` etc, `?? []` added defensively too).
- `compose()`'s own content-prep: an empty `$content['filemode']` now explicitly defaults to
  `Vfs\Sharing::ATTACH` before rendering (same defensive pattern as the adjacent `if
  (empty($content['priority'])) $content['priority']=3;`).
- `createMessage()`: a fail-safe at the very top, `if (empty($_formData['filemode'])) {
  $_formData['filemode'] = Vfs\Sharing::ATTACH; }` - the actual point of consequence, so ANY future
  way `filemode` could end up missing (a different template bug, a hook, a stale form) can never
  again silently mean "share as link" instead of "attach".

**Not ported**: the patch's `api/js/jsapi/egw.js` change (a guard against `data-etemplate`
bootstrapping the same form twice, attributed to "a stale, non-cache-busted app bundle pulling in a
2nd copy of the chunk from an older build") - plausible for their own deployment/build pipeline, but
speculative and unverified against this one; and its `mail/js/app.ts` import fix, which references
`acemailstor` (achelper's own 3rd-party app, not present in this repo) and is irrelevant here.

**Live-verified 2026-09-03**: after the `.xet` change, a fresh compose's `filemode` widget's own
parent now has no `id` (only `class="filemodeRow"`), confirming the array-manager perspective is no
longer created for it. Not separately re-verified via an actual classic-postback send (would need
forcing `jmapCompose` off or another `jmapEligible()`-blocking scenario to exercise
`createMessage()`'s classic path directly) - the `createMessage()`/`compose()` fail-safes are
straightforward enough (`empty()` checks, no branching logic) that this is considered low-risk.

## Backlog: `mail_compose_prepare` hook survival for a fully client-driven compose (2026-09-03, DESIGN SKETCH ONLY, ralf)

A 3rd-party developer (achelper, see the "achelper hook" cross-reference in the regression write-up
above) asked whether their `mail_compose_prepare` hook - used to pre-fill a new compose window's
`to`/`cc`/`subject`/`mail_htmltext`/`attachments`/`mailaccount` from business data (an InfoLog
entry, a PDF template, ...) - still works, and how it would need to change once compose stops
calling the server at all (a longer-term item on this project's own feature list, not yet built).

### Current status: unaffected, verified by static analysis (no code change needed today)

`mail_compose_prepare` fires unconditionally near the end of `mail_compose::compose()`
(`mail_compose.inc.php:1581`, `Api\Hooks::process(['location' => 'mail_compose_prepare', ...])`),
which still runs in full for EVERY compose today, `jmapCompose` or not - only reply/forward/draft
population is skipped server-side for JMAP mode (`$jmapReplySkip`), and that skip is itself gated on
`$_GET['from']` being one of THIS project's own dispatch values (`reply`/`forward`/`composeasnew`/
`composefromdraft`/etc, `mail_compose.inc.php:417-418`). A 3rd-party hook driven by its OWN distinct
`$_GET` params (achelper uses `mode`/`template`/`info_id`, never `from`/`id`) never trips any of
those branches, so `$_GET['mode']` is never unset and the hook always runs exactly as before.
Client-side, `bootstrapCompose()`'s dispatcher only recognizes `from`/`id`; with neither set it
falls to `bootstrapSignature()`, which touches only the body (inserting the current identity's
signature on top of whatever's already there - expected for ANY new compose, hook-driven or not)
and never overwrites `to`/`cc`/`subject`/`attachments`/`mailaccount`. So whatever the hook wrote
into `$content` server-side survives to render untouched, same as always.

### The real, forward-looking problem

`mail_compose_prepare` only exists as a hook point because `compose()` still fully renders
server-side today. A genuinely-new compose that skips that render entirely (the roadmap item) has
nothing left to fire `Api\Hooks::process()` during - the hook would simply never run.

### Proposed design: extract the prepare step into its own thin JSON endpoint, not a new client API

Rejected alternative: a new client-side hook API (e.g. `app.mail.registerComposePrepareHook(cb)`)
that 3rd parties register a JS callback with. Pushes a real rewrite onto every existing integration
(achelper included) for zero functional gain, and fragments "prepare a new compose" across however
many apps happen to implement it in JS instead of PHP.

Better: extract just the "`$_GET` -> resolve business data -> `Hooks::process('mail_compose_prepare',
...)` -> mutated `{content, readonlys, sel_options}`" step out of `compose()` into a small, standalone
method (eg. `mail_compose::ajax_prepareCompose()`), callable as its OWN lightweight JSON round trip
- no Etemplate rendering, no classic content-preparation cruft, just today's exact hook contract
returned as JSON. The client's own bootstrap calls it ONCE, early (in parallel with or just before
its own JMAP-native population), and merges the returned `content` into the widgets it's about to
set - **zero code changes needed for existing hook implementations** like achelper's, since the hook
itself still receives and returns the exact same shape it always has.

### Optimization (ralf, 2026-09-03): skip the round trip entirely when nothing implements the hook

Most installs have NO app registered for `mail_compose_prepare` at all - paying a whole extra HTTP
round trip on every single compose-open for a hook that will do nothing is wasteful. `Api\Hooks::
count('mail_compose_prepare')` (`api/src/Hooks.php:177`, a cheap in-memory lookup against the
already-loaded hook registry, no DB query) tells us this cheaply. Plan: compute it ONCE, server-side,
as part of whatever minimal bootstrap payload a fully-client-driven compose window still needs at
load (eg. a `hasComposePrepareHook: true/false` flag alongside translations/preferences/etc, exactly
the kind of thing that page already has to convey somehow) - `bootstrapCompose()` only calls the new
endpoint when that flag is true, making the round trip's cost strictly opt-in to installs that
actually use this integration point (achelper's, or any other).

### Open questions (not yet decided)

- Exact shape/timing of the "minimal bootstrap payload" a fully-client-driven compose window would
  still need server-side at all (translations, preferences, sel_options for identity/mailaccount,
  the `hasComposePrepareHook` flag above, ...) - this depends entirely on the shape of the "compose
  no longer calls the server" feature itself, which hasn't been designed yet.
- Merge ordering: does the hook's returned `content` apply BEFORE or interleaved with
  `bootstrapCompose()`'s own dispatch (reply/forward/draft/composeasnew)? Today's server-side
  ordering (hook runs LAST, right before render, so it can see/override everything) suggests "hook
  result wins", but this needs an explicit decision once real client-driven reply/forward population
  and hook-driven population can genuinely collide (not possible today, since they're mutually
  exclusive by `$_GET` param name as described above).
- Whether `ajax_prepareCompose()` needs its own auth/CSRF story distinct from the rest of
  `mail_compose`'s existing ajax_* methods (probably not - same session, same app).

## Backlog: "Undo Send" - abortable send with pre-uploaded draft (2026-09-02, NOT STARTED, ralf)

Replace the current "please wait" spinner (`trySendViaJmap()`'s `egw.loading_prompt()` call) with a
Gmail-style "Abort send" affordance:

- An **Abort** button, visually filling left-to-right like a progress bar over N seconds (N
  presumably a preference, matching the "undo send" pattern other mail clients use) - clicking it
  during that window cancels the send and returns the user to editing.
- Crucially, the EXPENSIVE part (uploading the message + attachments to the server) should start
  IMMEDIATELY, not after the countdown - only the actual submission/delivery step waits for the
  timer. If the countdown elapses without abort, that final step should therefore be fast (the
  heavy lifting already happened) - "quickly submit" per ralf's own wording.
- On abort: discard what was uploaded. For a JMAP-native account, no active cleanup needed - "just
  leave it in the blob-store to be cleaned up by the mailserver later" (ralf) - matching this
  project's existing tolerance for orphaned drafts elsewhere (e.g. `saveDraft()`'s own
  best-effort-only cleanup of a stale previous copy). Local-shim equivalent (what "server-side for
  the shim" ends up creating - presumably a real Drafts-folder append, given the shim has no
  separate blob-store concept the way Stalwart does) needs its own equivalent "leave it, don't
  actively clean up" story - or, since it's real IMAP under the hood, deleting it on abort might
  actually be cheap/safe enough to just do rather than leaving an orphaned draft behind; worth
  deciding explicitly when this is built rather than assuming parity with the JMAP-blob case.

Natural implementation shape, reusing structure already in `mail/js/jmap.ts`'s `sendNewEmail()`:
that method already does exactly two phases - (1) `createDraftEmail()`/`importWholeMessageDraft()`
(the expensive upload/build step) then (2) `EmailSubmission.set()` (the actual send). This feature
is essentially: keep phase 1 exactly as-is (start it immediately on clicking Send), but gate phase 2
behind the abort-countdown UI instead of calling it immediately - if aborted, phase 2 (and the
`onSuccessUpdateEmail` Drafts->Sent move) simply never runs, leaving the phase-1-created draft
in place (or, for the shim, deciding whether to actively delete it - see above). The UI side needs a
new countdown/progress-fill button component (not yet designed) replacing the current
`loading_prompt()` spinner call in `trySendViaJmap()`.

Not designed yet: the exact abort-window duration (fixed? a preference, matching other "implicit
preference" toggles like jmapCompose/previewPane?), what happens if the user closes the compose
window entirely during the countdown (does the send still complete, or does closing imply abort?),
and whether "continue editing" after abort needs to restore any state beyond simply not having sent
(the draft/attachments were already uploaded once - re-editing and sending again would upload a
SECOND time, matching `saveDraft()`'s own now-established "create new, clean up old copy" pattern
would handle that cleanly if reused here too).

## FIXED: carried-forward/freshly-uploaded attachments sometimes silently missing (2026-09-02 investigated, 2026-09-03 root-caused + fixed)

Real, reproduced-live symptom (ralf, via `composeasnew` off a real Sent message with a real
attachment - NOT specific to the new draft-continuation feature, which shares the same code and
shows the identical symptom): a freshly-bootstrapped compose window's `content.attachments` ends up
`null`/empty even though the source message genuinely has the attachment.

**What's been ruled out, all confirmed live against the real Stalwart account by directly poking the
actually-open, already-bootstrapped compose window via its own JS console:**
- NOT data loss on the source message - re-queried directly via JMAP, the original always still has
  its attachment, every time.
- NOT a logic bug in `MailJmap.fetchForReply()` - calling it manually, in the SAME already-open
  compose window, on the SAME source id, correctly returns the attachment every single time it was
  tried.
- NOT a logic bug in `MailCompose.carryForwardAttachments()` - calling it manually with that
  correctly-fetched data, in the same window, correctly populates `content.attachments` every time.
- NOT specific to `bootstrapDraft()` (this session's new feature) - `bootstrapComposeAsNew()`
  (pre-existing, unrelated to this session's changes) shows the identical symptom.

**What's confirmed suspicious:** doing the EXACT SAME two calls (`fetchForReply()` then
`carryForwardAttachments()`) by hand, moments after a fresh page load, works every time - but the
REAL automatic bootstrap sequence, run immediately on page load, sometimes doesn't. This points at a
TIMING/RACE condition specific to the moment right after compose first loads (JMAP token/session
setup, or some other early-page-load state not fully settled yet) - not a logic error in the
carry-forward code itself, which has now been verified correct twice, live, two different ways.

**A second, unconfirmed report (a test user, via ralf, not yet reproduced) may be the SAME root
cause manifesting differently**: attachments are only actually sent with the mail if a draft was
saved FIRST - compose a message with an attachment and hit Send directly (no prior save) and the
attachment doesn't go out. If the underlying issue really is "attachment data isn't reliably
available immediately after some trigger, but is a short while later", this fits exactly: an
intervening draft-save acts as an incidental delay that lets the race resolve, while a direct
compose-then-send hits the race window every time. Not yet confirmed - investigate together with the
above, likely the same fix will address both.

### Root cause (2026-09-03) - not actually a nondeterministic race at all

Two hypotheses from the day before were directly tested and conclusively ruled out against the
real Stalwart account, via raw curl JMAP calls (no browser needed):
- **Server-side concurrency corruption** - fired 8 concurrent `Email/get` calls at a freshly-created
  attachment message, repeated 5x. Every response was either fully correct
  (`hasAttachment`/`attachments` always right) or an explicit `urn:ietf:params:jmap:error:limit`
  rejection (`maxConcurrentRequests` exceeded, from the session document) - Stalwart never once
  returned a partially-wrong result. Rules out any theory involving Stalwart silently mangling data
  under load.
- **Missing-capability timing** - a request declaring `using: ["urn:ietf:params:jmap:core"]` only
  (i.e. `urn:ietf:params:jmap:mail` not yet loaded into `this.capabilities` at request time) doesn't
  silently drop mail-specific properties like `attachments` while keeping `subject` - Stalwart
  rejects the WHOLE method call outright (`unknownMethod`, capability required). This also can't
  explain "subject fine, attachments empty".

With both server-side theories eliminated, the actual bug turned out to be much simpler, and
client-side only: **`MailCompose.bootstrapCompose()` is deliberately fire-and-forget from
`setEtemplate()`** (`void this.bootstrapCompose()`, so opening the compose popup doesn't block on
it) - **and nothing ever awaited it before submitAction()/saveAsDraft() read `content.attachments`
to build the outgoing message.** `carryForwardAttachments()` (correct, per the day-before
verification) only finishes populating `content.attachments` once its own `fetchForReply()` network
round trip resolves - a real compose popup opening cold (fresh JMAP client, fresh WebSocket/session
setup, THEN the actual Email/get) can take a perceptible moment. Click Send (or "Save as Draft")
before that finishes and whatever's in `content.attachments` at that exact moment - still empty -
is what gets sent/saved, silently. Explains the second report exactly: an intervening draft-save
gives bootstrap enough extra wall-clock time to finish before the actual send happens afterward,
while a direct compose-then-send-immediately hits the empty window far more often. Not a "race" in
the sense of nondeterministic corruption - just a missing wait.

**Fix** (`mail/js/compose.ts`): new `bootstrapPromise` field, set to `bootstrapCompose()`'s own
promise in `setEtemplate()` (was fire-and-forget `void`). `submitAction()` chains it into the
existing `wait` gate (already used uniformly by all three send branches - mailvelope, JMAP-native,
classic postback - so one change covers all of them); `saveAsDraft()` wraps its whole body in
`bootstrapPromise.then(...)`. Autosave's own 2-minute interval was never in realistic danger; this
matters for a user clicking Send or "Save as Draft" quickly after opening compose.

Live-verified 2026-09-03 for the "click Send immediately" scenario this fix targets - but ralf's own
live repro the same day (`composeasnew` via the real "Verfassen" popup) turned out to hit a SECOND,
entirely separate, deterministic bug that this fix does not touch at all - see below.

### Second bug (2026-09-03) - TinyMCE editor-not-ready throw aborts the whole bootstrap function

ralf's repro was not timing-sensitive at all: right-clicking a Drafts-folder message with a real
attachment and choosing "Verfassen" (`from=composeasnew`) reliably opened a compose popup with NO
attachments, waiting several seconds made no difference, and calling
`MailJmap.fetchForReply(sourceId)` manually from the console on that SAME already-loaded popup
correctly returned the attachment - proving the fetch itself was fine and ruling out timing/race
entirely for this bug. The nearly-identical `bootstrapDraft()` (same fetch, same body, same
attachment-carry-forward) worked correctly for the identical source message when reached via a plain
URL navigation instead of a popup.

The console (previously not checked on the failing popup) showed the actual cause: an uncaught
`TypeError: Cannot read properties of undefined (reading 'serialize')`, thrown synchronously from
inside TinyMCE's own `getContent()`/`setContent()`, at the exact point `bootstrapComposeAsNew()`
calls `this.currentBodyWidget()?.set_value(context.body)`. `Et2HtmlArea`'s TinyMCE editor
initializes asynchronously - the editor object is assigned (`_handleTinyMceSetup()`) well before its
`init` event fires, and `_syncValueToEditor()`'s readiness check (`this._tinyMceEditor?.setContent`)
only confirms the object exists, not that its iframe body is actually attached yet. Calling
`set_value()` in that gap throws, and since `bootstrapComposeAsNew()`/`bootstrapDraft()`/
`bootstrapReply()` have no try/catch around it, the throw aborts the async function right there -
skipping every step after it, most visibly `carryForwardAttachments()`. A real popup
(`window.open()`) loses this race far more easily than a direct URL navigation because it gets less
main-thread/rendering priority for its TinyMCE iframe setup - explaining exactly why
`composefromdraft` "worked" in isolation while the real `composeasnew` popup didn't, even though
the two functions are otherwise near-identical.

`Et2HtmlArea` already exposes the right fix point for this: its own `tinymce` property is a promise
that resolves on the editor's `init` event (a documented "compatibility bridge", `resolvePromise`
pattern already built for exactly this kind of caller). **Fix** (`mail/js/compose.ts`): new
`setBodyValue(html)` helper - awaits `widget.tinymce` when present (a no-op for the plaintext
textarea widget, which has no such property) before calling `set_value()`. Replaces the three direct
`currentBodyWidget()?.set_value(...)` call sites: `bootstrapComposeAsNew()`, `bootstrapDraft()`, and
the shared `applySignatureForCurrentIdentity()` (which `bootstrapReply()`/`bootstrapSignature()`/
`updateSignatureForIdentity()` all funnel through) - covering every bootstrap path, not just the one
ralf hit.

Live-verified 2026-09-03 via the actual repro: right-click the same Drafts message -> "Verfassen" ->
real popup (not a direct URL nav) -> `content.attachments` correctly populated
(`mail_2mjmosokb2sb1DiOAPt.png`), no console exceptions.

**Process note**: while investigating this live in ralf's own browser, several of my own read-only
diagnostic actions (opening the draft repeatedly via right-click "Öffnen"/double-click, trying to
get a tracked popup tab) unintentionally triggered real compose-popup opens against the SAME
draft, each of which auto-saved (with the SAME attachment-loss bug) before being replaced -
producing a cascade of ~8 duplicate "Test mit DND attachment" drafts in ralf's real Drafts folder,
and the original (with its real attachment) no longer exists in any generation. Low-stakes (a test
message), but worth remembering: this environment's acc_id=1 is a SHARED mailbox across test/dev
accounts, and live poking of a real user's real mail account - even read-only JS console queries -
can have real side effects when compose windows and autosave are involved. Cleanup of the duplicate
drafts was offered but not yet done (pending ralf's go-ahead).

### Follow-up: bootstrapDraft()/bootstrapComposeAsNew() de-duplication (2026-09-03, DONE, live-verified)

The two functions were nearly line-for-line identical (ralf noticed while reviewing the fix above)
- their only real difference was `bootstrapDraft()` additionally setting `this.jmapDraftEmailId` so
the first save/send in the new session deletes the original draft. Refactored so
`bootstrapComposeAsNew()` now returns `Promise<JmapReplyContext | null>` (`null` on a failed fetch,
error already shown) instead of `void`, and `bootstrapDraft()` is now a 4-line wrapper that calls it
and only sets `jmapDraftEmailId` when it got a real context back - preserves the exact original
"only mark this session responsible for deleting the old draft if the load actually succeeded"
semantics, removes ~30 lines of duplication. Live-verified both paths on the same source message
(`mail::5::1::d::fkiaaabks`): "Verfassen" (composeasnew) and "Öffnen" (composefromdraft, confirmed
`jmapDraftEmailId` correctly set to `"fkiaaabks"`) - both populate the attachment, no console errors.
Committed + pushed to master, `597d61a341` (together with the bootstrapPromise/setBodyValue fixes
above).

## FIXED (2026-09-03): reply to a plain-text mail always opened as HTML, ignoring the "same format as original" preference; quoted body invisible once that got fixed

Two-part report: (1) a 3rd-party report that replying to plain-text mail loses the quoted body, and
(2) ralf's own repro - despite the `replyOptions` preference set to `'none'` ("use source as
displayed, if applicable" - classic's own "same format as original" mode), replying to a genuinely
plain-text message opened as HTML, WITH the correctly quoted body included.

### Root cause #1: `MailJmap.fetchForReply()`/`assembleBodyHtml()` misread RFC 8621's `htmlBody` fallback echo

Both methods determined html-vs-plain via `email.htmlBody.length > 0` - live-verified against the
exact reported message (`mail::5::1::a::eoyaaabdx`, "Test Plaintext", a single-part `text/plain`
message, no `subParts` at all): Stalwart's own `Email/get` response still returns a NON-EMPTY
`htmlBody` array for it, containing the SAME `text/plain`-typed part echoed into both `textBody` AND
`htmlBody` - RFC 8621 §4.1.4's own fallback behavior ("if there is no HTML part, return the plain
text part instead") so `htmlBody` is never simply empty for a message with any body at all. Checking
array length alone is therefore true for nearly every message, not just genuinely HTML ones -
`fetchForReply()`'s own `context.mimeType` came back `'html'` for this literally single-part plain
message, hence the reply UI opening in HTML mode regardless of the `replyOptions` preference
(neither `bootstrapReply()` nor anything else in `compose.ts` even reads that preference - it just
trusts `context.mimeType`, matching classic's own "'none' -> use the original's actual format"
semantics, correctly, once given a correct `context.mimeType`).

**Fix** (`mail/js/jmap.ts`): both `fetchForReply()` and `assembleBodyHtml()` (the message-VIEW body
renderer - same exact flaw, found by grepping for the same `htmlBody.length` pattern) now require
the picked `htmlBody` entry's own `type` to actually be `'text/html'` (`htmlParts.find(p => p.type
=== 'text/html')`) rather than just checking the array is non-empty.

### Root cause #2 (only became visible once #1 was fixed): body container visibility never synced to a bootstrap-set mimeType

With `context.mimeType` now correctly `'plain'`, the reply DID get quoted into the correct
(`mail_plaintext`) widget (confirmed directly: reading the widget's own `get_value()` returned the
fully-quoted, correctly `>`-prefixed plain body) - but the compose window kept showing the HTML rich
-text editor (with its own, empty `mail_htmltext` widget) instead, making the reply look completely
blank. `switchMimeTypeClientSide()` (the function that actually toggles which of `mailComposeHtmlContainer`/
`mailComposeTextContainer` is visible, via `set_disabled()` - `is_plain`/`is_html` are one-shot
server-render expression bindings, never reactive) is ONLY ever called from `submitOnChange()`'s
real user-driven `mimeType` onchange handler - `bootstrapReply()`/`bootstrapComposeAsNew()` (and
`bootstrapDraft()`, which delegates to the latter) set the `mimeType` WIDGET's value directly via
`set_value()`, which does not fire a synthetic change event, so the container swap never ran for
those. This exact gap has presumably always existed, but was never observable before Root cause #1
was fixed: `context.mimeType` was ALWAYS wrongly `'html'`, matching the server-rendered default
(HTML) container that's already visible - the desync could only ever manifest once a reply was
capable of genuinely resolving to plain text at all.

**Fix**: extracted `switchMimeTypeClientSide()`'s own container-toggle logic into a new
`syncMimeTypeContainers(toHtml)` helper, now also called right after both `bootstrapReply()`'s and
`bootstrapComposeAsNew()`'s own `mimeType` `set_value()` calls (not just from the user-toggle path).

**Live-verified 2026-09-03**, real end-to-end repro against the exact reported message (right-click
"Test Plaintext" in Inbox -> Antworten): reply now opens correctly in plain-text mode (HTML toggle
unchecked, plain toolbar) with the fully-quoted body visible (`>`-prefixed original + signature);
manually toggling the HTML checkbox afterward still correctly converts and shows the content (no
regression to the existing manual toggle path). No console errors either time.

## FIXED (2026-09-03): shim plain-text reply with umlauts previewed as mojibake - two independent bugs, both real

To test the fix above, ralf created a genuine plain-text reply (German umlauts/ß) via a shim
(Dovecot/IMAP) account and noticed the received message previewed with garbled `ü`/`ß` -
Thunderbird displayed the SAME raw message correctly, which turned out to be the key clue.

### Bug 1 (send side): `Api\Mailer::getRaw(false)` could emit a body whose actual bytes didn't match its own `Content-Transfer-Encoding` header

`getRaw($stream=true)`'s stream branch passes an explicit `'encode'` mask (7bit|8bit|binary, or
7bit-only for a signed S/MIME part) to `getBasePart()->toString()`; the non-stream branch
(`getRaw(false)` - what every JMAP-native send path actually calls) omitted it entirely, defaulting
to `toString()`'s own `ENCODE_7BIT`-only default. `Horde_Mime_Part`'s own transfer-encoding decision
is masked-dependent AND cached per-mask (`_temp['sendTransferEncoding'][$encode]`) - reserializing
the body with a DIFFERENT mask than whatever `send()` (a real SMTP transport, possibly negotiating
8BITMIME) or the earlier "never actually sent, just building a draft" auto-`send()` already decided
picks a different, inconsistent actual encoding for the SAME bytes than the `Content-Transfer-
Encoding` header (built from `$this->_headers`, NOT re-decided) still declares - e.g. a header
saying `quoted-printable` while the body was actually re-serialized raw/`8bit`, or vice versa.
Reproduced directly via PHPUnit (`Mailer::setBody()` + `getRaw(false)`, both with and without an
explicit prior `send()`/forced `8BIT` encode) - confirmed live, BEFORE this fix, in both directions.

**Fix**: compute `$encode` once, use it for the internal "never sent yet" auto-`send()` (now calling
this class's own `_send()`, which accepts `$opts`, instead of `parent::send()`, which doesn't) AND
for both `getRaw()` branches' body (re)serialization - the header and the body bytes it wraps are
now always decided from the exact same mask, whichever code path produced them.

### Bug 2 (read side, the actual mojibake): the shim's own body-decoders default an undeclared charset to `us-ascii`, not `utf-8`

`Api\Mail\Jmap\Imap::fetchBodyValue()` (JMAP `Email/get`'s bodyValues, live message reading) and
`structureToHtml()` (S/MIME/TNEF resolved-message rendering) both had `$charset =
$part->getContentTypeParameter('charset') ?: 'us-ascii';` - RFC 2045's literal default, but NOT
what any of this matters against in practice: this project's own outgoing plain-text is always
utf-8 (bug 1 above just made that reliably DECLARED too), and Thunderbird - which displayed ralf's
undeclared-charset test message correctly - already assumes utf-8 for undeclared charset rather
than the RFC-literal default, matching most modern MUAs. `Api\Mail.php` (`api/src/Mail.php:8154-
8155`) already carries this exact fix for the CLASSIC IMAP path (`Horde_Mime_Part::$defaultCharset
= 'utf-8'; // Default charset to utf-8, not us-ascii which Horde chooses`) - the newer JMAP-shim
implementation is a separate, parallel codepath that never inherited it.

**Fix**: both call sites now default to `'utf-8'` instead of `'us-ascii'`. Zero downside for a
genuinely us-ascii message (a strict subset of utf-8, decodes identically either way) - only
improves handling of any message - not just ones affected by bug 1, any 3rd-party sender's mail
too - that lacks a declared charset.

**Test coverage**: `mail/tests/StructureToHtmlTest.php::testUndeclaredCharsetDefaultsToUtf8NotMojibake`
(pure in-memory `Horde_Mime_Part`, no IMAP connection needed, matching this file's existing pattern)
- verified it actually catches the regression by reverting the fix locally and confirming the exact
mojibake pattern (`GrÃ¼Ãen` - UTF-8 bytes misread as Latin-1/us-ascii) reappears. `Api\Mailer::getRaw()`'s own fix was
verified via a throwaway PHPUnit script (both "never sent" and "sent with forced 8BIT" scenarios,
each showing internally-consistent output after the fix) - not added to the repo test suite (no
existing `Mailer`-focused test file to extend cleanly; the closest ones,
`api/tests/Mail/SmimeMailerTest.php` and `.../Jmap/ImapBuildMailerTest.php`, both re-ran clean with
this fix in place, 21+125 tests, no regressions).

## FIXED (2026-09-03): shim send via `EmailSubmission/set` (acc_id=42) took ~5.8s - deferred the post-send IMAP bookkeeping past the HTTP response

Ralf: "sending via the shim takes an aweful long time e.g. reproducable via acc_id=42 account, can
you instrument that a bit so we get an idea where it's actually coming from". Added temporary
per-step timing (to a scratch log file, removed again once done) around one real send:

```
acc=42:53 0_imapServerConnect=0.020s
acc=42:53 outcome=success 1_refetchDraft=0.585s 2_buildMailer=0.063s 3_smimeRawFetch=0.000s
  4_smtpSend=0.622s 5_getRaw=0.001s 6_appendToSent=3.414s 7_deleteOldDraft=1.146s TOTAL=5.831s
```

The actual SMTP send (`4_smtpSend`) is fast (~0.6s) - ~80% of the wall-clock time is TWO IMAP round
trips that run AFTER the mail has already gone out: appending the Sent-folder copy (~3.4s - a full
raw-bytes IMAP APPEND, not a cheap server-side copy) and deleting+expunging the now-obsolete Draft
(~1.1s). Two ways to fix, ralf's own framing:

- (a) use Dovecot's Submission service (JMAP-like: upload the blob once, then submission "send +
  copy to Sent" server-side) - real fix, but only pays off where the IMAP server actually
  advertises both blob-upload and Submission (ralf: EGroupware's own Dovecot-based Mail containers
  already offer/use both but without blob upload yet, so would profit; the hosting environment
  currently uses Postfix directly, planned to migrate to Stalwart/JMAP anyway, making this most
  relevant for the container deployments specifically). Unclear yet whether Horde's
  `Horde_Imap_Client` interface has any support for the blob/Submission side at all - not
  investigated.
- (b) `fastcgi_finish_request()`: answer the client as soon as the mail is actually sent, run the
  Sent-copy-append + old-draft-cleanup afterward, in the same PHP-FPM worker process. Chosen as the
  low-hanging fruit for now; (a) postponed.

**Fix**: `Imap::$deferredWork` (a static array of closures) + `Imap::queueDeferredWork()`/
`runDeferredWork()`. `emailSubmissionSet()` now queues the Sent-append + old-Draft delete/expunge
closure instead of running it inline, and builds the success response immediately after queuing
(the SMTP send itself, `$mailer->send()`, already ran synchronously before this point - by
construction the mail has genuinely already gone out, so the client isn't told "sent" before it's
true; only non-critical bookkeeping is deferred). `mail/jmap.php`'s POST dispatch branch calls
`fastcgi_finish_request()` (guarded by `function_exists()`, since not every SAPI has it) right after
`echo`ing the JSON response, then `Imap::runDeferredWork()`, then `exit` - the GET/cacheable branch
(read-only `Email/get` preview/view fetches) is untouched, since `EmailSubmission/set` is a mutating
call only ever reachable via POST. A deferred closure that throws is caught and `error_log()`'d
(same "best-effort bookkeeping" tolerance the old-draft cleanup already had inline before this
change) rather than surfaced anywhere - by the time it runs, the client already has its response and
there's no channel left to report a failure back to it.

**Live-verified** (same acc_id=42 account, a fresh compose + send via the browser): response TOTAL
dropped from ~5.8s to ~1.07s (`0_imapServerConnect=0.019s`,
`1_refetchDraft=0.488s 2_buildMailer=0.011s 3_smimeRawFetch=0.000s 4_smtpSend=0.562s 5_getRaw=0.003s
TOTAL=1.065s`), with a separate `DEFERRED appendToSent=3.732s deleteOldDraft=1.353s TOTAL=5.085s`
line confirming the background work still completed - the Sent folder got the message and the old
Draft was gone, both checked directly in the UI afterward.

**Not (yet) extended to plain `Email/set` move/destroy** (ralf raised this: "That deferring might
also make sense for other JMAP shim operations like moving mail to other folder/trash"): the same
`store()`+`expunge()` primitive `emailSet()`'s `destroy` branch uses is almost certainly paying a
similar cost (the old-Draft cleanup above uses that exact primitive), so a plain delete-forever or
move-to-Trash likely IS meaningfully slow too. But the risk shape is different from
`EmailSubmission/set`: there, the SMTP send is the actual requested action and has already
irreversibly happened by the time bookkeeping is deferred - a failure in the deferred part loses
nothing the client cares about (mail's already sent). For `emailSet()`, the move/copy/destroy IMAP
call IS the entire requested action - nothing has happened yet at response time if it's deferred.
Doing the same thing there would mean answering "done" before the IMAP op has actually run, which
would (1) reintroduce exactly the failure-mode `Mail optimistic delete no confirm` (fixed
`b9bffc171d`) was fixed to prevent - the client's own row-removal already deliberately waits for a
confirmed server response before updating the UI - and (2) have no channel left to surface a
deferred failure (permission error, full mailbox, dropped connection) back to the client at all,
unlike a thrown exception today which correctly leaves the row in place. Applying this pattern there
needs an explicit decision to accept that weaker guarantee (fire-and-forget, server-log-only
failures) for moves/deletes too - not just a mechanical copy of the `EmailSubmission/set` fix.

**Root-cause investigation (2026-09-03, ralf: "investigate root cause first")**: confirmed a plain
`Email/set` move-to-Trash on acc_id=42 is even slower than the send case - 7.4s wall-clock for a
SINGLE message. Temporarily enabled `Horde_Imap_Client`'s own protocol debug log
(`Mail\Imap::$default_params['debug']`, normally commented out) for one live move, then reverted -
Dovecot's own tagged response settles it directly:

```
S: 5 OK [HIGHESTMODSEQ 57820] Move completed (7.370 + 0.000 + 7.369 secs).
>> Command 5 took 7.4754 seconds.
```

Our own measured round-trip (7.4754s) matches Dovecot's self-reported server-side processing time
(7.370s) almost exactly - essentially ALL of the cost is inside Dovecot itself executing the MOVE,
not network latency, not our IMAP client code, not PHP overhead. This matches (and now has hard
protocol-level evidence for) the earlier 2026-08-27 finding already documented in
`Jmap\Imap::imapServer()`'s own docblock ("Dovecot's own MOVE response reported 'Move completed
(24.933 ... secs)'"). Genuinely external, infrastructure-side slowness on that particular Dovecot
backend (storage format/indexing/plugin overhead on MOVE/EXPUNGE) - nothing left to fix in this
codebase itself; further root-causing needs Dovecot server-side access (doveadm/logs on that
mailbox), which is outside this repo. Debug logging reverted, no code change from this
investigation.

**Extended the deferral to `Email/set` move/destroy too (2026-09-03, same day)**: ralf pointed out
`mail/js/app.ts`'s `callMove()`/`deleteMessages()` already remove the row optimistically BEFORE the
server replies and only reconcile (`nm.refresh()`) if the JMAP call rejects (`b9bffc171d`, same
commit for both) - so the "client told 'done' before it's true" risk from the "not (yet) extended"
analysis above already exists today, just surfaced via promise rejection rather than response
timing. The one real gap: a failure DURING the deferred IMAP call itself (rare - invalid
folder/message id etc. are still validated synchronously before anything is queued) has no channel
back to the client, which already got its "OK" and won't call `nm.refresh()`. Decided (ralf: "Defer
now, revert-signal later") to accept that narrower risk now, given how consistently slow this
backend's MOVE/EXPUNGE is, and revisit pushing a revert notification over the existing WebSocket
channel (`project_mail_jmap_push_notifications`) as a follow-up rather than building it upfront.

**Fix**: `emailSet()`'s `$moves` and `$destroyed` handling (the IMAP COPY+move and STORE+EXPUNGE
calls) now go through `queueDeferredWork()` too, same as `emailSubmissionSet()`'s. Flag add/remove
(plain STORE, no expunge) and `$copies` (COPY without removing the source - nothing disappears from
the current view to reconcile, no evidence it's slow) stay synchronous.

**Live-verified** (acc_id=42, real browser actions, `window.fetch` wrapped to time the `jmap.php`
POST): move-to-Trash dropped from 7.4s to 560ms, permanent delete (already-in-Trash, `destroy`
branch) to 536ms - both confirmed via the UI itself, not just the fast response: the moved message
showed up in Papierkorb afterward, and the permanently-deleted one was actually gone (not just
missing from the row that already disappeared optimistically).
