import {css} from 'lit';

export default css`
	:host {
		display: block;
		width: 100vw;
		height: 100vh;
		position: relative;

		--icon-size: 32px;
		--inactive-tab-opacity: 0.5
	}

	.egw_fw__layout-default {
		display: grid;

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

	.egw_fw__layout-default .egw_fw__header > * {
		flex: 1 1 auto;
	}

	/* To use the sl-split-panel, we need it to have its own space & nest stuff inside */

	.egw_fw__layout-default .egw_fw__divider {
		max-width: 100vw;
		grid-column-start: start;
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

	.egw_fw__layout-default .egw_fw__footer {
		grid-area: footer;
	}


	@media (min-width: 600px) {
		.egw_fw__main {
			overflow: hidden;
		}
		.egw_fw__layout-default {
			grid-template-columns: [start banner-start header-start footer-start main-start] 1fr [main-end header-end banner-end end];
			grid-template-rows: [top banner] fit-content(2em) [header] fit-content(2em) [main-header] fit-content(2em) [main] 1fr [footer bottom] fit-content(2em);
		}
	}
	@media (max-width: 599px) {
		.egw_fw__layout-default {
			grid-template-columns: [start banner-start header-start footer-start main-start] 1fr [main-end header-end banner-end end];
			grid-template-rows: [top banner] fit-content(2em) [header] fit-content(2em) [main-header] fit-content(2em) [main] 1fr [footer bottom] fit-content(2em);
		}

		::slotted([slot="logo"]) {
			display: none;
		}
	}

	/* Actual styles */

	.egw_fw__header sl-icon-button {
		color: inherit;
	}

	.egw_fw__header .egw_fw__app_list {
		flex: none;
	}
	.egw_fw__header .spacer {
        flex-shrink: 0;
        flex-grow: 6;
        flex-basis: 0;
    }
	
	.egw_fw__app_list::part(panel) {
		display: grid;
		grid-template-columns: repeat(5, 1fr);
		background-color: var(--sl-color-neutral-0);
		font-size: var(--icon-size);
	}

	.egw_fw__app_list img {
		height: var(--icon-size);
		width: var(--icon-size);
	}

	.egw_fw__open_applications {
		--track-width: 0px;
	}

	.egw_fw__open_applications::part(tabs) {
		align-items: baseline;
	}

    .egw_fw__open_applications sl-tab {
        width: auto;
        flex: 1 1 auto;
    }

    .egw_fw__open_applications sl-tab:last-of-type {
        flex: 0 0 auto;
    }
	/*make non active tabs a little transparent*/
	.egw_fw__open_applications sl-tab:not([active]){
		opacity: var(--inactive-tab-opacity);
	}
	
	.egw_fw__open_applications et2-image {
		height: var(--tab-icon-size, 32px);
		
		/* Always force icons to be the same size */
		min-width: calc(var(--tab-icon-size, 32px));
		min-height: calc(var(--tab-icon-size, 32px)); 
		/* Prevent large icons from causing problems */
		max-width: var(--tab-icon-size, 32px);
		max-height: var(--tab-icon-size, 32px);
	}
	
	.egw_fw__open_applications sl-tab::part(base) {
		padding: 0px;
		font-size: var(--tab-icon-size);
	}

	.egw_fw__open_applications sl-tab::part(close-button) {
		visibility: hidden;
		margin-inline-start: var(--sl-spacing-2x-small);
		color: var(--sl-color-neutral-900);
	}
	
	.egw_fw__open_applications sl-tab et2-image { 
		/*align items centered on round app colored background*/
		padding: var(--sl-spacing-2x-small);
        background-color: var(--application-color, var(--default-color, var(--sl-color-neutral-600)));
        border-radius: var(--sl-border-radius-circle);
		text-align: center;
        line-height: 105%;
    } 
	.egw_fw__open_applications sl-tab et2-image *[part="image"] {
        /*turn all app icons white*/
        filter: brightness(0) invert(1);
    } 
	.egw_fw__open_applications sl-tab:hover::part(close-button) {
		visibility: visible;
	}

	::slotted(egw-app) {
		display: none;
	}

	::slotted(egw-app[active]) {
		display: flex;
	}
`