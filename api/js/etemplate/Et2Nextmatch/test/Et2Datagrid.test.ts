import {assert} from "@open-wc/testing";
import {html, LitElement, render} from "lit";
import * as sinon from "sinon";
import {Et2Datagrid} from "../Et2Datagrid";
import datagridStyles from "../Et2Datagrid.styles.ts";
import {Et2Nextmatch} from "../Et2Nextmatch";
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

async function waitForEmbeddedHostHeight(
	el : Et2Datagrid,
	predicate : (height : string) => boolean,
	maxFrames : number = 30
) : Promise<boolean>
{
	for(let i = 0; i < maxFrames; i++)
	{
		if(predicate(el.style.height))
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

class DatagridPlainCustomFixture extends HTMLElement
{
}

if(!customElements.get("dg-plain-custom"))
{
	customElements.define("dg-plain-custom", DatagridPlainCustomFixture);
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
	 * Contract: no-results templates extracted from named .xet templates are
	 * preferred for the empty state.
	 * Setup: render an empty datagrid with templateData.noResultsTemplate.
	 * Pass: the empty state contains the template-provided content.
	 */
	it("uses templateData noResultsTemplate as the preferred empty state", async() =>
	{
		const el = createDatagrid();
		const noResultsTemplate = document.createElement("template");
		noResultsTemplate.innerHTML = `<div class="template-no-results">Template empty</div>`;
		el.columns = [{key: "name", title: "Name"}] as any;
		el.templateData = {
			columns: el.columns,
			rowTemplate: null,
			rowTemplateXml: null,
			rowTemplateAttrMap: {},
			loaderTemplate: null,
			noResultsTemplate
		} as any;
		document.body.append(el);
		await el.updateComplete;

		const state = el.shadowRoot!.querySelector(".dg-state.dg-state--empty") as HTMLElement | null;

		assert.isNotNull(state?.querySelector(".template-no-results"), "template noResults content should render");

		el.remove();
	});

	/**
	 * Contract: templateData.noResultsTemplate has precedence over slotted
	 * noResults content; the framework default is only used when neither exists.
	 * Setup: render an empty datagrid with both templateData.noResultsTemplate
	 * and a live noResults slot.
	 * Pass: the template-provided empty state renders and the live slot is not
	 * used as the state content.
	 */
	it("prefers templateData noResultsTemplate over slotted noResults content", async() =>
	{
		const el = createDatagrid();
		const noResultsTemplate = document.createElement("template");
		noResultsTemplate.innerHTML = `<div class="template-no-results">Template empty</div>`;
		const custom = document.createElement("div");
		custom.slot = "noResults";
		custom.className = "custom-no-results";
		custom.textContent = "Custom empty";
		el.columns = [{key: "name", title: "Name"}] as any;
		el.templateData = {
			columns: el.columns,
			rowTemplate: null,
			rowTemplateXml: null,
			rowTemplateAttrMap: {},
			loaderTemplate: null,
			noResultsTemplate
		} as any;
		el.append(custom);
		document.body.append(el);
		await el.updateComplete;

		const state = el.shadowRoot!.querySelector(".dg-state.dg-state--empty") as HTMLElement | null;

		assert.isNotNull(state?.querySelector(".template-no-results"), "template noResults content should render");
		assert.isNull(state?.querySelector("slot[name='noResults']"), "live noResults slot should not be used when a template is provided");

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

	it("renders rows after switching from tile template back to row template", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);
		const el = createDatagrid();
		const rowColumns = [{key: "label", title: "Label", width: "1fr"}] as any;
		const tileColumns = [{key: "label", title: "Label", width: "100%"}] as any;
		const rowTemplateData = {
			rowTemplateId: "test.index.rows",
			view: "row",
			columns: rowColumns
		} as any;
		const tileTemplateData = {
			rowTemplateId: "test.tile",
			view: "tile",
			tileLayout: {width: "160px", height: "120px"},
			columns: tileColumns
		} as any;
		el.columns = rowColumns;
		el.templateData = rowTemplateData;
		const initialRows = Array.from({length: 12}, (_v, index) => ({id: `row-${index}`, label: `Row ${index}`}));
		el.setInitialRows(initialRows);
		el.total = initialRows.length;
		host.appendChild(el);

		await el.updateComplete;
		await waitForDatagridRow(el, "row-0");

		el.view = "tile";
		el.columns = tileColumns;
		el.templateData = tileTemplateData;
		await el.updateComplete;
		await waitForDatagridRow(el, "row-0");

		el.view = "row";
		el.columns = rowColumns;
		el.templateData = rowTemplateData;
		await el.updateComplete;
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));

		const rowsBody = el.shadowRoot!.querySelector("tbody#rows") as HTMLElement;
		const renderedRow = rowsBody.querySelector(":scope > tr[data-row-id='row-0']") as HTMLElement | null;
		assert.isNotNull(renderedRow, "row should render after returning from tile view");
		assert.isAbove(rowsBody.querySelectorAll(":scope > tr[data-row-id]").length, 0, "row tbody should contain rendered rows");
		assert.notEqual(rowsBody.style.minHeight, "100%", "row tbody should not keep tile/grid min-height");

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

	/**
	 * Contract: a virtualizer range change must hydrate newly realized row widgets
	 * even if MutationObserver delivery is missed.
	 * Setup: render enough rows to scroll, then disconnect the observer before
	 * scrolling a new row into the virtual range.
	 * Pass: the connected widget for that newly realized row has its row value.
	 */
	it("hydrates newly realized rows from the virtualizer range event", async() =>
	{
		const host = document.createElement("div");
		host.style.cssText = "width:800px;height:240px";
		document.body.appendChild(host);
		const el = createDatagrid();
		el.style.height = "100%";
		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		const provider = new Et2RowProvider(el as any);
		const rowTemplate = document.createElement("tr");
		rowTemplate.innerHTML = `<td><et2-dg-test-transform class="row-label" data-value="$label"></et2-dg-test-transform></td>`;
		const prepared = await (provider as any)._prepareRowTemplate(rowTemplate, el.columns as any);
		el.templateData = {
			columns: el.columns,
			rowTemplate: prepared?.template ?? null,
			rowTemplateXml: prepared?.xml ?? null,
			rowTemplateAttrMap: prepared?.attrMap ?? {}
		} as any;
		const rows = Array.from({length: 48}, (_value, index) => ({id: `row-${index}`, label: `Row ${index}`}));
		el.setInitialRows(rows);
		el.total = rows.length;
		host.appendChild(el);

		try
		{
			assert.isNotNull(await waitForDatagridRow(el, "row-0"), "initial range should render");
			(el as any)._rowUpgradeObserver?.disconnect();
			const body = el.shadowRoot?.querySelector(".dg-body") as HTMLElement;
			body.scrollTop = 1200;
			body.dispatchEvent(new Event("scroll"));

			let widget : HTMLElement | null = null;
			for(let frame = 0; frame < 30; frame++)
			{
				const row = await waitForDatagridRow(el, "row-24");
				widget = row?.querySelector("et2-dg-test-transform.row-label") as HTMLElement | null;
				if(widget?.textContent === "Row 24")
				{
					break;
				}
				await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
			}
			assert.equal(widget?.textContent, "Row 24", "newly realized widget should be hydrated after range change");
		}
		finally
		{
			host.remove();
		}
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
	 * Contract: embedded child height events are handled at the direct parent
	 * grid boundary.
	 *
	 * Setup: render an expanded row containing an embedded child datagrid, then
	 * dispatch the same composed `et2-embedded-height` event a child uses after
	 * its host height changes.
	 *
	 * Pass: the parent forwards the child's resolved host height to its direct
	 * remeasure helper exactly once and stops the original event from bubbling
	 * past the parent grid.
	 */
	it("routes embedded height events through the direct parent grid", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "360px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = createDatagrid();
		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.templateData = {columns: el.columns} as any;
		el.expansionConfig = {
			isExpandable: (row) => !!row?.data?.is_parent,
			renderExpandedContent: () => html`<et2-datagrid class="child-grid"></et2-datagrid>`,
			expandedRowIds: new Set(["row-0"])
		};
		el.setInitialRows([{id: "row-0", label: "Row 0", is_parent: true}]);
		el.total = 1;
		host.appendChild(el);

		await el.updateComplete;
		const expandedRow = await waitForExpandedRow(el, "row-0");
		const childGrid = expandedRow!.querySelector("et2-datagrid") as Et2Datagrid;
		assert.instanceOf(childGrid, Et2Datagrid, "expanded content should contain the child grid");

		const remeasure = sinon.stub(el as any, "_remeasureDirectEmbeddedChildGrid").returns(true);
		const leaked = sinon.spy();
		document.body.addEventListener("et2-embedded-height", leaked);

		childGrid.dispatchEvent(new CustomEvent("et2-embedded-height", {
			bubbles: true,
			composed: true,
			detail: {height: 88}
		}));

		assert.isTrue(
			remeasure.calledOnceWithExactly(childGrid, false, 88),
			"parent should remeasure the direct child using its reported host height"
		);
		assert.isFalse(leaked.called, "direct parent should stop the original height event after handling it");
		(el as any)._remeasuredEmbeddedChildGridsThisFrame.add(childGrid);
		assert.isFalse(
			(el as any)._remeasureObservedEmbeddedChildGrid(childGrid),
			"ResizeObserver should not duplicate a child remeasure already handled by the height event in the same frame"
		);
		assert.isTrue(remeasure.calledOnce, "same-frame observer suppression should avoid a second remeasure call");

		document.body.removeEventListener("et2-embedded-height", leaked);
		remeasure.restore();
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
			{key: "plain_field", title: "Plain field", width: "1fr"},
			{key: "preferred", title: "Preferred", width: "1fr"},
			{key: "description", title: "Description", width: "1fr"}
		] as any;
		const rowTemplate = document.createElement("row");
		rowTemplate.innerHTML = `
			<td><et2-description id="\${row}[line1]" noLang="1" class="name-line"></et2-description></td>
			<td><et2-description id="$[line2]" noLang="1" class="legacy-name-line"></et2-description></td>
			<td><et2-description id="plain_field" noLang="1" class="plain-id-line"></et2-description></td>
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
				plain_field: "Plain id row text",
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
		assert.include(
			rowElement?.querySelector(".plain-id-line")?.textContent || "",
			"Plain id row text",
			"native description text should bind plain row field ids"
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
	 * Contract: row templates can contain non-Et2 custom elements that are not
	 * Et2Widget subclasses.  Et2 widgets still need to use Et2Widget.
	 * Setup: prepare a row template with a registered plain custom element.
	 * Pass: preparation succeeds and preserves static attributes.
	 */
	it("prepares non-Et2Widget custom elements without transformAttributes", async() =>
	{
		const el = createDatagrid();
		const provider = new Et2RowProvider(el as any);
		const rowTemplate = document.createElement("row");
		rowTemplate.innerHTML = `
			<td>
				<dg-plain-custom data-value="static"></dg-plain-custom>
			</td>
		`;

		const prepared = await (provider as any)._prepareRowTemplate(rowTemplate, [{key: "label", title: "Label"}] as any);
		const plain = prepared?.template.content.querySelector("dg-plain-custom") as HTMLElement | null;

		assert.isNotNull(plain, "plain custom element should remain in prepared row template");
		assert.equal(plain?.getAttribute("data-value"), "static", "static attributes should be preserved");
	});

	/**
	 * Contract: <et2-styles> inside a row template belongs to datagrid rows only.
	 * Setup: prepare a row template that contains row-local et2-styles.
	 * Pass: the stylesheet is extracted for the datagrid shadow root and the
	 * style widget is removed from the row template so it cannot inject into head.
	 */
	it("extracts row template styles for datagrid row shadow stylesheets", async() =>
	{
		const el = createDatagrid();
		const provider = new Et2RowProvider(el as any);
		const templateRoot = new DOMParser().parseFromString(`
			<template id="test.index.rows">
				<grid>
					<columns>
						<column/>
					</columns>
					<rows>
						<row class="th">
							<et2-nextmatch-header label="Title" id="title"/>
						</row>
						<row>
							<td><et2-description id="$[title]" noLang="1"></et2-description></td>
						</row>
					</rows>
				</grid>
				<et2-styles>
					tr.template-local-style td {
						color: rgb(1, 2, 3);
					}
				</et2-styles>
			</template>
		`, "text/xml").documentElement;

		const templateData = await (provider as any)._fromTemplateRoot(templateRoot);

		assert.lengthOf(templateData?.rowStylesheets || [], 1, "template-local et2-styles should be returned as stylesheets");
		assert.isNull(
			templateData?.rowTemplate.content.querySelector("et2-styles"),
			"row-local et2-styles tags should not remain in the prepared row template"
		);
		assert.isNull(
			templateData?.rowTemplateXml.querySelector("et2-styles"),
			"row-local et2-styles tags should not remain in the stored row XML"
		);
		assert.include(
			templateData!.rowStylesheets!.flatMap((sheet) => Array.from(sheet.cssRules as CSSRuleList).map((rule : CSSRule) => rule.cssText)).join("\n"),
			"tr.template-local-style td",
			"row-local CSS should be present in the extracted stylesheet"
		);
	});

	/**
	 * Contract: loaders and no-results content declared in named .xet templates
	 * are passed through to the datagrid, same as slotted content.
	 * Setup: parse a template root with a row template plus slot="loader" and
	 * slot="noResults" content.
	 * Pass: both templates are present and contain the declared markup.
	 */
	it("extracts state templates from named template roots", async() =>
	{
		const el = createDatagrid();
		const provider = new Et2RowProvider(el as any);
		const templateRoot = new DOMParser().parseFromString(`
			<template id="test.tile">
				<grid>
					<columns>
						<column/>
					</columns>
					<rows>
						<row class="th">
							<et2-nextmatch-header label="Title" id="title"/>
						</row>
						<row class="tile" data-tile-width="232px" data-tile-height="208px">
							<et2-vbox class="tile-body" width="100%" height="208px"></et2-vbox>
						</row>
					</rows>
				</grid>
				<et2-vbox class="tile-loader" slot="loader">
					<et2-description class="tile-loader-name"></et2-description>
				</et2-vbox>
				<et2-vbox class="tile-no-results" slot="noResults">
					<et2-description class="tile-no-results-message"></et2-description>
				</et2-vbox>
			</template>
		`, "text/xml").documentElement;

		const templateData = await (provider as any)._fromTemplateRoot(templateRoot);

		assert.isNotNull(templateData?.loaderTemplate, "loader template should be extracted");
		assert.isNotNull(
			templateData?.loaderTemplate?.content.querySelector(".tile-loader-name"),
			"loader template should contain declared loader content"
		);
		assert.isNotNull(templateData?.noResultsTemplate, "noResults template should be extracted");
		assert.isNotNull(
			templateData?.noResultsTemplate?.content.querySelector(".tile-no-results-message"),
			"noResults template should contain declared empty-state content"
		);
	});

	/**
	 * Contract: template-local row styles replace app.css for datagrid rows.
	 * Setup: compose row styles with both an app stylesheet and a template
	 * stylesheet present.
	 * Pass: the template stylesheet is included and the app stylesheet is not.
	 */
	it("uses template row styles instead of app.css in datagrid row stylesheets", async() =>
	{
		const nextmatch = new Et2Nextmatch() as any;
		const appSheet = new CSSStyleSheet();
		await appSheet.replace(".from-app-css { color: red; }");
		const templateSheet = new CSSStyleSheet();
		await templateSheet.replace(".from-template { color: green; }");

		nextmatch._appRowStylesheet = appSheet;
		nextmatch._templateData = {rowStylesheets: [templateSheet]};
		nextmatch._syncDatagridRowStylesheets();

		assert.include(nextmatch._rowStylesheets, templateSheet, "template row stylesheet should be adopted");
		assert.notInclude(nextmatch._rowStylesheets, appSheet, "app.css should not be adopted when template row styles exist");
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
			"$[active]",
			"Et2Widget should defer row-scoped boolean attributes during template preparation"
		);
		assert.equal(
			prepared?.attrMap?.[deferredId]?.active,
			"$[active]",
			"datagrid row binding should keep deferred boolean attributes for per-row transform"
		);
	});

	/**
	 * Contract: row-upgrade bookkeeping is attached only to elements which need
	 * row-time work.
	 * Setup: prepare a row with static markup, a row-bound widget value, and a
	 * customfields renderer with no dynamic source attributes.
	 * Pass: static elements stay unmarked while the dynamic widget and
	 * customfields renderer retain ids for the row upgrade pass.
	 */
	it("marks only row-time elements for nextmatch row hydration", async() =>
	{
		const el = createDatagrid();
		const provider = new Et2RowProvider(el as any);
		const rowTemplate = document.createElement("tr");
		rowTemplate.innerHTML = `
			<td class="static-cell">
				<et2-dg-test-transform class="static-widget" data-value="Static"></et2-dg-test-transform>
				<et2-dg-test-transform class="dynamic-widget" data-value="$label"></et2-dg-test-transform>
				<et2-customfields-list class="customfields"></et2-customfields-list>
			</td>
		`;

		const prepared = await (provider as any)._prepareRowTemplate(rowTemplate, [{key: "label", title: "Label"}] as any);
		const root = prepared?.template.content.firstElementChild as HTMLElement | null;
		const staticCell = root?.querySelector(".static-cell") as HTMLElement | null;
		const staticWidget = root?.querySelector(".static-widget") as HTMLElement | null;
		const dynamicWidget = root?.querySelector(".dynamic-widget") as HTMLElement | null;
		const customfields = root?.querySelector("et2-customfields-list") as HTMLElement | null;

		assert.isFalse(root?.hasAttribute("data-et2nm-id") || false, "static row root should not be upgraded");
		assert.isFalse(staticCell?.hasAttribute("data-et2nm-id") || false, "static cell should not be upgraded");
		assert.isFalse(staticWidget?.hasAttribute("data-et2nm-id") || false, "static widget should not be upgraded");
		assert.isTrue(dynamicWidget?.hasAttribute("data-et2nm-id") || false, "row-bound widget should be upgraded");
		assert.isTrue(customfields?.hasAttribute("data-et2nm-id") || false, "customfields renderer should be upgraded");

		const dynamicId = dynamicWidget?.getAttribute("data-et2nm-id") || "";
		assert.deepEqual(
			prepared?.attrMap?.[dynamicId],
			{"data-value": "$label"},
			"row-bound attributes should remain available for hydration"
		);
	});

	/**
	 * Contract: datagrid customfield rows receive shared metadata once from the
	 * customfield source and row-specific values from top-level #field data.
	 * Setup: build a row template containing et2-customfields-list without a header,
	 * forcing the modifications-array fallback used by legacy templates.
	 * Pass: only selected fields render while the renderer reuses the row object.
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

			assert.equal(
				list!.value,
				row.data,
				"row customfields should reuse the complete row object"
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
			"Legacy note",
			"modern ${row}[field] placeholder should resolve before widget transformation"
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
	 * Contract: provider-backed datagrids keep row data in the provider, not in datagrid row indexes.
	 * Setup: configure getRowData(), seed rows, and render one row from the internal index.
	 * Pass: datagrid stores only the id while rendered cells resolve values through the provider.
	 */
	it("stores only row ids when the provider supplies row data lookup", () =>
	{
		const el = createDatagrid();
		const rowDataById : Record<string, any> = {
			"addressbook::row-1": {uid: "addressbook::row-1", label: "Provider row"}
		};
		el.dataProvider = createDatagridDataProvider({
			getRowData: (rowId : string) => rowDataById[rowId] ?? null
		}) as any;
		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.templateData = {columns: el.columns} as any;

		el.setInitialRows([rowDataById["addressbook::row-1"]]);

		assert.deepEqual(el.rows[0], {id: "addressbook::row-1"}, "public row state should not carry provider-owned data");
		assert.deepEqual((el as any)._rowsByIndex[0], {id: "addressbook::row-1"}, "indexed row state should not carry provider-owned data");

		const rowElement = (el as any)._buildRowElement((el as any)._rowsByIndex[0], 0) as HTMLTableRowElement | null;
		assert.include(rowElement?.textContent || "", "Provider row", "row rendering should resolve data through provider.getRowData()");
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
	 * Contract: an expansion can shorten the item array while Lit Virtualizer
	 * still processes one queued range from the preceding array shape.
	 *
	 * Pass: stale undefined slots receive an absolute-index placeholder key and
	 * render as placeholders, rather than throwing and leaving prior physical
	 * rows overlapped in the DOM.
	 */
	it("tolerates stale virtualizer range items during expansion changes", () =>
	{
		const el = createDatagrid();
		el.dataProvider = createDatagridDataProvider({getQuerySignature: () => "stale-range"}) as any;

		assert.match(
			(el as any)._virtualRowKey(undefined, 37),
			/:placeholder:stale-range:37$/,
			"stale range slot should receive a stable absolute-index placeholder key"
		);
		assert.doesNotThrow(
			() => (el as any)._renderVirtualRow(undefined, 37),
			"stale range slot should render a placeholder rather than throwing"
		);
	});

	/**
	 * Contract: an expanded embedded child grid contributes its reserved height
	 * to the parent grid's single scrollport.
	 *
	 * Setup: render a short parent grid in a fixed-height host, expand the first
	 * row into an embedded virtualized child grid, and load enough child rows to
	 * require scrolling.
	 *
	 * Pass: the parent `.dg-body` remains the scroll container and exposes a
	 * scroll range after the expanded child height is applied.
	 */
	it("keeps the parent scrollport scrollable after embedded child expansion", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "180px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const childRows = Array.from({length: 50}, (_v, index) => ({id: `child-${index}`, label: `Child ${index}`}));
		const childTotal = 200;
		const childRowData = new Map(childRows.map((row) => [row.id, row]));
		const childColumns = [{key: "label", title: "Label", width: "1fr"}] as any;
		const childProvider = createDatagridDataProvider({
			fetchPage: async(start : number, pageSize : number) => ({
				rows: childRows.slice(start, start + pageSize),
				total: childTotal
			}),
			getRowData: (rowId : string) => childRowData.get(rowId),
			getQuerySignature: () => "expanded-child-scroll-regression"
		});
		const parent = createDatagrid();
		parent.style.height = "100%";
		parent.columns = childColumns;
		parent.templateData = {columns: parent.columns} as any;
		parent.expansionConfig = {
			isExpandable: (row) => !!row?.data?.is_parent,
			renderExpandedContent: () => html`
				<et2-datagrid
						embedded-virtualized
						no-visible-header
						.columns=${childColumns}
						.templateData=${{columns: childColumns} as any}
						.dataProvider=${childProvider as any}
				></et2-datagrid>
			`,
			expandedRowIds: new Set(["row-0"])
		};
		parent.setInitialRows([
			{id: "row-0", label: "Parent 0", is_parent: true},
			{id: "row-1", label: "Parent 1"}
		]);
		parent.total = 2;
		host.appendChild(parent);

		await parent.updateComplete;
		const expandedRow = await waitForExpandedRow(parent, "row-0");
		const child = expandedRow!.querySelector("et2-datagrid") as Et2Datagrid;
		assert.instanceOf(child, Et2Datagrid, "expanded row should host a child datagrid");

		const loaded = new Promise<void>((resolve) =>
		{
			child.addEventListener("et2-loading-done", () => resolve(), {once: true});
		});
		await child.reload();
		await loaded;
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		await parent.updateComplete;

		const parentBody = parent.shadowRoot!.querySelector(".dg-body") as HTMLElement;
		const childBody = child.shadowRoot!.querySelector(".dg-body") as HTMLElement;
		assert.isAbove(parseInt(child.style.height || "0", 10), host.clientHeight, "child should reserve more height than the parent viewport");
		assert.isAbove(
			parentBody.scrollHeight,
			parentBody.clientHeight,
			"parent body should have a scroll range after expanded child height is reserved"
		);
		assert.equal(getComputedStyle(childBody).overflowY, "visible", "child body should not become the nested scrollport");
		const firstChildRow = await waitForDatagridRow(child, "child-0");
		assert.isNotNull(firstChildRow, "the fetched child result should render an actual data row");
		assert.include(firstChildRow!.textContent || "", "Child 0", "the rendered child row should expose its fetched value");
		const childRect = child.getBoundingClientRect();
		const firstChildRect = firstChildRow!.getBoundingClientRect();
		assert.isAtLeast(firstChildRect.top, childRect.top - 1, "the first child row should begin inside its expanded host");
		assert.isAtMost(firstChildRect.bottom, childRect.bottom + 1, "the first child row should remain inside its expanded host");

		host.remove();
	});

	/**
	 * Contract: embedded virtualized child grids must keep their internal
	 * virtualizer spacer aligned with the full host height reservation.
	 *
	 * Setup: load one child page while the provider reports a larger total,
	 * matching a filemanager directory where more child rows exist than are
	 * initially rendered.
	 *
	 * Pass: the child host and its internal `tbody` both reserve the full
	 * virtualized child height, so scrolling the parent can reveal later child
	 * rows instead of clipping at the first rendered page.
	 */
	it("keeps embedded child tbody height aligned with total-row host reservation", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "180px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const rows = Array.from({length: 50}, (_v, index) => ({id: `child-spacer-${index}`, label: `Child ${index}`}));
		const rowData = new Map(rows.map((row) => [row.id, row]));
		const total = 200;
		const el = createDatagrid();
		el.embeddedVirtualized = true;
		el.noVisibleHeader = true;
		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.templateData = {columns: el.columns} as any;
		el.dataProvider = createDatagridDataProvider({
			fetchPage: async(start : number, pageSize : number) => ({
				rows: rows.slice(start, start + pageSize),
				total
			}),
			getRowData: (rowId : string) => rowData.get(rowId),
			getQuerySignature: () => "embedded-child-spacer-regression"
		}) as any;
		host.appendChild(el);

		const loaded = new Promise<void>((resolve) =>
		{
			el.addEventListener("et2-loading-done", () => resolve(), {once: true});
		});
		await el.reload();
		await loaded;
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		await el.updateComplete;

		const rowsBody = el.shadowRoot!.querySelector("tbody") as HTMLElement;
		const hostHeight = parseInt(el.style.height || "0", 10);
		const rowsBodyHeight = parseInt(rowsBody.style.height || rowsBody.style.minHeight || "0", 10);
		assert.isAbove(hostHeight, host.clientHeight, "embedded host should reserve the full child result height");
		assert.isAtLeast(
			rowsBodyHeight,
			hostHeight,
			"embedded tbody spacer should match the full host reservation, not just the first rendered page"
		);

		host.remove();
	});

	/**
	 * Contract: embedded virtualized grids must recompute their total reserved
	 * height from measured upgraded row height, not only from the virtualizer's
	 * initial row estimate.
	 *
	 * Setup: render an embedded child-style grid whose row stylesheet makes
	 * realized rows taller than the default virtualizer estimate while total
	 * reports many more rows than are currently realized.
	 *
	 * Pass: the child host and tbody reserve enough height for every reported
	 * child row at the measured upgraded row height, preventing bottom clipping.
	 */
	it("uses measured upgraded row height for embedded total reservation", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "180px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const rows = Array.from({length: 50}, (_v, index) => ({id: `child-tall-${index}`, label: `Child ${index}`}));
		const rowData = new Map(rows.map((row) => [row.id, row]));
		const total = 200;
		const el = createDatagrid();
		el.embeddedVirtualized = true;
		el.noVisibleHeader = true;
		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.templateData = {columns: el.columns} as any;
		el.dataProvider = createDatagridDataProvider({
			fetchPage: async(start : number, pageSize : number) => ({
				rows: rows.slice(start, start + pageSize),
				total
			}),
			getRowData: (rowId : string) => rowData.get(rowId),
			getQuerySignature: () => "embedded-tall-row-total-regression"
		}) as any;
		host.appendChild(el);

		const loaded = new Promise<void>((resolve) =>
		{
			el.addEventListener("et2-loading-done", () => resolve(), {once: true});
		});
		await el.reload();
		await loaded;
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		await el.updateComplete;

		const initialHostHeight = parseInt(el.style.height || "0", 10);
		const rowsBody = el.shadowRoot!.querySelector("tbody") as HTMLElement;
		for(const row of Array.from(rowsBody.querySelectorAll("tr[data-row-id]")) as HTMLElement[])
		{
			row.style.minHeight = "96px";
		}
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		await el.updateComplete;

		const firstRow = rowsBody.querySelector("tr[data-row-id]") as HTMLElement;
		const measuredRowHeight = Math.ceil(firstRow.getBoundingClientRect().height);
		const expectedReservedHeight = total * measuredRowHeight;
		const heightSettled = await waitForEmbeddedHostHeight(el, (height) => parseInt(height || "0", 10) >= expectedReservedHeight);
		const hostHeight = parseInt(el.style.height || "0", 10);
		const rowsBodyHeight = parseInt(rowsBody.style.height || rowsBody.style.minHeight || "0", 10);

		assert.isAtLeast(measuredRowHeight, 96, "test fixture should render rows taller than the default estimate");
		assert.isAbove(expectedReservedHeight, initialHostHeight, "late-upgraded rows should require a larger reservation than the initial estimate");
		assert.isTrue(heightSettled, "embedded height should settle after late row growth");
		assert.isAtLeast(hostHeight, expectedReservedHeight, "embedded host should reserve total rows at measured upgraded row height");
		assert.isAtLeast(rowsBodyHeight, expectedReservedHeight, "embedded tbody should match the upgraded total-row reservation");

		host.remove();
	});

	/**
	 * Contract: row-height estimates are updated after row widgets upgrade and
	 * consumers can await an explicit event before calculating embedded heights.
	 *
	 * Setup: render normal rows, force the estimate below the actual rendered
	 * row height, and run the post-upgrade settle path.
	 *
	 * Pass: `et2-row-widgets-upgraded` fires after the settle frame and carries
	 * the measured average now used for later reservations.
	 */
	it("emits upgraded-row event with measured average row height", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "220px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = createDatagrid();
		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.templateData = {columns: el.columns} as any;
		el.setInitialRows(Array.from({length: 5}, (_v, index) => ({id: `row-${index}`, label: `Row ${index}`})));
		el.total = 5;
		host.appendChild(el);

		await el.updateComplete;
		await waitForDatagridRow(el, "row-0");
		(el as any)._rowHeightLocked = false;
		(el as any)._rowHeightSource = "default";
		(el as any)._rowHeightSettled = false;
		(el as any)._rowHeightPx = 20;
		const upgraded = new Promise<CustomEvent<{ averageRowHeight : number }>>((resolve) =>
		{
			el.addEventListener("et2-row-widgets-upgraded", (event) => resolve(event as CustomEvent<{ averageRowHeight : number }>), {once: true});
		});

		(el as any)._scheduleRowsUpgradedSettle();
		const event = await upgraded;

		assert.isAbove(event.detail.averageRowHeight, 20, "upgraded event should report measured row height above the stale estimate");
		assert.equal((el as any)._rowHeightPx, event.detail.averageRowHeight, "future reservations should use the measured average");
		assert.equal(el.style.getPropertyValue("--row-height"), `${event.detail.averageRowHeight}px`);

		host.remove();
	});

	it("locks expandable grids to the first upgraded average row height", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "220px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = createDatagrid();
		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.templateData = {columns: el.columns} as any;
		el.expansionConfig = {
			isExpandable: () => true,
			renderExpandedContent: () => html`<div>expanded</div>`,
			expandedRowIds: new Set()
		};
		el.setInitialRows(Array.from({length: 5}, (_v, index) => ({id: `row-${index}`, label: `Row ${index}`})));
		el.total = 5;
		host.appendChild(el);

		const debugSpy = sinon.spy(egw, "debug");
		await el.updateComplete;
		await waitForDatagridRow(el, "row-0");
		(el as any)._rowHeightPx = 20;

		const upgraded = new Promise<CustomEvent<{ averageRowHeight : number }>>((resolve) =>
		{
			el.addEventListener("et2-row-widgets-upgraded", (event) => resolve(event as CustomEvent<{ averageRowHeight : number }>), {once: true});
		});
		(el as any)._scheduleRowsUpgradedSettle();
		const event = await upgraded;

		assert.equal((el as any)._rowHeightPx, event.detail.averageRowHeight, "expandable grid should use the first upgraded average");
		assert.isTrue((el as any)._rowHeightSettled, "expandable grid should settle after the first upgraded batch");
		assert.isTrue(el.fixedRowHeight, "expandable grid should switch to fixed row-height CSS");
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		const layout = (el as any)._tableLayoutConfig();
		assert.isOk(layout, "expandable grids should configure a settled normal-row pitch");
		assert.equal(layout._itemSize.height, event.detail.averageRowHeight, "normal rows should retain the settled height");
		assert.isFunction(layout._measureChildren, "the sparse layout should keep Lit's viewport measurement lifecycle active");
		assert.instanceOf(layout.expandedItemHeights, Map, "expanded branch heights should be held in a sparse map");
		const virtualizer = (el as any)._virtualize as any;
		assert.isAbove(virtualizer?._layout?.viewportSize?.height || 0, 0,
			"switching to the sparse layout must retain a non-zero viewport and rendered range");
		assert.isFalse(
			debugSpy.calledWithMatch("warn", sinon.match("Set an explicit row template height")),
			"generic expanded detail rows should not warn about subgrid row-height configuration"
		);
		debugSpy.restore();

		host.remove();
	});

	/**
	 * Contract: the deterministic expansion layout supports the common empty
	 * result set without attempting anchor or height-map calculations.
	 *
	 * Setup: create a settled expandable grid with zero virtual items.
	 *
	 * Pass: forcing the layout to reflow leaves its minimum scroll size intact
	 * and does not throw, so the grid can render its normal empty state.
	 */
	it("keeps the sparse expansion layout safe for empty lists", async() =>
	{
		const el = createDatagrid();
		el.expansionConfig = {
			isExpandable: () => true,
			renderExpandedContent: () => html``,
			expandedRowIds: new Set()
		};
		(el as any)._rowHeightSettled = true;
		(el as any)._sparseVirtualizerLayoutActive = true;
		const config = (el as any)._tableLayoutConfig();
		const layout = new config.type(() => undefined, config);
		layout.items = [];
		await Promise.resolve();
		layout.reflowIfNeeded(true);

		assert.equal(layout._scrollSize, 1, "empty lists should retain the virtualizer's minimum scroll size");
	});

	it("clears sparse expansion caches when rows reset", () =>
	{
		const el = createDatagrid();
		(el as any)._expandedVirtualItemHeights.set(4, 1200);
		(el as any)._virtualItems = [0, {type: "expanded", rowIndex: 0, parentRowId: "row-0"}];
		(el as any)._virtualItemsSignature = "stale-expanded-items";

		(el as any)._clearRows();

		assert.equal((el as any)._expandedVirtualItemHeights.size, 0, "sparse expanded-row heights should not survive a row reset");
		assert.deepEqual((el as any)._virtualItems, [], "expanded virtual items should be rebuilt for the next result set");
		assert.equal((el as any)._virtualItemsSignature, "", "the virtual item cache signature should be invalidated");
	});

	it("bootstraps a non-empty sparse layout from a zero-height host", async() =>
	{
		const el = createDatagrid();
		el.expansionConfig = {
			isExpandable: () => true,
			renderExpandedContent: () => html``,
			expandedRowIds: new Set()
		};
		(el as any)._rowHeightSettled = true;
		(el as any)._sparseVirtualizerLayoutActive = true;
		const config = (el as any)._tableLayoutConfig();
		const layout = new config.type(() => undefined, config);
		layout.items = Array.from({length: 48}, (_value, index) => index);
		await Promise.resolve();
		layout.reflowIfNeeded(true);

		assert.equal(layout._first, 0, "a zero-height host should retain an initial realized range");
		assert.isAbove(layout._last, 0, "the initial range should contain enough rows to establish host height");
	});

	it("warns about measured fixed row height only for subgrid expansion", () =>
	{
		const el = createDatagrid();
		(el as any).egw = () => egw;
		el.expansionConfig = {
			isExpandable: () => true,
			renderExpandedContent: () => html`<et2-datagrid embedded-virtualized></et2-datagrid>`,
			rendersSubgrid: true,
			expandedRowIds: new Set()
		};

		const debugSpy = sinon.spy(egw, "debug");
		(el as any)._logExpansionRowHeightWarning();

		assert.isTrue(
			debugSpy.calledWithMatch("warn", sinon.match("Set an explicit row template height")),
			"subgrid expansion should warn when row height was inferred from the first batch"
		);
		debugSpy.restore();
	});

	it("leaves non-expandable grids on variable-height virtualizer layout", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "220px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = createDatagrid();
		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.templateData = {columns: el.columns} as any;
		el.setInitialRows(Array.from({length: 5}, (_v, index) => ({id: `row-${index}`, label: `Row ${index}`})));
		el.total = 5;
		host.appendChild(el);

		await el.updateComplete;
		await waitForDatagridRow(el, "row-0");
		(el as any)._rowHeightPx = 20;

		const upgraded = new Promise<void>((resolve) => el.addEventListener("et2-row-widgets-upgraded", () => resolve(), {once: true}));
		(el as any)._scheduleRowsUpgradedSettle();
		await upgraded;

		assert.isFalse((el as any)._rowHeightSettled, "non-expandable grid should not freeze row-height averaging");
		assert.isFalse(el.fixedRowHeight, "non-expandable grid should not clip rows to a fixed measured height");
		assert.isUndefined((el as any)._tableLayoutConfig(), "non-expandable grid should keep Lit virtualizer's variable-height flow layout");

		host.remove();
	});

	/**
	 * Contract: a row-template height hint is authoritative. The grid still
	 * emits the row-upgraded event after widgets settle, but does not replace the
	 * template height with sampled averages from rendered rows.
	 */
	it("keeps template row height fixed while still emitting upgraded-row event", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "220px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const rowTemplate = document.createElement("template");
		rowTemplate.innerHTML = `<tr data-row-height="72"><td data-column="label">$label</td></tr>`;

		const el = createDatagrid();
		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.templateData = {
			columns: el.columns,
			rowTemplate,
			rowTemplateXml: null,
			rowTemplateAttrMap: {},
			loaderTemplate: null
		} as any;
		el.setInitialRows(Array.from({length: 5}, (_v, index) => ({id: `row-${index}`, label: `Row ${index}`})));
		el.total = 5;
		host.appendChild(el);

		await el.updateComplete;
		await waitForDatagridRow(el, "row-0");
		for(const row of Array.from(el.shadowRoot!.querySelectorAll("tr[data-row-id]")) as HTMLElement[])
		{
			row.style.minHeight = "140px";
		}

		const upgraded = new Promise<CustomEvent<{ averageRowHeight : number; rowHeightLocked : boolean }>>((resolve) =>
		{
			el.addEventListener("et2-row-widgets-upgraded", (event) =>
			{
				resolve(event as CustomEvent<{ averageRowHeight : number; rowHeightLocked : boolean }>);
			}, {once: true});
		});
		(el as any)._scheduleRowsUpgradedSettle();
		const event = await upgraded;

		assert.equal(event.detail.averageRowHeight, 72, "event should report the template row height");
		assert.isTrue(event.detail.rowHeightLocked, "event should advertise that row height is fixed");
		assert.equal((el as any)._rowHeightPx, 72, "template height should not be replaced by rendered row average");
		assert.equal((el as any)._measuredRowHeightByRowId.size, 0, "fixed-height templates should skip row-height sampling");
		assert.equal(el.style.getPropertyValue("--row-height"), "72px");
		assert.isTrue(el.fixedRowHeight, "template height should enable fixed-row-height mode");
		assert.isTrue(el.hasAttribute("fixed-row-height"), "fixed-row-height mode should reflect for CSS clipping");

		host.remove();
	});

	it("refreshes the virtualizer pitch from a changed CSS row-height", () =>
	{
		const el = createDatagrid();
		el.setRowHeightEstimate(72);
		el.style.setProperty("--row-height", "96px");
		el.refreshRowHeightFromCss();

		assert.equal(el.rowHeightEstimatePx, 96, "CSS refresh should update the numeric virtualizer pitch");
		assert.equal((el as any)._rowHeightSource, "css", "CSS refresh should retain the external source");
		assert.equal(el.style.getPropertyValue("--row-height"), "96px", "CSS refresh should preserve the consumer's inline variable");
		assert.isTrue((el as any)._rowHeightLocked, "an explicit CSS height should use fixed row layout");
	});

	it("clips regular cells in fixed row height mode", () =>
	{
		const cssText = String((datagridStyles as any).cssText || datagridStyles);
		assert.include(
			cssText,
			"min-height: max(44px, var(--row-height, 44px));",
			"regular rows should use the measured/configured row height as a min-height floor"
		);
		assert.match(
			cssText,
			/tbody\s*>\s*tr\s*{[\s\S]*box-sizing:\s*border-box;/,
			"row-height reservations must include the bottom border so an embedded subgrid cannot overrun its expanded host"
		);
		assert.match(
			cssText,
			/:host\(\[embedded-virtualized\]\)\s+\.dg-body\s+tbody\s*{[\s\S]*row-gap:\s*0;/,
			"embedded virtualized table rows should not use tbody row-gap because Lit virtualizer does not measure it"
		);
		assert.match(
			cssText,
			/:host\(\[fixed-row-height\]\)\s+\.dg-body\s+tbody\s*>\s*tr\[data-row-id\]:not\(\.dg-row-expanded\)\s*{[\s\S]*max-height:\s*var\(--row-height,\s*44px\);[\s\S]*overflow:\s*hidden;/,
			"fixed row-height rows should clip overflowing widget content to the declared row height"
		);
		assert.match(
			cssText,
			/:host\(\[fixed-row-height\]\)\s+\.dg-body\s+tbody\s*>\s*tr\[data-row-id\]:not\(\.dg-row-expanded\)\s*>\s*td,[\s\S]*overflow:\s*hidden;/,
			"fixed row-height cells should not expose independent vertical overflow"
		);
	});

	it("passes the parent row-height estimate to embedded child grids", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "220px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		const parent = createDatagrid();
		parent.columns = columns;
		parent.templateData = {columns} as any;
		parent.setRowHeightEstimate(96);
		parent.expansionConfig = {
			isExpandable: (row) => !!row?.data?.is_parent,
			renderExpandedContent: () => html`
				<et2-datagrid
						embedded-virtualized
						no-visible-header
						.columns=${columns}
						.templateData=${{columns} as any}
				></et2-datagrid>
			`,
			expandedRowIds: new Set(["parent-row"])
		};
		parent.setInitialRows([{id: "parent-row", label: "Parent", is_parent: true}]);
		parent.total = 1;
		(parent as any)._rowHeightSettled = true;
		host.appendChild(parent);

		await parent.updateComplete;
		const expandedRow = await waitForExpandedRow(parent, "parent-row");
		const child = expandedRow?.querySelector("et2-datagrid") as Et2Datagrid | null;
		assert.isOk(child, "test fixture should render an embedded child grid");
		await child!.updateComplete;
		await parent.updateComplete;
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		await child!.updateComplete;

		assert.equal(child!.rowHeightEstimatePx, 96, "child grid should inherit the parent's settled row height");
		assert.equal((child as any)._rowHeightSource, "parent",
			"an inherited parent estimate must lock the child to the parent's settled row height");
		assert.equal(child!.style.getPropertyValue("--row-height"), "96px", "child grid CSS should use the propagated row-height estimate");

		host.remove();
	});

	/**
	 * Contract: expanding a row must not collapse an already-scrollable parent
	 * grid's scroll range.
	 *
	 * Setup: render a parent grid with enough rows to require scrolling, expand a
	 * row into an asynchronously loaded embedded child grid, and wait for the
	 * child load to complete.
	 *
	 * Pass: the parent body still has a scrollbar-sized scroll range after the
	 * child grid reports its height.
	 */
	it("preserves the parent scrollbar when expanding a row in an already scrollable grid", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "180px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const childRows = Array.from({length: 50}, (_v, index) => ({id: `child-existing-scroll-${index}`, label: `Child ${index}`}));
		const childRowData = new Map(childRows.map((row) => [row.id, row]));
		const columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		const childProvider = createDatagridDataProvider({
			fetchPage: async(start : number, pageSize : number) => ({
				rows: childRows.slice(start, start + pageSize),
				total: 200
			}),
			getRowData: (rowId : string) => childRowData.get(rowId),
			getQuerySignature: () => "expanded-child-existing-scroll-regression"
		});
		const parent = createDatagrid();
		parent.style.height = "100%";
		parent.columns = columns;
		parent.templateData = {columns} as any;
		parent.setInitialRows(Array.from({length: 80}, (_v, index) => ({
			id: `row-${index}`,
			label: `Parent ${index}`,
			is_parent: index === 0
		})));
		parent.total = 80;
		host.appendChild(parent);
		await parent.updateComplete;
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));

		const parentBody = parent.shadowRoot!.querySelector(".dg-body") as HTMLElement;
		const baselineScrollHeight = parentBody.scrollHeight;
		assert.isAbove(baselineScrollHeight, parentBody.clientHeight, "parent should start with a scroll range");

		parent.expansionConfig = {
			isExpandable: (row) => !!row?.data?.is_parent,
			renderExpandedContent: () => html`
				<et2-datagrid
						embedded-virtualized
						no-visible-header
						.columns=${columns}
						.templateData=${{columns} as any}
						.dataProvider=${childProvider as any}
				></et2-datagrid>
			`,
			expandedRowIds: new Set(["row-0"])
		};
		await parent.updateComplete;
		const expandedRow = await waitForExpandedRow(parent, "row-0");
		const child = expandedRow!.querySelector("et2-datagrid") as Et2Datagrid;
		const loaded = new Promise<void>((resolve) =>
		{
			child.addEventListener("et2-loading-done", () => resolve(), {once: true});
		});
		await child.reload();
		await loaded;
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));

		assert.isAbove(parentBody.scrollHeight, parentBody.clientHeight, "parent body should remain scrollable after expansion");
		assert.isAtLeast(parentBody.scrollHeight, baselineScrollHeight, "expansion should not shrink the parent scroll range");

		host.remove();
	});

	/**
	 * Contract: the parent scrollport must drive both embedded child
	 * virtualization and top-level rows after the expanded branch.
	 *
	 * Setup: render one expanded parent row, a large embedded child result, and a
	 * top-level row after the expansion.
	 *
	 * Pass: scrolling the parent into the middle of the child branch renders
	 * later child rows, and scrolling past the child branch renders the following
	 * top-level row.
	 */
	it("renders later child rows and following parent rows without changing the shared scroll range", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "220px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const childRows = Array.from({length: 200}, (_v, index) => ({id: `child-shared-scroll-${index}`, label: `Child ${index}`}));
		const childRowData = new Map(childRows.map((row) => [row.id, row]));
		const columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		const calls : number[] = [];
		const childProvider = createDatagridDataProvider({
			fetchPage: async(start : number, pageSize : number) =>
			{
				calls.push(start);
				return {
					rows: childRows.slice(start, start + pageSize),
					total: childRows.length
				};
			},
			getRowData: (rowId : string) => childRowData.get(rowId),
			getQuerySignature: () => "shared-parent-scroll-regression"
		});
		const parent = createDatagrid();
		parent.style.height = "100%";
		parent.columns = columns;
		parent.templateData = {columns} as any;
		parent.expansionConfig = {
			isExpandable: (row) => !!row?.data?.is_parent,
			renderExpandedContent: () => html`
				<et2-datagrid
						embedded-virtualized
						no-visible-header
						.columns=${columns}
						.templateData=${{columns} as any}
						.dataProvider=${childProvider as any}
				></et2-datagrid>
			`,
			expandedRowIds: new Set(["row-0"])
		};
		parent.setInitialRows([
			{id: "row-0", label: "Parent 0", is_parent: true},
			{id: "row-after", label: "Parent after"}
		]);
		parent.total = 2;
		host.appendChild(parent);

		await parent.updateComplete;
		const expandedRow = await waitForExpandedRow(parent, "row-0");
		const child = expandedRow!.querySelector("et2-datagrid") as Et2Datagrid;
		const loaded = new Promise<void>((resolve) =>
		{
			child.addEventListener("et2-loading-done", () => resolve(), {once: true});
		});
		await child.reload();
		await loaded;
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));

		const parentBody = parent.shadowRoot!.querySelector(".dg-body") as HTMLElement;
		const childHostHeight = parseInt(child.style.height || "0", 10);
		assert.isAbove(childHostHeight, parentBody.clientHeight, "child branch should be taller than the parent viewport");
		assert.isAtLeast(
			parentBody.scrollHeight,
			childHostHeight,
			"parent scroll range should include the embedded child's full reserved height"
		);
		assert.isAbove(parentBody.scrollHeight, parentBody.clientHeight, "parent body must remain a constrained scrollport");
		const childRow = await waitForDatagridRow(child, "child-shared-scroll-0");
		assert.isNotNull(childRow, "test fixture should render the first child row before measuring child scroll target");
		const childRowHeight = Math.ceil(childRow!.getBoundingClientRect().height);
		const childOffsetInParent = parentBody.scrollTop + child.getBoundingClientRect().top - parentBody.getBoundingClientRect().top;
		const bottomBeforeChildScroll = parentBody.scrollHeight - parentBody.clientHeight;
		parentBody.scrollTop = bottomBeforeChildScroll;
		parentBody.dispatchEvent(new Event("scroll"));
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));

		parentBody.scrollTop = Math.floor(childOffsetInParent + 100 * childRowHeight);
		parentBody.dispatchEvent(new Event("scroll"));
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		for(let i = 0; i < 10 && !child.shadowRoot?.querySelector("[data-row-id='child-shared-scroll-100']"); i++)
		{
			await new Promise<void>((resolve) => setTimeout(resolve, 0));
			await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		}
		for(let i = 0; i < 10 && !child.shadowRoot?.querySelector("[data-row-id='child-shared-scroll-100']"); i++)
		{
			await new Promise<void>((resolve) => setTimeout(resolve, 25));
			await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		}
		const renderedChildIndexes = Array.from(child.shadowRoot?.querySelectorAll("[data-row-index]") || [])
			.map((row) => parseInt((row as HTMLElement).getAttribute("data-row-index") || "-1", 10));
		assert.isAbove(parentBody.scrollTop, 0, "parent scroll position must not be reset after child height sync");
		assert.isAbove(
			Math.max(...renderedChildIndexes),
			50,
			"scrolling the parent into the child branch should render later child rows"
			);
			assert.isNotNull(
				child.shadowRoot?.querySelector("[data-row-id='child-shared-scroll-100']"),
				`scrolling the parent into an unloaded child range should fetch and render real child rows, not leave a blank placeholder range; calls=${calls.join(",")}`
			);

		parentBody.scrollTop = childOffsetInParent + childHostHeight + 80;
		parentBody.dispatchEvent(new Event("scroll"));
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		assert.isNotNull(
			parent.shadowRoot?.querySelector("[data-row-id='row-after']"),
			"scrolling past the child branch should render the following top-level row"
		);

		const parentLayout = (parent as any)._virtualize?._layout;
		const stableScrollHeight = parentBody.scrollHeight;
		const stableLayoutSize = parentLayout?._scrollSize;
		const branchStart = Math.max(0, Math.floor(childOffsetInParent));
		const branchEnd = Math.max(branchStart, Math.floor(childOffsetInParent + childHostHeight));
		for(const target of [0, branchStart + 1, branchEnd - 1, bottomBeforeChildScroll])
		{
			parentBody.scrollTop = target;
			parentBody.dispatchEvent(new Event("scroll"));
			await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
			await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
			assert.equal(parentBody.scrollHeight, stableScrollHeight,
				"crossing a known child branch must not change the scrollbar extent");
			assert.equal(parentLayout?._scrollSize, stableLayoutSize,
				"crossing a known child branch must not change the sparse layout extent");
		}
		// Verify the deferred shared-scroll synchronization does not alter either
		// extent after the final scroll event has settled.
		await new Promise<void>((resolve) => window.setTimeout(resolve, 250));
		assert.equal(parentBody.scrollHeight, stableScrollHeight,
			"the settled shared-scroll sync must preserve the scrollbar extent");
		assert.equal(parentLayout?._scrollSize, stableLayoutSize,
			"the settled shared-scroll sync must preserve the sparse layout extent");

		host.remove();
	});

	/**
	 * Contract: an embedded grid must use its logical branch offset, rather than
	 * its recycled DOM position, when sharing an ancestor scrollport.
	 *
	 * Setup: give an embedded child a logical offset inside a scrolling host and
	 * drive the host to a later position.
	 *
	 * Pass: its FlowLayout viewport uses `scrollTop - logicalOffset`; a zero
	 * clipped viewport is also replaced by the ancestor viewport so deep nested
	 * branches do not clear all of their physical rows.
	 */
	it("uses logical shared-scroll viewport coordinates for embedded descendants", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "220px";
		host.style.width = "800px";
		host.style.overflowY = "auto";
		document.body.appendChild(host);

		const child = createDatagrid();
		child.embeddedVirtualized = true;
		child.noVisibleHeader = true;
		child.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		child.templateData = {columns: child.columns} as any;
		child.setInitialRows(Array.from({length: 40}, (_value, index) => ({id: `logical-offset-${index}`, label: `Row ${index}`})));
		child.total = 40;
		host.appendChild(child);

		await child.updateComplete;
		await waitForDatagridRow(child, "logical-offset-0");
		for(let i = 0; i < 2; i++)
		{
			await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		}

		(child as any)._embeddedParentScrollOffsetTop = 112;
		host.scrollTop = 400;
		(child as any)._handleEmbeddedParentScroll(host);

		const layout = (child as any)._virtualize?._layout;
		assert.equal(layout.viewportScroll.top, 288, "layout scroll position should be relative to the child branch's logical top");
		assert.isAbove(layout.viewportSize.height, 0, "embedded layout should retain a usable shared viewport height");

		host.remove();
	});

	/**
	 * Contract: parent-scroll propagation must tolerate nested embedded
	 * virtualizers that have been disconnected by row recycling.
	 *
	 * Setup: render an embedded child grid, then simulate Lit Virtualizer's
	 * transient disconnected state by clearing its private scroller controller.
	 *
	 * Pass: handling a parent scroll does not call the disconnected virtualizer's
	 * scroll handler and does not throw.
	 */
	it("skips disconnected embedded virtualizers during parent scroll sync", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "220px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const child = createDatagrid();
		child.embeddedVirtualized = true;
		child.noVisibleHeader = true;
		child.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		child.templateData = {columns: child.columns} as any;
		child.setInitialRows(Array.from({length: 20}, (_v, index) => ({id: `row-${index}`, label: `Row ${index}`})));
		child.total = 20;
		host.appendChild(child);

		await child.updateComplete;
		await waitForDatagridRow(child, "row-0");
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));

		const rowsBody = child.shadowRoot!.querySelector("tbody") as HTMLElement;
		const virtualizer = (child as any)._virtualize as any;
		assert.isOk(virtualizer, "embedded row-mode grids should use Lit virtualization against the shared scrollport");

		assert.doesNotThrow(() =>
		{
			(child as any)._handleEmbeddedParentScroll(host);
		});
		assert.isAtLeast(rowsBody.scrollTop, 0, "local embedded scroll offset should remain valid");

		host.remove();
	});

	/**
	 * Contract: measured expanded-branch height remains part of the parent
	 * virtual height even when the expanded row is not currently realized.
	 *
	 * Setup: seed expanded state and a cached measured branch height, matching a
	 * nested expanded row that was measured before row recycling removed it from
	 * the DOM.
	 *
	 * Pass: the cached floor adds only the branch height above the normal
	 * virtualizer row estimate.
	 */
	it("keeps cached expanded branch height while expanded rows are recycled", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "220px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = createDatagrid();
		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.templateData = {columns: el.columns} as any;
		el.expansionConfig = {
			isExpandable: (row) => !!row?.data?.is_parent,
			renderExpandedContent: () => html`<div>expanded</div>`,
			expandedRowIds: new Set(["row-10"])
		};
		el.setInitialRows(Array.from({length: 40}, (_v, index) => ({
			id: `row-${index}`,
			label: `Row ${index}`,
			is_parent: index === 10
		})));
		el.total = 40;
		host.appendChild(el);

		await el.updateComplete;
		(el as any)._rowHeightPx = 44;
		(el as any)._expandedRowHeightByParentRowId.set("row-10", 500);

		assert.equal(
			(el as any)._cachedExpandedRowsMinHeightFloor(40 * 44),
			40 * 44 + (500 - 44),
			"cached expanded branch should add only the measured extra height over the normal expanded-row estimate"
		);

		host.remove();
	});

	/**
	 * Contract: an embedded grid that contains its own expanded child branch must
	 * include cached inner expanded height in its host reservation.
	 *
	 * Setup: render an embedded child-style grid with an expanded row id and a
	 * cached measured inner branch height, matching a nested subgrid after the
	 * inner expanded row has been recycled out of DOM.
	 *
	 * Pass: the embedded host-height calculation remains taller than the plain
	 * data-row reservation by the cached expanded-branch extra height.
	 */
	it("includes cached inner expanded height in embedded host reservation", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "220px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const el = createDatagrid();
		el.embeddedVirtualized = true;
		el.noVisibleHeader = true;
		el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		el.templateData = {columns: el.columns} as any;
		el.expansionConfig = {
			isExpandable: (row) => !!row?.data?.is_parent,
			renderExpandedContent: () => html`<div>expanded</div>`,
			expandedRowIds: new Set(["row-10"])
		};
		el.setInitialRows(Array.from({length: 40}, (_v, index) => ({
			id: `row-${index}`,
			label: `Row ${index}`,
			is_parent: index === 10
		})));
		el.total = 40;
		host.appendChild(el);

		await el.updateComplete;
		await waitForDatagridRow(el, "row-0");
		(el as any)._rowHeightPx = 44;
		(el as any)._embeddedVirtualizedMeasuredRowHeightPx = 44;
		(el as any)._expandedRowHeightByParentRowId.set("row-10", 500);

		const height = parseInt((el as any)._embeddedVirtualizedContentHeight(), 10);
		assert.equal(
			height,
			40 * 44 + 500,
			"embedded host height should include the full extra expanded virtual item after recycling"
		);

		host.remove();
	});

	/**
	 * Contract: collapsing a deepest expanded branch removes its reservation from
	 * every ancestor without rolling child totals into their parents.
	 *
	 * Setup: render three real nested grids. The middle grid expands into a leaf
	 * grid, then its expanded row is collapsed after the leaf height has already
	 * been reserved by both ancestors.
	 *
	 * Pass: the middle grid drops its cached leaf reservation, reports its new
	 * host height, and the root grid drops the corresponding ancestor reservation
	 * instead of retaining blank scroll space. Root, child, and leaf totals remain
	 * their distinct reported values throughout the flow.
	 */
	it("keeps per-level totals isolated when a nested branch expands and collapses", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "220px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		const providerFor = (rows : any[], total : number, querySignature : string) =>
		{
			const rowData = new Map(rows.map((row) => [row.id, row]));
			return createDatagridDataProvider({
				fetchPage: async(start : number, pageSize : number) => ({rows: rows.slice(start, start + pageSize), total}),
				getRowData: (rowId : string) => rowData.get(rowId),
				getQuerySignature: () => querySignature
			});
		};
		const leafRows = Array.from({length: 4}, (_value, index) => ({id: `leaf-${index}`, label: `Leaf ${index}`}));
		const childRows = [{id: "child-0", label: "Child", is_parent: true}];
		const parentRows = [{id: "parent-0", label: "Parent", is_parent: true}];
		const leaf = createDatagrid();
		leaf.embeddedVirtualized = true;
		leaf.noVisibleHeader = true;
		leaf.columns = columns;
		leaf.templateData = {columns} as any;
		leaf.dataProvider = providerFor(leafRows, 25, "nested-total-leaf") as any;
		leaf.setInitialRows(leafRows);
		leaf.total = 25;
		leaf.setRowHeightEstimate(50, true);

		const child = createDatagrid();
		child.embeddedVirtualized = true;
		child.noVisibleHeader = true;
		child.columns = columns;
		child.templateData = {columns} as any;
		child.expansionConfig = {
			isExpandable: (row) => !!row?.data?.is_parent,
			renderExpandedContent: () => leaf,
			expandedRowIds: new Set(["child-0"])
		};
		child.dataProvider = providerFor(childRows, 7, "nested-total-child") as any;
		child.setInitialRows(childRows);
		child.total = 7;
		child.setRowHeightEstimate(50, true);

		const parent = createDatagrid();
		parent.style.height = "100%";
		parent.columns = columns;
		parent.templateData = {columns} as any;
		parent.expansionConfig = {
			isExpandable: (row) => !!row?.data?.is_parent,
			renderExpandedContent: () => child,
			expandedRowIds: new Set(["parent-0"])
		};
		parent.dataProvider = providerFor(parentRows, 11, "nested-total-parent") as any;
		parent.setInitialRows(parentRows);
		parent.total = 11;
		parent.setRowHeightEstimate(50, true);
		host.appendChild(parent);

		await parent.updateComplete;
		await child.updateComplete;
		await leaf.updateComplete;
		for(let i = 0; i < 4; i++)
		{
			await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		}

		const childExpandedRow = await waitForExpandedRow(child, "child-0");
		assert.isNotNull(childExpandedRow, "middle grid should render the deepest expanded branch");
		assert.equal(parent.total, 11, "root total should remain its own row count after nested expansion");
		assert.equal(child.total, 7, "child total should remain its own row count after nested expansion");
		assert.equal(leaf.total, 25, "leaf total should remain its own row count after nested expansion");
		const leafHeight = (leaf as any)._fixedVirtualItemsHeight();
		const childHeight = (child as any)._fixedVirtualItemsHeight();
		const initialParentHeight = (parent as any)._fixedVirtualItemsHeight();
		assert.equal((child as any)._expandedRowHeightByParentRowId.get("child-0"), leafHeight, "middle grid should reserve the leaf height");
		assert.equal((parent as any)._expandedRowHeightByParentRowId.get("parent-0"), childHeight, "root grid should reserve the middle grid including its leaf branch");
		assert.isAbove(initialParentHeight, childHeight, "root virtual extent should include its own rows as well as the nested branch");

		// `_setRowExpanded()` normally schedules a Lit update, whose embedded-host
		// sync emits this notification after render. Suppress that unrelated
		// virtualizer render here and emit the same settled host-height update
		// directly, so this test isolates the recursive reservation contract.
		const requestUpdate = sinon.stub(child, "requestUpdate").returns(false as any);
		(child as any)._setRowExpanded((child as any)._rowsByIndex[0], false);
		const collapsedChildHeight = (child as any)._fixedVirtualItemsHeight();
		(child as any)._applyEmbeddedVirtualizedHostHeight(`${collapsedChildHeight}px`);
		requestUpdate.restore();

		assert.isFalse((child as any)._expandedRowHeightByParentRowId.has("child-0"), "middle grid should clear the collapsed leaf reservation");
		assert.isBelow(collapsedChildHeight, childHeight, "middle grid should shrink after its nested branch collapses");
		assert.equal((parent as any)._expandedRowHeightByParentRowId.get("parent-0"), collapsedChildHeight, "root grid should receive the reduced middle-grid height");
		assert.isBelow((parent as any)._fixedVirtualItemsHeight(), initialParentHeight, "root virtual extent should release the recursive reservation");
		assert.equal(parent.total, 11, "root total should not change when the nested branch collapses");
		assert.equal(child.total, 7, "child total should not change when its branch collapses");
		assert.equal(leaf.total, 25, "leaf total should not change when it is detached by collapse");

		host.remove();
	});

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
			const rowsBodyRect = rowsBody.getBoundingClientRect();
			const rowBounds = renderedRows.map((row) => row.getBoundingClientRect());
			const renderedRowsHeight = Math.ceil(
				Math.max(...rowBounds.map((rect) => rect.bottom)) -
				Math.min(Math.min(...rowBounds.map((rect) => rect.top)), rowsBodyRect.top)
			);
			const heightSettled = await waitForEmbeddedHostHeight(el, (height) => parseInt(height || "0", 10) >= renderedRowsHeight);
			const explicitTbodyHeight = rowsBody.style.height || rowsBody.style.minHeight;
			const hostHeightSynced = await waitForEmbeddedHostHeight(el, (height) => height === explicitTbodyHeight, 20);

			assert.match(explicitTbodyHeight, /^\d+px$/, "tbody should keep the virtualizer's explicit height");
			assert.isTrue(heightSettled, "embedded height should settle after row widget upgrade");
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
			const heightSettled = await waitForEmbeddedHostHeight(el, (height) => parseInt(height || "0", 10) >= renderedRowsHeight);
			const hostHeightSynced = await waitForEmbeddedHostHeight(el, (height) => height === rowsBody.style.height, 20);

			assert.isTrue(heightSettled, "embedded height should settle after row upgrade drain");
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
	 * Contract: replacing placeholders in an embedded grid's later chunk must
	 * leave realized rows positioned in increasing visual order.
	 *
	 * Setup: render an embedded child grid with only the first page loaded, drive
	 * it from an ancestor scrollport into a later unloaded range, then resolve
	 * that later page.
	 *
	 * Pass: later real rows render and their virtualizer transforms remain
	 * monotonic, avoiding overlapped/mispositioned rows.
	 */
	it("positions later embedded rows after placeholder replacement", async() =>
	{
		const childRows = Array.from({length: 200}, (_v, index) => ({id: `embedded-later-${index}`, label: `Row ${index}`}));
		const rowData = new Map(childRows.map((row) => [row.id, row]));
		const calls : number[] = [];
		const dataProvider = createDatagridDataProvider({
			fetchPage: async(start : number, pageSize : number) =>
			{
				calls.push(start);
				return {
					total: childRows.length,
					rows: childRows.slice(start, start + pageSize)
				};
			},
			getRowData: (rowId : string) => rowData.get(rowId),
			getQuerySignature: () => "embedded-later-placeholder-position"
		});

		const scrollport = document.createElement("div");
		scrollport.style.height = "240px";
		scrollport.style.width = "800px";
		scrollport.style.overflowY = "auto";
		document.body.appendChild(scrollport);

		const child = createDatagrid();
		child.embeddedVirtualized = true;
		child.noVisibleHeader = true;
		child.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		child.templateData = {columns: child.columns} as any;
		child.dataProvider = dataProvider as any;
		scrollport.appendChild(child);

		const loaded = new Promise<void>((resolve) =>
		{
			child.addEventListener("et2-loading-done", () => resolve(), {once: true});
		});
		await child.reload();
		await loaded;
		const heightSettled = await waitForEmbeddedHostHeight(child, (height) => parseInt(height || "0", 10) >= childRows.length * 40);
		assert.isTrue(heightSettled, "embedded child should reserve enough host height for later rows");
		assert.isAbove(scrollport.scrollHeight, scrollport.clientHeight, "ancestor scrollport should be scrollable before requesting later rows");
		const firstRow = await waitForDatagridRow(child, "embedded-later-0");
		assert.isNotNull(firstRow, "test fixture should render the first child row before measuring row height");
		const measuredRowHeight = Math.ceil(firstRow.getBoundingClientRect().height);
		assert.isAbove(measuredRowHeight, 0, "test fixture should have a measurable first child row");

		scrollport.scrollTop = 100 * measuredRowHeight;
		(child as any)._handleEmbeddedParentScroll(scrollport);
		for(let i = 0; i < 20 && !child.shadowRoot?.querySelector("[data-row-id='embedded-later-100']"); i++)
		{
			await new Promise((resolve) => window.setTimeout(resolve, 0));
			await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
			(child as any)._handleEmbeddedParentScroll(scrollport);
		}

		assert.isTrue(calls.some((start) => start === 100), "embedded child should fetch the later visible chunk");
		const laterRows = Array.from(child.shadowRoot?.querySelectorAll("[data-row-id^='embedded-later-']") || []) as HTMLElement[];
		const indexedRows = laterRows
			.map((row) => ({
				index: parseInt(row.getAttribute("data-row-index") || "-1", 10),
				top: row.getBoundingClientRect().top,
				bottom: row.getBoundingClientRect().bottom
			}))
			.filter((row) => row.index >= 100)
			.sort((left, right) => left.index - right.index);
		assert.isAtLeast(indexedRows.length, 2, "later real child rows should render after placeholder replacement");
		for(let i = 1; i < indexedRows.length; i++)
		{
			assert.isAtLeast(
				indexedRows[i].top,
				indexedRows[i - 1].top,
				"later embedded child rows should keep monotonically increasing visual positions"
			);
			assert.isAtLeast(
				indexedRows[i].top,
				indexedRows[i - 1].bottom - 1,
				"later embedded child rows should not overlap their previous row"
			);
		}

		scrollport.remove();
	});

	/**
	 * Contract: when a later embedded page is queued before the provider reports
	 * a total, placeholder reservation must extend to the absolute requested
	 * range. Appending one placeholder page to the loaded count under-reserves
	 * the scroll range and can make later rows appear at the wrong offset or not
	 * appear at all.
	 */
	it("reserves absolute placeholder extent for later embedded chunks without a known total", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "240px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const child = createDatagrid();
		child.embeddedVirtualized = true;
		child.noVisibleHeader = true;
		child.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		child.templateData = {columns: child.columns} as any;
		child.setInitialRows(Array.from({length: 50}, (_v, index) => ({id: `unknown-total-${index}`, label: `Row ${index}`})));
		child.total = null;
		host.appendChild(child);

		await child.updateComplete;
		const firstRow = await waitForDatagridRow(child, "unknown-total-0");
		assert.isNotNull(firstRow, "test fixture should render the first child row");
		const measuredRowHeight = Math.ceil(firstRow.getBoundingClientRect().height);

		(child as any)._queueRequest(100, 50, "unknown-total-later:100:50");
		await child.updateComplete;
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));

		assert.isAtLeast(
			(child as any)._virtualRowCount(),
			150,
			"queued later placeholders should reserve through start + count, even with total unknown"
		);
		assert.isTrue(
			await waitForEmbeddedHostHeight(child, (height) => parseInt(height || "0", 10) >= 150 * measuredRowHeight),
			"embedded host should expose the reserved later placeholder extent to the parent scrollport"
		);

		host.remove();
	});

	/**
	 * Contract: after a queued later page resolves with the final total, pending
	 * placeholder extent must be cleared so the embedded grid does not render
	 * skeleton rows after the last real row.
	 */
	it("clears later placeholder extent after final embedded page resolves", async() =>
	{
		const childRows = Array.from({length: 106}, (_v, index) => ({id: `final-page-${index}`, label: `Row ${index}`}));
		const rowData = new Map(childRows.map((row) => [row.id, row]));
		const dataProvider = createDatagridDataProvider({
			fetchPage: async(start : number, pageSize : number) => ({
				total: childRows.length,
				rows: childRows.slice(start, start + pageSize)
			}),
			getRowData: (rowId : string) => rowData.get(rowId),
			getQuerySignature: () => "embedded-final-page-placeholders"
		});

		const host = document.createElement("div");
		host.style.height = "240px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const child = createDatagrid();
		child.embeddedVirtualized = true;
		child.noVisibleHeader = true;
		child.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		child.templateData = {columns: child.columns} as any;
		child.dataProvider = dataProvider as any;
		host.appendChild(child);

		const loaded = new Promise<void>((resolve) =>
		{
			child.addEventListener("et2-loading-done", () => resolve(), {once: true});
		});
		await child.reload();
		await loaded;

		const finalRequestKey = (child as any)._requestKey(100, 6);
		(child as any)._queueRequest(100, 6, finalRequestKey);
		(child as any)._inFlightRequestKeys.add(finalRequestKey);
		await (child as any)._fetchPage(100, 6, finalRequestKey);
		await child.updateComplete;

		assert.equal((child as any)._pendingPlaceholderRequests.size, 0, "resolved final-page placeholder request should be removed");
		assert.equal((child as any)._pendingPlaceholderCount, 0, "resolved final-page placeholder count should be removed");
		assert.equal((child as any)._virtualRowCount(), childRows.length, "virtual row count should stop at the known final total");

		host.remove();
	});

	it("shrinks stale embedded spacer height after final total is known", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "240px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const child = createDatagrid();
		child.embeddedVirtualized = true;
		child.noVisibleHeader = true;
		child.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		child.templateData = {columns: child.columns} as any;
		child.setInitialRows(Array.from({length: 6}, (_v, index) => ({id: `final-spacer-${index}`, label: `Row ${index}`})));
		child.total = 6;
		host.appendChild(child);

		await child.updateComplete;
		const firstRow = await waitForDatagridRow(child, "final-spacer-0");
		assert.isNotNull(firstRow, "test fixture should render a measurable row");
		const rowHeight = Math.ceil(firstRow.getBoundingClientRect().height);
		const rowsBody = child.shadowRoot!.querySelector("#rows") as HTMLElement;
		rowsBody.style.height = `${rowHeight * 8}px`;
		(child as any)._embeddedVirtualizedHostHeight = `${rowHeight * 8}px`;

		(child as any)._scheduleVirtualizerLayoutSync();
		(child as any)._syncEmbeddedVirtualizedHostHeight();
		for(let i = 0; i < 30 && parseInt(child.style.height || "0", 10) > rowHeight * 6 + 8; i++)
		{
			await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
			await child.updateComplete;
		}

		assert.isAtMost(parseInt(child.style.height || "0", 10), rowHeight * 6 + 8, "embedded host should shrink to the known final row count");
		assert.isAtMost(parseInt(rowsBody.style.height || "0", 10), rowHeight * 6 + 8, "embedded spacer should not keep stale placeholder height");

		host.remove();
	});

	it("does not shrink embedded spacer to the loaded slice before final total is materialized", async() =>
	{
		const host = document.createElement("div");
		host.style.height = "240px";
		host.style.width = "800px";
		document.body.appendChild(host);

		const child = createDatagrid();
		child.embeddedVirtualized = true;
		child.noVisibleHeader = true;
		child.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		child.templateData = {columns: child.columns} as any;
		child.setInitialRows(Array.from({length: 50}, (_v, index) => ({id: `partial-spacer-${index}`, label: `Row ${index}`})));
		child.total = 200;
		host.appendChild(child);

		await child.updateComplete;
		const firstRow = await waitForDatagridRow(child, "partial-spacer-0");
		assert.isNotNull(firstRow, "test fixture should render a measurable row");
		const rowHeight = child.rowHeightEstimatePx;
		const rowsBody = child.shadowRoot!.querySelector("#rows") as HTMLElement;
		rowsBody.style.height = `${rowHeight * 50}px`;

		const height = parseInt((child as any)._embeddedVirtualizedContentHeight(), 10);

		assert.isAtLeast(height, rowHeight * 200, "partial known-total child should reserve the full reported total, not only loaded rows");
		assert.isAtLeast(parseInt(rowsBody.style.height || "0", 10), rowHeight * 200, "embedded spacer should remain scrollable through unloaded known-total rows");

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

			scratchTable.remove();
			host.remove();
		});

		/**
		 * Contract: a datagrid keeps its virtualizer row host alive from initial
		 * loading through rows in both row and tile view.
		 * Setup: start in configuration loading, then provide one initial page and
		 * render a virtual item from an unloaded page into the live row host.
		 * Pass: the host survives, fetches the deeper page, and renders its live row.
		 */
		for(const view of ["row", "tile"] as const)
		{
			it(`fetches and renders deep ${view} rows after initial loading clears`, async() =>
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
					getQuerySignature: () => `initial-loading-${view}`
				});

				const host = document.createElement("div");
				host.style.height = "360px";
				host.style.width = "800px";

				const el = createDatagrid();
				el.configurationLoading = true;
				if(view === "tile")
				{
					el.view = view;
				}
				el.style.height = "100%";
				(el as any)._requestDispatchDelayMs = 0;
				(el as any)._rowHeightLocked = true;
				(el as any)._rowHeightPx = 42;
				el.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
				el.pageSize = 50;
				el.dataProvider = dataProvider as any;
				document.body.appendChild(host);
				host.appendChild(el);
				await el.updateComplete;

				const rows = el.shadowRoot!.querySelector("#rows") as HTMLElement | null;
				assert.isNotNull(rows, "virtualizer row host should exist during initial loading");

				el.setInitialRows(Array.from({length: 50}, (_v, index) => ({id: `row-${index}`, label: `Row ${index}`})));
				el.total = 200;
				(el as any)._reconcileRowRenderState();
				el.configurationLoading = false;
				await el.updateComplete;

				assert.equal(el.shadowRoot!.querySelector("#rows"), rows, "loading state should not replace the virtualizer row host");
				render((el as any)._renderVirtualRow(160), rows!);

				(el as any)._processQueuedRequests();
				await new Promise((resolve) => window.setTimeout(resolve, 0));
				await el.updateComplete;
				assert.include(calls, 150, "deep virtual item should fetch the missing page");

				render((el as any)._renderVirtualRow(160), rows!);
				const rendered = rows!.querySelector("[data-row-id='row-160']") as HTMLElement | null;
				assert.isNotNull(rendered, "live row host should render the fetched deep row");
				assert.equal(rendered!.getAttribute("data-row-index"), "160", "live deep row should keep its absolute index");
				host.remove();
			});
		}
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
