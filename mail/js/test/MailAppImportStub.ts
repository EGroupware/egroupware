/**
 * Globals that have to exist BEFORE mail/js/app.ts (and the api/ import chain it pulls in) is
 * evaluated - import this module ahead of "../app", ESM evaluates imports in declaration order.
 *
 * app.ts can't be imported at all without them, which is why the other mail tests only ever
 * `import type` it:
 * - `app.classes`: app.ts registers itself with `app.classes.mail = MailApp` at module scope.
 * - `framework`: without one, egw.js's bootstrap appends "cd=yes" to window.location.search to go
 *   get a framework, reloading the page out from under the test runner. setSidebox() gets called
 *   during that same bootstrap.
 * - `jQuery`: egw.js's bootstrap uses it (page-generation-time display, popup resize), and
 *   Et2Avatar's bundled cropper registers itself on jQuery.fn at import time. A chainable no-op
 *   covers both; app.ts's own jQuery uses are all inside methods, none of which run here.
 * - `egw.prefsOnly`: the switch egw_core.ts wants to see to build the REAL egw object around the
 *   test runner's own stub (which it keeps, merged in) instead of leaving the stub as-is - without
 *   it there is no egw.extend() for the api/ modules to register themselves on.
 * - `egw.registerJSONPlugin`: called at etemplate2.ts module scope.
 */
const globals : any = window;

globals.app = globals.app || {classes: {}};
globals.app.classes = globals.app.classes || {};
globals.framework = globals.framework || {setSidebox: () => {}};

if(!globals.jQuery)
{
	const fn : any = {};
	const chainable : any = new Proxy(function() { return chainable; }, {
		get: (target, prop) =>
		{
			if(prop === 'length') return 0;
			if(prop === 'fn') return fn;
			return () => chainable;
		}
	});
	globals.jQuery = globals.$ = chainable;
}

if(globals.egw)
{
	globals.egw.prefsOnly = true;
	globals.egw.registerJSONPlugin = globals.egw.registerJSONPlugin ?? (() => {});
}

export {};
