var __awaiter = (this && this.__awaiter) || function (thisArg, _arguments, P, generator) {
    function adopt(value) { return value instanceof P ? value : new P(function (resolve) { resolve(value); }); }
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};
import { assert, fixture, html } from "@open-wc/testing";
import "../Et2Customfields";
import "../Et2CustomfieldsList";
import "../Et2CustomfieldsFilters";
const customfields = {
    cf_text: { label: "Text", type: "text", type2: "task" },
    cf_select: { label: "Select", type: "select", type2: "project" },
    cf_private: { label: "Private", type: "select", type2: "0", private: "1" }
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
describe("Et2Customfields webcomponents", () => {
    it("resolves explicit field visibility for et2-customfields-list", () => __awaiter(void 0, void 0, void 0, function* () {
        const element = yield fixture(html `
			<et2-customfields-list></et2-customfields-list>
		`);
        element.customfields = customfields;
        element.fields = { cf_text: true, cf_select: false, cf_private: true };
        yield element.updateComplete;
        assert.deepEqual(element.getCustomfieldVisibility(), { cf_text: true, cf_select: false, cf_private: true }, "list widget should preserve explicit visibility map");
    }));
    it("applies mode-specific defaults for et2-customfields-filters", () => __awaiter(void 0, void 0, void 0, function* () {
        const element = yield fixture(html `
			<et2-customfields-filters></et2-customfields-filters>
		`);
        element.customfields = customfields;
        yield element.updateComplete;
        assert.deepEqual(element.getVisibleFieldNames(), ["cf_text", "cf_select", "cf_private"], "filter widget should default all customfields visible");
    }));
    it("supports type_filter previous across widget instances", () => __awaiter(void 0, void 0, void 0, function* () {
        const first = yield fixture(html `
			<et2-customfields type-filter="project"></et2-customfields>
		`);
        first.customfields = customfields;
        yield first.updateComplete;
        const second = yield fixture(html `
			<et2-customfields type-filter="previous"></et2-customfields>
		`);
        second.customfields = customfields;
        yield second.updateComplete;
        assert.deepEqual(second.getCustomfieldVisibility(), { cf_text: false, cf_select: true, cf_private: true }, "type_filter=previous should reuse last filter setting for new instances");
    }));
});
//# sourceMappingURL=Et2CustomfieldsWidgets.test.js.map