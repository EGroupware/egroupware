/**
 * EGroupware eTemplate2 - JS code for implementing inheritance with attributes
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
 */

/*egw:uses
	et2_core_common;
	egw_inheritance;
*/

var ClassWithAttributes = (function(){ "use strict"; return Class.extend(
{
	/**
	 * Returns the value of the given attribute. If the property does not
	 * exist, an error message is issued.
	 *
	 * @param {string} _name
	 * @return {*}
	 */
	getAttribute: function(_name) {
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
			egw.debug("error", this, "Attribute '" + _name  + "' does not exist!");
		}
	},

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
	setAttribute: function(_name, _value, _override) {
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
	},

	/**
	 * generateAttributeSet sanitizes the given associative array of attributes
	 * (by passing each entry to "et2_checkType" and checking for existance of
	 * the attribute) and adds the default values to the associative array.
	 *
	 * @param {object} _attrs is the associative array containing the attributes.
	 */
	generateAttributeSet: function(_attrs) {

		// Sanity check and validation
		for (var key in _attrs)
		{
			if (typeof this.attributes[key] != "undefined")
			{
				if (!this.attributes[key].ignore)
				{
					_attrs[key] = et2_checkType(_attrs[key], this.attributes[key].type,
						key, this);
				}
			}
			else
			{
				// Key does not exist - delete it and issue a warning
				delete(_attrs[key]);
				egw.debug("warn", this, "Attribute '" + key +
					"' does not exist in " + _attrs.type+"!");
			}
		}

		// Include default values or already set values for this attribute
		for (var key in this.attributes)
		{
			if (typeof _attrs[key] == "undefined")
			{
				var _default = this.attributes[key]["default"];
				if (_default == et2_no_init)
				{
					_default = undefined;
				}

				_attrs[key] = _default;
			}
		}

		return _attrs;
	},

	/**
	 * The initAttributes function sets the attributes to their default
	 * values. The attributes are not overwritten, which means, that the
	 * default is only set, if either a setter exists or this[propName] does
	 * not exist yet.
	 *
	 * @param {object} _attrs is the associative array containing the attributes.
	 */
	initAttributes: function(_attrs) {
		for (var key in _attrs)
		{
			if (typeof this.attributes[key] != "undefined" && !this.attributes[key].ignore && !(_attrs[key] == undefined))
			{
				this.setAttribute(key, _attrs[key], false);
			}
		}
	},

	_validate_attributes: function(attributes)
	{
		// Validate the attributes
		for (var key in attributes)
		{
			et2_validateAttrib(key, attributes[key]);
		}
	}
});}).call(this);