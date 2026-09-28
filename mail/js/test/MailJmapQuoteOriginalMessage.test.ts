import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";
import type {JmapReplyContext} from "../jmap";

/**
 * Ticket #125251 ("Beim Weiterleiten gehen Formatierung und Zeilenumbrüche verloren" - a real
 * customer forwarding a plain-text fax-notification email): quoteOriginalMessage() used to decide
 * its OWN output shape purely from `context.mimeType` (the ORIGINAL message's format) - correct
 * as long as the compose ended up in that same mode, which used to be guaranteed. Ticket #124821
 * later taught MailCompose.bootstrapReply() to let the `replyOptions` preference ("force html"/
 * "force text") pick a DIFFERENT mode than the original message's own mimeType, but never told
 * quoteOriginalMessage() about it - a plain-text original quoted under "force html" came back as
 * raw plain text (literal '\r\n' newlines, no markup) inserted straight into an HTML/TinyMCE
 * editor, which collapses bare whitespace exactly like any other HTML content: every line ran
 * together into one paragraph - "Zeilenumbrüche gehen verloren". Fixed by passing the compose's
 * actual target mode in explicitly and converting context.body to match it, in EITHER direction.
 */

const egw = {
	lang : (label : string) => label,
	htmlspecialchars : (s : string) => s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;"),
};

function createFakeApp() : MailApp
{
	return {egw} as unknown as MailApp;
}

function baseContext(overrides : Partial<JmapReplyContext> = {}) : JmapReplyContext
{
	return {
		from : [{name : "Sender", email : "sender@example.org"}],
		to : [],
		cc : [],
		bcc : [],
		replyTo : null,
		subject : "Test",
		date : "",
		mimeType : "plain",
		body : "line one\nline two",
		profileID : "1",
		inReplyTo : null,
		references : null,
		attachments : [],
		threadTopic : null,
		threadIndex : null,
		listId : null,
		autocrypt : null,
		...overrides
	} as JmapReplyContext;
}

describe("MailJmap.quoteOriginalMessage() - target mode may differ from the original's own mimeType", () =>
{
	it("plain original, plain target (the ordinary case) - '> '-quotes each line unchanged", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const result = jmap.quoteOriginalMessage(baseContext(), false);

		assert.include(result, "> line one");
		assert.include(result, "> line two");
	});

	it("html original, html target (the ordinary case) - body passed through unchanged", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const context = baseContext({mimeType : "html", body : "<p>line one</p><p>line two</p>"});
		const result = jmap.quoteOriginalMessage(context, true);

		assert.include(result, "<blockquote type=\"cite\"><p>line one</p><p>line two</p></blockquote>");
	});

	it("plain original, HTML target (ticket #125251) - newlines become <br>, not left literal", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const result = jmap.quoteOriginalMessage(baseContext(), true);

		assert.include(result, "line one<br>\nline two");
		assert.notInclude(result, "\n\nline two", "a bare newline here would collapse in an HTML editor");
	});

	it("plain original, HTML target - escapes HTML-special characters in the body", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const context = baseContext({body : "<script>alert(1)</script>"});
		const result = jmap.quoteOriginalMessage(context, true);

		assert.notInclude(result, "<script>alert(1)</script>");
		assert.include(result, "&lt;script&gt;");
	});

	it("html original, plain target (the mirror case) - tags are converted to plain text, not left literal", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const context = baseContext({mimeType : "html", body : "<p>line one</p><p>line two</p>"});
		const result = jmap.quoteOriginalMessage(context, false);

		assert.notInclude(result, "<p>");
		assert.include(result, "> line one");
		assert.include(result, "> line two");
	});

	it("defaults targetIsHtml from context.mimeType when the caller doesn't pass it (back-compat)", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const htmlContext = baseContext({mimeType : "html", body : "<p>hi</p>"});

		assert.include(jmap.quoteOriginalMessage(htmlContext), "<blockquote");
		assert.include(jmap.quoteOriginalMessage(baseContext()), "> line one");
	});
});
