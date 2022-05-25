/*
 * Egroupware
 *
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package calendar
 * @subpackage etemplate
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

/*egw:uses
	/calendar/js/et2_widget_view.js;
	/calendar/js/et2_widget_daycol.js;
	/calendar/js/et2_widget_event.js;
*/


import {et2_createWidget, et2_register_widget, WidgetConfig} from "../../api/js/etemplate/et2_core_widget";
import {et2_valueWidget} from "../../api/js/etemplate/et2_core_valueWidget";
import {ClassWithAttributes} from "../../api/js/etemplate/et2_core_inheritance";
import {et2_action_object_impl} from "../../api/js/etemplate/et2_core_DOMWidget";
import {et2_calendar_planner} from "./et2_widget_planner";
import {egw_getObjectManager, egwActionObject} from "../../api/js/egw_action/egw_action.js";
import {EGW_AI_DRAG_ENTER, EGW_AI_DRAG_OUT} from "../../api/js/egw_action/egw_action_constants.js";
import {et2_IResizeable} from "../../api/js/etemplate/et2_core_interfaces";
import {egw} from "../../api/js/jsapi/egw_global";
import {et2_calendar_view} from "./et2_widget_view";

/**
 * Class for one row of a planner
 *
 * This widget is responsible for the label on the side
 *
 */
export class et2_calendar_planner_row extends et2_valueWidget implements et2_IResizeable
{
	static readonly _attributes: any = {
		start_date: {
			name: "Start date",
			type: "any"
		},
		end_date: {
			name: "End date",
			type: "any"
		},
		value: {
			type: "any"
		}
	};
	private div: JQuery;
	private title: JQuery;
	private rows: JQuery;
	private _cached_rows: any[];
	private _row_height = 20;
	private _actionObject: egwActionObject;

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_calendar_planner_row._attributes, _child || {}));
		
		// Main container
		this.div = jQuery(document.createElement("div"))
			.addClass("calendar_plannerRowWidget")
			.css('width',this.options.width);
		this.title = jQuery(document.createElement('div'))
			.addClass("calendar_plannerRowHeader")
			.appendTo(this.div);
		this.rows = jQuery(document.createElement('div'))
			.addClass("calendar_eventRows")
			.appendTo(this.div);

		this.setDOMNode(this.div[0]);

		this.set_start_date(this.options.start_date);
		this.set_end_date(this.options.end_date);

		this._cached_rows = [];
	}

	doLoadingFinished( )
	{
		super.doLoadingFinished();

		this.set_label(this.options.label);
		this._draw();

		// Actions are set on the parent, so we need to explicitly get in here
		// and get ours
		this._link_actions(this.getParent().options.actions || []);
		return true;
	}

	destroy( )
	{
		super.destroy();
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
	 * Link the actions to the DOM nodes / widget bits.
	 *
	 * @param {object} actions {ID: {attributes..}+} map of egw action information
	 */
	_link_actions(actions)
	{
		// Get the parent?  Might be a grid row, might not.  Either way, it is
		// just a container with no valid actions
		let objectManager = egw_getObjectManager(this.getInstanceManager().app, true, 1);
		objectManager = objectManager.getObjectById(this.getInstanceManager().uniqueId,2) || objectManager;
		let parent = objectManager.getObjectById(this.id, 1) || objectManager.getObjectById(this.getParent().id, 1) || objectManager;
		if(!parent)
		{
			egw.debug('error','No parent objectManager found');
			return;
		}

		// This binds into the egw action system.  Most user interactions (drag to move, resize)
		// are handled internally using jQuery directly.
		let widget_object = this._actionObject || parent.getObjectById(this.id);
		const aoi = new et2_action_object_impl(this, this.getDOMNode(this)).getAOI();
		const planner = this.getParent();

		for(let i = 0; i < parent.children.length; i++)
		{
			const parent_finder = jQuery(parent.children[i].iface.doGetDOMNode()).find(this.div);
			if(parent_finder.length > 0)
			{
				parent = parent.children[i];
				break;
			}
		}

		// Determine if we allow a dropped event to use the invite/change actions
		const _invite_enabled = function (action, event, target)
		{
			var event = event.iface.getWidget();
			const row = target.iface.getWidget() || false;
			if(event === row || !event || !row ||
				!event.options || !event.options.value.participants
			)
			{
				return false;
			}

			let owner_match = false;
			const own_row = event.getParent() === row;

			for (let id in event.options.value.participants)
			{
				owner_match = owner_match || row.node.dataset.participants === '' + id;
			}

			const enabled = !owner_match &&
				// Not inside its own timegrid
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
			if(event.type === 'drop' && widget_object.getActionLink('egw_link_drop').enabled)
			{
				this.getWidget().getParent()._event_drop.call(
					jQuery('.calendar_d-n-d_timeCounter', _data.ui.draggable)[0],
					this.getWidget().getParent(), event, _data.ui,
					this.getWidget()
				);
			}
			const drag_listener = function(_event)
			{
				let position = {};
				if(planner.options.group_by === 'month')
				{
					position = {left: _event.clientX, top: _event.clientY};
				}
				else
				{
					let style = getComputedStyle(_data.ui.helper);
					position = {
						top: parseInt(style.top),
						left: _event.clientX - jQuery(this).parent().offset().left
					}
				}
				aoi.getWidget().getParent()._drag_helper(jQuery('.calendar_d-n-d_timeCounter', _data.ui.draggable)[0], position, 0);

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
			};
			const time = jQuery('.calendar_d-n-d_timeCounter', _data.ui.draggable);
			switch(_event)
			{
				// Triggered once, when something is dragged into the timegrid's div
				case EGW_AI_DRAG_ENTER:
					// Listen to the drag and update the helper with the time
					// This part lets us drag between different timegrids
					jQuery(_data.ui.draggable).on('drag.et2_timegrid_row' + widget_object.id, drag_listener);
					jQuery(_data.ui.draggable).on('dragend.et2_timegrid_row' + widget_object.id, function()
					{
						jQuery(_data.ui.draggable).off('drag.et2_timegrid_row' + widget_object.id);
					});
					widget_object.iface.getWidget().div.addClass('drop-hover');

					// Disable invite / change actions for same calendar or already participant
					let event = _data.ui.selected[0];
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
						jQuery(_data.ui.draggable).prepend('<div class="calendar_d-n-d_timeCounter" data-count="1"><span></span></div>');
					}


					break;

				// Triggered once, when something is dragged out of the timegrid
				case EGW_AI_DRAG_OUT:
					// Stop listening
					jQuery(_data.ui.draggable).off('drag.et2_timegrid_row' + widget_object.id);
					// Remove highlight
					widget_object.iface.getWidget().div.removeClass('drop-hover');

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
		const action_links = this._get_action_links(actions);

		this.getParent()._init_links_dnd(widget_object.manager, action_links);

		widget_object.updateActionLinks(action_links);
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
		const action_links = [];

		// Only these actions are allowed without a selection (empty actions)
		const empty_actions = ['add'];

		for(let i in actions)
		{
			const action = actions[i];
			if(empty_actions.indexOf(action.id) !== -1 || action.type == 'drop')
			{
				action_links.push(typeof action.id != 'undefined' ? action.id : i);
			}
		}
		return action_links;
	}

	/**
	 * Draw the individual divs for weekends and events
	 */
	_draw( )
	{
		// Remove any existing
		this.rows.remove('.calendar_eventRowsMarkedDay,.calendar_eventRowsFiller').nextAll().remove();

		let days = 31;
		let width = '100';
		if (this.getParent().options.group_by === 'month')
		{
			days = this.options.end_date.getUTCDate();

			if(days < 31)
			{
				const diff = 31 - days;
				width = 'calc('+(diff * 3.23) + '% - ' + (diff * 7) + 'px)';
			}
		}

		// mark weekends and other special days in yearly planner
		if (this.getParent().options.group_by == 'month')
		{
			this.rows.append(this._yearlyPlannerMarkDays(this.options.start_date, days));
		}

		if (this.getParent().options.group_by === 'month' && days < 31)
		{
			// add a filler for non existing days in that month
			this.rows.after('<div class="calendar_eventRowsFiller"'+
				' style="width:'+width+';" ></div>');
		}
	}

	set_label(label)
	{
		this.options.label = label;
		this.title.text(label);
		if(this.getParent().options.group_by === 'month')
		{
			this.title.attr('data-date', this.options.start_date.toJSON());
			this.title.attr('data-sortby', 'user');
			this.title.addClass('et2_clickable et2_link');
		}
		else
		{
			this.title.attr('data-date','');
			this.title.removeClass('et2_clickable');
		}
	}

	/**
	 * Change the start date
	 *
	 * @param {Date} new_date New end date
	 * @returns {undefined}
	 */
	set_start_date(new_date)
	{
		if(!new_date || new_date === null)
		{
			throw new TypeError('Invalid end date. ' + new_date.toString());
		}

		this.options.start_date = new Date(typeof new_date == 'string' ? new_date : new_date.toJSON());
		this.options.start_date.setUTCHours(0);
		this.options.start_date.setUTCMinutes(0);
		this.options.start_date.setUTCSeconds(0);
	}
	/**
	 * Change the end date
	 *
	 * @param {string|number|Date} new_date New end date
	 * @returns {undefined}
	 */
	set_end_date(new_date)
	{
		if(!new_date || new_date === null)
		{
			throw new TypeError('Invalid end date. ' + new_date.toString());
		}

		this.options.end_date = new Date(typeof new_date == 'string' ? new_date : new_date.toJSON());
		this.options.end_date.setUTCHours(23);
		this.options.end_date.setUTCMinutes(59);
		this.options.end_date.setUTCSeconds(59);
	}

	/**
	 * Mark special days (birthdays, holidays) on the planner
	 *
	 * @param {Date} start Start of the month
	 * @param {number} days How many days in the month
	 */
	_yearlyPlannerMarkDays(start,days)
	{
		const day_width = 3.23;
		const t = new Date(start);
		let content = '';
		for(let i = 0; i < days; i++)
		{
			const holidays = [];
			// TODO: implement this, pull / copy data from et2_widget_timegrid
			const day_class = (<et2_calendar_planner>this.getParent()).day_class_holiday(t, holidays);

			if (day_class)	// no regular weekday
			{
				content += '<div class="calendar_eventRowsMarkedDay '+day_class+
					'" style="left: '+(i*day_width)+'%; width:'+day_width+'%;"'+
					(holidays ? ' title="'+holidays.join(',')+'"' : '')+
					' ></div>';
			}
			t.setUTCDate(t.getUTCDate()+1);
		}
		return content;
	}

	/**
	 * Callback used when the daywise data changes
	 *
	 * Events should update themselves when their data changes, here we are
	 * dealing with a change in which events are displayed on this row.
	 *
	 * @param {String[]} event_ids
	 * @returns {undefined}
	 */
	_data_callback( event_ids)
	{
		const events = [];
		if(event_ids == null || typeof event_ids.length == 'undefined') event_ids = [];
		for(let i = 0; i < event_ids.length; i++)
		{
			let event = <any>egw.dataGetUIDdata('calendar::' + event_ids[i]);
			event = event && event.data || false;
			if(event && event.date)
			{
				events.push(event);
			}
			else if (event)
			{
				// Got an ID that doesn't belong
				event_ids.splice(i--,1);
			}
		}
		if(!this.getParent().disabled && event_ids.length > 0)
		{
			this.resize();
			this._update_events(events);
		}
	}

	date_helper(value)
	{
		return (<et2_calendar_view>this.getParent()).date_helper(value);
	}

	/**
	 * Load the event data for this day and create event widgets for each.
	 *
	 * If event information is not provided, it will be pulled from the content array.
	 *
	 * @param {Object[]} [events] Array of event information, one per event.
	 */
	_update_events(events)
	{
		// Remove all events
		while(this._children.length > 0)
		{
			const node = this._children[this._children.length - 1];
			this.removeChild(node);
			node.destroy();
		}
		this._cached_rows = [];

		for(var c = 0; c < events.length; c++)
		{
			// Create event
			var event = et2_createWidget('calendar-event',{
				id:'event_'+events[c].row_id,
				value: events[c]
			},this);
		}

		// Seperate loop so column sorting finds all children in the right place
		for(var c = 0; c < events.length; c++)
		{
			let event = this.getWidgetById('event_'+events[c].row_id);
			if(!event) continue;
			if(this.isInTree())
			{
				event.doLoadingFinished();
			}
		}
	}

	/**
	 * Position the event according to it's time and how this widget is laid
	 * out.
	 *
	 * @param {undefined|Object|et2_calendar_event} event
	 */
	position_event(event?)
	{
		const rows = this._spread_events();
		const height = rows.length * this._row_height;
		let row_width = this.rows.width();
		if(row_width == 0)
		{
			// Not rendered yet or something
			row_width = this.getParent().gridHeader.width() - this.title.width()
		}
		row_width -= 15;

		for(let c = 0; c < rows.length; c++)
		{
			// Calculate vertical positioning
			const top = c * (100.0 / rows.length);

			for(let i = 0; (rows[c].indexOf(event) >=0 || !event) && i < rows[c].length; i++)
			{
				// Calculate horizontal positioning
				const left = this._time_to_position(rows[c][i].options.value.start);
				const width = this._time_to_position(rows[c][i].options.value.end) - left;

				// Position the event
				rows[c][i].div.css('top', top+'%');
				rows[c][i].div.css('height', (100/rows.length)+'%');
				rows[c][i].div.css('left', left.toFixed(1)+'%');
				rows[c][i].div.outerWidth((width/100 * row_width) +'px');
			}
		}
		if(height)
		{
			this.div.height(height+'px');
		}
	}

	/**
	 * Sort a day's events into non-overlapping rows
	 *
	 * @returns {Array[]} Events sorted into rows
	 */
	_spread_events()
	{
		// Keep it so we don't have to re-do it when the next event asks
		let cached_length = 0;
		this._cached_rows.map(function(row) {cached_length+=row.length;});
		if(cached_length === this._children.length)
		{
			return this._cached_rows;
		}

		// sorting the events in non-overlapping rows
		const rows = [];
		const row_end = [0];

		// Sort in chronological order, so earliest ones are at the top
		this._children.sort(function(a,b) {
			const start = new Date(a.options.value.start) - new Date(b.options.value.start);
			const end = new Date(a.options.value.end) - new Date(b.options.value.end);
			// Whole day events sorted by ID, normal events by start / end time
			if(a.options.value.whole_day && b.options.value.whole_day)
			{
				// Longer duration comes first so we have nicer bars across the top
				const duration =
					(new Date(b.options.value.end) - new Date(b.options.value.start)) -
					(new Date(a.options.value.end) - new Date(a.options.value.start));

				return duration ? duration : (a.options.value.app_id - b.options.value.app_id);
			}
			else if (a.options.value.whole_day || b.options.value.whole_day)
			{
				return a.options.value.whole_day ? -1 : 1;
			}
			return start ? start : end;
		});

		for(let n = 0; n < this._children.length; n++)
		{
			const event = this._children[n].options.value || false;
			if(typeof event.start !== 'object')
			{
				event.start = this.date_helper(event.start);
			}
			if(typeof event.end !== 'object')
			{
				event.end = this.date_helper(event.end);
			}
			if(typeof event['start_m'] === 'undefined')
			{
				let day_start = event.start.valueOf() / 1000;
				const dst_check = new Date(event.start);
				dst_check.setUTCHours(12);

				// if daylight saving is switched on or off, correct $day_start
				// gives correct times after 2am, times between 0am and 2am are wrong
				const daylight_diff = day_start + 12 * 60 * 60 - (dst_check.valueOf() / 1000);
				if(daylight_diff)
				{
					day_start -= daylight_diff;
				}

				event['start_m'] = event.start.getUTCHours() * 60 + event.start.getUTCMinutes();
				if (event['start_m'] < 0)
				{
					event['start_m'] = 0;
					event['multiday'] = true;
				}
				event['end_m'] = event.end.getUTCHours() * 60 + event.end.getUTCMinutes();
				if (event['end_m'] >= 24*60)
				{
					event['end_m'] = 24*60-1;
					event['multiday'] = true;
				}
				if(!event.start.getUTCHours() && !event.start.getUTCMinutes() && event.end.getUTCHours() == 23 && event.end.getUTCMinutes() == 59)
				{
					event.whole_day_on_top = (event.non_blocking && event.non_blocking != '0');
				}
			}

			// Skip events entirely on hidden weekends
			if(this._hidden_weekend_event(event))
			{
				const node = this._children[n];
				this.removeChild(n--);
				node.destroy();
				continue;
			}

			const event_start = new Date(event.start).valueOf();
			for(var row = 0; row_end[row] > event_start; ++row);	// find a "free" row (no other event)
			if(typeof rows[row] === 'undefined') rows[row] = [];
			rows[row].push(this._children[n]);
			row_end[row] = new Date(event['end']).valueOf();
		}
		this._cached_rows = rows;
		return rows;
	}

	/**
	 * Check to see if the event is entirely on a hidden weekend
	 *
	 * @param values Array of event values, not an et2_widget_event
	 */
	_hidden_weekend_event(values)
	{
		if(!this.getParent() || this.getParent().options.group_by == 'month' || this.getParent().options.show_weekend)
		{
			return false;
		}
		// Starts on Saturday or Sunday, ends Sat or Sun, less than 2 days long
		else if([0,6].indexOf(values.start.getUTCDay()) !== -1 && [0,6].indexOf(values.end.getUTCDay()) !== -1
				&& values.end - values.start < 2 * 24 * 3600 * 1000)
		{
			return true;
		}
		return false;
	}

	/**
	 * Calculates the horizontal position based on the time given, as a percentage
	 * between the start and end times
	 *
	 * @param {int|Date|string} time in minutes from midnight, or a Date in string or object form
	 * @param {int|Date|string} start Earliest possible time (0%)
	 * @param {int|Date|string} end Latest possible time (100%)
	 * @return {float} position in percent
	 */
	_time_to_position(time, start?, end?)
	{
		let pos = 0.0;

		// Handle the different value types
		start = this.options.start_date;
		end = this.options.end_date;

		if(typeof start === 'string')
		{
			start = new Date(start);
			end = new Date(end);
		}
		const wd_start = 60 * (parseInt(''+egw.preference('workdaystarts', 'calendar')) || 9);
		const wd_end = 60 * (parseInt(''+egw.preference('workdayends', 'calendar')) || 17);

		let t = time;
		if(typeof time === 'number' && time < 3600)
		{
			t = new Date(start.valueOf() + wd_start * 3600*1000);
		}
		else
		{
			t = new Date(time);
		}

		// Limits
		if(t <= start) return 0; // We are left of our scale
		if(t >= end) return 100; // We are right of our scale

		// Remove space for weekends, if hidden
		let weekend_count = 0;
		let weekend_before = 0;
		let partial_weekend = 0;
		if(this.getParent().options.group_by !== 'month' && this.getParent() && !this.getParent().options.show_weekend)
		{

			const counter_date = new Date(start);
			do
			{
				if([0,6].indexOf(counter_date.getUTCDay()) !== -1)
				{
					if(counter_date.getUTCDate() === t.getUTCDate() && counter_date.getUTCMonth() === t.getUTCMonth())
					{
						// Event is partially on a weekend
						partial_weekend += (t.getUTCHours() *60 + t.getUTCMinutes())*60*1000;
					}
					else if(counter_date < t)
					{
						weekend_before++;
					}
					weekend_count++;
				}
				counter_date.setUTCDate(counter_date.getUTCDate() + 1);
			} while(counter_date < end);
			// Put it in ms
			weekend_before *= 24 * 3600 * 1000;
			weekend_count *= 24 * 3600 * 1000;
		}

		// Basic scaling, doesn't consider working times
		pos = (t - start - weekend_before-partial_weekend) / (end - start - weekend_count);

		// Month view
		if(this.getParent().options.group_by !== 'month')
		{
			// Daywise scaling
			/* Needs hourly scales that consider working hours
			var start_date = new Date(start.getUTCFullYear(), start.getUTCMonth(),start.getUTCDate());
			var end_date = new Date(end.getUTCFullYear(), end.getUTCMonth(),end.getUTCDate());
			var t_date = new Date(t.getUTCFullYear(), t.getUTCMonth(),t.getUTCDate());

			var days = Math.round((end_date - start_date) / (24 * 3600 * 1000))+1;
			pos = 1 / days * Math.round((t_date - start_date) / (24*3600 * 1000));

			var time_of_day = typeof t === 'object' ? 60 * t.getUTCHours() + t.getUTCMinutes() : t;

			if (time_of_day >= wd_start)
			{
				var day_percentage = 0.1;
				if (time_of_day > wd_end)
				{
					day_percentage = 1;
				}
				else
				{
					var wd_length = wd_end - wd_start;
					if (wd_length <= 0) wd_length = 24*60;
					day_percentage = (time_of_day-wd_start) / wd_length;		// between 0 and 1
				}
				pos += day_percentage / days;
			}
			*/
		}
		else
		{
			// 2678400 is the number of seconds in 31 days
			pos = (t - start) / 2678400000;
		}
		pos = 100 * pos;

		return pos;
	}

	// Resizable interface
	/**
	 * Resize
	 *
	 * Parent takes care of setting proper width & height for the containing div
	 * here we just need to adjust the events to fit the new size.
	 */
	resize ()
	{
		if(this.disabled || !this.div.is(':visible') || this.getParent().disabled)
		{
			return;
		}

		const row = jQuery('<div class="calendar_plannerEventRowWidget"></div>').appendTo(this.rows);
		this._row_height = (parseInt(window.getComputedStyle(row[0]).getPropertyValue("height")) || 20);
		row.remove();

		// Resize & position all events
		this.position_event();
	}

}
et2_register_widget(et2_calendar_planner_row, ["calendar-planner_row"]);