import {css} from "lit";

/**
 * Selectors are written against the tag rather than :host because et2-customfields-filters
 * renders into its own light DOM - see lightDomStylesTemplate() in Et2CustomfieldsBase.
 */
export default css`
	et2-customfields-filters {
		display: block;
	}

	et2-customfields-filters .customfields-filters {
		display: flex;
		flex-direction: column;
		gap: var(--sl-spacing-2x-small, 0.25rem);
	}

	et2-customfields-filters .customfields-filters__field {
		min-width: 0;
	}

	et2-customfields-filters .customfields-filters__field > * {
		min-width: 0;
	}
`;
