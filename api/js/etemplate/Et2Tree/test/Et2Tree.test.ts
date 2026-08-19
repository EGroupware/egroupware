import {assert, fixture, html} from "@open-wc/testing";
import "../Et2Tree";

window.egw = {
	ajaxUrl: (url) => url,
	decodePath: (_path : string) => _path,
	image: () => "data:image/svg+xml;base64,",
	preference: i => "",
	tooltipUnbind: () => {},
	webserverUrl: ""
};

describe("Et2Tree", () =>
{
	/**
	 * Et2WidgetWithSelectMixin's own select_options getter/setter is designed for flat
	 * SelectOption {value, label} lists - Et2Tree overrides it to redirect straight to its own
	 * _selectOptions (what _optionTemplate()/getNode() actually render/search) instead, so a
	 * caller doing a client-side bulk replacement (eg. mail's app.ts after a JMAP fetch) via
	 * `tree.select_options = data` actually reaches the rendered tree.
	 */
	it("select_options assignment (plain array) is reflected via getNode()", async() =>
	{
		const tree : any = await fixture(html`<et2-tree></et2-tree>`);

		tree.select_options = [{id: "a", text: "A", item: [], child: false}];
		await tree.updateComplete;

		assert.isOk(tree.getNode("a"), "getNode('a') should find the node set via select_options");
	});

	/**
	 * Regression test: classic mail_tree.inc.php (and anything else building a tree server-side)
	 * emits a *root wrapper* object - {id: 0, item: [...]} - not a plain array of top-level nodes.
	 * Before Et2Tree overrode select_options itself, a fix that instead synced from the mixin's
	 * *cleaned* value (this.select_options, post cleanSelectOptions()) was fed that wrapper object
	 * as if it were a flat option list: cleanSelectOptions() iterated its own keys ("id"/"item")
	 * as if they were option entries, producing a single bogus {value: "id", label: "0"} node and
	 * a second blank one - the tree visibly collapsing to a lone "0" in production. Bypassing the
	 * mixin's accessor entirely (this class's actual fix) must show the wrapper's real children,
	 * not "0".
	 */
	it("a root-wrapper object ({id, item}) is unwrapped correctly, not mangled into a lone '0'", async() =>
	{
		const tree : any = await fixture(html`<et2-tree></et2-tree>`);

		tree.select_options = {
			id: 0,
			item: [{id: "a", text: "A", item: [], child: false}],
		};
		await tree.updateComplete;

		assert.notOk(tree.getNode("id"), "must not fabricate a node from the wrapper's own 'id' key");
		assert.notOk(tree.getNode("item"), "must not fabricate a node from the wrapper's own 'item' key");
	});
});
