import {assert} from "@open-wc/testing";
import {html, LitElement} from "lit";
import {Et2Datagrid} from "../Et2Datagrid";
import datagridStyles from "../Et2Datagrid.styles.ts";
import {Et2RowProvider} from "../Et2RowProvider";

const egw = {
	lang: (label : string) => label,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	preference: () => null,
	set_preference: () => {}
};
let lastPreferenceCall : { app : string; key : string; value : any } | null = null;
egw.set_preference = (app : string, key : string, value : any) =>
{
	lastPreferenceCall = {app, key, value};
};
egw.app_name = () => "test";

window.egw = function() { return egw; } as any;
Object.assign(window.egw, egw);

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
	window.addEventListener("error", resizeObserverErrorHandler);
	resizeObserverRejectionHandler = (event : PromiseRejectionEvent) =>
	{
		const message = String((event?.reason && (event.reason.message || event.reason)) || "");
		if(message.includes("ResizeObserver loop completed with undelivered notifications"))
		{
			event.preventDefault();
		}
	};
	window.addEventListener("unhandledrejection", resizeObserverRejectionHandler);
});

after(() =>
{
	if(resizeObserverErrorHandler)
	{
		window.removeEventListener("error", resizeObserverErrorHandler);
		resizeObserverErrorHandler = null;
	}
	if(resizeObserverRejectionHandler)
	{
		window.removeEventListener("unhandledrejection", resizeObserverRejectionHandler);
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
		const el = new Et2Datagrid();
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
		const el = new Et2Datagrid();
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

	it("supports modern and legacy row shorthand expressions in template attributes", async() =>
	{
		const el = new Et2Datagrid();
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
});

describe.skip("Et2Datagrid keyboard navigation", () =>
{
	it("advances active row with ArrowDown in virtualized data", async() =>
	{
		const el = new Et2Datagrid();
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

describe.skip("Et2Datagrid column sizing", () =>
{
	it("keeps static pixel column widths in CSS grid tracks", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = new Et2Datagrid();
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

		const el = new Et2Datagrid();
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

		const el = new Et2Datagrid();
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

describe.skip("Et2Datagrid column preference keys", () =>
{
	it("derives default key from owner tag and row template id", async() =>
	{
		lastPreferenceCall = null;
		const host = document.createElement("et2-nextmatch");
		const el = new Et2Datagrid();
		host.appendChild(el);
		document.body.appendChild(host);
		await el.updateComplete;

		el.templateData = {
			columns: [{key: "a", title: "A", width: "1fr"}],
			rowTemplateId: "addressbook.index.rows",
			rowTemplate: null,
			rowTemplateXml: null,
			rowTemplateAttrMap: {},
			loaderTemplate: null
		} as any;
		el.columns = [{key: "a", title: "A", width: "1fr"}] as any;
		await el.updateComplete;
		el.dispatchEvent(new CustomEvent("et2-columns-changed", {detail: {columns: el.columns}, bubbles: true, composed: true}));

		assert.isNotNull(lastPreferenceCall, "preference should be saved on column change");
		assert.equal(lastPreferenceCall!.key, "nextmatch-addressbook.index.rows", "default key should include owner prefix and row template id");
		assert.equal(lastPreferenceCall!.app, "addressbook", "app name should come from egw app context");

		host.remove();
	});

	it("uses explicit columnPreferenceName override when provided", async() =>
	{
		lastPreferenceCall = null;
		const host = document.createElement("et2-nextmatch");
		const el = new Et2Datagrid();
		el.columnPreferenceName = "my-custom-key";
		host.appendChild(el);
		document.body.appendChild(host);
		await el.updateComplete;

		el.templateData = {
			columns: [{key: "a", title: "A", width: "1fr"}],
			rowTemplateId: "addressbook.index.rows",
			rowTemplate: null,
			rowTemplateXml: null,
			rowTemplateAttrMap: {},
			loaderTemplate: null
		} as any;
		el.columns = [{key: "a", title: "A", width: "1fr"}] as any;
		await el.updateComplete;
		el.dispatchEvent(new CustomEvent("et2-columns-changed", {detail: {columns: el.columns}, bubbles: true, composed: true}));

		assert.isNotNull(lastPreferenceCall, "preference should be saved on column change");
		assert.equal(lastPreferenceCall!.key, "my-custom-key", "custom key override should take precedence");

		host.remove();
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

		const el = new Et2Datagrid();
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

		const el = new Et2Datagrid();
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

		const el = new Et2Datagrid();
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

		const el = new Et2Datagrid();
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

		const el = new Et2Datagrid();
		el.style.height = "100%";
		host.appendChild(el);
		await el.updateComplete;
		(el as any)._requestDispatchDelayMs = 0;

		let fetchCalls = 0;
		el.dataProvider = {
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
		} as any;

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

		const el = new Et2Datagrid();
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

		const el = new Et2Datagrid();
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

		const el = new Et2Datagrid();
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
		const dataProvider = {
			fetchPage: () => new Promise((resolve) =>
			{
				resolvePage = resolve;
			}),
			getQuerySignature: () => "height-stability"
		};

		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = new Et2Datagrid();
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
		const dataProvider = {
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
		};

		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = new Et2Datagrid();
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

	it("requests rows when user scroll reaches unloaded chunk", async() =>
	{
		const calls : number[] = [];
		const dataProvider = {
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
		};

		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = new Et2Datagrid();
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

describe.skip("Et2Datagrid data loading", () =>
{
	it("does not request rows when there are sufficient rows provided initially", async() =>
	{
		let fetchCalls = 0;
		const dataProvider = {
			fetchPage: async() =>
			{
				fetchCalls++;
				return {
					total: 200,
					rows: []
				};
			},
			getQuerySignature: () => "sufficient-initial-rows"
		};

		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = new Et2Datagrid();
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
		const dataProvider = {
			fetchPage: async() =>
			{
				fetchCalls++;
				return {
					total: 5,
					rows: []
				};
			},
			getQuerySignature: () => "initial-equals-total"
		};

		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = new Et2Datagrid();
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
		const dataProvider = {
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
		};

		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = new Et2Datagrid();
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
