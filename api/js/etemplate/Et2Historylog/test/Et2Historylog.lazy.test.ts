import {assert, fixture, html} from "@open-wc/testing";
import * as sinon from "sinon";
import {Et2Historylog} from "../Et2Historylog";
import {Et2RowProvider} from "../../Et2Datagrid/Et2RowProvider";
import {restoreResizeObserverNoise, silenceResizeObserverNoise} from "./resizeObserverNoise";

import "../../Et2Datagrid/Et2Datagrid";

/**
 * Contract under test:
 * - `lazy` (on by default) holds off *everything* the widget would load - its row template as
 *   well as the entries - until it is actually being displayed. An entry's history is one of the
 *   more expensive things to fetch and a history tab is rarely the one the user opens first.
 * - "Being displayed" is not "on the active tab panel": a dialog that builds its whole widget
 *   tree up front and reveals a section later hides this just as well as an unopened tab does,
 *   and the widget must defer for both. An earlier version listened for the enclosing
 *   `<et2-tabbox>`'s `sl-tab-show` and so covered only tabs.
 * - Switching `lazy` off means load now, hidden or not.
 *
 * Setup strategy: render a real `et2-historylog` inside a `display:none` wrapper - a tab panel
 * is only one way of producing that, and needs a whole tabbox to reproduce - and stub
 * `Et2RowProvider.fromTemplate()`, the first thing `firstUpdated()` does once it stops waiting.
 * That is both the observable signal and what keeps the row template off the network.
 *
 * Pass criteria: nothing loaded while hidden; loaded as soon as it is shown; loaded immediately
 * in every non-lazy or already-displayed case.
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
	debug_level: () => 0,
	dataFetch: (_e, _r, _f, _w, callback) => callback({order: [], total: 0}),
	dataRegisterUID: () => {},
	dataUnregisterUID: () => {},
	dataStoreUID: () => {},
	accounts: () => Promise.resolve([]),
	lang_notranslate: (s : string) => s
};
window.egw = function() { return egwStub; } as any;
Object.assign(window.egw, egwStub);

/**
 * The IntersectionObserver behind `lazy` reports asynchronously even for a synchronous style
 * change, and firstUpdated() is async either way - so "did it load" is only answerable after
 * letting both settle.
 */
const settled = () => new Promise(resolve => setTimeout(resolve, 100));

describe("Et2Historylog lazy loading", () =>
{
	// A real et2-datagrid's virtualizer intermittently trips a benign ResizeObserver-loop error
	// that web-test-runner counts as a failure.
	before(silenceResizeObserverNoise);
	after(restoreResizeObserverNoise);

	let fromTemplate : sinon.SinonStub;

	beforeEach(() =>
	{
		fromTemplate = sinon.stub(Et2RowProvider.prototype, "fromTemplate").resolves({columns: []} as any);
	});

	afterEach(() => sinon.restore());

	async function createHistorylog(lazy : boolean | null = null, hidden = true)
	{
		const wrapper = await fixture<HTMLElement>(html`
            <div style="display:${hidden ? "none" : "block"}"></div>`);
		const el = new Et2Historylog();
		el.value = {id: 42, app: "infolog", "status-widgets": {}};
		if(lazy !== null)
		{
			el.lazy = lazy;
		}
		wrapper.append(el);
		await el.updateComplete;
		await settled();
		return {wrapper, el};
	}

	it("loads nothing at all while hidden", async() =>
	{
		const {el} = await createHistorylog();

		assert.isTrue(el.lazy, "test setup: lazy is on by default");
		assert.isFalse(fromTemplate.called, "not even the row template should be read while nobody can see it");
	});

	it("loads once it is shown, without any tab involved", async() =>
	{
		const {wrapper} = await createHistorylog();
		assert.isFalse(fromTemplate.called, "test setup: should still be waiting");

		wrapper.style.display = "block";
		await settled();

		assert.isTrue(fromTemplate.called, "being displayed is what it was waiting for, tab or not");
	});

	it("loads immediately when displayed from the start", async() =>
	{
		await createHistorylog(null, false);

		assert.isTrue(fromTemplate.called, "lazy only defers while hidden, it is not a delay");
	});

	it("loads immediately when hidden but lazy is off", async() =>
	{
		await createHistorylog(false);

		assert.isTrue(fromTemplate.called, "lazy=false means load now, visible or not");
	});
});
