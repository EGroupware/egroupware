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

	/**
	 * Regression test for the actual production incident (not just the select_options override in
	 * isolation): Et2WidgetWithSelectMixin's own updated() calls find_select_options() - a second,
	 * independent generic option-lookup utility with the exact same "treat this widget's raw
	 * sel_options entry as a flat option list" assumption - whenever "id" changes and
	 * select_options didn't change in the SAME batch. A tree's id is set (via loadFromXML(), as
	 * part of normal widget construction) in a render pass separate from when its data is applied,
	 * so this fires on every real page load and re-derives + reassigns select_options from the
	 * SAME root-wrapper sel_options entry, mangling it right back even though the override above
	 * had already unwrapped it correctly moments earlier - exactly what let the "shows a lone 0"
	 * bug ship a second time after the first fix (see updated()'s own docblock, which suppresses
	 * this by hiding "id" from the super call).
	 */
	it("assigning id in the same batch as select_options does not re-mangle the tree", async() =>
	{
		const tree : any = await fixture(html`<et2-tree></et2-tree>`);
		// find_select_options() (called by the mixin's own updated(), see below) re-fetches its
		// own copy of this widget's raw sel_options entry independently of select_options -
		// stubbed here exactly like a real "foldertree"-id widget would have it, via a plain
		// object rather than a full et2_arrayMgr since only .getEntry() is ever called for this id
		tree.getArrayMgr = (part : string) => part === "sel_options" ? {
			getEntry: (id : string) => id === "foldertree"
				? {id: 0, item: [{id: "a", text: "A", item: [], child: false}]} : null,
			getRoot: () => ({getEntry: () => null}),
		} : null;

		// mirrors a real widget's actual construction order: the framework sets its id and its
		// real tree data in the same synchronous pass (well before Lit's next microtask-scheduled
		// update runs) - this batching is what let Et2WidgetWithSelectMixin's own updated() see
		// both "id" and "select_options" as changed together and still re-derive (mangle) the
		// latter from scratch via find_select_options(), clobbering the value just assigned
		tree.id = "foldertree";
		tree.select_options = {id: 0, item: [{id: "a", text: "A", item: [], child: false}]};
		await tree.updateComplete;

		assert.isOk(tree.getNode("a"), "the real node must survive whatever update cycle follows");
		assert.notOk(tree.getNode("id"), "must not fabricate a node from the wrapper's own 'id' key");
		assert.notOk(tree.getNode("item"), "must not fabricate a node from the wrapper's own 'item' key");
	});
});
