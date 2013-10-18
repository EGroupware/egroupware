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
	egw_debug;
*/

egw.extend('json', egw.MODULE_WND_LOCAL, function(_app, _wnd) {

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
	function json_request(_menuaction, _parameters, _callback, _context,
		_async, _sender, _egw)
	{
		// Copy the parameters
		this.url = _egw.ajaxUrl(_menuaction);
		this.parameters = _parameters ? _parameters : [];
		this.async = _async ? _async : false;
		this.callback = _callback ? _callback : null;
		this.context = _context ? _context : null;
		this.sender = _sender ? _sender : null;
		this.egw = _egw;

		// We currently don't have a request object
		this.request = null;

		// Some variables needed for notification about a JS files done loading
		this.onLoadFinish = null;
		this.jsFiles = 0;
		this.jsCount = 0;

		// Function which is currently used to display alerts -- may be replaced by
		// some API function.
		this.alertHandler = function(_message, _details) {
			alert(_message);

			if (_details)
			{
				_egw.debug('info', _message, _details);
			}
		};
	}

	/**
	 * Sends the assembled request to the server
	 * @param {boolean} [async=false] Overrides async provided in constructor to give an easy way to make simple async requests
	 * @returns undefined
	 */
	json_request.prototype.sendRequest = function(async) {
		if(typeof async != "undefined")
		{
			this.async = async;
		}
		
		// Assemble the complete request
		var request_obj = {
			'json_data': this.egw.jsonEncode({
				'request': {
					'parameters': this.parameters
				}
			})
		};

		// Send the request via AJAX using the jquery ajax function
		// we need to use jQuery of window of egw object, as otherwise the one from main window is used!
		// (causing eg. apply from server with app.$app.method to run in main window instead of popup) 
		this.egw.window.$j.ajax({
			url: this.url,
			async: this.async,
			context: this,
			data: request_obj,
			dataType: 'json',
			type: 'POST',
			success: this.handleResponse,
			error: function(_xmlhttp, _err) {
				this.egw.debug('error', 'Ajax request to', this.url, ' failed:',
					_err);
			}
		});
	}

	json_request.prototype.handleResponse = function(data) {
		if (data && data.response)
		{
			// Load files first
			var js_files = [];
			for (var i = data.response.length - 1; i > 0; --i)
			{
				var res = data.response[i];
				if(res.type == 'js' && typeof res.data == 'string')
				{
					js_files.unshift(res.data);
					data.response.splice(i,1);
				}
			}
			if(js_files.length > 0)
			{
				this.egw.includeJS(js_files, function() {this.handleResponse(data);}, this);
				return;
			}
			for (var i = 0; i < data.response.length; i++)
			{
				// Get the response object
				var res = data.response[i];

				// Check whether a plugin for the given type exists
				if (typeof plugins[res.type] !== 'undefined')
				{
					for (var j = 0; j < plugins[res.type].length; j++) {
						try {
							// Get a reference to the plugin
							var plugin = plugins[res.type][j];

							// Call the plugin callback
							plugin.callback.call(
								plugin.context ? plugin.context : this.context,
								res.type, res, this
							);

						} catch(e) {
							var msg = e.message ? e.message : e + '';
							var stack = e.stack ? "\n-- Stack trace --\n" + e.stack : ""
							this.egw.debug('error', 'Exception "' + msg + '" while handling JSON response type "' + res.type + '", plugin', plugin, 'response', res, stack);
						}
					}
				}
			}
		}
	}

	var json = {

		/** The constructor of the egw_json_request class.
		 *
		 * @param _menuaction the menuaction function which should be called and
		 * 	which handles the actual request. If the menuaction is a full featured
		 * 	url, this one will be used instead.
		 * @param _parameters which should be passed to the menuaction function.
		 * @param _async specifies whether the request should be asynchronous or
		 * 	not.
		 * @param _callback specifies the callback function which should be
		 * 	called, once the request has been sucessfully executed.
		 * @param _context is the context which will be used for the callback function 
		 * @param _sender is a parameter being passed to the _callback function
		 */
		json: function(_menuaction, _parameters, _callback, _context, _async,
			_sender)
		{
			return new json_request(_menuaction, _parameters, _callback, 
				_context, _async, _sender, this);
		},

		/**
		 * Registers a new handler plugin.
		 *
		 * @param _callback is the callback function which should be called
		 * 	whenever a response is comming from the server.
		 * @param _context is the context in which the callback function should
		 * 	be called. If null is given, the plugin is executed in the context
		 * 	of the request object context.
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
	json.registerJSONPlugin(function(type, res, req) {
		//Check whether all needed parameters have been passed and call the alertHandler function
		if ((typeof res.data.message != 'undefined') && 
			(typeof res.data.details != 'undefined'))
		{
			req.alertHandler(
				res.data.message,
				res.data.details)
			return true;
		}
		throw 'Invalid parameters';
	}, null, 'alert');

	// Register the "assign" plugin
	json.registerJSONPlugin(function(type, res, req) {
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
	json.registerJSONPlugin(function(type, res, req) {
		//Callback the caller in order to allow him to handle the data
		if (req.callback)
		{
			req.callback.call(req.sender, res.data);
			return true;
		}
	}, null, 'data');

	// Register the "script" plugin
	json.registerJSONPlugin(function(type, res, req) {
		if (typeof res.data == 'string')
		{
			try
			{
				var func = new Function(res.data);
				func.call(req.egw ? req.egw.window : window);
			}
			catch (e)
			{
				req.egw.debug('error', 'Error while executing script: ',
					res.data,e)
			}
			return true;
		}
		throw 'Invalid parameters';
	}, null, 'script');

	// Register the "apply" plugin
	json.registerJSONPlugin(function(type, res, req) {
		if (typeof res.data.func == 'string')
		{
			var parts = res.data.func.split('.');
			var func = parts.pop();
			var parent = req.egw.window;
			for(var i=0; i < parts.length && typeof parent[parts[i]] != 'undefined'; ++i)
			{
				parent = parent[parts[i]];
			}
			if (typeof parent[func] == 'function')
			{
				try
				{
					parent[func].apply(parent, res.data.parms);
				}
				catch (e)
				{
					req.egw.debug('error', e.message, ' in function', res.data.func,
						'Parameters', res.data.parms);
				}
				return true;
			}
			else
			{
				throw '"' + res.data.func + '" is not a callable function (type is ' + typeof parent[func] + ')';
			}
		}
		throw 'Invalid parameters';
	}, null, 'apply');

	// Register the "jquery" plugin
	json.registerJSONPlugin(function(type, res, req) {
		if (typeof res.data.select == 'string' &&
			typeof res.data.func == 'string')
		{
			try
			{
				var jQueryObject = $j(res.data.select, req.context);
				jQueryObject[res.data.func].apply(jQueryObject,	res.data.parms);
			}
			catch (e)
			{
				req.egw.debug('error', 'Function', res.data.func,
					'Parameters', res.data.parms);
			}
			return true;
		}
		throw 'Invalid parameters';
	}, null, 'jquery');

	// Register the "redirect" plugin
	json.registerJSONPlugin(function(type, res, req) {
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
				egw_appWindowOpen(req.egw.getAppName(), res.data.url);
			}
			return true;
		}
		throw 'Invalid parameters';
	}, null, 'redirect');

	// Register the 'css' plugin
	json.registerJSONPlugin(function(type, res, req) {
		if (typeof res.data == 'string')
		{
			req.egw.includeCSS(res.data);
			return true;
		}
		throw 'Invalid parameters';
	}, null, 'css');

	// Register the 'js' plugin
	json.registerJSONPlugin(function(type, res, req) {
		if (typeof res.data == 'string')
		{
			req.jsCount++;
			req.egw.includeJS(res.data, function() {
				req.jsFiles++;
				if (req.jsFiles == req.jsCount && req.onLoadFinish)
				{
					req.onLoadFinish.call(req.sender);
				}
			});
			return true;
		}
		throw 'Invalid parameters';
	}, null, 'js');

	// Register the 'html' plugin, replacing document content with send html
	json.registerJSONPlugin(function(type, res, req) {
		if (typeof res.data == 'string')
		{
			// Empty the document tree
			while (_wnd.document.childNodes.length > 0)
			{
				_wnd.document.removeChild(_wnd.document.childNodes[0]);
			}

			// Write the given content
			_wnd.document.write(res.data);

			// Close the document
			_wnd.document.close();
			return true;
		}
		throw 'Invalid parameters';
	}, null, 'html');

	// Return the extension
	return json;
});

