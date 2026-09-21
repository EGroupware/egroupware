import {ReactiveController, ReactiveControllerHost} from "lit";
import type {SlPopup} from "@shoelace-style/shoelace";

/**
 * The Popover API, which the DOM typings shipped with our TypeScript do not know about yet.
 * Declared narrowly here rather than widening the project's `lib`, which would need a compiler
 * upgrade to go with it.
 */
interface PopoverElement extends HTMLElement
{
	showPopover() : void;

	hidePopover() : void;
}

/**
 * Keeps hoisted Shoelace popups (select dropdowns, tooltips, date pickers, ...) anchored to
 * their widget when they open inside a CSS container query container.
 *
 * A layout that can collapse its own columns has to know how wide it is, so it declares itself
 * a query container with `container-type: inline-size`.  That is where the trouble starts.  By
 * the letter of the spec a query container applies layout containment, which makes the element
 * a containing block for `position: fixed` descendants - but no browser actually does that
 * part.  Measured on Chrome 152 and Firefox 142, a fixed descendant of a query container still
 * resolves against the viewport, while `contain: layout` and `transform` on the same element
 * both do capture it.  Floating UI, which places every Shoelace popup, believes the spec: it
 * takes the first `container-type` ancestor as the containing block and hands the popup
 * coordinates measured from that element's scrolled padding box.  The browser then reads the very same numbers as viewport coordinates, so the
 * popup lands `container.scrollTop - container.top` away from the field it belongs to - already
 * wrong when nothing is scrolled, and drifting further with every pixel scrolled.  Floating UI
 * also treats the container as a clipping ancestor, squeezing a dropdown in a short tab panel
 * down to a few rows.
 *
 * Promoting the popup into the top layer settles the argument instead of taking a side:
 * Floating UI short-circuits its containing block search for a top layer element and measures
 * from the viewport, which is exactly where the browser paints it.  Clipping ancestors drop out
 * at the same time, so a dropdown may again overflow the panel it lives in.  Crucially the
 * container itself is left alone - forcing real containment on it (`contain: layout`) would fix
 * these popups by breaking everything else that uses `position: fixed` to escape a scrolling
 * panel, the rich text editor's menus among them.
 *
 * Only popups that asked to escape are touched: `hoist` gives a popup `position: fixed`, and a
 * popup without it is deliberately placed against an offset parent inside the container and has
 * to stay there.  A popup with no query container above it is left alone too, since it is
 * already placed correctly and clipped the way its widget intended.
 */
export class Et2TopLayerPopupController implements ReactiveController
{
	/** Panels this controller put into the top layer, so it can take them back out again */
	private promoted = new Set<HTMLElement>();

	/**
	 * Widgets whose popup is currently closed.
	 *
	 * sl-popup's own `active` cannot answer this: sl-select sets it once and leaves it set for
	 * the life of the widget, hiding the list by other means, so a popup that has been opened
	 * once looks permanently open.  Worse, the autoUpdate that `active` starts also keeps
	 * running, and goes on emitting sl-reposition on every window resize long after the dropdown
	 * was dismissed - which without this set would quietly re-promote a closed popup each time.
	 * A popup driven directly rather than by a widget (Et2Email, the markdown toolbar) emits no
	 * show/hide at all and so is never in here, which is the right answer for it.
	 */
	private closed = new WeakSet<EventTarget>();

	constructor(private host : ReactiveControllerHost & HTMLElement)
	{
		host.addController(this);
	}

	hostConnected()
	{
		// All of these are composed, so they cross the shadow boundaries between a popup deep
		// inside a widget and this host.  It takes all four because no one of them covers every
		// case: sl-select emits sl-reposition only the first time its dropdown is positioned and
		// nothing but sl-show on every open after that, while a popup driven directly rather than
		// by a widget (Et2Email, the markdown toolbar) emits sl-reposition and no show/hide at
		// all.  On the way out, et2-select emits sl-hide and never reaches sl-after-hide, while a
		// widget that animates its popup out only settles at sl-after-hide.
		this.host.addEventListener("sl-reposition", this.handlePosition);
		this.host.addEventListener("sl-show", this.handleShow);
		this.host.addEventListener("sl-after-show", this.handleShow);
		this.host.addEventListener("sl-hide", this.handleHide);
		this.host.addEventListener("sl-after-hide", this.handleHide);
	}

	hostDisconnected()
	{
		this.host.removeEventListener("sl-reposition", this.handlePosition);
		this.host.removeEventListener("sl-show", this.handleShow);
		this.host.removeEventListener("sl-after-show", this.handleShow);
		this.host.removeEventListener("sl-hide", this.handleHide);
		this.host.removeEventListener("sl-after-hide", this.handleHide);
		this.promoted.forEach(panel => this.demote(panel));
	}

	private handlePosition = (event : Event) =>
	{
		const popup = <SlPopup>event.composedPath().find(
			target => target instanceof HTMLElement && target.localName === "sl-popup"
		);
		if(popup)
		{
			this.promote(popup);
		}
	};

	/**
	 * Both show events run this, and the work is done at once rather than on the next frame.
	 * Deferring would be the tidier way to wait for the popup to render, but a frame never comes
	 * in a background tab, and the dropdown would be visible in the wrong place until it did.
	 * Nothing is lost by being early: the very first open has not set the popup's `active` yet
	 * and is picked up by the sl-reposition that follows it, and every open after that finds
	 * `active` still set from the first, because sl-select never clears it.
	 */
	private handleShow = (event : Event) =>
	{
		const widget = event.composedPath()[0];
		this.closed.delete(widget);

		// The popup is a child of the widget that emitted this, not an ancestor, so it is not in
		// the event's path and has to be looked up inside the widget instead.
		popupsInside(widget).forEach(popup => this.promote(popup));
	};

	/**
	 * A popup that closed is not in the path of the event announcing it - it is a child of the
	 * widget that emitted it, not an ancestor - so the panels to take back out are found by
	 * asking which of ours sit inside the widget that just closed.
	 */
	private handleHide = (event : Event) =>
	{
		const widget = event.composedPath()[0];
		this.closed.add(widget);

		this.promoted.forEach(panel =>
		{
			if(!panel.isConnected || contains(widget, panel))
			{
				this.demote(panel);
			}
		});
	};

	private promote(popup : SlPopup)
	{
		const panel = popup.shadowRoot?.querySelector<HTMLElement>(".popup");

		// Layout hosts nest - an et2-customfields inside a tab inside an edit dialog is three of
		// them - and the composed event reaches every one on its way out.  A panel already in the
		// top layer therefore belongs to whichever controller got there first: showing it again
		// is a harmless no-op, but it would leave two controllers each believing they have to put
		// it back, and repeat the reposition for nothing.
		if(!panel || panel.matches(":popover-open") || !popup.active || popup.strategy !== "fixed" ||
			this.isClosed(popup) || !hasQueryContainerAncestor(popup))
		{
			return;
		}

		neutralizePopoverDefaults(popup);
		panel.setAttribute("popover", "manual");
		try
		{
			(<PopoverElement>panel).showPopover();
		}
		catch(e)
		{
			// Nothing to fall back to - leave the popup where Shoelace put it rather than half
			// promoted, and let the misplacement be the visible symptom.
			panel.removeAttribute("popover");
			return;
		}
		this.promoted.add(panel);

		// Shoelace measured these coordinates against the container a moment ago.  Now that the
		// panel is in the top layer the same measurement has to be taken against the viewport.
		popup.reposition();
	}

	private isClosed(popup : SlPopup) : boolean
	{
		for(const ancestor of composedAncestors(popup))
		{
			if(this.closed.has(ancestor))
			{
				return true;
			}
		}
		return false;
	}

	private demote(panel : HTMLElement)
	{
		this.promoted.delete(panel);
		if(panel.matches(":popover-open"))
		{
			(<PopoverElement>panel).hidePopover();
		}
		panel.removeAttribute("popover");
	}
}

/**
 * The sl-popups a widget renders, in its shadow root or its light DOM - sl-select keeps its
 * dropdown in the shadow root, but nothing says a widget has to.
 */
function popupsInside(widget : EventTarget) : SlPopup[]
{
	if(!(widget instanceof HTMLElement))
	{
		return [];
	}
	return [
		...(widget.shadowRoot ? Array.from(widget.shadowRoot.querySelectorAll<SlPopup>("sl-popup")) : []),
		...Array.from(widget.querySelectorAll<SlPopup>("sl-popup"))
	];
}

/**
 * The node itself and everything above it up to (but not including) the document body, stepping
 * out of each shadow root through its host.
 *
 * Needed because a popup sits several shadow roots below the template or customfields widget
 * that declares the container, and neither parentElement nor contains() crosses those.
 */
function* composedAncestors(node : Node) : Generator<Node>
{
	let current : Node = node;
	while(current && current !== document.body)
	{
		yield current;
		current = current.parentNode instanceof ShadowRoot ? current.parentNode.host : current.parentNode;
	}
}

/** Is descendant inside ancestor, counting shadow boundaries as ordinary parentage? */
function contains(ancestor : EventTarget, descendant : Node) : boolean
{
	for(const node of composedAncestors(descendant))
	{
		if(node === ancestor)
		{
			return true;
		}
	}
	return false;
}

/**
 * Is any ancestor of this popup a container query container?
 */
function hasQueryContainerAncestor(popup : HTMLElement) : boolean
{
	for(const node of composedAncestors(popup))
	{
		if(node instanceof HTMLElement)
		{
			// An element that is not in the document has no computed style to speak of and
			// answers with an empty string, which is not "normal" but is not a container either.
			const containerType = getComputedStyle(node).containerType;
			if(containerType && containerType !== "normal")
			{
				return true;
			}
		}
	}
	return false;
}

const POPOVER_RESET_ID = "et2-top-layer-reset";

/**
 * The UA stylesheet decorates every `[popover]` with a border, padding and an opaque background,
 * and pins it with `inset: 0; margin: auto` - which would park the popup in the middle of the
 * screen.  Shoelace writes `left` / `top` inline so those two win on their own, but `right`,
 * `bottom`, `margin` and the paint properties have to be turned off by hand.  The rule goes in
 * the popup's own shadow root, where it outranks the UA styles while staying invisible to
 * everything else on the page - and still loses to a widget styling ::part(popup) from outside,
 * which is what a popup that draws its own panel (et2-tree-dropdown) depends on.
 */
function neutralizePopoverDefaults(popup : SlPopup)
{
	const root = popup.shadowRoot;
	if(!root || root.getElementById(POPOVER_RESET_ID))
	{
		return;
	}

	const style = document.createElement("style");
	style.id = POPOVER_RESET_ID;
	style.textContent = `
		.popup:popover-open {
			inset: auto;
			margin: 0;
			border: 0;
			padding: 0;
			background: transparent;
			color: inherit;
			overflow: visible;
			width: auto;
			height: auto;
		}
	`;
	root.appendChild(style);
}
