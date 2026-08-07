/**
 * Test harness for the "message" module (egw_message.js).
 *
 * Loads the REAL, unmodified egw_message.js on top of the Layer 1 core
 * environment. This module is heavily tied to the app framework (toast
 * messages, EgwApp push/refresh observers, page navigation as a last
 * resort) - rather than loading the real framework/EgwApp class, tests
 * provide minimal stand-ins for the handful of globals egw_message.js
 * reads directly (not through the composition engine):
 *
 * - `egw.top` - normally set by egw.js's bootstrap (`window.egw.top =
 *   window`); message()'s "delegate to top window" branch depends on it.
 * - `window.egw_getFramework()` / bare `framework` - real implementations
 *   live in jsapi.js / kdots's EgwFramework; a minimal stand-in here just
 *   returns `window.framework` if a test sets one.
 * - `window.EgwApp` - real one is EgwApp's static Symbol.iterator over all
 *   instances (see egw_app.ts); a plain array works identically for
 *   `for (const x of window.EgwApp)`.
 * - `window.egw_appWindow` - only exercised when a test passes _targetapp.
 *
 * egw_message.js has a side-effect-only `import './egw_json.js'` (just for
 * registerJSONPlugin, which this harness provides directly) - redirected
 * to the empty stub, same as EgwDataHarness/EgwJsonHarness.
 *
 * message()'s no-framework DOM fallback creates a real `<egw-message>`
 * element and awaits its `.updateComplete` before calling `.toast()` - both
 * only exist once the real (Lit-based) web component is defined. Rather
 * than loading that whole component, a minimal custom element satisfying
 * just that shape is registered here.
 */
import {createEgwCoreEnv, EgwCoreEnv} from "./EgwCoreHarness";

export interface EgwMessageEnv extends EgwCoreEnv
{
}

export async function createEgwMessageEnv(prefs : object = {}) : Promise<EgwMessageEnv>
{
	const base = await createEgwCoreEnv(prefs);
	const env = base as EgwMessageEnv;

	await loadScript(env.window.document, '/vendor/bower-asset/jquery/dist/jquery.min.js');

	env.egw.extend('debug', env.egw.MODULE_GLOBAL, () => ({
		debug: () => {},
		debug_level: () => 0
	}));

	env.egw.extend('json', env.egw.MODULE_WND_LOCAL, () => ({
		registerJSONPlugin: () => {},
		unregisterJSONPlugin: () => {}
	}));

	// What egw.js's bootstrap normally sets before any window-local module runs.
	env.egw.top = env.window;

	(env.window as any).egw_getFramework = () => (env.window as any).framework || null;
	(env.window as any).EgwApp = [];
	(env.window as any).egw_appWindow = (_app : string) => env.window;

	if (!env.window.customElements.get('egw-message'))
	{
		const HTMLElementInWindow : any = (env.window as any).HTMLElement;
		const PromiseInWindow : any = (env.window as any).Promise;
		const EgwMessageStub = class extends HTMLElementInWindow
		{
			message : string;
			type : string;
			toast() {}
			get updateComplete() { return PromiseInWindow.resolve(true); }
		};
		env.window.customElements.define('egw-message', EgwMessageStub as any);
	}

	const importMap = env.window.document.createElement('script');
	importMap.type = 'importmap';
	importMap.textContent = JSON.stringify({
		imports: {'/api/js/jsapi/egw_json.ts': '/api/js/jsapi/test/EgwJsStub.ts'}
	});
	env.window.document.head.appendChild(importMap);

	await loadScript(env.window.document, '/api/js/jsapi/egw_message.ts', 'module');

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
