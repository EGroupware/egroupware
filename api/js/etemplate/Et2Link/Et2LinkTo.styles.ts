import {css} from "lit";

export default css`
	[hidden] {
		display: none;
	}

	et2-link-entry {
		flex: 1 1 auto;
	}

	.input-group__container {
		flex: 1 1 auto;
	}

	.form-control-input {
		display: flex;
		width: 100%;
		gap: 0.5rem;
	}

	::slotted(.et2_file) {
		width: 30px;
	}
`;
