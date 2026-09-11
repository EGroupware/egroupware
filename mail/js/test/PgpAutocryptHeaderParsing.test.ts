import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";

/**
 * MailJmap.parseAutocryptHeader()/parseAutocryptHeaders() (mail/js/jmap.ts) - Autocrypt Level 1
 * (https://docs.autocrypt.org/level1.html) Phase 5 item 4's receiving half, pure parsing logic
 * only (doc/ai/projects/mail-pgp-signature-verification.md - not yet wired into any real "read an
 * incoming message" caller, that needs the not-yet-built consent dialog from item 5 first). Every
 * rule tested here is quoted from the spec text itself, fetched live 2026-09-09 while writing this.
 */
const FROM = "sender@example.invalid";
const KEYDATA = "AAAABASE64KEYDATA====";

function header(extra : string = "") : string
{
	return `addr=${FROM}; ${extra}keydata=${KEYDATA}`;
}

describe("MailJmap.parseAutocryptHeader()", () =>
{
	it("parses a minimal valid header (addr + keydata only) as prefer-encrypt:'nopreference'", () =>
	{
		const result = MailJmap.parseAutocryptHeader(`addr=${FROM}; keydata=${KEYDATA}`, FROM);

		assert.deepEqual(result, {addr: FROM, keydata: KEYDATA, preferEncrypt: "nopreference"});
	});

	it("parses prefer-encrypt=mutual", () =>
	{
		const result = MailJmap.parseAutocryptHeader(header("prefer-encrypt=mutual; "), FROM);

		assert.equal(result?.preferEncrypt, "mutual");
	});

	it("treats any prefer-encrypt value other than 'mutual' as 'nopreference', not an error - "
		+ "per spec: \"any other value ... should interpret the value as nopreference\"", () =>
	{
		const result = MailJmap.parseAutocryptHeader(header("prefer-encrypt=yes; "), FROM);

		assert.equal(result?.preferEncrypt, "nopreference");
		assert.equal(result?.addr, FROM);
	});

	it("lowercases addr for comparison/output, matching a differently-cased From address", () =>
	{
		const result = MailJmap.parseAutocryptHeader(`addr=Sender@Example.Invalid; keydata=${KEYDATA}`,
			"SENDER@EXAMPLE.INVALID");

		assert.equal(result?.addr, "sender@example.invalid");
	});

	it("rejects the header entirely when addr does not match the message's From address - "
		+ "per spec: \"the entire Autocrypt header MUST be treated as invalid\"", () =>
	{
		assert.isNull(MailJmap.parseAutocryptHeader(`addr=someone-else@example.invalid; keydata=${KEYDATA}`, FROM));
	});

	it("rejects a header missing addr", () =>
	{
		assert.isNull(MailJmap.parseAutocryptHeader(`keydata=${KEYDATA}`, FROM));
	});

	it("rejects a header missing keydata", () =>
	{
		assert.isNull(MailJmap.parseAutocryptHeader(`addr=${FROM}`, FROM));
	});

	it("rejects an empty header value", () =>
	{
		assert.isNull(MailJmap.parseAutocryptHeader("", FROM));
	});

	it("silently ignores an unrecognized attribute whose name starts with '_' (non-critical), "
		+ "per spec: \"MUA SHOULD ignore any unsupported non-critical attributes\"", () =>
	{
		const result = MailJmap.parseAutocryptHeader(header("_unknown-vendor-attr=whatever; "), FROM);

		assert.deepEqual(result, {addr: FROM, keydata: KEYDATA, preferEncrypt: "nopreference"});
	});

	it("rejects the whole header on an unrecognized CRITICAL attribute (no leading '_'), per "
		+ "spec: \"MUST treat the entire Autocrypt header as invalid if it encounters a 'critical' "
		+ "attribute that it doesn't support\"", () =>
	{
		assert.isNull(MailJmap.parseAutocryptHeader(header("some-future-attr=x; "), FROM));
	});

	it("rejects a header exceeding the spec's 10 KiB size cap, defensively (a spec-compliant "
		+ "sender never exceeds it, but a malicious/corrupted one might)", () =>
	{
		const huge = `addr=${FROM}; keydata=${"A".repeat(11 * 1024)}`;

		assert.isNull(MailJmap.parseAutocryptHeader(huge, FROM));
	});

	it("rejects a malformed attribute with no '=' rather than guessing what it means", () =>
	{
		assert.isNull(MailJmap.parseAutocryptHeader(`addr=${FROM}; garbage; keydata=${KEYDATA}`, FROM));
	});

	it("rejects a header with a duplicated attribute name", () =>
	{
		assert.isNull(MailJmap.parseAutocryptHeader(`addr=${FROM}; addr=other@example.invalid; keydata=${KEYDATA}`, FROM));
	});

	it("tolerates whitespace around ';' and '=' the same way real-world folded headers produce it", () =>
	{
		const result = MailJmap.parseAutocryptHeader(`addr = ${FROM} ;  keydata = ${KEYDATA} `, FROM);

		assert.deepEqual(result, {addr: FROM, keydata: KEYDATA, preferEncrypt: "nopreference"});
	});
});

describe("MailJmap.parseAutocryptHeaders() - multiple-header handling", () =>
{
	it("returns the single result when exactly one header value is valid", () =>
	{
		const result = MailJmap.parseAutocryptHeaders([`addr=${FROM}; keydata=${KEYDATA}`], FROM);

		assert.deepEqual(result, {addr: FROM, keydata: KEYDATA, preferEncrypt: "nopreference"});
	});

	it("returns null when zero header values are valid", () =>
	{
		assert.isNull(MailJmap.parseAutocryptHeaders([`addr=someone-else@example.invalid; keydata=${KEYDATA}`], FROM));
	});

	it("discards ALL headers - returns null - when MORE THAN ONE is independently valid, per "
		+ "spec: \"If there is more than one valid header, this SHOULD be treated as an error, and "
		+ "all Autocrypt headers discarded as invalid\"", () =>
	{
		const result = MailJmap.parseAutocryptHeaders([
			`addr=${FROM}; keydata=${KEYDATA}`,
			`addr=${FROM}; keydata=DIFFERENTKEYDATA`,
		], FROM);

		assert.isNull(result);
	});

	it("still returns the one valid result when a SECOND header value is present but invalid "
		+ "(only one was ever actually valid)", () =>
	{
		const result = MailJmap.parseAutocryptHeaders([
			`addr=${FROM}; keydata=${KEYDATA}`,
			`addr=someone-else@example.invalid; keydata=OTHER`,
		], FROM);

		assert.deepEqual(result, {addr: FROM, keydata: KEYDATA, preferEncrypt: "nopreference"});
	});

	it("returns null for an empty array", () =>
	{
		assert.isNull(MailJmap.parseAutocryptHeaders([], FROM));
	});
});
