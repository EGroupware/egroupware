/**
 * EGroupware eTemplate2
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage dataview
 * @link https://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright EGroupware GmbH 2011-2021
 */

/*egw:uses
	et2_dataview_view_aoi;

	egw_action.egw_keymanager;
*/

import {egw} from "../jsapi/egw_global";
import {et2_bounds} from "./et2_core_common";
import {et2_dataview_rowAOI} from "./et2_dataview_view_aoi";
import {egwActionObjectInterface} from "../egw_action/egw_action.js";
import {
	EGW_AO_SHIFT_STATE_BLOCK,
	EGW_AO_SHIFT_STATE_MULTI,
	EGW_AO_STATE_FOCUSED,
	EGW_AO_STATE_NORMAL,
	EGW_AO_STATE_SELECTED
} from "../egw_action/egw_action_constants.js";
import {egwBitIsSet, egwSetBit} from "../egw_action/egw_action_common.js";
import {Et2Dialog} from "./Et2Dialog/Et2Dialog";

/**
 * The selectioManager is internally used by the et2_dataview_controller class
 * to manage the row selection.
 * As the action system does not allow selection of entries which are currently
 * not in the dom tree, we have to manage this in this class. The idea is to
 * manage an external action object interface for each visible row and proxy all
 * state changes between an dummy action object, that does no selection handling,
 * and the external action object interface.
 *
 * @augments Class
 */
export class et2_dataview_selectionManager
{

	// Maximum number of rows we can safely fetch for selection
	// Actual selection may have more rows if we already have some
	public static readonly MAX_SELECTION = 1000;

	private _parent: any;
	private _indexMap: any;
	private _actionObjectManager: any;
	private _makeVisibleCallback: any;
	private _queryRangeCallback: any;
	private select_callback: null;

	private _context: any;
	_registeredRows: {};
	_focusedEntry: null;
	private _invertSelection: boolean;
	private _selectAll: boolean;
	private _inUpdate: boolean;
	private _total: number;
	private _children: any[];

	/**
	 * Constructor
	 *
	 * @param _parent
	 * @param _indexMap
	 * @param _actionObjectManager
	 * @param _queryRangeCallback
	 * @param _makeVisibleCallback
	 * @param _context
	 * @memberOf et2_dataview_selectionManager
	 */
	constructor(_parent, _indexMap, _actionObjectManager,
			_queryRangeCallback, _makeVisibleCallback, _context) 
	{

		// Copy the arguments
		this._parent = _parent;
		this._indexMap = _indexMap;
		this._actionObjectManager = _actionObjectManager;
		this._queryRangeCallback = _queryRangeCallback;
		this._makeVisibleCallback = _makeVisibleCallback;
		this._context = _context;

		// Attach this manager to the parent manager if one is given
		if (this._parent)
		{
			this._parent._children.push(this);
		}

		// Use our selection instead of object manager's to handle not-loaded rows
		if(_actionObjectManager)
		{
			this._actionObjectManager.getAllSelected = jQuery.proxy(
					this.getAllSelected, this
			);
		}

		// Internal map which contains all curently selected uids and their
		// state
		this._registeredRows = {};
		this._focusedEntry = null;
		this._invertSelection = false;
		this._selectAll = false;
		this._inUpdate = false;
		this._total = 0;
		this._children = [];

		// Callback for when the selection changes
		this.select_callback = null;
	}

	destroy( )
	{

		// If we have a parent, unregister from that
		if (this._parent)
		{
			var idx = this._parent._children.indexOf(this);
			this._parent._children.splice(idx, 1);
		}

		// Destroy all children
		for (var i = this._children.length - 1; i >= 0; i--)
		{
			this._children[i].destroy();
		}

		// Delete all still registered rows
		for (var key in this._registeredRows)
		{
			this.unregisterRow(key, this._registeredRows[key].tr);
		}
		this.select_callback = null;
	}

	clear( )
	{
		for (var key in this._registeredRows)
		{
			this.unregisterRow(key, this._registeredRows[key].tr);
			delete this._registeredRows[key];
		}
		if(this._actionObjectManager)
		{
			this._actionObjectManager.clear();
		}
		for (key in this._indexMap) {
			delete this._indexMap[key];
		}
		this._total = 0;
		this._focusedEntry = null;
		this._invertSelection = false;
		this._selectAll = false;
		this._inUpdate = false;
	}

	setIndexMap( _indexMap)
	{
		this._indexMap = _indexMap;
	}

	setTotalCount( _total)
	{
		this._total = _total;
	}

	registerRow( _uid, _idx, _tr, _links)
	{

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
			this._attachActionObject(entry, _tr, _uid, _links, _idx);
		}

		// Update the entry
		if(entry.ao) entry.ao._index;
		entry.idx = _idx;
		entry.tr = _tr;

		// Update the visible state of the _tr
		this._updateEntryState(entry, entry.state);
	}

	unregisterRow( _uid, _tr, _noDelete? : boolean)
	{

		// _noDelete defaults to false
		_noDelete = _noDelete ? true : false;

		if (typeof this._registeredRows[_uid] !== "undefined"
		    && this._registeredRows[_uid].tr === _tr)
		{
			this._inUpdate = true;

			// Don't leave focusedEntry
			// @ts-ignore
			if(this._focusedEntry !== null && this._focusedEntry.uid == _uid)
			{
				this.setFocused(_uid, false);
			}
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
	}

	resetSelection( )
	{
		this._invertSelection = false;
		this._selectAll = false;
		this._actionObjectManager.setAllSelected(false);

		for (var key in this._registeredRows)
		{
			this.setSelected(key, false);
		}
		for(var i = 0; i < this._children.length; i++)
		{
			this._children[i].resetSelection();
		}
	}

	setSelected( _uid, _selected)
	{
		this._selectAll = false;
		var entry = this._getRegisteredRowsEntry(_uid);
		this._updateEntryState(entry,
				egwSetBit(entry.state, EGW_AO_STATE_SELECTED, _selected));
	}

	getAllSelected()
	{
		var selected = this.getSelected();
		return selected.all || (selected.ids.length === this._total);
	}

	setFocused( _uid, _focused)
	{
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
			//console.log('et2_dataview_controller_selection::setFocused -> UID:'+_uid+' is focused by:'+this._actionObjectManager.name);
			var entry = this._focusedEntry = this._getRegisteredRowsEntry(_uid);
			this._updateEntryState(entry,
					egwSetBit(entry.state, EGW_AO_STATE_FOCUSED, true));
		}
	}

	selectAll( )
	{
		// Reset the selection
		this.resetSelection();

		this._selectAll = true;

		// Run as a range if there's less then the max
		if(egw.dataKnownUIDs(this._context._dataProvider.dataStorePrefix).length !== this._total &&
				this._total <= et2_dataview_selectionManager.MAX_SELECTION
		)
		{
			this._selectRange(0, this._total);
		}
		// Tell action manager to do all
		this._actionObjectManager.setAllSelected(true);

		// Update the selection
		for (var key in this._registeredRows)
		{
			var entry = this._registeredRows[key];
			this._updateEntryState(entry, entry.state);
		}

		this._selectAll = true;
	}

	getSelected( )
	{
		// Collect all currently selected ids
		var ids = [];
		for (var key in this._registeredRows)
		{
			if (egwBitIsSet(this._registeredRows[key].state, EGW_AO_STATE_SELECTED))
			{
				ids.push(key);
			}
		}

		// Push all events of the child managers onto the list
		for (var i = 0; i < this._children.length; i++)
		{
			ids = ids.concat(this._children[i].getSelected().ids);
		}

		// Return an array containing those ids
		// RB: we are currently NOT using "inverted"
		return {
			//"inverted": this._invertSelection,
			"all": this._selectAll,
			"ids": ids
		};
	}


	/** -- PRIVATE FUNCTIONS -- **/


	_attachActionObjectInterface( _entry, _tr, _uid)
	{
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
	}

	_getDummyAOI( _entry, _tr, _uid, _idx)
	{
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
		};

		// Connect the the two AOIs
		dummyAOI.doTriggerEvent = _entry.aoi.doTriggerEvent;

		// Implementation of the getDOMNode function, so that the event
		// handlers can be properly bound
		dummyAOI.getDOMNode = function () { return _tr; };

		return dummyAOI;
	}

	_attachActionObject( _entry, _tr, _uid, _links, _idx)
	{

		// Get the dummyAOI which connects the action object to the tr but
		// does no selection handling
		var dummyAOI = this._getDummyAOI(_entry, _tr, _uid, _idx);

		// Create an action object for the tr and connect it to a dummy AOI
		if(this._actionObjectManager)
		{
			if(this._actionObjectManager.getObjectById(_uid))
			{
				var state = _entry.state;
				this._actionObjectManager.getObjectById(_uid).remove();
				_entry.state = state;
			}
			_entry.ao = this._actionObjectManager.addObject(_uid, dummyAOI);
		}

		// Force context (actual widget) in here, it's the last place it's available
		_entry.ao._context = this._context;
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
			var total = self._total || Object.keys(self._indexMap).length;
			var max_index = Math.max.apply(Math,Object.keys(self._indexMap));
			// Get a reasonable number of iterations - not all
			var count = Math.max(1,Math.min(self._total,50));
			var element = null;
			var idx = _entry.idx;
			while(element == null && count > 0 && max_index > 0)
			{
				count--;
				element = getIndexAO(Math.max(0,
					Math.min(max_index, idx += _step)));
			}
			return element;
		}

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
	}

	_updateState( _uid, _state)
	{
		var entry = this._getRegisteredRowsEntry(_uid);

		this._updateEntryState(entry, _state);

		return entry;
	}

	_updateEntryState( _entry, _state)
	{

		if (this._selectAll)
		{
			_state |= EGW_AO_STATE_SELECTED;
		}
		else if (this._invertSelection)
		{
			_state ^= EGW_AO_STATE_SELECTED;
		}

		// Attach ao if not there, happens for rows loaded for selection, but
		// not displayed yet
		if(!_entry.ao && _entry.uid && this._actionObjectManager)
		{
			var _links = [];
			for (var key in this._registeredRows)
			{
				if(this._registeredRows[key].ao && this._registeredRows[key].ao.actionLinks)
				{
					_links = this._registeredRows[key].ao.actionLinks;
					break;
				}
			}
			if(_links.length)
			{
				this._attachActionObjectInterface(_entry, null, _entry.uid);
				this._attachActionObject(_entry, null, _entry.uid, _links, _entry.idx);
			}
		}

		// Update the state if it has changed
		if ((_entry.aoi && _entry.aoi.getState() !== _state) || _entry.state != _state)
		{
			this._inUpdate = true; // Recursion prevention

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

		// Update the visual state
		if (_entry.aoi && _entry.aoi.doSetState)
		{
			_entry.aoi.doSetState(_state);
		}

		// Update the state of the entry
		_entry.state = _state;
	}

	_getRegisteredRowsEntry( _uid)
	{
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
	}

	_handleSelect( _uid, _entry, _shift, _ctrl)
	{
		// If not "_ctrl" is set, reset the selection
		if (!_ctrl)
		{
			var top = this;
			while(top._parent !== null)
			{
				top = top._parent;
			}
			top.resetSelection();
			this._actionObjectManager.setAllSelected(false); // needed for hirachical stuff
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

		if(this.select_callback && typeof this.select_callback == "function")
		{
			this.select_callback.apply(this._context, arguments);
		}
	}

	_selectRange( _start, _stop)
	{
		// Contains ranges that are not currently in the index map and that have
		// to be queried
		var queryRanges = [];

		// Iterate over the given range and select the elements in the range
		// from _start to _stop
		var naStart = false;
		var s = Math.min(_start, _stop);
		var e = Math.max(_stop, _start);
		var RANGE_MAX = 50;
		var range_break = s + RANGE_MAX;
		for (var i = s; i <= e; i++)
		{
			if (typeof this._indexMap[i] !== "undefined" &&
			    this._indexMap[i].uid && egw.dataGetUIDdata(this._indexMap[i].uid))
			{
				// Add the range to the "queryRanges"
				if (naStart !== false)
				{
					queryRanges.push(et2_bounds(naStart, i - 1));
					naStart = false;
					range_break += RANGE_MAX;
				}

				// Select the element, unless flagged for exclusion
				// Check for no_actions flag via data
				var data = egw.dataGetUIDdata(this._indexMap[i].uid);
				if(data && data.data && !data.data.no_actions)
				{
					this.setSelected(this._indexMap[i].uid, true);
				}
			}
			else if (naStart === false)
			{
				naStart = i;
				range_break = naStart + RANGE_MAX;
			}
			else if(i >= range_break)
			{
				queryRanges.push(et2_bounds(naStart ? naStart : s, i - 1));
				naStart = i;
				range_break += RANGE_MAX;
			}
		}

		// Add the last range to the "queryRanges"
		if (naStart !== false)
		{
			queryRanges.push(et2_bounds(naStart, i - 1));
			naStart = false;
		}

		// Query all unknown ranges from the server
		if(queryRanges.length > 0)
		{
			this._query_ranges(queryRanges);
		}
	}

	_query_ranges(queryRanges)
	{
		var that = this;
		var record_count = 0;
		var range_index = 0;
		var range_count = queryRanges.length;
		var cont = true;
		var fetchPromise = new Promise(function(resolve)
		{
			resolve();
		});
		// Found after dialog loads
		var progressbar;

		let dialog = new Et2Dialog(this._context._widget.egw());
		dialog.transformAttributes({
			callback:
			// Abort the long task if they canceled the data load
				function() {cont = false},
			template: egw.webserverUrl + '/api/templates/default/long_task.xet',
			message: egw.lang('Loading'),
			title: egw.lang('please wait...'),
			buttons: [{
				button_id: Et2Dialog.CANCEL_BUTTON,
				label: egw.lang('cancel'),
				id: 'dialog[cancel]',
				image: 'cancel'
			}],
			width: 300
		});
		(this._context._widget.getDOMNode() || document.body).appendChild(dialog);
		dialog.updateComplete.then(() =>
		{
			dialog.template.DOMContainer.addEventListener('load', function()
			{
				// Get access to template widgets
				progressbar = dialog.template.widgetContainer.getWidgetById('progressbar');
			});
		});

		for(var i = 0; i < queryRanges.length; i++)
		{
			if(record_count + (queryRanges[i].bottom - queryRanges[i].top + 1) > that.MAX_SELECTION)
			{
				egw.message(egw.lang('Too many rows selected.<br />Select all, or less than %1 rows', that.MAX_SELECTION));
				break;
			}
			else
			{
				record_count += (queryRanges[i].bottom - queryRanges[i].top + 1);
				fetchPromise = fetchPromise.then((function()
				{
					// Check for abort
					if(!cont)
					{
						return;
					}

					return new Promise(function(resolve)
					{
						that._queryRangeCallback.call(that._context, this,
							function(_order)
							{
								for(var j = 0; j < _order.length; j++)
								{
									// Check for no_actions flag via data since entry isn't there/available
									var data = egw.dataGetUIDdata(_order[j]);
								if(!data || data && data.data && !data.data.no_actions)
								{
									var entry = this._getRegisteredRowsEntry(_order[j]);
									this._updateEntryState(entry,
											egwSetBit(entry.state, EGW_AO_STATE_SELECTED, true));
								}
							}
							progressbar.set_value(100*(++range_index/range_count));
							resolve();
						}, that);
					}.bind(this));
				}).bind(queryRanges[i]));
			}
		}
		fetchPromise.finally(function() {
			dialog.close();
		});
	}

}

