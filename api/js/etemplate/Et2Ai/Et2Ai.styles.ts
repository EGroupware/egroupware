import {css} from 'lit';

export default css`
	:host {
		position: relative;
		--aitools-color: var(--sl-color-blue-500);
	}

	.et2-ai {
		width: 100%;
		height: 100%;
		--max-result-height: 3em;

		&:hover .et2-ai-dropdown {
			visibility: visible;
		}
	}

	.et2-ai-dropdown {
		visibility: hidden;
		position: absolute;
		top: 0px;
		right: 0px;

		et2-button-icon {
			font-size: calc(var(--sl-font-size-large) * 1.5);
			position: relative;
			top: calc(var(--sl-font-size-large) * -.5);
			right: calc(var(--sl-font-size-large) * -.4);
		}
	}


	sl-card, sl-alert {
		position: absolute;
		width: 100%;
		overflow: hidden;
		top: 0;
		z-index: var(--sl-z-index-dialog);
		/* box-shadow only below, not on the sides to avoid weird bleed on the sides */
		box-shadow: 0 1em 6px -6px hsla(240, 3.8%, 46.1%, 0.52);
		--padding: var(--sl-spacing-small);

		&::part(base) {
			max-height: var(--max-result-height);
		}

		&::part(header) {
			display: flex;
			align-items: center;
		}

		&::part(body), &::part(message) {
			overflow-y: auto;
		}

		* {
			flex: 1 1 auto;
		}

		et2-hbox[slot="header"] {
			flex-grow: 0
		}
		
		et2-button-icon[name="close"] {
			margin-left: auto;
			flex: 0 0;
		}
	}

	sl-alert {
		display: block;
	}

	.et2-ai-result {
		.et2-ai-result-content.text {
			white-space: pre-wrap;
		}
	}

	@media screen and (max-width: 600px) {
		slot[name="trigger"] > *, ::slotted([slot="trigger"]) {
			position: absolute;
			top: calc(-0.5 * var(--sl-spacing-2x-large));
			
			/* This works well for the current icon */
			left: calc(-1 * var(--sl-spacing-2x-large));
		}

		::slotted([slot="trigger"]) {
			left: calc(-0.5 * var(--sl-spacing-2x-large));
		}
	}
`;