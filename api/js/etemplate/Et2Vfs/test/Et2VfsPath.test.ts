import {assert, elementUpdated, fixture, html} from '@open-wc/testing';
import * as sinon from 'sinon';
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";
import {Et2VfsPath} from "../Et2VfsPath";

/**
 * Test file for Etemplate webComponent VfsPath
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
	preference: i => "",
	tooltipUnbind: () => {},
	webserverUrl: "",
};

let element : Et2VfsPath;

async function before()
{
	// Create an element to test with, and wait until it's ready
	// @ts-ignore
	element = await fixture<Et2VfsPath>(html`
        <et2-vfs-path label="I'm a vfs path">
        </et2-vfs-path>
	`);

	// Stub egw()
	sinon.stub(element, "egw").returns(window.egw);
	await elementUpdated(element);

	return element;
}

describe("Path widget basics", () =>
{
	// Setup run before each test
	beforeEach(before);

	// Make sure it works
	it('is defined', () =>
	{
		assert.instanceOf(element, Et2VfsPath);
	});

	it('has a label', async() =>
	{
		element.set_label("Label set");
		await elementUpdated(element);

		assert.equal(element.querySelector("[slot='label']").textContent, "Label set");
	});

	it("textbox gets focus when widget is focused", async() =>
	{
		element.focus();
		await elementUpdated(element);
		assert.equal(element.shadowRoot.activeElement, element._edit, "Editable path did not get focus when widget got focus");
	});

	it("blurring widget accepts current text", async() =>
	{
		const value = "/home/test/directory";
		element.focus();
		await elementUpdated(element);
		element._edit.value = value;
		element.blur();
		await elementUpdated(element);

		assert.equal(element.value, value, "Path was not accepted on blur");
	});
});

inputBasicTests(async() =>
{
	const element = await before();
	element.noLang = true;
	return element
}, "/home/test", "sl-breadcrumb");