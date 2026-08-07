/**
 * Test harness for the "ready" module (egw_ready.js) - MODULE_WND_LOCAL.
 *
 * Loads the REAL, unmodified egw_ready.js (plus the lightweight, real
 * egw_utils.js for egw.uid(), used by readyWaitFor's token generation) on
 * top of the Layer 1 core environment.
 *
 * The module attaches its DOMContentLoaded/load listeners to a window at
 * the moment its 'ready' instance is first created for that window (see
 * EgwCoreHarness / Layer 1 for why: extend() eagerly instantiates
 * MODULE_WND_LOCAL factories for every window slot that already exists,
 * which includes the root window as soon as this file loads). By the time
 * that happens in a test, the iframe's own DOMContentLoaded/load have long
 * since fired for real, so they'll never fire again naturally - tests
 * simulate them with fireReadyEvent(), which dispatches a synthetic
 * DOMContentLoaded on the target window's document. That's enough: the
 * module doesn't care whether the event is "real", only that a listener
 * it registered gets invoked.
 */
import {createEgwCoreEnv, EgwCoreEnv} from "./EgwCoreHarness";

export interface EgwReadyEnv extends EgwCoreEnv
{
	/** Simulate the document's DOMContentLoaded event resolving the built-in 'readyEvent' token */
	fireReadyEvent(win? : Window) : void;
	/** Simulate the window's load event - the fallback listener, registered alongside DOMContentLoaded */
	fireLoadEvent(win? : Window) : void;
}

export async function createEgwReadyEnv(prefs : object = {}) : Promise<EgwReadyEnv>
{
	const base = await createEgwCoreEnv(prefs);
	const env = base as EgwReadyEnv;

	env.egw.extend('debug', env.egw.MODULE_GLOBAL, () => ({
		debug: () => {},
		debug_level: () => 0
	}));

	await loadScript(env.window.document, '/api/js/jsapi/egw_utils.ts', 'module');
	await loadScript(env.window.document, '/api/js/jsapi/egw_ready.ts', 'module');

	env.fireReadyEvent = (win : Window = env.window) =>
	{
		win.document.dispatchEvent(new Event('DOMContentLoaded'));
	};
	env.fireLoadEvent = (win : Window = env.window) =>
	{
		win.dispatchEvent(new Event('load'));
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
