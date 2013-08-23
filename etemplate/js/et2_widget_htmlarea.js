/**
 * EGroupware eTemplate2 - JS widget for HTML editing
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

/**
 * @augments et2_inputWidget
 */
var et2_htmlarea = et2_inputWidget.extend(
{
	modes: ['ascii','simple','extended','advanced'],
	
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
			'description': 'Have the toolbar expanded (visible)'
		},
		'base_href': {
			'name': 'Image base path',
			'default': et2_no_init,
			'type': 'string',
			'description': 'activates the browser for images at the path (relative to the docroot)'
		},
		'config': {
			// internal default configuration
			'name': 'Internal configuration',
			'type':'any',
			'default': et2_no_init,
			'description': 'Internal configuration - managed by preferences & framework, passed in here'
		},
	},

	legacyOptions: ['mode','height','width','expand_toolbar','base_href'],

	/**
	 * Constructor
	 * 
	 * @param _parent
	 * @param _attrs
	 * @memberOf et2_htmlarea
	 */
	init: function(_parent, _attrs) {
		// _super.apply is responsible for the actual setting of the params (some magic)
		this._super.apply(this, arguments);

		// Allow no child widgets
		this.supportedWidgetClasses = [];
		this.htmlNode = $j(document.createElement("textarea"))
			.css('height', this.options.height)
			.addClass('et2_textbox_ro');
		this.setDOMNode(this.htmlNode[0]);
	},
		
	transformAttributes: function(_attrs) {

		// Check mode, some apps jammed everything in there
		if(jQuery.inArray(_attrs['mode'], this.modes) < 0)
		{
			this.egw().debug("warn", "Invalid mode for '%s': %s Valid options:", _attrs['id'],_attrs['mode'], this.modes);
			var list = _attrs['mode'].split(',');
			for(var i = 0; i < list.length && i < this.legacyOptions.length; i++)
			{
				_attrs[this.legacyOptions[i]] = list[i];
			}
		}
		this._super.apply(this, arguments);
	},
		
	doLoadingFinished: function() {
		this._super.apply(this, arguments);
		if(this.mode == 'ascii') return;
		
		var self = this;
		var ckeditor;
		try
		{
			CKEDITOR.replace(this.dom_id,jQuery.extend({},this.options.config,this.options));
			ckeditor = CKEDITOR.instances[this.dom_id];
			ckeditor.setData(self.value);
			delete self.value;
		}
		catch (e)
		{
			if(CKEDITOR.instances[this.dom_id])
			{
				CKEDITOR.instances[this.dom_id].destroy();
			}
			if(this.htmlNode.ckeditor)
			{
				CKEDITOR.replace(this.dom_id,this.options.config);
				ckeditor = CKEDITOR.instances[this.dom_id];
				ckeditor.setData(self.value);
				delete self.value;
			}
		}
	},

	destroy: function() {
		try
		{
			//this.htmlNode.ckeditorGet().destroy(true);
			var ckeditor = CKEDITOR.instances[this.dom_id];
			if (ckeditor) ckeditor.destroy(true);
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
			var ckeditor = CKEDITOR.instances[this.dom_id];
			if (ckeditor)
			{
				ckeditor.setData(_value);
			}
			else
			{
				this.htmlNode.val(_value);				
				this.value = _value;
			}
		} catch (e) {
			// CK editor not ready - callback will do it
			this.value = _value;
		}
	},

	getValue: function() {
		try
		{
			//return this.htmlNode.ckeditorGet().getData();
			var ckeditor = CKEDITOR.instances[this.dom_id];
			return ckeditor ? ckeditor.getData() : this.htmlNode.val();
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

