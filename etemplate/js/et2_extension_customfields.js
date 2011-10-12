/**
 * eGroupWare eTemplate2 - JS Custom fields object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2011
 * @version $Id$
 */

"use strict";

/*egw:uses
	lib/tooltip;
	jquery.jquery;
	et2_core_xml;
	et2_core_DOMWidget;
	et2_core_inputWidget;
*/

var et2_customfields_list = et2_DOMWidget.extend([et2_IDetachedDOM], {

	attributes: {
		'customfields': {
			'name': 'Custom fields',
			'description': 'Auto filled'
		},
		'fields': {
			'name': 'Custom fields',
			'description': 'Auto filled'
		},
		'value': {
			'name': 'Custom fields',
			'description': 'Auto filled'
		},
	},

	prefix: '#',

	init: function() {
		// Some apps (infolog edit) don't give ID, so assign one to get settings
		if(!arguments[1].id) arguments[1].id = "custom_fields";

		this._super.apply(this, arguments);

		// Create the table body and the table
                this.tbody = $j(document.createElement("tbody"));
                this.table = $j(document.createElement("table"))
                        .addClass("et2_grid");
                this.table.append(this.tbody);


		this.rows = {};
		this.widgets = {};
		this.detachedNodes = [];

		if(this.options && this.options.customfields)
		{
			this.loadFields();
		}
	},

	destroy: function() {
		this._super.apply(this, arguments);
		this.tbody = null;
		this.table = null;
		this.rows = null;
		this.widgets = null;
	},

	getDOMNode: function(_sender) {
		// If the parent class functions are asking for the DOM-Node, return the
		// outer table.
		if (_sender == this)
		{
			return this.table[0];
		}

		// Check whether the _sender object exists inside the management array
		if(this.rows && _sender.id && this.rows[_sender.id])
		{
			return this.rows[_sender.id];
		}

		return null;
	},

	/**
	 * Initialize widgets for custom fields
	 */
	loadFields: function() {
		if(!this.options || !this.options.customfields) return;

		// Already set up - avoid duplicates in nextmatch
		if(this._type == 'customfields-list' && !this.isInTree()) return;

		// Create the table rows
		for(var field_name in this.options.customfields)
		{
			// Avoid creating field twice
			if(this.rows[field_name]) continue;

			var field = this.options.customfields[field_name];
			var row = jQuery(document.createElement("tr"))
				.appendTo(this.tbody);
			var cf = jQuery(document.createElement("td"))
				.appendTo(row);
			var setup_function = '_setup_'+field.type;
			var attrs = {
				'id': 		field_name,
				'statustext':	field.help,
				'required':	field.needed,
			};
			if(this[setup_function]) {
				this[setup_function].call(this, field_name, field, attrs);
			}
			
			if(this._type == 'customfields-list') {
				// No label, cust widget
				attrs.readonly = true;
				this.detachedNodes.push(cf[0]);
				this.rows[field_name] = cf[0];
			} else {
				// Label in first column, widget in 2nd
				cf.text(field.label + "");
				cf = jQuery(document.createElement("td"))
					.appendTo(row);
				this.rows[field_name] = cf[0];
			}

			// No label on the widget itself
			delete(attrs.label);

			// Create widget
			var widget = this.widgets[field_name] = et2_createWidget(field.type, attrs, this);
		}
	},

	transformAttributes: function(_attrs) {
		this._super.apply(this, arguments);

		// Add in settings that are objects
		if(!_attrs.customfields)
		{
			var data = this.getArrayMgr("modifications").getEntry(this.id);
			for(var key in data)
			{
				if(data[key] instanceof Object && ! _attrs[key]) _attrs[key] = data[key];
			}
		}

		if (this.id)
		{
			// Set the value for this element
			var contentMgr = this.getArrayMgr("content");
			if (contentMgr != null) {
				var val = contentMgr.getEntry(this.id);
				_attrs["value"] = {};
				if (val !== null)
				{
					// Only set the values that match desired custom fields
					for(var key in val)
					{
						if(key.indexOf(this.prefix) == 0) {
							_attrs["value"][key] = val[key];
						}
					}
					//_attrs["value"] = val;
				}
				else
				{
					// Check for custom fields directly in record
					for(var key in _attrs.customfields)
					{
						_attrs["value"][this.prefix + key] = contentMgr.getEntry(this.prefix + key);
					}
				}
			}
		}

		if(this.options && this.options.customfields)
		{
			this.loadFields();
		}
	},
		
	set_value: function(_value) {
		if(!this.options.customfields) return;
		for(var field_name in this.options.customfields)
		{
			if(!this.widgets[field_name] || !this.widgets[field_name].set_value) continue;
			var value = _value[this.prefix + field_name] ? _value[this.prefix + field_name] : null;
			this.widgets[field_name].set_value(value);
		}
	},

	/**
	 * Adapt provided attributes to match options for widget
	 */
	_setup_text: function(field_name, field, attrs) {
		field.type = 'textbox';
		attrs.rows = field.rows;
		attrs.size = field.len;
	},
	_setup_select: function(field_name, field, attrs) {
		attrs.select_options = field.values;
	},

	/**
         * Code for implementing et2_IDetachedDOM
         */
	getDetachedAttributes: function(_attrs)
	{
		_attrs.push("value", "class");
	},

	getDetachedNodes: function()
	{
		return this.detachedNodes ? this.detachedNodes : [];
	},

	setDetachedAttributes: function(_nodes, _values)
	{
		if (typeof _values["value"] != "undefined")
		{
			for(var field_name in this.widgets) {
				var value = _values["value"][this.prefix + field_name] ? _values["value"][this.prefix + field_name] : null;
				if(this.widgets[field_name].implements( et2_IDetachedDOM))
				{
					var widget_values = {"value": value};
					this.widgets[field_name].setDetachedAttributes(this.widgets[field_name].getDetachedNodes(), widget_values);
				}
				else
				{
					this.widgets[field_name].set_value(value);
				}
			}
		}

		if (typeof _values["class"] != "undefined")
		{
			this.set_class(_values["class"]);
                }
        }
});

et2_register_widget(et2_customfields_list, ["customfields", "customfields-list"]);

