/**
 * Shared test for the label/help-text/prefix/suffix slot convention.
 *
 * Every widget that wants `.et2-label-fixed` (Et2Widget.ts) or similar CSS to work has to expose
 * its label as `part="form-control-label"` (help-text: "form-control-help-text", also `"prefix"`
 * / `"suffix"`) - that's the only thing `::part()` styling can reach.  This is true whether the
 * widget gets there by subclassing a Shoelace form control directly (Et2Textbox extends
 * Et2InputWidget(SlInput), so Shoelace's own render() applies), by using the mixin's own
 * _labelTemplate()/_helpTextTemplate() helpers (Et2Date, Et2File, Et2Link*, ...), or by a fully
 * custom render() (Et2Description) - so the check here is deliberately black-box: does the right
 * `part=` show up with the right content, not how the widget gets there.
 *
 * label/help-text are also expected to disappear (or at least render no visible content) when
 * there's nothing to show, since several widgets conditionally skip rendering the wrapper
 * entirely to avoid an empty label-sized gap. Both directions are checked. prefix/suffix don't
 * get that treatment - they're declared unconditionally in every widget that has them (see the
 * grep evidence in doc/ai/projects/input-widget-test-coverage.md), so only "shows up when
 * slotted" is meaningful there.
 *
 * Usable on its own for widgets that aren't a full Et2InputWidget (e.g. Et2Description, which has
 * no readonly/required/get_value at all), and used internally by Et2InputWidget/test/
 * InputBasicTests.ts for the label/help-text checks every input widget gets by default.
 *
 * When "label" is checked, this also verifies `.et2-label-fixed` (Et2Widget.ts) actually gives
 * the label part a fixed width - that CSS hook is exactly why the `part=` convention matters in
 * the first place, so it's worth checking it actually works, not just that the part exists.
 *
 * @param before Function to create / setup the widget under test. Run before each test, must
 *    return the widget.
 * @param slotNames Which slots this widget is expected to support. Not auto-detected - a widget
 *    that's genuinely missing a slot (eg. Et2Description has no help-text) is not a failure, but
 *    only the caller knows which is which.
 * @param options.skipLabelFixed Widget's label part legitimately can't respect
 *    `.et2-label-fixed` - eg. Et2Description's label lives on a `display: contents` `<slot>`,
 *    which `width` has no effect on. Expect more of these as they're found; every one needs a
 *    real, documented reason at the call site.
 */

import {assert} from "@open-wc/testing";

export type WidgetSlotName = "label" | "help-text" | "prefix" | "suffix";

export interface WidgetSlotTestOptions
{
	skipLabelFixed? : boolean;
}

// The @csspart name each slot is expected to expose - see Et2InputWidget's _labelTemplate() /
// _helpTextTemplate(), and the matching `part=` attributes grepped across Et2Email, Et2VfsPath,
// Et2TreeDropdown, Et2LinkAdd, Et2SwitchIcon, Et2DateRange, Et2Description, and every Shoelace
// form control our widgets subclass directly (SlInput, SlSelect, ...).
const PART_NAME : Record<WidgetSlotName, string> = {
	"label": "form-control-label",
	"help-text": "form-control-help-text",
	"prefix": "prefix",
	"suffix": "suffix",
};

// label/help-text have a first-class property that's the normal way any real caller sets them.
// prefix/suffix don't - the only way to give a widget one is to slot an element in a template.
const PROPERTY_NAME : Partial<Record<WidgetSlotName, string>> = {
	"label": "label",
	"help-text": "helpText",
};

/**
 * Find the element carrying `part="X"` anywhere in the composed tree under `root`, crossing
 * nested shadow roots.
 *
 * Several of our widgets (Et2Select, Et2Toolbar) don't render the part themselves - they wrap a
 * Shoelace component as a *child* and re-expose its internal part with `exportparts="..."`
 * (confirmed empirically: Et2Select's own shadow root has no `[part]` at all, only a child
 * `<sl-select exportparts="form-control-label, ...">`; the actual `part="form-control-label"`
 * element lives inside *that* child's own shadow root). `exportparts` is a pure CSS/`::part()`
 * mechanism - the attribute is never copied outward, so a single-level `querySelector` can never
 * see it. Searching every nested shadow root is the only way to find it from test code, and is
 * correct regardless of how many `exportparts` hops away the real element is.
 */
function deepQueryPart(root : Element | ShadowRoot, part : string) : HTMLElement | null
{
	const direct = root.querySelector(`[part~="${part}"]`) as HTMLElement | null;
	if(direct)
	{
		return direct;
	}
	for(const el of Array.from(root.querySelectorAll("*")))
	{
		if(el.shadowRoot)
		{
			const found = deepQueryPart(el.shadowRoot, part);
			if(found)
			{
				return found;
			}
		}
	}
	return null;
}

/**
 * Is the named part actually showing something?  Node presence alone isn't enough - some widgets
 * (Et2Description) always render the `<slot part="...">` itself and only condition the fallback
 * content inside it, so an empty slot with no assigned nodes still matches the selector.
 *
 * The part may also carry an inner `<slot>` rather than be one itself - Shoelace's own
 * prefix/suffix pattern (confirmed empirically on Et2Textbox) is `<span part="prefix"><slot
 * name="prefix"></slot></span>`, so light-DOM content assigned from outside shows up on that
 * inner `<slot>`'s assignedNodes(), not on the wrapping `<span>` - `.textContent` on an ancestor
 * does not reach assigned content the way `assignedNodes()` does, it only sees the shadow tree's
 * own children (which is exactly right for fallback content like `${this.label}`, since that
 * content really is a child of the `<slot>` in the shadow tree).
 *
 * Deliberately NOT `assignedNodes({flatten: true})`: confirmed empirically (on Et2DateRange) that
 * `flatten: true` makes an empty slot's own *fallback* content (eg. the whitespace text nodes a
 * multi-line template leaves around `${this.helpText}` when it's "") come back as "assigned"
 * nodes even though nothing was ever slotted from outside - a false positive for "shows nothing
 * when not set". Plain `assignedNodes()` only returns genuinely externally-assigned nodes, and
 * whitespace-only text nodes are filtered out the same way `node.textContent.trim()` already is.
 *
 * checkVisibility() on the part node itself is only meaningful when that node generates its own
 * box.  A `<slot>` used directly as the part carrier (Et2Description) is `display: contents` -
 * it never has a box of its own even when its (fallback or assigned) content is fully visible, so
 * checkVisibility() on it is always false regardless of content - confirmed empirically, not
 * assumed. Content presence is the real signal there; checkVisibility() is still worth applying
 * to every other part shape (a real <label>/<div>), where a widget could otherwise hide it with
 * `display: none` while leaving the text content in place.
 */
function isPartShown(element : Element, part : string) : boolean
{
	const node = element.shadowRoot ? deepQueryPart(element.shadowRoot, part) : null;
	if(!node)
	{
		return false;
	}
	const slot = (node.tagName == "SLOT" ? node : node.querySelector("slot")) as HTMLSlotElement | null;
	const hasAssigned = !!slot && slot.assignedNodes().some(
		n => n.nodeType === Node.ELEMENT_NODE || (n.textContent || "").trim() !== ""
	);
	const hasContent = hasAssigned || node.textContent.trim() !== "";
	if(!hasContent)
	{
		return false;
	}
	return getComputedStyle(node).display === "contents" || node.checkVisibility();
}

/**
 * Some widgets (confirmed on Et2Email) need a second update cycle to fully settle after a
 * property change - the first update reacts to the property itself, and something it triggers
 * (eg. a HasSlotController-driven or aria-attribute follow-up) only lands on the update after
 * that. A single `await element.updateComplete` isn't reliably enough; this is.
 */
async function settle(element : { updateComplete : Promise<unknown> }) : Promise<void>
{
	await element.updateComplete;
	await element.updateComplete;
}

export function widgetSlotTests(before : Function, slotNames : WidgetSlotName[], options : WidgetSlotTestOptions = {})
{
	describe("Slots", () =>
	{
		let element : Element & { updateComplete : Promise<unknown>, [key : string] : any };

		beforeEach(async() =>
		{
			element = await before();
		});

		slotNames.forEach((name) =>
		{
			const part = PART_NAME[name];
			const property = PROPERTY_NAME[name];

			describe(`"${name}" (part="${part}")`, () =>
			{
				if(property)
				{
					it("shows nothing when not set", async() =>
					{
						// Clear explicitly rather than trusting a fresh before() fixture to have
						// no label/help-text - several widgets' own test fixtures set one in the
						// template as a realistic usage example (eg. Et2Date's
						// `<et2-date label="I'm a date">`), which isn't a bug, just not what this
						// particular check needs.
						element[property] = "";
						await settle(element);

						assert.isFalse(isPartShown(element, part),
							`part="${part}" is showing something with no ${property} set - it should be empty / not rendered`
						);
					});

					it(`shows the ${name} when ${property} is set`, async() =>
					{
						const text = `Test ${name} content`;
						element[property] = text;
						await settle(element);

						assert.isTrue(isPartShown(element, part),
							`part="${part}" did not show up after setting ${property}`
						);
						assert.include(
							deepQueryPart(element.shadowRoot, part).textContent,
							text,
							`part="${part}" did not contain the ${property} text`
						);
					});

					if(name === "label" && !options.skipLabelFixed)
					{
						it("respects .et2-label-fixed", async() =>
						{
							element[property] = `Test ${name} content`;
							await settle(element);

							element.classList.add("et2-label-fixed");
							await settle(element);

							const node = deepQueryPart(element.shadowRoot, part);
							assert.exists(node, `part="${part}" not found with .et2-label-fixed applied`);

							// Et2Widget.ts's :host(.et2-label-fixed) rule fixes the label to
							// var(--label-width, 8em) - no override is set here, so the default
							// 8em (relative to the label's own font-size) is what should apply.
							// Read the CSS width property itself (getComputedStyle), not the
							// rendered box (getBoundingClientRect) - confirmed empirically that
							// the two can disagree (flex-layout sizing noise unrelated to the
							// width property this check cares about, and inconsistently so
							// between browsers), while getComputedStyle().width reliably reflects
							// exactly what the CSS rule set.
							const style = getComputedStyle(node);
							const expectedWidth = 8 * parseFloat(style.fontSize);
							assert.approximately(
								parseFloat(style.width),
								expectedWidth,
								1,
								`part="${part}" is not a fixed ~8em wide with .et2-label-fixed applied`
							);
						});
					}
				}
				else
				{
					it(`shows content slotted into "${name}"`, async() =>
					{
						const probe = document.createElement("span");
						probe.slot = name;
						probe.textContent = `probe-${name}`;
						element.appendChild(probe);
						await settle(element);

						assert.isTrue(isPartShown(element, part),
							`part="${part}" did not show up after slotting content into it`
						);
						assert.isTrue(probe.checkVisibility(), `Content slotted into "${name}" is not visible`);

						element.removeChild(probe);
					});
				}
			});
		});
	});
}
