/**
 * Test harness for egw_css.js ("css" module, MODULE_WND_LOCAL) and
 * egw_store.js ("store" module, MODULE_GLOBAL).
 *
 * Both are small, self-contained, and need nothing beyond a real DOM
 * (css.js creates/manipulates a <style> element; store.js reads/writes the
 * iframe's own sessionStorage/localStorage) - no jQuery, no network layer.
 */
import {createEgwCoreEnv, EgwCoreEnv} from "./EgwCoreHarness";

export interface EgwCssStoreEnv extends EgwCoreEnv
{
}

export async function createEgwCssStoreEnv(prefs : object = {}) : Promise<EgwCssStoreEnv>
{
	const base = await createEgwCoreEnv(Object.assign({webserverUrl: 'https://example.test'}, prefs));
	const env = base as EgwCssStoreEnv;

	for (const file of ['egw_css.ts', 'egw_store.ts'])
	{
		await loadScript(env.window.document, '/api/js/jsapi/'+file, 'module');
	}

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
