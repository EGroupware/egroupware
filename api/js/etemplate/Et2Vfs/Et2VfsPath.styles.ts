import {css} from 'lit';

export default css`

	.form-control-input {
		position: relative;
		min-width: 15em;

		flex: 1;
		display: flex;
		flex-direction: row;
		flex-wrap: nowrap;
		align-items: center;
		justify-content: space-between;
		gap: 0.1rem 0.5rem;

		background-color: var(--sl-input-background-color);
		border: solid var(--sl-input-border-width) var(--sl-input-border-color);

		border-radius: var(--sl-input-border-radius-medium);
		font-size: var(--sl-input-font-size-medium);
		padding-block: 0;
		padding-inline: var(--sl-input-spacing-medium);
		padding-top: 0.1rem;
		padding-bottom: 0.1rem;

		transition: var(--sl-transition-fast) color, var(--sl-transition-fast) border, var(--sl-transition-fast) box-shadow,
		var(--sl-transition-fast) background-color;
	}

	.vfs-path__value-input {
		flex: 1 1 auto;
		min-width: 12em;
		border: none;
		outline: none;
		color: var(--input-text-color);

		font-size: var(--sl-input-font-size-medium);
		padding-block: 0;
		padding-inline: var(--sl-input-spacing-medium);
	}

	/* Edit button */

	.vfs-path__edit {
		flex-grow: 0;
		display: inline-flex;
		visibility: hidden;
		align-items: center;
		justify-content: center;
		font-size: inherit;
		color: var(--sl-input-icon-color);
		border: none;
		background: none;
		padding: 0;
		transition: var(--sl-transition-fast) color;
		cursor: pointer;
	}

	:host(:hover) .vfs-path__edit {
		visibility: visible;
	}

	.vfs-path__scroll {
		flex: 1 1 auto;
		overflow: hidden;
		min-width: 10em;
	}

	.vfs-path__overflow .vfs-path__scroll {
		min-width: 8em;
		margin-right: 2em;
	}

	.form-control-input sl-icon-button[name*="caret"] {
		display: none;
		position: absolute;
		background: var(--sl-input-background-color);
	}

	.form-control-input sl-icon-button[name*="caret"]:last-of-type {
		right: 2em;
	}

	:host(:hover) .form-control-input.vfs-path__overflow sl-icon-button[name*="caret"] {
		display: initial;
	}

	/* Breadcrumb directories */

	sl-breadcrumb {
	}

	et2-image {
		flex: none;
		height: 2em;
	}

	sl-breadcrumb::part(base) {
		flex-wrap: nowrap;
	}

	sl-breadcrumb-item::part(base) {
		font-size: var(--sl-font-size-medium);
	}
	sl-breadcrumb-item::part(label) {
		color: var(--input-text-color);
	}

	sl-breadcrumb-item::part(separator) {
		color: var(--input-text-color);
		margin: 0;
		padding: 0 var(--sl-spacing-2x-small);
		cursor: pointer;
	}

	sl-breadcrumb-item:first-of-type {
	}

	sl-breadcrumb-item:first-of-type::part(separator) {
		display: initial;
	}

	sl-breadcrumb-item:last-of-type::part(separator) {
		/* Trailing / */
		display: none;
	}

	/* Sizes */

	.form-control--medium, .form-control--medium .form-control-input {
		min-height: calc(var(--sl-input-height-medium) - var(--sl-input-border-width) * 2);
		padding-top: 0;
		padding-bottom: 0;
	}

	.form-control--medium .vfs-path__edit {
		margin-inline-start: var(--sl-input-spacing-medium);
	}


	/* Readonly */

	:host([readonly]) .form-control-input {
		border: none;
		box-shadow: none;
	}

	.vfs-path__readonly sl-breadcrumb-item::part(label) {
		cursor: initial;
	}

	.vfs-path__disabled sl-breadcrumb-item::part(label) {
		color: var(--sl-input-color-disabled);
	}
`;