/**
 * Tests for allowFreeEntries - letting the value contain things that are not options.
 *
 * This had no coverage at all, despite mail compose, Et2SelectTab and Et2SelectThumbnail all
 * depending on it, and despite it being scheduled to move out of SearchMixin into its own
 * Et2Select-side mixin.  See doc/ai/projects/et2select-searchmixin-removal.md §8b.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */
import {assert, elementUpdated, fixture, html, oneEvent} from '@open-wc/testing';
import * as sinon from 'sinon';
import {Et2Select} from "../Et2Select";
import {Et2Textbox} from "../../Et2Textbox/Et2Textbox";
import {SearchableSelect, egwStub, searchFor, searchKey, searchReady, searchValue, tagsReady, tagValues, typeSearch, visibleOptionValues} from "./helpers";

let keep_import : Et2Textbox = null;

// @ts-ignore
window.egw = egwStub;

/**
 * Make sure Et2Select is actually loaded before the first fixture, otherwise the element
 * does not upgrade and none of its methods exist.
 */
before(async() =>
{
	const warmup = await fixture<Et2Select>(html`
        <et2-select></et2-select>`);
	assert.instanceOf(warmup, Et2Select);
	warmup.remove();
});

async function makeSelect(attributes : string) : Promise<SearchableSelect>
{
	const element = <SearchableSelect>await fixture(`
        <et2-select ${attributes}>
            <option value="one">One</option>
            <option value="two">Two</option>
        </et2-select>`);
	element.loadFromXML(element);
	sinon.stub(element, "egw").returns(window.egw);
	await searchReady(element);
	return element;
}

describe("Free entries", () =>
{
	let element : SearchableSelect;

	beforeEach(async() =>
	{
		element = await makeSelect(`label="Free" allowFreeEntries="true" multiple="true"`);
	});

	it("is off by default", async() =>
	{
		const plain = await makeSelect(`label="Plain"`);
		assert.isFalse(plain.allowFreeEntries, "allowFreeEntries defaulted to on");
	});

	it("createFreeEntry() puts the text in the value", async() =>
	{
		assert.isTrue(element.createFreeEntry("brand new"), "createFreeEntry() rejected the text");
		await element.updateComplete;

		assert.include(element.value, "brand new", "Free entry did not end up in the value");
	});

	it("createFreeEntry() offers the text as an option", async() =>
	{
		element.createFreeEntry("brand new");
		await element.updateComplete;

		assert.include(visibleOptionValues(element), "brand new", "Free entry was not added to the options");
	});

	it("createFreeEntry() fires change", async() =>
	{
		const listener = oneEvent(element, "change");
		element.createFreeEntry("brand new");
		await listener;

		assert.include(element.value, "brand new");
	});

	it("rejects empty text", async() =>
	{
		assert.isFalse(element.createFreeEntry(""), "Accepted an empty free entry");
		assert.isFalse(element.createFreeEntry(null), "Accepted a null free entry");
		assert.notInclude(element.value, "", "Empty free entry ended up in the value");
	});

	/**
	 * createFreeEntry() used to trim the text for the option it creates but store the untrimmed
	 * text as the value.  The two then disagreed, and since a free entry carries isMatch:false
	 * _optionTemplate() only renders it while it is in the value - so the user got a value with
	 * no visible option or tag behind it.  Both sides are trimmed now.
	 */
	it("trims whitespace off both the option and the value", async() =>
	{
		element.createFreeEntry("  padded  ");
		await element.updateComplete;

		assert.include(element.value, "padded", "Free entry value was not trimmed");
		assert.notInclude(element.value, "  padded  ", "Untrimmed text is still in the value");
		assert.include(visibleOptionValues(element), "padded", "Trimmed free entry was not offered as an option");
	});

	it("shows a whitespace-padded free entry as a tag", async() =>
	{
		element.createFreeEntry("  padded  ");
		await element.updateComplete;
		await tagsReady(element);

		assert.include(tagValues(element), "padded", "Padded free entry got no tag");
	});

	it("does not double-add the same entry", async() =>
	{
		element.createFreeEntry("twice");
		await element.updateComplete;
		element.createFreeEntry("twice");
		await element.updateComplete;

		const matches = visibleOptionValues(element).filter(v => v == "twice");
		assert.lengthOf(matches, 1, "Free entry was added twice");
		assert.lengthOf(element.value.filter(v => v == "twice"), 1, "Free entry was in the value twice");
	});

	it("does not duplicate an existing option", async() =>
	{
		element.createFreeEntry("one");
		await element.updateComplete;

		const matches = visibleOptionValues(element).filter(v => v == "one");
		assert.lengthOf(matches, 1, "Free entry duplicated an existing option");
	});

	it("keeps earlier entries when adding another", async() =>
	{
		element.createFreeEntry("first");
		await element.updateComplete;
		element.createFreeEntry("second");
		await element.updateComplete;

		assert.includeMembers(element.value, ["first", "second"], "Adding a second free entry lost the first");
	});

	it("shows free entries as tags", async() =>
	{
		element.createFreeEntry("tagged");
		await element.updateComplete;
		await tagsReady(element);

		assert.include(tagValues(element), "tagged", "Free entry did not get a tag");
	});

	for(const key of ["Enter", ",", "Tab"])
	{
		it(`"${key}" turns what was typed into an entry`, async() =>
		{
			typeSearch(element, "typed entry");
			searchKey(element, key);
			await element.updateComplete;

			assert.include(element.value, "typed entry", `"${key}" did not create a free entry`);
		});
	}

	it("clears the search box after creating an entry", async() =>
	{
		typeSearch(element, "typed entry");
		searchKey(element, "Enter");
		await element.updateComplete;

		assert.equal(searchValue(element), "", "Search box still held the text after creating the entry");
	});

	it("does not create an entry for a key that is not a tag break", async() =>
	{
		typeSearch(element, "typed entry");
		searchKey(element, "a");
		await element.updateComplete;

		assert.notInclude(element.value, "typed entry", "A non-break key created a free entry");
	});

	it("does not create entries when allowFreeEntries is off", async() =>
	{
		const plain = await makeSelect(`label="Plain" search="true" multiple="true"`);

		typeSearch(plain, "typed entry");
		searchKey(plain, "Enter");
		await plain.updateComplete;

		assert.notInclude(plain.value ?? [], "typed entry", "Created a free entry with allowFreeEntries off");
	});
});

describe("Free entries, single value", () =>
{
	let element : SearchableSelect;

	beforeEach(async() =>
	{
		element = await makeSelect(`label="Free" allowFreeEntries="true"`);
	});

	it("replaces the value rather than appending", async() =>
	{
		element.createFreeEntry("first");
		await element.updateComplete;
		element.createFreeEntry("second");
		await element.updateComplete;

		assert.equal(element.value, "second", "Single-value free entry did not replace the previous one");
	});

	it("takes a free entry over a real option", async() =>
	{
		element.value = "one";
		await element.updateComplete;

		element.createFreeEntry("not an option");
		await element.updateComplete;

		assert.equal(element.value, "not an option");
	});
});

describe("Free entries and search together", () =>
{
	let element : SearchableSelect;

	beforeEach(async() =>
	{
		element = await makeSelect(`label="Both" allowFreeEntries="true" search="true" multiple="true"`);
	});

	it("keeps a free entry through a later search", async() =>
	{
		element.createFreeEntry("kept");
		await element.updateComplete;

		await searchFor(element, "one");

		assert.include(element.value, "kept", "Searching dropped an already-selected free entry");
	});

	it("works with search off", async() =>
	{
		// Et2SelectThumbnail does exactly this: free entries, no search
		const noSearch = await makeSelect(`label="No search" allowFreeEntries="true" search="false" multiple="true"`);

		assert.isTrue(noSearch.createFreeEntry("no search needed"), "Free entry needs search enabled");
		await noSearch.updateComplete;
		assert.include(noSearch.value, "no search needed");
	});
});
