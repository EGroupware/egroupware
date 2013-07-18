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
 * Uses xoxco tagsinput library
 * @see http://xoxco.com/projects/code/tagsinput/
 * @augments et2_inputWidget
 */
var et2_taglist = et2_inputWidget.extend(
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
		"autocomplete_url": {
			"name": "Autocomplete source",
			"type": "string",
			"default": "etemplate_widget_taglist::ajax_search::etemplate",
			"description": "Menuaction (app.class.function) for autocomplete data source.  Must return actual JSON, and nothing more."
		},
		"autocomplete_params": {
			"name": "Autocomplete parameters",
			"type": "any",
			"default": {},
			"description": "Extra parameters passed to autocomplete URL"
		},
		"onAddTag": {
			"name": "onAddTag",
			"type": "js",
			"default": et2_no_init,
			"description": "Callback when a tag is added",
		},
		"onRemoveTag": {
			"name": "onRemoveTag",
			"type": "js",
			"default": et2_no_init,
			"description": "Callback when a tag is removed",
		},
		"onchange": {
			"name": "onChange",
			"type": "js",
			"default": et2_no_init,
			"description": "Callback when tags are changed",
		},
		"width": {
			default: "150px"
		}
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
		
		this.taglist = this.div.magicSuggest({
			id: this.getInstanceManager().uniqueId + '_' + this.id,
			data: this.options.select_options ? this.options.select_options : this.options.autocomplete_url,
			dataUrlParams: this.options.autocomplete_params,
			method: 'GET',
			displayField: "label",
			emptyText: this.options.empty_label,
			hideTrigger: true,
			noSuggestionText: this.egw().lang("No suggestions"),
			required: this.options.required
		});
		return true;
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
		
	set_value: function(value) {
	
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

// Require css - merge into etemplate2.css with all other widgets when done
if(typeof egw != 'undefined') egw(window).includeCSS(egw.webserverUrl + "/phpgwapi/js/jquery/magicsuggest/src/magicsuggest-1.3.0.css");