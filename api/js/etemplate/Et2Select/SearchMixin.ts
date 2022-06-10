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
				:host([allowFreeEntries]) ::slotted([slot="suffix"]) {
					display: none;
				}
				
				/* Make textbox take full width */
				::slotted([name="search_input"]:focus ){
					width: 100%;
				}
				:host([search]) .select--open .select__prefix {
					flex: 2 1 auto;
					width: 100%;
				}
				
				/* Search textbox general styling, starts hidden */
				.select__prefix ::slotted(.search_input) {
					display: none;
					margin-left: 0px;
					width: 100%;
				}
				/* Search UI active - show textbox & stuff */
				::slotted(.search_input.active) {
					display: flex;
				}
				
				/* Hide options that do not match current search text */
				::slotted(.no-match) {
					display: none;
				}
				
				/* Keep overflow tag right-aligned.  It's the only sl-tag. */
				 .select__tags sl-tag {
					margin-left: auto;
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

		constructor(...args : any[])
		{
			super(...args);

			this.search = false;
			this.searchUrl = "";
			this.searchOptions = {};

			this.allowFreeEntries = false;
			this.editModeEnabled = false;

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

			this._handleSearchButtonClick = this._handleSearchButtonClick.bind(this);
			this._handleSearchAbort = this._handleSearchAbort.bind(this);
			this._handleSearchKeyDown = this._handleSearchKeyDown.bind(this);
			this.handleTagInteraction = this.handleTagInteraction.bind(this);
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

		/**
		 * Add the nodes we need to search
		 *
		 * @protected
		 *
		 * NB: Not sure which is the best way yet, SlotMixin or using render()
		 */
		protected _addNodes()
		{
			const div = document.createElement("div");
			div.classList.add("search_input");
			div.slot = "prefix";
			render(this._searchInputTemplate(), div);
			this.appendChild(div);
		}

		protected _searchInputTemplate()
		{
			// I can't figure out how to get this full width via CSS
			return html`
                <input type="text" part="input" style="width:100%" @keydown=${this._handleSearchKeyDown}/>
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
			return this.querySelector(".search_input input");
		}

		protected get _activeControls()
		{
			return this.querySelector(".search_input");
		}

		protected get menuItems()
		{
			return this.querySelectorAll("sl-menu-item");
		}

		/**
		 * Only local options, excludes server options
		 *
		 * @protected
		 */
		protected get localItems()
		{
			return this.querySelectorAll("sl-menu-item:not(.remote)");
		}

		protected get remoteItems()
		{
			return this.querySelectorAll("sl-menu-item.remote");
		}

		get value()
		{
			return super.value;
		}

		set value(new_value : string | string[])
		{
			super.value = new_value;

			// Overridden to add options if allowFreeEntries=true
			if(this.allowFreeEntries)
			{
				if(typeof this.value == "string" && !this.select_options.find(o => o.value == value))
				{
					this.createFreeEntry(value);
				}
				else
				{
					this.value.forEach((e) =>
					{
						if(!this.select_options.find(o => o.value == e))
						{
							this.createFreeEntry(e);
						}
					});
				}
			}
		}

		getItems()
		{
			return [...this.querySelectorAll("sl-menu-item:not(.no-match)")];
		}

		protected _bindListeners()
		{
			this.addEventListener("sl-blur", this._handleSearchAbort);
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

				// Hide the label for the currently selected value - it shows as checked in list
				// and we want the space
				this.shadowRoot.querySelector("[part='display-label']").style.display = "none";
			}
		}

		handleMenuHide()
		{
			super.handleMenuHide();
			if(this.searchEnabled)
			{
				this._activeControls?.classList.remove("active");

				// Restore selected value visibility
				this.shadowRoot.querySelector("[part='display-label']").style.display = "";
			}
		}

		handleTagInteraction(event : KeyboardEvent | MouseEvent)
		{
			let result = super.handleTagInteraction(event);

			// Check if remove button was clicked
			const path = event.composedPath();
			const clearButton = path.find((el) =>
			{
				if(el instanceof HTMLElement)
				{
					const element = el as HTMLElement;
					return element.classList.contains('tag__remove');
				}
				return false;
			});

			// No edit, or removed tag
			if(!this.editModeEnabled || clearButton)
			{
				return;
			}

			// Find the tag
			const tag = <Et2Tag>path.find((el) => el instanceof Et2Tag);
			this.startEdit(tag);
		}

		/**
		 * Value was cleared
		 */
		_handleClear()
		{
			// Restore label styling
			this.shadowRoot.querySelector("[part='display-label']").style.display = "";
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
			if(event.key === "Enter")
			{
				event.preventDefault();
				if(this.allowFreeEntries && this.createFreeEntry(this._searchInputNode.value))
				{
					this._searchInputNode.value = "";
					if(!this.multiple)
					{
						this.dropdown.hide();
					}
				}
				else
				{
					this.startSearch();
				}
			}

			// Start the search automatically if they have enough letters
			clearTimeout(this._searchTimeout);
			if(this._searchInputNode.value.length >= Et2WidgetWithSearch.MIN_CHARS)
			{
				this._searchTimeout = window.setTimeout(() => {this.startSearch()}, Et2WidgetWithSearch.SEARCH_TIMEOUT);
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
				entries = this.select_options.concat(entries);

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
			}
			// Make sure not to double-add
			if(this.multiple && this.value.indexOf(text) == -1)
			{
				this.value.push(text);
			}
			else if(!this.multiple)
			{
				this.value = text;
			}
			this.requestUpdate('select_options');
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

		public startEdit(tag : Et2Tag)
		{
			// Turn on edit UI
			this.handleMenuShow();

			// but hide the menu
			this.updateComplete.then(() => this.dropdown.hide());

			// Pre-set value to tag value
			this._searchInputNode.value = tag.textContent.trim();

			// Remove from value & DOM.  If they finish the edit, the new one will be added.
			this.value = this.value.filter(v => v !== this._searchInputNode.value);
			tag.remove();
		}

		protected _handleSearchButtonClick(e)
		{
			this.handleMenuShow();
		}

		/**
		 * Override this method from SlSelect to stick our own tags in there
		 */
		syncItemsFromValue()
		{
			if(typeof super.syncItemsFromValue === "function")
			{
				super.syncItemsFromValue();
			}

			// Only applies to multiple
			if(typeof this.displayTags !== "object" || !this.multiple)
			{
				return;
			}

			let overflow = null;
			if(this.maxTagsVisible > 0 && this.displayTags.length > this.maxTagsVisible)
			{
				overflow = this.displayTags.pop();
			}
			const checkedItems = Object.values(this.menuItems).filter(item => this.value.includes(item.value));
			this.displayTags = checkedItems.map(item => this._tagTemplate(item));

			// Re-slice & add overflow tag
			if(overflow)
			{
				this.displayTags = this.displayTags.slice(0, this.maxTagsVisible);
				this.displayTags.push(overflow);
			}
		}

		/**
		 * Customise how tags are rendered.  This overrides what SlSelect
		 * does in syncItemsFromValue().
		 * This is a copy+paste from SlSelect.syncItemsFromValue().
		 *
		 * @param item
		 * @protected
		 */
		protected _tagTemplate(item)
		{
			return html`
                <et2-tag
                        part="tag"
                        exportparts="
              base:tag__base,
              content:tag__content,
              remove-button:tag__remove-button
            "
                        variant="neutral"
                        size=${this.size}
                        ?pill=${this.pill}
                        removable
                        @click=${this.handleTagInteraction}
                        @keydown=${this.handleTagInteraction}
                        @sl-remove=${(event) =>
                        {
                            event.stopPropagation();
                            if(!this.disabled)
                            {
                                item.checked = false;
                                this.syncValueFromItems();
                            }
                        }}
                >
                    ${this.getItemLabel(item)}
                </et2-tag>
			`;
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