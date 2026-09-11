import {assert} from "@open-wc/testing";
import {legacyVisibility, sampleCustomfields} from "./legacyVisibilityHelper";

type LegacyCreationField = {
	label? : string;
	type? : string;
	rows? : number;
	len? : number;
	account_type? : string;
	only_app? : string;
	onlyApp? : string;
	values? : Record<string, any>;
};

const legacyFilterAllowsField = (field : LegacyCreationField, apps : Record<string, any> = {}) =>
{
	const type = String(field?.type || "");
	return type.startsWith("select") || (
		type !== "filemanager" &&
		typeof apps[type] !== "undefined"
	);
};

const legacyWidgetCreation = (input : {
	fieldName : string;
	field : LegacyCreationField;
	mode? : "customfields" | "customfields-list" | "customfields-filters";
	apps? : Record<string, any>;
	readonly? : boolean;
}) =>
{
	const field = {...input.field};
	const mode = input.mode || "customfields";
	const apps = input.apps || {};
	const attrs : Record<string, any> = {
		id: "#" + input.fieldName,
		readonly: input.readonly === true
	};
	const type = String(field.type || "text");
	const setupFunction = "_setup_" + (apps[type] ? "link_entry" : type.replace("-", "_"));

	if(mode === "customfields-filters" && !legacyFilterAllowsField(field, apps))
	{
		return {
			created: false,
			widgetType: null,
			setupFunction,
			reason: "filter-disallowed",
			attrs
		};
	}

	let created = true;
	let reason : string | undefined;

	switch(setupFunction)
	{
		case "_setup_text":
			field.type = field.rows && field.rows > 1 ? "textarea" : "textbox";
			if(field.len)
			{
				attrs.size = field.len;
				if(field.rows === 1)
				{
					attrs.maxlength = field.len;
				}
			}
			if(attrs.readonly)
			{
				field.type = "description";
			}
			break;

		case "_setup_serial":
			field.type = "textbox";
			attrs.readonly = true;
			break;

		case "_setup_int":
			field.type = "number";
			attrs.precision = 0;
			break;

		case "_setup_select":
			attrs.rows = field.rows;
			if(attrs.rows > 1)
			{
				attrs.multiple = true;
			}
			if(field.values && field.values["@"])
			{
				attrs.searchUrl = field.values["@"];
			}
			break;

		case "_setup_select_account":
			attrs.empty_label = "Select";
			if(field.account_type)
			{
				attrs.account_type = field.account_type;
			}
			attrs.rows = field.rows;
			if(attrs.rows > 1)
			{
				attrs.multiple = true;
			}
			break;

		case "_setup_radio":
			field.type = "radiogroup";
			attrs.options = field.values;
			break;

		case "_setup_button":
			if(mode !== "customfields")
			{
				created = false;
				reason = "button-not-created-outside-customfields";
			}
			else if(attrs.readonly)
			{
				created = false;
				reason = "readonly-button-skipped";
			}
			break;

		case "_setup_link_entry":
			if(type === "filemanager")
			{
				attrs.type = "et2-vfs-upload";
				if(mode === "customfields")
				{
					created = false;
					reason = "filemanager-customfields-special-case";
				}
			}
			else
			{
				attrs.type = "link-entry";
				attrs[attrs.readonly ? "app" : "onlyApp"] = typeof field.only_app === "undefined" ?
					type :
					(field.onlyApp ?? field.only_app);
				attrs.searchOptions = {filter: field.values || {}};
			}
			break;
	}

	return {
		created,
		widgetType: created ? String(attrs.type || field.type || type) : null,
		setupFunction,
		reason,
		attrs
	};
};

/**
 * Contract under test:
 * - Legacy customfield visibility/filtering behavior from `et2_extension_customfields`
 *   is captured in deterministic baseline tests for migration safety.
 *
 * Setup strategy:
 * - Use a pure helper that mirrors constructor filtering branches.
 * - Evaluate type-filter, exclude, tab, and default-tab/private paths.
 *
 * Pass criteria:
 * - Returned visibility maps match expected per-field booleans for each scenario.
 *
 * Environment note:
 * - This suite is intentionally separated as legacy baseline (`Legacy*` naming)
 *   and may be removed once migration is fully complete.
 */
describe("Legacy customfields visibility baseline", () =>
{
	it("applies type_filter across type2 values and unrestricted fields", () =>
	{
		const visibility = legacyVisibility({
			customfields: sampleCustomfields,
			typeFilter: "task"
		});
		assert.deepEqual(visibility, {
			cf_text: true,
			cf_project: true,
			cf_private: true,
			cf_file: true
		}, "task type_filter should keep matching and unrestricted customfields visible");
	});

	it("supports type_filter=previous by reusing prior filter state", () =>
	{
		legacyVisibility({
			customfields: sampleCustomfields,
			typeFilter: "project"
		});
		const visibility = legacyVisibility({
			customfields: sampleCustomfields,
			typeFilter: "previous"
		});
		assert.deepEqual(visibility, {
			cf_text: false,
			cf_project: true,
			cf_private: true,
			cf_file: true
		}, "previous should reuse project filter and hide non-matching typed fields");
	});

	it("applies exclude after explicit field selection", () =>
	{
		const visibility = legacyVisibility({
			customfields: sampleCustomfields,
			fields: {cf_text: true, cf_private: true},
			exclude: "cf_private"
		});
		assert.deepEqual(visibility, {
			cf_text: true,
			cf_project: false,
			cf_private: false,
			cf_file: false
		}, "excluded customfields must be hidden even if explicitly selected");
	});

	it("filters by tab when no explicit fields are provided", () =>
	{
		const visibility = legacyVisibility({
			customfields: sampleCustomfields,
			tab: "extra"
		});
		assert.deepEqual(visibility, {
			cf_text: true,
			cf_project: true,
			cf_private: true,
			cf_file: true
		}, "fields without a tab stay visible while matching tab-specific fields remain visible");
	});

	it("hides tab-specific fields when the active tab does not match", () =>
	{
		const visibility = legacyVisibility({
			customfields: sampleCustomfields,
			tab: "missing"
		});
		assert.deepEqual(visibility, {
			cf_text: true,
			cf_project: false,
			cf_private: true,
			cf_file: true
		}, "default visibility is all fields, constrained by tab-specific customfields");
	});

	it("applies default tab private split rules", () =>
	{
		const visibility = legacyVisibility({
			customfields: sampleCustomfields,
			defaultTabMatch: "-private"
		});
		assert.deepEqual(visibility, {
			cf_text: false,
			cf_project: false,
			cf_private: true,
			cf_file: false
		}, "private default tab should keep only private customfields visible");
	});
});

/**
 * Contract under test:
 * - Legacy customfield widget creation maps customfield `type` settings to the
 *   widget type/attrs used by `et2_extension_customfields.loadFields()`.
 *
 * Setup strategy:
 * - Use the pure legacy setup helper instead of instantiating legacy jQuery
 *   widgets. Cover representative setup branches and filter-mode skips.
 *
 * Pass criteria:
 * - Returned widget type, setup function, creation flag, and key attrs match
 *   the legacy setup contract for each customfield type.
 *
 * Environment note:
 * - `nextmatch-customfields` is intentionally excluded; its header path is
 *   covered separately by webcomponent tests.
 */
describe("Legacy customfields type-to-widget baseline", () =>
{
	it("maps text customfields by row count and readonly state", () =>
	{
		assert.include(
			legacyWidgetCreation({
				fieldName: "cf_text",
				field: {type: "text", rows: 1, len: 40}
			}),
			{
				created: true,
				widgetType: "textbox",
				setupFunction: "_setup_text"
			},
			"single-row text customfields should use textbox"
		);
		assert.deepInclude(
			legacyWidgetCreation({
				fieldName: "cf_textarea",
				field: {type: "text", rows: 4, len: 60}
			}),
			{
				created: true,
				widgetType: "textarea",
				setupFunction: "_setup_text"
			},
			"multi-row text customfields should use textarea"
		);
		assert.deepInclude(
			legacyWidgetCreation({
				fieldName: "cf_readonly",
				field: {type: "text", rows: 1},
				readonly: true
			}),
			{
				created: true,
				widgetType: "description",
				setupFunction: "_setup_text"
			},
			"readonly text customfields should render as description"
		);
	});

	it("maps numeric, serial, and radio customfields to their legacy widget types", () =>
	{
		assert.deepInclude(
			legacyWidgetCreation({fieldName: "cf_int", field: {type: "int"}}),
			{created: true, widgetType: "number", setupFunction: "_setup_int"},
			"integer customfields should use number widgets"
		);
		assert.equal(
			legacyWidgetCreation({fieldName: "cf_int", field: {type: "int"}}).attrs.precision,
			0,
			"integer customfields should set zero precision"
		);
		assert.deepInclude(
			legacyWidgetCreation({fieldName: "cf_serial", field: {type: "serial"}}),
			{created: true, widgetType: "textbox", setupFunction: "_setup_serial"},
			"serial customfields should use readonly textboxes"
		);
		assert.isTrue(
			legacyWidgetCreation({fieldName: "cf_serial", field: {type: "serial"}}).attrs.readonly,
			"serial customfields should force readonly"
		);
		assert.deepInclude(
			legacyWidgetCreation({fieldName: "cf_radio", field: {type: "radio", values: {a: "A"}}}),
			{created: true, widgetType: "radiogroup", setupFunction: "_setup_radio"},
			"radio customfields should use radiogroup"
		);
	});

	it("maps select customfields and select-account settings", () =>
	{
		const select = legacyWidgetCreation({
			fieldName: "cf_select",
			field: {type: "select", rows: 3, values: {"@": "customfields/options"}}
		});
		assert.deepInclude(
			select,
			{created: true, widgetType: "select", setupFunction: "_setup_select"},
			"select customfields should use select widgets"
		);
		assert.deepInclude(
			select.attrs,
			{rows: 3, multiple: true, searchUrl: "customfields/options"},
			"multi-row select customfields should enable multiple and searchUrl"
		);

		const account = legacyWidgetCreation({
			fieldName: "cf_account",
			field: {type: "select-account", rows: 1, account_type: "groups"}
		});
		assert.deepInclude(
			account,
			{created: true, widgetType: "select-account", setupFunction: "_setup_select_account"},
			"select-account customfields should use select-account widgets"
		);
		assert.deepInclude(
			account.attrs,
			{empty_label: "Select", account_type: "groups"},
			"select-account customfields should set account-specific attrs"
		);
	});

	it("maps app-backed customfields to link-entry and handles filemanager separately", () =>
	{
		const appBacked = legacyWidgetCreation({
			fieldName: "cf_project",
			field: {type: "project", values: {filter: "active"}},
			apps: {project: true}
		});
		assert.deepInclude(
			appBacked,
			{created: true, widgetType: "link-entry", setupFunction: "_setup_link_entry"},
			"app-backed customfields should use link-entry"
		);
		assert.deepInclude(
			appBacked.attrs,
			{onlyApp: "project", searchOptions: {filter: {filter: "active"}}},
			"app-backed customfields should restrict link-entry to the app and pass filter settings"
		);

		assert.deepInclude(
			legacyWidgetCreation({
				fieldName: "cf_file",
				field: {type: "filemanager"},
				apps: {filemanager: true},
				mode: "customfields-list"
			}),
			{created: true, widgetType: "et2-vfs-upload", setupFunction: "_setup_link_entry"},
			"filemanager list customfields should use the upload widget type"
		);
	});

	it("skips disallowed customfields in filter mode", () =>
	{
		assert.isTrue(
			legacyFilterAllowsField({type: "select"}, {}),
			"filter mode should allow select fields"
		);
		assert.isTrue(
			legacyFilterAllowsField({type: "project"}, {project: true}),
			"filter mode should allow app-backed fields"
		);
		assert.isFalse(
			legacyFilterAllowsField({type: "filemanager"}, {filemanager: true}),
			"filter mode should reject filemanager"
		);
		assert.deepInclude(
			legacyWidgetCreation({
				fieldName: "cf_text",
				field: {type: "text"},
				mode: "customfields-filters"
			}),
			{
				created: false,
				widgetType: null,
				setupFunction: "_setup_text",
				reason: "filter-disallowed"
			},
			"filter mode should skip non-select non-app fields"
		);
	});

	it("skips button customfields outside the edit customfields widget", () =>
	{
		assert.deepInclude(
			legacyWidgetCreation({
				fieldName: "cf_button",
				field: {type: "button"},
				mode: "customfields-list"
			}),
			{
				created: false,
				widgetType: null,
				setupFunction: "_setup_button",
				reason: "button-not-created-outside-customfields"
			},
			"button customfields should not be created in list/filter widgets"
		);
		assert.deepInclude(
			legacyWidgetCreation({
				fieldName: "cf_button",
				field: {type: "button"},
				mode: "customfields"
			}),
			{
				created: true,
				widgetType: "button",
				setupFunction: "_setup_button"
			},
			"button customfields should be created in the edit customfields widget"
		);
	});
});
