import {assert} from "@open-wc/testing";
import "./AddressbookAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way the mail app.ts tests do, before app.ts pulls it in transitively
import "../../../api/js/etemplate/Et2Widget/Et2Widget";

/**
 * app.ts has to be loaded through its explicit source path: a plain `import ... from "../app"`
 * resolves to addressbook/js/app.js, a gitignored tsc output nothing rebuilds any more (only the
 * app.min.js bundle is refreshed), so the test would silently run against stale code. The
 * specifier is kept in a variable so TypeScript treats it as a dynamic module, while the
 * dev-server transforms the .ts on the fly. AddressbookApp itself is not exported - the module
 * registers it as `app.classes.addressbook` at module scope, which is what we read.
 */
const APP_SOURCE = '/addressbook/js/app.ts';

/**
 * Regression coverage for adb_mail_vcard()'s confirmation message.
 *
 * The handler builds `content = {data:{files:{file:[],type:[]}}}` - the shape
 * app.mail.setCompose() wants - but its final loop still read `content[index].file`, the shape
 * from before that restructure (`{vcard:{file:[],type:[]}}`). So every single "Mail VCard" ended
 * in `TypeError: Cannot read properties of undefined (reading 'length')`, thrown right after the
 * compose window had already opened: the user saw no confirmation and an error in the console,
 * while the mail itself looked fine. The label was stale too - `egw.lang(index)` had become
 * lang('data') instead of naming what gets attached.
 *
 * Setup: adb_mail_vcard() only needs `this.et2` (for the nextmatch it asks about "all selected")
 * and the egw methods below, so the app object is a bare Object.create(prototype) - no EgwApp
 * constructor, which would want a real framework, sidebox and etemplate. egw.openWithinWindow()
 * is stubbed: opening a compose window is not what this test is about, and the bug was in the
 * code that runs *after* it returns.
 *
 * Pass criteria: the call must not throw, and it must report the number of contacts it attached
 * with the vCard label. A count of 0 or the string 'data' means the stale structure is back.
 */
const CONTACT_IDS = ['12', '34', '56'];

/** an et2 whose nextmatch reports an explicit (not "select all") selection */
function createFakeEt2()
{
	const nm = {
		getSelection: () => ({ids: CONTACT_IDS, all: false}),
		fetchAllIds: () => Promise.resolve(CONTACT_IDS)
	};
	return {getWidgetById: (id : string) => id === 'nm' ? nm : null};
}

describe('AddressbookApp.adb_mail_vcard()', () =>
{
	let app : any;
	let messages : string[];
	let composeCalls : any[];
	let AddressbookApp : any;

	before(async function()
	{
		// unlike a static `import ... from "../app"` (evaluated as part of the module graph,
		// before any mocha timer starts - see mail/js/test/MailMobileViewFlag.test.ts), this
		// dynamic import happens inside the hook itself and so counts against mocha's default
		// 3000ms hook timeout. Loading app.ts's whole transitive chain cold is comfortably
		// under that on a dev machine, but not always on CI's slower/shared runners (observed
		// CI-only timeout failures, 2026-09-03) - give it real headroom instead.
		this.timeout(15000);
		await import(APP_SOURCE);
		AddressbookApp = (<any>window).app.classes.addressbook;
	});

	beforeEach(() =>
	{
		messages = [];
		composeCalls = [];
		const egw : any = {
			// substitutes %1/%2 like the real one, and carries the single translation that matters
			// here: addressbook/lang/egw_en.lang has "vcard<TAB>common<TAB>en<TAB>vCard"
			lang: (msg : string, ...args : any[]) => (msg === 'vcard' ? 'vCard' :
				args.reduce((s : string, arg, i) => s.replace('%' + (i + 1), arg), msg)),
			preference: () => 'utf-8',
			message: (msg : string) => messages.push(msg),
			openWithinWindow: (...args : any[]) => composeCalls.push(args)
		};
		// app.ts calls the bare global `egw`, not this.egw, inside adb_mail_vcard()
		(<any>window).egw = egw;

		app = Object.create(AddressbookApp.prototype);
		Object.assign(app, {appname: 'addressbook', egw: egw, et2: createFakeEt2()});
	});

	it('reports how many contacts it attached, as vCards, without throwing', () =>
	{
		const elems = CONTACT_IDS.map(id => ({id: 'addressbook::' + id}));

		app.adb_mail_vcard({id: 'mail'}, elems);

		assert.equal(composeCalls.length, 1, 'a compose window is still opened');
		assert.deepEqual(messages, ['3 contact(s) added as vCard']);
	});

	it('hands the compose window one file and one type per contact', () =>
	{
		const elems = CONTACT_IDS.map(id => ({id: 'addressbook::' + id}));

		app.adb_mail_vcard({id: 'mail'}, elems);

		const [, , content, link] = composeCalls[0];
		assert.deepEqual(link['preset[file]'], CONTACT_IDS.map(id => 'vfs://default/apps/addressbook/' + id + '/.entry'));
		assert.equal(link['preset[type]'].length, CONTACT_IDS.length);
		// the message is read off content, so its shape is part of the contract that broke
		assert.deepEqual(content.data.files.file, link['preset[file]']);
	});

	it('says nothing when there is nothing to attach', () =>
	{
		app.adb_mail_vcard({id: 'mail'}, []);

		assert.deepEqual(messages, [], 'no contacts, no confirmation');
	});
});
