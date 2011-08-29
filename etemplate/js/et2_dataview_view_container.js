/**
 * eGroupWare eTemplate2 - Class which contains the "row" base class
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage dataview
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
 */

"use strict"

/*egw:uses
	et2_dataview_interfaces;
*/

var et2_dataview_container = Class.extend({

	init: function(_data, _invalidationElem) {
		this._dataProvider = _data;
		this._invalidationElem = _invalidationElem;

		this._node = null;
	},

	setJNode: function(_node) {
		// Replace the old node with the new one
		if (this._node[0].parent)
		{
			this._node.replaceWith(_node);
		}

		this._node = _node;
	},

	getJNode: function() {
		return this._node;
	},

	invalidate: function() {
		this._invalidationElem.invalidate();
	}

});

/**
 * Returns the height of the container in pixels and zero if the element is not
 * visible. The height is clamped to positive values.
 * The browser switch is placed at this position as the getHeight function is one
 * of the mostly called functions in the whole grid code and should stay
 * quite fast.
 */
if ($j.browser.mozilla)
{
	et2_dataview_container.prototype.getHeight = function()
	{
		if (this.node)
		{
			// Firefox sometimes provides fractional pixel values - we are
			// forced to use those - we can obtain the fractional pixel height
			// by using the window.getComputedStyle function
			var compStyle = getComputedStyle(this._node, null);
			if (compStyle)
			{
				var styleHeightStr = compStyle.getPropertyValue("height");
				var height = parseFloat(styleHeightStr.substr(0, styleHeightStr.length - 2));

				if (isNaN(height) || height < 1)
				{
					height = false;
				}
			}

			return height;
		}

		return 0;
	}
}
else
{
	et2_dataview_container.prototype.getHeight = function()
	{
		if (this.node)
		{
			return this._node.offsetHeight;
		}

		return 0;
	}
}

