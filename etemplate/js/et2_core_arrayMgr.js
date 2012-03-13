/**
 * eGroupWare eTemplate2 - JS content array manager
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
 */

"use strict";

/*egw:uses
	et2_core_common;
	et2_core_inheritance;
	et2_core_phpExpressionCompiler;
*/

var et2_arrayMgr = Class.extend({

	splitIds: true,

	init: function(_data, _parentMgr) {
		if (typeof _parentMgr == "undefined")
		{
			_parentMgr = null;
		}

		// Copy the parent manager which is needed to access relative data when
		// being in a relative perspective of the manager
		this.parentMgr = _parentMgr;

		// Hold a reference to the data
		if (typeof _data == "undefined" || !_data)
		{
			egw.debug("error", "No data passed to content array manager!");
			_data = {};
		}

		// Expand sub-arrays that have been shmushed together, so further perspectives work
		if (this.splitIds)
		{
			for(var key in _data) {
				var indexes = key.split('&#x5B;');
				if (indexes.length > 1)
				{
					indexes = [indexes.shift(), indexes.join('&#x5B;')];
					indexes[1] = indexes[1].substring(0,indexes[1].length-6);
					var children = indexes[1].split('&#x5B;&#x5D;');
					if(children.length)
					{
						indexes = jQuery.merge([indexes[0]], children);
					}
				}
				if(indexes.length > 1)
				{
					var value = _data[key];
					var target = _data;
					for(var i = 0; i < indexes.length; i++) {
						if(typeof target[indexes[i]] == "undefined") {
							target[indexes[i]] = i == indexes.length-1 ? value : {};
						}
						target = target[indexes[i]];
					}
					delete _data[key];
				}
			}
		}

		this.data = _data;

		// Holds information about the current perspective
		this.perspectiveData = {
			"owner": null,
			"key": null,
			"row": null
		}
	},

	/**
	 * Returns the root content array manager object
	 */
	getRoot : function() {
		if (this.parentMgr != null)
		{
			return this.parentMgr.getRoot();
		}

		return this;
	},

/*	getValueForID : function(_id) {
		if (typeof this.data[_id] != "undefined")
		{
			return this.data[_id];
		}

		return null;
	},*/

	/**
	 * Returns the path to this content array manager perspective as an array
	 * containing the key values
	 * 
	 * @param _path is used internally, do not supply it manually.
	 */
	getPath : function(_path) {
		if (typeof _path == "undefined")
		{
			_path = [];
		}

		if (this.perspectiveData.key != null)
		{
			_path.push(this.perspectiveData.key);
		}

		if (this.parentMgr != null)
		{
			this.parentMgr.getPath(_path);
		}

		return _path;
	},

	/**
	 * Get array entry is the equivalent to the boetemplate get_array function.
	 * It returns a reference to the (sub) array with the given key. This also works
	 * for keys using the ETemplate referencing scheme like a[b][c]
	 *
	 * @param _key is the string index, may contain sub-indices like a[b]
	 * @param _referenceInto if true none-existing sub-arrays/-indices get created
	 * 	to be returned as reference, else false is returned. Defaults to false
	 * @param _skipEmpty returns false if _key is not present in this content array.
	 * 	Defaults to false.
	 */
	getEntry : function(_key, _referenceInto, _skipEmpty) {
		if (typeof _referenceInto == "undefined")
		{
			_referenceInto = false;
		}

		if (typeof _skipEmpty == "undefined")
		{
			_skipEmpty = false;
		}

		// Parse the given key by removing the "]"-chars and splitting at "["
		var indexes = [_key];

		if (this.splitIds)
		{
			indexes = _key.split('[');
			if (indexes.length > 1)
			{
				indexes = [indexes.shift(), indexes.join('[')];
				indexes[1] = indexes[1].substring(0,indexes[1].length-1);
				var children = indexes[1].split('][');
				if(children.length)
				{
					indexes = jQuery.merge([indexes[0]], children);
				}
			}
		}

		var entry = this.data;
		for (var i = 0; i < indexes.length; i++)
		{
			// Abort if the current entry is not an object (associative array) and
			// we should descend further into it.
			var isObject = typeof entry === 'object';
			if (!isObject && !_referenceInto || entry == null)
			{
				return null;
			}

			// Check whether the entry actually exists
			var idx = indexes[i];
			if (_skipEmpty && (!isObject || typeof entry[idx] == "undefined"))
			{
				return null;
			}

			entry = entry[idx];
		}

		return entry;
	},

	compiledExpressions: {},

	/**
	 * Equivaltent to the boetemplate::expand_name function.
	 *
	 * Expands variables inside the given identifier to their values inside the
	 * content array.
	 */
	expandName : function(_ident) {
		// Check whether the identifier refers to an index in the content array
		var is_index_in_content = _ident.charAt(0) == '@';

		// Check whether "$" occurs in the given identifier
		var pos_var = _ident.indexOf('$');
		if (pos_var >= 0 && this.perspectiveData.row != null)
		{
			// Get the content array for the current row
			var row = this.perspectiveData.row;
			var cont = this.data;
			var row_cont = cont[row];

			// Check whether the expression has already been compiled - if not,
			// try to compile it first. If an error occurs, the identifier
			// function is set to null
			var proto = this.constructor.prototype;
			if (typeof proto.compiledExpressions[_ident] == "undefined")
			{
				try
				{
					proto.compiledExpressions[_ident] = et2_compilePHPExpression(
						_ident, ["row", "cont", "row_cont"]);
				}
				catch(e)
				{
					proto.compiledExpressions[_ident] = null;
					egw.debug("error", "Error while compiling PHP->JS ", e);
				}
			}

			// Execute the previously compiled expression, if it is not "null"
			// because compilation failed. The parameters have to be in the same
			// order as defined during compilation.
			if (proto.compiledExpressions[_ident])
			{
				try
				{
					_ident = proto.compiledExpressions[_ident](row, cont, row_cont);
				}
				catch(e)
				{
					egw.debug("error", e);
				}
			}
		}

		if (is_index_in_content)
		{
			// If an additional "@" is specified, this means that we have to return
			// the entry from the root element
			if (_ident.charAt(1) == '@')
			{
				_ident = this.getRoot().getEntry(_ident.substr(2));
			}
			else
			{
				_ident = this.getEntry(_ident.substr(1));
			}
		}

		return _ident;
	},

	parseBoolExpression: function(_expression) {
		// If the first char of the expression is a '!' this means, that the value
		// is to be negated.
		if (_expression.charAt(0) == '!')
		{
			return !this.parseBoolExpression(_expression.substr(1));
		}

		// Split the expression at a possible "="
		var parts = _expression.split('=');

		// Expand the first value
		var val = this.expandName(parts[0]);

		// If a second expression existed, test that one
		if (typeof parts[1] != "undefined")
		{
			// Expand the second value
			var checkVal = this.expandName(parts[1]);

			// Values starting with / are treated as regular expression. It is
			// checked whether the first value matches the regular expression
			if (checkVal.charAt(0) == '/')
			{
				return (new RegExp(checkVal.substr(1, checkVal.length - 2)))
					.test(val) ? true : false;
			}

			// Otherwise check for simple equality
			return val == checkVal;
		}

		return et2_evalBool(val);
	},

	openPerspective: function(_owner, _root, _row)
	{
		// Get the root node
		var root = typeof _root == "string" ? this.data[_root] :
			(_root == null ? this.data : _root);
		if(typeof root == "undefined" && typeof _root == "string") root = this.getEntry(_root);

		// Create a new content array manager with the given root
		var mgr = new et2_arrayMgr(root, this);

		// Set the owner
		mgr.perspectiveData.owner = _owner;

		// Set the root key
		if (typeof _root == "string")
		{
			mgr.perspectiveData.key = _root;
		}

		// Set _row parameter
		if (typeof _row != "undefined")
		{
			mgr.perspectiveData.row = _row;
		}

		return mgr;
	}

});

var et2_readonlysArrayMgr = et2_arrayMgr.extend({

	splitIds: false,

	isReadOnly: function(_id, _attr, _parent) {
		var entry = null;

		if (_id != null)
		{
			entry = this.getEntry(_id);
		}

		// Let the array entry override the read only attribute entry
		if (typeof entry != "undefined" && !(typeof entry === 'object'))
		{
			return entry;
		}

		// If the attribute is set, return that
		if (typeof _attr != "undefined" && _attr !== null)
		{
			return et2_evalBool(_attr);
		}

		// Otherwise take into accounf whether the parent is readonly
		if (typeof _parent != "undefined" && _parent)
		{
			return true;
		}

		// Otherwise return the default value
		return (typeof this.getEntry("__ALL__") != "undefined");
	}

});

/**
 * Creates a new set of array managers
 *
 * @param _owner is the owner object of the array managers - this object (a widget)
 * 	will free the array manager
 * @param _mgrs is the original set of array managers, the array managers are
 * 	inside an associative array as recived from et2_widget::getArrayMgrs()
 * @param _data is an associative array of new data which will be merged into the
 * 	existing array managers.
 * @param _row is the row for which the array managers will be opened.
 */
function et2_arrayMgrs_expand(_owner, _mgrs, _data, _row)
{
	// Create a copy of the given _mgrs associative array
	var result = {};

	// Merge the given data associative array into the existing array managers
	for (var key in _mgrs)
	{
		result[key] = _mgrs[key];

		if (typeof _data[key] != "undefined")
		{
			// Open a perspective for the given data row
			var rowData = {};
			rowData[_row] = _data[key];

			result[key] = _mgrs[key].openPerspective(_owner, rowData, _row);
		}
	}

	// Return the resulting managers object
	return result;
}

