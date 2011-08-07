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
	et2_widget;
*/

/**
 * Class which implements the "button" XET-Tag
 */ 
var et2_button = et2_DOMWidget.extend({

	init: function(_parent) {
		this.btn = $j(document.createElement("button"))
			.addClass("et2_button");

		this._super.apply(this, arguments);
		this.label = "";
	},

	set_label: function(_value) {
		if (_value != this.value)
		{
			this.label = _value;

			this.btn.text(_value);
		}
	},

	getDOMNode: function() {
		return this.btn[0];
	}

});

et2_register_widget(et2_button, ["button"]);

