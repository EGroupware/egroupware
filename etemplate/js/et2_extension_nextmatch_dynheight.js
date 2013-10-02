/**
 * EGroupware eTemplate2 - JS Dynheight object
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

/*egw:use
	jquery.jquery;
	et2_core_inheritance;
*/

/**
 * Object which resizes an inner node to the maximum extend of an outer node
 * (without creating a scrollbar) - it achieves that by performing some very
 * nasty and time consuming calculations.
 * 
 * @augments Class
 */
var et2_dynheight = Class.extend(
{
	/**
	 * Constructor for the dynheight object
	 *
	 * @param _outerNode is the node which surrounds the _innerNode and to
	 * 	which extend the innerNode should be expanded without creating a
	 * 	scrollbar. Note: The outer node must be a parent of the inner node.
	 * @param _innerNode is the node which should be scaled. Call update to
	 * 	scale the node.
	 * @param _minHeight is the minimum height the inner node should have
	 * @memberOf et2_dynheight
	 */
	init: function(_outerNode, _innerNode, _minHeight) {
		this.outerNode = $j(_outerNode);
		this.innerNode = $j(_innerNode);
		this.minHeight = _minHeight;

		this.bottomNodes = [];
		this.initialized = false;
		this.innerMargin = 0;
		this.outerMargin = 0;
	},

	/**
	 * Resizes the inner node. When this is done, the callback function is
	 * called.
	 */
	update: function(_callback, _context) {
		// Check whether the inner node is actually visible - if not, don't
		// trigger the callback function
		if (this.innerNode.is(":visible"))
		{
			// Initialize the height calculation
			this._initialize();

			// Get the outer container height and offset, if available
			var oh = this.outerNode.height();
			var ot = this.outerNode.offset() ? this.outerNode.offset().top : 0;

			// Get top and height of the inner node
			var it = this.innerNode.offset().top;

			// Calculate the height of the "bottomNodes"
			var bminTop = this.bottomNodes.length ? Infinity : 0;
			var bmaxBot = 0;
			for (var i = 0; i < this.bottomNodes.length; i++)
			{
				// Ignore hidden popups
				if(this.bottomNodes[i].find('.action_popup').length)
				{
					egw.debug('warn', "Had to skip a hidden popup - it should be removed",
						this.bottomNodes[i].find('.action_popup')
					);
					continue;
				}
				// Get height, top and bottom and calculate the maximum/minimum
				var bh = this.bottomNodes[i].outerHeight(true);
				var bt = this.bottomNodes[i].offset().top;
				var bb = bh + bt;

				if (i == 0 || bminTop > bt)
				{
					bminTop = bt;
				}

				if (i == 0 || bmaxBot < bb)
				{
					bmaxBot = bb;
				}
			}

			// Get the height of the bottom container
			var bh = bmaxBot - bminTop;

			// Calculate the new height of the inner container
			var w = this.innerNode.width();
			// Some checking to make sure it doesn't overflow the width when user
			// resizes the window
			if(w > this.outerNode.width())
			{
				w = this.outerNode.width();
			}
			if (w > $j(window).width())
			{
				// 50px border, totally arbitrary, but we just need to make sure it's inside
				w = $j(window).width()-50;
			}
			var h = Math.max(this.minHeight, oh + ot - it - bh -
				this.innerMargin - this.outerMargin);
			this.innerNode.height(h);

			// Call the callback function
			if (typeof _callback != "undefined")
			{
				_callback.call(_context, w, h);
			}
		}
	},

	/**
	 * Function used internally which collects all DOM-Nodes which are located
	 * below this element.
	 */
	_collectBottomNodes: function(_node, _bottom) {
		// Calculate the bottom position of the inner node
		if (typeof _bottom == "undefined")
		{
			_bottom = this.innerNode.offset().top + this.innerNode.height();
		}

		if (_node)
		{
			// Accumulate the outer margin of the parent elements
			var node = $j(_node);
			var ooh = node.outerHeight(true);
			var oh = node.height();
			this.outerMargin += (ooh - oh) / 2; // Divide by 2 as the value contains margin-top and -bottom

			// Iterate over the children of the given node and do the same
			// recursively to the parent nodes until the _outerNode or body is
			// reached.
			var self = this;
			$j(_node).children().each(function() {
				var $this = $j(this);
				var top = $this.offset().top;
				if (this != self.innerNode[0] && top >= _bottom)
				{
					self.bottomNodes.push($this);
				}
			});

			if (_node != this.outerNode[0] && _node != $j("body")[0])
			{
				this._collectBottomNodes(_node.parentNode, _bottom);
			}
		}
	},

	/**
	 * Used internally to calculate some information which will not change over
	 * the time.
	 */
	_initialize: function() {
		if (!this.initialized)
		{
			// Collect all bottomNodes and calculates the outer margin
			this.bottomNodes = [];
			this.outerMargin = 0;
			this._collectBottomNodes(this.innerNode[0].parentNode);

			// Calculate the inner margin
			var ioh = this.innerNode.outerHeight(true);
			var ih = this.innerNode.height();
			this.innerMargin = ioh - ih;

			this.initialized = true;
		}
	}

});

