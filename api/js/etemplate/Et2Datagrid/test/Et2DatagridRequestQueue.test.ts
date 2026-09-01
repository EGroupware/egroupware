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
