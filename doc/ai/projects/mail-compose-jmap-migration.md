# Mail: move compose to client-side JMAP-first, S/MIME + TNEF as server-side services

## Status: Steps 0, 1, 3, and (out of order) the draft-save half of Step 5 done + live-verified
against real Stalwart (2026-08-27); Step 4's reply, reply-all, reply-with-attachments (incl.
attachment carry-forward), single-message inline forward, forward-as-attachment (single or
multiple messages), and inline-image resolution for the quoted body (incl. its own send/draft-save
side - blob re-upload, caching, and now a proper text/plain alternative) are ALL done +
live-verified (2026-08-31, incl. against the IMAP-shim backend - see its own write-up below). Step
4 is now functionally complete except reply-all/forward into an already-open compose popup (the
`setCompose()` case, deliberately deferred - see its own write-up below). Next up: Step 2
(IMAP-shim EmailSubmission emulation) - the shim can bootstrap/quote/carry-forward attachments via
JMAP already, but sending still falls back to classic there entirely, since `EmailSubmission/set`
was never built for it.

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

**Known open issue, not yet root-caused**: clicking a carried-forward `message/rfc822` attachment's
filename in the compose attachments list (`displayUploadedFile()`'s own `jmapBlobId` branch,
`displayJmapBlobAttachment()`) unexpectedly ended up navigating to
`mail.mail_ui.importMessageFromVFS2DraftAndDisplay` (a real, pre-existing menuaction registered via
`mail_hooks.inc.php`'s MIME-type registry for `message/rfc822` specifically -
`formData[file]`/`formData[data]`/`formData[type]=message/rfc822`) - NOT anything
`displayJmapBlobAttachment()` itself calls. That import path expects a real VFS-stored file, which
a bare JMAP blob reference isn't - timed out server-side, then showed a confusing "Zielordner
Drafts existiert nicht" confirm dialog. Root mechanism not yet found (checked `Et2Description`'s
own `_handleClick()` - `open_link()` only fires if `mimeData`/`href` are set, and neither is bound
anywhere in this row template) - something ELSE is routing `message/rfc822`-typed rows through this
registry, specific to that one MIME type (this whole carry-forward mechanism already works fine
for image/PDF/other types, confirmed live). Needs more investigation before a fix.

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

**Known gap, deliberately deferred (ralf, 2026-08-31): carry-forward attachments break if SEND
falls back to classic on a shim account.** `EmailSubmission/set` was never built for the shim
(phasing's own Step 2, still not started) - `resolveComposeContext()` throws
`JmapUnsupportedBackendError` for `token.isLocal`, so `trySendViaJmap()` correctly falls through to
the unchanged classic postback send for a shim account. That's fine for plain reply/reply-all/
inline-forward (no attachments, just widget text) - but for reply-with-attachments/forward, the
carried attachments are shaped `{jmapBlobId, tmp_name:"jmap:"+blobId}`, which matches NEITHER of
classic `createMessage()`'s two recognized attachment shapes (`{uid,folder,partID}` or
`{file:<real path>}`) - it falls into the "non-vfs file" branch and calls
`basename($attachment['file'])` with `file` undefined, producing a bogus path instead of the real
attachment. Ralf: not worth fixing now, since the plan is to remove classic-send-fallback entirely
once JMAP-native sending is added to the shim (making this whole gap moot) - just tracked here for
now, revisit once Step 2 (shim EmailSubmission) happens.

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
10. **Eliminate the ETemplate postback cycle from compose** (ralf, 2026-08-27, future phase - see
    its own write-up above near Step 4's mapping): once nothing about compose depends on server-side
    processing anymore, stop having `mail_compose.compose()` pre-compute reply/forward content into
    the initial ETemplate content array - the popup opens with a generic, content-free template
    shell instead, and client JS populates everything from JMAP fetches. `.xet` templates keep their
    role as pure layout/widget-tree definitions; they stop being a request/response transport.
    Positioned after Step 4 proves client-side quoting/signature/attachments, likely alongside or
    just before Step 9.
