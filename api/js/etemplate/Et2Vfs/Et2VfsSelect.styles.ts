import {css} from 'lit';

export default css`
	:host {
		flex: 0 0;
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
		height: 100%;
		min-height: 5em;
		display: flex;
		flex-direction: column;
		align-items: center;
		filter: contrast(0.1);
		user-select: none;
	}

	.vfs_select__listbox .vfs_select__empty et2-image {
		margin-top: auto;
	}
`;