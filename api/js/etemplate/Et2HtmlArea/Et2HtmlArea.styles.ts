import {css} from "lit";

export default css`
	:host {
		display: flex;
		flex-direction: column;
		width: 100%;
		height: 100%;
	}

	.form-control {
		display: flex;
		align-items: stretch;
		flex-direction: column;
		flex-wrap: nowrap;
		flex: 1 1 auto;
	}

	.form-control-input {
		display: flex;
		flex-direction: column;
		flex: 1 1 auto;
		min-width: 40em;
		min-height: 10em;
	}

	.form-control__help-text {
		display: none;
		flex-basis: 2em;
	}

	.form-control--has-help-text .form-control__help-text {
		display: block;
	}

	tinymce-editor,
	textarea {
	                display: flex;
	                flex-direction: column;
		flex: 1 1 auto;
		height: 100%;
		min-height: 0;
		width: 100%;
		box-sizing: border-box;
	}

	textarea {
		resize: vertical;
		font: inherit;
	}

	.htmlarea__has-menu, .htmlarea__has-toolbar {
		min-height: 15em;
	}

	.htmlarea__has-menu.htmlarea__has-toolbar {
		min-height: 20em;
	}

	.htmlarea__readonly {
		flex: 1 1 auto;
		min-height: 0;
		min-width: 0;
		overflow-wrap: anywhere;
		white-space: pre-wrap;
	}
`;
