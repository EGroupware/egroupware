import {assert} from "@open-wc/testing";
// both of these have to come before "../app" - see MailAppImportStub's own docblock
import "./MailAppImportStub";
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import {MailApp} from "../app";
import {etemplate2} from "../../../api/js/etemplate/etemplate2";

/**
 * Regression coverage for the mobile message view's toolbar acting on nothing at all
 * (reported: "Die Funktion gelesen/ungelesen markieren geht in der mobilen Ansicht nicht wenn man
 * eine einzelne E-Mail geöffnet hat.").
 *
 * A toolbar button always executes its action with an EMPTY selection (Et2Toolbar:
 * `action.execute([])`), so every mail action has to work out its own target: from the template's
 * own `mail_id` (that's the desktop display popup), else from `currentlyFocussed`. In the mobile
 * main window `this.et2` is mail.index - no `mail_id` there - and a single tap runs the "open"
 * action WITHOUT selecting the row, so `currentlyFocussed` is the only thing that can name the
 * opened message. mobileView() used to stash the row id on the view template's widget container
 * for et2_ready()'s 'mail.view' case to copy over, but that case stopped running when the mobile
 * view became an Et2Dialog (which loads its template with _no_et2_ready), leaving read/unread -
 * and flag/label/delete/reply/forward with it - a silent no-op on an empty message id.
 */

const ROW_ID = 'mail::1::2::SU5CT1gvdGVzdDM=::42';

/** Just enough of the mail.index et2 tree for mobileView()/callFlagMessages() - notably NO mail_id. */
function createFakeIndexEt2(toolbarActions : object)
{
	const arrayMgrs = {
		content: {getEntry: (_key : string) => undefined},
		sel_options: {getEntry: (key : string) => key === 'toolbar' ? toolbarActions : undefined},
	};
	return {
		getArrayMgr: (name : string) => arrayMgrs[name],
		getWidgetById: (id : string) => id === 'nm[foldertree]' ? {value: '2::INBOX/test3'} : null,
	};
}

/**
 * A MailApp without EgwApp's constructor (which wants a real framework, sidebox and egw instance),
 * carrying only the state mobileView() and the flag action actually read. The list/tree/server
 * side-effects of a successful flag are neutralized, EXCEPT flagMessages() - that one records, as
 * "did this action reach a message at all" is the whole assertion.
 */
function createMailApp(toolbarActions : object)
{
	const app = Object.create(MailApp.prototype) as MailApp;
	const flagged : { flag : any, msg : string[] }[] = [];
	const deleted : string[][] = [];

	Object.assign(app, {
		appname: 'mail',
		egw: {lang: (label : string) => label, is_popup: () => false},
		nm_index: 'nm',
		isMainWindow: true,
		selectedMails: [],
		currentlyFocussed: '',
		customLabels: {},
		et2: createFakeIndexEt2(toolbarActions),
		// viewEntry() builds a real Et2Dialog around mail/templates/mobile/view.xet - out of scope
		// here, but it does set et2_view, which is what makes callFlagMessages() treat this as a
		// popup-ish view further down
		viewEntry: function(_action, _senders, _noEdit?, _callback?)
		{
			this.et2_view = {name: 'view.xet'};
			return Promise.resolve(this.et2_view);
		},
		nm: {getSelection: () => ({ids: [], all: false})},
		patchRow: () => {},
		updateFilterData: () => {},
		refreshFolderStatus: () => {},
		getActiveFilters: () => false,
		flagMessages: (flag, elems) => void flagged.push({flag, msg: elems?.msg}),
		deleteMessages: (msg) => void deleted.push(msg?.msg),
	});

	return {app, flagged, deleted};
}

describe("mobile message view", () =>
{
	let originalGetByApplication;
	let rows : { [uid : string] : any };

	beforeEach(() =>
	{
		// egw's row cache: the real 'data' module is only pulled in by egw.js's (here empty)
		// dynamic include list, so it never registers on the global egw object under test
		rows = {[ROW_ID]: {data: {subject: 'test signed', flags: {read: 'read'}, 'class': ''}}};
		//@ts-ignore
		egw.dataGetUIDdata = (uid : string) => rows[uid];
		//@ts-ignore
		egw.dataStoreUID = (uid : string, data : any) => void (rows[uid] = {data});

		// callFlagMessages()'s 'read' branch resolves the current folder through the FIRST mail
		// etemplate in the window, which in the mobile main window is mail.index
		originalGetByApplication = etemplate2.getByApplication;
		//@ts-ignore
		etemplate2.getByApplication = (_app : string) => [{
			widgetContainer: {getWidgetById: (id : string) => id === 'nm[foldertree]' ? {value: '2::INBOX/test3'} : null}
		}];
	});

	afterEach(() =>
	{
		etemplate2.getByApplication = originalGetByApplication;
	});

	it("marks the opened message read/unread", async() =>
	{
		const {app, flagged} = createMailApp({read: {caption: 'Read / Unread'}});

		await app.mobileView({id: 'open', data: {}}, [{id: ROW_ID}]);
		// exactly what clicking the toolbar entry does: the action, with an empty selection
		app.flag({id: 'read'}, []);

		assert.deepEqual(flagged, [{flag: 'unread', msg: [ROW_ID]}],
			"read/unread has to reach the message opened in the mobile view");
	});

	/**
	 * Delete needs its own coverage on top of the flag path: it has a second guard of its own,
	 * dropping the action outright when the main window has no LIST selection - which the mobile
	 * view never has, however clearly it is showing one specific message.
	 */
	it("deletes the opened message, with nothing selected in the list", async() =>
	{
		const {app, deleted} = createMailApp({delete: {caption: 'Delete'}});

		await app.mobileView({id: 'open', data: {}}, [{id: ROW_ID}]);
		app.deleteMessage({id: 'delete'}, []);

		assert.deepEqual(deleted, [[ROW_ID]], "delete has to reach the message opened in the mobile view");
	});
});
