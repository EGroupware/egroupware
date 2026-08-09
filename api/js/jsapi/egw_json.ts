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

import './egw.js';
import './egw_utils';

export interface JsonModule
{
	/**
	 * Check if there is a *working* connection to a push server
	 */
	pushAvailable() : boolean;

	/** The constructor of the egw_json_request class.
	 *
	 * @param _menuaction the menuaction function which should be called and
	 * 	which handles the actual request. If the menuaction is a full featured
	 * 	url, this one will be used instead.
	 * @param _parameters which should be passed to the menuaction function.
	 * @param _callback specifies the callback function which should be
	 * 	called, once the request has been sucessfully executed.
	 * @param _context is the context which will be used for the callback function
	 * @param _async true: asynchronious request, false: synchronious request,
	 * 	"keepalive": async. request with keepalive===true / sendBeacon, to be used in beforeunload event
	 * @param _sender is a parameter being passed to the _callback function
	 */
	json(_menuaction : string, _parameters? : any[] | object, _callback? : Function, _context? : object, _async? : boolean|"keepalive", _sender?) : JsonRequest;

	/**
	 * Do an AJAX call and get a javascript promise, which will be resolved with the returned data.
	 *
	 * egw.request() returns immediately with a Promise.  The promise will be resolved with just the returned data,
	 * any other "piggybacked" responses will be handled by registered handlers.  The data will also be passed to
	 * any registered data handlers (egw.data) before it is passed to your handler.
	 *
	 * @param _menuaction
	 * @param _parameters
	 *
	 * @return Promise resolving to data part (not full response, which can contain other parts).
	 * Promise.abort() allows to abort the pending request
	 */
	request(_menuaction : string, _parameters : any[] | object) : Promise<any>;

	/**
	 * Call a function specified by it's name (possibly dot separated, eg. "app.myapp.myfunc")
	 *
	 * @param _func dot-separated function name or function
	 * @param args variable number of arguments
	 */
	callFunc(_func : string|Function, ...args : any) : Promise<any>|any;

	/**
	 * Call a function specified by it's name (possibly dot separated, eg. "app.myapp.myfunc")
	 *
	 * @param _func dot-separated function name or function
	 * @param args arguments
	 * @param _context
	 */
	applyFunc(_func : string|Function, args : any[], _context? : object) : Promise<any>|any;

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
	 * @param _global Register the handler globally or
	 *	locally.  Global handlers must stay around, so should be used
	 *	for global modules.
	 */
	registerJSONPlugin(_callback : Function, _context, _type?, _global?) : void;

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
	 * @param _global Remove a global or local handler.
	 */
	unregisterJSONPlugin(_callback : Function, _context, _type? : string, _global? : boolean) : void;

	/**
	 * Removes all plugins registered on this (window-local) instance
	 */
	unregisterAllPlugins() : void;
}

declare global
{
	interface IegwWndLocal extends JsonModule
	{
	}
}

const MIN_RECONNECT_TIME = 1000;
const MAX_RECONNECT_TIME = 300000;
const CHECK_INTERVAL = 30000;	// 30 sec
const MAX_PING_RESPONSE_TIME = 1000;

/**
 * A single JSON request/response object, as returned by Json.json()/.request().
 *
 * Never registered via egw.extend(), so its `this` is always just "the
 * request instance" - normal class semantics, no dynamic-dispatch/self-
 * capture concerns like the Json module class below. It still needs to
 * reach into the OWNING Json instance's shared state though (the plugin
 * registries and the one push-websocket connection are per-window, not
 * per-request) - #json plus Json's small set of internal accessors below
 * is that bridge, since #private fields can't be reached across classes
 * even with a reference.
 */
class JsonRequest
{
	url : string;
	parameters : any[];
	async : boolean | "keepalive";
	callback : Function;
	context : any;
	sender : any;
	egw : any;
	onLoadFinish : any = null;
	jsFiles = 0;
	jsCount = 0;
	websocket : any = null;

	#json : Json;

	constructor(_menuaction : string, _parameters : any[] | object, _callback : Function, _context : any,
		_async : boolean|"keepalive", _sender : any, _egw : any, _json : Json)
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
		this.#json = _json;
	}

	/**
	 * Function which is currently used to display alerts -- may be replaced by
	 * some API function.
	 */
	alertHandler(_message : string, _details? : any)
	{
		// we need to use the alert function of the window of the request, not just the main window
		(this.egw ? this.egw.window : window).alert(_message);

		if (_details)
		{
			this.egw.debug('info', _message, _details);
		}
	}

	/**
	 * Open websocket to push server (and keeps it open)
	 *
	 * @param url this.websocket(s)://host:port
	 * @param tokens tokens to subscribe too: sesssion-, user- and instance-token (in that order!)
	 * @param account_id to connect for
	 * @param error option error callback(_msg) used instead our default this.error
	 * @param reconnect timeout in ms (internal)
	 */
	openWebSocket(url : string, tokens : string[], account_id : number, error? : Function, reconnect? : number) : void
	{
		this.#json.reconnectTime = reconnect || MIN_RECONNECT_TIME;
		let check_timer : any;
		const check = () =>
		{
			this.websocket.send('ping');
			check_timer = window.setTimeout(() =>
			{
				console.log("Server did not respond to ping in "+MAX_PING_RESPONSE_TIME+" seconds --> try reconnecting");
				check_timer = null;
				this.websocket.onclose = () =>
				{
					this.websocket = null;
					this.openWebSocket(url, tokens, account_id, error, this.#json.reconnectTime);
				};
				this.websocket.close();	// closing it now, before reopening it, to not end up with multiple connections
			}, MAX_PING_RESPONSE_TIME);
		};

		this.websocket = this.#json.websocket = new WebSocket(url);
		this.websocket.onopen = (e) =>
		{
			check_timer = window.setTimeout(check, CHECK_INTERVAL);
			this.websocket.send(JSON.stringify({
				subscribe: tokens,
				account_id: parseInt(<any>account_id)
			}));
		};

		this.websocket.onmessage = (event) =>
		{
			this.#json.reconnectTime = MIN_RECONNECT_TIME;
			console.log(event);
			if (check_timer) window.clearTimeout(check_timer);
			check_timer = window.setTimeout(check, CHECK_INTERVAL);
			if (event.data === 'pong') return;	// just a keepalive message
			let data = JSON.parse(event.data);
			if (data && data.type)
			{
				this.handleResponse({ response: [data]});
			}
		};

		this.websocket.onerror = (error) =>
		{
			this.#json.reconnectTime = Math.min(this.#json.reconnectTime * 2, MAX_RECONNECT_TIME);

			console.log(error);
			(error||this.handleError({}, error));
		};

		this.websocket.onclose = (event) =>
		{
			if (event.wasClean)
			{
				this.#json.reconnectTime = MIN_RECONNECT_TIME;
				console.log(`[close] Connection closed cleanly, code=${event.code} reason=${event.reason}`);
			}
			else
			{
				this.#json.reconnectTime = Math.min(this.#json.reconnectTime * 2, MAX_RECONNECT_TIME);

				// e.g. server process killed or network down
				// event.code is usually 1006 in this case
				console.log('[close] Connection died --> reconnect in '+this.#json.reconnectTime+'ms');
				if (check_timer) window.clearTimeout(check_timer);
				check_timer = null;
				window.setTimeout(() => this.openWebSocket(url, tokens, account_id, error, this.#json.reconnectTime), this.#json.reconnectTime);
			}
		};
	}

	/**
	 * Sends the assembled request to the server
	 * @param _async Overrides async provided in constructor: true: asynchronious request,
	 * 	false: synchronious request, "keepalive": async. request with keepalive===true / sendBeacon, to be used in beforeunload event
	 * @param method ='POST' allow to eg. use a (cachable) 'GET' request instead of POST
	 * @param error option error callback(_xmlhttp, _err) used instead our default this.error
	 *
	 * @return Promise or for async==="keepalive" boolean is returned
	 * Promise.abort() allows to abort the pending request
	 */
	sendRequest(async? : boolean|"keepalive", method? : "POST"|"GET", error? : Function) : any
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
		let init : any = {
			method: method
		}
		if (url.includes("api.queue") || url.includes("Rocketchat"))
		{
			// Low priority for the queued requests
			init.priority = "low";
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
		let promise : any;
		if (this.async)
		{
			const controller = new AbortController();
			const signal = controller.signal;
			let response_ok = false;
			promise = this.#json.wnd.fetch(url, {...init, signal})
				.then((response) => {
					response_ok = response.ok;
					if (!response.ok) {
						throw response;
					}
					return response.json();
				})
				.then((data) => this.handleResponse(data) || data)
				.catch((_err) => {
					if (!response_ok)
					{
						// request was aborted via promise.abort(), or browser cancelled it (eg. navigation): ignore
						if (_err && _err.name === 'AbortError')
						{
							return;
						}
						// HTTP-level error (eg. 400 from a thrown InvalidArgumentException): _err is the Response object
						// or a network-level failure (eg. TypeError "Failed to fetch"): _err is the Error object
						(error || this.handleError).call(this, _err, 'error');
					}
					// no response / empty body causing response.json() to throw (a different error per browser!)
					else if (!_err.message.match(/Unexpected end of/i))
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
	}

	/**
	 * Default error callback displaying error via egw.message
	 */
	handleError(response : any, _err? : string) : any
	{
		// Don't error about an abort
		if(_err !== 'abort')
		{
			// for fetch Response get json, as it's used below (only once, body can only be read once!)
			if (typeof response.headers === 'object' && response.headers.get('Content-Type') === 'application/json' &&
				typeof response.responseJSON === 'undefined')
			{
				return response.json().then((json) => {
					response.responseJSON = json;
					this.handleError(response, 'error');
				})
			}
			const date = typeof response.headers === 'object' ? 'Date: '+response.headers.get('Date') :
				(typeof response.getAllResponseHeaders === 'function' ? response.getAllResponseHeaders().match(/^Date:.*$/mi)[0] : null) ||
				'Date: '+(new Date).toString();
			// response is not a real HTTP response (eg. a network-level fetch failure): fall back to its error message
			const status = typeof response.status !== 'undefined' ? response.statusText+' ('+response.status+')' :
				(response.message || this.egw.lang('network error'));
			this.egw.message.call(this.egw,
				this.egw.lang('A request to the EGroupware server returned with an error')+
				': '+status+"\n\n"+
				this.egw.lang('Please reload the EGroupware desktop (F5 / Cmd+r).')+"\n"+
				this.egw.lang('If the error persists, contact your administrator for help and ask to check the error-log of the webserver.')+
				"\n\nURL: "+this.url+"\n"+date+
				// if EGroupware send JSON payload with error, errno show it here too
				(_err === 'error' && response.status === 400 && typeof response.responseJSON === 'object' && response.responseJSON.error ?
				"\nError: "+response.responseJSON.error+' ('+response.responseJSON.errno+')' : ''),
				'error'
			);

			this.egw.debug('error', 'Ajax request to', this.url, ' failed: ', _err, response.status, response.statusText, response.responseJSON);

			// check of unparsable JSON on server-side, which might be caused by some network problem --> resend max. twice
			if (_err === 'error' && response.status === 400 && typeof response.responseJSON === 'object' &&
				response.responseJSON.errno && response.responseJSON.error.substr(0, 5) === 'JSON ')
			{
				// ToDo: resend request max. twice
			}
		}
	}

	handleResponse(data : any) : any
	{
		if (data && typeof data.response != 'undefined')
		{
			/* disabled for now
			if (egw.preference('show_generation_time', 'common', false) == "1")
			{
				var gen_time_div = jQuery('#divGenTime').length > 0 ? jQuery('#divGenTime')
				:jQuery('<div id="divGenTime" class="pageGenTime"><span class="pageTime"></span></div>').appendTo('#egw_fw_footer');
			}*/
			// Load files first
			var js_files : string[] = [];
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
				// Need to use this.egw.window.egw_import() to make sure file is loaded in correct window
				Promise.all(js_files.map((file) => this.egw.window.egw_import(file))).then(() =>
				{
					var end_time = (new Date).getTime();
					this.handleResponse(data);
					/* disabled for now
					if (egw.preference('show_generation_time', 'common', false) == "1")
					{
						var gen_time_div = jQuery('#divGenTime');
						if (!gen_time_div.length) gen_time_div = jQuery('.pageGenTime');
						var gen_time_async = jQuery('.asyncIncludeTime').length > 0 ? jQuery('.asyncIncludeTime'):
							gen_time_div.append('<span class="asyncIncludeTime"></span>').find('.asyncIncludeTime');
						gen_time_async.text(egw.lang('async includes took %1s', (end_time-start_time)/1000));
					}*/
				});
				return;
			}

			// defer apply's for app.* after et2_load is finished
			let apply_app : any[] = [];
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
				var handlers = [this.#json.plugins, this.#json.globalPlugins];
				for(var handler_idx = 0; handler_idx < handlers.length; handler_idx++)
				{
					var handler_level = handlers[handler_idx];
					if (typeof handler_level[res.type] !== 'undefined')
					{
						const handlerCount = handler_level[res.type].length;
						for (let j = handlerCount - 1; j >= 0; j--)
						{
							try {
								// Get a reference to the plugin
								var plugin = handler_level[res.type][j];
								/* disabled for now
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
								}*/
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
	}
}

/**
 * Module sending json requests
 */
class Json implements JsonModule
{
	#wnd : Window;

	/**
	 * Registered handlers for JS responses, organized per response type,
	 * each response type having an array of handlers attached to it.
	 */
	#plugins : {[type : string] : {callback : Function, context : any}[]} = {};

	/**
	 * Global json handlers are from global modules, not window level
	 */
	#globalPlugins : {[type : string] : {callback : Function, context : any}[]};

	/**
	 * The one push-server websocket connection for this window, and its
	 * reconnect back-off - shared by every JsonRequest.openWebSocket() call,
	 * since there's only ever one connection per window. Exposed to
	 * JsonRequest via the accessors below - #private fields aren't reachable
	 * across classes, even with a reference to the owning instance.
	 */
	#websocket : any = null;
	#reconnectTime = MIN_RECONNECT_TIME;

	get wnd() : Window { return this.#wnd; }
	get plugins() { return this.#plugins; }
	get globalPlugins() { return this.#globalPlugins; }
	get websocket() { return this.#websocket; }
	set websocket(ws : any) { this.#websocket = ws; }
	get reconnectTime() { return this.#reconnectTime; }
	set reconnectTime(t : number) { this.#reconnectTime = t; }

	constructor(_wnd : Window)
	{
		this.#wnd = _wnd;

		if(typeof (<any>egw)._global_json_handlers == 'undefined')
		{
			(<any>egw)._global_json_handlers = {};
		}
		this.#globalPlugins = (<any>egw)._global_json_handlers;

		// Regisert the "alert" plugin
		this.registerJSONPlugin(function(type, res, req) {
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
		this.registerJSONPlugin(function(type, res, req) {
			//Check whether all needed parameters have been passed and call the alertHandler function
			if ((typeof res.data.message != 'undefined'))
			{
				req.egw.message(res.data.message, res.data.type);
				return true;
			}
			throw 'Invalid parameters';
		}, null, 'message');

		// Register the "assign" plugin
		this.registerJSONPlugin((type, res, req) => {
			//Check whether all needed parameters have been passed and call the alertHandler function
			if ((typeof res.data.id != 'undefined') &&
				(typeof res.data.key != 'undefined') &&
				(typeof res.data.value != 'undefined'))
			{
				var obj : any = this.#wnd.document.getElementById(res.data.id);
				if (obj)
				{
					obj[res.data.key] = res.data.value;

					if (res.data.key == "innerHTML")
					{
						(<any>window).egw_insertJS(res.data.value);
					}

					return true;
				}

				return false;
			}
			throw 'Invalid parameters';
		}, null, 'assign');

		// Register the "data" plugin
		this.registerJSONPlugin(function(type, res, req) {
			//Callback the caller in order to allow him to handle the data
			if (req.callback)
			{
				req.callback.call(req.sender, res.data);
				return true;
			}
		}, null, 'data');

		// Register the "script" plugin
		this.registerJSONPlugin(function(type, res, req) {
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
		this.registerJSONPlugin(function(type, res, req) {
			if (typeof res.data.func == 'string')
			{
				req.egw.applyFunc(res.data.func, res.data.parms, req.egw.window);
				return true;
			}
			throw 'Invalid parameters';
		}, null, 'apply');

		// Register the "jquery" plugin - a generic "call any jQuery method by
		// name with server-supplied args" bridge for legacy server-side response
		// actions. There's no native equivalent to "dispatch an arbitrary method
		// by string name" that doesn't just reinvent jQuery, and unlike other
		// jQuery call sites removed in this modernization pass this one isn't
		// incidental DOM manipulation - it's a protocol feature that may still
		// be emitted by old PHP code. Deferred, like egw_debug.ts's dialog and
		// egw_utils.ts's getHiddenDimensions().
		this.registerJSONPlugin((type, res, req) => {
			if (typeof res.data.select == 'string' &&
				typeof res.data.func == 'string')
			{
				try
				{
					var jQueryObject = (<any>jQuery)(res.data.select, req.context);
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
		}, this.#wnd, 'jquery');

		// Register the "redirect" plugin
		this.registerJSONPlugin(function(type, res, req) {
			//console.log(res.data.url);
			if (typeof res.data.url == 'string' &&
				typeof res.data.global == 'boolean')
			{
				//Special handling for framework reload
				// Was `|=` in the original .js (an untyped bitwise-OR-assign that,
				// at runtime, silently turned a `true` global into the number 1) -
				// TS rejects `|=` on a value narrowed to `boolean` by the typeof
				// check above. `||=` is behaviorally equivalent here since the only
				// later use of res.data.global is a truthy check.
				res.data.global ||= (res.data.url.indexOf("?cd=10") > 0);

				if (res.data.global)
				{
					(<any>window).egw_topWindow().location.href = res.data.url;
				}
				// json request was originating from a different popup --> redirect that one
				else if(this && (<any>this).DOMContainer && (<any>this).DOMContainer.ownerDocument.defaultView != window &&
					egw((<any>this).DOMContainer.ownerDocument.defaultView).is_popup())
				{
					(<any>this).DOMContainer.ownerDocument.location.href = res.data.url;
				}
				// main window, open url in respective tab
				else
				{
					(<any>window).egw_appWindowOpen(res.data.app, res.data.url);
				}
				return true;
			}
			throw 'Invalid parameters';
		}, null, 'redirect');

		// Register the 'css' plugin
		this.registerJSONPlugin(function(type, res, req) {
			if (typeof res.data == 'string')
			{
				req.egw.includeCSS(res.data);
				return true;
			}
			throw 'Invalid parameters';
		}, null, 'css');

		// Register the 'js' plugin
		this.registerJSONPlugin(function(type, res, req) {
			if (typeof res.data == 'string')
			{
				return Promise.all((<any>res.data).map((src) => import(src)))
					.then(() => req.onLoadFinish.call(req.sender));
			}
			throw 'Invalid parameters';
		}, null, 'js');

		// Register the 'html' plugin, replacing document content with send html
		this.registerJSONPlugin((type, res, req) => {
			if (typeof res.data == 'string')
			{
				// Empty the document tree
				while (this.#wnd.document.childNodes.length > 0)
				{
					this.#wnd.document.removeChild(this.#wnd.document.childNodes[0]);
				}

				// Write the given content
				this.#wnd.document.write(res.data);

				// Close the document
				this.#wnd.document.close();
				return true;
			}
			throw 'Invalid parameters';
		}, null, 'html');
	}

	/**
	 * Check if there is a *working* connection to a push server
	 */
	pushAvailable = () : boolean =>
	{
		return this.#websocket !== null && this.#websocket.readyState == this.#websocket.OPEN && this.#reconnectTime === MIN_RECONNECT_TIME;
	}

	/**
	 * `json()`/`request()` both need the dynamic `this` of whichever
	 * egw(app,wnd) instance the caller used (passed on as _egw, for eg.
	 * req.egw.message()/.lang()/.debug() to dispatch correctly), while also
	 * needing this Json instance's own state (plugins/websocket/wnd) for the
	 * new JsonRequest - the standard self-capture pattern.
	 */
	json = ((self : Json) => function(this : any, _menuaction : string, _parameters? : any[] | object, _callback? : Function,
		_context? : any, _async? : boolean|"keepalive", _sender? : any) : JsonRequest
	{
		return new JsonRequest(_menuaction, _parameters, _callback, _context, _async, _sender, this, self);
	})(this);

	/**
	 * Do an AJAX call and get a javascript promise, which will be resolved with the returned data.
	 *
	 * egw.request() returns immediately with a Promise.  The promise will be resolved with just the returned data,
	 * any other "piggybacked" responses will be handled by registered handlers.  The data will also be passed to
	 * any registered data handlers (egw.data) before it is passed to your handler.
	 *
	 * @return Promise resolving to data part (not full response, which can contain other parts)
	 * Promise.abort() allows to abort the pending request
	 */
	request = ((self : Json) => function(this : any, _menuaction : string, _parameters? : any[] | object) : any
	{
		const request : any = new JsonRequest(_menuaction, _parameters, null, this, true, this, this, self);
		const response = request.sendRequest();
		let promise : any = response.then(function(response : any)
		{
			// The ajax request has completed, get just the data & pass it on
			if(response && response.response)
			{
				let data = [];
				for(let value of response.response)
				{
					if(value.type && value.type === "data" && typeof value.data !== "undefined")
					{
						// Data was packed in response
						data.push(value.data);
					}
					else if (value && typeof value.type === "undefined" && typeof value.data === "undefined")
					{
						// Just raw data
						data.push(value);
					}
				}
				// Normally only 1 data, but multiple etemplate.exec calls can give multiple
				return data.length > 1 ? data : data[0];
			}
			return response;
		});
		// pass abort method to returned response
		if (typeof response.abort === 'function')
		{
			promise.abort = response.abort;
		}
		return promise;
	})(this);

	/**
	 * Pure dynamic dispatch through this.applyFunc(...) - no own state needed.
	 */
	callFunc = function(this : any, _func : string|Function, ...args : any) : any
	{
		return this.applyFunc(_func, args);
	}

	/**
	 * Needs this Json instance's own #wnd (as the default `parent`/recursion
	 * context) plus dynamic `this.webserverUrl`/self-recursion - self-capture.
	 */
	applyFunc = ((self : Json) => function(this : any, _func : string|Function, args : any, _context? : any) : any
	{
		let parent : any = _context || self.#wnd;
		let func : any = _func;

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
				else if (i == 1 && parts[0] == 'app' && typeof (_context || self.#wnd).app.classes[parts[1]] === 'undefined')
				{
					return (<any>self.#wnd).egw_import(this.webserverUrl+'/'+parts[1]+'/js/app.min.js?'+((new Date).valueOf()/86400|0).toString())
						.then(() => this.applyFunc(_func, args, _context || self.#wnd),
							(err) => {console.error("Failure loading /"+parts[1]+'/js/app.min.js' + " (" + err + ")\nAborting.")});
				}
				// check if we need a not yet instantiated app.js object --> instantiate it now
				else if (i == 1 && parts[0] == 'app' && typeof (_context || self.#wnd).app.classes[parts[1]] === 'function')
				{
					parent = parent[parts[1]] = new (_context || self.#wnd).app.classes[parts[1]](parts[1], self.#wnd);
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
	})(this);

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
	 * @param _global Register the handler globally or
	 *	locally.  Global handlers must stay around, so should be used
	 *	for global modules.
	 */
	registerJSONPlugin = (_callback : Function, _context : any, _type? : string, _global? : boolean) : void =>
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
		var scoped = _global ? this.#globalPlugins : this.#plugins;

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
	}

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
	 * @param _global Remove a global or local handler.
	 */
	unregisterJSONPlugin = (_callback : Function, _context : any, _type? : string, _global? : boolean) : void =>
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
		var scoped = _global ? this.#globalPlugins : this.#plugins;
		if (typeof scoped[_type] !== 'undefined') {
			for (var i = 0; i < scoped[_type].length; i++)
			{
				if (scoped[_type][i].callback == _callback &&
					scoped[_type][i].context == _context)
				{
					scoped[_type].splice(i, 1);
					break;
				}
			}
		}
	}

	/**
	 * Removes all plugins registered on this (window-local) instance
	 */
	unregisterAllPlugins = () : void =>
	{
		for (const type of Object.getOwnPropertyNames(this.#plugins))
		{
			delete this.#plugins[type];
		}
	}
}

egw.extend('json', egw.MODULE_WND_LOCAL, (_app : string, _wnd : Window) => new Json(_wnd));
