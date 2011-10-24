/**
 * eGroupWare eTemplate2 - JS Box object
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
	jquery.jquery;
	et2_core_baseWidget;
*/

/**
 * Class which implements the hbox and vbox tag
 */ 
var et2_box = et2_baseWidget.extend([et2_IDetachedDOM], {

	createNamespace: true,

	init: function() {
		this._super.apply(this, arguments);

		this.div = $j(document.createElement("div"))
			.addClass("et2_" + this._type)
			.addClass("et2_box_widget");

		this.setDOMNode(this.div[0]);
	},

	/**
         * Code for implementing et2_IDetachedDOM
	 * This doesn't need to be implemented.
	 * Individual widgets are detected and handled by the grid, but the interface is needed for this to happen
         */
        getDetachedAttributes: function(_attrs)
        {
        },

        getDetachedNodes: function()
        {
		return [this.getDOMNode()];
        },

        setDetachedAttributes: function(_nodes, _values)
        {
        }

});

et2_register_widget(et2_box, ["vbox", "box"]);

