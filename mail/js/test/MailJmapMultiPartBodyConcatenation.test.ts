import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Tickets #128441 and #128091 (live, reported 2026-11): Apple Mail (iOS/iPadOS/macOS Mail) can
 * split ONE logical message body into several sibling text/plain or text/html parts with an
 * inline (non-alternative) attachment placed in between them, whenever the user inserts a file
 * mid-message. A spec-compliant JMAP server (Stalwart) returns every one of those parts in
 * textBody/htmlBody (RFC 8621) - this client only ever took the FIRST one
 * (`htmlParts.find(...)`/`textParts[0]`), silently dropping every later fragment. For #128441
 * that meant the real text (typed AFTER the inline attachment) never appeared at all; for #128091,
 * only the first of several html fragments showed up. Server-side, the same class of bug was
 * fixed in Api\Mail\Jmap\Imap::emailBodyFields() (the classic/shimmed-IMAP path) - this is the
 * client-side twin, for a REAL JMAP/Stalwart account (which never calls that PHP code at all).
 */

const egw = {
	user: (_key : string) => 1,
	lang: (label : string) => label,
	preference: (_key : string, _app? : string) => null,
	config: (_name : string, _app? : string) => null,
	request: async() => ({}),
	message: (_msg : string, _type? : string) => {},
	webserverUrl: "https://example.com",
	link: (path : string) => "https://example.com" + path,
};

function createFakeApp() : MailApp
{
	return {egw, getCustomLabels: () => ({})} as unknown as MailApp;
}

describe("MailJmap.assembleBodyHtml() - concatenates ALL textBody/htmlBody parts, not just the first", () =>
{
	function assemble(email : Record<string, any>, htmlOptions? : string) : string
	{
		const jmap = new MailJmap(createFakeApp());
		return (jmap as any).assembleBodyHtml(email, htmlOptions);
	}

	it("ticket #128441: concatenates two text/plain fragments split by an inline attachment", () =>
	{
		const html = assemble({
			textBody: [{partId: "1", type: "text/plain"}, {partId: "3", type: "text/plain"}],
			htmlBody: [],
			bodyValues: {"1": {value: ""}, "3": {value: "Von meinem iPad gesendet"}},
		});

		assert.include(html, "Von meinem iPad gesendet",
			"the real text (sent AFTER the inline attachment) must be part of the body");
	});

	it("ticket #128091: concatenates three text/html fragments split by inline attachments, in order", () =>
	{
		const html = assemble({
			textBody: [],
			htmlBody: [
				{partId: "2.1", type: "text/html"},
				{partId: "2.3", type: "text/html"},
				{partId: "2.5", type: "text/html"},
			],
			bodyValues: {
				"2.1": {value: "<p>Hallo,</p>"},
				"2.3": {value: "<p>wird hier unterbrochen</p>"},
				"2.5": {value: "<p>Rest des Textes</p>"},
			},
		});

		assert.include(html, "Hallo,");
		assert.include(html, "wird hier unterbrochen");
		assert.include(html, "Rest des Textes");
		assert.isBelow(html.indexOf("Hallo,"), html.indexOf("wird hier unterbrochen"),
			"fragments must stay in document order");
		assert.isBelow(html.indexOf("wird hier unterbrochen"), html.indexOf("Rest des Textes"));
	});

	it("still shows the single-part case correctly (no regression)", () =>
	{
		const html = assemble({
			textBody: [],
			htmlBody: [{partId: "2", type: "text/html"}],
			bodyValues: {"2": {value: "<p>Simple message</p>"}},
		});

		assert.include(html, "Simple message");
	});

	it("a plain-text-only message (RFC 8621 §4.1.4 fallback-echo into htmlBody) still renders as plain text", () =>
	{
		const html = assemble({
			textBody: [{partId: "1", type: "text/plain"}],
			// Stalwart echoes the text/plain part into htmlBody too, typed text/plain not text/html
			htmlBody: [{partId: "1", type: "text/plain"}],
			bodyValues: {"1": {value: "just plain text"}},
		});

		assert.include(html, "just plain text");
	});
});

function primeWithEmail(jmap : MailJmap, profileID : string, email : any) : void
{
	(jmap as any).tokens[profileID] = {
		sessionUrl: "https://example.com", accountId: "acc1", access_token: "tok",
		expires_at: Date.now() + 100000, isLocal: false, customLabels: {},
	};
	(jmap as any).clients[profileID] = {
		requestMany: async(buildFn : (t : any) => any) =>
		{
			const t = {Email: {get: (_args : any) => ({list: [email]})}};
			return [buildFn(t)];
		},
	};
}

function replyFixture(overrides : Record<string, any>) : any
{
	return {
		from: [], to: [], cc: [], bcc: [], replyTo: null,
		subject: "Original subject", sentAt: "2026-01-01T00:00:00Z",
		messageId: ["msg1@example.com"], references: [],
		htmlBody: [], textBody: [], bodyValues: {}, attachments: [],
		...overrides,
	};
}

describe("MailJmap.fetchForReply() - concatenates ALL textBody/htmlBody parts, not just the first", () =>
{
	it("quotes both text/plain fragments split by an inline attachment (ticket #128441)", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeWithEmail(jmap, "1", replyFixture({
			textBody: [{partId: "1", type: "text/plain"}, {partId: "3", type: "text/plain"}],
			bodyValues: {"1": {value: ""}, "3": {value: "Von meinem iPad gesendet"}},
		}));

		const context = await jmap.fetchForReply("1::1::INBOX::42");

		assert.include(context.body, "Von meinem iPad gesendet");
	});

	it("quotes all three text/html fragments split by inline attachments, in order (ticket #128091)", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeWithEmail(jmap, "1", replyFixture({
			htmlBody: [
				{partId: "2.1", type: "text/html"},
				{partId: "2.3", type: "text/html"},
				{partId: "2.5", type: "text/html"},
			],
			bodyValues: {
				"2.1": {value: "<p>Hallo,</p>"},
				"2.3": {value: "<p>wird hier unterbrochen</p>"},
				"2.5": {value: "<p>Rest des Textes</p>"},
			},
		}));

		const context = await jmap.fetchForReply("1::1::INBOX::42");

		assert.include(context.body, "Hallo,");
		assert.include(context.body, "wird hier unterbrochen");
		assert.include(context.body, "Rest des Textes");
		assert.isBelow(context.body.indexOf("Hallo,"), context.body.indexOf("wird hier unterbrochen"));
	});
});
