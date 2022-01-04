/**
 * EGroupware eTemplate2 - Readonly select WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {html, LitElement} from "@lion/core";
import {et2_IDetachedDOM} from "../et2_core_interfaces";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {find_select_options, SelectOption} from "./Et2Select";

/**
 * This is a stripped-down read-only widget used in nextmatch
 * (and other read-only usages)
 */
export class Et2SelectReadonly extends Et2Widget(LitElement) implements et2_IDetachedDOM
{
	protected value : any;
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
	}

	transformAttributes(_attrs)
	{
		super.transformAttributes(_attrs);

		let sel_options = find_select_options(this, _attrs['select_options'], _attrs);
		if(sel_options.length > 0)
		{
			this.set_select_options(sel_options);
		}
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
		let current = this._options.find(option => option.value == this.value);
		let label = current ? current.label : "";

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