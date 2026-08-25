import {assert, fixture, html} from "@open-wc/testing";
import "../Et2Link";
import "../Et2LinkString";

// Et2LinkString's constructor reads the "maxmatchs" preference before any test code gets a
// chance to override egw() on the instance, so the fallback needs to be a thenable up front.
window.egw = Object.assign(() => window.egw, {
	preference: () => Promise.resolve(20),
	lang: (l : string) => l,
	debug: () => {},
	// Rendered <et2-link> children (one per parsed id) call these during their own lifecycle
	image: () => "",
	link_title: () => Promise.resolve(""),
	tooltipBind: () => {},
	tooltipUnbind: () => {}
}) as any;

/**
 * Regression coverage: an empty-string value must render no links at all, not a phantom
 * entry that ends up showing the "??" MISSING_TITLE placeholder forever (see Et2Link.test.ts
 * for the sentinel's own contract).
 */
describe("Et2LinkString", () =>
{
	it("renders no links for an empty string value", async() =>
	{
		const element = await fixture<any>(html`<et2-link-string></et2-link-string>`);

		element.set_value("");
		await element.updateComplete;

		assert.isEmpty(element._link_list);
		assert.isNull(element.shadowRoot.querySelector("et2-link"));
	});

	it("still parses a normal CSV list of ids", async() =>
	{
		const element = await fixture<any>(html`<et2-link-string application="addressbook"></et2-link-string>`);

		element.set_value("1,2,3");
		await element.updateComplete;

		assert.lengthOf(element._link_list, 3);
		assert.deepEqual(element._link_list.map(l => l.id), ["1", "2", "3"]);
	});

	it("ignores a trailing comma instead of adding an empty entry", async() =>
	{
		const element = await fixture<any>(html`<et2-link-string application="addressbook"></et2-link-string>`);

		element.set_value("1,2,");
		await element.updateComplete;

		assert.lengthOf(element._link_list, 2);
	});
});
