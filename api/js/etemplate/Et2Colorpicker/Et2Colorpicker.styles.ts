import {css} from "lit";

export default css`
	:host {
		display: flex;
	}
	.input-group__suffix{
		width: 12px;
		height: 12px;
	}
	.input-group__container {
		align-items: center
	}

	.color-dropdown__trigger--empty .input__clear {
		display: none;
	}
	.input__clear {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		font-size: inherit;
		color: var(--sl-input-icon-color);
		border: none;
		background: none;
		padding: 0px;
		transition: var(--sl-transition-fast) color;
		cursor: pointer;

		/* Positioning of clear button */
		position: absolute;
		left: var(--sl-input-height-medium);
		top: 0px;
		margin: auto 0px;
		bottom: 0px;

	}
`;
