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

/**
 * This code setups the egw namespace and adds the "extend" function, which is
 * used by extension modules to inject their content into the egw object.
 */
(function()
{
	"use strict";

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
				_arr.splice(i, 1);
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
			_moduleInstances.app[_app] = modInsts;

			// Otherwise create the application specific instances
			for (var key in _modules)
			{
				var mod = _modules[key];

				// Check whether the module is actually an application local
				// instance. As the module instance may already have been
				// created by another extension (when calling the egw.module
				// function) we're doing the second check.
				if (mod.flags === _egw.MODULE_APP_LOCAL
				    && typeof modInsts[key] === 'undefined')
				{
					modInsts[key] = mod.code.call(_egw, _app, window);
				}
			}
		}

		return _moduleInstances.app[_app];
	}

	function getExistingWndModules(_moduleInstances, _window)
	{
		// Search for the specific window instance
		for (var i = 0; i < _moduleInstances.wnd.length; i++)
		{
			if (_moduleInstances.wnd[i].window === _window)
			{
				return _moduleInstances.wnd[i].modules;
			}
		}

		return null;
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
	 * @param _window is the window for which the module instances should get
	 * 	created.
	 */
	function getWndModules(_egw, _modules, _moduleInstances, _instances, _window)
	{
		var mods = getExistingWndModules(_moduleInstances, _window);
		if (mods) {
			return mods;
		}

		// If none was found, create the slot
		mods = {};
		_moduleInstances.wnd.push({
			'window': _window,
			'modules': mods
		});

		// Add an eventlistener for the "onunload" event -- if "onunload" gets
		// called, we have to delete the module slot created above
		var fnct = function() {
			cleanupEgwInstances(_instances, _moduleInstances, function(_w) {
				return _w.window === _window;
			});
		};
		if (_window.attachEvent)
		{
			_window.attachEvent('onbeforeunload', fnct);
		}
		else
		{
			_window.addEventListener('beforeunload', fnct, false);
		}

		// Otherwise create the window specific instances
		for (var key in _modules)
		{
			var mod = _modules[key];

			// Check whether the module is actually a window local instance. As
			// the module instance may already have been created by another
			// extension (when calling the egw.module function) we're doing the
			// second check.
			if (mod.flags === _egw.MODULE_WND_LOCAL
			    && typeof mods[key] === 'undefined')
			{
				mods[key] = mod.code.call(_egw, null, _window);
			}
		}

		return mods;
	}

	/**
	 * Creates an api instance for the given application and the given window.
	 *
	 * @param {globalEgw} _egw is the global _egw instance which should be used.
	 * @param {object} _modules is the hash map which contains references to all module
	 * 	descriptors.
	 * @param {object} _moduleInstances is the the object which contains the application
	 * 	and window specific module instances.
	 * @param {array} _list is the overall instances list, to which the module should be
	 * 	added.
	 * @param {object} _instances is the overall instances list, to which the module should be
	 * 	added.
	 * @param {string} _app is the application for which the instance should be created.
	 * @param {DOMElement} _window is the window for which the instance should be created.
	 * @return {egw}
	 */
	function createEgwInstance(_egw, _modules, _moduleInstances, _list, _instances, _app, _window)
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
	 * @param {globalEgw} _egw is the global _egw instance which should be used.
	 * @param {object} _modules is the hash map which contains references to all module
	 * 	descriptors.
	 * @param {object} _moduleInstances is the the object which contains the application
	 * 	and window specific module instances.
	 * @param {object} _instances is the overall instances list, to which the module should be
	 * 	added.
	 * @param {string} _app is the application for which the instance should be created.
	 * @param {DOMElement} _window is the window for which the instance should be created.
	 * @return {egw}
	 */
	function getEgwInstance(_egw, _modules, _moduleInstances, _instances, _app, _window)
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

		// Store the global extension module
		_moduleInstances.glo[_module] = globalExtension;

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

		// Store the global extension module
		_moduleInstances.glo[_module] = globalExtension;

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

	/**
	 * Creates the egw object --- if the egw object should be created, some data
	 * has already been set inside the object by the Api\Framework::header
	 * function and the instance has been marked as "prefsOnly".
	 */
	if (typeof window.egw != "undefined" && window.egw.prefsOnly)
	{
		// Rescue the old egw object
		var prefs = window.egw;
		delete prefs['prefsOnly'];

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
			'wnd': [],
			'glo': {}
		};

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
				try {
					return w.window && w.window.closed;
				}
				catch(e) {
					// IE(11) seems to throw a permission denied error, when accessing closed property
					return true;
				}
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
		 *
		 * @return {egw}
		 */
		var egw = function() {

			// Get the window/app reference
			var _app = null;
			var _window = window;

			switch (arguments.length)
			{
				case 0:
					// Return the global instance
					return instances['~global~'][0]['instance'];

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
		};

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

			/**
			 * Very similar to the egw function itself, but the module function
			 * returns just the functions exported by a single extension -- in
			 * this way extensions themselve are capable of accessing each
			 * others functions while they are being instanciated. Yet you
			 * should be carefull not to create any cyclic dependencies.
			 *
			 * @param _module is the name of the module
			 * @param _for may either be a string describing an application,
			 * 	an object referencing to a window or evaluate to false, in which
			 * 	case the global instance will be returned.
			 */
			module: function(_module, _for) {
				if (typeof modules[_module] !== 'undefined')
				{
					// Return the global instance of the module if _for
					// evaluates to false
					if (!_for)
					{
						return moduleInstances.glo[_module];
					}

					// Assume _for is an application name if it is a string.
					// Check whether the given application instance actually
					// exists.
					if (typeof _for === 'string'
					    && typeof moduleInstances.app[_for] !== 'undefined')
					{
						var mods = moduleInstances.app[_for];

						// Otherwise just instanciate the module if it has not
						// been created yet.
						if (typeof mods[_module] === 'undefined')
						{
							var mod = modules[_module];
							mods[_module] = mod.code.call(this, _app, window);
						}

						return mods[_module];
					}

					// If _for is an object, assume it is a window.
					if (typeof _for === 'object')
					{
						var mods = getExistingWndModules(moduleInstances, _for);

						// Check whether the module container for that window
						// has been found
						if (mods != null && typeof mods[_module] != 'undefined')
						{
							return mods[_module];
						}
						// If the given module has not been instanciated for
						// this window, instanciate it
						if (mods == null) mods = {};
						if (typeof mods[_module] === 'undefined')
						{
							var mod = modules[_module];
							mods[_module] = mod.code.call(this, null, _for);
						}
						return mods[_module];

					}
				}

				return null;
			},

			/**
			 * The "constant" function can be used to update a constant in all
			 * egw instances.
			 *
			 * @param _module is the module for which the constant should be set
			 * @param _name is the name of the constant
			 * @param _value is the value to which it should be set
			 * @param _window if set, updating the constant is restricted to
			 * 	those api instances which belong to the given window, if _window
			 * 	evaluates to false, all instances will be updated.
			 */
			constant: function(_module, _name, _value, _window) {
				// Update the module instances first
				for (var i = 0; i < moduleInstances.wnd.length; i++)
				{
					if (!_window || _window === moduleInstances.wnd[i].window)
					{
						moduleInstances.wnd[i].modules[_module][_name] = _value;
					}
				}

				// Now update all already instanciated instances
				for (var key in instances)
				{
					for (var i = 0; i < instances[key].length; i++)
					{
						if (!_window || _window === instances[key][i].window)
						{
							instances[key][i].instance[_name] = _value;
						}
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
				};
			}
		};

		// Merge the globalEgw functions into the egw object.
		mergeObjects(egw, globalEgw);

		// Merge the preferences into the egw object.
		mergeObjects(egw, prefs);

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

