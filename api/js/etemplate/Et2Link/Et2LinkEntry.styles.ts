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

	/* The search drops below the app select when the two do not fit side by side */
	/*
	 * The row has no basis of its own, so what it needs (the app select and a search of at least 200px) is
	 * what decides whether the label can stay beside it.  With a content-sized basis it asked for the
	 * widest the search could ever be, and the label moved above the field with plenty of room to spare.
	 */
	.form-control-input {
		display: flex;
		flex: 1 1 0;
		flex-wrap: wrap;
		gap: 0.5rem;
		min-width: min(calc(200px + 6rem), 100%);
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
	 * as it was used, moving everything after it.  Only its min-width decides when it wraps.
	 */
	et2-link-search {
		flex: 1 1 0;
	}
`;
