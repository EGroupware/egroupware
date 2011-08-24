/**
 * eGroupWare eTemplate2 - JS HRule object
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
	et2_baseWidget;
*/

/**
 * Class which implements the hrule tag
 */ 
var et2_hrule = et2_baseWidget.extend({

	init: function() {
		this._super.apply(this, arguments);

		this.setDOMNode(document.createElement("hr"));
	}

});

et2_register_widget(et2_hrule, ["hrule"]);

