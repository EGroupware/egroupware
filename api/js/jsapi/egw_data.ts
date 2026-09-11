/**
 * eGroupWare eTemplate2
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2012
 */

import './egw.js';
import './egw_json';	// for egw.registerJSONPlugin

export interface DataModule
{
	/**
	 * The dataFetch function provides an abstraction layer for the
	 * corresponding "EGroupware\Api\Etemplate\Widget\Nextmatch::ajax_get_rows" function.
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
	 * @return a Promise that rejects if the request failed (network error, non-2xx response,
	 *  malformed response, ...) - the default error message/logging still happens regardless.
	 *  On success _callback has already run by the time the promise resolves; the promise
	 *  itself carries no data, it only signals completion/failure for callers that need to
	 *  know a fetch didn't just silently never call _callback.
	 */
	dataFetch(_execId : string, _queriedRange : {start? : number, num_rows? : number, refresh? : string|string[], no_data? : boolean, only_data? : boolean},
	          _filters : object, _widgetId : string, _callback : Function, _context : any,
	          _knownUids? : string[]) : Promise<void>;

	/**
	 * Turn on long-term client side cache of a particular request
	 * (cache the nextmatch query results) for fast, immediate response
	 * with old data.
	 *
	 * The request is still sent to the server, and the cache is updated
	 * with fresh data, and any needed callbacks are called again with
	 * the fresh data.
	 *
	 * @param prefix UID / Application prefix should match the
	 *	individual record prefix
	 * @param callback_function A function that will analize the provided fetch
	 *	parameters and return a reproducable cache key, or false to not cache
	 *	the request.
	 * @param notice_function A function that will be called whenever
	 *	cached data is used.  It is passed one parameter, a boolean that indicates
	 *	if the server is or will be queried to refresh the cache.  Do not fetch additional data
	 *	inside this callback, and return quickly.
	 * @param context Context for callback function.
	 */
	dataCacheRegister(prefix : string, callback_function : Function, notice_function : Function, context : object) : void;

	/**
	 * Unregister a previously registered cache callback
	 * @param prefix UID / Application prefix should match the
	 *	individual record prefix
	 * @param callback Callback function to un-register.  If
	 *	omitted, all functions for the prefix will be removed.
	 */
	dataCacheUnregister(prefix : string, callback? : Function) : void;

	/**
	 * Let an app opt-in to answer dataFetch() itself, instead of the regular
	 * ajax_get_rows request - e.g. mail fetching rows directly from a JMAP
	 * server for Stalwart-backed accounts.
	 *
	 * Only one registered callback per prefix is expected to actually handle a
	 * given fetch; the first one returning a truthy value wins.
	 *
	 * @param prefix UID / Application prefix should match the
	 *	individual record prefix
	 * @param callback_function function(_execId, _queriedRange, _filters,
	 *	_widgetId, _knownUids, _lastModification) - called before the regular
	 *	ajax_get_rows request. Return false/undefined to let dataFetch() continue
	 *	normally, or a {order, data, total, lastModification, readonlys} result
	 *	(same shape ajax_get_rows itself returns, un-prefixed uids) - or a Promise
	 *	resolving to one of those - to answer the fetch instead.
	 * @param context Context for callback function.
	 */
	dataRegisterFetch(prefix : string, callback_function : Function, context : object) : void;

	/**
	 * Unregister a previously registered fetch callback
	 * @param prefix UID / Application prefix should match the
	 *	individual record prefix
	 * @param callback Callback function to un-register.  If
	 *	omitted, all functions for the prefix will be removed.
	 */
	dataUnregisterFetch(prefix : string, callback? : Function) : void;
}

export interface DataStorageModule
{
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
	dataRegisterUID(_uid : string, _callback : Function, _context?, _execId?: string, _widgetId?: string) : void;

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
	dataUnregisterUID(_uid : string, _callback? : Function, _context?) : void;

	/**
	 * Returns whether data is available for the given uid.
	 *
	 * @param _uid is the uid for which should be checked whether it has some
	 * 	data.
	 */
	dataHasUID(_uid : string) : boolean;

	/**
	 * Returns data of a given uid.
	 *
	 * @param _uid is the uid for which should be checked whether it has some
	 * 	data.
	 */
	dataGetUIDdata(_uid : string) : IegwData;

	/**
	 * Returns all uids that have the given prefix
	 *
	 * @param _prefix
	 * @return of uids
	 */
	dataKnownUIDs(_prefix : string) : string[];

	/**
	 * Stores data for the uid and calls all callback functions registered
	 * for that uid.
	 *
	 * @param _uid is the uid for which the data should be saved.
	 * @param _data is the data which should be saved.
	 * @param _skipCallback do not call any callback functions, just update the local storage
	 */
	dataStoreUID(_uid : string, _data : object, _skipCallback?:boolean) : void;

	/**
	 * Deletes the data for a certain uid from the local storage and
	 * unregisters all callback functions associated to it.
	 *
	 * This does NOT update nextmatch!
	 * Application code should use: egw(window).refresh(msg, app, id, "delete");
	 *
	 * @param _uid is the uid which should be deleted.
	 */
	dataDeleteUID(_uid : string) : void;

	/**
	 * Force a refreash of the given uid from the server if known, and
	 * calls all associated callbacks.
	 *
	 * If the UID does not have any registered callbacks, it cannot be refreshed because the required
	 * execID and context are missing.
	 *
	 * @param _uid is the uid which should be refreshed.
	 * @return True if the uid is known and can be refreshed, false if unknown and will not be refreshed
	 */
	dataRefreshUID(_uid : string) : boolean;

	/**
	 * Search for exact UID string or regular expression and return widgets using it
	 *
	 * @param _uid is the uid which should be refreshed.
	 * @return UID: array of (nextmatch-)wigetIds
	 */
	dataSearchUIDs(_uid : string|RegExp) : /*et2_nextmatch*/any[];

	/**
	 * Search for exact UID string or regular expression and call registered (nextmatch-)widgets refresh function with given _type
	 *
	 * This method is preferable over dataRefreshUID for app code, as it takes care of things like counters too.
	 *
	 * It does not do anything for _type="add"!
	 *
	 * @param _uid is the uid which should be refreshed.
	 * @param _type "delete", "edit", "update", not useful for "add"!
	 * @return (nextmatch-)wigets refreshed
	 */
	dataRefreshUIDs(_uid : string|RegExp, _type : "delete"|"edit"|"update") : /*et2_nextmatch*/any[];
}

declare global
{
	interface IegwAppLocal extends DataModule
	{
	}

	interface IegwGlobal extends DataStorageModule
	{
	}
}

/**
 * How many UIDs we'll tell the server we know about.  No need to pass the whole list around.
 */
const KNOWN_UID_LIMIT = 200;

/**
 * Cache lifetime
 *
 * If cached results are used, we check their timestamp.  If the timestamp
 * is older than this, we will also ask for fresh data.  For cached data
 * younger than this, we only return the cache
 *
 * 29 seconds, 1 less then the fastest nextmatch autorefresh option
 */
const CACHE_LIFETIME = 29; // seconds

/**
 * Cached fetches are differentiated from actual results by using this prefix
 */
const CACHE_KEY_PREFIX = 'cached_fetch_';

/**
 * Looks like too much data is cached.  Forget some.
 *
 * Tries to free up localStorage by removing the oldest cached data for the
 * given prefix, but if none is found it will look at all cached data.
 *
 * Stateless (only touches window.localStorage + the module-level
 * CACHE_KEY_PREFIX const), so stays a plain module-scope function rather
 * than a class member.
 *
 * @param _prefix UID / application prefix
 * @returns Number of cached recordsets removed, normally 1.
 */
function clearCache(_prefix : string) : number
{
	// Find cached items for the prefix, we prefer to expire just within the app
	var indexes : {key : string, lastModification : number}[] = [];
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
			try {
				let cached = JSON.parse(window.localStorage.getItem(key));
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
			catch (e) {
				window.localStorage.removeItem(key);
			}
		}
	}
	// Nothing for that prefix?  Clear all cached data.
	if(_prefix && indexes.length == 0)
	{
		return clearCache('');
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

/**
 * Module storing and updating row data
 */
class Data implements DataModule
{
	#app : string;
	#lastModification : any = null;

	/**
	 * fetchCallback stores callbacks that get the first chance to answer a
	 * dataFetch() call themselves, instead of the regular ajax_get_rows
	 * request, e.g. to fetch rows via a different transport (see mail's
	 * direct-JMAP get_rows). It is indexed by prefix.
	 */
	#fetchCallback : {[prefix : string] : {callback : Function, context : any}[]} = {};

	/**
	 * cacheCallback stores callbacks that determine if data is placed
	 * into cacheStorage, or simply kept temporarily.  It is indexed
	 * by prefix.
	 */
	#cacheCallback : {[prefix : string] : {callback : Function, notification : Function|false, context : any}[]} = {};

	constructor(_app : string)
	{
		this.#app = _app;
	}

	/**
	 * The uid function generates a session-unique id for the current
	 * application by appending the application name to the given uid.
	 */
	private UID(_uid : string, _prefix? : string) : string
	{
		_prefix = _prefix ? _prefix : this.#app;

		return _prefix + "::" + _uid;
	}

	/**
	 * Never dispatched dynamically (only ever called internally, from
	 * dataFetch() below), so a plain private method needing no self-capture
	 * is enough - called-as-a-method syntax (this.parseServerResponse(...)/
	 * self.parseServerResponse(...)) already gives it the right `this`.
	 */
	private parseServerResponse(_result : any, _callback : Function, _context : any, _execId : string, _widgetId : string) : void
	{
		// Check whether the result is valid
		// This result is not for us, quietly return
		if(_result && typeof _result.type != 'undefined') return;

		// "result" has to be an object consisting of "order" and "data"
		if (!(_result && typeof _result.order !== "undefined"
		    && typeof _result.data !== "undefined"))
		{
			egw.debug("error", "Invalid result for 'dataFetch'");
		}

		if (_result.lastModification)
		{
			this.#lastModification = _result.lastModification;
		}

		if (_result.order && _result.data)
		{
			// Assemble the correct order uids
			if(!(_result.order.length && _result.order[0] && _result.order[0].indexOf && _result.order[0].indexOf(_context.prefix) == 0))
			{
				for (var i = 0; i < _result.order.length; i++)
				{
					_result.order[i] = this.UID(_result.order[i], _context.prefix);
				}
			}

			// Load all data entries that have been sent or delete them
			for (var key in _result.data)
			{
				let uid = this.UID(key, (typeof _context == "object" && _context != null) ?_context.prefix : "");
				if (_result.data[key] === null &&
				(
					typeof _context.refresh == "undefined" || _context.refresh && !_context.refresh.includes(key)
				))
				{
					egw.dataDeleteUID(uid);
				}
				else
				{
					egw.dataStoreUID(uid, _result.data[key]);
				}
			}

			// Check if we tried to refresh a specific row and didn't get it, so set it to null
			// (triggers update for listeners), then remove it
			if(typeof _context == "object" && _context.refresh)
			{
				for(let i = 0; i < _context.refresh.length; i++)
				{
					let uid = this.UID(_context.refresh[i], _context.prefix);
					if(_result.order.indexOf(uid) >= 0)
					{
						continue;
					}
					egw.dataStoreUID(uid, null);
					egw.dataDeleteUID(uid);
				}
			}

			// Check to see if we need long-term caching of the query and its results
			if(window.localStorage && _context.prefix && this.#cacheCallback[_context.prefix]  && !_context.no_cache)
			{
				// Ask registered callbacks if we should cache this
				for(var i = 0; i < this.#cacheCallback[_context.prefix].length; i++)
				{
					var cc = this.#cacheCallback[_context.prefix][i];
					var cache_key : any = cc.callback.call(cc.context, _context);
					if(cache_key)
					{
						cache_key = CACHE_KEY_PREFIX + _context.prefix + '::' + cache_key;
						try
						{
							for (var key in _result.data)
							{
								var uid = this.UID(key, (typeof _context == "object" && _context != null) ? _context.prefix : "");

								// Register a handler on each data so we can know if it is updated or removed
								egw.dataUnregisterUID(uid, null, cache_key);
								egw.dataRegisterUID(uid, function(this : any, data : any, _uid : string) {
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
								var count = clearCache(_context.prefix);
								egw.debug('info', 'localStorage full, removed ' + count + ' stored datasets');
							}
							// No, something worse happened
							else
							{
								// "warning" is not a recognized debug level (only "warn" is) - this call
								// was already a silent no-op in the original .js; preserved as-is rather
								// than "fixed" to actually log, which would be an observable behavior change.
								(<any>egw.debug)('warning', 'Tried to cache some data.  It did not work.', cache_key, e);
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
					"lastModification": this.#lastModification
				});
			}
		}
	}

	/**
		 * The dataFetch function provides an abstraction layer for the
		 * corresponding "EGroupware\Api\Etemplate\Widget\Nextmatch::ajax_get_rows" function.
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
	/**
	 * `this` is only ever referenced inside the nested sendRequest() below,
	 * which is a plain (non-arrow) function called bare - so its own `this`
	 * is independent of dataFetch()'s, same as in the original. That means
	 * dataFetch() itself has no actual dynamic-dispatch dependency despite
	 * declaring `this: any` in the original, and can be a plain arrow field;
	 * `self` captures it once for sendRequest()'s benefit, to reach this
	 * instance's own #cacheCallback/parseServerResponse().
	 */
	dataFetch = (_execId : string, _queriedRange : any, _filters : any, _widgetId : string,
			_callback : Function, _context : any, _knownUids? : string[]) : Promise<void> =>
	{
		const self = this;
		var lm = this.#lastModification;
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
		var knownUids : any = _knownUids ? _knownUids : egw.dataKnownUIDs(_context.prefix ? _context.prefix : this.#app);
		if(knownUids > KNOWN_UID_LIMIT)
		{
			knownUids.slice(typeof _queriedRange.start != "undefined" ? _queriedRange.start:0,KNOWN_UID_LIMIT);
		}

		// Regular request to ajax_get_rows, incl. the long-term query cache check.
		// Named so a registered fetchCallback (see below) can fall back to it asynchronously.
		function sendRequest() : Promise<void>
		{
			// Check to see if we have long-term caching of the query and its results
			if(window.localStorage && _context.prefix && self.#cacheCallback[_context.prefix])
			{
				// Ask registered callbacks if we should cache this
				for(var i = 0; i < self.#cacheCallback[_context.prefix].length; i++)
				{
					var cc = self.#cacheCallback[_context.prefix][i];
					var cache_key : any = cc.callback.call(cc.context, _context);
					if(cache_key)
					{
						cache_key = CACHE_KEY_PREFIX + _context.prefix + '::' + cache_key;

						var cached : any = window.localStorage.getItem(cache_key);
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
							self.parseServerResponse(cached, _callback, _context, _execId, _widgetId);
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
								return Promise.resolve();
							}
						}
					}
				}
			}
			// create a clone of filters, which can be used in parseServerResponse and cache callbacks
			// independent of changes happening while waiting for the response
			_context.filters = {..._filters};
			var request = egw.json(
				"EGroupware\\Api\\Etemplate\\Widget\\Nextmatch::ajax_get_rows",
				[
					_execId,
					_queriedRange,
					_filters,
					_widgetId,
					knownUids,
					lm
				],
				function(result) {
					self.parseServerResponse(result, _callback, _context, _execId, _widgetId);
				},
				this,
				true
			);
			// request.sendRequest()'s own promise already resolves to `undefined` on any
			// failure (network error, no response, bad JSON, abort - its .catch() calls the
			// default error handler for the message/logging, then swallows rather than
			// rethrowing) and to the truthy response data on success. _callback above has
			// already run by the time this settles. Surface the swallowed failure as a
			// rejection here so callers can await dataFetch() to know the request failed,
			// instead of _callback silently never firing.
			return request.sendRequest().then((result : any) =>
			{
				if(typeof result === "undefined")
				{
					throw new Error("dataFetch request to " + _widgetId + " failed");
				}
			});
		}

		// Give a registered app callback (see dataRegisterFetch) the first chance to
		// answer this fetch itself, e.g. via a different transport than ajax_get_rows.
		if(_context.prefix && this.#fetchCallback[_context.prefix] && this.#fetchCallback[_context.prefix].length)
		{
			for(var i = 0; i < this.#fetchCallback[_context.prefix].length; i++)
			{
				var fc = this.#fetchCallback[_context.prefix][i];
				var result : any = fc.callback.call(fc.context, _execId, _queriedRange, _filters, _widgetId, knownUids, lm);
				if(result)
				{
					// result may be the {order, data, total, ...} shape directly, or a
					// Promise resolving to it (or to false/undefined to fall back)
					return Promise.resolve(result).then(function(res)
					{
						if(res)
						{
							self.parseServerResponse(res, _callback, _context, _execId, _widgetId);
						}
						else
						{
							return sendRequest();
						}
					}, function(err)
					{
						// misbehaving fetchCallback (rejected instead of resolving false) - fall back rather than hang
						egw.debug('warn', 'dataRegisterFetch callback for prefix "'+_context.prefix+'" rejected, falling back to ajax_get_rows', err);
						return sendRequest();
					});
				}
			}
		}

		return sendRequest();
	}

	/**
	 * Turn on long-term client side cache of a particular request
	 * (cache the nextmatch query results) for fast, immediate response
	 * with old data.
	 *
	 * The request is still sent to the server, and the cache is updated
	 * with fresh data, and any needed callbacks are called again with
	 * the fresh data.
	 *
	 * @param prefix UID / Application prefix should match the
	 *	individual record prefix
	 * @param callback_function A function that will analize the provided fetch
	 *	parameters and return a reproducable cache key, or false to not cache
	 *	the request.
	 * @param notice_function A function that will be called whenever
	 *	cached data is used.  It is passed one parameter, a boolean that indicates
	 *	if the server is or will be queried to refresh the cache.  Do not fetch additional data
	 *	inside this callback, and return quickly.
	 * @param context Context for callback function.
	 */
	dataCacheRegister = (prefix : string, callback_function : Function, notice_function? : Function, context? : any) : void =>
	{
		if(typeof this.#cacheCallback[prefix] == 'undefined')
		{
			this.#cacheCallback[prefix] = [];
		}
		this.#cacheCallback[prefix].push({
			callback: callback_function,
			notification: notice_function || false,
			context: context || null
		});
	}

	/**
	 * Unregister a previously registered cache callback
	 * @param prefix UID / Application prefix should match the
	 *	individual record prefix
	 * @param callback Callback function to un-register.  If
	 *	omitted, all functions for the prefix will be removed.
	 */
	dataCacheUnregister = (prefix : string, callback? : Function) : void =>
	{
		if(typeof callback != 'undefined')
		{
			for(var i = 0; i < this.#cacheCallback[prefix].length; i++)
			{
				if(this.#cacheCallback[prefix][i].callback == callback)
				{
					this.#cacheCallback[prefix].splice(i,1);
					return;
				}
			}
		}
		// Callback not provided or not found, reset by prefix
		this.#cacheCallback[prefix] = [];
	}

	/**
	 * Let an app opt-in to answer dataFetch() itself, instead of the regular
	 * ajax_get_rows request - e.g. mail fetching rows directly from a JMAP
	 * server for Stalwart-backed accounts.
	 *
	 * Only one registered callback per prefix is expected to actually handle a
	 * given fetch; the first one returning a truthy value wins.
	 *
	 * @param prefix UID / Application prefix should match the
	 *	individual record prefix
	 * @param callback_function function(_execId, _queriedRange, _filters,
	 *	_widgetId, _knownUids, _lastModification) - called before the regular
	 *	ajax_get_rows request. Return false/undefined to let dataFetch() continue
	 *	normally, or a {order, data, total, lastModification, readonlys} result
	 *	(same shape ajax_get_rows itself returns, un-prefixed uids) - or a Promise
	 *	resolving to one of those - to answer the fetch instead.
	 * @param context Context for callback function.
	 */
	dataRegisterFetch = (prefix : string, callback_function : Function, context? : any) : void =>
	{
		if(typeof this.#fetchCallback[prefix] == 'undefined')
		{
			this.#fetchCallback[prefix] = [];
		}
		this.#fetchCallback[prefix].push({
			callback: callback_function,
			context: context || null
		});
	}

	/**
	 * Unregister a previously registered fetch callback
	 * @param prefix UID / Application prefix should match the
	 *	individual record prefix
	 * @param callback Callback function to un-register.  If
	 *	omitted, all functions for the prefix will be removed.
	 */
	dataUnregisterFetch = (prefix : string, callback? : Function) : void =>
	{
		if(typeof callback != 'undefined' && this.#fetchCallback[prefix])
		{
			for(var i = 0; i < this.#fetchCallback[prefix].length; i++)
			{
				if(this.#fetchCallback[prefix][i].callback == callback)
				{
					this.#fetchCallback[prefix].splice(i,1);
					return;
				}
			}
			return;
		}
		// Callback not provided or not found, reset by prefix
		this.#fetchCallback[prefix] = [];
	}
}

egw.extend("data", egw.MODULE_APP_LOCAL, (_app : string, _wnd : Window) => new Data(_app));

/**
 * Contains the queue timeout in milliseconds.
 *
 * Unused since the original .js (dead even there - kept as-is rather than
 * removed, not this modernization pass's job to prune dead code).
 */
const QUEUE_TIMEOUT = 10;

/**
 * This constant specifies the maximum age of entries in the local storrage
 * in milliseconds
 */
const MAX_AGE = 5 * 60 * 1000; // 5 mins

/**
 * This constant specifies the interval in which the local storage gets
 * cleaned up.
 */
const CLEANUP_INTERVAL = 30 * 1000; // 30 sec

/**
 * Module holding the shared, global data cache and its registered
 * per-uid update callbacks - shared by every app's Data (dataFetch)
 * instance above, plus grid widgets querying it directly.
 */
class DataStorage implements DataStorageModule
{
	/**
	 * The localStorage field caches the data for certain uids. An
	 * entry looks like the following:
	 * 	{
	 * 		timestamp: <CREATION TIMESTAMP (local)>,
	 * 		data: <DATA>
	 * 	}
	 *
	 * Named to match the original's shadowing local variable; window's
	 * localStorage API is always referenced explicitly (window.localStorage)
	 * so there's no ambiguity between the two.
	 */
	#localStorage : {[uid : string] : {timestamp : number, data : any}} = {};

	/**
	 * The registeredCallbacks map is used to store all callbacks registerd for
	 * a certain uid.
	 */
	#registeredCallbacks : {[uid : string] : {callback : Function, context : any, execId : string, widgetId : string}[]} = {};

	/**
	 * Uids and timers used for querying data uids, hashed by the first few
	 * bytes of the _execId, stored as an object of the form
	 * {
	 *     "timer": <QUEUE TIMER>,
	 *     "uids": <ARRAY OF UIDS>
	 * }
	 */
	#queue : {[hash : string] : {timer : any, uids : string[]}} = {};

	constructor(_wnd : Window)
	{
		egw.registerJSONPlugin(function(this : any, type, res, req) {
			// registered globally, so it sees every "data"-typed response app-wide - res.data is
			// legitimately null for plenty of endpoints that just don't happen to use this {uid,
			// data} caching convention (eg. mail.mail_ui.ajax_resolveSpecialCaseBody(), which
			// returns null when it has nothing to resolve yet) - found live 2026-09-01, crashed
			// resolveSpecialCaseBody()'s own response handling with "Cannot read properties of
			// null (reading 'uid')".
			if (res.data &&
				(typeof res.data.uid != 'undefined') &&
				(typeof res.data.data != 'undefined'))
			{
				// Store it, which will call all registered listeners
				this.dataStoreUID(res.data.uid, res.data.data);
				return true;
			}
		}, egw, 'data', true);

		/**
		 * Register a cleanup function, which throws away all data entries which are
		 * older than MAX_AGE.
		 */
		_wnd.setInterval(() => {
			// Get the current timestamp
			var time = (new Date).getTime();

			// Iterate over the local storage
			for (var uid in this.#localStorage)
			{
				// Expire old data, if there are no callbacks
				if (time - this.#localStorage[uid].timestamp > MAX_AGE && typeof this.#registeredCallbacks[uid] == "undefined")
				{
					// Unregister all registered callbacks for that uid
					egw.dataUnregisterUID(uid);

					// Delete the data from the localStorage
					delete this.#localStorage[uid];

					// We don't clean long-term storage because of age until it runs
					// out of space
				}
			}
		}, CLEANUP_INTERVAL);
	}

	/**
	 * Registers the intrest in a certain uid for a callback function. If
	 * the data for that uid changes or gets loaded, the given callback
	 * function is called. If the data for the given uid is available at the
	 * time of registering the callback, the callback is called immediately.
	 *
	 * `this.dataFetch(...)`/`this.debug(...)` in the queueing branch below
	 * dispatch to the "data" (app-local) module merged onto whichever
	 * egw(app,wnd) instance the caller used, so this needs dynamic `this` -
	 * hence the `self`-capture for reaching this instance's own
	 * #registeredCallbacks/#localStorage/#queue.
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
	dataRegisterUID = ((self : DataStorage) => function (this : any, _uid : string, _callback : Function, _context? : any, _execId? : string, _widgetId? : string) : void {
			// Create the slot for the uid if it does not exist now
			if (typeof self.#registeredCallbacks[_uid] === "undefined")
			{
				self.#registeredCallbacks[_uid] = [];
			}

			// Store the given callback
			self.#registeredCallbacks[_uid].push({
				"callback": _callback,
				"context": _context ? _context : null,
				"execId": _execId,
				"widgetId" : _widgetId
			});

			// Check whether the data is available -- if yes, immediately call
			// back the callback function
			if (typeof self.#localStorage[_uid] !== "undefined")
			{
				// Update the timestamp and call the given callback function
				self.#localStorage[_uid].timestamp = (new Date).getTime();
				_callback.call(_context, self.#localStorage[_uid].data, _uid);
			}
			// Check long-term storage
			else if((<any>window).localStorage && (<any>window).localStorage[_uid])
			{
				self.#localStorage[_uid] = JSON.parse((<any>window).localStorage[_uid]);
				_callback.call(_context, self.#localStorage[_uid].data, _uid);
			}
			else if (_execId && _widgetId)
			{
				// Get the first 50 bytes of the exex id
				var hash = _execId.substring(0, 50);

				// Create a new queue if it does not exist yet
				if (typeof self.#queue[hash] === "undefined")
				{
					// captures the CALLING egw(app,wnd) instance (this function's own
					// dynamic `this`, distinct from `self` above which is always this
					// DataStorage instance), so the deferred fetch below dispatches
					// through the same instance dataRegisterUID() was invoked through
					var egwInstance = this;
					self.#queue[hash] = {"uids": [], "timer": null};
					// scheduled BY the calling instance's window, not by us: a timer only fires
					// if both its callback and the setTimeout() call belong to a fully active
					// document, and our realm is the opener's for anything running in a popup,
					// so it may already be gone. See egw_set_timeout() in egw.js
					const timerWnd : any = (egwInstance && egwInstance.window) || window;
					const fetchQueued = function () {
						// Fetch the data - failure is already reported via the default error
						// message/logging, nothing more to do here.
						egwInstance.dataFetch(_execId, {
								"start": 0,
								"num_rows": 0,
								"only_data": true,
								"refresh": self.#queue[hash].uids
							},
							[], _widgetId, null, _context, null).catch(() => {});

						// Delete the queue entry
						delete self.#queue[hash];
					};
					self.#queue[hash].timer = typeof timerWnd.egw_set_timeout === 'function'
						? timerWnd.egw_set_timeout(fetchQueued, 100) : timerWnd.setTimeout(fetchQueued, 100);
				}

				// Push the uid onto the queue, removing the prefix
				var parts = _uid.split("::");
				parts.shift();
				if (self.#queue[hash].uids.indexOf(parts.join("::")) === -1)
				{
					self.#queue[hash].uids.push(parts.join('::'));
				}
			}
			else
			{
				this.debug("log", "Data for uid " + _uid + " not available.");
			}
		})(this);

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
	dataUnregisterUID = (_uid : string, _callback? : Function, _context? : any) : void =>
	{
		// Force the optional parameters to be exactly null
		_callback = _callback ? _callback : null;
		_context = _context ? _context : null;

		if (typeof this.#registeredCallbacks[_uid] !== "undefined")
		{
			// Iterate over the registered callbacks for that uid and delete
			// all callbacks pointing to the given callback and context
			for (var i = this.#registeredCallbacks[_uid].length - 1; i >= 0; i--)
			{
				if ((!_callback || this.#registeredCallbacks[_uid][i].callback === _callback)
				    && (!_context || this.#registeredCallbacks[_uid][i].context === _context))
				{
					this.#registeredCallbacks[_uid].splice(i, 1);
				}
			}

			// Delete the slot if no callback is left for the uid
			if (this.#registeredCallbacks[_uid].length === 0)
			{
				delete this.#registeredCallbacks[_uid];
			}
		}
	}

	/**
	 * Returns whether data is available for the given uid.
	 *
	 * @param _uid is the uid for which should be checked whether it has some
	 * 	data.
	 */
	dataHasUID = (_uid : string) : boolean =>
	{
		return typeof this.#localStorage[_uid] !== "undefined";
	}

	/**
	 * Returns data of a given uid.
	 *
	 * @param _uid is the uid for which should be checked whether it has some
	 * 	data.
	 */
	dataGetUIDdata = (_uid : string) : any =>
	{
		return this.#localStorage[_uid];
	}

	/**
	 * Returns all uids that have the given prefix
	 *
	 * @param _prefix
	 * @return of uids
	 * TODO: Improve this
	 */
	dataKnownUIDs = (_prefix : string) : string[] =>
	{
		var result : string[] = [];

		for (var key in this.#localStorage)
		{
			var parts = key.split("::");
			if (parts.shift() === _prefix && this.#localStorage[key].data)
			{
				result.push(parts.join('::'));
			}
		}

		return result;
	}

	/**
	 * Stores data for the uid and calls all callback functions registered
	 * for that uid.
	 *
	 * @param _uid is the uid for which the data should be saved.
	 * @param _data is the data which should be saved.
	 * @param _skip_callback do not call any callback functions, just update the local storage
	 */
	dataStoreUID = (_uid : string, _data : any, _skip_callback : boolean = false) : void =>
	{
		// Get the current unix timestamp
		var timestamp = (new Date).getTime();

		// Store the data in the local storage
		this.#localStorage[_uid] = {
			"timestamp": timestamp,
			"data": _data
		};
		if(_skip_callback) return;

		// Inform all registered callback functions and pass the data to
		// those.
		if (typeof this.#registeredCallbacks[_uid] != "undefined")
		{
			for (var i = this.#registeredCallbacks[_uid].length - 1; i >= 0; i--)
			{
				try {
					this.#registeredCallbacks[_uid][i].callback.call(
						this.#registeredCallbacks[_uid][i].context,
						_data,
						_uid
					);
				} catch (e) {
					// Remove this callback from the list
					if(typeof this.#registeredCallbacks[_uid] != "undefined")
					{
						this.#registeredCallbacks[_uid].splice(i, 1);
					}
				}
			}
		}
	}

	/**
	 * Deletes the data for a certain uid from the local storage and
	 * unregisters all callback functions associated to it.
	 *
	 * This does NOT update nextmatch!
	 * Application code should use: egw(window).refresh(msg, app, id, "delete");
	 *
	 * Needs own state (#localStorage) plus dynamic `this.dataUnregisterUID(...)`
	 * dispatch (matching the original's `this.` self-call), hence self-capture.
	 *
	 * @param _uid is the uid which should be deleted.
	 */
	dataDeleteUID = ((self : DataStorage) => function (this : any, _uid : string) : void {
		if (typeof self.#localStorage[_uid] !== "undefined")
		{
			// Delete the element from the local storage
			delete self.#localStorage[_uid];

			// Unregister all callbacks for that uid
			self.dataUnregisterUID(_uid);
		}
	})(this);

	/**
	 * Force a refreash of the given uid from the server if known, and
	 * calls all associated callbacks.
	 *
	 * If the UID does not have any registered callbacks, it cannot be refreshed because the required
	 * execID and context are missing.
	 *
	 * Needs own state (#registeredCallbacks) plus dynamic `this.dataFetch(...)`
	 * dispatch (matching the original's `this.` call, to the "data" app-local
	 * module merged onto whichever instance called dataRefreshUID), hence
	 * self-capture.
	 *
	 * @param _uid is the uid which should be refreshed.
	 * @return True if the uid is known and can be refreshed, false if unknown and will not be refreshed
	 */
	dataRefreshUID = ((self : DataStorage) => function (this : any, _uid : string) : boolean {
		if (typeof self.#localStorage[_uid] === "undefined") return false;

		if(typeof self.#registeredCallbacks[_uid] !== "undefined" && self.#registeredCallbacks[_uid].length > 0)
		{
			var _execId = self.#registeredCallbacks[_uid][0].execId;
			// This widget ID MUST be a nextmatch, because the data call is to Etemplate\Widget\Nexmatch
			var nextmatchId = self.#registeredCallbacks[_uid][0].widgetId;
			var uidParts = _uid.split("::");
			var context : any = {
				"prefix":uidParts.shift()
			};
			var uid = uidParts.join("::");

			// find filters, even if context is not always from nextmatch, eg. caching uses it's a string context
			var filters = {};
			for(var i=0; i < self.#registeredCallbacks[_uid].length; i++)
			{
				var callback : any = self.#registeredCallbacks[_uid][i];
				if (typeof callback.context == 'object' &&
					typeof callback.context.self == 'object' &&
					typeof callback.context.self._filters == 'object')
				{
					filters = callback.context.self._filters;
					break;
				}
			}

			// need to send nextmatch filters too, as server-side will merge old version from request otherwise -
			// failure is already reported via the default error message/logging, nothing more to do here.
			this.dataFetch(_execId, {'refresh':uid}, filters, nextmatchId, false, context, [uid]).catch(() => {});

			return true;
		}
		return false;
	})(this);

	/**
	 * Search for exact UID string or regular expression and return widgets using it
	 *
	 * @param _uid is the uid which should be refreshed.
	 * @return UID: array of (nextmatch-)wigetIds
	 */
	dataSearchUIDs = (_uid : string|RegExp) : any =>
	{
		var matches : any = {};
		var f = (_uid : string) =>
		{
			if (typeof matches[_uid] == "undefined")
			{
				matches[_uid] = [];
			}
			if (typeof this.#registeredCallbacks[_uid] !== "undefined")
			{
				for(var n=0; n < this.#registeredCallbacks[_uid].length; ++n)
				{
					var callback : any = this.#registeredCallbacks[_uid][n];
					if (typeof callback.context != "undefined" &&
						typeof callback.context.self != "undefined" &&
						typeof callback.context.self._widget != "undefined")
					{
						matches[_uid].push(callback.context.self._widget);
					}
				}
			}
		};
		if (typeof _uid == "object" && (<any>_uid).constructor.name == "RegExp")
		{
			for(var uid in this.#localStorage)
			{
				if ((<RegExp>_uid).test(uid))
				{
					f(uid);
				}
			}
		}
		else if (typeof this.#localStorage[<string>_uid] != "undefined")
		{
			f(<string>_uid);
		}
		return matches;
	}

	/**
	 * Search for exact UID string or regular expression and call registered (nextmatch-)widgets refresh function with given _type
	 *
	 * This method is preferable over dataRefreshUID for app code, as it takes care of things like counters too.
	 *
	 * It does not do anything for _type="add"!
	 *
	 * Pure dynamic dispatch through `this.dataSearchUIDs(...)` - no own state
	 * needed directly - so a plain `function` field (no self-capture).
	 *
	 * @param _uid is the uid which should be refreshed.
	 * @param _type "delete", "edit", "update", not useful for "add"!
	 * @return (nextmatch-)wigets refreshed
	 */
	dataRefreshUIDs = function (this : any, _uid : string|RegExp, _type : string) : any[]
	{
		var uids = this.dataSearchUIDs(_uid);
		var widgets : any[] = [];
		var uids4widget : any[] = [];
		for(var uid in uids)
		{
			for(var n=0; n < uids[uid].length; ++n)
			{
				var widget : any = uids[uid][n];
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
}

egw.extend("data_storage", egw.MODULE_GLOBAL, (_app : string, _wnd : Window) => new DataStorage(_wnd));
