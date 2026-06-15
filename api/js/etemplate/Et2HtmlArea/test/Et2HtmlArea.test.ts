import {assert, fixture, html} from "@open-wc/testing";
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";
import * as sinon from "sinon";
import type {Et2HtmlArea} from "../Et2HtmlArea";
import "../Et2HtmlArea";
import {
	BLOCK_FORMATS,
	DEFAULT_MENUBAR,
	editorContentStyle,
	htmlAreaFormats,
	LANGUAGE_CODE,
	menubarFromPreference,
	NPM_PLUGIN_SET,
	normalizeFormatBlock,
	paragraphStyles,
	requestedToolbarSetting,
	toolbarForMode,
	TOOLBAR_ADVANCED,
	TOOLBAR_SIMPLE
} from "../Et2HtmlAreaConfig";

type Preferences = Record<string, any>;

window.egw = {
	lang: (label : string) => label,
	preference: (name : string) => ({
		lang: "en",
		rte_font: "arial, helvetica, sans-serif",
		rte_font_size: "10",
		rte_font_unit: "pt",
		rte_formatblock: "p",
		rte_menubar: "1",
		rte_toolbar: "bold,italic,link"
	}[name] ?? ""),
	ajaxUrl: (menuaction : string) => `/egroupware/json.php?menuaction=${encodeURIComponent(menuaction)}`,
	webserverUrl: "/egroupware",
	tooltipUnbind: () => {}
} as any;

afterEach(() =>
{
	sinon.restore();
});

function preferenceStub(preferences : Preferences)
{
	return (name : string) => preferences[name];
}

describe("Et2HtmlArea TinyMCE preferences", () =>
{
	it("normalizes preferred paragraph font styles with safe defaults", () =>
	{
		assert.deepEqual(
			paragraphStyles(preferenceStub({
				rte_font: "",
				rte_font_size: "",
				rte_font_unit: ""
			})),
			{
				"font-family": "arial, helvetica, sans-serif",
				"font-size": "10pt"
			},
			"Missing font preferences should use the same defaults everywhere"
		);
		assert.deepEqual(
			paragraphStyles(preferenceStub({
				rte_font: "Verdana",
				rte_font_size: "12",
				rte_font_unit: "px"
			})),
			{
				"font-family": "Verdana",
				"font-size": "12px"
			},
			"Explicit font preferences should be normalized once for config and submit-time inlining"
		);
	});

	it("applies font, size, and unit preferences to paragraph formats", () =>
	{
		const formats = htmlAreaFormats(preferenceStub({
			rte_font: "arial, helvetica, sans-serif",
			rte_font_size: "11",
			rte_font_unit: "pt"
		})) as any;

		assert.deepInclude(formats.p, {
			block: "p",
			remove: "all"
		}, "Paragraph format should keep TinyMCE block replacement behavior");
		assert.deepEqual(formats.p.styles, {
			"font-family": "arial, helvetica, sans-serif",
			"font-size": "11pt"
		}, "Paragraph format should use font preferences");
		assert.notProperty(
			formats,
			"customparagraph",
			"Small paragraph should use TinyMCE's native div block, not sticky classes or inline styles"
		);
		assert.deepEqual(
			formats.div,
			{
				block: "div",
				remove: "all"
			},
			"Small paragraph div format should not add inline styles or classes"
		);
		assert.include(BLOCK_FORMATS, "Small Paragraph=div");
	});

	it("makes small paragraph div margins zero in computed editor body CSS", () =>
	{
		const style = document.createElement("style");
		const editorBody = document.createElement("section");
		const div = document.createElement("div");
		const paragraph = document.createElement("p");

		style.textContent = `
body, p, div {
  margin: 1rem 0;
  margin-block: 1rem;
}
${editorContentStyle(preferenceStub({
			rte_font: "arial, helvetica, sans-serif",
			rte_font_size: "11",
			rte_font_unit: "pt"
		}))}
`;
		editorBody.append(div, paragraph);
		document.head.append(style);
		document.body.append(editorBody);

		const divStyle = getComputedStyle(div);
		const paragraphStyle = getComputedStyle(paragraph);

		try
		{
			assert.equal(divStyle.marginTop, "0px");
			assert.equal(divStyle.marginBottom, "0px");
			assert.equal(divStyle.marginBlockStart, "0px");
			assert.equal(divStyle.marginBlockEnd, "0px");
			assert.notEqual(paragraphStyle.marginTop, "0px", "Normal paragraphs should still have vertical margin");
		}
		finally
		{
			style.remove();
			editorBody.remove();
		}
	});

	it("uses rte_formatblock as the initial format block and falls back safely", () =>
	{
		assert.equal(
			normalizeFormatBlock("customparagraph"),
			"div",
			"Legacy small paragraph preference should map to TinyMCE's native div block"
		);
		assert.equal(normalizeFormatBlock("h2"), "h2", "Heading preference should be preserved");
		assert.equal(
			normalizeFormatBlock("script"),
			"p",
			"Unknown format preferences should fall back to paragraph"
		);
	});

	it("maps EGroupware language preferences to TinyMCE language codes", () =>
	{
		assert.equal(LANGUAGE_CODE.de, "de");
		assert.equal(LANGUAGE_CODE["pt-br"], "pt_BR");
		assert.equal(LANGUAGE_CODE.no, "nb_NO");
		assert.equal(LANGUAGE_CODE.uk, "en_GB");
	});

	it("uses menubar preference unless explicitly disabled", () =>
	{
		assert.equal(
			menubarFromPreference("1"),
			DEFAULT_MENUBAR,
			"Enabled menubar preference should show the default menubar"
		);
		assert.isFalse(menubarFromPreference("0"), "Disabled menubar preference should hide the menubar");
		assert.isFalse(menubarFromPreference("1", true), "noMenubar should override the preference");
		assert.equal(
			menubarFromPreference(undefined),
			DEFAULT_MENUBAR,
			"Missing preference should preserve the default menubar"
		);
	});

	it("normalizes toolbar preference values", () =>
	{
		assert.equal(
			requestedToolbarSetting(["bold", "italic"]),
			"bold,italic",
			"Array preference should become a legacy action list"
		);
		assert.equal(
			requestedToolbarSetting({first: "bold", second: "link"}),
			"bold,link",
			"Object preference should become a legacy action list"
		);
		assert.equal(
			requestedToolbarSetting("bold,italic"),
			"bold,italic",
			"String preference should be preserved"
		);
		assert.equal(requestedToolbarSetting("bold", true), "", "noToolbar should ignore toolbar preference");
	});

	it("uses toolbar preference only when mode is not fixed", () =>
	{
		assert.equal(
			toolbarForMode("", "bold,italic,link"),
			"bold italic | link",
			"Preference toolbar should filter the advanced toolbar when mode is not fixed"
		);
		assert.equal(
			toolbarForMode("simple", "bold"),
			TOOLBAR_SIMPLE,
			"Fixed simple mode should ignore the toolbar preference"
		);
		assert.equal(
			toolbarForMode("advanced", "bold"),
			TOOLBAR_ADVANCED,
			"Fixed advanced mode should ignore the toolbar preference"
		);
		assert.equal(
			toolbarForMode("ascii", "bold,italic,link"),
			"",
			"ASCII mode should ignore rich text toolbar preferences"
		);
	});

	it("honors explicit toolbar disabling only when mode is not fixed", () =>
	{
		assert.isFalse(
			toolbarForMode("", "bold", true),
			"noToolbar should disable the preference-driven toolbar"
		);
		assert.equal(
			toolbarForMode("advanced", "bold", true),
			TOOLBAR_ADVANCED,
			"Fixed advanced mode should keep its toolbar preset"
		);
	});

	it("appends integration toolbar items to the selected toolbar", () =>
	{
		assert.equal(
			toolbarForMode("simple", "", false, ["aitoolsPrompts"]),
			`${TOOLBAR_SIMPLE} | aitoolsPrompts`,
			"Integration buttons should be appended without changing the selected preset"
		);
		assert.equal(
			toolbarForMode("", "false | bold", false, ["customButton"]),
			"customButton",
			"Integration buttons should still render when the base toolbar setting is empty"
		);
		assert.isFalse(
			toolbarForMode("", "bold", true, ["customButton"]),
			"Explicit toolbar disabling should also suppress integration buttons"
		);
	});
});

describe("Et2HtmlArea default rich text mode", () =>
{
	it("renders TinyMCE and publishes config bridges when mode is empty", async() =>
	{
		const element = await fixture<Et2HtmlArea>(html`
			<et2-htmlarea value="<p>Hello</p>"></et2-htmlarea>
		`);
		const editor = element.shadowRoot.querySelector("tinymce-editor");
		const configPath = editor?.getAttribute("config") ?? "";
		const callbackPath = editor?.getAttribute("setup")?.replace(/\.setup$/, "") ?? "";
		const configKey = configPath.split(".")[1];
		const callbackKey = callbackPath.split(".")[1];

		assert.equal(element.mode, "", "Empty mode should be the default rich text mode");
		assert.exists(editor, "Default mode should render TinyMCE");
		assert.notExists(element.shadowRoot.querySelector("textarea"), "Default mode should not render textarea");
		assert.equal(editor.getAttribute("toolbar"), "bold italic | link", "Default mode should use the toolbar preference");
		assert.equal(editor.getAttribute("menubar"), DEFAULT_MENUBAR, "Default mode should use the menubar preference");
		assert.equal(editor.getAttribute("statusbar"), "false", "Statusbar should keep the HtmlArea default");
		assert.equal(editor.getAttribute("plugins"), NPM_PLUGIN_SET, "Default mode should activate the configured TinyMCE plugins");
		assert.equal(editor.getAttribute("content_css"), "/egroupware/api/tinymce.php?darkmode=0&YXJpYWwsIGhlbHZldGljYSwgc2Fucy1zZXJpZjo6MTA6OnB0");
		assert.include(editor.getAttribute("images_upload_url"), "type=htmlarea");

		assert.property(window.egwEt2HtmlAreaConfigBridge, configKey, "Config should be published for TinyMCE web component");
		assert.property(window.egwEt2HtmlAreaCallbackBridge, callbackKey, "Callbacks should be published for TinyMCE web component");
		assert.equal(window.egwEt2HtmlAreaConfigBridge[configKey].base_url, "/egroupware/node_modules/tinymce");
		assert.equal(window.egwEt2HtmlAreaConfigBridge[configKey].language, "en");
		assert.equal(window.egwEt2HtmlAreaConfigBridge[configKey].valid_children, "+body[style]");
		assert.isFunction(window.egwEt2HtmlAreaCallbackBridge[callbackKey].setup);
	});

	it("renders rich text directly when readonly", async() =>
	{
		const configKeys = Object.keys(window.egwEt2HtmlAreaConfigBridge ?? {});
		const callbackKeys = Object.keys(window.egwEt2HtmlAreaCallbackBridge ?? {});
		const element = await fixture<Et2HtmlArea>(html`
			<et2-htmlarea readonly value="<p>Hello <strong>world</strong></p>"></et2-htmlarea>
		`);
		const readonlyContent = element.shadowRoot.querySelector("[part='readonly-content']");

		assert.notExists(element.shadowRoot.querySelector("tinymce-editor"), "Readonly rich text should not initialize TinyMCE");
		assert.notExists(element.shadowRoot.querySelector("textarea"), "Readonly rich text should not render a textarea");
		assert.equal(readonlyContent?.querySelector("p")?.textContent, "Hello world");
		assert.exists(
			readonlyContent?.querySelector("strong"),
			"Readonly rich text should render HTML elements from the value"
		);
		assert.deepEqual(
			Object.keys(window.egwEt2HtmlAreaConfigBridge ?? {}),
			configKeys,
			"Readonly rich text should not publish a TinyMCE config bridge"
		);
		assert.deepEqual(
			Object.keys(window.egwEt2HtmlAreaCallbackBridge ?? {}),
			callbackKeys,
			"Readonly rich text should not publish TinyMCE callbacks"
		);
	});

	it("renders ASCII text directly when readonly", async() =>
	{
		const element = await fixture<Et2HtmlArea>(html`
			<et2-htmlarea mode="ascii" readonly value="First line
<strong>literal</strong>"></et2-htmlarea>
		`);
		const readonlyContent = element.shadowRoot.querySelector("[part='readonly-content']");

		assert.notExists(element.shadowRoot.querySelector("tinymce-editor"), "Readonly ASCII text should not initialize TinyMCE");
		assert.notExists(element.shadowRoot.querySelector("textarea"), "Readonly ASCII text should not render a textarea");
		assert.include(readonlyContent?.textContent ?? "", "First line\n<strong>literal</strong>");
		assert.notExists(
			readonlyContent?.querySelector("strong"),
			"Readonly ASCII text should render markup as literal text"
		);
	});

	it("does not push editor-originated content back into TinyMCE on update", async() =>
	{
		const element = await fixture<Et2HtmlArea>(html`
			<et2-htmlarea></et2-htmlarea>
		`);
		const editorContent = "<p><a href=\"https://example.org\">https://example.org</a></p>";
		const setContent = sinon.spy();
		const editor = {
			getContent: () => editorContent,
			setContent
		};

		(element as any)._tinyMceEditor = editor;
		(element as any)._syncValueFromEditor(false);
		await element.updateComplete;

		assert.equal(element.value, editorContent, "Editor input should still update the widget value");
		assert.isFalse(
			setContent.called,
			"Editor-originated value updates should not call setContent and reset the caret"
		);

		element.value = "<p>External update</p>";
		await element.updateComplete;

		assert.isTrue(setContent.calledOnceWith("<p>External update</p>"), "External value updates should still sync into TinyMCE");
	});
});

describe("Et2HtmlAreaReadonly", () =>
{
	it("registers et2-htmlarea_ro and renders rich text directly", async() =>
	{
		const element = await fixture<HTMLElement>(html`
			<et2-htmlarea_ro value="<p>Readonly <em>HTML</em></p>"></et2-htmlarea_ro>
		`);
		const readonlyContent = element.shadowRoot.querySelector("[part='readonly-content']");

		assert.instanceOf(element, customElements.get("et2-htmlarea_ro"));
		assert.notExists(element.shadowRoot.querySelector("tinymce-editor"), "Readonly component should not initialize TinyMCE");
		assert.notExists(element.shadowRoot.querySelector("textarea"), "Readonly component should not render a textarea");
		assert.equal(readonlyContent?.querySelector("p")?.textContent, "Readonly HTML");
		assert.exists(readonlyContent?.querySelector("em"), "Readonly component should render HTML values as HTML");
	});

	it("renders ASCII text literally", async() =>
	{
		const element = await fixture<HTMLElement>(html`
			<et2-htmlarea_ro mode="ascii" value="<p>Literal</p>"></et2-htmlarea_ro>
		`);
		const readonlyContent = element.shadowRoot.querySelector("[part='readonly-content']");

		assert.equal(readonlyContent?.textContent?.trim(), "<p>Literal</p>");
		assert.notExists(readonlyContent?.querySelector("p"), "ASCII mode should not treat the value as HTML");
	});

	it("accepts detached row value updates", async() =>
	{
		const element = await fixture<any>(html`
			<et2-htmlarea_ro value=""></et2-htmlarea_ro>
		`);
		const supportedAttrs : string[] = [];
		const rowData : Record<string, string> = {
			value: "<p>Row <strong>customfield</strong></p>",
			label: "Details",
			mode: ""
		};

		element.getDetachedAttributes(supportedAttrs);
		const filtered = Object.keys(rowData)
			.filter(key => supportedAttrs.includes(key))
			.reduce((values : Record<string, string>, key) =>
			{
				values[key] = rowData[key];
				return values;
			}, {});
		element.setDetachedAttributes([element], filtered);
		await element.updateComplete;

		const readonlyContent = element.shadowRoot.querySelector("[part='readonly-content']");
		assert.equal(
			readonlyContent?.querySelector("p")?.textContent,
			"Row customfield",
			"Nextmatch detached row updates should set the readonly htmlarea value"
		);
		assert.exists(readonlyContent?.querySelector("strong"));
	});
});

inputBasicTests(async() =>
{
	const element = await fixture<Et2HtmlArea>(html`
		<et2-htmlarea mode="ascii"></et2-htmlarea>
	`);
	sinon.stub(element, "egw").returns(window.egw as any);
	return element;
}, "Plain text value", "textarea");

inputBasicTests(async() =>
{
	const element = await fixture<Et2HtmlArea>(html`
		<et2-htmlarea></et2-htmlarea>
	`);
	sinon.stub(element, "egw").returns(window.egw as any);
	return element;
}, "Rich text value", "tinymce-editor");
