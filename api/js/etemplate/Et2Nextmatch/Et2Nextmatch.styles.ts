import {css} from "lit";

export default css`
	:host {
		display: block;
		height: 100%;
		min-height: 0;
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
`;
