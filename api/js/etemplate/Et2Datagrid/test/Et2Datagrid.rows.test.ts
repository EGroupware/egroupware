import {assert} from "@open-wc/testing";
import {Et2Datagrid} from "../Et2Datagrid";
import {et2_arrayMgr} from "../../et2_core_arrayMgr";

function createDatagrid() : Et2Datagrid
{
	const grid = new Et2Datagrid();
	grid.dataProvider = {
		fetchPage: async() => ({rows: [], total: 0}),
		getDataStorePrefix: () => "rows-test",
		normalizeRowId: (id : string | number) => String(id),
		toProviderRowId: (id : string) => id,
		refresh: async() => ({rows: [], removedRowIds: []})
	} as any;
	return grid;
}

class Et2DatagridRowsTransform extends HTMLElement
{
	private _mgr : any;

	setArrayMgr(_name : string, mgr : any)
	{
		this._mgr = mgr;
	}

	transformAttributes(attrs : Record<string, any>)
	{
		for(const [attribute, value] of Object.entries(attrs))
		{
			const resolved = typeof value === "string" && value.includes("$") ? this._mgr?.expandName(value) : value;
			this.setAttribute(attribute, String(resolved ?? ""));
		}
	}
}

if(!customElements.get("et2-dg-rows-transform"))
{
	customElements.define("et2-dg-rows-transform", Et2DatagridRowsTransform);
}

class Et2DatagridRowObjectFixture extends HTMLElement
{
	public value : any = undefined;
	public label : any = undefined;

	set_value(value : any)
	{
		this.value = value;
	}

	transformAttributes(attrs : Record<string, any>)
	{
		Object.assign(this, attrs);
	}
}

if(!customElements.get("et2-dg-row-object"))
{
	customElements.define("et2-dg-row-object", Et2DatagridRowObjectFixture);
}

describe("Et2Datagrid row hydration", () =>
{
	it("hydrates row-bound widget attributes", () =>
	{
		const grid = createDatagrid();
		grid.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		const rowTemplate = document.createElement("template");
		rowTemplate.innerHTML = `<tr><td><et2-dg-rows-transform data-et2nm-id="w1"></et2-dg-rows-transform></td></tr>`;
		grid.templateData = {
			columns: grid.columns,
			rowTemplate,
			rowTemplateXml: null,
			rowTemplateAttrMap: {w1: {"data-value": "$row.label"}},
			loaderTemplate: null
		} as any;
		const row = {id: "row-0", data: {label: "Row 0"}};
		const rowElement = (grid as any)._buildRowElement(row, 0) as HTMLElement;

		assert.isTrue((grid as any)._applyRowElementAttributes(rowElement, row.data, 0));
		assert.equal(rowElement.querySelector("et2-dg-rows-transform")?.getAttribute("data-value"), "Row 0");
	});

	it("hydrates ordinary row attributes without a content perspective", () =>
	{
		const grid = createDatagrid();
		const contentMgr = new et2_arrayMgr({});
		let perspectiveCalls = 0;
		const openPerspective = contentMgr.openPerspective.bind(contentMgr);
		contentMgr.openPerspective = (...args : any[]) =>
		{
			perspectiveCalls++;
			return openPerspective(...args);
		};
		grid.setArrayMgr("content", contentMgr);
		grid.columns = [{key: "note", title: "Note", width: "1fr"}] as any;
		const rowTemplate = document.createElement("template");
		rowTemplate.innerHTML = `<tr><td><et2-dg-rows-transform data-et2nm-id="w1"></et2-dg-rows-transform></td></tr>`;
		grid.templateData = {columns: grid.columns, rowTemplate, rowTemplateXml: null, rowTemplateAttrMap: {w1: {"data-value": "$row_cont[note]"}}, loaderTemplate: null} as any;
		const row = {id: "row-0", data: {note: "Direct row value"}};
		const rowElement = (grid as any)._buildRowElement(row, 0) as HTMLElement;

		assert.isTrue((grid as any)._applyRowElementAttributes(rowElement, row.data, 0));
		assert.equal(rowElement.querySelector("et2-dg-rows-transform")?.getAttribute("data-value"), "Direct row value");
		assert.equal(perspectiveCalls, 0);
	});

	it("resolves direct bindings in arbitrary widget attributes", () =>
	{
		const grid = createDatagrid();
		grid.columns = [{key: "value", title: "Value", width: "1fr"}] as any;
		const rowTemplate = document.createElement("template");
		rowTemplate.innerHTML = `<tr><td><et2-dg-rows-transform data-et2nm-id="w1"></et2-dg-rows-transform></td></tr>`;
		grid.templateData = {
			columns: grid.columns,
			rowTemplate,
			rowTemplateXml: null,
			rowTemplateAttrMap: {
				w1: {
					"data-value": "$value",
					"aria-label": "$label",
					title: "Record $label",
					"data-nested": "$[meta.code]"
				}
			},
			loaderTemplate: null
		} as any;
		const row = {id: "row-0", data: {value: "42", label: "Example", meta: {code: "A-1"}}};
		const rowElement = (grid as any)._buildRowElement(row, 0) as HTMLElement;

		assert.isTrue((grid as any)._applyRowElementAttributes(rowElement, row.data, 0));
		const widget = rowElement.querySelector("et2-dg-rows-transform") as HTMLElement;
		assert.equal(widget.getAttribute("data-value"), "42");
		assert.equal(widget.getAttribute("aria-label"), "Example");
		assert.equal(widget.getAttribute("title"), "Record Example");
		assert.equal(widget.getAttribute("data-nested"), "A-1");
	});

	it("keeps null row fields empty and supplies $row as the row object", () =>
	{
		const grid = createDatagrid();
		grid.columns = [{key: "name", title: "Name", width: "1fr"}] as any;
		const rowTemplate = document.createElement("template");
		rowTemplate.innerHTML = `<tr><td><et2-dg-row-object data-et2nm-id="row-object"></et2-dg-row-object></td></tr>`;
		grid.templateData = {
			columns: grid.columns,
			rowTemplate,
			rowTemplateXml: null,
			rowTemplateAttrMap: {"row-object": {id: "$row", label: "$row_cont[missing]"}},
			loaderTemplate: null
		} as any;
		const row = {id: "row-0", data: {name: "report.txt", missing: null}};
		const rowElement = (grid as any)._buildRowElement(row, 0) as HTMLElement;

		assert.isTrue((grid as any)._applyRowElementAttributes(rowElement, row.data, 0));
		const widget = rowElement.querySelector("et2-dg-row-object") as Et2DatagridRowObjectFixture;
		assert.equal(widget.value, row.data, "$row should preserve the row object for specialized renderers");
		assert.equal(widget.label, "", "null fields should hydrate as blank values");
	});
});
