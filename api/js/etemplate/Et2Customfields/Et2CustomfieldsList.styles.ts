import {css} from "lit";

export default css`
	:host {
		display: block;
	}

	.customfields-list {
		display: flex;
		flex-direction: column;
		gap: var(--sl-spacing-2x-small, 0.25rem);
	}

	.customfields-list__field {
		display: flex;
		align-items: center;
		min-width: 0;
	}

	.customfields-list__field[hidden] {
		display: none;
	}

	.customfields-list__field > * {
		min-width: 0;
	}

	:host([no-label]) .customfields-list__field {
		align-items: stretch;
		width: 100%;
	}

	:host([no-label]) .customfields-list__field > * {
		flex: 1 1 auto;
		width: 100%;
		max-width: 100%;
	}

	:host([no-label]) et2-link::part(remark) {
		display: none;
	}
`;
