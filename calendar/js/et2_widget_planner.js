/* 
 * Egroupware Calendar timegrid
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */


"use strict";

/*egw:uses
	/etemplate/js/et2_core_valueWidget;
	/calendar/js/et2_widget_planner_row.js;
	/calendar/js/et2_widget_event.js;
*/

/**
 * Class which implements the "calendar-planner" XET-Tag for displaying a longer
 * ( > 10 days) span of time
 *
 * @augments et2_valueWidget
 * @class
 */
var et2_calendar_planner = et2_valueWidget.extend([et2_IDetachedDOM, et2_IResizeable],
{
	createNamespace: true,
	
	attributes: {
		start_date: {
			name: "Start date",
			type: "any"
		},
		end_date: {
			name: "End date",
			type: "any"
		},
		group_by: {
			name: "Group by",
			type: "string", // or category ID
			default: "0",
			description: "Display planner by 'user', 'month', or the given category"
		},
		owner: {
			name: "Owner",
			type: "any", // Integer, or array of integers
			default: 0,
			description: "Account ID number of the calendar owner, if not the current user"
		},
		filter: {
			name: "Filter",
			type: "string",
			default: '',
			description: 'A filter that is used to select events.  It is passed along when events are queried.'
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
	},

	/**
	 * Constructor
	 *
	 * @memberOf et2_calendar_planner
	 * @constructor
	 */
	init: function() {
		this._super.apply(this, arguments);

		// Main container
		this.div = $j(document.createElement("div"))
			.addClass("calendar_plannerWidget");

		// Header
		this.gridHeader = $j(document.createElement("div"))
			.addClass("calendar_plannerHeader")
			.appendTo(this.div);
		this.headerTitle = $j(document.createElement("div"))
			.addClass("calendar_plannerHeaderTitle")
			.appendTo(this.gridHeader);
		this.headers = $j(document.createElement("div"))
			.addClass("calendar_plannerHeaderRows")
			.appendTo(this.gridHeader);

		this.rows = $j(document.createElement("div"))
			.appendTo(this.div);
		
		// Used for its date calculations
		this.date_helper = et2_createWidget('date-time',{},null);
		this.date_helper.loadingFinished();

		this.value = [];

		// Update timer, to avoid redrawing twice when changing start & end date
		this.update_timer = null;

		this.setDOMNode(this.div[0]);
	},

	destroy: function() {
		this._super.apply(this, arguments);
		this.div.off();

		// date_helper has no parent, so we must explicitly remove it
		this.date_helper.destroy();
		this.date_helper = null;
		
		// Stop the invalidate timer
		if(this.update_timer)
		{
			window.clearTimeout(this.update_timer);
		}
	},

	doLoadingFinished: function() {
		this._super.apply(this, arguments);
		
		// Don't bother to draw anything if there's no date yet
		if(this.options.start_date)
		{
			this._drawGrid();
		}

		// Actions may be set on a parent, so we need to explicitly get in here
		// and get ours
		this._link_actions(this.options.actions || this._parent.options.actions || []);

		return true;
	},

	/**
	 * These handle the differences between the different group types.
	 * They provide the different titles, labels and grouping
	 */
	groupers: {
		// Group by user has one row for each user
		user:
		{
			// Title in top left corner
			title: function() { return this.egw().lang('User');},
			// Column headers
			headers: function() {
				var start = new Date(this.options.start_date);
				var end = new Date(this.options.end_date);
				var start_date = new Date(start.getUTCFullYear(), start.getUTCMonth(),start.getUTCDate());
				var end_date = new Date(end.getUTCFullYear(), end.getUTCMonth(),end.getUTCDate());
				var day_count = Math.round((end_date - start_date) /(1000*3600*24))+1;
				if(day_count >= 28)
				{
					this.headers.append(this._header_months(start, day_count));
				}
				if(day_count >= 5)
				{
					this.headers.append(this._header_weeks(start, day_count));
				}
				this.headers.append(this._header_days(start, day_count));
				if(day_count <= 7)
				{
					this.headers.append(this._header_hours(start, day_count));
				}
			},
			// Labels for the rows
			row_labels: function() {
				var labels = {};
				var accounts = egw.accounts();
				for(var i = 0; i < this.options.owner.length; i++)
				{
					var user = this.options.owner[i];
					if(parseInt(user) === 0)
					{
						// 0 means current user
						user = egw.user('account_id');
					}
					if (isNaN(user))		// resources
					{
						labels[user] = egw.link_title('resources',user.match(/\d+/)[0],function(name) {this[user] = name;},labels);
					}
					else if (user < 0)	// groups
					{
						egw.accountData(user,'account_fullname',true,function(result) {
							for(var id in result)
							{
								this[id] = result[id];
							}
						},labels);
					}
					else	// users
					{
						user = parseInt(user)
						for(var i = 0; i < accounts.length; i++)
						{
							if(accounts[i].value === user)
							{
								labels[user] = accounts[i].label;
								break;
							}
						}
					}
				}

				return labels;
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
				for(var user in event.participants)
				{
					var participant = event.participants[user];
					if(participant && typeof labels[user] !== 'undefined' && status_to_show.indexOf(participant.substr(0,1)) >= 0 ||
						this.options.filter === 'owner' && event.owner === user)
					{
						if(typeof rows[user] === 'undefined')
						{
							rows[user] = [];
						}
						rows[user].push(event);
					}
				}
			},
			// Draw a single row
			draw_row: function(sort_key, label, events) {
				return this._drawRow(sort_key, label,events,this.options.start_date, this.options.end_date);
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
				var labels = {};
				var d = new Date(this.options.start_date);
				d = new Date(d.valueOf() + d.getTimezoneOffset() * 60 * 1000);
				for(var i = 0; i < 12; i++)
				{
					labels[d.getUTCFullYear() +'-'+d.getUTCMonth()] = egw.lang(date('F',d))+' '+d.getUTCFullYear();
					d.setUTCMonth(d.getUTCMonth()+1);
				}
				return labels;
			},
			group: function(labels, rows,event) {
				var start = new Date(event.start);
				var key = start.getUTCFullYear() +'-'+start.getUTCMonth();
				if(typeof rows[key] === 'undefined')
				{
					rows[key] = [];
				}
				rows[key].push(event);

				// end in a different month?
				var end = new Date(event.end);
				var end_key = end.getUTCFullYear() +'-'+end.getUTCMonth();
				while(key !== end_key)
				{
					var year = start.getUTCFullYear();
					var month = start.getUTCMonth();
					if (++month > 12)
					{
						++year;
						month = 1;
					}
					key = sprintf('%04d-%02d',year,month);
					rows[key].push(event);
				}
			},
			// Draw a single row, but split up the dates
			draw_row: function(sort_key, label, events)
			{
				var key = sort_key.split('-');
				this._drawRow(sort_key, label, events, new Date(key[0],key[1],1),new Date(key[0],parseInt(key[1])+1,0));
			}
		},
		// Group by category has one row for each [sub]category
		category:
		{
			title: function() { return this.egw().lang('Category');},
			headers: function() {
				var start = new Date(this.options.start_date);
				var end = new Date(this.options.end_date);
				var start_date = new Date(start.getUTCFullYear(), start.getUTCMonth(),start.getUTCDate());
				var end_date = new Date(end.getUTCFullYear(), end.getUTCMonth(),end.getUTCDate());
				var day_count = Math.round((end_date - start_date) /(1000*3600*24))+1;

				if(day_count >= 28)
				{
					this.headers.append(this._header_months(start, day_count));
				}
				
				if(day_count >= 5)
				{
					this.headers.append(this._header_weeks(start, day_count));
				}
				this.headers.append(this._header_days(start, day_count));
				if(day_count <= 7)
				{
					this.headers.append(this._header_hours(start, day_count));
				}
			},
			row_labels: function() {
				return {'': egw.lang('none')};
			},
			group: function(labels, rows, event) {
				if(typeof rows[event.category] === 'undefined')
				{
					rows[event.category] = [];
				}
				rows[event.category].push(event);
				if(typeof labels[event.category] === 'undefined')
				{
					var categories = et2_selectbox.cat_options({_type:'select-cat'}, {application: 'calendar'});
					for(var i in categories )
					{
						if(parseInt(categories[i].value) === parseInt(event.category))
						{
							labels[event.category] = categories[i].label;
						}
					}
				}
			},
			draw_row: function(sort_key, label, events) {
				return this._drawRow(sort_key, label,events,this.options.start_date, this.options.end_date);
			}
		}
	},

	/**
	 * Something changed, and the planner needs to be re-drawn.  We wait a bit to
	 * avoid re-drawing twice if start and end date both changed, then recreate.
	 *
	 * @param {boolean} trigger=false Trigger an event once things are done.
	 *	Waiting until invalidate completes prevents 2 updates when changing the date range.
	 * @returns {undefined}
	 */
	invalidate: function(trigger) {

		// Wait a bit to see if anything else changes, then re-draw the days
		if(this.update_timer === null)
		{
			this.update_timer = window.setTimeout(jQuery.proxy(function() {
				this.widget.update_timer = null;

				this.widget._fetch_data();
				//this.widget._drawGrid();

				// Update actions
				if(this._actionManager)
				{
					this._link_actions(this._actionManager.children);
				}

				if(this.trigger)
				{
					this.widget.change();
				}
			},{widget:this,"trigger":trigger}),ET2_GRID_INVALIDATE_TIMEOUT);
		}
	},

	detachFromDOM: function() {
		// Remove the binding to the change handler
		$j(this.div).off("change.et2_calendar_timegrid");

		this._super.apply(this, arguments);
	},

	attachToDOM: function() {
		this._super.apply(this, arguments);

		// Add the binding for the event change handler
		$j(this.div).on("change.et2_calendar_timegrid", '.calendar_calEvent', this, function(e) {
			// Make sure function gets a reference to the widget
			var args = Array.prototype.slice.call(arguments);
			if(args.indexOf(this) == -1) args.push(this);

			return e.data.event_change.apply(e.data, args);
		});

		// Add the binding for the change handler
		$j(this.div).on("change.et2_calendar_timegrid", '*:not(.calendar_calEvent)', this, function(e) {
				return e.data.change.call(e.data, e, this);
			});

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
	_drawGrid: function()
	{

		this.div.css('height', this.options.height);

		// Clear old events
		var delete_index = this._children.length - 1;
		while(this._children.length > 0 && delete_index >= 0)
		{
			this._children[delete_index].free();
			this.removeChild(this._children[delete_index--]);
		}
		
		// Clear old rows
		this.rows.empty();

		var grouper = this.groupers[isNaN(this.options.group_by) ? this.options.group_by : 'category'];
		if(!grouper) return;

		// Headers
		this.headers.empty();
		this.headerTitle.text(grouper.title.apply(this));
		grouper.headers.apply(this);

		// Get the rows / labels
		var labels = grouper.row_labels.call(this);
		
		// Group the events
		var events = {};
		for(var i = 0; i < this.value.length; i++)
		{
			grouper.group.call(this, labels, events, this.value[i]);
		}

		// Draw the rows
		for(var key in labels)
		{
			grouper.draw_row.call(this,key, labels[key], events[key] || []);
		}

	},

	/**
	 * Draw a single row of the planner
	 *
	 * @param {string} key Index into the grouped labels & events
	 * @param {string} label
	 * @param {Array} events
	 * @param {Date} start
	 * @param {Date} end
	 */
	_drawRow: function(key, label, events, start, end)
	{
		var row = et2_createWidget('calendar-planner_row',{
				id: key,
				label: label,
				start_date: start,
				end_date: end,
				value: events
			},this);


		if(this.isInTree())
		{
			row.doLoadingFinished();
		}
		
		// Add actual events
		row._update_events(events);
	},


	_header_day_of_month: function()
	{
		var day_width = 3.23; // 100.0 / 31;

		// month scale with navigation
		var content = '<div class="calendar_plannerScale">';
		var start = new Date(this.options.start_date);
		start = new Date(start.valueOf() + start.getTimezoneOffset() * 60 * 1000);
		var end = new Date(this.options.end_date);
		end = new Date(end.valueOf() + end.getTimezoneOffset() * 60 * 1000);

		var title = egw.lang(date('F',start))+' '+date('Y',start)+' - '+
			egw.lang(date('F',end))+' '+date('Y',end);

		// calculate date for navigation links
		var time = new Date(start);
		time.setUTCFullYear(time.getUTCFullYear()-1);
		var last_year = date('Ymd',time);
		time.setUTCMonth(time.getUTCMonth()+11);
		var last_month = date('Ymd',time);
		time.setUTCMonth(time.getUTCMonth()+2);
		var next_month = date('Ymd',time);
		time.setUTCMonth(time.getUTCMonth()+11);
		var next_year = date('Ymd',time);

		title = last_year + ' ' + last_month + ' ' + title + ' ' +next_month +' ' +next_year;
/*
 *  TODO: implement these arrows
		title = html::a_href(html::image('phpgwapi','first',lang('back one year'),$options=' alt="<<"'),array(
				'menuaction' => $this->view_menuaction,
				'date'       => $last_year,
			)) + ' &nbsp; '+
			html::a_href(html::image('phpgwapi','left',lang('back one month'),$options=' alt="<"'),array(
				'menuaction' => $this->view_menuaction,
				'date'       => $last_month,
			)) + ' &nbsp; '+title;
		title += ' &nbsp; '.html::a_href(html::image('phpgwapi','right',lang('forward one month'),$options=' alt=">>"'),array(
					'menuaction' => $this->view_menuaction,
					'date'       => $next_month,
				))+ ' &nbsp; '+
				html::a_href(html::image('phpgwapi','last',lang('forward one year'),$options=' alt=">>"'),array(
					'menuaction' => $this->view_menuaction,
					'date'       => $next_year,
				));
		*/

		content += '<div class="calendar_plannerMonthScale th" style="left: 0; width: 100%;">'+
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
	},

	/**
	 * Make a header showing the months
	 * @param {Date} start
	 * @param {number} days
	 * @returns {string} HTML snippet
	 */
	_header_months: function(start, days)
	{
		var content = '<div class="calendar_plannerScale">';
		var days_in_month = 0;
		var day_width = 100 / days;
		for(var t = new Date(start),left = 0,i = 0; i < days; t.setUTCDate(t.getUTCDate() + days_in_month),left += days_in_month*day_width,i += days_in_month)
		{
			days_in_month = new Date(t.getUTCFullYear(),t.getUTCMonth()+1,0).getUTCDate();

			if (i + days_in_month > days)
			{
				days_in_month = days - i;
			}
			if (days_in_month > 5)
			{
				var title = egw.lang(date('F',new Date(t.valueOf() + t.getTimezoneOffset() * 60 * 1000)))
			}
			if (days_in_month > 10)
			{
				title += ' '+t.getUTCFullYear();
			
				// previous links
				var prev = new Date(t);
				prev.setUTCDate(1);
				prev.setUTCMonth(prev.getUTCMonth()-1);
				
				var full = prev.toJSON();
				prev.setUTCDate(start.getUTCDate());
				if (prev.getUTCDate() >= 15) prev = new Date(t);		// we stay in the same month
				prev.setUTCDate(start.getUTCDate() < 15 ? 15 : 1);
				var half = prev.toJSON();
				title = this._scroll_button('first',full) + this._scroll_button('left',half) + title;

				
				// next links
				var next = new Date(t);
				next.setUTCMonth(next.getUTCMonth()+1);
				next.setUTCDate(start.getUTCDate() < 15 ? 15 : 1);
				half = next.toJSON();
				next.setUTCMonth(next.getUTCMonth()+1);
				full = next.toJSON();

				title += this._scroll_button('right',half) + this._scroll_button('last',full);
			}
			else
			{
				title = '&nbsp;';
			}
			content += '<div class="calendar_plannerMonthScale et2_clickable" data-date="'+t.toJSON()+'" data-planner_days='+days_in_month+
				' style="left: '+left+'%; width: '+(day_width*days_in_month)+'%;">'+
				title+"</div>";
		}
		content += "</div>";		// end of plannerScale

		return content;
	},

	/**
	 * Make a header showing the week numbers
	 * 
	 * @param {Date} start
	 * @param {number} days
	 * @returns {string} HTML snippet
	 */
	_header_weeks: function(start, days)
	{
		var week_width = 100 / days * (days <= 7 ? days : 7);

		var content = '<div class="calendar_plannerScale" data-planner_days=7>';
		var state = ''

		// we're not using UTC so date() formatting function works
		var t = new Date(start.valueOf() + start.getTimezoneOffset() * 60 * 1000);
		for(var left = 0,i = 0; i < days; t.setDate(t.getDate() + 7),left += week_width,i += 7)
		{
			var title = egw.lang('Week')+' '+date('W',t);

			state = new Date(t.valueOf() - start.getTimezoneOffset() * 60 * 1000).toJSON();
			/*
			if (days > 7)
			{
				$title = html::a_href($title,array(
					'menuaction' => 'calendar.calendar_uiviews.planner',
					'planner_days' => 7,
					'date'       => date('Ymd',$t),
				),false,' title="'.html::htmlspecialchars(lang('Weekview')).'"');
			}
			else
			{
				// prev. week
				$title = html::a_href(html::image('phpgwapi','first',lang('previous'),$options=' alt="<<"'),array(
					'menuaction' => $this->view_menuaction,
					'date'       => date('Ymd',$t-7*DAY_s),
				)) . ' &nbsp; <b>'.$title;
				// next week
				$title .= '</b> &nbsp; '.html::a_href(html::image('phpgwapi','last',lang('next'),$options=' alt=">>"'),array(
					'menuaction' => $this->view_menuaction,
					'date'       => date('Ymd',$t+7*DAY_s),
				));
			}
			*/
			content += '<div class="calendar_plannerWeekScale et2_clickable" data-date=\'' + state + '\' style="left: '+left+'%; width: '+week_width+'%;">'+title+"</div>";
		}
		content += "</div>";		// end of plannerScale

		return content;
	},

	/**
	 * Make a header for some days
	 *
	 * @param {Date} start
	 * @param {number} days
	 * @returns {string} HTML snippet
	 */
	_header_days: function(start, days)
	{
		var day_width = 100 / days;
		var content = '<div class="calendar_plannerScale'+(days > 3 ? 'Day' : '')+'" data-planner_days="1" >';

		// we're not using UTC so date() formatting function works
		var t = new Date(start.valueOf() + start.getTimezoneOffset() * 60 * 1000);
		for(var left = 0,i = 0; i < days; t.setDate(t.getDate()+1),left += day_width,++i)
		{
			var holidays = [];
			var day_class = this.day_class_holiday(t,holidays);
			var title = '';
			var state = '';

			if (days <= 3)
			{
				title = egw.lang(date('l',t))+', '+date('j',t)+'. '+egw.lang(date('F',t));
			}
			else if (days <= 7)
			{
				title = egw.lang(date('l',t))+' '+date('j',t);
			}
			else
			{
				title = egw.lang(date('D',t)).substr(0,2)+'<br />'+date('j',t);
			}
			state = new Date(t.valueOf() - start.getTimezoneOffset() * 60 * 1000).toJSON();
			if (days > 1)
			{
				/*
				title = html::a_href($title,array(
					'menuaction'   => 'calendar.calendar_uiviews.planner',
					'planner_days' => 1,
					'date'         => date('Ymd',$t),
				),false,strpos($class,'calendar_calHoliday') !== false || strpos($class,'calendar_calBirthday') !== false ? '' : ' title="'.html::htmlspecialchars(lang('Dayview')).'"');
				*/
			}
			if (days < 5)
			{
				/*
				if (!i)	// prev. day only for the first day
				{
					title = html::a_href(html::image('phpgwapi','first',lang('previous'),$options=' alt="<<"'),array(
						'menuaction' => $this->view_menuaction,
						'date'       => date('Ymd',$start-DAY_s),
					)) . ' &nbsp; '.$title;
				}
				if (i == days-1)	// next day only for the last day
				{
					title += ' &nbsp; '.html::a_href(html::image('phpgwapi','last',lang('next'),$options=' alt=">>"'),array(
						'menuaction' => $this->view_menuaction,
						'date'       => date('Ymd',$start+DAY_s),
					));
				}
				*/
			}
			content += '<div class="calendar_plannerDayScale et2_clickable '+ day_class+
				'" data-date=\'' + state +'\' style="left: '+left+'%; width: '+day_width+'%;"'+
				(holidays ? ' title="'+holidays.join(',')+'"' : '')+'>'+title+"</div>\n";
		}
		content += "</div>";		// end of plannerScale

		return content;
	},

	/**
	 * Create a header with hours
	 * 
	 * @param {Date} start
	 * @param {number} days
	 * @returns {string} HTML snippet for the header
	 */
	_header_hours: function(start,days)
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
			var t = new Date(start.getUTCFullYear(),start.getUTCMonth(),start.getUTCDate());
			var s = new Date(start);
			s.setUTCHours(23);
			s.setUTCMinutes(59);
			s.setUTCSeconds(59);
			hours = (s.getTime() - t.getTime()) / 3600000;
		}
		var cell_width = 100 / hours * decr;

		var content = '<div class="calendar_plannerScale">';
		
		// we're not using UTC so date() formatting function works
		var t = new Date(start.valueOf() + start.getTimezoneOffset() * 60 * 1000);
		for(var left = 0,i = 0; i < hours; left += cell_width,i += decr)
		{
			var title = date(egw.preference('timeformat','calendar') == 12 ? 'ha' : 'H',t);

			content += '<div class="calendar_plannerHourScale" style="left: '+left+'%; width: '+(cell_width)+'%;">'+title+"</div>";
			t.setHours(t.getHours()+decr);
		}
		content += "</div>";		// end of plannerScale

		return content;
	},

	/**
	 * Create a pagination button, and inserts it
	 * 
	 */
	_scroll_button: function(image, date)
	{
		return '<img class="et2_clickable" src="' + egw.image(image)+ '" data-date="' + (date.toJSON ? date.toJSON():date) + '"/>';
	},

	/**
	 * Applies class for today, and any holidays for current day
	 *
	 * @param {Date} date
	 * @param {string[]} holiday_list Filled with a list of holidays for that day
	 *
	 * @return {string} CSS Classes for the day.  calendar_calBirthday, calendar_calHoliday, calendar_calToday and calendar_weekend as appropriate
	 */
	day_class_holiday: function(date,holiday_list) {

		if(!date) return '';

		var day_class = '';
		
		// Holidays and birthdays
		var holidays = et2_calendar_daycol.get_holidays(this,date.getUTCFullYear());

		// Pass a number rather than the date object, to make sure it doesn't get changed
		this.date_helper.set_value(date.getTime()/1000);
		var date_key = ''+this.date_helper.get_year() + sprintf('%02d',this.date_helper.get_month()) + sprintf('%02d',this.date_helper.get_date());
		if(holidays && holidays[date_key])
		{
			holidays = holidays[date_key];
			for(var i = 0; i < holidays.length; i++)
			{
				if (typeof holidays[i]['birthyear'] !== 'undefined')
				{
					day_class += ' calendar_calBirthday';

					holiday_list.push(holidays[i]['name']);
				}
				else
				{
					day_class += 'calendar_calHoliday';

					holiday_list.push(holidays[i]['name']);
				}
			}
		}
		holidays = holiday_list.join(',');
		var today = new Date();
		if(date_key === ''+today.getUTCFullYear()+
			sprintf("%02d",today.getUTCMonth()+1)+
			sprintf("%02d",today.getUTCDate())
		)
		{
			day_class += "calendar_calToday";
		}
		if(date.getUTCDay() == 0 || date.getUTCDay() == 6)
		{
			day_class += "calendar_weekend";
		}
		return day_class;
	},

	/**
	 * Link the actions to the DOM nodes / widget bits.
	 *
	 * @todo This currently does nothing
	 * @param {object} actions {ID: {attributes..}+} map of egw action information
	 */
	_link_actions: function(actions)
	{
		// Get the parent?  Might be a grid row, might not.  Either way, it is
		// just a container with no valid actions
		var objectManager = egw_getAppObjectManager(true);
		var parent = objectManager.getObjectById(this._parent.id);
		if(!parent) return;
		
		for(var i = 0; i < parent.children.length; i++)
		{
			var parent_finder = jQuery(this.div, parent.children[i].iface.doGetDOMNode());
			if(parent_finder.length > 0)
			{
				parent = parent.children[i];
				break;
			}
		}
	},

	/**
	 * Automatically add dnd support for linking
	 */
	_init_links_dnd: function(mgr,actionLinks) {
		var self = this;

		var drop_action = mgr.getActionById('egw_link_drop');
		var drag_action = mgr.getActionById('egw_link_drag');

		// Check if this app supports linking
		if(!egw.link_get_registry(this.dataStorePrefix || this.egw().appName, 'query') ||
			egw.link_get_registry(this.dataStorePrefix || this.egw().appName, 'title'))
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

			},true);
		}
		if(actionLinks.indexOf(drop_action.id) < 0)
		{
			actionLinks.push(drop_action.id);
		}
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
				// Drag helper - list titles.  Arbitrarily limited to 10.
				var helper = $j(document.createElement("div"));
				for(var i = 0; i < selected.length && i < 10; i++)
				{
					var id = selected[i].id.split('::');
					var span = $j(document.createElement('span')).appendTo(helper);
					egw.link_title(id[0],id[1], function(title) {
						this.append(title);
						this.append('<br />');
					}, span);
				}
				// As we wanted to have a general defaul helper interface, we return null here and not using customize helper for links
				// TODO: Need to decide if we need to create a customized helper interface for links anyway
				//return helper;
				return null;
			},true);
		}
		if(actionLinks.indexOf(drag_action.id) < 0)
		{
			actionLinks.push(drag_action.id);
		}
		drag_action.set_dragType('link');
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
			if(action.type === 'drop')
			{
				action_links.push(typeof action.id !== 'undefined' ? action.id : i);
			}
		}
		return action_links;
	},

	/**
	 * Use the egw.data system to get data from the calendar list for the
	 * selected time span.
	 * 
	 */
	_fetch_data: function()
	{
		this.egw().dataFetch(
			this.getInstanceManager().etemplate_exec_id,
			{start: 0, num_rows:0},
			jQuery.extend({}, app.calendar.state,
			{
				get_rows: 'calendar.calendar_uilist.get_rows',
				row_id:'row_id',
				startdate:this.options.start_date,
				enddate:this.options.end_date,
				col_filter: {participant: this.options.owner},
				filter:'custom'
			}),
			this.id,
			function(data) {
				console.log(data);
				var events = [];
				for(var i = 0; i < data.order.length && data.total; i++)
				{
					var record = this.egw().dataGetUIDdata(data.order[i]);
					if(record && record.data)
					{
						events.push(record.data);
					}
				}
				this.value = events;
				this._drawGrid();
			}, this,null
		);
	},

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
	set_value: function(events)
	{
		if(typeof events !== 'object') return false;

		if(events.owner)
		{
			this.set_owner(events.owner);
			delete events.owner;
		}
		if(events.start_date)
		{
			this.set_start_date(events.start_date);
			delete events.start_date;
		}
		if(events.end_date)
		{
			this.set_end_date(events.end_date);
			delete events.end_date;
		}

		this.value = events || [];
	},

	/**
	 * Change the start date
	 * 
	 * @param {string|number|Date} new_date New starting date
	 * @returns {undefined}
	 */
	set_start_date: function(new_date)
	{
		if(!new_date || new_date === null)
		{
			throw new Error('Invalid start date. ' + new_date.toString());
		}

		// Use date widget's existing functions to deal
		if(typeof new_date === "object" || typeof new_date === "string" && new_date.length > 8)
		{
			this.date_helper.set_value(new_date);
		}
		else if(typeof new_date === "string")
		{
			this.date_helper.set_year(new_date.substring(0,4));
			this.date_helper.set_month(new_date.substring(4,6));
			this.date_helper.set_date(new_date.substring(6,8));
		}

		var old_date = this.options.start_date;
		this.options.start_date = this.date_helper.getValue();

		if(old_date !== this.options.start_date && this.isAttached())
		{
			this.invalidate(true);
		}
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
			throw new Error('Invalid end date. ' + new_date.toString());
		}
		// Use date widget's existing functions to deal
		if(typeof new_date === "object" || typeof new_date === "string" && new_date.length > 8)
		{
			this.date_helper.set_value(new_date);
		}
		else if(typeof new_date === "string")
		{
			this.date_helper.set_year(new_date.substring(0,4));
			this.date_helper.set_month(new_date.substring(4,6));
			this.date_helper.set_date(new_date.substring(6,8));
		}

		this.date_helper.set_hours(23);
		this.date_helper.set_minutes(59);
		this.date_helper.date.setSeconds(59);
		var old_date = this.options.end_date;
		this.options.end_date = this.date_helper.getValue();

		if(old_date !== this.options.end_date && this.isAttached())
		{
			this.invalidate(true);
		}
	},

	/**
	 * Change how the planner is grouped
	 * 
	 * @param {string|number} group_by 'user', 'month', or an integer category ID
	 * @returns {undefined}
	 */
	set_group_by: function(group_by)
	{
		if(isNaN(group_by) && typeof this.groupers[group_by] === 'undefined')
		{
			throw new Error('Invalid group_by "'+group_by+'"');
		}
		var old = this.options.group_by;
		this.options.group_by = ''+group_by;

		if(old !== this.options.group_by && this.isAttached())
		{
			this.invalidate(true);
		}
	},

	/**
	 * Set which users to display when filtering, and for rows when grouping by user.
	 *
	 * @param {number|number[]} _owner Account ID
	 */
	set_owner: function(_owner)
	{
		var old = this.options.owner;
		if(!jQuery.isArray(_owner))
		{
			if(typeof _owner === "string")
			{
				_owner = _owner.split(',');
			}
			else
			{
				_owner = [_owner];
			}
		}
		else
		{
			_owner = jQuery.extend([],_owner);
		}
		this.options.owner = _owner;
		if(old !== this.options.owner && this.isAttached())
		{
			this.invalidate(true);
		}
	},


	/**
	 * Call change handler, if set
	 */
	change: function(event) {
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
	},

	/**
	 * Call event change handler, if set
	 */
	event_change: function(event, dom_node) {
		if (this.onevent_change)
		{
			var event_data = this._get_event_info(dom_node);
			var event_widget = this.getWidgetById(event_data.id);
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
	},

	/**
	 * Click handler calling custom handler set via onclick attribute to this.onclick
	 *
	 * This also handles all its own actions, including navigation.  If there is
	 * an event associated with the click, it will be found and passed to the
	 * onclick function.
	 *
	 * @param {Event} _ev
	 * @returns {boolean}
	 */
	click: function(_ev)
	{
		var result = true;
		
		// Is this click in the event stuff, or in the header?
		if(this.gridHeader.has(_ev.target).length === 0 && !$j(_ev.target).hasClass('calendar_plannerRowHeader'))
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
			return result;
		}
		else if (!jQuery.isEmptyObject(_ev.target.dataset))
		{
			// Click on a header, we can go there
			_ev.data = jQuery.extend({},_ev.target.parentNode.dataset, _ev.target.dataset);
			this.change(_ev);
		}
		else
		{
			// Default handler to open a new event at the selected time
			// TODO: Determine date / time more accurately from position
			this.egw().open(null, 'calendar', 'add', {
				date: _ev.target.dataset.date || this.day_list[0],
				hour: _ev.target.dataset.hour || this.options.day_start,
				minute: _ev.target.dataset.minute || 0
			} , '_blank');
			return false;
		}
	},

	_get_event_info: function(dom_node)
	{
		// Determine as much relevant info as can be found
		var event_node = $j(dom_node).closest('[data-id]',this.div)[0];
		var day_node = $j(event_node).closest('[data-date]',this.div)[0];
		
		return jQuery.extend({
				event_node: event_node,
				day_node: day_node,
			},
			event_node ? event_node.dataset : {},
			day_node ? day_node.dataset : {}
		);
	},

	
	/**
	 * Get time from position
	 * 
	 * @param {number} x
	 * @param {number} y
	 * @returns {DOMNode[]} time node(s) for the given position
	 */
	_get_time_from_position: function(x,y) {
		
		x = Math.round(x);
		y = Math.round(y);
		var nodes = $j('.calendar_calAddEvent[data-hour]',this.div).removeClass('drop-hover').filter(function() {
			var offset = $j(this).offset();
			var range={x:[offset.left,offset.left+$j(this).outerWidth()],y:[offset.top,offset.top+$j(this).outerHeight()]};
			
			var i = (x >=range.x[0]  && x <= range.x[1]) && (y >= range.y[0] && y <= range.y[1]);
			return i;
		}).addClass("drop-hover");
		
		return nodes;
	},
		
	/**
	 * Code for implementing et2_IDetachedDOM
	 *
	 * @param {array} _attrs array to add further attributes to
	 */
	getDetachedAttributes: function(_attrs) {
		_attrs.push('start_date','end_date');
	},

	getDetachedNodes: function() {
		return [this.getDOMNode()];
	},

	setDetachedAttributes: function(_nodes, _values) {
		this.div = $j(_nodes[0]);

		if(_values.start_date)
		{
			this.set_start_date(_values.start_date);
		}
		if(_values.end_date)
		{
			this.set_end_date(_values.end_date);
		}
	},

	// Resizable interface
	resize: function (_height)
	{
		this.options.height = _height;
		this.div.css('height', this.options.height);
	}
});
et2_register_widget(et2_calendar_planner, ["calendar-planner"]);