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
				prefs[_app] = jQuery.extend(true, {}, _data);	// we always clone here, as call can come from this.preferences!
			}
		},

		/**
		 * Query an EGroupware user preference
		 *
		 * If a prefernce is not already loaded (only done for "common" by default), it is synchroniosly queryed from the server!
		 *
		 * @param {string} _name name of the preference, eg. 'dateformat', or '*' to get all the application's preferences
		 * @param {string} _app default 'common'
		 * @param {function|false|undefined} _callback optional callback, if preference needs loading first
		 * if false given and preference is not loaded, undefined is return and no (synchronious) request is send to server
		 * @param {object} _context context for callback
		 * @return string|bool preference value or false, if callback given and preference not yet loaded
		 */
		preference: function(_name, _app, _callback, _context)
		{
			if (typeof _app == 'undefined') _app = 'common';

			if (typeof prefs[_app] == 'undefined')
			{
				if (_callback === false) return undefined;
				var request = this.json('EGroupware\\Api\\Framework::ajax_get_preference', [_app], _callback, _context);
				request.sendRequest(typeof _callback == 'function', 'GET');	// use synchronous (cachable) GET request
				if (typeof prefs[_app] == 'undefined') prefs[_app] = {};
				if (typeof _callback == 'function') return false;
			}
			if (_name == "*") return typeof prefs[_app] ==='object' ? jQuery.extend({},prefs[_app]) : prefs[_app];

			return typeof prefs[_app][_name] === 'object' && prefs[_app][_name] !== null ?
				jQuery.extend({},prefs[_app][_name]) : prefs[_app][_name];
		},

		/**
		 * Set a preference and sends it to the server
		 *
		 * Server will silently ignore setting preferences, if user has no right to do so!
		 *
		 * @param {string} _app application name or "common"
		 * @param {string} _name name of the pref
		 * @param {string} _val value of the pref, null, undefined or "" to unset it
		 * @param {function} _callback Function passed along to the queue, called after preference is set server-side
		 */
		set_preference: function(_app, _name, _val, _callback)
		{
			// if there is no change, no need to submit it to server
			if (typeof prefs[_app] != 'undefined' && prefs[_app][_name] === _val) return;

			this.jsonq('EGroupware\\Api\\Framework::ajax_set_preference',[_app, _name, _val], _callback);

			// update own preference cache, if _app prefs are loaded (dont update otherwise, as it would block loading of other _app prefs!)
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
						egw.open_link(egw.link(url, query), '_blank', '900x450');
						break;

					case 'acl':
						query.menuaction='preferences.preferences_acl.index';
						if (jQuery.inArray(current_app, apps) != -1) query.acl_app=current_app;
						egw.open_link(egw.link(url, query), '_blank', '900x450');
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
		}
	};
});

