/**
 * Sharable date styles constant
 */

import {css} from "@lion/core";

export const dateStyles = css`
	:host {
		display: inline-block;
		white-space: nowrap;
		min-width: 20ex;
	}
	.overdue {
		color: red; // var(--whatever the theme color)
	}
`;