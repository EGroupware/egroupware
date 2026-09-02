import {assert} from "@open-wc/testing";
import {Et2Datagrid} from "../Et2Datagrid";

/**
 * Contract for Et2Datagrid._handleEmbeddedHeightEvent(), the hop that carries a nested
 * grid's reserved height up to its ancestors.
 *
 * Reported from filemanager (/home -> multimedia -> Photos -> 2001): the middle grid kept
 * the height it had before its own child expanded, so the top-level grid reserved far too
 * little, produced no scrollbar, and drew rows over each other. Measured live, the middle
 * grid computed 1496px for itself while its applied host height stayed at 352px - the
 * value, the calculation and the event delivery were all correct, only the recompute was
 * missing.
 *
 * These are deliberately deterministic unit tests on the handler rather than a nested
 * rendering fixture. The live defect is a *missed* recompute that eventually self-corrects
 * off some later incidental trigger, so any integration test with a generous settle passes
 * (verified: a three-level fixture asserting applied-equals-computed passed before the
 * fix), and catching it by settling fewer frames would only assert timing. What is worth
 * pinning is the invariant that the recompute is unconditional.
 *
 * Setup: a real Et2Datagrid as the receiving (middle) grid with its collaborators stubbed,
 * fed a synthetic et2-embedded-height event whose composedPath reports a child grid.
 * _remeasureDirectEmbeddedChildGrid() is stubbed to return false, which is what it does
 * when the child's reported height has not changed, or when the event came from a
 * descendant rather than a direct child.
 *
 * Pass criteria: an embedded receiver schedules its own height re-sync even when the
 * child's height did not change; the event stops at the grid that handled it; and the
 * parent's row pitch is not pushed onto a grid that is not its direct child.
 */

const hosts : HTMLElement[] = [];

/**
 * A connected receiving grid. Connected so it has a shadow root, which is what
 * distinguishes a direct child (its expanded row lives in that root) from a descendant.
 */
async function createReceiver(embedded : boolean)
{
	const host = document.createElement("div");
	document.body.appendChild(host);
	hosts.push(host);
	const grid = new Et2Datagrid();
	grid.embeddedVirtualized = embedded;
	host.appendChild(grid);
	await grid.updateComplete;
	let syncCalls = 0;
	(grid as any)._scheduleEmbeddedVirtualizedHeightSync = () => { syncCalls++; };
	return {grid, syncCalls: () => syncCalls};
}

/**
 * Place a child grid where a real direct child sits: inside an expanded row belonging to
 * this grid's own shadow root. A grid left unattached stands in for a deeper descendant,
 * whose expanded row belongs to some other grid's root.
 */
function attachAsDirectChild(grid : Et2Datagrid, child : Et2Datagrid)
{
	const expandedRow = document.createElement("tr");
	expandedRow.setAttribute("data-dg-expanded-row", "1");
	expandedRow.appendChild(child);
	grid.shadowRoot!.appendChild(expandedRow);
	return expandedRow;
}

function embeddedHeightEvent(source : Et2Datagrid, height = 500)
{
	const event = new CustomEvent("et2-embedded-height", {detail: {height}, bubbles: true, composed: true});
	let stopped = false;
	(event as any).composedPath = () => [source];
	event.stopPropagation = () => { stopped = true; };
	return {event, stopped: () => stopped};
}

describe("Et2Datagrid nested expansion height propagation", () =>
{
	afterEach(() =>
	{
		while(hosts.length)
		{
			hosts.pop()?.remove();
		}
	});

	it("re-syncs its own height even when the child's reported height did not change", async() =>
	{
		const {grid, syncCalls} = await createReceiver(true);
		const child = new Et2Datagrid();
		attachAsDirectChild(grid, child);
		// What _remeasureDirectEmbeddedChildGrid() returns when the child's height is
		// unchanged. This grid's own reservation can still be stale, because its own
		// branch total grew when its child expanded.
		(grid as any)._remeasureDirectEmbeddedChildGrid = () => false;
		const {event} = embeddedHeightEvent(child);

		(grid as any)._handleEmbeddedHeightEvent(event);

		assert.equal(syncCalls(), 1,
			"an embedded grid must recompute its own reserved height on a child height report, changed or not");
	});

	it("still re-syncs when the child's height did change", async() =>
	{
		const {grid, syncCalls} = await createReceiver(true);
		const child = new Et2Datagrid();
		attachAsDirectChild(grid, child);
		(grid as any)._remeasureDirectEmbeddedChildGrid = () => true;
		const {event} = embeddedHeightEvent(child);

		(grid as any)._handleEmbeddedHeightEvent(event);

		assert.equal(syncCalls(), 1, "the changed-height path must keep re-syncing");
	});

	it("does not re-sync a top-level grid, which reserves height by its own path", async() =>
	{
		const {grid, syncCalls} = await createReceiver(false);
		const child = new Et2Datagrid();
		attachAsDirectChild(grid, child);
		(grid as any)._remeasureDirectEmbeddedChildGrid = () => true;
		const {event} = embeddedHeightEvent(child);

		(grid as any)._handleEmbeddedHeightEvent(event);

		assert.equal(syncCalls(), 0, "a non-embedded grid has no embedded host height to sync");
	});

	it("stops a handled event so it cannot be re-handled by a grandparent", async() =>
	{
		const {grid} = await createReceiver(true);
		const child = new Et2Datagrid();
		attachAsDirectChild(grid, child);
		(grid as any)._remeasureDirectEmbeddedChildGrid = () => false;
		const {event, stopped} = embeddedHeightEvent(child);

		(grid as any)._handleEmbeddedHeightEvent(event);

		assert.isTrue(stopped(),
			"an unchanged-height event must not keep bubbling to ancestors that do not host this child");
	});

	it("ignores a report from a grid that is not its direct child", async() =>
	{
		const {grid, syncCalls} = await createReceiver(true);
		// Never placed in this grid's shadow root, so it stands for a deeper descendant.
		const descendant = new Et2Datagrid();
		let pitchPushes = 0;
		descendant.setRowHeightEstimate = () => { pitchPushes++; };
		const {event, stopped} = embeddedHeightEvent(descendant);

		(grid as any)._handleEmbeddedHeightEvent(event);

		assert.equal(pitchPushes, 0,
			"row pitch must only be forced onto a direct child, so the check has to precede the push");
		assert.equal(syncCalls(), 0, "a descendant's report is its own parent's business, not this grid's");
		assert.isFalse(stopped(), "the event must continue to the grid that does host this child");
	});
});
