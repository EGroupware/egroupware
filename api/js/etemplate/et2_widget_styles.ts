/**
 * EGroupware eTemplate2 - JS widget class containing styles
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright EGroupware GmbH 2011-2021
 */

/*egw:uses
	et2_core_widget;
*/

import {et2_register_widget, et2_widget, WidgetConfig} from "./et2_core_widget";
import {ClassWithAttributes} from "./et2_core_inheritance";

/**
 * Function which appends the encapsulated style data to the head tag of the
 * page.
 *
 * TODO: The style data could be parsed for rules and appended using the JS
 * stylesheet interface, allowing the style only to modifiy nodes of the current
 * template.
 *
 * @augments et2_widget
 */
export class et2_styles extends et2_widget
{
	private styleNode: HTMLStyleElement;
	private head: HTMLHeadElement;
	private dom_id: string;
	/**
	 * Constructor
	 *
	 * @memberOf et2_styles
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_styles._attributes, _child || {}));

		// Allow no child widgets
		this.supportedWidgetClasses = [];

		// Create the style node and append it to the head node
		this.styleNode = document.createElement("style");
		this.styleNode.setAttribute("type", "text/css");

		this.head = this.egw().window.document.getElementsByTagName("head")[0];
		this.head.appendChild(this.styleNode);
	}

	destroy( )
	{
		// Remove the style node again and delete any reference to it
		this.head.removeChild(this.styleNode);

		super.destroy();
	}

	loadContent( _content)
	{
		// @ts-ignore
		if (this.styleNode.styleSheet)
		{
			// IE
			// @ts-ignore
			this.styleNode.styleSheet.cssText += _content;
		}
		else
		{
			this.styleNode.appendChild(document.createTextNode(_content));
		}
	}

	/**
	 * Sets the id of the DOM-Node.
	 *
	 * DOM id's have dots "." replaced with dashes "-"
	 *
	 * @param {string} _value id to set
	 */
	set_id( _value)
	{

		this.id = _value;
		this.dom_id = _value ? this.getInstanceManager().uniqueId+'_'+_value.replace(/\./g, '-') : _value;

		if (this.styleNode)
		{
			if (_value != "")
			{
				this.styleNode.setAttribute("id", this.dom_id);
			}
			else
			{
				this.styleNode.removeAttribute("id");
			}
		}
	}
}
et2_register_widget(et2_styles, ["styles"]);

