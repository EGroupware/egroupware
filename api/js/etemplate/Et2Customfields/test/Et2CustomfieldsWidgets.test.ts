import {assert, fixture, html} from "@open-wc/testing";
import "../Et2Customfields";
import "../Et2CustomfieldsList";
import "../Et2CustomfieldsListRow";
import "../Et2CustomfieldsFilters";
import {Et2CustomfieldsBase} from "../Et2CustomfieldsBase";

const customfields = {
	cf_text: {label: "Text", type: "text", type2: "task"},
	cf_select: {label: "Select", type: "select", type2: "project"},
	cf_private: {label: "Private", type: "select", type2: "0", private: "1"}
};

/**
 * Contract under test:
 * - New Et2Customfields webcomponents expose the same controller-driven visibility
 *   state that nextmatch header integration consumes.
 *
 * Setup strategy:
 * - Render each component variant and assign deterministic customfield metadata.
 *
 * Pass criteria:
 * - Visibility maps reflect mode + filter inputs.
 * - Public visibility APIs return predictable field names/maps.
 */
describe("Et2Customfields webcomponents", () =>
{
	it("resolves explicit field visibility for et2-customfields-list", async() =>
	{
		const element = await fixture<Et2CustomfieldsBase>(html`
			<et2-customfields-list></et2-customfields-list>
		`);
		element.customfields = customfields;
		element.fields = {cf_text: true, cf_select: false, cf_private: true};
		await element.updateComplete;
		assert.deepEqual(
			element.getCustomfieldVisibility(),
			{cf_text: true, cf_select: false, cf_private: true},
			"list widget should preserve explicit visibility map"
		);
	});

	/**
	 * Contract: the full list widget renders child field widgets in light DOM and
	 * keeps child widget instances stable when only row values change.
	 * Setup: render one visible text customfield, then replace value.
	 * Pass: the child widget is not in shadow DOM and the same instance displays
	 * the new value.
	 */
	it("renders list field widgets in light DOM and updates only row values", async() =>
	{
		const element = await fixture<Et2CustomfieldsBase>(html`
            <et2-customfields-list></et2-customfields-list>
		`);
		element.customfields = customfields;
		element.fields = {cf_text: true, cf_select: false, cf_private: false};
		element.value = {"#cf_text": "First row"};
		await element.updateComplete;

		const firstWidget = element.querySelector("[data-field='cf_text'] et2-description") as HTMLElement | null;
		await (firstWidget as any)?.updateComplete;
		assert.isNull(element.shadowRoot, "customfields list should render into light DOM");
		assert.isNotNull(firstWidget, "customfields list should create field widgets in its light DOM");
		assert.include(firstWidget?.textContent || "", "First row", "field widget should display the current row value");

		element.value = {"#cf_text": "Second row"};
		await element.updateComplete;

		const secondWidget = element.querySelector("[data-field='cf_text'] et2-description") as HTMLElement | null;
		await (secondWidget as any)?.updateComplete;
		assert.strictEqual(secondWidget, firstWidget, "unchanged field definitions should keep the same widget instance");
		assert.include(secondWidget?.textContent || "", "Second row", "row value changes should update the existing widget");
	});

	/**
	 * Contract: customfield metadata controls the field list; row values alone do not.
	 * Setup: assign only a row value and no customfield definitions.
	 * Pass: no visible field names or field DOM nodes are created.
	 */
	it("does not derive the field list from row values", async() =>
	{
		const element = await fixture<Et2CustomfieldsBase>(html`
            <et2-customfields-list></et2-customfields-list>
		`);
		element.value = {"#cf_text": "Row value without metadata"};
		await element.updateComplete;

		assert.deepEqual(
			element.getVisibleFieldNames(),
			[],
			"row values must not create customfield definitions; missing metadata is a setup problem"
		);
		assert.isNull(
			element.querySelector("[data-field='cf_text']"),
			"customfields list should remain empty until customfield metadata is supplied"
		);
	});

	/**
	 * Contract: the datagrid row renderer displays selected #customfield values as text.
	 * Setup: assign one visible customfield plus a row-scoped #field value.
	 * Pass: field text renders and no nested Et2 widget is created.
	 */
	it("renders datagrid row customfields without nested widgets", async() =>
	{
		const element = await fixture<Et2CustomfieldsBase>(html`
            <et2-customfields-list-row></et2-customfields-list-row>
		`);
		element.customfields = customfields;
		element.fields = {cf_text: true, cf_select: false, cf_private: false};
		element.value = {"#cf_text": "Fast row"};
		await element.updateComplete;

		assert.isNotNull(
			element.shadowRoot?.querySelector("[data-field='cf_text']"),
			"row renderer should render visible customfield values"
		);
		assert.isNull(
			element.shadowRoot?.querySelector("et2-description"),
			"row renderer should avoid nested Et2 widgets for datagrid performance"
		);
		assert.include(
			element.shadowRoot?.querySelector("[data-field='cf_text']")?.textContent || "",
			"Fast row",
			"row renderer should display the current row value"
		);
	});

	/**
	 * Contract: datagrid row customfield values use only the supported #field key.
	 * Setup: assign both unprefixed and prefixed values for the same field.
	 * Pass: the rendered text comes from #field and ignores the unprefixed value.
	 */
	it("uses only prefixed customfield keys for datagrid row values", async() =>
	{
		const element = await fixture<Et2CustomfieldsBase>(html`
			<et2-customfields-list-row></et2-customfields-list-row>
		`);
		element.customfields = customfields;
		element.fields = {cf_text: true};
		element.value = {cf_text: "Unsupported", "#cf_text": "Supported"};
		await element.updateComplete;

		const text = element.shadowRoot?.querySelector("[data-field='cf_text']")?.textContent || "";
		assert.include(text, "Supported", "row renderer should display #field values");
		assert.notInclude(text, "Unsupported", "row renderer should ignore unprefixed field values");
	});

	it("applies mode-specific defaults for et2-customfields-filters", async() =>
	{
		const element = await fixture<Et2CustomfieldsBase>(html`
			<et2-customfields-filters></et2-customfields-filters>
		`);
		element.customfields = customfields;
		await element.updateComplete;
		assert.deepEqual(
			element.getVisibleFieldNames(),
			["cf_text", "cf_select", "cf_private"],
			"filter widget should default all customfields visible"
		);
	});

	it("supports type_filter previous across widget instances", async() =>
	{
		const first = await fixture<Et2CustomfieldsBase>(html`
			<et2-customfields type-filter="project"></et2-customfields>
		`);
		first.customfields = customfields;
		await first.updateComplete;

		const second = await fixture<Et2CustomfieldsBase>(html`
			<et2-customfields type-filter="previous"></et2-customfields>
		`);
		second.customfields = customfields;
		await second.updateComplete;

		assert.deepEqual(
			second.getCustomfieldVisibility(),
			{cf_text: false, cf_select: true, cf_private: true},
			"type_filter=previous should reuse last filter setting for new instances"
		);
	});
});
