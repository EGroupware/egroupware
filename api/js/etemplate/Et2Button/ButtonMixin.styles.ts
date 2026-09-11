import {css} from "lit";

export default css`
	            :host {
	                padding: 0;
	                /* These should probably come from somewhere else */
	               	max-width: 125px;
	               	min-width: fit-content;
	               	display: block;
	            }
	            /* Override general disabled=hide from Et2Widget */
	            :host([disabled]) {
	            	display: block;
	            }
	            :host([hideonreadonly][disabled]) {
	            	display:none !important;
	            }

		/* Leave label there for accessibility, but position it so it can't be seen */
		:host(.imageOnly) .button__label {
			position: absolute;
			left: -999px
		}

	            /* Set size for icon */
	            ::slotted(img.imageOnly) {
	    			padding-right: 0px !important;
	    			width: 16px !important;
		}
	            ::slotted(et2-image) {
	            	height: 20px;
	                max-width: 20px;
	                display: flex;
			font-size: 20px !important;
			padding-left: var(--et2-button-image-padding-left);
	/*fix for firefox esr: this version does not set a width on the image to fill available space
	so we force the button images to be square*/
			width: 20px;
	            }
	            ::slotted([slot="icon"][src='']) {
			display: none;
		}
		.imageOnly {
			width:18px;
			height: 18px;
		}
		/* Make hover border match other widgets (select) */
		.button--standard.button--default:hover:not(.button--disabled) {
			background-color: var(--sl-color-gray-200);
			border-color: var(--sl-input-border-color-hover);
			color: var(--sl-input-color-hover);
		}
		.button {
			justify-content: left;
		}
		.button--has-label.button--medium .button__label {
			padding: 0 var(--sl-spacing-medium);
		}
		.button__label {
			text-overflow: ellipsis;
	    			overflow-x: hidden;
		}
		.button__prefix {
			padding-left: 1px;
		}

		/* Only image, no label */
		.button--has-prefix:not(.button--has-label) {
			justify-content: center;
			width: var(--sl-input-height-medium);
			padding-inline-start: 0;			
		}

			.button--has-prefix:not(.button--has-label) {
				::slotted(et2-image), .button__label {
					padding-left: 0;
				}
		}

		/* Override primary styling - we use variant=primary on first dialog button */
		.button--standard.button--primary {
			background-color: var(--sl-color-gray-100);
			border-color: var(--sl-color-gray-400);
			color: var(--sl-input-color-hover);
		}
		.button--standard.button--primary:hover:not(.button--disabled),
		.button--standard.button--primary.button--checked:not(.button--disabled) {
			background-color: var(--sl-color-gray-200);
			border-color: var(--sl-color-gray-600);
			color: initial;
		}
		.button--standard.button--primary:active:not(.button--disabled) {
			border-color: var(--sl-color-gray-700);
			background-color: var(--sl-color-gray-300);
			color: initial;
		}
`;
