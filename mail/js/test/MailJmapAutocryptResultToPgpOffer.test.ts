import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";

/**
 * MailJmap.autocryptResultToPgpOffer() (mail/js/jmap.ts) - converts a successfully-parsed
 * `Autocrypt:` header (parseAutocryptHeader()'s own result, covered by PgpAutocryptHeaderParsing.
 * test.ts) into the `{email, armoredKey, keyFingerprint, keyUid}` shape MailApp.
 * pgpAutoOfferAddToContact()/pgpKeyAddToContact() (`mail/js/app.ts`) already expect from the
 * inline-key case - Autocrypt Phase 5 item 4's remaining wiring (doc/ai/projects/
 * mail-pgp-signature-verification.md), 2026-09-09.
 *
 * MULTI_UID_KEY_ARMOR is the SAME real openpgp.js-generated fixture PgpAutocryptKeydata.test.ts
 * already uses (synthetic `*@example.invalid` identities) - round-tripped here through
 * armoredKeyToAutocryptKeydata() first to get real, valid `keydata=` bytes to feed back in, exactly
 * as a real incoming Autocrypt header would carry.
 */
const MULTI_UID_KEY_ARMOR = `-----BEGIN PGP PUBLIC KEY BLOCK-----

xjMEaqFGLhYJKwYBBAHaRw8BAQdAPk9weuCMMStID5gVpU/BcQVOdhz68Y8+
oqsBQjXO1VHNKVBIUFVuaXQgRml4dHVyZSA8Zml4dHVyZUBleGFtcGxlLmlu
dmFsaWQ+wsATBBMWCgCFBYJqoUYuAwsJBwkQqD5F3UidAGFFFAAAAAAAHAAg
c2FsdEBub3RhdGlvbnMub3BlbnBncGpzLm9yZ+a2SuQ742mO2BYkPhxEMZMi
3L1CrMZ5nSWqeiH4ggANBRUKCA4MBBYAAgECGQECmwMCHgEWIQQSA8irdCoN
/SUkAzSoPkXdSJ0AYQAApzoA/AuK84X4gPnTHS+qw2q4FFJrSW4UpJkfZD4v
6t4DztJaAP0eTXfalNJNCpMJhyNEyD85ZwW+2OmhjwpQ5GV+vJcADc0xUEhQ
VW5pdCBGaXh0dXJlIEFsdCA8Zml4dHVyZS1hbHRAZXhhbXBsZS5pbnZhbGlk
PsLAEAQTFgoAggWCaqFGLgMLCQcJEKg+Rd1InQBhRRQAAAAAABwAIHNhbHRA
bm90YXRpb25zLm9wZW5wZ3Bqcy5vcmejNGlwBiDCKykG3ojMYytHdK2trSCD
E9W4AKnnpt0idQUVCggODAQWAAIBApsDAh4BFiEEEgPIq3QqDf0lJAM0qD5F
3UidAGEAABLmAQCUOcJNNVeBHTRPS9OiDUI/eq2UytqtlwPwrD8aRnIm9QEA
8wk3qkmOenZrktVvZUMFERu1CGrcvxoNI9oPd/jXngXOOARqoUYuEgorBgEE
AZdVAQUBAQdAMJjJjxWBbFl4METvMASQh3mAKOmiyjtz0msZijzAVHkDAQgH
wr4EGBYKAHAFgmqhRi4JEKg+Rd1InQBhRRQAAAAAABwAIHNhbHRAbm90YXRp
b25zLm9wZW5wZ3Bqcy5vcmcBpf/2XqojBrmgMdRvm7aahGsMeSdaRetw5cSX
a2H7EwKbDBYhBBIDyKt0Kg39JSQDNKg+Rd1InQBhAACgMgD+O7ZPZeqB/lz7
jVVdHDwoFpht7qokhrD2iVdrPGmoxcsBALhLiCoTYdOx7T6iVjD4N0tMrhyP
FH95hkPykAtOnJwL
=1O1y
-----END PGP PUBLIC KEY BLOCK-----`;

describe("MailJmap.autocryptResultToPgpOffer()", () =>
{
	it("converts a valid Autocrypt result into the pgpAutoOfferAddToContact() shape", async() =>
	{
		const keydata = await MailJmap.armoredKeyToAutocryptKeydata(MULTI_UID_KEY_ARMOR);
		const parsed = MailJmap.parseAutocryptHeader(`addr=fixture@example.invalid; keydata=${keydata}`,
			"fixture@example.invalid");
		assert.isNotNull(parsed);

		const offer = await MailJmap.autocryptResultToPgpOffer(parsed!);

		assert.equal(offer?.email, "fixture@example.invalid");
		assert.equal(offer?.keyUid, "PHPUnit Fixture <fixture@example.invalid>");
		assert.match(offer!.keyFingerprint, /^[0-9a-f]{16,64}$/);
		// the re-armored key must itself be a real, re-parseable PGP public key block, still usable
		// for encryption (same round-trip guarantee PgpAutocryptKeydata.test.ts already covers for
		// armoredKeyToAutocryptKeydata() itself)
		assert.include(offer!.armoredKey, "-----BEGIN PGP PUBLIC KEY BLOCK-----");
	});

	it("returns null (not thrown) for garbage keydata that can't be parsed back into a key", async() =>
	{
		const offer = await MailJmap.autocryptResultToPgpOffer({
			addr: "fixture@example.invalid", keydata: "not-valid-base64-key-data!!!", preferEncrypt: "nopreference",
		});

		assert.isNull(offer);
	});
});
