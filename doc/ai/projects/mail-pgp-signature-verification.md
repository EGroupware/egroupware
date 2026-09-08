# Mail: verify PGP/MIME signatures natively (no Mailvelope dependency)

## Status: Phase 2 core engine done + live-verified (2026-09-08) - UI wiring not started

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

**Not yet done**: no UI wiring at all yet (no trigger call from `MailApp.loadMessageBody()`, no
`setPgpSignatureFlags()`, no icon assets, no `display.xet` changes) - this doc's own original
"Suggested phasing" Step 3. `verifyPgpSignature()` itself is fully callable and correct today, just
not yet connected to anything a user would see.

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

## Explicitly out of scope for this project

- PGP **encryption/decryption** - stays exactly as-is via Mailvelope; this project is
  signature-verification-only.
- Verifying `multipart/encrypted` PGP/MIME's own inner signature (if the encrypted payload itself
  contains a signed-then-encrypted structure) - Mailvelope already decrypts that client-side and
  reports its own signature status; not this project's concern.
- Any change to how EGroupware *sends* signed/encrypted PGP mail.
