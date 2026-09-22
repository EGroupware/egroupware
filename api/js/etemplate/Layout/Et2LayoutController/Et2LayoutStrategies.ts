import type {Et2LayoutStrategy} from "./Et2LayoutController";

// Scheduled animation frames
const growRowRaf = new WeakMap<HTMLElement, number>();
// Per-host observers: what the layout watches to know it has to look again
const growRowObservers = new WeakMap<HTMLElement, { resize : ResizeObserver, children : MutationObserver }>();
// Automatic grow tags (grow attribute not needed)
const GROW_TAG_SELECTOR = "et2-tabbox";
const GROW_SELECTOR = `[grow], ${GROW_TAG_SELECTOR}`;
// Children that stretch from wherever the grid put them to the end of their line
const SPAN_END_SELECTOR = `[span="end"], [span="*"]`;

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

/**
 * Which column of the grid a child was placed in, as a 1-based grid line number.
 *
 * Auto-placement is done by the browser and the result is not exposed anywhere: the computed
 * grid-column-start of an auto-placed item is still "auto".  So we reconstruct it from the used
 * track sizes (getComputedStyle resolves grid-template-columns to pixels) and how far the child
 * sits from the grid's inline-start content edge.  Measuring from the *inline*-start keeps this
 * working in RTL, where track 1 is the rightmost one.
 *
 * @param base the grid container
 * @param child one of its grid items
 * @return 1-based column line, 1 if it cannot be worked out
 */
function gridColumnOf(base : HTMLElement, child : HTMLElement) : number
{
	const style = getComputedStyle(base);
	const tracks = style.gridTemplateColumns.split(" ").map(parseFloat).filter(size => !isNaN(size));
	if(tracks.length < 2)
	{
		return 1;
	}

	const gap = parseFloat(style.columnGap) || 0;
	const baseRect = base.getBoundingClientRect();
	const childRect = child.getBoundingClientRect();
	const offset = style.direction === "rtl"
				   ? baseRect.right - parseFloat(style.borderRightWidth) - parseFloat(style.paddingRight) - childRect.right
				   : childRect.left - baseRect.left - parseFloat(style.borderLeftWidth) - parseFloat(style.paddingLeft);

	let trackStart = 0;
	for(let column = 0; column < tracks.length; column++)
	{
		// 1px of slack: sub-pixel track sizes do not add up to the item's rounded position
		if(offset <= trackStart + 1)
		{
			return column + 1;
		}
		trackStart += tracks[column] + gap;
	}
	return tracks.length;
}

/**
 * Give every labelled field in a layout the same label width.
 *
 * A layout means a form, and a form wants its labels in a column so the inputs start at the same
 * place - so this is the default rather than something each field has to ask for.
 *
 * The container's `class` is the whole statement about labels: say nothing and you get them, write
 * any class at all - `class=""` will do - and you have taken them back, and name `et2-label-fixed`
 * among your own classes to have them anyway.  Taking them back is what a form whose labels are
 * long sentences wants, because `--label-width` then becomes a narrow column for the label to wrap
 * inside and the row grows taller than lining it up is worth.
 *
 * The class still has to land on each widget, because it sizes that widget's own label inside its
 * own shadow root and nothing can reach across all of them at once.  Widgets carrying no label at
 * all are skipped: a form widget with nothing to show reports `label` as an empty string.  The
 * non-form widgets that fall through (a box, a button) have no label part of their own for it to
 * size, and for a box it is what passes the width on to the fields inside it.
 *
 * @param host the layout host
 * @param children its direct children
 */
function applyFixedLabels(host : HTMLElement, children : HTMLElement[]) : void
{
	if(host.hasAttribute("class") && !host.classList.contains("et2-label-fixed"))
	{
		return;
	}
	children.forEach((child) =>
	{
		if(child.hasAttribute("label") || child["label"] != "")
		{
			child.classList.add("et2-label-fixed");
		}
	});
}

/**
 * Turn each grow child's factor into a real flex-grow, for the flex layouts.
 *
 * `stack` is a flex column, so the browser distributes the leftover height itself and none of the
 * measuring the grid layouts need applies here - the CSS just cannot read `grow="2"` out of the
 * attribute, it can only say `flex: 1 1 auto` and give every growing child an equal share.
 *
 * The basis has to go with the factor.  `flex: 1 1 auto` leaves each child's own height as its
 * basis, and a widget whose natural height already fills the container is then being *shrunk*
 * rather than grown - shrinking goes by basis, not by grow, so two children come out exactly the
 * same size however different their factors are.  `flex-basis: 0` hands the whole distribution to
 * the factors, which is the only reading of `grow="2"` that means anything.  With a single growing
 * child it makes no difference either way.
 *
 * @param children the host's direct children
 */
function applyFlexGrow(children : HTMLElement[]) : void
{
	for(const child of children)
	{
		if(!child.matches(GROW_SELECTOR))
		{
			continue;
		}
		child.style.flexGrow = String(getGrowFactor(child));
		child.style.flexBasis = "0";
	}
}

/**
 * Stretch span="end" / span="*" children from their own column to the end of the line.
 *
 * CSS cannot say this on its own.  `grid-column-end: -1` on an item whose start is auto is a span
 * of *one* ending at the last line, so the item jumps into the last column and leaves a hole
 * beside it instead of filling the rest of its line - which is why the column has to be measured
 * and written back as an explicit `<column> / -1`.
 *
 * Children are handled in document order and measured one at a time, on purpose: widening one can
 * push the ones after it onto a different line, so a single batch of measurements taken up front
 * would be stale by the time it was applied.
 *
 * @param base the grid container
 * @param children the host's direct children
 */
function applySpanToEnd(base : HTMLElement, children : HTMLElement[]) : void
{
	const spanEnd = children.filter(child => child.matches(SPAN_END_SELECTOR));

	// Let them auto-place again from scratch - last time's column is not necessarily this time's
	for(const child of spanEnd)
	{
		child.style.removeProperty("grid-column");
	}
	if(spanEnd.length === 0 || !getComputedStyle(base).display.includes("grid"))
	{
		return;
	}

	for(const child of spanEnd)
	{
		if(child.getClientRects().length === 0)
		{
			continue;
		}
		child.style.gridColumn = `${gridColumnOf(base, child)} / -1`;
	}
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

		// A flex base is `stack`, and flex distributes the leftover height on its own: all it
		// needs is the grow factor.  Everything below writes grid properties that a flex
		// container ignores, so there is nothing else to do here.
		if(getComputedStyle(base).display.includes("flex"))
		{
			applyFlexGrow(children);
			return;
		}

		// Before measuring rows: a child stretched to the end of its line can push the ones after
		// it onto another line, so the row tops below have to be read after this has settled
		applySpanToEnd(base, children);

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
 * Watch the host for the two things that make the layout's answer stale.
 *
 * A resize is the obvious one.  The children are the one that is easy to miss: the strategy is
 * applied when the host connects and on each of its updates, but eTemplate builds a dialog by
 * creating the widgets and appending them afterwards, so the children the strategy first saw are
 * usually not the real ones.  A resize does follow most of the time - widgets arriving make the
 * host taller - but "most of the time" is not something a form's labels can hang on, so the
 * arrival is watched for directly.
 *
 * Adding classes and inline styles to the children is an attribute change, not a childList one, so
 * doing the work cannot re-trigger the observer.
 *
 * @param {HTMLElement} host
 */
function ensureGrowRowObserver(host: HTMLElement): void
{
	if(growRowObservers.has(host))
	{
		return;
	}

	const lookAgain = () =>
	{
		const currentChildren = Array.from(host.children) as HTMLElement[];
		applyFixedLabels(host, currentChildren);
		scheduleGrowRowSizing(host, currentChildren);
	};

	const resize = new ResizeObserver(lookAgain);
	resize.observe(host);
	const children = new MutationObserver(lookAgain);
	children.observe(host, {childList: true});
	growRowObservers.set(host, {resize, children});
}

function cleanupGrowRowObserver(host: HTMLElement): void
{
	const observers = growRowObservers.get(host);
	if(observers)
	{
		observers.resize.disconnect();
		observers.children.disconnect();
		growRowObservers.delete(host);
	}

	const pendingRaf = growRowRaf.get(host);
	if(typeof pendingRaf === "number")
	{
		cancelAnimationFrame(pendingRaf);
		growRowRaf.delete(host);
	}

	for(const child of Array.from(host.children) as HTMLElement[])
	{
		if(child.matches(SPAN_END_SELECTOR))
		{
			child.style.removeProperty("grid-column");
		}
		if(child.matches(GROW_SELECTOR))
		{
			child.style.removeProperty("flex-grow");
			child.style.removeProperty("flex-basis");
		}
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
		applyFixedLabels(host, children);
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
		applyFixedLabels(host, children);
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

export type Et2LayoutName = 'stack' | '2-column' | "edit";

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
};
 */


/**
 * Map layout names to strategies
 */
export const LAYOUT_STRATEGIES : Record<string, Et2LayoutStrategy> = {
	stack: stackLayoutStrategy,
	"2-column": twoColumnLayoutStrategy,
	edit: editLayoutStrategy,
};

/**
 * Returns the strategy for a given layout
 */
export function getLayoutStrategy(name : string) : Et2LayoutStrategy | undefined
{
	return LAYOUT_STRATEGIES[name];
}
