import {assert, fixture, html, oneEvent} from "@open-wc/testing";
import * as sinon from "sinon";
import pdfjs from "@bundled-es-modules/pdfjs-dist/build/pdf";
import "../pdf-player";

// pdf-player.ts sets GlobalWorkerOptions.workerSrc to a path relative to the current page
// ('node_modules/...', no leading slash) - resolves fine from a page served at the app root, but
// this test file's own page lives several directories deep, so that relative path 404s the real
// worker, and pdf.js then hangs waiting for it rather than failing fast. Override it with the same
// file made root-relative (matches other test config in this repo for other node_modules assets),
// AND pass `disableWorker: true` on every getDocument() call below (so parsing/rendering runs on
// the main thread - a real, supported pdf.js mode, not a mock): disableWorker alone still isn't
// enough for a document that fails to parse at all, which hits an internal check for a configured
// workerSrc before it gets far enough to skip actually using one.
pdfjs.GlobalWorkerOptions.workerSrc = "/node_modules/@bundled-es-modules/pdfjs-dist/build/pdf.worker.js";

// Stub global egw - pdf-player.ts calls bare egw.message() on a load error
// @ts-ignore
const egw = {
	message: () => {}
};
window.egw = function() {return egw};
Object.assign(window.egw, egw);

/**
 * Builds a minimal, valid (but content-less) multi-page PDF as raw bytes, for feeding straight
 * into pdfjs.getDocument({data: ...}) - no xref/startxref table, relying on pdf.js's own
 * "brute-force scan for N G obj" recovery path (well-established, used by pdf.js for real-world
 * malformed PDFs) rather than getting exact byte offsets right by hand.
 */
function minimalPdf(pageCount : number) : Uint8Array
{
	const kids = [];
	let pages = "";
	for(let i = 0; i < pageCount; i++)
	{
		const objNum = 3 + i;
		kids.push(`${objNum} 0 R`);
		pages += `${objNum} 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\n`;
	}
	const pdf = `%PDF-1.1\n` +
		`1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n` +
		`2 0 obj<</Type/Pages/Kids[${kids.join(" ")}]/Count ${pageCount}>>endobj\n` +
		pages +
		`trailer<</Root 1 0 R>>\n`;
	return new TextEncoder().encode(pdf);
}

// set currentTime()'s __render() call is fire-and-forget (its promise is neither returned nor
// awaited) - a test that calls nextPage()/prevPage() and returns immediately leaves that render
// promise dangling, and once the fixture is torn down (destroying the pdf), it settles anyway and
// rejects with "Transport destroyed" as an unhandled rejection *outside* any test, confusing later
// tests. Give it a real tick to settle first.
const aTick = () => new Promise<void>(resolve => setTimeout(resolve, 50));

describe("pdf-player", () =>
{
	/**
	 * Loads a fixture pdf-player with a real (fixture) multi-page PDF and waits for the
	 * 'loadedmetadata' event __buildPDFView() fires after rendering page 1.
	 */
	async function loadedPlayer(pageCount = 2) : Promise<any>
	{
		const el : any = await fixture(html`
            <pdf-player></pdf-player>`);
		const loaded = oneEvent(el, "loadedmetadata");
		el.src = {data: minimalPdf(pageCount), disableWorker: true};
		await loaded;
		// 'loadedmetadata' fires right after page 1's render() promise resolves, but pdf.js does
		// not release the canvas's internal render lock until a tick later - calling nextPage()
		// immediately can still collide with it. Give it a moment to fully settle.
		await aTick();
		return el;
	}

	it("loads a PDF, renders page 1 into a real <canvas>, and reports its page count as duration", async() =>
	{
		const el = await loadedPlayer(2);

		assert.equal(el.duration, 2, "duration must be the PDF's page count");
		const canvas = el.shadowRoot.querySelector("canvas");
		assert.ok(canvas, "a <canvas> must exist in the shadow root");
		assert.isAbove(canvas.width, 0, "the rendered page must have a real pixel width");
		assert.isAbove(canvas.height, 0, "the rendered page must have a real pixel height");
	});

	// Regression test: the src getter/setter pair was dead code until this test suite exercised
	// it - get src() returned `this.src` (itself, ie. infinite recursion / stack overflow) and
	// set src() called `.forEach()` on `this._wrapper.children`, an HTMLCollection, which has no
	// forEach() (unlike the NodeList querySelectorAll() returns) and throws a TypeError. Neither
	// was ever reached in production: et2_video.ts drives this element exclusively through the
	// 'src' ATTRIBUTE (jQuery .attr('src', ...) -> attributeChangedCallback() -> __buildPDFView()
	// directly), never the property. Both are fixed alongside this test.
	it("the src property getter/setter round-trip works (previously dead/broken code)", async() =>
	{
		const el = await loadedPlayer(2);
		const newSrc = {data: minimalPdf(1), disableWorker: true};

		assert.doesNotThrow(() => el.src = newSrc, "setter must not throw (was: HTMLCollection has no forEach)");
		assert.strictEqual(el.src, newSrc, "getter must return what was set, not recurse into itself");
	});

	// NOTE: __render()'s page.render() call is NOT awaited/cancelled by set currentTime() before
	// starting the next one - calling nextPage()/prevPage() again before the previous render on
	// the same <canvas> has finished throws pdf.js's own "Cannot use the same canvas during
	// multiple render() operations" (confirmed while writing this test: real pdf.js behavior, not
	// a test artifact). A real user could hit this by clicking next/prev fast enough - a
	// pre-existing bug, out of scope to fix here, but worth knowing. This test therefore awaits
	// each page turn before starting the next one.
	it("nextPage()/prevPage() navigate between real pages and update currentTime", async() =>
	{
		const el = await loadedPlayer(2);

		el.nextPage();
		await aTick();
		assert.equal(el.currentTime, 1);

		el.nextPage();
		await aTick();
		assert.equal(el.currentTime, 2);

		el.prevPage();
		await aTick();
		assert.equal(el.currentTime, 1);
	});

	it("going past the last page marks ended without moving currentTime further", async() =>
	{
		const el = await loadedPlayer(2);

		el.nextPage(); // currentTime 1
		await aTick();
		el.nextPage(); // currentTime 2 (last page)
		await aTick();
		assert.isFalse(el.ended);

		el.nextPage(); // 3 > duration(2)
		assert.isTrue(el.ended, "must be marked ended once past the last page");
		assert.equal(el.currentTime, 2, "currentTime must not advance past the last real page");
		await aTick();
	});

	it("play() advances through pages on an interval, then pauses itself at the end", async() =>
	{
		// pdf.js's disableWorker mode schedules its main-thread "fake worker" message loop via
		// real setTimeout() internally - fake timers must not go in until AFTER loading finishes,
		// or that internal scheduling freezes and loadedPlayer() never resolves.
		const el = await loadedPlayer(2);
		const clock = sinon.useFakeTimers();
		try
		{
			// NOTE: set playbackRate(seconds) stores seconds*1000 (ms) internally, but the getter
			// returns that raw internal value rather than dividing back down - so the getter/setter
			// pair does not round-trip (another pre-existing asymmetry, like the src bug above, but
			// left alone since it doesn't throw and isn't part of what this suite is characterizing).
			// The default (never explicitly set) is 1000.
			assert.equal(el.playbackRate, 1000);

			await el.play();
			assert.isFalse(el.paused);

			clock.tick(1000); // -> currentTime 1
			clock.tick(1000); // -> currentTime 2 (last page)
			assert.equal(el.currentTime, 2);
			assert.isFalse(el.paused, "not yet paused - the interval only checks at the START of the NEXT tick");

			clock.tick(1000); // interval sees currentTime(2) >= duration(2) -> ends + pauses itself
			assert.isTrue(el.ended);
			assert.isTrue(el.paused);
			assert.equal(el.currentTime, 2, "currentTime must not go past the last page even once ended");

			// interval must really be cleared - further ticks change nothing
			clock.tick(5000);
			assert.equal(el.currentTime, 2);
		}
		finally
		{
			clock.restore();
		}
	});

	// NOTE: this test may log a harmless "Transport destroyed" unhandled-rejection browser console
	// error (not a test failure - verified stable green across repeated runs). It comes from a
	// dangling internal pdf.js promise settling after disconnectedCallback() has already destroyed
	// the pdf, the same general class of fire-and-forget-render timing noise documented on the
	// nextPage()/prevPage() test above, just not fully suppressible here without changing
	// production code.
	it("disconnectedCallback() destroys the pdf document and stops a running play() interval", async() =>
	{
		const el = await loadedPlayer(2);
		const clock = sinon.useFakeTimers();
		try
		{
			const pdf = el.__pdfViewState.pdf;
			const destroy = sinon.spy(pdf, "destroy");

			await el.play();
			el.remove();

			assert.isTrue(destroy.calledOnce);
			assert.isNull(el.__pdfViewState.pdf);

			// the play() interval must be cleared too - ticking further must not throw or push events
			assert.doesNotThrow(() => clock.tick(10000));
		}
		finally
		{
			clock.restore();
		}
	});

	it("a load error is reported via egw.message('error'), not thrown", async() =>
	{
		// spy on window.egw.message itself, NOT window.egw().message: Object.assign() above copied
		// the function VALUE onto window.egw as a separate property, not a live reference to the
		// inner egw object - pdf-player.ts's bare `egw.message(...)` call resolves to the global
		// window.egw (a function with .message attached), so that's what has to be spied on.
		const message = sinon.spy(window.egw, "message");
		const el : any = await fixture(html`
            <pdf-player></pdf-player>`);

		el.src = {data: new Uint8Array([1, 2, 3, 4]), disableWorker: true};
		await new Promise<void>((resolve, reject) =>
		{
			const deadline = Date.now() + 4000;
			const check = () =>
			{
				if(message.called)
				{
					resolve();
				}
				else if(Date.now() > deadline)
				{
					reject(new Error("egw.message('error') was never called"));
				}
				else
				{
					setTimeout(check, 10);
				}
			};
			check();
		});

		assert.isTrue(message.calledOnce);
		assert.equal(message.firstCall.args[1], "error");
	});
});
