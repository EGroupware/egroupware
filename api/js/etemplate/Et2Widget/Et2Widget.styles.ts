import {css} from "lit";

/**
 * For read-only widgets that render their own `<span class="form-control-label">` next to the value.
 *
 * Under .et2-label-fixed the label becomes a column of its own (the width comes from the rule in the
 * default styles below) and the row is as tall as the input it stands in for.  Like an editable field, the
 * value drops below the label when the two no longer fit side by side.  The height is on the label
 * rather than the host because a layout's min-height: 0 on its children outranks :host rules.
 */
export const readonlyFixedLabelStyles = css`
	:host(.et2-label-fixed) {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
	}

	:host(.et2-label-fixed) .form-control-label {
		display: flex;
		align-items: center;
		min-height: var(--sl-input-height-medium);
		margin-right: var(--sl-spacing-medium);
	}
`;

export default css`
	:host([disabled]) {
		display: none;
	}

	/* Without this, an inherited :host{display:...} (eg. from Shoelace) silently outranks the browser's own [hidden] rule - see Et2MenuItem.ts for the same fix. */
	:host([hidden]) {
		display: none;
	}

	:host(.et2_clickable) {
		cursor: pointer;
	}

	/* CSS to align internal inputs according to box alignment */

	:host([align="center"]) .input-group__input {
		justify-content: center;
	}

	:host([align="right"]) .input-group__input {
		justify-content: flex-end;
	}

	/* Put widget label to the left of the widget */

	::part(form-control), .form-control {
		display: flex;
		align-items: center;
		flex-wrap: wrap;
	}

	::part(form-control-label), .form-control-label {
		flex: 0 0 auto;
		white-space: normal;
	}

	.form-control--has-label .form-control__label {
		margin-right: var(--sl-spacing-medium);
	}

	::part(form-control-input), .form-control-input {
		flex: 1 1 auto;
		position: relative;
		max-width: 100%;
	}

	/*
	 * The help text is where a failed validation shows its message.  It is out of the flow, under the control: in
	 * the row (flex-basis: 100% only meant "a line of its own" where the row wraps, and a fixed-label row does not)
	 * it took the input's width, so a required field that failed shrank and its neighbours moved.
	 */
	::part(form-control), .form-control {
		position: relative;
	}

	::part(form-control-help-text), .form-control-help-text, .form-control .form-control__help-text {
		flex-basis: auto;
		position: absolute;
		top: 100%;
		left: 0;
		width: auto;
		/* Over the widget that follows it, which may have a stacking context of its own (an editor) */
		z-index: 1;
	}

	/* Use .et2-label-fixed class to give fixed label size */

	:host(.et2-label-fixed) {
		/*
		 * :where() around the in-shadow selector so all three branches weigh the same as the
		 * ::part() ones.  Without it, a widget whose own label element is matched by that branch
		 * gets a heavier rule than the one-column collapse a layout writes as ::part(), so on a
		 * narrow screen its label keeps the fixed column while every other widget's goes full
		 * width - the class has to mean the same thing for all of them.
		 */
		&::part(form-control-label), & > *::part(form-control-label), :where(.form-control-label) {

			width: initial;
			width: var(--label-width, 8em);
		}
	}

	:host(.et2-label-fixed)::part(form-control-help-text), :host(.et2-label-fixed) .form-control-help-text,
	:host(.et2-label-fixed) .form-control__help-text {
		width: auto;
		/* A margin, not left: it moves the in-flow help text too (a select's own), and leaves it the room that is left to wrap in */
		margin-left: calc(var(--sl-spacing-medium) + var(--label-width, 8em));
	}
`;
