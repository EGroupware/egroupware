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

	loadFields: function() {
		if(!this.options || !this.options.customfields) return;
		if(!this.isInTree()) return;

		// Create the table rows
		for(var field_name in this.options.customfields)
		{
			if(this.rows[field_name]) continue;

			var field = this.options.customfields[field_name];
			var row = jQuery(document.createElement("tr"))
				.appendTo(this.tbody);
			var cf = jQuery(document.createElement("td"))
				.appendTo(row);
			var attrs = jQuery.extend(true, {'id': field_name}, field);
			if(this._type == 'customfields-list') {
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
			var widget = this.widgets[field_name] = et2_createWidget(field.type, attrs, this);
		}
	},
	transformAttributes: function(_attrs) {
		this._super.apply(this, arguments);

		if (this.id)
		{
			// Set the value for this element
			var contentMgr = this.getArrayMgr("content");
			if (contentMgr != null) {
				var val = contentMgr.getEntry(this.id);
				if (val !== null)
				{
					// Only set the values that match desired custom fields
					_attrs["value"] = {};
					for(var key in val)
					{
						if(key.indexOf(this.prefix) == 0) {
							_attrs["value"][key] = val[key];
						}
					}
					//_attrs["value"] = val;
				}
			}
		}

		// Add in settings that are objects
		if(!_attrs.customfields)
		{
			var data = this.getArrayMgr("modifications").getEntry(this.id);
			for(var key in data)
			{
				if(data[key] instanceof Object && ! _attrs[key]) _attrs[key] = data[key];
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
			this.widgets[field_name].set_value(_value[this.prefix + field_name]);
		}
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
			this.set_value(_values["value"]);
		}

		if (typeof _values["class"] != "undefined")
		{
			this.set_class(_values["class"]);
                }
        }
});

et2_register_widget(et2_customfields_list, ["customfields", "customfields-list"]);

