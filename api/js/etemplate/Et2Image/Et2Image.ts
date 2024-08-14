/**
 * EGroupware eTemplate2 - Image widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

import {css, html, LitElement, render, nothing} from "lit";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {et2_IDetachedDOM} from "../et2_core_interfaces";
import {property} from "lit/decorators/property.js";
import {customElement} from "lit/decorators/custom-element.js";

@customElement("et2-image")
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

				::slotted(img) {
					max-height: 100%;
					max-width: 100%;
				}

				:host([icon]) {
					height: 1.3rem;
				}
			`];
	}

	/**
	 * The label of the image
	 * Actually not used as label, but we put it as title
	 */
	@property({type: String})
	label = "";

	/**
	 * Image
	 * Displayed image
	 */
	@property({type: String})
	src = "";

	/**
	 * Default image
	 * Image to use if src is not found
	 */
	@property({type: String})
	defaultSrc = "";

	/**
	 * Link Target
	 * Link URL, empty if you don't wan't to display a link.
	 */
	@property({type: String})
	href = "";

	/**
	 * Link target
	 * Link target descriptor
	 */
	@property({type: String})
	extraLinkTarget = "_self";

	/**
	 * Popup
	 * widthxheight, if popup should be used, eg. 640x480
	 */
	@property({type: String})
	extraLinkPopup = "";

	/**
	 * Width of image
	 */
	@property({type: String})
	width;

	/**
	 * Height of image
	 */
	@property({type: String})
	height;

	@property({type: String})
	style;

	constructor()
	{
		super();

		this._handleClick = this._handleClick.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();
	}

	render()
	{
		let src = this.parse_href(this.src) || this.parse_href(this.defaultSrc);
		if(!src)
		{
			// Hide if no valid image
			return html``;
		}
		return html`
            <img ${this.id ? html`id="${this.id}"` : ''}
                 src="${src}"
                 alt="${this.label}"
				 width="${this.width || nothing}"
                 height="${this.height || nothing}"
                 style="${this.style || nothing}"
                 part="image"
                 loading="lazy"
                 title="${this.statustext || this.label}"
            >`;
	}

	/**
	 * Puts the rendered content / img-tag in light DOM
	 * @link https://lit.dev/docs/components/shadow-dom/#implementing-createrenderroot
	 */
	protected createRenderRoot()
	{
		return this;
	}

	protected parse_href(img_href : string) : string
	{
		img_href = img_href || '';
		// allow url's too
		if(img_href[0] == '/' || img_href.substr(0, 4) == 'http' || img_href.substr(0, 5) == 'data:')
		{
			return img_href;
		}
		let src = this.egw()?.image(img_href);
		if(src)
		{
			return src;
		}
		return "";
	}

	_handleClick(_ev : MouseEvent) : boolean
	{
		if(this.href)
		{
			this.egw().open_link(this.href, this.extraLinkTarget, this.extraLinkPopup);
		}
		else
		{
			return super._handleClick(_ev);
		}
	}

	get _img()
	{
		return this.querySelector('img');
	}

	/**
	 * Handle changes that have to happen based on changes to properties
	 *
	 */
	updated(changedProperties)
	{
		super.updated(changedProperties);

		if(changedProperties.has("src") && !this._img)
		{
			render(this._imageTemplate(), this);
		}
		if(changedProperties.has("src") && this._img)
		{
			this._img.setAttribute("src", this.parse_href(this.src) || this.parse_href(this.defaultSrc));
		}
		// if there's an href or onclick, make it look clickable
		if(changedProperties.has("href") || typeof this.onclick !== "undefined")
		{
			this.classList.toggle("et2_clickable", this.href || typeof this.onclick !== "undefined")
		}
		for(const changedPropertiesKey in changedProperties)
		{
			if(Et2Image.getPropertyOptions()[changedPropertiesKey])
			{
				this._img[changedPropertiesKey] = this[changedPropertiesKey];
			}
		}
	}

	transformAttributes(_attrs : any)
	{
		super.transformAttributes(_attrs);

		// Expand src with additional stuff
		// This should go away, since we're not checking for $ or @
		if(typeof _attrs["src"] != "undefined")
		{
			let manager = this.getArrayMgr("content");
			if(manager && _attrs["src"])
			{
				let src = manager.getEntry(_attrs["src"], false, true);
				if(typeof src != "undefined" && src !== null)
				{
					if(typeof src == "object")
					{
						this.src = this.egw().link('/index.php', src);
					}
					else
					{
						this.src = src;
					}
				}
			}
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
		_attrs.push("src", "label", "href", "statustext");
	}

	getDetachedNodes()
	{
		return [<HTMLElement><unknown>this];
	}

	setDetachedAttributes(_nodes, _values)
	{
		for(let attr in _values)
		{
			this[attr] = _values[attr];
		}
	}
}