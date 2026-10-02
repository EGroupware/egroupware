import {assert, elementUpdated, fixture, html, oneEvent} from "@open-wc/testing";
import * as sinon from "sinon";
import {emulateMedia} from "@web/test-runner-commands";
import {Et2Template} from "../../../Et2Template/Et2Template";
import {Et2Tabs} from "../Et2Tabs";
import {Et2TabsMobile} from "../Et2TabsMobile";
import {Et2Tab} from "../Et2Tab";
import {Et2TabPanel} from "../Et2TabPanel";
import {Et2Details} from "../../Et2Details/Et2Details";
import {Et2Description} from "../../../Et2Description/Et2Description";
import {et2_arrayMgr, et2_readonlysArrayMgr} from "../../../et2_core_arrayMgr";

/**
 * Printing a tabbox
 *
 * etemplate2.print() walks the widget tree and calls beforePrint() on every visible widget, the
 * tabbox before the widgets inside its panels.  So beforePrint() must not throw (that aborts the
 * whole print), and must make the panels visible synchronously - a widget in a panel that is still
 * hidden when the walk reaches it is skipped and never prepares for printing.
 */
// Stub global egw
// @ts-ignore
window.egw = {
	debug: () => {},
	debug_level: () => 0,
	lang: i => i + "",
	link: i => i,
	tooltipUnbind: () => {},
	webserverUrl: "",
	window: window
};

// Runtime references, so the imports are not erased as type-only and the elements register
const keepImport = [Et2Template, Et2Tabs, Et2TabsMobile, Et2Tab, Et2TabPanel, Et2Details, Et2Description];

function fakedTemplate(template_text)
{
	const parser = new window.DOMParser();
	return parser.parseFromString(template_text, "text/xml").children[0];
}

const tabTemplate = (tag) => `<overlay><template id="print_${tag}">
	<${tag} id="tabs">
		<tabs>
			<tab id="one" label="One"/>
			<tab id="two" label="Two"/>
			<tab id="three" label="Three" hidden="true"/>
			<tab id="four" label="Four"/>
		</tabs>
		<tabpanels>
			<et2-description id="d_one" value="One content"></et2-description>
			<et2-description id="d_two" value="Two content"></et2-description>
			<et2-description id="d_three" value="Three content"></et2-description>
			<et2-description id="d_four" value="Four content"></et2-description>
		</tabpanels>
	</${tag}>
</template></overlay>`;

for(const tag of ["et2-tabbox", "et2-tabbox_mobile"])
{
	Et2Template.templateCache["print_" + tag] = <Element>fakedTemplate(tabTemplate(tag)).childNodes.item(0);
}

async function load(tag : string) : Promise<Et2Tabs>
{
	// @ts-ignore
	const template : Et2Template = await fixture(html`<et2-template></et2-template>`);
	sinon.stub(template, "egw").returns(window.egw);
	template.setArrayMgr("content", new et2_arrayMgr({}));
	template.setArrayMgr("readonlys", new et2_readonlysArrayMgr({}));
	const loaded = oneEvent(template, "load");
	template.template = "print_" + tag;
	await loaded;

	const tabs = <Et2Tabs>template.querySelector(tag);
	await elementUpdated(tabs);
	await Promise.all(tabs.getAllPanels().map(p => (<any>p).updateComplete));
	return tabs;
}

const isVisible = (element : Element) => (<HTMLElement>element).offsetWidth > 0 || (<HTMLElement>element).offsetHeight > 0;
const visibleIds = (tabs : Et2Tabs) => ["d_one", "d_two", "d_three", "d_four"]
	.filter(id => isVisible(tabs.querySelector("#" + id)));

describe("Et2Tabs printing", () =>
{
	let tabs : Et2Tabs;
	beforeEach(async() =>
	{
		tabs = await load("et2-tabbox");
	});

	it("shows only the active tab before printing", () =>
	{
		assert.deepEqual(visibleIds(tabs), ["d_one"]);
	});

	it("beforePrint() does not throw", () =>
	{
		assert.doesNotThrow(() => tabs.beforePrint());
	});

	it("beforePrint() does not throw when no panel is active", async() =>
	{
		tabs.getAllPanels().forEach(p => p.active = false);
		tabs.getAllTabs(true).forEach(t => t.active = false);
		await elementUpdated(tabs);
		assert.isNull(tabs.querySelector("[active]"), "test setup left something active");

		assert.doesNotThrow(() => tabs.beforePrint());
	});

	it("beforePrint() shows every non-hidden panel synchronously and leaves hidden ones hidden", () =>
	{
		tabs.beforePrint();

		// No await - etemplate2.print() checks the panels' children in the same pass
		assert.deepEqual(visibleIds(tabs), ["d_one", "d_two", "d_four"]);
	});

	it("beforePrint() removes the panel height limit", async() =>
	{
		tabs.tabHeight = "30";
		await elementUpdated(tabs);
		const body = <HTMLElement>tabs.shadowRoot.querySelector(".tab-group__body");
		assert.equal(body.getBoundingClientRect().height, 30, "test setup did not limit the height");

		tabs.beforePrint();

		const panels = tabs.getAllPanels().filter(p => !p.hidden);
		const content = panels.reduce((sum, p) => sum + p.getBoundingClientRect().height, 0);
		assert.isAbove(content, 30, "test setup panels are not taller than the limit");
		assert.isAtLeast(body.getBoundingClientRect().height, content, "panel body is still height limited");
	});

	it("beforePrint() gives each panel its own height, not the tabbox's", async() =>
	{
		// A layout (eg. layout="edit") sizes the tabbox from outside, and tabHeight="auto" makes the
		// panels fill it (eg. timesheet.edit)
		const style = document.createElement("style");
		style.textContent = "et2-tabbox#tabs { height: 400px; }";
		document.head.append(style);
		try
		{
			tabs.tabHeight = "auto";
			await elementUpdated(tabs);
			tabs.beforePrint();

			for(const panel of tabs.getAllPanels().filter(p => !p.hidden))
			{
				// Each panel holds a single line of text
				const height = panel.getBoundingClientRect().height;
				assert.isBelow(height, 100, `panel ${panel.name} is stretched to the tabbox height`);
				assert.closeTo(height, panel.firstElementChild.getBoundingClientRect().height, 1,
					`panel ${panel.name} is not as high as its content`);
			}
		}
		finally
		{
			style.remove();
		}
	});

	it("prints each tab's label above its panel instead of the tab bar", async() =>
	{
		const nav = <HTMLElement>tabs.shadowRoot.querySelector(".tab-group__nav-container");
		const panel = (id) => <HTMLElement>tabs.querySelector(`et2-tab-panel[name="${id}"]`);
		const heading = (id) => getComputedStyle(panel(id), "::before").content;
		tabs.beforePrint();
		try
		{
			await emulateMedia({media: "print"});
			assert.equal(getComputedStyle(nav).display, "none", "tab bar is printed");
			// Firefox reports attr() in computed content unresolved
			for(const [id, label] of [["one", "One"], ["two", "Two"]])
			{
				assert.include(['"' + label + '"', "attr(data-print-label)"], heading(id), "no heading for " + id);
				assert.equal(panel(id).dataset.printLabel, label);
			}
		}
		finally
		{
			await emulateMedia({media: "screen"});
		}
		// On screen there are no headings and the tab bar stays
		assert.notEqual(getComputedStyle(nav).display, "none");
		assert.equal(heading("one"), "none");
	});

	it("afterPrint() restores the selected tab", async() =>
	{
		tabs.beforePrint();
		tabs.afterPrint();
		await elementUpdated(tabs);
		await Promise.all(tabs.getAllPanels().map(p => (<any>p).updateComplete));

		assert.deepEqual(visibleIds(tabs), ["d_one"]);
		assert.equal(tabs.value, "one");
	});

	it("afterPrint() restores the selected tab after the user switched tabs", async() =>
	{
		(<HTMLElement>tabs.querySelector('et2-tab[panel="two"]')).click();
		await elementUpdated(tabs);
		await Promise.all(tabs.getAllPanels().map(p => (<any>p).updateComplete));
		assert.deepEqual(visibleIds(tabs), ["d_two"], "test setup did not switch tabs");

		tabs.beforePrint();
		tabs.afterPrint();
		await elementUpdated(tabs);
		await Promise.all(tabs.getAllPanels().map(p => (<any>p).updateComplete));

		assert.deepEqual(visibleIds(tabs), ["d_two"]);
		assert.equal(tabs.value, "two");
	});
});

describe("Et2TabsMobile printing", () =>
{
	let tabs : Et2Tabs;
	beforeEach(async() =>
	{
		tabs = await load("et2-tabbox_mobile");
	});

	it("beforePrint() opens every non-hidden tab, afterPrint() closes the ones it opened", async() =>
	{
		const details = (id) => <any>tabs.shadowRoot.querySelector(`et2-details[id="${id}"]`);
		assert.isTrue(details("one").open, "test setup: first tab is not open");
		assert.isFalse(details("two").open, "test setup: second tab is open");

		assert.doesNotThrow(() => tabs.beforePrint());
		await Promise.all(["one", "two", "four"].map(id => details(id).updateComplete));
		assert.isTrue(details("two").open);
		assert.isTrue(details("four").open);
		assert.isFalse(details("three").open, "hidden tab was opened");

		tabs.afterPrint();
		await Promise.all(["one", "two", "four"].map(id => details(id).updateComplete));
		assert.isTrue(details("one").open, "tab that was open before printing got closed");
		assert.isFalse(details("two").open);
		assert.isFalse(details("four").open);
	});

	it("beforePrint() prepares the widgets in the tabs it opened, and waits for them", async() =>
	{
		// Make the descriptions printable widgets, d_two takes a while to get ready
		let ready;
		const calls = {};
		for(const id of ["d_one", "d_two", "d_three", "d_four"])
		{
			const widget = <any>tabs.querySelector("#" + id);
			calls[id] = [];
			widget.beforePrint = () =>
			{
				calls[id].push(isVisible(widget));
				return id == "d_two" ? new Promise(resolve => ready = resolve) : undefined;
			};
			widget.afterPrint = () => {};
		}

		let done = false;
		const prepared = (<Promise<void>><unknown>tabs.beforePrint()).then(() => done = true);
		// Wait until d_two was asked to prepare
		for(let i = 0; i < 100 && !calls["d_two"].length; i++)
		{
			await new Promise(resolve => setTimeout(resolve, 10));
		}

		assert.deepEqual(calls["d_two"], [true], "widget in a closed tab was not prepared once, while visible");
		assert.deepEqual(calls["d_four"], [true], "widget in a closed tab was not prepared once, while visible");
		assert.isEmpty(calls["d_one"], "widget in the open tab is etemplate2.print()'s to prepare, not ours");
		assert.isEmpty(calls["d_three"], "widget in a hidden tab was prepared");

		await new Promise(resolve => setTimeout(resolve, 50));
		assert.isFalse(done, "beforePrint() did not wait for the widget to get ready");
		ready();
		await prepared;
		assert.isTrue(done);
	});
});
