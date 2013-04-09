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
	/phpgwapi/js/ckeditor/ckeditor.js;
	/phpgwapi/js/ckeditor/config.js;
	/phpgwapi/js/ckeditor/adapters/jquery.js;
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

	init: function(_parent, _attrs) {
		// _super.apply is responsible for the actual setting of the params (some magic)
		this._super.apply(this, arguments);

		// Allow no child widgets
		this.supportedWidgetClasses = [];

		
		this.htmlNode = $j(document.createElement("div"));
		this.setDOMNode(this.htmlNode[0]);
	},

	doLoadingFinished: function() {
		this._super.apply(this, arguments);
		var self = this;
		var ckeditor;
		try
		{
			CKEDITOR.replace(this.id,this.options.config);
			ckeditor = CKEDITOR.instances[this.id];
			ckeditor.setData(self.value);
			delete self.value;
		}
		catch (e)
		{
			if(CKEDITOR.instances[this.id])
			{
				CKEDITOR.instances[this.id].destroy();
			}
			if(this.htmlNode.ckeditor)
			{
				CKEDITOR.replace(this.id,this.options.config);
				ckeditor = CKEDITOR.instances[this.id];
				ckeditor.setData(self.value);
				delete self.value;
			}
		}
	},

	destroy: function() {
		try
		{
			//this.htmlNode.ckeditorGet().destroy(true);
			ckeditor = CKEDITOR.instances[this.id];
			ckeditor.destroy(true);
		}
		catch (e)
		{
			this.egw().debug("warn",e);
			this.htmlNode = null;
		}
	},
	set_value: function(_value) {
		try {
			//this.htmlNode.ckeditorGet().setData(_value);
			ckeditor = CKEDITOR.instances[this.id];
			ckeditor.setData(_value);
		} catch (e) {
			// CK editor not ready - callback will do it
			this.value = _value;
		}
	},

	getValue: function() {
		try
		{
			//return this.htmlNode.ckeditorGet().getData();
			ckeditor = CKEDITOR.instances[this.id];
			return ckeditor.getData();
		}
		catch (e)
		{
			// CK Error
			this.egw().debug("error",e);
			return null;
		}
	}
});

et2_register_widget(et2_htmlarea, ["htmlarea"]);


