import {assert} from "@open-wc/testing";
import {Et2Historylog} from "../Et2Historylog";
import {Et2Template} from "../../Et2Template/Et2Template";
import {et2_arrayMgr, et2_readonlysArrayMgr} from "../../et2_core_arrayMgr";
import {restoreResizeObserverNoise, silenceResizeObserverNoise} from "./resizeObserverNoise";

import "../../Et2Filterbox/Et2Filterbox";
import "../../Et2Select/Et2Select";
import "../../Et2Select/Select/Et2SelectReadonly";
import "../../Et2Select/Select/Et2SelectAccount";
import "../../Layout/Et2Box/Et2Box";
import "../../Et2Datagrid/Et2Datagrid";

/**
 * Contract under test:
 * - The history log's filters stay editable when the dialog around it is read-only.  Filters are a
 *   view control, not form data, so neither the dialog's readonlys (InfoLog sets `__ALL__` for an
 *   entry the user can't edit) nor the history log's own readonly flag may turn them into labels.
 *
 * Setup strategy:
 * - A bare et2-historylog with stubbed egw, given the array managers a read-only edit dialog
 *   provides: readonlys with `__ALL__` set, and the widget itself marked readonly.
 * - The filter template is pre-seeded into Et2Template's cache (no server in these tests) with the
 *   same widgets api/templates/default/historylog.filters.xet uses for "Changed" and "User".
 * - The drawer's filterbox is created directly, which loads that template into it.
 *
 * Pass criteria: the "Changed" and "User" filters are created as their editable widgets
 * (et2-select / et2-select-account), not the `_ro` variants, and are not readonly.
 */

const egwStub = {
	lang: (label : string) => label,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	image: () => "",
	preference: () => null,
	set_preference: () => {},
	app_name: () => "infolog",
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

const FILTERS = `<overlay><template id="api.historylog.filters">
	<et2-vbox>
		<et2-select id="col_filter[status]" label="Changed" multiple="true" emptyLabel="All"></et2-select>
		<et2-select-account id="col_filter[owner]" label="User" emptyLabel="All" accountType="both"></et2-select-account>
	</et2-vbox>
</template></overlay>`;
Et2Template.templateCache["api.historylog.filters"] =
	<Element>new DOMParser().parseFromString(FILTERS, "text/xml").documentElement.firstElementChild;

describe("Et2Historylog filters in a read-only dialog", () =>
{
	before(silenceResizeObserverNoise);
	after(restoreResizeObserverNoise);

	let el : Et2Historylog;
	afterEach(() => el?.remove());

	it("keeps the filter widgets editable", async() =>
	{
		el = new Et2Historylog();
		el.setArrayMgr("content", new et2_arrayMgr({}));
		const readonlys = new et2_readonlysArrayMgr({__ALL__: true});
		readonlys.perspectiveData.owner = <any>el;
		el.setArrayMgr("readonlys", readonlys);
		(<any>el).readonly = true;
		el.value = {id: 42, app: "infolog", "status-widgets": {}};
		document.body.append(el);
		await el.updateComplete;

		const filterbox = (<any>el)._ensureFilterbox();
		assert.exists(filterbox, "the drawer's filterbox must be created");

		let status, owner;
		for(let i = 0; i < 50 && !(status && owner); i++)
		{
			await new Promise(resolve => setTimeout(resolve, 10));
			status = filterbox.querySelector('[id="col_filter[status]"]');
			owner = filterbox.querySelector('[id="col_filter[owner]"]');
		}
		assert.exists(status, "the filter template must load");
		assert.exists(owner);

		assert.equal(status.localName, "et2-select", "'Changed' must not render as a read-only label");
		assert.isNotTrue(status.readonly);
		assert.equal(owner.localName, "et2-select-account", "'User' must not render as a read-only label");
		assert.isNotTrue(owner.readonly);
	});
});
