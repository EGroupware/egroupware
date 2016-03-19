/**
 * EGroupware eTemplate2 - dataview code
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage dataview
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2014
 * @version $Id: et2_dataview_view_container_1.js 46338 2014-03-20 09:40:37Z ralfbecker $
 */

/*egw:uses
	jquery.jquery;
	et2_dataview_interfaces;
*/

/**
 * Displays tiles or thumbnails (squares) instead of full rows.
 *
 * It's important that the template specifies a fixed width and height (via CSS)
 * so that the rows and columns work out properly.
 *
 * @augments et2_dataview_container
 */
var et2_dataview_tile = (function(){ "use strict"; return et2_dataview_row.extend([],
{
	columns: 4,

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

		// Make sure the needed class is there to get the CSS
		this.tr.addClass('tile');
	},

	makeExpandable: function (_expandable, _callback, _context) {
		// Nope.  It mostly works, it's just weird.
	},

	getAvgHeightData: function() {
		var res = {
			"avgHeight": this.getHeight() / this.columns,
			"avgCount": this.columns
		};
		return res;
	},

	/**
	 * Returns the height for the tile.
	 *
	 * This is where we do the magic.  If a new row should start, we return the proper
	 * height.  If this should be another tile in the same row, we say it has 0 height.
	 * @returns {Number}
	 */
	getHeight: function() {
		if(this._index % this.columns == 0)
		{
			return this._super();
		}
		else
		{
			return 0;
		}
	},

	/**
	 * Broadcasts an invalidation through the container tree. Marks the own
	 * height as invalid.
	 */
	invalidate: function() {
		if(this._inTree && this.tr)
		{
			var template_width = $j('.innerContainer',this.tr).children().outerWidth(true);
			if(template_width)
			{

				this.tr.css('width', template_width + (this.tr.outerWidth(true) - this.tr.width()));
			}
		}
		this._recalculate_columns();
		this._super();
	},

	/**
	 * Recalculate how many columns we can fit in a row.
	 * While browser takes care of the actual layout, we need this for proper
	 * pagination.
	 */
	_recalculate_columns: function() {
		if(this._inTree && this.tr && this.tr.parent())
		{
			this.columns = Math.max(1,parseInt(this.tr.parent().innerWidth() / this.tr.outerWidth(true)));
		}
	}
});}).call(this);
