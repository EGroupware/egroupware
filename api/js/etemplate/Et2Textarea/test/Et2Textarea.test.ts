/**
 * Test file for et2-textarea's markdown editing surface
 *
 * Contract under test: the markdown editor is strictly opt-in.  With markdown off, et2-textarea
 * must render exactly what Shoelace renders today - no shell, no view switcher, no preview - so
 * that the ~hundreds of existing textareas in the suite are untouched.  With markdown on, the
 * shell appears and the view switcher drives which panes are shown.
 *
 * Setup: real widgets in a fixture, with a minimal egw() stub for lang() and the preference
 * read/write the view switcher does.  Preference writes are captured rather than sent.
 *
 * Pass criteria: presence or absence of the .markdown-shell / preview / textarea nodes in the
 * shadow root.  The first test is the regression guard - if it fails, every plain textarea in
 * EGroupware has changed.
 */
import {assert, elementUpdated, fixture, html} from "@open-wc/testing";
import type {Et2Textarea} from "../Et2Textarea";
import "../Et2Textarea";
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";

let written: { app: string, name: string, value: any }[] = [];
let preferences: Record<string, any> = {};

window.egw = {
	lang: (label: string, ...args: any[]) => args.length ? `${label} ${args.join(" ")}` : label,
	preference: (name: string) => preferences[name],
	set_preference: (app: string, name: string, value: any) => written.push({app, name, value}),
	webserverUrl: "/egroupware",
	image: () => "",
	tooltipUnbind: () => {}
} as any;

beforeEach(() =>
{
	written = [];
	preferences = {};
});

describe("et2-textarea without markdown", () =>
{
	it("renders no markdown chrome at all", async() =>
	{
		const el: Et2Textarea = await fixture(html`
            <et2-textarea .value=${"# not a heading"}></et2-textarea>`);
		await elementUpdated(el);

		assert.isFalse(!!el.shadowRoot.querySelector(".markdown-shell"), "no shell");
		assert.isFalse(!!el.shadowRoot.querySelector(".markdown-view"), "no view switcher");
		assert.isFalse(!!el.shadowRoot.querySelector(".et2_markdown"), "nothing parsed");
		assert.isTrue(!!el.shadowRoot.querySelector("textarea"), "still a plain textarea");
	});

	it("defaults markdown off", async() =>
	{
		const el: Et2Textarea = await fixture(html`
            <et2-textarea></et2-textarea>`);
		assert.isFalse(el.markdown);
	});
});

describe("et2-textarea with markdown", () =>
{
	async function markdownTextarea(value = "# Heading\n\ntext"): Promise<Et2Textarea>
	{
		const el: Et2Textarea = await fixture(html`
            <et2-textarea markdown .value=${value}></et2-textarea>`);
		await elementUpdated(el);
		return el;
	}

	it("wraps the textarea in the shell and shows the view switcher", async() =>
	{
		const el = await markdownTextarea();

		assert.isTrue(!!el.shadowRoot.querySelector(".markdown-shell"), "shell");
		assert.isTrue(!!el.shadowRoot.querySelector(".markdown-view"), "view switcher");
		assert.isTrue(!!el.shadowRoot.querySelector("textarea"), "source still in the DOM");
	});

	it("keeps the view switcher out of the way until hovered or focused", async() =>
	{
		// explicitly in edit: in view the source pane is hidden, so it cannot take focus and
		// :focus-within could never fire
		const el: Et2Textarea = await fixture(html`
            <et2-textarea markdown markdown-mode="edit" .value=${"# Heading"}></et2-textarea>`);
		await elementUpdated(el);
		const view = el.shadowRoot.querySelector(".markdown-view");

		// same affordance as the AI button: present, but not visible at rest
		assert.equal(getComputedStyle(view).visibility, "hidden", "hidden at rest");

		// focus-within is what keeps it reachable without a pointer
		(<HTMLTextAreaElement>el.shadowRoot.querySelector("textarea")).focus();
		await elementUpdated(el);
		assert.equal(getComputedStyle(view).visibility, "visible", "visible once focused");
	});

	it("opens in view, showing the rendered markdown", async() =>
	{
		const el = await markdownTextarea();

		assert.equal(el.markdownMode, "view");
		assert.isTrue(!!el.shadowRoot.querySelector(".markdown-shell__preview"), "preview from the start");
	});

	it("shows the source and no preview in edit", async() =>
	{
		const el: Et2Textarea = await fixture(html`
            <et2-textarea markdown markdown-mode="edit" .value=${"# Heading\n\ntext"}></et2-textarea>`);
		await elementUpdated(el);

		assert.isTrue(!!el.shadowRoot.querySelector("textarea"), "source");
		assert.isFalse(!!el.shadowRoot.querySelector(".markdown-shell__preview"), "no preview");
	});

	it("renders the parsed markdown in view", async() =>
	{
		const el = await markdownTextarea();
		el.markdownMode = "view";
		await elementUpdated(el);

		const preview = el.shadowRoot.querySelector(".markdown-shell__preview");
		assert.isTrue(!!preview, "preview pane");
		assert.isTrue(!!preview.querySelector("h1"), "markdown was parsed");

		// hidden, NOT removed: Shoelace's textarea internals reach for this.input on every
		// update, so dropping it from the DOM throws
		const source = el.shadowRoot.querySelector(".markdown-shell__source");
		assert.isTrue(source.hasAttribute("hidden"), "source pane hidden");
		assert.isTrue(!!el.shadowRoot.querySelector("textarea"), "source still in the DOM");
	});

	it("survives cycling through every view", async() =>
	{
		const el = await markdownTextarea("start");

		for(const mode of ["split", "view", "edit", "view", "split", "edit"])
		{
			el.markdownMode = <any>mode;
			await elementUpdated(el);
		}

		// the source has been re-parented several times - it still has to work
		const textarea = <HTMLTextAreaElement>el.shadowRoot.querySelector("textarea");
		assert.isTrue(!!textarea, "textarea survived");

		textarea.value = "typed after switching";
		textarea.dispatchEvent(new Event("input", {bubbles: true, composed: true}));
		await elementUpdated(el);

		assert.equal((<any>el).value, "typed after switching", "value still round-trips");
	});

	it("shows source and preview together in split", async() =>
	{
		const el = await markdownTextarea();
		el.markdownMode = "split";
		await elementUpdated(el);

		assert.isTrue(!!el.shadowRoot.querySelector("et2-split"), "splitter mounted");
		assert.isTrue(!!el.shadowRoot.querySelector("textarea"), "source");
		assert.isTrue(!!el.shadowRoot.querySelector(".markdown-shell__preview"), "preview");
	});

	it("only mounts the splitter in split view", async() =>
	{
		const el = await markdownTextarea();
		assert.isFalse(!!el.shadowRoot.querySelector("et2-split"), "not in view");

		el.markdownMode = "edit";
		await elementUpdated(el);
		assert.isFalse(!!el.shadowRoot.querySelector("et2-split"), "not in edit");
	});

	it("gives the splitter no id, so it writes no splitter-size preference", async() =>
	{
		const el = await markdownTextarea();
		el.markdownMode = "split";
		await elementUpdated(el);

		const split = el.shadowRoot.querySelector("et2-split");
		assert.isTrue(!split.id, "no id on the splitter");
	});
});

describe("et2-textarea markdown view preference", () =>
{
	it("seeds the view from the preference", async() =>
	{
		preferences = {markdown_view: "split"};

		const el: Et2Textarea = await fixture(html`
            <et2-textarea markdown></et2-textarea>`);
		await elementUpdated(el);

		assert.equal(el.markdownMode, "split");
	});

	it("ignores a preference the template overrides", async() =>
	{
		// neither "split" nor the "view" default, so a pass can only mean the template won
		preferences = {markdown_view: "split"};

		const el: Et2Textarea = await fixture(html`
            <et2-textarea markdown markdown-mode="edit"></et2-textarea>`);
		await elementUpdated(el);

		assert.equal(el.markdownMode, "edit");
	});

	it("ignores a nonsense preference", async() =>
	{
		preferences = {markdown_view: "sideways"};

		const el: Et2Textarea = await fixture(html`
            <et2-textarea markdown></et2-textarea>`);
		await elementUpdated(el);

		assert.equal(el.markdownMode, "view");
	});

	it("does not read the preference when markdown is off", async() =>
	{
		// "split" is not the default, so reading the preference would be visible here
		preferences = {markdown_view: "split"};

		const el: Et2Textarea = await fixture(html`
            <et2-textarea></et2-textarea>`);
		await elementUpdated(el);

		assert.equal(el.markdownMode, "view");
	});

	it("remembers the view the user picks", async() =>
	{
		const el: Et2Textarea = await fixture(html`
            <et2-textarea markdown></et2-textarea>`);
		await elementUpdated(el);

		(<any>el)._setMarkdownMode("split");
		await elementUpdated(el);

		assert.equal(el.markdownMode, "split");
		assert.deepEqual(written, [{app: "common", name: "markdown_view", value: "split"}]);
	});
});

/**
 * Contract under test: the preview is a way back into the editor. Clicking a rendered block
 * switches preview to edit and puts the caret on the source that produced it.
 *
 * Setup: a markdown textarea with known source, driven through real click events on the
 * rendered nodes. data-source-line comes from the renderer's sourceMap option.
 *
 * Pass criteria: the resulting selectionStart is the offset of the clicked construct in the
 * markdown source. A failure means the source map or the offset lookup drifted - both would
 * leave the caret in the wrong place, which is worse than not moving it.
 */
describe("et2-textarea markdown click-to-edit", () =>
{
	const SRC = "# Heading\n\na **bold** word\n\n- item one";

	async function preview(): Promise<Et2Textarea>
	{
		const el: Et2Textarea = await fixture(html`
            <et2-textarea markdown markdown-mode="view" .value=${SRC}></et2-textarea>`);
		await elementUpdated(el);
		return el;
	}

	/**
	 * Click the middle of an element, with real coordinates.
	 *
	 * The coordinates are the point: the caret comes from caretPositionFromPoint, so a synthetic
	 * click without them lands nowhere and the widget correctly degrades to the block start.
	 */
	function clickCentre(el: Element)
	{
		const rect = el.getBoundingClientRect();
		el.dispatchEvent(new MouseEvent("click", {
			bubbles: true, composed: true,
			clientX: Math.round(rect.left + rect.width / 2),
			clientY: Math.round(rect.top + rect.height / 2)
		}));
	}

	async function caretAfterClick(el: Et2Textarea, selector: string): Promise<number>
	{
		clickCentre(el.shadowRoot.querySelector(".markdown-shell__preview " + selector));
		await elementUpdated(el);
		await el.updateComplete;
		return (<HTMLTextAreaElement>el.shadowRoot.querySelector("textarea")).selectionStart;
	}

	it("tags rendered blocks with their source line", async() =>
	{
		const el = await preview();
		const pane = el.shadowRoot.querySelector(".markdown-shell__preview");

		assert.equal(pane.querySelector("h1").getAttribute("data-source-line"), "0");
		assert.equal(pane.querySelector("p").getAttribute("data-source-line"), "2");
		assert.equal(pane.querySelector("li").getAttribute("data-source-line"), "4");
	});

	it("switches to edit and puts the caret in the clicked block", async() =>
	{
		const el = await preview();
		const caret = await caretAfterClick(el, "li");

		assert.equal(el.markdownMode, "edit", "switched to edit");
		const from = SRC.indexOf("item one");
		assert.isTrue(caret >= from && caret <= from + "item one".length,
			`caret ${caret} inside "item one" (${from}..${from + 8})`);
	});

	it("lands inside the clicked word, past its markers", async() =>
	{
		const el = await preview();
		const caret = await caretAfterClick(el, "strong");

		const from = SRC.indexOf("bold");
		assert.isTrue(caret >= from && caret <= from + 4, `caret ${caret} inside "bold" (${from}..${from + 4})`);
	});

	it("skips the heading marker", async() =>
	{
		const el = await preview();
		const caret = await caretAfterClick(el, "h1");

		assert.isTrue(caret >= SRC.indexOf("Heading"), `caret ${caret} past the "# "`);
	});

	it("does not change the view when clicking the preview in split", async() =>
	{
		const el = await preview();
		el.markdownMode = "split";
		await elementUpdated(el);

		const caret = await caretAfterClick(el, "h1");

		assert.equal(el.markdownMode, "split", "split stays split");
		assert.isTrue(caret >= SRC.indexOf("Heading"), "caret still moved");
	});

	it("falls back to the block start for a click with no usable point", async() =>
	{
		const el = await preview();
		// no clientX/clientY: nothing for the caret API to resolve, so the deliberate cliff
		el.shadowRoot.querySelector(".markdown-shell__preview li")
			.dispatchEvent(new MouseEvent("click", {bubbles: true, composed: true}));
		await elementUpdated(el);
		await el.updateComplete;

		const caret = (<HTMLTextAreaElement>el.shadowRoot.querySelector("textarea")).selectionStart;
		assert.equal(caret, SRC.indexOf("- item one"), "start of the block's source line");
	});

	it("leaves the display path free of source-line markup", async() =>
	{
		const el: Et2Textarea = await fixture(html`
            <et2-textarea markdown markdown-mode="edit" .value=${SRC}></et2-textarea>`);
		await elementUpdated(el);
		assert.isFalse(!!el.shadowRoot.querySelector("[data-source-line]"), "none in edit view");
	});
});

inputBasicTests(async() => await fixture<Et2Textarea>(html`<et2-textarea></et2-textarea>`), "I'm a good test value", "textarea");
