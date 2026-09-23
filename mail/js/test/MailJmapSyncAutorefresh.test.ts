import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Ticket-driven regression (2026-09-23, real forum report help.egroupware.org "26.9.20260922
 * Ständiger reload vom Posteingang" - many users hit high CPU/constant visible reloads): the
 * classic server-rendered nextmatch (mail_ui::get_rows(), removed for the full client-side JMAP
 * migration) used to dynamically disable the row list's periodic autorefresh timer once push was
 * confirmed working for the current account (commits 9a005ab7c0/6bd87cafb5, 2020) - nothing
 * replaced it, so every account fell back to Et2Nextmatch's own default polling interval even
 * when real push already covered it.
 *
 * MailJmap.syncAutorefresh() restores this, called from shapeFetchResult() on every row fetch
 * (same granularity the removed get_rows() used) - see its own docblock for why token.enableWsPush
 * is only trusted for a non-local (real JMAP/Stalwart) account, never a local/IMAP-shim one.
 */

function createFakeApp(nm : any) : MailApp
{
	const egw = {
		user: (_key : string) => 1,
		lang: (label : string) => label,
		preference: (_key : string, _app? : string) => null,
		config: (_name : string, _app? : string) => null,
		request: async() => ({}),
		message: (_msg : string, _type? : string) => {},
	};
	return {
		egw,
		getCustomLabels: () => ({}),
		nm_index: "nm",
		et2: {getWidgetById: (id : string) => id === "nm" ? nm : null},
	} as unknown as MailApp;
}

function primeToken(jmap : MailJmap, profileID : string, overrides : Record<string, any>) : void
{
	(jmap as any).tokens[profileID] = {
		sessionUrl: "https://example.com", accountId: "acc1", access_token: "tok",
		expires_at: Date.now() + 100000, isLocal: false, customLabels: {},
		enableWsPush: false, pushAvailable: false,
		...overrides,
	};
}

function callSync(jmap : MailJmap, selectedFolder : string) : void
{
	(jmap as any).syncAutorefresh(selectedFolder);
}

describe("MailJmap.syncAutorefresh()", () =>
{
	it("disables autorefresh when the server-relayed push subscription is available (pushAvailable)", () =>
	{
		const nm : any = {settings: {disable_autorefresh: false}};
		const jmap = new MailJmap(createFakeApp(nm));
		primeToken(jmap, "1", {pushAvailable: true, enableWsPush: false});

		callSync(jmap, "1::INBOX");

		assert.isTrue(nm.settings.disable_autorefresh);
	});

	it("disables autorefresh for a real-JMAP (non-local) account using the WS-fallback transport (enableWsPush)", () =>
	{
		const nm : any = {settings: {disable_autorefresh: false}};
		const jmap = new MailJmap(createFakeApp(nm));
		primeToken(jmap, "1", {enableWsPush: true, isLocal: false, pushAvailable: false});

		callSync(jmap, "1::INBOX");

		assert.isTrue(nm.settings.disable_autorefresh);
	});

	it("does NOT disable autorefresh for a local/IMAP-shim account just because enableWsPush is set", () =>
	{
		// enableWsPush is an installation-wide fact (no working EGroupware push-server), set
		// regardless of account type - but a local/shim account's JMAP session never advertises a
		// websocket capability, so that transport never actually connects for it. Trusting it here
		// would wrongly disable autorefresh even for an IMAP host never on the admin's
		// "imap_hosts_with_push" allowlist.
		const nm : any = {settings: {disable_autorefresh: false}};
		const jmap = new MailJmap(createFakeApp(nm));
		primeToken(jmap, "1", {enableWsPush: true, isLocal: true, pushAvailable: false});

		callSync(jmap, "1::INBOX");

		assert.isFalse(nm.settings.disable_autorefresh);
	});

	it("leaves autorefresh enabled when neither push mechanism is available", () =>
	{
		const nm : any = {settings: {disable_autorefresh: false}};
		const jmap = new MailJmap(createFakeApp(nm));
		primeToken(jmap, "1", {enableWsPush: false, pushAvailable: false});

		callSync(jmap, "1::INBOX");

		assert.isFalse(nm.settings.disable_autorefresh);
	});

	it("re-enables autorefresh when switching from a push-capable account to one without push", () =>
	{
		const nm : any = {settings: {disable_autorefresh: false}};
		const jmap = new MailJmap(createFakeApp(nm));
		primeToken(jmap, "1", {pushAvailable: true});
		primeToken(jmap, "2", {pushAvailable: false, enableWsPush: false});

		callSync(jmap, "1::INBOX");
		assert.isTrue(nm.settings.disable_autorefresh, "account 1 has push - autorefresh off");

		callSync(jmap, "2::INBOX");
		assert.isFalse(nm.settings.disable_autorefresh, "account 2 has no push - autorefresh back on");
	});

	it("is a no-op when there is no nm widget (eg. not yet rendered)", () =>
	{
		const jmap = new MailJmap(createFakeApp(null));
		primeToken(jmap, "1", {pushAvailable: true});

		assert.doesNotThrow(() => callSync(jmap, "1::INBOX"));
	});

	it("is a no-op when there is no token yet for this account", () =>
	{
		const nm : any = {settings: {disable_autorefresh: false}};
		const jmap = new MailJmap(createFakeApp(nm));

		callSync(jmap, "999::INBOX");

		assert.isFalse(nm.settings.disable_autorefresh);
	});
});
