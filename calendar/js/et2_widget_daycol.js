/* 
 * Egroupware
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */


"use strict";

/*egw:uses
	et2_core_valueWidget;
	/calendar/js/et2_widget_event.js;
*/

/**
 * Class which implements the "calendar-timegrid" XET-Tag for displaying a span of days
 *
 * This widget is responsible for the times on the side
 *
 * @augments et2_DOMWidget
 */
var et2_calendar_daycol = et2_valueWidget.extend([et2_IDetachedDOM],
{

	attributes: {
		date: {
			name: "Date",
			type: "any",
			description: "What date is this daycol for.  YYYYMMDD or Date"
		},
		owner: {
			name: "Owner",
			type: "any", // Integer, or array of integers
			default: 0,
			description: "Account ID number of the calendar owner, if not the current user"
		},
		display_birthday_as_event: {
			name: "Birthdays",
			type: "boolean",
			default: false,
			description: "Display birthdays as events"
		},
		display_holiday_as_event: {
			name: "Holidays",
			type: "boolean",
			default: false,
			description: "Display holidays as events"
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
			.addClass("calendar_calDayCol")
			.css('width',this.options.width);
		this.title = $j(document.createElement('div'))
			.appendTo(this.div);

		this.setDOMNode(this.div[0]);

		// Used for its date calculations - note this is a datetime, parent
		// uses just a date
		this.date_helper = et2_createWidget('date-time',{},null);
		this.date_helper.loadingFinished();

		// Init to defaults, just in case
		this.display_settings = {
			wd_start:	60*9,
			wd_end:		60*17,
			granularity:	30,
			extraRows:	2,
			rowsToDisplay:	10,
			rowHeight:	20
		}
	},

	doLoadingFinished: function() {
		this._super.apply(this, arguments);
		
		// Parent will have everything we need, just load it from there
		if(this.title.text() === '' && this.options.date &&
			this._parent && this._parent.instanceOf(et2_calendar_timegrid))
		{
			// Forces an update
			var date = this.options.date;
			this.options.date = '';
			this.set_date(date);
		}
	},

	destroy: function() {
		this._super.apply(this, arguments);
	},

	/**
	 * Draw the individual divs for clicking
	 */
	_draw: function() {
		// Remove any existing
		$j('.calendar_calAddEvent',this.div).remove();

		// Grab real values from parent
		if(this._parent && this._parent.instanceOf(et2_calendar_timegrid))
		{
			this.display_settings.wd_start = 60*this._parent.options.day_start;
			this.display_settings.wd_end = 60*this._parent.options.day_end;
			this.display_settings.granularity = this._parent.options.granularity;
			this.display_settings.extraRows = this._parent.options.extra_rows;
		}

		this.display_settings.rowsToDisplay	= ((this.display_settings.wd_end - this.display_settings.wd_start)/this.display_settings.granularity)+2+2*this.display_settings.extraRows;
		this.display_settings.rowHeight= (100/this.display_settings.rowsToDisplay).toFixed(1);

		// adding divs to click on for each row / time-span
		for(var t =this.display_settings.wd_start,i = 1+this.display_settings.extraRows; t <= this.display_settings.wd_end; t += this.display_settings.granularity,++i)
		{
			var linkData = {
				'menuaction':'calendar.calendar_uiforms.edit',
				'date'		: this.options.date,
				'hour'		: sprintf("%02d",Math.floor(t / 60)),
				'minute'	: sprintf("%02d",Math.floor(t % 60))
			};
			if (this.options.owner) linkData['owner'] = this.options.owner;

			var droppableDateTime = linkData['date'] + "T" + linkData['hour'] + linkData['minute'];
			var droppableID='drop_'+droppableDateTime+'_O'+(this.options.owner<0?'group'+Math.abs(this.options.owner):this.options.owner);

			var hour = jQuery('<div id="' + droppableID + '" style="height:'+ this.display_settings.rowHeight +'%; top: '+ (i*this.display_settings.rowHeight).toFixed(1) +'%;" class="calendar_calAddEvent">')
				.attr('data-date',linkData.date)
				.attr('data-hour',linkData.hour)
				.attr('data-minute',linkData.minute)
				.appendTo(this.div);
		}
	},

	/**
	 * Set the date
	 *
	 * @param {string|Date} _date New date
	 * @param {Object[]} events=false List of events to be displayed, or false to
	 *	automatically fetch data from content array
	 * @param {boolean} force_redraw=false Redraw even if the date is the same.
	 *	Used for when new data is available.
	 */
	set_date: function(_date, events, force_redraw)
	{
		if(typeof events === 'undefined' || !events)
		{
			events = false;
		}
		if(typeof force_redraw === 'undefined' || !force_redraw)
		{
			force_redraw = false;
		}
		if(!this._parent || !this._parent.date_helper)
		{
			egw.debug('warn', 'Day col widget "' + this.id + '" is missing its parent.');
			return false;
		}
		if(typeof _date === "object")
		{
			this._parent.date_helper.set_value(_date);
		}
		else if(typeof _date === "string")
		{
			this._parent.date_helper.set_year(_date.substring(0,4));
			this._parent.date_helper.set_month(_date.substring(4,6));
			this._parent.date_helper.set_date(_date.substring(6,8));
		}

		this.date = new Date(this._parent.date_helper.getValue());

		// Keep internal option in Ymd format, it gets passed around in this format
		var new_date = ""+this._parent.date_helper.get_year()+
			sprintf("%02d",this._parent.date_helper.get_month())+
			sprintf("%02d",this._parent.date_helper.get_date());
		
		// Set label
		// Add timezone offset back in, or formatDate will lose those hours
		var formatDate = new Date(this.date.valueOf() + this.date.getTimezoneOffset() * 60 * 1000);
		var date_string = this._parent._children.length === 1 ?
			this.long_date(formatDate,false, false, true) :
			jQuery.datepicker.formatDate('DD dd',formatDate);
		this.title.text(date_string);

		// Avoid redrawing if date is the same
		if(new_date === this.options.date && !force_redraw)
		{
			return;
		}
		else
		{
			this.options.date = new_date;
		}

		this.div.attr("data-date", this.options.date);

		// Set holiday and today classes
		this.day_class_holiday();

		// Update all the little boxes
		this._draw();

		this._update_events(events);
	},

	/**
	 * Set the owner of this day
	 *
	 * @param {number} _owner Account ID
	 */
	set_owner: function(_owner) {
		this.options.owner = parseInt(_owner);
		this.div.attr('data-sortable-id', this.options.owner);
	},

	/**
	 * Applies class for today, and any holidays for current day
	 */
	day_class_holiday: function() {
		// Remove all classes
		this.title.removeClass()
			// Except this one...
			.addClass("et2_clickable calendar_calDayColHeader");

		// Set today class - note +1 when dealing with today, as months in JS are 0-11
		var today = new Date();
		
		this.title.toggleClass("calendar_calToday", this.options.date === ''+today.getUTCFullYear()+
			sprintf("%02d",today.getUTCMonth()+1)+
			sprintf("%02d",today.getUTCDate())
		);

		// Holidays and birthdays
		var holidays = et2_calendar_daycol.get_holidays(this,this.options.date.substring(0,4));
		var holiday_list = [];
		if(holidays && holidays[this.options.date])
		{
			holidays = holidays[this.options.date];
			for(var i = 0; i < holidays.length; i++)
			{
				if (typeof holidays[i]['birthyear'] !== 'undefined')
				{
					this.title.addClass('calendar_calBirthday');

					//If the birthdays are already displayed as event, don't
					//show them in the caption
					if (!this.options.display_birthday_as_event)
					{
						holiday_list.push(holidays[i]['name']);
					}
				}
				else
				{
					this.title.addClass('calendar_calHoliday');
					this.title.attr('data-holiday', holidays[i]['name']);

					//If the birthdays are already displayed as event, don't
					//show them in the caption
					if (!this.options.display_holiday_as_event)
					{
						holiday_list.push(holidays[i]['name']);
					}
				}
			}
		}
		this.title.attr('title', holiday_list.join(','));
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
		}
		var events = _events || this.getArrayMgr('content').getEntry(this.options.date) || [];

		// Sort events into minimally-overlapping columns
		var columns = this._spread_events(events);

		for(var c = 0; c < columns.length; c++)
		{
			// Calculate horizontal positioning
			var left = Math.ceil(5 + (1.5 * 100 / (this.options.width || 100)));
			var width = 98 - left;
			if (columns.length !== 1)
			{
				width = !c ? 70 : 50;
				left += c * (100.0-left) / columns.length;
			}
			if (left + width > 100.0) width = 98.0 - left;

			var whole_day_counter = 0;

			for(var i = 0; i < columns[c].length; i++)
			{
				// Calculate vertical positioning
				var top = 0;
				var height = 0;
				if(columns[c][i].whole_day_on_top)
				{
					top =  ((this.title.height()/this.div.height())*100) + this.display_settings.rowHeight*whole_day_counter++;
					height = this.display_settings.rowHeight;
				}
				else
				{
					top = this._time_to_position(columns[c][i].start_m,whole_day_counter);
					height = this._time_to_position(columns[c][i].end_m,whole_day_counter)-top;
				}

				// Create event
				var event = et2_createWidget('calendar-event',{id:columns[c][i].app_id||columns[c][i].id},this);
				if(this.isInTree())
				{
					event.doLoadingFinished();
				}
				event.set_value(columns[c][i]);
				event._link_actions(this._parent._parent.options.actions||{});
				
				// Position the event
				event.div.css('top', top+'%');
				event.div.css('height', height+'%');
				event.div.css('left', left.toFixed(1)+'%');
				event.div.css('width', width.toFixed(1)+'%');
			}
		}
		
	},

	/**
	 * Sort a day's events into minimally overlapping columns
	 * 
	 * @param {Object[]} events
	 * @returns {Array[]} Events sorted into columns
	 */
	_spread_events: function(events)
	{
		var day_start = this.date.valueOf() / 1000;
		var dst_check = new Date(this.date);
		dst_check.setUTCHours(12);

		// if daylight saving is switched on or off, correct $day_start
		// gives correct times after 2am, times between 0am and 2am are wrong
		var daylight_diff = day_start + 12*60*60 - (dst_check.valueOf()/1000);
		if(daylight_diff)
		{
			day_start -= daylight_diff;
		}

		var eventCols = [], col_ends = [];
		for(var i = 0; i < events.length; i++)
		{
			var event = events[i];
			var c = 0;
			event['multiday'] = false;
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
			if(!event.start.getUTCHours() && !event.start.getUTCMinutes() && event.end.getUTCHours() == 23 && event.end.getUTCMinutes() == 59)
			{
				event.whole_day_on_top = (event.non_blocking && event.non_blocking != '0');
			}
			if (!event['whole_day_on_top'])
			{
				for(c = 0; event['start_m'] < col_ends[c]; ++c);
				col_ends[c] = event['end_m'];
			}
			if(typeof eventCols[c] == 'undefined')
			{
				eventCols[c] = [];
			}
			eventCols[c].push(event);
		}
		return eventCols;
	},
	
	/**
	 * Calculates the vertical position based on the time
	 *
	 * workday start- and end-time, is taken into account, as well as timeGrids px_m - minutes per pixel param
	 *
	 * @param {int} time in minutes from midnight
	 * @param {int} [row_offset=0] Add extra spacing for additional rows
	 * @return {float} position in percent
	 */
	_time_to_position: function(time,row_offset)
	{
		var pos = 0.0;
		if(typeof row_offset === 'undefined')
		{
			row_offset = 0;
		}
		
		// time before workday => condensed in the first $this->extraRows rows
		if (this.display_settings.wd_start > 0 && time < this.display_settings.wd_start)
		{
			pos = ((this.title.height()/this.div.height())*100) + (row_offset + (time / this.display_settings.wd_start )) * this.display_settings.rowHeight;
		}
		// time after workday => condensed in the last row
		else if (this.display_settings.wd_end < 24*60 && time > this.display_settings.wd_end+1*this.display_settings.granularity)
		{
			pos = 100 - (row_offset * this.display_settings.rowHeight * (1 - (time - this.display_settings.wd_end) / (24*60 - this.display_settings.wd_end)));
		}
		// time during the workday => 2. row on (= + granularity)
		else
		{
			pos = this.display_settings.rowHeight * (1+this.display_settings.extraRows+(time-this.display_settings.wd_start)/this.display_settings.granularity);
		}
		pos = pos.toFixed(1)

		return pos;
	},

	/**
	* Formats one or two dates (range) as long date (full monthname), optionaly with a time
	*
	* Take care of any timezone issues before you pass the dates in.
	*
	* @param {Date} first first date
	* @param {Date} last=0 last date for range, or false for a single date
	* @param {boolean} display_time=false should a time be displayed too
	* @param {boolean} display_day=false should a day-name prefix the date, eg. monday June 20, 2006
	* @return string with formatted date
	*/
	long_date: function(first, last, display_time, display_day)
	{
		if(!last || typeof last !== 'object')
		{
			 last = false;
		}

		if(!display_time) display_time = false;
		if(!display_day) display_day = false;

		var range = '';

		var datefmt = egw.preference('dateformat');
		var timefmt = egw.preference('timeformat') == 12 ? 'h:i a' : 'H:i';

		var month_before_day = datefmt[0].toLowerCase() == 'm' ||
			datefmt[2].toLowerCase() == 'm' && datefmt[4] == 'd';

		if (display_day)
		{
			range = jQuery.datepicker.formatDate('DD',first)+(datefmt[0] != 'd' ? ' ' : ', ');
		}
		for (var i = 0; i < 5; i += 2)
		{
			 switch(datefmt[i])
			 {
				 case 'd':
					 range += first.getUTCDate()+ (datefmt[1] == '.' ? '.' : '');
					 if (last && (first.getUTCMonth() != last.getUTCMonth() || first.getFullYear() != last.getFullYear()))
					 {
						 if (!month_before_day)
						 {
							 range += jQuery.datepicker.formatDate('MM',first);
						 }
						 if (first.getFullYear() != last.getFullYear() && datefmt[0] != 'Y')
						 {
							 range += (datefmt[0] != 'd' ? ', ' : ' ') . first.getFullYear();
						 }
						 if (display_time)
						 {
							 range += ' '+jQuery.datepicker.formatDate(dateTimeFormat(timefmt),first);
						 }
						 if (!last)
						 {
							 return range;
						 }
						 range += ' - ';

						 if (first.getFullYear() != last.getFullYear() && datefmt[0] == 'Y')
						 {
							 range += last.getFullYear() + ', ';
						 }

						 if (month_before_day)
						 {
							 range += jQuery.datepicker.formatDate('MM',last);
						 }
					 }
					 else
					 {
						 if (display_time)
						 {
							 range += ' '+jQuery.datepicker.formatDate(dateTimeFormat(timefmt),last);
						 }
						 if(last)
						 {
							 range += ' - ';
						 }
					 }
					 if(last)
					 {
						 range += ' ' + last.getUTCDate() + (datefmt[1] == '.' ? '.' : '');
					 }
					 break;
				 case 'm':
				 case 'M':
					 range += ' '+jQuery.datepicker.formatDate('MM',month_before_day ? first : last) + ' ';
					 break;
				 case 'Y':
					 if (datefmt[0] != 'm')
					 {
						 range += ' ' + (datefmt[0] == 'Y' ? first.getFullYear()+(datefmt[2] == 'd' ? ', ' : ' ') : last.getFullYear()+' ');
					 }
					 break;
			 }
		}
		if (display_time && last)
		{
			 range += ' '+jQuery.datepicker.formatDate(dateTimeFormat(timefmt),last);
		}
		if (datefmt[4] == 'Y' && datefmt[0] == 'm')
		{
			 range += ', ' + last.getFullYear();
		}
		return range;
	},

	attachToDOM: function()
	{
		this._super.apply(this, arguments);

		// Remove the binding for the click handler, unless there's something
		// custom here.
		if (!this.onclick)
		{
			$j(this.node).off("click");
		}
		// But we do want to listen to certain clicks, and handle them internally
		$j(this.node).on('click.et2_daycol',
			'.calendar_calDayColHeader,.calendar_calAddEvent',
			jQuery.proxy(this.click, this)
		);
	},

	/**
	 * Click handler calling custom handler set via onclick attribute to this.onclick,
	 * or the default which is to open a new event at that time.
	 *
	 * Normally, you don't bind to this one, but the attribute is supported if you
	 * can get a reference to the widget.
	 *
	 * @param {Event} _ev
	 * @returns {boolean}
	 */
	click: function(_ev) {
		
		if($j(_ev.target).hasClass('calendar_calDayColHeader'))
		{
			this._parent.set_start_date(this.date);
			this._parent.set_end_date(this.date);
			return false;
		}
		else if ($j(_ev.target).hasClass('calendar_calAddEvent'))
		{
			// Default handler to open a new event at the selected time
			this.egw().open(null, 'calendar', 'add', {
				date: _ev.target.dataset.date || this.options.date,
				hour: _ev.target.dataset.hour || this._parent.options.day_start,
				minute: _ev.target.dataset.minute || 0
			} , '_blank');
			return false;
		}
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

et2_register_widget(et2_calendar_daycol, ["calendar-daycol"]);

// Static class stuff
jQuery.extend(et2_calendar_daycol,
{
	holiday_cache: {},
	/**
	 * Fetch and cache a list of the year's holidays
	 *
	 * @param {et2_calendar_timegrid} widget
	 * @param {string|numeric} year
	 * @returns {Array}
	 */
	get_holidays: function(widget,year)
	{
		// Loaded in an iframe or something
		if(!egw.window.et2_calendar_daycol) return {};

		var cache = egw.window.et2_calendar_daycol.holiday_cache[year];
		if (typeof cache == 'undefined')
		{
			// Fetch with json instead of jsonq because there may be more than
			// one widget listening for the response by the time it gets back,
			// and we can't do that when it's queued.
			egw.window.et2_calendar_daycol.holiday_cache[year] = egw.json(
				'calendar_timegrid_etemplate_widget::ajax_get_holidays',
				[year]
			).sendRequest();
		}
		cache = egw.window.et2_calendar_daycol.holiday_cache[year];
		if(typeof cache.done == 'function')
		{
			// pending, wait for it
			cache.done(jQuery.proxy(function(response) {
				egw.window.et2_calendar_daycol.holiday_cache[this.year] = response.response[0].data||undefined;

				egw.window.setTimeout(jQuery.proxy(function() {
					this.widget.day_class_holiday();
				},this),1);
			},{widget:widget,year:year}));
			return {};
		}
		else
		{
			return cache;
		}
	}
});