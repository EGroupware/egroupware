import {assert} from "@open-wc/testing";
import {Et2CustomfieldsController, mergeCustomfieldSettingsFromSources} from "../Et2CustomfieldsController.ts";
import {legacyVisibility, sampleCustomfields} from "./legacyVisibilityHelper";

/**
 * Contract under test:
 * - `Et2CustomfieldsController` must preserve legacy visibility/filter outcomes
 *   for covered migration scenarios.
 * - Filter field allowance matches legacy type gate semantics.
 *
 * Setup strategy:
 * - Reuse the same sample customfield definitions and compare controller output
 *   against legacy baseline helper output.
 *
 * Pass criteria:
 * - Controller visible-map equals legacy baseline maps for each scenario.
 * - `isAllowedFilterField()` allows select/app fields and blocks filemanager.
 *
 * Environment note:
 * - These tests intentionally target deterministic controller behavior only.
 */
describe("Et2CustomfieldsController", () =>
{
	const compareToLegacy = (name : string, input : any) =>
	{
		const legacy = legacyVisibility({
			customfields: sampleCustomfields,
			...input
		});
		const controller = new Et2CustomfieldsController({
			customfields: sampleCustomfields,
			...input
		});
		assert.deepEqual(controller.getVisibleMap(), legacy, name);
	};

	it("matches legacy type_filter behavior", () =>
	{
		compareToLegacy("type_filter=task mismatch", {typeFilter: "task"});
	});

	it("matches legacy type_filter previous behavior", () =>
	{
		legacyVisibility({
			customfields: sampleCustomfields,
			typeFilter: "project"
		});
		new Et2CustomfieldsController({
			customfields: sampleCustomfields,
			typeFilter: "project"
		});
		compareToLegacy("type_filter=previous mismatch", {typeFilter: "previous"});
	});

	it("matches legacy explicit fields + exclude behavior", () =>
	{
		compareToLegacy("explicit fields + exclude mismatch", {
			fields: {cf_text: true, cf_private: true},
			exclude: "cf_private"
		});
	});

	it("matches legacy default private tab visibility behavior", () =>
	{
		compareToLegacy("default private tab mismatch", {
			defaultTabMatch: "-private"
		});
	});

	it("matches legacy tab-filter behavior", () =>
	{
		compareToLegacy("tab filter mismatch", {tab: "extra"});
	});

	it("keeps selection-item visibility aligned with current map", () =>
	{
		const controller = new Et2CustomfieldsController({
			customfields: sampleCustomfields,
			fields: {cf_text: true, cf_project: false, cf_private: true, cf_file: false}
		});
		const itemMap = controller.getSelectionItems().reduce((result : Record<string, boolean>, item) =>
		{
			result[item.name] = item.visible;
			return result;
		}, {});
		assert.deepEqual(itemMap, controller.getVisibleMap(), "selection items should mirror visibility state");
	});

	it("applies legacy filter field type allowance rules", () =>
	{
		const controller = new Et2CustomfieldsController({
			customfields: sampleCustomfields
		});
		assert.isTrue(controller.isAllowedFilterField(sampleCustomfields.cf_project, {project: true}), "app-backed fields should be allowed as filters");
		assert.isTrue(controller.isAllowedFilterField({type: "select"}, {}), "select fields should be allowed as filters");
		assert.isFalse(controller.isAllowedFilterField(sampleCustomfields.cf_file, {filemanager: true}), "filemanager should not be allowed as a filter");
	});

	it("applies tab limits to default visibility", () =>
	{
		const controller = new Et2CustomfieldsController({
			customfields: sampleCustomfields,
			tab: "missing"
		});
		assert.deepEqual(controller.getVisibleMap(), {
			cf_text: true,
			cf_project: false,
			cf_private: true,
			cf_file: true
		}, "tab-specific customfields should be hidden when their tab does not match");
	});

	it("normalizes array-shaped customfields by field name for chooser labels", () =>
	{
		const controller = new Et2CustomfieldsController({
			customfields: [
				{name: "cf_text", label: "Text"},
				{name: "cf_project", label: "Project"}
			],
			fields: {cf_text: true, cf_project: false}
		});

		const items = controller.getSelectionItems();
		assert.deepEqual(
			items.map((item) => item.name),
			["cf_text", "cf_project"],
			"selection names should come from field metadata, not numeric indexes"
		);
		assert.deepEqual(
			items.map((item) => item.label),
			["Text", "Project"],
			"selection labels should use customfield labels"
		);
	});

	it("prefers inner field name over outer key for visibility and labels", () =>
	{
		const controller = new Et2CustomfieldsController({
			customfields: {
				"0": {name: "cf_text", label: "Text"},
				"1": {name: "cf_date", label: "Date"}
			},
			fields: {"0": true, "1": false}
		});

		assert.deepEqual(
			controller.getVisibleMap(),
			{cf_text: true, cf_date: false},
			"explicit visibility keyed by outer id/index should map to inner field names"
		);
		assert.deepEqual(
			controller.getSelectionItems().map((item) => item.name),
			["cf_text", "cf_date"],
			"selection item names should use inner field names"
		);
	});

	it("merges widget and global customfield settings into missing attrs", () =>
	{
		const attrs : Record<string, any> = {
			exclude: "",
			typeFilter: null
		};
		const changed = mergeCustomfieldSettingsFromSources(
			attrs,
			{fields: {cf_text: true}},
			{customfields: {cf_text: {name: "cf_text", label: "Text"}}, exclude: "cf_private"}
		);
		assert.isTrue(changed, "merge should report changed attrs");
		assert.deepEqual(attrs.fields, {cf_text: true}, "local fields should be applied");
		assert.deepEqual(attrs.customfields, {cf_text: {name: "cf_text", label: "Text"}}, "global customfields should fill missing attrs");
		assert.equal(attrs.exclude, "", "explicit attrs should not be overwritten");
	});

	it("preserves explicit fields while hydrating missing customfield definitions", () =>
	{
		const attrs : Record<string, any> = {
			fields: {cf_text: false, cf_private: true},
			customfields: {}
		};
		const changed = mergeCustomfieldSettingsFromSources(
			attrs,
			{},
			{
				customfields: {
					cf_text: {name: "cf_text", label: "Text"},
					cf_private: {name: "cf_private", label: "Private"}
				},
				fields: {cf_text: true, cf_private: false}
			}
		);

		assert.isTrue(changed, "merge should fill missing customfield definitions");
		assert.deepEqual(
			attrs.fields,
			{cf_text: false, cf_private: true},
			"existing field visibility must not be overwritten by source defaults"
		);
		assert.deepEqual(
			Object.keys(attrs.customfields),
			["cf_text", "cf_private"],
			"missing customfield definitions should be hydrated"
		);
	});

	it("treats empty fields object as missing and fills from source", () =>
	{
		const attrs : Record<string, any> = {
			fields: {}
		};
		const changed = mergeCustomfieldSettingsFromSources(
			attrs,
			{fields: {cf_text: true}},
			{}
		);
		assert.isTrue(changed, "merge should update missing fields");
		assert.deepEqual(attrs.fields, {cf_text: true}, "source fields should be applied when target fields map is empty");
	});
});
