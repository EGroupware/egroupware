import {assert} from "@open-wc/testing";
import {describeJmapError, describeSetError, MailJmap} from "../jmap";
import type {MailApp} from "../app";

const egw = {
	user: (_key : string) => 1,
	lang: (label : string) => label,
	preference: (_key : string, _app? : string) => null,
	request: async() => ({}),
};

function createFakeApp() : MailApp
{
	return {
		egw,
		mail_getCustomLabels: () => ({}),
		mail_updateCustomLabelStylesheet: () => {}
	} as unknown as MailApp;
}

function fakeEmail(overrides : Record<string, any> = {}) : Record<string, any>
{
	return {
		id: "email1",
		subject: "Test subject",
		from: [{name: "Sender", email: "sender@example.com"}],
		to: [],
		cc: [],
		bcc: [],
		keywords: {},
		sentAt: "2026-01-01T00:00:00Z",
		receivedAt: "2026-01-01T00:00:00Z",
		size: 100,
		preview: "a body snippet",
		hasAttachment: false,
		...overrides
	};
}

/**
 * Stub JamClient.requestMany(): captures the "properties" array the production code asks
 * for (the actual regression under test), then resolves with one fixed Email/get result -
 * a real JamClient batches/resolves JMAP method-call thunks, which is irrelevant to what
 * this test verifies, so it's not reimplemented here.
 */
function createFakeClient(email : Record<string, any>, capture : { properties? : string[] })
{
	return {
		requestMany: async(buildFn : (t : any) => any) =>
		{
			const t = {
				Email: {
					get: (args : any) =>
					{
						capture.properties = args.properties;
						return null;
					}
				}
			};
			buildFn(t);
			// A real JMAP server only returns the requested properties - filter the fixture the
			// same way, so a fix that (correctly) stops requesting 'preview' is actually exercised,
			// instead of the assertion passing/failing based on the fixture alone.
			const filtered : Record<string, any> = {};
			(capture.properties || []).forEach((property) => filtered[property] = email[property]);
			return [{emails: {list: [filtered]}}];
		}
	};
}

function primeToken(jmap : MailJmap, profileID : string, client : any) : void
{
	(jmap as any).tokens[profileID] = {
		sessionUrl: "https://example.com",
		accountId: "acc1",
		access_token: "tok",
		expires_at: Date.now() + 100000,
		isLocal: false,
		customLabels: {}
	};
	(jmap as any).clients[profileID] = client;
}

describe("MailJmap.fetchRows() held-back push refresh - preview snippet", () =>
{
	/**
	 * Contract: fetchRows()'s {refresh: [...]} branch (used both for egw.dataRefreshUID() and
	 * for a push 'add'/'update' NextMatch held back while this browser tab wasn't active, then
	 * applied on return - see Et2Datagrid's refresh handling) must respect the same "Sneak
	 * preview in list" (filter2) setting getRows() (the normal listing fetch) already does -
	 * not fetch/include a body-preview snippet when that setting is off.
	 *
	 * Setup: fetchRows() with a {refresh: [rowId]} range and filter2 falsy, against a stubbed
	 * JMAP client that captures the requested Email/get properties and returns a fixed Email
	 * with a non-empty "preview" field (as a real server might, if asked for it).
	 *
	 * Pass: 'preview' is NOT in the requested properties, and the resulting row's
	 * "bodypreview" field is empty - proving the client discarded/never asked for the snippet.
	 */
	it("does not request or include the preview snippet when the setting is off", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const capture : { properties? : string[] } = {};
		primeToken(jmap, "1", createFakeClient(fakeEmail(), capture));

		const result : any = await jmap.fetchRows("exec", {refresh: ["1::1::mbox1::email1"]},
			{filter2: ""}, "widget", [], 0);

		assert.isOk(result, "fetchRows() should resolve a real result, not false");
		assert.notInclude(capture.properties, "preview",
			"Email/get must not request 'preview' when filter2 is falsy");
		assert.equal(result.data["1::1::mbox1::email1"].bodypreview, "",
			"row must have no snippet when the setting is off, even if the server sent one back");
	});

	/**
	 * Contract: the same {refresh: [...]} branch DOES request/include the preview snippet when
	 * the "Sneak preview in list" setting is on - this is the positive-case counterpart, so a
	 * future change that stops requesting 'preview' unconditionally (over-fixing this bug in
	 * the other direction) also fails a test.
	 */
	it("requests and includes the preview snippet when the setting is on", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const capture : { properties? : string[] } = {};
		primeToken(jmap, "1", createFakeClient(fakeEmail(), capture));

		const result : any = await jmap.fetchRows("exec", {refresh: ["1::1::mbox1::email1"]},
			{filter2: "on"}, "widget", [], 0);

		assert.include(capture.properties, "preview",
			"Email/get must request 'preview' when filter2 is truthy");
		assert.equal(result.data["1::1::mbox1::email1"].bodypreview, "a body snippet",
			"row must carry the server's snippet when the setting is on");
	});
});

/**
 * Contract: MailJmap.email2row() flags a From/To/Cc/Bcc field as "suspect" when the server's
 * own address-list parsing left an entry with no usable email address - the shape a real JMAP
 * server's (eg. Stalwart's) address parser produces when it isn't RFC 2047-aware and mis-splits
 * a sending MUA's malformed encoded-word (a literal, unencoded comma inside a quoted display
 * name - valid per RFC 2047, but breaks a naive comma-split). MailApp.renderMessageInto()
 * (mail/js/app.ts) uses this flag to trigger an on-demand repair - see
 * mail_ui::ajax_parseAddressList()'s docblock for the full story.
 */
describe("MailJmap.email2row() - suspect address field detection", () =>
{
	it("flags a field whose address list has an entry with no usable email", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const capture : { properties? : string[] } = {};
		const email = fakeEmail({
			cc: [{name: "Jane Doe", email: "jane.doe@example.com"}, {name: "broken fragment", email: ""}],
		});
		primeToken(jmap, "1", createFakeClient(email, capture));

		const result : any = await jmap.fetchRows("exec", {refresh: ["1::1::mbox1::email1"]},
			{filter2: ""}, "widget", [], 0);

		assert.include(result.data["1::1::mbox1::email1"].suspectAddressFields, "cc");
	});

	/**
	 * Real-world shape found live against Stalwart: the address boundary is found correctly
	 * (a valid, complete email address), but the display-name decode leaves stray backslashes/
	 * quotes behind - eg. name: '\\"Example_Corp", " Consulting\\"' for what should have been
	 * "Example Corp, Consulting". A literal backslash never legitimately appears in a decoded
	 * display name, so this is a reliable "something went wrong" signal on its own, even though
	 * the email address itself is completely valid.
	 */
	it("flags a field whose entry has a valid email but a backslash-mangled display name", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const capture : { properties? : string[] } = {};
		const email = fakeEmail({
			cc: [{name: "Jane Doe", email: "jane.doe@example.com"},
				{name: '\\"Example Corp", " Consulting\\"', email: "info@example.com"}],
		});
		primeToken(jmap, "1", createFakeClient(email, capture));

		const result : any = await jmap.fetchRows("exec", {refresh: ["1::1::mbox1::email1"]},
			{filter2: ""}, "widget", [], 0);

		assert.include(result.data["1::1::mbox1::email1"].suspectAddressFields, "cc");
	});

	it("does not flag a well-formed address list", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const capture : { properties? : string[] } = {};
		const email = fakeEmail({
			cc: [{name: "Jane Doe", email: "jane.doe@example.com"}, {name: "John Smith", email: "john.smith@example.com"}],
		});
		primeToken(jmap, "1", createFakeClient(email, capture));

		const result : any = await jmap.fetchRows("exec", {refresh: ["1::1::mbox1::email1"]},
			{filter2: ""}, "widget", [], 0);

		assert.deepEqual(result.data["1::1::mbox1::email1"].suspectAddressFields, []);
	});
});

describe("describeJmapError() - classify a caught jmap-jam rejection", () =>
{
	it("formats a single {type, description} error object using description", () =>
	{
		assert.equal(describeJmapError({type: "invalidArguments", description: "bad name"}), "bad name");
	});

	it("falls back to type when description is missing", () =>
	{
		assert.equal(describeJmapError({type: "accountNotFound"}), "accountNotFound");
	});

	it("joins an array of error objects (requestMany()'s shape) with '; '", () =>
	{
		assert.equal(
			describeJmapError([{type: "invalidArguments", description: "bad name"}, {type: "forbidden"}]),
			"bad name; forbidden");
	});

	it("returns null for a plain network-failure Error/TypeError (no .type) - keeps silent fallback", () =>
	{
		assert.isNull(describeJmapError(new TypeError("Failed to fetch")));
		assert.isNull(describeJmapError(new Error("boom")));
	});

	it("returns null for a non-object/string throw", () =>
	{
		assert.isNull(describeJmapError("plain string error"));
		assert.isNull(describeJmapError(undefined));
	});

	it("returns null for an empty array", () =>
	{
		assert.isNull(describeJmapError([]));
	});
});

describe("describeSetError() - format a Mailbox/set or Email/set per-item SetError map", () =>
{
	it("formats a single SetError entry", () =>
	{
		assert.equal(describeSetError({c0: {type: "invalidProperties", description: "name already exists"}}),
			"name already exists");
	});

	it("joins multiple SetError entries with '; '", () =>
	{
		assert.equal(
			describeSetError({id1: {type: "notFound"}, id2: {type: "forbidden", description: "no access"}}),
			"notFound; no access");
	});

	it("returns null for undefined or an empty map", () =>
	{
		assert.isNull(describeSetError(undefined));
		assert.isNull(describeSetError({}));
	});
});
