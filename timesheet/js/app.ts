/**
 * EGroupware - Timesheet - Javascript UI
 *
 * @link http://www.egroupware.org
 * @package timesheet
 * @author Hadi Nategh	<hn-AT-stylite.de>
 * @copyright (c) 2008-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

import '../../api/js/jsapi/egw_global';

import {EgwApp} from '../../api/js/jsapi/egw_app';
import {egw} from "../../api/js/jsapi/egw_global";
import {Et2DateTimeReadonly} from "../../api/js/etemplate/Et2Date/Et2DateTimeReadonly";
import {Et2Dialog} from "../../api/js/etemplate/Et2Dialog/Et2Dialog";
import {Et2DateTime} from "../../api/js/etemplate/Et2Date/Et2DateTime";
import type {Et2Date} from "../../api/js/etemplate/Et2Date/Et2Date";
import {et2_grid} from "../../api/js/etemplate/et2_widget_grid";
import type {Et2ButtonToggle} from "../../api/js/etemplate/Et2Button/Et2ButtonToggle";
import type {Et2Select} from "../../api/js/etemplate/Et2Select/Et2Select";
import type {Et2Widget} from "../../api/js/etemplate/Et2Widget/Et2Widget";

/**
 * UI for timesheet
 *
 * @augments EgwApp
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

			// Show / hide descriptions according to details filter, and sync toolbar toggle with it
			const detailsToggle : Et2ButtonToggle = this.et2.getWidgetById('details');
			if (this.nm && detailsToggle)
			{
				detailsToggle.value = this.nm.activeFilters.filter2 == '1';
				this.filter2_change(null, detailsToggle);
			}
		}
	}

	/**
	 * Enable or disable the date filter
	 *
	 * If the filter is set to something that needs dates, we open the
	 * filter-box and show start- and endtime.
	 *
	 * @param ev
	 * @param filter
	 */
	filter_change(ev : Event, filter : Et2Select)
	{
		const dates = this.et2.getWidgetById('timesheet.index.dates');
		if (filter && dates)
		{
			dates.set_disabled(filter.value !== "custom");
			if (!filter.value) this.nm.applyFilters({startdate: null, enddate: null}, {reload: false});
			if (filter.value === "custom")
			{
				const filterDrawer = filter.closest('egw-app').filtersDrawer;
				if (filterDrawer && !filterDrawer.open)
				{
					filterDrawer.open = true;
				}
				window.setTimeout(() => dates.getWidgetById('startdate').focus());
			}
		}
		return true;
	}

	/**
	 * show or hide the details of rows by selecting the filter2 option
	 * either 'all' for details or 'no_description' for no details
	 *
	 * @param ev
	 * @param filter2
	 */
	filter2_change(ev : Event, filter2 : Et2Select | Et2ButtonToggle)
	{
		if (this.nm && filter2)
		{
			const show = typeof filter2.value === "boolean" ? filter2.value : filter2.value == '1';
			// Rows render inside Et2Datagrid's shadow DOM, so use a custom property (see rows.css)
			// instead of egw.css(), which only reaches the light DOM.
			this.nm.style.setProperty("--timesheet-ts-details-weight", show ? "bold" : "normal");
			// Show / hide descriptions
			this.nm.style.setProperty("--timesheet-ts-details-display", show ? "flex" : "none");
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
		var nm = action.data?.nextmatch || false;
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
			if(typeof nm_value.col_filter.linked === "string")
			{
				const split = nm_value.col_filter.linked.split(':') || '';
				extras.link_app = split[0] || '';
				extras.link_id = split[1] || '';
			}
			else
			{
				extras.link_app = nm_value.col_filter.linked.app;
				extras.link_id = nm_value.col_filter.linked.id;
			}
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
			ts_project.placeholder = _widget.getValue() ?_widget._searchNode?.optionSearch(_widget.value)?.label : '';
		}
	}

	/**
	 * Date changed while editing a new entry: if the "start at end of last entry" preference
	 * is set, fetch the end-time of the last entry on the new day and use it as the start-time
	 *
	 * @param {Event} _ev
	 * @param {Et2Date} _widget
	 */
	ts_start_changed(_ev : Event, _widget : Et2Date)
	{
		const ts_id = this.et2.getValueById('ts_id');
		const start_time = <Et2DateTime>this.et2.getWidgetById('start_time');
		const end_time = <Et2DateTime>this.et2.getWidgetById('end_time');
		if (ts_id || !start_time || this.egw.preference('new_entry_default', 'timesheet') !== 'start_time')
		{
			return;
		}

		start_time.disabled = true;
		this.egw.loading_prompt('ts_start_changed', true, '', start_time);
		egw.json('timesheet.timesheet_ui.ajax_get_last_end',
			[this.et2.getValueById('ts_owner'), _widget.getValue()],
			(last_end) =>
			{
				// Et2DateTimeOnly.value expects something new Date() can parse - a bare "H:i"
				// string is not, so wrap it to match the widget's own internal dateFormat
				start_time.value = last_end ? '1970-01-01T' + last_end + ':00Z' : '';
				// force empty end-time, unless continuing from last entry (mirrors edit())
				if (last_end && end_time)
				{
					end_time.value = '';
				}
			}
		).sendRequest(true).finally(() =>
		{
			start_time.disabled = false;
			this.egw.loading_prompt('ts_start_changed', false);
		});
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
				// startdate/enddate widgets are not part of every template (eg. mobile)
				this.et2.getWidgetById('startdate')?.set_value(start);
				this.et2.getWidgetById('enddate')?.set_value(end);
				// Only feed the resolved range back into nm's real filters for an actual dated preset.
				// The default/'All' filter has no real date window - the server reports 'now' here
				// purely so the (hidden) date pickers have a sensible starting point if the user later
				// switches to 'custom' - so a fresh/no-filter session must stay filter-less: no filter,
				// no startdate, no time range, and (see get_rrows()) no day/week/month/year summary rows.
				if (nm.activeFilters.filter)
				{
					// The first time this fires for a real preset, nm has no startdate yet, so the rows
					// already on screen were fetched without one (missing the summary rows). Reload once
					// to bring them in; after that, don't reload on every call, since "start" drifts by a
					// second on every request when it's still just 'now' - reloading on every drift would
					// loop forever.
					const reload = !nm.activeFilters.startdate;
					// This is called from a server response that's still being processed, so defer past
					// the current synchronous handling - otherwise mutating the filters now would make
					// Et2Datagrid think the in-flight fetch that triggered this call is stale, and discard
					// its (valid) response.
					window.setTimeout(() => nm.applyFilters({startdate: start, enddate: end}, {reload}));
				}
			}
		}
	}

	/**
	 * If editing a timesheet and no quantity is set, update the placeholder text when duration changes
	 *
	 * This is for display only
	 */
	update_quantity(event, widget)
	{
		const quantity = this.et2.getWidgetById("ts_quantity");
		if(quantity)
		{
			// use decimal separator from user prefs
			const format = this.egw.preference('number_format');
			const sep = format ? format[0] : '.';

			// Duration is in minutes, round to hours with 2 decimals
			const minutes = parseInt(widget.value) / 60;
			let val = "" + Math.round((minutes + Number.EPSILON) * 1000) / 1000;
			if(format && sep && sep !== '.')
			{
				val = val.replace('.', sep);
			}

			// Clear actual value to update if it was nearly the same
			const old_val = parseInt(widget._oldValue) / 60;
			if(Math.abs(quantity.valueAsNumber - old_val) < 0.01)
			{
				quantity.value = "";
			}

			// Set placeholder
			quantity.placeholder = val;
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
			const time = <Et2DateTime><any>dialog.eTemplate.widgetContainer.getWidgetById('time');
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

	/**
	 * Run filter_change()'s side effects (date-widget disable/focus, stale startdate/enddate
	 * cleanup) whenever nm's own "filter" value actually transitions - regardless of what
	 * triggered it: the toolbar select's onchange, a restored favorite, or the framework's
	 * "Clear filters" button (which sets widget values programmatically and calls
	 * nm.applyFilters() directly, never going through changeNmFilter()/checkNmFilterChanged() at
	 * all). Comparing oldFilters/activeFilters from the event is reliable where widget.value is
	 * not: by the time any change-triggered sync runs, the widget's own value already reflects
	 * the new state, so a before/after comparison against it never sees a difference.
	 */
	nmFilterChange(_ev : Event)
	{
		const detail = (<CustomEvent>_ev).detail;
		const oldFilter = detail?.oldFilters?.filter;
		const newFilter = detail?.activeFilters?.filter;
		super.nmFilterChange(_ev);
		if(oldFilter !== newFilter)
		{
			const filterWidget = this.et2.getWidgetById('filter');
			if(filterWidget)
			{
				this.filter_change(null, <Et2Select>filterWidget);
			}
		}
	}

	/**
	 * Show details has been clicked
	 */
	toggleDetails(_ev : Event, _widget : Et2ButtonToggle)
	{
		if (!this.nm) return;
		this.nm.applyFilters({filter2: _widget.value ? '1' : ''});
		// _widget.value is already updated by the time this onchange fires, so
		// checkNmFilterChanged()'s value-changed check below won't see a difference - update directly
		this.filter2_change(_ev, _widget);
	}

	/**
	 * Check if any NM filter or search in app-toolbar needs to be updated to reflect NM internal state
	 *
	 * @param app_toolbar
	 * @param id
	 * @param value
	 */
	checkNmFilterChanged(app_toolbar, id : string, value : string)
	{
		super.checkNmFilterChanged(app_toolbar, id, value);

		if (id === 'filter2')
		{
			const details_toggle : Et2ButtonToggle = this.et2.getWidgetById('details');
			if (details_toggle && details_toggle.value != (value === '1'))
			{
				details_toggle.value = value === '1';
				// if it's a real change, we also need to call this.filter2_change, with the already changed value!
				this.filter2_change(null, details_toggle);
			}
		}
		// "filter" is handled in nmFilterChange() instead - see its docblock for why.
	}
}

app.classes.timesheet = TimesheetApp;