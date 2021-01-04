"use strict";
/**
 * EGroupware eTemplate2 - JS content array manager
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 *

/*egw:uses
    et2_core_common;
    egw_inheritance;
    et2_core_phpExpressionCompiler;
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
/**
 * Manage access to various template customisation arrays passed to etemplate->exec().
 *
 * This manages access to content, modifications and readonlys arrays
 */
var et2_arrayMgr = /** @class */ (function () {
    /**
     * Constructor
     *
     * @memberOf et2_arrayMgr
     * @param _data
     * @param _parentMgr
     */
    function et2_arrayMgr(_data, _parentMgr) {
        if (_data === void 0) { _data = {}; }
        this.splitIds = true;
        // Holds information about the current perspective
        this.perspectiveData = {
            "owner": null,
            "key": null,
            "row": null
        };
        this.readOnly = false;
        if (typeof _parentMgr == "undefined") {
            _parentMgr = null;
        }
        // Copy the parent manager which is needed to access relative data when
        // being in a relative perspective of the manager
        this._parentMgr = _parentMgr;
        // Hold a reference to the data
        if (typeof _data == "undefined" || !_data) {
            egw.debug("log", "No data passed to content array manager.  Probably a mismatch between template namespaces and data.");
            _data = {};
        }
        // Expand sub-arrays that have been shmushed together, so further perspectives work
        // Shmushed keys look like: ${row}[info_cat]
        // Expanded: ${row}: Object{info_cat: ..value}
        if (this.splitIds) {
            // For each index, we need a key: {..} sub array
            for (var key in _data) {
                // Split up indexes
                var indexes = key.replace(/&#x5B;/g, "[").split('[');
                // Put data in the proper place
                if (indexes.length > 1) {
                    var value = _data[key];
                    var target = _data;
                    for (var i = 0; i < indexes.length; i++) {
                        indexes[i] = indexes[i].replace(/&#x5D;/g, '').replace(']', '');
                        if (typeof target[indexes[i]] == "undefined" || target[indexes[i]] === null) {
                            target[indexes[i]] = i == indexes.length - 1 ? value : {};
                        }
                        target = target[indexes[i]];
                    }
                    delete _data[key];
                }
            }
        }
        this.data = _data;
    }
    /**
     * Returns the root content array manager object
     */
    et2_arrayMgr.prototype.getRoot = function () {
        if (this._parentMgr != null) {
            return this._parentMgr.getRoot();
        }
        return this;
    };
    et2_arrayMgr.prototype.getParentMgr = function () {
        return this._parentMgr;
    };
    et2_arrayMgr.prototype.getPerspectiveData = function () {
        return this.perspectiveData;
    };
    et2_arrayMgr.prototype.setPerspectiveData = function (new_perspective) {
        this.perspectiveData = new_perspective;
    };
    et2_arrayMgr.prototype.setRow = function (new_row) {
        this.perspectiveData.row = new_row;
    };
    /**
     * Explodes compound keys (eg IDs) into a list of namespaces
     * This uses no internal values, just expands
     *
     * eg:
     * a[b][c] => [a,b,c]
     * col_filter[tr_tracker] => [col_filter, tr_tracker]
     *
     * @param {string} _key
     *
     * @return {string[]}
     */
    et2_arrayMgr.prototype.explodeKey = function (_key) {
        if (!_key || typeof _key == 'string' && _key.trim() === "")
            return [];
        // Parse the given key by removing the "]"-chars and splitting at "["
        var indexes = [_key];
        if (typeof _key === "string") {
            _key = _key.replace(/&#x5B;/g, "[").replace(/&#x5D;/g, "]");
            indexes = _key.split('[');
        }
        if (indexes.length > 1) {
            indexes = [indexes.shift(), indexes.join('[')];
            indexes[1] = indexes[1].substring(0, indexes[1].length - 1);
            var children = indexes[1].split('][');
            if (children.length) {
                indexes = jQuery.merge([indexes[0]], children);
            }
        }
        return indexes;
    };
    /**
     * Returns the path to this content array manager perspective as an array
     * containing the key values
     *
     * @param _path is used internally, do not supply it manually.
     */
    et2_arrayMgr.prototype.getPath = function (_path) {
        if (typeof _path == "undefined") {
            _path = [];
        }
        if (this.perspectiveData.key != null) {
            // prepend components of this.perspectiveData.key to path, can be more then one eg. "nm[rows]"
            _path = this.perspectiveData.key.replace(/]/g, '').split('[').concat(_path);
        }
        if (this._parentMgr != null) {
            _path = this._parentMgr.getPath(_path);
        }
        return _path;
    };
    /**
     * Get array entry is the equivalent to the boetemplate get_array function.
     * It returns a reference to the (sub) array with the given key. This also works
     * for keys using the ETemplate referencing scheme like a[b][c]
     *
     * @param _key is the string index, may contain sub-indices like a[b]
     * @param _referenceInto if true none-existing sub-arrays/-indices get created
     *    to be returned as reference, else false is returned. Defaults to false
     * @param _skipEmpty returns null if _key is not present in this content array.
     *    Defaults to false.
     */
    et2_arrayMgr.prototype.getEntry = function (_key, _referenceInto, _skipEmpty) {
        if (typeof _referenceInto == "undefined") {
            _referenceInto = false;
        }
        if (typeof _skipEmpty == "undefined") {
            _skipEmpty = false;
        }
        // Parse the given key by removing the "]"-chars and splitting at "["
        var indexes = this.explodeKey(_key);
        if (indexes.length == 0 && _skipEmpty)
            return null;
        var entry = this.data;
        for (var i = 0; i < indexes.length; i++) {
            // Abort if the current entry is not an object (associative array) and
            // we should descend further into it.
            var isObject = typeof entry === 'object';
            if (!isObject && !_referenceInto || entry == null || jQuery.isEmptyObject(entry)) {
                return null;
            }
            // Check whether the entry actually exists
            var idx = indexes[i];
            if (_skipEmpty && (!isObject || typeof entry[idx] == "undefined")) {
                return null;
            }
            entry = entry[idx];
        }
        return entry;
    };
    /**
     * Equivalent to the boetemplate::expand_name function.
     *
     * Expands variables inside the given identifier to their values inside the
     * content array.
     *
     * @param {string} _ident Key used to reference into managed array
     * @return {*}
     */
    et2_arrayMgr.prototype.expandName = function (_ident) {
        // Check whether the identifier refers to an index in the content array
        var is_index_in_content = _ident.charAt(0) == '@';
        // Check whether "$" occurs in the given identifier
        var pos_var = _ident.indexOf('$');
        if (pos_var >= 0 && (this.perspectiveData.row != null || !_ident.match(/\$\{?row\}?/))
            // Avoid messing with regex in validators
            && pos_var !== _ident.indexOf("$/")) {
            // Get the content array for the current row
            var row = typeof this.perspectiveData.row == 'number' ? this.perspectiveData.row : '';
            var row_cont = this.data[row] || {};
            // $cont is NOT root but current name-space in old eTemplate
            var cont = this.data; //getRoot().data;
            var _cont = this.data; // according to a grep only used in ImportExport just twice
            // Check whether the expression has already been compiled - if not,
            // try to compile it first. If an error occurs, the identifier
            // function is set to null
            if (typeof et2_arrayMgr.compiledExpressions[_ident] == "undefined") {
                try {
                    if (this.perspectiveData.row == null) {
                        // No row, compile for only top level content
                        // @ts-ignore
                        et2_arrayMgr.compiledExpressions[_ident] = et2_compilePHPExpression(_ident, ["cont", "_cont"]);
                    }
                    else {
                        // @ts-ignore
                        et2_arrayMgr.compiledExpressions[_ident] = et2_compilePHPExpression(_ident, ["row", "cont", "row_cont", "_cont"]);
                    }
                }
                catch (e) {
                    et2_arrayMgr.compiledExpressions[_ident] = null;
                    egw.debug("error", "Error while compiling PHP->JS ", e);
                }
            }
            // Execute the previously compiled expression, if it is not "null"
            // because compilation failed. The parameters have to be in the same
            // order as defined during compilation.
            if (et2_arrayMgr.compiledExpressions[_ident]) {
                try {
                    if (this.perspectiveData.row == null) {
                        // No row, exec with only top level content
                        _ident = et2_arrayMgr.compiledExpressions[_ident](cont, _cont);
                    }
                    else {
                        _ident = et2_arrayMgr.compiledExpressions[_ident](row, cont, row_cont, _cont);
                    }
                }
                catch (e) {
                    // only log error, as they are no real errors but missing data
                    egw.debug("log", typeof e == 'object' ? e.message : e);
                    _ident = null;
                }
            }
        }
        if (is_index_in_content && _ident) {
            // If an additional "@" is specified, this means that we have to return
            // the entry from the root element
            if (_ident.charAt(1) == '@') {
                return this.getRoot().getEntry(_ident.substr(2));
            }
            else {
                return this.getEntry(_ident.substr(1));
            }
        }
        return _ident;
    };
    et2_arrayMgr.prototype.parseBoolExpression = function (_expression) {
        // If the first char of the expression is a '!' this means, that the value
        // is to be negated.
        if (_expression.charAt(0) == '!') {
            return !this.parseBoolExpression(_expression.substr(1));
        }
        // Split the expression at a possible "="
        var parts = _expression.split('=');
        // Expand the first value
        var val = this.expandName(parts[0]);
        val = (typeof val == "undefined" || val === null) ? '' : '' + val;
        // If a second expression existed, test that one
        if (typeof parts[1] != "undefined") {
            // Expand the second value
            var checkVal = '' + this.expandName(parts[1]);
            // Values starting with / are treated as regular expression. It is
            // checked whether the first value matches the regular expression
            if (checkVal.charAt(0) == '/') {
                return (new RegExp(checkVal.substr(1, checkVal.length - 2)))
                    .test(val);
            }
            // Otherwise check for simple equality
            return val == checkVal;
        }
        return et2_evalBool(val);
    };
    /**
     * ?
     *
     * @param {object} _owner owner object
     * @param {(string|null|object)} _root string with key, null for whole data or object with data
     * @param {number?} _row key for into the _root for the desired row
     */
    et2_arrayMgr.prototype.openPerspective = function (_owner, _root, _row) {
        // Get the root node
        var root = typeof _root == "string" ? this.data[_root] :
            (_root == null ? this.data : _root);
        if (typeof root == "undefined" && typeof _root == "string")
            root = this.getEntry(_root);
        // Create a new content array manager with the given root
        var constructor = this.readOnly ? et2_readonlysArrayMgr : et2_arrayMgr;
        var mgr = new constructor(root, this);
        // Set the owner
        mgr.perspectiveData.owner = _owner;
        // Set the root key
        if (typeof _root == "string") {
            mgr.perspectiveData.key = _root;
        }
        // Set _row parameter
        if (typeof _row != "undefined") {
            mgr.perspectiveData.row = _row;
        }
        return mgr;
    };
    et2_arrayMgr.compiledExpressions = {};
    return et2_arrayMgr;
}());
exports.et2_arrayMgr = et2_arrayMgr;
/**
 * @augments et2_arrayMgr
 */
var et2_readonlysArrayMgr = /** @class */ (function (_super) {
    __extends(et2_readonlysArrayMgr, _super);
    function et2_readonlysArrayMgr() {
        var _this = _super !== null && _super.apply(this, arguments) || this;
        _this.readOnly = true;
        return _this;
    }
    /**
     * Find out if the given ID is readonly, according to the array data
     *
     * @memberOf et2_readonlysArrayMgr
     * @param _id
     * @param _attr
     * @param _parent
     * @returns
     */
    et2_readonlysArrayMgr.prototype.isReadOnly = function (_id, _attr, _parent) {
        var entry = null;
        if (_id != null) {
            if (_id.indexOf('$') >= 0 || _id.indexOf('@') >= 0) {
                _id = this.expandName(_id);
            }
            // readonlys was not namespaced in old eTemplate, therefore if we dont find data
            // under current namespace, we look into parent
            // (if there is anything namespaced, we will NOT look for parent!)
            var mgr = this;
            while (mgr.getParentMgr() && jQuery.isEmptyObject(mgr.data)) {
                mgr = mgr.getParentMgr();
            }
            entry = mgr.getEntry(_id);
        }
        // Let the array entry override the read only attribute entry
        if (typeof entry != "undefined" && !(typeof entry === 'object')) {
            return entry;
        }
        // If the attribute is set, return that
        if (typeof _attr != "undefined" && _attr !== null) {
            // Accept 'editable', but otherwise boolean
            return this.expandName(_attr) === 'editable' ? 'editable' : et2_evalBool(_attr);
        }
        // Otherwise take into accounf whether the parent is readonly
        if (typeof _parent != "undefined" && _parent) {
            return true;
        }
        // Otherwise return the default value
        entry = this.getEntry("__ALL__");
        return entry !== null && (typeof entry != "undefined");
    };
    /**
     * Override parent to handle cont and row_cont.
     *
     * Normally these should refer to the readonlys data, but that's not
     * useful, so we use the owner inside perspective data to expand using content.
     *
     * @param {string} ident Key for searching into the array.
     * @returns {*}
     */
    et2_readonlysArrayMgr.prototype.expandName = function (ident) {
        return this.perspectiveData.owner.getArrayMgr('content').expandName(ident);
    };
    return et2_readonlysArrayMgr;
}(et2_arrayMgr));
exports.et2_readonlysArrayMgr = et2_readonlysArrayMgr;
/**
 * Creates a new set of array managers
 *
 * @param _owner is the owner object of the array managers - this object (a widget)
 *    will free the array manager
 * @param _mgrs is the original set of array managers, the array managers are
 *    inside an associative array as recived from et2_widget::getArrayMgrs()
 * @param _data is an associative array of new data which will be merged into the
 *    existing array managers.
 * @param _row is the row for which the array managers will be opened.
 */
function et2_arrayMgrs_expand(_owner, _mgrs, _data, _row) {
    // Create a copy of the given _mgrs associative array
    var result = {};
    // Merge the given data associative array into the existing array managers
    for (var key in _mgrs) {
        result[key] = _mgrs[key];
        if (typeof _data[key] != "undefined") {
            // Open a perspective for the given data row
            var rowData = {};
            rowData[_row] = _data[key];
            result[key] = _mgrs[key].openPerspective(_owner, rowData, _row);
        }
    }
    // Return the resulting managers object
    return result;
}
exports.et2_arrayMgrs_expand = et2_arrayMgrs_expand;
//# sourceMappingURL=et2_core_arrayMgr.js.map