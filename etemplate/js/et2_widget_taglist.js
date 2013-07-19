/**
 * EGroupware eTemplate2 - JS Tag list object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2013
 * @version $Id$
 */

"use strict";

/*egw:uses
	et2_core_inputWidget;
	/phpgwapi/js/jquery/magicsuggest/src/magicsuggest-1.3.0.js;
*/

/**
 * Tag list widget
 * 
 * A cross between auto complete, selectbox and chosen multiselect
 * 
 * Uses MagicSuggest library
 * @see http://nicolasbize.github.io/magicsuggest/
 * @augments et2_inputWidget
 */
var et2_taglist = et2_selectbox.extend(
{
	attributes: {
		"empty_label": {
			"name": "Empty label",
			"type": "string",
			"default": "",
			"description": "Textual label for when nothing is selected"
		},
		"select_options": {
			"type": "any",
			"name": "Select options",
			"default": null, //[{id: "a", label: "Alpha"},{id:"b", label: "Beta"}],
			"description": "Internaly used to hold the select options."
		},

		// Value can be CSV String or Array
		"value": {
			"type": "any"
		},
		
		// These default parameters set it to read the addressbook via the link system
		"autocomplete_url": {
			"name": "Autocomplete source",
			"type": "string",
			"default": "etemplate_widget_taglist::ajax_search::etemplate",
			"description": "Menuaction (app.class.function) for autocomplete data source.  Must return actual JSON, and nothing more."
		},
		"autocomplete_params": {
			"name": "Autocomplete parameters",
			"type": "any",
			"default": {app:"addressbook"},
			"description": "Extra parameters passed to autocomplete URL"
		},
		
		allowFreeEntries: {
			"name": "Allow free entries",
			"type": "boolean",
			"default": true,
			"description": "Restricts or allows the user to type in arbitrary entries"
		},
		
		"onchange": {
			"name": "onChange",
			"type": "js",
			"default": et2_no_init,
			"description": "Callback when tags are changed.  Argument is the new selection.",
		},
		"onclick": {
			"name": "onClick",
			"type": "js",
			"default": et2_no_init,
			"description": "Callback when a tag is clicked.  The clicked tag is passed."
		},
		"tagRenderer": {
			"name": "Tag renderer",
			"type": "js",
			"default": et2_no_init,
			"description": "Callback to provide a custom renderer for what's _inside_ each tag.  Function parameter is the select_option data for that ID."
		},
		"listRenderer": {
			"name": "List renderer",
			"type": "js",
			"default": et2_no_init,
			"description": "Callback to provide a custom renderer for each suggested item.  Function parameter is the select_option data for that ID."
		},
		"width": {
			default: "100%"
		},
		// Selectbox attributes that are not applicable
		"multiple": { ignore: true},
		"rows": { ignore: true},
		"tags": { ignore: true}
	},
	
	/**
	 * Construtor
	 * 
	 * @memberOf et2_selectbox
	 */
	init: function() {
		this._super.apply(this, arguments);
		
		// jQuery wrapped DOM node
		this.div = jQuery("<div></div>");
		
		// magicSuggest object
		this.taglist = null;
		
		this.setDOMNode(this.div[0]);
	},
		
	destroy: function() {
		if(this.div != null)
		{
			// Undo the plugin
		}
		this._super.apply(this, arguments);

	},
		
	doLoadingFinished: function() {
		this._super.apply(this, arguments);
		
		// Initialize magicSuggest here
		if(this.taglist != null) return;
		
		
		// MagicSuggest would replaces our div, so add a wrapper instead
		this.taglist = $j('<div/>').appendTo(this.div);
		
		this.taglist = this.taglist.magicSuggest({
			data: this.options.select_options && !jQuery.isEmptyObject(this.options.select_options) ? this.options.select_options : this.options.autocomplete_url,
			dataUrlParams: this.options.autocomplete_params,
			value: this.options.value,
			method: 'GET',
			displayField: "label",
			invalidCls: 'invalid ui-state-error',
			emptyText: this.options.empty_label,
			hideTrigger: true,
			noSuggestionText: this.egw().lang("No suggestions"),
			required: this.options.required,
			allowFreeEntries: this.options.allowFreeEntries,
			disabled: this.options.disabled || this.options.readonly,
			editable: !(this.options.disabled || this.options.readonly),
			selectionRenderer: this.options.tagRenderer || this.selectionRenderer,
			renderer: this.options.listRenderer || null
		});
		
		// Display / hide a loading icon while fetching
		$j(this.taglist)
			.on("beforeload", function() {this.container.prepend('<div class="ui-icon loading"/>')})
			.on("load", function() {$j('.loading',this.container).remove()});

		// onChange
		if(this.options.onchange)
		{
			$j(this.taglist).on("selectionchange", jQuery.proxy(this.change,this));
		}
		
		// onClick - pass more than baseWidget, so unbind it to avoid double callback
		if(this.options.onclick)
		{
			this.div.unbind("click.et2_baseWidget")
				.on("click.et2_baseWidget", '.ms-sel-item', jQuery.proxy(function(event) { 
				var widget = this;
				// Pass the target as expected, but also the data for that tag
				this.click(event.target, $j(event.target).parent().data("json"));
			},this));
		}
		return true;
	},
	
	selectionRenderer: function(item)
	{
		var label = '<span title="'+(typeof item.title != "undefined" ?item.title:'')+'">'+item.label+'</span>';
		return label;
	},
	set_autocomplete_url: function(source)
	{
		if(source.indexOf('http') != 0)
		{
			source = this.egw().ajaxUrl(source);
		}
		this.options.autocomplete_url = source;
		
		if(this.taglist == null) return;
		this.taglist.setData(source);
	},
		
	/**
	 * Set the list of suggested options to a static list.
	 * 
	 * You can pass either the traditional {id:label, id:label...} or an array of objects,
	 * and either will be coerced to what is needed.
	 * 
	 * $param Array _options
	 */
	set_select_options: function(_options)
	{
		for (var key in _options)
		{
			var option = {id: key};
			
			// Translate the options
			if(!this.options.no_lang)
			{
				if (typeof _options[key] === 'object')
				{
					if(_options[key]["label"]) option["label"] = this.egw().lang(_options[key]["label"]);
					if(_options[key]["title"]) option["title"] = this.egw().lang(_options[key]["title"]);
				}
				else
				{
					option.label = this.egw().lang(_options[key]);
				}
			}
			this.options.select_options.push(option);
		}
		
		if(this.taglist == null) return;
		this.taglist.setData(this.options.select_options);
	},
		
	set_disabled: function(disabled)
	{
		this.options.disabled = disabled;
		
		if(this.taglist == null) return;
		disabled ? this.taglist.disable() : this.taglist.enable();
	},
		
	set_value: function(value) {
		if(this.options.readonly && !this.options.select_options)
		{
			this.options.select_options = value;
		}
		if(this.taglist == null) return;
		this.taglist.clear(true);
		this.taglist.setValue(value);
	},
	getValue: function() {
	
		if(this.taglist == null) return null;
		return this.taglist.getValue();
	}
});
et2_register_widget(et2_taglist, ["taglist"]);

// Require css
// TODO: merge into etemplate2.css with all other widgets when done
if(typeof egw != 'undefined') egw(window).includeCSS(egw.webserverUrl + "/phpgwapi/js/jquery/magicsuggest/src/magicsuggest-1.3.0.css");