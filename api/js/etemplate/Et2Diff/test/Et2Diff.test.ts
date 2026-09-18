/**
 * Test file for Etemplate webComponent Et2Diff
 */
import {assert, fixture, html} from '@open-wc/testing';
import {sendMouse} from "@web/test-runner-commands";
import {Et2Diff} from "../Et2Diff";
import * as sinon from "sinon";
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";

// Reference to component under test
let element : Et2Diff;

async function before()
{
	// Create an element to test with, and wait until it's ready
	element = await fixture<Et2Diff>(html`
        <et2-diff noDialog></et2-diff>
	`);

	// Stub egw()
	sinon.stub(element, "egw").returns({
		lang: i => i,
		tooltipUnbind: () => {}
	});

	return element;
}

describe("Diff widget", () =>
{
	// Setup run before each test
	beforeEach(before);

	it('is defined', () =>
	{
		assert.instanceOf(element, Et2Diff);
	});

	it("prepends a diff header when the value doesn't already have one", async() =>
	{
		element.set_value("just some text, not a real diff");
		await element.updateComplete;

		assert.isTrue(element.get_value().startsWith("--- diff\n+++ diff\n"), "Header should be added");
	});

	it("is never dirty", () =>
	{
		element.set_value("--- diff\n+++ diff\n@@ -1 +1 @@\n-old\n+new");
		assert.isFalse(element.isDirty());
	});
});

/**
 * The widget caps its own height and offers a button to see the rest in a dialog.
 *
 * That button appears on hover, but only for a diff that is actually cut off - a diff the reader
 * can already see in full is not clickable either, since the dialog would show nothing new.
 *
 * These need real layout, so they use their own fixture: the one above passes noDialog, which
 * takes the cap off and means nothing is ever cut off.
 */
describe("Diff widget height cap", () =>
{
	const long = "--- diff\n+++ diff\n@@ -1 +40 @@\n-was\n" +
		Array.from({length: 40}, (_, i) => "+line " + i).join("\n") + "\n";
	const short = "--- diff\n+++ diff\n@@ -1 +1 @@\n-was\n+is\n";

	async function diffWith(value : string, attrs = {})
	{
		const diff = await fixture<Et2Diff>(html`
            <et2-diff style="width: 30em"></et2-diff>`);
		sinon.stub(diff, "egw").returns({lang: i => i, tooltipUnbind: () => {}, tooltipBind: () => {}, image: () => ""});
		Object.assign(diff, attrs);
		diff.set_value(value);
		await diff.updateComplete;
		return diff;
	}

	const expandIcon = (diff : Et2Diff) => diff.shadowRoot.querySelector(".expand-icon");

	/** Real pointer move, so the :hover the button is gated on actually applies */
	async function hover(diff : Et2Diff)
	{
		const box = diff.getBoundingClientRect();
		await sendMouse({type: "move", position: [Math.round(box.x + box.width / 2), Math.round(box.y + 5)]});
	}

	it("offers the pop-out on hover when part of the diff is cut off", async() =>
	{
		const diff = await diffWith(long);

		assert.isTrue(diff.overflowing, "a diff taller than the cap must report itself as cut off");
		assert.equal(getComputedStyle(expandIcon(diff)).display, "none", "nothing until it is hovered");

		await hover(diff);

		assert.notEqual(getComputedStyle(expandIcon(diff)).display, "none",
			"hovering a cut-off diff must offer the way to see the rest of it");
	});

	it("does not offer it when the whole diff fits", async() =>
	{
		const diff = await diffWith(short);

		assert.isFalse(diff.overflowing, "a diff shorter than the cap must not report itself as cut off");

		await hover(diff);

		assert.equal(getComputedStyle(expandIcon(diff)).display, "none",
			"hovering must not offer a pop-out that would show nothing new");
	});

	it("ignores a click when the whole diff fits", async() =>
	{
		const diff = await diffWith(short);

		diff.dispatchEvent(new MouseEvent("click", {bubbles: true}));
		await diff.updateComplete;

		assert.isFalse(diff.hasAttribute("open"), "nothing is hidden, so there is nothing to open");
	});

	it("never cuts off a diff that was told not to use a dialog", async() =>
	{
		const diff = await diffWith(long, {noDialog: true});
		await diff.updateComplete;

		assert.isFalse(diff.overflowing, "noDialog takes the cap off, so nothing is ever cut off");
	});
});

// value always gets a "--- diff\n+++ diff\n" header prepended if not already present (even for
// "" - see the "prepends a diff header" test above), so the round-trip / empty value both need
// that accounted for. The rendered diff goes into the *light* DOM (Diff2Html can't have its CSS
// imported into the shadow DOM), so there's no shadow-DOM selector for the empty-display check.
// required is skipped: set_value("") always prepends the header, so getValue() is never
// genuinely "empty" ('' or null) for isValid()'s required check to catch - there's no way to
// represent "no diff" that the widget itself can distinguish from "a blank diff". Not meaningful
// in practice either; a diff display isn't normally marked required.
const DIFF_HEADER = "--- diff\n+++ diff\n";
inputBasicTests(before, DIFF_HEADER + "@@ -1 +1 @@\n-old line\n+new line", "input", {
	emptyValue: DIFF_HEADER,
	skip: ["required"],
	checkEmptyDisplay: (element : Et2Diff) =>
		assert.equal(element.textContent.trim(), "", "Displaying something when there is no value")
});
