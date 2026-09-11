import {assert, elementUpdated, fixture, html} from "@open-wc/testing";
import "../Et2VfsSize";

window.egw = {
	app_name: () => "filemanager",
	debug: () => {},
	lang: (label : string) => label,
	preference: () => "en"
} as any;

describe("Et2VfsSize", () =>
{
	it("uses set_value() for legacy Et2 row binding", async() =>
	{
		const element = await fixture<HTMLElement & {set_value : Function; value : number}>(html`<et2-vfs-size></et2-vfs-size>`);

		element.set_value({size: "1536"});
		await elementUpdated(element);

		assert.equal(element.value, 1536);
		assert.notInclude(element.shadowRoot?.textContent || "", "0 byte", "valid size should replace Shoelace's default value");
	});

	it("renders nothing for empty or invalid sizes", async() =>
	{
		const element = await fixture<HTMLElement & {set_value : Function; value : number}>(html`<et2-vfs-size></et2-vfs-size>`);

		await elementUpdated(element);
		assert.equal(element.shadowRoot?.textContent?.trim() || "", "", "unset size should render empty");

		element.set_value("");
		await elementUpdated(element);
		assert.equal(element.shadowRoot?.textContent?.trim() || "", "", "empty size should render empty");
	});
});
