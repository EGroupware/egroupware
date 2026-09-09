import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * MailJmap.buildAutocryptHeader() (mail/js/jmap.ts) - Autocrypt Level 1 (https://docs.autocrypt.
 * org/level1.html) Phase 5 item 3 (doc/ai/projects/mail-pgp-signature-verification.md): builds the
 * sending identity's own outgoing `Autocrypt:` header value from whatever PGP key the addressbook
 * has stored for that identity's own address, reusing armoredKeyToAutocryptKeydata()'s own
 * minimize/re-armor logic (already covered end-to-end by PgpAutocryptKeydata.test.ts) - this file
 * covers the addressbook-lookup + header-assembly logic layered on top of it, mocking
 * egw.request() the same way PgpSignatureVerification.test.ts's addressbook lookups are mocked.
 *
 * MULTI_UID_KEY_ARMOR/NO_SUBKEY_KEY_ARMOR are the SAME real openpgp.js-generated fixtures
 * PgpAutocryptKeydata.test.ts already uses (synthetic `*@example.invalid` identities) - duplicated
 * here rather than imported/exported, matching this test suite's established self-contained-file
 * convention.
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

const NO_SUBKEY_KEY_ARMOR = `-----BEGIN PGP PUBLIC KEY BLOCK-----

xjMEaqFGQRYJKwYBBAHaRw8BAQdAYferR3Pk2IeLrl4rJKOxlM9yiBxOFWXI
L2Av58NKO1nNLE5vIFN1YmtleSBGaXh0dXJlIDxub3N1YmtleUBleGFtcGxl
LmludmFsaWQ+wsATBBMWCgCFBYJqoUZBAwsJBwkQqoMShnVopkNFFAAAAAAA
HAAgc2FsdEBub3RhdGlvbnMub3BlbnBncGpzLm9yZ/0FoW8wJNHjFd5Vu0+S
naltmbOcjckv0wtAnAGFh5MEBRUKCA4MBBYAAgECGQECmwMCHgEWIQTFBW09
+eMX83xY/cuqgxKGdWimQwAAHHkBAMua1Fbv/N7JwzU6hvt9z1Y209eLuYHH
7tm/sryNTHD8AQCyaT1RS6RjNdiNRe3VKPKx6MFXxIDA5FbLRQRCxHZfCA==
=FMci
-----END PGP PUBLIC KEY BLOCK-----`;

const IDENTITY = {id: "1", email: "fixture@example.invalid", name: "Sender"};

function createFakeApp(egwRequest : (method : string, params : any[]) => Promise<any>) : MailApp
{
	return {egw: {request: egwRequest}} as unknown as MailApp;
}

describe("MailJmap.buildAutocryptHeader()", () =>
{
	it("returns 'addr=...; keydata=...' when the addressbook has a usable PGP key for the identity", async() =>
	{
		let requestedMethod : string | undefined;
		let requestedParams : any[] | undefined;
		const jmap = new MailJmap(createFakeApp(async(method, params) =>
		{
			requestedMethod = method;
			requestedParams = params;
			return {"fixture@example.invalid": MULTI_UID_KEY_ARMOR};
		}));

		const header = await (jmap as any).buildAutocryptHeader(IDENTITY);

		assert.equal(requestedMethod, "addressbook.addressbook_bo.ajax_get_pgp_keys");
		assert.deepEqual(requestedParams, [["fixture@example.invalid"]]);
		const expectedKeydata = await MailJmap.armoredKeyToAutocryptKeydata(MULTI_UID_KEY_ARMOR);
		assert.equal(header, `addr=fixture@example.invalid; keydata=${expectedKeydata}`);
		// keydata= must be the LAST parameter per the Autocrypt spec
		assert.isTrue(header!.endsWith(`keydata=${expectedKeydata}`));
	});

	it("returns null when the addressbook has no PGP key stored for this identity at all", async() =>
	{
		const jmap = new MailJmap(createFakeApp(async() => ({})));

		assert.isNull(await (jmap as any).buildAutocryptHeader(IDENTITY));
	});

	it("returns null when the stored key has no Autocrypt-compatible encryption subkey", async() =>
	{
		const jmap = new MailJmap(createFakeApp(async() => ({"fixture@example.invalid": NO_SUBKEY_KEY_ARMOR})));

		assert.isNull(await (jmap as any).buildAutocryptHeader(IDENTITY));
	});

	it("returns null (not thrown) when the addressbook lookup itself fails", async() =>
	{
		const jmap = new MailJmap(createFakeApp(async() =>
		{
			throw new Error("network error");
		}));

		assert.isNull(await (jmap as any).buildAutocryptHeader(IDENTITY));
	});

	it("returns null without even attempting a lookup when the identity has no email", async() =>
	{
		let called = false;
		const jmap = new MailJmap(createFakeApp(async() =>
		{
			called = true;
			return {};
		}));

		assert.isNull(await (jmap as any).buildAutocryptHeader({id: "1", email: "", name: "No Email"}));
		assert.isFalse(called);
	});
});
