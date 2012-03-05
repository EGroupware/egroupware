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
	jquery.jquery;

	egw_core;
	egw_utils;
	egw_files;
*/

egw.extend('json', egw.MODULE_WND_LOCAL, function(_egw, _wnd) {

	/**
	 * Object which contains all registered handlers for JS responses.
	 * The handlers are organized per response type in the top level of the
	 * object, where each response type can have an array of handlers attached
	 * to it.
	 */
	var plugins = {};

	/**
	 * Internal implementation of the JSON request object.
	 */
	function json_request(_menuaction, _parameters, _callback, _context, _egw)
	{
		// Initialize undefined parameters
		if (typeof _parameters === 'undefined')
		{
			_parameters = [];
		}

		if (typeof _callback === 'undefined')
		{
			_callback = null;
		}

		if (typeof _context === 'undefined')
		{
			_context = null;
		}

		// Copy the parameters
		this.parameters = _parameters;
		this.egw = _egw;
		this.callback = _callback;
		this.context = _context;

		this.request = null;
		this.sender = null;
		this.callback = null;

		this.onLoadFinish = null;
		this.jsFiles = 0;
		this.jsCount = 0;

		this.alertHandler = this.alertFunc;
	}

	/**
	 * Function which is currently used to display alerts -- may be replaced by
	 * some API function.
	 */
	json_request.prototype.alertFunc = function(_message, _details)
	{
		alert(_message);
		if(_details) _egw_json_debug_log(_message, _details);
	}

	var json = {

		/** The constructor of the egw_json_request class.
		 *
		 * @param _menuaction the menuaction function which should be called and
		 * 	which handles the actual request. If the menuaction is a full featured
		 * 	url, this one will be used instead.
		 * @param _parameters which should be passed to the menuaction function.
		 * @param _callback specifies the callback function which should be
		 * 	called, once the request has been sucessfully executed.
		 * @param _context is the context which will be used for the callback function 
		 */
		request: function(_menuaction, _parameters, _callback, _context)
		{
			return new json_request(_menuaction, _parameters, _callback,
				_context, this);
		}

		/**
		 * Registers a new handler plugin.
		 *
		 * @param _callback is the callback function which should be called
		 * 	whenever a response is comming from the server.
		 * @param _context is the context in which the callback function should
		 * 	be called. If null is given, the plugin is executed in the context
		 * 	of the request object.
		 * @param _type is an optional parameter defaulting to 'global'.
		 * 	it describes the response type which this plugin should be
		 * 	handling.
		 */
		registerJSONPlugin: function(_callback, _context, _type)
		{
			// _type defaults to 'global'
			if (typeof _type === 'undefined')
			{
				_type = 'global';
			}

			// Create an array for the given category inside the plugins object
			if (typeof plugins[_type] === 'undefined')
			{
				plugins[_type] = [];
			}

			// Add the entry
			plugins[_type].push({
				'callback': _callback,
				'context': _context,
			});
		},

		/**
		 * Removes a previously registered plugin.
		 *
		 * @param _callback is the callback function which should be called
		 * 	whenever a response is comming from the server.
		 * @param _context is the context in which the callback function should
		 * 	be called.
		 * @param _type is an optional parameter defaulting to 'global'.
		 * 	it describes the response type which this plugin should be
		 * 	handling.
		 */
		unregisterJSONPlugin: function(_callback, _context, _type)
		{
			// _type defaults to 'global'
			if (typeof _type === 'undefined')
			{
				_type = 'global';
			}

			if (typeof plugins[_type] !== 'undefined') {
				for (var i = 0; i < plugins[_type].length; i++)
				{
					if (plugins[_type][i].callback == _callback &&
						plugins[_type][i].context == _context)
					{
						plugins[_type].slice(i, 1);
						break;
					}
				}
			}
		}
	};

	// Regisert the "alert" plugin
	json.registerPlugin(function(type, res) {
		//Check whether all needed parameters have been passed and call the alertHandler function
		if ((typeof res.data.message != 'undefined') && 
			(typeof res.data.details != 'undefined'))
		{					
			this.alertHandler(
				res.data.message,
				res.data.details)
			return true;
		}
		throw 'Invalid parameters';
	}, null, 'alert');

	// Register the "assign" plugin
	json.registerPlugin(function(type, res) {
		//Check whether all needed parameters have been passed and call the alertHandler function
		if ((typeof res.data.id != 'undefined') && 
			(typeof res.data.key != 'undefined') &&
			(typeof res.data.value != 'undefined'))
		{					
			var obj = document.getElementById(res.data.id);
			if (obj)
			{
				obj[res.data.key] = res.data.value;

				if (res.data.key == "innerHTML")
				{
					egw_insertJS(res.data.value);
				}

				return true;
			}

			return false;
		}
		throw 'Invalid parameters';
	}, null, 'assign');

	// Register the "data" plugin
	json.registerPlugin(function(type, res) {
		//Callback the caller in order to allow him to handle the data
		if (this.callback)
		{
			this.callback.call(this.sender, res.data);
			return true;
		}
	}, null, 'data');

	// Register the "script" plugin
	json.registerPlugin(function(type, res) {
		if (typeof res.data == 'string')
		{
			try
			{
				var func = new Function(res.data);
				func.call(window);
			}
			catch (e)
			{
				this.egw.debug('error', 'Error while executing script: ',
					res.data)
			}
			return true;
		}
		throw 'Invalid parameters';
	}, null, 'script');

	// Register the "apply" plugin
	json.registerPlugin(function(type, res) {
		if (typeof res.data.func == 'string' &&
		    typeof window[res.data.func] == 'function')
		{
			try
			{
				window[res.data.func].apply(window, res.data.parms);
			}
			catch (e)
			{
				this.egw.debug('error', 'Function', res.data.func,
					'Parameters', res.data.parms);
			}
			return true;
		}
		throw 'Invalid parameters';
	}, null, 'apply');

	// Register the "jquery" plugin
	json.registerPlugin(function(type, res) {
		if (typeof res.data.select == 'string' &&
			typeof res.data.func == 'string')
		{
			try
			{
				var jQueryObject = $j(res.data.select, this.context);
				jQueryObject[res.data.func].apply(jQueryObject,	res.data.parms);
			}
			catch (e)
			{
				this.egw.debug('error', 'Function', res.data.func,
					'Parameters', res.data.parms);
			}
			return true;
		}
		throw 'Invalid parameters';
	}, null, 'jquery');

	// Register the "redirect" plugin
	json.registerPlugin(function(type, res) {
		//console.log(res.data.url);
		if (typeof res.data.url == 'string' &&
			typeof res.data.global == 'boolean')
		{
			//Special handling for framework reload
			res.data.global |= (res.data.url.indexOf("?cd=10") > 0);

			if (res.data.global)
			{
				egw_topWindow().location.href = res.data.url;
			}
			else
			{
				egw_appWindowOpen(this.app, res.data.url);
			}
			return true;
		}
		throw 'Invalid parameters';
	}, null, 'redirect');

	// Register the 'css' plugin
	json.registerPlugin(function(type, res) {
		if (typeof res.data == 'string')
		{
			this.egw.includeCSS(res.data);
			return true;
		}
		throw 'Invalid parameters';
	}, null, 'css');

	// Register the 'js' plugin
	json.registerPlugin(function(type, res) {
		if (typeof res.data == 'string')
		{
			this.jsCount++;
			var self = this;

			this.egw.includeJS(res.data, function() {
				self.jsFiles++;
				if (self.jsFiles == self.jsCount && this.onLoadFinish)
				{
					this.onLoadFinish.call(this.sender);
				}
			});
		}
		throw 'Invalid parameters';
	}, null, 'js');

	// Return the extension
	return json;
});

