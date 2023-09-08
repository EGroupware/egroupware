/**
 * EGroupware eTemplate2 - SearchMixin
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

import {css, CSSResultGroup, html, LitElement, nothing, render} from "lit";
import {cleanSelectOptions, SelectOption} from "./FindSelectOptions";
import {Validator} from "@lion/form-core";
import {Et2Tag} from "./Tag/Et2Tag";
import {SlMenuItem} from "@shoelace-style/shoelace";
import {StaticOptions} from "./StaticOptions";
import {TemplateResult} from "@lion/core";

// Otherwise import gets stripped
let keep_import : Et2Tag;

// Export the Interface for TypeScript
type Constructor<T = {}> = new (...args : any[]) => T;

export declare class SearchMixinInterface
{
	/**
	 * Enable searching on options
	 */
	search : boolean;

	/**
	 * Get [additional] options from the server when you search, instead of just searching existing options
	 */
	searchUrl : string;

	/**
	 * Allow adding new options that are not in the search results
	 */
	allowFreeEntries : boolean;

	/**
	 * Additional search options passed to the search functions
	 *
	 * @type {object}
	 */
	searchOptions : object;

	/**
	 * Start the search process
	 */
	startSearch() : void

	/**
	 * Search local options
	 */
	localSearch(search : string, options : object) : Promise<void>

	/**
	 * Search remote options.
	 * If searchUrl is not set, it will return very quickly with no results
	 */
	remoteSearch(search : string, options : object) : Promise<void>

	/**
	 * Check a [local] item to see if it matches
	 */
	searchMatch(search : string, options : object, item : LitElement) : boolean

	/**
	 * Additional customisation location, where we stick the search elements
	 *
	 * @type {TemplateResult}
	 */
	_extraTemplate : TemplateResult
}

/**
 * Base class for things that do search type behaviour
 * Separated to keep things a little simpler.
 *
 * Currently I assume we're extending an Et2Select, so changes may need to be made for better abstraction
 */
export const Et2WithSearchMixin = <T extends Constructor<LitElement>>(superclass : T) =>
{
	class Et2WidgetWithSearch extends superclass
	{
		static get properties()
		{
			return {
				...super.properties,
				search: {type: Boolean, reflect: true},

				searchUrl: {type: String},

				/**
				 * Allow custom entries that are not in the options
				 */
				allowFreeEntries: {type: Boolean, reflect: true},

				/**
				 * Additional search parameters that are passed to the server
				 * when we query searchUrl
				 */
				searchOptions: {type: Object},

				/**
				 * Allow editing tags by clicking on them.
				 * allowFreeEntries must be true
				 */
				editModeEnabled: {type: Boolean}
			}
		}

		static get styles() : CSSResultGroup
		{
			return [
				// @ts-ignore
				...(super.styles ? (Symbol.iterator in Object(super.styles) ? super.styles : [super.styles]) : []),
				css`

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

				  :host([search]) sl-select[open]::part(display-input), :host([allowfreeentries]) sl-select[open]::part(display-input) {
					display: none;
				}

				  :host([search][multiple]) sl-select[open]::part(expand-icon) {
					display: none;
				  }

				  :host([multiple]) sl-select[open]::part(tags) {
					flex-basis: 100%;
				  }

				  :host([multiple]) sl-select[open]::part(combobox) {
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
					background-color: white;
					z-index: var(--sl-z-index-dropdown);
				  }

				  :host([search]) et2-textbox::part(search__base) {
					border: none;
					box-shadow: none;
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

				  .no-match {
					display: none;
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
				`
			]
		}

		// Borrowed from Lion ValidatorMixin, but we don't want the whole thing
		protected defaultValidators : Validator[];
		protected validators : Validator[];

		private _searchTimeout : number;

		/**
		 * When user is typing, we wait this long for them to be finished before we start the search
		 * @type {number}
		 * @protected
		 */
		protected static SEARCH_TIMEOUT = 500;

		/**
		 * We need at least this many characters before we start the search
		 *
		 * @type {number}
		 * @protected
		 */
		protected static MIN_CHARS = 2;

		/**
		 * Limit server searches to 100 results, matches Link::DEFAULT_NUM_ROWS
		 * @type {number}
		 */
		static RESULT_LIMIT : number = 100;


		// Hold the original option data from earlier search results, since we discard on subsequent search
		private _selected_remote = <SelectOption[]>[];

		// Hold current search results, selected or otherwise
		private _remote_options = <SelectOption[]>[];

		/**
		 * These characters will end a free tag
		 * @type {string[]}
		 */
		static TAG_BREAK : string[] = ["Tab", "Enter", ","];

		constructor(...args : any[])
		{
			super(...args);

			this.search = false;
			this.searchUrl = "";
			this.searchOptions = {app: "addressbook"};

			this.allowFreeEntries = false;
			this.editModeEnabled = false;

			// Hiding the selected options from the dropdown means we can't un-select the tags
			// hidden by the max limit.  Prefer no limit.
			this.maxOptionsVisible = -1;

			this.validators = [];
			/**
			 * Used by Subclassers to add default Validators.
			 * A email input for instance, always needs the isEmail validator.
			 * @example
			 * ```js
			 * this.defaultValidators.push(new IsDate());
			 * ```
			 * @type {Validator[]}
			 */
			this.defaultValidators = [];

			this.handleOptionClick = this.handleOptionClick.bind(this);
			this._handleChange = this._handleChange.bind(this);
			this.handleTagEdit = this.handleTagEdit.bind(this);
			this._handleAfterShow = this._handleAfterShow.bind(this);
			this._handleMenuHide = this._handleMenuHide.bind(this);
			this._handleSearchBlur = this._handleSearchBlur.bind(this);
			this._handleClear = this._handleClear.bind(this);
			this._handleDoubleClick = this._handleDoubleClick.bind(this);
			this._handleSearchAbort = this._handleSearchAbort.bind(this);
			this._handleSearchChange = this._handleSearchChange.bind(this);
			this._handleSearchKeyDown = this._handleSearchKeyDown.bind(this);
			this._handleSearchMouseDown = this._handleSearchMouseDown.bind(this);
			this._handleEditKeyDown = this._handleEditKeyDown.bind(this);
			this._handlePaste = this._handlePaste.bind(this);
		}

		connectedCallback()
		{
			super.connectedCallback();

			this.classList.toggle("search", this.searchEnabled);

			// Missing any of the required attributes?  Don't change anything.
			// If readonly, skip it
			if(!this.searchEnabled && !this.editModeEnabled && !this.allowFreeEntries || this.readonly)
			{
				return;
			}

			this._bindListeners();
		}

		disconnectedCallback()
		{
			super.disconnectedCallback();
			this._unbindListeners();
		}

		willUpdate(changedProperties)
		{
			super.willUpdate(changedProperties);

			// Turn on search if there's more than 20 options
			if(changedProperties.has("select_options") && this.select_options.length > 20)
			{
				this.search = true;
			}

			// If searchURL is set, turn on search
			if(changedProperties.has("searchUrl") && this.searchUrl)
			{
				this.search = true;
				// Decode URL, possibly again.  If set in template, it can wind up double-encoded.
				this.searchUrl = this.egw().decodePath(this.searchUrl);
			}

			// Add missing options if search or free entries enabled
			if(changedProperties.has("value") && this.value)
			{
				// Overridden to add options if allowFreeEntries=true
				if(this.allowFreeEntries && typeof this.value == "string" && !this.select_options.find(o => o.value == this.value &&
					(!o.class || o.class && !o.class.includes('remote'))))
				{
					this.createFreeEntry(this.value);
				}
				else if(this.allowFreeEntries && this.multiple)
				{
					this.getValueAsArray().forEach((e) =>
					{
						if(!this.select_options.find(o => o.value == e))
						{
							this.createFreeEntry(e);
						}
					});
				}
				if(this.searchEnabled)
				{
					// Check to see if value is for an option we do not have
					for(const newValueElement of this.getValueAsArray())
					{
						if(this.select_options.some(o => o.value == newValueElement))
						{
							continue;
						}

						this._missingOption(newValueElement);
					}
				}
			}
		}

		update(changedProperties)
		{
			super.update(changedProperties);

			// One of the key properties has changed, need to add the needed nodes
			if(changedProperties.has("search") || changedProperties.has("editModeEnabled") || changedProperties.has("allowFreeEntries"))
			{
				// Missing any of the required attributes?  Now we need to take it out.
				if(!this.searchEnabled && !this.editModeEnabled && !this.allowFreeEntries || this.readonly)
				{
					this.querySelector(".search_input")?.remove();
					return;
				}

				// Normally this should be handled in render(), but we have to add our nodes in
				//this._addNodes();
			}
			// Update any tags if edit mode changes
			if(changedProperties.has("editModeEnabled") || changedProperties.has("readonly"))
			{
				// Required because we explicitly create tags instead of doing it in render()
				this.shadowRoot.querySelectorAll(".select__tags > *").forEach((tag : Et2Tag) =>
				{
					tag.editable = this.editModeEnabled && !this.readonly;
					tag.removable = !this.readonly;
				});
			}
		}

		/**
		 * Add the nodes we need to search - add to DOM
		 *
		 * @protected
		 */
		protected _addNodes()
		{
			if(this._activeControls)
			{
				// Already there
				return;
			}

			const div = document.createElement("div");
			div.classList.add("search_input");
			render(this._searchInputTemplate(), div);

			div.slot = "prefix";
			div.part = "search";
			this.appendChild(div);
		}

		protected _extraTemplate()
		{
			if(!this.searchEnabled && !this.editModeEnabled && !this.allowFreeEntries || this.readonly)
			{
				return nothing;
			}
			return this._searchInputTemplate();
		}

		protected _searchInputTemplate()
		{
			let edit = nothing;
			if(this.editModeEnabled)
			{
				edit = html`<input id="edit" type="text" part="input" autocomplete="off" style="width:100%"
                                   @keydown=${this._handleEditKeyDown}
                                   @click=${(e) => e.stopPropagation()}
                                   @blur=${this.stopEdit.bind(this)}
                />`;
			}
			return html`
                <div class="search_input" slot="prefix">
                <et2-textbox id="search" type="text" part="input"
                             exportparts="base:search__base"
                             clearable
                             autocomplete="off"
                             placeholder="${this.egw().lang("search")}"
                             style="flex: 1 1 auto;"
                               @keydown=${this._handleSearchKeyDown}
                               @blur=${this._handleSearchBlur}
                ></et2-textbox>
                ${edit}
                </div>
			`;
		}

		protected _noResultsTemplate()
		{
			return html`
                <div class="no-results">${this.egw().lang("no suggestions")}</div>`;
		}

		/**
		 * Do we have the needed properties set, so we can actually do searching
		 *
		 * @returns {boolean}
		 */
		public get searchEnabled() : boolean
		{
			return !this.readonly && (this.search || this.searchUrl.length > 0);
		}

		protected get _searchInputNode() : HTMLInputElement
		{
			return this._activeControls?.querySelector("#search");
		}

		protected get _editInputNode() : HTMLInputElement
		{
			return this._activeControls?.querySelector("input#edit");
		}

		protected get _activeControls()
		{
			return this.shadowRoot?.querySelector(".search_input") ||
				this.querySelector(".search_input");
		}

		protected get optionTag()
		{
			return 'sl-option';
		}

		/**
		 * Only local options, excludes server options
		 *
		 * @protected
		 */
		protected get localItems() : NodeList
		{
			return this.select.querySelectorAll(this.optionTag + ":not(.remote)");
		}

		/**
		 * Only remote options from search results
		 * @returns {NodeList}
		 * @protected
		 */
		protected get remoteItems() : NodeList
		{
			return this.select?.querySelectorAll(this.optionTag + ".remote") ?? [];
		}

		/**
		 * Only free entries
		 * @returns {NodeList}
		 * @protected
		 */
		protected get freeEntries() : NodeList
		{
			return this.select?.querySelectorAll(this.optionTag + ".freeEntry") ?? [];
		}

		get select_options() : SelectOption[]
		{
			let options = [];

			// Any provided options
			options = options.concat(this.__select_options ?? []);

			// Any kept remote options
			options = options.concat(this._selected_remote ?? []);

			// Current search results
			options = options.concat(this._remote_options ?? []);

			if(this.allowFreeEntries)
			{
				this.freeEntries.forEach((item : SlMenuItem) =>
				{
					if(!options.some(i => i.value == item.value))
					{
						options.push({value: item.value, label: item.textContent, class: item.classList.toString()});
					}
				})
			}
			return options;
		}

		set select_options(options : SelectOption[])
		{
			super.select_options = options;

			// Remove any selected remote, they're real options now
			for(let remote_index = this._selected_remote.length - 1; remote_index >= 0; remote_index--)
			{
				let remote = this._selected_remote[remote_index];
				if(options.findIndex(o => o.value == remote.value) != -1)
				{
					this._selected_remote.splice(remote_index, 1);
					this.querySelector('[value="' + remote.value + '"]')?.classList.remove("remote");
				}
			}
		}

		get value()
		{
			return super.value;
		}

		set value(new_value : string | string[])
		{
			super.value = new_value;

			if(!new_value || !this.allowFreeEntries && !this.searchUrl)
			{
				return;
			}
			
			// If widget is currently open, we may need to re-calculate search / dropdown positioning
			if(this.isOpen)
			{
				this._handleMenuShow();
			}
		}

		/**
		 * Some [part of a] value is missing from the available options, but should be there, so find and add it.
		 *
		 * This is used when not all options are sent to the client (search, link list).  Ideally we want to send
		 * the options for the current value, but sometimes this is not the best option so here we search or create
		 * the option as needed.  These are not free entries, but need to match some list somewhere.
		 *
		 * @param {string} newValueElement
		 * @protected
		 */
		protected _missingOption(newValueElement : string)
		{
			// Given a value we need to search for - this will add in all matches, including the one needed
			this.remoteSearch(newValueElement, this.searchOptions).then((result : SelectOption[]) =>
			{
				const option = <SelectOption>result.find(o => o.value == newValueElement);
				if(option)
				{
					this._selected_remote.push(option);
				}
			});
		}

		protected fix_bad_value()
		{
			if(!this.allowFreeEntries && !this.searchEnabled)
			{
				// Let regular select deal with it
				return false;
			}
			const valueArray = Array.isArray(this.value) ? this.value : (!this.value ? [] : this.value.toString().split(','));

			// Check any already found options
			if(Object.values(this.getAllOptions()).filter((option) => valueArray.find(val => val == option.value)).length === 0)
			{
				return false;
			}

			return true;
			// TODO? Should we check the server, or just be OK with it?  Passing the "current" value in sel_options makes sure the value is there
		}

		protected _bindListeners()
		{
			this.addEventListener("sl-clear", this._handleClear);
			this.addEventListener("sl-show", this._handleMenuShow);
			this.addEventListener("sl-after-show", this._handleAfterShow);
			this.addEventListener("sl-hide", this._handleMenuHide);

			// Need our own change to catch the change event from search input
			this.addEventListener("change", this._handleChange);

			if(this.allowFreeEntries)
			{
				this.addEventListener("paste", this._handlePaste);
			}

			this.updateComplete.then(() =>
			{
				// Search messes up event order.  Since it throws its own bubbling change event,
				// selecting an option fires 2 change events - 1 before the widget is finished adjusting, losing the value
				// We catch all change events, then call this._oldChange only when value changes
				this.removeEventListener("change", this._oldChange);

				this._searchInputNode?.removeEventListener("change", this._searchInputNode.handleChange);
				this._searchInputNode?.addEventListener("change", this._handleSearchChange);

				//		this.dropdown.querySelector('.select__label').addEventListener("change", this.handleTagEdit);
			});
		}

		protected _unbindListeners()
		{
			this.removeEventListener("sl-select", this._handleSelect);
			this.removeEventListener("sl-show", this._handleMenuShow);
			this.removeEventListener("sl-after-show", this._handleAfterShow);
			this.removeEventListener("sl-hide", this._handleMenuHide);
			this.removeEventListener("sl-clear", this._handleClear)
			this.removeEventListener("change", this._handleChange);
			this.removeEventListener("paste", this._handlePaste);

			this._searchInputNode?.removeEventListener("change", this._handleSearchChange);
		}

		_handleMenuShow()
		{
			if(this.readonly)
			{
				return;
			}
			// Move search (& menu) if there's no value
			this._activeControls?.classList.toggle("novalue", this.multiple && this.value == '' || !this.multiple);

			// Reset for parent calculations, will be adjusted after if needed
			//this.dropdown.setAttribute("distance", 0);

			if(this.searchEnabled || this.allowFreeEntries)
			{
				this._activeControls?.classList.add("active");
				// Hide edit explicitly since it's so hard via CSS
				if(this._editInputNode)
				{
					this._editInputNode.style.display = "none";
				}
			}

			if(this.editModeEnabled && this.allowFreeEntries && !this.multiple && this.value)
			{
				this.startEdit();
				this._editInputNode.select();
				// Hide search explicitly since it's so hard via CSS
				this._searchInputNode.style.display = "none";
			}
		}

		/**
		 * Reposition the dropdown to allow space for current value and search.  If the dropdown was positioned above
		 * instead of below, we don't need the extra space - remove it.
		 */
		_handleAfterShow()
		{
			if(this.searchEnabled || this.allowFreeEntries)
			{
				this._searchInputNode.focus();
				this._searchInputNode.select();
			}
			return;
			// Need to give positioner a chance to position.
			// If we call it right away, it has not updated.
			// I haven't found an event or Promise to hook on to
			window.setTimeout(() =>
			{
				if(this.dropdown?.getAttribute("distance") && this.dropdown?.popup?.dataset.currentPlacement == "top")
				{
					this.dropdown.setAttribute("distance", 0);
					this.dropdown.reposition();
				}
				else
				{
					this.dropdown?.setAttribute("distance",
						!this._activeControls || this._activeControls?.classList.contains("novalue") ?
						parseInt(getComputedStyle(this.control).getPropertyValue("border-width")) :
							// Make room for search below
						parseInt(getComputedStyle(this._activeControls).getPropertyValue("--sl-input-height-medium"))
					);
				}
			}, 100);

		}

		focus()
		{
			this.show();
			this._searchInputNode.focus();
		}

		_handleMenuHide()
		{
			if(this.readonly)
			{
				return;
			}
			clearTimeout(this._searchTimeout);

			// Reset display
			if(this._searchInputNode)
			{
				this._searchInputNode.style.display = "";
			}
			if(this._editInputNode)
			{
				this._editInputNode.style.display = "";
			}

			this._activeControls?.classList.remove("active");
		}

		_triggerChange(event)
		{
			// Don't want searchbox events to trigger change event
			if(event.target == this._searchInputNode)
			{
				event.stopImmediatePropagation();
				event.preventDefault();
				return false;
			}
			return true;
		}

		_handleChange(event)
		{
			if(event.target == this._searchInputNode)
			{
				event.stopImmediatePropagation();
				event.preventDefault();
				return false;
			}

			return this._oldChange(event);
		}

		_handleDoubleClick(event : MouseEvent)
		{
			// No edit (shouldn't happen...)
			if(!this.editModeEnabled)
			{
				return;
			}

			// Find the tag
			const path = event.composedPath();
			const tag = <Et2Tag>path.find((el) => el instanceof Et2Tag);
			this.dropdown.hide();
			this.updateComplete.then(() =>
			{
				tag.startEdit(event);
			});
		}

		/**
		 * An option was selected
		 */
		handleOptionClick(event)
		{
			// Need to keep the remote option - only if selected
			if(event.target.classList.contains("remote") && !this.select_options.find(o => o.value == event.target.value))
			{
				this._selected_remote.push({...event.target.option});
			}
			super.handleOptionClick(event);

			this.updateComplete.then(() =>
			{
				// If they just chose one from the list, re-focus the search
				if(this.multiple && this.searchEnabled)
				{
					this._searchInputNode.focus();
					this._searchInputNode.select();

					// If we were overlapping, reset
					if(this._activeControls.classList.contains("novalue"))
					{
						this._handleMenuShow();
						this._handleAfterShow();
					}

					// Scroll the new tag into view
					if(event.detail)
					{
						// Causes sidemenu (calendar) to scroll to top & get stuck
						/*
						this.updateComplete.then(() =>
						{
							this.shadowRoot.querySelector("et2-tag[value='" + event.detail.item.value.replace(/'/g, "\\\'") + "']")?.scrollIntoView({block: "nearest"});
						});
						 */
					}
				}
				else if(!this.multiple && this.searchEnabled)
				{
					// Stop all the search stuff when they select an option
					// this shows all non-matching options again
					this._handleSearchAbort(event);
				}
			});
		}

		/**
		 * Value was cleared
		 */
		_handleClear(e)
		{
			// Only keep remote options that are still used
			this._selected_remote = this._selected_remote.filter((option) => this.value.indexOf(option.value) !== -1);

			if(!this.multiple && this.searchEnabled)
			{
				this._handleSearchAbort(e);

				// Start searching again
				this._handleMenuShow();
			}
		}

		/**
		 * Handle blur from search field
		 *
		 * Either the user changed fields, or selected an option.  For selecting don't interfere, but for
		 * changing fields we need to make sure the menu is hidden.
		 *
		 * @param event
		 */
		async _handleSearchBlur(event : FocusEvent)
		{
			clearTimeout(this._searchTimeout);
		}

		/**
		 * Handle keypresses inside the search input
		 * @param {KeyboardEvent} event
		 * @protected
		 */
		protected _handleSearchKeyDown(event : KeyboardEvent)
		{
			clearTimeout(this._searchTimeout);
			this._activeControls?.classList.add("active");

			// Pass off some keys to select
			if(['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key))
			{

				// Strip out hidden non-matching selected & disabled items so key navigation works
				// TODO
				return super.handleKeyDown(event);
			}
			event.stopPropagation();

			// Don't allow event to bubble or it will interact with select
			event.stopImmediatePropagation();
			if(Et2WidgetWithSearch.TAG_BREAK.indexOf(event.key) !== -1 && this.allowFreeEntries && this.createFreeEntry(this._searchInputNode.value))
			{
				event.preventDefault();
				this._searchInputNode.value = "";
				this.updateComplete.then(async() =>
				{
					// update sizing / position before getting ready for another one
					if(this.multiple)
					{
						//	await this.show();
						this._searchInputNode.focus();
					}
				});
			}
			else if(event.key == "Enter")
			{
				event.preventDefault();
				this.startSearch();
				return;
			}
			else if(event.key == "Escape")
			{
				this._handleSearchAbort(event);
				this.dropdown.hide();
				return;
			}

			// Start the search automatically if they have enough letters
			// -1 because we're in keyDown handler, and value is from _before_ this key was pressed
			if(this._searchInputNode.value.length >= Et2WidgetWithSearch.MIN_CHARS - 1)
			{
				this._searchTimeout = window.setTimeout(() => {this.startSearch()}, Et2WidgetWithSearch.SEARCH_TIMEOUT);
			}
		}

		/**
		 * Combobox listens for mousedown, which interferes with search clear button.
		 * Here we block it from bubbling
		 * @param {MouseEvent} event
		 * @protected
		 */
		protected _handleSearchMouseDown(event : MouseEvent)
		{
			event.stopPropagation();
		}

		protected _handleEditKeyDown(event : KeyboardEvent)
		{
			// Stop propagation, or parent key handler will add again
			event.stopImmediatePropagation();

			if(Et2WidgetWithSearch.TAG_BREAK.indexOf(event.key) !== -1 && this.allowFreeEntries)
			{
				// Prevent default, since that would try to submit
				event.preventDefault();
				this.stopEdit();
			}
			// Abort edit, put original value back
			else if(event.key == "Escape")
			{
				this.stopEdit(true);
			}
		}

		/**
		 * Sometimes users paste multiple comma separated values at once.  Split them then handle normally.
		 *
		 * @param {ClipboardEvent} event
		 * @protected
		 */
		protected _handlePaste(event : ClipboardEvent)
		{
			event.preventDefault();

			let paste = event.clipboardData.getData('text');
			if(!paste)
			{
				return;
			}
			const selection = window.getSelection();
			if(selection.rangeCount)
			{
				selection.deleteFromDocument();
			}
			let values = paste.split(/,\t/);

			values.forEach(v =>
			{
				this.createFreeEntry(v.trim());
			});
			this.dropdown.hide();
		}

		/**
		 * Start searching
		 *
		 * If we have local options, we'll search & display any matches.
		 * If serverUrl is set, we'll ask the server for results as well.
		 */
		public async startSearch()
		{
			// Stop timeout timer
			clearTimeout(this._searchTimeout);

			// Show a spinner
			let spinner = document.createElement("sl-spinner");
			spinner.slot = "expand-icon";
			this.appendChild(spinner);

			// Hide clear button
			let clear_button = <HTMLElement>this._searchInputNode.shadowRoot.querySelector(".input__clear")
			if(clear_button)
			{
				clear_button.style.display = "none";
			}

			// Clear previous results
			this._clearResults();
			await this.updateComplete;

			// Start the searches
			return Promise.all([
				this.localSearch(this._searchInputNode.value, this.searchOptions),
				this.remoteSearch(this._searchInputNode.value, this.searchOptions)
			]).then(() =>
			{
				// Show no results indicator
				if(this.getAllOptions().filter(e => !e.classList.contains("no-match")).length == 0)
				{
					let target = this._optionTargetNode || this;
					let temp = document.createElement("div");
					render(this._noResultsTemplate(), temp);
					target.append(temp.children[0]);
				}

				// Remove spinner
				spinner.remove();

				// Restore clear button
				if(clear_button)
				{
					clear_button.style.display = "";
				}
			});
		}

		/**
		 * Clear search term and any search results
		 *
		 * Local options are not removed, but remote options are
		 */
		public clearSearch()
		{
			// Stop timeout timer
			clearTimeout(this._searchTimeout);

			this._clearResults();

			// Clear search term
			if(this._searchInputNode)
			{
				this._searchInputNode.value = "";
			}
		}

		protected _clearResults()
		{
			let target = this._optionTargetNode || this;

			// Remove "no suggestions"
			target.querySelector(".no-results")?.remove();

			// Remove any previously selected remote options that aren't used anymore
			this._selected_remote = this._selected_remote.filter((option) =>
			{
				return this.multiple ? this.value.indexOf(option.value) != -1 : this.value == option.value;
			});
			// Remove remote options that aren't used
			let keepers = this._selected_remote.reduce((prev, current) =>
			{
				return prev + ":not([value='" + ('' + current.value).replace(/'/g, "\\\'") + "'])";
			}, "");
			target.querySelectorAll(".remote" + keepers).forEach(o => o.remove());
			target.childNodes.forEach((n) =>
			{
				if(n.nodeType == Node.COMMENT_NODE)
				{
					n.remove();
				}
			})
		}

		/**
		 * Filter the local options
		 *
		 * @param {string} search
		 * @protected
		 */
		protected localSearch(search : string, options : object) : Promise<void>
		{
			return new Promise((resolve) =>
			{
				this.localItems.forEach((item) =>
				{
					let match = this.searchMatch(search, item);
					item.classList.toggle("match", match);
					// set disabled so arrow keys step over.  Might be a better way to handle that
					item.disabled = !match;
					item.classList.toggle("no-match", !match);
				})
				resolve();
			});
		}

		/**
		 * Ask for remote options and add them in unconditionally
		 * @param {string} search
		 * @protected
		 */
		protected remoteSearch(search : string, options : object) : Promise<SelectOption[]>
		{
			if(!this.searchUrl)
			{
				return Promise.resolve([]);
			}

			// Check our URL: JSON file or URL?
			if(this.searchUrl.includes(".json"))
			{
				// Get the file, search it
				return this.jsonQuery(search, options);
			}
			else
			{
				// Fire off the query to the server
				return this.remoteQuery(search, options);
			}
		}

		/**
		 * Search through a JSON file in the browser
		 *
		 * @param {string} search
		 * @param {object} options
		 * @protected
		 */
		protected jsonQuery(search : string, options : object) : Promise<SelectOption[]>
		{
			// Get the file
			const controller = new AbortController();
			const signal = controller.signal;
			let response_ok = false;
			return StaticOptions.cached_from_file(this, this.searchUrl)
				.then(options =>
				{
					// Filter the options
					const lower_search = search.toLowerCase();
					const filtered = options.filter(option =>
					{
						return option.label.toLowerCase().includes(lower_search) || option.value.includes(search)
					});
					// Limit results
					const totalCount = filtered.length;
					if(filtered.length > Et2WidgetWithSearch.RESULT_LIMIT)
					{
						filtered.splice(Et2WidgetWithSearch.RESULT_LIMIT);
					}
					// Add the matches
					this.processRemoteResults(filtered, totalCount);
					return filtered;
				})
				.catch((_err) =>
				{
					this.egw().message(_err.statusText || this.searchUrl, "error");
					return [];
				});
		}

		/**
		 * Actually query the server.
		 *
		 * This can be overridden to change request parameters or eg. send them as POST parameters.
		 *
		 * Default implementation here sends search string and options:
		 * - as two parameters to the AJAX function
		 * - and (additional) as GET parameters plus search string as "query"
		 *
		 * This is done to support as well the old taglist callbacks, as the regular select ones!
		 *
		 * @param {string} search
		 * @param {object} options
		 * @returns {any}
		 * @protected
		 */
		protected remoteQuery(search : string, options : object) : Promise<SelectOption[]>
		{
			// Include a limit, even if options don't, to avoid massive lists breaking the UI
			let sendOptions = {
				num_rows: Et2WidgetWithSearch.RESULT_LIMIT,
				...options
			}
			return this.egw().request(this.egw().link(this.egw().ajaxUrl(this.egw().decodePath(this.searchUrl)),
				{query: search, ...sendOptions}), [search, sendOptions]).then((results) =>
			{
				// If results have a total included, pull it out.
				// It will cause errors if left in the results
				let total = null;
				if(typeof results.total !== "undefined")
				{
					total = results.total;
					delete results.total;
				}
				let entries = cleanSelectOptions(results);
				this.processRemoteResults(entries, total);
				return entries;
			});
		}

		/**
		 * Add in remote results
		 * @param results
		 * @param totalResults If there are more results than were returned, total number of matches
		 * @protected
		 */
		protected processRemoteResults(entries, totalResults = 0)
		{
			if(!entries?.length)
			{
				return Promise.resolve();
			}
			// Add a "remote" class so we can tell these apart from any local results
			entries.forEach((entry) => entry.class = (entry.class || "") + " remote");

			let target = this._optionTargetNode || this;
			if(target)
			{
				// Add in remote options, avoiding duplicates
				this.select_options.filter(function(item)
				{
					let i = entries.findIndex(x => (x.value == item.value));
					if(i <= -1)
					{
						entries.push(item);
					}
					return null;
				});

				this._remote_options = entries;
				this.requestUpdate("select_options");
				return;

				let options = html`${entries.map(this._optionTemplate.bind(this))}`;

				/**
				 * Add in new options.
				 * Rendering directly into target will remove existing options, which we don't need to do
				 */

				let temp_target = document.createElement("div");
				let resultCount = entries.length;

				render(options, temp_target);
				return Promise.all(([...temp_target.querySelectorAll(":scope > *")].map(item => item.render)))
					.then(() =>
					{
						temp_target.querySelectorAll(":scope > *").forEach((item) =>
						{
							// Avoid duplicate error
							if(!target.querySelector("[value='" + ('' + item.value).replace(/'/g, "\\\'") + "']"))
							{
								target.appendChild(item);
							}
						})
					})
					.then(() =>
					{
						if(totalResults && totalResults > resultCount)
						{
							// More results available that were not sent
							let count = document.createElement("span")
							count.classList.add("remote");
							count.textContent = this.egw().lang("%1 more...", totalResults - resultCount);
							target.appendChild(count);
						}
					});
			}
		}

		/**
		 * Check if one of our [local] items matches the search
		 *
		 * @param search
		 * @param item
		 * @returns {boolean}
		 * @protected
		 */
		protected searchMatch(search, item) : boolean
		{
			if(!item || !item.value)
			{
				return false;
			}
			if(item.textContent?.toLowerCase().includes(search.toLowerCase()))
			{
				return true;
			}
			if(typeof item.value == "string")
			{
				return item.value.includes(search.toLowerCase());
			}
			return item.value == search;
		}

		/**
		 * Create an entry that is not in the options and add it to the value
		 *
		 * @param {string} text Used as both value and label
		 */
		public createFreeEntry(text : string) : boolean
		{
			if(!text || !this.validateFreeEntry(text))
			{
				return false;
			}
			// Make sure not to double-add
			if(!this.querySelector("[value='" + text.replace(/'/g, "\\\'") + "']") && !this.__select_options.find(o => o.value == text))
			{
				this.__select_options.push(<SelectOption>{
					value: text.trim(),
					label: text.trim(),
					class: "freeEntry"
				});
				this.requestUpdate('select_options');
			}

			// Make sure not to double-add, but wait until the option is there
			this.updateComplete.then(() =>
			{
				if(this.multiple && this.value.indexOf(text) == -1)
				{
					this.value.push(text);
				}
				else if(!this.multiple && this.value !== text)
				{
					this.value = text;
				}
				this.requestUpdate("value");
			});

			// If we were overlapping edit inputbox with the value display, reset
			if(!this.readonly && this._activeControls?.classList.contains("novalue"))
			{
				this._searchInputNode.style.display = "";
			}
			return true;
		}

		/**
		 * Check if a free entry value is acceptable.
		 * We use validators directly using the proposed value
		 *
		 * @param text
		 * @returns {boolean}
		 */
		public validateFreeEntry(text) : boolean
		{
			let validators = [...this.validators, ...this.defaultValidators];
			let result = validators.filter(v =>
				v.execute(text, v.param, {node: this}),
			);
			return validators.length > 0 && result.length == 0 || validators.length == 0;
		}

		public handleTagEdit(event)
		{
			let value = event.target.value;
			let original = event.target.dataset.original_value;

			if(!value || !this.allowFreeEntries || !this.validateFreeEntry(value))
			{
				// Not a good value, reset it.
				event.target.variant = "danger"
				return false;
			}

			event.target.variant = "success";

			// Add to internal list
			this.createFreeEntry(value);

			// Remove original from value & DOM
			if(value != original)
			{
				if(this.multiple)
				{
					this.value = this.value.filter(v => v !== original);
				}
				else
				{
					this.value = value;
				}
				this.querySelector("[value='" + original.replace(/'/g, "\\\'") + "']")?.remove();
				this.__select_options = this.__select_options.filter(v => v.value !== original);
			}
		}

		/**
		 * Start editing the current value if multiple=false
		 *
		 * @param {Et2Tag} tag
		 */
		public startEdit(tag? : Et2Tag)
		{
			const tag_value = tag ? tag.value : this.value;

			// hide the menu
			this.dropdown.hide()

			waitForEvent(this, "sl-after-hide").then(() =>
			{
				// Turn on edit UI
				this._activeControls.classList.add("editing", "active");

				// Pre-set value to tag value
				this._editInputNode.style.display = "";
				this._editInputNode.value = tag_value
				this._editInputNode.focus();

				// If they abort the edit, they'll want the original back.
				this._editInputNode.dataset.initial = tag_value;
			})
		}

		protected stopEdit(abort = false)
		{
			// type to select will focus matching entries, but we don't want to stop the edit yet
			if(typeof abort == "object" && abort.type == "blur")
			{
				if(abort.relatedTarget?.localName == this.optionTag)
				{
					return;
				}
				// Edit lost focus, accept changes
				abort = false;
			}

			const original = this._editInputNode.dataset.initial;
			delete this._editInputNode.dataset.initial;

			let value = abort ? original : this._editInputNode.value;
			this._editInputNode.value = "";

			if(value && value != original)
			{
				this.createFreeEntry(value);

				this.updateComplete.then(() =>
				{
					const item = this.querySelector("[value='" + value.replace(/'/g, "\\\'") + "']");
					item.dispatchEvent(new CustomEvent("sl-select", {detail: {item}}));
				})
			}

			// Remove original from value & DOM
			if(value != original)
			{
				if(this.multiple)
				{
					this.value = this.value.filter(v => v !== original);
					this.querySelector("[value='" + original.replace(/'/g, "\\\'") + "']")?.remove();
				}
				else
				{
					this.value = value;
				}
				this.select_options = this.select_options.filter(v => v.value !== original);
			}


			this._activeControls.classList.remove("editing", "active");
			if(!this.multiple)
			{
				this.updateComplete.then(async() =>
				{
					// Don't know why, but this doesn't always work leaving the value hidden by prefix
					await this.dropdown.hide();
					this.dropdown.classList.remove("select--open");
					this.dropdown.panel.setAttribute("hidden", "");
				});
			}
		}

		protected _handleSearchAbort(e)
		{
			this._activeControls.classList.remove("active");
			this.clearSearch();
		}

		/**
		 * et2-searchbox (SlInput) sends out an event on change.
		 * We don't care, and if we let it bubble it'll get in the way.
		 * @param e
		 * @protected
		 */
		protected _handleSearchChange(e)
		{
			e.stopImmediatePropagation();
			e.preventDefault();
			return false;
		}
	}

	return Et2WidgetWithSearch as unknown as Constructor<SearchMixinInterface> & T;
}