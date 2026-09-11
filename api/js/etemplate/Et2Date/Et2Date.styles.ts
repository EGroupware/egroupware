import {css} from "lit";

export default css`
	  :host {
		width: auto;
	  }

		/* Scroll buttons */
		.form-control-input {
			position: relative;
			display: flex;

			et2-textbox {
				flex: 1 1 auto;
				min-width: 14ex;
			}
		}

		.form-control-input:hover .et2-date-time__scrollbuttons {
		display: flex;
	  }

	  .et2-date-time__scrollbuttons {
		display: none;
		flex-direction: column;
		width: calc(var(--sl-input-height-medium) / 2);
		position: absolute;
		right: 0px;
		  margin-inline-end: 0px;
	  }

	  .et2-date-time__scrollbuttons > * {
		font-size: var(--sl-font-size-2x-small);
		height: calc(var(--sl-input-height-medium) / 2);
	  }
	.et2-date-time__scrollbuttons > *::part(base) {
		padding: 3px;
	}
`;
