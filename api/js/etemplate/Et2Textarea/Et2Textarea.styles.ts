import {css} from "lit";

export default css`
	:host {
		display: flex;
		flex-direction: column;
		width: 100%;
		height: 100%;
	}

	.textarea--resize-vertical {
		height: 100%;
	}

	:host::part(form-control) {
		height: 100%;
		align-items: stretch !important;
	}

	:host::part(form-control-input), :host::part(textarea) {
		height: 100%;
	}

	.form-control-input .textarea--standard.textarea--focused:not(.textarea--disabled){
		width: calc(100% - (2 * var(--sl-focus-ring-width)));
		margin-left: var(--sl-focus-ring-width);
	}
`;
