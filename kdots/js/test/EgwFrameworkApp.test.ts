import {assert, fixture, html} from '@open-wc/testing';
import * as sinon from "sinon";

import '../EgwFrameworkApp';
import {EgwFrameworkApp} from '../EgwFrameworkApp';
import * as egwGlobal from "../../../api/js/jsapi/egw_global";
// egw_global.js hands out the egw it saw when it was first imported.  Its .d.ts only
// declares globals, so the export has to be reached without types.
const egw = (<any>egwGlobal).egw;
import "../../../api/js/etemplate/Et2Filterbox/Et2Filterbox";
import {Et2Filterbox} from "../../../api/js/etemplate/Et2Filterbox/Et2Filterbox";

/**
 * An app always lives inside a framework: it registers tab listeners on it while connecting and
 * waits on its getEgwComplete() before admitting an update is done.  A stand-in element is enough
 * for that, and avoids dragging a whole EgwFramework (with its own egw expectations, singleton
 * global-framework handover and initial-loader removal) into every app test.
 */
function frameworkStandIn() : HTMLElement
{
	const framework = document.createElement("egw-framework");
	(<any>framework).getEgwComplete = () => Promise.resolve();
	return framework;
}

/**
 * EgwFrameworkApp reaches egw two ways: window.egw of the moment, and the binding egw_global.js
 * captured when it was first imported.  Those are not the same object under the test runner, so
 * both have to be stubbed.  Callable, because widgets get their own instance via egw(appname).
 */
function stubEgw() : any
{
	const egwStub : any = function() { return egwStub; };
	Object.assign(egwStub, {
		window: window,
		lang: sinon.stub().callsFake(t => t),
		debug: sinon.stub(),
		debug_level: sinon.stub().returns(0),
		preference: sinon.stub().resolves(""),
		set_preference: sinon.stub(),
		link_get_registry: sinon.stub().returns(null),
		image: sinon.stub().returns(""),
		user: sinon.stub().returns({preferences: {}}),
		tooltipBind: sinon.stub(),
		tooltipUnbind: sinon.stub()
	});
	(<any>window).egw = egwStub;
	Object.assign(egw, egwStub);
	(<any>window).app = (<any>window).app || {};
	return egwStub;
}

describe('EgwFrameworkApp', () =>
{
	let element : EgwFrameworkApp;
	let sandbox : sinon.SinonSandbox;

	beforeEach(async() =>
	{
		sandbox = sinon.createSandbox();
		stubEgw();

		const framework = await fixture(frameworkStandIn());
		framework.innerHTML = `<egw-app name="test-app" url="https://test.app" title="Test App"></egw-app>`;
		element = <EgwFrameworkApp>framework.querySelector("egw-app");
		await element.updateComplete;
	});

	afterEach(() =>
	{
		sandbox.restore();
	});

	it('renders with default properties', () =>
	{
		assert.equal(element.name, 'test-app');
		assert.equal(element.url, 'https://test.app');
		assert.equal(element.title, 'Test App');
		assert.deepEqual(element.features, {});
	});

	it('handles active state changes', async() =>
	{
		element.setAttribute('active', '');
		await element.updateComplete;

		assert.isTrue(element.hasAttribute('active'));
	});
});

/**
 * Contract under test:
 * - `handleFilterChange()` re-renders the header when filters change, so the filter button icon
 *   switches between "filter-circle" (no filters) and "filter-circle-fill" (filters set) even
 *   though `rowCount` - the only reactive property the header used to depend on - never changes.
 * - Both event flavours count: "change" from the filterbox itself, and "et2-filter" bubbling up
 *   from a nextmatch (favourite, nextmatch header widget, ...).
 * - A "change" from any other input in the application is ignored, since "change" bubbles up from
 *   every input below us.
 * - `filterInfo()` examines a copy: the filter values it is handed must come back untouched.
 *
 * Setup strategy:
 * - `getNextmatch` is stubbed and a `slot="filter"` child added - the filter button only renders
 *   with both.  They go in after the first update, since `load()` empties the light DOM.
 * - The filterbox's `value` getter is shadowed, so a "filter" can be set and cleared without any
 *   nextmatch data or server round trip.
 * - The icon is read back out of the rendered `et2-button-icon`, ie. what the user actually sees,
 *   rather than out of the return value of `filterInfo()`.
 *
 * Pass criteria:
 * - `rowCount` stays at its initial value throughout, so any icon change proves the handler (and
 *   not an incidental row-count update) is what triggered the re-render.
 * - Ignored events must leave both the icon and `requestUpdate()` alone.
 */
describe("EgwFrameworkApp filter indicator", () =>
{
	let element : EgwFrameworkApp;
	let filterbox : Et2Filterbox;
	let filterValues : { [id : string] : any };
	let sandbox : sinon.SinonSandbox;

	const filterIcon = () => element.shadowRoot.querySelector(".egw_fw_app__filter_info_tooltip et2-button-icon")
		?.getAttribute("name");

	// handleFilterChange() renders once immediately, and again once the filterbox has caught up
	const settled = async() =>
	{
		await element.updateComplete;
		await filterbox.updateComplete;
		await element.updateComplete;
	};

	beforeEach(async() =>
	{
		sandbox = sinon.createSandbox();
		filterValues = {};
		stubEgw();

		const framework = await fixture(frameworkStandIn());
		framework.innerHTML = `<egw-app name="test-app"></egw-app>`;
		element = <EgwFrameworkApp>framework.querySelector("egw-app");
		await element.updateComplete;

		// load() empties the light DOM on the first update, so children go in afterwards
		element.getNextmatch = () => <any>{id: "nm"};
		const customFilter = document.createElement("div");
		customFilter.slot = "filter";
		element.append(customFilter);

		filterbox = <Et2Filterbox>document.createElement("et2-filterbox");
		Object.defineProperty(filterbox, "value", {get: () => filterValues, configurable: true});
		element.append(filterbox);
		await settled();
	});

	afterEach(() =>
	{
		sandbox.restore();
	});

	it("shows an empty filter icon when no filters are set", () =>
	{
		assert.equal(filterIcon(), "filter-circle", "no filters set");
	});

	it("fills the filter icon on a filterbox change, without a row count change", async() =>
	{
		const rowCount = element.rowCount;

		filterValues = {cat_id: "42"};
		filterbox.dispatchEvent(new Event("change", {bubbles: true}));
		await settled();

		assert.equal(filterIcon(), "filter-circle-fill", "filters set");
		assert.equal(element.rowCount, rowCount, "row count must not be what triggered the update");
	});

	it("empties the filter icon again when the filters are cleared", async() =>
	{
		filterValues = {cat_id: "42"};
		filterbox.dispatchEvent(new Event("change", {bubbles: true}));
		await settled();
		assert.equal(filterIcon(), "filter-circle-fill", "filters set");

		filterValues = {};
		filterbox.dispatchEvent(new Event("change", {bubbles: true}));
		await settled();

		assert.equal(filterIcon(), "filter-circle", "filters cleared");
	});

	it("fills the filter icon on et2-filter from a nextmatch", async() =>
	{
		const nmNode = document.createElement("div");
		nmNode.classList.add("et2_nextmatch");
		element.append(nmNode);

		filterValues = {cat_id: "42"};
		nmNode.dispatchEvent(new CustomEvent("et2-filter", {bubbles: true}));
		await settled();

		assert.equal(filterIcon(), "filter-circle-fill", "filters set by nextmatch");
	});

	it("ignores change events from other inputs", async() =>
	{
		const other = document.createElement("input");
		element.append(other);
		await settled();

		const requestUpdate = sandbox.spy(element, "requestUpdate");

		// Filters "changed" behind our back - an ignored event must not pick that up
		filterValues = {cat_id: "42"};
		other.dispatchEvent(new Event("change", {bubbles: true}));
		await settled();

		assert.isFalse(requestUpdate.called, "change from an unrelated input must not re-render");
		assert.equal(filterIcon(), "filter-circle", "icon must not change");
	});

	it("does not modify the filter values it is given", () =>
	{
		const values = {sort: {id: "cat_id", asc: true}, search_type: "rag", cat_id: ""};

		const info = element.filterInfo(values);

		assert.deepEqual(values, {sort: {id: "cat_id", asc: true}, search_type: "rag", cat_id: ""},
			"caller's values untouched");
		assert.equal(info.icon, "filter-circle", "sort & search type alone are not filters");
	});
});
