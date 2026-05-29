import {assert} from "@open-wc/testing";
import {Et2NextmatchDataProvider} from "../Et2NextmatchDataProvider";

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

		const host = document.createElement("div") as any;
		host.id = "nm";
		host.activeFilters = {col_filter: {}, filter: "legacy-selected-value"};
		host.sortBy = () => {};
		host.getAttribute = (name : string) => name === "id" ? "nm" : null;
		host.getInstanceManager = () => ({etemplate_exec_id: "exec-1", app: "addressbook"});
		host.getArrayMgr = (name : string) =>
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
		};
		host.getParent = () => ({
			getArrayMgr: () => ({data: {}})
		});
		host.getWidgetById = (id : string) => id === "filter" ? filterWidget : null;
		host.closest = () => null;
		host.egw = () => ({
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
		const hostA = document.createElement("div") as any;
		hostA.id = "nm-a";
		hostA.getAttribute = () => "nm-a";
		hostA.getInstanceManager = () => ({app: "addressbook"});
		hostA.egw = () => ({app_name: () => "addressbook"});
		hostA._filters = {sort: {asc: true, id: "title"}, col_filter: {owner: "5", status: "open"}};

		const hostB = document.createElement("div") as any;
		hostB.id = "nm-b";
		hostB.getAttribute = () => "nm-b";
		hostB.getInstanceManager = () => ({app: "addressbook"});
		hostB.egw = () => ({app_name: () => "addressbook"});
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
		const host = document.createElement("div") as any;
		host.id = "nm-order";
		host.activeFilters = {col_filter: {}};
		host.sortBy = () => {};
		host.getAttribute = () => "nm-order";
		host.getInstanceManager = () => ({etemplate_exec_id: "exec-1", app: "addressbook"});
		host.getArrayMgr = () => ({data: {}, getEntry: (key : string) => key});
		host.getParent = () => ({getArrayMgr: () => ({data: {}})});
		host.getWidgetById = () => null;
		host.closest = () => null;
		host.egw = () => ({
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
		const appHost = document.createElement("div") as any;
		appHost.id = "nm-app";
		appHost.getAttribute = () => "nm-app";
		appHost.getInstanceManager = () => ({app: "calendar"});
		appHost.egw = () => ({app_name: () => "addressbook"});
		const appProvider = new Et2NextmatchDataProvider(appHost);
		assert.equal(appProvider.getDataStorePrefix(), "calendar", "instance app should be preferred for prefix");

		const idHost = document.createElement("div") as any;
		idHost.id = "nm-id";
		idHost.getAttribute = (name : string) => name === "id" ? "nm-id" : null;
		idHost.getInstanceManager = () => ({});
		idHost.egw = () => ({app_name: () => ""});
		const idProvider = new Et2NextmatchDataProvider(idHost);
		assert.equal(idProvider.getDataStorePrefix(), "nm-id", "host id should be fallback prefix when app is unavailable");
	});
});
