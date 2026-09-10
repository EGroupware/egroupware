/**
 * Test file for Etemplate webComponent Et2VfsSelectButton
 */
import {assert, elementUpdated, fixture, html} from '@open-wc/testing';
import {Et2VfsSelectButton} from "../Et2VfsSelectButton";
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";
import * as sinon from "sinon";

// @ts-ignore
window.egw = {
	lang: i => i + "*",
	tooltipUnbind: () => {},
	tooltipBind: () => {},
	image: () => "",
	link_app_list: () => ({}),
	langRequireApp: () => Promise.resolve(),
	getLocalStorageItem: () => null
};
// Reference to component under test
let element : Et2VfsSelectButton;

async function before()
{
	element = await fixture<Et2VfsSelectButton>(html`
        <et2-vfs-select></et2-vfs-select>
	`);

	sinon.stub(element, "egw").returns(window.egw);
	await elementUpdated(element);

	return element;
}

describe("Vfs select button widget", () =>
{
	// Setup run before each test
	beforeEach(before);

	it('is defined', () =>
	{
		assert.instanceOf(element, Et2VfsSelectButton);
	});
});

// value is an array of paths/FileInfo - no label/help-text rendering exists at all (it's a
// button, like Et2Button), it internally builds a nested <et2-vfs-select-dialog> which needs the
// same egw() surface Et2LinkTo's nested dialog construction needed (see that test file).
inputBasicTests(before, ["/home/test/file.txt"], "input", {
	skip: ["label", "help-text"],
	checkEmptyDisplay: () => {}
});
