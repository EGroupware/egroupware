/**
 * eGroupWare eTemplate2 - Contains the dataview base object.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage dataview
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
 */

"use strict"

/*egw:uses
	et2_core_inheritance;
*/

var et2_dataview_IInvalidatable = new Interface({

	invalidate: function() {}

});

var et2_dataview_IDataRow = new Interface({

	updateData: function(_data) {}

});

var et2_dataview_IViewRange = new Interface({

	setViewRange: function(_range) {}

});
