import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Ticket #127461's second connectivity gap ("we do not recover automatically", ralf): recovery
 * used to be purely reactive, happening only inside confirmCheckCert()'s own recheck, itself only
 * ever scheduled after some real request had already failed. Nothing ever listened for the
 * browser's own 'online' event. onOnline() (installed once, window-level, in the constructor) is
 * the proactive half: for every profile, it lifts any ineligible-until lockout and kicks that
 * profile's client (JamWebSocketClient.retryNow() - a no-op if already connected or not
 * WebSocket-capable at all); then, for whichever profile is actually on screen, it runs one cheap
 * probe - 'online' only ever reflects the local network interface, never whether the JMAP endpoint
 * itself is reachable (RECONNECT_DELAYS_MS' own docblock in jmap-jam-websocket.ts) - and refreshes
 * the grid only once that probe actually succeeds.
 */

function createFakeApp(refreshed : {count : number}) : MailApp
{
	const egw : any = {
		user: (_key : string) => 1,
		lang: (label : string) => String(label),
		preference: (key : string, _app? : string) => key === 'ActiveProfileID' ? 'p1' : null,
		config: (_name : string, _app? : string) => null,
		request: sinon.stub().resolves({}),
	};
	return {
		egw,
		getCustomLabels: () => ({}),
		refreshMessageGrid: () => { refreshed.count++; },
	} as unknown as MailApp;
}

function primeToken(jmap : MailJmap, profileID : string, client : any) : void
{
	(jmap as any).tokens[profileID] = {
		sessionUrl: "https://example.com", accountId: "acc1", access_token: "tok",
		expires_at: Date.now() + 100000, isLocal: true, customLabels: {},
	};
	(jmap as any).clients[profileID] = client;
}

async function flush() : Promise<void>
{
	await new Promise((resolve) => setTimeout(resolve, 0));
	await new Promise((resolve) => setTimeout(resolve, 0));
}

describe("MailJmap.onOnline()", () =>
{
	it("retries every profile's client immediately, lifts ineligible lockouts, and refreshes the active profile's grid once reachable again", async() =>
	{
		const refreshed = {count : 0};
		const app = createFakeApp(refreshed);
		const jmap = new MailJmap(app);

		const retried : string[] = [];
		primeToken(jmap, "p1", {
			retryNow : () => retried.push("p1"),
			requestMany : async() => [{ids : {ids : []}}],
		});
		primeToken(jmap, "p2", {retryNow : () => retried.push("p2")});
		(jmap as any).ineligibleUntil["p1"] = Date.now() + 100000;

		(jmap as any).onOnline();
		await flush();

		assert.deepEqual(retried.sort(), ["p1", "p2"], "every profile's client must be kicked, not just the active one");
		assert.isUndefined((jmap as any).ineligibleUntil["p1"], "an ineligible lockout must be lifted too");
		assert.equal(refreshed.count, 1, "the active profile's grid must be refreshed once the probe succeeds");
	});

	it("stays silent - no grid refresh, nothing thrown - while the active profile is still unreachable", async() =>
	{
		const refreshed = {count : 0};
		const app = createFakeApp(refreshed);
		const jmap = new MailJmap(app);

		primeToken(jmap, "p1", {
			retryNow : () => {},
			requestMany : async() => { throw new TypeError("Failed to fetch"); },
		});

		assert.doesNotThrow(() => (jmap as any).onOnline());
		await flush();

		assert.equal(refreshed.count, 0, "must not refresh the grid while the probe still fails");
	});
});
