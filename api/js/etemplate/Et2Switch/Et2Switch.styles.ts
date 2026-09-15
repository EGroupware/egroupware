import {css} from "lit";

export default css`
	:host {
	/* Make it line up with the middle of surroundings */
	margin: auto 0px;
	vertical-align: -webkit-baseline-middle;
	}

	/*
	Our render() wraps Shoelace's form-control in one of our own so the label can go in front of
	the switch.  Letting Shoelace's own form-control disappear into that flex row keeps label,
	switch and help text in a single row, the way they are for every other input widget - which
	is what .et2-label-fixed's help-text offset assumes.
	*/

	.form-control > .form-control {
	display: contents;
	}

	.switch {
	position: relative;
	}

	.toggle__label {
	position: absolute;
	left: 0px;
	border-radius: 50%;
	flex: 0 0 auto;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: var(--width);
	height: var(--height);
	margin: 0px;
	}

	.switch__thumb {
	z-index: var(--sl-z-index-tooltip);
	}

	::slotted(span.label) {
	width: var(--width);
	display: inline-flex;
	align-items: center;
	height: var(--height);
	}

	/* 
	Use two images instead of normal switch by adding et2_image_switch class
	see etemplate.css for the rest (slotted label)
	 */

	:host(.et2SlideSwitch) .switch {
	min-width: 60px;
	--height: var(--sl-input-height-medium);
	border-color: var(--sl-input-border-color);
	border-width: var(--sl-input-border-width);
	border-radius: var(--sl-border-radius-medium);
	border-style: solid;
	}

	:host(.et2SlideSwitch) .switch__control {
	visibility: hidden;
	}

	:host(.et2SlideSwitch) .switch__label {
	width: 100%;
	height: 100%;
	}

	:host(.et2SlideSwitch) ::slotted(.label) {
	flex: 1 1 auto;
	}
`;
