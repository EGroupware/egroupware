/**
 * Smoke and behaviour tests for the 17 Et2SelectReadonly subclasses (`et2-select-*_ro`).
 *
 * Most of them are a handful of lines overriding `find_select_options()`, which is exactly why
 * they are worth a cheap parameterised test: nothing in the product exercises them until a user
 * opens the list that uses one, and a constructor that throws or an option list that comes back
 * empty looks identical to "this row has no value".  Each is used in shipped templates, several
 * hundred times in total.
 *
 * Behaviour under test:
 * - every `*_ro` tag upgrades to a real Et2SelectReadonly and renders a matching option
 * - none of them throws while constructing, connecting or rendering
 * - Et2SelectDayOfWeekReadonly expands a packed bitmask into individual days
 * - Et2SelectPercentReadonly builds its 0..100 list in its constructor
 * - Et2SelectBoolReadonly resolves yes/no through find_select_options()
 * - Et2SelectCategoryReadonly flattens hierarchical categories, so a child category still renders
 *
 * Setup strategy:
 * The shared `egwStub` is extended with the account entry points (`link_title`, `accounts`) that
 * the account variant reaches for, and installed globally before the first element is created.
 * Subclasses that would fetch from the server only do so from `transformAttributes()`, which a
 * plain `fixture()` never calls - so the smoke test supplies options directly and stays offline.
 * Where a subclass's own option-building is the point, `find_select_options()` is invoked
 * explicitly.
 *
 * Pass criteria:
 * Explicit assertions on the rendered `<li>` list and on `select_options`/`getValueAsArray()`.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */
import {assert, fixture} from "@open-wc/testing";
import {egwStub} from "./helpers";

const stub = {
	...egwStub,
	// The account variant resolves ids it has no option for
	link_title: (_app : string, id : string) => Promise.resolve("Account " + id),
	accounts: () => Promise.resolve([])
};
const callableEgw = function() { return stub; };
Object.assign(callableEgw, stub);
// @ts-ignore
window.egw = callableEgw;

import "../Select/Et2SelectReadonly";
import {
	Et2SelectBoolReadonly,
	Et2SelectCategoryReadonly,
	Et2SelectDayOfWeekReadonly,
	Et2SelectPercentReadonly,
	Et2SelectReadonly
} from "../Select/Et2SelectReadonly";

/**
 * Every readonly select tag registered by Et2SelectReadonly.ts.
 * Keep in sync with the customElements.define() calls at the bottom of that file - a tag added
 * there and not here is a tag nothing tests.
 */
const READONLY_TAGS = [
	"et2-select_ro",
	"et2-select-account_ro",
	"et2-select-app_ro",
	"et2-select-bitwise_ro",
	"et2-select-bool_ro",
	"et2-select-cat_ro",
	"et2-select-percent_ro",
	"et2-select-country_ro",
	"et2-select-day_ro",
	"et2-select-dow_ro",
	"et2-select-hour_ro",
	"et2-select-month_ro",
	"et2-select-number_ro",
	"et2-select-priority_ro",
	"et2-select-state_ro",
	"et2-select-timezone_ro",
	"et2-select-year_ro",
	"et2-select-lang_ro"
];

const rendered = (element : any) =>
	Array.from(element.shadowRoot?.querySelectorAll("li") ?? []).map((li : any) => li.textContent?.trim());

describe("Et2SelectReadonly subclasses", () =>
{
	describe("every registered *_ro tag", () =>
	{
		READONLY_TAGS.forEach(tag =>
		{
			it(`${tag} upgrades and renders a matching option`, async() =>
			{
				const element = <any>await fixture(`<${tag} noLang></${tag}>`);

				assert.instanceOf(element, Et2SelectReadonly, `${tag} did not upgrade to Et2SelectReadonly`);

				element.select_options = [{value: "1", label: "One"}];
				element.value = "1";
				await element.updateComplete;

				assert.deepEqual(rendered(element), ["One"], `${tag} should render the matching option's label`);
			});
		});

		it("names only tags that are really registered", () =>
		{
			// Catches a tag renamed or dropped in Et2SelectReadonly.ts: the list above would still
			// "pass" its smoke test by upgrading to nothing, so check registration explicitly.
			const unregistered = READONLY_TAGS.filter(tag => !customElements.get(tag));
			assert.isEmpty(unregistered, "READONLY_TAGS names tags nothing registers: " + unregistered.join(", "));
			assert.sameMembers(READONLY_TAGS, [...new Set(READONLY_TAGS)], "duplicate tag in READONLY_TAGS");
			// A reminder rather than a real guard - a *new* tag can only be noticed by a human
			// adding it here.  Bump the count deliberately when that happens.
			assert.lengthOf(READONLY_TAGS, 18, "update READONLY_TAGS when Et2SelectReadonly.ts registers another tag");
		});
	});

	describe("Et2SelectPercentReadonly", () =>
	{
		it("builds its 0..100 list without being asked", async() =>
		{
			const element = <Et2SelectPercentReadonly>await fixture(`<et2-select-percent_ro></et2-select-percent_ro>`);
			await element.updateComplete;

			assert.deepEqual(
				(<any[]>element.select_options).map(o => "" + o.value),
				["0", "10", "20", "30", "40", "50", "60", "70", "80", "90", "100"],
				"percent options are built in the constructor, not fetched"
			);
		});

		it("renders a percentage value", async() =>
		{
			const element = <Et2SelectPercentReadonly>await fixture(`<et2-select-percent_ro noLang></et2-select-percent_ro>`);
			element.value = "50";
			await element.updateComplete;

			assert.deepEqual(rendered(element), ["50%"], "a percent value should render with its unit");
		});
	});

	describe("Et2SelectBoolReadonly", () =>
	{
		it("resolves yes/no through find_select_options()", async() =>
		{
			const element = <Et2SelectBoolReadonly>await fixture(`<et2-select-bool_ro noLang></et2-select-bool_ro>`);
			// find_select_options() is normally called from transformAttributes()
			(<any>element).find_select_options({});
			// value is declared protected on the mixin interface, but is the widget's public API
			(<any>element).value = "1";
			await element.updateComplete;

			assert.deepEqual(rendered(element), ["yes"], "1 should render as yes");

			(<any>element).value = "0";
			await element.updateComplete;
			assert.deepEqual(rendered(element), ["no"], "0 should render as no");
		});
	});

	describe("Et2SelectDayOfWeekReadonly", () =>
	{
		it("expands a packed bitmask into the individual days", async() =>
		{
			const element = <Et2SelectDayOfWeekReadonly>await fixture(`<et2-select-dow_ro noLang></et2-select-dow_ro>`);
			element.select_options = [
				{value: "1", label: "Monday"},
				{value: "2", label: "Tuesday"},
				{value: "4", label: "Wednesday"},
				{value: "8", label: "Thursday"}
			];
			// The setter wraps a plain string into an array, which getValueAsArray() passes
			// through - the bitmask path is reached by assigning the packed value directly.
			(<any>element).__value = "7";
			await element.updateComplete;

			assert.deepEqual(
				element.getValueAsArray(),
				["1", "2", "4"],
				"every day whose bit is set must be listed"
			);
		});

		it("passes an already-expanded array through", async() =>
		{
			const element = <Et2SelectDayOfWeekReadonly>await fixture(`<et2-select-dow_ro noLang></et2-select-dow_ro>`);
			element.select_options = [{value: "1", label: "Monday"}, {value: "4", label: "Wednesday"}];
			(<any>element).value = ["1", "4"];
			await element.updateComplete;

			assert.deepEqual(element.getValueAsArray(), ["1", "4"], "an array value is already expanded");
			assert.deepEqual(rendered(element), ["Monday", "Wednesday"], "and should render both days");
		});
	});

	describe("Et2SelectCategoryReadonly", () =>
	{
		it("flattens hierarchical categories so a child still renders", async() =>
		{
			// Categories come back as a tree; Et2SelectReadonly's render() only looks at the top
			// level, so a sub-category would silently render as nothing without flattening.
			const element = <Et2SelectCategoryReadonly>await fixture(`<et2-select-cat_ro noLang></et2-select-cat_ro>`);
			const tree = [
				{
					value: "1", label: "Parent", children: [
						{value: "2", label: "Child"},
						{value: "3", label: "Other child", children: [{value: "4", label: "Grandchild"}]}
					]
				}
			];
			element.select_options = (<any>element).flattenOptions(tree);
			element.value = "4";
			await element.updateComplete;

			assert.deepEqual(
				(<any[]>element.select_options).map(o => "" + o.value),
				["1", "2", "3", "4"],
				"flattenOptions() should walk the whole tree, at any depth"
			);
			assert.deepEqual(rendered(element), ["Grandchild"], "a deeply nested category must still render");
		});
	});
});
