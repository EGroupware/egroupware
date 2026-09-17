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
	},
	// a widget with statustext binds a tooltip as soon as it connects
	tooltipBind: () => {},
	tooltipUnbind: () => {}
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
	 * Contract: a list shows values, not field names - the name goes on the surrounding row
	 * as a tooltip.  A URL is the documented exception, because a bare address says nothing
	 * on its own, and no-label row rendering drops even that.
	 * Setup: render a plain customfield and a URL customfield in normal and no-label lists.
	 * Pass: the plain field gets no label either way, the URL field gets the customfield
	 * label only in the normal list.
	 */
	it("shows values without field names, and suppresses even the URL label for rows", async() =>
	{
		await import("../../Et2Url/Et2Url");
		await import("../../Et2Url/Et2UrlReadonly");
		const customfields = {
			cf_unknown: {label: "Visible label", type: "not-registered"},
			cf_url: {label: "Homepage", type: "url"}
		};
		const normal = await fixture<Et2CustomfieldsBase>(html`
			<et2-customfields-list></et2-customfields-list>
		`);
		normal.customfields = customfields;
		normal.fields = {cf_unknown: true, cf_url: true};
		normal.value = {"#cf_unknown": "Value", "#cf_url": "https://example.org"};
		await normal.updateComplete;

		assert.notOk(
			(normal.querySelector("[data-field='cf_unknown'] > *") as any)?.label,
			"a list field should be shown without its name"
		);
		assert.equal(
			(normal.querySelector("[data-field='cf_url'] > *") as any)?.label,
			"Homepage",
			"a URL in a list should be shown by name rather than by address"
		);
		assert.notOk(normal.querySelector("label"), "the list itself should not render labels");

		const rowStyle = await fixture<Et2CustomfieldsBase & {noLabel : boolean}>(html`
			<et2-customfields-list no-label></et2-customfields-list>
		`);
		rowStyle.customfields = customfields;
		rowStyle.fields = {cf_unknown: true, cf_url: true};
		rowStyle.value = {"#cf_unknown": "Value", "#cf_url": "https://example.org"};
		await rowStyle.updateComplete;

		assert.equal(
			(rowStyle.querySelector("[data-field='cf_url'] > *") as any)?.label,
			"",
			"row-style customfields lists should suppress child labels"
		);
	});

	/**
	 * Contract: the visibility map survives transformAttributes().  That only passes a property
	 * through untouched when it is declared `type: Object`; an untyped one goes through
	 * `"" + value` and arrives as the string "[object Object]", which selects nothing.  A
	 * template giving a comma-separated list of names as a string still has to work.
	 * Setup: send a fields map and a fields string the way a template's modifications do.
	 * Pass: the map stays a map, the string still selects its named fields.
	 */
	it("keeps the visibility map an object through transformAttributes", async() =>
	{
		const element = await fixture<any>(html`
			<et2-customfields-list></et2-customfields-list>
		`);
		element.transformAttributes({
			customfields: {cf_one: {label: "One", type: "text"}, cf_two: {label: "Two", type: "text"}},
			fields: {cf_one: true}
		});
		await element.updateComplete;
		assert.isObject(element.fields, "a visibility map must not be stringified into an attribute");
		assert.deepEqual(element.getVisibleFieldNames(), ["cf_one"], "only the selected field should show");

		const fromString = await fixture<any>(html`
			<et2-customfields-list></et2-customfields-list>
		`);
		fromString.transformAttributes({
			customfields: {cf_one: {label: "One", type: "text"}, cf_two: {label: "Two", type: "text"}},
			fields: "cf_two"
		});
		await fromString.updateComplete;
		assert.deepEqual(fromString.getVisibleFieldNames(), ["cf_two"], "a comma-separated list should still work");
	});

	/**
	 * Contract: templates place a single customfield as `<customfields id="#Name" label="..."/>`,
	 * and that label names the field rather than the customfield's own name.  With more than one
	 * field showing there is nothing for it to name, so the fields keep their own labels.
	 * Setup: render one field with a label, then two fields with the same label set.
	 * Pass: the single field takes the given label; the two keep their own.
	 */
	it("lets a single-field placement rename the field", async() =>
	{
		const single = await fixture<any>(html`
			<et2-customfields label="Membership type"></et2-customfields>
		`);
		single.customfields = {cf_one: {label: "Mitgliedsart", type: "text", rows: 1}};
		single.fields = {cf_one: true};
		await single.updateComplete;
		assert.equal(
			single.querySelector("[data-field='cf_one'] > *")?.label,
			"Membership type",
			"a single placed customfield should take the label the template gave it"
		);

		const many = await fixture<any>(html`
			<et2-customfields label="Membership type"></et2-customfields>
		`);
		many.customfields = {
			cf_one: {label: "Mitgliedsart", type: "text", rows: 1},
			cf_two: {label: "Jahr", type: "text", rows: 1}
		};
		many.fields = {cf_one: true, cf_two: true};
		await many.updateComplete;
		assert.equal(
			many.querySelector("[data-field='cf_one'] > *")?.label,
			"Mitgliedsart",
			"with several fields showing, each keeps its own label"
		);
	});

	/**
	 * Contract: a customfields widget's onchange belongs to the field it renders.  Templates
	 * written against the legacy widget read `widget.value` and `widget.select_options` in the
	 * handler, so `widget` has to be the generated input, not the container.
	 * Setup: render a select customfield with an onchange.
	 * Pass: the generated widget carries the handler, and the container does not swallow it.
	 */
	it("puts onchange on the generated widget, not on itself", async() =>
	{
		const element = await fixture<any>(html`
			<et2-customfields></et2-customfields>
		`);
		let sawValue = null;
		element.onchange = function() { sawValue = this.value; };
		element.customfields = {cf_select: {label: "Select", type: "select", values: {a: "A", b: "B"}}};
		element.fields = {cf_select: true};
		await element.updateComplete;

		const widget = element.querySelector("[data-field='cf_select'] > *") as any;
		assert.isFunction(widget?.onchange, "the generated widget should carry the handler");
		widget.value = "b";
		widget.onchange.call(widget);
		assert.equal(sawValue, "b", "the handler should see the generated widget, with its value");
	});

	/**
	 * Contract: a multi-line text customfield gets the AI assistant offered around it, the way the
	 * legacy widget wrapped one - et2-ai works on whatever is slotted into it, so the textarea has
	 * to be its child.  A single-line one, and an editor that carries its own tools, do not.
	 * Setup: render a multi-row text, a single-row text and an htmlarea customfield.
	 * Pass: only the multi-row text sits inside an et2-ai.
	 */
	it("offers the AI assistant only when the customfield asks for it", async() =>
	{
		await import("../../Et2Ai/Et2Ai");
		const element = await fixture<any>(html`
			<et2-customfields></et2-customfields>
		`);
		element.customfields = {
			cf_area: {label: "Notes", type: "text", rows: 4},
			cf_ai: {label: "Draft", type: "text", rows: 4, values: {noAiTools: false}},
			cf_html: {label: "Body", type: "htmlarea", values: {noAiTools: false}}
		};
		element.fields = {cf_area: true, cf_ai: true, cf_html: true};
		await element.updateComplete;

		assert.equal(
			element.querySelector("[data-field='cf_area'] > *")?.localName,
			"et2-textarea",
			"customfields do not get AI tools by default - the server turns them off for these types"
		);

		const wrapped = element.querySelector("[data-field='cf_ai'] > *");
		assert.equal(wrapped?.localName, "et2-ai", "a customfield that asks for them should be wrapped");
		assert.equal(wrapped?.firstElementChild?.localName, "et2-textarea", "the textarea has to be slotted into it");

		assert.notEqual(
			element.querySelector("[data-field='cf_html'] > *")?.localName,
			"et2-ai",
			"an editor with its own tools is never wrapped, even when asked"
		);
	});

	/**
	 * Contract: `<et2-customfields field="Name"/>` places one customfield.  The server builds an id
	 * of "#Name" from the same attribute and reads the value back from it, so the widget has to
	 * arrive at the same id, or what it submits lands nowhere.
	 * Setup: transform attributes with a field name, and without one.
	 * Pass: the id is the prefixed field name, or the server's default key when no field is named.
	 */
	it("takes its id from the field it was asked to place", async() =>
	{
		const element = await fixture<any>(html`
			<et2-customfields></et2-customfields>
		`);
		element.transformAttributes({field: "Mitgliedsart"});
		assert.equal(element.id, "#Mitgliedsart", "the server reads the value back from this id");

		const all = await fixture<any>(html`
			<et2-customfields></et2-customfields>
		`);
		all.transformAttributes({});
		assert.equal(all.id, "custom_fields", "without a field named, it is the whole set");
	});

	/**
	 * Contract: templates whose onchange handlers were written against the legacy widget call
	 * get_value() on it.
	 * Setup: render a customfield and read it back both ways.
	 * Pass: the legacy name returns what getValue() does.
	 */
	it("keeps the legacy get_value() name working", async() =>
	{
		const element = await fixture<any>(html`
			<et2-customfields></et2-customfields>
		`);
		element.customfields = {cf_text: {label: "Text", type: "text", rows: 1}};
		element.fields = {cf_text: true};
		element.value = {"#cf_text": "kept"};
		await element.updateComplete;
		assert.deepEqual(element.get_value(), element.getValue());
		assert.equal(element.get_value()["#cf_text"], "kept");
	});

	/**
	 * Contract: rendering must not hand a field back the value it started with.  lit re-runs the
	 * ref callback for the same element on every render, and by then the field holds what the user
	 * typed - re-applying would silently discard their input.  A genuinely new value, eg. the entry
	 * being reloaded, still has to reach the field.
	 * Setup: type into a field, force a re-render, then change the widget's own value.
	 * Pass: the typed value survives the re-render; the reload replaces it.
	 */
	it("keeps what the user typed across a re-render", async() =>
	{
		const element = await fixture<any>(html`
			<et2-customfields></et2-customfields>
		`);
		element.customfields = {cf_text: {label: "Text", type: "text", rows: 1}};
		element.fields = {cf_text: true};
		element.value = {"#cf_text": "from the server"};
		await element.updateComplete;

		const field = element.querySelector("[data-field='cf_text'] > *") as any;
		field.value = "typed by the user";

		element.requestUpdate();
		await element.updateComplete;
		assert.equal(
			element.getValue()["#cf_text"],
			"typed by the user",
			"a re-render must not throw away what the user typed"
		);
		assert.equal(element.querySelector("[data-field='cf_text'] > *"), field, "the same field is still there");

		element.value = {"#cf_text": "reloaded"};
		await element.updateComplete;
		assert.equal(
			element.getValue()["#cf_text"],
			"reloaded",
			"a new value from the server should still reach the field"
		);
	});

	/**
	 * Contract: a filemanager customfield renders its name, an upload and a button to link an
	 * existing file, and still submits exactly one value - neither the caption nor the button may
	 * appear as a value of its own, or the server sees a customfield name that does not exist.
	 * The caption is there because an upload renders its label inside its own button, which puts
	 * the name somewhere different from every other customfield and loses it entirely when
	 * noUpload hides that button.
	 * Setup: render a filemanager customfield.
	 * Pass: all three are in the DOM, and getValue() reports only the customfield itself.
	 */
	it("renders a field's extra widgets without submitting them", async() =>
	{
		await import("../../Et2Vfs/Et2VfsUpload");
		await import("../../Et2Vfs/Et2VfsSelectButton");
		const element = await fixture<any>(html`
			<et2-customfields></et2-customfields>
		`);
		element.customfields = {cf_file: {label: "Attachment", type: "filemanager"}};
		element.fields = {cf_file: true};
		await element.updateComplete;

		const rendered = [...element.querySelectorAll("[data-field='cf_file'] > *")].map((w : any) => w.localName);
		assert.deepEqual(
			rendered,
			["et2-label", "et2-vfs-upload", "et2-vfs-select"],
			"a filemanager customfield needs its name, the upload and the button to link an existing file"
		);
		assert.equal(
			element.querySelector("[data-field='cf_file'] > et2-label")?.value,
			"Attachment",
			"the caption names the field, since the upload cannot show its own label here"
		);
		assert.deepEqual(
			Object.keys(element.getValue()),
			["#cf_file"],
			"only the field's own value is submitted - the button has no customfield name"
		);
	});

	/**
	 * Contract: et2-customfields is itself the submitted input.  etemplate2.getValues() only
	 * visits widgets that implement et2_IInput, and Customfields::validate() reads one
	 * {"#name": value} map out of the widget's own id - so the widget reports every editable
	 * field together rather than letting each generated widget submit itself.
	 * Setup: render editable text and select customfields, then change one.
	 * Pass: all four et2_IInput methods exist, getValue() returns the prefixed map, and the
	 * dirty flag follows the generated widgets.
	 */
	it("submits every editable customfield under its own id", async() =>
	{
		const element = await fixture<any>(html`
			<et2-customfields></et2-customfields>
		`);
		element.customfields = {
			cf_text: {label: "Text", type: "text", rows: 1},
			cf_select: {label: "Select", type: "select", values: {open: "Open", done: "Done"}}
		};
		element.fields = {cf_text: true, cf_select: true};
		element.value = {"#cf_text": "first", "#cf_select": "open"};
		await element.updateComplete;

		["getValue", "isDirty", "resetDirty", "isValid"].forEach((method) =>
			assert.isFunction(element[method], `et2_IInput requires ${method}(), or getValues() skips us`)
		);

		assert.deepEqual(
			element.getValue(),
			{"#cf_text": "first", "#cf_select": "open"},
			"values should be reported as a prefixed map"
		);

		element.resetDirty();
		assert.isFalse(element.isDirty(), "nothing is changed right after a reset");

		const text = element.querySelector("[data-field='cf_text'] > *") as any;
		text.value = "second";
		assert.isTrue(element.isDirty(), "a changed field should make the customfields dirty");
		assert.equal(element.getValue()["#cf_text"], "second", "getValue should read the live widget");
	});

	/**
	 * Contract: a template that places <customfields/> with no id still has its values read from
	 * `custom_fields` server-side (Customfields::GLOBAL_ID), and readonly fields are not submitted
	 * because the server has nothing to validate for them.
	 * Setup: render a widget with no id, and a readonly one.
	 * Pass: the id defaults to custom_fields, and a readonly widget submits an empty map.
	 */
	it("defaults its id to custom_fields, and submits nothing when readonly", async() =>
	{
		const element = await fixture<any>(html`
			<et2-customfields></et2-customfields>
		`);
		element.transformAttributes({});
		assert.equal(element.id, "custom_fields", "a customfields widget with no id needs the server's key");

		const readonly = await fixture<any>(html`
			<et2-customfields readonly></et2-customfields>
		`);
		readonly.customfields = {cf_text: {label: "Text", type: "text", rows: 1}};
		readonly.fields = {cf_text: true};
		readonly.value = {"#cf_text": "shown"};
		await readonly.updateComplete;
		assert.deepEqual(readonly.getValue(), {}, "a readonly customfield should not be submitted");
	});

	/**
	 * Contract: generated field widgets need our array managers and instance manager to reach the
	 * server and resolve readonly, but must NOT be moved by Et2Widget.addChild(), which appends.
	 * Setup: render an editable customfield and inspect the generated widget's placement.
	 * Pass: it has us as its parent and is still inside the row lit put it in.
	 */
	it("parents generated widgets without moving them out of their row", async() =>
	{
		const element = await fixture<any>(html`
			<et2-customfields></et2-customfields>
		`);
		element.customfields = {cf_text: {label: "Text", type: "text", rows: 1}};
		element.fields = {cf_text: true};
		await element.updateComplete;

		const row = element.querySelector("[data-field='cf_text']");
		const widget = row?.firstElementChild as any;
		assert.equal(widget?.getParent(), element, "the generated widget should reach our array managers");
		assert.equal(widget?.parentElement, row, "it should stay in the row, not be appended to the host");
	});

	/**
	 * Contract: a customfields widget renders into its own light DOM but can itself sit inside
	 * another component's shadow root - a nextmatch puts its rows inside et2-datagrid's - and it
	 * has to be styled there too.  A document-level stylesheet does not reach a shadow root, and
	 * one adopted onto that root does not survive the datagrid reassigning its adoptedStyleSheets.
	 * Setup: attach a shadow root to a host, connect an et2-customfields-list inside it, then
	 * clear that root's adoptedStyleSheets the way the datagrid's own render does.
	 * Pass: the widget is still laid out by its own stylesheet.
	 */
	it("styles itself inside another component's shadow root", async() =>
	{
		const host = await fixture<HTMLElement>(html`
			<div></div>
		`);
		const shadow = host.attachShadow({mode: "open"});
		const list = document.createElement("et2-customfields-list") as any;
		shadow.appendChild(list);
		list.customfields = {cf_text: {label: "Text", type: "text"}};
		list.fields = {cf_text: true};
		list.value = {"#cf_text": "Value"};
		await list.updateComplete;

		shadow.adoptedStyleSheets = [];
		await list.updateComplete;

		assert.equal(
			getComputedStyle(list.querySelector(".customfields-list")).display,
			"flex",
			"the widget should be laid out by its own stylesheet inside a shadow root"
		);
	});

	/**
	 * Contract: an editable customfield is named by the generated widget's own label, not by
	 * a sibling <label> - a custom element is not labelable, so a sibling would neither focus
	 * it on click nor name it for a screen reader.
	 * Setup: render an editable text customfield with a label.
	 * Pass: the generated widget carries the label and the widget renders no <label> element.
	 */
	it("lets the generated widget carry its own label when editable", async() =>
	{
		const element = await fixture<Et2CustomfieldsBase>(html`
			<et2-customfields></et2-customfields>
		`);
		element.customfields = {
			cf_text: {label: "Project code", type: "text", rows: 1}
		};
		element.fields = {cf_text: true};
		element.value = {"#cf_text": "abc"};
		await element.updateComplete;

		const widget = element.querySelector("[data-field='cf_text'] > *") as any;
		await widget?.updateComplete;

		assert.equal(widget?.label, "Project code", "the generated widget should carry the field label");
		assert.notOk(element.querySelector("label"), "no inert sibling <label> should be rendered");
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
			// a multi-line text customfield is wrapped in et2-ai, so look inside it
			element.querySelector("[data-field='cf_notes'] et2-textarea")?.localName,
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
