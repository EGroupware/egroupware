/**
 * EGroupware eTemplate2 - JS Widget base class
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright EGroupware GmbH 2011-2021
 */

import {egw} from "../jsapi/egw_global";

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
 * Array with all types supported by the et2_checkType function.
 */
export var et2_validTypes = ["boolean", "string", "rawstring", "html", "float", "integer", "any", "js", "dimension"];

/**
 * Object whith default values for the above types. Do not specify array or
 * objects inside the et2_typeDefaults object, as this instance will be shared
 * between all users of it.
 */
export var et2_typeDefaults : object = {
	"boolean": false,
	"string": "",
	"rawstring": "",	// no html-entity decoding
	"html": "",
	"js": null,
	"float": 0.0,
	"integer": 0,
	"any": null,
	"dimension": "auto"
};

export function et2_evalBool(_val)
{
	if (typeof _val == "string")
	{
		if (_val == "false" || _val == "0")
		{
			return false;
		}
	}

	return _val ? true : false;
}

/**
 * Concat et2 name together, eg. et2_concat("namespace","test[something]") == "namespace[test][something]"
 * @param variable number of arguments to contact
 * @returns string
 */
export function et2_form_name(_cname,_name)
{
	var parts = [];
	for(var i=0; i < arguments.length; ++i)
	{
		var name = arguments[i];
		if (typeof name == 'string' && name.length > 0)	// et2_namespace("","test") === "test" === et2_namespace(null,"test")
		{
			parts = parts.concat(name.replace(/]/g,'').split('['));
		}
	}
	var name = parts.shift();
	return parts.length ? name + '['+parts.join('][')+']' : name;
}

/**
 * Checks whether the given value is of the given type. Strings are converted
 * into the corresponding type. The (converted) value is returned. All supported
 * types are listed in the et2_validTypes array.
 *
 * @param mixed _val value
 * @param string _type a valid type eg. "string" or "js"
 * @param string _attr attribute name
 * @param object _widget
 */
export function et2_checkType(_val, _type, _attr, _widget)
{
	if (typeof _attr == "undefined")
	{
		_attr = null;
	}

	function _err() {
		var res = et2_typeDefaults[_type];

		if(typeof _val != "undefined" && _val)
		{
			egw.debug("warn", "Widget %o: '" + _val + "' was not of specified _type '" +
				_type + (_attr != null ? "' for attribute '" + _attr + "' " : "") +
				"and is now '" + res + "'",_widget);
		}
		return res;
	}

	// If the type is "any" simply return the value again
	if (_type == "any")
	{
		return _val;
	}

	// we dont check default-value any further, that also fixes type="js" does NOT accept null,
	// which happens on expanded values
	if (_val === et2_typeDefaults[_type])
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

		if (typeof _val == "string")
		{
			var lcv = _val.toLowerCase();
			if (lcv === "true" || lcv === "false" || lcv === "")
			{
				return _val === "true";
			}
			if(lcv === "0" || lcv === "1")
			{
				return _val === "1";
			}
		}
		else if (typeof _val == "number")
		{
			return _val != 0;
		}

		return _err();
	}

	// Check whether the given value is of the type "string"
	if (_type == "string" || _type == "html" || _type == "rawstring")
	{
		if (typeof _val == "number")	// as php is a bit vague here, silently convert to a string
		{
			return _val.toString();
		}

		if (typeof _val == "string")
		{
			return _type == "string" ? html_entity_decode(_val) : _val;
		}

		// Handle some less common possibilities
		// Maybe a split on an empty string
		if(typeof _val == "object" && jQuery.isEmptyObject(_val)) return "";

		return _err();
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

		return _err();
	}

	// Check whether the value is an integer by comparing the result of
	// parseInt(_val) to the value itself.
	if (_type == "integer")
	{
		const unit_reg_exp = /^(\d+)([kmg]{0,1}b?)$/i;
		// allow to use b, k, kb, m, mb, g or gb postfix
		if (typeof _val === "string" && _val.match(unit_reg_exp))
		{
			const matches = _val.match(unit_reg_exp);
			switch(matches[2][0].toLowerCase())
			{
				case 'b':
					_val = parseInt(matches[1]);
					break;
				case 'k':
					_val = parseInt(matches[1])*1024;
					break;
				case 'm':
					_val = parseInt(matches[1])*1024*1024;
					break;
				case 'g':
					_val = parseInt(matches[1])*1024*1024*1024;
					break;
			}
		}
		if (parseInt(_val) == _val)
		{
			return parseInt(_val);
		}

		return _err();
	}

	// Parse the given dimension value
	if (_type == "dimension")
	{
		// Case 1: The value is "auto"
		if (_val == "auto")
		{
			return _val;
		}

		// Case 2: The value is simply a number, attach "px"
		if (!isNaN(_val))
		{
			return parseFloat(_val) + "px";
		}

		// Case 3: The value is already a valid css pixel value or a percentage
		if (typeof _val == "string" &&
		   ((_val.indexOf("px") == _val.length - 2 && !isNaN(_val.split("px")[0] as any)) ||
		   (_val.indexOf("%") == _val.length - 1 && !isNaN(_val.split("%")[0] as any))))
		{
			return _val;
		}

		return _err();
	}

	// Javascript
	if (_type == "js")
	{
		if (typeof _val == "function" || typeof _val == "undefined")
		{
			return _val;
		}
		if (_val) _val = _val.replace(/window\.close\(\)/g, 'egw(window).close()');

		// Check to see if it's a string in app.appname.function format, and wrap it in
		// a closure to make sure context is preserved
		if(typeof _val == "string" && _val.substr(0,4) == "app." && app)
		{
			var parts = _val.split('.');
			var func = parts.pop();
			var parent = window;
			for(var i=0; i < parts.length && typeof parent[parts[i]] != 'undefined'; ++i)
			{
				parent = parent[parts[i]];
			}
			if (typeof parent[func] == 'function')
			{
				try
				{
					return jQuery.proxy(parent[func],parent);
				}
				catch (e)
				{
					egw.debug('error', 'Function', _val);
					return _err();
				}
			}
		}

		if (!_val || typeof _val == "string")
		{
			return _val;	// get compiled later in widgets own initAttributes, as widget is not yet initialised
		}
	}

	// We should never come here
	throw("Invalid type identifier '" + _attr + "': '" + _type+"'");
}

/**
 * If et2_no_init is set as default value, the initAttributes function will not
 * try to initialize the attribute with the default value.
 */
export const et2_no_init = new Object();

/**
 * Validates the given attribute with the given id. The validation checks for
 * the existance of a human name, a description, a type and a default value.
 * If the human name defaults to the given id, the description defaults to an
 * empty string, the type defaults to any and the default to the corresponding
 * type default.
 */
export function et2_validateAttrib(_id, _attrib)
{
	// Default ignore to false.
	if (typeof _attrib["ignore"] == "undefined")
	{
		_attrib["ignore"] = false;
	}

	// Break if "ignore" is set to true.
	if (_attrib.ignore)
	{
		return;
	}

	if (typeof _attrib["name"] == "undefined")
	{
		_attrib["name"] = _id;
		egw.debug("log", "Human name ('name'-Field) for attribute '" +
			_id + "' has not been supplied, set to '" + _id + "'");
	}

	if (typeof _attrib["description"] == "undefined")
	{
		_attrib["description"] = "";
		egw.debug("log", "Description for attribute '" +
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
			egw.debug("error", "Invalid type '" + _attrib["type"] + "' for attribute '" + _id +
			    "' supplied.  Valid types are ", et2_validTypes);
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
export function et2_arrayValues(_arr)
{
	var result = [];
	for (var key in _arr)
	{
		// @ts-ignore we check key is an integer
		if (parseInt(key) == key)
		{
			result.push(_arr[key]);
		}
	}

	return result;
}

/**
 * Equivalent to the PHP array_keys function
 */
export function et2_arrayKeys(_arr)
{
	var result = [];
	for (var key in _arr)
	{
		result.push(key);
	}

	return result;
}

export function et2_arrayIntKeys(_arr)
{
	var result = [];
	for (var key in _arr)
	{
		result.push(parseInt(key));
	}

	return result;
}


/**
 * Equivalent to the PHP substr function, partly take from phpjs, licensed under
 * the GPL.
 */
export function et2_substr (str, start, len) {
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
export function et2_csvSplit(_str : string, _num? : number, _delimiter? : string, _enclosure? : string)
{
	// Default the parameters
	if (typeof _str == "undefined" || _str == null)
	{
		_str = "";
	}
	if (typeof _num == "undefined")
	{
		_num = null;
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

/**
 * Parses the given string and returns an array marking parts which are URLs
 */
export function et2_activateLinks(_content)
{
	var _match = false;
	var arr = [];

	function _splitPush(_matches, _proc)
	{
		if (_matches)
		{
			// We had a match
			_match = true;

			// Replace "undefined" with ""
			for (var i = 1; i < _matches.length; i++)
			{
				if (typeof _matches[i] == "undefined")
				{
					_matches[i] = "";
				}
			}

			// Split the content string at the given position(s)
			// but we only handle the first occurence
			var splitted = _content.split(_matches[0]);

			// Push the not-matched part
			var left = splitted.shift();
			if (left)
			{
				// activate the links of the left string
				arr = arr.concat(et2_activateLinks(left));
			}

			// Call the callback function which converts the matches into an object
			// and appends it to the string
			_proc(_matches);

			// Set the new working string to the right part
			_content = splitted.join(_matches[0]);
		}
	}

	var mail_regExp = /(mailto:)?([a-z0-9._-]+)@([a-z0-9_-]+)\.([a-z0-9._-]+)/i;

	//  First match things beginning with http:// (or other protocols)
	var protocol = '(http:\\/\\/|(ftp:\\/\\/|https:\\/\\/))';	// only http:// gets removed, other protocolls are shown
	var domain = '([\\w-]+\\.[\\w-.]+)';
	var subdir = '([\\w\\-\\.,@?^=%&;:\\/~\\+#]*[\\w\\-\\@?^=%&\\/~\\+#])?';
	var http_regExp = new RegExp(protocol + domain + subdir, 'i');

	//  Now match things beginning with www.
	var domain = 'www(\\.[\\w-.]+)';
	var subdir = '([\\w\\-\\.,@?^=%&:\\/~\\+#]*[\\w\\-\\@?^=%&\\/~\\+#])?';
	var www_regExp = new RegExp(domain + subdir, 'i');

	do {
		_match = false;

		// Abort if the remaining length of _content is smaller than 20 for
		// performance reasons
		if (!_content)
		{
			break;
		}

		// No need make emailaddress spam-save, as it gets dynamically created
		_splitPush(_content.match(mail_regExp), function(_matches) {
			arr.push({
				"href": (_matches[1]?'':'mailto:')+_matches[0],
				"text": _matches[2] + "@" + _matches[3] + "." + _matches[4]
			});
		});

		// Create hrefs for links starting with "http://"
		_splitPush(_content.match(http_regExp), function(_matches) {
			arr.push({
				"href": _matches[0],
				"text": _matches[2] + _matches[3] + _matches[4]
			});
		});

		// Create hrefs for links starting with "www."
		_splitPush(_content.match(www_regExp), function(_matches) {
			arr.push({
				"href": "http://" + _matches[0],
				"text": _matches[0]
			});
		});
	} while (_match)

	arr.push(_content);

	return arr;
}

/**
 * Inserts the structure generated by et2_activateLinks into the given DOM-Node
 */
export function et2_insertLinkText(_text, _node, _target)
{
	if(!_node)
	{
		egw.debug("warn", "et2_insertLinkText called without node", _text, _node, _target);
		return;
	}

	// Clear the node
	for (var i = _node.childNodes.length - 1; i >= 0; i--)
	{
		_node.removeChild(_node.childNodes[i]);
	}

	for (var i = 0; i < _text.length; i++)
	{
		var s = _text[i];

		if (typeof s == "string" || typeof s == "number")
		{
			// Include line breaks
			var lines = typeof s !== "number" && s.split ? s.split('\n') : [s+""];

			// Insert the lines
			for (var j = 0; j < lines.length; j++)
			{
				_node.appendChild(document.createTextNode(lines[j]));

				if (j < lines.length - 1)
				{
					_node.appendChild(document.createElement("br"));
				}
			}
		}
		else if(s?.text)	// no need to generate a link, if there is no content in it
		{
			if(!s.href)
			{
				egw.debug("warn", "et2_activateLinks gave bad data", s, _node, _target);
				s.href = "";
			}
			var a = jQuery(document.createElement("a"))
				.attr("href", s.href)
				.text(s.text);

			if (typeof _target != "undefined" && _target && _target != "_self" && s.href.substr(0, 7) != "mailto:")
			{
				a.attr("target", _target);
			}
			// open mailto links depending on preferences in mail app
			if (s.href.substr(0, 7) == "mailto:" &&
				(egw.user('apps').mail || egw.user('apps').felamimail) &&
				egw.preference('force_mailto','addressbook') != '1')
			{
				a.click(function(event){
					egw.open_link(this.href);
					return false;
				});
			}

			a.appendTo(_node);
		}
	}
}

/**
 * Creates a copy of the given object (non recursive)
 */
export function et2_cloneObject(_obj)
{
	var result = {};

	for (var key in _obj)
	{
		result[key] = _obj[key];
	}

	return result;
}

/**
 * Returns true if the given array of nodes or their children contains the given
 * child node.
 */
export function et2_hasChild(_nodes, _child)
{
	for (var i = 0; i < _nodes.length; i++)
	{
		if (_nodes[i] == _child)
		{
			return true;
		}
		else if (_nodes[i].childNodes)
		{
			var res = et2_hasChild(_nodes[i].childNodes, _child);

			if (res)
			{
				return true;
			}
		}
	}

	return false;
}

/**
 * Functions to work with ranges and range intersection (used in the dataview)
 */

/**
 * Common functions used in most view classes
 */

/**
 * Returns an "range" object with the given top position and height
 */
export function et2_range(_top, _height)
{
	return {
		"top": _top,
		"bottom": _top + _height
	};
}

/**
 * Returns an "area" object with the given top- and bottom position
 */
export function et2_bounds(_top, _bottom)
{
	return {
		"top": _top,
		"bottom": _bottom
	};
}

/**
 * Returns whether two range objects intersect each other
 */
export function et2_rangeIntersect(_ar1, _ar2)
{
	return ! (_ar1.bottom < _ar2.top || _ar1.top > _ar2.bottom);
}

/**
 * Returns whether two ranges intersect (result = 0) or their relative position
 * to each other (used to do a binary search inside a list of sorted range objects).
 */
export function et2_rangeIntersectDir(_ar1, _ar2)
{
	if (_ar1.bottom < _ar2.top)
	{
		return -1;
	}
	if (_ar1.top > _ar2.bottom)
	{
		return 1;
	}
	return 0;
}

/**
 * Returns whether two ranges are equal.
 */
export function et2_rangeEqual(_ar1, _ar2)
{
	return _ar1.top === _ar2.top && _ar1.bottom === _ar2.bottom;
}

/**
 * Substracts _ar2 from _ar1, returns an array of new ranges.
 */
export function et2_rangeSubstract(_ar1, _ar2)
{
	// Per default return the complete _ar1 range
	var res = [_ar1];

	// Check whether there is an intersection between the given ranges
	if (et2_rangeIntersect(_ar1, _ar2))
	{
		res = [et2_bounds(_ar1.top, _ar2.top),
			   et2_bounds(_ar2.bottom, _ar1.bottom)];
	}

	// Remove all zero-length ranges from the result
	for (var i = res.length - 1; i >= 0; i--)
	{
		if (res[i].bottom - res[i].top <= 0)
		{
			res.splice(i, 1);
		}
	}

	return res;
}

/**
 * Decode html entities so they can be added via .text(_str), eg. html_entity_decode('&amp;') === '&'
 *
 * @param {string} _str
 * @returns {string}
 */
export function html_entity_decode(_str)
{
	return _str && _str.indexOf('&') != -1 ? jQuery('<span>'+_str+'</span>').text() : _str;
}