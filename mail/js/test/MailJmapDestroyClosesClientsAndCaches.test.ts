import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";

/**
 * MailJmap.destroy() - closing every live WebSocket client (mirrors the token-refresh path's
 * own close() a bit further down jmap.ts, which already does this for the SAME reason: a real
 * window/popup closing gets its sockets torn down by the browser on navigation anyway, but
 * MailApp can also destroy()+rebuild this instance without a real navigation - found while
 * auditing the client-side mail code for the same class of leak #305/#306 fixed, following up
 * on reports of EGroupware (Firefox especially) getting sluggish over a long session.
 */
describe("MailJmap.destroy()", () =>
{
	it("closes every live WebSocket client", () =>
	{
		const jmap : any = new MailJmap({} as any);
		const closed : string[] = [];
		jmap.clients = {
			acc1: {close : () => closed.push('acc1')},
			acc2: {close : () => closed.push('acc2')},
		};

		jmap.destroy();

		assert.deepEqual(closed.sort(), ['acc1', 'acc2']);
		assert.deepEqual(jmap.clients, {});
	});

	it("does not throw when there are no clients", () =>
	{
		const jmap : any = new MailJmap({} as any);
		jmap.clients = {};

		assert.doesNotThrow(() => jmap.destroy());
	});
});

/**
 * MailJmap.touchPgpCache() - pgpSignatureCache/pgpEncryptedCache are small per-row caches
 * (a verify-result object or a boolean) populated on every message view, with no eviction at
 * all before this - unlike rowsWithUrls/touchRowUrls(), which already bounds the object-URL
 * caches for the exact same reason. Over a multi-week session reading thousands of messages,
 * these grew forever - a plausible small contributor to "gets sluggish over a very long
 * session", independent of the popup-realm-pinning bugs.
 */
describe("MailJmap PGP cache eviction (touchPgpCache)", () =>
{
	it("evicts the least recently touched row's PGP caches once the bound is exceeded", () =>
	{
		const jmap : any = new MailJmap({} as any);
		const max = (MailJmap as any).MAX_ROWS_WITH_PGP_CACHE;

		for (let i = 0; i < max; i++)
		{
			jmap.pgpSignatureCache['row' + i] = {valid : true};
			jmap.pgpEncryptedCache['row' + i] = false;
			jmap.touchPgpCache('row' + i);
		}
		assert.property(jmap.pgpSignatureCache, 'row0', "not evicted yet, exactly at the bound");

		jmap.pgpSignatureCache['rowOverflow'] = {valid : true};
		jmap.pgpEncryptedCache['rowOverflow'] = true;
		jmap.touchPgpCache('rowOverflow');

		assert.notProperty(jmap.pgpSignatureCache, 'row0', "least recently touched row evicted");
		assert.notProperty(jmap.pgpEncryptedCache, 'row0', "both caches evicted together for the same row");
		assert.property(jmap.pgpSignatureCache, 'row1', "next-least-recent row is still kept");
		assert.property(jmap.pgpSignatureCache, 'rowOverflow');
	});

	it("re-touching an existing row's cache keeps it from being evicted ahead of truly older ones", () =>
	{
		const jmap : any = new MailJmap({} as any);
		const max = (MailJmap as any).MAX_ROWS_WITH_PGP_CACHE;

		for (let i = 0; i < max; i++)
		{
			jmap.pgpSignatureCache['row' + i] = {valid : true};
			jmap.touchPgpCache('row' + i);
		}
		// row0 is about to be the least recently touched - re-touch it (a cache HIT, eg. the
		// message is viewed again) so it should survive the next overflow instead of row1.
		jmap.touchPgpCache('row0');

		jmap.pgpSignatureCache['rowOverflow'] = {valid : true};
		jmap.touchPgpCache('rowOverflow');

		assert.property(jmap.pgpSignatureCache, 'row0', "re-touched row must not be evicted");
		assert.notProperty(jmap.pgpSignatureCache, 'row1', "row1 is now the least recently touched");
	});

	it("verifyPgpSignature() touches the cache on a cache HIT too, not just when freshly computed", async() =>
	{
		const jmap : any = new MailJmap({} as any);
		jmap.pgpSignatureCache['cached-row'] = {valid : true};

		await jmap.verifyPgpSignature('cached-row');

		assert.include(jmap.rowsWithPgpCache, 'cached-row');
	});
});
