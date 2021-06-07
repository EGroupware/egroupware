/**
 * EGroupware eTemplate2 - JS HRule object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Andreas Stöckel
 */

/*egw:uses
	et2_core_baseWidget;
*/

import {et2_register_widget, WidgetConfig} from "./et2_core_widget";
import {et2_baseWidget} from "./et2_core_baseWidget";
import {ClassWithAttributes} from "./et2_core_inheritance";

/**
 * Class which implements the hrule tag
 *
 * @augments et2_baseWidget
 */
export class et2_hrule extends et2_baseWidget
{
	/**
	 * Constructor
	 *
	 * @memberOf et2_hrule
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_hrule._attributes, _child || {}));

		this.setDOMNode(document.createElement("hr"));
	}
}
et2_register_widget(et2_hrule, ["hrule"]);


