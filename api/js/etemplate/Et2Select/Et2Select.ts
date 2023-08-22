/**
 * EGroupware eTemplate2 - Select WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css, html, TemplateResult} from "lit";
import {Et2WidgetWithSelectMixin} from "./Et2WidgetWithSelectMixin";
import {SelectOption} from "./FindSelectOptions";
import shoelace from "../Styles/shoelace";
import {RowLimitedMixin} from "../Layout/RowLimitedMixin";
import {SlOption, SlSelect} from "@shoelace-style/shoelace";
import {Et2Tag} from "./Tag/Et2Tag";

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
export class Et2Select extends Et2WidgetWithSelect
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

			  /* Style for the list */

			  ::part(listbox) {
				min-width: fit-content;
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
		if((super._triggerChange && super._triggerChange(e) || typeof super._triggerChange === "undefined") && !this._block_change_event)
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
			val = [...new Set(val.map(v => typeof v === 'number' ? v.toString() : v || ''))];
		}
		const oldValue = this.value;
		super.value = val || '';
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
		tag.textContent = item.getTextLabel().trim();
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