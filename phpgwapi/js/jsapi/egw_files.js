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
	egw_ready;
	egw_debug;
*/

/**
 * @augments Class
 */
egw.extend('files', egw.MODULE_WND_LOCAL, function(_app, _wnd) 
{
	var egw = this;

	/**
	 * Array which contains all currently bound in javascript and css files.
	 */
	var files = {};
	
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

	return {
		/**
		 * Load and execute javascript file(s) in order
		 * 
		 * @memberOf egw
		 * @param string|array _jsFiles (array of) urls to include
		 * @param function _callback called after JS files are loaded and executed
		 * @param object _context
		 * @param string _prefix prefix for _jsFiles
		 */
		includeJS: function(_jsFiles, _callback, _context, _prefix) {
			if (typeof _prefix === 'undefined')
			{
				_prefix = '';
			}
			// LABjs uses prefix only if url is not absolute, so removing leading / if necessary and add it to prefix
			if (_prefix)
			{
				// Also allow including a single javascript file
				if (typeof _jsFiles === 'string')
				{
					_jsFiles = [_jsFiles];
				}
				for(var i=0; i < _jsFiles.length; ++i)
				{
					if (_jsFiles[i].charAt(0) == '/') _jsFiles[i] = _jsFiles[i].substr(1);
				}
				if (_prefix.charAt(_prefix.length-1) != '/')
				{
					_prefix += '/';
				}
			}
			// setting AlwaysPreserverOrder: true, 'til we have some other means of ensuring dependency resolution
			(egw_LAB || $LAB.setOptions({AlwaysPreserveOrder:true,BasePath:_prefix})).script(_jsFiles).wait(function(){
				_callback.call(_context);
			});
		},

		/**
		 * Include a CSS file
		 * 
		 * @param _cssFile full url of file to include
		 */
		includeCSS: function(_cssFile) {
			//Check whether the requested file has already been included
			var file = removeTS(_cssFile);
			if (typeof files[file] === 'undefined')
			{
				files[file] = true;

				// Create the node which is used to include the css fiel
				var cssnode = _wnd.document.createElement('link');
				cssnode.type = "text/css";
				cssnode.rel = "stylesheet";
				cssnode.href = _cssFile;

				// Get the head node and append the newly created "link" node
				// to it.
				var head = _wnd.document.getElementsByTagName('head')[0];
				if(jQuery('link[href="'+_cssFile+'"]',head).length == 0)
				{
					head.appendChild(cssnode);
				}
			}
		}
	}

});


