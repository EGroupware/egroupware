import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * End-to-end coverage for MailJmap.verifyPgpSignature() (mail/js/jmap.ts) - Phase 4 of
 * doc/ai/projects/mail-pgp-signature-verification.md's own "Suggested phasing", using a real
 * openpgp.js-generated key/signature fixture (generated once via the full `openpgp` npm package,
 * `node -e "..."`, and hardcoded below - the lightweight build this production code itself uses
 * has no key-generation/signing support, only verify, so the fixture can't be generated inline in
 * the test) rather than mocking openpgp.js itself, so the real `import('openpgp/lightweight')`
 * dynamic import and the real ArmorSignature/ArmorKey parsing are exercised exactly as production
 * code runs them - only the JMAP transport (client.requestMany()/downloadBlob()) and the
 * addressbook lookup (egw.request()) are faked, following MailJmap.test.ts's own primeToken()
 * pattern.
 *
 * The fixture's WIRE_SUBPART is the exact bytes of a `text/plain` MIME sub-part (its own
 * Content-Type header + blank line + body, CRLF throughout) as it appears between the
 * `multipart/signed`'s two boundary delimiters; SIGNATURE_ARMOR is a detached signature over those
 * same bytes MINUS their own trailing CRLF (RFC 1847 canonical form - the CRLF immediately before
 * a boundary delimiter belongs to the delimiter, not the signed content - matching
 * MailJmap.sliceMultipartSigned()'s own trimTrailingCrlf()).
 */
const BOUNDARY = "BOUNDARY123456789";

const PUBLIC_KEY = `-----BEGIN PGP PUBLIC KEY BLOCK-----

xjMEaqEkZRYJKwYBBAHaRw8BAQdAZVeGGe7+anYuRrptgAs7XPZ9lm0zHUmq
ESUJvp9llSDNKFBIUFVuaXQgRml4dHVyZSA8c2VuZGVyQGV4YW1wbGUuaW52
YWxpZD7CwBMEExYKAIUFgmqhJGUDCwkHCRCWePnTQqXcZUUUAAAAAAAcACBz
YWx0QG5vdGF0aW9ucy5vcGVucGdwanMub3Jn+VLfzEnxnXfR60zulZRuTkf2
ZBZr698BP4cMjylvbfsFFQoIDgwEFgACAQIZAQKbAwIeARYhBKuF+LiDOcAQ
kn++gJZ4+dNCpdxlAADq6AEAjikU0+v7/Xm81s1GnixFjA13i37i6QZIoNDy
ji1NwFYA/3ANns551V1zCsMdOrrcEwGEitn9qk+Cq802G7tbplILzjgEaqEk
ZRIKKwYBBAGXVQEFAQEHQERzA/7cE6pk+0CNpJa2pLOmRPzYkh20U3BVtQf2
COJXAwEIB8K+BBgWCgBwBYJqoSRlCRCWePnTQqXcZUUUAAAAAAAcACBzYWx0
QG5vdGF0aW9ucy5vcGVucGdwanMub3JnEaw7aHBC/Sfo4TxMyxSGUoUj/ahp
qktDm1I2OYAOIZMCmwwWIQSrhfi4gznAEJJ/voCWePnTQqXcZQAAnUEA/1N9
ge8k82rFYqqeTFQjIzFvy5wmCIzbfQM4ypRHb+CWAQDyTf2bgqGIdVZliywl
sncoEo02nx4ZibN+s2wfKhl2AQ==
=e7jS
-----END PGP PUBLIC KEY BLOCK-----
`;

const SIGNATURE_ARMOR = `-----BEGIN PGP SIGNATURE-----

wrsEABYKAG0FgmqhJGUJEJZ4+dNCpdxlRRQAAAAAABwAIHNhbHRAbm90YXRp
b25zLm9wZW5wZ3Bqcy5vcmewXnZJZIDvBy98UoEYR0m02XBcG0sNmbTgcDZe
25QbgBYhBKuF+LiDOcAQkn++gJZ4+dNCpdxlAAA/cQD+JEoNMWW0AQDsFuIA
SnUX0Q/nm6fCP4Uau0nNu3qkexYA/05adWtLR97hqixfkH+L5d7Dp8xh3qRG
i581PeORQ7EL
=zCkv
-----END PGP SIGNATURE-----
`;

const WIRE_SUBPART = "Content-Type: text/plain; charset=utf-8\r\n\r\nTest PGP signed body for unit tests.\r\n";
const TAMPERED_WIRE_SUBPART = "Content-Type: text/plain; charset=utf-8\r\n\r\nTampered body, signature must NOT match.\r\n";

function buildRawEml(wireSubPart : string) : Uint8Array
{
	const text =
		"From: PHPUnit Fixture <sender@example.invalid>\r\n" +
		"To: recipient@example.invalid\r\n" +
		"Subject: Test\r\n" +
		"MIME-Version: 1.0\r\n" +
		`Content-Type: multipart/signed; micalg="pgp-sha256"; protocol="application/pgp-signature"; boundary="${BOUNDARY}"\r\n` +
		"\r\n" +
		"This is an OpenPGP/MIME signed message.\r\n" +
		`--${BOUNDARY}\r\n` +
		wireSubPart +
		`--${BOUNDARY}\r\n` +
		"Content-Type: application/pgp-signature; name=\"signature.asc\"\r\n" +
		"Content-Description: OpenPGP digital signature\r\n" +
		"Content-Disposition: attachment; filename=\"signature.asc\"\r\n" +
		"\r\n" +
		SIGNATURE_ARMOR +
		`--${BOUNDARY}--\r\n`;
	return new TextEncoder().encode(text);
}

function fakeResponse(bytes : Uint8Array) : {arrayBuffer : () => Promise<ArrayBuffer>}
{
	return {arrayBuffer: async() => bytes.buffer.slice(bytes.byteOffset, bytes.byteOffset + bytes.byteLength)};
}

const BODY_STRUCTURE = {
	type: "multipart/signed",
	subParts: [
		{type: "text/plain", partId: "1"},
		{type: "application/pgp-signature", partId: "2"},
	],
};

function primeFixture(jmap : MailJmap, options : {
	rawBytes : Uint8Array,
	attachments? : any[],
	downloadKeyBytes? : Uint8Array,
	fromEmail? : string,
}) : void
{
	(jmap as any).tokens["1"] = {
		sessionUrl: "https://example.com", accountId: "acc1", access_token: "tok",
		expires_at: Date.now() + 100000, isLocal: false, customLabels: {},
	};
	(jmap as any).clients["1"] = {
		requestMany: async(_buildFn : (t : any) => any) => [{
			got: {
				list: [{
					bodyStructure: BODY_STRUCTURE,
					blobId: "blob-raw-message",
					from: [{email: options.fromEmail || "sender@example.invalid"}],
					attachments: options.attachments || [],
				}],
			},
		}],
		downloadBlob: async(args : any) =>
		{
			if (args.blobId === "blob-raw-message")
			{
				return fakeResponse(options.rawBytes);
			}
			if (args.blobId === "blob-inline-key" && options.downloadKeyBytes)
			{
				return {text: async() => new TextDecoder().decode(options.downloadKeyBytes)};
			}
			throw new Error("PgpSignatureVerification.test.ts: unexpected downloadBlob() blobId " + args.blobId);
		},
	};
}

function createFakeApp(egwRequest : (method : string, params : any[]) => Promise<any>) : MailApp
{
	return {
		egw: {
			user: (_key : string) => 1,
			lang: (label : string, ..._args : any[]) => label,
			preference: (_key : string, _app? : string) => null,
			config: (_name : string, _app? : string) => null,
			request: egwRequest,
			message: (_msg : string, _type? : string) => {},
		},
	} as unknown as MailApp;
}

describe("MailJmap.verifyPgpSignature() - end-to-end with a real openpgp.js fixture", () =>
{
	it("verifies a correctly-signed message against a key from the addressbook", async() =>
	{
		const jmap = new MailJmap(createFakeApp(
			async(_method, _params) => ({"sender@example.invalid": PUBLIC_KEY})));
		primeFixture(jmap, {rawBytes: buildRawEml(WIRE_SUBPART)});

		const result = await jmap.verifyPgpSignature("mail::0::1::mbx1::email1");

		assert.deepEqual(result, {
			signed: true, verified: true, keySource: "addressbook", email: "sender@example.invalid",
		});
	});

	it("falls back to the message's own inline application/pgp-keys attachment when the "
		+ "addressbook has no key for the sender - and, being verified from a NOT-yet-addressbook "
		+ "key, also returns the armoredKey/keyFingerprint/keyUid display fields "
		+ "MailApp.pgpAutoOfferAddToContact() needs to offer adding it", async() =>
	{
		const jmap = new MailJmap(createFakeApp(async(_method, _params) => ({})));
		primeFixture(jmap, {
			rawBytes: buildRawEml(WIRE_SUBPART),
			attachments: [{type: "application/pgp-keys", blobId: "blob-inline-key"}],
			downloadKeyBytes: new TextEncoder().encode(PUBLIC_KEY),
		});

		const result : any = await jmap.verifyPgpSignature("mail::0::1::mbx1::email1");

		assert.equal(result.signed, true);
		assert.equal(result.verified, true);
		assert.equal(result.keySource, "inline");
		assert.equal(result.email, "sender@example.invalid");
		assert.equal(result.armoredKey, PUBLIC_KEY);
		assert.equal(result.keyUid, "PHPUnit Fixture <sender@example.invalid>");
		assert.match(result.keyFingerprint, /^[0-9a-f]{16,64}$/);
	});

	it("does NOT return armoredKey/keyFingerprint/keyUid when the key came from the "
		+ "addressbook (already known, nothing to offer adding)", async() =>
	{
		const jmap = new MailJmap(createFakeApp(
			async(_method, _params) => ({"sender@example.invalid": PUBLIC_KEY})));
		primeFixture(jmap, {rawBytes: buildRawEml(WIRE_SUBPART)});

		const result : any = await jmap.verifyPgpSignature("mail::0::1::mbx1::email1");

		assert.equal(result.keySource, "addressbook");
		assert.isUndefined(result.armoredKey);
		assert.isUndefined(result.keyFingerprint);
		assert.isUndefined(result.keyUid);
	});

	it("reports verified:false (not hidden, not thrown) when the signed content doesn't match "
		+ "the signature - a tampered/altered message", async() =>
	{
		const jmap = new MailJmap(createFakeApp(
			async(_method, _params) => ({"sender@example.invalid": PUBLIC_KEY})));
		primeFixture(jmap, {rawBytes: buildRawEml(TAMPERED_WIRE_SUBPART)});

		const result = await jmap.verifyPgpSignature("mail::0::1::mbx1::email1");

		assert.deepEqual(result, {
			signed: true, verified: false, keySource: "addressbook", email: "sender@example.invalid",
		});
	});

	it("reports keySource:'none' when no key is available anywhere (addressbook empty, no inline "
		+ "pgp-keys attachment)", async() =>
	{
		const jmap = new MailJmap(createFakeApp(async(_method, _params) => ({})));
		primeFixture(jmap, {rawBytes: buildRawEml(WIRE_SUBPART)});

		const result = await jmap.verifyPgpSignature("mail::0::1::mbx1::email1");

		assert.deepEqual(result, {
			signed: true, verified: false, keySource: "none", email: "sender@example.invalid",
		});
	});

	it("reports verified:false and addressMismatch:true when the key cryptographically verifies "
		+ "but none of its own User IDs claim the message's From address - eg. a key misfiled "
		+ "under the wrong addressbook contact, or an inline key attached to spoof a different "
		+ "sender - security-relevant: an attacker who controls their own valid key/signature "
		+ "must never have it rendered as verified just by using someone else's From address",
	async() =>
	{
		// PUBLIC_KEY's own UID is "PHPUnit Fixture <sender@example.invalid>" - looked up here
		// under a DIFFERENT address than the message's From, simulating a misfiled addressbook
		// entry (the signature itself is genuinely valid for this exact message/key pair).
		const jmap = new MailJmap(createFakeApp(
			async(_method, _params) => ({"attacker@example.invalid": PUBLIC_KEY})));
		primeFixture(jmap, {rawBytes: buildRawEml(WIRE_SUBPART), fromEmail: "attacker@example.invalid"});

		const result = await jmap.verifyPgpSignature("mail::0::1::mbx1::email1");

		assert.deepEqual(result, {
			signed: true, verified: false, keySource: "addressbook", email: "attacker@example.invalid",
			addressMismatch: true,
		});
	});

	it("returns null for a message with no multipart/signed structure at all", async() =>
	{
		const jmap = new MailJmap(createFakeApp(async(_method, _params) => ({})));
		(jmap as any).tokens["1"] = {
			sessionUrl: "https://example.com", accountId: "acc1", access_token: "tok",
			expires_at: Date.now() + 100000, isLocal: false, customLabels: {},
		};
		(jmap as any).clients["1"] = {
			requestMany: async() => [{
				got: {
					list: [{
						bodyStructure: {type: "text/plain", partId: "1"},
						blobId: "blob-raw-message",
						from: [{email: "sender@example.invalid"}],
						attachments: [],
					}],
				},
			}],
		};

		const result = await jmap.verifyPgpSignature("mail::0::1::mbx1::email1");

		assert.isNull(result);
	});
});
