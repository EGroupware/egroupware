/**
 * eGroupWare eTemplate2 - JS widget for HTML editing
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2012
 * @version $Id$
 */

"use strict";

/*egw:uses
	jsapi.jsapi; // Needed for egw_seperateJavaScript
	jquery.jquery;
	/phpgwapi/js/ckeditor3/ckeditor.js;
	/phpgwapi/js/ckeditor3/config.js;
	/phpgwapi/js/ckeditor3/adapters/jquery.js;
	et2_core_baseWidget;
*/

var et2_htmlarea = et2_inputWidget.extend({

	attributes: {
		'mode': {
			'name': 'Mode',
			'description': 'One of {ascii|simple|extended|advanced}',
			'default': 'simple',
			'type': 'string'
		},
		'height': {
			'name': 'Height',
			'default': et2_no_init,
			'type': 'string'
		},
		'width': {
			'name': 'Width',
			'default': et2_no_init,
			'type': 'string'
		},
		'expand_toolbar': {
			'name': 'Expand Toolbar',
			'default': true,
			'type':'any',
		},
		'base_href': {
			'name': 'Image base path',
			'default': et2_no_init,
			'type': 'string',
			'description': 'activates the browser for images at the path (relative to the docroot)'
		},
		'config': {
			// internal default configuration
			'type':'any',
			'default': et2_no_init
		},
	},

	legacyOptions: ['mode','height','width','expand_toolbar','base_href'],

	ck_props: {},
	init: function(_parent, _attrs) {
		this.ck_props = _attrs['config'] ? _attrs['config'] : {};

		this._super.apply(this, arguments);

		// Allow no child widgets
		this.supportedWidgetClasses = [];

		
		this.htmlNode = $j(document.createElement("div"));
		this.setDOMNode(this.htmlNode[0]);
	},

	doLoadingFinished: function() {
		this._super.apply(this, arguments);
		this.htmlNode.ckeditor(function() {},this.ck_props);
	},

	destroy: function() {
		this.htmlNode.ckeditorGet().destroy(true);
	},
	set_value: function(_value) {
		this.htmlNode.val(_value);
	},

	getValue: function() {
		return this.htmlNode.val();
	}
});

et2_register_widget(et2_htmlarea, ["htmlarea"]);


