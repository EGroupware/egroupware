import {css} from 'lit';

export default css`
	:host {
		--aitools-color: var(--sl-color-blue-500);
	}

	.et2-ai {
		width: 100%;
		height: 100%;
		position: relative;
		align-items: stretch;
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
		left: 7px; /* Not sure where this comes from, but without this the left side is cut off and there's too much space to the right*/
		z-index: var(--sl-z-index-dialog);
		box-shadow: var(--sl-shadow-large);
		--padding: var(--sl-spacing-small);

		&::part(base) {
			max-height: var(--max-result-height);
			--border-color: var(--aitools-color);
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
		.et2-ai-translation {
			display: flex;
			align-items: center;

			> * {
				flex-grow: 0;
			}

			et2-image {
				margin: 0 var(--sl-spacing-medium);
			}
		}
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

	/* Special stuff for when we wrap an htmlarea */

	.tox, ::slotted(.tox) {
		height: 100% !important;
	}

	.et2-ai--has-html-target {
		/* We hide the normal dropdown, activate it through toolbar */

		.et2-ai-dropdown {
			display: none;
		}

		sl-card, sl-alert {
			left: 0px; /* Extra space not needed for html target*/
		}
	}
`;