import type {Et2LayoutStrategy} from "./Et2LayoutController";

const growRowRaf = new WeakMap<HTMLElement, number>();
const growRowObservers = new WeakMap<HTMLElement, ResizeObserver>();

function scheduleGrowRowSizing(host: HTMLElement, children: HTMLElement[]): void
{
	const previous = growRowRaf.get(host);
	if(typeof previous === "number")
	{
		cancelAnimationFrame(previous);
	}

	const raf = requestAnimationFrame(() =>
	{
		growRowRaf.delete(host);

		const base = host.shadowRoot?.querySelector<HTMLElement>('[part="base"]');
		if(!base)
		{
			return;
		}

		const visibleChildren = children.filter(child => child.getClientRects().length > 0);
		const growChild = visibleChildren.find(child => child.matches("[grow], et2-tabbox"));

		// No grow-capable child -> reset any previously forced grow row
		if(!growChild)
		{
			base.style.removeProperty("grid-template-rows");
			base.style.removeProperty("align-content");
			return;
		}

		const rowTops: number[] = [];
		for(const child of visibleChildren)
		{
			const top = Math.round(child.getBoundingClientRect().top);
			if(!rowTops.some(existing => Math.abs(existing - top) <= 1))
			{
				rowTops.push(top);
			}
		}
		rowTops.sort((a, b) => a - b);

		const growTop = Math.round(growChild.getBoundingClientRect().top);
		let growRow = rowTops.findIndex(top => Math.abs(top - growTop) <= 1) + 1;
		if(growRow <= 0)
		{
			growRow = 1;
		}

		const growStyle = getComputedStyle(growChild);
		const minHeight = growStyle.minHeight;
		const maxHeight = growStyle.maxHeight;
		const minTrack = minHeight && minHeight !== "auto" ? minHeight : "min-content";
		const growTrack = maxHeight && maxHeight !== "none"
			? `minmax(${minTrack}, ${maxHeight})`
			: `minmax(${minTrack}, 1fr)`;

		const before = growRow > 1 ? `${"min-content ".repeat(growRow - 1)}` : "";
		base.style.gridTemplateRows = `${before}${growTrack}`;
		base.style.alignContent = "stretch";
	});

	growRowRaf.set(host, raf);
}

function ensureGrowRowObserver(host: HTMLElement): void
{
	if(growRowObservers.has(host))
	{
		return;
	}

	const observer = new ResizeObserver(() =>
	{
		const currentChildren = Array.from(host.children) as HTMLElement[];
		scheduleGrowRowSizing(host, currentChildren);
	});
	observer.observe(host);
	growRowObservers.set(host, observer);
}

function cleanupGrowRowObserver(host: HTMLElement): void
{
	const observer = growRowObservers.get(host);
	if(observer)
	{
		observer.disconnect();
		growRowObservers.delete(host);
	}

	const pendingRaf = growRowRaf.get(host);
	if(typeof pendingRaf === "number")
	{
		cancelAnimationFrame(pendingRaf);
		growRowRaf.delete(host);
	}

	const base = host.shadowRoot?.querySelector<HTMLElement>('[part="base"]');
	base?.style.removeProperty("grid-template-rows");
	base?.style.removeProperty("align-content");
}

/**
 * Stack layout: vertical flex.
 * - Most children: normal height
 * - "grow" children: take remaining height
 * - <et2-tabbox>: always grows
 */
export const stackLayoutStrategy : Et2LayoutStrategy = {
	apply(host, children)
	{
	},
};

/**
 * 2-column layout
 * - CSS handles the grid
 * - JS ensures <et2-tabbox> takes full width + grows
 */
export const twoColumnLayoutStrategy : Et2LayoutStrategy = {
	apply(host, children)
	{
		// Automatically apply fixed label to children
		if(host.classList.contains("et2-label-fixed"))
		{
			children.forEach((child) =>
			{
				child.classList.add("et2-label-fixed");
			});
		}

		ensureGrowRowObserver(host);
		scheduleGrowRowSizing(host, children);
	},

	cleanup(host)
	{
		cleanupGrowRowObserver(host);
	}
};

export const editLayoutStrategy : Et2LayoutStrategy = {
	apply(host, children)
	{
		twoColumnLayoutStrategy.apply(host, children);
	},
};

/**
 * Absolute layout: stub
 * - JS does nothing; CSS may define positioning
 */
export const absoluteLayoutStrategy : Et2LayoutStrategy = {
	apply()
	{
	},
};


export type Et2LayoutName = 'stack' | '2-column' | "edit" | 'absolute';

/**
 * Expose the CSS so the host can consume it.
 *
 *
 * Currently CSS is in kdots less instead of widget
export const LAYOUT_CSS : Record<string, any> = {
	stack: layoutStackStyle,
	"2-column": layout2ColumnStyle,
	edit: layoutEditStyle,
	absolute: layoutAbsoluteStyle,
};
 */


/**
 * Map layout names to strategies
 */
export const LAYOUT_STRATEGIES : Record<string, Et2LayoutStrategy> = {
	stack: stackLayoutStrategy,
	"2-column": twoColumnLayoutStrategy,
	edit: editLayoutStrategy,
	absolute: absoluteLayoutStrategy,
};

/**
 * Returns the strategy for a given layout
 */
export function getLayoutStrategy(name : string) : Et2LayoutStrategy | undefined
{
	return LAYOUT_STRATEGIES[name];
}
