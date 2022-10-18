/**
 * EGroupware clientside API object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel (as AT stylite.de)
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */

/*egw:uses
	egw_core;
*/
import './egw_core.js';

egw.extend('preferences', egw.MODULE_GLOBAL, function()
{
	"use strict";

	/**
	 * Object holding the prefences as 2-dim. associative array, use
	 * egw.preference(name[,app]) to access it.
	 *
	 * @access: private, use egw.preferences() or egw.set_perferences()
	 */
	var prefs = {};
	var grants = {};

	/**
	 * App-names in egw_preference table are limited to 16 chars, so we can not store anything longer
	 *
	 * Also modify tab-names used in CRM-view ("addressbook-*") to "infolog".
	 *
	 * @param _app
	 * @returns {string}
	 */
	function sanitizeApp(_app)
	{
		if (typeof _app === 'undefined') _app = 'common';

		if (_app.length > 16)
		{
			_app = _app.substring(0, 16);
		}
		if (_app.match(/^addressbook-/))
		{
			_app = 'infolog';
		}
		return _app;
	}

	// Return the actual extension
	return {
		/**
		 * Setting prefs for an app or 'common'
		 *
		 * @param {object} _data object with name: value pairs to set
		 * @param {string} _app application name, 'common' or undefined to prefes of all apps at once
		 * @param {boolean} _need_clone _data need to be cloned, as it is from different window context
		 *	and therefore will be inaccessible in IE, after that window is closed
		 */
		set_preferences: function(_data, _app, _need_clone)
		{
			if (typeof _app == 'undefined')
			{
				prefs = _need_clone ? jQuery.extend(true, {}, _data) : _data;
			}
			else
			{
				prefs[sanitizeApp(_app)] = jQuery.extend(true, {}, _data);	// we always clone here, as call can come from this.preferences!
			}
		},

		/**
		 * Query an EGroupware user preference
		 *
		 * If a preference is not already loaded (only done for "common" by default),
		 * it is synchronously queried from the server, if no _callback parameter is given!
		 *
		 * @param {string} _name name of the preference, eg. 'dateformat', or '*' to get all the application's preferences
		 * @param {string} _app default 'common'
		 * @param {function|boolean|undefined} _callback optional callback, if preference needs loading first
		 *  - default/undefined: preference is synchronously queried, if not loaded, and returned
		 *  - function: if loaded, preference is returned, if not false and callback is called once it's loaded
		 * 	- true:  a promise for the preference is returned
		 *	- false: if preference is not loaded, undefined is return and no (synchronous) request is send to server
		 * @param {object} _context context for callback
		 * @return Promise|object|string|bool (Promise for) preference value or false, if callback given and preference not yet loaded
		 */
		preference: function(_name, _app, _callback, _context)
		{
			_app = sanitizeApp(_app);

			if (typeof prefs[_app] === 'undefined')
			{
				if (_callback === false) return undefined;
				const request = this.json('EGroupware\\Api\\Framework::ajax_get_preference', [_app], _callback, _context);
				const promise = request.sendRequest(typeof _callback !== 'undefined', 'GET');
				if (typeof prefs[_app] === 'undefined') prefs[_app] = {};
				if (_callback === true) return promise.then(() => this.preference(_name, _app));
				if (typeof _callback === 'function') return false;
			}
			let ret;
			if (_name === "*")
			{
				ret = typeof prefs[_app] === 'object' ? jQuery.extend({}, prefs[_app]) : prefs[_app];
			}
			else
			{
				ret = typeof prefs[_app][_name] === 'object' && prefs[_app][_name] !== null ?
					jQuery.extend({}, prefs[_app][_name]) : prefs[_app][_name];
			}
			if (_callback === true)
			{
				return Promise.resolve(ret);
			}
			return ret;
		},

		/**
		 * Set a preference and sends it to the server
		 *
		 * Server will silently ignore setting preferences, if user has no right to do so!
		 *
		 * Preferences are only send to server, if they are changed!
		 *
		 * @param {string} _app application name or "common"
		 * @param {string} _name name of the pref
		 * @param {string} _val value of the pref, null, undefined or "" to unset it
		 * @param {function} _callback Function passed along to the queue, called after preference is set server-side,
		 *	IF the preference is changed / has a value different from the current one
		 */
		set_preference: function(_app, _name, _val, _callback)
		{
			_app = sanitizeApp(_app);

			// if there is no change, no need to submit it to server
			if (typeof prefs[_app] != 'undefined')
			{
				var current = prefs[_app][_name];
				var setting = _val;
				// to compare objects we serialize them
				if (typeof current == 'object') current = JSON.stringify(current);
				if (typeof setting == 'object') setting = JSON.stringify(setting);
				if (setting === current) return;
			}

			this.jsonq('EGroupware\\Api\\Framework::ajax_set_preference',[_app, _name, _val], _callback);

			// update own preference cache, if _app prefs are loaded (don't update otherwise, as it would block loading of other _app prefs!)
			if (typeof prefs[_app] != 'undefined')
			{
				if (_val === undefined || _val === "" || _val === null)
				{
					delete prefs[_app][_name];
				}
				else
				{
					prefs[_app][_name] = _val;
				}
			}
		},

		/**
		 * Endpoint for push to request reload of preference, if loaded and affected
		 *
		 * @param _app app-name of prefs to reload
		 * @param _account_id _account_id 0: allways reload (default or forced prefs), <0: reload if member of group
		 */
		reload_preferences: function(_app, _account_id)
		{
			if (typeof _account_id !== 'number') _account_id = parseInt(_account_id);
			if (typeof prefs[_app] === 'undefined' ||	// prefs not loaded
				_account_id < 0 && this.user('memberships').indexOf(_account_id) < 0)	// no member of this group
			{
				return;
			}
			var request = this.json('EGroupware\\Api\\Framework::ajax_get_preference', [_app]);
			request.sendRequest();
		},

		/**
		 * Call context / open app specific preferences function
		 *
		 * @param {string} name type 'acl', 'prefs', or 'cats'
		 * @param {(array|object)} apps array with apps allowing to call that type, or object/hash with app and boolean or hash with url-params
		 */
		show_preferences: function (name, apps)
		{
			var current_app = this.app_name();
			var query = {menuaction:'',current_app: current_app};
			// give warning, if app does not support given type, but all apps link to common prefs, if they dont support prefs themselfs
			if (jQuery.isArray(apps) && jQuery.inArray(current_app, apps) == -1 && (name != 'prefs' && name != 'acl') ||
				!jQuery.isArray(apps) && (typeof apps[current_app] == 'undefined' || !apps[current_app]))
			{
				egw_message(egw.lang('Not supported by current application!'), 'warning');
			}
			else
			{
				var url = '/index.php';
				switch(name)
				{
					case 'prefs':
						query.menuaction ='preferences.preferences_settings.index';
						if (jQuery.inArray(current_app, apps) != -1) query.appname=current_app;
						egw.open_link(egw.link(url, query), '_blank', '1200x600');
						break;

					case 'acl':
						query.menuaction='preferences.preferences_acl.index';
						if (jQuery.inArray(current_app, apps) != -1) query.acl_app=current_app;
						egw.open_link(egw.link(url, query), '_blank', '1200x600');
						break;

					case 'cats':
						if (typeof apps[current_app] == 'object')
						{
							for(var key in apps[current_app])
							{
								query[key] = encodeURIComponent(apps[current_app][key]);
							}
						}
						else
						{
							query.menuaction='preferences.preferences_categories_ui.index';
							query.cats_app=current_app;
						}
						query.ajax = true;
						egw.link_handler(egw.link(url, query), current_app);
						break;
				}
			}
		},

		/**
		 * Setting prefs for an app or 'common'
		 *
		 * @param {object} _data
		 * @param {string} _app application name or undefined to set grants of all apps at once
		 *	and therefore will be inaccessible in IE, after that window is closed
		 */
		set_grants: function(_data, _app)
		{
			if (_app)
			{
				grants[_app] = jQuery.extend(true, {}, _data);
			}
			else
			{
				grants = jQuery.extend(true, {}, _data);
			}
		},

		/**
		 * Query an EGroupware user preference
		 *
		 * We currently load grants from all apps in egw.js, so no need for a callback or promise.
		 *
		 * @param {string} _app app-name
		 * @param {function|false|undefined} _callback optional callback, if preference needs loading first
		 * if false given and preference is not loaded, undefined is return and no (synchronious) request is send to server
		 * @param {object} _context context for callback
		 * @return {object|undefined|false} grant object, false if not (yet) loaded and no callback or undefined
		 */
		grants: function( _app) //, _callback, _context)
		{
			/* we currently load grants from all apps in egw.js, so no need for a callback or promise
			if (typeof grants[_app] == 'undefined')
			{
				if (_callback === false) return undefined;
				var request = this.json('EGroupware\\Api\\Framework::ajax_get_preference', [_app], _callback, _context);
				request.sendRequest(typeof _callback == 'function', 'GET');	// use synchronous (cachable) GET request
				if (typeof grants[_app] == 'undefined') grants[_app] = {};
				if (typeof _callback == 'function') return false;
			}*/
			return typeof grants[_app] === 'object' ? jQuery.extend({}, grants[_app]) : grants[_app];
		},

		/**
		 * Get mime types supported by file editor AND not excluded by user
		 *
		 * @param {string} _mime current mime type
		 * @returns {object|null} returns object of filemanager editor hook
		 */
		file_editor_prefered_mimes: function(_mime)
		{
			const fe = jQuery.extend(true, {}, this.link_get_registry('filemanager-editor'));
			let ex_mimes = this.preference('collab_excluded_mimes', 'filemanager');
			const dblclick_action = this.preference('document_doubleclick_action', 'filemanager');
			if (dblclick_action === 'download' && typeof _mime === 'string')
			{
				ex_mimes = !ex_mimes ? _mime : ex_mimes+','+_mime;
			}
			if (fe && fe.mime && ex_mimes && typeof ex_mimes === 'string')
			{
				ex_mimes = ex_mimes.split(',');
				for (let mime in fe.mime)
				{
					for (let i in ex_mimes)
					{
						if (ex_mimes[i] === mime) delete(fe.mime[mime]);
					}
				}
			}
			return fe && fe.mime ? fe : null;
		}
	};
});