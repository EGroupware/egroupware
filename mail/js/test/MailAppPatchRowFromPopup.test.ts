import {assert} from "@open-wc/testing";
import "./MailAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way the other mail app.ts tests do, before app.ts pulls it in transitively
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import type {MailApp} from "../app";

/**
 * MailApp.patchRow() is called from popups too: MailCompose (mail/js/compose.ts) calls it on the
 * POPUP's own MailApp after a successful send to show the replied/forwarded icon, and the "view"
 * popup's read/flag toggles go through it. Run in the popup, the objects it builds (labelTags)
 * belonged to the popup's realm and, stored in the opener's shared row data and from there in the
 * row's widgets, kept every sent reply/forward popup alive (~20MB each) until the main window was
 * reloaded. A call from a window that doesn't own the list must be handed to the one that does,
 * and the row's flags (a popup caller may have created that object: `flags ||= {}`) copied there.
 */
const APP_SOURCE = '/mail/js/app.ts';
const ROW_ID = 'mail::1::INBOX::42';

describe('MailApp.patchRow() called from a popup', () =>
{
	let MailAppClass : typeof MailApp;
	let originalOpener : any;
	let originalDataGet : any;
	let originalDataStore : any;
	let dataStore : Record<string, any>;

	before(async function()
	{
		this.timeout(15000);
		MailAppClass = (await import(APP_SOURCE)).MailApp;
	});

	beforeEach(() =>
	{
		const egw = (window as any).egw;
		originalOpener = (window as any).opener;
		originalDataGet = egw.dataGetUIDdata;
		originalDataStore = egw.dataStoreUID;
		dataStore = {};
		egw.dataGetUIDdata = (uid : string) => dataStore[uid];
		egw.dataStoreUID = (uid : string, data : any) => { dataStore[uid] = {data}; };
	});

	afterEach(() =>
	{
		const egw = (window as any).egw;
		egw.dataGetUIDdata = originalDataGet;
		egw.dataStoreUID = originalDataStore;
		Object.defineProperty(window, 'opener', {value : originalOpener, configurable : true, writable : true});
	});

	/** The main window's MailApp: owns the list, labels come from ITS getRowLabelTags() */
	function createListOwner(labelTags : any[])
	{
		const refreshed : any[] = [];
		const optimistic : string[] = [];
		const app = Object.create(MailAppClass.prototype);
		Object.assign(app, {
			appname : 'mail',
			nm : {refresh : (ids : string[]) => refreshed.push(ids)},
			getRowLabelTags : () => labelTags,
		});
		// `jmap` is a getter on MailApp.prototype - shadow it with an own property
		Object.defineProperty(app, 'jmap', {value : {markOptimistic : (uid : string) => optimistic.push(uid)}});
		return {app, refreshed, optimistic};
	}

	/** A popup's own MailApp: no list of its own, must never build the row's data itself */
	function createPopupApp()
	{
		const app = Object.create(MailAppClass.prototype);
		Object.assign(app, {
			appname : 'mail',
			getRowLabelTags : () => { throw new Error("the popup must not build the row's labelTags"); },
		});
		return app;
	}

	it('hands the call to the window owning the list, so the row only gets that window\'s objects', () =>
	{
		const ownerLabels = [{value : 'label1', label : 'One'}, {value : 'label2', label : 'Two'}];
		const owner = createListOwner(ownerLabels);
		Object.defineProperty(window, 'opener', {value : {closed : false, app : {mail : owner.app}}, configurable : true});
		dataStore[ROW_ID] = {data : {flags : {replied : 'replied'}, 'class' : 'mail replied'}};

		createPopupApp().patchRow(ROW_ID);

		const data = dataStore[ROW_ID].data;
		assert.strictEqual(data.labelTags, ownerLabels, "labelTags must come from the list owner's own getRowLabelTags()");
		assert.strictEqual(data.status_icon, 'mail_reply');
		assert.deepEqual(owner.optimistic, [ROW_ID]);
		assert.deepEqual(owner.refreshed, [[ROW_ID]]);
	});

	it('copies the row\'s flags, so a flags object a popup created is not kept by the row', () =>
	{
		const owner = createListOwner([]);
		Object.defineProperty(window, 'opener', {value : {closed : false, app : {mail : owner.app}}, configurable : true});
		// what compose.ts does after sending, when the row had no flags yet: `flags ||= {}` in the popup
		const popupFlags = {replied : 'replied', forwarded : 'forwarded'};
		dataStore[ROW_ID] = {data : {flags : popupFlags}};

		createPopupApp().patchRow(ROW_ID);

		const data = dataStore[ROW_ID].data;
		assert.notStrictEqual(data.flags, popupFlags);
		assert.deepEqual(data.flags, popupFlags);
		assert.strictEqual(data.status_icon, 'mail_forward');
	});

	it('still patches the row directly when called in the window owning the list', () =>
	{
		const ownerLabels = [{value : 'label1', label : 'One'}, {value : 'label2', label : 'Two'}];
		const owner = createListOwner(ownerLabels);
		Object.defineProperty(window, 'opener', {value : null, configurable : true});
		dataStore[ROW_ID] = {data : {flags : {read : 'read', flagged : 'flagged'}}};

		owner.app.patchRow(ROW_ID);

		const data = dataStore[ROW_ID].data;
		assert.strictEqual(data.labelTags, ownerLabels);
		assert.strictEqual(data.flagged_icon, 'unread_flagged_small');
		assert.deepEqual(owner.refreshed, [[ROW_ID]]);
	});
});
