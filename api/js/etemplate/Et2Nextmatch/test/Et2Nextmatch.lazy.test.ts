import {assert, fixture, html} from "@open-wc/testing";
import * as sinon from "sinon";
import {Et2Nextmatch} from "../Et2Nextmatch";
import {Et2Datagrid} from "../../Et2Datagrid/Et2Datagrid";

/**
 * Contract under test:
 * - `lazy` holds off the *client-side fetch fallback* in `firstUpdated()` until the nextmatch
 *   is actually being displayed - not just until an ancestor `<et2-tab-panel>` becomes the
 *   active one. An app that deliberately ships an empty list (`'num_rows' => 0` server-side)
 *   and shows it on demand must be able to say so: admin's group list sits next to the
 *   accounts list behind a tree selection, hidden by `disabled`, with no tab panel anywhere,
 *   and used to fetch itself on every admin page load whether or not anyone opened it.
 * - The gate is `lazy`-only and fetch-only. Without `lazy` a hidden nextmatch still loads
 *   immediately (that is every list in a background app tab), and rows/total the server
 *   already sent still go straight into the grid, since there is nothing left to defer.
 *
 * Setup strategy: render a real `et2-nextmatch` inside a `display:none` wrapper and stub
 * `Et2Datagrid.reload()`/`setInitialRows()` on the prototype - the branch `firstUpdated()`
 * takes is the behaviour, actually fetching is not, and stubbing before the element exists
 * avoids racing the `await`s firstUpdated() does before it gets there. Visibility is then
 * flipped on the wrapper, the way an app toggling its own views does.
 *
 * Pass criteria: no reload while hidden *and* lazy; a reload as soon as it is shown; an
 * immediate reload in every other combination.
 */

const egwStub = {
	lang: (label : string) => label,
	image: () => "",
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	preference: () => null,
	set_preference: () => {},
	app_name: () => "admin",
	link: (url : string) => url,
	uid: () => "nm-lazy-test",
	debug: () => {}
};
window.egw = function() { return egwStub; } as any;
Object.assign(window.egw, egwStub);

/**
 * The IntersectionObserver `Et2LazyLoadController` watches with reports asynchronously even
 * for a synchronous style change, and `firstUpdated()` awaits the template before it gets to
 * the fetch - so "did it fetch" is only answerable after letting both settle.
 */
const settled = () => new Promise(resolve => setTimeout(resolve, 100));

describe("Et2Nextmatch lazy fetch gating", () =>
{
	let reload : sinon.SinonStub;
	let setInitialRows : sinon.SinonStub;

	beforeEach(() =>
	{
		reload = sinon.stub(Et2Datagrid.prototype, "reload").resolves(undefined as any);
		setInitialRows = sinon.stub(Et2Datagrid.prototype, "setInitialRows").returns(undefined as any);
	});

	afterEach(() =>
	{
		reload.restore();
		setInitialRows.restore();
	});

	/**
	 * A nextmatch inside a wrapper whose display we control, with no row template on purpose:
	 * that would send the widget looking for an .xet over the network, and none of this is
	 * about row rendering.
	 */
	async function createNextmatch(attributes : Partial<Et2Nextmatch> = {}, hidden = true)
	{
		const wrapper = await fixture<HTMLElement>(html`
            <div style="display:${hidden ? "none" : "block"}"></div>`);
		const nm = new Et2Nextmatch();
		Object.assign(nm, attributes);
		wrapper.append(nm);
		await nm.updateComplete;
		await settled();
		return {wrapper, nm};
	}

	it("does not fetch while hidden", async() =>
	{
		await createNextmatch({lazy: true});

		assert.isFalse(reload.called, "a hidden lazy nextmatch must not ask the server for rows");
	});

	it("fetches once it is shown", async() =>
	{
		const {wrapper} = await createNextmatch({lazy: true});
		assert.isFalse(reload.called, "test setup: should still be waiting");

		wrapper.style.display = "block";
		await settled();

		assert.isTrue(reload.called, "showing the nextmatch should trigger the deferred fetch");
	});

	it("fetches immediately when it is displayed from the start", async() =>
	{
		await createNextmatch({lazy: true}, false);

		assert.isTrue(reload.called, "lazy only defers while hidden, it is not a delay");
	});

	it("fetches immediately when hidden but not lazy", async() =>
	{
		await createNextmatch({lazy: false});

		assert.isTrue(reload.called, "without lazy, being hidden must not change anything");
	});

	/**
	 * `lazy` gates the fetch fallback only. A server that already ran get_rows and sent a
	 * total takes the preloaded-rows branch, which has nothing to wait for - which is also
	 * why an app using `lazy` has to suppress that server-side prefetch as well.
	 */
	it("still applies rows the server already sent, hidden or not", async() =>
	{
		const {nm} = await createNextmatch({lazy: true, settings: <any>{total: 0}});

		assert.isTrue(setInitialRows.called, "server-sent rows/total go straight into the grid");
		assert.isFalse(reload.called, "and no redundant client fetch on top of them");
		assert.isTrue(nm.lazy, "test setup: lazy was actually set");
	});
});
