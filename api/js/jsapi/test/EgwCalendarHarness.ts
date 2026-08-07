/**
 * Test harness for the "calendar" module (egw_calendar.js) - MODULE_GLOBAL.
 *
 * holidays() does a real `window.fetch()` - stubbed here via `env.fetchStub`
 * so tests control the response instead of hitting a real server.
 * getTimezoneOffset()/week_start() read egw.preference(), so a minimal
 * 'preferences' stub is provided (not the real egw_preferences.js, which
 * would need a real network layer for its own tests - out of scope here).
 */
import {createEgwCoreEnv, EgwCoreEnv} from "./EgwCoreHarness";
import * as sinon from "sinon";

export interface EgwCalendarEnv extends EgwCoreEnv
{
	fetchStub : sinon.SinonStub;
	prefs : {[name : string] : any};
}

export async function createEgwCalendarEnv(prefs : object = {}) : Promise<EgwCalendarEnv>
{
	const base = await createEgwCoreEnv(Object.assign({webserverUrl: 'https://example.test'}, prefs));
	const env = base as EgwCalendarEnv;

	env.prefs = {};

	env.egw.extend('debug', env.egw.MODULE_GLOBAL, () => ({
		debug: () => {},
		debug_level: () => 0
	}));

	env.egw.extend('prefs-stub', env.egw.MODULE_GLOBAL, () => ({
		preference: (name : string) => env.prefs[name],
		config: () => undefined
	}));

	env.egw.extend('links-stub', env.egw.MODULE_GLOBAL, () => ({
		link: (url : string, params? : any) =>
			url + (params && Object.keys(params).length ? '?' + new URLSearchParams(params).toString() : '')
	}));

	env.fetchStub = sinon.stub();
	(env.window as any).fetch = env.fetchStub;

	await loadScript(env.window.document, '/api/js/jsapi/egw_calendar.js', 'module');

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
