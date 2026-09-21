/**
 * Globals that have to exist BEFORE filemanager/js/filemanager.ts (and the api/ import chain it
 * pulls in) is evaluated - import this module ahead of "../filemanager", ESM evaluates imports in
 * declaration order.
 *
 * Mirrors addressbook/js/test/AddressbookAppImportStub.ts, calendar/js/test/CalendarAppImportStub.ts
 * and mail/js/test/MailAppImportStub.ts - the requirements are not app-specific, they come from
 * egw.js's bootstrap and the etemplate2/Et2* import chain every app's own js file pulls in:
 * - `app.classes`: filemanager.ts registers itself with `app.classes.filemanager = filemanagerAPP`
 *   at module scope.
 * - `framework`: without one, egw.js's bootstrap appends "cd=yes" to window.location.search to go
 *   get a framework, reloading the page out from under the test runner. setSidebox() gets called
 *   during that same bootstrap.
 * - `jQuery`: egw.js's bootstrap uses it (page-generation-time display, popup resize). A chainable
 *   no-op covers it; filemanager.ts's own jQuery uses are all inside methods, none of which run here.
 *   `.attr()` is the one call in that bootstrap whose return value is actually read (egw.js's own
 *   `jQuery('#late-sidebox').attr('data-setSidebox')`) rather than just chained/discarded - see
 *   AddressbookAppImportStub.ts's own comment for the CI-only crash this avoids.
 * - `egw.prefsOnly`: the switch egw_core.ts wants to see to build the REAL egw object around the
 *   test runner's own stub (which it keeps, merged in) instead of leaving the stub as-is - without
 *   it there is no egw.extend() for the api/ modules to register themselves on.
 * - `egw.registerJSONPlugin`: called at etemplate2.ts module scope.
 * - `egw.user`: read at module scope somewhere in the et2 import chain; harmless to provide
 *   everywhere.
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
