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
	::slotted(a) {
		cursor: pointer;
		color: var(--sl-color-primary-700);
		text-decoration: none;
	  	display: inherit;
	}
`;
