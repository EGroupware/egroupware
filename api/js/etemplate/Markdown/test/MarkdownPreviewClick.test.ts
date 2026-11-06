/**
 * Test file for clicking the markdown preview to get back into the editor
 *
 * Contract under test: the preview is the way back into the source.  A click on a word puts the
 * caret at that word; a click that hits no text at all - the space under the last line, or the
 * whole pane of a field with nothing in it yet - still opens the editor, with the caret after the
 * last word.  A link in the preview stays a link.
 *
 * Setup: real widgets in a fixture with the usual minimal egw() stub.  document.caretPositionFromPoint
 * is stubbed where a test cares which character was hit: a synthetic click has no meaningful
 * coordinates, and what is under test is what we do with the answer, not the browser's hit-testing.
 *
 * Pass criteria: the view the widget ends up in, and where the caret lands in the source.
 */
import {assert, elementUpdated, fixture, html} from "@open-wc/testing";
import type {Et2Textarea} from "../../Et2Textarea/Et2Textarea";
import "../../Et2Textarea/Et2Textarea";

window.egw = {
	lang: (label : string, ...args : any[]) => args.length ? `${label} ${args.join(" ")}` : label,
	preference: () => undefined,
	set_preference: () => {},
	webserverUrl: "/egroupware",
	image: () => "",
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	message: () => {},
	uid: () => "uid"
} as any;

type Editor = Et2Textarea & { value : string, markdownMode : string };

async function editor(value : string, mode = "view") : Promise<Editor>
{
	const el : Editor = await fixture(html`
        <et2-textarea markdown markdown-mode=${mode} .value=${value}></et2-textarea>`);
	await elementUpdated(el);
	return el;
}

function preview(el : Editor) { return el.shadowRoot.querySelector(".markdown-shell__preview"); }

function source(el : Editor) { return el.shadowRoot.querySelector("textarea"); }

async function click(el : Editor, target : Element)
{
	target.dispatchEvent(new MouseEvent("click", {bubbles: true, composed: true}));
	await elementUpdated(el);
	// the caret is set in a .then() on updateComplete
	await el.updateComplete;
	await new Promise(resolve => setTimeout(resolve, 0));
}

/** what the browser says is under the pointer; null = "cannot tell", the usual answer here */
function caretFromPoint(offsetNode : Node | null, offset = 0)
{
	const real = (<any>document).caretPositionFromPoint;
	(<any>document).caretPositionFromPoint = () => offsetNode ? {offsetNode, offset} : null;
	return () => {(<any>document).caretPositionFromPoint = real;};
}

describe("markdown preview click - a field with no text", () =>
{
	/**
	 * The bug: an empty field renders no blocks, so every click missed "[data-source-line]" and
	 * the handler gave up - leaving no way at all to start typing by clicking.
	 */
	it("still opens the editor", async() =>
	{
		const el = await editor("");

		await click(el, preview(el));

		assert.equal(el.markdownMode, "edit", "clicking an empty preview has to get you in");
	});

	/**
	 * The other half of the same bug: the handler can be as willing as it likes, a box with no
	 * height cannot be clicked.  An empty value renders no blocks at all, so the pane collapsed
	 * and there was physically nothing on screen to aim at.
	 */
	it("still has something to click when it is empty", async() =>
	{
		const el = await editor("");

		assert.isAbove(preview(el).getBoundingClientRect().height, 0);
	});

	it("puts the caret in the empty source", async() =>
	{
		const el = await editor("");

		await click(el, preview(el));

		assert.equal(source(el).selectionStart, 0);
		assert.equal(el.shadowRoot.activeElement, source(el), "and focuses it, ready to type");
	});
});

describe("markdown preview click - a field with text", () =>
{
	it("opens the editor behind the last word when the click hit no text", async() =>
	{
		const el = await editor("first line\n\nsecond line");

		// the pane itself, ie. the empty space under the last paragraph
		await click(el, preview(el));

		assert.equal(el.markdownMode, "edit");
		assert.equal(source(el).selectionStart, "first line\n\nsecond line".length);
	});

	it("still goes to the block that was clicked", async() =>
	{
		const el = await editor("first line\n\nsecond line");
		const blocks = preview(el).querySelectorAll("[data-source-line]");
		const restore = caretFromPoint(null);

		await click(el, blocks[blocks.length - 1]);
		restore();

		assert.equal(el.markdownMode, "edit");
		// start of the second paragraph, not the end of the text
		assert.equal(source(el).selectionStart, "first line\n\n".length);
	});

	it("leaves a link in the preview alone", async() =>
	{
		const el = await editor("see [the docs](https://example.org) here");

		await click(el, preview(el).querySelector("a"));

		assert.equal(el.markdownMode, "view", "following the link is what a link is for");
	});
});

describe("markdown preview click - split view", () =>
{
	it("moves the caret without changing the view", async() =>
	{
		const el = await editor("first line", "split");

		await click(el, preview(el));

		assert.equal(el.markdownMode, "split", "the editor is already on screen");
		assert.equal(source(el).selectionStart, "first line".length);
	});
});
