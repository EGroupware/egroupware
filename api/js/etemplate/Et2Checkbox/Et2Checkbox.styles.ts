import {css} from "lit";

export default css`
	:host {
	/* Make it line up with the middle of surroundings */
	margin: auto 0px;
	vertical-align: baseline;
	}

	:host([disabled]) {
	display: initial;
	}

	/* Fix positioning */

	.checkbox {
	position: relative;
	}

	/* Extend hover highlight to label */

	.checkbox:not(.checkbox--disabled):hover {
	color: var(--sl-input-border-color-hover);
	}

	/* Use normal color even when required */

	:host([required]) .checkbox__control {
	color: var(--input-text-color);
	}
`;
