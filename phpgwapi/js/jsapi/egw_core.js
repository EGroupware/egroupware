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

/**
 * This code setups the egw namespace and adds the "extend" function, which is
 * used by extension modules to inject their content into the egw object.
 */
(function(_parent) {

	var instanceUid = 0;

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

	function deleteWhere(_arr, _cond)
	{
		for (var i = _arr.length - 1; i >= 0; i--)
		{
			if (_cond(_arr[i]))
			{
				_arr.splice(i, 1)
			}
		}
	}

	/**
	 * The getAppModules function returns all application specific api modules
	 * for the given application. If those application specific api instances
	 * were not created yet, the functions creates them.
	 *
	 * @param _egw is a reference to the global _egw instance and is passed as
	 * 	a context to the module instance.
	 * @param _modules is the hash map which contains all module descriptors.
	 * @param _moduleInstances is the the object which contains the application
	 * 	and window specific module instances.
	 * @param _app is the application for which the module instances should get
	 * 	created.
	 */
	function getAppModules(_egw, _modules, _moduleInstances, _app)
	{
		// Check whether the application specific modules for that instance
		// already exists, if not, create it
		if (typeof _moduleInstances.app[_app] === 'undefined')
		{
			var modInsts = {};

			// Otherwise create the application specific instances
			for (var key in _modules)
			{
				var mod = _modules[key];
				if (mod.flags === _egw.MODULE_APP_LOCAL)
				{
					modInsts[key] = mod.code.call(_egw, _app, window);
				}
			}

			_moduleInstances.app[_app] = modInsts;
		}

		return _moduleInstances.app[_app];
	}

	/**
	 * The getWndModules function returns all window specific api modules for
	 * the given window. If those window specific api instances were not created
	 * yet, the functions creates them.
	 *
	 * @param _egw is a reference to the global _egw instance and is passed as
	 * 	a context to the module instance.
	 * @param _modules is the hash map which contains all module descriptors.
	 * @param _moduleInstances is the the object which contains the application
	 * 	and window specific module instances.
	 * @param _instances refers to all api instances.
	 * @param _wnd is the window for which the module instances should get
	 * 	created.
	 */
	function getWndModules(_egw, _modules, _moduleInstances, _instances, _window)
	{
		// Search for the specific window instance
		for (var i = 0; i < _moduleInstances.wnd.length; i++)
		{
			var descr = _moduleInstances.wnd[i];

			if (descr.window === _window)
			{
				return descr.modules;
			}
		}

		// If none was found, create the slot
		var mods = {};
		_moduleInstances.wnd.push({
			'window': _window,
			'modules': mods
		});

		// Add an eventlistener for the "onunload" event -- if "onunload" gets
		// called, we have to delete the module slot created above
		_window.addEventListener('beforeunload', function() {
			cleanupEgwInstances(_instances, _moduleInstances, function(_w) {
				return _w.window === _window});
		}, false);

		// Otherwise create the window specific instances
		for (var key in _modules)
		{
			var mod = _modules[key];
			if (mod.flags === _egw.MODULE_WND_LOCAL)
			{
				mods[key] = mod.code.call(_egw, null, _window);
			}
		}

		return mods;
	}

	/**
	 * Creates an api instance for the given application and the given window.
	 *
	 * @param _egw is the global _egw instance which should be used.
	 * @param _modules is the hash map which contains references to all module
	 * 	descriptors.
	 * @param _moduleInstances is the the object which contains the application
	 * 	and window specific module instances.
	 * @param _list is the overall instances list, to which the module should be
	 * 	added.
	 * @param _instances refers to all api instances.
	 * @param _app is the application for which the instance should be created.
	 * @param _wnd is the window for which the instance should be created.
	 */
	function createEgwInstance(_egw, _modules, _moduleInstances, _list,
			_instances, _app, _window)
	{
		// Clone the global object
		var instance = cloneObject(_egw);

		// Let "_window" and "_app" be exactly null, if it evaluates to false
		_window = _window ? _window : null;
		_app = _app ? _app : null;

		// Set the application name and the window the API instance belongs to
		instance.appName = _app;
		instance.window = _window;

		// Push the newly created instance onto the instance list
		_list.push({
			'instance': instance,
			'window': _window,
			'app': _app
		});

		// Merge either the application specific and/or the window specific
		// module instances into the new instance
		if (_app)
		{
			var appModules = getAppModules(_egw, _modules, _moduleInstances,
				_app);

			for (var key in appModules)
			{
				mergeObjects(instance, appModules[key]);
			}
		}

		if (_window)
		{
			var wndModules = getWndModules(_egw, _modules, _moduleInstances,
				_instances, _window);

			for (var key in wndModules)
			{
				mergeObjects(instance, wndModules[key]);
			}
		}

		// Return the new api instance
		return instance;
	}

	/**
	 * Returns a egw instance for the given application and the given window. If
	 * the instance does not exist now, the instance will be created.
	 *
	 * @param _egw is the global _egw instance which should be used.
	 * @param _modules is the hash map which contains references to all module
	 * 	descriptors.
	 * @param _moduleInstances is the the object which contains the application
	 * 	and window specific module instances.
	 * @param _list is the overall instances list, to which the module should be
	 * 	added.
	 * @param _app is the application for which the instance should be created.
	 * @param _wnd is the window for which the instance should be created.
	 */
	function getEgwInstance(_egw, _modules, _moduleInstances, _instances, _app,
		_window)
	{
		// Generate the hash key for the instance descriptor object
		var hash = _app ? _app : '~global~';

		// Let "_window" be exactly null, if it evaluates to false
		_window = _window ? _window : null;

		// Create a new entry if the calculated hash does not exist
		if (typeof _instances[hash] === 'undefined')
		{
			_instances[hash] = [];
			return createEgwInstance(_egw, _modules, _moduleInstances, 
				_instances[hash], _instances, _app, _window);
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
		return createEgwInstance(_egw, _modules, _moduleInstances, 
			_instances[hash], _instances, _app, _window);
	}

	function cleanupEgwInstances(_instances, _moduleInstances, _cond)
	{
		// Iterate over the instances
		for (var key in _instances)
		{
			// Delete all entries corresponding to closed windows
			deleteWhere(_instances[key], _cond);

			// Delete the complete instance key if the array is empty
			if (_instances[key].length === 0)
			{
				delete _instances[key];
			}
		}

		// Delete all entries corresponding to non existing elements in the
		// module instances
		deleteWhere(_moduleInstances.wnd, _cond);
	}

	function mergeGlobalModule(_module, _code, _instances, _moduleInstances)
	{
		// Generate the global extension
		var globalExtension = _code.call(egw, null, window);

		for (var key in _instances)
		{
			for (var i = 0; i < _instances[key].length; i++)
			{
				mergeObjects(_instances[key][i].instance,
						globalExtension);
			}
		}
	}

	function mergeAppLocalModule(_module, _code, _instances, _moduleInstances)
	{
		// Generate the global extension
		var globalExtension = _code.call(egw, null, window);

		// Merge the extension into the global instances
		for (var i = 0; i < _instances['~global~'].length; i++)
		{
			mergeObjects(_instances['~global~'][i].instance, globalExtension);
		}

		for (var key in _moduleInstances.app)
		{
			// Create the application specific instance and
			// store it in the module instances
			var appExtension = _code.call(egw, key, window);
			_moduleInstances.app[key][_module] = appExtension;

			// Merge the extension into all instances for
			// the current application
			for (var i = 0; i < _instances[key].length; i++)
			{
				mergeObjects(_instances[key][i].instance, appExtension);
			}
		}
	}

	function mergeWndLocalModule(_module, _code, _instances, _moduleInstances)
	{
		// Iterate over all existing windows
		for (var i = 0; i < _moduleInstances.wnd.length; i++)
		{
			var wnd = _moduleInstances.wnd[i].window;

			// Create the window specific instance and
			// register it.
			var wndExtension = _code.call(egw, null, wnd);
			_moduleInstances.wnd[i].modules[_module] = wndExtension;

			// Extend all existing instances which are using
			// this window.
			for (var key in _instances)
			{
				for (var j = 0; j < _instances[key].length; j++)
				{
					if (_instances[key][j].window === wnd)
					{
						mergeObjects(_instances[key][j].instance,
								wndExtension);
					}
				}
			}
		}
	}

	if (window.opener && typeof window.opener.egw !== 'undefined')
	{
		this['egw'] = window.opener.egw;
	}
	else if (window.top && typeof window.top.egw !== 'undefined')
	{
		this['egw'] = window.top.egw;
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

		var moduleInstances = {
			'app': {},
			'wnd': []
		}

		/**
		 * instances contains references to all created instances.
		 */
		var instances = {};

		/**
		 * Set a interval which is used to cleanup unused API instances all 10
		 * seconds.
		 */
		window.setInterval(function() {
			cleanupEgwInstances(instances, moduleInstances, function(w) {
				return w.window && w.window.closed
			});
		}, 10000);

		/**
		 * The egw function returns an instance of the client side api. If no
		 * parameter is given, an egw istance, which is not bound to a certain
		 * application is returned.
		 * You may pass either an application name (as string) to the egw
		 * function and/or a window object. If you specify both, the app name
		 * has to preceed the window object reference. If no window object is
		 * given, the root window will be used.
		 */
		var egw = function() {

			// Get the window/app reference
			var _app = null;
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
			return getEgwInstance(egw, modules, moduleInstances, instances,
					_app, _window);
		}

		var globalEgw = {

			/**
			 * The MODULE_GLOBAL flag describes a module as global. A global
			 * module always works on the same data.
			 */
			MODULE_GLOBAL: 0,

			/**
			 * The MODULE_APP_LOCAL flag is used to describe a module as local
			 * for each application. Each time an api object is requested for
			 * another application, the complete module gets recreated.
			 */
			MODULE_APP_LOCAL: 1,

			/**
			 * The MODULE_WND_LOCAL flag is used to describe a module as local
			 * for each window. Each time an api object is requested for another
			 * window, the complete module gets recreated.
			 */
			MODULE_WND_LOCAL: 2,

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
			 * 	as a local or a global module. May be one of egw.MODULE_GLOBAL,
			 * 	MODULE_APP_LOCAL or MODULE_WND_LOCAL.
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

					// Create new app/module specific instances for the new
					// module and merge the new module into all created
					// instances
					switch (_flags)
					{
						// Easiest case -- simply merge the extension into all
						// instances
						case egw.MODULE_GLOBAL:
							mergeGlobalModule(_module, _code, instances,
									moduleInstances);
							break;

						// Create new application specific instances and merge
						// those into all api instances for that application
						case egw.MODULE_APP_LOCAL:
							mergeAppLocalModule(_module, _code, instances,
									moduleInstances);
							break;


						// Create new window specific instances for each window
						// and merge those into all api instances for that
						// window
						case egw.MODULE_WND_LOCAL:
							mergeWndLocalModule(_module, _code, instances,
									moduleInstances);
							break;
					}
				}
			},

			dumpModules: function() {
				return modules;
			},

			dumpInstances: function() {
				return {
					'instances': instances,
					'moduleInstances': moduleInstances
				}
			}

		};

		// Merge the globalEgw functions into the egw object.
		mergeObjects(egw, globalEgw);

		// Create the entry for the root window in the module instances
		moduleInstances.wnd.push({
			'window': window,
			'modules': []
		});

		// Create the entry for the global window in the instances and register
		// the global instance there
		instances['~global~'] = [{
			'window': window,
			'instance': egw,
			'app': null
		}];

		// Publish the egw object
		this['egw'] = egw;
	}
}).call(window);

