import type {Et2LayoutStrategy} from "./Et2LayoutController";

// Scheduled animation frames
const growRowRaf = new WeakMap<HTMLElement, number>();
// Resize Observers
const growRowObservers = new WeakMap<HTMLElement, ResizeObserver>();
// Automatic grow tags (grow attribute not needed)
const GROW_TAG_SELECTOR = "et2-tabbox";
const GROW_SELECTOR = `[grow], ${GROW_TAG_SELECTOR}`;

function getGrowFactor(child : HTMLElement) : number
{
	if(child.matches(GROW_TAG_SELECTOR) && !child.hasAttribute("grow"))
	{
		return 1;
	}

	const raw = child.getAttribute("grow");
	// Boolean attribute (or invalid value) defaults to 1fr
	if(raw === null || raw === "")
	{
		return 1;
	}

	const parsed = Number.parseInt(raw, 10);
	return Number.isFinite(parsed) && parsed > 0 ? parsed : 1;
}

// Schedule recalculation at the next animation frame, not right now
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
		const growChildren = visibleChildren.filter(child => child.matches(GROW_SELECTOR));

		// No grow-capable child - reset any previously forced grow row sizing
		if(growChildren.length === 0)
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

		// Find preferred sizing for growing children
		const rows = rowTops.map(() => ({minValues: [] as string[], maxValues: [] as string[], growFactor: 0}));
		for(const growChild of growChildren)
		{
			const growTop = Math.round(growChild.getBoundingClientRect().top);
			const rowIndex = rowTops.findIndex(top => Math.abs(top - growTop) <= 1);
			if(rowIndex < 0)
			{
				continue;
			}

			const growStyle = getComputedStyle(growChild);
			const minHeight = growStyle.minHeight;
			const maxHeight = growStyle.maxHeight;

			if(minHeight && minHeight !== "auto")
			{
				rows[rowIndex].minValues.push(minHeight);
			}
			if(maxHeight && maxHeight !== "none")
			{
				rows[rowIndex].maxValues.push(maxHeight);
			}
			rows[rowIndex].growFactor = Math.max(rows[rowIndex].growFactor, getGrowFactor(growChild));
		}

		// Set updated grid row sizing
		const trackList = rows.map(row =>
		{
			if(row.minValues.length === 0 && row.maxValues.length === 0)
			{
				return "min-content";
			}

			const minTrack = row.minValues.length === 0
							 ? "min-content"
							 : row.minValues.length === 1
							   ? row.minValues[0]
							   : `max(${row.minValues.join(", ")})`;
			const maxTrack = row.maxValues.length === 0
							 ? `${Math.max(1, row.growFactor)}fr`
							 : row.maxValues.length === 1
							   ? row.maxValues[0]
							   : `min(${row.maxValues.join(", ")})`;
			return `minmax(${minTrack}, ${maxTrack})`;
		});

		base.style.gridTemplateRows = trackList.join(" ");
		base.style.alignContent = "stretch";
	});

	growRowRaf.set(host, raf);
}


/**
 * Register a ResizeObserver on the host so we can schedule resizing
 *
 * @param {HTMLElement} host
 */
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
		ensureGrowRowObserver(host);
		scheduleGrowRowSizing(host, children);
	},

	cleanup(host)
	{
		cleanupGrowRowObserver(host);
	}
};

/**
 * 2-column layout
 * - CSS handles the grid
 * - JS ensures <et2-tabbox> & [grow] grows
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

/**
 * Edit dialog layout extends 2-column layout with additional styling
 */
export const editLayoutStrategy : Et2LayoutStrategy = {
	apply(host, children)
	{
		twoColumnLayoutStrategy.apply(host, children);
	},
	cleanup(host)
	{
		cleanupGrowRowObserver(host);
	}
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
 * Currently CSS is in kdots/css/src/layouts less files instead of the webComponent (./layouts/*.styles.ts).
 * This is bad for encapsulation but good for full control
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
