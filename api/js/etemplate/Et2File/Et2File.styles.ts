import {css} from 'lit';

export default css`
	:host {
		display: flex;
		overflow: hidden;
	}

	:host([loading]) .file__button et2-image {
		display: none;
	}

	:host([readonly]) .file__button {
		display: none;
	}
	.file {
		width: 100%;
	}
	.file--single > div {
		display: flex;
		flex-direction: row;
		flex-wrap: nowrap;
	}

	/**
	 * The "list" slot's own assigned et2-file-item(s) must stack vertically regardless of the
	 * row layout above - et2-file-item's :host is display:contents, so without this its
	 * file-item boxes flatten straight into the surrounding row and end up side-by-side instead
	 * of as a list, whenever more than one is slotted in (eg. display="list" without multiple).
	 */
	slot[name="list"] {
		display: flex;
		flex-direction: column;
	}

	.file__file-list {
		width: 100%;
		max-width: calc(100vw - var(--sl-spacing-large));
		max-height: calc(100% - var(--sl-input-height-medium));
		overflow-y: auto;
	}
	.file__file-list::part(popup) {
		min-width: 25em;
		background-color: var(--sl-panel-background-color);
		overflow-y: auto;
		z-index: 100;
	}


	/**
	 * Single display (multiple=false) match height
	 * (multiple or readonly look weird with this, so don't change them)
	 */

	.file--single et2-file-item[display="small"]::part(base) {
		height: 100%;
	}

	/**
	 * Single (non-popup) list sits inline in the button's own row, whose height
	 * is often less than --sl-input-height-medium (eg. display="small").  The
	 * popup-oriented max-height above then evaluates negative and clamps to 0,
	 * hiding the file entirely, so don't constrain it here.
	 */
	.file--single .file__file-list {
		max-height: none;
	}
`;