import {css} from "lit";

export default css`
	::slotted(input), input, ::slotted(select) {
		background-color: transparent;
		border: none !important;
	}
	.input-group {
		border: 1px solid var(--input-border-color);
	}
	.input-group__suffix{
		text-align: center;
	}
	.input-group__container {
		align-items: center
	}
	::slotted([slot="suffix"]) {
		border: none !important;
		background-color: transparent !important;
		width: 2rem;
		margin-inline-end: var(--sl-input-spacing-medium);
	}
	::slotted(:disabled) {cursor: default !important;}
	:host(:hover) ::slotted([slot="suffix"]) {
		cursor: pointer;
	}
`;
