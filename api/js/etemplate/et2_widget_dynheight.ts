/**
 * EGroupware eTemplate2 - JS Dynheight object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright EGroupware GmbH 2011-2021
 */

/*egw:use
	/vendor/bower-asset/jquery/dist/jquery.js;
	et2_core_inheritance;
*/

import {egw} from "../jsapi/egw_global";

/**
 * Object which resizes an inner node to the maximum extend of an outer node
 * (without creating a scrollbar) - it achieves that by performing some very
 * nasty and time consuming calculations.
 */
export class et2_dynheight
{
	private initialized: boolean = false;

	private outerNode: JQuery;
	private innerNode: JQuery;
	private minHeight: number = 0;

	private bottomNodes: any[] = [];
	private innerMargin: number = 0;
	private outerMargin: number = 0;

	constructor(_outerNode, _innerNode, _minHeight) 
	{
		this.outerNode = jQuery(_outerNode);
		this.innerNode = jQuery(_innerNode);
		this.minHeight = _minHeight;
	}

	destroy()
	{
		this.outerNode = null;
		this.innerNode = null;
		this.bottomNodes = [];
	}

	/**
	 * Resizes the inner node. When this is done, the callback function is
	 * called.
	 *
	 * @param {function} _callback
	 * @param {object} _context
	 */
	update( _callback, _context)
	{
		// Check whether the inner node is actually visible - if not, don't
		// trigger the callback function
		if (this.innerNode.is(":visible"))
		{
			// Initialize the height calculation
			this._initialize();

			// Get the outer container height and offset, if available
			const oh = this.outerNode.height();
			const ot = this.outerNode.offset() ? this.outerNode.offset().top : 0;

			// Get top and height of the inner node
			const it = this.innerNode.offset().top;

			// Calculate the height of the "bottomNodes"
			let bminTop = this.bottomNodes.length ? Infinity : 0;
			let bmaxBot = 0;
			for (let i = 0; i < this.bottomNodes.length; i++)
			{
				// Ignore hidden popups
				if(this.bottomNodes[i].find('.action_popup').length)
				{
					egw.debug('warn', "Had to skip a hidden popup - it should be removed",
						this.bottomNodes[i].find('.action_popup')
					);
					continue;
				}
				// Ignore other hidden nodes
				if(!this.bottomNodes[i].is(':visible')) continue;

				// Get height, top and bottom and calculate the maximum/minimum
				let bh = this.bottomNodes[i].outerHeight(true);
				let bt = this.bottomNodes[i].offset().top;
				const bb = bh + bt;

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
			const bh = Math.max(0, bmaxBot - bminTop);

			// Calculate the new height of the inner container
			const h = Math.max(this.minHeight, oh + ot - it - bh -
				this.innerMargin - this.outerMargin);
			this.innerNode.height(h);

			// Update the width
			// Some checking to make sure it doesn't overflow the width when user
			// resizes the window
			let w = this.outerNode.width();
			if (w > jQuery(window).width())
			{
				// 50px border, totally arbitrary, but we just need to make sure it's inside
				w = jQuery(window).width()-50;
			}
			if(w != this.innerNode.outerWidth())
			{
				this.innerNode.width(w);
			}

			// Call the callback function
			if (typeof _callback != "undefined")
			{
				_callback.call(_context, w, h);
			}
		}
	}

	/**
	 * Function used internally which collects all DOM-Nodes which are located
	 * below this element.
	 *
	 * @param {HTMLElement} _node
	 * @param {number} _bottom
	 */
	_collectBottomNodes( _node : any, _bottom? : number)
	{
		// Calculate the bottom position of the inner node
		if (typeof _bottom == "undefined")
		{
			_bottom = this.innerNode.offset().top + this.innerNode.height();
		}

		if (_node)
		{
			// Accumulate the outer margin of the parent elements
			const node = jQuery(_node);
			const ooh = node.outerHeight(true);
			const oh = node.height();
			this.outerMargin += (ooh - oh) / 2; // Divide by 2 as the value contains margin-top and -bottom

			// Iterate over the children of the given node and do the same
			// recursively to the parent nodes until the _outerNode or body is
			// reached.
			const self = this;
			jQuery(_node).children().each(function() {
				const $this = jQuery(this);
				const top = $this.offset().top;
				if(this != self.innerNode[0] && top >= _bottom && ["fixed", "absolute"].indexOf(getComputedStyle(this).position) == -1)
				{
					self.bottomNodes.push($this);
				}
			});

			if (_node != this.outerNode[0] && _node != jQuery("body")[0])
			{
				this._collectBottomNodes(_node.parentNode, _bottom);
			}
		}
	}

	/**
	 * Used internally to calculate some information which will not change over
	 * the time.
	 */
	_initialize( )
	{
		if (!this.initialized)
		{
			// Collect all bottomNodes and calculates the outer margin
			this.bottomNodes = [];
			this.outerMargin = 0;
			this._collectBottomNodes(this.innerNode[0].parentNode);

			// Calculate the inner margin
			const ioh = this.innerNode.outerHeight(true);
			const ih = this.innerNode.height();
			this.innerMargin = ioh - ih;

			this.initialized = true;
		}
	}

}