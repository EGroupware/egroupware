import {css} from "lit";

export default css`
	.form-field__group-two {
		max-width: 100%;
	}

	.form-control-input {
		display: flex;
		flex-direction: row;
		flex-wrap: nowrap;
		align-items: baseline;
	}

	.input-group__after {
		display: contents;
		margin-inline-start: var(--sl-input-spacing-medium);
	}

	sl-select {
		color: var(--input-text-color);
		flex: 2 1 auto;
		min-width: min-content;
		width: 8em;

		&::part(combobox) {
			border-left: 1px solid var(--input-border-color);
			border-top-left-radius: 0px;
			border-bottom-left-radius: 0px;
		}
	}

	sl-select::part(control) {
		border-top-left-radius: 0px;
		border-bottom-left-radius: 0px;
	}

	.duration__input {
		flex: 1 1 auto;
		width: min-content;
		min-width: 5em;
		/* This is the same as max-width of the number field */
		max-width: 7em;
		margin-right: -2px;
	}


	.duration__input:not(:first-child)::part(base) {
		border-top-left-radius: 0px;
		border-bottom-left-radius: 0px;
	}

	.duration__input:not(:last-child)::part(base) {
		border-right: none;
		border-top-right-radius: 0px;
		border-bottom-right-radius: 0px;
	}
`;
