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

	.historylog__header {
		display: flex;
		align-items: center;
		justify-content: flex-end;
		gap: var(--sl-spacing-2x-small);
		flex: 0 0 auto;
		min-height: var(--sl-input-height-small);
	}


	et2-datagrid {
		flex: 1 1 auto;
		min-height: 0;
	}

	/* The filter drawer is 'contained', so it slides over the history log rather than the window */
	sl-drawer::part(panel) {
		--size: min(28em, 90%);
	}

	sl-drawer::part(body) {
		padding: var(--sl-spacing-medium);
	}

	/* Same fix egw-app needs: without it the header-action buttons sit off-centre */
	sl-drawer [slot="header-actions"] {
		display: flex;
	}

	/*
	 * One label column for every filter, so they line up instead of each label sizing itself.
	 * The filterbox's own default is a percentage (min(20rem, 30%)), which in a drawer this narrow
	 * leaves the controls ragged - a fixed em width is what makes it read like egw-app's.
	 */
	et2-filterbox {
		--label-width: 7em;
		--gap-width: var(--sl-spacing-small);
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
