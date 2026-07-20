import {css} from "lit";

export default css`
	:host {
		display: flex;
		height: 100%;
		min-height: 0;
		flex-direction: column;
	}

	et2-datagrid {
		display: block;
		height: 100%;
		min-height: 0;
		--meta-column-width: 6px;
	}

	et2-datagrid::part(row-meta) {
		border-left: 6px solid transparent;
	}

	et2-datagrid::part(row-meta-category) {
		border-left-color: var(--category-color, transparent);
	}

	.nextmatch-subgrid {
		height: auto;
	}

	.nextmatch-subgrid::part(state) {
		padding: var(--sl-spacing-x-small);
	}

	.nextmatch_lettersearch {
		display: flex;
		flex-wrap: nowrap;
	}

	.lettersearch {
		flex: 1 1 auto;
		border: 1px solid var(--sl-color-neutral-300);
		background: var(--sl-color-neutral-100);
		color: var(--sl-color-neutral-900);
		font: inherit;
		padding: 0.1rem 0.4rem;
		cursor: pointer;
	}

	.lettersearch_active,
	.lettersearch:hover {
		background: var(--sl-color-primary-100);
		border-color: var(--sl-color-primary-500);
	}

`;
