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
	/* Ellipsis when too small */

	  sl-option.option__label {
		display: block;
		text-overflow: ellipsis;
		/* This is usually not used due to flex, but is the basis for ellipsis calculation */
		width: 10ex;
	}

	  :host([rows]) .menu {
		height: calc(var(--rows, 5) * 1.9rem);
		overflow-y: auto;
	}
		sl-menu-item::part(label){
			display: flex;
			align-items: center;
		}
`;
