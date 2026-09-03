/**
 * Globals that have to exist BEFORE addressbook/js/app.ts (and the api/ import chain it pulls in) is
 * evaluated - import this module ahead of "../app", ESM evaluates imports in declaration order.
 *
 * Mirrors mail/js/test/MailAppImportStub.ts and calendar/js/test/CalendarAppImportStub.ts - the requirements
 * are not app-specific, they come from egw.js's bootstrap and the etemplate2/Et2* import chain every app.ts pulls in:
 * - `app.classes`: app.ts registers itself with `app.classes.addressbook = AddressbookApp` at module scope.
 * - `framework`: without one, egw.js's bootstrap appends "cd=yes" to window.location.search to go
 *   get a framework, reloading the page out from under the test runner. setSidebox() gets called
 *   during that same bootstrap.
 * - `jQuery`: egw.js's bootstrap uses it (page-generation-time display, popup resize), and
 *   Et2Avatar's bundled cropper registers itself on jQuery.fn at import time. A chainable no-op
 *   covers both; app.ts's own jQuery uses are all inside methods, none of which run here.
 *   `.attr()` is the one call in that bootstrap whose return value is actually read (egw.js's own
 *   `jQuery('#late-sidebox').attr('data-setSidebox')`) rather than just chained/discarded - the
 *   chainable proxy answering with itself there made `sidebox` a truthy non-string, which then
 *   crashed `JSON.parse(sidebox)` trying to coerce the proxy to a string ("Cannot convert object
 *   to primitive value" / "its [Symbol.toPrimitive] method returned an object", the same CI-only
 *   bug fixed for mail/js/test/MailAppImportStub.ts by 346c1b2b9c). `.attr()` alone returns
 *   `undefined`, matching real jQuery's "selector matched nothing" behavior, so that
 *   `if (... && sidebox && ...)` check stays falsy.
 * - `egw.prefsOnly`: the switch egw_core.ts wants to see to build the REAL egw object around the
 *   test runner's own stub (which it keeps, merged in) instead of leaving the stub as-is - without
 *   it there is no egw.extend() for the api/ modules to register themselves on.
 * - `egw.registerJSONPlugin`: called at etemplate2.ts module scope.
 * - `egw.user`: read at module scope somewhere in the et2 import chain (calendar's view widget
 *   does it for an attribute default); harmless to provide everywhere.
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
			if(prop === 'attr') return () => undefined;
			return () => chainable;
		}
	});
	globals.jQuery = globals.$ = chainable;
}

if(globals.egw)
{
	globals.egw.prefsOnly = true;
	globals.egw.registerJSONPlugin = globals.egw.registerJSONPlugin ?? (() => {});
	globals.egw.user = globals.egw.user ?? ((_field : string) => _field === 'account_id' ? 1 : null);
}

export {};
