/**
 * EGroupware eTemplate2 - Select WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css, html, PropertyValues, TemplateResult} from "lit";
import {Et2WidgetWithSelectMixin} from "./Et2WidgetWithSelectMixin";
import {SelectOption} from "./FindSelectOptions";
import shoelace from "../Styles/shoelace";
import {RowLimitedMixin} from "../Layout/RowLimitedMixin";
import {SlOption, SlSelect} from "@shoelace-style/shoelace";
import {Et2Tag} from "./Tag/Et2Tag";
import {Et2WithSearchMixin} from "./SearchMixin";

// export Et2WidgetWithSelect which is used as type in other modules
export class Et2WidgetWithSelect extends RowLimitedMixin(Et2WidgetWithSelectMixin(SlSelect))
{
	// Gets an array of all <sl-option> elements
	protected getAllOptions()
	{
		// @ts-ignore
		return [...this.querySelectorAll<SlOption>('sl-option')];
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

			  :host([readonly][multiple]) .select__expand-icon {
				display: none;
			  }

			  /* Style for tag count if rows=1 */

			  :host([readonly][multiple][rows]) .select__tags sl-tag {
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

	constructor()
	{
		super();
		this.hoist = true;
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

	// @ts-ignore
	get value()
	{
		return super.value;
	}

	// @ts-ignore
	set value(val : string | string[] | number | number[])
	{
		if(typeof val === 'string' && val.indexOf(',') !== -1 && this.multiple)
		{
			val = val.split(',');
		}
		if(typeof val === 'number')
		{
			val = val.toString();
		}
		if(Array.isArray(val))
		{
			// Make sure value has no duplicates, and values are strings
			val = <string[]>[...new Set(val.map(v => typeof v === 'number' ? v.toString() : v || ''))];
		}
		const oldValue = this.value;
		super.value = val || [];
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

			const missing = filterBySelectOptions(value, this.select_options);
			if(missing.length > 0)
			{
				console.warn("Invalid option '" + missing.join(", ") + " ' removed");
				value = value.filter(item => missing.indexOf(item) == -1);
			}
		}
		return value;
	}

	transformAttributes(attrs)
	{
		super.transformAttributes(attrs);

		// Deal with initial value of multiple set as CSV
		if(this.multiple && typeof this.value == "string")
		{
			this.value = this.value.length ? this.value.split(",") : [];
		}
	}

	/**
	 * Add an option for the "empty label" option, used if there's no value
	 *
	 * @returns {TemplateResult}
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

		if(changedProperties.has('select_options'))
		{
			this._renderOptions().then(async() =>
			{
				// If the options changed, update the display
				await this.updateComplete;
				this.selectionChanged();
			});
		}
		if(changedProperties.has('select_options') || changedProperties.has("value") || changedProperties.has('emptyLabel'))
		{
			this.updateComplete.then(() => this.fix_bad_value());
		}
		if(changedProperties.has("select_options") && changedProperties.has("value"))
		{
			// Re-set value, the option for it may have just shown up
			//this.updateComplete.then(() => this.syncItemsFromValue())
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
		if(this.maxTagsVisible > 0 && this.displayTags.length > this.maxTagsVisible)
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
			this.displayTags = this.displayTags.slice(0, this.maxTagsVisible);
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

	_emptyLabelTemplate() : TemplateResult
	{
		if(!this.emptyLabel || this.multiple)
		{
			return html``;
		}
		return html`
            <sl-option value="">${this.emptyLabel}</sl-option>`;
	}

	/**
	 * Used by Et2WidgetWithSelect to render each option into the select
	 *
	 * @param {SelectOption} option
	 * @returns {TemplateResult}
	 */
	_optionTemplate(option : SelectOption) : TemplateResult
	{
		// Tag used must match this.optionTag, but you can't use the variable directly.
		// Pass option along so SearchMixin can grab it if needed
		return html`
            <sl-option value="${option.value}"
                       title="${!option.title || this.noLang ? option.title : this.egw().lang(option.title)}"
                       class="${option.class}" .option=${option}
                       .selected=${this.getValueAsArray().some(v => v == option.value)}
                       ?disabled=${option.disabled}
            >
                ${this._iconTemplate(option)}
                ${this.noLang ? option.label : this.egw().lang(option.label)}
            </sl-option>`;
	}

	/**
	 * Tag used for rendering tags when multiple=true
	 * Used for creating, finding & filtering options.
	 * @see createTagNode()
	 * @returns {string}
	 */
	public get tagTag() : string
	{
		return "et2-tag";
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
		this.dropdown.hide();
	}

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
			this.syncItemsFromValue();
			this.validate();
		}
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

	/**
	 * Override from SlSelect to deal with value='' or [] and option has value=''.
	 * This fixes "empty" value not being shown
	 * @private
	 */
	private handleValueChange()
	{
		const allOptions = this.getAllOptions();
		const value = Array.isArray(this.value) ? this.value : [this.value];
		this.setSelectedOptions(allOptions.filter((el) => value.includes(el.value) || value.length == 0 && el.value === ""));
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
}

if(typeof customElements.get("et2-select") === "undefined")
{
	customElements.define("et2-select", Et2Select);
}