/**

 * EGroupware eTemplate2 - JS Ajax select / auto complete object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2012
 */

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	et2_core_inputWidget;
	et2_core_valueWidget;
*/

import {et2_register_widget, WidgetConfig} from "./et2_core_widget";
import {et2_inputWidget} from "./et2_core_inputWidget";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_valueWidget} from "./et2_core_valueWidget";
import {et2_selectbox} from "./et2_widget_selectbox";
import {et2_IDetachedDOM} from "./et2_core_interfaces";

/**
 * Using AJAX, this widget allows a type-ahead find similar to a ComboBox, where as the user enters information,
 * a drop-down box is populated with the n closest matches.  If the user clicks on an item in the drop-down, that
 * value is selected.
 * n is the maximum number of results set in the user's preferences.
 * The user is restricted to selecting values in the list.
 * This widget can get data from any function that can provide data to a nextmatch widget.
 * @augments et2_inputWidget
 */
export class et2_ajaxSelect extends et2_inputWidget
{
	static readonly _attributes : any = {
		'get_rows': {
			"name": "Data source",
			"type": "any",
			"default": "",
			"description": "Function to get search results, either a javascript function or server-side."
		},
		'get_title': {
			"name": "Title function",
			"type": "any",
			"default": "",
			"description": "Function to get title for selected entry.  Used when closed, and if no template is given."
		},
		'id_field': {
			"name": "Result ID field",
			"type": "string",
			"default": "value",
			"description": "Which key in result sub-array to look for row ID.  If omitted, the key for the row will be used."
		},
		'template': {
			"name": "Row template",
			"type": "string",
			"default": "",
			"description": "ID of the template to use to display rows.  If omitted, title will be shown for each result."
		},
		'filter': {
			"name": "Filter",
			"type": "string",
			"default": "",
			"description": "Apply filter to search results.  Same as nextmatch."
		},
		'filter2': {
			"name": "Filter 2",
			"type": "string",
			"default": "",
			"description": "Apply filter to search results.  Same as nextmatch."
		},
		'link': {
			"name": "Read only link",
			"type": "boolean",
			"default": "true",
			"description": "If readonly, widget will be text.  If link is set, widget will be a link."
		},

		// Pass by code only
		'values': {
			"name": "Values",
			"type": "any",
			"default": {},
			"description": "Specify the available options.  Use this, or Data source."
		}
	};
	private input: JQuery = null;
	/**
	 * Constructor
	 *
	 * @memberOf et2_ajaxSelect
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_ajaxSelect._attributes, _child || {}));
		
		if(typeof _attrs.get_rows == 'string')
		{
			_attrs.get_rows = this.egw().link('/index.php', {
				menuaction: this.options.get_rows
			})
		}
		this.createInputWidget();

		this.input = null;

		this.createInputWidget();
	}

	createInputWidget() {
		this.input = jQuery(document.createElement("input"));

		this.input.addClass("et2_textbox");

		this.setDOMNode(this.input[0]);

		let widget = this;
		this.input.autocomplete({
			delay: 100,
			source: this.options.get_rows ?
				this.options.get_rows :
				et2_selectbox.find_select_options(this, this.options.values),
			select: function(event, ui) {
				widget.value = ui.item[widget.options.id_field];
				if(widget.options.get_title)
				{
					if(typeof widget.options.get_title == 'function')
					{
						widget.input.val(widget.options.get_title.call(widget.value));
					}
					else if (typeof widget.options.get_title == 'string')
					{
						// TODO: Server side callback
					}
				}
				else
				{
					widget.input.val(ui.item.label);
				}
				// Prevent default action of setting field to the value
				return false;
			}
		});
	}

	getValue()
	{
		if(this.options.blur && this.input.val() == this.options.blur) return "";
		return this.value;
	}

	set_value(_value)
	{
		this.value = _value;
		if(this.input.autocomplete('instance'))
		{
			let source = this.input.autocomplete('option','source');
			if(typeof source == 'object')
			{
				for(let i in source)
				{
					if(typeof source[i].value != 'undefined' && typeof source[i].label != 'undefined' &&  source[i].value === _value)
					{
						this.input.val(source[i].label)
					}
					else if (typeof source[i] == 'string')
					{
						this.input.val(source[_value]);
						break;
					}
				}
			}
			else if(typeof source == 'function')
			{
				// TODO
			}
		}
	}

	set_blur(_value)
	{
		if(_value) {
			this.input.attr("placeholder", _value + "");	// HTML5
			if(!this.input[0]["placeholder"]) {
				// Not HTML5
				if(this.input.val() == "") this.input.val(this.options.blur);
				this.input.focus(this,function(e) {
					if(e.data.input.val() == e.data.options.blur) e.data.input.val("");
				}).blur(this, function(e) {
					if(e.data.input.val() == "") e.data.input.val(e.data.options.blur);
				});
			}
		} else {
			this.input.removeAttr("placeholder");
		}
	}
}
et2_register_widget(et2_ajaxSelect, ["ajax_select"]);

/**
* et2_textbox_ro is the dummy readonly implementation of the textbox.
* @augments et2_valueWidget
*/
export class et2_ajaxSelect_ro extends et2_valueWidget implements et2_IDetachedDOM
{
	/**
	 * Ignore all more advanced attributes.
	 */
	static readonly _attributes : any = {
		"multiline": {
			"ignore": true
		}
	};
	private span: JQuery;

	/**
	 * Constructor
	 *
	 * @memberOf et2_ajaxSelect_ro
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_ajaxSelect_ro._attributes, _child || {}));

		this.value = "";
		this.span = jQuery(document.createElement("span"));

		this.setDOMNode(this.span[0]);
	}

	set_value(_value)
	{
		this.value = _value;

		if(!_value) _value = "";
		this.span.text(_value);
	}
	/**
	 * Code for implementing et2_IDetachedDOM
	 */
	getDetachedAttributes(_attrs)
	{
		_attrs.push("value");
	}

	getDetachedNodes()
	{
		return [this.span[0]];
	}

	setDetachedAttributes(_nodes, _values)
	{
		this.span = jQuery(_nodes[0]);
		if(typeof _values["value"] != 'undefined')
		{
			this.set_value(_values["value"]);
		}
	}
}
et2_register_widget(et2_ajaxSelect_ro, ["ajax_select_ro"]);


