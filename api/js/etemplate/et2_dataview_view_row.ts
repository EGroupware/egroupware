/**
 * EGroupware eTemplate2 - dataview
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage dataview
 * @link https://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright EGroupware GmbH 2011-2021
 */

/*egw:uses
	egw_action.egw_action;

	et2_dataview_view_container;
*/

import {et2_dataview_IViewRange} from "./et2_dataview_interfaces";
import {et2_dataview_container} from "./et2_dataview_view_container";

export class et2_dataview_row extends et2_dataview_container implements et2_dataview_IViewRange
{
	/**
	 * Creates the row container. Use the "setRow" function to load the actual
	 * row content.
	 *
	 * @param _parent is the row parent container.
	 */
	constructor( _parent)
	{
		// Call the inherited constructor
		super(_parent);

		// Create the outer "tr" tag and append it to the container
		this.tr = jQuery(document.createElement("tr"));
		this.appendNode(this.tr);

		// Grid row which gets expanded when clicking on the corresponding
		// button
		this.expansionContainer = null;
		this.expansionVisible = false;

		// Toggle button which is used to show and hide the expansionContainer
		this.expansionButton = null;
	}

	destroy( )
	{

		if (this.expansionContainer != null)
		{
			this.expansionContainer.destroy();
		}

		super.destroy();
	}

	clear( )
	{
		this.tr.empty();
	}

	makeExpandable( _expandable, _callback, _context)
	{
		if (_expandable)
		{
			// Create the tr and the button if this has not been done yet
			if (!this.expansionButton)
			{
				this.expansionButton = jQuery(document.createElement("span"));
				this.expansionButton.addClass("arrow closed");
			}

			// Update context
			var self = this;
			this.expansionButton.off("click").on("click", function (e) {
					self._handleExpansionButtonClick(_callback, _context);
					e.stopImmediatePropagation();
			});

			jQuery("td:first", this.tr).prepend(this.expansionButton);
		}
		else
		{
			// If the row is made non-expandable, remove the created DOM-Nodes
			if (this.expansionButton)
			{
				this.expansionButton.remove();
			}

			if (this.expansionContainer)
			{
				this.expansionContainer.destroy();
			}

			this.expansionButton = null;
			this.expansionContainer = null;
		}
	}

	removeFromTree( )
	{
		if (this.expansionContainer)
		{
			this.expansionContainer.removeFromTree();
		}

		this.expansionContainer = null;
		this.expansionButton = null;

		super.removeFromTree();
	}

	getDOMNode( )
	{
		return this.tr[0];
	}

	getJNode( )
	{
		return this.tr;
	}

	getHeight( )
	{
		var h = super.getHeight();

		if (this.expansionContainer && this.expansionVisible)
		{
			h += this.expansionContainer.getHeight();
		}

		return h;
	}

	getAvgHeightData( )
	{
		// Only take the height of the own tr into account
		//var oldVisible = this.expansionVisible;
		this.expansionVisible = false;

		var res = {
			"avgHeight": this.getHeight(),
			"avgCount": 1
		};

		this.expansionVisible = true;

		return res;
	}


	/** -- PRIVATE FUNCTIONS -- **/


	_handleExpansionButtonClick( _callback, _context)
	{
		// Create the "expansionContainer" if it does not exist yet
		if (!this.expansionContainer)
		{
			this.expansionContainer = _callback.call(_context);
			this.expansionContainer.insertIntoTree(this.tr);
			this.expansionVisible = false;
		}

		// Toggle the visibility of the expansion tr
		this.expansionVisible = !this.expansionVisible;
		jQuery(this.expansionContainer._nodes[0]).toggle(this.expansionVisible);

		// Set the class of the arrow
		if (this.expansionVisible)
		{
			this.expansionButton.addClass("opened");
			this.expansionButton.removeClass("closed");
		}
		else
		{
			this.expansionButton.addClass("closed");
			this.expansionButton.removeClass("opened");
		}

		this.invalidate();
	}


	/** -- Implementation of et2_dataview_IViewRange -- **/


	setViewRange( _range)
	{
		if (this.expansionContainer && this.expansionVisible
			&& this.expansionContainer.implements(et2_dataview_IViewRange))
		{
			// Substract the height of the own row from the container
			var oh = jQuery(this._nodes[0]).height();
			_range.top -= oh;

			// Proxy the setViewRange call to the expansion container
			this.expansionContainer.setViewRange(_range);
		}
	}

}