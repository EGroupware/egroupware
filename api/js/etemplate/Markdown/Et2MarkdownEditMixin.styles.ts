/**
 * EGroupware eTemplate2 - markdown editor chrome
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */

import {css} from "lit";

/**
 * The editor's own chrome - the view switcher, the split layout and the format popup.
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
		/* it is the way back into the editor,*/
                        height: 100%;
                        box-sizing: border-box;
		cursor: text;
		overflow: auto;
		padding: var(--sl-spacing-x-small);
		background-color: var(--sl-color-neutral-0);
		border: solid var(--sl-input-border-width) var(--sl-input-border-color);
		border-radius: var(--sl-input-border-radius-medium);
	}

	/* the view switcher sits over the top-right corner of the editor, next to
	   the AI button in the top-right - including staying out of the way until
	   you go looking for it (Et2Ai.styles.ts does the same with visibility) */
	.markdown-view {
		visibility: hidden;
		position: absolute;
		top: var(--sl-spacing-3x-small);
		right: var(--sl-spacing-medium);
		z-index: 1;
	}

	/* focus-within is ours, not the AI button's: hover alone would put the
	   switcher out of reach of the keyboard entirely.  [open] keeps the panel
	   usable once the pointer has moved off the trigger onto the panel. */
	.markdown-shell:hover .markdown-view,
	.markdown-shell:focus-within .markdown-view,
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
`;
