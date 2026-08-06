/**
 * Test harness for the remaining MODULE_GLOBAL modules: egw_preferences.js,
 * egw_user.js, egw_config.js, egw_lang.js, egw_images.js, egw_links.js.
 *
 * These are lower priority per the test plan (mostly plain getter/setter
 * state with some genuinely interesting logic sprinkled in - sanitizeApp,
 * image lookup precedence, mime-type matching, url building) and none of
 * them depend on each other, so they share one harness and one fake
 * network layer instead of six near-identical ones.
 *
 * `jsonq()`/`request()`/`json()` are stubbed here, NOT loaded for real -
 * the real network layer already has its own thorough Layer 2 coverage
 * (EgwJson.test.ts); these modules only need something to call and record
 * the call, not a working transport.
 */
import {createEgwCoreEnv, EgwCoreEnv} from "./EgwCoreHarness";

export interface JsonqCall
{
	menuaction : string;
	parameters : any[];
	callback : Function | undefined;
}

export interface RequestCall
{
	menuaction : string;
	parameters : any[];
	resolve(data : any) : void;
	reject(err : any) : void;
}

export interface EgwMiscEnv extends EgwCoreEnv
{
	jsonqCalls : JsonqCall[];
	requestCalls : RequestCall[];
}

export async function createEgwMiscEnv(prefs : object = {}) : Promise<EgwMiscEnv>
{
	const base = await createEgwCoreEnv(Object.assign({webserverUrl: 'https://example.test'}, prefs));
	const env = base as EgwMiscEnv;
	env.jsonqCalls = [];
	env.requestCalls = [];

	await loadScript(env.window.document, '/vendor/bower-asset/jquery/dist/jquery.min.js');

	env.egw.extend('debug', env.egw.MODULE_GLOBAL, () => ({
		debug: () => {},
		debug_level: () => 0
	}));

	env.egw.extend('network-stub', env.egw.MODULE_GLOBAL, () => ({
		jsonq: (menuaction : string, parameters : any[], callback? : Function) : Promise<any> =>
		{
			env.jsonqCalls.push({menuaction, parameters, callback});
			return new Promise(() => {}); // deliberately never resolves; tests inspect the recorded call instead
		},
		json: () => ({sendRequest: () => {}}),
		request: (menuaction : string, parameters : any[]) : Promise<any> =>
			new Promise((resolve, reject) => env.requestCalls.push({menuaction, parameters, resolve, reject})),
		// getAppName() (egw_core.js) unconditionally calls this.app_name(), which
		// is normally provided by egw_message.js - not loaded here.
		app_name: () => null
	}));

	for (const file of ['egw_preferences.js', 'egw_user.js', 'egw_config.js', 'egw_lang.js', 'egw_images.js', 'egw_links.js'])
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
