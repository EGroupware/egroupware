import {assert, fixture} from "@open-wc/testing";
import * as sinon from "sinon";
import {LitElement} from "lit";
import {ExposeMixin} from "../ExposeMixin";

/**
 * Real report (ralf, 2026-09-15): "for me the download button / disk icon in expose does NOT
 * work at all" - clicking it silently did nothing, no visible error. Live-reproduced (a Tracker
 * Links-tab file attachment, my.egroupware.org) and root-caused via the browser console:
 *
 *   TypeError: this.getInstanceManager is not a function
 *       at HTMLAnchorElement.handleDownload (...)
 *
 * ExposeMixin's connectedCallback() renders the gallery's static markup via lit's standalone
 * render() straight into `document.body` (blueimp-gallery needs one shared, page-level container,
 * not the widget's own shadow DOM) - `render(this._galleryTemplate(), document.body)`, WITHOUT a
 * `{host: this}` option (see Et2Diff.ts for the one other place in this codebase that option is
 * used, and why). Without it, lit's own @click=${this.handleDownload} binding inside that
 * template can't resolve the right `this` when the event actually fires - `this` inside
 * handleDownload ends up being lit's own internal event-binding object, which obviously has no
 * getInstanceManager() of its own.
 *
 * Every OTHER handler this template's blueimp.Gallery instance invokes directly (onclick/onopen/
 * onslide/...) was ALREADY manually re-bound onto the instance in the constructor for exactly
 * this class of reason (blueimp-gallery calls those with ITS OWN `this`, unrelated to lit
 * entirely) - handleDownload was simply left off that list, since it is instead invoked via lit's
 * own template event binding, not by blueimp-gallery directly. Fixed by adding it to the same
 * list, so it carries the correct `this` regardless of how it ends up being invoked.
 *
 * Both assertions below share ONE fixture()/instance deliberately: connectedCallback() renders
 * #blueimp-gallery as a page-wide singleton (`if (document.body.querySelector('#blueimp-gallery')
 * == null)`), so whichever instance happens to create it first "owns" its rendered .download
 * link's binding for the rest of the run - a second, separately-created instance stubbing its OWN
 * getInstanceManager() would not be the one the already-rendered link is bound to.
 */

// ExposeMixin<B extends Constructor<LitElement>>() only reads `super.styles` and extends the
// class it's given - a bare LitElement subclass satisfies that at runtime; the `as any` cast
// sidesteps the mixin's own generic constraint typing (matching every real call site's approach),
// not the fix under test here. Needs a real custom-element registration (LitElement/
// ReactiveElement refuses `new` on an unregistered class - "Illegal constructor").
class TestExpose extends ExposeMixin(class extends LitElement
{
	static styles = [];
} as any)
{
}

customElements.define("test-expose-download-binding", <CustomElementConstructor><unknown>TestExpose);

describe("ExposeMixin - handleDownload binding", () =>
{
	it("rebinds handleDownload onto the instance, and a real click reaches that same instance's own getInstanceManager().download()", async() =>
	{
		const instance : any = await fixture(`<test-expose-download-binding></test-expose-download-binding>`);

		assert.isTrue(Object.prototype.hasOwnProperty.call(instance, "handleDownload"),
			"handleDownload must be an OWN property of the instance (bound in the constructor) - " +
			"before the fix it was left as an unbound prototype method, the one handler this " +
			"constructor's own re-binding loop forgot");

		const downloadSpy = sinon.spy();
		instance.getInstanceManager = () => ({download : downloadSpy});

		// connectedCallback() (triggered by fixture()'s own insertion above) already rendered the
		// real gallery markup - including the real @click=${this.handleDownload} binding, WITHOUT
		// {host: this} - straight into document.body via `render(this._galleryTemplate(),
		// document.body)`. That's the exact call this test needs to exercise unmodified: the
		// omitted {host: this} is what the fix works around, not something to paper over here.
		const galleryEl = document.body.querySelector("#blueimp-gallery") as any;
		assert.exists(galleryEl, "connectedCallback() must have rendered the real #blueimp-gallery root");
		// handleDownload() only reads gallery.getIndex()/list - populated here directly rather
		// than via a real blueimp.Gallery instance, which needs real slide/image data this test
		// doesn't care about.
		galleryEl.gallery = {
			getIndex : () => 0,
			list : [{download_href : "https://example.com/real-file.png"}],
		};

		const downloadLink = galleryEl.querySelector("a.download") as HTMLAnchorElement;
		assert.exists(downloadLink, "the gallery template must render its own .download link");
		downloadLink.click();

		assert.isTrue(downloadSpy.calledOnceWith("https://example.com/real-file.png"),
			"a real click, going through lit's own event binding exactly as production does, " +
			"must reach the REAL instance's getInstanceManager().download() with the right URL - " +
			"before the fix this threw 'this.getInstanceManager is not a function' instead");
	});
});
