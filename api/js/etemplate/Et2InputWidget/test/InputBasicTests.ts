/**
 * Common, shared contract tests for every Et2InputWidget.
 *
 * Have your widget creation in a separate function ("before"), and pass it in along with a "good"
 * test value. Checking bad/invalid values and widget-specific behaviour is still up to your own
 * test file - this is just a baseline: if a widget doesn't pass these, something is genuinely
 * wrong with it (readonly/disabled/hidden not being respected, required not being enforced, a
 * value not round-tripping, or the label/help-text slots not following the `part=` convention
 * the rest of the widget library depends on for consistent styling, e.g. `.et2-label-fixed`).
 *
 * @example
 * async function before()
 * {
 * 	// Create an element to test with, and wait until it's ready
 * 	// @ts-ignore
 * 	element = await fixture<Et2Date>(html`
 *         <et2-date label="I'm a date"></et2-date>
 * 	`);
 * 	return element;
 * }
 * inputBasicTests(before, "2008-09-22T00:00:00.000Z", "input");
 *
 * @param before Function to create / setup the widget to test. Run before each test, must return
 *    the widget.
 * @param test_value A "good" value to set on the widget.
 * @param value_selector Passed to querySelector() to find the DOM node that displays the value,
 *    used only for the "no value gives empty string" check (skip that with
 *    `options.checkEmptyDisplay`/`options.skip` if there's no such single selector - eg. a Select
 *    or breadcrumb-shaped widget).
 * @param options See {@link InputBasicTestOptions}.
 */

import {Et2InputWidgetInterface} from "../Et2InputWidget";
import {assert, elementUpdated} from "@open-wc/testing";
import {widgetSlotTests} from "../../Et2Widget/test/WidgetSlotTests";

export interface InputBasicTestOptions
{
	/**
	 * What get_value() should equal after set_value(test_value), if different from test_value.
	 * Use this when a widget legitimately normalizes/corrects the value on the way back out
	 * (eg. Et2Number applying precision/separators, Et2Date normalizing a timestamp format).
	 * Defaults to test_value (an exact round trip).
	 */
	expectedValue? : any;

	/**
	 * What get_value() returns when nothing has been set. Defaults to "". Use eg. [] for
	 * `multiple`-style widgets.
	 */
	emptyValue? : any;

	/**
	 * Custom assertion replacing the default "value_selector's innerText is empty" check, for
	 * widgets whose empty rendering isn't reducible to blank text (eg. a Select showing a
	 * placeholder option, or a breadcrumb always showing a root icon).
	 */
	checkEmptyDisplay? : (element : Element) => void;

	/**
	 * Escape hatch for scenarios that plainly don't apply to this widget. Every entry needs a
	 * real, widget-specific reason - leave a one-line comment at the call site saying why, don't
	 * use this as a quick way to silence a failure without understanding it.
	 */
	skip? : Array<"readonly" | "disabled" | "hidden" | "required" | "roundtrip" | "label" | "help-text" | "label-fixed">;
}

// Widget used in each test
let element : Et2InputWidgetInterface & { checkVisibility() : boolean };

export function inputBasicTests(before : Function, test_value : any, value_selector : string, options : InputBasicTestOptions = {})
{
	const skip = options.skip || [];
	const expectedValue = "expectedValue" in options ? options.expectedValue : test_value;
	const emptyValue = "emptyValue" in options ? options.emptyValue : "";

	if(!skip.includes("readonly"))
	{
		describe("Readonly", () =>
		{
			beforeEach(async() =>
			{
				element = await before();
			});

			it("does not return a value (via attribute)", async() =>
			{
				element.readonly = true;

				element.set_value(test_value);

				// wait for asychronous changes to the DOM
				await elementUpdated(<Element><unknown>element);
				// Read-only widget returns null
				assert.equal(element.getValue(), null);
			});

			it("does not return a value (via method)", async() =>
			{
				(<Et2InputWidgetInterface>element).set_readonly(true);

				element.set_value(test_value);

				// wait for asychronous changes to the DOM
				await elementUpdated(<Element><unknown>element);
				// Read-only widget returns null
				assert.equal(element.getValue(), null);
			});

			it("does not return a value if it goes readonly after having a value", async() =>
			{
				element.set_value(test_value);

				element.set_readonly(true);

				// wait for asychronous changes to the DOM
				await elementUpdated(<Element><unknown>element);
				// Read-only widget returns null
				assert.equal(element.getValue(), null);
			});
		});
	}

	if(!skip.includes("disabled"))
	{
		describe("Disabled", () =>
		{
			beforeEach(async() =>
			{
				element = await before();
			});

			it("does not return a value (via attribute)", async() =>
			{
				element.disabled = true;

				element.set_value(test_value);

				await elementUpdated(<Element><unknown>element);
				assert.equal(element.getValue(), null);
			});

			it("does not return a value if it becomes disabled after having a value", async() =>
			{
				element.set_value(test_value);

				element.disabled = true;

				await elementUpdated(<Element><unknown>element);
				assert.equal(element.getValue(), null);
			});

			// This is what actually distinguishes "disabled" from "hidden" - a disabled widget
			// is still fully shown, just not interactive
			// (doc/etemplate2/pages/getting-started/widgets.md#disabled-vs-readonly-vs-hidden)
			it("stays visible", async() =>
			{
				element.disabled = true;

				await elementUpdated(<Element><unknown>element);
				assert.isTrue(element.checkVisibility(), "Disabled widget should stay visible, only readonly/hidden hide it");
			});
		});
	}

	if(!skip.includes("hidden"))
	{
		describe("Hidden", () =>
		{
			beforeEach(async() =>
			{
				element = await before();
			});

			it("is not visible", async() =>
			{
				element.hidden = true;

				await elementUpdated(<Element><unknown>element);
				assert.isFalse(element.checkVisibility(), "Hidden widget should not be visible");
			});

			// Unlike disabled/readonly, a hidden widget's value still gets submitted - it's
			// invisible to the *user*, not removed from the form
			// (doc/etemplate2/pages/getting-started/widgets.md#disabled-vs-readonly-vs-hidden)
			it("still returns its value", async() =>
			{
				element.set_value(test_value);
				element.hidden = true;

				await elementUpdated(<Element><unknown>element);
				assert.deepEqual(element.get_value(), expectedValue, "Hidden widget should still return its value");
			});
		});
	}

	describe("In/Out value tests", () =>
	{
		beforeEach(async() =>
		{
			element = await before();
		});
		it("no value gives empty string", async() =>
		{
			element.set_value("");
			await elementUpdated(element);

			if(options.checkEmptyDisplay)
			{
				options.checkEmptyDisplay(<Element><unknown>element);
			}
			else
			{
				// Shows as empty / no value
				let value = (<Element><unknown>element).querySelector(value_selector) || (<Element><unknown>element).shadowRoot.querySelector(value_selector);
				assert.isDefined(value, "Bad value selector '" + value_selector + "'");
				assert.isNotNull(value, "Bad value selector '" + value_selector + "'");

				assert.equal(value.innerText.trim(), "", "Displaying something when there is no value");
			}
			if(element.multiple)
			{
				assert.isEmpty(element.get_value());
				return;
			}
			// Gives no value
			assert.deepEqual(element.get_value(), emptyValue, "Value mismatch");
		});

		it("value out matches value in", async() =>
		{
			element.set_value(test_value);

			// wait for asychronous changes to the DOM
			await elementUpdated(<Element><unknown>element);

			// widget returns what we gave it (or what it's expected to correct it to)
			assert.deepEqual(element.get_value(), expectedValue);
		});
	});

	if(!skip.includes("required"))
	{
		describe("Required", () =>
		{
			beforeEach(async() =>
			{
				assert.isTrue(test_value !== "" && test_value !== null && typeof test_value !== "undefined", "test_value needs to be a value");

				element = await before();
				await elementUpdated(<Element><unknown>element);
				element.required = true;
				await elementUpdated(<Element><unknown>element);
			});

			it("is invalid without a value", async() =>
			{
				element.set_value("");

				// wait for asychronous changes to the DOM
				await elementUpdated(<Element><unknown>element);

				// widget returns what we gave it
				assert.deepEqual(element.get_value(), emptyValue);
				assert.equal(element.required, true, "required not set");

				// widget fails validation
				let messages = [];
				assert.isFalse(element.isValid(messages), `Required has no value (${element.getValue()}), but is considered valid`);
			});
			it("is valid with a value", async() =>
			{
				element.set_value(test_value);

				// wait for asychronous changes to the DOM
				await elementUpdated(<Element><unknown>element);

				// widget returns what we gave it
				assert.deepEqual(element.get_value(), expectedValue);
				assert.equal(element.required, true, "required not set");

				// widget fails validation
				let messages = [];
				assert.isTrue(element.isValid(messages), `Required has a value (${element.getValue()}), but is not considered valid. ` + messages.join("\n"));
			});
		});
	}

	// Every input widget gets a label and help-text slot from Et2InputWidget's own
	// _labelTemplate()/_helpTextTemplate() (or an equivalent from whatever it subclasses directly,
	// eg. Shoelace's SlInput) - see WidgetSlotTests.ts for why this checks the part= convention
	// rather than just "is something visible". Checked separately since a widget can support one
	// without the other (eg. Et2Switch's label is its own text content, not a separate part, but
	// it does have a real help-text part).
	const slots = (["label", "help-text"] as const).filter(slot => !skip.includes(slot));
	if(slots.length)
	{
		widgetSlotTests(before, slots, {skipLabelFixed: skip.includes("label-fixed")});
	}
}
