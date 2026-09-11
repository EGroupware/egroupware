/**
 * Test harness for egw_tail.js - NOT an egw.extend() module. It's a
 * standalone `DOMContentLoaded` page-controller script for admin's log-tail
 * page, wiring up buttons and polling egw.json() for new log content.
 *
 * The harness's iframe document has already fully loaded (past its own
 * native DOMContentLoaded) by the time egw_tail.ts's `<script type=module>`
 * is injected and registers its listener - so that listener never fires on
 * its own. Tests must dispatch a synthetic 'DOMContentLoaded' event on
 * `env.window.document` once the fixture DOM and stubs are in place.
 *
 * egw_tail.js imports egw_json.js, which itself has a side-effect-only
 * import of egw.js - the enormous, heavily side-effecting page bootstrap
 * (DOM script-tag attributes, jQuery, dynamic legacy includes, websocket/
 * popup handling) that's explicitly out of scope for this migration and
 * assumes a real EGroupware page (`#egw_script_id`, etc) it doesn't have
 * here. Both are redirected via an import map to the existing EgwJsStub.ts
 * (already used by EgwDataHarness for the same reason) - `egw.json` is then
 * assigned a fully test-controlled stub directly, since there's no PHP
 * backend in this test environment to answer real AJAX calls, and letting
 * refresh_log()'s recursive window.setTimeout loop run for real would make
 * tests slow/flaky. `lang()`/`open_link()` are provided by a small
 * MODULE_GLOBAL stub, mirroring EgwOpenHarness's approach.
 */
import {createEgwCoreEnv, EgwCoreEnv} from "./EgwCoreHarness";

export interface JsonCall
{
	menuaction : string;
	parameters : any[];
	callback : Function | undefined;
}

export interface EgwTailEnv extends EgwCoreEnv
{
	jsonCalls : JsonCall[];
	openLinkCalls : string[];
	log : HTMLElement;
	clearLogBtn : HTMLElement;
	purgeLogBtn : HTMLElement;
	emptyLogBtn : HTMLElement;
	downloadLogBtn : HTMLElement;
	fireDomContentLoaded() : void;
}

export async function createEgwTailEnv(options : {filename? : string} = {}) : Promise<EgwTailEnv>
{
	const base = await createEgwCoreEnv({webserverUrl: 'https://example.test'});
	const env = base as EgwTailEnv;

	const doc = env.window.document;
	env.log = doc.createElement('pre');
	env.log.id = 'log';
	if (typeof options.filename !== 'undefined')
	{
		env.log.setAttribute('data-filename', options.filename);
	}
	doc.body.appendChild(env.log);

	env.clearLogBtn = doc.createElement('et2-button');
	env.clearLogBtn.id = 'clear_log';
	env.purgeLogBtn = doc.createElement('et2-button');
	env.purgeLogBtn.id = 'purge_log';
	env.emptyLogBtn = doc.createElement('et2-button');
	env.emptyLogBtn.id = 'empty_log';
	env.downloadLogBtn = doc.createElement('et2-button');
	env.downloadLogBtn.id = 'download_log';
	doc.body.append(env.clearLogBtn, env.purgeLogBtn, env.emptyLogBtn, env.downloadLogBtn);

	(env.window as any).egw_getWindowInnerWidth = () => 1000;
	(env.window as any).egw_getWindowInnerHeight = () => 800;

	env.jsonCalls = [];
	env.openLinkCalls = [];

	env.egw.extend('tail-test-stub', env.egw.MODULE_GLOBAL, () => ({
		lang: (msg : string) => msg,
		open_link: (url : string) => { env.openLinkCalls.push(url); },
		json: (menuaction : string, parameters : any[], callback? : Function) =>
		{
			env.jsonCalls.push({menuaction, parameters, callback});
			return {sendRequest: () => {}};
		}
	}));

	const importMap = doc.createElement('script');
	importMap.type = 'importmap';
	importMap.textContent = JSON.stringify({
		imports: {
			'/api/js/jsapi/egw.js': '/api/js/jsapi/test/EgwJsStub.ts',
			'/api/js/jsapi/egw_json.ts': '/api/js/jsapi/test/EgwJsStub.ts'
		}
	});
	doc.head.appendChild(importMap);

	await loadScript(doc, '/api/js/jsapi/egw_tail.ts', 'module');

	env.fireDomContentLoaded = () =>
	{
		doc.dispatchEvent(new (env.window as any).Event('DOMContentLoaded', {bubbles: true, cancelable: true}));
	};

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
