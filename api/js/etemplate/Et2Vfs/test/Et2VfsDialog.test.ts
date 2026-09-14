import {assert, elementUpdated, fixture, html} from '@open-wc/testing';
import * as sinon from 'sinon';
import {Et2VfsSelectDialog} from "../Et2VfsSelectDialog";

/**
 * Test file for Etemplate webComponent Et2VfsSelectDialog
 *
 */
window.egw = {
	ajaxUrl: () => "",
	app: () => "addressbook",
	decodePath: (_path : string) => _path,
	encodePath: (_path : string) => _path,
	image: () => "data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxNS4wLjAsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkViZW5lXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4Ig0KCSB3aWR0aD0iMzJweCIgaGVpZ2h0PSIzMnB4IiB2aWV3Qm94PSIwIDAgMzIgMzIiIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDMyIDMyIiB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBmaWxsPSIjNjk2OTY5IiBkPSJNNi45NDMsMjguNDUzDQoJYzAuOTA2LDAuNzY1LDIuMDk3LDEuMTI3LDMuMjg2LDEuMTA5YzAuNDMsMC4wMTQsMC44NTItMC4wNjgsMS4yNjUtMC4yMDdjMC42NzktMC4xOCwxLjMyOC0wLjQ1LDEuODY2LTAuOTAyTDI5LjQwMywxNC45DQoJYzEuNzcyLTEuNDk4LDEuNzcyLTMuOTI1LDAtNS40MjJjLTEuNzcyLTEuNDk3LTQuNjQ2LTEuNDk3LTYuNDE4LDBMMTAuMTE5LDIwLjM0OWwtMi4zODktMi40MjRjLTEuNDQtMS40NTctMy43NzItMS40NTctNS4yMTIsMA0KCWMtMS40MzgsMS40Ni0xLjQzOCwzLjgyNSwwLDUuMjgxQzIuNTE4LDIzLjIwNiw1LjQ3NCwyNi45NDcsNi45NDMsMjguNDUzeiIvPg0KPC9zdmc+DQo=",
	jsonq: () => Promise.resolve({}),
	lang: i => i + "*",
	link: i => i,
	link_app_list: () => ({}),
	langRequireApp: () => {},
	preference: i => "",
	tooltipUnbind: () => {},
	webserverUrl: "",
};

let element : Et2VfsSelectDialog;

async function before()
{
	// Create an element to test with, and wait until it's ready
	// @ts-ignore
	element = await fixture<Et2VfsSelectDialog>(html`
        <et2-vfs-select-dialog title="I'm a vfs select dialog">
        </et2-vfs-select-dialog>
	`);

	// Stub egw()
	sinon.stub(element, "egw").returns(window.egw);
	await elementUpdated(element);

	return element;
}

/** Cannot use automatic testing as Et2Dialog still uses old widgets, which break all the includes
 *

describe("Vfs Select Dialog widget basics", () =>
{

	// Setup run before each test
	beforeEach(before);

	// Make sure it works
	it('is defined', () =>
	{
		assert.instanceOf(element, Et2VfsSelectDialog);
	});

	it('has a title', async() =>
	{
		element.title = "Title set";
		await elementUpdated(element);

		assert.equal(element.querySelector("[slot='title']").textContent, "Label set");
	});

	it("Search gets focus when widget is focused", async() =>
	{
		element.focus();
		await elementUpdated(element);
		assert.equal(element.shadowRoot.activeElement, element._searchNode, "Search did not get focus when widget got focus");
	});
});
 */

// searchMatch() is a plain method - it can be exercised on a detached element, without the
// fixture()/render() path the (commented out) suite above trips over.
describe("Et2VfsSelectDialog.searchMatch()", () =>
{
	let dialog : any;

	beforeEach(() =>
	{
		dialog = new Et2VfsSelectDialog();
		dialog.egw = () => window.egw;
	});

	it("matches a file against an active mime filter", () =>
	{
		const option = {value: "/home/user/report.pdf", label: "report.pdf", mime: "application/pdf"};

		assert.isTrue(dialog.searchMatch("report", {mime: "application/pdf"}, option));
		assert.isFalse(dialog.searchMatch("report", {mime: "image/"}, option));
	});

	// the filemanager clipboard (Et2LinkPasteDialog) passes row data through verbatim, and the
	// server sends mime === false for a file it could not resolve - it must not match, not throw
	for(const [label, mime] of [["false", false], ["missing", undefined]])
	{
		it(`does not match a file whose mime-type is ${label}`, () =>
		{
			const option = {value: "/home/user/dangling.odt", label: "dangling.odt", mime};

			assert.isFalse(dialog.searchMatch("dangling", {mime: "application/vnd.oasis.opendocument.text"}, option));
		});
	}

	it("ignores the mime-type when no filter is set", () =>
	{
		const option = {value: "/home/user/dangling.odt", label: "dangling.odt", mime: false};

		assert.isTrue(dialog.searchMatch("dangling", {}, option));
	});
});
