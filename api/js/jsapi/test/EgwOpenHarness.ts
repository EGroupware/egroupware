/**
 * Test harness for the "open" module (egw_open.js).
 *
 * Loads the REAL, unmodified egw_open.js on top of the Layer 1 core
 * environment. This module's job is deciding HOW to open something
 * (popup vs framework tab vs plain navigation vs javascript: execution),
 * not implementing the link registry, mime detection, or dialog widgets
 * it consults to make that decision - so this harness stubs those
 * dependencies as configurable sinon stubs (`env.stubs.*`), and stubs the
 * real browser side effects it would otherwise trigger:
 *
 * - `window.open` (`env.window.open`) - so tests never open a real popup.
 * - `Et2Dialog` (bare global) - the popup-blocker warning dialog.
 * - `egwIsMobile()` (bare global) - defaults to "not mobile".
 * - `egw.top` - set the way egw.js's bootstrap normally would.
 *
 * jQuery is loaded for real (egw_open.js uses jQuery.extend and, in
 * openPopup, jQuery(egw.top).outerWidth()/outerHeight()), as is the real
 * egw_utils.js for `storeWindow` (open_link's popup-tracking call) - it's
 * lightweight and only imports egw_core.js, already loaded. `message` is
 * NOT loaded for real (that's egw_message.js, its own Layer 2 module) -
 * mailto() only needs it as a plain stub.
 *
 * `Et2Dialog` is a minimal custom element stand-in (registered so `new
 * Et2Dialog(...)` is legal), not the real Lit component - it captures
 * whatever transformAttributes() is given (including the `callback`
 * openWithinWindow()'s dialog-chooser wires up) so tests can invoke that
 * callback directly, as if a user had clicked a button, without needing
 * the real dialog to render at all.
 *
 * `json()` is a controllable fake (see `env.jsonCalls`) purely for
 * openDialog() - the real network layer has its own thorough coverage in
 * EgwJson.test.ts.
 *
 * `HTMLFormElement.prototype.submit` is stubbed in the iframe's realm
 * (`env.formSubmits`), so the long-parameters form-POST path
 * (openComposePost(), also used by openWithinWindow()) can be asserted on
 * without the browser actually navigating anything.
 *
 * Deliberately NOT exercised anywhere in the test suite built on this
 * harness: assigning to a real window's `location.href` (link_handler's
 * no-framework fallback).
 */
import {createEgwCoreEnv, EgwCoreEnv} from "./EgwCoreHarness";
import * as sinon from "sinon";

export interface JsonCall
{
	menuaction : string;
	parameters : any[];
	callback : Function | undefined;
}

export interface EgwOpenEnv extends EgwCoreEnv
{
	stubs : {
		link_get_registry : sinon.SinonStub;
		link : sinon.SinonStub;
		mime_open : sinon.SinonStub;
		get_mime_info : sinon.SinonStub;
		link_app_list : sinon.SinonStub;
		lang : sinon.SinonStub;
		preference : sinon.SinonStub;
		set_preference : sinon.SinonStub;
		callFunc : sinon.SinonStub;
		message : sinon.SinonStub;
		app_name : sinon.SinonStub;
	};
	jsonCalls : JsonCall[];

	/** Every form.submit() the module ran, in order (see FormSubmit) */
	formSubmits : FormSubmit[];
}

/**
 * A recorded form.submit() - captured at submit time, as the module removes
 * the form from the document again right afterwards.
 */
export interface FormSubmit
{
	form : HTMLFormElement;
	action : string;
	method : string;
	target : string;
	/** the form's inputs as name/value pairs, in document order */
	params : [string, string][];
	/** was the form part of the document when it got submitted? */
	connected : boolean;
}

export async function createEgwOpenEnv(prefs : object = {}) : Promise<EgwOpenEnv>
{
	const base = await createEgwCoreEnv(Object.assign({webserverUrl: 'https://example.test'}, prefs));
	const env = base as EgwOpenEnv;

	await loadScript(env.window.document, '/vendor/bower-asset/jquery/dist/jquery.min.js');

	env.egw.extend('debug', env.egw.MODULE_GLOBAL, () => ({
		debug: () => {},
		debug_level: () => 0
	}));

	env.jsonCalls = [];

	env.stubs = {
		link_get_registry: sinon.stub(),
		link: sinon.stub().callsFake((url : string, params? : any) =>
			url + (params && Object.keys(params).length ? '?' + new URLSearchParams(params).toString() : '')),
		mime_open: sinon.stub(),
		get_mime_info: sinon.stub(),
		link_app_list: sinon.stub().returns({}),
		lang: sinon.stub().callsFake((msg : string) => msg),
		preference: sinon.stub().returns(undefined),
		set_preference: sinon.stub(),
		callFunc: sinon.stub(),
		message: sinon.stub(),
		// egw_message.js's app_name(), not loaded here; used by openWithinWindow()
		app_name: sinon.stub().returns('infolog')
	};

	await loadScript(env.window.document, '/api/js/jsapi/egw_utils.ts', 'module');

	env.egw.extend('links-stub', env.egw.MODULE_GLOBAL, () => ({
		link_get_registry: env.stubs.link_get_registry,
		link: env.stubs.link,
		mime_open: env.stubs.mime_open,
		get_mime_info: env.stubs.get_mime_info,
		link_app_list: env.stubs.link_app_list,
		lang: env.stubs.lang,
		preference: env.stubs.preference,
		set_preference: env.stubs.set_preference,
		callFunc: env.stubs.callFunc,
		message: env.stubs.message,
		app_name: env.stubs.app_name,
		json: (menuaction : string, parameters : any[], callback? : Function) =>
		{
			env.jsonCalls.push({menuaction, parameters, callback});
			return {sendRequest: () => {}};
		}
	}));

	(env.window as any).egwIsMobile = () => null;

	const HTMLElementInWindow : any = (env.window as any).HTMLElement;
	const Et2DialogStub : any = class extends HTMLElementInWindow
	{
		appname : string;
		attrs : any;
		constructor(appname? : string)
		{
			super();
			this.appname = appname;
		}
		transformAttributes(attrs : any)
		{
			this.attrs = attrs;
			Object.assign(this, attrs);
		}
	};
	Et2DialogStub.show_dialog = sinon.stub();
	Et2DialogStub.BUTTONS_OK = 'ok';
	Et2DialogStub.WARNING_MESSAGE = 'warning';
	Et2DialogStub.BUTTONS_YES_NO_CANCEL = 'yes_no_cancel';
	Et2DialogStub.YES_BUTTON = 'yes';
	Et2DialogStub.NO_BUTTON = 'no';
	Et2DialogStub.CANCEL_BUTTON = 'cancel';
	env.window.customElements.define('et2-dialog-open-stub', Et2DialogStub);
	(env.window as any).Et2Dialog = Et2DialogStub;

	env.window.open = sinon.stub() as any;

	// Capture form.submit() instead of letting the browser navigate/open anything.
	env.formSubmits = [];
	(<any>env.window).HTMLFormElement.prototype.submit = function(this : HTMLFormElement)
	{
		env.formSubmits.push({
			form: this,
			action: this.getAttribute('action'),
			method: this.getAttribute('method'),
			target: this.getAttribute('target'),
			params: Array.from(this.querySelectorAll('input')).map(input => [input.name, input.value]),
			connected: this.isConnected
		});
	};

	// What egw.js's bootstrap normally sets before any window-local module runs.
	env.egw.top = env.window;

	await loadScript(env.window.document, '/api/js/jsapi/egw_open.ts', 'module');

	return env;
}

function loadScript(doc : Document, src : string, type? : string) : Promise<void>
{
	return new Promise((resolve, reject) =>
	{
		const script = doc.createElement('script');
		if (type) script.type = type;
		script.src = src;
		script.onload = () => resolve();
		script.onerror = () => reject(new Error('Failed to load '+src));
		doc.head.appendChild(script);
	});
}
