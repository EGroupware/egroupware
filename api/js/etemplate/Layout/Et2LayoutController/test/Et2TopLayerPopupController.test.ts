/**
 * Tests for Et2TopLayerPopupController
 *
 * The controller's job is to decide which Shoelace popups have to be moved into the top layer to
 * survive a container query container above them, so that is what these tests drive: a host
 * carrying the controller, a popup underneath it, and the sl-reposition event Shoelace emits
 * every time a popup places itself.
 */
import {assert, elementUpdated, fixture, html} from '@open-wc/testing';
import {LitElement} from "lit";
import "@shoelace-style/shoelace/dist/components/popup/popup.js";
import {Et2TopLayerPopupController} from "../Et2TopLayerPopupController";

/**
 * Stands in for et2-template / et2-customfields: a layout host that renders into its light DOM
 * and creates the controller, which is all the controller needs of it.
 */
class TopLayerTestHost extends LitElement
{
	public controller = new Et2TopLayerPopupController(this);

	createRenderRoot() { return this; }
}

if(!customElements.get("et2-toplayer-test-host"))
{
	customElements.define("et2-toplayer-test-host", TopLayerTestHost);
}

describe("Top layer popup controller", () =>
{
	let element : TopLayerTestHost;

	/**
	 * sl-popup only emits sl-reposition from its own reposition(), which needs a live anchor and
	 * a layout pass.  The controller reacts to the event whoever sent it, so it is raised
	 * directly here instead.
	 */
	function reposition(popup : HTMLElement)
	{
		popup.dispatchEvent(new CustomEvent("sl-reposition", {bubbles: true, composed: true}));
	}

	function panelOf(popup : HTMLElement) : HTMLElement
	{
		return popup.shadowRoot.querySelector(".popup");
	}

	/** The sweep for closed popups is keyed on the widget that emitted the event */
	function hide(popup : HTMLElement, event : string)
	{
		popup.parentElement.dispatchEvent(new CustomEvent(event, {bubbles: true, composed: true}));
	}

	async function build(isQueryContainer : boolean, strategy : string) : Promise<HTMLElement>
	{
		element = await fixture<TopLayerTestHost>(html`
            <et2-toplayer-test-host style="display:block">
                <span id="anchor"></span>
                <div class="widget">
                    <sl-popup anchor="anchor">
                        <div>content</div>
                    </sl-popup>
                </div>
            </et2-toplayer-test-host>
		`);
		if(isQueryContainer)
		{
			element.style.containerType = "inline-size";
		}

		const popup = element.querySelector<HTMLElement>("sl-popup");
		popup["strategy"] = strategy;
		popup["active"] = true;
		await elementUpdated(popup);
		return popup;
	}

	it("promotes a hoisted popup inside a query container", async() =>
	{
		const popup = await build(true, "fixed");

		reposition(popup);

		assert.isTrue(panelOf(popup).matches(":popover-open"),
			"a fixed popup under a query container has to reach the top layer, or it gets placed against the container instead of the viewport");
	});

	it("leaves a popup alone when no query container is above it", async() =>
	{
		const popup = await build(false, "fixed");

		reposition(popup);

		assert.isFalse(panelOf(popup).matches(":popover-open"),
			"without a query container the popup is already placed correctly - promoting it would only change how it clips");
	});

	it("leaves a non-hoisted popup alone", async() =>
	{
		const popup = await build(true, "absolute");

		reposition(popup);

		assert.isFalse(panelOf(popup).matches(":popover-open"),
			"an absolutely positioned popup is deliberately placed against an offset parent inside the container");
	});

	/*
	 * Floating UI's arithmetic against the wrong containing block is self-cancelling while the
	 * container scrolls: y = anchorTop + scrollTop - hostTop, so scrolling by d drops the anchor
	 * by d and raises scrollTop by d and y never changes.  It repositions on every scroll and
	 * faithfully computes the same wrong answer, so an open dropdown is left parked while its
	 * field slides away.  Measuring at rest would not catch that - only scrolling does.
	 */
	it("keeps an open popup on its anchor while the container scrolls", async() =>
	{
		const scroller = await fixture<TopLayerTestHost>(html`
            <et2-toplayer-test-host
                    style="display:block; height:200px; width:400px; overflow:auto; container-type:inline-size; position:relative;">
                <div style="height:300px"></div>
                <span id="scroll-anchor" style="display:block; height:20px"></span>
                <div class="widget">
                    <sl-popup anchor="scroll-anchor" placement="bottom">
                        <div style="width:120px; height:60px"></div>
                    </sl-popup>
                </div>
                <div style="height:300px"></div>
            </et2-toplayer-test-host>
		`);
		const popup = scroller.querySelector<HTMLElement>("sl-popup");
		const anchor = scroller.querySelector<HTMLElement>("#scroll-anchor");
		popup["strategy"] = "fixed";
		popup["active"] = true;
		await elementUpdated(popup);
		reposition(popup);
		assert.isTrue(panelOf(popup).matches(":popover-open"), "promoted to start with");

		const drift = () =>
		{
			const a = anchor.getBoundingClientRect(), p = panelOf(popup).getBoundingClientRect();
			return Math.round(p.top - a.bottom);
		};

		for(const scrollTop of [0, 100, 250, 60])
		{
			scroller.scrollTop = scrollTop;
			await new Promise(resolve => setTimeout(resolve, 150));
			assert.closeTo(drift(), 0, 2,
				`dropdown came off its field after scrolling the container to ${scrollTop}`);
		}
	});

	/*
	 * The reset that cancels the UA [popover] chrome lives in the popup's own shadow root, so it
	 * must stay beatable from outside: a theme (kdots glassy sets a border and a 1rem radius) and
	 * a widget that draws its own panel (et2-tree-dropdown) both style ::part(popup) from the
	 * outer tree, and an outer ::part rule wins over the shadow tree's own regardless of
	 * specificity.  If that ever stopped holding, promoted dropdowns would silently lose their
	 * border, radius and background.
	 */
	it("lets an outer ::part(popup) rule keep its chrome in the top layer", async() =>
	{
		const themed = await fixture<TopLayerTestHost>(html`
            <et2-toplayer-test-host style="display:block; container-type: inline-size;">
                <style>
                    sl-popup::part(popup) {
                        border: 2px solid rgb(10, 20, 30);
                        border-radius: 16px;
                        background: rgb(1, 2, 3);
                    }
                </style>
                <span id="themed-anchor"></span>
                <div class="widget">
                    <sl-popup anchor="themed-anchor">
                        <div>content</div>
                    </sl-popup>
                </div>
            </et2-toplayer-test-host>
		`);
		const popup = themed.querySelector<HTMLElement>("sl-popup");
		popup["strategy"] = "fixed";
		popup["active"] = true;
		await elementUpdated(popup);

		reposition(popup);
		assert.isTrue(panelOf(popup).matches(":popover-open"), "promoted, so the reset is in play");

		const style = getComputedStyle(panelOf(popup));
		assert.equal(style.borderTopWidth, "2px", "the theme's border must survive the reset");
		assert.equal(style.borderTopColor, "rgb(10, 20, 30)", "and keep its colour");
		assert.equal(style.borderTopLeftRadius, "16px", "and its radius");
		assert.equal(style.backgroundColor, "rgb(1, 2, 3)", "and its background, or the panel goes transparent");
	});

	it("survives nested layout hosts seeing the same popup", async() =>
	{
		// An et2-customfields inside a tab inside an edit dialog is three layout hosts deep, and
		// the composed event reaches every one of them.
		const outer = await fixture<TopLayerTestHost>(html`
            <et2-toplayer-test-host style="display:block; container-type: inline-size;">
                <et2-toplayer-test-host style="display:block; container-type: inline-size;">
                    <span id="nested-anchor"></span>
                    <div class="widget">
                        <sl-popup anchor="nested-anchor">
                            <div>content</div>
                        </sl-popup>
                    </div>
                </et2-toplayer-test-host>
            </et2-toplayer-test-host>
		`);
		const popup = outer.querySelector<HTMLElement>("sl-popup");
		popup["strategy"] = "fixed";
		popup["active"] = true;
		await elementUpdated(popup);

		reposition(popup);

		assert.isTrue(panelOf(popup).matches(":popover-open"),
			"the second controller to see the event must not undo what the first one did");
	});

	/*
	 * et2-select emits sl-hide and never gets as far as sl-after-hide, so this is the sequence
	 * that has to work, not just the tidier sl-after-hide one.
	 */
	["sl-hide", "sl-after-hide"].forEach(event =>
	{
		it(`takes the popup back out of the top layer on ${event}`, async() =>
		{
			const popup = await build(true, "fixed");
			reposition(popup);
			assert.isTrue(panelOf(popup).matches(":popover-open"), "promoted to start with");

			hide(popup, event);

			assert.isFalse(panelOf(popup).matches(":popover-open"),
				"a closed popup left in the top layer would keep painting over everything else");
			assert.isFalse(panelOf(popup).hasAttribute("popover"),
				"the popover attribute has to come off with it");
		});
	});

	/*
	 * sl-select never clears sl-popup's `active`, so its autoUpdate keeps emitting sl-reposition
	 * on every window resize for the rest of the widget's life - long after the dropdown was
	 * dismissed.
	 */
	it("does not put a closed popup back into the top layer when it repositions again", async() =>
	{
		const popup = await build(true, "fixed");
		reposition(popup);
		hide(popup, "sl-hide");

		reposition(popup);

		assert.isFalse(panelOf(popup).matches(":popover-open"),
			"a reposition after closing must not re-promote - `active` is still set, so it is no proof the popup is open");
	});

	/*
	 * sl-select emits sl-reposition only the first time it positions its dropdown; every open
	 * after that announces itself with nothing but sl-show, and the popup is not in that event's
	 * path.  Promotion therefore cannot be driven by sl-reposition alone.
	 */
	it("promotes on sl-show alone, without a reposition", async() =>
	{
		const popup = await build(true, "fixed");
		reposition(popup);
		hide(popup, "sl-hide");
		assert.isFalse(panelOf(popup).matches(":popover-open"), "closed, so nothing carries over");

		popup.parentElement.dispatchEvent(new CustomEvent("sl-show", {bubbles: true, composed: true}));

		assert.isTrue(panelOf(popup).matches(":popover-open"),
			"every open after the first fires only sl-show - waiting for a reposition would leave the dropdown misplaced");
	});
});
