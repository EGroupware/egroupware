import {css} from "lit";

export default css`
	:host {
	    /* Avoid unwanted style overlap from button */
	    border: none;
	    background-color: none;
	}

	:host, sl-menu {
	    /**
		Adapt shoelace color variables to what we want
		Maybe some logical variables from etemplate2.css here?
		*/
	    --sl-color-primary-50: var(--sl-input-background-color-hover);
	    --sl-color-primary-100: var(--gray-10);
	    --sl-color-primary-300: var(--sl-input-border-color-hover, var(--input-border-color));
	    --sl-color-primary-400: var(--input-border-color);
	    --sl-color-primary-600: var(--primary-background-color);
	    --sl-color-primary-700: var(--sl-input-color-hover);
	}

	:host(:active), :host([active]) {
	    background-color: initial;
	}

	sl-button-group {
	    display: initial;
	}

	#main {
	    flex: 1 1 auto;
	}

	et2-image {
	    width: 1em;
	}

	::slotted(et2-image[slot="prefix"]), ::slotted(et2-image[slot="suffix"]) {
	    width: 20px;
	    height: 20px;
	    display: flex;
	    font-size: 20px !important;
	}

	sl-menu-item::part(label) {
	    color: var(--item-color, inherit);
	}

	/* Leave the label in the accessibility tree, but hide it visually */

	:host([iconOnly]) {
	    #main::part(base){
	        padding-inline: var(--sl-spacing-small);
	    }
	    #main::part(label) {

	        position: absolute;
	        left: -999px;
	    }
	}
`;
