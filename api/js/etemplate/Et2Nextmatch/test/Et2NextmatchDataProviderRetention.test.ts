import {assert} from "@open-wc/testing";
import {Et2NextmatchDataProvider} from "../Et2NextmatchDataProvider";

/**
 * Contract: Et2Datagrid keeps only row ids and reads row *data* back out of egw's
 * central cache, so anything still renderable has to stay cached - but no more than
 * that.  fetchPage() registers a listener per row purely to deliver it once; leaving
 * those registered pinned every row ever fetched (egw only evicts uids with no
 * listener), retained each closure's captured fetch response, and - because
 * dataRegisterUID appends without deduping - stacked another dead listener on the same
 * uid on every reload, all re-run on each later push for that row.  Delivery listeners
 * must therefore be swapped for a single shared keep-alive once the page resolves, and
 * released when the query changes or the host detaches.
 *
 * Setup: a fake egw modelling the real registry semantics from api/js/jsapi/egw_data.ts
 * - dataRegisterUID appends (no dedupe) and dataUnregisterUID removes by
 * callback+context - so listener bookkeeping is observable per uid.
 *
 * Pass criteria: after a page settles, each row's uid holds exactly one listener; that
 * count does not grow across repeated fetches of the same rows; releaseRetainedRows()
 * drops every listener this provider added, leaving nothing behind for the sweep to
 * skip.
 */

/** Mirrors egw_data's registry semantics closely enough for listener bookkeeping. */
function createFakeEgw(order : string[])
{
	const registered : Record<string, Array<{ callback : Function; context : any }>> = {};
	const stored : Record<string, any> = {};
	return {
		registered,
		stored,
		listenerCount: (uid : string) => (registered[uid] || []).length,
		totalListeners: () => Object.values(registered).reduce((sum, list) => sum + list.length, 0),
		api: {
			app_name: () => "addressbook",
			dataFetch: (_execId : string, _request : any, _filters : any, _widgetId : string, callback : Function) =>
			{
				callback({rows: {}, order, total: order.length});
			},
			dataRegisterUID: (uid : string, callback : Function, context : any) =>
			{
				// Real egw appends without deduping - that is what made repeat reloads pile up.
				(registered[uid] = registered[uid] || []).push({callback, context});
				callback({id: uid.replace(/^addressbook::/, "")}, uid);
			},
			dataUnregisterUID: (uid : string, callback : Function, context : any) =>
			{
				const list = registered[uid];
				if(!list)
				{
					return;
				}
				for(let i = list.length - 1; i >= 0; i--)
				{
					if((!callback || list[i].callback === callback) && (!context || list[i].context === context))
					{
						list.splice(i, 1);
					}
				}
				if(!list.length)
				{
					delete registered[uid];
				}
			},
			dataStoreUID: (uid : string, data : any) =>
			{
				stored[uid] = data;
			},
			dataGetUIDdata: (uid : string) => (stored[uid] ? {data: stored[uid]} : undefined)
		}
	};
}

function createHost(egwApi : any) : any
{
	const host = document.createElement("div") as any;
	host.id = "nm-retention";
	host.settings = {};
	host.activeFilters = {col_filter: {}};
	host.sortBy = () => {};
	host.getAttribute = () => host.id;
	host.getInstanceManager = () => ({etemplate_exec_id: "exec-1", app: "addressbook"});
	host.getArrayMgr = () => ({data: {}, getEntry: (key : string) => key});
	host.getParent = () => ({getArrayMgr: () => ({data: {}})});
	host.getWidgetById = () => null;
	host.closest = () => null;
	host.egw = () => egwApi;
	return host;
}

/** Let the microtask that swaps delivery listeners for keep-alives run. */
async function afterMicrotasks() : Promise<void>
{
	for(let i = 0; i < 3; i++)
	{
		await Promise.resolve();
	}
}

const ORDER = ["addressbook::1", "addressbook::2", "addressbook::3"];

describe("Et2NextmatchDataProvider row retention", () =>
{
	it("leaves exactly one keep-alive listener per row after a page settles", async() =>
	{
		const egw = createFakeEgw(ORDER);
		const provider = new Et2NextmatchDataProvider(createHost(egw.api));

		const page = await provider.fetchPage(0, 3);
		await afterMicrotasks();

		assert.equal(page.rows.length, 3, "all three rows should be delivered");
		for(const row of page.rows)
		{
			assert.equal(egw.listenerCount(row.id), 1, `row ${row.id} should hold exactly one listener`);
		}
	});

	it("does not accumulate listeners across repeated fetches of the same rows", async() =>
	{
		const egw = createFakeEgw(ORDER);
		const provider = new Et2NextmatchDataProvider(createHost(egw.api));

		for(let reload = 0; reload < 5; reload++)
		{
			await provider.fetchPage(0, 3);
			await afterMicrotasks();
		}

		assert.equal(egw.totalListeners(), 3, "five fetches of three rows should still leave three listeners");
	});

	it("releases every listener it added when the query changes", async() =>
	{
		const egw = createFakeEgw(ORDER);
		const provider = new Et2NextmatchDataProvider(createHost(egw.api));

		await provider.fetchPage(0, 3);
		await afterMicrotasks();
		assert.isAbove(egw.totalListeners(), 0, "rows should be retained while the query is live");

		provider.releaseRetainedRows();

		assert.equal(egw.totalListeners(), 0, "releasing should leave nothing pinned in the cache");
	});

	it("leaves other widgets' listeners for the same row alone", async() =>
	{
		const egw = createFakeEgw(ORDER);
		const provider = new Et2NextmatchDataProvider(createHost(egw.api));
		const otherWidget = {};
		const otherCallback = () => {};
		egw.api.dataRegisterUID("addressbook::1", otherCallback, otherWidget);

		await provider.fetchPage(0, 3);
		await afterMicrotasks();
		provider.releaseRetainedRows();

		assert.equal(egw.listenerCount("addressbook::1"), 1, "another widget's listener must survive");
		assert.strictEqual(egw.registered["addressbook::1"][0].callback, otherCallback, "and must be the one it registered");
	});
});
