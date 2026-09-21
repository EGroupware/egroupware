import {css} from "lit";

/**
 * Selectors are written against the tag rather than :host because et2-customfields renders
 * into its own light DOM - see lightDomStylesTemplate() in Et2CustomfieldsBase.
 */
export default css`
	et2-customfields {
		display: block;
		container-type: inline-size;
	}

	/*
	 * Tuning knobs, deliberately in :where() so they carry no specificity at all: an app stylesheet,
	 * a template's style= or a wrapping element can set either one and win, whatever the source
	 * order.  This is the supported way to overrule the defaults below without touching this file.
	 */
	:where(et2-customfields) {
		/*
		 * Width of the label column the fields line up in.  Every generated widget carries
		 * et2-label-fixed, which reads this, so an app or template can retune the column by setting
		 * it on the customfields widget rather than per field.
		 */
		--label-width: 12em;

		/*
		 * How wide a column has to be before a grid layout is allowed to start a second one.  A
		 * customfield is a label beside a control and reads best in one column, so this is set far
		 * wider than the 26rem a grid layout would otherwise use: an ordinary edit dialog stays
		 * single-column, and only a full-page dialog is wide enough to split.  Set it to something
		 * huge to pin a template to one column, or drop it to pack more in.
		 */
		--column-min-width: 48rem;
	}

	/*
	 * A dialog that lays itself out already has a label column, and the customfields tab sits
	 * between its header rows and its footer - so ours has to be that same width or the labels step
	 * in and out as the eye travels down the dialog.  Inheriting hands us whatever the dialog uses,
	 * including the width its own fields fall back to when it names none.  Only the label column is
	 * shared: --column-min-width is left alone, so the fields below still stack in one column.
	 */
	:where([layout] et2-customfields) {
		--label-width: inherit;
	}

	/*
	 * One field per row, as the table this replaced gave.  A template asking for a grid layout gets
	 * its columns from kdots' layout rules instead, which target this same element through its
	 * "base" part - so step aside there rather than rely on which selector happens to win.
	 */
	et2-customfields:not([layout="2-column"]):not([layout="edit"]) .customfields {
		display: flex;
		flex-direction: column;
		gap: var(--sl-spacing-2x-small, 0.25rem);
	}

	/*
	 * Too narrow for a label column beside the control - put the label above instead, and move all
	 * three parts together so fields do not each wrap at their own width.  Mirrors what the grid
	 * layouts do in one-column mode.
	 */
	@container (max-width: 30em) {
		et2-customfields:not([layout="2-column"]):not([layout="edit"]) .customfields__field > *::part(form-control-label) {
			width: 100%;
			flex-basis: 100%;
			margin-right: 0;
		}

		et2-customfields:not([layout="2-column"]):not([layout="edit"]) .customfields__field > *::part(form-control-input) {
			flex-basis: 100%;
		}

		et2-customfields:not([layout="2-column"]):not([layout="edit"]) .customfields__field > *::part(form-control-help-text) {
			left: 0;
		}
	}

	et2-customfields .customfields__field {
		min-width: 0;
	}

	et2-customfields .customfields__field > * {
		min-width: 0;
	}

	/*
	 * A customfield is usually one widget, but a filemanager field also gets a button to link an
	 * existing file, and a button field with several label=onclick values gets one button each.
	 * Those belong beside each other - they are all still the one field.  Scoped to fields that
	 * actually have more than one, so a lone widget keeps whatever width it wants.
	 */
	et2-customfields .customfields__field:has(> * + *) {
		display: flex;
		align-items: flex-start;
		gap: var(--sl-spacing-2x-small, 0.25rem);
	}

	/*
	 * et2-htmlarea has a min-height of its own for when it has a menu and toolbar, but that only
	 * stops its inner element shrinking - it does not make the host grow, and the host's own
	 * height:100% resolves against whatever height the row happens to give it.  A definite floor
	 * is not subject to that ambiguity, and mirrors the component's own menu+toolbar minimum.
	 */
	et2-customfields et2-htmlarea,
	et2-customfields[layout] et2-htmlarea {
		min-height: 20em;
	}

	/* A "header" customfield is a caption between the fields, not one of them */
	et2-customfields .et2_customfield_header {
		font-size: 120%;
		font-weight: bold;
	}

	/* Stands in for the label an upload would otherwise render inside its own button, so it has to
	   line up with the label column the rest of the fields use */
	et2-customfields .customfields__caption {
		width: var(--label-width, 8em);
		flex: 0 0 auto;
	}

	/* A filemanager customfield with values.noUpload keeps the widget to show the chosen file,
	   but not the button to choose one */
	et2-customfields et2-vfs-upload.noUpload::part(button) {
		display: none;
	}

	/* Where an upload told to use a fileListTarget lists its files: after both of the field's
	   buttons instead of between them, taking whatever width is left so a filename has room */
	et2-customfields .customfields__file-list {
		flex: 1 1 auto;
		min-width: 0;
	}

	/* That upload is then only its button, so it must not still claim the width its list needed */
	et2-customfields .customfields__field:has(> .customfields__file-list) > et2-vfs-upload {
		flex: 0 0 auto;
	}
`;
