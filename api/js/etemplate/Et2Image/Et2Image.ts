/**
 * EGroupware eTemplate2 - Image widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css, html, LitElement} from "@lion/core";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {et2_IDetachedDOM} from "../et2_core_interfaces";

export class Et2Image extends Et2Widget(LitElement) implements et2_IDetachedDOM
{
	static get styles()
	{
		return [
			...super.styles,
			css`
            :host {
				display: inline-block;
            }
            img {
            	height: 100%;
            	width: 100%;
            }
            `,
		];
	}

	static get properties()
	{
		return {

			/**
			 * Image
			 * Displayed image
			 */
			src: {type: String},

			/**
			 * Default image
			 * Image to use if src is not found
			 */
			default_src: {type: String},

			/**
			 * Link Target
			 * Link URL, empty if you don't wan't to display a link.
			 */
			href: {type: String},

			/**
			 * Link target
			 * Link target descriptor
			 */
			extra_link_target: {type: String},

			/**
			 * Popup
			 * widthxheight, if popup should be used, eg. 640x480
			 */
			extra_link_popup: {type: String},

			/**
			 * Expose view
			 * Clicking on an image with href value would popup an expose view, and will show image referenced by href.
			 */
			expose_view: {type: Boolean},
		}
	}

	constructor()
	{
		super();
		this.src = "";
		this.default_src = egw?.image("help");
		this.href = "";
		this.extra_link_target = "_self";
		this.extra_link_popup = "";
		this.expose_view = false;
	}

	connectedCallback()
	{
		super.connectedCallback();
		this._handleClick = this._handleClick.bind(this);
	}

	render()
	{
		return html`
            <img ${this.id ? html`id="${this.id}"` : ''}
                 src="${this.src || this.default_src}"
                 alt="${this.label}"
                 title="${this.statustext || this.label}"
            >`;
	}

	/**
	 * Set image src
	 *
	 * @param {string} _value image, app/image or url
	 */
	set src(_value : string)
	{
		this.__src = _value;

		// allow url's too
		if(_value[0] == '/' || _value.substr(0, 4) == 'http' || _value.substr(0, 5) == 'data:')
		{
			this.setAttribute('src', _value);
			return;
		}
		let src = this.egw().image(_value);
		if(src)
		{
			this.setAttribute('src', src);
		}
	}

	_handleClick(_ev : MouseEvent) : boolean
	{
		if(this.href)
		{
			this.egw().open_link(this.href, this.extra_link_target, this.extra_link_popup);
		}
		else
		{
			return super._handleClick(_ev);
		}
	}

	/**
	 * Handle changes that have to happen based on changes to properties
	 *
	 */
	requestUpdate(name : PropertyKey, oldValue)
	{
		super.requestUpdate(name, oldValue);

		// if there's an href, make it look clickable
		if(name == 'href')
		{
			this.classList.toggle("et2_clickable", this.href)
		}
	}

	/**
	 * Code for implementing et2_IDetachedDOM
	 *
	 * Individual widgets are detected and handled by the grid, but the interface is needed for this to happen
	 *
	 * @param {array} _attrs array to add further attributes to
	 */
	getDetachedAttributes(_attrs)
	{
		_attrs.push('data');
	}

	getDetachedNodes()
	{
		return [this.getDOMNode()];
	}

	setDetachedAttributes(_nodes, _values)
	{
		if(_values.data)
		{
			var pairs = _values.data.split(/,/g);
			for(var i = 0; i < pairs.length; ++i)
			{
				var name_value = pairs[i].split(':');
				jQuery(_nodes[0]).attr('data-' + name_value[0], name_value[1]);
			}
		}
	}
}

customElements.define("et2-image", Et2Image as any, {extends: 'img'});