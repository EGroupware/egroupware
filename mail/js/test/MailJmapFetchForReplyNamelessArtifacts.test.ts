import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Regression coverage for a real report (ralf, 2026-09-15): forwarding
 * https://boulder.egroupware.org/egroupware/mail/compose.php?from=forward&id=mail::5::42::SU5CT1g=::185020
 * showed "2 leere Anhänge" (2 empty attachments) in the compose attachment list. The original
 * message (an Apple Mail one pasting 3 inline images) carries 2 extra `text/plain` parts with NO
 * filename and only 2 bytes of content each - Apple Mail's own multipart-boundary padding
 * artifact (a stray blank line dropped between the pasted images), not real attachments at all.
 *
 * MailJmap.fetchForReply()'s attachment mapper (used for both reply AND forward, incl.
 * mode=forwardinline) just carried `name: a.name || ''` straight through with no fallback -
 * unlike the message-VIEW path (AttachmentJmap::jmapAttachmentsToLegacy(), mail/src/Ui/
 * AttachmentJmap.php) which synthesizes a "Unbekannt_PartN.ext" name for the exact same kind of
 * nameless part. Two fixes here: a part that is BOTH nameless AND this tiny is simply dropped
 * (ralf: "we probably want to ignore all 2-byte artifacts" - a genuine attachment is never both
 * nameless and a couple bytes long, so this can't false-positive on real content); a nameless
 * part that ISN'T tiny (rare, but real) gets the SAME "Unbekannt_PartN" fallback name the display
 * view already uses (ralf: "attachments without name in preview/display get a name 'Unknown...',
 * while in the compose/forward they had an empty name, that's inconsistent"), so the two views of
 * the same message stay consistent.
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

function primeToken(jmap : MailJmap, profileID : string, client : any) : void
{
	(jmap as any).tokens[profileID] = {
		sessionUrl : "https://example.com", accountId : "acc1", access_token : "tok",
		expires_at : Date.now() + 100000, isLocal : false, customLabels : {},
	};
	(jmap as any).clients[profileID] = client;
}

/** Plain-text-only fixture - deliberately no htmlBody, so useHtml stays false and no inline-image resolution is ever triggered. */
function fixtureEmail(overrides : Record<string, any> = {}) : any
{
	return {
		from : [], to : [], cc : [], bcc : [], replyTo : null,
		subject : "Original subject", sentAt : "2026-01-01T00:00:00Z",
		messageId : ["msg1@example.com"], references : [],
		textBody : [{partId : "p1", type : "text/plain"}],
		bodyValues : {p1 : {value : "the original body"}},
		attachments : [],
		...overrides,
	};
}

function primeWithEmail(jmap : MailJmap, profileID : string, email : any) : void
{
	primeToken(jmap, profileID, {
		requestMany : async(buildFn : (t : any) => any) =>
		{
			const t = {Email : {get : (_args : any) => ({list : [email]})}};
			return [buildFn(t)];
		},
	});
}

describe("MailJmap.fetchForReply() - drops nameless boundary-artifact parts", () =>
{
	it("drops a nameless 2-byte text/plain part alongside real named attachments", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeWithEmail(jmap, "1", fixtureEmail({
			attachments : [
				{blobId : "b1", partId : "2", name : "PastedGraphic-2.png", type : "image/png", size : 105379},
				{blobId : "b2", partId : "3", name : "", type : "text/plain", size : 2},
				{blobId : "b3", partId : "4", name : "PastedGraphic-1.png", type : "image/png", size : 75273},
				{blobId : "b4", partId : "5", name : "", type : "text/plain", size : 2},
				{blobId : "b5", partId : "6", name : "PastedGraphic-3.png", type : "image/png", size : 70213},
			],
		}));

		const context = await jmap.fetchForReply("1::1::INBOX::42");

		assert.deepEqual(context.attachments.map((a) => a.name),
			["PastedGraphic-2.png", "PastedGraphic-1.png", "PastedGraphic-3.png"],
			"the 2 nameless boundary-padding parts must not show up as blank attachment rows");
	});

	it("keeps a nameless part that is NOT tiny, giving it the same 'unknown_PartN' fallback name the display view uses", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeWithEmail(jmap, "1", fixtureEmail({
			attachments : [
				{blobId : "b1", partId : "7", name : "", type : "application/pdf", size : 5000},
			],
		}));

		const context = await jmap.fetchForReply("1::1::INBOX::42");

		assert.lengthOf(context.attachments, 1, "a large nameless part is real content, not boundary noise");
		assert.equal(context.attachments[0].size, 5000);
		assert.equal(context.attachments[0].name, "unknown_Part7.pdf",
			"must not be blank - matches AttachmentJmap::jmapAttachmentsToLegacy()'s own naming for the same case");
	});

	it("keeps a real attachment even when it happens to be small, as long as it has a name", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeWithEmail(jmap, "1", fixtureEmail({
			attachments : [
				{blobId : "b1", partId : "2", name : "tiny.txt", type : "text/plain", size : 2},
			],
		}));

		const context = await jmap.fetchForReply("1::1::INBOX::42");

		assert.lengthOf(context.attachments, 1, "a named attachment is never dropped, regardless of size");
		assert.equal(context.attachments[0].name, "tiny.txt");
	});
});
