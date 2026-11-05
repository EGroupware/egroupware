import {assert} from "@open-wc/testing";
import {html, LitElement} from "lit";
// The controller only names it in its template, so nothing registers it in a test bundle
import "@shoelace-style/shoelace/dist/components/drawer/drawer.js";
import {Et2FilterDrawerController, hasActiveFilters} from "../Et2FilterDrawerController";

/**
 * Contract under test: what any widget gets from Et2FilterDrawerController.
 *
 * - `hasActiveFilters()` counts a filter as set only when it holds something: "", null,
 *   undefined, false and containers of only those are not set, and `ignore` keys are skipped.
 * - The drawer opens and closes through `toggle()` / `open`, calls `onOpen` each time it opens,
 *   closes on Escape from anywhere inside it, and starts closed again after a disconnect.
 * - The header offers "Clear filters", calling `clear`, only while `render()` is told something
 *   is filtered.
 * - `blank()` names every filter and column filter as "", apart from the `ignore` ones.
 *
 * Setup strategy: a bare LitElement host rendering the controller's drawer around a text input,
 * standing in for a filterbox field.
 *
 * Pass criteria are `hasActiveFilters()`'s answers and the rendered drawer's `open`.
 */

class FilterDrawerTestHost extends LitElement
{
	opened = 0;
	cleared = 0;
	filtered = false;
	filterDrawer = new Et2FilterDrawerController(this, {
		ignore: ["sort"],
		onOpen: () => this.opened++,
		clear: () => this.cleared++
	});

	egw()
	{
		return {lang: (label : string) => label};
	}

	render()
	{
		return this.filterDrawer.render(html`<input class="field"/>`, this.filtered);
	}
}
customElements.define("test-filter-drawer-host", FilterDrawerTestHost);

describe("Et2FilterDrawerController", () =>
{
	let host : FilterDrawerTestHost;

	beforeEach(async() =>
	{
		host = document.createElement("test-filter-drawer-host") as FilterDrawerTestHost;
		document.body.append(host);
		await host.updateComplete;
	});

	afterEach(() =>
	{
		host.remove();
	});

	it("counts only filters that hold something", () =>
	{
		assert.isFalse(hasActiveFilters(null));
		assert.isFalse(hasActiveFilters({search: "", cat_id: null, filter: undefined, flag: false}));
		assert.isFalse(hasActiveFilters({col_filter: {owner: "", status: []}, ids: [""]}),
			"containers holding nothing do not count");
		assert.isTrue(hasActiveFilters({col_filter: {owner: "5"}}));
		assert.isTrue(hasActiveFilters({status: ["~file~"]}));
		assert.isTrue(hasActiveFilters({cat_id: 0}), "0 is a value");
		assert.isFalse(hasActiveFilters({sort: {id: "name", asc: true}}, ["sort"]), "ignored keys do not count");
		assert.isTrue(host.filterDrawer.isActive({search: "x", sort: {id: "name"}}));
		assert.isFalse(host.filterDrawer.isActive({sort: {id: "name"}}), "the host's ignore list applies");
	});

	it("blanks every filter by name, apart from the ignored ones", () =>
	{
		assert.deepEqual(
			host.filterDrawer.blank({search: "x", cat_id: 5, sort: {id: "name"}, col_filter: {user_ts: {from: "2026-10-06"}, owner: ""}}),
			{search: "", cat_id: "", col_filter: {user_ts: "", owner: ""}}
		);
		assert.deepEqual(host.filterDrawer.blank(null), {col_filter: {}});
	});

	it("toggles the drawer and calls onOpen each time it opens", async() =>
	{
		const drawer = host.filterDrawer.drawer;
		assert.equal(drawer?.localName, "sl-drawer", "drawer must be reachable once rendered");
		assert.isFalse(drawer.open, "drawer starts closed");

		host.filterDrawer.toggle();
		await host.updateComplete;
		assert.isTrue(drawer.open);
		assert.equal(host.opened, 1);

		host.filterDrawer.toggle();
		await host.updateComplete;
		assert.isFalse(drawer.open);

		host.filterDrawer.toggle();
		await host.updateComplete;
		assert.equal(host.opened, 2, "onOpen runs on every open");
	});

	it("closes on Escape from a field inside the drawer, without letting it escape further", async() =>
	{
		let escaped = 0;
		const countEscape = () => escaped++;
		document.addEventListener("keydown", countEscape);
		try
		{
			host.filterDrawer.open = true;
			await host.updateComplete;

			host.shadowRoot!.querySelector(".field")!.dispatchEvent(
				new KeyboardEvent("keydown", {key: "Escape", bubbles: true, composed: true}));
			await host.updateComplete;

			assert.isFalse(host.filterDrawer.drawer.open, "Escape must close the drawer");
			assert.equal(escaped, 0, "Escape must not also close whatever dialog holds the widget");
		}
		finally
		{
			document.removeEventListener("keydown", countEscape);
		}
	});

	it("offers Clear filters only while something is filtered", async() =>
	{
		const clearAction = () => host.shadowRoot!.querySelector("[slot='header-actions']") as HTMLElement | null;
		assert.isNull(clearAction(), "nothing filtered, nothing to clear");

		host.filtered = true;
		host.requestUpdate();
		await host.updateComplete;
		assert.isNotNull(clearAction());

		clearAction()!.click();
		assert.equal(host.cleared, 1, "the action should call clear");
	});

	it("starts closed again after a disconnect", async() =>
	{
		host.filterDrawer.open = true;
		await host.updateComplete;

		host.remove();
		document.body.append(host);
		await host.updateComplete;

		assert.isFalse(host.filterDrawer.open);
		assert.isFalse(host.filterDrawer.drawer.open);
	});
});
