/**
 * eGroupWare eTemplate2
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2012
 * @version $Id$
 */

/*egw:uses
	egw_core;
	egw_debug;
*/

"use strict";

egw.extend("data", egw.MODULE_APP_LOCAL, function (_app, _wnd) {

	/**
	 * How many UIDs we'll tell the server we know about.  No need to pass the whole list around.
	 */
	var KNOWN_UID_LIMIT = 200;

	/**
	 * Cache lifetime
	 *
	 * If cached results are used, we check their timestamp.  If the timestamp
	 * is older than this, we will also ask for fresh data.  For cached data
	 * younger than this, we only return the cache
	 *
	 * 29 seconds, 1 less then the fastest nextmatch autorefresh option
	 */
	var CACHE_LIFETIME = 29; // seconds

	/**
	 * Cached fetches are differentiated from actual results by using this prefix
	 * @type String
	 */
	var CACHE_KEY_PREFIX = 'cached_fetch_';

	var lastModification = null;

	/**
	 * cacheCallback stores callbacks that determine if data is placed
	 * into cacheStorage, or simply kept temporarily.  It is indexed
	 * by prefix.
	 *
	 * @type Array
	 */
	var cacheCallback = {};

	/**
	 * The uid function generates a session-unique id for the current
	 * application by appending the application name to the given uid.
	 *
	 * @param {string} _uid
	 * @param {string} _prefix
	 */
	function UID(_uid, _prefix)
	{
		_prefix = _prefix ? _prefix : _app;

		return _prefix + "::" + _uid;
	}

	/**
	 * Looks like too much data is cached.  Forget some.
	 *
	 * Tries to free up localStorage by removing the oldest cached data for the
	 * given prefix, but if none is found it will look at all cached data.
	 *
	 * @param {string} _prefix UID / application prefix
	 * @returns {Number} Number of cached recordsets removed, normally 1.
	 */
	function _clearCache(_prefix)
	{
		// Find cached items for the prefix, we prefer to expire just within the app
		var indexes = [];
		for(var i = 0; i < window.localStorage.length; i++)
		{
			var key = window.localStorage.key(i);

			// This is a cached fetch for many rows
			if(key.indexOf(CACHE_KEY_PREFIX+_prefix) == 0)
			{
				var cached = JSON.parse(window.localStorage.getItem(key));

				if(cached.lastModification)
				{
					indexes.push({
						key: key,
						lastModification: cached.lastModification
					});
				}
				else
				{
					// No way to know how old it is, just remove it
					window.localStorage.removeItem(key);
				}
			}
			// Actual cached data
			else if (key.indexOf(_prefix) == 0)
			{
				var cached = JSON.parse(window.localStorage.getItem(key));
				if(cached.timestamp)
				{
					indexes.push({
						key: key,
						lastModification: cached.timestamp
					});
				}
				else
				{
					// No way to know how old it is, just remove it
					window.localStorage.removeItem(key);
				}
			}
		}
		// Nothing for that prefix?  Clear all cached data.
		if(_prefix && indexes.length == 0)
		{
			return _clearCache('');
		}
		// Found some cached for that prefix, only remove the oldest
		else if (indexes.length > 0)
		{
			indexes.sort(function(a,b) {
				if(a.lastModification < b.lastModification) return 1;
				if(a.lastModification > b.lastModification) return -1;
				return 0;
			});
			window.localStorage.removeItem(indexes.pop().key);
			return 1;
		}
		return indexes.length;
	}

	function parseServerResponse(_result, _callback, _context, _execId, _widgetId)
	{
		// Check whether the result is valid
		// This result is not for us, quietly return
		if(_result && typeof _result.type != 'undefined') return;

		// "result" has to be an object consting of "order" and "data"
		if (!(_result && typeof _result.order !== "undefined"
		    && typeof _result.data !== "undefined"))
		{
			egw.debug("error", "Invalid result for 'dataFetch'");
		}

		if (_result.lastModification)
		{
			lastModification = _result.lastModification;
		}

		if (_result.order && _result.data)
		{
			// Assemble the correct order uids
			if(!(_result.order.length && _result.order[0] && _result.order[0].indexOf && _result.order[0].indexOf(_context.prefix) == 0))
			{
				for (var i = 0; i < _result.order.length; i++)
				{
					_result.order[i] = UID(_result.order[i], _context.prefix);
				}
			}

			// Load all data entries that have been sent or delete them
			for (var key in _result.data)
			{
				var uid = UID(key, (typeof _context == "object" && _context != null) ?_context.prefix : "");
				if (_result.data[key] === null &&
				(
					typeof _context.refresh == "undefined" || _context.refresh && !jQuery.inArray(key,_context.refresh)
				))
				{
					egw.dataDeleteUID(uid);
				}
				else
				{
					egw.dataStoreUID(uid, _result.data[key]);
				}
			}

			// Tried to refresh a specific row and got nothing, so set it to null
			// (triggers update for listeners), then remove it
			if(_result.order.length == 0 && typeof _context == "object" && _context.refresh)
			{
				for(var i = 0; i < _context.refresh.length; i++)
				{
					var uid = UID(_context.refresh[i], _context.prefix);
					egw.dataStoreUID(uid, null);
					egw.dataDeleteUID(uid);
				}
			}

			// Check to see if we need long-term caching of the query and its results
			if(window.localStorage && _context.prefix && cacheCallback[_context.prefix]  && !_context.no_cache)
			{
				// Ask registered callbacks if we should cache this
				for(var i = 0; i < cacheCallback[_context.prefix].length; i++)
				{
					var cc = cacheCallback[_context.prefix][i];
					var cache_key = cc.callback.call(cc.context, _context);
					if(cache_key)
					{
						cache_key = CACHE_KEY_PREFIX + _context.prefix + '::' + cache_key;
						try
						{
							for (var key in _result.data)
							{
								var uid = UID(key, (typeof _context == "object" && _context != null) ? _context.prefix : "");

								// Register a handler on each data so we can know if it is updated or removed
								egw.dataUnregisterUID(uid, null, cache_key);
								egw.dataRegisterUID(uid, function(data, _uid) {
									// If data item is removed, remove it from cached fetch too
									if(data == null)
									{
										var cached = JSON.parse(window.localStorage[this]) || false;
										if(cached && cached.order && cached.order.indexOf(_uid) >= 0)
										{
											cached.order.splice(cached.order.indexOf(_uid),1);
											if(cached.total) cached.total--;
											window.localStorage[this] = JSON.stringify(cached);
										}
										window.localStorage.removeItem(_uid);
									}
									else
									{
										// Update or store data in long-term storage
										window.localStorage[_uid] = JSON.stringify({timestamp: (new Date).getTime(), data: data});
									}
								}, cache_key, _execId, _widgetId);
							}
							// Don't keep data in long-term cache with request also
							_result.data = {};
							window.localStorage.setItem(cache_key,JSON.stringify(_result));
						}
						catch (e)
						{
							// Maybe ran out of space?  Free some up.
							if(e.name == 'QuotaExceededError'	// storage quota is exceeded, remove cached data
								|| e.name == 'NS_ERROR_DOM_QUOTA_REACHED')	// FF-name
							{
								var count = _clearCache(_context.prefix);
								egw.debug('info', 'localStorage full, removed ' + count + ' stored datasets');
							}
							// No, something worse happened
							else
							{
								egw.debug('warning', 'Tried to cache some data.  It did not work.', cache_key, e);
							}
						}
					}
				}
			}

			// Call the callback function and pass the calculated "order" array
			// as well as the "total" count and the "timestamp" to the listener.
			if (_callback)
			{
				_callback.call(_context, {
					"order": _result.order,
					"total": parseInt(_result.total),
					"readonlys": _result.readonlys,
					"rows": _result.rows,
					"lastModification": lastModification
				});
			}
		}
	}

	return {

		/**
		 * The dataFetch function provides an abstraction layer for the
		 * corresponding "etemplate_widget_nextmatch::ajax_get_rows" function.
		 * The server returns the following structure:
		 * 	{
		 * 		order: [uid, ...],
		 * 		data:
		 * 			{
		 * 				uid0: data,
		 * 				...
		 * 				uidN: data
		 * 			},
		 * 		total: <TOTAL COUNT>,
		 * 		lastModification: <LAST MODIFICATION TIMESTAMP>,
		 * 		readonlys: <READONLYS>
		 * 	}
		 * If a uid got deleted on the server above data is null.
		 * If a uid is omitted from data, is has not changed since lastModification.
		 *
		 * If order/data is null, this means that nothing has changed for the
		 * given range.
		 * The dataFetch function stores new data for the uid's inside the
		 * local data storage, the grid views are then capable of querying the
		 * data for those uids from the local storage using the
		 * "dataRegisterUID" function.
		 *
		 * @param _execId is the execution context of the etemplate instance
		 * 	you're querying the data for.
		 * @param _queriedRange is an object of the following form:
		 * 	{
		 * 		start: <START INDEX>,
		 * 		num_rows: <COUNT OF ENTRIES>
		 * 	}
		 * The range always corresponds to the given filter settings.
		 * @param _filters contains the filter settings. The filter settings are
		 * 	those which are crucial for the mapping between index and uid.
		 * @param _widgetId id with full namespace of widget
		 * @param _callback is the function that should get called, once the data
		 * 	is available. The data passed to the callback function has the
		 * 	following form:
		 * 	{
		 * 		order: [uid, ...],
		 * 		total: <TOTAL COUNT>,
		 * 		lastModification: <LAST MODIFICATION TIMESTAMP>,
		 * 		readonlys: <READONLYS>
		 * 	}
		 * 	Please note that the "uids" comming from the server and the ones
		 * 	being parsed to the callback function differ. While the uids
		 * 	which are returned from the server are only unique inside the
		 * 	application, the uids which are used on the client are "globally"
		 * 	unique.
		 * @param _context is the context in which the callback function will get
		 * 	called.
		 * @param _knownUids is an array of uids already known to the client.
		 *  This parameter may be null in order to indicate that the client
		 *  currently has no data for the given filter settings.
		 */
		dataFetch: function (_execId, _queriedRange, _filters, _widgetId,
				_callback, _context, _knownUids)
		{
			var lm = lastModification;
			if(typeof _context.lastModification != "undefined") lm = _context.lastModification;

			if (_queriedRange["no_data"])
			{
				lm = 0xFFFFFFFFFFFF;
			}
			else if (_queriedRange["only_data"])
			{
				lm = 0;
			}

			// Store refresh in context to not delete the other entries when server only returns these
			if (typeof _queriedRange.refresh != "undefined")
			{
				if(typeof _queriedRange.refresh == "string")
				{
					_context.refresh = [_queriedRange.refresh];
				}
				else
				{
					_context.refresh = _queriedRange.refresh;
				}
			}

			// Limit the amount of UIDs we say we know about to a sensible number, in case user is enjoying auto-pagination
			var knownUids = _knownUids ? _knownUids : egw.dataKnownUIDs(_context.prefix ? _context.prefix : _app);
			if(knownUids > KNOWN_UID_LIMIT)
			{
				knownUids.slice(typeof _queriedRange.start != "undefined" ? _queriedRange.start:0,KNOWN_UID_LIMIT);
			}

			// Check to see if we have long-term caching of the query and its results
			if(window.localStorage && _context.prefix && cacheCallback[_context.prefix])
			{
				// Ask registered callbacks if we should cache this
				for(var i = 0; i < cacheCallback[_context.prefix].length; i++)
				{
					var cc = cacheCallback[_context.prefix][i];
					var cache_key = cc.callback.call(cc.context, _context);
					if(cache_key)
					{
						cache_key = CACHE_KEY_PREFIX + _context.prefix + '::' + cache_key;

						var cached = window.localStorage.getItem(cache_key);
						if(cached)
						{
							cached = JSON.parse(cached);
							var needs_update = true;

							// Check timestamp
							if(cached.lastModification && ((Date.now()/1000) - cached.lastModification) < CACHE_LIFETIME)
							{
								needs_update = false;
							}

							egw.debug('log', 'Data cached query from ' + new Date(cached.lastModification*1000)+': ' + cache_key + '('+
								(needs_update ? 'will be' : 'will not be')+" updated)\nprocessing...");

							// Call right away with cached data, but set no_cache flag
							// to avoid re-caching this data with a new timestamp.
							// We may still ask the server though.
							var no_cache = _context.no_cache;
							_context.no_cache = true;
							parseServerResponse(cached, _callback, _context, _execId, _widgetId);
							_context.no_cache = no_cache;


							// If cache registrant wants notification of cache useage,
							// let it know
							if(cc.notification)
							{
								cc.notification.call(cc.context, needs_update);
							}

							if(!needs_update)
							{
								// Cached data is new enough, skip the server call
								return;
							}
						}
					}
				}
			}
			// create a clone of filters, which can be used in parseServerResponse and cache callbacks
			// independent of changes happening while waiting for the response
			_context.filters = jQuery.extend({}, _filters);
			var request = egw.json(
				_app+".etemplate_widget_nextmatch.ajax_get_rows.etemplate",
				[
					_execId,
					_queriedRange,
					_filters,
					_widgetId,
					knownUids,
					lm
				],
				function(result) {
					parseServerResponse(result, _callback, _context, _execId, _widgetId);
				},
				this,
				true
			);
			request.sendRequest();
		},

		/**
		 * Turn on long-term client side cache of a particular request
		 * (cache the nextmatch query results) for fast, immediate response
		 * with old data.
		 *
		 * The request is still sent to the server, and the cache is updated
		 * with fresh data, and any needed callbacks are called again with
		 * the fresh data.
		 *
		 * @param {string} prefix UID / Application prefix should match the
		 *	individual record prefix
		 * @param {function} callback_function A function that will analize the provided fetch
		 *	parameters and return a reproducable cache key, or false to not cache
		 *	the request.
		 * @param {function} notice_function A function that will be called whenever
		 *	cached data is used.  It is passed one parameter, a boolean that indicates
		 *	if the server is or will be queried to refresh the cache.  Do not fetch additional data
		 *	inside this callback, and return quickly.
		 * @param {object} context Context for callback function.
		 */
		dataCacheRegister: function(prefix, callback_function, notice_function, context)
		{
			if(typeof cacheCallback[prefix] == 'undefined')
			{
				cacheCallback[prefix] = [];
			}
			cacheCallback[prefix].push({
				callback: callback_function,
				notification: notice_function || false,
				context: context || null
			});
		},

		/**
		 * Unregister a previously registered cache callback
		 * @param {string} prefix UID / Application prefix should match the
		 *	individual record prefix
		 * @param {function} [callback] Callback function to un-register.  If
		 *	omitted, all functions for the prefix will be removed.
		 */
		dataCacheUnregister: function(prefix, callback)
		{
			if(typeof callback != 'undefined')
			{
				for(var i = 0; i < cacheCallback[prefix].length; i++)
				{
					if(cacheCallback[prefix][i].callback == callback)
					{
						cacheCallback[prefix].splice(i,1);
						return;
					}
				}
			}
			// Callback not provided or not found, reset by prefix
			cacheCallback[prefix] = [];
		}
	};

});

egw.extend("data_storage", egw.MODULE_GLOBAL, function (_app, _wnd) {

	/**
	 * The localStorage object is used to store the data for certain uids. An
	 * entry inside the localStorage object looks like the following:
	 * 	{
	 * 		timestamp: <CREATION TIMESTAMP (local)>,
	 * 		data: <DATA>
	 * 	}
	 */
	var localStorage = {};

	/**
	 * The registeredCallbacks map is used to store all callbacks registerd for
	 * a certain uid.
	 */
	var registeredCallbacks = {};

	/**
	 * Uids and timers used for querying data uids, hashed by the first few
	 * bytes of the _execId, stored as an object of the form
	 * {
	 *     "timer": <QUEUE TIMER>,
	 *     "uids": <ARRAY OF UIDS>
	 * }
	 */
	var queue = {};

	/**
	 * Contains the queue timeout in milliseconds.
	 */
	var QUEUE_TIMEOUT = 10;

	/**
	 * This constant specifies the maximum age of entries in the local storrage
	 * in milliseconds
	 */
	var MAX_AGE = 5 * 60 * 1000; // 5 mins

	/**
	 * This constant specifies the interval in which the local storage gets
	 * cleaned up.
	 */
	var CLEANUP_INTERVAL = 30 * 1000; // 30 sec

	/**
	 * Register a cleanup function, which throws away all data entries which are
	 * older than the given age.
	 */
	_wnd.setInterval(function() {
		// Get the current timestamp
		var time = (new Date).getTime();

		// Iterate over the local storage
		for (var uid in localStorage)
		{
			// Expire old data, if there are no callbacks
			if (time - localStorage[uid].timestamp > MAX_AGE && typeof registeredCallbacks[uid] == "undefined")
			{
				// Unregister all registered callbacks for that uid
				egw.dataUnregisterUID(uid);

				// Delete the data from the localStorage
				delete localStorage[uid];

				// We don't clean long-term storage because of age until it runs
				// out of space
			}
		}
	}, CLEANUP_INTERVAL);

	return {

		/**
		 * Registers the intrest in a certain uid for a callback function. If
		 * the data for that uid changes or gets loaded, the given callback
		 * function is called. If the data for the given uid is available at the
		 * time of registering the callback, the callback is called immediately.
		 *
		 * @param _uid is the uid for which the callback should be registered.
		 * @param _callback is the callback which should get called.
		 * @param _context is the optional context in which the callback will be
		 * executed
		 * @param _execId is the exec id which will be used in case the data is
		 * not available
		 * @param _widgetId is the widget id which will be used in case the uid
		 * has to be fetched.
		 */
		dataRegisterUID: function (_uid, _callback, _context, _execId, _widgetId) {
			// Create the slot for the uid if it does not exist now
			if (typeof registeredCallbacks[_uid] === "undefined")
			{
				registeredCallbacks[_uid] = [];
			}

			// Store the given callback
			registeredCallbacks[_uid].push({
				"callback": _callback,
				"context": _context ? _context : null,
				"execId": _execId,
				"widgetId" : _widgetId
			});

			// Check whether the data is available -- if yes, immediately call
			// back the callback function
			if (typeof localStorage[_uid] !== "undefined")
			{
				// Update the timestamp and call the given callback function
				localStorage[_uid].timestamp = (new Date).getTime();
				_callback.call(_context, localStorage[_uid].data, _uid);
			}
			// Check long-term storage
			else if(window.localStorage && window.localStorage[_uid])
			{
				localStorage[_uid] = JSON.parse(window.localStorage[_uid]);
				_callback.call(_context, localStorage[_uid].data, _uid);
			}
			else if (_execId && _widgetId)
			{
				// Get the first 50 bytes of the exex id
				var hash = _execId.substring(0, 50);

				// Create a new queue if it does not exist yet
				if (typeof queue[hash] === "undefined")
				{
					var self = this;
					queue[hash] = { "uids": [], "timer": null };
					queue[hash].timer = window.setTimeout(function () {
						// Fetch the data
						self.dataFetch(_execId, {"start": 0, "num_rows": 0, "only_data": true, "refresh": queue[hash].uids},
							[], _widgetId, null, _context, null);

						// Delete the queue entry
						delete queue[hash];
					}, 100);
				}

				// Push the uid onto the queue, removing the prefix
				var parts = _uid.split("::");
				parts.shift();
				queue[hash].uids.push(parts.join('::'));
			}
			else
			{
				this.debug("log", "Data for uid " + _uid + " not available.");
			}
		},

		/**
		 * Unregisters the intrest of updates for a certain data uid.
		 *
		 * @param _uid is the data uid for which the callbacks should be
		 * 	unregistered.
		 * @param _callback specifies the specific callback that should be
		 * 	unregistered. If it evaluates to false, all callbacks (or those
		 * 	matching the optionally given context) are removed.
		 * @param _context specifies the callback context that should be
		 * 	unregistered. If it evaluates to false, all callbacks (or those
		 * 	matching the optionally given callback function) are removed.
		 */
		dataUnregisterUID: function (_uid, _callback, _context) {

			// Force the optional parameters to be exactly null
			_callback = _callback ? _callback : null;
			_context = _context ? _context : null;

			if (typeof registeredCallbacks[_uid] !== "undefined")
			{

				// Iterate over the registered callbacks for that uid and delete
				// all callbacks pointing to the given callback and context
				for (var i = registeredCallbacks[_uid].length - 1; i >= 0; i--)
				{
					if ((!_callback || registeredCallbacks[_uid][i].callback === _callback)
					    && (!_context || registeredCallbacks[_uid][i].context === _context))
					{
						registeredCallbacks[_uid].splice(i, 1);
					}
				}

				// Delete the slot if no callback is left for the uid
				if (registeredCallbacks[_uid].length === 0)
				{
					delete registeredCallbacks[_uid];
				}
			}
		},

		/**
		 * Returns whether data is available for the given uid.
		 *
		 * @param _uid is the uid for which should be checked whether it has some
		 * 	data.
		 */
		dataHasUID: function (_uid) {
			return typeof localStorage[_uid] !== "undefined";
		},

		/**
		 * Returns data of a given uid.
		 *
		 * @param _uid is the uid for which should be checked whether it has some
		 * 	data.
		 */
		dataGetUIDdata: function (_uid) {
			return localStorage[_uid];
		},

		/**
		 * Returns all uids that have the given prefix
		 *
		 * @param {string} _prefix
		 * @return {array}
		 * TODO: Improve this
		 */
		dataKnownUIDs: function (_prefix) {

			var result = [];

			for (var key in localStorage)
			{
				var parts = key.split("::");
				if (parts.shift() === _prefix && localStorage[key].data)
				{

					result.push(parts.join('::'));
				}
			}

			return result;
		},

		/**
		 * Stores data for the uid and calls all callback functions registered
		 * for that uid.
		 *
		 * @param _uid is the uid for which the data should be saved.
		 * @param _data is the data which should be saved.
		 */
		dataStoreUID: function (_uid, _data) {
			// Get the current unix timestamp
			var timestamp = (new Date).getTime();

			// Store the data in the local storage
			localStorage[_uid] = {
				"timestamp": timestamp,
				"data": _data
			};

			// Inform all registered callback functions and pass the data to
			// those.
			if (typeof registeredCallbacks[_uid] != "undefined")
			{
				for (var i = registeredCallbacks[_uid].length - 1; i >= 0; i--)
				{
					try {
						registeredCallbacks[_uid][i].callback.call(
							registeredCallbacks[_uid][i].context,
							_data,
							_uid
						);
					} catch (e) {
						// Remove this callback from the list
						registeredCallbacks[_uid].splice(i, 1);
					}
				}
			}
		},

		/**
		 * Deletes the data for a certain uid from the local storage and
		 * unregisters all callback functions associated to it.
		 *
		 * This does NOT update nextmatch!
		 * Application code should use: egw(window).refresh(msg, app, id, "delete");
		 *
		 * @param _uid is the uid which should be deleted.
		 */
		dataDeleteUID: function (_uid) {
			if (typeof localStorage[_uid] !== "undefined")
			{
				// Delete the element from the local storage
				delete localStorage[_uid];

				// Unregister all callbacks for that uid
				this.dataUnregisterUID(_uid);
			}
		},

		/**
		 * Force a refreash of the given uid from the server if known, and
		 * calls all associated callbacks.
		 *
		 * If the UID does not have any registered callbacks, it cannot be refreshed because the required
		 * execID and context are missing.
		 *
		 * @param {string} _uid is the uid which should be refreshed.
		 * @return {boolean} True if the uid is known and can be refreshed, false if unknown and will not be refreshed
		 */
		dataRefreshUID: function (_uid) {
			if (typeof localStorage[_uid] === "undefined") return false;

			if(typeof registeredCallbacks[_uid] !== "undefined" && registeredCallbacks[_uid].length > 0)
			{
				var _execId = registeredCallbacks[_uid][0].execId;
				// This widget ID MUST be a nextmatch, because the data call is to etemplate_widget_nexmatch
				var nextmatchId = registeredCallbacks[_uid][0].widgetId;
				var uid = _uid.split("::");
				var context = {
					"prefix":uid.shift()
				};
				uid = uid.join("::");

				// find filters, even if context is not always from nextmatch, eg. caching uses it's a string context
				var filters = {};
				for(var i=0; i < registeredCallbacks[_uid].length; i++)
				{
					var callback = registeredCallbacks[_uid][i];
					if (typeof callback.context == 'object' &&
						typeof callback.context.self == 'object' &&
						typeof callback.context.self._filters == 'object')
					{
						filters = callback.context.self._filters;
						break;
					}
				}

				// need to send nextmatch filters too, as server-side will merge old version from request otherwise
				this.dataFetch(_execId, {'refresh':uid}, filters, nextmatchId, false, context, [uid]);

				return true;
			}
			return false;
		},

		/**
		 * Search for exact UID string or regular expression and return widgets using it
		 *
		 * @param {string|RegExp} _uid is the uid which should be refreshed.
		 * @return {object} UID: array of (nextmatch-)wigetIds
		 */
		dataSearchUIDs: function(_uid)
		{
			var matches = {};
			var f = function(_uid)
			{
				if (typeof matches[_uid] == "undefined")
				{
					matches[_uid] = [];
				}
				if (typeof registeredCallbacks[_uid] !== "undefined")
				{
					for(var n=0; n < registeredCallbacks[_uid].length; ++n)
					{
						var callback = registeredCallbacks[_uid][n];
						if (typeof callback.context != "undefined" &&
							typeof callback.context.self != "undefined" &&
							typeof callback.context.self._widget != "undefined")
						{
							matches[_uid].push(callback.context.self._widget);
						}
					}
				}
			};
			if (typeof _uid == "object" && _uid.constructor.name == "RegExp")
			{
				for(var uid in localStorage)
				{
					if (_uid.test(uid))
					{
						f(uid);
					}
				}
			}
			else if (typeof localStorage[_uid] != "undefined")
			{
				f(_uid);
			}
			return matches;
		},

		/**
		 * Search for exact UID string or regular expression and call registered (nextmatch-)widgets refresh function with given _type
		 *
		 * This method is preferable over dataRefreshUID for app code, as it takes care of things like counters too.
		 *
		 * It does not do anything for _type="add"!
		 *
		 * @param {string|RegExp) _uid is the uid which should be refreshed.
		 * @param {string} _type "delete", "edit", "update", not useful for "add"!
		 * @return {array} (nextmatch-)wigets refreshed
		 */
		dataRefreshUIDs: function(_uid, _type)
		{
			var uids = this.dataSearchUIDs(_uid);
			var widgets = [];
			var uids4widget = [];
			for(var uid in uids)
			{
				for(var n=0; n < uids[uid].length; ++n)
				{
					var widget = uids[uid][n];
					var idx = widgets.indexOf(widget);
					if (idx == -1)
					{
						widgets.push(widget);
						idx = widgets.length-1;
					}
					// uids for nextmatch.refesh do NOT contain the prefix
					var nm_uid = uid.replace(RegExp('^'+widget.controller.dataStorePrefix+'::'), '');
					if (typeof uids4widget[idx] == "undefined")
					{
						uids4widget[idx] = [nm_uid];
					}
					else
					{
						uids4widget[idx].push(nm_uid);
					}
				}
			}
			for(var w=0; w < widgets.length; ++w)
			{
				widgets[w].refresh(uids4widget[w], _type);
			}
			return widgets;
		}
	};
});
