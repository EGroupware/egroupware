import {assert} from "@open-wc/testing";
import {Et2DatagridRequestQueue, Et2DatagridRequestQueueHost} from "../Et2DatagridRequestQueue";

/**
 * Contract: Et2DatagridRequestQueue.scheduleProcessing() debounces chunk fetches so a
 * burst of scroll-driven requests coalesces, but dispatches the first request out of an
 * idle queue immediately - that one has nothing to coalesce with, so the delay would be
 * pure latency on a list open or filter change.  Embedded child grids opt out (their
 * height reservation is coupled to when rows land).
 *
 * Setup: a minimal fake host exposing only the queue's host surface, with a large
 * dispatch delay so "debounced" and "immediate" are unambiguous.  Dispatches are
 * recorded, and each assertion waits a few macrotask turns - enough for a 0ms timer to
 * fire, far short of the configured delay.
 *
 * Pass criteria: a first request from an idle, non-embedded queue is dispatched within
 * that short window; the same request is NOT dispatched in that window when something is
 * already in flight, when a debounce timer is already armed, or when the host is an
 * embedded grid.
 */

const DISPATCH_DELAY_MS = 5000;

function createHost(overrides : Partial<Et2DatagridRequestQueueHost> = {}) : Et2DatagridRequestQueueHost
{
	return {
		dataProvider: {getQuerySignature: () => "sig"} as any,
		egw: () => ({lang: (text : string) => text}),
		requestUpdate: () => {},
		_requestDispatchDelayMs: DISPATCH_DELAY_MS,
		embeddedVirtualized: false,
		_isEmbeddedInitialLoading: () => false,
		...overrides
	} as Et2DatagridRequestQueueHost;
}

/** Long enough for a 0ms timer, far shorter than DISPATCH_DELAY_MS. */
async function shortWait() : Promise<void>
{
	for(let i = 0; i < 5; i++)
	{
		await new Promise<void>((resolve) => setTimeout(resolve, 0));
	}
}

describe("Et2DatagridRequestQueue dispatch timing", () =>
{
	it("dispatches the first request from an idle queue without waiting for the debounce", async() =>
	{
		const queue = new Et2DatagridRequestQueue(createHost());
		const dispatched : number[] = [];

		queue.queueRequest(0, 50, queue.requestKey(0, 50));
		queue.scheduleProcessing((start) => dispatched.push(start));
		await shortWait();

		assert.deepEqual(dispatched, [0], "first request should dispatch immediately");
	});

	it("still debounces once a request is in flight", async() =>
	{
		const queue = new Et2DatagridRequestQueue(createHost());
		const dispatched : number[] = [];

		queue.markInFlight("already-running");
		queue.queueRequest(50, 50, queue.requestKey(50, 50));
		queue.scheduleProcessing((start) => dispatched.push(start));
		await shortWait();

		assert.deepEqual(dispatched, [], "a request queued behind an in-flight fetch should wait for the debounce");
	});

	it("still debounces the rest of a burst once a timer is armed", async() =>
	{
		const queue = new Et2DatagridRequestQueue(createHost());
		const dispatched : number[] = [];
		const onDispatch = (start : number) => dispatched.push(start);

		// Two requests arriving in the same turn: the first arms the immediate dispatch,
		// the second must not also skip the debounce.
		queue.queueRequest(0, 50, queue.requestKey(0, 50));
		queue.scheduleProcessing(onDispatch);
		queue.queueRequest(50, 50, queue.requestKey(50, 50));
		queue.scheduleProcessing(onDispatch);
		await shortWait();

		assert.deepEqual(dispatched, [], "rearming with a second queued request should fall back to the debounce");
	});

	it("keeps the full debounce for an embedded child grid", async() =>
	{
		const queue = new Et2DatagridRequestQueue(createHost({embeddedVirtualized: true}));
		const dispatched : number[] = [];

		queue.queueRequest(0, 50, queue.requestKey(0, 50));
		queue.scheduleProcessing((start) => dispatched.push(start));
		await shortWait();

		assert.deepEqual(dispatched, [], "embedded grids should not take the immediate-dispatch path");
	});
});

/**
 * Contract: scheduleProcessing()'s debounce timer gets cleared and re-armed by every new
 * arrival, so a continuous stream of newer requests could in theory defer dispatch forever.
 * MAX_DISPATCH_DELAY_MS (500ms) caps that: once the oldest still-queued request has waited
 * that long, the next scheduleProcessing() call flushes immediately instead of re-arming.
 *
 * Setup: a host with a 5000ms configured debounce (DISPATCH_DELAY_MS) - far longer than the
 * 500ms cap - so these tests would fail (never dispatch, then time out at mocha's 3s limit)
 * if the forced-flush path were removed. Something is marked in flight first so every call
 * takes the debounced path, never the idle-queue immediate-dispatch shortcut tested above.
 * These need real elapsed wall-clock time (no fake timers - see the file-level ground rule
 * recorded in the test-timing audit) so each one runs for ~0.5-1s.
 *
 * Pass criteria: continuous re-arming every 100ms still gets a flush by ~600ms, not 5000ms;
 * the forced flush and clear() both reset the "how long has the oldest entry waited" clock,
 * so the request right after either one gets its own full debounce window rather than being
 * force-flushed immediately off a stale clock.
 */
describe("Et2DatagridRequestQueue MAX_DISPATCH_DELAY_MS forced flush", () =>
{
	function wait(ms : number) : Promise<void>
	{
		return new Promise<void>((resolve) => setTimeout(resolve, ms));
	}

	it("forces a flush once the oldest queued entry has waited MAX_DISPATCH_DELAY_MS, despite continuous re-arming", async() =>
	{
		const queue = new Et2DatagridRequestQueue(createHost());
		const dispatched : number[] = [];
		queue.markInFlight("already-running"); // keep every call on the debounced path

		let start = 0;
		queue.queueRequest(start, 10, queue.requestKey(start, 10));
		queue.scheduleProcessing((s) => dispatched.push(s));

		// Keep re-queueing + re-arming every 100ms, well inside the 5000ms configured
		// debounce, for longer than the 500ms cap. Without the cap this never dispatches
		// inside this loop - only the forced flush can make it happen this fast.
		for(let i = 0; i < 8 && dispatched.length === 0; i++)
		{
			await wait(100);
			start += 10;
			queue.queueRequest(start, 10, queue.requestKey(start, 10));
			queue.scheduleProcessing((s) => dispatched.push(s));
		}

		assert.isAbove(dispatched.length, 0,
			"the forced flush should have dispatched the queued burst well before the 5000ms configured debounce");
	});

	it("resets the oldest-queued clock on flush, so the next request gets a fresh debounce window", async() =>
	{
		const queue = new Et2DatagridRequestQueue(createHost());
		const dispatched : number[] = [];
		queue.markInFlight("already-running");

		// Force a flush the same way as above.
		let start = 0;
		queue.queueRequest(start, 10, queue.requestKey(start, 10));
		queue.scheduleProcessing((s) => dispatched.push(s));
		for(let i = 0; i < 8 && dispatched.length === 0; i++)
		{
			await wait(100);
			start += 10;
			queue.queueRequest(start, 10, queue.requestKey(start, 10));
			queue.scheduleProcessing((s) => dispatched.push(s));
		}
		assert.isAbove(dispatched.length, 0, "setup: the first burst should have been force-flushed");

		// A request queued right after that flush must NOT be force-flushed immediately -
		// if _oldestQueuedAt weren't reset by flush(), it could still read as "already
		// waited >= 500ms" and skip straight to another forced flush.
		dispatched.length = 0;
		queue.markInFlight("still-running");
		queue.queueRequest(1000, 10, queue.requestKey(1000, 10));
		queue.scheduleProcessing((s) => dispatched.push(s));
		await shortWait();

		assert.deepEqual(dispatched, [], "a request right after a forced flush should get its own fresh debounce window, not an immediate re-flush");
	});

	it("resets the oldest-queued clock on clear(), so a later request isn't force-flushed off a stale clock", async() =>
	{
		const queue = new Et2DatagridRequestQueue(createHost());
		queue.markInFlight("already-running");

		queue.queueRequest(0, 10, queue.requestKey(0, 10));
		await wait(600); // older than MAX_DISPATCH_DELAY_MS, but never dispatched via scheduleProcessing()
		queue.clear();

		const dispatched : number[] = [];
		queue.markInFlight("still-running");
		queue.queueRequest(2000, 10, queue.requestKey(2000, 10));
		queue.scheduleProcessing((s) => dispatched.push(s));
		await shortWait();

		assert.deepEqual(dispatched, [],
			"a request queued after clear() should get a fresh debounce window, not an immediate flush from the pre-clear() request's age");
	});
});
