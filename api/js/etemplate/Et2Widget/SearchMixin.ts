import {html, LitElement, nothing, TemplateResult} from "lit";
import {state} from "lit/decorators/state.js";
import {property} from "lit/decorators/property.js";
import {repeat} from "lit/directives/repeat.js";
import {until} from "lit/directives/until.js";
import type {IegwAppLocal} from "../../jsapi/egw_global";
import {Et2InputWidgetInterface} from "../Et2InputWidget/Et2InputWidget";
import {classMap} from "lit/directives/class-map.js";


/**
 * Type for "search result" data
 * Search results are shown in a list, and the user can choose one or more of them
 */
export type SearchResult = {
	value : string
	label : string;
	// Hover help text
	title? : string;
	// Related image or icon
	icon? : string;
	// Class applied to node
	class? : string;
	// Show the item, but it is not selectable.
	// If multiple=true and the item is in the value, it is not removable.
	disabled? : boolean;
	// If a search is in progress, does this option match.
	// Automatically changed.
	isMatch? : boolean;

	// The item has children (option group)
	hasChildren? : boolean,
	// The item's children
	children? : SearchResult[]
}

/**
 * We expect the server to respond with data in this format
 */
export interface SearchResultsInterface<DataType extends SearchResult>
{
	/* Results matching the search & searchOptions */
	results : DataType[],

	/* The total number of matching results */
	total : number

	/* Message from the search like "Access denied" */
	message? : string
}

export declare class SearchMixinInterface<DataType extends SearchResult, Results extends SearchResultsInterface<DataType>>
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
	 * Additional search options passed to the search functions
	 *
	 * @type {object}
	 */
	searchOptions : object;

	/**
	 * Start the search process
	 */
	startSearch() : Promise<void>

	/**
	 * Check to see if a local search result matches the search string
	 *
	 * @param {string} search
	 * @param {DataType} result
	 * @returns {boolean}
	 */
	searchMatch<DataType>(search : string, result : DataType) : boolean

	/**
	 * Search local options
	 */
	protected localSearch<DataType>(search : string, options : object) : Promise<DataType[]>

	/**
	 * Search remote options.
	 * If searchUrl is not set, it will return very quickly with no results
	 */
	protected remoteSearch<DataType>(search : string, options : object) : Promise<DataType[]>

	/**
	 * Deal with search results, processing & updating based on what the search returned
	 *
	 * @param {Results} results
	 * @protected
	 */
	protected processResults<DataType>(results : Results) : DataType[]

	/**
	 * Deal with remote results specifically
	 */
	protected processLocalResults<DataType>(results : Results) : DataType[]

	/**
	 * Deal with remote results specifically
	 */
	protected processRemoteResults<DataType>(results : Results) : DataType[]

	/**
	 * This method will be called whenever the selection changes. It will update the selected search result cache.
	 * Override it in your class and include the user's selection in your value.
	 */
	protected searchResultSelected()
}

/* Whatever element you use to show the search results should implement this interface */
export interface SearchResultElement
{
	// Value of the option
	value : string,
	// Text displayed to user
	label : string,
	// Element cannot be selected
	disabled? : boolean,
	// Element is highlighted for interaction
	current? : boolean,
	// Element has been selected for inclusion in the value
	selected? : boolean
}

type Constructor<T = {}> = new (...args : any[]) => T;


/**
 * @summary Strongly typed mixin for asking the server for values that match a string the user types in and displaying those
 * matches for the user to choose from.
 *
 * # How to use this mixin:
 * ## Extend:
 * export class MySearchingWidget extends SearchMixin(...)
 *
 * ## Override:
 * These methods must be overridden:
 * searchResultSelected() - Called when the user has selected a search result. You need to call super.selectionChanged(), then
 * update your value from `this.selectedResults`.
 *
 * ```ts
 * protected searchResultSelected() {
 * 	super.searchResultSelected();
 * 	this.value = this.selectedResults[0];
 * }
 * ```
 * Other methods can be overridden if needed.
 *
 * ## Render:
 * ```
 * render() {
 * return html`
 * 	...
 * 	${this.searchInputTemplate()}
 * 	...
 * 	${this.searchResultsTemplate()}
 * 	...
 * `;
 * }
 * Call `searchInputTemplate()` and `searchResultsTemplate()` from your `render()` method
 *
 *
 * @param {T} superClass
 * @returns {Constructor<SearchMixinInterface<DataType, Results>> & T}
 * @constructor
 *
 * @event et2-select - Emitted when the selection changes
 */
export const SearchMixin = <T extends Constructor<Et2InputWidgetInterface &
	{ egw() : IegwAppLocal, noLang : boolean } & LitElement>,
	DataType extends SearchResult, Results extends SearchResultsInterface<DataType>>(superClass : T) =>
{
	class SearchMixinClass extends superClass
	{
		/**
		 * Enable or disable searching
		 */
		@property({type: Boolean}) search : boolean = true;
		/**
		 * Get [additional] options from the server when you search, instead of just searching in the browser
		 */
		@property() searchUrl : string = "";
		/**
		 * Additional search parameters that are passed to the server
		 * when we query searchUrl
		 */
		@property({type: Object}) searchOptions = {};

		/**
		 * Indicates whether the search results are open. You can toggle this attribute to show and hide the results list.
		 */
		@property({type: Boolean, reflect: true}) resultsOpen = false;

		// A search is currently in progress
		@state() searching = false;
		// The component has the focus
		@state() hasFocus = false;
		// For keyboard navigation of search results
		@state() currentResult : LitElement & SearchResultElement = null;
		// Search result nodes marked as "selected"
		@state() selectedResults : (HTMLElement & SearchResultElement)[] = [];

		// You can set specific class options here.  They will be overridden by searchOptions.
		protected _classSearchOptions = {};

		protected _totalResults : number = 0;
		protected _searchTimeout : number;
		protected _searchPromise : Promise<DataType[]> = Promise.resolve(<DataType[]>[]);
		protected _searchResults : DataType[] = [];

		// Input where user types to filter
		protected get _searchNode() : HTMLInputElement { return this.shadowRoot.querySelector("#search");}

		// Element where we render the search results
		protected get _listNode() : HTMLElement { return this.shadowRoot.querySelector("#listbox");}

		protected get _resultNodes() : (LitElement & SearchResultElement)[] { return this._listNode ? Array.from(this._listNode.querySelectorAll(":scope > :not(div)")) : [];}

		constructor(...args : any[])
		{
			super(...args);

			this.handleResultsKeyDown = this.handleResultsKeyDown.bind(this);
			this.handleSuggestionsMouseUp = this.handleSuggestionsMouseUp.bind(this);
		}

		/**
		 * Start searching for results matching what has been typed
		 */
		public async startSearch()
		{
			// Stop timeout timer
			clearTimeout(this._searchTimeout);

			this._totalResults = 0;
			this._searchResults = [];

			// Clear current option, it's probably going to go away
			this.setCurrentResult(null);

			this.searching = true;
			this.resultsOpen = true;
			this.requestUpdate("searching");

			// Start the searches
			this._searchPromise = Promise.all([
				this.localSearch(this._searchNode?.value ?? "", this.searchOptions),
				this.remoteSearch(this._searchNode?.value ?? "", this.searchOptions)
			]).then(async() =>
			{
				this.searching = false;
				this.requestUpdate("searching", true);
				if(!this.resultsOpen && this.hasFocus)
				{
					this.resultsOpen = true;
					this.requestUpdate("resultsOpen");
				}

				await this.updateComplete;

				this.currentResult = this._resultNodes[0];

				return this._searchResults;
			});
		}

		public getValueAsArray()
		{
			if(Array.isArray(this.value))
			{
				return this.value;
			}
			if(this.value == "null" || this.value == null || typeof this.value == "undefined" || this.value == "")
			{
				return [];
			}
			return [this.value];
		}

		/**
		 * Check if one of our [local] items matches the search
		 *
		 * @param search
		 * @returns {boolean}
		 * @protected
		 */
		public searchMatch<DataType extends SearchResult>(search : string, searchOptions : Object, option : DataType) : boolean
		{
			if(!option || !option.value)
			{
				return false;
			}
			// Search all string fields
			let searchString = search.toLowerCase();
			const searchFields = ["label", "value", "title"]
			for(let i = 0; i < searchFields.length; i++)
			{
				let field = searchFields[i];
				if(option[field]?.toLowerCase().includes(searchString))
				{
					return true;
				}
			}

			return false;
		}

		/**
		 * If you have a local list of options, you can search through them on the client and include them in the results.
		 *
		 * This is done independently from the server-side search, and the results are merged.
		 *
		 * @param {string} search
		 * @param {object} searchOptions
		 * @returns {Promise<any[]>}
		 * @protected
		 */
		protected localSearch<DataType extends SearchResult>(search : string, searchOptions : object, localOptions : DataType[] = []) : Promise<DataType[]>
		{
			const local : Results = <Results><unknown>{
				results: <DataType[]>[],
				total: 0
			}
			let doSearch = <DataType extends SearchResult>(options : DataType[], value : string) =>
			{
				options.forEach((option) =>
				{
					if(typeof option !== "object")
					{
						return;
					}
					if(this.searchMatch<DataType>(value, searchOptions, option))
					{
						local.results.push(option);
						local.total++;
					}
					if(typeof option.children != "undefined" && Array.isArray(option.children))
					{
						return doSearch(option.children, value);
					}
				});
			};
			doSearch(localOptions, search);

			return Promise.resolve(this.processLocalResults(local));
		}

		protected remoteSearch<DataType>(search : string, options : object) : Promise<DataType[]>
		{
			// If no searchUrl, no search
			if(!this.searchUrl)
			{
				return Promise.resolve(<DataType[]><unknown>[]);
			}

			// Include a limit by default to avoid massive lists breaking the UI
			// This can be overridden by setting a different limit in this.searchOptions
			let sendOptions = {
				num_rows: 100,
				...this._classSearchOptions,
				...options
			}
			return this.egw().request(this.egw().link(this.egw().ajaxUrl(this.egw().decodePath(this.searchUrl)),
				{query: search, ...sendOptions}), [search, sendOptions]).then((results : Results) =>
			{
				return this.processRemoteResults(results);
			});
		}

		/**
		 * Handle the search results from wherever we got them
		 *
		 * @param {Results} results
		 * @protected
		 */
		protected processResults(results : Results)
		{
			// Look through results, we may reject some
			for(let i = results.results.length - 1; i >= 0; i--)
			{
				const entry = results.results[i];

				// Filter to avoid duplicates
				if(this._searchResults.some(o => o.value == entry.value))
				{
					results.total--;
					results.results.splice(i, 1);
				}
			}

			this._searchResults.splice(this._searchResults.length, 0, ...results.results)

			this._totalResults += results.total;
			if(typeof results.message)
			{
				//this.statustext = results.message;
			}

			this.requestUpdate();
		}

		/**
		 * Process local results
		 */
		protected processLocalResults<DataType>(results : Results) : DataType[]
		{
			this.processResults(results);

			return <DataType[]><unknown>results?.results ?? <DataType[]>[];
		}

		/**
		 * Process remote results
		 *
		 * Any results that already exist will be removed to avoid duplicates
		 *
		 * @param results
		 * @protected
		 * @internal
		 */
		protected processRemoteResults<DataType>(results : Results) : DataType[]
		{
			this.processResults(results);

			return <DataType[]><unknown>results?.results ?? <DataType[]>[];
		}

		/**
		 * Sets the current search result, which is the one the user is currently interacting with (e.g. via keyboard).
		 * Only one result may be "current" at a time.
		 */
		private setCurrentResult(result : LitElement & SearchResultElement | null)
		{
			// Clear selection
			this._resultNodes.forEach((el) =>
			{
				el.current = false;
				el.tabIndex = -1;
				el.requestUpdate("current");
			});

			// Select the target option
			if(result)
			{
				this.currentResult = result;
				result.current = true;
				result.tabIndex = 0;
				result.focus();
				result.requestUpdate("current");
			}
		}


		/**
		 * Toggles a search result's selected state
		 */
		protected toggleResultSelection(result : HTMLElement & SearchResultElement, force? : boolean)
		{
			if(force === true || force === false)
			{
				result.selected = force;
			}
			else
			{
				result.selected = !result.selected;
			}

			if(result instanceof LitElement)
			{
				result.requestUpdate("selected");
			}
			this.searchResultSelected();
		}

		/**
		 * This method must be called whenever the selection changes. It will update the selected file cache, the current
		 * value, and the display value
		 */
		protected searchResultSelected()
		{
			// Update selected files cache
			this.selectedResults = this._resultNodes.filter(el => el.selected);

			/**
			 * Update the value:
			 * eg:
			if(this.multiple && typeof this.value !== "undefined")
			{
				this.value = this.selectedResults.map(el => el.value);
			}
			else if (typeof this.value !== "undefined")
			{
				this.value = [this.selectedResults[0]?.value] ?? [];
			}
			 */

			this.updateComplete.then(() =>
			{
				this.dispatchEvent(new Event("et2-select"));
			})
		}


		/**
		 * Keyboard events from the search results list
		 *
		 * @param {KeyboardEvent} event
		 */
		protected handleResultsKeyDown(event : KeyboardEvent)
		{
			// Navigate options
			if(["ArrowUp", "ArrowDown", "Home", "End"].includes(event.key))
			{
				event.stopPropagation()
				const suggestions = this._resultNodes;
				const currentIndex = suggestions.indexOf(this.currentResult);
				let newIndex = Math.max(0, currentIndex);

				// Prevent scrolling
				event.preventDefault();

				if(event.key === "ArrowDown")
				{
					newIndex = currentIndex + 1;
					if(newIndex > suggestions.length - 1)
					{
						newIndex = suggestions.length - 1;
					}
				}
				else if(event.key === "ArrowUp")
				{
					newIndex = currentIndex - 1;
					if(newIndex < 0)
					{
						this.setCurrentResult(null);
						this._searchNode.focus();
					}
				}
				else if(event.key === "Home")
				{
					newIndex = 0;
				}
				else if(event.key === "End")
				{
					newIndex = suggestions.length - 1;
				}

				this.setCurrentResult(suggestions[newIndex]);
			}
			// Close results on escape
			else if(["Escape"].includes(event.key))
			{
				this.resultsOpen = false;
				this._searchNode.focus();
			}
			else if([" ", "Enter"].includes(event.key) && this.currentResult)
			{
				event.preventDefault();
				this.currentResult.selected = true;
				this.searchResultSelected();
			}
		}


		handleSearchKeyDown(event)
		{
			clearTimeout(this._searchTimeout);

			// Tab on empty leaves
			if(this._searchNode.value == "" && event.key == "Tab")
			{
				// Propagate, browser will do its thing
				return;
			}
			// Up / Down navigates options
			if(['ArrowDown', 'ArrowUp'].includes(event.key) && this._searchResults.length)
			{
				if(!this.resultsOpen)
				{
					this.resultsOpen = true;
					this.requestUpdate("resultsOpen", false)
				}
				event.stopPropagation();
				this.setCurrentResult(this.currentResult ?? this._resultNodes[0]);
				return;
			}

			// Start search immediately
			else if(event.key == "Enter")
			{
				event.preventDefault();
				this.startSearch();
				return;
			}
			else if(event.key == "Escape")
			{
				this.resultsOpen = false;
				this.requestUpdate("resultsOpen", true)
				event.stopPropagation();
				return;
			}

			// Start the search automatically if they have enough letters
			if(this._searchNode.value.length > 0)
			{
				this._searchTimeout = window.setTimeout(() => {this.startSearch()}, 500);
			}
		}

		/**
		 * Mouse up from the suggestion list
		 * @param event
		 */
		protected handleSuggestionsMouseUp(event : MouseEvent)
		{
			const target = <HTMLElement & SearchResultElement>event.target;
			if(typeof target?.value == "undefined")
			{
				return;
			}

			this.toggleResultSelection(target);

			this._searchNode.value = "";
		}

		/**
		 * Show all the search results
		 * Include this in your render()
		 *
		 * @returns {TemplateResult<1>}
		 * @protected
		 */
		protected searchResultsTemplate()
		{
			return html`
                <div
                        id="listbox"
                        role="listbox"
                        aria-expanded=${this.resultsOpen ? 'true' : 'false'}
                        aria-labelledby="label"
                        part="listbox"
                        class="search__results"
                        tabindex="-1"
                        @keydown=${this.handleResultsKeyDown}
                        @mouseup=${this.handleSuggestionsMouseUp}
                >
                    ${this.resultsTemplate()}
                </div>`;
		}

		protected searchInputTemplate()
		{
			return html`
                <input id="search" type="text" part="input"
                       class="search__input"
                       autocomplete="off"
                       ?disabled=${this.disabled}
                       ?readonly=${this.readonly}
                       placeholder="${this.hasFocus || this.value && this.value.length > 0 || this.disabled || this.readonly ? "" : this['placeholder']}"
                       tabindex="0"
                       @keydown=${this.handleSearchKeyDown}
                />
			`;
		}

		protected resultsTemplate()
		{
			const empty = this._searchResults.length == 0;

			const promise = this._searchPromise.then(() =>
			{
				return html`
                    ${empty ? this.noResultsTemplate() : html`
                        ${repeat(this._searchResults, (result) => result.value, (result, index) => this.resultTemplate(result, index))}
                        ${until(this.moreResultsTemplate(), nothing)}
                    `
                    }`;
			});
			return html`
                ${until(promise, html`
                    <div class="search__loading">
                        <sl-spinner></sl-spinner>
                    </div>`)}`;
		}

		protected resultTemplate(result : DataType, index : number) : TemplateResult
		{
			// Exclude non-matches when searching
			// unless they're already selected, in which case removing them removes them from value
			if(typeof result.isMatch == "boolean" && !result.isMatch && !this.getValueAsArray().includes(result.value))
			{
				return html``;
			}

			// We pass the DataType object along so SearchMixin can grab it if needed
			const value = (<string>result.value).replaceAll(" ", "___");
			const classes = result.class ? Object.fromEntries((result.class).split(" ").map(k => [k, true])) : {};
			return html`
                <sl-option
                        part="option"
                        exportparts="prefix:tag__prefix, suffix:tag__suffix"
                        value="${value}"
                        title="${!result.title || this.noLang ? result.title : this.egw().lang(result.title)}"
                        class=${classMap({
                            "match": this.search && (result.isMatch || false),
                            "no-match": this.search && result.isMatch == false,
                            ...classes
                        })}
                        .value=${result.value}
                        .option=${result}
                        .selected=${this.getValueAsArray().some(v => v == value)}
                        ?disabled=${result.disabled}
                >
                    ${this.iconTemplate(result)}
                    ${this.noLang ? result.label : this.egw().lang(result.label)}
                </sl-option>`;
		}

		/**
		 * Get the icon template for a given result
		 *
		 * @param option
		 * @protected
		 */
		protected iconTemplate(option : DataType)
		{
			if(!option.icon)
			{
				return html``;
			}

			return html`
                <et2-image slot="prefix" part="icon" src="${option.icon}"></et2-image>`;
		}

		protected noResultsTemplate() : TemplateResult
		{
			return html`
                <div class="search__empty">
                    <!--<et2-image src="logo"></et2-image>-->
                    ${this.egw().lang("no results match")}
                </div>`;
		}

		protected async moreResultsTemplate()
		{
			if(this._totalResults <= 0 || !this._searchPromise || !this._listNode)
			{
				return nothing;
			}
			return this._searchPromise.then(() =>
			{
				const moreCount = this._totalResults - this._searchResults.length;
				const more = this.egw().lang("%1 more...", moreCount);

				return html`${moreCount > 0 ?
							  html`
                                  <div class="search__more">${more}</div>` : nothing}`;
			});
		}

	};
	return SearchMixinClass as unknown as Constructor<SearchMixinInterface<DataType, Results>> & LitElement & T;
}