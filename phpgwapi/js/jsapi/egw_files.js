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

egw.extend('files', egw.MODULE_WND_LOCAL, function(_app, _wnd) {

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

	/**
	 * Add file to list of loaded files
	 * 
	 * @param src url of file
	 * @param dom optional dom node to trac loading status of javascript files
	 */
	function addFile(src, dom)
	{
		if (src)
		{
			files[removeTS(src)] = dom || true;
		}
	}
	
	/**
	 * Check if a source file is already loaded or loading
	 * 
	 * @param _src url of file (already run throught removeTS!)
	 * @return false: not loaded, true: loaded or "loading"
	 */
	function isLoaded(_src)
	{
		switch (typeof files[_src])
		{
			case 'undefined':
				return false;
			case 'boolean':
				return files[_src];
			default:
				return files[_src].readyState == 'complete' || files[_src].readyState == 'loaded';
		}
		return "loading";
	}
	
	/**
	 * object with array of callbacks or contexts by source/url
	 */
	var callbacks = {};
	var contexts = {};
	
	/**
	 * Attach onLoad callback to given js source
	 * 
	 * @param _src url of file (already run throught removeTS!)
	 * @param _callback
	 * @param _context
	 * @return true if callback got attached, false if it was run directly
	 */
	function attachCallback(_src, _callback, _context)
	{
		if (typeof _callback === 'undefined') return;

		switch (typeof files[_src])
		{
			case 'undefined':
			case 'boolean':
				_callback.call(_context);
				return false;
		}
		
		if (typeof callbacks[_src] === 'undefined')
		{
			callbacks[_src] = []; 
			contexts[_src] = [];
			callbacks[_src].push(_callback);
			contexts[_src].push(_context);
			
			var scriptnode = files[_src];
			
			// Setup the 'onload' handler for FF, Opera, Chrome
			scriptnode.onload = function(e) {
				egw.debug('info', 'Retrieved JS file "%s" from server', _src);
				runCallbacks.call(this, _src);
			};

			// IE
			if (typeof scriptnode.readyState != 'undefined')
			{
				if (scriptnode.readyState != 'complete' &&
					scriptnode.readyState != 'loaded')
				{
					scriptnode.onreadystatechange = function() {
						var node = _wnd.event.srcElement;
						if (node.readyState == 'complete' || node.readyState == 'loaded')
						{
							egw.debug('info', 'Retrieved JS file "%s" from server', _src);
							runCallbacks.call(this, _src);
						}
					};
				}
				else
				{
					runCallbacks.call(this, _src);
					return false;
				}
			}
		}
		else
		{
			callbacks[_src].push(_callback);
			contexts[_src].push(_context);
		}
		return true;
	}
	
	/**
	 * Run all callbacks of a given source
	 * 
	 * @param _src url of file (already run throught removeTS!)
	 */
	function runCallbacks(_src)
	{
		if (typeof callbacks[_src] === 'undefined') return;
		
		egw.debug('info', 'Running %d callbacks for JS file "%s"', callbacks[_src].length, _src);

		for(var i = 0; i < callbacks[_src].length; i++)
		{
			callbacks[_src][i].call(contexts[_src][i]);
		}
		delete callbacks[_src];
		delete contexts[_src];
	}

	/**
	 * Gather all already loaded JavaScript and CSS files on document load.
	 */
	// Iterate over the script tags
	var scripts = _wnd.document.getElementsByTagName('script');
	for (var i = 0; i < scripts.length; i++)
	{
		addFile(scripts[i].getAttribute('src'), scripts[i]);
	}

	// Iterate over the link tags
	var links = _wnd.document.getElementsByTagName('link');
	for (var i = 0; i < links.length; i++)
	{
		addFile(links[i].getAttribute('href'));
	}

	/**
	 * Include a single javascript file and call given callback once it's done
	 * 
	 * If file is already loaded, _callback gets called imediatly
	 * 
	 * @param _jsFile url of file
	 * @param _callback
	 * @param _context for callback
	 */
	function includeJSFile(_jsFile, _callback, _context)
	{
		var _src = removeTS(_jsFile);
		var alreadyLoaded = isLoaded(_src);

		if (alreadyLoaded === false)
		{
			// Create the script node which contains the new file
			var scriptnode = _wnd.document.createElement('script');
			scriptnode.type = "text/javascript";
			scriptnode.src = _jsFile;
			scriptnode._originalSrc = _jsFile;
			
			files[_src] = scriptnode;

			// Append the newly create script node to the head
			var head = _wnd.document.getElementsByTagName('head')[0];
			head.appendChild(scriptnode);

			// Request the given javascript file
			egw.debug('info', 'Requested JS file "%s" from server', _jsFile);
		}
		else if (alreadyLoaded === true)
		{
			egw.debug('info', 'JS file "%s" already loaded', _jsFile);
		}
		else
		{
			egw.debug('info', 'JS file "%s" currently loading', _jsFile);
		}

		// attach (or just run) callback
		attachCallback(_src, _callback, _context);
	}

	return {
		includeJS: function(_jsFiles, _callback, _context, _prefix) {
			// Also allow including a single javascript file
			if (typeof _jsFiles === 'string')
			{
				_jsFiles = [_jsFiles];
			}
			if (typeof _prefix === 'undefined')
			{
				_prefix = '';
			}

			var loaded = 0;

			// Include all given JS files, if all are successfully loaded, call
			// the context function
			for (var i = 0; i < _jsFiles.length; i++)
			{
				includeJSFile.call(this, _prefix+_jsFiles[i], function(_file) {
					loaded++;
					if (loaded == _jsFiles.length && _callback) {
						_callback.call(_context);
					}
				});
			}
		},

		includeCSS: function(_cssFile) {
			//Check whether the requested file has already been included
			if (typeof files[_cssFile] === 'undefined')
			{
				files[_cssFile] = true;

				// Create the node which is used to include the css fiel
				var cssnode = _wnd.document.createElement('link');
				cssnode.type = "text/css";
				cssnode.rel = "stylesheet";
				cssnode.href = _cssFile;

				// Get the head node and append the newly created "link" node
				// to it.
				var head = _wnd.document.getElementsByTagName('head')[0];
				head.appendChild(cssnode);
			}
		}
	}

});


