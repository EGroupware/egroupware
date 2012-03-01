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

egw().extend('lang', function() {

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
		 */
		set_lang_arr: function(_app, _messages)
		{
			lang_arr[_app] = _messages;
		},
		
		/**
		 * Translate a given phrase replacing optional placeholders
		 * 
		 * @param string _msg message to translate
		 * @param string _arg1 ... _argN
		 */
		lang: function(_msg, _arg1)
		{
			var translation = _msg;
			_msg = _msg.toLowerCase();
			
			// search apps in given order for a replacement
			var apps = [this.getAppName(), 'etemplate', 'common'];
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
		}
	};

});

