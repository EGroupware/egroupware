import {assert} from "@open-wc/testing";
import {Et2DatagridRowRenderer} from "../Et2DatagridRowRenderer";

/**
 * Contract: resolving a mounted row's deferred template attributes leaves the row's
 * identity alone.  The row root's stored attributes (eg. filemanager's
 * `class="row $row_cont[class]"`) are resolved again once the row is mounted, when it
 * already carries `data-row-id`.  Resolving every attribute treated a "{x}" or "$x"
 * inside the id as a row placeholder and blanked it, so a file named "brace{test}.txt"
 * got the id of a non-existing "brace.txt" and could not be selected or acted on.
 *
 * Setup: a stub host resolving fields from plain row data, and a mounted row whose
 * data-row-id holds both placeholder forms.
 *
 * Pass criteria: the stored class is resolved from the row, the identity attributes
 * keep their exact values.
 */
describe("Et2DatagridRowRenderer row identity", () =>
{
	const applyStored = (rowRoot : HTMLElement, stored : Record<string, string>, rowData : any) =>
	{
		const host = {_getFieldValue: (row : any, key : string) => row?.[key]} as any;
		new Et2DatagridRowRenderer(host)["applyRowRootStoredAttributes"](rowRoot, stored, rowData);
	};

	it("keeps braces and dollar signs in data-row-id when resolving stored attributes", () =>
	{
		const rowId = "filemanager::/home/user/brace{test}$name.txt";
		const rowRoot = document.createElement("tr");
		rowRoot.setAttribute("data-row-id", rowId);
		rowRoot.setAttribute("data-et2dg-upgraded-for", `row:${rowId}`);

		applyStored(rowRoot, {class: "row $class"}, {class: "file", test: "", name: "", path: "/home/user/brace{test}$name.txt"});

		assert.equal(rowRoot.getAttribute("data-row-id"), rowId, "row id must not be resolved as a template expression");
		assert.equal(rowRoot.getAttribute("data-et2dg-upgraded-for"), `row:${rowId}`);
		assert.include(rowRoot.className.split(/\s+/), "file", "the template's own class placeholder is still resolved");
	});
});
