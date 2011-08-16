/**
 * eGroupWare eTemplate2 - JS widget class containing styles
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
	et2_widget;
*/

/**
 * Function which appends the encapsulated style data to the head tag of the
 * page.
 *
 * TODO: The style data could be parsed for rules and appended using the JS
 * stylesheet interface, allowing the style only to modifiy nodes of the current
 * template.
 */
var et2_styles = et2_widget.extend({

	init: function() {
		this._super.apply(this, arguments);

		// Allow no child widgets
		this.supportedWidgetClasses = [];

		// Create the textnode which will contain the style data
		this.textNode = document.createTextNode();

		// Create the style node and append it to the head node
		this.styleNode = document.createElement("style");
		this.styleNode.setAttribute("type", "text/css");
		this.styleNode.appendChild(this.textNode);
		
		(document.getElementsByTagName("head")[0]).appendChild(this.styleNode);
	},

	destroy: function() {
		this._super.apply(this, arguments);

		// Remove the style node again and delete any reference to it
		(document.getElementsByTagName("head")[0]).removeChild(this.styleNode);
		this.styleNode = null;
	},

	loadContent: function(_content) {
		// Set the style data
		this.textNode.data = _content;
	}

});

et2_register_widget(et2_styles, ["styles"]);

