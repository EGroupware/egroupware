/**
 * eGroupWare eTemplate2 - JS widget class containing raw HTML
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
	jsapi.jsapi; // Needed for egw_seperateJavaScript
	jquery.jquery;
	et2_core_baseWidget;
*/

var et2_html = et2_valueWidget.extend({

	init: function() {
		this._super.apply(this, arguments);

		// Allow no child widgets
		this.supportedWidgetClasses = [];

		this.htmlNode = $j(document.createElement("span"));
		this.setDOMNode(this.htmlNode[0]);
	},

	loadContent: function(_data) {
		// Create an object containg the given value and an empty js string
		var html = {html: _data ? _data : '', js: ''};

		// Seperate the javascript from the given html. The js code will be
		// written to the previously created empty js string
		egw_seperateJavaScript(html);

		// Append the html to the parent element
		this.htmlNode.append(html.html);
		this.htmlNode.append(html.js);
	},

	set_value: function(_value) {
		this.htmlNode.empty();
		this.loadContent(_value);
	}

});

et2_register_widget(et2_html, ["html","htmlarea_ro"]);


