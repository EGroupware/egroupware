import {css} from "lit";

export default css`
	:host {
		display: flex;
		flex-direction: column;
		/* The grid virtualizes against this, so it needs a bounded height.  A history log lives
		   in a tab panel, which does not always give one - see _resolveHeight(). */
		height: 100%;
		min-height: 15em;
		position: relative;
		overflow: hidden;
	}

	:host([auto-height]) {
		height: auto;
		overflow: visible;
	}

	et2-datagrid {
		flex: 1 1 auto;
		min-height: 0;
	}

	/*
	 * Et2DateRange lays From and To out in a nowrap row, which is wider than a drawer this narrow.
	 * Stack them instead - but the interesting part is min-width: 0.  The shared .form-control is
	 * flex-wrap: wrap, so without it the input keeps its min-content width, does not fit beside the
	 * label, and the whole input wraps onto its own line starting at the label's left edge.
	 * Allowing it to shrink keeps it in the control column, lined up with the other filters.
	 */
	/*
	 * The popped-out diff (showDiff()).  The whole point of popping one out is to read all of it,
	 * so it gets far more room than a dialog's content-sized default, and scrolls past that.
	 */
	.historylog__diff::part(panel) {
		width: min(60em, 90vw);
		max-height: 80vh;
	}

	.historylog__diff::part(body) {
		overflow: auto;
	}

	et2-date-range::part(form-control-input) {
		display: flex;
		flex-direction: column;
		flex-wrap: nowrap;
		flex: 1 1 0;
		min-width: 0;
		gap: var(--sl-spacing-2x-small);
	}
`;
