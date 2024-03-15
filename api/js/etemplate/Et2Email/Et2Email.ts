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
import {styleMap} from "lit/directives/style-map.js";
import {keyed} from "lit/directives/keyed.js";
import {live} from "lit/directives/live.js";
import {map} from "lit/directives/map.js";
import {repeat} from "lit/directives/repeat.js";
import {HasSlotController} from "../Et2Widget/slot";
import {SlOption, SlPopup, SlRemoveEvent} from "@shoelace-style/shoelace";
import shoelace from "../Styles/shoelace";
import {Et2EmailTag} from "../Et2Select/Tag/Et2EmailTag";
import {waitForEvent} from "../Et2Widget/event";
import styles from "./Et2Email.styles";
import {SelectOption} from "../Et2Select/FindSelectOptions";
import {IsEmail} from "../Validators/IsEmail";
import {Validator} from "@lion/form-core";
import Sortable from "sortablejs/modular/sortable.complete.esm.js";
import {SearchMixinInterface} from "../Et2Widget/SearchMixin";

/**
 * @summary Enter email addresses, offering suggestions from contacts
 * @since 23.1
 *
 * @dependency sl-icon
 * @dependency sl-popup
 * @dependency et2-email-tag
 * @dependency et2-textbox
 *
 * @slot label - The input's label. Alternatively, you can use the `label` attribute.
 * @slot prefix - Used to prepend a presentational icon or similar element to the combobox.
 * @slot suffix - Like prefix, but after
 * @slot help-text - Text that describes how to use the input. Alternatively, you can use the `help-text` attribute.
 *
 * @event change - Emitted when the control's value changes.
 * @event sl-input - Emitted when the control receives input.
 * @event sl-focus - Emitted when the control gains focus.
 * @event sl-blur - Emitted when the control loses focus.
 * @event sl-show - Emitted when the suggestion menu opens.
 * @event sl-after-show - Emitted after the suggestion menu opens and all animations are complete.
 * @event sl-hide - Emitted when the suggestion menu closes.
 * @event sl-after-hide - Emitted after the suggestion menu closes and all animations are complete.
 *
 * @csspart form-control - The form control that wraps the label, input, and help text.
 * @csspart form-control-label - The label's wrapper.
 * @csspart form-control-input - The textbox's wrapper.
 * @csspart form-control-help-text - The help text's wrapper.
 * @csspart combobox - The visible part of the control that is not the listbox - tags, input, prefix & suffix
 * @csspart prefix - The container that wraps the prefix slot.
 * @csspart suffix - The container that wraps the suffix slot.
 * @csspart listbox - The listbox container where suggestions are slotted.
 * @csspart input - The input element
 * @csspart option - Each matching email address suggestion
 * @csspart tag - The individual tags that represent each email address.
 *
 * @cssproperty [--height=5] - The maximum height of the widget, to limit size when you have a lot of addresses.  Set by rows property, when set.
 */
export class Et2Email extends Et2InputWidget(LitElement) implements SearchMixinInterface
{
	// Solves some issues with focus
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
	 * The current value of the component, an array of valid email addresses.
	 * If allowPlaceholder=true, placeholders are also allowed
	 */
	@property({
		converter: {
			fromAttribute: (value : string) =>
			{
				// Parse string into array
				if(typeof value === 'string' && value.indexOf(',') !== -1)
				{
					return parseEmailsString(value, false);
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
	allowDragAndDrop? : boolean = true;

	/** Allow placeholders like {{email}}, as well as real email-addresses */
	@property({type: Boolean})
	allowPlaceholder : boolean;

	/** Include mailing lists: returns them with their integer list_id */
	@property({type: Boolean})
	includeLists : boolean;

	/**
	 * What to display for the selected email addresses
	 *
	 * {@link Et2EmailTag#emailDisplay}
	 */
	@property({type: String})
	emailDisplay : "full" | "email" | "name" | "domain";

	/** The component's help text. If you need to display HTML, use the `help-text` slot instead. */
	@property({attribute: 'help-text'}) helpText = '';

	/**
	 * Indicates whether the suggestions are open. You can toggle this attribute to show and hide the menu, or you can
	 * use the `show()` and `hide()` methods and this attribute will reflect the suggestion open state.
	 */
	@property({type: Boolean, reflect: true}) open = false;

	/**
	 * Custom search options, passed to the searchUrl along with the search text
	 *
	 * @type {{includeLists : boolean}}
	 */
	@property({type: Object}) searchOptions = {includeLists: true};

	/**
	 * Server-side search for suggested email addresses.
	 * Set to "" to cancel searching.
	 * @type {string}
	 */
	@property({type: String}) searchUrl = "EGroupware\\Api\\Etemplate\\Widget\\Taglist::ajax_email";

	/**
	 * Limit the maximum height of the widget, for when you have a lot of addresses.
	 * Set it to 1 for special single-line styling, 0 to disable
	 * @type {number}
	 */
	@property({type: Number, reflect: true}) rows;

	@state() searching = false;
	@state() hasFocus = false;
	@state() currentOption : SlOption;
	@state() currentTag : Et2EmailTag;

	/** If the select is limited to 1 row, we show the number of tags not visible */
	@state() _tagsHidden = 0;


	get _popup() : SlPopup { return this.shadowRoot.querySelector("sl-popup");}

	get _listbox() : HTMLElement { return this.shadowRoot.querySelector("#listbox");}

	get _search() : HTMLInputElement { return this.shadowRoot.querySelector("#search");}

	get _tags() : Et2EmailTag[] { return Array.from(this.shadowRoot.querySelectorAll("et2-email-tag"));}

	get _suggestions() : SlOption[] { return Array.from(this.shadowRoot.querySelectorAll("sl-option"));}

	/**
	 * When user is typing, we wait this long for them to be finished before we start the search
	 * @type {number}
	 * @protected
	 * @internal
	 */
	public static SEARCH_TIMEOUT : number = 500;

	/**
	 * Typing these characters will end the email address and start a new one
	 * @type {string[]}
	 *
	 * @internal
	 */
	public static TAG_BREAK : string[] = ["Tab", "Enter", ","];

	protected readonly hasSlotController = new HasSlotController(this, 'help-text', 'label');

	/** User preference to immediately close the search results after selecting a match
	 * @internal
	 */
	protected _close_on_select = true;

	protected _searchTimeout : number;
	protected _searchPromise : Promise<SelectOption[]> = Promise.resolve([]);
	protected _selectOptions : SelectOption[] = [];

	// Overflow Observer for +# display
	protected tagOverflowObserver : IntersectionObserver = null;

	// Drag / drop / sort
	protected _sortable : Sortable;

	// UID to force Lit to re-draw tags after sort
	private _valueUID : string;

	constructor(...args : any[])
	{
		// @ts-ignore
		super(...args);

		this.defaultValidators.push(new IsEmail(this.allowPlaceholder));

		// Set email display to preference, will be overridden by template attribute
		this.emailDisplay = this._getEmailDisplayPreference();

		// Additional option for select email, per ticket #79694
		this._close_on_select = this.egw().preference("select_multiple_close") != "open";

		this.handleOpenChange = this.handleOpenChange.bind(this);
		this.handleLostFocus = this.handleLostFocus.bind(this);
		this.handleSortEnd = this.handleSortEnd.bind(this);
		this.handleTagOverflow = this.handleTagOverflow.bind(this);
		this.handleMouseEnter = this.handleMouseEnter.bind(this);
		this.handleMouseLeave = this.handleMouseLeave.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();
		this.open = false;
		this._valueUID = this.egw().uid();
		this.updateComplete.then(() => this.makeSortable());
		document.addEventListener('focusin', this.handleLostFocus);
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();

		if(this._sortable)
		{
			this._sortable.destroy();
		}
		document.removeEventListener('focusin', this.handleLostFocus);
	}

	set_value(_value)
	{
		if (!Array.isArray(_value))
		{
			this.value = parseEmailsString(_value, this.allowPlaceholder);
		}
		else
		{
			this.value = _value;
		}
		this.requestUpdate("value");
	}

	willUpdate(changedProperties : PropertyValues)
	{
		super.willUpdate(changedProperties);

		if(changedProperties.has('allowPlaceholder'))
		{
			this.defaultValidators = (<Array<Validator>>this.defaultValidators).filter(v => !(v instanceof IsEmail));
			this.defaultValidators.push(new IsEmail(this.allowPlaceholder));
		}
	}

	update(changedProperties : PropertyValues)
	{
		super.update(changedProperties)

		if(changedProperties.has("open"))
		{
			this.handleOpenChange();
		}
	}

	firstUpdated(changedProperties : PropertyValues)
	{
		super.firstUpdated(changedProperties);

		// Make sure validators reflect allowPlaceholder, in case it's not caught by willUpdate()
		this.defaultValidators = (<Array<Validator>>this.defaultValidators).filter(v => !(v instanceof IsEmail));
		this.defaultValidators.push(new IsEmail(this.allowPlaceholder));
	}

	updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);

		// Re-set sorting / drag & drop
		if(changedProperties.has("value"))
		{
			this.makeSortable();
		}
		this.checkTagOverflow();
	}

	private _getEmailDisplayPreference()
	{
		const pref = this.egw().preference("emailTag", "mail") ?? "";
		switch(pref)
		{
			case "fullemail":
				return "full"
			default:
			case "onlyname":
				return "name";
			case "onlyemail":
				return "email";
			case "domain":
				return "domain";
		}
	}

	private addOpenListeners()
	{
		document.addEventListener('mousedown', this.handleLostFocus);
	}

	private removeOpenListeners()
	{
		document.removeEventListener('mousedown', this.handleLostFocus);
	}

	protected makeSortable()
	{
		if(this._sortable)
		{
			this._sortable.destroy();
		}

		if(!this.allowDragAndDrop)
		{
			this.classList.remove("et2-sortable-email");
			return;
		}
		this.classList.add("et2-sortable-email");
		let pull : boolean | string = !this.disabled && !this.readonly;
		if(this.readonly && !this.disabled)
		{
			pull = 'clone';
		}

		this._sortable = Sortable.create(this.shadowRoot.querySelector('.email__combobox'), {
			draggable: "et2-email-tag",
			group: {
				name: "email",
				pull: pull,
				put: !(this.readonly || this.disabled)
			},
			onEnd: this.handleSortEnd
		});
	}

	/**
	 * Sets the current suggestion, which is the option the user is currently interacting with (e.g. via keyboard).
	 * Only one option may be "current" at a time.
	 */
	private setCurrentOption(option : SlOption | null)
	{
		// Clear selection
		this._suggestions.forEach(el =>
		{
			el.current = false;
			el.tabIndex = -1;
		});

		// Select the target option
		this.currentOption = option;
		if(option)
		{
			option.current = true;
			option.tabIndex = 0;
			option.focus();
		}
	}

	private setCurrentTag(tag : Et2EmailTag)
	{
		this._tags.forEach(t =>
		{
			t.tabIndex = -1;
			if(t.current)
			{
				t.current = false;
				t.requestUpdate();
			}
		});
		this.currentTag = tag;
		if(tag)
		{
			this.currentTag.tabIndex = 0;
			this.currentTag.current = true;
			this.currentTag.requestUpdate();
			this.currentTag.focus();
		}
	}

	protected checkTagOverflow()
	{
		// Create / destroy intersection observer
		if(this.readonly && this.rows == "1" && this.tagOverflowObserver == null)
		{
			this.tagOverflowObserver = new IntersectionObserver(this.handleTagOverflow, {
				root: this.shadowRoot.querySelector(".email__combobox"),
				threshold: 0.1
			});
		}
		else if((!this.readonly || this.rows !== 1) && this.tagOverflowObserver !== null)
		{
			this.tagOverflowObserver.disconnect();
			this.tagOverflowObserver = null;
		}

		if(this.tagOverflowObserver)
		{
			this.updateComplete.then(() =>
			{
				for(const tag of Array.from(this.shadowRoot.querySelectorAll(".email__combobox et2-email-tag")))
				{
					this.tagOverflowObserver.observe(tag);
				}
			});
		}
	}


	/**
	 * Create an entry that is not in the suggestions and add it to the value
	 *
	 * @param {string} text Used as both value and label
	 */
	public addAddress(text : string) : boolean
	{
		if(!text || !this.validateAddress(text))
		{
			return false;
		}
		// Make sure not to double-add
		if(!this.value.includes(text.replace(/'/g, "\\\'")))
		{
			this.value.push(text.trim());
			this.requestUpdate('value');
		}

		this.dispatchEvent(new Event("change", {bubbles: true}));

		return true;
	}

	/**
	 * Check if a free entry value is acceptable.
	 * We use validators directly using the proposed value
	 *
	 * @param text
	 * @returns {boolean}
	 */
	public validateAddress(text) : boolean
	{
		let validators = [...this.validators, ...this.defaultValidators];
		let result = validators.filter(v =>
			v.execute(text, v.param, {node: this}),
		);
		return validators.length > 0 && result.length == 0 || validators.length == 0;
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
		if(!this.open || this.disabled)
		{
			return undefined;
		}

		this.open = false;
		this.requestUpdate("open");
		return waitForEvent(this, 'sl-after-hide');
	}


	/**
	 * Start searching for contacts matching what has been typed
	 */
	public async startSearch()
	{
		// Stop timeout timer
		clearTimeout(this._searchTimeout);

		// Clear current option, it's probably going to go away
		this.setCurrentOption(null);

		// If no searchUrl, no search
		if(!this.searchUrl)
		{
			return;
		}

		this.searching = true;
		this.requestUpdate("searching");

		// Start the searches
		this._searchPromise = this.remoteSearch(this._search.value, this.searchOptions);
		return this._searchPromise.then(async() =>
		{
			this.searching = false;
			this.requestUpdate("searching", true);
			if(!this.open && this.hasFocus)
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
	 * @internal
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
	 * @internal
	 */
	protected processRemoteResults(entries)
	{
		this._selectOptions = entries;
		this.updateComplete.then(() =>
		{
			this.currentOption = this._suggestions[0];
		});

		this.requestUpdate();

		return entries;
	}

	/**
	 * The end of a sort, either internal or between widgets that deal with email
	 *
	 * @param event
	 * @protected
	 * @internal
	 */
	protected handleSortEnd(event)
	{
		if(this.disabled || this.readonly || !event.item?.value || !this.validateAddress(event.item.value) ||
			// No real change
			event.from === event.to && event.oldDraggableIndex == event.newDraggableIndex
		)
		{
			return;
		}
		const tag = <Et2EmailTag>event.item;
		const from = Sortable.utils.closest(event.from, "et2-email, .et2-sortable-email");
		const to = Sortable.utils.closest(event.to, "et2-email, .et2-sortable-email");

		if(from == this)
		{
			const index = this.value.indexOf(tag.value);

			if(index > -1)
			{
				this.value.splice(index, 1);
			}

			// Reset focus
			/*
			if(typeof from.focus == "function")
			{
				this.updateComplete.then(() =>
				{
					from.focus();
				});
			}
			 */
			// Update key to force Lit to redraw tags
			this._valueUID = this.egw()?.uid() ?? new Date().toISOString();
		}
		if(to === this)
		{
			let targetIndex = typeof event.newDraggableIndex == "number" ? event.newDraggableIndex : this.value.length;
			this.value.splice(targetIndex, 0, tag.value);

			// Update key to force Lit to redraw tags
			this._valueUID = this.egw()?.uid() ?? new Date().toISOString();
		}
		else if(typeof to.handleSortEnd == "function")
		{
			to.handleSortEnd(event);
		}
		// Remove tag to avoid occasional duplication
		tag.remove();

		this.requestUpdate("value");
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
			this.setCurrentOption(this._suggestions[0]);

			// Show
			this.dispatchEvent(new CustomEvent('sl-show', {bubbles: true}));
			this.addOpenListeners();

			this._listbox.hidden = false;
			this._popup.active = true;

			// Make sure the current option is scrolled into view (required for Safari)
			if(this.currentOption)
			{
				this.currentOption.scrollIntoView();
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


	/**
	 * Callback for the intersection observer so we know when tags don't fit
	 *
	 * Here we set the flag to show how many more tags are hidden, but this only happens
	 * when there are more tags than space.
	 *
	 * @param entries
	 * @protected
	 */
	protected handleTagOverflow(entries : IntersectionObserverEntry[])
	{
		const oldCount = this._tagsHidden;
		let visibleTagCount = this.value.length - this._tagsHidden;
		let update = false;
		// If we have all tags, start from 0, otherwise it's just a change
		if(entries.length == this.value.length)
		{
			visibleTagCount = 0;
		}
		else
		{
			update = true;
		}
		for(const tag of entries)
		{
			if(tag.isIntersecting)
			{
				visibleTagCount++;
			}
			else if(update && !tag.isIntersecting)
			{
				visibleTagCount--;
			}
			else
			{
				break;
			}
		}
		if(visibleTagCount && visibleTagCount < this.value.length)
		{
			this._tagsHidden = this.value.length - visibleTagCount;
		}
		else
		{
			this._tagsHidden = 0;
		}
		this.requestUpdate("_tagsHidden", oldCount);
	}


	/**
	 * Sometimes users paste multiple comma separated values at once.  Split them then handle normally.
	 * Overridden here to handle email addresses that may have commas using the regex from the validator.
	 *
	 * @param {ClipboardEvent} event
	 * @protected
	 */
	protected handlePaste(event : ClipboardEvent)
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
		let values = parseEmailsString(paste, this.allowPlaceholder);

		if(values)
		{
			values.forEach(v =>
			{
				this.addAddress(v.trim());
			});
			this.hide();

			// Update key to force Lit to redraw tags
			this._valueUID = this.egw()?.uid() ?? new Date().toISOString();
			this.dispatchEvent(new Event("change", {bubbles: true}));
		}
	}

	private handleSearchFocus()
	{
		// Clear any manual message (errors on invalid search text)
		this.set_validation_error(false);

		this.hasFocus = true;
		// Should not be needed, but not firing the update
		this.requestUpdate("hasFocus");

		// Reset tags to not take focus
		this.setCurrentTag(null);

		this._search.setSelectionRange(this._search.value.length, this._search.value.length);
	}

	private handleSearchBlur(event : FocusEvent)
	{
		this.hasFocus = false;
		// Should not be needed, but not firing the update
		this.requestUpdate("hasFocus");

		// If they had something OK typed, use it, but only if focus went outside Et2Email
		// because maybe they clicked an option which took focus
		if(event.composedPath().includes(this))
		{
			if(this.addAddress(this._search.value.trim()))
			{
				this._search.value = "";
				this.dispatchEvent(new Event("change", {bubbles: true}));
			}
			else if(this._search.value)
			{
				// Invalid input, show message.  Not part of the value, so normal validation doesn't apply
				// Can't just call this.validate(), it will get cleared immediately
				this.set_validation_error(this.egw().lang("Invalid email") + ' "' + this._search.value + '"')
			}
		}
	}

	handleSearchKeyDown(event)
	{
		clearTimeout(this._searchTimeout);

		// Left at beginning goes to tags
		if(this._search.selectionStart == 0 && event.key == "ArrowLeft")
		{
			this.hide();
			this._tags.forEach(t => t.tabIndex = 0);
			if(this._tags.length > 0)
			{
				this.setCurrentTag(this._tags[this._tags.length - 1]);
			}
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
		if(['ArrowDown', 'ArrowUp'].includes(event.key) && this._suggestions.length)
		{
			if(!this.open)
			{
				return this.show();
			}
			return this.handleSuggestionsKeyDown(event);
		}
		// Tab or enter checks current value
		else if(Et2Email.TAG_BREAK.indexOf(event.key) !== -1)
		{
			// Check for valid email or current selection
			if(!this.validateAddress(this._search.value.trim()) &&
				this.currentOption && this.currentOption.value.toLowerCase().includes(this._search.value.toLowerCase()))
			{
				this._search.value = this.currentOption.value.replaceAll("___", " ");
			}
			if(this.addAddress(this._search.value.trim()))
			{
				this.open = false;
				this._search.value = "";
				this.dispatchEvent(new Event("change", {bubbles: true}));
			}
			if(event.key == "Tab")
			{
				this.blur();
				// Allow tab to change the focus
			}
			else
			{
				// Don't want the key to do its normal thing
				event.stopPropagation();
				event.preventDefault();
			}
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
			this._selectOptions = [];
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
				this.setCurrentTag(this._tags[nextTagIndex]);
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
			const tags = this._tags;
			let index = tags.indexOf(event.target);
			event.target.dispatchEvent(new CustomEvent('sl-remove', {bubbles: true}));
			index += event.key == "Delete" ? 1 : -1;
			if(index >= 0 && index < tags.length)
			{
				this.setCurrentTag(this._tags[index]);
			}
			else
			{
				this._search.focus();
			}
		}
		// Edit tag
		else if(event.target instanceof Et2EmailTag && ["Enter"].includes(event.key))
		{
			event.target.startEdit();
		}
	}

	/**
	 * If rows=1 and multiple=true, when they put the mouse over the widget show all tags
	 * @param {MouseEvent} e
	 * @private
	 */
	protected handleMouseEnter(e : MouseEvent)
	{
		if(this.rows == "1" && this.value.length > 1)
		{
			e.stopPropagation();

			// Bind to turn this all off
			this.addEventListener("mouseleave", this.handleMouseLeave);

			this.classList.add("hover");
			this.requestUpdate();
		}
	}

	/**
	 * If we're showing all rows because of _handleMouseEnter, reset when mouse leaves
	 * @param {MouseEvent} e
	 * @private
	 */
	protected handleMouseLeave(e : MouseEvent)
	{
		this.classList.remove("hover");
		this.requestUpdate();
	}

	/**
	 * Keyboard events from the suggestion list
	 *
	 * @param {KeyboardEvent} event
	 */
	handleSuggestionsKeyDown(event : KeyboardEvent)
	{
		// Select the option
		if(this.currentOption && ["ArrowRight", " ", ...Et2Email.TAG_BREAK].includes(event.key) &&
			this.addAddress((<string>this.currentOption.value).replaceAll("___", " "))
		)
		{
			if(this._close_on_select)
			{
				this.open = false;
			}
			this._search.focus();
			this._search.value = "";
			if(event.key !== "Tab")
			{
				event.stopPropagation();
				event.preventDefault();
			}
			return;
		}
		// Navigate options
		if(["ArrowUp", "ArrowDown", "Home", "End"].includes(event.key))
		{
			event.stopPropagation()
			const suggestions = this._suggestions;
			const currentIndex = suggestions.indexOf(this.currentOption);
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
					this.setCurrentOption(null);
					this._search.focus();
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

			this.setCurrentOption(suggestions[newIndex]);
		}
		else if(["Escape"])
		{
			this.open = false;
			this._search.focus();
		}
	}

	/**
	 * Mouse up from the suggestion list
	 * @param event
	 */
	handleSuggestionsMouseUp(event : MouseEvent)
	{
		if(typeof event.target.value == "undefined")
		{
			return;
		}

		const value = ((<SlOption>event.target).value).replaceAll("___", " ");
		this.addAddress(value);
		this._search.value = "";
		this._search.focus();
		this.requestUpdate("value");
		this.dispatchEvent(new Event("change", {bubbles: true}));
		if(this._close_on_select)
		{
			this.open = false;
		}
	}

	handleTagChange(event)
	{
		// Need to update our value, or it will just redo the tag with the old value
		if(event.originalValue && this.value.includes(event.originalValue))
		{
			let index = this.value.indexOf(event.originalValue);
			this.value[index] = event.target.value;
			this.requestUpdate();
			this.dispatchEvent(new Event("change", {bubbles: true}));
		}
		if(event.target.current)
		{
			this.setCurrentTag(event.target);
		}
	}

	handleTagRemove(event : SlRemoveEvent, value : string)
	{
		// Find the tag value and remove it from current value
		const index = this.value.indexOf(value);
		this.value.splice(index, 1);
		this.requestUpdate("value");
		this.dispatchEvent(new Event("change", {bubbles: true}));
	}

	/* Sub-template when [readonly][rows=1] to show all tags in current value in popup */
	readonlyHoverTemplate()
	{
		if(!this.classList.contains("hover"))
		{
			return nothing;
		}

		// Offset distance to open _over_ the rest
		let distance = (-1 * parseInt(getComputedStyle(this).height)) + 1;
		return html`
            <sl-popup
                    active
                    anchor=${this}
                    auto-size="both"
                    class="hover__popup details hoist details__body"
                    distance=${distance}
                    placement="bottom"
                    sync="width"
            >
                ${this.tagsTemplate()}
            </sl-popup>
		`;
	}

	tagsTemplate()
	{
		return html`${keyed(this._valueUID, map(this.value, (value, index) => this.tagTemplate(value)))}`;
	}

	tagTemplate(value)
	{
		const readonly = (this.readonly || this.disabled);
		const isEditable = !readonly;
		const isValid = this.validateAddress(value);

		return html`
            <et2-email-tag
                    exportparts="image"
                    part="tag"
                    class=${classMap({
                        "et2-select-draggable": !this.readonly && this.allowDragAndDrop,
                    })}
                    variant=${this.isValid ? nothing : "danger"}
                    .emailDisplay=${this.emailDisplay ?? nothing}
                    .value=${live(value)}
                    ?removable=${!readonly}
                    ?readonly=${readonly}
                    ?editable=${isEditable}
                    @sl-remove=${(e : SlRemoveEvent) => this.handleTagRemove(e, value)}
                    @mousedown=${(e) => {this._cancelOpen = true;}}
                    @dblclick=${(e) => {e.target.startEdit();}}
                    @change=${this.handleTagChange}
            >
            </et2-email-tag>`;
	}

	protected tagLimitTemplate() : TemplateResult | typeof nothing
	{
		if(this._tagsHidden == 0)
		{
			return nothing;
		}
		return html`
            <sl-tag
                    part="tag__limit"
                    class="tag_limit"
                    slot="expand-icon"
            >+${this._tagsHidden}
            </sl-tag>`;
	}

	inputTemplate()
	{
		return html`
            <input id="search" type="text" part="input"
                   class="email__search"
                   exportparts="base:search__base"
                   autocomplete="off"
                   ?disabled=${this.disabled}
                   ?readonly=${this.readonly}
                   placeholder="${this.hasFocus || this.value.length > 0 || this.disabled || this.readonly ? "" : this.placeholder}"
                   tabindex="0"
                   @keydown=${this.handleSearchKeyDown}
                   @blur=${this.handleSearchBlur}
                   @focus=${this.handleSearchFocus}
                   @paste=${this.handlePaste}
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
                    exportparts="prefix:tag__prefix, suffix:tag__suffix, image"
                    title="${!option.title || this.noLang ? option.title : this.egw().lang(option.title)}"
                    class=${classMap({
                        ...classes
                    })}
                    .value="${value}"
                    .option=${option}
                    ?disabled=${option.disabled}
            >
                <et2-lavatar slot="prefix" exportparts="image" part="icon" size="1.8em"
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
		const isPlaceholderVisible = this.placeholder && this.value.length === 0 && !this.disabled && !this.readonly;

		let styles = {};

		if(this.rows !== 0)
		{
			styles["--height"] = this.rows;
		}

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
                    style=${styleMap(styles)}
                    @click=${this.handleLabelClick}
                    @mouseenter=${this.handleMouseEnter}
                    @mousedown=${() =>
                    {
                        if(!this.hasFocus)
                        {
                            // Helps Sortable work every time
                            this.focus();
                        }
                    }}
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
                ${this.readonlyHoverTemplate()}
                <div part="form-control-input" class="form-control-input">
                    <sl-popup
                            class=${classMap({
                                email: true,
                                input: true,
                                'email--open': this.open,
                                'email--disabled': this.disabled,
                                'email--readonly': this.readonly,
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
                                part="combobox base"
                                class="email__combobox"
                                slot="anchor"
                                @keydown=${this.handleComboboxKeyDown}
                        >
                            <slot part="prefix" name="prefix" class="email__prefix"></slot>
                            ${this.tagsTemplate()}
                            ${this.inputTemplate()}
                            ${this.tagLimitTemplate()}
                            ${this.searching ? html`
                                <sl-spinner class="email__loading"></sl-spinner>` : nothing}
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
                                @keydown=${this.handleSuggestionsKeyDown}
                                @mouseup=${this.handleSuggestionsMouseUp}
                        >
                            ${(this._selectOptions && this._selectOptions.length) ? this.suggestionsTemplate() : this.egw().lang("no matches found")}
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

customElements.define("et2-email", Et2Email);

/**
 * Parse string that may contain multiple comma separated email addresses into an array
 *
 * @param {string} value
 * @returns {string[]}
 * @protected
 */
function parseEmailsString(value : string, allowPlaceholder = false) : string[]
{
	if (!value) return [];
	let preg = allowPlaceholder ? IsEmail.EMAIL_PLACEHOLDER_PREG : IsEmail.EMAIL_PREG;
	// Trim line start / end anchors off validation regex, make global
	let regex = new RegExp(preg.toString().substring(2, preg.toString().length - 3), 'g');
	return value.match(regex);
}