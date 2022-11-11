/**
 * EGroupware clientside API object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Andreas Stöckel (as AT stylite.de)
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 */

/*egw:uses
	vendor.bower-asset.jquery.dist.jquery;

	egw_core;
	egw_utils;
	egw_files;
	egw_debug;
*/
import './egw.js';
import './egw_utils.js';

/**
 * Module sending json requests
 *
 * @param {string} _app application name object is instantiated for
 * @param {object} _wnd window object is instantiated for
 */
egw.extend('json', egw.MODULE_WND_LOCAL, function(_app, _wnd)
{
	"use strict";

	/**
	 * Object which contains all registered handlers for JS responses.
	 * The handlers are organized per response type in the top level of the
	 * object, where each response type can have an array of handlers attached
	 * to it.
	 */
	var plugins = {};

	/**
	 * Global json handlers are from global modules, not window level
	 */
	if(typeof egw._global_json_handlers == 'undefined')
	{
		egw._global_json_handlers = {};
	}
	var global_plugins = egw._global_json_handlers;

	/**
	 * Internal implementation of the JSON request object.
	 *
	 * @param {string} _menuaction
	 * @param {array} _parameters
	 * @param {function} _callback
	 * @param {object} _context
	 * @param {boolean|"keepalive"} _async true: asynchronious request, false: synchronious request,
	 * 	"keepalive": async. request with keepalive===true / sendBeacon, to be used in boforeunload event
	 * @param {object} _sender
	 * @param {egw} _egw
	 */
	const json_request = function(_menuaction, _parameters, _callback, _context,
		_async, _sender, _egw)
	{
		// Copy the parameters
		this.url = _egw.ajaxUrl(_menuaction);
		// IE JSON-serializes arrays passed in from different window contextx (eg. popups)
		// as objects (it looses object-type of array), causing them to be JSON serialized
		// as objects and loosing parameters which are undefined
		// JSON.strigify([123,undefined]) --> '{"0":123}' instead of '[123,null]'
		this.parameters = _parameters ? [].concat(_parameters) : [];
		this.async = typeof _async != 'undefined' ? _async : true;
		this.callback = _callback ? _callback : null;
		this.context = _context ? _context : null;
		this.sender = _sender ? _sender : null;
		this.egw = _egw;

		// Some variables needed for notification about a JS files done loading
		this.onLoadFinish = null;
		this.jsFiles = 0;
		this.jsCount = 0;

		// Function which is currently used to display alerts -- may be replaced by
		// some API function.
		this.alertHandler = function(_message, _details) {
			// we need to use the alert function of the window of the request, not just the main window
			(this.egw ? this.egw.window : window).alert(_message);

			if (_details)
			{
				_egw.debug('info', _message, _details);
			}
		};
	}

	const min_reconnect_time = 1000;
	const max_reconnect_time = 300000;
	const check_interval = 30000;	// 30 sec
	const max_ping_response_time = 1000;
	let reconnect_time = min_reconnect_time;
	let websocket = null;

	/**
	 * Open websocket to push server (and keeps it open)
	 *
	 * @param {string} url this.websocket(s)://host:port
	 * @param {array} tokens tokens to subscribe too: sesssion-, user- and instance-token (in that order!)
	 * @param {number} account_id to connect for
	 * @param {function} error option error callback(_msg) used instead our default this.error
	 * @param {int} reconnect timeout in ms (internal)
	 */
	json_request.prototype.openWebSocket = function(url, tokens, account_id, error, reconnect)
	{
		reconnect_time = reconnect || min_reconnect_time;
		let check_timer;
		let check = function()
		{
			this.websocket.send('ping');
			check_timer = window.setTimeout(function()
			{
				console.log("Server did not respond to ping in "+max_ping_response_time+" seconds --> try reconnecting");
				check_timer = null;
				this.websocket.onclose = function()
				{
					this.websocket = null;
					this.openWebSocket(url, tokens, account_id, error, reconnect_time);
				}.bind(this);
				this.websocket.close();	// closing it now, before reopening it, to not end up with multiple connections
			}.bind(this), max_ping_response_time);
		}.bind(this);

		websocket = this.websocket = new WebSocket(url);
		this.websocket.onopen = (e) =>
		{
			check_timer = window.setTimeout(check, check_interval);
			this.websocket.send(JSON.stringify({
				subscribe: tokens,
				account_id: parseInt(account_id)
			}));
		};

		this.websocket.onmessage = (event) =>
		{
			reconnect_time = min_reconnect_time;
			console.log(event);
			if (check_timer) window.clearTimeout(check_timer);
			check_timer = window.setTimeout(check, check_interval);
			if (event.data === 'pong') return;	// just a keepalive message
			let data = JSON.parse(event.data);
			if (data && data.type)
			{
				this.handleResponse({ response: [data]});
			}
		};

		this.websocket.onerror = (error) =>
		{
			reconnect_time *= 2;
			if (reconnect_time > max_reconnect_time) reconnect_time = max_reconnect_time;

			console.log(error);
			(error||this.handleError({}, error));
		};

		this.websocket.onclose = (event) =>
		{
			if (event.wasClean)
			{
				reconnect_time = min_reconnect_time;
				console.log(`[close] Connection closed cleanly, code=${event.code} reason=${event.reason}`);
			}
			else
			{
				reconnect_time *= 2;
				if (reconnect_time > max_reconnect_time) reconnect_time = max_reconnect_time;

				// e.g. server process killed or network down
				// event.code is usually 1006 in this case
				console.log('[close] Connection died --> reconnect in '+reconnect_time+'ms');
				if (check_timer) window.clearTimeout(check_timer);
				check_timer = null;
				window.setTimeout(() => this.openWebSocket(url, tokens, account_id, error, reconnect_time), reconnect_time);
			}
		};
	},

	/**
	 * Sends the assembled request to the server
	 * @param {boolean|"keepalive"} _async Overrides async provided in constructor: true: asynchronious request,
	 * 	false: synchronious request, "keepalive": async. request with keepalive===true / sendBeacon, to be used in beforeunload event
	 * @param {string} method ='POST' allow to eg. use a (cachable) 'GET' request instead of POST
	 * @param {function} error option error callback(_xmlhttp, _err) used instead our default this.error
	 *
	 * @return {Promise|boolean} Promise or for async==="keepalive" boolean is returned
	 * Promise.abort() allows to abort the pending request
	 */
	json_request.prototype.sendRequest = function(async, method, error)
	{
		if(typeof async != "undefined")
		{
			this.async = async;
		}

		if (typeof method === 'undefined') method = 'POST';

		// Assemble the complete request
		const request_obj = JSON.stringify({
			request: {
				parameters: this.parameters
			}
		});

		// send with keepalive===true for sendBeacon to be used in beforeunload event
		if (this.async === "keepalive" && typeof navigator.sendBeacon !== "undefined")
		{
			const data = new FormData();
			data.append('json_data', request_obj);
			//(window.opener||window).console.log("navigator.sendBeacon", this.url, request_obj, data.getAll('json_data'));
			return navigator.sendBeacon(this.url, data);
		}

		let url = this.url;
		let init = {
			method: method
		}
		if (method === 'GET')
		{
			url += (url.indexOf('?') === -1 ? '?' : '&') + new URLSearchParams({ json_data: request_obj });
		}
		else
		{
			init.headers = { 'Content-Type': 'application/json'};
			init.body = request_obj;
		}
		let promise;
		if (this.async)
		{
			const controller = new AbortController();
			const signal = controller.signal;
			let response_ok = false;
			promise = (this.egw.window?this.egw.window:window).fetch(url, {...init, ...signal})
				.then((response) => {
					response_ok = response.ok;
					if (!response.ok) {
						throw response;
					}
					return response.json();
				})
				.then((data) => this.handleResponse(data) || data)
				.catch((_err) => {
					// no response / empty body causing response.json() to throw (a different error per browser!)
					if (response_ok && !_err.message.match(/Unexpected end of/i))
					{
						(error || this.handleError).call(this, _err)
					}
				});

			// offering a simple abort mechanism and compatibility with jQuery.ajax
			promise.abort = () => controller.abort();
		}
		else
		{
			console.trace("Synchronous AJAX request detected", this);
			const request = new XMLHttpRequest();
			request.open(method, url, false);
			if (method !== 'GET') request.setRequestHeader('Content-Type', 'application/json');
			request.send(init.body);
			if (request.status >= 200 && request.status < 300)
			{
				const json = JSON.parse(request.responseText);
				promise = Promise.resolve(this.handleResponse(json) || json);
			}
			else
			{
				(error || this.handleError).call(this, request, 'error')
			}
		}
		// compatibility with jQuery.ajax
		if (promise && typeof promise.then === 'function') promise.done = promise.then;

		return promise;
	};

	/**
	 * Default error callback displaying error via egw.message
	 *
	 * @param {XMLHTTP|Response} response
	 * @param {string} _err
	 */
	json_request.prototype.handleError = function(response, _err) {
		// Don't error about an abort
		if(_err !== 'abort')
		{
			// for fetch Response get json, as it's used below
			if (typeof response.headers === 'object' && response.headers.get('Content-Type') === 'application/json')
			{
				return response.json().then((json) => {
					response.responseJSON = json;
					this.handleError(response, 'error');
				})
			}
			const date = typeof response.headers === 'object' ? 'Date: '+response.headers.get('Date') :
				(typeof response.getAllResponseHeaders === 'function' ? response.getAllResponseHeaders().match(/^Date:.*$/mi)[0] : null) ||
				'Date: '+(new Date).toString();
			this.egw.message.call(this.egw,
				this.egw.lang('A request to the EGroupware server returned with an error')+
				': '+response.statusText+' ('+response.status+")\n\n"+
				this.egw.lang('Please reload the EGroupware desktop (F5 / Cmd+r).')+"\n"+
				this.egw.lang('If the error persists, contact your administrator for help and ask to check the error-log of the webserver.')+
				"\n\nURL: "+this.url+"\n"+date+
				// if EGroupware send JSON payload with error, errno show it here too
				(_err === 'error' && response.status === 400 && typeof response.responseJSON === 'object' && response.responseJSON.error ?
				"\nError: "+response.responseJSON.error+' ('+response.responseJSON.errno+')' : '')
			);

			this.egw.debug('error', 'Ajax request to', this.url, ' failed: ', _err, response.status, response.statusText, response.responseJSON);

			// check of unparsable JSON on server-side, which might be caused by some network problem --> resend max. twice
			if (_err === 'error' && response.status === 400 && typeof response.responseJSON === 'object' &&
				response.responseJSON.errno && response.responseJSON.error.substr(0, 5) === 'JSON ')
			{
				// ToDo: resend request max. twice
			}
		}
	};

	json_request.prototype.handleResponse = function(data) {
		if (data && typeof data.response != 'undefined')
		{
			if (egw.preference('show_generation_time', 'common', false) == "1")
			{
				var gen_time_div = jQuery('#divGenTime').length > 0 ? jQuery('#divGenTime')
				:jQuery('<div id="divGenTime" class="pageGenTime"><span class="pageTime"></span></div>').appendTo('#egw_fw_footer');
			}
			// Load files first
			var js_files = [];
			for (var i = data.response.length - 1; i >= 0; --i)
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
				var start_time = (new Date).getTime();
				// for some reason using this.includeJS() does NOT work / app.classes does not get set, before the Promise resolves
				Promise.all(js_files.map((file) => import(file))).then(() => {
					var end_time = (new Date).getTime();
					this.handleResponse(data);
					if (egw.preference('show_generation_time', 'common', false) == "1")
					{
						var gen_time_div = jQuery('#divGenTime');
						if (!gen_time_div.length) gen_time_div = jQuery('.pageGenTime');
						var gen_time_async = jQuery('.asyncIncludeTime').length > 0 ? jQuery('.asyncIncludeTime'):
							gen_time_div.append('<span class="asyncIncludeTime"></span>').find('.asyncIncludeTime');
						gen_time_async.text(egw.lang('async includes took %1s', (end_time-start_time)/1000));
					}
				});
				return;
			}

			// defer apply's for app.* after et2_load is finished
			let apply_app = [];
			if (data.response.filter((res) => res.type === 'et2_load').length)
			{
				apply_app = data.response.filter((res) => res.type === 'apply' && res.data.func.substr(0, 4) === 'app.');
				if (apply_app.length)
				{
					data.response = data.response.filter((res) => !(res.type === 'apply' && res.data.func.substr(0, 4) === 'app.'));
				}
			}

			// Flag for only data response - don't call callback if only data
			var only_data = (data.response.length > 0);

			for (var i = 0; i < data.response.length; i++)
			{
				// Get the response object
				var res = data.response[i];
				if(typeof res.type == 'string' && res.type != 'data') only_data = false;

				// Check whether a plugin for the given type exists
				var handlers = [plugins, global_plugins];
				for(var handler_idx = 0; handler_idx < handlers.length; handler_idx++)
				{
					var handler_level = handlers[handler_idx];
					if (typeof handler_level[res.type] !== 'undefined')
					{
						for (var j = 0; j < handler_level[res.type].length; j++) {
							try {
								// Get a reference to the plugin
								var plugin = handler_level[res.type][j];
								if (res.type === 'et2_load')
								{
									if (egw.preference('show_generation_time', 'common', false) == "1")
									{
										if (gen_time_div.length > 0)
										{
											gen_time_div.find('span.pageTime').text(egw.lang("Page was generated in %1 seconds ", data.page_generation_time));
											if (data.session_restore_time)
											{
												var gen_time_session_span = gen_time_div.find('span.session').length > 0 ? gen_time_div.find('span.session'):
														gen_time_div.append('<span class="session"></span>').find('.session');
												gen_time_session_span.text(egw.lang("session restore time in %1 seconds ", data.page_generation_time));
											}
										}
									}
								}
								// Call the plugin callback
								const promise = plugin.callback.call(
									plugin.context ? plugin.context : this.context,
									res.type, res, this
								);
								// defer apply_app's after et2_load is finished (it returns a promise for that)
								if (res.type === 'et2_load' && apply_app.length && typeof promise.then === 'function')
								{
									promise.then(() => this.handleResponse({response: apply_app}));
								}
							} catch(e) {
								var msg = e.message ? e.message : e + '';
								var stack = e.stack ? "\n-- Stack trace --\n" + e.stack : "";
								this.egw.debug('error', 'Exception "' + msg + '" while handling JSON response from ' +
									this.url + ' [' + JSON.stringify(this.parameters) + '] type "' + res.type +
									'", plugin', plugin, 'response', res, stack);
							}
						}
					}
				}
			}
			// Call request callback, if provided
			if(typeof this.callback === 'function' && !only_data)
			{
				this.callback.call(this.context,res);
			}
		}
	};

	var json =
	{
		/**
		 * Check if there is a *working* connection to a push server
		 *
		 * @return {boolean}
		 */
		pushAvailable: function()
		{
			return websocket !== null && websocket.readyState == websocket.OPEN && reconnect_time === min_reconnect_time;
		},

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
		 * Do an AJAX call and get a javascript promise, which will be resolved with the returned data.
		 *
		 * egw.request() returns immediately with a Promise.  The promise will be resolved with just the returned data,
		 * any other "piggybacked" responses will be handled by registered handlers.  The data will also be passed to
		 * any registered data handlers (egw.data) before it is passed to your handler.
		 *
		 * To use:
		 * @example
		 * 	egw.request(
		 * 		"EGroupware\\Api\\Etemplate\\Widget\\Select::ajax_get_options",
		 * 		["select-cat"]
	 	 * 	)
		 * 	.then(function(data) {
		 * 		// Deal with the returned data here.  data may be undefined if no data was returned.
		 * 		console.log("Here's the categories:",data);
		 * 	});
		 *
		 *
		 * 	The return is a Promise, so multiple .then() can be chained in the usual ways:
		 * 	@example
		 * 	egw.request(...)
		 * 		.then(function(data) {
		 * 		  if(debug) console.log("Requested data", data);
		 * 		}
		 * 		.then(function(data) {
		 * 			// Change the data for the rest of the chain
		 * 		    if(typeof data === "undefined") return [];
		 * 		}
		 * 		.then(function(data) {
		 * 			// data is never undefined now, if it was before it's an empty array now
		 * 		 	for(let i = 0; i < data.length; i++)
		 * 			{
		 * 		 		...
		 * 			}
		 * 		}
		 *
		 *
		 * 	You can also fire off multiple requests, and wait for them to all be answered:
		 * 	@example
		 * 	let first = egw.request(...);
		 * 	let second = egw.request(...);
		 * 	Promise.all([first, second])
		 * 		.then(function(values) {
		 * 		 	console.log("First:", values[0], "Second:", values[1]);
		 * 		}
		 *
		 *
		 * @param {string} _menuaction
		 * @param {any[]} _parameters
		 *
		 * @return Promise resolving to data part (not full response, which can contain other parts)
		 * Promise.abort() allows to abort the pending request
		 */
		request: function(_menuaction, _parameters)
		{
			const request = new json_request(_menuaction, _parameters, null, this, true, this, this);
			const response = request.sendRequest();
			let promise = response.then(function(response)
			{
				// The ajax request has completed, get just the data & pass it on
				if(response && response.response)
				{
					for(let value of response.response)
					{
						if(value.type && value.type === "data" && typeof value.data !== "undefined")
						{
							// Data was packed in response
							return value.data;
						}
						else if (value && typeof value.type === "undefined" && typeof value.data === "undefined")
						{
							// Just raw data
							return value;
						}
					}
					return undefined;
				}
				return response;
			});
			// pass abort method to returned response
			if (typeof response.abort === 'function')
			{
				promise.abort = response.abort;
			}
			return promise;
		},

		/**
		 * Call a function specified by it's name (possibly dot separated, eg. "app.myapp.myfunc")
		 *
		 * @param {string|Function} _func dot-separated function name or function
		 * @param {mixed} ...args variable number of arguments
		 * @returns {mixed|Promise}
		 */
		callFunc: function(_func)
		{
			return this.applyFunc(_func, [].slice.call(arguments, 1));
		},

		/**
		 * Call a function specified by it's name (possibly dot separated, eg. "app.myapp.myfunc")
		 *
		 * @param {string|Function} _func dot-separated function name or function
		 * @param {array} args arguments
		 * @param {object} _context
		 * @returns {mixed|Promise}
		 */
		applyFunc: function(_func, args, _context)
		{
			let parent = _context || _wnd;
			let func = _func;

			if (typeof _func === 'string')
			{
				let parts = _func.split('.');
				func = parts.pop();
				for(var i=0; i < parts.length; ++i)
				{
					if (typeof parent[parts[i]] !== 'undefined')
					{
						parent = parent[parts[i]];
					}
					// check if we need a not yet included app.js object --> include it now and return a Promise
					else if (i == 1 && parts[0] == 'app' && typeof app.classes[parts[1]] === 'undefined')
					{
						return import(this.webserverUrl+'/'+parts[1]+'/js/app.min.js?'+((new Date).valueOf()/86400|0).toString())
							.then(() => this.applyFunc(_func, args, _context),
								(err) => {console.error("Failure loading /"+parts[1]+'/js/app.min.js' + " (" + err + ")\nAborting.")});
					}
					// check if we need a not yet instantiated app.js object --> instantiate it now
					else if (i == 1 && parts[0] == 'app' && typeof app.classes[parts[1]] === 'function')
					{
						parent = parent[parts[1]] = new app.classes[parts[1]](parts[1], _wnd);
					}
				}
				if (typeof parent[func] == 'function')
				{
					func = parent[func];
				}
			}
			if (typeof func != 'function')
			{
				throw _func+" is not a function!";
			}
			return func.apply(parent, args);
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
		 * @param {boolean} [_global=false] Register the handler globally or
		 *	locally.  Global handlers must stay around, so should be used
		 *	for global modules.
		 */
		registerJSONPlugin: function(_callback, _context, _type, _global)
		{
			// _type defaults to 'global'
			if (typeof _type === 'undefined')
			{
				_type = 'global';
			}
			// _global defaults to false
			if (typeof _global === 'undefined')
			{
				_global = false;
			}
			var scoped = _global ? global_plugins : plugins;

			// Create an array for the given category inside the plugins object
			if (typeof scoped[_type] === 'undefined')
			{
				scoped[_type] = [];
			}

			// Add the entry
			scoped[_type].push({
				'callback': _callback,
				'context': _context
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
		 * @param {boolean} [_global=false] Remove a global or local handler.
		 */
		unregisterJSONPlugin: function(_callback, _context, _type, _global)
		{
			// _type defaults to 'global'
			if (typeof _type === 'undefined')
			{
				_type = 'global';
			}
			// _global defaults to false
			if (typeof _global === 'undefined')
			{
				_global = false;
			}
			var scoped = _global ? global_plugins : plugins;
			if (typeof scoped[_type] !== 'undefined') {
				for (var i = 0; i < scoped[_type].length; i++)
				{
					if (scoped[_type][i].callback == _callback &&
						scoped[_type][i].context == _context)
					{
						scoped[_type].slice(i, 1);
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
				res.data.details);
			return true;
		}
		throw 'Invalid parameters';
	}, null, 'alert');

	// Regisert the "message" plugin
	json.registerJSONPlugin(function(type, res, req) {
		//Check whether all needed parameters have been passed and call the alertHandler function
		if ((typeof res.data.message != 'undefined'))
		{
			req.egw.message(res.data.message, res.data.type);
			return true;
		}
		throw 'Invalid parameters';
	}, null, 'message');

	// Register the "assign" plugin
	json.registerJSONPlugin(function(type, res, req) {
		//Check whether all needed parameters have been passed and call the alertHandler function
		if ((typeof res.data.id != 'undefined') &&
			(typeof res.data.key != 'undefined') &&
			(typeof res.data.value != 'undefined'))
		{
			var obj = _wnd.document.getElementById(res.data.id);
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
					res.data,e);
			}
			return true;
		}
		throw 'Invalid parameters';
	}, null, 'script');

	// Register the "apply" plugin
	json.registerJSONPlugin(function(type, res, req) {
		if (typeof res.data.func == 'string')
		{
			req.egw.applyFunc(res.data.func, res.data.parms, req.egw.window);
			return true;
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
				var jQueryObject = jQuery(res.data.select, req.context);
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
	}, _wnd, 'jquery');

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
			// json request was originating from a different popup --> redirect that one
			else if(this && this.DOMContainer && this.DOMContainer.ownerDocument.defaultView != window &&
				egw(this.DOMContainer.ownerDocument.defaultView).is_popup())
			{
				this.DOMContainer.ownerDocument.location.href = res.data.url;
			}
			// main window, open url in respective tab
			else
			{
				egw_appWindowOpen(res.data.app, res.data.url);
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
			return Promise.all(res.data.map((src) => import(src)))
				.then(() => req.onLoadFinish.call(req.sender));
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