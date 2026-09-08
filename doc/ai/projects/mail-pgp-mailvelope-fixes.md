# Mail: PGP/Mailvelope send + display fixes (2026-09-08)

## Status: send-side and display-side both fully verified (both JMAP backends, preview pane + popup)

Started as "let me test PGP with Mailvelope before building the openpgp.js signature-verification
project" (see `mail-pgp-signature-verification.md`). Turned into fixing a chain of real bugs that
had made both PGP send and PGP display effectively non-functional since the mail_compose/client-
side-rendering migrations - not caused by that migration, but never revisited once it landed since
nobody had exercised Mailvelope seriously in the meantime.

## Bug 1: compose PGP toggle hung forever ("Bitte warten...")

`togglePgpEncrypt()` (`mail/js/app.ts`) needed to force plain-text before encrypting (Mailvelope
only supports plain-text) and called the classic `getInstanceManager().submit()` full-postback
reload to do it - dead since compose stopped registering a postback menuaction (mail_compose::
compose() deleted, doc/ai/projects/mail-compose-jmap-migration.md Step 10). Fixed: reuse
`compose.ts`'s existing client-side `switchMimeTypeClientSide()` conversion instead (made public
for this caller). Also fixed two related `.checkbox('pgp', ...)` calls on a since-removed widget
API (the "No" branch of the switch-off confirm dialog, and the reply-to-encrypted-message
auto-check) - both now use `_actionManager.getActionById('pgp').set_checked(...)`, matching the
pattern already used elsewhere in the same method.

## Bug 2: PGP send did nothing at all

`submitAction()`'s mailvelope branch (`mail/js/compose.ts`) encrypted the body via Mailvelope,
populated the `mail_plaintext` widget with the armored result, and just `return false` - never
actually sent anything. Autosave picked up the pieces ~2 minutes later via `saveAsDraftClassic()`'s
own mailvelope branch, which posts to `ajax_saveAsDraft` - a real endpoint, but one that
unconditionally calls `Api\Mail::reopen()`/`getFolderStatus()` against the Drafts folder using
classic raw-IMAP-era code that can't talk to a JMAP-only account (Stalwart on port 443) - the
`EGroupware\Api\Mail::getFolderStatus failed... Error when communicating with the mail server`
error that surfaced this whole investigation.

Root architectural point (ralf): S/MIME needs a server round-trip because ITS encryption happens
server-side against a stored cert; Mailvelope already encrypts client-side, so there's no reason
PGP send needs the classic postback (or ANY server round-trip) at all - it should go straight
through JMAP, same as everything else post-migration.

### Fix: PGP send now goes through JMAP natively, both backends

- `MailJmap.pgpEncryptBody()` (`mail/js/jmap.ts`) wraps Mailvelope's already-encrypted armored
  output into an RFC 3156 `multipart/encrypted` bodyStructure - two tiny blobs (the fixed
  "Version: 1" identifier part + the ciphertext itself) uploaded via the same `uploadAttachment()`
  primitive real attachments use. No server round-trip for the crypto itself.
- `createDraftEmail()`'s `bodyOverride` swap (previously S/MIME-only, a flat `{type, blobId}`
  single-part shape) now also accepts a `{type, subParts}` nested shape and assigns it directly to
  `bodyStructure` - the exact mechanism S/MIME's `smimeEncryptBody()` already established.
- `sendNewEmail()`/`saveDraft()` both gained a `pgpArmored` param threaded through to
  `pgpEncryptBody()`.
- `jmapEligible()` no longer excludes mailvelope (it used to, alongside S/MIME/attachments) -
  `trySendViaJmap()`/`trySaveDraftViaJmap()` both now encrypt via Mailvelope first when active, then
  pass the armored result through.
- **Live-verified against real Stalwart (acc_id=1)**: a real draft-save and a real send both
  succeeded via direct JMAP calls; raw MIME inspection confirmed correct `multipart/encrypted`
  structure with both required subparts, cleaned up afterward.
- **The one real open technical question, resolved**: JMAP's structured `EmailBodyPart.type` has no
  dedicated field for extra Content-Type parameters (RFC 3156 §4 requires `protocol=
  "application/pgp-encrypted"` on the top-level part) - live-tested that Stalwart takes the WHOLE
  `type; params` string verbatim into the real Content-Type header rather than rejecting/stripping
  it, so `type: 'multipart/encrypted; protocol="application/pgp-encrypted"'` is enough; no need for
  the heavier "build the whole raw message + Email/import" fallback S/MIME's TYPE_SIGN case needed.
- **Shim backend** (`Api\Mail\Jmap\Imap::buildMailerFromEmailProperties()`) extended with an
  analogous special case recognizing this same 2-part shape, reusing the EXISTING
  `Api\Mailer::setOpenPgpBody()` (already used by the classic postback path, `mail/src/
  ComposeMessageBuilder.php`'s own `'openpgp'` case) rather than re-implementing the MIME structure
  by hand - guarantees byte-identical output to what the classic path already produces correctly.
  **Live-verified against the shim (acc_id=85)** too: same structure, same protocol param, correct
  on both backends.

## Bug 3: PGP display never triggered Mailvelope at all

`mailvelopeDisplay()` (the code that hands an already-armored preview body to Mailvelope's
`createDisplayContainer()`) was only ever called from `et2_ready()`'s ONE-TIME iframe `load`
listeners (attached when the template first initializes) - dead for the modern JMAP-native fast
path (`MailApp.loadMessageBody()`, `mail/js/app.ts`), which re-sets `.srcdoc` on the SAME iframe
element per message selection without ever re-triggering that original listener. A PGP message's
raw armored text rendered fine (by design - `MailJmap.fetchBody()`'s PGP branch, `jmap.ts`, renders
it into the same `td.td_display > pre` shape specifically so Mailvelope can find it), but nothing
ever called Mailvelope to actually decrypt/replace it.

### Fix chain (found one problem at a time, live)

1. **Trigger**: added `this.mailvelopeAvailable(this.mailvelopeDisplay)` to `loadMessageBody()`'s
   own per-load listener (fast path) and `loadClassicBody()`'s (classic fallback) - removed the now-
   redundant calls from `et2_ready()`'s listeners (which were doubling up with the new ones for the
   'mail.display' popup case specifically, showing the SAME message decrypted twice side by side).
2. **CSP**: `frame-src 'none'` (deliberate 2020 hardening, `mail/src/Ui/MessageDisplayHandler.php`,
   predates Mailvelope integration by years, never meant to interact with it) blocks Mailvelope's
   `chrome-extension://` iframe injection outright - `Cannot read properties of null (reading
   'appendChild')`. Confirmed via `stylite/js/app.ts`'s working InfoLog Mailvelope integration
   (description editor + tooltip) that the framework's normal default (`frame-src 'self'`, no
   override) is already sufficient - no `chrome-extension:` allowance needed anywhere. Fixed by
   skipping the `frame-src` override specifically when the body is PGP (`multipart/encrypted`,
   detected via `$structure->contentTypeMap()` server-side / `findPgpPart()` client-side already
   existing) - both `MessageDisplayHandler.php` (classic) and `MailJmap.wrapDocument()`'s new
   `forMailvelope` param (JMAP fast path) - connect-src/manifest-src stay fully hardened either way.
3. **Container resolution**: `mailvelopeDisplay()`'s old `iframe.parentElement.dom_id` computation
   assumed a light-DOM parent with a legacy `dom_id` property - `Cannot read properties of null
   (reading 'dom_id')`. The `<iframe>` (`Et2Iframe.ts`'s own accessor) lives inside that widget's
   shadow root; `.parentElement` is null (parent is the ShadowRoot itself, not an Element). Walking
   up further (`iframeWidget.parentElement`, an `<et2-ai>` wrapper) still isn't reachable via
   Mailvelope's own top-level `document.querySelector()` - shadow nesting goes at least 2 levels
   deep in the preview-pane case specifically (the 'mail.display' popup's `.mailDisplayContainer`
   class, `display.xet`'s own `<et2-box>`, sits genuinely in light DOM and was never affected).
   Fixed by sidestepping the whole reachability question for the preview-pane case: create a fresh
   plain `<div>` directly on `document.body`, positioned over the iframe's on-screen rect, instead
   of trying to find/walk to an existing reachable ancestor.
4. **State reset across message switches**: `mailvelopeDisplay()` used to `return` immediately for a
   non-PGP body, before ever reaching the code that would undo a PREVIOUS pgp message's own
   `iframe.style.display = 'none'` - selecting a PGP message then a normal one left the normal
   message's (correctly-loaded) content invisible. Fixed by moving the reset (un-hide iframe, clear
   stale anchor divs / previous Mailvelope iframes) to run unconditionally at the top of the method,
   before the early-return.
5. **Screen-size CSS**: Mailvelope's injected iframe had NO sizing rule for normal screen viewing -
   the only existing rule (`.mailDisplayContainer.mailvelopeTopContainer > iframe`) was scoped
   inside `@media print` (print-layout work, unrelated). Added a normal-screen rule in `app.less` +
   directly in the compiled `app.css` (no local LESS compiler in this checkout - `app.css` isn't
   part of the kdots theme bundle `npm run css` produces, it's loaded standalone, so hand-editing it
   is both necessary and sufficient here): `.mailDisplayContainer` gets `position: relative` (a
   containing block, since Mailvelope's iframe/wrapper turned out to use absolute positioning - a
   plain `100%` without this resolved against a farther positioned ancestor instead, landing in an
   unrelated quadrant of the page); the iframe itself gets `position:absolute; inset 0; width/height
   100%` via a DESCENDANT selector (not direct-child - it may be wrapped) matching either Mailvelope
   URL scheme (`chrome-extension:` or `about:blank?mvelo`). `height` needed `!important`: confirmed
   live via DevTools (ralf's own DOM screenshot) that Mailvelope's own script sets an inline
   `style="height: <n>px"` on this same iframe once the passphrase is entered and it resizes to the
   decrypted content - an inline style always wins over an external stylesheet rule regardless of
   selector specificity, so without `!important` the container went full-height only until that
   point, then shrank to ~half.

### Live-verified

- Preview pane: real Mailvelope decrypt-and-display confirmed working by ralf (passphrase prompt,
  decrypted content shown), full width/height, correct on message switches.
- Popup: confirmed working end-to-end by ralf after fix 5's `!important` - full width AND full
  height now survive past the passphrase-entry resize, original iframe cleanly hidden.

## Bug 4: PGP's own structural parts shown as a fake attachment (Stalwart only)

Viewing a real PGP-encrypted message on a real-JMAP (Stalwart) account showed a stray
"Unbekannt_Part1..." row in the attachments block - RFC 3156 §4's `application/pgp-encrypted`
"Version: 1" control part and its ciphertext sibling, both structural parts of the message's own
`multipart/encrypted` body, not real user attachments. Confirmed by ralf as backend-specific and
independent of how the message was sent (even an externally-SMTP-delivered message to Stalwart
showed it) - the IMAP shim already got this right, Stalwart didn't. Partially covered by
Mailvelope's own overlay, compounding the confusion.

Classic mail already handles this correctly (`Api\Mail::getMessageAttachments()`,
`api/src/Mail.php:5915`, skips both parts of a `multipart/encrypted` structure) - the newer
JMAP-native attachment listing (`AttachmentJmap::jmapAttachmentsToLegacy()`, shared by both
backends) never gained the equivalent skip. Fixed by filtering on the `application/pgp-encrypted`
marker type (RFC 3156-reserved, never legitimately used by a real attachment) plus its immediate
next sibling in the flattened attachments list - conservative, backend-uniform (fixes Stalwart,
harmless no-op for the shim which apparently never surfaced the pair there to begin with), and
leaves a genuinely separate real attachment outside the `multipart/encrypted` wrapper (e.g.
`multipart/mixed[multipart/encrypted[...], real_attachment.pdf]`) untouched. Confirmed by ralf: with
the fake attachment gone, the earlier overlay concern (Mailvelope covering a would-be attachments
row) resolved as a side effect too - no attachments row, nothing to cover.

### Known remaining rough edge (not resolved this session, low priority)

- **Re-selecting the SAME already-decrypted PGP message a second time removed Mailvelope's display**
  (confirmed by ralf: same message, second click, not a different one). Not root-caused - candidates:
  Mailvelope's own dedup/caching behavior on a second `createDisplayContainer()` call for identical
  content, or a race between the reset-cleanup (fix 4) and the first call's still-settling async
  state. Uncommon interaction (most users won't re-click an already-open message); not chased
  further this session.

### Aside: testing this through Claude's own browser automation was unreliable

Claude's own `claude-in-chrome` browser automation (CDP-attached) turned out to interfere with
Mailvelope's decrypted display - content visibly disappeared the moment automated inspection
(`javascript_exec`) touched the page, and later even a plain reload/navigate through the automated
tab was enough. Strong signal this is Mailvelope's OWN defensive behavior (many PGP tools
intentionally collapse decrypted plaintext when they detect script-level inspection/CDP-attached
automation, as an anti-exfiltration measure), not an EGroupware bug. Every fix past that point (CSS
sizing especially) had to be verified in ralf's own regular, non-automated browser session instead -
worth remembering for any FUTURE Mailvelope-display work in this app: don't trust Claude's own tab
for it, ask the user to check.

## Explicitly out of scope / untouched

- Everything in `mail-pgp-signature-verification.md` (native openpgp.js signature verification,
  Mailvelope-free) - this session's testing was specifically Mailvelope-based encryption, the
  precursor ralf wanted working before evaluating that separate project.
- The classic postback send/save path's own "Missing menuaction for submit" issue for OTHER
  (non-PGP) ineligible-for-JMAP sends - noted as a latent, pre-existing, unrelated gap found while
  investigating Bug 1, not fixed (out of scope for tonight, needs its own scoping).
