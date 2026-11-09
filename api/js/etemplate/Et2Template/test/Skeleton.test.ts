import {assert, fixture} from "@open-wc/testing";
import {html, render} from "lit";
import * as sinon from "sinon";
import {Et2Template} from "../Et2Template";

/**
 * An add / edit form shows a skeleton of itself while it loads, instead of the spinner
 */
describe("Et2Template loader", () =>
{
	let skeletonCss : sinon.SinonStub;

	beforeEach(() =>
	{
		skeletonCss = sinon.stub(<any>Et2Template, "skeletonCss").get(() => ".egw-skeleton{position:absolute}");
	});
	afterEach(() => skeletonCss.restore());

	const loader = async(template : string, skeleton = true) =>
	{
		const el = new Et2Template();
		el.template = template;
		el.skeleton = skeleton;
		const container = await fixture(html`<div></div>`);
		render(el.loadingTemplate(), <HTMLElement>container);
		return container;
	};

	it("shows the skeleton when the form wants one, whatever its name", async() =>
	{
		for(const name of ["infolog.edit", "timesheet.add_material", "mail.compose"])
		{
			const container = await loader(name);
			assert.isNotNull(container.querySelector(".egw-skeleton--template"), name);
			assert.isNull(container.querySelector("sl-spinner"), name);
		}
	});

	it("shows the spinner when it does not, whatever its name", async() =>
	{
		for(const name of ["infolog.edit", "api.about"])
		{
			const container = await loader(name, false);
			assert.isNull(container.querySelector(".egw-skeleton--template"), name);
			assert.isNotNull(container.querySelector("sl-spinner"), name);
		}
	});

	it("shows the spinner if the page did not bring the skeleton CSS", async() =>
	{
		skeletonCss.get(() => "");
		const container = await loader("infolog.edit");
		assert.isNull(container.querySelector(".egw-skeleton--template"));
		assert.isNotNull(container.querySelector("sl-spinner"));
	});
});
