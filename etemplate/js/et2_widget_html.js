/**
 * EGroupware eTemplate2 - JS widget class containing raw HTML
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

/**
 * @augments et2_valueWidget
 */
var et2_html = et2_valueWidget.extend([et2_IDetachedDOM], 
{
	attributes: {
		'label': {
			'default': "",
			description: "The label is displayed by default in front (for radiobuttons behind) each widget (if not empty). If you want to specify a different position, use a '%s' in the label, which gets replaced by the widget itself. Eg. '%s Name' to have the label Name behind a checkbox. The label can contain variables, as descript for name. If the label starts with a '@' it is replaced by the value of the content-array at this index (with the '@'-removed and after expanding the variables).",
			ignore: false,
			name: "Label",
			translate: true,
			type: "string",
		},
		"needed": {
			"ignore": true
		}
	},
	
	/**
	 * Constructor
	 * 
	 * @memberOf et2_html
	 */
	init: function() {
		this._super.apply(this, arguments);

		// Allow no child widgets
		this.supportedWidgetClasses = [];

		this.htmlNode = $j(document.createElement("span"));
		if(this.options.label)
		{
			this.htmlNode.append('<span class="et2_label">'+this.options.label+'</span>');
		}
		this.setDOMNode(this.htmlNode[0]);
	},

	loadContent: function(_data) {
		// Create an object containg the given value and an empty js string
		var html = {html: _data ? _data : '', js: ''};

		// Seperate the javascript from the given html. The js code will be
		// written to the previously created empty js string
		egw_seperateJavaScript(html);

		// Append the html to the parent element
		if(this.options.label)
		{
			this.htmlNode.append('<span class="et2_label">'+this.options.label+'</span>');
		}
		this.htmlNode.append(html.html);
		this.htmlNode.append(html.js);
	},

	set_value: function(_value) {
		this.htmlNode.empty();
		this.loadContent(_value);
	},

	/**
	 * Code for implementing et2_IDetachedDOM
	 */
	getDetachedAttributes: function(_attrs)
	{
		_attrs.push("value", "class");
	},

	getDetachedNodes: function()
	{
		return [this.htmlNode[0]];
	},

	setDetachedAttributes: function(_nodes, _values)
	{
		this.htmlNode = jQuery(_nodes[0]);
		if(typeof _values['value'] !== 'undefined')
		{
			this.set_value(_values['value']);
		}
	}

});
et2_register_widget(et2_html, ["html","htmlarea_ro"]);

