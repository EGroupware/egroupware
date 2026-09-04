import {assert} from "@open-wc/testing";
import {Et2Nextmatch} from "../Et2Nextmatch";
import type {Et2Datagrid} from "../../Et2Datagrid/Et2Datagrid";
import {et2_arrayMgr} from "../../et2_core_arrayMgr";

/**
 * Contract under test:
 * - A row template can hide a column with an expression, eg. infolog's
 *   `<column disabled="@no_customfields"/>`, and the server sends the flag it reads among the
 *   non-numeric keys of the rows response.
 * - Those keys are written into the content array manager, which is plain data rather than a
 *   reactive property, so the datagrid has to be told to look again.
 *
 * Setup strategy:
 * - Build a nextmatch with a content array manager and three template columns, one of them
 *   carrying a `@`-expression in `disabled`.
 * - Push the flag through the same entry point the server response uses,
 *   `Et2NextmatchDataProvider.processAdditionalData()`.
 *
 * Pass criteria:
 * - The conditional column renders while the flag is absent or false.
 * - It disappears from the rendered header once the flag arrives, without any other change.
 */

const egwStub = {
	lang: (label : string) => label,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	image: () => "",
	preference: () => null,
	set_preference: () => {},
	app_name: () => "infolog",
	link: (url : string) => url,
	dataFetch: (_execId, _request, _filters, _widgetId, callback) => callback({order: [], total: 0}),
	dataRegisterUID: (_uid, callback) => callback({}, "row::1"),
	debug: () => {}
};
window.egw = function() { return egwStub; } as any;
Object.assign(window.egw, egwStub);

const COLUMNS = [
	{key: "info_subject", title: "Subject"},
	{key: "customfields", title: "Custom fields", disabled: "@no_customfields"},
	{key: "info_datemodified", title: "Last modified"}
];

/**
 * Connect a nextmatch whose columns come straight from template data, skipping the
 * template fetch a real one would do.
 */
async function createNextmatch(content : Record<string, any>) : Promise<Et2Nextmatch>
{
	const el = new Et2Nextmatch();
	el.id = "nm";
	el.setArrayMgr("content", new et2_arrayMgr(content));
	document.body.append(el);
	await el.updateComplete;
	(el as any)._applyTemplateData({
		columns: COLUMNS.map((column) => ({...column})),
		rowTemplate: null,
		rowTemplateXml: null,
		rowTemplateAttrMap: {},
		loaderTemplate: null
	});
	el.requestUpdate();
	await el.updateComplete;
	await datagrid(el).updateComplete;
	return el;
}

function datagrid(el : Et2Nextmatch) : Et2Datagrid
{
	return el.shadowRoot!.querySelector("et2-datagrid") as Et2Datagrid;
}

function renderedColumnKeys(el : Et2Nextmatch) : string[]
{
	return Array.from(datagrid(el).shadowRoot!.querySelectorAll(".dg-header .dg-col"))
		.map((cell) => (cell as HTMLElement).dataset.columnKey || "");
}

describe("Et2Nextmatch conditional columns", () =>
{
	it("renders a conditional column while its flag is unset", async() =>
	{
		const el = await createNextmatch({});

		assert.deepEqual(
			datagrid(el)._visibleColumns().map((column) => column.key),
			["info_subject", "customfields", "info_datemodified"],
			"a missing flag must not hide the column"
		);
		el.remove();
	});

	it("hides a conditional column when the flag arrives with the rows response", async() =>
	{
		const el = await createNextmatch({});
		assert.include(renderedColumnKeys(el), "customfields", "column should start out rendered");

		(el as any)._dataProvider.processAdditionalData({no_customfields: true});
		await datagrid(el).updateComplete;

		assert.deepEqual(
			renderedColumnKeys(el),
			["info_subject", "info_datemodified"],
			"the rendered header must drop the column once the content flag says so"
		);
		el.remove();
	});

	it("shows the column again when the flag turns back off", async() =>
	{
		const el = await createNextmatch({no_customfields: true});
		assert.notInclude(renderedColumnKeys(el), "customfields", "column should start out hidden");

		(el as any)._dataProvider.processAdditionalData({no_customfields: false});
		await datagrid(el).updateComplete;

		assert.include(renderedColumnKeys(el), "customfields", "clearing the flag must bring the column back");
		el.remove();
	});

	it("leaves a conditional column out of the submitted column selection", async() =>
	{
		const el = await createNextmatch({no_customfields: true});

		assert.deepEqual(
			el.getValue().selectcols,
			["info_subject", "info_datemodified"],
			"a column hidden by its expression must not be saved as a selected column"
		);
		el.remove();
	});
});
