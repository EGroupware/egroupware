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

/*egw:uses
	egw_core;
	egw_ready;
	egw_debug;
*/

/**
 * @augments Class
 * @param {string} _app application name object is instanciated for
 * @param {object} _wnd window object is instanciated for
 */
egw.extend('files', egw.MODULE_WND_LOCAL, function(_app, _wnd)
{
	"use strict";

	var egw = this;

	/**
	 * Remove optional timestamp attached directly as first query parameter, eg. /path/name.js?12345678[&other=val]
	 *
	 * Examples:
	 *  /path/file.js --> /path/file.js
	 *  /path/file.js?123456 --> /path/file.js
	 *  /path/file.php?123456&param=value --> /path/file.php?param=value
	 *
	 * @param _src url
	 * @return url with timestamp stripped off
	 */
	function removeTS(_src)
	{
		return _src.replace(/\?[0-9]+&?/, '?').replace(/\?$/, '');
	}

	/**
	 * RegExp to extract string with comma-separated files from a bundle-url
	 *
	 * @type RegExp
	 */
	var bundle2files_regexp = /phpgwapi\/inc\/min\/\?b=[^&]+&f=([^&]+)/;

	/**
	 * Regexp to detect and remove .min.js extension
	 *
	 * @type RegExp
	 */
	var min_js_regexp = /\.min\.js$/;

	/**
	 * Return array of files-sources from bundle(s) incl. bundle-src itself
	 *
	 * @param {string|Array} _srcs all url's have to be egw releativ!
	 * @returns {Array}
	 */
	function files_from_bundles(_srcs)
	{
		var files = [];

		if (typeof _srcs == 'string') _srcs = [_srcs];

		for(var n=0; n < _srcs.length; ++n)
		{
			var file = _srcs[n];
			files.push(file.replace(min_js_regexp, '.js'));
			var contains = file.match(bundle2files_regexp);

			if (contains && contains.length > 1)
			{
				var bundle = contains[1].split(',');
				for(var i; i < bundle.length; ++i)
				{
					files.push(bundle[i].replace(min_js_regexp, '.js'));
				}
			}
		}
		return files;
	}

	/**
	 * Strip of egw_url from given urls (if containing it)
	 *
	 * @param {array} _urls absolute urls
	 * @returns {array} relativ urls
	 */
	function strip_egw_url(_urls)
	{
		var egw_url = egw.webserverUrl;
		if (egw_url.charAt(egw_url.length-1) != '/') egw_url += '/';

		for(var i=0; i < _urls.length; ++i)
		{
			var file = _urls[i];
			// check if egw_url is only path and urls contains full url incl. protocol
			// --> prefix it with our protocol and host, as eg. splitting by just '/' will fail!
			var need_full_url = egw_url[0] == '/' && file.substr(0,4) == 'http' ? window.location.protocol+'//'+window.location.host : '';
			var parts = file.split(need_full_url+egw_url);
			if (parts.length > 1)
			{
				// discard protocol and host
				parts.shift();
				_urls[i] = parts.join(need_full_url+egw_url);
			}
		}
		return _urls;
	}

	/**
	 * Array which contains all currently bound in javascript and css files.
	 */
	var files = [];
	// add already included scripts
	var tags = jQuery('script', _wnd.document);
	for(var i=0; i < tags.length; ++i)
	{
		files.push(removeTS(tags[i].src));
	}
	// add already included css
	tags = jQuery('link[type="text/css"]', _wnd.document);
	for(var i=0; i < tags.length; ++i)
	{
		files.push(removeTS(tags[i].href));
	}
	// make urls egw-relative
	files = strip_egw_url(files);
	// resolve bundles and replace .min.js with .js
	files = files_from_bundles(files);

	return {
		/**
		 * Load and execute javascript file(s) in order
		 *
		 * @memberOf egw
		 * @param {string|array} _jsFiles (array of) urls to include
		 * @param {function} _callback called after JS files are loaded and executed
		 * @param {object} _context
		 * @param {string} _prefix prefix for _jsFiles
		 */
		includeJS: function(_jsFiles, _callback, _context, _prefix)
		{
			// Also allow including a single javascript file
			if (typeof _jsFiles === 'string')
			{
				_jsFiles = [_jsFiles];
			}
			// LABjs uses prefix only if url is not absolute, so removing leading / if necessary and add it to prefix
			if (_prefix)
			{
				for(var i=0; i < _jsFiles.length; ++i)
				{
					if (_jsFiles[i].charAt(0) == '/') _jsFiles[i] = _jsFiles[i].substr(1);
				}
			}
			// as all our included checks use egw relative url strip off egw-url and use it as prefix
			else
			{
				_jsFiles = strip_egw_url(_jsFiles);
				_prefix = egw.webserverUrl;
			}
			if (_prefix.charAt(_prefix.length-1) != '/')
			{
				_prefix += '/';
			}

			// search and NOT load files already included as part of a bundle
			for(var i=0; i < _jsFiles.length; ++i)
			{
				var file = _jsFiles[i];
				if (this.included(file, true))	// check if included and marks as such if not
				{
					_jsFiles.splice(i, 1);
					i--;	// as index will be incr by for!
				}
			}
			// check if all files already included or sheduled to be included --> call callback via egw_LAB.wait
			if (!_jsFiles.length)
			{
				egw_LAB.wait(function(){
					_callback.call(_context);
				});
				return;
			}

			// setting AlwaysPreserverOrder: true, 'til we have some other means of ensuring dependency resolution
			(egw_LAB || $LAB.setOptions({AlwaysPreserveOrder:true,BasePath:_prefix})).script(_jsFiles).wait(function(){
				_callback.call(_context);
			});
		},

		/**
		 * Check if file is already included and optional mark it as included if not yet included
		 *
		 * Check does NOT differenciate between file.min.js and file.js.
		 * Only .js get's recored in files for further checking, if _add_if_not set.
		 *
		 * @param {string} _file
		 * @param {boolean} _add_if_not if true mark file as included
		 * @return boolean true if file already included, false if not
		 */
		included: function(_file, _add_if_not)
		{
			var file = removeTS(_file).replace(min_js_regexp, '.js');
			var not_inc = files.indexOf(file) == -1;

			if (not_inc && _add_if_not)
			{
				files = files.concat(files_from_bundles(file));
			}
			return !not_inc;
		},

		/**
		 * Include a CSS file
		 *
		 * @param {string|array} _cssFiles full url of file to include
		 */
		includeCSS: function(_cssFiles)
		{
			if (typeof _cssFiles == 'string') _cssFiles = [_cssFiles];
			_cssFiles = strip_egw_url(_cssFiles);

			for(var n=0; n < _cssFiles.length; ++n)
			{
				var file = _cssFiles[n];
				if (!this.included(file, true))	// check if included and marks as such if not
				{
					// Create the node which is used to include the css file
					var cssnode = _wnd.document.createElement('link');
					cssnode.type = "text/css";
					cssnode.rel = "stylesheet";
					cssnode.href = egw.webserverUrl+'/'+file;

					// Get the head node and append the newly created "link" nod to it
					var head = _wnd.document.getElementsByTagName('head')[0];
					head.appendChild(cssnode);
				}
			}
		}
	};
});


