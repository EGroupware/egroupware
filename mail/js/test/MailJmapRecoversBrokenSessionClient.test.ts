import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Ticket #127461 ("cannot import new mail via jmap", Milan): a JamWebSocketClient's own session
 * fetch runs once, in its constructor (see HTTP_TIMEOUT_MS's docblock in jmap-jam-websocket.ts) -
 * if that fetch fails (eg. a DNS lookup that just never answers), `client.session` stays
 * permanently rejected for the rest of that one client instance's life. ensureToken()'s
 * cached-token fast path used to just return the still-valid cached TOKEN without ever checking
 * whether the CLIENT built from it was actually usable, so every later call (including a popup
 * reusing the opener's MailJmap instance - MailApp.jmap's own docblock) kept hitting the same dead
 * client for up to ~1h, until the next scheduled token refresh happened to replace it.
 *
 * createClient() (shared by the token-refresh path and ensureToken()'s own cached-token path) now
 * drops a client from this.clients[profileID] the moment its session rejects, and the cached-token
 * path rebuilds it - using the already-cached token, no new ajax_jmapBootstrap round trip - the
 * next time anything asks for it.
 */

function createFakeApp() : MailApp
{
	const egw : any = {
		user: (_key : string) => 1,
		lang: (label : string) => String(label),
		preference: (_key : string, _app? : string) => null,
		config: (_name : string, _app? : string) => null,
		request: sinon.stub().resolves({
			sessionUrl: "https://example.com/session",
			accountId: "acc1",
			access_token: "tok",
			expires_in: 3600,
			isLocal: false,
			customLabels: {},
		}),
	};
	return {egw, getCustomLabels: () => ({})} as unknown as MailApp;
}

describe("MailJmap.ensureToken() - recovers a client whose own session fetch already failed", () =>
{
	const OriginalWebSocket = (globalThis as any).WebSocket;
	let fetchStub : sinon.SinonStub;

	afterEach(() =>
	{
		fetchStub?.restore();
		(globalThis as any).WebSocket = OriginalWebSocket;
	});

	it("rebuilds the client on the next call using the still-cached token, without a second bootstrap round trip", async() =>
	{
		// no WebSocket global at all - keeps this test to the plain HTTP transport, irrelevant to
		// what's being exercised here
		delete (globalThis as any).WebSocket;
		const app = createFakeApp();
		const jmap = new MailJmap(app);
		const profileID = "p1";

		// the client's OWN session fetch fails outright - stands in for a DNS stall/HTTP_TIMEOUT_MS
		// timeout, either way `session` ends up permanently rejected for this client instance
		fetchStub = sinon.stub(globalThis, "fetch").rejects(new TypeError("Failed to fetch"));

		const token1 = await (jmap as any).ensureToken(profileID);
		assert.isOk(token1, "the bootstrap round trip itself must still succeed, even though the client's own session fetch will fail");
		const brokenClient = (jmap as any).clients[profileID];
		assert.isOk(brokenClient, "a client is still created optimistically alongside the token");

		// let the broken client's own session promise settle and its drop-on-rejection handler
		// (installed by createClient()) run
		await brokenClient.session.catch(() => {});
		await new Promise((resolve) => setTimeout(resolve, 0));
		assert.isUndefined((jmap as any).clients[profileID], "the broken client must drop itself once its session rejects");

		// a second call, still well within the token's expiry (the cached-token fast path), must
		// notice the client is gone and rebuild it
		const token2 = await (jmap as any).ensureToken(profileID);
		assert.strictEqual(token2, token1, "the cached token is reused as-is, not re-bootstrapped");
		assert.equal((app.egw.request as sinon.SinonStub).callCount, 1,
			"no second ajax_jmapBootstrap round trip - only the client itself needed rebuilding");
		const rebuiltClient = (jmap as any).clients[profileID];
		assert.isOk(rebuiltClient, "ensureToken() must rebuild the client using the still-cached token");
		assert.notStrictEqual(rebuiltClient, brokenClient, "must be a genuinely new client instance, not the same dead one");
	});
});
