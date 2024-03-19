/**
 * EGroupware eTemplate2 - Select WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css, LitElement, nothing, PropertyValues, TemplateResult} from "lit";
import {html, literal, StaticValue} from "lit/static-html.js";
import {Et2WidgetWithSelectMixin} from "./Et2WidgetWithSelectMixin";
import {SelectOption} from "./FindSelectOptions";
import shoelace from "../Styles/shoelace";
import {RowLimitedMixin} from "../Layout/RowLimitedMixin";
import {Et2WithSearchMixin} from "./SearchMixin";
import {property} from "lit/decorators/property.js";
import {SlChangeEvent, SlOption, SlSelect} from "@shoelace-style/shoelace";
import {repeat} from "lit/directives/repeat.js";
import {classMap} from "lit/directives/class-map.js";
import {state} from "lit/decorators/state.js";

// export Et2WidgetWithSelect which is used as type in other modules
export class Et2WidgetWithSelect extends RowLimitedMixin(Et2WidgetWithSelectMixin(LitElement))
{
	// Gets an array of all <sl-option> elements
	protected getAllOptions()
	{
		// @ts-ignore
		return [...this.querySelectorAll<Et2Option>('sl-option')];
	}
};
/**
 * @summary Select one or more options from a given list
 * @since 23.1
 *
 * @dependency sl-select
 * @dependency sl-option
 *
 * At its most basic, you can select one option from a list provided.  The list can be passed from the server in
 * the sel_options array or options can be added as children in the template.  Some extending classes provide specific
 * options, such as Et2SelectPercent or Et2SelectCountry.  All provided options will be mixed together and used.
 *
 * To allow selecting more than one option, use the attribute multiple="true".   This will take & return an array
 * as value instead of just a string.
 *
 * SearchMixin adds additional abilities to ALL select boxes
 * @see Et2WithSearchMixin
 *
 * Override for extending widgets:
 * # Custom display of selected value
 * 	When selecting a single value (!multiple) you can override doLabelChange() to customise the displayed label
 * 	@see Et2SelectCategory, which adds in the category icon
 *
 * # Custom option rows
 *  Options can have 'class' and 'icon' properties that will be used for the option
 * 	The easiest way for further customisation to use CSS in an external file (like etemplate2.css) and ::part().
 * 	@see Et2SelectCountry which displays flags via CSS instead of using SelectOption.icon
 *
 * # Custom tags
 * 	When multiple is set, instead of a single value each selected value is shown in a tag.  While it's possible to
 * 	use CSS to some degree, we can also use a custom tag class that extends Et2Tag.
 * 	1.  Create the extending class
 * 	2.  Make sure it's loaded (add to etemplate2.ts)
 * 	3.  In your extending Et2Select, override get tagTag() to return the custom tag name
 *
 * @slot - Reflected into listbox options. Must be <sl-option> elements. You can use <sl-divider> to group items visually.  Normally you set the options by parameter.
 * @slot prefix - Used to prepend a presentational icon or similar element to the combobox.
 * @slot help-text - Text that describes how to use the input. Alternatively, you can use the `help-text` attribute.
 *
 * @event change - Emitted when the control's value changes.
 * @event sl-clear - Emitted when the control’s value is cleared.
 * @event sl-input - Emitted when the control receives input.
 * @event sl-focus - Emitted when the control gains focus.
 * @event sl-blur - Emitted when the control loses focus.
 * @event sl-show - Emitted when the suggestion menu opens.
 * @event sl-after-show - Emitted after the suggestion menu opens and all animations are complete.
 * @event sl-hide - Emitted when the suggestion menu closes.
 * @event sl-after-hide - Emitted after the suggestion menu closes and all animations are complete.
 *
 * @csspart prefix - The container that wraps the prefix slot.
 * @csspart tags - The container that houses option tags when multiselect is used.
 * @csspart display-input - The element that displays the selected option’s label, an <input> element.
 * @csspart expand-icon - The container that wraps the expand icon.
 * @csspart combobox - The container the wraps the prefix, combobox, clear icon, and expand button.
 * @csspart listbox - The listbox container where options are slotted.
 * @csspart option - The options in the listbox container
 * @csspart icon - Icon in the option
 * @csspart emptyLabel - Wrapper around the label shown when there is no option selected
 * @csspart tag__prefix - The container that wraps the option prefix
 * @csspart tag__suffix - The container that wraps the option suffix
 * @csspart tag__limit - Element that is shown when the number of selected options exceeds maxOptionsVisible
 */
// @ts-ignore SlSelect styles is a single CSSResult, not an array, so TS complains
export class Et2Select extends Et2WithSearchMixin(Et2WidgetWithSelect)
{
	static get styles()
	{
		return [
			// Parent (SlSelect) returns a single cssResult, not an array
			shoelace,
			super.styles,
			css`
			  :host {
				display: block;
				flex: 1 0 auto;
				--icon-width: 20px;
			  }


			  ::slotted(img), img {
				vertical-align: middle;
			}
			
			/* Get rid of padding before/after options */
			sl-menu::part(base) {
				padding: 0px;
			}
			/* No horizontal scrollbar, even if options are long */
			.dropdown__panel {
				overflow-x: clip;
			  }

			  /* Ellipsis when too small */

			  ::part(tags) {
				max-width: 100%;
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
				max-height: calc(var(--rows, 5) * (var(--sl-input-height-medium) * 0.8))
			  }

				:host([readonly][rows='1']) ::part(tags) {
				overflow: hidden;
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
				background-color: white;
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
			`
		];
	}

	static get properties()
	{
		return {
			...super.properties,
			/**
			 * Toggle between single and multiple selection
			 */
			multiple: {
				type: Boolean,
				reflect: true,
			},

			/**
			 * Click handler for individual tags instead of the select as a whole.
			 * Only used if multiple=true so we have tags
			 */
			onTagClick: {
				type: Function,
			}
		}
	}


	/** Placeholder text to show as a hint when the select is empty. */
	@property() placeholder = '';
	/** Allows more than one option to be selected. */
	@property({type: Boolean, reflect: true}) multiple = false;
	/** Disables the select control. */
	@property({type: Boolean, reflect: true}) disabled = false;

	/** Adds a clear button when the select is not empty. */
	@property({type: Boolean}) clearable = false;

	/** The select's label. If you need to display HTML, use the `label` slot instead. */
	@property() label = '';

	/**
	 * The preferred placement of the select's menu. Note that the actual placement may vary as needed to keep the listbox
	 * inside of the viewport.
	 */
	@property({reflect: true}) placement : 'top' | 'bottom' = 'bottom';

	/** The select's help text. If you need to display HTML, use the `help-text` slot instead. */
	@property({attribute: 'help-text'}) helpText = '';

	/** If the select is limited to 1 row, we show the number of tags not visible */
	@state()
	protected _tagsHidden = 0;

	private __value : string | string[] = "";

	protected tagOverflowObserver : IntersectionObserver = null;

	constructor()
	{
		super();
		this.hoist = true;

		this._tagTemplate = this._tagTemplate.bind(this);
		this._handleMouseEnter = this._handleMouseEnter.bind(this);
		this._handleMouseLeave = this._handleMouseLeave.bind(this);
		this._handleTagOverflow = this._handleTagOverflow.bind(this);
	}
	/**
	 * List of properties that get translated
	 *
	 * @returns object
	 */
	static get translate()
	{
		return {
			...super.translate,
			emptyLabel: true
		}
	}

	connectedCallback()
	{
		super.connectedCallback();
		this.updateComplete.then(() =>
		{
			this.addEventListener("sl-change", this._triggerChange);
			// Fixes missing empty label
			this.select?.requestUpdate("value");
			// Fixes incorrect opening position
			this.select?.popup?.handleAnchorChange();

			// requestUpdate("value") above means we need to check tags again
			this.select.updateComplete.then(() => {this.checkTagOverflow(); });
		});
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();

		this.removeEventListener("sl-change", this._triggerChange);
	}

	_triggerChange(e)
	{
		if(super._triggerChange(e))
		{
			this.dispatchEvent(new Event("change", {bubbles: true}));
		}
	}

	/**
	 * Handle the case where there is no value set, or the value provided is not an option.
	 * If this happens, we choose the first option or empty label.
	 *
	 * Careful when this is called.  We change the value here, so an infinite loop is possible if the widget has
	 * onchange.
	 *
	 */
	protected fix_bad_value()
	{
		// Stop if there are no options
		if(!Array.isArray(this.select_options) || this.select_options.length == 0)
		{
			// Nothing to do here
			return;
		}

		// emptyLabel is fine
		if((this.value == '' || this.value == []) && (this.emptyLabel || this.placeholder))
		{
			return;
		}

		let valueArray = this.getValueAsArray();

		// Check for value using missing options (deleted or otherwise not allowed)
		let filtered = this.filterOutMissingOptions(valueArray);
		if(filtered.length != valueArray.length)
		{
			this.value = filtered;
			return;
		}

		// Multiple is allowed to be empty, and if we don't have an emptyLabel or options nothing to do
		if(this.multiple || (!this.emptyLabel && this.select_options.length === 0))
		{
			return;
		}

		// See if parent (search / free entry) is OK with it
		if(super.fix_bad_value())
		{
			return;
		}
		// If somebody gave '' as a select_option, let it be
		if(this.value === '' && this.select_options.filter((option) => this.value === option.value).length == 1)
		{
			return;
		}
		// If no value is set, choose the first option
		// Only do this on once during initial setup, or it can be impossible to clear the value

		// value not in options --> use emptyLabel, if exists, or first option otherwise
		if(this.select_options.filter((option) => valueArray.find(val => val == option.value) ||
			Array.isArray(option.value) && option.value.filter(o => valueArray.find(val => val == o.value))).length === 0)
		{
			let oldValue = this.value;
			this.value = this.emptyLabel ? "" : "" + this.select_options[0]?.value;
			// ""+ to cast value of 0 to "0", to not replace with ""
			this.requestUpdate("value", oldValue);
		}
	}

	@property()
	get value()
	{
		// Handle a bunch of non-values, if it's multiple we want an array
		if(this.multiple && (this.__value == "null" || this.__value == null || typeof this.__value == "undefined" ||
			!this.emptyLabel && this.__value == "" && !this.select_options.find(o => o.value == "")))
		{
			return [];
		}
		if(!this.multiple && !this.emptyLabel && this.__value == "" && !this.select_options.find(o => o.value == ""))
		{
			return null;
		}
		return this.multiple ?
			   this.__value ?? [] :
			   this.__value ?? "";
	}

	// @ts-ignore
	set value(val : string | string[] | number | number[])
	{
		if(typeof val === "undefined" || val == null)
		{
			val = "";
		}
		if(typeof val === 'string' && val.indexOf(',') !== -1 && this.multiple)
		{
			val = val.split(',');
		}
		if(typeof val === 'number')
		{
			val = val.toString();
		}
		const oldValue = this.value;
		if(Array.isArray(val))
		{
			// Make sure value has no duplicates, and values are strings
			this.__value = <string[]>[...new Set(val.map(v => (typeof v === 'number' ? v.toString() : v || '')))];
		}
		else
		{
			this.__value = val;
		}
		if(this.multiple && typeof this.__value == "string")
		{
			this.__value = this.__value != "" ? [this.__value] : [];
		}
		else if(!this.multiple && Array.isArray(this.__value))
		{
			this.__value = this.__value.toString();
		}
		if(this.select)
		{
			this.select.value = this.shoelaceValue;
		}
		this.requestUpdate("value", oldValue);
	}

	/**
	 * Check a value for missing options and remove them.
	 *
	 * We'll warn about it in the helpText, and if they save the change will be made.
	 * This is to avoid the server-side validation error, which the user can't do much about.
	 *
	 * @param {string[]} value
	 * @returns {string[]}
	 */
	filterOutMissingOptions(value : string[]) : string[]
	{
		if(!this.readonly && value && value.length > 0 && !this.allowFreeEntries && this.select_options.length > 0)
		{
			function filterBySelectOptions(arrayToFilter, options : SelectOption[])
			{
				const filteredArray = arrayToFilter.filter(item =>
				{
					// Check if item is found in options
					return !options.some(option =>
					{
						if(typeof option.value === 'string')
						{
							// Regular option
							return option.value === item;
						}
						else if(Array.isArray(option.value))
						{
							// Recursively check if item is found in nested array (option groups)
							return filterBySelectOptions([item], option.value).length > 0;
						}
						return false;
					});
				});

				return filteredArray;
			}

			// Empty is allowed, if there's an emptyLabel
			if(value.toString() == "" && this.emptyLabel)
			{
				return value;
			}

			const missing = filterBySelectOptions(value, this.select_options);
			if(missing.length > 0)
			{
				debugger;
				console.warn("Invalid option '" + missing.join(", ") + "' removed from " + this.id, this);
				value = value.filter(item => missing.indexOf(item) == -1);
			}
		}
		return value;
	}

	/**
	 * Additional customisations from the XET node
	 *
	 * @param {Element} _node
	 */
	loadFromXML(_node : Element)
	{
		super.loadFromXML(_node);

		// Wait for update to be complete before we check for bad value so extending selects can have a chance
		this.updateComplete.then(() => this.fix_bad_value());
	}

	/** @param {import('@lion/core').PropertyValues } changedProperties */
	willUpdate(changedProperties : PropertyValues)
	{
		super.willUpdate(changedProperties);

		if(changedProperties.has("multiple"))
		{
			this.value = this.__value;
		}
		if(changedProperties.has("select_options") || changedProperties.has("value") || changedProperties.has("emptyLabel"))
		{
			this.updateComplete.then(() =>
			{
				this.fix_bad_value();
			});
		}
	}

	/**
	 * After render, DOM nodes are there
	 *
	 * Check to see if tags overflow, set the counter flag
	 *
	 * @param {PropertyValues} changedProperties
	 */
	updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);

		this.checkTagOverflow();
	}

	protected checkTagOverflow()
	{
		// Create / destroy intersection observer
		if(this.readonly && this.rows == "1" && this.multiple && this.tagOverflowObserver == null)
		{
			this.tagOverflowObserver = new IntersectionObserver(this._handleTagOverflow, {
				root: this.select.shadowRoot.querySelector(".select__tags"),
				threshold: 0.1
			});
		}
		else if((!this.readonly || this.rows !== "1" || !this.multiple) && this.tagOverflowObserver !== null)
		{
			this.tagOverflowObserver.disconnect();
			this.tagOverflowObserver = null;
		}

		if(this.tagOverflowObserver)
		{
			this.select.updateComplete.then(() =>
			{
				// @ts-ignore
				for(const tag of this.select.shadowRoot.querySelectorAll(".select__tags *:not(div):not(sl-tag)"))
				{
					this.tagOverflowObserver.observe(tag);
				}
			});
		}
	}

	/**
	 * Tag used for rendering tags when multiple=true
	 * Used for creating, finding & filtering options.
	 * @see createTagNode()
	 * @returns {string}
	 */
	public get tagTag() : StaticValue
	{
		return literal`et2-tag`;
	}

	blur()
	{
		if(typeof super.blur == "function")
		{
			super.blur();
		}
		this.hide();
	}

	/**
	 * Apply the user preference to close the dropdown if an option is clicked, even if multiple=true.
	 * The default (from SlSelect) leaves the dropdown open for multiple=true
	 *
	 * @param {MouseEvent} event
	 * @private
	 */
	private handleOptionClick(event : MouseEvent)
	{
		super.handleOptionClick(event);

		// Only interested in option clicks, but handler is bound higher
		if(event.target.tagName !== "SL-OPTION")
		{
			return;
		}

		if(this._close_on_select)
		{
			this.hide();
		}
	}

	private et2HandleBlur(event : Event)
	{
		if(typeof super.et2HandleBlur === "function")
		{
			super.et2HandleBlur(event);
		}
	}


	protected handleValueChange(e : SlChangeEvent)
	{
		// Only interested when selected value changes, not any nested inputs
		if(e.target !== this.select)
		{
			return;
		}

		const old_value = this.__value;
		this.__value = Array.isArray(this.select.value) ?
					   this.select.value.map(e => e.replaceAll("___", " ")) :
					   this.select.value.replaceAll("___", " ");
		this.requestUpdate("value", old_value);
	}

	/**
	 * Always close the dropdown if an option is clicked, even if multiple=true.  This differs from SlSelect,
	 * which leaves the dropdown open for multiple=true
	 *
	 * @param {KeyboardEvent} event
	 * @private
	 */
	private handleKeyDown(event : KeyboardEvent)
	{
		if(event.key === 'Enter' || (event.key === ' ' && this.typeToSelectString === ''))
		{
			this.dropdown.hide().then(() =>
			{
				if(typeof this.handleMenuHide == "function")
				{
					// Make sure search gets hidden
					this.handleMenuHide();
				}
			});
			event.stopPropagation();
		}

	}

	protected handleTagClick(event : MouseEvent)
	{
		if(typeof this.onTagClick == "function")
		{
			event.stopPropagation();
			return this.onTagClick(event, event.target);
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
	protected _handleTagOverflow(entries : IntersectionObserverEntry[])
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
	 * If rows=1 and multiple=true, when they put the mouse over the widget show all tags
	 * @param {MouseEvent} e
	 * @private
	 */
	protected _handleMouseEnter(e : MouseEvent)
	{
		if(this.readonly && this.rows == "1" && this.multiple == true && this.value.length > 1)
		{
			e.stopPropagation();

			let distance = (-1 * parseInt(getComputedStyle(this).height)) + 2;

			// Bind to turn this all off
			this.addEventListener("mouseleave", this._handleMouseLeave);

			// Popup - this might get wiped out next render(), might not
			this.updateComplete.then(() =>
			{
				let tags = this.select.shadowRoot.querySelector(".select__tags");
				let popup = document.createElement("sl-popup");
				popup.anchor = this;
				popup.distance = distance;
				popup.placement = "bottom";
				popup.strategy = "fixed";
				popup.active = true;
				popup.sync = "width";
				popup.setAttribute("exportparts", "tags, popup");
				popup.classList.add("hover__popup", "details", "hoist", "details__body");
				this.shadowRoot.append(popup);
				popup.appendChild(tags.cloneNode(true));
				tags.style.width = getComputedStyle(this).width;
				tags.style.margin = 0;
			});
		}
	}

	/**
	 * If we're showing all rows because of _handleMouseEnter, reset when mouse leaves
	 * @param {MouseEvent} e
	 * @private
	 */
	protected _handleMouseLeave(e : MouseEvent)
	{
		let popup = this.shadowRoot.querySelector("sl-popup");
		if(popup)
		{
			// Popup still here.  Remove it
			popup.remove();
		}
		this.select.requestUpdate();
	}

	/** Shows the listbox. */
	async show()
	{
		return this.select.show();
	}

	/** Hides the listbox. */
	async hide()
	{
		this.select.hide();
	}

	get open()
	{
		return this.select?.open ?? false;
	}

	protected get select() : SlSelect
	{
		return this.shadowRoot?.querySelector("sl-select");
	}

	/**
	 * Custom, dynamic styling
	 *
	 * Put as much as you can in static styles for performance reasons
	 * Override this for custom dynamic styles
	 *
	 * @returns {TemplateResult}
	 * @protected
	 */
	protected _styleTemplate() : TemplateResult
	{
		return null;
	}

	/**
	 * Used for the "no value" option for single select
	 * Placeholder is used for multi-select with no value
	 *
	 * @returns {TemplateResult}
	 */
	_emptyLabelTemplate() : TemplateResult
	{
		if(!this.emptyLabel || this.multiple)
		{
			return html``;
		}
		return html`
            <sl-option
                    part="emptyLabel option"
                    value=""
                    .selected=${this.getValueAsArray().some(v => v == "")}
            >
                ${this.emptyLabel}
            </sl-option>`;
	}

	/**
	 * Iterate over all the options
	 * @returns {TemplateResult}
	 * @protected
	 */
	protected _optionsTemplate() : TemplateResult
	{
		return html`${repeat(this.select_options
			// Filter out empty values if we have empty label to avoid duplicates
			.filter(o => this.emptyLabel ? o.value !== '' : o), (o : SelectOption) => o.value, this._groupTemplate.bind(this))
		}`;
	}

	/**
	 * Used to render each option into the select
	 * Override for custom select options.  Note that spaces are not allowed in option values,
	 * and sl-select _requires_ options to be <sl-option>
	 *
	 * @param {SelectOption} option
	 * @returns {TemplateResult}
	 */
	protected _optionTemplate(option : SelectOption) : TemplateResult
	{
		// Exclude non-matches when searching
		// unless they're already selected, in which case removing them removes them from value
		if(typeof option.isMatch == "boolean" && !option.isMatch && !this.getValueAsArray().includes(option.value))
		{
			return html``;
		}

		// Tag used must match this.optionTag, but you can't use the variable directly.
		// Pass option along so SearchMixin can grab it if needed
		const value = (<string>option.value).replaceAll(" ", "___");
		const classes = option.class ? Object.fromEntries((option.class).split(" ").map(k => [k, true])) : {};
		return html`
            <sl-option
                    part="option"
                    exportparts="prefix:tag__prefix, suffix:tag__suffix"
                    value="${value}"
                    title="${!option.title || this.noLang ? option.title : this.egw().lang(option.title)}"
                    class=${classMap({
                        "match": this.searchEnabled && (option.isMatch || false),
                        "no-match": this.searchEnabled && option.isMatch == false,
                        ...classes
                    })}
                    .option=${option}
                    .selected=${this.getValueAsArray().some(v => v == value)}
                    ?disabled=${option.disabled}
            >
                ${this._iconTemplate(option)}
                ${this.noLang ? option.label : this.egw().lang(option.label)}
            </sl-option>`;
	}

	/**
	 * Get the icon for the select option
	 *
	 * @param option
	 * @protected
	 */
	protected _iconTemplate(option : SelectOption)
	{
		if(!option.icon)
		{
			return html``;
		}

		return html`
            <et2-image slot="prefix" part="icon" style="width: var(--icon-width)"
                       src="${option.icon}"></et2-image>`
	}


	/**
	 * Custom tag
	 *
	 * Override this to customise display when multiple=true.
	 * There is no restriction on the tag used, unlike _optionTemplate()
	 *
	 * @param {Et2Option} option
	 * @param {number} index
	 * @returns {TemplateResult}
	 * @protected
	 */
	protected _tagTemplate(option : SlOption, index : number) : TemplateResult
	{
		const readonly = (this.readonly || option && typeof (option.disabled) != "undefined" && option.disabled);
		const isEditable = this.editModeEnabled && !readonly;
		const image = this._iconTemplate(option.option ?? option);
		const tagName = this.tagTag;
		return html`
            <${tagName}
                    part="tag"
                    exportparts="
                      base:tag__base,
                      content:tag__content,
                      remove-button:tag__remove-button,
                      remove-button__base:tag__remove-button__base,
                      icon:icon
                    "
                    class=${"search_tag " + option.classList.value}
                    tabindex="-1"
                    ?pill=${this.pill}
                    size=${this.size || "medium"}
                    ?removable=${!readonly}
                    ?readonly=${readonly}
                    .editable=${isEditable}
                    .value=${option.value.replaceAll("___", " ")}
                    @change=${this.handleTagEdit}
                    @dblclick=${this._handleDoubleClick}
                    @mousedown=${typeof this.onTagClick == "function" ? (e) => this.handleTagClick(e) : nothing}
            >
                ${image ?? nothing}
                ${option.getTextLabel().trim()}
            </${tagName}>
		`;
	}

	protected _tagLimitTemplate() : TemplateResult | typeof nothing
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

	/**
	 * Additional customisation template
	 * Override if needed.  Added after select options.
	 *
	 * @protected
	 */
	protected _extraTemplate() : TemplateResult | typeof nothing
	{
		return typeof super._extraTemplate == "function" ? super._extraTemplate() : nothing;
	}

	/**
	 * Shoelace select uses space as multiple separator, so our values cannot have a space in them.
	 * We replace spaces with "___" before passing the value to SlSelect
	 *
	 * @protected
	 */
	protected get shoelaceValue() : string | string[]
	{
		return Array.isArray(this.value) ?
			   this.value.map(v => { return v.replaceAll(" ", "___"); }) :
			   (typeof this.value == "string" ? this.value.replaceAll(" ", "___") : "");
	}

	public render()
	{
		const value = this.shoelaceValue;

		let icon : TemplateResult | typeof nothing = nothing;
		if(!this.multiple)
		{
			const icon_option = this.select_options.find(o => (o.value == value || Array.isArray(value) && value.includes(o.value)) && o.icon);
			if(icon_option)
			{
				icon = this._iconTemplate(icon_option);
			}
		}
		return html`
            ${this._styleTemplate()}
            <sl-select
                    exportparts="prefix, tags, display-input, expand-icon, combobox, combobox:base, listbox, option"
                    label=${this.label}
                    placeholder=${this.placeholder || (this.multiple && this.emptyLabel ? this.emptyLabel : "")}
                    ?multiple=${this.multiple}
                    ?disabled=${this.disabled || this.readonly}
                    ?clearable=${this.clearable}
                    ?required=${this.required}
                    helpText=${this.helpText}
                    hoist
                    placement=${this.placement}
                    tabindex="0"
                    .getTag=${this._tagTemplate}
                    .maxOptionsVisible=${0}
                    .value=${value}
                    @sl-change=${this.handleValueChange}
                    @mouseenter=${this._handleMouseEnter}
                    @mouseup=${this.handleOptionClick}
                    @mousewheel=${
                            // Grab & stop mousewheel to prevent scrolling sidemenu when scrolling through options
                            e => e.stopImmediatePropagation()
                    }
                    size=${this.size || "medium"}
            >
                ${icon}
                ${this._emptyLabelTemplate()}
                ${this._optionsTemplate()}
                ${this._tagLimitTemplate()}
                ${this._extraTemplate()}
                <slot name="prefix" slot="prefix"></slot>
                <slot></slot>
                <div slot="help-text">
                    <slot name="feedback"></slot>
                </div>
            </sl-select>
		`;
	}
}

if(typeof customElements.get("et2-select") === "undefined")
{
	customElements.define("et2-select", Et2Select);
}

declare global
{
	interface HTMLElementTagNameMap
	{
		"et2-select" : Et2Select;
	}
}