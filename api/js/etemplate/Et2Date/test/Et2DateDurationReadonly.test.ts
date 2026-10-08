/**
 * Test file for the unitDisplay option of Etemplate webComponent Et2DateDurationReadonly
 */
import {assert, fixture, html} from '@open-wc/testing';
import {Et2DateDurationReadonly} from "../Et2DateDurationReadonly";
// The class is only used as a type below, import the module itself to register the element
import "../Et2DateDurationReadonly";
import "@shoelace-style/shoelace/dist/components/select/select.js";
import "@shoelace-style/shoelace/dist/components/option/option.js";
import "../../Et2Textbox/Et2Number";
import * as sinon from "sinon";

/**
 * Create a readonly duration, with the user's "lang" preference stubbed
 */
async function createDuration(lang : string, value : number, attrs = "") : Promise<Et2DateDurationReadonly>
{
	const element = <Et2DateDurationReadonly>await fixture(
		html`<et2-date-duration_ro unitDisplay="long" label=${attrs}></et2-date-duration_ro>`);
	sinon.stub(element, "egw").returns(<any>{
		lang: i => i,
		tooltipUnbind: () => {},
		preference: (name : string) => name == "lang" ? lang : ""
	});
	element.value = value;
	await element.updateComplete;
	return element;
}

const shown = (element : Et2DateDurationReadonly) => element.shadowRoot.textContent.replace(/\s+/g, " ").trim();

describe("Et2DateDurationReadonly unitDisplay", () =>
{
	afterEach(() => sinon.restore());

	it("spells the best unit out, singular and plural", async() =>
	{
		assert.equal(shown(await createDuration("en", 1)), "1 minute");
		assert.equal(shown(await createDuration("en", 45)), "45 minutes");
		assert.equal(shown(await createDuration("en", 120)), "2 hours");
		// 8 hour working days
		assert.equal(shown(await createDuration("en", 480 * 30)), "30 days");
	});

	it("writes the unit in the user's language, not the browser's", async() =>
	{
		assert.equal(shown(await createDuration("de", 480 * 30)), "30 Tage");
		assert.equal(shown(await createDuration("de", 1)), "1 Minute");
		assert.equal(shown(await createDuration("fr", 120)), "2 heures");
	});

	it("falls back to the language of the page, when the preference is not there yet", async() =>
	{
		const old = document.documentElement.lang;
		document.documentElement.lang = "de";
		try
		{
			assert.equal(shown(await createDuration("", 120)), "2 Stunden");
		}
		finally
		{
			document.documentElement.lang = old;
		}
	});

	it("survives a language the browser does not know", async() =>
	{
		assert.match(shown(await createDuration("not a language", 120)), /2/);
	});

	it("shows nothing, not even the label, for an empty duration", async() =>
	{
		assert.equal(shown(await createDuration("en", 0, "Match again after")), "");
	});

	it("shows the label before the duration", async() =>
	{
		assert.equal(shown(await createDuration("en", 120, "Match again after")), "Match again after 2 hours");
	});
});
