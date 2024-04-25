import {css} from 'lit';

export default css`
	:host {
		display: block;
		width: 100vw;
		height: 100vh;
		position: relative;

		--icon-size: 32px;
	}

	.egw_fw__layout-default {
		display: grid;
		gap: 0.5em 0.1em;
		border: 1px dotted;

		width: 100%;
		height: 100%;
	}

	.egw_fw__layout-default > * {
		position: relative;
		display: flex;
	}

	.egw_fw__layout-default .egw_fw__banner {
		grid-area: banner;
		grid-column-start: banner-start;
		grid-column-end: banner-end;
	}

	.egw_fw__layout-default .egw_fw__header {
		grid-area: header;
		align-items: center;
	}

	/* To use the sl-split-panel, we need it to have its own space & nest stuff inside */

	.egw_fw__layout-default .egw_fw__divider {
		grid-column-start: sidemenu-start;
		grid-column-end: status-start;
		grid-row-start: main-header;
		grid-row-end: footer;
		display: flex;
		justify-content: stretch;
	}

	.egw_fw__layout-default sl-split-panel {
		width: 100%;
	}

	.egw_fw__layout-default sl-split-panel::part(divider) {
		color: var(--sl-color-primary-500);
	}

	.egw_fw__layout-default .egw_fw__sidemenu {
		overflow-x: hidden;
		overflow-y: auto;
	}

	.egw_fw__layout-default .egw_fw__status {
		overflow-x: hidden;
		overflow-y: auto;
	}

	.egw_fw__layout-default .egw_fw__main-wrapper {
		width: 100%;
		display: grid;
		grid-template-columns: [start] 1fr [end];
		grid-template-rows: [top main-header] fit-content(2em) [main] 1fr [main-footer] fit-content(0px) [ bottom]
	}

	.egw_fw__layout-default .egw_fw__main {
		grid-column-start: start;
		grid-column-end: end;
		grid-row-start: main;
		grid-row-end: main;
		overflow: hidden;
		overflow-x: auto;
	}

	.egw_fw__layout-default .egw_fw__main-header {
		grid-column-start: start;
		grid-column-end: end;
		grid-row-start: main-header;
		grid-row-end: main-header
	}

	.egw_fw__layout-default .egw_fw__main-footer {
		grid-column-start: start;
		grid-column-end: end;
		grid-row-start: main-footer;
		grid-row-end: main-footer
	}

	.egw_fw__layout-default .egw_fw__footer {
		grid-area: footer;
	}


	@media (min-width: 500px) {
		.egw_fw__layout-default {
			grid-template-columns: [start sidemenu-start banner-start header-start footer-start] 200px [sidemenu-end main-start] 1fr [main-end] fit-content(2em) [header-end banner-end end];
			grid-template-rows: [top banner] fit-content(2em) [header] fit-content(2em) [ main-header] fit-content(2em) [main] 1fr [main-footer] fit-content(2em) [footer bottom] fit-content(2em);
		}
	}

	/* Actual styles */

	.egw_fw__header sl-icon-button {
		color: inherit;
	}

	.egw_fw__app_list::part(panel) {
		display: grid;
		grid-template-columns: repeat(5, 1fr);
		background-color: var(--sl-color-neutral-0);
		font-size: var(--icon-size);
	}

	.egw_fw__open_applications et2-image {
		height: var(--icon-size);
		width: var(--icon-size);
	}

	.egw_fw__open_applications sl-tab::part(base) {
		padding: 0px;
		font-size: var(--icon-size);
	}

	.egw_fw__open_applications sl-tab::part(close-button) {
		visibility: hidden;
		margin-inline-start: var(--sl-spacing-2x-small);
		color: var(--sl-color-neutral-100);
	}

	.egw_fw__open_applications sl-tab et2-image {
		padding: var(--sl-spacing-small) var(--sl-spacing-3x-small);
	}

	.egw_fw__open_applications sl-tab:hover::part(close-button) {
		visibility: visible;
	}
`