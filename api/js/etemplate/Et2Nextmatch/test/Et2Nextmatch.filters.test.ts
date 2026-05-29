import {assert} from "@open-wc/testing";
import {Et2Nextmatch} from "../Et2Nextmatch";
import {ET2_NEXTMATCH_FILTER_EVENT, ET2_NEXTMATCH_SORT_EVENT} from "../Headers/events";
import * as sinon from "sinon";

/**
 * Contract under test:
 * - Header filter/sort events are handled by et2-nextmatch after bubbling completes.
 * - `preventDefault()` on those events cancels filter/sort application.
 *
 * Setup strategy:
 * - Render a minimal `et2-nextmatch` with stubbed `egw` methods.
 * - Dispatch cancelable custom events from a descendant node.
 * - Assert filter state changes on the component instance.
 *
 * Pass criteria:
 * - Non-canceled events update `activeFilters`.
 * - Canceled events leave `activeFilters` unchanged.
 *
 * Environment note:
 * - Uses microtask deferral, so each assertion waits one tick after dispatch.
 */

const egwStub = {
	lang: (label : string) => label,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	preference: () => null,
	set_preference: () => {},
	app_name: () => "addressbook",
	dataFetch: (_execId, _request, _filters, _widgetId, callback) => callback({order: [], total: 0}),
	dataRegisterUID: (_uid, callback) => callback({}, "row::1"),
	debug: () => {}
};
window.egw = function() { return egwStub; } as any;
Object.assign(window.egw, egwStub);

const waitForBubblingHandlers = async() =>
{
	await Promise.resolve();
	await Promise.resolve();
};

describe("Et2Nextmatch header event handling", () =>
{
	/**
	 * Contract under test:
	 * - Non-canceled header filter events merge into active filters.
	 *
	 * Setup strategy:
	 * - Dispatch bubbling/cancelable filter event from a descendant node.
	 *
	 * Pass criteria:
	 * - `activeFilters.col_filter` reflects dispatched filter payload.
	 */
	it("applies filter event when not canceled", async() =>
	{
		const el = new Et2Nextmatch();
		document.body.append(el);
		await el.updateComplete;

		const eventSource = document.createElement("div");
		el.append(eventSource);
		eventSource.dispatchEvent(new CustomEvent(ET2_NEXTMATCH_FILTER_EVENT, {
			bubbles: true,
			composed: true,
			cancelable: true,
			detail: {filters: {col_filter: {owner: "42"}}}
		}));
		await waitForBubblingHandlers();

		assert.equal(el.activeFilters.col_filter.owner, "42", "column filter should be applied");
		el.remove();
	});

	/**
	 * Contract under test:
	 * - Prevented header sort events must not apply sort state.
	 *
	 * Setup strategy:
	 * - Attach a one-time listener that calls `preventDefault()` on sort event.
	 *
	 * Pass criteria:
	 * - `activeFilters.sort` remains unset.
	 */
	it("skips sort application when event is prevented", async() =>
	{
		const el = new Et2Nextmatch();
		document.body.append(el);
		await el.updateComplete;

		const prevent = (event : Event) => event.preventDefault();
		document.addEventListener(ET2_NEXTMATCH_SORT_EVENT, prevent, {once: true});

		const eventSource = document.createElement("div");
		el.append(eventSource);
		eventSource.dispatchEvent(new CustomEvent(ET2_NEXTMATCH_SORT_EVENT, {
			bubbles: true,
			composed: true,
			cancelable: true,
			detail: {id: "title", asc: true}
		}));
		await waitForBubblingHandlers();

		assert.isUndefined(el.activeFilters.sort, "sort state should remain unset when canceled");
		el.remove();
	});

	/**
	 * Contract under test:
	 * - Prevented header filter events must not apply filter state.
	 *
	 * Setup strategy:
	 * - Attach a one-time listener that calls `preventDefault()` on filter event.
	 *
	 * Pass criteria:
	 * - Dispatched column filter does not appear in active filters.
	 */
	it("skips filter application when event is prevented", async() =>
	{
		const el = new Et2Nextmatch();
		document.body.append(el);
		await el.updateComplete;

		const prevent = (event : Event) => event.preventDefault();
		document.addEventListener(ET2_NEXTMATCH_FILTER_EVENT, prevent, {once: true});

		const eventSource = document.createElement("div");
		el.append(eventSource);
		eventSource.dispatchEvent(new CustomEvent(ET2_NEXTMATCH_FILTER_EVENT, {
			bubbles: true,
			composed: true,
			cancelable: true,
			detail: {filters: {col_filter: {owner: "42"}}}
		}));
		await waitForBubblingHandlers();

		assert.isUndefined(el.activeFilters.col_filter.owner, "filter state should remain unchanged when canceled");
		el.remove();
	});

	/**
	 * Contract under test:
	 * - Non-canceled header sort events update sort filter state.
	 *
	 * Setup strategy:
	 * - Dispatch bubbling/cancelable sort event from a descendant node.
	 *
	 * Pass criteria:
	 * - `activeFilters.sort` equals dispatched sort id/asc payload.
	 */
	it("applies sort state when sort event is not canceled", async() =>
	{
		const el = new Et2Nextmatch();
		document.body.append(el);
		await el.updateComplete;

		const eventSource = document.createElement("div");
		el.append(eventSource);
		eventSource.dispatchEvent(new CustomEvent(ET2_NEXTMATCH_SORT_EVENT, {
			bubbles: true,
			composed: true,
			cancelable: true,
			detail: {id: "title", asc: true}
		}));
		await waitForBubblingHandlers();

		assert.deepEqual(el.activeFilters.sort, {id: "title", asc: true}, "sort state should be applied");
		el.remove();
	});

	/**
	 * Contract under test:
	 * - Setting `filterTemplate` routes template content into the created filterbox.
	 *
	 * Setup strategy:
	 * - Stub `_ensureFilterbox()` with a fake filterbox implementing `setFilterTemplate`.
	 * - Assign template element to `filterTemplate` property.
	 *
	 * Pass criteria:
	 * - Filterbox is created and contains the assigned template node.
	 */
	it("applies filterTemplate through Et2Nextmatch property", async() =>
	{
		const el = new Et2Nextmatch();
		document.body.append(el);
		await el.updateComplete;
		const fakeFilterbox = document.createElement("div") as any;
		fakeFilterbox.setFilterTemplate = (template : HTMLElement | null) =>
		{
			fakeFilterbox.replaceChildren();
			if(template)
			{
				fakeFilterbox.append(template);
			}
		};
		const ensureFilterboxStub = sinon.stub(el as any, "_ensureFilterbox").returns(fakeFilterbox);

		const template = document.createElement("div") as any;
		template.id = "nm-filter-template";
		template.load = () => Promise.resolve();

		el.filterTemplate = template;
		await waitForBubblingHandlers();
		await waitForBubblingHandlers();

		const filterbox = fakeFilterbox as HTMLElement | null;
		assert.isNotNull(filterbox, "nextmatch should create a filterbox for filter template");
		assert.isNotNull(filterbox?.querySelector("#nm-filter-template"), "template should be attached in filterbox");

		ensureFilterboxStub.restore();
		filterbox?.remove();
		el.remove();
	});

});
