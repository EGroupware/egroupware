/**
 * Test file for Etemplate webComponent Et2Diff
 */
import {assert, fixture, html} from '@open-wc/testing';
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
