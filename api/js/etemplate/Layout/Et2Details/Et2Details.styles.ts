import {css} from "lit";

export default css`
	:host {
		display: block;
	}
	:host([align="right"]) > div {
		justify-content: flex-end;
	}

	:host([align="left"]) > div {
		justify-content: flex-start;
	}

	/* CSS for child elements */

	::slotted(*) {
		flex: 1 1 auto;
	}

	::slotted(img), ::slotted(et2-image) {
		/* Stop images from growing.  In general we want them to stay */
		flex-grow: 0;
	}

	::slotted([align="left"]) {
		margin-right: auto;
		order: -1;
	}

	::slotted([align="right"]) {
		margin-left: auto;
		order: 1;
	}

	.details {
		border: var(--sl-panel-border-width) solid var(--sl-panel-border-color);
		margin: 0px;
		overflow: hidden;
		height: 100%;
		display: flex;
		flex-direction: column;
	}

	.details__content {
		height: 100%;
		min-height: 1px;
		overflow-y: auto;
	}

	.details.hoist {
		position: relative;
		overflow: visible;
	}

	.details__body {
		display: none;
	}

	.details--open .details__body {
		display: block;
		flex: 1 1 auto;
	}

	.details:not(.hoist).details--open.details--overlay-summary {
		.details__summary {
			visibility: hidden;
		}

		.details__body {
			margin-top: calc(-1 * var(--sl-input-height-medium));
		}

		.details__body.overlaySummaryRightAligned {
			padding-right: calc(3 * var(--sl-spacing-medium));
		}

		.details__body.overlaySummaryLeftAligned {
			padding-left: calc(3 * var(--sl-spacing-medium));
		}
	}

	.details.hoist .details__body {
		position: absolute;
		z-index: var(--sl-z-index-drawer);
		background: var(--sl-color-neutral-0);
		box-shadow: var(--sl-shadow-large);
		width: 100%;
		min-width: fit-content;
		border-radius: var(--sl-border-radius-small);
		border: var(--sl-panel-border-width) solid var(--sl-panel-border-color);
		max-height: 15em;
		overflow-y: auto;
	}

	.details.hoist .details__body.overlaySummaryLeftAligned {
		top: 0;
		left: 2em;
		width: calc(100% - 2em);
	}

	.details.hoist .details__body.overlaySummaryRightAligned {
		top: 0;
	}

	.details__summary-icon--left-aligned {
		order: -1;
	}
`;
