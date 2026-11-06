import {assert, elementUpdated, fixture, html} from "@open-wc/testing";
import {et2_arrayMgr} from "../../et2_core_arrayMgr";
import {Et2Description} from "../../Et2Description/Et2Description";
import "../../Layout/Et2Box/Et2Box";
import "../../Et2Textbox/Et2Textbox";

/**
 * Contract: grow=, span= and full= written in a .xet reach the DOM as attributes.
 *
 * They have to.  Everything the layout system does with them is a selector - the stylesheet's
 * `[grow]` / `[span="all"]` / `[full]` rules, and GROW_SELECTOR / SPAN_END_SELECTOR in
 * Et2LayoutStrategies - and a selector cannot see a JS property.
 *
 * What makes the difference is one line of transformAttributes(): it calls setAttribute() only for
 * an attribute the element already carries, or a property the widget declares as reflecting.
 * Widgets built from a .xet are created with document.createElement() and handed their attributes
 * as a plain object, so they carry none at that point - which means an undeclared name lands as an
 * inert property and every layout rule silently misses it.  That is exactly what happened: the
 * layout docs described all three, `etemplate2.0.rng` accepted all three, and none of them did
 * anything from a template.
 *
 * The existing Layout.test.ts cases cannot catch this, because they write their markup as real
 * `<div grow="1">` attributes and never go through transformAttributes() at all.
 *
 * Setup: each case attaches a widget, hands it a content arrayMgr and calls transformAttributes() -
 * the same entry point loadWebComponent() uses.  Attached rather than detached on purpose: Lit only
 * starts its update cycle on connection, and reflection happens during an update.
 *
 * Pass criteria: the attribute is on the element afterwards, carrying the value the layout CSS
 * matches on.
 */

// Stub global egw, as the widgets call it while rendering
// @ts-ignore
window.egw = {
	debug_level: () => 0,
	debug: () => {},
	image: () => "",
	lang: i => i + "",
	link: i => i,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	webserverUrl: ""
};

// A runtime reference, so the import is not erased as type-only
let keepImport : Et2Description = new Et2Description();

const CONTENT = {is_wide: true, is_narrow: false};

/**
 * Put one attribute through the .xet path and report what the element ended up with.
 */
async function fromTemplate(attribute : string, value : string, tag = "et2-description")
{
	const widget : any = await fixture(`<${tag}></${tag}>`);
	widget.setArrayMgr("content", new et2_arrayMgr(CONTENT));
	widget.transformAttributes({[attribute]: value});
	await elementUpdated(widget);

	return {attribute: widget.getAttribute(attribute), property: widget[attribute]};
}

describe("Layout attributes reach the DOM from a template", () =>
{
	describe("grow", () =>
	{
		[["1", "1"], ["2", "2"], ["3", "3"]].forEach(([written, expected]) =>
		{
			it(`puts grow="${written}" on the element for the stylesheet to match`, async() =>
			{
				const result = await fromTemplate("grow", written);

				assert.equal(result.attribute, expected,
					`grow="${written}" must be a real attribute - [layout] [grow] is a selector, ` +
					`and a JS property is invisible to it`);
			});
		});

		it("keeps a bare grow, which the layout reads as a factor of 1", async() =>
		{
			// The empty value is the interesting one: it is falsy, so transformAttributes' own
			// setAttribute branch skips it and only Lit's reflection puts it on the element
			const result = await fromTemplate("grow", "");

			assert.notEqual(result.attribute, null,
				"a bare grow must still leave the attribute behind - [grow] matches by presence");
		});

		it("does it for a layout widget too", async() =>
		{
			assert.equal((await fromTemplate("grow", "2", "et2-hbox")).attribute, "2");
		});

		it("leaves no attribute on a widget that never asked", async() =>
		{
			const widget : any = await fixture(html`<et2-description></et2-description>`);
			await elementUpdated(widget);

			assert.isFalse(widget.hasAttribute("grow"),
				"a grow attribute on a widget that never asked would make it take space in every layout");
		});
	});

	describe("span", () =>
	{
		[["all", "all"], ["end", "end"], ["*", "*"]].forEach(([written, expected]) =>
		{
			it(`puts span="${written}" on the element`, async() =>
			{
				const result = await fromTemplate("span", written);

				assert.equal(result.attribute, expected,
					`span="${written}" is matched by a selector - grid-base.less for "all", ` +
					`SPAN_END_SELECTOR for "end"/"*"`);
			});
		});
	});

	describe("full", () =>
	{
		// Declared Boolean, so it resolves through parseBoolExpression the way hidden/disabled do
		const CASES : [string, boolean][] = [
			["true", true],
			["1", true],
			["false", false],
			["@is_wide", true],
			["@is_narrow", false],
			["!@is_narrow", true]
		];

		CASES.forEach(([written, expected]) =>
		{
			it(`resolves full="${written}" to ${expected}`, async() =>
			{
				const result = await fromTemplate("full", written);

				// A boolean attribute applies by presence, so a false that left "false" behind
				// would still be full width
				assert.equal(result.attribute !== null, expected,
					`full="${written}" left the attribute as ${JSON.stringify(result.attribute)}`);
			});
		});
	});

	describe("the declarations the whole thing rests on", () =>
	{
		const WIDGETS = ["et2-description", "et2-hbox", "et2-textbox"];

		WIDGETS.forEach(tag =>
		{
			["grow", "span", "full"].forEach(attribute =>
			{
				it(`declares ${attribute} as reflecting on <${tag}>`, () =>
				{
					const widgetClass : any = window.customElements.get(tag);
					const options : any = widgetClass.getPropertyOptions(attribute);

					assert.isTrue(typeof options === "object" && options.reflect === true,
						`${attribute} must stay reflect: true on the Et2Widget mixin - without it ` +
						`transformAttributes() sets it as a plain property and no layout rule matches`);
				});
			});
		});

		it("declares full as Boolean, which is what selects the expression-aware resolver", () =>
		{
			const widgetClass : any = window.customElements.get("et2-description");
			const options : any = widgetClass.getPropertyOptions("full");

			assert.equal(typeof options === "object" ? options.type : options, Boolean,
				"full must stay type: Boolean, or full=\"false\" resolves to a truthy string");
		});

		it("keeps grow and span as strings, so grow=\"2\" and span=\"all\" survive as written", () =>
		{
			const widgetClass : any = window.customElements.get("et2-description");

			["grow", "span"].forEach(attribute =>
			{
				const options : any = widgetClass.getPropertyOptions(attribute);
				assert.equal(typeof options === "object" ? options.type : options, String,
					`${attribute} carries a value, not a presence - Boolean would collapse it`);
			});
		});
	});
});
