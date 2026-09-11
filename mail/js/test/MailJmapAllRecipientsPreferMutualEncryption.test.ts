import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * MailJmap.allRecipientsPreferMutualEncryption() (mail/js/jmap.ts) - Phase 5 item 6's
 * recipient-side check for the "mutual" auto-encrypt preference (doc/ai/projects/
 * mail-pgp-signature-verification.md): true only when EVERY given address has its own
 * `prefer-encrypt=mutual` already stored (addressbook_bo::ajax_get_autocrypt_prefer_encrypt()).
 */
function createFakeApp(egwRequest : (method : string, params : any[]) => Promise<any>) : MailApp
{
	return {egw: {request: egwRequest}} as unknown as MailApp;
}

describe("MailJmap.allRecipientsPreferMutualEncryption()", () =>
{
	it("returns true when every recipient has prefer-encrypt=mutual stored", async() =>
	{
		let requestedMethod : string | undefined;
		let requestedParams : any[] | undefined;
		const jmap = new MailJmap(createFakeApp(async(method, params) =>
		{
			requestedMethod = method;
			requestedParams = params;
			return {"a@example.invalid": "mutual", "b@example.invalid": "mutual"};
		}));

		const result = await jmap.allRecipientsPreferMutualEncryption(["a@example.invalid", "b@example.invalid"]);

		assert.isTrue(result);
		assert.equal(requestedMethod, "addressbook.addressbook_bo.ajax_get_autocrypt_prefer_encrypt");
		assert.deepEqual(requestedParams, [["a@example.invalid", "b@example.invalid"]]);
	});

	it("returns false when even one recipient is missing prefer-encrypt=mutual", async() =>
	{
		const jmap = new MailJmap(createFakeApp(async() => ({"a@example.invalid": "mutual"})));

		assert.isFalse(await jmap.allRecipientsPreferMutualEncryption(["a@example.invalid", "b@example.invalid"]));
	});

	it("returns false for an empty recipient list, without even attempting a lookup", async() =>
	{
		let called = false;
		const jmap = new MailJmap(createFakeApp(async() =>
		{
			called = true;
			return {};
		}));

		assert.isFalse(await jmap.allRecipientsPreferMutualEncryption([]));
		assert.isFalse(called);
	});

	it("lowercases and deduplicates addresses before requesting", async() =>
	{
		let requestedParams : any[] | undefined;
		const jmap = new MailJmap(createFakeApp(async(_method, params) =>
		{
			requestedParams = params;
			return {"a@example.invalid": "mutual"};
		}));

		const result = await jmap.allRecipientsPreferMutualEncryption(["A@Example.invalid", "a@example.invalid"]);

		assert.isTrue(result);
		assert.deepEqual(requestedParams, [["a@example.invalid"]]);
	});

	it("fails closed (returns false, never throws) when the addressbook lookup itself fails", async() =>
	{
		const jmap = new MailJmap(createFakeApp(async() =>
		{
			throw new Error("network error");
		}));

		assert.isFalse(await jmap.allRecipientsPreferMutualEncryption(["a@example.invalid"]));
	});

	it("does not match 'nopreference' or any other stored value, only the exact string 'mutual'", async() =>
	{
		const jmap = new MailJmap(createFakeApp(async() => ({"a@example.invalid": "nopreference"})));

		assert.isFalse(await jmap.allRecipientsPreferMutualEncryption(["a@example.invalid"]));
	});
});
