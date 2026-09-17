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
		/*
		 * This control stacks its label over the editor, so it is a column flex - and wrapping a
		 * column flex makes a second column, not a second row.  A layout's one-column collapse
		 * turns wrapping on to get a label onto its own line, which is right for the row-direction
		 * controls it is written for but here puts the editor beside its label, off the side of the
		 * screen.  !important because a rule reaching in through ::part() from the light DOM wins
		 * over this one at any specificity, and this is the control's own structure rather than a
		 * style choice.
		 */
		flex-wrap: nowrap !important;
		flex: 1 1 auto;
	}

	.form-control-input {
		display: flex;
		flex-direction: column;
		flex: 1 1 auto;
		/*
		 * An editor wants 40em, but never more than it has been given: a plain minimum makes it
		 * overflow its container on a phone, or in any layout that puts it in a narrow column, and
		 * the toolbar then sits off the side of the screen where it cannot be reached.
		 */
		min-width: min(40em, 100%);
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
