/* 
 * Egroupware
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package 
 * @subpackage 
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */



/**
 * Class for one row of a planner
 *
 * This widget is responsible for the label on the side
 *
 * @augments et2_valueWidget
 */
var et2_calendar_planner_row = et2_valueWidget.extend([et2_IDetachedDOM],
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
		this.div = $j(document.createElement("div"))
			.addClass("calendar_plannerRowWidget")
			.css('width',this.options.width);
		this.title = $j(document.createElement('div'))
			.addClass("calendar_plannerRowHeader")
			.css('width', '15%')
			.appendTo(this.div);
		this.rows = $j(document.createElement('div'))
			.addClass("calendar_eventRows")
			.appendTo(this.div);

		this.setDOMNode(this.div[0]);

		// Used for its date calculations
		this.date_helper = et2_createWidget('date-time',{},null);
		this.date_helper.loadingFinished();
	},

	doLoadingFinished: function() {
		this._super.apply(this, arguments);

		this.set_label(this.options.label);
		this._draw();
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
	 * Draw the individual divs for weekends and events
	 */
	_draw: function() {
		// Remove any existing
		this.rows.empty().nextAll().remove();

		var days = 31;
		var width = 85;
		if (this._parent.options.group_by === 'month')
		{
			days = new Date(this.options.end_date.getUTCFullYear(),this.options.end_date.getUTCMonth()+1,0).getUTCDate();
			if(days < 31)
			{
				width = 85*days/31;
				this.rows.css('width',width+'%');
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
				' style="left:'+(15+width)+'%; width:'+(85-width)+'%;" ></div>');
		}
	},

	set_label: function(label)
	{
		this.options.label = label;
		this.title.text(label);
		if(this._parent.options.group_by === 'month')
		{
			this.title.attr('data-date', this.options.start_date.toJSON());
			this.title.addClass('et2_clickable');
		}
		else
		{
			this.title.attr('data-date','');
			this.title.removeClass('et2_clickable');
		}
	},

	/**
	 * Mark special days (birthdays, holidays) on the planner
	 *
	 * @param {Date} start Start of the month
	 * @param {number} days How many days in the month
	 */
	_yearlyPlannerMarkDays: function(start,days)
	{
		var day_width = 100/days;
		var t = new Date(start);
		var content = '';
		for(var left = 0,i = 0; i < days;left += day_width,++i)
		{
			var holidays = [];
			// TODO: implement this, pull / copy data from et2_widget_timegrid
			var day_class = this._parent.day_class_holiday(t,holidays);

			if (day_class)	// no regular weekday
			{
				content += '<div class="calendar_eventRowsMarkedDay '+day_class+
					'" style="left: '+left+'%; width:'+day_width+'%;"'+
					(holidays ? ' title="'+holidays.join(',')+'"' : '')+
					' ></div>';
			}
			t.setUTCDate(t.getUTCDate()+1)
		}
		return content;
	},

	/**
	 * Load the event data for this day and create event widgets for each.
	 *
	 * If event information is not provided, it will be pulled from the content array.
	 *
	 * @param {Object[]} [_events] Array of event information, one per event.
	 */
	_update_events: function(_events)
	{
		// Remove all events
		while(this._children.length)
		{
			this._children[this._children.length-1].free();
			this.removeChild(this._children[this._children.length-1]);
		}
		
		var rows = this._spread_events(_events);
		var row = $j('<div class="calendar_plannerEventRowWidget"></div>').appendTo(this.rows);
		var height = rows.length * (parseInt(window.getComputedStyle(row[0]).getPropertyValue("height")) || 20);
		row.remove();

		for(var c = 0; c < rows.length; c++)
		{
			// Calculate vertical positioning
			var top = c * (100.0 / rows.length);

			for(var i = 0; i < rows[c].length; i++)
			{
				// Calculate horizontal positioning
				var left = this._time_to_position(rows[c][i].start);
				var width = this._time_to_position(rows[c][i].end)-left;

				// Create event
				var event = et2_createWidget('calendar-event',{
					id:rows[c][i].app_id||rows[c][i].id,
					class: 'calendar_plannerEvent'
				},this);
				if(this.isInTree())
				{
					event.doLoadingFinished();
				}
				event.set_value(rows[c][i]);

				// TODO
				event._link_actions(this._parent.options.actions||{});

				// Position the event
				event.div.css('top', top+'%');
				event.div.css('height', (100/rows.length)+'%');
				event.div.css('left', left.toFixed(1)+'%');
				event.div.css('width', width.toFixed(1)+'%');
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
	 * @param {Object[]} events
	 * @returns {Array[]} Events sorted into rows
	 */
	_spread_events: function(events)
	{
		// sorting the events in non-overlapping rows
		var rows = [];
		var row_end = [0];

		var start = this.options.start_date;
		var end = this.options.end_date;

		for(var n = 0; n < events.length; n++)
		{
			var event = events[n];
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
				
				event['start_m'] = parseInt((event.start.valueOf()/1000 - day_start) / 60);
				if (event['start_m'] < 0)
				{
					event['start_m'] = 0;
					event['multiday'] = true;
				}
				event['end_m'] = parseInt((event.end.valueOf()/1000 - day_start) / 60);
				if (event['end_m'] >= 24*60)
				{
					event['end_m'] = 24*60-1;
					event['multiday'] = true;
				}
			}

			var event_start = new Date(events[n].start).valueOf();
			for(var row = 0; row_end[row] > event_start; ++row);	// find a "free" row (no other event)
			if(typeof rows[row] === 'undefined') rows[row] = [];
			rows[row].push(events[n]);
			row_end[row] = new Date(events[n]['end']).valueOf();
		}
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

		// Basic scaling, doesn't consider working times
		pos = (t - start) / (end - start);


		// Month view
		if(this._parent.options.group_by !== 'month')
		{
			// Daywise scaling
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
			
		}
		pos = 100 * pos;

		return pos;
	},

	/**
	 * Code for implementing et2_IDetachedDOM
	 *
	 * @param {array} _attrs array to add further attributes to
	 */
	getDetachedAttributes: function(_attrs) {

	},

	getDetachedNodes: function() {
		return [this.getDOMNode()];
	},

	setDetachedAttributes: function(_nodes, _values) {

	},

});

et2_register_widget(et2_calendar_planner_row, ["calendar-planner_row"]);