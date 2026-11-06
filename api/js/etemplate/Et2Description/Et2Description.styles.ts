import {css} from "lit";

/**
 * Links inside a description (href, activateLinks, markdown) are rendered into the description's light DOM,
 * inside span.description--content, so the shadow styles below can't reach them: ::slotted() only matches
 * direct children.  These rules are put into whichever document or shadow root the description is in
 * (see Et2Description.addLinkStyles()), :where() keeps them at zero specificity so any app rule still wins.
 */
export const linkStyles = css`
	:where(et2-description, et2-label) a {
		cursor: pointer;
		color: var(--sl-color-primary-700);
		text-decoration: none;
	}
`;

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

	/* Like an editable field, the value drops below the label when the two no longer fit side by side */
	:host(.et2-label-fixed) {
		flex-wrap: wrap;
	}

	/*
	 * The label slot is display: contents, so the width .et2-label-fixed puts on the label part has no box to
	 * apply to.  Give the slot one, so a read-only value lines up with the editable fields around it.
	 */
	:host(.et2-label-fixed) slot[part="form-control-label"]:not(.split-label) {
		display: flex;
		align-items: center;
		/*
		 * A labelled read-only value takes up the same row height as the input it stands in for.  On the label
		 * rather than the host: a layout's own min-height: 0 on its children outranks :host rules.
		 */
		min-height: var(--sl-input-height-medium);
		/* Same gap an editable field leaves after its label, so the values start in the same place */
		margin-right: var(--sl-spacing-medium);
	}
	::slotted(a) {
		cursor: pointer;
		color: var(--sl-color-primary-700);
		text-decoration: none;
	  	display: inherit;
	}
`;
