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

"use strict";

/*egw:uses
	egw_core;
*/

egw.extend('preferences', egw.MODULE_GLOBAL, function() {

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
		 */
		set_preferences: function(_data, _app)
		{
			if (typeof _app == 'undefined')
			{
				prefs = _data;
			}
			else
			{
				prefs[_app] = _data;
			}
		},

		/**
		 * Query an EGroupware user preference
		 *
		 * If a prefernce is not already loaded (only done for "common" by default), it is synchroniosly queryed from the server!
		 *
		 * @param {string} _name name of the preference, eg. 'dateformat', or '*' to get all the application's preferences
		 * @param {string} _app default 'common'
		 * @return string preference value
		 * @todo add a callback to query it asynchron
		 */
		preference: function(_name, _app)
		{
			if (typeof _app == 'undefined') _app = 'common';

			if (typeof prefs[_app] == 'undefined')
			{
				var request = this.json('home.egw_framework.ajax_get_preference.template', [_app]);
				request.sendRequest(false, 'GET');	// use synchronous (cachable) GET request
				if (typeof prefs[_app] == 'undefined') prefs[_app] = {};
			}
			if(_name == "*") return prefs[_app];

			return prefs[_app][_name];
		},

		/**
		 * Set a preference and sends it to the server
		 *
		 * Server will silently ignore setting preferences, if user has no right to do so!
		 *
		 * @param {string} _app application name or "common"
		 * @param {string} _name name of the pref
		 * @param {string} _val value of the pref
		 */
		set_preference: function(_app, _name, _val)
		{
			this.jsonq('home.egw_framework.ajax_set_preference.template',[_app, _name, _val]);

			// update own preference cache, if _app prefs are loaded (dont update otherwise, as it would block loading of other _app prefs!)
			if (typeof prefs[_app] != 'undefined') prefs[_app][_name] = _val;
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
			var query = {};
			// give warning, if app does not support given type, but all apps link to common prefs, if they dont support prefs themselfs
			if ($j.isArray(apps) && $j.inArray(current_app, apps) == -1 && name != 'prefs' ||
				!$j.isArray(apps) && (typeof apps[current_app] == 'undefined' || !apps[current_app]))
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
						if ($j.inArray(current_app, apps) != -1) query.appname=current_app;
						break;

					case 'acl':
						query.menuaction='preferences.preferences_acl.index';
						query.acl_app=current_app;
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
						break;
				}
				query.current_app = current_app;
				egw.link_handler(egw.link(url, query), current_app);
			}
		}
	};
});

