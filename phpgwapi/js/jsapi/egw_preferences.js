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
	var prefs = {
		common: { 
			dateformat: "Y-m-d", 
			timeformat: 24,
			lang: "en"
		}
	};

	// Return the actual extension
	return {
		/**
		 * Setting prefs for an app or 'common'
		 * 
		 * @param object _data object with name: value pairs to set
		 * @param string _app application name, 'common' or undefined to prefes of all apps at once
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
		 * @param string _name name of the preference, eg. 'dateformat', or '*' to get all the application's preferences
		 * @param string _app='common'
		 * @return string preference value
		 * @todo add a callback to query it asynchron
		 */
		preference: function(_name, _app) 
		{
			if (typeof _app == 'undefined') _app = 'common';

			if (typeof prefs[_app] == 'undefined')
			{
				xajax_doXMLHTTPsync('home.egw_framework.ajax_get_preference.template', _app);

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
		 * @param string _app application name or "common"
		 * @param string _name name of the pref
		 * @param string _val value of the pref
		 */
		set_preference: function(_app, _name, _val)
		{
			this.jsonq('home.egw_framework.ajax_set_preference.template',[_app, _name, _val]);

			// update own preference cache, if _app prefs are loaded (dont update otherwise, as it would block loading of other _app prefs!)
			if (typeof prefs[_app] != 'undefined') prefs[_app][_name] = _val;
		}
	};

});

