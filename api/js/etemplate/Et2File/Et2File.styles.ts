import {css} from 'lit';

export default css`
	:host {
		display: flex;
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

	.file__file-list {
		width: 100%;
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
`;