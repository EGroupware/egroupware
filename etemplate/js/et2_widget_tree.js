/**
 * eGroupWare eTemplate2 - JS Tree object
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
        et2_core_inputWidget;
	/phpgwapi/js/dhtmlxtree/js/dhtmlXCommon.js;
	/phpgwapi/js/dhtmlxtree/js/dhtmlXTree.js;
	/phpgwapi/js/dhtmlxtree/dhtmlxTree/codebase/ext/dhtmlxtree_json.js;
	/phpgwapi/js/dhtmlxtree/dhtmlxTree/sources/ext/dhtmlxtree_start.js;
*/

var et2_tree = et2_baseWidget.extend({

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
		"onnodeselect": {
			"name": "onNodeSelect",
			"type": "string",
			"default": "",
			"description": "Javascript executed when user selects a node"
		},
		"oncheck": {
			"name": "onNodeSelect",
			"type": "string",
			"default": "",
			"description": "Javascript executed when user checks a node"
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

	createTree: function(widget) {
this.egw().debug("log","ID",widget.div.attr("id"));
this.egw().debug("log",widget.div[0].parentElement);
window = this.egw().window;
			widget.input = new dhtmlXTreeObject({
				parent:		widget.div[0],
				width:		'100%',
				height:		'100%',
				checkbox:	(widget.options.oncheck),
				onCheck:	widget.options.oncheck,
		//		multiselect:	widget.options.multiple // Documented, but not available
			});

	},

	set_select_options: function(options) {
		if(this.input !== null)
		{
			this.input.loadJSArray(options);
			return;
		}

		var xmp = jQuery(document.createElement('ul')).attr("container",true).appendTo(this.div);
		for(var key in options)
		{
			var name = (!this.options.no_lang) ? options[key][2] : this.egw().lang(options[key].name ? options[key].name : options[key][2]);
			var item = jQuery(document.createElement('li'))
				.attr("id", options[key][0])
				.text( name)
				.appendTo(xmp);
		}
		this.input = dhtmlXTreeFromHTML(this.div[0]);

	}
});
et2_register_widget(et2_tree, ["tree","tree-cat"]);

