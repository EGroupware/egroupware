/**
 * EGroupware eTemplate2 - Readonly date WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {html, LitElement} from "@lion/core";
import {formatDate, parseDate} from "./Et2Date";
import {et2_IDetachedDOM} from "../et2_core_interfaces";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {dateStyles} from "./DateStyles";

/**
 * This is a stripped-down read-only widget used in nextmatch
 */
export class Et2DateReadonly extends Et2Widget(LitElement) implements et2_IDetachedDOM
{
	protected value : any;
	protected parser : Function;
	protected formatter : Function;

	static get styles()
	{
		return [
			...super.styles,
			dateStyles
		];
	}

	static get properties()
	{
		return {
			...super.properties,
			value: String,
		}
	}

	constructor()
	{
		super();
		this.parser = parseDate;
		this.formatter = formatDate;
	}

	set_value(value)
	{
		this.value = value;
	}

	render()
	{
		let parsed : Date | Boolean = this.value ? this.parser(this.value) : false

		return html`
            <span slot="label">${this.label}</span>
            <time ${this.id ? html`id="${this._dom_id}"` : ''}
                  datetime="${parsed ? this.formatter(<Date>parsed, {dateFormat: "Y-m-d", timeFormat: "H:i:s"}) : ""}">
                ${this.value ? this.formatter(<Date>parsed) : ''}
            </time>
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
customElements.define("et2-date_ro", Et2DateReadonly);