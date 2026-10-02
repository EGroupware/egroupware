/**
 * Column titles read from a raw row template expand their label expressions.
 *
 * Et2RowProvider takes each column's `title` from the template's header cell before any header
 * widget exists, so it reads the raw `label` attributes.  A label such as `@@labels[record_title]`
 * (Records list) has to be expanded against the content, as the header widget expands its own
 * label - otherwise the expression itself shows up wherever `column.title` is used: the column
 * selection dialog for a header cell wrapping several widgets, and every header cell's tooltip.
 */
import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {Et2RowProvider} from "../Et2RowProvider";
import {Et2DatagridColumnState} from "../Et2DatagridColumnState";
import {Et2Datagrid} from "../Et2Datagrid";
import {et2_arrayMgr} from "../../et2_core_arrayMgr";
import "../../Et2Nextmatch/ColumnSelection";
import "../../Et2Nextmatch/Headers/SortableHeader";
import "../../Et2Nextmatch/Headers/AccountFilterHeader";
import "../../Layout/Et2Box/Et2Box";
import * as egwGlobal from "../../../jsapi/egw_global";

const egw = {
	debug: (..._args : any[]) => {},
	lang: (label : string) => label,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	preference: () => null,
	set_preference: () => {},
	app_name: () => "records",
	link: (url : string) => url
};
window.egw = function() { return egw; } as any;
Object.assign(window.egw, egw);

const HEADER = `<row class="th">
	<et2-nextmatch-sortheader label="@@labels[record_title]" id="record_title"></et2-nextmatch-sortheader>
	<et2-vbox>
		<et2-nextmatch-sortheader label="@@labels[record_modified]" id="record_modified"></et2-nextmatch-sortheader>
		<et2-nextmatch-header-account emptyLabel="Modified by" id="record_modifier"></et2-nextmatch-header-account>
	</et2-vbox>
	<et2-nextmatch-sortheader label="Plain" id="plain"></et2-nextmatch-sortheader>
</row>`;

/** The header row as a template is read: raw XML nodes, not widgets */
function headerNode() : Element
{
	return new DOMParser().parseFromString(HEADER, "text/xml").documentElement;
}

function columnsFor(content : any | null, header : Element = headerNode())
{
	const host = document.createElement("div") as any;
	host.getArrayMgr = (name : string) => name === "content" && content ? new et2_arrayMgr(content) : null;
	return (new Et2RowProvider(host) as any)._extractColumnsFromHeaderNode(header);
}

/**
 * Label values that look like expressions themselves.  Expanded once they are data, so they have to
 * arrive as they are - neither looked up again, nor compiled as a PHP expression.
 */
const TRICKY = {
	record_title: "Cost $price @record_modified {Total} $row_cont[x]",
	record_modified: "@@labels[record_title]"
};

describe("Et2RowProvider header titles", () =>
{
	/**
	 * Contract under test:
	 * - `@@` label expressions in raw header cells are expanded with the host's content, both for a
	 *   single header widget and for each widget inside a wrapping et2-vbox; literal labels are kept.
	 * - The column selection caption of the wrapping cell, which comes from `column.title`, is the
	 *   expanded one.
	 *
	 * Setup strategy:
	 * - Records-style header row parsed from XML, host content `{labels: {...}}` as records_bo sends it.
	 *
	 * Pass criteria:
	 * - Titles are "Title", "Modified / Modified by" and "Plain"; no title or caption contains "@".
	 */
	it("expands label expressions against the content", () =>
	{
		const columns = columnsFor({labels: {record_title: "Title", record_modified: "Modified"}});

		assert.deepEqual(columns.map((column) => column.title), ["Title", "Modified / Modified by", "Plain"]);

		const captions = new Et2DatagridColumnState().toSelectionItems(columns).map((item) => item.caption);
		assert.equal(captions[1], "Modified / Modified by", "wrapping header cell is listed with its expanded labels");
	});

	/**
	 * Contract under test:
	 * - Without a content array manager (eg. a slot-built grid) titles are read as before.
	 *
	 * Pass criteria:
	 * - The raw attribute values are returned unchanged.
	 */
	it("keeps labels as they are without content", () =>
	{
		const columns = columnsFor(null);

		assert.deepEqual(columns.map((column) => column.title),
			["@@labels[record_title]", "@@labels[record_modified] / Modified by", "Plain"]);
	});

	describe("expands only once", () =>
	{
		let compileErrors : string[];

		beforeEach(() =>
		{
			// Compiled expressions are cached for the page, a failed one too
			(et2_arrayMgr as any).compiledExpressions = {};
			compileErrors = [];
			const collect = (level : string, ...args : any[]) =>
			{
				if(level === "error" || String(args[0]).includes("ompil"))
				{
					compileErrors.push([level, ...args].map(String).join(" "));
				}
			};
			// et2_arrayMgr logs through egw_global's binding, which need not be this file's egw
			sinon.stub(egw, "debug").callsFake(collect);
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

		/**
		 * Contract under test:
		 * - A label value that itself contains `$`, `@`, `@@` or `{...}` is used as it is: the title,
		 *   the column selection caption and its rendered text all show the value from the content.
		 * - The raw template node is not modified, so the header widget later built from it expands
		 *   the original expression, not the value.
		 *
		 * Setup strategy:
		 * - Content labels whose values look like expressions (TRICKY), and a content entry that a
		 *   second expansion of them would find, so expanding twice would change the text.
		 * - The selection items go into et2-nextmatch-columnselection the way the dialog passes them,
		 *   through transformAttributes() with a content array manager.
		 *
		 * Pass criteria:
		 * - Titles, captions and rendered menu item text equal the TRICKY values exactly; the raw
		 *   nodes keep their `@@labels[...]` attributes; nothing was logged as a compile error.
		 */
		it("does not expand an expanded label again", async() =>
		{
			const content = {labels: TRICKY, price: "WRONG", record_modified: "WRONG", Total: "WRONG"};
			const header = headerNode();
			const columns = columnsFor(content, header);

			assert.deepEqual(columns.map((column) => column.title),
				[TRICKY.record_title, TRICKY.record_modified + " / Modified by", "Plain"]);
			assert.equal(header.querySelector("#record_title").getAttribute("label"), "@@labels[record_title]",
				"raw template node must keep the expression for the header widget");

			// By the time the dialog opens, Et2Datagrid._prepareHeaderNode() has replaced each raw
			// header node with the widget built from it, and the caption prefers that widget's label
			const grid = new Et2Datagrid();
			grid.setArrayMgr("content", new et2_arrayMgr(content));
			columns.forEach((column) => column.header = grid.createElementFromNode(column.header));

			const items = new Et2DatagridColumnState().toSelectionItems(columns);
			const selector = document.createElement("et2-nextmatch-columnselection") as any;
			selector.setArrayMgr("content", new et2_arrayMgr(content));
			selector.transformAttributes({columns: items});
			document.body.append(selector);
			await selector.updateComplete;
			try
			{
				const rendered = Array.from(selector.shadowRoot.querySelectorAll("sl-menu-item"))
					.map((item : any) => item.textContent.trim());
				// The single widget cell is listed with its widget's label, which has had its {...}
				// sub-strings translated (see below); the wrapping cell with the column title
				assert.includeMembers(rendered, [TRICKY.record_title.replace("{Total}", "Total"), TRICKY.record_modified + " / Modified by"]);
				assert.notInclude(rendered.join("|"), "WRONG");
			}
			finally
			{
				selector.remove();
			}
			assert.deepEqual(compileErrors, []);
		});

		/**
		 * Contract under test:
		 * - The header widget built from the raw header node (Et2Datagrid._prepareHeaderNode() does
		 *   this with createElementFromNode()) shows the same text as the column title: one
		 *   expansion each, of the same raw expression.
		 *
		 * Pass criteria:
		 * - The widget's label is the TRICKY value with its `$` and `@` parts untouched.  The widget
		 *   does translate `{...}` sub-strings of its label, which column titles do not (a separate,
		 *   pre-existing gap: wrapping header cells are listed untranslated), so `{Total}` -> `Total`
		 *   is the only difference allowed.
		 */
		it("gives the header widget and the title the same text", () =>
		{
			const content = {labels: TRICKY, price: "WRONG", record_modified: "WRONG", Total: "WRONG"};
			const header = headerNode();
			const [titleColumn] = columnsFor(content, header);

			const grid = new Et2Datagrid();
			grid.setArrayMgr("content", new et2_arrayMgr(content));
			const widget = grid.createElementFromNode(header.querySelector("#record_title")) as any;

			assert.equal(titleColumn.title, TRICKY.record_title);
			assert.equal(widget.label, TRICKY.record_title.replace("{Total}", "Total"));
			assert.deepEqual(compileErrors, []);
		});
	});
});
