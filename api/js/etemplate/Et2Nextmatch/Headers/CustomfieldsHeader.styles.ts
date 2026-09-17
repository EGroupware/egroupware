import {css} from "lit";

/**
 * Selectors are written against the tag rather than :host because
 * et2-nextmatch-header-customfields renders into its own light DOM - see lightDomStylesTemplate().
 */
export default css`
	et2-nextmatch-header-customfields {
		display: block;
	}

	et2-nextmatch-header-customfields .label.et2_label_empty {
		min-width: var(--sl-spacing-small);
	}

	et2-nextmatch-header-customfields .customfields-header {
		position: relative;
	}

	et2-nextmatch-header-customfields .customfields-header__fields {
		width: 100%;
		border-collapse: collapse;
	}

	et2-nextmatch-header-customfields .customfields-header__fields td {
		padding: 0;
		vertical-align: top;
	}

	et2-nextmatch-header-customfields .customfields-header__field-header {
		display: block;
		width: 100%;
	}

	et2-nextmatch-header-customfields .customfields-header__field-list {
		max-height: 5em;
		overflow: hidden;
		overflow-y: auto;
	}
`;
