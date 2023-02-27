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
	et2_core_valueWidget;
	/calendar/js/et2_widget_event.js;
*/

import {et2_createWidget, et2_register_widget, WidgetConfig} from "../../api/js/etemplate/et2_core_widget";
import {et2_valueWidget} from "../../api/js/etemplate/et2_core_valueWidget";
import {et2_calendar_timegrid} from "./et2_widget_timegrid";
import {et2_calendar_event} from "./et2_widget_event";
import {ClassWithAttributes} from "../../api/js/etemplate/et2_core_inheritance";
import {et2_IDetachedDOM, et2_IResizeable} from "../../api/js/etemplate/et2_core_interfaces";
import {et2_no_init} from "../../api/js/etemplate/et2_core_common";
import {egw} from "../../api/js/jsapi/egw_global";
import {egwIsMobile, sprintf} from "../../api/js/egw_action/egw_action_common.js";
import {CalendarApp} from "./app";
import {et2_calendar_view} from "./et2_widget_view";
import flatpickr from "flatpickr";
import {formatDate} from "../../api/js/etemplate/Et2Date/Et2Date";
import {ColorTranslator} from "colortranslator";

/**
 * Class which implements the "calendar-timegrid" XET-Tag for displaying a single days
 *
 * This widget is responsible mostly for positioning its events
 *
 */
export class et2_calendar_daycol extends et2_valueWidget implements et2_IDetachedDOM, et2_IResizeable
{

	static readonly _attributes: any = {
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
	};
	private div: JQuery;
	private header: JQuery;
	private title: JQuery;
	private event_wrapper: JQuery;
	private user_spacer: JQuery;
	private all_day: JQuery;

	private registeredUID: string = null;

	// Init to defaults, just in case - they will be updated from parent
	private display_settings: any = {
		wd_start:	60*9,
		wd_end:		60*17,
		granularity:	30,
		rowsToDisplay:	10,
		rowHeight:	20,
		// Percentage; not yet available
		titleHeight: 2.0
	};
	private date: Date;
	private class: string;

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_calendar_daycol._attributes, _child || {}));

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
			.addClass('et2_clickable et2_link')
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
	}

	doLoadingFinished( )
	{
		let result = super.doLoadingFinished();

		// Parent will have everything we need, just load it from there

		if(this.getParent() && this.getParent().options.owner)
		{
			this.set_owner(this.getParent().options.owner);
		}
		if(this.title.text() === '' && this.options.date &&
			this.getParent() && this.getParent().instanceOf(et2_calendar_timegrid))
		{
			// Forces an update
			const date = this.options.date;
			this.options.date = '';
			this.set_date(date);
		}

		return result;
	}

	destroy( )
	{
		super.destroy();
		this.div.off();
		this.header.off().remove();
		this.title.off();
		this.div = null;
		this.header = null;
		this.title = null;
		this.user_spacer = null;

		egw.dataUnregisterUID(this.registeredUID,null,this);
	}

	getDOMNode(sender)
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
	}

	/**
	 * Draw the individual divs for clicking to add an event
	 */
	_draw( )
	{
		// Remove any existing
		jQuery('.calendar_calAddEvent',this.div).remove();

		// Grab real values from parent
		if(this.getParent() && this.getParent().instanceOf(et2_calendar_timegrid))
		{
			this.display_settings.wd_start = 60*this.getParent().options.day_start;
			this.display_settings.wd_end = 60*this.getParent().options.day_end;
			this.display_settings.granularity = this.getParent().options.granularity;
			const header = this.getParent().dayHeader.children();

			// Figure out insert index
			let idx = 0;
			const siblings = this.getParent().getDOMNode(this).childNodes;
			while(idx < siblings.length && siblings[idx] != this.getDOMNode())
			{
				idx++;
			}
			// Stick header in the right place
			if(idx == 0)
			{
				this.getParent().dayHeader.prepend(this.header);
			}
			else if(header.length)
			{
				header.eq(Math.min(header.length,idx)-1).after(this.header);
			}
		}

		this.div.attr('data-date', this.options.date);
	}

	getDate() : Date
	{
		return this.date;
	}

	date_helper(value)
	{
		return (<et2_calendar_view>this.getParent()).date_helper(value);
	}

	/**
	 * Set the date
	 *
	 * @param {string|Date} _date New date
	 * @param {Object[]} events =false List of event data to be displayed, or false to
	 *	automatically fetch data from content array
	 * @param {boolean} force_redraw =false Redraw even if the date is the same.
	 *	Used for when new data is available.
	 */
	set_date(_date, events?, force_redraw?)
	{
		if(typeof events === 'undefined' || !events)
		{
			events = false;
		}
		if(typeof force_redraw === 'undefined' || !force_redraw)
		{
			force_redraw = false;
		}
		if(!this.getParent())
		{
			egw.debug('warn', 'Day col widget "' + this.id + '" is missing its parent.');
			return false;
		}

		this.date = (<et2_calendar_view>this.getParent()).date_helper(_date);

		// Keep internal option in Ymd format, it gets passed around in this format
		const new_date = formatDate(this.date, {dateFormat: "Ymd"});

		// Set label
		if(!this.options.label)
		{
			// Add timezone offset back in, or formatDate will lose those hours
			const formatDate = new Date(this.date.valueOf() + this.date.getTimezoneOffset() * 60 * 1000);

			this.title.html('<span class="long_date">' + egw.lang(flatpickr.formatDate(formatDate, 'l')) +
				'</span><span class="short_date">' + egw.lang(flatpickr.formatDate(formatDate, 'D')) + '</span>' +
				flatpickr.formatDate(formatDate, 'd'));
		}
		this.title
			.attr("data-date", new_date)
			.toggleClass('et2_label', !!this.options.label);
		this.header
			.attr('data-date',new_date)
			.attr('data-whole_day',true);

		// Avoid redrawing if date is the same
		if(new_date === this.options.date &&
			this.display_settings.granularity === this.getParent().options.granularity &&
			!force_redraw
		)
		{
			return;
		}

		const cache_id = CalendarApp._daywise_cache_id(new_date, this.options.owner);
		if(this.options.date && this.registeredUID &&
			cache_id !== this.registeredUID)
		{
			egw.dataUnregisterUID(this.registeredUID,null,this);

			// Remove existing events
			while(this._children.length > 0)
			{
				const node = this._children[this._children.length - 1];
				this.removeChild(node);
				node.destroy();
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
	}

	/**
	 * Set the owner of this day
	 *
	 * @param {number|number[]|string|string[]} _owner - Owner ID, which can
	 *	be an account ID, a resource ID (as defined in calendar_bo, not
	 *	necessarily an entry from the resource app), or a list containing a
	 *	combination of both.
	 */
	set_owner( _owner)
	{

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

		const cache_id = CalendarApp._daywise_cache_id(this.options.date, _owner);
		if(this.options.date && this.registeredUID &&
			cache_id !== this.registeredUID)
		{
			egw.dataUnregisterUID(this.registeredUID,null,this);
		}

		if(this.registeredUID !== cache_id)
		{
			this.registeredUID = cache_id;
			egw.dataRegisterUID(this.registeredUID, this._data_callback,this,this.getInstanceManager().execId,this.id);
		}
	}

	set_class( classnames)
	{
		this.header.removeClass(this.class);
		super.set_class(classnames);
		this.header.addClass(classnames);
	}

	/**
	 * Callback used when the daywise data changes
	 *
	 * Events should update themselves when their data changes, here we are
	 * dealing with a change in which events are displayed on this day.
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
			let event : any = egw.dataGetUIDdata('calendar::'+event_ids[i]);
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

		if(!this.div.is(":visible"))
		{
			// Not visible, defer the layout or it all winds up at the top
			// Cancel any existing listener & bind
			jQuery(this.getInstanceManager().DOMContainer.parentNode)
				.off('show.'+CalendarApp._daywise_cache_id(this.options.date, this.options.owner))
				.one('show.'+CalendarApp._daywise_cache_id(this.options.date, this.options.owner), function() {
					this._update_events(events)
				}.bind(this));
			return;
		}
		if(!this.getParent().disabled)
			this._update_events(events);
	}

	set_label( label)
	{
		this.options.label = label;
		this.title.text(label);
		this.title.toggleClass('et2_clickable et2_link',label === '');
	}
	set_left( left)
	{
		if(this.div)
		{
			this.div.css('left',left);
		}
	}
	set_width(width)
	{
		this.options.width = width;

		if(this.div)
		{
			this.div.outerWidth(this.options.width);
			this.header.outerWidth(this.options.width);
		}
	}

	/**
	 * Applies class for today, and any holidays for current day
	 */
	async day_class_holiday( )
	{
		this.title
			// Remove all special day classes
			.removeClass('calendar_calToday calendar_calBirthday calendar_calHoliday')
			// Except this one...
			.addClass("et2_clickable et2_link");
		this.title.attr('data-holiday','');

		// Set today class - note +1 when dealing with today, as months in JS are 0-11
		const today = new Date();
		today.setUTCMinutes(today.getUTCMinutes() - today.getTimezoneOffset());

		this.title.toggleClass("calendar_calToday", this.options.date === ''+today.getUTCFullYear()+
			sprintf("%02d",today.getUTCMonth()+1)+
			sprintf("%02d",today.getUTCDate())
		);

		// Holidays and birthdays
		let fetched_holidays = await this.egw().holidays(this.options.date.substring(0, 4));
		const holiday_list = [];
		let holiday_pref = (egw.preference('birthdays_as_events', 'calendar') || []);
		if(typeof holiday_pref === 'string')
		{
			holiday_pref = holiday_pref.split(',');
		}
		else
		{
			holiday_pref = jQuery.extend([], holiday_pref);
		}

		// Show holidays as events on mobile or by preference
		const holidays_as_events = egwIsMobile() || egw.preference('birthdays_as_events', 'calendar') === true ||
			holiday_pref.indexOf('holiday') >= 0;

		const birthdays_as_events = egwIsMobile() || holiday_pref.indexOf('birthday') >= 0;

		if(fetched_holidays && fetched_holidays[this.options.date])
		{
			fetched_holidays = fetched_holidays[this.options.date];
			for(let i = 0; i < fetched_holidays.length; i++)
			{
				if(typeof fetched_holidays[i]['birthyear'] !== 'undefined')
				{
					// Show birthdays as events on mobile or by preference
					if(birthdays_as_events && this.getWidgetById("event_" + escape(fetched_holidays[i].name)) == null)
					{
						// Create event
						var event = et2_createWidget('calendar-event', {
							id: 'event_' + fetched_holidays[i].name,
							value: {
								row_id: escape(fetched_holidays[i].name),
								title: fetched_holidays[i].name,
								whole_day: true,
								whole_day_on_top: true,
								start: (<et2_calendar_view>this.getParent()).date_helper(this.options.date),
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
						holiday_list.push(fetched_holidays[i]['name']);
					}
				}
				else
				{
					// Show holidays as events on mobile
					if(holidays_as_events && this.getWidgetById("event_" + escape(fetched_holidays[i].name)) == null)
					{
						// Create event
						var event = et2_createWidget('calendar-event', {
							id: 'event_' + fetched_holidays[i].name,
							value: {
								row_id: escape(fetched_holidays[i].name),
								title: fetched_holidays[i].name,
								whole_day: true,
								whole_day_on_top: true,
								start: (<et2_calendar_view>this.getParent()).date_helper(this.options.date),
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
						this.title.attr('data-holiday', fetched_holidays[i]['name']);

						//If the birthdays are already displayed as event, don't
						//show them in the caption
						if (!this.options.display_holiday_as_event)
						{
							holiday_list.push(fetched_holidays[i]['name']);
						}
					}
				}
			}
		}
		this.title.attr('title', holiday_list.join(', '));
	}

	/**
	 * Load the event data for this day and create event widgets for each.
	 *
	 * If event information is not provided, it will be pulled from the content array.
	 *
	 * @param {Object[]} [_events] Array of event information, one per event.
	 */
	_update_events(_events)
	{
		let c;
		const events = _events || this.getArrayMgr('content').getEntry(this.options.date) || [];

		// Remove extra events
		while(this._children.length > 0)
		{
			const node = this._children[this._children.length - 1];
			this.removeChild(node);
			node.destroy();
		}

		// Make sure children are in cronological order, or columns are backwards
		events.sort(function(a,b) {
			const start = new Date(a.start) - new Date(b.start);
			const end = new Date(a.end) - new Date(b.end);
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

		for(c = 0; c < events.length; c++)
		{
			// Create event
			var event = et2_createWidget('calendar-event',{
				id:'event_'+events[c].id,
				value: events[c]
			},this);
		}

		// Seperate loop so column sorting finds all children in the right place
		let child_length = this._children.length;
		for(c = 0; c < events.length && c < child_length; c++)
		{
			let event = this.getWidgetById('event_'+events[c].id);
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
	}

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
	_out_of_view()
	{
		// Reset
		this.header.children('.hiddenEventBefore').remove();
		this.div.children('.hiddenEventAfter').remove();
		this.event_wrapper.css('overflow','visible');
		this.all_day.removeClass('overflown');
		jQuery('.calendar_calEventBody', this.div).css({'padding-top': '','margin-top':''});

		const timegrid = <et2_calendar_timegrid>this.getParent();

		// elem is jquery div of event
		function isHidden(elem) {
			// Add an extra 5px top and bottom to include events just on the
			// edge of visibility
			const docViewTop = timegrid.scrolling.scrollTop() + 5,
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
			const visible = {
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
			const hidden = isHidden.call(this, event.div);
			const day = this;
			if(!hidden)
			{
				return;
			}
			// Only top is hidden, move label
			// Bottom hidden is fine
			if(hidden.hidden === 'top' && !hidden.completely && !event.div.hasClass('calendar_calEventSmall'))
			{
				const title_height = event.title.outerHeight();
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
	}

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
	_hidden_indicator(event, top, onclick)
	{
		let indicator = null;
		const day = this;
		const timegrid = this.getParent();
		const fixed_height = timegrid.div.hasClass('calendar_calTimeGridFixed');

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
			const count = parseInt(indicator.attr('data-hidden_count')) + 1;
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
		if(indicator !== null)
		{
			// Avoid white, which is hard to see
			// Use border-bottom-color, Firefox doesn't give a value with border-color
			const color = (new ColorTranslator(event.div.css('background-color'))).RGB !== 'rgb(255,255,255)' ?
				event.div.css('background-color') : event.div.css('border-bottom-color');
			if(color !== 'rgba(0, 0, 0, 0)')
			{
				indicator.css('border-color', color);
			}
		}
	}

	/**
	 * Sort a day's events into minimally overlapping columns
	 *
	 * @returns {Array[]} Events sorted into columns
	 */
	_spread_events()
	{
		if(!this.date) return [];

		let day_start = this.date.valueOf() / 1000;
		const dst_check = new Date(this.date);
		dst_check.setUTCHours(12);

		// if daylight saving is switched on or off, correct $day_start
		// gives correct times after 2am, times between 0am and 2am are wrong
		const daylight_diff = day_start + 12 * 60 * 60 - (dst_check.valueOf() / 1000);
		if(daylight_diff)
		{
			day_start -= daylight_diff;
		}

		const eventCols = [], col_ends = [];

		// Make sure children are in cronological order, or columns are backwards
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

				return (Math.abs(duration) > 360000) ? duration : (a.options.value.title.localeCompare(b.options.value.title));
			}
			else if (a.options.value.whole_day || b.options.value.whole_day)
			{
				return a.options.value.whole_day ? -1 : 1;
			}
			return start ? start : end;
		});

		for(let i = 0; i < this._children.length; i++)
		{
			const event = this._children[i].options.value || false;
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

			let c = 0;
			event['multiday'] = false;
			if(typeof event.start !== 'object')
			{
				event.start = new Date(event.start);
			}
			if(typeof event.end !== 'object')
			{
				event.end = new Date(event.end);
			}
			event['start_m'] = parseInt(String((event.start.valueOf() / 1000 - day_start) / 60), 10);
			if (event['start_m'] < 0)
			{
				event['start_m'] = 0;
				event['multiday'] = true;
			}
			event['end_m'] = parseInt(String((event.end.valueOf() / 1000 - day_start) / 60), 10);
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
	}

	/**
	 * Position the event according to its time and how this widget is laid
	 * out.
	 *
	 * @param {et2_calendar_event} [event] - Event to be updated
	 *	If a single event is not provided, all events are repositioned.
	 */
	position_event(event?)
	{
		// If hidden, skip it - it takes too long
		if(!this.div.is(':visible')) return;

		// Sort events into minimally-overlapping columns
		const columns = this._spread_events();

		for(let c = 0; c < columns.length; c++)
		{
			// Calculate horizontal positioning
			let left = Math.ceil(5 + (1.5 * 100 / (parseFloat(this.options.width) || 100)));
			let right = 2;
			if (columns.length !== 1)
			{
				right = !c ? 30 : 2;
				left += c * (100.0-left) / columns.length;
			}

			for(let i = 0; (columns[c].indexOf(event) >= 0 || !event) && i < columns[c].length; i++)
			{
				// Calculate vertical positioning
				let top = 0;
				let height = 0;
				// Position the event
				if(this.display_settings.granularity === 0)
				{
					if(this.all_day.has(columns[c][i].div).length)
					{
						columns[c][i].div.prependTo(this.event_wrapper);
					}
					else if(this.event_wrapper.has(columns[c][i].div).length == 0)
					{
						columns[c][i].div.appendTo(this.event_wrapper);
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
						this.getParent().resizeTimes();
					}
					continue;
				}
				else
				{
					if(this.all_day.has(columns[c][i].div).length)
					{
						columns[c][i].div.appendTo(this.event_wrapper);
						this.getParent().resizeTimes();
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
						const border_diff = columns[c][i].div.outerHeight() - columns[c][i].div.height();
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
	}

	/**
	 * Calculates the vertical position based on the time
	 *
	 * This calculation is a percentage from 00:00 to 23:59
	 *
	 * @param {int} time in minutes from midnight
	 * @return {float} position in percent
	 */
	_time_to_position(time)
	{
		let pos = 0.0;

		// 24h
		pos = ((time / 60) / 24) * 100;

		return pos.toFixed(1);
	}

	attachToDOM()
	{
		let result = super.attachToDOM();

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

		return result;
	}

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
	click(_ev)
	{
		if(this.getParent().options.readonly ) return;

		// Drag to create in progress
		if(this.getParent().drag_create.start !== null) return;

		// Click on the title
		if (jQuery(_ev.target).hasClass('calendar_calAddEvent'))
		{
			if(this.header.has(_ev.target).length == 0 && !_ev.target.dataset.whole_day)
			{
				// Default handler to open a new event at the selected time
				var options = {
					date: _ev.target.dataset.date || this.options.date,
					hour: _ev.target.dataset.hour || this.getParent().options.day_start,
					minute: _ev.target.dataset.minute || 0,
					owner: this.options.owner
				};
				app.calendar.add(options);
				return false;
			}
			// Header, all day non-blocking
			else if (this.header.has(_ev.target).length && !jQuery('.hiddenEventBefore',this.header).has(_ev.target).length ||
				this.header.is(_ev.target)
			)
			{
				// Click on the header, but not the title.  That's an all-day non-blocking
				const end = this.date.getFullYear() + '-' + (this.date.getUTCMonth() + 1) + '-' + this.date.getUTCDate() + 'T23:59';
				let options = {
					start: this.date.toJSON(),
					end: end,
					non_blocking: true,
					owner: this.options.owner
				};
				app.calendar.add(options);
				return false;
			}
		}
		// Day label
		else if(this.title.is(_ev.target) || this.title.has(_ev.target).length)
		{
			app.calendar.update_state({view: 'day',date: this.date.toJSON()});
			return false;
		}

	}

	/**
	 * Code for implementing et2_IDetachedDOM
	 *
	 * @param {array} _attrs array to add further attributes to
	 */
	getDetachedAttributes( _attrs)
	{

	}

	getDetachedNodes() {
		return [this.getDOMNode(this)];
	}

	setDetachedAttributes( _nodes, _values)
	{

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

		if(this.display_settings.granularity !== this.getParent().options.granularity)
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
}
et2_register_widget(et2_calendar_daycol, ["calendar-daycol"]);