import {css} from "lit";

export default css`
	.tag {
	  position: relative;
	}

	.tag__prefix {
	  flex: 0 1 auto;

	  opacity: 30%;
	  cursor: pointer;


		et2-lavatar {
			--size: var(--icon-width, 1em);
		}
	}

	.tag__has_plus et2-button-icon {
	  visibility: visible;
	}

	:host(:hover) .tag__has_plus {
	  opacity: 100%;
	}

	/* Address is for a contact - always show */

	.tag__prefix.tag__has_contact {
	  opacity: 100%;
	}

	.tag__remove {
	  order: 3;
	}

	/* Shoelace disabled gives a not-allowed cursor, but we also set disabled for read-only.
	 * We don't want the not-allowed cursor, since you can always click the email address
	 */

	:host([readonly]) {
	  cursor: pointer !important;
	}
`;
