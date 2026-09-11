import {assert} from "@open-wc/testing";
import "./InfologAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way addressbook/js/test/AddressbookNoFiltersReload.test.ts does, before app.ts pulls it in
import "../../../api/js/etemplate/Et2Widget/Et2Widget";

/**
 * app.ts has to be loaded through its explicit source path - see
 * addressbook/js/test/MailVcardMessage.test.ts's docblock for why. InfologApp itself is not
 * exported - the module registers it as `app.classes.infolog` at module scope.
 */
const APP_SOURCE = '/infolog/js/app.ts';

/**
 * Coverage for InfologApp._push_grant_check()'s responsible carve-out.
 *
 * Being responsible for an entry gives implicit access, and infolog_bo::is_responsible_user()
 * matches info_responsible against the user *and* his memberships - so an entry owned by
 * somebody who granted us nothing is still ours to see if a group we are in is responsible for
 * it.  Our memberships are not grants, so they are not in what egw.grants() gives us: verified
 * live, where the user's five memberships were all absent from egw.grants('infolog').  The base
 * class' "no grant from any listed account means no access" would therefore drop those pushes.
 *
 * The method only reads egw.user()/egw.grants() and pushData.acl, so it is exercised on the
 * prototype with a minimal `this` rather than through a constructed app.  `super` resolves off
 * the method's own home object, so the call into EgwApp still happens for real.
 */
describe('InfologApp._push_grant_check()', () =>
{
	let InfologApp : any;
	let original_egw : any;

	// grants as they arrive clientside: our own account and accounts that granted us
	// something, never our memberships
	const GRANTS = {"9": -1, "6": 1, "7": 7};
	const MEMBERSHIPS = [-408, -2, -1];
	const UNGRANTED_OWNER = 4;

	before(async function()
	{
		this.timeout(15000);
		await import(APP_SOURCE);
		InfologApp = (<any>window).app.classes.infolog;
	});

	beforeEach(() =>
	{
		original_egw = (<any>window).egw;
		(<any>window).egw = Object.assign(function() {return (<any>window).egw;}, {
			...(original_egw || {}),
			grants: () => GRANTS,
			user: (field : string) => field === "account_id" ? 9 : field === "memberships" ? MEMBERSHIPS : undefined
		});
	});

	afterEach(() =>
	{
		(<any>window).egw = original_egw;
	});

	function grantCheck(acl : any) : boolean
	{
		const pushData : any = {app: "infolog", id: 42, type: "edit", account_id: 9, acl: acl};
		// `this.egw` is what the override reads, the base class uses the bare global
		return InfologApp.prototype._push_grant_check.call(
			{appname: "infolog", egw: (<any>window).egw}, pushData, ["info_owner", "info_responsible"]
		);
	}

	it('keeps an entry a group we are in is responsible for, though nobody granted us anything', () =>
	{
		assert.isTrue(grantCheck({info_owner: UNGRANTED_OWNER, info_responsible: [-408]}));
	});

	it('keeps an entry we are responsible for ourselves', () =>
	{
		assert.isTrue(grantCheck({info_owner: UNGRANTED_OWNER, info_responsible: [9]}));
	});

	it('keeps it when the IDs arrive as strings, as the server sends them', () =>
	{
		assert.isTrue(grantCheck({info_owner: String(UNGRANTED_OWNER), info_responsible: ["-408"]}));
	});

	it('keeps it when responsible is a single value rather than a list', () =>
	{
		assert.isTrue(grantCheck({info_owner: UNGRANTED_OWNER, info_responsible: -408}));
	});

	it('drops an entry responsible only for a group we are not in', () =>
	{
		assert.isFalse(grantCheck({info_owner: UNGRANTED_OWNER, info_responsible: [-999]}));
	});

	it('drops an entry with nobody responsible that nobody granted us', () =>
	{
		assert.isFalse(grantCheck({info_owner: UNGRANTED_OWNER, info_responsible: []}));
		assert.isFalse(grantCheck({info_owner: UNGRANTED_OWNER, info_responsible: null}));
	});

	it('still falls back to the grants for the owner', () =>
	{
		assert.isTrue(grantCheck({info_owner: 7, info_responsible: []}));
		assert.isTrue(grantCheck({info_owner: 9, info_responsible: []}));
	});
});
