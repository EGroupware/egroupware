/**
 * Imported (for its side effect only) before "../app" in NotificationsApp.test.ts.
 *
 * app.ts self-bootstraps on import (window.egw_ready.then(...) at the bottom of that file, since
 * unlike every other app it has no owning template to sequence that instead). The bare test
 * environment's egw stub doesn't implement langRequire() - stub it here so that bootstrap
 * harmlessly no-ops instead of logging an unrelated unhandled-rejection on every run of that test
 * file. ES module imports evaluate in encounter order, so importing this file first (even though
 * it has no exports) guarantees these stubs exist before app.ts's own top-level code runs.
 */
(<any>window).egw ??= <any>{};
(<any>window).egw.langRequire ??= () => Promise.resolve();
(<any>window).egw.preference ??= () => undefined;
(<any>window).egw_ready ??= Promise.resolve();

export {};
