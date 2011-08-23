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
	et2_inputWidget;
	et2_baseWidget;
*/

/**
 * Class which implements the "button" XET-Tag
 */ 
var et2_button = et2_baseWidget.extend(et2_IInput, {

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

	init: function() {
		this._super.apply(this, arguments);

		this.label = "";
		this.clicked = false;
		this.btn = null;

		if (!this.options.readonly)
		{
			this.btn = $j(document.createElement("button"))
				.addClass("et2_button");

			this.setDOMNode(this.btn[0]);
		}
	},

	click: function() {
		// Execute the JS code connected to the event handler
		if (this.onclick)
		{
			if (!this.onclick())
				return false;
		}

		// Submit the form
		if (this._type != "buttononly")
		{
			this.clicked = true;
			this.getInstanceManager().submit();
			this.clicked = false;
		}
	},

	set_label: function(_value) {
		if (this.btn)
		{
			this.label = _value;

			this.btn.text(_value);
		}
	},


	/**
	 * Implementation of the et2_IInput interface
	 */

	/**
	 * Always return false as a button is never dirty
	 */
	isDirty: function() {
		return false;
	},

	resetDirty: function() {
	},

	getValue: function() {
		if (this.clicked)
		{
			return true;
		}

		// If "null" is returned, the result is not added to the submitted
		// array.
		return null;
	}

});

et2_register_widget(et2_button, ["button", "buttononly"]);

