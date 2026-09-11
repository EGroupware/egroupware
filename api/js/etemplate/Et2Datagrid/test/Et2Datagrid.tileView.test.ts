import {assert} from "@open-wc/testing";
import {Et2Datagrid} from "../Et2Datagrid";
import {virtualizerRef} from "@lit-labs/virtualizer/virtualize.js";

function createDatagridDataProvider(overrides : Record<string, any> = {}, prefix : string = "test")
{
	return {
		fetchPage: async() => ({rows: [], total: 0}),
		getDataStorePrefix: () => prefix,
		normalizeRowId: (rowId : string | number) => String(rowId ?? ""),
		toProviderRowId: (rowId : string) => rowId,
		refresh: async() => ({rows: [], removedRowIds: []}),
		...overrides
	};
}

function createTileDatagrid() : Et2Datagrid
{
	const el = new Et2Datagrid();
	el.dataProvider = createDatagridDataProvider() as any;
	el.view = "tile";
	const tileColumns = [{key: "label", title: "Label", width: "100%"}] as any;
	el.columns = tileColumns;
	el.templateData = {
		rowTemplateId: "test.tile",
		view: "tile",
		tileLayout: {width: "160px", height: "120px"},
		columns: tileColumns
	} as any;
	return el;
}

function makeItems(n : number)
{
	return Array.from({length: n}, (_v, index) => ({id: `row-${index}`, label: `Row ${index}`}));
}

async function waitForTileRow(el : Et2Datagrid, rowId : string) : Promise<HTMLElement | null>
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

describe("Et2Datagrid tile view virtualizer connection recovery", () =>
{
	/**
	 * Behaviour under test: something can leave the tile view's @lit-labs/virtualizer
	 * AsyncDirective with its internal `_connected` flag stuck `false` (its
	 * ResizeObserver torn down, `_updateLayout()`'s `this._layout && this._connected`
	 * guard permanently blocking work) even though the Et2Datagrid host element itself
	 * is genuinely connected and continues receiving normal property updates. This was
	 * confirmed live against a real anonymous filemanager share: reparenting the
	 * share's containing <form> left the tile grid's virtualizer stuck disconnected -
	 * confirmed via instrumentation that the very next reconnect succeeds
	 * (`_connected` briefly becomes `true` again), but a further disconnect happens
	 * afterward at the directive/part level rather than through this host's native
	 * connectedCallback/disconnectedCallback, so connectedCallback() never gets
	 * another chance to run and heal it - #rows stayed empty forever once real row
	 * data arrived, and not fixable by a window resize, since the virtualizer was
	 * asleep rather than mismeasuring.
	 *
	 * Fix: Et2Datagrid._reconnectStuckVirtualizer() (checked from both
	 * connectedCallback() and updated()) detects `_connected === false` while this
	 * host is still connected and calls virtualizer.connected() directly. Checking
	 * from updated() - which keeps running on ordinary property changes even when no
	 * further connectedCallback ever fires - is what actually covers the confirmed
	 * failure path; connectedCallback() alone is not sufficient.
	 *
	 * Setup: mount a real tile-view grid with real rows so a genuine Virtualizer
	 * instance bootstraps, then reach it via the library's own exported
	 * `virtualizerRef` symbol (the same mechanism Et2Datagrid itself uses) and call
	 * its `disconnected()` directly - without ever removing the host from the
	 * document - to reproduce the stuck state precisely, independent of whatever
	 * obscure browser/Lit interaction produces it in production (a plain DOM
	 * reparent in this test environment reconnects cleanly, matching what was
	 * confirmed live: the immediate reconnect after reparenting is not the problem).
	 *
	 * Pass criteria: rows assigned while the virtualizer is stuck disconnected must
	 * still render once Et2Datagrid processes the next ordinary update, and the
	 * virtualizer's own `_connected` flag must end up `true` again - not just that
	 * connectedCallback() was called, since the flag alone is not the real contract.
	 */
	it("self-heals a virtualizer stuck disconnected on the next ordinary update", async() =>
	{
		const host = document.createElement("div");
		host.style.cssText = "height:400px;width:600px;";
		document.body.appendChild(host);

		const el = createTileDatagrid();
		el.setInitialRows(makeItems(15));
		el.total = 15;
		host.appendChild(el);

		await el.updateComplete;
		assert.isNotNull(await waitForTileRow(el, "row-0"), "rows should render on initial mount");

		const rowsBody = el.shadowRoot!.querySelector("#rows") as any;
		const virtualizer = rowsBody[virtualizerRef];
		assert.isTrue(virtualizer._connected, "precondition: virtualizer starts out connected");

		// Force exactly the confirmed-live stuck state, without touching the DOM.
		virtualizer.disconnected();
		assert.isFalse(virtualizer._connected, "precondition: virtualizer is now disconnected");

		// This is the real-world failure trigger: ordinary data arriving while the
		// virtualizer is stuck. Without the fix, this row would never render.
		el.setInitialRows(makeItems(20));
		el.total = 20;
		await el.updateComplete;

		assert.isNotNull(
			await waitForTileRow(el, "row-19"),
			"rows assigned while stuck must render once the next update heals the virtualizer, not stay dropped forever"
		);
		assert.isTrue(virtualizer._connected, "the virtualizer must have been reconnected, not just have happened to render once");

		host.remove();
	});
});
