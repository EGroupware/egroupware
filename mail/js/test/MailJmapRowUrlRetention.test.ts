import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Contract: MailJmap keeps the object URLs (blob: URLs for inline images and viewable
 * attachments) of only the few most recently previewed messages, and revokes the rest.
 *
 * Why it matters: each URL keeps its whole Blob alive until it is revoked or the page is closed.
 * Revoking happened only when the SAME message was resolved again, so a mail tab left open for
 * days held the images and attachments of every message ever previewed in it - megabytes per
 * message with a photo or a PDF.
 *
 * Setup: a MailJmap with a fake JMAP client whose downloadBlob() hands back a small Blob; one
 * <img data-cid> document per message, as resolveInlineImages() receives it from the preview
 * iframe. URL.createObjectURL / revokeObjectURL are wrapped to track which URLs are live.
 *
 * Pass criteria: after previewing more messages than the limit, the number of live URLs does not
 * grow with the number of messages, the oldest messages' URLs are the ones revoked, and a
 * download that finishes after its message was pushed out revokes its own URL instead of keeping it.
 *
 * Environment: no network.
 */
const egw = {
	user: (_key : string) => 1,
	lang: (label : string) => label,
	preference: (_key : string, _app? : string) => null,
	config: (_name : string, _app? : string) => null,
	request: async() => ({}),
	message: (_msg : string, _type? : string) => {},
	webserverUrl: "/egroupware",
};

const MAX_ROWS = (<any>MailJmap).MAX_ROWS_WITH_URLS as number;

describe("MailJmap object URL retention", () =>
{
	let jmap : MailJmap;
	let live : Set<string>;
	let createStub : sinon.SinonStub;
	let revokeStub : sinon.SinonStub;
	let downloads : (() => void)[];
	let holdDownloads : boolean;

	beforeEach(() =>
	{
		live = new Set();
		let counter = 0;
		createStub = sinon.stub(URL, "createObjectURL").callsFake(() =>
		{
			const url = "blob:test/" + (++counter);
			live.add(url);
			return url;
		});
		revokeStub = sinon.stub(URL, "revokeObjectURL").callsFake((url : string) => { live.delete(url); });

		downloads = [];
		holdDownloads = false;
		jmap = new MailJmap({egw, getCustomLabels: () => ({})} as unknown as MailApp);
		(<any>jmap).clients["1"] = {
			downloadBlob: () => new Promise(resolve =>
			{
				const finish = () => resolve({blob: async() => new Blob(["x"], {type: "image/png"})});
				holdDownloads ? downloads.push(finish) : finish();
			})
		};
	});

	afterEach(() =>
	{
		createStub.restore();
		revokeStub.restore();
	});

	async function preview(rowId : string) : Promise<HTMLImageElement>
	{
		const doc = document.implementation.createHTMLDocument("");
		doc.body.innerHTML = '<img data-cid="logo">';
		await jmap.resolveInlineImages(doc, rowId, <any>{
			profileID: "1", accountId: "acc",
			attachments: [{cid: "<logo>", blobId: "b-" + rowId, type: "image/png", name: "logo.png"}],
		});
		return <HTMLImageElement>doc.querySelector("img");
	}

	it("does not keep growing as more messages are previewed", async() =>
	{
		for(let i = 1; i <= MAX_ROWS + 4; i++)
		{
			await preview("row" + i);
		}

		assert.equal(createStub.callCount, MAX_ROWS + 4, "every message got its image");
		assert.equal(live.size, MAX_ROWS, "only the most recent messages keep a live URL");
	});

	it("revokes the oldest messages' URLs, not the newest", async() =>
	{
		const imgs : HTMLImageElement[] = [];
		for(let i = 1; i <= MAX_ROWS + 1; i++)
		{
			imgs.push(await preview("row" + i));
		}

		assert.isFalse(live.has(imgs[0].src), "the first message's URL is revoked");
		for(let i = 1; i <= MAX_ROWS; i++)
		{
			assert.isTrue(live.has(imgs[i].src), "message " + (i + 1) + " still has its URL");
		}
	});

	it("counts going back to a message as using it again", async() =>
	{
		const first = await preview("row1");
		for(let i = 2; i <= MAX_ROWS; i++)
		{
			await preview("row" + i);
		}
		// back to the first one, then one more new message: it is the second that is now the oldest
		const firstAgain = await preview("row1");
		const other = await preview("row" + (MAX_ROWS + 1));

		assert.isTrue(live.has(firstAgain.src), "the message just visited again is kept");
		assert.isTrue(live.has(other.src));
		assert.isFalse(live.has(first.src), "its earlier URL was replaced when it was resolved again");
		assert.equal(live.size, MAX_ROWS);
	});

	it("revokes a PDF's page wrapper even when the same message was resolved again while its pages rendered", async() =>
	{
		// The preview resolves a message's attachments from two places, so a second resolve can
		// revoke and replace the row's URLs while the first is still rendering a PDF. The wrapper
		// that finishes afterwards used to be pushed onto the array the second resolve had just
		// thrown away, and was never revoked.
		let finishPages : (url : string) => void;
		const wrapperStub = sinon.stub(MailJmap, "wrapPdfViewerWithDownload").callsFake(() =>
			new Promise<string>(resolve => { finishPages = resolve; }));
		(<any>jmap).clients["1"] = {
			downloadBlob: async() => ({blob: async() => new Blob(["%PDF"], {type: "application/pdf"})}),
		};
		(<any>jmap).tokens["1"] = {
			sessionUrl: "https://example.com", accountId: "acc", access_token: "tok",
			expires_at: Date.now() + 100000, isLocal: false, customLabels: {},
		};

		try
		{
			const first = jmap.getAttachmentViewUrl("rowA", "1", "blob1", "a.pdf", "application/pdf");
			await new Promise(resolve => setTimeout(resolve, 20));	// first call is now rendering its pages

			jmap.revokeAttachmentViewUrls("rowA");	// the second resolve
			const wrapper = "blob:test/wrapper";
			live.add(wrapper);
			finishPages(wrapper);
			await first;

			assert.isTrue(live.has(wrapper), "the finished wrapper is the one the preview shows");
			jmap.revokeAttachmentViewUrls("rowA");
			assert.equal(live.size, 0, "so it is revoked with the row's other URLs, not left behind");
		}
		finally
		{
			wrapperStub.restore();
		}
	});

	it("revokes a download that finishes after its message was pushed out", async() =>
	{
		holdDownloads = true;
		const slow = preview("slow");
		await Promise.resolve();
		holdDownloads = false;
		for(let i = 1; i <= MAX_ROWS; i++)
		{
			await preview("row" + i);
		}
		const before = live.size;

		downloads.forEach(finish => finish());
		const img = await slow;

		assert.equal(live.size, before, "the late download added nothing that stays live");
		assert.equal(img.getAttribute("src") ?? "", "", "nobody is looking at that message, so it gets no image");
		assert.equal(createStub.callCount, revokeStub.callCount + live.size, "every URL created is either live or revoked");
	});
});
