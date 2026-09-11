import {css} from "lit";

export default css`
	/* Allow actually disabled inputs */

	:host([disabled]) {
	display: initial;
	}

	/* Needed so required can show through */

	::slotted(input), input {
	background-color: transparent;
	}

	/* Used to allow auto-sizing on slotted inputs */

	.input-group__container > .input-group__input ::slotted(.form-control) {
	width: 100%;
	}

	.form-control__help-text {
	position: relative;
	  width: 100%;
	}
`;
