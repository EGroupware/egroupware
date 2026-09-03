/**
 * EGroupware eTemplate2 - Iframe widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */


import {css, html, LitElement} from "lit";
import {Et2Widget} from "../Et2Widget/Et2Widget";

export class Et2Iframe extends Et2Widget(LitElement)
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

	__getIframeNode() : HTMLIFrameElement
	{
		return this.shadowRoot?.querySelector('iframe') ?? null;
	}

	/**
	 * Run callback with the real <iframe> node, once it exists
	 *
	 * transformAttributes() (initial widget-tree construction from XML, eg. an initial
	 * value="..." or a readonly/disabled default) can call set_src()/set_value() etc. before
	 * this element is ever connected to the document - this.shadowRoot (and so the real
	 * <iframe> inside it) doesn't exist yet at that point. Run immediately if it already does,
	 * otherwise wait for the first render to commit.
	 */
	private __withIframeNode(callback : (node : HTMLIFrameElement) => void) : void
	{
		const node = this.__getIframeNode();
		if(node)
		{
			callback(node);
		}
		else
		{
			this.updateComplete.then(() =>
			{
				const node = this.__getIframeNode();
				if(node) callback(node);
			});
		}
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
			this.__withIframeNode((node) =>
			{
				// a leftover srcdoc attribute overrides src and suppresses the load event
				if(_value.trim() == 'about:blank')
				{
					node.removeAttribute('srcdoc');
					node.src = _value;
				}
				else
				{
					// Load the new page, but display a loader
					let loader = document.createElement('div');
					loader.className = 'et2_iframe loading';
					node.before(loader);
					window.setTimeout(function() {
						node.removeAttribute('srcdoc');
						node.src = _value;
						node.addEventListener('load',function() {
							loader.remove();
						});
					},0);
				}
			});
		}
	}

	/**
	 * Set name of iframe (to be used as target for links)
	 *
	 * @param _name
	 */
	set_name(_name)
	{
		this.name = _name;
		this.__withIframeNode((node) => node.setAttribute('name', _name));
	}

	set_allow (_allow)
	{
		this.allow = _allow;
		this.__withIframeNode((node) => node.setAttribute('allow', _allow));
	}
	/**
	 * Make it look like part of the containing document
	 *
	 * @param _seamless boolean
	 */
	set_seamless(_seamless)
	{
		this.seamless = _seamless;
		this.__withIframeNode((node) => node.setAttribute("seamless", _seamless));
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
		this.__withIframeNode((node) => node.setAttribute("srcdoc", _value));
	}
}

// @ts-ignore TypeScript is not recognizing that Et2Iframe is a LitElement
customElements.define("et2-iframe", Et2Iframe);
