/**
 * EGroupware eTemplate2 - dataview
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage dataview
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011-2012
 * @version $Id$
 */

/*egw:uses
	egw_action.egw_action;

	et2_dataview_view_container;
*/

/**
 * @augments et2_dataview_container
 */
var et2_dataview_row = (function(){ "use strict"; return et2_dataview_container.extend(et2_dataview_IViewRange,
{
	/**
	 * Creates the row container. Use the "setRow" function to load the actual
	 * row content.
	 *
	 * @param _parent is the row parent container.
	 * @memberOf et2_dataview_row
	 */
	init: function(_parent) {
		// Call the inherited constructor
		this._super(_parent);

		// Create the outer "tr" tag and append it to the container
		this.tr = $j(document.createElement("tr"));
		this.appendNode(this.tr);

		// Grid row which gets expanded when clicking on the corresponding
		// button
		this.expansionContainer = null;
		this.expansionVisible = false;

		// Toggle button which is used to show and hide the expansionContainer
		this.expansionButton = null;
	},

	destroy: function () {

		if (this.expansionContainer != null)
		{
			this.expansionContainer.free();
		}

		this._super();
	},

	clear: function() {
		this.tr.empty();
	},

	makeExpandable: function (_expandable, _callback, _context) {
		if (_expandable)
		{
			// Create the tr and the button if this has not been done yet
			if (!this.expansionButton)
			{
				this.expansionButton = $j(document.createElement("span"));
				this.expansionButton.addClass("arrow closed");
			}

			// Update context
			var self = this;
			this.expansionButton.off("click").on("click", function (e) {
					self._handleExpansionButtonClick(_callback, _context);
					e.stopImmediatePropagation();
			});

			$j("td:first", this.tr).prepend(this.expansionButton);
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
				this.expansionContainer.free();
			}

			this.expansionButton = null;
			this.expansionContainer = null;
		}
	},

	removeFromTree: function () {
		if (this.expansionContainer)
		{
			this.expansionContainer.removeFromTree();
		}

		this.expansionContainer = null;
		this.expansionButton = null;

		this._super();
	},

	getDOMNode: function () {
		return this.tr[0];
	},

	getJNode: function () {
		return this.tr;
	},

	getHeight: function () {
		var h = this._super();

		if (this.expansionContainer && this.expansionVisible)
		{
			h += this.expansionContainer.getHeight();
		}

		return h;
	},

	getAvgHeightData: function() {
		// Only take the height of the own tr into account
		//var oldVisible = this.expansionVisible;
		this.expansionVisible = false;

		var res = {
			"avgHeight": this.getHeight(),
			"avgCount": 1
		};

		this.expansionVisible = true;

		return res;
	},


	/** -- PRIVATE FUNCTIONS -- **/


	_handleExpansionButtonClick: function (_callback, _context) {
		// Create the "expansionContainer" if it does not exist yet
		if (!this.expansionContainer)
		{
			this.expansionContainer = _callback.call(_context);
			this.expansionContainer.insertIntoTree(this.tr);
			this.expansionVisible = false;
		}

		// Toggle the visibility of the expansion tr
		this.expansionVisible = !this.expansionVisible;
		$j(this.expansionContainer._nodes[0]).toggle(this.expansionVisible);

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
	},


	/** -- Implementation of et2_dataview_IViewRange -- **/


	setViewRange: function (_range) {
		if (this.expansionContainer && this.expansionVisible
		    && this.expansionContainer.implements(et2_dataview_IViewRange))
		{
			// Substract the height of the own row from the container
			var oh = $j(this._nodes[0]).height();
			_range.top -= oh;

			// Proxy the setViewRange call to the expansion container
			this.expansionContainer.setViewRange(_range);
		}
	}

});}).call(this);

