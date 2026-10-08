import {assert, fixture, html} from "@open-wc/testing";
import * as sinon from "sinon";
import type {Et2HtmlArea} from "../Et2HtmlArea";
import "../Et2HtmlArea";
import {Et2VfsSelectDialog} from "../../Et2Vfs/Et2VfsSelectDialog";

// Matches the egw fake api/js/etemplate/Et2Vfs/test/Et2VfsSelectButton.test.ts already uses to
// construct the same et2-vfs-select-dialog component this suite's _openDefaultFilePicker() loads.
window.egw = {
	lang: (label : string) => label,
	preference: () => "",
	ajaxUrl: (menuaction : string) => `/egroupware/json.php?menuaction=${encodeURIComponent(menuaction)}`,
	webserverUrl: "/egroupware",
	tooltipUnbind: () => {},
	tooltipBind: () => {},
	image: () => "",
	link_app_list: () => ({}),
	langRequireApp: () => Promise.resolve(),
	getLocalStorageItem: () => null,
} as any;

afterEach(() =>
{
	sinon.restore();
});

/**
 * Ticket #125961: "insert from VFS" must be able to produce a `data:` URI instead of the classic
 * webdav.php URL - a webdav.php URL only works for whoever has this exact account's own session.
 * A literal `imageUpload` URL (mail compose/signature's own data:-URI-producing endpoint, set as
 * a plain server-rendered content value - NOT a JS property, which @tinymce/tinymce-webcomponent
 * only reads once, at its own one-time init, too late for anything set afterward) now doubles as
 * the "insert from VFS" target too: the SAME literal URL, with `&path=` appended, instead of the
 * classic `${webserverUrl}${file.downloadUrl}` build. Every other `imageUpload="link_to"` user
 * (a content-path lookup, not a literal URL) must keep today's behavior unchanged.
 */
describe("Et2HtmlArea._openDefaultFilePicker()'s literal-imageUpload-URL VFS-insert path", () =>
{
	function stubDialog(path : string, fileInfo : object | undefined)
	{
		sinon.stub(Et2VfsSelectDialog.prototype, "show").resolves(undefined);
		sinon.stub(Et2VfsSelectDialog.prototype, "getComplete").resolves([1, path]);
		sinon.stub(Et2VfsSelectDialog.prototype, "fileInfo").returns(fileInfo);
	}

	it("keeps building the webdav.php url unchanged for a classic link_to imageUpload", async() =>
	{
		const element = await fixture<Et2HtmlArea>(html`<et2-htmlarea></et2-htmlarea>`);
		(element as any).imageUpload = "some_content_key";
		stubDialog("/home/me/photo.png", {path: "/home/me/photo.png", downloadUrl: "/webdav.php/home/me/photo.png"});
		const fetchSpy = sinon.spy(globalThis, "fetch");
		let resolvedUrl : string;
		const callback = (url : string) => { resolvedUrl = url; };

		await (element as any)._openDefaultFilePicker(callback, {filetype: "image"});

		assert.isFalse(fetchSpy.called, "a classic link_to imageUpload must never fetch() the resize endpoint");
		assert.equal(resolvedUrl, "/egroupware/webdav.php/home/me/photo.png");
	});

	it("fetches the literal imageUpload URL with &path= appended, and uses the data: URI it returns", async() =>
	{
		const element = await fixture<Et2HtmlArea>(html`<et2-htmlarea></et2-htmlarea>`);
		(element as any).imageUpload = "/json.php?menuaction=Foo::bar";
		stubDialog("/home/me/photo.png", {path: "/home/me/photo.png", downloadUrl: "/webdav.php/home/me/photo.png"});
		const fetchStub = sinon.stub(globalThis, "fetch").resolves({
			json: () => Promise.resolve({location: "data:image/png;base64,AAAA"}),
		} as Response);
		let resolvedUrl : string;
		const callback = (url : string) => { resolvedUrl = url; };

		await (element as any)._openDefaultFilePicker(callback, {filetype: "image"});

		// isTrue(called), not calledOnce: Et2VfsSelectDialog's own background remoteSearch()
		// (unrelated to this test, see its own stubbed-out egw fake) can race an unhandled fetch
		// of its own into the same sinon stub - the actual guarantee this test cares about is
		// that OUR call went out with the right url/method, not an exact total call count.
		assert.isTrue(fetchStub.called);
		const ourCall = fetchStub.getCalls().find(call => String(call.args[0]).includes("menuaction=Foo::bar"));
		assert.exists(ourCall, "the literal imageUpload URL must have been fetched");
		assert.equal(ourCall.args[0], "/json.php?menuaction=Foo::bar&path=%2Fhome%2Fme%2Fphoto.png");
		assert.equal(ourCall.args[1]?.method, "POST");
		assert.equal(resolvedUrl, "data:image/png;base64,AAAA",
			"the endpoint's own data: URI must be used as the src, never the webdav.php url");
	});

	it("never calls back, and never fetches, when no file was actually picked", async() =>
	{
		const element = await fixture<Et2HtmlArea>(html`<et2-htmlarea></et2-htmlarea>`);
		(element as any).imageUpload = "/json.php?menuaction=Foo::bar";
		stubDialog("", undefined);
		const fetchSpy = sinon.spy(globalThis, "fetch");
		let called = false;
		const callback = () => { called = true; };

		await (element as any)._openDefaultFilePicker(callback, {filetype: "image"});

		assert.isFalse(fetchSpy.called);
		assert.isFalse(called);
	});
});
