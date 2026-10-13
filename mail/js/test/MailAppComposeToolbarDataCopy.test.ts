import {assert} from "@open-wc/testing";
import "./MailAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way the other mail app.ts tests do, before app.ts pulls it in transitively
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import type {MailApp} from "../app";

/**
 * getComposeToolbarData()'s per-account cache (mail/js/app.ts) lives in the MAIN window and is
 * served to every compose popup via window.opener. Handing out the cached objects themselves let
 * a popup write objects of its OWN realm into them (the toolbar replaces actions.<id>.data) -
 * bootstrapComposePopup()'s `{...actions}` is only a shallow copy - so from then on the main
 * window's cache referenced the popup's realm and every closed compose popup stayed in memory
 * (~20MB each in Firefox's about:memory, under window-objects/top(none)/detached) until the main
 * window was reloaded. Every caller must get its own deep copy instead, while the server is still
 * asked only once per account.
 */
const APP_SOURCE = '/mail/js/app.ts';

describe('MailApp.getComposeToolbarData() hands out copies of its cache', () =>
{
	let MailAppClass : typeof MailApp;
	let originalOpener : any;

	before(async function()
	{
		this.timeout(15000);
		MailAppClass = (await import(APP_SOURCE)).MailApp;
	});

	beforeEach(() =>
	{
		originalOpener = (window as any).opener;
		Object.defineProperty(window, 'opener', {value : null, configurable : true, writable : true});
	});

	afterEach(() =>
	{
		Object.defineProperty(window, 'opener', {value : originalOpener, configurable : true, writable : true});
	});

	function serverData()
	{
		return {
			actions : {send : {caption : 'Send', data : {toolbarDefault : true}}},
			sel_options : {mailaccount : [{value : '1', label : 'me@example.org'}]},
			content : {mailaccount : '1', to : []},
		};
	}

	function createApp(requests : any[][]) : any
	{
		const app = Object.create(MailAppClass.prototype);
		Object.assign(app, {
			appname : 'mail',
			composeToolbarDataPromises : {},
			egw : {
				request(method : string, params : any[])
				{
					requests.push([method, params]);
					return Promise.resolve(serverData());
				},
			},
		});
		return app;
	}

	it('asks the server only once per account', async() =>
	{
		const requests : any[][] = [];
		const app = createApp(requests);

		await app.getComposeToolbarData('1');
		await app.getComposeToolbarData('1');
		await app.getComposeToolbarData('1');

		assert.lengthOf(requests, 1, "the per-account cache must still save the round trip");
	});

	it('gives every caller its own objects, down to the nested ones', async() =>
	{
		const app = createApp([]);

		const first = await app.getComposeToolbarData('1');
		const second = await app.getComposeToolbarData('1');

		assert.notStrictEqual(first, second);
		assert.notStrictEqual(first.actions, second.actions);
		assert.notStrictEqual(first.actions.send, second.actions.send);
		assert.notStrictEqual(first.actions.send.data, second.actions.send.data);
		assert.deepEqual(first, second);
	});

	it('keeps what a caller writes into its copy out of the cache and out of later callers', async() =>
	{
		const app = createApp([]);

		const popupCopy = await app.getComposeToolbarData('1');
		// what a compose popup's toolbar does: replace a nested object with one of its own
		const popupObject = {popupRealm : true};
		popupCopy.actions.send.data = popupObject;
		popupCopy.content.to.push('someone@example.org');

		const cached = await app.composeToolbarDataPromises['1'];
		assert.notStrictEqual(cached.actions.send.data, popupObject, "the cache must never reference a caller's objects");
		assert.deepEqual(cached, serverData());
		assert.deepEqual(await app.getComposeToolbarData('1'), serverData(), "a later popup must start from the server's data");
	});
});
