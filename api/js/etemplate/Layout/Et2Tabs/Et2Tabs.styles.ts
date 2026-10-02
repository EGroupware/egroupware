import {css} from "lit";

export default css`
		/*scroll detection detect if scrollbar is available scroll detection only works in chromium not in Firefox or Safari*/
		@keyframes detect-scroll {
			from, to { --can-scroll:0;}
		}
	.tab-group--top {
		height: 100%;
		min-height: fit-content;
	}
	.tab-group__body {
		flex: 1 1 auto;
		overflow: hidden auto;
	}
	.tab-group__body-fixed-height {
		flex: 0 0 auto;
	}
	::slotted([hidden]) {
		display: none;
	}
	::slotted(et2-tab-panel) {
		flex: 1 1 auto;
	}
	::slotted(et2-tab-panel:not([active])) {
				display: none;
	}

		:host([tabheight]) {
			overflow: hidden;

			.tab-group {
				min-height: initial;
			}
		}

	/* beforePrint() shows every tab's panel, so no height limit (the inline height comes from tabHeight) */
	:host([printing]) {
		overflow: visible;
	}
	:host([printing]) .tab-group--top {
		height: auto;
	}
	:host([printing]) .tab-group__body {
		height: auto !important;
		overflow: visible;
	}
	/* Each panel prints its tab label as heading instead, see Et2TabPanel */
	@media print {
		:host([printing]) .tab-group__nav-container {
			display: none;
		}
	}
`;
