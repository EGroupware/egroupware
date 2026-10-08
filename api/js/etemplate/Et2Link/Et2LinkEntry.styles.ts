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

	/*
	 * The app select and the search always stay on one line; only the label may wrap above them.
	 * The row has no basis of its own, so what it needs (the app select and a search of at least 200px) is
	 * what decides whether the label can stay beside it.  With a content-sized basis it asked for the
	 * widest the search could ever be, and the label moved above the field with plenty of room to spare.
	 */
	.form-control-input {
		display: flex;
		flex: 1 1 0;
		flex-wrap: nowrap;
		gap: 0.5rem;
		min-width: min(calc(12.5rem + 6rem), 100%);
	}

	et2-link-apps {
		flex: 1 1 auto;
		&::part(icon){
			margin-inline-end: 0;
		}
	}

	et2-url {
		flex: 1 1 auto;
	}

	/*
	 * No basis of its own: with auto the search's content (what is typed, the selected entry, the open
	 * dropdown) decides whether the row still fits, and the search would drop below the app select as soon
	 * as it was used, moving everything after it.
	 */
	et2-link-search {
		flex: 1 1 0;
	}
`;
