/**
 * Test file for locating a textarea's selection on screen
 *
 * Contract under test: textareaSelectionRect() returns a client rect over the current selection
 * of a real, rendered textarea, and null when there is nothing to point at.  This is what anchors
 * the markdown format popup, so the rect has to sit inside the textarea and move the way the
 * selection moves.
 *
 * Setup: a real textarea in a fixture with a monospace font and a known size, so line and column
 * changes are measurable.  A hidden mirror div is created against document.body by the module.
 *
 * Pass criteria: relative, not absolute.  Exact pixel positions depend on the browser's font
 * rendering and would be flaky, so the assertions check containment inside the textarea and the
 * direction of movement - a later line is lower, a later column is further right.
 *
 * A failure means the mirror div has stopped matching the textarea's metrics, which is exactly
 * the drift that makes the popup point at the wrong word.
 */
import {assert, fixture, html} from '@open-wc/testing';
import {selectionVirtualElement, textareaSelectionRect} from "../textareaSelectionRect";

const TEXT = "line one here\nline two here\nline three here";

async function makeTextarea(): Promise<HTMLTextAreaElement>
{
	const el: HTMLTextAreaElement = await fixture(html`
        <textarea
                style="font-family: monospace; font-size: 14px; line-height: 20px;
                       width: 400px; height: 120px; padding: 4px; border: 1px solid black;"
        ></textarea>`);
	el.value = TEXT;
	return el;
}

describe("textareaSelectionRect()", () =>
{
	it("returns null when nothing is selected", async() =>
	{
		const el = await makeTextarea();
		el.setSelectionRange(3, 3);
		assert.isTrue(textareaSelectionRect(el) === null);
	});

	it("returns null for a missing or detached textarea", async() =>
	{
		assert.isTrue(textareaSelectionRect(null) === null);

		const orphan = document.createElement("textarea");
		orphan.value = TEXT;
		orphan.setSelectionRange(0, 4);
		assert.isTrue(textareaSelectionRect(orphan) === null);
	});

	it("puts the rect inside the textarea", async() =>
	{
		const el = await makeTextarea();
		el.setSelectionRange(0, 4);      // "line" on the first line

		const rect = textareaSelectionRect(el);
		const box = el.getBoundingClientRect();

		assert.isTrue(rect.width > 0, "rect has width");
		assert.isTrue(rect.left >= box.left - 1 && rect.right <= box.right + 1, "inside horizontally");
		assert.isTrue(rect.top >= box.top - 1 && rect.bottom <= box.bottom + 1, "inside vertically");
	});

	it("moves down for a later line", async() =>
	{
		const el = await makeTextarea();

		el.setSelectionRange(0, 4);
		const first = textareaSelectionRect(el);

		el.setSelectionRange(TEXT.indexOf("line three"), TEXT.indexOf("line three") + 4);
		const third = textareaSelectionRect(el);

		assert.isTrue(third.top > first.top, `third line (${third.top}) below first (${first.top})`);
	});

	it("moves right for a later column on the same line", async() =>
	{
		const el = await makeTextarea();

		el.setSelectionRange(0, 4);      // "line"
		const start = textareaSelectionRect(el);

		el.setSelectionRange(5, 8);      // "one"
		const later = textareaSelectionRect(el);

		assert.isTrue(later.left > start.left, `"one" (${later.left}) right of "line" (${start.left})`);
		assert.isTrue(Math.abs(later.top - start.top) < 2, "still on the same line");
	});

	it("exposes a virtual element sl-popup can anchor to", async() =>
	{
		const el = await makeTextarea();
		el.setSelectionRange(0, 4);

		const anchor = selectionVirtualElement(el);
		assert.isTrue(typeof anchor.getBoundingClientRect === "function");
		assert.isTrue(anchor.getBoundingClientRect().width > 0);

		// re-read after the selection moves - the popup relies on this staying live
		el.setSelectionRange(TEXT.indexOf("line three"), TEXT.indexOf("line three") + 4);
		assert.isTrue(anchor.getBoundingClientRect().top > 0);
	});
});
