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

		// Stop the invalidate timer
		if(this.update_timer)
		{
			window.clearTimeout(this.update_timer);
		}
	},

	doLoadingFinished: function() {
		this._super.apply(this, arguments);
		this._drawGrid();

		// Actions may be set on a parent, so we need to explicitly get in here
		// and get ours
		this._link_actions(this.options.actions || this._parent.options.actions || []);

		// Automatically bind drag and resize for every event using jQuery directly
		// - no action system -
		var timegrid = this;

		// Show the current time while dragging
		var drag_helper = function(event, element,height)
		{
			this.dropEnd = timegrid._get_time_from_position(element.getBoundingClientRect().left,
				element.getBoundingClientRect().top+parseInt(height));

			if (typeof this.dropEnd != 'undefined' && this.dropEnd.length)
			{
				this.dropEnd.addClass("drop-hover");
				var time = jQuery.datepicker.formatTime(
					egw.preference("timeformat") == 12 ? "h:mmtt" : "HH:mm",
					{
						hour: this.dropEnd.attr('data-hour'),
						minute: this.dropEnd.attr('data-minute'),
						seconds: 0,
						timezone: 0
					},
					{"ampm": (egw.preference("timeformat") == "12")}
				);
				this.innerHTML = '<div style="font-size: 1.1em; text-align:center; font-weight: bold; height:100%;"><span class="calendar_timeDemo" >'+time+'</span></div>';
			}
			else
			{
				this.innerHTML = '<div class="calendar_d-n-d_forbiden"></div>';
			}
			return this.dropEnd;
		};
		
		this.div.on('mouseover', '.calendar_calEvent:not(.ui-resizable):not(.rowNoEdit)', function() {
			// Load the event
			timegrid._get_event_info(this);
			var that = this;
			
			//Resizable event handler
			$j(this).resizable
			({
				distance: 10,
				grid: [10000,timegrid.rowHeight],
				autoHide: true,
				handles: 's,se',
				containment:'parent',

				/**
				 *  Triggered when the resizable is created.
				 *
				 * @param {event} event
				 * @param {Object} ui
				 */
				create:function(event, ui)
				{
					var resizeHelper = event.target.getAttribute('data-resize');
					if (resizeHelper == 'WD' || resizeHelper == 'WDS')
					{
						jQuery(this).resizable('destroy');
					}
				},

				/**
				 * Triggered at start of resizing a calEvent
				 *
				 * @param {event} event
				 * @param {Object} ui
				 */
				start:function(event, ui)
				{
					this.dropStart = timegrid._get_time_from_position(ui.element[0].getBoundingClientRect().left,ui.element[0].getBoundingClientRect().top).last();
					this.dropDate = timegrid._get_event_info(this).start;
				},

				/**
				 * Triggered at the end of resizing the calEvent.
				 *
				 * @param {event} event
				 * @param {Object} ui
				 */
				stop:function(event, ui)
				{
					var e = new jQuery.Event('change');
					e.originalEvent = event;
					e.data = {duration: 0};
					var event_data = timegrid._get_event_info(this);
					var event_widget = timegrid.getWidgetById(event_data.id);
					
					var sT = parseInt(this.dropStart.attr('data-hour'))* 60 + parseInt(this.dropStart.attr('data-minute'));
					if (typeof this.dropEnd != 'undefined' && this.dropEnd.length == 1)
					{
						var eT = parseInt(this.dropEnd.attr('data-hour') * 60) + parseInt(this.dropEnd.attr('data-minute'));
						e.data.duration = ((eT - sT)/60) * 3600;



						if(event_widget)
						{
							event_widget.options.value.duration = e.data.duration;
						}
						$j(this).trigger(e);


						// That cleared the resize handles, so remove for re-creation...
						$j(this).resizable('destroy');
					}
					// Clear the helper, re-draw
					event_widget.set_value(event_widget.options.value);
				},

				/**
				 * Triggered during the resize, on the drag of the resize handler
				 *
				 * @param {event} event
				 * @param {Object} ui
				 */
				resize:function(event, ui)
				{
					// Add 5px to make sure it doesn't land right on the edge of a div
					drag_helper.call(this,event,ui.element[0],ui.helper.outerHeight()+5);
				}
			});
		});

		// Customize and override some draggable settings
		this.div.on('dragcreate','.calendar_calEvent:not(.rowNoEdit)', function(event,ui) {
				$j(this).draggable('option','cursorAt',false);
			})
			.on('dragstart', '.calendar_calEvent:not(.rowNoEdit)', function(event,ui) {
				$j('.calendar_calEvent',ui.helper).width($j(this).width())
					.height($j(this).outerHeight())
					.appendTo(ui.helper);
			})
			.on('dragstop','.calendar_calEvent:not(.rowNoEdit)', function(event,ui) {
				var e = new jQuery.Event('change');
				e.originalEvent = event;
				e.data = {start: 0};
				if (typeof this.dropEnd != 'undefined' && this.dropEnd.length >= 1)
				{
					var drop_date = this.dropEnd.attr('data-date')||false;

					var eT = parseInt(this.dropEnd.attr('data-hour') * 60) + parseInt(this.dropEnd.attr('data-minute'));
					
					var event_data = timegrid._get_event_info(this);
					var event_widget = timegrid.getWidgetById(event_data.id);

					if(event_widget)
					{
						event_widget._parent.date_helper.set_year(drop_date.substring(0,4));
						event_widget._parent.date_helper.set_month(drop_date.substring(4,6));
						event_widget._parent.date_helper.set_date(drop_date.substring(6,8));
						event_widget._parent.date_helper.set_hours(this.dropEnd.attr('data-hour'));
						event_widget._parent.date_helper.set_minutes(this.dropEnd.attr('data-minute'));
						event_widget.options.value.start = event_widget._parent.date_helper.getValue();
					
						event_widget.recur_prompt(function(button_id) {
							//Get infologID if in case if it's an integrated infolog event
							if (event_data.app === 'infolog')
							{
								// If it is an integrated infolog event we need to edit infolog entry
								egw().json('stylite_infolog_calendar_integration::ajax_moveInfologEvent', [event_data.id, event_widget.options.value.start||false]).sendRequest();
							}
							else
							{
								//Edit calendar event
								egw().json('calendar.calendar_uiforms.ajax_moveEvent',[button_id=='series' ? event_data.id : event_data.app_id,event_data.owner, event_widget.options.value.start,	timegrid.options.owner||egw.user('account_id')]).sendRequest();
							}
						});
					}
				}
			})
			// As event is dragged, update the time
			.on('drag', '.calendar_calEvent:not(.rowNoEdit)', function(event,ui) {
				this.dropEnd = drag_helper.call($j('.calendar_calEventHeader',ui.helper)[0],event,ui.helper[0],0);
				$j('.calendar_timeDemo',ui.helper).css('bottom','auto');
			});

		// Bind scroll event
		// When the user scrolls, we'll move enddate - startdate days
		this.div.on('wheel',jQuery.proxy(function(e) {
			var direction = e.originalEvent.deltaY > 0 ? 1 : -1;

			this.date_helper.set_value(this.options.end_date || this.options.start_date);
			var end = this.date_helper.get_time();

			this.date_helper.set_value(this.options.start_date);
			var start = this.date_helper.get_time();
			
			var delta = 1000 * 60 * 60 * 24 + Math.max(0,end - start);
			
			// TODO - actually fetch new data
			this.set_start_date(new Date(start + (delta * direction )));
			this.set_end_date(new Date(end + (delta * direction)));
			
			e.preventDefault();
			return false;
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
	 * @param {boolean} trigger=false Trigger an event once things are done.
	 *	Waiting until invalidate completes prevents 2 updates when changing the date range.
	 * @returns {undefined}
	 */
	invalidate: function(trigger) {

		// Reset the list of days
		this.day_list = [];

		// Wait a bit to see if anything else changes, then re-draw the days
		if(this.update_timer === null)
		{
			this.update_timer = window.setTimeout(jQuery.proxy(function() {
				this.widget.update_timer = null;

				// Update actions
				if(this._actionManager)
				{
					this._link_actions(this._actionManager.children);
				}
				
				this.widget._drawDays();
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
		this.invalidate();
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
		var rowHeight = (100/rowsToDisplay).toFixed(1);
		this.rowHeight = this.div.height() / rowsToDisplay;

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
			html += '<div class="calendar_calTimeRowTime et2_clickable" data-time="'+time.trim()+'" data-hour="'+Math.floor(t/60)+'" data-minute="'+(t%60)+'">'+time_label+"</div></div>\n";
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
		// TODO: need data doesn't take category & other filters into account
		var need_data = true;
		for(var i = 0; i < this.day_list.length; i++)
		{
			day = this.day_widgets[i];
			// Set the date, and pass any data we have
			if(typeof this.value[this.day_list[i]] === 'undefined') need_data = true;
			if(day.options.owner != this.options.owner) need_data = true;

			day.set_date(this.day_list[i], this.value[this.day_list[i]] || false);
			day.set_owner(this.options.owner);
			day.set_id(this.day_list[i]);
			day.set_width((100/this.day_list.length).toFixed(2) + '%');

			// Position
			$j(day.getDOMNode()).css('left', ((100/this.day_list.length).toFixed(2) * i) + '%');
		}
		
		// Fetch any needed data
		if(need_data)
		{
			this._fetch_data();
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
			if(show_weekend || !show_weekend && [0,6].indexOf(this.date_helper.date.getUTCDay()) === -1 || end_date == start_date)
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
		return;
// Ug.
		// This binds into the egw action system.  Most user interactions (drag to move, resize)
		// are handled internally using jQuery directly.
		var widget_object = parent.getObjectById(this.id);
		var aoi = new et2_action_object_impl(this,this.getDOMNode());
		aoi.doTriggerEvent = function(_event, _data) {

			// Determine target node
			var event = _data.event || false;
			if(!event) return;
			var nodes = $j('.calendar_calAddEvent[data-hour]',this.doGetDOMNode()).filter(function() {
				var offset = $j(this).offset();
				var range={x:[offset.left,offset.left+$j(this).outerWidth()],y:[offset.top,offset.top+$j(this).outerHeight()]};
				return(event.pageX >=range.x[0]  && event.pageX <= range.x[1]) && (event.pageY >= range.y[0] && event.pageY <= range.y[1]);
			});

			switch(_event)
			{
				case EGW_AI_DRAG_OVER:
					// Highlight target time, and display time in helper
					if(nodes.length)
					{
						// Highlight the destination time
						$j('[data-date]',this.doGetDOMNode()).removeClass("ui-state-active");
						nodes.addClass('ui-state-active');

						// Update the helper with the actual time
						var time = jQuery.datepicker.formatTime(
							egw.preference("timeformat") == 12 ? "h:mmtt" : "HH:mm",
							{
								hour: nodes.attr('data-hour'),
								minute: nodes.attr('data-minute'),
								seconds: 0,
								timezone: 0
							},
							{"ampm": (egw.preference("timeformat") == "12")}
						);

						_data.ui.helper[0].innerHTML = '<div class="calendar_d-n-d_timeCounter"><span>'+time+'</span></div>';
						if(_data.ui.draggable)
						{
							_data.ui.draggable
								.off('.et2_timegrid')
								.on('drag.et2_timegrid',jQuery.proxy(function(event, ui) {this.doTriggerEvent(EGW_AI_DRAG_OVER,{event:event,ui:ui});},this))
							_data.ui.helper.css('width', _data.ui.draggable.width()+'px')
								.css('height', _data.ui.draggable.height()+'px');
						}
					}
					break;
				case EGW_AI_DRAG_OUT:
					// Reset
					$j('[data-date]',this.doGetDOMNode()).removeClass("ui-state-active");
					_data.ui.draggable.off('.et2_timegrid');
					$j('.calendar_d-n-d_timeCounter',_data.ui.helper[0]).remove();
					break;
			}
		};
		if (widget_object == null) {
			// Add a new container to the object manager which will hold the widget
			// objects
			widget_object = parent.insertObject(false, new egwActionObject(
				this.id, parent, aoi,
				parent.manager.getActionById(this.id) || parent.manager
			));
		}
		else
		{
			widget_object.setAOI(aoi);
		}
		
		// Delete all old objects
		widget_object.clear();
		widget_object.unregisterActions();

		// Go over the widget & add links - this is where we decide which actions are
		// 'allowed' for this widget at this time
		var action_links = this._get_action_links(actions);


		this._init_links_dnd(widget_object.manager, action_links);
		
		widget_object.updateActionLinks(action_links);
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
			if(action.type == 'drop')
			{
				action_links.push(typeof action.id != 'undefined' ? action.id : i);
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
				var updated_days = {};
				for(var i = 0; i < data.order.length && data.total; i++)
				{
					var record = this.egw().dataGetUIDdata(data.order[i]);
					if(record && record.data)
					{
						if(typeof updated_days[record.data.date] === 'undefined')
						{
							updated_days[record.data.date] = [];
						}
						// Copy, to avoid unwanted changes by reference
						updated_days[record.data.date].push(jQuery.extend({},record.data));

						// Check for multi-day events listed once
						// Date must stay a string or we might cause problems with nextmatch
						var dates = {
							start: typeof record.data.start === 'string' ? record.data.start : record.data.start.toJSON(),
							end: typeof record.data.end === 'string' ? record.data.end : record.data.end.toJSON(),
						};
						if(dates.start.substr(0,10) != dates.end.substr(0,10))
						{
							this.date_helper.set_value(record.data.end);
							var end = this.date_helper.date.getTime();
							this.date_helper.set_value(record.data.start);

							do
							{
								var expanded_date = ''+this.date_helper.get_year() + sprintf('%02d',this.date_helper.get_month()) + sprintf('%02d',this.date_helper.get_date());
								if(typeof(updated_days[expanded_date]) == 'undefined')
								{
									updated_days[expanded_date] = [];
								}
								if(record.data.date !== expanded_date)
								{
									// Copy, to avoid unwanted changes by reference
									updated_days[expanded_date].push(jQuery.extend({},record.data));
								}
								this.date_helper.set_date(this.date_helper.get_date()+1);
							}
							// Limit it to 14 days to avoid infinite loops in case something is mis-set,
							// though the limit is more based on how wide the screen is
							while(end >= this.date_helper.date.getTime() && i <= 14)
						}
					}
				}
				for(var i = 0; i < this.day_list.length; i++)
				{
					var day = this.day_widgets[i];
					day.set_date(this.day_list[i], updated_days[this.day_list[i]]||[], true);

					this.value[this.day_list[i]] = updated_days[this.day_list[i]];
				}
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

		var use_days_sent = true;

		if(events.owner)
		{
			this.set_owner(events.owner);
			delete events.owner;
		}
		if(events.start_date)
		{
			this.set_start_date(events.start_date);
			delete events.start_date;
			use_days_sent = false;
		}
		if(events.end_date)
		{
			this.set_end_date(events.end_date);
			delete events.end_date;
			use_days_sent = false;
		}

		this.value = events || {};

		if(use_days_sent)
		{
			var day_list = Object.keys(events);
			if(day_list.length)
			{
				this.set_start_date(day_list[0]);
				this.set_end_date(day_list[day_list.length-1]);
			}
		}

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
		if(!new_date || new_date === null)
		{
			throw exception('Invalid start date. ' + new_date.toString());
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
			throw exception('Invalid end date. ' + new_date.toString());
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

		var old_date = this.options.end_date;
		this.options.end_date = this.date_helper.getValue();

		if(old_date !== this.options.end_date && this.isAttached())
		{
			this.invalidate(true);
		}
	},

	/**
	 * Call change handler, if set
	 */
	change: function() {
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
	 * Turn on or off the visibility of weekends
	 *
	 * @param {boolean} weekends
	 */
	set_show_weekend: function(weekends)
	{
		if(this.options.show_weekend !== weekends)
		{
			this.options.show_weekend = weekends ? true : false;
			if(this.isAttached())
			{
				this.invalidate();
			}
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
				et2_calendar_event.recur_prompt(event);

				return false;
			}
			return result;
		}
		else if (_ev.target.dataset.date)
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
	 * Set which user owns this.  Owner is passed along to the individual
	 * days.
	 *
	 * @param {number} _owner Account ID
	 * @returns {undefined}
	 */
	set_owner: function(_owner)
	{
		var old = this.options.owner || 0;

		// Let select-account widget handle value validation
		this.owner.set_value(typeof _owner == "string" || typeof _owner == "number" ? _owner : jQuery.extend([],_owner));

		this.options.owner = _owner;//this.owner.getValue();
		if(old !== this.options.owner && this.isAttached())
		{
			this.invalidate(true);
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