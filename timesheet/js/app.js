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
			this.filter2_change();
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

	/**
	 * show or hide the details of rows by selecting the filter2 option
	 * either 'all' for details or 'no_description' for no details
	 *
	 */
	filter2_change: function()
	{
		var nm = this.et2.getWidgetById('nm');
		var filter2 = this.et2.getWidgetById('filter2');

		if (nm && filter2)
		{
			egw.css("#timesheet-index span.timesheet_titleDetails","font-weight:" + (filter2.getValue() == '1' ? "bold;" : "normal;"));
			// Show / hide descriptions
			egw.css(".et2_label.ts_description","display:" + (filter2.getValue() == '1' ? "block;" : "none;"));
		}
	},

	/**
	 * Change handler for project selection to set empty ts_project string, if project get deleted
	 *
	 * @param {type} _egw
	 * @param {et2_widget_link_entry} _widget
	 * @returns {undefined}
	 */
	pm_id_changed: function(_egw, _widget)
	{
		// Update price list
		var ts_pricelist = _widget.getRoot().getWidgetById('pl_id');
		egw.json('projectmanager_widget::ajax_get_pricelist',[_widget.getValue()],function(value) {
			ts_pricelist.set_select_options(value||{})
		}).sendRequest(true);

		var ts_project = this.et2.getWidgetById('ts_project');
		if (ts_project)
		{
			ts_project.set_blur(_widget.getValue() ? _widget.search.val() : '');
		}
	}
});
