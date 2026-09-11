import {assert, elementUpdated, fixture, html} from "@open-wc/testing";
import * as sinon from "sinon";
import "../Et2VfsMime";
import {Et2VfsMime} from "../Et2VfsMime";

// Ambient global `egw` (not `this.egw()`) is read at module-eval time (ExposeMixin.ts's
// IMAGE_DEFAULT) and by a few Et2VfsMime methods directly (set value()'s label, isExposable()'s
// and expose_onclick()'s egw.file_editor_prefered_mimes() calls) - same top-level function-stub
// shape as Et2VfsName.test.ts, extended with the extra ambient methods this widget needs.
window.egw = Object.assign(() => window.egw, {
	lang: (msg : string, ...args : any[]) => args.length ? msg.replace('%1', args[0]) : msg,
	file_editor_prefered_mimes: () => null,
	debug: () => {},
	image: () => ""
}) as any;

describe("Et2VfsMime", () =>
{
	async function element() : Promise<any>
	{
		const el = await fixture<any>(html`<et2-vfs-mime></et2-vfs-mime>`);
		// per-instance egw() override for the widget's own this.egw() calls (mime_icon,
		// tooltipBind/Unbind) - separate from the ambient window.egw stub above, same
		// two-tier pattern Et2VfsName.test.ts uses.
		el.egw = () => ({
			mime_icon: sinon.stub().returns("mime-icon-url"),
			tooltipBind: sinon.spy(),
			tooltipUnbind: sinon.spy(),
			image: () => "",
			lang: (msg : string) => msg,
			debug: () => {}
		});
		return el;
	}

	it("derives its icon src from egw().mime_icon(), keyed by the row's mime/path/mtime", async() =>
	{
		const el = await element();
		const mime_icon = sinon.stub().returns("https://example.test/mime/pdf.png");
		el.egw = () => ({mime_icon, tooltipBind: () => {}, tooltipUnbind: () => {}, image: () => "", lang: (m : string) => m, debug: () => {}});

		el.value = {mime: "application/pdf", path: "/home/demo/report.pdf", mtime: 12345};
		await elementUpdated(el);

		assert.isTrue(mime_icon.calledOnceWith("application/pdf", "/home/demo/report.pdf", undefined, 12345));
		assert.equal(el.src, "https://example.test/mime/pdf.png");
	});

	it("prefers an explicit src over the mime_icon() lookup when both are given", async() =>
	{
		const el = await element();
		const mime_icon = sinon.stub().returns("should-not-be-used");
		el.egw = () => ({mime_icon, tooltipBind: () => {}, tooltipUnbind: () => {}, image: () => "", lang: (m : string) => m, debug: () => {}});

		el.value = {mime: "application/pdf", path: "/home/demo/report.pdf", src: "https://example.test/explicit.png"};
		await elementUpdated(el);

		assert.equal(el.src, "https://example.test/explicit.png");
	});

	it("treats a plain 'main/sub' string value as a bare mime type", async() =>
	{
		const el = await element();
		el.value = "image/jpeg";
		await elementUpdated(el);

		assert.equal(el.mime, "image/jpeg");
	});

	it("flags symlink=true only when the S_IFLNK bit (0xA000) is set in the file mode", async() =>
	{
		const el = await element();

		el.value = {mime: "text/plain", path: "/home/demo/notes.txt", mode: 0o120644 /* S_IFLNK | 0644, matches 0xA000 mask */};
		await elementUpdated(el);
		assert.isTrue(el.symlink, "a mode with the symlink bits set must flag symlink=true");

		el.value = {mime: "text/plain", path: "/home/demo/notes.txt", mode: 0o100644 /* plain regular file */};
		await elementUpdated(el);
		assert.isFalse(el.symlink, "a plain regular-file mode must flag symlink=false");
	});

	it("isExposable() always returns false for a directory (Vfs::DIR_MIME_TYPE), regardless of download_url", async() =>
	{
		const el = await element();
		el.value = {mime: Et2VfsMime.DIR_MIME_TYPE, path: "/home/demo/subfolder", download_url: "/webdav.php/home/demo/subfolder"};
		await elementUpdated(el);

		assert.isFalse(el.isExposable(), "a directory row must never be treated as exposable, even with a download_url set");
	});

	it("isExposable() returns false for a file with no download_url, even for an otherwise-exposable image mime", async() =>
	{
		const el = await element();
		el.value = {mime: "image/jpeg", path: "/home/demo/photo.jpg"};
		await elementUpdated(el);

		assert.isFalse(el.isExposable(), "without a download_url there is nothing to expose, regardless of mime type");
	});

	it("isExposable() returns true for a mime type with a registered preferred external editor, even if not gallery-previewable", async() =>
	{
		const el = await element();
		(window.egw as any).file_editor_prefered_mimes = () => ({
			edit: {menuaction: "some.editor.open"},
			mime: {"application/vnd.oasis.opendocument.text": true}
		});
		el.value = {
			mime: "application/vnd.oasis.opendocument.text",
			path: "/home/demo/letter.odt",
			download_url: "/webdav.php/home/demo/letter.odt"
		};
		await elementUpdated(el);

		assert.isTrue(el.isExposable(),
			"a mime type with a preferred editor must be exposable even though it's not a gallery image/video/audio type");

		(window.egw as any).file_editor_prefered_mimes = () => null;
	});

	it("isExposable() falls back to the gallery mime check when there is no preferred editor for this mime type", async() =>
	{
		const el = await element();
		el.value = {mime: "image/jpeg", path: "/home/demo/photo.jpg", download_url: "/webdav.php/home/demo/photo.jpg"};
		await elementUpdated(el);

		assert.isTrue(el.isExposable(), "a plain gallery-previewable image mime must still be exposable with no preferred editor");

		el.value = {mime: "application/zip", path: "/home/demo/archive.zip", download_url: "/webdav.php/home/demo/archive.zip"};
		await elementUpdated(el);

		assert.isFalse(el.isExposable(), "a non-previewable, non-editable mime type must not be exposable");
	});

	it("binds a large-thumbnail tooltip only for OpenDocument mime types (text/presentation/spreadsheet/chart)", async() =>
	{
		const el = await element();
		const tooltipBind = sinon.spy();
		const tooltipUnbind = sinon.spy();
		el.egw = () => ({
			mime_icon: () => "icon-url", tooltipBind, tooltipUnbind, image: () => "", lang: (m : string) => m, debug: () => {}
		});

		el.value = {mime: "application/vnd.oasis.opendocument.spreadsheet", path: "/home/demo/sheet.ods"};
		await elementUpdated(el);
		assert.isTrue(tooltipBind.called, "an OpenDocument spreadsheet must get the large-thumbnail tooltip bound");

		tooltipBind.resetHistory();
		tooltipUnbind.resetHistory();

		el.value = {mime: "application/pdf", path: "/home/demo/report.pdf"};
		await elementUpdated(el);
		assert.isFalse(tooltipBind.called, "a non-OpenDocument mime type must not get the large-thumbnail tooltip bound");
		assert.isTrue(tooltipUnbind.called, "a non-OpenDocument mime type must have any previous tooltip unbound");
	});
});
