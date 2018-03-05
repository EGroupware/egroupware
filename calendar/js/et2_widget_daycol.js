/*
 * Egroupware
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */


/*egw:uses
	et2_core_valueWidget;
	/calendar/js/et2_widget_event.js;
*/

/**
 * Class which implements the "calendar-timegrid" XET-Tag for displaying a single days
 *
 * This widget is responsible mostly for positioning its events
 *
 * @augments et2_valueWidget
 */
var et2_calendar_daycol = (function(){ "use strict"; return et2_valueWidget.extend([et2_IDetachedDOM, et2_IResizeable],
{

	attributes: {
		date: {
			name: "Date",
			type: "any",
			description: "What date is this daycol for.  YYYYMMDD or Date",
			default: et2_no_init
		},
		owner: {
			name: "Owner",
			type: "any", // Integer, string, or array of either
			default: et2_no_init,
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
		this.div = jQuery(document.createElement("div"))
			.addClass("calendar_calDayCol")
			.css('width',this.options.width)
			.css('left', this.options.left);
		this.header = jQuery(document.createElement('div'))
			.addClass("calendar_calDayColHeader")
			.css('width',this.options.width)
			.css('left', this.options.left);
		this.title = jQuery(document.createElement('div'))
			.appendTo(this.header);
		this.user_spacer = jQuery(document.createElement('div'))
			.addClass("calendar_calDayColHeader_spacer")
			.appendTo(this.header);
		this.all_day = jQuery(document.createElement('div'))
			.addClass("calendar_calDayColAllDay")
			.css('max-height', (egw.preference('limit_all_day_lines', 'calendar') || 3 ) * 1.4 + 'em')
			.appendTo(this.header);
		this.event_wrapper = jQuery(document.createElement('div'))
			.addClass("event_wrapper")
			.appendTo(this.div);

		this.setDOMNode(this.div[0]);

		// Used for its date calculations - note this is a datetime, parent
		// uses just a date
		this.date_helper = et2_createWidget('date-time',{},null);
		this.date_helper.loadingFinished();

		// Init to defaults, just in case - they will be updated from parent
		this.display_settings = {
			wd_start:	60*9,
			wd_end:		60*17,
			granularity:	30,
			rowsToDisplay:	10,
			rowHeight:	20,
			// Percentage; not yet available
			titleHeight: 2.0
		};

		this.registeredUID = null;
	},

	doLoadingFinished: function() {
		this._super.apply(this, arguments);

		// Parent will have everything we need, just load it from there

		if(this._parent && this._parent.options.owner)
		{
			this.set_owner(this._parent.options.owner);
		}
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
		this.div.off();
		this.header.off().remove();
		this.title.off();
		this.div = null;
		this.header = null;
		this.title = null;
		this.user_spacer = null;

		// date_helper has no parent, so we must explicitly remove it
		this.date_helper.destroy();
		this.date_helper = null;

		egw.dataUnregisterUID(this.registeredUID,false,this);
	},

	getDOMNode: function(sender)
	{
		if(!sender || sender === this) return this.div[0];
		if(sender.instanceOf && sender.instanceOf(et2_calendar_event))
		{
			if(this.display_settings.granularity === 0)
			{
				return this.event_wrapper[0];
			}
			if(sender.options.value.whole_day_on_top ||
				sender.options.value.whole_day && sender.options.value.non_blocking === true)
			{
				return this.all_day[0];
			}
			return this.div[0];
		}
	},

	/**
	 * Draw the individual divs for clicking to add an event
	 */
	_draw: function() {
		// Remove any existing
		jQuery('.calendar_calAddEvent',this.div).remove();

		// Grab real values from parent
		if(this._parent && this._parent.instanceOf(et2_calendar_timegrid))
		{
			this.display_settings.wd_start = 60*this._parent.options.day_start;
			this.display_settings.wd_end = 60*this._parent.options.day_end;
			this.display_settings.granularity = this._parent.options.granularity;
			var header = this._parent.dayHeader.children();

			// Figure out insert index
			var idx = 0;
			var siblings = this._parent.getDOMNode(this).childNodes;
			while(idx < siblings.length && siblings[idx] != this.getDOMNode())
			{
				idx++;
			}
			// Stick header in the right place
			if(idx == 0)
			{
				this._parent.dayHeader.prepend(this.header);
			}
			else if(header.length)
			{
				header.eq(Math.min(header.length,idx)-1).after(this.header);
			}
		}

		this.div.attr('data-date', this.options.date);
	},

	/**
	 * Set the date
	 *
	 * @param {string|Date} _date New date
	 * @param {Object[]} events =false List of event data to be displayed, or false to
	 *	automatically fetch data from content array
	 * @param {boolean} force_redraw =false Redraw even if the date is the same.
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
			// month then day.  Use a string to avoid browser timezone.
			this._parent.date_helper.set_value(_date.substring(0,4)+'-'+(_date.substring(4,6))+'-'+_date.substring(6,8)+'T00:00:00Z');
		}

		this.date = new Date(this._parent.date_helper.getValue());

		// Keep internal option in Ymd format, it gets passed around in this format
		var new_date = ""+this._parent.date_helper.get_year()+
			sprintf("%02d",this._parent.date_helper.get_month())+
			sprintf("%02d",this._parent.date_helper.get_date());

		// Set label
		if(!this.options.label)
		{
			// Add timezone offset back in, or formatDate will lose those hours
			var formatDate = new Date(this.date.valueOf() + this.date.getTimezoneOffset() * 60 * 1000);

			this.title.html('<span class="long_date">'+jQuery.datepicker.formatDate('DD',formatDate)+
				'</span><span class="short_date">'+jQuery.datepicker.formatDate('D',formatDate)+'</span>'+
				jQuery.datepicker.formatDate('d',formatDate));
		}
		this.title
			.attr("data-date", new_date);
		this.header
			.attr('data-date',new_date)
			.attr('data-whole_day',true);

		// Avoid redrawing if date is the same
		if(new_date === this.options.date &&
			this.display_settings.granularity === this._parent.options.granularity &&
			!force_redraw
		)
		{
			return;
		}

		var cache_id = app.classes.calendar._daywise_cache_id(new_date,this.options.owner);
		if(this.options.date && this.registeredUID &&
			cache_id !== this.registeredUID)
		{
			egw.dataUnregisterUID(this.registeredUID,false,this);

			// Remove existing events
			while(this._children.length > 0)
			{
				var node = this._children[this._children.length-1];
				this.removeChild(node);
				node.free();
			}
		}

		this.options.date = new_date;

		// Set holiday and today classes
		this.day_class_holiday();

		// Update all the little boxes
		this._draw();


		// Register for updates on events for this day
		if(this.registeredUID !== cache_id)
		{
			this.registeredUID = cache_id;
			egw.dataRegisterUID(this.registeredUID, this._data_callback,this,this.getInstanceManager().execId,this.id);
		}
	},

	/**
	 * Set the owner of this day
	 *
	 * @param {number|number[]|string|string[]} _owner - Owner ID, which can
	 *	be an account ID, a resource ID (as defined in calendar_bo, not
	 *	necessarily an entry from the resource app), or a list containing a
	 *	combination of both.
	 */
	set_owner: function(_owner) {

		this.title
			.attr("data-owner", _owner);
		this.header.attr('data-owner',_owner);
		this.div.attr('data-owner',_owner);

		// Simple comparison, both numbers
		if(_owner === this.options.owner) return;

		// More complicated comparison, one or the other is an array
		if((typeof _owner == 'object' || typeof this.options.owner == 'object') &&
			_owner.toString() == this.options.owner.toString())
		{
			return;
		}

		this.options.owner = typeof _owner !== 'object' ? [_owner] : _owner;

		var cache_id = app.classes.calendar._daywise_cache_id(this.options.date,_owner);
		if(this.options.date && this.registeredUID &&
			cache_id !== this.registeredUID)
		{
			egw.dataUnregisterUID(this.registeredUID,false,this);
		}

		if(this.registeredUID !== cache_id)
		{
			this.registeredUID = cache_id;
			egw.dataRegisterUID(this.registeredUID, this._data_callback,this,this.getInstanceManager().execId,this.id);
		}
	},

	set_class: function(classnames) {
		this.header.removeClass(this.class);
		this._super.apply(this, arguments);
		this.header.addClass(classnames);
	},

	/**
	 * Callback used when the daywise data changes
	 *
	 * Events should update themselves when their data changes, here we are
	 * dealing with a change in which events are displayed on this day.
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
			if(event && event.date && et2_calendar_event.owner_check(event, this) && (
				event.date === this.options.date ||
				// Accept multi-day events
				new Date(event.start) <= this.date //&& new Date(event.end) >= this.date
			))
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
			this._update_events(events);
	},

	set_label: function(label) {
		this.options.label = label;
		this.title.text(label);
		this.title.toggleClass('et2_clickable et2_link',label === '');
	},
	set_left: function(left) {
		if(this.div)
		{
			this.div.css('left',left);
		}
	},
	set_width: function(width) {
		this.options.width = width;

		if(this.div)
		{
			this.div.outerWidth(this.options.width);
			this.header.outerWidth(this.options.width);
		}
	},

	/**
	 * Applies class for today, and any holidays for current day
	 */
	day_class_holiday: function() {
		this.title
			// Remove all special day classes
			.removeClass('calendar_calToday calendar_calBirthday calendar_calHoliday')
			// Except this one...
			.addClass("et2_clickable et2_link");
		this.title.attr('data-holiday','');

		// Set today class - note +1 when dealing with today, as months in JS are 0-11
		var today = new Date();
		today.setUTCMinutes(today.getUTCMinutes() - today.getTimezoneOffset());

		this.title.toggleClass("calendar_calToday", this.options.date === ''+today.getUTCFullYear()+
			sprintf("%02d",today.getUTCMonth()+1)+
			sprintf("%02d",today.getUTCDate())
		);

		// Holidays and birthdays
		var holidays = et2_calendar_view.get_holidays(this,this.options.date.substring(0,4));
		var holiday_list = [];
		var holiday_pref = (egw.preference('birthdays_as_events','calendar')||'').split(',');
debugger;
		// Show holidays as events on mobile or by preference
		var holidays_as_events = egwIsMobile() || egw.preference('birthdays_as_events','calendar') === true ||
				holiday_pref.indexOf('holiday') >= 0;

		var birthdays_as_events = egwIsMobile() || holiday_pref.indexOf('birthday') >= 0;

		if(holidays && holidays[this.options.date])
		{
			holidays = holidays[this.options.date];
			for(var i = 0; i < holidays.length; i++)
			{
				if (typeof holidays[i]['birthyear'] !== 'undefined')
				{
					// Show birthdays as events on mobile or by preference
					if(birthdays_as_events)
					{
						// Create event
						this._parent.date_helper.set_value(this.options.date.substring(0,4)+'-'+
							(this.options.date.substring(4,6))+'-'+this.options.date.substring(6,8)+
							'T00:00:00Z');
						var event = et2_createWidget('calendar-event',{
							id:'event_'+holidays[i].name,
							value: {
								title: holidays[i].name,
								whole_day: true,
								whole_day_on_top: true,
								start: new Date(this._parent.date_helper.get_value()),
								end: this.options.date,
								owner: this.options.owner,
								participants: this.options.owner,
								app: 'calendar',
								class: 'calendar_calBirthday'
							},
							readonly: true,
							class: 'calendar_calBirthday'
						},this);
						event.doLoadingFinished();
						event._update();
					}
					if (!egwIsMobile())
					{
						//If the birthdays are already displayed as event, don't
						//show them in the caption
						this.title.addClass('calendar_calBirthday');
						holiday_list.push(holidays[i]['name']);
					}
				}
				else
				{
					// Show holidays as events on mobile
					if(holidays_as_events)
					{
						// Create event
						this._parent.date_helper.set_value(this.options.date.substring(0,4)+'-'+
							(this.options.date.substring(4,6))+'-'+this.options.date.substring(6,8)+
							'T00:00:00Z');
						var event = et2_createWidget('calendar-event',{
							id:'event_'+holidays[i].name,
							value: {
								title: holidays[i].name,
								whole_day: true,
								whole_day_on_top: true,
								start: new Date(this._parent.date_helper.get_value()),
								end: this.options.date,
								owner: this.options.owner,
								participants: this.options.owner,
								app: 'calendar',
								class: 'calendar_calHoliday'
							},
							readonly: true,
							class: 'calendar_calHoliday'
						},this);
						event.doLoadingFinished();
						event._update();
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
		while(this._children.length > 0)
		{
			var node = this._children[this._children.length-1];
			this.removeChild(node);
			node.free();
		}

		// Make sure children are in cronological order, or columns are backwards
		events.sort(function(a,b) {
			var start = new Date(a.start) - new Date(b.start);
			var end = new Date(a.end) - new Date(b.end);
			// Whole day events sorted by ID, normal events by start / end time
			if(a.whole_day && b.whole_day)
			{
				return (a.app_id - b.app_id);
			}
			else if (a.whole_day || b.whole_day)
			{
				return a.whole_day ? -1 : 1;
			}
			return start ? start : end;
		});

		for(var c = 0; c < events.length; c++)
		{
			// Create event
			var event = et2_createWidget('calendar-event',{
				id:'event_'+events[c].id,
				value: events[c]
			},this);
		}

		// Seperate loop so column sorting finds all children in the right place
		for(var c = 0; c < events.length && c < this._children.length; c++)
		{
			var event = this.getWidgetById('event_'+events[c].id);
			if(!event) continue;
			if(this.isInTree())
			{
				event.doLoadingFinished();
			}
		}


		// Show holidays as events on mobile or by preference
		if(egwIsMobile() || egw.preference('birthdays_as_events','calendar'))
		{
			this.day_class_holiday();
		}

		// Apply styles to hidden events
		this._out_of_view();
	},

	/**
	 * Apply styles for out-of-view and partially hidden events
	 *
	 * There are 3 different states or modes of display:
	 *
	 * - 'Normal' - When showing events positioned by time, the indicator is just
	 *	a bar colored by the last category color.  On hover it shows either the
	 *	title of a single event or "x event(s)" if more than one are hidden.
	 *	Clicking adjusts the current view to show the earliest / latest hidden
	 *	event
	 *
	 * - Fixed - When showing events positioned by time but in a fixed-height
	 *  week (not auto-sized to fit screen) the indicator is the same as sized.
	 *  On hover it shows the titles of the hidden events, clicking changes
	 *  the view to the selected day.
	 *
	 * - GridList - When showing just a list, the indicator shows "x event(s)",
	 *	and on hover shows the category color, title & time.  Clicking changes
	 *	the view to the selected day, and opens the event for editing.
	 */
	_out_of_view: function()
	{
		// Reset
		this.header.children('.hiddenEventBefore').remove();
		this.div.children('.hiddenEventAfter').remove();
		this.event_wrapper.css('overflow','visible');
		this.all_day.removeClass('overflown');
		jQuery('.calendar_calEventBody', this.div).css({'padding-top': '','margin-top':''});

		var timegrid = this._parent;

		// elem is jquery div of event
		function isHidden(elem) {
			// Add an extra 5px top and bottom to include events just on the
			// edge of visibility
			var docViewTop = timegrid.scrolling.scrollTop() + 5,
			docViewBottom = docViewTop + (
				this.display_settings.granularity === 0 ?
				this.event_wrapper.height() :
				timegrid.scrolling.height() - 10
			),
			elemTop = elem.position().top,
			elemBottom = elemTop + elem.outerHeight(true);
			if((elemBottom <= docViewBottom) && (elemTop >= docViewTop))
			{
				// Entirely visible
				return false;
			}
			var visible = {
				hidden: elemTop > docViewTop ? 'bottom' : 'top',
				completely: false
			};
			visible.completely = visible.hidden == 'top' ? elemBottom < docViewTop : elemTop > docViewBottom;
			return visible;
		}

		// In gridlist view, we can quickly check if we need it at all
		if(this.display_settings.granularity === 0 && this._children.length)
		{
			jQuery('div.calendar_calEvent',this.div).show(0);
			if(Math.ceil(this.div.height() / this._children[0].div.height()) > this._children.length)
			{
				return;
			}
		}
		// Check all day overflow
		this.all_day.toggleClass('overflown',
			this.all_day[0].scrollHeight - this.all_day.innerHeight() > 5
		);

		// Check each event
		this.iterateOver(function(event) {
			// Skip whole day events and events missing value
			if(this.display_settings.granularity && (
				(!event.options || !event.options.value || event.options.value.whole_day_on_top))
			)
			{
				return;
			}
			// Reset
			event.title.css({'top':'','background-color':''});
			event.body.css({'padding-top':'','margin-top':''});
			var hidden = isHidden.call(this,event.div);
			var day = this;
			if(!hidden)
			{
				return;
			}
			// Only top is hidden, move label
			// Bottom hidden is fine
			if(hidden.hidden === 'top' && !hidden.completely && !event.div.hasClass('calendar_calEventSmall'))
			{
				var title_height = event.title.outerHeight();
				event.title.css({
					'top': timegrid.scrolling.scrollTop() - event.div.position().top,
					'background-color': 'transparent'
				});
				event.body.css({
					'padding-top': timegrid.scrolling.scrollTop() - event.div.position().top + title_height,
					'margin-top' : -title_height
				});
			}
			// Too many in gridlist view, show indicator
			else if (this.display_settings.granularity === 0 && hidden)
			{
				if(jQuery('.hiddenEventAfter',this.div).length == 0)
				{
					this.event_wrapper.css('overflow','hidden');
				}
				this._hidden_indicator(event, false, function() {
					app.calendar.update_state({view: 'day', date: day.date});
				});
				// Avoid partially visible events
				// We need to hide all, or the next row will be visible
				event.div.hide(0);
			}
			// Completely out of view, show indicator
			else if (hidden.completely)
			{
				this._hidden_indicator(event, hidden.hidden == 'top',false);
			}
		}, this, et2_calendar_event);
	},

	/**
	 * Show an indicator that there are hidden events
	 *
	 * The indicator works 3 different ways, depending on if the day can be
	 * scrolled, is fixed, or if in gridview.
	 *
	 * @see _out_of_view()
	 *
	 * @param {et2_calendar_event} event Event we're creating the indicator for
	 * @param {boolean} top Events hidden at the top (true) or bottom (false)
	 * @param {function} [onclick] Callback for when user clicks on the indicator
	 */
	_hidden_indicator: function _hidden_indicator(event, top, onclick)
	{
		var indicator = '';
		var day = this;
		var timegrid = this._parent;
		var fixed_height = timegrid.div.hasClass('calendar_calTimeGridFixed');

		// Event is before the displayed times
		if(top)
		{
			// Create if not already there
			if(jQuery('.hiddenEventBefore',this.header).length === 0)
			{
				indicator = jQuery('<div class="hiddenEventBefore"></div>')
					.appendTo(this.header)
					.attr('data-hidden_count', 1);
				if(!fixed_height)
				{
					indicator
						.text(event.options.value.title)
						.on('click', typeof onclick === 'function' ? onclick : function() {
								jQuery('.calendar_calEvent',day.div).first()[0].scrollIntoView();
							return false;
						});
				}
			}
			else
			{
				indicator = jQuery('.hiddenEventBefore',this.header);
				indicator.attr('data-hidden_count', parseInt(indicator.attr('data-hidden_count')) + 1);

				if (!fixed_height)
				{
					indicator.text(day.egw().lang('%1 event(s) %2',indicator.attr('data-hidden_count'),''));
				}
			}
		}
		// Event is after displayed times
		else
		{
			indicator = jQuery('.hiddenEventAfter',this.div);
			// Create if not already there
			if(indicator.length === 0)
			{
				indicator = jQuery('<div class="hiddenEventAfter"></div>')
					.attr('data-hidden_count', 0)
					.appendTo(this.div);
				if(!fixed_height)
				{
					indicator
						.on('click', typeof onclick === 'function' ? onclick : function() {
							jQuery('.calendar_calEvent',day.div).last()[0].scrollIntoView(false);
							// Better re-run this to clean up
							day._out_of_view();
							return false;
						});
				}
				else
				{
					indicator
						.on('mouseover', function() {
							indicator.css({
								'height': (indicator.attr('data-hidden_count')*1.2) + 'em',
								'margin-top': -(indicator.attr('data-hidden_count')*1.2) + 'em'
							});
						})
						.on('mouseout', function() {
							indicator.css({
								'height': '',
								'margin-top': ''
							});
						});
				}
			}
			var count = parseInt(indicator.attr('data-hidden_count')) + 1;
			indicator.attr('data-hidden_count', count);
			if(this.display_settings.granularity === 0)
			{
				indicator.append(event.div.clone());
				indicator.attr('data-hidden_label', day.egw().lang('%1 event(s) %2',indicator.attr('data-hidden_count'),''));
			}
			else if (!fixed_height)
			{
				indicator.text(day.egw().lang('%1 event(s) %2',indicator.attr('data-hidden_count'),''));
			}
			indicator.css('top',timegrid.scrolling.height() + timegrid.scrolling.scrollTop()-indicator.innerHeight());
		}
		// Show different stuff for fixed height
		if(fixed_height)
		{
			indicator
				.append("<div id='"+event.dom_id +
					"' data-id='"+event.options.value.id+"'>"+
						event.options.value.title+
					"</div>"
				);
		}
		// Match color to the event
		if(indicator !== '')
		{
			// Avoid white, which is hard to see
			// Use border-bottom-color, Firefox doesn't give a value with border-color
			var color = jQuery.Color(event.div.css('background-color')).toString() !== jQuery.Color('white').toString() ?
				event.div.css('background-color') : event.div.css('border-bottom-color');
			if(color !== 'rgba(0, 0, 0, 0)')
			{
				indicator.css('border-color', color);
			}
		}
	},

	/**
	 * Sort a day's events into minimally overlapping columns
	 *
	 * @returns {Array[]} Events sorted into columns
	 */
	_spread_events: function()
	{
		if(!this.date) return [];

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

		for(var i = 0; i < this._children.length; i++)
		{
			var event = this._children[i].options.value || false;
			if(!event) continue;
			if(event.date && event.date != this.options.date &&
				// Multi-day events date may be different
				(new Date(event.start) >= this.date || new Date(event.end) < this.date )
			)
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
				event.start = new Date(event.start);
			}
			if(typeof event.end !== 'object')
			{
				event.end = new Date(event.end);
			}
			event['start_m'] = (event.start.valueOf()/1000 - day_start) / 60
			if (event['start_m'] < 0)
			{
				event['start_m'] = 0;
				event['multiday'] = true;
			}
			event['end_m'] = (event.end.valueOf()/1000 - day_start) / 60;
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
	 * Position the event according to its time and how this widget is laid
	 * out.
	 *
	 * @param {et2_calendar_event} [event] - Event to be updated
	 *	If a single event is not provided, all events are repositioned.
	 */
	position_event: function(event)
	{
		// If hidden, skip it - it takes too long
		if(!this.div.is(':visible')) return;

		// Sort events into minimally-overlapping columns
		var columns = this._spread_events();

		for(var c = 0; c < columns.length; c++)
		{
			// Calculate horizontal positioning
			var left = Math.ceil(5 + (1.5 * 100 / (parseFloat(this.options.width) || 100)));
			var right = 2;
			if (columns.length !== 1)
			{
				right = !c ? 30 : 2;
				left += c * (100.0-left) / columns.length;
			}

			for(var i = 0; (columns[c].indexOf(event) >= 0 || !event) && i < columns[c].length; i++)
			{
				// Calculate vertical positioning
				var top = 0;
				var height = 0;
				// Position the event
				if(this.display_settings.granularity === 0)
				{
					if(this.all_day.has(columns[c][i].div).length)
					{
						columns[c][i].div.prependTo(this.event_wrapper);
					}
					columns[c][i].div.css('top', '');
					columns[c][i].div.css('height', '');
					columns[c][i].div.css('left', '');
					columns[c][i].div.css('right', '');
					// Strip out of view padding
					columns[c][i].body.css('padding-top','');
					continue;
				}
				if(columns[c][i].options.value.whole_day_on_top)
				{
					if(!this.all_day.has(columns[c][i].div).length)
					{
						columns[c][i].div.css('top', '');
						columns[c][i].div.css('height','');
						columns[c][i].div.css('left', '');
						columns[c][i].div.css('right', '');
						columns[c][i].body.css('padding-top','');
						columns[c][i].div
							.appendTo(this.all_day);
						this._parent.resizeTimes();
					}
					continue;
				}
				else
				{
					if(this.all_day.has(columns[c][i].div).length)
					{
						columns[c][i].div.appendTo(this.event_wrapper);
						this._parent.resizeTimes();
					}
					top = this._time_to_position(columns[c][i].options.value.start_m);
					height = this._time_to_position(columns[c][i].options.value.end_m)-top;
				}

				// Position the event
				if(event && columns[c].indexOf(event) >= 0 || !event)
				{
					columns[c][i].div.css('top', top+'%');
					columns[c][i].div.css('height', height+'%');
					// Remove spacing from border, but only if visible or the height will be wrong
					if(columns[c][i].div.is(':visible'))
					{
						var border_diff = columns[c][i].div.outerHeight() - columns[c][i].div.height();
						columns[c][i].div.css('height','calc('+height+'% - ' +border_diff+')');
					}
					// This gives the wrong height
					//columns[c][i].div.outerHeight(height+'%');
					columns[c][i].div.css('left', left.toFixed(1)+'%');
					columns[c][i].div.css('right', right.toFixed(1)+'%');
					columns[c][i].div.css('z-index',parseInt(20)+c);
					columns[c][i]._small_size();
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
	 * This calculation is a percentage from 00:00 to 23:59
	 *
	 * @param {int} time in minutes from midnight
	 * @return {float} position in percent
	 */
	_time_to_position: function(time)
	{
		var pos = 0.0;

		// 24h
		pos = ((time / 60) / 24) * 100;

		pos = pos.toFixed(1);

		return pos;
	},

	attachToDOM: function()
	{
		this._super.apply(this, arguments);

		// Remove the binding for the click handler, unless there's something
		// custom here.
		if (!this.onclick)
		{
			jQuery(this.node).off("click");
		}
		// But we do want to listen to certain clicks, and handle them internally
		jQuery(this.node).on('click.et2_daycol',
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
	click: function(_ev)
	{
		if(this._parent.options.readonly ) return;

		// Drag to create in progress
		if(this._parent.drag_create.start !== null) return;

		// Click on the title
		if (jQuery(_ev.target).hasClass('calendar_calAddEvent'))
		{
			if(this.header.has(_ev.target).length == 0 && !_ev.target.dataset.whole_day)
			{
				// Default handler to open a new event at the selected time
				var options = {
					date: _ev.target.dataset.date || this.options.date,
					hour: _ev.target.dataset.hour || this._parent.options.day_start,
					minute: _ev.target.dataset.minute || 0,
					owner: this.options.owner
				};
				this.egw().open(null, 'calendar', 'add', options, '_blank');
				return false;
			}
			// Header, all day non-blocking
			else if (this.header.has(_ev.target).length && !jQuery('.hiddenEventBefore',this.header).has(_ev.target).length ||
				this.header.is(_ev.target)
			)
			{
				// Click on the header, but not the title.  That's an all-day non-blocking
				var end = this.date.getFullYear() + '-' + (this.date.getUTCMonth()+1) + '-' + this.date.getUTCDate() + 'T23:59';
				var options = {
					start: this.date.toJSON(),
					end: end,
					non_blocking: true,
					owner: this.options.owner
				}
				this.egw().open(null, 'calendar', 'add', options, '_blank');
				return false;
			}
		}
		// Day label
		else if(this.title.is(_ev.target) || this.title.has(_ev.target).length)
		{
			app.calendar.update_state({view: 'day',date: this.date.toJSON()});
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

		if(this.display_settings.granularity !== this._parent.options.granularity)
		{
			// Layout has changed
			this._draw();

			// Resize & position all events
			this.position_event();
		}
		else
		{
			// Don't need to resize & reposition, just clear some stuff
			// to reset for _out_of_view()
			this.iterateOver(function(widget) {
				widget._small_size();
			}, this, et2_calendar_event);
		}
		this._out_of_view();
	}
});}).call(this);

et2_register_widget(et2_calendar_daycol, ["calendar-daycol"]);
