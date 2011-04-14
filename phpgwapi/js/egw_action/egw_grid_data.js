/**
 * eGroupWare egw_action framework - egw action framework
 *
 * @link http://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 * @version $Id$
 */

/*
uses
	egw_action,
	egw_action_common,
	egw_grid_columns;
*/

/** -- egwGridDataElement Class -- **/

var EGW_DATA_TYPE_RANGE = 0;
var EGW_DATA_TYPE_ELEMENT = 1;

/**
 * Contains the data (model) objects which retrieve data from the given source and
 * pass it to.
 *
 * @param object _parent the parent data element in which the new element is contained
 * @param object _columns the columns object which contains information about the data columns
 * @param object _readQueue is the queue object which queues data-fetching calls and executes these
 * 	asynchronously.
 * @param object _objectManager if this element is the root element (_parent is null),
 * 	specify the _objectManager in order to supply a parent object manager for that
 * 	element.
 */
function egwGridDataElement(_id, _parent, _columns, _readQueue, _objectManager)
{
	// Copy the passed arguments
	this.id = _id;
	this.parent = _parent;
	this.columns = _columns;
	this.readQueue = _readQueue;

	// Generate the action object associated to this element
	this.parentActionObject = _parent ? _parent.actionObject : _objectManager;
	this.actionObject = null;

	// If this is the root object, add the an root action object to the objectManager
	if (!_parent)
	{
		this.actionObject = this.parentActionObject.addObject(_id, null,
			EGW_AO_FLAG_IS_CONTAINER);
		this.readQueue.setDataRoot(this);
	}

	// Preset some parameters
	this.children = [];

	this.data = {};
	this.caption = false;
	this.iconUrl = false;
	this.iconSize = false;
	this.iconOverlay = [];
	this.opened = _parent == null;
	this.index = 0;
	this.canHaveChildren = false;
	this.type = egwGridViewRow;
	this.userData = null;
	this.updatedGrid = null;
	this.actionLinkGroups = {};
	this.group = false;
	this.capColTime = 0;
	this.rowClass = "";

	this.gridViewObj = null;
}

var EGW_GRID_DATA_UPDATE_TIME = 0;

egwGridDataElement.prototype.free = function()
{
	//TODO
}

egwGridDataElement.prototype.set_rowClass = function(_value)
{
	if (_value != this.rowClass)
	{
		this.rowClass = _value;
	}
}

egwGridDataElement.prototype.set_caption = function(_value)
{
	if (_value != this.caption)
	{
		this.capColTime = EGW_GRID_DATA_UPDATE_TIME;
		this.caption = _value;
	}
}

egwGridDataElement.prototype.set_iconUrl = function(_value)
{
	if (_value != this.iconUrl)
	{
		this.capColTime = EGW_GRID_DATA_UPDATE_TIME;
		this.iconUrl = _value;
	}
}

egwGridDataElement.prototype.set_iconOverlay = function(_value)
{
	if (!egwArraysEqual(_value, this.iconOverlay))
	{
		this.capColTime = EGW_GRID_DATA_UPDATE_TIME;
		this.iconOverlay = _value;
	}
}


egwGridDataElement.prototype.set_iconSize = function(_value)
{
	if (_value != this.iconSize)
	{
		this.capColTime = EGW_GRID_DATA_UPDATE_TIME;
		this.iconSize = _value;
	}
}

egwGridDataElement.prototype.set_opened = function(_value)
{
	this.opened = _value;
}

egwGridDataElement.prototype.set_canHaveChildren = function(_value)
{
	// Calculate the canHaveChildren value which would really be set
	var rv = _value && (this.children.length == 0);

	if (rv != this.canHaveChildren)
	{
		this.canHaveChildren = _value;
		this.capColTime = EGW_GRID_DATA_UPDATE_TIME;
	}
}

egwGridDataElement.prototype.set_group = function(_value)
{
	this.group = _value;

	var root = this.getRootElement();
	if (typeof root.actionLinkGroups[_value] != "undefined")
	{
		this.actionObject.updateActionLinks(root.actionLinkGroups[_value]);
	}
}

/**
 * Updates the column data. The column data is an object (used as associative array)
 * which may be of the following outline:
 *
 * 	{
 * 		"[col1_id]": "[data]",
 * 		"[col2_id]":
 * 			{
 * 				"data": "[data]",
 * 				"sortData": "[sortData]"
 * 			}
 * 	}
 *
 * "sortData" is data which is used for sorting instead of "data" when set.
 */
egwGridDataElement.prototype.set_data = function(_value)
{
	if (typeof _value == "object" && _value.constructor == Object)
	{
		// Update the column data specified in the value
		for (col_id in _value)
		{
			var val = _value[col_id];

			var data = "";
			var sortData = null;

			if (typeof val == "object")
			{
				data = typeof val.data != "undefined" ? val.data : "";
				sortData = typeof val.sortData != "undefined" ? val.sortData : null;
			}
			else
			{
				data = val;
			}

			// Set the data column timestamp - this is used inside the grid_view
			// row unit in order to only update data which has really changed
			var ts = 0;
			var newData = true;

			if (typeof this.data[col_id] != "undefined" && this.data[col_id].data == data)
			{
				ts = this.data[col_id].ts;
				newData = false;
			}

			if (newData)
			{
				ts = EGW_GRID_DATA_UPDATE_TIME;
			}

			this.data[col_id] = {
				"data": data,
				"sortData": sortData,
				"queued": false,
				"time": ts
			}
		}
	}
}

/**
 * Loads data into the GridData element. This function has two basic operating modes:
 * 
 * 1. If an array of objects is passed, the specified objects are added as children.
 * If a child node with the given ID already exists, it is updated.
 * The given data array must have the following form:
 * [
 * 	{
 * 		["entryType": (EGW_DATA_TYPE_ELEMENT | EGW_DATA_TYPE_RANGE)] // Defaults to EGW_DATA_TYPE_ELEMENT
 *		"type": "[Typeclass]" // Typeclass of the view-container: specifies the chars after the egwGridView-prefix. Defaults to "Row" which becomes "egwGridViewRow",
 * 		IF EGW_DATA_TYPE_ELEMENT:
 *			"id": [ Name of the element ]
 * 			"children": [ Objects which will be added to the children of the element ]
 *			ELEMENT DATA // See below
		IF EGW_DATA_TYPE_RANGE:
			"count": [Count of Elements], | "ids": [ Array with element ids ],
			"group": [ Action Link Group to which the generated objects should be added ]
			"prefix": "[String prefix which will be added to each element including their index in the list]"
 * 	}
 * ]
 *
 * 2. If a string or number is passed, inside an array, it is encapsulated into
 * an empty entry with that id
 *
 * 3. If an object with element dara is passed, the properties of the element will
 * be updated to the given values.
 *
 * {
 * 		"data": { COLUMN DATA OBJECT } // See "set_data" function
 * 		"caption": "[Caption]" // Used in the EGW_COL_TYPE_NAME_ICON_FIXED column
 * 		"iconUrl": "[IconUrl]" // Used in the EGW_COL_TYPE_NAME_ICON_FIXED column
 * 		"opened": [true|false] // Specifies whether the row is "opened" or "closed" (in trees)
 * 		"canHaveChildren": [true|false] // Specifies whether the row "open/close" button is displayed
 * }
 */
egwGridDataElement.prototype.loadData = function(_data, _doCallUpdate)
{
	// Store the current timestamp
	EGW_GRID_DATA_UPDATE_TIME = (new Date).getTime();

	if (typeof _doCallUpdate == "undefined")
	{
		_doCallUpdate = false;
	}

	if (_data.constructor == Array)
	{
		var virgin = this.children.length == 0;
		var last_element = null;

		for (var i = 0; i < _data.length; i++)
		{
			var entry = _data[i];

			// Single string entries are automatically converted to an entry
			// with that id
			if (typeof entry == String || typeof entry == Number)
			{
				entry = {
					"id": (entry + '') // The "+ ''" converts the entry to a string
				}
			}

			if (entry.constructor != Object)
			{
				continue;
			}

			var element = null;

			// Read the entry type and the element type (if they are set)
			var entryType = typeof entry.entryType == "number" ? entry.entryType :
				EGW_DATA_TYPE_ELEMENT;
			var type = (typeof entry.type == "string") && (typeof window["egwGridView" + entry.type] == "function") ?
				window["egwGridView" + entry.type] : egwGridViewRow;

			// Inserts a range of given dummy elements into the data tree
			if (entryType == EGW_DATA_TYPE_RANGE)
			{
				var prefix = typeof entry.prefix == "string" ? entry.prefix : "elem_";
				var canHaveChildren = typeof entry.canHaveChildren == "boolean" ? entry.canHaveChildren : false;
				var index = last_element ? last_element.index + 1 : 0;
				var group = typeof entry.group == "string" ? entry.group : false;
				var ids = [];

				if (typeof entry.ids != "undefined")
				{
					ids = entry.ids;
				}
				else if (typeof entry.count != "undefined")
				{
					var count = typeof entry.count == "number" && entry.count >= 0 ? entry.count : 1;

					for (var j = 0; j < count; j++)
					{
						ids.push(prefix + (index + j));
					}
				}

				for (var j = 0; j < ids.length; j++)
				{
					element = this.insertElement(index + j, ids[j]);
					element.type = type; // Type can only be set directly after creation
					element.canHaveChildren = canHaveChildren;
					if (group !== false)
					{
						element.set_group(group);
					}
				}
			}
			else if (entryType == EGW_DATA_TYPE_ELEMENT)
			{
				var id = typeof entry.id == "string" ? entry.id : "";
				element = null;

				if (!virgin && id)
				{
					element = this.getElementById(id, 1);
				}

				if (!element)
				{
					element = this.insertElement(false, id);
					element.type = type; // Type can only be set directly after creation
				}


				element.loadData(entry);
			}

			last_element = element;
		}
	}
	else
	{
		// Load all the data element for which a setter function exists
		egwActionStoreJSON(_data, this, true);

		// Load the child data
		if (typeof _data.children != "undefined" && _data.children.constructor == Array)
		{
			this.loadData(_data.children);
		}

		if (_doCallUpdate)
		{
			this.callBeginUpdate();
		}

		this.callGridViewObjectUpdate();
	}
}

/**
 * Resets all relevant data (the column data, icon and icon size) of the element
 * and triggers a gridViewObj update.
 */
egwGridDataElement.prototype.clearData = function()
{
	this.data = {};
	this.caption = false;
	this.iconUrl = false;
	this.iconSize = false;
	this.capColTime = 0;

	this.callGridViewObjectUpdate();
}

/**
 * Inserts a new element as child at the given position
 *
 * @param integer _index is the index at which the element will be inserted. If 
 * 	false, the element will be added to the end of the list.
 * @param string _id is the id of the newly created element
 * @returns the newly created element
 */
egwGridDataElement.prototype.insertElement = function(_index, _id)
{
	if (!_index)
	{
		_index = this.children.length;
	}
	else
	{
		_index = Math.max(0, Math.min(this.children.length, _index));
	}

	// Create the data element
	var element = new egwGridDataElement(_id, this, this.columns, this.readQueue,
		null);
	element.index = _index;

	// Create the action object
	var object = this.actionObject.insertObject(_index, _id, null, 0);
	object.data = element;

	// Link the two together
	element.actionObject = object;

	// As this element now at least has one child, "canHaveChildren" must be true
	this.canHaveChildren = true;

	// Insert the element at the given index
	this.children.splice(_index, 0, element);

	// Increment the index of all following elements
	for (var i = _index + 1; i < this.children.length; i++)
	{
		this.children[i].index++;
	}

	return element;
}

/**
 * Adds a new data element as child to the end of the list.
 *
 * @param string _id is the object identifier
 * @returns the newly created element
 */
egwGridDataElement.prototype.addElement = function(_id)
{
	return this.insertElement(false, _id);
}

egwGridDataElement.prototype.removeElement = function()
{
	//TODO
}

/**
 * Searches for the element with the given id and returns it. _depth specifies
 * the maximum recursion depth. May be omited.
 */
egwGridDataElement.prototype.getElementById = function(_id, _depth)
{
	if (typeof _depth == "undefined")
	{
		_depth = -1;
	}

	// Check whether this element is the searched one, if yes return it
	if (_id == this.id)
	{
		return this;
	}

	// Only continue searching in deeper levels, if the given depth is greater than
	// zero, or hasn't been defined and is therefore smaller than zero
	if (_depth < 0 || _depth > 0)
	{
		for (var i = 0; i < this.children.length; i++)
		{
			var elem = this.children[i].getElementById(_id, _depth - 1);

			if (elem)
			{
				return elem;
			}
		}
	}

	return null;
}

/**
 * Returns all children as array - this list will be used to set the item list
 * of the egwGridViewSpacer containers.
 */
egwGridDataElement.prototype.getChildren = function(_callback, _context)
{
	if (this.children.length > 0)
	{
		_callback.call(_context, this.children, true);
	}
	else if (this.canHaveChildren)
	{
		// If the children havn't been loaded yet, request them via queue call.
		this.readQueue.queueCall(this, EGW_DATA_QUEUE_CHILDREN, function() {
			_callback.call(_context, this.children, false);
		}, this);
	}
}

egwGridDataElement.prototype.hasColumn = function(_columnId, _returnData)
{
	// Get the column
	var col = this.columns.getColumnById(_columnId);
	var res = null;

	if (col)
	{
		res = false;

		// Check whether the queried column is the "EGW_COL_TYPE_NAME_ICON_FIXED" column
		if (col.type == EGW_COL_TYPE_NAME_ICON_FIXED)
		{
			if (this.caption !== false)
			{
				if (_returnData)
				{
					res = {
						"caption": this.caption,
						"iconUrl": this.iconUrl,
						"time": this.capColTime,
						"queued": false
					}
				}
				else
				{
					res = true;
				}
			}

			if (!_returnData && typeof (this.data[_columnId]) != "undefined" && this.data[_columnId].queued)
			{
				res = true;
			}
		}
		else if (col.type == EGW_COL_TYPE_CHECKBOX)
		{
			if (!_returnData)
			{
				res = true; // Tell the loader that the checkbox data is always available
			}
			else
			{
				var dataSet = (typeof this.data[_columnId] != "undefined");
				res = {
					"data": dataSet ? this.data[_columnId].data : 0,
					"time": dataSet ? this.data[_columnId].time : this.capColTime,
					"queued": false
				}
			}
		}
		else
		{
			// Check whether the column data of this column has been read,
			// if yes, return it.
			if (typeof this.data[_columnId] != "undefined")
			{
				if (_returnData)
				{
					if (typeof this.data[_columnId].data != "undefined")
					{
						res = this.data[_columnId];
					}
				}
				else
				{
					res = this.data[_columnId].queued;
				}
			}
			// Probably there is a default value specified for this column...
			else if (col["default"] !== EGW_COL_DEFAULT_FETCH)
			{
				if (_returnData)
				{
					res = col["default"];
				}
				else
				{
					res = true;
				}
			}
		}
	}

	return res;
}

/**
 * Returns the data for the given columns or incomplete data if those columns
 * are not available now. Those columns are loaded asynchronously in the background
 * and the GridViewObject is informed about this as soon as the new data has been
 * loaded.
 *
 * @param _columnIds is an array of column ids for which the data should be returned
 */
egwGridDataElement.prototype.getData = function(_columnIds)
{
	var queryList = [];
	var result = {};

	for (var i = 0; i < _columnIds.length; i++)
	{
		var res = this.hasColumn(_columnIds[i], true);

		// Either add the result to the result list (if the column data was available)
		// or add it to the query list.
		if (res !== null)
		{
			if (res !== false)
			{
				if (typeof res.queued != "undefined" && res.queued != false)
				{
					result[_columnIds[i]] = false;
				}
				else
				{
					result[_columnIds[i]] = res;
				}
			}
			else
			{
				queryList.push(_columnIds[i]);
			}
		}
	}

	// If one data entry hasn't been available, queue the request for this data
	// in the readQueue
	if (queryList.length > 0)
	{
		this.readQueue.queueCall(this, queryList);
	}

	return result;
}


/**
 * Calls the row object update function - checks whether the row object implements
 * this interface and whether it is set.
 */
egwGridDataElement.prototype.callGridViewObjectUpdate = function(_immediate)
{
	if (typeof _immediate == "undefined")
	{
		_immediate = false;
	}

	if (this.gridViewObj && typeof this.gridViewObj.doUpdateData == "function")
	{
		this.gridViewObj.doUpdateData(_immediate);
	}
}

egwGridDataElement.prototype.getDeepestOpened = function()
{
	if (this.opened && this.children.length > 0)
	{
		return this.children[this.children.length - 1].getDeepestOpened();
	}
	else
	{
		return this;
	}
}

/**
 * Returns whether this data element is a odd or even one
 */
egwGridDataElement.prototype.isOdd = function()
{
	return (this.index % 2) == 0; // Improve - old exact version needed way too much performance
}

/**
 * Function which is called by the grid view container in order to update the 
 * action object aoi.
 */
egwGridDataElement.prototype.setGridViewObj = function(_obj)
{
	this.gridViewObj = _obj;

	if (_obj && typeof _obj.getAOI == "function")
	{
		var aoi = _obj.getAOI();
		this.actionObject.setAOI(aoi);
		aoi.reconnectActions();
	}
	else
	{
		this.actionObject.setAOI(null);
	}
}

/**
 * Returns the root element
 */
egwGridDataElement.prototype.getRootElement = function()
{
	if (!this.parent)
	{
		return this;
	}
	else
	{
		return this.parent.getRootElement();
	}
}

/**
 * Returns the depth of this element in the document tree
 */
egwGridDataElement.prototype.getDepth = function()
{
	return (this.parent) ? (this.parent.getDepth() + 1) : 0;
}

/**
 * Calls the beginUpdate function of the grid associated to the grid view object
 */
egwGridDataElement.prototype.callBeginUpdate = function()
{
	if (this.gridViewObj)
	{
		var root = this.getRootElement();

		if (root.updatedGrid != this.gridViewObj.grid)
		{
			if (root.updatedGrid)
			{
				root.updatedGrid.endUpdate();
			}
			root.updatedGrid = this.gridViewObj.grid;
			root.updatedGrid.beginUpdate();
		}
	}
}

/**
 * Calls the end update function of the currently active updated grid
 */
egwGridDataElement.prototype.callEndUpdate = function()
{
	var root = this.getRootElement();

	if (root.updatedGrid)
	{
		root.updatedGrid.endUpdate();
		root.updatedGrid = null;
	}
}

/**
 * Deletes all child elements
 */
egwGridDataElement.prototype.empty = function()
{
	this.children = [];

	// Prevent all event handlers which are associated to elements in the read
	// queue from being called - those elements might no longer exist
	this.readQueue.flushEventQueue();
}

/**
 * Returns all parents in a list
 */
egwGridDataElement.prototype.getParentList = function(_lst)
{
	if (typeof _lst == "undefined")
	{
		_lst = [];
	}

	_lst.push(this);

	if (this.parent)
	{
		this.parent.getParentList(_lst);
	}

	return _lst;
}

/**
 * Requires a certain column to have all data localy - if this isn't the case,
 * the data is fetched from the server.
 *
 * @param string _colId specifies the column which should be loaded
 * @param function _callback is the function which should be called once all data
 * 	has been fetched.
 * @param object _context is the context in which the callback should be executed
 * @param object _loadIds is used internally to accumulate the object ids which
 * 	should be loaded.
 */
egwGridDataElement.prototype.requireColumn = function(_colId, _callback, _context,
	_loadElems)
{
	var outerCall = false;
	if (typeof _loadElems == "undefined")
	{
		_loadElems = {
			"elems": []
		}
		outerCall = true;
	}

	if (!outerCall && !this.hasColumn(_colId, false))
	{
		_loadElems.elems.push(this);
	}

	for (var i = 0; i < this.children.length; i++)
	{
		this.children[i].requireColumn(_colId, null, null, _loadElems);
	}

	// TODO: In which cases has this to be aborted?
	if (outerCall)
	{
		if (_loadElems.elems.length > 0)
		{
			this.readQueue.queueCall(_loadElems.elems, [_colId], function() {
				_callback.call(_context);
			}, null);
		}
		else
		{
			// If all elements had been loaded, postpone calling the callback function
			window.setTimeout(function() {
				_callback.call(_context);
			}, 0);
		}
	}
}


/**
 * Sorts the data element by the given column, the given sort direction and the
 * given sort mode - if the tree doesn't have all the column data loaded which is
 * needed for sorting, it first queries it from the server.
 *
 * @param string _colId is the id of the column
 * @param int _dir is one of EGW_COL_SORTMODE_*
 * @param int _mode is one of EGW_COL_SORTABLE_*
 * @param function _callback is a callback function which is called once the
 * 	sorting is done
 * @param 
 * @param boolean _outerCall is used internally, do not specify it
 */
egwGridDataElement.prototype.sortChildren = function(_colId, _dir, _mode, _callback,
	_context, _outerCall)
{
	if (typeof _outerCall == "undefined")
	{
		_outerCall = true;
	}

	// If this is the outer call of the function, we first have to make sure
	// that all data for the given column id is available
	if (_outerCall)
	{
		this.requireColumn(_colId, function() {
			// Call the actual sort part of this function by explicitly passing "false"
			// to the _outerCall parameter
			this.sortChildren(_colId, _dir, _mode, _callback, _context, false);

			_callback.call(_context);
		}, this);
	}
	else
	{
		// Select the sort function
		var sortFunc = null;
		switch (_mode) {
			case EGW_COL_SORTABLE_ALPHABETIC:
				sortFunc = egwGridData_sortAlphabetic;
				break;

			case EGW_COL_SORTABLE_NATURAL:
				sortFunc = egwGridData_sortNatural;
				break;

			case EGW_COL_SORTABLE_NUMERICAL:
				sortFunc = egwGridData_sortNumerical;
				break;
		}

		var col = this.columns.getColumnById(_colId);

		// Determine the mode multiplier which is used to sort the list in the
		// given direction.
		var dirMul = (_dir == EGW_COL_SORTMODE_ASC) ? 1 : -1;
		this.children.sort(function (a, b) {
			// Fetch the sortData or the regular data from the a and b element
			// and pass it to the sort function
			var aData = "";
			var bData = "";

			switch (col.type)
			{
				case EGW_COL_TYPE_DEFAULT:
					aData = a.data[_colId].sortData === null ? a.data[_colId].data :
						a.data[_colId].sortData;
					bData = b.data[_colId].sortData === null ? b.data[_colId].data :
						b.data[_colId].sortData;
					break;

				case EGW_COL_TYPE_NAME_ICON_FIXED:
					aData = a.caption;
					bData = b.caption;
					break;

				case EGW_COL_TYPE_CHECKBOX:
					aData = a.actionObject.getSelected() ? 1 : 0;
					bData = b.actionObject.getSelected() ? 1 : 0;
					break;
			}

			return sortFunc(aData, bData) * dirMul;
		});

		// Sort all children
		for (var i = 0; i < this.children.length; i++)
		{
			this.children[i].sortChildren(_colId, _dir, _mode, null, null, false);
		}

		// Sorting is done - call the callback function
		if (_callback)
		{
			_callback.call(_context);
		}
	}
}

function egwGridData_sortAlphabetic(a, b)
{
	return (a > b) ? 1 : -1;
}

function egwGridData_sortNumerical(a, b)
{
	aa = parseFloat(a);
	bb = parseFloat(b);

	return (aa > bb) ? 1 : -1;
}

/**
 * See http://my.opera.com/GreyWyvern/blog/show.dml/1671288
 */
function egwGridData_sortNatural(a, b)
{
	function chunkify(t)
	{
		var tz = [], x = 0, y = -1, n = 0, i, j;

		while (i = (j = t.charAt(x++)).charCodeAt(0))
		{
			var m = (i == 46 || (i >=48 && i <= 57));
			if (m !== n)
			{
				tz[++y] = "";
				n = m;
			}
			tz[y] += j;
		}

		return tz;
	}

	var aa = chunkify(a);
	var bb = chunkify(b);

	for (x = 0; aa[x] && bb[x]; x++)
	{
		if (aa[x] !== bb[x])
		{
			var c = Number(aa[x]), d = Number(bb[x]);
			if (c == aa[x] && d == bb[x])
			{
				return c - d;
			}
			else
			{
				return (aa[x] > bb[x]) ? 1 : -1;
			}
		}
	}
	return aa.length - bb.length;
}


/**
 * Returns the outermost parent of a set of elements - assume the following tree
 * where the elements marked with "x" are passed as array to this function:
 *
 * ROOT
 *  |- CHILD 1
 *     |- SUB_CHILD 1
 *     |- SUB_CHILD 2 (x)
 *  |- CHILD 2
 *     |- SUB_CHILD 1
 *         |- SUB_SUB_CHILD 1 (x)
 * The function should now return the "ROOT" elements as this is the outermost
 * parent the elements have in common.
 *
 * TODO: If I think about this, the function actually doesn't work like I'd like
 * 	it to behave...
 */
function egwGridData_getOutermostParent(_elements)
{
	var minElem = null;
	var minCnt = 0;

	for (var i = 0; i < _elements.length; i++)
	{
		var parents = _elements[i].getParentList();

		if (i == 0 || parents.length < minCnt)
		{
			minCnt = parents.length;
			minElem = _elements[i];
		}
	}

	return minElem ? (minElem.parent ? minElem.parent : minElem) : null;
}


/** - egwGridDataReadQueue -- **/

// Some internally used constants
var EGW_DATA_QUEUE_ELEM = 0;
var EGW_DATA_QUEUE_CHILDREN = 1;

// Count of elements which are dynamically added to the update list.
var EGW_DATA_QUEUE_PREFETCH_COUNT = 50;

// Timeout after which the queue events are no longer queued but the actual
// callback function is called.
var EGW_DATA_QUEUE_FLUSH_TIMEOUT = 200;

// Maximum count of elements in the queue after which the queue is flushed
var EGW_DATA_QUEUE_MAX_ELEM_COUNT = 100;

function egwGridDataQueue(_fetchCallback, _context)
{
	this.fetchCallback = _fetchCallback;
	this.context = _context;
	this.dataRoot = null;

	this.queue = [];
	this.queueColumns = [];
	this.timeoutId = 0;

	this.eventQueue = new egwEventQueue();
}

/**
 * Prevents handling the response function - e.g. if the data elements got emptied.
 */
egwGridDataQueue.prototype.flushEventQueue = function()
{
	this.eventQueue.flush();
}

egwGridDataQueue.prototype.setDataRoot = function(_dataRoot)
{
	this.dataRoot = _dataRoot;
}

/**
 * Adds an element to the queue and checks whether its element count is larger
 * than the one specified in EGW_DATA_QUEUE_MAX_ELEM_COUNT. If this is the case,
 * the queue is flushed and false is returned, otherwise true.
 */
egwGridDataQueue.prototype._queue = function(_obj, _last)
{
	this.timeoutId++;

	// Push the queue object onto the queue
	this.queue.push(_obj);

	if (_last && this.queue.length > EGW_DATA_QUEUE_MAX_ELEM_COUNT)
	{
		this.flushQueue(false);
		return false;
	}
	else
	{
		// Specify that the element data is queued
		if (_obj.type != EGW_DATA_QUEUE_CHILDREN)
		{
			for (var i = 0; i < this.queueColumns.length; i++)
			{
				if (typeof _obj.elem.data[this.queueColumns[i]] == "undefined")
				{
					_obj.elem.data[this.queueColumns[i]] = {
						"queued": true
					}
				}
			}
		}

		// Set the flush queue timeout
		if (_last)
		{
			var tid = this.timeoutId;
			var self = this;
			this.eventQueue.queueTimeout(this.flushQueue, this, [true],
				EGW_DATA_QUEUE_FLUSH_TIMEOUT, "dataQueueTimeout");
		}
	}

	return true;
}

egwGridDataQueue.prototype._accumulateQueueColumns = function(_columns)
{
	if (this.dataRoot.columns.columns.length > this.queueColumns.length)
	{
		// Merge the specified columns into the queueColumns variable
		for (var i = 0; i < _columns.length; i++)
		{
			if (this.queueColumns.indexOf(_columns[i]) == -1)
			{
				this.queueColumns.push(_columns[i]);
			}
		}
	}
}

/**
 * Queues the given element in the fetch-data queue.
 *
 * @param object/array _elems is a single element or an array of elements -
 * 	their data will be fetched
 * @param array _columns is an array of column ids which should be fetched. Those
 * 	columns will be accumulated over the queue calls. _columns may also take
 * 	the value EGW_DATA_QUEUE_CHILDREN in which case a request for the children
 * 	of the given element is queued.
 * @param function _callback is a callback function which will be called after
 * 	the data has been sent from the server.
 * @param object _context is the context in which the callback function will
 * 	be executed.
 */
egwGridDataQueue.prototype.queueCall = function(_elems, _columns, _callback, _context)
{
	if (typeof _callback == "undefined")
	{
		_callback = null;
	}
	if (typeof _context == "undefined")
	{
		_context = null;
	}

	if (!(_elems instanceof Array))
	{
		_elems = [_elems];
	}

	for (var i = 0; i < _elems.length; i++)
	{
		var last = i == _elems.length - 1;
		if (_columns === EGW_DATA_QUEUE_CHILDREN)
		{
			this._queue({
					"elem": _elems[i],
					"type": EGW_DATA_QUEUE_CHILDREN,
					"callback": last ? _callback : null,
					"context": _context
				}, last);
		}
		else
		{
			// Accumulate the queue columns ids
			this._accumulateQueueColumns(_columns);

			// Queue the element and search in the elements around the given one for
			// elements whose data isn't loaded yet.
			this._queue({
				"elem": _elems[i],
				"type": EGW_DATA_QUEUE_ELEM,
				"callback": last ? _callback : null,
				"context": _context
			}, last);
		}
	}
}

egwGridDataQueue.prototype._getQueuePlanes = function()
{
	var planes = [];
	var curPlane = null;

	for (var i = 0; i < this.queue.length; i++)
	{
		var elem = this.queue[i].elem;

		if (!curPlane || elem.parent != curPlane.parent)
		{
			curPlane = null;
			for (var j = 0; j < planes.length; j++)
			{
				if (planes[j].parent == elem.parent)
				{
					curPlane = planes[j];
					break;
				}
			}

			if (!curPlane)
			{
				curPlane = {
					"parent": elem.parent,
					"cnt": 0,
					"min": 0,
					"max": 0,
					"idx": 0,
					"done": false
				};
				planes.push(curPlane);
			}
		}

		if (curPlane.cnt == 0 || elem.index < curPlane.min)
		{
			curPlane.min = elem.index;
		}
		if (curPlane.cnt == 0 || elem.index > curPlane.max)
		{
			curPlane.max = elem.index;
		}

		curPlane.cnt++;
	}

	return planes;
}

egwGridDataQueue.prototype.prefetch = function(_cnt)
{
	var cnt = _cnt;
	var planes = this._getQueuePlanes();

	// Set the start indices
	for (var i = 0; i < planes.length; i++)
	{
		planes[i].idx = Math.max(0, Math.ceil(planes[i].min - _cnt / (2 * planes.length)));
	}

	// Add as many elements as specified to the prefetched elements
	var done = 0;
	var plane = 0;
	while (cnt > 0 && done < planes.length)
	{
		if (!planes[plane].done)
		{
			var idx = planes[plane].idx;

			if (!planes[plane].parent || idx == planes[plane].parent.children.length)
			{
				planes[plane].done = true;
				done++;
			}
			else
			{
				var hasData = true;
				var elem = planes[plane].parent.children[idx];
				for (var j = 0; j < this.queueColumns.length; j++)
				{
					if (!elem.hasColumn(this.queueColumns[j], false))
					{
						hasData = false;
						break;
					}
				}

				if (!hasData)
				{
					this._queue({
						"elem": elem,
						"type": EGW_DATA_QUEUE_ELEM,
						"callback": null,
						"context": null
					});
					cnt--;
				}

				planes[plane].idx++;
			}
		}

		// Go to the next plane
		plane = (plane + 1) % planes.length;
	}
}

egwGridDataQueue.prototype.empty = function()
{
	this.queue = [];
}

/**
 * Empties the queue and calls the fetch callback which cares about retrieving
 * the data from the server.
 */
egwGridDataQueue.prototype.flushQueue = function(_doPrefetch)
{
	var ids = [];

	if (_doPrefetch)
	{
		// Get the count of elements which will be dynamically added to the list, "prefetched"
		var prefetch_cnt = Math.min(EGW_DATA_QUEUE_PREFETCH_COUNT,
			Math.max(0, EGW_DATA_QUEUE_MAX_ELEM_COUNT - this.queue.length));

		this.prefetch(prefetch_cnt);
	}

	// Generate a list of element ids
	for (var i = 0; i < this.queue.length; i++)
	{
		var id = this.queue[i].elem.id;
		if (id == this.queue[i].elem.id)
		{
			if (this.queue[i].type == EGW_DATA_QUEUE_CHILDREN)
			{
				id = "[CHILDREN]" + id;
			}
		}

		ids.push(id);
	}

	// Check whether there actually are elements queued...
	if (ids.length > 0)
	{
		// Call the fetch callback and save a snapshot of the current queue
		var queue = this.queue;
		this.fetchCallback.call(this.context, ids, this.queueColumns, function(_data) {
			this.dataCallback(_data, queue);
		}, this);

		this.queue = [];
		this.queueColumns = [];
		this.timeoutId = 0;
	}
}

/**
 * Internal function which is called when the data is received from the fetchCallback.
 *
 * @param _data contains the data which has been retrieved by the fetchCallback
 * @param _queue is the list of elements which had been requested.
 */
egwGridDataQueue.prototype.dataCallback = function(_data, _queue)
{
	var rootData = [];
	try
	{
		// Iterate over the given data and check whether the data coresponds to one
		// of the queue elements - if yes, call their (probably) specified callback.
		// All elements for which no queue element can be found are added to the
		// "rootData" list, which is then loaded by the "dataRoot" data object.
		var i = 0;
		for (var i = 0; i < _data.length; i++)
		{
			var hasTarget = false;

			// Search for a queue element which belongs to the given data entry.
			if (_queue.length > 0 && typeof _data[i].id != "undefined")
			{
				var id = _data[i].id;

				for (var j = 0; j < _queue.length; j++)
				{
					if (_queue[j].elem.id == id)
					{
						_queue[j].elem.loadData(_data[i], true);

						// Call the queue object callback (if specified)
						if (_queue[j].callback)
						{
							_queue[j].callback.call(_queue[j].context);
						}

						// Delete this queue element
						_queue.splice(j, 1);

						hasTarget = true;
						break;
					}
				}
			}

			if (!hasTarget)
			{
				rootData.push(_data[i]);
			}
		}

		this.dataRoot.loadData(rootData, true);
	}
	finally
	{
		this.dataRoot.callEndUpdate();
	}
}

