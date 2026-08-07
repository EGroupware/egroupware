/**
 * Test harness for egw_preferences.js, egw_user.js and egw_links.js,
 * loaded together (for real, unmodified) since they genuinely depend on
 * each other in production: link_get_registry()'s icon default calls
 * this.app() (user.js), file_editor_prefered_mimes() calls
 * this.link_get_registry() (links.js) and this.preference() (preferences.js),
 * link_app_list() calls both this.app() and this.lang() (config/lang, also
 * loaded here for that reason).
 *
 * Unlike EgwMiscHarness (the earlier, shallower pass over these same
 * modules), `json()`/`request()`/`jsonq()` here are real enough to drive
 * the async/pending-load code paths, not just record-and-ignore stubs:
 *
 * - `json()` returns a request whose sendRequest() returns a controllable
 *   Promise. Responding to it also calls the real egw.set_preferences()/
 *   set_lang_arr() etc. first - mirroring what the server's "apply" JSON
 *   response plugin actually does in production (egw_json.js dispatches
 *   an 'apply' response BEFORE the sendRequest() promise itself resolves,
 *   which is why preference()'s `promise.then(() => this.preference(...))`
 *   re-read works at all - see EgwPreferences.test.ts for why this matters).
 * - `request()` (used by user.js's accounts()/accountData()) similarly
 *   returns a controllable Promise, recorded for inspection.
 * - `jsonq()` (used by set_preference(), link_title()) records calls
 *   without a working transport - already covered by EgwJson.test.ts.
 *
 * All three build their Promises via `env.window`'s own Promise constructor,
 * not the bare `Promise` global: this harness file's own code runs in the
 * TOP test page's realm, but accountData()'s pending-merge logic does a real
 * `instanceof Promise` check (not just duck-typing `.then`) against the
 * IFRAME's own Promise constructor - a promise built with the wrong
 * realm's constructor silently fails that check and never gets unwrapped.
 * (Confirmed by writing this harness once with the bare `Promise` first;
 * accountData()'s own "second pending call" test failed until fixed here.)
 */
import {createEgwCoreEnv, EgwCoreEnv} from "./EgwCoreHarness";

export interface JsonCall
{
	menuaction : string;
	parameters : any[];
	callback : Function | undefined;
	context : any;
	/** Simulate the server responding: applies `_data` via set_preferences() before resolving, like the real 'apply' plugin would */
	respond(data : any) : void;
}

export interface RequestCall
{
	menuaction : string;
	parameters : any[];
	resolve(data : any) : void;
	reject(err : any) : void;
}

export interface JsonqCall
{
	menuaction : string;
	parameters : any[];
	callback : Function | undefined;
	/** Simulate the real ~100ms batching timer firing: calls callbeforesend(parameters) now, like egw_jsonq.js's jsonq_send() would */
	flush() : void;
	/** Simulate the server responding, resolving the jsonq() promise */
	respond(data : any) : void;
}

export interface EgwLinksPrefsUserEnv extends EgwCoreEnv
{
	jsonCalls : JsonCall[];
	requestCalls : RequestCall[];
	jsonqCalls : JsonqCall[];
	/** app_name()'s stubbed return value - settable per test, defaults to null */
	currentAppName : string | null;
	openLinkCalls : any[][];
	linkHandlerCalls : any[][];
}

export async function createEgwLinksPrefsUserEnv(prefs : object = {}) : Promise<EgwLinksPrefsUserEnv>
{
	const base = await createEgwCoreEnv(Object.assign({webserverUrl: 'https://example.test'}, prefs));
	const env = base as EgwLinksPrefsUserEnv;
	env.jsonCalls = [];
	env.requestCalls = [];
	env.jsonqCalls = [];
	env.currentAppName = null;
	env.openLinkCalls = [];
	env.linkHandlerCalls = [];

	await loadScript(env.window.document, '/vendor/bower-asset/jquery/dist/jquery.min.js');

	env.egw.extend('debug', env.egw.MODULE_GLOBAL, () => ({
		debug: () => {},
		debug_level: () => 0
	}));

	env.egw.extend('network-stub', env.egw.MODULE_GLOBAL, () => ({
		// getAppName() needs this; real one is egw_message.js, not loaded here.
		// Settable per test via env.currentAppName (used by show_preferences()).
		app_name: () => env.currentAppName,
		open_link: (...args : any[]) => { env.openLinkCalls.push(args); },
		link_handler: (...args : any[]) => { env.linkHandlerCalls.push(args); },
		json: (menuaction : string, parameters : any[], callback? : Function, context? : any) =>
		{
			return {
				sendRequest: () : Promise<any> =>
				{
					let resolveFn : (v : any) => void;
					const promise = new (env.window as any).Promise(resolve => { resolveFn = resolve; });
					env.jsonCalls.push({
						menuaction, parameters, callback, context,
						respond(data : any)
						{
							// mirror the real "apply" JSON response plugin, which runs
							// BEFORE sendRequest()'s own promise settles
							if (menuaction.includes('ajax_get_preference'))
							{
								env.egw().set_preferences(data, parameters[0]);
							}
							if (typeof callback === 'function') callback.call(context, data);
							resolveFn(data);
						}
					});
					return promise;
				}
			};
		},
		request: (menuaction : string, parameters : any[]) : Promise<any> =>
			new (env.window as any).Promise((resolve, reject) => env.requestCalls.push({menuaction, parameters, resolve, reject})),
		jsonq: (menuaction : string, parameters : any[], callback? : Function, sender? : any, callbeforesend? : Function) : Promise<any> =>
		{
			let resolveFn : (v : any) => void;
			const promise = new (env.window as any).Promise(resolve => { resolveFn = resolve; });
			const call : JsonqCall = {
				menuaction, parameters, callback,
				flush()
				{
					// mirrors egw_jsonq.js's jsonq_send(): callbeforesend runs
					// only once the batch is actually about to be sent, NOT at
					// registration time - link_title() relies on that gap to
					// batch multiple ids into a single request.
					if (typeof callbeforesend === 'function') callbeforesend.call(sender, parameters);
				},
				respond(data : any)
				{
					if (typeof callback === 'function') callback.call(sender, data);
					resolveFn(data);
				}
			};
			env.jsonqCalls.push(call);
			return promise;
		}
	}));

	// bare global used by preferences.show_preferences()'s "not supported" branch
	(env.window as any).egw_message = () => {};

	for (const file of ['egw_preferences.ts', 'egw_user.ts', 'egw_config.ts', 'egw_lang.ts', 'egw_links.ts'])
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
