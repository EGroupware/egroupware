/**
 * Tests for Et2AccountFilterHeader (`et2-nextmatch-header-account`).
 *
 * This is FilterMixin over Et2SelectAccount rather than plain Et2Select, so the point of these
 * tests is that the mixin's filter contract survives the account widget's own asynchronous option
 * loading - the failure mode this guards against is an account header that renders and fetches
 * fine but never actually filters.
 *
 * Behaviour under test:
 * - opts into hoisting and clearing like the plain filter header
 * - pre-fills its options from the account list on connect
 * - a change still emits `et2-nextmatch-filter` with the selected account id under the header id
 * - `setNextmatch()` still seeds from an already-active col_filter
 *
 * Setup strategy:
 * The egw stub is extended with the two account entry points Et2SelectAccount uses - `accounts()`
 * for the pre-filled list and `link_title()` for ids not in it - both resolving immediately, so no
 * test depends on network timing.  `fetchComplete` is awaited where the option list matters.
 *
 * Pass criteria:
 * Explicit assertions on the loaded options and on the emitted filter event detail.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */
import {assert} from "@open-wc/testing";
import "../Headers/AccountFilterHeader";
import {Et2AccountFilterHeader} from "../Headers/AccountFilterHeader";
import {ET2_NEXTMATCH_FILTER_EVENT} from "../Headers/events";
import {fakeNextmatch, inHost, installEgwStub, waitForBubblingHandlers} from "./headerHelpers";

const ACCOUNTS = [
	{value: "5", label: "Demo User"},
	{value: "6", label: "Another User"}
];

installEgwStub({
	// @ts-ignore - stub only needs the shape Et2SelectAccount calls
	accounts: () => Promise.resolve(ACCOUNTS.slice()),
	// @ts-ignore
	link_title: (app : string, id : string) => Promise.resolve("Account " + id),
	preference: () => null
});

const accountHeader = (id = "owner") =>
{
	const header = <any>document.createElement("et2-nextmatch-header-account");
	header.id = id;
	return header;
};

describe("Et2AccountFilterHeader", () =>
{
	it("upgrades and opts into hoisting and clearing", async() =>
	{
		const {host, element} = await inHost(accountHeader());
		try
		{
			assert.instanceOf(element, Et2AccountFilterHeader, "et2-nextmatch-header-account did not upgrade");
			assert.isTrue(element.hoist, "an account header must hoist, its dropdown escapes the header row otherwise");
			assert.isTrue(element.clearable, "a filter must be clearable");
		}
		finally
		{
			host.remove();
		}
	});

	it("pre-fills its options from the account list", async() =>
	{
		const {host, element} = await inHost(accountHeader());
		try
		{
			await element.fetchComplete;
			await element.updateComplete;

			assert.sameMembers(
				element.select_options.map(o => o.value),
				["5", "6"],
				"account header should offer the accounts fetched on connect"
			);
		}
		finally
		{
			host.remove();
		}
	});

	it("offers an 'All' entry so the account filter can be cleared", async() =>
	{
		const {host, element} = await inHost(accountHeader());
		try
		{
			assert.equal(element.emptyLabel, "All", "an unlabelled account filter should offer 'All'");
		}
		finally
		{
			host.remove();
		}
	});

	it("emits the selected account as a col_filter", async() =>
	{
		const {host, element} = await inHost(accountHeader("owner"));
		try
		{
			await element.fetchComplete;
			element.value = "5";
			await element.updateComplete;

			let detail : any = null;
			host.addEventListener(ET2_NEXTMATCH_FILTER_EVENT, (event : Event) => detail = (<CustomEvent>event).detail);
			element.dispatchEvent(new Event("change", {bubbles: true}));
			await waitForBubblingHandlers();

			assert.deepEqual(
				detail?.filters,
				{col_filter: {owner: "5"}},
				"account header should filter by the selected account id"
			);
		}
		finally
		{
			host.remove();
		}
	});

	it("applies the filter through the legacy nextmatch when unhandled", async() =>
	{
		const {host, element} = await inHost(accountHeader("owner"));
		try
		{
			await element.fetchComplete;
			const nextmatch = fakeNextmatch();
			element.setNextmatch(nextmatch);
			element.value = "6";
			await element.updateComplete;

			element.dispatchEvent(new Event("change", {bubbles: true}));
			await waitForBubblingHandlers();

			assert.deepEqual(
				nextmatch.calls.applyFilters,
				[{col_filter: {owner: "6"}}],
				"unhandled account filter should fall back to nextmatch.applyFilters()"
			);
		}
		finally
		{
			host.remove();
		}
	});

	it("seeds itself from the account filter already active on the nextmatch", async() =>
	{
		const {host, element} = await inHost(accountHeader("owner"));
		try
		{
			await element.fetchComplete;
			element.setNextmatch(fakeNextmatch({col_filter: {owner: "5"}}));
			await element.updateComplete;

			assert.equal(element.value, "5", "an already-filtered list must show its active account filter");
		}
		finally
		{
			host.remove();
		}
	});
});
