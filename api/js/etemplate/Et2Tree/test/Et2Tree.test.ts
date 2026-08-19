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
	 * Et2WidgetWithSelectMixin's own select_options getter/setter is a completely separate
	 * property from Et2Tree's own _selectOptions, which is what _optionTemplate()/getNode()
	 * actually render/search - a caller doing a client-side bulk replacement (eg. mail's app.ts
	 * after a JMAP fetch) via `tree.select_options = data` needs this to actually reach the
	 * rendered tree, not silently do nothing (see Et2Tree.ts's updated() override).
	 */
	it("select_options assignment is reflected via getNode()", async() =>
	{
		const tree : any = await fixture(html`<et2-tree></et2-tree>`);

		tree.select_options = [{id: "a", text: "A", item: [], child: false}];
		await tree.updateComplete;

		assert.isOk(tree.getNode("a"), "getNode('a') should find the node set via select_options");
	});
});
