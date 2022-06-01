/**
 * EGroupware eTemplate2 - SearchMixin
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css, dedupeMixin, html, LitElement, render, repeat, SlotMixin} from "@lion/core";
import {cleanSelectOptions, SelectOption} from "./FindSelectOptions";


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
export const Et2WithSearchMixin = dedupeMixin((superclass) =>
{
	class Et2WidgetWithSearch extends SlotMixin(superclass)
	{
		static get properties()
		{
			return {
				...super.properties,
				search: {type: Boolean, reflect: true},

				searchUrl: {type: String},

				allowFreeEntries: {type: Boolean},

				searchOptions: {type: Object}
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
				::slotted([slot="suffix"]) {
					display: none;
				}
				:host([search]) ::slotted([slot="suffix"]) {
					display: initial;
				}
				::slotted([name="search_input"]:focus ){
					width: 100%;
				}
				.select__prefix ::slotted(.search_input) {
					display: none;
					margin-left: 0px;
					width: 100%;
				}
				::slotted(.search_input.active) {
					display: flex;
				}
				::slotted(.no-match) {
					display: none;
				}
				`
			]
		}

		private _searchTimeout : number;
		protected static SEARCH_TIMEOUT = 500;
		protected static MIN_CHARS = 2;

		constructor(...args : any[])
		{
			super(...args);

			this.search = false;
			this.searchUrl = "";
			this.searchOptions = {};

			this._handleSearchButtonClick = this._handleSearchButtonClick.bind(this);
			this._handleSearchAbort = this._handleSearchAbort.bind(this);
			this._handleSearchKeyDown = this._handleSearchKeyDown.bind(this);
		}

		connectedCallback()
		{
			super.connectedCallback();

			// Missing any of the required attributes?  Don't change anything.
			if(!this.search && !this.searchUrl && !this.allowFreeEntries)
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
			return html`
                <input type="text" @keydown=${this._handleSearchKeyDown}/>
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

		getItems()
		{
			return [...this.querySelectorAll("sl-menu-item:not(.no-match)")];
		}

		protected _bindListeners()
		{
			this.addEventListener("sl-blur", this._handleSearchAbort);
			this._searchButtonNode.addEventListener("click", this._handleSearchButtonClick);
		}

		protected _unbindListeners()
		{
			this.removeEventListener("sl-blur", this._handleSearchAbort);
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
				// If there's only one option, select it
				if(this.getItems().length === 1)
				{
					this.getItems()[0].click();
					this.dropdown.hide();
					return;
				}
				event.preventDefault();
				this.startSearch();
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

		protected remoteQuery(search : string, options : object)
		{
			return this.egw().request(this.searchUrl, [search]).sendRequest().then((result) =>
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
			entries.forEach((entry) => entry.class = entry.class += "remote");

			let target = this._optionTargetNode || this;
			if(target)
			{
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
		}
	}

	return Et2WidgetWithSearch as Constructor<SearchMixinInterface> & T & LitElement;
});