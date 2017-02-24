/*
 * Egroupware
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package
 * @subpackage
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

/*egw:uses
	/calendar/js/et2_widget_view.js;
	/calendar/js/et2_widget_daycol.js;
	/calendar/js/et2_widget_event.js;
*/


/**
 * Class for one row of a planner
 *
 * This widget is responsible for the label on the side
 *
 * @augments et2_valueWidget
 */
var et2_calendar_planner_row = (function(){ "use strict"; return et2_valueWidget.extend([et2_IResizeable],
{
	attributes: {
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
	},

	/**
	 * Constructor
	 *
	 * @memberOf et2_calendar_daycol
	 */
	init: function() {
		this._super.apply(this, arguments);

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

		// Used for its date calculations
		this.date_helper = et2_createWidget('date-time',{},null);
		this.date_helper.loadingFinished();

		this.set_start_date(this.options.start_date);
		this.set_end_date(this.options.end_date);

		this._cached_rows = [];
		this._row_height = 20;
	},

	doLoadingFinished: function() {
		this._super.apply(this, arguments);

		this.set_label(this.options.label);
		this._draw();
		
		this._link_actions([]);
		return true;
	},

	destroy: function() {
		this._super.apply(this, arguments);

		// date_helper has no parent, so we must explicitly remove it
		this.date_helper.destroy();
		this.date_helper = null;
	},

	getDOMNode: function(_sender)
	{
		if(_sender === this || !_sender)
		{
			return this.div[0];
		}
		if(_sender._parent === this)
		{
			return this.rows[0];
		}
	},

	/**
	 * Link the actions to the DOM nodes / widget bits.
	 *
	 * @param {object} actions {ID: {attributes..}+} map of egw action information
	 */
	_link_actions: function(actions)
	{
		// Get the parent?  Might be a grid row, might not.  Either way, it is
		// just a container with no valid actions
		var objectManager = egw_getObjectManager(this.getInstanceManager().app,true,1);
		objectManager = objectManager.getObjectById(this.getInstanceManager().uniqueId,2) || objectManager;
		var parent = objectManager.getObjectById(this.id,1) || objectManager.getObjectById(this._parent.id,1) || objectManager;
		if(!parent)
		{
			egw.debug('error','No parent objectManager found');
			return;
		}

		// This binds into the egw action system.  Most user interactions (drag to move, resize)
		// are handled internally using jQuery directly.
		var widget_object = this._actionObject || parent.getObjectById(this.id);
		var aoi = new et2_action_object_impl(this,this.getDOMNode());
		var planner = this.getParent();

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
		var _invite_enabled = function(action, event, target)
		{
			var event = event.iface.getWidget();
			var row = target.iface.getWidget() || false;
			if(event === row || !event || !row ||
				!event.options || !event.options.value.participants
			)
			{
				return false;
			}
			
			var owner_match = false;
			var own_row = event.getParent() === row;

			for(var id in event.options.value.participants)
			{
				owner_match = owner_match || row.node.dataset.participants === ''+id;
			}

			var enabled = !owner_match &&
				// Not inside its own timegrid
				!own_row;

			widget_object.getActionLink('invite').enabled = enabled;
			widget_object.getActionLink('change_participant').enabled = enabled;

			// If invite or change participant are enabled, drag is not
			widget_object.getActionLink('egw_link_drop').enabled = !enabled;
		};

		aoi.doTriggerEvent = function(_event, _data) {

			// Determine target node
			var event = _data.event || false;
			if(!event) return;
			if(_data.ui.draggable.hasClass('rowNoEdit')) return;
			/*
			We have to handle the drop in the normal event stream instead of waiting
			for the egwAction system so we can get the helper, and destination
			*/
			if(event.type === 'drop' && widget_object.getActionLink('egw_link_drop').enabled)
			{
				this.getWidget().getParent()._event_drop.call(
					jQuery('.calendar_d-n-d_timeCounter',_data.ui.helper)[0],
					this.getWidget().getParent(), event, _data.ui,
					this.getWidget()
				);
			}
			var drag_listener = function(_event, ui) {
				if(planner.options.group_by === 'month')
				{
					var position = {left: _event.clientX, top: _event.clientY};
				}
				else
				{
					var position = {top:ui.position.top, left: ui.position.left - jQuery(this).parent().offset().left};
				}
				aoi.getWidget().getParent()._drag_helper(
					jQuery('.calendar_d-n-d_timeCounter',ui.helper)[0],
					position,0
				);

				var event = _data.ui.draggable.data('selected')[0];
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
			var time = jQuery('.calendar_d-n-d_timeCounter',_data.ui.helper);
			switch(_event)
			{
				// Triggered once, when something is dragged into the timegrid's div
				case EGW_AI_DRAG_OVER:
					// Listen to the drag and update the helper with the time
					// This part lets us drag between different timegrids
					_data.ui.draggable.on('drag.et2_timegrid_row'+widget_object.id, drag_listener);
					_data.ui.draggable.on('dragend.et2_timegrid_row'+widget_object.id, function() {
						_data.ui.draggable.off('drag.et2_timegrid_row' + widget_object.id);
					});
					widget_object.iface.getWidget().div.addClass('drop-hover');

					// Disable invite / change actions for same calendar or already participant
					var event = _data.ui.draggable.data('selected')[0];
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
						_data.ui.helper.prepend('<div class="calendar_d-n-d_timeCounter" data-count="1"><span></span></div>');
					}


					break;

				// Triggered once, when something is dragged out of the timegrid
				case EGW_AI_DRAG_OUT:
					// Stop listening
					_data.ui.draggable.off('drag.et2_timegrid_row'+widget_object.id);
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
		var action_links = this._get_action_links(actions);

		this.getParent()._init_links_dnd(widget_object.manager, action_links);

		widget_object.updateActionLinks(action_links);
	},

	/**
	 * Get all action-links / id's of 1.-level actions from a given action object
	 *
	 * Here we are only interested in drop events.
	 *
	 * @param actions
	 * @returns {Array}
	 */
	_get_action_links: function(actions)
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
	},

	/**
	 * Draw the individual divs for weekends and events
	 */
	_draw: function() {
		// Remove any existing
		this.rows.remove('.calendar_eventRowsMarkedDay,.calendar_eventRowsFiller').nextAll().remove();

		var days = 31;
		var width = 100;
		if (this._parent.options.group_by === 'month')
		{
			days = this.options.end_date.getUTCDate();
			
			if(days < 31)
			{
				var diff = 31 - days;
				width = 'calc('+(diff * 3.23) + '% - ' + (diff * 7) + 'px)';
			}
		}

		// mark weekends and other special days in yearly planner
		if (this._parent.options.group_by == 'month')
		{
			this.rows.append(this._yearlyPlannerMarkDays(this.options.start_date, days));
		}

		if (this._parent.options.group_by === 'month' && days < 31)
		{
			// add a filler for non existing days in that month
			this.rows.after('<div class="calendar_eventRowsFiller"'+
				' style="width:'+width+';" ></div>');
		}
	},

	set_label: function(label)
	{
		this.options.label = label;
		this.title.text(label);
		if(this._parent.options.group_by === 'month')
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
	},

	/**
	 * Change the start date
	 *
	 * @param {Date} new_date New end date
	 * @returns {undefined}
	 */
	set_start_date: function(new_date)
	{
		if(!new_date || new_date === null)
		{
			throw exception('Invalid end date. ' + new_date.toString());
		}

		this.options.start_date = new Date(typeof new_date == 'string' ? new_date : new_date.toJSON());
		this.options.start_date.setUTCHours(0);
		this.options.start_date.setUTCMinutes(0);
		this.options.start_date.setUTCSeconds(0);
	},
	/**
	 * Change the end date
	 *
	 * @param {string|number|Date} new_date New end date
	 * @returns {undefined}
	 */
	set_end_date: function(new_date)
	{
		if(!new_date || new_date === null)
		{
			throw exception('Invalid end date. ' + new_date.toString());
		}

		this.options.end_date = new Date(typeof new_date == 'string' ? new_date : new_date.toJSON());
		this.options.end_date.setUTCHours(23);
		this.options.end_date.setUTCMinutes(59);
		this.options.end_date.setUTCSeconds(59);
	},

	/**
	 * Mark special days (birthdays, holidays) on the planner
	 *
	 * @param {Date} start Start of the month
	 * @param {number} days How many days in the month
	 */
	_yearlyPlannerMarkDays: function(start,days)
	{
		var day_width = 3.23;
		var t = new Date(start);
		var content = '';
		for(var i = 0; i < days;i++)
		{
			var holidays = [];
			// TODO: implement this, pull / copy data from et2_widget_timegrid
			var day_class = this._parent.day_class_holiday(t,holidays);

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
	},

	/**
	 * Callback used when the daywise data changes
	 *
	 * Events should update themselves when their data changes, here we are
	 * dealing with a change in which events are displayed on this row.
	 *
	 * @param {String[]} event_ids
	 * @returns {undefined}
	 */
	_data_callback: function(event_ids) {
		var events = [];
		if(event_ids == null || typeof event_ids.length == 'undefined') event_ids = [];
		for(var i = 0; i < event_ids.length; i++)
		{
			var event = egw.dataGetUIDdata('calendar::'+event_ids[i]);
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
		if(!this._parent.disabled)
		{
			this.resize();
			this._update_events(events);
		}
	},

	/**
	 * Load the event data for this day and create event widgets for each.
	 *
	 * If event information is not provided, it will be pulled from the content array.
	 *
	 * @param {Object[]} [events] Array of event information, one per event.
	 */
	_update_events: function(events)
	{
		// Remove all events
		while(this._children.length > 0)
		{
			var node = this._children[this._children.length-1];
			this.removeChild(node);
			node.free();
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
		for(var c = 0; c < events.length && c < this._children.length; c++)
		{
			var event = this.getWidgetById('event_'+events[c].row_id);
			if(!event) continue;
			if(this.isInTree())
			{
				event.doLoadingFinished();
			}
		}
	},

	/**
	 * Position the event according to it's time and how this widget is laid
	 * out.
	 *
	 * @param {undefined|Object|et2_calendar_event} event
	 */
	position_event: function(event)
	{
		var rows = this._spread_events();
		var height = rows.length * this._row_height;
		var row_width = this.rows.width();

		for(var c = 0; c < rows.length; c++)
		{
			// Calculate vertical positioning
			var top = c * (100.0 / rows.length);

			for(var i = 0; (rows[c].indexOf(event) >=0 || !event) && i < rows[c].length; i++)
			{
				// Calculate horizontal positioning
				var left = this._time_to_position(rows[c][i].options.value.start);
				var width = this._time_to_position(rows[c][i].options.value.end)-left;

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
	},

	/**
	 * Sort a day's events into non-overlapping rows
	 *
	 * @returns {Array[]} Events sorted into rows
	 */
	_spread_events: function()
	{
		// Keep it so we don't have to re-do it when the next event asks
		var cached_length = 0;
		this._cached_rows.map(function(row) {cached_length+=row.length;});
		if(cached_length === this._children.length)
		{
			return this._cached_rows;
		}

		// sorting the events in non-overlapping rows
		var rows = [];
		var row_end = [0];

		// Sort in chronological order, so earliest ones are at the top
		this._children.sort(function(a,b) {
			var start = new Date(a.options.value.start) - new Date(b.options.value.start);
			var end = new Date(a.options.value.end) - new Date(b.options.value.end);
			// Whole day events sorted by ID, normal events by start / end time
			if(a.options.value.whole_day && b.options.value.whole_day)
			{
				// Longer duration comes first so we have nicer bars across the top
				var duration =
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

		for(var n = 0; n < this._children.length; n++)
		{
			var event = this._children[n].options.value || false;
			if(typeof event.start !== 'object')
			{
				this.date_helper.set_value(event.start);
				event.start = new Date(this.date_helper.getValue());
			}
			if(typeof event.end !== 'object')
			{
				this.date_helper.set_value(event.end);
				event.end = new Date(this.date_helper.getValue());
			}
			if(typeof event['start_m'] === 'undefined')
			{
				var day_start = event.start.valueOf() / 1000;
				var dst_check = new Date(event.start);
				dst_check.setUTCHours(12);

				// if daylight saving is switched on or off, correct $day_start
				// gives correct times after 2am, times between 0am and 2am are wrong
				var daylight_diff = day_start + 12*60*60 - (dst_check.valueOf()/1000);
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

			var event_start = new Date(event.start).valueOf();
			for(var row = 0; row_end[row] > event_start; ++row);	// find a "free" row (no other event)
			if(typeof rows[row] === 'undefined') rows[row] = [];
			rows[row].push(this._children[n]);
			row_end[row] = new Date(event['end']).valueOf();
		}
		this._cached_rows = rows;
		return rows;
	},

	/**
	 * Calculates the horizontal position based on the time given, as a percentage
	 * between the start and end times
	 *
	 * @param {int|Date|string} time in minutes from midnight, or a Date in string or object form
	 * @param {int|Date|string} start Earliest possible time (0%)
	 * @param {int|Date|string} end Latest possible time (100%)
	 * @return {float} position in percent
	 */
	_time_to_position: function(time, start, end)
	{
		var pos = 0.0;

		// Handle the different value types
		start = this.options.start_date;
		end = this.options.end_date;

		if(typeof start === 'string')
		{
			start = new Date(start);
			end = new Date(end);
		}
		var wd_start = 60 * (parseInt(egw.preference('workdaystarts','calendar')) || 9);
		var wd_end = 60 * (parseInt(egw.preference('workdayends','calendar')) || 17);

		var t = time;
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
		var weekend_count = 0;
		var weekend_before = 0;
		if(this._parent.options.group_by !== 'month' && this._parent && !this._parent.options.show_weekend)
		{

			var counter_date = new Date(start);
			do
			{
				if([0,6].indexOf(counter_date.getUTCDay()) !== -1)
				{
					weekend_count++;
					if(counter_date < t) weekend_before++;
				}
				counter_date.setUTCDate(counter_date.getUTCDate() + 1);
			} while(counter_date < end)
			// Put it in ms
			weekend_before *= 24 * 3600 * 1000;
			weekend_count *= 24 * 3600 * 1000;
		}

		// Basic scaling, doesn't consider working times
		pos = (t - start - weekend_before) / (end - start - weekend_count);

		// Month view
		if(this._parent.options.group_by !== 'month')
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
	},

	// Resizable interface
	/**
	 * Resize
	 *
	 * Parent takes care of setting proper width & height for the containing div
	 * here we just need to adjust the events to fit the new size.
	 */
	resize: function ()
	{
		if(this.disabled || !this.div.is(':visible') || this._parent.disabled)
		{
			return;
		}

		var row = jQuery('<div class="calendar_plannerEventRowWidget"></div>').appendTo(this.rows);
		this._row_height = (parseInt(window.getComputedStyle(row[0]).getPropertyValue("height")) || 20);
		row.remove();
	}

});}).call(this);

et2_register_widget(et2_calendar_planner_row, ["calendar-planner_row"]);