import {css} from "lit";

export default css`
	:host {
		width: fit-content;
		cursor: pointer;
		display: inline-flex;
		white-space: nowrap;
	}

	.nextmatch_sortheader {
		padding-right: var(--sl-spacing-small);
		overflow: hidden;
		text-overflow: ellipsis;
		flex: 1 1 auto;
	}

	.nextmatch_sortheader:hover {
		text-decoration: underline;
	}

	.nextmatch_sortheader--marker {
		width: 1em;
		text-decoration: none;
		background-repeat: no-repeat;
	}
`;
