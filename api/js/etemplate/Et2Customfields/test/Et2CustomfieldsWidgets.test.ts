import {assert, fixture, html} from "@open-wc/testing";
import type {Et2CustomfieldsBase} from "../Et2CustomfieldsBase";

let openedLink : string | null = null;
const egwStub = {
	lang: (label : string) => label,
	link_app_list: () => ({}),
	link: (link : string) => link,
	open_link: (link : string) =>
	{
		openedLink = link;
	}
};
window.egw = function() { return egwStub; } as any;
Object.assign(window.egw, egwStub);

const customfields = {
	cf_text: {label: "Text", type: "text", type2: "task"},
	cf_select: {label: "Select", type: "select", type2: "project"},
	cf_private: {label: "Private", type: "select", type2: "0", private: "1"}
};

/**
 * Contract under test:
 * - New Et2Customfields webcomponents expose the same controller-driven visibility
 *   state that nextmatch header integration consumes.
 *
 * Setup strategy:
 * - Render each component variant and assign deterministic customfield metadata.
 *
 * Pass criteria:
 * - Visibility maps reflect field, tab, and filter inputs.
 * - Public visibility APIs return predictable field names/maps.
 */
describe("Et2Customfields webcomponents", () =>
{
	before(async function()
	{
		this.timeout(10000);
		await import("../Et2Customfields");
		await import("../Et2CustomfieldsList");
		await import("../Et2CustomfieldsFilters");
		await import("../../Et2Select/Et2Select");
		await import("../../Et2Select/SelectTypes");
		await import("../../Et2Textbox/Et2Textbox");
		await import("../../Et2Textbox/Et2TextboxReadonly");
		await import("../../Et2Textarea/Et2Textarea");
		await import("../../Et2Textarea/Et2TextareaReadonly");
	});

	it("resolves explicit field visibility for et2-customfields-list", async() =>
	{
		const element = await fixture<Et2CustomfieldsBase>(html`
			<et2-customfields-list></et2-customfields-list>
		`);
		element.customfields = customfields;
		element.fields = {cf_text: true, cf_select: false, cf_private: true};
		await element.updateComplete;
		assert.deepEqual(
			element.getCustomfieldVisibility(),
			{cf_text: true, cf_select: false, cf_private: true},
			"list widget should preserve explicit visibility map"
		);
	});

	/**
	 * Contract: the full list widget renders child field widgets in light DOM and
	 * keeps child widget instances stable when only row values change.
	 * Setup: render one visible text customfield, then replace value.
	 * Pass: the child widget is not in shadow DOM and the same instance displays
	 * the new value.
	 */
	it("renders list field widgets in light DOM and updates only row values", async() =>
	{
		const element = await fixture<Et2CustomfieldsBase>(html`
            <et2-customfields-list></et2-customfields-list>
		`);
		element.customfields = customfields;
		element.fields = {cf_text: true, cf_select: false, cf_private: false};
		element.value = {"#cf_text": "First row"};
		await element.updateComplete;

		const firstWidget = element.querySelector("[data-field='cf_text'] > *") as HTMLElement | null;
		await (firstWidget as any)?.updateComplete;
		assert.isNull(element.shadowRoot, "customfields list should render into light DOM");
		assert.isNotNull(firstWidget, "customfields list should create field widgets in its light DOM");
		assert.equal(firstWidget?.localName, "et2-textbox_ro", "list text customfields should use readonly textboxes");
		assert.include(firstWidget?.textContent || "", "First row", "field widget should display the current row value");

		element.value = {"#cf_text": "Second row"};
		await element.updateComplete;

		const secondWidget = element.querySelector("[data-field='cf_text'] > *") as HTMLElement | null;
		await (secondWidget as any)?.updateComplete;
		assert.strictEqual(secondWidget, firstWidget, "unchanged field definitions should keep the same widget instance");
		assert.include(secondWidget?.textContent || "", "Second row", "row value changes should update the existing widget");
	});

	/**
	 * Contract: select customfields display option labels, not raw stored values.
	 * Setup: render a select customfield with an option map and a stored #field
	 * value.
	 * Pass: the list creates the readonly select widget and its rendered text is
	 * the option label while the raw value is not shown.
	 */
	it("renders list select customfields as readonly labels", async() =>
	{
		const element = await fixture<Et2CustomfieldsBase>(html`
			<et2-customfields-list></et2-customfields-list>
		`);
		element.customfields = {
			cf_select: {label: "Select", type: "select", values: {open: "Open label", closed: "Closed label"}}
		};
		element.fields = {cf_select: true};
		element.value = {"#cf_select": "open"};
		await element.updateComplete;

		const widget = element.querySelector("[data-field='cf_select'] > *") as any;
		await widget?.updateComplete;

		assert.equal(widget?.localName, "et2-select_ro", "list select customfields should use readonly select widgets");
		assert.include(widget?.innerText || "", "Open label", "list select customfields should display option labels");
		assert.notInclude(widget?.innerText || "", "open", "list select customfields should not display raw stored values");
	});

	/**
	 * Contract: normal customfields lists keep labels supplied by widget mapping,
	 * while no-label list rendering suppresses those child widget labels.
	 * Setup: render the same fallback description customfield in normal and
	 * no-label list widgets.
	 * Pass: the normal widget receives the customfield label, and the no-label
	 * widget does not.
	 */
	it("can suppress child widget labels for row-style list rendering", async() =>
	{
		const normal = await fixture<Et2CustomfieldsBase>(html`
			<et2-customfields-list></et2-customfields-list>
		`);
		normal.customfields = {
			cf_unknown: {label: "Visible label", type: "not-registered"}
		};
		normal.fields = {cf_unknown: true};
		normal.value = {"#cf_unknown": "Value"};
		await normal.updateComplete;

		const normalWidget = normal.querySelector("[data-field='cf_unknown'] > *") as any;
		assert.equal(normalWidget?.label, "Visible label", "normal customfields lists should keep child labels");

		const rowStyle = await fixture<Et2CustomfieldsBase & {noLabel : boolean}>(html`
			<et2-customfields-list no-label></et2-customfields-list>
		`);
		rowStyle.customfields = normal.customfields;
		rowStyle.fields = {cf_unknown: true};
		rowStyle.value = {"#cf_unknown": "Value"};
		await rowStyle.updateComplete;

		const rowWidget = rowStyle.querySelector("[data-field='cf_unknown'] > *") as any;
		assert.equal(rowWidget?.label, "", "row-style customfields lists should suppress child labels");
	});

	it("wires readonly URL customfield widgets to their default action", async() =>
	{
		await import("../../Et2Url/Et2Url");
		await import("../../Et2Url/Et2UrlReadonly");

		openedLink = null;
		const element = await fixture<Et2CustomfieldsBase>(html`
			<et2-customfields-list no-label></et2-customfields-list>
		`);
		element.customfields = {
			cf_url: {label: "Website", type: "url"}
		};
		element.fields = {cf_url: true};
		element.value = {"#cf_url": "www.egroupware.org"};
		await element.updateComplete;

		const widget = element.querySelector("[data-field='cf_url'] > et2-url_ro") as HTMLElement | null;
		await (widget as any)?.updateComplete;

		assert.isFunction((widget as any)?.onclick, "readonly URL customfield should receive its default click action");
		widget?.dispatchEvent(new MouseEvent("click", {bubbles: true, composed: true}));
		assert.equal(openedLink, "http://www.egroupware.org", "clicking the readonly URL customfield should open the URL");
	});

	it("renders editable et2-customfields with mapped field widgets", async() =>
	{
		const element = await fixture<Et2CustomfieldsBase>(html`
			<et2-customfields></et2-customfields>
		`);
		element.customfields = {
			cf_text: {label: "Text", type: "text", rows: 1},
			cf_notes: {label: "Notes", type: "text", rows: 3}
		};
		element.fields = {cf_text: true, cf_notes: true};
		element.value = {"#cf_text": "Editable text", "#cf_notes": "Editable notes"};
		await element.updateComplete;

		assert.equal(
			element.querySelector("[data-field='cf_text'] > *")?.localName,
			"et2-textbox",
			"editable single-row text should use textbox"
		);
		assert.equal(
			element.querySelector("[data-field='cf_notes'] > *")?.localName,
			"et2-textarea",
			"editable multi-row text should use textarea"
		);
	});

	/**
	 * Contract: customfield metadata controls the field list; row values alone do not.
	 * Setup: assign only a row value and no customfield definitions.
	 * Pass: no visible field names or field DOM nodes are created.
	 */
	it("does not derive the field list from row values", async() =>
	{
		const element = await fixture<Et2CustomfieldsBase>(html`
            <et2-customfields-list></et2-customfields-list>
		`);
		element.value = {"#cf_text": "Row value without metadata"};
		await element.updateComplete;

		assert.deepEqual(
			element.getVisibleFieldNames(),
			[],
			"row values must not create customfield definitions; missing metadata is a setup problem"
		);
		assert.isNull(
			element.querySelector("[data-field='cf_text']"),
			"customfields list should remain empty until customfield metadata is supplied"
		);
	});

	it("defaults et2-customfields-filters to visible fields", async() =>
	{
		const element = await fixture<Et2CustomfieldsBase>(html`
			<et2-customfields-filters></et2-customfields-filters>
		`);
		element.customfields = customfields;
		await element.updateComplete;
		assert.deepEqual(
			element.getVisibleFieldNames(),
			["cf_text", "cf_select", "cf_private"],
			"filter widget should default all customfields visible"
		);
	});

	it("renders customfields filters as selectboxes and skips non-filter fields", async() =>
	{
		const element = await fixture<Et2CustomfieldsBase>(html`
			<et2-customfields-filters></et2-customfields-filters>
		`);
		element.customfields = {
			cf_text: {label: "Text", type: "text"},
			cf_select: {label: "Select", type: "select", values: {open: "Open", closed: "Closed"}},
			cf_file: {label: "File", type: "filemanager"}
		};
		await element.updateComplete;

		const select = element.querySelector("[data-field='cf_select'] > *") as any;
		assert.equal(select?.localName, "et2-select", "select customfield filters should render as selectboxes");
		assert.equal(select?.emptyLabel, "all", "filter selectbox should use the legacy empty label");
		assert.isTrue(select?.multiple, "filter selectbox should be multiple");
		assert.isNull(element.querySelector("[data-field='cf_text']"), "text customfields should not render as filters");
		assert.isNull(element.querySelector("[data-field='cf_file']"), "filemanager customfields should not render as filters");
	});

	it("supports type_filter previous across widget instances", async() =>
	{
		const first = await fixture<Et2CustomfieldsBase>(html`
			<et2-customfields type-filter="project"></et2-customfields>
		`);
		first.customfields = customfields;
		await first.updateComplete;

		const second = await fixture<Et2CustomfieldsBase>(html`
			<et2-customfields type-filter="previous"></et2-customfields>
		`);
		second.customfields = customfields;
		await second.updateComplete;

		assert.deepEqual(
			second.getCustomfieldVisibility(),
			{cf_text: false, cf_select: true, cf_private: true},
			"type_filter=previous should reuse last filter setting for new instances"
		);
	});
});
