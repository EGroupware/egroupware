import {css} from 'lit';

export default css`
	.form-control .form-control__label {
		display: none;
	}

	.form-control .form-control__help-text {
		display: none;
	}

	/* Label */

	.form-control--has-label .form-control__label {
		display: inline-block;
		color: var(--sl-input-label-color);
		margin-bottom: var(--sl-spacing-3x-small);
	}

	.form-control--has-label.form-control--small .form-control__label {
		font-size: var(--sl-input-label-font-size-small);
	}

	.form-control--has-label.form-control--medium .form-control__label {
		font-size: var(--sl-input-label-font-size-medium);
	}

	.form-control--has-label.form-control--large .form-control__label {
		font-size: var(--sl-input-label-font-size-large);
	}

	/* Help text */

	.form-control--has-help-text .form-control__help-text {
		display: block;
		color: var(--sl-input-help-text-color);
		margin-top: var(--sl-spacing-3x-small);
	}

	.tree-dropdown__combobox {
		min-height: calc(var(--sl-input-height-medium) - 2 * var(--sl-input-border-width));

		display: flex;
		flex-direction: row;
		flex-wrap: nowrap;
		align-items: flex-start;
		justify-content: space-between;

		background-color: var(--sl-input-background-color);
		border: solid var(--sl-input-border-width) var(--sl-input-border-color);

		border-radius: var(--sl-input-border-radius-medium);
		font-size: var(--sl-input-font-size-medium);
		overflow-y: auto;
		overflow-x: hidden;
		padding-block: 0;
		padding-inline: var(--sl-input-spacing-medium);

		transition: var(--sl-transition-fast) color, var(--sl-transition-fast) border, var(--sl-transition-fast) box-shadow,
		var(--sl-transition-fast) background-color;
	}

	.tree-dropdown--disabled {
		background-color: var(--sl-input-background-color-disabled);
		border-color: var(--sl-input-border-color-disabled);
		color: var(--sl-input-color-disabled);
		opacity: 0.5;
		cursor: not-allowed;
		outline: none;
	}

	:not(.tree-dropdown--disabled).tree-dropdown--open .tree-dropdown__combobox,
	:not(.tree-dropdown--disabled).tree-dropdown--focused .tree-dropdown__combobox {
		background-color: var(--sl-input-background-color-focus);
		border-color: var(--sl-input-border-color-focus);
		box-shadow: 0 0 0 var(--sl-focus-ring-width) var(--sl-input-focus-ring-color);
	}

	:host([required]) .tree-dropdown__combobox {
		background-color: var(--background-required);
	}

	/* Trigger */

	.tree-dropdown__expand-icon {
		height: var(--sl-input-height-small);
		margin-top: var(--sl-spacing-small);
		flex: 0 0 auto;
		display: flex;
		align-items: center;
		transition: var(--sl-transition-medium) rotate ease;
		rotate: 0;
		margin-inline-start: var(--sl-spacing-small);
	}

	.tree-dropdown--open .tree-dropdown__expand-icon {
		rotate: -180deg;
	}

	/* End trigger */

	.tree-dropdown__prefix {
		order: 1;
	}

	/* Tags */

	.tree-dropdown__tags {
		display: flex;
		flex: 2 1 auto;
		flex-wrap: wrap;
		align-content: center;
		gap: 0.1rem 0.5rem;
		padding-top: var(--sl-spacing-3x-small);
		max-height: calc(var(--height, 5) * var(--sl-input-height-medium));
		min-width: 0px;
	}

	/* Limit tag size */

	.tree_tag {
		color: green;
		max-width: 25em;
		overflow: hidden;
	}

	/* End tags */

	/* Search box */

	:host([readonly]) .tree-dropdown__search {
		display: none;
	}

	.tree-dropdown__search {
		flex: 1 1 7em;
		order: 10;
		min-width: 2em;
		border: none;
		outline: none;

		font-size: var(--sl-input-font-size-medium);
		padding-block: 0;
		padding-inline: var(--sl-input-spacing-medium);
	}

	.form-control--medium .tree-dropdown__search {
		/* Input same size as tags */
		height: calc(var(--sl-input-height-medium) * 0.8);
	}

	.tree-dropdown--disabled .tree-dropdown__search {
		cursor: not-allowed;
	}

	.tree-dropdown--readonly .tree-dropdown__search {
		cursor: default;
	}

	/* tag takes full width when widget is not multiple and has value and does not have focus */

	:host(:not([multiple])) .tree-dropdown--has-value .tree-dropdown__search {
		display: none;
	}

	:host(:not([multiple])) .tree-dropdown--focused .tree-dropdown__search,
	:host(:not([multiple])) .tree-dropdown--open .tree-dropdown__search {
		display: initial;
	}

	.tree-dropdown__suffix {
		order: 20;
	}

	.search__results {
		display: none;
	}

	.tree-dropdown--searching .search__results {
		display: initial;;
	}

	/* Tree */
	.tree-dropdown--searching et2-tree {
		display: none;
	}

	sl-popup::part(popup) {
		font-size: var(--sl-font-size-medium);
		font-weight: var(--sl-font-weight-normal);
		box-shadow: var(--sl-shadow-large);
		background: var(--sl-panel-background-color);
		border: solid var(--sl-panel-border-width) var(--sl-panel-border-color);
		border-radius: var(--sl-border-radius-medium);
		padding-block: var(--sl-spacing-x-small);
		padding-inline: 0;
		overflow-y: auto;
		overscroll-behavior: none;
		z-index: var(--sl-z-index-dropdown);

		/* Make sure it adheres to the popup's auto size */
		height: auto;
		max-width: var(--auto-size-available-width);
	}

	et2-tree {
		&::part(item) {
			font-family: var(--sl-font-sans);
			font-weight: var(--sl-font-weight-normal);
			line-height: var(--sl-line-height-normal);
			letter-spacing: var(--sl-letter-spacing-normal);
			color: var(--sl-color-neutral-700);

		}

		&::part(item-item) {
			padding: var(--sl-spacing-x-small) var(--sl-spacing-medium) var(--sl-spacing-x-small) var(--sl-spacing-x-small);
			transition: var(--sl-transition-fast) fill;
		}

		&::part(item-item):hover {
			background-color: var(--sl-color-neutral-100);
			color: var(--sl-color-neutral-1000);
		}

		&::part(checkbox) {
			display: none;
		}

		&::part(label) {
			flex: 1 0 auto;
			display: contents;
			overflow: unset;

			&:hover {
				text-decoration: none;
			}
		}

		&::part(label_text) {
			white-space: normal;
			overflow: unset;
			padding-right: var(--sl-spacing-medium);
			max-width: 30em;
		}
	}
`;