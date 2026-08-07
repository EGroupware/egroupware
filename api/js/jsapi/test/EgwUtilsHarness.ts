/**
 * Test harness for the "utils" module (egw_utils.js) - MODULE_GLOBAL.
 *
 * storeWindow()/getOpenWindows()/windowClosed() call `this.getSessionItem`/
 * `this.setSessionItem` - contributed by egw_store.js, a separate
 * MODULE_GLOBAL module - so the real egw_store.ts is loaded here too (both
 * merge into the same global instance). jQuery is loaded for real, used by
 * getHiddenDimensions(). A minimal `debug` stub is registered since
 * decodePath()'s catch branch calls `egw.debug('error', ...)`.
 */
import {createEgwCoreEnv, EgwCoreEnv} from "./EgwCoreHarness";

export interface EgwUtilsEnv extends EgwCoreEnv
{
	debugCalls : any[][];
}

export async function createEgwUtilsEnv(prefs : object = {}) : Promise<EgwUtilsEnv>
{
	const base = await createEgwCoreEnv(Object.assign({webserverUrl: 'https://example.test'}, prefs));
	const env = base as EgwUtilsEnv;

	await loadScript(env.window.document, '/vendor/bower-asset/jquery/dist/jquery.min.js');

	env.debugCalls = [];
	env.egw.extend('debug', env.egw.MODULE_GLOBAL, () => ({
		debug: (...args : any[]) => { env.debugCalls.push(args); },
		debug_level: () => 0
	}));

	await loadScript(env.window.document, '/api/js/jsapi/egw_store.ts', 'module');
	await loadScript(env.window.document, '/api/js/jsapi/egw_utils.js', 'module');

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
