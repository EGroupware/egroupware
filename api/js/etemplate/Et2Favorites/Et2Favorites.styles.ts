import {css} from "lit";

export default css`
	:host {
	  min-width: 8ex;
	}

	et2-image {
	  display: block;
	  position: relative;
	top: -2px;
	}

	et2-image[src="trash"] {
	display: none;
	}

	sl-menu {
	min-width: 15em;
	}

	sl-menu-item:hover et2-image[src="trash"] {
	display: initial;
	}

	sl-menu-item:last-child::part(base) {
	background-image: none;
	}
`;
