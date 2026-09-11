import {css} from "lit";

export default css`
	:host {
		--header-spacing: var(--sl-spacing-medium);
		--body-spacing: var(--sl-spacing-medium);
	    --width: auto;
	}
	.dialog__panel {
		border: 1px solid silver;
		box-shadow: -2px 1px 9px 3px var(--sl-color-gray-400);
		min-width: 250px;
		touch-action: none;
	}
	.dialog__header {
		display: flex;
	    			border-bottom: 1px inset;
	}
	.dialog__title {
		font-size: var(--sl-font-size-medium);
		font-weight: bold;
		user-select: none;
		overflow: hidden;
	}

	.dialog__header-actions {
		align-content: center;
	}
	.dialog__close {
		padding: 0;
		order: 99;
		border-top-right-radius: calc(var(--sl-border-radius-medium) * .5);
	}
	.dialog__footer	{
		--footer-spacing: 5px;
		display: flex;
		flex-wrap: nowrap;
		justify-content: flex-start;
		align-items: stretch;
		gap: 5px;
		border-top: 1px solid var(--sl-color-gray-400);
		margin-top: 0.5em;
	}

	::slotted(.dialog_content) {
		height: var(--height, 100%);
	}
	          ::slotted(.dialog_content:not(.dialog--has_template):not(form.et2_container)) {
	              white-space: pre-wrap;
	          }

	/* Non-modal dialogs don't have an overlay */

	:host(:not([ismodal])) .dialog, :host(:not([isModal])) .dialog__overlay {
	pointer-events: none;
	background: transparent;
	}

	:host(:not([ismodal])) .dialog__panel {
	pointer-events: auto;
	}

	/* Hide close button when set */

	:host([noclosebutton]) .dialog__close {
	display: none;
	}

	/* Button alignments */

	::slotted([align="left"]) {
	margin-right: auto;
	order: -1;
	}

	::slotted([align="right"]) {
	margin-left: auto;
	order: 1;
	}
`;
