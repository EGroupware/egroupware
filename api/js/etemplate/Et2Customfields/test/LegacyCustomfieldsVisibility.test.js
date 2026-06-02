import { assert } from "@open-wc/testing";
import { legacyVisibility, sampleCustomfields } from "./legacyVisibilityHelper";
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
describe("Legacy customfields visibility baseline", () => {
    it("applies type_filter across type2 values and unrestricted fields", () => {
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
    it("supports type_filter=previous by reusing prior filter state", () => {
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
    it("applies exclude after explicit field selection", () => {
        const visibility = legacyVisibility({
            customfields: sampleCustomfields,
            fields: { cf_text: true, cf_private: true },
            exclude: "cf_private"
        });
        assert.deepEqual(visibility, {
            cf_text: true,
            cf_project: false,
            cf_private: false,
            cf_file: false
        }, "excluded customfields must be hidden even if explicitly selected");
    });
    it("filters by tab when no explicit fields are provided", () => {
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
    it("applies default tab private split rules", () => {
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
//# sourceMappingURL=LegacyCustomfieldsVisibility.test.js.map