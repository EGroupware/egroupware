import {assert} from "@open-wc/testing";
import {Et2NextmatchDataProvider} from "../Et2NextmatchDataProvider";

function createProviderHost(overrides : Record<string, any> = {}) : any
{
	const host = document.createElement("div") as any;
	host.id = overrides.id ?? "nm-test";
	host.activeFilters = overrides.activeFilters ?? {col_filter: {}};
	host.sortBy = overrides.sortBy ?? (() => {});
	host.getAttribute = overrides.getAttribute ?? (() => host.id);
	host.getInstanceManager = overrides.getInstanceManager ?? (() => ({etemplate_exec_id: "exec-1", app: "addressbook"}));
	host.getArrayMgr = overrides.getArrayMgr ?? (() => ({data: {}, getEntry: (key : string) => key}));
	host.getParent = overrides.getParent ?? (() => ({getArrayMgr: () => ({data: {}})}));
	host.getWidgetById = overrides.getWidgetById ?? (() => null);
	host.closest = overrides.closest ?? (() => null);
	host.egw = overrides.egw ?? (() => ({
		app_name: () => "addressbook",
		dataFetch: () => {},
		dataRegisterUID: () => {}
	}));
	return host;
}

describe("Et2NextmatchDataProvider additional sel_options handling", () =>
{
	/**
	 * Contract under test:
	 * - Additional `sel_options` payloads update filter widgets without causing re-fetch loops.
	 *
	 * Setup strategy:
	 * - Stub `dataFetch()` to return a `sel_options` payload and no row order.
	 * - Provide a filter widget spy-like `set_select_options()` and stable current value.
	 *
	 * Pass criteria:
	 * - Exactly one fetch occurs.
	 * - Widget options are updated once.
	 * - Existing widget value remains intact.
	 */
	it("updates filter select options from fetch response without triggering another fetch", async() =>
	{
		let dataFetchCalls = 0;
		const updatedOptions : any[] = [];
		const filterWidget = {
			value: "legacy-selected-value",
			set_select_options: (options : any) => updatedOptions.push(options)
		};

		const host = createProviderHost({
			id: "nm",
			activeFilters: {col_filter: {}, filter: "legacy-selected-value"},
			getAttribute: (name : string) => name === "id" ? "nm" : null,
			getArrayMgr: (name : string) =>
			{
				if(name === "sel_options")
				{
					return {data: {}};
				}
				if(name === "content")
				{
					return {data: {}, getEntry: (key : string) => key};
				}
				return {data: {}};
			},
			getWidgetById: (id : string) => id === "filter" ? filterWidget : null,
			egw: () => ({
				app_name: () => "addressbook",
				dataFetch: (_execId, _request, _filters, _widgetId, callback) =>
				{
					dataFetchCalls++;
					callback({
						rows: {
							sel_options: {
								filter: {
									"": "All addressbooks",
									"17": "Current users"
								}
							}
						},
						order: [],
						total: 0
					});
				},
				dataRegisterUID: () => {}
			})
		});

		const provider = new Et2NextmatchDataProvider(host);
		await provider.fetchPage(0, 25);

		assert.equal(dataFetchCalls, 1, "sel_options update must not trigger an extra fetch");
		assert.equal(updatedOptions.length, 1, "filter widget should receive new options exactly once");
		assert.deepEqual(updatedOptions[0], {"": "All addressbooks", "17": "Current users"});
		assert.equal(filterWidget.value, "legacy-selected-value", "current value is kept client-side even if missing in new options");
	});
});

describe("Et2NextmatchDataProvider core behavior", () =>
{
	/**
	 * Contract under test:
	 * - Query signatures are deterministic for semantically equivalent filter objects.
	 *
	 * Setup strategy:
	 * - Create two hosts with identical filter content but different object key ordering.
	 *
	 * Pass criteria:
	 * - `getQuerySignature()` matches for both providers.
	 */
	it("produces stable query signatures for equivalent filter objects", () =>
	{
		const hostA = createProviderHost({
			id: "nm-a",
			getInstanceManager: () => ({app: "addressbook"}),
			egw: () => ({app_name: () => "addressbook"})
		});
		hostA._filters = {sort: {asc: true, id: "title"}, col_filter: {owner: "5", status: "open"}};

		const hostB = createProviderHost({
			id: "nm-b",
			getInstanceManager: () => ({app: "addressbook"}),
			egw: () => ({app_name: () => "addressbook"})
		});
		hostB._filters = {col_filter: {status: "open", owner: "5"}, sort: {id: "title", asc: true}};

		const providerA = new Et2NextmatchDataProvider(hostA);
		const providerB = new Et2NextmatchDataProvider(hostB);

		assert.equal(
			providerA.getQuerySignature(),
			providerB.getQuerySignature(),
			"query signature should be deterministic regardless of object key order"
		);
	});

	/**
	 * Contract under test:
	 * - Provider preserves server-declared row order regardless of UID callback timing.
	 *
	 * Setup strategy:
	 * - Stub `dataFetch()` with ordered UIDs.
	 * - Stub `dataRegisterUID()` to resolve in intentionally shuffled timing.
	 *
	 * Pass criteria:
	 * - Returned row ids follow original server `order`.
	 * - `total` matches response total.
	 */
	it("preserves server order even when UID registrations resolve out of order", async() =>
	{
		const host = createProviderHost({
			id: "nm-order",
			egw: () => ({
			app_name: () => "addressbook",
			dataFetch: (_execId, _request, _filters, _widgetId, callback) =>
			{
				callback({
					rows: {},
					order: ["uid-1", "uid-2", "uid-3"],
					total: 3
				});
			},
			dataRegisterUID: (uid : string, callback : Function) =>
			{
				const delays : Record<string, number> = {"uid-1": 15, "uid-2": 1, "uid-3": 5};
				window.setTimeout(() =>
				{
					callback({title: uid.toUpperCase()}, uid);
				}, delays[uid] || 0);
			}
			})
		});

		const provider = new Et2NextmatchDataProvider(host);
		const page = await provider.fetchPage(0, 25);
		assert.deepEqual(
			page.rows.map((row) => row.id),
			["uid-1", "uid-2", "uid-3"],
			"row order should match server `order` list, not callback completion order"
		);
		assert.equal(page.total, 3, "total should come from response");
	});

	/**
	 * Contract under test:
	 * - Data-store prefix selection uses app context first and host id as fallback.
	 *
	 * Setup strategy:
	 * - One host with `instanceManager.app`.
	 * - One host without app context but with a widget id.
	 *
	 * Pass criteria:
	 * - Prefix resolves to app when available, otherwise to host id.
	 */
	it("uses app name for data-store prefix and falls back to widget id", () =>
	{
		const appHost = createProviderHost({
			id: "nm-app",
			getInstanceManager: () => ({app: "calendar"}),
			egw: () => ({app_name: () => "addressbook"})
		});
		const appProvider = new Et2NextmatchDataProvider(appHost);
		assert.equal(appProvider.getDataStorePrefix(), "calendar", "instance app should be preferred for prefix");

		const idHost = createProviderHost({
			id: "nm-id",
			getAttribute: (name : string) => name === "id" ? "nm-id" : null,
			getInstanceManager: () => ({}),
			egw: () => ({app_name: () => ""})
		});
		const idProvider = new Et2NextmatchDataProvider(idHost);
		assert.equal(idProvider.getDataStorePrefix(), "nm-id", "host id should be fallback prefix when app is unavailable");
	});

	/**
	 * Contract under test:
	 * - Duplicate concurrent refresh requests for the same row reuse one server fetch.
	 *
	 * Setup strategy:
	 * - Stub `dataFetch()` to delay completion.
	 * - Trigger two provider refreshes for the same bare row id before the first resolves.
	 *
	 * Pass criteria:
	 * - Exactly one refresh fetch is issued.
	 * - Both callers receive the same normalized row id.
	 */
	it("deduplicates concurrent refresh requests for the same row", async() =>
	{
		let fetchCalls = 0;
		let releaseFetch : (() => void) | null = null;
		const host = createProviderHost({
			id: "nm-refresh-dedupe",
			egw: () => ({
			app_name: () => "addressbook",
			dataGetUIDdata: (uid : string) => (window as any).__providerRefreshCache?.[uid],
			dataFetch: (_execId, request, _filters, _widgetId, callback) =>
			{
				fetchCalls++;
				releaseFetch = () =>
				{
					(window as any).__providerRefreshCache = {
						"addressbook::42": {
							timestamp: Date.now(),
							data: {uid: "addressbook::42", title: "Updated title"}
						}
					};
					callback({rows: {}, total: request.refresh?.length || 1});
				};
			},
			dataRegisterUID: () => {}
			})
		});

		(window as any).__providerRefreshCache = {};
		const provider = new Et2NextmatchDataProvider(host);
		const first = provider.refresh(["42"], "update");
		const second = provider.refresh(["42"], "update");
		assert.equal(fetchCalls, 1, "concurrent duplicate refreshes should share one fetch");

		releaseFetch?.();
		const [firstResult, secondResult] = await Promise.all([first, second]);
		assert.deepEqual(firstResult.rows.map((row) => row.id), ["addressbook::42"]);
		assert.deepEqual(secondResult.rows.map((row) => row.id), ["addressbook::42"]);

		delete (window as any).__providerRefreshCache;
	});

	/**
	 * Contract under test:
	 * - Explicit refresh still fetches the server even when the local cache already has row data.
	 *
	 * Setup strategy:
	 * - Seed `dataGetUIDdata()` with an existing cache entry.
	 * - Trigger a provider refresh and let `dataFetch()` overwrite the cached row data.
	 *
	 * Pass criteria:
	 * - One server fetch is issued.
	 * - Returned row comes from the refreshed cache payload, not the stale prefetch snapshot.
	 */
	it("fetches explicit refreshes even when a cached row already exists", async() =>
	{
		let fetchCalls = 0;
		let cachedTitle = "Fresh cached row";
		const host = createProviderHost({
			id: "nm-refresh-cache",
			getInstanceManager: () => ({etemplate_exec_id: "exec-1", app: "calendar"}),
			egw: () => ({
			app_name: () => "calendar",
			dataGetUIDdata: (uid : string) => ({
				timestamp: Date.now(),
				data: {uid, title: cachedTitle}
			}),
			dataFetch: (_execId, _request, _filters, _widgetId, callback) =>
			{
				fetchCalls++;
				cachedTitle = "Fetched row";
				callback({rows: {}, total: 1});
			},
			dataRegisterUID: () => {}
			})
		});

		const provider = new Et2NextmatchDataProvider(host);
		const result = await provider.refresh(["99"], "update");

		assert.equal(fetchCalls, 1, "explicit refresh should fetch even when cache already has the row");
		assert.deepEqual(result.rows.map((row) => row.id), ["calendar::99"]);
		assert.equal(result.rows[0].data.title, "Fetched row");
	});

	/**
	 * Contract under test:
	 * - Refresh forwards each CRUD-like update type to `dataFetch()` and returns cached row data when the row exists.
	 *
	 * Setup strategy:
	 * - Run provider refresh once per update type with a stubbed cache and capture the request context.
	 *
	 * Pass criteria:
	 * - Each request carries the expected `type`, bare refresh id, and prefix.
	 * - Returned row ids are normalized datastore ids.
	 */
	(["add", "update", "edit", "delete"] as const).forEach((type) =>
	{
		it(`forwards ${type} refresh requests with provider context`, async() =>
		{
			const calls : any[] = [];
			const cache : Record<string, any> = {};
			const host = createProviderHost({
				id: `nm-refresh-${type}`,
				egw: () => ({
					app_name: () => "addressbook",
					dataGetUIDdata: (uid : string) => cache[uid] ?? null,
					dataFetch: (execId, request, filters, widgetId, callback, context, rowIds) =>
					{
						calls.push({execId, request, filters, widgetId, context, rowIds});
						cache["addressbook::42"] = {
							timestamp: Date.now(),
							data: {uid: "addressbook::42", title: `${type} row`}
						};
						callback({rows: {}, total: 1});
					},
					dataRegisterUID: () => {}
				})
			});

			const provider = new Et2NextmatchDataProvider(host);
			const result = await provider.refresh(["42"], type);

			assert.lengthOf(calls, 1, "refresh should issue exactly one fetch");
			assert.deepEqual(calls[0].request, {refresh: ["42"]});
			assert.deepEqual(calls[0].context, {type, prefix: "addressbook"});
			assert.deepEqual(calls[0].rowIds, ["42"]);
			assert.deepEqual(result.rows.map((row) => row.id), ["addressbook::42"]);
			assert.deepEqual(result.removedRowIds, []);
		});
	});

	/**
	 * Contract under test:
	 * - Missing refresh targets are reported as removals when the server says the row no longer exists.
	 *
	 * Setup strategy:
	 * - Return no cache entry after `dataFetch()` and a `total` of zero.
	 *
	 * Pass criteria:
	 * - The row is not returned in `rows`.
	 * - The normalized id is returned in `removedRowIds`.
	 */
	it("reports removed rows when refresh confirms the row no longer exists", async() =>
	{
		const host = createProviderHost({
			id: "nm-refresh-missing",
			egw: () => ({
				app_name: () => "addressbook",
				dataGetUIDdata: () => null,
				dataFetch: (_execId, _request, _filters, _widgetId, callback) =>
				{
					callback({rows: {}, total: 0});
				},
				dataRegisterUID: () => {}
			})
		});

		const provider = new Et2NextmatchDataProvider(host);
		const result = await provider.refresh(["42"], "update");

		assert.deepEqual(result.rows, []);
		assert.deepEqual(result.removedRowIds, ["addressbook::42"]);
	});

	/**
	 * Contract under test:
	 * - Unknown refresh totals fall back to cache presence instead of forcing a removal.
	 *
	 * Setup strategy:
	 * - Omit `total` from the response but populate the cache with fresh row data.
	 *
	 * Pass criteria:
	 * - The refreshed row is returned.
	 * - No removal is emitted.
	 */
	it("uses cached row presence when refresh response omits total", async() =>
	{
		const host = createProviderHost({
			id: "nm-refresh-no-total",
			egw: () => ({
				app_name: () => "addressbook",
				dataGetUIDdata: (uid : string) => ({
					timestamp: Date.now(),
					data: {uid, title: "Cache-backed row"}
				}),
				dataFetch: (_execId, _request, _filters, _widgetId, callback) =>
				{
					callback({rows: {}});
				},
				dataRegisterUID: () => {}
			})
		});

		const provider = new Et2NextmatchDataProvider(host);
		const result = await provider.refresh(["42"], "update");

		assert.deepEqual(result.rows.map((row) => row.id), ["addressbook::42"]);
		assert.deepEqual(result.removedRowIds, []);
	});

	/**
	 * Contract under test:
	 * - Multiple refresh ids merge cleanly into one response with data winning over removals.
	 *
	 * Setup strategy:
	 * - Refresh one existing row and one missing row in a single provider call.
	 *
	 * Pass criteria:
	 * - Existing row appears once in `rows`.
	 * - Missing row appears once in `removedRowIds`.
	 */
	it("merges mixed refresh results across multiple row ids", async() =>
	{
		const cache : Record<string, any> = {
			"addressbook::42": {
				timestamp: Date.now(),
				data: {uid: "addressbook::42", title: "Existing row"}
			}
		};
		const host = createProviderHost({
			id: "nm-refresh-multi",
			egw: () => ({
				app_name: () => "addressbook",
				dataGetUIDdata: (uid : string) => cache[uid] ?? null,
				dataFetch: (_execId, request, _filters, _widgetId, callback) =>
				{
					callback({rows: {}, total: request.refresh?.[0] === "42" ? 1 : 0});
				},
				dataRegisterUID: () => {}
			})
		});

		const provider = new Et2NextmatchDataProvider(host);
		const result = await provider.refresh(["42", "77"], "update");

		assert.deepEqual(result.rows.map((row) => row.id), ["addressbook::42"]);
		assert.deepEqual(result.removedRowIds, ["addressbook::77"]);
	});
});
