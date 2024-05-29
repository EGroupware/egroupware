import {css} from 'lit';

export default css`

	/* Layout */

	:host {
		position: relative;
		width: 100%;
		height: 100%;

		display: flex;
		flex-direction: column;

		--application-color: var(--primary-background-color);
	}

	:host > * {
		position: relative;
		display: flex;
	}

	.egw_fw_app__name {
		max-width: 20vw;
		flex: 1 1 20vw;
	}

	.egw_fw_app__name h2 {
		margin-inline-start: var(--sl-spacing-medium);
	}

	.egw_fw_app__header {
		justify-content: flex-start;
		align-items: center;
		justify-items: stretch;
		flex: 1 0 2em;
		max-height: 3em;
	}

	.egw_fw_app__outerSplit {
		grid-column: start / end;
		grid-row: start / end;
		grid-template-rows: subgrid;
		--min: var(--left-min, 0px);
		--max: var(--left-max, 20%);
	}

	.egw_fw_app__innerSplit {
		grid-template-rows: subgrid;
		grid-column-end: -1;
		grid-row: start / end;
		--max: calc(100% - var(--right-min, 0px));
		--min: calc(100% - var(--right-max, 50%));
	}

	.egw_fw_app__innerSplit.no-content {
		--min: 100%;
	}

	sl-split-panel::part(divider) {
		grid-row: start / end;
		font-size: var(--sl-font-size-medium);
	}

	sl-split-panel > sl-icon {
		position: absolute;
		border-radius: var(--sl-border-radius-small);
		background-color: var(--application-color);
		color: var(--sl-color-neutral-0);
		padding: 0.5rem 0.125rem;
		z-index: var(--sl-z-index-drawer);
	}

	sl-split-panel.no-content {
		--divider-width: 0px;
	}

	sl-split-panel.no-content::part(divider) {
		display: none;
	}

	.egw_fw_app__aside {
		overflow-x: hidden;
		overflow-y: auto;
		display: grid;
		grid-template-rows: subgrid;
		grid-row: start / end;
	}


	.egw_fw_app__aside_header {
		grid-row: sub-header / main;
	}

	.egw_fw_app__aside_content {
		height: 100%;
		grid-row: main / footer;
	}

	.egw_fw_app__aside_footer {
		grid-row: footer / end;
	}

	.egw_fw_app__main {
		flex: 1 1 100%;
		display: grid;
		align-items: stretch;
		justify-content: stretch;
		overflow: hidden;
	}
	.egw_fw_app__header {
		grid-row: sub-header / main;
	}

	.egw_fw_app__main_content {
		grid-row: main / footer;
	}

	.egw_fw_app__footer {
		grid-row: footer / end;
	}

	.header, .footer {
		overflow: hidden;
	}

	.egw_fw_app__loading {
		text-align: center;

		sl-spinner {
			--track-width: 1rem;
			font-size: 10rem;
			--indicator-color: var(--application-color, var(--primary-background-color, var(--sl-color-primary-600)));
		}
	}

	@media (min-width: 600px) {
		.egw_fw_app__main {
			grid-template-columns: [start left] min-content [ main] 1fr [right] min-content [end];
			grid-template-rows: [start sub-header] fit-content(2em) [main] auto [footer] fit-content(4em) [end];
		}

		.egw_fw_app__aside {
			overflow-y: hidden;
		}

		.egw_fw_app__aside_content, egw_fw_app__main_content {
			overflow-x: hidden;
			overflow-y: auto;
		}

		.egw_fw_app__main_content {
			display: flex;
			flex-direction: column;
			align-items: stretch;
		}

		::slotted(*) {
			flex: 1 1 auto;
		}

		::slotted(iframe) {
			width: 100%;
		}
	}
	@media (max-width: 599px) {
		.egw_fw_app__main {
			grid-template-columns: [start left main-start] fit-content(50%)  [right] auto [main-end end];
			grid-template-rows: 
				[start sub-header] fit-content(2em) 
				[main] auto 
				[aside-header] fit-content(2em) 
				[aside] min-content 
				[aside-footer] fit-content(4em) 
				[footer] fit-content(4em) 
				[end];
		}

		sl-split-panel {
			display: contents
		}

		sl-split-panel::part(divider) {
			display: none;
		}

		.egw_fw_app__header {
			grid-column: start / end;
		}
		.egw_fw_app__main_content {
			grid-row: main / aside-header;
			grid-column: start / end;
		}

		.egw_fw_app__aside {
			grid-row: aside-header / footer;
		}

		.egw_fw_app__aside_header {
			grid-row: aside-header / aside;
		}

		.egw_fw_app__aside_content {
			grid-row: aside / aside-footer;
		}

		.egw_fw_app__aside_footer {
			grid-row: aside-footer / footer;
		}

		.egw_fw_app__footer {
			grid-column: start / end;
		}
	}


`