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
*/

import {et2_createWidget, et2_register_widget, WidgetConfig} from "../../api/js/etemplate/et2_core_widget";
import {ClassWithAttributes} from "../../api/js/etemplate/et2_core_inheritance";
import {et2_calendar_view} from "./et2_widget_view";
import {et2_action_object_impl} from "../../api/js/etemplate/et2_core_DOMWidget";
import {et2_dataview_grid} from "../../api/js/etemplate/et2_dataview_view_grid";
import {et2_calendar_daycol} from "./et2_widget_daycol";
import {egw} from "../../api/js/jsapi/egw_global";
import {et2_no_init} from "../../api/js/etemplate/et2_core_common";
import {et2_IDetachedDOM, et2_IPrint, et2_IResizeable} from "../../api/js/etemplate/et2_core_interfaces";
import {et2_calendar_event} from "./et2_widget_event";
import {egw_getObjectManager, egwActionObject} from "../../api/js/egw_action/egw_action.js";
import {et2_compileLegacyJS} from "../../api/js/etemplate/et2_core_legacyJSFunctions";
import {Et2Dialog} from "../../api/js/etemplate/Et2Dialog/Et2Dialog";
import {EGW_AI_DRAG_ENTER, EGW_AI_DRAG_OUT} from "../../api/js/egw_action/egw_action_constants.js";
import {formatDate, formatTime, parseTime} from "../../api/js/etemplate/Et2Date/Et2Date";
import interact from "@interactjs/interactjs/index";
import type {InteractEvent} from "@interactjs/core/InteractEvent";

/**
 * Class which implements the "calendar-timegrid" XET-Tag for displaying a span of days
 *
 * This widget is responsible for the times on the side, and it is also the
 * controller for both positioning and setting the day columns.  Day columns are
 * recycled rather than removed and re-created to reduce reloading.  Similarly,
 * the horizontal time grid (when used - see granularity attribute) is only
 * redrawn or resized when needed.  Unfortunately resizing is needed every time
 * the all day section has an event added or removed so the full work day from
 * start time to end time is properly displayed.
 *
 *
 * @augments et2_calendar_view
 */
export class et2_calendar_timegrid extends et2_calendar_view implements et2_IDetachedDOM, et2_IResizeable,et2_IPrint
{
	static readonly _attributes : any = {
		value: {
			type: "any",
			description: "An array of events, indexed by date (Ymd format)."
		},
		day_start: {
			name: "Day start time",
			type: "string",
			default: parseInt(''+egw.preference('workdaystarts','calendar')) || 9,
			description: "Work day start time.  If unset, this will default to the current user's preference"
		},
		day_end: {
			name: "Day end time",
			type: "string",
			default: parseInt(''+egw.preference('workdayends','calendar')) || 17,
			description: "Work day end time.  If unset, this will default to the current user's preference"
		},
		show_weekend: {
			name: "Weekends",
			type: "boolean",
			// @ts-ignore
			default: egw.preference('days_in_weekview','calendar') != 5,
			description: "Display weekends.  The date range should still include them for proper scrolling, but they just won't be shown."
		},
		granularity: {
			name: "Granularity",
			type: "integer",
			default: parseInt(''+egw.preference('interval','calendar')) || 30,
			description: "How many minutes per row, or 0 to display events as a list"
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
		},
		height: {
			"default": '100%'
		}
	};
	private gridHeader: JQuery;
	private dayHeader: JQuery;
	private scrolling: JQuery;
	private days: JQuery;
	private owner: any;
	private gridHover: JQuery;

	private day_list: any[];
	private day_widgets: any[];
	private resize_timer: number;
	private _top_time: number;
	private rowHeight: number;
	private daily_owner: boolean = false;
	private _drop_data: any;
	private day_start: any;
	private day_end: any;
	
	/**
	 * Constructor
	 *
	 * @memberOf et2_calendar_timegrid
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_calendar_timegrid._attributes, _child || {}));


		// Main container
		this.div = jQuery(document.createElement("div"))
			.addClass("calendar_calTimeGrid")
			.addClass("calendar_TimeGridNoLabel");

		// Headers
		this.gridHeader = jQuery(document.createElement("div"))
			.addClass("calendar_calGridHeader")
			.appendTo(this.div);
		this.dayHeader = jQuery(document.createElement("div"))
			.appendTo(this.gridHeader);

		// Contains times / rows
		this.scrolling = jQuery(document.createElement('div'))
			.addClass("calendar_calTimeGridScroll")
			.appendTo(this.div)
			.append('<div class="calendar_calTimeLabels"></div>');

		// Contains days / columns
		this.days = jQuery(document.createElement("div"))
			.addClass("calendar_calDayCols")
			.appendTo(this.scrolling);

		// Used for owners
		this.owner = et2_createWidget('description',{},this);

		this._labelContainer = jQuery(document.createElement("label"))
			.addClass("et2_label et2_link")
			.appendTo(this.gridHeader);

		this.gridHover = jQuery('<div style="height:5px;" class="calendar_calAddEvent drop-hover">');

		// List of dates in Ymd
		// The first one should be start_date, last should be end_date
		this.day_list = [];
		this.day_widgets = [];

		// Timer to re-scale time to fit
		this.resize_timer = null;

		this.setDOMNode(this.div[0]);
	}

	destroy( )
	{
		// Stop listening to tab changes
		if(typeof framework !== 'undefined' && framework.getApplicationByName('calendar').tab)
		{
			jQuery(framework.getApplicationByName('calendar').tab.contentDiv).off('show.' + this.id);
		}

		super.destroy();

		// Delete all old objects
		this._actionObject.clear();
		this._actionObject.unregisterActions();
		this._actionObject.remove();
		this._actionObject = null;

		this.div.off();
		this.div = null;
		this.gridHeader = null;
		this.dayHeader = null;
		this.days = null;
		this.scrolling = null;
		this._labelContainer = null;

		// Stop the resize timer
		if(this.resize_timer)
		{
			window.clearTimeout(this.resize_timer);
		}
	}

	doLoadingFinished( )
	{
		super.doLoadingFinished();

		// Listen to tab show to make sure we scroll to the day start, not top
		if(typeof framework !== 'undefined' && framework.getApplicationByName('calendar').tab)
		{
			jQuery(framework.getApplicationByName('calendar').tab.contentDiv)
				.on('show.' + this.id, jQuery.proxy(
					function()
					{
						if(this.scrolling)
						{
							this.scrolling.scrollTop(this._top_time);
						}
					},this)
				);
		}

		// Need to get the correct internal sizing
		this.resize();

		this._drawGrid();

		// Actions may be set on a parent, so we need to explicitly get in here
		// and get ours
		this._link_actions(this.options.actions || this.getParent().options.actions || []);

		// Automatically bind drag and resize for every event using jQuery directly
		// - no action system -
		var timegrid = this;

		/**
		 * If user puts the mouse over an event, then we'll set up resizing so
		 * they can adjust the length.  Should be a little better on resources
		 * than binding it for every calendar event, and we won't need exceptions
		 * for planner view to resize horizontally.
		 */
		this.div.on('mouseover', '.calendar_calEvent:not(.ui-resizable):not(.rowNoEdit)', function()
		{
			// Only resize in timegrid
			if(timegrid.options.granularity === 0)
			{
				return;
			}

			// Load the event
			timegrid._get_event_info(this);
			if(this.classList.contains("resizing"))
			{
				// Currently already resizing
				return;
			}

			//Resizable event handler
			interact(this).resizable
			({
				distance: 10,
				invert: "reposition",
				edges: {bottom: true},
				startAxis: "y",
				lockAxis: "y",
				containment: 'parent',
				modifiers: [
					interact.modifiers.snapSize({
						targets: [interact.createSnapGrid({width: 10, height: timegrid.rowHeight})]
					})
				],

				/**
				 *  Triggered when the resizable is created.
				 *
				 * @param {event} event
				 * @param {Object} ui
				 */
				create: function(event, ui)
				{
					var resizeHelper = event.target.getAttribute('data-resize');
					if(resizeHelper == 'WD' || resizeHelper == 'WDS')
					{
						jQuery(this).resizable('destroy');
					}
				},

				/**
				 * If dragging to resize an event, abort drag to create
				 *
				 * @param {InteractEvent} event
				 */
				onstart: function(event)
				{
					if(timegrid.drag_create.start)
					{
						// Abort drag to create, we're dragging to resize
						timegrid._drag_create_end({});
					}
					event.target.classList.add("resizing");
				},

				/**
				 * Triggered at the end of resizing the calEvent.
				 *
				 * @param {InteractEvent} event
				 */
				onend: function(event)
				{
					// Remove for re-creation...
					interact(this).unset();
					event.target.classList.remove("resizing");

					var e = new jQuery.Event('change');
					e.originalEvent = event;
					e.data = {duration: 0};
					var event_data = timegrid._get_event_info(this);
					var event_widget = <et2_calendar_event>timegrid.getWidgetById(event_data.widget_id);
					var sT = event_widget.options.value.start_m;
					if(typeof this.dropEnd != 'undefined' && this.dropEnd.length == 1)
					{
						var eT = (parseInt(timegrid._drop_data.hour) * 60) + parseInt(timegrid._drop_data.minute);
						e.data.duration = ((eT - sT) / 60) * 3600;

						if(event_widget)
						{
							event_widget.options.value.end_m = eT;
							event_widget.options.value.duration = e.data.duration;
						}
						jQuery(this).trigger(e);
						event_widget._update(event_widget.options.value);

					}
					// Clear the helper, re-draw
					if(event_widget && event_widget._parent)
					{
						event_widget._parent.position_event(event_widget);
					}
					timegrid.div.children('.drop-hover').removeClass('.drop-hover');
				}.bind(this),

				/**
				 * Triggered during the resize, on the drag of the resize handler
				 *
				 * @param {InteractEvent} event
				 */
				onmove: function(event)
				{
					event.target.style.height = event.rect.height + "px";
					// Add a bit for better understanding - it will show _to_ the start,
					// covering the 'actual' target
					timegrid._get_time_from_position(event.target.getBoundingClientRect().left, event.target.getBoundingClientRect().bottom + 5);
					timegrid.gridHover.hide();
					var drop = timegrid._drag_helper(this, event.target);
					if(drop && !drop.is(':visible'))
					{
						drop.get(0).scrollIntoView(false);
					}
				}.bind(this)
			});
		});

		// Customize and override some draggable settings
		this.div
			.on('dragstart', '.calendar_calEvent', function(event)
			{
				// Cancel drag to create, we're dragging an existing event
				timegrid.drag_create.start = null;
				timegrid._drag_create_end();
				timegrid.div.on("dragover.timegrid", (e) =>
				{
					timegrid._get_time_from_position(e.clientX, e.clientY);
				})
			})
			.on("dragend", () =>
			{
				timegrid.div.off("drag.timegrid");
			})
			.on('mousemove', function(event)
			{
				timegrid._get_time_from_position(event.clientX, event.clientY);
			})
			.on('mouseout', function(event)
			{
				if(timegrid.div.has(event.relatedTarget).length === 0)
				{
					timegrid.gridHover.hide();
				}
			})
			.on('mousedown', ':not(.calendar_calEvent)', this._mouse_down.bind(this))
			.on('mouseup', this._mouse_up.bind(this));

		return true;
	}

	_createNamespace() {
		return true;
	}

	/**
	 * Show the current time while dragging
	 * Used for resizing as well as drag & drop
	 *
	 * @param {type} element
	 * @param {type} helper
	 * @param {type} height
	 */
	_drag_helper(element, helper,height)
	{
		if(!element) return;

		element.dropEnd = this.gridHover;

		if(element.dropEnd.length)
		{
			this._drop_data = jQuery.extend({},element.dropEnd[0].dataset || {});
		}

		if (typeof element.dropEnd != 'undefined' && element.dropEnd.length)
		{
			// Make sure the target is visible in the scrollable day
			if(this.gridHover.is(':visible'))
			{
				if(this.scrolling.scrollTop() > 0 && this.scrolling.scrollTop() >= this.gridHover.position().top - this.rowHeight)
				{
					this.scrolling.scrollTop(this.gridHover.position().top-this.rowHeight);
				}
				else if (this.scrolling.scrollTop() + this.scrolling.height() <= this.gridHover.position().top + (2*this.rowHeight))
				{
					this.scrolling.scrollTop(this.scrolling.scrollTop() + this.rowHeight);
				}
			}
			var time = '';
			if(this._drop_data.whole_day)
			{
				time = this.egw().lang('Whole day');
			}
			else if (this.options.granularity === 0)
			{
				// No times, keep what's in the event
				// Add class to helper to keep formatting
				jQuery(helper).addClass('calendar_calTimeGridList');
			}
			else
			{
				// @ts-ignore
				time = formatTime(parseTime(element.dropEnd.attr('data-hour') + ":" + element.dropEnd.attr('data-minute')));
			}
			element.innerHTML = '<div style="font-size: 1.1em; text-align:center; font-weight: bold; height:100%;"><span class="calendar_timeDemo" >'+time+'</span></div>';
		}
		else
		{
			element.innerHTML = '<div class="calendar_d-n-d_forbiden" style="height:100%"></div>';
		}
		jQuery(element).width(jQuery(helper).width());
		return element.dropEnd;
	}

	/**
	 * Handler for dropping an event on the timegrid
	 *
	 * @param {type} timegrid
	 * @param {type} event
	 * @param {type} ui
	 * @param {type} dropEnd
	 */
	_event_drop( timegrid, event,ui, dropEnd)
	{
		var e = new jQuery.Event('change');
		e.originalEvent = event;
		e.data = {start: 0};

		if(typeof dropEnd != 'undefined' && dropEnd)
		{
			var drop_date = dropEnd.date || false;
			let target_date;

			var event_data = timegrid._get_event_info(ui.draggable);
			var event_widget = timegrid.getWidgetById(event_data.widget_id);
			if(!event_widget)
			{
				// Widget was moved across weeks / owners
				event_widget = timegrid.getParent().getWidgetById(event_data.widget_id);
			}
			if(event_widget)
			{
				// Send full string to avoid rollover between months using set_month()
				target_date = event_widget._parent.date_helper(
					drop_date.substring(0, 4) + '-' + drop_date.substring(4, 6) + '-' + drop_date.substring(6, 8) +
					'T00:00:00Z'
				);


				// Make sure whole day events stay as whole day events by ignoring drop time
				if(event_data.app == 'calendar' && event_widget.options.value.whole_day)
				{
					target_date.setUTCHours(0);
					target_date.setUTCMinutes(0);
				}
				else if (timegrid.options.granularity === 0)
				{
					// List, not time grid - keep time
					target_date.setUTCHours(event_widget.options.value.start.getUTCHours());
					target_date.setUTCMinutes(event_widget.options.value.start.getUTCMinutes());
				}
				else
				{
					// Non-whole day events, and integrated apps, can change
					target_date.setUTCHours(dropEnd.whole_day ? 0 : dropEnd.hour || 0);
					target_date.setUTCMinutes(dropEnd.whole_day ? 0 : dropEnd.minute || 0);
				}

				// Leave the helper there until the update is done
				var loading = event_data.event_node;
				// and add a loading icon so user knows something is happening
				jQuery('.calendar_calEventHeader', event_widget.div).addClass('loading');

				event_widget.recur_prompt(function(button_id)
				{
					if(button_id === 'cancel' || !button_id)
					{
						// Need to refresh the event with original info to clean up
						var app_id = event_widget.options.value.app_id ? event_widget.options.value.app_id : event_widget.options.value.id + (event_widget.options.value.recur_type ? ':' + event_widget.options.value.recur_date : '');
						egw().dataStoreUID('calendar::' + app_id, egw.dataGetUIDdata('calendar::' + app_id).data);
						loading.remove();
						return;
					}
					let duration : string | number | boolean;
					//Get infologID if in case if it's an integrated infolog event
					if (event_data.app === 'infolog')
					{
						// Duration - infologs are always non-blocking
						duration = dropEnd.whole_day ? 86400-1 : (
							event_widget.options.value.whole_day ? (egw().preference('defaultlength','calendar')*60) : false);

						// If it is an integrated infolog event we need to edit infolog entry
						egw().json('stylite_infolog_calendar_integration::ajax_moveInfologEvent',
							[event_data.app_id, target_date || false, duration],
							function()
							{
								loading.remove();
							}
						).sendRequest(true);
					}
					else
					{
						//Edit calendar event

						// Duration - check for whole day dropped on a time, change it to full days
						duration = event_widget.options.value.whole_day && dropEnd.hour ?
							// Make duration whole days, less 1 second
							(Math.round((event_widget.options.value.end - event_widget.options.value.start) / (1000 * 86400)) * 86400) -1 :
							false;
						// Event (whole day or not) dropped on whole day section, change to whole day non blocking
						if(dropEnd.whole_day) duration = 'whole_day';

						// Send the update
						var _send = function(series_instance)
						{
							var start = new Date(target_date);

							egw().json('calendar.calendar_uiforms.ajax_moveEvent', [
									button_id === 'series' ? event_data.id : event_data.app_id, event_data.owner,
									start,
									timegrid.options.owner || egw.user('account_id'),
									duration,
									series_instance
								],
								function()
								{
									loading.remove();
								}
							).sendRequest(true);
						};

						// Check for modifying a series that started before today
						if (event_widget.options.value.recur_type && button_id === 'series')
						{
							event_widget.series_split_prompt(function(_button_id) {
								if(_button_id === Et2Dialog.OK_BUTTON)
								{
									_send(event_widget.options.value.recur_date);
								}
								else
								{
									loading.remove();
								}
							});
						}
						else
						{
							_send(event_widget.options.value.recur_date);
						}
					}
				});
			}
		}
	}

	/**
	 * Something changed, and the days need to be re-drawn.  We wait a bit to
	 * avoid re-drawing twice if start and end date both changed, then recreate
	 * the days.
	 * The whole grid is not regenerated because times aren't expected to change,
	 * just the days.
	 *
	 * @param {boolean} [trigger=false] Trigger an event once things are done.
	 *	Waiting until invalidate completes prevents 2 updates when changing the date range.
	 * @returns {undefined}
	 */
	invalidate( trigger?)
	{

		// Reset the list of days
		this.day_list = [];

		// Wait a bit to see if anything else changes, then re-draw the days
		if(this.update_timer)
		{
			window.clearTimeout(this.update_timer);
		}
		this.update_timer = window.setTimeout(jQuery.proxy(function() {
			this.widget.update_timer = null;
			window.clearTimeout(this.resize_timer);
			this.widget.loader.hide().show();

			// Update actions
			if(this.widget._actionManager)
			{
				this.widget._link_actions(this.widget._actionManager.children);
			}

			this.widget._drawDays();
			// We have to completely re-do times, as they may have changed in
			// scale to the point where more labels are needed / need to be removed
			this.widget._drawTimes();
			if(this.trigger)
			{
				this.widget.change();
			}
			this.widget._updateNow();

			// Hide loader
			window.setTimeout(jQuery.proxy(function() {this.loader.hide();},this.widget),200);
		},{widget:this,"trigger":trigger}),et2_dataview_grid.ET2_GRID_INVALIDATE_TIMEOUT);
	}

	detachFromDOM( )
	{
		// Remove the binding to the change handler
		jQuery(this.div).off(".et2_calendar_timegrid");

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

		// Catch resize and prevent it from bubbling further, triggering
		// etemplate's resize
		this.div.on('resize', this, function(e) {
			e.stopPropagation();
		});

		return result;
	}

	getDOMNode( _sender)
	{
		if(_sender === this || !_sender)
		{
			return this.div ? this.div[0] : null;
		}
		else if (_sender.instanceOf(et2_calendar_daycol))
		{
			return this.days ? this.days[0] : null;
		}
		else if (_sender)
		{
			return this.gridHeader ? this.gridHeader[0] : null;
		}
	}

	set_disabled( disabled)
	{
		var old_value = this.options.disabled;
		super.set_disabled(disabled);
		if(disabled)
		{
			this.loader.show();
		}
		else if (old_value !== disabled)
		{
			// Scroll to start of day - stops jumping in FF
			// For some reason on Chrome & FF this doesn't quite get the day start
			// to the top, so add 2px;
			this.scrolling.scrollTop(this._top_time+2);
		}

	}

	/**
	 * Update the 'now' line
	 * @private
	 */
	// @ts-ignore
	public _updateNow()
	{
		let now = super._updateNow();
		if(now === false || this.options.granularity == 0 || !this.div.is(':visible'))
		{
			this.now_div.hide();
			return false;
		}

		// Position & show line
		let set_line = function(line, now, day)
		{
			line.appendTo(day.getDOMNode()).show();
			let pos = day._time_to_position(now.getUTCHours() * 60 + now.getUTCMinutes());
			//this.now_div.position({my: 'left', at: 'left', of: day.getDOMNode()});
			line.css('top', pos + '%');
		}

		// Showing just 1 day, multiple owners - span all
		if(this.daily_owner && this.day_list.length == 1)
		{
			let day = this.day_widgets[0];
			set_line(this.now_div, now, day);
			this.now_div.css('width', (this.day_widgets.length * 100) + '%');
			return true;
		}

		// Find the day of the week
		for(var i = 0; i < this.day_widgets.length; i++)
		{
			let day = this.day_widgets[i];
			if(day.getDate() >= now)
			{
				day = this.day_widgets[i-1];
				set_line(this.now_div, now, day);
				this.now_div.css('width','100%');
				break;
			}
		}
		return true;
	}

	/**
	 * Clear everything, and redraw the whole grid
	 */
	_drawGrid( )
	{

		this.div.css('height', this.options.height)
			.empty();
		this.loader.prependTo(this.div).show();

		// Draw in the horizontal - the times
		this._drawTimes();

		// Draw in the vertical - the days
		this.invalidate();
	}

	/**
	 * Creates the DOM nodes for the times in the left column, and the horizontal
	 * lines (mostly via CSS) that span the whole date range.
	 */
	_drawTimes( )
	{
		jQuery('.calendar_calTimeRow',this.div).remove();

		this.div.toggleClass('calendar_calTimeGridList', this.options.granularity === 0);

		this.gridHeader
			.attr('data-date', this.options.start_date)
			.attr('data-owner', this.options.owner)
			.append(this._labelContainer)
			.append(this.owner.getDOMNode())
			.append(this.dayHeader)
			.appendTo(this.div);

		// Max with 18 avoids problems when it's not shown
		var header_height = Math.max(this.gridHeader.outerHeight(true), 18);

		this.scrolling
			.appendTo(this.div)
			.off();

		// No time grid - list
		if(this.options.granularity === 0)
		{
			this.scrolling.css('height','100%');
			this.days.css('height', '100%');
			this.iterateOver(function(day) {
				day.resize();
			},this,et2_calendar_daycol);
			return;
		}

		var wd_start = 60*this.options.day_start;
		var wd_end = 60*this.options.day_end;
		var granularity = this.options.granularity;
		var totalDisplayMinutes	= wd_end - wd_start;
		var rowsToDisplay	= Math.ceil((totalDisplayMinutes+60)/granularity);
		var row_count = (1440 / this.options.granularity);


		this.scrolling
			.on('scroll', jQuery.proxy(this._scroll, this));

		// Percent
		var rowHeight = (100/rowsToDisplay).toFixed(1);
		// Pixels
		this.rowHeight = this.scrolling.height() / rowsToDisplay;

		// We need a reasonable bottom limit here, but resize will handle it
		// if we get too small
		if(this.rowHeight < 5 && this.div.is(':visible'))
		{
			if(this.rowHeight === 0)
			{
				// Something is not right...
				this.rowHeight = 5;
			}
		}

		// the hour rows
		var show = {
			5  : [0,15,30,45],
			10 : [0,30],
			15 : [0,30],
			45 : [0,15,30,45]
		};
		var html = '';
		var line_height = parseInt(this.div.css('line-height'));
		this._top_time = 0;
		for(var t = 0,i = 0; t < 1440; t += granularity,++i)
		{
			if(t <= wd_start && t + granularity > wd_start)
			{
				this._top_time = this.rowHeight * (i+1+(wd_start - (t+granularity))/granularity);
			}
			var working_hours = (t >= wd_start && t < wd_end) ? ' calendar_calWorkHours' : '';
			html += '<div class="calendar_calTimeRow' + working_hours + '" style="height: '+(100/row_count)+'%;">';
			// show time for full hours, always for 45min interval and at least on every 3 row
			// @ts-ignore
			let time = formatTime(parseTime((t / 60) + ":" + (t % 60)));

			var time_label = (typeof show[granularity] === 'undefined' ? t % 60 === 0 : show[granularity].indexOf(t % 60) !== -1) ? time : '';
			if(time_label && egw.preference("timeformat") == "12" && time_label.split(':')[0] < 10)
			{
				time_label ='&nbsp;&nbsp;' + time_label;
			}
			html += '<div class="calendar_calTimeRowTime et2_clickable" data-time="'+time.trim()+'" data-hour="'+Math.floor(t/60)+'" data-minute="'+(t%60)+'">'+time_label+"</div></div>\n";
		}

		// Set heights in pixels for scrolling
		jQuery('.calendar_calTimeLabels',this.scrolling)
			.empty()
			.height(this.rowHeight*i)
			.append(html);
		this.days.css('height', (this.rowHeight*i)+'px');
		this.gridHover.css('height', this.rowHeight);

		// Scroll to start of day
		this.scrolling.scrollTop(this._top_time);
	}

	/**
	 * As window size and number of all day non-blocking events change, we need
	 * to re-scale the time grid to make sure the full working day is shown.
	 *
	 * We use a timeout to avoid doing it multiple times if redrawing or resizing.
	 */
	resizeTimes( )
	{

		// Hide resizing from user
		this.loader.show();

		// Wait a bit to see if anything else changes, then re-draw the times
		if(this.resize_timer)
		{
			window.clearTimeout(this.resize_timer);
		}
		// No point if it is just going to be redone completely
		if(this.update_timer) return;

		this.resize_timer = window.setTimeout(jQuery.proxy(function() {
			if(this._resizeTimes)
			{
				this.resize_timer = null;

				this._resizeTimes();
			}
		},this),1);
	}

	/**
	 * Re-scale the time grid to make sure the full working day is shown.
	 * This is the timeout callback that does the actual re-size immediately.
	 */
	_resizeTimes( )
	{

		if(!this.div.is(':visible'))
		{
			return;
		}
		var wd_start = 60*this.options.day_start;
		var wd_end = 60*this.options.day_end;
		var totalDisplayMinutes	= wd_end - wd_start;
		var rowsToDisplay	= Math.ceil((totalDisplayMinutes+60)/this.options.granularity);
		var row_count = (1440 / this.options.granularity);

		var new_height = this.scrolling.height() / rowsToDisplay;
		var old_height = this.rowHeight;
		this.rowHeight = new_height;

		jQuery('.calendar_calTimeLabels', this.scrolling).height(this.rowHeight*row_count);
		this.days.css('height', this.options.granularity === 0 ?
			'100%' :
			(this.rowHeight*row_count)+'px'
		);

		// Scroll to start of day
		this._top_time = (wd_start * this.rowHeight) / this.options.granularity;
		// For some reason on Chrome & FF this doesn't quite get the day start
		// to the top, so add 2px;
		this.scrolling.scrollTop(this._top_time+2);

		if(this.rowHeight != old_height)
		{
			this.iterateOver(function(child) {
				if (child !== this && typeof child.resize === 'function')
				{
					child.resize();
				}
			},this, et2_IResizeable);
		}

		this.loader.hide();
	}

	/**
	 * Set up the needed day widgets to correctly display the selected date
	 * range.  First we calculate the needed dates, then we create any needed
	 * widgets.  Existing widgets are recycled rather than discarded.
	 */
	_drawDays( )
	{
		this.scrolling.append(this.days);

		// If day list is still empty, recalculate it from start & end date
		if(this.day_list.length === 0 && this.options.start_date && this.options.end_date)
		{
			this.day_list = this._calculate_day_list(this.options.start_date, this.options.end_date, this.options.show_weekend);
		}
		// For a single day, we show each owner in their own daycol
		this.daily_owner = this.day_list.length === 1 &&
			this.options.owner.length > 1 &&
			this.options.owner.length < (parseInt(''+egw.preference('day_consolidate','calendar')) || 6);
		var daycols_needed = this.daily_owner ? this.options.owner.length : this.day_list.length;
		var day_width = ( Math.min( jQuery(this.getInstanceManager().DOMContainer).width(),this.days.width())/daycols_needed);
		if(!day_width || !this.day_list)
		{
			// Hidden on another tab, or no days for some reason
			var dim = egw.getHiddenDimensions(this.days, false);
			day_width = ( dim.w /Math.max(daycols_needed,1));
		}

		// Create any needed widgets - otherwise, we'll just recycle
		// Add any needed day widgets (now showing more days)
		var add_index = 0;
		var before = true;

		while(daycols_needed > this.day_widgets.length)
		{
			var existing_index = this.day_widgets[add_index] && !this.daily_owner ?
				this.day_list.indexOf(this.day_widgets[add_index].options.date) :
				-1;
			before = existing_index > add_index;

			var day = et2_createWidget('calendar-daycol',{
				owner: this.options.owner,
				width: (before ? 0 : day_width) + "px"
			},this);
			if(this.isInTree())
			{
				day.doLoadingFinished();
			}
			if(existing_index != -1 && parseInt(this.day_list[add_index]) < parseInt(this.day_list[existing_index]))
			{
				this.day_widgets.unshift(day);
				jQuery(this.getDOMNode(day)).prepend(day.getDOMNode(day));
			}
			else
			{
				this.day_widgets.push(day);
			}
			add_index++;
		}
		// Remove any extra day widgets (now showing less)
		var delete_index = this.day_widgets.length - 1;
		before = false;
		while(this.day_widgets.length > daycols_needed)
		{
			// If we're going down to an existing one, just keep it for cool CSS animation
			while(delete_index > 1 && this.day_list.indexOf(this.day_widgets[delete_index].options.date) > -1)
			{
				delete_index--;
				before = true;
			}
			if(delete_index < 0) delete_index = 0;

			// Widgets that are before our date shrink, after just get pushed out
			if(before)
			{
				this.day_widgets[delete_index].set_width('0px');
			}
			this.day_widgets[delete_index].div.hide();
			this.day_widgets[delete_index].header.hide();
			this.day_widgets[delete_index].destroy();
			this.day_widgets.splice(delete_index--,1);
		}

		this.set_header_classes();

		// Create / update day widgets with dates and data
		for(var i = 0; i < this.day_widgets.length; i++)
		{
			day = this.day_widgets[i];

			// Position
			day.set_left((day_width * i) + 'px');
			day.title.removeClass('blue_title');
			if(this.daily_owner)
			{
				// Each 'day' is the same date, different user
				day.set_id(this.day_list[0]+'-'+this.options.owner[i]);
				day.set_date(this.day_list[0], false);
				day.set_owner(this.options.owner[i]);
				day.set_label(this._get_owner_name(this.options.owner[i]));
				day.title.addClass('blue_title');
			}
			else
			{
				// Show user name in day header even if only one
				if(this.day_list.length === 1)
				{
					day.set_label(this._get_owner_name(this.options.owner));
					day.title.addClass('blue_title');
				}
				else
				{
					// Go back to self-calculated date by clearing the label
					day.set_label('');
				}
				day.set_id(this.day_list[i]);
				day.set_date(this.day_list[i], this.value[this.day_list[i]] || false);
				day.set_owner(this.options.owner);
			}
			day.set_width(day_width + 'px');
		}

		// Adjust and scroll to start of day
		this.resizeTimes();

		// Don't hold on to value any longer, use the data cache for best info
		this.value = {};

		if(this.daily_owner)
		{
			this.set_label('');
		}

		// Handle not fully visible elements
		this._scroll();

		// Set 'now' line
		this._updateNow();

		// TODO: Figure out how to do this with detached nodes
		/*
		var nodes = this.day_col.getDetachedNodes();
		var supportedAttrs = [];
		this.day_col.getDetachedAttributes(supportedAttrs);
		supportedAttrs.push("id");

		for(var i = 0; i < day_count; i++)
		{
			this.day_col.setDetachedAttributes(nodes.clone(),)
		}
		*/
	}

	/**
	 * Set header classes
	 *
	 */
	set_header_classes()
	{
		var day;
		let app_calendar = this.getInstanceManager().app_obj.calendar || app.calendar;
		for(var i = 0; i < this.day_widgets.length; i++)
		{
			day = this.day_widgets[i];

			// Classes
			if(app_calendar && app_calendar.state &&
				this.day_list[i] && parseInt(this.day_list[i].substr(4,2)) !== new Date(app_calendar.state.date).getUTCMonth()+1)
			{
				day.set_class('calendar_differentMonth');
			}
			else
			{
				day.set_class('');
			}
		}
	}

	/**
	 * Update UI while scrolling within the selected time
	 *
	 * Toggles out of view indicators and adjusts not visible headers
	 * @param {Event} event Scroll event
	 */
	private _scroll(event?)
	{
		if(!this.day_widgets) return;

		// Loop through days, let them deal with it
		for(var day = 0; day < this.day_widgets.length; day++)
		{
			this.day_widgets[day]._out_of_view();
		}
	}

	/**
	 * Calculate a list of days between start and end date, skipping weekends if
	 * desired.
	 *
	 * @param {Date|string} start_date Date that et2_date widget can understand
	 * @param {Date|string} end_date Date that et2_date widget can understand
	 * @param {boolean} show_weekend If not showing weekend, Saturday and Sunday
	 *	will not be in the returned list.
	 *
	 * @returns {string[]} List of days in Ymd format
	 */
	_calculate_day_list( start_date, end_date, show_weekend)
	{

		let day_list = [];
		if(!start_date || !end_date)
		{
			return day_list;
		}

		let end = this.date_helper(end_date);
		let i = 1;
		let start = this.date_helper(start_date);

		do
		{
			if(show_weekend || !show_weekend && [0, 6].indexOf(start.getUTCDay()) === -1 || end_date === start_date)
			{
				day_list.push(formatDate(start, {dateFormat: "Ymd"}));
			}
			start.setUTCDate(start.getUTCDate() + 1);
		}
			// Limit it to 14 days to avoid infinite loops in case something is mis-set,
			// though the limit is more based on how wide the screen is
		while(end >= start && i++ <= 14);

		return day_list;
	}

	/**
	 * Link the actions to the DOM nodes / widget bits.
	 *
	 * @param {object} actions {ID: {attributes..}+} map of egw action information
	 */
	_link_actions(actions)
	{
		// Get the parent?  Might be a grid row, might not.  Either way, it is
		// just a container with no valid actions
		var objectManager = egw_getObjectManager(this.getInstanceManager().app,true,1);
		objectManager = objectManager.getObjectById(this.getInstanceManager().uniqueId,2) || objectManager;
		var parent = objectManager.getObjectById(this.id,1) || objectManager.getObjectById(this.getParent().id,1) || objectManager;
		if(!parent)
		{
			debugger;
			egw.debug('error','No parent objectManager found');
			return;
		}

		// This binds into the egw action system.  Most user interactions (drag to move, resize)
		// are handled internally using jQuery directly.
		var widget_object = this._actionObject || parent.getObjectById(this.id);
		var aoi = new et2_action_object_impl(this,this.getDOMNode(this)).getAOI();

		for(var i = 0; i < parent.children.length; i++)
		{
			var parent_finder = jQuery(parent.children[i].iface.doGetDOMNode()).find(this.div);
			if(parent_finder.length > 0)
			{
				parent = parent.children[i];
				break;
			}
		}
		// Determine if we allow a dropped event to use the invite/change actions
		let _invite_enabled = function (action, event, target)
		{
			var event = event.iface.getWidget();
			var timegrid = target.iface.getWidget() || false;
			if(event === timegrid || !event || !timegrid ||
				!event.options || !event.options.value.participants || !timegrid.options.owner
			)
			{
				return false;
			}
			var owner_match = false;
			var own_timegrid = event.getParent().getParent() === timegrid && !timegrid.daily_owner;

			for (var id in event.options.value.participants)
			{
				if(!timegrid.daily_owner)
				{
					if(timegrid.options.owner === id ||
						timegrid.options.owner.indexOf &&
						timegrid.options.owner.indexOf(id) >= 0)
					{
						owner_match = true;
					}
				}
				else
				{
					timegrid.iterateOver(function (col)
					                     {
						                     // Check scroll section or header section
						                     if(col.div.has(timegrid.gridHover).length || col.header.has(timegrid.gridHover).length)
						                     {
							                     owner_match = owner_match || col.options.owner.indexOf(id) !== -1;
							                     own_timegrid = (col === event.getParent());
						                     }
					                     }, this, et2_calendar_daycol);
				}
			}
			var enabled = !owner_match &&
				// Not inside its own timegrid
				!own_timegrid;

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
				var dropEnd = false;
				var helper = jQuery('.calendar_d-n-d_timeCounter', _data.ui.helper)[0];
				if(helper && helper.dropEnd && helper.dropEnd.length >= 1)
				{
					dropEnd = helper.dropEnd[0].dataset || this.dropEnd
				}
				this.getWidget()._event_drop.call(jQuery('.calendar_d-n-d_timeCounter', _data.ui.helper)[0], this.getWidget(), event, _data.ui, dropEnd);
			}
			var drag_listener = function(_event)
			{
				aoi.getWidget()._drag_helper(jQuery('.calendar_d-n-d_timeCounter', _data.ui.helper)[0], _data.ui.helper[0], 0);
				if(aoi.getWidget().daily_owner)
				{
					_invite_enabled(
						widget_object.getActionLink('invite').actionObj,
						event,
						widget_object
					);
				}
			};
			var time = jQuery('.calendar_d-n-d_timeCounter', _data.ui.helper);
			switch(_event)
			{
				// Triggered once, when something is dragged into the timegrid's div
				case EGW_AI_DRAG_ENTER:
					// Remove formatting for out-of-view events (full day non-blocking)
					jQuery('.calendar_calEventHeader', _data.ui.helper).css('top', '');
					jQuery('.calendar_calEventBody', _data.ui.helper).css('padding-top', '');

					// Disable invite / change actions for same calendar or already participant
					var event = _data.ui.selected[0];
					if(!event || event.id && event.id.indexOf('calendar') !== 0)
					{
						event = false;
					}
					if(event)
					{
						_invite_enabled(
							widget_object.getActionLink('invite').actionObj,
							event,
							widget_object
						);
					}

					if(time.length)
					{
						// The out will trigger after the over, so we count
						time.data('count',time.data('count')+1);
					}
					else
					{
						jQuery(_data.ui.helper).prepend('<div class="calendar_d-n-d_timeCounter" data-count="1"><span></span></div>');
					}

					break;

				// Triggered once, when something is dragged out of the timegrid
				case EGW_AI_DRAG_OUT:
					// Stop listening
					jQuery(_data.ui.draggable).off('drag.et2_timegrid' + widget_object.id);
					// Remove highlighted time square
					var timegrid = aoi.getWidget();
					timegrid.gridHover.hide();
					timegrid.scrolling.scrollTop(timegrid._top_time);

					// Out triggers after the over, count to not accidentally remove
					time.data('count', time.data('count') - 1);
					if(time.length && time.data('count') <= 0)
					{
						time.remove();
					}
					break;
				default:
					// It never came in?
					if(!time.length)
					{
						jQuery(_data.ui.helper).prepend('<div class="calendar_d-n-d_timeCounter" data-count="1"><span></span></div>');
					}
					drag_listener(event);
			}
		};

		if (widget_object == null) {
			// Add a new container to the object manager which will hold the widget
			// objects
			widget_object = parent.insertObject(false, new egwActionObject(
				this.id, parent, aoi,
				this._actionManager|| parent.manager.getActionById(this.id) || parent.manager
			));
		}
		else
		{
			widget_object.setAOI(aoi);
		}
		this._actionObject = widget_object;

		// Delete all old objects
		widget_object.clear();
		widget_object.unregisterActions();

		// Go over the widget & add links - this is where we decide which actions are
		// 'allowed' for this widget at this time
		var action_links = this._get_action_links(actions);

		this._init_links_dnd(widget_object.manager, action_links);

		widget_object.updateActionLinks(action_links);
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

		var drop_link = mgr.getActionById('egw_link_drop');
		var drop_change_participant = mgr.getActionById('change_participant');
		var drop_invite = mgr.getActionById('invite');
		var drag_action = mgr.getActionById('egw_link_drag');

		// Check if this app supports linking
		if(!egw.link_get_registry(this.dataStorePrefix, 'query') ||
			egw.link_get_registry(this.dataStorePrefix, 'title'))
		{
			if(drop_link)
			{
				drop_link.remove();
				if(actionLinks.indexOf(drop_link.id) >= 0)
				{
					actionLinks.splice(actionLinks.indexOf(drop_link.id),1);
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
		if(drop_link == null)
		{
			// Create the drop action that links entries
			drop_link = mgr.addAction('drop', 'egw_link_drop', egw.lang('Create link'), egw.image('link'), function(action, source, target) {

				// Extract link IDs
				var links = [];
				var id = '';
				for(var i = 0; i < source.length; i++)
				{
					// Check for no ID (invalid) or same manager (dragging an event)
					if(!source[i].id) continue;
					if(source[i].manager === target.manager)
					{
						// Find the timegrid, could have dropped on an event
						var timegrid = target.iface.getWidget();
						while(target.parent && timegrid.instanceOf && !timegrid.instanceOf(et2_calendar_timegrid))
						{
							target = target.parent;
							timegrid = target.iface.getWidget();
						}


						if (timegrid && timegrid._drop_data)
						{
							timegrid._event_drop.call(source[i].iface.getDOMNode(),timegrid,null, action.ui,timegrid._drop_data);
						}
						timegrid._drop_data = false;
						// Ok, stop.
						return false;
					}

					id = source[i].id.split('::');
					links.push({app: id[0] == 'filemanager' ? 'link' : id[0], id: id[1]});
				}
				if(links.length && target && target.iface.getWidget() && target.iface.getWidget().instanceOf(et2_calendar_event))
				{
					// Link the entries
					egw.json(self.egw().getAppName()+".etemplate_widget_link.ajax_link.etemplate",
						target.id.split('::').concat([links]),
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
				else if (links.length)
				{
					// Get date and time
					var params = jQuery.extend({},jQuery('.drop-hover[data-date]',target.iface.getDOMNode())[0].dataset || {});

					// Add link IDs
					var app_registry = egw.link_get_registry('calendar');
					params[app_registry.add_app] = [];
					params[app_registry.add_id] = [];
					for(var n in links)
					{
						params[app_registry.add_app].push( links[n].app);
						params[app_registry.add_id].push( links[n].id);
					}
					app.calendar.add(params);
				}

			},true);

			drop_link.acceptedTypes = ['default','link'];
			drop_link.hideOnDisabled = true;

			// Create the drop action for moving events between calendars
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

						// Find the timegrid, could have dropped on an event
						var timegrid = target.iface.getWidget();
						while(target.parent && timegrid.instanceOf && !timegrid.instanceOf(et2_calendar_timegrid))
						{
							target = target.parent;
							timegrid = target.iface.getWidget();
						}

						// Leave the helper there until the update is done
						var loading = action.ui.draggable;

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
							var add_owner = jQuery.extend([],timegrid.options.owner);
							if(timegrid.daily_owner)
							{
								timegrid.iterateOver(function(col) {
									if(col.div.has(timegrid.gridHover).length || col.header.has(timegrid.gridHover).length)
									{
										add_owner = col.options.owner;
									}
								}, this, et2_calendar_daycol);
							}
							egw().json('calendar.calendar_uiforms.ajax_invite', [
									button_id==='series' ? event_data.id : event_data.app_id,
									add_owner,
									action.id === 'change_participant' ?
										jQuery.extend([],source[i].iface.getWidget().getParent().options.owner) :
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
		if(actionLinks.indexOf(drop_link.id) < 0)
		{
			actionLinks.push(drop_link.id);
		}

		actionLinks.push(drop_invite.id);
		actionLinks.push(drop_change_participant.id);

		// Don't re-add
		if(drag_action == null)
		{
			// Create drag action that allows linking
			drag_action = mgr.addAction('drag', 'egw_link_drag', egw.lang('link'), 'link', function(action, selected) {
				// Drag helper - list titles.
				// As we wanted to have a general defaul helper interface, we return null here and not using customize helper for links
				// TODO: Need to decide if we need to create a customized helper interface for links anyway
				//return helper;
				return null;
			},true);
		}
		// The timegrid itself is not draggable, so don't add a link.
		// The action is there for the children (events) to use
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
		// TODO: determine which actions are allowed without an action (empty actions)
		for(var i in actions)
		{
			var action = actions[i];
			if(action.type == 'drop')
			{
				action_links.push(typeof action.id != 'undefined' ? action.id : i);
			}
		}
		return action_links;
	}

	/**
	 * Provide specific data to be displayed.
	 * This is a way to set start and end dates, owner and event data in one call.
	 *
	 * Events will be retrieved automatically from the egw.data cache, so there
	 * is no great need to provide them.
	 *
	 * @param {Object[]} events Array of events, indexed by date in Ymd format:
	 *	{
	 *		20150501: [...],
	 *		20150502: [...]
	 *	}
	 *	Days should be in order.
	 *  {string|number|Date} events.start_date - New start date
	 *  {string|number|Date} events.end_date - New end date
	 *  {number|number[]|string|string[]} event.owner - Owner ID, which can
	 *	be an account ID, a resource ID (as defined in calendar_bo, not
	 *	necessarily an entry from the resource app), or a list containing a
	 *	combination of both.
	 */
	set_value(events)
	{
		if(typeof events !== 'object') return false;

		var use_days_sent = true;

		if(events.start_date)
		{
			use_days_sent = false;
		}
		if(events.end_date)
		{
			use_days_sent = false;
		}

		super.set_value(events);

		if(use_days_sent)
		{
			var day_list = Object.keys(events);
			if(day_list.length)
			{
				this.set_start_date(day_list[0]);
				this.set_end_date(day_list[day_list.length-1]);
			}


			// Sub widgets actually get their own data from egw.data, so we'll
			// stick it there
			var consolidated = et2_calendar_view.is_consolidated(this.options.owner, this.day_list.length == 1 ? 'day' : 'week');
			for(var day in events)
			{
				let day_list = [];
				for(var i = 0; i < events[day].length; i++)
				{
					day_list.push(events[day][i].row_id);
					egw.dataStoreUID('calendar::'+events[day][i].row_id, events[day][i]);
				}
				// Might be split by user, so we have to check that too
				for(var i = 0; i < this.options.owner.length; i++)
				{
					var owner = consolidated ? this.options.owner : this.options.owner[i];
					var day_id = CalendarApp._daywise_cache_id(day,owner);
					egw.dataStoreUID(day_id, day_list);
					if(consolidated) break;
				}
			}
		}

		// Reset and calculate instead of just use the keys so we can get the weekend preference
		this.day_list = [];

		// None of the above changed anything, hide the loader
		if(!this.update_timer)
		{
			window.setTimeout(jQuery.proxy(function() {this.loader.hide();},this),200);
		}
	}

	/**
	 * Set which user owns this.  Owner is passed along to the individual
	 * days.
	 *
	 * @param {number|number[]} _owner Account ID
	 * @returns {undefined}
	 */
	set_owner(_owner)
	{
		var old = this.options.owner || 0;
		super.set_owner(_owner);

		this.owner.set_label('');
		this.div.removeClass('calendar_TimeGridNoLabel');

		// Check to see if it's our own calendar, with just us showing
		if(typeof _owner == 'object' && _owner.length == 1)
		{
			var rowCount = 0;
			this.getParent().iterateOver(function(widget) {
				if(!widget.disabled) rowCount++;
			},this, et2_calendar_timegrid);
			// Just us, show week number
			if(rowCount == 1 && _owner.length == 1 && _owner[0] == egw.user('account_id') || rowCount != 1) _owner = false;
		}

		var day_count = this.day_list.length ? this.day_list.length :
				this._calculate_day_list(this.options.start_date, this.options.end_date, this.options.show_weekend).length;
		// @ts-ignore
		if(typeof _owner == 'string' && isNaN(_owner))
		{
			this.set_label('');
			this.owner.set_value(this._get_owner_name(_owner));

			// Label is empty, but give extra space for the owner name
			this.div.removeClass('calendar_TimeGridNoLabel');
		}
		else if (!_owner || typeof _owner == 'object' && _owner.length > 1 ||
			// Single owner, single day
			_owner.length === 1 && day_count === 1
		)
		{
			// Don't show owners if more than one, show week number
			this.owner.set_value('');
			if(this.options.start_date)
			{
				this.set_label(egw.lang('wk') + ' ' +
					(app.calendar ? app.calendar.date.week_number(this.options.start_date) : '')
				);
			}
		}
		else
		{
			this.owner.options.application = 'api-accounts';
			this.owner.set_value(this._get_owner_name(_owner));
			this.set_label('');
			jQuery(this.getDOMNode(this.owner)).prepend(this.owner.getDOMNode());
		}

		if(this.isAttached() && (
			typeof old === "number" && typeof _owner === "number" && old !== this.options.owner ||
			// Array of ids will not compare as equal
			((typeof old === 'object' || typeof _owner === 'object') && old.toString() !== _owner.toString()) ||
			// Strings
			typeof old === 'string' && ''+old !== ''+this.options.owner
		))
		{
			this.invalidate(true);
		}
	}

	/**
	 * Set a label for this week
	 *
	 * May conflict with owner, which is displayed when there's only one owner.
	 *
	 * @param {string} label
	 */
	set_label(label)
	{
		this.options.label = label;
		this._labelContainer.html(label);
		this.gridHeader.prepend(this._labelContainer);

		// If it's a short label (eg week number), don't give it an extra line
		// but is empty, but give extra space for a single owner name
		this.div.toggleClass(
			'calendar_TimeGridNoLabel',
			label.trim().length > 0 && label.trim().length <= 6 ||
			this.options.owner.length > 1
		);
	}

	/**
	 * Set how big the time divisions are
	 *
	 * Setting granularity to 0 will remove the time divisions and display
	 * each days events in a list style.  This 'gridlist' is not to be confused
	 * with the list view, which uses a nextmatch.
	 *
	 * @param {number} minutes
	 */
	set_granularity(minutes)
	{
		// Avoid  < 0
		minutes = Math.max(0,minutes);

		if(this.options.granularity !== minutes)
		{
			if(this.options.granularity === 0 || minutes === 0)
			{
				this.options.granularity = minutes;
				// Need to re-do a bunch to make sure this is propagated
				this.invalidate();
			}
			else
			{
				this.options.granularity = minutes;
				this._drawTimes();
			}
		}
		else if (!this.update_timer)
		{
			this.resizeTimes();
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
	 * Call change handler, if set
	 */
	change( )
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

	get_granularity()
	{
		// get option, or user's preference
		if(typeof this.options.granularity === 'undefined')
		{
			this.options.granularity = egw.preference('interval','calendar') || 30;
		}
		return parseInt(this.options.granularity);
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
		if(this.options.readonly ) return;

		// Drag to create in progress
		if(this.drag_create.start !== null) return;

		// Is this click in the event stuff, or in the header?
		if(_ev.target.dataset.id || jQuery(_ev.target).parents('.calendar_calEvent').length)
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

			var event_node = jQuery(event.event_node);
			if(event.id && result && !this.disabled && !this.options.readonly &&
				// Permissions - opening will fail if we try
				event_node && !(event_node.hasClass('rowNoView'))
			)
			{
				if(event.widget_id && this.getWidgetById(event.widget_id))
				{
					this.getWidgetById(event.widget_id).recur_prompt();
				}
				else
				{
					et2_calendar_event.recur_prompt(event);
				}

				return false;
			}
			return result;
		}
		else if (this.gridHeader.is(_ev.target) && _ev.target.dataset ||
			this._labelContainer.is(_ev.target) && this.gridHeader[0].dataset)
		{
			app.calendar.update_state(jQuery.extend(
				{view: 'week'},
				this._labelContainer.is(_ev.target) ?
					this.gridHeader[0].dataset :
					_ev.target.dataset
			));
		}
		else if (this.options.owner.length === 1 && jQuery(this.owner.getDOMNode()).is(_ev.target))
		{
			// Click on the owner in header, show just that owner
			app.calendar.update_state({owner: this.options.owner});
		}
		else if (this.dayHeader.has(_ev.target).length)
		{
			// Click on a day header - let day deal with it
			// First child is a selectAccount
			for(var i = 1; i < this._children.length; i++)
			{
				if(this._children[i].header && (
					this._children[i].header.has(_ev.target).length || this._children[i].header.is(_ev.target))
				)
				{
					return this._children[i].click(_ev);
				}
			}
		}
		// No time grid, click on a day
		else if (this.options.granularity === 0 &&
			(jQuery(_ev.target).hasClass('event_wrapper') || jQuery(_ev.target).hasClass('.calendar_calDayCol'))
		)
		{
			// Default handler to open a new event at the selected time
			var target = jQuery(_ev.target).hasClass('event_wrapper') ? _ev.target.parentNode : _ev.target;
			var options = {
				date: target.dataset.date || this.options.date,
				hour: target.dataset.hour || this._parent.options.day_start,
				minute: target.dataset.minute || 0,
				owner: this.options.owner
			};
			app.calendar.add(options);
			return false;
		}
	}

	/**
	 * Mousedown handler to support drag to create
	 *
	 * @param {jQuery.Event} event
	 */
	_mouse_down(event)
	{
		if(event.which !== 1)
		{
			return;
		}

		if(this.options.readonly)
		{
			return;
		}

		// Skip for events
		if(event.target.parentElement.classList.contains("calendar_calEvent"))
		{
			return;
		}

		let start = {...this.gridHover[0].dataset};
		if(start.date)
		{
			// Set parent for event
			if(this.daily_owner)
			{
				// Each 'day' is the same date, different user
				// Find the correct row so we know the parent
				var col = event.target.closest('.calendar_calDayCol');
				for(var i = 0; i < this._children.length && col; i++)
				{
					if(this._children[i].node === col)
					{
						this.drag_create.parent = this._children[i];
						break;
					}
				}
			}
			else
			{
				this.drag_create.parent = this.getWidgetById(start.date);
			}

			// Format date
			let date = this.date_helper(start.date);
			if(start.hour)
			{
				date.setUTCHours(start.hour);
			}
			if(start.minute)
			{
				date.setUTCMinutes(start.minute);
			}
			start.date = date;

			this.gridHover.css('cursor', 'ns-resize');

			// Start update
			var timegrid = this;
			this.div.on('mousemove.dragcreate', function()
			{
				if(timegrid.drag_create.event && timegrid.drag_create.parent && timegrid.drag_create.end)
				{
					var end = jQuery.extend({}, timegrid.gridHover[0].dataset);
					if(end.date)
					{
						let date = timegrid.date_helper(end.date);
						if(end.hour)
						{
							date.setUTCHours(end.hour);
						}
						if(end.minute)
						{
							date.setUTCMinutes(end.minute);
						}
						timegrid.drag_create.end.date = date;
					}
					try
					{
						timegrid._drag_update_event();
					}
					catch (e)
					{
						timegrid._drag_create_end();
					}
				}
			});
		}
		return this._drag_create_start(start);
	}

	/**
	 * Mouseup handler to support drag to create
	 *
	 * @param {jQuery.Event} event
	 */
	_mouse_up(event)
	{
		if (this.options.readonly) return;
		let end = {...this.gridHover[0].dataset};
		if(end.date)
		{
			let date = this.date_helper(end.date);
			if(end.hour)
			{
				date.setUTCHours(end.hour);
			}
			if(end.minute)
			{
				date.setUTCMinutes(end.minute);
			}
			end.date = date;
		}
		this.div.off('mousemove.dragcreate');
		this.gridHover.css('cursor', '');

		return this._drag_create_end(this.drag_create.event ? {date: end.date} : undefined);
	}

	/**
	 * Get time from position for drag and drop
	 *
	 * This does not return an actual time on a clock, but finds the closest
	 * time node (.calendar_calAddEvent or day column) to the given position.
	 *
	 * @param {number} x
	 * @param {number} y
	 * @returns {DOMNode[]} time node(s) for the given position
	 */
	_get_time_from_position( x,y)
	{

		x = Math.round(x);
		y = Math.round(y);

		var path = [];
		var day = null;
		var time = null;

		let nodes = document.elementsFromPoint(x, y);

		for(var id in this.gridHover[0].dataset) {
			delete this.gridHover[0].dataset[id];
		}
		if(this.options.granularity == 0)
		{
			this.gridHover.css('height','');
		}
		for(let i = 0; i < nodes.length && nodes[i].tagName != 'FORM'; i++)
		{
			let node = nodes[i];
			let $node = jQuery(node);
			// Ignore high level & non-time (grid itself, header parent & week label)
			if([this.node, this.gridHeader[0], this._labelContainer[0]].indexOf(node) !== -1 ||
				// Day labels
				this.gridHeader.has(node).length && !$node.hasClass("calendar_calDayColAllDay") && !$node.hasClass('calendar_calDayColHeader'))
			{
				continue;
			}
			if(node.classList.contains('calendar_calDayColHeader'))
			{
				for(var id in node.dataset)
				{
					this.gridHover[0].dataset[id] = node.dataset[id];
				}
				this.gridHover.css({
					top: '',
					bottom: '0px',
					// Use 100% height if we're hiding the day labels to avoid
					// any remaining space from the hidden labels
					height: $node.height() > parseInt($node.css('line-height')) ?
							$node.css('padding-bottom') : '100%'
				});
				day = node;
				this.gridHover
					.attr('data-non_blocking', 'true');
				break;
			}
			if(node.classList.contains('calendar_calDayCol'))
			{
				day = node;
				this.gridHover
					.attr('data-date', day.dataset.date);
			}
			if(node.classList.contains('calendar_calTimeRowTime'))
			{
				time = node;
				this.gridHover
					.attr('data-hour', time.dataset.hour)
					.attr('data-minute', time.dataset.minute);
				break;
			}
		}

		if(!day)
		{
			return [];
		}
		this.gridHover
			.show()
			.css("position", "absolute")
			.appendTo(day);
		if(time)
		{
			this.gridHover
				.height(this.rowHeight)
				.css("top", time.offsetTop + "px");
		}
		this.gridHover.css('left','');
		return this.gridHover;
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

	getDetachedNodes( )
	{
		return [this.getDOMNode(this)];
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
	/**
	 * @param {boolean} [_too_small=null] Force the widget to act as if it was too small
	 */
	resize (_too_small?)
	{
		if(this.disabled || !this.div.is(':visible'))
		{
			return;
		}

		/*
		We expect the timegrid to be in a table with 0 or more other timegrids,
		1 per row.  We want each timegrid to be as large as possible, but space
		shared equally.  Height can't be set to a percentage on the rows, because
		that doesn't work.  However, if any timegrid is too small (1/2 hour < 1 line
		height), we change to showing only the working hours with no vertical
		scrollbar.  Each week gets as much space as it needs, and all scroll together.
		*/
		// How many rows?
		var rowCount = 0;
		this.getParent().iterateOver(function(widget) {
			if(!widget.disabled) rowCount++;
		},this, et2_calendar_timegrid);

		// Take the whole tab height, or home portlet
		if(this.getInstanceManager().app === 'home')
		{
			var height = jQuery(this.getParent().getDOMNode(this)).parentsUntil('.et2_portlet').last().parent().innerHeight();

			// Allow for portlet header
			height -= jQuery('.ui-widget-header',this.div.parents('.egw_fw_ui_tab_content')).outerHeight(true);
		}
		else
		{
			var height = jQuery(this.getInstanceManager().DOMContainer).parent().innerHeight();

			// Allow for toolbar
			height -= jQuery('#calendar-toolbar',this.div.parents('.egw_fw_ui_tab_content')).outerHeight(true);
		}

		this.options.height = Math.floor(height / rowCount);

		// Allow for borders & padding
		this.options.height -= 2*((this.div.outerWidth(true) - this.div.innerWidth()) + parseInt(this.div.parent().css('padding-top')));

		// Calculate how much space is needed, and
		// if too small be bigger
		var needed = ((this.day_end - this.day_start) /
			(this.options.granularity / 60) * parseInt(this.div.css('line-height'))) +
			this.gridHeader.outerHeight();
		var too_small = needed > this.options.height && this.options.granularity != 0;


		if(this.getInstanceManager().app === 'home')
		{
			var modify_node = jQuery(this.getParent().getDOMNode(this)).parentsUntil('.et2_portlet').last();
		}
		else
		{
			var modify_node = jQuery(this.getInstanceManager().DOMContainer);
		}
		modify_node
			.css({
				'overflow-y': too_small || _too_small ? 'auto' : 'hidden',
				'overflow-x': 'hidden',
				'height': too_small || _too_small ? height : '100%'
			});
		if(too_small || _too_small)
		{
			this.options.height = Math.max(this.options.height, needed);
			// Set all others to match
			if(!_too_small && rowCount > 1 && this.getParent())
			{
				window.setTimeout(jQuery.proxy(function() {
					if(!this._parent) return;
					this._parent.iterateOver(function(widget) {
						if(!widget.disabled) widget.resize(true);
					},this, et2_calendar_timegrid);
				},this),1);
				return;
			}
			this.div.addClass('calendar_calTimeGridFixed');
		}
		else
		{
			this.div.removeClass('calendar_calTimeGridFixed');
		}
		this.div.css('height', this.options.height);

		// Re-do time grid
		if(!this.update_timer)
		{
			this.resizeTimes();
		}

		// Try to resize width, though animations cause problems
		var total_width = modify_node.parent().innerWidth() - this.days.position().left;
		// Space for todos, if there
		total_width -= jQuery(this.getInstanceManager().DOMContainer).siblings().has(':visible').not('#calendar-toolbar').outerWidth();

		var day_width = (total_width > 0 ? total_width : modify_node.width())/this.day_widgets.length;
		// update day widgets
		for(var i = 0; i < this.day_widgets.length; i++)
		{
			var day = this.day_widgets[i];

			// Position
			day.set_left((day_width * i) + 'px');
			day.set_width(day_width + 'px');
		}
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

		var height_check = this.div.height();
		this.div.css('max-height','17cm');
		if(this.div.height() != height_check)
		{
			this.div.height('17cm');
			this._resizeTimes();
		}

		// update day widgets, if not on single day view
		//
		// TODO: Find out why don't we update single day view
		// Let the single day view participate in print calculation. 
		if(this.day_widgets.length > 0)
		{
			var day_width = (100 / this.day_widgets.length);
			for(var i = 0; i < this.day_widgets.length; i++)
			{
				var day = this.day_widgets[i];

				// Position
				day.set_left((i*day_width) + '%');
				day.set_width(day_width + '%');
				// For some reason the column's method does not set it correctly in Chrome
				day.header[0].style.width = day_width + '%';
			}
		}

		// Stop Firefox from scrolling the day to the top - this would break printing in Chrome
		if (navigator.userAgent.match(/(firefox|safari|iceweasel)/i) && !navigator.userAgent.match(/chrome/i))
		{
			var height = this.scrolling.scrollTop() + this.scrolling.height();
			this.scrolling
				// Disable scroll event, or it will recalculate out of view events
				.off('scroll')
				// Explicitly transform to the correct place
				.css({
					'transform': 'translateY(-'+this.scrolling.scrollTop()+'px)',
					'margin-bottom': '-'+this.scrolling.scrollTop()+'px',
					'height': height+'px'
			});
			this.div.css({'height':'','max-height':''});
		}
	}

	/**
	 * Reset after printing
	 */
	afterPrint( )
	{
		this.div.css('maxHeight','');
		this.scrolling.children().css({'transform':'', 'overflow':''});
		this.div.height(this.options.height);
		if (navigator.userAgent.match(/(firefox|safari|iceweasel)/i) && !navigator.userAgent.match(/chrome/i))
		{
			this._resizeTimes();
			this.scrolling
				// Re-enable out-of-view formatting on scroll
				.on('scroll', jQuery.proxy(this._scroll, this))
				// Remove translation
				.css({'transform':'', 'margin-bottom':''});
		}
	}
}
et2_register_widget(et2_calendar_timegrid, ["calendar-timegrid"]);