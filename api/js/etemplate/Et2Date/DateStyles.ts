/**
 * Sharable date styles constant
 */

import {css} from "@lion/core";
import {colorsDefStyles} from "../Styles/colorsDefStyles";
import {cssImage} from "../Et2Widget/Et2Widget";

export const dateStyles = [
	colorsDefStyles,
	css`
	:host {
		display: block;
		white-space: nowrap;
		min-width: fit-content;
	}
	.overdue {
		color: red; // var(--whatever the theme color)
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