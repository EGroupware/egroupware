import {assert} from "@open-wc/testing";
import "../ColumnSelection.ts";

const egw = {
	lang: (label : string) => label,
	user: () => ({})
};
window.egw = function() { return egw; } as any;
Object.assign(window.egw, egw);

describe("Et2ColumnSelection", () =>
{
	/**
	 * Contract under test:
	 * - Datagrid column selection metadata with nested customFields renders
	 *   individual customfield checkbox rows.
	 *
	 * Setup strategy:
	 * - Instantiate the column selector with one customfields-aware column using
	 *   the metadata produced by Et2DatagridColumnState.
	 *
	 * Pass criteria:
	 * - The dialog contains one checkbox for each customfield child.
	 */
	it("renders individual customfields from datagrid selection metadata", async() =>
	{
		const selector = document.createElement("et2-nextmatch-columnselection") as any;
		selector.columns = [{
			id: "customfields",
			title: "Custom fields",
			caption: "Custom fields",
			widget: document.createElement("et2-nextmatch-header-customfields"),
			visibility: true,
			isCustomfields: true,
			customFields: [
				{id: "cf_text", name: "cf_text", caption: "Text", visibility: true},
				{id: "cf_private", name: "cf_private", caption: "Private", visibility: false}
			]
		}];
		document.body.append(selector);
		await selector.updateComplete;

		const values = Array.from(selector.shadowRoot.querySelectorAll("sl-menu-item"))
			.map((item : any) => item.value);
		assert.includeMembers(values, ["customfields", "cf_text", "cf_private"], "customfield child rows should be rendered");

		selector.remove();
	});
});
