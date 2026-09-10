import {css} from "lit";

export default css`
	:host {
		height: auto;
	}

	slot[name="collapse-icon"], slot[name="expand-icon"] {
	                display: none;
	            }

	.details {
		height: 100%;
	                position: relative;
		display: flex;
		flex-direction: column;
	            }

	.details__body {
		flex: 1 1 auto;
		position: relative;
	}

	.summaryOnTop .details__body {
		height: 100% !important;
		position: relative;
	}

	.details__content {
		overflow: hidden;
		overflow-y: auto;
		height: 100%;
	}

	summary {
		flex: 0 0 auto;
	                pointer-events: none;
	            }

	.details.summaryOnTop > summary {
	                position: absolute;
	                pointer-events: none;
	                width: fit-content;
	                line-height: 0;
	                top: -1rem;
	                left: .5rem;
	                background: var(--sl-color-neutral-0);
	            }

	.details.summaryOnTop {
	                padding-top: .5rem;
	                margin-top: .5rem;
		height: auto;
		overflow: visible;
	            }
`;
