import {css} from "lit";

export default css`
	* {
		white-space: pre-wrap;
	}
	:host {
		display:flex;
		flex-direction: row;
		justify-content: flex-start;
		align-items: center;
		flex: 0 1 auto !important;
	}

		label {
			padding-inline-end: 1ex;
		}

		.split-label label {
			display: contents;
		}
	::slotted(a) {
		cursor: pointer;
		color: var(--sl-color-primary-700);
		text-decoration: none;
	  	display: inherit;
	}
`;
