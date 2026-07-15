import {assert} from "@open-wc/testing";
import {Et2Datagrid} from "../Et2Datagrid";
// @ts-ignore TS2691: web-test-runner transpiles this source import; generated JS may be stale here.
import {Et2RowProvider} from "../Et2RowProvider.ts";
import {Et2DatagridTemplateData} from "../Et2Datagrid.types";
import "../../Layout/Et2Box/Et2Box";

const egwStub = {
	debug: () => {},
	lang: (label : string) => label,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	preference: () => null,
	set_preference: () => {},
	app_name: () => "test",
	link: (url : string) => url,
	hashString: async(value : string) => {
		const data = (new TextEncoder()).encode(value);
		const hashBuffer = await crypto.subtle.digest("SHA-256", data);
		return Array.from(new Uint8Array(hashBuffer)).map(byte => byte.toString(16).padStart(2, "0")).join("");
	}
};
window.egw = function() { return egwStub; } as any;
Object.assign(window.egw, egwStub);

function createDatagridDataProvider(prefix : string = "test")
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
		refresh: async() => ({rows: [], removedRowIds: []})
	};
}

function tileTemplateRoot() : Element
{
	const documentXml = new DOMParser().parseFromString(`
		<template id="test.tile">
			<grid width="100%">
				<columns>
					<column width="100%"/>
				</columns>
				<rows>
					<row class="th">
						<nextmatch-header/>
					</row>
					<row class="tile $row_cont[class]">
						<et2-vbox class="tile-card" width="135px" height="110px" data="kind:$row_cont[kind]" statustext="$row_cont[title]">
							<et2-description id="\${row}[title]" no_lang="1"></et2-description>
						</et2-vbox>
					</row>
				</rows>
			</grid>
		</template>
	`, "text/xml");
	return documentXml.documentElement;
}

function rowTemplateRoot() : Element
{
	const documentXml = new DOMParser().parseFromString(`
		<template id="test.row">
			<grid width="100%">
				<columns>
					<column width="100%"/>
				</columns>
				<rows>
					<row class="th">
						<nextmatch-header/>
					</row>
					<row class="$row_cont[class]">
						<et2-vbox class="row-card" data="kind:$row_cont[kind]" statustext="$row_cont[title]">
							<et2-description id="\${row}[title]" no_lang="1"></et2-description>
						</et2-vbox>
					</row>
				</rows>
			</grid>
		</template>
	`, "text/xml");
	return documentXml.documentElement;
}

async function waitForRenderedRow(el : Et2Datagrid, rowId : string) : Promise<HTMLElement | null>
{
	for(let i = 0; i < 20; i++)
	{
		const row = el.shadowRoot?.querySelector(`[data-row-id='${rowId}']`) as HTMLElement | null;
		if(row)
		{
			return row;
		}
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		await el.updateComplete;
	}
	return null;
}

async function waitForUpgradedRow(el : Et2Datagrid, rowId : string) : Promise<HTMLElement | null>
{
	for(let i = 0; i < 20; i++)
	{
		const row = el.shadowRoot?.querySelector(`[data-row-id='${rowId}'][data-et2dg-upgraded-for]`) as HTMLElement | null;
		if(row)
		{
			return row;
		}
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		await el.updateComplete;
	}
	return null;
}

async function waitForUpgradedRowTag(el : Et2Datagrid, rowId : string, tagName : string) : Promise<HTMLElement | null>
{
	for(let i = 0; i < 20; i++)
	{
		const rows = Array.from(el.shadowRoot?.querySelectorAll(`[data-row-id='${rowId}'][data-et2dg-upgraded-for]`) || []) as HTMLElement[];
		const row = rows.find((candidate) => candidate.tagName.toLowerCase() === tagName);
		if(row)
		{
			return row;
		}
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		await el.updateComplete;
	}
	return null;
}

describe("Et2Datagrid tile view", () =>
{
	let originalResizeObserver : typeof window.ResizeObserver | undefined;
	let originalWindowOnError : OnErrorEventHandler | null = null;
	let resizeObserverErrorHandler : ((event : ErrorEvent) => void) | null = null;
	let resizeObserverRejectionHandler : ((event : PromiseRejectionEvent) => void) | null = null;

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

	/**
	 * Contract under test:
	 * - Tile templates declare tile layout by using a `tile` row class and
	 *   generic fixed dimensions on their tile content.
	 *
	 * Setup strategy:
	 * - Parse a tile-shaped XML template through Et2RowProvider's template-root
	 *   parser.
	 *
	 * Pass criteria:
	 * - The resolved template data is marked as tile view, includes fixed tile
	 *   dimensions, and the reusable row template has a non-table root.
	 */
	it("infers generic tile layout metadata from a tile row template", async() =>
	{
		const provider = new Et2RowProvider({egw: () => egwStub} as any);
		const templateData = await (provider as any)._fromTemplateRoot(tileTemplateRoot()) as Et2DatagridTemplateData;

		assert.equal(templateData.view, "tile", "tile row class should select tile view metadata");
		assert.equal(templateData.tileLayout?.width, "135px", "tile width should come from fixed tile content");
		assert.equal(templateData.tileLayout?.height, "110px", "tile height should come from fixed tile content");
		assert.equal(templateData.rowTemplate?.content.firstElementChild?.tagName.toLowerCase(), "div", "tile rows should not render as table rows");
		assert.isTrue(templateData.rowTemplate?.content.firstElementChild?.classList.contains("tile"), "tile class should be retained");
	});

	it("uses normalized row markup in template signatures", async() =>
	{
		const rowTemplateRoot = new DOMParser().parseFromString(`
			<template id="same.id">
				<grid>
					<columns><column width="100%"/></columns>
					<rows>
						<row class="th"><nextmatch-header/></row>
						<row><et2-description id="\${row}[title]"></et2-description></row>
					</rows>
				</grid>
			</template>
		`, "text/xml").documentElement;
		const tileTemplateRoot = new DOMParser().parseFromString(`
			<template id="same.id">
				<grid>
					<columns><column width="100%"/></columns>
					<rows>
						<row class="th"><nextmatch-header/></row>
						<row class="tile"><et2-description id="\${row}[title]"></et2-description></row>
					</rows>
				</grid>
			</template>
		`, "text/xml").documentElement;
		const provider = new Et2RowProvider({egw: () => egwStub} as any);
		const rowTemplateData = await (provider as any)._fromTemplateRoot(rowTemplateRoot) as Et2DatagridTemplateData;
		const tileTemplateData = await (provider as any)._fromTemplateRoot(tileTemplateRoot) as Et2DatagridTemplateData;

		assert.equal(rowTemplateData.rowTemplateId, tileTemplateData.rowTemplateId, "fixture should keep the same template id");
		assert.isString(rowTemplateData.templateSignature, "row template should have a render signature");
		assert.isString(tileTemplateData.templateSignature, "tile template should have a render signature");
		assert.isBelow(rowTemplateData.templateSignature?.length || 0, 80, "row template signature should remain compact");
		assert.isBelow(tileTemplateData.templateSignature?.length || 0, 80, "tile template signature should remain compact");
		assert.notEqual(rowTemplateData.templateSignature, tileTemplateData.templateSignature, "row and tile markup must invalidate different virtualized rows");
	});

	it("applies category classes from row data even without a category class placeholder", () =>
	{
		const row = document.createElement("div");
		row.className = "tile";

		Et2RowProvider.customizeRowRootAttributes(row, {cat_id: "3,7"}, (rowData, key) => rowData[key]);

		assert.isTrue(row.classList.contains("row_category"), "category marker class should be applied from row data");
		assert.isTrue(row.classList.contains("cat_3"), "first category class should be applied from row data");
		assert.isTrue(row.classList.contains("cat_7"), "second category class should be applied from row data");
	});

	/**
	 * Contract under test:
	 * - Tile mode virtualizes each entry as its own item, not as a synthetic row
	 *   containing multiple entries.
	 *
	 * Setup strategy:
	 * - Render Et2Datagrid in tile mode with two initial rows and fixed tile
	 *   metadata.
	 *
	 * Pass criteria:
	 * - The tile virtualizer host is used, each entry has its own `data-row-id`,
	 *   and no table row is produced for the tile entries.
	 */
	it("renders tile entries as individual virtual items", async() =>
	{
		const provider = new Et2RowProvider({egw: () => egwStub} as any);
		const templateData = await (provider as any)._fromTemplateRoot(tileTemplateRoot()) as Et2DatagridTemplateData;
		const el = new Et2Datagrid();
		el.style.width = "420px";
		el.style.height = "240px";
		el.view = "tile";
		el.templateData = templateData;
		el.columns = templateData.columns;
		el.dataProvider = createDatagridDataProvider() as any;
		document.body.appendChild(el);
		el.setInitialRows([
			{id: "test::/one", title: "one", kind: "document"},
			{id: "test::/two", title: "two", kind: "document"}
		]);
		await el.updateComplete;

		const first = await waitForRenderedRow(el, "test::/one");
		const second = await waitForRenderedRow(el, "test::/two");
		const tileGrid = el.shadowRoot?.querySelector(".dg-tile-grid") as HTMLElement | null;

		assert.isOk(tileGrid, "tile mode should render the tile virtualizer host");
		assert.isOk(first, "first tile item should render with its own row id");
		assert.isOk(second, "second tile item should render with its own row id");
		assert.equal(first?.tagName.toLowerCase(), "div", "tile item should be a non-table element");
		assert.isNull(first?.closest("tr"), "tile item should not be wrapped in a table row");

		el.remove();
	});

	/**
	 * Contract under test:
	 * - Tile entries use the same deferred row upgrade path as table rows.
	 *
	 * Setup strategy:
	 * - Render a tile template with a child attribute bound to `$row_cont[kind]`.
	 *
	 * Pass criteria:
	 * - The top-level tile row is marked upgraded and the child attribute is
	 *   transformed from the row's data.
	 */
	it("upgrades tile row template widgets after render", async() =>
	{
		const provider = new Et2RowProvider({egw: () => egwStub} as any);
		const templateData = await (provider as any)._fromTemplateRoot(tileTemplateRoot()) as Et2DatagridTemplateData;
		const el = new Et2Datagrid();
		el.style.width = "420px";
		el.style.height = "240px";
		el.view = "tile";
		el.templateData = templateData;
		el.columns = templateData.columns;
		el.dataProvider = createDatagridDataProvider() as any;
		document.body.appendChild(el);
		el.setInitialRows([
			{id: "test::/one", title: "one", kind: "document"}
		]);
		await el.updateComplete;

		const row = await waitForUpgradedRow(el, "test::/one");
		const tileCard = row?.querySelector(".tile-card") as HTMLElement | null;

		assert.isOk(row, "tile row should be upgraded after render");
		assert.equal(tileCard?.getAttribute("data-kind"), "document", "tile child attributes should be transformed from row data");
		assert.equal(tileCard?.getAttribute("statustext"), "one", "tile child widget statustext should be transformed from row data");
		assert.equal((tileCard as any)?.statustext, "one", "tile child widget statustext property should be transformed from row data");

		el.remove();
	});

	/**
	 * Contract under test:
	 * - Switching between row and tile templates rebinds row upgrade handling to
	 *   the replacement `#rows` container.
	 *
	 * Setup strategy:
	 * - Render tile view first, switch to a table row template, then switch back
	 *   to the tile template with the same materialized row data.
	 *
	 * Pass criteria:
	 * - Rows rendered after each switch are marked upgraded and their child
	 *   row-scoped attributes are transformed from row data.
	 */
	it("upgrades rows after switching between tile and row templates", async() =>
	{
		const provider = new Et2RowProvider({egw: () => egwStub} as any);
		const tileTemplateData = await (provider as any)._fromTemplateRoot(tileTemplateRoot()) as Et2DatagridTemplateData;
		const rowTemplateData = await (provider as any)._fromTemplateRoot(rowTemplateRoot()) as Et2DatagridTemplateData;
		const el = new Et2Datagrid();
		el.style.width = "420px";
		el.style.height = "240px";
		el.view = "tile";
		el.templateData = tileTemplateData;
		el.columns = tileTemplateData.columns;
		el.dataProvider = createDatagridDataProvider() as any;
		document.body.appendChild(el);
		el.setInitialRows([
			{id: "test::/one", title: "one", kind: "document"}
		]);
		await el.updateComplete;
		assert.isOk(await waitForUpgradedRowTag(el, "test::/one", "div"), "initial tile row should upgrade");

		el.view = "row";
		el.templateData = rowTemplateData;
		el.columns = rowTemplateData.columns;
		await el.updateComplete;
		const row = await waitForUpgradedRowTag(el, "test::/one", "tr");
		const rowCard = row?.querySelector(".row-card") as HTMLElement | null;

		assert.isOk(row, "row view should upgrade after switching from tile view");
		assert.equal(rowCard?.getAttribute("data-kind"), "document", "row child attributes should transform after switching from tile view");
		assert.equal(rowCard?.getAttribute("statustext"), "one", "row child statustext should transform after switching from tile view");

		el.view = "tile";
		el.templateData = tileTemplateData;
		el.columns = tileTemplateData.columns;
		await el.updateComplete;
		const tile = await waitForUpgradedRowTag(el, "test::/one", "div");
		const tileCard = tile?.querySelector(".tile-card") as HTMLElement | null;

		assert.isOk(tile, "tile view should upgrade after switching from row view");
		assert.equal(tileCard?.getAttribute("data-kind"), "document", "tile child attributes should transform after switching from row view");
		assert.equal(tileCard?.getAttribute("statustext"), "one", "tile child statustext should transform after switching from row view");

		el.remove();
	});
});
