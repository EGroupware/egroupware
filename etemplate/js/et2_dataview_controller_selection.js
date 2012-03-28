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

/**
 * The selectioManager is internally used by the et2_dataview_controller class
 * to manage the row selection.
 */
var et2_dataview_selectionManager = Class.extend({

	init: function (_indexMap) {
		// Copy the reference to the index map
		this._indexMap = _indexMap;

		// Internal map which contains all curently selected uids
		this._selectedUids = {};

		// Controls whether the selection is currently inverted (e.g. after
		// selectAll)
		this._invertSelection = false;
	},

	setIndexMap: function (_indexMap) {
		this._indexMap = _indexMap;
	},

	/**
	 * Resets the selection state of all selected elements.
	 */
	resetSelection: function () {
		// Iterate over the index map and reset the selection flag of all rows
		for (var key in this._indexMap)
		{
			if (this._indexMap[key].ao)
			{
				this._indexMap[key].ao.setSelected(false);
			}
		}

		// Reset the internal representation and the inversion flag
		this._selectedUids = {};
		this._invertSelection = false;
	},

	/**
	 * Marks the given uid as selected.
	 */
	uidAddSelection: function (_uid) {
		this._selectedUids[_uid] = true;
	},

	/**
	 * Removes the selection from the given uid.
	 */
	uidRemoveSelection: function (_uid) {
		delete this._selectedUids[_uid];
	},

	/**
	 * Returns whether the given uid is selected or not.
	 */
	uidIsSelected: function (_uid) {
		return (!this._invertSelection) ===
				(this._selectedUids[_uid] ? true : false);
	},

	/**
	 * Hooks into the given action object / action object interface in order
	 * to handle selection.
	 */
	hook: function (_ao, _aoi, _uid) {

		// Hook into the action object state change handler, as we need
		// our own selection code
		// Big TODO: Remove the old selection handling code from
		// egwAction once it is no longer used outside et2 applications
		_aoi.setStateChangeCallback(
			function (_newState, _changedBit, _shiftState) {

				var selected = egwBitIsSet(_newState, EGW_AO_STATE_SELECTED);

				// Deselect all other objects inside this container, if the "MULTI" shift-
				// state is not set
				if (!egwBitIsSet(_shiftState, EGW_AO_SHIFT_STATE_MULTI))
				{
					this.resetSelection();
				}

				// Update the internal status of the uid
				if (selected)
				{
					this.uidAddSelection(_uid);
				}
				else
				{
					this.uidRemoveSelection(_uid);
				}

				_ao.setSelected(selected);

				return _newState;

			}, this);

		// Set the selection state of the ao
		_ao.setSelected(this.uidIsSelected(_uid));
	}

});

