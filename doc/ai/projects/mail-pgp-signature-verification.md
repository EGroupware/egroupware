# Mail: verify PGP/MIME signatures natively (no Mailvelope dependency)

## Status: Phase 3 UI wiring done + live-verified (2026-09-08); ralf's own live testing 2026-09-09
surfaced 5 real bugs (4 UI-wiring, 1 in the core verify engine itself), all fixed + live-verified
same day (see below) - Phase 4 tests not started (beyond the one regression test added for the
core-engine bug); 3 follow-up items queued 2026-09-09 (Autocrypt key-import dialog, sending an
Autocrypt header, Mailvelope sign-on-send) - see "Planned follow-up" below, none started

### 2026-09-09 core-engine bugfix: base64-encoded signature parts

`MailJmap.extractSignatureArmor()` (`mail/js/jmap.ts`) assumed everything after a
`application/pgp-signature` part's own header block *is* the ASCII-armored signature text - true
for Thunderbird/Enigmail (leaves it plain 7bit), but a real message from `jens.riedel@baw.de`
("Rückfragen zu den Installationsdateien von Collabora Office Desktop" - structurally the Phase 1
spike's "older.eml" fixture) has `Content-Transfer-Encoding: base64` on that part specifically.
`openpgp.readSignature()` then threw "Misformed armored text" on the still-base64-encoded bytes,
silently caught by `verifyPgpSignature()`'s top-level `try/catch` - the message showed **no PGP
icon at all** (found live 2026-09-09, ralf: "testing with the 'Rückfragen zu den Install...', which
is PGP signed but with a different structure then the TB signature, is not shown as signed"), not
even the "unknown key"/"invalid" states the doc's own 3-state model has for exactly this kind of
case. Fixed by checking that header and `atob()`-decoding first when it says `base64`. New unit
test coverage in `mail/js/test/PgpSignatureArmorExtraction.test.ts` (the extraction function is
pure/static, no JMAP mocking needed).

**Important caveat found while live-verifying this fix**: the live test mailbox's 11 copies of this
message all still show `verified:false` (`pgp_sig_invalid`, correctly *shown* now, just not
*verified*) - independently confirmed this is **not** a remaining code bug: downloaded the original
`.eml` from ralf's own Downloads folder, ran the exact same extraction algorithm against it by hand
(Python port, `/tmp` scratch script), and `gpg --verify` against that byte-exact original returned
"Korrekte Signatur" successfully using the sender's own inline `application/pgp-keys` key - proving
the extraction/verification logic itself is correct for this structural shape. The live mailbox's
stored copies have a *different* SHA-256 than that original file, meaning whatever import/resend
process put 11 copies into this test account altered the bytes somewhere - fatal for a byte-exact
MIME signature, but a pre-existing test-data fidelity issue outside this project's scope, not
something to "fix" in `verifyPgpSignature()` itself.

### 2026-09-09 UI-wiring bugfix round (live-tested by ralf)

- **Icon swap**: compose toolbar's PGP encrypt toggle now uses bootstrap `file-lock2` (was the
  generic `lock` also used for other unrelated things); the `pgp_signature` status icon now uses
  `envelope-at-fill` (ralf's own choice, was also `lock` before - indistinguishable from the
  compose toggle and S/MIME had no equivalent overlap).
- **Both PGP and S/MIME icons showing on a plain S/MIME-only message** - `setPgpSignatureFlags()`
  was calling `pgp_signature.set_disabled(!data.signed)` to hide the icon for a non-PGP message,
  copying `setSmimeFlags()`'s own pattern - but `disabled` never hides an Et2Widget, only makes it
  look non-interactive (per `Et2Widget.ts`'s own docblock); S/MIME's icons only ever "worked" via
  their `hidden="!@smime=..."` one-shot server-render binding (a real content field, since S/MIME
  status IS known server-side), which PGP has no equivalent of (100% client-side/async detection).
  Fixed by using `.hidden` instead, with both `index.xet`/`display.xet` now defaulting
  `hidden="true"` so nothing flashes visible before the async verify resolves.
- **PGP icon visibly smaller/differently-weighted than S/MIME's** despite identical `width="24"` -
  S/MIME's icons are inlined custom SVGs that scale to fill their box; a bootstrap-icons class like
  `bi-envelope-at-fill` renders via a `::before` webfont glyph sized by `font-size` (not
  width/height) - it was rendering at the page's ambient 14px. Fixed with a scoped CSS rule
  (`.mailPreviewHeaders.smimeIcons et2-image[class*="bi-"] { font-size: 24px; }`) in `app.less`
  (+ hand-mirrored `app.css`).
- **Duplicate tooltip** (a plain native OS one stacked on the framework's own nicer-styled one) -
  turned out to be a pre-existing, framework-wide `Et2Image.ts` bug, not scoped to this feature:
  `render()` copied `statustext` into the native `title` attribute, duplicating
  `Et2Widget.ts`'s own `updated()`-driven `egw().tooltipBind()` binding that already exists for
  *every* widget's `statustext` (including images) - any et2-image with a statustext showed two
  tooltips. Fixed in `Et2Image.ts` (title now only comes from `label`, never `statustext`) and
  committed separately from the mail-specific changes since it's a shared-widget fix, not mail-only.
- **PGP signature "attachment" not hidden from the attachment list** - the RFC 1847 detached
  signature part of a `multipart/signed` message showed up as a normal-looking attachment (an
  `OpenPGP_signature.asc` file), same underlying issue as the earlier `multipart/encrypted` control
  part fix. Fixed in `AttachmentJmap::jmapAttachmentsToLegacy()` by filtering
  `application/pgp-signature` outright - simpler than the encrypted case's "marker + next sibling"
  skip, since JMAP's own flattened `attachments` list always surfaces the signature as one
  self-contained entry regardless of how deeply the signed content itself is nested. Verified
  against both real fixture shapes from the Phase 1 spike (a plain Thunderbird self-signed message,
  and one that also carries the sender's own `application/pgp-keys` inline - only the signature is
  hidden, the inline key stays visible/downloadable, needed for the still-unbuilt Autocrypt
  follow-up above). New PHPUnit coverage in `mail/tests/JmapAttachmentsToLegacyTest.php`.

`MailJmap.verifyPgpSignature(rowId)` (`mail/js/jmap.ts`) implements the full chain: detects a
PGP-signed `multipart/signed` (`findPgpSignaturePart()`), downloads the whole raw message (the
spike's own confirmed-safe path), slices out the exact signed bytes + detached signature
(`sliceMultipartSigned()`/`extractMultipartSignedBoundary()`/`extractSignatureArmor()`), resolves a
verification key (addressbook first via the existing `ajax_get_pgp_keys`, then a same-message inline
`application/pgp-keys` attachment), and calls `openpgp.js` (lazy-loaded lightweight build,
`loadOpenpgp()`) to verify. **Live-verified end-to-end** against the real Stalwart fixture from the
spike: uploaded/imported the same message, stored ralf's own real public key via
`ajax_set_pgp_keys`, called `verifyPgpSignature()` - result `{signed:true, verified:true,
keySource:'addressbook', email:'rb@egroupware.org'}`, matching the spike's own independent `gpg
--verify` "Korrekte Signatur" ground truth exactly.

`openpgp` added as an npm dependency (lightweight build, `openpgp/lightweight` export) -
lazy-loaded via a plain dynamic `import()`, which Rollup automatically code-splits into its own
~232K chunk (confirmed: `chunks/openpgp.min-*.js`), never touching the main bundle for the
overwhelming majority of messages that are never PGP-signed. `mail/js/openpgp.d.ts` is a small
ambient module declaration working around tsconfig.json's classic "node" `moduleResolution` not
understanding package.json `"exports"` subpath maps (Rollup's own resolver handles it fine
regardless - this is purely a TypeScript type-checking gap). `web-test-runner.config.mjs` needed
the same "resolve this bare specifier to a concrete file" mock-modules entry `dompurify`/`tinymce`
already use, for the same underlying reason under its own (different) esbuild-based resolver.

**Phase 3 UI wiring done and live-verified (2026-09-08)**, deviating slightly from the original
"Suggested phasing" Step 3/1 plan: rather than folding PGP into `fetchBody()`'s own `JmapBodyResult`
(the `smime`-field approach), `verifyPgpSignature(rowId)` is called directly and independently from
`MailApp.loadMessageBody()`'s fast-path `iframe` load listener and `loadClassicBody()`'s own load
listener (`mail/js/app.ts`, same two spots `mailvelopeAvailable(mailvelopeDisplay)` already hooks
into) - simpler than threading a new field through the body-fetch pipeline, and correctly async
(doesn't block the body from rendering while the signature check runs). A `rowId ===
this.currentlyFocussed` staleness guard on the resolved promise avoids a slow verify from a
previously-selected message clobbering a later selection's icon.

`MailApp.setPgpSignatureFlags(result)`/`pgpClearFlags(nodes)` (`mail/js/app.ts`) mirror
`setSmimeFlags()`/`smimeClearFlags()`'s shape exactly, but with PGP's own 3-state vocabulary instead
of force-fitting S/MIME's verify/cert/unknownemail one: `pgp_sig_verified` (green,
`result.verified===true`), `pgp_sig_invalid` (red, a key was found - addressbook or inline - but
cryptographic verification failed) and `pgp_sig_unknownkey` (purple, `keySource==='none'`, no key
available to even attempt verification). Own icon (`pgp_signature`, generic `lock` image, NOT
reusing the `smime_sign`/`smime_encrypt` custom SVGs - different trust mechanism, deliberately
visually distinct only by border/icon color today) added to both `index.xet` (preview pane) and
`display.xet` (popup/full view) right next to the existing `smime_signature`/`smime_encryption`
icons in the `smimeIcons` hbox; CSS in `app.less` (and hand-mirrored into the compiled `app.css`,
since this checkout has no local LESS compiler) reuses S/MIME's exact green/red/purple palette for
visual consistency. Reset wiring mirrors S/MIME's too: `preview()`'s pre-load reset now clears PGP
flags alongside S/MIME's, and `setPgpSignatureFlags()` itself clears-then-reapplies on every call so
re-selecting an already-open message doesn't need special-casing.

**Live-verified in the browser** against a real signed-by-self message in ralf's own inbox
("Testmail PGP singed", sent from/to `rb@egroupware.org`): preview pane showed the green
`pgp_sig_verified` border + green lock icon immediately on selection, with statustext "PGP/MIME
signed message, signature verified for rb@egroupware.org"; selecting a different (PGP-encrypted,
not signed) message correctly cleared both the border class and disabled the icon; re-selecting the
signed message re-applied the verified state correctly (round-trip). Lang phrases added to
`mail/lang/egw_en.lang`/`egw_de.lang` for the three statustext variants. `npx tsc --noEmit`,
`npm run build`, and `npx web-test-runner "mail/js/**/*.test.ts"` (197/197 passing) all clean - no
new errors introduced by this phase.

Popup/`mail.display` path not independently browser-verified this session (Claude's own browser
automation has trouble tracking popup windows it didn't open itself into its tab group - see
[[mail-pgp-mailvelope-fixes]]'s aside on this) but is code-symmetric with the already-proven S/MIME
popup handling (`egw(window).is_popup()` branch in `setSmimeFlags()`/now `setPgpSignatureFlags()`
picks `.mailDisplayContainer` instead of `mailPreviewContainer`) and uses the exact same
`pgp_signature` widget id/JS methods `display.xet` shares with `index.xet` - low risk, but worth a
quick manual popup check.

Precursor: [[mail-pgp-mailvelope-fixes]] - Mailvelope-based encrypt/decrypt was fixed and live-verified
first, as ralf's own baseline to test signature verification against.

Follow-up to fixing the "PGP signed messages are displayed red as unverified" regression
(`MailJmap.isSpecialCase()`/`SPECIAL_CASE_TYPES`, `mail/js/jmap.ts` - a PGP/MIME `multipart/signed`
message was being routed into the S/MIME resolver and naturally "failing to verify"). Ralf asked:
*"could we verify PGP signature directly via openssl and the key stored in our addressbook, without
Mailvelope I mean?"*

Short answer worked out below: **openssl itself is a dead end** - it only speaks X.509/PKCS7
(S/MIME), never OpenPGP (RFC 4880). Real verification needs either a PHP `gnupg` extension (not
installed anywhere in this stack - `php -m` inside the `egroupware` container has no `gnupg`) or a
JS OpenPGP implementation. Recommendation: **client-side, via `openpgp.js`**, reusing the
addressbook's already-existing PGP key infrastructure - no server-side crypto dependency needed at
all, which also fits this app's established "client-side JMAP-native" direction better than adding
a new PHP extension would.

## Why this is even possible without Mailvelope

Today's only PGP-aware code (`mailvelopeDisplay()`/`mailvelopeGetCheckRecipients()`,
`api/js/jsapi/egw_app.ts`, `mail/js/app.ts`) exists entirely to drive the **Mailvelope browser
extension** - EGroupware hands it ciphertext/keyring-lookups, Mailvelope's own embedded OpenPGP
engine does the actual crypto in its own isolated iframe. That's necessary for **decryption**
(needs the recipient's *private* key, which Mailvelope's keyring holds and EGroupware never sees).
**Signature verification only ever needs the sender's *public* key** - no private key, no
passphrase, no isolation requirement - so there's no architectural reason it has to go through
Mailvelope at all, or even require the extension to be installed.

## Existing infrastructure this can reuse as-is

- **Key lookup**: `addressbook_bo::ajax_get_pgp_keys($recipients)`
  (`addressbook/inc/class.addressbook_bo.inc.php:84`, menuaction
  `addressbook.addressbook_bo.ajax_get_pgp_keys`) already does exactly "given an email address,
  return its ASCII-armored PGP public key" - checks the addressbook's own stored keys
  (`Api\Contacts::FILES_PGP_PUBKEY`) first, falls back to a public keyserver lookup
  (`get_pgp_keyserver()`) if not found locally. Currently only ever called from
  `mailvelopeGetCheckRecipients()` (`api/js/jsapi/egw_app.ts:2225`) to import a *recipient's* key
  into Mailvelope's keyring before encrypting outgoing mail - the exact same call, unmodified,
  answers "what's this *sender's* public key" equally well for verifying an incoming signature.
- **A message can also carry its own signer's key inline**: the example `.eml` ralf provided has a
  `Content-Type: application/pgp-keys; name="OpenPGP_..."` part (a `-----BEGIN PGP PUBLIC KEY
  BLOCK-----`) alongside the `multipart/signed` structure - a common convention many PGP/MIME
  clients (Enigmail/Thunderbird, ProtonMail, etc.) follow, attaching the sender's own key so a
  first-time recipient can verify without any lookup at all. Worth treating as a third, "free"
  key source (RFC 8621 already surfaces it as a normal attachment part) - **with the obvious trust
  caveat**: a self-attached key only tells you *which* key claims to have signed the message, not
  that it belongs to who it says it does. Exactly the same "signed, but by an unknown/unverified
  key" distinction `setSmimeFlags()` already renders today (`smime_cert_notverified` vs
  `smime_cert_verified` classes, `mail/js/app.ts:8285-8302`) - reuse that same three-state model
  (verified / signed-but-unknown-key / signature-invalid) rather than inventing a new one.
- **Detecting a PGP/MIME signed part at all**: already just fixed -
  `MailJmap.isSpecialCase()`/`findPgpPart()`-adjacent code in `mail/js/jmap.ts` now correctly tells
  PGP-signed `multipart/signed` apart from S/MIME-signed. The two sub-parts RFC 1847 guarantees
  (`part.subParts[0]` = the signed content, `part.subParts[1]` = the detached
  `application/pgp-signature` armored block) are already right there in `bodyStructure` - no new
  JMAP properties needed to *find* them.
- **Downloading a part's raw bytes**: `MailJmap.downloadPartText()` (`mail/js/jmap.ts:3003`) already
  does a JMAP Blob download by `blobId` for exactly this kind of "I need this one MIME part's actual
  bytes, not the assembled/sanitized display HTML" need (used today for the PGP/MIME *encrypted*
  ciphertext part). The same primitive fetches both sub-parts a signature check needs.

## The one real open question: byte-exact canonicalization - RESOLVED (2026-09-08 spike)

RFC 3156 §5 verifies a PGP/MIME signature against the **canonical MIME representation** of the
first sub-part - its raw, still-transfer-encoded bytes, with CRLF line endings, exactly as they
were on the wire. This is the single most common source of real-world "valid signature reported as
invalid" bugs in PGP/MIME implementations generally - not something to assume away, and it turned
out to matter in exactly the way expected.

**Method**: two real, independently-obtained fixture `.eml` files (ralf's own Thunderbird self-sent
message, `multipart/signed → [multipart/mixed, application/pgp-signature]`; and an older message
from an external sender with an inline-attached public key, `multipart/signed → [multipart/mixed,
application/pgp-signature]` plus the mixed part itself carrying `[multipart/alternative,
application/pgp-keys]`). Ground truth for each: extracted the exact wire-byte range of `subParts[0]`
(including ITS OWN Content-Type header - RFC 1847's canonical form requires that) by locating the
outer boundary markers directly in the raw bytes (no MIME-reconstructing parser, to avoid the exact
risk being tested), then independently confirmed each with `gpg --verify` against the extracted
signature part (importing the sender's own inline-attached key for the external-sender fixture,
matching the "message carries its own signer's key" case this doc already flagged as worth
supporting) - both fixtures verified as genuinely, correctly signed before touching JMAP at all.

**Findings** (uploaded each fixture as a blob, `Email/import`ed it into both a real Stalwart account
and the local shim, fetched it back via JMAP, compared SHA-256 hashes against the local ground
truth - full detail in [[mail-pgp-mailvelope-fixes]], which this same session's Mailvelope work had
already built and verified the blob-upload/import primitives for):

1. **Whole-message download IS byte-exact on both backends** - `Email/get`'s own `blobId` on the
   message itself, downloaded via the normal Blob-download path, hashed identically to the original
   local file on Stalwart AND the shim (`2feb6f95176cf21...`, matching exactly). This is the one
   fully-safe, backend-uniform primitive.
2. **A sub-part's own `blobId` (RFC 8621's per-part download) is NOT safe to rely on for this**, for
   a different reason on each backend:
   - **Stalwart**: only assigns a `blobId` to actual leaf content parts. `subParts[0]` in both test
     fixtures is itself a `multipart/mixed` container (not a leaf) - its `blobId` came back `null`.
     No per-part download exists to even attempt for this (very common, not edge-case) shape.
   - **The shim**: DOES assign a `blobId` to every part, container or not (its own self-describing
     `mailbox:uid:partId` scheme) - but downloading a *container* part's blobId returns only that
     part's own multipart BODY, missing its own Content-Type header line (confirmed: the returned
     bytes started at the inner boundary marker, not at `Content-Type: multipart/mixed...`). Per
     RFC 1847 §2.1 the canonical form needs that header included - this data is subtly wrong for
     signature verification even though a naive length/existence check wouldn't catch it.

**Conclusion**: skip the per-part blobId path for this feature entirely, on both backends - always
use the "fetch the whole raw message via its own top-level `blobId`, then slice out `subParts[0]`'s
exact byte range by hand" approach this doc's own fallback plan already anticipated. Locating that
byte range needs the SAME boundary-marker approach the spike's own ground-truth extraction used
(don't reconstruct via a MIME parser - slice the confirmed-byte-exact raw bytes directly using the
already-known boundary string from `bodyStructure`/the Content-Type header), trimming the CRLF
immediately before the boundary delimiter per RFC 2046. `findPgpPart()`'s existing bodyStructure walk
(`mail/js/jmap.ts`) already locates which node is `subParts[0]` and would need to also record the
multipart/signed parent's own boundary string for this slicing.

## Library choice

`openpgp.js` (ProtonMail-maintained, MIT license, the de facto standard pure-JS OpenPGP
implementation - also what Mailvelope itself embeds internally) is the obvious choice for the actual
`verify()` call. Not currently a dependency anywhere in this repo (checked `package.json`,
`package-lock.json`, `node_modules` - zero hits). Two things worth deciding at implementation time,
not now:
- **Lazy-load it**, the same way `Et2HtmlArea`'s TinyMCE chunk is already a separate bundle
  (`api/js/etemplate/Et2HtmlArea/`) rather than bundled into `app.min.js` - `openpgp.js`'s full
  build is not small, and the overwhelming majority of messages are never PGP-signed at all, so
  paying that cost only when a `multipart/signed`+PGP message is actually opened is the right
  default, mirroring this codebase's existing "pay for it only when you need it" precedent.
- `openpgp.js` ships a smaller "lightweight" build (verify-only, no key generation/encryption code)
  which may be worth using here specifically, since this feature never needs to *generate* or
  *encrypt* anything - only worth confirming its feature set actually covers detached-signature
  verification (it does, per openpgp.js's own docs) before committing to it over the full build.

## Where verification runs / UI

Mirrors the existing S/MIME flow's shape but is simpler, since it's 100% client-side with no
private-key/passphrase step at all:

1. `MailJmap.fetchBody()` (`mail/js/jmap.ts:2451`) already special-cases `findPgpPart()` for the
   *encrypted* case (`multipart/encrypted`, line ~2537). A parallel branch for a PGP-*signed*
   `multipart/signed` (now correctly excluded from `isSpecialCase()`'s S/MIME routing) would: fetch
   both sub-parts' bytes, resolve a public key (addressbook lookup, then the message's own attached
   key part if present, matching the fallback order `get_pgp_keys()`/`get_pgp_keyserver()` already
   establish server-side), run `openpgp.verify()`, and return a `pgp: {signed, verified, keySource,
   email}`-shaped result alongside the normal body - same idea as today's `smime` field on
   `JmapBodyResult` (`jmap.ts:2529`), just computed locally instead of via a server round-trip.
2. `MailApp.setSmimeFlags()` (`mail/js/app.ts:8265`) has its own dedicated icon/CSS-class wiring
   (`smime_signature`/`smime_encryption` in `mail/templates/default/display.xet:27-29`,
   `smime_cert_verified`/`_notverified`/`_notvalid` classes). A new, parallel
   `setPgpSignatureFlags()`-style method plus a `pgp_signature` icon (own statustext/icon asset, NOT
   reusing the S/MIME icon - they're different trust mechanisms and should look visibly distinct)
   is the natural fit, rather than trying to force PGP results through the S/MIME-specific
   verify/cert/unknownemail vocabulary that method already has.
3. **No dependency on the Mailvelope extension being installed at all** - this is the whole point
   of the ask ("without Mailvelope"). `mailvelopeAvailable()`'s own detection gate
   (`mail/js/app.ts:363,469`) stays scoped to what it already covers (decrypting/composing
   encrypted mail); this new verify-only path runs unconditionally for anyone with the mail app,
   extension or not.

## Suggested phasing

1. **Spike**: confirm byte-exact canonicalization works (the open question above) against both
   Stalwart and the local shim, using ralf's example message as the fixture - this is the one
   finding that could reshape the whole design (e.g. force the "fetch the raw message and slice it
   by hand" fallback), so it should happen before any UI/wiring work.
2. Add `openpgp.js` (lazy-loaded), a `verifyPgpSignature()`-equivalent in `jmap.ts` wired into
   `fetchBody()`, using addressbook lookup + in-message attached key as the two key sources (skip
   the public-keyserver fallback initially - lower value for a first pass, `get_pgp_keyserver()`'s
   own PHP equivalent can stay addressbook-only-callable for now).
3. UI: new icon + `setPgpSignatureFlags()`, mirroring the S/MIME icon's three-state model.
4. Tests: TS unit tests using openpgp.js's own generated test key/message fixtures (deterministic,
   no live server needed) for the verify logic itself, plus a live-Stalwart/live-shim check using
   ralf's real example message for the canonicalization question and an end-to-end sanity check.

## Planned follow-up (added 2026-09-09, not started)

Three extensions ralf asked for on top of the done verify-only Phase 1-3 work above, testing of
which he'll do "tomorrow" (i.e. after this note was written):

1. **Autocrypt-driven key import from the unknown-key icon.** Today `pgp_sig_unknownkey`
   (this project) and `smime_cert_unknownemail` (existing S/MIME code, `setSmimeFlags()`) just
   render a purple/neutral icon with no click action - `setPgpSignatureFlags()`/`setSmimeFlags()`
   already wire an `onclick` for their *other* states (`smimeCertAddToContact()` for S/MIME's
   verified/notverified/notvalid cases - see `smime_signature`/`smime_encryption` `onclick` in
   `setSmimeFlags()`, `mail/js/app.ts`). When the message carries an `Autocrypt:` header (the
   [Autocrypt](https://autocrypt.org/level1.html) standard: `Autocrypt: addr=<email>;
   keydata=<base64 key, no ASCII armor>`, optionally with a `prefer-encrypt` attribute) and no
   matching key was found in the addressbook, clicking the icon should offer a dialog ("this
   message includes a public key for %1, save it to the addressbook?") rather than the icon
   staying inert. Needs: (a) surfacing the raw `Autocrypt` header value up to the client - check
   whether JMAP `Email/get`'s `header:Autocrypt:asText` (RFC 8621 §4.1.3 convenience property) is
   already exposed anywhere in `MailJmap`, or needs adding to the `properties` list fetched in
   `verifyPgpSignature()`/the S/MIME equivalent; (b) decoding the `keydata=` value (base64 -> raw
   OpenPGP key bytes -> needs re-wrapping in ASCII armor, since `ajax_set_pgp_keys` and the
   addressbook's stored-key format expect armored text, and `openpgp.js`'s own
   `key.armor()`/`readKey({binaryKey})` can do that conversion); (c) an S/MIME equivalent needs its
   own key-source, since Autocrypt itself is OpenPGP-only by spec - worth clarifying with ralf
   whether "Autocrypt header" for the S/MIME case actually means a *different*, S/MIME-specific
   convention (e.g. the sender's cert as a `smime.p7s`/`application/pkcs7-mime` attachment, which
   S/MIME signed messages already carry) rather than a literal `Autocrypt:` header, since no such
   header exists for S/MIME in any standard.
2. **Sending an Autocrypt header on outgoing mail.** If the sending account has its own public key
   stored in the addressbook (`Api\Contacts::FILES_PGP_PUBKEY` - the same store
   `ajax_get_pgp_keys()`/`ajax_set_pgp_keys()` already read/write), add an `Autocrypt:` header to
   outgoing mail carrying it (PGP case: literal Autocrypt spec, base64 raw key + `addr=`). Needs
   the same "S/MIME equivalent" clarification as above - S/MIME doesn't have an Autocrypt-shaped
   header/spec, so this may end up being "attach/reference the sender's cert" rather than a literal
   header, unless ralf wants a custom (non-standard) analogous header. Likely lands in
   `mail/js/compose.ts`'s `trySendViaJmap()`/`trySaveDraftViaJmap()` (where the PGP-encrypt-via-
   Mailvelope call already happens) or server-side in `Api\Mail\Jmap\Imap::buildMailerFromEmailProperties()`
   depending on whether it needs to run whether or not Mailvelope is even involved (an unsigned,
   unencrypted plain outgoing mail should presumably still get the header if the account has a
   published key - this is opportunistic key distribution, independent of whether *this particular
   message* is signed/encrypted).
3. **Mailvelope: also PGP-sign outgoing mail, not just encrypt.** Today's Mailvelope integration
   (`mail/js/app.ts`'s `mailvelope_editor`, `mail/js/compose.ts`'s `trySendViaJmap()`/
   `trySaveDraftViaJmap()` - see [[mail-pgp-mailvelope-fixes]]) only ever calls
   `mailvelope_editor.encrypt(recipients)`, producing `multipart/encrypted`. Mailvelope's own
   editor API additionally supports **sign-only** (`multipart/signed`, no encryption - the exact
   shape `verifyPgpSignature()` above already knows how to check) and *signed-then-encrypted*
   combined output; need to check the installed Mailvelope version's exact editor API for
   requesting a signature (likely an editor-creation option like `armorHeaders`/a `sign: true`
   equivalent, or a separate `editor.sign()` call before/instead of `encrypt()` - needs checking
   against Mailvelope's actual client-API docs, not assumed). This directly supersedes this doc's
   own earlier "Explicitly out of scope" line about not changing how EGroupware *sends*
   signed/encrypted PGP mail (see below) - that exclusion was written when this project was
   verify-only; sending-side signing is now explicitly wanted, just via Mailvelope rather than
   `openpgp.js` (Mailvelope holds the private signing key, `openpgp.js` here never does - same
   division of responsibility as encrypt/decrypt already has).

None of these three has been designed in detail yet (no data-flow spike, no UI mock, no code) -
this section is a plan-level placeholder capturing the ask, not an implementation.

## Explicitly out of scope for this project

- PGP **encryption/decryption** - stays exactly as-is via Mailvelope; this project is
  signature-verification-only. (Superseded for the *signing* half by planned-follow-up item 3
  above - encryption itself is still out of scope, unchanged.)
- Verifying `multipart/encrypted` PGP/MIME's own inner signature (if the encrypted payload itself
  contains a signed-then-encrypted structure) - Mailvelope already decrypts that client-side and
  reports its own signature status; not this project's concern.
- Any change to how EGroupware *sends* signed/encrypted PGP mail. (Superseded by planned-follow-up
  items 2 and 3 above.)
