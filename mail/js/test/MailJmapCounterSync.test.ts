import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * MailJmap.requestCounterSync(): the browser keeps the folder-tree's unread counters of a real JMAP
 * account current by itself, from the JMAP server's Mailbox state, instead of depending on the
 * server-relayed push alone (which stopped silently for 21h on 2026-11-05, leaving every badge wrong).
 *
 * Setup: a MailJmap with a primed real-JMAP token and a fake client, whose requestMany() records
 * the JMAP calls built for it and answers from a scripted list of responses. The fake app supplies
 * the "loaded folder-tree nodes" (folderNodesByJmapId()) and records what setFolderStatus() is
 * given. No network, no timers, no real tree.
 *
 * Pass criteria are per test: which JMAP calls were made (full Mailbox/get vs. Mailbox/changes
 * with the right sinceState), and exactly which badges were set.
 */

type Call = { method: string, args: any };

/**
 * A client answering each requestMany() with the next entry of $responses - an Error gets thrown,
 * like the real client does for a method-level error (eg. cannotCalculateChanges).
 */
function fakeClient(responses: any[])
{
	const requests: Call[][] = [];
	const client = {
		// the first sync registers for push (enablePushOnce()), nothing here ever pushes
		onPush: () => () => {},
		requestMany: async(buildFn: (t: any) => any) =>
		{
			const calls: Call[] = [];
			const call = (method: string) => (args: any) =>
			{
				calls.push({method, args});
				return {$ref: (path: string) => '#' + method + path};
			};
			buildFn({Mailbox: {get: call('Mailbox/get'), changes: call('Mailbox/changes')}, Email: {set: call('Email/set')}});
			requests.push(calls);
			const response = responses.shift();
			if (response instanceof Error) throw response;
			if (typeof response === 'function') return [await response()];
			return [response];
		}
	};
	return {client, requests};
}

function createJmap(nodes: Record<string, string>, responses: any[], token: Record<string, any> = {})
{
	const status: Record<string, number>[] = [];
	const pushed: any[] = [];
	const app = {
		egw: {user: () => 1, lang: (label: string) => label, preference: () => null},
		folderNodesByJmapId: () => nodes,
		setFolderStatus: (_status: Record<string, number>) => status.push({..._status}),
		push: (pushData: any) => pushed.push(pushData),
	} as unknown as MailApp;
	const jmap = new MailJmap(app);
	const {client, requests} = fakeClient(responses);
	(jmap as any).tokens["2"] = {accountId: "own", isLocal: false, enableWsPush: false, ...token};
	(jmap as any).clients["2"] = client;
	return {jmap, requests, status, pushed};
}

const full = (state: string, counts: Record<string, number>) => ({
	mailboxes: {state, list: Object.entries(counts).map(([id, unreadEmails]) => ({id, unreadEmails}))}
});
const delta = (newState: string, updated: Record<string, number>, created: Record<string, number> = {}, hasMoreChanges = false) => ({
	changes: {newState, hasMoreChanges},
	created: {list: Object.entries(created).map(([id, unreadEmails]) => ({id, unreadEmails}))},
	updated: {list: Object.entries(updated).map(([id, unreadEmails]) => ({id, unreadEmails}))},
});

const NODES = {inbox: "2::INBOX", sub: "2::INBOX/Sub"};

describe("MailJmap.requestCounterSync()", () =>
{
	it("does nothing for an IMAP account or an unknown profile", async() =>
	{
		const {jmap, requests, status} = createJmap(NODES, [], {isLocal: true});

		await jmap.requestCounterSync("2");
		await jmap.requestCounterSync("99");

		assert.equal(requests.length, 0, "IMAP counters must stay on their classic path");
		assert.equal(status.length, 0);
		assert.isFalse(jmap.countersManaged("2"));
		assert.isFalse(jmap.countersManaged("99"));
	});

	it("fetches all counts the first time, and only sets badges of loaded tree nodes", async() =>
	{
		const {jmap, requests, status} = createJmap(NODES, [full("s1", {inbox: 3, sub: 0, notInTree: 7})]);

		await jmap.requestCounterSync("2");

		assert.deepEqual(requests, [[{method: "Mailbox/get", args: {accountId: "own", properties: ["id", "unreadEmails"]}}]]);
		assert.deepEqual(status, [{"2::INBOX": 3, "2::INBOX/Sub": 0}], "0 has to be set too, to clear a stale badge");
		assert.isTrue(jmap.countersManaged("2"));
		assert.isTrue((jmap as any).pushEnabled["2"], "from now on changes are pushed, whichever account is viewed");
	});

	it("asks only for what changed since the state the badges show", async() =>
	{
		const {jmap, requests, status} = createJmap(NODES, [
			full("s1", {inbox: 3, sub: 0}),
			delta("s2", {sub: 1}),
			delta("s3", {}),
		]);

		await jmap.requestCounterSync("2");
		await jmap.requestCounterSync("2");
		await jmap.requestCounterSync("2");

		assert.deepEqual(requests[1].map(call => call.method), ["Mailbox/changes", "Mailbox/get", "Mailbox/get"]);
		assert.equal(requests[1][0].args.sinceState, "s1");
		assert.deepEqual(requests[1][1].args.ids, "#Mailbox/changes/created");
		assert.deepEqual(requests[1][2].args.ids, "#Mailbox/changes/updated");
		assert.equal(requests[2][0].args.sinceState, "s2", "each sync continues from the state the last one ended on");
		assert.deepEqual(status, [{"2::INBOX": 3, "2::INBOX/Sub": 0}, {"2::INBOX/Sub": 1}],
			"nothing changed must not touch the tree at all");
	});

	it("follows hasMoreChanges until the delta is complete", async() =>
	{
		const {jmap, requests, status} = createJmap(NODES, [
			full("s1", {inbox: 3, sub: 0}),
			delta("s2", {sub: 1}, {}, true),
			delta("s3", {inbox: 4}),
		]);

		await jmap.requestCounterSync("2");
		await jmap.requestCounterSync("2");

		assert.equal(requests[2][0].args.sinceState, "s2");
		assert.deepEqual(status[1], {"2::INBOX/Sub": 1, "2::INBOX": 4});
		assert.equal((jmap as any).counterSync["2"].states.own, "s3");
	});

	it("falls back to all counts, if the server can not calculate the changes", async() =>
	{
		const {jmap, requests, status} = createJmap(NODES, [
			full("s1", {inbox: 3, sub: 0}),
			new Error("cannotCalculateChanges"),
			full("s9", {inbox: 5, sub: 2}),
		]);

		await jmap.requestCounterSync("2");
		await jmap.requestCounterSync("2");

		assert.equal(requests[2][0].method, "Mailbox/get");
		assert.isUndefined(requests[2][0].args.ids);
		assert.deepEqual(status[1], {"2::INBOX": 5, "2::INBOX/Sub": 2});
		assert.isTrue(jmap.countersManaged("2"), "a working fallback is no failure");
	});

	it("fetches all counts again once a new tree node showed up", async() =>
	{
		const nodes: Record<string, string> = {inbox: "2::INBOX"};
		const {jmap, requests, status} = createJmap(nodes, [
			full("s1", {inbox: 3, sub: 4}),
			full("s1", {inbox: 3, sub: 4}),
		]);

		await jmap.requestCounterSync("2");
		// the "Sub" level got loaded meanwhile: a delta from s1 would say nothing about it
		nodes.sub = "2::INBOX/Sub";
		await jmap.requestCounterSync("2");

		assert.equal(requests[1][0].method, "Mailbox/get");
		assert.deepEqual(status[1], {"2::INBOX": 3, "2::INBOX/Sub": 4});
	});

	it("runs once more for all requests made while a sync is in flight, never in parallel", async() =>
	{
		let release: (value: any) => void;
		const blocked = () => new Promise(resolve => release = resolve);
		const {jmap, requests, status} = createJmap(NODES, [blocked, delta("s2", {inbox: 1})]);

		const first = jmap.requestCounterSync("2");
		const second = jmap.requestCounterSync("2");
		const third = jmap.requestCounterSync("2");
		assert.equal(requests.length, 1, "only one request may be in flight");

		release(full("s1", {inbox: 2, sub: 0}));
		await Promise.all([first, second, third]);

		assert.equal(requests.length, 2, "three requests while running are one further run");
		assert.equal(requests[1][0].args.sinceState, "s1");
		assert.deepEqual(status, [{"2::INBOX": 2, "2::INBOX/Sub": 0}, {"2::INBOX": 1}]);
	});

	it("hands the counters back to the push while it fails, and takes over again", async() =>
	{
		const warn = sinon.stub(console, "warn");
		try
		{
			const {jmap, status} = createJmap(NODES, [
				new Error("connection lost"),
				full("s1", {inbox: 3, sub: 0}),
			]);

			await jmap.requestCounterSync("2");
			assert.isFalse(jmap.countersManaged("2"), "a failed sync must not block the pushed counters");
			assert.equal(status.length, 0);

			await jmap.requestCounterSync("2");
			assert.isTrue(jmap.countersManaged("2"));
			assert.deepEqual(status, [{"2::INBOX": 3, "2::INBOX/Sub": 0}]);
		}
		finally
		{
			warn.restore();
		}
	});

	it("syncs the folders of a shared account against their own JMAP account", async() =>
	{
		const ROOT = "\u0001shared-accounts\u0001";
		const warn = sinon.stub(console, "warn");
		try
		{
			const {jmap, requests, status} = createJmap({
				inbox: "2::INBOX",
				[ROOT]: "2::user",                          // placeholder, no mailbox
				[ROOT + "other\u0002"]: "2::user/other",    // placeholder, no mailbox
				[ROOT + "other\u0002mb1"]: "2::user/other/INBOX",
				[ROOT + "gone\u0002mb2"]: "2::user/gone/INBOX",
			}, []);
			// answered by account instead of by order: the accounts are asked in parallel
			(jmap as any).clients["2"].requestMany = async(buildFn: (t: any) => any) =>
			{
				let accountId = "";
				buildFn({Mailbox: {get: (args: any) => { accountId = args.accountId; }}});
				requests.push([{method: "Mailbox/get", args: {accountId}}]);
				if (accountId === "gone") throw new Error("forbidden");
				return [full("s-" + accountId, accountId === "own" ? {inbox: 1} : {mb1: 6})];
			};

			await jmap.requestCounterSync("2");

			assert.sameMembers(requests.map(calls => calls[0].args.accountId), ["own", "other", "gone"]);
			assert.deepEqual(status, [{"2::INBOX": 1, "2::user/other/INBOX": 6}]);
			assert.isTrue(jmap.countersManaged("2"), "a failing shared account must not stop our own counters");
		}
		finally
		{
			warn.restore();
		}
	});
});

describe("MailJmap.processWsPushStates() - unread counters", () =>
{
	it("syncs the counters for a StateChange, without building row updates the server relays already", async() =>
	{
		const {jmap, requests, status, pushed} = createJmap(NODES, [full("s1", {inbox: 3, sub: 0})]);

		await (jmap as any).processWsPushStates("2", "own", {Email: "e2", Mailbox: "s1"});
		await (jmap as any).counterSync["2"].running;

		assert.equal(requests.length, 1);
		assert.deepEqual(status, [{"2::INBOX": 3, "2::INBOX/Sub": 0}]);
		assert.equal(pushed.length, 0, "with a push-server, row updates would show up twice");
		assert.isUndefined((jmap as any).wsPushStates["2"]);
	});

	it("skips a StateChange reporting the Mailbox state the badges already show", async() =>
	{
		const {jmap, requests} = createJmap(NODES, [full("s1", {inbox: 3, sub: 0})]);
		await jmap.requestCounterSync("2");

		await (jmap as any).processWsPushStates("2", "own", {Email: "e2", Mailbox: "s1"});

		assert.equal(requests.length, 1, "our own change, synced already, needs no second request");
	});

	it("skips a StateChange without a Mailbox state, eg. for flagging a message", async() =>
	{
		const {jmap, requests} = createJmap(NODES, [full("s1", {inbox: 3, sub: 0})]);
		await jmap.requestCounterSync("2");

		await (jmap as any).processWsPushStates("2", "own", {Email: "e2"});
		await (jmap as any).processWsPushStates("2", "own", {Email: "e3"});

		assert.equal(requests.length, 1, "no mailbox changed, asking what changed would return nothing");
	});
});

describe("MailJmap counter sync triggers", () =>
{
	it("does not ask again just to be sure, if a sync is running or was done meanwhile", async() =>
	{
		let release: (value: any) => void;
		const blocked = () => new Promise(resolve => release = resolve);
		const {jmap, requests} = createJmap(NODES, [blocked, delta("s2", {})]);
		const queued = Date.now();

		const running = jmap.requestCounterSync("2");
		const joined = jmap.requestCounterSync("2", Date.now());
		release(full("s1", {inbox: 3, sub: 0}));
		await Promise.all([running, joined]);
		assert.equal(requests.length, 1, "joins the running sync, without a further run");

		await jmap.requestCounterSync("2", queued);
		assert.equal(requests.length, 1, "a sync finished since it was queued");

		await jmap.requestCounterSync("2", Date.now() + 1000);
		assert.equal(requests.length, 2, "nothing since then: ask");
	});


	it("checks only accounts which are due, and none while the tab is hidden", async() =>
	{
		const {jmap} = createJmap(NODES, []);
		(jmap as any).tokens["3"] = {accountId: "own3", isLocal: false};
		const requested = sinon.stub(jmap, "requestCounterSync").resolves();
		(jmap as any).counterSync["2"] = {finished: Date.now() - 1000};
		(jmap as any).counterSync["3"] = {finished: Date.now() - 50000};

		(jmap as any).syncCountersIfDue();
		assert.deepEqual(requested.args.map(args => args[0]), ["3"], "45s is due by the next tick, 1s is not");

		requested.resetHistory();
		const hidden = sinon.stub(document, "hidden").get(() => true);
		try
		{
			(jmap as any).syncCountersIfDue(0);
			assert.isFalse(requested.called);
		}
		finally
		{
			hidden.restore();
		}
	});

	it("syncs in a request of its own after changing messages, where the counters can not come along", async() =>
	{
		const {jmap} = createJmap(NODES, []);
		const requested = sinon.stub(jmap, "requestCounterSync").resolves();
		sinon.stub(jmap as any, "ensureToken").resolves((jmap as any).tokens["2"]);
		(jmap as any).clients["2"].requestMany = async() => [{result: {updated: {e1: null}}}];

		await (jmap as any).updateKeywords("2", "inbox", {e1: {"keywords/$seen": true}});
		assert.deepEqual(requested.args, [["2"]]);

		(jmap as any).clients["2"].requestMany = async() => { throw new Error("connection lost"); };
		const error = sinon.stub(console, "error");
		try
		{
			await (jmap as any).destroyIds("2", "inbox", ["e1"]).catch(() => {});
		}
		finally
		{
			error.restore();
		}
		assert.equal(requested.callCount, 2);
	});
});

/**
 * MailJmap.counterBatch(): a request changing messages brings the changed unread counters along,
 * so our own flag changes, moves and deletes cost the JMAP server no further request (measured
 * live before this: two further requests per change).
 *
 * Setup as above, plus `transport: "websocket"` on the fake client (the counter calls are
 * optional calls, which only the WebSocket transport knows) and a first full sync as baseline.
 * requestCounterSync() is spied on afterwards, to count the further requests.
 */
describe("MailJmap.counterBatch() - counters come along with a change", () =>
{
	async function synced(responses: any[])
	{
		const nodes: Record<string, string> = {...NODES};
		const created = createJmap(nodes, [full("s1", {inbox: 3, sub: 0}), ...responses]);
		(created.jmap as any).clients["2"].transport = "websocket";
		sinon.stub(created.jmap as any, "ensureToken").resolves((created.jmap as any).tokens["2"]);
		await created.jmap.requestCounterSync("2");
		return {...created, nodes, requested: sinon.spy(created.jmap, "requestCounterSync")};
	}
	const changed = (newState: string, updated: Record<string, number>, hasMoreChanges = false) => ({
		result: {updated: {e1: null}},
		counterChanges: {newState, hasMoreChanges},
		countersCreated: {list: []},
		countersUpdated: {list: Object.entries(updated).map(([id, unreadEmails]) => ({id, unreadEmails}))},
	});

	it("sets the badges from the answer of the change, without any further request", async() =>
	{
		const {jmap, requests, status, requested} = await synced([changed("s2", {inbox: 2})]);

		await (jmap as any).updateKeywords("2", "inbox", {e1: {"keywords/$seen": true}});

		assert.deepEqual(requests[1].map(call => call.method), ["Email/set", "Mailbox/changes", "Mailbox/get", "Mailbox/get"]);
		assert.equal(requests[1][1].args.sinceState, "s1");
		assert.deepEqual(status[1], {"2::INBOX": 2});
		assert.isFalse(requested.called, "the counters came along, nothing more to ask");
		assert.equal(requests.length, 2);

		// the push about our own change reports the state we already show: no request either
		await (jmap as any).processWsPushStates("2", "own", {Email: "e9", Mailbox: "s2"});
		assert.isFalse(requested.called);
	});

	it("deletes the same way", async() =>
	{
		const {jmap, requests, status, requested} = await synced([{...changed("s2", {inbox: 2}), result: {destroyed: ["e1"]}}]);

		await (jmap as any).destroyIds("2", "inbox", ["e1"]);

		assert.equal(requests[1][0].method, "Email/set");
		assert.deepEqual(status[1], {"2::INBOX": 2});
		assert.isFalse(requested.called);
	});

	it("still succeeds if the counters failed to come along, and asks for them separately", async() =>
	{
		const {jmap, status, requested} = await synced([
			{result: {updated: {e1: null}}},    // the client left the failed optional calls out
			new Error("cannotCalculateChanges"),
			full("s5", {inbox: 2, sub: 0}),
		]);

		const result = await (jmap as any).updateKeywords("2", "inbox", {e1: {"keywords/$seen": true}});
		await (jmap as any).counterSync["2"].running;

		assert.deepEqual(result, {updated: {e1: null}}, "the change worked, and must be reported so");
		assert.deepEqual(requested.args, [["2"]]);
		assert.deepEqual(status[1], {"2::INBOX": 2, "2::INBOX/Sub": 0});
	});

	it("asks separately too, if the change itself failed or more changes are pending", async() =>
	{
		const error = sinon.stub(console, "error");
		try
		{
			const {jmap, requested} = await synced([new Error("connection lost"), delta("s2", {}), changed("s3", {inbox: 2}, true), delta("s4", {})]);

			await (jmap as any).updateKeywords("2", "inbox", {e1: {"keywords/$seen": true}}).catch(() => {});
			await (jmap as any).counterSync["2"].running;
			assert.equal(requested.callCount, 1, "nothing came of the change: show the real counters");

			await (jmap as any).updateKeywords("2", "inbox", {e1: {"keywords/$seen": true}});
			assert.equal(requested.callCount, 2, "an incomplete delta is finished by a normal sync");
		}
		finally
		{
			error.restore();
		}
	});

	it("does not ride along over HTTP, for an IMAP account, or once a new tree node needs all counts", async() =>
	{
		const {jmap, nodes, requests, requested} = await synced([{result: {updated: {e1: null}}}, delta("s2", {})]);
		(jmap as any).clients["2"].transport = "http";

		await (jmap as any).updateKeywords("2", "inbox", {e1: {"keywords/$seen": true}});
		await (jmap as any).counterSync["2"].running;

		assert.deepEqual(requests[1].map(call => call.method), ["Email/set"], "HTTP rejects a batch for any failed call");
		assert.equal(requested.callCount, 1);

		(jmap as any).clients["2"].transport = "websocket";
		assert.isNotNull((jmap as any).counterBatch("2", "own"));
		nodes.fresh = "2::Fresh";
		assert.isNull((jmap as any).counterBatch("2", "own"), "a delta says nothing about the new node");
		delete nodes.fresh;
		(jmap as any).tokens["2"].isLocal = true;
		assert.isNull((jmap as any).counterBatch("2", "own"));
	});

	it("drops a sync result which was overtaken by the counters of a change", async() =>
	{
		let release: (value: any) => void;
		const blocked = () => new Promise(resolve => release = resolve);
		const {jmap, status} = await synced([blocked, changed("s3", {inbox: 1}), delta("s3", {})]);

		// a sync is in flight from s1, when our change answers with the counters of s3
		const sync = jmap.requestCounterSync("2");
		await (jmap as any).updateKeywords("2", "inbox", {e1: {"keywords/$seen": true}});
		release(delta("s2", {inbox: 2}));
		await sync;

		assert.deepEqual(status.slice(1), [{"2::INBOX": 1}], "the older count of s2 must not overwrite the one of s3");
		assert.equal((jmap as any).counterSync["2"].states.own, "s3");
	});
});
