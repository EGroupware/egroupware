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
	this.opened = _parent == null;
	this.index = 0;
	this.canHaveChildren = false;
	this.type = egwGridViewRow;
	this.userData = null;

	this.gridViewObj = null;
}

egwGridDataElement.prototype.free = function()
{
	//TODO
}

egwGridDataElement.prototype.set_caption = function(_value)
{
	this.caption = _value;
}

egwGridDataElement.prototype.set_iconUrl = function(_value)
{
	this.iconUrl = _value;
}

egwGridDataElement.prototype.set_opened = function(_value)
{
	this.opened = _value;
}

egwGridDataElement.prototype.set_canHaveChildren = function(_value)
{
	this.canHaveChildren = _value && (this.children.length == 0);
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

			this.data[col_id] = {
				"data": data,
				"sortData": sortData
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
 *		"type": "[Typeclass]" // Typeclass of the view-container: specifies the chars after the egwGridView-prefix. Defaults to "Row" which becomes "egwGridViewRow"
 * 		IF EGW_DATA_TYPE_ELEMENT:
 * 			"children": [ Objects which will be added to the children of the element ]
 *			ELEMENT DATA // See below
		IF EGW_DATA_TYPE_RANGE:
			"count": [Count of Elements],
			"prefix": "[String prefix which will be added to each element including their index in the list]"
 * 	}
 * ]
 *
 * 2. If an object with element dara is passed, the properties of the element will
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
egwGridDataElement.prototype.loadData = function(_data)
{
	if (_data.constructor == Array)
	{
		var virgin = this.children.length == 0;
		var last_element = null;

		for (var i = 0; i < _data.length; i++)
		{
			var entry = _data[i];

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
				var count = typeof entry.count == "number" && entry.count >= 0 ? entry.count : 1;
				var prefix = typeof entry.prefix == "string" ? entry.prefix : "elem_";
				var index = last_element ? last_element.index + 1 : 0;

				for (var j = 0; j < count; j++)
				{
					var id = prefix + (index + j);
					element = this.insertElement(index + j, id);
					element.type = type; // Type can only be set directly after creation
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

		this.gridViewObj.callGridViewObjectUpdate();
	}
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
			var elem = this.children.getElementById(_id, _depth - 1);

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
		_callback.call(_context, this.children);
	}
	else if (this.canHaveChildren)
	{
		// If the children havn't been loaded yet, request them via queue call.
		this.readQueue.queue(this, EGW_DATA_QUEUE_CHILDREN, function() {
			_callback.call(_context, this.children);
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
						"iconUrl": this.iconUrl
					}
				}
				else
				{
					res = true;
				}
			}
		}
		else
		{
			// Check whether the column data of this column has been read,
			// if yes, return it.
			if (typeof this.data[_columnIds] != "undefined")
			{
				if (_returnData)
				{
					res = this.data[_columnIds].data;
				}
				else
				{
					res = true;
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
		res = this.hasColumn(_columnIds[i], true);

		// Either add the result to the result list (if the column data was available)
		// or add it to the query list.
		if (res !== null)
		{
			if (res !== false)
			{
				result[_columnIds[i]] = res;
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
		this.readQueue.queue(this, queryList);
	}

	return result;
}


/**
 * Calls the row object update function - checks whether the row object implements
 * this interface and whether it is set.
 */
egwGridDataElement.prototype.callGridViewObjectUpdate = function()
{
	if (this.gridViewObj && typeof this.gridViewObj.doUpdateData == "function")
	{
		this.gridViewObj.doUpdateData();
	}
}

/**
 * Returns the absolute index of this element
 */
egwGridDataElement.prototype.getTotalIndex = function()
{
	var idx = this.index;

	if (this.parent && this.parent.opened)
	{
		idx += this.parent.getTotalIndex();
	}

	return idx;
}

/**
 * Returns whether this data element is a odd or even one
 */
egwGridDataElement.prototype.isOdd = function()
{
	return (this.getTotalIndex() % 2) == 0;
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
		this.actionObject.setAOI(_obj.getAOI());
	}
	else
	{
		this.actionObject.setAOI(null);
	}
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
egwGridDataQueue.prototype._queue = function(_obj)
{
	this.queue.push(_obj);

	if (this.queue.length > EGW_DATA_QUEUE_MAX_ELEM_COUNT)
	{
		this.flushQueue();
		return false;
	}

	return true;
}

egwGridDataQueue.prototype.inQueue = function(_elem)
{
	for (var i = 0; i < this.queue.length; i++)
	{
		if (this.queue[i].elem == _elem)
		{
			return true;
		}
	}
	return false;
}

/**
 * Queues the given element in the fetch-data queue.
 *
 * @param object _elem is the element whose data will be fetched
 * @param array _columns is an array of column ids which should be fetched. Those
 * 	columns will be accumulated over the queue calls. _columns may also take
 * 	the value EGW_DATA_QUEUE_CHILDREN in which case a request for the children
 * 	of the given element is queued.
 * @param function _callback is a callback function which will be called after
 * 	the data has been sent from the server.
 * @param object _context is the context in which the callback function will
 * 	be executed.
 */
egwGridDataQueue.prototype.queue = function(_elem, _columns, _callback, _context)
{
	if (typeof _callback == "undefined")
	{
		_callback = null;
	}
	if (typeof _context == "undefined")
	{
		_context = null;
	}

	if (_columns === EGW_DATA_QUEUE_CHILDREN)
	{
		if (!this._queue({
				"elem": _elem,
				"type": EGW_DATA_QUEUE_CHILDREN,
				"proc": _callback,
				"context": _context
			}))
		{
			this.flushQueue();
		}
	}
	else
	{
		// Merge the specified columns into the queueColumns variable
		for (var i = 0; i < _columns.length; i++)
		{
			if (this.queueColumns.indexOf(_columns[i]) == -1)
			{
				this.queueColumns.push(_columns[i]);
			}
		}

		// Queue the element and search in the elements around the given one for
		// elements whose data isn't loaded yet.
		var done = !this._queue({
			"elem": _elem,
			"type": EGW_DATA_QUEUE_ELEM,
			"proc": _callback,
			"context": _context
		});

		// Prefetch other elements around the given element
		var parent = _elem.parent;
		if (parent)
		{
			// Initialize the start prefetch index and the max prefetch count
			var prefetch = EGW_DATA_QUEUE_PREFETCH_COUNT;
			var idx = Math.floor(Math.max(0, _elem.index - prefetch / 2));

			while (!done && prefetch > 0 && idx < parent.children.length)
			{

				// Don't prefetch the element itself
				if (idx != _elem.idx)
				{
					// Fetch the element with the current index from the children
					// of the parent of the element.
					var elem = parent.children[idx];

					// Check whether this element has all data columns loaded and is
					// not already in the queue
					if (!this.inQueue(elem))
					{
						var hasColumns = true;
						for (var j = 0; j < this.queueColumns.length; j++)
						{
							var res = elem.hasColumn(this.queueColumns[i], false);
							if (!res)
							{
								hasColumns = false;
								break;
							}
						}

						if (!hasColumns)
						{
							done = !this._queue({
								"elem": elem,
								"type": EGW_DATA_QUEUE_ELEM,
								"proc": null,
								"context": null
							});
							prefetch--;
						}
					}
				}

				idx++;
			}
		}
	}
}

/**
 * Empties the queue and calls the fetch callback which cares about retrieving
 * the data from the server.
 */
egwGridDataQueue.prototype.flushQueue = function()
{
	var ids = [];

	// Generate a list of element ids
	for (var i = 0; i < this.queue.length; i++)
	{
		ids.push(this.queue[i].elem.id);
	}

	// Call the fetch callback and save a snapshot of the current queue
	var queue = this.queue;
	this.fetchCallback.call(this.context, ids, this.queueColumns, function(_data) {
		this.dataCallback(_data, queue);
	}, this);

	this.queue = [];
	this.queueColumns = [];
}

egwGridDataQueue.prototype.dataCallback = function(_data, _queue)
{
	var rootData = [];

	// Iterate over the given data and check whether the data coresponds to one
	// of the queue elements - if yes, call their (probably) specified callback.
	// All elements for which no queue element can be found are added to the
	// "rootData" list, which is then loaded by the "dataRoot" data object.
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
					// The element has been found, update its data
					_queue[j].elem.loadData(_data[i]);

					// Call the queue object callback (if specified)
					if (_queue[j].callback)
					{
						_queue[j].callback.call(_queue[j].context);
					}

					// Delete this queue element
					_queue.splice(i, 1);

					hasTarget = true;
					break;
				}
			}
		}

		if (!hasTarget)
		{
			rootData.push(_queue[i]);
		}
	}

	this.dataRoot.loadData(rootData);
}

