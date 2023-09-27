/**
 * EGroupware eTemplate2 - JS content array manager
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Andreas Stöckel
 */

/*egw:uses
	et2_core_common;
	egw_inheritance;
	et2_core_phpExpressionCompiler;
*/

import {et2_evalBool} from "./et2_core_common";
import type {et2_widget} from "./et2_core_widget";
import {egw} from "../jsapi/egw_global";
import {et2_compilePHPExpression} from "./et2_core_phpExpressionCompiler";

/**
 * Manage access to various template customisation arrays passed to etemplate->exec().
 *
 * This manages access to content, modifications and readonlys arrays
 */
export class et2_arrayMgr
{
	splitIds : boolean = true;
	public data : object;
	// Holds information about the current perspective
	public perspectiveData : { owner : et2_widget; row : number; key : string } = {
		"owner": null,
		"key": null,
		"row": null
	};
	protected static compiledExpressions : object = {};
	private readonly _parentMgr : et2_arrayMgr;
	protected readOnly : boolean = false;

	/**
	 * Constructor
	 *
	 * @memberOf et2_arrayMgr
	 * @param _data
	 * @param _parentMgr
	 */
	constructor(_data : object = {}, _parentMgr? : et2_arrayMgr)
	{
		if(typeof _parentMgr == "undefined")
		{
			_parentMgr = null;
		}

		// Copy the parent manager which is needed to access relative data when
		// being in a relative perspective of the manager
		this._parentMgr = _parentMgr;

		// Hold a reference to the data
		if(typeof _data == "undefined" || !_data)
		{
			egw.debug("log", "No data passed to content array manager.  Probably a mismatch between template namespaces and data.");
			_data = {};
		}

		// Expand sub-arrays that have been shmushed together, so further perspectives work
		// Shmushed keys look like: ${row}[info_cat]
		// Expanded: ${row}: Object{info_cat: ..value}
		if(this.splitIds)
		{
			// For each index, we need a key: {..} sub array
			for(let key in _data)
			{
				// Split up indexes
				const indexes = key.replace(/&#x5B;/g, "[").split('[');

				// Put data in the proper place
				if(indexes.length > 1)
				{
					const value = _data[key];
					let target = _data;
					for(let i = 0; i < indexes.length; i++)
					{
						indexes[i] = indexes[i].replace(/&#x5D;/g, '').replace(']', '');
						if(typeof target[indexes[i]] == "undefined" || target[indexes[i]] === null)
						{
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
	getRoot() : et2_arrayMgr
	{
		if(this._parentMgr != null)
		{
			return this._parentMgr.getRoot();
		}

		return this;
	}

	getParentMgr() : et2_arrayMgr
	{
		return this._parentMgr;
	}

	getPerspectiveData() : { owner : et2_widget; row : number; key : string }
	{
		return this.perspectiveData;
	}

	setPerspectiveData(new_perspective : { owner : et2_widget; row : number; key : string })
	{
		this.perspectiveData = new_perspective;
	}

	setRow(new_row : number)
	{
		this.perspectiveData.row = new_row;
	}

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
	explodeKey(_key : string) : string[]
	{
		if(!_key || typeof _key == 'string' && _key.trim() === "")
		{
			return [];
		}

		// Parse the given key by removing the "]"-chars and splitting at "["
		let indexes = [_key];

		if(typeof _key === "string")
		{
			_key = _key.replace(/&#x5B;/g, "[").replace(/&#x5D;/g, "]");
			indexes = _key.split('[');
		}
		if(indexes.length > 1)
		{
			indexes = [indexes.shift(), indexes.join('[')];
			indexes[1] = indexes[1].substring(0, indexes[1].length - 1);
			const children = indexes[1].split('][');
			if(children.length)
			{
				indexes = jQuery.merge([indexes[0]], children);
			}
		}
		return indexes;
	}

	/**
	 * Returns the path to this content array manager perspective as an array
	 * containing the key values
	 *
	 * @param _path is used internally, do not supply it manually.
	 */
	getPath(_path? : string[]) : string[]
	{
		if(typeof _path == "undefined")
		{
			_path = [];
		}

		if(this.perspectiveData.key != null)
		{
			// prepend components of this.perspectiveData.key to path, can be more then one eg. "nm[rows]"
			_path = this.perspectiveData.key.replace(/]/g, '').split('[').concat(_path);
		}

		if(this._parentMgr != null)
		{
			_path = this._parentMgr.getPath(_path);
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
	 *    to be returned as reference, else false is returned. Defaults to false
	 * @param _skipEmpty returns null if _key is not present in this content array.
	 *    Defaults to false.
	 */
	getEntry(_key : string, _referenceInto? : boolean, _skipEmpty? : boolean) : any
	{
		if(typeof _referenceInto == "undefined")
		{
			_referenceInto = false;
		}

		if(typeof _skipEmpty == "undefined")
		{
			_skipEmpty = false;
		}

		// Parse the given key by removing the "]"-chars and splitting at "["
		const indexes = this.explodeKey(_key);
		if(indexes.length == 0 && _skipEmpty)
		{
			return null;
		}

		let entry = this.data;
		for(let i = 0; i < indexes.length; i++)
		{
			// Abort if the current entry is not an object (associative array) and
			// we should descend further into it.
			const isObject = typeof entry === 'object';
			if(!isObject && !_referenceInto || entry == null || jQuery.isEmptyObject(entry))
			{
				return null;
			}

			// Check whether the entry actually exists
			const idx = indexes[i];
			if(_skipEmpty && (!isObject || typeof entry[idx] == "undefined"))
			{
				return null;
			}

			entry = entry[idx];
		}

		return entry;
	}

	/**
	 * Equivalent to the boetemplate::expand_name function.
	 *
	 * Expands variables inside the given identifier to their values inside the
	 * content array.
	 *
	 * @param {string} _ident Key used to reference into managed array
	 * @return {*}
	 */
	expandName(_ident : string) : string | object
	{
		// Check whether the identifier refers to an index in the content array
		const is_index_in_content = _ident.charAt(0) == '@';

		// Check whether "$" occurs in the given identifier
		const pos_var = _ident.indexOf('$');
		if(pos_var >= 0 && (this.perspectiveData.row != null || !_ident.match(/\$\{?row\}?/))
			// Avoid messing with regex in validators
			&& pos_var !== _ident.indexOf("$/")
		)
		{
			// Get the content array for the current row
			const row = typeof this.perspectiveData.row == 'number' ? this.perspectiveData.row : '';
			const row_cont = this.data[row] || {};
			// $cont is NOT root but current name-space in old eTemplate
			const cont = this.data;//getRoot().data;
			const _cont = this.data;// according to a grep only used in ImportExport just twice

			// Check whether the expression has already been compiled - if not,
			// try to compile it first. If an error occurs, the identifier
			// function is set to null
			if(typeof et2_arrayMgr.compiledExpressions[_ident] == "undefined")
			{
				try
				{
					if(this.perspectiveData.row == null)
					{
						// No row, compile for only top level content
						// @ts-ignore
						et2_arrayMgr.compiledExpressions[_ident] = et2_compilePHPExpression(
							_ident, ["cont", "_cont"]);
					}
					else
					{
						// @ts-ignore
						et2_arrayMgr.compiledExpressions[_ident] = et2_compilePHPExpression(
							_ident, ["row", "cont", "row_cont", "_cont"]);
					}
				}
				catch(e)
				{
					et2_arrayMgr.compiledExpressions[_ident] = null;
					egw.debug("error", "Error while compiling PHP->JS ", e);
				}
			}

			// Execute the previously compiled expression, if it is not "null"
			// because compilation failed. The parameters have to be in the same
			// order as defined during compilation.
			if(et2_arrayMgr.compiledExpressions[_ident])
			{
				try
				{
					if(this.perspectiveData.row == null)
					{
						// No row, exec with only top level content
						_ident = et2_arrayMgr.compiledExpressions[_ident](cont, _cont);
					}
					else
					{
						_ident = et2_arrayMgr.compiledExpressions[_ident](row, cont, row_cont, _cont);
					}
				}
				catch(e)
				{
					// only log error, as they are no real errors but missing data
					egw.debug("log", typeof e == 'object' ? e.message : e);
					_ident = null;
				}
			}
		}

		if(is_index_in_content && _ident)
		{
			// If an additional "@" is specified, this means that we have to return
			// the entry from the root element
			if(_ident.charAt(1) == '@')
			{
				return this.getRoot().getEntry(_ident.substr(2));
			}
			else
			{
				return this.getEntry(_ident.substr(1));
			}
		}

		return _ident;
	}

	parseBoolExpression(_expression : string|number|boolean|undefined)
	{
		if (typeof _expression === "boolean")
		{
			return _expression;
		}

		if(typeof _expression === "undefined" || _expression === null)
		{
			return false;
		}

		if(typeof _expression === "number")
		{
			return !!_expression;
		}

		// Check whether "$" occurs in the given identifier, don't parse rows if we're not in a row
		// This saves booleans in repeating rows from being parsed too early - we'll parse again when repeating
		if(_expression.indexOf('$') >= 0 && this.perspectiveData.row == null && _expression.match(/\$\{?row\}?/))
		{
			return _expression;
		}

		// If the first char of the expression is a '!' this means, that the value
		// is to be negated.
		if(_expression.charAt(0) == '!')
		{
			return !this.parseBoolExpression(_expression.substr(1));
		}

		// Split the expression at a possible "="
		const parts = _expression.split('=');

		// Expand the first value
		let val = this.expandName(parts[0]);
		val = (typeof val == "undefined" || val === null) ? '' : '' + val;

		// If a second expression existed, test that one
		if(typeof parts[1] != "undefined")
		{
			// Expand the second value
			const checkVal = '' + this.expandName(parts[1]);

			// Values starting with / are treated as regular expression. It is
			// checked whether the first value matches the regular expression
			if(checkVal.charAt(0) == '/')
			{
				return (new RegExp(checkVal.substr(1, checkVal.length - 2)))
					.test(val);
			}

			// Otherwise check for simple equality
			return val == checkVal;
		}

		return et2_evalBool(val);
	}

	/**
	 * ?
	 *
	 * @param {object} _owner owner object
	 * @param {(string|null|object)} _root string with key, null for whole data or object with data
	 * @param {number?} _row key for into the _root for the desired row
	 */
	openPerspective(_owner : et2_widget, _root : (string | null | object), _row? : number | null) : et2_arrayMgr
	{
		// Get the root node
		let root = typeof _root == "string" ? this.data[_root] :
				   (_root == null ? this.data : _root);
		if(typeof root == "undefined" && typeof _root == "string")
		{
			root = this.getEntry(_root);
		}

		// Create a new content array manager with the given root
		const constructor = this.readOnly ? et2_readonlysArrayMgr : et2_arrayMgr;
		const mgr = new constructor(root, this);

		// Set the owner
		mgr.perspectiveData.owner = _owner;

		// Set the root key
		if(typeof _root == "string")
		{
			mgr.perspectiveData.key = _root;
		}

		// Set _row parameter
		if(typeof _row != "undefined")
		{
			mgr.perspectiveData.row = _row;
		}

		return mgr;
	}

}

/**
 * @augments et2_arrayMgr
 */
export class et2_readonlysArrayMgr extends et2_arrayMgr
{

	readOnly : boolean = true;

	/**
	 * Find out if the given ID is readonly, according to the array data
	 *
	 * @memberOf et2_readonlysArrayMgr
	 * @param _id
	 * @param _attr
	 * @param _parent
	 * @returns
	 */
	isReadOnly(_id : string, _attr : string, _parent? : et2_arrayMgr) : boolean | string
	{
		let entry = null;

		if(_id != null)
		{
			if(_id.indexOf('$') >= 0 || _id.indexOf('@') >= 0)
			{
				_id = this.expandName(_id);
			}
			// readonlys was not namespaced in old eTemplate, therefore if we dont find data
			// under current namespace, we look into parent
			// (if there is anything namespaced, we will NOT look for parent!)
			let mgr : et2_arrayMgr = this;
			while(mgr.getParentMgr() && jQuery.isEmptyObject(mgr.data))
			{
				mgr = mgr.getParentMgr();
			}
			entry = mgr.getEntry(_id);
		}

		// Let the array entry override the read only attribute entry
		if(typeof entry != "undefined" && !(typeof entry === 'object'))
		{
			return entry;
		}

		// If the attribute is set, return that
		if(typeof _attr != "undefined" && _attr !== null)
		{
			// Accept 'editable', but otherwise boolean
			return typeof _attr === 'string' && this.expandName(_attr) === 'editable' ? 'editable' : et2_evalBool(_attr);
		}

		// Otherwise take into accounf whether the parent is readonly
		if(typeof _parent != "undefined" && _parent)
		{
			return true;
		}

		// Otherwise return the default value
		entry = this.getEntry("__ALL__");
		return entry !== null && (typeof entry != "undefined");
	}

	/**
	 * Override parent to handle cont and row_cont.
	 *
	 * Normally these should refer to the readonlys data, but that's not
	 * useful, so we use the owner inside perspective data to expand using content.
	 *
	 * @param {string} ident Key for searching into the array.
	 * @returns {*}
	 */
	expandName(ident : string) : any
	{
		return this.perspectiveData.owner.getArrayMgr('content').expandName(ident);
	}
}

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
export function et2_arrayMgrs_expand(_owner : et2_widget, _mgrs : object, _data : object, _row : number)
{
	// Create a copy of the given _mgrs associative array
	let result = {};

	// Merge the given data associative array into the existing array managers
	for(let key in _mgrs)
	{
		result[key] = _mgrs[key];

		if(typeof _data[key] != "undefined")
		{
			// Open a perspective for the given data row
			let rowData = {};
			rowData[_row] = _data[key];

			result[key] = _mgrs[key].openPerspective(_owner, rowData, _row);
		}
	}

	// Return the resulting managers object
	return result;
}