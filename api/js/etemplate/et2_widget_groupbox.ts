/**
 * EGroupware eTemplate2 - JS Groupbox object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2012
 */

/*egw:uses
	et2_core_baseWidget;
*/

import {et2_register_widget, WidgetConfig} from "./et2_core_widget";
import {et2_baseWidget} from "./et2_core_baseWidget";
import {ClassWithAttributes} from "./et2_core_inheritance";

/**
 * Class which implements the groupbox tag
 *
 * @augments et2_baseWidget
 */
export class et2_groupbox extends et2_baseWidget
{
	/**
	 * Constructor
	 *
	 * @memberOf et2_groupbox
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_groupbox._attributes, _child || {}));

		this.setDOMNode(document.createElement("fieldset"));
	}
}
et2_register_widget(et2_groupbox, ["groupbox"]);

/**
 * @augments et2_baseWidget
 */
export class et2_groupbox_legend extends et2_baseWidget
{
	static readonly _attributes : any = {
		"label": {
			"name": "Label",
			"type": "string",
			"default": "",
			"description": "Label for group box",
			"translate" : true
		}
	};

	/**
	 * Constructor
	 *
	 * @memberOf et2_groupbox_legend
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_groupbox_legend._attributes, _child || {}));
		let legend = jQuery(document.createElement("legend")).text(this.options.label);
		this.setDOMNode(legend[0]);
	}
}
et2_register_widget(et2_groupbox_legend, ["caption"]);

