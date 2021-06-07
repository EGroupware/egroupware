/**
 * EGroupware eTemplate2 - JS widget class containing raw HTML
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Andreas Stöckel
 */

/*egw:uses
	jsapi.jsapi; // Needed for egw_seperateJavaScript
	/vendor/bower-asset/jquery/dist/jquery.js;
	et2_core_baseWidget;
*/

import {et2_valueWidget} from "./et2_core_valueWidget";
import {WidgetConfig, et2_register_widget} from "./et2_core_widget";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_IDetachedDOM} from "./et2_core_interfaces";
import {et2_no_init} from "./et2_core_common";

/**
 * @augments et2_valueWidget
 */
export class et2_html extends et2_valueWidget implements et2_IDetachedDOM
{
	static readonly _attributes : any = {
		'label': {
			'default': "",
			description: "The label is displayed by default in front (for radiobuttons behind) each widget (if not empty). If you want to specify a different position, use a '%s' in the label, which gets replaced by the widget itself. Eg. '%s Name' to have the label Name behind a checkbox. The label can contain variables, as descript for name. If the label starts with a '@' it is replaced by the value of the content-array at this index (with the '@'-removed and after expanding the variables).",
			ignore: false,
			name: "Label",
			translate: true,
			type: "string"
		},
		"needed": {
			"ignore": true
		},
		value: {
			name: "Value",
			description: "The value of the widget",
			type: "html",	// "string" would remove html tags by running html_entity_decode
			default: et2_no_init
		}
	};

	htmlNode : JQuery = null;

	/**
	 * Constructor
	 *
	 * @memberOf et2_html
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_html._attributes, _child || {}));

		// Allow no child widgets
		this.supportedWidgetClasses = [];

		this.htmlNode = jQuery(document.createElement("span"));
		if(this.getType() == 'htmlarea')
		{
			this.htmlNode.addClass('et2_textbox_ro');
		}
		if(this.options.label)
		{
			this.htmlNode.append('<span class="et2_label">'+this.options.label+'</span>');
		}
		this.setDOMNode(this.htmlNode[0]);
	}

	loadContent(_data)
	{
		// Create an object containg the given value and an empty js string
		let html = {html: _data ? _data : '', js: ''};

		// Separate the javascript from the given html. The js code will be
		// written to the previously created empty js string
		egw_seperateJavaScript(html);

		// Append the html to the parent element
		if(this.options.label)
		{
			this.htmlNode.append('<span class="et2_label">'+this.options.label+'</span>');
		}
		this.htmlNode.append(html.html);
		this.htmlNode.append(html.js);
	}

	set_value(_value)
	{
		this.htmlNode.empty();
		this.loadContent(_value);
	}

	/**
	 * Code for implementing et2_IDetachedDOM
	 *
	 * @param {array} _attrs
	 */
	getDetachedAttributes(_attrs)
	{
		_attrs.push("value", "class");
	}

	getDetachedNodes()
	{
		return [this.htmlNode[0]];
	}

	setDetachedAttributes(_nodes, _values)
	{
		this.htmlNode = jQuery(_nodes[0]);
		if(typeof _values['value'] !== 'undefined')
		{
			this.set_value(_values['value']);
		}
	}

}
et2_register_widget(et2_html, ["html","htmlarea_ro"]);


