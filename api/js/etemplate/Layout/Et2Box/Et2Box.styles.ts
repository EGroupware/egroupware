import {css} from "lit";

export default css`
	:host {
	display: block;
	}
	:host > div {
	display: flex;
	flex-wrap: nowrap;
	justify-content: flex-start;
	align-items: stretch;
	height: 100%;
	}
	:host([align="right"]) > div {
	justify-content: flex-end;
	}
	:host([align="left"]) > div {
	justify-content: flex-start;
	}
	:host([align="center"]) > div {
	justify-content: center;
	}
	/* CSS for child elements */
	::slotted(*) {
	flex: 1 1 auto;
	}
	::slotted(img),::slotted(et2-image) {
	/* Stop images from growing.  In general we want them to stay */
	flex-grow: 0;
	}
	::slotted([align="left"]) {
	margin-right: auto;
	order: -1;
	}
	::slotted([align="right"]) {
	margin-left: auto;
	order: 1;
	text-align: initial;
	}

	/* work around for chromium print bug, see render() */
	:host > .no-print-gap {
	gap: 0px;
	}
`;

/** Et2VBox stacks its children vertically, overriding the shared row layout above. */
export const vbox = css`
	:host > div {
	flex-direction: column;
	}

	:host([align="center"]) > div {
	align-items: center;
	}

	/* CSS for child elements */

	::slotted(*) {
	/* Stop children from growing vertically.  In general we want them to stay their "normal" height */
	flex-grow: 0;
	}
`;
