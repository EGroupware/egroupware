import {assert} from "@open-wc/testing";
import {Et2Nextmatch} from "../Et2Nextmatch";
import {ET2_NEXTMATCH_FILTER_EVENT, ET2_NEXTMATCH_SORT_EVENT} from "../Headers/events";
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
		el.searchletter = false;
		document.body.append(el);
		await el.updateComplete;

		const letterSearch = el.shadowRoot?.querySelector(".nextmatch_lettersearch");
		assert.isNull(letterSearch, "lettersearch should not render by default");
		el.remove();
	});

	/**
	 * Contract under test:
	 * - Setting `searchletter` as a property mirrors into active filters before render.
	 *
	 * Setup strategy:
	 * - Render nextmatch with a property-provided search letter.
	 *
	 * Pass criteria:
	 * - `activeFilters.searchletter` matches the property value.
	 * - Letter-search controls render because an active letter is set.
	 */
	it("mirrors searchletter property into active filters before render", async() =>
	{
		const el = new Et2Nextmatch();
		el.searchletter = "M";
		document.body.append(el);
		await el.updateComplete;

		assert.equal(el.activeFilters.searchletter, "M", "property searchletter should be mirrored into filters");
		assert.isNotNull(el.shadowRoot?.querySelector(".nextmatch_lettersearch"), "active searchletter should render lettersearch");
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
		const el = new Et2Nextmatch();
		const settings = {
			actions: {archive: {}},
			action_var: "nm_action_id",
			placeholder_actions: "add,import_csv"
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

		assert.deepEqual(attrs.settings, settings, "settings should stay as the content object");
		assert.equal(el.settings.action_var, "nm_action_id", "settings property should receive the object action_var");
		assert.deepEqual(el.placeholderActions, ["add", "import_csv"], "legacy settings should still normalize other properties");

		el.settings = "[object Object]";
		assert.deepEqual(el.settings, {action_var: "action"}, "non-object settings assignments should fall back to defaults");
		getArrayMgr.restore();
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

	it("implements et2_IInput so submit value collection includes it", () =>
	{
		const el = new Et2Nextmatch();

		assert.isTrue(et2_implements_registry[et2_IInput](el as any), "Et2Nextmatch should satisfy et2_IInput structurally");
		assert.deepEqual(el.getValue(), el.value, "getValue should provide the submitted nextmatch value");
	});

});
