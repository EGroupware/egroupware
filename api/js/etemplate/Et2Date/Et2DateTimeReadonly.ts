import {css, html, LitElement} from "@lion/core";
import {formatDateTime, parseDateTime} from "./Et2Date";
import {et2_IDetachedDOM} from "../et2_core_interfaces";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {dateStyles} from "./DateStyles";

/**
 * This is a stripped-down read-only widget used in nextmatch
 */
export class Et2DateTimeReadonly extends Et2Widget(LitElement) implements et2_IDetachedDOM
{
	private value : any;

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

	set_value(value)
	{
		this.value = value;
	}

	render()
	{
		let parsed : Date | Boolean = this.value ? parseDateTime(this.value) : false

		return html`
            <time ${this.id ? html`id="${this._dom_id}"` : ''}
                  datetime="${parsed ? formatDateTime(<Date>parsed, {dateFormat: "Y-m-d", timeFormat: "H:i:s"}) : ""}">
                ${this.value ? formatDateTime(<Date>parsed) : ''}
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
		// Do nothing, since we can't actually stop being a DOM node...
	}

	loadFromXML()
	{
		// nope
	}

	loadingFinished()
	{
		// already done, I'm a wc with no children
	}
}

// @ts-ignore TypeScript is not recognizing that Et2Date is a LitElement
customElements.define("et2-datetime_ro", Et2DateTimeReadonly);