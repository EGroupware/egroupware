/**
 * eGroupWare eTemplate2 - JS Box object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id: et2_box.js 36147 2011-08-16 13:12:39Z igel457 $
 */

"use strict";

/*egw:uses
	jquery.jquery;
	et2_core_baseWidget;
*/

/**
 * Class which implements the hbox and vbox tag
 */ 
var et2_hbox = et2_baseWidget.extend({

	createNamespace: true,

	init: function() {
		this._super.apply(this, arguments);

		this.alignData = {
			"hasAlign": false,
			"hasLeft": false,
			"hasCenter": false,
			"hasRight": false,
			"lastAlign": "left"
		};

		this.leftDiv = null;
		this.rightDiv = null;

		this.div = $j(document.createElement("div"))
			.addClass("et2_" + this._type)
			.addClass("et2_box_widget");

		this.setDOMNode(this.div[0]);
	},

	_buildAlignCells: function() {
		if (this.alignData.hasAlign)
		{
			// Check whether we have more than one type of align
			var mto = (this.alignData.hasLeft && this.alignData.hasRight) ||
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
					this.rightDiv = $j(document.createElement("div"))
						.addClass("et2_hbox_right")
						.appendTo(this.div);
				}

				// Create an additional container for elements with align type
				// left, as the top container is used for the centered elements
				if (this.alignData.hasCenter)
				{
					// Create the left div if an element is centered
					this.leftDiv = $j(document.createElement("div"))
						.addClass("et2_hbox_left")
						.appendTo(this.div);

					this.div.addClass("et2_hbox_al_center");
				}
			}
		}
	},

	/**
	 * The overwritten loadFromXML function checks whether any child element has
	 * a special align value.
	 */
	loadFromXML: function(_node) {
		// Check whether any child node has an alignment tag
		et2_filteredNodeIterator(_node, function(_node) {
			var align = _node.getAttribute("align");

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
		this._super.apply(this, arguments);
	},

	assign: function(_obj) {
		// Copy the align data and the cells from the object which should be
		// assigned
		this.alignData = et2_cloneObject(_obj.alignData);
		this._buildAlignCells();

		// Call the inherited assign function
		this._super.apply(this, arguments);
	},

	getDOMNode: function(_sender) {
		// Return a special align container if this hbox needs it
		if (_sender != this && this.alignData.hasAlign)
		{
			// Check whether we've create a special container for the widget
			var align = (_sender.implements(et2_IAligned) ?
				_sender.get_align() : "left");

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
		return this._super.apply(this, arguments);
	},

	/**
	 * Tables added to the root node need to be inline instead of blocks
	 */
	addChild: function(child) {
		this._super.apply(this, arguments);
		if(child.instanceOf && child.instanceOf(et2_grid) || child._type == 'et2_grid')
		{
			jQuery(child.getDOMNode(child)).css("display", "inline-table");
		}
	}
});

et2_register_widget(et2_hbox, ["hbox"]);

