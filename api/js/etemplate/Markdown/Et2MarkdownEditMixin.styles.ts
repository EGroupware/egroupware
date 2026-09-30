/**
 * EGroupware eTemplate2 - markdown editor chrome
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */

import {css} from "lit";

/**
 * The editor's own chrome - the field controls, the split layout and the format popup.
 *
 * Styling for the *rendered* markdown is a separate concern and lives in markdown.less, which
 * reaches shadow DOM through Markdown.styles.ts and the light DOM through widgets.less.
 */
export default css`
	.markdown-shell {
		position: relative;
		display: flex;
		flex-direction: column;
		height: 100%;
		min-height: 0;
	}

	.markdown-shell__panes {
		flex: 1 1 auto;
		min-height: 0;
		display: flex;
	}

	.markdown-shell__panes > * {
		flex: 1 1 auto;
		min-width: 0;
	}

	.markdown-shell__source {
		display: flex;
		min-width: 0;
		min-height: 0;
	}

	.markdown-shell__source > * {
		flex: 1 1 auto;
		min-width: 0;
	}

	/* beats the flex display above, so ?hidden really hides */
	.markdown-shell [hidden] {
		display: none !important;
	}

	/* the preview scrolls on its own, so a long document cannot stretch the field */
	.markdown-shell__preview {
		/* It is the way back into the editor - a click anywhere on it puts the caret in the
		   source - so it has to BE somewhere.  height:100% is what does that: a visible field
		   always has a height from somewhere.  min-height only covers the case where it does
		   not (an empty preview has no content to hold it open), and costs nothing when it
		   does. */
		height: 100%;
		min-height: var(--sl-input-height-medium, 2.5rem);
		box-sizing: border-box;
		cursor: text;
		overflow: auto;
		padding: var(--sl-spacing-x-small);
		background-color: var(--sl-color-neutral-0);
		border: solid var(--sl-input-border-width) var(--sl-input-border-color);
		border-radius: var(--sl-input-border-radius-medium);
	}

	/* Positions the field controls - see _fieldControlsTemplate() */
	:host {
		position: relative;
	}

	/* One strip over the top-right corner of the field holds every control that lives there -
	   our view switcher and whatever is slotted in, eg. et2-ai's button - so they line up
	   with each other instead of each guessing where the other one is.  Each control keeps its
	   own size; only the placement is shared.
	   It stays out of the way until you go looking for it.  focus-within as well as hover, or
	   the controls would be out of reach of the keyboard entirely. */
	.field-controls {
		visibility: hidden;
		position: absolute;
		top: var(--sl-spacing-2x-small);
		right: var(--sl-spacing-2x-small);
		z-index: 1;
		display: flex;
		align-items: center;
		gap: var(--sl-spacing-3x-small);
	}

	/* [open] keeps the panel usable once the pointer has moved off the trigger onto the panel */
	:host(:hover) .field-controls,
	:host(:focus-within) .field-controls,
	.markdown-view[open] {
		visibility: visible;
	}

	.markdown-view::part(panel) {
		padding: var(--sl-spacing-3x-small);
	}

	.markdown-view__panel {
		display: flex;
		gap: var(--sl-spacing-3x-small);
	}

	.markdown-view__option.active::part(base) {
		background-color: var(--sl-color-neutral-200);
		border-radius: var(--sl-border-radius-small);
	}

	.markdown-popup__bar {
		display: flex;
		align-items: center;
		gap: var(--sl-spacing-3x-small);
		padding: var(--sl-spacing-3x-small);
		background-color: var(--sl-panel-background-color);
		border: solid var(--sl-panel-border-width) var(--sl-panel-border-color);
		border-radius: var(--sl-border-radius-medium);
		box-shadow: var(--sl-shadow-large);
	}

	.markdown-popup__separator {
		width: var(--sl-panel-border-width);
		align-self: stretch;
		background-color: var(--sl-panel-border-color);
	}

	/* the file input is only there to open the chooser - the button is what is seen */
	input.markdown-popup__file {
		display: none;
	}
`;
