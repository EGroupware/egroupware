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
import '../../api/js/jsapi/egw_global';

import {EgwApp} from '../../api/js/jsapi/egw_app';
import {egw} from "../../api/js/jsapi/egw_global";
import {Et2DateTimeReadonly} from "../../api/js/etemplate/Et2Date/Et2DateTimeReadonly";
import {Et2Dialog} from "../../api/js/etemplate/Et2Dialog/Et2Dialog";
import {Et2DateTime} from "../../api/js/etemplate/Et2Date/Et2DateTime";
import {et2_grid} from "../../api/js/etemplate/et2_widget_grid";

/**
 * UI for timesheet
 *
 * @augments AppJS
 */
class TimesheetApp extends EgwApp
{

	// These fields help with push filtering & access control to see if we care about a push message
	protected push_grant_fields = ["ts_owner"];
	protected push_filter_fields = ["ts_owner"]

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
			egw.css(".ts_description", "display:" + (filter2.getValue() == '1' ? "block;" : "none;"));
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
		return this.et2.getValueById('ts_title');
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

	/**
	 * Edit time of an event in events tab of edit timesheet
	 *
	 * @param MouseEvent _ev
	 * @param Et2DateTimeReadonly _widget
	 */
	editEventTime(_ev : MouseEvent, _widget : Et2DateTimeReadonly)
	{
		_ev.stopPropagation();	// tab-panel somehow also gets the event
		if (this.et2.getInstanceManager().isDirty())
		{
			Et2Dialog.alert(this.egw.lang('You have unsaved changes, you need save them before editing events!'), this.egw.lang('Unsaved changes'));
			return;
		}
		const tse_id = _widget.closest('tr')?.id?.replace('timesheet-events::', '');
		const grid = <et2_grid>_widget.getParent();
		const tse_type = parseInt(grid.getWidgetById(_widget.id.replace('[tse_time]', '[tse_type]')).value[0]);
		const dialog = new Et2Dialog(this.egw);
		dialog.getUpdateComplete().then(() =>
		{
			const time = <Et2DateTime><any>dialog.template.widgetContainer.getWidgetById('time');
			// start-time set end-time as max
			if (0+tse_type & 1)
			{
				time.set_max((<Et2DateTimeReadonly><any>grid.getWidgetById(_widget.id.replace(/^(\d+)/,
					n => (parseInt(n)+1).toString()))).value);
			}
			// stop- or pause-time, set start-time as min
			else
			{
				time.set_min((<Et2DateTimeReadonly><any>grid.getWidgetById(_widget.id.replace(/^(\d+)/,
					n => (parseInt(n)-1).toString()))).value);
			}
		});
		// Set attributes.  They can be set in any way, but this is convenient.
		dialog.transformAttributes({
			callback: (_button, _values) => {
				const change = (new Date(_widget.value)).valueOf() - (new Date(_values.time)).valueOf();
				if (_button === Et2Dialog.OK_BUTTON && change)
				{
					_widget.value = _values.time;
					egw.request('timesheet.EGroupware\\Timesheet\\Events.ajax_updateTime',
						[tse_id, new Date((new Date(_values.time)).valueOf() + egw.getTimezoneOffset() * 60000)])
						.then(_data =>
						{
							// reload the whole dialog
							window.location.href = window.location.href+'&tabs=events';
						});
				}
			},
			title: egw.lang('Change time'),
			template: 'timesheet.edit.events.change',
			buttons: Et2Dialog.BUTTONS_OK_CANCEL,
			value: {
				content: { time: _widget.value }
			}
		});
		// Add to DOM, dialog will auto-open
		document.body.appendChild(dialog);
	}
}

app.classes.timesheet = TimesheetApp;