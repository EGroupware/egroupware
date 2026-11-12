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

	/**
	 * Ticket #127781: the legacy baseline itself has a bug here (reproduced faithfully by
	 * legacyVisibility() for the OTHER parity tests in this file) - an untabbed customfield fell
	 * through every branch to a bare `else { visible = true }`, so it showed on every NAMED tab,
	 * not just the cf-default catch-all it actually belongs to. On a dialog with an untabbed
	 * field plus at least one named tab, that meant two `<et2-customfields>` instances (sharing
	 * Et2Customfields.DEFAULT_ID) both created a widget for it; whichever one's value etemplate2.ts
	 * merged last silently won, dropping an edit made in the other - regardless of which tab the
	 * user actually typed into. This test intentionally diverges from compareToLegacy()'s
	 * bug-for-bug parity for exactly this one case.
	 */
	it("hides an untabbed customfield on a named tab that is not the default catch-all", () =>
	{
		assert.deepEqual(
			new Et2CustomfieldsController({customfields: sampleCustomfields, tab: "extra"}).getVisibleMap(),
			{cf_text: false, cf_project: true, cf_private: false, cf_file: false},
			"only the field actually assigned to this tab should show; untabbed fields belong on cf-default"
		);
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

	/**
	 * Contract: a type filter narrows whatever else selected the field.  The server sends every
	 * customfield as selected and leaves the filtering to us, so a field restricted to other entry
	 * types must stay hidden even though it arrived as selected.
	 * Setup: select every field explicitly, with a type filter that only one of them matches.
	 * Pass: only the matching field and the ones with no type restriction are visible.
	 */
	it("applies the type filter even when every field was selected", () =>
	{
		const controller = new Et2CustomfieldsController({
			customfields: {
				cf_task: {name: "cf_task", label: "Task", type: "text", type2: ["task"]},
				cf_other: {name: "cf_other", label: "Other", type: "text", type2: ["Dienstreise"]},
				cf_any: {name: "cf_any", label: "Any", type: "text", type2: []}
			},
			// what the server sends: everything selected
			fields: {cf_task: true, cf_other: true, cf_any: true},
			typeFilter: "task"
		});
		assert.deepEqual(
			controller.getVisibleFieldNames(),
			["cf_task", "cf_any"],
			"a customfield restricted to other entry types should not show"
		);
	});

	it("applies tab limits to default visibility", () =>
	{
		const controller = new Et2CustomfieldsController({
			customfields: sampleCustomfields,
			tab: "missing"
		});
		// Ticket #127781: an untabbed field (cf_text, cf_private, cf_file) belongs only on the
		// cf-default catch-all tab (see the "hides an untabbed customfield on a named tab" test
		// above) - a named tab that no field actually claims, like this one, shows nothing at all.
		assert.deepEqual(controller.getVisibleMap(), {
			cf_text: false,
			cf_project: false,
			cf_private: false,
			cf_file: false
		}, "tab-specific customfields should be hidden when their tab does not match");
	});

	it("ignores a customfield's tab where there are no tabs", () =>
	{
		// A list, a row and a nextmatch column header all render outside any tab, so they have no
		// tab for a customfield to match.  Honouring `tab` there hides every customfield assigned
		// to one - and in the column header that also drops it from the column-selection dialog,
		// so it cannot be switched back on either.
		assert.deepEqual(
			new Et2CustomfieldsController({
				customfields: sampleCustomfields,
				honourTabs: false
			}).getVisibleMap(),
			{cf_text: true, cf_project: true, cf_private: true, cf_file: true},
			"a customfield on a tab still belongs in a list"
		);

		// The same with an explicit selection, which is the shape a saved column preference has
		assert.deepEqual(
			new Et2CustomfieldsController({
				customfields: sampleCustomfields,
				fields: {cf_project: true},
				honourTabs: false
			}).getVisibleFieldNames(),
			["cf_project"],
			"selecting a tab's customfield in a list has to be enough to show it"
		);

		// ... and a dialog, which does have tabs, is unaffected - and shows nothing at all for a
		// named tab no field claims (ticket #127781: untabbed fields belong on cf-default only)
		assert.deepEqual(
			new Et2CustomfieldsController({
				customfields: sampleCustomfields,
				tab: "missing"
			}).getVisibleFieldNames(),
			[],
			"a tab still shows only its own customfields"
		);
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

	it("does not report changed when source fields/customfields are also empty", () =>
	{
		// A customfields column with zero defined fields legitimately has empty
		// fields/customfields everywhere. Reporting "changed" here made
		// CustomfieldsHeader's updated() -> _syncCustomfieldsFromModifications()
		// re-assign brand-new empty-object literals forever, each one seen as a
		// change by Lit's reference-equality dirty-checking - an infinite render
		// loop that froze the whole tab (found live 2026-09-14).
		const attrs : Record<string, any> = {
			fields: {},
			customfields: {}
		};
		const changed = mergeCustomfieldSettingsFromSources(
			attrs,
			{fields: {}},
			{customfields: {}, fields: {}}
		);
		assert.isFalse(changed, "an empty source has nothing to hydrate");
		assert.deepEqual(attrs.fields, {}, "fields should stay untouched");
		assert.deepEqual(attrs.customfields, {}, "customfields should stay untouched");
	});

	/**
	 * Ticket #127781 (live-reproduced on bb-trunk, 2026-11-12): a project with an untabbed
	 * customfield ("Berater") plus at least one named-tab customfield showed "saved" but silently
	 * dropped an edit to the untabbed one. Both the cf-default tab's and a named tab's
	 * <et2-customfields> share Et2Customfields.DEFAULT_ID ("custom_fields"); etemplate2.ts's
	 * getValues() merges same-id widgets' values by key, so if both instances create a widget for
	 * the same field, whichever is merged last wins regardless of which tab the user actually
	 * edited. The only thing that keeps that merge safe is each tab's controller reporting a
	 * disjoint set of visible fields - this asserts that invariant directly, for the real
	 * untabbed+named-tab shape rather than just one tab's visibility map in isolation.
	 */
	it("never shows the same field on both the cf-default tab and a named tab", () =>
	{
		const fields = {
			untabbed: {name: "untabbed", label: "Berater", tab: null},
			tabbed: {name: "tabbed", label: "Datenschutz field", tab: "Datenschutz"}
		};
		const defaultTab = new Et2CustomfieldsController({
			customfields: fields,
			defaultTabMatch: ""
		}).getVisibleFieldNames();
		const namedTab = new Et2CustomfieldsController({
			customfields: fields,
			tab: "Datenschutz"
		}).getVisibleFieldNames();

		assert.deepEqual(defaultTab, ["untabbed"], "cf-default shows only the untabbed field");
		assert.deepEqual(namedTab, ["tabbed"], "the named tab shows only its own field, not the untabbed one too");
		assert.isEmpty(
			defaultTab.filter((name) => namedTab.includes(name)),
			"cf-default and a named tab must never both create a widget for the same field"
		);
	});
});
