/**
 * eGroupWare eTemplate2
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage dataview
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011-2012
 * @version $Id$
 */

/*egw:uses
	et2_core_common;
	et2_core_inheritance;

	et2_dataview_interfaces;
	et2_dataview_view_row;
*/

/**
 * The fetch timeout specifies the time during which the controller tries to
 * consolidate requests for rows.
 */
var ET2_DATAVIEW_FETCH_TIMEOUT = 50;

/**
 * The et2_dataview_controller class is the intermediate layer between a grid
 * instance and the corresponding data source. It manages updating the grid,
 * as well as inserting and deleting rows.
 */
var et2_dataview_controller = Class.extend({

	/**
	 * Constructor of the et2_dataview_controller, connects to the grid
	 * callback.
	 */
	init: function (_grid, _dataProvider, _rowCallback, _context)
	{
		// Copy the given arguments
		this._grid = _grid;
		this._dataProvider = _dataProvider;
		this._rowCallback = _rowCallback;
		this._rowContext = _context;

		// Initialize the "index map" which contains all currently displayed
		// containers hashed by the "index"
		this._indexMap = {};

		// "lastModified" contains the last timestap which was returned from the
		// server.
		this._lastModification = null;

		// Timer used for queing fetch requests
		this._queueTimer = null;

		// Array used for queing the requests
		this._queue = [];

		// Register the dataFetch callback
		this._grid.setDataCallback(this._gridCallback, this);
	},

	destroy: function () {

		this._clearTimer();

		this._super();
	},

	/**
	 * The update function queries the server for changes in the currently
	 * managed index range -- those changes are then merged into the current
	 * view without a complete rebuild of every row.
	 */
	update: function () {
		// Clear the fetch queue
		this._queue = [];
		this._clearTimer();

		// Get the currently visible range from the grid
		var range = this._grid.getIndexRange();

		// Force range.top and range.bottom to contain an integer
		if (range.top === false)
		{
			range.top = range.bottom = 0;
		}

		// Require that range from the server
		this._queueFetch(range.top, range.bottom - range.top + 1,
				this._lastModification !== null, true);
	},

	/**
	 * Rebuilds the complete grid.
	 */
	reset: function () {
		// Throw away all internal mappings and reset the timestamp
		this._indexMap = {};
		this._lastModification = null;

		// Clear the grid
		this._grid.clear();

		// Update the data
		this.update();
	},


	/* -- PRIVATE FUNCTIONS -- */


	_getIndexEntry: function (_idx) {
		// Create an entry in the index map if it does not exist yet
		if (typeof this._indexMap[_idx] === "undefined")
		{
			this._indexMap[_idx] = {
				"row": null,
				"uid": null
			};
		}

		// Always update the index of the entries before returning them. This is
		// neccessary, as when we remove the uid from an entry without row, its
		// index does not get updated any further
		this._indexMap[_idx]["idx"] = _idx;

		return this._indexMap[_idx];
	},

	/**
	 * Inserts a new data row into the grid. index and uid are derived from the
	 * given management entry. If the data for the given uid does not exist yet,
	 * a "loading" placeholder will be shown instead. The function will do
	 * nothing if there already is a row associated to the entry. This function
	 * will not re-insert a row if the entry already had a row.
	 *
	 * @param _entry is the management entry for the index the row will be
	 * displayed at.
	 * @param _update specifies whether the row should be updated if _entry.row
	 * already exists.
	 * @return true, if all data for the row has been available, false
	 * otherwise.
	 */
	_insertDataRow: function (_entry, _update) {
		// Abort if the entry already has a row but the _insert flag is not set
		if (_entry.row && !_update)
		{
			return true;
		}

		// Context used for the callback functions
		var ctx = {"self": this, "entry": _entry};

		// Create a new row instance, if it does not exist yet
		var createdRow = false;
		if (!_entry.row)
		{
			createdRow = true;
			_entry.row = new et2_dataview_row(this._grid);
			_entry.row.setDestroyCallback(this._destroyCallback, ctx);
		}

		// Load the row data if we have a uid for the entry
		this.hasData = false; // Gets updated by the _dataCallback
		if (_entry.uid)
		{
			// Register the callback / immediately load the data
			this._dataProvider.dataRegisterUID(_entry.uid, this._dataCallback,
					ctx);
		}

		// Display the loading "row prototype" if we don't have data for the row
		if (!this.hasData)
		{
			// Get the average height, the "-5" derives from the td padding
			var avg = Math.round(this._grid.getAverageHeight() - 5) + "px";
			var prototype = this._grid.getRowProvider().getPrototype("loading");
			$j("div", prototype).css("height", avg);
			var node = _entry.row.getJNode();
			node.empty();
			node.append(prototype.children());
		}

		// Insert the row into the table -- the same row must never be inserted
		// twice into the grid, so this function only executes the following
		// code only if it is a newly created row.
		if (createdRow)
		{
			this._grid.insertRow(_entry.idx, _entry.row);
		}

		return this.hasData;
	},

	/**
	 * Function which gets called by the grid when data is requested.
	 *
	 * @param _idxStart is the index of the first row for which data is
	 * requested.
	 * @param _idxEnd is the index of the last requested row.
	 */
	_gridCallback: function (_idxStart, _idxEnd) {

		var needsData = false;

		// Iterate over all elements the dataview requested and create a row
		// which indicates that we are currently loading data
		for (var i = _idxStart; i <= _idxEnd; i++)
		{
			var entry = this._getIndexEntry(i);

			// Insert the row for the entry -- do not update rows which are
			// already existing, as we do not have new data for those.
			if (!this._insertDataRow(entry, false) && needsData === false)
			{
				needsData = i;
			}
		}

		// Queue fetching that data range
		if (needsData !== false)
		{
			this._queueFetch(needsData, _idxEnd - needsData + 1, false);
		}
	},

	/**
	 * 
	 */
	_queueFetch: function (_start, _numRows, _refresh, _immediate) {

		// Force immediate to be false
		_immediate = _immediate ? _immediate : false;
//		_immediate = true;

		// Push the request onto the request queue
		this._queue.push({
				"start": _start,
				"num_rows": _numRows,
				"refresh": _refresh
		});

		// Start the queue timer, if this has not already been done
		if (this._queueTimer === null && !_immediate)
		{
			var self = this;
			this._queueTimer = window.setTimeout(function () {
				self._flushQueue();
			}, ET2_DATAVIEW_FETCH_TIMEOUT);
		}

		if (_immediate)
		{
			this._flushQueue();
		}
	},

	_flushQueue: function () {

		function consolidateQueries(_q) {
			var didConsolidation = false;

			var _new = [];
			var skip = {};

			for (var i = 0; i < _q.length; i++)
			{
				var r1 = et2_range(_q[i].start, _q[i].num_rows);

				var intersected = false;

				for (var j = i + 1; j < _q.length; j++)
				{
					if (skip[j])
					{
						continue;
					}

					var r2 = et2_range(_q[j].start, _q[j].num_rows);

					if (et2_rangeIntersect(r1, r2))
					{
						var n = et2_bounds(Math.min(r1.top, r2.top),
								Math.max(r1.botom, r2.bottom));
						_new.push({
							"start": n.top,
							"num_rows": n.bottom - n.top + 1,
							"refresh": _q[i].refresh
						});
						skip[i] = true;
						skip[j] = true;
						intersected = true;
					}
				}

				if (!intersected)
				{
					_new.push(_q[i]);
					skip[i] = true;
				}
			}

			if (didConsolidation) {
				return consolidateQueries(_new);
			}

			return _new;
		}

		// Clear any still existing timer
		this._clearTimer();

		// Calculate the refresh flag (refresh = false is stronger)
		var refresh = true;
		for (var i = 0; i < this._queue.length; i++)
		{
			refresh = refresh && this._queue[i].refresh;
		}

		// Extend all ranges into bottom direction, initialize the queries array
		for (var i = 0; i < this._queue.length; i++)
		{
			this._queue[i].num_rows += 10;
			this._queue[i].refresh = refresh;
		}

		// Consolidate all queries
		var queries = consolidateQueries(this._queue);

		// Execute all queries
		for (var i = 0; i < queries.length; i++)
		{
			// Sanitize the requests
			queries[i].start = Math.max(0, queries[i].start);
			queries[i].num_rows = Math.min(this._grid.getTotalCount(),
					queries[i].start + queries[i].num_rows) - queries[i].start;

			// Context used in the callback function
			var ctx = {
					"self": this,
					"start": queries[i].start,
					"count": queries[i].num_rows
			};

			// Call the callback
			this._dataProvider.dataFetch(queries[i], this._lastModification,
					this._fetchCallback, ctx);
		}

		// Flush the queue
		this._queue = [];
	},

	_clearTimer: function () {

		// Reset the queue timer upon destruction
		if (this._queueTimer)
		{
			window.clearTimeout(this._queueTimer);
			this._queueTimer = null;
		}

	},

	/**
	 * 
	 */
	_dataCallback: function (_data) {
		// Set the "hasData" flag
		this.self.hasData = true;

		// Call the row callback with the new data -- the row callback then
		// generates the row DOM nodes that will be inserted into the grid
		if (this.self._rowCallback)
		{
			// Remove everything from the current row
			this.entry.row.clear();

			// Fill the row DOM Node with data
			this.self._rowCallback.call(
				this.self._rowContext,
				_data,
				this.entry.row.getDOMNode(),
				this.entry.idx,
				this.entry
			);

			// Invalidate the current row entry
			this.entry.row.invalidate();
		}
	},

	/**
	 *
	 */
	_destroyCallback: function (_row) {
		// There is no further row connected to the entry
		this.entry.row = null;

		// Unregister the data callback
		this.self._dataProvider.dataUnregisterUID(this.entry.uid,
				this.self._dataCallback, null);
	},

	/**
	 * Returns an array containing "_count" index mapping entries starting from
	 * the index given in "_start".
	 */
	_getIndexMapping: function (_start, _count) {
		var result = [];

		for (var i = _start; i < _start + _count; i++)
		{
			result.push(this._getIndexEntry(i));
		}

		return result;
	},

	/**
	 * Updates the grid according to the new order. The function simply does the
	 * following: It iterates along the new order (given in _order) and the old
	 * order given in _idxMap. Iteration variables used are
	 *     a) i -- points to the current entry in _order
	 *     b) idx -- points to the current grid row that will be effected by
	 *        this operation.
	 *     c) mapIdx -- points to the current entry in _indexMap
	 * The following cases may occur:
	 *     a) The current entry in the old order has no uid or no row -- in that
	 *        case the row at the current position is simply updated,
	 *        the old pointer will be incremented.
	 *     b) The two uids differ -- insert a new row with the new uid, do not
	 *        increment the old pointer.
	 *     c) The two uids are the same -- increment the old pointer.
	 * In a last step all rows that are left in the old order are deleted. All
	 * newly created index entries are returned. This function does not update
	 * the internal mapping in _idxMap.
	 */
	_updateOrder: function (_start, _count, _idxMap, _order) {
		// The result contains the newly created index map entries which have to
		// be merged with the result
		var result = [];

		// Iterate over the new order
		var mapIdx = 0;
		var idx = _start;
		for (var i = 0; i < _order.length; i++, idx++)
		{
			var current = _idxMap[mapIdx];

			if (!current.row || !current.uid)
			{
				// If there is no row yet at the current position or the uid
				// of that entry is unknown, simply update the entry.
				current.uid = _order[i];
				current.idx = idx;

				// Only update the row, if it is displayed (e.g. has a "loading"
				// row displayed) -- this is needed for prefetching
				if (current.row)
				{
					this._insertDataRow(current, true);
				}

				mapIdx++;
			}
			else if (current.uid !== _order[i])
			{
				// Insert a new row at the new position
				var entry = {
					"idx": idx,
					"uid": _order[i],
					"row": null
				};
				this._insertDataRow(entry, true);

				// Remember the new entry
				result.push(entry);
			}
			else
			{
				// Do nothing, the uids do not differ, just update the index of
				// the element
				current.idx = idx;
				mapIdx++;
			}
		}

		// Delete as many rows as we have left
		for (var i = mapIdx; i < _idxMap.length; i++)
		{
			this._grid.deleteRow(idx);
		}

		return result;
	},

	_mergeResult: function (_newEntries, _invalidStartIdx, _diff) {

		if (_newEntries.length > 0 || _diff > 0)
		{
			// Create a new index map
			var newMap = {};

			// Insert all new entries into the new index map
			for (var i = 0; i < _newEntries.length; i++)
			{
				newMap[_newEntries[i].idx] = _newEntries[i];
			}

			// Insert all old entries that have a row into the new index map
			// while adjusting their indices
			for (var key in this._indexMap)
			{
				// Get the corresponding index entry
				var entry = this._indexMap[key];

				// Only keep index entries which are currently displayed
				if (entry.row)
				{
					// Calculate the new index -- if rows were deleted, we'll
					// have to adjust the index
					var newIdx = entry.idx >= _invalidStartIdx
							? entry.idx - _diff : entry.idx;
					entry.idx = key;
					newMap[newIdx] = entry;
				}
			}

			// Make the new index map the current index map
			this._indexMap = newMap;
		}

	},

	_fetchCallback: function (_response) {
		// Do nothing if _response.order evaluates to false
		if (!_response.order)
		{
			return;
		}

		// Copy the last modification
		this.self._lastModification = _response.lastModification;

		// Make sure _response.order.length is not longer than the requested
		// count
		var order = _response.order.splice(0, this.count);

		// Get the current index map for the updated region
		var idxMap = this.self._getIndexMapping(this.start, this.count);

		// Update the grid using the new order. The _updateOrder function does
		// not update the internal mapping while inserting and deleting rows, as
		// this would move us to another asymptotic runtime level.
		var res = this.self._updateOrder(this.start, this.count, idxMap, order);

		// Merge the new indices, update all indices with rows that were not
		// affected and invalidate all indices if there were changes
		this.self._mergeResult(res, this.start + order.length,
				idxMap.length - order.length);

		// Update the total element count in the grid
		this.self._grid.setTotalCount(_response.total);
	}

});

