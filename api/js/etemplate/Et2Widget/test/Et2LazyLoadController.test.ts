import {assert, fixture, html} from "@open-wc/testing";
import {LitElement} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {Et2LazyLoadController} from "../Et2LazyLoadController";

/**
 * Contract: onReady only runs once the host is actually worth doing deferred work for -
 * connected, not hidden by CSS anywhere up the tree (the ancestor can hide it via a
 * custom property, as timesheet's row template does - see Et2LinkString.test.ts), and,
 * if the caller supplied one, whatever `isExtraReady` checks.  A host that starts hidden
 * (or with `isExtraReady` false) must produce zero calls until it actually becomes ready,
 * matching what a nextmatch row recycled for another entry needs: no wasted requests
 * while nobody can see or use the answer.
 *
 * Pass criteria: no call while hidden; exactly the expected call once shown; the extra
 * condition gates independently of visibility and only reacts to recheck().
 */

@customElement("test-lazy-load-host")
class TestLazyLoadHost extends LitElement
{
	calls : number = 0;
	extraReady = true;
	controller = new Et2LazyLoadController(this, () => this.calls++, () => this.extraReady);
}

// The IntersectionObserver callback is asynchronous even for a synchronous style change
const observed = () => new Promise(resolve => setTimeout(resolve, 50));

// Whether `promise` has already settled, without ever leaving it pending forever if it hasn't -
// a settled promise's .then() runs as a microtask, which always finishes before this timeout does
const isSettled = (promise : Promise<unknown>) =>
{
	let settled = false;
	promise.then(() => settled = true);
	return new Promise(resolve => setTimeout(() => resolve(settled), 0));
};

describe("Et2LazyLoadController", () =>
{
	it("does not call onReady while the host is hidden", async() =>
	{
		const wrapper = await fixture<HTMLElement>(html`
            <div style="display:none"><test-lazy-load-host></test-lazy-load-host></div>`);
		const host = wrapper.querySelector<TestLazyLoadHost>("test-lazy-load-host");

		await observed();

		assert.isFalse(host.controller.ready);
		assert.equal(host.calls, 0);
	});

	it("calls onReady once the host is shown", async() =>
	{
		const wrapper = await fixture<HTMLElement>(html`
            <div style="display:none"><test-lazy-load-host></test-lazy-load-host></div>`);
		const host = wrapper.querySelector<TestLazyLoadHost>("test-lazy-load-host");
		await observed();

		wrapper.style.display = "block";
		await observed();

		assert.isTrue(host.controller.ready);
		assert.isAtLeast(host.calls, 1);
	});

	it("reports ready for a hidden ancestor set via a custom property, not just an inline style", async() =>
	{
		// Mirrors how the timesheet list hides its rows' link lists - display comes from a
		// custom property set on a distant ancestor, which the host knows nothing about
		const wrapper = await fixture<HTMLElement>(html`
            <div style="--host-display:none">
                <test-lazy-load-host style="display:var(--host-display, inline)"></test-lazy-load-host>
            </div>`);
		const host = wrapper.querySelector<TestLazyLoadHost>("test-lazy-load-host");
		await observed();
		assert.isFalse(host.controller.ready);

		wrapper.style.setProperty("--host-display", "inline");
		await observed();

		assert.isTrue(host.controller.ready);
		assert.isAtLeast(host.calls, 1);
	});

	it("keeps the extra condition independent of visibility, reacting only to recheck()", async() =>
	{
		const host = await fixture<TestLazyLoadHost>(html`<test-lazy-load-host></test-lazy-load-host>`);
		host.extraReady = false;
		await observed();

		// Visible, but the extra condition isn't satisfied - and nothing prompted a recheck
		assert.isFalse(host.controller.ready);
		assert.equal(host.calls, 0);

		host.extraReady = true;
		// No IntersectionObserver event fires here - visibility never changed - so onReady
		// must not run until the caller explicitly asks for a recheck
		await observed();
		assert.equal(host.calls, 0, "ran before recheck() was called");

		host.controller.recheck();

		assert.equal(host.calls, 1);
	});

	it("does not call onReady from recheck() if the extra condition still isn't satisfied", async() =>
	{
		const host = await fixture<TestLazyLoadHost>(html`<test-lazy-load-host></test-lazy-load-host>`);
		host.extraReady = false;

		host.controller.recheck();

		assert.equal(host.calls, 0);
	});

	it("force() calls onReady immediately, bypassing both hidden and a false extra condition", async() =>
	{
		const wrapper = await fixture<HTMLElement>(html`
            <div style="display:none"><test-lazy-load-host></test-lazy-load-host></div>`);
		const host = wrapper.querySelector<TestLazyLoadHost>("test-lazy-load-host");
		host.extraReady = false;

		host.controller.force();

		assert.equal(host.calls, 1);
	});

	it("does not change what ready reports - force() is a one-off bypass, not a standing override", async() =>
	{
		const wrapper = await fixture<HTMLElement>(html`
            <div style="display:none"><test-lazy-load-host></test-lazy-load-host></div>`);
		const host = wrapper.querySelector<TestLazyLoadHost>("test-lazy-load-host");

		host.controller.force();

		assert.isFalse(host.controller.ready, "force() leaked into ready for later checks");
	});

	describe("whenReady", () =>
	{
		it("resolves right away when already ready", async() =>
		{
			const host = await fixture<TestLazyLoadHost>(html`<test-lazy-load-host></test-lazy-load-host>`);

			assert.isTrue(await isSettled(host.controller.whenReady));
		});

		it("stays pending while hidden, then resolves once shown", async() =>
		{
			const wrapper = await fixture<HTMLElement>(html`
                <div style="display:none"><test-lazy-load-host></test-lazy-load-host></div>`);
			const host = wrapper.querySelector<TestLazyLoadHost>("test-lazy-load-host");

			const whenReady = host.controller.whenReady;
			assert.isFalse(await isSettled(whenReady), "resolved while still hidden");

			wrapper.style.display = "block";
			await observed();

			assert.isTrue(await isSettled(whenReady));
		});

		it("resolves via recheck(), same as onReady does", async() =>
		{
			const host = await fixture<TestLazyLoadHost>(html`<test-lazy-load-host></test-lazy-load-host>`);
			host.extraReady = false;

			const whenReady = host.controller.whenReady;
			assert.isFalse(await isSettled(whenReady));

			host.extraReady = true;
			host.controller.recheck();

			assert.isTrue(await isSettled(whenReady));
		});

		it("resolves via force(), even though ready is still false", async() =>
		{
			const wrapper = await fixture<HTMLElement>(html`
                <div style="display:none"><test-lazy-load-host></test-lazy-load-host></div>`);
			const host = wrapper.querySelector<TestLazyLoadHost>("test-lazy-load-host");

			const whenReady = host.controller.whenReady;
			host.controller.force();

			assert.isTrue(await isSettled(whenReady));
			assert.isFalse(host.controller.ready);
		});

		it("hands out a fresh pending promise on the next read, once the last one has settled", async() =>
		{
			const wrapper = await fixture<HTMLElement>(html`
                <div style="display:none"><test-lazy-load-host></test-lazy-load-host></div>`);
			const host = wrapper.querySelector<TestLazyLoadHost>("test-lazy-load-host");

			const first = host.controller.whenReady;
			host.controller.force();
			await first;

			// Still hidden - force() doesn't change what ready reports - so reading whenReady
			// again must wait again, not hand back something already resolved from before
			const second = host.controller.whenReady;
			assert.isFalse(await isSettled(second), "reused a settled promise from the first force()");
		});
	});
});
