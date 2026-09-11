import {css} from "lit";

export default css`
	:host {
	  flex: 1 1 auto;
	}

	.tag--pill {
	  overflow: hidden;
	}

	::slotted(et2-image) {
	  height: 20px;
		width: var(--icon-width, 20px);
		display: inline-block;
	}

		::slotted(et2-lavatar) {
			--size: 2rem;
		}

	.tag__prefix {
	  line-height: normal;
	}
	.tag__content {
	  padding: 0px 0.2rem;
	  flex: 1 2 auto;
	  overflow: hidden;
	  text-overflow: ellipsis;
	}

	.tag__edit {
	  flex: 10 1 auto;
	  min-width: 20ex;
	  width: 60ex;
	}

	/* Avoid button getting truncated by right side of button */

	.tag__remove {
	  margin-right: 0;
	  margin-left: 0;
	}

	et2-button-icon {
	  visibility: hidden;
	}

	:host(:hover) et2-button-icon {
	  visibility: visible;
	}
`;
