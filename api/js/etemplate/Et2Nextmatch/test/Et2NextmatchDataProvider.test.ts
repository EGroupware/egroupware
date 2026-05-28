import {assert} from "@open-wc/testing";
import {Et2NextmatchDataProvider} from "../Et2NextmatchDataProvider";

describe("Et2NextmatchDataProvider additional sel_options handling", () =>
{
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
