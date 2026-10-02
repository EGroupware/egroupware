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

// A host that defers its work with defer(), finishing it when the test says so
@customElement("test-lazy-defer-host")
class TestLazyDeferHost extends LitElement
{
	controller = new Et2LazyLoadController(this);
	loaded = false;
	finish : () => void;
	fail : (e : Error) => void;

	connectedCallback()
	{
		super.connectedCallback();
		this.controller.defer(() => new Promise<void>((resolve, reject) =>
		{
			this.finish = () => { this.loaded = true; resolve(); };
			this.fail = reject;
		})).catch(() => {});
	}
}

// A printable widget in a host's shadow root, eg. Et2Historylog's Et2Datagrid
@customElement("test-lazy-printable")
class TestLazyPrintable extends LitElement
{
	calls : string[] = [];
	beforePrint() { this.calls.push("before"); }
	afterPrint() { this.calls.push("after"); }
}

@customElement("test-lazy-shadow-host")
class TestLazyShadowHost extends TestLazyDeferHost
{
	render()
	{
		return html`<test-lazy-printable></test-lazy-printable>`;
	}
}

// A host that prints itself, eg. Et2Nextmatch
@customElement("test-lazy-print-host")
class TestLazyPrintHost extends LitElement
{
	controller = new Et2LazyLoadController(this);
	beforePrint() { return "own"; }
	afterPrint() {}
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

	describe("printing", () =>
	{
		/**
		 * Printing shows what nobody opened and scrolls nothing into view, so the controller makes
		 * its host an et2_IPrint: etemplate2.print() calls the host's beforePrint(), which must
		 * open the gates and resolve only once the host's deferred work is done.
		 */
		const hidden = async(tag) =>
		{
			const wrapper = await fixture<HTMLElement>(html`<div style="display:none"></div>`);
			const host = document.createElement(tag);
			wrapper.append(host);
			await (<any>host).updateComplete;
			return <any>host;
		};

		it("gives a host without print handling beforePrint() and afterPrint()", async() =>
		{
			const host = await hidden("test-lazy-defer-host");
			assert.isFunction(host.beforePrint);
			assert.isFunction(host.afterPrint);
		});

		it("leaves a host's own beforePrint() alone", async() =>
		{
			const host = await hidden("test-lazy-print-host");
			assert.equal(host.beforePrint(), "own");
		});

		it("beforePrint() starts the deferred work and waits for it", async() =>
		{
			const host = await hidden("test-lazy-defer-host");
			await observed();
			assert.isUndefined(host.finish, "test setup: work started while hidden");

			const printed = host.beforePrint();
			await isSettled(printed);
			assert.isFunction(host.finish, "beforePrint() did not start the deferred work");
			assert.isFalse(await isSettled(printed), "beforePrint() did not wait for the work");

			host.finish();
			await printed;
			assert.isTrue(host.loaded);
		});

		it("beforePrint() resolves when the deferred work fails", async() =>
		{
			const host = await hidden("test-lazy-defer-host");
			const printed = host.beforePrint();
			await isSettled(printed);
			host.fail(new Error("load failed"));

			assert.isTrue(await isSettled(printed), "a failed load stopped the print");
		});

		it("beforePrint() prepares the printable widgets in the host's shadow root, after the work", async() =>
		{
			const host = await hidden("test-lazy-shadow-host");
			// etemplate2.print() only calls beforePrint() for a displayed host
			host.parentElement.style.display = "block";
			const printable = host.shadowRoot.querySelector("test-lazy-printable");

			const printed = host.beforePrint();
			await isSettled(printed);
			assert.isEmpty(printable.calls, "prepared the shadow widget before the deferred work was done");

			host.finish();
			await printed;
			assert.deepEqual(printable.calls, ["before"]);

			host.afterPrint();
			assert.deepEqual(printable.calls, ["before", "after"]);
		});

		it("beforePrint() waits for a Promise returned by onReady", async() =>
		{
			let finish;
			const host = await hidden("test-lazy-load-host");
			(<any>host.controller).onReady = () => new Promise(resolve => finish = resolve);

			const printed = host.beforePrint();
			assert.isFunction(finish, "beforePrint() did not call onReady");
			assert.isFalse(await isSettled(printed), "beforePrint() did not wait for onReady");
			finish();
			assert.isTrue(await isSettled(printed));
		});
	});
});
