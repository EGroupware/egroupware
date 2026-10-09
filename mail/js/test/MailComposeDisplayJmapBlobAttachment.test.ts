import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {MailCompose} from "../compose";
import type {MailApp} from "../app";

/**
 * Ticket #126271 (a real customer): downloading a forwarded xlsx attachment from the COMPOSE
 * window's own attachment list got a cryptic/random filename instead of its real one.
 *
 * Root cause: displayJmapBlobAttachment() (mail/js/compose.ts) navigated a sized egw.openPopup()
 * directly to MailJmap.downloadBlobUrl()'s own result - for anything other than a PDF, that's a
 * BARE blob: URL of the raw file (downloadBlobUrl() only wraps PDF in a real HTML shell). A
 * plain NAVIGATION to a blob: URL (as opposed to a real `<a download>` click) falls back to the
 * blob's own opaque UUID as the save filename regardless of the underlying File's real .name -
 * same bug class already fixed elsewhere for a RECEIVED message's own attachments (tracker
 * #124541/#124932, getAttachmentViewUrl()'s own docblock) - this one compose-window call site
 * never got it. This also explains the ticket's OTHER symptom (a blank popup/tab opens
 * alongside the download): navigating a window to a blob: URL the browser can't render inline
 * just triggers a download inside that window's own context, leaving it empty.
 */

function createFakeWidget(id : string, initial : any = '')
{
	return {
		id, _value: initial,
		get_value() { return this._value; },
		set_value(v : any) { this._value = v; },
		set_disabled() {},
		getParent() { return null; },
	};
}

function createFakeEt2()
{
	const widgets : Record<string, any> = {};
	return {
		getWidgetById : (id : string) => widgets[id] ??= createFakeWidget(id),
		getArrayMgr : (_name : string) => ({getEntry : (_key : string) => undefined, data : {}}),
		setArrayMgr : (_name : string, _mgr : any) => {},
		widgets,
	};
}

function createCompose(downloadBlobUrl : sinon.SinonStub)
{
	const egw : any = {
		lang : (label : string, ...args : string[]) =>
		{
			let i = 0;
			return String(label).replace(/%(\d+)/g, () => args[i++] ?? '');
		},
		preference : (_key : string, _app? : string) => null,
		message : (_msg : string, _type? : string) => {},
		openPopup : sinon.stub(),
		link : (path : string, params : any = {}) => path + '?' + new URLSearchParams(params).toString(),
	};
	(<any>window).egw = egw;

	const app = {egw, jmap : {downloadBlobUrl}} as unknown as MailApp;
	const compose = new MailCompose(app, {from : '', sourceId : '', mode : ''});
	(compose as any).et2 = createFakeEt2();
	return {compose, egw};
}

describe("MailCompose.displayJmapBlobAttachment() (ticket #126271)", () =>
{
	let clickStub : sinon.SinonStub;

	beforeEach(() =>
	{
		clickStub = sinon.stub(HTMLAnchorElement.prototype, 'click');
	});

	afterEach(() =>
	{
		clickStub.restore();
	});

	it("downloads a non-PDF attachment via a real <a download> click, never opening a popup", async() =>
	{
		const downloadBlobUrl = sinon.stub().resolves("blob:http://x/raw-xlsx-blob");
		const {compose, egw} = createCompose(downloadBlobUrl);

		await (compose as any).displayJmapBlobAttachment({
			jmapProfileID : "1", jmapBlobId : "blob1", name : "Bestellung.xlsx",
			type : "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", tmp_name : "tmp1",
		});

		assert.isTrue(downloadBlobUrl.calledOnceWith("1", "blob1", "Bestellung.xlsx",
			"application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"));
		assert.isFalse(egw.openPopup.called, "a bare blob: URL has no page to navigate a popup to");
		assert.isTrue(clickStub.calledOnce, "must trigger a real download click");
		const anchor = clickStub.firstCall.thisValue as HTMLAnchorElement;
		assert.equal(anchor.href, "blob:http://x/raw-xlsx-blob");
		assert.equal(anchor.download, "Bestellung.xlsx", "the real filename must be used, not left to the blob: URL's own opaque UUID");
	});

	it("still opens a PDF in a sized popup - downloadBlobUrl() already wraps it in a real HTML shell", async() =>
	{
		const downloadBlobUrl = sinon.stub().resolves("blob:http://x/pdf-viewer-wrapper");
		const {compose, egw} = createCompose(downloadBlobUrl);

		await (compose as any).displayJmapBlobAttachment({
			jmapProfileID : "1", jmapBlobId : "blob1", name : "Invoice.pdf", type : "application/pdf", tmp_name : "tmp2",
		});

		assert.isTrue(egw.openPopup.calledOnceWith("blob:http://x/pdf-viewer-wrapper", 800, 600, "maildisplayAttachment_tmp2"));
		assert.isFalse(clickStub.called, "a PDF's own wrapper page already has its own working download link");
	});
});
