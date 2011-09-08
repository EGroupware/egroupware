/**
 * eGroupWare eTemplate2 - Class which contains a the data model
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage dataview
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
 */

"use strict"

/*egw:uses
	et2_core_inheritance;
	et2_core_common;
	et2_dataview_interfaces;
*/

var et2_dataview_dataProvider = Class.extend(et2_IDataProvider, {

	/**
	 * Creates this instance of the data provider. 
	 */
	init: function(_source, _total) {
		this._source = _source;
		this._total = _total;

		this._registeredRows = {};

		this._data = {};
		this._dataCount = 0;

		this._queue = {};
		this._queueSize = 0;

		this._stepSize = 25; // Count of elements which is loaded at once
		this._maxCount = 1000; // Maximum count before the elements are cleaned up

		var self = this;
		this._cleanupInterval = window.setInterval(function() {self._cleanup()},
			10 * 1000);
		this._queueFlushTimeout = null;
	},

	destroy: function() {
		// Destroy the cleanup timer callback
		window.clearInterval(this._cleanupInterval);

		// Destroy the _queueFlushTimeout
		if (this._queueFlushTimeout !== null)
		{
			window.clearTimeout(this._queueFlushTimeout);
		}
	},

	/**
	 * Returns the total count
	 */
	getCount: function() {
		return this._total;
	},

	registerDataRow: function(_dataRow, _idx) {
		// Make sure _idx is a int
		_idx = parseInt(_idx);

		if (typeof this._registeredRows[_idx] != "undefined")
		{
			et2_debug("warn", "Overriding data row for index " + _idx);
		}

		// Associate the given data row with that index
		this._registeredRows[_idx] = _dataRow;

		// Check whether an entry exists in the data array - if yes, call the
		// request immediately
		if (typeof this._data[_idx] != "undefined")
		{
			this._callUpdateData(_idx);
		}
		else
		{
			this._queueIndex(_idx);
		}
	},

	unregisterDataRow: function(_idx) {
		// Make sure _idx is a int
		_idx = parseInt(_idx);

		delete(this._registeredRows[_idx]);
	},

	/* ---- PRIVATE FUNCTIONS ---- */

	_queueIndex: function(_idx) {
		// Mark the index as queued
		if (typeof this._queue[_idx] == "undefined")
		{
			this._queue[_idx] = true;
			this._queueSize++;
		}

		if (this._queueSize > this._stepSize)
		{
			this._flushQueue();
		}
		else
		{
			// (Re)start the queue flush timer
			var self = this;
			this._stopFlushTimer();
			this._queueFlushTimeout = window.setTimeout(function() {
				self._queueFlushTimeout = null;
				self._flushQueue();
			}, 50);
		}
	},

	_flushQueue: function() {
		// Stop the flush timer if it is still active
		this._stopFlushTimer();

		// Mark all elements in a radius of this._stepSize / 2
		var marked = {};
		var r = Math.floor(this._stepSize / 2);
		for (var key in this._queue)
		{
			key = parseInt(key);

			var b = Math.max(0, key - r);
			var t = Math.min(key + r, this._total - 1);
			for (var i = b; i <= t; i ++)
			{
				marked[i] = true;
			}
		}

		// Reset the queue
		this._queue = {};
		this._queueSize = 0;

		// Create a list with start indices and counts
		var fetchList = [];
		var entry = null;
		var last = 0;

		// Get the int keys and sort the array numeric
		var arr = et2_arrayIntKeys(marked).sort(function(a,b){return a > b ? 1 : (a == b ? 0 : -1)});

		for (var i = 0; i < arr.length; i++)
		{
			if (i == 0 || arr[i] - last > 1)
			{
				if (entry)
				{
					fetchList.push(entry);
				}
				entry = {
					"startIdx": arr[i],
					"count": 1
				};
			}
			else
			{
				entry.count++;
			}

			last = arr[i];
		}

		if (entry)
		{
			fetchList.push(entry);
		}

		// Call the "getRows" callback
		this._source.getRows(fetchList, this._receiveData, this);
	},

	_receiveData: function(_data) {
		var time = (new Date).getTime();

		for (var key in _data)
		{
			// Make sure the key is a int
			key = parseInt(key);

			// Copy the data for the given index
			this._data[key] = {
				"data": _data[key],
				"timestamp": time
			};

			// Update the row associated to the index
			this._callUpdateData(key);
		}
	},

	_stopFlushTimer: function() {
		// Stop the queue flush timer
		if (this._queueFlushTimeout !== null)
		{
			window.clearTimeout(this._queueFlushTimeout);
		}
	},

	_callUpdateData: function(_idx) {
		if (typeof this._registeredRows[_idx] != "undefined")
		{
//			this._data[idx].timestamp = (new Date).getTime();
			this._registeredRows[_idx].updateData({
				"content": this._data[_idx].data
			});
		}
	},

	_cleanup: function() {
		// Delete all data rows which have not been accessed for more than 
		// "delta" ms (5 minutes) - this method does not ensure that _dataCount
		// gets below _maxCount!
		var delta = 5 * 60 * 1000;
		var now = (new Date).getTime();

		if (this._dataCount > this._maxCount)
		{
			for (var key in this._data)
			{
				var entry = this._data[key];

				if (now - entry.timestamp > delta)
				{
					delete(this._data[key]);
					this._dataCount--;
				}
			}
		}
	}

});


