import {assert} from "@open-wc/testing";
import "../ColumnSelection.ts";

const egw = {
	lang: (label : string) => label,
	user: () => ({}),
	link: (url : string) => url
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

	/**
	 * Contract under test:
	 * - Unchecking the customfields parent column does not discard remembered
	 *   individual customfield rows.
	 *
	 * Setup strategy:
	 * - Render a customfields-aware column with a checked child field.
	 * - Uncheck only the parent row.
	 *
	 * Pass criteria:
	 * - The selector value omits the parent column but keeps the child field.
	 */
	it("keeps checked customfield children when parent column is unchecked", async() =>
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
				{id: "cf_text", name: "cf_text", caption: "Text", visibility: true}
			]
		}];
		document.body.append(selector);
		await selector.updateComplete;

		const parent = selector.shadowRoot.querySelector("sl-menu-item[value='customfields']") as any;
		const child = selector.shadowRoot.querySelector("sl-menu-item[value='cf_text']") as any;
		parent.checked = false;
		child.checked = true;

		assert.notInclude(selector.value, "customfields", "parent customfields column should be omitted");
		assert.include(selector.value, "cf_text", "child field should remain selected when parent is unchecked");

		selector.remove();
	});
});
