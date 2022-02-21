/**
 * EGroupware eTemplate2 - Description WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {LionSelect} from "@lion/select";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {et2_readAttrWithDefault} from "../et2_core_xml";
import {css, html, PropertyValues, render, repeat, TemplateResult} from "@lion/core";
import {cssImage} from "../Et2Widget/Et2Widget";
import {StaticOptions} from "./StaticOptions";

export interface SelectOption
{
	value : string;
	label : string;
	// Hover help text
	title? : string;
}

/**
 * Base class for things that do selectbox type behaviour, to avoid putting too much or copying into read-only
 * selectboxes, also for common handling of properties for more special selectboxes.
 *
 * LionSelect (and any other LionField) use slots to wrap a real DOM node.  ET2 doesn't expect this,
 * so we have to create the input node (via slots()) and respect that it is _external_ to the Web Component.
 * This complicates things like adding the options, since we can't just override _inputGroupInputTemplate()
 * and include them when rendering - the parent expects to find the <select> added via a slot, render() would
 * put it inside the shadowDOM.  That's fine, but then it doesn't get created until render(), and the parent
 * (LionField) can't find it when it looks for it before then.
 *
 */
export class Et2WidgetWithSelect extends Et2InputWidget(LionSelect)
{
	static get properties()
	{
		return {
			...super.properties,
			/**
			 * Textual label for first row, eg: 'All' or 'None'.  It's value will be ''
			 */
			empty_label: String,

			/**
			 * Select box options
			 *
			 * Will be found automatically based on ID and type, or can be set explicitly in the template using
			 * <option/> children, or using widget.set_select_options(SelectOption[])
			 */
			select_options: Object,
		}
	}

	constructor()
	{
		super();

		this.select_options = <StaticOptions[]>[];
	}

	/** @param {import('@lion/core').PropertyValues } changedProperties */
	updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);

		// If the ID changed (or was just set) find the select options
		if(changedProperties.has("id"))
		{
			this.set_select_options(find_select_options(this));
		}
	}

	set_value(val)
	{
		let oldValue = this.modalValue;
		this.modalValue = val
		this.requestUpdate("value", oldValue);
	}

	/**
	 * Set the select options
	 *
	 * @param {SelectOption[]} new_options
	 */
	set_select_options(new_options : SelectOption[] | { [key : string] : string })
	{
		if(!Array.isArray(new_options))
		{
			let fixed_options = [];
			for(let key in new_options)
			{
				fixed_options.push({value: key, label: new_options[key]});
			}
			this.select_options = fixed_options;
		}
		else
		{
			this.select_options = new_options;
		}
	}

	get_select_options()
	{
		return this.select_options;
	}

	/**
	 * Render the "empty label", used when the selectbox does not currently have a value
	 *
	 * @returns {}
	 */
	_emptyLabelTemplate() : TemplateResult
	{
		return html`${this.empty_label}`;
	}

	_optionTemplate(option : SelectOption) : TemplateResult
	{
		return html``;
	}

	/**
	 * Load extra stuff from the template node.  In particular, we're looking for any <option/> tags added.
	 *
	 * @param {Element} _node
	 */
	loadFromXML(_node : Element)
	{
		// Read the option-tags
		let options = _node.querySelectorAll("option");
		let new_options = [];
		for(let i = 0; i < options.length; i++)
		{
			new_options.push({
				value: et2_readAttrWithDefault(options[i], "value", options[i].textContent),
				// allow options to contain multiple translated sub-strings eg: {Firstname}.{Lastname}
				label: options[i].textContent.replace(/{([^}]+)}/g, function(str, p1)
				{
					return this.egw().lang(p1);
				}),
				title: et2_readAttrWithDefault(options[i], "title", "")
			});
		}

		if(this.id)
		{
			new_options = find_select_options(this, {}, new_options);
		}
		this.set_select_options(new_options);
	}
}

export class Et2Select extends Et2WidgetWithSelect
{
	static get styles()
	{
		return [
			...super.styles,
			css`
			:host {
				display: inline-block;
			}
			select {
				width: 100%
				color: var(--input-text-color, #26537c);
				border-radius: 3px;
				flex: 1 0 auto;
				padding-top: 4px;
				padding-bottom: 4px;
				padding-right: 20px;
				border-width: 1px;
				border-style: solid;
				border-color: #e6e6e6;
				-webkit-appearance: none;
				-moz-appearance: none;
				margin: 0;
				background: #fff no-repeat center right;
				background-image: ${cssImage('arrow_down')};
				background-size: 8px auto;
				background-position-x: calc(100% - 8px);
				text-indent: 5px;
			}

			select:hover {
				box-shadow: 1px 1px 1px rgb(0 0 0 / 60%);
			}`
		];
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

	connectedCallback()
	{
		super.connectedCallback();
		//MOVE options that were set as children inside SELECT:
		this.querySelector('select').append(...this.querySelectorAll('option'));
	}

	/** @param {import('@lion/core').PropertyValues } changedProperties */
	updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);
		if(changedProperties.has('select_options'))
		{
			// Add in actual options as children to select
			if(this._inputNode)
			{
				// We use this.get_select_options() instead of this.select_options so children can override
				// This is how sub-types get their options in
				render(html`${this._emptyLabelTemplate()}
                        ${repeat(this.get_select_options(), (option : SelectOption) => option.value, this._optionTemplate.bind(this))}`,
					this._inputNode
				);
			}
		}
		if(changedProperties.has('select_options') || changedProperties.has("value"))
		{
			// Re-set value, the option for it may have just shown up
			this._inputNode.value = this.modalValue || "";
		}
	}

	_emptyLabelTemplate() : TemplateResult
	{
		if(!this.empty_label)
		{
			return html``;
		}
		return html`
            <option value="" ?selected=${!this.modalValue}>${this.empty_label}</option>`;
	}

	_optionTemplate(option : SelectOption) : TemplateResult
	{
		return html`
            <option value="${option.value}" title="${option.title}" ?selected=${option.value == this.modalValue}>
                ${option.label}
            </option>`;
	}
}

/**
 * Use a single StaticOptions, since it should have no state
 * @type {StaticOptions}
 */
const so = new StaticOptions();

/**
 * Find the select options for a widget, out of the many places they could be.
 *
 * This will give valid, correct array of SelectOptions.  It will check:
 * - sel_options ArrayMgr, taking into account namespaces and checking the root
 * - content ArrayMgr, looking for "options-<id>"
 * - passed options, used by specific select types
 *
 * @param {Et2Widget} widget to check for.  Should be some sort of select widget.
 * @param {object} attr_options Select options in attributes array
 * @param {SelectOption[]} options Known options, passed in if you've already got some.  Cached type options, for example.
 * @return {SelectOption[]} Select options, or empty array
 */
export function find_select_options(widget, attr_options?, options : SelectOption[] = []) : SelectOption[]
{
	let name_parts = widget.id.replace(/&#x5B;/g, '[').replace(/]|&#x5D;/g, '').split('[');

	let content_options : SelectOption[] = [];

	// Try to find the options inside the "sel-options"
	if(widget.getArrayMgr("sel_options"))
	{
		// Try first according to ID
		let options = widget.getArrayMgr("sel_options").getEntry(widget.id);
		// ID can get set to an array with 0 => ' ' - not useful
		if(options && (options.length == 1 && typeof options[0] == 'string' && options[0].trim() == '' ||
			// eg. autorepeated id "cat[3]" would pick array element 3 from cat
			typeof options.value != 'undefined' && typeof options.label != 'undefined' && widget.id.match(/\[\d+]$/)))
		{
			content_options = null;
		}
		else
		{
			content_options = options;
		}
		// We could wind up too far inside options if label,title are defined
		if(options && !isNaN(name_parts[name_parts.length - 1]) && options.label && options.title)
		{
			name_parts.pop();
			content_options = widget.getArrayMgr("sel_options").getEntry(name_parts.join('['));
			delete content_options["$row"];
		}

		// Select options tend to be defined once, at the top level, so try that
		if(!content_options || content_options.length == 0)
		{
			content_options = widget.getArrayMgr("sel_options").getRoot().getEntry(name_parts[name_parts.length - 1]);
		}

		// Try in correct namespace (inside a grid or something)
		if(!content_options || content_options.length == 0)
		{
			content_options = widget.getArrayMgr("sel_options").getEntry(name_parts[name_parts.length - 1]);
		}

		// Try name like widget[$row]
		if(name_parts.length > 1 && (!content_options || content_options.length == 0))
		{
			let pop_that = JSON.parse(JSON.stringify(name_parts));
			while(pop_that.length > 1 && (!content_options || content_options.length == 0))
			{
				let last = pop_that.pop();
				content_options = widget.getArrayMgr('sel_options').getEntry(pop_that.join('['));

				// Double check, might have found a normal parent namespace ( eg subgrid in subgrid[selectbox] )
				// with an empty entry for the selectbox.  If there were valid options here,
				// we would have found them already, and keeping this would result in the ID as an option
				if(content_options && !Array.isArray(content_options) && typeof content_options[last] != 'undefined' && content_options[last])
				{
					content_options = content_options[last];
				}
				else if(content_options)
				{
					// Check for real values
					for(let key in content_options)
					{
						if(!(isNaN(<number><unknown>key) && typeof content_options[key] === 'string' ||
							!isNaN(<number><unknown>key) && typeof content_options[key] === 'object' && typeof content_options[key]['value'] !== 'undefined'))
						{
							// Found a parent of some other namespace
							content_options = undefined;
							break;
						}
					}
				}
			}
		}

		// Maybe in a row, and options got stuck in ${row} instead of top level
		// not sure this code is still needed, as server-side no longer creates ${row} or {$row} for select-options
		let row_stuck = ['${row}', '{$row}'];
		for(let i = 0; i < row_stuck.length && (!content_options || content_options.length == 0); i++)
		{
			// perspectiveData.row in nm, data["${row}"] in an auto-repeat grid
			if(widget.getArrayMgr("sel_options").perspectiveData.row || widget.getArrayMgr("sel_options").data[row_stuck[i]])
			{
				var row_id = widget.id.replace(/[0-9]+/, row_stuck[i]);
				content_options = widget.getArrayMgr("sel_options").getEntry(row_id);
				if(!content_options || content_options.length == 0)
				{
					content_options = widget.getArrayMgr("sel_options").getEntry(row_stuck[i] + '[' + widget.id + ']');
				}
			}
		}
		if(attr_options && Object.keys(attr_options).length > 0 && content_options)
		{
			//content_options = jQuery.extend(true, {}, attr_options, content_options);
			content_options = [...attr_options, ...content_options];
		}
	}

	// Check whether the options entry was found, if not read it from the
	// content array.
	if(content_options && content_options.length > 0 && widget.getArrayMgr('content') != null)
	{
		if(content_options)
		{
			attr_options = content_options;
		}
		let content_mgr = widget.getArrayMgr('content');
		if(content_mgr)
		{
			// If that didn't work, check according to ID
			if(!content_options)
			{
				content_options = content_mgr.getEntry("options-" + widget.id);
			}
			// Again, try last name part at top level
			if(!content_options)
			{
				content_options = content_mgr.getRoot().getEntry("options-" + name_parts[name_parts.length - 1]);
			}
		}
	}

	// Default to an empty object
	if(content_options == null)
	{
		content_options = [];
	}

	// Include passed options, preferring any content options
	if(options.length || Object.keys(options).length > 0)
	{
		for(let i in content_options)
		{
			let value = typeof content_options[i] == 'object' && typeof content_options[i].value !== 'undefined' ? content_options[i].value : i;
			let added = false;

			// Override any existing
			for(let j in options)
			{
				if('' + options[j].value === '' + value)
				{
					added = true;
					options[j] = content_options[i];
					break;
				}
			}
			if(!added)
			{
				options.splice(parseInt(i), 0, content_options[i]);
			}
		}
		content_options = options;
	}

	// Clean up
	if(!Array.isArray(content_options) && typeof content_options === "object" && Object.values(content_options).length > 0)
	{
		let fixed_options = [];
		for(let key in <object>content_options)
		{
			let option = {value: key, label: content_options[key]}
			// This could be an option group - not sure we have any
			if(typeof option.label !== "string")
			{
				// @ts-ignore Yes, option.label.label is not supposed to exist but that's what we're checking
				if(typeof option.label.label !== "undefined")
				{
					// @ts-ignore Yes, option.label.label is not supposed to exist but that's what we're checking
					option.label = option.label.label;
				}
			}
			fixed_options.push(option);
		}
		content_options = fixed_options;
	}
	return content_options;
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select", Et2Select);

export class Et2SelectApp extends Et2Select
{
	get_select_options() : SelectOption[]
	{
		return so.app(this, {other: this.other || []});
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
		let options = this.get_select_options();
		for(let index in options)
		{
			let right = parseInt(options[index].value);
			if(!!(new_value & right))
			{
				expanded_value.push(right);
			}
		}
		this.modalValue = expanded_value;

		this.requestUpdate("value", oldValue);
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-bitwise", Et2SelectBitwise);

export class Et2SelectBool extends Et2Select
{
	get_select_options() : SelectOption[]
	{
		return so.bool(this);
	}

}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-bool", Et2SelectBool);

export class Et2SelectCategory extends Et2Select
{
	get_select_options() : SelectOption[]
	{
		return so.cat(this, {other: this.other || []});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-cat", Et2SelectCategory);

export class Et2SelectPercent extends Et2Select
{
	get_select_options() : SelectOption[]
	{
		return so.percent(this, {});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-percent", Et2SelectPercent);

export class Et2SelectCountry extends Et2Select
{
	get_select_options() : SelectOption[]
	{
		return so.country(this, {});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-country", Et2SelectCountry);

export class Et2SelectDay extends Et2Select
{
	get_select_options() : SelectOption[]
	{
		return so.day(this, {other: this.other || []});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-day", Et2SelectDay);

export class Et2SelectDayOfWeek extends Et2Select
{
	get_select_options() : SelectOption[]
	{
		return so.dow(this, {other: this.other || []});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-dow", Et2SelectDayOfWeek);

export class Et2SelectHour extends Et2Select
{
	get_select_options() : SelectOption[]
	{
		return so.hour(this, {other: this.other || []});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-hour", Et2SelectHour);

export class Et2SelectMonth extends Et2Select
{
	get_select_options() : SelectOption[]
	{
		return so.month(this);
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-month", Et2SelectMonth);

export class Et2SelectNumber extends Et2Select
{
	get_select_options() : SelectOption[]
	{
		return so.number(this, {other: this.other || []});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-number", Et2SelectNumber);

export class Et2SelectPriority extends Et2Select
{
	get_select_options() : SelectOption[]
	{
		return so.priority(this);
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-priority", Et2SelectPriority);

export class Et2SelectState extends Et2Select
{
	get_select_options() : SelectOption[]
	{
		return so.state(this, {other: this.other || []});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-state", Et2SelectState);

export class Et2SelectTimezone extends Et2Select
{
	get_select_options() : SelectOption[]
	{
		return so.timezone(this, {other: this.other || []});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-timezone", Et2SelectTimezone);

export class Et2SelectYear extends Et2Select
{
	get_select_options() : SelectOption[]
	{
		return so.year(this, {other: this.other || []});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-year", Et2SelectYear);