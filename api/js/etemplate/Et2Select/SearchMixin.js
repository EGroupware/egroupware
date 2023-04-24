/**
 * EGroupware eTemplate2 - SearchMixin
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */
var __awaiter = (this && this.__awaiter) || function (thisArg, _arguments, P, generator) {
    function adopt(value) { return value instanceof P ? value : new P(function (resolve) { resolve(value); }); }
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};
import { css, html, render, SlotMixin } from "@lion/core";
import { cleanSelectOptions } from "./FindSelectOptions";
import { Et2Tag } from "./Tag/Et2Tag";
import { SlMenuItem } from "@shoelace-style/shoelace";
import { waitForEvent } from "@shoelace-style/shoelace/dist/internal/event";
import { StaticOptions } from "./StaticOptions";
// Otherwise import gets stripped
let keep_import;
/**
 * Base class for things that do search type behaviour
 * Separated to keep things a little simpler.
 *
 * Currently I assume we're extending an Et2Select, so changes may need to be made for better abstraction
 */
export const Et2WithSearchMixin = (superclass) => {
    class Et2WidgetWithSearch extends SlotMixin(superclass) {
        constructor(...args) {
            super(...args);
            // Hold the original option data from earlier search results, since we discard on subsequent search
            this._selected_remote = [];
            this.search = false;
            this.searchUrl = "";
            this.searchOptions = { app: "addressbook" };
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
            this.handleMenuSelect = this.handleMenuSelect.bind(this);
            this._handleChange = this._handleChange.bind(this);
            this.handleTagEdit = this.handleTagEdit.bind(this);
            this._handleAfterShow = this._handleAfterShow.bind(this);
            this._handleSearchBlur = this._handleSearchBlur.bind(this);
            this._handleClear = this._handleClear.bind(this);
            this._handleDoubleClick = this._handleDoubleClick.bind(this);
            this._handleSearchAbort = this._handleSearchAbort.bind(this);
            this._handleSearchChange = this._handleSearchChange.bind(this);
            this._handleSearchKeyDown = this._handleSearchKeyDown.bind(this);
            this._handleEditKeyDown = this._handleEditKeyDown.bind(this);
            this._handlePaste = this._handlePaste.bind(this);
        }
        static get properties() {
            return Object.assign(Object.assign({}, super.properties), { search: { type: Boolean, reflect: true }, searchUrl: { type: String }, 
                /**
                 * Allow custom entries that are not in the options
                 */
                allowFreeEntries: { type: Boolean, reflect: true }, 
                /**
                 * Additional search parameters that are passed to the server
                 * when we query searchUrl
                 */
                searchOptions: { type: Object }, 
                /**
                 * Allow editing tags by clicking on them.
                 * allowFreeEntries must be true
                 */
                editModeEnabled: { type: Boolean } });
        }
        static get styles() {
            return [
                // @ts-ignore
                ...(super.styles ? (Symbol.iterator in Object(super.styles) ? super.styles : [super.styles]) : []),
                css `
				/* Move the widget border 
				.form-control-input {
					border: solid var(--sl-input-border-width) var(--sl-input-border-color);
					border-radius: var(--sl-input-border-radius-medium);
				}
				.form-control-input:hover {
					background-color: var(--sl-input-background-color-hover);
					border-color: var(--sl-input-border-color-hover);
					color: var(--sl-input-color-hover);
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
				/* Show / hide SlSelect icons - dropdown arrow, etc but not loading spinner */
				:host([allowFreeEntries]) ::slotted(sl-icon[slot="suffix"]) {
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
				/* Full width search textbox covers loading spinner, lift it up */
				::slotted(sl-spinner) {
					z-index: 2;
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
				:host([search]:not([multiple])) .select--open .select__label {
					margin: 0px;
				}
				:host([allowfreeentries]:not([multiple])) .select--standard.select--open:not(.select--disabled) .select__control .select__prefix {
					flex: 1 1 auto;
				}
				:host([allowfreeentries]:not([multiple])) .select--standard.select--open:not(.select--disabled) .select__control .select__label {
					display: none;
				}

				  /* Search textbox general styling, starts hidden */

				  .select__prefix ::slotted(.search_input), .search_input {
					display: none;
					flex: 1 1 auto;
					margin-left: 0px;
					width: 100%;
					height: var(--sl-input-height-medium);
					position: absolute;
					background-color: white;
					z-index: var(--sl-z-index-dropdown);
				  }

				  /* Search UI active - show textbox & stuff */

				  ::slotted(.search_input.active), .search_input.active,
				  .search_input.editing {
					display: flex;
				  }

				  /* If multiple and no value, overlap search onto widget instead of below */

				  :host([multiple]) .search_input.active.novalue {
					top: 0px;
				  }
				
				/* Hide options that do not match current search text */
				::slotted(.no-match) {
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
				/* no menu */
				:host([readonly]) sl-menu {
					display: none;
				}
				/* normal cursor */
				:host([readonly]) .select__control {
					cursor: initial;
				}
				`
            ];
        }
        connectedCallback() {
            super.connectedCallback();
            this.classList.toggle("search", this.searchEnabled);
            // Missing any of the required attributes?  Don't change anything.
            // If readonly, skip it
            if (!this.searchEnabled && !this.editModeEnabled && !this.allowFreeEntries || this.readonly) {
                return;
            }
            this._addNodes();
            this._bindListeners();
        }
        disconnectedCallback() {
            super.disconnectedCallback();
            this._unbindListeners();
        }
        willUpdate(changedProperties) {
            super.willUpdate(changedProperties);
            // Turn on search if there's more than 20 options
            if (changedProperties.has("select_options") && this.select_options.length > 20) {
                this.search = true;
            }
            // If searchURL is set, turn on search
            if (changedProperties.has("searchUrl") && this.searchUrl) {
                this.search = true;
                // Decode URL, possibly again.  If set in template, it can wind up double-encoded.
                this.searchUrl = this.egw().decodePath(this.searchUrl);
            }
            // Add missing options if search or free entries enabled
            if (changedProperties.has("value") && this.value) {
                // Overridden to add options if allowFreeEntries=true
                if (this.allowFreeEntries && typeof this.value == "string" && !this.select_options.find(o => o.value == this.value &&
                    (!o.class || o.class && !o.class.includes('remote')))) {
                    this.createFreeEntry(this.value);
                }
                else if (this.allowFreeEntries && this.multiple) {
                    this.value.forEach((e) => {
                        if (!this.select_options.find(o => o.value == e)) {
                            this.createFreeEntry(e);
                        }
                    });
                }
                if (this.searchEnabled) {
                    // Check to see if value is for an option we do not have
                    for (const newValueElement of this.getValueAsArray()) {
                        if (this.select_options.some(o => o.value == newValueElement)) {
                            continue;
                        }
                        this._missingOption(newValueElement);
                    }
                }
            }
        }
        update(changedProperties) {
            var _a;
            super.update(changedProperties);
            // One of the key properties has changed, need to add the needed nodes
            if (changedProperties.has("search") || changedProperties.has("editModeEnabled") || changedProperties.has("allowFreeEntries")) {
                // Missing any of the required attributes?  Now we need to take it out.
                if (!this.searchEnabled && !this.editModeEnabled && !this.allowFreeEntries || this.readonly) {
                    (_a = this.querySelector(".search_input")) === null || _a === void 0 ? void 0 : _a.remove();
                    return;
                }
                // Normally this should be handled in render(), but we have to add our nodes in
                this._addNodes();
            }
            // Update any tags if edit mode changes
            if (changedProperties.has("editModeEnabled") || changedProperties.has("readonly")) {
                // Required because we explicitly create tags instead of doing it in render()
                this.shadowRoot.querySelectorAll(".select__tags > *").forEach((tag) => {
                    tag.editable = this.editModeEnabled && !this.readonly;
                    tag.removable = !this.readonly;
                });
            }
        }
        /**
         * Add the nodes we need to search - adjust parent shadowDOM
         *
         * @protected
         */
        _addNodes() {
            if (this._activeControls) {
                // Already there
                return;
            }
            const div = document.createElement("div");
            div.classList.add("search_input");
            render(this._searchInputTemplate(), div);
            if (!super.multiple) {
                div.slot = "prefix";
                this.appendChild(div);
                return;
            }
            super.updateComplete.then(() => {
                let control = this.shadowRoot.querySelector(".form-control-input");
                control.append(div);
            });
        }
        /**
         * Customise how tags are rendered.
         * Override to add edit
         *
         * @param item
         * @protected
         */
        _createTagNode(item) {
            let tag = document.createElement(this.tagTag);
            tag.editable = this.editModeEnabled && !this.readonly;
            return tag;
        }
        _searchInputTemplate() {
            let edit = null;
            if (this.editModeEnabled) {
                edit = html `<input id="edit" type="text" part="input" autocomplete="off" style="width:100%"
                                   @keydown=${this._handleEditKeyDown}
                                   @click=${(e) => e.stopPropagation()}
                                   @blur=${this.stopEdit.bind(this)}
                />`;
            }
            // I can't figure out how to get this full width via CSS
            return html `
                <et2-textbox id="search" type="text" part="input" clearable
                             autocomplete="off"
                             placeholder="${this.egw().lang("search")}"
                               style="width:100%"
                               @keydown=${this._handleSearchKeyDown}
                               @blur=${this._handleSearchBlur}
                ></et2-textbox>
                ${edit}
			`;
        }
        _noResultsTemplate() {
            return html `
                <div class="no-results">${this.egw().lang("no suggestions")}</div>`;
        }
        /**
         * Do we have the needed properties set, so we can actually do searching
         *
         * @returns {boolean}
         */
        get searchEnabled() {
            return !this.readonly && (this.search || this.searchUrl.length > 0);
        }
        get _searchInputNode() {
            var _a;
            return (_a = this._activeControls) === null || _a === void 0 ? void 0 : _a.querySelector("#search");
        }
        get _editInputNode() {
            var _a;
            return (_a = this._activeControls) === null || _a === void 0 ? void 0 : _a.querySelector("input#edit");
        }
        get _activeControls() {
            var _a;
            return ((_a = this.shadowRoot) === null || _a === void 0 ? void 0 : _a.querySelector(".search_input")) ||
                this.querySelector(".search_input");
        }
        /**
         * Only local options, excludes server options
         *
         * @protected
         */
        get localItems() {
            return this.querySelectorAll(this.optionTag + ":not(.remote)");
        }
        /**
         * Only remote options from search results
         * @returns {NodeList}
         * @protected
         */
        get remoteItems() {
            return this.querySelectorAll(this.optionTag + ".remote");
        }
        /**
         * Only free entries
         * @returns {NodeList}
         * @protected
         */
        get freeEntries() {
            return this.querySelectorAll(this.optionTag + ".freeEntry");
        }
        get select_options() {
            let options = [];
            // Any provided options
            options = options.concat(this.__select_options);
            // Any kept remote options
            options = options.concat(this._selected_remote);
            if (this.allowFreeEntries) {
                this.freeEntries.forEach((item) => {
                    if (!options.some(i => i.value == item.value)) {
                        options.push({ value: item.value, label: item.textContent, class: item.classList.toString() });
                    }
                });
            }
            return options;
        }
        set select_options(options) {
            var _a;
            super.select_options = options;
            // Remove any selected remote, they're real options now
            for (let remote_index = this._selected_remote.length - 1; remote_index >= 0; remote_index--) {
                let remote = this._selected_remote[remote_index];
                if (options.findIndex(o => o.value == remote.value) != -1) {
                    this._selected_remote.splice(remote_index, 1);
                    (_a = this.querySelector('[value="' + remote.value + '"]')) === null || _a === void 0 ? void 0 : _a.classList.remove("remote");
                }
            }
        }
        get value() {
            return super.value;
        }
        set value(new_value) {
            super.value = new_value;
            if (!new_value || !this.allowFreeEntries && !this.searchUrl) {
                return;
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
        _missingOption(newValueElement) {
            // Given a value we need to search for - this will add in all matches, including the one needed
            this.remoteSearch(newValueElement, this.searchOptions).then((result) => {
                const option = result.find(o => o.value == newValueElement);
                if (option) {
                    this._selected_remote.push(option);
                }
            });
        }
        fix_bad_value() {
            if (!this.allowFreeEntries && !this.searchEnabled) {
                // Let regular select deal with it
                return false;
            }
            const valueArray = Array.isArray(this.value) ? this.value : (!this.value ? [] : this.value.toString().split(','));
            // Check any already found options
            if (Object.values(this.menuItems).filter((option) => valueArray.find(val => val == option.value)).length === 0) {
                return false;
            }
            return true;
            // TODO? Should we check the server, or just be OK with it?  Passing the "current" value in sel_options makes sure the value is there
        }
        _bindListeners() {
            this.addEventListener("sl-clear", this._handleClear);
            this.addEventListener("sl-after-show", this._handleAfterShow);
            // Need our own change to catch the change event from search input
            this.addEventListener("change", this._handleChange);
            if (this.allowFreeEntries) {
                this.addEventListener("paste", this._handlePaste);
            }
            this.updateComplete.then(() => {
                var _a, _b;
                // Search messes up event order.  Since it throws its own bubbling change event,
                // selecting an option fires 2 change events - 1 before the widget is finished adjusting, losing the value
                // We catch all change events, then call this._oldChange only when value changes
                this.removeEventListener("change", this._oldChange);
                (_a = this._searchInputNode) === null || _a === void 0 ? void 0 : _a.removeEventListener("change", this._searchInputNode.handleChange);
                (_b = this._searchInputNode) === null || _b === void 0 ? void 0 : _b.addEventListener("change", this._handleSearchChange);
                this.dropdown.querySelector('.select__label').addEventListener("change", this.handleTagEdit);
            });
        }
        _unbindListeners() {
            var _a;
            this.removeEventListener("sl-select", this._handleSelect);
            this.removeEventListener("sl-after-show", this._handleAfterShow);
            this.removeEventListener("sl-clear", this._handleClear);
            this.removeEventListener("change", this._handleChange);
            this.removeEventListener("paste", this._handlePaste);
            (_a = this._searchInputNode) === null || _a === void 0 ? void 0 : _a.removeEventListener("change", this._handleSearchChange);
        }
        handleMenuShow() {
            var _a, _b;
            if (this.readonly) {
                return;
            }
            // Move search (& menu) if there's no value
            (_a = this._activeControls) === null || _a === void 0 ? void 0 : _a.classList.toggle("novalue", this.multiple && this.value == '' || !this.multiple);
            // Reset for parent calculations, will be adjusted after if needed
            this.dropdown.setAttribute("distance", 0);
            super.handleMenuShow();
            if (this.searchEnabled || this.allowFreeEntries) {
                (_b = this._activeControls) === null || _b === void 0 ? void 0 : _b.classList.add("active");
                this._searchInputNode.focus();
                this._searchInputNode.select();
                // Hide edit explicitly since it's so hard via CSS
                if (this._editInputNode) {
                    this._editInputNode.style.display = "none";
                }
            }
            if (this.editModeEnabled && this.allowFreeEntries && !this.multiple && this.value) {
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
        _handleAfterShow() {
            // Need to give positioner a chance to position.
            // If we call it right away, it has not updated.
            // I haven't found an event or Promise to hook on to
            window.setTimeout(() => {
                var _a, _b, _c, _d, _e;
                if (((_a = this.dropdown) === null || _a === void 0 ? void 0 : _a.getAttribute("distance")) && ((_c = (_b = this.dropdown) === null || _b === void 0 ? void 0 : _b.popup) === null || _c === void 0 ? void 0 : _c.dataset.currentPlacement) == "top") {
                    this.dropdown.setAttribute("distance", 0);
                    this.dropdown.reposition();
                }
                else {
                    (_d = this.dropdown) === null || _d === void 0 ? void 0 : _d.setAttribute("distance", !this._activeControls || ((_e = this._activeControls) === null || _e === void 0 ? void 0 : _e.classList.contains("novalue")) ?
                        parseInt(getComputedStyle(this.control).getPropertyValue("border-width")) :
                        // Make room for search below
                        parseInt(getComputedStyle(this._activeControls).getPropertyValue("--sl-input-height-medium")));
                }
            }, 100);
        }
        focus() {
            var _a;
            (_a = this.dropdown) === null || _a === void 0 ? void 0 : _a.show().then(() => {
                this._searchInputNode.focus();
            });
        }
        handleMenuHide() {
            var _a;
            if (this.readonly) {
                return;
            }
            clearTimeout(this._searchTimeout);
            super.handleMenuHide();
            // Reset display
            if (this._searchInputNode) {
                this._searchInputNode.style.display = "";
            }
            if (this._editInputNode) {
                this._editInputNode.style.display = "";
            }
            if (this.searchEnabled || this.allowFreeEntries) {
                (_a = this._activeControls) === null || _a === void 0 ? void 0 : _a.classList.remove("active");
                this.shadowRoot.querySelector('.select__label').style.display = "";
            }
        }
        _triggerChange(event) {
            // Don't want searchbox events to trigger change event
            if (event.target == this._searchInputNode) {
                event.stopImmediatePropagation();
                event.preventDefault();
                return false;
            }
            return true;
        }
        _handleChange(event) {
            if (event.target == this._searchInputNode) {
                event.stopImmediatePropagation();
                event.preventDefault();
                return false;
            }
            return this._oldChange(event);
        }
        _handleDoubleClick(event) {
            // No edit (shouldn't happen...)
            if (!this.editModeEnabled) {
                return;
            }
            // Find the tag
            const path = event.composedPath();
            const tag = path.find((el) => el instanceof Et2Tag);
            this.dropdown.hide();
            this.updateComplete.then(() => {
                tag.startEdit(event);
            });
        }
        /**
         * An option was selected
         */
        handleMenuSelect(event) {
            // Need to keep the remote option - only if selected
            if (event.detail.item.classList.contains("remote") && !this.select_options.find(o => o.value == event.detail.item.value)) {
                this._selected_remote.push(Object.assign({}, event.detail.item.option));
            }
            super.handleMenuSelect(event);
            this.updateComplete.then(() => {
                // If they just chose one from the list, re-focus the search
                if (this.multiple && this.searchEnabled) {
                    this._searchInputNode.focus();
                    this._searchInputNode.select();
                    // If we were overlapping, reset
                    if (this._activeControls.classList.contains("novalue")) {
                        this.handleMenuShow();
                        this._handleAfterShow();
                    }
                    // Scroll the new tag into view
                    if (event.detail && event.detail.item) {
                        // Causes sidemenu (calendar) to scroll to top & get stuck
                        /*
                        this.updateComplete.then(() =>
                        {
                            this.shadowRoot.querySelector("et2-tag[value='" + event.detail.item.value.replace(/'/g, "\\\'") + "']")?.scrollIntoView({block: "nearest"});
                        });
                         */
                    }
                }
                else if (!this.multiple && this.searchEnabled) {
                    // Stop all the search stuff when they select an option
                    // this shows all non-matching options again
                    this._handleSearchAbort(event);
                }
            });
        }
        /**
         * Value was cleared
         */
        _handleClear(e) {
            // Only keep remote options that are still used
            this._selected_remote = this._selected_remote.filter((option) => this.getValueAsArray().indexOf(option.value) !== -1);
            if (!this.multiple && this.searchEnabled) {
                this._handleSearchAbort(e);
                // Restore label styling
                this.shadowRoot.querySelector("[part='display-label']").style.display = "";
                // Start searching again
                this.updateComplete.then(() => this.handleMenuShow());
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
        _handleSearchBlur(event) {
            return __awaiter(this, void 0, void 0, function* () {
                clearTimeout(this._searchTimeout);
                if (event.relatedTarget && event.relatedTarget instanceof SlMenuItem) {
                    return;
                }
                // Try any value they had in progress
                if (this._searchInputNode.value && this.allowFreeEntries) {
                    this.createFreeEntry(this._searchInputNode.value);
                }
                this.clearSearch();
            });
        }
        /**
         * Handle keypresses inside the search input
         * @param {KeyboardEvent} event
         * @protected
         */
        _handleSearchKeyDown(event) {
            var _a;
            clearTimeout(this._searchTimeout);
            (_a = this._activeControls) === null || _a === void 0 ? void 0 : _a.classList.add("active");
            this.dropdown.show();
            // Pass off some keys to select
            if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
                // Strip out hidden non-matching selected & disabled items so key navigation works
                this.menuItems = this.menuItems.filter(i => !i.disabled);
                return super.handleKeyDown(event);
            }
            event.stopPropagation();
            // Don't allow event to bubble or it will interact with select
            event.stopImmediatePropagation();
            if (Et2WidgetWithSearch.TAG_BREAK.indexOf(event.key) !== -1 && this.allowFreeEntries && this.createFreeEntry(this._searchInputNode.value)) {
                event.preventDefault();
                this._searchInputNode.value = "";
                this.dropdown.hide().then(() => __awaiter(this, void 0, void 0, function* () {
                    // update sizing / position before getting ready for another one
                    if (this.multiple) {
                        yield this.dropdown.show();
                        this._searchInputNode.focus();
                    }
                }));
            }
            else if (event.key == "Enter") {
                event.preventDefault();
                this.startSearch();
                return;
            }
            else if (event.key == "Escape") {
                this._handleSearchAbort(event);
                this.dropdown.hide();
                return;
            }
            // Start the search automatically if they have enough letters
            // -1 because we're in keyDown handler, and value is from _before_ this key was pressed
            if (this._searchInputNode.value.length >= Et2WidgetWithSearch.MIN_CHARS - 1) {
                this._searchTimeout = window.setTimeout(() => { this.startSearch(); }, Et2WidgetWithSearch.SEARCH_TIMEOUT);
            }
        }
        _handleEditKeyDown(event) {
            // Stop propagation, or parent key handler will add again
            event.stopImmediatePropagation();
            if (Et2WidgetWithSearch.TAG_BREAK.indexOf(event.key) !== -1 && this.allowFreeEntries) {
                // Prevent default, since that would try to submit
                event.preventDefault();
                this.stopEdit();
            }
            // Abort edit, put original value back
            else if (event.key == "Escape") {
                this.stopEdit(true);
            }
        }
        /**
         * Sometimes users paste multiple comma separated values at once.  Split them then handle normally.
         *
         * @param {ClipboardEvent} event
         * @protected
         */
        _handlePaste(event) {
            event.preventDefault();
            let paste = event.clipboardData.getData('text');
            if (!paste) {
                return;
            }
            const selection = window.getSelection();
            if (selection.rangeCount) {
                selection.deleteFromDocument();
            }
            let values = paste.split(/,\t/);
            values.forEach(v => {
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
        startSearch() {
            return __awaiter(this, void 0, void 0, function* () {
                // Stop timeout timer
                clearTimeout(this._searchTimeout);
                // Show a spinner
                let spinner = document.createElement("sl-spinner");
                spinner.slot = "suffix";
                this.appendChild(spinner);
                // Hide clear button
                let clear_button = this._searchInputNode.shadowRoot.querySelector(".input__clear");
                if (clear_button) {
                    clear_button.style.display = "none";
                }
                // Clear previous results
                this._clearResults();
                yield this.updateComplete;
                // Start the searches
                return Promise.all([
                    this.localSearch(this._searchInputNode.value, this.searchOptions),
                    this.remoteSearch(this._searchInputNode.value, this.searchOptions)
                ]).then(() => {
                    // Show no results indicator
                    if (this.menuItems.filter(e => !e.classList.contains("no-match")).length == 0) {
                        let target = this._optionTargetNode || this;
                        let temp = document.createElement("div");
                        render(this._noResultsTemplate(), temp);
                        target.append(temp.children[0]);
                    }
                    // Remove spinner
                    spinner.remove();
                    // Restore clear button
                    if (clear_button) {
                        clear_button.style.display = "";
                    }
                }).then(() => {
                    // Not sure why this stays hidden if there's no results, but it sticks and hides all results afterward
                    this.dropdown.shadowRoot.querySelector(".dropdown__panel").removeAttribute("hidden");
                    // Call our resize stuff explicitly
                    this._handleAfterShow();
                });
            });
        }
        /**
         * Clear search term and any search results
         *
         * Local options are not removed, but remote options are
         */
        clearSearch() {
            // Stop timeout timer
            clearTimeout(this._searchTimeout);
            this._clearResults();
            // Clear search term
            if (this._searchInputNode) {
                this._searchInputNode.value = "";
            }
        }
        _clearResults() {
            var _a;
            let target = this._optionTargetNode || this;
            // Remove "no suggestions"
            (_a = target.querySelector(".no-results")) === null || _a === void 0 ? void 0 : _a.remove();
            // Remove any previously selected remote options that aren't used anymore
            this._selected_remote = this._selected_remote.filter((option) => {
                return this.multiple ? this.value.indexOf(option.value) != -1 : this.value == option.value;
            });
            // Remove remote options that aren't used
            let keepers = this._selected_remote.reduce((prev, current) => {
                return prev + ":not([value='" + ('' + current.value).replace(/'/g, "\\\'") + "'])";
            }, "");
            target.querySelectorAll(".remote" + keepers).forEach(o => o.remove());
            target.childNodes.forEach((n) => {
                if (n.nodeType == Node.COMMENT_NODE) {
                    n.remove();
                }
            });
            // Reset remaining options.  It might be faster to re-create instead.
            this._menuItems.forEach((item) => {
                var _a;
                item.disabled = ((_a = item.option) === null || _a === void 0 ? void 0 : _a.disabled) || false;
                item.classList.remove("match");
                item.classList.remove("no-match");
            });
        }
        /**
         * Filter the local options
         *
         * @param {string} search
         * @protected
         */
        localSearch(search, options) {
            return new Promise((resolve) => {
                this.localItems.forEach((item) => {
                    let match = this.searchMatch(search, item);
                    item.classList.toggle("match", match);
                    // set disabled so arrow keys step over.  Might be a better way to handle that
                    item.disabled = !match;
                    item.classList.toggle("no-match", !match);
                });
                resolve();
            });
        }
        /**
         * Ask for remote options and add them in unconditionally
         * @param {string} search
         * @protected
         */
        remoteSearch(search, options) {
            if (!this.searchUrl) {
                return Promise.resolve([]);
            }
            // Check our URL: JSON file or URL?
            if (this.searchUrl.includes(".json")) {
                // Get the file, search it
                return this.jsonQuery(search, options);
            }
            else {
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
        jsonQuery(search, options) {
            // Get the file
            const controller = new AbortController();
            const signal = controller.signal;
            let response_ok = false;
            return StaticOptions.cached_from_file(this, this.searchUrl)
                .then(options => {
                // Filter the options
                const lower_search = search.toLowerCase();
                const filtered = options.filter(option => {
                    return option.label.toLowerCase().includes(lower_search) || option.value.includes(search);
                });
                // Limit results
                const totalCount = filtered.length;
                if (filtered.length > Et2WidgetWithSearch.RESULT_LIMIT) {
                    filtered.splice(Et2WidgetWithSearch.RESULT_LIMIT);
                }
                // Add the matches
                this.processRemoteResults(filtered, totalCount);
                return filtered;
            })
                .catch((_err) => {
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
        remoteQuery(search, options) {
            // Include a limit, even if options don't, to avoid massive lists breaking the UI
            let sendOptions = Object.assign({ num_rows: Et2WidgetWithSearch.RESULT_LIMIT }, options);
            return this.egw().request(this.egw().link(this.egw().ajaxUrl(this.egw().decodePath(this.searchUrl)), Object.assign({ query: search }, sendOptions)), [search, sendOptions]).then((results) => {
                // If results have a total included, pull it out.
                // It will cause errors if left in the results
                let total = null;
                if (typeof results.total !== "undefined") {
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
        processRemoteResults(entries, totalResults = 0) {
            if (!(entries === null || entries === void 0 ? void 0 : entries.length)) {
                return Promise.resolve();
            }
            // Add a "remote" class so we can tell these apart from any local results
            entries.forEach((entry) => entry.class = (entry.class || "") + " remote");
            let target = this._optionTargetNode || this;
            if (target) {
                // Add in remote options, avoiding duplicates
                this.select_options.filter(function (item) {
                    let i = entries.findIndex(x => (x.value == item.value));
                    if (i <= -1) {
                        entries.push(item);
                    }
                    return null;
                });
                let options = html `${entries.map(this._optionTemplate.bind(this))}`;
                /**
                 * Add in new options.
                 * Rendering directly into target will remove existing options, which we don't need to do
                 */
                let temp_target = document.createElement("div");
                let resultCount = entries.length;
                render(options, temp_target);
                return Promise.all(([...temp_target.querySelectorAll(":scope > *")].map(item => item.render)))
                    .then(() => {
                    temp_target.querySelectorAll(":scope > *").forEach((item) => {
                        // Avoid duplicate error
                        if (!target.querySelector("[value='" + ('' + item.value).replace(/'/g, "\\\'") + "']")) {
                            target.appendChild(item);
                        }
                    });
                    this.handleMenuSlotChange();
                })
                    .then(() => {
                    if (totalResults && totalResults > resultCount) {
                        // More results available that were not sent
                        let count = document.createElement("span");
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
        searchMatch(search, item) {
            var _a;
            if (!item || !item.value) {
                return false;
            }
            if ((_a = item.textContent) === null || _a === void 0 ? void 0 : _a.toLowerCase().includes(search.toLowerCase())) {
                return true;
            }
            if (typeof item.value == "string") {
                return item.value.includes(search.toLowerCase());
            }
            return item.value == search;
        }
        /**
         * Create an entry that is not in the options and add it to the value
         *
         * @param {string} text Used as both value and label
         */
        createFreeEntry(text) {
            var _a;
            if (!text || !this.validateFreeEntry(text)) {
                return false;
            }
            // Make sure not to double-add
            if (!this.querySelector("[value='" + text.replace(/'/g, "\\\'") + "']") && !this.__select_options.find(o => o.value == text)) {
                this.__select_options.push({
                    value: text.trim(),
                    label: text.trim(),
                    class: "freeEntry"
                });
                this.requestUpdate('select_options');
            }
            // Make sure not to double-add
            if (this.multiple && this.value.indexOf(text) == -1) {
                this.value.push(text);
            }
            else if (!this.multiple && this.value !== text) {
                this.value = text;
            }
            this.requestUpdate("value");
            // If we were overlapping edit inputbox with the value display, reset
            if (!this.readonly && ((_a = this._activeControls) === null || _a === void 0 ? void 0 : _a.classList.contains("novalue"))) {
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
        validateFreeEntry(text) {
            let validators = [...this.validators, ...this.defaultValidators];
            let result = validators.filter(v => v.execute(text, v.param, { node: this }));
            return validators.length > 0 && result.length == 0 || validators.length == 0;
        }
        handleTagEdit(event) {
            var _a;
            let value = event.target.value;
            let original = event.target.dataset.original_value;
            if (!value || !this.allowFreeEntries || !this.validateFreeEntry(value)) {
                // Not a good value, reset it.
                event.target.variant = "danger";
                return false;
            }
            event.target.variant = "success";
            // Add to internal list
            this.createFreeEntry(value);
            // Remove original from value & DOM
            if (value != original) {
                if (this.multiple) {
                    this.value = this.value.filter(v => v !== original);
                }
                else {
                    this.value = value;
                }
                (_a = this.querySelector("[value='" + original.replace(/'/g, "\\\'") + "']")) === null || _a === void 0 ? void 0 : _a.remove();
                this.__select_options = this.__select_options.filter(v => v.value !== original);
            }
        }
        /**
         * Start editing the current value if multiple=false
         *
         * @param {Et2Tag} tag
         */
        startEdit(tag) {
            const tag_value = tag ? tag.value : this.value;
            // hide the menu
            this.dropdown.hide();
            waitForEvent(this, "sl-after-hide").then(() => {
                // Turn on edit UI
                this._activeControls.classList.add("editing", "active");
                // Pre-set value to tag value
                this._editInputNode.style.display = "";
                this._editInputNode.value = tag_value;
                this._editInputNode.focus();
                // If they abort the edit, they'll want the original back.
                this._editInputNode.dataset.initial = tag_value;
            });
        }
        stopEdit(abort = false) {
            var _a, _b;
            // type to select will focus matching entries, but we don't want to stop the edit yet
            if (typeof abort == "object" && abort.type == "blur") {
                if (((_a = abort.relatedTarget) === null || _a === void 0 ? void 0 : _a.localName) == "sl-menu-item") {
                    return;
                }
                // Edit lost focus, accept changes
                abort = false;
            }
            const original = this._editInputNode.dataset.initial;
            delete this._editInputNode.dataset.initial;
            let value = abort ? original : this._editInputNode.value;
            this._editInputNode.value = "";
            if (value && value != original) {
                this.createFreeEntry(value);
                this.updateComplete.then(() => {
                    const item = this.querySelector("[value='" + value.replace(/'/g, "\\\'") + "']");
                    item.dispatchEvent(new CustomEvent("sl-select", { detail: { item } }));
                });
            }
            // Remove original from value & DOM
            if (value != original) {
                if (this.multiple) {
                    this.value = this.value.filter(v => v !== original);
                    (_b = this.querySelector("[value='" + original.replace(/'/g, "\\\'") + "']")) === null || _b === void 0 ? void 0 : _b.remove();
                }
                else {
                    this.value = value;
                }
                this.select_options = this.select_options.filter(v => v.value !== original);
            }
            this._activeControls.classList.remove("editing", "active");
            if (!this.multiple) {
                this.updateComplete.then(() => __awaiter(this, void 0, void 0, function* () {
                    // Don't know why, but this doesn't always work leaving the value hidden by prefix
                    yield this.dropdown.hide();
                    this.dropdown.classList.remove("select--open");
                    this.dropdown.panel.setAttribute("hidden", "");
                }));
            }
            this.syncItemsFromValue();
        }
        _handleSearchAbort(e) {
            this._activeControls.classList.remove("active");
            this.clearSearch();
            this.syncItemsFromValue();
        }
        /**
         * et2-searchbox (SlInput) sends out an event on change.
         * We don't care, and if we let it bubble it'll get in the way.
         * @param e
         * @protected
         */
        _handleSearchChange(e) {
            e.stopImmediatePropagation();
            e.preventDefault();
            return false;
        }
    }
    /**
     * When user is typing, we wait this long for them to be finished before we start the search
     * @type {number}
     * @protected
     */
    Et2WidgetWithSearch.SEARCH_TIMEOUT = 500;
    /**
     * We need at least this many characters before we start the search
     *
     * @type {number}
     * @protected
     */
    Et2WidgetWithSearch.MIN_CHARS = 2;
    /**
     * Limit server searches to 100 results, matches Link::DEFAULT_NUM_ROWS
     * @type {number}
     */
    Et2WidgetWithSearch.RESULT_LIMIT = 100;
    /**
     * These characters will end a free tag
     * @type {string[]}
     */
    Et2WidgetWithSearch.TAG_BREAK = ["Tab", "Enter", ","];
    return Et2WidgetWithSearch;
};
//# sourceMappingURL=SearchMixin.js.map