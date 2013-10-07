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
	egw_files;
	egw_ready;
*/

/**
 * @augments Class
 */
egw.extend('lang', egw.MODULE_GLOBAL, function() {

	/**
	 * Translations
	 * 
	 * @access: private, use egw.lang() or egw.set_lang_arr()
	 */
	var lang_arr = {};

	// Return the actual extension
	return {
		/**
		 * Set translation for a given application
		 * 
		 * @param string _app
		 * @param object _message message => translation pairs
		 * @memberOf egw
		 */
		set_lang_arr: function(_app, _messages)
		{
			if(!jQuery.isArray(_messages))
			{
				lang_arr[_app] = _messages;
			}
		},
		
		/**
		 * Translate a given phrase replacing optional placeholders
		 * 
		 * @param string _msg message to translate
		 * @param string _arg1 ... _argN
		 */
		lang: function(_msg, _arg1)
		{
			if(typeof _msg !== "string")
			{
				egw().debug("warn", "Cannot translate an object", _msg);
				return _msg;
			}
			var translation = _msg;
			_msg = _msg.toLowerCase();
			
			// search apps in given order for a replacement
			var apps = this.lang_order || ['custom', this.getAppName(), 'etemplate', 'common'];
			for(var i = 0; i < apps.length; ++i)
			{
				if (typeof lang_arr[apps[i]] != "undefined" &&
					typeof lang_arr[apps[i]][_msg] != 'undefined')
				{
					translation = lang_arr[apps[i]][_msg];
					break;
				}
			}
			if (arguments.length == 1) return translation;
			
			if (arguments.length == 2) return translation.replace('%1', arguments[1]);
			
			// to cope with arguments containing '%2' (eg. an urlencoded path like a referer),
			// we first replace all placeholders '%N' with '|%N|' and then we replace all '|%N|' with arguments[N]
			translation = translation.replace(/%([0-9]+)/g, '|%$1|');
			for(var i = 1; i < arguments.length; ++i)
			{
				translation = translation.replace('|%'+i+'|', arguments[i]);
			}
			return translation;
		},

		/**
		 * Includes the language files for the given applications -- if those
		 * do not already exist, include them.
		 *
		 * @param _window is the window which needs the language -- this is
		 * 	needed as the "ready" event has to be postponed in that window until
		 * 	all lang files are included.
		 * @param _apps is an array containing the applications for which the
		 * 	data is needed as objects of the following form:
		 * 		{
		 * 			app: <APPLICATION NAME>,
		 * 			lang: <LANGUAGE CODE>
		 * 		}
		 */
		langRequire: function(_window, _apps) {
			// Get the ready and the files module for the given window
			var ready = this.module("ready", _window);
			var files = this.module("files", this.window);

			// Build the file names which should be included
			var jss = [];
			var apps = [];
			for (var i = 0; i < _apps.length; i++)
			{
				if (typeof lang_arr[_apps[i].app] === "undefined")
				{
					jss.push(this.webserverUrl
						+ '/phpgwapi/lang.php?app='
						+ _apps[i].app + '&lang=' + _apps[i].lang);
				}
				apps.push(_apps[i].app);
			}
			if (this !== egw)
			{
				this.lang_order = apps.reverse();
			}

			// Only continue if we need to include a language
			if (jss.length > 0)
			{
				// Require a "ready postpone token"
				var token = ready.readyWaitFor();

				// Call "readyDone" once all js files have been included.
				files.includeJS(jss, function () {
					ready.readyDone(token);
				}, this);
			}
		}
	};

});

