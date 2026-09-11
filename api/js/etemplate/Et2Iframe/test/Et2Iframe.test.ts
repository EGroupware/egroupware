import {assert, fixture, html} from "@open-wc/testing";
import * as sinon from "sinon";
import {Et2Iframe} from "../Et2Iframe";

// Stub global egw
// @ts-ignore
const egw = {
	app_name: () => "test",
	appName: "test",
	debug: () => {},
	image: () => "",
	lang: i => i,
	link: i => i,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	uniqueId: () => "1",
	webserverUrl: ""
};
window.egw = function() {return egw};
Object.assign(window.egw, egw);

// set_src()'s non-"about:blank" path defers the actual node.src assignment via
// window.setTimeout(..., 0) to show a loading spinner first - wait one macrotask for it.
const aTick = () => new Promise<void>(resolve => setTimeout(resolve, 0));

describe("et2-iframe", () =>
{
	it("renders a real <iframe> in its shadow root", async() =>
	{
		const el = await fixture<Et2Iframe>(html`
            <et2-iframe></et2-iframe>`);
		assert.ok(el);
		assert.instanceOf(el, Et2Iframe);
		assert.instanceOf(el.__getIframeNode(), HTMLIFrameElement);
	});

	it("set_src() loads the URL into the real <iframe>", async() =>
	{
		const el = await fixture<Et2Iframe>(html`
            <et2-iframe></et2-iframe>`);
		el.set_src("https://example.invalid/doc.pdf");
		await aTick();

		assert.equal(el.__getIframeNode().src, "https://example.invalid/doc.pdf");
	});

	it("set_src('about:blank') applies immediately, without the loader delay", async() =>
	{
		const el = await fixture<Et2Iframe>(html`
            <et2-iframe></et2-iframe>`);
		el.__getIframeNode().setAttribute("srcdoc", "<p>leftover</p>");

		el.set_src("about:blank");

		assert.equal(el.__getIframeNode().src, "about:blank");
		assert.isFalse(el.__getIframeNode().hasAttribute("srcdoc"));
	});

	// Regression test for https://github.com/asig2016/egroupware/commit/2adb786f9d78a390fabfd7e09444f0a3c7b7d3ae:
	// a grid (eg. acdms' document preview) hands its content down by assigning the
	// reactive `src` property directly, never calling set_src(). render() never bound
	// `src` to the real <iframe>, so the document stayed blank.
	it("setting the src property directly (not via set_src()) loads it into the real <iframe>", async() =>
	{
		const el = await fixture<Et2Iframe>(html`
            <et2-iframe></et2-iframe>`);

		el.src = "https://example.invalid/doc.pdf";
		await el.updateComplete;
		await aTick();

		assert.equal(el.__getIframeNode().src, "https://example.invalid/doc.pdf");
	});

	it("a src attribute set in the template loads on first render", async() =>
	{
		const el = await fixture<Et2Iframe>(html`
            <et2-iframe src="https://example.invalid/initial.pdf"></et2-iframe>`);
		await aTick();

		assert.equal(el.__getIframeNode().src, "https://example.invalid/initial.pdf");
	});

	it("does not reload the same src twice (set_src() then the resulting property update)", async() =>
	{
		const el = await fixture<Et2Iframe>(html`
            <et2-iframe></et2-iframe>`);
		// __applySrc() is what actually touches the real <iframe> node - spy on it
		// directly rather than on the iframe's own (genuinely async, unreliable in a
		// test environment for a fake domain) 'load' event.
		const applySrc = sinon.spy(el as any, "__applySrc");

		el.set_src("https://example.invalid/doc.pdf");
		await aTick();
		assert.equal(applySrc.callCount, 1, "set_src() applies once");

		// set_src() above already assigned el.src, which schedules an updated() pass -
		// let that run and confirm it did not call __applySrc() a second time for the
		// same value it just applied.
		await el.updateComplete;
		await aTick();

		assert.equal(el.__getIframeNode().src, "https://example.invalid/doc.pdf");
		assert.equal(applySrc.callCount, 1, "no spurious re-apply from the property update echoing set_src()'s own change");
	});

	it("set_value() with a URL-like value routes through set_src()", async() =>
	{
		const el = await fixture<Et2Iframe>(html`
            <et2-iframe></et2-iframe>`);
		el.set_value("https://example.invalid/doc.pdf");
		await aTick();

		assert.equal(el.__getIframeNode().src, "https://example.invalid/doc.pdf");
	});

	it("set_value() with plain content routes through set_srcdoc()", async() =>
	{
		const el = await fixture<Et2Iframe>(html`
            <et2-iframe></et2-iframe>`);
		el.set_value("<p>Hello</p>");

		assert.equal(el.__getIframeNode().getAttribute("srcdoc"), "<p>Hello</p>");
	});

	// Regression test for the crash this session found live-testing admin:
	// transformAttributes() (initial widget-tree construction from XML) can call
	// set_src()/set_value() before this element is ever connected to the document,
	// when this.shadowRoot (and so the real <iframe>) doesn't exist yet.
	it("set_src() called before the element is connected does not throw, and applies once connected", async() =>
	{
		const el = document.createElement("et2-iframe") as Et2Iframe;

		assert.doesNotThrow(() => el.set_src("https://example.invalid/doc.pdf"));

		document.body.append(el);
		await el.updateComplete;
		await aTick();

		assert.equal(el.__getIframeNode().src, "https://example.invalid/doc.pdf");
		el.remove();
	});

	it("set_name()/set_allow()/set_seamless() reach the real <iframe>'s attributes", async() =>
	{
		const el = await fixture<Et2Iframe>(html`
            <et2-iframe></et2-iframe>`);
		el.set_name("myframe");
		el.set_allow("fullscreen");
		el.set_seamless(true);

		const node = el.__getIframeNode();
		assert.equal(node.getAttribute("name"), "myframe");
		assert.equal(node.getAttribute("allow"), "fullscreen");
		assert.equal(node.getAttribute("seamless"), "true");
	});
});
