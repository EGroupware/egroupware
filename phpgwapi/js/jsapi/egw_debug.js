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

egw.extend('debug', egw.MODULE_GLOBAL, function(_app, _wnd) {

	/**
	 * DEBUGLEVEL specifies which messages are printed to the console.
	 * Decrease the value of EGW_DEBUGLEVEL to get less messages.
	 */
	var DEBUGLEVEL = 2;

	/**
	 * The debug function can be used to send a debug message to the
	 * java script console. The first parameter specifies the debug
	 * level, all other parameters are passed to the corresponding
	 * console function.
	 */
	return {
		debug: function(_level) {
			if (typeof _wnd.console != "undefined")
			{
				// Get the passed parameters and remove the first entry
				var args = [];
				for (var i = 1; i < arguments.length; i++)
				{
					args.push(arguments[i]);
				}

				// Add in a trace
				if (DEBUGLEVEL >= 5 && typeof (new Error).stack != "undefined")
				{
					var stack = (new Error).stack;
					args.push(stack);
				}

				if (_level == "log" && DEBUGLEVEL >= 4 &&
					typeof _wnd.console.log == "function")
				{
					_wnd.console.log.apply(_wnd.console, args);
				}

				if (_level == "info" && DEBUGLEVEL >= 3 &&
					typeof _wnd.console.info == "function")
				{
					_wnd.console.info.apply(_wnd.console, args);
				}

				if (_level == "warn" && DEBUGLEVEL >= 2 &&
					typeof _wnd.console.warn == "function")
				{
					_wnd.console.warn.apply(_wnd.console, args);
				}

				if (_level == "error" && DEBUGLEVEL >= 1 &&
					typeof _wnd.console.error == "function")
				{
					_wnd.console.error.apply(_wnd.console, args);
				}
			}
		}
	}
});

