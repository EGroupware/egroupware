import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
// both of these have to come before "../app" - see MailAppImportStub's own docblock
import "./MailAppImportStub";
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import {MailApp} from "../app";

/**
 * Ticket #126161: a mail with a winmail.dat (Outlook TNEF) showed its unpacked files in the preview, but the display
 * popup (and an .eml opened from a ticket) showed just "winmail.dat" and the download of the unpacked PDF was empty.
 *
 * The popup's attachmentsBlock is already filled (from the row cache or the server) when renderMessageInto() gets it,
 * and the raw winmail.dat of the JMAP code has no "winmailFlag", which the winmail.dat resolution required. Now
 * it is recognized by its type or name, and replaced by the unpacked files, keeping other attachments. The RTF copy of
 * the mail body, which is part of every winmail.dat, is no attachment.
 */
function createMailApp(request : sinon.SinonStub)
{
	const app = Object.create(MailApp.prototype) as MailApp;
	Object.assign(app, {
		egw : {request, preference : () => 'onlyname'},
		et2 : {getWidgetById : () => undefined},
		setupViewAttachmentActions : sinon.spy(),
		resolveAttachmentViewUrls : sinon.stub().resolves(false),
	});
	return app;
}

const tick = () => new Promise((resolve) => setTimeout(resolve, 0));

describe("MailApp.renderMessageInto() - winmail.dat in the display popup", () =>
{
	let dataStoreUID : sinon.SinonSpy;
	const rowId = 'mail::5::INBOX::12750';
	const rtf = {filename : 'Untitled.rtf', type : 'application/rtf', partID : '2', winmailFlag : '12750@2@0', attachment_number : 0};
	const pdf = {filename : 'Rechnung 174977.pdf', type : 'application/pdf', partID : '2', winmailFlag : '12750@2@1', attachment_number : 1};

	beforeEach(() =>
	{
		dataStoreUID = sinon.spy();
		(<any>window).egw = {dataStoreUID, dataGetUIDdata : () => ({data : {}}), preference : () => 'onlyname'};
	});

	it("resolves a JMAP-native winmail.dat WITHOUT winmailFlag, asking with its partID and blobId", async() =>
	{
		const request = sinon.stub().resolves([rtf, pdf]);
		const template = {set_value : sinon.spy()};
		const data : any = {uid : rowId, attachmentsBlock : [{filename : 'winmail.dat', type : 'application/ms-tnef',
			mimetype : 'MS Tnef', partID : '2', blobId : 'blob-1', winmailFlag : null, attachment_number : 0}]};

		(<any>createMailApp(request)).renderMessageInto(template, rowId, data);
		await tick();

		assert.isTrue(request.calledOnceWith('mail.EGroupware\\Mail\\Ui.ajax_resolveWinmail', [rowId, '2', 'blob-1']));
		const block = template.set_value.lastCall.args[0].content.attachmentsBlock;
		assert.deepEqual(block.map((a : any) => a.filename), ['Rechnung 174977.pdf'], 'the RTF copy of the body is not an attachment');
		assert.equal(block[0].attachment_number, 0);
		assert.isTrue(dataStoreUID.calledOnce, 'cached to not resolve it again');
	});

	it("keeps the other attachments around a JMAP-native winmail.dat (which returns only the unpacked files)", async() =>
	{
		const request = sinon.stub().resolves([rtf, pdf]);
		const template = {set_value : sinon.spy()};
		const data : any = {uid : rowId, attachmentsBlock : [
			{filename : 'first.png', type : 'image/png', attachment_number : 0},
			{filename : 'winmail.dat', type : 'application/ms-tnef', partID : '3', blobId : 'blob-1', attachment_number : 1},
			{filename : 'last.txt', type : 'text/plain', attachment_number : 2},
		]};

		(<any>createMailApp(request)).renderMessageInto(template, rowId, data);
		await tick();

		const block = template.set_value.lastCall.args[0].content.attachmentsBlock;
		assert.deepEqual(block.map((a : any) => a.filename), ['first.png', 'Rechnung 174977.pdf', 'last.txt']);
		assert.deepEqual(block.map((a : any) => a.attachment_number), [0, 1, 2]);
	});

	it("sets up the download/view actions and the pdf.js viewer URLs for the unpacked files, as for any attachment", async() =>
	{
		const request = sinon.stub().resolves([rtf, pdf]);
		const template = {set_value : sinon.spy()};
		const app : any = createMailApp(request);
		const data : any = {uid : rowId, attachmentsBlock : [{filename : 'winmail.dat', type : 'application/ms-tnef', partID : '2', blobId : 'blob-1'}]};

		app.renderMessageInto(template, rowId, data);
		await tick();

		assert.isTrue(app.setupViewAttachmentActions.calledWith(data), 'view actions for the unpacked block');
		assert.deepEqual(app.setupViewAttachmentActions.lastCall.args[0].attachmentsBlock.map((a : any) => a.filename), ['Rechnung 174977.pdf']);
		assert.isTrue(app.resolveAttachmentViewUrls.calledWith(rowId, data.attachmentsBlock), 'viewer URLs for the unpacked block');
		// the sel_options of the actions are rendered with the content
		assert.property(template.set_value.lastCall.args[0], 'sel_options');
	});

	it("replaces the whole block for a classic row (no blobId), as the classic server fallback returns ALL attachments", async() =>
	{
		const other = {filename : 'other.pdf', type : 'application/pdf', attachment_number : 1};
		const request = sinon.stub().resolves([rtf, other, pdf]);
		const template = {set_value : sinon.spy()};
		const data : any = {uid : rowId, attachmentsBlock : [
			{filename : 'winmail.dat', type : 'application/ms-tnef', partID : '2', winmailFlag : '1', attachment_number : 0},
			{filename : 'other.pdf', type : 'application/pdf', attachment_number : 1},
		]};

		(<any>createMailApp(request)).renderMessageInto(template, rowId, data);
		await tick();

		assert.isTrue(request.calledOnceWith('mail.EGroupware\\Mail\\Ui.ajax_resolveWinmail', [rowId, '2', null]));
		const block = template.set_value.lastCall.args[0].content.attachmentsBlock;
		assert.deepEqual(block.map((a : any) => a.filename), ['other.pdf', 'Rechnung 174977.pdf'], 'other.pdf must not be there twice');
		assert.deepEqual(block.map((a : any) => a.attachment_number), [0, 1]);
	});

	it("leaves the winmail.dat as it is, if it can not be resolved, and stops the loading indicator", async() =>
	{
		const request = sinon.stub().resolves(undefined);
		const classList = {add : sinon.spy(), remove : sinon.spy()};
		const widget = {getDOMNode : () => ({classList})};
		const app = createMailApp(request);
		(<any>app).et2 = {getWidgetById : (id : string) => id === 'attachmentsBlock' ? widget : undefined};
		const template = {set_value : sinon.spy()};
		const winmail = {filename : 'winmail.dat', type : 'application/ms-tnef', partID : '2', blobId : 'blob-1'};
		const data : any = {uid : rowId, attachmentsBlock : [winmail]};

		(<any>app).renderMessageInto(template, rowId, data);
		await tick();

		assert.deepEqual(data.attachmentsBlock, [winmail]);
		assert.isTrue(classList.add.calledOnceWith('loading'));
		assert.isTrue(classList.remove.calledOnceWith('loading'));
		assert.isFalse(dataStoreUID.called);
	});

	it("stops the loading indicator, if the request fails", async() =>
	{
		const request = sinon.stub().rejects(new Error('Failed to fetch'));
		const classList = {add : sinon.spy(), remove : sinon.spy()};
		const app = createMailApp(request);
		(<any>app).et2 = {getWidgetById : (id : string) => id === 'attachmentsBlock' ? {getDOMNode : () => ({classList})} : undefined};

		(<any>app).renderMessageInto({set_value : sinon.spy()}, rowId,
			{uid : rowId, attachmentsBlock : [{filename : 'winmail.dat', type : 'application/ms-tnef', partID : '2', blobId : 'b'}]});
		await tick();

		assert.isTrue(classList.remove.calledOnceWith('loading'));
	});

	it("does not try to resolve anything for a message without winmail.dat", async() =>
	{
		const request = sinon.stub().resolves([]);
		const data : any = {uid : rowId, attachmentsBlock : [{filename : 'Rechnung.pdf', type : 'application/pdf'}], attachments : 'x'};

		(<any>createMailApp(request)).renderMessageInto({set_value : sinon.spy()}, rowId, data);
		await tick();

		assert.isFalse(request.called);
	});

	it("a message/rfc822 sub-part view still gets ITS attachments, even if the containing message has a winmail.dat", () =>
	{
		const request = sinon.stub().resolves({attachmentsBlock : []});
		const data : any = {uid : rowId, attachmentsBlock : [{filename : 'winmail.dat', type : 'application/ms-tnef', blobId : 'b'}]};

		(<any>createMailApp(request)).renderMessageInto({set_value : sinon.spy()}, rowId, data, '2');

		assert.isTrue(request.calledOnceWith('mail.EGroupware\\Mail\\Ui.ajax_fetchAttachments', [rowId, null, '2']));
	});
});
