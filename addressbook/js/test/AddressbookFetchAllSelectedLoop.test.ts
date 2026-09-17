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
 * Behaviour under test: `_fetchAllSelected()` must resolve a "select all" selection exactly
 * once, even though its callers (addEmail(), adb_mail_vcard()) resolve it by calling
 * *themselves* again with the fetched ids as their `selected` argument.
 *
 * Why: fetching the ids does not clear the nextmatch's own "all rows are selected" flag - by
 * design, the selection is still "all". So on the second call `nm.getSelection().all` was still
 * true, `_fetchAllSelected()` fetched again, called the callback again, and so on forever.
 * Live symptom (reported 2026-09-17, "Email -> Add to To" on a distribution list): a "Loading,
 * please wait" dialog that is created and destroyed over and over and never finishes, plus one
 * identical `ajax_get_rows(start=0, num_rows=200)` request every few hundred ms until the tab
 * is reloaded.
 *
 * Setup strategy: a bare `Object.create(prototype)` app object (same as
 * AddressbookAddEmailNullNm.test.ts - no real EgwApp/framework/etemplate needed), plus a stub
 * nextmatch whose `getSelection().all` stays `true` forever, exactly as the real one does.
 *
 * Pass criteria: `fetchAllIds()` is called exactly once and the callback runs exactly once. A
 * regression would call both an unbounded number of times; the counters below would run away
 * and the assertions fail (the recursion is broken by a re-entrancy guard, not by a depth
 * limit, so a regression cannot pass by accident).
 */
function createAllSelectedNm(ids : string[], counters : { fetches : number })
{
	return {
		getSelection: () => ({ids: [], all: true}),
		fetchAllIds: () =>
		{
			counters.fetches++;
			return Promise.resolve(ids);
		}
	};
}

describe('AddressbookApp._fetchAllSelected() re-entrancy', () =>
{
	let app : any;
	let AddressbookApp : any;
	let openLinkCalls : string[];

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
			lang: (msg : string) => msg,
			preference: () => undefined,
			message: () => {},
			open_link: (uri : string) => openLinkCalls.push(uri),
			dataGetUIDdata: () => ({data: {n_fn: 'Test Contact', email: 'contact@example.org'}})
		};
		(<any>window).egw = egw;

		app = Object.create(AddressbookApp.prototype);
		Object.assign(app, {appname: 'addressbook', egw: egw});
	});

	it('resolves "all selected" once, even when the callback re-enters it', async() =>
	{
		const counters = {fetches: 0};
		const nm = createAllSelectedNm(['1', '2', '3'], counters);
		let callbackRuns = 0;
		let reEntryReturned : boolean | null = null;

		const callback = () =>
		{
			callbackRuns++;
			// what addEmail()/adb_mail_vcard() do: call themselves again, which hits
			// _fetchAllSelected() a second time with the very same still-"all" selection
			reEntryReturned = app._fetchAllSelected(nm, callback);
		};

		assert.isTrue(app._fetchAllSelected(nm, callback), 'first call takes over and fetches');
		await new Promise(resolve => setTimeout(resolve, 0));

		assert.equal(counters.fetches, 1, 'fetchAllIds() called exactly once');
		assert.equal(callbackRuns, 1, 'callback ran exactly once');
		assert.isFalse(reEntryReturned, 're-entry reports "nothing to fetch" so the caller carries on');
	});

	it('is armed again for the next, genuinely new "select all"', async() =>
	{
		const counters = {fetches: 0};
		const nm = createAllSelectedNm(['1'], counters);
		const callback = () => { app._fetchAllSelected(nm, callback); };

		app._fetchAllSelected(nm, callback);
		await new Promise(resolve => setTimeout(resolve, 0));
		app._fetchAllSelected(nm, callback);
		await new Promise(resolve => setTimeout(resolve, 0));

		assert.equal(counters.fetches, 2, 'two separate user actions each get their own fetch');
	});

	it('addEmail() opens exactly one mailto: link for a "select all" selection', async() =>
	{
		const counters = {fetches: 0};
		const nm = createAllSelectedNm(['1', '2'], counters);
		const action = {id: 'add_to_to', getManager: () => ({getActionById: () => ({checked: true})})};

		app.addEmail(action, [], nm, 'business');
		await new Promise(resolve => setTimeout(resolve, 0));

		assert.equal(counters.fetches, 1, 'ids fetched once');
		assert.equal(openLinkCalls.length, 1, 'exactly one compose window opened');
		assert.include(openLinkCalls[0], 'contact@example.org');
	});
});
