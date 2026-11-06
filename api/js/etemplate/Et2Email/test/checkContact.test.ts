import {assert} from "@open-wc/testing";
import {checkContact} from "../utils";

/**
 * Contract: checkContact() answers every caller, and a lookup that failed does not stop later
 * ones from being made.
 *
 * Why it matters: the lookup state is module-wide. The old code reset it only when the request
 * succeeded, so one failed request (offline, an expired session) left every later call queued
 * behind a request that was never going to answer - no email tag or avatar in mail, compose or
 * the addressbook ever resolved its contact again until the page was reloaded, and each held its
 * widget while it waited.
 *
 * Setup: window.egw.jsonq is stubbed. Addresses are unique per test, as results are cached by address.
 *
 * Pass criteria: callers of a failed lookup are answered false, the failure is not cached, and the
 * next call performs a new lookup that resolves normally.
 *
 * Environment: no network.
 */
describe("checkContact()", () =>
{
	let jsonq : (menuaction : string, params : any[], callback, sender, beforeSend : Function) => Promise<any>;
	let calls : number;
	const original = (<any>window).egw;

	beforeEach(() =>
	{
		calls = 0;
		(<any>window).egw = {
			jsonq: (menuaction, params, callback, sender, beforeSend) => jsonq(menuaction, params, callback, sender, beforeSend)
		};
	});

	afterEach(() =>
	{
		(<any>window).egw = original;
	});

	const withTimeout = <T>(promise : Promise<T>, ms = 1000) : Promise<T | "TIMED_OUT"> => Promise.race([
		promise,
		new Promise<"TIMED_OUT">(resolve => setTimeout(() => resolve("TIMED_OUT"), ms))
	]);

	it("resolves a caller with what the server returned for its address", async() =>
	{
		jsonq = (_m, _p, _c, _s, beforeSend) =>
		{
			calls++;
			// like the real jsonq(), asks for the parameters when it sends, not when it is called
			return Promise.resolve().then(() =>
			{
				const params : any[] = [[]];
				beforeSend(params);
				return {[params[0][0]]: {id: 5, n_fn: "Found"}};
			});
		};

		const result : any = await withTimeout(checkContact("found@example.invalid"));

		assert.equal(result.n_fn, "Found");
		assert.equal(calls, 1);
	});

	it("answers a caller false when the lookup fails, and looks again for the next one", async() =>
	{
		jsonq = () =>
		{
			calls++;
			return Promise.reject(new Error("network down"));
		};
		const failed = await withTimeout(checkContact("failed@example.invalid"));
		assert.strictEqual(failed, false, "a failed lookup must answer its callers, not leave them waiting");

		jsonq = (_m, _p, _c, _s, beforeSend) =>
		{
			calls++;
			// like the real jsonq(), asks for the parameters when it sends, not when it is called
			return Promise.resolve().then(() =>
			{
				const params : any[] = [[]];
				beforeSend(params);
				return {[params[0][0]]: {id: 6, n_fn: "Recovered"}};
			});
		};
		const recovered : any = await withTimeout(checkContact("recovered@example.invalid"));

		assert.notEqual(recovered, "TIMED_OUT", "the failure must not wedge every later lookup");
		assert.equal(recovered.n_fn, "Recovered");
		assert.equal(calls, 2, "the second call performed its own lookup");

		// the failure was not cached as "no such contact"
		jsonq = (_m, _p, _c, _s, beforeSend) =>
		{
			calls++;
			// like the real jsonq(), asks for the parameters when it sends, not when it is called
			return Promise.resolve().then(() =>
			{
				const params : any[] = [[]];
				beforeSend(params);
				return {[params[0][0]]: {id: 7, n_fn: "Later"}};
			});
		};
		const retried : any = await withTimeout(checkContact("failed@example.invalid"));
		assert.equal(retried.n_fn, "Later");
	});
});
