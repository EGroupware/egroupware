import {assert} from "@open-wc/testing";
import {Et2Datagrid} from "../Et2Datagrid";

const egw = {
	debug: () => {},
	lang: (label : string) => label,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	preference: () => null,
	set_preference: () => {},
	app_name: () => "addressbook"
};
let preferenceCalls : { app : string; key : string; value : any }[] = [];
egw.set_preference = (app : string, key : string, value : any) =>
{
	preferenceCalls.push({app, key, value});
};

window.egw = function() { return egw; } as any;
Object.assign(window.egw, egw);

function createDatagridDataProvider(overrides : Record<string, any> = {}, prefix : string = "addressbook")
{
	return {
		fetchPage: async() => ({rows: [], total: 0}),
		getDataStorePrefix: () => prefix,
		normalizeRowId: (rowId : string | number, ensurePrefix? : boolean) =>
		{
			const normalized = String(rowId ?? "");
			return ensurePrefix && !normalized.startsWith(`${prefix}::`) ? `${prefix}::${normalized}` : normalized;
		},
		toProviderRowId: (rowId : string) => rowId.replace(new RegExp(`^${prefix}::`), ""),
		refresh: async() => ({rows: [], removedRowIds: []}),
		...overrides
	};
}

function createDatagrid() : Et2Datagrid
{
	const el = new Et2Datagrid();
	el.dataProvider = createDatagridDataProvider() as any;
	return el;
}

describe("Et2Datagrid column preferences", () =>
{
	it("uses source column order to align row cells after preference reordering", () =>
	{
		const el = createDatagrid();
		const rowTemplate = document.createElement("template");
		rowTemplate.innerHTML = `
			<tr>
				<td>A cell</td>
				<td>B cell</td>
				<td>C cell</td>
			</tr>
		`;

		el.columns = [
			{key: "b", title: "B"},
			{key: "a", title: "A"},
			{key: "c", title: "C"}
		] as any;
		const sourceColumns = [
			{key: "a", title: "A"},
			{key: "b", title: "B"},
			{key: "c", title: "C"}
		];
		el.templateData = {
			columns: el.columns,
			sourceColumns,
			rowTemplate,
			rowTemplateXml: null,
			rowTemplateAttrMap: {},
			loaderTemplate: null
		} as any;
		(el as any).willUpdate(new Map([["templateData", null]]));

		const rowElement = (el as any)._buildRowElement({id: "row-0", data: {}}, 0) as HTMLTableRowElement | null;
		assert.isNotNull(rowElement, "row should be built from template");

		const visibleCells = Array.from(rowElement!.querySelectorAll(":scope > td:not([data-dg-meta-cell])")) as HTMLElement[];
		assert.deepEqual(
			visibleCells.map((cell) => cell.getAttribute("data-col-key")),
			["b", "a", "c"],
			"row cells should be reordered to visible column order"
		);
		assert.deepEqual(
			visibleCells.map((cell) => cell.textContent?.trim()),
			["B cell", "A cell", "C cell"],
			"cell contents should stay associated with their original source columns"
		);
	});

	it("derives default structured preference key from owner tag and row template id", () =>
	{
		preferenceCalls = [];
		const host = document.createElement("et2-nextmatch");
		const el = createDatagrid();
		host.attachShadow({mode: "open"}).appendChild(el);

		el.templateData = {
			columns: [{key: "a", title: "A", width: "1fr"}],
			rowTemplateId: "addressbook.index.rows",
			rowTemplate: null,
			rowTemplateXml: null,
			rowTemplateAttrMap: {},
			loaderTemplate: null
		} as any;
		el.columns = [{key: "a", title: "A", width: "1fr"}] as any;
		(el as any)._persistColumnPreferences();

		const structuredPreference = preferenceCalls.find((call) => call.key === "nextmatch-addressbook.index.rows-prefs");
		assert.isDefined(structuredPreference, "structured preference should be saved on column change");
		assert.equal(structuredPreference!.app, "addressbook", "app name should come from egw app context");
	});

	it("uses explicit columnPreferenceName override when provided", () =>
	{
		preferenceCalls = [];
		const host = document.createElement("et2-nextmatch");
		const el = createDatagrid();
		el.columnPreferenceName = "my-custom-key";
		host.attachShadow({mode: "open"}).appendChild(el);

		el.templateData = {
			columns: [{key: "a", title: "A", width: "1fr"}],
			rowTemplateId: "addressbook.index.rows",
			rowTemplate: null,
			rowTemplateXml: null,
			rowTemplateAttrMap: {},
			loaderTemplate: null
		} as any;
		el.columns = [{key: "a", title: "A", width: "1fr"}] as any;
		(el as any)._persistColumnPreferences();

		const structuredPreference = preferenceCalls.find((call) => call.key === "my-custom-key");
		assert.isDefined(structuredPreference, "custom key override should be used for structured preference");
	});
});
