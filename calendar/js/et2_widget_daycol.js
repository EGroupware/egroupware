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
var et2_calendar_daycol = et2_valueWidget.extend([et2_IDetachedDOM, et2_IResizeable],
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
			rowHeight:	20,
			// Percentage; not yet available
			titleHeight: 2.0
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

		egw.dataUnregisterUID(app.classes.calendar._daywise_cache_id(this.options.date,this.options.owner),false,this);
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
		this.display_settings.titleHeight = (this.title.height()/this.div.height())*100;

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
			// Need a new date to avoid invalid month/date combinations when setting
			// month then day
			this._parent.date_helper.set_value(new Date(_date.substring(0,4),_date.substring(4,6)-1,_date.substring(6,8),-new Date().getTimezoneOffset()/60,0,0));
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
			app.calendar.date.long_date(formatDate,false, false, true) :
			jQuery.datepicker.formatDate('DD dd',formatDate);
		this.title.text(date_string);

		// Avoid redrawing if date is the same
		if(new_date === this.options.date && !force_redraw)
		{
			return;
		}

		if(this.options.date)
		{
			egw.dataUnregisterUID(app.classes.calendar._daywise_cache_id(this.options.date,this.options.owner),false,this);
		}

		this.options.date = new_date;

		this.div.attr("data-date", this.options.date);

		// Set holiday and today classes
		this.day_class_holiday();

		// Update all the little boxes
		this._draw();


		// Register for updates on events for this day
		var cache_id = app.classes.calendar._daywise_cache_id(new_date,this.options.owner);
		egw.dataRegisterUID(cache_id, function(event_ids) {
			var events = [];
			for(var i = 0; i < event_ids.length; i++)
			{
				var event = egw.dataGetUIDdata('calendar::'+event_ids[i]).data;
				if(event && event.date && event.date === this.options.date)
				{
					events.push(event);
				}
			}
			this._update_events(events);
		},this,this.getInstanceManager().execId,this.id);

		if(events) {
			this._update_events(events);
		}
	},

	/**
	 * Set the owner of this day
	 *
	 * @param {number|number[]} _owner Account ID
	 */
	set_owner: function(_owner) {
		if(_owner !== this.options.owner)
		{
			egw.dataUnregisterUID(app.classes.calendar._daywise_cache_id+(this.options.date,this.options.owner),false,this);

			this.options.owner = _owner;
			this.div.attr('data-sortable-id', this.options.owner);

			// Register for updates on events for this day
			egw.dataRegisterUID(app.classes.calendar._daywise_cache_id(this.options.date,this.options.owner), function(event_ids) {
				var events = [];
				for(var i = 0; i < event_ids.length; i++)
				{
					var event = egw.dataGetUIDdata('calendar::'+event_ids[i]).data;
					if(event && event.date && event.date === this.options.date)
					{
						events.push(event);
					}
				}
				this._update_events(events);
			},this,this.getInstanceManager().execId,this.id);
		}
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
		var events = _events || this.getArrayMgr('content').getEntry(this.options.date) || [];

		// Remove extra events
		while(this._children.length > events.length)
		{
			var node = this._children[this._children.length-1];
			this.removeChild(node);
			node.free();
		}
		
		// Make sure children are in cronological order, or columns are backwards
		events.sort(function(a,b) {
			var start = new Date(a.start) - new Date(b.start);
			var end = new Date(a.end) - new Date(b.end);
			return a.whole_day ? -1 : (start ? start : end);
		});
		
		for(var c = this._children.length; c < events.length; c++)
		{
			// Create event
			var event = et2_createWidget('calendar-event',{
				id:events[c].app_id||events[c].id,
				value: events[c]
			},this);
			if(this.isInTree())
			{
				event.doLoadingFinished();
			}

			// Copy actions set in parent
			event._link_actions(this._parent._parent.options.actions||{});
		}

		// Seperate loop so column sorting finds all children in the right place
		for(var c = 0; c < events.length && c < this._children.length; c++)
		{
			this._children[c].set_value(events[c]);
		}
	},

	/**
	 * Sort a day's events into minimally overlapping columns
	 * 
	 * @returns {Array[]} Events sorted into columns
	 */
	_spread_events: function()
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

		// Make sure children are in cronological order, or columns are backwards
		this._children.sort(function(a,b) {
			var start = new Date(a.options.value.start) - new Date(b.options.value.start);
			var end = new Date(a.options.value.end) - new Date(b.options.value.end);
			return a.options.value.whole_day ? -1 : (start ? start : end);
		});
		
		for(var i = 0; i < this._children.length; i++)
		{
			var event = this._children[i].options.value || false;
			if(!event) continue;
			if(event.date && event.date != this.options.date)
			{
				// Still have a child event that has changed date (DnD)
				this._children[i].destroy();
				this.removeChild(this._children[i]);
				continue;
			}

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
			if(typeof eventCols[c] === 'undefined')
			{
				eventCols[c] = [];
			}
			eventCols[c].push(this._children[i]);
		}
		return eventCols;
	},

	/**
	 * Position the event according to it's time and how this widget is laid
	 * out.
	 *
	 * @param {undefined|Object|et2_calendar_event} event
	 */
	position_event: function(event)
	{
		// Sort events into minimally-overlapping columns
		var columns = this._spread_events();

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

			for(var i = 0; (columns[c].indexOf(event) >= 0 || !event) && i < columns[c].length; i++)
			{
				// Calculate vertical positioning
				var top = 0;
				var height = 0;
				if(columns[c][i].options.value.whole_day_on_top)
				{
					top = this.display_settings.titleHeight + this.display_settings.rowHeight*whole_day_counter++;
					height = this.display_settings.rowHeight;
				}
				else
				{
					top = this._time_to_position(columns[c][i].options.value.start_m,whole_day_counter);
					height = this._time_to_position(columns[c][i].options.value.end_m,whole_day_counter)-top;
				}

				// Position the event
				if(event && columns[c].indexOf(event) >= 0 || !event)
				{
					columns[c][i].div.css('top', top+'%');
					columns[c][i].div.css('height', height+'%');
					columns[c][i].div.css('left', left.toFixed(1)+'%');
					columns[c][i].div.css('width', width.toFixed(1)+'%');

				}
			}
			// Only wanted to position this event, leave the other columns alone
			if(event && columns[c].indexOf(event) >= 0)
			{
				return;
			}
		}
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
			pos = this.display_settings.titleHeight + (row_offset + (time / this.display_settings.wd_start )) * this.display_settings.rowHeight;
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

	// Resizable interface
	resize: function (_height)
	{
		this.display_settings.titleHeight = (this.title.height()/_height)*100;
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
			).sendRequest(true);
		}
		cache = egw.window.et2_calendar_daycol.holiday_cache[year];
		if(typeof cache.done == 'function')
		{
			// pending, wait for it
			cache.done(jQuery.proxy(function(response) {
				egw.window.et2_calendar_daycol.holiday_cache[this.year] = response.response[0].data||undefined;

				egw.window.setTimeout(jQuery.proxy(function() {
					// Make sure widget hasn't been destroyed while we wait
					if(typeof this.widget.free == 'undefined')
					{
						this.widget.day_class_holiday();
					}
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