import {css} from "lit";

export default css`
	:host {
		min-width: 15em;
	}

	et2-image[src="trash"] {
		display: none;
	}
	[part="menu"]{
		padding: 0;
	}

	sl-menu-item:hover et2-image[src="trash"] {
		display: initial;
	}

	sl-menu-item[active] {
		background-color: var(--highlight-background-color);
	}
	sl-menu-item::part(submenu-icon), sl-menu-item::part(checked-icon){
		width: 0;
	}
	sl-menu-item::part(base){
	    padding-block: 0;
	}

	[slot="prefix"]:not([name="plus"]){
		color: var(--sl-color-neutral-500);
	}
`;
