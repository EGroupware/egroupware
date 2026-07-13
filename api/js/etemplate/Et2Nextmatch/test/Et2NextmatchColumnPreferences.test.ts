import {assert} from "@open-wc/testing";
import {
	applyLegacyNextmatchColumnPreferences,
	applyLegacyCustomfieldVisibility,
	datagridColumnPreferenceValue,
	legacyVisibleCustomfieldNames
} from "../Et2NextmatchColumnPreferences.ts";

describe("Et2Nextmatch column preferences", () =>
{
	it("applies legacy customfield entries as field-level visibility", () =>
	{
		const customfieldsHeader = {};
		const columns = [
			{key: "cat_id", title: "Category"},
			{key: "type", title: "Type"},
			{key: "customfields", title: "Custom fields", header: customfieldsHeader as any},
			{key: "photo", title: "Photo"}
		];
		const legacyKeys = "cat_id,type,customfields,#Branche,#CustomerNumber,#allow_data_processing,#Newsletter,#language,#instanzname,#tagging,#VAT-ID,photo"
			.split(",");

		const visibleCustomfields = legacyVisibleCustomfieldNames(legacyKeys);
		const applied = applyLegacyCustomfieldVisibility(columns, visibleCustomfields);

		assert.isTrue(applied, "customfield visibility should be applied to the customfields header");
		assert.deepEqual(
			(customfieldsHeader as any).fields,
			{
				Branche: true,
				CustomerNumber: true,
				allow_data_processing: true,
				Newsletter: true,
				language: true,
				instanzname: true,
				tagging: true,
				"VAT-ID": true
			},
			"only customfields selected in the legacy CSV should be visible"
		);
	});

	it("keeps legacy customfield visibility on headers that are not upgraded yet", () =>
	{
		const customfieldsHeader = {};
		const columns = [
			{key: "customfields", title: "Custom fields", header: customfieldsHeader as any}
		];

		const applied = applyLegacyCustomfieldVisibility(columns, ["cf_text", "cf_private"]);

		assert.isTrue(applied, "legacy customfield visibility should be stored for a pending header");
		assert.deepEqual(
			(customfieldsHeader as any).fields,
			{cf_text: true, cf_private: true},
			"pending header should receive fields for Lit/custom element upgrade"
		);
		assert.deepEqual(
			columns[0].customFields,
			["cf_text", "cf_private"],
			"column should carry legacy selected customfields until the header is ready"
		);
	});

	it("normalizes legacy customfield visibility into datagrid preference value", () =>
	{
		const columns = [
			{key: "customfields", title: "Custom fields"},
			{key: "subject", title: "Subject", hidden: true}
		];
		const nextColumns = applyLegacyNextmatchColumnPreferences(
			columns,
			"customfields,#cf_text,#cf_private",
			null
		);

		assert.deepEqual(
			datagridColumnPreferenceValue(nextColumns),
			[
				{
					key: "customfields",
					width: undefined,
					hidden: false,
					customFields: ["cf_text", "cf_private"]
				},
				{
					key: "subject",
					width: undefined,
					hidden: true,
					customFields: undefined
				}
			],
			"Datagrid should receive resolved structured customfield preferences"
		);
	});

	it("applies legacy Nextmatch order, widths, fuzzy columns, and customfields", () =>
	{
		const customfieldsHeader = {};
		const columns = [
			{key: "cat_id", title: "Category", width: "60px"},
			{key: "type", title: "Type", width: "40px"},
			{key: "n_fileas_n_given_n_family_org_name", title: "Name"},
			{key: "business_adr_one_countrycode", title: "Business address"},
			{key: "customfields", title: "Custom fields", header: customfieldsHeader as any},
			{key: "photo", title: "Photo"},
			{key: "room", title: "Room"}
		];
		const legacyVisibility = [
			"cat_id",
			"type",
			"n_fileas_n_given_n_family_n_family_n_given_org_name_n_family_n_given_n_fileas",
			"business_adr_one_countrycode_adr_one_postalcode",
			"customfields",
			"#Branche",
			"#CustomerNumber",
			"photo"
		].join(",");

		const nextColumns = applyLegacyNextmatchColumnPreferences(
			columns,
			legacyVisibility,
			JSON.stringify({type: "55px", photo: "75px"})
		);

		assert.deepEqual(
			nextColumns.map((column) => column.key),
			["cat_id", "type", "n_fileas_n_given_n_family_org_name", "business_adr_one_countrycode", "customfields", "photo", "room"],
			"legacy preference order should apply to selected slots and keep unselected columns in original slots"
		);
		assert.deepEqual(
			nextColumns.filter((column) => column.hidden !== true).map((column) => column.key),
			["cat_id", "type", "n_fileas_n_given_n_family_org_name", "business_adr_one_countrycode", "customfields", "photo"],
			"only legacy-selected top-level columns should be visible"
		);
		assert.equal(nextColumns.find((column) => column.key === "type")?.width, "55px", "legacy width should apply by key");
		assert.equal(nextColumns.find((column) => column.key === "photo")?.width, "75px", "legacy width should apply after ordering");
		assert.deepEqual(
			(customfieldsHeader as any).fields,
			{Branche: true, CustomerNumber: true},
			"legacy customfield entries should apply field-level visibility"
		);
	});
});
