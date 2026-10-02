import {assert, elementUpdated, fixture, html, oneEvent, waitUntil} from "@open-wc/testing";
import * as sinon from "sinon";
import {Et2Template} from "../../../Et2Template/Et2Template";
import {Et2Tabs} from "../Et2Tabs";
import {Et2Tab} from "../Et2Tab";
import {Et2TabPanel} from "../Et2TabPanel";
import {Et2Description} from "../../../Et2Description/Et2Description";
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
const keepImport = [Et2Template, Et2Tabs, Et2Tab, Et2TabPanel, Et2Description];

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
