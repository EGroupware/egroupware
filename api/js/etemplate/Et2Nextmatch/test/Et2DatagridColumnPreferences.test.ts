import {assert} from "@open-wc/testing";
import {Et2Datagrid} from "../Et2Datagrid";

const egw = {
	debug: () => {},
	lang: (label : string) => label,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	preference: (..._args : any[]) => null,
	set_preference: (..._args : any[]) => {},
	app_name: () => "addressbook",
	link: (url : string) => url
};
let preferenceCalls : { app : string; key : string; value : any }[] = [];
egw.set_preference = (app : string, key : string, value : any) =>
{
	preferenceCalls.push({app, key, value});
};

window.egw = function() { return egw; } as any;
Object.assign(window.egw, egw);

function createDatagridDataProvider(overrides : Record<string, any> = {}, prefix : string = "addressbook")
{
	return {
		fetchPage: async() => ({rows: [], total: 0}),
		getDataStorePrefix: () => prefix,
		normalizeRowId: (rowId : string | number, ensurePrefix? : boolean) =>
		{
			const normalized = String(rowId ?? "");
			return ensurePrefix && !normalized.startsWith(`${prefix}::`) ? `${prefix}::${normalized}` : normalized;
		},
		toProviderRowId: (rowId : string) => rowId.replace(new RegExp(`^${prefix}::`), ""),
		refresh: async() => ({rows: [], removedRowIds: []}),
		...overrides
	};
}

function createDatagrid() : Et2Datagrid
{
	const el = new Et2Datagrid();
	el.dataProvider = createDatagridDataProvider() as any;
	return el;
}

describe("Et2Datagrid column preferences", () =>
{
	it("replays saved customfield preferences as selected fields", () =>
	{
		const storedPreference = [
			{
				key: "customfields",
				hidden: false,
				customFields: ["cf_visible"]
			}
		];
		const originalPreference = egw.preference;
		const preference = (key : string) => key === "nextmatch-addressbook.index.rows-prefs" ? storedPreference : null;
		egw.preference = preference;
		(window.egw as any).preference = preference;
		try
		{
			const el = createDatagrid();
			const header = document.createElement("div");
			const host = document.createElement("et2-nextmatch");
			host.attachShadow({mode: "open"}).appendChild(el);
			el.columnPreferenceName = "nextmatch-addressbook.index.rows-prefs";
			el.templateData = {
				columns: [{key: "customfields", title: "Custom fields", header: header as any}],
				rowTemplateId: "addressbook.index.rows",
				rowTemplate: null,
				rowTemplateXml: null,
				rowTemplateAttrMap: {},
				loaderTemplate: null
			} as any;
			el.columns = [{key: "customfields", title: "Custom fields", header: header as any}] as any;

			(el as any)._loadColumnPreferencesIfNeeded();

			assert.equal(
				header.getAttribute("fields"),
				"cf_visible",
				"stored selected customfields should be set on the header before upgrade"
			);
		}
		finally
		{
			egw.preference = originalPreference;
			(window.egw as any).preference = originalPreference;
		}
	});

	it("uses source column order to align row cells after preference reordering", () =>
	{
		const el = createDatagrid();
		const rowTemplate = document.createElement("template");
		rowTemplate.innerHTML = `
			<tr>
				<td>A cell</td>
				<td>B cell</td>
				<td>C cell</td>
			</tr>
		`;

		el.columns = [
			{key: "b", title: "B"},
			{key: "a", title: "A"},
			{key: "c", title: "C"}
		] as any;
		const sourceColumns = [
			{key: "a", title: "A"},
			{key: "b", title: "B"},
			{key: "c", title: "C"}
		];
		el.templateData = {
			columns: el.columns,
			sourceColumns,
			rowTemplate,
			rowTemplateXml: null,
			rowTemplateAttrMap: {},
			loaderTemplate: null
		} as any;
		(el as any).willUpdate(new Map([["templateData", null]]));

		const rowElement = (el as any)._buildRowElement({id: "row-0", data: {}}, 0) as HTMLTableRowElement | null;
		assert.isNotNull(rowElement, "row should be built from template");

		const visibleCells = Array.from(rowElement!.querySelectorAll(":scope > td:not([data-dg-meta-cell])")) as HTMLElement[];
		assert.deepEqual(
			visibleCells.map((cell) => cell.getAttribute("data-col-key")),
			["b", "a", "c"],
			"row cells should be reordered to visible column order"
		);
		assert.deepEqual(
			visibleCells.map((cell) => cell.textContent?.trim()),
			["B cell", "A cell", "C cell"],
			"cell contents should stay associated with their original source columns"
		);
	});

	it("derives default structured preference key from owner tag and row template id", () =>
	{
		preferenceCalls = [];
		const host = document.createElement("et2-nextmatch");
		const el = createDatagrid();
		host.attachShadow({mode: "open"}).appendChild(el);

		el.templateData = {
			columns: [{key: "a", title: "A", width: "1fr"}],
			rowTemplateId: "addressbook.index.rows",
			rowTemplate: null,
			rowTemplateXml: null,
			rowTemplateAttrMap: {},
			loaderTemplate: null
		} as any;
		el.columns = [{key: "a", title: "A", width: "1fr"}] as any;
		(el as any)._persistColumnPreferences();

		const structuredPreference = preferenceCalls.find((call) => call.key === "nextmatch-addressbook.index.rows-prefs");
		assert.isDefined(structuredPreference, "structured preference should be saved on column change");
		assert.equal(structuredPreference!.app, "addressbook", "app name should come from egw app context");

		// Et2Datagrid only ever persists its own structured preference - it has no concept of
		// the legacy Nextmatch CSV format. That compatibility write is Et2Nextmatch's
		// responsibility (see Et2Nextmatch's own column-preferences tests).
		assert.isUndefined(
			preferenceCalls.find((call) => call.key === "nextmatch-addressbook.index.rows"),
			"Et2Datagrid must not write anything under the legacy row-template key"
		);
	});

	it("uses explicit columnPreferenceName override when provided", () =>
	{
		preferenceCalls = [];
		const host = document.createElement("et2-nextmatch");
		const el = createDatagrid();
		el.columnPreferenceName = "my-custom-key";
		host.attachShadow({mode: "open"}).appendChild(el);

		el.templateData = {
			columns: [{key: "a", title: "A", width: "1fr"}],
			rowTemplateId: "addressbook.index.rows",
			rowTemplate: null,
			rowTemplateXml: null,
			rowTemplateAttrMap: {},
			loaderTemplate: null
		} as any;
		el.columns = [{key: "a", title: "A", width: "1fr"}] as any;
		(el as any)._persistColumnPreferences();

		const structuredPreference = preferenceCalls.find((call) => call.key === "my-custom-key");
		assert.isDefined(structuredPreference, "custom key override should be used for structured preference");
		assert.isTrue(Array.isArray(structuredPreference!.value), "structured preference must stay the modern {key,hidden,...} array shape");

		// Et2Datagrid has no concept of the legacy CSV format at all, so it must not write to
		// either the custom key or the default row-template key in that shape - that's
		// Et2Nextmatch's job now, decoupled entirely from columnPreferenceName.
		assert.isUndefined(
			preferenceCalls.find((call) => typeof call.value === "string"),
			"Et2Datagrid must never write a CSV-format preference, regardless of columnPreferenceName"
		);
	});

	it("keeps infolog's filter2 'details' toggle as two independent structured preference keys", () =>
	{
		// Infolog computes `columnselection_pref` server-side as 'nextmatch-infolog.index.rows'
		// plus '-details' when filter2 === 'all', then reads $this->prefs[$columnselection_pref]
		// back (via infolog_ui::columnselection_csv(), which accepts either a legacy CSV string or
		// Et2Datagrid's structured array) to decide show_times and which columns to hide on the
		// embedded "sp" sub-entry view. Et2Nextmatch forwards that setting to columnPreferenceName,
		// so the structured preference must land under the exact key for each state - a shared,
		// details-agnostic key would silently drop that distinction (see the
		// et2-nextmatch-conversion.md case study). The legacy CSV-format compatibility write is
		// Et2Nextmatch's responsibility, not Et2Datagrid's - see Et2Nextmatch's own tests for that
		// half of the behaviour, including the case where infolog's no-details key happens to
		// coincide with the legacy `nextmatch-<rowTemplateId>` key.
		preferenceCalls = [];
		const originalAppName = egw.app_name;
		egw.app_name = () => "infolog";
		(window.egw as any).app_name = egw.app_name;
		try
		{
			const templateData = {
				columns: [{key: "info_used_time_info_planned_time", title: "Time", width: "1fr"}],
				rowTemplateId: "infolog.index.rows",
				rowTemplate: null,
				rowTemplateXml: null,
				rowTemplateAttrMap: {},
				loaderTemplate: null
			} as any;
			const columns = [{key: "info_used_time_info_planned_time", title: "Time", width: "1fr"}] as any;

			const details = createDatagrid();
			document.createElement("et2-nextmatch").attachShadow({mode: "open"}).appendChild(details);
			details.columnPreferenceName = "nextmatch-infolog.index.rows-details";
			details.templateData = templateData;
			details.columns = columns;
			(details as any)._persistColumnPreferences();

			const noDetails = createDatagrid();
			document.createElement("et2-nextmatch").attachShadow({mode: "open"}).appendChild(noDetails);
			noDetails.columnPreferenceName = "nextmatch-infolog.index.rows";
			noDetails.templateData = templateData;
			noDetails.columns = columns;
			(noDetails as any)._persistColumnPreferences();

			const detailsStructured = preferenceCalls.find((call) =>
				call.key === "nextmatch-infolog.index.rows-details" && Array.isArray(call.value));
			const noDetailsStructured = preferenceCalls.find((call) =>
				call.key === "nextmatch-infolog.index.rows" && Array.isArray(call.value));
			assert.isDefined(detailsStructured, "details state should persist its own structured preference under its own key");
			assert.isDefined(noDetailsStructured, "no-details state should persist its own structured preference, distinct from details");
			assert.equal(detailsStructured!.app, "infolog");
			assert.equal(noDetailsStructured!.app, "infolog");
			assert.isUndefined(
				preferenceCalls.find((call) => typeof call.value === "string"),
				"Et2Datagrid must never write a CSV-format value, regardless of what columnPreferenceName happens to look like"
			);
		}
		finally
		{
			egw.app_name = originalAppName;
			(window.egw as any).app_name = originalAppName;
		}
	});

	it("does not let a later preference reload clobber applyExternalColumns()", () =>
	{
		preferenceCalls = [];
		const storedPreference = [
			{key: "a", hidden: false},
			{key: "b", hidden: true}
		];
		const originalPreference = egw.preference;
		const preference = (key : string) => key === "nextmatch-addressbook.index.rows-prefs" ? storedPreference : null;
		egw.preference = preference;
		(window.egw as any).preference = preference;
		try
		{
			const el = createDatagrid();
			const host = document.createElement("et2-nextmatch");
			host.attachShadow({mode: "open"}).appendChild(el);
			el.columnPreferenceName = "nextmatch-addressbook.index.rows-prefs";
			el.templateData = {
				columns: [{key: "a", title: "A"}, {key: "b", title: "B"}],
				rowTemplateId: "addressbook.index.rows",
				rowTemplate: null,
				rowTemplateXml: null,
				rowTemplateAttrMap: {},
				loaderTemplate: null
			} as any;
			el.columns = [{key: "a", title: "A"}, {key: "b", title: "B"}] as any;

			// set_columns()/setColumns() (favorites, app state restore) is called
			// before the preference for this key has ever been loaded - the real
			// race that lets a later reload clobber it. It explicitly asks for
			// "b" to be visible, bypassing the stored preference (which hides it).
			(el as any).applyExternalColumns([
				{key: "a", title: "A", hidden: false},
				{key: "b", title: "B", hidden: false}
			]);

			// Simulates the willUpdate() cycle that applyExternalColumns()'s own
			// `columns` assignment triggers - it must not merge the stored
			// preference and hide "b" again just because this is the first
			// time the preference for this key would otherwise be loaded.
			(el as any)._loadColumnPreferencesIfNeeded();

			assert.isFalse(
				el.columns.find((column) => column.key === "b")!.hidden,
				"applyExternalColumns() selection should survive the willUpdate cycle it triggers"
			);
			assert.equal(preferenceCalls.length, 0, "applyExternalColumns() must not write the column preference");
		}
		finally
		{
			egw.preference = originalPreference;
			(window.egw as any).preference = originalPreference;
		}
	});

	it("applyExternalColumns() overrides an already-loaded preference and survives further reloads", () =>
	{
		preferenceCalls = [];
		const storedPreference = [
			{key: "a", hidden: false},
			{key: "b", hidden: true},
			{key: "c", hidden: false}
		];
		const originalPreference = egw.preference;
		const preference = (key : string) => key === "nextmatch-addressbook.index.rows-prefs" ? storedPreference : null;
		egw.preference = preference;
		(window.egw as any).preference = preference;
		try
		{
			const el = createDatagrid();
			const host = document.createElement("et2-nextmatch");
			host.attachShadow({mode: "open"}).appendChild(el);
			el.columnPreferenceName = "nextmatch-addressbook.index.rows-prefs";
			el.templateData = {
				columns: [{key: "a", title: "A"}, {key: "b", title: "B"}, {key: "c", title: "C"}],
				rowTemplateId: "addressbook.index.rows",
				rowTemplate: null,
				rowTemplateXml: null,
				rowTemplateAttrMap: {},
				loaderTemplate: null
			} as any;
			el.columns = [{key: "a", title: "A"}, {key: "b", title: "B"}, {key: "c", title: "C"}] as any;

			// Preference is already loaded (grid rendered normally first) before
			// the favorites restore runs - the scenario distinct from the race
			// above.
			(el as any)._loadColumnPreferencesIfNeeded();
			assert.deepEqual(
				el.columns.map((column) => ({key: column.key, hidden: !!column.hidden})),
				[
					{key: "a", hidden: false},
					{key: "b", hidden: true},
					{key: "c", hidden: false}
				],
				"preference should be applied before the favorites override"
			);

			// Favorites asks for only "b" to be visible - the opposite of, and a
			// strict subset of, what the stored preference says.
			(el as any).applyExternalColumns([
				{key: "a", title: "A", hidden: true},
				{key: "b", title: "B", hidden: false},
				{key: "c", title: "C", hidden: true}
			]);
			assert.deepEqual(
				el.columns.map((column) => ({key: column.key, hidden: !!column.hidden})),
				[
					{key: "a", hidden: true},
					{key: "b", hidden: false},
					{key: "c", hidden: true}
				],
				"only the favorite-specified columns should be visible"
			);

			// A later reload (unrelated re-render churn) must not revert the
			// favorites selection back to the stored preference.
			(el as any)._loadColumnPreferencesIfNeeded();
			assert.deepEqual(
				el.columns.map((column) => ({key: column.key, hidden: !!column.hidden})),
				[
					{key: "a", hidden: true},
					{key: "b", hidden: false},
					{key: "c", hidden: true}
				],
				"favorites selection should survive a later preference reload"
			);
			assert.equal(preferenceCalls.length, 0, "favorites override must not write the column preference");
		}
		finally
		{
			egw.preference = originalPreference;
			(window.egw as any).preference = originalPreference;
		}
	});
});
