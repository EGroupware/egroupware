/**
 * EGroupware clientside Application javascript base object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 */

import {EgwApp} from "./egw_app";
import './egw_inheritance.js';
import {etemplate2} from "../etemplate/etemplate2";
import {et2_createWidget} from "../etemplate/et2_core_widget";
import {Et2Dialog} from "../etemplate/Et2Dialog/Et2Dialog";
import {et2_nextmatch} from "../etemplate/et2_extension_nextmatch";
import "sortablejs/Sortable.min.js";
import {Et2Favorites} from "../etemplate/Et2Favorites/Et2Favorites";
import {egw_globalObjectManager} from "../egw_action/egw_action";

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
export const AppJS = (function(){ "use strict"; return Class.extend(
{
	/**
	 * Internal application name - override this
	 */
	appname: '',

	/**
	 * Internal reference to the most recently loaded etemplate2 widget tree
	 *
	 * NOTE: This variable can change which etemplate it points to as the user
	 * works.  For example, loading the home or admin apps can cause
	 * et2_ready() to be called again with a different template.  this.et2 will
	 * then point to a different template.  If the user then closes that tab,
	 * this.et2 will point to a destroyed object, and trying to use it will fail.
	 *
	 * If you need a reference to a certain template you can either store a local
	 * reference or access it through etemplate2.
	 *
	 * @example <caption>Store a local reference</caption>
	 *	// in et2_ready()
	 *	if(name == 'index') this.index_et2 = et2.widgetContainer;
	 *
	 *	// Remember to clean up in destroy()
	 *	delete this.index_et2;
	 *
	 *	// Instead of this.et2, using a local reference
	 *	this.index_et2 ...
	 *
	 *
	 * @example <caption>Access via etemplate2 object</caption>
	 * // Instead of this.et2, using it's unique ID
	 * var et2 = etemplate2.getById('myapp-index)
	 * if(et2)
	 * {
	 *		et2.widgetContainer. ...
	 * }
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
		this.egw = egw(this.appname, window);

		// Initialize sidebox for non-popups.
		// ID set server side
		if(!this.egw.is_popup())
		{
			var sidebox = jQuery('#favorite_sidebox_'+this.appname);
			if(sidebox.length == 0 && egw_getFramework() != null)
			{
				var egw_fw = egw_getFramework();
				sidebox= jQuery('#favorite_sidebox_'+this.appname,egw_fw.sidemenuDiv);
			}
			// Make sure we're running in the top window when we init sidebox
			if(window.app[this.appname] === this && window.top.app[this.appname] !== this && window.top.app[this.appname])
			{
				window.top.app[this.appname]._init_sidebox(sidebox);
			}
			else
			{
				this._init_sidebox(sidebox);
			}
		}
		// register with new TS app object to receive push and observer message
		EgwApp._register_instance(this);
	},

	/**
	 * Clean up any created objects & references
	 * @param {object} _app local app object
	 */
	destroy: function(_app) {
		delete this.et2;
		if (this.sidebox)
			this.sidebox.off();
		delete this.sidebox;
		if (!_app) delete app[this.appname];
		// deregister with new TS app object
		let index = -1;
		if((index = EgwApp._instances.indexOf(this)) >= 0)
		{
			EgwApp._instances.splice(index,1);
		}
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
		if (this.egw && this.egw.is_popup())
		{
			this._set_Window_title();
		}

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
	 * Handle a push notification about entry changes from the websocket
	 *
	 * Get's called for data of all apps, but should only handle data of apps it displays,
	 * which is by default only it's own, but can be for multiple apps eg. for calendar.
	 *
	 * @param  pushData
	 * @param {string} pushData.app application name
	 * @param {(string|number)} pushData.id id of entry to refresh or null
	 * @param {string} pushData.type either 'update', 'edit', 'delete', 'add' or null
	 * - update: request just modified data from given rows.  Sorting is not considered,
	 *		so if the sort field is changed, the row will not be moved.
	 * - edit: rows changed, but sorting may be affected.  Requires full reload.
	 * - delete: just delete the given rows clientside (no server interaction neccessary)
	 * - add: requires full reload for proper sorting
	 * @param {object|null} pushData.acl Extra data for determining relevance.  eg: owner or responsible to decide if update is necessary
	 * @param {number} pushData.account_id User that caused the notification
	 */
	push: function(pushData)
	{
		// don't care about other apps data, reimplement if your app does care eg. calendar
		if (pushData.app !== this.appname) return;

		// only handle delete by default, for simple case of uid === "$app::$id"
		if (pushData.type === 'delete' && egw.dataHasUID(this.uid(pushData)))
		{
			egw.refresh('', pushData.app, pushData.id, 'delete');
		}
	},

	/**
	 * Callback from nextmatch so application can have some control over
	 * where new rows (added via push) are added.  This is only called when
	 * the type is "add".
	 *
	 * @param nm Nextmatch the entry is going to be added to
	 * @param uid
	 * @param current_order
	 */
	nm_refresh_index: function(nm, uid, current_order)
	{
		// Do we have a modified field so we can check nm sort order?
		if(this.modification_field_name)
		{
			let value = nm.getValue();
			let sort = value.sort || {};

			if(sort && sort.id == this.modification_field_name && sort.asc == false)
			{
				// Sorting by modification time, DESC.  Put it at the top.
				return 0;
			}
		}
		// Don't actually add it in.
		return false;
	},

	/**
	 * Get (possible) app-specific uid
	 *
	 * @param {object} pushData see push method for individual attributes
	 */
	uid: function(pushData)
	{
		return pushData.app + "::" + pushData.id;
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
			Et2Dialog.show_dialog(function (button_id, value)
			{
				if (button_id != Et2Dialog.NO_BUTTON)
				{
					that._do_action(action_id, _elems);
				}
			}, confirm_msg, egw.lang('Confirmation required'), Et2Dialog.BUTTONS_YES_NO, Et2Dialog.QUESTION_MESSAGE);
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
			url = egw.link('/index.php', {'favorite': safe_name, ...egw.link_get_registry(this.appname, 'list')});
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
	 * Function to load selected row from nm into a template view
	 *
	 * @param {object} _action
	 * @param {object} _senders
	 * @param {boolean} _noEdit defines whether to set edit button or not default is false
	 * @param {function} et2_callback function to run after et2 is loaded
	 */
	viewEntry: function(_action, _senders, _noEdit, et2_callback)
	{
		//full id in nm
		var id = _senders[0].id;
		// flag for edit button
		var noEdit = _noEdit || false;
		// nm row id
		var rowID = '';
		// content to feed to etemplate2
		var content = {};

		var self = this;

		if (id){
			var parts = id.split('::');
			rowID = parts[1];
			content = egw.dataGetUIDdata(id);
			if (content.data) content = content.data;
		}

		// create a new app object with just constructors for our new etemplate2 object
		var app = { classes: window.app.classes };

		/* destroy generated etemplate for view mode in DOM*/
		var destroy = function(){
			self.viewContainer.remove();
			delete self.viewTemplate;
			delete self.viewContainer;
			delete self.et2_view;
			// we need to reference back into parent context this
			for (var v in self)
			{
				this[v] = self[v];
			}
			app = null;
		};

		// view container
		this.viewContainer = jQuery(document.createElement('div'))
				.addClass('et2_mobile_view')
				.css({
					"z-index":102,
					width:"100%",
					height:"100%",
					background:"white",
					display:'block',
					position: 'absolute',
					left:0,
					bottom:0,
					right:0,
					overflow:'auto'
				})
				.attr('id','popupMainDiv')
				.appendTo('body');

		// close button
		var close = jQuery(document.createElement('span'))
				.addClass('egw_fw_mobile_popup_close loaded')
				.click(function(){
					destroy.call(app[self.appname]);
					//disable selected actions after close
					egw_globalObjectManager.setAllSelected(false);
				})
				.appendTo(this.viewContainer);
		if (!noEdit)
		{
			// edit button
			var edit = jQuery(document.createElement('span'))
					.addClass('mobile-view-editBtn')
					.click(function(){
						egw.open(rowID, self.appname);
					})
					.appendTo(this.viewContainer);
		}
		// view template main container (content)
		this.viewTemplate = jQuery(document.createElement('div'))
				.attr('id', this.appname+'-view')
				.addClass('et2_mobile-view-container popupMainDiv')
				.appendTo(this.viewContainer);

		var mobileViewTemplate = (_action.data.mobileViewTemplate ||'edit').split('?');
		var templateName = mobileViewTemplate[0];
		var templateTimestamp = mobileViewTemplate[1];
		var templateURL = egw.webserverUrl+ '/' + this.appname + '/templates/mobile/'+templateName+'.xet'+'?'+templateTimestamp;

		var data = {
			'content': content,
			'readonlys': {'__ALL__':true,'link_to':false},
			'currentapp': this.appname,
			'langRequire': this.et2.getArrayMgr('langRequire').data,
			'sel_options': this.et2.getArrayMgr('sel_options').data,
			'modifications': this.et2.getArrayMgr('modifications').data,
			'validation_errors': this.et2.getArrayMgr('validation_errors').data
		};

		// etemplate2 object for view
		this.et2_view = new etemplate2 (this.viewTemplate[0], false);
		framework.pushState('view');
		if(templateName)
		{
			this.et2_view.load(this.appname+'.'+templateName,templateURL, data, typeof et2_callback == 'function'?et2_callback:function(){}, app);
		}

		// define a global close function for view template
		// in order to be able to destroy view on action
		this.et2_view.close = destroy;
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
					var li = jQuery(this);
					li.siblings().removeClass('ui-state-highlight');

					var state = {};
					var pref = egw.preference('favorite_' + this.dataset.id, self.appname);
					if(pref)
					{
						// Extend, to prevent changing the preference by reference
						jQuery.extend(true, state, pref);
					}
					if(this.dataset.id != 'add')
					{
						event.stopImmediatePropagation();
						self.setState.call(self, state);
						return false;
					}
				})
				.addClass("ui-helper-clearfix");

			let el = document.getElementById('favorite_sidebox_' + this.appname)?.getElementsByTagName('ul')[0];
			if(el && el instanceof HTMLElement)
			{
				let sortablejs = Sortable.create(el, {
					ghostClass: 'ui-fav-sortable-placeholder',
					draggable: 'li:not([data-id$="add"])',
					delay: 25,
					dataIdAttr: 'data-id',
					onSort: function(event)
					{
						let favSortedList = sortablejs.toArray();
						self.egw.set_preference(self.appname, 'fav_sort_pref', favSortedList);
						self._refresh_fav_nm();
					}
				});
			}

			// Bind favorite de-select
			var egw_fw = egw_getFramework();
			if(egw_fw && egw_fw.applications[this.appname] && egw_fw.applications[this.appname].browser
				&& egw_fw.applications[this.appname].browser.baseDiv)
			{
				jQuery(egw_fw.applications[this.appname].browser.baseDiv)
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
		alert('To use favorites port your app.js to TypeScript!');

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
				}, self, Et2Favorites);
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
		alert('To use favorites port your app.js to TypeScript!');

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
		var id = jQuery(this).parentsUntil('li').parent().attr("data-id");
		var group = jQuery(this).parentsUntil('li').parent().attr("data-group") || '';
		var line = jQuery('li[data-id="'+id+'"]',app.sidebox);
		var name = line.first().text();
		var trash = this;
		line.addClass('loading');

		// Make sure first
		var do_delete = function(button_id)
		{
			if (button_id != Et2Dialog.YES_BUTTON)
			{
				line.removeClass('loading');
				return;
			}

			// Hide the trash
			jQuery(trash).hide();

			// Delete preference server side
			var request = egw.json("EGroupware\\Api\\Framework::ajax_set_favorite",
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
				jQuery(trash).parentsUntil("li").parent(),
				true,
				jQuery(trash).parentsUntil("li").parent()
			);
			request.sendRequest(true);
		};
		Et2Dialog.show_dialog(do_delete, (egw.lang("Delete") + " " + name + "?"),
			egw.lang("Delete"), Et2Dialog.BUTTONS_YES_NO, Et2Dialog.QUESTION_MESSAGE);

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
		var self = this;

		jQuery('li[data-id]',this.sidebox).removeClass('ui-state-highlight');

		jQuery('li[data-id]',this.sidebox).each(function(i,href) {
			var favorite = {};
			if(this.dataset.id && egw.preference('favorite_'+this.dataset.id,self.appname))
			{
				favorite = egw.preference('favorite_'+this.dataset.id,self.appname);
			}
			if(!favorite || jQuery.isEmptyObject(favorite)) return;

			// Handle old style by making it like new style
			if(favorite.filter && !favorite.state)
			{
				favorite.state = favorite.filter;
			}

			var match_count = 0;
			var extra_keys = Object.keys(favorite.state);
			for(var state_key in state)
			{
				extra_keys.splice(extra_keys.indexOf(state_key),1);
				if(typeof favorite.state != 'undefined' && typeof state[state_key] != 'undefined'&&typeof favorite.state[state_key] != 'undefined' && ( state[state_key] == favorite.state[state_key] || !state[state_key] && !favorite.state[state_key]))
				{
					match_count++;
				}
				else if (state_key == 'selectcols')
				{
					// Skip, might be set, might not
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
					else if (state[state_key].length !== 'undefined' && typeof favorite.state[state_key].length !== 'undefined' &&
						state[state_key].length === 0 && favorite.state[state_key].length === 0)
					{
						// Both set, but both empty
						match_count++;
						continue;
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
						else if(typeof state[state_key][sub_key] !== 'undefined' && state[state_key][sub_key] != favorite.state[state_key][sub_key])
						{
							// Different values, do not match
							return;
						}
					}
				}
				else if (typeof state[state_key] !== 'undefined'
						 && typeof favorite.state != 'undefined'&&typeof favorite.state[state_key] !== 'undefined'
						 && state[state_key] != favorite.state[state_key])
				{
					// Different values, do not match
					return;
				}
			}
			// Check for anything set that the current one does not have
			for(var i = 0; i < extra_keys.length; i++)
			{
				if(favorite.state[extra_keys[i]]) return;
			}
			if(match_count > best_count)
			{
				best_match = this.dataset.id;
				best_count = match_count;
			}
		});
		if(best_match)
		{
			jQuery('li[data-id="'+best_match+'"]',this.sidebox).addClass('ui-state-highlight');
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
	 * mailvelope object contains SyncHandlers
	 *
	 * @property {function} descriptionuploadSync function called by Mailvelope to upload encrypted private key backup
	 * @property {function} downloadSync function called by Mailvelope to download encrypted private key backup
	 * @property {function} backup function called by Mailvelope to upload a public keyring backup
	 * @property {function} restore function called by Mailvelope to restore a public keyring backup
	 */
	mailvelopeSyncHandlerObj: {
		/**
		 * function called by Mailvelope to upload a public keyring
		 * @param {UploadSyncHandler} _uploadObj
		 *	@property {string} etag entity tag for the uploaded encrypted keyring, or null if initial upload
		 *	@property {AsciiArmored} keyringMsg encrypted keyring as PGP armored message
		 * @returns {Promise.<UploadSyncReply, Error>}
		 */
		uploadSync: function(_uploadObj)
		{
			return new Promise(function(_resolve,_reject){});
		},

		/**
		 * function called by Mailvelope to download a public keyring
		 *
		 * @param {object} _downloadObj
		 *	@property {string} etag entity tag for the current local keyring, or null if no local eTag
		 * @returns {Promise.<DownloadSyncReply, Error>}
		 */
		downloadSync: function(_downloadObj)
		{
			return new Promise(function(_resolve,_reject){});
		},

		/**
		 * function called by Mailvelope to upload an encrypted private key backup
		 *
		 * @param {BackupSyncPacket} _backup
		 *	@property {AsciiArmored} backup an encrypted private key as PGP armored message
		 * @returns {Promise.<undefined, Error>}
		 */
		backup: function(_backup)
		{
			return new Promise(function(_resolve,_reject){
				// Store backup sync packet into .PGP-Key-Backup file in user directory
				jQuery.ajax({
					method:'PUT',
					url: egw.webserverUrl+'/webdav.php/home/'+egw.user('account_lid')+'/.PGP-Key-Backup',
					contentType: 'application/json',
					data: JSON.stringify(_backup),
					success:function(){
						_resolve(_backup);
					},
					error: function(_err){
						_reject(_err);
					}
				});
			});
		},

		/**
		 * function called by Mailvelope to restore an encrypted private key backup
		 *
		 * @returns {Promise.<BackupSyncPacket, Error>}
		 * @todo
		 */
		restore: function()
		{
			return new Promise(function(_resolve,_reject){
				var resolve = _resolve;
				var reject = _reject;
				jQuery.ajax({
					url:egw.webserverUrl+'/webdav.php/home/'+egw.user('account_lid')+'/.PGP-Key-Backup',
					method: 'GET',
					success: function(_backup){
						resolve(JSON.parse(_backup));
						egw.message('Your key has been restored successfully.');
					},
					error: function(_err){
						//Try with old back file name
						if (_err.status == 404)
						{
							jQuery.ajax({
								method:'GET',
								url: egw.webserverUrl+'/webdav.php/home/'+egw.user('account_lid')+'/.PK_PGP',
								success: function(_backup){
									resolve(JSON.parse(_backup));
									egw.message('Your key has been restored successfully.');
								},
								error: function(_err){
									_reject(_err);
								}
							});
						}
						else
						{
							_reject(_err);
						}
					}
				});
			});
		}
	},

	/**
	 * Function for backup file operations
	 *
	 * @param {type} _url Url of the backup file
	 * @param {type} _cmd command to operate
	 *	- PUT: to store backup file
	 *	- GET: to read backup file
	 *	- DELETE: to delete backup file
	 *
	 * @param {type} _successCallback function called when the operation is successful
	 * @param {type} _errorCallback function called when the operation fails
	 * @param {type} _data data which needs to be stored in file via PUT command
	 */
	_mailvelopeBackupFileOperator: function(_url, _cmd, _successCallback, _errorCallback, _data)
	{
		var ajaxObj = {
			url: _url || egw.webserverUrl+'/webdav.php/home/'+egw.user('account_lid')+'/.PGP-Key-Backup',
			method: _cmd,
			success: _successCallback,
			error: _errorCallback
		};
		switch (_cmd)
		{
			case 'PUT':
				jQuery.extend({},ajaxObj, {
					data: JSON.stringify(_data),
					contentType: 'application/json'
				});
				break;
			case 'GET':
				jQuery.extend({},ajaxObj, {
					dataType: 'json'
				});
				break;
			case 'DELETE':
				break;
		}
		jQuery.ajax(ajaxObj);
	},

	/**
	 * Create backup dialog
	 * @param {string} _selector DOM selector to attach backupDialog
	 * @param {boolean} _initSetup determine wheter it's an initialization backup or restore backup
	 *
	 * @returns {Promise.<backupPopupId, Error>}
	 */
	mailvelopeCreateBackupDialog: function(_selector, _initSetup)
	{
		var self = this;
		var selector = _selector || 'body';
		var initSetup = _initSetup;
		jQuery('iframe[src^="chrome-extension"],iframe[src^="about:blank?mvelo"]').remove();
		return new Promise(function(_resolve, _reject)
		{
			var resolve = _resolve;
			var reject = _reject;

			mailvelope.getKeyring('egroupware').then(function(_keyring)
			{
				_keyring.addSyncHandler(self.mailvelopeSyncHandlerObj);

				var options = {
					initialSetup:initSetup
				};
				_keyring.createKeyBackupContainer(selector, options).then(function(_popupId){
					_popupId.isReady().then(function(result){
						egw.message('Your key has been backedup into  .PGP-Key-Backup successfully.');
						jQuery(selector).empty();
					});
					resolve(_popupId);
				},
				function(_err){
					reject(_err);
				});
			},
			function(_err)
			{
				reject(_err);
			});
		});
	},

	/**
	 * Delete backup key from filesystem
	 */
	mailvelopeDeleteBackup: function()
	{
		var self = this;
		Et2Dialog.show_dialog(function (_button_id)
			{
				if (_button_id == Et2Dialog.YES_BUTTON)
				{
					self._mailvelopeBackupFileOperator(undefined, 'DELETE', function ()
					{
						self.egw.message(self.egw.lang('The backup key has been deleted.'));
					}, function (_err)
					{
						self.egw.message(self.egw.lang('Was not able to delete the backup key because %1', _err));
					});
				}
			},
			self.egw.lang('Are you sure, you would like to delete the backup key?'),
			self.egw.lang('Delete backup key'),
			{}, Et2Dialog.BUTTONS_YES_NO_CANCEL, Et2Dialog.QUESTION_MESSAGE, undefined, self.egw);
	},

	/**
	 * Create mailvelope restore dialog
	 * @param {string} _selector DOM selector to attach restorDialog
	 * @param {boolean} _restorePassword if true, will restore key password too
	 *
	 * @returns {Promise}
	 */
	mailvelopeCreateRestoreDialog: function(_selector, _restorePassword)
	{
		var self = this;
		var restorePassword = _restorePassword;
		var selector = _selector || 'body';
		//Clear the
		jQuery('iframe[src^="chrome-extension"],iframe[src^="about:blank?mvelo"]').remove();
		return new Promise(function(_resolve, _reject){
			var resolve = _resolve;
			var reject = _reject;

			mailvelope.getKeyring('egroupware').then(function(_keyring)
			{
				_keyring.addSyncHandler(self.mailvelopeSyncHandlerObj);

				var options = {
					restorePassword:restorePassword
				};
				_keyring.restoreBackupContainer(selector, options).then(function(_restoreId){
					var $restore_selector = jQuery('iframe[src^="chrome-extension"],iframe[src^="about:blank?mvelo"]');
					$restore_selector.css({position:'absolute', "z-index":1});
					resolve(_restoreId);
				},
				function(_err){
					reject(_err);
				});
			},
			function(_err)
			{
				reject(_err);
			});
		});
	},

	/**
	 * Create a dialog to show all backup/restore options
	 *
	 * @returns {undefined}
	 */
	mailvelopeCreateBackupRestoreDialog: function()
	{
		var self = this;
		var appname = egw.app_name();
		var menu = [
			// Header row should be empty item 0
			{},
			// Restore Keyring item 1
			{label:"Restore key" ,image:"lock", onclick:"app."+appname+".mailvelopeCreateRestoreDialog('#_mvelo')"},
			// Restore pass phrase item 2
			{label:"Restore password",image:"password", onclick:"app."+appname+".mailvelopeCreateRestoreDialog('#_mvelo', true)"},
			// Delete backup Key item 3
			{label:"Delete backup", image:"delete", onclick:"app."+appname+".mailvelopeDeleteBackup"},
			// Backup Key item 4
			{label:"Backup Key", image:"save", onclick:"app."+appname+".mailvelopeCreateBackupDialog('#_mvelo', false)"}
		];

		var dialog = function(_content, _callback)
		{
			return et2_createWidget("dialog", {
						callback: function(_button_id, _value) {
							if (typeof _callback == "function")
							{
								_callback.call(this, _button_id, _value.value);
							}
						},
						title: egw.lang('Backup/Restore'),
						buttons:[{"button_id": 'close',"text": egw.lang('Close'), id: 'dialog[close]', image: 'cancelled', "default":true}],
						value: {
							content: {
								menu:_content
							}
						},
						template: egw.webserverUrl+'/api/templates/default/pgp_backup_restore.xet',
						class: "pgp_backup_restore",
						modal:true,
						width: 700
			});
		};
		if (typeof mailvelope != 'undefined')
		{
			mailvelope.getKeyring('egroupware').then(function(_keyring)
			{
				self._mailvelopeBackupFileOperator(undefined, 'GET', function(_data){
					dialog(menu);
				},
				function(){
					// Remove delete item
					menu.splice(3,1);
					menu[3]['onclick'] = "app."+appname+".mailvelopeCreateBackupDialog('#_mvelo', true)";
					dialog(menu);
				});
			},
			function(){
				mailvelope.createKeyring('egroupware').then(function(){dialog(menu);});
			});
		}
		else
		{
			this.mailvelopeInstallationOffer();
		}
	},

	/**
	 * Create a dialog and offers installation option for installing mailvelope plugin
	 * plus it offers a video tutorials to get the user morte familiar with mailvelope
	 */
	mailvelopeInstallationOffer: function ()
	{
		var buttons = [
			{"text": egw.lang('Install'), id: 'install', image: 'check', "default":true},
			{"text": egw.lang('Close'), id:'close', image: 'cancelled'}
		];
		var dialog = function(_content, _callback)
		{
			return et2_createWidget("et2-dialog", {
				callback: function (_button_id, _value)
				{
					if (typeof _callback == "function")
					{
						_callback.call(this, _button_id, _value.value);
					}
				},
				title: egw.lang('PGP Encryption Installation'),
				buttons: buttons,
				dialog_type: 'info',
				value: {
					content: _content
				},
				template: egw.webserverUrl + '/api/templates/default/pgp_installation.xet',
				class: "pgp_installation",
				isModal: true
				//resizable:false,
			});
		};
		var content = [
			// Header row should be empty item 0
			{},
			{domain:this.egw.lang('Add your domain as "%1" in options to list of email providers and enable API.',
					'*.'+this._mailvelopeDomain()), video:"test", control:"true"}
		];

		document.body.append(dialog(content, function (_button)
		{
			if (_button == 'install')
			{
				if (typeof chrome != 'undefined')
				{
					// ATM we are not able to trigger mailvelope installation directly
					// since the installation should be triggered from the extension
					// owner validate website (mailvelope.com), therefore, we just redirect
					// user to chrome webstore to install mailvelope from there.
					window.open('https://chrome.google.com/webstore/detail/mailvelope/kajibbejlbohfaggdiogboambcijhkke');
				}
				else if (typeof InstallTrigger != 'undefined' && InstallTrigger.enabled())
				{
					InstallTrigger.install({mailvelope:"https://download.mailvelope.com/releases/latest/mailvelope.firefox.xpi"},
						function(_url, _status){
							if (_status == 0)
							{
								Et2Dialog.alert(egw.lang('Mailvelope addon installation succeded. Now you may configure the options.'));
								return;
							}
							else
							{
								Et2Dialog.alert(egw.lang('Mailvelope addon installation failed! Please try again.'));
							}
						});
				}
			}
		}));
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
							.css({position: 'absolute', top: 8, right: 8, "z-index":2})
							.click(function()
							{
								// try fetching public key, to check user created onw
								self.mailvelope_keyring.exportOwnPublicKey(self.egw.user('account_email')).then(function(_pubKey)
								{
									// CreateBackupDialog
									self.mailvelopeCreateBackupDialog().then(function(_popupId){
										jQuery('iframe[src^="chrome-extension"],iframe[src^="about:blank?mvelo"]').css({position:'absolute', "z-index":1});
									},
									function(_err){
										egw.message(_err);
									});

									// if yes, hide settings dialog
									jQuery(mvelo_settings_selector).each(function(index,item){
										if (!item.src.match(/keyBackupDialog.html/,'ig')) item.remove();
									});
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
									Et2Dialog.show_dialog(function (_button_id)
										{
											if (_button_id != Et2Dialog.NO_BUTTON)
											{
												var keys = {};
												keys[self.egw.user('account_id')] = _pubKey;
												self.egw.json('addressbook.addressbook_bo.ajax_set_pgp_keys',
													[keys, _button_id != Et2Dialog.YES_BUTTON ? true : undefined]).sendRequest()
													.then(function (_data)
													{
														self.egw.message(_data.response['0'].data);
													});
											}
										},
										self.egw.lang('It is recommended to store your public key in addressbook, so other users can write you encrypted mails.'),
										self.egw.lang('Store your public key in Addressbook?'),
										{}, buttons, Et2Dialog.QUESTION_MESSAGE, undefined, self.egw);
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
									if (_result == 'IMPORTED' || _result == 'UPDATED')
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
	},

	/**
	 * Check if the share action is enabled for this entry
	 *
	 * @param {egwAction} _action
	 * @param {egwActionObject[]} _entries
	 * @param {egwActionObject} _target
	 * @returns {boolean} if action is enabled
	 */
	is_share_enabled: function is_share_enabled(_action, _entries, _target)
	{
		return true;
	},
	/**
	 * create a share-link for the given entry
	 *
	 * @param {egwAction} _action egw actions
	 * @param {egwActionObject[]} _senders selected nm row
	 * @param {egwActionObject} _target Drag source.  Not used here.
	 * @param {Boolean} _writable Allow edit access from the share.
	 * @param {Boolean} _files Allow access to files from the share.
	 * @param {Function} _callback Callback with results
	 * @returns {Boolean} returns false if not successful
	 */
	share_link: function(_action, _senders, _target, _writable, _files, _callback){
		var path = _senders[0].id;
		if(!path)
		{
			return this.egw.message(this.egw.lang('Missing share path.  Unable to create share.'), 'error');
		}
		switch(_action.id)
		{
			case 'shareFilemanager':
				// Sharing a link to just files in filemanager
				var id = path.split('::');
				path = '/apps/'+ id[0] + '/' + id[1];
		}
		if(typeof _writable === 'undefined' && _action.parent && _action.parent.getActionById('shareWritable'))
		{
			_writable = _action.parent.getActionById('shareWritable').checked || false;
		}
		if(typeof _files === 'undefined' && _action.parent && _action.parent.getActionById('shareFiles'))
		{
			_files = _action.parent.getActionById('shareFiles').checked || false;
		}

		return egw.json('EGroupware\\Api\\Sharing::ajax_create', [_action.id, path, _writable, _files],
			_callback ? _callback : this._share_link_callback, this, true, this).sendRequest();
	},

	share_merge: function(_action, _senders, _target)
	{
		var parent = _action.parent.parent;
		var _writable = false;
		var _files = false;
		if(parent && parent.getActionById('shareWritable'))
		{
			_writable = parent.getActionById('shareWritable').checked || false;
		}
		if(parent && parent.getActionById('shareFiles'))
		{
			_files = parent.getActionById('shareFiles').checked || false;
		}

		// Share only works on one at a time
		var promises = [];
		for(var i = 0; i < _senders.length; i++)
		{
			promises.push(new Promise(function(resolve, reject) {
				this.share_link(_action, [_senders[i]], _target, _writable, _files, resolve);
			}.bind(this)));
		}

		// But merge into email can handle several
		Promise.all(promises.map(function(p){p.catch(function(e){console.log(e)})}))
			.then(function(values) {
				// Process document after all shares created
				return nm_action(_action, _senders, _target);
			});
	},

	/**
	 * Share-link callback
	 * @param {object} _data
	 */
	_share_link_callback: function(_data) {
		if (_data.msg || _data.share_link) window.egw_refresh(_data.msg, this.appname);

		var copy_link_to_clipboard = null;

		var copy_link_to_clipboard = function(evt){
			var $target = jQuery(evt.target);
			$target.select();
			try {
				var successful = document.execCommand('copy');
				if (successful)
				{
					egw.message('Share link copied into clipboard');
					return true;
				}
			}
			catch (e) {}
			egw.message('Failed to copy the link!');
		};
		jQuery("body").on("click", "[name=share_link]", copy_link_to_clipboard);
		et2_createWidget("dialog", {
			callback: function( button_id, value) {
				jQuery("body").off("click", "[name=share_link]", copy_link_to_clipboard);
				return true;
			},
			title: _data.title ? _data.title : egw.lang("%1 Share Link", _data.writable ? egw.lang("Writable"): egw.lang("Readonly")),
			template: _data.template,
			width: 450,
			value: {content:{ "share_link": _data.share_link }}
		});
	},

	/**
	 * Opens _menuaction in an Et2Dialog
	 *
	 * Equivalent to egw.openDialog, though this one works in popups too.
	 *
	 * @param _menuaction
	 * @return Promise<Et2Dialog>
	 */
	openDialog: function (_menuaction)
	{
		let resolver;
		let rejector;
		const dialog_promise = new Promise((resolve, reject) =>
		{
			resolver = value => resolve(value);
			rejector = reason => reject(reason);
		});
		let request = this.egw.json(_menuaction.match(/^([^.:]+)/)[0] + '.jdots_framework.ajax_exec.template.' + _menuaction,
			['index.php?menuaction=' + _menuaction, true], _response =>
			{
				if(Array.isArray(_response) && typeof _response[0] === 'string')
				{
					let dialog = jQuery(_response[0]).appendTo(document.body);
					if(dialog.length > 0 && dialog.get(0))
					{
						resolver(dialog.get(0));
					}
					else
					{
						console.log("Unable to add dialog with dialogExec('" + _menuaction + "')", _response);
						rejector(new Error("Unable to add dialog"));
					}
				}
				else
				{
					console.log("Invalid response to dialogExec('" + _menuaction + "')", _response);
					rejector(new Error("Invalid response to dialogExec('" + _menuaction + "')"));
				}
			}).sendRequest();
		return dialog_promise;
	}
});}).call(window);