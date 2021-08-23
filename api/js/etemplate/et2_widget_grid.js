"use strict";
/**
 * EGroupware eTemplate2 - JS Grid object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
 */
var __extends = (this && this.__extends) || (function () {
    var extendStatics = function (d, b) {
        extendStatics = Object.setPrototypeOf ||
            ({ __proto__: [] } instanceof Array && function (d, b) { d.__proto__ = b; }) ||
            function (d, b) { for (var p in b) if (b.hasOwnProperty(p)) d[p] = b[p]; };
        return extendStatics(d, b);
    };
    return function (d, b) {
        extendStatics(d, b);
        function __() { this.constructor = d; }
        d.prototype = b === null ? Object.create(b) : (__.prototype = b.prototype, new __());
    };
})();
Object.defineProperty(exports, "__esModule", { value: true });
exports.et2_grid = void 0;
/*egw:uses
    /vendor/bower-asset/jquery/dist/jquery.js;
    et2_core_DOMWidget;
    et2_core_xml;
*/
require("./et2_core_common");
require("./et2_core_interfaces");
var et2_core_widget_1 = require("./et2_core_widget");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
var et2_core_DOMWidget_1 = require("./et2_core_DOMWidget");
require("../egw_action/egw_action.js");
/**
 * Class which implements the "grid" XET-Tag
 *
 * This also includes repeating the last row in the grid and filling
 * it with content data
 *
 * @augments et2_DOMWidget
 */
var et2_grid = /** @class */ (function (_super) {
    __extends(et2_grid, _super);
    /**
     * Constructor
     *
     * @memberOf et2_grid
     */
    function et2_grid(_parent, _attrs, _child) {
        var _this = 
        // Call the parent constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_grid._attributes, _child || {})) || this;
        // Counters for rows and columns
        _this.rowCount = 0;
        _this.columnCount = 0;
        // 2D-Array which holds references to the DOM td tags
        _this.cells = [];
        _this.rowData = [];
        _this.colData = [];
        _this.managementArray = [];
        // Keep the template node for later regeneration
        _this.template_node = null;
        // Wrapper div for height & overflow, if needed
        _this.wrapper = null;
        // Create the table body and the table
        _this.table = jQuery(document.createElement("table"))
            .addClass("et2_grid");
        _this.thead = jQuery(document.createElement("thead"))
            .appendTo(_this.table);
        _this.tfoot = jQuery(document.createElement("tfoot"))
            .appendTo(_this.table);
        _this.tbody = jQuery(document.createElement("tbody"))
            .appendTo(_this.table);
        return _this;
    }
    et2_grid.prototype._initCells = function (_colData, _rowData) {
        // Copy the width and height
        var w = _colData.length;
        var h = _rowData.length;
        // Create the 2D-Cells array
        var cells = new Array(h);
        for (var y = 0; y < h; y++) {
            cells[y] = new Array(w);
            // Initialize the cell description objects
            for (var x = 0; x < w; x++) {
                // Some columns (nm) we do not parse into a boolean
                var col_disabled = _colData[x].disabled;
                cells[y][x] = {
                    "td": null,
                    "widget": null,
                    "colData": _colData[x],
                    "rowData": _rowData[y],
                    "disabled": col_disabled || _rowData[y].disabled,
                    "class": _colData[x]["class"],
                    "colSpan": 1,
                    "autoColSpan": false,
                    "rowSpan": 1,
                    "autoRowSpan": false,
                    "width": _colData[x].width,
                    "x": x,
                    "y": y
                };
            }
        }
        return cells;
    };
    et2_grid.prototype._getColDataEntry = function () {
        return {
            width: "auto",
            class: "",
            align: "",
            span: "1",
            disabled: false
        };
    };
    et2_grid.prototype._getRowDataEntry = function () {
        return {
            height: "auto",
            class: "",
            valign: "top",
            span: "1",
            disabled: false
        };
    };
    et2_grid.prototype._getCell = function (_cells, _x, _y) {
        if ((0 <= _y) && (_y < _cells.length)) {
            var row = _cells[_y];
            if ((0 <= _x) && (_x < row.length)) {
                return row[_x];
            }
        }
        throw ("Error while accessing grid cells, invalid element count or span value!");
    };
    et2_grid.prototype._forceNumber = function (_val) {
        if (isNaN(_val)) {
            throw (_val + " is not a number!");
        }
        return parseInt(_val);
    };
    et2_grid.prototype._fetchRowColData = function (columns, rows, colData, rowData) {
        // Some things cannot be done inside a nextmatch - nm will do the expansion later
        var nm = false;
        var widget = this;
        while (!nm && widget != this.getRoot()) {
            nm = (widget.getType() == 'nextmatch');
            widget = widget.getParent();
        }
        // Parse the columns tag
        et2_filteredNodeIterator(columns, function (node, nodeName) {
            var colDataEntry = this._getColDataEntry();
            // This cannot be done inside a nm, it will expand it later
            colDataEntry["disabled"] = nm ?
                et2_readAttrWithDefault(node, "disabled", "") :
                this.getArrayMgr("content")
                    .parseBoolExpression(et2_readAttrWithDefault(node, "disabled", ""));
            if (nodeName == "column") {
                colDataEntry["width"] = et2_readAttrWithDefault(node, "width", "auto");
                colDataEntry["class"] = et2_readAttrWithDefault(node, "class", "");
                colDataEntry["align"] = et2_readAttrWithDefault(node, "align", "");
                colDataEntry["span"] = et2_readAttrWithDefault(node, "span", "1");
                // Keep any others attributes set, there's no 'column' widget
                for (var i in node.attributes) {
                    var attr = node.attributes[i];
                    if (attr.nodeType == 2 && typeof colDataEntry[attr.nodeName] == 'undefined') {
                        colDataEntry[attr.nodeName] = attr.value;
                    }
                }
            }
            else {
                colDataEntry["span"] = "all";
            }
            colData.push(colDataEntry);
        }, this);
        // Parse the rows tag
        et2_filteredNodeIterator(rows, function (node, nodeName) {
            var rowDataEntry = this._getRowDataEntry();
            rowDataEntry["disabled"] = this.getArrayMgr("content")
                .parseBoolExpression(et2_readAttrWithDefault(node, "disabled", ""));
            if (nodeName == "row") {
                // Remember this row for auto-repeat - it'll eventually be the last one
                this.lastRowNode = node;
                rowDataEntry["height"] = et2_readAttrWithDefault(node, "height", "auto");
                rowDataEntry["class"] = et2_readAttrWithDefault(node, "class", "");
                rowDataEntry["valign"] = et2_readAttrWithDefault(node, "valign", "");
                rowDataEntry["span"] = et2_readAttrWithDefault(node, "span", "1");
                rowDataEntry["part"] = et2_readAttrWithDefault(node, "part", "body");
                var id = et2_readAttrWithDefault(node, "id", "");
                if (id) {
                    rowDataEntry["id"] = id;
                }
            }
            else {
                rowDataEntry["span"] = "all";
            }
            rowData.push(rowDataEntry);
        }, this);
        // Add in repeated rows
        // TODO: It would be nice if we could skip header (thead) & footer (tfoot) or treat them separately
        var rowIndex = Infinity;
        if (this.getArrayMgr("content")) {
            var content_1 = this.getArrayMgr("content");
            var rowDataEntry = rowData[rowData.length - 1];
            rowIndex = rowData.length - 1;
            // Find out if we have any content rows, and how many
            var cont = true;
            var _loop_1 = function () {
                if (content_1.data[rowIndex]) {
                    rowData[rowIndex] = jQuery.extend({}, rowDataEntry);
                    rowIndex++;
                }
                else if (this_1.lastRowNode != null) {
                    // Have to look through actual widgets to support const[$row]
                    // style names - should be avoided so we can remove this extra check
                    // Old etemplate checked first two widgets, or first two box children
                    // This cannot be done inside a nextmatch - nm will do the expansion later
                    nm = false;
                    if (nm) {
                        return "break";
                    }
                    // Not in a nextmatch, so we can expand with abandon
                    var currentPerspective = jQuery.extend({}, content_1.perspectiveData);
                    var check_1 = function (node, nodeName) {
                        if (nodeName == 'box' || nodeName == 'hbox' || nodeName == 'vbox') {
                            return et2_filteredNodeIterator(node, check_1, this);
                        }
                        content_1.perspectiveData.row = rowIndex;
                        for (var attr in node.attributes) {
                            var value = et2_readAttrWithDefault(node, node.attributes[attr].name, "");
                            // Don't include first char, those should be handled by normal means
                            // and it would break nextmatch
                            if (value.indexOf('@') > 0 || value.indexOf('$') > 0) {
                                // Ok, we found something.  How many? Check for values.
                                var ident = content_1.expandName(value);
                                // expandName() handles index into content (@), but we have to look up
                                // regular values
                                if (value[0] != '@') {
                                    // Returns null if there isn't an actual value
                                    ident = content_1.getEntry(ident, false, true);
                                }
                                while (ident != null && rowIndex < 1000) {
                                    rowData[rowIndex] = jQuery.extend({}, rowDataEntry);
                                    content_1.perspectiveData.row = ++rowIndex;
                                    ident = content_1.expandName(value);
                                    if (value[0] != '@') {
                                        // Returns null if there isn't an actual value
                                        ident = content_1.getEntry(ident, false, true);
                                    }
                                }
                                if (rowIndex >= 1000) {
                                    egw.debug("error", "Problem in autorepeat fallback: too many rows for '%s'.  Use a nextmatch, or start debugging.", value);
                                }
                                return;
                            }
                        }
                    };
                    et2_filteredNodeIterator(this_1.lastRowNode, check_1, this_1);
                    cont = false;
                    content_1.perspectiveData = currentPerspective;
                }
                else {
                    return "break";
                }
            };
            var this_1 = this, nm;
            while (cont) {
                var state_1 = _loop_1();
                if (state_1 === "break")
                    break;
            }
        }
        if (rowIndex <= rowData.length - 1) {
            // No auto-repeat
            this.lastRowNode = null;
        }
    };
    et2_grid.prototype._fillCells = function (cells, columns, rows) {
        var h = cells.length;
        var w = (h > 0) ? cells[0].length : 0;
        var currentPerspective = jQuery.extend({}, this.getArrayMgr("content").perspectiveData);
        // Read the elements inside the columns
        var x = 0;
        et2_filteredNodeIterator(columns, function (node, nodeName) {
            function _readColNode(node, nodeName) {
                if (y >= h) {
                    this.egw().debug("warn", "Skipped grid cell in column, '" +
                        nodeName + "'");
                    return;
                }
                var cell = this._getCell(cells, x, y);
                // Read the span value of the element
                if (node.getAttribute("span")) {
                    cell.rowSpan = node.getAttribute("span");
                }
                else {
                    cell.rowSpan = cell.colData["span"];
                    cell.autoRowSpan = true;
                }
                if (cell.rowSpan == "all") {
                    cell.rowSpan = cells.length;
                }
                var span = cell.rowSpan = this._forceNumber(cell.rowSpan);
                // Create the widget
                var widget = this.createElementFromNode(node, nodeName);
                // Fill all cells the widget is spanning
                for (var i = 0; i < span && y < cells.length; i++, y++) {
                    this._getCell(cells, x, y).widget = widget;
                }
            }
            // If the node is a column, create the widgets which belong into
            // the column
            var y = 0;
            if (nodeName == "column") {
                et2_filteredNodeIterator(node, _readColNode, this);
            }
            else {
                _readColNode.call(this, node, nodeName);
            }
            x++;
        }, this);
        // Read the elements inside the rows
        var y = 0;
        x = 0;
        var readRowNode;
        var nm = false;
        var widget = this;
        while (!nm && widget != this.getRoot()) {
            nm = (widget.getType() == 'nextmatch');
            widget = widget.getParent();
        }
        et2_filteredNodeIterator(rows, function (node, nodeName) {
            readRowNode = function _readRowNode(node, nodeName) {
                if (x >= w) {
                    if (nodeName != "description") {
                        // Only notify it skipping other than description,
                        // description used to pad
                        this.egw().debug("warn", "Skipped grid cell in row, '" +
                            nodeName + "'");
                    }
                    return;
                }
                var cell = this._getCell(cells, x, y);
                // Read the span value of the element
                if (node.getAttribute("span")) {
                    cell.colSpan = node.getAttribute("span");
                }
                else {
                    cell.colSpan = cell.rowData["span"];
                    cell.autoColSpan = true;
                }
                if (cell.colSpan == "all") {
                    cell.colSpan = cells[y].length;
                }
                var span = cell.colSpan = this._forceNumber(cell.colSpan);
                // Read the align value of the element
                if (node.getAttribute("align")) {
                    cell.align = node.getAttribute("align");
                }
                // store id of nextmatch-*headers, so it is available for disabled widgets, which get not instanciated
                if (nodeName.substr(0, 10) == 'nextmatch-') {
                    cell.nm_id = node.getAttribute('id');
                }
                // Apply widget's class to td, for backward compatability
                if (node.getAttribute("class")) {
                    cell.class += (cell.class ? " " : "") + this.getArrayMgr("content").expandName(node.getAttribute("class"));
                }
                // Create the element
                if (!cell.disabled || cell.disabled && typeof cell.disabled === 'string') {
                    //Skip if it is a nextmatch while the nextmatch handles row adjustment by itself
                    if (!nm) {
                        // Adjust for the row
                        var mgrs = this.getArrayMgrs();
                        for (var name_1 in mgrs) {
                            this.getArrayMgr(name_1).perspectiveData.row = y;
                        }
                        if (this._getCell(cells, x, y).rowData.id) {
                            this._getCell(cells, x, y).rowData.id = this.getArrayMgr("content").expandName(this._getCell(cells, x, y).rowData.id);
                        }
                        if (this._getCell(cells, x, y).rowData.class) {
                            this._getCell(cells, x, y).rowData.class = this.getArrayMgr("content").expandName(this._getCell(cells, x, y).rowData.class);
                        }
                    }
                    if (!nm && typeof cell.disabled === 'string') {
                        cell.disabled = this.getArrayMgr("content").parseBoolExpression(cell.disabled);
                    }
                    if (nm || !cell.disabled) {
                        var widget = this.createElementFromNode(node, nodeName);
                    }
                }
                // Fill all cells the widget is spanning
                for (var i = 0; i < span && x < cells[y].length; i++, x++) {
                    cell = this._getCell(cells, x, y);
                    if (cell.widget == null) {
                        cell.widget = widget;
                    }
                    else {
                        throw ("Grid cell collision, two elements " +
                            "defined for cell (" + x + "," + y + ")!");
                    }
                }
            };
            // If the node is a row, create the widgets which belong into
            // the row
            x = 0;
            if (this.lastRowNode && node == this.lastRowNode) {
                return;
            }
            if (nodeName == "row") {
                // Adjust for the row
                for (var name in this.getArrayMgrs()) {
                    //this.getArrayMgr(name).perspectiveData.row = y;
                }
                var cell = this._getCell(cells, x, y);
                if (cell.rowData.id) {
                    this.getArrayMgr("content").expandName(cell.rowData.id);
                }
                // If row disabled, just skip it
                var disabled = false;
                if (node.getAttribute("disabled") == "1") {
                    disabled = true;
                }
                if (!disabled) {
                    et2_filteredNodeIterator(node, readRowNode, this);
                }
            }
            else {
                readRowNode.call(this, node, nodeName);
            }
            y++;
        }, this);
        // Extra content rows
        for (y; y < h; y++) {
            x = 0;
            et2_filteredNodeIterator(this.lastRowNode, readRowNode, this);
        }
        // Reset
        for (var name in this.getArrayMgrs()) {
            this.getArrayMgr(name).perspectiveData = currentPerspective;
        }
    };
    et2_grid.prototype._expandLastCells = function (_cells) {
        var h = _cells.length;
        var w = (h > 0) ? _cells[0].length : 0;
        // Determine the last cell in each row and expand its span value if
        // the span has not been explicitly set.
        for (var y = 0; y < h; y++) {
            for (var x = w - 1; x >= 0; x--) {
                var cell = _cells[y][x];
                if (cell.widget != null) {
                    if (cell.autoColSpan) {
                        cell.colSpan = w - x;
                    }
                    break;
                }
            }
        }
        // Determine the last cell in each column and expand its span value if
        // the span has not been explicitly set.
        for (var x = 0; x < w; x++) {
            for (var y = h - 1; y >= 0; y--) {
                var cell = _cells[y][x];
                if (cell.widget != null) {
                    if (cell.autoRowSpan) {
                        cell.rowSpan = h - y;
                    }
                    break;
                }
            }
        }
    };
    et2_grid.prototype._createNamespace = function () {
        return true;
    };
    /**
     * As the does not fit very well into the default widget structure, we're
     * overwriting the loadFromXML function and doing a two-pass reading -
     * in the first step the
     *
     * @param {object} _node xml node to process
     */
    et2_grid.prototype.loadFromXML = function (_node) {
        // Keep the node for later changing / reloading
        this.template_node = _node;
        // Get the columns and rows tag
        var rowsElems = et2_directChildrenByTagName(_node, "rows");
        var columnsElems = et2_directChildrenByTagName(_node, "columns");
        if (rowsElems.length == 1 && columnsElems.length == 1) {
            var columns = columnsElems[0];
            var rows = rowsElems[0];
            var colData = [];
            var rowData = [];
            // Fetch the column and row data
            this._fetchRowColData(columns, rows, colData, rowData);
            // Initialize the cells
            var cells = this._initCells(colData, rowData);
            // Create the widgets inside the cells and read the span values
            this._fillCells(cells, columns, rows);
            // Expand the span values of the last cells
            this._expandLastCells(cells);
            // Create the table rows
            this.createTableFromCells(cells, colData, rowData);
        }
        else {
            throw ("Error while parsing grid, none or multiple rows or columns tags!");
        }
    };
    et2_grid.prototype.createTableFromCells = function (_cells, _colData, _rowData) {
        this.managementArray = [];
        this.cells = _cells;
        this.colData = _colData;
        this.rowData = _rowData;
        // Set the rowCount and columnCount variables
        var h = this.rowCount = _cells.length;
        var w = this.columnCount = (h > 0) ? _cells[0].length : 0;
        // Create the table rows.
        for (var y = 0; y < h; y++) {
            var parent_1 = this.tbody;
            switch (this.rowData[y]["part"]) {
                case 'header':
                    if (!this.tbody.children().length && !this.tfoot.children().length) {
                        parent_1 = this.thead;
                    }
                    break;
                case 'footer':
                    if (!this.tbody.children().length) {
                        parent_1 = this.tfoot;
                    }
                    break;
            }
            var tr = jQuery(document.createElement("tr")).appendTo(parent_1)
                .addClass(this.rowData[y]["class"]);
            if (this.rowData[y].disabled) {
                tr.hide();
            }
            if (this.rowData[y].height != "auto") {
                tr.height(this.rowData[y].height);
            }
            if (this.rowData[y].valign) {
                tr.attr("valign", this.rowData[y].valign);
            }
            if (this.rowData[y].id) {
                tr.attr("id", this.rowData[y].id);
            }
            // Create the cells. x is incremented by the colSpan value of the
            // cell.
            for (var x = 0; x < w;) {
                // Fetch a cell from the cells
                var cell = this._getCell(_cells, x, y);
                if (cell.td == null && cell.widget != null) {
                    // Create the cell
                    var td = jQuery(document.createElement("td")).appendTo(tr)
                        .addClass(cell["class"]);
                    if (cell.disabled) {
                        td.hide();
                        cell.widget.options.disabled = cell.disabled;
                    }
                    if (cell.width != "auto") {
                        td.width(cell.width);
                    }
                    if (cell.align) {
                        td.attr("align", cell.align);
                    }
                    // Add the entry for the widget to the management array
                    this.managementArray.push({
                        "cell": td[0],
                        "widget": cell.widget,
                        "disabled": cell.disabled
                    });
                    // Set the span values of the cell
                    var cs = (x == w - 1) ? w - x : Math.min(w - x, cell.colSpan);
                    var rs = (y == h - 1) ? h - y : Math.min(h - y, cell.rowSpan);
                    // Set the col and row span values
                    if (cs > 1) {
                        td.attr("colspan", cs);
                    }
                    if (rs > 1) {
                        td.attr("rowspan", rs);
                    }
                    // Assign the td to the cell
                    for (var sx = x; sx < x + cs; sx++) {
                        for (var sy = y; sy < y + rs; sy++) {
                            this._getCell(_cells, sx, sy).td = td;
                        }
                    }
                    x += cell.colSpan;
                }
                else {
                    x++;
                }
            }
        }
    };
    et2_grid.prototype.getDOMNode = function (_sender) {
        // If the parent class functions are asking for the DOM-Node, return the
        // outer table.
        if (_sender == this || typeof _sender == 'undefined') {
            return this.wrapper != null ? this.wrapper[0] : this.table[0];
        }
        // Check whether the _sender object exists inside the management array
        for (var i = 0; i < this.managementArray.length; i++) {
            if (this.managementArray[i].widget == _sender) {
                return this.managementArray[i].cell;
            }
        }
        return null;
    };
    et2_grid.prototype.isInTree = function (_sender) {
        var vis = true;
        if (typeof _sender != "undefined" && _sender != this) {
            vis = false;
            // Check whether the _sender object exists inside the management array
            for (var i = 0; i < this.managementArray.length; i++) {
                if (this.managementArray[i].widget == _sender) {
                    vis = !(typeof this.managementArray[i].disabled === 'boolean' ?
                        this.managementArray[i].disabled :
                        this.getArrayMgr("content").parseBoolExpression(this.managementArray[i].disabled));
                    break;
                }
            }
        }
        return _super.prototype.isInTree.call(this, this, vis);
    };
    /**
     * Set the overflow attribute
     *
     * Grid needs special handling because HTML tables don't do overflow.  We
     * create a wrapper DIV to handle it.
     * No value or default visible needs no wrapper, as table is always overflow visible.
     *
     * @param {string} _value Overflow value, must be a valid CSS overflow value, default 'visible'
     */
    et2_grid.prototype.set_overflow = function (_value) {
        var wrapper = this.wrapper || this.table.parent('[id$="_grid_wrapper"]');
        this.overflow = _value;
        if (wrapper.length == 0 && _value && _value !== 'visible') {
            this.wrapper = wrapper = this.table.wrap('<div id="' + this.id + '_grid_wrapper"></div>').parent();
            if (this.height) {
                wrapper.css('height', this.height);
            }
        }
        wrapper.css('overflow', _value);
        if (wrapper.length && (!_value || _value === 'visible')) {
            this.table.unwrap();
        }
    };
    /**
     * Change the content for the grid, and re-generate its contents.
     *
     * Changing the content does not allow changing the structure of the grid,
     * as that is loaded from the template file.  The rows and widgets inside
     * will be re-created (including auto-repeat).
     *
     * @param {Object} _value New data for the grid
     * @param {Object} [_value.content] New content
     * @param {Object} [_value.sel_options] New select options
     * @param {Object} [_value.readonlys] New read-only values
     */
    et2_grid.prototype.set_value = function (_value) {
        // Destroy children, empty grid
        for (var i = 0; i < this.managementArray.length; i++) {
            var cell = this.managementArray[i];
            if (cell.widget) {
                cell.widget.destroy();
            }
        }
        this.managementArray = [];
        this.thead.empty();
        this.tfoot.empty();
        this.tbody.empty();
        // Update array managers
        for (var key in _value) {
            this.getArrayMgr(key).data = _value[key];
        }
        // Rebuild grid
        this.loadFromXML(this.template_node);
        // New widgets need to finish
        var promises = [];
        this.loadingFinished(promises);
    };
    /**
     * Sortable allows you to reorder grid rows using the mouse.
     * The new order is returned as part of the value of the
     * grid, in 'sort_order'.
     *
     * @param {boolean|function} sortable Callback or false to disable
     */
    et2_grid.prototype.set_sortable = function (sortable) {
        var $node = jQuery(this.getDOMNode());
        if (!sortable) {
            $node.sortable("destroy");
            return;
        }
        // Make sure rows have IDs, so sortable has something to return
        jQuery('tr', this.tbody).each(function (index) {
            var $this = jQuery(this);
            // Header does not participate in sorting
            if ($this.hasClass('th'))
                return;
            // If row doesn't have an ID, assign the index as ID
            if (!$this.attr("id"))
                $this.attr("id", index);
        });
        var self = this;
        // Set up sortable
        $node.sortable({
            // Header does not participate in sorting
            items: "> tbody > tr:not(.th)",
            distance: 15,
            cancel: this.options.sortable_cancel,
            placeholder: this.options.sortable_placeholder,
            containment: this.options.sortable_containment,
            connectWith: this.options.sortable_connectWith,
            update: function (event, ui) {
                self.egw().json(sortable, [
                    self.getInstanceManager().etemplate_exec_id,
                    $node.sortable("toArray"),
                    self.id
                ], null, self, true).sendRequest();
            },
            receive: function (event, ui) {
                if (typeof self.sortable_recieveCallback == 'function') {
                    self.sortable_recieveCallback.call(self, event, ui, self.id);
                }
            },
            start: function (event, ui) {
                if (typeof self.options.sortable_startCallback == 'function') {
                    self.options.sortable_startCallback.call(self, event, ui, self.id);
                }
            }
        });
    };
    /**
     * Override parent to apply actions on each row
     *
     * @param {array} actions [ {ID: attributes..}+] as for set_actions
     */
    et2_grid.prototype._link_actions = function (actions) {
        // Get the top level element for the tree
        // get appObjectManager for the actual app, it might not always be the current app(e.g. running app content under admin tab)
        // @ts-ignore
        var objectManager = window.egw_getAppObjectManager(true, this.getInstanceManager().app);
        objectManager = objectManager.getObjectById(this.getInstanceManager().uniqueId, 2) || objectManager;
        var widget_object = objectManager.getObjectById(this.id);
        if (widget_object == null) {
            // Add a new container to the object manager which will hold the widget
            // objects
            widget_object = objectManager.insertObject(false, new egwActionObject(this.id, objectManager, new et2_core_DOMWidget_1.et2_action_object_impl(this).getAOI(), this._actionManager || objectManager.manager.getActionById(this.id) || objectManager.manager));
        }
        // Delete all old objects
        widget_object.clear();
        // Go over the widget & add links - this is where we decide which actions are
        // 'allowed' for this widget at this time
        var action_links = this._get_action_links(actions);
        // Deal with each row in tbody, ignore action-wise rows in thead or tfooter for now
        var i = 0, r = 0;
        for (; i < this.rowData.length; i++) {
            if (this.rowData[i].part != 'body')
                continue;
            var content = this.getArrayMgr('content').getEntry(i);
            if (content) {
                // Add a new action object to the object manager
                var row = jQuery('tr', this.tbody)[r];
                var aoi = new et2_core_DOMWidget_1.et2_action_object_impl(this, row).getAOI();
                var obj = widget_object.addObject(content.id || "row_" + r, aoi);
                // Set the data to the content so it's available for the action
                obj.data = content;
                obj.updateActionLinks(action_links);
            }
            r++;
        }
    };
    /**
     * Code for implementing et2_IDetachedDOM
     * This doesn't need to be implemented.
     * Individual widgets are detected and handled by the grid, but the interface is needed for this to happen
     *
     * @param {array} _attrs array to add further attributes to
     */
    et2_grid.prototype.getDetachedAttributes = function (_attrs) {
    };
    et2_grid.prototype.getDetachedNodes = function () {
        return [this.getDOMNode()];
    };
    et2_grid.prototype.setDetachedAttributes = function (_nodes, _values) {
    };
    /**
     * Generates nextmatch column name for headers in a grid
     *
     * Implemented here as default implementation in et2_externsion_nextmatch
     * only considers children, but grid does NOT instanciate disabled rows as children.
     *
     * @return {string}
     */
    et2_grid.prototype._getColumnName = function () {
        var ids = [];
        for (var r = 0; r < this.cells.length; ++r) {
            var cols = this.cells[r];
            for (var c = 0; c < cols.length; ++c) {
                if (cols[c].nm_id)
                    ids.push(cols[c].nm_id);
            }
        }
        return ids.join('_');
    };
    et2_grid.prototype.resize = function (_height) {
        if (typeof this.options != 'undefined' && _height
            && typeof this.options.resize_ratio != 'undefined' && this.options.resize_ratio) {
            // apply the ratio
            _height = (this.options.resize_ratio != '') ? _height * this.options.resize_ratio : _height;
            if (_height != 0) {
                if (this.wrapper) {
                    this.wrapper.height(this.wrapper.height() + _height);
                }
                else {
                    this.table.height(this.table.height() + _height);
                }
            }
        }
    };
    /**
     * Get a dummy row object containing all widget of a row
     *
     * This is only a temp. solution until rows are implemented as eT2 containers and
     * _sender.getParent() will return a real row container.
     *
     * @deprecated Not used?  Remove this if you find something using it
     *
     * @param {et2_widget} _sender
     * @returns {Array|undefined}
     */
    et2_grid.prototype.getRow = function (_sender) {
        if (!_sender || !this.cells)
            return;
        for (var r = 0; r < this.cells.length; ++r) {
            var row = this.cells[r];
            var _loop_2 = function () {
                if (!row[c].widget)
                    return "continue";
                var found = row[c].widget === _sender;
                if (!found)
                    row[c].widget.iterateOver(function (_widget) { if (_widget === _sender)
                        found = true; });
                if (found) {
                    // return a fake row object allowing to iterate over it's children
                    var row_obj_1 = new et2_core_widget_1.et2_widget(this_2, {});
                    for (var c = 0; c < row.length; ++c) {
                        if (row[c].widget)
                            row_obj_1.addChild(row[c].widget);
                    }
                    row_obj_1.isInTree = jQuery.proxy(this_2.isInTree, this_2);
                    // we must not free the children!
                    row_obj_1.destroy = function () {
                        // @ts-ignore
                        delete row_obj_1._children;
                    };
                    return { value: row_obj_1 };
                }
            };
            var this_2 = this;
            for (var c = 0; c < row.length; ++c) {
                var state_2 = _loop_2();
                if (typeof state_2 === "object")
                    return state_2.value;
            }
        }
    };
    /**
     * Needed for the align interface, but we're not doing anything with it...
     */
    et2_grid.prototype.get_align = function () {
        return "";
    };
    et2_grid._attributes = {
        // Better to use CSS, no need to warn about it
        "border": {
            "ignore": true
        },
        "align": {
            "name": "Align",
            "type": "string",
            "default": "left",
            "description": "Position of this element in the parent hbox"
        },
        "spacing": {
            "ignore": true
        },
        "padding": {
            "ignore": true
        },
        "sortable": {
            "name": "Sortable callback",
            "type": "string",
            "default": et2_no_init,
            "description": "PHP function called when user sorts the grid.  Setting this enables sorting the grid rows.  The callback will be passed the ID of the grid and the new order of the rows."
        },
        sortable_containment: {
            name: "Sortable bounding area",
            type: "string",
            default: "",
            description: "Defines bounding area for sortable items"
        },
        sortable_connectWith: {
            name: "Sortable connectWith element",
            type: "string",
            default: "",
            description: "Defines other sortable areas that should be connected to sort list"
        },
        sortable_placeholder: {
            name: "Sortable placeholder",
            type: "string",
            default: "",
            description: "Defines sortable placeholder"
        },
        sortable_cancel: {
            name: "Sortable cancel class",
            type: "string",
            default: "",
            description: "Defines sortable cancel which prevents sorting the matching element"
        },
        sortable_recieveCallback: {
            name: "Sortable receive callback",
            type: "js",
            default: et2_no_init,
            description: "Defines sortable receive callback function"
        },
        sortable_startCallback: {
            name: "Sortable start callback",
            type: "js",
            default: et2_no_init,
            description: "Defines sortable start callback function"
        }
    };
    return et2_grid;
}(et2_core_DOMWidget_1.et2_DOMWidget));
exports.et2_grid = et2_grid;
et2_core_widget_1.et2_register_widget(et2_grid, ["grid"]);
//# sourceMappingURL=et2_widget_grid.js.map