import {css} from 'lit';

export default css`
	:host {
		flex: 0 0;
	}

	et2-dialog::part(body) {
		display: flex;
		flex-direction: column;
	}

	.et2_toolbar {
		display: flex;
		gap: 1ex;
	}

	.et2_toolbar::slotted(*) {
		flex: 1 1 auto;
	}

	.et2_toolbar::slotted(et2-button) {
		flex-grow: 0;
	}

	.search__results {
		flex: 2 1 auto;
		min-height: 15em;
		overflow-y: auto;
	}

	.search__results .search__empty {
		height: 50%;
		min-height: 5em;
		min-width: 20em;
		display: flex;
		flex-direction: column;
		align-items: center;
		filter: contrast(0.1);
		user-select: none;
	}

	.search__results .search__empty et2-image {
		margin-top: auto;
	}

	.vfs_select__file_row {
		display: table-row;
	}

	.search__results .search__loading {
		text-align: center;
		line-height: 15em; // 3 * listbox min height
	}

	.search__results sl-spinner {
		font-size: 4rem;
	}

	.search__results .search__more {
		text-align: center;
	}

	.vfs_select__mimefilter {
		flex: 0 0;
	}

	:host::part(form-control-help-text) {
		flex-basis: min-content !important;
	}
`;