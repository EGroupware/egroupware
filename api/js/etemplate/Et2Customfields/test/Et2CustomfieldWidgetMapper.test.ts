import {assert} from "@open-wc/testing";
import {
	isAllowedCustomfieldFilter,
	mapCustomfieldToWidget,
	mapCustomfieldToWidgets,
	normalizeCustomfieldOptions
} from "../Et2CustomfieldWidgetMapper.ts";

[
	"et2-description",
	"et2-select",
	"et2-select-account",
	"et2-textbox",
	"et2-textbox_ro",
	"et2-number",
	"et2-number_ro",
	"et2-textarea",
	"et2-textarea_ro",
	"et2-checkbox",
	"et2-checkbox_ro",
	"et2-date",
	"et2-date_ro",
	"et2-date-time",
	"et2-date-time_ro",
	"et2-url",
	"et2-url_ro",
	"et2-link",
	"et2-link-entry",
	"et2-htmlarea",
	"et2-label",
	"et2-vfs-upload",
	"et2-vfs-select",
	"et2-password",
	"et2-password_ro"
].forEach((tagName) =>
{
	if(!customElements.get(tagName))
	{
		customElements.define(tagName, class extends HTMLElement {});
	}
});

describe("Et2CustomfieldWidgetMapper", () =>
{
	const egwStub = {
		link_app_list: () => ({project: true})
	};
	const previousEgw = (globalThis as any).egw;

	before(() =>
	{
		(globalThis as any).egw = () => egwStub;
		Object.assign((globalThis as any).egw, egwStub);
	});

	after(() =>
	{
		(globalThis as any).egw = previousEgw;
	});

	it("maps text customfields to editable or readonly textbox widgets", () =>
	{
		const editable = mapCustomfieldToWidget("cf_text", {type: "text", rows: 1, len: 40}, "Value", {
			context: "field",
			readonly: false
		});
		assert.equal(editable?.tagName, "et2-textbox", "editable text should use textbox");
		assert.deepInclude(editable?.attrs || {}, {
			id: "#cf_text",
			value: "Value",
			maxlength: 40,
			width: "40em"
		});

		const readonly = mapCustomfieldToWidget("cf_text", {type: "text", rows: 1}, "Value", {
			context: "list",
			readonly: true
		});
		assert.equal(readonly?.tagName, "et2-textbox_ro", "readonly text should use registered readonly textbox");
	});

	it("maps numeric, textarea, select, and radio settings", () =>
	{
		assert.deepInclude(
			mapCustomfieldToWidget("cf_int", {type: "int"}, 5, {context: "field"})?.attrs || {},
			{precision: 0},
			"integer customfields should set zero precision"
		);
		assert.equal(
			mapCustomfieldToWidget("cf_textarea", {type: "text", rows: 4}, "Long", {context: "field"})?.tagName,
			"et2-textarea",
			"multi-row text should use textarea"
		);

		const select = mapCustomfieldToWidget(
			"cf_select",
			{type: "select", rows: 3, values: {"@": "customfields/options", a: "A"}},
			"a",
			{context: "field"}
		);
		assert.equal(select?.tagName, "et2-select", "select customfields should use select");
		assert.deepInclude(select?.attrs || {}, {
			rows: 3,
			multiple: true,
			searchUrl: "customfields/options"
		});
		assert.deepEqual(select?.attrs.select_options, [{value: "a", label: "A"}]);

		const radio = mapCustomfieldToWidget("cf_radio", {type: "radio", values: {"": "Pick", a: "A"}}, "a", {
			context: "field"
		});
		assert.equal(radio?.tagName, "et2-select", "radio customfields use select as the webcomponent equivalent");
		assert.deepEqual(radio?.attrs.select_options, [{value: "a", label: "A"}]);
	});

	it("maps app-backed fields to editable link-entry and readonly link widgets", () =>
	{
		const appBacked = mapCustomfieldToWidget(
			"cf_project",
			{type: "project", values: {filter: "active"}},
			"12",
			{context: "field", apps: {project: true}}
		);
		assert.equal(appBacked?.tagName, "et2-link-entry", "app-backed customfields should use link-entry");
		assert.deepInclude(appBacked?.attrs || {}, {
			onlyApp: "project",
			value: {app: "project", id: "12"},
			searchOptions: {filter: {filter: "active"}}
		});

		const readonly = mapCustomfieldToWidget(
			"cf_project",
			{type: "project", values: {filter: "active"}},
			"12",
			{context: "list", readonly: true, apps: {project: true}}
		);
		assert.equal(readonly?.tagName, "et2-link", "readonly app-backed customfields should display as links");
		assert.deepInclude(readonly?.attrs || {}, {
			app: "project",
			value: {app: "project", id: "12"}
		});
		assert.notProperty(readonly?.attrs || {}, "searchOptions", "readonly links should not keep search options");

		const discovered = mapCustomfieldToWidget(
			"cf_project",
			{type: "project"},
			"13",
			{context: "list", readonly: true}
		);
		assert.equal(discovered?.tagName, "et2-link", "app-backed customfields should use egw link app discovery by default");

		assert.isTrue(isAllowedCustomfieldFilter({type: "select"}));
		assert.isTrue(isAllowedCustomfieldFilter({type: "project"}, {project: true}));
		assert.isFalse(isAllowedCustomfieldFilter({type: "filemanager"}, {filemanager: true}));
	});

	it("applies filter defaults and skips disallowed filters", () =>
	{
		const filter = mapCustomfieldToWidget(
			"cf_select",
			{type: "select", values: {open: "Open"}},
			"",
			{context: "filters"}
		);
		assert.equal(filter?.tagName, "et2-select", "filter customfields should render as selectboxes");
		assert.deepInclude(filter?.attrs || {}, {
			emptyLabel: "all",
			required: false,
			multiple: true
		});
		assert.notProperty(filter?.attrs || {}, "rows", "filter widgets should not keep rows");

		assert.isNull(
			mapCustomfieldToWidget("cf_text", {type: "text"}, "", {context: "filters"}),
			"non-select non-app fields should not render in filter mode"
		);
	});

	it("gives the label to the generated widget only where it builds a form control", () =>
	{
		const field = {type: "text", label: "Project code", rows: 1};
		assert.equal(
			mapCustomfieldToWidget("cf_text", field, "", {context: "field"})?.attrs.label,
			"Project code",
			"an editable customfield should name itself"
		);
		assert.equal(
			mapCustomfieldToWidget("cf_select", {type: "select", label: "Status"}, "", {context: "filters"})?.attrs.label,
			"Status",
			"a filter should name itself"
		);
		assert.isUndefined(
			mapCustomfieldToWidget("cf_text", field, "", {context: "list", readonly: true})?.attrs.label,
			"a list shows values, with the field name on the surrounding row instead"
		);
		assert.isUndefined(
			mapCustomfieldToWidget("cf_text", field, "", {context: "row", readonly: true})?.attrs.label,
			"a datagrid row shows values, with the field name in the column header instead"
		);
		assert.equal(
			mapCustomfieldToWidget("cf_name", {type: "text"}, "", {context: "field"})?.attrs.label,
			"cf_name",
			"a field with no label of its own should fall back to its name"
		);

		// The "" entry of a radio customfield is the caption of its empty option
		const radio = mapCustomfieldToWidget("cf_radio", {type: "radio", label: "Size", values: {"": "Pick", a: "A"}}, "", {
			context: "field"
		});
		assert.deepInclude(radio?.attrs || {}, {label: "Size", emptyLabel: "Pick"});
	});

	it("renders one button per label=onclick value", () =>
	{
		const two = mapCustomfieldToWidgets(
			"cf_go",
			{type: "button", label: "Actions", values: {Approve: "app.x.approve", Reject: "app.x.reject"}},
			"",
			{context: "field"}
		);
		assert.lengthOf(two, 2, "each label=onclick value should get its own button");
		assert.deepEqual(two.map((m) => m.attrs.label), ["Approve", "Reject"]);
		assert.deepEqual(two.map((m) => m.attrs.onclick), ["app.x.approve", "app.x.reject"]);
		assert.notEqual(two[0].attrs.id, two[1].attrs.id, "two widgets must not share one id");

		const one = mapCustomfieldToWidgets("cf_go", {type: "button", label: "Go", values: {Send: "app.x.send"}}, "", {
			context: "field"
		});
		assert.lengthOf(one, 1);
		assert.deepInclude(one[0].attrs, {label: "Send", onclick: "app.x.send"});

		// Nothing to press, so say so rather than render a button that does nothing
		const none = mapCustomfieldToWidgets("cf_go", {type: "button", label: "Go"}, "", {context: "field"});
		assert.equal(none[0].attrs.label, 'No "label=onclick" in values!');

		assert.isEmpty(
			mapCustomfieldToWidgets("cf_go", {type: "button", values: {A: "x"}}, "", {context: "list", readonly: true}),
			"a list has nothing to press"
		);
	});

	it("passes a template's onchange to the generated widget", () =>
	{
		assert.equal(
			mapCustomfieldToWidget("cf_sel", {type: "select", label: "S"}, "", {
				context: "field",
				onchange: "app.infolog.setTitle"
			})?.attrs.onchange,
			"app.infolog.setTitle",
			"the handler belongs on the input that changed"
		);
		assert.notProperty(
			mapCustomfieldToWidget("cf_sel", {type: "select", label: "S"}, "", {context: "field"})?.attrs || {},
			"onchange",
			"no handler should be invented when the template gave none"
		);
	});

	it("sizes an htmlarea customfield and lets its values configure the editor", () =>
	{
		const mapping = mapCustomfieldToWidget(
			"cf_html",
			{type: "htmlarea", label: "Body", len: 600, rows: 10, values: {mode: "ascii", toolbar: "bold italic"}},
			"",
			{context: "field"}
		);
		assert.equal(mapping?.tagName, "et2-htmlarea");
		// TinyMCE's init config wants plain pixel lengths
		assert.deepInclude(mapping?.attrs.config, {width: "600px", height: "160px", toolbarStartupExpanded: false});
		// anything else in values configures the widget, so a customfield can reach settings the
		// mapper knows nothing about
		assert.deepInclude(mapping?.attrs || {}, {mode: "ascii", toolbar: "bold italic"});

		const rowsDefault = mapCustomfieldToWidget("cf_html", {type: "htmlarea"}, "", {context: "field"});
		assert.equal(rowsDefault?.attrs.config.height, "80px", "five rows is the default height");
	});

	it("does not treat option-carrying values as widget settings", () =>
	{
		// values here are the options themselves, not settings - they must not land as properties
		const select = mapCustomfieldToWidget("cf_sel", {type: "select", values: {a: "A", b: "B"}}, "", {
			context: "field"
		});
		assert.notProperty(select?.attrs || {}, "a");
		assert.deepEqual(select?.attrs.select_options, [{value: "a", label: "A"}, {value: "b", label: "B"}]);
	});

	it("renders label and header customfields as captions, not inputs", () =>
	{
		const header = mapCustomfieldToWidget("cf_head", {type: "header", label: "Contact details"}, "", {
			context: "field"
		});
		assert.equal(header?.tagName, "et2-label", "a header customfield is a caption");
		assert.equal(header?.attrs.value, "Contact details", "it shows the field's own label");
		assert.isTrue(header?.attrs.readonly, "there is nothing to submit for a caption");
		assert.include(header?.attrs.class, "et2_customfield_header", "styling keys off the legacy class name");
		assert.notProperty(header?.attrs || {}, "label", "the caption is the value, not a label on a control");
		assert.notProperty(header?.attrs || {}, "id", "a caption must not answer to the customfield's name");

		const label = mapCustomfieldToWidget("cf_label", {type: "label", label: "Notes follow"}, "", {
			context: "field"
		});
		assert.equal(label?.tagName, "et2-label");
		assert.include(label?.attrs.class, "et2_customfield_label");
	});

	it("keeps the AI assistant off unless the customfield asks for it", () =>
	{
		assert.isTrue(
			mapCustomfieldToWidget("cf_area", {type: "text", rows: 4}, "", {context: "field"})?.attrs.noAiTools,
			"a customfield textarea should not offer AI tools by default"
		);
		assert.isTrue(
			mapCustomfieldToWidget("cf_html", {type: "htmlarea"}, "", {context: "field"})?.attrs.noAiTools,
			"nor should an htmlarea customfield"
		);
		assert.isFalse(
			mapCustomfieldToWidget("cf_area", {type: "text", rows: 4, values: {noAiTools: false}}, "", {
				context: "field"
			})?.attrs.noAiTools,
			"a customfield can turn them on through values, like any other setting"
		);
	});

	it("offers a filemanager customfield a button to link an existing file", () =>
	{
		const both = mapCustomfieldToWidgets("cf_file", {type: "filemanager", label: "Attachment"}, "", {
			context: "field"
		});
		assert.lengthOf(both, 2, "uploading is only half of what a filemanager customfield is for");
		assert.deepEqual(both.map((m) => m.tagName), ["et2-vfs-upload", "et2-vfs-select"]);
		assert.deepInclude(both[1].attrs, {
			id: "#cf_file_vfs_select",
			path: "~",
			buttonLabel: "Link"
		});
		assert.notProperty(
			both[1].attrs, "methodId",
			"the path to link into only exists on the upload element, so it cannot be set here - " +
			"Et2Customfields reads it off the upload when it places the button"
		);
		assert.notProperty(both[1].attrs, "value", "the button carries no value of its own");

		// a field that says it does not want the button
		assert.lengthOf(
			mapCustomfieldToWidgets("cf_file", {type: "filemanager", values: {noVfsSelect: "1"}}, "", {
				context: "field"
			}),
			1,
			"values.noVfsSelect turns the button off"
		);

		// a field with nothing to upload into still gets the button - that is its only control
		const noUpload = mapCustomfieldToWidgets("cf_file", {type: "filemanager", values: {noUpload: "1"}}, "", {
			context: "field"
		});
		assert.lengthOf(noUpload, 2, "a noUpload field would have no control at all without it");

		assert.lengthOf(
			mapCustomfieldToWidgets("cf_file", {type: "filemanager"}, "", {context: "list", readonly: true}),
			1,
			"there is nothing to link from a list"
		);
	});

	it("keeps a filemanager customfield labelled and compact where it is only being shown", () =>
	{
		// A print or view template is nothing but readonly fields, so this one has to be labelled
		// the same way all of them are: the upload puts its own label inside its button, and a
		// readonly upload has no button, so the label has to come off the widget for
		// Et2Customfields to put it in the label column instead.
		const readonly = mapCustomfieldToWidgets("cf_file", {type: "filemanager", label: "Attachment"}, "", {
			context: "field",
			readonly: true
		});
		assert.lengthOf(readonly, 1, "there is nothing to link into a field nobody can change");
		assert.equal(readonly[0].tagName, "et2-vfs-upload");
		assert.notProperty(readonly[0].attrs, "label", "the label belongs in the label column, not in a hidden button");
		assert.deepInclude(readonly[0].attrs, {
			readonly: true,
			display: "small",
			inline: true
		}, "a shown file is one compact line, not a thumbnail row");

		// Et2File gives itself these two, but only in loadFromXML(), which a customfield's widget
		// never goes through - so losing them here means losing them entirely
		assert.deepInclude(
			mapCustomfieldToWidgets("cf_file", {type: "filemanager", label: "Attachment"}, "", {context: "field"})[0].attrs,
			{display: "small", inline: true},
			"an editable one is laid out the same way"
		);
	});

	it("never puts the editable password widget where the field is only being shown", () =>
	{
		const stored = "caps3UfNJHx6imfMRKXD2A==nXIvE/hPqD2hFdB5uRVGsg==";

		// a list row, one per entry, whether or not anyone opened it
		assert.equal(
			mapCustomfieldToWidget("cf_pw", {type: "passwd", label: "Password"}, stored, {
				context: "list",
				readonly: true
			})?.tagName,
			"et2-password_ro",
			"a list gets the readonly password widget, like every other type does"
		);

		// a print template: still an edit context, but not editable
		assert.equal(
			mapCustomfieldToWidget("cf_pw", {type: "passwd"}, stored, {context: "field", readonly: true})?.tagName,
			"et2-password_ro"
		);

		// where it can actually be edited it is the real widget
		const editable = mapCustomfieldToWidget("cf_pw", {type: "passwd"}, stored, {context: "field"});
		assert.equal(editable?.tagName, "et2-password");
		assert.deepInclude(editable?.attrs || {}, {viewable: true, plaintext: false, autocomplete: "new-password"});
	});

	it("lines editable customfields up in a label column", () =>
	{
		// et2-label-fixed is the shared way to ask for a fixed label column, which is what the
		// table this replaced used to give
		assert.include(
			mapCustomfieldToWidget("cf_text", {type: "text", label: "Project code", rows: 1}, "", {
				context: "field"
			})?.attrs.class ?? "",
			"et2-label-fixed",
			"an editable customfield should line up with the others"
		);

		// a caption has no label, so a label column would only indent it
		assert.notInclude(
			mapCustomfieldToWidget("cf_head", {type: "header", label: "Contact details"}, "", {
				context: "field"
			})?.attrs.class ?? "",
			"et2-label-fixed"
		);

		// a list shows values with the name on the row, so there is no label to line up
		assert.notInclude(
			mapCustomfieldToWidget("cf_text", {type: "text", rows: 1}, "", {context: "list", readonly: true})
				?.attrs.class ?? "",
			"et2-label-fixed"
		);

		// a filterbox gives every filter it generates itself the same class, and the one on our own
		// tag can not reach these - they are two elements further down, in our light DOM
		assert.include(
			mapCustomfieldToWidget("cf_sel", {type: "select", label: "Project code", values: {a: "A"}}, "", {
				context: "filters"
			})?.attrs.class ?? "",
			"et2-label-fixed",
			"a customfield filter should line up with the filters around it"
		);
	});

	it("normalizes options and falls back for unsupported types", () =>
	{
		assert.deepEqual(
			normalizeCustomfieldOptions({a: "A", b: "B"}),
			[
				{value: "a", label: "A"},
				{value: "b", label: "B"}
			],
			"key-value options should normalize to select option objects"
		);
		assert.equal(
			mapCustomfieldToWidget("cf_unknown", {type: "not-registered"}, "Value", {context: "list"})?.tagName,
			"et2-description",
			"unsupported customfield types should fall back to description"
		);
	});
});
