/**
 * EGroupware eTemplate2 - Select WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css, html, PropertyValues, TemplateResult} from "@lion/core";
import {StaticOptions} from "./StaticOptions";
import {Et2widgetWithSelectMixin} from "./Et2WidgetWithSelectMixin";
import {SelectOption} from "./FindSelectOptions";
import {Et2InvokerMixin} from "../Et2Url/Et2InvokerMixin";
import {SlMenuItem, SlSelect} from "@shoelace-style/shoelace";
import {egw} from "../../jsapi/egw_global";
import shoelace from "../Styles/shoelace";
import {Et2WithSearchMixin} from "./SearchMixin";
import {Et2Tag} from "./Tag/Et2Tag";

// export Et2WidgetWithSelect which is used as type in other modules
export class Et2WidgetWithSelect extends Et2widgetWithSelectMixin(SlSelect)
{
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
export class Et2Select extends Et2WithSearchMixin(Et2InvokerMixin(Et2WidgetWithSelect))
{
	static get styles()
	{
		return [
			// Parent (SlSelect) returns a single cssResult, not an array
			super.styles,
			shoelace,
			css`
			:host {
				display: block;
				--icon-width: 20px;
			}
			
			
			::slotted(img), img {
				vertical-align: middle;
			}
			
			/* Get rid of padding before/after options */
			sl-menu::part(base) {
				padding: 0px;
			}
			
			/* Avoid double scrollbar if there are a lot of options */
			.select__menu
			{
				max-height: initial;
			}

			/** multiple=true uses tags for each value **/
			/* styling for icon inside tag (not option) */
			.tag_image {
				margin-right: var(--sl-spacing-x-small);
			}
			/* Maximum height + scrollbar on tags (+ other styling) */
			.select__tags {
				margin-left: 0px;
				max-height: 5em;
				overflow-y: auto;
				
				gap: 0.1rem 0.5rem;
			}
			/* Keep overflow tag right-aligned.  It's the only sl-tag. */
			 .select__tags sl-tag {
				margin-left: auto;
			}	
			select:hover {
				box-shadow: 1px 1px 1px rgb(0 0 0 / 60%);
			}`
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
			 * Add a button to switch to multiple with given number of rows
			 */
			expand_multiple_rows: {
				type: Number,
			}
		}
	}

	get slots()
	{
		return {
			...super.slots,
			input: () =>
			{
				return document.createElement("select");
			}
		}
	}

	constructor(...args : any[])
	{
		super();
		this._triggerChange = this._triggerChange.bind(this);
		this._doResize = this._doResize.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();

		this.fix_bad_value();

		this.updateComplete.then(() =>
		{
			this.addEventListener("sl-clear", this._triggerChange)
			this.addEventListener("sl-change", this._triggerChange);
			this.addEventListener("sl-after-show", this._doResize)
		});
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();

		this.removeEventListener("sl-clear", this._triggerChange)
		this.removeEventListener("sl-change", this._triggerChange);
		this.removeEventListener("sl-after-show", this._doResize);
	}

	firstUpdated(changedProperties?)
	{
		super.firstUpdated(changedProperties);
	}

	_triggerChange(e)
	{
		this.dispatchEvent(new Event("change"));
	}

	/**
	 * Change the menu sizing to allow the menu to be wider than the field width, but no smaller
	 *
	 * @param e
	 * @private
	 */
	private _doResize(e)
	{
		this.menu.style.minWidth = this.menu.style.width;
		this.menu.style.width = "";
	}

	/**
	 * Get the node where we're putting the selection options
	 *
	 * @returns {HTMLElement}
	 */
	get _optionTargetNode() : HTMLElement
	{
		return <HTMLElement><unknown>this;
	}

	/**
	 * Handle the case where there is no value set, or the value provided is not an option.
	 * If this happens, we choose the first option or empty label.
	 *
	 * Careful when this is called.  We change the value here, so an infinite loop is possible if the widget has
	 * onchange.
	 *
	 * @private
	 */
	private fix_bad_value()
	{
		// If no value is set, choose the first option
		// Only do this on firstUpdated() otherwise it is impossible to clear the field
		const valueArray = Array.isArray(this.value) ? this.value : (!this.value ? [] : this.value.toString().split(','));
		// value not in options --> use empty_label, if exists, or first option otherwise
		if(this.value !== "" && !this.multiple && Array.isArray(this.select_options) && this.select_options.length > 0 && this.select_options.filter((option) => valueArray.find(val => val == option.value)).length === 0)
		{
			this.value = this.empty_label ? "" : "" + this.select_options[0]?.value;
			// ""+ to cast value of 0 to "0", to not replace with ""
		}
	}

	/**
	 * @deprecated use this.multiple = multi
	 *
	 * @param multi
	 */
	set_multiple(multi)
	{
		this.multiple = multi;
	}

	set_value(val : string | string[] | number | number[])
	{
		if (typeof val === 'number')
		{
			val = val.toString();
		}
		if (Array.isArray(val))
		{
			val = val.map(v => typeof v === 'number' ? v.toString() : v || '');
		}
		this.value = val || '';
	}

	/**
	 * Reimplemented to allow/keep string[] as value
	 *
	 * @param value string|string[]
	 */
	_callParser(value = this.formattedValue)
	{
		if(this.multiple && Array.isArray(value))
		{
			return value;
		}
		return super._callParser(value);
	}

	private _set_invoker(rows)
	{
		this._invokerAction = () => {
			this.multiple = true;
			this._inputNode.size = parseInt(rows) || 4;
			this._invokerNode.style.display = 'none';
		}
		this._invokerTitle = egw.lang('Switch to multiple');
		this._invokerLabel = '+';
	}

	transformAttributes(attrs)
	{
		if (attrs.expand_multiple_rows)
		{
			this._set_invoker(attrs.expand_multiple_rows);
		}
		super.transformAttributes(attrs);

		// Deal with initial value of multiple set as CSV
		if(this.multiple && typeof this.value == "string")
		{
			this.value = this.value.length ? this.value.split(",") : [];
		}
	}

	set expand_multiple_rows(rows)
	{
		if (rows && !this.multiple)
		{
			this._set_invoker(rows);
		}
		else
		{
			this._invokerLabel = undefined;
		}
		this.__expand_multiple_rows = rows;
	}

	get expand_multiple_rows()
	{
		return this.__expand_multiple_rows;
	}

	/**
	 * Method to check if invoker can be activated: not disabled, empty or invalid
	 *
	 * Overwritten to NOT disable if empty.
	 *
	 * @protected
	 * */
	_toggleInvokerDisabled()
	{
		if (this._invokerNode)
		{
			const invokerNode = /** @type {HTMLElement & {disabled: boolean}} */ (this._invokerNode);
			invokerNode.disabled = this.disabled || this.hasFeedbackFor.length > 0;
		}
	}

	/** @param {import('@lion/core').PropertyValues } changedProperties */
	willUpdate(changedProperties : PropertyValues)
	{
		super.willUpdate(changedProperties);

		if(changedProperties.has('select_options') || changedProperties.has("value") || changedProperties.has('empty_label'))
		{
			this.fix_bad_value();

			// Re-set value, the option for it may have just shown up
			this.value = this.value || "";
		}

		// propagate multiple to selectbox
		if(changedProperties.has('multiple'))
		{
			// switch the expand button off
			if(this.multiple)
			{
				this.expand_multiple_rows = 0;
			}
		}
	}

	/**
	 * Override this method from SlSelect to stick our own tags in there
	 */
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
		const checkedItems = Object.values(this.menuItems).filter(item => this.value.includes(item.value));
		this.displayTags = checkedItems.map(item => this._createTagNode(item));

		// Re-slice & add overflow tag
		if(overflow)
		{
			this.displayTags = this.displayTags.slice(0, this.maxTagsVisible);
			this.displayTags.push(overflow);
		}
	}

	_emptyLabelTemplate() : TemplateResult
	{
		if(!this.empty_label || this.multiple)
		{
			return html``;
		}
		return html`
            <sl-menu-item value="">${this.empty_label}</sl-menu-item>`;
	}

	/**
	 * Tag used for rendering options
	 * Used for finding & filtering options, they're created by the mixed-in class
	 * @returns {string}
	 */
	public get optionTag()
	{
		return "sl-menu-item";
	}


	_optionTemplate(option : SelectOption) : TemplateResult
	{
		let icon = option.icon ? html`
            <et2-image slot="prefix" part="icon" style="width: var(--icon-width)"
                       src="${option.icon}"></et2-image>` : "";

		// Tag used must match this.optionTag, but you can't use the variable directly.
		// Pass option along so SearchMixin can grab it if needed
		return html`
            <sl-menu-item value="${option.value}" title="${option.title}" class="${option.class}" .option=${option}>
                ${icon}
                ${option.label}
            </sl-menu-item>`;
	}

	/**
	 * Tag used for rendering tags when multiple=true
	 * Used for creating, finding & filtering options.
	 * @see createTagNode()
	 * @returns {string}
	 */
	public get tagTag()
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
		const tag = <Et2Tag>document.createElement(this.tagTag);
		tag.value = item.value;
		tag.textContent = this.getItemLabel(item);
		tag.class = item.classList.value + " search_tag";
		if(this.size)
		{
			tag.size = this.size;
		}
		if(this.readonly)
		{
			tag.removable = false;
			tag.readonly = true;
		}
		else
		{
			tag.addEventListener("dblclick", this._handleDoubleClick);
			tag.addEventListener("click", this.handleTagInteraction);
			tag.addEventListener("keydown", this.handleTagInteraction);
			tag.addEventListener("sl-remove", (event) =>
			{
				event.stopPropagation();
				if(!this.disabled)
				{
					item.checked = false;
					this.syncValueFromItems();
				}
			});
		}
		let image = this._createImage(item);
		if(image)
		{
			tag.prepend(image);
		}
		return tag;
	}

	protected _createImage(item)
	{
		let image = item.querySelector("et2-image");
		if(image)
		{
			image = image.clone();
			image.slot = "prefix";
			image.class = "tag_image";
			return image;
		}
		return "";
	}

	public get menuItems() : HTMLElement[]
	{
		return [...this.querySelectorAll<SlMenuItem>(this.optionTag)];
	}
}

customElements.define("et2-select", Et2Select);
/**
 * Use a single StaticOptions, since it should have no state
 * @type {StaticOptions}
 */
const so = new StaticOptions();


export class Et2SelectApp extends Et2Select
{
	constructor()
	{
		super();

		this.select_options = so.app(this, {other: this.other || []});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-app", Et2SelectApp);

export class Et2SelectBitwise extends Et2Select
{
	set value(new_value)
	{
		let oldValue = this._value;
		let expanded_value = [];
		let options = this.select_options;
		for(let index in options)
		{
			let right = parseInt(options[index].value);
			if(!!(new_value & right))
			{
				expanded_value.push(right);
			}
		}
		super.value = expanded_value;

		this.requestUpdate("value", oldValue);
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-bitwise", Et2SelectBitwise);

export class Et2SelectBool extends Et2Select
{
	constructor()
	{
		super();

		this.select_options = so.bool(this);
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-bool", Et2SelectBool);


export class Et2SelectDay extends Et2Select
{
	constructor()
	{
		super();

		this.select_options = so.day(this, {other: this.other || []});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-day", Et2SelectDay);

export class Et2SelectDayOfWeek extends Et2Select
{
	constructor()
	{
		super();

		this.select_options = so.dow(this, {other: this.other || []});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-dow", Et2SelectDayOfWeek);

export class Et2SelectHour extends Et2Select
{
	constructor()
	{
		super();

		this.select_options = so.hour(this, {other: this.other || []});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-hour", Et2SelectHour);

export class Et2SelectMonth extends Et2Select
{
	constructor()
	{
		super();

		this.select_options = so.month(this);
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-month", Et2SelectMonth);

export class Et2SelectNumber extends Et2Select
{
	static get properties()
	{
		return {
			...super.properties,
			/**
			 * Step between numbers
			 */
			interval: {type: Number},
			min: {type: Number},
			max: {type: Number},

			/**
			 * Add one or more leading zeros
			 * Set to how many zeros you want (000)
			 */
			leading_zero: {type: String},
			/**
			 * Appended after every number
			 */
			suffix: {type: String}
		}
	}

	constructor()
	{
		super();
		this.min = 1;
		this.max = 10;
		this.interval = 1;
		this.leading_zero = "";
		this.suffix = "";
	}

	updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);

		if(changedProperties.has('min') || changedProperties.has('max') || changedProperties.has('interval') || changedProperties.has('suffix'))
		{
			this.select_options = so.number(this);
		}
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-number", Et2SelectNumber);

export class Et2SelectPercent extends Et2SelectNumber
{
	constructor()
	{
		super();
		this.min = 0;
		this.max = 100;
		this.interval = 10;
		this.suffix = "%%";
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-percent", Et2SelectPercent);

export class Et2SelectPriority extends Et2Select
{
	constructor()
	{
		super();

		this.select_options = so.priority(this);
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-priority", Et2SelectPriority);

export class Et2SelectState extends Et2Select
{
	/**
	 * Two-letter ISO country code
	 */
	protected __country_code;

	static get properties()
	{
		return {
			...super.properties,
			country_code: String,
		}
	}

	constructor()
	{
		super();

		this.country_code = 'DE';
	}

	get country_code()
	{
		return this.__country_code;
	}

	set country_code(code : string)
	{
		this.__country_code = code;
		this.select_options = so.state(this, {country_code: this.__country_code});
	}

	set_country_code(code)
	{
		this.country_code = code;
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-state", Et2SelectState);

export class Et2SelectTimezone extends Et2Select
{
	constructor()
	{
		super();

		this.select_options = so.timezone(this, {other: this.other || []});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-timezone", Et2SelectTimezone);

export class Et2SelectYear extends Et2SelectNumber
{
	constructor()
	{
		super();
		this.min = -3;
		this.max = 2;
	}

	updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);

		if(changedProperties.has('min') || changedProperties.has('max') || changedProperties.has('interval') || changedProperties.has('suffix'))
		{
			this.select_options = so.year(this);
		}
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-year", Et2SelectYear);