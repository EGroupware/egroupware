import {css} from "lit";

export default css`
	:host([focused]) ::slotted(button), :host(:hover) ::slotted(button) {
	display: inline-block;
	}

	.form-control-input {
		et2-textbox {
			flex: 1 1 auto;
			min-width: 19ex;
		}

		input[type*=date] {
			min-width: 14.5em;
		}
	}

	::slotted(.calendar_button) {
	border: none;
	background: transparent;
	margin-left: -20px;
	display: none;
	}
`;
