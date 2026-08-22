import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {MailJmap} from "../jmap";
import {JamWebSocketClient} from "../jmap-jam-websocket";
import type {MailApp} from "../app";

/**
 * Same FakeWebSocket as JamWebSocketClient.test.ts (readyState/addEventListener/send/close plus
 * simulate*() test helpers) - duplicated rather than imported since that file doesn't export it and
 * these are two independent test suites.
 */
class FakeWebSocket
{
	static readonly CONNECTING = 0;
	static readonly OPEN = 1;
	static readonly CLOSED = 3;
	static instances : FakeWebSocket[] = [];

	readyState = FakeWebSocket.CONNECTING;
	sent : string[] = [];
	#listeners : Record<string, Array<(event : any) => void>> = {};

	constructor(public url : string, public protocol : string)
	{
		FakeWebSocket.instances.push(this);
	}

	addEventListener(type : string, listener : (event : any) => void) : void
	{
		(this.#listeners[type] || (this.#listeners[type] = [])).push(listener);
	}

	send(data : string) : void
	{
		this.sent.push(data);
	}

	close() : void
	{
		this.simulateClose();
	}

	simulateOpen() : void
	{
		this.readyState = FakeWebSocket.OPEN;
		this.#dispatch("open", {});
	}

	simulateMessage(data : unknown) : void
	{
		this.#dispatch("message", {data : JSON.stringify(data)});
	}

	simulateClose() : void
	{
		this.readyState = FakeWebSocket.CLOSED;
		this.#dispatch("close", {});
	}

	#dispatch(type : string, event : any) : void
	{
		(this.#listeners[type] || []).slice().forEach((listener) => listener(event));
	}
}

function fakeSession() : any
{
	return {
		capabilities : {"urn:ietf:params:jmap:websocket" : {url : "wss://example.com/jmap/ws/", supportsPush : false}},
		accounts : {}, primaryAccounts : {},
		apiUrl : "https://example.com/jmap/api/",
		downloadUrl : "https://example.com/jmap/download/{accountId}/{blobId}/{name}?type={type}",
		uploadUrl : "https://example.com/jmap/upload/{accountId}/",
		eventSourceUrl : "https://example.com/jmap/eventsource/",
		state : "state1"
	};
}

function stubFetch(session : any) : sinon.SinonStub
{
	return sinon.stub(globalThis, "fetch").callsFake(async() => ({json : async() => session}));
}

async function flush() : Promise<void>
{
	await new Promise((resolve) => setTimeout(resolve, 0));
}

/**
 * Responds to the single most-recently-sent Mailbox/get request with a fixed id -> {id, name,
 * parentId} map - folderId2path() does one request per ancestor level (see its own docblock for why
 * it isn't batched via $ref()), so each call to this only ever needs to answer one request.
 */
function respondToMailboxGet(socket : FakeWebSocket, mailboxes : Record<string, {id : string; name : string; parentId : string | null}>) : void
{
	const sentFrame = JSON.parse(socket.sent[socket.sent.length - 1]);
	const [method, args, callId] = sentFrame.methodCalls[0];
	assert.equal(method, "Mailbox/get");
	const list = (args.ids as string[]).map((id : string) => mailboxes[id]).filter(Boolean)
		.map((m) => ({id : m.id, name : m.name, parentId : m.parentId}));
	socket.simulateMessage({
		"@type" : "Response", requestId : sentFrame.id, sessionState : "s1",
		methodResponses : [["Mailbox/get", {accountId : "acc1", state : "s1", list, notFound : []}, callId]]
	});
}

async function createConnectedJmapClient() : Promise<{client : JamWebSocketClient; socket : FakeWebSocket}>
{
	const client = new JamWebSocketClient({sessionUrl : "https://example.com/session", bearerToken : "tok"});
	await client.session;
	await flush();
	const socket = FakeWebSocket.instances[FakeWebSocket.instances.length - 1];
	socket.simulateOpen();
	return {client, socket};
}

const egw = {
	user: (_key : string) => 1,
	lang: (label : string) => label,
	preference: (_key : string, _app? : string) => null,
	config: (_name : string, _app? : string) => null,
	request: async() => ({}),
	message: (_msg : string, _type? : string) => {},
};

function createFakeApp(pushed : any[]) : MailApp
{
	return {
		egw,
		getCustomLabels: () => ({}),
		updateCustomLabelStylesheet: () => {},
		push: (pushData : any) => pushed.push(pushData)
	} as unknown as MailApp;
}

describe("MailJmap.folderId2path()", () =>
{
	const OriginalWebSocket = (globalThis as any).WebSocket;
	let fetchStub : sinon.SinonStub;

	beforeEach(() =>
	{
		(globalThis as any).WebSocket = FakeWebSocket;
		fetchStub = stubFetch(fakeSession());
	});

	afterEach(() =>
	{
		fetchStub?.restore();
		(globalThis as any).WebSocket = OriginalWebSocket;
		FakeWebSocket.instances = [];
	});

	it("resolves a top-level mailbox (no parent) in one round trip", async() =>
	{
		const {client, socket} = await createConnectedJmapClient();
		const jmap = new MailJmap(createFakeApp([]));

		const promise = (jmap as any).folderId2path(client, "1", "acc1", "inbox-id");
		await flush();
		respondToMailboxGet(socket, {"inbox-id" : {id : "inbox-id", name : "Inbox", parentId : null}});

		assert.equal(await promise, "INBOX", "an 'Inbox'-named mailbox must be normalized to upper-case INBOX");
		assert.equal(socket.sent.length, 1, "a root-level mailbox must resolve in a single round trip");
	});

	it("walks the parentId chain one round trip per level", async() =>
	{
		const {client, socket} = await createConnectedJmapClient();
		const jmap = new MailJmap(createFakeApp([]));
		const mailboxes = {
			"sub2-id" : {id : "sub2-id", name : "Sub2", parentId : "sub1-id"},
			"sub1-id" : {id : "sub1-id", name : "Sub1", parentId : "inbox-id"},
			"inbox-id" : {id : "inbox-id", name : "INBOX", parentId : null}
		};

		const promise = (jmap as any).folderId2path(client, "1", "acc1", "sub2-id");
		for (let i = 0; i < 3; i++)
		{
			await flush();
			respondToMailboxGet(socket, mailboxes);
		}

		assert.equal(await promise, "INBOX/Sub1/Sub2");
		assert.equal(socket.sent.length, 3, "3 levels deep must take exactly 3 round trips");
	});

	it("returns null for a folder that no longer exists (e.g. a 'destroyed' push item)", async() =>
	{
		const {client, socket} = await createConnectedJmapClient();
		const jmap = new MailJmap(createFakeApp([]));

		const promise = (jmap as any).folderId2path(client, "1", "acc1", "gone-id");
		await flush();
		respondToMailboxGet(socket, {});

		assert.isNull(await promise);
	});

	it("caches the resolved path - a second lookup for the same folderId makes no further requests", async() =>
	{
		const {client, socket} = await createConnectedJmapClient();
		const jmap = new MailJmap(createFakeApp([]));

		const first = (jmap as any).folderId2path(client, "1", "acc1", "inbox-id");
		await flush();
		respondToMailboxGet(socket, {"inbox-id" : {id : "inbox-id", name : "Inbox", parentId : null}});
		assert.equal(await first, "INBOX");

		const second = await (jmap as any).folderId2path(client, "1", "acc1", "inbox-id");
		assert.equal(second, "INBOX");
		assert.equal(socket.sent.length, 1, "the cached lookup must not send another request");
	});
});

describe("MailJmap.processWsPushStates()", () =>
{
	function primeWsPushToken(jmap : MailJmap, profileID : string, client : any) : void
	{
		(jmap as any).tokens[profileID] = {
			sessionUrl: "https://example.com", accountId: "acc1", access_token: "tok",
			expires_at: Date.now() + 100000, isLocal: false, customLabels: {}, enableWsPush: true
		};
		(jmap as any).clients[profileID] = client;
	}

	it("seeds the baseline on the first StateChange without pushing anything", async() =>
	{
		const pushed : any[] = [];
		const jmap = new MailJmap(createFakeApp(pushed));
		primeWsPushToken(jmap, "1", {});

		await (jmap as any).processWsPushStates("1", "acc1", {Email : "s1"});

		assert.deepEqual((jmap as any).wsPushStates["1"]["acc1"], {Email : "s1"});
		assert.equal(pushed.length, 0);
	});

	it("does nothing when the state is unchanged from the known baseline", async() =>
	{
		const pushed : any[] = [];
		const jmap = new MailJmap(createFakeApp(pushed));
		const requestManySpy = sinon.spy();
		primeWsPushToken(jmap, "1", {requestMany : requestManySpy});
		(jmap as any).wsPushStates = {"1" : {acc1 : {Email : "s1"}}};

		await (jmap as any).processWsPushStates("1", "acc1", {Email : "s1"});

		assert.isFalse(requestManySpy.called, "an unchanged state must not trigger any JMAP calls");
		assert.equal(pushed.length, 0);
	});

	it("fetches changes and pushes to the app when the state actually changed", async() =>
	{
		const pushed : any[] = [];
		const jmap = new MailJmap(createFakeApp(pushed));
		const email = {id : "email1", mailboxIds : {"inbox-id" : true}, from : [{name : "Alice", email : "alice@example.com"}], subject : "Hi", preview : "  hello  "};
		const client = {
			requestMany: async(buildFn : (t : any) => any) =>
			{
				const t = {
					Email : {
						changes: (_args : any) => ({$ref : (_p : string) => []}),
						get: (args : any) => ({$ref : (_p : string) => []})
					}
				};
				buildFn(t);
				return [{
					emailChanges: {},
					emailCreated: {list : [email]},
					emailUpdated: {list : []},
					emailDestroyed: {list : []}
				}];
			}
		};
		// folderId2path() will be called for the created email - stub it directly rather than
		// re-driving a fake WebSocket server, since this test is about the diff/dispatch logic,
		// not folder resolution (covered by its own describe() block above).
		sinon.stub(jmap as any, "folderId2path").resolves("INBOX");

		primeWsPushToken(jmap, "1", client);
		(jmap as any).wsPushStates = {"1" : {acc1 : {Email : "old-state"}}};

		await (jmap as any).processWsPushStates("1", "acc1", {Email : "new-state"});

		assert.equal(pushed.length, 1);
		assert.deepEqual(pushed[0], {
			app: "mail",
			// EGroupware's own account_id (egw.user('account_id'), mocked to 1 above), NOT Stalwart's
			// own opaque JMAP accountId ("acc1") - see buildEmailPush()'s own comment for why using
			// the latter here would mismatch every row cached by email2row().
			id: "1::1::inbox-id::email1",
			type: "add",
			acl: {folder : "INBOX", event : "MessageNew", from : "Alice <alice@example.com>", subject : "Hi", snippet : "hello"}
		});
		assert.deepEqual((jmap as any).wsPushStates["1"]["acc1"], {Email : "new-state"}, "the baseline must advance to the new state");
	});
});
