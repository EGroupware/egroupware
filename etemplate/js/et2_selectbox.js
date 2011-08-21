/**
 * eGroupWare eTemplate2 - JS Selectbox object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @author Andreas Stöckel
 * @copyright Nathan Gray 2011
 * @version $Id$
 */

"use strict";

/*egw:uses
	jquery.jquery;
	et2_inputWidget;
*/

var et2_selectbox = et2_inputWidget.extend({

	attributes: {
		"multiselect": {
			"name": "multiselect",
			"type": "boolean",
			"default": false,
			"description": "Allow selecting multiple options"
		},
		"rows": {
			"name": "Rows",
			"type": "any",	// Old options put either rows or empty_label in first space
			"default": 1,
			"description": "Number of rows to display"
		},
		"empty_label": {
			"name": "Empty label",
			"type": "string",
			"default": "",
			"description": "Textual label for first row, eg: 'All' or 'None'.  ID will be ''"
		},
		"select_options": {
			"ignore": true // Just include "select_options" here to have it copied from the parseArrayMgrAttrs to the options-object
		}
	},

	legacyOptions: ["rows"],

	init: function(_parent) {
		this._super.apply(this, arguments);

		// This widget allows no other widgets inside of it
		this.supportedWidgetClasses = [];

		this.createInputWidget();
	},

	parseArrayMgrAttrs: function(_attrs) {
		// Try to find the options inside the "sel-options" array
		_attrs["select_options"] = this.getArrayMgr("sel_options").getValueForID(this.id);

		// Check whether the options entry was found, if not read it from the
		// content array.
		if (_attrs["select_options"] == null)
		{
			_attrs["select_options"] = this.getArrayMgr('content')
				.getValueForID("options-" + this.id)
		}

		// Default to an empty object
		if (_attrs["select_options"] == null)
		{
			_attrs["select_options"] = {};
		}
	},

	_appendOptionElement: function(_value, _label) {
		$j(document.createElement("option"))
			.attr("value", _value)
			.text(_label)
			.appendTo(this.input);
	},

	createInputWidget: function() {
		// Create the base input widget
		this.input = $j(document.createElement("select"))
			.addClass("et2_selectbox")
			.attr("size", this.options.rows);

		this.setDOMNode(this.input[0]);

		// Add the empty label
		if(this.options.empty_label)
		{
			this._appendOptionElement("" == this.getValue() ? "selected" : "",
				this.empty_label);
		}

		// Add the select_options
		for(var key in this.options.select_options)
		{
			this._appendOptionElement(key, this.options.select_options[key]);
		}

		// Set multiselect
		if(this.options.multiselect)
		{
			this.input.attr("multiple", "multiple");
		}
	}

});

et2_register_widget(et2_selectbox, ["menupopup", "listbox", "select-cat",
	"select-account"]);

/**
 * Class which just implements the menulist container
 */ 
var et2_menulist = et2_DOMWidget.extend({

	init: function() {
		this._super.apply(this, arguments);

		this.supportedWidgetClasses = [et2_selectbox];
	},

	// Just pass the parent DOM node through
	getDOMNode: function(_sender) {
		if (_sender != this._parent && _sender != this)
		{
			return this._parent.getDOMNode(this);
		}

		return null;
	}

});

et2_register_widget(et2_menulist, ["menulist"]);


