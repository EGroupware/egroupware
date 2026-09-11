import {css} from "lit";

export default css`
	:host {
		display: flex;
		flex-direction: column;
		column-gap: 10px;
		overflow: hidden;
	}

	div {
		display: flex;
		gap: 10px;
	}

	div:hover {
		background-color: var(--highlight-background-color);
	}

	div.zip_highlight {
		animation-name: new_entry_pulse, new_entry_clear;
		animation-duration: 5s;
		animation-delay: 0s, 30s;
		animation-fill-mode: forwards;
	}

	/* CSS for child elements */

	et2-link::part(title):after {
		/* Reset from Et2LinkString */
		content: initial;
	}

	et2-link::part(icon) {
		width: 1rem;
		display: inline-block;
	}

	et2-link::part(remark) {
		/* Reset from Et2LinkString */
		display: initial;
		/*edit windows link tabs highlight comments*/
		margin-left:auto;
		font-weight: bold;
		font-style: italic;
		color: var(--sl-color-gray-700)
	}

	et2-link {
		display: block;
		flex: 1 1 auto;
	}

	et2-link:hover {
		text-decoration: none;
	}

	et2-link::part(base) {
		display: flex;
	}

	.remark {
		flex: 1 1 auto;
		width: 20%;
	}

	div et2-image[part=delete-button] {
		visibility: hidden;
		width: 16px;
		order: 5;
		cursor: pointer;
	}

	div:hover et2-image[part=delete-button] {
		visibility: initial;
	}
`;
