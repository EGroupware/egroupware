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
