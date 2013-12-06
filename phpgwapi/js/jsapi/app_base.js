/**
 * EGroupware clientside Application javascript base object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

"use strict";

/*egw:uses
        egw_inheritance;
*/

/**
 * Object to collect instanciated appliction objects
 *
 * Attributes classes collects loaded application classes,
 * which can get instanciated:
 *
 *	app[appname] = new app.classes[appname]();
 *
 * On destruction only app[appname] gets deleted, app.classes[appname] need to be used again!
 *
 * @type object
 */
window.app = {classes: {}};

/**
 * Common base class for application javascript
 * Each app should extend as needed.
 *
 * All application javascript should be inside.  Intitialization goes in init(),
 * clean-up code goes in destroy().  Initialization is done once all js is loaded.
 *
 * var app.appname = AppJS.extend({
 *	// Actually set this one, the rest is example
 *	appname: appname,
 *
 *	internal_var: 1000,
 *
 *	init: function()
 *	{
 *		// Call the super
 *		this._super.apply(this, arguments);
 *
 *		// Init the stuff
 *		if ( egw.preference('dateformat', 'common') )
 *		{
 *			// etc
 *		}
 *	},
 *	_private: function()
 *	{
 *		// Underscore private by convention
 *	}
 * });
 */
var AppJS = Class.extend(
{
	/**
	 * Internal application name - override this
	 */
	appname: '',

	/**
	 * Internal reference to etemplate2 widget tree
	 */
	et2: null,

	/**
	 * Internal reference to egw client-side api object for current app and window
	 */
	egw: null,

	/**
	 * Initialization and setup goes here, but the etemplate2 object
	 * is not yet ready.
	 */
	init: function() {
		window.app[this.appname] = this;

		this.egw = egw(this.appname, window);
	},

	/**
	 * Clean up any created objects & references
	 */
	destroy: function() {
		delete this.et2;
		delete window.app[this.appname];
	},

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().  Note that this can be called
	 * several times, with different et2 objects, as templates are loaded.
	 *
	 * @param et2 etemplate2 Newly ready object
	 */
	et2_ready: function(et2) {
		if(this.et2 !== null)
		{
			egw.debug('log', "Changed et2 object");
		}
		this.et2 = et2.widgetContainer;
	},

	/**
	 * Open an entry.
	 *
	 * Designed to be used with the action system as a callback
	 * eg: onExecute => app.<appname>.open
	 *
	 * @param _action
	 * @param _senders
	 */
	open: function(_action, _senders) {
		var id_app = _senders[0].id.split('::')
		egw.open(id_app[1], this.appname);
	 },

	/**
	 * A generic method to action to server asynchronously
	 *
	 * Designed to be used with the action system as a callback.
	 * In the PHP side, set the action
	 * 'onExecute' => 'javaScript:app.<appname>.action', and
	 * implement _do_action(action_id, selected)
	 *
	 * @param {egwAction} _action
	 * @param {egwActionObject[]} _elems
	 */
	action: function(_action, _elems)
	{
		// let user confirm select-all
		var select_all = _action.getManager().getActionById("select_all");
		var confirm_msg = (_elems.length > 1 || select_all && select_all.checked) &&
			typeof _action.data.confirm_multiple != 'undefined' ?
				_action.data.confirm_multiple : _action.data.confirm;

		if (typeof confirm_msg != 'undefined')
		{
			var that = this;
			var action_id = _action.id;
			et2_dialog.show_dialog(function(button_id,value)
			{
				if (button_id != et2_dialog.NO_BUTTON)
				{
					that._do_action(action_id, _elems);
				}
			}, confirm_msg, egw.lang('Confirmation required'), et2_dialog.BUTTONS_YES_NO, et2_dialog.QUESTION_MESSAGE);
		}
		else if (typeof this._do_action == 'function')
		{
			this._do_action(_action.id, _elems);
		}
		else
		{
			// If this is a nextmatch action, do an ajax submit setting the action
			var nm = null;
			var action = _action;
			while(nm == null && action.parent != null)
			{
				if(action.data.nextmatch) nm = action.data.nextmatch;
				action = action.parent;
			}
			if(nm != null)
			{
				var value = {};
				value[nm.options.settings.action_var] = _action.id;
				nm.set_value(value);
				nm.getInstanceManager().submit();
			}
		}
	},

	/**
	 * Set the application's state to the given state.
	 *
	 * While not pretending to implement the history API, it is patterned similarly
	 * @link http://www.whatwg.org/specs/web-apps/current-work/multipage/history.html
	 *
	 * The default implementation works with the favorites to apply filters to a nextmatch.
	 *
	 *
	 * @param {{name: string, state: object}|string} state Object (or JSON string) for a state.
	 *	Only state is required, and its contents are application specific.  
	 * @param {type} name description
	 */
	setState: function(state)
	{
		// State should be an object, not a string, but we'll parse
		if(typeof state == "string")
		{
			if(state.indexOf('{') != -1 || state =='null')
			{
				state = JSON.parse(state);
			}
		}
		if(typeof state != "object")
		{
			egw.debug('error', 'Unable to set state to %o, needs to be an object',state);
			return;
		}
		if(state == null)
		{
			state = {};
		}

		// Try and find a nextmatch widget, and set its filters
		var nextmatched = false;
		var et2 = etemplate2.getByApplication(this.appname);
		for(var i = 0; i < et2.length; i++)
		{
			et2[i].widgetContainer.iterateOver(function(_widget) {
				// Apply
				_widget.activeFilters = state;
				_widget.applyFilters();
				nextmatched = true;
			}, this, et2_nextmatch);
		}

		// No nextmatch?  Try a redirect to list
		if(!nextmatched)
		{
			egw.open('',this.appname,'list',{'state': state},this.appname);
		}
	},

	/**
	 * Retrieve the current state of the application for future restoration
	 *
	 * The state can be anything, as long as it's an object.  The contents are
	 * application specific.  The default implementation finds a nextmatch and
	 * returns its value.
	 *
	 * @return {object} Value of a nextmatch
	 */
	getState: function()
	{
		var state = {};

		// Try and find a nextmatch widget, and set its filters
		var et2 = etemplate2.getByApplication(this.appname);
		for(var i = 0; i < et2.length; i++)
		{
			et2[i].widgetContainer.iterateOver(function(_widget) {
				state = _widget.getValue();
			}, this, et2_nextmatch);
		}

		return state;
	},

	add_favorite: function()
	{
		if(typeof this.favorite_popup == "undefined")
		{
			this._create_favorite_popup();
		}
		// Get current state
		this.favorite_popup.state = this.getState();
/*
		// Add in extras
		for(var extra in this.options.filters)
		{
			// Don't overwrite what nm has, chances are nm has more up-to-date value
			if(typeof this.popup.current_filters == 'undefined')
			{
				this.popup.current_filters[extra] = this.nextmatch.options.settings[extra];
			}
		}

		// Add in application's settings
		if(this.filters != true)
		{
			for(var i = 0; i < this.filters.length; i++)
			{
				this.popup.current_filters[this.options.filters[i]] = this.nextmatch.options.settings[this.options.filters[i]];
			}
		}
*/
		// Remove some internal values
		delete this.favorite_popup.state[this.id];
		if(this.favorite_popup.group != undefined)
		{
			delete this.favorite_popup.state[this.favorite_popup.group.id];
		}

		// Make sure it's an object - deep copy to prevent references in sub-objects (col_filters)
		this.favorite_popup.state = jQuery.extend(true,{},this.favorite_popup.state);

		// Update popup with current set filters (more for debug than user)
		var filter_list = [];
		var add_to_popup = function(arr) {
			filter_list.push("<ul>");
			jQuery.each(arr, function(index, filter) {
				filter_list.push("<li id='index'><span class='filter_id'>"+index+"</span>" +
					(typeof filter != "object" ? "<span class='filter_value'>"+filter+"</span>": "")
				);
				if(typeof filter == "object" && filter != null) add_to_popup(filter);
				filter_list.push("</li>");
			});
			filter_list.push("</ul>");
		}
		add_to_popup(this.favorite_popup.state);
		$j("#"+this.appname+"_favorites_popup_state",this.favorite_popup)
			.replaceWith(
				$j(filter_list.join("")).attr("id",this.appname+"_favorites_popup_state")
			);
		$j("#"+this.appname+"_favorites_popup_state",this.favorite_popup)
			.hide()
			.siblings(".ui-icon-circle-plus")
			.removeClass("ui-icon-circle-minus");

		// Popup
		this.favorite_popup.dialog("open");
		console.log(this);
	},

	/**
	 * Create the "Add new" popup dialog
	 */
	_create_favorite_popup: function()
	{
		var self = this;
		var favorite_prefix = 'favorite_';

		// Clear old, if existing
		if(this.favorite_popup && this.favorite_popup.group)
		{
			this.favorite_popup.group.free();
			delete this.favorite_popup;
		}

		// Create popup
		this.favorite_popup = $j('<div id="'+this.dom_id + '_nm_favorites_popup" title="' + egw().lang("New favorite") + '">\
			<form>\
			<label for="name">'+
				this.egw.lang("name") +
			'</label>' +

			'<input type="text" name="name" id="name"/>\
			<div id="'+this.appname+'_favorites_popup_admin"/>\
			<span>'+ this.egw.lang("Details") + '</span><span style="float:left;" class="ui-icon ui-icon-circle-plus ui-button" />\
			<ul id="'+this.appname+'_favorites_popup_state"/>\
			</form>\
			</div>'
		).appendTo(this.et2 ? this.et2.getDOMNode() : $j('body'));

		$j(".ui-icon-circle-plus",this.favorite_popup).prev().andSelf().click(function() {
			var details = $j("#"+self.appname+"_favorites_popup_state",self.favorite_popup)
				.slideToggle()
				.siblings(".ui-icon-circle-plus")
				.toggleClass("ui-icon-circle-minus");
		});

		// Add some controls if user is an admin
		var apps = egw().user('apps');
		var is_admin = (typeof apps['admin'] != "undefined");
		if(is_admin)
		{
			this.favorite_popup.group = et2_createWidget("select-account",{
				id: "favorite[group]",
				account_type: "groups",
				empty_label: "Groups",
				no_lang: true,
				parent_node: this.appname+'_favorites_popup_admin'
			},this.et2 || null);

		}

		var buttons = {};
		buttons[this.egw.lang("save")] =  function() {
			// Add a new favorite
			var name = $j("#name",this);

			if(name.val())
			{
				// Add to the list
				name.val(name.val().replace(/(<([^>]+)>)/ig,""));
				var safe_name = name.val().replace(/[^A-Za-z0-9-_]/g,"_");
				var favorite = {
					name: name.val(),
					group: (typeof self.favorite_popup.group != "undefined" &&
						self.favorite_popup.group.get_value() ? self.favorite_popup.group.get_value() : false),
					state: self.favorite_popup.state
				};

				var favorite_pref = favorite_prefix+safe_name;

				// Save to preferences
				if(typeof self.favorite_popup.group != "undefined" && self.favorite_popup.group.getValue() != '')
				{
					// Admin stuff - save preference server side
					self.egw.jsonq('home.egw_framework.ajax_set_favorite.template'
						[
							self.options.app,
							name.val(),
							"add",
							self.favorite_popup.group.get_value(),
							self.favorite_popup.state
						]
					);
					self.favorite_popup.group.set_value('');
				}
				else
				{
					// Normal user - just save to preferences client side
					self.egw.set_preference(self.appname,favorite_pref,{
						name: name.val(),
						group: false,
						state:self.favorite_popup.state
					});
				}
				delete self.favorite_popup.state;
			}
			// Reset form
			name.val("");
			$j("#filters",self.favorite_popup).empty();

			$j(this).dialog("close");
		};
		buttons[this.egw.lang("cancel")] = function() {
			self.favorite_popup.group.set_value(null);
			$j(this).dialog("close");
		};

		this.favorite_popup.dialog({
			autoOpen: false,
			modal: true,
			buttons: buttons,
			close: function() {
			}
		});
	},
});
