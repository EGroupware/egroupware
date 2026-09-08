/**
 * Tests for EgwApp._push_grant_check(), the cheap clientside ACL pre-check that lets
 * push() drop notifications about entries the user cannot possibly see, so we avoid a
 * refresh round-trip for every entry change anywhere in the instance.
 *
 * The method only reads egw.grants() and pushData.acl, so it is exercised directly on
 * the prototype with a minimal `this` instead of constructing an EgwApp (whose
 * constructor needs a framework, sidebox DOM and window.app).
 *
 * NOT covered: push() itself, which needs an etemplate2 instance with a nextmatch.
 */
import {assert} from "@open-wc/testing";
import {EgwApp, PushData} from "../egw_app";

/**
 * Grants as egw.grants() returns them: keyed by the account IDs we got a grant from,
 * value is the rights bitmask.  Accounts that granted us nothing are simply absent.
 */
const GRANTS = {6: -1, 7: 1};

let original_egw;

/**
 * Call the method under test with the given grants, without building an EgwApp
 */
function grantCheck(acl : any, grant_fields : string[], grants : any = GRANTS) : boolean
{
	(<any>window).egw = Object.assign(function() {return (<any>window).egw;}, {
		grants: () => grants
	});
	const pushData = <PushData>{app: "example", id: 15, type: "edit", account_id: 6, acl: acl};
	return EgwApp.prototype._push_grant_check.call({appname: "example"}, pushData, grant_fields);
}

describe("EgwApp._push_grant_check()", () =>
{
	beforeEach(() =>
	{
		original_egw = (<any>window).egw;
	});

	afterEach(() =>
	{
		(<any>window).egw = original_egw;
	});

	describe("single account field (eg. ts_owner, info_owner)", () =>
	{
		it("has access to an entry owned by an account that granted us rights", () =>
		{
			assert.isTrue(grantCheck({host_creator: 7}, ["host_creator"]));
		});

		it("has access to our own entry", () =>
		{
			assert.isTrue(grantCheck({host_creator: 6}, ["host_creator"]));
		});

		it("has no access to an entry owned by an account that granted us nothing", () =>
		{
			assert.isFalse(grantCheck({host_creator: 4}, ["host_creator"]));
		});

		it("has no access to an entry owned by an account that does not exist", () =>
		{
			assert.isFalse(grantCheck({host_creator: 999}, ["host_creator"]));
		});

		it("accepts account IDs as strings, as the server sometimes sends them", () =>
		{
			assert.isTrue(grantCheck({host_creator: "7"}, ["host_creator"]));
			assert.isFalse(grantCheck({host_creator: "4"}, ["host_creator"]));
		});

		it("has no access if the field is empty", () =>
		{
			assert.isFalse(grantCheck({host_creator: null}, ["host_creator"]));
			assert.isFalse(grantCheck({}, ["host_creator"]));
		});
	});

	describe("multi-value field (eg. info_responsible, shared_with)", () =>
	{
		it("has access if any of the listed accounts granted us rights", () =>
		{
			assert.isTrue(grantCheck({info_responsible: [4, 7]}, ["info_responsible"]));
		});

		it("has no access if none of the listed accounts granted us rights", () =>
		{
			assert.isFalse(grantCheck({info_responsible: [4, 999]}, ["info_responsible"]));
		});

		it("has no access if the list is empty", () =>
		{
			assert.isFalse(grantCheck({info_responsible: []}, ["info_responsible"]));
		});
	});

	describe("several grant fields", () =>
	{
		const fields = ["info_owner", "info_responsible"];

		it("has access via the second field when the first grants nothing", () =>
		{
			assert.isTrue(grantCheck({info_owner: 4, info_responsible: [7]}, fields));
		});

		it("has access via the first field when the second grants nothing", () =>
		{
			assert.isTrue(grantCheck({info_owner: 7, info_responsible: [4]}, fields));
		});

		it("has no access when no field grants anything", () =>
		{
			assert.isFalse(grantCheck({info_owner: 4, info_responsible: [999]}, fields));
		});
	});

	describe("nothing to check against", () =>
	{
		it("assumes access if we have no grants for the app at all", () =>
		{
			assert.isTrue(grantCheck({host_creator: 4}, ["host_creator"], null));
		});

		it("assumes access if the push message carries no acl data", () =>
		{
			assert.isTrue(grantCheck(undefined, ["host_creator"]));
		});
	});

	describe("addressbook special owners", () =>
	{
		it("has access to account contacts (owner 0) if we have a grant for them", () =>
		{
			assert.isTrue(grantCheck({owner: 0}, ["owner"], {0: 1, 6: -1}));
		});

		it("has no access to account contacts if we have no grant for them", () =>
		{
			assert.isFalse(grantCheck({owner: 0}, ["owner"], {6: -1}));
		});

		it("has access to a group addressbook (negative owner) we got a grant for", () =>
		{
			assert.isTrue(grantCheck({owner: -3}, ["owner"], {"-3": 1, 6: -1}));
		});
	});
});
