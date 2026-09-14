import {assert} from "@open-wc/testing";
import {Et2DatagridRowRenderer} from "../Et2DatagridRowRenderer";

/**
 * Contract: Et2DatagridRowRenderer.guardFocusAfterVirtualMutation() must put keyboard
 * focus back on the active row after row DOM churn - a re-render replaces row elements
 * outright, which drops focus to the document body and would otherwise end keyboard
 * navigation.  Parking focus on the grid itself is only a stopgap for the case where
 * the replacement row is not mounted yet: it keeps key events flowing but visibly drops
 * the row's focus ring, and it must not become the resting state.
 *
 * Setup: a stub host with a real shadow root holding a grid element and one row, so
 * focus is exercised for real rather than asserted against a faked activeElement.
 *
 * Pass criteria: the row ends up focused when it is mounted, a previous stopgap parked
 * on the grid is still recovered to the row, and focus the user moved elsewhere on
 * purpose is left alone.
 */

function createStubHost(withRow : boolean = true)
{
	const host = document.createElement("div") as any;
	const shadow = host.attachShadow({mode: "open"});
	shadow.innerHTML = `
		<table role="grid" tabindex="-1">
			<tbody id="rows">
				${withRow ? `<tr data-row-index="0" tabindex="0"><td></td></tr>` : ""}
			</tbody>
		</table>
	`;
	host.activeRowIndex = 0;
	host._restoreFocusAfterRender = false;
	Object.defineProperty(host, "_gridTable", {
		get: () => shadow.querySelector("[role='grid']")
	});
	// Same contract as the real Et2Datagrid method: focus the row for `index` when it
	// currently has a DOM element, otherwise leave focus where it is.
	host._focusRowByIndex = (index : number) =>
	{
		const row = shadow.querySelector(`[data-row-index="${index}"]`) as HTMLElement | null;
		row?.focus({preventScroll: true});
	};
	document.body.append(host);
	return {host, shadow};
}

describe("Et2DatagridRowRenderer focus guard", () =>
{
	let hosts : HTMLElement[] = [];

	afterEach(() =>
	{
		hosts.forEach((host) => host.remove());
		hosts = [];
	});

	const guard = (host : any) =>
	{
		hosts.push(host);
		new Et2DatagridRowRenderer(host as any)["guardFocusAfterVirtualMutation"]();
	};

	// Compare a short label rather than the elements themselves: a failing assertion on
	// two DOM nodes makes the test runner serialize whole subtrees.
	const focused = (shadow : ShadowRoot) =>
	{
		const active = shadow.activeElement as HTMLElement | null;
		if(!active) return "none";
		return active.hasAttribute("data-row-index") ? "row" : active.tagName.toLowerCase();
	};

	it("returns focus to the active row after a re-render dropped it", () =>
	{
		const {host, shadow} = createStubHost();
		(document.activeElement as HTMLElement)?.blur?.();
		guard(host);
		assert.strictEqual(focused(shadow), "row",
			"row that is mounted again should take focus back directly");
	});

	it("recovers focus parked on the grid by an earlier mutation", () =>
	{
		const {host, shadow} = createStubHost();
		// The stopgap state: document.activeElement is then the shadow host, which the
		// "user moved to another control" check must not mistake for a deliberate move.
		(shadow.querySelector("[role='grid']") as HTMLElement).focus();
		assert.strictEqual(focused(shadow), "table", "precondition");

		guard(host);
		assert.strictEqual(focused(shadow), "row",
			"focus parked on the grid should be handed back to the active row");
	});

	it("falls back to the grid while no row is mounted, so keys keep reaching it", () =>
	{
		const {host, shadow} = createStubHost(false);
		(document.activeElement as HTMLElement)?.blur?.();
		guard(host);
		assert.strictEqual(focused(shadow), "table",
			"with no row to focus, the grid keeps keyboard events alive");
		assert.isTrue(host._restoreFocusAfterRender,
			"a later render must be told to put focus back on the row");
	});

	it("leaves focus the user moved to another control alone", () =>
	{
		const {host, shadow} = createStubHost();
		const elsewhere = document.createElement("input");
		document.body.append(elsewhere);
		elsewhere.focus();
		try
		{
			guard(host);
			assert.strictEqual((document.activeElement as HTMLElement)?.tagName.toLowerCase(), "input",
				"deliberate focus must not be stolen");
			assert.strictEqual(focused(shadow), "none", "nothing inside the grid should have taken focus");
		}
		finally
		{
			elsewhere.remove();
		}
	});
});
