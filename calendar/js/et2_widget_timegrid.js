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
	/calendar/js/et2_widget_daycol.js;
	/calendar/js/et2_widget_event.js;
*/

/**
 * Class which implements the "calendar-timegrid" XET-Tag for displaying a span of days
 *
 * This widget is responsible for the times on the side
 *
 * @augments et2_DOMWidget
 */
var et2_calendar_timegrid = et2_valueWidget.extend([et2_IDetachedDOM, et2_IResizeable],
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
		value: {
			type: "any",
			description: "An array of events, indexed by date (Ymd format)."
		},
		day_start: {
			name: "Day start time",
			type: "string",
			default: parseInt(egw.preference('workdaystarts','calendar')) || 9,
			description: "Work day start time.  If unset, this will default to the current user's preference"
		},
		day_end: {
			name: "Day end time",
			type: "string",
			default: parseInt(egw.preference('workdayends','calendar')) || 17,
			description: "Work day end time.  If unset, this will default to the current user's preference"
		},
		show_weekend: {
			name: "Weekends",
			type: "boolean",
			default: egw.preference('days_in_weekview','calendar') != 5,
			description: "Display weekends.  The date range should still include them for proper scrolling, but they just won't be shown."
		},
		granularity: {
			name: "Granularity",
			type: "integer",
			default: parseInt(egw.preference('interval','calendar')) || 30,
			description: "How many minutes per row"
		},
		extra_rows: {
			name: "Extra rows",
			type: "integer",
			default: 2,
			description: "Extra rows above and below the workday"
		},
		owner: {
			name: "Owner",
			type: "any", // Integer, or array of integers
			default: 0,
			description: "Account ID number of the calendar owner, if not the current user"
		},

		height: {
			"default": '100%'
		}
	},
	/**
	 * Constructor
	 *
	 * @memberOf et2_calendar_timegrid
	 */
	init: function() {
		this._super.apply(this, arguments);

		// Main container
		this.div = $j(document.createElement("div"))
			.addClass("calendar_calTimeGrid");

		// Contains times / rows
		this.gridHeader = $j(document.createElement("div"))
			.addClass("calendar_calGridHeader")
			.appendTo(this.div);
		
		// Contains days / columns
		this.days = $j(document.createElement("div"))
			.addClass("calendar_calDayCols")
			.appendTo(this.div);

		// Used for its date calculations
		this.date_helper = et2_createWidget('date',{},null);
		this.date_helper.loadingFinished();

		// Used for owners
		this.owner = et2_createWidget('select-account_ro',{},this);
		
		// List of dates in Ymd
		// The first one should be start_date, last should be end_date
		this.day_list = [];
		this.day_widgets = [];

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
	},

	doLoadingFinished: function() {
		this._super.apply(this, arguments);
		this._drawGrid();

		// Bind scroll event
		// When the user scrolls, we'll move enddate - startdate days
		this.div.on('wheel',jQuery.proxy(function(e) {
			var direction = e.originalEvent.deltaY > 0 ? 1 : -1;

			this.date_helper.set_value(this.options.end_date);
			var end = this.date_helper.get_time();

			this.date_helper.set_value(this.options.start_date);
			var start = this.date_helper.get_time();
			
			var delta = 1000 * 60 * 60 * 24 + (end - start);// / (1000 * 60 * 60 * 24));
			
			// TODO - actually fetch new data
			this.set_start_date(new Date(start + (delta * direction )));
			this.set_end_date(new Date(end + (delta * direction)));
			
			e.preventDefault();
			return false;
		},this))
		// Bind context event to create actionobjects as needed
		// TODO: Do it like this, or the normal way?
		.on('contextmenu', jQuery.proxy(function(e) {
			if(this.days.has(e.target).length)
			{
				var event = this._get_event_info(e.originalEvent.target);
				this._link_event(event);
			}
		},this));

		return true;
	},

	/**
	 * Something changed, and the days need to be re-drawn.  We wait a bit to
	 * avoid re-drawing twice if start and end date both changed, then recreate
	 * the days.
	 * The whole grid is not regenerated because times aren't expected to change,
	 * just the days.
	 * 
	 * @returns {undefined}
	 */
	invalidate: function() {

		// Reset the list of days
		this.day_list = [];

		// Wait a bit to see if anything else changes, then re-draw the days
		if(this.update_timer === null)
		{
			this.update_timer = window.setTimeout(jQuery.proxy(function() {
				this.update_timer = null;
				this._drawDays();
			},this),ET2_GRID_INVALIDATE_TIMEOUT);
		}
	},

	getDOMNode: function(_sender) {
		if(_sender === this || !_sender)
		{
			return this.div[0];
		}
		else if (_sender.instanceOf(et2_calendar_daycol))
		{
			return this.days[0];
		}
		else if (_sender)
		{
			return this.gridHeader[0];
		}
	},
	
	_drawGrid: function() {

		this.div.css('height', this.options.height)
			.empty();

		// Draw in the horizontal - the times
		this._drawTimes();

		// Draw in the vertical - the days
		this.div.append(this.days);
		this._drawDays();
	},

	/**
	 * Creates the DOM nodes for the times in the left column, and the horizontal
	 * lines (mostly via CSS) that span the whole time span.
	 */
	_drawTimes: function() {
		var wd_start = 60*this.options.day_start;
		var wd_end = 60*this.options.day_end;
		var granularity = this.options.granularity;
		var totalDisplayMinutes	= wd_end - wd_start;
		var rowsToDisplay	= (totalDisplayMinutes/granularity)+2+2*this.options.extra_rows;
		var rowHeight		= (100/rowsToDisplay).toFixed(1);

		// ensure a minimum height of each row
		if (this.options.height < (rowsToDisplay+1) * 12)
		{
			this.options.height = (rowsToDisplay+1) * 12;
		}

		this.gridHeader
			.css('height', rowHeight+'%')
			.text(this.options.label)
			.appendTo(this.div);

		// the hour rows
		var show = {
			5  : [0,15,30,45],
			10 : [0,30],
			15 : [0,30],
			45 : [0,15,30,45]
		};
		var html = '';
		for(var t = wd_start,i = 1+this.options.extra_rows; t <= wd_end; t += granularity,++i)
		{
			html += '<div class="calendar_calTimeRow" style="height: '+rowHeight+'%; top:'+ (i*rowHeight).toFixed(1) +'%;">';
			// show time for full hours, always for 45min interval and at least on every 3 row
			var time = jQuery.datepicker.formatTime(
					egw.preference("timeformat") == 12 ? "h:mmtt" : "HH:mm",
					{
						hour: t / 60,
						minute: t % 60,
						seconds: 0,
						timezone: 0
					},
					{"ampm": (egw.preference("timeformat") == "12")}
				);

			var time_label = (typeof show[granularity] === 'undefined' ? t % 60 === 0 : show[granularity].indexOf(t % 60) !== -1) ? time : '';
			html += '<div class="calendar_calTimeRowTime et2_clickable data-time="'+time.trim()+' data-hour="'+Math.floor(t/60)+'" data-minute="'+(t%60)+'">'+time_label+"</div></div>\n";
		}
		this.div.append(html);
	},

	/**
	 * Set up the needed day widgets to correctly display the selected date
	 * range.  First we calculate the needed dates, then we create any needed
	 * widgets.  Existing widgets are recycled rather than discarded.
	 */
	_drawDays: function() {
		// If day list is still empty, recalculate it from start & end date
		if(this.day_list.length === 0)
		{
			this.day_list = this._calculate_day_list(this.options.start_date, this.options.end_date, this.options.show_weekend);
		}

		// Create any needed widgets - otherwise, we'll just recycle
		// Add any needed day widgets (now showing more days)
		while(this.day_list.length > this.day_widgets.length)
		{
			var day = et2_createWidget('calendar-daycol',{
				owner: this.options.owner
			},this);
			if(this.isInTree())
			{
				day.doLoadingFinished();
			}
			this.day_widgets.push(day);
		}
		// Remove any extra day widgets (now showing less)
		var delete_index = this.day_widgets.length - 1;
		while(this.day_widgets.length > this.day_list.length)
		{
			// If we're going down to an existing one, just keep it for cool CSS animation
			while(this.day_list.indexOf(this.day_widgets[delete_index].options.date) > -1)
			{
				delete_index--;
			}
			this.day_widgets[delete_index].set_width('0px');
			this.day_widgets[delete_index].free();
			this.day_widgets.splice(delete_index--,1);
		}

		// Create / update day widgets with dates and data, if available
		for(var i = 0; i < this.day_list.length; i++)
		{
			day = this.day_widgets[i];
			// Set the date, and pass any data we have
			day.set_date(this.day_list[i], this.value[this.day_list[i]] || false);
			day.set_id(this.day_list[i]);
			day.set_width((100/this.day_list.length).toFixed(2) + '%');

			// Position
			$j(day.getDOMNode()).css('left', ((100/this.day_list.length).toFixed(2) * i) + '%');
		}

		// Update actions
		if(this._actionManager)
		{
			this._link_actions(this._actionManager.children);
		}

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
	},

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
	_calculate_day_list: function(start_date, end_date, show_weekend) {
		
		var day_list = [];
		
		this.date_helper.set_value(end_date);
		var end = this.date_helper.date.getTime();
		var i = 1;
		this.date_helper.set_value(start_date);

		do
		{
			if(show_weekend || !show_weekend && [0,6].indexOf(this.date_helper.date.getUTCDay()) === -1)
			{
				day_list.push(''+this.date_helper.get_year() + sprintf('%02d',this.date_helper.get_month()) + sprintf('%02d',this.date_helper.get_date()));
			}
			this.date_helper.set_date(this.date_helper.get_date()+1);
		}
		// Limit it to 14 days to avoid infinite loops in case something is mis-set,
		// though the limit is more based on how wide the screen is
		while(end >= this.date_helper.date.getTime() && i <= 14)

		return day_list;
	},

	/**
	 * Link the actions to the DOM nodes / widget bits.
	 *
	 * @param {object} actions {ID: {attributes..}+} map of egw action information
	 */
	_link_actions: function(actions)
	{
		this._super.apply(this, arguments);

		 // Get the top level element for the tree
		var objectManager = egw_getAppObjectManager(true);
		var widget_object = objectManager.getObjectById(this.id);

		// Time grid is just a container
		widget_object.flags = EGW_AO_FLAG_IS_CONTAINER;
	},

	/**
	 * Bind a single event as needed to the action system. 
	 *
	 * @param {Object} event
	 */
	_link_event: function(event)
	{
		if(!event || !event.app_id) return;

		// Go over the widget & add links - this is where we decide which actions are
		// 'allowed' for this widget at this time
		var objectManager = egw_getObjectManager(this.id,false);
		if(objectManager == null)
		{
			// No actions set up
			return;
		}
		
		var obj = null;
		debugger;
		if(!(obj = objectManager.getObjectById(event.app_id)))
		{
			obj = objectManager.addObject(event.app_id, new et2_action_object_impl(this,event.event_node));
			obj.data = event;
			obj.updateActionLinks(objectManager.actionLinks)
		}
		objectManager.setAllSelected(false);
		obj.setSelected(true);
		objectManager.updateSelectedChildren(obj,true)
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
		this.value = events;
		var day_list = Object.keys(events);
		this.set_start_date(day_list[0]);
		this.set_end_date(day_list[day_list.length-1]);

		// Reset and calculate instead of just use the keys so we can get the weekend preference
		this.day_list = [];
	},

	/**
	 * Change the start date
	 * 
	 * @param {string|number|Date} new_date New starting date
	 * @returns {undefined}
	 */
	set_start_date: function(new_date)
	{
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
			this.invalidate();
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

		var old_date = this.options.end_date;
		this.options.end_date = this.date_helper.getValue();

		if(old_date !== this.options.end_date && this.isAttached())
		{
			this.invalidate();
		}
	},

	get_granularity: function()
	{
		// get option, or user's preference
		if(typeof this.options.granularity === 'undefined')
		{
			this.options.granularity = egw.preference('interval','calendar') || 30;
		}
		return parseInt(this.options.granularity);
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
		if(this.days.has(_ev.target).length)
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
				this._edit_event(event);

				return false;
			}
			return result;
		}
		else
		{
			// Default handler to open a new event at the selected time
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

	_edit_event: function(event)
	{
		if(event.recur_type)
		{
			var edit_id = event.id;
			var edit_date = event.start;
			var that = this;
			var buttons = [
				{text: this.egw().lang("Edit exception"), id: "exception", class: "ui-priority-primary", "default": true},
				{text: this.egw().lang("Edit series"), id:"series"},
				{text: this.egw().lang("Cancel"), id:"cancel"}
			];
			et2_dialog.show_dialog(function(_button_id)
			{
				switch(_button_id)
				{
					case 'exception':
						that.egw().open(edit_id, 'calendar', 'edit', {date:edit_date,exception: '1'});
						break;
					case 'series':
						that.egw().open(edit_id, 'calendar', 'edit', {date:edit_date});
						break;
					case 'cancel':

					default:
						break;
				}
			},this.egw().lang("Do you want to edit this event as an exception or the whole series?"),
			this.egw().lang("This event is part of a series"), {}, buttons, et2_dialog.WARNING_MESSAGE);
		}
		else
		{
			this.egw().open(event.id, event.app||'calendar','edit');
		}
	},


	/**
	 * Set which user owns this.  Owner is passed along to the individual
	 * days.
	 *
	 * @param {number} _owner Account ID
	 * @returns {undefined}
	 */
	set_owner: function(_owner)
	{
		// Let select-account widget handle value validation
		this.owner.set_value(_owner);

		this.options.owner = _owner;//this.owner.getValue();

		for (var i = this._children.length - 1; i >= 0; i--)
		{
			if(typeof this._children[i].set_owner === 'function')
			{
				this._children[i].set_owner(this.options.owner);
			}
		}
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
et2_register_widget(et2_calendar_timegrid, ["calendar-timegrid"]);