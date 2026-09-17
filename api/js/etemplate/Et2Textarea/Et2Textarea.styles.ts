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

	/*
	 * The input takes whatever height the control has left rather than all of it.  A narrow layout
	 * wraps the label onto its own line above the box, and "100%" is still the whole control, so the
	 * box hung past the bottom of the widget and over the field below it.  Flex already hands it the
	 * remaining space, which is the full height anyway whenever the label sits beside it.
	 */
	:host::part(form-control-input) {
		flex: 1 1 auto;
		min-height: 0;
	}

	:host::part(textarea) {
		height: 100%;
	}

	.form-control-input .textarea--standard.textarea--focused:not(.textarea--disabled){
		width: calc(100% - (2 * var(--sl-focus-ring-width)));
		margin-left: var(--sl-focus-ring-width);
	}
`;
