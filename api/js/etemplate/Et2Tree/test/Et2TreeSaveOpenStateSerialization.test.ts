import {assert, fixture, html} from "@open-wc/testing";
import "../Et2Tree";
// Et2Tree only imports SlTreeItem as a type, so esbuild drops shoelace's side-effect registration
// from this bundle and the <sl-tree> in its shadow root would never upgrade - register both here,
// the way the rollup bundle ends up doing for a real page
import "@shoelace-style/shoelace/dist/components/tree/tree.js";
import "@shoelace-style/shoelace/dist/components/tree-item/tree-item.js";

window.egw = {
	ajaxUrl: (url) => url,
	decodePath: (_path : string) => _path,
	image: () => "data:image/svg+xml;base64,",
	preference: i => "",
	tooltipUnbind: () => {},
	webserverUrl: ""
};

/**
 * Regression coverage for ticket #124991 ("Ordnerstruktur klappt immer wieder Unterordner auf" -
 * collapse one folder, expand another, the collapsed one re-expands on its own, worsening with
 * repeated use).
 *
 * saveOpenState() always collects and sends the COMPLETE current open-id list (never a delta), so
 * there is no incremental-merge/stale-cache bug to find client-side. The actual race is server-
 * side: Framework::ajax_set_preference() is a plain read-modify-write with no locking, and jsonq's
 * own batching (egw_jsonq.ts's jsonqSend(), on its own 100ms tick) never waits for an in-flight
 * api.queue request before sending the next one. Two saves fired close together (but more than the
 * 300ms debounce apart) could each become their own concurrent HTTP request, and whichever happened
 * to finish its OWN read-modify-write LAST won server-side - regardless of which one actually
 * carried the newer state. flushOpenState() now serializes these requests itself: never more than
 * one in flight for this preference at a time, always sending the LATEST ids once the current one
 * settles and dropping any superseded one queued behind it.
 */
describe("Et2Tree.flushOpenState() - save-request serialization (ticket #124991)", () =>
{
	let originalJsonq;

	beforeEach(() =>
	{
		originalJsonq = window.egw.jsonq;
	});

	afterEach(() =>
	{
		window.egw.jsonq = originalJsonq;
	});

	it("never lets a second save for the same preference go out while one is still in flight", async() =>
	{
		const tree : any = await fixture(html`<et2-tree></et2-tree>`);
		const calls : any[][] = [];
		let resolveFirst : () => void;
		//@ts-ignore
		window.egw.jsonq = (_menuaction : string, parameters : any[]) =>
		{
			calls.push(parameters);
			return new Promise<void>((resolve) => { resolveFirst = resolve; });
		};

		tree.flushOpenState(['mail', 'ExpandedFolders'], ['a']);
		tree.flushOpenState(['mail', 'ExpandedFolders'], ['a', 'b']);
		tree.flushOpenState(['mail', 'ExpandedFolders'], ['a', 'b', 'c']);

		assert.equal(calls.length, 1, "only the first call may actually reach jsonq while none has resolved yet");
		assert.deepEqual(JSON.parse(calls[0][2]), ['a']);

		resolveFirst();
		// let the in-flight promise's .finally() chain, and the recursive flushOpenState() it
		// triggers, actually run
		await new Promise(resolve => setTimeout(resolve, 0));
		await new Promise(resolve => setTimeout(resolve, 0));

		assert.equal(calls.length, 2,
			"once the first request resolves, exactly one more goes out - not one per queued call");
		assert.deepEqual(JSON.parse(calls[1][2]), ['a', 'b', 'c'],
			"the LATEST ids must be what's actually sent, not the superseded ['a','b'] in between - " +
			"an older request finishing after it is exactly what let a stale state win in #124991");
	});

	it("sends a request immediately when nothing is already in flight", async() =>
	{
		const tree : any = await fixture(html`<et2-tree></et2-tree>`);
		const calls : any[][] = [];
		//@ts-ignore
		window.egw.jsonq = (_menuaction : string, parameters : any[]) =>
		{
			calls.push(parameters);
			return Promise.resolve();
		};

		tree.flushOpenState(['mail', 'ExpandedFolders'], ['a']);

		assert.equal(calls.length, 1);
		assert.deepEqual(calls[0], ['mail', 'ExpandedFolders', '["a"]']);
	});

	it("recovers and still sends a pending save after an in-flight request fails", async() =>
	{
		const tree : any = await fixture(html`<et2-tree></et2-tree>`);
		const calls : any[][] = [];
		let rejectFirst : (e : Error) => void;
		//@ts-ignore
		window.egw.jsonq = (_menuaction : string, parameters : any[]) =>
		{
			calls.push(parameters);
			if (calls.length === 1) return new Promise<void>((_resolve, reject) => { rejectFirst = reject; });
			return Promise.resolve();
		};

		tree.flushOpenState(['mail', 'ExpandedFolders'], ['a']);
		tree.flushOpenState(['mail', 'ExpandedFolders'], ['a', 'b']);

		rejectFirst(new Error('simulated network failure'));
		await new Promise(resolve => setTimeout(resolve, 0));
		await new Promise(resolve => setTimeout(resolve, 0));

		assert.equal(calls.length, 2, "a failed save must not permanently block later ones");
		assert.deepEqual(JSON.parse(calls[1][2]), ['a', 'b']);
	});
});
