import {css} from "lit";

export default css`
	:host {
		display: block;
	}

	.customfields {
		display: grid;
		grid-template-columns: max-content minmax(0, 1fr);
		gap: var(--sl-spacing-2x-small, 0.25rem) var(--sl-spacing-small, 0.75rem);
		align-items: start;
	}

	.customfields__label {
		padding-top: var(--sl-spacing-2x-small, 0.25rem);
	}

	.customfields__field {
		min-width: 0;
	}

	.customfields__field > * {
		min-width: 0;
	}
`;
