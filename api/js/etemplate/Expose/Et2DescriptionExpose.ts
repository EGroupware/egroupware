/**
 * EGroupware eTemplate2 - Description that can expose
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2022 Nathan Gray
 */

import {ExposeMixin, ExposeValue, MediaValue} from "./ExposeMixin";
import {Et2Description} from "../Et2Description/Et2Description";
import {et2_IDetachedDOM} from "../et2_core_interfaces";
import {html} from "@lion/core";

/**
 * Shows a description and if you click on it, it shows the file specified by href in gallery.
 *
 * If the gallery cannot handle the file type (specified by mime) then href is handled as
 * a normal description, and clicking follows the link.
 */
//@ts-ignore Something not right with types & inheritance according to TypeScript
export class Et2DescriptionExpose extends ExposeMixin(Et2Description) implements et2_IDetachedDOM
{
	static get properties()
	{
		return {
			...super.properties,

			/**
			 * Mime type
			 * Used to determine this widget can be exposed.  If not one of the OK mime types, will be treated
			 * as a normal description
			 */
			mime: {
				type: String,
				reflect: true
			},

			/**
			 * hash for data stored on service-side with egw_link::(get|set)_data()
			 */
			mimeData: {type: String},
		}
	}

	constructor() {super();}

	connectedCallback()
	{
		super.connectedCallback();
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
	}

	/** These guys needed to get value where it needs to be */
	set value(new_value)
	{
		return super.value = new_value;
	}

	get value()
	{
		return super.value;
	}

	/**
	 * Needed to for transformAttributes() to set the value.
	 * Not sure why Et2Description.set_value() isn't enough.
	 */
	set_value(value)
	{
		super.set_value(value);
	}

	/**
	 * Override the wrap link, since clicking on a link would work and do both
	 * @param href
	 * @param value
	 * @returns {TemplateResult<1>}
	 * @protected
	 */
	protected wrapLink(href, value)
	{
		if(this.isExposable())
		{
			return html`${value}`;
		}
		else
		{
			// Expose cannot handle this particular file / link, wrap it as normal
			return super.wrapLink(href, value);
		}
	}

	/**
	 * Used to determine if this widget is exposable.  Images always are, even if we don't actually
	 * know the mime type.
	 *
	 * @returns {ExposeValue}
	 */
	get exposeValue() : ExposeValue
	{
		return {
			mime: this.mime,
			path: this.href,
			download_url: this.href,
		};
	}

	/**
	 * Get the info needed to show this image as slide(s)
	 */
	getMedia(_value) : MediaValue[]
	{
		let media = super.getMedia(_value);
		if(media)
		{
			media[0].title = this.value;
		}
		return media;
	}
}

customElements.define("et2-description-expose", Et2DescriptionExpose as any);