import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
// both of these have to come before "../app" - see MailAppImportStub's own docblock
import "./MailAppImportStub";
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import {MailApp} from "../app";
import {etemplate2} from "../../../api/js/etemplate/etemplate2";

/**
 * Ticket #126271 follow-up (2026-10-14, confirmed live by bb@/ik@egroupware.org - Edge
 * specifically, not Chrome/Firefox): clicking a non-viewable attachment's own filename link used
 * to fall through to Et2Description's own egw.open_link(href, '_browser', ...) ->
 * window.open(url, '_blank') against the classic download URL - Edge alone leaves a permanently
 * blank extra tab behind, alongside the download's own tab (which closes itself normally
 * everywhere). resolveAttachmentViewUrls() (own test file) now clears that row's own mime_url so
 * window.open() is never reached at all; this method restores "click the filename to download"
 * via the exact same fetch()+<a download>/JMAP-blob mechanism the toolbar's own "Download" action
 * already uses safely (downloadOneAttachment(), extracted from saveAttachmentHandler()'s own
 * 'downloadOneAsFile' case for this reuse).
 */

const ROW_ID = 'mail::1::2::SU5CT1gvdGVzdDM=::42';

function createFakeWidget(id : string, mime : string, overrides : Record<string, any> = {}, parentData : any = null)
{
	return {
		id, mime, href: '', mimeData: '',
		getParent: () => parentData === null ? null : {getArrayMgr: (_name : string) => ({data: parentData})},
		...overrides,
	};
}

function createMailApp(overrides : Record<string, any> = {})
{
	const egw : any = {
		webserverUrl: '/egroupware',
		message: sinon.stub(),
	};
	const jmap : any = {
		messageReference: sinon.stub().returns({profileID: '1'}),
		downloadAttachment: sinon.stub().resolves(),
	};
	const app = Object.create(MailApp.prototype) as MailApp;
	Object.defineProperty(app, 'egw', {value: egw});
	Object.defineProperty(app, 'jmap', {value: jmap});
	Object.assign(app, overrides);
	return {app, egw, jmap};
}

function fakeEvent()
{
	return {preventDefault: sinon.spy()} as unknown as MouseEvent;
}

describe("MailApp.attachmentFilenameClick()", () =>
{
	it("does nothing (returns true, no preventDefault) for a viewable type", () =>
	{
		const {app} = createMailApp();
		const ev = fakeEvent();
		const widget = createFakeWidget('row1[filename]', 'image/png');

		const result = (app as any).attachmentFilenameClick(ev, widget);

		assert.isTrue(result);
		assert.isFalse((ev.preventDefault as any).called);
	});

	it("does nothing for the message/rfc822 popup type, even without a blobId", () =>
	{
		const {app} = createMailApp();
		const widget = createFakeWidget('row1[filename]', 'message/rfc822');

		const result = (app as any).attachmentFilenameClick(fakeEvent(), widget);

		assert.isTrue(result);
	});

	it("does nothing when a mime_data handler is already registered (eg. Collabora)", () =>
	{
		const {app} = createMailApp();
		const widget = createFakeWidget('row1[filename]', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			{mimeData: 'abc123'});

		const result = (app as any).attachmentFilenameClick(fakeEvent(), widget);

		assert.isTrue(result);
	});

	it("downloads via the JMAP blob path for a non-viewable attachment with a blobId", () =>
	{
		const {app, jmap} = createMailApp();
		const attachment = {filename: 'Report.xlsx', type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			blobId: 'blob1', mail_id: ROW_ID};
		const widget = createFakeWidget('row1[filename]', attachment.type, {}, {row1: attachment});
		const ev = fakeEvent();

		const result = (app as any).attachmentFilenameClick(ev, widget);

		assert.isFalse(result);
		assert.isTrue((ev.preventDefault as any).called);
		assert.isTrue(jmap.downloadAttachment.calledOnceWith('1', 'blob1', 'Report.xlsx', attachment.type));
	});

	it("falls back to the classic fetch()+<a download> path when there is no blobId", () =>
	{
		const download = sinon.stub(etemplate2.prototype, 'download');
		try
		{
			const {app} = createMailApp();
			const attachment = {filename: 'Report.xlsx', type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				mail_id: ROW_ID, partID: '2', winmailFlag: ''};
			const widget = createFakeWidget('row1[filename]', attachment.type, {}, {row1: attachment});

			const result = (app as any).attachmentFilenameClick(fakeEvent(), widget);

			assert.isFalse(result);
			assert.isTrue(download.calledOnce);
			assert.include(download.firstCall.args[0], 'mode=save');
			assert.include(download.firstCall.args[0], 'Ui.getAttachment');
		}
		finally
		{
			download.restore();
		}
	});

	/**
	 * The single-attachment summary near the header (mail/templates/default/{index,display}.xet's
	 * own hardcoded "attachmentsBlock[0][filename]", not inside a repeating grid row) resolves
	 * against the template's TOP-LEVEL content instead of a grid row's own scoped sub-array - this
	 * same literal id string is the NESTED path "attachmentsBlock[0]" there, not a flat key.
	 */
	it("resolves the single-attachment summary's own nested 'attachmentsBlock[0]' id shape", () =>
	{
		const {app, jmap} = createMailApp();
		const attachment = {filename: 'Report.xlsx', type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			blobId: 'blob1', mail_id: ROW_ID};
		const widget = createFakeWidget('attachmentsBlock[0][filename]', attachment.type, {},
			{attachmentsBlock: [attachment]});

		const result = (app as any).attachmentFilenameClick(fakeEvent(), widget);

		assert.isFalse(result);
		assert.isTrue(jmap.downloadAttachment.calledOnceWith('1', 'blob1', 'Report.xlsx', attachment.type));
	});

	it("fails gracefully (no throw, just a console error) when the row's own data can't be resolved at all", () =>
	{
		const consoleError = sinon.stub(console, 'error');
		try
		{
			const {app, jmap} = createMailApp();
			const widget = createFakeWidget('row1[filename]', 'application/zip', {}, {});

			const result = (app as any).attachmentFilenameClick(fakeEvent(), widget);

			assert.isFalse(result);
			assert.isFalse(jmap.downloadAttachment.called);
			assert.isTrue(consoleError.called);
		}
		finally
		{
			consoleError.restore();
		}
	});
});
