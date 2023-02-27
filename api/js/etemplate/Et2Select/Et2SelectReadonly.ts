/**
 * EGroupware eTemplate2 - Readonly select WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css, html, LitElement, repeat, TemplateResult} from "@lion/core";
import {et2_IDetachedDOM} from "../et2_core_interfaces";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {StaticOptions} from "./StaticOptions";
import {find_select_options, SelectOption} from "./FindSelectOptions";
import {SelectAccountMixin} from "./SelectAccountMixin";

const so = new StaticOptions();

/**
 * This is a stripped-down read-only widget used in nextmatch
 * (and other read-only usages)
 */
export class Et2SelectReadonly extends Et2Widget(LitElement) implements et2_IDetachedDOM
{
	static get styles()
	{
		return [
			...super.styles,
			css`
ul {
    margin: 0px;
    padding: 0px;
    display: inline-block;
}

li {
    text-decoration: none;
    list-style-image: none;
    list-style-type: none;
}
			`
		];
	}

	static get properties()
	{
		return {
			...super.properties,
			value: String,
			select_options: {type: Array}
		}
	}

	private __select_options : SelectOption[];
	private __value : string[];

	constructor()
	{
		super();
		this.type = "";
		this.__select_options = <SelectOption[]>[];
		this.__value = [];
	}

	protected find_select_options(_attrs)
	{
		let sel_options = find_select_options(this, _attrs['select_options']);
		if(sel_options.length > 0)
		{
			this.select_options = sel_options;
		}
	}

	transformAttributes(_attrs)
	{
		/*
		TODO: Check with more / different nextmatch data to see if this becomes faster.
		Currently it's faster for the nextmatch to re-do transformAttributes() and find_select_options()
		 on every row than it is to use widget.clone()

		// If there's no parent, there's a good chance we're in a nextmatch row so skip the transform
		if(!this.getParent())
		{
			return;
		}
		 */

		super.transformAttributes(_attrs);

		this.find_select_options(_attrs)
	}

	/**
	 * @deprecated assign to value
	 * @param value
	 */
	set_value(value)
	{
		this.value = value;
	}

	/**
	 * @deprecated use value
	 * @param value
	 */
	get_value(value)
	{
		return this.value;
	}

	/**
	 * @deprecated use value
	 * @param value
	 */
	getValue(value)
	{
		return this.value;
	}

	set value(new_value : string | string[])
	{
		// Split anything that is still a CSV
		if(typeof new_value == "string" && new_value.indexOf(",") > 0)
		{
			new_value = new_value.split(",");
		}
		// Wrap any single value into an array for consistent rendering
		if(typeof new_value == "string" || typeof new_value == "number")
		{
			new_value = ["" + new_value];
		}

		let oldValue = this.__value;
		this.__value = new_value;

		this.requestUpdate("value", oldValue);
	}

	get value()
	{
		return this.__value;
	}

	/**
	 * Set the select options
	 *
	 * @param {SelectOption[]} new_options
	 */
	set select_options(new_options : SelectOption[] | { [key : string] : string })
	{
		if(!Array.isArray(new_options))
		{
			let fixed_options : SelectOption[] = [];
			for(let key in new_options)
			{
				fixed_options.push(<SelectOption>{value: key, label: new_options[key]});
			}
			console.warn(this.id + " passed a key => value map instead of array");
			this.select_options = fixed_options;
			return;
		}
		this.__select_options = new_options;
	}

	/**
	 * Set the select options
	 *
	 * @deprecated assign to select_options
	 * @param new_options
	 */
	set_select_options(new_options : SelectOption[] | { [key : string] : string })
	{
		this.select_options = new_options;
	}

	get select_options() : SelectOption[] | { [key : string] : string }
	{
		return this.__select_options;
	}

	render()
	{
		if(!this.value || Array.isArray(this.value) && !this.value.length)
		{
			return this._readonlyRender({label: this.emptyLabel || "", value: ""});
		}

		return html`
            <ul>
                ${repeat(this.value, (val : string) => val, (val) =>
                {
                    let option = (<SelectOption[]>this.select_options).find(option => option.value == val);
                    if(!option)
                    {
                        return "";
                    }
                    return this._readonlyRender(option);
                })}
            </ul>
		`;
	}

	_readonlyRender(option : SelectOption) : TemplateResult
	{
		return html`
            <li>${this.noLang ? option.label : this.egw().lang(option.label + "")}</li>
		`;
	}

	getDetachedAttributes(attrs)
	{
		attrs.push("id", "value", "class");
	}

	getDetachedNodes() : HTMLElement[]
	{
		return [<HTMLElement><unknown>this];
	}

	setDetachedAttributes(_nodes : HTMLElement[], _values : object, _data? : any) : void
	{
		for(let attr in _values)
		{
			this[attr] = _values[attr];
		}
	}

	loadFromXML()
	{
		// nope
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select_ro", Et2SelectReadonly);

export class Et2SelectAccountReadonly extends SelectAccountMixin(Et2SelectReadonly)
{


}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-account_ro", Et2SelectAccountReadonly);

export class Et2SelectAppReadonly extends Et2SelectReadonly
{
	constructor()
	{
		super();

		this.select_options = so.app(this, {other: this.other || []});
	}

	protected find_select_options(_attrs) {}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-app_ro", Et2SelectAppReadonly);

export class Et2SelectBitwiseReadonly extends Et2SelectReadonly
{
	render()
	{
		let new_value = [];
		for(let index in this.select_options)
		{
			let option = this.select_options[index];
			let right = parseInt(option && option.value ? option.value : index);
			if(!!(this.value & right))
			{
				new_value.push(right);
			}
		}
		return html`
            <ul>
                ${repeat(new_value, (val : string) => val, (val) =>
                {
                    let option = (<SelectOption[]>this.select_options).find(option => option.value == val);
                    if(!option)
                    {
                        return "";
                    }
                    return this._readonlyRender(option);
                })}
            </ul>`;
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-bitwise_ro", Et2SelectBitwiseReadonly);

export class Et2SelectBoolReadonly extends Et2SelectReadonly
{
	constructor()
	{
		super();
		this.select_options = so.bool(this);
	}

	protected find_select_options(_attrs) {}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-bool_ro", Et2SelectBoolReadonly);

export class Et2SelectCategoryReadonly extends Et2SelectReadonly
{
	constructor()
	{
		super();

		so.cat(this).then(_options =>
		{
			this.select_options = _options;

			// on first load we have the value before the options arrive --> need to request an update
			if (this.value && (!Array.isArray(this.value) || this.value.length))
			{
				this.requestUpdate('value');
			}
		});
	}

	protected find_select_options(_attrs) {}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-cat_ro", Et2SelectCategoryReadonly);

export class Et2SelectPercentReadonly extends Et2SelectReadonly
{
	constructor()
	{
		super();
		this.select_options = so.percent(this, {});
	}

	protected find_select_options(_attrs) {}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-percent_ro", Et2SelectPercentReadonly);

export class Et2SelectCountryReadonly extends Et2SelectReadonly
{
	constructor()
	{
		super();
		this.select_options = so.country(this, {});
	}

	protected find_select_options(_attrs) {}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-country_ro", Et2SelectCountryReadonly);

export class Et2SelectDayReadonly extends Et2SelectReadonly
{
	constructor()
	{
		super();

		this.select_options = so.day(this, {other: this.other || []});
	}

	protected find_select_options(_attrs) {}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-day_ro", Et2SelectDayReadonly);

export class Et2SelectDayOfWeekReadonly extends Et2SelectReadonly
{
	constructor()
	{
		super();

		this.select_options = so.dow(this, {other: this.other || []});
	}

	protected find_select_options(_attrs) {}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-dow_ro", Et2SelectDayOfWeekReadonly);

export class Et2SelectHourReadonly extends Et2SelectReadonly
{
	constructor()
	{
		super();

		this.select_options = so.hour(this, {other: this.other || []});
	}

	protected find_select_options(_attrs) {}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-hour_ro", Et2SelectHourReadonly);

export class Et2SelectMonthReadonly extends Et2SelectReadonly
{
	constructor()
	{
		super();

		this.select_options = so.month(this);
	}

	protected find_select_options(_attrs) {}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-month_ro", Et2SelectMonthReadonly);

export class Et2SelectNumberReadonly extends Et2SelectReadonly
{
	constructor()
	{
		super();

		this.select_options = so.number(this, {other: this.other || []});
	}

	protected find_select_options(_attrs) {}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-number_ro", Et2SelectNumberReadonly);

export class Et2SelectPriorityReadonly extends Et2SelectReadonly
{
	constructor()
	{
		super();
		this.select_options = so.priority(this);
	}

	protected find_select_options(_attrs) {}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-priority_ro", Et2SelectPriorityReadonly);

export class Et2SelectStateReadonly extends Et2SelectReadonly
{
	constructor()
	{
		super();

		this.select_options = so.state(this, {other: this.other || []});
	}

	protected find_select_options(_attrs) {}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-state_ro", Et2SelectStateReadonly);

export class Et2SelectTimezoneReadonly extends Et2SelectReadonly
{
	constructor()
	{
		super();

		this.select_options = so.timezone(this, {other: this.other || []});
	}

	protected find_select_options(_attrs) {}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-timezone_ro", Et2SelectTimezoneReadonly);

export class Et2SelectYearReadonly extends Et2SelectReadonly
{
	constructor()
	{
		super();

		this.select_options = so.year(this, {other: this.other || []});
	}

	protected find_select_options(_attrs) {}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-year_ro", Et2SelectYearReadonly);

export class Et2SelectLangReadonly extends Et2SelectReadonly
{
	constructor()
	{
		super();

		this.select_options = so.lang(this, {other: this.other || []});
	}

	protected find_select_options(_attrs) {}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-lang_ro", Et2SelectLangReadonly);