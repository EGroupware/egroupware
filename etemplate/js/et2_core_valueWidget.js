/**
 * eGroupWare eTemplate2 - JS widget class with value attribute and auto loading
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
 */

"use strict";

/*egw:uses
	jquery.jquery;
	et2_core_baseWidget;
*/

/**
 * et2_valueWidget is the base class for et2_inputWidget - valueWidget introduces
 * the "value" attribute and automatically loads it from the "content" array
 * after loading from XML.
 */
var et2_valueWidget = et2_baseWidget.extend({

	attributes: {
		"value": {
			"name": "Value",
			"description": "The value of the widget",
			"type": "string",
			"default": et2_no_init
		}
	},

	transformAttributes: function(_attrs) {
		this._super.apply(this, arguments);

		if (this.id)
		{
			// Set the value for this element
			var contentMgr = this.getArrayMgr("content");
			if (contentMgr != null) {
				var val = contentMgr.getEntry(this.id);
				if (val !== null)
				{
					_attrs["value"] = val;
				}
			}
		}
	}

});

