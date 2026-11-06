/**
 * Tests for Et2SelectAccount (`et2-select-account`) and SelectAccountMixin.
 *
 * With ~900 uses across shipped templates this is the single most-used select subclass, and it
 * had no coverage at all.  It is also the one with the most conditional behaviour: what it offers
 * depends on the user's `account_selection` preference and on `accountType`, and it resolves ids
 * it was never given options for by asking the server one title at a time.  Every one of those
 * paths fails *quietly* - an account picker that comes up empty, or shows a bare numeric id, is
 * indistinguishable from "no accounts match".
 *
 * Behaviour under test:
 * - pre-fills from the client-side account list on connect, by account type
 * - `account_selection = 'primary_group'` fetches own groups rather than all accounts, and 'both'
 *   fetches accounts as well
 * - `account_selection = 'none'` neither fetches nor offers options to a non-admin, and does not
 *   enable server search
 * - changing `accountType` re-fetches instead of appending to the previous list
 * - a value with no matching option is resolved through `link_title()` and rendered with its real
 *   name rather than its id
 * - `filterOutMissingOptions()` is deliberately a pass-through, because the client never has the
 *   full account list
 *
 * Setup strategy:
 * `egw()` is stubbed per test with recording `accounts()`/`link_title()` and a settable
 * `preference()`, installed globally before the element is created - Et2SelectAccount reads the
 * preference in its *constructor*, which is too early for a per-instance stub.  `fetchComplete`
 * is awaited before asserting on options.
 *
 * Pass criteria:
 * Explicit assertions on which account type was requested, on the resulting `select_options`, and
 * on the resolved label.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */
import {assert, fixture} from "@open-wc/testing";
import {egwStub} from "./helpers";

const ACCOUNTS = {
	accounts: [{value: "5", label: "Demo User"}, {value: "6", label: "Another User"}],
	groups: [{value: "-1", label: "Admins"}],
	owngroups: [{value: "-2", label: "My Group"}],
	both: [{value: "5", label: "Demo User"}, {value: "-1", label: "Admins"}]
};

let fetched : string[] = [];
let titled : string[] = [];
let accountSelection : string = "";
let userApps : any = {admin: true};

const stub = {
	...egwStub,
	preference: (name : string) => name === "account_selection" ? accountSelection : null,
	user: (_what : string) => userApps,
	accounts: (type : string) =>
	{
		fetched.push(type);
		return Promise.resolve((ACCOUNTS[type] || []).map(o => ({...o})));
	},
	link_title: (_app : string, id : string) =>
	{
		titled.push("" + id);
		return Promise.resolve("Resolved " + id);
	}
};
const callableEgw = function() { return stub; };
Object.assign(callableEgw, stub);
// @ts-ignore
window.egw = callableEgw;

import "../Select/Et2SelectAccount";
import {Et2SelectAccount} from "../Select/Et2SelectAccount";

async function accountSelect(attributes = "")
{
	const element = <Et2SelectAccount>await fixture(`<et2-select-account ${attributes}></et2-select-account>`);
	await element.updateComplete;
	await (<any>element).fetchComplete;
	await element.updateComplete;
	return element;
}

const values = (element : Et2SelectAccount) => (element.select_options || []).map(o => "" + o.value);

describe("Et2SelectAccount", () =>
{
	beforeEach(() =>
	{
		fetched = [];
		titled = [];
		accountSelection = "";
		userApps = {admin: true};
	});

	it("upgrades and pre-fills from the account list", async() =>
	{
		const element = await accountSelect();

		assert.instanceOf(element, Et2SelectAccount, "et2-select-account did not upgrade");
		assert.sameMembers([...new Set(fetched)], ["accounts"], "the default account type is accounts");
		assert.sameMembers(values(element), ["5", "6"], "fetched accounts should become options");
	});

	/**
	 * NOTE: pins observed behaviour, not desired behaviour.  The `accountType` setter re-fetches
	 * whenever it runs while connected, and it runs more than once during upgrade (attribute ->
	 * property), so the same list is fetched several times and `account_options` ends up holding
	 * duplicates.  Nothing visible breaks - the `select_options` getter de-duplicates by value -
	 * but the round-trips are real.  Pinned so a future fix shows up here as a deliberate change
	 * rather than going unnoticed in either direction.
	 */
	it("de-duplicates the account list even though it is fetched more than once", async() =>
	{
		const element = await accountSelect();

		assert.isAtLeast(fetched.length, 1, "at least one fetch is expected");
		assert.deepEqual(
			values(element),
			[...new Set(values(element))],
			"however many times it fetched, the user must not see an account twice"
		);
	});

	it("enables server search, because the client never has every account", async() =>
	{
		const element = await accountSelect();

		assert.include(
			(<any>element).searchUrl,
			"ajax_search",
			"an account select must be able to search the server for accounts it did not preload"
		);
	});

	describe("accountType", () =>
	{
		it("fetches groups when asked for groups", async() =>
		{
			const element = await accountSelect('accountType="groups"');

			assert.sameMembers([...new Set(fetched)], ["groups"], "accountType should decide what is fetched");
			assert.sameMembers(values(element), ["-1"], "group options should be offered");
		});

		it("clears the previous type's accounts and fetches the new type", async() =>
		{
			const element = await accountSelect();
			assert.sameMembers(values(element), ["5", "6"], "starts with accounts");

			element.accountType = "groups";
			await (<any>element).fetchComplete;
			await (<any>element).fetchComplete;
			await element.updateComplete;

			assert.include(fetched, "groups", "changing the type should fetch the new type");
			assert.sameMembers(
				(<any>element).account_options.map(o => "" + o.value),
				["-1"],
				"the fetched-account list must be replaced, not appended to"
			);
			assert.include(values(element), "-1", "the new type's options should be offered");
		});

		/**
		 * NOTE: pins observed behaviour, not desired behaviour.  Although `account_options` is
		 * correctly cleared above, the previous type's entries still show up in `select_options`:
		 * the `accountType` setter ends with `super.select_options = this.select_options`, which
		 * copies the *merged* list (including the accounts fetched for the old type) into the base
		 * option store, where the later clear cannot reach it.  Verified by probing both lists -
		 * `account_options` is ["-1"] while `select_options` is ["6","5","-1"].  Whether that is a
		 * leak or a deliberate way to keep an already-selected value resolvable is a product
		 * decision; pinned so the choice is at least visible.
		 */
		it("still offers the previous type's accounts from the base option store", async() =>
		{
			const element = await accountSelect();
			element.accountType = "groups";
			await (<any>element).fetchComplete;
			await (<any>element).fetchComplete;
			await element.updateComplete;

			assert.includeMembers(
				values(element),
				["5", "6"],
				"the accounts fetched before the type changed are copied into the base store and persist"
			);
		});
	});

	describe("account_selection preference", () =>
	{
		it("fetches own groups only, for primary_group", async() =>
		{
			accountSelection = "primary_group";
			await accountSelect('accountType="groups"');

			assert.sameMembers(
				[...new Set(fetched)],
				["owngroups"],
				"primary_group must not expose groups the user is not a member of"
			);
		});

		it("fetches accounts as well for primary_group with type both", async() =>
		{
			accountSelection = "primary_group";
			await accountSelect('accountType="both"');

			assert.sameMembers(
				[...new Set(fetched)],
				["accounts", "owngroups"],
				"'both' needs accounts plus own groups"
			);
		});

		it("fetches nothing and offers nothing to a non-admin when set to none", async() =>
		{
			accountSelection = "none";
			userApps = {};
			const element = await accountSelect();

			assert.isEmpty(fetched, "'none' must not fetch the account list");
			assert.isEmpty(values(element), "'none' must not offer accounts to a non-admin");
		});

		it("does not enable server search when set to none", async() =>
		{
			accountSelection = "none";
			userApps = {};
			const element = await accountSelect();

			assert.notOk((<any>element).searchUrl, "'none' must not let the user search for accounts either");
		});

		it("still shows options to an admin when set to none", async() =>
		{
			accountSelection = "none";
			userApps = {admin: true};
			const element = await accountSelect();
			element.select_options = [{value: "5", label: "Demo User"}];
			await element.updateComplete;

			assert.sameMembers(values(element), ["5"], "an admin is exempt from the 'none' restriction");
		});
	});

	describe("resolving an unknown account id", () =>
	{
		it("asks the server for the title and renders it", async() =>
		{
			accountSelection = "none";
			userApps = {admin: true};
			const element = await accountSelect();
			element.value = "42";
			await element.updateComplete;
			await Promise.resolve();
			await element.updateComplete;

			assert.include(titled, "42", "an id with no option must be looked up");
			const option = (<any[]>element.select_options).find(o => o.value == "42");
			assert.exists(option, "the looked-up account should become an option");
			assert.equal(option.label, "Resolved 42", "the option should show the account's name, not its id");
		});

		it("does not look up an id it already has an option for", async() =>
		{
			const element = await accountSelect();
			element.value = "5";
			await element.updateComplete;

			assert.notInclude(titled, "5", "a preloaded account needs no round-trip");
		});
	});

	it("never filters a value out for having no option", async() =>
	{
		// Et2Select normally drops values with no matching option.  An account select must not,
		// because the option may simply not have been fetched yet.
		const element = await accountSelect();
		assert.deepEqual(
			element.filterOutMissingOptions(["999"]),
			["999"],
			"an account value must survive even with no matching option"
		);
	});
});
