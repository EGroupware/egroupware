/**
 * EGroupware - Timesheet - Javascript UI
 *
 * @link http://www.egroupware.org
 * @package timesheet
 * @author Hadi Nategh	<hn-AT-stylite.de>
 * @copyright (c) 2008-13 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * UI for timesheet
 *
 * @augments AppJS
 */
app.classes.timesheet = AppJS.extend(
{
	appname: 'timesheet',
	/**
	 * et2 widget container
	 */
	et2: null,
	/**
	 * path widget
	 */

	/**
	 * Constructor
	 *
	 * @memberOf app.timesheet
	 */
	init: function()
	{
		// call parent
		this._super.apply(this, arguments);
	},

	/**
	 * Destructor
	 */
	destroy: function()
	{
		delete this.et2;
		// call parent
		this._super.apply(this, arguments);
	},

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().
	 *
	 * @param et2 etemplate2 Newly ready object
	 */
	et2_ready: function(et2)
	{
		// call parent
		this._super.apply(this, arguments);

		if (typeof et2.templates['timesheet.index'] != "undefined")
		{
			this.filter_change();
		}
	},

	/**
	 *
	 */
	filter_change: function()
	{
		var filter = this.et2.getWidgetById('filter');
		var dates = this.et2.getWidgetById('timesheet.index.dates');

		if (filter && dates)
		{
			dates.set_disabled(filter.value !== "custom");
		}
	},
});
