import {assert} from "@open-wc/testing";
import {render} from "lit";
import {Et2Nextmatch} from "../Et2Nextmatch";
import {ET2_NEXTMATCH_FILTER_EVENT, ET2_NEXTMATCH_SORT_EVENT, Et2NextmatchSortEventDetail} from "../Headers/events";
import {et2_IInput, et2_implements_registry} from "../../et2_core_interfaces";
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
	image: () => "",
	preference: (key? : string) => String(key || "").endsWith("-lettersearch") ? true : null,
	set_preference: () => {},
	app_name: () => "addressbook",
	link: (url : string) => url,
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

const waitForCondition = async(condition : () => boolean) =>
{
	for(let i = 0; i < 10 && !condition(); i++)
	{
		await waitForBubblingHandlers();
	}
};

const sortableMode = (sortHeader : HTMLElement) =>
{
	const label = sortHeader.shadowRoot!.querySelector(".nextmatch_sortheader") as HTMLElement | null;
	return label?.classList.contains("asc") ? "asc" :
	       label?.classList.contains("desc") ? "desc" :
	       "none";
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

	it("exposes active filters without allowing direct mutation", () =>
	{
		const el = new Et2Nextmatch();
		el.applyFilters({
			search: "term",
			col_filter: {owner: "42"},
			sort: {id: "title", asc: true}
		}, {reload: false});

		const filters = el.activeFilters;
		filters.search = "changed";
		filters.col_filter.owner = "99";
		filters.sort.asc = false;

		assert.equal(el.activeFilters.search, "term", "top-level active filter mutation should not affect internal state");
		assert.equal(el.activeFilters.col_filter.owner, "42", "nested column filter mutation should not affect internal state");
		assert.deepEqual(el.activeFilters.sort, {id: "title", asc: true}, "nested sort mutation should not affect internal state");
	});

	it("can update filter state without reloading or clearing row actions", async() =>
	{
		const el = new Et2Nextmatch();
		document.body.append(el);
		await el.updateComplete;
		const datagrid = el.shadowRoot!.querySelector("et2-datagrid") as any;
		const reload = sinon.spy(datagrid, "reload");
		const clearRowActionObjects = sinon.spy();
		(el as any)._actionController.clearRowActionObjects = clearRowActionObjects;

		const changed = el.applyFilters({view: "tile"}, {reload: false, clearActions: false});

		assert.isTrue(changed, "filter state should update");
		assert.equal(el.activeFilters.view, "tile", "view should be stored in active filters");
		assert.isFalse(reload.called, "reload should be skipped");
		assert.isFalse(clearRowActionObjects.called, "row actions should be preserved");
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
	 * - Sort headers cycle through unsorted, ascending, descending, and back to
	 *   unsorted.
	 *
	 * Setup strategy:
	 * - Click a standalone sort header, applying each reflected mode between
	 *   clicks the same way Nextmatch does after filter state changes.
	 *
	 * Pass criteria:
	 * - The emitted sort detail requests asc, then desc, then clear.
	 */
	it("emits a clear sort event after ascending and descending states", async() =>
	{
		const sortHeader = document.createElement("et2-nextmatch-sortheader") as any;
		sortHeader.id = "title";
		document.body.append(sortHeader);
		await sortHeader.updateComplete;
		const sortEvents : Et2NextmatchSortEventDetail[] = [];
		sortHeader.addEventListener(ET2_NEXTMATCH_SORT_EVENT, (event : CustomEvent<Et2NextmatchSortEventDetail>) =>
		{
			event.preventDefault();
			sortEvents.push({...event.detail});
		});

		sortHeader.click();
		sortHeader.setSortmode("asc");
		sortHeader.click();
		sortHeader.setSortmode("desc");
		sortHeader.click();

		assert.deepInclude(sortEvents[0], {id: "title", asc: true}, "first click should request ascending sort");
		assert.deepInclude(sortEvents[1], {id: "title", asc: false}, "second click should request descending sort");
		assert.equal(sortEvents[2].id, "title", "third click should still identify the column");
		assert.isTrue(sortEvents[2].clear, "third click should request clearing the sort");
		assert.isUndefined(sortEvents[2].asc, "clear event should not include a sort direction");
		sortHeader.remove();
	});

	/**
	 * Contract under test:
	 * - Sort headers with a descending default still cycle through all three
	 *   states.
	 *
	 * Setup strategy:
	 * - Click a standalone sort header with `sortmode=DESC`, applying each
	 *   reflected mode between clicks.
	 *
	 * Pass criteria:
	 * - The emitted sort detail requests desc, then asc, then clear.
	 */
	it("keeps the three-state cycle when the default sort direction is descending", async() =>
	{
		const sortHeader = document.createElement("et2-nextmatch-sortheader") as any;
		sortHeader.id = "modified";
		sortHeader.sortmode = "DESC";
		document.body.append(sortHeader);
		await sortHeader.updateComplete;
		const sortEvents : Et2NextmatchSortEventDetail[] = [];
		sortHeader.addEventListener(ET2_NEXTMATCH_SORT_EVENT, (event : CustomEvent<Et2NextmatchSortEventDetail>) =>
		{
			event.preventDefault();
			sortEvents.push({...event.detail});
		});

		sortHeader.click();
		sortHeader.setSortmode("desc");
		sortHeader.click();
		sortHeader.setSortmode("asc");
		sortHeader.click();

		assert.deepInclude(sortEvents[0], {id: "modified", asc: false}, "first click should request descending sort");
		assert.deepInclude(sortEvents[1], {id: "modified", asc: true}, "second click should request ascending sort");
		assert.isTrue(sortEvents[2].clear, "third click should request clearing the sort");
		assert.isUndefined(sortEvents[2].asc, "clear event should not include a sort direction");
		sortHeader.remove();
	});

	/**
	 * Contract under test:
	 * - Nextmatch honors explicit clear-sort events from sortable headers.
	 *
	 * Setup strategy:
	 * - Seed an active sort and dispatch a header sort event with `clear`.
	 *
	 * Pass criteria:
	 * - `activeFilters.sort` is cleared.
	 */
	it("clears sort state when sort event requests clear", async() =>
	{
		const el = new Et2Nextmatch();
		el.applyFilters({sort: {id: "title", asc: false}}, {reload: false});
		document.body.append(el);
		await el.updateComplete;

		const eventSource = document.createElement("div");
		el.append(eventSource);
		eventSource.dispatchEvent(new CustomEvent(ET2_NEXTMATCH_SORT_EVENT, {
			bubbles: true,
			composed: true,
			cancelable: true,
			detail: {id: "title", clear: true}
		}));
		await waitForBubblingHandlers();

		assert.isUndefined(el.activeFilters.sort, "sort state should be cleared");
		el.remove();
	});

	/**
	 * Contract under test:
	 * - Et2Nextmatch reflects a single active sort into sortheaders rendered by
	 *   the internal datagrid shadow DOM.
	 *
	 * Setup strategy:
	 * - Render Et2Nextmatch and manually place two sortable headers in the
	 *   datagrid shadow root, matching where column headers are rendered.
	 * - Set one active sort and call the internal reflection method.
	 *
	 * Pass criteria:
	 * - Matching header is active.
	 * - Non-matching sibling header is cleared to `none`.
	 */
	it("clears inactive datagrid sort headers when reflecting nextmatch sort state", async() =>
	{
		const el = new Et2Nextmatch();
		document.body.append(el);
		await el.updateComplete;

		const datagrid = el.shadowRoot!.querySelector("et2-datagrid") as HTMLElement & { shadowRoot : ShadowRoot };
		assert.isNotNull(datagrid, "nextmatch should render datagrid");
		const nameHeader = document.createElement("et2-nextmatch-sortheader") as HTMLElement;
		nameHeader.setAttribute("id", "name");
		const dateHeader = document.createElement("et2-nextmatch-sortheader") as HTMLElement;
		dateHeader.setAttribute("id", "date");
		const customfieldsHeader = document.createElement("et2-nextmatch-header-customfields") as any;
		customfieldsHeader.customfields = {
			cf_text: {label: "Text", type: "text"}
		};
		customfieldsHeader.fields = {
			cf_text: true
		};
		datagrid.shadowRoot!.append(nameHeader, dateHeader, customfieldsHeader);
		await customfieldsHeader.updateComplete;
		const customfieldSortHeader = customfieldsHeader.querySelector("et2-nextmatch-sortheader") as HTMLElement | null;
		assert.isNotNull(customfieldSortHeader, "customfield sort header should render in light DOM");
		await (nameHeader as any).updateComplete;
		await (dateHeader as any).updateComplete;
		await (customfieldSortHeader as any).updateComplete;

		(nameHeader as any).setSortmode("desc");
		(dateHeader as any).setSortmode("asc");
		(customfieldSortHeader as any).setSortmode("desc");
		await (nameHeader as any).updateComplete;
		await (dateHeader as any).updateComplete;
		await (customfieldSortHeader as any).updateComplete;

		(el as any)._filters.sort = {id: "date", asc: true};
		(el as any)._updateSortHeaderState();
		await (nameHeader as any).updateComplete;
		await (dateHeader as any).updateComplete;
		await (customfieldSortHeader as any).updateComplete;

		assert.equal(sortableMode(dateHeader), "asc", "active datagrid sort header should reflect current sort");
		assert.equal(sortableMode(nameHeader), "none", "inactive datagrid sort header should be cleared");
		assert.equal(sortableMode(customfieldSortHeader!), "none", "inactive customfield sort header should be cleared");
		el.remove();
	});

	/**
	 * Contract under test:
	 * - Initial sort settings are reflected into active filters and
	 *   sortable header state before the first load.
	 *
	 * Setup strategy:
	 * - Create nextmatch with `settings.order` and `settings.sort`.
	 * - Add matching and non-matching sortable headers to the datagrid shadow DOM
	 *   before `firstUpdated()` initializes settings sort.
	 *
	 * Pass criteria:
	 * - `activeFilters.sort` matches the configured default.
	 * - The matching header shows the configured sort direction.
	 */
	it("reflects initial settings sort into sortable headers", async() =>
	{
		const el = new Et2Nextmatch();
		el.settings = {
			order: "date",
			sort: "DESC"
		};
		const nameHeader = document.createElement("et2-nextmatch-sortheader") as HTMLElement;
		nameHeader.setAttribute("id", "name");
		const dateHeader = document.createElement("et2-nextmatch-sortheader") as HTMLElement;
		dateHeader.setAttribute("id", "date");
		const applySlotsStub = sinon.stub(el as any, "_applyTemplateFromSlots").callsFake(async() =>
		{
			const datagrid = el.shadowRoot!.querySelector("et2-datagrid") as HTMLElement & { shadowRoot : ShadowRoot };
			assert.isNotNull(datagrid, "nextmatch should render datagrid");
			datagrid.shadowRoot!.append(nameHeader, dateHeader);
			await (nameHeader as any).updateComplete;
			await (dateHeader as any).updateComplete;
		});

		document.body.append(el);
		await el.updateComplete;
		await waitForCondition(() => !!el.activeFilters.sort);
		await (nameHeader as any).updateComplete;
		await (dateHeader as any).updateComplete;

		assert.deepEqual(el.activeFilters.sort, {id: "date", asc: false}, "initial settings sort should become active");
		assert.equal(sortableMode(dateHeader), "desc", "matching header should show initial descending sort");
		assert.equal(sortableMode(nameHeader), "none", "non-matching header should remain unsorted");
		applySlotsStub.restore();
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

	/**
	 * Contract under test:
	 * - Enabling `lettersearch` renders letter controls that update the searchletter filter.
	 *
	 * Setup strategy:
	 * - Create nextmatch with `lettersearch=true`.
	 * - Click one rendered letter button.
	 *
	 * Pass criteria:
	 * - `activeFilters.searchletter` matches clicked letter.
	 */
	it("renders lettersearch and applies selected letter filter", async() =>
	{
		const el = new Et2Nextmatch();
		el.lettersearch = true;
		document.body.append(el);
		await el.updateComplete;

		const letterButton = el.shadowRoot?.querySelector(".nextmatch_lettersearch .lettersearch") as HTMLButtonElement | null;
		assert.isNotNull(letterButton, "lettersearch should render when enabled");
		const chosenLetter = letterButton?.textContent?.trim() || "A";
		letterButton?.click();
		await waitForBubblingHandlers();

		assert.equal(el.activeFilters.searchletter, chosenLetter, "clicking a letter should apply searchletter filter");
		el.remove();
	});

	/**
	 * Contract under test:
	 * - Letter-search controls stay hidden when neither `lettersearch` nor active `searchletter` is set.
	 *
	 * Setup strategy:
	 * - Render nextmatch with default letter-search settings.
	 *
	 * Pass criteria:
	 * - No `.nextmatch_lettersearch` element is rendered.
	 */
	it("does not render lettersearch when disabled and no active letter is set", async() =>
	{
		const el = new Et2Nextmatch();
		el.lettersearch = false;
		document.body.append(el);
		await el.updateComplete;

		const letterSearch = el.shadowRoot?.querySelector(".nextmatch_lettersearch");
		assert.isNull(letterSearch, "lettersearch should not render by default");
		el.remove();
	});

	/**
	 * Contract under test:
	 * - Settings-provided `searchletter` is stored as an active filter, not as a
	 *   retained setting.
	 *
	 * Setup strategy:
	 * - Render nextmatch with a settings-provided search letter.
	 *
	 * Pass criteria:
	 * - `activeFilters.searchletter` matches the settings value.
	 * - `settings.searchletter` is not retained as a setting.
	 * - Letter-search controls render because an active letter is set.
	 */
	it("moves settings searchletter into active filters before render", async() =>
	{
		const el = new Et2Nextmatch();
		el.settings = {searchletter: "M"};
		document.body.append(el);
		await el.updateComplete;

		assert.equal(el.activeFilters.searchletter, "M", "settings searchletter should be moved into filters");
		assert.isUndefined(el.settings.searchletter, "searchletter should not be retained in settings");
		assert.isNotNull(el.shadowRoot?.querySelector(".nextmatch_lettersearch"), "active searchletter should render lettersearch");
		el.remove();
	});

	/**
	 * Contract under test:
	 * - Letter search participates in column selection as a pseudo-column.
	 * - Hiding that pseudo-column clears the active search letter and removes the
	 *   pseudo id before real column ordering is applied.
	 *
	 * Setup strategy:
	 * - Render nextmatch with `lettersearch=true` and an active search letter.
	 * - Dispatch the datagrid column-selection extension events directly.
	 *
	 * Pass criteria:
	 * - The chooser item list gains `~search_letter~`.
	 * - Applying a selection without that id clears `searchletter`.
	 */
	it("adds lettersearch to column selection and clears it when hidden", async() =>
	{
		const el = new Et2Nextmatch();
		el.lettersearch = true;
		el.applyFilters({searchletter: "M"}, {reload: false});
		document.body.append(el);
		await el.updateComplete;

		const datagrid = el.shadowRoot?.querySelector("et2-datagrid") as HTMLElement | null;
		assert.isNotNull(datagrid, "nextmatch should render a datagrid");

		const columns : any[] = [];
		datagrid!.dispatchEvent(new CustomEvent("et2-column-selection-items", {
			detail: {columns},
			bubbles: true,
			composed: true
		}));
		assert.equal(columns[0]?.id, "~search_letter~", "lettersearch should be exposed as a chooser item");
		assert.equal(columns[0]?.caption, "Search letter", "chooser item should use the legacy caption");
		assert.isTrue(columns[0]?.visibility, "lettersearch chooser item should reflect current visibility");

		const selectedOrder = ["name", "~search_letter~"];
		datagrid!.dispatchEvent(new CustomEvent("et2-column-selection-apply", {
			detail: {selectedOrder},
			bubbles: true,
			composed: true
		}));
		assert.deepEqual(selectedOrder, ["name"], "lettersearch pseudo id should be removed before column ordering");

		const hiddenSelection = ["name"];
		datagrid!.dispatchEvent(new CustomEvent("et2-column-selection-apply", {
			detail: {selectedOrder: hiddenSelection},
			bubbles: true,
			composed: true
		}));
		await waitForBubblingHandlers();
		await el.updateComplete;

		assert.isFalse(el.activeFilters.searchletter, "hiding lettersearch should clear the active search letter");
		assert.isNull(el.shadowRoot?.querySelector(".nextmatch_lettersearch"), "hidden lettersearch should not render");
		el.remove();
	});

	/**
	 * Contract under test:
	 * - `placeholder` property is forwarded to datagrid empty-state text.
	 *
	 * Setup strategy:
	 * - Set `placeholder` on nextmatch and inspect child datagrid property.
	 *
	 * Pass criteria:
	 * - Datagrid `emptyStateText` equals configured placeholder text.
	 */
	it("maps placeholder text to datagrid empty state text property", async() =>
	{
		const el = new Et2Nextmatch();
		el.placeholder = "Nothing here yet";
		document.body.append(el);
		await el.updateComplete;

		const datagrid = el.shadowRoot?.querySelector("et2-datagrid") as any;
		assert.equal(datagrid?.emptyStateText, "Nothing here yet", "placeholder should be passed to datagrid empty-state text");
		el.remove();
	});

	/**
	 * Contract under test:
	 * - Nextmatch keeps the datagrid in configuration-loading state while an
	 *   initial named row template is still resolving.
	 *
	 * Setup strategy:
	 * - Configure a template name and hold the row-provider promise open during
	 *   first render.
	 *
	 * Pass criteria:
	 * - The child datagrid receives `configurationLoading=true`.
	 * - The missing-template warning is not logged before template resolution
	 *   completes.
	 */
	it("does not warn about missing row template while initial named template is loading", async() =>
	{
		const el = new Et2Nextmatch();
		el.template = "addressbook.index.rows";
		let resolveTemplate : (value : any) => void = () => {};
		const templatePromise = new Promise((resolve) =>
		{
			resolveTemplate = resolve;
		});
		const fromTemplate = sinon.stub((el as any)._rowProvider, "fromTemplate").returns(templatePromise);
		const debug = sinon.spy(egwStub, "debug");
		document.body.append(el);
		await el.updateComplete;

		const datagrid = el.shadowRoot?.querySelector("et2-datagrid") as any;
		await datagrid?.updateComplete;

		assert.isTrue(datagrid?.configurationLoading, "datagrid should stay in configuration-loading state");
		assert.isFalse(debug.getCalls().some((call) =>
				(call.args as any[])[0] === "warn" && (call.args as any[])[1] === "Et2Datagrid: No row template configured"),
			"missing-template warning should not be logged while template is loading");

		resolveTemplate(null);
		await Promise.resolve();
		debug.restore();
		fromTemplate.restore();
		el.remove();
	});

	/**
	 * Contract under test:
	 * - Right-click on placeholder area routes configured `placeholderActions`
	 *   to placeholder popup flow.
	 *
	 * Setup strategy:
	 * - Stub action controller popup methods.
	 * - Dispatch a contextmenu event from a `.dg-state` element.
	 *
	 * Pass criteria:
	 * - Placeholder popup is called once with configured action ids.
	 * - Event is prevented and row-popup path is not called.
	 */
	it("uses placeholderActions via action controller context popup on empty state", async() =>
	{
		const el = new Et2Nextmatch();
		el.placeholderActions = ["add", "import_csv"];
		document.body.append(el);
		await el.updateComplete;

		const triggerPlaceholderPopup = sinon.stub((el as any)._actionController, "triggerPlaceholderPopup").returns(true);
		const triggerPopupForRow = sinon.stub((el as any)._actionController, "triggerPopupForRow").returns(false);
		const state = document.createElement("div");
		state.className = "dg-state";
		el.append(state);
		const event = new MouseEvent("contextmenu", {bubbles: true, cancelable: true, composed: true});
		state.dispatchEvent(event);
		await waitForBubblingHandlers();

		assert.isTrue(triggerPlaceholderPopup.calledOnce, "placeholder popup should be attempted");
		assert.deepEqual(triggerPlaceholderPopup.firstCall.args[1], ["add", "import_csv"], "configured placeholderActions should be used");
		assert.isTrue(event.defaultPrevented, "context menu should be prevented when placeholder popup opens");
		assert.isFalse(triggerPopupForRow.called, "row popup should not run when placeholder popup handled event");

		triggerPlaceholderPopup.restore();
		triggerPopupForRow.restore();
		el.remove();
	});

	/**
	 * Contract under test:
	 * - The real datagrid default empty placeholder still routes context menus
	 *   through Nextmatch's placeholder action path.
	 *
	 * Setup strategy:
	 * - Render Nextmatch with columns but no rows, leaving Datagrid to provide
	 *   its built-in no-results placeholder.
	 * - Dispatch a composed `contextmenu` from the actual datagrid shadow DOM
	 *   placeholder.
	 *
	 * Pass criteria:
	 * - Placeholder popup is called with configured placeholder actions.
	 * - The event is prevented and the regular row popup path is not used.
	 */
	it("routes context menu from the datagrid default empty placeholder", async() =>
	{
		const el = new Et2Nextmatch();
		el.placeholderActions = ["add", "import_csv"];
		document.body.append(el);
		const triggerPlaceholderPopup = sinon.stub((el as any)._actionController, "triggerPlaceholderPopup").returns(true);
		const triggerPopupForRow = sinon.stub((el as any)._actionController, "triggerPopupForRow").returns(false);
		try
		{
			el.setColumns([{key: "name", title: "Name"} as any]);
			await el.updateComplete;
			const datagrid = el.shadowRoot?.querySelector("et2-datagrid") as any;
			await datagrid?.updateComplete;
			await waitForCondition(() => !!datagrid?.shadowRoot?.querySelector(".dg-empty-row"));
			const placeholder = datagrid?.shadowRoot?.querySelector(".dg-empty-row") as HTMLElement | null;

			assert.isNotNull(placeholder, "datagrid should render its built-in empty placeholder");
			const event = new MouseEvent("contextmenu", {bubbles: true, cancelable: true, composed: true});
			placeholder!.dispatchEvent(event);
			await waitForBubblingHandlers();

			assert.isTrue(triggerPlaceholderPopup.calledOnce, "placeholder popup should be attempted from rendered placeholder");
			assert.deepEqual(triggerPlaceholderPopup.firstCall.args[1], ["add", "import_csv"], "configured placeholderActions should be used");
			assert.isTrue(event.defaultPrevented, "context menu should be prevented when placeholder popup opens");
			assert.isFalse(triggerPopupForRow.called, "row popup should not run for the empty placeholder");
		}
		finally
		{
			triggerPlaceholderPopup.restore();
			triggerPopupForRow.restore();
			el.remove();
		}
	});

	/**
	 * Contract under test:
	 * - CSV string `placeholderActions` values are normalized to action-id arrays.
	 *
	 * Setup strategy:
	 * - Set `placeholderActions` to `"add,import_csv"`.
	 * - Dispatch contextmenu on `.dg-state` and inspect controller call args.
	 *
	 * Pass criteria:
	 * - Placeholder popup receives `["add", "import_csv"]`.
	 */
	it("splits placeholderActions CSV string when configured as string", async() =>
	{
		const el = new Et2Nextmatch();
		(el as any).placeholderActions = "add,import_csv";
		document.body.append(el);
		await el.updateComplete;

		const triggerPlaceholderPopup = sinon.stub((el as any)._actionController, "triggerPlaceholderPopup").returns(true);
		const triggerPopupForRow = sinon.stub((el as any)._actionController, "triggerPopupForRow").returns(false);
		const state = document.createElement("div");
		state.className = "dg-state";
		el.append(state);
		const event = new MouseEvent("contextmenu", {bubbles: true, cancelable: true, composed: true});
		state.dispatchEvent(event);
		await waitForBubblingHandlers();

		assert.isTrue(triggerPlaceholderPopup.calledOnce, "placeholder popup should be attempted");
		assert.deepEqual(triggerPlaceholderPopup.firstCall.args[1], ["add", "import_csv"], "CSV placeholderActions should split to array");
		triggerPlaceholderPopup.restore();
		triggerPopupForRow.restore();
		el.remove();
	});

	/**
	 * Contract under test:
	 * - Configured `extra_attributes` initialize into active filter payload state.
	 *
	 * Setup strategy:
	 * - Configure one extra attribute and matching property value on nextmatch.
	 *
	 * Pass criteria:
	 * - `activeFilters` contains that attribute with expected value.
	 */
	it("seeds extra_attributes into active filters for fetch payloads", async() =>
	{
		const el = new Et2Nextmatch();
		el.extraAttributes = ["selectedFolder"];
		(el as any).selectedFolder = "INBOX";
		document.body.append(el);
		await el.updateComplete;

		assert.equal(el.activeFilters.selectedFolder, "INBOX", "extra attribute should initialize in active filters");
		el.remove();
	});

	it("keeps settings as an object and ignores non-object settings attributes", () =>
	{
		/*
		 * Contract: legacy settings remain available for action/filter behaviour, but
		 * initial rows and actions are exposed through their own attrs so settings
		 * does not retain duplicate initialization payloads.
		 */
		const el = new Et2Nextmatch();
		const rows = [{id: "row-1", label: "Initial row"}];
		const settings = {
			actions: {archive: {}},
			action_var: "nm_action_id",
			col_filter: {owner: "5"},
			filter_aria_label: "Addressbook",
			home_dir: "/home/demo",
			not_a_nextmatch_setting: "pollution",
			placeholder_actions: "add,import_csv",
			searchletter: "M",
			rows
		};
		const contentMgr = {
			getEntry: () => settings,
			getPath: () => [],
			expandName: (value) => value,
			parseBoolExpression: (value) => value
		} as any;
		const modificationsMgr = {
			getPerspectiveData: () => ({owner: null}),
			getEntry: () => null
		} as any;
		const getArrayMgr = sinon.stub(el, "getArrayMgr");
		getArrayMgr.withArgs("content").returns(contentMgr);
		getArrayMgr.withArgs("modifications").returns(modificationsMgr);
		const attrs : any = {
			id: "nm",
			settings: "[object Object]"
		};

		el.transformAttributes(attrs);

		assert.deepEqual(attrs.rows, rows, "initial rows should still be exposed for datagrid seeding");
		assert.equal(attrs.home_dir, "/home/demo", "legacy content attributes should still be exposed separately");
		assert.notProperty(attrs.settings, "rows", "settings should not retain the initial row payload");
		assert.notProperty(attrs.settings, "col_filter", "settings should not retain active column filters");
		assert.notProperty(attrs.settings, "home_dir", "settings should not retain undocumented app attributes");
		assert.notProperty(attrs.settings, "not_a_nextmatch_setting", "settings should ignore unknown content keys");
		assert.notProperty(attrs.settings, "searchletter", "settings should not retain active letter-search state");
		assert.deepEqual(attrs.settings, {
			action_var: "nm_action_id",
			filter_aria_label: "Addressbook",
			placeholder_actions: "add,import_csv"
		}, "settings should keep non-initialization content settings");
		assert.deepEqual(el.activeFilters.col_filter, {owner: "5"}, "content col_filter should move into active filters");
		assert.equal(el.activeFilters.searchletter, "M", "content searchletter should move into active filters");
		assert.notProperty(el.settings, "rows", "settings property should not retain the initial row payload");
		assert.notProperty(el.settings, "col_filter", "settings property should not retain active column filters");
		assert.notProperty(el.settings, "searchletter", "settings property should not retain active letter-search state");
		assert.equal(el.settings.action_var, "nm_action_id", "settings property should receive the object action_var");
		assert.deepEqual(el.placeholderActions, ["add", "import_csv"], "legacy settings should still normalize other properties");

		el.settings = "[object Object]";
		assert.deepEqual(el.settings, {action_var: "action"}, "non-object settings assignments should fall back to defaults");
		getArrayMgr.restore();
	});

	it("moves explicit settings col_filter into active filters", () =>
	{
		const el = new Et2Nextmatch();

		el.settings = {
			action_var: "nm_action_id",
			col_filter: {owner: "5"},
			searchletter: "M"
		};

		assert.deepEqual(el.activeFilters.col_filter, {owner: "5"}, "settings col_filter should be moved into filters");
		assert.equal(el.activeFilters.searchletter, "M", "settings searchletter should be moved into filters");
		assert.deepEqual(el.settings, {action_var: "nm_action_id"}, "active filter state should not be retained as settings");
	});

	it("provides getValue input and deprecated set_columns compatibility", async() =>
	{
		const el = new Et2Nextmatch();
		(Et2Nextmatch as any)._deprecationWarnings?.clear?.();
		const warn = sinon.stub(console, "warn");
		el.setColumns([
			{key: "title", title: "Title"} as any,
			{key: "owner", title: "Owner"} as any
		]);
		el.applyFilters({
			search: "term",
			col_filter: {owner: "5"}
		}, {reload: false});

		const value = el.value;
		assert.deepEqual(value.selectcols, ["title", "owner"], "value getter should expose visible column keys");
		assert.equal(value.search, "term", "value getter should include active filter state");
		assert.equal(value.col_filter.owner, "5", "value getter should include column filter state");

		assert.deepEqual(el.getValue(), value, "getValue should return the submitted nextmatch value");
		assert.isFalse(warn.called, "getValue should not warn because it implements et2_IInput");

		el.set_columns(["owner"]);
		assert.deepEqual(
			el.value.selectcols,
			["owner"],
			"set_columns should only change visibility on already defined columns"
		);
		assert.isTrue(warn.calledOnce, "deprecated set_columns compatibility method should warn");

		warn.restore();
	});

	/**
	 * Contract under test:
	 * - `setColumns()` updates the live root datagrid, not only Nextmatch's
	 *   submit value/template cache.
	 *
	 * Setup strategy:
	 * - Render a real Et2Nextmatch so the child datagrid exists, then call
	 *   `setColumns()` with a changed visibility state.
	 *
	 * Pass criteria:
	 * - The child datagrid's current `columns` property reflects the hidden flag.
	 */
	it("applies setColumns changes to the rendered datagrid", async() =>
	{
		const el = new Et2Nextmatch();
		document.body.append(el);
		await el.updateComplete;
		const columnEvents : CustomEvent[] = [];
		el.addEventListener("et2-columns-changed", (event : CustomEvent) =>
		{
			columnEvents.push(event);
		});

		el.setColumns([
			{key: "title", title: "Title"} as any,
			{key: "owner", title: "Owner"} as any
		]);
		await el.updateComplete;

		const grid = el.shadowRoot!.querySelector("et2-datagrid") as any;
		assert.deepEqual(
			grid.columns.map((column) => ({key: column.key, hidden: column.hidden === true})),
			[
				{key: "title", hidden: false},
				{key: "owner", hidden: false}
			],
			"initial setColumns call should reach the rendered datagrid"
		);
		assert.equal(columnEvents.length, 1, "initial setColumns call should emit a column-change event");
		assert.deepEqual(
			columnEvents[0].detail.columns.map((column) => column.key),
			["title", "owner"],
			"setColumns event should expose the updated column list"
		);

		el.setColumns(["owner"]);
		await el.updateComplete;
		await grid.updateComplete;

		assert.deepEqual(
			grid.columns.map((column) => ({key: column.key, hidden: column.hidden === true})),
			[
				{key: "title", hidden: true},
				{key: "owner", hidden: false}
			],
			"string setColumns calls should update current datagrid column visibility"
		);
		assert.equal(columnEvents.length, 2, "string setColumns call should emit a column-change event");
		assert.deepEqual(
			columnEvents[1].detail.columns.map((column) => ({key: column.key, hidden: column.hidden === true})),
			[
				{key: "title", hidden: true},
				{key: "owner", hidden: false}
			],
			"string setColumns event should expose the updated column visibility"
		);

		el.remove();
	});

	it("implements et2_IInput so submit value collection includes it", () =>
	{
		const el = new Et2Nextmatch();

		assert.isTrue(et2_implements_registry[et2_IInput](el as any), "Et2Nextmatch should satisfy et2_IInput structurally");
		assert.deepEqual(el.getValue(), el.value, "getValue should provide the submitted nextmatch value");
	});

});

describe("Et2Nextmatch expandable child grid wiring", () =>
{
	/**
	 * Contract under test:
	 * - Nextmatch grids reserve enough leading metadata column width for row expanders.
	 *
	 * Setup strategy:
	 * - Render a minimal nextmatch and inspect the inner datagrid's explicit CSS variable.
	 *
	 * Pass criteria:
	 * - The normal 6px metadata indicator width is lifted to at least Shoelace large spacing.
	 */
	it("widens the meta column for row expanders", async() =>
	{
		const el = new Et2Nextmatch();
		document.body.append(el);
		await el.updateComplete;

		const grid = el.shadowRoot!.querySelector("et2-datagrid") as HTMLElement | null;
		assert.isNotNull(grid, "nextmatch should render an inner datagrid");
		assert.strictEqual(
			grid!.style.getPropertyValue("--meta-column-width"),
			"max(var(--sl-spacing-large), 6px)"
		);

		el.remove();
	});

	it("collapses expanded rows and forgets child-grid column snapshots", async() =>
	{
		const el = new Et2Nextmatch();
		document.body.append(el);
		await el.updateComplete;
		(el as any)._expandedRowIds = new Set(["addressbook::parent-1"]);
		(el as any)._subgridColumnSnapshots.set("addressbook::parent-1", {
			columns: [{key: "title", title: "Title"}]
		});
		const requestUpdate = sinon.spy(el, "requestUpdate");

		const changed = el.collapseExpandedRows();

		assert.isTrue(changed, "collapse should report changed state");
		assert.equal((el as any)._expandedRowIds.size, 0, "expanded row ids should be cleared");
		assert.equal((el as any)._subgridColumnSnapshots.size, 0, "child grid column snapshots should be cleared");
		assert.isTrue(requestUpdate.called, "collapsing expanded rows should schedule a render");

		requestUpdate.resetHistory();
		assert.isFalse(el.collapseExpandedRows(), "empty expansion state should be a no-op");
		assert.isFalse(requestUpdate.called, "no-op collapse should not schedule another render");
		el.remove();
	});

	/**
	 * Contract under test:
	 * - Expanded nextmatch child content uses the same row template/columns as the parent.
	 * - Child grids hide only their visible header and disable their column chooser.
	 *
	 * Setup strategy:
	 * - Call the private render hook directly with a minimal expanded-row context.
	 * - Render the returned template into a detached container.
	 *
	 * Pass criteria:
	 * - The rendered child datagrid receives the same templateData and columns objects.
	 * - The visible header is configured hidden while the child remains a normal datagrid.
	 */
		it("renders child grids with the same template data and no visible header", async() =>
		{
			const el = new Et2Nextmatch();
			const columns : any[] = [{key: "title", title: "Title"}];
		const fetchPage = sinon.stub().resolves({rows: [], total: 0});
		const createChildProvider = sinon.stub().returns({
			fetchPage,
			getQuerySignature: () => "child-query",
			getDataStorePrefix: () => "addressbook",
			normalizeRowId: (rowId : string | number) => String(rowId ?? ""),
			toProviderRowId: (rowId : string) => rowId,
			refresh: async() => ({rows: [], removedRowIds: []})
		});
		const templateData = {
			rowTemplateId: "infolog.index.rows",
			rowTemplate: null,
			rowTemplateXml: null,
			rowTemplateAttrMap: {},
			loaderTemplate: null,
			columns
		};
		document.body.append(el);
		await el.updateComplete;
		(el as any)._templateData = templateData;
		(el as any)._templateLoading = false;
		(el as any)._dataProvider = {
			createChildProvider,
			toProviderRowId: (rowId : string) => rowId.replace(/^addressbook::/, ""),
			normalizeRowId: (rowId : string | number, ensurePrefix? : boolean) =>
			{
				const normalized = String(rowId ?? "");
				return ensurePrefix && !normalized.startsWith("addressbook::") ? `addressbook::${normalized}` : normalized;
			}
		};

		const container = document.createElement("div");
		document.body.append(container);
		render((el as any)._renderExpandedNextmatchGrid({
			row: {id: "addressbook::parent-1", data: {is_parent: true}},
			rowIndex: 0,
			parentGrid: document.createElement("et2-datagrid"),
			columnSizes: "120px",
			metaColumnWidth: "28px"
		}), container);
		await Promise.resolve();

		const childGrid = container.querySelector("et2-datagrid") as any;
		assert.isNotNull(childGrid, "expanded content should render a child datagrid");
		const reload = sinon.stub(childGrid, "reload").callsFake(async() =>
		{
			await fetchPage(0, childGrid.pageSize);
		});
		await childGrid.updateComplete;
		await Promise.resolve();
		assert.strictEqual(childGrid.templateData, templateData, "child grid should reuse parent template data");
		assert.notStrictEqual(childGrid.columns, columns, "child grid should not share the parent column array");
		assert.deepEqual(
			childGrid.columns.map((column) => ({key: column.key, title: column.title, width: column.width})),
			columns.map((column) => ({key: column.key, title: column.title, width: column.width})),
			"child grid should receive equivalent parent column descriptors"
		);
		childGrid.columns[0].width = "320px";
		assert.notEqual(columns[0].width, "320px", "child column width changes should not mutate parent column descriptors");
		assert.isTrue(childGrid.noVisibleHeader, "child grid should hide only its visible header");
		assert.isTrue(childGrid.noColumnSelection, "child grid should not expose independent column selection");
		assert.isTrue(childGrid.inheritColumnSizes, "child grid should inherit column track sizing from the parent grid");
		assert.isTrue(childGrid.embeddedVirtualized, "child grid should reserve virtualized height inside the parent scrollport");
		assert.isFalse(childGrid.hasAttribute("auto-height"), "child grid should not use simple auto-height for large child result sets");
		assert.isFalse(childGrid.noColumnPersistence, "child grid should rely on hidden headers for preference suppression");
		assert.isFalse(childGrid.noColumnResize, "child grid should rely on hidden headers for resize suppression");
		assert.isNull(
			childGrid.shadowRoot?.querySelector(".dg-col-resize-handle"),
			"child grid should not expose independent column resizing when its header is hidden"
		);
		assert.isFalse(childGrid.autoActivateFirstRow, "child grid should not create an active row simply by rendering");
		assert.strictEqual(
			childGrid.style.getPropertyValue("--column-sizes"),
			"",
			"child grid should not set its own column track string"
		);
		assert.strictEqual(childGrid.style.getPropertyValue("--meta-column-width"), "6px", "child grid should use a non-expander meta column width");
		assert.isTrue(createChildProvider.calledOnceWithExactly("addressbook::parent-1"), "child provider should be created for the parent row");
		assert.isTrue(reload.calledOnce, "child grid should be asked to reload when opened");
		assert.isTrue(fetchPage.calledOnceWithExactly(0, 50), "child grid should fetch its first page when opened");

		render(null, container);
		container.remove();
		el.remove();
	});

	it("uses root datagrid column changes for first expanded child grid render", async() =>
	{
		const el = new Et2Nextmatch();
		const originalColumns = [
			{key: "title", title: "Title", width: "100px"},
			{key: "date", title: "Date", width: "1fr"}
		];
		const resizedColumns = [
			{key: "title", title: "Title", width: "260px"},
			{key: "date", title: "Date", width: "1fr"}
		];
		const laterColumns = [
			{key: "title", title: "Title", width: "320px"},
			{key: "date", title: "Date", width: "1fr"}
		];
		const fetchPage = sinon.stub().resolves({rows: [], total: 0});
		const createChildProvider = sinon.stub().returns({
			fetchPage,
			getQuerySignature: () => "child-query",
			getDataStorePrefix: () => "addressbook",
			normalizeRowId: (rowId : string | number) => String(rowId ?? ""),
			toProviderRowId: (rowId : string) => rowId,
			refresh: async() => ({rows: [], removedRowIds: []})
		});
		const templateData = {
			rowTemplateId: "infolog.index.rows",
			rowTemplate: null,
			rowTemplateXml: null,
			rowTemplateAttrMap: {},
			loaderTemplate: null,
			columns: originalColumns
		};
		document.body.append(el);
		await el.updateComplete;
		(el as any)._templateData = templateData;
		(el as any)._templateLoading = false;
		(el as any)._dataProvider = {
			createChildProvider,
			toProviderRowId: (rowId : string) => rowId,
			normalizeRowId: (rowId : string | number) => String(rowId ?? "")
		};
		await el.requestUpdate();
		await el.updateComplete;

		const rootGrid = el.shadowRoot!.querySelector("et2-datagrid") as any;
		rootGrid.dispatchEvent(new CustomEvent("et2-columns-changed", {
			detail: {columns: resizedColumns},
			bubbles: true,
			composed: true
		}));
		await el.updateComplete;
		assert.strictEqual((el as any)._templateData, templateData, "root column sync must not replace templateData and trigger preference reload loops");

		const container = document.createElement("div");
		document.body.append(container);
		render((el as any)._renderExpandedNextmatchGrid({
			row: {id: "addressbook::parent-1", data: {is_parent: true}},
			rowIndex: 0,
			parentGrid: rootGrid,
			columnSizes: "260px 1fr",
			metaColumnWidth: "28px"
		}), container);
		await Promise.resolve();

		const childGrid = container.querySelector("et2-datagrid") as any;
		assert.deepEqual(
			childGrid.columns.map((column) => ({key: column.key, width: column.width})),
			resizedColumns.map((column) => ({key: column.key, width: column.width})),
			"first expanded child grid should use the root datagrid's current resized columns"
		);
		assert.strictEqual(
			childGrid.style.getPropertyValue("--column-sizes"),
			"",
			"first expanded child grid should inherit the root datagrid's current column track"
		);
		assert.isTrue(childGrid.inheritColumnSizes, "child grid should be configured to inherit column track sizing");
		assert.strictEqual(childGrid.style.getPropertyValue("--meta-column-width"), "6px", "child grid should not inherit the parent expander meta width");

		rootGrid.dispatchEvent(new CustomEvent("et2-columns-changed", {
			detail: {columns: laterColumns},
			bubbles: true,
			composed: true
		}));
		await el.updateComplete;
		render((el as any)._renderExpandedNextmatchGrid({
			row: {id: "addressbook::parent-1", data: {is_parent: true}},
			rowIndex: 0,
			parentGrid: rootGrid,
			columnSizes: "320px 1fr",
			metaColumnWidth: "28px"
		}), container);
		await Promise.resolve();

		const rerenderedChildGrid = container.querySelector("et2-datagrid") as any;
		assert.deepEqual(
			rerenderedChildGrid.columns.map((column) => ({key: column.key, width: column.width})),
			resizedColumns.map((column) => ({key: column.key, width: column.width})),
			"existing child grid should keep the column snapshot captured when it was created"
		);
		assert.strictEqual(
			rerenderedChildGrid.style.getPropertyValue("--column-sizes"),
			"",
			"existing child grid should continue inheriting the parent column track"
		);

		render(null, container);
		container.remove();
		el.remove();
	});

	/**
	 * Contract under test:
	 * - Nextmatch expansion treats settings.is_parent as the row-data key to evaluate.
	 * - settings.is_parent_value, when set, is compared to that row-data value.
	 * - The normalized row.is_parent boolean remains a fallback for providers that expose only normalized data.
	 */
	it("evaluates expandable rows from nextmatch hierarchy settings", () =>
	{
		const el = new Et2Nextmatch();
		el.settings = {is_parent: "group_count"};
		const config = (el as any)._datagridExpansionConfig();

		assert.isTrue(
			config.isExpandable({id: "group", data: {group_count: 2}}, 0),
			"truthy configured row field should make the row expandable"
		);
		assert.isFalse(
			config.isExpandable({id: "empty-group", data: {group_count: 0, is_parent: true}}, 1),
			"present but empty configured row field should take precedence over normalized fallback"
		);
		el.settings = {is_parent: "node_type", is_parent_value: "folder"};
		assert.isTrue(
			config.isExpandable({id: "folder", data: {node_type: "folder"}}, 2),
			"configured is_parent_value should allow matching rows"
		);
		assert.isFalse(
			config.isExpandable({id: "leaf", data: {node_type: "leaf"}}, 3),
			"configured is_parent_value should reject non-matching rows"
		);
		el.settings = {};
		assert.isTrue(
			config.isExpandable({id: "normalized-parent", data: {is_parent: true}}, 4),
			"normalized true should remain a fallback when no hierarchy field is configured"
		);
	});
});
