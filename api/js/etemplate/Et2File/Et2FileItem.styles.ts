import {css} from 'lit';

export default css`
	:host {
		--border-radius: var(--sl-border-radius-medium);
		--border-style: solid;
		display: contents;
		margin: 0;
	}

	.file-item {
		position: relative;
		display: flex;
		background-color: var(--sl-panel-background-color);
		border: var(--sl-panel-border-width) var(--border-style, solid) var(--sl-panel-border-color);
		border-radius: var(--sl-border-radius-medium);
		font-size: var(--sl-font-size-medium);
		font-weight: var(--sl-font-weight-normal);
		line-height: var(--sl-line-height-normal);
		color: var(--label-color);
		margin: inherit;
	}

	.file-item__content {
		position: relative;
		display: flex;
		flex: 1;
		overflow: hidden;
	}

	.file-item:not(.file-item--has-image) .file-item__image,
	.file-item:not(.file-item--closable) .file-item__close-button {
		display: none;
	}

	.file-item--is-loading .file-item__image,
	.file-item--is-loading .file-item__label {
		visibility: hidden;
	}

	.file-item__image {
		flex: 0 0 auto;
		display: flex;
		align-items: center;
		padding-left: var(--sl-spacing-medium);
		color: var(--sl-color-primary-600);

		slot > * {
			line-height: 1;
		}

	}

	.file-item__progress-bar__container {
		inset: 0;
		position: absolute;
		display: flex;
		padding: var(--sl-spacing-large);
		align-items: center;
	}

	.file-item__progress-bar {
		flex: 1;
	}

	.file-item__label {
		flex: 1 1 auto;
		padding: var(--sl-spacing-small);
		overflow: hidden;
		display: flex;
		flex-direction: column;
		font-size: var(--sl-font-size-small);
	}

	.file-item__label__size {
		font-size: var(--sl-font-size-x-small);
		line-height: var(--sl-line-height-dense);
	}

	.file-item__close-button {
		flex: 0 0 auto;
		display: flex;
		align-items: center;
		font-size: var(--sl-font-size-large);
		padding-right: var(--sl-spacing-small);
		color: var(--sl-color-neutral-500);
	}

	/*
	 * Variants
	 */

	/* Default */

	.file-item.file-item--default {
		border-color: var(--sl-color-neutral-300);
		color: var(--sl-color-neutral-700);
	}

	.file-item.file-item--default:hover:not(.file-item--disabled) {
		border-color: var(--sl-input-border-color-hover);
		color: var(--sl-input-color-hover);
	}

	/* Primary */

	.file-item.file-item--primary {
		border-color: var(--sl-color-primary-600);
		color: var(--sl-color-primary-600);
	}

	.file-item.file-item--primary:hover:not(.file-item--disabled) {
		border-color: var(--sl-color-primary-300);
		color: var(--sl-color-primary-500);
	}

	/* Success */

	.file-item.file-item--success {
		border-color: var(--sl-color-success-600);
		color: var(--sl-color-success-600);
	}

	.file-item.file-item--success:hover:not(.file-item--disabled) {
		border-color: var(--sl-color-success-500);
		color: var(--sl-color-success-500);
	}

	/* Neutral */

	.file-item.file-item--neutral {
		border-color: var(--sl-color-neutral-600);
		color: var(--sl-color-neutral-1000);
	}

	.file-item.file-item--neutral:hover:not(.file-item--disabled) {
		border-color: var(--sl-color-neutral-500);
		color: var(--sl-color-neutral-1000);
	}

	/* Warning */

	.file-item.file-item--warning {
		border-color: var(--sl-color-warning-600);
		color: var(--sl-color-warning-600);
	}

	.file-item.file-item--warning:hover:not(.file-item--disabled) {
		border-color: var(--sl-color-warning-500);
		color: var(--sl-color-warning-500);
	}

	/* Danger */

	.file-item.file-item--danger {
		border-color: var(--sl-color-danger-600);
		color: var(--sl-color-danger-600);

		.file-item__image {
			color: inherit;
		}
	}

	.file-item.file-item--danger:hover:not(.file-item--disabled) {
		border-color: var(--sl-color-danger-500);
		color: var(--sl-color-danger-500);
	}

	/**
	 * Displays
	 */

	.file-item.file-item--large {
		min-height: var(--sl-input-height-large);

		.file-item__image {
			font-size: var(--sl-font-size-2x-large);
		}

		.file-item__label {
			padding: var(--sl-spacing-medium);
		}
	}

	.file-item.file-item--small {
		min-height: var(--sl-input-height-small);

		.file-item__image {
			font-size: var(--sl-font-size-large);
			line-height: 1;
		}

		.file-item__label {
			flex-direction: row;
			align-items: center;

			slot {
				display: inline-block;
				flex: 1 1 auto;
			}

			sl-format-bytes {
				flex: 0 0 fit-content;
			}
		}
	}

	.file-item.file-item--list {
		min-height: var(--sl-input-height-small);

		.file-item__image {
			font-size: var(--sl-font-size-large);
			line-height: 1;
		}

		.file-item__label {
			flex-direction: row;
			align-items: center;

			slot {
				display: inline-block;
				flex: 1 1 auto;
			}

			sl-format-bytes {
				flex: 0 0 20%;
				text-align: right;
			}
		}
	}

	.file-item.file-item--list.file-item--default {
		border-color: transparent;
	}
`;