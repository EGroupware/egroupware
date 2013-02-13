/**
 * EGroupware eTemplate2 - JS Tree object
 *
 * @link http://community.egroupware.org/egroupware/phpgwapi/js/dhtmlxtree/docsExplorer/dhtmlxtree/
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @author Ralf Becker
 * @copyright Nathan Gray 2011
 * @version $Id$
 */

"use strict";

/*egw:uses
        et2_core_inputWidget;
	/phpgwapi/js/dhtmlxtree/js/dhtmlXCommon.js;
//	using debugable and fixed source of dhtmltree instead: /phpgwapi/js/dhtmlxtree/js/dhtmlXTree.js;
	/phpgwapi/js/dhtmlxtree/dhtmlxTree/sources/dhtmlxtree.js;
	/phpgwapi/js/dhtmlxtree/dhtmlxTree/sources/ext/dhtmlxtree_json.js;
//	/phpgwapi/js/dhtmlxtree/dhtmlxTree/sources/ext/dhtmlxtree_start.js;
*/

var et2_tree = et2_inputWidget.extend({

	attributes: {
		"multiple": {
			"name": "multiple",
			"type": "boolean",
			"default": false,
			"description": "Allow selecting multiple options"
		},
		"select_options": {
			"type": "any",
			"name": "Select options",
			"default": {},
			"description": "Used to set the tree options."
		},
		"onclick": {
			"name": "onClick",
			"type": "string",
			"default": "",
			"description": "JS code which gets executed when clicks on text of a node"
		},
		"onselect": {
			"name": "onSelect",
			"type": "string",
			"default": "",
			"description": "Javascript executed when user selects a node"
		},
		"oncheck": {
			"name": "onCheck",
			"type": "string",
			"default": "",
			"description": "Javascript executed when user checks a node"
		},
		// onChange event is mapped depending on multiple to onCheck or onSelect
		"image_path": {
			"name": "Image directory",
			"type": "string",
			"default": this.egw().webserverUrl + "/phpgwapi/templates/default/images/dhtmlxtree/",
			"description": "Directory for tree structure images"
		},
		"value": {
			"type": "any",
			"default": {}
		}
	},

	init: function() {
		this._super.apply(this, arguments);

		this.input = null;

		this.div = $j(document.createElement("div")).addClass("dhtmlxTree");
		this.setDOMNode(this.div[0]);
	},

	destroy: function() {
		this.input.destructor();
		this.input = null;
		this._super.apply(this, arguments);
	},

	/**
	 * Get tree items from the sel_options data array
	 */
	transformAttributes: function(_attrs) {
		this._super.apply(this, arguments);

		// If select_options are already known, skip the rest
		if(this.options && this.options.select_options && !jQuery.isEmptyObject(this.options.select_options))
		{
			return;
		}

		var name_parts = this.id.replace(/]/g,'').split('[');

		// Try to find the options inside the "sel-options" array
		if(this.getArrayMgr("sel_options"))
		{
			// Select options tend to be defined once, at the top level, so try that first
			var content_options = this.getArrayMgr("sel_options").getRoot().getEntry(name_parts[name_parts.length-1]);

			// Try again according to ID
			if(!content_options) content_options = this.getArrayMgr("sel_options").getEntry(this.id);
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

	// overwrite default onclick to do nothing, as we install onclick via dhtmlxtree
	onclick: function(_node) {},

	createTree: function(widget) {
			widget.input = new dhtmlXTreeObject({
				parent:		widget.div[0],
				width:		'100%',
				height:		'100%',
				image_path:	widget.options.image_path,
				checkbox:	widget.options.multiple,
			});
			// attach all event handlers (attributs starting with "on"), if they are set
			for(var name in widget.options)
			{
				if (name.substr(0,2) == 'on' && widget.options[name])
				{
					// automatic convert onChange event to oncheck or onSelect depending on multiple is used or not
					if (name == 'onchange') name = widget.options.multiple ? 'oncheck' : 'onselect';
					widget.input.attachEvent(widget.attributes[name].name, function(_args){
						var _widget = widget;	// closure to pass in et2 widget (1. param of event handler)
						// use widget attributes to pass arguments and name of event to handler
						_widget.event_args = arguments;
						_widget.event_name = this.callEvent.arguments[0].substr(3);
						var _js = _widget.options[_widget.event_name] || _widget.options.onchange;
						(et2_compileLegacyJS(_js, _widget, this))();
						delete _widget.event_args;
						delete _widget.event_name;
					});
					
				}
			}
	},

	set_select_options: function(options) {

		var custom_images = false;

		if(this.input == null)
		{
			this.createTree(this);
		}

		// Structure data for category tree
		if(this._type == 'tree-cat' && !jQuery.isArray(options)) {
			var data = {id:0,item:[]};
			var stack = [];
			for(var key in options)
			{
				// See if item has an icon
				if(options[key].data && typeof options[key].data.icon !== 'undefined' && options[key].data.icon) 
				{
					var img = this.egw().image(options[key].data.icon, options[key].appname);
					if(img) 
					{
						custom_images = true;
						options[key].im0 = options[key].im1 = options[key].im2 = img;
					}
				}
				// Item color - not working
				if(options[key].data && typeof options[key].data.color !== 'undefined' && options[key].data.color)
				{
					options[key].style += "background-color ='"+options[key].data.color+"';";
				} 

				// Tooltip
				if(options[key].description && !options[key].tooltip)
				{
					options[key].tooltip = options[key].description;
				}

				var parent_id = parseInt(options[key]['parent']);
				if(isNaN(parent_id)) parent_id = 0;
				if(!stack[parent_id]) stack[parent_id] = [];
				stack[parent_id].push(options[key]);
			}
			if(custom_images)
			{
				var path = this.input.iconURL;
				this.input.setIconPath("");
				for(var k = 0; k < this.input.imageArray.length; k++)
					this.input.imageArray[k] = path + this.input.imageArray[k];
			}
			var f=function(data,f) {
				if (stack[data.id])
				{
					data.item=stack[data.id];
					for (var j=0; j<data.item.length; j++)
					{
						f(data.item[j],f);
					}
				}
			}
			f(data,f);
			options = data;
		}

		this.input.loadJSONObject(options);
	},

	set_value: function(new_value) {
		this.value = this._oldValue = (typeof new_value === 'string' && this.options.multiple ? new_value.split(',') : new_value);
		if(this.input == null) return;
		
		if (this.options.multiple)
		{
			// Clear all checked
			var checked = this.input.getAllChecked().split(this.input.dlmtr);
			for(var i = 0; i < checked.length; i++)
			{
				this.input.setCheck(checked[i], false);
			}
	
			// Check selected
			for(var i = 0; i < this.value.length; i++)
			{
				this.input.setCheck(this.value[i], true);
				this.input.openItem(this.value[i]);
			}
		}
		else
		{
			this.input.selectItem(this.value, false);	// false = do not trigger onSelect
		}
	},

	/**
	 * getValue, retrieves the Id of the selected Item
	 * @return string or object or null
	 */
	getValue: function() {
		if(this.input == null) return null;
		return this.options.multiple ? this.input.getAllChecked().split(this.input.dlmtr) : this.input.getSelectedItemId();
	},

	/**
	 * getSelectedLabel, retrieves the Label of the selected Item
	 * @return string or null
	 */
	getSelectedLabel: function() {
		if(this.input == null) return null;
		if (this.options.multiple)
		{
			/*
			var out = [];
			var checked = this.input.getAllChecked().split(this.input.dlmtr);
			for(var i = 0; i < checked.length; i++)
			{
				out.push(this.input.getItemText(checked[i]));
			}
			return out;
			*/
			return null; // not supported yet
		}
		else
		{
			return this.input.getSelectedItemText();
		}
	},

	/**
	 * getSelectedNode, retrieves the full node of the selected Item
	 * @return string or null
	 */
	getSelectedNode: function() {
		if(this.input == null) return null;
		// no support for multiple selections
		// as there is no get Method to return the full selected node, we use this
		return this.options.multiple ? null : this.input._selected[0];
	}
});
et2_register_widget(et2_tree, ["tree","tree-cat"]);

