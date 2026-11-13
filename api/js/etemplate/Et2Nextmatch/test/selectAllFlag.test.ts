/**
 * Tests for skipping the "fetch every id" step of a "Select all" action the server expands itself.
 *
 * CONTRACT
 * With "Select all" the list only holds its loaded rows, so EgwAction.execute() asks the
 * nextmatch whether the selection is incomplete and, if so, fetches all ids first. An action
 * marked server-side with data.server_select_all has a handler that ignores the ids, so:
 * - EgwAction.execute() runs it under "Select all" without asking the nextmatch for more ids
 * - selectAllFlag() then sends the list's total, not the count of the (partial) ids
 * A long task sends one request per row and is never treated as marked. Without "Select all"
 * the mark changes nothing: a shift range over unfetched rows still has to be completed.
 *
 * SETUP
 * Real actions from a throw-away action manager, their data pointing at a fake nextmatch that
 * offers getSelection(), totalCount, selectionIncomplete() (always true) and an
 * executeWithCompleteSelection() that records being called instead of fetching.
 *
 * PASS CRITERIA
 * Exactly the return values and calls asserted; no request is made anywhere.
 */
import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {selectAllFlag} from "../selectAllFlag";
// egw_action first: importing EgwAction.ts on its own trips over the action classes' import cycle
import {egw_getActionManager} from "../../../egw_action/egw_action";
import {SERVER_SELECT_ALL} from "../../../egw_action/EgwAction";

function nextmatch(all : boolean, total : any = 0)
{
	return {
		getSelection: () => ({ids: [], all: all}),
		totalCount: total,
		selectionIncomplete: sinon.stub().returns(true),
		executeWithCompleteSelection: sinon.spy()
	};
}

function createAction(data : any, handler : Function = () => {})
{
	const manager = egw_getActionManager("nm_server_select_all_" + Math.random(), true);
	const action = manager.addAction("popup", "close", "Close", "", handler, true);
	action.data = data;
	return action;
}

const marked = (nm : any = null) => createAction({[SERVER_SELECT_ALL]: true, nextmatch: nm});
const unmarked = (nm : any = null) => createAction({nextmatch: nm});
const longTask = (nm : any = null) => createAction({[SERVER_SELECT_ALL]: true, nm_action: "long_task", nextmatch: nm});

describe("select-all the server expands itself", () =>
{
	describe("EgwAction.serverSelectsAll()", () =>
	{
		it("is true for an action marked in its data", () =>
		{
			assert.isTrue(marked().serverSelectsAll());
		});

		it("is false for an unmarked action, or one without data", () =>
		{
			assert.isFalse(unmarked().serverSelectsAll());
			assert.isFalse(createAction(null).serverSelectsAll());
		});

		it("is false for a long task, even when marked", () =>
		{
			assert.isFalse(longTask().serverSelectsAll());
		});
	});

	describe("EgwAction.execute()", () =>
	{
		const senders = [{id: "example::1"}, {id: "example::2"}];

		it("fetches nothing for a marked action under select-all", () =>
		{
			const nm = nextmatch(true, 500);
			const handler = sinon.spy();
			const action = createAction({[SERVER_SELECT_ALL]: true, nextmatch: nm}, handler);

			action.execute(senders);

			assert.isFalse(nm.executeWithCompleteSelection.called, "no ids may be fetched");
			assert.isTrue(handler.calledOnce, "handler runs straight away");
			assert.strictEqual(handler.firstCall.args[1], senders, "with the senders it was given");
		});

		it("still fetches for an unmarked action under select-all", () =>
		{
			const nm = nextmatch(true, 500);
			unmarked(nm).execute(senders);
			assert.isTrue(nm.executeWithCompleteSelection.calledOnce);
		});

		it("still fetches for a long task under select-all", () =>
		{
			const nm = nextmatch(true, 500);
			longTask(nm).execute(senders);
			assert.isTrue(nm.executeWithCompleteSelection.calledOnce);
		});

		it("still completes an incomplete selection for a marked action when select-all is off", () =>
		{
			const nm = nextmatch(false, 500);
			marked(nm).execute(senders);
			assert.isTrue(nm.executeWithCompleteSelection.calledOnce);
		});
	});

	describe("selectAllFlag()", () =>
	{
		const partial = ["1", "2"];
		const nextmatch = (all : boolean, _ids : any[], total : any) => ({getSelection: () => ({ids: [], all: all}), totalCount: total});

		it("sends the list's total for a marked action, whose ids are only the loaded rows", () =>
		{
			assert.strictEqual(selectAllFlag(nextmatch(true, [], 500), partial, marked()), 500);
		});

		it("sends the count of the fetched ids for an unmarked action", () =>
		{
			assert.strictEqual(selectAllFlag(nextmatch(true, [], 500), partial, unmarked()), 2);
			assert.strictEqual(selectAllFlag(nextmatch(true, [], 500), partial), 2);
			assert.strictEqual(selectAllFlag(nextmatch(true, [], 500), partial, null), 2);
		});

		it("sends the count of the fetched ids for a long task", () =>
		{
			assert.strictEqual(selectAllFlag(nextmatch(true, [], 500), partial, longTask()), 2);
		});

		it("sends true for a marked action when the list does not know its total", () =>
		{
			assert.strictEqual(selectAllFlag(nextmatch(true, [], undefined), partial, marked()), true);
			assert.strictEqual(selectAllFlag(nextmatch(true, [], 0), partial, marked()), true);
		});

		it("sends false without select-all, marked or not", () =>
		{
			assert.strictEqual(selectAllFlag(nextmatch(false, [], 500), partial, marked()), false);
		});
	});
});
