import {assert} from "@open-wc/testing";
import "./FilemanagerAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way the other app.ts tests do, before app.ts pulls it in transitively
import "../../../api/js/etemplate/Et2Widget/Et2Widget";

/**
 * app.ts has to be loaded through its explicit source path: a plain `import ... from "../app"`
 * resolves to filemanager/js/app.js, a gitignored tsc output nothing rebuilds any more (only the
 * app.min.js bundle is refreshed), so the test would silently run against stale code. The
 * specifier is kept in a variable so TypeScript treats it as a dynamic module, while the
 * dev-server transforms the .ts on the fly. filemanagerAPP itself is not exported from app.ts -
 * the module registers it as `app.classes.filemanager`, which is what we read.
 */
const APP_SOURCE = '/filemanager/js/app.ts';

/**
 * Regression coverage for Tracker #124911: the Collabora editor's "Send document by e-mail"
 * toolbar button (collabora/js/app.ts on_save_as_mail() -> app.filemanager.mail() -> here)
 * silently did nothing when clicked.
 *
 * Root cause: open_mail()'s "nothing to reuse, open a fresh compose" path called
 * `(<any>window).app.mail?.composeWithPreset(...)` directly. The Collabora editor opens in its
 * own plain `window.open()` window (collabora/js/app.ts, menuaction collabora.../Ui.editor),
 * which never loads mail's own JS bundle - so `window.app.mail` is always undefined there, and
 * the optional-chaining call just no-op'd, with no error and no popup. Same bug class already
 * fixed once for egw_open.ts's mailto() (219284f985, 2026-09-14) and applied here now too: dispatch
 * via egw.applyFunc('app.mail.composeWithPreset', ...) instead, which lazy-loads mail's bundle
 * first if it isn't loaded yet.
 *
 * Setup: open_mail() only touches `this.basename()` (pure) and the module-scope global `egw`, so
 * the app object is a bare Object.create(prototype) - no EgwApp constructor, which would want a
 * real framework, sidebox and etemplate.
 */
describe('filemanagerAPP.open_mail()', () =>
{
	let app : any;
	let egw : any;
	let composeCalls : any[];
	let applyFuncCalls : any[];
	let filemanagerAppClass : any;

	before(async function()
	{
		this.timeout(15000);
		await import(APP_SOURCE);
		filemanagerAppClass = (<any>window).app.classes.filemanager;
	});

	beforeEach(() =>
	{
		composeCalls = [];
		applyFuncCalls = [];
		egw = {
			openWithinWindow: (...args : any[]) => composeCalls.push(args),
			dataGetUIDdata: () => undefined,
			applyFunc: (func : string, args : any[]) =>
			{
				applyFuncCalls.push({func, args});
			},
		};
		(<any>window).egw = egw;

		app = Object.create(filemanagerAppClass.prototype);
	});

	it('opens compose via egw.openWithinWindow(), not a direct window.app.mail read', () =>
	{
		app.open_mail(['/apps/filemanager/report.pdf']);

		assert.equal(composeCalls.length, 1);
	});

	it('dispatches the "nothing to reuse" compose open through egw.applyFunc(), never a direct window.app.mail? read', () =>
	{
		app.open_mail(['/apps/filemanager/report.pdf'], {'preset[filemode]': 'attach'});

		const openNew = composeCalls[0][6];
		assert.equal(typeof openNew, 'function', 'openWithinWindow() got an _open_new override');

		openNew();

		assert.equal(applyFuncCalls.length, 1);
		assert.equal(applyFuncCalls[0].func, 'app.mail.composeWithPreset');
		assert.deepEqual(applyFuncCalls[0].args[0].files, [{
			path: 'vfs://default/apps/filemanager/report.pdf',
			name: 'report.pdf',
			type: 'application/octet-stream',
		}]);
		assert.equal(applyFuncCalls[0].args[0].filemode, 'attach');
	});
});
