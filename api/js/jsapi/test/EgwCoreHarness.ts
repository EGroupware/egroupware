/**
 * Test harness for the egw_core.js composition engine
 *
 * egw_core.js is a legacy, non-module IIFE: it builds `window.egw` once, at
 * script-evaluation time, and keeps all module/instance state in closures
 * over that window. It cannot be "reset" between tests in the same window,
 * the way a class or a module with exported state normally would be.
 *
 * Real usage already relies on exactly this property - every popup, tab and
 * iframe gets its own independent copy of the engine - so tests get their
 * isolation the same way: each test loads a fresh copy of egw_core.js into
 * a throwaway iframe, seeded beforehand with the "prefsOnly" stub that
 * Api\Framework::header() leaves behind server-side for the real bootstrap
 * (see api/js/jsapi/egw_core.js, the `window.egw.prefsOnly` check).
 *
 * Only egw_core.js itself is loaded here - no egw.js, no jQuery, no other
 * egw_*.js module. That keeps these tests focused purely on the composition
 * engine's contract (instance identity/caching, module merge rules,
 * cleanup, ...), independent of any single feature module's behaviour.
 */

export interface EgwCoreEnv
{
	/** The iframe's own window - egw_core.js was loaded into this realm */
	window : Window;

	/** window.egw itself - i.e. the `egw` function/object, not an instance returned by calling it */
	egw : any;

	/**
	 * Create a second, distinct Window object living inside this env, for
	 * window-scoped (MODULE_WND_LOCAL) tests. It does NOT get its own copy
	 * of egw_core.js - the composition engine only uses it as an identity
	 * key and as the `_wnd` a module factory is called with, neither of
	 * which requires anything to be loaded into it.
	 */
	createWindow() : Window;

	/** Remove the iframe(s) and let the browser tear down their realms */
	destroy() : void;
}

/**
 * @param prefs extra properties to seed onto the initial `window.egw` stub,
 *   merged the same way egw_core.js merges "prefs" into the real object
 *   (eg. `{webserverUrl: '...'}`)
 */
export async function createEgwCoreEnv(prefs : object = {}) : Promise<EgwCoreEnv>
{
	const iframe = document.createElement('iframe');
	// Browsers replace an iframe's initial document asynchronously after
	// insertion (a second, real "about:blank" navigation) - writing to
	// contentDocument/contentWindow before that settles can silently be
	// discarded (observed as the harness hanging in Firefox). Setting
	// `src` explicitly and waiting for `load` sidesteps that.
	iframe.src = 'about:blank';
	const ready = new Promise<void>(resolve => iframe.addEventListener('load', () => resolve(), {once: true}));
	document.body.appendChild(iframe);
	await ready;

	const win = <any>iframe.contentWindow;
	const childWindows : HTMLIFrameElement[] = [];

	// What Api\Framework::header() leaves behind server-side for egw_core.js
	// to pick up and turn into the real composition engine.
	win.egw = Object.assign({prefsOnly: true}, prefs);

	await loadScript(iframe.contentDocument, '/api/js/jsapi/egw_core.js');

	return {
		window: win,
		egw: win.egw,
		createWindow()
		{
			const child = document.createElement('iframe');
			win.document.body.appendChild(child);
			childWindows.push(child);
			return child.contentWindow as Window;
		},
		destroy()
		{
			childWindows.forEach(child => child.remove());
			iframe.remove();
		}
	};
}

function loadScript(doc : Document, src : string) : Promise<void>
{
	return new Promise((resolve, reject) =>
	{
		const script = doc.createElement('script');
		script.src = src;
		script.onload = () => resolve();
		script.onerror = () => reject(new Error('Failed to load '+src));
		doc.head.appendChild(script);
	});
}
