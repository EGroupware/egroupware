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
	lib/tooltip;
	jquery.jquery;
	et2_DOMWidget;
	et2_inputWidget;
*/

var et2_selectbox = et2_inputWidget.extend({

	attributes: {
		"multiple": {
			"name": "multiple",
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
			"type": "any",
			"name": "Select options",
			"default": {},
			"description": "Internaly used to hold the select options."
		}
	},

	legacyOptions: ["rows"],

	init: function(_parent) {
		this._super.apply(this, arguments);

		// Only allow options inside this element
		this.supportedWidgetClasses = [et2_option];

		this.createInputWidget();
	},

	destroy: function() {
		this._super.apply(this, arguments);

		this.input = null;
	},

	transformAttributes: function(_attrs) {
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

		// Set multiple
		if(this.options.multiple)
		{
			this.input.attr("multiple", "multiple");
		}
	},

	/**
	 * The set_select_optons function is added, as the select options have to be
	 * added after the "option"-widgets were added to selectbox.
	 */
	set_select_options: function(_options) {

		var root = this;

		// Add the select_options
		for (var key in _options)
		{
			var attrs = {
				"value": key
			};

			if (_options[key] instanceof Object)
			{
				attrs["label"] = _options[key]["label"] ? _options[key]["label"] : "";
				attrs["statustext"] = _options[key]["title"] ? _options[key]["title"] : "";
			}
			else
			{
				attrs["label"] = _options[key]
			}

			// Add all other important options to the attributes
			et2_option.prototype.generateAttributeSet(attrs);

			new et2_option(root, attrs);
		}
	}

});

et2_register_widget(et2_selectbox, ["menupopup", "listbox", "select-cat",
	"select-account", "select-percent", 'select-priority', 'select-access',
	'select-country', 'select-state', 'select-year', 'select-month',
	'select-day', 'select-dow', 'select-hour', 'select-number', 'select-app',
	'select-lang', 'select-bool', 'select-timezone' ]);

/**
 * Widget class which represents a single option inside a selectbox
 */
var et2_option = et2_baseWidget.extend({

	attributes: {
		"value": {
			"name": "Value",
			"type": "string",
			"description": "Value which is sent back to the server when this entry is selected."
		},
		"label": {
			"name": "Label",
			"type": "string",
			"description": "Caption of the option element"
		},
		"width": {
			"ignore": true
		},
		"height": {
			"ignore": true
		},
		"align": {
			"ignore": true
		}
	},

	init: function() {
		this._super.apply(this, arguments);

		// Only allow other options inside of this element
		this.supportedWidgetClasses = [et2_option];

		this.option = $j(document.createElement("option"))
			.attr("value", this.options.value);

		if (this.options.label)
		{
			this.option.text(this.options.label);
		}

		this.setDOMNode(this.option[0]);
	},

	destroy: function() {
		this._super.apply(this, arguments);

		this.option = null;
	},

	loadContent: function(_data) {
		this.option.text(_data);
	},

/*	Doesn't work either with selectboxes
	set_statustext: function(_value) {
		this.statustext = _value;
		this.option.attr("title", _value);
	}*/

});

et2_register_widget(et2_option, ["option"]);


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
		if (_sender != this)
		{
			return this._parent.getDOMNode(this);
		}

		return null;
	}

});

et2_register_widget(et2_menulist, ["menulist"]);


