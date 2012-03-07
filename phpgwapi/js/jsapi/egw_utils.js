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

/*egw:uses
	egw_core;
*/

egw.extend('utils', egw.MODULE_GLOBAL, function() {

	function json_escape_string(input)
	{
		var len = input.length;
		var res = "";

		for (var i = 0; i < len; i++)
		{
			switch (input.charAt(i))
			{
				case '"':
					res += '\\"';
					break;

				case '\n':
					res += '\\n';
					break;

				case '\r':
					res += '\\r';
					break;

				case '\\':
					res += '\\\\';
					break;

				case '\/':
					res += '\\/';
					break;

				case '\b':
					res += '\\b';
					break;

				case '\f':
					res += '\\f';
					break;

				case '\t':
					res += '\\t';
					break;

				default:
					res += input.charAt(i);
			}
		}

		return res;
	}

	function json_encode_simple(input)
	{
		switch (input.constructor)
		{
			case String:
				return '"' + json_escape_string(input) + '"';

			case Number:
				return input.toString();

			case Boolean:
				return input ? 'true' : 'false';

			default:
				return null;
		}
	}

	function json_encode(input)
	{
		if (input == null || !input && input.length == 0) return 'null';

		var simple_res = json_encode_simple(input);
		if (simple_res == null)
		{
			switch (input.constructor)
			{
				case Array:
					var buf = [];
					for (var k in input)
					{
						//Filter non numeric entries
						if (!isNaN(k))
							buf.push(json_encode(input[k]));
					}
					return '[' + buf.join(',') + ']';

				case Object:
					var buf = [];
					for (var k in input)
					{
						buf.push(json_encode_simple(k) + ':' + json_encode(input[k]));
					}
					return '{' + buf.join(',') + '}';

				default:
					switch(typeof input)
					{
						case 'array':
							var buf = [];
							for (var k in input)
							{
								//Filter non numeric entries
								if (!isNaN(k))
									buf.push(json_encode(input[k]));
							}
							return '[' + buf.join(',') + ']';

						case 'object':
							var buf = [];
							for (var k in input)
							{
								buf.push(json_encode_simple(k) + ':' + json_encode(input[k]));
							}
							return '{' + buf.join(',') + '}';

					}
					return 'null';
			}
		}
		else
		{
			return simple_res;
		}
	}

	var uid_counter = 0;

	// Create the utils object which contains references to all functions
	// covered by it.
	var utils = {

		ajaxUrl: function(_menuaction) {
			return this.webserverUrl + '/json.php?menuaction=' + _menuaction;
		},

		elemWindow: function(_elem) {
			var res =
				_elem.ownerDocument.parentNode ||
				_elem.ownerDocument.defaultView;
			return res;
		},

		uid: function() {
			return (uid_counter++).toString(16);
		}

	};

	// Check whether the browser already supports encoding JSON -- if yes, use
	// its implementation, otherwise our own
	if (typeof window.JSON !== 'undefined' && typeof window.JSON.stringify !== 'undefined')
	{
		utils["jsonEncode"] = JSON.stringify;
	}
	else
	{
		utils["jsonEncode"] = json_encode;
	}

	// Return the extension
	return utils;

});

