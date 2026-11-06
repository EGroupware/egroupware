import {assert, elementUpdated, fixture, html, oneEvent, waitUntil} from "@open-wc/testing";
import * as sinon from "sinon";
import {Et2Template} from "../../../Et2Template/Et2Template";
import {Et2Tabs} from "../Et2Tabs";
import {Et2Tab} from "../Et2Tab";
import {Et2TabPanel} from "../Et2TabPanel";
import {Et2Description} from "../../../Et2Description/Et2Description";
import {Et2Checkbox} from "../../../Et2Checkbox/Et2Checkbox";
import {Et2Textarea} from "../../../Et2Textarea/Et2Textarea";
import {et2_arrayMgr, et2_readonlysArrayMgr} from "../../../et2_core_arrayMgr";

/**
 * Sizing a tabbox
 *
 * etemplate2 calls resize() with the spare (or, if negative, missing) space of the surrounding
 * template in px, and the tabbox adds that to its tabHeight.  tabHeight can be in any CSS unit, so
 * resize() has to start from the rendered height of the panel area instead of parseInt()ing the
 * value: parseInt("86vh") is 86, which collapsed an 800px panel to the 50px floor.
 *
 * Setup: a two-tab tabbox loaded from a faked template (like etemplate2 does) inside a 600px high
 * fixture, so 86vh and em resolve to a known number of px in the test browser.
 *
 * Pass: resize() changes the rendered body height by exactly the px passed, whatever unit
 * tabHeight was in; px / unitless values keep the plain-number behaviour; "auto" is untouched.
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
const keepImport = [Et2Template, Et2Tabs, Et2Tab, Et2TabPanel, Et2Description, Et2Checkbox, Et2Textarea];

function fakedTemplate(template_text)
{
	const parser = new window.DOMParser();
	return parser.parseFromString(template_text, "text/xml").children[0];
}

Et2Template.templateCache["size_tabbox"] = <Element>fakedTemplate(`<overlay><template id="size_tabbox">
	<et2-tabbox id="tabs">
		<tabs>
			<tab id="one" label="One"/>
			<tab id="two" label="Two"/>
		</tabs>
		<tabpanels>
			<et2-description id="d_one" value="One content"></et2-description>
			<et2-description id="d_two" value="Two content"></et2-description>
		</tabpanels>
	</et2-tabbox>
</template></overlay>`).childNodes.item(0);

async function load() : Promise<Et2Tabs>
{
	// @ts-ignore
	const template : Et2Template = await fixture(html`<et2-template style="display: block; height: 600px; font-size: 16px"></et2-template>`);
	sinon.stub(template, "egw").returns(window.egw);
	template.setArrayMgr("content", new et2_arrayMgr({}));
	template.setArrayMgr("readonlys", new et2_readonlysArrayMgr({}));
	const loaded = oneEvent(template, "load");
	template.template = "size_tabbox";
	await loaded;

	const tabs = <Et2Tabs>template.querySelector("et2-tabbox");
	await elementUpdated(tabs);
	// _sizeTabs() sets tabHeight once the first panel has loaded
	await waitUntil(() => tabs.tabHeight, "tabHeight never set");
	await elementUpdated(tabs);
	return tabs;
}

function bodyHeight(tabs : Et2Tabs) : number
{
	return Math.round(tabs.shadowRoot.querySelector(".tab-group__body").getBoundingClientRect().height);
}

async function setTabHeight(tabs : Et2Tabs, height : string)
{
	tabs.tabHeight = height;
	await elementUpdated(tabs);
}

describe("Tabbox sizing", () =>
{
	afterEach(() => sinon.restore());

	it("sizes to the first tab when its content fits, not to 86vh", async() =>
	{
		const tabs = await load();
		assert.notEqual(tabs.tabHeight, "86vh");
		assert.isBelow(bodyHeight(tabs), Math.round(0.86 * window.innerHeight));
	});

	for(const unit of ["86vh", "20em", "calc(10em + 100px)"])
	{
		it(`resize() adds the px to a tabHeight of ${unit}`, async() =>
		{
			const tabs = await load();
			await setTabHeight(tabs, unit);
			const before = bodyHeight(tabs);
			assert.isAbove(before, 200, "test needs a body taller than the 50px floor plus the shrink");

			tabs.resize(-100);
			await elementUpdated(tabs);
			assert.equal(bodyHeight(tabs), before - 100, "shrank by the missing space");

			tabs.resize(40);
			await elementUpdated(tabs);
			assert.equal(bodyHeight(tabs), before - 60, "grew by the spare space");
		});
	}

	for(const px of ["300", "300px"])
	{
		it(`resize() adds the px to a tabHeight of ${px}`, async() =>
		{
			const tabs = await load();
			await setTabHeight(tabs, px);

			tabs.resize(-100);
			assert.equal(tabs.tabHeight, "200");
			await elementUpdated(tabs);
			assert.equal(bodyHeight(tabs), 200);
		});
	}

	it("resize() does not shrink below 50px", async() =>
	{
		const tabs = await load();
		await setTabHeight(tabs, "20em");

		tabs.resize(-10000);
		assert.equal(tabs.tabHeight, "50");
	});

	it("resize() leaves tabHeight auto and a resize of 0 alone", async() =>
	{
		const tabs = await load();
		await setTabHeight(tabs, "20em");
		tabs.resize(0);
		assert.equal(tabs.tabHeight, "20em");

		await setTabHeight(tabs, "auto");
		tabs.resize(-100);
		assert.equal(tabs.tabHeight, "auto");
	});
});

/**
 * Progressive tab building
 *
 * The browser can only draw once the widgets of the visible tab exist, so a tabbox builds the tab
 * it shows first, lets the browser draw, and builds the others in later tasks.  Hidden tabs are not
 * lazy: everything exists before the tabbox (and so the template, and etemplate2's ready) is complete.
 *
 * Setup: a three-tab tabbox with an input in every tab, loaded from a faked template.  A MutationObserver
 * records the moment each tab's input is attached.
 *
 * Pass: when the first tab's input exists the other tabs' do not; the template's "load" event only comes after
 * all of them exist; the template's value read-out includes the values of the tabs that were built later; showing
 * a tab that is still empty builds it at once.
 */
Et2Template.templateCache["progressive_tabbox"] = <Element>fakedTemplate(`<overlay><template id="progressive_tabbox">
	<et2-tabbox id="tabs">
		<tabs>
			<tab id="one" label="One"/>
			<tab id="two" label="Two"/>
			<tab id="three" label="Three"/>
		</tabs>
		<tabpanels>
			<et2-checkbox id="t_one"></et2-checkbox>
			<et2-checkbox id="t_two"></et2-checkbox>
			<et2-textarea id="t_three"></et2-textarea>
		</tabpanels>
	</et2-tabbox>
</template></overlay>`).childNodes.item(0);

describe("Progressive tab building", () =>
{
	afterEach(() => sinon.restore());

	async function start(content = {}) : Promise<{ template : Et2Template, seen : string[], atFirst : string[], loaded : Promise<Event>, firstContent : Promise<void> }>
	{
		// @ts-ignore
		const template : Et2Template = await fixture(html`<et2-template style="display: block; height: 600px"></et2-template>`);
		sinon.stub(template, "egw").returns(window.egw);
		template.setArrayMgr("content", new et2_arrayMgr(content));
		template.setArrayMgr("readonlys", new et2_readonlysArrayMgr({}));

		// Order in which the inputs turn up in the document
		const seen : string[] = [];
		// What had turned up when the first one did.  The observer runs right after the task that added it, so
		// this is exact, where polling from the test would miss the later tabs being built in between.
		const atFirst : string[] = [];
		new MutationObserver(records =>
		{
			records.forEach(r => r.addedNodes.forEach((n : any) =>
			{
				[...(n.querySelectorAll?.("[id^=t_]") ?? []), ...(n.id?.startsWith("t_") ? [n] : [])]
					.forEach(t => seen.includes(t.id) || seen.push(t.id));
			}));
			if(seen.length && !atFirst.length)
			{
				atFirst.push(...seen);
			}
		}).observe(template, {childList: true, subtree: true});

		const firstContent = new Promise<void>(resolve => new MutationObserver((r, o) => {o.disconnect(); resolve();})
			.observe(template, {childList: true, subtree: true}));
		const loaded = oneEvent(template, "load");
		template.template = "progressive_tabbox";
		return {template, seen, atFirst, loaded, firstContent};
	}

	it("builds the first tab before the others", async() =>
	{
		const {template, seen, atFirst, loaded} = await start();
		await loaded;
		const tabs = <Et2Tabs>template.querySelector("et2-tabbox");

		assert.deepEqual(atFirst, ["t_one"], "only the first tab was built when its content first turned up");
		assert.deepEqual(seen, ["t_one", "t_two", "t_three"], "the others followed, in order");
		assert.equal(tabs.querySelectorAll("[id^=t_]").length, 3);
	});

	it("builds the tab the content asks for first, not the first in the list", async() =>
	{
		const {template, seen, atFirst, loaded} = await start({tabs: "two"});
		await loaded;
		const tabs = <Et2Tabs>template.querySelector("et2-tabbox");

		assert.deepEqual(atFirst, ["t_two"], "the selected tab was built first");
		assert.sameMembers(seen, ["t_one", "t_two", "t_three"], "the others followed");
		assert.equal(tabs.getActiveTab()?.panel, "two", "and it is the one shown");
	});

	it("starts with the first tab if the content names no such tab", async() =>
	{
		const {atFirst, loaded} = await start({tabs: "nonexistent"});
		await loaded;
		assert.deepEqual(atFirst, ["t_one"]);
	});

	it("is only complete, and the template only loaded, when every tab is built", async() =>
	{
		const {template, seen, loaded, firstContent} = await start();
		await firstContent;
		const tabs = <Et2Tabs>template.querySelector("et2-tabbox");

		// Count what exists at the moment each of them is done
		let atComplete = -1, atLoad = -1;
		tabs.updateComplete.then(() => atComplete = seen.length);
		loaded.then(() => atLoad = seen.length);

		await loaded;
		assert.equal(atLoad, 3, "load event fired before every tab was built");
		await tabs.updateComplete;
		assert.equal(atComplete, 3, "tabbox complete before every tab was built");
	});

	it("reads the values of tabs that were built later", async() =>
	{
		const {template, loaded} = await start({t_one: true, t_two: true, t_three: "3"});
		await loaded;
		await template.updateComplete;

		assert.equal(template.getValueById("t_one"), true);
		assert.equal(template.getValueById("t_two"), true, "tab that was not visible");
		assert.equal(template.getValueById("t_three"), "3", "tab that was not visible");
	});

	it("builds a tab at once when it is shown before its turn", async() =>
	{
		const {template, loaded, firstContent} = await start({t_three: "3"});
		// Right after the first content turned up, which is before the other tabs are built
		await firstContent;
		const tabs = <Et2Tabs>template.querySelector("et2-tabbox");
		assert.isNull(template.querySelector("#t_three"), "test needs the third tab to be still empty");

		tabs.show("three");
		assert.isNotNull(template.querySelector("#t_three"), "empty tab was shown");
		await loaded;
		assert.equal(tabs.querySelectorAll("[id^=t_]").length, 3, "not built twice");
	});
});
