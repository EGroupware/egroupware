/**
 * EGroupware eTemplate2 - Description WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

import {Et2Widget} from "../Et2Widget/Et2Widget";
import {html, css, LitElement} from "@lion/core";
import {et2_IDetachedDOM} from "../et2_core_interfaces";
import {activateLinks} from "../ActivateLinksDirective";

export class Et2Description extends Et2Widget(LitElement) implements et2_IDetachedDOM
{

	protected _value : string = "";

	static get styles()
	{
		return [
			...super.styles,
			css`
			:host {
				white-space: pre-wrap;
			}`
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

	get value()
	{
		return this._value;
	}

	set value(_value : string)
	{
		let oldValue = this.value;

		if(!_value)
		{
			_value = "";
		}

		// Do we do this here, or in transformAttributes()?
		if(!this.no_lang)
		{
			_value = this.egw().lang(_value);
		}

		if(_value && (_value + "").indexOf('%s') != -1)
		{
			_value = _value.replace(/%s/g, _value);
		}

		this._value = _value;
		this.requestUpdate('value', oldValue);
	}

	render()
	{

		// Add hover action button (Edit)
		if(this.hover_action)
		{
			// TODO
		}
		if(this.extra_link_popup || this.mime)
		{
			// TODO
		}
		

		// If there's a link, wrap that
		if(this.href && this._value)
		{
			return this.wrapLink(this.href, this._value);
		}
		// If we want to activate links inside, do that
		else if(this.activateLinks && this._value)
		{
			return this.getActivatedValue(this._value, this.href ? this.extra_link_target : '_blank');
		}
		// Just do the value
		else
		{
			return html`${this._value}`;
		}
	}

	protected wrapLink(href, value)
	{
		if(href.indexOf('/') == -1 && href.split('.').length >= 3 &&
			!(href.indexOf('mailto:') != -1 || href.indexOf('://') != -1 || href.indexOf('javascript:') != -1)
		)
		{
			href = "/index.php?menuaction=" + href;
		}
		if(href.charAt(0) == '/')             // link relative to eGW
		{
			href = egw.link(href);
		}
		return html`<a href="${href}" target="${this.target ?? "_blank"}">${value}</a>`;
	}

	protected getActivatedValue(value, target)
	{
		return html`${activateLinks(value, target)}`;
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

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-description", Et2Description);