import {css} from "lit";

export default css`
	:host {

		height: 100%;
		/*
		width: 100%;

		min-height: fit-content;
		min-width: fit-content;
		*/
	}
	.tab-panel {
		height: 100%;
	}
	::slotted(*) {
		height: 100%;
	}
`;
