/**
 * Sharable date styles constant
 */

import {css} from "lit";
import {colorsDefStyles} from "../Styles/colorsDefStyles";
import {cssImage} from "../Et2Widget/Et2Widget";

export const dateStyles = [
	colorsDefStyles,
	css`
		:host {
			display: block;
			white-space: nowrap;
			min-width: fit-content;
			background-color: transparent;
			color: var(--sl-color-neutral-950);
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

		input.flatpickr {
			border: 1px solid;
			border-color: var(--input-border-color);
			color: var(--input-text-color);
			padding-top: 4px;
			padding-bottom: 4px;
			flex: 1 1 auto;
		}

		input.flatpickr:hover {
			background-image: ${cssImage("datepopup")};
			background-repeat: no-repeat;
			background-position-x: right;
			background-position-y: 1px;
			background-size: 18px;
		}
`];