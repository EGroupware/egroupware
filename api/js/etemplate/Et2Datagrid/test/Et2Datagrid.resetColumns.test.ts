import {assert} from "@open-wc/testing";
import {Et2Datagrid} from "../Et2Datagrid";
import type {Et2DatagridColumnResetDetail} from "../Et2Datagrid.types";

/**
 * "Reset to default" of the column selection: resetColumnPreferences()
 *
 * The server is simulated: `own` holds the user's own preferences, `fallback` what is effective without
 * them (group, default or forced), and ajax_get_preference hands the cache the effective ones.
 */
const KEY = "nextmatch-addressbook.index.rows-prefs";
let own : Record<string, any> = {};
let fallback : Record<string, any> = {};
let cache : Record<string, any> = {};
let requests : string[] = [];
let messages : string[] = [];
let failOn : string | null = null;
let inFlight = 0;

const egw = {
	debug: () => {},
	lang: (label : string) => label,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	preference: (name : string, _app? : string) => cache[name],
	set_preference: () => {},
	app_name: () => "addressbook",
	link: (url : string) => url,
	message: (message : string, type : string) => { messages.push(type + ": " + message); },
	request: async(menuaction : string, params : any[]) =>
	{
		// parallel requests rewrite each other's row server-side, so they must not overlap
		assert.equal(inFlight, 0, "requests must run one after the other");
		inFlight++;
		await new Promise((resolve) => setTimeout(resolve, 5));
		inFlight--;
		const method = menuaction.split("::").pop();
		requests.push(method + "(" + params.slice(1, 2).join() + ")");
		if(failOn && params[1] === failOn)
		{
			throw new Error("simulated failure");
		}
		if(method === "ajax_set_preference")
		{
			delete own[params[1]];
		}
		else if(method === "ajax_get_preference")
		{
			cache = {...fallback, ...own};
		}
		return [];
	}
};
window.egw = function() { return egw; } as any;
Object.assign(window.egw, egw);

const templateColumns = () => [
	{key: "name", title: "Name", width: "40%"},
	{key: "email", title: "Email", width: "30%"},
	{key: "phone", title: "Phone", width: "20%", hidden: true},
	{key: "customfields", title: "Custom fields", width: "10%"}
];

function createDatagrid() : Et2Datagrid
{
	const el = new Et2Datagrid();
	el.dataProvider = {
		fetchPage: async() => ({rows: [], total: 0}),
		getDataStorePrefix: () => "addressbook",
		normalizeRowId: (rowId : string | number) => String(rowId ?? ""),
		toProviderRowId: (rowId : string) => rowId,
		refresh: async() => ({rows: [], removedRowIds: []})
	} as any;
	const host = document.createElement("et2-nextmatch");
	host.attachShadow({mode: "open"}).appendChild(el);
	el.columnPreferenceName = KEY;
	el.templateData = {
		columns: templateColumns(),
		sourceColumns: templateColumns(),
		rowTemplateId: "addressbook.index.rows",
		rowTemplate: null,
		rowTemplateXml: null,
		rowTemplateAttrMap: {},
		loaderTemplate: null
	} as any;
	// the user's own layout: reordered, phone shown, name hidden, widths changed
	el.columns = [
		{key: "phone", title: "Phone", width: "50%", hidden: false},
		{key: "email", title: "Email", width: "25%", hidden: false},
		{key: "name", title: "Name", width: "5%", hidden: true},
		{key: "customfields", title: "Custom fields", width: "20%", hidden: false}
	] as any;
	(el as any)._loadedColumnPreferenceKey = KEY;
	return el;
}

const layout = (el : Et2Datagrid) => el.columns.map((column) => (column.hidden ? "-" : "") + column.key + ":" + (column.width ?? ""));

describe("Et2Datagrid resetColumnPreferences()", () =>
{
	beforeEach(() =>
	{
		own = {[KEY]: [{key: "phone", hidden: false}], "other-pref": "kept"};
		fallback = {};
		cache = {...own};
		requests = [];
		messages = [];
		failOn = null;
		inFlight = 0;
	});

	it("deletes the user's own preferences one after the other, also those listeners add, then shows the default", async() =>
	{
		fallback = {[KEY]: [{key: "name", hidden: false}, {key: "email", hidden: true}, {key: "phone", hidden: true, width: "15%"}]};
		const el = createDatagrid();
		let reloaded = false;
		el.parentNode!.addEventListener("et2-column-selection-reset", (event : CustomEvent<Et2DatagridColumnResetDetail>) =>
		{
			event.detail.prefs.push({app: "addressbook", name: "nextmatch--lettersearch"});
			event.detail.reloaded.then(() => reloaded = true);
		});

		await el.resetColumnPreferences();

		assert.deepEqual(requests, [
			`ajax_set_preference(${KEY})`,
			"ajax_set_preference(nextmatch--lettersearch)",
			"ajax_get_preference()"
		]);
		assert.isUndefined(own[KEY], "the user's own value is gone");
		assert.equal(own["other-pref"], "kept", "other preferences stay");
		assert.deepEqual(layout(el), ["name:40%", "-email:30%", "-phone:15%", "customfields:10%"],
			"the default's order, visibility and widths, the template for what it does not mention");
		assert.isTrue(reloaded, "listeners learn the preferences are reloaded");
		assert.isEmpty(messages);
	});

	it("shows the template's columns when there is no default", async() =>
	{
		const el = createDatagrid();

		await el.resetColumnPreferences();

		assert.deepEqual(layout(el), ["name:40%", "email:30%", "-phone:20%", "customfields:10%"]);
	});

	it("takes the default a listener keeps in a form of its own, when no column preference is left", async() =>
	{
		const el = createDatagrid();
		let given : string[] = [];
		const changed : string[][] = [];
		el.parentNode!.addEventListener("et2-column-selection-reset", (event : CustomEvent<Et2DatagridColumnResetDetail>) =>
		{
			event.detail.defaultColumns = (columns) =>
			{
				given = columns.map((column) => (column.hidden ? "-" : "") + column.key);
				return columns.map((column) => column.key === "email" ? {...column, hidden: true} : column);
			};
		});
		el.addEventListener("et2-columns-changed", (event : CustomEvent) => changed.push(event.detail.columns.map((column) => column.key)));

		await el.resetColumnPreferences();

		assert.deepEqual(given, ["name", "email", "-phone", "customfields"], "it gets the template's columns");
		assert.deepEqual(layout(el), ["name:40%", "-email:30%", "-phone:20%", "customfields:10%"]);
		assert.lengthOf(changed, 1, "et2-columns-changed fires once");
	});

	it("ignores the listener's default when a column preference is effective", async() =>
	{
		fallback = {[KEY]: [{key: "email", hidden: false}, {key: "name", hidden: false}]};
		const el = createDatagrid();
		let asked = false;
		el.parentNode!.addEventListener("et2-column-selection-reset", (event : CustomEvent<Et2DatagridColumnResetDetail>) =>
		{
			event.detail.defaultColumns = (columns) => { asked = true; return columns; };
		});

		await el.resetColumnPreferences();

		assert.isFalse(asked);
		assert.deepEqual(layout(el).slice(0, 2), ["email:30%", "name:40%"]);
	});

	it("puts the custom fields header back to the template's fields", async() =>
	{
		const el = createDatagrid();
		let reset = 0;
		const header = document.createElement("div") as any;
		header.resetCustomfieldVisibility = () => reset++;
		el.columns = el.columns.map((column) => column.key === "customfields" ? {...column, header} : column);

		await el.resetColumnPreferences();

		assert.equal(reset, 1);
	});

	it("reports a failed request, keeps the columns and still tells the listeners", async() =>
	{
		failOn = "nextmatch--lettersearch";
		const el = createDatagrid();
		const before = layout(el);
		let reloaded = false;
		el.parentNode!.addEventListener("et2-column-selection-reset", (event : CustomEvent<Et2DatagridColumnResetDetail>) =>
		{
			event.detail.prefs.push({app: "addressbook", name: "nextmatch--lettersearch"});
			event.detail.reloaded.then(() => reloaded = true);
		});

		await el.resetColumnPreferences();
		await Promise.resolve();

		assert.deepEqual(messages, ["error: simulated failure"]);
		assert.deepEqual(layout(el), before, "columns stay until the next load");
		assert.notInclude(requests, "ajax_get_preference()");
		assert.isTrue(reloaded);
	});

	it("does nothing without a column preference", async() =>
	{
		const el = createDatagrid();
		el.noColumnPersistence = true;

		await el.resetColumnPreferences();

		assert.isEmpty(requests);
	});
});
