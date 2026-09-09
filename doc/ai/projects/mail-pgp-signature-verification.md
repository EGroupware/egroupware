# Mail: verify PGP/MIME signatures natively (no Mailvelope dependency)

## Status: Phase 3 UI wiring done + live-verified (2026-09-08); ralf's own live testing 2026-09-09
surfaced 5 real bugs (4 UI-wiring, 1 in the core verify engine itself), all fixed + live-verified
same day (see below). **Phase 4 (unit tests) done for the verify engine + attachment-hiding**
(2026-09-09): `PgpSignatureVerification.test.ts` (end-to-end `verifyPgpSignature()` against a real
openpgp.js fixture - verified/tampered/inline-key/no-key/not-signed), `PgpSignatureArmorExtraction.
test.ts` (the base64-CTE regression), and S/MIME's own attachment-hiding coverage in
`CreateAttachmentBlockTest.php` (every `Mail\Smime::$SMIME_TYPES` control part, which also found +
fixed a real array-reindexing bug in `createAttachmentBlock()`); `findPgpSignaturePart()`/
`sliceMultipartSigned()`/`extractMultipartSignedBoundary()` now have their own isolated unit tests
too (`PgpSignatureStructureHelpers.test.ts`, 2026-09-09), closing the one gap the previous status
note called out. 4 follow-up items queued 2026-09-09 (Autocrypt key-import dialog, sending an
Autocrypt header, Mailvelope sign-on-send, reply/forward auto-matching signed/encrypted state) -
see "Planned follow-up" below; item 4's PGP-encrypted half is now DONE + live-verified (2026-09-09,
see its own entry), the other three (and item 4's PGP-signed half) still not started. **Autocrypt
integration (Phase 5) planned in full 2026-09-09** (own section further down) - consent dialog +
preference, `Autocrypt`/`Autocrypt-Gossip` send+receive, `prefer-encrypt` storage, the
multi-key-per-address storage fix, mutual-auto-encrypt preference, S/MIME auto-add-when-verified,
and an explicit Level 1 spec gap/deviation audit. Phase 5 step 1 (keydata minimize/re-armor) is now
DONE (2026-09-09, see its own phasing entry) - the rest of Phase 5 is still plan only.

### 2026-09-09 core-engine bugfix: base64-encoded signature parts

`MailJmap.extractSignatureArmor()` (`mail/js/jmap.ts`) assumed everything after a
`application/pgp-signature` part's own header block *is* the ASCII-armored signature text - true
for Thunderbird/Enigmail (leaves it plain 7bit), but a real message from a third-party sender
(name/subject withheld here - no consent to publish those details; structurally the Phase 1
spike's "older.eml" fixture) has `Content-Transfer-Encoding: base64` on that part specifically.
`openpgp.readSignature()` then threw "Misformed armored text" on the still-base64-encoded bytes,
silently caught by `verifyPgpSignature()`'s top-level `try/catch` - the message showed **no PGP
icon at all** (found live 2026-09-09, ralf: testing that message showed it "is not shown as
signed" despite being PGP signed with a structure differing from the Thunderbird-signed case),
not even the "unknown key"/"invalid" states the doc's own 3-state model has for exactly this kind
of case. Fixed by checking that header and `atob()`-decoding first when it says `base64`. New
unit test coverage in `mail/js/test/PgpSignatureArmorExtraction.test.ts` (the extraction function
is pure/static, no JMAP mocking needed).

**Important caveat found while live-verifying this fix**: the live test mailbox's several copies of
this message all still show `verified:false` (`pgp_sig_invalid`, correctly *shown* now, just not
*verified*) - independently confirmed this is **not** a remaining code bug: downloaded the original
`.eml` from ralf's own Downloads folder, ran the exact same extraction algorithm against it by hand
(Python port, `/tmp` scratch script), and `gpg --verify` against that byte-exact original returned
"Korrekte Signatur" successfully using the sender's own inline `application/pgp-keys` key - proving
the extraction/verification logic itself is correct for this structural shape. The live mailbox's
stored copies have a *different* SHA-256 than that original file, meaning whatever import/resend
process put those copies into this test account altered the bytes somewhere - fatal for a
byte-exact MIME signature, but a pre-existing test-data fidelity issue outside this project's
scope, not something to "fix" in `verifyPgpSignature()` itself. Ralf is investigating that
byte-fidelity gap separately (2026-09-09: "our eml export is not byte exact").

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

1. **Autocrypt-driven key import from the unknown-key icon** and **sending an Autocrypt header on
   outgoing mail** - both **superseded 2026-09-09** by the much more detailed "Autocrypt integration
   (Phase 5)" plan further down this doc (consent dialog + preference, `Autocrypt-Gossip:` sending/
   receiving, `prefer-encrypt` storage, the multi-key-per-address storage fix both PGP and S/MIME
   need, and an explicit Level-1-spec gap audit) - see that section for the real plan, this entry
   stays only as a pointer so old links/history still resolve.
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

4. **Reply/forward auto-matches the original message's signed/encrypted state - PGP-encrypted half
   DONE (2026-09-09), PGP-signed half still blocked on item #3 above.** ralf: "we should automatic
   enable the toggles to the same state, so by default we answer signed messages with signed
   messages (there's currently a gap with PGP), and encrypted messages with encrypted messages."
   **Default only, never enforced** - the user can still switch a toggle back off for that one
   reply/forward.
   - **S/MIME**: turned out to already work, unrelated to today's work - `composeMessage()`
     (`mail/js/app.ts`) has long set `settings.smime_type = content.data['smime']` (the row's own
     server-known field) unconditionally, threaded through `compose.php`/`bootstrapComposePopup()`
     to pre-check `smime_sign`/`smime_encrypt` - confirming ralf's own suspicion that S/MIME was
     the one NOT missing this.
   - **PGP encrypted -> pre-check `pgp`**: newly built. `MailJmap.peekPgpEncrypted()` (a synchronous
     cache of `fetchBody()`'s own structural PGP-encrypted detection, populated whenever a message
     is actually viewed - PGP has no server-known row field like `smime` to read synchronously) ->
     `composeMessage()` -> `pgp_encrypted=1` through `compose.php` -> `bootstrapComposePopup()`.
     Pre-checking the button alone was NOT enough, unlike S/MIME (which just reads the widget's
     value at send time) - PGP additionally needs a real, initialized `mailvelope_editor`, which
     needs `mailvelopeGetCheckRecipients()` to actually run (imports the recipient's key into
     Mailvelope's own keyring, fetching it from the addressbook server-side first if needed).
     `bootstrapComposePopup()` now explicitly calls `togglePgpEncrypt({checked: true})` - the exact
     same code path a real click uses - but only AFTER `MailCompose.bootstrapReply()` (via its own
     `bootstrapPromise`) has populated the reply's real recipient; triggering it right after the
     initial template bootstrap left the "To" field still empty, so the key-check ran for nobody.
     Both gaps found live (ralf: "just setting the toggle seems not to trigger Mailvelope").
     Live-verified: recipient correctly populated, `mailvelope_editor` created, action's `checked`
     state still `true` (not reverted to `false`, which `togglePgpEncrypt()`'s own error path does
     on a real failure).
   - **PGP signed -> "answer signed with signed"**: still not possible. The `pgp` compose action is
     encryption only (Mailvelope) - there is no PGP *sign*-on-send mechanism at all yet (item #3
     above). `MailJmap.pgpSignatureCache`/`peekPgpSignature()` already exist (mirroring
     `pgpEncryptedCache`/`peekPgpEncrypted()`) ready for when item #3 adds a real sign toggle to
     pre-check against, but `composeMessage()` doesn't call `peekPgpSignature()` yet - deliberately
     not force-fitting a guessed-at future action id/param shape before item #3's own design (which
     needs to check Mailvelope's actual editor API first) is worked out.

None of items 1-3 has been designed in detail yet (no data-flow spike, no UI mock, no code) - that
part of this section is still a plan-level placeholder capturing the ask, not an implementation.

## Autocrypt integration (Phase 5) - plan only, 2026-09-09, nothing implemented yet

Ralf's ask, verbatim (2026-09-09): attach an `Autocrypt:` header for our own key + `Autocrypt-Gossip:`
headers for recipients' known keys on send; consume a replied/forwarded message's own
`Autocrypt-Gossip:` headers as a key source for Mailvelope; ask-and-remember consent before storing
a key learned this way; fix multi-key-per-contact storage for PGP *and* S/MIME; store `prefer-encrypt`
alongside a stored key; audit against the [Autocrypt Level 1 spec](https://docs.autocrypt.org/level1.html)
for gaps/contradictions; add a "mutual" auto-encrypt preference; auto-add a verified S/MIME sender's
cert to an already-known contact without asking.

### What Autocrypt actually is (spec summary, Level 1)

Two headers, both `addr=...; [prefer-encrypt=mutual;] keydata=<base64>` shaped:

- **`Autocrypt:`** - one per outgoing message, `addr` MUST equal the `From` address, `keydata` is
  the *sender's own* public key. Sent unconditionally (not just to known peers) - this is what makes
  it "opportunistic": every recipient of any mail from us learns our key, without ever asking them
  first.
- **`Autocrypt-Gossip:`** - one **per recipient** (`To`/`Cc`/`Reply-To`), each carrying *that
  recipient's* key as this message's sender already knows it. **MUST be inside the encrypted MIME
  payload, never in the plaintext outer headers** ("SHOULD NOT be added outside encrypted MIME part
  to avoid leaking third-party metadata") - this is how a group conversation bootstraps everyone's
  keys to everyone else without a directory lookup: if A encrypts to B and C, gossiping both of
  their keys, B and C now each know the other's key too, enabling an encrypted reply-all.

`prefer-encrypt=mutual` is a mutual opt-in signal: "if you (the peer) also prefer-encrypt=mutual,
default new messages between us to encrypted." Absence means `nopreference` - never send any other
value. The spec's own recommendation algorithm only turns encryption on by default when **both**
sides have said `mutual` (or the message is itself a reply to something that was encrypted - already
built, see item 4 above).

Keydata is **not** the armored text we store today: "MUST consist of exactly five packets:
signing-capable primary key, user ID, self-signature, encryption-capable subkey, binding signature.
Content MUST be binary format (not ASCII-armored), then base64-encoded." A minimized, single-UID,
single-subkey export - not whatever a real-world key (multiple UIDs, multiple subkeys, third-party
signatures, revocations) actually contains.

### Where our design deliberately diverges from Level 1 (and why)

Level 1's own model is a **private, per-account, disposable cache** (`peers[addr]`) that updates
itself silently on every incoming message, no user prompt ever, specifically to stay frictionless.
Our addressbook is the opposite: a **shared, authoritative, multi-user contact store** - silently
overwriting a shared contact's stored key from a header nobody reviewed is a materially bigger deal
than Level 1's own throwaway cache. Ralf's ask/no-ask consent dialog + preference is a deliberate,
correct adaptation for that context, not a spec gap to "fix" - documenting this explicitly so a
later reviewer doesn't try to make this match the spec's own no-prompt design.

Two consequences of that divergence, worth being explicit about:
- We are **not** building the full `peers[addr]` state machine (`last_seen`/`autocrypt_timestamp`/
  `gossip_timestamp`, "youngest header always wins" auto-overwrite, the 35-day staleness-discourage
  rule). What we *are* building - explicit consent before ever touching the addressbook, backed by
  the existing key-storage functions - covers the actually-wanted behavior (learn keys
  opportunistically, but a human reviews what gets kept) without needing that machinery.
- The spec's per-peer model has **exactly one key slot per address**, full stop ("Each peer indexed
  by canonicalized email address has single `public_key` and single `gossip_key` ... No support for
  multiple keys per peer in Level 1"). That's *consistent* with fixing our own per-CONTACT-RECORD
  storage to be per-ADDRESS instead (see below) - a contact with a business and a home address is,
  in Autocrypt's own terms, two separate peers, each with its own one key slot. It does **not**
  justify multiple keys for the *same* address; "youngest wins" is the spec's answer to that, which
  our own "ask before overwriting" consent step effectively replaces with a human decision instead.

### 1. Fix: PGP/S-MIME key storage is per-contact-record, not per-address

Confirmed in `addressbook_bo::set_keys()`/`get_keys()` (`addressbook/inc/class.addressbook_bo.inc.php`,
shared by both `$pgp=true` and `$pgp=false`/S-MIME): a key is stored as a single VFS file
(`Api\Link::vfs_path('addressbook', $contact['id'], $file)`, `$file` = `Api\Contacts::FILES_PGP_PUBKEY`
or `FILES_SMIME_PUBKEY`) keyed purely by **contact id** - one file per contact, full stop. `get_keys()`
already searches both `contact_email` *and* `contact_email_home` and can return the same stored
content under either address, but there is only ever one thing stored: if a contact has a business
and a home address using two *different* real-world keys, storing a key learned for one silently
clobbers whatever was stored for the other the moment `set_keys()` runs again. This is the literal
gap ralf flagged ("we already support 2 email addresses (business and home)") - confirmed real, not
just theoretical, and it blocks both Autocrypt (business/home genuinely often use different keys)
and the existing S/MIME manual-add flow equally.

**Fix shape (needs a real spike before locking in, but the direction is clear)**: replace the bare
single-key VFS file with a small structured store keyed by address - simplest option is a JSON file
at the same VFS path (`{"business@x.com": {key: "-----BEGIN...", prefer_encrypt: "mutual"},
"home@x.com": {...}}`), keeping `set_keys()`/`get_keys()`'s existing per-contact-id file location
and ACL/backend dispatch (`pubkey_use_file()`) unchanged - only the *content* of that one file
changes shape, and old single-armored-key files need a one-time read-side fallback (a file that
doesn't parse as JSON is treated as "one key, address unknown" for backward compatibility with
every contact that already has a key stored today). `ajax_get_pgp_keys()`/`ajax_set_pgp_keys()`'s
own email|account_id -> key **map** shape already matches this per-address model on the wire; the
contact-record storage layer underneath is the only thing that needs to change.

### 2. Store `prefer-encrypt` as a comment, not a new column

Ralf's own proposed shape - prepend a comment line to the stored key text rather than a new DB/VFS
field: `# prefer-encrypt=mutual\n-----BEGIN PGP PUBLIC KEY BLOCK-----...`. Combined with the
per-address JSON restructuring above, this naturally becomes a `prefer_encrypt` property alongside
each address's `key` entry instead of a literal comment line - functionally the same "carry it with
the key, no new schema" idea, just placed in the new structure rather than as literal prepended
text. (If the multi-key fix above turns out to need more design time than the comment-prefix idea,
the comment-prefix approach also works standalone against *today's* single-key-per-contact storage
as a smaller first step - worth keeping in mind as a fallback ordering.)

### 3. Sending: `Autocrypt:` (own key) and `Autocrypt-Gossip:` (recipients' keys)

- **`Autocrypt:`** - if the sending identity's own account has a stored PGP public key (`ajax_get_pgp_keys()`
  against the account's own email, or account keys may need their own lookup path - check), convert
  it from our armored storage to Autocrypt's binary-minimized form (openpgp.js can read the armored
  key and re-export/minimize it - needs confirming its API actually supports stripping down to the
  mandated 5-packet shape, see the "real gaps" list below) and add the header. Include
  `prefer-encrypt=mutual` only if the NEW "mutual" preference (item 8 below) is on for this account.
  Unconditional per the spec (every outgoing message, not just to known Autocrypt peers) - matches
  "opportunistic."
- **`Autocrypt-Gossip:`** - one per `To`/`Cc` recipient whose key is in the addressbook, **only when
  the message is actually being encrypted** (Mailvelope on) - per the spec, gossip headers belong
  inside the plaintext MIME payload that then gets encrypted, never as a bare outer RFC 5322 header.
  Concretely: build the `Autocrypt-Gossip:` header lines and prepend them to the plaintext body
  `mail_plaintext`/`mail_htmltext` content **before** calling `mailvelope_editor.encrypt()`, so they
  end up inside the ciphertext exactly as the spec requires - NOT alongside the outer `Autocrypt:`
  header, which stays outside as normal. Needs checking exactly how Mailvelope's own editor
  API/output represents the payload's own header block (does `encrypt()` let us inject arbitrary
  header lines into the to-be-encrypted MIME part, or do we need to hand-assemble that part
  ourselves before handing it to Mailvelope?) - a real unknown to resolve during implementation, not
  assumed here.
- Where in the send path: `MailJmap.createDraftEmail()`/`draftEmailProperties()` (`mail/js/jmap.ts`)
  build the outgoing `Email/set` properties today: RFC 8621 §4.1.3's `header:<Name>:asRaw` property
  shape is already used read-side in this file (`MDN_HEADER_PROPERTY`, `CONTENT_TYPE_HEADER_PROPERTY`)
  - the write-side equivalent should work the same way for a single `Autocrypt:` header; **multiple**
  same-named `Autocrypt-Gossip:` headers (one per recipient) may need an array-valued header
  property or a different JMAP mechanism entirely - needs verifying against both Stalwart's real
  JMAP and the local shim's own header-writing support before assuming either works.

### 4. Receiving: use a replied/forwarded message's own `Autocrypt`/`Autocrypt-Gossip` headers as a key source

When Mailvelope-composing a reply/forward (`mailvelopeCompose()`, `mail/js/app.ts` - already reads
the quoted PGP-armored body out of the source message for the "reply to encrypted message" case),
also parse that source message's own raw headers for `Autocrypt:` (the original sender's key -
useful if the addressbook doesn't have it yet) and `Autocrypt-Gossip:` (other recipients' keys, if
this was a group message) and feed anything found through the same consent-dialog path as item 5
below, rather than silently trusting it. Per the spec's own robustness notes: **discard entirely**
if more than one `Autocrypt:` header is present on the message (a spec-defined error condition, not
just "take the first one"), and skip peer-state-relevant processing entirely for
`multipart/report` messages (MDNs) and messages with more than one `From` address - both apply
equally to us even without building the full peer-state machine, since they're about not trusting
malformed/adversarial input, not about the state machine itself. Needs the reverse of item 3's
binary-minimize conversion: base64-decode the header's `keydata`, then re-armor it (`key.armor()`)
to match our own storage convention.

### 5. Consent dialog + new preference

Reuses `smimeCertAddToContact()`'s existing dialog shape (`mail/js/app.ts` - a "Close" / "Add this
certificate into contact" `et2-dialog`, currently S/MIME-only, opened on click) as the template for
a new PGP-key-consent dialog, but triggered differently: not on click, but automatically whenever a
key was newly learned (via item 3's `Autocrypt:` header on an incoming message, or item 4's gossip
parsing) for an address the addressbook has **no** stored key for yet - buttons **Yes / No / Never
ask again**, matching ralf's own wording exactly (a 3rd button beyond the existing 2, or a
dialog-level checkbox - needs a real UI decision when this gets built, not decided here). "Never ask
again" persists into a new preference, mirroring the `previewPane` shape in `mail_hooks::settings()`
(`mail/inc/class.mail_hooks.inc.php`): a `select` with `''` (default, ask every time) / `'never'`
(never ask) - name TBD at implementation time (e.g. `autocrypt_ask_import`).

### 6. New preference: "mutual" auto-encrypt

A second new preference (checkbox or 3-way select, TBD): when on, and the current compose's
recipient(s) all have a stored `prefer_encrypt=mutual` (item 2's storage) **and** our own account's
own `Autocrypt:` header also carries `prefer-encrypt=mutual` (item 3), auto-enable the `pgp` toggle
for a **new** compose (not just reply-to-encrypted, which is already built - see item 4 in the
"Reply/forward auto-matches" entry above). Reuses the exact same `togglePgpEncrypt({checked: true})`
+ post-`bootstrapPromise` timing this session's reply/forward auto-encrypt work already established
(`bootstrapComposePopup()`, `mail/js/app.ts`) - the recipient-population-must-finish-first and
recipient-key-check-must-actually-run lessons from that work apply identically here, just with a
different *reason* to decide `pgpEncrypted='1'` (stored `prefer_encrypt` match instead of "was the
source message encrypted").

### 7. S/MIME: auto-add a verified sender's cert to an already-known contact, no dialog

Small addition to the existing S/MIME verify flow (`setSmimeFlags()`, `mail/js/app.ts`): when a
message's S/MIME signature verifies (`data.verify` true) **and** the sender's email already matches
an existing addressbook contact, call the same `ajax_smimeAddCertToContact` the dialog's "Add this
certificate" button already uses, directly - skip the dialog entirely, since a *verified* signature
from an *already-known* contact needs no extra confirmation (unlike an unknown/unverified one, which
keeps today's click-to-add dialog unchanged). Also needs the same multi-key-per-address storage fix
(item 1) - a contact with two addresses, each with their own real S/MIME cert, has exactly the same
"second cert clobbers the first" problem PGP has today.

### Other spec gaps/contradictions worth flagging now (not necessarily fixing in v1)

- **Keydata must be minimized + binary, not our stored armored text.** The single biggest technical
  gap: `Autocrypt:`/`Autocrypt-Gossip:` headers are NOT "base64 of whatever armored key we have" -
  they're base64 of a specific, minimal 5-packet binary export. Needs real openpgp.js engineering
  (read the stored key, produce a minimized single-UID/single-subkey binary export) before any
  header can be legally emitted - this is not a formatting detail, a full multi-UID/multi-subkey key
  sent as `keydata` violates the spec outright and other implementations may reject it.
- **10 KiB header size cap** ("MUAs MUST NOT send headers exceeding 10 KiB including prefix and
  folding whitespace") - should be a hard check before emitting any Autocrypt/Gossip header, skip
  (don't truncate) and log if a key is too large after minimization.
- **Address canonicalization** (lowercase local part, IDNA/punycode domain) needed when matching an
  incoming header's `addr=` against addressbook email fields - `get_keys()` already lowercases for
  its own search, worth confirming that's sufficient or needs extending for IDNA domains.
- **Multiple accounts/aliases** - "Each account/alias MUST have distinct `accounts[from-addr]`
  entry" - relevant since EGroupware mail accounts already support multiple identities; the "own
  key for the Autocrypt header" lookup (item 3) must be keyed by the *actual From address being
  sent from*, not a single fixed "the user's key," if a user has more than one configured identity.
- **Not building**: the 35-day `autocrypt_timestamp`-vs-`last_seen` staleness/discourage heuristic,
  Setup Message / Setup Code (transferring one's own key+secret between devices via a passphrase-
  protected message) - both explicitly deferred, no ask for either today.

### Suggested phasing (draft, not committed to)

1. **DONE (2026-09-09).** Spike + implement the keydata minimize/binary-export + re-armor round
   trip: `MailJmap.armoredKeyToAutocryptKeydata()`/`autocryptKeydataToArmoredKey()` (`mail/js/jmap.ts`),
   with real openpgp.js-generated multi-UID/multi-subkey fixture coverage
   (`mail/js/test/PgpAutocryptKeydata.test.ts`). Confirmed live in the spike (full `openpgp` npm
   package used only to *generate* the test fixture, since key generation needs it - the lightweight
   build alone was used for the actual minimize/export/re-read/armor logic under test): the
   lightweight build's packet-level API (`PacketList`, `readKey({binaryKey})`, `.armor()`) is
   sufficient - **no second, separate full-build load is needed** for Autocrypt key handling, closing
   what would otherwise have been an open bundle-size question. Minimization takes the PRIMARY user's
   own existing self-signature and the encryption-capable subkey's own existing binding signature
   as-is (no re-signing, which would need the private key - unusable for a contact's key we only
   ever have the public half of) and reassembles just those 5 packets; returns `null` for a
   signing-only key (no valid `getEncryptionKey()`), which the spec's own 5-packet shape doesn't fit
   anyway.
2. Multi-key-per-address storage fix (item 1) - unblocks everything else, including the existing
   S/MIME single-cert limitation, independently useful even without any Autocrypt work landing yet.
3. Sending `Autocrypt:` (own key) - the simplest, most self-contained piece, no consent-dialog UI
   needed (never touches another contact's stored data).
4. Consent dialog + preference (item 5), then receiving/gossip-parsing (items 3's gossip half, 4) -
   the two together are what actually needs the dialog.
5. `prefer-encrypt` storage (item 2) + mutual auto-encrypt preference (item 6).
6. S/MIME auto-add-when-verified (item 7) - small, independent, can land any time after item 1.

## Explicitly out of scope for this project

- PGP **encryption/decryption** - stays exactly as-is via Mailvelope; this project is
  signature-verification-only. (Superseded for the *signing* half by planned-follow-up item 3
  above - encryption itself is still out of scope, unchanged.)
- Verifying `multipart/encrypted` PGP/MIME's own inner signature (if the encrypted payload itself
  contains a signed-then-encrypted structure) - Mailvelope already decrypts that client-side and
  reports its own signature status; not this project's concern.
- Any change to how EGroupware *sends* signed/encrypted PGP mail. (Superseded by planned-follow-up
  items 2 and 3 above.)
