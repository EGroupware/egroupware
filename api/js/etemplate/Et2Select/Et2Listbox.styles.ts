import {css} from "lit";

export default css`
	:host {
		display: block;
		flex: 1 0 auto;
		--icon-width: 20px;
	}

	::slotted(img), img {
		vertical-align: middle;
	}
		et2-image[part="icon"]{
		}
	    et2-image[part="icon"][slot="prefix"]{
	        width: var(--icon-width);
	        font-size: var(--icon-width);
	    }

	.menu {
		/* Get rid of padding before/after options */
		padding: 0px;

		/* No horizontal scrollbar, even if options are long */
		overflow-x: clip;
	}
	/* Required and nothing selected: the yellow etemplate2.css gives other widgets through parts this one does not export */
	:host([required]:not(.hasValue)) .menu {
		background-color: var(--required-background-color, #ffffd0);
	}

	/* Ellipsis when too small */

	  sl-option.option__label {
		display: block;
		text-overflow: ellipsis;
		/* This is usually not used due to flex, but is the basis for ellipsis calculation */
		width: 10ex;
	}

	  :host([rows]) .menu {
		height: calc(var(--rows, 5) * var(--row-height) + 2 * var(--sl-input-border-width)); /* + top/bottom border */
		overflow-y: auto;
	}
		sl-menu-item::part(label){
			display: flex;
			align-items: center;
		}

	/*
	 * Shoelace highlights a hovered and a focused item alike, in the primary colour, and its menu focuses whatever the
	 * pointer is over.  Hover is only a quiet gray here: the primary highlight is for the item the keyboard is on.
	 */
	sl-menu-item:not([disabled]):hover::part(base) {
		background-color: var(--sl-color-neutral-100, #f4f4f5);
		color: var(--sl-color-neutral-700, inherit);
	}

	/* The focus the menu gives an item the pointer passed over is not the keyboard: [keyboard] is set by the last key */
	:host(:not([keyboard])) sl-menu-item:not([disabled]):focus:not(:hover)::part(base) {
		background-color: transparent;
		color: inherit;
	}

	:host([keyboard]) sl-menu-item:not([disabled]):focus-visible:not(:hover)::part(base) {
		background-color: var(--sl-color-primary-600);
		color: var(--sl-color-neutral-0);
	}
`;
