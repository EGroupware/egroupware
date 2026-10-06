/**
 * Layout of widgets that carry a label in a fixed-width column (`.et2-label-fixed`).
 *
 * WidgetSlotTests checks that the label *part* gets its fixed width.  That is only half of what a
 * form needs from these widgets: a row of labels and values only lines up if every kind of widget
 * - editable or read-only - also keeps the value beside the label while there is room, takes the
 * same row height as the input it stands in for, and drops the value (or field) below the label
 * when there is not.  A read-only widget is a hand-rolled label + value, so none of that comes for
 * free the way it does for an editable field built on a Shoelace form control.
 *
 * Behaviour under test:
 * - read-only widgets: value on the label's row while it fits, below the label when it does not,
 *   and a row at least as tall as an input
 * - Et2DateRange / Et2LinkEntry: label stays beside the field while the field fits, moves above it
 *   when it does not, and the two parts of the field (From/To, app select/search) share a row while
 *   there is room
 * - Et2LinkSearch keeps its height when it is focused, so what is below it does not move
 *
 * Not covered: that Et2LinkEntry's field row sizes itself by what it needs rather than by what is in
 * the search, and that opening the search does not move it onto its own row.  Both depend on the real
 * width of the theme's controls and of an open dropdown, which this page does not have - the layouts
 * they replaced also fit here, so a test could not go red.  Those two were checked in a real page.
 *
 * Pass criteria:
 * Positions are read from the rendered boxes (label, value text, field) in a container of a known
 * width, so a layout that merely looks different but puts things in the same places still passes.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */
import {assert, fixture, html} from "@open-wc/testing";

const stub = {
	ajaxUrl: url => url,
	decodePath: url => url,
	lang: i => i,
	tooltipUnbind: () => {},
	tooltipBind: () => {},
	image: () => "",
	preference: (pref) => pref == "lang" ? Promise.resolve("en") : "",
	holidays: () => Promise.resolve({}),
	link_title: (_app : string, id : string) => Promise.resolve("Title " + id),
	accounts: () => Promise.resolve([]),
	webserverUrl: "",
	config: () => "",
	user: () => 1,
	app: () => "api",
	getSessionItem: () => null,
	setSessionItem: () => {},
	debug: () => {},
	// What a link search asks the server for: no results
	request: () => Promise.resolve({total: 0, results: []})
};
const callableEgw = function() { return stub; };
Object.assign(callableEgw, stub);
// @ts-ignore
window.egw = callableEgw;
// @ts-ignore
window.egwIsMobile = () => false;

// Widgets that have to be registered for the ones under test to render completely
import "@shoelace-style/shoelace/dist/components/select/select.js";
import "@shoelace-style/shoelace/dist/components/option/option.js";
import "../../Et2Textbox/Et2Number";
import "../../Et2Date/Et2Date";
import "../../Et2Date/Et2DateDuration";
import "../../Et2Date/Et2DateRange";
import "../../Et2Date/Et2DateReadonly";
import "../../Et2Date/Et2DateTimeReadonly";
import "../../Et2Date/Et2DateTimeOnlyReadonly";
import "../../Et2Date/Et2DateDurationReadonly";
import "../../Et2Date/Et2DateTimeToday";
import "../../Et2Description/Et2Description";
import "../../Et2Textbox/Et2TextboxReadonly";
import "../../Et2Textbox/Et2NumberReadonly";
import "../../Et2Select/Select/Et2SelectReadonly";
import "../../Et2Vfs/Et2VfsSize";
import "../../Et2Link/Et2LinkEntry";
import "../../Et2Link/Et2LinkSearch";

const LABEL_PART = "form-control-label";

type TestElement = HTMLElement & { updateComplete : Promise<unknown>, [key : string] : any };

/**
 * Create a widget with a label inside a container of a given width.
 */
async function place(tag : string, setup : (el : TestElement) => void, width : number) : Promise<{ container : HTMLElement, element : TestElement }>
{
	const container = await fixture<HTMLElement>(html`
        <div style="width: ${width}px; position: relative; font-size: 16px"></div>`);
	const element = <TestElement>document.createElement(tag);
	element.setAttribute("label", "Label");
	element.classList.add("et2-label-fixed");
	setup(element);
	container.appendChild(element);
	await element.updateComplete;
	await element.updateComplete;
	// Fonts and nested widgets settle after the first paint
	await new Promise(resolve => setTimeout(resolve, 100));
	return {container, element};
}

function deepFind(root : Node, match : (el : Element) => boolean) : Element | null
{
	const kids : Node[] = Array.from(root.childNodes);
	if((<Element>root).shadowRoot)
	{
		kids.push(...Array.from((<Element>root).shadowRoot.childNodes));
	}
	for(const kid of kids)
	{
		if(kid.nodeType === Node.ELEMENT_NODE)
		{
			if(match(<Element>kid))
			{
				return <Element>kid;
			}
			const found = deepFind(kid, match);
			if(found)
			{
				return found;
			}
		}
	}
	return null;
}

function labelBox(element : Element) : DOMRect
{
	const label = deepFind(element, el => (el.getAttribute("part") ?? "").split(" ").includes(LABEL_PART) &&
		el.getBoundingClientRect().height > 0);
	assert.exists(label, "No visible label part found");
	return label.getBoundingClientRect();
}

/**
 * Box of the value: the first text node (light DOM or any shadow root) matching the pattern, or the
 * first <input> whose value does, for widgets that show their value in a field
 */
function textBox(element : Element, pattern : RegExp) : DOMRect
{
	const find = (node : Node) : Text | HTMLInputElement | null =>
	{
		const kids : Node[] = Array.from(node.childNodes);
		if((<Element>node).shadowRoot)
		{
			kids.push(...Array.from((<Element>node).shadowRoot.childNodes));
		}
		for(const kid of kids)
		{
			if(kid.nodeType === Node.TEXT_NODE && pattern.test(kid.textContent ?? ""))
			{
				return <Text>kid;
			}
			if(kid instanceof HTMLInputElement && pattern.test(kid.value ?? "") && kid.getBoundingClientRect().height > 0)
			{
				return kid;
			}
			const found = find(kid);
			if(found)
			{
				return found;
			}
		}
		return null;
	};
	const found = find(element);
	assert.exists(found, `No text or input value matching ${pattern} found in the widget`);
	if(found instanceof HTMLInputElement)
	{
		return found.getBoundingClientRect();
	}
	const range = document.createRange();
	range.selectNodeContents(found);
	return range.getBoundingClientRect();
}

/**
 * Is the select showing its search row in place of the selected text?
 */
function searchRowShown(select : Element) : boolean
{
	const row = deepFind(select, el => el.classList.contains("search_input"));
	return !!row && getComputedStyle(row).display !== "none" && row.getBoundingClientRect().height > 0;
}

// What a real page gets from the Shoelace theme: the height of an input, and the gap an editable field
// leaves between its label and its input.
const THEME_VARS : Record<string, string> = {
	"--sl-input-height-medium": "2.5rem",
	"--sl-spacing-medium": "1rem",
	// The select's combobox has a border of this width; the search row has to fit inside it
	"--sl-input-border-width": "1px",
	"--sl-input-border-color": "#999"
};

describe("Fixed label layout", () =>
{
	const previous : Record<string, string> = {};
	before(() =>
	{
		for(const [name, value] of Object.entries(THEME_VARS))
		{
			previous[name] = document.documentElement.style.getPropertyValue(name);
			document.documentElement.style.setProperty(name, value);
		}
	});
	after(() =>
	{
		for(const name of Object.keys(THEME_VARS))
		{
			if(previous[name])
			{
				document.documentElement.style.setProperty(name, previous[name]);
			}
			else
			{
				document.documentElement.style.removeProperty(name);
			}
		}
	});

	describe("read-only widgets", () =>
	{
		const readonlyWidgets : { tag : string, setup : (el : TestElement) => void, value : RegExp }[] = [
			{tag: "et2-textbox_ro", setup: el => el.value = "Some value", value: /Some value/},
			{tag: "et2-number_ro", setup: el => el.value = "42", value: /42/},
			{tag: "et2-description", setup: el => el.value = "Some value", value: /Some value/},
			{tag: "et2-date_ro", setup: el => el.value = "2026-10-06T10:00:00Z", value: /2026/},
			{tag: "et2-date-time_ro", setup: el => el.value = "2026-10-06T10:00:00Z", value: /2026/},
			{tag: "et2-date-timeonly_ro", setup: el => el.value = "2026-10-06T10:00:00Z", value: /\d:\d\d/},
			{tag: "et2-date-duration_ro", setup: el => el.value = "90", value: /1:30|1,5|1\.5/},
			{tag: "et2-vfs-size", setup: el => el.value = 2048, value: /2/},
			{
				tag: "et2-select_ro", setup: el =>
				{
					el.select_options = [{value: "a", label: "Option A"}];
					el.value = "a";
				}, value: /Option A/
			}
		];

		readonlyWidgets.forEach(({tag, setup, value}) =>
		{
			describe(tag, () =>
			{
				it("keeps the value on the label's row while it fits", async() =>
				{
					const {element} = await place(tag, setup, 600);
					const label = labelBox(element);
					const text = textBox(element, value);

					assert.isBelow(text.top, label.bottom,
						"The value is not beside the label although there is plenty of room");
				});

				it("drops the value below the label when it does not fit", async() =>
				{
					// Room for the label column and its gap, but not for any value beside it
					const wide = await place(tag, setup, 600);
					const labelWidth = labelBox(wide.element).width;
					const {element} = await place(tag, setup, Math.ceil(labelWidth + 24));
					const label = labelBox(element);
					const text = textBox(element, value);

					assert.isAtLeast(text.top, label.bottom - 1,
						"The value is squeezed beside the label instead of wrapping below it");
				});

				it("is as tall as an input", async() =>
				{
					const {element} = await place(tag, setup, 600);
					const inputHeight = 2.5 * parseFloat(getComputedStyle(document.documentElement).fontSize);

					assert.isAtLeast(element.getBoundingClientRect().height, inputHeight - 1,
						"The row is shorter than the input the value stands in for");
				});
			});
		});
	});

	describe("Et2DateRange", () =>
	{
		const setup = el =>
		{
			el.value = {from: "2026-10-03", to: "2026-10-31"};
		};

		it("keeps the label beside the dates while they fit", async() =>
		{
			const {element} = await place("et2-date-range", setup, 440);
			const label = labelBox(element);
			const from = textBox(element, /2026-10-03/);

			assert.isBelow(from.top, label.bottom, "The label is above the dates although there is room");
		});

		it("puts the label above the dates when they do not fit", async() =>
		{
			const {element} = await place("et2-date-range", setup, 140);
			const label = labelBox(element);
			const from = textBox(element, /2026-10-03/);

			assert.isAtLeast(from.top, label.bottom - 1);
		});

		it("shows From and To side by side while there is room, one above the other when there is not", async() =>
		{
			const wide = await place("et2-date-range", setup, 600);
			const sameRow = (element : Element) =>
				Math.abs(textBox(element, /2026-10-03/).top - textBox(element, /2026-10-31/).top) < 2;
			assert.isTrue(sameRow(wide.element), "From and To are not on one row in a wide container");

			const narrow = await place("et2-date-range", setup, 150);
			assert.isFalse(sameRow(narrow.element), "From and To did not stack in a narrow container");
		});

		it("does not overflow a narrow container", async() =>
		{
			const {container, element} = await place("et2-date-range", setup, 150);
			const to = textBox(element, /2026-10-31/);

			assert.isAtMost(to.right, container.getBoundingClientRect().right + 1,
				"The To date sticks out of its container");
		});
	});

	describe("Et2LinkEntry", () =>
	{
		// With an entry selected the search is as wide as the title, which is what makes a field that sizes
		// itself by its content ask for more room than it needs
		const setup = (el : TestElement) =>
		{
			el.value = {app: "infolog", id: "123", title: "A very long entry title, as long as an infolog subject can be, that is shown in the search ".repeat(3)};
		};

		it("keeps the label beside the field while it fits", async() =>
		{
			// Label column, app select and the 200px search just fit in this; a field that sized itself
			// by its content would already have pushed the label above it
			const {element} = await place("et2-link-entry", setup, 440);
			const label = labelBox(element);
			const app = element.shadowRoot.querySelector("et2-link-apps").getBoundingClientRect();

			assert.isBelow(app.top, label.bottom, "The label is above the field although there is room");
		});

		it("puts the label above the field when it does not fit", async() =>
		{
			const {element} = await place("et2-link-entry", setup, 200);
			const label = labelBox(element);
			const app = element.shadowRoot.querySelector("et2-link-apps").getBoundingClientRect();

			assert.isAtLeast(app.top, label.bottom - 1);
		});

		it("keeps the search on the app select's row while there is room", async() =>
		{
			const {element} = await place("et2-link-entry", setup, 700);
			const app = element.shadowRoot.querySelector("et2-link-apps").getBoundingClientRect();
			const search = element.shadowRoot.querySelector("et2-link-search").getBoundingClientRect();

			assert.approximately(search.top, app.top, 2);
		});

		it("keeps its height when the search is focused", async() =>
		{
			const {element} = await place("et2-link-entry", setup, 700);
			const before = element.getBoundingClientRect().height;

			const search = element.shadowRoot.querySelector<any>("et2-link-search");
			search.focus();
			await new Promise(resolve => setTimeout(resolve, 500));
			assert.isTrue(searchRowShown(search), "The search row did not open, so this tests nothing");

			assert.approximately(element.getBoundingClientRect().height, before, 0.5,
				"Focusing the search made the widget taller, pushing what is below it down");
		});
	});

	describe("Et2LinkSearch", () =>
	{
		it("keeps its height when it is focused", async() =>
		{
			const {element} = await place("et2-link-search", () => {}, 400);
			const before = element.getBoundingClientRect().height;

			element.focus();
			await new Promise(resolve => setTimeout(resolve, 500));
			assert.isTrue(searchRowShown(element), "The search row did not open, so this tests nothing");

			assert.approximately(element.getBoundingClientRect().height, before, 0.5,
				"Focusing the search made the widget taller, pushing what is below it down");
		});
	});
});
