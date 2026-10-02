import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * help.egroupware.org "Rückmeldung zu 26.9.20260928" (Jürgen, two separate installs, both on
 * Firefox): the 30-second background autorefresh poll (Et2NextmatchAutoRefresh) intermittently
 * hit a genuine "couldn't even talk to the server" failure (a raw Firefox network-stack error,
 * "Content-Length header of network response exceeds response Body") specifically right after
 * switching back to the EGroupware tab from another window/tab - exactly when a browser is most
 * likely resuming a connection it throttled/suspended while backgrounded. Before this fix,
 * handleFetchRowsError() treated that identically to a real account problem: it opened the
 * account-edit wizard popup (which then even got blocked by the browser's own popup blocker,
 * adding a second, more confusing message on top) - for what the wizard's own fresh diagnosis
 * then found nothing wrong with, and what the very next poll cycle would have resolved quietly
 * on its own anyway.
 *
 * Reproduces the failure directly against MailJmap.fetchRows(), without depending on
 * Et2NextmatchAutoRefresh at all - the fix only needs to know "did this tab regain visibility
 * just now", not "was this specific call an autorefresh tick". Sets recentlyResumedVisibilityAt
 * directly rather than dispatching a real 'visibilitychange' event: this whole test run shares
 * one document across every test file's MailJmap instances (each constructor call adds its own
 * listener, never removed), so a real event can't be scoped to just this file's instances.
 */

function createFakeApp(messages : Array<[string, string]>) : MailApp
{
	const egw = {
		user: (_key : string) => 1,
		lang: (label : string, ...args : string[]) =>
		{
			let i = 0;
			return String(label).replace(/%(\d+)/g, () => args[i++] ?? '');
		},
		preference: (_key : string, _app? : string) => null,
		config: (_name : string, _app? : string) => null,
		request: async() => ({}),
		message: (msg : string, type : string) => messages.push([msg, type]),
		link: (_path : string, _params : Record<string, any>) => "https://example.com/",
		open_link: () => { throw new Error("popupCheckCert() must not open the account wizard"); },
	};
	return {egw, getCustomLabels: () => ({})} as unknown as MailApp;
}

/**
 * A client that resolves mailboxId()'s and mailboxRole()'s own lookups normally, but blows up
 * with a plain (non-JMAP-shaped) network error on the actual Email/query+Email/get list fetch -
 * mailboxId() itself wraps any failure into a JmapUserError with its own friendly message (a
 * different, already-handled case - see its own docblock), so reproducing the real
 * "couldn't even talk to the server" symptom this fix targets needs the failure to happen one
 * level further in, at the main list query jmap.ts's getRows() does not itself wrap.
 */
function createFailingClient()
{
	return {
		requestMany: async(buildFn : (t : any) => any) =>
		{
			const t = {
				Mailbox: {
					query: (_args : any) => ({ids: ["mbox1"]}),
					get: (_args : any) => ({list: [{role: null}]}),
				},
				Email: {
					query: (_args : any) : any => { throw new TypeError("Failed to fetch"); },
					get: (_args : any) => ({list: []}),
				},
			};
			return [buildFn(t)];
		},
	};
}

function primeToken(jmap : MailJmap, profileID : string, client : any) : void
{
	(jmap as any).tokens[profileID] = {
		sessionUrl: "https://example.com",
		accountId: "acc1",
		access_token: "tok",
		expires_at: Date.now() + 100000,
		// skips the (irrelevant here) large-mailbox cutoff lookup, which would otherwise also
		// need Email/query stubbed out - see getRows()'s own "never reachable via the local IMAP
		// shim" comment
		isLocal: true,
		customLabels: {},
	};
	(jmap as any).clients[profileID] = client;
}

describe("MailJmap.handleFetchRowsError() - suppresses popupCheckCert() right after the tab regains visibility", () =>
{
	it("opens the account wizard for a generic network failure with no recent visibility change", async() =>
	{
		const messages : Array<[string, string]> = [];
		const app = createFakeApp(messages);
		const jmap = new MailJmap(app);
		// a distinct profileID per test - popupCheckCert()'s own 5min debounce is keyed by
		// profileID in sessionStorage, which survives across this whole test run
		const profileID = "novisibility1";
		primeToken(jmap, profileID, createFailingClient());
		// this whole test run shares one document across every test file's MailJmap instances
		// (each constructor call adds its own listener, never removed) - a real, unrelated
		// visibility toggle elsewhere in the suite could otherwise leak into this fresh instance;
		// pin it explicitly instead of relying on document.hidden's ambient state
		(jmap as any).recentlyResumedVisibilityAt = 0;

		let threw = false;
		try
		{
			await jmap.fetchRows("exec", {start: 0, num_rows: 50}, {selectedFolder: profileID + "::INBOX"}, "widget", [], 0);
		}
		catch (_e)
		{
			// the fake open_link() throws to prove popupCheckCert() actually tried to open it -
			// which also means the error toast right after it in handleFetchRowsError() never
			// runs in this fake, unlike a real open_link() call that returns normally
			threw = true;
		}

		assert.isTrue(threw, "must attempt to open the account wizard when there was no recent visibility change");
	});

	it("stays quiet (no wizard, no toast) for the same failure right after the tab regained visibility", async() =>
	{
		const messages : Array<[string, string]> = [];
		const app = createFakeApp(messages);
		const jmap = new MailJmap(app);
		const profileID = "recentvisibility1";
		primeToken(jmap, profileID, createFailingClient());
		// same field the real document-level visibilitychange listener (installed in the
		// constructor) sets - see the "no recent visibility change" test's own comment for why
		// this is set directly rather than via a real dispatched event
		(jmap as any).recentlyResumedVisibilityAt = Date.now();

		const result : any = await jmap.fetchRows("exec", {start: 0, num_rows: 50},
			{selectedFolder: profileID + "::INBOX"}, "widget", [], 0);

		assert.isOk(result, "fetchRows() must still resolve a real (empty) result, never reject");
		assert.deepEqual(result.order, []);
		assert.equal(messages.length, 0, "must not surface a disruptive error toast for this transient case");
	});

	it("opens the account wizard again once the visibility-resume grace period has passed", async() =>
	{
		const messages : Array<[string, string]> = [];
		const app = createFakeApp(messages);
		const jmap = new MailJmap(app);
		const profileID = "elapsedvisibility1";
		primeToken(jmap, profileID, createFailingClient());
		// simulate the grace period having elapsed, without a real sleep
		(jmap as any).recentlyResumedVisibilityAt = Date.now() - 6000;

		let threw = false;
		try
		{
			await jmap.fetchRows("exec", {start: 0, num_rows: 50}, {selectedFolder: profileID + "::INBOX"}, "widget", [], 0);
		}
		catch (_e)
		{
			threw = true;
		}

		assert.isTrue(threw, "a failure well outside the grace period is a real failure again");
	});
});
