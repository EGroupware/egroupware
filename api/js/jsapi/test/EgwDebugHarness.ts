/**
 * Test harness for the "debug" module (egw_debug.js) - MODULE_GLOBAL.
 *
 * jQuery is loaded for real (used by show_log()'s jQuery-UI-dialog check
 * and the module-level global error handler setup). No jQuery UI is
 * loaded, matching a modern EGroupware install - which matters, since
 * show_log()'s dialog-rendering branch is entirely gated on
 * `window.jQuery.ui.dialog` existing.
 */
import {createEgwCoreEnv, EgwCoreEnv} from "./EgwCoreHarness";

export interface EgwDebugEnv extends EgwCoreEnv
{
}

export async function createEgwDebugEnv(prefs : object = {}) : Promise<EgwDebugEnv>
{
	const base = await createEgwCoreEnv(Object.assign({webserverUrl: 'https://example.test'}, prefs));
	const env = base as EgwDebugEnv;

	await loadScript(env.window.document, '/vendor/bower-asset/jquery/dist/jquery.min.js');

	await loadScript(env.window.document, '/api/js/jsapi/egw_debug.ts', 'module');

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
