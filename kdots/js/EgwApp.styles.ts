import {css} from 'lit';

export default css`

	/* Layout */

	:host {
		position: relative;
		width: 100%;
		height: 100%;

		display: grid;
	}

	:host > * {
		position: relative;
		display: flex;
	}

	.egw_fw_app__left {
		grid-area: left;
		overflow-x: hidden;
		overflow-y: auto;
	}

	.egw_fw_app__right {
		grid-area: right;
		overflow-x: hidden;
		overflow-y: auto;
	}

	.egw_fw_app__main {
		grid-area: main;
		overflow: hidden;
		overflow-x: auto;
	}

	.egw_fw_app__header {
		grid-area: header;
	}


	.egw_fw_app__footer {
		grid-area: footer;
	}

	@media (min-width: 500px) {
		:host {
			grid-template-columns: [start left] fit-content(20%)  [main] 1fr [right] fit-content(50%) [end];
			grid-template-rows: [header] fit-content(2em)  [main] 1fr  [footer bottom] fit-content(2em) [end];
			grid-template-areas:
				"left-header header right-header"
				"left main right"
				"left-footer footer right-footer"
		}
	}
	@media (max-width: 500px) {
		:host {
			grid-template-areas:
				"header"
				"main"
				"left right"
		}

		[slot="footer"] {
			display: none;
		}
	}
`