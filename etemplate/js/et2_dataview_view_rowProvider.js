/**
 * eGroupWare eTemplate2 - Class which contains a factory method for rows
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage dataview
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
 */

/*egw:uses
	jquery.jquery;
	et2_core_inheritance;
	et2_core_interfaces;
	et2_core_arrayMgr;
	et2_core_widget;
*/

/**
 * The row provider contains prototypes (full clonable dom-trees) 
 * for all registered row types.
 */
var et2_dataview_rowProvider = Class.extend({

	init: function(_outerId, _columnIds) {
		// Copy the given parameters
		this._outerId = _outerId;
		this._columnIds = _columnIds;
		this._prototypes = {};

		this._dataRowTemplate = null;
		this._mgrs = null;
		this._rootWidget = null;

		// Create the default row "prototypes"
		this._createFullRowPrototype();
		this._createDefaultPrototype();
		this._createEmptyPrototype();
	},

	/**
	 * Returns a clone of the prototype with the given name. If the generator
	 * callback function is given, this function is called if the prototype
	 * does not yet registered.
	 */
	getPrototype: function(_name, _generator, _context) {
		if (typeof this._prototypes[_name] == "undefined")
		{
			if (typeof _generator != "undefined")
			{
				this._prototypes[_name] = _generator.call(_context, this._outerId,
					this._columnIds);
			}
			else
			{
				return null;
			}
		}

		return this._prototypes[_name].clone();
	},

	setDataRowTemplate: function(_template, _rootWidget) {
		this._dataRowTemplate = _template;
		this._rootWidget = _rootWidget;
	},

	getDataRow: function(_data, _row, _idx) {
		// Create the row widget
		var rowWidget = new et2_dataview_rowWidget(this._rootWidget, _row[0]);

		// Create array managers with the given data merged in
		var mgrs = et2_arrayMgrs_expand(rowWidget, this._rootWidget.getArrayMgrs(),
			_data, _idx);

		// Let the row widget create the widgets
		rowWidget.createWidgets(mgrs, this._dataRowTemplate);

		return rowWidget;
	},

	/* ---- PRIVATE FUNCTIONS ---- */

	_createFullRowPrototype: function() {
		var tr = $j(document.createElement("tr"));
		var td = $j(document.createElement("td"))
			.attr("span", this._columnIds.length)
			.appendTo(tr);
		var div = $j(document.createElement("div"))
			.addClass(this._outerId + "_div_fullRow")
			.appendTo(td);

		this._prototypes["fullRow"] = tr;
	},

	_createDefaultPrototype: function() {
		var tr = $j(document.createElement("tr"));

		// Append a td for each column
		for (var i = 0; i < this._columnIds.length; i++)
		{
			var td = $j(document.createElement("td"))
				.addClass(this._outerId + "_td_" + this._columnIds[i])
				.appendTo(tr);
			var div = $j(document.createElement("div"))
				.addClass(this._outerId + "_div_" + this._columnIds[i])
				.addClass("innerContainer")
				.appendTo(td);
		}

		this._prototypes["default"] = tr;
	},

	_createEmptyPrototype: function() {
		this._prototypes["empty"] = $j(document.createElement("tr"));
	}

});

var et2_dataview_rowWidget = et2_widget.extend(et2_IDOMNode, {

	init: function(_parent, _row) {
		// Call the parent constructor with some dummy attributes
		this._super(_parent, {"id": "", "type": "rowWidget"});

		// Initialize some variables
		this._widgets = [];

		// Copy the given DOM node
		this._row = _row;
	},

	/**
	 * Copies the given array manager and clones the given widgets and inserts
	 * them into the row which has been passed in the constructor.
	 */
	createWidgets: function(_mgrs, _widgets) {
		// Set the array managers - don't use setArrayMgrs here as this creates
		// an unnecessary copy of the object
		this._mgrs = _mgrs;

		// Clone the given the widgets with this element as parent
		this._widgets = new Array(_widgets.length);
		for (var i = 0; i < _widgets.length; i++)
		{
			this._widgets[i] = _widgets[i].clone(this);
			this._widgets[i].loadingFinished();
		}
	},

	/**
	 * Returns the column node for the given sender
	 */
	getDOMNode: function(_sender) {

		if (typeof _sender == "undefined" || !_sender)
		{
			return this.row;
		}

		for (var i = 0; i < this._widgets.length; i++)
		{
			if (this._widgets[i] == _sender)
			{
				return this._row.childNodes[i]; // Return the i-th td tag
			}
		}
	}

});


