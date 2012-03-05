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
	jquery.jquery; // Used for traversing the DOM
	egw_core;
	egw_debug;
*/

egw.extend('files', egw.MODULE_GLOBAL, function() {

	/**
	 * Array which contains all currently bound in javascript and css files.
	 */
	var files = {};

	/**
	 * Gather all already loaded JavaScript and CSS files on document load.
	 * 
	 * TODO: Currently this can only contain the JS files present in the main
	 * window.
	 */
	$j(document).ready(function() {
		$j("script, link").each(function() {
			var elem = $j(this);

			if (elem.attr("src"))
			{
				files[elem.attr("src")] = true;
			}

			if (elem.attr("href"))
			{
				files[elem.attr("href")] = true;
			}

		});
	});

	function includeJSFile(_jsFile, _callback, _context)
	{
		var alreadyLoaded = false;

		if (typeof files[_jsFile] === 'undefined')
		{
			// Create the script node which contains the new file
			var scriptnode = document.createElement('script');
			scriptnode.type = "text/javascript";
			scriptnode.src = _jsFile;
			scriptnode._originalSrc = _jsFile;

			// Setup the 'onload' handler for FF, Opera, Chrome
			scriptnode.onload = function(e) {
				this.debug('info', 'Retrieved JS file "%s" from server', _jsFile);
				_callback.call(_context, _jsFile);
			};

			// IE
			if (typeof scriptnode.readyState != 'undefined')
			{
				if (scriptnode.readyState != 'complete' &&
					scriptnode.readyState != 'loaded')
				{
					scriptnode.onreadystatechange = function() {
						var node = window.event.srcElement;
						if (node.readyState == 'complete' || node.readyState == 'loaded')
						{
							this.debug('info', 'Retrieved JS file "%s" from server', _jsFile);
							_callback.call(_context, _jsFile);
						}
					};
				}
				else
				{
					alreadyLoaded = true;
				}
			}

			// Append the newly create script node to the head
			var head = document.getElementsByTagName('head')[0];
			head.appendChild(scriptnode);

			// Request the given javascript file
			this.debug('info', 'Requested JS file "%s" from server', _jsFile);
		}

		// If the file is already loaded, call the callback
		if (alreadyLoaded)
		{
			window.setTimeout(
				function() {
					_callback.call(_context, _jsFile);
				}, 0);
		}
	}

	return {
		includeJS: function(_jsFiles, _callback, _context) {
			// Also allow including a single javascript file
			if (typeof _jsFiles === 'string')
			{
				_jsFiles = [_jsFiles];
			}

			var loaded = 0;

			// Include all given JS files, if all are successfully loaded, call
			// the context function
			for (var i = 0; i < _jsFiles.length; i++)
			{
				includeJSFile.call(this, _jsFiles[i], function(_file) {
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
				var cssnode = document.createElement('link');
				cssnode.type = "text/css";
				cssnode.rel = "stylesheet";
				cssnode.href = _cssFile;

				// Get the head node and append the newly created "link" node
				// to it.
				var head = document.getElementsByTagName('head')[0];
				head.appendChild(cssnode);
			}
		}
	}

});


