/**
 * Tests for EgwApp.linkHandler(), which shows a link to the application's list in the nextmatch that
 * is already loaded, and leaves every link it does not fully understand to the framework.
 *
 * The methods are exercised on the prototype, with the lookup of the loaded nextmatch replaced.
 */
import {assert} from "@open-wc/testing";
import {EgwApp} from "../egw_app";

const proto = <any>EgwApp.prototype;
const INDEX = "/egroupware/index.php?menuaction=example.example_ui.index";

/**
 * An application whose tab shows the given nextmatch
 */
function appWith(nm : any, overrides : object = {})
{
	const messages : string[] = [];
	return {
		messages,
		egw: {message: (msg : string) => messages.push(msg)},
		linkHandler: proto.linkHandler,
		linkParamFilters: proto.linkParamFilters,
		_linkFilters: proto._linkFilters,
		_linkNextmatch: () => nm,
		...overrides
	};
}

function nextmatch(activeFilters : object = {})
{
	const applied : any[] = [];
	return {applied, activeFilters, applyFilters: (filters : any) => applied.push(filters)};
}

describe("EgwApp.linkHandler()", () =>
{
	it("sets the search of a link on the loaded nextmatch", () =>
	{
		const nm = nextmatch();
		assert.isTrue(appWith(nm).linkHandler(INDEX + "&search=%23123&ajax=true"));
		assert.deepEqual(nm.applied, [{search: "#123"}]);
	});

	it("shows the message of a link it handles", () =>
	{
		const nm = nextmatch();
		const app = appWith(nm);
		assert.isTrue(app.linkHandler(INDEX + "&search=foo&msg=Entry+saved"));
		assert.deepEqual(app.messages, ["Entry saved"]);
	});

	it("leaves a link without a filter to the framework, it asks for a reload", () =>
	{
		const nm = nextmatch();
		const app = appWith(nm);
		assert.isFalse(app.linkHandler(INDEX + "&ajax=true"));
		assert.isFalse(app.linkHandler(INDEX + "&ajax=true&msg=Entry+saved"));
		assert.isFalse(app.linkHandler(INDEX + "&search=&ajax=true"));
		assert.deepEqual(nm.applied, []);
		assert.deepEqual(app.messages, []);
	});

	it("leaves a link with an unknown parameter to the framework", () =>
	{
		const nm = nextmatch();
		assert.isFalse(appWith(nm).linkHandler(INDEX + "&search=foo&action=sp&action_id=5"));
		assert.isFalse(appWith(nm).linkHandler(INDEX + "&search=foo&fw_target=example-abc"));
		assert.deepEqual(nm.applied, []);
	});

	it("leaves the link to the framework when the page is not the loaded one", () =>
	{
		assert.isFalse(appWith(null).linkHandler(INDEX + "&search=foo"));
		assert.isFalse(appWith(null).linkHandler(""));
	});

	it("sets a parameter named like a filter the nextmatch has a value for, empty or not", () =>
	{
		const nm = nextmatch({filter: "", cat_id: null, filter2: -1, col_filter: {info_type: ""}, sort: {id: "x", asc: true}});
		assert.isTrue(appWith(nm).linkHandler(INDEX + "&filter=own&cat_id=5&filter2=6&col_filter%5Binfo_type%5D=task"));
		assert.deepEqual(nm.applied, [{filter: "own", cat_id: "5", filter2: 6, col_filter: {info_type: "task"}}]);
	});

	it("leaves a parameter the nextmatch has no filter for to the framework", () =>
	{
		const nm = nextmatch({filter: "", col_filter: {info_type: ""}});
		assert.isFalse(appWith(nm).linkHandler(INDEX + "&cat_id=5"));
		assert.isFalse(appWith(nm).linkHandler(INDEX + "&filter=own&col_filter%5Binfo_status%5D=done"));
		assert.isFalse(appWith(nm).linkHandler(INDEX + "&sort=x"));
		assert.isFalse(appWith(nm).linkHandler(INDEX + "&col_filter=x"));
		assert.deepEqual(nm.applied, []);
	});

	it("does not set a filter from a parameter without a value", () =>
	{
		const nm = nextmatch({filter: "own"});
		assert.isFalse(appWith(nm).linkHandler(INDEX + "&filter="));
		assert.isTrue(appWith(nm).linkHandler(INDEX + "&filter=&search=foo"));
		assert.deepEqual(nm.applied, [{search: "foo"}]);
	});

	it("passes the loaded nextmatch on, for an application to look at its filters", () =>
	{
		const nm = nextmatch();
		let seen = null;
		const app = appWith(nm, {
			_linkFilters(query : URLSearchParams, _nm : any)
			{
				seen = _nm;
				return proto._linkFilters.call(this, query, _nm);
			}
		});
		assert.isTrue(app.linkHandler(INDEX + "&search=foo"));
		assert.strictEqual(seen, nm);
	});

	it("merges the filters of the parameters an application adds", () =>
	{
		const nm = nextmatch();
		const app = appWith(nm, {
			linkParamFilters(param : string, value : string, query : URLSearchParams)
			{
				switch(param)
				{
					case "link_app":
						return {col_filter: {linked: {app: value, id: query.get("link_id")}}};
					case "link_id":
						return null;
					case "owner":
						return {col_filter: {owner: value}};
				}
				return proto.linkParamFilters.call(this, param, value, query);
			}
		});
		assert.isTrue(app.linkHandler(INDEX + "&link_app=infolog&link_id=5&owner=7&search=foo"));
		assert.deepEqual(nm.applied, [{
			search: "foo",
			col_filter: {linked: {app: "infolog", id: "5"}, owner: "7"}
		}]);
	});
});
