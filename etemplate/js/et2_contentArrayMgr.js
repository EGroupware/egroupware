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
	this.data = _data;
}

et2_contentArrayMgr.prototype.getValueForID = function(_id)
{
	if (typeof this.data[_id] != "undefined")
	{
		return this.data[_id];
	}

	return null;
}

et2_contentArrayMgr.prototype.openPerspective = function(_rootId)
{
	return new et2_contentArrayMgr(this._data[_rootId], this);
}

