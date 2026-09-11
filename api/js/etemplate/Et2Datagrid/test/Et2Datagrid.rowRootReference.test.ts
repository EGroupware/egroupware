/**
 * A row template may test a value belonging to the whole entry rather than the row, by referencing
 * the content array with "@name" or - for the template root - "@@name".  Tracker's comment rows use
 * it to pick the widget that renders a comment based on the ticket's tr_edit_mode.
 *
 * Et2Datagrid's own row resolver only understands "$" row references, so these have to be handed to
 * the array manager, which resolves them through parseBoolExpression().  When they were not, both
 * "@@x=y" and "!@@x=y" reached a Boolean attribute as the same non-empty string - so both branches
 * of a hidden=/disabled= pair applied and the wrong widget rendered.
 */
import {assert} from "@open-wc/testing";
import {Et2Datagrid} from "../Et2Datagrid";
import {et2_arrayMgr} from "../../et2_core_arrayMgr";
import "../../Et2Description/Et2Description";

const egw = {debug: () => {}, lang: (l : string) => l, tooltipBind: () => {}, tooltipUnbind: () => {}, app_name: () => "tracker"};
window.egw = function() { return egw; } as any;
Object.assign(window.egw, egw);

describe("Et2Datagrid row template root (@@) references", () =>
{
	const rowResolver = () =>
	{
		const grid = Object.create(Et2Datagrid.prototype) as any;
		grid._getFieldValue = (rowData : any, field : string) => rowData?.[field];
		return grid;
	};

	it("resolves @@ against the template root from inside a row perspective", () =>
	{
		const root = new et2_arrayMgr({tr_edit_mode: "ascii"});
		const row = root.openPerspective({} as any, {0: {reply_id: 900}}, 0);

		assert.equal(row.getRoot().getEntry("tr_edit_mode"), "ascii");
		assert.isFalse(row.parseBoolExpression("@@tr_edit_mode=html"));
		assert.isTrue(row.parseBoolExpression("!@@tr_edit_mode=html"));
	});

	it("defers an @@ expression to the array manager instead of resolving it as a row value", () =>
	{
		const grid = rowResolver();
		const rowData = {reply_id: 900};

		const resolved = grid._resolveRowExpression("@@tr_edit_mode=html", rowData, "900");

		assert.isTrue(resolved.fallback, "must ask for the array-manager perspective");
		assert.equal(resolved.value, "@@tr_edit_mode=html", "expression handed on unchanged");
	});

	it("declines the direct-boolean fast path for @@ expressions", () =>
	{
		const grid = rowResolver();

		assert.isUndefined(grid._directBooleanRowValue("@@tr_edit_mode=html", {reply_id: 900}, "900"));
		assert.isUndefined(grid._directBooleanRowValue("!@@tr_edit_mode=html", {reply_id: 900}, "900"));
	});

	it("gives the two branches of a hidden= pair opposite values on a real widget", async() =>
	{
		const root = new et2_arrayMgr({tr_edit_mode: "ascii"});
		const row = root.openPerspective({} as any, {0: {reply_message: "text"}}, 0);

		// Set in markup, as a row template does - that is what makes transformAttributes()
		// write the resolved value back out as a DOM attribute.
		const forHtml = document.createElement("et2-description") as any;
		forHtml.setAttribute("hidden", "!@@tr_edit_mode=html");
		forHtml.setArrayMgr("content", row);
		forHtml.transformAttributes({hidden: "!@@tr_edit_mode=html"});

		const forText = document.createElement("et2-description") as any;
		forText.setAttribute("hidden", "@@tr_edit_mode=html");
		forText.setArrayMgr("content", row);
		forText.transformAttributes({hidden: "@@tr_edit_mode=html"});

		assert.isTrue(forHtml.hidden, "html-only widget hidden on an ascii entry");
		assert.isFalse(forText.hidden, "text widget shown on an ascii entry");
		// A boolean attribute hides by presence, so a resolved false must remove it entirely
		assert.isFalse(forText.hasAttribute("hidden"), "hidden=\"false\" would still hide");
	});
});
