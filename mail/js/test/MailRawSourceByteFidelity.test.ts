import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Investigation for the bug report (2026-09-09, ralf, relaying a tester report): "our exporting
 * and importing of .eml files is not byte exact and breaks s/mime or pgp signatures" - ralf's own
 * follow-up narrowed this to the SIGNED body specifically, not the (unsigned) envelope headers.
 *
 * A multipart/signed (S/MIME) or PGP/MIME signature is computed over the EXACT bytes of the
 * signed MIME entity (RFC 1847 - canonical CRLF line endings, but otherwise byte-for-byte). Any
 * transport step in between "download this message's raw bytes" and "the .eml file a user later
 * re-opens" that treats those bytes as TEXT rather than an opaque blob is a real corruption risk
 * for anything that isn't already valid UTF-8 - which a signed body is under no obligation to be
 * (a `Content-Transfer-Encoding: 8bit` part can legitimately carry any 8-bit charset, e.g. the
 * still-common windows-1252/ISO-8859-1, particularly from Outlook-originated S/MIME mail).
 *
 * MailJmap.fetchRawSourceByBlobId() (mail/js/jmap.ts) - the JMAP-backend (real Stalwart account)
 * "download this message's raw source" primitive, originally reused by BOTH fetchRawSource()
 * (view-source/view-header) and MailCompose.integrateSentMessage() (mail/js/compose.ts, whose
 * `eml` string used to become a REAL .eml file attachment via mail/src/Compose.php's
 * ajax_integrateSent() - i.e. genuinely "exported" for a user to later open in a real mail
 * client) - calls `response.text()` on the downloaded blob. Per the WHATWG Fetch spec,
 * Body.text() always UTF-8-decodes the bytes, regardless of what charset the message's own
 * Content-Type header declares, and replaces any byte sequence that isn't valid UTF-8 with
 * U+FFFD (the replacement character) - an IRREVERSIBLE, silent corruption for exactly the kind
 * of 8-bit signed body described above.
 *
 * FIXED (2026-09-09): integrateSentMessage() now uses the new, byte-exact
 * fetchRawSourceBytesBase64ByBlobId()/fetchRawSourceBytesBase64() instead (base64-encodes the
 * raw bytes rather than decoding them as text - see that method's own docblock) - covered by the
 * second describe block below, which proves the fix actually round-trips the SAME 8-bit signed
 * body that corrupts the original method. fetchRawSourceByBlobId() itself is intentionally left
 * as-is (still lossy for non-UTF-8 bytes) - it remains fine for its own remaining callers
 * (view-source/view-header display, where readable text - not byte-exact reversibility - is what
 * actually matters), so the first describe block below still documents/pins down that known,
 * accepted behaviour rather than a live bug.
 *
 * These tests use a REAL Response object (this test runs in a real browser via web-test-runner,
 * not jsdom) so the corruption/fix demonstrated here is the browser's actual Fetch/btoa/atob
 * implementation, not a hand-rolled approximation of it.
 */

const egw = {
	user : (_key : string) => 1,
	lang : (label : string) => label,
	preference : (_key : string, _app? : string) => null,
	config : (_name : string, _app? : string) => null,
	request : async() => ({}),
	message : (_msg : string, _type? : string) => {},
};

function createFakeApp() : MailApp
{
	return {egw, getCustomLabels : () => ({})} as unknown as MailApp;
}

function primeToken(jmap : MailJmap, profileID : string, client : any) : void
{
	(jmap as any).tokens[profileID] = {
		sessionUrl : "https://example.com", accountId : "acc1", access_token : "tok",
		expires_at : Date.now() + 100000, isLocal : false, customLabels : {},
	};
	(jmap as any).clients[profileID] = client;
}

function concatBytes(...parts : (number[] | Uint8Array)[]) : Uint8Array
{
	const arrays = parts.map((p) => p instanceof Uint8Array ? p : new Uint8Array(p));
	const total = arrays.reduce((sum, a) => sum + a.length, 0);
	const out = new Uint8Array(total);
	let offset = 0;
	for (const a of arrays)
	{
		out.set(a, offset);
		offset += a.length;
	}
	return out;
}

const CRLF = [0x0d, 0x0a];
const ascii = (s : string) : number[] => Array.from(new TextEncoder().encode(s));

/**
 * A fully ASCII/CRLF multipart/signed message (base64 signature, plain-ASCII signed body) -
 * every byte here is already valid UTF-8, so this is the "healthy" control case: it must survive
 * a real download+.text() round-trip byte-for-byte. If THIS one failed too, the bug would be far
 * more visible/universal than a single tester report - confirming it does NOT fail here isolates
 * the actual bug to non-UTF-8-safe bytes specifically, not "any signed message whatsoever".
 */
function asciiSafeSignedMessage() : Uint8Array
{
	return concatBytes(
		ascii('Content-Type: multipart/signed; protocol="application/pkcs7-signature"; '
			+ 'micalg=sha-256; boundary="sig-boundary"'), CRLF, CRLF,
		ascii('--sig-boundary'), CRLF,
		ascii('Content-Type: text/plain; charset=us-ascii'), CRLF,
		ascii('Content-Transfer-Encoding: 7bit'), CRLF, CRLF,
		ascii('Hello, this is the signed body.'), CRLF,
		ascii('--sig-boundary'), CRLF,
		ascii('Content-Type: application/pkcs7-signature; name="smime.p7s"'), CRLF,
		ascii('Content-Transfer-Encoding: base64'), CRLF, CRLF,
		ascii('MIIBogYJKoZIhvcNAQcCoIIBkzCCAY8CAQExDzANBglghkgBZQMEAgEFADALBgkq'), CRLF,
		ascii('--sig-boundary--'), CRLF,
	);
}

/**
 * Same structure, but the signed body is declared `Content-Transfer-Encoding: 8bit` with a
 * genuine ISO-8859-1 byte (0xE9, "e" with an acute accent) sitting directly in the raw stream -
 * a real, legitimate MIME shape (8BITMIME is a standard SMTP extension; 8bit CTE + a legacy
 * charset is common from older/corporate MUAs, notably Outlook), and exactly the kind of content
 * a real S/MIME signature would cover byte-for-byte.
 */
function eightBitSignedBodyMessage() : Uint8Array
{
	return concatBytes(
		ascii('Content-Type: multipart/signed; protocol="application/pkcs7-signature"; '
			+ 'micalg=sha-256; boundary="sig-boundary"'), CRLF, CRLF,
		ascii('--sig-boundary'), CRLF,
		ascii('Content-Type: text/plain; charset=iso-8859-1'), CRLF,
		ascii('Content-Transfer-Encoding: 8bit'), CRLF, CRLF,
		ascii('caf'), [0xe9], CRLF, // "café" written in real ISO-8859-1, not UTF-8
		ascii('--sig-boundary'), CRLF,
		ascii('Content-Type: application/pkcs7-signature; name="smime.p7s"'), CRLF,
		ascii('Content-Transfer-Encoding: base64'), CRLF, CRLF,
		ascii('MIIBogYJKoZIhvcNAQcCoIIBkzCCAY8CAQExDzANBglghkgBZQMEAgEFADALBgkq'), CRLF,
		ascii('--sig-boundary--'), CRLF,
	);
}

describe("MailJmap.fetchRawSourceByBlobId() - raw .eml byte fidelity (real-JMAP/Stalwart backend)", () =>
{
	it("preserves a plain ASCII/CRLF signed message byte-for-byte (control case)", async() =>
	{
		const original = asciiSafeSignedMessage();
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", {
			downloadBlob : async() => new Response(original, {headers : {"Content-Type" : "message/rfc822"}}),
		});

		const text = await jmap.fetchRawSourceByBlobId("1", "blob1");
		const roundTripped = new TextEncoder().encode(text);

		assert.deepEqual(Array.from(roundTripped), Array.from(original),
			"an already-UTF-8-safe message must round-trip exactly");
	});

	it("CORRUPTS a genuine 8-bit (non-UTF-8) signed-body byte via response.text()'s UTF-8 decode - reproduces the reported bug", async() =>
	{
		const original = eightBitSignedBodyMessage();
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", {
			downloadBlob : async() => new Response(original, {headers : {"Content-Type" : "message/rfc822"}}),
		});

		const text = await jmap.fetchRawSourceByBlobId("1", "blob1");
		const roundTripped = new TextEncoder().encode(text);

		// U+FFFD (encoded as EF BF BD in UTF-8) replaces the single invalid 0xE9 byte - proof of
		// exactly where/how the corruption happens, not just "somehow different"
		assert.include(text, "�", "the invalid ISO-8859-1 byte must have been replaced with U+FFFD");
		assert.notDeepEqual(Array.from(roundTripped), Array.from(original),
			"fetchRawSourceByBlobId() is NOT byte-exact for a genuinely 8-bit signed body - " +
			"this corrupts anything relying on this method for a byte-exact export (view-source, " +
			"and MailCompose.integrateSentMessage()'s .eml attachment via ajax_integrateSent())");
	});
});

describe("MailJmap.fetchRawSource() - same corruption reachable via the rowId-based wrapper (view-source/view-header path)", () =>
{
	function primeWithBlob(jmap : MailJmap, bytes : Uint8Array) : void
	{
		primeToken(jmap, "1", {
			requestMany : async(buildFn : (t : any) => any) =>
			{
				const t = {Email : {get : (_args : any) => null}};
				buildFn(t);
				return [{emails : {list : [{blobId : "blob1"}]}}];
			},
			downloadBlob : async() => new Response(bytes, {headers : {"Content-Type" : "message/rfc822"}}),
		});
	}

	it("still corrupts the same 8-bit signed body when reached via a rowId (not a raw blobId)", async() =>
	{
		const original = eightBitSignedBodyMessage();
		const jmap = new MailJmap(createFakeApp());
		primeWithBlob(jmap, original);

		const text = await jmap.fetchRawSource("acc1::1::INBOX::42");
		const roundTripped = new TextEncoder().encode(text);

		assert.notDeepEqual(Array.from(roundTripped), Array.from(original));
	});
});

/** Decode a base64 string back to raw bytes - the browser's real atob(), not a hand-rolled decoder. */
function base64ToBytes(base64 : string) : Uint8Array
{
	const binary = atob(base64);
	const bytes = new Uint8Array(binary.length);
	for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
	return bytes;
}

describe("MailJmap.fetchRawSourceBytesBase64ByBlobId()/fetchRawSourceBytesBase64() - THE FIX: byte-exact even for an 8-bit signed body", () =>
{
	it("preserves the plain ASCII/CRLF control message byte-for-byte", async() =>
	{
		const original = asciiSafeSignedMessage();
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", {
			downloadBlob : async() => new Response(original, {headers : {"Content-Type" : "message/rfc822"}}),
		});

		const base64 = await jmap.fetchRawSourceBytesBase64ByBlobId("1", "blob1");

		assert.deepEqual(Array.from(base64ToBytes(base64)), Array.from(original));
	});

	it("preserves the genuinely 8-bit signed body byte-for-byte (the exact case that corrupts fetchRawSourceByBlobId())", async() =>
	{
		const original = eightBitSignedBodyMessage();
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", {
			downloadBlob : async() => new Response(original, {headers : {"Content-Type" : "message/rfc822"}}),
		});

		const base64 = await jmap.fetchRawSourceBytesBase64ByBlobId("1", "blob1");
		const roundTripped = base64ToBytes(base64);

		assert.deepEqual(Array.from(roundTripped), Array.from(original),
			"fetchRawSourceBytesBase64ByBlobId() must be byte-exact - this is what " +
			"MailCompose.integrateSentMessage() now uses instead of the lossy fetchRawSourceByBlobId()");
	});

	it("also preserves it byte-for-byte via the rowId-based wrapper (the actual call site used by integrateSentMessage())", async() =>
	{
		const original = eightBitSignedBodyMessage();
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", {
			requestMany : async(buildFn : (t : any) => any) =>
			{
				const t = {Email : {get : (_args : any) => null}};
				buildFn(t);
				return [{emails : {list : [{blobId : "blob1"}]}}];
			},
			downloadBlob : async() => new Response(original, {headers : {"Content-Type" : "message/rfc822"}}),
		});

		const base64 = await jmap.fetchRawSourceBytesBase64("acc1::1::INBOX::42");
		const roundTripped = base64ToBytes(base64);

		assert.deepEqual(Array.from(roundTripped), Array.from(original));
	});

	it("chunks the binary-string conversion correctly for a message larger than the 0x8000 chunk size", async() =>
	{
		// a big run of a 8-bit byte repeated past the chunking boundary - exercises the
		// String.fromCharCode(...bytes.subarray(...)) loop actually spanning >1 chunk correctly,
		// not just happening to work for a small fixture
		const big = new Uint8Array(0x8000 * 2 + 137).fill(0xe9);
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", {
			downloadBlob : async() => new Response(big, {headers : {"Content-Type" : "message/rfc822"}}),
		});

		const base64 = await jmap.fetchRawSourceBytesBase64ByBlobId("1", "blob1");

		assert.deepEqual(Array.from(base64ToBytes(base64)), Array.from(big));
	});
});
