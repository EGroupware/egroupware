import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";

/**
 * MailJmap.extractSignatureArmor() (private static, accessed via `as any` like the reflection
 * pattern used for AttachmentJmap::jmapAttachmentsToLegacy()'s own PHPUnit tests) - takes the raw
 * bytes of a PGP/MIME `application/pgp-signature` sub-part (headers + blank line + body) and
 * returns the ASCII-armored signature text ready for openpgp.readSignature().
 *
 * Regression coverage for a real bug (found live 2026-09-09, on a real third-party sender's
 * message - structurally identical to the Phase 1 spike's "older.eml" fixture): most senders
 * (Thunderbird/Enigmail) leave this part as plain 7bit ASCII armor, but this one had
 * `Content-Transfer-Encoding: base64` on the signature part itself - the naive "everything after
 * the blank line is the armor" original implementation handed openpgp.readSignature()
 * still-base64-encoded bytes, which threw "Misformed armored text" - silently caught by
 * verifyPgpSignature()'s top-level try/catch, so the message showed no PGP icon at all instead of
 * a "verification failed"/"unknown key" one.
 */
function extract(headers : string, body : string) : string
{
	const raw = new TextEncoder().encode(headers + "\r\n\r\n" + body);
	return (MailJmap as any).extractSignatureArmor(raw);
}

describe("MailJmap.extractSignatureArmor()", () =>
{
	it("returns the body as-is when there is no Content-Transfer-Encoding header", () =>
	{
		const armor = "-----BEGIN PGP SIGNATURE-----\r\n\r\nabcd\r\n-----END PGP SIGNATURE-----\r\n";
		const result = extract(
			"Content-Type: application/pgp-signature; name=\"OpenPGP_signature.asc\"\r\n" +
			"Content-Disposition: attachment; filename=\"OpenPGP_signature.asc\"",
			armor);
		assert.equal(result, armor);
	});

	it("returns the body as-is for Content-Transfer-Encoding: 7bit", () =>
	{
		const armor = "-----BEGIN PGP SIGNATURE-----\r\n\r\nabcd\r\n-----END PGP SIGNATURE-----\r\n";
		const result = extract(
			"Content-Type: application/pgp-signature\r\nContent-Transfer-Encoding: 7bit", armor);
		assert.equal(result, armor);
	});

	it("base64-decodes the body for Content-Transfer-Encoding: base64", () =>
	{
		const armor = "-----BEGIN PGP SIGNATURE-----\r\n\r\nabcd\r\n-----END PGP SIGNATURE-----\r\n";
		const encoded = btoa(armor);
		// realistic base64 bodies are wrapped at ~76 chars with their own CRLFs - must be stripped
		const wrapped = (encoded.match(/.{1,20}/g) || []).join("\r\n");
		const result = extract(
			"Content-Type: application/pgp-signature\r\nContent-Transfer-Encoding: base64", wrapped);
		assert.equal(result, armor);
	});

	it("is case-insensitive matching the Content-Transfer-Encoding value", () =>
	{
		const armor = "-----BEGIN PGP SIGNATURE-----\r\n\r\nabcd\r\n-----END PGP SIGNATURE-----\r\n";
		const encoded = btoa(armor);
		const result = extract(
			"Content-Type: application/pgp-signature\r\nContent-Transfer-Encoding: Base64", encoded);
		assert.equal(result, armor);
	});
});
