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

	var lastModification = null;

	/**
	 * The uid function generates a session-unique id for the current
	 * application by appending the application name to the given uid.
	 */
	function UID(_uid, prefix)
	{
		prefix = prefix ? prefix : _app;
		return prefix + "::" + _uid;
	}

	function parseServerResponse(_result, _callback, _context)
	{
		// Check whether the result is valid -- so "result" has to be an object
		// consting of "order" and "data"
		if (!(_result && typeof _result.order !== "undefined"
		    && typeof _result.data !== "undefined"))
		{
			egw.debug("error", "Invalid result for 'dataFetch'");
		}

		// The "uidsMissing" contains a list of missing uids.
		var uidsMissing = [];

		if (_result.lastModification)
		{
			lastModification = _result.lastModification;
		}

		if (_result.order && _result.data)
		{
			// Assemble the correct order uids
			for (var i = 0; i < _result.order.length; i++)
			{
				_result.order[i] = UID(_result.order[i], _context.prefix);
			}

			// Load all data entries that have been sent or delete them
			for (var key in _result.data)
			{
				var uid = UID(key, _context.prefix);
				if (_result.data[key] === null)
				{
					egw.dataDeleteUID(uid);
				}
				else
				{
					egw.dataStoreUID(uid, _result.data[key]);
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
					"rows": _result.rows
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
		 * @param execId is the execution context of the etemplate instance
		 * 	you're querying the data for.
		 * @param queriedRange is an object of the following form:
		 * 	{
		 * 		start: <START INDEX>,
		 * 		num_rows: <COUNT OF ENTRIES>
		 * 	}
		 * The range always corresponds to the given filter settings.
		 * @param filters contains the filter settings. The filter settings are
		 * 	those which are crucial for the mapping between index and uid.
		 * @param widgetId id with full namespace of widget
		 * @param knownUids is an array of uids already known to the client. 
		 *  This parameter may be null in order to indicate that the client 
		 *  currently has no data for the given filter settings.
		 * @param callback is the function that should get called, once the data
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
		 * @param context is the context in which the callback function will get
		 * 	called.
		 */
		dataFetch: function (_execId, _queriedRange, _filters, _widgetId,
				_callback, _context, _knownUids)
		{
			var lm = lastModification;
			if (_queriedRange["no_data"])
			{
				lm = 0xFFFFFFFFFFFF;
			}
			else if (_queriedRange["only_data"])
			{
				lm = 0;
			}

			var request = egw.json(
				"etemplate_widget_nextmatch::ajax_get_rows::etemplate",
				[
					_execId,
					_queriedRange,
					_filters,
					_widgetId,
					_knownUids ? _knownUids : egw.dataKnownUIDs(_context.prefix ? _context.prefix : _app),
					lm
				],
				function(result) {
					parseServerResponse(result, _callback, _context);
				},
				this,
				true
			);
			request.sendRequest();
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
			if (time - localStorage[uid].timestamp > MAX_AGE)
			{
				// Unregister all registered callbacks for that uid
				egw.dataUnregisterUID(uid);

				// Delete the data from the localStorage
				delete localStorage[uid];
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
				"context": _context ? _context : null
			});

			// Check whether the data is available -- if yes, immediately call
			// back the callback function
			if (typeof localStorage[_uid] !== "undefined")
			{
				// Update the timestamp and call the given callback function
				localStorage[_uid].timestamp = (new Date).getTime();
				_callback.call(_context, localStorage[_uid].data);
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
						self.dataFetch(_execId, {"start": 0, "num_rows": 0, "only_data": true},
							[], _widgetId, null, null, queue[hash].uids);

						// Delete the queue entry
						delete queue[hash];
					}, 10);
				}

				// Push the uid onto the queue
				queue[hash].uids.push(_uid.split("::").pop());
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
		 * @param uid is the uid for which should be checked whether it has some
		 * 	data.
		 */
		dataHasUID: function (_uid) {
			return typeof localStorage[_uid] !== "undefined";
		},

		/**
		 * Returns all uids that have the given prefix
		 * TODO: Improve this
		 */
		dataKnownUIDs: function (_prefix) {

			var result = [];

			for (var key in localStorage)
			{
				var parts = key.split("::");
				if (parts[0] === _prefix && localStorage[key].data)
				{
					result.push(parts[1]);
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
							_data
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
		 * @param uid is the uid which should be deleted.
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
		 * Force a refreash of the given uid from the server, and
		 * calls all associated callbacks
		 *
		 * @param uid is the uid which should be refreshed.
		 */
		dataRefreshUID: function (_uid) {
			if (typeof localStorage[_uid] === "undefined") return;

			this.dataFetch(_execId, {'refresh':_uid}, _filters, _widgetId,
					_callback, _context, _knownUids);
			
		}

	};

});


