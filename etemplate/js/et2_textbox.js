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
	et2_inputWidget;
	et2_valueWidget;
*/

/**
 * Class which implements the "textbox" XET-Tag
 */ 
var et2_textbox = et2_inputWidget.extend({

	attributes: {
		"multiline": {
			"name": "multiline",
			"type": "boolean",
			"default": false,
			"description": "If true, the textbox is a multiline edit field."
		}
	},

	init: function(_parent) {
		this._super.apply(this, arguments);

		this.input = null;
		this.id = "";

		this.createInputWidget();
	},

	createInputWidget: function() {
		if (this.multiline)
		{
			this.input = $j(document.createElement("textarea"));
		}
		else
		{
			this.input = $j(document.createElement("input"));
		}

		this.input.addClass("et2_textbox");

		this.setDOMNode(this.input[0]);
	},

	set_multiline: function(_value) {
		if (_value != this.multiline)
		{
			this.multiline = _value;

			this.createInputWidget();

			// Write all settings again
			this.update();
		}
	}

});

et2_register_widget(et2_textbox, ["textbox", "int", "float"]);

/**
 * et2_textbox_ro is the dummy readonly implementation of the textbox.
 */
var et2_textbox_ro = et2_valueWidget.extend({

	/**
	 * Ignore all more advanced attributes.
	 */
	attributes: {
		"multiline": {
			"ignore": true
		}
	},

	init: function() {
		this._super.apply(this, arguments);

		this.value = "";
		this.span = $j(document.createElement("span"))
			.addClass("et2_textbox_ro");

		this.setDOMNode(this.span[0]);
	},

	set_value: function(_value) {
		this.value = _value;

		this.span.text(_value);
	}

});

et2_register_widget(et2_textbox_ro, ["textbox_ro", "int_ro", "float_ro"]);

