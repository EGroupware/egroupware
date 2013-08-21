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
app.timesheet = AppJS.extend(
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
			this.timesheet_filter_change();
		}

	},

	/**
	 *
	 */
	timesheet_filter_change: function()
	{
		if (etemplate2.getByApplication('timesheet')[0].widgetContainer.getWidgetById('filter').value === "custom")
		{
			etemplate2.getByApplication('timesheet')[0].widgetContainer.getWidgetById('timesheet.index.dates').set_disabled(false);
		}
		else
		{
			etemplate2.getByApplication('timesheet')[0].widgetContainer.getWidgetById('timesheet.index.dates').set_disabled(true);
		}
	},

});
