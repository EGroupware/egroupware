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
		},

		/**
		 * Decode encoded vfs special chars
		 * 
		 * @param string _path path to decode
		 * @return string
		 */
		decodePath: function(_path) {
			return decodeURIComponent(_path);
		},
		
		/**
		 * Encode vfs special chars excluding /
		 * 
		 * @param string _path path to decode
		 * @return string
		 */
		encodePath: function(_path) {
			var components = _path.split('/');
			for(var n=0; n < components.length; n++)
			{
				components[n] = this.encodePathComponent(components[n]);
			}
			return components.join('/');
		},
		
		/**
		 * Encode vfs special chars removing /
		 * 
		 * //'%' => '%25',	// % should be encoded, but easily leads to double encoding, therefore better NOT encodig it
		 * '#' => '%23',
		 * '?' => '%3F',
		 * '/' => '',	// better remove it completly
		 *
		 * @param string _path path to decode
		 * @return string
		 */
		/*
		*/
		encodePathComponent: function(_comp) {
			return _comp.replace(/#/g,'%23').replace(/\?/g,'%3F').replace(/\//g,'');
		},

		/**
		 * If an element has display: none (or a parent like that), it has no size.
		 * Use this to get its dimensions anyway.
		 *
		 * @param element HTML element
		 * @param boolOuter Pass true to get outerWidth() / outerHeight() instead of width() / height()
		 *
		 * @return Object [w: width, h: height]
		 * 
		 * @author Ryan Wheale
		 * @see http://www.foliotek.com/devblog/getting-the-width-of-a-hidden-element-with-jquery-using-width/
		 */
		getHiddenDimensions: function(element, boolOuter) {
			var $item = $j(element);
			var props = { position: "absolute", visibility: "hidden", display: "block" };
			var dim = { "w":0, "h":0 , "left":0, "top":0};
			var $hiddenParents = $item.parents().andSelf().not(":visible");

			var oldProps = [];
			$hiddenParents.each(function() {
				var old = {};
				for ( var name in props ) {
					old[ name ] = this.style[ name ];
				}
				$j(this).show();
				oldProps.push(old);
			});

			dim.w = (boolOuter === true) ? $item.outerWidth() : $item.width();
			dim.h = (boolOuter === true) ? $item.outerHeight() : $item.height();
			dim.top = $item.offset().top;
			dim.left = $item.offset().left;

			$hiddenParents.each(function(i) {
				var old = oldProps[i];
				for ( var name in props ) {
					this.style[ name ] = old[ name ];
				}
			});
			//$.log(”w: ” + dim.w + ”, h:” + dim.h)
			return dim;
		},
			
			
		/**
		 * Store a window's name in egw.store so we can have a list of open windows
		 * 
		 * @param {string} appname
		 * @param {Window} popup
		 */
		storeWindow: function(appname, popup)
		{
			// Don't store if it has no name
			if(!popup.name || ['_blank'].indexOf(popup.name) >= 0)
			{
				return;
			}

			var _target_app = appname || this.appName || egw_appName || 'common';
			var open_windows = JSON.parse(this.getSessionItem(_target_app, 'windows')) || {};
			open_windows[popup.name] = Date.now();
			this.setSessionItem(_target_app, 'windows', JSON.stringify(open_windows));
			
			// We don't want to start the timer on the popup here, because this is the function that updates the timeout, so it would set a timer each time.  Timer is started in egw.js
		},
			
		/**
		 * Get a list of the names of open popups
		 * 
		 * Using the name, you can get a reference to the popup using:
		 * window.open('', name);
		 * Popups that were not given a name when they were opened are not tracked.
		 * 
		 * @param {string} appname Application that owns/opened the popup
		 * @param {string} regex Optionally filter names by the given regular expression
		 * 
		 * @returns {string[]} List of window names
		 */
		getOpenWindows: function(appname, regex) {
			var open_windows = JSON.parse(this.getSessionItem(appname, 'windows')) || {};
			if(typeof regex == 'undefined')
			{
				return open_windows;
			}
			var list = []
			var now = Date.now();
			for(var i in open_windows)
			{
				// Expire old windows (5 seconds since last update)
				if(now - open_windows[i] > 5000)
				{
					egw.windowClosed(appname,i);
					continue;
				}
				if(i.match(regex))
				{
					list.push(i);
				}
			}
			return list;
		},

		/**
		 * Notify egw of closing a named window, which removes it from the list
		 * 
		 * @param {String} appname
		 * @param {Window|String} closed Window that was closed, or its name
		 * @returns {undefined}
		 */
		windowClosed: function(appname, closed) {
			var closed_name = typeof closed == "string" ? closed : closed.name;
			var closed_window = typeof closed == "string" ? null : closed;
			window.setTimeout(function() {
				if(closed_window != null && !closed_window.closed) return;
				
				var open_windows = JSON.parse(egw().getSessionItem(appname, 'windows')) || {};
				delete open_windows[closed_name];
				egw.setSessionItem(appname, 'windows', JSON.stringify(open_windows));
			}, 100);
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
