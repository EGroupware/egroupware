import {css} from "lit";

export default css`
	:host {
		position: relative;
	}

	.expand-icon {
		display: none;
		position: absolute;
		bottom: var(--sl-spacing-medium);
		right: var(--sl-spacing-medium);
		background-color: var(--sl-panel-background-color);
		z-index: 1;
	}

	/*
	 * On hover, and only for a diff that is actually cut off - offering it for one the reader can
	 * already see in full would say there is more when there is not.
	 * Et2Diff._checkOverflow() sets the attribute.
	 */
	:host([overflowing]:hover) .expand-icon {
		display: initial;
	}

	:host([overflowing]:not([open])) {
		cursor: pointer;
	}

	:host(:not([noDialog])) .form-control-input {
		max-height: 9em;
		overflow: hidden;
	}
`;
