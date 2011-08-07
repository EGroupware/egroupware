/**
 * eGroupWare eTemplate2 - JS code for implementing inheritance
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
 */

"use strict";

/**
 * Usage of the JS inheritance system
 * ----------------------------------
 *
 * To create a class write
 * 
 * MyClass = Class.extend([interfaces, ] functions);
 *
 * where "interfaces" is a single interface or an array of interfaces and
 * functions an object containing the functions the class implements.
 *
 * An interface has to be created in the following way:
 *
 * var IBreathingObject = new Interface({
 * 		breath: function() {}
 * });
 * 
 * var Human = Class.extend(IBreathingObject, {
 * 		walk: function() {
 * 			console.log("Walking");
 * 		},
 * 		speak: function(_words) {
 * 			console.log(_words);
 * 		}
 * });
 * 
 * As "Human" does not implement the function "breath", "Human" is treated as
 * abstract. Trying to create an instance of "Human" will throw an exception.
 * However
 * 
 * Human.prototype.implements(IBreathingObject);
 * 
 * will return true. Lets create a specific class of "Human":
 *
 * var ChuckNorris = Human.extend({
 * 		breath: function() {
 * 			console.log("Chuck Norris does not breath, he holds air hostage.");
 * 		},
 * 		speak: function(_words) {
 * 			console.warn("Chuck Norris says:");
 * 			this._super(_words);
 * 		}
 * });
 */

// The following code is mostly taken from 
// http://ejohn.org/blog/simple-javascript-inheritance/
// some parts were slightly changed for better understanding. Added possiblity
// to use interfaces.

/* Simple JavaScript Inheritance
 * By John Resig http://ejohn.org/
 * MIT Licensed
 */
// Inspired by base2 and Prototype
(function(){
	var initializing = false

	// Check whether "function decompilation" works - fnTest is normally used to
	// check whether a 
	var fnTest = /xyz/.test(function(){xyz;}) ? /\b_super\b/ : /.*/;

	// Base "Class" for interfaces - needed to check whether an object is an
	// interface
	this.Interface = function(fncts) {
		for (var key in fncts)
		{
			this[key] = fncts[key];
		}
	};

	/**
	 * The addInterfaceStuff function adds all interface functions the class has
	 * to implement to the class prototype.
	 */
	function addInterfaceStuff(prototype, interfaces)
	{
		// Remember all interface functions in the prototype
		var ifaces = ((typeof prototype["_ifacefuncs"] == "undefined") ? [] :
			prototype["_ifacefuncs"]);

		prototype["_ifacefuncs"] = [];

		for (var i = 0; i < interfaces.length; i++)
		{
			var iface = interfaces[i];
			if (iface instanceof Interface)
			{
				for (var key in iface)
				{
					prototype["_ifacefuncs"].push(key);
				}
			}
			else
			{
				throw("Interfaces must be instanceof Interface!");
			}
		}

		for (var i = 0; i < ifaces.length; i++)
		{
			prototype["_ifacefuncs"].push(ifaces[i]);
		}

		// The implements function can be used to check whether the object
		// implements the given interface.
		prototype["implements"] = function(_iface) {
			for (var key in _iface)
			{
				if (this._ifacefuncs.indexOf(key) < 0)
				{
					return false;
				}
			}
			return true;
		}

		// The instanceOf function can be used to check for both - classes and
		// interfaces. Please don't change the case of this function as this
		// affects IE and Opera support.
		prototype["instanceOf"] = function(_obj) {
			if (_obj instanceof Interface)
			{
				return this.implements(_obj);
			}
			else
			{
				return this instanceof _obj;
			}
		}
	};

	function classExtend(interfaces, prop) {

		if (typeof prop == "undefined")
		{
			prop = interfaces;
			interfaces = [];
		}

		// If a single interface is given, encapsulate it in an array
		if (!(interfaces instanceof Array))
		{
			interfaces = [interfaces];
		}

		var _super = this.prototype;

		// Instantiate a base class (but only create the instance,
		// don't run the init constructor)
		initializing = true;
		var prototype = new this();
		initializing = false;

		// Copy the properties over onto the new prototype
		for (var name in prop) {
			// Check if we're overwriting an existing function and check whether
			// the function actually uses "_super" - the RegExp test function
			// silently converts the funciton prop[name] to a string.
			if (typeof prop[name] == "function" &&
			    typeof _super[name] == "function" && fnTest.test(prop[name]))
			{
				prototype[name] = (function(name, fn){
					return function() {
						var tmp = this._super;

						// Add a new ._super() method that is the same method
						// but on the super-class
						this._super = _super[name];

						// The method only need to be bound temporarily, so we
						// remove it when we're done executing
						var ret = fn.apply(this, arguments);
						this._super = tmp;

						return ret;
					};
				})(name, prop[name]);
			}
			else
			{
				prototype[name] = prop[name];
			}
		}

		// Add the interface functions and the "implements" function to the
		// prototype
		addInterfaceStuff(prototype, interfaces);

		// The dummy class constructor
		function Class() {
			// All construction is actually done in the init method
			if (!initializing)
			{
				// Check whether the object implements all interface functions
				for (var i = 0; i < this._ifacefuncs.length; i++)
				{
					var func = this._ifacefuncs[i];
					if (!(typeof this[func] == "function"))
					{
						throw("Trying to create abstract object, interface " + 
							"function '" + func + "' not implemented.");
					}
				}

				if (this.init)
				{
					this.init.apply(this, arguments);
				}
			}
		}

		// Populate our constructed prototype object
		Class.prototype = prototype;

		// Enforce the constructor to be what we expect
		Class.prototype.constructor = Class;

		// And make this class extendable
		Class.extend = classExtend;

		return Class;
	};

	// The base Class implementation (does nothing)
	this.Class = function(){};

	// Create a new Class that inherits from this class. The first parameter
	// is an array which defines a set of interfaces the object has to
	// implement. An interface is simply an object with named functions.
	Class.extend = classExtend;
}).call(window);

