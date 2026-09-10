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

	:host(:hover) {
		.expand-icon {
			display: initial;
		}
	}

	:host(:not([open])) {
		cursor: pointer;
	}

	:host(:not([noDialog])) .form-control-input {
		max-height: 9em;
		overflow: hidden;
	}
`;
