/**
 * Row data is data, not template markup.
 *
 * A row template's placeholders ("$row_cont[x]", "$[x]", "{x}", ...) are resolved
 * against the row once.  Whatever the row value then contains - "$", "$$", "{...}",
 * quotes, newlines, eg. an email body - must reach the widget as it is: it must not be
 * resolved a second time as a placeholder, nor compiled as a PHP expression.
 */
import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {Et2Datagrid} from "../Et2Datagrid";
import {Et2RowProvider} from "../Et2RowProvider";
import {et2_arrayMgr} from "../../et2_core_arrayMgr";
import "../../Et2Description/Et2Description";
import * as egwGlobal from "../../../jsapi/egw_global";

const egw = {
	debug: () => {},
	lang: (label : string) => label,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	preference: () => null,
	set_preference: () => {},
	app_name: () => "addressbook",
	link: (url : string) => url
};
window.egw = function() { return egw; } as any;
Object.assign(window.egw, egw);

// An email as notifications put it into a list row: a "$$placeholder$$", a "$name",
// a "{name}", quotes and newlines
const EMAIL = "Subject: Data protection\nFrom: \"All users\"\n\nHere's a chance to update.\n" +
	"We'll delete you after $$delete_no_response_duration$$ days, see {other} and $other.\n";

function createDatagrid() : Et2Datagrid
{
	const el = new Et2Datagrid();
	el.dataProvider = {
		fetchPage: async() => ({rows: [], total: 0}),
		getDataStorePrefix: () => "addressbook",
		normalizeRowId: (rowId : string | number) => String(rowId ?? ""),
		toProviderRowId: (rowId : string) => rowId,
		refresh: async() => ({rows: [], removedRowIds: []})
	} as any;
	el.setArrayMgr("content", new et2_arrayMgr({}));
	return el;
}

/**
 * Build one row from a single-cell row template, and apply its deferred attributes
 */
// An attribute the lightweight-description fast path does not handle, so the row keeps a
// real et2-description widget, as it does eg. with a statustext or an onclick
const REAL_WIDGET = `data-probe="1"`;

async function renderRow(cellHtml : string, rowData : any, rowRootAttributes : Record<string, string> = {}, content : any = {})
{
	const el = createDatagrid();
	el.setArrayMgr("content", new et2_arrayMgr(content));
	el.columns = [{key: "body", title: "Body", width: "1fr"}] as any;
	const provider = new Et2RowProvider(el as any);
	const rowTemplate = document.createElement("tr");
	Object.entries(rowRootAttributes).forEach(([name, value]) => rowTemplate.setAttribute(name, value));
	const cell = document.createElement("td");
	cell.innerHTML = cellHtml;
	rowTemplate.appendChild(cell);

	const prepared = await (provider as any)._prepareRowTemplate(rowTemplate, el.columns as any);
	(el as any).templateData = {
		columns: el.columns,
		rowTemplate: prepared?.template ?? null,
		rowTemplateXml: prepared?.xml ?? null,
		rowTemplateAttrMap: prepared?.attrMap ?? {},
		rowTemplateFieldMap: prepared?.fieldMap ?? {}
	};
	const row = {id: "row-0", data: rowData};
	const rowElement = (el as any)._buildRowElement(row, 0) as HTMLElement;
	(el as any)._applyRowElementAttributes(rowElement, row.data, 0);
	// The same row again with changed data, as a refresh does: the row's markup is unchanged,
	// so the grid keeps its DOM and only applies the row attributes again
	(rowElement as any).applyRowData = (data : any) => (el as any)._applyRowElementAttributes(rowElement, data, 0);
	return rowElement;
}

describe("Et2Datagrid row data is not expanded twice", () =>
{
	let compileErrors : string[];

	beforeEach(() =>
	{
		// Compiled expressions are cached for the page, a failed one too: without a reset
		// only the first test to expand a value would log its compile error
		(et2_arrayMgr as any).compiledExpressions = {};
		compileErrors = [];
		const collect = (level : string, ...args : any[]) =>
		{
			if(level === "error" || String(args[0]).includes("ompil"))
			{
				compileErrors.push([level, ...args].map(String).join(" "));
			}
		};
		// et2_arrayMgr logs through egw_global's binding, a snapshot of window.egw taken
		// when that module loaded - which need not be the egw this file installs
		sinon.stub(egw as any, "debug").callsFake(collect);
		(window.egw as any).debug = egw.debug;
		const globalEgw = (egwGlobal as any).egw;
		if(globalEgw && globalEgw !== window.egw)
		{
			sinon.stub(globalEgw, "debug").callsFake(collect);
		}
	});

	afterEach(() =>
	{
		sinon.restore();
		(window.egw as any).debug = egw.debug;
	});

	it("passes a row value bound by value= on as it is", async() =>
	{
		const rowElement = await renderRow(`<et2-description class="probe" ${REAL_WIDGET} value="$row_cont[body]"></et2-description>`,
			{body: EMAIL, other: "OTHER"});
		const description = rowElement.querySelector(".probe") as any;
		assert.equal(description?.value, EMAIL);
		assert.deepEqual(compileErrors, [], "row data must not be compiled as a PHP expression");
	});

	it("passes a row value bound by id= on as it is", async() =>
	{
		const rowElement = await renderRow(`<et2-description class="probe" ${REAL_WIDGET} id="\${row}[body]"></et2-description>`,
			{body: EMAIL, other: "OTHER"});
		const description = rowElement.querySelector(".probe") as any;
		assert.equal(description?.value, EMAIL);
		assert.deepEqual(compileErrors, [], "row data must not be compiled as a PHP expression");
	});

	it("passes a row value bound by id= on as it is, next to an @@ attribute (tracker's plain-text comments)", async() =>
	{
		// tracker/templates/default/edit.xet: the "@@" reference makes the row use the array
		// manager's row perspective
		const rowElement = await renderRow(
			`<et2-description class="probe" id="\${row}[reply_message]" hidden="@@tr_edit_mode=html"></et2-description>`,
			{reply_message: EMAIL, other: "OTHER"}, {}, {tr_edit_mode: "ascii"});
		const description = rowElement.querySelector(".probe") as any;
		assert.equal(description?.localName, "et2-description");
		assert.isFalse(description?.hasAttribute("hidden"), "a plain-text ticket shows its plain-text comments");
		assert.equal(description?.value, EMAIL);
		assert.deepEqual(compileErrors, [], "row data must not be compiled as a PHP expression");
	});

	it("passes a row value bound to another attribute on as it is", async() =>
	{
		// statustext is a translated attribute: a "{...}" in it is translated, as it always
		// was for row values, so this value has none
		const body = "Costs $other\nand $$placeholder$$";
		const rowElement = await renderRow(`<et2-description class="probe" statustext="$row_cont[body]"></et2-description>`,
			{body, other: "OTHER"});
		const description = rowElement.querySelector(".probe") as any;
		assert.equal(description?.statustext, body);
		assert.deepEqual(compileErrors, [], "row data must not be compiled as a PHP expression");
	});

	it("does not resolve placeholders inside a row value embedded in a longer attribute", async() =>
	{
		const rowElement = await renderRow(`<et2-description class="probe" ${REAL_WIDGET} value="Re: $row_cont[subject]"></et2-description>`,
			{subject: "costs $other {other}", other: "OTHER"});
		const description = rowElement.querySelector(".probe") as any;
		assert.equal(description?.value, "Re: costs $other {other}");
		assert.deepEqual(compileErrors, [], "row data must not be compiled as a PHP expression");
	});

	it("does not resolve placeholders inside a nested row value embedded in a longer attribute", async() =>
	{
		const rowElement = await renderRow(`<et2-description class="probe" ${REAL_WIDGET} value="Re: $row_cont[mail][subject]"></et2-description>`,
			{mail: {subject: "costs $other {other}"}, other: "OTHER"});
		const description = rowElement.querySelector(".probe") as any;
		assert.equal(description?.value, "Re: costs $other {other}");
		assert.deepEqual(compileErrors, [], "row data must not be compiled as a PHP expression");
	});

	it("replaces a row-bound class, leaving no placeholder behind (tracker's read/unread summary)", async() =>
	{
		const rowElement = await renderRow(`<et2-description class="probe $row_cont[seen_class] et2_link" ${REAL_WIDGET} value="$row_cont[summary]"></et2-description>`,
			{summary: "Ticket", seen_class: "tracker_unseen"});
		const description = rowElement.querySelector(".probe") as HTMLElement;
		assert.sameMembers(Array.from(description.classList), ["probe", "tracker_unseen", "et2_link"], description.className);
	});

	it("drops the previous value of a row-bound class when the row's data changes", async() =>
	{
		const rowElement = await renderRow(`<et2-description class="probe $row_cont[seen_class] et2_link" ${REAL_WIDGET} value="$row_cont[summary]"></et2-description>`,
			{summary: "Ticket", seen_class: "tracker_unseen"});
		const description = rowElement.querySelector(".probe") as HTMLElement;
		description.classList.add("runtime_state");

		(rowElement as any).applyRowData({summary: "Ticket", seen_class: "tracker_seen"});

		assert.sameMembers(Array.from(description.classList), ["probe", "tracker_seen", "et2_link", "runtime_state"],
			"the new row class replaces the old one; a class added by something else stays: " + description.className);
	});

	it("passes a row value on as it is to a lightweight description", async() =>
	{
		const rowElement = await renderRow(`<et2-description class="probe" value="$row_cont[body]"></et2-description>`,
			{body: EMAIL, other: "OTHER"});
		const description = rowElement.querySelector(".probe") as HTMLElement;
		assert.equal(description?.localName, "span", "a plain description becomes native text");
		assert.equal(description?.textContent, EMAIL);
		assert.deepEqual(compileErrors, [], "row data must not be compiled as a PHP expression");
	});

	it("does not resolve placeholders inside a row value in a text node", async() =>
	{
		const rowElement = await renderRow(`<span class="probe">{subject}</span>`,
			{subject: "costs $other {other}", other: "OTHER"});
		assert.equal(rowElement.querySelector(".probe")?.textContent, "costs $other {other}");
	});

	it("does not resolve placeholders inside a row value in a row root attribute", async() =>
	{
		const rowElement = await renderRow(`<span>x</span>`,
			{subject: "costs $other {other}", other: "OTHER"}, {"data-subject": "$row_cont[subject]"});
		assert.equal(rowElement.getAttribute("data-subject"), "costs $other {other}");
	});

	it("does not resolve placeholders inside a row value in a {field} row root attribute", async() =>
	{
		const rowElement = await renderRow(`<span>x</span>`,
			{subject: "costs $other {other}", other: "OTHER"}, {"data-subject": "{subject}"});
		assert.equal(rowElement.getAttribute("data-subject"), "costs $other {other}");
	});
});
