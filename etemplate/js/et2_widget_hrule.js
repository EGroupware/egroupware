/**
 * EGroupware eTemplate2 - JS HRule object
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
	et2_core_baseWidget;
*/

/**
 * Class which implements the hrule tag
 * 
 * @augments et2_baseWidget
 */ 
var et2_hrule = et2_baseWidget.extend(
{
	/**
	 * Constructor
	 * 
	 * @memberOf et2_hrule
	 */
	init: function() {
		this._super.apply(this, arguments);

		this.setDOMNode(document.createElement("hr"));
	}
});
et2_register_widget(et2_hrule, ["hrule"]);

