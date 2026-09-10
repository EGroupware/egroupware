import {css} from "lit";

export default css`
	:host {
		--icon-width: 20px;
		display: inline-block;
		min-width: 64px;
	}
	:host(.app-icons) {
		max-width: 75px;
	}
	.select__menu {
		overflow-x: hidden;
	}
	::part(control) {
		border: none;
		box-shadow: initial;
	}
`;
