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
		assert.isTrue(!!el.shadowRoot.querySelector("textarea"), "source still editable");
	});

	it("opens in edit, showing the source and no preview", async() =>
	{
		const el = await markdownTextarea();

		assert.equal(el.markdownMode, "edit");
		assert.isFalse(!!el.shadowRoot.querySelector(".markdown-shell__preview"), "no preview yet");
	});

	it("renders the parsed markdown in preview", async() =>
	{
		const el = await markdownTextarea();
		el.markdownMode = "preview";
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

		for(const mode of ["split", "preview", "edit", "preview", "split", "edit"])
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

		assert.equal(el.value, "typed after switching", "value still round-trips");
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
		assert.isFalse(!!el.shadowRoot.querySelector("et2-split"), "not in edit");

		el.markdownMode = "preview";
		await elementUpdated(el);
		assert.isFalse(!!el.shadowRoot.querySelector("et2-split"), "not in preview");
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
		preferences = {markdown_view: "split"};

		const el: Et2Textarea = await fixture(html`
            <et2-textarea markdown markdown-mode="preview"></et2-textarea>`);
		await elementUpdated(el);

		assert.equal(el.markdownMode, "preview");
	});

	it("ignores a nonsense preference", async() =>
	{
		preferences = {markdown_view: "sideways"};

		const el: Et2Textarea = await fixture(html`
            <et2-textarea markdown></et2-textarea>`);
		await elementUpdated(el);

		assert.equal(el.markdownMode, "edit");
	});

	it("does not read the preference when markdown is off", async() =>
	{
		preferences = {markdown_view: "preview"};

		const el: Et2Textarea = await fixture(html`
            <et2-textarea></et2-textarea>`);
		await elementUpdated(el);

		assert.equal(el.markdownMode, "edit");
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
