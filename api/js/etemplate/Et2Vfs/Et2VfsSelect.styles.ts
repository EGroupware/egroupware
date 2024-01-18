import {css} from 'lit';

export default css`
	:host {
		flex: 0 0;
	}

	et2-dialog::part(panel) {
		height: 40em;
	}
	et2-dialog::part(body) {
		display: flex;
		flex-direction: column;
	}

	.vfs_select__listbox {
		flex: 1 1 auto;
		min-height: 15em;
		overflow-y: auto;
	}

	.vfs_select__listbox .vfs_select__empty {
		height: 50%;
		min-height: 5em;
		min-width: 20em;
		display: flex;
		flex-direction: column;
		align-items: center;
		filter: contrast(0.1);
		user-select: none;
	}

	.vfs_select__file_row {
		display: table-row;
	}

	.vfs_select__listbox .vfs_select__loading {
		text-align: center;
		line-height: 15em; // 3 * listbox min height
	}

	.vfs_select__listbox sl-spinner {
		font-size: 4rem;
	}
	.vfs_select__listbox .vfs_select__empty et2-image {
		margin-top: auto;
	}

	.vfs_select__mimefilter {
		flex: 0 0;
	}
`;