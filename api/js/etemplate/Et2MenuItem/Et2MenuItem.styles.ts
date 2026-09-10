import {css} from "lit";

export default css`
	/* Et2Widget hides anything disabled, but a disabled menu item has to stay visible and
	   greyed out - being unavailable is the information the menu is conveying.  Gone is
	   what [hidden] is for, so the two are split here:  [disabled] undoes the inherited
	   rule, [hidden] follows it.  [hidden] comes second on purpose - equal specificity,
	   so source order is what makes it win when an item is both. */
	:host([disabled]) {
		display: block;
	}

	/* Not the browser's [hidden] rule:  that is a user-agent style, and sl-menu-item's
	   own :host {display: block} is author-level, so it would otherwise win. */
	:host([hidden]) {
		display: none;
	}

	sl-popup::part(popup){
		border: none;
		border-radius: var(--sl-border-radius-medium) /*this is the radius sl-menu-item uses */
	}
`;
