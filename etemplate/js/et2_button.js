/**
 * eGroupWare eTemplate2 - JS Button object
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
	et2_baseWidget;
*/

/**
 * Class which implements the "button" XET-Tag
 */ 
var et2_button = et2_baseWidget.extend({

	attributes: {
		"label": {
			"name": "caption",
			"type": "string",
			"description": "Label of the button"
		}
	},

	init: function(_parent) {
		this._super.apply(this, arguments);

		this.label = "";

		this.btn = $j(document.createElement("button"))
			.addClass("et2_button");

		this.setDOMNode(this.btn[0]);
	},

	set_label: function(_value) {
		if (_value != this.value)
		{
			this.label = _value;

			this.btn.text(_value);
		}
	}

});

et2_register_widget(et2_button, ["button"]);

