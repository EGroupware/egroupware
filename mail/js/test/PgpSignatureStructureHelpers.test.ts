import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Isolated unit coverage for the three small helpers verifyPgpSignature() (mail/js/jmap.ts) builds
 * on - previously only exercised indirectly via PgpSignatureVerification.test.ts's end-to-end
 * fixture (doc/ai/projects/mail-pgp-signature-verification.md's own "not yet covered" note). All
 * three are pure/synchronous (no JMAP, no network), so no mocking is needed beyond a bare
 * MailJmap instance for the one instance method.
 */
function createFakeApp() : MailApp
{
	return {
		egw: {
			user: (_key : string) => 1,
			lang: (label : string) => label,
			preference: (_key : string, _app? : string) => null,
			config: (_name : string, _app? : string) => null,
			request: async() => ({}),
			message: (_msg : string, _type? : string) => {},
		},
	} as unknown as MailApp;
}

describe("MailJmap.findPgpSignaturePart()", () =>
{
	const jmap = new MailJmap(createFakeApp());
	const find = (part : any) => (jmap as any).findPgpSignaturePart(part);

	it("finds a top-level multipart/signed[content, pgp-signature]", () =>
	{
		const signedPart = {type: "text/plain", partId: "1"};
		const sigPart = {type: "application/pgp-signature", partId: "2"};
		const body = {type: "multipart/signed", subParts: [signedPart, sigPart]};

		assert.deepEqual(find(body), {signedPart, sigPart});
	});

	it("recurses to find it nested inside an outer multipart/mixed (a real attachment alongside "
		+ "a signed body)", () =>
	{
		const signedPart = {type: "text/plain", partId: "2"};
		const sigPart = {type: "application/pgp-signature", partId: "3"};
		const body = {
			type: "multipart/mixed",
			subParts: [
				{type: "multipart/signed", subParts: [signedPart, sigPart]},
				{type: "application/pdf", partId: "4"},
			],
		};

		assert.deepEqual(find(body), {signedPart, sigPart});
	});

	it("is case-insensitive matching both the multipart/signed and pgp-signature types", () =>
	{
		const signedPart = {type: "TEXT/Plain"};
		const sigPart = {type: "Application/PGP-Signature"};
		const body = {type: "Multipart/Signed", subParts: [signedPart, sigPart]};

		assert.deepEqual(find(body), {signedPart, sigPart});
	});

	it("returns null for a plain, non-multipart body", () =>
	{
		assert.isNull(find({type: "text/plain", partId: "1"}));
	});

	it("returns null for null/undefined input", () =>
	{
		assert.isNull(find(null));
		assert.isNull(find(undefined));
	});

	it("does NOT match an S/MIME-signed multipart/signed (application/pkcs7-signature, not "
		+ "application/pgp-signature) - the two are handled by entirely separate code paths, this "
		+ "one must never claim an S/MIME message as PGP-signed", () =>
	{
		const body = {
			type: "multipart/signed",
			subParts: [{type: "text/plain"}, {type: "application/pkcs7-signature"}],
		};

		assert.isNull(find(body));
	});

	it("does NOT match multipart/signed with only one sub-part (malformed - the RFC-mandated "
		+ "detached signature is missing)", () =>
	{
		const body = {type: "multipart/signed", subParts: [{type: "text/plain"}]};

		assert.isNull(find(body));
	});
});

describe("MailJmap.extractMultipartSignedBoundary()", () =>
{
	const extract = (text : string) => (MailJmap as any).extractMultipartSignedBoundary(new TextEncoder().encode(text));

	it("extracts a quoted boundary", () =>
	{
		const boundary = extract(
			"From: a@example.invalid\r\n" +
			"Content-Type: multipart/signed; micalg=\"pgp-sha256\"; protocol=\"application/pgp-signature\"; boundary=\"BOUND123\"\r\n" +
			"\r\nbody");

		assert.equal(boundary, "BOUND123");
	});

	it("extracts an unquoted boundary", () =>
	{
		const boundary = extract("Content-Type: multipart/signed; boundary=BOUND456\r\n\r\nbody");

		assert.equal(boundary, "BOUND456");
	});

	it("extracts a boundary from a folded (continuation-line) header, same as real long MUA "
		+ "headers commonly wrap", () =>
	{
		const boundary = extract(
			"Content-Type: multipart/signed;\r\n" +
			" micalg=\"pgp-sha256\";\r\n" +
			" protocol=\"application/pgp-signature\";\r\n" +
			" boundary=\"FOLDED-BOUNDARY\"\r\n" +
			"\r\nbody");

		assert.equal(boundary, "FOLDED-BOUNDARY");
	});

	it("is case-insensitive matching both the header name and multipart/signed", () =>
	{
		const boundary = extract("content-type: MULTIPART/SIGNED; boundary=\"CaseTest\"\r\n\r\nbody");

		assert.equal(boundary, "CaseTest");
	});

	it("returns null when there is no multipart/signed Content-Type header at all", () =>
	{
		assert.isNull(extract("Content-Type: text/plain\r\n\r\nbody"));
	});

	it("returns null for an S/MIME multipart/signed (a different protocol, no PGP boundary to "
		+ "extract for) - not actually reachable in practice (verifyPgpSignature() only gets this "
		+ "far after findPgpSignaturePart() already confirmed a PGP signature), but the boundary "
		+ "IS still found even for a non-PGP protocol since this helper doesn't check `protocol=`",
		() =>
	{
		const boundary = extract(
			"Content-Type: multipart/signed; protocol=\"application/pkcs7-signature\"; boundary=\"X\"\r\n\r\nbody");

		assert.equal(boundary, "X");
	});
});

describe("MailJmap.sliceMultipartSigned()", () =>
{
	const BOUNDARY = "B123";

	function raw(signedWireBytes : string, sigWireBytes : string, boundary : string) : Uint8Array
	{
		const text =
			`--${boundary}\r\n` + signedWireBytes +
			`--${boundary}\r\n` + sigWireBytes +
			`--${boundary}--\r\n`;
		return new TextEncoder().encode(text);
	}

	function slice(signedWireBytes : string, sigWireBytes : string, boundary = BOUNDARY)
	{
		return (MailJmap as any).sliceMultipartSigned(raw(signedWireBytes, sigWireBytes, boundary), boundary);
	}

	it("slices out the signed content and signature part, each starting right after their own "
		+ "boundary delimiter line", () =>
	{
		const result = slice(
			"Content-Type: text/plain\r\n\r\nhello\r\n",
			"Content-Type: application/pgp-signature\r\n\r\narmor\r\n");

		assert.equal(new TextDecoder().decode(result.signedBytes), "Content-Type: text/plain\r\n\r\nhello");
		assert.equal(new TextDecoder().decode(result.sigPartBytes), "Content-Type: application/pgp-signature\r\n\r\narmor");
	});

	it("trims exactly the CRLF immediately before each boundary delimiter (RFC 1847 canonical "
		+ "form - that CRLF belongs to the delimiter line, not the signed content) but keeps any "
		+ "OTHER trailing blank line untouched", () =>
	{
		const result = slice(
			"Content-Type: text/plain\r\n\r\nline one\r\n\r\n",
			"Content-Type: application/pgp-signature\r\n\r\narmor\r\n");

		// only the LAST \r\n (the one directly abutting the boundary) is trimmed - the blank line
		// in the middle of the body must survive
		assert.equal(new TextDecoder().decode(result.signedBytes), "Content-Type: text/plain\r\n\r\nline one\r\n");
	});

	it("returns null when the boundary can't be found 3 times (malformed/truncated message)", () =>
	{
		const text = new TextEncoder().encode(`--${BOUNDARY}\r\nonly one delimiter\r\n`);

		assert.isNull((MailJmap as any).sliceMultipartSigned(text, BOUNDARY));
	});

	it("treats the boundary as a plain literal string, not a regex - a boundary containing "
		+ "regex-special characters (+, ., *) must still be found correctly", () =>
	{
		const boundary = "a+b.c*d";
		const result = slice(
			"Content-Type: text/plain\r\n\r\nhello\r\n",
			"Content-Type: application/pgp-signature\r\n\r\narmor\r\n",
			boundary);

		assert.equal(new TextDecoder().decode(result.signedBytes), "Content-Type: text/plain\r\n\r\nhello");
	});
});
