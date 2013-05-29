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
	/phpgwapi/js/egw_action/egw_dragdrop_dhtmlx_tree.js;
	/phpgwapi/js/dhtmlxtree/js/dhtmlXCommon.js;
//	using debugable and fixed source of dhtmltree instead: /phpgwapi/js/dhtmlxtree/js/dhtmlXTree.js;
	/phpgwapi/js/dhtmlxtree/dhtmlxTree/sources/dhtmlxtree.js;
	/phpgwapi/js/dhtmlxtree/dhtmlxTree/sources/ext/dhtmlxtree_json.js;
//	/phpgwapi/js/dhtmlxtree/dhtmlxTree/sources/ext/dhtmlxtree_start.js;
*/

/**
 * Tree widget
 * 
 * For syntax of nodes supplied via sel_optons or autoloading refer to etemplate_widget_tree class.
 * 
 * @augments et2_inputWidget
 */
var et2_tree = et2_inputWidget.extend(
{
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
		},
		"actions": {
			"name": "Actions array",
			"type": "any",
			"default": et2_no_init,
			"description": "List of egw actions that can be done on the tree.  This includes context menu, drag and drop.  TODO: Link to action documentation"
		},
		"autoloading": {
			"name": "Autoloading",
			"type": "string",
			"default": "",
			"description": "JSON URL or menuaction to be called for nodes marked with child=1, but not having children, GET parameter selected contains node-id"
		}
	},

	/**
	 * Constructor
	 * 
	 * @memberOf et2_tree
	 */
	init: function() {
		this._super.apply(this, arguments);

		this.input = null;

		this.div = $j(document.createElement("div")).addClass("dhtmlxTree");
		this.setDOMNode(this.div[0]);
	},

	destroy: function() {
		if(this.input)
		{
			this.input.destructor();
		}
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
				.getEntry("options-" + this.id);
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
		if (widget.options.autoloading)
		{
			var url = widget.options.autoloading;
			if (url.charAt(0) != '/' && url.substr(0,4) != 'http')
			{
				url = '/json.php?menuaction='+url;
			}
			if (url.charAt(0) == '/') url = egw.webserverUrl+url;
			this.autoloading_url = url;
			widget.input.setXMLAutoLoading(url);
			widget.input.setDataMode('JSON');
		}
	},

	set_select_options: function(options) {

		var custom_images = false;
		this.options.select_options = options;

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
				this.autoloading_url = url;
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
			};
			f(data,f);
			options = data;
		}
		// if no options given, but autoloading url, use that to load initial nodes
		if (typeof options.id == 'undefined' && this.input.XMLsource)
			this.input.loadJSON(this.input.XMLsource);
		else
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
			this.input.openItem(this.value);
		}
	},

	/**
	 * Set Actions on the tree
	 *
	 * Each action is defined as an object:
	 * move: {
	 *	type: "drop",
	 *	acceptedTypes: "mail",
	 *	icon:	"move",
	 *	caption:	"Move to"
	 *	onExecute:	javascript:mail_move"
	 * }
	 *
	 * This will turn all the tree nodes into drop targets.  When "mail" drag types are dropped,
	 * the global function mail_move(egwAction action, egwActionObject sender) will be called.  The ID of the
	 * dragged "mail" will be in sender.id, some information about the sender will be in sender.context.
	 *
	 * @param Array[ {ID, attributes..}+] array of egw action information - this is different from what is passed in to
	 *	set_actions, which takes a more human-friendly Object map.
	 */
	_link_actions: function(parsed_actions) {

		// Get the top level element for the tree
		var objectManager = egw_getAppObjectManager(true);
		var treeObj = objectManager.getObjectById(this.id);
		if (treeObj == null) {
			// Add a new container to the object manager which will hold the tree
			// objects
			treeObj = objectManager.addObject(this.id, null, EGW_AO_FLAG_IS_CONTAINER);
		}

		// Delete all old objects
		treeObj.clear();

		// Go over the tree parts & add links
		var action_links = [];
		for(var i = 0; i < parsed_actions.length; i++)
		{
			action_links.push(parsed_actions[i].id);
		}
		if (typeof this.options.select_options != 'undefined')
		{

			// Iterate over the options (leaves) and add action to each one
			var apply_actions = function(treeObj, option)
			{
				// Add a new action object to the object manager
				var obj = treeObj.addObject(this.id, new dhtmlxtreeItemAOI(this.input, option.id));
                                obj.updateActionLinks(action_links);

				if(option.item && option.item.length > 0)
				{
					for(var i = 0; i < option.item.length; i++)
					{
						apply_actions.call(this, treeObj, option.item[i]);
					}
				}
			};
			apply_actions.call(this, treeObj, this.options.select_options);
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
	 * renameItem, renames an item by id
	 * @param _id ID of the node
	 * @param _newid ID of the node
	 * @param _label label to set
	 * @return void
	 */
	renameItem: function(_id, _newItemId, _label) {
		if(this.input == null) return null;
		this.input.changeItemId(_id,_newItemId);

		// Update action
		// since the action ID has to = this.id, getObjectById() won't work
		var treeObj = egw_getAppObjectManager().getObjectById(this.id);
		for(var i=0; i < treeObj.children.length; i++)
		{
			if(treeObj.children[i].iface && treeObj.children[i].iface.id == _id)
			{
				treeObj.children[i].iface.id = _newItemId;
			}
		}
		
		if (typeof _label != 'undefined') this.input.setItemText(_newItemId,_label);
	},

	/**
	 * deleteItem, deletes an item by id
	 * @param _id ID of the node
	 * @param _selectParent select the parent node true/false
	 * @return void
	 */
	deleteItem: function(_id, _selectParent) {
		if(this.input == null) return null;
		this.input.deleteItem(_id, _selectParent);
		// Update action
		// since the action ID has to = this.id, getObjectById() won't work
		var treeObj = egw_getAppObjectManager().getObjectById(this.id);
		for(var i=0; i < treeObj.children.length; i++)
		{
			if(treeObj.children[i].iface && treeObj.children[i].iface.id == _id)
			{
				delete treeObj.children[i].iface.id;
				delete treeObj.children[i].iface.node;
			}
		}
	},

	/**
	 * Updates a leaf of the tree by requesting new information from the server using the
	 * autoloading attribute.
	 *
	 * @param _id ID of the node
	 * @return void
	 */
	refreshItem: function(_id) {
		if(this.input == null) return null;
		this.input.deleteChildItems(_id);
		this.input.setDataMode('JSON');

		/* Can't use this, it doesn't allow a callback
		this.input.refreshItem(_id);
		*/

		var self = this;
		this.input.loadJSON(this.egw().link(this.autoloading_url, {id: _id}), 
			function() { self._dhtmlxtree_json_callback(JSON.parse(this.response), _id);}
		);
	},

	/**
	 * Callback for after using dhtmlxtree's AJAX loading
	 * The tree has visually already been updated at this point, we just need
	 * to update the internal data.
	 *
	 * @param Object new_data Fresh data for the tree
	 * @param Object update_option_id optional If provided, only update that node (and children) with the
	 *	provided data instead of the whole thing.  Allows for partial updates.
	 * @return void
	 */
	_dhtmlxtree_json_callback: function(new_data, update_option_id) {
		// Find the option to update
		var option = this.options.select_options;
		if(typeof update_option_id != "undefined")
		{
			var queue = []
			do
			{
				for(var i in option.item)
				{
					queue.push(option.item[i]);
				}
				option = queue.pop();
			}
			while(option.id != update_option_id && queue.length > 0)
		}

		// Update select options
		jQuery.extend(option,new_data || {});
		
		// Update actions by just re-setting them
		this.set_actions(this.options.actions);
	},

	/**
	 * setLabel, sets the Label of of an item by id
	 * @param _id ID of the node
	 * @param _label label to set
	 * @return void
	 */
	setLabel: function(_id, _label) {
		if(this.input == null) return null;
		this.input.setItemText(_id,_label);
	},

	/**
	 * getLabel, gets the Label of of an item by id
	 * @param _id ID of the node
	 * @return _label
	 */
	getLabel: function(_id) {
		if(this.input == null) return null;
		return this.input.getItemText(_id);
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
	},

	/**
	 * getTreeNodeOpenItems
	 * 
	 * @param _nodeID, the nodeID where to start from (initial node)
	 * @param mode, the mode to run in: forced fakes the initial node openState to be open
	 * @return structured array of node ids: array(message-ids)
	 */
	getTreeNodeOpenItems: function (_nodeID, mode) {
		if(this.input == null) return null;
		var z = this.input.getSubItems(_nodeID).split(",");
		var oS;
		var PoS;
		var rv;
		var returnValue = [_nodeID];
		var modetorun = "none";
		if (mode) { modetorun = mode; }
		PoS = this.input.getOpenState(_nodeID);
		if (modetorun == "forced") PoS = 1;
		if (PoS == 1) {
			for(var i=0;i<z.length;i++) {
				oS = this.input.getOpenState(z[i]);
				//alert(z[i]+' OpenState:'+oS);
				if (oS == -1) { returnValue.push(z[i]); }
				if (oS == 0) { returnValue.push(z[i]); }
				if (oS == 1) {
					//alert("got here")
					rv = this.getTreeNodeOpenItems(z[i]);
					//returnValue.concat(rv); // not working as expected; the following does
					for(var j=0;j<rv.length;j++) {returnValue.push(rv[j]);}
				}		
			}
		}
		//alert(returnValue.join('#,#'));
		return returnValue;
	}
});
et2_register_widget(et2_tree, ["tree","tree-cat"]);

