/**
 * Widget conformance checks: the code-quality / UX standards every Et2 widget is expected to meet,
 * on top of the hard contract in InputBasicTests.ts (readonly / disabled / hidden / required /
 * value round trip / label & help-text parts).
 *
 * These run as **warnings**.  A failing check logs `[conformance] <test title>: <reason>` to the
 * console and the test still passes, so a widget that has not been brought up to standard yet does
 * not break the build.  Run with `JSTEST_CONFORMANCE=strict` to make every failure a real failure
 * (that is the end state - flip the default in web-test-runner.config.mjs once the warnings are
 * gone).  Checks that apply to a widget are declared where it is tested, via the `skip` list passed
 * to inputBasicTests(): opt out of a check only for a real, widget-specific reason, with a one-line
 * comment at the call site saying why.
 *
 * What is checked, and what is deliberately left to each widget's own tests:
 *
 *  - "a11y"        axe finds no violations (labelled / disabled / required).  Covers the accessible
 *                  name and label association, so there is no separate hand-rolled name check.
 *  - "disabled-controls"  disabling disables the inner controls (disabled or aria-disabled), and
 *                  the widget cannot take focus; enabling again restores it.
 *  - "required-aria"      required is exposed to assistive tech (required / aria-required).
 *  - "help-text-aria"     help text is tied to the control with aria-describedby.
 *  - "focus"       focus() lands inside the widget, blur() takes it away again.
 *  - "unhide"      hiding is reversible - the widget comes back.
 *  - "change-event"       a programmatic set_value() does not fire "change" (only the user does).
 *  - "lifecycle"   remove and re-add the widget: no error, still renders.
 *  - "namespace"   inside a namespace the widget reads its value from, and reports its path under,
 *                  that namespace (what etemplate2.getValues() relies on to build the submit array).
 *  - "parts"       the `options.parts` the widget documents are really there.
 *  - "styles"      no hard-coded px lengths in the widget's own CSS (use em / variables).
 *
 * Not covered here yet, by design: keyboard operation (needs the widget's own interaction),
 * context-menu / drag-and-drop actions, popup-window realm, and legacy .xet attribute parsing
 * (see BooleanAttributeExpressions.test.ts).
 */

import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {deepQueryPart} from "./WidgetSlotTests";
import {et2_arrayMgr} from "../../et2_core_arrayMgr";

export type ConformanceCheck =
	"a11y"
	| "disabled-controls"
	| "required-aria"
	| "help-text-aria"
	| "focus"
	| "unhide"
	| "change-event"
	| "lifecycle"
	| "namespace"
	| "parts"
	| "styles";

export interface WidgetConformanceOptions
{
	/** A "good" value, same as given to inputBasicTests() */
	testValue : any;
	/** What getValue() returns for testValue, if the widget normalizes it */
	expectedValue? : any;
	/** Checks that do not apply to this widget */
	skip? : ConformanceCheck[];
	/** The @csspart names this widget documents (beyond the label / help-text ones checked elsewhere) */
	parts? : string[];
}

declare global
{
	interface Window
	{
		/** "strict": conformance failures fail the test.  Anything else: they are logged as warnings. */
		JSTEST_CONFORMANCE? : string;
	}
}

/** Tag of the widget under test, for the warnings - set in widgetConformanceTests()'s beforeEach */
let currentTag = "";

/**
 * An `it()` whose failure is only a warning, unless JSTEST_CONFORMANCE=strict
 */
function conformanceIt(title : string, fn : () => Promise<void> | void)
{
	it(title, async function()
	{
		const started = performance.now();
		try
		{
			// Our own deadline, shorter than mocha's: a check that hangs is a finding too, and must
			// not turn into a hard timeout failure while the tier is only supposed to warn
			let timer;
			await Promise.race([
				fn(),
				new Promise((_, reject) => timer = setTimeout(() => reject(new Error("timed out")), 2000))
			]).finally(() => clearTimeout(timer));
		}
		catch(e)
		{
			if(window.JSTEST_CONFORMANCE === "strict")
			{
				throw e;
			}
			console.warn(`[conformance] <${currentTag}> ${title}: ${e?.message ?? e}`);
		}
		finally
		{
			const took = Math.round(performance.now() - started);
			if(took > 1000)
			{
				console.info(`[conformance-slow] <${currentTag}> ${title}: ${took}ms`);
			}
		}
	});
}

/** Two update cycles - some widgets only settle on the second (see WidgetSlotTests.settle()) */
async function settle(element : any)
{
	await element.updateComplete;
	await element.updateComplete;
}

/** Every element matching `selector` under `root`, crossing shadow roots */
function deepQueryAll(root : Element | ShadowRoot, selector : string) : HTMLElement[]
{
	const found = Array.from(root.querySelectorAll<HTMLElement>(selector));
	for(const el of Array.from(root.querySelectorAll("*")))
	{
		if(el.shadowRoot)
		{
			found.push(...deepQueryAll(el.shadowRoot, selector));
		}
	}
	return found;
}

const CONTROL_SELECTOR = "input:not([type=hidden]), textarea, select, button, [contenteditable=true], " +
	"[role=textbox], [role=combobox], [role=checkbox], [role=switch], [role=slider], [role=spinbutton], " +
	"[role=listbox], [role=radio], [role=button]";

/** The interactive controls a widget renders, in light DOM or any shadow root */
function controlsOf(element : Element) : HTMLElement[]
{
	const controls = deepQueryAll(element, CONTROL_SELECTOR);
	if(element.shadowRoot)
	{
		controls.push(...deepQueryAll(element.shadowRoot, CONTROL_SELECTOR));
	}
	return controls;
}

function requireControls(element : Element) : HTMLElement[]
{
	const controls = controlsOf(element);
	if(!controls.length)
	{
		throw new Error("no interactive control found in the widget - if it has none, skip this check with a reason");
	}
	return controls;
}

/** Is `node` the widget itself or anywhere inside it, crossing shadow boundaries */
function composedContains(host : Element, node : Node | null) : boolean
{
	while(node)
	{
		if(node === host)
		{
			return true;
		}
		node = (<any>node).assignedSlot ?? node.parentNode ?? (<ShadowRoot>node).host ?? null;
	}
	return false;
}

/** document.activeElement, descending through shadow roots to the real focused node */
function deepActiveElement() : Element | null
{
	let active = document.activeElement;
	while(active?.shadowRoot?.activeElement)
	{
		active = active.shadowRoot.activeElement;
	}
	return active;
}

function isDisabledish(control : HTMLElement, host : HTMLElement) : boolean
{
	return (<any>control).disabled === true ||
		control.getAttribute("aria-disabled") === "true" ||
		host.getAttribute("aria-disabled") === "true";
}

function isRequiredish(control : HTMLElement, host : HTMLElement) : boolean
{
	return (<any>control).required === true ||
		control.getAttribute("aria-required") === "true" ||
		host.getAttribute("aria-required") === "true";
}

/** Resolve an aria-describedby id relative to the control (same shadow root, else the document) */
function describedByText(control : HTMLElement) : string
{
	const ids = (control.getAttribute("aria-describedby") ?? "").split(/\s+/).filter(Boolean);
	const root = <Document | ShadowRoot>control.getRootNode();
	return ids.map(id => root.getElementById?.(id)?.textContent ?? "").join(" ").trim();
}

export function widgetConformanceTests(before : Function, options : WidgetConformanceOptions)
{
	const skip = options.skip ?? [];
	const expectedValue = "expectedValue" in options ? options.expectedValue : options.testValue;
	const enabled = (check : ConformanceCheck) => !skip.includes(check);

	describe("Conformance", () =>
	{
		let element : any;

		beforeEach(async() =>
		{
			element = await before();
			currentTag = element.localName;
		});

		if(enabled("a11y"))
		{
			describe("Accessibility (axe)", () =>
			{
				// color-contrast: the test page has none of the app's CSS, so contrast says nothing
				const axe = {ignoredRules: ["color-contrast"]};

				conformanceIt("labelled widget has no violations", async() =>
				{
					element.label = "Conformance label";
					await settle(element);
					await assert.isAccessible(element, axe);
				});
				conformanceIt("disabled widget has no violations", async() =>
				{
					element.label = "Conformance label";
					element.disabled = true;
					await settle(element);
					await assert.isAccessible(element, axe);
				});
				conformanceIt("required widget has no violations", async() =>
				{
					element.label = "Conformance label";
					element.required = true;
					await settle(element);
					await assert.isAccessible(element, axe);
				});
			});
		}

		if(enabled("disabled-controls"))
		{
			describe("Disabled", () =>
			{
				conformanceIt("disables its inner controls and cannot take focus", async() =>
				{
					element.disabled = true;
					await settle(element);

					for(const control of requireControls(element))
					{
						assert.isTrue(isDisabledish(control, element),
							`<${control.localName}> is still enabled (no disabled / aria-disabled) while the widget is disabled`);
					}
					element.focus();
					assert.isFalse(composedContains(element, deepActiveElement()), "A disabled widget took focus");
				});
				conformanceIt("enables its inner controls again", async() =>
				{
					element.disabled = true;
					await settle(element);
					element.disabled = false;
					await settle(element);

					for(const control of requireControls(element))
					{
						assert.isFalse(isDisabledish(control, element),
							`<${control.localName}> stays disabled after the widget is re-enabled`);
					}
				});
			});
		}

		if(enabled("required-aria"))
		{
			conformanceIt("Required is exposed to assistive tech (required / aria-required)", async() =>
			{
				element.required = true;
				await settle(element);

				assert.isTrue(requireControls(element).some(control => isRequiredish(control, element)),
					"No control has required / aria-required while the widget is required");
			});
		}

		if(enabled("help-text-aria"))
		{
			conformanceIt("Help text is linked to the control with aria-describedby", async() =>
			{
				element.helpText = "Conformance help text";
				await settle(element);

				assert.isTrue(requireControls(element).some(
					control => describedByText(control).includes("Conformance help text")),
					"No control has an aria-describedby that resolves to the help text");
			});
		}

		if(enabled("focus"))
		{
			conformanceIt("Focus: focus() goes inside the widget, blur() takes it out", async() =>
			{
				element.focus();
				await settle(element);
				assert.isTrue(composedContains(element, deepActiveElement()),
					`focus() left focus on <${deepActiveElement()?.localName}>, not inside the widget`);

				element.blur();
				await settle(element);
				assert.isFalse(composedContains(element, deepActiveElement()), "blur() left focus inside the widget");
			});
		}

		if(enabled("unhide"))
		{
			conformanceIt("Hidden: is visible again after being unhidden", async() =>
			{
				element.hidden = true;
				await settle(element);
				assert.isFalse(element.checkVisibility(), "Hidden widget is still visible");

				element.hidden = false;
				await settle(element);
				assert.isTrue(element.checkVisibility(), "Widget did not come back after being unhidden");
			});
		}

		if(enabled("change-event"))
		{
			// Only the user changes a value by interacting; code setting it, either way, must not look like one
			const assertSilent = async(setter : () => void, how : string) =>
			{
				const changes = sinon.spy();
				element.addEventListener("change", changes);
				element.addEventListener("sl-change", changes);

				setter();
				await settle(element);

				assert.equal(changes.callCount, 0,
					`${how} fired ${changes.callCount} change event(s) - only user input should`);
			};
			conformanceIt("Value: set_value() does not fire a change event",
				() => assertSilent(() => element.set_value(options.testValue), "set_value()"));
			conformanceIt("Value: setting the value property does not fire a change event",
				() => assertSilent(() => element.value = options.testValue, "element.value ="));
		}

		if(enabled("lifecycle"))
		{
			conformanceIt("Lifecycle: survives being removed, added again and removed again", async() =>
			{
				// Errors thrown later, from a render or a promise, never reach a try/catch - they go to
				// mocha's global handler, which fails the test even though this tier only warns.  Capture
				// phase on window runs ahead of mocha's own listener, and mocha's window.onerror is swapped
				// out for the length of the check, so they stay ours.
				const errors : string[] = [];
				const capture = (e : any) =>
				{
					errors.push(e.message ?? e.reason?.message ?? String(e.reason));
					e.preventDefault();
					e.stopImmediatePropagation();
				};
				const mochaOnError = window.onerror;
				window.onerror = (message) =>
				{
					errors.push(String(message));
					return true;
				};
				window.addEventListener("error", capture, true);
				window.addEventListener("unhandledrejection", capture, true);
				try
				{
					element.label = "Still here";
					await settle(element);

					const parent = element.parentNode;
					const next = element.nextSibling;
					element.remove();
					parent.insertBefore(element, next);
					await settle(element);
					assert.isTrue(element.isConnected, "Widget is not connected after re-adding");
					assert.isTrue(element.checkVisibility(), "Widget is not visible after re-adding");

					// A second disconnect, so anything set up again on re-adding has to tear down again
					// (and so a throw lands here and not in the fixture cleanup, where it cannot be caught)
					element.remove();
					await new Promise(resolve => setTimeout(resolve, 50));
					assert.isEmpty(errors, "Errors while disconnecting / reconnecting: " + errors.join("; "));
				}
				finally
				{
					window.removeEventListener("error", capture, true);
					window.removeEventListener("unhandledrejection", capture, true);
					window.onerror = mochaOnError;
				}
			});
		}

		if(enabled("namespace"))
		{
			// What Et2Template does when it has a namespace ("content" attribute): it gives its
			// children a content array manager rooted at that part of the data, and etemplate2's
			// getValues() then stores each child's getValue() at getPath() + id.  Done by hand
			// here, on the widget under test, so this needs neither a template nor a loader.
			describe("Namespace", () =>
			{
				const FIELD = "conformance_field";

				/** Give the widget its id the way a template would, with `namespace` as its content root */
				function placeInNamespace(content : object, namespace? : string)
				{
					const top = new et2_arrayMgr(content);
					element.setArrayMgr("content", namespace ? top.openPerspective(element, namespace) : top);
					element.transformAttributes({id: FIELD});
				}

				conformanceIt("reads its value from the namespace, not the top level", async() =>
				{
					placeInNamespace({[FIELD]: "top level", sub: {[FIELD]: options.testValue}}, "sub");
					await settle(element);
					assert.deepEqual(element.getValue(), expectedValue,
						"Widget did not get its value from content[sub][" + FIELD + "]");
				});
				conformanceIt("reports its submit path under the namespace", async() =>
				{
					placeInNamespace({sub: {[FIELD]: options.testValue}}, "sub");
					await settle(element);
					assert.deepEqual([...element.getPath(), element.id], ["sub", FIELD],
						"Submit path should be sub[" + FIELD + "]");
					// An etemplate puts its own unique id in front of the namespace
					assert.isTrue(element.getAttribute("id").endsWith("sub_" + FIELD),
						`DOM id "${element.getAttribute("id")}" does not carry the namespace (expected ...sub_${FIELD})`);
				});
				conformanceIt("without a namespace its path is just its id", async() =>
				{
					placeInNamespace({[FIELD]: options.testValue});
					await settle(element);
					assert.deepEqual([...element.getPath(), element.id], [FIELD]);
					assert.deepEqual(element.getValue(), expectedValue, "Widget did not get its value from the content");
				});
			});
		}

		if(enabled("parts") && options.parts?.length)
		{
			conformanceIt("Parts: exposes the documented parts", () =>
			{
				const missing = options.parts.filter(part => !deepQueryPart(element.shadowRoot ?? element, part));
				assert.isEmpty(missing, "Missing part(s): " + missing.join(", "));
			});
		}

		if(enabled("styles"))
		{
			conformanceIt("Styles: no hard-coded px lengths (use em / variables)", () =>
			{
				const sheets : any[] = (<any>element.constructor).elementStyles ?? [];
				const css = sheets.map(sheet => sheet?.cssText ?? "").join("\n").replace(/\/\*[\s\S]*?\*\//g, "");
				// 0 and 1px (hairline borders) are fine
				const offenders = [...css.matchAll(/(?<![\w.#-])(\d*\.?\d+)px\b/g)]
					.filter(match => parseFloat(match[1]) > 1)
					.map(match => match[0]);
				assert.isEmpty(offenders, `${offenders.length} px length(s): ${[...new Set(offenders)].slice(0, 8).join(", ")}`);
			});
		}
	});
}
