/**
 * Test harness for the "files" module (egw_files.js) - MODULE_WND_LOCAL.
 *
 * On instantiation, egw_files.js scans the target window's document for
 * already-present <script>/<link type="text/css"> tags to seed its
 * "already included" list - so tests populate the iframe's <head> BEFORE
 * calling egw(app, win) for the first time, then assert via included().
 *
 * jQuery is loaded for real (the module uses jQuery() to scan for tags).
 * `egw.legacy_js_regexp` is seeded to match production (set by egw.js's
 * bootstrap in real usage, not loaded here) since includeJS() depends on it.
 *
 * includeJS()'s actual dynamic import() resolution is NOT exercised here -
 * intercepting native dynamic import in a test is impractical, consistent
 * with this project's precedent of not testing script/module execution
 * paths directly (see EgwJson.test.ts's 'js'/'html' response plugins).
 */
import {createEgwCoreEnv, EgwCoreEnv} from "./EgwCoreHarness";

export interface EgwFilesEnv extends EgwCoreEnv
{
}

export async function createEgwFilesEnv(prefs : object = {}) : Promise<EgwFilesEnv>
{
	const base = await createEgwCoreEnv(Object.assign({
		webserverUrl: 'https://example.test',
		legacy_js_regexp: /\/dhtmlx|jquery-ui|^etemplate\/|^phpbrain\/|^phpgwapi\//
	}, prefs));
	const env = base as EgwFilesEnv;

	await loadScript(env.window.document, '/vendor/bower-asset/jquery/dist/jquery.min.js');

	env.egw.extend('debug', env.egw.MODULE_GLOBAL, () => ({
		debug: () => {},
		debug_level: () => 0
	}));

	await loadScript(env.window.document, '/api/js/jsapi/egw_files.js', 'module');

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
