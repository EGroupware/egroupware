# Mail: verify PGP/MIME signatures natively (no Mailvelope dependency)

## Status: scoping only (2026-09-08) - nothing implemented yet

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

## The one real open question: byte-exact canonicalization

RFC 3156 §5 verifies a PGP/MIME signature against the **canonical MIME representation** of the
first sub-part - its raw, still-transfer-encoded bytes, with CRLF line endings, exactly as they
were on the wire. This is the single most common source of real-world "valid signature reported as
invalid" bugs in PGP/MIME implementations generally (a relay normalizing line endings, a decoder
handing back LF instead of CRLF, trailing-whitespace differences, etc.) - **not something to assume
away**. Concretely still needs answering before implementation: does `downloadPartText()`/JMAP's
Blob download for `part.subParts[0]`'s `blobId` give back that exact original wire representation,
or an already-decoded/newline-normalized version? This needs a live check against both real
Stalwart and the local IMAP shim (`JmapShim.php`) - they may not even agree with each other - using
a real signed message (ralf's example `.eml`, saved as a fixture) and a known-good independent
verification (e.g. `gpg --verify` on the raw file) as the ground truth to compare against. If the
byte-exact bytes aren't recoverable via the normal Blob-download path, the fallback is fetching the
**entire raw message** (`Email/get`'s `blobId` on the message itself, or an `EMAILID` raw-fetch) and
slicing out the first MIME part by hand from that - more work, but guaranteed byte-correct since
nothing has been through any JMAP-level re-encoding at all.

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
