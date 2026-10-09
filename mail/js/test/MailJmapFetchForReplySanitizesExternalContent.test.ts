import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Found live 2026-10-09 (ralf): a blocked external url (eg. a tracking pixel) from the original
 * message must not survive into fetchForReply()'s own body - used verbatim by Reply/Reply All/
 * Forward (quoteOriginalMessage()'s <blockquote>) and Compose-as-new/Edit-as-new (copied in
 * directly, not even blockquoted - mail/js/compose.ts). Unlike the message-VIEW path, the compose
 * editor has no CSP of its own: a live external url would be fetched the instant it's rendered
 * into TinyMCE, AND re-sent as part of a brand-new message, reaching the tracker on behalf of a
 * recipient who never received the original mail at all.
 *
 * See MailJmapExternalContentCsp.test.ts's own "sanitizeExternalContentForQuoting()" describe
 * block for unit coverage of the sanitizer itself across every vector (style/<style>/background/
 * poster/srcset/SVG) - this file only has to confirm fetchForReply() actually calls it.
 */

const egw = {
	user : (_key : string) => 1,
	lang : (label : string) => label,
	preference : (_key : string, _app? : string) => null,
	config : (_name : string, _app? : string) => null,
	request : async() => ({}),
	message : (_msg : string, _type? : string) => {},
};

function createFakeApp() : MailApp
{
	return {egw, getCustomLabels : () => ({})} as unknown as MailApp;
}

function primeWithEmail(jmap : MailJmap, profileID : string, email : any) : void
{
	(jmap as any).tokens[profileID] = {
		sessionUrl : "https://example.com", accountId : "acc1", access_token : "tok",
		expires_at : Date.now() + 100000, isLocal : false, customLabels : {},
	};
	(jmap as any).clients[profileID] = {
		requestMany : async(buildFn : (t : any) => any) =>
		{
			const t = {Email : {get : (_args : any) => ({list : [email]})}};
			return [buildFn(t)];
		},
	};
}

function fixtureEmail(htmlBody : string) : any
{
	return {
		from : [], to : [], cc : [], bcc : [], replyTo : null,
		subject : "Original subject", sentAt : "2026-01-01T00:00:00Z",
		messageId : ["msg1@example.com"], references : [],
		htmlBody : [{partId : "p1", type : "text/html"}],
		bodyValues : {p1 : {value : htmlBody}},
		attachments : [],
	};
}

describe("MailJmap.fetchForReply() sanitizes external content", () =>
{
	it("strips a tracking pixel's <img> from the quoted body", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeWithEmail(jmap, "1", fixtureEmail(
			'<p>hi there</p><img src="https://tracker.example.com/pixel.gif?recipient=victim">'));

		const context = await jmap.fetchForReply("1::1::INBOX::42");

		assert.notInclude(context.body, "tracker.example.com");
		assert.notInclude(context.body, "recipient=victim");
		assert.include(context.body, "hi there");
	});

	it("strips a tracking CSS background from the quoted body, keeping the rest of the style", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeWithEmail(jmap, "1", fixtureEmail(
			'<div style="background:url(https://tracker.example.com/bg.gif); color: red">hi</div>'));

		const context = await jmap.fetchForReply("1::1::INBOX::42");

		assert.notInclude(context.body, "tracker.example.com");
		assert.include(context.body, "color: red");
	});

	it("never touches a cid: inline image - resolveInlineCidImages() still resolves it afterward", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeWithEmail(jmap, "1", fixtureEmail('<img src="cid:logo@example.com">'));
		(jmap as any).resolveInlineCidImages = async(html : string) => html; // stub, not under test here

		const context = await jmap.fetchForReply("1::1::INBOX::42");

		assert.include(context.body, 'src="cid:logo@example.com"');
	});
});
