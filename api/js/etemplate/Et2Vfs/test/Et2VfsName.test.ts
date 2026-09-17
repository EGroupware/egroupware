import {assert, fixture} from "@open-wc/testing";
import {html, unsafeStatic} from "lit/static-html.js";
import * as sinon from "sinon";
import "../Et2VfsName";

window.egw = Object.assign(() => window.egw, {
	decodePath: (path : string) => path
}) as any;

describe("Et2VfsName", () =>
{
	it("opens its row-bound VFS path", async() =>
	{
		const element = await fixture<any>(html`<et2-vfs-name></et2-vfs-name>`);
		const open = sinon.spy();
		element.egw = () => ({open, lang: (value : string) => value, decodePath: (path : string) => path, tooltipUnbind: () => {}});
		element.value = {path: "/home/user/report.pdf", name: "report.pdf", mime: "application/pdf"};
		await element.updateComplete;

		assert.isFalse(element.open());
		assert.isTrue(open.calledOnceWith({
			path: "/home/user/report.pdf",
			type: "application/pdf"
		}, "file"));
	});

	it("preserves string values used by editable filename fields", async() =>
	{
		const element = await fixture<any>(html`<et2-vfs-name></et2-vfs-name>`);

		element.value = "folder/report.pdf";

		assert.equal(element.value, "folder/report.pdf");
	});

	it("opens its row-bound VFS path when rendered readonly", async() =>
	{
		const element = await fixture<any>(html`<et2-vfs-name_ro></et2-vfs-name_ro>`);
		const open = sinon.spy();
		element.egw = () => ({open, lang: (value : string) => value, decodePath: (path : string) => path, tooltipUnbind: () => {}});
		element.value = {path: "/home/user/report.pdf", name: "report.pdf", mime: "application/pdf"};
		await element.updateComplete;

		assert.isFalse(element.open());
		assert.isTrue(open.calledOnceWith({
			path: "/home/user/report.pdf",
			type: "application/pdf"
		}, "file"));
	});

	// the server sends mime === false when it could not resolve the file, eg. a symlink whose
	// target was deleted.  Opening it would only produce a webdav url that 404s into a blank tab.
	for(const tag of ["et2-vfs-name", "et2-vfs-name_ro"])
	{
		it(`reports instead of opening a file with no mime-type (${tag})`, async() =>
		{
			const element = await fixture<any>(html`<${unsafeStatic(tag)}></${unsafeStatic(tag)}>`);
			const open = sinon.spy();
			const message = sinon.spy();
			element.egw = () => ({
				open,
				message,
				lang: (value : string, placeholder : string) => value.replace("%1", placeholder),
				decodePath: (path : string) => path,
				tooltipUnbind: () => {}
			});
			element.value = {path: "/home/user/dangling.odt", name: "dangling.odt", mime: false};
			await element.updateComplete;

			assert.isFalse(element.open());
			assert.isTrue(open.notCalled, "should not try to open a file the server could not resolve");
			assert.isTrue(message.calledOnceWith("File 'dangling.odt' not found!", "error"));
		});

		// a directory has to reach egw.open() as Vfs::DIR_MIME_TYPE, the only mime the link-registry
		// has filemanager registered for - otherwise it finds no handler and falls back to the WebDAV
		// url, opening the listing in a new browser tab instead of in filemanager.  Not every directory
		// reports that mime itself: a symlink to one keeps its target's, and EPL's links stream-wrapper
		// gives /apps/<app>/... "egw/<app>".  What they all have is the server's is_dir flag.
		it(`opens a directory whose mime is not DIR_MIME_TYPE in filemanager (${tag})`, async() =>
		{
			const element = await fixture<any>(html`<${unsafeStatic(tag)}></${unsafeStatic(tag)}>`);
			const open = sinon.spy();
			element.egw = () => ({open, lang: (value : string) => value, decodePath: (path : string) => path, tooltipUnbind: () => {}});
			element.value = {path: "/apps/addressbook", name: "addressbook", mime: "egw/addressbook", is_dir: 1};
			await element.updateComplete;

			assert.isFalse(element.open());
			assert.isTrue(open.calledOnceWith({
				path: "/apps/addressbook",
				type: "httpd/unix-directory"
			}, "file"));
		});
	}
});
