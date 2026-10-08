/**
 * Sharable date styles constant
 */

import {css} from "lit";
import {colorsDefStyles} from "../Styles/colorsDefStyles";

export const dateStyles = [
	colorsDefStyles,
	css`
		:host {
			display: block;
			white-space: nowrap;
			min-width: fit-content;
			/* LitFlatpickr's own :host sets a white background and black text, follow the surroundings instead */
			background-color: transparent;
			color: inherit;
			/* ... and a hand cursor over everything, the label included */
			cursor: default;
		}

		/* LitFlatpickr makes everything slotted into it a hand as well, and the label text is slotted */
		::slotted(*) {
			cursor: default;
		}

		/* Size variants */

		.form-control--small {
			font-size: var(--sl-input-label-font-size-small);
		}

		.form-control--medium {
			font-size: var(--sl-input-label-font-size-medium);
		}

		.form-control--large {
			font-size: var(--sl-input-label-font-size-large);
		}

		/* Style input directly for mobile */

		.form-control-input input[type*=date]:only-child {
			font-size: var(--sl-input-font-size-large);
			height: var(--sl-input-height-large);
			line-height: var(--sl-input-height-large);
			border: var(--sl-input-border-width) solid var(--sl-input-border-color);
			border-radius: var(--sl-input-border-radius-medium);
			padding: 0 var(--sl-input-spacing-medium);
			width: 10em;
			flex-grow: 1;
		}

		.overdue {
			color: red; // var(--whatever the theme color)
		}

		input[type="date"] {
			padding: 0 var(--sl-input-spacing-medium);
		}
`];