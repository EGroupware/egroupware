/**
 * EGroupware eTemplate2 - Readonly select WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {html, LitElement, TemplateResult} from "@lion/core";
import {et2_IDetachedDOM} from "../et2_core_interfaces";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {find_select_options, SelectOption} from "./Et2Select";
import {StaticOptions} from "./StaticOptions";

const so = new StaticOptions();

/**
 * This is a stripped-down read-only widget used in nextmatch
 * (and other read-only usages)
 */
export class Et2SelectReadonly extends Et2Widget(LitElement) implements et2_IDetachedDOM
{
	protected _options : SelectOption[] = [];

	static get styles()
	{
		return [
			...super.styles
		];
	}

	static get properties()
	{
		return {
			...super.properties,
			value: String,
			select_options: Object
		}
	}

	constructor()
	{
		super();
		this.type = "";
	}

	protected find_select_options(_attrs)
	{
		let sel_options = find_select_options(this, _attrs['select_options'], _attrs);
		if(sel_options.length > 0)
		{
			this.set_select_options(sel_options);
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

	set_value(value)
	{
		this.value = value;
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
			let fixed_options : SelectOption[] = [];
			for(let key in new_options)
			{
				fixed_options.push(<SelectOption>{value: key, label: new_options[key]});
			}
			console.warn(this.id + " passed a key => value map instead of array");
			this._options = fixed_options;
			return;
		}
		this._options = new_options;
	}

	render()
	{
		let label = "";
		if(this.value)
		{
			let current = this._options.find(option => option.value == this.value);
			label = current ? current.label : "";
		}
		else if(this.empty_text)
		{
			label = this.empty_text;
		}

		return this._readonlyRender(label)
	}

	_readonlyRender(label) : TemplateResult
	{
		return html`
            <span>${label}</span>
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
		// Do nothing, since we can't actually stop being a DOM node...
	}

	loadFromXML()
	{
		// nope
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select_ro", Et2SelectReadonly);

export class Et2SelectAppReadonly extends Et2SelectReadonly
{
	constructor()
	{
		super();

		this._options = so.app(this, {other: this.other || []});
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
		for(var index in this.options.select_options)
		{
			var option = this.options.select_options[index];
			var right = option && option.value ? option.value : index;
			if(!!(this.value & right))
			{
				new_value.push(right);
			}
		}
		return this._readonlyRender(new_value);
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-bitwise_ro", Et2SelectBitwiseReadonly);

export class Et2SelectBoolReadonly extends Et2SelectReadonly
{
	constructor()
	{
		super();
		this._options = so.bool(this);
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

		this._options = so.cat(this, {other: this.other || []});
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
		this._options = so.percent(this, {});
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
		this._options = so.country(this, {});
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

		this._options = so.day(this, {other: this.other || []});
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

		this._options = so.dow(this, {other: this.other || []});
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

		this._options = so.hour(this, {other: this.other || []});
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

		this._options = so.month(this);
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

		this._options = so.number(this, {other: this.other || []});
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
		this._options = so.priority(this);
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

		this._options = so.state(this, {other: this.other || []});
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

		this._options = so.timezone(this, {other: this.other || []});
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

		this._options = so.year(this, {other: this.other || []});
	}

	protected find_select_options(_attrs) {}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-year_ro", Et2SelectYearReadonly);
