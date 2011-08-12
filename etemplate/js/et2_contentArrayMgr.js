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

function et2_contentArrayMgr(_data, _parentMgr)
{
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
		et2_debug("error", "Invalid data passed to content array manager!");
		_data = {};
	}

	this.data = _data;

	// Holds information about the current perspective
	this.perspectiveData = {
		"owner": null,
		"key": null,
		"col": 0,
		"row": 0
	}
}

/**
 * Returns the root content array manager object
 */
et2_contentArrayMgr.prototype.getRoot = function()
{
	if (this.parentMgr != null)
	{
		return this.parentMgr.getRoot();
	}

	return this;
}

et2_contentArrayMgr.prototype.getValueForID = function(_id)
{
	if (typeof this.data[_id] != "undefined")
	{
		return this.data[_id];
	}

	return null;
}

/**
 * Returns the path to this content array manager perspective as an array
 * containing the key values
 * 
 * @param _path is used internally, do not supply it manually.
 */
et2_contentArrayMgr.prototype.getPath = function(_path)
{
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
}

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
et2_contentArrayMgr.prototype.getEntry = function(_key, _referenceInto,
	_skipEmpty)
{
	if (typeof _referenceInto == "undefined")
	{
		_referenceInto = false;
	}

	if (typeof _skipEmpty == "undefined")
	{
		_skipEmpty = false;
	}

	// Parse the given key by removing the "]"-chars and splitting at "["
	var indexes = _key.replace(/]/g,'').split('[');

	var entry = this.data;
	for (var i = 0; i < indexes.length; i++)
	{
		// Abort if the current entry is not an object (associative array) and
		// we should descend further into it.
		var isObject = entry instanceof Object;
		if (!isObject && !_referenceInto)
		{
			return false;
		}

		// Check whether the entry actually exists
		var idx = indexes[i];
		if (_skipEmpty && (!isObject || typeof entry[idx] == "undefined"))
		{
			return false;
		}

		entry = entry[idx];
	}

	return entry;
}

/**
 * Equivaltent to the boetemplate::expand_name function.
 *
 * Expands variables inside the given identifier to their values inside the
 * content array.
 */
et2_contentArrayMgr.prototype.expandName = function(_ident)
{
	// Check whether the identifier refers to an index in the content array
	var is_index_in_content = _ident.charAt(0) == '@';

	// Check whether "$" occurs in the given identifier
	var pos_var = _ident.indexOf('$');
	if (pos_var >= 0)
	{
		// TODO
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
}

et2_contentArrayMgr.prototype.parseBoolExpression = function(_expression)
{
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
				.match(val) ? true : false;
		}

		// Otherwise check for simple equality
		return val == checkVal;
	}

	return val != '' && (typeof val != "string" || val.toLowerCase() != "false");
}

et2_contentArrayMgr.prototype.openPerspective = function(_owner, _root, _col, _row)
{
	// Get the root node
	var root = typeof _root == "string" ? this.data[_root] :
		(_root == null ? this.data : _root);

	// Create a new content array manager with the given root
	var mgr = new et2_contentArrayMgr(root, this);

	// Set the owner
	mgr.perspectiveData.owner = _owner;

	// Set the root key
	if (typeof _root == "string")
	{
		mgr.perspectiveData.key = _root;
	}

	// Set the _col and _row parameter
	if (typeof _col != "undefined" && typeof _row != "undefined")
	{
		mgr.perspectiveData.col = _col;
		mgr.perspectiveData.row = _row;
	}

	return mgr;
}

