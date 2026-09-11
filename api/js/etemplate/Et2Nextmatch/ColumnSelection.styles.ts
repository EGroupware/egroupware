import {css} from "lit";

export default css`
	:host {
		max-height: inherit;
		min-width: 35em;
		display: flex;
		flex-direction: column;
		flex: 1 1 auto;
		--icon-width: 20px;
	}

	sl-menu {
		flex: 1 10 auto;
		overflow-y: auto;
		max-height: 50em;
	}

	/* Drag handle on columns (not individual custom fields or search letter) */

	sl-menu > .select_row::part(base) {
		padding-left: var(--sl-spacing-x-large);
	}

	.select_row::part(prefix) {
		display: none;
	}

	sl-menu > .column::part(prefix) {
		display: initial;
		position: absolute;
		left: 0px;
		font-size: var(--sl-font-size-large);
		cursor: grab;
	}

	sl-menu-item::part(label), sl-menu-item::part(submenu-icon) {
		cursor: initial;
	}

	/* Change vertical alignment of CF checkbox line to up with title, not middle */

	.custom_fields::part(base) {
		align-items: baseline;
	}
`;
