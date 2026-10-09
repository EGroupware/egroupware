import {CSSResult, ReactiveController, ReactiveControllerHost} from "lit";
import {Et2LayoutName, getLayoutStrategy} from "./Et2LayoutStrategies";
import {Et2TopLayerPopupController} from "./Et2TopLayerPopupController";

// Re-exported so a widget adding layout support only has to import from here: it needs
// Et2LayoutHost and Et2LayoutController anyway, and the `layout` property it declares to
// satisfy the interface has to be typed Et2LayoutName.
export type {Et2LayoutName};

/** The size of a layout's content box, in pixels */
export type Et2LayoutSize = { width : number, height : number };

/**
 * Widgets with layout implement Et2LayoutHost
 *
 * 1.  Implement Et2LayoutHost interface
 * 2.  Create an Et2LayoutController
 *
 * private _layout = new Et2LayoutController(this);
 *
 * 3.  Optionally, let the widget be asked how big its layout would like to be (a popup fits itself
 *     to it) by forwarding to the controller:
 *
 * getPreferredSize() : Et2LayoutSize | null { return this._layout.getPreferredSize(); }
 */
export interface Et2LayoutHost extends ReactiveControllerHost, HTMLElement
{
	layout : Et2LayoutName;
}

export interface Et2LayoutStrategy
{
	apply(host : HTMLElement, children : HTMLElement[]) : void;

	cleanup?(host : HTMLElement, children : HTMLElement[]) : void;
}

/**
 * How many columns each layout asks for when something wants to size itself to the layout
 * (see Et2LayoutController.getPreferredSize()).  It is a preference, not a limit: the grid still gives
 * as many columns as fit, and one when it is narrow.
 */
const PREFERRED_COLUMNS : Record<Et2LayoutName, number> = {
	"stack": 1,
	"2-column": 2,
	"edit": 2
};

export class Et2LayoutController implements ReactiveController
{
	static readonly styles : CSSResult[] = [];//Object.values(LAYOUT_CSS);
	private activeStrategy? : Et2LayoutStrategy;

	/*
	 * A layout that collapses its own columns declares itself a container query container, which
	 * misplaces every hoisted popup opened inside it.  The controller is created here rather than
	 * by each host widget because the layout is what introduces the container in the first place.
	 */
	private topLayerPopups : Et2TopLayerPopupController;

	constructor(private host : Et2LayoutHost)
	{
		(host as ReactiveControllerHost).addController(this);
		this.topLayerPopups = new Et2TopLayerPopupController(host);
	}

	hostConnected()
	{
		this.applyLayout();
	}

	hostUpdated()
	{
		this.applyLayout();
	}

	hostDisconnected()
	{
		if(this.activeStrategy?.cleanup)
		{
			const children = Array.from(this.host.children) as HTMLElement[];
			this.activeStrategy.cleanup(this.host, children);
		}
		this.activeStrategy = undefined;
	}

	/**
	 * How big this layout would like to be, for a window sized to its content (a popup).
	 *
	 * The host fills whatever it is given and scrolls inside it, so anything measuring it only ever
	 * gets back the size it already has - which is why a popup holding a layout cannot fit itself
	 * to it the way it fits a template made of a fixed table.  This is the size the layout asks
	 * for instead: the columns it prefers, each as wide as --column-min-width, and every row at its
	 * natural height (a growing row at its own minimum) once the columns are that wide.
	 *
	 * Measured in place, in one go: the host is given the preferred width and no height, read, and
	 * put back before the browser paints.
	 *
	 * @param columns how many columns to ask for, the layout's own preference by default
	 * @return the size of the host's content box, or null if it has no layout to measure (not
	 *         laid out yet, or not rendered)
	 */
	getPreferredSize(columns? : number) : Et2LayoutSize | null
	{
		// A shadow-DOM host exposes its wrapper as part "base"; a light-DOM host has it as a child
		const base = this.host.shadowRoot?.querySelector<HTMLElement>('[part="base"]') ??
			this.host.querySelector<HTMLElement>(':scope > [part~="base"]');
		if(!this.activeStrategy || !base || !this.host.isConnected)
		{
			return null;
		}
		const baseStyle = getComputedStyle(base);
		const isGrid = baseStyle.display.includes("grid");
		columns = Math.max(1, columns ?? (isGrid ? PREFERRED_COLUMNS[this.host.layout] ?? 1 : 1));

		// --column-min-width may be in em or rem, so let the browser turn it into pixels.  The same
		// goes for --collapse-width, the width at or below which the grid is one column whatever
		// its columns would need.
		const probe = document.createElement("div");
		probe.style.cssText = "position:absolute;visibility:hidden;width:var(--column-min-width, 26rem)";
		const collapseProbe = document.createElement("div");
		collapseProbe.style.cssText = "position:absolute;visibility:hidden;width:var(--collapse-width, 600px)";
		base.append(probe, collapseProbe);
		const columnWidth = probe.getBoundingClientRect().width;
		const collapseWidth = collapseProbe.getBoundingClientRect().width;
		probe.remove();
		collapseProbe.remove();

		const gap = parseFloat(baseStyle.columnGap) || 0;
		const hostStyle = getComputedStyle(this.host);
		const padding = (parseFloat(baseStyle.paddingLeft) || 0) + (parseFloat(baseStyle.paddingRight) || 0) +
			(parseFloat(hostStyle.paddingLeft) || 0) + (parseFloat(hostStyle.paddingRight) || 0);

		// Room for a scrollbar too: while the layout is still too tall for its window it has one, and
		// the width that takes is what decides whether the columns fit - so without it they would
		// stay collapsed, stay too tall, and keep the scrollbar
		const overflowBefore = this.host.style.overflowY;
		this.host.style.overflowY = "scroll";
		const scrollbar = this.host.offsetWidth - this.host.clientWidth;
		this.host.style.overflowY = overflowBefore;

		// One pixel over: the grid collapses a column at max-width: <that many columns>, which is
		// inclusive, so exactly enough room for them collapses them again
		let width = Math.ceil(columns * columnWidth + (columns - 1) * gap + padding + scrollbar) + 1;
		if(columns > 1)
		{
			// The collapse is inclusive too, and does not count the scrollbar's width in the space it has
			width = Math.max(width, Math.ceil(collapseWidth + scrollbar) + 1);
		}

		// No height means the content overflows, and a scrollbar would take its width from the
		// columns and collapse them - so nothing may scroll while this is measured.
		// The grid's rows are written inline for the columns it has right now, and are only
		// worked out again a frame after a resize, so at another width they are stale: let every
		// row size itself instead.
		const previous = {
			width: this.host.style.width,
			height: this.host.style.height,
			overflowY: this.host.style.overflowY,
			rows: base.style.gridTemplateRows
		};
		this.host.style.width = width + "px";
		this.host.style.height = "0";
		this.host.style.overflowY = "hidden";
		base.style.gridTemplateRows = "";
		const height = this.host.scrollHeight;
		this.host.style.width = previous.width;
		this.host.style.height = previous.height;
		this.host.style.overflowY = previous.overflowY;
		base.style.gridTemplateRows = previous.rows;

		return {width, height};
	}

	private applyLayout()
	{
		const strategy = getLayoutStrategy(this.host.layout);
		if(!strategy)
		{
			return;
		}

		const children = Array.from(this.host.children) as HTMLElement[];
		if(this.activeStrategy && this.activeStrategy !== strategy && this.activeStrategy.cleanup)
		{
			this.activeStrategy.cleanup(this.host, children);
		}
		strategy.apply(this.host, children);
		this.activeStrategy = strategy;
	}
}
