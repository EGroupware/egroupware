/**
 * Assertions for "this element should (not) be there", for use from any *.test.ts.
 *
 * These exist because of how chai fails rather than how it passes.  A DOM node cannot be
 * serialised back to the test runner, so an assertion that fails while holding an element -
 * `assert.isNull(el.querySelector(".x"))`, `assert.notExists()`, `assert.isUndefined()`,
 * `assert.notOk()`, or a bare `assert.equal(node, null)` - takes the entire test file down with
 * "Browser tests did not finish within 120000ms" and says nothing about which assertion failed
 * or what was actually found.  Every other test in that file is reported as neither passed nor
 * failed.  Custom elements, shadow roots and non-empty NodeLists all behave the same way.
 *
 * assertNoElement() compares short strings instead, so a failure reads
 * "expected '<et2-image class=\"icon\">' to equal ''" and names the offending element.
 *
 * Only this direction needs help.  Asserting that an element IS there is already safe with plain
 * `assert.isOk()` / `assert.isNotNull()`, because those only ever fail holding null.
 */
import {assert} from "@open-wc/testing";

/** Anything a DOM lookup hands back: one node, a node list, or nothing. */
export type FoundElement = Element | ShadowRoot | ArrayLike<Element> | null | undefined;

/**
 * Describe what a lookup found, as a string safe to put in an assertion message.
 *
 * Empty string means "nothing there", which is what assertNoElement() compares against - an
 * empty NodeList counts as nothing, matching what `querySelectorAll` returning no matches means.
 */
function describeFound(found : FoundElement) : string
{
	if(found === null || typeof found === "undefined")
	{
		return "";
	}
	// A shadow root has no tag of its own to report
	if(typeof (<ShadowRoot>found).host !== "undefined" && typeof (<Element>found).tagName === "undefined")
	{
		return "#shadow-root";
	}
	if(typeof (<Element>found).tagName === "string")
	{
		const element = <Element>found;
		const id = element.id ? ` id="${element.id}"` : "";
		const css = element.getAttribute("class") ? ` class="${element.getAttribute("class")}"` : "";
		return `<${element.tagName.toLowerCase()}${id}${css}>`;
	}
	// A NodeList or array of nodes: name the first few, so a failure says which ones turned up
	const list = Array.from(<ArrayLike<Element>>found);
	if(list.length === 0)
	{
		return "";
	}
	const named = list.slice(0, 3).map(node => describeFound(node)).join(", ");
	return list.length + (list.length === 1 ? " element: " : " elements: ") + named + (list.length > 3 ? ", ..." : "");
}

/**
 * Assert that a DOM lookup found nothing, naming what it found if it did.
 *
 * Use in place of assert.isNull() / assert.notExists() / assert.isUndefined() / assert.notOk()
 * whenever the value could be an element, a shadow root or a node list.
 *
 * @param {FoundElement} found Result of a querySelector(), querySelectorAll(), shadowRoot lookup etc.
 * @param {string} message What the test is actually checking.
 */
export function assertNoElement(found : FoundElement, message? : string) : void
{
	assert.equal(describeFound(found), "", message);
}
