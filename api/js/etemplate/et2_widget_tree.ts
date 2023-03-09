/**
 * EGroupware eTemplate2 - JS Tree object
 *
 * @link http://community.egroupware.org/egroupware/api/js/dhtmlxtree/docsExplorer/dhtmlxtree/
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @author Ralf Becker
 * @copyright Nathan Gray 2011
 */

/*egw:uses
	et2_core_inputWidget;
	/api/js/egw_action/egw_dragdrop_dhtmlx_tree.js;
	/api/js/dhtmlxtree/codebase/dhtmlxcommon.js;
//	using debugable and fixed source of dhtmltree instead: /api/js/dhtmlxtree/js/dhtmlXTree.js;
	/api/js/dhtmlxtree/sources/dhtmlxtree.js;
	/api/js/dhtmlxtree/sources/ext/dhtmlxtree_json.js;
//	/api/js/dhtmlxtree/sources/ext/dhtmlxtree_start.js;
*/

import {et2_register_widget, WidgetConfig} from "./et2_core_widget";
import {et2_inputWidget} from "./et2_core_inputWidget";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_no_init} from "./et2_core_common";
import {egw} from "../jsapi/egw_global";
import {egw_getAppObjectManager, egw_getObjectManager, egwActionObject} from "../egw_action/egw_action.js";
import {EGW_AO_FLAG_IS_CONTAINER} from "../egw_action/egw_action_constants.js";
import {dhtmlxtreeItemAOI} from "../egw_action/egw_dragdrop_dhtmlx_tree.js";
import {egwIsMobile} from "../egw_action/egw_action_common.js";

/* no module, but egw:uses is ignored, so adding it here commented out
import '../../../api/js/dhtmlxtree/sources/dhtmlxtree.js';
import '../../../api/js/dhtmlxtree/sources/ext/dhtmlxtree_json.js';
import '../../../api/js/dhtmlxtree/sources/ext/dhtmlxtree_start.js';
 */

/**
 * Tree widget
 *
 * For syntax of nodes supplied via sel_options or autoloading refer to Etemplate\Widget\Tree class.
 *
 * @augments et2_inputWidget
 */
export class et2_tree extends et2_inputWidget
{
	static readonly _attributes : any = {
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
			"description": "JS code which gets executed when clicks on text of a node"
		},
		"onselect": {
			"name": "onSelect",
			"type": "js",
			"default": et2_no_init,
			"description": "Javascript executed when user selects a node"
		},
		"oncheck": {
			"name": "onCheck",
			"type": "js",
			"default": et2_no_init,
			"description": "Javascript executed when user checks a node"
		},
		// onChange event is mapped depending on multiple to onCheck or onSelect
		onopenstart: {
			"name": "onOpenStart",
			"type": "js",
			"default": et2_no_init,
			"description": "Javascript function executed when user opens a node: function(_id, _widget, _hasChildren) returning true to allow opening!"
		},
		onopenend: {
			"name": "onOpenEnd",
			"type": "js",
			"default": et2_no_init,
			"description": "Javascript function executed when opening a node is finished: function(_id, _widget, _hasChildren)"
		},
		"image_path": {
			"name": "Image directory",
			"type": "string",
			"default": egw().webserverUrl + "/api/templates/default/images/dhtmlxtree/",
			"description": "Directory for tree structure images, set on server-side to 'dhtmlx' subdir of templates image-directory"
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
		},
		"std_images": {
			"name": "Standard images",
			"type": "string",
			"default": "",
			"description": "comma-separated names of icons for a leaf, closed and opend folder (default: leaf.png,folderClosed.png,folderOpen.png), images with extension get loaded from image_path, just 'image' or 'appname/image' are allowed too"
		},
		"multimarking": {
			"name": "multimarking",
			"type": "any",
			"default": false,
			"description": "Allow marking multiple nodes, default is false which means disabled multiselection, true or 'strict' activates it and 'strict' makes it strick to only same level marking"
		},
		highlighting:{
			"name": "highlighting",
			"type": "boolean",
			"default": false,
			"description": "Add highlighting class on hovered over item, highlighting is disabled by default"
		}
	};

	private input : any = null;
	private div : JQuery;
	private autoloading_url: any;
	/**
	 * Regexp used by _htmlencode
	 */
	_lt_regexp : RegExp = /</g;

	/**
	 * Constructor
	 *
	 * @memberOf et2_tree
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_tree._attributes, _child || {}));

		this.input = null;

		this.div = jQuery(document.createElement("div")).addClass("dhtmlxTree");
		this.setDOMNode(this.div[0]);
	}

	destroy() {
		if(this.input)
		{
			this.input.destructor();
		}
		this.input = null;
		super.destroy();
	}

	/**
	 * Get tree items from the sel_options data array
	 *
	 * @param {object} _attrs
	 */
	transformAttributes(_attrs) {
		super.transformAttributes(_attrs);

		// If select_options are already known, skip the rest
		if(this.options && this.options.select_options && !jQuery.isEmptyObject(this.options.select_options))
		{
			return;
		}

		let name_parts = this.id.replace(/]/g,'').split('[');

		// Try to find the options inside the "sel-options" array
		if(this.getArrayMgr("sel_options"))
		{
			// Select options tend to be defined once, at the top level, so try that first
			let content_options = this.getArrayMgr("sel_options").getRoot().getEntry(name_parts[name_parts.length-1]);

			// Try again according to ID
			if(!content_options) content_options = this.getArrayMgr("sel_options").getEntry(this.id);
			if(_attrs["select_options"] && !jQuery.isEmptyObject(_attrs["select_options"]) && content_options)
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
			let content_options = this.getArrayMgr('content').getRoot().getEntry(name_parts[name_parts.length-1]);
			// If that didn't work, check according to ID
			_attrs["select_options"] = content_options ? content_options : this.getArrayMgr('content')
				.getEntry("options-" + this.id);
		}

		// Default to an empty object
		if (_attrs["select_options"] == null)
		{
			_attrs["select_options"] = {};
		}
	}

	// overwrite default onclick to do nothing, as we install onclick via dhtmlxtree
	click(_node) {}

	createTree(widget)
	{
		widget.input = new dhtmlXTreeObject({
			parent: widget.div[0],
			width: '100%',
			height: '100%',
			image_path: widget.options.image_path,
			checkbox: widget.options.multiple
		});
		// Allow controlling icon size by CSS
		widget.input.def_img_x = "";
		widget.input.def_img_y = "";

		// to allow "," in value, eg. folder-names, IF value is specified as array
		widget.input.dlmtr = ':}-*(';

		if(widget.options.std_images)
		{
			widget.setImages.apply(widget, widget.options.std_images.split(','));
		}
		else
		{
			// calling setImages to get our png or svg default images
			widget.setImages();
		}
		// Add in the callback so we can keep the two in sync
		widget.input.AJAX_callback = function(dxmlObject) {
			widget._dhtmlxtree_json_callback(JSON.parse(dxmlObject.xmlDoc.responseText), widget.input.lastLoadedXMLId);
			// Call this in case we added some options that were already selected, but missing
			if(widget.options.multiple)
			{
				widget.set_value(widget.value);
			}
		};

		if (widget.options.autoloading)
		{
			let url = widget.options.autoloading;

			//Set escaping mode to utf8, as url in
			//autoloading needs to be utf8 encoded.
			//For instance item id with umlaut.
			widget.input.setEscapingMode('utf8');

			if (url.charAt(0) != '/' && url.substr(0,4) != 'http')
			{
				url = '/json.php?menuaction='+url;
			}
			this.autoloading_url = url;

			widget.input.setXMLAutoLoading(egw.link(url));
			widget.input.setDataMode('JSON');
		}

		if (widget.options.multimarking)
		{
			widget.input.enableMultiselection(!!widget.options.multimarking, widget.options.multimarking === 'strict');
		}
		// Enable/Disable highlighting
		widget.input.enableHighlighting(!!widget.options.highlighting);

		// if templates supplies open/close right/down arrows, show no more lines and use them instead of plus/minus
		let open = egw.image('dhtmlxtree/open');
		let close  = egw.image('dhtmlxtree/close');
		if (open && close)
		{
			widget.input.enableTreeLines(false);
			open = this._rel_url(open);
			widget.input.setImageArrays('plus', open, open, open, open, open);
			close = this._rel_url(close);
			widget.input.setImageArrays('minus', close, close, close, close, close);
		}

		this._install_handler('onBeforeCheck', function() {
			return !this.options.readonly;
		}.bind(this));
	}

	/**
	 * Install event handlers on tree
	 *
	 * @param _name
	 * @param _handler
	 */
	private _install_handler(_name, _handler)
	{
		if (typeof _handler == 'function')
		{
			if(this.input == null) this.createTree(this);
			// automatic convert onChange event to oncheck or onSelect depending on multiple is used or not
			if (_name == 'onchange') _name = this.options.multiple ? 'oncheck' : 'onselect';
			let handler = _handler;
			let widget = this;
			this.input.attachEvent(_name, function(_id){
				let args = jQuery.makeArray(arguments);
				// splice in widget as 2. parameter, 1. is new node-id, now 3. is old node id
				args.splice(1, 0, widget);
				// try to close mobile sidemenu after clicking on node
				if (egwIsMobile() && typeof args[2] == 'string') framework.toggleMenu('on');
				return handler.apply(this, args);
			});
		}
	}

	set_onchange(_handler) { this._install_handler('onchange', _handler); }
	set_onclick(_handler) { this._install_handler('onclick',  _handler); }
	set_onselect(_handler) { this._install_handler('onselect', _handler); }
	set_onopenstart(_handler) { this._install_handler('onOpenStart', _handler); }
	set_onopenend(_handler) { this._install_handler('onOpenEnd', _handler); }

	set_select_options(options)
	{
		let custom_images = false;
		this.options.select_options = options;

		if(this.input == null)
		{
			this.createTree(this);
		}

		// Structure data for category tree
		if(this.getType() == 'tree-cat')
		{
			let data = {id:0,item:[]};
			let stack = {};
			for(let key=0; key < options.length; key++)
			{
				// See if item has an icon
				if(options[key].data && typeof options[key].data.icon !== 'undefined' && options[key].data.icon)
				{
					let img = this.egw().image(options[key].data.icon, options[key].appname);
					if(img)
					{
						custom_images = true;
						options[key].im0 = options[key].im1 = options[key].im2 = img;
					}
				}
				// Item color - not working
				if(options[key].data && typeof options[key].data.color !== 'undefined' && options[key].data.color)
				{
					options[key].style = options[key].style || "" + "background-color:'"+options[key].data.color+"';";
				}

				// Tooltip
				if(options[key].description && !options[key].tooltip)
				{
					options[key].tooltip = options[key].description;
				}
				let parent_id = parseInt(options[key]['parent']);
				if(isNaN(parent_id)) parent_id = 0;
				if(!stack[parent_id]) stack[parent_id] = [];
				stack[parent_id].push(options[key]);
			}
			if(custom_images)
			{
				let path = this.input.iconURL;
				this.input.setIconPath("");
				for(let k = 0; k < this.input.imageArray.length; k++)
					this.input.imageArray[k] = path + this.input.imageArray[k];
			}
			let f = function(data, _f)
			{
				if (stack[data.id])
				{
					data.item=stack[data.id];
					for (let j=0; j<data.item.length; j++)
					{
						f(data.item[j], _f);
					}
				}
			};

			f(data, f);
			options = data;
		}
		this.input.deleteChildItems("0");
		// if no options given, but autoloading url, use that to load initial nodes
		if (typeof options.id == 'undefined' && this.input.XMLsource)
			this.input.loadJSON(this.input.XMLsource);
		else
			this.input.loadJSONObject(this._htmlencode_node(options));
	}



	/**
	 * html encoding of text of node
	 *
	 * We only do a minimal html encoding by replacing opening bracket < with &lt;
	 * as tree seems not to need more and we dont want to waste time.
	 *
	 * @param {string} _text text to encode
	 * @return {string}
	 */
	private _htmlencode(_text : string) : string
	{
		if (_text && _text.indexOf('<') >= 0)
		{
			_text = _text.replace(this._lt_regexp, '&lt;');
		}
		return _text;
	}

	/**
	 * html encoding of text of node incl. all children
	 *
	 * @param {object} _item with required attributes text, id and optional tooltip and item
	 * @return {object} encoded node
	 */
	private _htmlencode_node(_item : {text : string, item : any}) : object
	{
		_item.text = this._htmlencode(_item.text);

		if (_item.item && jQuery.isArray(_item.item))
		{
			for(let i=0; i < _item.item.length; ++i)
			{
				this._htmlencode_node(_item.item[i]);
			}
		}
		return _item;
	}

	set_value(new_value)
	{
		this.value = this._oldValue = (typeof new_value === 'string' && this.options.multiple ? new_value.split(',') : new_value);
		if(this.input == null) return;

		if (this.options.multiple)
		{
			// Clear all checked
			let checked = this.input.getAllChecked().split(this.input.dlmtr);
			for(let i = 0; i < checked.length; i++)
			{
				this.input.setCheck(checked[i], false);
			}

			// Check selected
			for(let i = 0; i < this.value.length; i++)
			{
				this.input.setCheck(this.value[i], true);
				// autoloading openning needs to be absolutely based on user interaction
				// or open flag in folder structure, therefore, We should
				// not force it to open the node
				if (!this.options.autoloading) this.input.openItem(this.value[i]);
			}
		}
		else
		{
			this.input.selectItem(this.value, false);	// false = do not trigger onSelect
			this.input.focusItem(this.value);
			this.input.openItem(this.value);
		}
	}

	/**
	 * Links actions to tree nodes
	 *
	 * @param {object} actions [ {ID: attributes..}+] as for set_actions
	 */
	_link_actions(actions)
	{
		// Get the top level element for the tree
		// Only look 1 level deep for application object manager
		let objectManager = egw_getObjectManager(this.egw().app_name(),true,1);
		let treeObj = objectManager.getObjectById(this.id);
		if (treeObj == null) {
			// Add a new container to the object manager which will hold the tree
			// objects
			treeObj = objectManager.addObject(
				new egwActionObject(this.id, objectManager, null, this._actionManager, EGW_AO_FLAG_IS_CONTAINER),
				null, EGW_AO_FLAG_IS_CONTAINER
			);
		}

		// Delete all old objects
		treeObj.clear();

		// Go over the tree parts & add links
		let action_links = this._get_action_links(actions);

		if (typeof this.options.select_options != 'undefined')
		{

			// Iterate over the options (leaves) and add action to each one
			let apply_actions = function(treeObj, option)
			{
				// Add a new action object to the object manager
				// @ts-ignore
				let obj = treeObj.addObject((typeof option.id == 'number' ? String(option.id) : option.id), new dhtmlxtreeItemAOI(this.input, option.id));
				obj.updateActionLinks(action_links);

				if(option.item && option.item.length > 0)
				{
					for(let i = 0; i < option.item.length; i++)
					{
						apply_actions.call(this, treeObj, option.item[i]);
					}
				}
			};
			apply_actions.call(this, treeObj, this.options.select_options);
		}
	}

	/**
	 * getValue, retrieves the Id of the selected Item
	 * @return string or object or null
	 */
	getValue()
	{
		if(this.input == null) return null;
		if (this.options.multiple)
		{
			let allChecked = this.input.getAllChecked().split(this.input.dlmtr);
			let allUnchecked = this.input.getAllUnchecked().split(this.input.dlmtr);
			if (this.options.autoloading)
			{

				let res = {};
				for (let i=0;i<allChecked.length;i++)
				{
					res[allChecked[i]]= {value:true};
				}
				for (let i=0;i<allUnchecked.length;i++)
				{
					res[allUnchecked[i]]= {value:false};
				}
				return res;
			}
			else
			{
				return allChecked;
			}
		}
		return this.input.getSelectedItemId();
	}

	/**
	 * getSelectedLabel, retrieves the Label of the selected Item
	 * @return string or null
	 */
	getSelectedLabel()
	{
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
	}

	/**
	 * renameItem, renames an item by id
	 *
	 * @param {string} _id ID of the node
	 * @param {string} _newItemId ID of the node
	 * @param {string} _label label to set
	 */
	renameItem(_id, _newItemId, _label)
	{
		if(this.input == null) return null;
		this.input.changeItemId(_id,_newItemId);

		// Update action
		// since the action ID has to = this.id, getObjectById() won't work
		var treeObj = (<egwActionObject><unknown>egw_getAppObjectManager()).getObjectById(this.id);
		for(var i=0; i < treeObj.children.length; i++)
		{
			if(treeObj.children[i].id == _id)
			{
				treeObj.children[i].id = _newItemId;
				if (treeObj.children[i].iface) treeObj.children[i].iface.id = _newItemId;
				break;
			}
		}

		if (typeof _label != 'undefined') this.setLabel(_newItemId, _label);
	}

	/**
	 * deleteItem, deletes an item by id
	 * @param _id ID of the node
	 * @param _selectParent select the parent node true/false
	 * @return void
	 */
	deleteItem(_id, _selectParent)
	{
		if(this.input == null) return null;
		this.input.deleteItem(_id, _selectParent);
		// Update action
		// since the action ID has to = this.id, getObjectById() won't work
		let treeObj = (<egwActionObject><unknown>egw_getAppObjectManager()).getObjectById(this.id);
		for(let i=0; i < treeObj.children.length; i++)
		{
			if(treeObj.children[i].id == _id)
			{
				treeObj.children.splice(i,1);
			}
		}
	}

	/**
	 * Updates a leaf of the tree by requesting new information from the server using the
	 * autoloading attribute.
	 *
	 * @param {string} _id ID of the node
	 * @param {Object} [data] If provided, the item is refreshed directly  with
	 *	the provided data instead of asking the server
	 * @return void
	 */
	refreshItem(_id,data)
	{
		if(this.input == null) return null;
		this.input.deleteChildItems(_id);
		this.input.setDataMode('JSON');

		/* Can't use this, it doesn't allow a callback
		this.input.refreshItem(_id);
		*/

		let self = this;
		if(typeof data != 'undefined' && data != null)
		{
			this.input.loadJSONObject(data,
				function() { self._dhtmlxtree_json_callback(data, _id);}
			);
		}
		else
		{
			this.input.loadJSON(this.egw().link(this.autoloading_url, {id: _id}),
				function(dxmlObject) {
				self._dhtmlxtree_json_callback(JSON.parse(dxmlObject.xmlDoc.responseText), _id);

				// refreshing root node causes binding actions fails in dhtmlx tree, we try to refresh the opened node
				// in order to rebind the actions again.
				if (_id == 0)
				{
					let openedId = self._oldValue.split("::")[0];
					let interval = setInterval(()=> {
						if (self.input.getOpenState(openedId))
						{
							clearInterval(interval);
							self.refreshItem(openedId);
						}
					}, 100);
				}
			}
			);
		}
	}

	/**
	 * focus the item, and scrolls it into view
	 *
	 * @param _id ID of the node
	 * @return void
	 */
	focusItem(_id)
	{
		if(this.input == null) return null;
		this.input.focusItem(_id);
	}

	/**
	 * hasChildren
	 *
	 * @param _id ID of the node
	 * @return the number of childelements
	 */
	hasChildren(_id)
	{
		if(this.input == null) return null;
		return this.input.hasChildren(_id);
	}

	/**
	 * Callback for after using dhtmlxtree's AJAX loading
	 * The tree has visually already been updated at this point, we just need
	 * to update the internal data.
	 *
	 * @param {object} new_data Fresh data for the tree
	 * @param {string} update_option_id optional If provided, only update that node (and children) with the
	 *	provided data instead of the whole thing.  Allows for partial updates.
	 * @return void
	 */
	private _dhtmlxtree_json_callback(new_data, update_option_id)
	{
		// not sure if it makes sense to try update_option_id, so far I only seen it to be -1
		let parent_id = typeof update_option_id != 'undefined' && update_option_id != -1 ? update_option_id : new_data.id;
		// find root of loaded data to merge it there
		let option = this._find_in_item(parent_id, this.options.select_options);
		// if we found it, merge it
		if (option)
		{
			jQuery.extend(option,new_data || {});
		}
		else	// else store it in root
		{
			this.options.select_options = new_data;
		}
		// Update actions by just re-setting them
		this.set_actions(this.options.actions || {});
	}

	/**
	 * Recursive search item object for given id
	 *
	 * @param {string} _id
	 * @param {object} _item
	 * @returns
	 */
	private _find_in_item(_id, _item)
	{
		if (_item && _item.id == _id)
		{
			return _item;
		}
		if (_item && typeof _item.item != 'undefined')
		{
			for(let i=0; i < _item.item.length; ++i)
			{
				let found = this._find_in_item(_id, _item.item[i]);
				if (found) return found;
			}
		}
		return null;
	}

	/**
	 * Get node data by id
	 *
	 * @param {string} _id id of node
	 * @return {object} object with attributes id, im0-2, text, tooltip, ... as set via select_options or autoload url
	 */
	getNode(_id)
	{
		return this._find_in_item(_id, this.options.select_options);
	}

	/**
	 * Sets label of an item by id
	 *
	 * @param _id ID of the node
	 * @param _label label to set
	 * @param _tooltip new tooltip, default is previous set tooltip
	 * @return void
	 */
	setLabel(_id, _label, _tooltip?)
	{
		if(this.input == null) return null;
		let tooltip = _tooltip || (this.getNode(_id) && this.getNode(_id).tooltip ?
			this.getNode(_id).tooltip : "");
		this.input.setItemText(_id, this._htmlencode(_label), tooltip);
	}

	/**
	 * Sets a style for an item by id
	 *
	 * @param {string} _id ID of node
	 * @param {string} _style style to set
	 * @return void
	 */
	setStyle(_id, _style)
	{
		if(this.input == null) return null;
		this.input.setItemStyle(_id, _style);
	}

	/**
	 * getLabel, gets the Label of of an item by id
	 * @param _id ID of the node
	 * @return _label
	 */
	getLabel(_id)
	{
		if(this.input == null) return null;
		return this.input.getItemText(_id);
	}

	/**
	 * getSelectedNode, retrieves the full node of the selected Item
	 * @return string or null
	 */
	getSelectedNode()
	{
		if(this.input == null) return null;
		// no support for multiple selections
		// as there is no get Method to return the full selected node, we use this
		return this.options.multiple ? null : this.input._selected[0];
	}

	/**
	 * getTreeNodeOpenItems
	 *
	 * @param {string} _nodeID the nodeID where to start from (initial node)
	 * @param {string} mode the mode to run in: "forced" fakes the initial node openState to be open
	 * @return {object} structured array of node ids: array(message-ids)
	 */
	getTreeNodeOpenItems(_nodeID : string, mode? : string)
	{
		if(this.input == null) return null;
		let z = this.input.getSubItems(_nodeID).split(this.input.dlmtr);
		let oS;
		let PoS;
		let rv;
		let returnValue = [_nodeID];
		let modetorun = "none";
		if (mode) { modetorun = mode; }
		PoS = this.input.getOpenState(_nodeID);
		if (modetorun == "forced") PoS = 1;
		if (PoS == 1) {
			for(let i=0;i<z.length;i++) {
				oS = this.input.getOpenState(z[i]);
				//alert(z[i]+' OpenState:'+oS);
				if (oS == -1) { returnValue.push(z[i]); }
				if (oS == 0) { returnValue.push(z[i]); }
				if (oS == 1) {
					//alert("got here")
					rv = this.getTreeNodeOpenItems(z[i]);
					//returnValue.concat(rv); // not working as expected; the following does
					for(let j=0;j<rv.length;j++) {returnValue.push(rv[j]);}
				}
			}
		}
		//alert(returnValue.join('#,#'));
		return returnValue;
	}

	/**
	 * Fetch user-data stored in specified node under given name
	 *
	 * User-data need to be stored in json as follows:
	 *
	 * {"id": "node-id", "im0": ..., "userdata": [{"name": "user-name", "content": "user-value"},...]}
	 *
	 * In above example getUserData("node-id", "user-name") will return "user-value"
	 *
	 * @param _nodeId
	 * @param _name
	 * @returns
	 */
	getUserData(_nodeId, _name)
	{
		if(this.input == null) return null;
		return this.input.getUserData(_nodeId, _name);
	}

	/**
	 * Stores / updates user-data in specified node and name
	 *
	 * @param _nodeId
	 * @param _name
	 * @param _value
	 * @returns
	 */
	setUserData(_nodeId, _name, _value)
	{
		if(this.input == null) return null;
		return this.input.setUserData(_nodeId, _name, _value);
	}

	/**
	 * Query nodes open state and optinal change it
	 *
	 * @param _id node-id
	 * @param _open specify to change true: open, false: close, everything else toggle
	 * @returns true if open, false if closed
	 */
	openItem(_id, _open?)
	{
		if (this.input == null) return null;

		let is_open = this.input.getOpenState(_id) == 1;

		if (typeof _open != 'undefined' && is_open !== _open)
		{
			if(is_open)
			{
				this.input.closeItem(_id);
			}
			else
			{
				this.input.openItem(_id);
			}
		}
		return is_open;
	}

	/**
	 * reSelectItem, reselects an item by id
	 * @param _id ID of the node
	 */
	reSelectItem(_id)
	{
		if (this.input == null) return null;
		this.input.selectItem(_id,false,false);
	}

	/**
	 * Set images for a specific node or all new nodes (default)
	 *
	 * If images contain an extension eg. "leaf" they are asumed to be in image path (/phpgwapi/templates/default/images/dhtmlxtree/).
	 * Otherwise they get searched via egw.image() in current app, phpgwapi or can be specified as "app/image".
	 *
	 * @param {string} _leaf leaf image, default "leaf"
	 * @param {string} _closed closed folder image, default "folderClosed"
	 * @param {string} _open opened folder image, default "folderOpen"
	 * @param {string} _id if not given, standard images for new nodes are set
	 */
	setImages(_leaf? : string, _closed? : string, _open? : string, _id? : string)
	{
		// NOTE: The image order for open/closed as documented in dhtmltree is backwards
		let images = [_leaf || 'dhtmlxtree/leaf', _open || 'dhtmlxtree/folderOpen', _closed || 'dhtmlxtree/folderClosed'];
		let image_extensions = /\.(gif|png|jpe?g|svg)/i;
		for(let i = 0; i < 3; ++i)
		{
			let image = images[i];
			if (!image.match(image_extensions))
			{
				images[i] = this._rel_url(this.egw().image(image) || image);
			}
		}
		if (typeof _id == 'undefined')
		{
			this.input.setStdImages.apply(this.input, images);
		}
		else
		{
			images.unshift(_id);
			this.input.setItemImage2.apply(this.input, images);
		}
	}

	/**
	 * Set state of node incl. it's children
	 *
	 * @param {string} _id id of node
	 * @param {boolean|string} _state or "toggle" to toggle state
	 */
	setSubChecked(_id, _state)
	{
		if (_state === "toggle") _state = !this.input.isItemChecked(_id);
		this.input.setSubChecked(_id, _state);
	}

	/**
	 * Get URL relative to image_path option
	 *
	 * Both URL start with EGroupware webserverUrl and image_path gets allways appended to images by tree.
	 *
	 * @param {string} _url
	 * @return {string} relativ url
	 */
	private _rel_url(_url)
	{
		let path_parts = this.options.image_path.split(this.egw().webserverUrl);
		path_parts = path_parts[1].split('/');
		let url_parts = _url.split(this.egw().webserverUrl);
		url_parts = url_parts[1].split('/');

		for(let i=0; i < path_parts.length; ++i)
		{
			if (path_parts[i] != url_parts[i])
			{
				while(++i < path_parts.length) url_parts.unshift('..');
				break;
			}
			url_parts.shift();
		}
		return url_parts.join('/');
	}
}
et2_register_widget(et2_tree, ["tree","tree-cat"]);