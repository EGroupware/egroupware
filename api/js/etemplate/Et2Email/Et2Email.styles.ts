import {css} from 'lit';

export default css`
	:host([open]) {
		/* Handles z-index issues with toolbar of html editor on the page*/
		position: relative;
		z-index: 2;
	}

	.form-control-input {
		/* This allows the dropdown to show over other inputs */
		position: relative;
		z-index: 1;
	}

	.email .email__combobox {
		flex: 1;
		display: flex;
		flex-direction: row;
		flex-wrap: wrap;
		align-items: center;
		gap: 0.1rem 0.5rem;

		background-color: var(--sl-input-background-color);
		border: solid var(--sl-input-border-width) var(--sl-input-border-color);

		border-radius: var(--sl-input-border-radius-medium);
		font-size: var(--sl-input-font-size-medium);
		min-height: var(--sl-input-height-medium);
		padding-block: 0;
		padding-inline: var(--sl-input-spacing-medium);
		padding-top: 0.1rem;
		padding-bottom: 0.1rem;

		transition: var(--sl-transition-fast) color, var(--sl-transition-fast) border, var(--sl-transition-fast) box-shadow,
		var(--sl-transition-fast) background-color;
	}

	.email.email--disabled .email__combobox {
		background-color: var(--sl-input-background-color-disabled);
		border-color: var(--sl-input-border-color-disabled);
		color: var(--sl-input-color-disabled);
		opacity: 0.5;
		cursor: not-allowed;
		outline: none;
	}

	.email:not(.email--disabled).email--open .email__combobox,
	.email:not(.email--disabled).email--focused .email__combobox {
		background-color: var(--sl-input-background-color-focus);
		border-color: var(--sl-input-border-color-focus);
		box-shadow: 0 0 0 var(--sl-focus-ring-width) var(--sl-input-focus-ring-color);
	}

	.email .email__prefix {
		order: 1;
	}
	/* Tags */
	.email et2-email-tag {
		order: 2;
		flex-grow: 0;
		margin: auto 0px;
		--icon-width: 1.8em;

		outline: none;
	}

	/* Search box */
	.email__search {
		flex: 1 1 auto;
		order: 10;
		min-width: 10em;
		border: none;
		outline: none;

		font-size: var(--sl-input-font-size-medium);
		padding-block: 0;
		padding-inline: var(--sl-input-spacing-medium);
	}

	.form-control--medium .email__search {
		/* Input same size as tags */
		height: calc(var(--sl-input-height-medium) * 0.8);
	}

	.email .email__loading {
		order: 19;
	}
	.email .email__suffix {
		order: 20;
	}

	/* Listbox */

	.email__listbox {
		display: block;
		position: relative;
		font-family: var(--sl-font-sans);
		font-size: var(--sl-font-size-medium);
		font-weight: var(--sl-font-weight-normal);
		box-shadow: var(--sl-shadow-large);
		background: var(--sl-panel-background-color);
		border: solid var(--sl-panel-border-width) var(--sl-panel-border-color);
		border-radius: var(--sl-border-radius-medium);
		padding-block: var(--sl-spacing-x-small);
		padding-inline: 0;
		overflow: auto;
		overscroll-behavior: none;

		/* Make sure it adheres to the popup's auto size */
		max-width: var(--auto-size-available-width);
		max-height: var(--auto-size-available-height);

		/* This doesn't work for some reason, it's overwritten somewhere */
		--size: 1.8em;
	}

	.email__listbox ::slotted(sl-divider) {
		--spacing: var(--sl-spacing-x-small);
	}

	.email__listbox ::slotted(small) {
		font-size: var(--sl-font-size-small);
		font-weight: var(--sl-font-weight-semibold);
		color: var(--sl-color-neutral-500);
		padding-block: var(--sl-spacing-x-small);
		padding-inline: var(--sl-spacing-x-large);
	}

`;