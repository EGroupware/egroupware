/**
 * Test harness for the "data"/"data_storage" modules (egw_data.js).
 *
 * Loads the REAL, unmodified egw_data.js as an ES module on top of the same
 * egw_core.js environment Layer 1 uses (see EgwCoreHarness), so these tests
 * exercise production code rather than a re-implementation. Three things
 * egw_data.js needs that aren't part of the composition engine itself:
 *
 * 1. jQuery - egw_data.js calls jQuery.extend()/jQuery.inArray() directly
 *    (a real runtime dependency, not an ESM import - production loads it
 *    as a plain global script before any egw_*.js file). We load the real
 *    vendored jQuery the same way.
 * 2. `egw.debug` - normally provided by egw_debug.js's MODULE_GLOBAL
 *    'debug' module. egw_data.js calls it directly (cache-hit logging, and
 *    to report a misbehaving dataRegisterFetch callback that rejected
 *    instead of resolving false) - a no-op stand-in is enough here.
 * 3. `egw.registerJSONPlugin` / `egw.json` - normally provided by
 *    egw_json.js's MODULE_WND_LOCAL 'json' module. dataFetch() needs to
 *    reach the server and data_storage's factory registers a plugin at
 *    load time, but neither needs the real network/websocket machinery, so
 *    a minimal stand-in is registered here, with `json()` returning a
 *    controllable fake request instead of making real network calls.
 * 4. egw_data.js has side-effect-only `import './egw.js'` and
 *    `import './egw_json.js'` (just to guarantee `window.egw` and
 *    registerJSONPlugin exist before it calls egw.extend(...), both of
 *    which this harness already guarantees). Actually executing the real
 *    files would pull in DOM script-tag attributes, async include loading
 *    and popup/websocket handling that has nothing to do with data
 *    storage, so an import map redirects both specifiers to an empty stub
 *    (EgwJsStub.ts), scoped to this iframe's document only.
 */
import {createEgwCoreEnv, EgwCoreEnv} from "./EgwCoreHarness";

export interface FakeJsonCall
{
	menuaction : string;
	parameters : any[];
	sent : boolean;
	/** simulate the server answering this request */
	respond(result : any) : void;
}

export interface EgwDataEnv extends EgwCoreEnv
{
	/** every egw.json(...) call made by dataFetch()'s sendRequest(), in order */
	jsonCalls : FakeJsonCall[];
	/** respond to the most recently issued egw.json() call */
	respondToLastJsonCall(result : any) : void;
}

export async function createEgwDataEnv() : Promise<EgwDataEnv>
{
	const base = await createEgwCoreEnv();
	const env = base as EgwDataEnv;
	env.jsonCalls = [];

	await loadScript(env.window.document, '/vendor/bower-asset/jquery/dist/jquery.min.js');

	env.egw.extend('debug', env.egw.MODULE_GLOBAL, () => ({
		debug: () => {},
		debug_level: () => 0
	}));

	// Stand-in for the two egw_json.js entry points data.js actually calls.
	// Registered as MODULE_WND_LOCAL, same as the real 'json' module, so it
	// lands on the egw object itself exactly the way the real one does
	// (see Layer 1 / EgwCoreHarness for why: WND_LOCAL modules registered
	// before any popup window exists get merged onto the global instance,
	// which IS the egw object).
	env.egw.extend('json', env.egw.MODULE_WND_LOCAL, () => ({
		registerJSONPlugin: () => {},
		unregisterJSONPlugin: () => {},
		json: (menuaction : string, parameters : any[], callback : Function, context : any) =>
		{
			const call : FakeJsonCall = {
				menuaction,
				parameters,
				sent: false,
				respond(result : any) { callback.call(context, result); }
			};
			env.jsonCalls.push(call);
			return {sendRequest: () => { call.sent = true; }};
		}
	}));

	env.respondToLastJsonCall = (result : any) =>
	{
		const call = env.jsonCalls[env.jsonCalls.length - 1];
		if (!call) throw new Error('No egw.json() call to respond to');
		call.respond(result);
	};

	const importMap = env.window.document.createElement('script');
	importMap.type = 'importmap';
	importMap.textContent = JSON.stringify({
		imports: {
			'/api/js/jsapi/egw.js': '/api/js/jsapi/test/EgwJsStub.ts',
			'/api/js/jsapi/egw_json.js': '/api/js/jsapi/test/EgwJsStub.ts'
		}
	});
	env.window.document.head.appendChild(importMap);

	await loadScript(env.window.document, '/api/js/jsapi/egw_data.js', 'module');

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
