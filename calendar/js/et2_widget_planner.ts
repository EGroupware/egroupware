/*
 * Egroupware Calendar timegrid
 *
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package calendar
 * @subpackage etemplate
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


/*egw:uses
	/calendar/js/et2_widget_view.js;
	/calendar/js/et2_widget_planner_row.js;
	/calendar/js/et2_widget_event.js;
*/

import {et2_createWidget, et2_register_widget, WidgetConfig} from "../../api/js/etemplate/et2_core_widget";
import {ClassWithAttributes} from "../../api/js/etemplate/et2_core_inheritance";
import {et2_calendar_view} from "./et2_widget_view";
import {et2_action_object_impl} from "../../api/js/etemplate/et2_core_DOMWidget";
import {et2_calendar_event} from "./et2_widget_event";
import {et2_calendar_planner_row} from "./et2_widget_planner_row";
import {egw} from "../../api/js/jsapi/egw_global";
import {egw_getObjectManager, egwActionObject} from "../../api/js/egw_action/egw_action.js";
import {
	EGW_AI_DRAG_ENTER,
	EGW_AI_DRAG_OUT,
	EGW_AO_FLAG_IS_CONTAINER
} from "../../api/js/egw_action/egw_action_constants.js";
import {et2_IDetachedDOM, et2_IPrint, et2_IResizeable} from "../../api/js/etemplate/et2_core_interfaces";
import {et2_compileLegacyJS} from "../../api/js/etemplate/et2_core_legacyJSFunctions";
import {et2_no_init} from "../../api/js/etemplate/et2_core_common";
import {CalendarApp} from "./app";
import {sprintf} from "../../api/js/egw_action/egw_action_common.js";
import {et2_dataview_grid} from "../../api/js/etemplate/et2_dataview_view_grid";
import {et2_selectbox} from "../../api/js/etemplate/et2_widget_selectbox";
import {formatDate, formatTime} from "../../api/js/etemplate/Et2Date/Et2Date";
import interact from "@interactjs/interactjs/index";
import type {InteractEvent} from "@interactjs/core/InteractEvent";

/**
 * Class which implements the "calendar-planner" XET-Tag for displaying a longer
 * ( > 10 days) span of time.  Events can be grouped into rows by either user,
 * category, or month.  Their horizontal position and size in the row is determined
 * by their start date and duration relative to the displayed date range.
 *
 * @augments et2_calendar_view
 */
export class et2_calendar_planner extends et2_calendar_view implements et2_IDetachedDOM, et2_IResizeable, et2_IPrint
{
	static readonly _attributes: any = {
		group_by: {
			name: "Group by",
			type: "string", // or category ID
			default: "0",
			description: "Display planner by 'user', 'month', or the given category"
		},
		filter: {
			name: "Filter",
			type: "string",
			default: '',
			description: 'A filter that is used to select events.  It is passed along when events are queried.'
		},
		show_weekend: {
			name: "Weekends",
			type: "boolean",
			default: egw.preference('days_in_weekview','calendar') != 5,
			description: "Display weekends.  The date range should still include them for proper scrolling, but they just won't be shown."
		},
		hide_empty: {
			name: "Hide empty rows",
			type: "boolean",
			default: false,
			description: "Hide rows with no events."
		},
		value: {
			type: "any",
			description: "A list of events, optionally you can set start_date, end_date and group_by as keys and events will be fetched"
		},
		"onchange": {
			"name": "onchange",
			"type": "js",
			"default": et2_no_init,
			"description": "JS code which is executed when the date range changes."
		},
		"onevent_change": {
			"name": "onevent_change",
			"type": "js",
			"default": et2_no_init,
			"description": "JS code which is executed when an event changes."
		}
	};

	public static readonly DEFERRED_ROW_TIME: number = 100;

	private gridHeader: JQuery;
	private headerTitle: JQuery;
	private headers: JQuery;
	private rows: JQuery;
	private grid: JQuery;
	private vertical_bar: JQuery;

	private doInvalidate: boolean;
	
	private registeredCallbacks: any[];
	private cache: {};
	private _deferred_row_updates: {};
	private grouper: any;

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_calendar_planner._attributes, _child || {}));


		// Main container
		this.div = jQuery(document.createElement("div"))
			.addClass("calendar_plannerWidget");

		// Header
		this.gridHeader = jQuery(document.createElement("div"))
			.addClass("calendar_plannerHeader")
			.appendTo(this.div);
		this.headerTitle = jQuery(document.createElement("div"))
			.addClass("calendar_plannerHeaderTitle")
			.appendTo(this.gridHeader);
		this.headers = jQuery(document.createElement("div"))
			.addClass("calendar_plannerHeaderRows")
			.appendTo(this.gridHeader);

		this.rows = jQuery(document.createElement("div"))
			.addClass("calendar_plannerRows")
			.appendTo(this.div);
		this.grid = jQuery(document.createElement("div"))
			.addClass("calendar_plannerGrid")
			.appendTo(this.div);

		this.vertical_bar = jQuery(document.createElement("div"))
			.addClass('verticalBar')
			.appendTo(this.div);

		this.value = [];

		// Update timer, to avoid redrawing twice when changing start & end date
		this.update_timer = null;
		this.doInvalidate = true;

		this.setDOMNode(this.div[0]);

		this.registeredCallbacks = [];
		this.cache = {};
		this._deferred_row_updates = {};
	}

	destroy( )
	{
		super.destroy();

		this.div.off();

		for(var i = 0; i < this.registeredCallbacks.length; i++)
		{
			egw.dataUnregisterUID(this.registeredCallbacks[i],null,this);
		}
	}

	doLoadingFinished( )
	{
		super.doLoadingFinished();

		// Don't bother to draw anything if there's no date yet
		if(this.options.start_date)
		{
			this._drawGrid();
		}

		// Automatically bind drag and resize for every event using jQuery directly
		// - no action system -
		var planner = this;

		this.cache = {};
		this._deferred_row_updates = {};

		/**
		 * If user puts the mouse over an event, then we'll set up resizing so
		 * they can adjust the length.  Should be a little better on resources
		 * than binding it for every calendar event.
		 */
		this.div.on('mouseover', '.calendar_calEvent:not(.ui-resizable):not(.rowNoEdit)', function()
		{
			// Load the event
			planner._get_event_info(this);
			var that = this;

			//Resizable event handler
			interact(this).resizable
			({
				invert: "reposition",
				edges: {right: true},
				startAxis: "x",
				lockAxis: "x",
				containment: 'parent',


				/**
				 * If dragging to resize an event, abort drag to create
				 *
				 * @param {InteractEvent} event
				 */
				onstart: function(event : InteractEvent)
				{
					if(planner.drag_create.start)
					{
						// Abort drag to create, we're dragging to resize
						planner._drag_create_end({});
					}
					event.target.classList.add("resizing");
				},

				/**
				 * Triggered at the end of resizing the calEvent.
				 *
				 * @param {InteractEvent} event
				 */
				onend: function(event : InteractEvent)
				{
					interact(this).unset();
					var e = new jQuery.Event('change');
					e.originalEvent = event;
					e.data = {duration: 0};
					var event_data = planner._get_event_info(this);
					var event_widget = planner.getWidgetById(event_data.widget_id);
					var sT = event_widget.options.value.start_m;
					if(typeof this.dropEnd != 'undefined')
					{
						var eT = parseInt(this.dropEnd.getUTCHours() * 60) + parseInt(this.dropEnd.getUTCMinutes());
						e.data.duration = ((eT - sT) / 60) * 3600;

						if(event_widget)
						{
							event_widget.options.value.end_m = eT;
							event_widget.options.value.duration = e.data.duration;
						}

						// Leave the helper there until the update is done
						var loading = event_data.event_node;

						// and add a loading icon so user knows something is happening
						jQuery('.calendar_timeDemo', loading).after('<div class="loading"></div>');

						jQuery(this).trigger(e);

						// Remove loading, done or not
						loading.remove();
					}
					// Clear the helper, re-draw
					if(event_widget)
					{
						(<et2_calendar_planner_row>event_widget.getParent()).position_event(event_widget);
					}
				}.bind(this),

				/**
				 * Triggered during the resize, on the drag of the resize handler
				 *
				 * @param {InteractEvent} event
				 */
				onmove: function(event : InteractEvent)
				{
					event.target.style.width = event.rect.width + "px";
					let position;
					if(planner.options.group_by == 'month')
					{
						position = {left: event.clientX, top: event.clientY};
					}
					else
					{
						let offset = parseInt(getComputedStyle(event.target).left) - event.rect.left;
						position = {top: event.rect.top, left: event.rect.right + offset};
					}
					planner._drag_helper(this, position, event.rect.height);
				}.bind(this)
			});
		});
		this.div
			.on('mousemove', function(event)
			{
				// Ignore headers
				if(planner.headers.has(event.target).length !== 0)
				{
					planner.vertical_bar.hide();
					return;
				}
				// Position bar by mouse
				planner.vertical_bar.position({
					my: 'right-1',
					of: event,
					collision: 'fit'
				});
				planner.vertical_bar.css('top','0px');

				// Get time at mouse
				if(jQuery(event.target).closest('.calendar_eventRows').length == 0)
				{
					// "Invalid" times, from space after the last planner row, or header
					var time = planner._get_time_from_position(event.pageX - planner.grid.offset().left, 10);
				}
				else if(planner.options.group_by == 'month')
				{
					var time = planner._get_time_from_position(event.clientX, event.clientY);
				}
				else
				{
					var time = planner._get_time_from_position(event.offsetX, event.offsetY);
				}
				// Passing to formatter, cancel out timezone
				if(time)
				{
					const formatDate = new Date(time.valueOf() + time.getTimezoneOffset() * 60 * 1000);
					planner.vertical_bar
						.html('<span>'+date(egw.preference('timeformat','calendar') == 12 ? 'h:ia' : 'H:i',formatDate)+'</span>')
						.show();

					if(planner.drag_create.event && planner.drag_create.parent && planner.drag_create.end)
					{

						planner.drag_create.end.date = time.toJSON();
						planner._drag_update_event();
					}
				}
				else
				{
					// No (valid) time, just hide
					planner.vertical_bar.hide();
				}
			})
			.on('mousedown', jQuery.proxy(this._mouse_down, this))
			.on('mouseup', jQuery.proxy(this._mouse_up, this));

		// Actions may be set on a parent, so we need to explicitly get in here
		// and get ours
		this._link_actions(this.options.actions || this.getParent().options.actions || []);

		// Customize and override some draggable settings
		this.div
			.on('dragstart', '.calendar_calEvent', function(event)
			{
				// Cancel drag to create, we're dragging an existing event
				planner._drag_create_end();
			});
		return true;
	}

	_createNamespace() {
		return true;
	}
	
	/**
	 * These handle the differences between the different group types.
	 * They provide the different titles, labels and grouping
	 */
	groupers = {
		// Group by user has one row for each user
		user :
		{
			// Title in top left corner
			title: function() : string
			{
				return this.egw().lang('User');
			},
			// Column headers
			headers: async function()
			{
				var start = new Date(this.options.start_date);
				var end = new Date(this.options.end_date);
				var start_date = new Date(start.getUTCFullYear(), start.getUTCMonth(),start.getUTCDate());
				var end_date = new Date(end.getUTCFullYear(), end.getUTCMonth(),end.getUTCDate());
				var day_count = Math.round((end_date - start_date) /(1000*3600*24))+1;
				if(day_count >= 6)
				{
					this.headers.append(this._header_months(start, day_count));
				}
				if(day_count < 120)
				{
					var weeks = this._header_weeks(start, day_count);
					this.headers.append(weeks);
					this.grid.append(weeks);
				}
				if(day_count < 60)
				{
					var days = await this._header_days(start, day_count);
					this.headers.append(days);
					this.grid.append(days);
				}
				if(day_count <= 7)
				{
					var hours = this._header_hours(start, day_count);
					this.headers.append(hours);
					this.grid.append(hours);
				}
			},
			// Labels for the rows
			row_labels: function() {
				var labels = [];
				var already_added = [];
				var options = [];
				var resource = null;
				if(app.calendar && app.calendar.sidebox_et2 && app.calendar.sidebox_et2.getWidgetById('owner'))
				{
					const owner = app.calendar.sidebox_et2.getWidgetById('owner')
					options = [...owner.select_options, ...owner._selected_remote];
				}
				else
				{
					options = this.getArrayMgr("sel_options").getRoot().getEntry('owner');
				}
				for(var i = 0; i < this.options.owner.length; i++)
				{
					var user = this.options.owner[i];
					// Handle grouped resources like mailing lists - pull it from sidebox owner
					// and expand to their contents
					if(options && options.find &&
						((resource = options.find(function(element) {return element.value == user;}) || {}) || isNaN(user)))
					{
						if(resource && resource.resources)
						{
							for(var j = 0; j < resource.resources.length; j++)
							{
								var id = resource.resources[j];
								if(already_added.indexOf('' + id) < 0)
								{
									labels.push({
										id: id,
										label: this._get_owner_name(id)||'',
										data: {participants:id,owner:id}
									});
									already_added.push(''+id);
								}
							}
						}
						else if (user < 0)
						{
							// Group, but no users found.  Need those.
							egw.accountData(parseInt(user),'account_fullname',true,function(result) {
								this.invalidate();
							},this);
						}
						else if(already_added.indexOf(''+user) < 0 && (isNaN(user) || parseInt(user) >= 0))
						{
							labels.push({
								id: user,
								label: this._get_owner_name(user),
								data: {participants:user,owner:user}
							});
							already_added.push(''+user);
						}
					}
					else if (user < 0)	// groups
					{
						egw.accountData(parseInt(user),'account_fullname',true,function(result) {
							for(var id in result)
							{
								if(already_added.indexOf(''+id) < 0)
								{
									this.push({id: id, label: result[id]||'', data: {participants:id,owner:id}});
									already_added.push(''+id);
								}
							}
						},labels);
					}
					else	// users
					{
						if(already_added.indexOf(user) < 0)
						{
							var label = this._get_owner_name(user)||'';
							labels.push({id: user, label: label, data: {participants:user,owner:''}});
							already_added.push(''+user);
						}
					}
				}

				return labels.sort(function(a,b) {
					return a.label.localeCompare(b.label);
				});
			},
			// Group the events into the rows
			group: function(labels, rows, event) {
				// convert filter to allowed status
				var status_to_show = ['U','A','T','D','G'];
				switch(this.options.filter)
				{
					case 'unknown':
						status_to_show = ['U','G']; break;
					case 'accepted':
						status_to_show = ['A']; break;
					case 'tentative':
						status_to_show = ['T']; break;
					case 'rejected':
						status_to_show = ['R']; break;
					case 'delegated':
						status_to_show = ['D']; break;
					case 'all':
						status_to_show = ['U','A','T','D','G','R']; break;
					default:
						status_to_show = ['U','A','T','D','G']; break;
				}
				var participants = event.participants;
				var add_row = function(user, participant) {
					var label_index = false;
					for(var i = 0; i < labels.length; i++)
					{
						if(labels[i].id == user)
						{
							label_index = i;
							break;
						}
					}
					if(participant && label_index !== false && status_to_show.indexOf(participant.substr(0,1)) >= 0 ||
						!participant && label_index !== false ||
						this.options.filter === 'owner' && event.owner === user)
					{
						if(typeof rows[label_index] === 'undefined')
						{
							rows[label_index] = [];
						}
						rows[label_index].push(event);
					}
				};
				for(var user in participants)
				{
					var participant = participants[user];
					if (parseInt(user) < 0)	// groups
					{
						var planner = this;
						egw.accountData(user,'account_fullname',true,function(result) {
							for(var id in result)
							{
								if(!participants[id]) add_row.call(planner,id,participant);
							}
						},labels);
						continue;
					}
					add_row.call(this, user, participant);
				}
			},
			// Draw a single row
			draw_row: function(sort_key, label, events) {
				var row = this._drawRow(sort_key, label,events,this.options.start_date, this.options.end_date);
				if(this.options.hide_empty && !events.length)
				{
					row.set_disabled(true);
				}
				// Highlight current user, sort_key is account_id
				if(sort_key === egw.user('account_id'))
				{
					row.set_class('current_user')
				}
				// Set account_id so event.owner_check can use it
				row.options.owner = sort_key;

				// Since the daywise cache is by user, we can tap in here
				var t = new Date(this.options.start_date);
				var end = new Date(this.options.end_date);
				do
				{
					var cache_id = CalendarApp._daywise_cache_id(t, sort_key);
					egw.dataRegisterUID(cache_id, row._data_callback, row);

					t.setUTCDate(t.getUTCDate() + 1);
				}
				while(t < end);
				return row;
			}
		},

		// Group by month has one row for each month
		month:
		{
			title: function() { return this.egw().lang('Month');},
			headers: function() {
				this.headers.append(this._header_day_of_month());
			},
			row_labels: function() {
				var labels = [];
				var d = new Date(this.options.start_date);
				d = new Date(d.valueOf() + d.getTimezoneOffset() * 60 * 1000);
				for(var i = 0; i < 12; i++)
				{
					// Not using UTC because we corrected for timezone offset
					labels.push({id:sprintf('%04d-%02d', d.getFullYear(), d.getMonth()), label:this.egw().lang(date('F',d))+' '+d.getFullYear()});
					d.setMonth(d.getMonth()+1);
				}
				return labels;
			},
			group: function(labels, rows,event) {
				// Yearly planner does not show infologs
				if(event && event.app && event.app == 'infolog') return;

				var start = new Date(event.start);
				start = new Date(start.valueOf() + start.getTimezoneOffset() * 60 * 1000);
				var key = sprintf('%04d-%02d', start.getFullYear(), start.getMonth());
				var label_index : number|boolean = false;
				for(var i = 0; i < labels.length; i++)
				{
					if(labels[i].id == key)
					{
						label_index = i;
						break;
					}
				}
				if(label_index)
				{
					if(typeof rows[label_index] === 'undefined')
					{
						rows[label_index] = [];
					}
					rows[label_index].push(event);
				}

				// end in a different month?
				var end = new Date(event.end);
				end = new Date(end.valueOf() + end.getTimezoneOffset() * 60 * 1000);
				var end_key = sprintf('%04d-%02d',end.getFullYear(),end.getMonth());
				var year = start.getFullYear();
				var month = start.getMonth();
				key = sprintf('%04d-%02d',year,month);

				do
				{
					var end_label_index = typeof label_index == "boolean" ? 0 : label_index;

					for(let i = end_label_index; i < labels.length; i++)
					{
						if(labels[i].id == key)
						{
							end_label_index = i;
							if(typeof rows[end_label_index] === 'undefined')
							{
								rows[end_label_index] = [];
							}
							break;
						}
					}
					if(end_label_index != label_index)
					{
						rows[end_label_index].push(event);
					}
					if (++month > 11)
					{
						++year;
						month = 0;
					}
					key = sprintf('%04d-%02d',year,month);
				} while(key <= end_key)
			},
			// Draw a single row, but split up the dates
			draw_row: function(sort_key, label, events)
			{
				var key = sort_key.split('-');
				var start = new Date(key[0]+"-"+sprintf("%02d",parseInt(key[1])+1)+"-01T00:00:00Z");
				// Use some care to avoid issues with timezones and daylight savings
				var end = new Date(start);
				end.setUTCMonth(start.getUTCMonth() + 1);
				end.setUTCDate(1);
				end.setUTCHours(0);
				end.setUTCMinutes(0);
				end = new Date(end.valueOf() - 1000);
				end.setUTCMonth(start.getUTCMonth());
				this._drawRow(sort_key, label, events, start, end);
			}
		},
		// Group by category has one row for each [sub]category
		category:
		{
			title: function() { return this.egw().lang('Category');},
			headers: async function() {
				var start = new Date(this.options.start_date);
				var end = new Date(this.options.end_date);
				var start_date = new Date(start.getUTCFullYear(), start.getUTCMonth(),start.getUTCDate());
				var end_date = new Date(end.getUTCFullYear(), end.getUTCMonth(),end.getUTCDate());
				var day_count = Math.round((end_date - start_date) /(1000*3600*24))+1;

				if(day_count >= 6)
				{
					this.headers.append(this._header_months(start, day_count));
				}
				if(day_count < 120)
				{
					var weeks = this._header_weeks(start, day_count);
					this.headers.append(weeks);
					this.grid.append(weeks);
				}
				if(day_count < 60)
				{
					var days = await this._header_days(start, day_count);
					this.headers.append(days);
					this.grid.append(days);
				}
				if(day_count <= 7)
				{
					var hours = this._header_hours(start, day_count);
					this.headers.append(hours);
					this.grid.append(hours);
				}
			},
			row_labels: function() {
				var im = this.getInstanceManager();
				var categories = et2_selectbox.cat_options({
						_type:'select-cat',
						getInstanceManager: function() {return im;}
					},{application: 'calendar'});

				var labels = [];
				let app_calendar = this.getInstanceManager().app_obj.calendar || app.calendar;
				if(!app_calendar.state.cat_id ||
					app_calendar.state.cat_id.toString() === '' ||
					app_calendar.state.cat_id.toString() == '0'
				)
				{
					app_calendar.state.cat_id = '';
					labels.push({id:'',value:'',label: egw.lang('none'), main: '', data: {}});
					labels = labels.concat(categories);
				}
				else
				{
					var cat_id = app_calendar.state.cat_id;
					if(typeof cat_id == 'string')
					{
						cat_id = cat_id.split(',');
					}
					for(var i = 0; i < cat_id.length; i++)
					{
						// Find label for that category
						for(var j = 0; j < categories.length; j++)
						{
							if(categories[j].value == cat_id[i])
							{
								categories[j].id = categories[j].value;
								labels.push(categories[j]);
								break;
							}
						}

						// Get its children immediately
						egw.json(
							'EGroupware\\Api\\Etemplate\\Widget\\Select::ajax_get_options',
							['select-cat',',,,calendar,'+cat_id[i]],
							function(data) {
								labels = labels.concat(data);
							}
						).sendRequest(false);
					}
				}

				for(var i = labels.length -1; i >= 0; i--)
				{
					labels[i].id = labels[i].value;
					labels[i].data = {
						cat_id: labels[i].id,
						main: labels[i].value==labels[i].main
					};
					if(labels[i].children && labels[i].children.length)
					{
						labels[i].data.has_children = true;
					}
				}
				return labels;
			},
			group: function(labels, rows, event) {
				var cats = event.category;
				let app_calendar = this.getInstanceManager().app_obj.calendar || app.calendar;
				if(typeof event.category === 'string')
				{
					cats = cats.split(',');
				}
				for(var cat = 0; cat < cats.length; cat++)
				{
					var label_index = false;
					var category = cats[cat] ? parseInt(cats[cat],10) : false;
					if(category == 0 || !category) category = '';
					for(var i = 0; i < labels.length; i++)
					{
						if(labels[i].id == category)
						{
							// If there's no cat filter, only show the top level
							if(!app_calendar.state.cat_id)
							{
								for(var j = 0; j < labels.length; j++)
								{
									if(labels[j].id == labels[i].main)
									{
										label_index = j;
										break;
									}
								}
								break;
							}
							label_index = i;
							break;
						}
					}
					if(label_index !== false && typeof rows[label_index] === 'undefined')
					{
						rows[label_index] = [];
					}
					if(label_index !== false && rows[label_index].indexOf(event) === -1)
					{
						rows[label_index].push(event);
					}
				}
			},
			draw_row: function(sort_key, label, events) {
				var row = this._drawRow(sort_key, label,events,this.options.start_date, this.options.end_date);
				if(this.options.hide_empty && !events.length)
				{
					row.set_disabled(true);
				}
				return row;
			}
		}
	};

	/**
	 * Something changed, and the planner needs to be re-drawn.  We wait a bit to
	 * avoid re-drawing twice if start and end date both changed, then recreate.
	 *
	 * @param {boolean} trigger =false Trigger an event once things are done.
	 *	Waiting until invalidate completes prevents 2 updates when changing the date range.
	 * @returns {undefined}
	 */
	invalidate( trigger?)
	{

		// Busy
		if(!this.doInvalidate) return;

		// Not yet ready
		if(!this.options.start_date || !this.options.end_date) return;

		// Wait a bit to see if anything else changes, then re-draw the days
		if(this.update_timer !== null)
		{
			window.clearTimeout(this.update_timer);
		}
		this.update_timer = window.setTimeout(jQuery.proxy(function() {
			this.widget.doInvalidate = false;

			// Show AJAX loader
			this.widget.loader.show();

			this.widget.cache = {};
			this._deferred_row_updates = {};

			this.widget._fetch_data();

			this.widget._drawGrid();

			if(this.trigger)
			{
				this.widget.change();
			}
			this.widget.update_timer = null;
			this.widget.doInvalidate = true;

		this.widget._updateNow();
			window.setTimeout(jQuery.proxy(function() {if(this.loader) this.loader.hide();},this.widget),500);
		},{widget:this,"trigger":trigger}),et2_dataview_grid.ET2_GRID_INVALIDATE_TIMEOUT);
	}

	detachFromDOM()
	{
		// Remove the binding to the change handler
		jQuery(this.div).off("change.et2_calendar_timegrid");

		return super.detachFromDOM();
	}

	attachToDOM( )
	{
		let result = super.attachToDOM();

		// Add the binding for the event change handler
		jQuery(this.div).on("change.et2_calendar_timegrid", '.calendar_calEvent', this, function(e) {
			// Make sure function gets a reference to the widget
			var args = Array.prototype.slice.call(arguments);
			if(args.indexOf(this) == -1) args.push(this);

			return e.data.event_change.apply(e.data, args);
		});

		// Add the binding for the change handler
		jQuery(this.div).on("change.et2_calendar_timegrid", '*:not(.calendar_calEvent)', this, function(e) {
				return e.data.change.call(e.data, e, this);
			});

		return result;
	}

	getDOMNode(_sender)
	{
		if(_sender === this || !_sender)
		{
			return this.div[0];
		}
		if(_sender._parent === this)
		{
			return this.rows[0];
		}
	}

	/**
	 * Creates all the DOM nodes for the planner grid
	 *
	 * Any existing nodes (& children) are removed, the headers & labels are
	 * determined according to the current group_by value, and then the rows
	 * are created.
	 *
	 * @method
	 * @private
	 *
	 */
	async _drawGrid()
	{

		this.div.css('height', this.options.height);

		// Clear old events
		var delete_index = this._children.length - 1;
		while(this._children.length > 0 && delete_index >= 0)
		{
			this._children[delete_index].destroy();
			this.removeChild(this._children[delete_index--]);
		}

		// Clear old rows
		this.rows.empty()
			.append(this.grid);
		this.grid.empty();

		var grouper = this.grouper;
		if(!grouper) return;

		// Headers
		this.headers.empty();
		this.headerTitle.text(grouper.title.apply(this));
		await grouper.headers.apply(this);
		this.grid.find('*').contents().filter(function(){
			return this.nodeType === 3;
		}).remove();

		// Get the rows / labels
		var labels = grouper.row_labels.call(this);

		// Group the events
		var events = {};
		for(var i = 0; i < this.value.length; i++)
		{
			grouper.group.call(this, labels, events, this.value[i]);
		}

		// Set height for rows
		this.rows.height(this.div.height() - this.headers.outerHeight());

		// Draw the rows
		let app_calendar = this.getInstanceManager().app_obj.calendar || app.calendar;
		for(var key in labels)
		{
			if (!labels.hasOwnProperty(key)) continue;

			// Skip sub-categories (events are merged into top level)
			if(this.options.group_by == 'category' &&
				(!app_calendar.state.cat_id || app_calendar.state.cat_id == '') &&
				labels[key].id != labels[key].main
			)
			{
				continue;
			}
			var row = grouper.draw_row.call(this,labels[key].id, labels[key].label, events[key] || []);

			// Add extra data for clicking on row
			if(row)
			{
				for(var extra in labels[key].data)
				{
					row.getDOMNode().dataset[extra] = labels[key].data[extra];
				}
			}
		}

		// Adjust header if there's a scrollbar
		if(this.rows.children().last().length)
		{
			this.gridHeader.css('margin-right', (this.rows.width() - this.rows.children().first().width()) + 'px');
		}
		// Add actual events
		for(var key in this._deferred_row_updates)
		{
			window.clearTimeout(key);
		}
		window.setTimeout(jQuery.proxy(function() {
			this._deferred_row_update();
		}, this ),et2_calendar_planner.DEFERRED_ROW_TIME);
		this.value = [];
	}

	/**
	 * Draw a single row of the planner
	 *
	 * @param {string} key Index into the grouped labels & events
	 * @param {string} label
	 * @param {Array} events
	 * @param {Date} start
	 * @param {Date} end
	 */
	_drawRow(key, label, events, start, end)
	{
		let row = et2_createWidget('calendar-planner_row',{
				id: 'planner_row_'+key,
				label: label,
				start_date: start,
				end_date: end,
				value: events,
				readonly: this.options.readonly
			},this);


		if(this.isInTree())
		{
			row.doLoadingFinished();
		}

		return row;
	}


	_header_day_of_month()
	{
		let day_width = 3.23; // 100.0 / 31;

		// month scale with navigation
		var content = '<div class="calendar_plannerScale">';
		var start = new Date(this.options.start_date);
		start = new Date(start.valueOf() + start.getTimezoneOffset() * 60 * 1000);
		var end = new Date(this.options.end_date);
		end = new Date(end.valueOf() + end.getTimezoneOffset() * 60 * 1000);

		var title = this.egw().lang(date('F',start))+' '+date('Y',start)+' - '+
			this.egw().lang(date('F',end))+' '+date('Y',end);

		content += '<div class="calendar_plannerMonthScale th et2_link" style="left: 0; width: 100%;">'+
				title+"</div>";
		content += "</div>";		// end of plannerScale

		// day of month scale
		content +='<div class="calendar_plannerScale">';

		for(var left = 0, i = 0; i < 31; left += day_width,++i)
		{
			content += '<div class="calendar_plannerDayOfMonthScale " style="left: '+left+'%; width: '+day_width+'%;">'+
				(1+i)+"</div>\n";
		}
		content += "</div>\n";

		return content;
	}
	/**
	 * Update the 'now' line
	 * @private
	 */
	public _updateNow()
	{
		let now = super._updateNow();
		if(now === false || this.grouper == this.groupers.month)
		{
			this.now_div.hide();
			return false;
		}

		let row = null;
		for(let i = 0; i < this._children.length && row == null; i++)
		{
			if(this._children[i].instanceOf(et2_calendar_planner_row))
			{
				row = this._children[i];
			}
		}
		if(!row)
		{
			this.now_div.hide();
			return false;
		}
		this.now_div.appendTo(this.grid)
		    .show()
		    .css('left', row._time_to_position(now) + '%');
	}

	/**
	 * Make a header showing the months
	 * @param {Date} start
	 * @param {number} days
	 * @returns {string} HTML snippet
	 */
	_header_months(start, days)
	{
		var content = '<div class="calendar_plannerScale">';
		var days_in_month = 0;
		var day_width = 100 / days;
		var end = new Date(start);
		end.setUTCDate(end.getUTCDate()+days);
		var t = new Date(start.valueOf());
		for(var left = 0,i = 0; i < days;t.setUTCDate(1),t.setUTCMonth(t.getUTCMonth()+1),left += days_in_month*day_width,i += days_in_month)
		{
			var u = new Date(t.getUTCFullYear(),t.getUTCMonth()+1,0,-t.getTimezoneOffset()/60);
			days_in_month =  1+ ((u-t) / (24*3600*1000));

			var first = new Date(t.getUTCFullYear(),t.getUTCMonth(),1,-t.getTimezoneOffset()/60);
			if(days_in_month <= 0) break;

			if (i + days_in_month > days)
			{
				days_in_month = days - i;
			}
			var title = this.egw().lang(date('F',new Date(t.valueOf() + t.getTimezoneOffset() * 60 * 1000)));
			if (days_in_month > 10)
			{
				title += '</span> <span class="et2_clickable et2_link" data-sortby="month">'+t.getUTCFullYear();
			}
			else if (days_in_month < 5)
			{
				title = '&nbsp;';
			}
			content += '<div class="calendar_plannerMonthScale" data-date="'+first.toJSON()+
				'" style="left: '+left+'%; width: '+(day_width*days_in_month)+'%;"><span'+
				' data-planner_view="month" class="et2_clickable et2_link">'+
				title+"</span></div>";
		}
		content += "</div>";		// end of plannerScale

		return content;
	}

	/**
	 * Make a header showing the week numbers
	 *
	 * @param {Date} start
	 * @param {number} days
	 * @returns {string} HTML snippet
	 */
	_header_weeks(start, days)
	{

		var content = '<div class="calendar_plannerScale" data-planner_view="week">';
		var state = '';

		// we're not using UTC so date() formatting function works
		var t = new Date(start.valueOf());

		// Make sure we're lining up on the week
		let app_calendar = this.getInstanceManager().app_obj.calendar || app.calendar;
		var week_end = app_calendar.date.end_of_week(start);
		var days_in_week = Math.floor(((week_end-start ) / (24*3600*1000))+1);
		var week_width = 100 / days * (days <= 7 ? days : days_in_week);
		for(var left = 0,i = 0; i < days; t.setUTCDate(t.getUTCDate() + 7),left += week_width)
		{
			// Avoid overflow at the end
			if(days - i < 7)
			{
				days_in_week = days-i;
			}
			var usertime = new Date(t.valueOf());
			if(start.getTimezoneOffset() < 0)
			{
				// Gets the right week # east of GMT.  West does not need it(?)
				usertime.setUTCMinutes(usertime.getUTCMinutes() - start.getTimezoneOffset());
			}

			week_width = 100 / days * Math.min(days, days_in_week);

			var title = this.egw().lang('Week')+' '+app_calendar.date.week_number(usertime);

			if(start.getTimezoneOffset() > 0)
			{
				// Gets the right week start west of GMT
				usertime.setUTCMinutes(usertime.getUTCMinutes() +start.getTimezoneOffset());
			}
			state = app_calendar.date.start_of_week(usertime);
			state.setUTCHours(0);
			state.setUTCMinutes(0);
			state = state.toJSON();

			if(days_in_week > 1 || days == 1)
			{
				content += '<div class="calendar_plannerWeekScale et2_clickable et2_link" data-date=\'' + state + '\' style="left: '+left+'%; width: '+week_width+'%;">'+title+"</div>";
			}
			i+= days_in_week;
			if(days_in_week != 7)
			{
				t.setUTCDate(t.getUTCDate() - (7 - days_in_week));
				days_in_week = 7;
			}
		}
		content += "</div>";		// end of plannerScale

		return content;
	}

	/**
	 * Make a header for some days
	 *
	 * @param {Date} start
	 * @param {number} days
	 * @returns {string} HTML snippet
	 */
	async _header_days(start, days)
	{
		var day_width = 100 / days;
		var content = '<div class="calendar_plannerScale'+(days > 3 ? 'Day' : '')+'" data-planner_view="day" >';

		// we're not using UTC so date() formatting function works
		var t = new Date(start.valueOf() + start.getTimezoneOffset() * 60 * 1000);
		for(var left = 0,i = 0; i < days; t.setDate(t.getDate()+1),left += day_width,++i)
		{
			if(!this.options.show_weekend && [0,6].indexOf(t.getDay()) !== -1 ) continue;
			var holidays = [];
			var tempDate = new Date(t);
			tempDate.setMinutes(tempDate.getMinutes()-tempDate.getTimezoneOffset());
			var title = '';
			let state = new Date(t.valueOf() - t.getTimezoneOffset() * 60 * 1000);
			var day_class = await this.day_class_holiday(state,holidays, days);

			if (days <= 3)
			{
				title = this.egw().lang(date('l',t))+', '+date('j',t)+'. '+this.egw().lang(date('F',t));
			}
			else if (days <= 7)
			{
				title = this.egw().lang(date('l',t))+' '+date('j',t);
			}
			else
			{
				title = this.egw().lang(date('D',t)).substr(0,2)+'<br />'+date('j',t);
			}

			content += '<div class="calendar_plannerDayScale et2_clickable et2_link '+ day_class+
				'" data-date=\'' + state.toJSON() +'\''+
				(holidays ? ' title="'+holidays.join(',')+'"' : '')+'>'+title+"</div>\n";
		}
		content += "</div>";		// end of plannerScale

		return content;
	}

	/**
	 * Create a header with hours
	 *
	 * @param {Date} start
	 * @param {number} days
	 * @returns {string} HTML snippet for the header
	 */
	_header_hours(start,days)
	{
		var divisors = [1,2,3,4,6,8,12];
		var decr = 1;
		for(var i = 0; i < divisors.length; i++)	// numbers dividing 24 without rest
		{
			if (divisors[i] > days) break;
			decr = divisors[i];
		}
		var hours = days * 24;
		if (days === 1)			// for a single day we calculate the hours of a days, to take into account daylight saving changes (23 or 25 hours)
		{
			var t = new Date(start.getUTCFullYear(),start.getUTCMonth(),start.getUTCDate(),-start.getTimezoneOffset()/60);
			var s = new Date(start);
			s.setUTCHours(23);
			s.setUTCMinutes(59);
			s.setUTCSeconds(59);
			hours = Math.ceil((s.getTime() - t.getTime()) / 3600000);
		}
		var cell_width = 100 / hours * decr;

		var content = '<div class="calendar_plannerScale" data-planner_view="day">';

		// we're not using UTC so date() formatting function works
		var t = new Date(start.valueOf() + start.getTimezoneOffset() * 60 * 1000);
		for(var left = 0,i = 0; i < hours; left += cell_width,i += decr)
		{
			if(!this.options.show_weekend && [0,6].indexOf(t.getDay()) !== -1 ) continue;
			var title = date(egw.preference('timeformat','calendar') == 12 ? 'ha' : 'H',t);

			content += '<div class="calendar_plannerHourScale et2_link" data-date="' + t.toJSON() +'" style="left: '+left+'%; width: '+(cell_width)+'%;">'+title+"</div>";
			t.setHours(t.getHours()+decr);
		}
		content += "</div>";		// end of plannerScale

		return content;
	}

	/**
	 * Applies class for today, and any holidays for current day
	 *
	 * @param {Date} date
	 * @param {string[]} holiday_list Filled with a list of holidays for that day
	 * @param {integer} days Number of days shown in the day header
	 *
	 * @return {string} CSS Classes for the day.  calendar_calBirthday, calendar_calHoliday, calendar_calToday and calendar_weekend as appropriate
	 */
	async day_class_holiday(date, holiday_list, days?)
	{
		if(!date)
		{
			return '';
		}

		// Holidays and birthdays
		const fetched = await this.egw().holidays(date.getUTCFullYear());
		var day_class = '';

		// Pass a string rather than the date object, to make sure it doesn't get changed
		let date_key = formatDate(this.date_helper(date.toJSON()), {dateFormat: "Ymd"});
		if(fetched && fetched[date_key])
		{
			const dates = fetched[date_key];
			for(var i = 0; i < dates.length; i++)
			{
				if(typeof dates[i]['birthyear'] !== 'undefined')
				{
					day_class += ' calendar_calBirthday ';
					if(typeof days == 'undefined' || days <= 21)
					{
						day_class += ' calendar_calBirthdayIcon ';
					}

					holiday_list.push(dates[i]['name']);
				}
				else
				{
					day_class += 'calendar_calHoliday ';

					holiday_list.push(dates[i]['name']);
				}
			}
		}
		var today = new Date();
		if(date_key === ''+today.getFullYear()+
			sprintf("%02d",today.getMonth()+1)+
			sprintf("%02d",today.getDate())
		)
		{
			day_class += "calendar_calToday ";
		}
		if(date.getUTCDay() == 0 || date.getUTCDay() == 6)
		{
			day_class += "calendar_weekend ";
		}
		return day_class;
	}

	/**
	 * Link the actions to the DOM nodes / widget bits.
	 *
	 * @todo This currently does nothing
	 * @param {object} actions {ID: {attributes..}+} map of egw action information
	 */
	_link_actions(actions)
	{
		if(!this._actionObject)
		{
			// Get the parent?  Might be a grid row, might not.  Either way, it is
			// just a container with no valid actions
			var objectManager = egw_getObjectManager(this.getInstanceManager().app,true,1);
			objectManager = objectManager.getObjectById(this.getInstanceManager().uniqueId,2) || objectManager;
			var parent = objectManager.getObjectById(this.id,3) || objectManager.getObjectById(this._parent.id,3) || objectManager;
			if(!parent)
			{
				debugger;
				egw.debug('error','No parent objectManager found');
				return;
			}

			for(var i = 0; i < parent.children.length; i++)
			{
				var parent_finder = jQuery('#'+this.div.id, parent.children[i].iface.doGetDOMNode());
				if(parent_finder.length > 0)
				{
					parent = parent.children[i];
					break;
				}
			}
		}

		// This binds into the egw action system.  Most user interactions (drag to move, resize)
		// are handled internally using jQuery directly.
		var widget_object = this._actionObject || parent.getObjectById(this.id);
		var aoi = new et2_action_object_impl(this,this.getDOMNode(this)).getAOI();

		/**
		 * Determine if we allow a dropped event to use the invite/change actions,
		 * and enable or disable them appropriately
		 *
		 * @param {egwAction} action
		 * @param {et2_calendar_event} event The event widget being dragged
		 * @param {egwActionObject} target Planner action object
		 */
		var _invite_enabled = function(action, event, target)
		{
			var event = event.iface.getWidget();
			var planner = target.iface.getWidget() || false;
			//debugger;
			if(event === planner || !event || !planner ||
				!event.options || !event.options.value.participants || !planner.options.owner
			)
			{
				return false;
			}
			var owner_match = false;
			var own_row = false;

			for(var id in event.options.value.participants)
			{
				planner.iterateOver(function(row) {
					// Check scroll section or header section
					if(row.div.hasClass('drop-hover') || row.div.has(':hover'))
					{
						owner_match = owner_match || row.node.dataset[planner.options.group_by] === ''+id;
						own_row = (row === event.getParent());
					}
				}, this, et2_calendar_planner_row);

			}
			var enabled = !owner_match &&
				// Not inside its own row
				!own_row;

			widget_object.getActionLink('invite').enabled = enabled;
			widget_object.getActionLink('change_participant').enabled = enabled;

			// If invite or change participant are enabled, drag is not
			widget_object.getActionLink('egw_link_drop').enabled = !enabled;
		};

		aoi.doTriggerEvent = function(_event, _data)
		{

			// Determine target node
			var event = _data.event || false;
			if(!event)
			{
				return;
			}
			if(_data.ui.draggable.classList.contains('rowNoEdit'))
			{
				return;
			}

			/*
			We have to handle the drop in the normal event stream instead of waiting
			for the egwAction system so we can get the helper, and destination
			*/
			if(event.type === 'drop')
			{
				this.getWidget()._event_drop.call(jQuery('.calendar_d-n-d_timeCounter', _data.ui.draggable)[0], this.getWidget(), event, _data.ui);
			}
			var drag_listener = function(event)
			{
				let style = getComputedStyle(_data.ui.helper);
				aoi.getWidget()._drag_helper(jQuery('.calendar_d-n-d_timeCounter', _data.ui.draggable)[0], {
					top: parseInt(style.top),
					left: event.clientX - jQuery(this).parent().offset().left
				}, 0);
			};
			var time = jQuery('.calendar_d-n-d_timeCounter', _data.ui.draggable);
			switch(_event)
			{
				// Triggered once, when something is dragged into the timegrid's div
				case EGW_AI_DRAG_ENTER:
					// Listen to the drag and update the helper with the time
					// This part lets us drag between different timegrids
					jQuery(_data.ui.draggable).on('drag.et2_timegrid' + widget_object.id, drag_listener);
					jQuery(_data.ui.draggable).on('dragend.et2_timegrid' + widget_object.id, function()
					{
						jQuery(_data.ui.draggable).off('drag.et2_timegrid' + widget_object.id);
					});
					if(time.length)
					{
						// The out will trigger after the over, so we count
						time.data('count', time.data('count') + 1);
					}
					else
					{
						jQuery(_data.ui.draggable).prepend('<div class="calendar_d-n-d_timeCounter" data-count="1"><span></span></div>');
					}

					break;

				// Triggered once, when something is dragged out of the timegrid
				case EGW_AI_DRAG_OUT:
					// Stop listening
					jQuery(_data.ui.draggable).off('drag.et2_timegrid' + widget_object.id);
					// Remove any highlighted time squares
					jQuery('[data-date]',this.doGetDOMNode()).removeClass("ui-state-active");

					// Out triggers after the over, count to not accidentally remove
					time.data('count',time.data('count')-1);
					if(time.length && time.data('count') <= 0)
					{
						time.remove();
					}
					break;
			}
		};

		if (widget_object == null) {
			// Add a new container to the object manager which will hold the widget
			// objects
			widget_object = parent.insertObject(false, new egwActionObject(
				this.id, parent, aoi,
				this._actionManager || parent.manager.getActionById(this.id) || parent.manager
			),EGW_AO_FLAG_IS_CONTAINER);
		}
		else
		{
			widget_object.setAOI(aoi);
		}
		// Go over the widget & add links - this is where we decide which actions are
		// 'allowed' for this widget at this time
		var action_links = this._get_action_links(actions);

		this._init_links_dnd(widget_object.manager, action_links);

		widget_object.updateActionLinks(action_links);
		this._actionObject = widget_object;
	}

	/**
	 * Automatically add dnd support for linking
	 *
	 * @param {type} mgr
	 * @param {type} actionLinks
	 */
	_init_links_dnd( mgr,actionLinks)
	{

		if (this.options.readonly) return;

		var self = this;

		var drop_action = mgr.getActionById('egw_link_drop');
		var drop_change_participant = mgr.getActionById('change_participant');
		var drop_invite = mgr.getActionById('invite');
		var drag_action = mgr.getActionById('egw_link_drag');
		var paste_action = mgr.getActionById('egw_paste');

		// Disable paste action
		if(paste_action == null)
		{
			paste_action = mgr.addAction('popup', 'egw_paste', egw.lang('Paste'), egw.image('editpaste'), function(){},true);
		}
		paste_action.set_enabled(false);

		// Check if this app supports linking
		if(!egw.link_get_registry(this.dataStorePrefix || 'calendar', 'query') ||
			egw.link_get_registry(this.dataStorePrefix || 'calendar', 'title'))
		{
			if(drop_action)
			{
				drop_action.remove();
				if(actionLinks.indexOf(drop_action.id) >= 0)
				{
					actionLinks.splice(actionLinks.indexOf(drop_action.id),1);
				}
			}
			if(drag_action)
			{
				drag_action.remove();
				if(actionLinks.indexOf(drag_action.id) >= 0)
				{
					actionLinks.splice(actionLinks.indexOf(drag_action.id),1);
				}
			}
			return;
		}

		// Don't re-add
		if(drop_action == null)
		{
			// Create the drop action that links entries
			drop_action = mgr.addAction('drop', 'egw_link_drop', egw.lang('Create link'), egw.image('link'), function(action, source, dropped) {
				// Extract link IDs
				var links = [];
				var id = '';
				for(var i = 0; i < source.length; i++)
				{
					if(!source[i].id) continue;
					id = source[i].id.split('::');
					links.push({app: id[0] == 'filemanager' ? 'link' : id[0], id: id[1]});
				}
				if(!links.length)
				{
					return;
				}
				if(links.length && dropped && dropped.iface.getWidget() && dropped.iface.getWidget().instanceOf(et2_calendar_event))
				{
					// Link the entries
					egw.json(self.egw().getAppName()+".etemplate_widget_link.ajax_link.etemplate",
						dropped.id.split('::').concat([links]),
						function(result) {
							if(result)
							{
								this.egw().message('Linked');
							}
						},
						self,
						true,
						self
					).sendRequest();
				}
			},true);

			drop_action.acceptedTypes = ['default','link'];
			drop_action.hideOnDisabled = true;

			// Create the drop action for moving events between planner rows
			var invite_action = function(action, source, target) {

				// Extract link IDs
				var links = [];
				var id = '';
				for(var i = 0; i < source.length; i++)
				{
					// Check for no ID (invalid) or same manager (dragging an event)
					if(!source[i].id) continue;
					if(source[i].manager === target.manager)
					{

						// Find the row, could have dropped on an event
						var row = target.iface.getWidget();
						while(target.parent && row.instanceOf && !row.instanceOf(et2_calendar_planner_row))
						{
							target = target.parent;
							row = target.iface.getWidget();
						}

						// Leave the helper there until the update is done
						var loading = action.ui.helper.clone(true).appendTo(jQuery('body'));

						// and add a loading icon so user knows something is happening
						if(jQuery('.calendar_timeDemo',loading).length == 0)
						{
							jQuery('.calendar_calEventHeader',loading).addClass('loading');
						}
						else
						{
							jQuery('.calendar_timeDemo',loading).after('<div class="loading"></div>');
						}

						var event_data = egw.dataGetUIDdata(source[i].id).data;
						et2_calendar_event.recur_prompt(event_data, function(button_id) {
							if(button_id === 'cancel' || !button_id)
							{
								return;
							}
							var add_owner = [row.node.dataset.participants];

							egw().json('calendar.calendar_uiforms.ajax_invite', [
									button_id==='series' ? event_data.id : event_data.app_id,
									add_owner,
									action.id === 'change_participant' ?
										[source[i].iface.getWidget().getParent().node.dataset.participants] :
										[]
								],
								function() { loading.remove();}
							).sendRequest(true);
						});
						// Ok, stop.
						return false;
					}
				}
			};

			drop_change_participant = mgr.addAction('drop', 'change_participant', egw.lang('Move to'), egw.image('participant'), invite_action,true);
			drop_change_participant.acceptedTypes = ['calendar'];
			drop_change_participant.hideOnDisabled = true;

			drop_invite = mgr.addAction('drop', 'invite', egw.lang('Invite'), egw.image('participant'), invite_action,true);
			drop_invite.acceptedTypes = ['calendar'];
			drop_invite.hideOnDisabled = true;
		}
		if(actionLinks.indexOf(drop_action.id) < 0)
		{
			actionLinks.push(drop_action.id);
		}
		actionLinks.push(drop_invite.id);
		actionLinks.push(drop_change_participant.id);

		// Accept other links, and files dragged from the filemanager
		// This does not handle files dragged from the desktop.  They are
		// handled by et2_nextmatch, since it needs DOM stuff
		if(drop_action.acceptedTypes.indexOf('link') == -1)
		{
			drop_action.acceptedTypes.push('link');
		}

		// Don't re-add
		if(drag_action == null)
		{
			// Create drag action that allows linking
			drag_action = mgr.addAction('drag', 'egw_link_drag', egw.lang('link'), 'link', function(action, selected) {
				// As we wanted to have a general defaul helper interface, we return null here and not using customize helper for links
				// TODO: Need to decide if we need to create a customized helper interface for links anyway
				//return helper;
				return null;
			},true);
		}
		// The planner itself is not draggable, the action is there for the children
		if(false && actionLinks.indexOf(drag_action.id) < 0)
		{
			actionLinks.push(drag_action.id);
		}
		drag_action.set_dragType(['link','calendar']);
	}

	/**
	 * Get all action-links / id's of 1.-level actions from a given action object
	 *
	 * Here we are only interested in drop events.
	 *
	 * @param actions
	 * @returns {Array}
	 */
	_get_action_links(actions)
	{
		var action_links = [];

		// Only these actions are allowed without a selection (empty actions)
		var empty_actions = ['add'];

		for(var i in actions)
		{
			var action = actions[i];
			if(empty_actions.indexOf(action.id) !== -1 ||  action.type === 'drop')
			{
				action_links.push(typeof action.id !== 'undefined' ? action.id : i);
			}
		}
		// Disable automatic paste action, it doesn't have what is needed to work
		action_links.push({
			"actionObj": 'egw_paste',
			"enabled": false,
			"visible": false
		});
		return action_links;
	}

	/**
	 * Show the current time while dragging
	 * Used for resizing as well as drag & drop
	 *
	 * @param {type} element
	 * @param {type} position
	 * @param {type} height
	 */
	_drag_helper(element, position ,height)
	{
		let time = this._get_time_from_position(position.left, position.top);
		element.dropEnd = time;
		let formatted_time = formatTime(time);

		element.innerHTML = '<div class="calendar_d-n-d_timeCounter"><span class="calendar_timeDemo" >'+formatted_time+'</span></div>';

		//jQuery(element).width(jQuery(helper).width());
	}

	/**
	 * Handler for dropping an event on the timegrid
	 *
	 * @param {type} planner
	 * @param {type} event
	 * @param {type} ui
	 */
	_event_drop( planner, event,ui)
	{
		var e = new jQuery.Event('change');
		e.originalEvent = event;
		e.data = {start: 0};
		if(typeof this.dropEnd != 'undefined' && this.dropEnd)
		{
			var drop_date = this.dropEnd.toJSON() || false;

			var event_data = planner._get_event_info(ui.draggable);
			var event_widget = planner.getWidgetById(event_data.widget_id);
			if(event_widget)
			{
				event_widget.options.value.start = event_widget._parent.date_helper(drop_date);

				// Leave the helper there until the update is done
				var loading = event_data.event_node;
				// and add a loading icon so user knows something is happening
				jQuery('.calendar_calEventHeader', event_widget.div).addClass('loading');

				event_widget.recur_prompt(function(button_id) {
					if(button_id === 'cancel' || !button_id) return;
					//Get infologID if in case if it's an integrated infolog event
					if (event_data.app === 'infolog')
					{
						// If it is an integrated infolog event we need to edit infolog entry
						egw().json('stylite_infolog_calendar_integration::ajax_moveInfologEvent',
							[event_data.id, event_widget.options.value.start||false],
							function() {loading.remove();}
						).sendRequest(true);
					}
					else
					{
						//Edit calendar event
						egw().json('calendar.calendar_uiforms.ajax_moveEvent', [
								button_id==='series' ? event_data.id : event_data.app_id,event_data.owner,
								event_widget.options.value.start,
								planner.options.owner||egw.user('account_id')
							],
							function() { loading.remove();}
						).sendRequest(true);
					}
				});
			}
		}
	}

	/**
	 * Use the egw.data system to get data from the calendar list for the
	 * selected time span.
	 *
	 */
	_fetch_data()
	{
		var value = [];
		var fetch = false;
		this.doInvalidate = false;

		for(var i = 0; i < this.registeredCallbacks.length; i++)
		{
			egw.dataUnregisterUID(this.registeredCallbacks[i],false,this);
		}
		this.registeredCallbacks.splice(0,this.registeredCallbacks.length);

		// Remember previous day to avoid multi-days duplicating
		var last_data = [];

		var t = new Date(this.options.start_date);
		var end = new Date(this.options.end_date);
		do
		{
			value = value.concat(this._cache_register(t, this.options.owner, last_data));

			t.setUTCDate(t.getUTCDate() + 1);
		}
		while(t < end);

		this.doInvalidate = true;
		return value;
	}

	/**
	 * Deal with registering for data cache
	 *
	 * @param Date t
	 * @param String owner Calendar owner
	 */
	_cache_register(t, owner, last_data)
	{
		// Cache is by date (and owner, if seperate)
		var date = t.getUTCFullYear() + sprintf('%02d',t.getUTCMonth()+1) + sprintf('%02d',t.getUTCDate());
		var cache_id = CalendarApp._daywise_cache_id(date, owner);
		var value = [];

		if(egw.dataHasUID(cache_id))
		{
			var c = egw.dataGetUIDdata(cache_id);
			if(c.data && c.data !== null)
			{
				// There is data, pass it along now
				for(var j = 0; j < c.data.length; j++)
				{
					if(last_data.indexOf(c.data[j]) === -1 && egw.dataHasUID('calendar::'+c.data[j]))
					{
						value.push(egw.dataGetUIDdata('calendar::'+c.data[j]).data);
					}
				}
				last_data = c.data;
			}
		}
		else
		{
			// Assume it's empty, if there is data it will be filled later
			egw.dataStoreUID(cache_id, []);
		}
		this.registeredCallbacks.push(cache_id);

		egw.dataRegisterUID(cache_id, function(data) {

			if(data && data.length)
			{
				var invalidate = true;

				// Try to determine rows interested
				var labels = [];
				var events = {};
				if(this.grouper)
				{
					labels = this.grouper.row_labels.call(this);
					invalidate = false;
				}

				var im = this.getInstanceManager();
				for(var i = 0; i < data.length; i++)
				{
					var event = egw.dataGetUIDdata('calendar::'+data[i]);

					if(!event) continue;
					events = {};

					// Try to determine rows interested
					if(event.data && this.grouper)
					{
						this.grouper.group.call(this, labels, events, event.data);
					}
					if(Object.keys(events).length > 0 )
					{
						for(var label_id in events)
						{
							var id = ""+labels[label_id].id;
							if(typeof this.cache[id] === 'undefined')
							{
								this.cache[id] = [];
							}
							if(this.cache[id].indexOf(event.data.row_id) === -1)
							{
								this.cache[id].push(event.data.row_id);
							}
							if (this._deferred_row_updates[id])
							{
								window.clearTimeout(this._deferred_row_updates[id]);
							}
							this._deferred_row_updates[id] = window.setTimeout(jQuery.proxy(this._deferred_row_update,this,id),this.DEFERRED_ROW_TIME);
						}
					}
					else
					{
						// Could be an event no row is interested in, could be a problem.
						// Just redraw everything
						invalidate = true;
						continue;
					}

					// If displaying by category, we need the infolog (or other app) categories too
					if(event && event.data && event.data.app && this.options.group_by == 'category')
					{
						// Fake it to use the cache / call
						et2_selectbox.cat_options({
							_type:'select-cat',
							getInstanceManager: function() {return im;}
						}, {application:event.data.app||'calendar'});
					}
				}

				if(invalidate)
				{
					this.invalidate(false);
				}
			}
		}, this, this.getInstanceManager().execId,this.id);

		return value;
	}

	/**
	 * Because users may be participants in various events and the time it takes
	 * to create many events, we don't want to update a row too soon - we may have
	 * to re-draw it if we find the user / category in another event.  Pagination
	 * makes this worse.  We wait a bit before updating the row to avoid
	 * having to re-draw it multiple times.
	 *
	 * @param {type} id
	 * @returns {undefined}
	 */
	_deferred_row_update( id)
	{
		// Something's in progress, skip
		if(!this.doInvalidate) return;

		this.grid.height(0);

		var id_list = typeof id === 'undefined' ? Object.keys(this.cache) : [id];
		for(var i = 0; i < id_list.length; i++)
		{
			var cache_id = id_list[i];
			var row = <et2_calendar_planner_row>this.getWidgetById('planner_row_'+cache_id);

			window.clearTimeout(this._deferred_row_updates[cache_id]);
			delete this._deferred_row_updates[cache_id];

			if(row)
			{
				row._data_callback(this.cache[cache_id]);
				row.set_disabled(this.options.hide_empty && this.cache[cache_id].length === 0);
			}
			else
			{
				break;
			}
		}

		// Updating the row may push things longer, update length
		// Add 1 to keep the scrollbar, otherwise we need to recalculate the
		// header widths too.
		this.grid.height(this.rows[0].scrollHeight+1);

		// Adjust header if there's a scrollbar - Firefox needs this re-calculated,
		// otherwise the header will be missing the margin space for the scrollbar
		// in some cases
		if(this.rows.children().last().length)
		{
			this.gridHeader.css('margin-right', (this.rows.width() - this.rows.children().first().width()) + 'px');
		}
	}

	/**
	 * Provide specific data to be displayed.
	 * This is a way to set start and end dates, owner and event data in once call.
	 *
	 * @param {Object[]} events Array of events, indexed by date in Ymd format:
	 *	{
	 *		20150501: [...],
	 *		20150502: [...]
	 *	}
	 *	Days should be in order.
	 *
	 */
	set_value(events)
	{
		if(typeof events !== 'object') return false;

		super.set_value(events);

		// Planner uses an array, not map
		var val = this.value;
		var array = [];
		Object.keys(this.value).forEach(function (key) {
			array.push(val[key]);
		});
		this.value = array;
	}

	/**
	 * Change the start date
	 * Planner view uses a date object internally
	 *
	 * @param {string|number|Date} new_date New starting date
	 * @returns {undefined}
	 */
	set_start_date(new_date)
	{
		super.set_start_date(new_date);
		this.options.start_date = new Date(this.options.start_date);
	}

	/**
	 * Change the end date
	 * Planner view uses a date object internally
	 *
	 * @param {string|number|Date} new_date New end date
	 * @returns {undefined}
	 */
	set_end_date(new_date)
	{
		super.set_end_date(new_date);
		this.options.end_date = new Date(this.options.end_date);
	}

	/**
	 * Change how the planner is grouped
	 *
	 * @param {string|number} group_by 'user', 'month', or an integer category ID
	 * @returns {undefined}
	 */
	set_group_by(group_by)
	{
		if(isNaN(group_by) && typeof this.groupers[group_by] === 'undefined')
		{
			throw new Error('Invalid group_by "'+group_by+'"');
		}
		var old = this.options.group_by;
		this.options.group_by = ''+group_by;

		this.grouper = this.groupers[isNaN(this.options.group_by) ? this.options.group_by : 'category'];

		if(old !== this.options.group_by && this.isAttached())
		{
			this.invalidate(true);
		}
	}

	/**
	 * Set which users to display
	 *
	 * Changing the owner will invalidate the display, and it will be redrawn
	 * after a timeout.  Overwriting here to check for groups without members.
	 *
	 * @param {number|number[]|string|string[]} _owner - Owner ID, which can
	 *	be an account ID, a resource ID (as defined in calendar_bo, not
	 *	necessarily an entry from the resource app), or a list containing a
	 *	combination of both.
	 *
	 * @memberOf et2_calendar_view
	 */
	set_owner(_owner)
	{
		super.set_owner(_owner);

		// If we're grouping by user, we need group members
		if(this.update_timer !== null && this.options.group_by == 'user')
		{
			let options = [];
			let resource = {};
			let missing_resources = [];

			if(app.calendar && app.calendar.sidebox_et2 && app.calendar.sidebox_et2.getWidgetById('owner'))
			{
				options = app.calendar.sidebox_et2.getWidgetById('owner').select_options;
			}
			else
			{
				options = this.getArrayMgr("sel_options").getRoot().getEntry('owner');
			}
			for(var i = 0; i < this.options.owner.length; i++)
			{
				var user = this.options.owner[i];
				if(isNaN(user) || user >= 0 || !options) continue;

				// Owner is a group, see if we have its members
				if(options.find &&
					((resource = options.find(function (element)
                      {
						  return element.value == user;
                      }))))
				{
					// Members found
					continue;
				}
				// Group, but no users found.  Need those.
				missing_resources.push(user);

				// Maybe api already has them?
				egw.accountData(parseInt(user),'account_fullname',true,function(result) {
					missing_resources.splice(missing_resources.indexOf(this),1);
				}.bind(user),user);
			}
			if(missing_resources.length > 0)
			{
				// Ask server, and WAIT or we have to redraw
				egw.json('calendar_owner_etemplate_widget::ajax_owner',[missing_resources],function(data) {
					for(let owner in data)
					{
						if(!owner || typeof owner == "undefined") continue;
						options.push(data[owner]);
					}
				}, this,false,this).sendRequest(false);
			}
		}
	}

	/**
	 * Turn on or off the visibility of weekends
	 *
	 * @param {boolean} weekends
	 */
	set_show_weekend(weekends)
	{
		weekends = weekends ? true : false;
		if(this.options.show_weekend !== weekends)
		{
			this.options.show_weekend = weekends;
			if(this.isAttached())
			{
				this.invalidate();
			}
		}
	}

	/**
	 * Turn on or off the visibility of hidden (empty) rows
	 *
	 * @param {boolean} hidden
	 */
	set_hide_empty(hidden)
	{
		this.options.hide_empty = hidden;
	}

	/**
	 * Call change handler, if set
	 *
	 * @param {type} event
	 */
	change( event)
	{
		if (this.onchange)
		{
			if(typeof this.onchange == 'function')
			{
				// Make sure function gets a reference to the widget
				var args = Array.prototype.slice.call(arguments);
				if(args.indexOf(this) == -1) args.push(this);

				return this.onchange.apply(this, args);
			} else {
				return (et2_compileLegacyJS(this.options.onchange, this, _node))();
			}
		}
	}

	/**
	 * Call event change handler, if set
	 *
	 * @param {type} event
	 * @param {type} dom_node
	 */
	event_change( event, dom_node)
	{
		if (this.onevent_change)
		{
			var event_data = this._get_event_info(dom_node);
			var event_widget = this.getWidgetById(event_data.widget_id);
			et2_calendar_event.recur_prompt(event_data, jQuery.proxy(function(button_id, event_data) {
				// No need to continue
				if(button_id === 'cancel') return false;

				if(typeof this.onevent_change == 'function')
				{
					// Make sure function gets a reference to the widget
					var args = Array.prototype.slice.call(arguments);

					if(args.indexOf(event_widget) == -1) args.push(event_widget);

					// Put button ID in event
					event.button_id = button_id;

					return this.onevent_change.apply(this, [event, event_widget, button_id]);
				} else {
					return (et2_compileLegacyJS(this.options.onevent_change, event_widget, dom_node))();
				}
			},this));
		}
		return false;
	}

	/**
	 * Click handler calling custom handler set via onclick attribute to this.onclick
	 *
	 * This also handles all its own actions, including navigation.  If there is
	 * an event associated with the click, it will be found and passed to the
	 * onclick function.
	 *
	 * @param {Event} _ev
	 * @returns {boolean} Continue processing event (true) or stop (false)
	 */
	click(_ev)
	{
		var result = true;

		// Drag to create in progress
		if(this.drag_create.start !== null) return;

		// Is this click in the event stuff, or in the header?
		if(!this.options.readonly && this.gridHeader.has(_ev.target).length === 0 && !jQuery(_ev.target).hasClass('calendar_plannerRowHeader'))
		{
			// Event came from inside, maybe a calendar event
			var event = this._get_event_info(_ev.originalEvent.target);
			if(typeof this.onclick == 'function')
			{
				// Make sure function gets a reference to the widget, splice it in as 2. argument if not
				var args = Array.prototype.slice.call(arguments);
				if(args.indexOf(this) == -1) args.splice(1, 0, this);

				result = this.onclick.apply(this, args);
			}

			if(event.id && result && !this.options.disabled && !this.options.readonly)
			{
				et2_calendar_event.recur_prompt(event);

				return false;
			}
			else if (!event.id)
			{
				// Clicked in row, but not on an event
				// Default handler to open a new event at the selected time
				if(jQuery(event.target).closest('.calendar_eventRows').length == 0)
				{
					// "Invalid" times, from space after the last planner row, or header
					var date = this._get_time_from_position(_ev.pageX - this.grid.offset().left, _ev.pageY - this.grid.offset().top);
				}
				else if(this.options.group_by == 'month')
				{
					var date = this._get_time_from_position(_ev.clientX, _ev.clientY);
				}
				else
				{
					var date = this._get_time_from_position(_ev.offsetX, _ev.offsetY);
				}
				var row = jQuery(_ev.target).closest('.calendar_plannerRowWidget');
				var data = row.length ? row[0].dataset : {};
				if(date)
				{
					app.calendar.add(jQuery.extend({
                           start: date.toJSON(),
                           hour: date.getUTCHours(),
                           minute: date.getUTCMinutes()
                       }, data));
					return false;
				}
			}
			return result;
		}
		else if (this.gridHeader.has(_ev.target).length > 0 && !jQuery.isEmptyObject(_ev.target.dataset) ||
			jQuery(_ev.target).hasClass('calendar_plannerRowHeader') && !jQuery.isEmptyObject(_ev.target.dataset))
		{
			// Click on a header, we can go there
			_ev.data = jQuery.extend({},_ev.target.parentNode.dataset, _ev.target.dataset);
			for(var key in _ev.data)
			{
				if(!_ev.data[key])
				{
					delete _ev.data[key];
				}
			}
			app.calendar.update_state(_ev.data);
		}
		else if (!this.options.readonly)
		{
			// Default handler to open a new event at the selected time
			// TODO: Determine date / time more accurately from position
			app.calendar.add({
				date: _ev.target.dataset.date || this.options.start_date.toJSON(),
				hour: _ev.target.dataset.hour || this.options.day_start,
				minute: _ev.target.dataset.minute || 0
			});
			return false;
		}
	}

	/**
	 * Get time from position
	 *
	 * @param {number} x
	 * @param {number} y
	 * @returns {Date|Boolean} A time for the given position, or false if one
	 *	could not be determined.
	 */
	_get_time_from_position( x,y)
	{
		if(!this.options.start_date || !this.options.end_date)
		{
			return false;
		}

		x = Math.round(x);
		y = Math.round(y);

		// Round to user's preferred event interval
		var interval = egw.preference('interval', 'calendar') || 30;

		// Relative horizontal position, as a percentage
		var width = 0;
		jQuery('.calendar_eventRows', this.div).each(function() {width = Math.max(width, jQuery(this).width());});
		var rel_x = Math.min(x / width, 1);

		// Relative time, in minutes from start
		var rel_time = 0;

		var day_header = jQuery('.calendar_plannerScaleDay', this.headers);
		let date;

		// Simple math, the x is offset from start date
		if(this.options.group_by !== 'month' && (
			// Either all days are visible, or only 1 day (no day header)
			this.options.show_weekend || day_header.length === 0
		))
		{
			rel_time = (new Date(this.options.end_date) - new Date(this.options.start_date)) * rel_x / 1000;
			date = this.date_helper(this.options.start_date.toJSON());
		}
		// Not so simple math, need to account for missing days
		else if(this.options.group_by !== 'month' && !this.options.show_weekend)
		{
			// Find which day
			if(day_header.length === 0) return false;
			var day = document.elementFromPoint(
				day_header.offset().left + rel_x * this.headers.innerWidth(),
				day_header.offset().top
			);

			// Use day, and find time in that day
			if(day && day.dataset && day.dataset.date)
			{
				date = this.date_helper(day.dataset.date);
				rel_time = ((x - jQuery(day).position().left) / jQuery(day).outerWidth(true)) * 24 * 60;
				date.setUTCMinutes(Math.round(rel_time / interval) * interval);
				return date;
			}
			return false;
		}
		else
		{
			// Find the correct row so we know which month, then get the offset
			var hidden_nodes = [];
			var row = null;
			// Hide any drag or tooltips that may interfere
			do
			{
				row = document.elementFromPoint(x, y);
				if(this.div.has(row).length == 0)
				{
					hidden_nodes.push({element: row, display: row.style.display});
					row.style.display = "none";
				}
				else
				{
					break;
				}
			} while(row && row.nodeName !== 'BODY');
			if(!row) return false;

			// Restore hidden nodes
			for(var i = 0; i < hidden_nodes.length; i++)
			{
				hidden_nodes[i].element.style.display = hidden_nodes[i].display;
			}
			row = jQuery(row).closest('.calendar_plannerRowWidget');


			var row_widget = null;
			for(var i = 0; i < this._children.length && row.length > 0; i++)
			{
				if(this._children[i].div[0] == row[0])
				{
					row_widget = this._children[i];
					break;
				}
			}
			if(row_widget)
			{
				// Not sure where the extra -1 and +2 are coming from, but it makes it work out
				// in FF & Chrome
				rel_x = Math.min((x - row_widget.rows.offset().left - 1) / (row_widget.rows.width() + 2), 1);

				// 2678400 is the number of seconds in 31 days
				rel_time = (2678400) * rel_x;
				date = this.date_helper(row_widget.options.start_date.toJSON());
			}
			else
			{
				return false;
			}
		}
		if(rel_time < 0) return false;

		date.setUTCMinutes(Math.round(rel_time / (60 * interval)) * interval);

		return date;
	}

	/**
	 * Mousedown handler to support drag to create
	 *
	 * @param {jQuery.Event} event
	 */
	_mouse_down(event)
	{
		// Only left mouse button
		if(event.which !== 1) return;

		// Ignore headers
		if(this.headers.has(event.target).length !== 0) return false;

		// Get time at mouse
		if(this.options.group_by === 'month')
		{
			var time = this._get_time_from_position(event.clientX, event.clientY);
		}
		else
		{
			var time = this._get_time_from_position(event.offsetX, event.offsetY);
		}
		if(!time) return false;

		// Find the correct row so we know the parent
		var row = event.target.closest('.calendar_plannerRowWidget');
		for(var i = 0; i < this._children.length && row; i++)
		{
			if(this._children[i].div[0] === row)
			{
				this.drag_create.parent = this._children[i];
				// Clear cached events for re-layout
				this._children[i]._cached_rows = [];
				break;
			}
		}
		if(!this.drag_create.parent) return false;

		this.div.css('cursor', 'ew-resize');

		return this._drag_create_start(jQuery.extend({},this.drag_create.parent.node.dataset,{date: time.toJSON()}));
	}

	/**
	 * Mouseup handler to support drag to create
	 *
	 * @param {jQuery.Event} event
	 */
	_mouse_up(event)
	{
		// Get time at mouse
		if(this.options.group_by === 'month')
		{
			var time = this._get_time_from_position(event.clientX, event.clientY);
		}
		else
		{
			var time = this._get_time_from_position(event.offsetX, event.offsetY);
		}

		return this._drag_create_end(time ? {date: time.toJSON()} : false);
	}

	/**
	 * Code for implementing et2_IDetachedDOM
	 *
	 * @param {array} _attrs array to add further attributes to
	 */
	getDetachedAttributes( _attrs)
	{
		_attrs.push('start_date','end_date');
	}

	getDetachedNodes()
	{
		return [this.getDOMNode()];
	}

	setDetachedAttributes( _nodes, _values)
	{
		this.div = jQuery(_nodes[0]);

		if(_values.start_date)
		{
			this.set_start_date(_values.start_date);
		}
		if(_values.end_date)
		{
			this.set_end_date(_values.end_date);
		}
	}

	// Resizable interface
	resize()
	{
		// Take the whole tab height
		var height = Math.min(jQuery(this.getInstanceManager().DOMContainer).height(),jQuery(this.getInstanceManager().DOMContainer).parent().innerHeight());

		// Allow for toolbar
		height -= jQuery('#calendar-toolbar',this.div.parents('.egw_fw_ui_tab_content')).outerHeight(true);

		this.options.height = height;
		this.div.css('height', this.options.height);
		// Set height for rows
		this.rows.height(this.div.height() - this.headers.outerHeight());

		this.grid.height(this.rows[0].scrollHeight);
	}

	/**
	 * Set up for printing
	 *
	 * @return {undefined|Deferred} Return a jQuery Deferred object if not done setting up
	 *  (waiting for data)
	 */
	beforePrint( )
	{

		if(this.disabled || !this.div.is(':visible'))
		{
			return;
		}
		this.rows.css('overflow-y', 'visible');

		var rows = jQuery('.calendar_eventRows');
		var width = rows.width();
		var events = jQuery('.calendar_calEvent', rows)
				.each(function() {
					var event = jQuery(this);
					event.width((event.width() / width) * 100 + '%')
				});

	}

	/**
	 * Reset after printing
	 */
	afterPrint( )
	{
		this.rows.css('overflow-y', 'auto');
	}
}
et2_register_widget(et2_calendar_planner, ["calendar-planner"]);