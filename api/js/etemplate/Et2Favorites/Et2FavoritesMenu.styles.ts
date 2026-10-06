import {css} from "lit";

export default css`
	:host {
		min-width: 15em;
	}

	[part="menu"] {
		padding: 0;
		--indent-size: var(--sl-spacing-small);
		--indent-guide-width: 0;
	}

	[part="label"] {
		font-weight: var(--sl-font-weight-semibold);
		padding: var(--sl-spacing-x-small);
	}

	sl-tree-item::part(item) {
		padding-block: 0;
	}

	sl-tree-item::part(expand-button) {
		padding: 0 var(--sl-spacing-2x-small);
	}

	/* Nothing to open, so nothing to leave room for */
	.no-folders sl-tree-item::part(expand-button) {
		display: none;
	}

	sl-tree-item::part(label) {
		flex: 1 1 auto;
		min-width: 0;
	}

	sl-tree-item[selected]::part(item) {
		background-color: var(--highlight-background-color);
	}

	sl-tree-item.has-active:not([expanded])::part(item) {
		background-color: var(--highlight-background-color);
	}

	.row {
		display: flex;
		align-items: center;
		gap: var(--sl-spacing-x-small);
		width: 100%;
	}

	.row > sl-icon {
		flex: 0 0 auto;
		color: var(--sl-color-neutral-500);
	}

	.row > .name {
		flex: 1 1 auto;
		min-width: 0;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	/* What can be done is there when pointing at the row, or when there is no pointing */
	.more {
		flex: 0 0 auto;
		visibility: hidden;
		/* As tall as the row, so there is no edge of the button to miss at the top or bottom */
		align-self: stretch;
		display: flex;
	}

	.more::part(trigger) {
		display: flex;
		height: 100%;
	}

	.more et2-button-icon {
		display: flex;
		height: 100%;
	}

	/* A wide target: missing it applies the favorite.  Padding, not a bigger icon, so the row does not grow,
	 * and the same size as the icon to drop onto, which takes its place while dragging. */
	.more et2-button-icon::part(base) {
		box-sizing: border-box;
		height: 100%;
		padding-block: var(--sl-spacing-3x-small);
		padding-inline: var(--sl-spacing-small);
	}

	.row:hover .more,
	.row:focus-within .more,
	.more[open] {
		visibility: visible;
	}

	/* The picture the browser drags along is taken when the drag starts, with the pointer still over the row.
	 * Sortable has marked the row as chosen by then, so keep the button out of the picture. */
	sl-tree-item.sortable-chosen .row .more {
		visibility: hidden;
	}

	/* While dragging, the buttons make way for the icons to drop onto */
	.drop-icon {
		display: none;
		flex: 0 0 auto;
		/* A wide target, for aiming at while dragging.  Padding, not a bigger icon, so the row does not grow. */
		box-sizing: content-box;
		padding-block: var(--sl-spacing-3x-small);
		padding-inline: var(--sl-spacing-small);
		color: var(--sl-color-primary-600);
		cursor: copy;
	}

	:host([dragging]) .drop-icon {
		display: inline-flex;
	}

	:host([dragging]) .more {
		display: none;
	}

	/* No dropping onto itself */
	.ui-fav-sortable-placeholder > .row > .drop-icon {
		visibility: hidden;
	}

	sl-tree-item.drop-onto > .row > .drop-icon {
		color: var(--sl-color-neutral-0);
		background-color: var(--sl-color-primary-600);
		border-radius: var(--sl-border-radius-small);
	}

	@media (hover: none) {
		.more {
			visibility: visible;
		}
	}

	/* Where the favorite being dragged would go */
	sl-tree-item.ui-fav-sortable-placeholder::part(item) {
		background-color: var(--highlight-background-color);
		opacity: 0.6;
	}

	/* The favorite being dragged is over the middle of this one */
	sl-tree-item.drop-onto::part(item) {
		outline: 2px solid var(--sl-color-primary-600);
		outline-offset: -2px;
	}
`;
