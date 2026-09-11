import {css} from "lit";

export default css`
	[part='edit'] {
	    visibility: hidden;
	    border-radius: 50%;
	    margin: -4px;
	    z-index: 1;
	    color: var(--sl-color-neutral-950)
	}

	div[part='initials'] {
	    & ~ .edit {
	        position: absolute;
	        right: 2rem;
	    }

	    & ~ .delete {
	        position: absolute;
	        left: 2rem;
	    }
	}

	:host(:hover)::part(edit) {
	    visibility: visible;
	}

	/* if we fall back to an sl-icon give it a visible color*/

	sl-icon {
	    color: var(--sl-color-neutral-950)
	}
`;
