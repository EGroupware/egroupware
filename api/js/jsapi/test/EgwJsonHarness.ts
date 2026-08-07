/**
 * Test harness for the "json"/"jsonq" modules (egw_json.js, egw_jsonq.js).
 *
 * Loads the REAL, unmodified egw_json.js and egw_jsonq.js on top of the
 * Layer 1 core environment (see EgwCoreHarness for why an iframe per test).
 * Three things they need that aren't part of the composition engine:
 *
 * 1. jQuery and egw_utils.js (for `ajaxUrl`) - real runtime dependencies,
 *    loaded/imported for real since they're lightweight and self-contained
 *    (egw_utils.js only imports egw_core.js, already loaded).
 * 2. `egw.debug` - same no-op stand-in used by the data.js harness.
 * 3. `window.fetch` - json_request.sendRequest()'s async path calls the
 *    window's real fetch(). A controllable fake replaces it so tests never
 *    hit the network, while still exercising the real request/response
 *    handling code around it.
 * 4. egw_json.js has a side-effect-only `import './egw.js'` (just to
 *    guarantee `window.egw` exists, which this harness already
 *    guarantees). Redirected via a per-document import map to an empty
 *    stub, same as EgwDataHarness - see EgwJsStub.ts.
 * 5. A handful of the built-in response plugins (message/css/redirect) call
 *    methods from OTHER modules (egw_message.js's message(), egw_files.js's
 *    includeCSS()) or bare globals (egw_insertJS, egw_appWindowOpen) not
 *    loaded here - stubbed as sinon spies via `env.stubs.*` so tests can
 *    assert on them without pulling in those modules' own dependencies.
 */
import {createEgwCoreEnv, EgwCoreEnv} from "./EgwCoreHarness";
import * as sinon from "sinon";

export interface FakeFetchCall
{
	url : string;
	init : any;
	resolve(json : any) : void;
	resolveNotOk(status : number, body? : any) : void;
	reject(err : any) : void;
}

export interface EgwJsonEnv extends EgwCoreEnv
{
	/** every window.fetch() call made by json_request.sendRequest(), in order */
	fetchCalls : FakeFetchCall[];
	stubs : {
		message : sinon.SinonStub;
		includeCSS : sinon.SinonStub;
	};
}

export async function createEgwJsonEnv(prefs : object = {}) : Promise<EgwJsonEnv>
{
	const base = await createEgwCoreEnv(Object.assign({webserverUrl: 'https://example.test'}, prefs));
	const env = base as EgwJsonEnv;
	env.fetchCalls = [];

	await loadScript(env.window.document, '/vendor/bower-asset/jquery/dist/jquery.min.js');

	env.egw.extend('debug', env.egw.MODULE_GLOBAL, () => ({
		debug: () => {},
		debug_level: () => 0
	}));

	env.stubs = {
		message: sinon.stub(),
		includeCSS: sinon.stub()
	};
	env.egw.extend('stubs', env.egw.MODULE_GLOBAL, () => ({
		message: env.stubs.message,
		includeCSS: env.stubs.includeCSS,
		// handleError()'s user-facing message text; identity is enough here
		lang: (msg : string) => msg
	}));
	// bare globals the "assign"/"redirect" built-in plugins call, normally
	// defined in jsapi.js (not loaded here)
	(env.window as any).egw_insertJS = sinon.stub();
	(env.window as any).egw_appWindowOpen = sinon.stub();

	env.window.fetch = (url : string, init : any) : Promise<any> =>
	{
		return new Promise((resolve, reject) =>
		{
			env.fetchCalls.push({
				url, init,
				resolve(json : any)
				{
					resolve({ok: true, status: 200, headers: {get: () => null}, json: () => Promise.resolve(json)});
				},
				resolveNotOk(status : number, body : any = {})
				{
					resolve({
						ok: false, status, statusText: 'Error',
						headers: {get: (h : string) => h === 'Content-Type' ? 'application/json' : 'Thu, 1 Jan 1970 00:00:00 GMT'},
						json: () => Promise.resolve(body)
					});
				},
				reject
			});
		});
	};

	const importMap = env.window.document.createElement('script');
	importMap.type = 'importmap';
	importMap.textContent = JSON.stringify({
		imports: {'/api/js/jsapi/egw.js': '/api/js/jsapi/test/EgwJsStub.ts'}
	});
	env.window.document.head.appendChild(importMap);

	await loadScript(env.window.document, '/api/js/jsapi/egw_json.js', 'module');
	await loadScript(env.window.document, '/api/js/jsapi/egw_jsonq.js', 'module');

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
