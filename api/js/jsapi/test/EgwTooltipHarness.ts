/**
 * Test harness for the "tooltip" module (egw_tooltip.js) - MODULE_WND_LOCAL.
 *
 * Needs nothing beyond a real DOM (no jQuery, no network layer) - just the
 * bare `egwIsMobile()` global it consults to skip binding on mobile, stubbed
 * here to always report "not mobile" like EgwOpenHarness does.
 */
import {createEgwCoreEnv, EgwCoreEnv} from "./EgwCoreHarness";

export interface EgwTooltipEnv extends EgwCoreEnv
{
	setMobile(mobile : boolean) : void;
}

export async function createEgwTooltipEnv(prefs : object = {}) : Promise<EgwTooltipEnv>
{
	const base = await createEgwCoreEnv(Object.assign({webserverUrl: 'https://example.test'}, prefs));
	const env = base as EgwTooltipEnv;

	(env.window as any).egwIsMobile = () => null;
	env.setMobile = (mobile : boolean) => { (env.window as any).egwIsMobile = () => (mobile ? 'mobile' : null); };

	await loadScript(env.window.document, '/api/js/jsapi/egw_tooltip.js', 'module');

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
