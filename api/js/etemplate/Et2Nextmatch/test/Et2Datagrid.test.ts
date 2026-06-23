import {assert} from "@open-wc/testing";
import {html, LitElement} from "lit";
import {Et2Datagrid} from "../Et2Datagrid";
import datagridStyles from "../Et2Datagrid.styles.ts";
import {Et2RowProvider} from "../Et2RowProvider";
import {et2_arrayMgr} from "../../et2_core_arrayMgr";
import {Et2CustomfieldsBase} from "../../Et2Customfields/Et2CustomfieldsBase";
import "../../Et2Customfields/Et2CustomfieldsList";
import "../../Et2Customfields/Et2CustomfieldsListRow";

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

class Et2DatagridTestTransform extends HTMLElement
{
	private _mgr : any;
	public lastTransformedAttrs : Record<string, string> | null = null;

	setArrayMgr(_name : string, mgr : any)
	{
		this._mgr = mgr;
	}

	transformAttributes(attrs : Record<string, string>)
	{
		this.lastTransformedAttrs = {...attrs};
		const raw = attrs["data-value"] || "";
		const shouldExpand = raw.includes("$") || raw.includes("{");
		const resolved = shouldExpand && this._mgr?.expandName ? this._mgr.expandName(raw) : raw;
		this.setAttribute("data-value", String(resolved ?? ""));
		this.textContent = String(resolved ?? "");
	}
}

if(!customElements.get("et2-dg-test-transform"))
{
	customElements.define("et2-dg-test-transform", Et2DatagridTestTransform);
}

class Et2DatagridMgrProbe extends HTMLElement
{
	private _mgrs : Record<string, any> = {};

	setArrayMgrs(mgrs : Record<string, any>)
	{
		this._mgrs = mgrs;
	}

	setArrayMgr(name : string, mgr : any)
	{
		this._mgrs[name] = mgr;
	}

	transformAttributes(attrs : Record<string, string>)
	{
		const customfields = this._mgrs.modifications?.getRoot?.()?.getEntry("~custom_fields~", true)?.customfields || {};
		const value = this._mgrs.content?.expandName?.(attrs["data-value"] || "");
		this.setAttribute("data-fields", Object.keys(customfields).join(","));
		this.setAttribute("data-value", String(value ?? ""));
	}

	loadFromXML()
	{
	}
}

if(!customElements.get("et2-dg-mgr-probe"))
{
	customElements.define("et2-dg-mgr-probe", Et2DatagridMgrProbe);
}

class Et2DatagridAlignmentFixture extends LitElement
{
	static styles = datagridStyles;

	render()
	{
		return html`
			<div class="dg-root" style="--meta-column-width: 24px; --column-sizes: 140px 1fr; --column-count: 2;">
				<div class="dg-header">
					<div class="dg-col dg-col--lead" data-column-key="name">Name</div>
					<div class="dg-col" data-column-key="email">Email</div>
				</div>
				<div class="dg-body">
					<table>
						<tbody id="rows">
							<tr data-row-id="row-0">
								<td data-dg-meta-cell="1"></td>
								<td data-col-key="name">Ada</td>
								<td data-col-key="email">ada@example.test</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		`;
	}
}

if(!customElements.get("et2-dg-alignment-fixture"))
{
	customElements.define("et2-dg-alignment-fixture", Et2DatagridAlignmentFixture);
}

let resizeObserverErrorHandler : ((event : ErrorEvent) => void) | null = null;
let resizeObserverRejectionHandler : ((event : PromiseRejectionEvent) => void) | null = null;
let originalResizeObserver : typeof window.ResizeObserver | undefined;
before(() =>
{
	originalResizeObserver = window.ResizeObserver;
	class ResizeObserverStub
	{
		observe() {}
		unobserve() {}
		disconnect() {}
	}
	window.ResizeObserver = ResizeObserverStub as any;

	resizeObserverErrorHandler = (event : ErrorEvent) =>
	{
		const message = String(event?.message || "");
		if(message.includes("ResizeObserver loop completed with undelivered notifications"))
		{
			event.preventDefault();
			event.stopImmediatePropagation?.();
		}
	};
	window.addEventListener("error", resizeObserverErrorHandler, true);
	resizeObserverRejectionHandler = (event : PromiseRejectionEvent) =>
	{
		const message = String((event?.reason && (event.reason.message || event.reason)) || "");
		if(message.includes("ResizeObserver loop completed with undelivered notifications"))
		{
			event.preventDefault();
		}
	};
	window.addEventListener("unhandledrejection", resizeObserverRejectionHandler, true);
});

after(() =>
{
	if(resizeObserverErrorHandler)
	{
		window.removeEventListener("error", resizeObserverErrorHandler, true);
		resizeObserverErrorHandler = null;
	}
	if(resizeObserverRejectionHandler)
	{
		window.removeEventListener("unhandledrejection", resizeObserverRejectionHandler, true);
		resizeObserverRejectionHandler = null;
	}
	if(originalResizeObserver)
	{
		window.ResizeObserver = originalResizeObserver;
	}
});

describe("Et2Datagrid row rendering", () =>
{
	it("aligns visible headers with body cells when meta column has width", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "240px";
		host.style.width = "500px";
		document.body.appendChild(host);

		const el = document.createElement("et2-dg-alignment-fixture") as Et2DatagridAlignmentFixture;
		el.style.height = "100%";
		host.appendChild(el);
		await el.updateComplete;

		const headerCell = el.shadowRoot!.querySelector(".dg-header .dg-col[data-column-key='name']") as HTMLElement | null;
		const row = el.shadowRoot!.querySelector("[data-row-id]") as HTMLElement | null;
		const bodyCell = el.shadowRoot!.querySelector("tbody [data-row-id] td[data-col-key='name']") as HTMLElement | null;
		assert.isNotNull(row, "body row should render");
		assert.isNotNull(headerCell, "first visible header should render");
		assert.isNotNull(bodyCell, "first visible body cell should render");

		const headerLeft = Math.round(headerCell!.getBoundingClientRect().left);
		const bodyLeft = Math.round(bodyCell!.getBoundingClientRect().left);
		assert.equal(
			headerLeft,
			bodyLeft,
			"meta column width must not shift the first visible header away from its body cell"
		);

		host.remove();
	});

	it("replays pending customfield visibility when header becomes ready", () =>
	{
		const el = createDatagrid();
		const header : Record<string, any> = {};
		el.columns = [
			{key: "customfields", title: "Custom fields", header: header as any}
		] as any;
		(el as any)._pendingCustomfieldVisibilityByColumnKey.set("customfields", {cf_text: true, cf_private: false});

		(el as any)._applyPendingCustomfieldHeaderVisibility();
		assert.equal(
			(el as any)._pendingCustomfieldVisibilityByColumnKey.size,
			1,
			"pending customfield visibility should remain queued until header API is available"
		);

		let applied : Record<string, boolean> | null = null;
		header.setCustomfieldVisibility = (visibility : Record<string, boolean>) =>
		{
			applied = {...visibility};
		};

		(el as any)._applyPendingCustomfieldHeaderVisibility();
		assert.deepEqual(
			applied,
			{cf_text: true, cf_private: false},
			"queued visibility should be applied once header exposes setCustomfieldVisibility()"
		);
		assert.equal(
			(el as any)._pendingCustomfieldVisibilityByColumnKey.size,
			0,
			"pending visibility queue should clear after successful replay"
		);
	});

	it("applies transformed widget attributes and values for rendered rows", async() =>
	{
		const el = createDatagrid();
		const rowTemplate = document.createElement("template");
		rowTemplate.innerHTML = `
			<tr>
				<td><et2-dg-test-transform data-et2nm-id="w1" data-value="$label"></et2-dg-test-transform></td>
				<td>$label</td>
			</tr>
		`;

		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.templateData = {
			columns: el.columns,
			rowTemplate,
			rowTemplateXml: null,
			rowTemplateAttrMap: {w1: {"data-value": "$row.label"}},
			loaderTemplate: null
		} as any;
		const row = {id: "row-0", data: {id: "row-0", label: "Row 0"}};
		const rowElement = (el as any)._buildRowElement(row, 0) as HTMLTableRowElement | null;
		assert.isNotNull(rowElement, "row should be built from template");

		const applied = (el as any)._applyRowElementAttributes(rowElement!, row.data, 0);
		assert.isTrue(applied, "row template attributes should apply successfully");

		const transformed = rowElement!.querySelector("et2-dg-test-transform") as HTMLElement | null;
		assert.isNotNull(transformed, "transformed widget should be present in row");
		assert.isTrue(
			(transformed!.getAttribute("data-value") || "").length > 0,
			"transformed widget should have a non-empty data-value"
		);
		assert.equal(
			transformed!.getAttribute("data-value"),
			transformed!.textContent?.trim(),
			"transformed widget attribute and displayed value should match"
		);
	});

	/**
	 * Contract: row binding must preserve shared array managers while replacing
	 * only the content manager with the current row perspective.
	 * Setup: probe widget reads customfield metadata from modifications and value
	 * from row content.
	 * Pass: both shared metadata and row-scoped value are available.
	 */
	it("preserves non-content array managers while applying row content perspective", () =>
	{
		const el = createDatagrid();
		el.setArrayMgr("content", new et2_arrayMgr({}));
		el.setArrayMgr("modifications", new et2_arrayMgr({
			"~custom_fields~": {
				customfields: {
					cf_text: {label: "Text", type: "text"}
				}
			}
		}));

		const provider = new Et2RowProvider(el as any);
		el.columns = [{key: "customfields", title: "Custom fields", width: "1fr"}] as any;
		const rowTemplate = document.createElement("row");
		rowTemplate.innerHTML = `
			<td><et2-dg-mgr-probe data-value="$row_cont[#cf_text]"></et2-dg-mgr-probe></td>
		`;
		const prepared = (provider as any)._prepareRowTemplate(rowTemplate, el.columns as any);
		el.templateData = {
			columns: el.columns,
			rowTemplate: prepared?.template,
			rowTemplateXml: prepared?.xml,
			rowTemplateAttrMap: prepared?.attrMap || {},
			loaderTemplate: null
		} as any;

		const row = {id: "row-0", data: {"#cf_text": "Row customfield value"}};
		const rowElement = (el as any)._buildRowElement(row, 0) as HTMLTableRowElement | null;
		assert.isNotNull(rowElement, "row should be built from template");

		const applied = (el as any)._applyRowElementAttributes(rowElement!, row.data, 0);
		assert.isTrue(applied, "row template attributes should apply successfully");

		const probe = rowElement!.querySelector("et2-dg-mgr-probe") as HTMLElement | null;
		assert.equal(
			probe?.getAttribute("data-fields"),
			"cf_text",
			"row widget should retain access to shared customfield metadata from modifications"
		);
		assert.equal(
			probe?.getAttribute("data-value"),
			"Row customfield value",
			"row widget should still use row-scoped content for values"
		);
	});

	/**
	 * Contract: row templates may use et2-customfields-list, but datagrid rows
	 * instantiate the lightweight et2-customfields-list-row implementation.
	 * Setup: prepare a row template with an explicit et2-customfields-list tag.
	 * Pass: the prepared template contains the row renderer and keeps row attributes.
	 */
	it("uses the lightweight row renderer for explicit et2 customfields-list rows", () =>
	{
		const host = document.createElement("et2-datagrid") as Et2Datagrid;
		const provider = new Et2RowProvider(host);
		const row = document.createElement("row");
		row.innerHTML = `<et2-customfields-list id="$row" class="customfields"></et2-customfields-list>`;

		const prepared = (provider as any)._prepareRowTemplate(row, []);
		const list = prepared?.template.content.querySelector("et2-customfields-list-row") as HTMLElement | null;

		assert.isNotNull(
			list,
			"explicit et2-customfields-list rows should use the lightweight datagrid row renderer"
		);
		assert.equal(
			list?.getAttribute("id"),
			"$row",
			"row renderer should keep row-scoped id for later row-context transform"
		);
	});

	/**
	 * Contract: datagrid customfield rows receive shared metadata once from the
	 * customfield source and row-specific values from top-level #field data.
	 * Setup: build a row template containing et2-customfields-list without a header,
	 * forcing the modifications-array fallback used by legacy templates.
	 * Pass: only selected fields render and the value object contains visible #fields.
	 */
	it("hydrates datagrid row customfields once from shared metadata and displays row values", async() =>
	{
		const el = createDatagrid();
		el.setArrayMgr("content", new et2_arrayMgr({}));
		el.setArrayMgr("modifications", new et2_arrayMgr({
			"~custom_fields~": {
				customfields: {
					cf_text: {label: "Text", type: "text"},
					cf_hidden: {label: "Hidden", type: "text"}
				},
				fields: {
					cf_text: true,
					cf_hidden: false
				}
			}
		}));

		const provider = new Et2RowProvider(el as any);
		el.columns = [{key: "customfields", title: "Custom fields", width: "1fr"}] as any;
		const rowTemplate = document.createElement("row");
		rowTemplate.innerHTML = `<td><et2-customfields-list class="customfields"></et2-customfields-list></td>`;
		const prepared = (provider as any)._prepareRowTemplate(rowTemplate, el.columns as any);
		el.templateData = {
			columns: el.columns,
			rowTemplate: prepared?.template,
			rowTemplateXml: prepared?.xml,
			rowTemplateAttrMap: prepared?.attrMap || {},
			loaderTemplate: null
		} as any;

		const row = {id: "row-0", data: {"#cf_text": "Row customfield value", "#cf_hidden": "Hidden value"}};
		const rowElement = (el as any)._buildRowElement(row, 0) as HTMLTableRowElement | null;
		assert.isNotNull(rowElement, "row should be built from the customfields template");
		document.body.appendChild(rowElement!);

		try
		{
			const applied = (el as any)._applyRowElementAttributes(rowElement!, row.data, 0);
			assert.isTrue(applied, "row customfields should initialize from row managers");

			const list = rowElement!.querySelector("et2-customfields-list-row") as Et2CustomfieldsBase | null;
			assert.isNotNull(list, "row should use the lightweight customfields renderer");
			await list!.updateComplete;

			assert.deepEqual(
				list!.getVisibleFieldNames(),
				["cf_text"],
				"visible fields should come from shared customfield metadata, not row values"
			);
			assert.include(
				list!.shadowRoot?.querySelector("[data-field='cf_text']")?.textContent || "",
				"Row customfield value",
				"visible customfield should display the current row value"
			);
			assert.deepEqual(
				list!.value,
				{"#cf_text": "Row customfield value"},
				"row customfield values should include only visible selected customfields"
			);
			assert.isNull(
				list!.shadowRoot?.querySelector("[data-field='cf_hidden']"),
				"hidden customfield should not be rendered even when the row has a value"
			);
		}
		finally
		{
			rowElement!.remove();
		}
	});

	/**
	 * Contract: selected customfield visibility comes from the owning customfield
	 * header, not from row data or the widget's default fields object.
	 * Setup: provide a customfield header with four fields and three selected.
	 * Pass: the row renderer receives the same visibility map object from the header.
	 */
	it("applies selected customfield visibility from the header to row renderers", () =>
	{
		const el = createDatagrid();
		el.setArrayMgr("content", new et2_arrayMgr({}));
		el.setArrayMgr("modifications", new et2_arrayMgr({
			"~custom_fields~": {
				customfields: {
					cf_one: {label: "One", type: "text"},
					cf_two: {label: "Two", type: "text"},
					cf_three: {label: "Three", type: "text"},
					cf_four: {label: "Four", type: "text"}
				}
			}
		}));
		const visibility = {
			cf_one: true,
			cf_two: true,
			cf_three: true,
			cf_four: false
		};
		const header = {
			getCustomfieldVisibility: () => visibility,
			getCustomfieldSelectionItems: () => Object.keys(visibility).map((name) => ({
				name,
				label: name,
				visible: visibility[name]
			}))
		};
		el.columns = [{key: "customfields", title: "Custom fields", header: header as any}] as any;

		const provider = new Et2RowProvider(el as any);
		const rowTemplate = document.createElement("row");
		rowTemplate.innerHTML = `<div><et2-customfields-list class="customfields"></et2-customfields-list></div>`;
		const prepared = (provider as any)._prepareRowTemplate(rowTemplate, el.columns as any);
		el.templateData = {
			columns: el.columns,
			rowTemplate: prepared?.template,
			rowTemplateXml: prepared?.xml,
			rowTemplateAttrMap: prepared?.attrMap || {},
			loaderTemplate: null
		} as any;

		const row = {id: "row-0", data: {"#cf_one": "One", "#cf_two": "Two", "#cf_three": "Three", "#cf_four": "Four"}};
		const rowElement = (el as any)._buildRowElement(row, 0) as HTMLTableRowElement | null;
		assert.isNotNull(rowElement, "row should be built from the customfields template");
		assert.isTrue((el as any)._applyRowElementAttributes(rowElement!, row.data, 0));

		const list = rowElement!.querySelector("et2-customfields-list-row") as Et2CustomfieldsBase | null;
		assert.deepEqual(
			list?.fields,
			visibility,
			"row renderer should use the header's full selected customfield visibility map"
		);
	});

	it("supports modern and legacy row shorthand expressions in template attributes", async() =>
	{
		const el = createDatagrid();
		const rowTemplate = document.createElement("template");
		rowTemplate.innerHTML = `
			<tr class="$class $cat_id">
				<td><et2-dg-test-transform data-et2nm-id="w1" data-value="\${row}[note]"></et2-dg-test-transform></td>
				<td><et2-dg-test-transform data-et2nm-id="w2" data-value="$note"></et2-dg-test-transform></td>
			</tr>
		`;

		el.columns = [{key: "note", title: "Note", width: "1fr"}] as any;
		el.templateData = {
			columns: el.columns,
			rowTemplate,
			rowTemplateXml: null,
			rowTemplateAttrMap: {
				w1: {"data-value": "${row}[note]"},
				w2: {"data-value": "$note"}
			},
			loaderTemplate: null
		} as any;
		const row = {id: "row-0", data: {id: "row-0", class: "primary", cat_id: "3", note: "Legacy note"}};
		const rowElement = (el as any)._buildRowElement(row, 0) as HTMLTableRowElement | null;
		assert.isNotNull(rowElement, "row should be built from template");

		(rowElement as HTMLElement).setAttribute("data-et2nm-id", "row-root");
		(el.templateData as any).rowTemplateAttrMap["row-root"] = {"class": "$row_cont[class] $row_cont[cat_id]"};

		const applied = (el as any)._applyRowElementAttributes(rowElement!, row.data, 0);
		assert.isTrue(applied, "row template attributes should apply successfully");

		const transformed = rowElement!.querySelector("et2-dg-test-transform") as HTMLElement | null;
		assert.equal(
			(transformed as any)?.lastTransformedAttrs?.["data-value"],
			"${row}[note]",
			"modern ${row}[field] placeholder should be passed through to widget transform"
		);
		assert.equal(
			Et2RowProvider.resolveSimpleRowPlaceholders("$note", row.data, (_rowData, key) => row.data[key]),
			"Legacy note",
			"modern $field placeholder should resolve to row field value"
		);
		assert.include(rowElement!.className, "primary", "`$class` should resolve from row content");
		assert.include(rowElement!.className, "cat_3", "`$cat_id` should resolve into category class");
	});

	/**
	 * Contract: single-row refresh replaces loaded row data in place without a full reload.
	 * Setup: seed one loaded row, stub provider refresh with updated data for the same row id.
	 * Pass: loaded row data is replaced and selection remains on the same row id.
	 */
	it("applies a refreshed loaded row in place", async() =>
	{
		const el = createDatagrid();
		let pulsedRowIds : string[] = [];
		const renderedRow = document.createElement("tr");
		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.templateData = {columns: el.columns} as any;
		(el as any)._scheduleRenderedRowPulse = (rowIds : string[]) =>
		{
			pulsedRowIds = rowIds;
			renderedRow.classList.add("dg-row--refreshed");
		};
		el.dataProvider = createDatagridDataProvider({
			fetchPage: async() => ({rows: [], total: 1}),
			refresh: async() => ({
				rows: [{id: "addressbook::row-1", data: {uid: "addressbook::row-1", label: "Updated row"}}],
				removedRowIds: []
			})
		}) as any;
		el.setInitialRows([{uid: "addressbook::row-1", label: "Original row"}]);
		el.selectSingleRow("addressbook::row-1");

		await el.refresh(["row-1"], "update" as any);
		await new Promise((resolve) => window.setTimeout(resolve, 0));

		assert.equal(el.rows[0].data.label, "Updated row", "loaded row should be replaced with refreshed row data");
		assert.deepEqual(
			(el as any).selectedRowIds ? Array.from((el as any).selectedRowIds) : [],
			["addressbook::row-1"],
			"selection should remain anchored to the refreshed row id"
		);
		assert.deepEqual(pulsedRowIds, ["addressbook::row-1"], "refresh should schedule a pulse for the updated row");
		assert.isTrue(
			renderedRow.classList.contains("dg-row--refreshed"),
			"visible refreshed rows should receive the refreshed state class"
		);
	});

	/**
	 * Contract: selecting a visible row must tolerate sparse virtualized row state.
	 * Setup: emulate a scrolled grid where earlier indexes are not loaded but the
	 * last visible row is present in `_rowsByIndex`.
	 * Pass: selecting the last row does not throw and active selection points to it.
	 */
	it("selects a loaded last row when virtualized row indexes contain gaps", () =>
	{
		const el = createDatagrid();
		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.templateData = {columns: el.columns} as any;
		el.rows = [{id: "addressbook::last", data: {label: "Last row"}}] as any;
		(el as any)._rowsByIndex = [
			undefined,
			undefined,
			{id: "addressbook::last", data: {label: "Last row"}}
		];

		assert.doesNotThrow(() => el.selectSingleRow("addressbook::last"),
			"selecting sparse last row should not read id from undefined row slots");
		assert.deepEqual(Array.from((el as any).selectedRowIds), ["addressbook::last"], "last row should be selected");
		assert.equal((el as any).activeRowIndex, 2, "active row index should match sparse row position");
		assert.equal((el as any).activeRowId, "addressbook::last", "active row id should match selected row");
	});

	/**
	 * Contract: add refresh prepends newly visible rows to the top of the loaded grid.
	 * Setup: seed one loaded row, stub provider refresh with a different row id returned for add.
	 * Pass: new row is inserted at index 0 and existing row selection stays on the same row id.
	 */
	it("prepends newly added rows during add refresh", async() =>
	{
		const el = createDatagrid();
		let pulsedRowIds : string[] = [];
		const renderedRow = document.createElement("tr");
		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.templateData = {columns: el.columns} as any;
		(el as any)._scheduleRenderedRowPulse = (rowIds : string[]) =>
		{
			pulsedRowIds = rowIds;
			renderedRow.classList.add("dg-row--refreshed");
		};
		el.dataProvider = createDatagridDataProvider({
			fetchPage: async() => ({rows: [], total: 2}),
			refresh: async() => ({
				rows: [{id: "addressbook::row-2", data: {uid: "addressbook::row-2", label: "Inserted row"}}],
				removedRowIds: []
			})
		}) as any;
		el.setInitialRows([{uid: "addressbook::row-1", label: "Original row"}]);
		el.total = 1;
		el.selectSingleRow("addressbook::row-1");

		await el.refresh(["row-2"], "add" as any);
		await new Promise((resolve) => window.setTimeout(resolve, 0));

		assert.deepEqual(
			el.rows.map((row) => row.id),
			["addressbook::row-2", "addressbook::row-1"],
			"newly added row should be prepended ahead of currently loaded rows"
		);
		assert.equal(el.rows[0].data.label, "Inserted row");
		assert.equal((el as any).activeRowId, "addressbook::row-1", "active row should remain on the previously selected row");
		assert.equal((el as any).anchorRowIndex, 1, "anchor row index should shift with prepended rows");
		assert.equal(el.total, 2, "known total should grow when a new row is inserted locally");
		assert.deepEqual(pulsedRowIds, ["addressbook::row-2"], "add refresh should schedule a pulse for the inserted row");
		assert.isTrue(
			renderedRow.classList.contains("dg-row--refreshed"),
			"visible added rows should receive the refreshed state class"
		);
	});
});

describe("Et2Datagrid keyboard navigation", () =>
{
	it("advances active row with ArrowDown in virtualized data", async() =>
	{
		const el = createDatagrid();
		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.templateData = {columns: el.columns} as any;
		el.setInitialRows(Array.from({length: 200}, (_v, index) => ({id: `row-${index}`, label: `Row ${index}`})));
		el.total = 200;
		const startIndex = 20;
		(el as any)._moveActiveRow(startIndex, false);
		(el as any)._onTableKeydown(new KeyboardEvent("keydown", {key: "ArrowDown"}));

		assert.equal((el as any).activeRowIndex, startIndex + 1, "activeRowIndex should advance by exactly one row");
		assert.equal((el as any).activeRowId, `row-${startIndex + 1}`, "active row id should advance by exactly one row");
	});

});

describe("Et2Datagrid column sizing", () =>
{
	it("keeps static pixel column widths in CSS grid tracks", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = createDatagrid();
		el.style.height = "100%";
		host.appendChild(el);
		await el.updateComplete;

		el.columns = [
			{key: "a", title: "A", width: "120"},
			{key: "b", title: "B", width: "240px"}
		] as any;
		el.templateData = {columns: el.columns} as any;
		await el.updateComplete;

		const body = el.shadowRoot!.querySelector(".dg-body") as HTMLElement;
		const columnSizes = getComputedStyle(body).getPropertyValue("--column-sizes").trim();
		assert.include(columnSizes, "120px", "numeric widths should normalize to px");
		assert.include(columnSizes, "240px", "explicit px widths should be preserved");

		host.remove();
	});

	it("keeps relative column widths in CSS grid tracks", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = createDatagrid();
		el.style.height = "100%";
		host.appendChild(el);
		await el.updateComplete;

		el.columns = [
			{key: "a", title: "A", width: "30%"},
			{key: "b", title: "B", width: "1fr"}
		] as any;
		el.templateData = {columns: el.columns} as any;
		await el.updateComplete;

		const body = el.shadowRoot!.querySelector(".dg-body") as HTMLElement;
		const columnSizes = getComputedStyle(body).getPropertyValue("--column-sizes").trim();
		assert.include(columnSizes, "30fr", "percentage width should be normalized to fr");
		assert.include(columnSizes, "1fr", "fr width should remain relative");

		host.remove();
	});

	it("normalizes minWidth for pixel and unitless values", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = createDatagrid();
		el.style.height = "100%";
		host.appendChild(el);
		await el.updateComplete;

		el.columns = [
			{key: "a", title: "A", width: "30%", minWidth: "120"},
			{key: "b", title: "B", width: "240px", minWidth: "90px"}
		] as any;
		el.templateData = {columns: el.columns} as any;
		await el.updateComplete;

		const body = el.shadowRoot!.querySelector(".dg-body") as HTMLElement;
		const columnSizes = getComputedStyle(body).getPropertyValue("--column-sizes").trim();
		assert.include(columnSizes, "minmax(120px, 30fr)", "unitless minWidth should normalize to px");
		assert.include(columnSizes, "minmax(90px, 240px)", "px minWidth should be preserved");

		host.remove();
	});

});

describe("Et2Datagrid column preference keys", () =>
{
	it("derives default key from owner tag and row template id", async() =>
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

	it("uses explicit columnPreferenceName override when provided", async() =>
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

describe("Et2Datagrid selection mode", () =>
{
	it("starts with first row active but not selected", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = createDatagrid();
		el.style.height = "100%";
		host.appendChild(el);
		await el.updateComplete;

		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.templateData = {columns: el.columns} as any;
		el.setInitialRows([
			{id: "row-0", label: "Row 0"},
			{id: "row-1", label: "Row 1"}
		]);
		el.total = 2;
		(el as any)._reconcileRowRenderState();
		await el.updateComplete;
		(el as any)._syncRowAccessibilityState();
		await el.updateComplete;

		assert.equal((el as any).activeRowIndex, 0, "first row should be active by default");
		assert.equal((el as any).activeRowId, "row-0", "active row id should point to first row");
		assert.equal((el as any).selectedRowIds.size, 0, "no rows should be selected by default");

		const firstRow = el.shadowRoot!.querySelector("tr[data-row-index='0']") as HTMLElement | null;
		if(firstRow)
		{
			assert.equal(firstRow.getAttribute("aria-selected"), "false", "active first row should not be selected");
		}

		host.remove();
	});

	it("keeps selection empty when moving active row with ArrowDown", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = createDatagrid();
		el.style.height = "100%";
		host.appendChild(el);
		await el.updateComplete;

		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.templateData = {columns: el.columns} as any;
		el.setInitialRows([
			{id: "row-0", label: "Row 0"},
			{id: "row-1", label: "Row 1"},
			{id: "row-2", label: "Row 2"}
		]);
		el.total = 3;
		(el as any)._reconcileRowRenderState();
		await el.updateComplete;

		assert.equal((el as any).activeRowIndex, 0, "first row should start active");
		assert.equal((el as any).selectedRowIds.size, 0, "no rows should start selected");

		(el as any)._onTableKeydown(new KeyboardEvent("keydown", {key: "ArrowDown"}));
		await el.updateComplete;

		assert.equal((el as any).activeRowIndex, 1, "second row should be active after ArrowDown");
		assert.equal((el as any).activeRowId, "row-1", "active row id should move to second row");
		assert.equal((el as any).selectedRowIds.size, 0, "ArrowDown should not select rows");

		host.remove();
	});

	it("keeps ArrowDown navigation after scroll moves focus to container", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = createDatagrid();
		el.style.height = "100%";
		host.appendChild(el);
		await el.updateComplete;

		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.templateData = {columns: el.columns} as any;
		el.setInitialRows([
			{id: "row-0", label: "Row 0"},
			{id: "row-1", label: "Row 1"},
			{id: "row-2", label: "Row 2"}
		]);
		el.total = 3;
		(el as any)._reconcileRowRenderState();
		await el.updateComplete;
		(el as any)._moveActiveRow(0, true);
		await new Promise((resolve) => requestAnimationFrame(() => resolve(null)));

		const app = document.createElement("egw-app");
		app.tabIndex = -1;
		document.body.appendChild(app);
		app.focus();

		const body = el.shadowRoot!.querySelector(".dg-body") as HTMLElement;
		body.dispatchEvent(new Event("scroll"));
		await new Promise((resolve) => requestAnimationFrame(() => resolve(null)));
		await el.updateComplete;

		(el as any)._onTableKeydown(new KeyboardEvent("keydown", {key: "ArrowDown"}));
		await el.updateComplete;

		assert.equal((el as any).activeRowIndex, 1, "second row should be active after ArrowDown");
		assert.equal((el as any).activeRowId, "row-1", "active row id should move to second row");

		app.remove();
		host.remove();
	});

	it("keeps scroll focus behavior without stealing external focus", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = createDatagrid();
		el.style.height = "100%";
		host.appendChild(el);
		await el.updateComplete;

		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.templateData = {columns: el.columns} as any;
		el.setInitialRows([
			{id: "row-0", label: "Row 0"},
			{id: "row-1", label: "Row 1"},
			{id: "row-2", label: "Row 2"}
		]);
		el.total = 3;
		(el as any)._reconcileRowRenderState();
		await el.updateComplete;
		(el as any)._moveActiveRow(0, true);
		await new Promise((resolve) => requestAnimationFrame(() => resolve(null)));

		const body = el.shadowRoot!.querySelector(".dg-body") as HTMLElement;
		assert.isNotNull(body, "grid scroll body should exist");

		// Scroll must not steal focus from external controls.
		const input = document.createElement("input");
		document.body.appendChild(input);
		input.focus();
		body.dispatchEvent(new Event("scroll"));
		await new Promise((resolve) => requestAnimationFrame(() => resolve(null)));
		await el.updateComplete;
		assert.equal(document.activeElement, input, "scroll handling must not steal focus from active external control");
		input.remove();

		// If focus falls to container, keyboard navigation should still work after scroll.
		const app = document.createElement("egw-app");
		app.tabIndex = -1;
		document.body.appendChild(app);
		app.focus();
		body.dispatchEvent(new Event("scroll"));
		await new Promise((resolve) => requestAnimationFrame(() => resolve(null)));
		await el.updateComplete;

		(el as any)._onTableKeydown(new KeyboardEvent("keydown", {key: "ArrowDown"}));
		await el.updateComplete;
		assert.equal((el as any).activeRowIndex, 1, "active row should advance after ArrowDown");
		assert.equal((el as any).activeRowId, "row-1", "active row id should move to second row");

		app.remove();
		host.remove();
	});

	it("keeps selection empty with fetched rows when moving active row with ArrowDown", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = createDatagrid();
		el.style.height = "100%";
		host.appendChild(el);
		await el.updateComplete;
		(el as any)._requestDispatchDelayMs = 0;

		let fetchCalls = 0;
		el.dataProvider = createDatagridDataProvider({
			fetchPage: async(start : number, pageSize : number) =>
			{
				fetchCalls++;
				return {
					total: 3,
					rows: Array.from({length: Math.min(pageSize, 3)}, (_v, index) => ({
						id: `row-${start + index}`,
						label: `Row ${start + index}`
					}))
				};
			},
			getQuerySignature: () => "selection-fetch-no-initial"
		}) as any;

		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.templateData = {columns: el.columns} as any;
		el.pageSize = 3;
		el.total = 3;
		await el.updateComplete;

		el.loadMore();
		await new Promise((resolve) => window.setTimeout(resolve, 0));
		await new Promise((resolve) => window.setTimeout(resolve, 0));
		await el.updateComplete;

		assert.isAtLeast(fetchCalls, 1, "rows should be fetched when no initial rows are provided");

		assert.equal((el as any).activeRowIndex, 0, "first fetched row should start active");
		assert.equal((el as any).activeRowId, "row-0", "active row should map to first fetched row");
		assert.equal((el as any).selectedRowIds.size, 0, "no rows should be selected after initial fetch");

		(el as any)._onTableKeydown(new KeyboardEvent("keydown", {key: "ArrowDown"}));
		await el.updateComplete;

		assert.equal((el as any).activeRowIndex, 1, "second row should become active after ArrowDown");
		assert.equal((el as any).activeRowId, "row-1", "active row id should move to second fetched row");
		assert.equal((el as any).selectedRowIds.size, 0, "ArrowDown should not select fetched rows");

		host.remove();
	});

	it("applies selection behavior for none, single, and multiple selectionMode", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = createDatagrid();
		el.style.height = "100%";
		host.appendChild(el);
		await el.updateComplete;

		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.templateData = {columns: el.columns} as any;
		el.setInitialRows([
			{id: "row-0", label: "Row 0"},
			{id: "row-1", label: "Row 1"},
			{id: "row-2", label: "Row 2"}
		]);
		el.total = 3;
		await el.updateComplete;

		// none: pointer selection does nothing.
		el.selectionMode = "none";
		el.selectedRowIds = new Set(["row-0"]);
		(el as any)._updateSelectionFromPointer("row-1", 1, new MouseEvent("click"));
		assert.sameMembers(Array.from(el.selectedRowIds), ["row-0"], "selection should not change in none mode");

		// single: each selection replaces previous one, regardless of modifiers.
		el.selectionMode = "single";
		(el as any)._updateSelectionFromPointer("row-1", 1, new MouseEvent("click"));
		assert.sameMembers(Array.from(el.selectedRowIds), ["row-1"], "single mode should select clicked row");
		(el as any)._updateSelectionFromPointer("row-2", 2, new MouseEvent("click", {ctrlKey: true}));
		assert.sameMembers(Array.from(el.selectedRowIds), ["row-2"], "single mode should keep exactly one selected row");

		// multiple: ctrl/meta toggles and shift selects range from anchor.
		el.selectionMode = "multiple";
		(el as any)._updateSelectionFromPointer("row-0", 0, new MouseEvent("click"));
		(el as any)._updateSelectionFromPointer("row-1", 1, new MouseEvent("click", {ctrlKey: true}));
		assert.sameMembers(Array.from(el.selectedRowIds), ["row-0", "row-1"], "multiple mode should allow additive toggle");
		(el as any)._updateSelectionFromPointer("row-2", 2, new MouseEvent("click", {shiftKey: true}));
		assert.sameMembers(Array.from(el.selectedRowIds), ["row-1", "row-2"], "multiple mode should select contiguous range from current anchor with shift");

		host.remove();
	});

	it("selects all rows with Ctrl+A in multiple mode", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = createDatagrid();
		el.style.height = "100%";
		host.appendChild(el);
		await el.updateComplete;

		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.templateData = {columns: el.columns} as any;
		el.setInitialRows([
			{id: "row-0", label: "Row 0"},
			{id: "row-1", label: "Row 1"},
			{id: "row-2", label: "Row 2"}
		]);
		el.total = 3;
		el.selectionMode = "multiple";
		await el.updateComplete;

		const event = new KeyboardEvent("keydown", {key: "a", ctrlKey: true, cancelable: true});
		(el as any)._onTableKeydown(event);

		assert.isTrue(event.defaultPrevented, "Ctrl+A should prevent native browser select-all");
		assert.isTrue(el.allSelected, "Ctrl+A should set allSelected");
		assert.sameMembers(Array.from(el.selectedRowIds), ["row-0", "row-1", "row-2"], "Ctrl+A should select all rendered rows");

		host.remove();
	});

	it("does not select all rows with Ctrl+A outside multiple mode", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = createDatagrid();
		el.style.height = "100%";
		host.appendChild(el);
		await el.updateComplete;

		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.templateData = {columns: el.columns} as any;
		el.setInitialRows([
			{id: "row-0", label: "Row 0"},
			{id: "row-1", label: "Row 1"}
		]);
		el.total = 2;
		el.selectionMode = "single";
		el.selectedRowIds = new Set(["row-0"]);
		await el.updateComplete;

		const event = new KeyboardEvent("keydown", {key: "a", ctrlKey: true, cancelable: true});
		(el as any)._onTableKeydown(event);

		assert.isFalse(event.defaultPrevented, "Ctrl+A should not be intercepted outside multiple mode");
		assert.isFalse(el.allSelected, "single mode should not set allSelected from Ctrl+A");
		assert.sameMembers(Array.from(el.selectedRowIds), ["row-0"], "single mode selection should remain unchanged");

		host.remove();
	});
});

describe("Et2Datagrid virtual height stability", () =>
{
	it("keeps scroll height stable after replacing placeholders with fetched rows", async() =>
	{
		let resolvePage : ((value : any) => void) | null = null;
		const dataProvider = createDatagridDataProvider({
			fetchPage: () => new Promise((resolve) =>
			{
				resolvePage = resolve;
			}),
			getQuerySignature: () => "height-stability"
		});

		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = createDatagrid();
		el.style.height = "100%";
		host.appendChild(el);
		await el.updateComplete;
		(el as any)._requestDispatchDelayMs = 0;

		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.pageSize = 10;
		el.dataProvider = dataProvider as any;
		(el as any)._rowHeightLocked = true;
		(el as any)._rowHeightPx = 42;
		el.setInitialRows(Array.from({length: 10}, (_v, index) => ({id: `row-${index}`, label: `Row ${index}`})));
		el.total = 40;
		(el as any)._reconcileRowRenderState();
		await el.updateComplete;

		const body = el.shadowRoot!.querySelector(".dg-body") as HTMLElement;
		const baselineHeight = body.scrollHeight;

		(el as any)._requestChunkForRowIndex(15);
		(el as any)._processQueuedRequests();
		await new Promise((resolve) => window.setTimeout(resolve, 0));
		await el.updateComplete;
		const inFlightHeight = body.scrollHeight;
		assert.isAtLeast(inFlightHeight, baselineHeight, "scroll height shrank while fetch placeholders are active");
		assert.isFunction(resolvePage, "fetchPage was not called");

		resolvePage!({
			total: 40,
			rows: Array.from({length: 10}, (_v, index) => ({id: `row-${index + 10}`, label: `Row ${index + 10}`}))
		});
		// After resolving fetchPage, _fetchPage() still needs a macrotask to run its
		// completion path and rerender rows/spacer before we measure final height.
		await new Promise((resolve) => window.setTimeout(resolve, 0));
		await el.updateComplete;

		const finalHeight = body.scrollHeight;
		assert.isAtLeast(finalHeight, baselineHeight, "final scroll height should not drop below baseline after fetch");

		host.remove();
	});

	it("requests deeper chunks when rows in a later chunk are needed", async() =>
	{
		const calls : number[] = [];
		const dataProvider = createDatagridDataProvider({
			fetchPage: async(start : number, pageSize : number) =>
			{
				calls.push(start);
				return {
					total: 200,
					rows: Array.from({length: pageSize}, (_v, index) => ({
						id: `row-${start + index}`,
						label: `Row ${start + index}`
					}))
				};
			},
			getQuerySignature: () => "replace-stale-pending"
		});

		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = createDatagrid();
		el.style.height = "100%";
		host.appendChild(el);
		await el.updateComplete;
		(el as any)._requestDispatchDelayMs = 0;

		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.pageSize = 50;
		el.dataProvider = dataProvider as any;
		(el as any)._rowHeightLocked = true;
		(el as any)._rowHeightPx = 42;
		el.setInitialRows(Array.from({length: 50}, (_v, index) => ({id: `row-${index}`, label: `Row ${index}`})));
		el.total = 200;
		(el as any)._reconcileRowRenderState();
		await el.updateComplete;

		// First chunk is already materialized from initial rows, so no request.
		(el as any)._requestChunkForRowIndex(0);
		(el as any)._processQueuedRequests();
		await new Promise((resolve) => window.setTimeout(resolve, 0));
		assert.equal(calls.length, 0, "materialized chunk should not be fetched again");

		// Requesting a row in a deeper missing chunk should fetch that chunk.
		(el as any)._requestChunkForRowIndex(150);
		(el as any)._processQueuedRequests();
		await new Promise((resolve) => window.setTimeout(resolve, 0));
		await el.updateComplete;

		assert.isTrue(calls.some((start) => start === 150), "missing deeper chunk should be requested");
		host.remove();
	});

	it("requests more rows when user scrolls to unloaded chunk", async() =>
	{
		const calls : number[] = [];
		const dataProvider = createDatagridDataProvider({
			fetchPage: async(start : number, pageSize : number) =>
			{
				calls.push(start);
				return {
					total: 200,
					rows: Array.from({length: pageSize}, (_v, index) => ({
						id: `row-${start + index}`,
						label: `Row ${start + index}`
					}))
				};
			},
			getQuerySignature: () => "scroll-requests-chunk"
		});

		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = createDatagrid();
		el.style.height = "100%";
		host.appendChild(el);
		await el.updateComplete;
		(el as any)._requestDispatchDelayMs = 0;
		(el as any)._rowHeightLocked = true;
		(el as any)._rowHeightPx = 42;

		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.pageSize = 50;
		el.dataProvider = dataProvider as any;
		el.setInitialRows(Array.from({length: 50}, (_v, index) => ({id: `row-${index}`, label: `Row ${index}`})));
		el.total = 200;
		(el as any)._reconcileRowRenderState();
		await el.updateComplete;

		assert.equal(calls.length, 0, "initial rendered chunk should not trigger fetch");

		const body = el.shadowRoot!.querySelector(".dg-body") as HTMLElement;
		assert.isNotNull(body, "grid body should exist");

		// Simulate virtualization exposing a deeper row during scroll.
		(el as any)._renderVirtualRow(160);
		body.dispatchEvent(new Event("scroll"));
		await new Promise((resolve) => window.setTimeout(resolve, 0));
		await el.updateComplete;

		assert.isTrue(calls.some((start) => start === 150), "scrolling into unloaded area should request matching chunk");
		host.remove();
	});
});

describe("Et2Datagrid data loading", () =>
{
	it("does not request rows when there are sufficient rows provided initially", async() =>
	{
		let fetchCalls = 0;
		const dataProvider = createDatagridDataProvider({
			fetchPage: async() =>
			{
				fetchCalls++;
				return {
					total: 200,
					rows: []
				};
			},
			getQuerySignature: () => "sufficient-initial-rows"
		});

		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = createDatagrid();
		el.style.height = "100%";
		host.appendChild(el);
		await el.updateComplete;
		(el as any)._requestDispatchDelayMs = 0;
		(el as any)._rowHeightLocked = true;
		(el as any)._rowHeightPx = 42;

		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.pageSize = 20;
		el.dataProvider = dataProvider as any;
		el.setInitialRows(Array.from({length: 80}, (_v, index) => ({id: `row-${index}`, label: `Row ${index}`})));
		el.total = 200;
		await el.updateComplete;

		// Current chunk starts at 0, which is already fully materialized by initial rows.
		el.loadMore();
		await new Promise((resolve) => window.setTimeout(resolve, 0));
		await el.updateComplete;

		assert.equal(fetchCalls, 0, "loadMore should not fetch when initial rows already cover current requested chunk");
		host.remove();
	});

	it("does not request rows when initial rows equal total rows even if viewport has extra space", async() =>
	{
		let fetchCalls = 0;
		const dataProvider = createDatagridDataProvider({
			fetchPage: async() =>
			{
				fetchCalls++;
				return {
					total: 5,
					rows: []
				};
			},
			getQuerySignature: () => "initial-equals-total"
		});

		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = createDatagrid();
		el.style.height = "100%";
		host.appendChild(el);
		await el.updateComplete;
		(el as any)._requestDispatchDelayMs = 0;
		(el as any)._rowHeightLocked = true;
		(el as any)._rowHeightPx = 42;

		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.pageSize = 20;
		el.dataProvider = dataProvider as any;
		el.setInitialRows(Array.from({length: 5}, (_v, index) => ({id: `row-${index}`, label: `Row ${index}`})));
		el.total = 5;
		await el.updateComplete;

		// If total were larger, this viewport would request more rows. Because rows==total,
		// requesting more would be invalid and should not happen.
		el.loadMore();
		await new Promise((resolve) => window.setTimeout(resolve, 0));
		await el.updateComplete;

		assert.equal(fetchCalls, 0, "loadMore should not fetch when all rows are already provided");
		assert.equal(el.rows.length, 5, "preloaded rows should remain intact");
		host.remove();
	});

	it("requests rows when there are no preloaded rows", async() =>
	{
		let fetchCalls = 0;
		const dataProvider = createDatagridDataProvider({
			fetchPage: async(start : number, pageSize : number) =>
			{
				fetchCalls++;
				return {
					total: 5,
					rows: Array.from({length: Math.min(pageSize, 5)}, (_v, index) => ({
						id: `row-${start + index}`,
						label: `Row ${start + index}`
					}))
				};
			},
			getQuerySignature: () => "no-preloaded-rows"
		});

		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = createDatagrid();
		el.style.height = "100%";
		host.appendChild(el);
		await el.updateComplete;
		(el as any)._requestDispatchDelayMs = 0;

		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.pageSize = 5;
		el.dataProvider = dataProvider as any;
		el.total = 5;
		await el.updateComplete;

		el.loadMore();
		await new Promise((resolve) => window.setTimeout(resolve, 0));
		await new Promise((resolve) => window.setTimeout(resolve, 0));
		await el.updateComplete;

		assert.equal(fetchCalls, 1, "loadMore should request rows when grid starts empty");
		assert.equal(el.rows.length, 5, "fetched rows should be rendered");
		host.remove();
	});
});
