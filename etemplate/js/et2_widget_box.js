/**
 * EGroupware eTemplate2 - JS Box object
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
	jquery.jquery;
	et2_core_baseWidget;
*/

/**
 * Class which implements box and vbox tag
 *
 * @augments et2_baseWidget
 */
var et2_box = et2_baseWidget.extend([et2_IDetachedDOM],
{
	attributes: {
		// Not needed
		"rows": {"ignore": true},
		"cols": {"ignore": true}
	},

	createNamespace: true,

	/**
	 * Constructor
	 *
	 * @memberOf et2_box
	 */
	init: function() {
		this._super.apply(this, arguments);

		this.div = $j(document.createElement("div"))
			.addClass("et2_" + this._type)
			.addClass("et2_box_widget");

		this.setDOMNode(this.div[0]);
	},

	/**
	 * Overriden so we can check for autorepeating children.  We only check for
	 * $ in the immediate children & grandchildren of this node.
	 *
	 * @param {object} _node
	 */
	loadFromXML: function(_node) {
		if(this._type != "box")
		{
			return this._super.apply(this, arguments);
		}
		// Load the child nodes.
		var childIndex = 0;
		var repeatNode = null;
		for (var i=0; i < _node.childNodes.length; i++)
		{
			var node = _node.childNodes[i];
			var widgetType = node.nodeName.toLowerCase();

			if (widgetType == "#comment")
			{
				continue;
			}

			if (widgetType == "#text")
			{
				if (node.data.replace(/^\s+|\s+$/g, ''))
				{
					this.loadContent(node.data);
				}
				continue;
			}

			// Create the new element, if no expansion needed
			var id = et2_readAttrWithDefault(node, "id", "");
			if(id.indexOf('$') < 0 || widgetType != 'box')
			{
				this.createElementFromNode(node);
				childIndex++;
			}
			else
			{
				repeatNode = node;
			}
		}

		// Only the last child repeats(?)
		if(repeatNode != null)
		{
			var currentPerspective = this.getArrayMgr("content").perspectiveData;
			// Extra content
			for(childIndex; typeof this.getArrayMgr("content").data[childIndex] != "undefined" && this.getArrayMgr("content").data[childIndex]; childIndex++) {
				// Adjust for the row
				var mgrs = this.getArrayMgrs();
				for(var name in mgrs)
				{
					if(this.getArrayMgr(name).getEntry(childIndex))
					{
						this.getArrayMgr(name).perspectiveData.row = childIndex;
					}
				}

				this.createElementFromNode(repeatNode);
			}

			// Reset
			for(var name in this.getArrayMgrs())
			{
				this.getArrayMgr(name).perspectiveData = currentPerspective;
			}
		}
	},

	/**
	 * Code for implementing et2_IDetachedDOM
	 * This doesn't need to be implemented.
	 * Individual widgets are detected and handled by the grid, but the interface is needed for this to happen
	 *
	 * @param {array} _attrs array to add further attributes to
	 */
	getDetachedAttributes: function(_attrs)
	{
	},

	getDetachedNodes: function()
	{
		return [this.getDOMNode()];
	},

	setDetachedAttributes: function(_nodes, _values)
	{
	}

});
et2_register_widget(et2_box, ["vbox", "box"]);

