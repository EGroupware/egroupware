import {assert} from "@open-wc/testing";
import {render} from "lit";
import {Et2Datagrid} from "../Et2Datagrid";
import {Et2DatagridSelectionController} from "../Et2DatagridSelectionController";
import {assertNoElement} from "../../test/assertDom";
import * as sinon from "sinon";

const egw = {
	debug: () => {},
	lang: (label : string) => label,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	preference: () => null,
	set_preference: () => {},
	app_name: () => "addressbook",
	link: (url : string) => url
};
window.egw = function() { return egw; } as any;
Object.assign(window.egw, egw);

function createDataProvider(rows : any[])
{
	return {
		fetchPage: async(start : number, pageSize : number) => ({
			rows: rows.slice(start, start + pageSize),
			total: rows.length
		}),
		getDataStorePrefix: () => "addressbook",
		normalizeRowId: (rowId : string | number, ensurePrefix? : boolean) =>
		{
			const normalized = String(rowId ?? "");
			return ensurePrefix && !normalized.startsWith("addressbook::") ? `addressbook::${normalized}` : normalized;
		},
		toProviderRowId: (rowId : string) => rowId.replace(/^addressbook::/, ""),
		refresh: async() => ({rows: [], removedRowIds: []})
	};
}

function createDatagrid(rows : any[]) : Et2Datagrid
{
	const grid = new Et2Datagrid();
	grid.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
	grid.templateData = {columns: grid.columns} as any;
	grid.dataProvider = createDataProvider(rows) as any;
	return grid;
}

/**
 * Render one virtual row into `container` the way a mounted row reaches the DOM.
 *
 * _renderVirtualRow() deliberately builds a *selection-agnostic* row: baking the selection
 * into the HTML string it hands to unsafeHTML() made every affected row node get thrown away
 * and rebuilt (unhydrated) on the first render after any selection change - visible as the
 * whole list flickering on select-all.  The grid stamps selection onto the mounted node
 * instead, from the row renderer's mutation observer.  This container is not the grid's own
 * rows body, so point the grid at it and run that same pass here; without it the assertions
 * below would be testing the row *template* rather than the row the user sees.
 */
function renderVirtualRow(grid : Et2Datagrid, rowIndex : number, container : HTMLElement) : HTMLElement | null
{
	Object.defineProperty(grid, "_rowsBody", {configurable: true, get: () => container});
	render((grid as any)._renderVirtualRow(rowIndex), container);
	(grid as any)._syncRowAccessibilityState();
	return container.querySelector("[data-row-id]") as HTMLElement | null;
}

describe("Et2Datagrid row selection", () =>
{
	/**
	 * Contract: pointer selection follows the configured none, single, and
	 * multiple selection modes.
	 *
	 * Setup: exercise each mode against a three-row grid.
	 *
	 * Pass: none ignores selection, single replaces it, and multiple supports
	 * additive and range selection.
	 */
	it("applies pointer selection behavior for each selection mode", () =>
	{
		const rows = Array.from({length: 3}, (_value, index) => ({id: `row-${index}`, label: `Row ${index}`}));
		const grid = createDatagrid(rows);
		grid.setInitialRows(rows);
		grid.total = rows.length;

		grid.selectionMode = "none";
		(grid as any).selectedRowIds = new Set(["row-0"]);
		(grid as any)._updateSelectionFromPointer("row-1", 1, new MouseEvent("click"));
		assert.sameMembers(Array.from((grid as any).selectedRowIds), ["row-0"], "none mode should ignore pointer selection");

		grid.selectionMode = "single";
		(grid as any)._updateSelectionFromPointer("row-1", 1, new MouseEvent("click"));
		(grid as any)._updateSelectionFromPointer("row-2", 2, new MouseEvent("click", {ctrlKey: true}));
		assert.sameMembers(Array.from((grid as any).selectedRowIds), ["row-2"], "single mode should retain only the last selected row");

		grid.selectionMode = "multiple";
		(grid as any)._updateSelectionFromPointer("row-0", 0, new MouseEvent("click"));
		(grid as any)._updateSelectionFromPointer("row-1", 1, new MouseEvent("click", {ctrlKey: true}));
		(grid as any)._updateSelectionFromPointer("row-2", 2, new MouseEvent("click", {shiftKey: true}));
		assert.sameMembers(Array.from((grid as any).selectedRowIds), ["row-1", "row-2"], "multiple mode should support additive and range selection");
	});

	/**
	 * Contract: selection is state keyed by row id, not by a physical virtualized
	 * DOM element.
	 *
	 * Setup: select a row, then invoke the virtualizer's row-render callback for
	 * a different absolute index in the same physical host.
	 *
	 * Pass: the first row is no longer rendered, while its selected id remains
	 * in the selection model.
	 */
	it("keeps selected row ids when scrolling recycles their rendered rows", () =>
	{
		const rows = Array.from({length: 100}, (_value, index) => ({id: `row-${index}`, label: `Row ${index}`}));
		const grid = createDatagrid(rows);
		grid.pageSize = 50;
		grid.setInitialRows(rows);
		grid.total = rows.length;
		grid.selectSingleRow("row-10");

		const table = document.createElement("table");
		const body = document.createElement("tbody");
		table.append(body);
		document.body.append(table);
		const selectedRow = renderVirtualRow(grid, 10, body);
		assert.equal(selectedRow?.getAttribute("aria-selected"), "true", "initial virtual row should be selected");

		const laterRow = renderVirtualRow(grid, 60, body);
		assert.equal(laterRow?.getAttribute("data-row-id"), "row-60", "later virtual row should replace the earlier rendered row");
		assertNoElement(body.querySelector("[data-row-id='row-10']"), "selected row should no longer be rendered after virtualizer recycling");
		assert.sameMembers(Array.from((grid as any).selectedRowIds), ["row-10"], "selection must outlive the rendered row element");
		table.remove();
	});

	/**
	 * Contract: a selected row receives selected accessibility state every time
	 * virtualization realizes it again.
	 *
	 * Setup: select a first-group row, recycle it out of the virtualizer host,
	 * then render that absolute row index again.
	 *
	 * Pass: the newly rendered element has aria-selected=true without another
	 * selection interaction.
	 */
	it("restores selected state when an off-screen row is rendered again", () =>
	{
		const rows = Array.from({length: 100}, (_value, index) => ({id: `row-${index}`, label: `Row ${index}`}));
		const grid = createDatagrid(rows);
		grid.pageSize = 50;
		grid.setInitialRows(rows);
		grid.total = rows.length;
		grid.selectSingleRow("row-10");

		const table = document.createElement("table");
		const body = document.createElement("tbody");
		table.append(body);
		document.body.append(table);
		renderVirtualRow(grid, 60, body);
		const restoredRow = renderVirtualRow(grid, 10, body);

		assert.equal(restoredRow?.getAttribute("data-row-id"), "row-10");
		assert.equal(restoredRow?.getAttribute("aria-selected"), "true", "re-rendered selected row should restore its selected state");
		table.remove();
	});

	/**
	 * Contract: changing filters reloads rows without discarding explicit row-id
	 * selection when the selected row remains in the filtered result.
	 *
	 * Setup: select a row, replace the data-provider result with a filtered page
	 * that still contains it, and reload as filter application does.
	 *
	 * Pass: the selection model and the row rendered from the filtered result
	 * retain the selected state.
	 */
	it("preserves matching selected rows across a filter reload", async() =>
	{
		const rows = [{id: "row-1", label: "Matches filter"}];
		const grid = createDatagrid(rows);
		grid.pageSize = 50;
		grid.setInitialRows(rows);
		grid.total = 1;
		grid.selectSingleRow("row-1");
		await grid.reload();

		assert.sameMembers(Array.from((grid as any).selectedRowIds), ["row-1"], "filter reload should retain explicit selected row ids");
		(grid as any)._rowsByIndex = [{id: "row-1", data: rows[0]}];
		grid.rows = [{id: "row-1", data: rows[0]}] as any;
		const table = document.createElement("table");
		const body = document.createElement("tbody");
		table.append(body);
		document.body.append(table);
		const filteredRow = renderVirtualRow(grid, 0, body);
		assert.equal(filteredRow?.getAttribute("aria-selected"), "true", "matching row should render as selected after the filter reload");
		table.remove();
	});

	/**
	 * Contract: select-all represents the complete result set, including rows
	 * not yet loaded from later fetch groups.
	 *
	 * Setup: load the first group of a 100-row result, select all, then fetch and
	 * render a row from the second group.
	 *
	 * Pass: the emitted selection is global and both already-loaded and newly
	 * fetched rows render as selected.
	 */
	it("selects all rows across loaded and later fetch groups", () =>
	{
		const rows = Array.from({length: 100}, (_value, index) => ({id: `row-${index}`, label: `Row ${index}`}));
		const grid = createDatagrid(rows);
		grid.pageSize = 50;
		grid.setInitialRows(rows.slice(0, 50));
		grid.total = rows.length;
		let selectionDetail : any;
		grid.addEventListener("et2-selection-changed", (event : Event) => selectionDetail = (event as CustomEvent).detail);

		grid.selectAllRows();
		assert.isTrue(selectionDetail?.allSelected, "select-all should report a selection over the complete result set");

		const table = document.createElement("table");
		const body = document.createElement("tbody");
		table.append(body);
		document.body.append(table);
		const firstGroupRow = renderVirtualRow(grid, 10, body);
		assert.equal(firstGroupRow?.getAttribute("aria-selected"), "true", "loaded row should render as selected after select-all");

		// This is the same indexed state populated by a later page fetch.  The
		// selection assertion intentionally runs after the first page has been
		// selected, when this second page was not yet materialized.
		(grid as any)._rowsByIndex[60] = {id: "row-60", data: rows[60]};
		grid.rows = [...grid.rows, {id: "row-60", data: rows[60]}] as any;
		const laterGroupRow = renderVirtualRow(grid, 60, body);
		assert.equal(laterGroupRow?.getAttribute("data-row-id"), "row-60", "second fetch group should load the requested row");
		assert.equal(laterGroupRow?.getAttribute("aria-selected"), "true", "later fetched row should render as selected after select-all");
		table.remove();
	});

	/**
	 * Shift range setup shared by the tests below: a 100-row result with only rows 0-9 and
	 * 90-99 loaded - what the user gets clicking the first row, jumping to the end and
	 * shift+clicking the last - and a data provider able to serve the gap.
	 */
	const gappedGrids : Et2Datagrid[] = [];
	afterEach(() =>
	{
		// no prefetch timer may outlive its test
		gappedGrids.splice(0).forEach((grid) => grid.stopSelectionPrefetch());
	});

	function createGappedGrid()
	{
		const rows = Array.from({length: 100}, (_value, index) => ({id: `row-${index}`, label: `Row ${index}`}));
		const grid = createDatagrid(rows);
		gappedGrids.push(grid);
		const fetchPage = sinon.spy((grid.dataProvider as any), "fetchPage");
		grid.pageSize = 10;
		grid.setInitialRows(rows.slice(0, 10));
		grid.total = rows.length;
		for(let index = 90; index < 100; index++)
		{
			(grid as any)._rowsByIndex[index] = {id: rows[index].id, data: rows[index]};
		}
		const selections : string[][] = [];
		grid.addEventListener("et2-selection-changed", (event : Event) =>
			selections.push((event as CustomEvent).detail.selectedRowIds));
		const shiftSelectAll = () =>
		{
			(grid as any)._updateSelectionFromPointer("row-0", 0, new MouseEvent("click"));
			(grid as any)._updateSelectionFromPointer("row-99", 99, new MouseEvent("click", {shiftKey: true}));
		};
		return {rows, grid, fetchPage, selections, shiftSelectAll};
	}

	/**
	 * Contract: a shift range over rows that were never fetched selects the loaded rows right
	 * away and reports the rest as pending - without fetching anything, so the click stays
	 * instant. Whatever acts on the selection fetches the missing ids (Et2Nextmatch).
	 *
	 * Pass: 20 rows selected, pendingSelectionRange covers the whole range, no page requested.
	 */
	it("selects the loaded rows of a shift range and reports the rest as pending, fetching nothing", async() =>
	{
		const {grid, fetchPage, shiftSelectAll} = createGappedGrid();

		shiftSelectAll();
		await new Promise(resolve => setTimeout(resolve, 50));

		assert.lengthOf(Array.from((grid as any).selectedRowIds), 20, "loaded rows of the range are selected right away");
		assert.deepEqual(grid.pendingSelectionRange, {start: 0, end: 99}, "the range must report it is incomplete");
		assert.isFalse(fetchPage.called, "a shift+click must not fetch anything");
	});

	/**
	 * Contract: rows of a pending shift range join the selection as they arrive (eg. the user
	 * scrolls into the gap), and the range stops being pending once all its rows are there.
	 */
	it("adds rows arriving inside a pending shift range to the selection", async() =>
	{
		const {rows, grid, selections, shiftSelectAll} = createGappedGrid();
		shiftSelectAll();

		await grid.loadRowRange(10, 89);
		await new Promise(resolve => setTimeout(resolve, 0));

		assert.sameMembers(Array.from((grid as any).selectedRowIds), rows.map((row) => row.id),
			"rows fetched inside the range must be selected");
		assert.isNull(grid.pendingSelectionRange, "nothing pending once every row is there");
		assert.lengthOf(selections[selections.length - 1], 100, "the grown selection must be announced");
	});

	/**
	 * Contract: ids fetched for a pending range by the nextmatch complete it at once.
	 */
	it("completes a pending shift range with ids fetched for it", () =>
	{
		const {rows, grid, selections, shiftSelectAll} = createGappedGrid();
		shiftSelectAll();

		grid.completePendingSelectionRange(rows.slice(10, 90).map((row) => row.id));

		assert.lengthOf(Array.from((grid as any).selectedRowIds), 100);
		assert.isNull(grid.pendingSelectionRange);
		assert.lengthOf(selections[selections.length - 1], 100, "the completed selection must be announced");
	});

	/**
	 * Contract: a new selection replaces a pending range - rows arriving for the old range
	 * must not be added to it.
	 */
	it("drops a pending shift range when the user selects something else", async() =>
	{
		const {grid, shiftSelectAll} = createGappedGrid();
		shiftSelectAll();
		(grid as any)._updateSelectionFromPointer("row-5", 5, new MouseEvent("click"));

		assert.isNull(grid.pendingSelectionRange, "the new selection is complete");
		await grid.loadRowRange(10, 89);
		await new Promise(resolve => setTimeout(resolve, 0));

		assert.sameMembers(Array.from((grid as any).selectedRowIds), ["row-5"], "the later selection must win");
	});

	describe("prefetching a pending shift range", () =>
	{
		const {PREFETCH_DELAY_MS, PREFETCH_MAX_ROWS} = Et2DatagridSelectionController;
		beforeEach(() => Et2DatagridSelectionController.PREFETCH_DELAY_MS = 10);
		afterEach(() =>
		{
			Et2DatagridSelectionController.PREFETCH_DELAY_MS = PREFETCH_DELAY_MS;
			Et2DatagridSelectionController.PREFETCH_MAX_ROWS = PREFETCH_MAX_ROWS;
		});

		/** Wait for `condition`, failing after `timeout` ms */
		async function waitFor(condition : () => boolean, timeout = 3000)
		{
			for(const end = Date.now() + timeout; !condition(); )
			{
				assert.isBelow(Date.now(), end, "timed out waiting");
				await new Promise(resolve => setTimeout(resolve, 10));
			}
		}

		/** Record every page request and how many were on their way at once */
		function trackRequests(grid : Et2Datagrid)
		{
			const provider = grid.dataProvider as any;
			const original = provider.fetchPage.bind(provider);
			const stats = {starts: [] as number[], inFlight: 0, maxInFlight: 0};
			provider.fetchPage = async(start : number, count : number) =>
			{
				stats.starts.push(start);
				stats.maxInFlight = Math.max(stats.maxInFlight, ++stats.inFlight);
				try
				{
					await new Promise(resolve => setTimeout(resolve, 5));
					return await original(start, count);
				}
				finally
				{
					stats.inFlight--;
				}
			};
			return stats;
		}

		/**
		 * Contract: shortly after the shift+click the missing rows are fetched in the background,
		 * one page at a time - never more than one request on its way - and join the selection.
		 */
		it("fetches the missing rows one page at a time after a short pause", async() =>
		{
			const {rows, grid, shiftSelectAll} = createGappedGrid();
			const stats = trackRequests(grid);

			shiftSelectAll();
			assert.deepEqual(stats.starts, [], "nothing fetched right at the click");
			await waitFor(() => grid.pendingSelectionRange === null);

			assert.deepEqual(stats.starts, [10, 20, 30, 40, 50, 60, 70, 80], "each missing page once, in order");
			assert.equal(stats.maxInFlight, 1, "low profile: one request at a time");
			assert.sameMembers(Array.from((grid as any).selectedRowIds), rows.map((row) => row.id), "fetched rows join the selection");
		});

		/**
		 * Contract: changing the selection during the pause means nothing is fetched at all.
		 */
		it("fetches nothing when the selection changes during the pause", async() =>
		{
			Et2DatagridSelectionController.PREFETCH_DELAY_MS = 50;
			const {grid, shiftSelectAll} = createGappedGrid();
			const stats = trackRequests(grid);

			shiftSelectAll();
			(grid as any)._updateSelectionFromPointer("row-5", 5, new MouseEvent("click"));
			await new Promise(resolve => setTimeout(resolve, 150));

			assert.deepEqual(stats.starts, [], "an abandoned range must cost no request");
		});

		/**
		 * Contract: the prefetch stops after PREFETCH_MAX_ROWS rows; the rest is left for an action.
		 */
		it("stops after the maximum number of rows", async() =>
		{
			Et2DatagridSelectionController.PREFETCH_MAX_ROWS = 20;
			const {grid, shiftSelectAll} = createGappedGrid();
			const stats = trackRequests(grid);

			shiftSelectAll();
			await waitFor(() => stats.starts.length >= 2 && stats.inFlight === 0);
			await new Promise(resolve => setTimeout(resolve, 100));

			assert.deepEqual(stats.starts, [10, 20], "only the first 20 missing rows");
			assert.deepEqual(grid.pendingSelectionRange, {start: 0, end: 99}, "the rest is still pending");
		});

		/**
		 * Contract: stopSelectionPrefetch() - what an action calls before fetching the rest
		 * itself - stops the prefetch before its next page.
		 */
		it("stops before the next page when told to", async() =>
		{
			const {grid, shiftSelectAll} = createGappedGrid();
			const stats = trackRequests(grid);

			// stop while the first page is on its way
			const provider = grid.dataProvider as any;
			const fetchPage = provider.fetchPage;
			provider.fetchPage = (start : number, count : number) =>
			{
				grid.stopSelectionPrefetch();
				return fetchPage(start, count);
			};

			shiftSelectAll();
			await waitFor(() => stats.starts.length === 1 && stats.inFlight === 0);
			await new Promise(resolve => setTimeout(resolve, 150));

			assert.deepEqual(stats.starts, [10], "the page on its way is the only one");
		});
	});

	/**
	 * Contract: a shift range over rows that are all loaded is complete right away - nothing
	 * fetched, nothing pending - so an action on it runs immediately, without a wait dialog.
	 */
	it("completes a shift range over loaded rows right away", async() =>
	{
		const rows = Array.from({length: 100}, (_value, index) => ({id: `row-${index}`, label: `Row ${index}`}));
		const grid = createDatagrid(rows);
		const fetchPage = sinon.spy((grid.dataProvider as any), "fetchPage");
		grid.pageSize = 10;
		grid.setInitialRows(rows);
		grid.total = rows.length;

		(grid as any)._updateSelectionFromPointer("row-0", 0, new MouseEvent("click"));
		(grid as any)._updateSelectionFromPointer("row-99", 99, new MouseEvent("click", {shiftKey: true}));
		await new Promise(resolve => setTimeout(resolve, 50));

		assert.lengthOf(Array.from((grid as any).selectedRowIds), 100, "every row selected synchronously");
		assert.isNull(grid.pendingSelectionRange, "nothing pending");
		assert.isFalse(fetchPage.called, "no row fetched again");
	});

	/**
	 * Contract: Ctrl+A uses the same global select-all behavior as the public
	 * select-all action, but only in multiple-selection mode.
	 *
	 * Setup: press Ctrl+A in multiple and single mode.
	 *
	 * Pass: multiple mode marks the complete result set selected; single mode
	 * leaves the existing selection unchanged.
	 */
	it("handles Ctrl+A only for multiple selection", () =>
	{
		const rows = Array.from({length: 3}, (_value, index) => ({id: `row-${index}`, label: `Row ${index}`}));
		const grid = createDatagrid(rows);
		grid.setInitialRows(rows);
		grid.total = rows.length;
		const selectAll = new KeyboardEvent("keydown", {key: "a", ctrlKey: true, cancelable: true});
		(grid as any)._handleTableKeydown(selectAll);
		assert.isTrue(selectAll.defaultPrevented, "multiple mode should handle Ctrl+A");
		assert.isTrue((grid as any).allSelected, "Ctrl+A should select the complete result set");

		grid.selectionMode = "single";
		(grid as any).allSelected = false;
		(grid as any).selectedRowIds = new Set(["row-0"]);
		const ignored = new KeyboardEvent("keydown", {key: "a", ctrlKey: true, cancelable: true});
		(grid as any)._handleTableKeydown(ignored);
		assert.isFalse(ignored.defaultPrevented, "single mode should not intercept Ctrl+A");
		assert.sameMembers(Array.from((grid as any).selectedRowIds), ["row-0"], "single mode selection should remain unchanged");
	});

	/**
	 * Contract: deleting displayed rows reports their immediate surviving
	 * neighbours without exposing the grid's internal row collection.
	 * Setup: delete two adjacent rows from a five-row loaded result.
	 * Pass: the event contains the original neighbouring ids and the rows are
	 * removed from the display model.
	 */
	it("reports neighbours when displayed rows are deleted", async() =>
	{
		const rows = Array.from({length: 5}, (_value, index) => ({id: `addressbook::row-${index}`, label: `Row ${index}`}));
		const grid = createDatagrid(rows);
		grid.setInitialRows(rows);
		grid.total = rows.length;
		let detail : any;
		grid.addEventListener("et2-rows-deleted", (event : Event) => detail = (event as CustomEvent).detail);

		await grid.refresh(["row-1", "row-2"], "delete");

		assert.deepEqual(detail, {
			rowIds: ["addressbook::row-1", "addressbook::row-2"],
			previousRowId: "addressbook::row-0",
			nextRowId: "addressbook::row-3"
		}, "delete event should preserve both adjacent row ids");
		assert.sameMembers(grid.rows.map((row) => row.id), [
			"addressbook::row-0", "addressbook::row-3", "addressbook::row-4"
		], "deleted rows should no longer be displayed");
	});

	/**
	 * Contract: deleting the last row(s) of a list has no surviving row after
	 * it, so callers relying on nextRowId (e.g. mail's delayed-arrow-key
	 * selection) can tell there is nothing to advance to.
	 * Setup: delete the last row from a five-row loaded result.
	 * Pass: nextRowId is null while previousRowId still points at the row
	 * above the deleted one.
	 */
	it("reports no nextRowId when the last row is deleted", async() =>
	{
		const rows = Array.from({length: 5}, (_value, index) => ({id: `addressbook::row-${index}`, label: `Row ${index}`}));
		const grid = createDatagrid(rows);
		grid.setInitialRows(rows);
		grid.total = rows.length;
		let detail : any;
		grid.addEventListener("et2-rows-deleted", (event : Event) => detail = (event as CustomEvent).detail);

		await grid.refresh(["row-4"], "delete");

		assert.deepEqual(detail, {
			rowIds: ["addressbook::row-4"],
			previousRowId: "addressbook::row-3",
			nextRowId: null
		}, "deleting the last row should report no next row to advance to");
		assert.sameMembers(grid.rows.map((row) => row.id), [
			"addressbook::row-0", "addressbook::row-1", "addressbook::row-2", "addressbook::row-3"
		], "deleted row should no longer be displayed");
	});

	/**
	 * Contract: deleting the currently selected/previewed row must notify listeners
	 * that the selection changed, not just silently drop it from internal state -
	 * otherwise an `onselect`-driven preview pane (e.g. mail's) never re-runs and
	 * keeps showing the just-deleted row's content.
	 *
	 * Setup: select one row, then delete it via `refresh(..., "delete")`.
	 *
	 * Pass: an `et2-selection-changed` event fires with the row excluded from
	 * `selectedRowIds`.
	 */
	it("emits a selection-changed event when the selected row is deleted", async() =>
	{
		const rows = Array.from({length: 3}, (_value, index) => ({id: `addressbook::row-${index}`, label: `Row ${index}`}));
		const grid = createDatagrid(rows);
		grid.setInitialRows(rows);
		grid.total = rows.length;
		grid.selectSingleRow("addressbook::row-1");

		let detail : any = null;
		grid.addEventListener("et2-selection-changed", (event : Event) => detail = (event as CustomEvent).detail);

		await grid.refresh(["row-1"], "delete");

		assert.isNotNull(detail, "deleting the selected row should emit et2-selection-changed");
		assert.notInclude(detail.selectedRowIds, "addressbook::row-1", "deleted row should no longer be selected");
	});

	/**
	 * Contract: deleting rows that are neither selected nor active must not emit a
	 * spurious selection-changed event - the selection genuinely didn't change.
	 *
	 * Setup: select row-0, delete the unrelated row-2.
	 *
	 * Pass: no `et2-selection-changed` event fires.
	 */
	it("does not emit selection-changed when an unselected row is deleted", async() =>
	{
		const rows = Array.from({length: 3}, (_value, index) => ({id: `addressbook::row-${index}`, label: `Row ${index}`}));
		const grid = createDatagrid(rows);
		grid.setInitialRows(rows);
		grid.total = rows.length;
		grid.selectSingleRow("addressbook::row-0");

		let fired = false;
		grid.addEventListener("et2-selection-changed", () => fired = true);

		await grid.refresh(["row-2"], "delete");

		assert.isFalse(fired, "deleting an unrelated row should not emit a selection-changed event");
	});

	/**
	 * Contract: on mobile, a touch swipe on a row toggles its selection (see
	 * Et2DatagridSwipeController), but the browser still fires its own
	 * trailing `click` for that same gesture afterward - that click must not
	 * replace the selection the swipe just produced. Found live via real
	 * mobile touch emulation: right after a swipe marked a row, the very
	 * next click silently un-marked/replaced it.
	 *
	 * Setup: select row-0 with a plain click (multiple mode), then swipe
	 * row-1 (touch pointerdown/pointerup past the swipe threshold), then
	 * fire the trailing click a real touchscreen still sends on the same row.
	 *
	 * Pass: both row-0 and row-1 remain selected after the trailing click -
	 * it must be suppressed rather than replace the selection with just row-1.
	 */
	it("keeps a swipe's selection when the browser fires a trailing click for the same gesture", () =>
	{
		const rows = Array.from({length: 3}, (_value, index) => ({id: `row-${index}`, label: `Row ${index}`}));
		const grid = createDatagrid(rows);
		grid.selectionMode = "multiple";
		grid.setInitialRows(rows);
		grid.total = rows.length;

		const table = document.createElement("table");
		const body = document.createElement("tbody");
		table.append(body);
		document.body.append(table);
		renderVirtualRow(grid, 0, body);
		const row1 = renderVirtualRow(grid, 1, body);

		(grid as any)._updateSelectionFromPointer("row-0", 0, new MouseEvent("click"));

		const withTarget = (event : Event, target : EventTarget) : Event =>
		{
			Object.defineProperty(event, "target", {value: target, configurable: true});
			return event;
		};

		(grid as any)._handleTablePointerDown(withTarget(
			new PointerEvent("pointerdown", {pointerId: 1, pointerType: "touch", clientX: 100, clientY: 20}), row1));
		(grid as any)._handleTablePointerUp(withTarget(
			new PointerEvent("pointerup", {pointerId: 1, pointerType: "touch", clientX: 10, clientY: 20}), row1));
		assert.sameMembers(Array.from((grid as any).selectedRowIds), ["row-0", "row-1"],
			"swipe should add row-1 to the existing selection");

		(grid as any)._handleTableClick(withTarget(new MouseEvent("click", {clientX: 10, clientY: 20}), row1));
		assert.sameMembers(Array.from((grid as any).selectedRowIds), ["row-0", "row-1"],
			"the browser's trailing click for the same gesture must not replace the selection");

		table.remove();
	});

	/**
	 * Contract: changing the selection must not change the row markup the grid renders.
	 *
	 * _renderVirtualRow() commits `unsafeHTML(_buildRowElement(...).outerHTML)`.  unsafeHTML
	 * tears down and rebuilds the row's DOM whenever that string changes, and a rebuilt row
	 * comes back unhydrated (`.loading`, widgets not upgraded yet) - so any selection state
	 * baked into it makes every affected row blank and re-hydrate.  With "select all" that is
	 * the entire visible list at once, which also changes the row heights under the
	 * virtualizer and jumps the scroll position.
	 *
	 * Setup: build one row's markup, then select every row and make that row the active one,
	 * rebuilding its markup after each change.
	 *
	 * Pass: every build produces identical markup, while the state itself is live - the mounted
	 * row, stamped by _syncRowAccessibilityState(), carries both aria-selected and dg-row-active.
	 */
	it("builds identical row markup before and after selection and active-row changes", () =>
	{
		const rows = Array.from({length: 5}, (_value, index) => ({id: `row-${index}`, label: `Row ${index}`}));
		const grid = createDatagrid(rows);
		grid.selectionMode = "multiple";
		grid.setInitialRows(rows);
		grid.total = rows.length;

		const baselineHtml = (grid as any)._buildRowElement((grid as any)._rowsByIndex[1], 1).outerHTML;

		grid.selectAllRows();
		assert.equal((grid as any)._buildRowElement((grid as any)._rowsByIndex[1], 1).outerHTML, baselineHtml,
			"select-all must not change the row markup, or unsafeHTML rebuilds every visible row");

		(grid as any)._moveActiveRow(1, false);
		assert.equal((grid as any).activeRowId, "row-1", "the row should now be the active one");
		assert.equal((grid as any)._buildRowElement((grid as any)._rowsByIndex[1], 1).outerHTML, baselineHtml,
			"becoming the active row must not change the row markup either");

		const table = document.createElement("table");
		const body = document.createElement("tbody");
		table.append(body);
		document.body.append(table);
		const mounted = renderVirtualRow(grid, 1, body);
		assert.equal(mounted?.getAttribute("aria-selected"), "true",
			"the mounted row must still show the selection the markup no longer carries");
		assert.isTrue(mounted?.classList.contains("dg-row-active"),
			"the mounted row must still show the active state the markup no longer carries");
		assert.equal(mounted?.tabIndex, 0, "the active row must still be the grid's tab stop");
		table.remove();
	});
});
