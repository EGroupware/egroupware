import {assert} from "@open-wc/testing";
import {Et2Datagrid} from "../Et2Datagrid";
import {Et2RowProvider} from "../Et2RowProvider";
import {et2_arrayMgr} from "../../et2_core_arrayMgr";
import "../../Layout/Et2Box/Et2Box";

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

	/**
	 * Contract: a plain id in a row template binds the widget to that row field.
	 * Setup: prepare a row template with a top-level direct binding.
	 * Pass: preparation records the field and hydration supplies the row value.
	 */
	it("binds a top-level plain id to the matching row field", async() =>
	{
		const grid = createDatagrid();
		grid.columns = [{key: "id", title: "Id", width: "1fr"}] as any;
		const provider = new Et2RowProvider(grid as any);
		const rowNode = document.createElement("row");
		rowNode.innerHTML = `<td><et2-dg-row-object id="id"></et2-dg-row-object></td>`;

		const prepared = await (provider as any)._prepareRowTemplate(rowNode, grid.columns as any);
		const widgetId = prepared.template.content.querySelector("et2-dg-row-object")?.getAttribute("data-et2nm-id");
		assert.equal(prepared.fieldMap[widgetId!], "id", "plain id should be recorded as its own row field");

		grid.templateData = {
			columns: grid.columns,
			rowTemplate: prepared.template,
			rowTemplateXml: prepared.xml,
			rowTemplateAttrMap: prepared.attrMap,
			rowTemplateFieldMap: prepared.fieldMap,
			loaderTemplate: null
		} as any;
		const row = {id: "row-0", data: {id: 1, sub: {name: "cheese"}}};
		const rowElement = (grid as any)._buildRowElement(row, 0) as HTMLElement;

		assert.isTrue((grid as any)._applyRowElementAttributes(rowElement, row.data, 0));
		assert.equal((rowElement.querySelector("et2-dg-row-object") as Et2DatagridRowObjectFixture).value, 1);
	});

	/**
	 * Contract: an id on a container opens a namespace for its children instead of
	 * naming a value, so a plain id inside it binds the nested row field.
	 * Setup: prepare a row template with a widget inside <et2-vbox id="sub">.
	 * Pass: the container binds nothing and the child hydrates from rowData.sub.name.
	 */
	it("resolves a plain id inside a container through the container's namespace", async() =>
	{
		const grid = createDatagrid();
		grid.columns = [{key: "sub", title: "Sub", width: "1fr"}] as any;
		const provider = new Et2RowProvider(grid as any);
		const rowNode = document.createElement("row");
		rowNode.innerHTML = `<td><et2-vbox id="sub"><et2-dg-row-object id="name"></et2-dg-row-object></et2-vbox></td>`;

		const prepared = await (provider as any)._prepareRowTemplate(rowNode, grid.columns as any);
		const container = prepared.template.content.querySelector("et2-vbox") as HTMLElement;
		const containerId = container.getAttribute("data-et2nm-id");
		const widgetId = prepared.template.content.querySelector("et2-dg-row-object")?.getAttribute("data-et2nm-id");
		assert.equal(prepared.fieldMap[widgetId!], "sub.name", "child id should resolve through the container namespace");
		assert.isUndefined(containerId ? prepared.fieldMap[containerId] : undefined, "a namespace container binds no value of its own");

		grid.templateData = {
			columns: grid.columns,
			rowTemplate: prepared.template,
			rowTemplateXml: prepared.xml,
			rowTemplateAttrMap: prepared.attrMap,
			rowTemplateFieldMap: prepared.fieldMap,
			loaderTemplate: null
		} as any;
		const row = {id: "row-0", data: {id: 1, sub: {name: "cheese"}}};
		const rowElement = (grid as any)._buildRowElement(row, 0) as HTMLElement;

		assert.isTrue((grid as any)._applyRowElementAttributes(rowElement, row.data, 0));
		assert.equal((rowElement.querySelector("et2-dg-row-object") as Et2DatagridRowObjectFixture).value, "cheese");
	});

	/**
	 * Contract: only a container that carries an id opens a namespace.  An
	 * anonymous container is pure layout and must not touch the row-data path -
	 * this is the shape the example app's row template ships.
	 * Setup: prepare plain ids nested inside an <et2-vbox> with no id.
	 * Pass: the nested widgets bind their own top-level row fields, unprefixed.
	 * (A regression here would record ".host_created" or similar, binding nothing.)
	 */
	it("keeps plain ids top-level inside an anonymous container", async() =>
	{
		const grid = createDatagrid();
		grid.columns = [
			{key: "host_name", title: "Name", width: "1fr"},
			{key: "host_created", title: "Created", width: "1fr"}
		] as any;
		const provider = new Et2RowProvider(grid as any);
		const rowNode = document.createElement("row");
		rowNode.innerHTML = `
			<et2-dg-row-object id="host_name"></et2-dg-row-object>
			<et2-vbox>
				<et2-dg-row-object id="host_created"></et2-dg-row-object>
				<et2-vbox>
					<et2-dg-row-object id="host_creator"></et2-dg-row-object>
				</et2-vbox>
			</et2-vbox>
		`;

		const prepared = await (provider as any)._prepareRowTemplate(rowNode, grid.columns as any);
		assert.sameMembers(
			Object.values(prepared.fieldMap),
			["host_name", "host_created", "host_creator"],
			"anonymous containers, even nested, must contribute nothing to the row-data path"
		);

		grid.templateData = {
			columns: grid.columns,
			rowTemplate: prepared.template,
			rowTemplateXml: prepared.xml,
			rowTemplateAttrMap: prepared.attrMap,
			rowTemplateFieldMap: prepared.fieldMap,
			loaderTemplate: null
		} as any;
		const row = {id: "row-0", data: {host_name: "kelso", host_created: 1234, host_creator: 5}};
		const rowElement = (grid as any)._buildRowElement(row, 0) as HTMLElement;

		assert.isTrue((grid as any)._applyRowElementAttributes(rowElement, row.data, 0));
		const values = Array.from(rowElement.querySelectorAll("et2-dg-row-object"))
			.map((widget : any) => widget.value);
		assert.deepEqual(values, ["kelso", 1234, 5]);
	});

	/**
	 * Contract: whether an id opens a namespace is the widget class's own answer
	 * (`_createNamespace()`), not "does it have children".  Boxes scope their
	 * children; an ordinary widget does not, even when the template nests markup
	 * inside it, so its id still names a row value.
	 * Setup: give a non-namespace widget an element child, alongside a box.
	 * Pass: the widget binds its own field; only the box contributes a path segment.
	 */
	it("only namespace-opening widgets scope their children", async() =>
	{
		const grid = createDatagrid();
		grid.columns = [
			{key: "status", title: "Status", width: "1fr"},
			{key: "sub", title: "Sub", width: "1fr"}
		] as any;
		const provider = new Et2RowProvider(grid as any);
		const rowNode = document.createElement("row");
		rowNode.innerHTML = `
			<et2-dg-row-object id="status"><option value="1"></option></et2-dg-row-object>
			<et2-vbox id="sub"><et2-dg-row-object id="name"></et2-dg-row-object></et2-vbox>
		`;

		const prepared = await (provider as any)._prepareRowTemplate(rowNode, grid.columns as any);
		assert.sameMembers(
			Object.values(prepared.fieldMap),
			["status", "sub.name"],
			"a widget with children that does not create a namespace still binds its own field"
		);

		grid.templateData = {
			columns: grid.columns,
			rowTemplate: prepared.template,
			rowTemplateXml: prepared.xml,
			rowTemplateAttrMap: prepared.attrMap,
			rowTemplateFieldMap: prepared.fieldMap,
			loaderTemplate: null
		} as any;
		const row = {id: "row-0", data: {status: "open", sub: {name: "cheese"}}};
		const rowElement = (grid as any)._buildRowElement(row, 0) as HTMLElement;

		assert.isTrue((grid as any)._applyRowElementAttributes(rowElement, row.data, 0));
		const values = Array.from(rowElement.querySelectorAll("et2-dg-row-object"))
			.map((widget : any) => widget.value);
		assert.deepEqual(values, ["open", "cheese"]);
	});

	/**
	 * Contract: simple row descriptions are flattened to native text before they
	 * ever become widgets, so the namespace has to be resolved during preparation.
	 * Setup: prepare a plain <et2-description id="name"> inside <et2-vbox id="sub">.
	 * Pass: the flattened span renders the nested row value.
	 */
	it("resolves a namespaced plain id in a flattened row description", async() =>
	{
		const grid = createDatagrid();
		grid.columns = [{key: "sub", title: "Sub", width: "1fr"}] as any;
		const provider = new Et2RowProvider(grid as any);
		const rowNode = document.createElement("row");
		rowNode.innerHTML = `<td><et2-vbox id="sub"><et2-description id="name"></et2-description></et2-vbox></td>`;

		const prepared = await (provider as any)._prepareRowTemplate(rowNode, grid.columns as any);
		grid.templateData = {
			columns: grid.columns,
			rowTemplate: prepared.template,
			rowTemplateXml: prepared.xml,
			rowTemplateAttrMap: prepared.attrMap,
			rowTemplateFieldMap: prepared.fieldMap,
			loaderTemplate: null
		} as any;
		const row = {id: "row-0", data: {id: 1, sub: {name: "cheese"}}};
		const rowElement = (grid as any)._buildRowElement(row, 0) as HTMLElement;

		assert.equal(rowElement.querySelector("et2-vbox span")?.textContent, "cheese");
	});

	/**
	 * Contract: preparing a row template must not resolve its placeholders.  The
	 * prepared template is a prototype built once, before any rows are fetched, and
	 * reused for every row - so a namespaced description has to survive preparation
	 * with its placeholder intact and be resolved per row afterwards.  Expanding it
	 * at prototype time against an absent row would bake in an empty string and
	 * every row would render blank.
	 * Setup: prepare a namespaced description with no rows in play, inspect the
	 * prepared template, then build two different rows from that one template.
	 * Pass: the template still carries the literal `$[sub.name]`, and each row
	 * renders its own value.
	 */
	it("keeps namespaced placeholders unresolved in the prepared row template", async() =>
	{
		const grid = createDatagrid();
		grid.columns = [{key: "sub", title: "Sub", width: "1fr"}] as any;
		const provider = new Et2RowProvider(grid as any);
		const rowNode = document.createElement("row");
		rowNode.innerHTML = `<et2-vbox id="sub"><et2-description id="name"></et2-description></et2-vbox>`;

		const prepared = await (provider as any)._prepareRowTemplate(rowNode, grid.columns as any);
		assert.include(
			prepared.template.innerHTML,
			"$[sub.name]",
			"preparation must leave the placeholder for per-row resolution, not expand it against an absent row"
		);

		grid.templateData = {
			columns: grid.columns,
			rowTemplate: prepared.template,
			rowTemplateXml: prepared.xml,
			rowTemplateAttrMap: prepared.attrMap,
			rowTemplateFieldMap: prepared.fieldMap,
			loaderTemplate: null
		} as any;
		const first = (grid as any)._buildRowElement({id: "row-0", data: {sub: {name: "cheese"}}}, 0) as HTMLElement;
		const second = (grid as any)._buildRowElement({id: "row-1", data: {sub: {name: "pickle"}}}, 1) as HTMLElement;

		assert.equal(first.querySelector("et2-vbox span")?.textContent, "cheese");
		assert.equal(second.querySelector("et2-vbox span")?.textContent, "pickle",
			"one prepared template must serve every row independently");
	});

	/**
	 * Contract: a container namespace only scopes plain-id bindings.  Row
	 * expressions address the row itself, wherever they are written.
	 * Setup: put a ${row}[...] binding inside a container that carries an id.
	 * Pass: the expression still resolves against the top-level row field.
	 */
	it("leaves row expressions inside a namespace container addressing the row", async() =>
	{
		const grid = createDatagrid();
		grid.columns = [{key: "used", title: "Used", width: "1fr"}] as any;
		const provider = new Et2RowProvider(grid as any);
		const rowNode = document.createElement("row");
		rowNode.innerHTML = `<td><et2-vbox id="r_used_time"><et2-dg-rows-transform data-value="\${row}[used]"></et2-dg-rows-transform></et2-vbox></td>`;

		const prepared = await (provider as any)._prepareRowTemplate(rowNode, grid.columns as any);
		grid.templateData = {
			columns: grid.columns,
			rowTemplate: prepared.template,
			rowTemplateXml: prepared.xml,
			rowTemplateAttrMap: prepared.attrMap,
			rowTemplateFieldMap: prepared.fieldMap,
			loaderTemplate: null
		} as any;
		const row = {id: "row-0", data: {used: "2:30"}};
		const rowElement = (grid as any)._buildRowElement(row, 0) as HTMLElement;

		assert.isTrue((grid as any)._applyRowElementAttributes(rowElement, row.data, 0));
		assert.equal(rowElement.querySelector("et2-dg-rows-transform")?.getAttribute("data-value"), "2:30");
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
