/**
 * Tests for Et2SelectReadonly - the 580-line file behind 16 registered `*_ro` tags.
 *
 * This widget is what nextmatch renders in almost every readonly select column, so it is both the
 * most-instantiated select in the product and, until now, one of the least covered.  It is also
 * the one that implements `et2_IDetachedDOM`, the interface nextmatch uses to reuse a single
 * widget instance across rows - if that contract breaks, rows show each other's values rather
 * than erroring.
 *
 * Behaviour under test:
 * - value normalisation: CSV strings split, single strings/numbers wrap, arrays pass through
 * - rendering: only values with a matching option are shown, unless `allowFreeEntries`
 * - `emptyLabel` prepends an empty option
 * - `select_options` accepts the legacy key => value map (and says so on the console)
 * - labels are translated unless `noLang`
 * - the deprecated `set_value`/`get_value`/`getValue` aliases still work
 * - the `et2_IDetachedDOM` contract nextmatch depends on
 *
 * Setup strategy:
 * The shared `egwStub` is installed globally before any element is created.  Its `lang()` appends
 * "*", which makes translated-vs-untranslated visible in assertions rather than something the
 * test has to take on faith.  Rendered output is read from the shadow root's `<li>` list.
 *
 * Pass criteria:
 * Explicit assertions on `value`/`select_options` and on the rendered list items.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */
import {assert, fixture} from "@open-wc/testing";
import {egwStub} from "./helpers";

const callableEgw = function() { return egwStub; };
Object.assign(callableEgw, egwStub);
// @ts-ignore
window.egw = callableEgw;

import "../Select/Et2SelectReadonly";
import {Et2SelectReadonly} from "../Select/Et2SelectReadonly";

const OPTIONS = [
	{value: "1", label: "One"},
	{value: "2", label: "Two"},
	{value: "3", label: "Three"}
];

async function readonlySelect(attributes = "", options : any[] = OPTIONS)
{
	const element = <Et2SelectReadonly>await fixture(`<et2-select_ro ${attributes}></et2-select_ro>`);
	element.select_options = options.map(o => ({...o}));
	await element.updateComplete;
	return element;
}

const rendered = (element : Et2SelectReadonly) =>
	Array.from(element.shadowRoot?.querySelectorAll("li") ?? []).map(li => li.textContent?.trim());

describe("Et2SelectReadonly", () =>
{
	it("upgrades", async() =>
	{
		const element = await readonlySelect();
		assert.instanceOf(element, Et2SelectReadonly, "et2-select_ro did not upgrade");
	});

	describe("value normalisation", () =>
	{
		it("splits a CSV string, because that is what the server still sends", async() =>
		{
			const element = await readonlySelect();
			element.value = "1,3";
			await element.updateComplete;

			assert.deepEqual(element.value, ["1", "3"], "a CSV value must become an array");
		});

		it("wraps a single string", async() =>
		{
			const element = await readonlySelect();
			element.value = "2";
			await element.updateComplete;

			assert.deepEqual(element.value, ["2"], "a single value is rendered through the same array path");
		});

		it("wraps and stringifies a number", async() =>
		{
			const element = await readonlySelect();
			(<any>element).value = 2;
			await element.updateComplete;

			assert.deepEqual(element.value, ["2"], "numeric values must be stringified to match option values");
		});

		it("passes an array through untouched", async() =>
		{
			const element = await readonlySelect();
			element.value = ["1", "2"];
			await element.updateComplete;

			assert.deepEqual(element.value, ["1", "2"], "an array value is already in the wanted shape");
			assert.deepEqual(element.getValueAsArray(), ["1", "2"], "getValueAsArray() should agree");
		});
	});

	describe("rendering", () =>
	{
		it("renders the label of each matching option, in value order", async() =>
		{
			const element = await readonlySelect();
			element.value = "3,1";
			await element.updateComplete;

			assert.deepEqual(rendered(element), ["Three*", "One*"], "labels should follow the value order");
		});

		it("renders nothing for a value with no matching option", async() =>
		{
			const element = await readonlySelect();
			element.value = "99";
			await element.updateComplete;

			assert.deepEqual(
				rendered(element),
				[],
				"an unknown value is dropped rather than shown raw - this is what hides stale ids"
			);
		});

		it("renders the raw value when allowFreeEntries is set", async() =>
		{
			const element = await readonlySelect('allowFreeEntries');
			element.value = "99";
			await element.updateComplete;

			assert.deepEqual(rendered(element), ["99*"], "allowFreeEntries should show the value itself");
		});

		it("skips translation when noLang is set", async() =>
		{
			const element = await readonlySelect('noLang');
			element.value = "1";
			await element.updateComplete;

			assert.deepEqual(rendered(element), ["One"], "noLang means the label is already in the right language");
		});

		it("renders its label alongside the value", async() =>
		{
			const element = await readonlySelect('label="Category"');
			element.value = "1";
			await element.updateComplete;

			assert.include(
				element.shadowRoot?.querySelector("label")?.textContent,
				"Category",
				"the widget label should still render"
			);
		});
	});

	describe("select_options", () =>
	{
		it("prepends an empty option when emptyLabel is set", async() =>
		{
			const element = await readonlySelect();
			element.emptyLabel = "- none -";
			await element.updateComplete;

			assert.deepEqual(
				(<any[]>element.select_options)[0],
				{value: "", label: "- none -"},
				"emptyLabel should become the first option, so an empty value renders as something"
			);
		});

		it("accepts the legacy key => value map and warns about it", async() =>
		{
			const warnings : any[] = [];
			const originalWarn = console.warn;
			console.warn = (...args : any[]) => warnings.push(args);
			try
			{
				const element = <Et2SelectReadonly>await fixture(`<et2-select_ro id="legacy"></et2-select_ro>`);
				(<any>element).select_options = {"1": "One", "2": "Two"};
				await element.updateComplete;

				assert.deepEqual(
					element.select_options,
					[{value: "1", label: "One"}, {value: "2", label: "Two"}],
					"a key => value map must be converted to the option array shape"
				);
				assert.isNotEmpty(warnings, "the legacy shape should be reported so callers get fixed");
			}
			finally
			{
				console.warn = originalWarn;
			}
		});

		it("still accepts the deprecated set_select_options()", async() =>
		{
			const element = await readonlySelect("", []);
			element.set_select_options([{value: "7", label: "Seven"}]);
			element.value = "7";
			await element.updateComplete;

			assert.deepEqual(rendered(element), ["Seven*"], "the deprecated setter must keep working");
		});
	});

	describe("deprecated value aliases", () =>
	{
		it("set_value()/get_value()/getValue() all go through value", async() =>
		{
			const element = await readonlySelect();
			element.set_value("2");
			await element.updateComplete;

			assert.deepEqual(element.value, ["2"], "set_value() should assign value");
			assert.deepEqual(element.get_value(null), ["2"], "get_value() should read value");
			assert.deepEqual(element.getValue(null), ["2"], "getValue() should read value");
		});
	});

	describe("et2_IDetachedDOM contract", () =>
	{
		/**
		 * Nextmatch reuses one widget instance for every row of a readonly column, pushing each
		 * row's data through setDetachedAttributes().  If the attribute list or the setter drifts,
		 * rows render the previous row's value instead of failing - so this is pinned explicitly.
		 */
		it("declares the attributes nextmatch pushes per row", async() =>
		{
			const element = await readonlySelect();
			const attrs : string[] = [];
			element.getDetachedAttributes(attrs);

			assert.includeMembers(
				attrs,
				["id", "value", "class", "statustext", "emptyLabel"],
				"nextmatch needs every per-row attribute declared here"
			);
		});

		it("returns itself as the detached node", async() =>
		{
			const element = await readonlySelect();
			assert.deepEqual(element.getDetachedNodes(), [<any>element], "the widget is its own detached node");
		});

		it("applies per-row values through setDetachedAttributes()", async() =>
		{
			const element = await readonlySelect();
			element.setDetachedAttributes([<any>element], {value: "2", id: "row_2"});
			await element.updateComplete;

			assert.deepEqual(element.value, ["2"], "the row value should be applied");
			assert.equal(element.id, "row_2", "the row id should be applied");
			assert.deepEqual(rendered(element), ["Two*"], "and the row should re-render with it");
		});
	});
});
