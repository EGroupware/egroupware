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
	 * The uid function generates a session-unique id for the current
	 * application by appending the application name to the given uid.
	 */
	function UID(_uid)
	{
		return _app + "::" + _uid;
	}

	function parseServerResponse(_result, _execId, _queriedRange, _filters,
			_lastModification, _callback, _context)
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

		if (_result.order && _result.data)
		{
			var order = [];

			// Load all data entries that have been sent
			for (var key in _result.data)
			{
				var uid = UID(key);
				egw.dataStoreUID(uid, _result.data[key]);
			}

			// Iterate over the order entries and check whether all uids are
			// available
			for (var i = 0; i < _result.order.length; i++)
			{
				// Calculate the actual uid and store it
				var uid = UID(_result.order[i]);
				order.push(uid);

				// Check whether the data for that row is loaded, if no, update
				// the "uidsMissing" variable
				if (!egw.dataHasUID(uid))
				{
					uidsMissing.push(_result.order[i]);
				}
			}

			// Call the callback function and pass the calculated "order" array
			// as well as the "total" count and the "timestamp" to the listener.
			if (_callback)
			{
				_callback.call(_context, {
					"order": order,
					"lastModification":
						typeof _result.lastModification === "undefined"
							? _lastModification : _result.lastModification,
					"total": _result.total,
					"readonlys": _result.readonlys
				});
			}

			// Fetch the missing uids
			if (uidsMissing.length > 0)
			{
				this.dataFetch(_execId, _queriedRange, _filters, null, null,
					uidsMissing, null, null);
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
		 * If order/data is null, this means that nothing has changed for the
		 * given range.
		 * The fetchRows function stores new data for the uid's inside the
		 * local data storage, the grid views are then capable of querying the
		 * data for those uids from the local storage using the
		 * "dataRegisterUID" function.
		 *
		 * @param execId is the execution context of the etemplate instance
		 * 	you're querying the data for.
		 * @param queriedRange is an object of the following form:
		 * 	{
		 * 		start: <START INDEX>,
		 * 		count: <COUNT OF ENTRIES>
		 * 	}
		 * The range always corresponds to the given filter settings.
		 * @param filters contains the filter settings. The filter settings are
		 * 	those which are crucial for the mapping between index and uid.
		 * @param knownRanges is an array of the above form and informs the
		 * 	server which ranges are already known to the client. If there are
		 * 	changes in the knownRanges (like new elements being inserted or old
		 * 	ones being removed). This parameter may be null in order to
		 * 	indicate that the client currently has no data for the given filter
		 * 	settings.
		 * @param lastModification is the last timestamp that was returned from
		 * 	the server and for which the client has data. It may be null in
		 * 	order to indicate, that the client currently has no data or needs a
		 * 	complete refresh.
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
		dataFetch: function (_execId, _queriedRange, _filters, _knownRanges,
				_lastModification, _uids, _callback, _context)
		{
			var request = egw.json(
				"etemplate_widget_nextmatch::ajax_get_rows::etemplate",
				[
					_execId,
					_queriedRange,
					_filters,
					_knownRanges,
					_lastModification,
					_uids
				],
				function(result) {
					parseServerResponse.call(
						this,
						result,
						_execId,
						_queriedRange,
						_filters,
						_lastModification,
						_callback,
						_context
					); 
				},
				this
			);
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
		 * @param _context is an optional parameter which can 
		 */
		dataRegisterUID: function (_uid, _callback, _context) {
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
						registeredCallbacks.splice(i, 1);
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
			return typeof this.localStorage[_uid] !== "undefined";
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
				for (var i = 0; i < this.registeredCallbacks.length; i++)
				{
					this.registeredCallbacks[i].callback.call(
						this.registeredCallbacks[i].context,
						_data
					);
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
		}

	};

});


