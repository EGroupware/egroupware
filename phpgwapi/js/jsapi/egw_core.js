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

var egw;

/**
 * IE Fix for array.indexOf
 */
if (typeof Array.prototype.indexOf == "undefined")
{
	Array.prototype.indexOf = function(_elem) {
		for (var i = 0; i < this.length; i++)
		{
			if (this[i] === _elem)
				return i;
		}
		return -1;
	};
}

/**
 * This code setups the egw namespace and adds the "extend" function, which is
 * used by extension modules to inject their content into the egw object.
 */
(function(_parent) {

	// Some local functions for cloning and merging javascript objects
	function cloneObject(_obj) {
		var result = {};

		for (var key in _obj)
		{
			result[key] = _obj[key];
		}

		return result;
	}

	function mergeObjects(_to, _from) {
		// Extend the egw object
		for (var key in _from)
		{
			if (typeof _to[key] === 'undefined')
			{
				_to[key] = _from[key];
			}
		}
	}

	if (window.opener && typeof window.opener.egw !== 'undefined')
	{
		egw = window.opener.egw;
	}
	else if (window.top && typeof window.top.egw !== 'undefined')
	{
		egw = window.top.egw;
	}
	else
	{
		/**
		 * EGW_DEBUGLEVEL specifies which messages are printed to the console.
		 * Decrease the value of EGW_DEBUGLEVEL to get less messages.
		 */
		var EGW_DEBUGLEVEL = 4;

		/**
		 * Modules contains all currently loaded egw extension modules.
		 */
		var modules = [];

		var localEgw = {};

		/**
		 * The egw function returns an instance of the client side api. If no
		 * parameter is given, an egw istance, which is not bound to a certain
		 * application is returned.
		 */
		egw = function(_app) {

			// If no argument is given, simply return the global egw object, or
			// check whether 'window.egw_appName' is set correctly.
			if (typeof _app === 'undefined')
			{
				// TODO: Remove this code, window.egw_appName will be removed
				// in the future.
				if (typeof window.egw_appName == 'string')
				{
					_app = window.egw_appName;
				}
				else
				{
					return egw;
				}
			}

			if (typeof _app == 'string')
			{
				// If a argument is given, this represents the current application
				// name. Check whether we already have a copy of the egw object for
				// that application. If yes, return it.
				if (typeof localEgw[_app] === 'undefined')
				{
					// Otherwise clone the global egw object, set the application
					// name and return it
					localEgw[_app] = cloneObject(egw);
					localEgw[_app].appName = _app;
				}

				return localEgw[_app];
			}

			this.debug("error", "Non-string argument given to the egw function.");
		}

		var globalEgw = {

			/**
			 * Name of the application the egw object belongs to.
			 */
			appName: null,

			/**
			 * Returns the current application name. The current application
			 * name equals the name, which was given when calling the egw
			 * function. If the getAppName function is called on the global
			 * instance, 'etemplate' is returned.
			 */
			getAppName: function() {
				// Return the default application name if this function is
				// called on the global egw instance.
				if (!this.appName) {
					return 'etemplate';
				}

				// Otherwise return the correct application name.
				return this.appName;
			},

			/**
			 * base-URL of the EGroupware installation
			 * 
			 * get set via egw_framework::header()
			 */
			webserverUrl: "/egroupware",

			/**
			 * The extend function can be used to extend the egw object.
			 *
			 * @param _module should be a string containing the name of the new
			 * 	module.
			 * @param _code should be a function, which returns an object that
			 * 	should extend the egw object.
			 */
			extend: function(_module, _code) {

				// Check whether the given module has already been loaded.
				if (modules.indexOf(_module) < 0) {

					// Call the function specified by "_code" which returns
					// nothing but an object containing the extension.
					var content = _code.call(this);

					// Merge the extension into the egw function
					mergeObjects(egw, content);

					// Merge the extension into the local egw object
					for (var key in localEgw) {
						mergeObjects(localEgw[key], content);
					}

					// Register the module as loaded
					modules.push(_module);
				}
			},

			/**
			 * The debug function can be used to send a debug message to the
			 * java script console. The first parameter specifies the debug
			 * level, all other parameters are passed to the corresponding
			 * console function.
			 */
			debug: function(_level) {
				if (typeof console != "undefined")
				{
					// Get the passed parameters and remove the first entry
					var args = [];
					for (var i = 1; i < arguments.length; i++)
					{
						args.push(arguments[i]);
					}

					if (_level == "log" && EGW_DEBUGLEVEL >= 4 &&
						typeof console.log == "function")
					{
						console.log.apply(console, args);
					}

					if (_level == "info" && EGW_DEBUGLEVEL >= 3 &&
						typeof console.info == "function")
					{
						console.info.apply(console, args);
					}

					if (_level == "warn" && EGW_DEBUGLEVEL >= 2 &&
						typeof console.warn == "function")
					{
						console.warn.apply(console, args);
					}

					if (_level == "error" && EGW_DEBUGLEVEL >= 1 &&
						typeof console.error == "function")
					{
						console.error.apply(console, args);
					}
				}
			}
		};

		mergeObjects(egw, globalEgw);
	}
})();

