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
