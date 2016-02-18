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
	/phpgwapi/js/jquery/magicsuggest/magicsuggest.js;
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
			"description": "Textual label for when nothing is selected",
			translate: true
		},
		"select_options": {
			"type": "any",
			"name": "Select options",
			"default": {}, //[{id: "a", label: "Alpha"},{id:"b", label: "Beta"}],
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
			"default": "home.etemplate_widget_taglist.ajax_search.etemplate",
			"description": "Menuaction (app.class.function) for autocomplete data source.  Must return actual JSON, and nothing more."
		},
		"autocomplete_params": {
			"name": "Autocomplete parameters",
			"type": "any",
			"default": {app:"addressbook"},
			"description": "Extra parameters passed to autocomplete URL.  It should be a stringified JSON object."
		},

		allowFreeEntries: {
			"name": "Allow free entries",
			"type": "boolean",
			"default": true,
			"description": "Restricts or allows the user to type in arbitrary entries"
		},

		"onchange": {
			"description": "Callback when tags are changed.  Argument is the new selection."
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
		"tags": { ignore: true},
		useCommaKey: {
			name: "comma will start validation",
			type: "boolean",
			"default": true,
			description: "Set to false to allow comma in entered content"
		}
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
		this.div = jQuery("<div></div>").addClass('et2_taglist');

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

	transformAttributes: function(_attrs) {
		this._super.apply(this, arguments);

		// Handle url parameters - they should be an object
		if(typeof _attrs.autocomplete_params == 'string')
		{
			try
			{
				_attrs.autocomplete_params = JSON.parse(_attrs.autocomplete_params)
			}
			catch (e)
			{
				this.egw().debug('warn', 'Invalid autocomplete_params: '+_attrs.autocomplete_params );
			}
		}
	},

	doLoadingFinished: function() {
		this._super.apply(this, arguments);

		// Initialize magicSuggest here
		if(this.taglist != null) return;

		// If no options or ajax url, try the array mgr
		if(this.options.select_options === null && !this.options.autocomplete_url)
		{
			this.set_select_options(this.getArrayMgr("sel_options").getEntry(this.id));
		}

		// MagicSuggest would replaces our div, so add a wrapper instead
		this.taglist = $j('<div/>').appendTo(this.div);

		this.taglist_options = jQuery.extend( {
			// magisuggest can NOT work setting an empty autocomplete url, it will then call page url!
			// --> setting an empty options list instead
			data: this.options.autocomplete_url ? this.options.autocomplete_url :
				this.options.select_options || {},
			dataUrlParams: this.options.autocomplete_params,
			method: 'GET',
			displayField: "label",
			invalidCls: 'invalid ui-state-error',
			placeholder: this.options.empty_label,
			hideTrigger: true,
			noSuggestionText: this.egw().lang("No suggestions"),
			required: this.options.required,
			allowFreeEntries: this.options.allowFreeEntries,
			useTabKey: true,
			useCommaKey: this.options.useCommaKey,
			disabled: this.options.disabled || this.options.readonly,
			editable: !(this.options.disabled || this.options.readonly),
			selectionRenderer: jQuery.proxy(this.options.tagRenderer || this.selectionRenderer,this),
			renderer: jQuery.proxy(this.options.listRenderer || this.selectionRenderer,this),
			maxSelection: this.options.maxSelection,
			maxSelectionRenderer: jQuery.proxy(function(v) { this.egw().lang('You can not choose more then %1 item(s)!', v); }, this),
			width: this.options.width,	// propagate width
			highlight: false,	// otherwise renderer have to return strings,
			value: this.options.value
		}, this.lib_options);
		this.taglist = this.taglist.magicSuggest(this.taglist_options);
		this.$taglist = $j(this.taglist);

		// AJAX _and_ select options - use custom function
		if(this.options.autocomplete_url && !jQuery.isEmptyObject(this.options.select_options))
		{
			var widget = this;
			this.taglist.setData(function(query) {
				return widget._data.call(widget,query)
			});
		}

		// Display / hide a loading icon while fetching
		this.$taglist
			.on("beforeload", function() {this.container.prepend('<div class="ui-icon loading"/>');})
			.on("load", function() {$j('.loading',this.container).remove();})
		// Keep focus when selecting from the list
			.on("selectionchange", function() { $j('input',this.container).focus();})
		// Bind keyup so we can start ajax search when we like
			.on('keyup.start_search', jQuery.proxy(this._keyup, this));

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
			this.$taglist.focus(function(e) {
				widget.onfocus.call(widget.taglist, e, widget);
			});
		}
		return true;
	},

	/**
	 * convert _options to taglist data [{id:...,label:...},...] format
	 *
	 * @param {(object|array)} _options id: label or id: {label: ..., title: ...} pairs, or array if id's are 0, 1, ...
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
				if(option.value) option.id = option.value
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
	 * Custom data function to return local options if there is nothing
	 * typed, or query via AJAX if user typed something
	 *
	 * @returns {Array}
	 */
	_data: function(query) {

		var return_value = this.options.select_options || {};

		if (!jQuery.isEmptyObject(this.options.select_options) && !this._query_server
			|| query.trim().length < this.taglist_options.minChars
			|| !this.options.autocomplete_url)
		{
			// Check options, if there's a match there (that is not already
			// selected), do not ask server
			var filtered = []
			var selected = this.taglist.getSelection();
			$j.each(this.options.select_options, function(index, obj) {
				var name = obj.label;
				if(selected.indexOf(obj) < 0 && name.toLowerCase().indexOf(query.toLowerCase()) > -1)
				{
					filtered.push(obj);
				}
			});
			return_value = filtered.length > 0 ? filtered : this.options.autocomplete_url
		}
		else if (query.trim().length >= this.taglist_options.minChars || this._query_server)
		{
			// No options - ask server
			return this.options.autocomplete_url;
		}
		this._query_server = false;

		return return_value;
	},

	/**
	* Handler for keyup, used to start ajax search when we like
	*/
	_keyup: function(e, taglist, event) {
		if(event.which === jQuery.ui.keyCode.ENTER)
		{
			this._query_server = true;
			this.taglist.collapse();
			this.taglist.expand();
			this._query_server = false;

			this.div.find('.ms-res-item-active')
				.removeClass('ms-res-item-active');

			event.preventDefault();
			return false;
		}
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
		if(source && source[0] != '/' && source.indexOf('http') != 0)
		{
			source = this.egw().ajaxUrl(source);
		}
		if(this.options.autocomplete_url != source)
		{
			this.options.autocomplete_url = source;

			// do NOT set an empty autocomplete_url, magicsuggest would use page url instead!
			if(this.taglist == null || !source) return;

			var widget = this;
			this.taglist.setData(function(query) {
				return widget._data.call(widget,query)
			});
		}
	},

	/**
	 * Set the list of suggested options to a static list.
	 *
	 * @param {array} _options usual format see _options2data
	 */
	set_select_options: function(_options)
	{
		this.options.select_options = this._options2data(_options);
		
		if(this.taglist == null) return;
		var widget = this;
		this.taglist.setData(function(query) {
			return widget._data.call(widget,query)
		});
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
		var values = jQuery.isArray(value) ? jQuery.extend([],value) : [value];
		this.options.value = values;
		
		if(this.taglist == null) return;

		if(!value)
		{
			this.taglist.clear(true);
			return;
		}

		var result = [];
		for(var i=0; i < values.length; ++i)
		{
			var v = values[i];
			if (v && typeof v == 'object' && typeof v.id != 'undefined' && typeof v.label != 'undefined')
			{
				// already in correct format
			}
			else if (this.options.select_options &&
				// Check options
				(result = $j.grep(this.options.select_options, function(e) {
					return e.id == v;
				})) ||
				// Check current selection to avoid going back to server
				(result = $j.grep(this.taglist.getSelection(), function(e) {
					return e.id == v;
				}))
			)
			{
				// Options should have been provided, but they weren't
				// This can happen for ajax source with an existing value
				if(this.options.select_options == null)
				{
					this.options.select_options = [];
				}
				values[i] = result[0] ? result[0] : {
					id: v,
					label: v
				};
			}
			else
			{
				if (typeof values[i].id == 'undefined')
				{
					values[i] = {
						id: v,
						label: v
					};
				}
			}
		}

		this.taglist.setSelection(values);
	},

	getValue: function()
	{
		if(this.taglist == null) return null;
		// trigger blur on taglist to not loose just typed value
		jQuery(this.taglist.container).trigger('blur');
		return this.taglist.getValue();
	}
});
et2_register_widget(et2_taglist, ["taglist"]);

/**
 * Taglist customized specificlly for egw acccounts, fetches accounts and groups list,
 * free entries are allowed
 *
 */
var et2_taglist_account = et2_taglist.extend(
{
	attributes: {
		"autocomplete_url": {
			"default": "home.etemplate_widget_taglist.ajax_search.etemplate"
		},
		allowFreeEntries: {
			"default": false,
			ignore: true
		},
		account_type: {
			name: 'Account type',
			'default': 'accounts',
			type: 'string',
			description: 'Limit type of accounts.  One of {accounts,groups,both,owngroups}.'
		}
	},
	lib_options: {
		minChars: 2
	},

	init:function ()
	{
		this._super.apply(this, arguments);

		// Counter to prevent infinite looping while fetching account names
		this.deferred_loading = 0;

		this.options.autocomplete_params.type = "account";
	},

	/**
	 * Set if accounts, groups or both are supported
	 *
	 * Option get's passed on to autocomplete_params.
	 *
	 * @param {string} value "accounts" (default), "groups", "both", "owngroups"
	 */
	set_account_type: function(value)
	{
		if(value != this.options.account_type)
		{
			this.options.select_options = [];
		}
		this.options.autocomplete_params.account_type = this.options.account_type = value;

		this.options.select_options = this._get_accounts();

		if(this.taglist != null)
		{
			// Update taglist too, since it already copied the params
			this.taglist.setDataUrlParams(this.options.autocomplete_params);
		}
	},

	/**
	 * Get account info for select options from common client-side account cache
	 *
	 * @return {Array} select options
	 */
	_get_accounts: function()
	{
		if (!jQuery.isArray(this.options.select_options))
		{
			var options = jQuery.extend({}, this.options.select_options);
			this.options.select_options = [];
			for(var key in options)
			{
				if (typeof options[key] == 'object')
				{
					if (typeof(options[key].key) == 'undefined')
					{
						options[key].value = key;
					}
					this.options.select_options.push(options[key]);
				}
				else
				{
					this.options.select_options.push({value: key, label: options[key]});
				}
			}
		}
		var type = this.egw().preference('account_selection', 'common');
		var accounts = [];
		// for primary_group we only display owngroups == own memberships, not other groups
		if (type == 'primary_group' && this.options.account_type != 'accounts')
		{
			if (this.options.account_type == 'both')
			{
				accounts = this.egw().accounts('accounts');
			}
			accounts = accounts.concat(this.egw().accounts('owngroups'));
		}
		else
		{
			accounts = this.egw().accounts(this.options.account_type);
		}
		return this.options.select_options.concat(accounts);
	},

	int_reg_exp: /^-?[0-9]+$/,

	/**
	 * Set value(s) of taglist, reimplemented to automatic resolve numerical account_id's
	 *
	 * @param value (array of) ids
	 */
	set_value: function(value)
	{
		if(!value) return this._super.call(this, value);

		var values = jQuery.isArray(value) ? jQuery.extend([], value) : [value];
		for(var i=0; i < values.length; ++i)
		{
			var v = values[i];
			if (typeof v == 'object' && v.id === v.label) v = v.id;
			if (typeof v != 'object'  && !isNaN(v) && (typeof v != 'string' || v.match(this.int_reg_exp)))
			{
				v = parseInt(v);
				var label = this.egw().link_title('home-accounts', v);
				if (label)	// already cached on client-side --> replace it
				{
					values[i] = {
						id: v,
						label: label
					};
				}
				else if (!this.deferred_loading)	// call set_value again, after result has arrived from server
				{
					this.deferred_loading++;
					this.egw().link_title('home-accounts', v, function(label) {
						this.deferred_loading--;
						if (label) this.set_value(values);
					}, this);
				}
			}
		}
		// Don't proceed if waiting for labels
		if(this.deferred_loading <=0)
		{
			this._super.call(this, values);
		}
	}
});
et2_register_widget(et2_taglist_account, ["taglist-account"]);

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
			"default": "home.etemplate_widget_taglist.ajax_email.etemplate"
		},
		"autocomplete_params": {
			"default": {}
		},
		allowFreeEntries: {
			"default": true,
			ignore: true
		},
		include_lists: {
			name: "Include lists",
			description:"Include mailing lists in search results",
			default: false,
			type: "boolean"
		},
		useCommaKey: {
			name: "comma will start validation",
			type: "boolean",
			"default": false,
			description: "Set to false to allow comma in entered content"
		}
	},
	lib_options: {
		// Search function limits to 3 anyway
		minChars: 3
	},

	init: function() {
		this._super.apply(this, arguments);

		if(this.options.include_lists)
		{
			this.options.autocomplete_params.include_lists = true;
		}
	},

	// PREG for validation comes from et2_url
	//EMAIL_PREG: new RegExp(/^[^\x00-\x20()<>@,;:\".\[\]]+@([a-z0-9ÄÖÜäöüß](|[a-z0-9ÄÖÜäöüß_-]*[a-z0-9ÄÖÜäöüß])\.)+[a-z]{2,6}/),

	selectionRenderer: function(item)
	{
		// Trim
		if(typeof item.id == 'string')
		{
			item.id = item.id.trim();
		}
		if(typeof item.label == 'string')
		{
			item.label = item.label.trim();
		}
		// We check free entries for valid email, and render as invalid if it's not.
		var valid = item.id != item.label || et2_url.prototype.EMAIL_PREG.test(item.id || '');

		if (!valid && item.id)
		{
			// automatic quote 'Becker, Ralf <rb@stylite.de>' as '"Becker, Ralf" <rb@stylite.de>'
			var matches = item.id.match(/^(.*) ?<(.*)>$/);
			if (matches && et2_url.prototype.EMAIL_PREG.test('"'+matches[1].trim()+'" <'+matches[2].trim()+'>'))
			{
				item.id = item.label = '"'+matches[1].trim()+'" <'+matches[2].trim()+'>';
				valid = true;
			}
			// automatic insert multiple comma-separated emails like "rb@stylite.de, hn@stylite.de"
			if (!valid)
			{
				var parts = item.id.split(/, */);
				if (parts.length > 1)
				{
					valid = true;
					for(var i=0; i < parts.length; ++i)
					{
						parts[i] = parts[i].trim();
						if (!et2_url.prototype.EMAIL_PREG.test(parts[i]))
						{
							valid = false;
							break;
						}
					}
					if (valid)
					{
						item.id = item.label = parts.shift();
						// insert further parts into taglist, after validation first one
						var taglist = this.taglist;
						window.setTimeout(function()
						{
							var items = [];
							for(var i=0; i < parts.length; ++i)
							{
								items.push({id: parts[i], label: parts[i]});

							}
							taglist.addToSelection(items);
						}, 10);
					}
				}
			}
		}

		var label = jQuery('<span>').text(item.label);
		if (item.class) label.addClass(item.class);
		if (typeof item.title != 'undefined') label.attr('title', item.title);
		if (typeof item.data != 'undefined') label.attr('data', item.data);
		if (!valid) label.addClass('ui-state-error');

		return label;
	}
});
et2_register_widget(et2_taglist_email, ["taglist-email"]);

/**
 * et2_taglist_ro is the readonly implementation of the taglist.
 *
 * @augments et2_selectbox
 */
var et2_taglist_ro = et2_selectbox_ro.extend(
{
	/**
	 * Constructor
	 *
	 * @memberOf et2_selectbox_ro
	 */
	init: function() {
		this._super.apply(this, arguments);
		this.span = jQuery('<div><ul /></div>')
			.addClass('et2_taglist_ro');
		this.setDOMNode(this.span[0]);
		this.span = $j('ul',this.span)
			.addClass('ms-sel-ctn');
	},

	set_value: function(_value) {
		this._super.apply(this, arguments);
		$j('li',this.span).addClass('ms-sel-item');
	}
});
et2_register_widget(et2_taglist_ro, ["taglist_ro","taglist_email_ro", "taglist_account_ro" ]);

// Require css
// TODO: merge into etemplate2.css with all other widgets when done
if(typeof egw == 'function') egw(window).includeCSS(egw.webserverUrl + "/phpgwapi/js/jquery/magicsuggest/magicsuggest.css");
