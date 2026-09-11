import {css} from "lit";

export default css`
	:host {
		display: block;
	}

	.customfields-filters {
		display: flex;
		flex-direction: column;
		gap: var(--sl-spacing-2x-small, 0.25rem);
	}

	.customfields-filters__field {
		min-width: 0;
	}

	.customfields-filters__field > * {
		min-width: 0;
	}
`;
