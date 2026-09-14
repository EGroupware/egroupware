import {assert} from "@open-wc/testing";
import "./AddressbookAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way the mail app.ts tests do, before app.ts pulls it in transitively
import "../../../api/js/etemplate/Et2Widget/Et2Widget";

/**
 * app.ts has to be loaded through its explicit source path (see MailVcardMessage.test.ts's own
 * docblock for why): a plain `import ... from "../app"` resolves to the gitignored, stale tsc
 * output, not the live source.
 */
const APP_SOURCE = '/addressbook/js/app.ts';

/**
 * Regression coverage for a real user report (2026-09-14, Claus Brinkmann): right-clicking
 * selected contacts -> Email -> "Add to Bcc" (also To/Cc) showed in the context menu but
 * silently did nothing.
 *
 * Root cause: `EgwAction.execute(_senders, _target = null)` always calls
 * `onExecute.exec(this, _senders, _target)` - for a plain (non-drop) context-menu action,
 * `_target` is `null`, not `undefined`. `addEmail(action, selected, nm?, ...)`'s own fallback
 * (`if (typeof(nm) === "undefined") nm = this.et2.getWidgetById('nm');`) never triggered for a
 * `null` nm, so `nm` stayed `null` all the way into `_fetchAllSelected(nm, ...)`, which (unlike
 * the legacy `fetchAll()` utility it replaced in cd326f99042, 2026-06-25 - that one guarded with
 * `if(!nextmatch || !nextmatch.controller) return false;`) called `nm.getSelection()`
 * unconditionally and threw `TypeError: Cannot read properties of null (reading 'getSelection')`
 * - silently, inside a menu-item click handler, so the user never saw an error at all.
 *
 * Setup mirrors MailVcardMessage.test.ts: a bare `Object.create(prototype)` app object, no real
 * EgwApp/framework/etemplate needed for addEmail()'s own logic.
 */
function createFakeEt2(nmSelection : { ids : string[], all : boolean })
{
	const nm = {
		getSelection: () => nmSelection,
		fetchAllIds: () => Promise.resolve(nmSelection.ids),
	};
	return {getWidgetById: (id : string) => id === 'nm' ? nm : null};
}

describe('AddressbookApp.addEmail() with a null nm (plain context-menu action)', () =>
{
	let app : any;
	let openLinkCalls : string[];
	let AddressbookApp : any;

	before(async function()
	{
		this.timeout(15000);
		await import(APP_SOURCE);
		AddressbookApp = (<any>window).app.classes.addressbook;
	});

	beforeEach(() =>
	{
		openLinkCalls = [];
		const egw : any = {
			lang: (msg : string, ...args : any[]) => args.reduce((s : string, arg, i) => s.replace('%' + (i + 1), String(arg)), msg),
			preference: () => undefined,
			message: () => {},
			open_link: (uri : string) => openLinkCalls.push(uri),
			dataGetUIDdata: (id : string) => ({data: {n_fn: 'Test Contact', email: 'contact@example.org'}}),
		};
		(<any>window).egw = egw;

		app = Object.create(AddressbookApp.prototype);
		Object.assign(app, {
			appname: 'addressbook', egw: egw,
			et2: createFakeEt2({ids: ['1'], all: false}),
		});
	});

	const action = {id: 'add_to_bcc', getManager: () => ({getActionById: () => ({checked: false})})};
	const selected = [{id: 'addressbook::1'}];

	it('does not throw when nm is explicitly null (EgwAction.execute()\'s own default target)', () =>
	{
		assert.doesNotThrow(() => app.addEmail(action, selected, null));
	});

	it('still opens the mailto: bcc link when nm is null, falling back to this.et2\'s own nm', () =>
	{
		app.addEmail(action, selected, null);

		assert.equal(openLinkCalls.length, 1);
		assert.match(openLinkCalls[0], /^mailto:\?bcc=/);
		assert.include(openLinkCalls[0], 'contact@example.org');
	});

	it('_fetchAllSelected() itself also tolerates a null/undefined nm (defense in depth)', () =>
	{
		assert.equal(app._fetchAllSelected(null, () => {}), false);
		assert.equal(app._fetchAllSelected(undefined, () => {}), false);
	});
});
