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
	et2_core_xml;
	et2_core_DOMWidget;
	et2_core_inputWidget;
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

	init: function() {
		this._super.apply(this, arguments);
 
		// Allow no other widgets inside this one
		this.supportedWidgetClasses = [];

		// Legacy options could have row count or empty label in first slot	 
		if(typeof this.options.rows == "string" && isNaN(this.options.rows)) {
			this.options.empty_label = this.egw().lang(this.options.rows);
			this.options.rows = 1;
		}
		if(this.options.rows > 1) this.options.multiple = true;
		this.createInputWidget();
	},

	destroy: function() {
		this._super.apply(this, arguments);

		this.input = null;
	},

	transformAttributes: function(_attrs) {
		this._super.apply(this, arguments);

		// If select_options are already known, skip the rest
		if(this.options && this.options.select_options && !jQuery.isEmptyObject(this.options.select_options))
		{
			return;
		}

		var name_parts = this.id.replace(/&#x5B;/g,'[').replace(/]|&#x5D;/g,'').split('[');

		// Try to find the options inside the "sel-options" array
		if(this.getArrayMgr("sel_options"))
		{
			// Select options tend to be defined once, at the top level, so try that first
			var content_options = this.getArrayMgr("sel_options").getRoot().getEntry(name_parts[name_parts.length-1]);

			// Try again according to ID, but strip any encoded ][
			if(!content_options) content_options = this.getArrayMgr("sel_options").getEntry(this.id.replace(/&#x5B;/g,'[').replace(/&#x5D;/g,']'));
			if(_attrs["select_options"] && content_options)
			{
				_attrs["select_options"] = jQuery.extend({},_attrs["select_options"],content_options);
			} else if (content_options) {
				_attrs["select_options"] = content_options;
			}
		}

		// Check whether the options entry was found, if not read it from the
		// content array.
		if (_attrs["select_options"] == null)
		{
			// Again, try last name part at top level
			var content_options = this.getArrayMgr('content').getRoot().getEntry(name_parts[name_parts.length-1]);
			// If that didn't work, check according to ID
			_attrs["select_options"] = content_options ? content_options : this.getArrayMgr('content')
				.getEntry("options-" + this.id)
		}

		// Default to an empty object
		if (_attrs["select_options"] == null)
		{
			_attrs["select_options"] = {};
		}
	},

	_appendOptionElement: function(_value, _label, _title) {
		if(_value == "" && (_label == null || _label == "")) {
			_label = this.options.empty_label;
		}

		var option = $j(document.createElement("option"))
			.attr("value", _value)
			.text(_label+"");

		if (typeof _title != "undefined" && _title)
		{
			option.attr("title", _title);
		}
		option.appendTo(this.input);
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
				this.options.empty_label);
		}

		// Set multiple
		if(this.options.multiple)
		{
			this.input.attr("multiple", "multiple");
		}
	},

	loadFromXML: function(_node) {
		// Read the option-tags
		var options = et2_directChildrenByTagName(_node, "options");
		for (var i = 0; i < options.length; i++)
		{
			this._appendOptionElement(
				et2_readAttrWithDefault(options[i], "value", options[i].textContent),
				options[i].textContent,
				et2_readAttrWithDefault(options[i], "title", "")
			);
		}
	},

	set_value: function(_value) {
		jQuery("option",this.input).attr("selected", false);
		this.value = _value;
		if(typeof _value == "array")
		{
			for(var i = 0; i < _value.length; i++)
			{
				jQuery("option[value='"+_value[i]+"']", this.input).attr("selected", true);
			}
		}
		else if (typeof _value == "object")
		{
			for(var i in _value)
			{
				jQuery("option[value='"+_value[i]+"']", this.input).attr("selected", true);
			}
		}
		else
		{
			if(jQuery("option[value='"+_value+"']", this.input).attr("selected", true).length == 0)
			{
				this.egw().debug("warning", "Tried to set value that isn't an option", this, _value);
	//console.trace();
			}
		}
	},

	/**
	 * The set_select_options function is added, as the select options have to be
	 * added after the "option"-widgets were added to selectbox.
	 */
	set_select_options: function(_options) {
		// Add the select_options
		for (var key in _options)
		{

			// Translate the options
			if(!this.options.no_lang)
			{
				if (typeof _options[key] === 'object')
				{
					if(_options[key]["label"]) _options[key]["label"] = this.egw().lang(_options[key]["label"]);
					if(_options[key]["title"]) _options[key]["title"] = this.egw().lang(_options[key]["title"]);
				}
				else
				{
					_options[key] = this.egw().lang(_options[key]);
				}
			}

			if (typeof _options[key] === 'object')
			{
				this._appendOptionElement(key, 
					_options[key]["label"] ? _options[key]["label"] : "",
					_options[key]["title"] ? _options[key]["title"] : "");
			}
			else
			{
				this._appendOptionElement(key, _options[key]);
			}
		}
		// Sometimes value gets set before options
		if(this.value) this.set_value(this.value);
	}
});

et2_register_widget(et2_selectbox, ["menupopup", "listbox", "select", "select-cat",
	"select-account", "select-percent", 'select-priority', 'select-access',
	'select-country', 'select-state', 'select-year', 'select-month',
	'select-day', 'select-dow', 'select-hour', 'select-number', 'select-app',
	'select-lang', 'select-bool', 'select-timezone' ]);

/**
 * et2_selectbox_ro is the readonly implementation of the selectbox.
 */
var et2_selectbox_ro = et2_selectbox.extend([et2_IDetachedDOM], {

	init: function() {
		this._super.apply(this, arguments);

		this.supportedWidgetClasses = [];
		this.optionValues = {};
	},

	createInputWidget: function() {
		this.span = $j(document.createElement("span"))
			.addClass("et2_selectbox readonly")
			.text(this.options.empty_label);

		this.setDOMNode(this.span[0]);
	},

	loadFromXML: function(_node) {
		// Read the option-tags
		var options = et2_directChildrenByTagName(_node, "options");
		for (var i = 0; i < options.length; i++)
		{
			this.optionValues[et2_readAttrWithDefault(options[i], "value", 0)] =
				{
					"label": options[i].textContent,
					"title": et2_readAttrWithDefault(options[i], "title", "")
				}
		}
	},

	set_select_options: function(_options) {
		for (var key in _options)
		{
			this.optionValues[key] = _options[key];
		}
	},

	set_value: function(_value) {
		this.value = _value;
		var option = this.optionValues[_value];
		if (typeof option === 'object')
		{
			this.span.text(option.label);
			this.set_statustext(option.title);
		}
		else if (typeof option === 'string')
		{
			this.span.text(option);
		}
		else
		{
			this.span.text("");
		}
	},

	/**
	 * Functions for et2_IDetachedDOM
	 */
	 /**
         * Creates a list of attributes which can be set when working in the
         * "detached" mode. The result is stored in the _attrs array which is provided
         * by the calling code.
         */
        getDetachedAttributes: function(_attrs) {
                _attrs.push("value");
        },

        /**
         * Returns an array of DOM nodes. The (relatively) same DOM-Nodes have to be
         * passed to the "setDetachedAttributes" function in the same order.
         */
        getDetachedNodes: function() {
                return [this.span[0]];
        },

        /**
         * Sets the given associative attribute->value array and applies the
         * attributes to the given DOM-Node.
         *
         * @param _nodes is an array of nodes which have to be in the same order as
         *      the nodes returned by "getDetachedNodes"
         * @param _values is an associative array which contains a subset of attributes
         *      returned by the "getDetachedAttributes" function and sets them to the
         *      given values.
         */
        setDetachedAttributes: function(_nodes, _values) {
                this.span = jQuery(_nodes[0]);
                this.set_value(_values["value"]);
	}
});

et2_register_widget(et2_selectbox_ro, ["menupopup_ro", "listbox_ro", "select_ro", "select-cat_ro",
	"select-percent_ro", 'select-priority_ro', 'select-access_ro',
	'select-country_ro', 'select-state_ro', 'select-year_ro', 'select-month_ro',
	'select-day_ro', 'select-dow_ro', 'select-hour_ro', 'select-number_ro', 'select-app_ro',
	'select-lang_ro', 'select-bool_ro', 'select-timezone_ro' ]);

/**
 * Widget class which represents a single option inside a selectbox
 */
/*var et2_option = et2_baseWidget.extend({

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
			.attr("value", this.options.value)
			.attr("selected", this._parent.options.value == this.options.value ?
				"selected" : "");

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
	}

/*	Doesn't work either with selectboxes
	set_statustext: function(_value) {
		this.statustext = _value;
		this.option.attr("title", _value);
	}*/

//});*/

//et2_register_widget(et2_option, ["option"]);


/**
 * Class which just implements the menulist container
 */ 
var et2_menulist = et2_DOMWidget.extend({

	init: function() {
		this._super.apply(this, arguments);

		this.supportedWidgetClasses = [et2_selectbox, et2_selectbox_ro];
	},

	// Just pass the parent DOM node through
	getDOMNode: function(_sender) {
		if (_sender != this)
		{
			return this._parent.getDOMNode(this);
		}

		return null;
	},

	// Also need to pass through parent's children
	getChildren: function() {
		return this._parent.getChildren();
	}

});

et2_register_widget(et2_menulist, ["menulist"]);


