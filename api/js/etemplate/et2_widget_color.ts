/**
 * EGroupware eTemplate2 - JS Color picker object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2012
 * @version $Id$
 */

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	et2_core_inputWidget;
	et2_core_valueWidget;
*/

import {et2_register_widget, WidgetConfig} from "./et2_core_widget";
import {et2_valueWidget} from "./et2_core_valueWidget";
import {et2_inputWidget} from "./et2_core_inputWidget";
import {ClassWithAttributes} from "./et2_core_inheritance";

/**
 * Class which implements the "colorpicker" XET-Tag
 *
 */
export class et2_color extends et2_inputWidget
{
	private input: JQuery;
	private span: JQuery<HTMLElement>;
	private clear: JQuery<HTMLElement>;
	private image: JQuery<HTMLImageElement>;
	private cleared: boolean = true;
	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_color._attributes, _child || {}));
		// included via etemplate2.css
		//this.egw().includeCSS("phpgwapi/js/jquery/jpicker/css/jPicker-1.1.6.min.css");
		this.span = jQuery("<span class='et2_color'/>");
		this.image = <JQuery<HTMLImageElement>>jQuery("<img src='" + this.egw().image("non_loaded_bg") + "'/>")
			.appendTo(this.span)
			.on("click", function() {
				this.input.trigger('click')
			}.bind(this));
		this.input = jQuery("<input type='color'/>").appendTo(this.span)
			.on('change', function() {
				this.cleared = false;
				this.image.hide();
			}.bind(this));
		if (!this.options.readonly && !this.options.needed)
		{
			this.clear = jQuery("<span class='ui-icon clear'/>")
				.appendTo(this.span)
				.on("click", function()
			         {
				         this.set_value('');
				         return false;
			         }.bind(this)
				);
		}
		this.setDOMNode(this.span[0]);
	}

	getValue( )
	{
		var value = this.input.val();
		if(this.cleared || value === '#FFFFFF' || value === '#ffffff')
		{
			return '';
		}
		return value;
	}

	set_value( color)
	{
		if(!color)
		{
			color = '';
		}
		this.cleared = !color;
		this.image.toggle(!color);

		this.input.val(color);
	}
}
et2_register_widget(et2_color, ["colorpicker"]);

/**
 * et2_textbox_ro is the dummy readonly implementation of the textbox.
 * @augments et2_valueWidget
 */
export class et2_color_ro extends et2_valueWidget implements et2_IDetachedDOM
{
	private value: string;
	private $node: JQuery;
	/**
	 * Constructor
	 *
	 * @memberOf et2_color_ro
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, _child || {});

		this.value = "";
		this.$node = jQuery(document.createElement("div"))
			.addClass("et2_color");

		this.setDOMNode(this.$node[0]);
	}

	set_value( _value)
	{
		this.value = _value;

		if(!_value) _value = "inherit";
		this.$node.css("background-color", _value);
	}
	/**
	 * Code for implementing et2_IDetachedDOM
	 *
	 * @param {array} _attrs array to add further attributes to
	 */
	getDetachedAttributes(_attrs)
	{
		_attrs.push("value");
	}

	getDetachedNodes()
	{
		return [this.node];
	}

	setDetachedAttributes(_nodes, _values)
	{
		this.$node = jQuery(_nodes[0]);
		if(typeof _values["value"] != 'undefined')
		{
			this.set_value(_values["value"]);
		}
	}
}
et2_register_widget(et2_color_ro, ["colorpicker_ro"]);

