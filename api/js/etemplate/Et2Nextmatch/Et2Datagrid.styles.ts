import {css} from "lit";

export default css`
	:host {
		display: block;
		height: 100%;
		min-height: 0;
	}

	.dg-root {
		display: flex;
		flex-direction: column;
		height: 100%;
		min-height: 0;
		border: none;
		overflow: hidden;
		--column-sizes: '';
		--column-count: 1;
		--scrollbar-space: 15px;
		--row-height: 3em;
	}

	.dg-header {
		position: relative;
		display: grid;
		grid-template-columns: var(--column-sizes);
		background: var(--sl-color-neutral-100);
		border-bottom: var(--sl-panel-border-width) solid var(--sl-color-neutral-400);
		align-items: stretch;
		min-height: var(--sl-spacing-x-large);
		flex: 0 0 min-content;
		padding-right: var(--scrollbar-space);
	}

	.dg-col {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
		padding: var(--sl-spacing-2x-small) var(--sl-spacing-small);
		border-right: var(--sl-panel-border-width) solid var(--sl-color-neutral-400);
		box-sizing: border-box;
	}
	.dg-col:has(+ .dg-colselection) {
		border-right: none;
	}
	.dg-colselection {
		position: absolute;
		right: 0px;
		width: 18px;
		padding:0;
		justify-items: center;
	}

	.dg-body {
		flex: 1 1 auto;
		overflow: auto;
		min-height: 0;
		position: relative;
		scrollbar-gutter: stable;
	}

	table {
		width: 100%;
		box-sizing: border-box;
		display: grid;
		grid-template-columns: var(--column-sizes, repeat( var(--column-count),1fr));
		tbody {
			display: grid;
			grid-template-columns: var(--column-sizes, repeat(var(--column-count), 1fr));
			grid-column: 1 / -1;
			row-gap: var(--sl-spacing-2x-small);
		}
		tr {
			display: grid;
			grid-template-columns: var(--column-sizes, repeat(var(--column-count), 1fr));
			grid-column: 1/-1;
		}
	}
	thead {
		position: absolute;
		clip-path: inset(50%);
		height: 1px;
		width: 1px;
		margin: -1px;
		overflow: hidden;
		padding: 0;
		border: 0;
		white-space: nowrap;
	}
	tbody > tr {
		display: grid;
		grid-template-columns: var(--column-sizes, repeat(var(--column-count), 1fr));
		padding-bottom: var(--sl-spacing-2x-small);
		outline: none;
		width: 100%;
		min-height: 3em;
		border-bottom: var(--sl-panel-border-width) solid var(--sl-color-neutral-200);
	}

	tbody > tr[aria-selected="true"] {
		background: var(--sl-color-primary-50, #eef5ff);
	}

	tbody > tr.dg-row-active {
		box-shadow: inset 0 0 0 2px var(--sl-color-primary-600, #2869db);
	}

	tbody td,
	tbody th {
		box-sizing: border-box;
		padding: 0px var(--sl-spacing-x-small);
		min-width: 0;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.dg-row-placeholder {
		background: transparent;
	}

	.dg-placeholder-cell {
		grid-column: 1 / -1;
		padding: 6px 8px;
		align-content: center;
		--color: var(--sl-color-neutral-50);
		--sheen-color: var(--sl-color-neutral-200);
	}

	.dg-row-spacer {
		padding: 0;
		border: 0;
		height: 0;
		min-height: 0 !important;
		width: 100%;
		grid-column: 1 / -1;
		background-image: repeating-linear-gradient(
				0deg,
				var(--sl-color-neutral-200, rgba(0, 0, 0, 0.08)), var(--sl-color-neutral-200, rgba(0, 0, 0, 0.08)) 1px,
				var(--sl-color-neutral-50) 1px, var(--sl-color-neutral-50) var(--row-height, 3em)
		);
	}

	.skeleton-row {
		display: flex;
		padding: var(--sl-spacing-large);
	}

	.dg-state {
		padding: var(--sl-spacing-large);
	}

	.dg-state sl-alert {
		display: block;
	}

	#sentinel {
		/* Keep a physical target for IntersectionObserver across browsers. */
		display: block;
		width: 100%;
		height: 1px;
	}
`;
