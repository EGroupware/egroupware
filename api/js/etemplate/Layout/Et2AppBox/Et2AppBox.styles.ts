import {css} from "lit";

export default css`
	:host {
		display: block;
		height: 100%;
	}

	.et2_appbox {
		display: flex;
		flex-direction: column;
		height: 100%;
		min-height: 0;
	}

	.et2_appbox__header {
		display: grid;
		grid-template-columns: minmax(0, 1fr) auto;
		gap: var(--sl-spacing-x-small, 0.25rem);
		align-items: center;
		color: var(--application-header-text-color);
		background-color: var(--application-color);
	}

	.et2_appbox__body {
		display: grid;
		grid-template-columns: minmax(0, auto) minmax(0, 1fr) minmax(0, auto);
		flex: 1 1 auto;
		min-height: 0;
	}

	.et2_appbox__side {
		min-width: 0;
	}

	.et2_appbox__center {
		display: flex;
		flex-direction: column;
		min-width: 0;
		min-height: 0;
	}

	.et2_appbox__content {
		flex: 1 1 auto;
		min-height: 0;
	}

	.egw_fw_app__loading {
		position: absolute;
		inset: 0;
		display: grid;
		place-items: center;
		background: color-mix(in srgb, var(--sl-color-neutral-0), transparent 45%);
		z-index: 1;
		pointer-events: none;
	}
`;
