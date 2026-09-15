/**
 * Tests for Et2CustomFilterHeader (`et2-nextmatch-header-custom`).
 *
 * This header does not filter anything itself - it resolves a widget *type* coming from the
 * server into a real filter widget, hosts it in its slot, and proxies its value.  Everything
 * interesting is therefore in `transformAttributes()`'s type resolution, which has three separate
 * fallbacks (link-entry special case, legacy-type-to-webcomponent promotion, unknown-type
 * fallback) and no coverage until now.
 *
 * Behaviour under test:
 * - `link-entry` resolves to the dedicated entry header rather than a bare link-entry widget
 * - a legacy type name is promoted to its `et2-` web component when one is registered
 * - a type that is already a registered tag is used unchanged
 * - an unknown type falls back to a plain select instead of throwing
 * - the widgetType from the "modifications" array manager wins over the one in the attributes
 * - a hosted select is made hoisting and clearable, like the plain filter header
 * - `value` proxies to the hosted widget in both directions
 * - `set_select_options()` reaches the hosted widget when it can take options
 * - the FilterMixin contract still holds: a change emits the proxied value as a col_filter
 *
 * Setup strategy:
 * The header is created detached, given a "modifications" array manager stub, then
 * `transformAttributes()` is called directly - that is the entry point eTemplate itself uses, and
 * calling it is what builds the inner widget.  `console.error` is captured for the unknown-type
 * case so the expected diagnostic does not look like a test failure.
 *
 * Pass criteria:
 * Explicit assertions on the resolved `widgetType`/inner tag name, on the proxied value, and on
 * the emitted col_filter.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */
import {assert} from "@open-wc/testing";
import "../Headers/CustomFilterHeader";
import "../Headers/EntryHeader";
// et2-date stands in for "a type that is already a registered tag"; in a real page etemplate2
// has already imported every widget, here the test has to pull in the one it names.
import "../../Et2Date/Et2Date";
import {Et2CustomFilterHeader} from "../Headers/CustomFilterHeader";
import {ET2_NEXTMATCH_FILTER_EVENT} from "../Headers/events";
import {et2_arrayMgr} from "../../et2_core_arrayMgr";
import {fakeNextmatch, inHost, installEgwStub, waitForBubblingHandlers} from "./headerHelpers";

installEgwStub();

/**
 * Build a custom filter header the way eTemplate does: create, give it its array managers, then
 * hand it the server's attributes.  Real et2_arrayMgrs rather than doubles - transformAttributes()
 * reaches deep enough into them (getPerspectiveData(), expression expansion) that a stub would be
 * testing the stub.
 */
function customHeader(attrs : Record<string, any>, modifications : Record<string, any> = {})
{
	const header = <any>document.createElement("et2-nextmatch-header-custom");
	header.id = attrs.id ?? "custom";
	header.setArrayMgr("content", new et2_arrayMgr({}));
	header.setArrayMgr("modifications", new et2_arrayMgr(modifications));
	header.transformAttributes(attrs);
	return <Et2CustomFilterHeader & any>header;
}

const innerTag = (header : any) => header.filter_node?.localName;

describe("Et2CustomFilterHeader", () =>
{
	describe("widget type resolution", () =>
	{
		it("resolves link-entry to the dedicated entry header", async() =>
		{
			const header = customHeader({id: "link", widgetType: "link-entry"});
			const {host} = await inHost(header);
			try
			{
				assert.equal(
					header.widgetType,
					"et2-nextmatch-header-entry",
					"link-entry needs the entry *header*, a plain link-entry does not filter"
				);
				assert.equal(innerTag(header), "et2-nextmatch-header-entry", "inner widget should match the resolved type");
			}
			finally
			{
				host.remove();
			}
		});

		it("promotes a legacy type name to its web component", async() =>
		{
			const header = customHeader({id: "cat_id", widgetType: "select"});
			const {host} = await inHost(header);
			try
			{
				assert.equal(header.widgetType, "et2-select", "a legacy type should prefer the registered et2- component");
				assert.equal(innerTag(header), "et2-select", "inner widget should be the web component");
			}
			finally
			{
				host.remove();
			}
		});

		it("uses a type that is already a registered tag unchanged", async() =>
		{
			const header = customHeader({id: "created", widgetType: "et2-date"});
			const {host} = await inHost(header);
			try
			{
				assert.equal(header.widgetType, "et2-date", "an explicit tag should be taken as-is");
				assert.equal(innerTag(header), "et2-date", "inner widget should be the requested tag");
			}
			finally
			{
				host.remove();
			}
		});

		it("falls back to a select for an unknown type instead of throwing", async() =>
		{
			const errors : any[] = [];
			const originalError = console.error;
			console.error = (...args : any[]) => errors.push(args);
			try
			{
				const header = customHeader({id: "bogus", widgetType: "not-a-real-widget"});
				const {host} = await inHost(header);
				try
				{
					assert.equal(header.widgetType, "et2-select", "an unknown type should degrade to a plain select");
					assert.isNotEmpty(errors, "an unknown type should be reported to the console");
				}
				finally
				{
					host.remove();
				}
			}
			finally
			{
				console.error = originalError;
			}
		});

		it("prefers the widgetType from modifications over the one in the attributes", async() =>
		{
			const header = customHeader(
				{id: "cat_id", widgetType: "select"},
				{cat_id: {widgetType: "et2-date"}}
			);
			const {host} = await inHost(header);
			try
			{
				assert.equal(
					header.widgetType,
					"et2-date",
					"server-side modifications must be able to override the template's type"
				);
			}
			finally
			{
				host.remove();
			}
		});

		it("makes a hosted select hoisting and clearable", async() =>
		{
			const header = customHeader({id: "cat_id", widgetType: "select"});
			const {host} = await inHost(header);
			try
			{
				assert.isTrue(header.filter_node.hoist, "a hosted select must hoist out of the header row");
				assert.isTrue(header.filter_node.clearable, "a hosted filter must be clearable");
			}
			finally
			{
				host.remove();
			}
		});
	});

	describe("value proxying", () =>
	{
		it("reads the hosted widget's value", async() =>
		{
			const header = customHeader({id: "cat_id", widgetType: "select"});
			const {host} = await inHost(header);
			try
			{
				header.filter_node.value = "2";
				await header.filter_node.updateComplete;

				assert.equal(header.value, "2", "the header's value is the hosted widget's value");
			}
			finally
			{
				host.remove();
			}
		});

		it("writes through to the hosted widget", async() =>
		{
			const header = customHeader({id: "cat_id", widgetType: "select"});
			const {host} = await inHost(header);
			try
			{
				header.value = "1";
				await header.filter_node.updateComplete;

				assert.equal(header.filter_node.value, "1", "setting the header should set the hosted widget");
			}
			finally
			{
				host.remove();
			}
		});

		it("reports undefined rather than throwing before a widget exists", () =>
		{
			const header = <any>document.createElement("et2-nextmatch-header-custom");
			assert.isUndefined(header.value, "an unresolved custom header has no value");
		});
	});

	it("forwards new server options to the hosted widget", async() =>
	{
		const header = customHeader({id: "cat_id", widgetType: "select"});
		const {host} = await inHost(header);
		try
		{
			header.set_select_options({"1": "One", "2": "Two"});
			await header.filter_node.updateComplete;

			assert.sameMembers(
				header.filter_node.select_options.map(o => o.value),
				["1", "2"],
				"options pushed from the server should reach the hosted widget"
			);
		}
		finally
		{
			host.remove();
		}
	});

	it("emits the hosted widget's value as a col_filter", async() =>
	{
		const header = customHeader({id: "cat_id", widgetType: "select"});
		const {host} = await inHost(header);
		try
		{
			const nextmatch = fakeNextmatch();
			header.setNextmatch(nextmatch);
			header.value = "2";
			await header.filter_node.updateComplete;

			let detail : any = null;
			host.addEventListener(ET2_NEXTMATCH_FILTER_EVENT, (event : Event) => detail = (<CustomEvent>event).detail);
			header.dispatchEvent(new Event("change", {bubbles: true}));
			await waitForBubblingHandlers();

			assert.deepEqual(
				detail?.filters,
				{col_filter: {cat_id: "2"}},
				"the custom header filters by its hosted widget's value"
			);
			assert.deepEqual(
				nextmatch.calls.applyFilters,
				[{col_filter: {cat_id: "2"}}],
				"unhandled custom filter should still fall back to nextmatch.applyFilters()"
			);
		}
		finally
		{
			host.remove();
		}
	});
});
