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
and an explicit Level 1 spec gap/deviation audit. Phase 5 steps 1 (keydata minimize/re-armor), 2
(multi-key-per-address addressbook storage, **now including its `prefer-encrypt`-attribute storage
half too**), **3's `Autocrypt:`-sending half, 4's pure header-parsing logic, 5 (consent dialog +
preference, triggered by the already-working inline-key case rather than Autocrypt headers - see
its own entry for why), and 7 (auto-add for an already-known contact, extended from S/MIME-only to
BOTH S/MIME and PGP)** are DONE (2026-09-09, see their own phasing entries) - `Autocrypt-Gossip:`
sending, actually wiring item 4's header parser to a real incoming message's Autocrypt HEADER
specifically (item 5's dialog itself already ships, just not fed by that source yet), and item 6's
mutual auto-encrypt preference are still plan only; the new `prefer-encrypt` storage API from item 2
still has no caller (item 6 is what would set it). **Phase 5 step 2's alias-pointer storage rework
(Phase G) is now also DONE (2026-09-09)** - see its own entry under item 1 below.
**Security fix (Phase H, DONE 2026-09-09)**: a signature/cert that cryptographically verifies but
doesn't itself claim the message's From address is now shown as invalid, not verified, for both PGP
and S/MIME - see its own section below, right before "Why this is even possible without Mailvelope".

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

### 2026-09-09 Security fix (Phase H): signature must not verify unless the key/cert itself claims the sender's address

Ralf's own security-flagged request: *"Can you check that we only show a signature (s/mime or pgp)
as validated, IF it's key matches the From header, otherwise it should be shown as invalid. I
believe that's also in the Autocrypt standard and I also want a test for that, as it's security
relevant, if there's none yet (both s/mime and pgp)."* Correct instinct - Autocrypt Level 1 itself
requires exactly this alignment (`addr=` in the `Autocrypt:` header must match the message's own
`From`, or the whole header is discarded as invalid) - and the reasoning generalizes beyond
Autocrypt specifically: a cryptographically valid signature proves nothing about the claimed sender
if the signing key/cert doesn't itself claim that address, the same reason DKIM/DMARC alignment
checks exist. Before this fix, a key/cert could verify successfully while belonging to a completely
different identity than the message's `From:` and still render as "verified" - eg. an attacker's
own genuinely-issued key/cert, attached inline or filed under the wrong addressbook contact,
signing a message that merely *claims* to be from someone else.

**PGP** (`mail/js/jmap.ts`): `MailJmap.verifyPgpSignature()`'s result gained an `addressMismatch?:
boolean` field; a new `keyClaimsAddress(key, email)` checks the key's own `getUserIDs()` for a
bracketed `<email>` (or bare-email) match, case-insensitively, against ANY of the key's UIDs (a key
legitimately carrying multiple UIDs - eg. work + personal - only needs to claim the address
somewhere). Checked regardless of `keySource` - an inline (message-supplied, attacker-controlled on
a malicious message) key is the obvious risk, but an addressbook-stored key could equally be filed
under the wrong contact by mistake; this is a correctness check on the KEY, not a trust judgement
about where it came from. On a mismatch, `verified` is forced `false` and `addressMismatch: true` is
set, so `MailApp.setPgpSignatureFlags()` (`mail/js/app.ts`) can show a more specific statustext
("PGP/MIME signed message, signature does NOT belong to sender %1") while still rendering the same
`pgp_sig_invalid` (red) state as any other verification failure.

**S/MIME** (`api/src/Mail/Smime.php`): the `unknownemail` check already existed in
`resolveMessage()` (cert email / `subjectAltName` vs. `$fromAddress`, feeding
`X-EGroupware-Smime`'s metadata) - the gap was purely on the UI side: `MailApp.setSmimeFlags()`
(`mail/js/app.ts`) rendered `data.unknownemail` as a separate, softer `smime_cert_unknownemail`
(purple) state, visually distinct from - and less alarming than - an outright broken signature. Now
removes the `smime_cert_verified`/`smime_cert_notverified` classes and renders the SAME
`smime_cert_notvalid` (red) severity `setSmimeFlags()`'s own verify/cert branches already use for a
broken signature, with its own new statustext ("S/MIME signed message, signature does NOT belong to
sender %1").

**Tests** (none existed for this before, on either side, confirmed via grep across
`SmimeMailerTest.php`/`SmimeResolveMessageTest.php`/`StructureToHtmlTest.php`/`mail/js/test/`):
- PGP: a new case in `PgpSignatureVerification.test.ts` reuses the file's real openpgp.js fixture
  (same key/signature/message bytes as the existing verified-path test) but looks the key up under
  a *different* address than the fixture's own UID - simulating a misfiled addressbook entry -
  asserting `verified:false, addressMismatch:true`.
- S/MIME: two new tests in `SmimeResolveMessageTest.php` build a genuinely signed (`TYPE_SIGN`,
  `multipart/signed`, no stored account credential needed - `resolveMessage()`'s signature-only path
  never calls `get_acc_smime()`) message against a real self-signed cert, then call
  `resolveMessage()` with a mismatched vs. matching `$fromAddress` and assert `unknownemail` is/isn't
  set. (Note: a self-signed test cert never chain-verifies against a trusted CA in this environment
  regardless of address matching - `$metadata['verify']` stays `false` for an unrelated,
  pre-existing reason - so these tests assert on `signed`/`email`/`unknownemail` instead of `verify`.)
- No JS-side rendering tests were added for `setSmimeFlags()`/`setPgpSignatureFlags()` themselves
  (confirmed via grep: none exist for either method today) - out of scope for this fix, which is
  scoped to the underlying verify-result correctness; those methods are widget/DOM-heavy and would
  need their own dedicated test harness if ever covered.

Committed together (production code + both sides' tests) in a single commit, not yet pushed.

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

**DONE (2026-09-09), in two passes - Phase G being the second, final rework:**

*Pass 1*: replaced the bare single-key VFS file with a JSON object keyed by lowercased address,
each value originally `{"key": "-----BEGIN..."}`, keeping `set_keys()`/`get_keys()`'s existing
per-contact-id file location and ACL/backend dispatch (`pubkey_use_file()`) unchanged - only the
*content* of that one file changes shape. Found + fixed two real, independent bugs while building
this: `set_keys()`'s own search `$criteria` only ever included `contact_email`, never
`contact_email_home`, so a key stored for a contact's home-only address silently matched nothing;
and a brand-new contact with no `.files/` VFS directory yet (no photo/key/anything ever attached)
failed its very first key write outright (`file_put_contents()` can't create a missing parent
directory through the VFS stream wrapper) - fixed with an explicit `Api\Vfs::is_dir()`/`mkdir()`
guard. Initial tests in `addressbook/tests/AddressbookBoMultiKeyStorageTest.php` (reflection against
the two pure-function private statics, `\PHPUnit\Framework\TestCase`, no DB/session - the shared
docker environment's own account search hangs even for the *original* unmodified `set_keys()`,
confirmed via a git-stash comparison, a pre-existing environment limitation unrelated to this fix).

*Pass 2 (Phase G, ralf's own design refinement)*: ralf's own question - *"so currently we only have
the base64 encoded / armored key in the file, in future you propose to use a json object and index
the keys by their address, and probably aliases for further addresses... so if we do NOT find a
JSON object, we can only try to extract the address from the cert or assume it's one of the given
email of the contact... Probably verifying it when there's only a single cert/key makes a lot of
sense"* - then *"hmm, cant we detect the address from the public key, as least for s/mime it should
be easy via openssl as it's the CN... not sure about PGP"* - led to reworking the format one more
time before it shipped further: **the per-address value is now EITHER the armored key/cert text
directly, OR a plain address string naming another entry in the SAME object to use instead** (an
alias) - `{"business@x.com": "-----BEGIN...", "home@x.com": "business@x.com"}` - dropping the
`{"key": ...}` wrapper. `merge_keys_json()` creates these aliases automatically: storing the exact
same armored text under a second address makes the second entry a plain alias to the first instead
of duplicating the (often multi-KB) text again; `extract_key_for_address()` follows an alias chain
(cycle-guarded via a `$seen` map) to whatever it ultimately resolves to.

A new `detect_smime_address()` (`openssl_x509_parse()`, `subjectAltName`'s `email:` value first,
falling back to the subject DN's `emailAddress` attribute - confirmed both fields' exact shape via a
real generated test cert) gives S/MIME two things PGP structurally can't have (no `gnupg` extension
or PHP OpenPGP library anywhere in this stack, and armored PGP text is base64-encoded binary - a
key's own User ID isn't readable server-side at all, only client-side via `openpgp.js`, see
`MailJmap.keyClaimsAddress()` in the Phase H security fix above): (1) migrating a legacy (pre-format,
bare-armored-text) stored cert now keys it by the address `detect_smime_address()` reads out of it,
instead of always falling back to the address-unknown `"*"` entry; (2) when a contact's ONLY stored
S/MIME entry is that `"*"` fallback (nothing else on file - the check is skipped once more than one
entry exists, that's the normal multi-address case), the cert's own detected address is cross-checked
against whatever address is actually being asked for before handing it back at all - refusing to
offer a leftover/mismatched single key just because it's the only thing on file. Explicitly does
**not** clean up/remove a stale key when a contact's email changes outright (ralf's own deferred
scope decision - a separate, later concern) - it only stops that one leftover key from being
silently offered for an address it doesn't belong to.

`AddressbookBoMultiKeyStorageTest.php` rewritten for the new flat/alias format and `bool $pgp`
parameter (18 tests: same-content dedup -> alias, alias-chain following, cyclic-alias guard, legacy
S/MIME migration address detection vs. PGP's unconditional `"*"` fallback, the single-`"*"`-entry
S/MIME cross-check match/mismatch/skip-when-multiple-entries cases, and PGP's own entry never being
cross-checked at all).

**Follow-up, same day**: ralf caught a gap right after the above shipped - *"sorry I forgot about
the Autocrypt attributes ... for pgp we should also allow the set and get the extra attribute(s)
from Autocrypt like 'prefer':'encrypt' in the JSON, enhancing the JSON for PGP with an (optional)
'autocrypt' attribute, containing an object with again address-attribute(s) and the autocrypt
attributes of that address (only main address, not aliases)"* - this is now item 2's actual DONE
implementation, see below (superseding its own original "comment prefix" idea).

### 2. Autocrypt attributes (`prefer-encrypt` etc.) - DONE (2026-09-09), as a reserved JSON key, not a comment prefix

Superseded the originally-proposed "prepend a comment line to the key text" shape (`#
prefer-encrypt=mutual\n-----BEGIN...`) with ralf's own refined design once item 1's alias-pointer
format actually existed to hang it off of: a reserved top-level `"autocrypt"` key in the SAME
per-address JSON object item 1 already uses (`set_keys()`'s own docblock has the full up-to-date
format) - `{"business@x.com": "-----BEGIN...", "home@x.com": "business@x.com", "autocrypt":
{"business@x.com": {"prefer-encrypt": "mutual"}}}`. PGP only - Autocrypt is an OpenPGP/MIME-
specific mechanism, S/MIME has no equivalent concept (silently ignored if ever passed for S/MIME).

Attributes are keyed ONLY by the "main"/root address that directly holds the armored key text -
**never by an address that's merely an alias to another's key** (ralf's own explicit constraint,
"only main address, not aliases") - since `prefer-encrypt` etc. describe the key/identity itself,
not the alias pointer; asking for an alias address's attributes transparently resolves to its root
address' attributes instead (same alias-chain-following `resolve_root_address()` item 1's
`extract_key_for_address()` already uses, refactored out as a shared helper). Attributes attach
only to an address that ALREADY has a resolvable key - never created standalone (an orphan
`"autocrypt"` entry with no matching key would be meaningless), and merge (not replace) into
whatever attributes that address already had, so eg. learning `prefer-encrypt` later doesn't wipe
out some other attribute learned earlier.

New API on `addressbook_bo`: `get_autocrypt_attributes(array $contact, ?string $address) : array`
(read) and `set_autocrypt_attributes($recipient, array $attributes) : bool` (write - same
contact-search/ACL/`save()` path as `set_keys()`, factored into shared `key_storage_path()`/
`write_key_file()` private helpers both now use). Neither is wired into any caller yet - this is
storage-layer plumbing ahead of Autocrypt steps 3/4 below (sending/receiving the actual
`Autocrypt:` header's `prefer-encrypt=` parameter), which are what will actually call these.

7 new tests in `AddressbookBoMultiKeyStorageTest.php` (25 total): attributes attach when a key
already exists / merge in the same call as the key itself / are silently dropped when no key
exists for that address at all / resolve through an alias to the root address (not stored under
the alias) / merge rather than replace across repeated calls / are ignored entirely for S/MIME /
and don't inflate `extract_key_for_address()`'s "is this the contact's ONLY key" count used by
item 1's S/MIME single-key cross-check (the reserved `"autocrypt"` key is metadata, not a second
address entry).

### 3. Sending: `Autocrypt:` (own key) and `Autocrypt-Gossip:` (recipients' keys)

- **`Autocrypt:` - DONE (2026-09-09)**: new `MailJmap.buildAutocryptHeader(identity)` (`mail/js/
  jmap.ts`) looks up the sending identity's own address via the existing `ajax_get_pgp_keys()`
  addressbook endpoint (confirmed: no separate "account keys" lookup path needed, the same
  address-keyed endpoint used everywhere else works fine for the sending identity's own email too),
  converts the armored result via `armoredKeyToAutocryptKeydata()` (Phase 5 item 1, already built +
  tested), and assembles `addr=<email>; keydata=<base64>` (`keydata=` last, per spec). Wired into
  `sendNewEmail()` only (an actual submit), NOT `saveDraft()`'s autosave-only path - a still-unsent
  draft never reaches SMTP, so nothing is "outgoing" yet, and re-running an addressbook lookup +
  openpgp.js minimization on every autosave tick would be wasted work; `createDraftEmail()`/
  `draftEmailProperties()` both gained an optional `autocryptHeader` param threaded through for
  this, defaulting to omitted (no header) when not passed. Also applies to Mailvelope-encrypted
  sends (`pgpArmored` given) - correctly so, a PGP-encrypted message's own sender should still
  advertise their key for the recipient to reply-encrypt back; does NOT apply to S/MIME `TYPE_SIGN`'s
  "whole" pre-built-message path (Autocrypt is PGP-only, irrelevant there anyway). `prefer-encrypt=
  mutual` deliberately NOT added yet - correctly still gated on the "mutual" preference (item 6
  below), which doesn't exist yet; returns `null` (header omitted, not sent empty) whenever there's
  no key to advertise or the stored key has no Autocrypt-compatible encryption subkey, and swallows
  (logs, doesn't throw) any addressbook-lookup failure - a send must never fail just because this
  informational header couldn't be built. New tests: `MailJmapBuildAutocryptHeader.test.ts` (the
  lookup/assembly logic, addressbook mocked) and a new describe block in `MailJmapDraftEmailProperties
  Headers.test.ts` (the pure passthrough into the final Email/set properties).
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

**Parsing half - DONE (2026-09-09)**, wiring half still pending. New `MailJmap.
parseAutocryptHeader(headerValue, fromAddress)`/`parseAutocryptHeaders(headerValues, fromAddress)`
(`mail/js/jmap.ts`) implement Level 1's own validation rules exactly, confirmed against the spec
text itself (fetched live while writing this, not from memory): `addr`/`keydata` both required;
**"If this address [`addr`] differs from the one in the `From` header, the entire `Autocrypt`
header MUST be treated as invalid"**; attribute names starting with `_` are non-critical and
silently ignored if unrecognized, but any OTHER unrecognized attribute name invalidates the whole
header (**"MUST treat the entire `Autocrypt` header as invalid if it encounters a 'critical'
attribute that it doesn't support"**); `prefer-encrypt` is `'mutual'` only for an explicit
`prefer-encrypt=mutual`, else `'nopreference'` (**"any other value (or ... does not see the
attribute at all)"**); the spec's own 10 KiB cap is enforced defensively on read too (a malicious/
corrupted header could exceed what a spec-compliant sender ever would); and
`parseAutocryptHeaders()` applies the exact multiple-header rule (**"If there is more than one
valid header, this SHOULD be treated as an error, and all `Autocrypt` headers discarded as
invalid"**) across a message's full set of raw `Autocrypt:` header values. 19 tests in
`PgpAutocryptHeaderParsing.test.ts`, each quoting the spec line it verifies.

**Deliberately NOT yet built** (needs the not-yet-built consent dialog from item 5 first - the two
are meant to land together per this doc's own phasing, so half-wiring this now would mean silently
auto-trusting a header with no user awareness at all): actually calling this parser against a real
incoming/replied-to message's raw headers (`mailvelopeCompose()`, `mail/js/app.ts`, already reads
the quoted PGP-armored body out of the source message for the "reply to encrypted message" case -
the natural place to also read its `Autocrypt:`/`Autocrypt-Gossip:` headers), the
`multipart/report`/multiple-`From` skip-entirely guard (needs real message metadata this pure
parser deliberately doesn't take), `Autocrypt-Gossip:` parsing specifically (same header shape and
`parseAutocryptHeader()` should work for it directly, but it lives INSIDE the decrypted MIME
payload, not as a bare outer header - needs the decrypted-body access pattern, not built), and
feeding a successful parse's `keydata` through `autocryptKeydataToArmoredKey()` (item 1, already
built) into the actual consent-dialog-gated storage call.

### 5. Consent dialog + new preference - DONE (2026-09-09), triggered by the INLINE-key case, not Autocrypt headers yet

Ralf: *"for item 5 let's check the s/mime dialog for that and maybe while on it also add 'Never
ask', a preference and the automatic adding for senders already in AB (item 7). In general we want
the s/mime pgp to look similar, so users dont have to learn two different things."* Implemented
both items 5 and 7 together, for BOTH PGP and S/MIME, sharing one dialog shape and one preference -
see item 7's own entry just below for the shared design write-up (the two items turned out to be
one and the same mechanism, applied symmetrically to both signature types, so there's no point
describing them twice).

**Scope note**: item 5 was originally written assuming the trigger would be a newly-learned
`Autocrypt:`-header key (items 3/4) - those aren't wired to any live message-reading caller yet
(item 4's parsing logic is DONE, but not yet called against a real message's headers, see that
item's own entry). What ships now instead uses the OTHER, already-fully-working "found a key not in
the addressbook" signal: `PgpSignatureResult.keySource === 'inline'` (a message's own
`application/pgp-keys` attachment, Phase 4's existing infrastructure) - the direct PGP analogue of
S/MIME's own `addtocontact` (a cert embedded in the signed message itself, not from the
addressbook), and the correct symmetric trigger ralf's own "look similar" framing calls for. Once
Autocrypt-header learning is wired up later, it can feed the SAME dialog/preference/auto-add
mechanism (`pgpAutoOfferAddToContact()`) - this was designed generically for exactly that, not
`keySource:'inline'`-specific.

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

### 7. Auto-add a verified sender's cert/key to an already-known contact, no dialog - DONE (2026-09-09), both S/MIME and PGP

Originally scoped S/MIME-only ("auto-add to an ALREADY-known contact, no dialog... unlike an
unknown/unverified one, which keeps today's click-to-add dialog unchanged") - shipped alongside
item 5 above as one shared, symmetric mechanism for BOTH S/MIME and PGP instead, per ralf's own
"look similar" framing, and now the unknown-contact case ALSO auto-prompts (via item 5's consent
dialog) rather than staying click-only, since "Never ask" only makes sense as an opt-out for
something that pops up unprompted.

**Real gap found while wiring this**: `data.addtocontact` (`Api\Mail\Smime::resolveMessage()`) was
ALREADY being computed server-side this whole time, but NOTHING client-side ever read it for the
JMAP-native flow - the only code that ever called the actionable (`_display` falsy) variant of
`smimeCertAddToContact()` was the OLD classic-mail push path
(`Ui\MessageDisplayHandler.php`'s `$push->call('app.mail.smimeCertAddToContact', $smime)`, dead for
a JMAP-driven display). So this "verified but not-yet-in-addressbook cert" case had silently never
prompted at all for any JMAP-native message, S/MIME's own click-triggered info dialog
(`_display=true`, both existing `smime_signature`/`smime_encryption` icon onclick handlers) being
the only reachable path. Fixed as part of this work, not a pre-existing regression from earlier
this session's JMAP migration - `addtocontact` was simply never wired up to begin with.

**Shared shape** (`mail/js/app.ts`):
- `setSmimeFlags()`/`setPgpSignatureFlags()` now call `smimeAutoOfferAddToContact(data)`/
  `pgpAutoOfferAddToContact(data)` whenever there's something to offer - S/MIME: `data.verify &&
  data.addtocontact`; PGP: `_data.verified && _data.armoredKey` (see `PgpSignatureResult.
  armoredKey`'s own docblock, `mail/js/jmap.ts` - populated ONLY for `keySource==='inline'` AND a
  genuinely verified, address-matching signature, alongside new display-only `keyFingerprint`/
  `keyUid` fields sourced from the SAME `openpgp.readKey()` call `verifyPgpSignatureUncached()`
  already makes, no second parse needed).
- Both `*AutoOfferAddToContact()` methods FIRST attempt a silent update against a matching existing
  contact (`ajax_smimeAddCertToContact` / new `ajax_pgpAddKeyToContact`, `mail/src/Ui.php` - the
  latter a thin wrapper around a new plain `addressbook_bo::set_pgp_keys()`, deliberately NOT the
  existing `ajax_set_pgp_keys()` used elsewhere, which also uploads to a public keyserver and has a
  different return shape, side effects only appropriate for that method's own "user pastes their
  own key" flow). A truthy result means an existing contact was updated - item 7's own "no dialog"
  case, done, just a quiet `egw.message()` toast (matching the existing manual "Add this
  certificate" button's own feedback). A falsy result (no matching contact at all) falls through to
  the (now auto-triggered) consent dialog from item 5, UNLESS the shared `smime_pgp_add_contact`
  preference (`mail_hooks::settings()`, `mail/inc/class.mail_hooks.inc.php` - `select`, `''`
  default/"ask" vs `'never'`, live-verified rendering correctly with both options in the real
  Preferences UI, help text and label included) is set to `'never'`.
- `smimeCertAddToContact()`/new sibling `pgpKeyAddToContact()` (mirrored shape/naming) gained a
  3rd `_autoTriggered` param that adds a **"Never ask again"** button (persists the preference via
  `egw.set_preference()`) - only for the auto-triggered path, never the user-initiated
  click-on-the-icon path (both icons already had, or now also have for PGP, an `onclick` opening
  the SAME dialog `_display=true`, info-only - nothing to "opt out of" when the user asked to see
  it themselves).
- New `mail/templates/default/pgpKeyAddToContact.xet`, deliberately mirroring
  `smimeCertAddToContact.xet`'s exact grid/row shape (message/message2 header, "Signed by"/"Email
  address" rows in the same order) - differs only where PGP genuinely has no X.509 equivalent (a
  fingerprint row instead of issuer/country, since a PGP key isn't CA-issued).
- Both flows write into the SAME shared addressbook `pubkey` field/preset
  (`addressbook_bo::save()`'s own single-field dispatch-by-regex, not a separate PGP-specific
  field) when falling back to "create a new contact" - `egw.open('','addressbook','add',extra)`
  pre-filled with email/name/key exactly as S/MIME's existing fallback already does.

Live-verified (2026-09-09): the new `smime_pgp_add_contact` preference renders correctly in the
real Mail Preferences UI ("Einstellungen der Konfiguration" tab, right after "Fensterlayout") with
both `ask`/`never ask` options and its help text tooltip - confirms the `mail_hooks::settings()`
wiring end-to-end. **Not yet live-clicked-through**: the actual auto-popup dialog on a real signed
message (needs a live signed message from a not-yet-known sender to trigger against; all the
underlying logic is unit-tested and the full mail JS suite (466/466) + build stay clean, but the
dialog-opening methods themselves are, like `setSmimeFlags()`/`setPgpSignatureFlags()` before them,
DOM/widget-heavy and not unit-tested - see Phase H's own precedent for that decision).

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
2. **DONE (2026-09-09).** Multi-key-per-address storage fix (item 1) - `addressbook_bo::set_keys()`/
   `get_keys()`/`get_key()` (`addressbook/inc/class.addressbook_bo.inc.php`, shared by PGP and
   S/MIME via the `$pgp` flag) now store a JSON object keyed by lowercased address instead of one
   bare key per contact record, via two new pure-function helpers (`merge_keys_json()`,
   `extract_key_for_address()`) - a `"*"` entry is the legacy-file/account-id-lookup fallback, so
   nothing already stored breaks and an address never explicitly re-stored keeps resolving to it.
   Also fixed two smaller bugs found while building this: `set_keys()`'s own search/key-selection
   never checked `contact_email_home` at all (a key explicitly stored for someone's home-only
   address silently found no contact to attach it to), and a brand-new contact with no `.files/`
   VFS directory yet couldn't have its very first key written at all (`file_put_contents()` can't
   create the missing parent directory itself). Unit-tested via reflection on the two pure
   functions (`addressbook/tests/AddressbookBoMultiKeyStorageTest.php`, 8 tests) rather than a full
   integration test through `search()`/`save()`/ACL/VFS - found live while building this that the
   shared docker PHPUnit environment's own account search hangs indefinitely even for the
   ORIGINAL, unmodified `set_keys()` (confirmed by testing the pre-fix code directly) - a
   pre-existing environment limitation, not something this fix caused or needs to chase down.
   **Known follow-up, not fixed here**: the addressbook contact-edit UI's own PGP/S-MIME
   `vfs-upload` widget (`addressbook_ui::pubkey_uploaded()`) still uploads a single file with no
   per-address distinction at all - it degrades gracefully (the uploaded content becomes the `"*"`
   fallback, same as a legacy file), but doesn't yet let a user pick "this key is for my home
   address" through that specific UI path.
3. Sending `Autocrypt:` (own key) - DONE (2026-09-09, see its own entry) - was indeed the simplest,
   most self-contained piece, no consent-dialog UI needed (never touches another contact's stored
   data). `Autocrypt-Gossip:` (the other half of item 3) is still not started.
4. Item 4's pure `Autocrypt:`-header-parsing logic, item 5's consent dialog + preference, and item
   7's auto-add-for-known-contacts are ALL DONE (2026-09-09, see their own entries) - items 5/7
   shipped together as one shared, symmetric S/MIME+PGP mechanism, triggered by the already-working
   inline-key case rather than Autocrypt headers (item 4's parser has no live caller yet). Still not
   started: `Autocrypt-Gossip:` sending (item 3's other half), and actually wiring item 4's header
   parser to a real message's `Autocrypt:` header as an ADDITIONAL trigger source for item 5's now-
   already-shipped dialog.
5. `prefer-encrypt` **storage** (item 2) is DONE (2026-09-09, see its own entry) - what's left is
   mutual auto-encrypt preference (item 6) and actually wiring items 3/4's sending/receiving code
   to call `set_autocrypt_attributes()`/`get_autocrypt_attributes()`.
6. Item 6 (mutual auto-encrypt preference) - the only piece of the original items 5/6/7 grouping
   not yet done.

## Explicitly out of scope for this project

- PGP **encryption/decryption** - stays exactly as-is via Mailvelope; this project is
  signature-verification-only. (Superseded for the *signing* half by planned-follow-up item 3
  above - encryption itself is still out of scope, unchanged.)
- Verifying `multipart/encrypted` PGP/MIME's own inner signature (if the encrypted payload itself
  contains a signed-then-encrypted structure) - Mailvelope already decrypts that client-side and
  reports its own signature status; not this project's concern.
- Any change to how EGroupware *sends* signed/encrypted PGP mail. (Superseded by planned-follow-up
  items 2 and 3 above.)
