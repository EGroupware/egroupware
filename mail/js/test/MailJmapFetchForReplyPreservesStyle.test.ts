import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Ticket #126181 (Birgit, relaying a customer): an ERP-generated report email's own `.datagrid
 * table` CSS (borders, header background colour, zebra-striped rows) vanished not just from the
 * message VIEW (assembleBodyHtml(), covered by MailJmap.extractStyles()'s own unit tests) but from
 * a REPLY's quoted copy too - found live by ralf opening "Quellcode" on a reply's compose source
 * and seeing the quoted `<div class="datagrid">...` with no preceding `<style>` block at all, even
 * after the view-side fix was already confirmed working for the same message.
 *
 * Root cause: fetchForReply() ran the raw body straight through DOMPurify.sanitize() before
 * MailJmap.extractStyles() existed to protect it, same as assembleBodyHtml() did - confirmed live
 * (in a real Chrome) that DOMPurify strips every `<style>` element unconditionally, even with
 * `ADD_TAGS: ['style']`.
 */

const egw = {
	user : (_key : string) => 1,
	lang : (label : string) => label,
	preference : (_key : string, _app? : string) => null,
	config : (_name : string, _app? : string) => null,
	request : async() => ({}),
	message : (_msg : string, _type? : string) => {},
	webserverUrl : "https://example.com",
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

function htmlFixture(html : string) : any
{
	return {
		from : [], to : [], cc : [], bcc : [], replyTo : null,
		subject : "Original subject", sentAt : "2026-01-01T00:00:00Z",
		messageId : ["msg1@example.com"], references : [],
		htmlBody : [{partId : "p1", type : "text/html"}],
		textBody : [],
		bodyValues : {p1 : {value : html}},
		attachments : [],
	};
}

describe("MailJmap.fetchForReply() - preserves the original message's own <style> block", () =>
{
	it("keeps a <style> block's CSS intact in the quoted body", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeWithEmail(jmap, "1", htmlFixture(
			'<style>.datagrid table {border: 1px solid #8A0028;}</style>' +
			'<div class="datagrid"><table><tr><td>row</td></tr></table></div>'));

		const context = await jmap.fetchForReply("1::1::INBOX::42");

		assert.include(context.body, "<style>");
		assert.include(context.body, ".datagrid table");
		assert.include(context.body, "border: 1px solid #8A0028");
		assert.include(context.body, '<div class="datagrid">', "the rest of the body must still be there too");
	});

	it("leaves a body with no <style> block completely unaffected", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeWithEmail(jmap, "1", htmlFixture("<p>plain html, no style</p>"));

		const context = await jmap.fetchForReply("1::1::INBOX::42");

		assert.notInclude(context.body, "<style");
		assert.include(context.body, "plain html, no style");
	});
});
