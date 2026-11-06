/**
 * The hint in the empty search box says what the box accepts: only a search, or also new entries.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */
import {assert, fixture, html} from '@open-wc/testing';
import * as sinon from 'sinon';
import {Et2Select} from "../Et2Select";
import {Et2Textbox} from "../../Et2Textbox/Et2Textbox";
import {SearchableSelect, egwStub, searchNode} from "./helpers";

let keep_import : Et2Textbox = null;

// @ts-ignore
window.egw = {...egwStub};

before(async() =>
{
	const warmup = await fixture<Et2Select>(html`
        <et2-select></et2-select>`);
	assert.instanceOf(warmup, Et2Select);
	warmup.remove();
});

/**
 * The placeholder the user sees, which the test egw stub returns as the lang key plus "*"
 */
async function placeholderOf(attributes : string, options = "") : Promise<string>
{
	const searchUrl = attributes.match(/searchUrl="([^"]*)"/)?.[1];
	attributes = attributes.replace(/\s*searchUrl="[^"]*"/, "");

	const element = <SearchableSelect>await fixture(`<et2-select ${attributes}>${options}</et2-select>`);
	element.loadFromXML(element);
	sinon.stub(element, "egw").returns(window.egw);
	if(typeof searchUrl !== "undefined")
	{
		element.searchUrl = searchUrl;
	}
	await element.updateComplete;
	const box = <Et2Textbox>searchNode(element);
	assert.exists(box, "no search box to read a placeholder from");
	await box.updateComplete;
	return box.getAttribute("placeholder");
}

describe("Search box placeholder", () =>
{
	it("just says search without free entries", async() =>
	{
		assert.equal(await placeholderOf(`search="true"`), "search*");
	});

	it("says new entries are allowed when there is something to search", async() =>
	{
		assert.equal(await placeholderOf(`allowFreeEntries="true" searchUrl="test"`), "search or type to add...*");
		assert.equal(await placeholderOf(`allowFreeEntries="true" search="true"`), "search or type to add...*");
	});

	it("says select or type when there are options but no search", async() =>
	{
		assert.equal(
			await placeholderOf(`allowFreeEntries="true"`, `<option value="one">One</option>`),
			"select or type to add...*"
		);
	});

	it("counts the empty label as something to select", async() =>
	{
		// emptyLabel is shown as a selectable option, but is not part of select_options
		assert.equal(await placeholderOf(`allowFreeEntries="true" emptyLabel="No folder"`), "select or type to add...*");
	});

	it("only says type when there is nothing to select or search", async() =>
	{
		assert.equal(await placeholderOf(`allowFreeEntries="true"`), "type to add...*");
	});
});
