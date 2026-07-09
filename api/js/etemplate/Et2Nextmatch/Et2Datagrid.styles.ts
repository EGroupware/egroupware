import {css} from "lit";

export default css`
	:host {
		display: block;
		height: 100%;
		min-height: 0;
		--row-expander-size: var(--sl-spacing-large);
		--row-expander-icon-size: 0.5em;
	}

	:host([auto-height]) {
		height: auto;
	}

	:host([embedded-virtualized]) {
		height: var(--embedded-virtualized-height, auto);
	}

	.dg-root {
		display: flex;
		flex-direction: column;
		position: relative;
		height: 100%;
		min-height: 0;
		border: none;
		overflow: hidden;
		--column-sizes: '';
		--column-count: 1;
		--scrollbar-space: 0px;
		--column-selection-width: min(16px, var(--scrollbar-space));
		--row-height: 3em;
	}

	:host([auto-height]) .dg-root {
		height: auto;
		overflow: visible;
	}

	:host([embedded-virtualized]) .dg-root {
		height: 100%;
		overflow: visible;
	}

	.dg-header {
		--sl-panel-background-color: var(--sl-color-neutral-100);
		position: relative;
		display: grid;
		grid-template-columns: var(--meta-column-width, 0px) var(--column-sizes);
		background: var(--sl-panel-background-color);
		border-bottom: var(--sl-panel-border-width) solid var(--sl-color-neutral-400);
		align-items: stretch;
		min-height: var(--sl-spacing-x-large);
		flex: 0 0 min-content;
		padding-right: var(--scrollbar-space);
	}

	.dg-col {
		position: relative;
		padding: var(--sl-spacing-2x-small) var(--sl-spacing-small);
		border-right: var(--sl-panel-border-width) solid var(--sl-color-neutral-400);
		box-sizing: border-box;

		/* Inner div lets us have clear space on the right edge of the column header */

		.dg-col-inner {
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
	}

	.dg-col--lead {
		grid-column: span 2;
	}

	.dg-col-resize-handle {
		position: absolute;
		top: 0;
		right: calc(-1 * var(--sl-spacing-2x-small));
		width: var(--sl-spacing-small);
		height: 100%;
		cursor: ew-resize;
		touch-action: none;
		z-index: 2;
	}

	:host(.dg-resizing),
	:host(.dg-resizing) * {
		cursor: ew-resize !important;
		user-select: none;
	}

	:host(.dg-resizing.dg-resize-limit-min),
	:host(.dg-resizing.dg-resize-limit-min) *,
	:host(.dg-resizing.dg-resize-limit-max),
	:host(.dg-resizing.dg-resize-limit-max) * {
		cursor: not-allowed !important;
	}

	.dg-resize-helper {
		position: absolute;
		top: 0;
		bottom: 0;
		border: 1px solid var(--sl-color-primary-600, #2869db);
		background: rgba(40, 105, 219, 0.15);
		box-sizing: border-box;
		pointer-events: none;
		z-index: var(--sl-z-index-tooltip);
	}

	:host(.dg-resize-limit-min) .dg-resize-helper,
	:host(.dg-resize-limit-max) .dg-resize-helper {
		border-right-color: var(--sl-color-danger-600);
	}
	.dg-colselection {
		position: absolute;
		right: 0px;
		width: var(--column-selection-width);
		padding: 0;
		justify-items: center;
		/* Give it a background color in case insufficient space makes it overlap */
		background-color: var(--sl-color-neutral-100);
	}

	.dg-body {
		flex: 1 1 auto;
		overflow-y: auto;
		overflow-x: hidden;
		min-height: 0;
		position: relative;
		scrollbar-gutter: stable;

		table {
			width: 100%;
			box-sizing: border-box;
			display: grid;
			grid-template-columns: var(--meta-column-width, 0px) var(--column-sizes, repeat(var(--column-count), 1fr));

			tbody {
				display: grid;
				grid-template-columns: var(--meta-column-width, 0px) var(--column-sizes, repeat(var(--column-count), 1fr));
				grid-column: 1 / -1;
				row-gap: var(--sl-spacing-2x-small);
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
			grid-template-columns: var(--meta-column-width, 0px) var(--column-sizes, repeat(var(--column-count), 1fr));
			outline: none;
			width: 100%;
			min-height: 4em;
			border-bottom: var(--sl-panel-border-width) solid var(--sl-color-neutral-200);
		}

		tbody > tr.dg-row-expanded {
			grid-template-columns: var(--meta-column-width, 0px) var(--column-sizes, repeat(var(--column-count), 1fr));
			min-height: 0;
			border-bottom: 0;
		}

		tbody > *[aria-selected="true"] {
			background: var(--sl-color-primary-50, #eef5ff);
		}

		tbody > [data-row-id].dg-row-active {
			box-shadow: inset 0 0 0 2px var(--sl-color-primary-600, #2869db);
		}

		tbody > [data-row-id].dg-row--refreshed {
			animation: dg-row-refresh-pulse 5s ease-out forwards;
		}

		tbody > [data-row-id].drop-hover {
			background: var(--sl-color-primary-100);
			box-shadow: var(--sl-shadow-large);
		}

		tbody td,
		tbody th {
			box-sizing: border-box;
			padding: 0px var(--sl-spacing-x-small);
			min-width: 0;
			max-height: var(--row-cell-max-height, 10em);
			overflow-x: hidden;
			overflow-y: auto;
			text-overflow: ellipsis;
		}

		tbody td[data-dg-meta-cell="1"] {
			padding: 0;
			min-width: 0;
			max-height: none;
			overflow: hidden;
			display: flex;
			align-items: flex-start;
			justify-content: center;
		}

	}

	.dg-row-expander {
		appearance: none;
		border: 0;
		background: transparent;
		color: var(--sl-color-neutral-700);
		cursor: pointer;
		inline-size: var(--row-expander-size);
		block-size: var(--row-expander-size);
		display: inline-flex;
		align-items: center;
		justify-content: center;
		padding: 0;
		margin: 0;
	}

	.dg-row-expander:hover {
		color: var(--sl-color-neutral-900);
	}

	.dg-row-expander:focus-visible {
		outline: 2px solid var(--sl-color-primary-600);
		outline-offset: -2px;
	}

	.dg-row-expander__icon {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		transition: transform 120ms ease-out;
	}

	.dg-row-expander__chevron {
		inline-size: 0;
		block-size: 0;
		border-block-start: calc(var(--row-expander-icon-size) * 0.8) solid transparent;
		border-block-end: calc(var(--row-expander-icon-size) * 0.8) solid transparent;
		border-inline-start: var(--row-expander-icon-size) solid currentColor;
		transform-origin: 45% 50%;
	}

	.dg-row-expander--expanded .dg-row-expander__icon {
		transform: rotate(90deg);
	}

	.dg-row-expander slot[name="collapse-icon"],
	.dg-row-expander--expanded slot[name="expand-icon"] {
		display: none;
	}

	.dg-row-expander--expanded slot[name="collapse-icon"] {
		display: inline-flex;
	}

	.dg-row-placeholder {
		background: transparent;
	}

	.dg-body tbody td.dg-expanded-cell {
		grid-column: 1 / -1;
		padding: 0;
		max-height: none;
		overflow: visible;
	}

	@keyframes dg-row-refresh-pulse {
		0% {
			background-color: color-mix(in srgb, var(--sl-color-warning-200) 0%, transparent);
		}
		15% {
			background-color: var(--sl-color-warning-200);
		}
		100% {
			background-color: color-mix(in srgb, var(--sl-color-warning-200) 35%, transparent);
		}
	}

	:host([auto-height]) .dg-body {
		flex: 0 0 auto;
		overflow: visible;
		scrollbar-gutter: auto;
	}

	:host([embedded-virtualized]) .dg-body {
		flex: 1 1 auto;
		overflow: visible;
		scrollbar-gutter: auto;
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

`;
