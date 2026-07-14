import {assert} from "@open-wc/testing";
import {Et2Nextmatch} from "../Et2Nextmatch";

const egwStub = {
	debug: () => {},
	lang: (label : string) => label,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	preference: () => null,
	set_preference: () => {},
	app_name: () => "test",
	link: (url : string) => url
};
window.egw = function() { return egwStub; } as any;
Object.assign(window.egw, egwStub);

describe("Et2Nextmatch tile view", () =>
{
	/**
	 * Contract under test:
	 * - `setView()` is a frontend layout API and must not mutate active filters
	 *   or introduce a new server request shape by itself.
	 *
	 * Setup strategy:
	 * - Create Et2Nextmatch without rendering and call `setView("tile")`.
	 *
	 * Pass criteria:
	 * - The public view property changes, while `activeFilters` remains unchanged.
	 */
	it("setView changes frontend view without changing active filters", () =>
	{
		const nm = new Et2Nextmatch();
		const before = JSON.stringify(nm.activeFilters);

		nm.setView("tile");

		assert.equal(nm.view, "tile", "setView should update frontend layout view");
		assert.equal(JSON.stringify(nm.activeFilters), before, "setView should not mutate filters");
	});
});
