import {css} from "lit";

export default css`
	:host([disabled]) {
		display: none;
	}

	/* Without this, an inherited :host{display:...} (eg. from Shoelace) silently outranks the browser's own [hidden] rule - see Et2MenuItem.ts for the same fix. */
	:host([hidden]) {
		display: none;
	}

	:host(.et2_clickable) {
		cursor: pointer;
	}

	/* CSS to align internal inputs according to box alignment */

	:host([align="center"]) .input-group__input {
		justify-content: center;
	}

	:host([align="right"]) .input-group__input {
		justify-content: flex-end;
	}

	/* Put widget label to the left of the widget */

	::part(form-control), .form-control {
		display: flex;
		align-items: center;
		flex-wrap: wrap;
	}

	::part(form-control-label), .form-control-label {
		flex: 0 0 auto;
		white-space: normal;
	}

	.form-control--has-label .form-control__label {
		margin-right: var(--sl-spacing-medium);
	}

	::part(form-control-input), .form-control-input {
		flex: 1 1 auto;
		position: relative;
		max-width: 100%;
	}

	::part(form-control-help-text), .form-control-help-text {
		flex-basis: 100%;
		position: relative;
	}

	/* Use .et2-label-fixed class to give fixed label size */

	:host(.et2-label-fixed) {
		&::part(form-control-label), & > *::part(form-control-label), .form-control-label {

			width: initial;
			width: var(--label-width, 8em);
		}
	}

	:host(.et2-label-fixed)::part(form-control-help-text), :host(.et2-label-fixed) .form-control-help-text {
		left: calc(var(--sl-spacing-medium) + var(--label-width, 8em));
	}
`;
