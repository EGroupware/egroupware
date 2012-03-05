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
			_to[key] = _from[key];
		}
	}

	function createEgwInstance(_egw, _modules, _list, _app, _window)
	{
		// Clone the global object
		var instance = cloneObject(_egw);

		// Let "_window" be exactly null, if it evaluates to false
		_window = _window ? _window : null;

		// Set the application name and the window the API instance belongs to
		instance.appName = _app ? _app : null;
		instance.window = _window;

		// Insert the newly created instance into the instances list
		_list.push({
			'window': _window,
			'app': _app,
			'instance': instance
		});

		// Re-instanciate all modules which are marked as "local"
		for (var key in _modules)
		{
			// Get the module object
			var mod = _modules[key];

			if (mod.flags !== _egw.MODULE_GLOBAL)
			{
				// If the module is marked as application local and an
				// application instance is given or if the module is marked as
				// window local and a window instance is given, re-instanciate
				// this module.
				if (((mod.flags & _egw.MODULE_APP_LOCAL) && (_app)) ||
				    ((mod.flags & _egw.MODULE_WND_LOCAL) && (_window)))
				{
					var extension = mod.code.call(instance, instance,
						_window ? _window : window);
					mergeObjects(instance, extension);
				}
			}
		}

		return instance;
	}

	function getEgwInstance(_egw, _modules, _instances, _app, _window)
	{
		// Generate the hash key for the instance descriptor object
		var hash = _window ? _app + "_" + _window.location : _app;

		// Let "_window" be exactly null, if it evaluates to false
		_window = _window ? _window : null;

		// Create a new entry if the calculated hash does not exist
		if (typeof _instances[hash] === 'undefined')
		{
			_instances[hash] = [];
			return createEgwInstance(_egw, _modules, _instances[hash], _app,
				_window);
		}
		else
		{
			// Otherwise search for the api instance corresponding to the given
			// window
			for (var i = 0; i < _instances[hash].length; i++)
			{
				if (_instances[hash][i].window === _window)
				{
					return _instances[hash][i].instance;
				}
			}
		}

		// If we're still here, no API instance for the given window has been
		// found -- create a new entry
		return createEgwInstance(_egw, _modules, _instances[hash], _app, _window);
	}

	function cleanupEgwInstances(_instances)
	{
		// Iterate over the egw instances and check whether the window they
		// correspond to is still open.
		for (var key in _instances)
		{
			for (var i = _instances[key].length - 1; i >= 0; i--)
			{
				// Get the instance descriptor
				var instDescr = _instances[key][i];

				// Check whether the window this API instance belongs to is
				// still opened. If not, remove the API instance.
				if (instDescr.window && instDescr.window.closed)
				{
					_instances[key].splice(i, 1)
				}
			}

			// If all instances for the current hash have been deleted, delete
			// the hash entry itself
			if (_instances[key].length === 0)
			{
				delete _instances[key];
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
		 * Modules contains all currently loaded egw extension modules. A module
		 * is stored as an object of the following form:
		 * 	{
		 * 		name: <NAME OF THE OBJECT>,
		 * 		code: <REFERENCE TO THE MODULE FUNCTION>,
		 * 		flags: <MODULE FLAGS (local, global, etc.)
		 * 	}
		 */
		var modules = {};

		/**
		 * instances contains all api instances. These are organized as a hash
		 * of the form _app + _window.location. For each of these hashes a list
		 * of instances is stored, where the instance itself is an entry of the
		 * form
		 * 	{
		 * 		instance: <EGW_API_OBJECT>,
		 * 		app: <APPLICATION NAME>,
		 * 		window: <WINDOW REFERENCE>
		 * 	}
		 */
		var instances = {};

		/**
		 * Set a interval which is used to cleanup unused API instances all 10
		 * seconds.
		 */
		window.setInterval(function() {cleanupEgwInstances(instances);}, 10000);

		/**
		 * The egw function returns an instance of the client side api. If no
		 * parameter is given, an egw istance, which is not bound to a certain
		 * application is returned.
		 * You may pass either an application name (as string) to the egw
		 * function and/or a window object. If you specify both, the app name
		 * has to preceed the window object reference. If no window object is
		 * given, the root window will be used.
		 */
		egw = function() {

			// Get the window/app reference
			var _app = "";
			var _window = window;

			switch (arguments.length)
			{
				case 0:
					// Return the global instance
					return egw;

				case 1:
					if (typeof arguments[0] === 'string')
					{
						_app = arguments[0];
					}
					else if (typeof arguments[0] === 'object')
					{
						_window = arguments[0];
					}
					break;

				case 2:
					_app = arguments[0];
					_window = arguments[1];
					break;

				default:
					throw "Invalid count of parameters";
			}

			// Generate an API instance
			return getEgwInstance(egw, modules, instances, _app, _window);
		}

		var globalEgw = {

			/**
			 * The MODULE_GLOBAL flag describes a module as global. A global
			 * module always works on the same data.
			 */
			MODULE_GLOBAL: 0x00,

			/**
			 * The MODULE_APP_LOCAL flag is used to describe a module as local
			 * for each application. Each time an api object is requested for
			 * another application, the complete module gets recreated.
			 */
			MODULE_APP_LOCAL: 0x01,

			/**
			 * The MODULE_WND_LOCAL flag is used to describe a module as local
			 * for each window. Each time an api object is requested for another
			 * window, the complete module gets recreated.
			 */
			MODULE_WND_LOCAL: 0x02,

			/**
			 * Name of the application the egw object belongs to.
			 */
			appName: null,

			/**
			 * Reference to the window this egw object belongs to.
			 */
			window: window,

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
			 * @param _flags specifies whether the extension should be treated
			 * 	as a local or a global module.
			 * @param _code should be a function, which returns an object that
			 * 	should extend the egw object.
			 */
			extend: function(_module, _flags, _code) {

				// Check whether that module is already registered
				if (typeof modules[_module] === 'undefined')
				{
					// Create a new module entry
					modules[_module] = {
						'code': _code,
						'flags': _flags,
						'name': _module
					};

					// Generate the global extension
					var globalExtension = _code.call(egw, egw, window);

					// Merge the global extension into the egw function
					mergeObjects(egw, globalExtension);

					// Iterate over the instances and merge the modules into
					// them
					for (var key in instances)
					{
						for (var i = 0; i < instances[key].length; i++)
						{
							// Get the instance descriptor
							var instDescr = instances[key][i];

							// Merge the module into the instance
							if (_flags !== egw.MODULE_GLOBAL)
							{
								mergeObjects(instDescr.instance, _code.call(
									instDescr.instance, instDescr.instance,
									instDescr.window ? instDescr.window : window));
							}
							else
							{
								mergeObjects(instDescr.instance, globalExtension);
							}
						}
					}
				}
			},

			dumpModules: function() {
				return modules;
			},

			dumpInstances: function() {
				return instances;
			}

		};

		// Merge the globalEgw functions into the egw object.
		mergeObjects(egw, globalEgw);
	}
})();

