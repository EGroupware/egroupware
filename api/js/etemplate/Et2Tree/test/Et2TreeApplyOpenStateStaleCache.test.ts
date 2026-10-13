import {assert, fixture, html} from "@open-wc/testing";
import "../Et2Tree";
// Et2Tree only imports SlTreeItem as a type, so esbuild drops shoelace's side-effect registration
// from this bundle and the <sl-tree> in its shadow root would never upgrade - register both here,
// the way the rollup bundle ends up doing for a real page
import "@shoelace-style/shoelace/dist/components/tree/tree.js";
import "@shoelace-style/shoelace/dist/components/tree-item/tree-item.js";

window.egw = {
	ajaxUrl: (url) => url,
	decodePath: (_path : string) => _path,
	image: () => "data:image/svg+xml;base64,",
	preference: i => "",
	tooltipUnbind: () => {},
	webserverUrl: ""
};

/**
 * Regression coverage for ticket #126411 ("wired observation" - sporadically expanding/
 * collapsing one mailbox's folder in the tree auto-expands or auto-collapses ALL the OTHER
 * top-level mailboxes).
 *
 * loadOpenIds() parses and caches openStatePreference's persisted ids exactly ONCE per widget
 * instance. applyOpenState() is re-run on every lazy-load anywhere in the tree (see its own
 * docblock), each time re-applying that SAME cached snapshot - and for top-level items, once a
 * preference has ever been saved, it's authoritative both ways (closes anything not listed,
 * not just opens what is). Before this fix, saveOpenState() never updated the cached _openIds -
 * so after the user expanded or collapsed a top-level account that diverged from the original
 * page-load snapshot, the NEXT applyOpenState() re-run (triggered by lazy-loading some other,
 * unrelated account) reverted that account right back to its original state, with no further
 * action by the user. saveOpenState() must keep _openIds in sync with the tree's own latest
 * state so later applyOpenState() re-runs never see it as stale.
 */
describe("Et2Tree - applyOpenState()/saveOpenState() stale-cache (ticket #126411)", () =>
{
	let originalPreference;

	beforeEach(() =>
	{
		originalPreference = window.egw.preference;
	});

	afterEach(() =>
	{
		window.egw.preference = originalPreference;
	});

	async function makeTree(savedIds : string[])
	{
		//@ts-ignore
		window.egw.preference = (name : string, app : string) =>
			(app === "mail" && name === "ExpandedFolders") ? JSON.stringify(savedIds) : "";

		const tree : any = await fixture(html`<et2-tree></et2-tree>`);
		tree.openStatePreference = "mail.ExpandedFolders";
		tree._selectOptions = [
			{id: "accountA", text: "A", item: [], open: 1},
			{id: "accountB", text: "B", item: [], open: 0},
		];
		return tree;
	}

	it("keeps a newly-expanded top-level account open across a later, unrelated applyOpenState() re-run", async() =>
	{
		const tree = await makeTree(["accountA"]);

		// user expands accountB (eg. handleItemExpand()'s own effect)
		tree._selectOptions[1].open = 1;
		tree.saveOpenState();

		// some OTHER account's lazy-load resolves elsewhere in the tree, re-running applyOpenState()
		// against the SAME _selectOptions - before the fix this reasserted the original
		// ["accountA"]-only snapshot and force-closed accountB again
		tree.applyOpenState();

		assert.equal(tree._selectOptions[1].open, 1,
			"accountB must stay open - saveOpenState() must have refreshed the cached open-ids");
	});

	it("keeps a newly-collapsed top-level account closed across a later, unrelated applyOpenState() re-run", async() =>
	{
		const tree = await makeTree(["accountA", "accountB"]);
		tree._selectOptions[1].open = 1;

		// user collapses accountB
		tree._selectOptions[1].open = 0;
		tree.saveOpenState();

		// an unrelated lazy-load elsewhere re-runs applyOpenState() again
		tree.applyOpenState();

		assert.equal(tree._selectOptions[1].open, 0,
			"accountB must stay closed - applyOpenState() must not re-open it from a stale snapshot");
	});
});
