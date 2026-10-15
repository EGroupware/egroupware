import {assert} from "@open-wc/testing";
// both of these have to come before "../app" - see MailAppImportStub's own docblock
import "./MailAppImportStub";
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import {MailApp} from "../app";

/**
 * Coverage for MailApp.resolveAttachmentViewUrls() - doc/ai/projects/mail-test-coverage.md's
 * priority-4 entry. Genuinely untested until now: replaces an attachment row's server mime_url
 * with a client-side JMAP `blob:` object URL for the "click to view" path, skipping the classic
 * server round-trip - a broken exclusion filter could route a message/rfc822 sub-message or a
 * vCard/iCalendar import (both need real server-side handling, not a blob render) through the
 * wrong path.
 */

const ROW_ID = 'mail::1::2::SU5CT1gvdGVzdDM=::42';

function createMailApp()
{
	const app = Object.create(MailApp.prototype) as MailApp;
	const revoked : string[] = [];
	const requested : {rowId : string, profileID : string, blobId : string, filename : string, type : string}[] = [];
	let responder : (item : any) => Promise<string> = (item) => Promise.resolve(`blob:${item.blobId}`);

	Object.defineProperty(app, 'jmap', {value: {
		messageReference: (id : string) => ({profileID: '1'}),
		revokeAttachmentViewUrls: (rowId : string) => void revoked.push(rowId),
		getAttachmentViewUrl: (rowId : string, profileID : string, blobId : string, filename : string, type : string) =>
		{
			requested.push({rowId, profileID, blobId, filename, type});
			return responder({blobId, filename, type});
		},
	}});

	return {
		app, revoked, requested,
		setResponder(fn : (item : any) => Promise<string>) { responder = fn; },
	};
}

/** A jmap whose messageReference() throws, matching a non-JMAP (classic-only) row id. */
function createNonJmapMailApp()
{
	const app = Object.create(MailApp.prototype) as MailApp;
	let revokeCalled = false;
	let getCalled = false;
	Object.defineProperty(app, 'jmap', {value: {
		messageReference: () => { throw new Error('not a JMAP row'); },
		revokeAttachmentViewUrls: () => { revokeCalled = true; },
		getAttachmentViewUrl: () => { getCalled = true; return Promise.resolve(''); },
	}});
	return {app, calledAtAll: () => revokeCalled || getCalled};
}

function invoke(app : MailApp, rowId : string, attachmentsBlock : any[]) : Promise<boolean>
{
	return (app as any).resolveAttachmentViewUrls(rowId, attachmentsBlock);
}

describe("MailApp.resolveAttachmentViewUrls()", () =>
{
	it("returns false and never touches jmap for a non-JMAP row id", async() =>
	{
		const {app, calledAtAll} = createNonJmapMailApp();

		const result = await invoke(app, ROW_ID, [{blobId: 'b1', filename: 'a.txt', type: 'text/plain'}]);

		assert.isFalse(result);
		assert.isFalse(calledAtAll());
	});

	it("returns false and never calls revokeAttachmentViewUrls when there are no eligible attachments", async() =>
	{
		const {app, revoked, requested} = createMailApp();

		const result = await invoke(app, ROW_ID, []);

		assert.isFalse(result);
		assert.isEmpty(revoked);
		assert.isEmpty(requested);
	});

	it("skips items without a blobId, even when their type isn't excluded", async() =>
	{
		const {app, requested} = createMailApp();

		const result = await invoke(app, ROW_ID, [{filename: 'a.txt', type: 'text/plain'}]);

		assert.isFalse(result);
		assert.isEmpty(requested);
	});

	it("excludes message/rfc822, vCard and iCalendar attachments case-insensitively, even with a blobId", async() =>
	{
		const {app, requested} = createMailApp();
		const excludedItems = [
			{blobId: 'b1', filename: 'fwd.eml', type: 'message/rfc822'},
			{blobId: 'b2', filename: 'contact.vcf', type: 'text/vCard'},
			{blobId: 'b3', filename: 'contact.vcf', type: 'TEXT/X-VCARD'},
			{blobId: 'b4', filename: 'event.ics', type: 'text/calendar'},
			{blobId: 'b5', filename: 'event.ics', type: 'text/x-vcalendar'},
		];

		const result = await invoke(app, ROW_ID, excludedItems);

		assert.isFalse(result);
		assert.isEmpty(requested, "none of the excluded types must ever be resolved client-side");
	});

	it("revokes any previously-created view URLs for this row before resolving new ones", async() =>
	{
		const {app, revoked} = createMailApp();

		await invoke(app, ROW_ID, [{blobId: 'b1', filename: 'a.txt', type: 'text/plain'}]);

		assert.deepEqual(revoked, [ROW_ID]);
	});

	it("leaves other application/* types (eg. Excel) on the classic server mime_url, even with a blobId", async() =>
	{
		// Tracker #124932 (real customer, acc_id=42): clicking an .xlsx attachment's name
		// downloaded it as a random blob-UUID filename instead of its real name - same bug class
		// as tracker #124541. Leaving these on the classic mail_ui::getAttachment() URL
		// (Api\Header\Content::safe() sets a real Content-Disposition header there) sidesteps the
		// blob:-URL-filename problem entirely.
		const {app, requested} = createMailApp();
		const items = [
			{blobId: 'b1', filename: 'Report.xlsx', type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'},
			{blobId: 'b2', filename: 'archive.zip', type: 'application/zip'},
		];

		const result = await invoke(app, ROW_ID, items);

		assert.isFalse(result);
		assert.isUndefined(items[0].mime_url, "xlsx must not get a client-side blob mime_url");
		assert.isUndefined(items[1].mime_url, "zip must not get a client-side blob mime_url");
		assert.isEmpty(requested);
	});

	it("leaves PDF on the classic server mime_url too (ticket #125641) - the browser's own native " +
		"viewer needs the real server URL for fillable forms and working print, not a client-side blob", async() =>
	{
		const {app, requested} = createMailApp();

		const result = await invoke(app, ROW_ID, [{blobId: 'b1', filename: 'doc.pdf', type: 'application/pdf'}]);

		assert.isFalse(result);
		assert.isEmpty(requested);
	});

	it("leaves text/csv on the classic server mime_url too - many browsers download it rather than view it inline", async() =>
	{
		// Follow-up found live 2026-09-22: a first attempt at this fix used
		// `!type.startsWith('application/')`, which wrongly still routed text/csv through the
		// client-side blob: URL (a second real attachment, same account, still got a blob-UUID
		// filename after the first fix). Now an enumerated whitelist instead of a wildcard.
		const {app, requested} = createMailApp();

		const result = await invoke(app, ROW_ID, [{blobId: 'b1', filename: 'delivery.csv', type: 'text/csv'}]);

		assert.isFalse(result);
		assert.isEmpty(requested);
	});

	it("resolves each eligible attachment and writes its own mime_url in place", async() =>
	{
		const {app, requested} = createMailApp();
		const items = [
			{blobId: 'b1', filename: 'a.txt', type: 'text/plain'},
			{blobId: 'b2', filename: 'b.png', type: 'image/png'},
		];

		const result = await invoke(app, ROW_ID, items);

		assert.isTrue(result);
		assert.equal(items[0].mime_url, 'blob:b1');
		assert.equal(items[1].mime_url, 'blob:b2');
		assert.equal(requested.length, 2);
		assert.equal(requested[0].profileID, '1');
	});

	it("one attachment's failure doesn't block the others from resolving, and still reports overall success", async() =>
	{
		const {app, setResponder} = createMailApp();
		setResponder((item) => item.blobId === 'bad'
			? Promise.reject(new Error('download failed'))
			: Promise.resolve(`blob:${item.blobId}`));
		const items = [
			{blobId: 'bad', filename: 'broken.txt', type: 'text/plain'},
			{blobId: 'good', filename: 'ok.txt', type: 'text/plain'},
		];

		const result = await invoke(app, ROW_ID, items);

		assert.isTrue(result);
		assert.isUndefined(items[0].mime_url, "the failed item's mime_url must be left untouched");
		assert.equal(items[1].mime_url, 'blob:good');
	});

	it("reports overall failure (without throwing) when every attachment fails to resolve", async() =>
	{
		const {app, setResponder} = createMailApp();
		setResponder(() => Promise.reject(new Error('download failed')));

		const result = await invoke(app, ROW_ID, [{blobId: 'b1', filename: 'a.txt', type: 'text/plain'}]);

		assert.isFalse(result);
	});
});

/**
 * Ticket #126271 follow-up (2026-10-14, confirmed live by bb@/ik@egroupware.org - Edge
 * specifically): clicking a non-viewable attachment's own filename link used to fall through to
 * Et2Description's own window.open(url, '_blank') against the classic download URL, leaving a
 * permanently blank extra tab behind in Edge. Clearing the row's own mime_url here (so that
 * fallback becomes a no-op regardless of browser - window.open() is never reached at all any
 * more) is the actual fix; attachmentFilenameClick() (own test file) restores equivalent
 * "click the filename to download" behavior via a safe fetch()+<a download> mechanism instead.
 */
describe("MailApp.resolveAttachmentViewUrls() - non-viewable mime_url suppression (ticket #126271)", () =>
{
	it("clears mime_url for a non-viewable, non-excluded type with no mime_data handler", async() =>
	{
		const {app} = createMailApp();
		const items = [
			{filename: 'Report.xlsx', type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				mime_url: '/index.php?menuaction=mail.EGroupware%5CMail%5CUi.getAttachment&...'},
		];

		const result = await invoke(app, ROW_ID, items);

		assert.isTrue(result);
		assert.strictEqual(items[0].mime_url, '');
	});

	it("leaves mime_url untouched when a mime_data handler is already registered (eg. Collabora)", async() =>
	{
		const {app} = createMailApp();
		const items = [
			{filename: 'Report.xlsx', type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				mime_url: 'https://example/getAttachment', mime_data: 'abc123'},
		];

		const result = await invoke(app, ROW_ID, items);

		assert.isFalse(result);
		assert.equal(items[0].mime_url, 'https://example/getAttachment');
	});

	it("leaves mime_url untouched for the message/rfc822/vCard/iCalendar popup types", async() =>
	{
		const {app} = createMailApp();
		const items = [
			{filename: 'fwd.eml', type: 'message/rfc822', mime_url: 'https://example/displayMessage'},
			{filename: 'event.ics', type: 'text/calendar', mime_url: 'https://example/viewEvent'},
		];

		const result = await invoke(app, ROW_ID, items);

		assert.isFalse(result);
		assert.equal(items[0].mime_url, 'https://example/displayMessage');
		assert.equal(items[1].mime_url, 'https://example/viewEvent');
	});

	/**
	 * Ticket #126541 (bb@egroupware.org, 2026-10-15 - Chrome/Brave/Edge, not Firefox): PDF
	 * attachments were being force-downloaded instead of opened for viewing. This suppression
	 * check (added a day after #125641's own "PDF deliberately not in VIEWABLE_TYPES" decision)
	 * reused that same set to mean "no real view url of its own, safe to clear" - true for every
	 * type it was written against, but not for PDF's own, different reason for being absent (see
	 * VIEWABLE_TYPES' own docblock and `excluded`'s own comment in resolveAttachmentViewUrls()).
	 */
	it("leaves mime_url untouched for PDF (ticket #126541 - was wrongly suppressed, forcing a download)", async() =>
	{
		const {app} = createMailApp();
		const items = [
			{filename : 'doc.pdf', type : 'application/pdf', mime_url : 'https://example/getAttachment?...'},
		];

		const result = await invoke(app, ROW_ID, items);

		assert.isFalse(result);
		assert.equal(items[0].mime_url, 'https://example/getAttachment?...');
	});

	it("never touches an already-empty mime_url (nothing to suppress)", async() =>
	{
		const {app} = createMailApp();
		const items = [{filename: 'Report.xlsx', type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'}];

		const result = await invoke(app, ROW_ID, items);

		assert.isFalse(result);
		assert.isUndefined(items[0].mime_url);
	});
});
