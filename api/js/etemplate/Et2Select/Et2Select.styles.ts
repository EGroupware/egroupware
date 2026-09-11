import {css} from "lit";

export default css`
	/* Search & free-entry UI.  Moved here from SearchMixin, which had no business
	   shipping sl-select part overrides - the mixin is being retired and this styles
	   Et2Select's own DOM.  Behaviour unchanged, selectors are verbatim. */

	/* Full width search textbox covers loading spinner, lift it up */
	::slotted(sl-spinner) {
		z-index: 2;
	}

	/* Show edit textbox only when editing */
	.search_input #edit {
		display: none;
	}
	.search_input.editing #search {
		display: none;
	}
	.search_input.editing #edit {
		display: initial;
	}


	  :host([search]) sl-select[open]::part(prefix), :host([allowfreeentries]) sl-select[open]::part(prefix) {
		flex: 2 1 auto;
		flex-wrap: wrap;
		width: 100%;
	}

		:host([search]), :host([allowfreeentries]) {
			sl-select[open]::part(display-input) {
				display: none;
			}
		}

		:host([search][multiple]), :host([allowfreeentries]) {
			sl-select[open]::part(clear-button) {
				display: none;
			}

			sl-select[open]::part(expand-icon) {
				display: none;
			}
		}

	  sl-select[open][multiple]::part(tags) {
		flex-basis: 100%;
	  }

	  sl-select[open][multiple]::part(combobox) {
		flex-flow: wrap;
	  }


	  /* Search textbox general styling, starts hidden */

	  .search_input {
		display: none;
		/* See also etemplate2.css, searchbox border turned off in there */
		border: none;
		flex: 1 1 auto;
		order: 2;
		margin-left: 0px;
		height: var(--sl-input-height-medium);
		width: 100%;
		  background-color: var(--input-background-color);
		z-index: var(--sl-z-index-dropdown);

		  #search::part(input) {
			  padding: 0 calc(var(--sl-input-spacing-small) - var(--sl-input-border-width) * 4);
		  }
	  }

		:host([search]) et2-textbox::part(base), #edit, #edit:focus-visible {
		border: none;
		box-shadow: none;
			outline: none;
	  }

	  /* Search UI active - show textbox & stuff */

	  .search_input.active,
	  .search_input.editing {
		display: flex;
	  }

	  /* If multiple and no value, overlap search onto widget instead of below */

	  :host([multiple]) .search_input.active.novalue {
		top: 0px;
	  }

	/* Hide options that do not match current search text */

	  :host([search]) sl-option.no-match {
		display: none;
	}

	/* Nothing to show yet (no local options, nothing searched) - don't render an empty dropdown panel */

	  :host([dropdown-empty]) ::part(listbox) {
		border: none;
		box-shadow: none;
		padding: 0;
	}
	/* Different cursor for editable tags */
	:host([allowfreeentries]):not([readonly]) .search_tag::part(base)  {
		cursor: text;
	}

	/** Readonly **/
	/* No border */
	:host([readonly]) .form-control-input {
		border: none;
	}
	/* disable focus border */
	:host([readonly]) .form-control-input:focus-within {
		box-shadow: none;
	}
	/* normal cursor */
	:host([readonly]) .select__control {
		cursor: initial;
	}

	:host {
		display: block;
		flex: 1 0 auto;
		--icon-width: 20px;
	}

	.form-control--has-label::part(form-control-label) {
		margin-right: var(--sl-spacing-medium);
	}

	::slotted(img), img {
		vertical-align: middle;
	}

	/* No wrapping */

	sl-option::part(base) {
		white-space: nowrap;
	}

	/* No horizontal scrollbar, even if options are long */

	.dropdown__panel {
		overflow-x: clip;
	}

	/* Ellipsis when too small */

	::part(tags) {
		max-width: 100%;
		padding: var(--sl-spacing-2x-small); 
	}

	.select__label {
		display: block;
		text-overflow: ellipsis;
		/* This is usually not used due to flex, but is the basis for ellipsis calculation */
		width: 10ex;
	}

	/** multiple=true uses tags for each value **/
	/* styling for icon inside tag (not option) */

	.tag_image {
		margin-right: var(--sl-spacing-x-small);
	}

	/* Maximum height + scrollbar on tags (+ other styling) */

	::part(tags) {
		overflow-y: auto;
		margin-left: 0px;
		max-height: initial;
		min-height: auto;
		gap: 0.1rem 0.5rem;
	}

	:host([rows]) ::part(tags) {
		max-height: calc(var(--rows, 5) * (var(--sl-input-height-medium) * 0.8));
	}

	:host([rows='1']) ::part(tags) {
		overflow-y: hidden;
	}

	:host([readonly][rows='1']) ::part(tags) {
		overflow: hidden;
	}

	/* No wrapping if only 1 row */

	:host([multiple][rows='1']) [open]::part(combobox) {
		flex-flow: nowrap;
	}

	:host([multiple][rows='1']) [open]::part(tags) {
		flex-basis: auto;
	}

	/* No rows set, default height limit about 5 rows */

	:host(:not([rows])) ::part(tags) {
		max-height: 11em;
	}

	select:hover {
		box-shadow: 1px 1px 1px rgb(0 0 0 / 60%);
	}

	/* Hide dropdown trigger when multiple & readonly */

	:host([readonly][multiple]:not([rows='1']))::part(expand-icon) {
		display: none;
	}

	:host([search][open]) ::part(prefix) {
		flex-flow: wrap;
	}

	/* Style for tag count if rows=1 */

	.tag_limit {
		position: absolute;
		right: 0px;
		top: 0px;
		bottom: 0px;
		box-shadow: rgb(0 0 0/50%) -1.5ex 0px 1ex -1ex, rgb(0 0 0 / 0%) 0px 0px 0px 0px;
	}

	.tag_limit::part(base) {
		height: 100%;
		background-color: var(--sl-input-background-color);
		border-top-left-radius: 0;
		border-bottom-left-radius: 0;
		font-weight: bold;
		min-width: 3em;
		justify-content: center;
	}

	/* Show all rows on hover if rows=1 */

	:host([ readonly ][ multiple ][ rows ]) .hover__popup {
		width: -webkit-fill-available;
		width: -moz-fill-available;
		width: fill-available;
	}

	:host([readonly][multiple][rows]) .hover__popup::part(popup) {
		z-index: var(--sl-z-index-dropdown);
		background-color: var(--sl-color-neutral-0);
	}

	:host([ readonly ][ multiple ][ rows ]) .hover__popup .select__tags {
		display: flex;
		flex-wrap: wrap;
	}

	::part(listbox) {
		z-index: 1;
		background: var(--sl-input-background-color);
		padding: var(--sl-input-spacing-small);
		padding-left: 2px;

		box-shadow: var(--sl-shadow-large);
		min-width: fit-content;
		border-radius: var(--sl-border-radius-small);
		border: 1px solid var(--sl-color-neutral-200);
		overflow-y: auto;
	}

	::part(display-label) {
		margin: 0;
	}

	:host::part(display-label) {
		max-height: 8em;
		overflow-y: auto;
	}

	:host([readonly])::part(combobox) {
		background: none;
		opacity: 1;
		border: none;
	}

	/* Position & style of group titles */

	small {
		padding: var(--sl-spacing-medium);
	}
`;
