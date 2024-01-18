import {css} from 'lit';

export default css`
	:host {
		display: block;
		user-select: none;
	}

	:host(:focus) {
		outline: none;
	}

	:host([disabled]) {
		display: block;
	}

	.file {
		position: relative;
		display: flex;
		align-items: center;
		font-family: var(--sl-font-sans);
		font-size: var(--sl-font-size-medium);
		font-weight: var(--sl-font-weight-normal);
		line-height: var(--sl-line-height-normal);
		letter-spacing: var(--sl-letter-spacing-normal);
		color: var(--sl-color-neutral-700);
		padding: var(--sl-spacing-2x-small) var(--sl-spacing-medium) var(--sl-spacing-2x-small) var(--sl-spacing-x-small);
		transition: var(--sl-transition-fast) fill;
		cursor: pointer;
	}

	.file--hover:not(.file--current):not(.file--disabled) {
		background-color: var(--sl-color-neutral-100);
		color: var(--sl-color-neutral-1000);
	}

	.file--current,
	.file--current.file--disabled {
		background-color: var(--sl-color-primary-600);
		color: var(--sl-color-neutral-0);
		opacity: 1;
	}

	.file--disabled {
		outline: none;
		opacity: 0.5;
		cursor: not-allowed;
	}

	.file__label {
		flex: 1 1 auto;
		display: inline-block;
		line-height: var(--sl-line-height-dense);
	}

	.file .file__check {
		flex: 0 0 auto;
		display: flex;
		align-items: center;
		justify-content: center;
		visibility: hidden;
		padding-inline-end: var(--sl-spacing-2x-small);
	}

	.file et2-vfs-mime {
		/* line-height-normal has no unit */
		height: calc(var(--sl-line-height-normal) * 1em);
		width: var(--sl-input-height-medium);
		padding-inline-end: var(--sl-spacing-medium);
	}

	.file--selected .file__check {
		visibility: visible;
	}

	.file__prefix,
	.file__suffix {
		flex: 0 0 auto;
		display: flex;
		align-items: center;
	}

	.file__prefix::slotted(*) {
		margin-inline-end: var(--sl-spacing-x-small);
	}

	.file__suffix::slotted(*) {
		margin-inline-start: var(--sl-spacing-x-small);
	}

	@media (forced-colors: active) {
		:host(:hover:not([aria-disabled='true'])) .file {
			outline: dashed 1px SelectedItem;
			outline-offset: -1px;
		}
	}
`;
