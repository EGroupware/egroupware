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
		},

		"onclick": {
			"name": "onclick",
			"type": "js",
			"description": "JS code which gets executed when the button is clicked"
		}
	},

	init: function(_parent) {
		this._super.apply(this, arguments);

		this.label = "";

		this.btn = $j(document.createElement("button"))
			.addClass("et2_button")
			.click(this, function(e) {e.data.buttonClick()});

		this.setDOMNode(this.btn[0]);
	},

	buttonClick: function() {
		// Execute the JS code connected to the event handler
		if (this.onclick != null)
		{
			if (!this.onclick())
				return false;
		}

		// Fetch the form data
		var formData = this.getRoot().getValues();

		// Submit it!
		console.log(formData);
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

