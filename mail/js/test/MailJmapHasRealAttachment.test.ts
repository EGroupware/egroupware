import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";

/**
 * Ticket #128441: MailJmap.hasRealAttachment() is the shared "is this message's attachments list
 * (RFC 8621 §4.1.4 Email.attachments) actually worth showing a paperclip for" test - same filter
 * fetchForReply()'s own forward-attachment-list building already applied (isRealAttachmentPart()),
 * now also used to detect a real attachment Stalwart's own hasAttachment flag missed (see that
 * method's own docblock for the full root-cause writeup).
 */
describe("MailJmap.hasRealAttachment() (ticket #128441)", () =>
{
	it("true for an inline-disposition PDF with no Content-ID (the ticket's own Apple/iPad case)", () =>
	{
		assert.isTrue(MailJmap.hasRealAttachment([
			{partId : '2', type : 'application/pdf', name : 'Dienstreiseantrag.pdf', disposition : 'inline', size : 905},
		]));
	});

	it("false for a cid-referenced inline image (a signature logo, not a real attachment)", () =>
	{
		assert.isFalse(MailJmap.hasRealAttachment([
			{partId : '2', type : 'image/png', name : 'logo.png', disposition : 'inline', cid : '<logo@example.org>', size : 2000},
		]));
	});

	it("true for a cid-carrying part explicitly marked disposition=attachment", () =>
	{
		assert.isTrue(MailJmap.hasRealAttachment([
			{partId : '2', type : 'image/png', name : 'logo.png', disposition : 'attachment', cid : '<logo@example.org>', size : 2000},
		]));
	});

	it("false for a nameless, tiny part (Apple Mail's own multipart-boundary padding artifact)", () =>
	{
		assert.isFalse(MailJmap.hasRealAttachment([
			{partId : '2', type : 'text/plain', name : '', disposition : null, size : 2},
		]));
	});

	it("true for a nameless but substantial part - not every nameless part is boundary padding", () =>
	{
		assert.isTrue(MailJmap.hasRealAttachment([
			{partId : '2', type : 'application/octet-stream', name : '', disposition : 'attachment', size : 5000},
		]));
	});

	it("false for an empty or missing attachments list", () =>
	{
		assert.isFalse(MailJmap.hasRealAttachment([]));
		assert.isFalse(MailJmap.hasRealAttachment(undefined));
	});

	it("true as soon as ONE part among several qualifies", () =>
	{
		assert.isTrue(MailJmap.hasRealAttachment([
			{partId : '2', type : 'image/png', name : 'logo.png', disposition : 'inline', cid : '<logo@example.org>', size : 2000},
			{partId : '3', type : 'application/pdf', name : 'invoice.pdf', disposition : 'inline', size : 1200},
		]));
	});
});
