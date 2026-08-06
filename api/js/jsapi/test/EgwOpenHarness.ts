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
 * Deliberately NOT exercised anywhere in the test suite built on this
 * harness: assigning to a real window's `location.href` (link_handler's
 * no-framework fallback) and openWithinWindow's multi-popup Et2Dialog
 * chooser / long-content form-POST paths - both would need either real
 * navigation or the full Et2Dialog web component to behave meaningfully.
 */
import {createEgwCoreEnv, EgwCoreEnv} from "./EgwCoreHarness";
import * as sinon from "sinon";

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
		callFunc : sinon.SinonStub;
		message : sinon.SinonStub;
	};
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

	env.stubs = {
		link_get_registry: sinon.stub(),
		link: sinon.stub().callsFake((url : string, params? : any) =>
			url + (params && Object.keys(params).length ? '?' + new URLSearchParams(params).toString() : '')),
		mime_open: sinon.stub(),
		get_mime_info: sinon.stub(),
		link_app_list: sinon.stub().returns({}),
		lang: sinon.stub().callsFake((msg : string) => msg),
		preference: sinon.stub().returns(undefined),
		callFunc: sinon.stub(),
		message: sinon.stub()
	};

	await loadScript(env.window.document, '/api/js/jsapi/egw_utils.js', 'module');

	env.egw.extend('links-stub', env.egw.MODULE_GLOBAL, () => ({
		link_get_registry: env.stubs.link_get_registry,
		link: env.stubs.link,
		mime_open: env.stubs.mime_open,
		get_mime_info: env.stubs.get_mime_info,
		link_app_list: env.stubs.link_app_list,
		lang: env.stubs.lang,
		preference: env.stubs.preference,
		callFunc: env.stubs.callFunc,
		message: env.stubs.message
	}));

	(env.window as any).egwIsMobile = () => null;
	(env.window as any).Et2Dialog = {
		show_dialog: sinon.stub(),
		BUTTONS_OK: 'ok',
		WARNING_MESSAGE: 'warning'
	};
	env.window.open = sinon.stub() as any;

	// What egw.js's bootstrap normally sets before any window-local module runs.
	env.egw.top = env.window;

	await loadScript(env.window.document, '/api/js/jsapi/egw_open.js', 'module');

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
