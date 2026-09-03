import {assert} from "@open-wc/testing";
import "./AddressbookAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way MailVcardMessage.test.ts does, before app.ts pulls it in transitively
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import {Et2Nextmatch} from "../../../api/js/etemplate/Et2Nextmatch/Et2Nextmatch";
import {etemplate2} from "../../../api/js/etemplate/etemplate2";
import * as sinon from "sinon";

/**
 * app.ts has to be loaded through its explicit source path - see MailVcardMessage.test.ts's
 * docblock for why. AddressbookApp itself is not exported - the module registers it as
 * `app.classes.addressbook` at module scope, which is what we read.
 */
const APP_SOURCE = '/addressbook/js/app.ts';

/**
 * Regression coverage for setState()'s "No filters" favourite branch (addressbook/js/app.ts).
 *
 * Live-browser testing found the grid getting stuck showing stale, still-filtered rows (or,
 * with a race against a just-started reload from a different filter change, permanently empty)
 * after picking a category and then immediately clicking the "No filters" favourite. The
 * col_filter/cat_id/search state itself cleared correctly, but no fetch for the cleared state
 * was ever dispatched.
 *
 * Root cause: this branch consolidates three async clearing steps (col_filter, grouped_view,
 * advanced_search) into exactly one reload-triggering call at the end - `nm.applyFilters({
 * advanced_search: false})`, chosen specifically so this collapses to a single reload instead
 * of three racing ones (see the block's own comment, and commit 40210e9886). But
 * `applyFilters()` skips its reload whenever nothing it's given actually changed, and
 * advanced_search is already false/unset on the overwhelmingly common "No filters" click - no
 * advanced search was ever opened. That final call was then a silent no-op: the single reload
 * this block exists to guarantee never happened, leaving the grid showing whatever query was
 * loaded before the click.
 *
 * Setup strategy:
 * - A real `Et2Nextmatch` (and the real `Et2Datagrid` it renders) drives the actual
 *   applyFilters()/reload() path - only `dataProvider.fetchPage()` is stubbed, branching on
 *   whether a category filter is active, so a real fetch/reload is the only way the grid's
 *   total/rows can change.
 * - `egw.request()` (the advanced-search-clear round trip) resolves through a manually-held
 *   gate rather than a fixed delay, per api/js/etemplate/Et2Datagrid/test/TestTimingNotes.md -
 *   this lets the test both prove the reload waits for that round trip *and* prove it still
 *   happens once the round trip settles.
 *
 * Pass criteria:
 * - No reload happens before the advanced-search-clear round trip resolves (the consolidation
 *   this block is for is still intact).
 * - Once it resolves, the grid ends up showing the cleared-filter query's real total/rows, not
 *   stuck on the stale category-filtered ones.
 */
describe('AddressbookApp.setState({}) "No filters" favourite', () =>
{
	let AddressbookApp : any;
	let app : any;
	let nm : Et2Nextmatch;
	let datagrid : any;
	let sandbox : sinon.SinonSandbox;
	let resolveAdvancedSearchClear : () => void;

	// A real reload's real layout occasionally trips the harmless "ResizeObserver loop
	// completed with undelivered notifications" warning, which the test runner otherwise
	// treats as an uncaught error/test failure - same suppression, same reason, as
	// Et2Nextmatch.filters.test.ts's "reloads the child datagrid with real rows/total" test.
	const isResizeObserverLoopMessage = (text : string) => text.includes("ResizeObserver loop completed with undelivered notifications");
	let resizeObserverErrorHandler : (event : ErrorEvent) => void;
	let originalWindowOnError : typeof window.onerror;

	before(async function()
	{
		this.timeout(15000);
		await import(APP_SOURCE);
		AddressbookApp = (<any>window).app.classes.addressbook;
	});

	beforeEach(async() =>
	{
		sandbox = sinon.createSandbox();

		resizeObserverErrorHandler = (event : ErrorEvent) =>
		{
			if(isResizeObserverLoopMessage(String(event?.message || "")))
			{
				event.preventDefault();
				event.stopImmediatePropagation?.();
			}
		};
		window.addEventListener("error", resizeObserverErrorHandler, true);
		originalWindowOnError = window.onerror;
		window.onerror = (message, source, lineno, colno, error) =>
		{
			const text = String(message || error?.message || "");
			if(isResizeObserverLoopMessage(text))
			{
				return true;
			}
			return typeof originalWindowOnError === "function"
				   ? originalWindowOnError.call(window, message, source, lineno, colno, error)
				   : false;
		};

		const egwStub : any = {
			lang: (label : string) => label,
			tooltipBind: () => {},
			tooltipUnbind: () => {},
			image: () => "",
			preference: () => null,
			set_preference: () => {},
			app_name: () => "addressbook",
			link: (url : string) => url,
			dataFetch: (_execId : any, _request : any, _filters : any, _widgetId : any, callback : any) => callback({order: [], total: 0}),
			dataRegisterUID: (_uid : any, callback : any) => callback({}, "row::1"),
			debug: () => {},
			// bare-global `egw.request(...)` used by app.ts's setState() to clear advanced
			// search server-side - held open until the test explicitly releases it, so the
			// test can assert nothing reloads before this round trip settles.
			request: (method : string) => method === 'addressbook.addressbook_ui.ajax_clear_advanced_search'
				? new Promise<void>((resolve) => { resolveAdvancedSearchClear = resolve; })
				: Promise.resolve()
		};
		const egwFn : any = function() { return egwStub; };
		Object.assign(egwFn, egwStub);
		(<any>window).egw = egwFn;
		(<any>window).framework = {setSidebox: () => {}, setWebsiteTitle: () => {}};

		nm = new Et2Nextmatch();
		document.body.append(nm);
		await nm.updateComplete;
		datagrid = nm.shadowRoot!.querySelector("et2-datagrid");

		// Stand-in server: a category filter narrows to 3 rows, clearing it shows all 10 -
		// mirrors the real repro (a "Test" category filtered subset vs. the full address book).
		datagrid.dataProvider.fetchPage = async() => (nm.activeFilters.cat_id
			? {
				total: 3,
				rows: [
					{id: "addressbook::30-a", title: "Cat A"},
					{id: "addressbook::30-b", title: "Cat B"},
					{id: "addressbook::30-c", title: "Cat C"}
				]
			}
			: {
				total: 10,
				rows: Array.from({length: 10}, (_, i) => ({id: `addressbook::all-${i}`, title: `All ${i}`}))
			});

		// setState() only needs addressbook-index's "nm" and "grouped_view" widgets.
		const groupedFake : any = {value: ""};
		sandbox.stub(etemplate2, "getById").callsFake(((id : string) => id === "addressbook-index"
			? {widgetContainer: {getWidgetById: (widgetId : string) => widgetId === "nm" ? nm : (widgetId === "grouped_view" ? groupedFake : null)}}
			: null) as any);

		// Stub out the (unrelated) grouped-view template switch - assigning a real
		// `nm.template` here would drive Et2Nextmatch's real template loader against a
		// template file that doesn't exist in this test environment.
		(nm as any).set_template = () => Promise.resolve();

		app = Object.create(AddressbookApp.prototype);
		Object.assign(app, {appname: "addressbook"});
		// EgwApp.getState() otherwise walks etemplate2.getByApplication() - irrelevant here,
		// and setState()'s "No filters" branch only checks it has neither app nor id.
		app.getState = () => ({});
	});

	afterEach(() =>
	{
		sandbox.restore();
		nm.remove();
		window.removeEventListener("error", resizeObserverErrorHandler, true);
		window.onerror = originalWindowOnError;
	});

	function waitForLoadingDone() : Promise<void>
	{
		return new Promise((resolve) => datagrid.addEventListener("et2-loading-done", () => resolve(), {once: true}));
	}

	it("reloads with the cleared filter's real rows/total even though advanced_search was already off", async() =>
	{
		const initialLoad = waitForLoadingDone();
		nm.applyFilters({cat_id: "30", col_filter: {cat_id: "30"}});
		await initialLoad;
		assert.equal(datagrid.total, 3, "sanity: category filter should have loaded its own rows first");

		const reloaded = waitForLoadingDone();
		app.setState({});

		// Give the synchronous/microtask parts of setState() a chance to run, then confirm
		// the consolidated reload is still waiting on the advanced-search-clear round trip.
		await Promise.resolve();
		await Promise.resolve();
		assert.equal(datagrid.total, 3, "must not reload before the advanced-search-clear round trip settles");

		resolveAdvancedSearchClear();
		await reloaded;
		await datagrid.updateComplete;

		assert.equal(nm.activeFilters.cat_id, "", "category filter should be cleared");
		assert.equal(datagrid.total, 10, "grid must show the cleared-filter query's real total, not stay stuck on the stale category-filtered one");
		assert.equal(datagrid.rows.length, 10, "grid must show the cleared-filter query's real rows");
	});
});
