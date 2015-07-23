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
 *
 * @class AppJS
 * @augments Class
 */
var AppJS = Class.extend(
{
	/**
	 * Internal application name - override this
	 */
	appname: '',

	/**
	 * Internal reference to etemplate2 widget tree
	 *
	 * @var {et2_container}
	 */
	et2: null,

	/**
	 * Internal reference to egw client-side api object for current app and window
	 *
	 * @var {egw}
	 */
	egw: null,

	/**
	 * Initialization and setup goes here, but the etemplate2 object
	 * is not yet ready.
	 */
	init: function() {
		window.app[this.appname] = this;

		this.egw = egw(this.appname, window);

		// Initialize sidebox for non-popups.
		// ID set server side
		if(!this.egw.is_popup())
		{
			var sidebox = jQuery('#favorite_sidebox_'+this.appname);
			if(sidebox.length == 0 && egw_getFramework() != null)
			{
				var egw_fw = egw_getFramework();
				sidebox= $j('#favorite_sidebox_'+this.appname,egw_fw.sidemenuDiv);
			}
			// Make sure we're running in the top window when we init sidebox
			if(window.top.app[this.appname] !== this && window.top.app[this.appname])
			{
				window.top.app[this.appname]._init_sidebox(sidebox);
			}
			else
			{
				this._init_sidebox(sidebox);
			}
		}
	},

	/**
	 * Clean up any created objects & references
	 */
	destroy: function() {
		delete this.et2;
		if (this.sidebox)
			this.sidebox.off();
		delete this.sidebox;
		delete window.app[this.appname];
	},

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().  Note that this can be called
	 * several times, with different et2 objects, as templates are loaded.
	 *
	 * @param {etemplate2} et2
	 * @param {string} name template name
	 */
	et2_ready: function(et2,name) {
		if(this.et2 !== null)
		{
			egw.debug('log', "Changed et2 object");
		}
		this.et2 = et2.widgetContainer;
		this._fix_iFrameScrolling();
		if (this.egw && this.egw.is_popup()) this._set_Window_title();

		// Highlights the favorite based on initial list state
		this.highlight_favorite();
	},

	/**
	 * Observer method receives update notifications from all applications
	 *
	 * App is responsible for only reacting to "messages" it is interested in!
	 *
	 * @param {string} _msg message (already translated) to show, eg. 'Entry deleted'
	 * @param {string} _app application name
	 * @param {(string|number)} _id id of entry to refresh or null
	 * @param {string} _type either 'update', 'edit', 'delete', 'add' or null
	 * - update: request just modified data from given rows.  Sorting is not considered,
	 *		so if the sort field is changed, the row will not be moved.
	 * - edit: rows changed, but sorting may be affected.  Requires full reload.
	 * - delete: just delete the given rows clientside (no server interaction neccessary)
	 * - add: requires full reload for proper sorting
	 * @param {string} _msg_type 'error', 'warning' or 'success' (default)
	 * @param {object|null} _links app => array of ids of linked entries
	 * or null, if not triggered on server-side, which adds that info
	 * @return {false|*} false to stop regular refresh, thought all observers are run
	 */
	observer: function(_msg, _app, _id, _type, _msg_type, _links)
	{

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
		var id_app = _senders[0].id.split('::');
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
	 * @param {string} template template name to check, instead of trying all templates of current app
	 * @return {boolean} false - Returns false to stop event propagation
	 */
	setState: function(state, template)
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

		// Check for egw.open() parameters
		if(state.state && state.state.id && state.state.app)
		{
			return egw.open(state.state,undefined,undefined,{},'_self');
		}

		// Try and find a nextmatch widget, and set its filters
		var nextmatched = false;
		var et2 = template ? etemplate2.getByTemplate(template) : etemplate2.getByApplication(this.appname);
		for(var i = 0; i < et2.length; i++)
		{
			et2[i].widgetContainer.iterateOver(function(_widget) {
				// Firefox has trouble with spaces in search
				if(state.state && state.state.search) state.state.search = unescape(state.state.search);

				// Apply
				if(state.state && state.state.sort && state.state.sort.id)
				{
					_widget.sortBy(state.state.sort.id, state.state.sort.asc,false);
				}
				if(state.state && state.state.selectcols)
				{
					// Make sure it's a real array, not an object, then set cols
					_widget.set_columns(jQuery.extend([],state.state.selectcols));
				}
				_widget.applyFilters(state.state || state.filter || {});
				nextmatched = true;
			}, this, et2_nextmatch);
			if(nextmatched) return false;
		}

		// 'blank' is the special name for no filters, send that instead of the nice translated name
		var safe_name = jQuery.isEmptyObject(state) || jQuery.isEmptyObject(state.state||state.filter) ? 'blank' : state.name.replace(/[^A-Za-z0-9-_]/g, '_');
		var url = '/'+this.appname+'/index.php';

		// Try a redirect to list, if app defines a "list" value in registry
		if (egw.link_get_registry(this.appname, 'list'))
		{
			url = egw.link('/index.php', jQuery.extend({'favorite': safe_name}, egw.link_get_registry(this.appname, 'list')));
		}
		// if no list try index value from application
		else if (egw.app(this.appname).index)
		{
			url = egw.link('/index.php', 'menuaction='+egw.app(this.appname).index+'&favorite='+safe_name);
		}
		egw.open_link(url, undefined, undefined, this.appname);
		return false;
	},

	/**
	 * Retrieve the current state of the application for future restoration
	 *
	 * The state can be anything, as long as it's an object.  The contents are
	 * application specific.  The default implementation finds a nextmatch and
	 * returns its value.
	 * The return value of this function cannot be passed directly to setState(),
	 * since setState is expecting an additional wrapper, eg:
	 * {name: 'something', state: getState()}
	 *
	 * @return {object} Application specific map representing the current state
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

	/**
	 * Initializes actions and handlers on sidebox (delete)
	 *
	 * @param {jQuery} sidebox jQuery of DOM node
	 */
	_init_sidebox: function(sidebox)
	{
		if(sidebox.length)
		{
			var self = this;
			if(this.sidebox) this.sidebox.off();
			this.sidebox = sidebox;
			sidebox
				.off()
				// removed .on("mouse(enter|leave)" (wrapping trash icon), as it stalls delete in IE11
				.on("click.sidebox","div.ui-icon-trash", this, this.delete_favorite)
				// need to install a favorite handler, as we switch original one off with .off()
				.on('click.sidebox','li[data-id]', this, function(event) {
					var li = $j(this);
					li.siblings().removeClass('ui-state-highlight');

					// Wait an arbitrary 50ms to avoid having the class removed again
						// by the change handler.
					if(li.attr('data-id') !== 'blank')
					{
						window.setTimeout(function() {
							li.addClass('ui-state-highlight');
						},50);
					}
					
					var href = jQuery('a[href^="javascript:"]', this).prop('href');
					var matches = href ? href.match(/^javascript:([^\(]+)\((.*)?\);?$/) : null;
					if (matches && matches.length > 1 && matches[2] !== undefined)
					{
						event.stopImmediatePropagation();
						self.setState.call(self, JSON.parse(decodeURI(matches[2])));
						return false;
					}
				})
				.addClass("ui-helper-clearfix");

			//Add Sortable handler to sideBox fav. menu
			jQuery('ul','#favorite_sidebox_'+this.appname).sortable({
					items:'li:not([data-id$="add"])',
					placeholder:'ui-fav-sortable-placeholder',
					delay:250, //(millisecond) delay before the sorting should start
					helper: function(event, item) {
						// We'll need to know which app this is for
						item.attr('data-appname',self.appname);
						// Create custom helper so it can be dragged to Home
						var h_parent = item.parent().parent().clone();
						h_parent.find('li').not('[data-id="'+item.attr('data-id')+'"]').remove();
						h_parent.appendTo('body');
						return h_parent;
					},
					refreshPositions: true,
					update: function (event, ui)
					{
						var favSortedList = jQuery(this).sortable('toArray', {attribute:'data-id'});

						self.egw.set_preference(self.appname,'fav_sort_pref',favSortedList);

						self._refresh_fav_nm();
					}
				});

			// Bind favorite de-select
			var egw_fw = egw_getFramework();
			if(egw_fw && egw_fw.applications[this.appname] && egw_fw.applications[this.appname].browser
				&& egw_fw.applications[this.appname].browser.baseDiv)
			{
				$j(egw_fw.applications[this.appname].browser.baseDiv)
					.off('.sidebox')
					.on('change.sidebox', function() {
						self.highlight_favorite();
					});
			}
			return true;
		}
		return false;
	},

	/**
	 * Add a new favorite
	 *
	 * Fetches the current state from the application, then opens a dialog to get the
	 * name and other settings.  If user proceeds, the favorite is saved, and if possible
	 * the sidebox is directly updated to include the new favorite
	 *
	 * @param {object} [state] State settings to be merged into the application state
	 */
	add_favorite: function(state)
	{
		if(typeof this.favorite_popup == "undefined")
		{
			this._create_favorite_popup();
		}
		// Get current state
		this.favorite_popup.state = jQuery.extend({}, this.getState(), state||{});
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
		};
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

		// Stop the normal bubbling if this is called on click
		return false;
	},

	/**
	 * Update favorite items in nm fav. menu
	 *
	 */
	_refresh_fav_nm: function ()
	{
		var self = this;

		if(etemplate2 && etemplate2.getByApplication)
		{
			var et2 = etemplate2.getByApplication(self.appname);
			for(var i = 0; i < et2.length; i++)
			{
				et2[i].widgetContainer.iterateOver(function(_widget) {
					_widget.stored_filters = _widget.load_favorites(self.appname);
					_widget.init_filters(_widget);
				}, self, et2_favorites);
			}
		}
		else
		{
			throw new Error ("_refresh_fav_nm():Either et2 is  not ready/ not there yet. Make sure that etemplate2 is ready before call this method.");
		}
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
			this.favorite_popup.group.loadingFinished();
		}

		var buttons = {};
		buttons['save'] = {
			text: this.egw.lang('save'),
			default: true,
			click: function() {
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
						self.egw.jsonq(self.appname+'.egw_framework.ajax_set_favorite.template',
							[
								self.appname,
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
						self.egw.set_preference(self.appname,favorite_pref,favorite);
					}

					// Add to list immediately
					if(self.sidebox)
					{
						// Remove any existing with that name
						$j('[data-id="'+safe_name+'"]',self.sidebox).remove();

						// Create new item
						var html = "<li data-id='"+safe_name+"' data-group='" + favorite.group + "' class='ui-menu-item' role='menuitem'>\n";
						var href = 'javascript:app.'+self.appname+'.setState('+JSON.stringify(favorite)+');';
						html += "<a href='"+href+"' class='ui-corner-all' tabindex='-1'>";
						html += "<div class='" + 'sideboxstar' + "'></div>"+
							favorite.name;
						html += "<div class='ui-icon ui-icon-trash' title='" + egw.lang('Delete') + "'/>";
						html += "</a></li>\n";
						$j(html).insertBefore($j('li',self.sidebox).last());
						self._init_sidebox(self.sidebox);
					}

					// Try to update nextmatch favorites too
					self._refresh_fav_nm();
				}
				// Reset form
				delete self.favorite_popup.state;
				name.val("");
				$j("#filters",self.favorite_popup).empty();

				$j(this).dialog("close");
			}
		};
		buttons[this.egw.lang("cancel")] = function() {
			if(typeof self.favorite_popup.group !== 'undefined' && self.favorite_popup.group.set_value)
			{
				self.favorite_popup.group.set_value(null);
			}
			$j(this).dialog("close");
		};

		this.favorite_popup.dialog({
			autoOpen: false,
			modal: true,
			buttons: buttons,
			close: function() {
			}
		});

		// Bind handler for enter keypress
		this.favorite_popup.off('keydown').on('keydown', jQuery.proxy(function(e) {
			 var tagName = e.target.tagName.toLowerCase();
			tagName = (tagName === 'input' && e.target.type === 'button') ? 'button' : tagName;

			if(e.keyCode == jQuery.ui.keyCode.ENTER && tagName !== 'textarea' && tagName !== 'select' && tagName !=='button')
			{
				e.preventDefault();
				$j('button[default]',this.favorite_popup.parent()).trigger('click');
				return false;
			}
		},this));

		return false;
	},

	/**
	 * Delete a favorite from the list and update preferences
	 * Registered as a handler on the delete icons
	 *
	 * @param {jQuery.event} event event object
	 */
	delete_favorite: function(event)
	{
		// Don't do the menu
		event.stopImmediatePropagation();

		var app = event.data;
		var id = $j(this).parentsUntil('li').parent().attr("data-id");
		var group = $j(this).parentsUntil('li').parent().attr("data-group") || '';
		var line = $j('li[data-id="'+id+'"]',app.sidebox);
		var name = line.first().text();
		var trash = this;
		line.addClass('loading');

		// Make sure first
		var do_delete = function(button_id)
		{
			if(button_id != et2_dialog.YES_BUTTON)
			{
				line.removeClass('loading');
				return;
			}

			// Hide the trash
			$j(trash).hide();

			// Delete preference server side
			var request = egw.json(app.appname + ".egw_framework.ajax_set_favorite.template",
				[app.appname, id, "delete", group, ''],
				function(result) {
					// Got the full response from callback, which we don't want
					if(result.type) return;

					if(result && typeof result == 'boolean')
					{
						// Remove line from list
						line.slideUp("slow", function() { });

						app._refresh_fav_nm();
					}
					else
					{
						// Something went wrong server side
						line.removeClass('loading').addClass('ui-state-error');
					}
				},
				$j(trash).parentsUntil("li").parent(),
				true,
				$j(trash).parentsUntil("li").parent()
			);
			request.sendRequest(true);
		};
		et2_dialog.show_dialog(do_delete, (egw.lang("Delete") + " " +name +"?"),
			"Delete", et2_dialog.YES_NO, et2_dialog.QUESTION_MESSAGE);

		return false;
	},

	/**
	 * Mark the favorite closest matching the current state
	 *
	 * Closest matching takes into account not set values, so we pick the favorite
	 * with the most matching values without a value that differs.
	 */
	highlight_favorite: function() {
		if(!this.sidebox) return;

		var state = this.getState();
		var best_match = false;
		var best_count = 0;

		$j('li[data-id]',this.sidebox).removeClass('ui-state-highlight');

		$j('li[data-id] a[href^="javascript:"]',this.sidebox).each(function(i,href) {

			var matches = href.href ? href.href.match(/^javascript:([^\(]+)\((.*)?\);?$/) : null;
			var favorite = {}
			if (matches && matches.length > 1 && matches[2] !== undefined)
			{
				favorite = JSON.parse(decodeURI(matches[2]));
			}
			if(!favorite || jQuery.isEmptyObject(favorite)) return;
			
			var match_count = 0;
			for(var state_key in state)
			{
				if(typeof favorite.state != 'undefined' && typeof state[state_key] != 'undefined'&&typeof favorite.state[state_key] != 'undefined' && ( state[state_key] == favorite.state[state_key] || !state[state_key] && !favorite.state[state_key]))
				{
					match_count++;
				}
				else if (typeof state[state_key] != 'undefined' && state[state_key] && typeof state[state_key] === 'object' 
							&& typeof favorite.state != 'undefined' && typeof favorite.state[state_key] != 'undefined' && favorite.state[state_key] && typeof favorite.state[state_key] === 'object')
				{
					if((typeof state[state_key].length !== 'undefined' || typeof state[state_key].length !== 'undefined')
							&& (state[state_key].length || Object.keys(state[state_key]).length) != (favorite.state[state_key].length || Object.keys(favorite.state[state_key]).length ))
					{
						// State or favorite has a length, but the other does not
						if((state[state_key].length === 0 || Object.keys(state[state_key]).length === 0) &&
							(favorite.state[state_key].length == 0 || Object.keys(favorite.state[state_key]).length === 0))
						{
							// Just missing, or one is an array and the other is an object
							continue;
						}
						// One has a value and the other doesn't, no match
						return;
					}
					// Consider sub-objects (column filters) individually
					for(var sub_key in state[state_key])
					{
						if(state[state_key][sub_key] == favorite.state[state_key][sub_key] || !state[state_key][sub_key] && !favorite.state[state_key][sub_key])
						{
							match_count++;
						}
						else if (state[state_key][sub_key] && favorite.state[state_key][sub_key] &&
							typeof state[state_key][sub_key] === 'object' && typeof favorite.state[state_key][sub_key] === 'object')
						{
							// Too deep to keep going, just string compare for perfect match
							if(JSON.stringify(state[state_key][sub_key]) === JSON.stringify(favorite.state[state_key][sub_key]))
							{
								match_count++;
							}
						}
						else if(state[state_key][sub_key] && state[state_key][sub_key] != favorite.state[state_key][sub_key])
						{
							// Different values, do not match
							return;
						}

					}
				}
				else if (state_key == 'selectcols')
				{
					// Skip, might be set, might not
				}
				else if (typeof state[state_key] !== 'undefined' 
						 && typeof favorite.state != 'undefined'&&typeof favorite.state[state_key] !== 'undefined'
						 && state[state_key] != favorite.state[state_key])
				{
					// Different values, do not match
					return;
				}
			}
			if(match_count > best_count)
			{
				best_match = href.parentNode.dataset.id;
				best_count = match_count;
			}
		});
		if(best_match)
		{
			$j('li[data-id="'+best_match+'"]',this.sidebox).addClass('ui-state-highlight');
		}
	},

	/**
	 * Fix scrolling iframe browsed by iPhone/iPod/iPad touch devices
	 */
	_fix_iFrameScrolling: function()
	{
		if (/iPhone|iPod|iPad/.test(navigator.userAgent))
		{
			jQuery("iframe").on({
				load: function()
				{
					var body = this.contentWindow.document.body;

					var div = jQuery(document.createElement("div"))
							.css ({
								'height' : jQuery(this.parentNode).height(),
								'width' : jQuery(this.parentNode).width(),
								'overflow' : 'scroll'});
					while (body.firstChild)
					{
						div.append(body.firstChild);
					}
					jQuery(body).append(div);
				}
			});
		}
	},

	/**
	 * Set document title, uses getWindowTitle to get the correct title,
	 * otherwise set it with uniqueID as default title
	 */
	_set_Window_title: function ()
	{
		var title = this.getWindowTitle();
		if (title)
		{
			document.title = this.et2._inst.uniqueId + ": " + title;
		}
	},

	/**
	 * Window title getter function in order to set the window title
	 * this can be overridden on each application app.js file to customize the title value
	 *
	 * @returns {string} window title
	 */
	getWindowTitle: function ()
	{
		var titleWidget = this.et2.getWidgetById('title');
		if (titleWidget)
		{
			return titleWidget.options.value;
		}
		else
		{
			return this.et2._inst.uniqueId;
		}
	},

	/**
	 * Handler for drag and drop when dragging nextmatch rows from mail app
	 * and dropped on a row in the current application.  We copy the mail into
	 * the filemanager to link it since we can't link directly.
	 *
	 * This doesn't happen automatically.  Each application must indicate that
	 * it will accept dropped mail by it's nextmatch actions:
	 *
	 * $actions['info_drop_mail'] = array(
	 *		'type' => 'drop',
	 *		'acceptedTypes' => 'mail',
	 *		'onExecute' => 'javaScript:app.infolog.handle_dropped_mail',
	 *		'hideOnDisabled' => true
	 *	);
	 *
	 * This action, when defined, will not affect the automatic linking between
	 * normal applications.
	 *
	 * @param {egwAction} _action
	 * @param {egwActionObject[]} _selected Dragged mail rows
	 * @param {egwActionObject} _target Current application's nextmatch row the mail was dropped on
	 */
	handle_dropped_mail: function(_action, _selected, _target)
	{
		/**
		 * Mail doesn't support link system, so we copy it to VFS
		 */
		var ids = _target.id.split("::");
		if(ids.length != 2 || ids[0] == 'mail') return;

		var vfs_path = "/apps/"+ids[0]+"/"+ids[1];
		var mail_ids = [];

		for(var i = 0; i < _selected.length; i++)
		{
			mail_ids.push(_selected[i].id);
		}
		if(mail_ids.length)
		{
			egw.message(egw.lang("Please wait..."));
			this.egw.json('filemanager.filemanager_ui.ajax_action',['mail',mail_ids, vfs_path],function(data){
				// Trigger an update (minimal, no sorting changes) to display the new link
				egw.refresh(data.msg||'',ids[0],ids[1],'update');
			}).sendRequest(true);
		}
	},

	/**
	 * Check if Mailvelope is available, open (or create) "egroupware" keyring and call callback with it
	 *
	 * @param {function} _callback called if and only if mailvelope is available (context is this!)
	 */
	mailvelopeAvailable: function(_callback)
	{
		var self = this;
		var callback = jQuery.proxy(_callback, this);

		if (typeof mailvelope !== 'undefined')
		{
			this.mailvelopeOpenKeyring().then(callback);
		}
		else
		{
			jQuery(window).on('mailvelope', function()
			{
				self.mailvelopeOpenKeyring().then(callback);
			});
		}
	},

	/**
	 * PGP begin and end tags
	 */
	begin_pgp_message: '-----BEGIN PGP MESSAGE-----',
	end_pgp_message: '-----END PGP MESSAGE-----',

	/**
	 * Mailvelope "egroupware" Keyring
	 */
	mailvelope_keyring: undefined,

	/**
	 * jQuery selector for Mailvelope iframes in all browsers
	 */
	mailvelope_iframe_selector: 'iframe[src^="chrome-extension"],iframe[src^="about:blank?mvelo"]',

	/**
	 * Open (or create) "egroupware" keyring and call callback with it
	 *
	 * @returns {Promise.<Keyring, Error>} Keyring or Error with message
	 */
	mailvelopeOpenKeyring: function()
	{
		var self = this;

		return new Promise(function(_resolve, _reject)
		{
			if (self.mailvelope_keyring) _resolve(self.mailvelope_keyring);

			var resolve = _resolve;
			var reject = _reject;

			mailvelope.getKeyring('egroupware').then(function(_keyring)
			{
				self.mailvelope_keyring = _keyring;

				resolve(_keyring);
			},
			function(_err)
			{
				mailvelope.createKeyring('egroupware').then(function(_keyring)
				{
					self.mailvelope_keyring = _keyring;
					var mvelo_settings_selector = self.mailvelope_iframe_selector
						.split(',').map(function(_val){return 'body>'+_val;}).join(',');

					mailvelope.createSettingsContainer('body', _keyring, {
						email: self.egw.user('account_email'),
						fullName: self.egw.user('account_fullname')
					}).then(function()
					{
						// make only Mailvelope settings dialog visible
						jQuery(mvelo_settings_selector).css({position: 'absolute', top: 0});
						// add a close button, so we know when to offer storing public key to AB
						jQuery('<button class="et2_button et2_button_text" id="mailvelope_close_settings">'+self.egw.lang('Close')+'</button>')
							.css({position: 'absolute', top: 8, right: 8})
							.click(function()
							{
								// try fetching public key, to check user created onw
								self.mailvelope_keyring.exportOwnPublicKey(self.egw.user('account_email')).then(function(_pubKey)
								{
									// if yes, hide settings dialog
									jQuery(mvelo_settings_selector).remove();
									jQuery('button#mailvelope_close_settings').remove();
									// offer user to store his public key to AB for other users to find
									var buttons = [
										{button_id: 2, text: 'Yes', id: 'dialog[yes]', image: 'check', default: true},
										{button_id: 3, text : 'No', id: 'dialog[no]', image: 'cancelled'}
									];
									if (egw.user('apps').admin)
									{
										buttons.unshift({
											button_id: 5, text: 'Yes and allow non-admin users to do that too (recommended)',
											id: 'dialog[yes_allow]', image: 'check', default: true
										});
										delete buttons[1].default;
									}
									et2_dialog.show_dialog(function (_button_id)
									{
										if (_button_id != et2_dialog.NO_BUTTON )
										{
											var keys = {};
											keys[self.egw.user('account_id')] = _pubKey;
											self.egw.json('addressbook.addressbook_bo.ajax_set_pgp_keys',
												[keys, _button_id != et2_dialog.YES_BUTTON ? true : undefined]).sendRequest()
											.then(function(_data)
											{
												self.egw.message(_data.response['0'].data);
											});
										}
									},
									self.egw.lang('It is recommended to store your public key in addressbook, so other users can write you encrypted mails.'),
									self.egw.lang('Store your public key in Addressbook?'),
									{}, buttons, et2_dialog.QUESTION_MESSAGE, undefined, self.egw);
								},
								function(_err){
									self.egw.message(_err.message+"\n\n"+
									self.egw.lang("You will NOT be able to send or receive encrypted mails before completing that step!"), 'error');
								});
							})
							.appendTo('body');
					});
					resolve(_keyring);
				},
				function(_err)
				{
					reject(_err);
				});
			});
		});
	},

	/**
	 * Mailvelope uses Domain without first part: eg. "stylite.de" for "egw.stylite.de"
	 *
	 * @returns {string}
	 */
	_mailvelopeDomain: function()
	{
		var parts = document.location.hostname.split('.');
		if (parts.length > 1) parts.shift();
		return parts.join('.');
	},

	/**
	 * Check if we have a key for all recipients
	 *
	 * @param {Array} _recipients
	 * @returns {Promise.<Array, Error>} Array of recipients or Error with recipients without key
	 */
	mailvelopeGetCheckRecipients: function(_recipients)
	{
		// replace rfc822 addresses with raw email, as Mailvelop does not like them and lowercase all email
		var rfc822_preg = /<([^'" <>]+)>$/;
		var recipients = _recipients.map(function(_recipient)
		{
			var matches = _recipient.match(rfc822_preg);
			return matches ? matches[1].toLowerCase() : _recipient.toLowerCase();
		});

		// check if we have keys for all recipients
		var self = this;
		return new Promise(function(_resolve, _reject)
		{
			var resolve = _resolve;
			var reject = _reject;
			self.mailvelopeOpenKeyring().then(function(_keyring)
			{
				var keyring = _keyring;
				_keyring.validKeyForAddress(recipients).then(function(_status)
				{
					var no_key = [];
					for(var email in _status)
					{
						if (!_status[email]) no_key.push(email);
					}
					if (no_key.length)
					{
						// server addressbook on server for missing public keys
						self.egw.json('addressbook.addressbook_bo.ajax_get_pgp_keys', [no_key]).sendRequest().then(function(_data)
						{
							var data = _data.response['0'].data;
							var promises = [];
							for(var email in data)
							{
								promises.push(keyring.importPublicKey(data[email]).then(function(_result)
								{
									if (_result == 'IMPORTED')
									{
										no_key.splice(no_key.indexOf(email),1);
									}
								}));
							}
							Promise.all(promises).then(function()
							{
								if (no_key.length)
								{
									reject(new Error(self.egw.lang('No key for recipient:')+' '+no_key.join(', ')));
								}
								else
								{
									resolve(recipients);
								}
							});
						});
					}
					else
					{
						resolve(recipients);
					}
				});
			},
			function(_err)
			{
				reject(_err);
			});
		});
	}
});
