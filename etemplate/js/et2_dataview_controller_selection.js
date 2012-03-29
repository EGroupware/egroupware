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
	et2_dataview_view_aoi;

	egw_action.egw_keymanager;
*/

/**
 * The selectioManager is internally used by the et2_dataview_controller class
 * to manage the row selection.
 * As the action system does not allow selection of entries which are currently
 * not in the dom tree, we have to manage this in this class. The idea is to
 * manage an external action object interface for each visible row and proxy all
 * state changes between an dummy action object, that does no selection handling,
 * and the external action object interface.
 */
var et2_dataview_selectionManager = Class.extend({

	init: function (_indexMap, _actionObjectManager, _queryRangeCallback,
			_makeVisibleCallback, _context) {
		// Copy the arguments
		this._indexMap = _indexMap;
		this._actionObjectManager = _actionObjectManager;
		this._queryRangeCallback = _queryRangeCallback;
		this._makeVisibleCallback = _makeVisibleCallback;
		this._context = _context;

		// Internal map which contains all curently selected uids and their
		// state
		this._registeredRows = {};
		this._focusedEntry = null;
		this._invertSelection = false;
		this._inUpdate = false;
		this._total = 0;
	},

	setIndexMap: function (_indexMap) {
		this._indexMap = _indexMap;
	},

	setTotalCount: function (_total) {
		this._total = _total;
	},

	registerRow: function (_uid, _idx, _tr, _links) {

		// Get the corresponding entry from the registered rows array
		var entry = this._getRegisteredRowsEntry(_uid);

		// If the row has changed unregister the old one and do not delete
		// entry from the entry map
		if (entry.tr && entry.tr !== _tr) {
			this.unregisterRow(_uid, entry.tr, true);
		}

		// Create the AOI for the tr
		if (!entry.tr && _links)
		{
			this._attachActionObjectInterface(entry, _tr, _uid);
			this._attachActionObject(entry, _tr, _uid, _links, _idx)
		}

		// Update the entry
		entry.ao._index = entry.idx = _idx;
		entry.tr = _tr;

		// Update the visible state of the _tr
		this._updateEntryState(entry, entry.state);
	},

	unregisterRow: function (_uid, _tr, _noDelete) {

		// _noDelete defaults to false
		_noDelete = _noDelete ? true : false;

		if (typeof this._registeredRows[_uid] !== "undefined"
		    && this._registeredRows[_uid].tr === _tr)
		{
			this._inUpdate = true;

			this._registeredRows[_uid].tr = null;
			this._registeredRows[_uid].aoi = null;

			// Remove the action object from its container
			if (this._registeredRows[_uid].ao)
			{
				this._registeredRows[_uid].ao.remove();
				this._registeredRows[_uid].ao = null;
			}

			if (!_noDelete
			    && this._registeredRows[_uid].state === EGW_AO_STATE_NORMAL)
			{
				delete this._registeredRows[_uid];
			}

			this._inUpdate = false;
		}
	},

	resetSelection: function () {
		this._invertSelection = false;

		for (var key in this._registeredRows)
		{
			this.setSelected(key, false);
		}
	},

	setSelected: function (_uid, _selected) {
		var entry = this._getRegisteredRowsEntry(_uid);
		this._updateEntryState(entry,
				egwSetBit(entry.state, EGW_AO_STATE_SELECTED, _selected));
	},

	setFocused: function (_uid, _focused) {
		// Reset the state of the currently focused entry
		if (this._focusedEntry)
		{
			this._updateEntryState(this._focusedEntry,
					egwSetBit(this._focusedEntry.state, EGW_AO_STATE_FOCUSED,
							false));
			this._focusedEntry = null;
		}

		// Mark the new given uid as focused
		if (_focused)
		{
			var entry = this._focusedEntry = this._getRegisteredRowsEntry(_uid);
			this._updateEntryState(entry,
					egwSetBit(entry.state, EGW_AO_STATE_FOCUSED, true));
		}
	},

	selectAll: function () {
		// Reset the selection
		this.resetSelection();

		// Set the "invert selection" flag
		this._invertSelection = true;

		// Update the selection
		for (var key in this._registeredRows)
		{
			var entry = this._registeredRows[key];
			this._updateEntryState(entry, entry.state);
		}
	},

	getSelected: function () {
		// Collect all currently selected ids
		var ids = [];
		for (var key in this._registeredRows)
		{
			if (egwBitIsSet(this._registeredRows[key].state, EGW_AO_STATE_SELECTED))
			{
				ids.push(key);
			}
		}

		// Return an array containing those ids
		return {
			"inverted": this._invertSelection,
			"ids": ids
		}
	},


	/** -- PRIVATE FUNCTIONS -- **/


	_attachActionObjectInterface: function (_entry, _tr, _uid) {
		// Create the AOI which is used internally in the selection manager
		// this AOI is not connected to the AO, as the selection manager
		// cares about selection etc.
		_entry.aoi = new et2_dataview_rowAOI(_tr);
		_entry.aoi.setStateChangeCallback(
			function (_newState, _changedBit, _shiftState) {
				if (_changedBit === EGW_AO_STATE_SELECTED)
				{
					// Call the select handler
					this._handleSelect(
							_uid,
							_entry,
							egwBitIsSet(_shiftState, EGW_AO_SHIFT_STATE_BLOCK),
							egwBitIsSet(_shiftState, EGW_AO_SHIFT_STATE_MULTI)
						);
				}
			}, this);
	},

	_getDummyAOI: function (_entry, _tr, _uid, _idx) {
		// Create AOI
		var dummyAOI = new egwActionObjectInterface();
		var self = this;

		// Handling for Action Implementations updating the state
		dummyAOI.doSetState = function (_state) {
			if (!self._inUpdate)
			{
				// Update the "focused" flag
				self.setFocused(_uid, egwBitIsSet(_state, EGW_AO_STATE_FOCUSED));

				// Generally update the state
				self._updateState(_uid, _state);
			}
		};

		// Handle the "make visible" event, pass the request to the parent
		// controller
		dummyAOI.doMakeVisible = function () {
			self._makeVisibleCallback.call(self._context, _idx);
		}

		// Connect the the two AOIs
		dummyAOI.doTriggerEvent = _entry.aoi.doTiggerEvent;

		// Implementation of the getDOMNode function, so that the event
		// handlers can be properly bound
		dummyAOI.getDOMNode = function () { return _tr; };

		return dummyAOI;
	},

	_attachActionObject: function (_entry, _tr, _uid, _links, _idx) {

		// Get the dummyAOI which connects the action object to the tr but
		// does no selection handling
		var dummyAOI = this._getDummyAOI(_entry, _tr, _uid, _idx);

		// Create an action object for the tr and connect it to a dummy AOI
		_entry.ao = this._actionObjectManager.addObject(_uid, dummyAOI);
		_entry.ao.updateActionLinks(_links);
		_entry.ao._index = _idx;

		// Overwrite some functions like "traversePath", "getNext" and
		// "getPrevious"
		var self = this;

		function getIndexAO (_idx) {
			// Check whether the index is in the index map
			if (typeof self._indexMap[_idx] !== "undefined"
			    && self._indexMap[_idx].uid)
			{
				return self._getRegisteredRowsEntry(self._indexMap[_idx].uid).ao;
			}

			return null;
		}

		function getElementRelatively (_step) {
			return getIndexAO(Math.max(0,
				Math.min(self._total - 1, _entry.idx + _step)));
		};

		_entry.ao.getPrevious = function (_step) {
			return getElementRelatively(-_step);
		};

		_entry.ao.getNext = function (_step) {
			return getElementRelatively(_step);
		};

		_entry.ao.traversePath = function (_obj) {
			// Get the start and the stop index
			var s = Math.min(this._index, _obj._index);
			var e = Math.max(this._index, _obj._index);

			var result = [];

			for (var i = s; i < e; i++)
			{
				var ao = getIndexAO(i);
				if (ao)
				{
					result.push(ao);
				}
			}

			return result;
		};
	},

	_updateState: function (_uid, _state) {
		var entry = this._getRegisteredRowsEntry(_uid);

		this._updateEntryState(entry, _state);

		return entry;
	},

	_updateEntryState: function (_entry, _state) {

		// Update the state of the entry
		_entry.state = _state;

		if (this._invertSelection)
		{
			_state ^= EGW_AO_STATE_SELECTED;
		}

		// Update the state if it has changed
		if ((_entry.aoi && _entry.aoi.getState() !== _state) || _entry.state != _state)
		{
			this._inUpdate = true; // Recursion prevention

			// Update the visual state
			if (_entry.aoi)
			{
				_entry.aoi.setState(_state);
			}

			// Update the state of the action object
			if (_entry.ao)
			{
				_entry.ao.setSelected(egwBitIsSet(_state, EGW_AO_STATE_SELECTED));
				_entry.ao.setFocused(egwBitIsSet(_state, EGW_AO_STATE_FOCUSED));
			}

			this._inUpdate = false;

			// Delete the element if state was set to "NORMAL" and there is
			// no tr
			if (_state === EGW_AO_STATE_NORMAL && !_entry.tr)
			{
				delete this._registeredRows[_entry.uid];
			}
		}
	},

	_getRegisteredRowsEntry: function (_uid) {
		if (typeof this._registeredRows[_uid] === "undefined")
		{
			this._registeredRows[_uid] = {
				"uid": _uid,
				"idx": null,
				"state": EGW_AO_STATE_NORMAL,
				"tr": null,
				"aoi": null,
				"ao": null
			};
		}

		return this._registeredRows[_uid];
	},

	_handleSelect: function (_uid, _entry, _shift, _ctrl) {
		// If not "_ctrl" is set, reset the selection
		if (!_ctrl)
		{
			this.resetSelection();
		}

		// Mark the element that was clicked as selected
		var entry = this._getRegisteredRowsEntry(_uid);
		this.setSelected(_uid,
			!_ctrl || !egwBitIsSet(entry.state, EGW_AO_STATE_SELECTED));

		// Focus the element if shift is not pressed
		if (!_shift)
		{
			this.setFocused(_uid, true);
		}
		else if (this._focusedEntry)
		{
			this._selectRange(this._focusedEntry.idx, _entry.idx);
		}
	},

	_selectRange: function (_start, _stop) {
		// Contains ranges that are not currently in the index map and that have
		// to be queried
		var queryRanges = [];

		// Iterate over the given range and select the elements in the range
		// from _start to _stop
		var naStart = false;
		var s = Math.min(_start, _stop);
		var e = Math.max(_stop, _start);
		for (var i = s; i <= e; i++)
		{
			if (typeof this._indexMap[i] !== "undefined" &&
			    this._indexMap[i].uid)
			{
				// Add the range to the "queryRanges"
				if (naStart !== false) {
					queryRanges.push(et2_bounds(naStart, i - 1));
					naStart = false;
				}

				// Select the element
				this.setSelected(this._indexMap[i].uid, true);
			} else if (naStart === false) {
				naStart = i;
			}
		}

		// Add the last range to the "queryRanges"
		if (naStart !== false) {
			queryRanges.push(et2_bounds(naStart, i - 1));
			naStart = false;
		}

		// Query all unknown ranges from the server
		for (var i = 0; i < queryRanges.length; i++)
		{
			this._queryRangeCallback.call(this._context, queryRanges[i], 
				function (_order) {
					for (var j = 0; j < _order.length; j++)
					{
						this.setSelected(_order[j], true);
					}
				}, this);
		}
	}

});

