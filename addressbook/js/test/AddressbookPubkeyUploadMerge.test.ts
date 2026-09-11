import {assert} from "@open-wc/testing";
import "./AddressbookAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way MailVcardMessage.test.ts/AddressbookNoFiltersReload.test.ts already do
import "../../../api/js/etemplate/Et2Widget/Et2Widget";

const APP_SOURCE = '/addressbook/js/app.ts';

/**
 * AddressbookApp.keyUserIdAddresses() (addressbook/js/app.ts) - the PGP address-detection half
 * of the pubkey_uploaded() UI gap fix (doc/ai/projects/mail-pgp-signature-verification.md, Phase 5
 * item 1's own "Known follow-up"): pubkeyUploadStart() uses this to determine which of a
 * contact's own addresses an uploaded PGP key actually claims (matched against the contact's
 * `email`/`email_home` fields), so the upload merges into that address' slot instead of
 * clobbering the whole file. Same real-key fixture MailJmapBuildAutocryptHeader.test.ts already
 * uses (a genuine openpgp.js-generated key with two UIDs, `fixture@example.invalid` and
 * `fixture-alt@example.invalid`) - duplicated here per this test suite's established
 * self-contained-file convention.
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

describe("AddressbookApp.keyUserIdAddresses()", () =>
{
	let AddressbookApp : any;

	before(async function()
	{
		this.timeout(15000);
		await import(APP_SOURCE);
		AddressbookApp = (<any>window).app.classes.addressbook;
	});

	it("returns every User ID's email address, lowercased", async() =>
	{
		const openpgp : any = await import("openpgp/lightweight");
		const key = await openpgp.readKey({armoredKey: MULTI_UID_KEY_ARMOR});

		const addresses = AddressbookApp.keyUserIdAddresses(key);

		assert.sameMembers(addresses, ["fixture@example.invalid", "fixture-alt@example.invalid"]);
	});

	it("returns an empty array for a key object with no User IDs at all", () =>
	{
		const addresses = AddressbookApp.keyUserIdAddresses({getUserIDs: () => []});

		assert.deepEqual(addresses, []);
	});

	it("filters out a User ID with no email address in it", () =>
	{
		const addresses = AddressbookApp.keyUserIdAddresses({getUserIDs: () => ["Just A Name, No Email At All"]});

		assert.deepEqual(addresses, []);
	});

	it("matches only the addresses actually present on a contact, ignoring the others", async() =>
	{
		const openpgp : any = await import("openpgp/lightweight");
		const key = await openpgp.readKey({armoredKey: MULTI_UID_KEY_ARMOR});
		const claimed = AddressbookApp.keyUserIdAddresses(key);
		const known = ["fixture@example.invalid", "someone-else@example.invalid"];

		const matched = claimed.filter((a : string) => known.includes(a));

		assert.deepEqual(matched, ["fixture@example.invalid"]);
	});
});
