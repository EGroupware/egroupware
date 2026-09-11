import {css} from "lit";

export default css`
	/* Scroll buttons */

	:host(:hover) .input--medium .input__suffix ::slotted(et2-button-scroll) {
		visibility: visible;
	}

	.input--medium .input__suffix ::slotted(et2-button-scroll) {
		visibility: hidden;
		padding: 0px;
		margin: 0px;
		margin-left: var(--sl-spacing-small);
		margin-inline-end: var(--sl-spacing-x-small);
	}

	:host([step]) .input--medium .input__control {
		padding-left: 0px;
		flex-shrink: 0;
	}

	.form-control-input {
		min-width: var(--width, 4em);
		max-width: var(--width, 7em);
	}

	.input__control {
		text-align: right;
	}
`;
