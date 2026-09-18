import {css} from "lit";

/**
 * Row styles for et2-historylog.  Adopted into the Et2Datagrid shadowRoot, which is where the
 * row cells live - a `::part()` selector cannot reach into them from outside.
 */
export default css`
	/*
	 * A diff is unified-diff text and reads badly in a half-width column, so it takes both value
	 * columns: its own cell spans two grid tracks and the paired old-value cell (which holds only
	 * the server's marker) removes itself from flow.
	 *
	 * Datagrid rows are CSS grid, so this is a real track span - the legacy widget faked it with a
	 * jQuery colspan plus a pixel width read back from the column manager, and deleted the next
	 * <td> outright.
	 */
	td:has(> et2-historylog-value[diff]) {
		grid-column: span 2;
		border-right: none;
		overflow: visible;
	}

	td:has(> et2-historylog-value[diff-row]:not([diff])) {
		display: none;
	}

	/* Values are display-only here; let a long one wrap rather than stretch the column */
	et2-historylog-value {
		min-width: 0;
	}
`;
