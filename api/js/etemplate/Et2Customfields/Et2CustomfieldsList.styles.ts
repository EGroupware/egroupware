import {css} from "lit";

/**
 * Selectors are written against the tag rather than :host because et2-customfields-list
 * renders into its own light DOM - see lightDomStylesTemplate() in Et2CustomfieldsBase.
 */
export default css`
	et2-customfields-list {
		display: block;
	}

	et2-customfields-list .customfields-list {
		display: flex;
		flex-direction: column;
		gap: var(--sl-spacing-2x-small, 0.25rem);
	}

	et2-customfields-list .customfields-list__field {
		display: flex;
		align-items: center;
		min-width: 0;
	}

	et2-customfields-list .customfields-list__field[hidden] {
		display: none;
	}

	et2-customfields-list .customfields-list__field > * {
		min-width: 0;
	}

	et2-customfields-list[no-label] .customfields-list__field {
		align-items: stretch;
		width: 100%;
	}

	et2-customfields-list[no-label] .customfields-list__field > * {
		flex: 1 1 auto;
		width: 100%;
		max-width: 100%;
	}

	et2-customfields-list[no-label] et2-link::part(remark) {
		display: none;
	}
`;
