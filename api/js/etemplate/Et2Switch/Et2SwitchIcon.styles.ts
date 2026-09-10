import {css} from "lit";

export default css`
	:host {
		--indicator-color: var(--sl-color-primary-600);
		display: flex;
	}

	sl-switch {
		font-size: 1em;
		--height: 1em;
	}

	::part(control) {
		display: none;
	}

	::part(label) {
		width: 100%;
		height: 100%;
	}

	.label {
		display: inline-flex;
		flex: 1 1 auto;
		font-size: var(--height);
		/*add more height to the image container to allow centering*/
		height: calc(var(--height) + 4px);
		user-select: none;
	}

	et2-image, ::slotted(:scope > *) {
		flex: 1 1 50%;
		font-size: var(--width);
	}

	slot {
		color: var(--sl-input-placeholder-color);
	}

	sl-switch {
		display: flex;
		align-items: center;
	}

	sl-switch[checked] slot[name="on"], sl-switch:not([checked]) slot[name="off"] {
		color: var(--indicator-color, inherit);
		et2-image{
			filter: var(--image-filter)
		}
	}

	sl-switch[checked] slot[name="off"], sl-switch:not([checked]) slot[name="on"]{
	    et2-image{
	        filter: var(--image-filter-off)
	    }
	}

	sl-switch::part(label), sl-switch::part(form-control) {
		display: flex;
		align-items: center;
		margin-inline-start: 0px;
	}

	.label:hover {
		background-color: var(--sl-input-background-color-hover);
		border-color: var(--sl-input-border-color-hover);
	}
`;
