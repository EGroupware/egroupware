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
import {Et2Tag} from "./Tag/Et2Tag";
import {Et2WithSearchMixin} from "./SearchMixin";
import {property} from "lit/decorators/property.js";
import {SlChangeEvent, SlOption, SlSelect} from "@shoelace-style/shoelace";
import {repeat} from "lit/directives/repeat.js";

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
 * Select widget
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
 */
// @ts-ignore SlSelect styles is a single CSSResult, not an array, so TS complains
export class Et2Select extends Et2WithSearchMixin(Et2WidgetWithSelect)
{
	private _block_change_event : boolean = false;
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
			  .select__tags {
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

			  .select__tags {
				margin-left: 0px;
				max-height: initial;
				overflow-y: auto;
				gap: 0.1rem 0.5rem;
			  }

			  .select--medium .select__tags {
				padding-top: 2px;
				padding-bottom: 2px;
			  }

			  :host([rows]) .select__control > .select__label > .select__tags {
				max-height: calc(var(--rows, 5) * 29px);
			  }

			  :host([rows='1']) .select__tags {
				overflow: hidden;
			  }

			  /* Keep overflow tag right-aligned.  It's the only sl-tag. */

			  .select__tags sl-tag {
				margin-left: auto;
			  }

			  select:hover {
				box-shadow: 1px 1px 1px rgb(0 0 0 / 60%);
			  }

			  /* Hide dropdown trigger when multiple & readonly */

			  :host([readonly][multiple])::part(expand-icon) {
				display: none;
			  }

			  /* Style for tag count if rows=1 */

			  :host([readonly][multiple][rows])::part(tags) {
				position: absolute;
				right: 0px;
				top: 1px;
				box-shadow: rgb(0 0 0/50%) -1.5ex 0px 1ex -1ex, rgb(0 0 0 / 0%) 0px 0px 0px 0px;
			  }

			  :host([readonly][multiple][rows]) .select__tags sl-tag::part(base) {
				background-color: var(--sl-input-background-color);
				border-top-left-radius: 0;
				border-bottom-left-radius: 0;
				font-weight: bold;
				min-width: 3em;
				justify-content: center;
			  }

			  /* Show all rows on hover if rows=1 */

			  :host([readonly][multiple][rows]):hover .select__tags {
				width: -webkit-fill-available;
				width: -moz-fill-available;
				width: fill-available;
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
				max-height: 15em;
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

	/** The select's required attribute. */
	@property({type: Boolean, reflect: true}) required = false;


	private __value : string | string[] = "";

	constructor()
	{
		super();
		this.hoist = true;

		this._tagTemplate = this._tagTemplate.bind(this);
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
			this.select?.popup.handleAnchorChange();
		});
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();

		this.removeEventListener("sl-change", this._triggerChange);
	}

	_triggerChange(e)
	{
		if(super._triggerChange(e) && !this._block_change_event)
		{
			this.dispatchEvent(new Event("change", {bubbles: true}));
		}
		if(this._block_change_event)
		{
			this.updateComplete.then(() => this._block_change_event = false);
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
		if(this.value === "" && this.emptyLabel)
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
			this._block_change_event = (oldValue != this.value);
			// ""+ to cast value of 0 to "0", to not replace with ""
			this.requestUpdate("value", oldValue);
		}
	}

	@property()
	get value()
	{
		// Handle a bunch of non-values, if it's multiple we want an array
		if(this.multiple && (this.__value == "null" || this.__value == null || typeof this.__value == "undefined" || !this.emptyLabel && this.__value == ""))
		{
			return [];
		}
		if(!this.multiple && !this.emptyLabel && this.__value == "")
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
		if(this.select)
		{
			this.select.value = this.__value;
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
			this.updateComplete.then(() => this.fix_bad_value());
		}
		if(changedProperties.has("select_options") && changedProperties.has("value"))
		{
		}
	}

	/**
	 * Override this method from SlSelect to stick our own tags in there
	 *
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
		if(this.maxOptionsVisible > 0 && this.displayTags.length > this.maxOptionsVisible)
		{
			overflow = this.displayTags.pop();
		}

		const checkedItems = Object.values(this._menuItems).filter(item => this.value.includes(item.value));
		this.displayTags = checkedItems.map(item => this._createTagNode(item));

		if(checkedItems.length !== this.value.length && this.multiple)
		{
			// There's a value that does not have a menu item, probably invalid.
			// Add it as a marked tag so it can be corrected or removed.
			const filteredValues = this.value.filter(str => !checkedItems.some(obj => obj.value === str));
			for(let i = 0; i < filteredValues.length; i++)
			{
				const badTag = this._createTagNode({
					value: filteredValues[i],
					getTextLabel: () => filteredValues[i],
					classList: {value: ""}
				});
				badTag.variant = "danger";
				badTag.contactPlus = false;
				// Put it in front so it shows
				this.displayTags.unshift(badTag);
			}
		}

		// Re-slice & add overflow tag
		if(overflow)
		{
			this.displayTags = this.displayTags.slice(0, this.maxOptionsVisible);
			this.displayTags.push(overflow);
		}
		else if(this.multiple && this.rows == 1 && this.readonly && this.value.length > 1)
		{
			// Maybe more tags than we can show, show the count
			this.displayTags.push(html`
                <sl-tag class="multiple_tag" size=${this.size}>${this.value.length}</sl-tag> `);
		}
	}
	 */

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


	/**
	 * Customise how tags are rendered.  This overrides what SlSelect
	 * does in syncItemsFromValue().
	 * This is a copy+paste from SlSelect.syncItemsFromValue().
	 *
	 * @param item
	 * @protected
	 */
	protected _createTagNode(item)
	{
		console.warn("Deprecated");
		debugger;
		let tag;
		if(typeof super._createTagNode == "function")
		{
			tag = super._createTagNode(item);
		}
		else
		{
			tag = <Et2Tag>document.createElement(this.tagTag);
		}
		tag.value = item.value;
		tag.textContent = item?.getTextLabel()?.trim();
		tag.class = item.classList.value + " search_tag";
		tag.setAttribute("exportparts", "icon");
		if(this.size)
		{
			tag.size = this.size;
		}
		if(this.readonly || item.option && typeof (item.option.disabled) != "undefined" && item.option.disabled)
		{
			tag.removable = false;
			tag.readonly = true;
		}
		else
		{
			tag.addEventListener("dblclick", this._handleDoubleClick);
			tag.addEventListener("click", this.handleTagInteraction);
			tag.addEventListener("keydown", this.handleTagInteraction);
			tag.addEventListener("sl-remove", (event : CustomEvent) => this.handleTagRemove(event, item));
		}
		// Allow click handler even if read only
		if(typeof this.onTagClick == "function")
		{
			tag.addEventListener("click", (e) => this.onTagClick(e, e.target));
		}
		let image = this._createImage(item);
		if(image)
		{
			tag.prepend(image);
		}
		return tag;
	}

	blur()
	{
		if(typeof super.blur == "function")
		{
			super.blur();
		}
		this.hide();
	}

	/* Parent should be fine now?
	private handleTagRemove(event : CustomEvent, option)
	{
		event.stopPropagation();

		if(!this.disabled)
		{
			option.selected = false;
			let index = this.value.indexOf(option.value);
			if(index > -1)
			{
				this.value.splice(index, 1);
			}
			this.dispatchEvent(new CustomEvent('sl-input'));
			this.dispatchEvent(new CustomEvent('sl-change'));
			this.validate();
		}
	}

	 */

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
		this.dropdown?.hide();
	}


	protected handleValueChange(e : SlChangeEvent)
	{
		const old_value = this.__value;
		this.__value = this.select.value;
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

	/**
	 * Get the icon for the select option
	 *
	 * @param option
	 * @protected
	 */
	protected _iconTemplate(option)
	{
		if(!option.icon)
		{
			return html``;
		}

		return html`
            <et2-image slot="prefix" part="icon" style="width: var(--icon-width)"
                       src="${option.icon}"></et2-image>`
	}

	protected _createImage(item)
	{
		let image = item?.querySelector ? item.querySelector("et2-image") || item.querySelector("[slot='prefix']") : null;
		if(image)
		{
			image = image.clone();
			image.slot = "prefix";
			image.class = "tag_image";
			return image;
		}
		return "";
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

	protected _renderOptions()
	{return Promise.resolve();}

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
                    part="emptyLabel"
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
			.filter(o => this.emptyLabel ? o.value !== '' : o), this._groupTemplate.bind(this))
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
		if(typeof option.isMatch == "boolean" && !option.isMatch)
		{
			return html``;
		}

		// Tag used must match this.optionTag, but you can't use the variable directly.
		// Pass option along so SearchMixin can grab it if needed
		const value = (<string>option.value).replaceAll(" ", "___");
		return html`
            <sl-option
                    part="option"
                    value="${value}"
                    title="${!option.title || this.noLang ? option.title : this.egw().lang(option.title)}"
                    class="${option.class}" .option=${option}
                    .selected=${this.getValueAsArray().some(v => v == value)}
                    ?disabled=${option.disabled}
            >
                ${this._iconTemplate(option)}
                ${this.noLang ? option.label : this.egw().lang(option.label)}
            </sl-option>`;
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
		const image = this._createImage(option);
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
                    ?pill=${this.pill}
                    size=${this.size || "medium"}
                    ?removable=${!readonly}
                    ?readonly=${readonly}
                    ?editable=${isEditable}
                    .value=${option.value.replaceAll("___", " ")}
                    @dblclick=${this._handleDoubleClick}
                    @click=${typeof this.onTagClick == "function" ? (e) => this.onTagClick(e, e.target) : nothing}
            >
                ${image ?? nothing}
                ${option.getTextLabel().trim()}
            </${tagName}>
		`;
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

	public render()
	{
		const value = Array.isArray(this.value) ?
					  this.value.map(v => { return v.replaceAll(" ", "___"); }) :
					  (typeof this.value == "string" ? this.value.replaceAll(" ", "___") : "");

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
                    exportparts="prefix, tags, display-input, expand-icon, combobox, listbox, option"
                    label=${this.label}
                    placeholder=${this.placeholder || (this.multiple && this.emptyLabel ? this.emptyLabel : "")}
                    ?multiple=${this.multiple}
                    ?disabled=${this.disabled || this.readonly}
                    ?clearable=${this.clearable}
                    ?required=${this.required}
                    helpText=${this.helpText}
                    hoist
                    placement=${this.placement}
                    .getTag=${this._tagTemplate}
                    .maxOptionsVisible=${0}
                    .value=${value}
                    @sl-change=${this.handleValueChange}
            >
                ${icon}
                ${this._emptyLabelTemplate()}
                ${this._optionsTemplate()}
                ${this._extraTemplate()}
                <slot></slot>
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