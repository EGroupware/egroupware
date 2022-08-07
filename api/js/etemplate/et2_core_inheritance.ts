/**
 * EGroupware eTemplate2 - JS code for implementing inheritance with attributes
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link: https://www.egroupware.org
 * @author Andreas Stöckel
 */

/*egw:uses
	et2_core_common;
*/

import {egw, IegwAppLocal} from "../jsapi/egw_global";
import {et2_checkType, et2_no_init, et2_validateAttrib} from "./et2_core_common";
import {et2_implements_registry} from "./et2_core_interfaces";
import {Et2Widget} from "./Et2Widget/Et2Widget";

export class ClassWithInterfaces
{
		/**
		 * The implements function can be used to check whether the object
		 * implements the given interface.
		 *
		 * As TypeScript can not (yet) check if an objects implements an interface on runtime,
		 * we currently implements with each interface a function called 'implements_'+interfacename
		 * to be able to check here.
		 *
		 * @param _iface name of interface to check
		 */
		implements(_iface_name: string)
		{
				if (typeof et2_implements_registry[_iface_name] === 'function' &&
						et2_implements_registry[_iface_name](this))
				{
						return true
				}
				return false;
		}

		/**
		 * Check if object is an instance of a class or implements an interface (specified by the interfaces name)
		 *
		 * @param _class_or_interfacename class(-name) or string with name of interface
		 */
		instanceOf(_class_or_interfacename: any): boolean
		{
				if (typeof _class_or_interfacename === 'string')
				{
						return this.implements(_class_or_interfacename);
				}
				if (_class_or_interfacename === Et2Widget)
				{
					return true;
				}
				return this instanceof _class_or_interfacename;
		}
}

export class ClassWithAttributes extends ClassWithInterfaces
{
		/**
		 * Object to collect the attributes we operate on
		 */
		attributes: object;

		/**
		 * Returns the value of the given attribute. If the property does not
		 * exist, an error message is issued.
		 *
		 * @param {string} _name
		 * @return {*}
		 */
		getAttribute(_name)
		{
				if (typeof this.attributes[_name] != "undefined" &&
						!this.attributes[_name].ignore)
				{
						if (typeof this["get_" + _name] == "function")
						{
								return this["get_" + _name]();
						}
						else
						{
								return this[_name];
						}
				}
				else
				{
						egw.debug("error", this, "Attribute '" + _name + "' does not exist!");
				}
		}

		/**
		 * The setAttribute function sets the attribute with the given name to
		 * the given value. _override defines, whether this[_name] will be set,
		 * if this key already exists. _override defaults to true. A warning
		 * is issued if the attribute does not exist.
		 *
		 * @param {string} _name
		 * @param {*} _value
		 * @param {boolean} _override
		 */
		setAttribute(_name, _value, _override)
		{
				if (typeof this.attributes[_name] != "undefined")
				{
						if (!this.attributes[_name].ignore)
						{
								if (typeof _override == "undefined")
								{
										_override = true;
								}

								var val = et2_checkType(_value, this.attributes[_name].type,
										_name, this);

								if (typeof this["set_" + _name] == "function")
								{
										this["set_" + _name](val);
								}
								else if (_override || typeof this[_name] == "undefined")
								{
										this[_name] = val;
								}
						}
				}
				else
				{
						egw.debug("warn", this, "Attribute '" + _name + "' does not exist!");
				}
		}

		/**
		 * generateAttributeSet sanitizes the given associative array of attributes
		 * (by passing each entry to "et2_checkType" and checking for existance of
		 * the attribute) and adds the default values to the associative array.
		 *
		 * @param {object} _attrs is the associative array containing the attributes.
		 */
		static generateAttributeSet(widget, _attrs)
		{
				// Sanity check and validation
				for (var key in _attrs)
				{
						if (typeof widget[key] != "undefined")
						{
								if (!widget[key].ignore)
								{
										_attrs[key] = et2_checkType(_attrs[key], widget[key].type,
												key, this);
								}
						}
						else
						{
								// Key does not exist - delete it and issue a warning
								delete (_attrs[key]);
								egw.debug("warn", this, "Attribute '" + key +
										"' does not exist in " + _attrs.type + "!");
						}
				}

				// Include default values or already set values for this attribute
				for (var key in widget)
				{
						if (typeof _attrs[key] == "undefined")
						{
								var _default = widget[key]["default"];
								if (_default == et2_no_init)
								{
										_default = undefined;
								}

								_attrs[key] = _default;
						}
				}

				return _attrs;
		}

		/**
		 * The initAttributes function sets the attributes to their default
		 * values. The attributes are not overwritten, which means, that the
		 * default is only set, if either a setter exists or this[propName] does
		 * not exist yet.
		 *
		 * @param {object} _attrs is the associative array containing the attributes.
		 */
		initAttributes(_attrs)
		{
				for (var key in _attrs)
				{
						if (typeof this.attributes[key] != "undefined" && !this.attributes[key].ignore && !(_attrs[key] == undefined))
						{
								this.setAttribute(key, _attrs[key], false);
						}
				}
		}

		static buildAttributes(class_prototype: object)
		{
				let class_tree = [];
				let attributes = {};
				let n = 0;
				do
				{
					n++;
					class_tree.push(class_prototype);
					class_prototype = Object.getPrototypeOf(class_prototype);
				}
				while(class_prototype && class_prototype !== ClassWithAttributes && n < 50);

				for (let i = class_tree.length - 1; i >= 0; i--)
				{
						attributes = ClassWithAttributes.extendAttributes(attributes, class_tree[i]._attributes);
				}
				return attributes;
		}

		/**
		 * Extend current _attributes with the one from the parent class
		 *
		 * This gives inheritance from the parent plus the ability to override in the current class.
		 *
		 * @param _attributes
		 * @param _parent
		 */
		static extendAttributes(_parent: object, _attributes: object): object
		{
				function _copyMerge(_new, _old)
				{
						var result = {};

						// Copy the new object
						if (typeof _new != "undefined")
						{
								for (var key in _new)
								{
										result[key] = _new[key];
								}
						}

						// Merge the old object
						for (var key in _old)
						{
								if (typeof result[key] == "undefined")
								{
										result[key] = _old[key];
								}
						}

						return result;
				}

				var attributes = {};

				// Copy the old attributes
				for (var key in _attributes)
				{
						attributes[key] = _copyMerge({}, _attributes[key]);
				}

				// Add the old attributes to the new ones. If the attributes already
				// exist, they are merged.
				for (var key in _parent)
				{
						var _old = _parent[key];

						attributes[key] = _copyMerge(attributes[key], _old);
				}

				// Validate the attributes
				for (var key in attributes)
				{
						et2_validateAttrib(key, attributes[key]);
				}

				return attributes;
		}
}