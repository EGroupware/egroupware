import {assert} from "@open-wc/testing";
import {Et2DatagridRowRenderer} from "../Et2DatagridRowRenderer";

/**
 * Contract: Et2DatagridRowRenderer.scheduleRowsUpgradedSettle() must not commit a
 * row-height measurement while rows are still growing.  Freshly hydrated widgets keep
 * resizing for a frame or two, and the committing half of the measurement latches an
 * embedded grid's row pitch permanently, so a measurement taken too early is never
 * revised.  The settle therefore samples on successive frame pairs and only commits
 * once two consecutive samples agree - bounded, so a row that never converges cannot
 * leave height reservation pending forever.
 *
 * Setup: a stub host exposing just the surface the settle path touches, whose
 * _sampleRenderedRowHeightAverage() replays a scripted sequence of heights (one per
 * pass) to stand in for rows that grow and then stop.  The settle is awaited via the
 * et2-row-widgets-upgraded event it dispatches when it finishes.
 *
 * Pass criteria: the commit happens exactly once, after the sequence has stabilized -
 * checked by recording the sample value in effect at commit time.  A never-stabilizing
 * sequence must still commit, within the pass budget.  A stable sequence must not spend
 * extra passes.
 */

function createStubHost(samples : number[])
{
	const host = document.createElement("div") as any;
	let sampleIndex = 0;
	host.taken = [] as (number | null)[];
	host.commits = 0;
	host.committedAfterSamples = -1;
	host.embeddedVirtualized = false;
	host._embeddedVirtualizedHeightSyncPendingAfterRowUpgrade = false;
	host._rowHeightPx = 44;
	host._rowHeightLocked = false;
	host.total = 0;
	host.rows = [];
	host._sampleRenderedRowHeightAverage = () =>
	{
		// Hold the last scripted value once the script runs out, so "stable" sequences
		// stay stable rather than falling off the end.
		const value = samples[Math.min(sampleIndex, samples.length - 1)];
		sampleIndex++;
		host.taken.push(value);
		return value;
	};
	host._updateMeasuredAverageRowHeight = () =>
	{
		host.commits++;
		host.committedAfterSamples = host.taken.length;
		return host._rowHeightPx;
	};
	host._scheduleEmbeddedVirtualizedHeightSync = () => {};
	host._scheduleRowsMinHeightSync = () => {};
	return host;
}

function settle(host : any) : Promise<void>
{
	const renderer = new Et2DatagridRowRenderer(host);
	return new Promise<void>((resolve) =>
	{
		host.addEventListener("et2-row-widgets-upgraded", () => resolve(), {once: true});
		renderer.scheduleRowsUpgradedSettle();
	});
}

describe("Et2DatagridRowRenderer settle convergence", () =>
{
	it("waits for row heights to stop changing before committing", async() =>
	{
		// Rows grow across three passes, then hold at 90.
		const host = createStubHost([40, 70, 90, 90]);

		await settle(host);

		assert.equal(host.commits, 1, "the measurement should be committed exactly once");
		assert.equal(host.committedAfterSamples, 4, "commit should wait for two agreeing samples");
		assert.deepEqual(host.taken, [40, 70, 90, 90], "each pass should take one fresh sample");
	});

	it("commits immediately once two samples agree", async() =>
	{
		const host = createStubHost([64]);

		await settle(host);

		assert.equal(host.commits, 1, "the measurement should be committed exactly once");
		assert.equal(host.committedAfterSamples, 2, "a height that is already stable should not spend extra passes");
	});

	it("commits anyway when the height never stabilizes", async() =>
	{
		// Every sample differs, so the convergence check never succeeds.
		const host = createStubHost([10, 20, 30, 40, 50, 60, 70, 80, 90]);

		await settle(host);

		assert.equal(host.commits, 1, "a never-converging row must still commit");
		assert.isAtMost(host.taken.length, 5, "the settle must stay inside its pass budget");
	});

	it("commits without sampling further when there is nothing measurable", async() =>
	{
		const host = createStubHost([null as any]);

		await settle(host);

		assert.equal(host.commits, 1, "a null sample should finish the settle rather than retry");
		assert.equal(host.taken.length, 1, "nothing measurable means nothing to converge on");
	});
});
