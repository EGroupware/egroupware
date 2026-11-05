import {assert} from "@open-wc/testing";
import "@shoelace-style/shoelace/dist/components/drawer/drawer.js";
import {Et2Nextmatch} from "../Et2Nextmatch";
import {Et2Template} from "../../Et2Template/Et2Template";
import {et2_arrayMgr, et2_readonlysArrayMgr} from "../../et2_core_arrayMgr";
// Et2Nextmatch only uses it as a type; etemplate2.ts is what registers it in the real page
import "../../Et2Filterbox/Et2Filterbox";
import "../../Et2Select/Et2Select";
import "../../Et2Select/Select/Et2SelectReadonly";
import "../../Et2Select/Select/Et2SelectAccount";
import "../../Layout/Et2Box/Et2Box";

/**
 * Contract under test:
 * - A nextmatch keeping its filters in its own drawer (no egw-app above it, eg. in a popup) keeps
 *   them editable when the popup around it is read-only.  Filters are a view control, not form
 *   data, so neither the popup's readonlys (`__ALL__` for something the user can't edit) nor the
 *   nextmatch's own readonly flag may turn them into labels.
 *
 * Setup strategy:
 * - A bare et2-nextmatch in a plain div, so its filterbox goes into its own drawer, given the
 *   array managers a read-only popup provides: readonlys with `__ALL__` set, and the nextmatch
 *   itself marked readonly.
 * - Its filterTemplate is pre-seeded into Et2Template's cache (no server in these tests).
 *
 * Pass criteria: the filters are created as their editable widgets (et2-select /
 * et2-select-account), not the `_ro` variants, and are not readonly.
 */

const egwStub = {
	lang: (label : string) => label,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	image: () => "",
	preference: () => null,
	set_preference: () => {},
	app_name: () => "preferences",
	link: (url : string) => url,
	debug: () => {},
	debug_level: () => 0,
	dataFetch: (_e, _r, _f, _w, callback) => callback({order: [], total: 0}),
	dataRegisterUID: (_uid, callback) => callback({}, "row::1"),
	dataUnregisterUID: () => {},
	dataStoreUID: () => {},
	accounts: () => Promise.resolve([]),
	accountData: () => {},
	accountInfo: () => null,
	lang_notranslate: (s : string) => s,
	decodePath: (p : string) => p,
	encodePath: (p : string) => p,
	webserverUrl: ""
};
window.egw = function() { return egwStub; } as any;
Object.assign(window.egw, egwStub);

const FILTERS = `<overlay><template id="test.readonly.filter">
	<et2-vbox>
		<et2-select id="col_filter[status]" label="Status" emptyLabel="All"></et2-select>
		<et2-select-account id="col_filter[owner]" label="User" emptyLabel="All" accountType="accounts"></et2-select-account>
	</et2-vbox>
</template></overlay>`;
Et2Template.templateCache["test.readonly.filter"] =
	<Element>new DOMParser().parseFromString(FILTERS, "text/xml").documentElement.firstElementChild;

describe("Et2Nextmatch own filter drawer in a read-only popup", () =>
{
	let container : HTMLElement;
	afterEach(() => container?.remove());

	it("keeps the filter widgets editable", async() =>
	{
		container = document.createElement("div");
		document.body.append(container);

		const el = new Et2Nextmatch();
		el.setArrayMgr("content", new et2_arrayMgr({}));
		const readonlys = new et2_readonlysArrayMgr({__ALL__: true});
		readonlys.perspectiveData.owner = <any>el;
		el.setArrayMgr("readonlys", readonlys);
		(<any>el).readonly = true;
		el.filterTemplate = "test.readonly.filter";
		container.append(el);
		await el.updateComplete;

		let filterbox, status, owner;
		for(let i = 0; i < 100 && !(status && owner); i++)
		{
			await new Promise(resolve => setTimeout(resolve, 10));
			filterbox = el.querySelector("et2-filterbox");
			status = filterbox?.querySelector('[id="col_filter[status]"]');
			owner = filterbox?.querySelector('[id="col_filter[owner]"]');
		}
		assert.isTrue((<any>el)._ownFilterDrawer, "with no egw-app above it the nextmatch keeps its own drawer");
		assert.exists(status, "the filter template must load");
		assert.exists(owner);

		assert.equal(status.localName, "et2-select", "a select filter must not render as a read-only label");
		assert.isNotTrue(status.readonly);
		assert.equal(owner.localName, "et2-select-account", "an account filter must not render as a read-only label");
		assert.isNotTrue(owner.readonly);
	});
});
