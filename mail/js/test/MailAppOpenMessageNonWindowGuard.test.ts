import {assert} from "@open-wc/testing";
// both of these have to come before "../app" - see MailAppImportStub's own docblock
import "./MailAppImportStub";
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import {MailApp} from "../app";

const ROW_ID = 'mail::1::2::SU5CT1gvdGVzdDM=::42';

/**
 * Ticket #124351: MailApp.openMessage()'s setTitle() callback used to assume egw().open()'s
 * result is always a real Window - but EgwFramework.openPopup()'s own narrow-viewport/
 * "open_popups_in: same_window" branch resolves with an <et2-dialog> ELEMENT instead whenever the
 * calling window is narrow (live-reproduced 2026-09-28, ralf, from the browser console: opening a
 * message this way threw "Cannot read properties of undefined (reading 'querySelectorAll')" deep
 * inside egw(w)'s per-window Files module constructor, since a DOM Element has no `.document` at
 * all). Fixed by skipping egw(w)/title-setting entirely for a non-Window result - a dialog has no
 * independent window title to set anyway.
 */
function createMailApp()
{
	const app = Object.create(MailApp.prototype) as MailApp;

	Object.assign(app, {
		egw: {lang: (label : string) => label},
		selectedMails: [],
		currentlyFocussed: '',
		scheduleMarkRead: () => {},
	});

	return app;
}

describe("MailApp.openMessage() - non-Window result guard", () =>
{
	let originalOpen;
	let rows : { [uid : string] : any };

	beforeEach(() =>
	{
		rows = {[ROW_ID]: {data: {subject: 'test subject'}}};
		//@ts-ignore
		egw.dataGetUIDdata = (uid : string) => rows[uid];
		originalOpen = egw.open;
	});

	afterEach(() =>
	{
		egw.open = originalOpen;
	});

	it("does not crash constructing a per-window egw instance when open() resolves to a dialog element, not a real Window", async() =>
	{
		const app = createMailApp();
		const fakeDialogElement = document.createElement('div');
		//@ts-ignore
		egw.open = () => fakeDialogElement;

		const rejections : any[] = [];
		const onUnhandled = (e : PromiseRejectionEvent) => rejections.push(e.reason);
		window.addEventListener('unhandledrejection', onUnhandled);

		try
		{
			app.openMessage({id: 'open'}, [{id: ROW_ID}], 'view');
			// let setTitle()'s microtasks run and any unhandled rejection surface
			await new Promise(resolve => setTimeout(resolve, 0));
		}
		finally
		{
			window.removeEventListener('unhandledrejection', onUnhandled);
		}

		assert.isEmpty(rejections, 'openMessage() must not crash constructing egw(w) for a non-Window result');
		assert.equal(fakeDialogElement.title, '', 'a dialog element has no independent window title to set');
	});

	it("still sets the real window's title when open() resolves to an actual Window", async() =>
	{
		const app = createMailApp();
		//@ts-ignore
		egw.open = () => window;

		app.openMessage({id: 'open'}, [{id: ROW_ID}], 'view');
		await new Promise(resolve => setTimeout(resolve, 0));

		assert.equal(document.title, 'test subject');
	});
});
