/**
 * EGroupware - Timesheet - Javascript UI
 *
 * @link http://www.egroupware.org
 * @package timesheet
 * @author Hadi Nategh	<hn-AT-stylite.de>
 * @copyright (c) 2008-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

/*egw:uses
	/api/js/jsapi/egw_app.js
 */

import 'jquery';
import 'jqueryui';
import '../jsapi/egw_global';
import '../etemplate/et2_types';

import {EgwApp} from '../../api/js/jsapi/egw_app';
import {et2_nextmatch} from "../../api/js/etemplate/et2_extension_nextmatch";
import {etemplate2} from "../../api/js/etemplate/etemplate2";

/**
 * UI for timesheet
 *
 * @augments AppJS
 */
class TimesheetApp extends EgwApp
{

	constructor()
	{
		super('timesheet');
	}

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().
	 *
	 * @param et2 etemplate2 Newly ready object
	 * @param string name
	 */
	et2_ready(et2, name: string)
	{
		// call parent
		super.et2_ready(et2, name);

		if (name == 'timesheet.index')
		{
			this.filter_change();
			this.filter2_change();
		}
	}

	/**
	 *
	 */
	filter_change()
	{
		var filter = this.et2.getWidgetById('filter');
		var dates = this.et2.getWidgetById('timesheet.index.dates');
		let nm = this.et2.getDOMWidgetById('nm');
		if (filter && dates)
		{
			dates.set_disabled(filter.get_value() !== "custom");
			if (filter.get_value() == 0) nm.activeFilters.startdate = null;
			if (filter.value == "custom")
			{
				jQuery(this.et2.getWidgetById('startdate').getDOMNode()).find('input').focus();
			}
		}
		return true;
	}

	/**
	 * show or hide the details of rows by selecting the filter2 option
	 * either 'all' for details or 'no_description' for no details
	 *
	 */
	filter2_change()
	{
		var nm = this.et2.getWidgetById('nm');
		var filter2 = this.et2.getWidgetById('filter2');

		if (nm && filter2)
		{
			egw.css("#timesheet-index span.timesheet_titleDetails","font-weight:" + (filter2.getValue() == '1' ? "bold;" : "normal;"));
			// Show / hide descriptions
			egw.css(".et2_label.ts_description","display:" + (filter2.getValue() == '1' ? "block;" : "none;"));
		}
	}

	/**
	 * Wrapper so add action in the context menu can pass current
	 * filter values into new edit dialog
	 *
	 * @see add_with_extras
	 *
	 * @param {egwAction} action
	 * @param {egwActionObject[]} selected
	 */
	add_action_handler(action, selected)
	{
		var nm = action.getManager().data.nextmatch || false;
		if(nm)
		{
			this.add_with_extras(nm);
		}
	}

	/**
	 * Opens a new edit dialog with some extra url parameters pulled from
	 * nextmatch filters.
	 *
	 * @param {et2_widget} widget Originating/calling widget
	 */
	add_with_extras(widget)
	{
		var nm = widget.getRoot().getWidgetById('nm');
		var nm_value = nm.getValue() || {};

		var extras : any = {};
		if (nm_value.cat_id)
		{
			extras.cat_id = nm_value.cat_id;
		}

		if (nm_value.col_filter && nm_value.col_filter.linked)
		{
			var split = nm_value.col_filter.linked.split(':') || '';
			extras.link_app = split[0] || '';
			extras.link_id = split[1] || '';
		}
		if (nm_value.col_filter && nm_value.col_filter.pm_id)
		{
			extras.link_app = 'projectmanager';
			extras.link_id = nm_value.col_filter.pm_id;
		}
		else if (nm_value.col_filter && nm_value.col_filter.ts_project)
		{
			extras.ts_project = nm_value.col_filter.ts_project;
		}

		egw.open('','timesheet','add',extras);
	}

	/**
	 * Change handler for project selection to set empty ts_project string, if project get deleted
	 *
	 * @param {type} _egw
	 * @param {et2_widget_link_entry} _widget
	 * @returns {undefined}
	 */
	pm_id_changed(_egw, _widget)
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

	/**
	 * Update custom filter timespan, without triggering a change
	 */
	update_timespan(start, end)
	{
		if(this && this.et2)
		{
			var nm = this.et2.getWidgetById('nm');
			if(nm)
			{
				// Toggle update_in_progress to avoid another request
				nm.update_in_progress = true;
				this.et2.getWidgetById('startdate').set_value(start);
				this.et2.getWidgetById('enddate').set_value(end);
				nm.activeFilters.startdate = start;
				nm.activeFilters.enddate = end;
				nm.update_in_progress = false;
			}
		}
	}

	/**
	 * Get title in order to set it as document title
	 * @returns {string}
	 */
	getWindowTitle()
	{
		var widget = this.et2.getWidgetById('ts_title');
		if(widget) return widget.options.value;
	}

	private _grants : any;

	/**
	 * Handle a push notification about entry changes from the websocket
	 *
	 * @param  pushData
	 * @param {string} pushData.app application name
	 * @param {(string|number)} pushData.id id of entry to refresh or null
	 * @param {string} pushData.type either 'update', 'edit', 'delete', 'add' or null
	 * - update: request just modified data from given rows.  Sorting is not considered,
	 *		so if the sort field is changed, the row will not be moved.
	 * - edit: rows changed, but sorting may be affected.  Requires full reload.
	 * - delete: just delete the given rows clientside (no server interaction neccessary)
	 * - add: requires full reload for proper sorting
	 * @param {object|null} pushData.acl Extra data for determining relevance.  eg: owner or responsible to decide if update is necessary
	 * @param {number} pushData.account_id User that caused the notification
	 */
	push(pushData)
	{
		// timesheed does NOT care about other apps data
		if (pushData.app !== this.appname) return;

		if (pushData.type === 'delete')
		{
			return super.push(pushData);
		}

		// This must be before all ACL checks, as owner might have changed and entry need to be removed
		// (server responds then with null / no entry causing the entry to disapear)
		if (pushData.type !== "add" && this.egw.dataHasUID(this.uid(pushData)))
		{
			return etemplate2.app_refresh("", pushData.app, pushData.id, pushData.type);
		}

		// all other cases (add, edit, update) are handled identical
		// check visibility
		if (typeof this._grants === 'undefined')
		{
			this._grants = egw.grants(this.appname);
		}
		if (typeof this._grants[pushData.acl.ts_owner] === 'undefined') return;

		// check if we might not see it because of an owner filter
		let nm = <et2_nextmatch>this.et2?.getWidgetById('nm');
		let nm_value = nm?.getValue();
		if (nm && nm_value && nm_value.col_filter?.ts_owner && nm_value.col_filter.ts_owner != pushData.acl.ts_owner)
		{
			return;
		}
		etemplate2.app_refresh("",pushData.app, pushData.id, pushData.type);
	}

	/**
	 * Run action via ajax
	 *
	 * @param _action
	 * @param _senders
	 */
	ajax_action(_action, _senders)
	{
		let all = _action.parent.data.nextmatch?.getSelection().all;
		let ids = [];
		for(let i = 0; i < _senders.length; i++)
		{
			ids.push(_senders[i].id.split("::").pop());
		}
		egw.json("timesheet.timesheet_ui.ajax_action",[_action.id, ids, all]).sendRequest(true);
	}
}

app.classes.timesheet = TimesheetApp;
