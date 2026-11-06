import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {Et2Historylog} from "../Et2Historylog";
import {ET2_NEXTMATCH_FILTER_EVENT} from "../../Et2Nextmatch/Headers/events";
import {restoreResizeObserverNoise, silenceResizeObserverNoise} from "./resizeObserverNoise";

import "../../Et2Select/Et2Select";
import "../../Et2Select/Select/Et2SelectAccount";
import "../../Et2Description/Et2Description";
import "../../Et2Date/Et2DateTime";
import "../../Et2Diff/Et2Diff";
import "../../Layout/Et2Box/Et2Box";
import "../../Et2Datagrid/Et2Datagrid";

/**
 * Contract under test:
 * - Filter plumbing: header/filterbox filter events merge into activeFilters and reload.
 * - Every request is scoped to the record, and nothing else is sorted or selected.
 * - The `columns` attribute hides columns without making them unavailable.
 * - The widget returns no value.
 * - The filter drawer is reachable by the name Et2Filterbox closes on Escape.
 *
 * Setup strategy:
 * - Render a bare et2-historylog with stubbed egw.  No template is loaded (that needs a server),
 *   so tests that need columns set them directly - the row template's own parsing is Et2RowProvider's
 *   contract, not this widget's.
 *
 * Pass criteria: documented per test.
 */

const egwStub = {
	lang: (label : string) => label,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	image: () => "",
	preference: () => null,
	set_preference: () => {},
	app_name: () => "infolog",
	link: (url : string) => url,
	debug: () => {},
	dataFetch: (_e, _r, _f, _w, callback) => callback({order: [], total: 0}),
	dataRegisterUID: (_uid, callback) => callback({}, "row::1"),
	dataUnregisterUID: () => {},
	dataStoreUID: () => {},
	accounts: () => Promise.resolve([]),
	accountData: () => {},
	accountInfo: () => null,
	lang_notranslate: (s : string) => s,
	// Et2Template checks this while loading; without it the (expected) template-load failure in
	// these tests throws a second, confusing error on top of the real one.
	debug_level: () => 0,
	// Et2VfsPath runs every value through these; a '~file~' history row renders through it
	decodePath: (p : string) => p,
	encodePath: (p : string) => p
};
window.egw = function() { return egwStub; } as any;
Object.assign(window.egw, egwStub);

const settle = async() =>
{
	for(let i = 0; i < 4; i++)
	{
		await Promise.resolve();
	}
};

async function historylog(value : Record<string, any> = {id: 42, app: "infolog", "status-widgets": {}})
{
	const el = new Et2Historylog();
	el.value = value;
	document.body.append(el);
	await el.updateComplete;
	await settle();
	return el;
}

describe("Et2Historylog", () =>
{
	// These tests connect a real et2-datagrid, whose virtualizer intermittently trips a benign
	// ResizeObserver-loop error that web-test-runner counts as a failure.
	before(silenceResizeObserverNoise);
	after(restoreResizeObserverNoise);

	let created : Et2Historylog[] = [];
	const track = (el : Et2Historylog) => { created.push(el); return el; };

	afterEach(() =>
	{
		created.forEach(el => el.remove());
		created = [];
		sinon.restore();
	});

	/**
	 * Every row request is scoped to one record of one app.  The server overrides both from its own
	 * content, but they still have to be sent: their presence is what tells
	 * HistoryLog::validate() this is a row request and not a form submit.
	 *
	 * Pass criteria: both are in activeFilters once set up.
	 */
	it("scopes its requests to the record", async() =>
	{
		const el = track(await historylog({id: 42, app: "infolog", "status-widgets": {}}));

		assert.equal(el.activeFilters.record_id, 42);
		assert.equal(el.activeFilters.appname, "infolog");
	});

	/**
	 * Pass criteria: a bubbling filter event (what a filterbox widget or a header filter sends)
	 * merges into the active filters.
	 */
	it("applies a filter event from a filter widget", async() =>
	{
		const el = track(await historylog());

		const source = document.createElement("div");
		el.append(source);
		source.dispatchEvent(new CustomEvent(ET2_NEXTMATCH_FILTER_EVENT, {
			bubbles: true,
			composed: true,
			cancelable: true,
			detail: {filters: {col_filter: {owner: "5"}}}
		}));
		await settle();

		assert.equal(el.activeFilters.col_filter.owner, "5");
	});

	/**
	 * Clearing a filter has to remove the key, not send an empty one: the server merges what it
	 * receives into its stored copy, so a key left holding "" would keep filtering.
	 *
	 * Pass criteria: empty string, null and empty array all delete the key.
	 */
	it("removes a col_filter key when its value is cleared", async() =>
	{
		const el = track(await historylog());

		el.applyFilters({col_filter: {owner: "5", status: ["E"]}});
		assert.equal(el.activeFilters.col_filter.owner, "5");

		el.applyFilters({col_filter: {owner: ""}});
		assert.notProperty(el.activeFilters.col_filter, "owner",
			"an empty value must delete the key, not blank it");

		el.applyFilters({col_filter: {status: []}});
		assert.notProperty(el.activeFilters.col_filter, "status",
			"an empty array must delete the key too");
	});

	/**
	 * Applying filters announces itself so a filterbox can sync its widgets back up.
	 *
	 * Pass criteria: an et2-filter event carrying the new state is dispatched.
	 */
	it("announces applied filters", async() =>
	{
		const el = track(await historylog());
		let seen : any = null;
		el.addEventListener("et2-filter", (e : Event) => { seen = (<CustomEvent>e).detail; });

		el.applyFilters({search: "needle"});

		assert.isNotNull(seen, "applying filters must dispatch et2-filter");
		assert.equal(seen.activeFilters.search, "needle");
	});

	/**
	 * A filter widget whose value setter re-fires "change" on a programmatic set would otherwise
	 * come straight back in here from our own event listener, before the first call returned.
	 * Et2Nextmatch needs the same guard; this is the same bug class.
	 *
	 * Pass criteria: a reentrant call is refused rather than recursing.
	 */
	it("refuses a reentrant applyFilters", async() =>
	{
		const el = track(await historylog());
		let reentrantResult : any = "not called";
		el.addEventListener("et2-filter", () =>
		{
			if(reentrantResult === "not called")
			{
				reentrantResult = el.applyFilters({search: "inner"});
			}
		});

		el.applyFilters({search: "outer"});

		assert.isFalse(reentrantResult, "the reentrant call must be refused");
		assert.equal(el.activeFilters.search, "outer", "the outer call's state must stand");
	});

	/**
	 * The sort order is fixed (newest first) and the server rejects a client-supplied one.  These
	 * exist because NextmatchInterface requires them, and because the data provider calls sortBy()
	 * when a response echoes an `order` - they must be harmless, not throw.
	 *
	 * Pass criteria: both are no-ops that report they did nothing.
	 */
	it("has no sorting", async() =>
	{
		const el = track(await historylog());

		assert.isFalse(el.sortBy("owner", true), "sortBy must be a no-op");
		assert.isFalse(el.resetSort(), "resetSort must be a no-op");
		assert.isUndefined(el.activeFilters.sort, "no sort must ever be sent");
		assert.isUndefined(el.activeFilters.order);
	});

	/**
	 * Pass criteria: the grid is configured with no selection and no initial cursor.
	 */
	it("configures the grid without selection", async() =>
	{
		const el = track(await historylog());
		const grid = el.shadowRoot!.querySelector("et2-datagrid")!;

		assert.equal(grid.getAttribute("selection-mode"), "none",
			"a history log has no actions, so nothing to select for");
		assert.equal(grid.getAttribute("auto-activate-first-row"), "false",
			"the keyboard cursor must appear only once a key is pressed");
	});

	/**
	 * The legacy `columns` attribute listed which columns to show.  Hiding rather than dropping
	 * them keeps them available in the datagrid's column chooser.
	 *
	 * Pass criteria: listed columns are visible, unlisted ones are hidden but still present.
	 */
	it("hides the columns its `columns` attribute leaves out", async() =>
	{
		const el = track(await historylog());
		el.columns = "user_ts,status";
		const applied = (<any>el)._applyColumnVisibility([
			{key: "user_ts", title: "Date"},
			{key: "owner", title: "User"},
			{key: "status", title: "Changed"},
			{key: "new_value", title: "New"}
		]);

		assert.equal(applied.length, 4, "no column may be dropped outright");
		assert.isNotTrue(applied.find(c => c.key === "user_ts").hidden);
		assert.isNotTrue(applied.find(c => c.key === "status").hidden);
		assert.isTrue(applied.find(c => c.key === "owner").hidden, "an unlisted column must be hidden");
		assert.isTrue(applied.find(c => c.key === "new_value").hidden);
	});

	/**
	 * Pass criteria: an empty `columns` shows everything rather than hiding everything.
	 */
	it("shows every column when `columns` is empty", async() =>
	{
		const el = track(await historylog());
		el.columns = "";
		const applied = (<any>el)._applyColumnVisibility([{key: "user_ts", title: "Date"}]);

		assert.isNotTrue(applied[0].hidden);
	});

	/**
	 * Et2Filterbox closes on Escape through `event.target.filtersDrawer.hide()`, so the drawer has
	 * to be reachable under exactly that name.
	 *
	 * Pass criteria: the getter returns the drawer element.
	 */
	it("exposes its filter drawer by the name Et2Filterbox looks for", async() =>
	{
		const el = track(await historylog());

		assert.isNotNull(el.filtersDrawer, "filtersDrawer must resolve");
		assert.equal(el.filtersDrawer.localName, "sl-drawer");
		assert.isFunction(el.filtersDrawer.hide, "and must be something Escape can hide");
	});

	/**
	 * Pass criteria: the filter button reflects whether anything is filtered, and does not count
	 * the record scoping as a filter - it is always set.
	 */
	it("shows the filter button as filled only when something is filtered", async() =>
	{
		const el = track(await historylog());

		assert.equal((<any>el)._filterInfo().icon, "filter-circle",
			"record_id/appname are always set and are not filters");

		el.applyFilters({search: "needle"});
		assert.equal((<any>el)._filterInfo().icon, "filter-circle-fill");

		el.applyFilters({search: ""});
		assert.equal((<any>el)._filterInfo().icon, "filter-circle",
			"clearing the filter must un-fill the icon");
	});

	/**
	 * The header renders from `_filterInfo()`, which reads `_filters` - a plain field, not a
	 * reactive property.  So `applyFilters()` has to ask for a render itself; without that the
	 * funnel icon never fills and the drawer's "Clear filters" action never appears, even though
	 * the filter is applied and the rows do change.  Found live.
	 *
	 * Pass criteria: the rendered header reflects the filter state after applying, not just
	 * `_filterInfo()` in isolation.
	 */
	it("re-renders the header when filters are applied", async() =>
	{
		const el = track(await historylog());

		const clearActions = () => el.shadowRoot!.querySelectorAll('sl-drawer [slot="header-actions"]').length;
		const iconName = () => el.shadowRoot!.querySelector('[part="header"] et2-button-icon')?.getAttribute("name");

		assert.equal(iconName(), "filter-circle", "no filters: the funnel is empty");
		assert.equal(clearActions(), 0, "no filters: nothing to clear");

		el.applyFilters({search: "needle"});
		await el.updateComplete;
		assert.equal(iconName(), "filter-circle-fill", "a filter must fill the funnel in the rendered header");
		assert.equal(clearActions(), 1, "a filter must offer 'Clear filters' in the drawer header");

		el.applyFilters({search: ""});
		await el.updateComplete;
		assert.equal(iconName(), "filter-circle", "clearing must empty the funnel again");
		assert.equal(clearActions(), 0);
	});

	/**
	 * Pass criteria: a col_filter nested under an otherwise-empty object does not count as active.
	 */
	it("does not treat an empty col_filter as a filter", async() =>
	{
		const el = track(await historylog());
		el.applyFilters({col_filter: {}});

		assert.equal((<any>el)._filterInfo().icon, "filter-circle");
	});

	/**
	 * The history log is a display widget: it must never appear in a submit.
	 *
	 * Pass criteria: never dirty, no value.
	 */
	it("returns no value", async() =>
	{
		const el = track(await historylog());

		assert.isFalse(el.isDirty());
		assert.isNull(el.getValue());
	});

	/**
	 * Naming the "Changed" column the same as the widget makes them collide - the legacy widget
	 * warned about this too.
	 *
	 * Pass criteria: a warning is logged.
	 */
	it("warns when status_id collides with its own id", async() =>
	{
		// Et2Widget.egw() hands back the window.egw callable, which Object.assign() above copied
		// these methods onto - so a spy has to replace the method there, not on egwStub.
		const debug = sinon.spy();
		const previous = (<any>window.egw).debug;
		(<any>window.egw).debug = debug;
		try
		{
			const el = new Et2Historylog();
			el.id = "history";
			el.statusId = "history";
			el.value = {id: 1, app: "infolog", "status-widgets": {}};
			track(el);
			document.body.append(el);
			await el.updateComplete;
			await settle();

			assert.isTrue(debug.calledWithMatch("warn"), "a collision must be warned about");
		}
		finally
		{
			(<any>window.egw).debug = previous;
		}
	});

	/**
	 * Pass criteria: with no entry there is nothing to fetch, and the widget must not ask.
	 */
	it("fetches nothing without a record id", async() =>
	{
		const dataFetch = sinon.spy(<any>window.egw, "dataFetch");
		const el = track(await historylog({app: "infolog", "status-widgets": {}}));
		await settle();

		assert.isFalse(dataFetch.called, "no id means nothing to show, and nothing to request");
		assert.isUndefined(el.activeFilters.record_id);
	});
});
