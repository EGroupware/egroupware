import {css} from "lit";

export default css`
	:host {
		display: block;
		width: 100%;
		min-width: 0;
	}

	.form-control {
		display: block;
		min-height: 0;
	}

	.form-control-input,
	.htmlarea__readonly {
		display: block;
		min-height: 0;
		min-width: 0;
	}

	.htmlarea__readonly {
		overflow-wrap: anywhere;
	}

	.htmlarea__readonly--ascii {
		white-space: pre-wrap;
	}

	.htmlarea__readonly > :first-child {
		margin-block-start: 0;
	}

	.htmlarea__readonly > :last-child {
		margin-block-end: 0;
	}
`;
