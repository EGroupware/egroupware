import {css} from 'lit';

/**
 * The AI button itself - wherever it ends up, in our own corner or in the target's field controls
 * (see Et2Ai._adoptFieldControls()), so it is the same size in both.
 */
export const triggerStyles = css`
	.et2-ai-trigger {
		font-size: calc(var(--sl-font-size-large) * 1.5);

		&::part(base) {
			padding: 1px;
		}
	}
`;

export default css`
	:host {
		--aitools-color: var(--sl-color-blue-500);
		display: block;
		width: 100%;
		height: 100%;
	}

	/* Stay visible or else slotted content will hide too */

	:host([disabled]) {
		display: block;
	}
	/* Slotted items should always fill the whole widget*/
    ::slotted(*){
        flex: 1 1 auto;
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
		top: var(--sl-spacing-2x-small);
		right: var(--sl-spacing-2x-small);
	}


	sl-card, sl-alert {
		position: absolute;
		width: 100%;
		overflow: hidden;
		top: 0;
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
	/* When we wrap a textarea make it use our font*/
	::slotted(textarea) {
		font-family: inherit;
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