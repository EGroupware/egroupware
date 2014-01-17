/**
 * EGroupware eTemplate2 - Class which contains a the data model for nextmatch widgets
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

/**
 * @augments et2_dataview_controller
 */
var et2_nextmatch_controller = et2_dataview_controller.extend(et2_IDataProvider,
{
	/**
	 * Initializes the nextmatch controller.
	 *
	 * @param _parentController is the parent nextmatch controller instance
	 * @param _egw is the api instance
	 * @param _execId is the execId of the etemplate
	 * @param _widget is the nextmatch-widget we are fetching data for.
	 * @param _grid is the grid the grid controller will be controlling
	 * @param _rowProvider is the nextmatch row provider instance.
	 * @param _objectManager is the parent object manager (if null, the object
	 * manager) will be created using
	 * @param _actionLinks contains the action links
	 * @param _actions contains the actions, may be null if an object manager
	 * is given.
	 * @memberOf et2_nextmatch_controller
	 */
	init: function (_parentController, _egw, _execId, _widget, _parentId,
			_grid, _rowProvider, _actionLinks, _objectManager, _actions) {

		// Copy the egw reference
		this.egw = _egw;

		// Keep a reference to the widget
		this._widget = _widget;

		// Initialize the action and the object manager
		if (!_objectManager)
		{
			this._initActions(_actions);
		}
		else
		{
			this._actionManager = null;
			this._objectManager = _objectManager;
		}
		// Add our selection callback to selection manager
		var self = this;
		this._objectManager.setSelectedCallback = function() {self._selectCallback.apply(self,[this,arguments]);};

		// Call the parent et2_dataview_controller constructor
		this._super(_parentController, _grid, this, this._rowCallback,
			this._linkCallback, this, this._objectManager);

		// Copy the given parameters
		this._actionLinks = _actionLinks;
		this._execId = _execId;
		this._widgetId = _widget.id;
		this._parentId = _parentId;
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

	getObjectManager: function () {
		return this._objectManager;
	},


	/** -- PRIVATE FUNCTIONS -- **/

	/**
	 * Initializes the action and the object manager.
	 */
	_initActions: function (_actions) {
		// Generate a uid for the action and object manager
		var uid = this.egw.uid();

		if(_actions == null) _actions = [];

		// Initialize the action manager and add some actions to it
		// To work around a bug in action system we call global actionManger
		// of an application NOT just appname, but prefix it with "__"
		// If it is called just appname, actionLinks of actions with id
		// of an application fetch that applications menu as children,
		// if that applications tab is open in toplevel window.
		var gam = egw_getActionManager('__'+this.egw.appName);
		this._actionManager = gam.addAction("actionManager", uid);
		this._actionManager.updateActions(_actions, this.egw.appName);
		var data = this._actionManager.data;
		if (data == 'undefined' || !data)
		{
			data = {};
		}
		data.nextmatch = this._widget;
		this._actionManager.set_data(data);

		// Set the default execute handler
		var self = this;
		this._actionManager.setDefaultExecute(function (_action, _senders, _target) {
			// Get the selected ids descriptor object
			var ids = self._selectionMgr.getSelected();

			// Pass a reference to the actual widget
			if (typeof _action.data == 'undefined' || !_action.data) _action.data = {};
			_action.data.nextmatch = self._widget;

			// Call the nm_action function with the ids
			nm_action(_action, _senders, _target, ids);
		});

		// Set the 'Select All' handler
		var select_all = this._actionManager.getActionById('select_all');
		if(select_all)
		{
			select_all.set_onExecute(jQuery.proxy(function(action, selected) {
				this._selectionMgr.selectAll();
			}, this));
		}

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
			{ "content": _data }, _tr, _idx, this);
	},

	/**
	 * Returns the names of action links for a given data row -- currently these are
	 * always the same links, as we controll enabled/disabled over the row
	 * classes, unless the row UID is "", then it's an 'empty' row.
	 *
	 * The empty row placeholder can still have actions, but nothing that requires
	 * an actual UID.
	 *
	 * @TODO: Currently empty row is just add, need to actually filter somehow.  Here
	 * 	might not be the right place.
	 *
	 * @param _data Object The data for the row
	 * @param _idx int The row index
	 * @param _uid String The row's ID
	 *
	 * @return Array List of action names that valid for the row
	 */
	_linkCallback: function (_data, _idx, _uid) {
		if(_uid.trim() != "")
		{
			return this._actionLinks;
		}

		// No UID, so return a filtered list of actions that doesn't need a UID
		var links = [];
		try {
			links = typeof this._widget.options.settings.placeholder_actions != 'undefined' ? this._widget.options.settings.placeholder_actions : ["add"];
		} catch (e) {
		}

		return links;
	},


	/**
	 * Overridden from the parent to also process any additional data that
	 * the data source adds, such as readonlys and additonal content.
	 * For example, non-numeric IDs in rows are added to the content manager
	 */
	_fetchCallback: function (_response) {
		var nm = this.self._widget;
		if(!nm)
		{
			// Nextmatch either not connected, or it tried to destroy this
			// but the server returned something
			return;
		}
		// Readonlys
		// Other stuff
		for(var i in _response.rows)
		{
			if(jQuery.isNumeric(i)) continue;
			if(i == 'sel_options')
			{
				var mgr = nm.getArrayMgr(i);
				for(var id in _response.rows.sel_options)
				{
					mgr.data[id] = _response.rows.sel_options[id];
					var select = nm.getWidgetById(id);
					if(select && select.set_select_options)
					{
						select.set_select_options(_response.rows.sel_options[id]);
					}
				}
			}
			else
			{
				var mgr = nm.getArrayMgr('content');
				mgr.data[i] = _response.rows[i];
			}
		}

		// Call the inherited function
		this._super.apply(this, arguments);
	},

	/**
	 * Execute the select callback when the row selection changes
	 */
	_selectCallback: function(action,senders)
	{
		if(typeof senders == "undefined")
		{
			senders = [];
		}
		if(!this._widget) return;
		this._widget.onselect.call(this._widget, action,senders);
	},

	/** -- Implementation of et2_IDataProvider -- **/


	dataFetch: function (_queriedRange, _callback, _context) {

		// Merge the parent id into the _queriedRange if it is set
		if (this._parentId !== null)
		{
			_queriedRange["parent_id"] = this._parentId;
		}

		// Pass the fetch call to the API, multiplex the data about the
		// nextmatch instance into the call.
		this.egw.dataFetch(
				this._widget.getInstanceManager().etemplate_exec_id || this._execId,
				_queriedRange,
				this._filters,
				this._widgetId,
				_callback,
				_context);
	},

	dataRegisterUID: function (_uid, _callback, _context) {
		this.egw.dataRegisterUID(_uid, _callback, _context,
			this._widget.getInstanceManager().etemplate_exec_id || this._execId,
			this._widgetId
		);
	},

	dataUnregisterUID: function () {
		// Overwritten in the constructor
	}

});


