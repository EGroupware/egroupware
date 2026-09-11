/**
 * Tests for egw_ready.js ("ready" module) - MODULE_WND_LOCAL.
 *
 * Covers readyWaitFor()/readyDone() token accounting (including
 * out-of-order resolution and cross-window token isolation), ready()'s
 * "before" vs. normal callback firing rules, readyProgress() dispatch
 * order, _context ("this") binding for both ready() and readyProgress(),
 * the window "load" event as an alternate/redundant trigger to
 * DOMContentLoaded, and per-window isolation. See EgwReadyHarness for how
 * the DOMContentLoaded/load lifecycle is simulated.
 *
 * testCallReady() used to declare `var isReady = ...` inside its own
 * function body, which shadowed the factory-level `isReady` closure
 * variable instead of updating it - so the outer `isReady` never actually
 * became true. Fixed (removed the `var`); the "after the fix" describe
 * block below covers the three behaviours that bug used to break:
 * isReady() reporting correctly, a ready() callback registered after full
 * readiness firing via the documented setTimeout path, and readyProgress()
 * correctly warning instead of silently re-registering once already ready.
 */
import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {createEgwReadyEnv, EgwReadyEnv} from "./EgwReadyHarness";

function wait(ms : number) : Promise<void>
{
	return new Promise(resolve => setTimeout(resolve, ms));
}

describe('egw_ready.js (ready)', () =>
{
	let env : EgwReadyEnv;

	beforeEach(async() =>
	{
		env = await createEgwReadyEnv();
	});

	afterEach(() =>
	{
		env.destroy();
	});

	it('loads and registers the expected methods', () =>
	{
		const instance = env.egw();
		assert.isFunction(instance.readyWaitFor);
		assert.isFunction(instance.readyDone);
		assert.isFunction(instance.ready);
		assert.isFunction(instance.readyProgress);
		assert.isFunction(instance.isReady);
	});

	describe('readyWaitFor() / readyDone() token accounting', () =>
	{
		it('readyWaitFor() returns a unique token each time, and increases the pending count', () =>
		{
			const progress = sinon.stub();
			env.egw().readyProgress(progress);

			const tokenA = env.egw().readyWaitFor();
			const tokenB = env.egw().readyWaitFor();

			assert.isString(tokenA);
			assert.notEqual(tokenA, tokenB);
			// starts at 1 pending (the built-in "readyEvent"), then +1 per readyWaitFor()
			assert.deepEqual(progress.firstCall.args, [0, 2]);
			assert.deepEqual(progress.secondCall.args, [0, 3]);
		});

		it('readyDone() with an unknown/bogus token is silently ignored', () =>
		{
			const progress = sinon.stub();
			env.egw().readyProgress(progress);

			env.egw().readyDone('no-such-token');

			assert.isFalse(progress.called, 'an unknown token must not trigger a progress change');
		});

		it('readyDone() cannot be replayed for the same token twice', () =>
		{
			const progress = sinon.stub();
			const token = env.egw().readyWaitFor();
			env.egw().readyProgress(progress);

			env.egw().readyDone(token);
			env.egw().readyDone(token);

			assert.equal(progress.callCount, 1, 'the second readyDone() for the same token must be a no-op');
		});

		it('resolves tokens correctly out of order (a later token resolved before an earlier one)', () =>
		{
			const progress = sinon.stub();
			const tokenA = env.egw().readyWaitFor();
			const tokenB = env.egw().readyWaitFor();
			env.egw().readyProgress(progress);
			// pending: readyEvent + A + B = 3

			env.egw().readyDone(tokenB);
			assert.deepEqual(progress.firstCall.args, [1, 2], '[done, pending] after resolving B first');

			env.egw().readyDone(tokenA);
			assert.deepEqual(progress.secondCall.args, [2, 1], 'readyEvent is the only one left');
		});

		it('a token from one window is meaningless in another (readyDone is a no-op across windows)', () =>
		{
			const otherWindow = env.createWindow();
			const tokenInMain = env.egw().readyWaitFor();

			env.egw('otherapp', otherWindow).readyDone(tokenInMain);

			// still pending in the main window - readyEvent + our token = 2
			const progress = sinon.stub();
			env.egw().readyProgress(progress);
			env.egw().readyWaitFor();
			assert.deepEqual(progress.firstCall.args, [0, 3]);
		});
	});

	describe('readyProgress()', () =>
	{
		it('calls registered callbacks in LIFO order (most recently registered first)', () =>
		{
			const calls : string[] = [];
			env.egw().readyProgress(() => calls.push('first'));
			env.egw().readyProgress(() => calls.push('second'));

			env.egw().readyWaitFor();

			assert.deepEqual(calls, ['second', 'first']);
		});

		it('calls the callback with the given _context as `this`', () =>
		{
			const context = {label: 'my-context'};
			let seenThis : any;
			env.egw().readyProgress(function(this : any) { seenThis = this; }, context);

			env.egw().readyWaitFor();

			assert.strictEqual(seenThis, context);
		});
	});

	describe('ready() callback firing rules', () =>
	{
		it('does not fire a normal callback while any non-"before" wait is still pending', () =>
		{
			const cb = sinon.stub();
			const token = env.egw().readyWaitFor();
			env.egw().ready(cb);

			env.egw().readyDone(token); // only clears the custom token; built-in "readyEvent" still pending

			assert.isFalse(cb.called);
		});

		it('fires all pending callbacks once the built-in DOMContentLoaded/load event resolves the last pending item', () =>
		{
			const cb = sinon.stub();
			env.egw().ready(cb);

			env.fireReadyEvent();

			assert.isTrue(cb.calledOnce);
		});

		it('a "before" callback fires as soon as readyEvent is the ONLY thing left pending - even before it resolves', () =>
		{
			// register a custom wait, register a "before" callback, then
			// resolve everything EXCEPT the built-in readyEvent: this drops
			// pendingCnt to 1 with readyEvent as the sole remaining item,
			// which is exactly the condition that lets "before" callbacks
			// fire ahead of full readiness.
			const beforeCb = sinon.stub();
			const normalCb = sinon.stub();
			const token = env.egw().readyWaitFor();
			env.egw().ready(beforeCb, null, true);
			env.egw().ready(normalCb);

			env.egw().readyDone(token);

			assert.isTrue(beforeCb.calledOnce, '"before" callback must fire once only readyEvent remains');
			assert.isFalse(normalCb.called, 'a normal callback must still wait for readyEvent itself');

			env.fireReadyEvent();
			assert.isTrue(normalCb.calledOnce);
			assert.isTrue(beforeCb.calledOnce, 'must not fire a second time once actually ready');
		});

		it('a "before" callback does NOT fire early if a custom wait - not readyEvent - is the sole remaining item', () =>
		{
			// Resolve the built-in readyEvent FIRST, leaving only a custom
			// token pending. testCallReady()'s early-return guard
			// (pendingCnt === 1 && the remaining one isn't readyEvent)
			// blocks the whole function in this case, so even "before"
			// callbacks are skipped until full completion.
			const beforeCb = sinon.stub();
			const token = env.egw().readyWaitFor();
			env.egw().ready(beforeCb, null, true);

			env.fireReadyEvent(); // pendingCnt: 2 -> 1, the "1" being the custom token

			assert.isFalse(beforeCb.called);

			env.egw().readyDone(token); // pendingCnt: 1 -> 0, now everything fires
			assert.isTrue(beforeCb.calledOnce);
		});

		it('fires multiple eligible callbacks in LIFO order too', () =>
		{
			const calls : string[] = [];
			env.egw().ready(() => calls.push('first'));
			env.egw().ready(() => calls.push('second'));

			env.fireReadyEvent();

			assert.deepEqual(calls, ['second', 'first']);
		});

		it('calls the callback with the given _context as `this`', () =>
		{
			const context = {label: 'my-context'};
			let seenThis : any;
			env.egw().ready(function(this : any) { seenThis = this; }, context);

			env.fireReadyEvent();

			assert.strictEqual(seenThis, context);
		});

		it('the window\'s "load" event resolves readyEvent exactly like DOMContentLoaded does', () =>
		{
			const cb = sinon.stub();
			env.egw().ready(cb);

			env.fireLoadEvent();

			assert.isTrue(cb.calledOnce);
		});

		it('firing both DOMContentLoaded and load only resolves readyEvent once (registeredCallbacks fire a single time)', () =>
		{
			const cb = sinon.stub();
			env.egw().ready(cb);

			env.fireReadyEvent();
			env.fireLoadEvent();

			assert.isTrue(cb.calledOnce, 'the second event must find readyEvent already removed from readyPending and no-op');
		});
	});

	describe('isReady() and late registration, after fixing the `var isReady` shadowing bug', () =>
	{
		it('isReady() reports false until fully ready, then true', () =>
		{
			assert.isFalse(env.egw().isReady());

			env.fireReadyEvent();

			assert.isTrue(env.egw().isReady());
		});

		it('a ready() callback registered AFTER full readiness fires via the documented "already ready" setTimeout path', async() =>
		{
			env.fireReadyEvent(); // fully ready now

			const lateCb = sinon.stub();
			env.egw().ready(lateCb);

			assert.isFalse(lateCb.called, 'must not fire synchronously');
			await wait(20); // longer than the documented 1ms setTimeout
			assert.isTrue(lateCb.calledOnce);
		});

		it('readyProgress() warns instead of registering once already ready', () =>
		{
			const debugSpy = sinon.spy(env.egw(), 'debug');

			env.fireReadyEvent();
			env.egw().readyProgress(sinon.stub());

			assert.isTrue(debugSpy.calledWith('warning', 'ready has already been called!'));
		});

		it('readyWaitFor() likewise warns and refuses to hand out a new token once already ready', () =>
		{
			// doReadyWaitFor()'s "already ready" branch used to call
			// `this.debug(...)`, but it's invoked as a bare function
			// (readyWaitFor: function() { return doReadyWaitFor(); }), so
			// `this` was undefined in strict mode - this path was dead code
			// while the isReady bug masked it (isReady was never true), and
			// started throwing the moment that bug was fixed. Fixed to use
			// the closure-captured `egw` reference, matching the rest of the file.
			const debugSpy = sinon.spy(env.egw(), 'debug');

			env.fireReadyEvent();
			const token = env.egw().readyWaitFor();

			assert.isNull(token);
			assert.isTrue(debugSpy.calledWith('warning', 'ready has already been called!'));
		});
	});

	describe('per-window isolation (MODULE_WND_LOCAL)', () =>
	{
		it('two windows track independent ready state', () =>
		{
			const otherWindow = env.createWindow();

			const mainCb = sinon.stub();
			const otherCb = sinon.stub();
			env.egw().ready(mainCb);
			env.egw('otherapp', otherWindow).ready(otherCb);

			env.fireReadyEvent(otherWindow);

			assert.isTrue(otherCb.calledOnce);
			assert.isFalse(mainCb.called, 'resolving the other window\'s readyEvent must not affect this window');

			env.fireReadyEvent();
			assert.isTrue(mainCb.calledOnce);
		});
	});
});
