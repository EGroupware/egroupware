import {assert, fixture, html} from "@open-wc/testing";
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
});
