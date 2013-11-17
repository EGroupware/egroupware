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
	/phpgwapi/js/jquery/magicsuggest/src/magicsuggest-1.3.1.js;
*/

/**
 * Tag list widget
 *
 * A cross between auto complete, selectbox and chosen multiselect
 *
 * Uses MagicSuggest library
 * @see http://nicolasbize.github.io/magicsuggest/
 * @augments et2_selectbox
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
			"description": "Callback when tags are changed.  Argument is the new selection.",
		},
		"onclick": {
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
			"default": "100%"
		},
		"maxSelection": {
			"name": "max Selection",
			"type": "integer",
			"default": null,
			"description": "The maximum number of items the user can select if multiple selection is allowed."
		},
		// Selectbox attributes that are not applicable
		"multiple": { ignore: true},
		"rows": { ignore: true},
		"tags": { ignore: true}
	},

	// Allows sub-widgets to override options to the library
	lib_options: {},
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

		var options = jQuery.extend( {
			data: this.options.select_options && !jQuery.isEmptyObject(this.options.select_options) ? this._options2data(this.options.select_options) : this.options.autocomplete_url,
			dataUrlParams: this.options.autocomplete_params,
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
			selectionRenderer: jQuery.proxy(this.options.tagRenderer || this.selectionRenderer,this),
			renderer: jQuery.proxy(this.options.listRenderer || this.selectionRenderer,this),
			maxSelection: this.options.maxSelection,
			maxSelectionRenderer: jQuery.proxy(function(v) { this.egw().lang('You can not choose more then %1 item(s)!', v); }, this),
			width: this.options.width,	// propagate width
			highlight: false	// otherwise renderer have to return strings
		}, this.lib_options);
		this.taglist = this.taglist.magicSuggest(options);

		this.set_value(this.options.value);

		// Display / hide a loading icon while fetching
		$j(this.taglist)
			.on("beforeload", function() {this.container.prepend('<div class="ui-icon loading"/>');})
			.on("load", function() {$j('.loading',this.container).remove();});

		// onChange
		if(this.options.onchange)
		{
			$j(this.taglist).on("selectionchange", jQuery.proxy(this.change,this));
		}

		// onClick - pass more than baseWidget, so unbind it to avoid double callback
		if(typeof this.onclick == 'function')
		{
			this.div.unbind("click.et2_baseWidget")
				.on("click.et2_baseWidget", '.ms-sel-item', jQuery.proxy(function(event) {
				// Pass the target as expected, but also the data for that tag
				this.click(/*event.target,*/ $j(event.target).parent().data("json"));
			},this));
		}

		// onFocus
		if (typeof this.onfocus == 'function')
		{
			var widget = this;
			$j(this.taglist).focus(function(e) {
				widget.onfocus.call(widget.taglist, e, widget);
			});
		}
		return true;
	},

	/**
	 * convert _options to taglist data [{id:...,label:...},...] format
	 *
	 * @param object|array _options id: label or id: {label: ..., title: ...} pairs, or array if id's are 0, 1, ...
	 */
	_options2data: function(_options)
	{
		var options = jQuery.isArray(_options) ? jQuery.extend({}, _options) : _options;
		var data = [];
		for(var id in options)
		{
			var option = {id: id};
			if (typeof options[id] == 'object')
			{
				jQuery.extend(option, options[id]);
			}
			else
			{
				option.label = options[id];
			}
			data.push(option);
		}
		return data;
	},

	/**
	 * Set all options from current static select_options list
	 */
	select_all: function()
	{
		var all = [];
		for(var id in this.options.select_options) all.push(id);
		this.set_value(all);
	},

	/**
	 * Render a single item, taking care of correctly escaping html special chars
	 *
	 * @param item
	 * @returns {String}
	 */
	selectionRenderer: function(item)
	{
		var label = jQuery('<span>').text(item.label);
		if (typeof item.title != 'undefined') label.attr('title', item.title);

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
	 * $param Array _options usual format see _options2data
	 */
	set_select_options: function(_options)
	{
		this.options.select_options = _options;

		if(this.taglist == null) return;
		this.taglist.setData(this._options2data(this.options.select_options));
	},

	set_disabled: function(disabled)
	{
		this.options.disabled = disabled;

		if(this.taglist == null) return;
		disabled ? this.taglist.disable() : this.taglist.enable();
	},

	/**
	 * Set value(s) of taglist, add them automatic to select-options, if allowFreeEntries
	 *
	 * @param value (array of) ids
	 */
	set_value: function(value)
	{
		this.options.value = value;
		if(this.taglist == null) return;

		this.taglist.clear(true);
		if(!value) return;

		var values = jQuery.isArray(value) ? value : [value];
		for(var i=0; i < values.length; ++i)
		{
			var v = values[i];
			if (typeof this.options.select_options[v] == 'undefined')
			{
				values[i] = {
					id: v,
					label: v
				}
			}
			else
			{
				if (typeof values[i].id == 'undefined')
				{
					values[i] = {
						id: v,
						label: this.options.select_options[v]
					}
				}
			}
		}


		this.taglist.addToSelection(values);
	},

	getValue: function()
	{
		if(this.taglist == null) return null;
		return this.taglist.getValue();
	}
});
et2_register_widget(et2_taglist, ["taglist"]);

/**
 * Taglist customized specifically for emails, which it pulls from the mail application,
 * or addressbook if mail is not available.  Free entries are allowed, and we render
 * invalid free entries differently (but still accept them).
 *
 * @augments et2_taglist
 */
var et2_taglist_email = et2_taglist.extend(
{
	attributes: {
		"autocomplete_url": {
			"default": "etemplate_widget_taglist::ajax_email::etemplate",
		},
		"autocomplete_params": {
			"default": {},
		},
		allowFreeEntries: {
			"default": true,
			ignore: true
		}
	},
	lib_options: {
		// Search function limits to 3 anyway
		minChars: 3
	},

	// PREG for validation comes from et2_url
	//EMAIL_PREG: new RegExp(/^[^\x00-\x20()<>@,;:\".\[\]]+@([a-z0-9ÄÖÜäöüß](|[a-z0-9ÄÖÜäöüß_-]*[a-z0-9ÄÖÜäöüß])\.)+[a-z]{2,6}/),

	selectionRenderer: function(item)
	{
		// We check free entries for valid email, and render as invalid if it's not.
		var valid = item.id != item.label || et2_url.prototype.EMAIL_PREG.test(item.id || '');

		var label = jQuery('<span>').text(item.label);
		if (typeof item.title != 'undefined') label.attr('title', item.title);
		if (!valid) label.addClass('ui-state-error');

		return label;
	}
});
et2_register_widget(et2_taglist_email, ["taglist-email"]);

// Require css
// TODO: merge into etemplate2.css with all other widgets when done
if(typeof egw != 'undefined') egw(window).includeCSS(egw.webserverUrl + "/phpgwapi/js/jquery/magicsuggest/src/magicsuggest-1.3.1.css");
