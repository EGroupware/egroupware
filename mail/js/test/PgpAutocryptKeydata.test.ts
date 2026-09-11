import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";

/**
 * MailJmap.armoredKeyToAutocryptKeydata()/autocryptKeydataToArmoredKey() (mail/js/jmap.ts) -
 * converts between our own armored-text key storage and Autocrypt's own `keydata=` shape (base64
 * of a MINIMIZED 5-packet binary export - see doc/ai/projects/mail-pgp-signature-verification.md's
 * "Autocrypt integration (Phase 5)" plan for the full design).
 *
 * MULTI_UID_KEY_ARMOR below is a real openpgp.js-generated fixture (the full `openpgp` npm package,
 * which alone supports key generation - the lightweight build these functions themselves use does
 * not) with TWO user IDs and one subkey, generated once via `node -e "..."` and hardcoded here -
 * synthetic identities only (`*@example.invalid`), matching AGENTS.md's rule against real personal
 * data in test fixtures.
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

describe("MailJmap.armoredKeyToAutocryptKeydata()", () =>
{
	it("minimizes a multi-UID key to just the primary user's UID + one encryption subkey, still "
		+ "usable for encryption after the round trip", async() =>
	{
		const keydata = await MailJmap.armoredKeyToAutocryptKeydata(MULTI_UID_KEY_ARMOR);
		assert.isString(keydata);

		// decode the produced keydata back to a real key to inspect its actual shape - this is
		// the same thing a peer's own Autocrypt implementation would do on receipt
		const rearmored = await MailJmap.autocryptKeydataToArmoredKey(keydata!);
		const openpgp : any = await import("openpgp/lightweight");
		const key = await openpgp.readKey({armoredKey: rearmored});

		assert.deepEqual(key.getUserIDs(), ["PHPUnit Fixture <fixture@example.invalid>"],
			"only the PRIMARY user ID must survive minimization, not the second one");
		assert.equal(key.subkeys.length, 1, "exactly one (the encryption) subkey must survive");
		assert.isOk(await key.getEncryptionKey(), "the minimized key must still have a working encryption key");
	});

	it("produces base64 that decodes to a binary (non-armored) OpenPGP packet stream, per the "
		+ "Autocrypt spec's own keydata requirement", async() =>
	{
		const keydata = await MailJmap.armoredKeyToAutocryptKeydata(MULTI_UID_KEY_ARMOR);

		assert.notInclude(atob(keydata!), "-----BEGIN PGP PUBLIC KEY BLOCK-----",
			"keydata must be raw binary, never still-armored text");
	});

	it("returns null for a key with no encryption-capable subkey at all - not usable for "
		+ "Autocrypt's own mandated 5-packet (signing-primary + subkey-encrypts) shape", async() =>
	{
		const keydata = await MailJmap.armoredKeyToAutocryptKeydata(NO_SUBKEY_KEY_ARMOR);

		assert.isNull(keydata);
	});
});

describe("MailJmap.autocryptKeydataToArmoredKey()", () =>
{
	it("converts binary keydata back to ASCII-armored text matching our own addressbook storage "
		+ "format", async() =>
	{
		const keydata = await MailJmap.armoredKeyToAutocryptKeydata(MULTI_UID_KEY_ARMOR);

		const armored = await MailJmap.autocryptKeydataToArmoredKey(keydata!);

		assert.match(armored, /^-----BEGIN PGP PUBLIC KEY BLOCK-----/);
		assert.match(armored, /-----END PGP PUBLIC KEY BLOCK-----\s*$/);
	});
});
