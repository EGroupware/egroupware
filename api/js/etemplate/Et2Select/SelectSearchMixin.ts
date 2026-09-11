/**
 * EGroupware eTemplate2 - Et2Select's search
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

import {css, CSSResultGroup, html, LitElement, nothing, TemplateResult} from "lit";
import {cleanSelectOptions, SelectOption} from "./FindSelectOptions";
import {Et2Tag} from "./Tag/Et2Tag";
import {StaticOptions} from "./StaticOptions";
import {dedupeMixin} from "@open-wc/dedupe-mixin";
import {SlOption} from "@shoelace-style/shoelace";
import {Et2Textbox} from "../Et2Textbox/Et2Textbox";
import {until} from "lit/directives/until.js";
import {waitForEvent} from "../Et2Widget/event";
import {Validator} from "../Validators/Validator";
import {classMap} from "lit/directives/class-map.js";
import {property} from "lit/decorators/property.js";
import {SearchMixin, SearchResultsInterface} from "../Et2Widget/SearchMixin";
import {normalizeLegacySearchResults} from "./legacySearchResults";
// Free entries live below us in the mixin chain: we read allowFreeEntries to decide whether a tag
// break turns typed text into an entry, and our select_options getter builds on theirs.
import {FreeEntryMixinInterface} from "./FreeEntryMixin";

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
	 * Get [additional] options from the server when you search, instead of just searching existing
	 * options - a menuaction/JSON-file URL string, or a JS callback (same "supply results yourself
	 * instead of an ajax round-trip" pattern Et2Tree.autoloading already uses) returning a Promise
	 * of SelectOption[]
	 */
	searchUrl : string | ((search : string, options : object) => Promise<SelectOption[]>);

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
	localSearch(search : string, options : object) : Promise<any[]>

	/**
	 * Search remote options.
	 * If searchUrl is not set, it will return very quickly with no results
	 */
	remoteSearch(search : string, options : object) : Promise<any[]>

	/**
	 * Check a [local] item to see if it matches
	 */
	searchMatch(search : string, options : object, item : LitElement) : boolean

	/**
	 * Additional customisation location, where we stick the search elements
	 *
	 * @type {TemplateResult}
	 */
}

/**
 * Base class for things that do search type behaviour
 * Separated to keep things a little simpler.
 *
 * Currently I assume we're extending an Et2Select, so changes may need to be made for better abstraction
 */
export const SelectSearchMixin = dedupeMixin(<T extends Constructor<LitElement & FreeEntryMixinInterface>>(superclass : T) =>
{
	class SelectSearchMixinClass extends SearchMixin<Constructor<any> & typeof LitElement, SelectOption, SearchResultsInterface<SelectOption>>(<any>superclass)
	{
		@property({type: Boolean, reflect: true})
		search = false;

		/**
		 * A menuaction/JSON-file URL string, or a JS callback (same "supply results yourself
		 * instead of an ajax round-trip" pattern Et2Tree.autoloading already uses) returning a
		 * Promise of SelectOption[] - {attribute: false} since a function can't come from an HTML
		 * attribute anyway, only ever set as a real JS property.
		 */
		@property({attribute: false})
		searchUrl : string | ((search : string, options : object) => Promise<SelectOption[]>) = '';

		/**
		 * Additional search parameters that are passed to the server
		 * when we query searchUrl
		 */
		@property({type: Object})
		searchOptions : object = {app: "addressbook"};

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

		// Hold the original option data from earlier search results, since we discard on subsequent search
		private _selected_remote = <SelectOption[]>[];

		constructor(...args : any[])
		{
			super(...args);

			this.search = false;
			this.searchUrl = "";
			this.searchOptions = {app: "addressbook"};

			// Limit results by the user's preference rather than the generic mixin's flat 100
			this._classSearchOptions = {num_rows: parseInt(this.egw().preference('maxmatchs', 'common')) || 100};


			// Hiding the selected options from the dropdown means we can't un-select the tags
			// hidden by the max limit.  Prefer no limit.
			this.maxOptionsVisible = -1;

			this.handleOptionClick = this.handleOptionClick.bind(this);
			this._handleChange = this._handleChange.bind(this);
			this._handleAfterShow = this._handleAfterShow.bind(this);
			this._handleMenuHide = this._handleMenuHide.bind(this);
			this._handleSearchBlur = this._handleSearchBlur.bind(this);
			this._handleClear = this._handleClear.bind(this);
			this._handleSearchAbort = this._handleSearchAbort.bind(this);
			this._handleSearchClear = this._handleSearchClear.bind(this);
			this._handleSearchChange = this._handleSearchChange.bind(this);
			this._handleSearchKeyDown = this._handleSearchKeyDown.bind(this);
			this._handleSearchMouseDown = this._handleSearchMouseDown.bind(this);
			this._handleSearchInput = this._handleSearchInput.bind(this);
		}

		connectedCallback()
		{
			super.connectedCallback();

			this.classList.toggle("search", this.searchEnabled);

			// Missing any of the required attributes?  Don't change anything.
			// If readonly, skip it
			if(!this._needsSearchControls)
			{
				return;
			}

			this._bindListeners();
		}

		disconnectedCallback()
		{
			super.disconnectedCallback();
			if(this._searchTimeout)
			{
				window.clearTimeout(this._searchTimeout);
			}
			this._searchTimeout = null;
			this._unbindListeners();

			while(this.lastChild) this.lastChild.remove();
			this._selected_remote = [];
		}

		async getUpdateComplete()
		{
			const result = await super.getUpdateComplete();
			if(this._searchInputNode)
			{
				await this._searchInputNode.updateComplete;
			}
			return result;
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
				// Not applicable to a JS callback - only ever set as a real property, never from a
				// (possibly encoded) template attribute string.
				if(typeof this.searchUrl === "string")
				{
					this.searchUrl = this.egw().decodePath(this.searchUrl);
				}
			}
			if(changedProperties.has("searchOptions") && this.searchOptions){
				try {
					if (typeof this.searchOptions === 'string') {
						this.searchOptions = JSON.parse(this.searchOptions);
					}
				} catch (e) {
					//Could not transform to valid object, keep the string
				}
			}

			// Add missing options if search or free entries enabled
			if(changedProperties.has("value") && this.value)
			{
				if(this.searchEnabled)
				{
					// Check to see if value is for an option we do not have
					const checking = []
					for(const newValueElement of this.getValueAsArray())
					{
						if(this.optionSearch(newValueElement, null, "value", "children") ||
							// Legacy children as value
							this.optionSearch(newValueElement, null, "value", "value"))
						{
							continue;
						}

						checking.push(this._missingOption(newValueElement));
					}
					// SlSelect removes missing options from its value
					if(checking.length)
					{
						Promise.all(checking).then(() => { this.value = this.value});
					}
				}
			}
		}

		update(changedProperties)
		{
			super.update(changedProperties);

			this._updateEmptyState();

			// Update any tags if readonly changes
			if(changedProperties.has("readonly"))
			{
				// Required because we explicitly create tags instead of doing it in render()
				this.select?.shadowRoot?.querySelectorAll(".select__tags > div > *").forEach((tag : Et2Tag) =>
				{
					tag.removable = !this.readonly;
				});

				if(this.readonly)
				{
					this._unbindListeners();
				}
			}

			// One of the key properties has changed, need to add the needed nodes
			if(changedProperties.has("search") || changedProperties.has("allowFreeEntries"))
			{
				this._unbindListeners();
				// Missing any of the required attributes?  Now we need to take it out.
				if(!this._needsSearchControls)
				{
					this.querySelector(".search_input")?.remove();
					return;
				}

				// Listeners may have been skipped from connectedCallback()
				this._bindListeners();
			}
		}

		protected _extraTemplate() : TemplateResult | typeof nothing
		{
			if(!this._needsSearchControls)
			{
				return nothing;
			}

			return html`
                ${this._searchInputTemplate()}
                ${until(this._moreResultsTemplate(), nothing)}
                ${this._noResultsTemplate()}
			`;
		}

		protected async _moreResultsTemplate()
		{
			if(this._totalResults <= 0)
			{
				return nothing;
			}
			return this._searchPromise.then(() =>
			{
				// _totalResults is what the *server* said it has, so only the results we took from
				// it may be subtracted.  Counting every rendered .match subtracted local matches
				// too, which are not part of the server's total, and under-reported by that many.
				const moreCount = this._totalResults - this._searchResults.length;
				const more = this.egw().lang("%1 more...", moreCount);

				return html`<span class="more">${more}</span>`;
			});
		}

		protected _searchInputTemplate()
		{
			const edit = this._editControlTemplate();
			return html`
                <div class=${classMap({
                    search_input: true,
                    novalue: (this.value?.length == 0)
                })} slot="prefix">
                <et2-textbox id="search" type="text" part="input"
                             aria-label="${this.egw().lang("search")}"
                             exportparts="base:search__base"
                             clearable
                             autocomplete="off"
                             tabindex="-1"
                             placeholder="${this.egw().lang("search")}"
                             style="flex: 1 1 auto;"
                             @mousedown=${this._handleSearchMouseDown}
                             @keydown=${this._handleSearchKeyDown}
                             @blur=${this._handleSearchBlur}
                             @sl-clear=${this._handleSearchClear}
                             @sl-change=${this._handleSearchChange}
                             @sl-input=${this._handleSearchInput}
                ></et2-textbox>
                ${edit}
                </div>
			`;
		}

		protected _noResultsTemplate()
		{
			if(this._totalResults !== 0 || !this._searchInputNode?.value)
			{
				return nothing;
			}

			const noSuggestions = html`
                <div class="no-results">${this.egw().lang("no suggestions")}</div>`;

			// A search may still be running, in which case show the spinner and decide once it
			// lands - the total can still turn out non-zero.
			let noResults = this._searchPromise.then(() =>
			{
				return this._totalResults == 0 ?
					   noSuggestions :
					   nothing;
			});

			return html`${until(
				noResults,
				html`
                    <sl-spinner></sl-spinner>`
			)}`;
		}

		/**
		 * Do we have the needed properties set, so we can actually do searching
		 *
		 * @returns {boolean}
		 */
		/**
		 * Do we need the search-input container at all?
		 *
		 * Overridable so a subclass can add its own reason for wanting it - Et2Select does, for
		 * tag editing, which reuses this container but is not search.
		 */
		/**
		 * Anything extra a subclass wants inside the search-input container.
		 * Et2Select puts its tag-edit input here.
		 */
		protected _editControlTemplate() : TemplateResult | typeof nothing
		{
			return nothing;
		}

		/**
		 * Subclass hooks around the dropdown opening and closing, for controls that share the
		 * search container.  Et2Select uses them to show and hide its tag-edit input.
		 */
		protected _onMenuShowExtras()
		{
		}

		protected _onMenuHideExtras()
		{
		}

		/**
		 * Called once a tag break has consumed the typed text, so a subclass can put its own
		 * controls back.  Et2Select ends tag editing here.
		 */
		protected _resetExtraControls()
		{
		}

		protected get _needsSearchControls() : boolean
		{
			return !this.readonly && (this.searchEnabled || this.allowFreeEntries);
		}

		public get searchEnabled() : boolean
		{
			return !this.readonly && (this.search || !!this.searchUrl);
		}

		protected get _searchInputNode() : Et2Textbox
		{
			return this._activeControls?.querySelector("#search");
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

		get select_options() : SelectOption[]
		{
			let options = [];

			// Any provided options
			options = options.concat(this.__select_options ?? []);

			// Any kept remote options
			options = options.concat(this._selected_remote ?? []);

			// Current search results
			options = options.concat(this._searchResults ?? []);

			// Any free entries the user added come from FreeEntryMixin, below us
			const inherited = super.select_options ?? [];
			options = options.concat(inherited.filter(o => !options.some(existing => existing.value == o.value)));

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
			return this.remoteSearch(newValueElement, this.searchOptions).then((result : SelectOption[]) =>
			{
				// Server doesn't know either.  Remove it.
				if(!result || !result.length)
				{
					if(Array.isArray(this.value))
					{
						this.value.splice(this.value.indexOf(newValueElement), 1);
					}
					else
					{
						this.value = "";
					}
				}

				// Re-set / update value since SlSelect probably removed it by now due to missing option
				if(typeof this.select != "undefined")
				{
					this.select.value = this.shoelaceValue ?? this.value;
					this.select.requestUpdate("value");
				}
				this.requestUpdate("value");
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

			this._searchInputNode?.removeEventListener("change", this._handleSearchChange);
		}

		/**
		 * Toggle an attribute so CSS can suppress the dropdown panel's border/shadow/padding
		 * when there's nothing to show - eg. a search-only widget (no local options) before
		 * anything has been typed or searched
		 */
		protected _updateEmptyState()
		{
			const empty = this.searchEnabled && this.select_options.length === 0 && !this._searchInputNode?.value;
			this.toggleAttribute("dropdown-empty", empty);
		}

		_handleMenuShow()
		{
			if(this.readonly)
			{
				return;
			}
			this._updateEmptyState();
			this.setAttribute("open", "");

			// Move search (& menu) if there's no value
			this._activeControls?.classList.toggle("novalue", this.multiple && this.value == '' || !this.multiple);

			// Reset for parent calculations, will be adjusted after if needed
			//this.dropdown.setAttribute("distance", 0);

			if(this._needsSearchControls)
			{
				this._activeControls?.classList.add("active");
			}

			this._onMenuShowExtras();
		}

		/**
		 * Focus the search input after showing the dropdown so user can just type.
		 *
		 * Timeout is needed for some systems to properly focus
		 */
		_handleAfterShow()
		{
			if(this._needsSearchControls)
			{
				window.setTimeout(() =>
				{
					this._searchInputNode?.focus();
					this._searchInputNode?.select();
				}, 100);
			}
		}

		focus()
		{
			this.show().then(() =>
			{
				this._searchInputNode?.focus();
			});
		}

		_handleMenuHide()
		{
			if(this.readonly)
			{
				return;
			}
			this.removeAttribute("open");

			waitForEvent(this, "sl-after-hide").then(() => this.clearSearch());

			// Reset display
			if(this._searchInputNode)
			{
				this._searchInputNode.style.display = "";
			}
			this._onMenuHideExtras();

			this._activeControls?.classList.remove("active", "editing");
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

			// Find and keep any selected remote entries
			// Doing it here catches keypress changes too
			this._keepSelectedRemote();
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

		
		_keepSelectedRemote()
		{
			this.select.querySelectorAll("[aria-selected=true].remote").forEach((node) =>
			{
				const value = node.value.replaceAll("___", " ");
				if(!node.selected || this._selected_remote.some(o => o.value == value))
				{
					return;
				}
				const filter = (options) =>
				{
					for(let i = options.length - 1; i >= 0; i--)
					{
						if(Array.isArray(options[i].value))
						{
							filter(options[i].value);
						}
						else if(options[i].value == value)
						{
							this._selected_remote.push(options[i]);
							options.splice(i, 1);
						}
					}
				}
				filter(this._searchResults)
			});
		}
		/**
		 * An option was selected
		 */
		handleOptionClick(event)
		{
			// Only interested in option clicks, but handler is bound higher
			if(event.target.tagName !== "SL-OPTION")
			{
				return;
			}

			if(typeof super.handleOptionClick == "function")
			super.handleOptionClick(event);

			this.updateComplete.then(() =>
			{
				// If they just chose one from the list, re-focus the search
				if(this.multiple && this.searchEnabled)
				{
					this._searchInputNode.focus();
					this._searchInputNode.select();
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
			this._selected_remote = this._selected_remote.filter((option) => this.value?.indexOf(option.value) !== -1);

			if(!this.multiple && this.searchEnabled)
			{
				this._handleSearchAbort(e);
			}
			// Focus the widget again, ready for a search
			this.updateComplete.then(() =>
			{
				this.dropdown.open = true;
			})
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
			event.stopPropagation();
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
				return;
			}
			else if(event.key == "Tab" && !this._searchInputNode.value)
			{
				// Mess with tabindexes to allow focus to easily go to next control
				const input = this.select.shadowRoot.querySelector('[tabindex="0"]');
				input.setAttribute("tabindex", "-1");
				this.updateComplete.then(() =>
				{
					// Set it back so we can get focus again later
					input.setAttribute("tabindex", "0");
				})
				// Allow to propagate
				return;
			}
			event.stopPropagation();

			// Don't allow event to bubble or it will interact with select
			event.stopImmediatePropagation();
			if((<typeof FreeEntryMixinInterface><unknown>this.constructor).TAG_BREAK.indexOf(event.key) !== -1 && this.allowFreeEntries && this.createFreeEntry(this._searchInputNode.value))
			{
				this._searchInputNode.value = "";

				// If the search box was overlapping the value display, un-hide it.  This is search
				// UI, so it lives here rather than in createFreeEntry() - a free entry can be
				// created with no search UI present at all (Et2SelectThumbnail).
				if(!this.readonly && this._activeControls?.classList.contains("novalue"))
				{
					this._searchInputNode.style.display = "";
				}

				if(!this.multiple)
				{
					this._resetExtraControls();

					// Mess with tabindexes to allow focus to easily go to next control
					const input = this.select.shadowRoot.querySelector('[tabindex="0"]');
					input.setAttribute("tabindex", "-1");
					this.updateComplete.then(() =>
					{
						// Set it back so we can get focus again later
						input.setAttribute("tabindex", "0");
					})
					return;
				}

				event.preventDefault();
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
				this.hide();
				return;
			}

			// Normal typing / paste / autocomplete is handled by _handleSearchInput(),
			// because keydown sees the old value before the character or pasted text is inserted.
		}

		/**
		 * Handles the input event for the search functionality.
		 * navigation Input is handled by _handleSearchKeyDown (Enter,escape,arrows)
		 *
		 *
		 * @param {CustomEvent} event - The custom event (sl-input event) triggered by the search input field.
		 * @return {void} This method does not return any value.
		 */
		protected _handleSearchInput(event:CustomEvent): void
		{
			event.stopPropagation();

			clearTimeout(this._searchTimeout);
			this._activeControls?.classList.add("active");

			const value = this._searchInputNode?.value ?? "";

			if(value.length >= SelectSearchMixinClass.MIN_CHARS)
			{
				this._searchTimeout = window.setTimeout(() =>
				{
					this.startSearch();
				}, SelectSearchMixinClass.SEARCH_TIMEOUT);
			}
			else
			{
				this._clearResults();
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

		
		/**
		 * Sometimes users paste multiple comma separated values at once.  Split them then handle normally.
		 *
		 * @param {ClipboardEvent} event
		 * @protected
		 */


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

			this._keepSelectedRemote();


			this._totalResults = 0;

			// Not searching anymore, clear flag
			const clear_flag = (option) =>
			{
				if(Array.isArray(option.value))
				{
					option.value.map(clear_flag)
				}
				else
				{
					option.isMatch = null
				}
			}
			this.select_options.map(clear_flag);
			this.requestUpdate("select_options");

			// Rendering options using repeat() means we need to explicitly update the nodes since they
			// don't always get re-rendered
			for(const option of this.select.querySelectorAll(".no-match"))
			{
				option.classList.remove("no-match", "match");
			}
		}



		/**
		 * Local options are Et2Select's select_options - the generic mixin has no idea where a
		 * host keeps them.
		 */
		protected localSearch<DataType extends SelectOption>(search : string, searchOptions : object, localOptions : DataType[] = []) : Promise<DataType[]>
		{
			// Mark every option as match / non-match so _optionTemplate() can hide the misses
			this.select_options.forEach((option) =>
			{
				option.isMatch = this.searchMatch(search, searchOptions, option);
			});
			this.requestUpdate("select_options");

			return super.localSearch(search, searchOptions, <any>this.select_options);
		}

		/**
		 * A local match is already an option - it only needed its isMatch flag set, above.
		 * Adding it to the result list too would render it twice, and the "n more" count is
		 * about what the *server* still has, so local matches must not inflate it either.
		 */
		protected processLocalResults<DataType extends SelectOption>(results) : DataType[]
		{
			return <any>(results?.results ?? []);
		}

		/**
		 * A .json searchUrl is a file to fetch and filter in the browser, not an endpoint.
		 *
		 * The callback and "app.x.y" forms are handled by the generic mixin - they are not
		 * select-specific.  This one stays because it goes through StaticOptions, which is.
		 */
		protected remoteSearch<DataType extends SelectOption>(search : string, options : object) : Promise<DataType[]>
		{
			if(typeof this.searchUrl === "string" && this.searchUrl.includes(".json"))
			{
				return <any>this.jsonQuery(search, options);
			}

			return <any>this.remoteQuery(search, options);
		}

		/**
		 * How the request is actually made.  Kept as its own method because subclasses override it
		 * to change the parameters - Et2LinkSearch sends the app as a positional argument, and the
		 * old taglist callbacks expect the search string and options both ways round.
		 */
		protected remoteQuery(search : string, options : object) : Promise<SelectOption[]>
		{
			return <any>super.remoteSearch(search, options);
		}

		/**
		 * Turn a raw server response into results.  Kept for subclasses that make their own request
		 * and hand us what came back (Et2LinkSearch, CalendarOwner).
		 */
		protected _processResultCount(results) : SelectOption[]
		{
			return <any>this.processRemoteResults(results);
		}

		/**
		 * Our endpoints predate the {results, total} contract - see legacySearchResults.ts.
		 * Results are also tagged "remote" so they can be told apart from local options.
		 */
		protected processRemoteResults<DataType extends SelectOption>(results) : DataType[]
		{
			return this._addRemoteResults(normalizeLegacySearchResults(results, this.searchUrl));
		}

		/**
		 * Tag results as remote, drop the ones we already have as options, and hand the rest to the
		 * generic mixin.  Split out from processRemoteResults() so the .json path can reuse it
		 * without going through the legacy normaliser - a static options file is not an endpoint
		 * anyone can "update", so warning about its shape would be noise.
		 */
		private _addRemoteResults<DataType extends SelectOption>(normalised) : DataType[]
		{
			// A result we already have as an option is not a new result: flag the option we have as
			// a match and drop the duplicate, so it renders once.  The generic mixin only dedupes
			// within its own result list, which cannot see Et2Select's options.
			for(let i = normalised.results.length - 1; i >= 0; i--)
			{
				const entry = normalised.results[i];
				entry.class = ((entry.class || "") + " remote").trim();
				entry.isMatch = true;

				const existing = this.select_options.find(o => o.value == entry.value);
				if(existing)
				{
					existing.isMatch = true;
					normalised.results.splice(i, 1);
					normalised.total--;
				}
			}

			return <any>super.processRemoteResults(<any>normalised);
		}

		/**
		 * Wait for the user to have typed enough before going to the server.
		 */
		handleSearchKeyDown(event)
		{
			if(this._searchInputNode && this._searchInputNode.value.length > 0 &&
				this._searchInputNode.value.length < SelectSearchMixinClass.MIN_CHARS &&
				!["Enter", "Escape", "Tab", "ArrowUp", "ArrowDown"].includes(event.key))
			{
				clearTimeout(this._searchTimeout);
				return;
			}
			return super.handleSearchKeyDown(event);
		}

		/**
		 * sl-select renders the results as its own options, so the generic mixin's result list
		 * would be a second, duplicate one.
		 */
		protected searchResultsTemplate()
		{
			return nothing;
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
			let resultLimit = Math.max(parseInt(this.egw().preference('maxmatchs', 'common')), 100);
			return StaticOptions.cached_from_file(this, this.searchUrl)
				.then(options =>
				{
					// Filter the options
					const lower_search = search.toLowerCase();
					const filtered = options.filter(option =>
					{
						return option.label.toLowerCase().includes(lower_search) || option.value.includes(search)
					});
					// Limit what we show, but report what the file actually had so "n more" is honest
					const total = filtered.length;
					if(filtered.length > resultLimit)
					{
						filtered.splice(resultLimit);
					}
					this._addRemoteResults({results: filtered, total: total});
					return filtered;
				})
				.catch((_err) =>
				{
					this.egw().message(_err.statusText || this.searchUrl, "error");
					return [];
				});
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

		protected _handleSearchClear(e)
		{
			e.stopImmediatePropagation();
			e.preventDefault();
			this.clearSearch();
		}
	}

	return SelectSearchMixinClass as unknown as Constructor<SearchMixinInterface> & T;
});

/**
 * @deprecated use SelectSearchMixin.  Kept for one release for anything outside this repo.
 */
export const Et2WithSearchMixin = SelectSearchMixin;
