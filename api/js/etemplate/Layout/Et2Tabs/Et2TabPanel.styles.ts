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

	/* Et2Tabs.beforePrint() shows all panels, each headed by its tab label, one below the other: each
	 * takes its own height then, 100% would make every one as high as the whole tabbox */
	:host([data-print-label]),
	:host([data-print-label]) .tab-panel,
	:host([data-print-label]) ::slotted(*) {
		height: auto;
	}
	@media print {
		:host([data-print-label])::before {
			content: attr(data-print-label);
			display: block;
			margin-block: var(--sl-spacing-medium) var(--sl-spacing-x-small);
			border-bottom: 1px solid var(--sl-color-neutral-300);
			font-size: var(--sl-font-size-medium);
			font-weight: var(--sl-font-weight-semibold);
			break-after: avoid;
		}
	}
`;
