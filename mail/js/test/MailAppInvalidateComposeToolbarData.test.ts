import {assert} from "@open-wc/testing";
import "./MailAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way the other mail app.ts tests do, before app.ts pulls it in transitively
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import type {MailApp} from "../app";

/**
 * Ticket #125092 follow-up (Ingo/Birgit via ralf, live: "ohne Neuladen wird wieder die genommen
 * von der ich zuvor gewechselt war... als würde die pref nicht neu gelesen") -
 * getComposeToolbarData()'s own cache (mail/js/app.ts), keyed by accId and kept "for the life of
 * the MAIN window", has no way to know its cached `content.mailaccount` (baked in from whatever
 * mail/LastSignatureIDUsed was at the time of that FIRST fetch for this account) goes stale the
 * moment a LATER send updates that very preference - every later compose for the same account,
 * same main-window lifetime, kept reusing the now-wrong pre-selection without a reload (which
 * simply starts a fresh MailApp/cache) ever being involved. invalidateComposeToolbarData() (called
 * from MailJmap.rememberLastUsedIdentity() - see MailJmapRememberLastUsedIdentity.test.ts for that
 * wiring) is the fix; this file covers the cache-clearing/opener-redirect itself in isolation,
 * same `Object.create(MailAppClass.prototype)` construction as MailAppSafeOpener.test.ts's own
 * `jmap` getter coverage (real constructor needs far more than this needs).
 */
const APP_SOURCE = '/mail/js/app.ts';

describe('MailApp.invalidateComposeToolbarData()', () =>
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
	});

	afterEach(() =>
	{
		Object.defineProperty(window, 'opener', {value : originalOpener, configurable : true, writable : true});
	});

	function createApp() : any
	{
		const app = Object.create(MailAppClass.prototype);
		Object.assign(app, {appname : 'mail', composeToolbarDataPromises : {}});
		return app;
	}

	it('drops only the given account\'s own cached entry, leaving every other account untouched', () =>
	{
		Object.defineProperty(window, 'opener', {value : null, configurable : true});
		const app = createApp();
		app.composeToolbarDataPromises = {'1' : Promise.resolve('stale-1'), '42' : Promise.resolve('stale-42')};

		app.invalidateComposeToolbarData('1');

		assert.isUndefined(app.composeToolbarDataPromises['1']);
		assert.isDefined(app.composeToolbarDataPromises['42'], "a DIFFERENT account's own cache entry must survive untouched");
	});

	it('is a no-op (does not throw) when that account was never cached in the first place', () =>
	{
		Object.defineProperty(window, 'opener', {value : null, configurable : true});
		const app = createApp();

		assert.doesNotThrow(() => app.invalidateComposeToolbarData('999'));
	});

	it('redirects to the OPENER\'s own instance when called from within a popup, instead of clearing its own (separate) cache', () =>
	{
		const openerInvalidateCalls : string[] = [];
		const openerMailApp = {
			composeToolbarDataPromises : {'1' : Promise.resolve('opener-cached')},
			invalidateComposeToolbarData(accId : string)
			{
				openerInvalidateCalls.push(accId);
				delete this.composeToolbarDataPromises[accId];
			},
		};
		Object.defineProperty(window, 'opener', {value : {closed : false, app : {mail : openerMailApp}}, configurable : true});

		const popupApp = createApp();
		// the popup's OWN cache - must stay untouched, the real cache lives on the opener
		popupApp.composeToolbarDataPromises = {'1' : Promise.resolve('popup-own-copy')};

		popupApp.invalidateComposeToolbarData('1');

		assert.deepEqual(openerInvalidateCalls, ['1'], "must have invalidated via the OPENER's own instance");
		assert.isUndefined(openerMailApp.composeToolbarDataPromises['1'], "the opener's own cache entry must be gone");
		assert.isDefined(popupApp.composeToolbarDataPromises['1'],
			"the popup's own (never actually used) cache object must be left alone - it redirected entirely, not cleared locally too");
	});
});
