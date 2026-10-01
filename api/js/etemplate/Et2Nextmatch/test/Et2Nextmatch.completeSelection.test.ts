import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {Et2Nextmatch} from "../Et2Nextmatch";
import {egw_getActionManager} from "../../../egw_action/egw_action";
import {Et2Dialog} from "../../Et2Dialog/Et2Dialog";

const egwStub = {
	lang: (label : string) => label,
	image: () => "",
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	preference: (_key? : string) => null,
	set_preference: () => {},
	app_name: () => "addressbook",
	link: (url : string) => url,
	uid: () => "nm-test-id",
	debug: () => {},
	dataGetUIDdata: () => null
};
window.egw = function() { return egwStub; } as any;
Object.assign(window.egw, egwStub);

/**
 * Contract under test: an action handler always gets every selected row as its senders, no
 * matter how much of the list the grid has rendered or fetched.
 *
 * Why: the grid only renders the rows in view, and action senders used to be just the rendered
 * rows. With "select all", or a shift range over rows the user scrolled past without them being
 * fetched (first row, jump to the end, shift+click the last), every handler working from its
 * senders silently left out all the other rows - live: an appointment for 219 selected contacts
 * got 3 participants.
 *
 * Setup strategy:
 * - Et2Nextmatch.executeWithCompleteSelection() with a stub datagrid and data provider standing
 *   in for a 5 row result of which only row 1 is loaded.
 * - EgwAction.execute() against a stub nextmatch, for the routing: which actions wait for the
 *   complete selection and which run straight away.
 *
 * Pass criteria:
 * - The handler gets all 5 rows. Missing ids are fetched once, page by page behind the
 *   "Loading, please wait" dialog; a handler asking the nextmatch for all ids itself, as older
 *   handlers do, gets the same ones without another fetch.
 * - Rows the grid has already fetched are used as they are: no request, no dialog.
 * - Cancelling the dialog runs nothing and requests no further page.
 * - Drag, single-row actions and complete selections run synchronously with their own senders.
 */
const ALL_IDS = ["1", "2", "3", "4", "5"];
const toRowId = (id : string) => `addressbook::${id}`;

function stubNextmatch(options : { all? : boolean, range? : { start : number, end : number } | null, ids? : string[], loaded? : string[] } = {})
{
	const el = new Et2Nextmatch();
	const fetches : number[] = [];
	const requests : [number, number][] = [];
	let selection = {ids: options.ids ?? [toRowId("1")], all: !!options.all};
	const datagrid = {
		pendingSelectionRange: options.range ?? null,
		completePendingSelectionRange: (ids : string[]) =>
		{
			selection = {ids: Array.from(new Set([...selection.ids, ...ids])), all: false};
			datagrid.pendingSelectionRange = null;
		},
		stopSelectionPrefetch: () => {},
		getLoadedRowIds: () => options.loaded ?? [toRowId("1")],
		total: ALL_IDS.length
	};
	Object.defineProperty(el, "_datagrid", {configurable: true, get: () => datagrid});
	const provider = {
		onFetch: null as null | ((start : number) => void),
		fetchPage: async(start : number, count : number) =>
		{
			fetches.push(start);
			requests.push([start, count]);
			provider.onFetch?.(start);
			return {
				rows: ALL_IDS.slice(start, start + count).map((id) => ({id: toRowId(id), data: {id}})),
				total: ALL_IDS.length
			};
		},
		toProviderRowId: (id : string) => id.replace(/^addressbook::/, ""),
		normalizeRowId: (id : string, prefix : boolean) => prefix && !id.startsWith("addressbook::") ? toRowId(id) : id
	};
	(el as any)._dataProvider = provider;
	el.getSelection = () => selection;
	return {el, fetches, requests, provider, datagrid};
}

describe("Et2Nextmatch complete selection for actions", () =>
{
	let dialogStub : sinon.SinonStub;
	let destroy : sinon.SinonSpy;

	beforeEach(() =>
	{
		destroy = sinon.spy();
		dialogStub = sinon.stub(Et2Dialog, "show_dialog").returns({destroy} as any);
	});

	afterEach(() =>
	{
		dialogStub.restore();
	});

	/** Press the wait dialog's Cancel button */
	const cancelDialog = () => dialogStub.firstCall.args[0]("cancel");

	it("runs the handler with every row when all rows are selected", async() =>
	{
		const {el, fetches} = stubNextmatch({all: true});
		let senders : any[] = [];
		let idsFromHandler : string[] = [];

		await el.executeWithCompleteSelection(async(s) =>
		{
			senders = s;
			// what older handlers (eg. addressbook's _fetchAllSelected()) still do themselves
			idsFromHandler = await el.fetchAllIds();
		});

		assert.deepEqual(senders.map((s) => s.id), ALL_IDS.map(toRowId), "every matching row must be a sender, not only the loaded one");
		assert.deepEqual(idsFromHandler, ALL_IDS, "a handler asking for all ids gets the collected ones");
		assert.deepEqual(fetches, [1], "the missing ids must be fetched only once, after the loaded first row");
		assert.isTrue(dialogStub.calledOnce, "the user is told why the action waits");
		assert.isTrue(destroy.calledOnce, "and the dialog goes away again");
	});

	it("uses the rows already fetched with select all, without a request or a wait dialog", async() =>
	{
		const {el, fetches} = stubNextmatch({all: true, loaded: ALL_IDS.map(toRowId)});
		let senders : any[] = [];

		await el.executeWithCompleteSelection((s) => senders = s);

		assert.lengthOf(senders, ALL_IDS.length, "every row is a sender");
		assert.deepEqual(fetches, [], "nothing must be fetched again");
		assert.isFalse(dialogStub.called, "nothing to wait for, so no wait dialog");
		assert.deepEqual(await el.fetchAllIds(), ALL_IDS, "fetchAllIds() itself also answers from the loaded rows");
		assert.deepEqual(fetches, [], "still nothing fetched");
	});

	it("fetches the missing ids of a pending shift range and completes the grid's selection", async() =>
	{
		const {el, fetches, datagrid} = stubNextmatch({range: {start: 0, end: 4}});
		const complete = sinon.spy(datagrid, "completePendingSelectionRange");
		const stopPrefetch = sinon.spy(datagrid, "stopSelectionPrefetch");
		let senders : any[] = [];

		await el.executeWithCompleteSelection((s) => senders = s);

		assert.isTrue(stopPrefetch.calledBefore(complete), "the grid's prefetch is stopped, so no page is requested twice");
		assert.deepEqual(fetches, [1], "the range's missing ids are fetched");
		assert.isTrue(dialogStub.calledOnce, "the user is told why the action waits");
		assert.deepEqual(complete.firstCall?.args[0], ALL_IDS.map(toRowId), "the grid's selection is completed, so it matches what the action got");
		assert.deepEqual(senders.map((s) => s.id), ALL_IDS.map(toRowId), "the handler gets the whole range");
	});

	/**
	 * The grid reports rows it has not fetched as holes in a sparse array (`_rowsByIndex.map()`),
	 * not as nulls - and every()/map() skip holes. Seen live: a range over 219 rows with a gap
	 * counted as "all loaded", fetched nothing and ran the action on the 119 loaded rows.
	 */
	it("does not mistake the holes of the grid's sparse loaded rows for loaded rows", async() =>
	{
		const sparse : string[] = [];
		sparse[0] = toRowId("1");
		sparse[4] = toRowId("5");
		for(const options of [{range: {start: 0, end: 4}}, {all: true}])
		{
			const {el, requests} = stubNextmatch({...options, loaded: sparse});
			let senders : any[] = [];

			await el.executeWithCompleteSelection((s) => senders = s);

			assert.deepEqual(requests, [[1, 3]], `${JSON.stringify(options)}: exactly the gap must be fetched`);
			assert.lengthOf(senders, ALL_IDS.length, `${JSON.stringify(options)}: every row is a sender`);
		}
	});

	/**
	 * Only the gaps between rows the grid has fetched are requested - not everything from row 0
	 * again, as fetchAllIds() used to - each gap a page at most.
	 */
	it("requests only the gaps between loaded rows, a page at most at a time", async() =>
	{
		const sparse : string[] = [];
		sparse[0] = toRowId("1");
		sparse[2] = toRowId("3");
		const {el, requests} = stubNextmatch({loaded: sparse});

		const ids = await el.fetchIdRange(0, 5, 1);

		assert.deepEqual(ids, ALL_IDS, "loaded and fetched ids in row order");
		assert.deepEqual(requests, [[1, 1], [3, 1], [4, 1]], "each gap requested on its own, split into pages");
	});

	it("does not run the handler and fetches no further page when the user cancels", async() =>
	{
		for(const options of [{range: {start: 0, end: 4}}, {all: true}])
		{
			dialogStub.resetHistory();
			const {el, provider} = stubNextmatch(options);
			// cancel while the first page is on its way
			provider.onFetch = () => cancelDialog();
			let ran = false;

			await el.executeWithCompleteSelection(() => ran = true);

			assert.isFalse(ran, `${JSON.stringify(options)}: a cancelled fetch must not run the action on a partial selection`);
		}

		// paging stops at the page in flight
		dialogStub.resetHistory();
		const {el, fetches, provider} = stubNextmatch({range: {start: 0, end: 4}});
		provider.onFetch = () => cancelDialog();
		let error : any;
		await el.fetchIdRange(0, 5, 2).catch((e) => error = e);
		assert.equal(error?.name, "AbortError");
		assert.deepEqual(fetches, [1], "no further page after cancel");
	});

	/**
	 * A context menu keeps the senders from when it was opened. Rows arriving later in a shift
	 * range join the selection after that, so its senders are only part of the selection
	 * although nothing is pending any more - seen live: "Copy to clipboard" stored 169 of 219.
	 */
	it("treats senders that are only part of the current selection as incomplete", () =>
	{
		const {el} = stubNextmatch({ids: [toRowId("1"), toRowId("2"), toRowId("3")]});

		assert.isTrue(el.selectionIncomplete([{id: toRowId("1")}]), "stale part of the selection");
		assert.isFalse(el.selectionIncomplete([1, 2, 3].map((id) => ({id: toRowId(String(id))}))), "senders that are the selection");
		assert.isFalse(el.selectionIncomplete([{id: toRowId("9")}]), "senders a handler chose itself, unrelated to the selection");
	});

	/**
	 * Built-in nextmatch actions (popup / location urls with `$id`, submit) take their ids from
	 * the selection, which with "select all" only knows the rendered rows - so `$id` held those
	 * only. They must use the collected senders instead.
	 */
	it("builds the ids of built-in nextmatch actions from the collected senders", () =>
	{
		const {el} = stubNextmatch({all: true});
		const controller = (el as any)._actionController;

		const ids = controller.normalizeSelection({ids: [toRowId("1")], all: true}, ALL_IDS.map((id) => ({id: toRowId(id)})));

		assert.deepEqual(ids.providerIds, ALL_IDS, "every collected row must be in the ids");
		assert.isTrue(ids.all, "select-all flag is kept for the server");
		assert.deepEqual(controller.normalizeSelection({ids: [toRowId("2")], all: false}, [{id: toRowId("9")}]).providerIds,
			["2"], "without select all the selection still decides");
	});

	describe("EgwAction.execute() routing", () =>
	{
		function createAction(type : string, id : string, allowOnMultiple : boolean | string, handler : Function)
		{
			const manager = egw_getActionManager("nm_complete_selection_" + id + "_" + Math.random(), true);
			return manager.addAction(type, id, id, "", handler, allowOnMultiple as any);
		}

		function stubOwner(incomplete : boolean)
		{
			const collected = [{id: "addressbook::1"}, {id: "addressbook::2"}, {id: "addressbook::3"}];
			return {
				collected,
				owner: {
					selectionIncomplete: () => incomplete,
					executeWithCompleteSelection: (run : Function) => Promise.resolve().then(() => run(collected))
				}
			};
		}

		it("hands the handler the complete selection when the rendered rows are not all of it", async() =>
		{
			const {owner, collected} = stubOwner(true);
			const handler = sinon.spy();
			const action = createAction("popup", "add_cal", true, handler);
			action.data = {nextmatch: owner};

			action.execute([{id: "addressbook::1"}]);
			await new Promise(resolve => setTimeout(resolve, 0));

			assert.isTrue(handler.calledOnce);
			assert.strictEqual(handler.firstCall.args[1], collected, "handler must get the collected senders");
		});

		it("finds the nextmatch on a parent action", async() =>
		{
			const {owner, collected} = stubOwner(true);
			const handler = sinon.spy();
			const parent = createAction("popup", "calendar", true, null);
			parent.data = {nextmatch: owner};
			const child = parent.addAction("popup", "calendar_add", "Add", "", handler, true);

			child.execute([{id: "addressbook::1"}]);
			await new Promise(resolve => setTimeout(resolve, 0));

			assert.strictEqual(handler.firstCall?.args[1], collected);
		});

		it("runs synchronously with its own senders when the selection is complete", () =>
		{
			const {owner} = stubOwner(false);
			const handler = sinon.spy();
			const action = createAction("popup", "edit", true, handler);
			action.data = {nextmatch: owner};
			const senders = [{id: "addressbook::1"}];

			action.execute(senders);

			assert.isTrue(handler.calledOnce, "no waiting for a complete selection");
			assert.strictEqual(handler.firstCall.args[1], senders);
		});

		it("does not collect for drag and single-row actions", () =>
		{
			const {owner} = stubOwner(true);
			for(const [type, allowOnMultiple] of [["drag", true], ["popup", false]] as [string, boolean][])
			{
				const handler = sinon.spy();
				const action = createAction(type, type + "_action", allowOnMultiple, handler);
				action.data = {nextmatch: owner};
				const senders = [{id: "addressbook::1"}];

				action.execute(senders);

				assert.isTrue(handler.calledOnce, `${type} (allowOnMultiple=${allowOnMultiple}) must run synchronously`);
				assert.strictEqual(handler.firstCall.args[1], senders);
			}
		});
	});
});
