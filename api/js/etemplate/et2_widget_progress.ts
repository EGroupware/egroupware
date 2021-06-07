/**
 * EGroupware eTemplate2 - JS Progrss object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Ralf Becker
 */

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	et2_core_interfaces;
	et2_core_valueWidget;
*/

import {et2_register_widget, WidgetConfig} from "./et2_core_widget";
import {et2_valueWidget} from "./et2_core_valueWidget";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_IDetachedDOM} from "./et2_core_interfaces";
import {egw} from "../jsapi/egw_global";

/**
 * Class which implements the "progress" XET-Tag
 *
 * @augments et2_valueWidget
 */
export class et2_progress extends et2_valueWidget implements et2_IDetachedDOM
{
	static readonly _attributes : any = {
		"href": {
			"name": "Link Target",
			"type": "string",
			"description": "Link URL, empty if you don't wan't to display a link."
		},
		"extra_link_target": {
			"name": "Link target",
			"type": "string",
			"default": "_self",
			"description": "Link target descriptor"
		},
		"extra_link_popup": {
			"name": "Popup",
			"type": "string",
			"description": "widthxheight, if popup should be used, eg. 640x480"
		},
		"label": {
			"name": "Label",
			"default": "",
			"type": "string",
			"description": "The label is displayed as the title.  The label can contain variables, as descript for name. If the label starts with a '@' it is replaced by the value of the content-array at this index (with the '@'-removed and after expanding the variables).",
			"translate": true
		}
	};

	public static readonly legacyOptions : string[] = ["href", "extra_link_target", "imagemap", "extra_link_popup", "id"];
	private progress : HTMLElement = null;

	/**
	 * Constructor
	 *
	 * @memberOf et2_progress
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_progress._attributes, _child || {}));

		let outer = document.createElement("div");
		outer.className = "et2_progress";
		this.progress = document.createElement("div");
		this.progress.style.width = "0";
		outer.appendChild(this.progress);

		if (this.options.href)
		{
			outer.className += ' et2_clickable';
		}
		if(this.options["class"])
		{
			outer.className += ' '+this.options["class"];
		}
		this.setDOMNode(outer);	// set's this.node = outer
	}

	click(e)
	{
		super.click(e);

		if(this.options.href)
		{
			this.egw().open_link(this.options.href, this.options.extra_link_target, this.options.extra_link_popup);
		}
	}

	// setting the value as width of the progress-bar
	set_value(_value)
	{
		super.set_value(_value);
		_value = parseInt(_value)+"%";	// make sure we have percent attached
		this.progress.style.width = _value;
		if (!this.options.label) this.set_label(_value);
	}

	// set's label as title of this.node
	set_label(_value)
	{
		this.node.title = _value;
	}

	// set's class of this.node; preserve baseclasses et2_progress and if this.options.href is set et2_clickable
	set_class(_value)
	{
		let baseClass = "et2_progress";
		if (this.options.href)
		{
			baseClass += ' et2_clickable';
		}
		this.node.setAttribute('class', baseClass + ' ' + _value);
	}

	set_href(_value)
	{
		if (!this.isInTree())
		{
			return false;
		}

		this.options.href = _value;
		if (_value)
		{
			jQuery(this.node).addClass('et2_clickable')
				.wrapAll('<a href="'+_value+'"></a>"');

			let href = this.options.href;
			let popup = this.options.extra_link_popup;
			let target = this.options.extra_link_target;
			jQuery(this.node).parent().click(function(e)
			{
				egw.open_link(href,target,popup);
				e.preventDefault();
				return false;
			});
		}
		else if (jQuery(this.node).parent('a').length)
		{
			jQuery(this.node).removeClass('et2_clickable')
				.unwrap();
		}

		return true;
	}

	/**
	 * Implementation of "et2_IDetachedDOM" for fast viewing in gridview
	 *
	 * * @param {array} _attrs array to add further attributes to
	 */
	getDetachedAttributes(_attrs)
	{
		_attrs.push("value", "label", "href");
	}

	getDetachedNodes()
	{
		return [this.node, this.progress];
	}

	setDetachedAttributes(_nodes, _values)
	{
		// Set the given DOM-Nodes
		this.node = _nodes[0];
		this.progress = _nodes[1];

		// Set the attributes
		if (_values["label"])
		{
			this.set_label(_values["label"]);
		}
		if (_values["value"])
		{
			this.set_value(_values["value"]);
		}
		else if (_values["label"])
		{
			this.set_value(_values["label"]);
		}
		if(_values["href"])
		{
			jQuery(this.node).addClass('et2_clickable');
			this.set_href(_values["href"]);
		}
	}
}
et2_register_widget(et2_progress, ["progress"]);

