import {assert} from "@open-wc/testing";
import {html, LitElement, render} from "lit";
import {Et2Datagrid} from "../Et2Datagrid";
import datagridStyles from "../Et2Datagrid.styles.ts";
import {Et2RowProvider} from "../Et2RowProvider.ts";
import {et2_arrayMgr} from "../../et2_core_arrayMgr";
import {Et2CustomfieldsBase} from "../../Et2Customfields/Et2CustomfieldsBase";
import "../../Et2Customfields/Et2CustomfieldsList";
import {Et2UrlEmail} from "../../Et2Url/Et2UrlEmail";
import "../../Et2Url/Et2UrlEmailReadonly";
import {Et2UrlPhone} from "../../Et2Url/Et2UrlPhone";
import "../../Et2Url/Et2UrlPhoneReadonly.ts";
import {Et2Widget} from "../../Et2Widget/Et2Widget";

const egw = {
	debug: () => {},
	lang: (label : string) => label,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	preference: () => null,
	set_preference: () => {},
	app_name: () => "addressbook",
	link: (url : string) => url,
	hashString: async(value : string) => {
		const data = (new TextEncoder()).encode(value);
		const hashBuffer = await crypto.subtle.digest("SHA-256", data);
		return Array.from(new Uint8Array(hashBuffer)).map(byte => byte.toString(16).padStart(2, "0")).join("");
	}
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

async function waitForDatagridRow(el : Et2Datagrid, rowId : string) : Promise<HTMLElement | null>
{
	for(let i = 0; i < 20; i++)
	{
		const rows = Array.from(el.shadowRoot?.querySelectorAll(`[data-row-id='${rowId}']`) || []) as HTMLElement[];
		if(rows.length > 0)
		{
			return rows[rows.length - 1];
		}
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		await el.updateComplete;
	}
	return null;
}

async function waitForExpandedRow(el : Et2Datagrid, parentRowId : string) : Promise<HTMLElement | null>
{
	for(let i = 0; i < 20; i++)
	{
		const expandedRow = el.shadowRoot?.querySelector(
			`[data-dg-expanded-row='1'][data-parent-row-id='${parentRowId}']`
		) as HTMLElement | null;
		if(expandedRow)
		{
			return expandedRow;
		}
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		await el.updateComplete;
	}
	return null;
}

async function waitForExpandedContext(el : Et2Datagrid, expectedColumnSizes? : string) : Promise<HTMLElement | null>
{
	for(let i = 0; i < 20; i++)
	{
		const contexts = Array.from(el.shadowRoot?.querySelectorAll(".expanded-context") || []) as HTMLElement[];
		const context = contexts[contexts.length - 1] || null;
		if(context && (!expectedColumnSizes || context.dataset.columnSizes === expectedColumnSizes))
		{
			return context;
		}
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		await el.updateComplete;
	}
	return null;
}

async function waitForEmbeddedHostHeight(el : Et2Datagrid, height : string) : Promise<boolean>
{
	for(let i = 0; i < 20; i++)
	{
		if(el.style.height === height)
		{
			return true;
		}
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		await el.updateComplete;
	}
	return false;
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

class Et2DatagridContainerFixture extends HTMLElement
{
	loadFromXML(source : Element)
	{
		for(const child of Array.from(source.childNodes))
		{
			this.appendChild(child.cloneNode(true));
		}
	}
}

if(!customElements.get("et2-dg-container"))
{
	customElements.define("et2-dg-container", Et2DatagridContainerFixture);
}

class Et2DatagridDeferredFixture extends Et2Widget(LitElement)
{
	static get properties()
	{
		return {
			...super.properties,
			active: {type: Boolean}
		};
	}
}

if(!customElements.get("et2-dg-deferred"))
{
	customElements.define("et2-dg-deferred", Et2DatagridDeferredFixture);
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
let originalWindowOnError : OnErrorEventHandler | null = null;
before(() =>
{
	originalResizeObserver = window.ResizeObserver;
	originalWindowOnError = window.onerror;
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
	window.onerror = (message, source, lineno, colno, error) =>
	{
		const text = String(message || error?.message || "");
		if(text.includes("ResizeObserver loop completed with undelivered notifications"))
		{
			return true;
		}
		if(typeof originalWindowOnError === "function")
		{
			return originalWindowOnError.call(window, message, source, lineno, colno, error);
		}
		return false;
	};
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
	window.onerror = originalWindowOnError;
	originalWindowOnError = null;
});

beforeEach(function()
{
	console.info(`[Et2Datagrid.test] START ${this.currentTest?.fullTitle()}`);
});

afterEach(function()
{
	console.info(`[Et2Datagrid.test] END ${this.currentTest?.state || "unknown"} ${this.currentTest?.fullTitle()}`);
});

after(function()
{
	console.info("[Et2Datagrid.test] COMPLETE");
});

describe("Et2Datagrid row rendering", () =>
{
	/**
	 * Contract: the built-in no-results state looks like a full-width row, not a
	 * Shoelace alert.
	 * Setup: render an empty datagrid with a valid column structure so the grid
	 * enters the no-rows state rather than the missing-template state.
	 * Pass: the state wrapper remains available for Nextmatch context-menu
	 * routing, and the fallback content is row-like markup without `sl-alert`.
	 */
	it("renders the default empty state as a row-like placeholder", async() =>
	{
		const el = createDatagrid();
		el.columns = [{key: "name", title: "Name"}] as any;
		el.templateData = {columns: el.columns} as any;
		el.emptyStateText = "Nothing here yet";
		document.body.append(el);
		await el.updateComplete;

		const state = el.shadowRoot!.querySelector(".dg-state.dg-state--empty") as HTMLElement | null;
		const emptyRow = state?.querySelector(".dg-empty-row") as HTMLElement | null;
		const emptyCell = state?.querySelector(".dg-empty-cell") as HTMLElement | null;

		assert.isNotNull(state, "empty state should keep the dg-state context-menu anchor");
		assert.isNotNull(emptyRow, "default no-results fallback should render row-like markup");
		assert.equal(emptyCell?.textContent?.trim(), "Nothing here yet", "empty row should show configured placeholder text");
		assert.isNull(state?.querySelector("sl-alert"), "default no-results fallback should not render a Shoelace alert");
		assert.isNull(state?.querySelector(".dg-empty-action-menu"), "empty action menu button should be hidden by default");

		el.remove();
	});

	/**
	 * Contract: the empty-state action menu button is opt-in and emits a composed
	 * contextmenu event from the empty row.
	 * Setup: render an empty datagrid with the action menu flag enabled, then
	 * click the button.
	 * Pass: the button is present and `contextmenu` is emitted with the click coordinates.
	 */
	it("emits contextmenu from the empty-state action menu button", async() =>
	{
		const el = createDatagrid();
		el.columns = [{key: "name", title: "Name"}] as any;
		el.templateData = {columns: el.columns} as any;
		el.emptyStateActionMenu = true;
		document.body.append(el);
		await el.updateComplete;

		let contextEvent : MouseEvent | null = null;
		let rowReceivedContextMenu = false;
		const emptyRow = el.shadowRoot!.querySelector(".dg-empty-row") as HTMLElement | null;
		emptyRow?.addEventListener("contextmenu", () =>
		{
			rowReceivedContextMenu = true;
		}, {once: true});
		el.addEventListener("contextmenu", (event) =>
		{
			contextEvent = event as MouseEvent;
		}, {once: true});
		const button = el.shadowRoot!.querySelector(".dg-empty-action-menu") as HTMLElement | null;
		assert.isNotNull(button, "empty action menu button should render when enabled");

		button!.dispatchEvent(new MouseEvent("click", {
			bubbles: true,
			cancelable: true,
			composed: true,
			clientX: 44,
			clientY: 55
		}));

		assert.equal(contextEvent?.type, "contextmenu", "button click should emit a contextmenu event");
		assert.equal(contextEvent?.clientX, 44, "contextmenu should keep the click X coordinate");
		assert.equal(contextEvent?.clientY, 55, "contextmenu should keep the click Y coordinate");
		assert.isTrue(rowReceivedContextMenu, "contextmenu should be dispatched from the empty row");
		el.remove();
	});

	/**
	 * Contract: custom `noResults` slot content still replaces the built-in
	 * fallback.
	 * Setup: render an empty datagrid with a slotted custom no-results element.
	 * Pass: the shadow slot receives the custom element while the state wrapper
	 * remains the interaction anchor.
	 */
	it("keeps custom noResults slot content for the empty state", async() =>
	{
		const el = createDatagrid();
		el.columns = [{key: "name", title: "Name"}] as any;
		el.templateData = {columns: el.columns} as any;
		const custom = document.createElement("div");
		custom.slot = "noResults";
		custom.className = "custom-no-results";
		custom.textContent = "Custom empty";
		el.append(custom);
		document.body.append(el);
		await el.updateComplete;

		const state = el.shadowRoot!.querySelector(".dg-state.dg-state--empty") as HTMLElement | null;
		const slot = state?.querySelector("slot[name='noResults']") as HTMLSlotElement | null;
		const assigned = slot?.assignedElements() || [];

		assert.isNotNull(state, "empty state should keep the dg-state context-menu anchor");
		assert.deepEqual(assigned, [custom], "custom noResults content should be assigned to the empty-state slot");

		el.remove();
	});

	/**
	 * Contract: header scrollbar reservation is independent from the column
	 * selection action width.
	 * Setup: inspect the datagrid stylesheet used by the alignment fixture.
	 * Pass: scrollbar reservation defaults to zero and the chooser uses its own
	 * CSS custom property for width.
	 */
	it("does not reserve column chooser width as scrollbar space by default", () =>
	{
		const cssText = datagridStyles.cssText;

		assert.include(
			cssText,
			"--scrollbar-space: 0px;",
			"hidden or overlay scrollbars should not reserve phantom header width"
		);
		assert.match(
			cssText,
			/\.dg-colselection\s*{[\s\S]*width:\s*var\(--column-selection-width\);/,
			"column chooser should keep a fixed clickable width separate from scrollbar reservation"
		);
		assert.notInclude(
			cssText,
			":host(.dg-has-expanders)",
			"enabling expanders should not alter scroll-body layout before rows are expanded"
		);
	});

	it("keeps both expander icon slots rendered", () =>
	{
		const cssText = datagridStyles.cssText;

		assert.match(
			cssText,
			/\.dg-row-expander\s+slot\[name="expand-icon"\],\s*\.dg-row-expander\s+slot\[name="collapse-icon"\]\s*{[\s\S]*display:\s*inline-flex;/,
			"expand and collapse icon slots should both stay rendered"
		);
		assert.notMatch(
			cssText,
			/slot\[name="(?:expand|collapse)-icon"\][^{]*{[^}]*display:\s*none;/,
			"expander icon slots should not be hidden with display none"
		);
		assert.match(
			cssText,
			/\.dg-row-expander--expanded\s+slot\[name="expand-icon"\]\s*{[\s\S]*opacity:\s*0;/,
			"expanded state should visually swap icons without removing either slot"
		);
	});

	it("keeps numeric virtualizer items until rows are expanded", () =>
	{
		const el = createDatagrid();
		el.expansionConfig = {
			isExpandable: (row) => !!row?.data?.is_parent,
			renderExpandedContent: () => "",
			expandedRowIds: new Set()
		};
		el.setInitialRows([
			{id: "row-0", label: "Row 0", is_parent: true},
			{id: "row-1", label: "Row 1"}
		]);
		el.total = 2;

		assert.deepEqual((el as any)._getVirtualItems(2), [0, 1]);

		el.expansionConfig.expandedRowIds!.add("row-0");
		assert.deepEqual((el as any)._getVirtualItems(2), [
			0,
			{type: "expanded", rowIndex: 0, parentRowId: "row-0"},
			1
		]);
	});

	it("retargets row upgrade observation when switching between row and tile view", async() =>
	{
		const host = document.createElement("div");
		document.body.appendChild(host);
		const el = createDatagrid();
		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.setInitialRows([{id: "row-0", label: "Row 0"}]);
		el.total = 1;
		host.appendChild(el);

		await el.updateComplete;
		await waitForDatagridRow(el, "row-0");
		const rowRowsBody = el.shadowRoot!.getElementById("rows");
		assert.equal((el as any)._rowUpgradeObservedRowsBody, rowRowsBody, "row tbody should be observed initially");

		el.view = "tile";
		await el.updateComplete;
		await waitForDatagridRow(el, "row-0");
		const tileRowsBody = el.shadowRoot!.getElementById("rows");
		assert.notEqual(tileRowsBody, rowRowsBody, "tile view should render a different rows container");
		assert.equal((el as any)._rowUpgradeObservedRowsBody, tileRowsBody, "tile rows container should be observed after switch");

		host.remove();
	});

	it("recovers rows left with a stale upgrade queued marker", async() =>
	{
		const host = document.createElement("div");
		document.body.appendChild(host);
		const el = createDatagrid();
		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.setInitialRows([{id: "row-0", label: "Row 0"}]);
		el.total = 1;
		host.appendChild(el);

		await el.updateComplete;
		const row = await waitForDatagridRow(el, "row-0");
		assert.isNotNull(row, "row should render");
		row!.removeAttribute("data-et2dg-upgraded-for");
		row!.setAttribute("data-et2dg-upgrade-queued", "1");
		(el as any)._rowUpgradeQueue.length = 0;

		(el as any)._upgradeRenderedRows();
		assert.equal(row!.getAttribute("data-et2dg-upgrade-queued"), "1", "row should be requeued");
		assert.include((el as any)._rowUpgradeQueue, row, "row should be present in the active queue");

		host.remove();
	});

	it("does not cap virtual height below materialized rows", () =>
	{
		const el = createDatagrid();
		el.setInitialRows([
			{id: "row-0", label: "Row 0"},
			{id: "row-1", label: "Row 1"},
			{id: "row-2", label: "Row 2"},
			{id: "row-3", label: "Row 3"}
		]);
		el.total = 2;

		assert.equal((el as any)._virtualRowCount(), 4);
		assert.deepEqual((el as any)._getVirtualItems((el as any)._virtualRowCount()), [0, 1, 2, 3]);
	});

	/**
	 * Contract: expanded container rows are not data rows for focus, selection,
	 * accessibility synchronization, or rendered-row helpers.
	 * Setup: render a grid with one expanded parent and call the same private
	 * helpers used by keyboard/focus recovery.
	 * Pass: helper methods see only real data rows, and the expanded row keeps
	 * its non-focusable/non-selected container state.
	 */
	it("ignores expanded container rows for data-row focus and accessibility state", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = createDatagrid();
		el.style.height = "100%";
		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.templateData = {columns: el.columns} as any;
		el.expansionConfig = {
			isExpandable: (row) => !!row?.data?.is_parent,
			renderExpandedContent: () => html`<button class="child-button">Child</button>`,
			expandedRowIds: new Set(["row-0"])
		};
		host.appendChild(el);
		await el.updateComplete;
		el.setInitialRows([
			{id: "row-0", label: "Row 0", is_parent: true},
			{id: "row-1", label: "Row 1"}
		]);
		el.total = 2;
		await el.updateComplete;

		const expandedRow = await waitForExpandedRow(el, "row-0");
		assert.isNotNull(expandedRow, "expanded container row should render");
		assert.isFalse(expandedRow!.hasAttribute("data-row-index"), "expanded row should not advertise a data row index");
		assert.isTrue((el as any)._hasRenderedRows(), "real data rows should still be detected");
		assert.isTrue((el as any)._isRowIndexRendered(0), "parent data row should be rendered");
		assert.isTrue((el as any)._isRowIndexRendered(1), "sibling data row should be rendered");
		assert.strictEqual(
			(el as any)._findRenderedRowElement("row-0")?.getAttribute("data-dg-expanded-row"),
			null,
			"row lookup by parent id should return the parent data row, not the expanded container"
		);

		(el as any).activeRowIndex = 0;
		(el as any).activeRowId = "row-0";
		(el as any).selectedRowIds = new Set(["row-0"]);
		(el as any)._syncRowAccessibilityState();
		(el as any)._focusRowByIndex(0, 0, false);

		assert.equal(expandedRow!.getAttribute("aria-selected"), "false", "expanded row should remain unselected");
		assert.equal(expandedRow!.getAttribute("tabindex"), "-1", "expanded row should remain outside row roving tabindex");
		assert.isFalse(expandedRow!.classList.contains("dg-row-active"), "expanded row should not receive active row state");
		assert.equal(
			(el.shadowRoot!.activeElement as HTMLElement | null)?.getAttribute("data-row-index"),
			"0",
			"focus recovery should focus the parent data row"
		);

		host.remove();
	});

	/**
	 * Contract: the lead visible header spans the metadata column and first body
	 * cell, while subsequent headers align with their body cell columns.
	 * Setup: render a fixed alignment fixture with a leading meta column and two
	 * data columns.
	 * Pass: the lead header's right edge matches the first body cell's right
	 * edge, and the second header starts where the second body cell starts.
	 */
	it("aligns visible headers with body cells when lead header spans meta column", async() =>
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
		const secondHeaderCell = el.shadowRoot!.querySelector(".dg-header .dg-col[data-column-key='email']") as HTMLElement | null;
		const row = el.shadowRoot!.querySelector("[data-row-id]") as HTMLElement | null;
		const bodyCell = el.shadowRoot!.querySelector("tbody [data-row-id] td[data-col-key='name']") as HTMLElement | null;
		const secondBodyCell = el.shadowRoot!.querySelector("tbody [data-row-id] td[data-col-key='email']") as HTMLElement | null;
		assert.isNotNull(row, "body row should render");
		assert.isNotNull(headerCell, "first visible header should render");
		assert.isNotNull(secondHeaderCell, "second visible header should render");
		assert.isNotNull(bodyCell, "first visible body cell should render");
		assert.isNotNull(secondBodyCell, "second visible body cell should render");

		const headerRight = Math.round(headerCell!.getBoundingClientRect().right);
		const bodyRight = Math.round(bodyCell!.getBoundingClientRect().right);
		assert.equal(
			headerRight,
			bodyRight,
			"lead header should span the meta column and first body cell"
		);

		const secondHeaderLeft = Math.round(secondHeaderCell!.getBoundingClientRect().left);
		const secondBodyLeft = Math.round(secondBodyCell!.getBoundingClientRect().left);
		assert.equal(
			secondHeaderLeft,
			secondBodyLeft,
			"subsequent headers should align with their body cells"
		);

		host.remove();
	});

	/**
	 * Contract: datagrid row binding applies row-scoped template attributes to
	 * upgraded row widgets.
	 * Setup: build a row template with a transform probe and a row attribute map.
	 * Pass: transformed widget attributes resolve to a displayed non-empty row
	 * value.
	 */
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
	it("preserves non-content array managers while applying row content perspective", async() =>
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
		const prepared = await (provider as any)._prepareRowTemplate(rowTemplate, el.columns as any);
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
	 * Contract: plain readonly descriptions in datagrid rows render as native
	 * text, while descriptions with link behavior keep the full widget.
	 * Setup: prepare a row template with one simple value and linked values.
	 * Pass: the simple value becomes a span and row binding resolves its text.
	 */
	it("uses lightweight native text for simple datagrid row descriptions", async() =>
	{
		const el = createDatagrid();
		const provider = new Et2RowProvider(el as any);
		el.columns = [
			{key: "line1", title: "Line 1", width: "1fr"},
			{key: "line2", title: "Line 2", width: "1fr"},
			{key: "preferred", title: "Preferred", width: "1fr"},
			{key: "description", title: "Description", width: "1fr"}
		] as any;
		const rowTemplate = document.createElement("row");
		rowTemplate.innerHTML = `
			<td><et2-description id="\${row}[line1]" noLang="1" class="name-line"></et2-description></td>
			<td><et2-description id="$[line2]" noLang="1" class="legacy-name-line"></et2-description></td>
			<td><et2-description id="\${row}[preferred]" href="$row_cont[preferred_link]" noLang="1"></et2-description></td>
			<td><et2-description id="\${row}[description]" noLang="1" activateLinks="1"></et2-description></td>
		`;

		const prepared = await (provider as any)._prepareRowTemplate(rowTemplate, el.columns as any);
		const simple = prepared?.template.content.querySelector(".name-line") as HTMLElement | null;
		assert.equal(simple?.localName, "span", "simple descriptions should compile to native text");
		assert.isNull(
			prepared?.template.content.querySelector("span[data-et2nm-id]"),
			"native text should not need row attribute upgrade bookkeeping"
		);
		assert.isNotNull(
			prepared?.template.content.querySelector("et2-description[href]"),
			"linked descriptions should keep the full widget"
		);
		assert.isNotNull(
			prepared?.template.content.querySelector("et2-description[activatelinks]"),
			"activateLinks descriptions should keep the full widget"
		);

		el.templateData = {
			columns: el.columns,
			rowTemplate: prepared?.template,
			rowTemplateXml: prepared?.xml,
			rowTemplateAttrMap: prepared?.attrMap || {},
			loaderTemplate: null
		} as any;
		const row = {
			id: "row-0",
			data: {
				line1: "Lightweight row text",
				line2: "Legacy shorthand text",
				preferred: "Call me",
				preferred_link: "tel:+15551234567"
			}
		};
		const rowElement = (el as any)._buildRowElement(row, 0) as HTMLTableRowElement | null;
		assert.include(
			rowElement?.querySelector(".name-line")?.textContent || "",
			"Lightweight row text",
			"native description text should bind from row data"
		);
		assert.include(
			rowElement?.querySelector(".legacy-name-line")?.textContent || "",
			"Legacy shorthand text",
			"native description text should bind legacy $[field] row placeholders"
		);
		assert.isNotNull(
			rowElement?.querySelector("et2-description[href]"),
			"linked row description should still render as a widget"
		);
		assert.isNotNull(
			rowElement?.querySelector("et2-description[activatelinks]"),
			"activateLinks row description should still render as a widget"
		);
	});

	/**
	 * Contract: row-provider cloning owns child preparation for container
	 * widgets. Container loadFromXML must not load original children before the
	 * row provider appends prepared lightweight children.
	 * Setup: use a custom container whose loadFromXML would clone children if
	 * loadWebComponent() were used.
	 * Pass: only the prepared lightweight child exists.
	 */
	it("does not duplicate children when preparing custom element row containers", async() =>
	{
		const el = createDatagrid();
		const provider = new Et2RowProvider(el as any);
		const rowTemplate = document.createElement("row");
		rowTemplate.innerHTML = `
			<td>
				<et2-dg-container>
					<et2-description id="$[line1]" noLang="1" class="name-line"></et2-description>
				</et2-dg-container>
			</td>
		`;

		const prepared = await (provider as any)._prepareRowTemplate(rowTemplate, [{key: "line1", title: "Line 1"}] as any);
		const container = prepared?.template.content.querySelector("et2-dg-container") as HTMLElement | null;

		assert.equal(
			container?.querySelectorAll(".name-line").length,
			1,
			"container should contain only the prepared lightweight child"
		);
		assert.isNull(
			container?.querySelector("et2-description"),
			"source description child should not also be loaded by the container widget"
		);
	});

	/**
	 * Contract: datagrid rows instantiate readonly widget variants when
	 * etemplate2 has registered a `_ro` custom element.
	 * Setup: prepare a row template with et2-url-email, which has a registered
	 * et2-url-email_ro variant.
	 * Pass: the prepared row uses the readonly tag and keeps source attributes.
	 */
	it("uses registered readonly widget variants in datagrid row templates", async() =>
	{
		const el = createDatagrid();
		const provider = new Et2RowProvider(el as any);
		const rowTemplate = document.createElement("row");
		rowTemplate.innerHTML = `
			<td><et2-url-email id="\${row}[email]" readonly="true" emailDisplay="email"></et2-url-email></td>
		`;

		const prepared = await (provider as any)._prepareRowTemplate(rowTemplate, [{key: "email", title: "Email"}] as any);
		const email = prepared?.template.content.querySelector("et2-url-email_ro") as HTMLElement | null;

		assert.isNotNull(email, "email URL widgets should use the readonly custom element in rows");
		assert.isNull(
			prepared?.template.content.querySelector("et2-url-email"),
			"editable email URL widgets should not be kept for readonly rows"
		);
		assert.equal(
			email?.getAttribute("emailDisplay"),
			"email",
			"readonly widget should keep attributes needed by its renderer"
		);
	});

	/**
	 * Contract: row-scoped readonly email URL widgets show a synchronous
	 * fallback value when preference formatting waits on contact lookup.
	 * Setup: hydrate a datagrid row while emailDisplay="preference" resolves
	 * to a name-based display and hold the contact JSON request pending during
	 * assertions.
	 * Pass: the row still displays the raw email and click actions use the
	 * current row value instead of an empty/stale formatted value.
	 */
	it("hydrates readonly email URL row widgets before async preference formatting resolves", async() =>
	{
		const originalPreference = window.egw.preference;
		const originalJsonq = window.egw.jsonq;
		let resolveContactRequest : (result : Record<string, any>) => void = () => {};
		let contactRequest : Promise<Record<string, any>> | null = null;
		window.egw.preference = () => "onlyname";
		window.egw.jsonq = () =>
		{
			contactRequest = new Promise((resolve) =>
			{
				resolveContactRequest = resolve;
			});
			return contactRequest;
		};

		try
		{
			const el = createDatagrid();
			el.columns = [{key: "email", title: "Email", width: "1fr"}] as any;
			const provider = new Et2RowProvider(el as any);
			const rowTemplate = document.createElement("tr");
			const cell = document.createElement("td");
			cell.innerHTML = `<et2-url-email id="\${row}[email]" readonly="true" emailDisplay="preference"></et2-url-email>`;
			rowTemplate.appendChild(cell);

			const prepared = await (provider as any)._prepareRowTemplate(rowTemplate, el.columns as any);
			(el as any).templateData = {
				columns: el.columns,
				rowTemplate: prepared?.template ?? null,
				rowTemplateXml: prepared?.xml ?? null,
				rowTemplateAttrMap: prepared?.attrMap ?? {}
			};
			const row = {id: "row-0", data: {email: "row@example.com"}};
			const rowElement = (el as any)._buildRowElement(row, 0) as HTMLElement;

			(el as any)._applyRowElementAttributes(rowElement, row.data, 0);
			const email = rowElement.querySelector("et2-url-email_ro") as any;
			assert.isNotNull(email, rowElement.outerHTML);

			let clickedValue = "";
			const originalAction = Et2UrlEmail.action;
			Et2UrlEmail.action = (value) =>
			{
				clickedValue = value;
			};
			try
			{
				email.onclick(new MouseEvent("click"));
			}
			finally
			{
				Et2UrlEmail.action = originalAction;
			}

			assert.equal(email.value, row.data.email, "email should display the raw row value until async formatting resolves");
			assert.equal(clickedValue, row.data.email, "email click should use the current raw row value");
		}
		finally
		{
			resolveContactRequest({});
			await contactRequest;
			window.egw.preference = originalPreference;
			window.egw.jsonq = originalJsonq;
		}
	});

	/**
	 * Contract: readonly URL widgets whose id is changed per row use the resolved
	 * row value for both display and their click action.
	 * Setup: prepare an addressbook-style phone widget using ${row}[field].
	 * Pass: clicking the readonly phone widget dials the current row value.
	 */
	it("hydrates readonly phone URL row widgets when their id is row-scoped", async() =>
	{
		const el = createDatagrid();
		el.setArrayMgr("content", new et2_arrayMgr({phone_label: "Business phone"}));
		el.columns = [{key: "tel_work", title: "Work phone", width: "1fr"}] as any;
		const provider = new Et2RowProvider(el as any);
		const rowTemplate = document.createElement("tr");
		const cell = document.createElement("td");
		cell.innerHTML = `<et2-url-phone id="\${row}[tel_work]" readonly="true" class="telWork" statustext="@phone_label"></et2-url-phone>`;
		rowTemplate.appendChild(cell);

		const prepared = await (provider as any)._prepareRowTemplate(rowTemplate, el.columns as any);
		const preparedPhone = prepared?.template.content.querySelector("et2-url-phone_ro.telWork") as any;
		assert.equal(
			preparedPhone?.getAttribute("statustext"),
			"Business phone",
			"@ attributes should be resolved once in the nextmatch namespace"
		);
		let templateHandlerValue = "";
		const originalActionForTemplateHandler = Et2UrlPhone.action;
		Et2UrlPhone.action = (value) =>
		{
			templateHandlerValue = value;
		};
		try
		{
			preparedPhone.onclick.call({value: "(555) 111-TWO"}, new MouseEvent("click"));
		}
		finally
		{
			Et2UrlPhone.action = originalActionForTemplateHandler;
		}
		assert.equal(
			templateHandlerValue,
			"(555) 111-TWO",
			"phone click handler prepared on the template should use the clicked widget value"
		);
		(el as any).templateData = {
			columns: el.columns,
			rowTemplate: prepared?.template ?? null,
			rowTemplateXml: prepared?.xml ?? null,
			rowTemplateAttrMap: prepared?.attrMap ?? {}
		};
		const row = {id: "row-0", data: {tel_work: "(555) 123-ABCD"}};
		const rowElement = (el as any)._buildRowElement(row, 0) as HTMLElement;

		(el as any)._applyRowElementAttributes(rowElement, row.data, 0);
		const phone = rowElement.querySelector("et2-url-phone_ro.telWork") as any;
		assert.isNotNull(phone, rowElement.outerHTML);
		let clickedValue = "";
		const originalAction = Et2UrlPhone.action;
		Et2UrlPhone.action = (value) =>
		{
			clickedValue = value;
		};
		assert.equal(typeof phone.onclick, "function", "phone widget should have a callable click handler");
		try
		{
			phone.onclick(new MouseEvent("click"));
		}
		finally
		{
			Et2UrlPhone.action = originalAction;
		}

		assert.equal(phone?.localName, "et2-url-phone_ro", "phone URL widgets should use the readonly custom element in rows");
		assert.equal(phone?.value, row.data.tel_work, "dynamic phone id should be hydrated from row data");
		assert.equal(clickedValue, row.data.tel_work, "phone click should use the hydrated row value");
	});

	/**
	 * Contract: row-scoped boolean attributes use Et2Widget deferredProperties,
	 * while still being available to the per-row transform pass.
	 * Setup: prepare a row widget with a boolean property bound to $row_cont.
	 * Pass: the prepared widget and row attribute map both keep the deferred
	 * boolean expression for row-time parsing.
	 */
	it("keeps Et2Widget deferredProperties for row-scoped boolean attributes", async() =>
	{
		const el = createDatagrid();
		const provider = new Et2RowProvider(el as any);
		const rowTemplate = document.createElement("tr");
		const cell = document.createElement("td");
		cell.innerHTML = `<et2-dg-deferred active="$row_cont[active]"></et2-dg-deferred>`;
		rowTemplate.appendChild(cell);

		const prepared = await (provider as any)._prepareRowTemplate(rowTemplate, [{key: "active", title: "Active"}] as any);
		const widget = prepared?.template.content.querySelector("et2-dg-deferred") as any;
		const deferredId = widget?.getAttribute("data-et2nm-id");

		assert.equal(
			widget?.deferredProperties?.active,
			"$row_cont[active]",
			"Et2Widget should defer row-scoped boolean attributes during template preparation"
		);
		assert.equal(
			prepared?.attrMap?.[deferredId]?.active,
			"$row_cont[active]",
			"datagrid row binding should keep deferred boolean attributes for per-row transform"
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
		const prepared = await (provider as any)._prepareRowTemplate(rowTemplate, el.columns as any);
		assert.isNotNull(
			prepared?.template.content.querySelector("et2-customfields-list[data-et2nm-id]"),
			"leaf customfields row widget should keep row-upgrade bookkeeping"
		);
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

			const list = rowElement!.querySelector("et2-customfields-list") as Et2CustomfieldsBase | null;
			assert.isNotNull(list, "row should use the lightweight customfields renderer");
			await list!.updateComplete;

			const fieldEl = list!.querySelector("[data-field='cf_text']") as HTMLElement | null;
			const childWidget = fieldEl?.querySelector("*") as any;
			if(childWidget && typeof childWidget.updateComplete !== "undefined")
			{
				await childWidget.updateComplete;
			}

			assert.deepEqual(
				list!.getVisibleFieldNames(),
				["cf_text"],
				"visible fields should come from shared customfield metadata, not row values"
			);
			// Support either native-text renderers (et2-description) or readonly input widgets (et2-textbox_ro)
			if(childWidget && typeof childWidget.value !== "undefined")
			{
				assert.equal(
					String(childWidget.value),
					"Row customfield value",
					"visible customfield widget should expose the current row value via its value property"
				);
			}
			else
			{
				assert.include(
					fieldEl?.textContent || "",
					"Row customfield value",
					"visible customfield should display the current row value"
				);
			}

			assert.deepEqual(
				list!.value,
				{"#cf_text": "Row customfield value"},
				"row customfield values should include only visible selected customfields"
			);
			assert.isNotOk(
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
	it("applies selected customfield visibility from the header to row renderers", async() =>
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
		const prepared = await (provider as any)._prepareRowTemplate(rowTemplate, el.columns as any);
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

		const list = rowElement!.querySelector("et2-customfields-list") as Et2CustomfieldsBase | null;
		assert.deepEqual(
			list?.fields,
			visibility,
			"row renderer should use the header's full selected customfield visibility map"
		);
	});

	/**
	 * Contract: modern shorthand and legacy row expressions remain supported in
	 * row template attributes.
	 * Setup: build a row template using ${row}[field], $field, $class and
	 * $cat_id placeholders.
	 * Pass: widget transforms receive expected placeholders and row-level classes
	 * resolve from row content.
	 */
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
		const row = {id: "row-0", data: {id: "row-0", class: "primary", cat_id: "3,7", note: "Legacy note"}};
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
		assert.include(rowElement!.className, "cat_7", "`$cat_id` should resolve all category classes");
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
		await new Promise((resolve) => window.setTimeout(resolve, 0));
		let selectionEvents = 0;
		el.addEventListener("et2-selection-changed", () => selectionEvents++);

		await el.refresh(["row-1"], "update" as any);
		await new Promise((resolve) => window.setTimeout(resolve, 0));

		assert.equal(el.rows[0].data.label, "Updated row", "loaded row should be replaced with refreshed row data");
		assert.deepEqual(
			(el as any).selectedRowIds ? Array.from((el as any).selectedRowIds) : [],
			["addressbook::row-1"],
			"selection should remain anchored to the refreshed row id"
		);
		assert.deepEqual(pulsedRowIds, ["addressbook::row-1"], "refresh should schedule a pulse for the updated row");
		assert.equal(selectionEvents, 0, "refreshing selected row data should not emit a selection change");
		assert.isTrue(
			renderedRow.classList.contains("dg-row--refreshed"),
			"visible refreshed rows should receive the refreshed state class"
		);
	});

	/**
	 * Contract: if a refreshed visible row no longer qualifies as expandable,
	 * its live expansion state is pruned while the data row remains visible.
	 * Setup: seed one expanded parent row, then refresh it with non-parent data.
	 * Pass: the expanded container disappears and the controlled expanded-id set
	 * no longer contains the row id.
	 */
	it("collapses a refreshed row that no longer qualifies as expandable", async() =>
	{
		const el = createDatagrid();
		const expandedRowIds = new Set(["addressbook::row-1"]);
		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.templateData = {columns: el.columns} as any;
		el.expansionConfig = {
			isExpandable: (row) => !!row?.data?.is_parent,
			renderExpandedContent: () => html`<div class="expanded-context">Child rows</div>`,
			expandedRowIds,
			onExpandedRowIdsChanged: (nextExpandedRowIds) =>
			{
				expandedRowIds.clear();
				nextExpandedRowIds.forEach((id) => expandedRowIds.add(id));
				el.expansionConfig!.expandedRowIds = expandedRowIds;
			}
		};
		el.dataProvider = createDatagridDataProvider({
			refresh: async() => ({
				rows: [{id: "addressbook::row-1", data: {uid: "addressbook::row-1", label: "No children", is_parent: false}}],
				removedRowIds: []
			})
		}) as any;
		el.setInitialRows([{uid: "addressbook::row-1", label: "Parent row", is_parent: true}]);
		el.total = 1;

		assert.deepEqual(
			(el as any)._getVirtualItems(1),
			[0, {type: "expanded", rowIndex: 0, parentRowId: "addressbook::row-1"}],
			"expanded render item should exist before refresh"
		);

		await el.refresh(["row-1"], "update" as any);

		assert.isFalse(expandedRowIds.has("addressbook::row-1"), "refresh pruning should remove the row from expansion state");
		assert.deepEqual(
			(el as any)._getVirtualItems(1),
			[0],
			"expanded render item should be removed after the refreshed row is no longer expandable"
		);
		assert.equal(el.rows[0].data.label, "No children", "the refreshed data row should remain visible");
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
	/**
	 * Contract: keyboard navigation advances active row state without relying on
	 * contiguous DOM rows.
	 * Setup: seed a large virtualized row set, move active state to a middle row,
	 * then send ArrowDown.
	 * Pass: active row index and id advance by exactly one row.
	 */
	it("advances active row with ArrowDown in virtualized data", async() =>
	{
		const el = createDatagrid();
		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.templateData = {columns: el.columns} as any;
		el.setInitialRows(Array.from({length: 200}, (_v, index) => ({id: `row-${index}`, label: `Row ${index}`})));
		el.total = 200;
		const startIndex = 20;
		(el as any)._moveActiveRow(startIndex, false);
		(el as any)._handleTableKeydown(new KeyboardEvent("keydown", {key: "ArrowDown"}));

		assert.equal((el as any).activeRowIndex, startIndex + 1, "activeRowIndex should advance by exactly one row");
		assert.equal((el as any).activeRowId, `row-${startIndex + 1}`, "active row id should advance by exactly one row");
	});

	/**
	 * Contract: handled keyboard navigation belongs to the focused grid.
	 * Setup: seed a child-like grid, then send a cancelable ArrowDown event with
	 * a spyable stopPropagation method.
	 * Pass: the grid advances its own active row and stops the event before a
	 * parent grid can also process it.
	 */
	it("stops propagation for handled row navigation keys", async() =>
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
		await el.updateComplete;
		(el as any)._moveActiveRow(0, false);

		let bubbledToHost = false;
		el.addEventListener("keydown", () =>
		{
			bubbledToHost = true;
		});
		const event = new KeyboardEvent("keydown", {key: "ArrowDown", bubbles: true, composed: true, cancelable: true});
		const table = el.shadowRoot!.querySelector("table")!;
		table.dispatchEvent(event);

		assert.isTrue(event.defaultPrevented, "handled ArrowDown should prevent native page scrolling");
		assert.isFalse(bubbledToHost, "handled ArrowDown should not bubble into a parent datagrid");
		assert.equal((el as any).activeRowId, "row-1", "active row should advance within the handling grid");

		host.remove();
	});

});

describe("Et2Datagrid column sizing", () =>
{
	/**
	 * Contract: static pixel column widths are preserved in the CSS grid track
	 * definition.
	 * Setup: render a datagrid with one unitless width and one explicit px width.
	 * Pass: computed --column-sizes contains normalized pixel tracks for both.
	 */
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

	/**
	 * Contract: relative column widths stay relative in the CSS grid track
	 * definition.
	 * Setup: render a datagrid with percentage and fr column widths.
	 * Pass: computed --column-sizes converts percentage to fr and preserves fr.
	 */
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

	/**
	 * Contract: minWidth accepts both unitless and explicit pixel values.
	 * Setup: render percentage and pixel columns with unitless and px minWidth.
	 * Pass: computed --column-sizes contains minmax() tracks with pixel minimums.
	 */
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

describe("Et2Datagrid selection mode", () =>
{
	/**
	 * Contract: initial keyboard focus state does not imply selection.
	 * Setup: render a two-row grid and reconcile row state.
	 * Pass: first row is active, selected row set is empty and aria-selected is
	 * false when the row is rendered.
	 */
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

	/**
	 * Contract: arrow-key navigation changes active row without selecting it.
	 * Setup: render a three-row grid with no initial selection and send ArrowDown.
	 * Pass: active row moves to the second row while selected row set stays empty.
	 */
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

		(el as any)._handleTableKeydown(new KeyboardEvent("keydown", {key: "ArrowDown"}));
		await el.updateComplete;

		assert.equal((el as any).activeRowIndex, 1, "second row should be active after ArrowDown");
		assert.equal((el as any).activeRowId, "row-1", "active row id should move to second row");
		assert.equal((el as any).selectedRowIds.size, 0, "ArrowDown should not select rows");

		host.remove();
	});

	/**
	 * Contract: keyboard navigation remains available after scroll focus recovery.
	 * Setup: focus an app container, dispatch a datagrid body scroll, then send
	 * ArrowDown.
	 * Pass: active row advances to the next row.
	 */
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

		(el as any)._handleTableKeydown(new KeyboardEvent("keydown", {key: "ArrowDown"}));
		await el.updateComplete;

		assert.equal((el as any).activeRowIndex, 1, "second row should be active after ArrowDown");
		assert.equal((el as any).activeRowId, "row-1", "active row id should move to second row");

		app.remove();
		host.remove();
	});

	/**
	 * Contract: scroll handling must not steal focus from external controls but
	 * must still allow grid navigation when focus is on the app container.
	 * Setup: scroll once while an external input is focused, then scroll with an
	 * app container focused and send ArrowDown.
	 * Pass: external input keeps focus and app-container keyboard navigation
	 * advances the active row.
	 */
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

		(el as any)._handleTableKeydown(new KeyboardEvent("keydown", {key: "ArrowDown"}));
		await el.updateComplete;
		assert.equal((el as any).activeRowIndex, 1, "active row should advance after ArrowDown");
		assert.equal((el as any).activeRowId, "row-1", "active row id should move to second row");

		app.remove();
		host.remove();
	});

	/**
	 * Contract: fetched rows follow the same active-versus-selected behavior as
	 * preloaded rows.
	 * Setup: fetch initial rows through the data provider, then send ArrowDown.
	 * Pass: active row moves to the second fetched row and selection stays empty.
	 */
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

		(el as any)._handleTableKeydown(new KeyboardEvent("keydown", {key: "ArrowDown"}));
		await el.updateComplete;

		assert.equal((el as any).activeRowIndex, 1, "second row should become active after ArrowDown");
		assert.equal((el as any).activeRowId, "row-1", "active row id should move to second fetched row");
		assert.equal((el as any).selectedRowIds.size, 0, "ArrowDown should not select fetched rows");

		host.remove();
	});

	/**
	 * Contract: pointer selection follows the configured selectionMode.
	 * Setup: exercise none, single and multiple modes on the same three-row grid.
	 * Pass: none mode ignores changes, single mode replaces selection and
	 * multiple mode supports additive toggle and range selection.
	 */
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

	/**
	 * Contract: Ctrl+A selects all rendered rows only in multiple selection mode.
	 * Setup: render a three-row grid in multiple mode and send a cancelable
	 * Ctrl+A key event.
	 * Pass: native select-all is prevented, allSelected is set and all row ids are
	 * selected.
	 */
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
		(el as any)._handleTableKeydown(event);

		assert.isTrue(event.defaultPrevented, "Ctrl+A should prevent native browser select-all");
		assert.isTrue(el.allSelected, "Ctrl+A should set allSelected");
		assert.sameMembers(Array.from(el.selectedRowIds), ["row-0", "row-1", "row-2"], "Ctrl+A should select all rendered rows");

		host.remove();
	});

	/**
	 * Contract: Ctrl+A is not intercepted outside multiple selection mode.
	 * Setup: render a two-row grid in single mode with one selected row, then send
	 * a cancelable Ctrl+A key event.
	 * Pass: event default is not prevented, allSelected remains false and
	 * selection is unchanged.
	 */
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
		(el as any)._handleTableKeydown(event);

		assert.isFalse(event.defaultPrevented, "Ctrl+A should not be intercepted outside multiple mode");
		assert.isFalse(el.allSelected, "single mode should not set allSelected from Ctrl+A");
		assert.sameMembers(Array.from(el.selectedRowIds), ["row-0"], "single mode selection should remain unchanged");

		host.remove();
	});
});

describe("Et2Datagrid virtual height stability", () =>
{
	/**
	 * Contract: embedded subgrids start at a one-row reservation while loading,
	 * then grow after the virtualizer can report/render actual content.
	 */
	it("uses one row as the embedded virtualized loading base height", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = createDatagrid();
		el.embeddedVirtualized = true;
		el.noVisibleHeader = true;
		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.templateData = {columns: el.columns} as any;
		el.fetching = true;
		host.appendChild(el);

		await el.updateComplete;
		await el.updateComplete;

		assert.equal(el.style.height, "44px", "embedded loading grid should reserve one base row before rows render");
		assert.equal((el as any)._virtualRowCount(), 1, "embedded loading grid should only render one loading row");

		host.remove();
	});

	/**
	 * Contract: embedded virtualized grids keep the virtualizer-owned tbody height
	 * for small fully materialized child result sets, while keeping their body
	 * overflow visible so the ancestor grid remains the only scrollport.
	 *
	 * Setup: render a child-style datagrid whose loaded rows match its total.
	 *
	 * Pass: tbody has a concrete explicit height matching the rendered rows, and
	 * the internal body does not expose its own vertical scrollbar.
	 */
	it("preserves tbody height for fully loaded small embedded virtualized grids", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = createDatagrid();
		el.embeddedVirtualized = true;
		el.noVisibleHeader = true;
		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.templateData = {columns: el.columns} as any;
		const initialRows = Array.from({length: 10}, (_v, index) => ({id: `row-${index}`, label: `Row ${index}`}));
		el.setInitialRows(initialRows);
		el.total = initialRows.length;
		host.appendChild(el);

		await el.updateComplete;
		await waitForDatagridRow(el, "row-0");
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		await el.updateComplete;

			const body = el.shadowRoot!.querySelector(".dg-body") as HTMLElement;
			const root = el.shadowRoot!.querySelector(".dg-root") as HTMLElement;
			const rowsBody = el.shadowRoot!.querySelector("tbody") as HTMLElement;
			const renderedRows = Array.from(rowsBody.querySelectorAll(":scope > tr[data-row-id]")) as HTMLElement[];
			const explicitTbodyHeight = rowsBody.style.height || rowsBody.style.minHeight;
			const hostHeightSynced = await waitForEmbeddedHostHeight(el, explicitTbodyHeight);
			const rowsBodyRect = rowsBody.getBoundingClientRect();
			const rowBounds = renderedRows.map((row) => row.getBoundingClientRect());
			const renderedRowsHeight = Math.ceil(
				Math.max(...rowBounds.map((rect) => rect.bottom)) -
				Math.min(Math.min(...rowBounds.map((rect) => rect.top)), rowsBodyRect.top)
			);

			assert.match(explicitTbodyHeight, /^\d+px$/, "tbody should keep the virtualizer's explicit height");
			assert.isAtLeast(
				parseInt(explicitTbodyHeight, 10),
				renderedRowsHeight,
				"tbody height should not be shorter than the rendered child row stack"
			);
		assert.isTrue(hostHeightSynced, "embedded grid host height should match the virtualizer-owned tbody height");
		assert.equal(
			root.style.getPropertyValue("--embedded-virtualized-height"),
			explicitTbodyHeight,
			"embedded grid root height variable should match the virtualizer-owned tbody height"
		);
		assert.equal(getComputedStyle(body).overflowY, "visible", "embedded grid body should not be its own scrollport");

		host.remove();
	});

	/**
	 * Contract: embedded child grids remeasure after row widget upgrades, because
	 * the first virtualizer height can be a one-row loading estimate.
	 *
	 * Setup: render a fully loaded embedded grid, force its tbody/host height back
	 * to the one-row estimate, then run the row-upgrade drain path.
	 *
	 * Pass: the next frame restores tbody and host height to at least the rendered
	 * child-row stack height.
	 */
	it("remeasures embedded virtualized height after row upgrades drain", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = createDatagrid();
		el.embeddedVirtualized = true;
		el.noVisibleHeader = true;
		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.templateData = {columns: el.columns} as any;
		const initialRows = Array.from({length: 5}, (_v, index) => ({id: `row-${index}`, label: `Row ${index}`}));
		el.setInitialRows(initialRows);
		el.total = initialRows.length;
		host.appendChild(el);

		await el.updateComplete;
		await waitForDatagridRow(el, "row-0");
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
			await el.updateComplete;

			const rowsBody = el.shadowRoot!.querySelector("tbody") as HTMLElement;
			const renderedRows = Array.from(rowsBody.querySelectorAll(":scope > tr[data-row-id]")) as HTMLElement[];
			const rowsBodyRect = rowsBody.getBoundingClientRect();
			const rowBounds = renderedRows.map((row) => row.getBoundingClientRect());
			const renderedRowsHeight = Math.ceil(
				Math.max(...rowBounds.map((rect) => rect.bottom)) -
				Math.min(Math.min(...rowBounds.map((rect) => rect.top)), rowsBodyRect.top)
			);

			rowsBody.style.height = "44px";
			el.style.height = "44px";
			(el as any)._embeddedVirtualizedHostHeight = "44px";

			(el as any)._processRowUpgradeQueue();
			await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
			await el.updateComplete;
			const hostHeightSynced = await waitForEmbeddedHostHeight(el, rowsBody.style.height);

			assert.isAtLeast(parseInt(rowsBody.style.height, 10), renderedRowsHeight, "tbody height should cover rendered child rows");
			assert.isTrue(hostHeightSynced, "embedded grid host height should match the corrected tbody height");

			host.remove();
		});

		/**
	 * Contract: expanded cells must not inherit the normal data-cell max-height
	 * rule, because expanded rows host nested grids/detail content.
	 */
	it("does not apply normal data-cell max height to expanded cells", () =>
	{
		const cssText = String((datagridStyles as any).cssText || datagridStyles);
		assert.match(
			cssText,
			/\.dg-body\s+tbody\s+td\.dg-expanded-cell\s*{[\s\S]*max-height:\s*none;/,
			"expanded cells need a selector specific enough to beat the generic tbody td max-height rule"
		);
	});

	/**
	 * Contract: expanded-row animation respects the browser motion preference.
	 */
	it("guards expanded-row reveal animation with reduced-motion preference", () =>
	{
		const cssText = String((datagridStyles as any).cssText || datagridStyles);
		assert.match(
			cssText,
			/@media\s*\(prefers-reduced-motion:\s*no-preference\)\s*{[\s\S]*\.dg-row-expanded[\s\S]*animation:\s*dg-expanded-row-reveal/,
			"expanded-row reveal animation should only run when reduced motion is not requested"
		);
		assert.match(
			cssText,
			/\.dg-row-expanded\s+\.dg-expanded-content[\s\S]*animation:\s*dg-expanded-content-reveal/,
			"expanded content should get the reveal animation inside the reduced-motion media query"
		);
	});

	/**
	 * Contract: placeholder replacement must not shrink the virtual scroll range.
	 * Setup: seed initial rows, request a later chunk with a deferred provider
	 * response and compare scrollHeight before, during and after fetch.
	 * Pass: in-flight and final scroll heights stay at or above baseline.
	 */
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

	/**
	 * Contract: missing chunks deeper than the first page are requested on demand.
	 * Setup: seed the first chunk and manually request a row index in a later
	 * chunk.
	 * Pass: already materialized chunk is not fetched and the later chunk start is
	 * requested.
	 */
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

	/**
	 * Contract: scrolling into an unloaded virtualized area requests the matching
	 * data chunk.
	 * Setup: seed the first chunk, render a virtual row in a later chunk and
	 * dispatch scroll.
	 * Pass: provider is called with the later chunk start.
	 */
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

		/**
		 * Contract: when virtualization exposes a deep unloaded row, the datagrid
		 * fetches that chunk and can render the fetched row at its absolute index.
		 * Setup: preload only the first page, ask the virtual row renderer for row
		 * 160, then wait for the queued page fetch.
		 * Pass: row 160 is materialized in rowsByIndex and a second render returns
		 * row-160 instead of the placeholder/first-page row.
		 */
		it("renders fetched rows for deep virtual indexes", async() =>
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
				getQuerySignature: () => "deep-index-renders-fetched-row"
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

			const scratchTable = document.createElement("table");
			const scratchBody = document.createElement("tbody");
			scratchTable.appendChild(scratchBody);
			document.body.appendChild(scratchTable);

			render((el as any)._renderVirtualRow(160), scratchBody);
			const placeholder = scratchBody.querySelector("[data-row-id='placeholder:160']") as HTMLElement | null;
			assert.isNotNull(placeholder, "first deep render should expose a placeholder and queue fetch");

			(el as any)._processQueuedRequests();
			await new Promise((resolve) => window.setTimeout(resolve, 0));
			await el.updateComplete;

			assert.deepEqual(calls, [150], "deep render should fetch the chunk containing row 160");
			assert.equal((el as any)._rowsByIndex[160]?.id, "row-160", "deep fetched row should be stored by absolute index");

			render((el as any)._renderVirtualRow(160), scratchBody);
			const rendered = scratchBody.querySelector("[data-row-id='row-160']") as HTMLElement | null;
			assert.isNotNull(rendered, "second deep render should output row 160");
			assert.equal(rendered!.getAttribute("data-row-index"), "160", "second deep render should keep the absolute row index");
			assert.isNull(scratchBody.querySelector("[data-row-id='placeholder:160']"), "second deep render should no longer output a placeholder");

			(el as any)._virtualize?.element(160)?.scrollIntoView({block: "start"});
			await (el as any)._virtualize?.layoutComplete;
			await el.updateComplete;
			const liveRendered = await waitForDatagridRow(el, "row-160");
			assert.isNotNull(liveRendered, "live datagrid shadow DOM should render row 160 after virtualizer scroll");
			assert.equal(liveRendered!.getAttribute("data-row-index"), "160", "live rendered row should keep the absolute row index");
			assert.isNull(
				el.shadowRoot!.querySelector("[data-row-id='placeholder:160']"),
				"live datagrid shadow DOM should not keep row 160 as a placeholder"
			);

			scratchTable.remove();
			host.remove();
		});
	});

describe("Et2Datagrid data loading", () =>
{
	/**
	 * Contract: loadMore does not fetch data already covered by initial rows.
	 * Setup: preload enough rows to cover the current chunk and call loadMore.
	 * Pass: provider fetchPage is not called.
	 */
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

	/**
	 * Contract: loadMore does not fetch past the known total row count.
	 * Setup: preload exactly total rows in a viewport that could otherwise ask for
	 * more.
	 * Pass: provider fetchPage is not called and preloaded rows remain intact.
	 */
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

	/**
	 * Contract: an empty grid fetches its first page when loadMore is requested.
	 * Setup: configure a provider with no preloaded rows and call loadMore.
	 * Pass: provider fetchPage is called once and fetched rows are rendered.
	 */
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
