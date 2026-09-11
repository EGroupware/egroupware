import {css} from "lit";

export default css`
	:host {
		--category-color: transparent;
	}

	/* Color on tree items */
	::part(item-item) {
		border-inline-start: 4px solid transparent;
		border-inline-start-color: var(--category-color, transparent);
	}

	/* Color on tags */
	:host(:not([multiple])) .tree_tag::part(base) {
		border-inline-start: 4px solid transparent;
		border-inline-start-color: var(--category-color, transparent);
	}

	/* Color on single value */

	:host(:not([multiple])) .tree-dropdown:not(.tree-dropdown--has-value) .tree-dropdown__combobox {
		padding-inline-start: 3px;
	}

	:host(:not([multiple])) .tree-dropdown--has-value .tree-dropdown__combobox {
		border-inline-start: 4px solid;
		border-inline-start-color: var(--category-color, var(--sl-input-border-color));
	}
`;
