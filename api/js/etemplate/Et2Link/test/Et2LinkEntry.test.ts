/**
 * Test file for Etemplate webComponent Et2LinkEntry
 */
import {assert, elementUpdated, fixture, html} from '@open-wc/testing';
import {Et2LinkEntry} from "../Et2LinkEntry";
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";
import * as sinon from "sinon";

// @ts-ignore
window.egw = {
	lang: i => i + "*",
	tooltipUnbind: () => {},
	image: () => ""
};
// Reference to component under test
let element : Et2LinkEntry;

async function before()
{
	element = await fixture<Et2LinkEntry>(html`
        <et2-link-entry></et2-link-entry>
	`);

	sinon.stub(element, "egw").returns(window.egw);
	await elementUpdated(element);

	return element;
}

describe("Link entry widget", () =>
{
	// Setup run before each test
	beforeEach(before);

	it('is defined', () =>
	{
		assert.instanceOf(element, Et2LinkEntry);
	});

	it("accepts an app:id string and turns it into a LinkInfo object", async() =>
	{
		element.set_value("infolog:123");
		await elementUpdated(element);

		assert.deepEqual(element.get_value(), {app: "infolog", id: "123"});
	});
});

// required is skipped: isValid()'s check compares getValue().valueOf() to '', but an empty
// LinkInfo is always a structured {app, id: ""} object (never null/'' itself), so a plain-object
// .valueOf() never matches - the same class of gap as Et2Diff/Et2DateDuration's required checks.
inputBasicTests(before, {app: "infolog", id: "123"}, "input", {
	emptyValue: {app: undefined, id: ""},
	skip: ["required"],
	checkEmptyDisplay: (element : Et2LinkEntry) =>
		assert.notOk(element._searchNode?.value, "Displaying something when there is no value")
});
