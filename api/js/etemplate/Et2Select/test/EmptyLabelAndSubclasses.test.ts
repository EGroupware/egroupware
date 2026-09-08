/**
 * Two things with no coverage that the SearchMixin conversion puts directly at risk:
 *
 * 1. emptyLabel / empty-value handling.  Et2WidgetWithSelectMixin.getValueAsArray() keeps "" when
 *    there is an emptyLabel, and Et2Widget/SearchMixin.getValueAsArray() does not - so applying the
 *    new mixin over Et2Select silently shadows the select's version.  These tests pin the current
 *    behaviour so that shows up as a failure rather than a bug report.
 *
 * 2. Smoke tests for the Et2Select subclasses that customise search / free entries.
 *
 * See doc/ai/projects/et2select-searchmixin-removal.md §3a and §8b.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */
import {assert, fixture, html, oneEvent} from '@open-wc/testing';
import * as sinon from 'sinon';
import {Et2Select} from "../Et2Select";
import {Et2SelectTab} from "../Select/Et2SelectTab";
import {Et2SelectThumbnail} from "../Select/Et2SelectThumbnail";
import {Et2LinkSearch} from "../../Et2Link/Et2LinkSearch";
import {Et2Textbox} from "../../Et2Textbox/Et2Textbox";
import {SearchableSelect, egwStub, hasSearchUI, searchFor, searchReady, visibleOptionValues} from "./helpers";

let keep_import : Et2Textbox = null;

// @ts-ignore
window.egw = {...egwStub};

before(async() =>
{
	// Force each class to actually load & register before the first fixture uses it
	for(const [tag, cls] of <[string, any][]>[
		["et2-select", Et2Select],
		["et2-select-tab", Et2SelectTab],
		["et2-select-thumbnail", Et2SelectThumbnail],
		["et2-link-search", Et2LinkSearch]
	])
	{
		await customElements.whenDefined(tag);
		const warmup = await fixture(`<${tag}></${tag}>`);
		assert.instanceOf(warmup, cls, tag + " did not upgrade");
		warmup.remove();
	}
});

async function makeSelect(attributes : string, options = `
    <option value="one">One</option>
    <option value="two">Two</option>`) : Promise<SearchableSelect>
{
	const element = <SearchableSelect>await fixture(`<et2-select ${attributes}>${options}</et2-select>`);
	element.loadFromXML(element);
	sinon.stub(element, "egw").returns(window.egw);
	await element.updateComplete;
	return element;
}

describe("Empty value and emptyLabel", () =>
{
	it("keeps the empty value in getValueAsArray() when there is an emptyLabel", async() =>
	{
		const element = await makeSelect(`label="Empty" emptyLabel="Nothing"`);
		element.value = "";
		await element.updateComplete;

		assert.deepEqual(element.getValueAsArray(), [""],
			"emptyLabel select dropped its empty value - has getValueAsArray() been shadowed?");
	});

	it("drops the empty value when there is no emptyLabel", async() =>
	{
		const element = await makeSelect(`label="No empty"`);
		element.value = "";
		await element.updateComplete;

		assert.deepEqual(element.getValueAsArray(), [],
			"Select without an emptyLabel kept an empty value");
	});

	it("offers the empty option when there is an emptyLabel", async() =>
	{
		const element = await makeSelect(`label="Empty" emptyLabel="Nothing"`);
		element.value = "";
		await element.updateComplete;
		await searchReady(element);

		assert.include(visibleOptionValues(element), "",
			"emptyLabel option was not offered");
	});

	it("keeps the empty option through a search", async() =>
	{
		const element = await makeSelect(`label="Empty" emptyLabel="Nothing" search="true"`);
		element.value = "";
		await searchReady(element);

		await searchFor(element, "one");

		assert.equal(element.value, "", "Searching lost the empty value");
	});

	it("does not treat an empty value as a missing option", async() =>
	{
		const request = sinon.fake.returns(Promise.resolve([]));
		window.egw.request = request;

		const element = await makeSelect(`label="Empty" emptyLabel="Nothing" search="true" searchUrl="test"`);
		element.value = "";
		await element.updateComplete;
		await new Promise(resolve => setTimeout(resolve, 0));

		assert.isTrue(request.notCalled, "Went to the server to resolve an empty value");
		window.egw.request = egwStub.request;
	});
});

describe("Et2SelectTab", () =>
{
	it("allows free entries", async() =>
	{
		const element = <SearchableSelect><unknown>await fixture(`<et2-select-tab label="Tab"></et2-select-tab>`);
		sinon.stub(element, "egw").returns(window.egw);
		await element.updateComplete;

		assert.isTrue(element.allowFreeEntries, "Et2SelectTab did not turn on allowFreeEntries");
	});

	it("takes a tab name that is not a known app", async() =>
	{
		const element = <SearchableSelect><unknown>await fixture(`<et2-select-tab label="Tab"></et2-select-tab>`);
		sinon.stub(element, "egw").returns(window.egw);
		await element.updateComplete;

		element.value = "infolog-1234";
		await element.updateComplete;

		assert.equal(element.value, "infolog-1234", "Et2SelectTab lost an unknown tab value");
	});
});

describe("Et2SelectThumbnail", () =>
{
	let element : Et2SelectThumbnail & SearchableSelect;

	beforeEach(async() =>
	{
		element = <Et2SelectThumbnail & SearchableSelect>await fixture(`<et2-select-thumbnail label="Thumb"></et2-select-thumbnail>`);
		sinon.stub(element, "egw").returns(window.egw);
		await element.updateComplete;
	});

	it("has free entries and editing on, search off", async() =>
	{
		assert.isTrue(element.allowFreeEntries, "Free entries were off");
		assert.isTrue(element.editModeEnabled, "Edit mode was off");
		assert.isFalse(element.search, "Search was on");
		assert.isTrue(element.multiple, "Not multiple");
	});

	it("has no search UI", async() =>
	{
		assert.isFalse(element.searchEnabled, "Thumbnail select was searchable");
	});

	it("createFreeEntry() uses the text as the icon", async() =>
	{
		assert.isTrue(element.createFreeEntry("http://example.com/pic.png"), "createFreeEntry() refused");
		await element.updateComplete;

		const option = element.select_options.find(o => o.value == "http://example.com/pic.png");
		assert.exists(option, "Free entry was not added to the options");
		assert.equal(option.icon, "http://example.com/pic.png", "Free entry did not become an icon");
		assert.equal(option.label, "", "Thumbnail free entry should have no label");
	});

	/**
	 * Et2SelectThumbnail.createFreeEntry() used to do `this.value.push(text)`, mutating the array
	 * in place.  A push does not go through the value setter, so the thumbnail was added to the
	 * options but never to the value, and no `change` fired either.
	 */
	it("createFreeEntry() adds to both the options and the value", async() =>
	{
		element.createFreeEntry("http://example.com/pic.png");
		await element.updateComplete;

		assert.exists(element.select_options.find(o => o.value == "http://example.com/pic.png"),
			"Free entry option was expected");
		assert.include(element.value, "http://example.com/pic.png",
			"Free entry was not added to the value");
	});

	it("createFreeEntry() fires change", async() =>
	{
		const listener = oneEvent(element, "change");
		element.createFreeEntry("http://example.com/pic.png");
		await listener;

		assert.include(element.value, "http://example.com/pic.png");
	});
});

describe("Et2LinkSearch", () =>
{
	it("searches by default", async() =>
	{
		const element = <Et2LinkSearch & SearchableSelect>await fixture(`<et2-link-search label="Link"></et2-link-search>`);
		sinon.stub(element, "egw").returns(window.egw);
		await element.updateComplete;

		assert.isTrue(element.search, "Et2LinkSearch did not turn search on");
		assert.isTrue(element.searchEnabled, "Et2LinkSearch was not searchable");
		assert.isNotEmpty(element.searchUrl, "Et2LinkSearch had no searchUrl");
	});

	it("sends app and search text to the link search", async() =>
	{
		const request = sinon.fake.returns(Promise.resolve([]));
		window.egw.request = request;

		const element = <Et2LinkSearch & SearchableSelect>await fixture(`<et2-link-search label="Link" .searchOptions=${{app: "infolog"}}></et2-link-search>`);
		sinon.stub(element, "egw").returns(window.egw);
		element.searchOptions = {app: "infolog"};
		await searchReady(element);
		await searchFor(element, "find me");

		assert.isTrue(request.called, "Et2LinkSearch did not query the server");
		const args = request.firstCall.args[1];
		assert.include(args, "find me", "Search text was not sent");
		assert.include(args, "infolog", "App was not sent");

		window.egw.request = egwStub.request;
	});
});
