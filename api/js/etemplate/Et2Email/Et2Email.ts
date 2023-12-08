/**
 * EGroupware eTemplate2 - Email WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {html, LitElement, nothing, PropertyValues, TemplateResult} from "lit";
import {property} from "lit/decorators/property.js";
import {state} from "lit/decorators/state.js";
import {classMap} from "lit/directives/class-map.js";
import {repeat} from "lit/directives/repeat.js";
import {HasSlotController} from "../Et2Widget/slot";
import {SlOption, SlPopup, SlRemoveEvent} from "@shoelace-style/shoelace";
import shoelace from "../Styles/shoelace";
import {Et2EmailTag} from "../Et2Select/Tag/Et2EmailTag";
import {waitForEvent} from "../Et2Widget/event";
import styles from "./Et2Email.styles";
import {SelectOption} from "../Et2Select/FindSelectOptions";

/**
 * @summary Enter email addresses, offering suggestions from contacts
 * @documentation https://shoelace.style/components/select
 * @since 23.1
 *
 * @dependency sl-icon
 * @dependency sl-popup
 * @dependency et2-email-tag
 * @dependency et2-textbox
 *
 * @slot - The suggestion options. Must be `<sl-option>` elements. You can use `<sl-divider>` to group items visually.
 * @slot label - The input's label. Alternatively, you can use the `label` attribute.
 * @slot prefix - Used to prepend a presentational icon or similar element to the combobox.
 * @slot help-text - Text that describes how to use the input. Alternatively, you can use the `help-text` attribute.
 *
 * @event sl-change - Emitted when the control's value changes.
 * @event sl-input - Emitted when the control receives input.
 * @event sl-focus - Emitted when the control gains focus.
 * @event sl-blur - Emitted when the control loses focus.
 * @event sl-show - Emitted when the suggestion menu opens.
 * @event sl-after-show - Emitted after the suggestion menu opens and all animations are complete.
 * @event sl-hide - Emitted when the suggestion menu closes.
 * @event sl-after-hide - Emitted after the suggestion menu closes and all animations are complete.
 * @event sl-invalid - Emitted when the form control has been checked for validity and its constraints aren't satisfied.
 *
 * @csspart form-control - The form control that wraps the label, input, and help text.
 * @csspart form-control-label - The label's wrapper.
 * @csspart form-control-input - The textbox's wrapper.
 * @csspart form-control-help-text - The help text's wrapper.
 * @csspart prefix - The container that wraps the prefix slot.
 * @csspart listbox - The listbox container where options are slotted.
 * @csspart tags - The container that houses email tags
 * @csspart tag - The individual tags that represent each email address.
 * @csspart tag__base - The tag's base part.
 * @csspart tag__content - The tag's content part.
 * @csspart tag__remove-button - The tag's remove button.
 * @csspart tag__remove-button__base - The tag's remove button base part.
 */
export class Et2Email extends Et2InputWidget(LitElement)
{
	static shadowRootOptions = {...LitElement.shadowRootOptions, delegatesFocus: true};

	static get styles()
	{
		return [
			shoelace,
			...super.styles,
			styles
		];
	}

	/**
	 * The current value of the component, an array of valid email addresses
	 */
	@property({
		converter: {
			fromAttribute: (value : string) =>
			{
				// Parse string into array
				if(typeof value === 'string' && value.indexOf(',') !== -1)
				{
					let val = value.split(',');
					for(let n = 0; n < val.length - 1; n++)
					{
						while(val[n].indexOf('@') === -1 && n < val.length - 1)
						{
							val[n] += ',' + val[n + 1];
							val.splice(n + 1, 1);
						}
					}
					return val;
				}
				return value;
			},
			toAttribute: (value : string[]) => value.join(',')
		}
	})
	value : string[] = [];

	/** Placeholder text to show as a hint when the select is empty. */
	@property() placeholder = '';

	/** Allow drag and drop tags between two or more Et2Email widgets */
	@property({type: Boolean})
	allowDragAndDrop? : boolean;

	/** Allow placeholders like {{email}}, as well as real email-addresses */
	@property({type: Boolean})
	allowPlaceholder : boolean;

	/** Include mailing lists: returns them with their integer list_id */
	@property({type: Boolean})
	includeLists : boolean;

	/**
	 * If the email is a contact, we normally show the contact name instead of the email.
	 * Set to true to turn this off and always show just the email
	 * Mutually exclusive with fullEmail!
	 */
	@property({type: Boolean})
	onlyEmail : boolean;

	/** Show the full, original value email address under all circumstances, rather than the contact name for known contacts  */
	@property({type: Boolean})
	fullEmail : boolean;

	/** The component's help text. If you need to display HTML, use the `help-text` slot instead. */
	@property({attribute: 'help-text'}) helpText = '';

	/**
	 * Indicates whether the suggestions are open. You can toggle this attribute to show and hide the menu, or you can
	 * use the `show()` and `hide()` methods and this attribute will reflect the suggestion open state.
	 */
	@property({type: Boolean, reflect: true}) open = false;

	@property({type: Object}) searchOptions = {};
	@property({type: String}) searchUrl = "EGroupware\\Api\\Etemplate\\Widget\\Taglist::ajax_email";

	@state() searching = false;
	@state() hasFocus = false;
	@state() currentOption : SlOption;
	@state() currentTag : Et2EmailTag;


	get _popup() : SlPopup { return this.shadowRoot.querySelector("sl-popup");}

	get _listbox() : HTMLElement { return this.shadowRoot.querySelector("#listbox");}

	get _search() : HTMLInputElement { return this.shadowRoot.querySelector("#search");}

	get _tags() : Et2EmailTag[] { return Array.from(this.shadowRoot.querySelectorAll("et2-email-tag"));}

	get _suggestions() : SlOption[] { return Array.from(this.shadowRoot.querySelectorAll("sl-option"));}

	/**
	 * When user is typing, we wait this long for them to be finished before we start the search
	 * @type {number}
	 * @protected
	 */
	protected static SEARCH_TIMEOUT = 500;

	protected readonly hasSlotController = new HasSlotController(this, 'help-text', 'label');

	/** User preference to immediately close the search results after selecting a match */
	protected _close_on_select = true;

	protected _searchTimeout : number;
	protected _searchPromise : Promise<SelectOption[]> = Promise.resolve([]);
	protected _selectOptions : SelectOption[] = [];


	constructor(...args : any[])
	{
		// @ts-ignore
		super(...args);

		// Additional option for select email, per ticket #79694
		this._close_on_select = this.egw().preference("select_multiple_close") != "open";

		this.handleOpenChange = this.handleOpenChange.bind(this);
		this.handleLostFocus = this.handleLostFocus.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();
		this.open = false;
	}

	update(changedProperties : PropertyValues)
	{
		super.update(changedProperties)

		if(changedProperties.has("open"))
		{
			this.handleOpenChange();
		}
	}

	private addOpenListeners()
	{
		document.addEventListener('focusin', this.handleLostFocus);
		document.addEventListener('mousedown', this.handleLostFocus);
	}

	private removeOpenListeners()
	{
		document.removeEventListener('focusin', this.handleLostFocus);
		document.removeEventListener('mousedown', this.handleLostFocus);
	}


	/** Sets focus on the control. */
	focus(options? : FocusOptions)
	{
		this.hasFocus = true;
		// Should not be needed, but not firing the update
		this.requestUpdate("hasFocus");

		if(this._search)
		{
			this._search.focus(options);
		}
	}

	/** Removes focus from the control. */
	blur()
	{
		this.open = false;
		this.hasFocus = false;
		// Should not be needed, but not firing the update
		this.requestUpdate("open");
		this.requestUpdate("hasFocus");
		this._search.blur();

		clearTimeout(this._searchTimeout);
	}


	/** Shows the listbox. */
	async show()
	{
		if(this.open || this.disabled)
		{
			this.open = false;
			this.requestUpdate("open", true);
			return undefined;
		}

		this.open = true;
		this.requestUpdate("open", false)
		return waitForEvent(this, 'sl-after-show');
	}

	/** Hides the listbox. */
	async hide()
	{
		this.open = false;
		this.requestUpdate("open");
		if(!this.open || this.disabled)
		{
			return undefined;
		}

		return waitForEvent(this, 'sl-after-hide');
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

		this.searching = true;

		// Start the searches
		this._searchPromise = this.remoteSearch(this._search.value, this.searchOptions);
		return this._searchPromise.then(async() =>
		{
			this.searching = false;
			if(!this.open)
			{
				this.show();
			}

			await this.updateComplete;
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
	 *
	 * @param {string} search
	 * @param {object} options
	 * @returns Promise<SelectOption[]>
	 * @protected
	 */
	protected remoteSearch(search : string, options : object) : Promise<SelectOption[]>
	{
		// Include a limit, even if options don't, to avoid massive lists breaking the UI
		let sendOptions = {
			num_rows: 10,
			...options
		}
		return this.egw().request(this.egw().link(this.egw().ajaxUrl(this.egw().decodePath(this.searchUrl)),
			{query: search, ...sendOptions}), [search, sendOptions]).then((results) =>
		{
			return this.processRemoteResults(results);
		});
	}

	/**
	 * Add in remote results
	 *
	 * Any results that already exist will be removed to avoid duplicates
	 *
	 * @param results
	 * @protected
	 */
	protected processRemoteResults(entries)
	{
		if(!entries?.length)
		{
			return [];
		}

		this._selectOptions = entries;

		this.requestUpdate();

		return entries;
	}


	/**
	 * Focus has gone somewhere else
	 * @param {MouseEvent} event
	 */
	private handleLostFocus = (event : MouseEvent | KeyboardEvent) =>
	{
		// Close when clicking outside of the component
		const path = event.composedPath();
		if(this && !path.includes(this))
		{
			this.hide();
		}
	};

	async handleOpenChange()
	{
		if(this.open && !this.disabled)
		{
			// Reset the current option
			// TODO
			//this.setCurrentOption(this._suggestions[0]);

			// Show
			this.dispatchEvent(new CustomEvent('sl-show', {bubbles: true}));
			this.addOpenListeners();

			this._listbox.hidden = false;
			this._popup.active = true;

			// Select the appropriate option based on value after the listbox opens
			requestAnimationFrame(() =>
			{
				this.setCurrentOption(this.currentOption);
			});

			// Make sure the current option is scrolled into view (required for Safari)
			if(this.currentOption)
			{
				// TODO
				//scrollIntoView(this.currentOption, this._listbox, 'vertical', 'auto');
			}

			this.dispatchEvent(new CustomEvent('sl-after-show', {bubbles: true}));
		}
		else
		{
			// Hide
			this.dispatchEvent(new CustomEvent('sl-hide', {bubbles: true}));
			this.removeOpenListeners();

			this._listbox.hidden = true;
			this._popup.active = false;

			this.dispatchEvent(new CustomEvent('sl-after-hide', {bubbles: true}));
		}
	}

	private handleSearchFocus()
	{
		this.hasFocus = true;
		// Should not be needed, but not firing the update
		this.requestUpdate("hasFocus");

		// Reset tags to not take focus
		this._tags.forEach(t => t.tabIndex = -1);
		this.currentTag = null;

		this._search.setSelectionRange(0, 0);
	}

	private handleSearchBlur()
	{
		this.hasFocus = false;
		// Should not be needed, but not firing the update
		this.requestUpdate("hasFocus");
	}

	handleSearchKeyDown(event)
	{
		clearTimeout(this._searchTimeout);

		// Left at beginning goes to tags
		if(this._search.selectionStart == 0 && event.key == "ArrowLeft")
		{
			this.hide();
			this._tags.forEach(t => t.tabIndex = 0);
			this.currentTag = this._tags[this._tags.length - 1];
			this.currentTag.focus();
			event.stopPropagation();
			return;
		}

		// Tab on empty leaves
		if(this._search.value == "" && event.key == "Tab")
		{
			// Propagate, browser will do its thing
			return;
		}
		// Up / Down navigates options
		if(['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key))
		{
			// TODO - pass focus to list
			this.show();
			return;
		}
		// Tab or enter checks current value
		else if(event.key == "Enter")
		{
			event.preventDefault();
			this.startSearch();
			return;
		}
		else if(event.key == "Escape")
		{
			this.handleSearchAbort(event);
			this.hide();
			return;
		}

		// Start the search automatically if they have enough letters
		// -1 because we're in keyDown handler, and value is from _before_ this key was pressed
		if(this._search.value.length - 1 > 0)
		{
			this._searchTimeout = window.setTimeout(() => {this.startSearch()}, Et2Email.SEARCH_TIMEOUT);
		}
	}

	protected handleLabelClick()
	{
		this._search.focus();
	}

	/**
	 * Keyboard events that the search input did not grab
	 * (tags, otion navigation)
	 *
	 * @param {KeyboardEvent} event
	 */
	handleComboboxKeyDown(event : KeyboardEvent)
	{
		// Navigate between tags
		if(this.currentTag && (["ArrowLeft", "ArrowRight", "Home", "End"].includes(event.key)))
		{
			let nextTagIndex = this._tags.indexOf(this.currentTag);
			const tagCount = this._tags.length
			switch(event.key)
			{
				case 'ArrowLeft':
					nextTagIndex--;
					break;
				case 'ArrowRight':
					nextTagIndex++;
					break;
				case 'Home':
					nextTagIndex = 0;
					break;
				case 'End':
					nextTagIndex = this._tags.length - 1;
					break;
			}
			nextTagIndex = Math.max(0, nextTagIndex);
			if(nextTagIndex < tagCount && this._tags[nextTagIndex])
			{
				this._tags.forEach(t => t.tabIndex = -1);
				this.currentTag = this._tags[nextTagIndex];
				this.currentTag.tabIndex = 0;
				this.currentTag.focus();
			}
			else
			{
				// Arrow back to search, or got lost
				this._search.focus();
			}
			event.stopPropagation();
			return false;
		}
		// Remove tag
		if(event.target instanceof Et2EmailTag && ["Delete", "Backspace"].includes(event.key))
		{
			event.target.dispatchEvent(new CustomEvent('sl-remove', {bubbles: true}));
		}
		// Edit tag
		else if(event.target instanceof Et2EmailTag && ["Enter"].includes(event.key))
		{
			event.target.startEdit();
		}
	}

	handleTagChange(event)
	{
		// Need to update our value, or it will just redo the tag with the old value
		debugger;
		if(event.originalValue && this.value.indexOf(event.originalValue))
		{
			let index = this.value.indexOf(event.originalValue);
			this.value[index] = event.target.value;
			this.requestUpdate();
		}
	}

	handleTagRemove(event : SlRemoveEvent, value : string)
	{
		// Find the tag value and remove it from current value
		debugger;
		const index = this.value.indexOf(value);
		this.value.splice(index, 1);
		this.requestUpdate("value");
	}

	tagsTemplate()
	{
		return this.value.map((value, index) =>
		{
			// Wrap so we can handle the remove
			return html`
                <div @sl-remove=${(e : SlRemoveEvent) => this.handleTagRemove(e, value)}>
                    ${this.tagTemplate(value)}
                </div>`;
		});
	}

	tagTemplate(value)
	{
		const readonly = (this.readonly);
		const isEditable = !readonly;

		return html`
            <et2-email-tag
                    class=${classMap({
                        "et2-select-draggable": !this.readonly && this.allowDragAndDrop,
                    })}
                    .fullEmail=${this.fullEmail}
                    .onlyEmail=${this.onlyEmail}
                    .value=${value}
                    ?removable=${!readonly}
                    ?readonly=${readonly}
                    ?editable=${isEditable}
                    @mousedown=${(e) => {this._cancelOpen = true;}}
                    @change=${this.handleTagChange}
            >
            </et2-email-tag>`;
	}

	inputTemplate()
	{
		return html`
            <input id="search" type="text" part="input"
                   class="email__search"
                   exportparts="base:search__base"
                   autocomplete="off"
                   placeholder="${this.hasFocus ? "" : this.placeholder}"
                   @keydown=${this.handleSearchKeyDown}
                   @blur=${this.handleSearchBlur}
                   @focus=${this.handleSearchFocus}
            />
		`;
	}

	suggestionsTemplate()
	{
		return html`${repeat(this._selectOptions, (o : SelectOption) => o.value, this.optionTemplate.bind(this))}`;
	}


	/**
	 * Used to render each option into the suggestions
	 *
	 * @param {SelectOption} option
	 * @returns {TemplateResult}
	 */
	protected optionTemplate(option : SelectOption) : TemplateResult
	{
		const classes = option.class ? Object.fromEntries((option.class).split(" ").map(k => [k, true])) : {};
		const value = (<string>option.value).replaceAll(" ", "___");
		return html`
            <sl-option
                    part="option"
                    exportparts="prefix:tag__prefix, suffix:tag__suffix"
                    title="${!option.title || this.noLang ? option.title : this.egw().lang(option.title)}"
                    class=${classMap({
                        ...classes
                    })}
                    .value="${value}"
                    .option=${option}
                    ?disabled=${option.disabled}
            >
                <et2-lavatar slot="prefix" part="icon"
                             lname=${option.lname || nothing}
                             fname=${option.fname || nothing}
                             image=${option.icon || nothing}
                >
                </et2-lavatar>
                ${this.noLang ? option.label : this.egw().lang(option.label)}
            </sl-option>`;
	}

	render()
	{
		const hasLabelSlot = this.hasSlotController.test('label');
		const hasHelpTextSlot = this.hasSlotController.test('help-text');
		const hasLabel = this.label ? true : !!hasLabelSlot;
		const hasHelpText = this.helpText ? true : !!hasHelpTextSlot;
		const isPlaceholderVisible = this.placeholder && this.value.length === 0;

		// TODO Don't forget required & disabled

		return html`
            <div
                    part="form-control"
                    class=${classMap({
                        'form-control': true,
                        'form-control--medium': true,
                        'form-control--has-label': hasLabel,
                        'form-control--has-help-text': hasHelpText
                    })}
                    @click=${this.handleLabelClick}
            >
                <label
                        id="label"
                        part="form-control-label"
                        class="form-control__label"
                        aria-hidden=${hasLabel ? 'false' : 'true'}
                        @click=${this.handleLabelClick}
                >
                    <slot name="label">${this.label}</slot>
                </label>
                <div part="form-control-input" class="form-control-input">
                    <sl-popup
                            class=${classMap({
                                email: true,
                                input: true,
                                'email--open': this.open,
                                'email--disabled': this.disabled,
                                'email--focused': this.hasFocus,
                                'email--placeholder-visible': isPlaceholderVisible,
                                'email--top': this.placement === 'top',
                                'email--bottom': this.placement === 'bottom',
                            })}
                            placement="bottom"
                            strategy="fixed"
                            flip
                            shift
                            sync="width"
                            auto-size="vertical"
                            auto-size-padding="10"
                            ?active=${this.open}
                    >
                        <div
                                part="combobox"
                                class="email__combobox"
                                slot="anchor"
                                @keydown=${this.handleComboboxKeyDown}
                                @mousedown=${this.handleComboboxMouseDown}
                        >
                            <slot part="prefix" name="prefix" class="email__prefix"></slot>
                            ${this.tagsTemplate()}
                            ${this.inputTemplate()}
                            <slot part="suffix" name="suffix" class="email__suffix"></slot>
                        </div>
                        <div
                                id="listbox"
                                role="listbox"
                                aria-expanded=${this.open ? 'true' : 'false'}
                                aria-labelledby="label"
                                part="listbox"
                                class="email__listbox"
                                tabindex="-1"
                                @mouseup=${this.handleOptionClick}
                        >
                            ${this.suggestionsTemplate()}
                        </div>
                    </sl-popup>
                </div>
                <div
                        part="form-control-help-text"
                        id="help-text"
                        class="form-control__help-text"
                        aria-hidden=${hasHelpText ? 'false' : 'true'}
                >
                    <slot name="help-text">${this.helpText}</slot>
                </div>
            </div>
		`;
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-email", Et2Email);