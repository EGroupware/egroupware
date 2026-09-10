/**
 * Test file for Etemplate webComponent Et2LinkAdd
 */
import {assert, elementUpdated, fixture, html} from '@open-wc/testing';
import {Et2LinkAdd} from "../Et2LinkAdd";
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";
import * as sinon from "sinon";

// @ts-ignore
window.egw = {
	lang: i => i + "*",
	tooltipUnbind: () => {},
	image: () => ""
};
// Reference to component under test
let element : Et2LinkAdd;

async function before()
{
	element = await fixture<Et2LinkAdd>(html`
        <et2-link-add></et2-link-add>
	`);

	sinon.stub(element, "egw").returns(window.egw);
	await elementUpdated(element);

	return element;
}

describe("Link add widget", () =>
{
	// Setup run before each test
	beforeEach(before);

	it('is defined', () =>
	{
		assert.instanceOf(element, Et2LinkAdd);
	});
});

// value is a plain {app, to_app, to_id} object (despite the LinkInfo[]-looking type annotation,
// this.value.app / this.value.to_app are read directly, never this.value[0].app) - the base
// getValue()'s object-spread clone (not the array-clone branch) applies fine. No help-text
// rendering exists at all in render() despite computing hasHelpText for a CSS class.
inputBasicTests(before, {app: "infolog", to_app: "infolog", to_id: "123"}, "input", {
	skip: ["help-text"],
	checkEmptyDisplay: (element : Et2LinkAdd) =>
		assert.notOk(element._appNode?.value, "Displaying something when there is no value")
});
