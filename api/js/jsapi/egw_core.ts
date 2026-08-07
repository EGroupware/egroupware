 /**
 * EGroupware clientside API object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel (as AT stylite.de)
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 */

/**
 * This code sets up the egw namespace and adds the "extend" function, which is
 * used by extension modules to inject their content into the egw object.
 *
 * Kept as a plain, non-module IIFE (like the .js file it replaces) rather than
 * an ES module - it's loaded via a classic <script src> tag (both in the real
 * bootstrap and in EgwCoreHarness.ts), and never itself imports or is imported.
 * Its typed public contract (Iegw/IegwGlobal/IegwWndLocal/IegwAppLocal) lives in
 * egw_global.d.ts, not here - this file is the implementation.
 */
(function()
{
	"use strict";

	/** One entry in the module registry created by egw.extend() */
	interface ModuleDescriptor
	{
		code : ModuleFactory;
		flags : number;
		name : string;
	}

	type ModuleFactory = (this : any, _app : string | null, _wnd : Window) => object;

	/** One cached egw(app, wnd) instance */
	interface InstanceEntry
	{
		instance : any;
		window : Window | null;
		app : string | null;
	}

	/** instances['~global~' | app-name] -> window -> cached instance entry */
	type InstancesMap = Map<string, Map<Window | null, InstanceEntry>>;

	interface ModuleInstancesState
	{
		app : Map<string, Map<string, object>>;
		wnd : Map<Window, Map<string, object>>;
		glo : Map<string, object>;
	}

	// Some local functions for cloning and merging javascript objects
	function cloneObject(_obj : any) : any
	{
		var result : any = {};

		for (var key in _obj)
		{
			result[key] = _obj[key];
		}

		return result;
	}

	function mergeObjects(_to : any, _from : any) : void
	{
		// Extend the egw object
		for (var key in _from)
		{
			_to[key] = _from[key];
		}
	}

	/**
	 * The getAppModules function returns all application specific api modules
	 * for the given application. If those application specific api instances
	 * were not created yet, the functions creates them.
	 *
	 * @param _egw is a reference to the global _egw instance and is passed as
	 * 	a context to the module instance.
	 * @param _modules is the registry which contains all module descriptors.
	 * @param _moduleInstances is the the object which contains the application
	 * 	and window specific module instances.
	 * @param _app is the application for which the module instances should get
	 * 	created.
	 */
	function getAppModules(_egw : any, _modules : Map<string, ModuleDescriptor>, _moduleInstances : ModuleInstancesState, _app : string) : Map<string, object>
	{
		// Check whether the application specific modules for that instance
		// already exists, if not, create it
		if (!_moduleInstances.app.has(_app))
		{
			var modInsts = new Map<string, object>();
			_moduleInstances.app.set(_app, modInsts);

			// Otherwise create the application specific instances
			for (var [key, mod] of _modules)
			{
				// Check whether the module is actually an application local
				// instance. As the module instance may already have been
				// created by another extension (when calling the egw.module
				// function) we're doing the second check.
				if (mod.flags === _egw.MODULE_APP_LOCAL && !modInsts.has(key))
				{
					modInsts.set(key, mod.code.call(_egw, _app, window));
				}
			}
		}

		return _moduleInstances.app.get(_app)!;
	}

	function getExistingWndModules(_moduleInstances : ModuleInstancesState, _window : Window) : Map<string, object> | null
	{
		return _moduleInstances.wnd.get(_window) || null;
	}

	/**
	 * The getWndModules function returns all window specific api modules for
	 * the given window. If those window specific api instances were not created
	 * yet, the functions creates them.
	 *
	 * @param _egw is a reference to the global _egw instance and is passed as
	 * 	a context to the module instance.
	 * @param _modules is the registry which contains all module descriptors.
	 * @param _moduleInstances is the the object which contains the application
	 * 	and window specific module instances.
	 * @param _instances refers to all api instances.
	 * @param _window is the window for which the module instances should get
	 * 	created.
	 */
	function getWndModules(_egw : any, _modules : Map<string, ModuleDescriptor>, _moduleInstances : ModuleInstancesState, _instances : InstancesMap, _window : Window) : Map<string, object>
	{
		var mods = getExistingWndModules(_moduleInstances, _window);
		if (mods)
		{
			return mods;
		}

		// If none was found, create the slot
		mods = new Map<string, object>();
		_moduleInstances.wnd.set(_window, mods);

		// Add an eventlistener for the "onunload" event -- if "onunload" gets
		// called, we have to delete the module slot created above
		var fnct = function()
		{
			cleanupEgwInstances(_instances, _moduleInstances, function(_w)
			{
				return _w.window === _window;
			});
		};
		if ((<any>_window).attachEvent)
		{
			(<any>_window).attachEvent('onbeforeunload', fnct);
		}
		else
		{
			_window.addEventListener('beforeunload', fnct, false);
		}

		// Otherwise create the window specific instances
		for (var [key, mod] of _modules)
		{
			// Check whether the module is actually a window local instance. As
			// the module instance may already have been created by another
			// extension (when calling the egw.module function) we're doing the
			// second check.
			if (mod.flags === _egw.MODULE_WND_LOCAL && !mods.has(key))
			{
				mods.set(key, mod.code.call(_egw, null, _window));
			}
		}

		return mods;
	}

	/**
	 * Creates an api instance for the given application and the given window.
	 *
	 * @param _egw is the global _egw instance which should be used.
	 * @param _modules is the registry which contains references to all module
	 * 	descriptors.
	 * @param _moduleInstances is the the object which contains the application
	 * 	and window specific module instances.
	 * @param _byWindow is the per-window map for this (app|'~global~') hash bucket,
	 * 	to which the new instance should be added.
	 * @param _instances is the overall instances map, to which the module should be
	 * 	added.
	 * @param _app is the application for which the instance should be created.
	 * @param _window is the window for which the instance should be created.
	 */
	function createEgwInstance(_egw : any, _modules : Map<string, ModuleDescriptor>, _moduleInstances : ModuleInstancesState, _byWindow : Map<Window | null, InstanceEntry>, _instances : InstancesMap, _app : string | null, _window : Window | null) : any
	{
		// Clone the global object
		var instance = cloneObject(_egw);

		// Let "_window" and "_app" be exactly null, if it evaluates to false
		_window = _window ? _window : null;
		_app = _app ? _app : null;

		// Set the application name and the window the API instance belongs to
		instance.appName = _app;
		instance.window = _window;

		// Register the newly created instance
		_byWindow.set(_window, {
			'instance': instance,
			'window': _window,
			'app': _app
		});

		// Merge either the application specific and/or the window specific
		// module instances into the new instance
		if (_app)
		{
			var appModules = getAppModules(_egw, _modules, _moduleInstances, _app);

			for (var [, mod] of appModules)
			{
				mergeObjects(instance, mod);
			}
		}

		if (_window)
		{
			var wndModules = getWndModules(_egw, _modules, _moduleInstances, _instances, _window);

			for (var [, mod] of wndModules)
			{
				mergeObjects(instance, mod);
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
	 * @param _modules is the registry which contains references to all module
	 * 	descriptors.
	 * @param _moduleInstances is the the object which contains the application
	 * 	and window specific module instances.
	 * @param _instances is the overall instances map, to which the module should be
	 * 	added.
	 * @param _app is the application for which the instance should be created.
	 * @param _window is the window for which the instance should be created.
	 */
	function getEgwInstance(_egw : any, _modules : Map<string, ModuleDescriptor>, _moduleInstances : ModuleInstancesState, _instances : InstancesMap, _app : string | null, _window : Window | null) : any
	{
		// Generate the hash key for the instance descriptor object
		var hash = _app ? _app : '~global~';

		// Let "_window" be exactly null, if it evaluates to false
		_window = _window ? _window : null;

		var byWindow = _instances.get(hash);
		if (!byWindow)
		{
			// Create a new entry if the calculated hash does not exist
			byWindow = new Map<Window | null, InstanceEntry>();
			_instances.set(hash, byWindow);
		}
		else if (byWindow.has(_window))
		{
			// Found the api instance corresponding to the given window
			return byWindow.get(_window)!.instance;
		}

		// If we're still here, no API instance for the given window has been
		// found -- create a new entry
		return createEgwInstance(_egw, _modules, _moduleInstances, byWindow, _instances, _app, _window);
	}

	function cleanupEgwInstances(_instances : InstancesMap, _moduleInstances : ModuleInstancesState, _cond : (_entry : {window : Window | null}) => boolean) : void
	{
		// Iterate over the instances
		for (var [key, byWindow] of _instances)
		{
			// Delete all entries corresponding to closed windows
			for (var [win, entry] of Array.from(byWindow))
			{
				if (_cond(entry))
				{
					entry.instance && entry.instance.unregisterAllPlugins();
					byWindow.delete(win);
				}
			}

			// Delete the complete hash bucket if it is now empty
			if (byWindow.size === 0)
			{
				_instances.delete(key);
			}
		}

		// Delete all entries corresponding to non existing elements in the
		// module instances
		for (var [wndWindow] of Array.from(_moduleInstances.wnd))
		{
			if (_cond({window: wndWindow}))
			{
				_moduleInstances.wnd.delete(wndWindow);
			}
		}
	}

	function mergeGlobalModule(_module : string, _code : ModuleFactory, _instances : InstancesMap, _moduleInstances : ModuleInstancesState) : void
	{
		// Generate the global extension
		var globalExtension = _code.call(egw, null, window);

		// Store the global extension module
		_moduleInstances.glo.set(_module, globalExtension);

		for (var [, byWindow] of _instances)
		{
			for (var [, entry] of byWindow)
			{
				mergeObjects(entry.instance, globalExtension);
			}
		}
	}

	function mergeAppLocalModule(_module : string, _code : ModuleFactory, _instances : InstancesMap, _moduleInstances : ModuleInstancesState) : void
	{
		// Generate the global extension
		var globalExtension = _code.call(egw, null, window);

		// Store the global extension module
		_moduleInstances.glo.set(_module, globalExtension);

		// Merge the extension into the global instances
		for (var [, entry] of _instances.get('~global~')!)
		{
			mergeObjects(entry.instance, globalExtension);
		}

		for (var [key, appMods] of _moduleInstances.app)
		{
			// Create the application specific instance and
			// store it in the module instances
			var appExtension = _code.call(egw, key, window);
			appMods.set(_module, appExtension);

			// Merge the extension into all instances for
			// the current application
			for (var [, entry] of _instances.get(key)!)
			{
				mergeObjects(entry.instance, appExtension);
			}
		}
	}

	function mergeWndLocalModule(_module : string, _code : ModuleFactory, _instances : InstancesMap, _moduleInstances : ModuleInstancesState) : void
	{
		// Iterate over all existing windows
		for (var [wnd, mods] of _moduleInstances.wnd)
		{
			// Create the window specific instance and
			// register it.
			var wndExtension = _code.call(egw, null, wnd);
			mods.set(_module, wndExtension);

			// Extend all existing instances which are using
			// this window.
			for (var [, byWindow] of _instances)
			{
				var entry = byWindow.get(wnd);
				if (entry)
				{
					mergeObjects(entry.instance, wndExtension);
				}
			}
		}
	}

	/**
	 * Creates the egw object --- if the egw object should be created, some data
	 * has already been set inside the object by the Api\Framework::header
	 * function and the instance has been marked as "prefsOnly".
	 */
	if (typeof (<any>window).egw != "undefined" && (<any>window).egw.prefsOnly)
	{
		// Rescue the old egw object
		var prefs = (<any>window).egw;
		delete prefs['prefsOnly'];

		/**
		 * Modules contains all currently loaded egw extension modules.
		 */
		var modules = new Map<string, ModuleDescriptor>();

		var moduleInstances : ModuleInstancesState = {
			'app': new Map<string, Map<string, object>>(),
			'wnd': new Map<Window, Map<string, object>>(),
			'glo': new Map<string, object>()
		};

		/**
		 * instances contains references to all created instances.
		 */
		var instances : InstancesMap = new Map<string, Map<Window | null, InstanceEntry>>();

		/**
		 * Set a interval which is used to cleanup unused API instances all 10
		 * seconds.
		 */
		window.setInterval(function()
		{
			cleanupEgwInstances(instances, moduleInstances, function(w)
			{
				try
				{
					return !!(w.window && (<any>w.window).closed);
				}
				catch (e)
				{
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
		 */
		var egw : any = function()
		{
			// Get the window/app reference
			var _app : string | null = null;
			var _window : Window = window;

			switch (arguments.length)
			{
				case 0:
					// _app stays null, _window stays the root window - the
					// bootstrap below has already seeded the '~global~' hash's
					// entry for the root window with this very egw object, so
					// getEgwInstance() finds and returns it directly.
					break;

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
			return getEgwInstance(egw, modules, moduleInstances, instances, _app, _window);
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
			 * instance, 'api' is returned.
			 */
			getAppName: function(this : any)
			{
				// Otherwise return the correct application name.
				return this.app_name() || this.appName || 'api';
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
			extend: function(_module : string, _flags : number, _code : ModuleFactory) : void
			{
				// Check whether that module is already registered
				if (!modules.has(_module))
				{
					// Create a new module entry
					modules.set(_module, {
						'code': _code,
						'flags': _flags,
						'name': _module
					});

					// Create new app/module specific instances for the new
					// module and merge the new module into all created
					// instances
					switch (_flags)
					{
						// Easiest case -- simply merge the extension into all
						// instances
						case egw.MODULE_GLOBAL:
							mergeGlobalModule(_module, _code, instances, moduleInstances);
							break;

						// Create new application specific instances and merge
						// those into all api instances for that application
						case egw.MODULE_APP_LOCAL:
							mergeAppLocalModule(_module, _code, instances, moduleInstances);
							break;

						// Create new window specific instances for each window
						// and merge those into all api instances for that
						// window
						case egw.MODULE_WND_LOCAL:
							mergeWndLocalModule(_module, _code, instances, moduleInstances);
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
			module: function(this : any, _module : string, _for? : string | Window) : any
			{
				var mod = modules.get(_module);
				if (mod)
				{
					// Return the global instance of the module if _for
					// evaluates to false
					if (!_for)
					{
						return moduleInstances.glo.get(_module);
					}

					// Assume _for is an application name if it is a string.
					// Check whether the given application instance actually
					// exists.
					if (typeof _for === 'string' && moduleInstances.app.has(_for))
					{
						var appMods = moduleInstances.app.get(_for)!;

						// Otherwise just instanciate the module if it has not
						// been created yet. (In practice unreachable: an app's
						// module slot is only created by getAppModules(), which
						// eagerly instantiates every MODULE_APP_LOCAL module for
						// it at that point - so this is a defensive fallback,
						// not a normally-exercised path. The original .js here
						// referenced an undeclared `_app` variable instead of
						// `_for`, which would have thrown if this branch were
						// ever actually reached.)
						if (!appMods.has(_module))
						{
							appMods.set(_module, mod.code.call(this, _for, window));
						}

						return appMods.get(_module);
					}

					// If _for is an object, assume it is a window.
					if (typeof _for === 'object')
					{
						var wndMods = getExistingWndModules(moduleInstances, _for);

						// Check whether the module container for that window
						// has been found
						if (wndMods != null && wndMods.has(_module))
						{
							return wndMods.get(_module);
						}
						// If the given module has not been instanciated for
						// this window, instanciate it. Note: if no module
						// container exists yet for this window, one is NOT
						// registered here - matching the original behaviour,
						// this creates and returns a throwaway instance on
						// every call until a real egw(app, window) call creates
						// a persisted slot (see EgwCore.test.ts's documented
						// KNOWN QUIRK).
						if (wndMods == null)
						{
							wndMods = new Map<string, object>();
						}
						if (!wndMods.has(_module))
						{
							wndMods.set(_module, mod.code.call(this, null, _for));
						}
						return wndMods.get(_module);
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
			constant: function(_module : string, _name : string, _value : any, _window? : Window) : void
			{
				// Update the module instances first
				for (var [wnd, mods] of moduleInstances.wnd)
				{
					if (!_window || _window === wnd)
					{
						(<any>mods.get(_module))[_name] = _value;
					}
				}

				// Now update all already instanciated instances
				for (var [, byWindow] of instances)
				{
					for (var [, entry] of byWindow)
					{
						if (!_window || _window === entry.window)
						{
							entry.instance[_name] = _value;
						}
					}
				}
			},

			dumpModules: function() : {[name : string] : ModuleDescriptor}
			{
				// Reconstructed as a plain object (matching the pre-Map shape)
				// so callers relying on property access (`modules.foo`) keep
				// working unchanged.
				var result : {[name : string] : ModuleDescriptor} = {};
				for (var [key, mod] of modules)
				{
					result[key] = mod;
				}
				return result;
			},

			dumpInstances: function() : {instances : object, moduleInstances : object}
			{
				// Reconstructed as the original hash-of-arrays / array-of-
				// {window,modules} shape, so callers keep working unchanged.
				var instancesOut : {[key : string] : InstanceEntry[]} = {};
				for (var [key, byWindow] of instances)
				{
					instancesOut[key] = Array.from(byWindow.values());
				}

				var appOut : {[app : string] : {[module : string] : object}} = {};
				for (var [app, mods] of moduleInstances.app)
				{
					var modsOut : {[module : string] : object} = {};
					for (var [mod, inst] of mods)
					{
						modsOut[mod] = inst;
					}
					appOut[app] = modsOut;
				}

				var wndOut = Array.from(moduleInstances.wnd, function([win, mods])
				{
					var modsOut : {[module : string] : object} = {};
					for (var [mod, inst] of mods)
					{
						modsOut[mod] = inst;
					}
					return {'window': win, 'modules': modsOut};
				});

				var gloOut : {[module : string] : object} = {};
				for (var [mod, inst] of moduleInstances.glo)
				{
					gloOut[mod] = inst;
				}

				return {
					'instances': instancesOut,
					'moduleInstances': {'app': appOut, 'wnd': wndOut, 'glo': gloOut}
				};
			}
		};

		// Merge the globalEgw functions into the egw object.
		mergeObjects(egw, globalEgw);

		// Merge the preferences into the egw object.
		mergeObjects(egw, prefs);

		// Create the entry for the root window in the module instances
		moduleInstances.wnd.set(window, new Map<string, object>());

		// Create the entry for the global window in the instances and register
		// the global instance there
		var rootByWindow = new Map<Window | null, InstanceEntry>();
		rootByWindow.set(window, {'window': window, 'instance': egw, 'app': null});
		instances.set('~global~', rootByWindow);

		// Publish the egw object
		(<any>window)['egw'] = egw;
	}
})();
