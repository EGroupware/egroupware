import {css} from 'lit';

export default css`

	/* Layout */

	:host {
		position: relative;
		width: 100%;
		height: 100%;

		display: flex;
		flex-direction: column;
	}

	:host > * {
		position: relative;
		display: flex;
	}

	.egw_fw_app__name {
		max-width: 20vw;
		flex: 1 1 20vw;
	}

	.egw_fw_app__header {
		justify-content: flex-start;
		align-items: center;
		justify-items: stretch;
		flex: 1 0 2em;
		max-height: 3em;
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

	.egw_fw_app__left {
		grid-column: left / left;
	}

	.egw_fw_app__right {
		grid-column: right / right;
	}

	.egw_fw_app__main {
		flex: 1 1 100%;
		display: grid;
		align-items: stretch;
		justify-content: stretch;
		overflow: hidden;
		overflow-x: auto;
	}
	.egw_fw_app__header {
		grid-row: sub-header / main;
		grid-column: main / main;
	}

	.egw_fw_app__main_content {
		grid-row: main / footer;
		grid-column: main / main;
	}

	.egw_fw_app__footer {
		grid-column: main / right;
		grid-row: footer / end;
	}

	@media (min-width: 600px) {
		.egw_fw_app__main {
			grid-template-columns: [start left] fit-content(20%)  [main] 1fr [right] fit-content(50%) [end];
			grid-template-rows: [start sub-header] fit-content(2em) [main] auto [footer] fit-content(2em) [end];
		}

		::slotted(*) {
			height: 100%;
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