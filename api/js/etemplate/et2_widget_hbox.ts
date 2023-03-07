/**
 * EGroupware eTemplate2 - JS HBox object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Andreas Stöckel
 */

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	et2_core_baseWidget;
*/

import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_register_widget, et2_widget, WidgetConfig} from "./et2_core_widget";
import {et2_baseWidget} from "./et2_core_baseWidget";
import {et2_grid} from "./et2_widget_grid";
import {et2_filteredNodeIterator} from "./et2_core_xml";
import {et2_cloneObject} from "./et2_core_common";
import {et2_IAligned} from "./et2_core_interfaces";

/**
 * Class which implements hbox tag
 *
 * @augments et2_baseWidget
 */
export class et2_hbox extends et2_baseWidget
{
	alignData : any = {
		"hasAlign": false,
		"hasLeft": false,
		"hasCenter": false,
		"hasRight": false,
		"lastAlign": "left"
	};
	leftDiv : JQuery = null;
	rightDiv : JQuery = null;
	div : JQuery = null;

	/**
	 * Constructor
	 *
	 * @memberOf et2_hbox
	 */
	constructor(_parent?, _attrs? : WidgetConfig, _child? : object)
	{
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_hbox._attributes, _child || {}));

		this.leftDiv = null;
		this.rightDiv = null;

		this.div = jQuery(document.createElement("div"))
			.addClass("et2_" + super.getType())
			.addClass("et2_box_widget");

		super.setDOMNode(this.div[0]);
	}

	_createNamespace() : boolean
	{
		return true;
	}

	_buildAlignCells() {
		if (this.alignData.hasAlign)
		{
			// Check whether we have more than one type of align
			let mto = (this.alignData.hasLeft && this.alignData.hasRight) ||
				(this.alignData.hasLeft && this.alignData.hasCenter) ||
				(this.alignData.hasCenter && this.alignData.hasRight);

			if (!mto)
			{
				// If there is only one type of align, we simply have to set
				// the align of the top container
				if (this.alignData.lastAlign != "left")
				{
					this.div.addClass("et2_hbox_al_" + this.alignData.lastAlign);
				}
			}
			else
			{
				// Create an additional container for elements with align type
				// "right"
				if (this.alignData.hasRight)
				{
					this.rightDiv = jQuery(document.createElement("div"))
						.addClass("et2_hbox_right")
						.appendTo(this.div);
				}

				// Create an additional container for elements with align type
				// left, as the top container is used for the centered elements
				if (this.alignData.hasCenter)
				{
					// Create the left div if an element is centered
					this.leftDiv = jQuery(document.createElement("div"))
						.addClass("et2_hbox_left")
						.appendTo(this.div);

					this.div.addClass("et2_hbox_al_center");
				}
			}
		}
	}

	/**
	 * The overwritten loadFromXML function checks whether any child element has
	 * a special align value.
	 *
	 * @param {object} _node
	 */
	loadFromXML(_node)
	{
		// Check whether any child node has an alignment tag
		et2_filteredNodeIterator(_node, function(_node)
		{
			let align = _node.getAttribute("align");

			if (!align)
			{
				align = "left";
			}

			if (align != "left")
			{
				this.alignData.hasAlign = true;
			}

			this.alignData.lastAlign = align;

			switch (align)
			{
				case "left":
					this.alignData.hasLeft = true;
					break;
				case "right":
					this.alignData.hasRight = true;
					break;
				case "center":
					this.alignData.hasCenter = true;
					break;
			}
		}, this);

		// Build the align cells
		this._buildAlignCells();

		// Load the nodes as usual
		super.loadFromXML(_node);
	}

	assign(_obj)
	{
		// Copy the align data and the cells from the object which should be
		// assigned
		this.alignData = et2_cloneObject(_obj.alignData);
		this._buildAlignCells();

		// Call the inherited assign function
		super.assign(_obj);
	}

	getDOMNode(_sender? : et2_widget) {
		// Return a special align container if this hbox needs it
		if (_sender != this && this.alignData.hasAlign)
		{
			// Check whether we've create a special container for the widget
			let align = (_sender?.implements(et2_IAligned) ?
				_sender?.get_align() : "left");

			if (align == "left" && this.leftDiv != null)
			{
				return this.leftDiv[0];
			}

			if (align == "right" && this.rightDiv != null)
			{
				return this.rightDiv[0];
			}
		}

		// Normally simply return the hbox-div
		return super.getDOMNode(_sender);
	}

	/**
	 * Tables added to the root node need to be inline instead of blocks
	 *
	 * @param {et2_widget} child child-widget to add
	 */
	addChild(child) {
		super.addChild(child);
		if(child.instanceOf && child.instanceOf(et2_grid) && this.isAttached() || child._type == 'et2_grid' && this.isAttached())
		{
			jQuery(child.getDOMNode(child)).css("display", "inline-table");
		}
	}
}
et2_register_widget(et2_hbox, ["hbox", "old-hbox"]);


