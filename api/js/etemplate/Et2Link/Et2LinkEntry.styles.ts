import {css} from "lit";

export default css`
	:host {
		display: block;
	}

	:host(.hideApp) ::slotted([slot="app"]) {
		display: none;
	}

	[hidden] {
		display: none;
	}

	.form-control-input {
		display: flex;
		gap: 0.5rem;
	}

	et2-link-apps {
		flex: 1 1 auto;
		&::part(icon){
			margin-inline-end: 0;
		}
	}

	et2-url, et2-link-search {
		flex: 1 1 auto;
	}
`;
