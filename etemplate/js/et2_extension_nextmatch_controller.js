/**
 * eGroupWare eTemplate2 - Class which contains a the data model for nextmatch widgets
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2012
 * @version $Id$
 */

"use strict";

/*egw:uses
	et2_core_common;
	et2_core_inheritance;

	et2_dataview_view_row;
	et2_dataview_controller;
	et2_dataview_interfaces;

	et2_extension_nextmatch_actions; // Contains nm_action

	egw_data;
*/

var et2_nextmatch_controller = et2_dataview_controller.extend(
		et2_IDataProvider, {

	/**
	 * Initializes the nextmatch controller.
	 *
	 * @param _egw is the api instance
	 * @param _execId is the execId of the etemplate
	 * @param _widgetId is the id of the nextmatch-widget we are fetching data
	 * for.
	 * @param _grid is the grid the grid controller will be controlling
	 * @param _rowProvider is the nextmatch row provider instance.
	 * @param _objectManager is the parent object manager (if null, the object
	 * manager) will be created using
	 * @param _actionLinks contains the action links
	 * @param _actions contains the actions, may be null if an object manager
	 * is given.
	 */
	init: function (_egw, _execId, _widgetId, _grid, _rowProvider,
			_actionLinks, _objectManager, _actions) {

		// Copy the egw reference
		this.egw = _egw;

		// Initialize the action and the object manager
		if (!_objectManager)
		{
			this._initActions(_actions)
		}
		else
		{
			this._actionManager = null;
			this._objectManager = _objectManager;
		}

		// Call the parent et2_dataview_controller constructor
		this._super(_grid, this, this._rowCallback, this._linkCallback, this,
			this._objectManager);

		// Copy the given parameters
		this._actionLinks = _actionLinks
		this._execId = _execId;
		this._widgetId = _widgetId;
		this._rowProvider = _rowProvider;

		// We start with no filters
		this._filters = {};

		// Directly use the API-Implementation of dataRegisterUID and
		// dataUnregisterUID
		this.dataUnregisterUID = _egw.dataUnregisterUID;
	},

	destroy: function () {
		// If the actionManager variable is set, the object- and actionManager
		// were created by this instance -- clear them
		if (this._actionManager)
		{
			this._objectManager.remove();
			this._actionManager.remove();
		}

		this._super();
	},

	/**
	 * Updates the filter instance.
	 */
	setFilters: function (_filters) {
		// Update the filters, reset the "lastModification"
		this._filters = _filters;
		this._lastModification = null;
	},


	/** -- PRIVATE FUNCTIONS -- **/

	/**
	 * Initializes the action and the object manager.
	 */
	_initActions: function (_actions) {
		// Generate a uid for the action and object manager
		var uid = this.egw.uid();

		// Initialize the action manager and add some actions to it
		var gam = egw_getActionManager(this.egw.appName);
		this._actionManager = gam.addAction("actionManager", uid);
		this._actionManager.updateActions(_actions);

		// Set the default execute handler
		var self = this;
		this._actionManager.setDefaultExecute(function (_action, _senders, _target) {
			// Get the selected ids descriptor object
			var ids = self._selectionMgr.getSelected();

			// Call the nm_action function with the ids
			nm_action(_action, _senders, _target, ids);
		});

		// Initialize the object manager
		var gom = egw_getObjectManager(this.egw.appName);
		this._objectManager = gom.addObject(
				new egwActionObjectManager(uid, this._actionManager));
		this._objectManager.flags = this._objectManager.flags
				| EGW_AO_FLAG_DEFAULT_FOCUS | EGW_AO_FLAG_IS_CONTAINER;
	},

	/**
	 * Overwrites the inherited _destroyCallback function in order to be able
	 * to free the "rowWidget".
	 */
	_destroyCallback: function (_row) {
		// Destroy any widget associated to the row
		if (this.entry.widget)
		{
			this.entry.widget.free();
			this.entry.widget = null;
		}

		// Call the inherited function
		this._super.call(this, arguments);
	},

	/**
	 * Creates the actual data row.
	 *
	 * @param _data is an array containing the row data
	 * @param _tr is the tr into which the data will be inserted
	 * @param _idx is the index of the row
	 * @param _entry is the internal row datastructure of the controller, in
	 * this special case used to store the rowWidget reference, so that it can
	 * be properly freed.
	 */
	_rowCallback: function (_data, _tr, _idx, _entry) {
		// Let the row provider fill in the data row -- store the returned
		// rowWidget inside the _entry
		_entry.widget = this._rowProvider.getDataRow(
			{ "content": _data }, _tr, _idx);
	},

	/**
	 * Returns the action links for a given data row -- currently these are
	 * always the same links, as we controll enabled/disabled over the row
	 * classes.
	 */
	_linkCallback: function (_data, _idx, _uid) {
		return this._actionLinks;
	},


	/** -- Implementation of et2_IDataProvider -- **/


	dataFetch: function (_queriedRange, _callback, _context) {
		// Pass the fetch call to the API, multiplex the data about the
		// nextmatch instance into the call.
		this.egw.dataFetch(
				this._execId,
				_queriedRange,
				this._filters,
				this._widgetId,
				_callback,
				_context);
	},

	dataRegisterUID: function (_uid, _callback, _context) {
		this.egw.dataRegisterUID(_uid, _callback, _context, this._execId,
				this._widgetId);
	},

	dataUnregisterUID: function () {
		// Overwritten in the constructor
	}

});


