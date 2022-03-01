/**
 * EGroupware eTemplate2 - Iframe widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */


import {css, html, LitElement, SlotMixin} from "@lion/core";
import {Et2Widget} from "../Et2Widget/Et2Widget";

export class Et2Iframe extends Et2Widget(SlotMixin(LitElement))
{

	static get styles()
	{
		return [
			...super.styles,
			css`
			:host {
				display: flex;
			}
			:host > iframe {
				width: 100%;
				height: 100%;
			}
			/* Custom CSS */
			`,
		];
	}

	static get properties()
	{
		return {
			...super.properties,
			label: {type: String},
			seamless: {type: Boolean},
			name: {type: String},
			fullscreen: {type: Boolean},
			needed: {type: Boolean},
			src: {type:String},
			allow: {type: String}
		}
	}

	constructor(...args : any[])
	{
		super(...args);
	}
	get slots()
	{
		return {
			...super.slots
		};
	}
	connectedCallback()
	{
		super.connectedCallback();
	}

	render() {
		return html`
            <iframe ${this.id ? html`id="${this.id}"` : ''} allowfullscreen="${this.fullscreen}"
                    seamless="${this.seamless}" name="${this.name}" allow="${this.allow}"></iframe>
            <slot>${this.label}</slot>
		`;
	}

	__getIframeNode()
	{
		return this.shadowRoot.querySelector('iframe');
	}

	/**
	 * Set the URL for the iframe
	 *
	 * Sets the src attribute to the given value
	 *
	 * @param _value String URL
	 */
	set_src(_value)
	{
		if(_value.trim() != "")
		{
			if(_value.trim() == 'about:blank')
			{
				this.__getIframeNode().src = _value;
			}
			else
			{
				// Load the new page, but display a loader
				let loader = jQuery('<div class="et2_iframe loading"/>');
				this.__getIframeNode().before(loader);
				window.setTimeout(function() {
					this.__getIframeNode().src = _value;
					this.__getIframeNode().addEventListener('load',function() {
						loader.remove();
					});
				}.bind(this),0);

			}
		}
	}

	/**
	 * Set name of iframe (to be used as target for links)
	 *
	 * @param _name
	 */
	set_name(_name)
	{
		this.options.name = _name;
		this.__getIframeNode().attribute('name', _name);
	}

	set_allow (_allow)
	{
		this.options.allow = _allow;
		this.__getIframeNode().attribute('allow', _allow);
	}
	/**
	 * Make it look like part of the containing document
	 *
	 * @param _seamless boolean
	 */
	set_seamless(_seamless)
	{
		this.options.seamless = _seamless;
		this.__getIframeNode().attribute("seamless", _seamless);
	}

	set_value(_value)
	{
		if(typeof _value == "undefined") _value = "";

		if(_value.trim().indexOf("http") == 0 || _value.indexOf('about:') == 0 || _value[0] == '/')
		{
			// Value is a URL
			this.set_src(_value);
		}
		else
		{
			// Value is content
			this.set_srcdoc(_value);
		}
	}

	/**
	 * Sets the content of the iframe
	 *
	 * Sets the srcdoc attribute to the given value
	 *
	 * @param _value String Content of a document
	 */
	set_srcdoc(_value)
	{
		this.__getIframeNode().attribute("srcdoc", _value);
	}
}

// @ts-ignore TypeScript is not recognizing that Et2Iframe is a LitElement
customElements.define("et2-iframe", Et2Iframe);
