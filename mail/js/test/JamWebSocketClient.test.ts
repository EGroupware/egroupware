import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {JamWebSocketClient} from "../jmap-jam-websocket";

/**
 * Minimal fake WebSocket - just enough of the real interface (readyState, addEventListener, send,
 * close) for JamWebSocketClient to drive, plus simulateOpen()/simulateMessage()/simulateClose() test
 * helpers to play the server side of the RFC 8887 exchange.
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

function fakeSession(withWebSocket : boolean) : any
{
	return {
		capabilities : withWebSocket
			? {"urn:ietf:params:jmap:websocket" : {url : "wss://example.com/jmap/ws/", supportsPush : false}}
			: {},
		accounts : {},
		primaryAccounts : {},
		apiUrl : "https://example.com/jmap/api/",
		downloadUrl : "https://example.com/jmap/download/{accountId}/{blobId}/{name}?type={type}",
		uploadUrl : "https://example.com/jmap/upload/{accountId}/",
		eventSourceUrl : "https://example.com/jmap/eventsource/",
		state : "state1"
	};
}

/** Stubs fetch() for both the session bootstrap GET and, if the HTTP transport is exercised, the JMAP POST. */
function stubFetch(session : any, jmapResponse? : any) : sinon.SinonStub
{
	return sinon.stub(globalThis, "fetch").callsFake(async(_url : any, init? : any) : Promise<any> =>
	{
		if (!init || init.method !== "POST")
		{
			return {json : async() => session};
		}
		return {
			ok : true,
			headers : {get : () => "application/json"},
			json : async() => jmapResponse
		};
	});
}

/** Flushes both the already-resolved session promise and the microtask/macrotask chain #connect() runs after it. */
async function flush() : Promise<void>
{
	await new Promise((resolve) => setTimeout(resolve, 0));
}

describe("JamWebSocketClient", () =>
{
	const OriginalWebSocket = (globalThis as any).WebSocket;
	let fetchStub : sinon.SinonStub;

	afterEach(() =>
	{
		fetchStub?.restore();
		(globalThis as any).WebSocket = OriginalWebSocket;
		FakeWebSocket.instances = [];
	});

	it("stays on HTTP when the session has no websocket capability", async() =>
	{
		(globalThis as any).WebSocket = FakeWebSocket;
		fetchStub = stubFetch(fakeSession(false), {
			methodResponses : [["Core/echo", {hello : "world"}, "r1"]],
			sessionState : "state1"
		});

		const client = new JamWebSocketClient({sessionUrl : "https://example.com/session", bearerToken : "tok"});
		await client.session;
		await flush();

		assert.equal(FakeWebSocket.instances.length, 0, "no WebSocket should be opened without the capability");
		assert.equal(client.transport, "http");

		const [data] = await client.request(["Core/echo", {hello : "world"}]);
		assert.deepEqual(data, {hello : "world"});
	});

	it("connects over WebSocket when the capability is present, and correlates request/response by id", async() =>
	{
		(globalThis as any).WebSocket = FakeWebSocket;
		fetchStub = stubFetch(fakeSession(true));

		const client = new JamWebSocketClient({sessionUrl : "https://example.com/session", bearerToken : "tok"});
		await client.session;
		await flush();

		assert.equal(FakeWebSocket.instances.length, 1);
		const socket = FakeWebSocket.instances[0];
		assert.equal(socket.url, "wss://example.com/jmap/ws/");
		assert.equal(socket.protocol, "jmap");

		socket.simulateOpen();
		assert.equal(client.transport, "websocket");

		const pending = client.request(["Core/echo", {hello : "world"}]);
		await flush();

		assert.equal(socket.sent.length, 1);
		const sentFrame = JSON.parse(socket.sent[0]);
		assert.equal(sentFrame["@type"], "Request");
		assert.isString(sentFrame.id);
		assert.deepEqual(sentFrame.methodCalls, [["Core/echo", {hello : "world"}, "r1"]]);
		assert.include(sentFrame.using, "urn:ietf:params:jmap:core");

		socket.simulateMessage({
			"@type" : "Response",
			requestId : sentFrame.id,
			sessionState : "state1",
			methodResponses : [["Core/echo", {hello : "world"}, "r1"]]
		});

		const [data, meta] = await pending;
		assert.deepEqual(data, {hello : "world"});
		assert.equal(meta.sessionState, "state1");
	});

	it("rejects with a plain ProblemDetails object on a RequestError frame", async() =>
	{
		(globalThis as any).WebSocket = FakeWebSocket;
		fetchStub = stubFetch(fakeSession(true));

		const client = new JamWebSocketClient({sessionUrl : "https://example.com/session", bearerToken : "tok"});
		await client.session;
		await flush();

		const socket = FakeWebSocket.instances[0];
		socket.simulateOpen();

		const pending = client.request(["Core/echo", {}]);
		await flush();
		const sentFrame = JSON.parse(socket.sent[0]);

		socket.simulateMessage({
			"@type" : "RequestError",
			requestId : sentFrame.id,
			type : "urn:ietf:params:jmap:error:notRequest",
			status : 400,
			detail : "boom"
		});

		const error : any = await pending.catch((e) => e);
		assert.deepEqual(error, {type : "urn:ietf:params:jmap:error:notRequest", status : 400, detail : "boom"});
	});

	it("rejects in-flight requests (without retrying over HTTP) when the socket closes unexpectedly", async() =>
	{
		(globalThis as any).WebSocket = FakeWebSocket;
		fetchStub = stubFetch(fakeSession(true));

		const client = new JamWebSocketClient({sessionUrl : "https://example.com/session", bearerToken : "tok"});
		await client.session;
		await flush();

		const socket = FakeWebSocket.instances[0];
		socket.simulateOpen();

		const pending = client.request(["Core/echo", {}]);
		await flush();

		socket.simulateClose();

		let rejected = false;
		await pending.catch(() => rejected = true);
		assert.isTrue(rejected, "a request in flight when the socket closes must reject, not hang or silently retry over HTTP");
		assert.equal(client.transport, "http");
		assert.equal(fetchStub.callCount, 1, "only the session bootstrap fetch should have happened - no HTTP retry of the lost request");
	});
});

describe("JamWebSocketClient.requestMany()", () =>
{
	const OriginalWebSocket = (globalThis as any).WebSocket;
	let fetchStub : sinon.SinonStub;

	afterEach(() =>
	{
		fetchStub?.restore();
		(globalThis as any).WebSocket = OriginalWebSocket;
		FakeWebSocket.instances = [];
	});

	it("sends every drafted call in one batch, keyed by the caller's own ids", async() =>
	{
		(globalThis as any).WebSocket = FakeWebSocket;
		fetchStub = stubFetch(fakeSession(true));

		const client = new JamWebSocketClient({sessionUrl : "https://example.com/session", bearerToken : "tok"});
		await client.session;
		await flush();
		const socket = FakeWebSocket.instances[0];
		socket.simulateOpen();

		const pending = client.requestMany((t : any) => ({
			a : t.Mailbox.get({accountId : "acc1", ids : ["1"]}),
			b : t.Identity.get({accountId : "acc1"})
		}));
		await flush();

		const sentFrame = JSON.parse(socket.sent[0]);
		assert.deepEqual(sentFrame.methodCalls, [
			["Mailbox/get", {accountId : "acc1", ids : ["1"]}, "a"],
			["Identity/get", {accountId : "acc1"}, "b"]
		]);
		assert.include(sentFrame.using, "urn:ietf:params:jmap:mail");
		assert.include(sentFrame.using, "urn:ietf:params:jmap:submission");

		socket.simulateMessage({
			"@type" : "Response",
			requestId : sentFrame.id,
			sessionState : "state1",
			methodResponses : [
				["Mailbox/get", {list : [{id : "1"}]}, "a"],
				["Identity/get", {list : []}, "b"]
			]
		});

		const [result] = await pending;
		assert.deepEqual(result.a, {list : [{id : "1"}]});
		assert.deepEqual(result.b, {list : []});
	});

	it("resolves $ref() into a JMAP result reference pointing at the earlier call's own id", async() =>
	{
		(globalThis as any).WebSocket = FakeWebSocket;
		fetchStub = stubFetch(fakeSession(true));

		const client = new JamWebSocketClient({sessionUrl : "https://example.com/session", bearerToken : "tok"});
		await client.session;
		await flush();
		const socket = FakeWebSocket.instances[0];
		socket.simulateOpen();

		const pending = client.requestMany((t : any) =>
		{
			const ids = t.Mailbox.query({accountId : "acc1", filter : {}});
			const mailboxes = t.Mailbox.get({accountId : "acc1", ids : ids.$ref("/ids")});
			return {ids, mailboxes};
		});
		await flush();

		const sentFrame = JSON.parse(socket.sent[0]);
		assert.deepEqual(sentFrame.methodCalls, [
			["Mailbox/query", {accountId : "acc1", filter : {}}, "ids"],
			["Mailbox/get", {accountId : "acc1", "#ids" : {name : "Mailbox/query", resultOf : "ids", path : "/ids"}}, "mailboxes"]
		]);

		socket.simulateMessage({
			"@type" : "Response",
			requestId : sentFrame.id,
			sessionState : "state1",
			methodResponses : [
				["Mailbox/query", {ids : ["1", "2"]}, "ids"],
				["Mailbox/get", {list : [{id : "1"}, {id : "2"}]}, "mailboxes"]
			]
		});

		const [result] = await pending;
		assert.deepEqual(result.mailboxes, {list : [{id : "1"}, {id : "2"}]});
	});

	it("rejects with all ProblemDetails when any call in the batch errors, discarding the successful ones too", async() =>
	{
		(globalThis as any).WebSocket = FakeWebSocket;
		fetchStub = stubFetch(fakeSession(true));

		const client = new JamWebSocketClient({sessionUrl : "https://example.com/session", bearerToken : "tok"});
		await client.session;
		await flush();
		const socket = FakeWebSocket.instances[0];
		socket.simulateOpen();

		const pending = client.requestMany((t : any) => ({
			a : t.Mailbox.get({accountId : "acc1", ids : ["1"]}),
			b : t.Identity.get({accountId : "acc1"})
		}));
		await flush();
		const sentFrame = JSON.parse(socket.sent[0]);

		socket.simulateMessage({
			"@type" : "Response",
			requestId : sentFrame.id,
			sessionState : "state1",
			methodResponses : [
				["Mailbox/get", {list : [{id : "1"}]}, "a"],
				["error", {type : "urn:ietf:params:jmap:error:invalidArguments"}, "b"]
			]
		});

		const error : any = await pending.catch((e) => e);
		assert.deepEqual(error, [{type : "urn:ietf:params:jmap:error:invalidArguments"}]);
	});

	it("delegates to the inherited HTTP requestMany() when there is no websocket capability", async() =>
	{
		(globalThis as any).WebSocket = FakeWebSocket;
		fetchStub = stubFetch(fakeSession(false), {
			methodResponses : [["Mailbox/get", {list : []}, "a"]],
			sessionState : "state1"
		});

		const client = new JamWebSocketClient({sessionUrl : "https://example.com/session", bearerToken : "tok"});
		await client.session;
		await flush();

		assert.equal(FakeWebSocket.instances.length, 0);

		const [result] = await client.requestMany((t : any) => ({a : t.Mailbox.get({accountId : "acc1", ids : ["1"]})}));
		assert.deepEqual(result.a, {list : []});
	});
});

describe("JamWebSocketClient.onPush()", () =>
{
	const OriginalWebSocket = (globalThis as any).WebSocket;
	let fetchStub : sinon.SinonStub;

	afterEach(() =>
	{
		fetchStub?.restore();
		(globalThis as any).WebSocket = OriginalWebSocket;
		FakeWebSocket.instances = [];
	});

	it("sends WebSocketPushEnable on first registration, and WebSocketPushDisable once the last callback unsubscribes", async() =>
	{
		(globalThis as any).WebSocket = FakeWebSocket;
		fetchStub = stubFetch(fakeSession(true));

		const client = new JamWebSocketClient({sessionUrl : "https://example.com/session", bearerToken : "tok"});
		await client.session;
		await flush();
		const socket = FakeWebSocket.instances[0];
		socket.simulateOpen();

		assert.equal(socket.sent.length, 0, "no push frame should be sent before any onPush() registration");

		const unsubscribeA = client.onPush(() => {});
		assert.equal(socket.sent.length, 1);
		let frame = JSON.parse(socket.sent[0]);
		assert.equal(frame["@type"], "WebSocketPushEnable");
		assert.isNull(frame.dataTypes, "the default pushDataTypes '*' must become dataTypes: null on the wire");

		const unsubscribeB = client.onPush(() => {});
		assert.equal(socket.sent.length, 1, "a second registration while already enabled must not resend WebSocketPushEnable");

		unsubscribeA();
		assert.equal(socket.sent.length, 1, "unsubscribing while another callback is still registered must not disable push");

		unsubscribeB();
		assert.equal(socket.sent.length, 2);
		frame = JSON.parse(socket.sent[1]);
		assert.equal(frame["@type"], "WebSocketPushDisable");
	});

	it("dispatches StateChange frames to every registered callback, stripped of the transport-only @type", async() =>
	{
		(globalThis as any).WebSocket = FakeWebSocket;
		fetchStub = stubFetch(fakeSession(true));

		const client = new JamWebSocketClient({sessionUrl : "https://example.com/session", bearerToken : "tok"});
		await client.session;
		await flush();
		const socket = FakeWebSocket.instances[0];
		socket.simulateOpen();

		const received : any[] = [];
		client.onPush((change) => received.push(change));
		client.onPush((change) => received.push(change));

		socket.simulateMessage({
			"@type" : "StateChange",
			changed : {acc1 : {Email : "state-a"}},
			pushState : "push-1"
		});

		assert.equal(received.length, 2, "both registered callbacks must be called");
		received.forEach((change) =>
		{
			assert.deepEqual(change, {changed : {acc1 : {Email : "state-a"}}, pushState : "push-1"});
		});
	});

	it("re-enables push with the last known pushState after a reconnect", async() =>
	{
		(globalThis as any).WebSocket = FakeWebSocket;
		fetchStub = stubFetch(fakeSession(true));
		const clock = sinon.useFakeTimers();

		try
		{
			const client = new JamWebSocketClient({sessionUrl : "https://example.com/session", bearerToken : "tok"});
			await client.session;
			await clock.tickAsync(0);

			const firstSocket = FakeWebSocket.instances[0];
			firstSocket.simulateOpen();

			client.onPush(() => {});
			assert.equal(JSON.parse(firstSocket.sent[0])["@type"], "WebSocketPushEnable");

			firstSocket.simulateMessage({"@type" : "StateChange", changed : {acc1 : {Email : "s1"}}, pushState : "push-42"});
			firstSocket.simulateClose();

			await clock.tickAsync(1000);

			assert.equal(FakeWebSocket.instances.length, 2, "a new WebSocket should have been opened after the reconnect delay");
			const secondSocket = FakeWebSocket.instances[1];
			secondSocket.simulateOpen();

			assert.equal(secondSocket.sent.length, 1);
			const enableFrame = JSON.parse(secondSocket.sent[0]);
			assert.equal(enableFrame["@type"], "WebSocketPushEnable");
			assert.equal(enableFrame.pushState, "push-42", "the reconnect must carry the last pushState so the server can deliver missed changes");
		}
		finally
		{
			clock.restore();
		}
	});
});
