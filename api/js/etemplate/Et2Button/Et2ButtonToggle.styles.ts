import {css} from "lit";

export default css`
	slot[name] {
		display: none;
		width: 1em;
	}

	sl-switch {
		font-size: inherit;
	}
	img[part="image"]{
		filter: var(--image-filter);
	}
	et2-image::before{
		vertical-align: bottom;
	}

	sl-switch:not([checked]) slot[name="off"] {
		position: relative;
		color: currentColor;

		/*target img, and bootstrap font image*/
		img, *::before{
			filter: var(--image-filter-off, brightness(0) contrast(.3) opacity(.7));
		}
	}

	sl-switch[checked] slot[name="on"], sl-switch:not([checked]) slot[name="off"] {
		display: inline-block;
	}

	sl-switch:not([checked]):not(.has-off-icon) slot[name="off"]::after {
		content: '';
		position: absolute;
		top: 45%;
		left: 0;
		width: 100%;
		height: 2px;
		background-color: currentColor;
		transform: rotate(-45deg) translate(0%, 50%);
		pointer-events: none;
	}

	.label {
		border: var(--sl-input-border-width) solid var(--sl-input-border-color);
		border-radius: var(--sl-input-border-radius-medium);
		background-color: var(--sl-input-background-color);
		width: var(--sl-input-height-medium);
		height: var(--sl-input-height-medium);
		box-sizing: border-box;
		justify-content: center;
		align-items: center;
	}

	:host .label:hover {
		background-color: var(--sl-input-background-color-hover);
		border-color: var(--sl-input-border-color-hover);
	}

	/* Success */

	:host([variant=success]) .label {
		background-color: var(--sl-color-success-600);
		border-color: var(--sl-color-success-600);
		--indicator-color: var(--sl-color-neutral-0);
	}

	:host([variant=success]) .label:hover {
		background-color: var(--sl-color-success-500);
		border-color: var(--sl-color-success-500);
		--indicator-color: var(--sl-color-neutral-0);
	}

	/* Neutral */

	:host([variant=neutral]) .label {
		border-color: var(--sl-input-border-color);
		--indicator-color: var(--sl-input-color);
	}

	:host([variant=neutral]) .label:hover {
		border-color: var(--sl-input-border-color);
		--indicator-color: var(--sl-input-color);
	}

	/* Warning */

	:host([variant=warning]) .label {
		background-color: var(--sl-color-warning-600);
		border-color: var(--sl-color-warning-600);
		--indicator-color: var(--sl-color-neutral-0);
	}

	:host([variant=warning]) .label:hover {
		background-color: var(--sl-color-warning-500);
		border-color: var(--sl-color-warning-500);
		--indicator-color: var(--sl-color-neutral-0);
	}

	/* Danger */

	:host([variant=danger]) .label {
		background-color: var(--sl-color-danger-600);
		border-color: var(--sl-color-danger-600);
		--indicator-color: var(--sl-color-neutral-0);
	}

	:host([variant=danger]) .label:hover {
		background-color: var(--sl-color-danger-500);
		border-color: var(--sl-color-danger-500);
		--indicator-color: var(--sl-color-neutral-0);
	}

	/* Sizes */

	:host([size=small]) {
		font-size: var(--sl-input-font-size-small);

		.label {
			width: var(--sl-input-height-small);
			height: var(--sl-input-height-small);
		}
	}

	:host([size=large]) {
		font-size: var(--sl-input-font-size-large);

		.label {
			width: var(--sl-input-height-large);
			height: var(--sl-input-height-large);
		}
	}
`;
