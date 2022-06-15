/**
 * EGroupware eTemplate2 - SearchMixin
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css, html, LitElement, render, repeat, SlotMixin} from "@lion/core";
import {cleanSelectOptions, SelectOption} from "./FindSelectOptions";
import {Validator} from "@lion/form-core";
import {Et2Tag} from "./Tag/Et2Tag";

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
}

/**
 * Base class for things that do search type behaviour
 * Separated to keep things a little simpler.
 *
 * Currently I assume we're extending an Et2Select, so changes may need to be made for better abstraction
 */
export const Et2WithSearchMixin = <T extends Constructor<LitElement>>(superclass : T) =>
{
	class Et2WidgetWithSearch extends SlotMixin(superclass)
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

				searchOptions: {type: Object},

				/**
				 * Allow editing tags by clicking on them.
				 * allowFreeEntries must be true
				 */
				editModeEnabled: {type: Boolean}
			}
		}

		get slots()
		{
			return {
				...super.slots,
				suffix: () =>
				{
					const input = document.createElement("sl-icon");
					input.name = "search";
					return input;
				}
			}
		}

		static get styles()
		{
			return [
				// @ts-ignore
				...(super.styles ? (Symbol.iterator in Object(super.styles) ? super.styles : [super.styles]) : []),
				css`
				/* Show / hide SlSelect icons - dropdown arrow, etc */
				::slotted([slot="suffix"]) {
					display: none;
				}
				:host([search]) ::slotted([slot="suffix"]) {
					display: initial;
				}
				/* Move the widget border */
				.form-control-input {
					border: solid var(--sl-input-border-width) var(--sl-input-border-color);
					border-radius: var(--sl-input-border-radius-medium);
				}
				.select--standard .select__control {
					border-style: none;
				}
				/* Move focus highlight */
				.form-control-input:focus-within {
					box-shadow: var(--sl-focus-ring);
				}
				.select--standard.select--focused:not(.select--disabled) .select__control {
					box-shadow: initial;
				}
				:host([allowFreeEntries]) ::slotted([slot="suffix"]) {
					display: none;
				}
				/* Make search textbox take full width */
				::slotted(.search_input), ::slotted(.search_input) input, .search_input, .search_input input {
					width: 100%;
				}
				.search_input input {
					flex: 1 1 auto;
					width: 100%;
				}
				/* Don't show the current value while searching for single, we want the space
					This lets the current value shrink to nothing so the input can expand
				 */
				.select__label {
					flex: 1 15 auto;
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
				:host([search]:not([multiple])) .select--open .select__prefix {
					flex: 2 1 auto;
					width: 100%;
				}
				
				/* Search textbox general styling, starts hidden */
				.select__prefix ::slotted(.search_input),.search_input {
					display: none;
					flex: 1 1 auto;
					margin-left: 0px;
					width: 100%;
					height: var(--sl-input-height-medium);
					position: relative;
					background-color: white;
				}
				/* Search UI active - show textbox & stuff */
				::slotted(.search_input.active),.search_input.active,
				.search_input.editing{
					display: flex;
				}
				
				/* Hide options that do not match current search text */
				::slotted(.no-match) {
					display: none;
				}
				/* Different cursor for editable tags */
				:host([allowfreeentries]) .search_tag::part(base)  {
					cursor: text;
				}
				`
			]
		}

		// Borrowed from Lion ValidatorMixin, but we don't want the whole thing
		protected defaultValidators : Validator[];
		protected validators : Validator[];

		private _searchTimeout : number;
		protected static SEARCH_TIMEOUT = 500;
		protected static MIN_CHARS = 2;
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
			this.searchOptions = {};

			this.allowFreeEntries = false;
			this.editModeEnabled = false;

			// Hiding the selected options from the dropdown means we can't un-select the tags
			// hidden by the max limit.  Prefer no limit.
			this.maxTagsVisible = -1;

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

			this._handleSelect = this._handleSelect.bind(this);
			this._handleClear = this._handleClear.bind(this);
			this._handleSearchButtonClick = this._handleSearchButtonClick.bind(this);
			this._handleDoubleClick = this._handleDoubleClick.bind(this);
			this._handleSearchAbort = this._handleSearchAbort.bind(this);
			this._handleSearchKeyDown = this._handleSearchKeyDown.bind(this);
			this._handleEditKeyDown = this._handleEditKeyDown.bind(this);
		}

		connectedCallback()
		{
			super.connectedCallback();

			this.classList.toggle("search", this.searchEnabled);

			// Missing any of the required attributes?  Don't change anything.
			if(!this.searchEnabled)
			{
				return;
			}

			this._addNodes();
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

			// If searchURL is set, turn on search
			if(changedProperties.has("searchUrl") && this.searchUrl)
			{
				this.search = true;
			}
		}

		/**
		 * Add the nodes we need to search - adjust parent shadowDOM
		 *
		 * @protected
		 */
		protected _addNodes()
		{

			const div = document.createElement("div");
			div.classList.add("search_input");
			render(this._searchInputTemplate(), div);
			if(!super.multiple)
			{
				div.slot = "prefix";
				this.appendChild(div);
				return;
			}

			super.updateComplete.then(() =>
			{
				this.menu.querySelector("slot").textContent = this.egw().lang("No suggestions");

				let control = this.shadowRoot.querySelector(".form-control-input");
				control.append(div);

				// Move menu down to make space for search
				this.dropdown.setAttribute("distance", parseInt(getComputedStyle(div).getPropertyValue("--sl-input-height-medium")));
			});
		}

		protected _searchInputTemplate()
		{
			let edit = null;
			if(this.editModeEnabled)
			{
				edit = html`<input id="edit" type="text" part="input"
                                   @keydown=${this._handleEditKeyDown}
                                   @blur=${this.stopEdit.bind(this)}
                />`;
			}
			// I can't figure out how to get this full width via CSS
			return html`
                <input id="search" type="text" part="input" style="width:100%" @keydown=${this._handleSearchKeyDown}/>
                ${edit}
			`;
		}


		/**
		 * Do we have the needed properties set, so we can actually do searching
		 *
		 * @returns {boolean}
		 */
		public get searchEnabled() : boolean
		{
			return this.search || this.searchUrl.length > 0;
		}

		protected get _searchButtonNode()
		{
			return this.querySelector("sl-icon[slot='suffix']");
		}

		protected get _searchInputNode()
		{
			return this._activeControls.querySelector("input#search");
		}

		protected get _editInputNode() : HTMLInputElement
		{
			return this._activeControls.querySelector("input#edit");
		}

		protected get _activeControls()
		{
			return this.multiple ?
				   this.shadowRoot.querySelector(".search_input") :
				   this.querySelector(".search_input");
		}


		/**
		 * Only local options, excludes server options
		 *
		 * @protected
		 */
		protected get localItems()
		{
			return this.querySelectorAll(this.optionTag + ":not(.remote)");
		}

		protected get remoteItems()
		{
			return this.querySelectorAll(this.optionTag + ".remote");
		}

		get value()
		{
			return super.value;
		}

		set value(new_value : string | string[])
		{
			super.value = new_value;

			if(!new_value)
			{
				return;
			}

			// Overridden to add options if allowFreeEntries=true
			if(this.allowFreeEntries)
			{
				if(typeof this.value == "string" && !this.menuItems.find(o => o.value == this.value))
				{
					this.createFreeEntry(this.value);
				}
				else
				{
					this.value.forEach((e) =>
					{
						if(!this.menuItems.find(o => o.value == e))
						{
							this.createFreeEntry(e);
						}
					});
				}
			}
		}

		protected _bindListeners()
		{
			this.addEventListener("sl-blur", this._handleSearchAbort);
			this.addEventListener("sl-select", this._handleSelect);
			this.addEventListener("sl-clear", this._handleClear)
			if(this._oldChange)
			{
				// Search messes up event order somehow, selecting an option fires the change event before
				// the widget is finished adjusting, losing the value
				// This is not optimal, but we need to get that change event
				this.removeEventListener("change", this._oldChange);
			}

			this._searchButtonNode.addEventListener("click", this._handleSearchButtonClick);
		}

		protected _unbindListeners()
		{
			this.removeEventListener("sl-blur", this._handleSearchAbort);
			this.removeEventListener("sl-select", this._handleSelect);
			this.removeEventListener("sl-clear", this._handleClear)
			this.removeEventListener("change", this._handleChange);
			this._searchButtonNode.removeEventListener("click", this._handleSearchButtonClick);
		}

		handleMenuShow()
		{
			super.handleMenuShow();

			if(this.searchEnabled)
			{
				this._activeControls?.classList.add("active");
				this._searchInputNode.focus();
				this._searchInputNode.select();
			}
		}

		handleMenuHide()
		{
			super.handleMenuHide();
			if(this.searchEnabled)
			{
				this._activeControls?.classList.remove("active");
			}
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
			this.startEdit(tag);
		}

		/**
		 * An option was selected
		 */
		_handleSelect(event)
		{
			// If they just chose one from the list, re-focus the search
			if(this.multiple && this.searchEnabled)
			{
				this._searchInputNode.focus();

				// Scroll the new tag into view
				if(event.detail && event.detail.item)
				{
					this.updateComplete.then(() =>
					{
						this.shadowRoot.querySelector("et2-tag[value='" + event.detail.item.value + "']").scrollIntoView();
					});
				}
			}
		}

		/**
		 * Value was cleared
		 */
		_handleClear()
		{
			if(!this.multiple && this.searchEnabled)
			{
				// Restore label styling
				this.shadowRoot.querySelector("[part='display-label']").style.display = "";

				// Start searching again
				this.updateComplete.then(() => this.handleMenuShow())
			}
		}

		/**
		 * Handle keypresses inside the search input
		 * @param {KeyboardEvent} event
		 * @protected
		 */
		protected _handleSearchKeyDown(event : KeyboardEvent)
		{
			this._activeControls?.classList.add("active");
			this.dropdown.show();

			// Pass off some keys to select
			if(['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key))
			{
				return this.handleKeyDown(event);
			}

			// Don't allow event to bubble or it will interact with select
			event.stopImmediatePropagation();
			if(Et2WidgetWithSearch.TAG_BREAK.indexOf(event.key) !== -1 && this.allowFreeEntries && this.createFreeEntry(this._searchInputNode.value))
			{
				event.preventDefault();
				this._searchInputNode.value = "";
				if(!this.multiple)
				{
					this.dropdown.hide();
				}

			}
			else if(event.key == "Enter")
			{
				event.preventDefault();
				this.startSearch();
			}
			else if(event.key == "Escape")
			{
				this._handleSearchAbort(event);
			}

			// Start the search automatically if they have enough letters
			clearTimeout(this._searchTimeout);
			if(this._searchInputNode.value.length >= Et2WidgetWithSearch.MIN_CHARS)
			{
				this._searchTimeout = window.setTimeout(() => {this.startSearch()}, Et2WidgetWithSearch.SEARCH_TIMEOUT);
			}
		}

		protected _handleEditKeyDown(event : KeyboardEvent)
		{
			if(event.key == "Enter" && this.allowFreeEntries)
			{
				event.preventDefault();
				// Stop propagation, or parent key handler will add again
				event.stopImmediatePropagation();
				this.stopEdit();
			}
			// Abort edit, put original value back
			else if(event.key == "Escape")
			{
				this.stopEdit(true);
			}
		}

		/**
		 * Start searching
		 *
		 * If we have local options, we'll search & display any matches.
		 * If serverUrl is set, we'll ask the server for results as well.
		 */
		public startSearch()
		{
			// Show a spinner instead of search button
			this._searchButtonNode.style.display = "hidden";
			let spinner = document.createElement("sl-spinner");
			spinner.slot = this._searchButtonNode.slot;
			this.appendChild(spinner);

			// Start the searches
			Promise.all([
				this.localSearch(this._searchInputNode.value, this.searchOptions),
				this.remoteSearch(this._searchInputNode.value, this.searchOptions)
			]).then(() =>
			{
				spinner.remove();
			});
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
		protected remoteSearch(search : string, options : object)
		{
			// Remove existing remote items
			this.remoteItems.forEach(i => i.remove());

			if(!this.searchUrl)
			{
				return Promise.resolve();
			}

			// Fire off the query
			let promise = this.remoteQuery(search, options);

			return promise;
		}

		/**
		 * Actually query the server.
		 *
		 * This can be overridden to change request parameters
		 *
		 * @param {string} search
		 * @param {object} options
		 * @returns {any}
		 * @protected
		 */
		protected remoteQuery(search : string, options : object)
		{
			return this.egw().request(this.searchUrl, [search]).then((result) =>
			{
				this.processRemoteResults(result);
			});
		}

		/**
		 * Add in remote results
		 * @param results
		 * @protected
		 */
		protected processRemoteResults(results)
		{
			let entries = cleanSelectOptions(results);

			// Add a "remote" class so we can tell these apart from any local results
			entries.forEach((entry) => entry.class = (entry.class || "") + " remote");

			let target = this._optionTargetNode || this;
			if(target)
			{
				// Keep local options first, add in remote options
				this.select_options.filter(function(item)
				{
					let i = entries.findIndex(x => (x.value == item.value));
					if(i <= -1)
					{
						entries.push(item);
					}
					return null;
				});

				render(html`${repeat(<SelectOption[]>entries, (option : SelectOption) => option.value, this._optionTemplate.bind(this))}`,
					target
				);
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
			if(!this.validateFreeEntry(text))
			{
				return false;
			}
			// Make sure not to double-add
			if(!this.select_options.find(o => o.value == text))
			{
				this.select_options.push(<SelectOption>{
					value: text,
					label: text
				});
				this.requestUpdate('select_options');
			}

			// Make sure not to double-add
			if(this.multiple && this.value.indexOf(text) == -1)
			{
				this.value.push(text);
			}
			else if(!this.multiple)
			{
				this.value = text;
				return;
			}

			// Once added to options, add to value / tags
			this.updateComplete.then(() =>
			{
				this.menuItems.forEach(o =>
				{
					if(o.value == text)
					{
						o.dispatchEvent(new Event("click"));
					}
				});
				this.syncItemsFromValue();
			});
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
			return result.length == 0;
		}

		/**
		 * Start editing an existing (free) tag
		 *
		 * @param {Et2Tag} tag
		 */
		public startEdit(tag : Et2Tag)
		{
			const tag_value = tag.textContent.trim();

			// hide the menu
			//this.dropdown.hide()

			// Turn on edit UI
			this._activeControls.classList.add("editing", "active");

			// Pre-set value to tag value
			this._editInputNode.value = tag_value
			this._editInputNode.focus();

			// Remove from value & DOM.  If they finish the edit, the new one will be added.
			this.value = this.value.filter(v => v !== tag_value);
			this.select_options = this.select_options.filter(v => v.value !== tag_value);
			this.querySelector("[value='" + tag_value + "']").remove();
			tag.remove();

			// If they abort the edit, they'll want the original back.
			this._editInputNode.dataset.initial = tag_value;
		}

		protected stopEdit(abort = false)
		{
			let value = abort ? this._editInputNode.dataset.initial : this._editInputNode.value;

			this.createFreeEntry(value);
			delete this._editInputNode.dataset.initial;

			this._activeControls.classList.remove("editing", "active");
		}

		protected _handleSearchButtonClick(e)
		{
			this.handleMenuShow();
		}

		protected _handleSearchAbort(e)
		{
			this._activeControls.classList.remove("active");
			this._searchInputNode.value = "";

			// Reset options.  It might be faster to re-create instead.
			this.menuItems.forEach((item) =>
			{
				item.disabled = false;
				item.classList.remove("match");
				item.classList.remove("no-match");
			})
			this.syncItemsFromValue();
		}
	}

	return Et2WidgetWithSearch as unknown as Constructor<SearchMixinInterface> & T;
}