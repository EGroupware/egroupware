/**
 * EGroupware eTemplate2 - JS Custom fields object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2011
 */

/*egw:uses
	lib/tooltip;
	/vendor/bower-asset/jquery/dist/jquery.js;
	et2_core_xml;
	et2_core_DOMWidget;
	et2_core_inputWidget;
*/

import {et2_createWidget, et2_register_widget, et2_registry, WidgetConfig} from "./et2_core_widget";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_valueWidget} from "./et2_core_valueWidget";
import {et2_readonlysArrayMgr} from "./et2_core_arrayMgr";
import {et2_IDetachedDOM, et2_IInput} from "./et2_core_interfaces";
import {et2_cloneObject, et2_no_init} from "./et2_core_common";
import {et2_DOMWidget} from "./et2_core_DOMWidget";
import {loadWebComponent} from "./Et2Widget/Et2Widget";
import {LitElement} from "@lion/core";

export class et2_customfields_list extends et2_valueWidget implements et2_IDetachedDOM, et2_IInput
{
	static readonly _attributes = {
		'customfields': {
			'name': 'Custom fields',
			'description': 'Auto filled',
			'type': 'any'
		},
		'fields': {
			'name': 'Custom fields',
			'description': 'Auto filled',
			'type': 'any'
		},
		'value': {
			'name': 'Custom fields',
			'description': 'Auto filled',
			'type': "any"
		},
		'type_filter': {
			'name': 'Field filter',
			"default": "",
			"type": "any", // String or array
			"description": "Filter displayed custom fields by their 'type2' attribute"
		},
		'private': {
			ignore: true,
			type: 'boolean'
		},
		'sub_app': {
			'name': 'sub app name',
			'type': "string",
			'description': "Name of sub application"
		},
		// Allow onchange so you can put handlers on the sub-widgets
		'onchange': {
			"name": "onchange",
			"type": "string",
			"default": et2_no_init,
			"description": "JS code which is executed when the value changes."
		},
		// Allow changing the field prefix.  Normally it's the constant but importexport filter changes it.
		"prefix": {
			name: "prefix",
			type: "string",
			default: "#",
			description: "Custom prefix for custom fields.  Default #"
		}
	};

	public static readonly legacyOptions = ["type_filter","private", "fields"]; // Field restriction & private done server-side

	public static readonly PREFIX = '#';

	public static readonly DEFAULT_ID = "custom_fields";
	private tbody: JQuery;
	private table: JQuery;
	private rows = {};
	widgets = {};
	private detachedNodes = [];

	constructor(_parent?, _attrs? : WidgetConfig, _child? : object)
	{
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_customfields_list._attributes, _child || {}));

		// Some apps (infolog edit) don't give ID, so assign one to get settings
		if(!this.id)
		{
			this.id = _attrs.id = et2_customfields_list.DEFAULT_ID;

			// Add all attributes hidden in the content arrays to the attributes
			// parameter
			this.transformAttributes(_attrs);

			// Create a local copy of the options object
			this.options = et2_cloneObject(_attrs);
		}

		// Create the table body and the table
		this.tbody = jQuery(document.createElement("tbody"));
		this.table = jQuery(document.createElement("table"))
			.addClass("et2_grid et2_customfield_list");
		this.table.append(this.tbody);

		if(!this.options.fields) this.options.fields = {};
		if(typeof this.options.fields === 'string')
		{
			const fields = this.options.fields.split(',');
			this.options.fields = {};
			for(var i = 0; i < fields.length; i++)
			{
				this.options.fields[fields[i]] = true;
			}
		}

		if(this.options.type_filter && typeof this.options.type_filter == "string")
		{
			this.options.type_filter = this.options.type_filter.split(",");
		}
		if(this.options.type_filter)
		{
			const already_filtered = !jQuery.isEmptyObject(this.options.fields);
			for(let field_name in this.options.customfields)
			{
				// Already excluded?
				if(already_filtered && !this.options.fields[field_name]) continue;

				if(!this.options.customfields[field_name].type2 || this.options.customfields[field_name].type2.length == 0 ||
					this.options.customfields[field_name].type2 == '0')
				{
					// No restrictions
					this.options.fields[field_name] = true;
					continue;
				}
				const types = typeof this.options.customfields[field_name].type2 == 'string' ? this.options.customfields[field_name].type2.split(",") : this.options.customfields[field_name].type2;
				this.options.fields[field_name] = false;
				for(var i = 0; i < types.length; i++)
				{
					if(jQuery.inArray(types[i],this.options.type_filter) > -1)
					{
						this.options.fields[field_name] = true;

					}
				}
			}
		}

		this.setDOMNode(this.table[0]);
	}

	destroy( )
	{
		super.destroy();
		this.rows = {};
		this.widgets = {};
		this.detachedNodes = [];
		this.tbody = null;
	}

	/**
	 * What does this do?  I don't know, but when everything is done the second
	 * time, this makes it work.  Otherwise, all custom fields are lost.
	 */
	assign( _obj)
	{
		this.loadFields();
	}

	getDOMNode( _sender)
	{

		// Check whether the _sender object exists inside the management array
		if (this.rows && _sender.id && this.rows[_sender.id]) {
			return this.rows[_sender.id];
		}
		if (this.rows && _sender.id && _sender.id.indexOf("_label") && this.rows[_sender.id.replace("_label", "")])
		{
			return jQuery(this.rows[_sender.id.replace("_label","")]).prev("td")[0] || null;
		}

		return super.getDOMNode(_sender);
	}

	/**
	 * Initialize widgets for custom fields
	 */
	loadFields( )
	{
		if(!this.options || !this.options.customfields) return;

		// Already set up - avoid duplicates in nextmatch
		if(this.getType() == 'customfields-list' && !this.isInTree() && Object.keys(this.widgets).length > 0) return;
		if(!jQuery.isEmptyObject(this.widgets)) return;

		// Check for global setting changes (visibility)
		const global_data = this.getArrayMgr("modifications").getRoot().getEntry('~custom_fields~');
		if(global_data && global_data.fields && !this.options.fields) this.options.fields = global_data.fields;

		// For checking app entries
		const apps = this.egw().link_app_list();

		// Create the table rows
		for(let field_name in this.options.customfields)
		{
			// Skip fields if we're filtering
			if(this.getType() != 'customfields-list' && !jQuery.isEmptyObject(this.options.fields) && !this.options.fields[field_name]) continue;

			const field = this.options.customfields[field_name];

			let id = this.options.prefix + field_name;

			// Need curlies around ID for nm row expansion
			if(this.id == '$row')
			{
				id = "{" + this.id + "}" + "["+this.options.prefix + field_name+"]";
			}
			else if (this.id != et2_customfields_list.DEFAULT_ID)
			{
				// Prefix this ID to avoid potential ID collisions
				id = this.id + "["+id+"]";
			}

			// Avoid creating field twice
			if(!this.rows[id])
			{

				const row = jQuery(document.createElement("tr"))
					.appendTo(this.tbody)
					.addClass(this.id + '_' + id);
				let cf = jQuery(document.createElement("td"))
					.appendTo(row);
				if(!field.type) field.type = 'text";';
				const setup_function = '_setup_' + (apps[field.type] ? 'link_entry' : field.type.replace("-", "_"));

				const attrs: any = jQuery.extend({},this.options[field_name] ? this.options[field_name] : {}, {
					'id': id,
					'statustext': field.help || '',
					'needed': field.needed,
					'readonly':( <et2_readonlysArrayMgr> this.getArrayMgr("readonlys")).isReadOnly(id, ""+this.options.readonly),
					'value': this.options.value[this.options.prefix + field_name]
				});
				// Can't have a required readonly, it will warn & be removed later, so avoid the warning
				if(attrs.readonly === true) delete attrs.needed;

				if(this.options.onchange)
				{
					attrs.onchange = this.options.onchange;
				}

				if(this[setup_function]) {
					const no_skip = this[setup_function].call(this, field_name, field, attrs);
					if(!no_skip) continue;
				}
				this.rows[id] = cf[0];

				if(this.getType() == 'customfields-list') {
					// No label, custom widget
					attrs.readonly = true;
					// Widget tooltips don't work in nextmatch because of the creation / binding separation
					// Set title to field label so browser will show something
					// Add field label & help as data attribute to row, so it can be stylied with CSS (title should be disabled)
					row.attr('title', field.label);
					row.attr('data-label', field.label);
					row.attr('data-field', field_name);
					row.attr('data-help', field.help);
					this.detachedNodes.push(row[0]);
				}
				else
				{
					// Label in first column, widget in 2nd
					jQuery(document.createElement("td"))
						.prependTo(row);
					et2_createWidget("label",{id: id + "_label", value: field.label,for: id},this);
				}

				const type = attrs.type ? attrs.type : field.type;
				// Set any additional attributes set in options, but not for widgets that pass actual options
				if(['select','radio','radiogroup','checkbox','button'].indexOf(field.type) == -1 && !jQuery.isEmptyObject(field.values))
				{
					const w = et2_registry[type];
					const wc = window.customElements.get('et2-' + type);
					if(wc)
					{
						for(let attr_name in field.values)
						{
							if(wc.getPropertyOptions(attr_name))
							{
								attrs[attr_name] = field.values[attr_name];
							}
						}
					}
					else if(typeof w !== 'undefined')
					{
						for(let attr_name in field.values)
						{
							if(typeof w._attributes[attr_name] != "undefined")
							{
								attrs[attr_name] = field.values[attr_name];
							}
						}
					}
				}
				// Create widget
				if(window.customElements.get('et2-' + type))
				{
					if (typeof attrs.needed !== 'undefined')
					{
						attrs.required = attrs.needed;
						delete attrs.needed;
					}
					if (typeof attrs.size !== 'undefined' && ['small', 'medium', 'large'].indexOf(attrs.size) === -1)
					{
						if (attrs.size > 0) attrs.width = attrs.size+'em';
						delete attrs.size;
					}
					//this.widgets[field_name] = loadWebComponent('et2-' + type, attrs, null);
					// et2_extension_customfields.getDOMNode() needs webcomponent to have ID before it can put it in
					let wc = <LitElement>loadWebComponent('et2-' + type, attrs, this);
					wc.setParent(this);
					wc.updateComplete.then(() =>
					{
						this.widgets[field_name] = wc;
					})
				}
				else if(typeof et2_registry[type] !== 'undefined')
				{
					this.widgets[field_name] = et2_createWidget(type, attrs, this);
				}
			}

			// Field is not to be shown
			if(!this.options.fields || jQuery.isEmptyObject(this.options.fields) || this.options.fields[field_name] == true)
			{
				jQuery(this.rows[field_name]).show();
			}
			else
			{
				jQuery(this.rows[field_name]).hide();
			}

		}
	}

	/**
	 * Read needed info on available custom fields from various places it's stored.
	 */
	transformAttributes( _attrs)
	{
		super.transformAttributes(_attrs);

		// Add in settings that are objects

		// Customized settings for this widget (unlikely)
		const data = this.getArrayMgr("modifications").getEntry(this.id);
		// Check for global settings
		const global_data = this.getArrayMgr("modifications").getRoot().getEntry('~custom_fields~', true);
		if(global_data)
		{
			for(let key in data)
			{
				// Don't overwrite fields with global values
				if(global_data[key] && key !== 'fields')
				{
					data[key] = jQuery.extend(true, {}, data[key], global_data[key]);
				}
			}
		}
		for(var key in data)
		{
			_attrs[key] = data[key];
		}
		for(let key in global_data)
		{
			if(typeof global_data[key] != 'undefined' && ! _attrs[key]) _attrs[key] = global_data[key];
		}

		if (this.id)
		{
			// Set the value for this element
			const contentMgr = this.getArrayMgr("content");
			if (contentMgr != null) {
				const val = contentMgr.getEntry(this.id);
				_attrs["value"] = {};
				let prefix = _attrs["prefix"] || et2_customfields_list.PREFIX;
				if (val !== null)
				{
					if(this.id.indexOf(prefix) === 0 && typeof data.fields != 'undefined' && data.fields[this.id.replace(prefix,'')] === true)
					{
						_attrs['value'][this.id] = val;
					}
					else
					{
						// Only set the values that match desired custom fields
						for(let key in val)
						{
							if(key.indexOf(prefix) === 0) {
								_attrs["value"][key] = val[key];
							}
						}
					}
					//_attrs["value"] = val;
				}
				else
				{
					// Check for custom fields directly in record
					for(var key in _attrs.customfields)
					{
						_attrs["value"][prefix + key] = contentMgr.getEntry(prefix + key);
					}
				}
			}
		}
	}

	loadFromXML( _node)
	{
		this.loadFields();

		// Load the nodes as usual
		super.loadFromXML(_node);
	}

	set_value( _value)
	{
		if(!this.options.customfields) return;
		for(let field_name in this.options.customfields)
		{
			// Skip fields if we're filtering
			if(!jQuery.isEmptyObject(this.options.fields) && !this.options.fields[field_name]) continue;

			// Make sure widget is created, and has the needed function
			if(!this.widgets[field_name] || !this.widgets[field_name].set_value) continue;
			let value = _value[this.options.prefix + field_name] ? _value[this.options.prefix  + field_name] : null;

			// Check if ID was missing
			if(value == null && this.id == et2_customfields_list.DEFAULT_ID && this.getArrayMgr("content").getEntry(this.options.prefix + field_name))
			{
				value = this.getArrayMgr("content").getEntry(this.options.prefix + field_name);
			}

			switch(this.options.customfields[field_name].type)
			{
				case 'date':
					// Date custom fields are always in Y-m-d, which seldom matches user's preference
					// which fails when sent to date widget.  This is only used for nm rows, when possible
					// this is fixed server side
					if(value && isNaN(value))
					{
						// ToDo: value = jQuery.datepicker.parseDate("yy-mm-dd",value);
					}
					break;
			}
			this.widgets[field_name].set_value(value);
		}
	}

	/**
	 * et2_IInput so the custom field can be it's own widget.
	 */
	getValue( )
	{
		// Not using an ID means we have to grab all the widget values, and put them where server knows to look
		if(this.id != et2_customfields_list.DEFAULT_ID)
		{
			return null;
		}
		const value = {};
		for(let field_name in this.widgets)
		{
			if(this.widgets[field_name].getValue && !this.widgets[field_name].options.readonly)
			{
				value[this.options.prefix + field_name] = this.widgets[field_name].getValue();
			}
		}
		return value;
	}

	isDirty( )
	{
		let dirty = false;
		for(let field_name in this.widgets)
		{
			if(this.widgets[field_name].isDirty)
			{
				dirty = dirty || this.widgets[field_name].isDirty();
			}
		}
		return dirty;
	}

	resetDirty( )
	{
		for(let field_name in this.widgets)
		{
			if(this.widgets[field_name].resetDirty)
			{
				this.widgets[field_name].resetDirty();
			}
		}
	}

	isValid( )
	{
		// Individual customfields will handle themselves
		return true;
	}

	/**
	 * Adapt provided attributes to match options for widget
	 *
	 * rows > 1 --> textarea, with rows=rows and cols=len
	 * !rows    --> input, with size=len
	 * rows = 1 --> input, with size=len, maxlength=len
	 */
	_setup_text( field_name, field, attrs)
	{
		// No label on the widget itself
		delete (attrs.label);

		field.type = 'textbox';
		attrs.rows = field.rows > 1 ? field.rows : null;
		if(attrs.rows && attrs.rows > 0)
		{
			field.type = 'textarea';
		}

		if(field.len)
		{
			attrs.size = field.len;
			if(field.rows == 1)
			{
				attrs.maxlength = field.len;
			}
		}
		if(attrs.readonly)
		{
			field.type = 'description';
		}
		return true;
	}

	_setup_passwd( field_name, field, attrs)
	{
		// No label on the widget itself
		delete (attrs.label);
		let defaults = {
			viewable:true,
			plaintext: false,
			suggest: 16
		};
		for(let key of Object.keys(defaults))
		{
			attrs[key] = (field.values && typeof field.values[key] !== "undefined") ? field.values[key] : defaults[key];
		}

		return true;
	}

	_setup_ajax_select( field_name, field, attrs)
	{
		const attributes = ['get_rows', 'get_title', 'id_field', 'template'];
		if(field.values)
		{
			for(let i = 0; i < attributes.length; i++)
			{
				if(typeof field.values[attributes[i]] !== 'undefined')
				{
					attrs[attributes[i]] = field.values[attributes[i]];
				}
			}
		}
		return true;
	}
	_setup_float( field_name, field, attrs)
	{
		// No label on the widget itself
		delete(attrs.label);

		field.type = 'float';

		if(field.len)
		{
			attrs.size = field.len;
		}
		return true;
	}
	_setup_select( field_name, field, attrs)
	{
		// No label on the widget itself
		delete (attrs.label);

		attrs.rows = field.rows;
		if(attrs.rows > 1)
		{
			attrs.multiple = true;
		}
		// select_options are now send from server-side incl. ones defined via a file in EGroupware root
		attrs.tags = field.tags;

		return true;
	}
	_setup_select_account( field_name, field, attrs)
	{
		attrs.empty_label = egw.lang('Select');
		if(field.account_type)
		{
			attrs.account_type = field.account_type;
		}
		return this._setup_select(field_name, field, attrs);
	}

	 _setup_date(field_name, field, attrs) {
		attrs.data_format = field.values && field.values.format ? field.values.format : 'Y-m-d';
		return true;
	}
	_setup_date_time( field_name, field, attrs)
	{
		attrs.data_format = field.values && field.values.format ? field.values.format : 'Y-m-d H:i:s';
		return true;
	}
	_setup_htmlarea( field_name, field, attrs)
	{
		attrs.config = field.config ? field.config : {};
		attrs.config.toolbarStartupExpanded = false;
		if(field.len)
		{
			attrs.config.width = field.len+'px';
		}
		attrs.config.height = (((field.rows > 0 && field.rows !='undefined') ? field.rows : 5) *16) +'px';

		// We have to push the config modifications into the modifications array, or they'll
		// be overwritten by the site config from the server
		const data = this.getArrayMgr("modifications").getEntry(this.options.prefix + field_name);
		if(data) jQuery.extend(data.config, attrs.config);

		return true;
	}
	_setup_radio( field_name, field, attrs)
	{
		// 'Empty' label will be first
		delete(attrs.label);

		if(field.values && field.values[''])
		{
			attrs.label = field.values[''];
			delete field.values[''];
		}

		field.type = 'radiogroup';
		attrs.options = field.values;
		return true;
	}

	_setup_checkbox( field_name, field, attrs)
	{
	 	// Read-only checkbox is just text
		if(attrs.readonly && this.getType() !== "customfields")
		{
			attrs.ro_true = field.label;
		}
		else if (field.hasOwnProperty('ro_true'))
		{
			attrs.ro_true = field.ro_true;
		}
		if (field.hasOwnProperty('ro_false'))
		{
			attrs.ro_false = field.ro_false;
		}
		return true;
	}

	/**
	 * People set button attributes as
	 * label: javascript
	 */
	_setup_button( field_name, field, attrs)
	{
		// No label on the widget itself
		delete(attrs.label);

		attrs.label = field.label;

		if (this.getType() == 'customfields-list')
		{
			// No buttons in a list, it causes problems with detached nodes
			return false;
		}
		// Simple case, one widget for a custom field
		if(!field.values || typeof field.values != 'object' || Object.keys(field.values).length == 1)
		{
			for(let key in field.values)
			{
				attrs.label = key;
				attrs.onclick = field.values[key];
			}
			if (!attrs.label)
			{
				attrs.label = 'No "label=onclick" in values!';
				attrs.onclick = function(){ return false; };
			}
			return !attrs.readonly;
		}
		else
		{
			// Complicated case, a single custom field you get multiple widgets
			// Handle it all here, since this is the exception
			const row = jQuery('tr', this.tbody).last();
			let cf = jQuery('td', row);
			// Label in first column, widget in 2nd
			cf.text(field.label + "");
			cf = jQuery(document.createElement("td"))
				.appendTo(row);

			for(var key in field.values)
			{
				const button_attrs = jQuery.extend({}, attrs);
				button_attrs.label = key;
				button_attrs.onclick = field.values[key];
				button_attrs.id = attrs.id + '_' + key;

				// This controls where the button is placed in the DOM
				this.rows[button_attrs.id] = cf[0];

				// Do not store in the widgets list, one name for multiple widgets would cause problems
				/*this.widgets[field_name] = */ et2_createWidget(attrs.type ? attrs.type : field.type, button_attrs, this);
			}
			return false;
		}
	}
	_setup_link_entry( field_name, field, attrs)
	{
		if(field.type === 'filemanager')
		{
			return this._setup_filemanager(field_name, field, attrs);
		}
		// No label on the widget itself
		delete(attrs.label);

		attrs.type = "link-entry";
		attrs[attrs.readonly ? "app" : "only_app"] = typeof field.only_app == "undefined" ? field.type : field.only_app;
		return true;
	}

	_setup_filemanager( field_name, field, attrs)
	{
		attrs.type = 'vfs-upload';
		delete(attrs.label);

		if (this.getType() == 'customfields-list')
		{
			// No special UI needed?
			return true;
		}
		else
		{
			// Complicated case, a single custom field you get multiple widgets
			// Handle it all here, since this is the exception
			const row = jQuery('tr', this.tbody).last();
			let cf = jQuery('td', row);

			// Label in first column, widget in 2nd
			cf.text(field.label + "");
			cf = jQuery(document.createElement("td"))
				.appendTo(row);

			// Create upload widget
			let widget = this.widgets[field_name] = <et2_DOMWidget>et2_createWidget(attrs.type ? attrs.type : field.type, attrs, this);

			// This controls where the widget is placed in the DOM
			this.rows[attrs.id] = cf[0];
			jQuery(widget.getDOMNode(widget)).css('vertical-align','top');

			// Add a link to existing VFS file
			const select_attrs = jQuery.extend({},
				attrs,
				// Filemanager select
				{
					label: '',
					mode: widget.options.multiple ? 'open-multiple' : 'open',
					method: 'EGroupware\\Api\\Etemplate\\Widget\\Link::ajax_link_existing',
					method_id: attrs.path,
					button_label: egw.lang('Link')
				}, {type: 'vfs-select'});
			select_attrs.id = attrs.id + '_vfs_select';

			// This controls where the button is placed in the DOM
			this.rows[select_attrs.id] = cf[0];

			// Do not store in the widgets list, one name for multiple widgets would cause problems
			widget = <et2_DOMWidget>et2_createWidget(select_attrs.type, select_attrs, this);
			jQuery(widget.getDOMNode(widget)).css('vertical-align','top').prependTo(cf);
		}
		return false;
	}

	/**
	 * Display links in list as CF name
	 * @param field_name
	 * @param field
	 * @param attrs
	 */
	_setup_url( field_name, field, attrs)
	{
		if(this.getType() == 'customfields-list')
		{
			attrs.label = field.label;
		}

		return true;
	}

	/**
	 * Set which fields are visible, by name
	 *
	 * Note: no # prefix on the name
	 *
	 */
	set_visible( _fields)
	{
		for(let name in _fields)
		{
			if(this.rows[this.options.prefix + name])
			{
				if(_fields[name])
				{
					jQuery(this.rows[this.options.prefix+name]).show();
				}
				else
				{
					jQuery(this.rows[this.options.prefix+name]).hide();
				}
			}
			this.options.fields[name] = _fields[name];
		}
	}

	/**
	 * Code for implementing et2_IDetachedDOM
	 */
	getDetachedAttributes(_attrs)
	{
		_attrs.push("value", "class");
	}

	getDetachedNodes()
	{
		return this.detachedNodes ? this.detachedNodes : [];
	}

	setDetachedAttributes(_nodes, _values)
	{
		// Individual widgets are detected and handled by the grid, but the interface is needed for this to happen

		// Show the row if there's a value, hide it if there is no value
		for(let i = 0; i < _nodes.length; i++)
		{
			// toggle() needs a boolean to do what we want
			const key = _nodes[i].getAttribute('data-field');
			jQuery(_nodes[i]).toggle(_values.fields[key] && _values.value[this.options.prefix + key]?true:false);
		}
	}
}
et2_register_widget(et2_customfields_list, ["customfields", "customfields-list"]);