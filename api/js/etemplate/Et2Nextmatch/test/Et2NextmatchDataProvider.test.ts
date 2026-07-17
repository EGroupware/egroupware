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
			["addressbook::uid-1", "addressbook::uid-2", "addressbook::uid-3"],
			"row order should match server `order` list, not callback completion order"
		);
		assert.equal(page.total, 3, "total should come from response");
	});

	/**
	 * Contract under test:
	 * - Child providers fetch through the normal Nextmatch path with an added `parent_id` range value.
	 *
	 * Setup strategy:
	 * - Create a root provider and child provider from a datastore-prefixed parent id.
	 * - Capture the request passed to `dataFetch()`.
	 *
	 * Pass criteria:
	 * - The request includes start, num_rows, and raw provider parent id.
	 * - Returned child rows still use UID registration and server order.
	 */
	it("fetches child pages with parent_id using the normal row resolution flow", async() =>
	{
		let capturedRequest : any = null;
		const host = createProviderHost({
			id: "nm-child",
			egw: () => ({
				app_name: () => "addressbook",
				dataFetch: (_execId, request, _filters, _widgetId, callback) =>
				{
					capturedRequest = {...request};
					callback({
						rows: {},
						order: ["addressbook::child-1"],
						total: 1
					});
				},
				dataRegisterUID: (uid : string, callback : Function) =>
				{
					callback({title: "Child row"}, uid);
				}
			})
		});

		const provider = new Et2NextmatchDataProvider(host);
		const childProvider = provider.createChildProvider("addressbook::parent-7");
		const page = await childProvider.fetchPage(50, 25);

		assert.deepEqual(
			capturedRequest,
			{start: 50, num_rows: 25, parent_id: "parent-7"},
			"child fetch should send the raw parent id expected by Nextmatch.php"
		);
		assert.deepEqual(page.rows.map((row) => row.id), ["addressbook::child-1"]);
		assert.equal(page.total, 1);
	});

	/**
	 * Contract under test:
	 * - Child provider query signatures include both current filters and the parent id.
	 *
	 * Setup strategy:
	 * - Mutate the host filters after creating the child provider.
	 *
	 * Pass criteria:
	 * - The signature changes with current filters.
	 * - A different parent id produces a different signature.
	 */
	it("includes parent id and current filters in child query signatures", () =>
	{
		const host = createProviderHost({id: "nm-child-signature"});
		host._filters = {col_filter: {status: "open"}};
		const provider = new Et2NextmatchDataProvider(host);
		const childProvider = provider.createChildProvider("addressbook::parent-1");
		const initialSignature = childProvider.getQuerySignature!();

		host._filters = {col_filter: {status: "closed"}};
		assert.notEqual(
			childProvider.getQuerySignature!(),
			initialSignature,
			"child signature should use filters at call time"
		);
		assert.notEqual(
			provider.createChildProvider("addressbook::parent-2").getQuerySignature!(),
			childProvider.getQuerySignature!(),
			"different parent ids should not share child query signatures"
		);
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


	// ============================================================================
	// EDGE CASE TESTS
	// ============================================================================

	/**
	 * Contract under test:
	 * - After first refresh completes, a new refresh call should fetch fresh data from server.
	 *
	 * Setup strategy:
	 * - Complete one refresh, then immediately call refresh again.
	 * - Track dataFetch calls to verify second refresh also fetches.
	 *
	 * Pass criteria:
	 * - First refresh completes and resolves.
	 * - Second refresh is NOT reused from first promise.
	 * - Total of two dataFetch calls.
	 */
	it("allows fresh refresh after first refresh completes (no stale reuse)", async() =>
	{
		let fetchCalls = 0;
		const host = createProviderHost({
			id: "nm-refresh-fresh",
			egw: () => ({
				app_name: () => "addressbook",
				dataGetUIDdata: (uid : string) => ({
					timestamp: Date.now(),
					data: {uid, title: `refresh-${fetchCalls}`}
				}),
				dataFetch: (_execId, _request, _filters, _widgetId, callback) =>
				{
					fetchCalls++;
					callback({rows: {}, total: 1});
				},
				dataRegisterUID: () => {}
			})
		});

		const provider = new Et2NextmatchDataProvider(host);

		// First refresh completes
		const result1 = await provider.refresh(["99"], "update");
		assert.equal(fetchCalls, 1, "first refresh should fetch");
		assert.equal(result1.rows[0].data.title, "refresh-1", "first refresh gets first fetch result");

		// Second refresh (after first completes) should also fetch
		const result2 = await provider.refresh(["99"], "update");
		assert.equal(fetchCalls, 2, "second refresh should fetch again (not reuse old promise)");
		assert.equal(result2.rows[0].data.title, "refresh-2", "second refresh gets fresh fetch result");
	});

	/**
	 * Contract under test:
	 * - Calling refresh with empty array should return empty results without fetching.
	 *
	 * Setup strategy:
	 * - Call refresh([]) with no row IDs.
	 *
	 * Pass criteria:
	 * - No dataFetch calls occur.
	 * - Returns {rows: [], removedRowIds: []} immediately.
	 */
	it("handles empty refresh array without fetching", async() =>
	{
		let fetchCalls = 0;
		const host = createProviderHost({
			id: "nm-refresh-empty",
			egw: () => ({
				app_name: () => "addressbook",
				dataFetch: () =>
				{
					fetchCalls++;
				},
				dataRegisterUID: () => {}
			})
		});

		const provider = new Et2NextmatchDataProvider(host);
		const result = await provider.refresh([], "update");

		assert.equal(fetchCalls, 0, "no fetch should occur for empty array");
		assert.deepEqual(result.rows, []);
		assert.deepEqual(result.removedRowIds, []);
	});

	/**
	 * Contract under test:
	 * - If host is destroyed during refresh (getParent returns null), resolve gracefully.
	 *
	 * Setup strategy:
	 * - Create host with getParent that returns null during callback.
	 * - Stub dataFetch to delay callback and remove host.
	 *
	 * Pass criteria:
	 * - No errors thrown.
	 * - Returns empty results instead of crashing.
	 */
	it("handles host destruction during in-flight refresh", async() =>
	{
		let releaseFetch : (() => void) | null = null;
		const host = createProviderHost({
			id: "nm-refresh-destroyed",
			getParent: function()
			{
				// Simulate destroyed host
				return null;
			},
			egw: () => ({
				app_name: () => "addressbook",
				dataGetUIDdata: () => ({
					timestamp: Date.now(),
					data: {uid: "addressbook::99", title: "Destroyed"}
				}),
				dataFetch: (_execId, _request, _filters, _widgetId, callback) =>
				{
					releaseFetch = () =>
					{
						callback({rows: {}, total: 1});
					};
				},
				dataRegisterUID: () => {}
			})
		});

		const provider = new Et2NextmatchDataProvider(host);
		const refreshPromise = provider.refresh(["99"], "update");

		// Release the fetch while host is destroyed
		releaseFetch?.();
		const result = await refreshPromise;

		assert.deepEqual(result.rows, [], "destroyed host refresh returns empty rows");
		assert.deepEqual(result.removedRowIds, [], "destroyed host refresh returns no removals");
	});

	/**
	 * Contract under test:
	 * - Refreshing multiple different rows should create separate server requests (not over-deduplicate).
	 *
	 * Setup strategy:
	 * - Refresh 5 different rows in quick succession.
	 * - Track dataFetch calls.
	 *
	 * Pass criteria:
	 * - Five dataFetch calls are made (one per row).
	 * - All rows appear in results.
	 */
	it("refreshes multiple different rows without over-deduplicating", async() =>
	{
		let fetchCalls : string[] = [];
		const cache : Record<string, any> = {};

		const host = createProviderHost({
			id: "nm-refresh-batch",
			egw: () => ({
				app_name: () => "addressbook",
				dataGetUIDdata: (uid : string) => cache[uid] ?? null,
				dataFetch: (_execId, request, _filters, _widgetId, callback) =>
				{
					const rowId = request.refresh?.[0];
					fetchCalls.push(rowId);
					if(rowId)
					{
						cache[`addressbook::${rowId}`] = {
							timestamp: Date.now(),
							data: {uid: `addressbook::${rowId}`, title: `Row ${rowId}`}
						};
					}
					callback({rows: {}, total: 1});
				},
				dataRegisterUID: () => {}
			})
		});

		const provider = new Et2NextmatchDataProvider(host);
		const results = await Promise.all([
			provider.refresh(["1"], "update"),
			provider.refresh(["2"], "update"),
			provider.refresh(["3"], "update"),
			provider.refresh(["4"], "update"),
			provider.refresh(["5"], "update")
		]);

		assert.equal(fetchCalls.length, 5, "should make 5 separate fetch calls");
		assert.deepEqual(fetchCalls.sort(), ["1", "2", "3", "4", "5"], "each row fetched exactly once");

		const mergedRows = results.flatMap(r => r.rows);
		assert.equal(mergedRows.length, 5, "all 5 rows should be in results");
	});

	/**
	 * Contract under test:
	 * - If dataFetch throws an exception, refresh promise should reject with that error.
	 *
	 * Setup strategy:
	 * - dataFetch throws an error.
	 *
	 * Pass criteria:
	 * - Refresh promise rejects.
	 * - Error message is preserved.
	 */
	it("rejects refresh promise if dataFetch throws", async() =>
	{
		const testError = new Error("Server connection failed");
		const host = createProviderHost({
			id: "nm-refresh-error",
			egw: () => ({
				app_name: () => "addressbook",
				dataFetch: () =>
				{
					throw testError;
				},
				dataRegisterUID: () => {}
			})
		});

		const provider = new Et2NextmatchDataProvider(host);

		try
		{
			await provider.refresh(["99"], "update");
			assert.fail("refresh should have rejected");
		}
		catch(e)
		{
			assert.equal((e as Error).message, "Server connection failed");
		}
	});

	/**
	 * Contract under test:
	 * - Multiple concurrent refreshes with different update types work independently.
	 *
	 * Setup strategy:
	 * - Refresh row A with "update" and row B with "delete" simultaneously.
	 * - Verify both complete with correct type in context.
	 *
	 * Pass criteria:
	 * - Two fetch calls with different types.
	 * - Both results have correct data.
	 */
	it("handles multiple concurrent refreshes with different update types", async() =>
	{
		const calls : any[] = [];
		const cache : Record<string, any> = {
			"calendar::1": {timestamp: Date.now(), data: {uid: "calendar::1", title: "Event A"}},
			"calendar::2": {timestamp: Date.now(), data: {uid: "calendar::2", title: "Event B"}}
		};

		const host = createProviderHost({
			id: "nm-refresh-multi-type",
			getInstanceManager: () => ({etemplate_exec_id: "exec-1", app: "calendar"}),
			egw: () => ({
				app_name: () => "calendar",
				dataGetUIDdata: (uid : string) => cache[uid] ?? null,
				dataFetch: (_execId, request, _filters, _widgetId, callback, context) =>
				{
					calls.push({request, context});
					callback({rows: {}, total: 1});
				},
				dataRegisterUID: () => {}
			})
		});

		const provider = new Et2NextmatchDataProvider(host);
		const [result1, result2] = await Promise.all([
			provider.refresh(["1"], "update"),
			provider.refresh(["2"], "delete")
		]);

		assert.equal(calls.length, 2, "two fetch calls");
		assert.equal(calls[0].context.type, "update");
		assert.equal(calls[1].context.type, "delete");
		assert.equal(result1.rows[0].id, "calendar::1");
		assert.equal(result2.rows[0].id, "calendar::2");
	});

	/**
	 * Contract under test:
	 * - Row ID normalization works consistently across different input formats.
	 *
	 * Setup strategy:
	 * - Call normalizeRowId with various formats: bare id, prefixed id, number, string.
	 *
	 * Pass criteria:
	 * - All equivalent inputs normalize to same value.
	 */
	it("normalizes various row ID formats consistently", () =>
	{
		const host = createProviderHost({
			id: "nm-normalize",
			getInstanceManager: () => ({app: "infolog"})
		});

		const provider = new Et2NextmatchDataProvider(host);

		// Same row, different input formats
		const normalized1 = provider.normalizeRowId("42", true);
		const normalized2 = provider.normalizeRowId("infolog::42", true);

		assert.equal(normalized1, normalized2, "bare id and prefixed id should normalize the same");
		assert.equal(normalized1, "infolog::42", "should include app prefix");
	});

	// ============================================================================
	// ADDITIONAL DATA PROCESSING TESTS
	// ============================================================================

	/**
	 * Contract under test:
	 * - Multiple sel_options keys in response are all applied to widgets.
	 *
	 * Setup strategy:
	 * - Response includes sel_options for multiple widget IDs.
	 * - Stub getWidgetById to return widgets for each.
	 *
	 * Pass criteria:
	 * - Each widget's set_select_options is called with correct data.
	 * - Array manager is updated for each.
	 */
	it("applies multiple sel_options from response to different widgets", async() =>
	{
		const updatedWidgets : Record<string, any> = {};
		const arrayMgrs : Record<string, any> = {
			filter1: {data: {}},
			filter2: {data: {}},
			sel_options: {data: {}}
		};

		const host = createProviderHost({
			id: "nm-multi-options",
			getArrayMgr: (name : string) => arrayMgrs[name] || {data: {}},
			getWidgetById: (id : string) =>
			{
				if(id === "filter1" || id === "filter2")
				{
					return {
						value: "default",
						set_select_options: function(opts : any)
						{
							updatedWidgets[id] = opts;
							this.value = "default";
						}
					};
				}
				return null;
			},
			egw: () => ({
				app_name: () => "addressbook",
				dataFetch: (_execId, _request, _filters, _widgetId, callback) =>
				{
					callback({
						rows: {
							sel_options: {
								filter1: {opt1: "Option 1", opt2: "Option 2"},
								filter2: {optA: "Option A", optB: "Option B"}
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

		assert.deepEqual(updatedWidgets.filter1, {opt1: "Option 1", opt2: "Option 2"});
		assert.deepEqual(updatedWidgets.filter2, {optA: "Option A", optB: "Option B"});
		assert.deepEqual(arrayMgrs.sel_options.data.filter1, {opt1: "Option 1", opt2: "Option 2"});
		assert.deepEqual(arrayMgrs.sel_options.data.filter2, {optA: "Option A", optB: "Option B"});
	});

	/**
	 * Contract under test:
	 * - If sel_options references a widget that doesn't exist, should not crash.
	 *
	 * Setup strategy:
	 * - Response includes sel_options for non-existent widget.
	 *
	 * Pass criteria:
	 * - No errors thrown.
	 * - Array manager is still updated.
	 * - Page fetch completes successfully.
	 */
	it("handles sel_options for non-existent widgets gracefully", async() =>
	{
		const arrayMgrs : Record<string, any> = {
			sel_options: {data: {}}
		};

		const host = createProviderHost({
			id: "nm-missing-widget",
			getArrayMgr: (name : string) => arrayMgrs[name] || {data: {}},
			getWidgetById: () => null,  // All widgets missing
			egw: () => ({
				app_name: () => "addressbook",
				dataFetch: (_execId, _request, _filters, _widgetId, callback) =>
				{
					callback({
						rows: {
							sel_options: {
								nonexistent_widget: {opt: "value"}
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
		const page = await provider.fetchPage(0, 25);

		assert.deepEqual(page.rows, []);
		assert.deepEqual(arrayMgrs.sel_options.data.nonexistent_widget, {opt: "value"});
	});

	/**
	 * Contract under test:
	 * - Numeric row keys in the legacy `rows` payload are row data, not additional metadata.
	 *
	 * Setup strategy:
	 * - Response includes numeric row keys plus additional sel_options.
	 * - Track content manager writes while fetching an empty ordered page.
	 *
	 * Pass criteria:
	 * - Numeric keys are ignored by additional-data handling.
	 * - Real additional data is still applied.
	 */
	it("ignores numeric row keys when processing additional response data", async() =>
	{
		const contentData : Record<string, any> = {};
		const selOptionsData : Record<string, any> = {};
		const host = createProviderHost({
			id: "nm-numeric-row-keys",
			getArrayMgr: (name : string) =>
			{
				if(name === "content")
				{
					return {data: contentData, getEntry: (key : string) => contentData[key]};
				}
				if(name === "sel_options")
				{
					return {data: selOptionsData};
				}
				return {data: {}};
			},
			getParent: () => ({getArrayMgr: () => ({data: selOptionsData})}),
			egw: () => ({
				app_name: () => "addressbook",
				dataFetch: (_execId, _request, _filters, _widgetId, callback) =>
				{
					callback({
						rows: {
							0: {id: "addressbook::1", data: {n_fn: "Ada Lovelace"}},
							sel_options: {
								filter: {"": "All"}
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

		assert.notProperty(contentData, "0", "numeric row key should not be copied into content metadata");
		assert.deepEqual(selOptionsData.filter, {"": "All"}, "non-row additional data should still be applied");
	});

// ============================================================================
// DATAGRID INTEGRATION TESTS
// ============================================================================

	/**
	 * Contract under test:
	 * - Refresh results always have correct structure for datagrid consumption {id, data}.
	 *
	 * Setup strategy:
	 * - Refresh a row with various scenarios.
	 *
	 * Pass criteria:
	 * - All rows in results have id and data properties.
	 * - Data contains the row payload from cache.
	 */
	it("returns refresh results with correct structure for datagrid", async() =>
	{
		const host = createProviderHost({
			id: "nm-result-format",
			egw: () => ({
				app_name: () => "timesheet",
				dataGetUIDdata: (uid : string) => ({
					timestamp: Date.now(),
					data: {
						uid,
						ts_id: "123",
						ts_title: "My Entry",
						ts_start: "2026-06-01",
						ts_duration: "2.5",
						ts_description: "Detailed work description"
					}
				}),
				dataFetch: (_execId, _request, _filters, _widgetId, callback) =>
				{
					callback({rows: {}, total: 1});
				},
				dataRegisterUID: () => {}
			})
		});

		const provider = new Et2NextmatchDataProvider(host);
		const result = await provider.refresh(["123"], "update");

		assert.equal(result.rows.length, 1);
		const row = result.rows[0];
		assert.isNotEmpty(row.id);
		assert.isObject(row.data);
		assert.equal(row.data.ts_id, "123");
		assert.equal(row.data.ts_title, "My Entry");
		assert.isArray(result.removedRowIds);
	});

	/**
	 * Contract under test:
	 * - Result format is consistent across different update types.
	 *
	 * Setup strategy:
	 * - Refresh with update, delete, add types.
	 *
	 * Pass criteria:
	 * - All results have {rows, removedRowIds} structure.
	 * - Results are predictable based on server response.
	 */
	it("maintains consistent result structure across all update types", async() =>
	{
		const cache : Record<string, any> = {
			"mail::1": {timestamp: Date.now(), data: {uid: "mail::1", subject: "Email 1"}}
		};

		const host = createProviderHost({
			id: "nm-consistent-structure",
			getInstanceManager: () => ({etemplate_exec_id: "exec-1", app: "mail"}),
			egw: () => ({
				app_name: () => "mail",
				dataGetUIDdata: (uid : string) => cache[uid] ?? null,
				dataFetch: (_execId, request, _filters, _widgetId, callback, context) =>
				{
					// For delete type, simulate row no longer exists
					const total = request.refresh?.[0] === "1" && context.type === "delete" ? 0 : 1;
					callback({rows: {}, total});
				},
				dataRegisterUID: () => {}
			})
		});

		const provider = new Et2NextmatchDataProvider(host);

		const updateResult = await provider.refresh(["1"], "update");
		const deleteResult = await provider.refresh(["1"], "delete");

		// Both should have the structure
		assert.hasAllKeys(updateResult, ["rows", "removedRowIds"]);
		assert.hasAllKeys(deleteResult, ["rows", "removedRowIds"]);

		// Update should have row, delete should have removal
		assert.equal(updateResult.rows.length, 1);
		assert.equal(deleteResult.removedRowIds.length, 1);
	});

});
