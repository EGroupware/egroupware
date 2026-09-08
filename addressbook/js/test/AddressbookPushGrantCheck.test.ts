import {assert} from "@open-wc/testing";
import "./AddressbookAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way AddressbookNoFiltersReload.test.ts does, before app.ts pulls it in transitively
import "../../../api/js/etemplate/Et2Widget/Et2Widget";

/**
 * app.ts has to be loaded through its explicit source path - see MailVcardMessage.test.ts's
 * docblock for why. AddressbookApp itself is not exported - the module registers it as
 * `app.classes.addressbook` at module scope, which is what we read.
 */
const APP_SOURCE = '/addressbook/js/app.ts';

/**
 * Coverage for AddressbookApp._push_grant_check()'s accounts-addressbook carve-out.
 *
 * Contacts of accounts have owner 0.  Server-side, Api\Contacts::get_grants() gives the user
 * a grant for account 0 whenever the account_selection preference is not 'none'/'groupmembers'
 * (or the user is an admin), but the grants we get clientside are the plain
 * Api\Acl::get_grants() ones and carry no entry for 0 at all - verified live, on an instance
 * where the user was looking at a list made up entirely of owner-0 contacts.  So the base
 * class' "no grant from the owner means no access" would drop every push about an account
 * contact the user can see, which is what this override prevents.
 *
 * The method only reads egw.grants() and pushData.acl, so it is exercised on the prototype
 * with a minimal `this` rather than through a constructed app.  `super` resolves off the
 * method's own home object, so the call into EgwApp still happens for real.
 */
describe('AddressbookApp._push_grant_check()', () =>
{
	let AddressbookApp : any;
	let original_egw : any;

	// grants as they arrive clientside: keyed by granting account, and never holding a 0
	const GRANTS = {"9": -1, "6": 5, "-408": 1};

	before(async function()
	{
		this.timeout(15000);
		await import(APP_SOURCE);
		AddressbookApp = (<any>window).app.classes.addressbook;
	});

	beforeEach(() =>
	{
		original_egw = (<any>window).egw;
		(<any>window).egw = Object.assign(function() {return (<any>window).egw;}, {
			...(original_egw || {}),
			grants: () => GRANTS
		});
	});

	afterEach(() =>
	{
		(<any>window).egw = original_egw;
	});

	function grantCheck(acl : any) : boolean
	{
		const pushData : any = {app: "addressbook", id: 42, type: "edit", account_id: 9, acl: acl};
		return AddressbookApp.prototype._push_grant_check.call(
			{appname: "addressbook"}, pushData, ["owner", "shared_with"]
		);
	}

	it('lets pushes about account contacts (owner 0) through, as we can not tell clientside', () =>
	{
		assert.isTrue(grantCheck({owner: 0, shared_with: []}));
	});

	it('lets them through when owner arrives as a string, as the server sends it', () =>
	{
		assert.isTrue(grantCheck({owner: "0", shared_with: null}));
	});

	it('still drops a contact in an addressbook nobody granted us', () =>
	{
		assert.isFalse(grantCheck({owner: 4, shared_with: []}));
	});

	it('still accepts our own contacts', () =>
	{
		assert.isTrue(grantCheck({owner: 9, shared_with: []}));
	});

	it('still accepts a contact in an addressbook granted to us', () =>
	{
		assert.isTrue(grantCheck({owner: 6, shared_with: []}));
	});

	it('still accepts a contact shared with a group we are in that we have a grant for', () =>
	{
		assert.isTrue(grantCheck({owner: 4, shared_with: [-408]}));
	});

	it('still drops a contact shared only with accounts we have no grant from', () =>
	{
		assert.isFalse(grantCheck({owner: 4, shared_with: [5, 8]}));
	});
});
