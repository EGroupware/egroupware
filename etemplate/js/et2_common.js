/**
 * eGroupWare eTemplate2 - JS Widget base class
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
 */

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
 * ET2_DEBUGLEVEL specifies which messages are printed to the console. Decrease
 * the value of ET2_DEBUGLEVEL to get less messages.
 */
var ET2_DEBUGLEVEL = 0;

function et2_debug(_level, _msg)
{
	if (typeof console != "undefined")
	{
		if (_level == "log" && ET2_DEBUGLEVEL >= 4 &&
		    typeof console.log == "function")
		{
			console.log(_msg);
		}

		if (_level == "info" && ET2_DEBUGLEVEL >= 3 &&
		    typeof console.info == "function")
		{
			console.info(_msg);
		}

		if (_level == "warn" && ET2_DEBUGLEVEL >= 2 &&
		    typeof console.warn == "function")
		{
			console.warn(_msg);
		}

		if (_level == "error" && ET2_DEBUGLEVEL >= 1 &&
		    typeof console.error == "function")
		{
			console.error(_msg);
		}
	}
}

/**
 * Array with all types supported by the et2_checkType function.
 */
var et2_validTypes = ["boolean", "string", "float", "integer", "any"];

/**
 * Object whith default values for the above types. Do not specify array or
 * objects inside the et2_typeDefaults object, as this instance will be shared
 * between all users of it.
 */
var et2_typeDefaults = {
	"boolean": false,
	"string": "",
	"float": 0.0,
	"integer": 0,
	"any": null
};

/**
 * Checks whether the given value is of the given type. Strings are converted
 * into the corresponding type. The (converted) value is returned. All supported
 * types are listed in the et2_validTypes array.
 */
function et2_checkType(_val, _type)
{
	function _err() {
		throw("'" + _val + "' is not of specified _type '" +  _type + "'");
	}

	// If the type is "any" simply return the value again
	if (_type == "any")
	{
		return _val;
	}

	// If the type is boolean, check whether the given value is exactly true or
	// false. Otherwise check whether the value is the string "true" or "false".
	if (_type == "boolean")
	{
		if (_val === true || _val === false)
		{
			return _val;
		}

		var lcv = _val.toLowerCase();
		if (lcv === "true" || lcv === "false" || lcv === "")
		{
			return _val === "true";
		}

		_err();
	}

	// Check whether the given value is of the type "string"
	if (_type == "string")
	{
		if (typeof _val == "string")
		{
			return _val;
		}

		_err();
	}

	// Check whether the value is already a number, otherwise try to convert it
	// to one.
	if (_type == "float")
	{
		if (typeof _val == "number")
		{
			return _val;
		}

		if (!isNaN(_val))
		{
			return parseFloat(_val);
		}

		_err();
	}

	// Check whether the value is an integer by comparing the result of
	// parseInt(_val) to the value itself.
	if (_type == "integer")
	{
		if (parseInt(_val) == _val)
		{
			return parseInt(_val);
		}

		_err();
	}

	// We should never come here
	throw("Invalid type identifier supplied.");
}

/**
 * Validates the given attribute with the given id. The validation checks for
 * the existance of a human name, a description, a type and a default value.
 * If the human name defaults to the given id, the description defaults to an
 * empty string, the type defaults to any and the default to the corresponding
 * type default.
 */
function et2_validateAttrib(_id, _attrib)
{
	// Default ignore to false.
	if (typeof _attrib["ignore"] == "undefined")
	{
		_attrib["ignore"] = false
	}

	// Break if "ignore" is set to true.
	if (_attrib.ignore)
	{
		return;
	}

	if (typeof _attrib["name"] == "undefined")
	{
		_attrib["name"] = _id;
		et2_debug("log", "Human name ('name'-Field) for attribute '" +
			_id + "' has not been supplied, set to '" + _id + "'");
	}

	if (typeof _attrib["description"] == "undefined")
	{
		_attrib["description"] = "";
		et2_debug("log", "Description for attribute '" +
			_id + "' has not been supplied");
	}

	if (typeof _attrib["type"] == "undefined")
	{
		_attrib["type"] = "any";
	}
	else
	{
		if (et2_validTypes.indexOf(_attrib["type"]) < 0)
		{
			et2_debug("error", "Invalid type for attribute '" + _id + 
			    "' supplied.");
		}
	}

	// Set the defaults
	if (typeof _attrib["default"] == "undefined")
	{
		_attrib["default"] = et2_typeDefaults[_attrib["type"]];
	}
}

/**
 * Equivalent to the PHP array_values function
 */
function et2_arrayValues(_arr)
{
	var result = [];
	for (var key in _arr)
	{
		if (parseInt(key) == key)
		{
			result.push(_arr[key]);
		}
	}

	return result;
}

/**
 * Equivalent to the PHP substr function, partly take from phpjs, licensed under
 * the GPL.
 */
function et2_substr (str, start, len) {
	var end = str.length;

	if (start < 0)
	{
		start += end;
	}
	end = typeof len === 'undefined' ? end : (len < 0 ? len + end : len + start);

	return start >= str.length || start < 0 || start > end ? "" : str.slice(start, end);
}

/**
 * Split a $delimiter-separated options string, which can contain parts with
 * delimiters enclosed in $enclosure. Ported from class.boetemplate.inc.php
 *
 * Examples:
 * - et2_csvSplit('"1,2,3",2,3') === array('1,2,3','2','3')
 * - et2_csvSplit('1,2,3',2) === array('1','2,3')
 * - et2_csvSplit('"1,2,3",2,3',2) === array('1,2,3','2,3')
 * - et2_csvSplit('"a""b,c",d') === array('a"b,c','d')	// to escape enclosures double them!
 *
 * @param string _str
 * @param int _num=null in how many parts to split maximal, parts over this
 * 	number end up (unseparated) in the last part
 * @param string _delimiter=','
 * @param string _enclosure='"'
 * @return array
 */
function et2_csvSplit(_str, _num, _delimiter, _enclosure)
{
	// Default the parameters
	if (typeof _num == "undefined")
	{
		_num == null;
	}

	if (typeof _delimiter == "undefined")
	{
		_delimiter = ",";
	}

	if (typeof _enclosure == "undefined")
	{
		_enclosure = '"';
	}

	// If the _enclosure string does not occur in the string, simply use the
	// split function
	if (_str.indexOf(_enclosure) == -1)
	{
		return _num === null ? _str.split(_delimiter) :
			_str.split(_delimiter, _num);
	}

	// Split the string at the delimiter and join it again, when a enclosure is
	// found at the beginning/end of a part
	var parts = _str.split(_delimiter); 
	for (var n = 0; typeof parts[n] != "undefined"; n++)
	{
		var part = parts[n];

		if (part.charAt(0) === _enclosure)
		{
			var m = n;
			while (typeof parts[m + 1] != "undefined" && parts[n].substr(-1) !== _enclosure)
			{
				parts[n] += _delimiter + parts[++m];
				delete(parts[m]);
			}
			parts[n] = et2_substr(parts[n].replace(
				new RegExp(_enclosure + _enclosure, 'g'), _enclosure), 1 , -1);
			n = m;
		}
	}

	// Rebuild the array index
	parts = et2_arrayValues(parts);

	// Limit the parts to the given number
	if (_num !== null && _num > 0 && _num < parts.length && parts.length > 0)
	{
		parts[_num - 1] = parts.slice(_num - 1, parts.length).join(_delimiter);
		parts = parts.slice(0, _num);
	}

	return parts;
}


