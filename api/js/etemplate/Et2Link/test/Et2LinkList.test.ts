/**
 * Test file for Etemplate webComponent Et2LinkList
 */
import {assert} from "@open-wc/testing";

// @ts-ignore
window.egw = Object.assign(() => window.egw, {
	preference: () => Promise.resolve(20),
	lang: (l : string) => l,
	debug: () => {},
	image: () => "",
	link_title: () => Promise.resolve(""),
	tooltipBind: () => {},
	tooltipUnbind: () => {}
}) as any;

/**
 * The context menu of a link copies it into the EGroupware clipboard, in the format the list
 * context menus (EgwPopupActionImplementation) and Et2LinkPasteDialog read.
 */
describe("Et2LinkList clipboard", () =>
{
	let proto : any;
	let store : { [key : string] : string };
	let list : any;

	before(async() =>
	{
		proto = (await import("../Et2LinkList")).Et2LinkList.prototype;
	});

	beforeEach(() =>
	{
		store = {};
		const egw = {
			getSessionItem: (app, key) => store[app + "-" + key] ?? null,
			setSessionItem: (app, key, value) => {store[app + "-" + key] = value;}
		};
		list = {
			application: "calendar",
			entryId: "25412",
			egw: () => egw,
			_clipboardEntry: proto._clipboardEntry
		};
	});

	const clipboard = () => JSON.parse(store["phpgwapi-egw_clipboard"]);

	it("copies a linked entry as app::id with type link", () =>
	{
		assert.isTrue(proto._copyToClipboard.call(list, {app: "acilog", id: "270916", title: "A todo"}, false));

		assert.deepEqual(clipboard(), {
			type: ["link"],
			selected: [{id: "acilog::270916", data: {title: "A todo"}}]
		});
	});

	it("copies an attached file as its VFS path, like the filemanager does", () =>
	{
		proto._copyToClipboard.call(list, {app: "file", id: "My file.pdf", app2: "calendar", id2: "25412"}, false);

		const data = clipboard();
		assert.deepEqual(data.type, ["file", "link"]);
		assert.equal(data.selected[0].id, "filemanager::/apps/calendar/25412/My file.pdf");
		assert.equal(data.selected[0].data.path, "/apps/calendar/25412/My file.pdf");
		assert.equal(data.selected[0].data.name, "My file.pdf");
	});

	it("keeps the VFS encoding of the name in the path, shows it decoded", () =>
	{
		// "100% a#b?c.txt" as sqlfs stores it and the link list sends it
		const entry = proto._clipboardEntry.call(list, {app: "file", id: "100%25 a%23b%3Fc.txt", app2: "calendar", id2: "25412"});

		assert.equal(entry.data.path, "/apps/calendar/25412/100%25 a%23b%3Fc.txt");
		assert.equal(entry.id, "filemanager::/apps/calendar/25412/100%25 a%23b%3Fc.txt");
		assert.equal(entry.data.name, "100% a#b?c.txt");
	});

	it("does not throw on a name that is not VFS encoded", () =>
	{
		let entry;
		assert.doesNotThrow(() =>
		{
			entry = proto._clipboardEntry.call(list, {app: "file", id: "100% done.pdf", app2: "calendar", id2: "25412"});
		});
		assert.equal(entry.data.path, "/apps/calendar/25412/100% done.pdf");
		assert.equal(entry.data.name, "100% done.pdf");
	});

	it("never lets the name leave the entry's directory", () =>
	{
		// an encoded slash stays part of the name, it is not decoded into the path
		const entry = proto._clipboardEntry.call(list, {app: "file", id: "..%2F..%2Fsecret.txt", app2: "calendar", id2: "25412"});
		assert.equal(entry.data.path, "/apps/calendar/25412/..%2F..%2Fsecret.txt");

		for(const id of ["../../secret.txt", "sub/file.txt", "..", "."])
		{
			assert.isNull(proto._clipboardEntry.call(list, {app: "file", id: id, app2: "calendar", id2: "25412"}), id);
		}
	});

	it("replaces the clipboard on copy, appends on add without duplicates", () =>
	{
		proto._copyToClipboard.call(list, {app: "infolog", id: "1"}, false);
		proto._copyToClipboard.call(list, {app: "acilog", id: "2"}, false);
		assert.deepEqual(clipboard().selected.map(s => s.id), ["acilog::2"]);

		proto._copyToClipboard.call(list, {app: "file", id: "a.txt", app2: "calendar", id2: "25412"}, true);
		proto._copyToClipboard.call(list, {app: "acilog", id: "2"}, true);

		const data = clipboard();
		assert.deepEqual(data.selected.map(s => s.id), ["filemanager::/apps/calendar/25412/a.txt", "acilog::2"]);
		assert.deepEqual(data.type, ["file", "link"]);
	});

	it("does not copy a file that is not stored yet", () =>
	{
		assert.isNull(proto._clipboardEntry.call(list, {app: "file", id: {name: "new.txt"}}));
		assert.isFalse(proto._copyToClipboard.call(list, {app: "file", id: {name: "new.txt"}}, false));
		assert.isUndefined(store["phpgwapi-egw_clipboard"]);
	});
});

describe("Et2LinkList context menu", () =>
{
	let proto : any;
	let opened : any[];
	let list : any;

	before(async() =>
	{
		proto = (await import("../Et2LinkList")).Et2LinkList.prototype;
	});

	beforeEach(() =>
	{
		opened = [];
		const egw = {
			lang: (l : string) => l,
			image: () => "",
			open: (...args) => {opened.push(args);}
		};
		list = {
			application: "calendar",
			entryId: "25412",
			egw: () => egw,
			_createMailContextMenu: proto._createMailContextMenu
		};
		proto._createContextMenu.call(list);
	});

	it("opens the file information with the VFS path as the link list sends it", () =>
	{
		// "100% a#b?c.txt" as sqlfs stores it, only the encoded path exists in the VFS
		list.context.data = {app: "file", id: "100%25 a%23b%3Fc.txt", app2: "calendar", id2: "25412"};
		list.context.getItem("file_info").onClick();

		assert.deepEqual(opened, [["/apps/calendar/25412/100%25 a%23b%3Fc.txt", "filemanager", "edit"]]);
	});
});
