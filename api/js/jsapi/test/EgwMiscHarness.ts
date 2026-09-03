/**
 * Test harness for the remaining, self-contained MODULE_GLOBAL modules:
 * egw_config.js, egw_lang.js, egw_images.js.
 *
 * (egw_preferences.js, egw_user.js and egw_links.js moved to their own,
 * more thorough harness - see EgwLinksPrefsUserHarness.ts / EgwPreferences,
 * EgwUser and EgwLinks .test.ts - since they interact with each other and
 * with a real network layer. These three don't call json()/request()/
 * jsonq() in any code path exercised here, so no network stub is needed.)
 */
import {createEgwCoreEnv, EgwCoreEnv} from "./EgwCoreHarness";

export interface EgwMiscEnv extends EgwCoreEnv
{
}

export async function createEgwMiscEnv(prefs : object = {}, src? : string) : Promise<EgwMiscEnv>
{
	const base = await createEgwCoreEnv(Object.assign({webserverUrl: 'https://example.test'}, prefs), src);
	const env = base as EgwMiscEnv;

	await loadScript(env.window.document, '/vendor/bower-asset/jquery/dist/jquery.min.js');

	env.egw.extend('debug', env.egw.MODULE_GLOBAL, () => ({
		debug: () => {},
		debug_level: () => 0
	}));

	env.egw.extend('app-name-stub', env.egw.MODULE_GLOBAL, () => ({
		// getAppName() (egw_core.js) unconditionally calls this.app_name(), which
		// is normally provided by egw_message.js - not loaded here.
		app_name: () => null
	}));

	for (const file of ['egw_config.ts', 'egw_lang.ts', 'egw_images.ts'])
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
