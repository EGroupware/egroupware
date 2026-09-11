import {css} from "lit";

export default css`
	.form-control {
		display: flex;
		align-items: center;
		flex-wrap: wrap;
	}

	.form-control-input {
		display: flex;
		flex: 1 1 auto;
		position: relative;
		max-width: 100%;
	                border: solid var(--sl-input-border-width) var(--sl-input-border-color);
	                border-radius: var(--sl-input-border-radius-medium);
	                &:hover{
	                    border-color: var(--sl-input-border-color-hover);
	                }
	}
	et2-link-apps::part(combobox){
		border:none;
	}
`;
