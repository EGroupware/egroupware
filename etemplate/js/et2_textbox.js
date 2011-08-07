/**
 * eGroupWare eTemplate2 - JS Textbox object
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
	et2_widget;
*/

/**
 * Class which implements the "textbox" XET-Tag
 */ 
var et2_textbox = et2_DOMWidget.extend({

	init: function(_parent) {
		this.input = $j(document.createElement("input"))
			.addClass("et2_input");

		this._super.apply(this, arguments);
		this.label = "";
	},

	set_value: function(_value) {
		if (_value != this.value)
		{
			this.label = _value;

			this.input.attr("value", _value);
		}
	},

	getDOMNode: function() {
		return this.input[0];
	}

});

et2_register_widget(et2_textbox, ["textbox"]);

