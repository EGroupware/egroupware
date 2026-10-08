import {css} from "lit";

export default css`
	:host {
		display: flex;
	}
	.input-group__suffix{
		width: var(--sl-spacing-small);
		height: var(--sl-spacing-small);
	}
	.input-group__container {
		align-items: center
	}

	/* Required and empty: the yellow etemplate2.css gives the other widgets through a part this one does not have */
	:host([required]:not(.hasValue)) .color-dropdown__trigger {
		background-color: var(--required-background-color, #ffffd0);
	}

	.color-dropdown__trigger--empty .input__clear {
		display: none;
	}

	/* The same not-allowed cursor the trigger shows */
	.input__clear:disabled {
		cursor: not-allowed;
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
