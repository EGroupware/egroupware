/**
 * Tests for what EgwApp.ajax_action() and EgwApp.submit_action_popup() send as the "all selected"
 * flag of a nextmatch action.
 *
 * CONTRACT
 * With "Select all" ticked the server does not act on the ids it is sent, it expands the selection
 * itself by re-running the query its list last cached. So the flag carries the number of entries
 * the user's selection holds (the ids collected for the action, else the list's total) instead of
 * a plain true, and the server refuses to act if its expansion comes out at a different number
 * (Api\Etemplate\Widget\Nextmatch::checkSelectAllCount()). Without select-all it must stay
 * exactly false, and a list that does not know its total must still send true - a missing count
 * may never turn a "select all" into "only the visibly selected rows".
 *
 * SETUP
 * Both methods are called on the prototype with a minimal `this` (no EgwApp is constructed, its
 * constructor needs a framework and window.app), a fake nextmatch offering getSelection() and
 * totalCount, and an egw.request() that records its arguments.
 *
 * PASS CRITERIA
 * The 4th request parameter is exactly the expected value; nothing is sent to a real server.
 */
import {assert} from "@open-wc/testing";
import {EgwApp} from "../egw_app";
import {EgwAction} from "../../egw_action/EgwAction";

function fakeNextmatch(all : boolean, total : any, ids : string[] = [])
{
	return {
		getSelection: () => ({ids: ids, all: all}),
		totalCount: total,
		getInstanceManager: () => ({etemplate_exec_id: "exec-id"})
	};
}

/**
 * Run ajax_action() for a list in the given state, and return the parameters it sent
 */
function ajaxAction(all : boolean, total : any, ids : string[] = [], data : any = {}) : any[]
{
	let sent = null;
	const nm = fakeNextmatch(all, total, ids);
	const app = {
		appname: "example",
		nm: nm,
		et2: {etemplate_exec_id: "exec-id"},
		egw: {request: (_menuaction, params) => { sent = params; }}
	};
	const action = {
		id: "close", data: {nextmatch: nm, ...data}, getManager: () => ({getActionsByAttr: () => []}),
		serverSelectsAll: EgwAction.prototype.serverSelectsAll
	};
	EgwApp.prototype.ajax_action.call(app, <any>action, <any>ids.map(id => ({id: id})));
	return sent;
}

/**
 * Run submit_action_popup() for a list in the given state, and return the parameters it sent
 */
function popupAction(all : boolean, total : any, ids : string[] = []) : any[]
{
	let sent = null;
	const nm = fakeNextmatch(all, total, ids);
	const app = {
		appname: "example",
		nm: nm,
		et2: {etemplate_exec_id: "exec-id", getWidgetById: (id) => id === "nm" ? nm : null},
		egw: {request: (_menuaction, params) => { sent = params; }, debug: () => {}}
	};
	EgwApp.prototype.submit_action_popup.call(app, {id: "responsible_action[add]"});
	return sent;
}

describe("EgwApp: expected row count of a select-all action", () =>
{
	for(const [name, send] of <[string, (all : boolean, total : any, ids? : string[]) => any[]][]>[
		["ajax_action()", ajaxAction],
		["submit_action_popup()", popupAction]
	])
	{
		describe(name, () =>
		{
			it("sends the number of ids collected for the action in place of true when select-all is ticked", () =>
			{
				// the list's total is deliberately different: the ids were fetched with the
				// current filters just now, the total is whatever the last page response said
				const sent = send(true, 42, ["example::1", "example::2", "example::3"]);
				assert.strictEqual(sent[3], 3);
				assert.deepEqual(sent[2], ["1", "2", "3"]);
			});

			it("falls back to the list's total when no ids were collected", () =>
			{
				assert.strictEqual(send(true, 42)[3], 42);
			});

			it("sends exactly false without select-all, whatever the total is", () =>
			{
				assert.strictEqual(send(false, 42)[3], false);
				assert.strictEqual(send(false, 42, ["example::1", "example::2"])[3], false);
			});

			it("still sends true when the list does not know its total", () =>
			{
				assert.strictEqual(send(true, undefined)[3], true);
				assert.strictEqual(send(true, -1)[3], true);
				assert.strictEqual(send(true, NaN)[3], true);
			});

			it("sends true, not a falsy 0, for an empty list", () =>
			{
				assert.strictEqual(send(true, 0)[3], true);
			});

			it("accepts a total given as string", () =>
			{
				assert.strictEqual(send(true, "17")[3], 17);
			});
		});
	}

	describe("ajax_action() for an action the server expands itself", () =>
	{
		it("sends the list's total, as the ids are only the loaded rows", () =>
		{
			const sent = ajaxAction(true, 42, ["example::1", "example::2"], {server_select_all: true});
			assert.strictEqual(sent[3], 42);
			assert.deepEqual(sent[2], ["1", "2"]);
		});

		it("still sends false without select-all", () =>
		{
			assert.strictEqual(ajaxAction(false, 42, ["example::1"], {server_select_all: true})[3], false);
		});
	});
});
