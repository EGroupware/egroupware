/*
 * Egroupware Calendar timegrid
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */


/*egw:uses
	/calendar/js/et2_widget_view.js;
	/calendar/js/et2_widget_planner_row.js;
	/calendar/js/et2_widget_event.js;
*/

/**
 * Class which implements the "calendar-planner" XET-Tag for displaying a longer
 * ( > 10 days) span of time.  Events can be grouped into rows by either user,
 * category, or month.  Their horizontal position and size in the row is determined
 * by their start date and duration relative to the displayed date range.
 *
 * @augments et2_calendar_view
 */
var et2_calendar_planner = (function(){ "use strict"; return et2_calendar_view.extend([et2_IDetachedDOM, et2_IResizeable],
{
	createNamespace: true,

	attributes: {
		group_by: {
			name: "Group by",
			type: "string", // or category ID
			default: "0",
			description: "Display planner by 'user', 'month', or the given category"
		},
		filter: {
			name: "Filter",
			type: "string",
			default: '',
			description: 'A filter that is used to select events.  It is passed along when events are queried.'
		},
		show_weekend: {
			name: "Weekends",
			type: "boolean",
			default: egw.preference('days_in_weekview','calendar') != 5,
			description: "Display weekends.  The date range should still include them for proper scrolling, but they just won't be shown."
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
		this.div = jQuery(document.createElement("div"))
			.addClass("calendar_plannerWidget");

		// Header
		this.gridHeader = jQuery(document.createElement("div"))
			.addClass("calendar_plannerHeader")
			.appendTo(this.div);
		this.headerTitle = jQuery(document.createElement("div"))
			.addClass("calendar_plannerHeaderTitle")
			.appendTo(this.gridHeader);
		this.headers = jQuery(document.createElement("div"))
			.addClass("calendar_plannerHeaderRows")
			.appendTo(this.gridHeader);

		this.rows = jQuery(document.createElement("div"))
			.addClass("calendar_plannerRows")
			.appendTo(this.div);
		this.grid = jQuery(document.createElement("div"))
			.addClass("calendar_plannerGrid")
			.appendTo(this.div);

		this.vertical_bar = jQuery(document.createElement("div"))
			.addClass('verticalBar')
			.appendTo(this.div);

		this.value = [];

		// Update timer, to avoid redrawing twice when changing start & end date
		this.update_timer = null;
		this.doInvalidate = true;

		this.setDOMNode(this.div[0]);

		this.registeredCallbacks = [];
	},

	destroy: function() {
		this._super.apply(this, arguments);
		this.div.off();

		for(var i = 0; i < this.registeredCallbacks.length; i++)
		{
			egw.dataUnregisterUID(this.registeredCallbacks[i],false,this);
		}
	},

	doLoadingFinished: function() {
		this._super.apply(this, arguments);

		// Don't bother to draw anything if there's no date yet
		if(this.options.start_date)
		{
			this._drawGrid();
		}

		// Automatically bind drag and resize for every event using jQuery directly
		// - no action system -
		var planner = this;

		/**
		 * If user puts the mouse over an event, then we'll set up resizing so
		 * they can adjust the length.  Should be a little better on resources
		 * than binding it for every calendar event.
		 */
		this.div.on('mouseover', '.calendar_calEvent:not(.ui-resizable):not(.rowNoEdit)', function() {
				// Load the event
				planner._get_event_info(this);
				var that = this;

				//Resizable event handler
				jQuery(this).resizable
				({
					distance: 10,
					grid: [5, 10000],
					autoHide: false,
					handles: 'e',
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
					 * If dragging to resize an event, abort drag to create
					 *
					 * @param {jQuery.Event} event
					 * @param {Object} ui
					 */
					start: function(event, ui)
					{
						if(planner.drag_create.start)
						{
							// Abort drag to create, we're dragging to resize
							planner._drag_create_end({});
						}
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
						var event_data = planner._get_event_info(this);
						var event_widget = planner.getWidgetById(event_data.widget_id);
						var sT = event_widget.options.value.start_m;
						if (typeof this.dropEnd != 'undefined')
						{
							var eT = parseInt(this.dropEnd.getUTCHours() * 60) + parseInt(this.dropEnd.getUTCMinutes());
							e.data.duration = ((eT - sT)/60) * 3600;

							if(event_widget)
							{
								event_widget.options.value.end_m = eT;
								event_widget.options.value.duration = e.data.duration;
							}

							// Leave the helper there until the update is done
							var loading = ui.helper.clone().appendTo(ui.helper.parent());

							// and add a loading icon so user knows something is happening
							jQuery('.calendar_timeDemo',loading).after('<div class="loading"></div>');

							jQuery(this).trigger(e);

							// That cleared the resize handles, so remove for re-creation...
							jQuery(this).resizable('destroy');

							// Remove loading, done or not
							loading.remove();
						}
						// Clear the helper, re-draw
						if(event_widget)
						{
							event_widget._parent.position_event(event_widget);
						}
					},

					/**
					 * Triggered during the resize, on the drag of the resize handler
					 *
					 * @param {event} event
					 * @param {Object} ui
					 */
					resize:function(event, ui)
					{
						if(planner.options.group_by == 'month')
						{
							var position = {left: event.clientX, top: event.clientY};
						}
						else
						{
							var position = {top:ui.position.top, left: ui.position.left + ui.helper.width()};
						}
						planner._drag_helper(this,position,ui.helper.outerHeight());
					}
				});
			})
			.on('mousemove', function(event) {
				// Not when over header
				if(jQuery(event.target).closest('.calendar_eventRows').length == 0)
				{
					planner.vertical_bar.hide();
					return;
				}
				// Position bar by mouse
				planner.vertical_bar.position({
					my: 'right-1',
					of: event,
					collision: 'fit'
				});
				planner.vertical_bar.css('top','0px');

				// Get time at mouse
				if(planner.options.group_by == 'month')
				{
					var time = planner._get_time_from_position(event.clientX, event.clientY);
				}
				else
				{
					var time = planner._get_time_from_position(event.offsetX, event.offsetY);
				}
				// Passing to formatter, cancel out timezone
				if(time)
				{
					var formatDate = new Date(time.valueOf() + time.getTimezoneOffset() * 60 * 1000);
					planner.vertical_bar
						.html('<span>'+date(egw.preference('timeformat','calendar') == 12 ? 'h:ia' : 'H:i',formatDate)+'</span>')
						.show();

					if(planner.drag_create.event && planner.drag_create.parent && planner.drag_create.end)
					{

						planner.drag_create.end.date = time.toJSON()
						planner._drag_update_event();
					}
				}
				else
				{
					// No (valid) time, just hide
					planner.vertical_bar.hide();
				}
			})
			.on('mousedown', jQuery.proxy(this._mouse_down, this))
			.on('mouseup', jQuery.proxy(this._mouse_up, this));

		// Actions may be set on a parent, so we need to explicitly get in here
		// and get ours
		this._link_actions(this.options.actions || this._parent.options.actions || []);

		// Customize and override some draggable settings
		this.div.on('dragcreate','.calendar_calEvent', function(event, ui) {
				jQuery(this).draggable('option','cancel','.rowNoEdit');
				// Act like you clicked the header, makes it easier to position
				jQuery(this).draggable('option','cursorAt', {top: 5, left: 5});
			})
			.on('dragstart', '.calendar_calEvent', function(event,ui) {
				jQuery('.calendar_calEvent',ui.helper).width(jQuery(this).width())
					.height(jQuery(this).outerHeight())
					.css('top', '').css('left','')
					.appendTo(ui.helper);
				ui.helper.width(jQuery(this).width());

				// Cancel drag to create, we're dragging an existing event
				planner._drag_create_end();
			});
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
				if(day_count >= 6)
				{
					this.headers.append(this._header_months(start, day_count));
				}
				if(day_count < 120)
				{
					var weeks = this._header_weeks(start, day_count);
					this.headers.append(weeks);
					this.grid.append(weeks);
				}
				if(day_count < 60)
				{
					var days = this._header_days(start, day_count);
					this.headers.append(days);
					this.grid.append(days);
				}
				if(day_count <= 7)
				{
					var hours = this._header_hours(start, day_count);
					this.headers.append(hours);
					this.grid.append(hours);
				}
			},
			// Labels for the rows
			row_labels: function() {
				var labels = [];
				var already_added = [];
				for(var i = 0; i < this.options.owner.length; i++)
				{
					var user = this.options.owner[i];
					if (user < 0)	// groups
					{
						egw.accountData(user,'account_fullname',true,function(result) {
							for(var id in result)
							{
								if(already_added.indexOf(''+id) < 0)
								{
									this.push({id: id, label: result[id]||'', data: {participants:id,owner:id}});
									already_added.push(''+id);
								}
							}
						},labels);
					}
					else	// users
					{
						if(already_added.indexOf(user) < 0)
						{
							var label = this._get_owner_name(user)||'';
							labels.push({id: user, label: label, data: {participants:user,owner:''}});
							already_added.push(''+user);
						}
					}
				}

				return labels.sort(function(a,b) {
					return a.label.localeCompare(b.label);
				});
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
				var participants = event.participants;
				var add_row = function(user, participant) {
					var label_index = false;
					for(var i = 0; i < labels.length; i++)
					{
						if(labels[i].id == user)
						{
							label_index = i;
							break;
						}
					}
					if(participant && label_index !== false && status_to_show.indexOf(participant.substr(0,1)) >= 0 ||
						this.options.filter === 'owner' && event.owner === user)
					{
						if(typeof rows[label_index] === 'undefined')
						{
							rows[label_index] = [];
						}
						rows[label_index].push(event);
					}
				};
				for(var user in participants)
				{
					var participant = participants[user];
					if (parseInt(user) < 0)	// groups
					{
						var planner = this;
						egw.accountData(user,'account_fullname',true,function(result) {
							for(var id in result)
							{
								if(!participants[id]) add_row.call(planner,id,participant);
							}
						},labels);
						continue;
					}
					add_row.call(this, user, participant);
				}
			},
			// Draw a single row
			draw_row: function(sort_key, label, events) {
				if(['user','both'].indexOf(egw.preference('planner_show_empty_rows','calendar')) !== -1 || events.length)
				{
					return this._drawRow(sort_key, label,events,this.options.start_date, this.options.end_date);
				}
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
				var labels = [];
				var d = new Date(this.options.start_date);
				d = new Date(d.valueOf() + d.getTimezoneOffset() * 60 * 1000);
				for(var i = 0; i < 12; i++)
				{
					// Not using UTC because we corrected for timezone offset
					labels.push({id: d.getFullYear() +'-'+d.getMonth(), label:this.egw().lang(date('F',d))+' '+d.getFullYear()});
					d.setMonth(d.getMonth()+1);
				}
				return labels;
			},
			group: function(labels, rows,event) {
				// Yearly planner does not show infologs
				if(event && event.app && event.app == 'infolog') return;

				var start = new Date(event.start);
				start = new Date(start.valueOf() + start.getTimezoneOffset() * 60 * 1000);
				var key = start.getFullYear() +'-'+start.getMonth();
				var label_index = false;
				for(var i = 0; i < labels.length; i++)
				{
					if(labels[i].id == key)
					{
						label_index = i;
						break;
					}
				}
				if(typeof rows[label_index] === 'undefined')
				{
					rows[label_index] = [];
				}
				rows[label_index].push(event);

				// end in a different month?
				var end = new Date(event.end);
				end = new Date(end.valueOf() + end.getTimezoneOffset() * 60 * 1000);
				var end_key = end.getFullYear() +'-'+end.getMonth();
				var year = start.getFullYear();
				var month = start.getMonth();
				while(key !== end_key)
				{
					if (++month > 11)
					{
						++year;
						month = 0;
					}
					key = sprintf('%04d-%d',year,month);
					for(var i = 0; i < labels.length; i++)
					{
						if(labels[i].id == key)
						{
							label_index = i;
							if(typeof rows[label_index] === 'undefined')
							{
								rows[label_index] = [];
							}
							break;
						}
					}
					rows[label_index].push(event);
				}
			},
			// Draw a single row, but split up the dates
			draw_row: function(sort_key, label, events)
			{
				var key = sort_key.split('-');
				var start = new Date(key[0]+"-"+sprintf("%02d",parseInt(key[1])+1)+"-01T00:00:00Z");
				// Use some care to avoid issues with timezones and daylight savings
				var end = new Date(start);
				end.setUTCMonth(start.getUTCMonth() + 1);
				end.setUTCDate(1);
				end.setUTCHours(0);
				end.setUTCMinutes(0);
				end = new Date(end.valueOf() - 1000);
				end.setUTCMonth(start.getUTCMonth())
				this._drawRow(sort_key, label, events, start, end);
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

				if(day_count >= 6)
				{
					this.headers.append(this._header_months(start, day_count));
				}
				if(day_count < 120)
				{
					var weeks = this._header_weeks(start, day_count);
					this.headers.append(weeks);
					this.grid.append(weeks);
				}
				if(day_count < 60)
				{
					var days = this._header_days(start, day_count);
					this.headers.append(days);
					this.grid.append(days);
				}
				if(day_count <= 7)
				{
					var hours = this._header_hours(start, day_count);
					this.headers.append(hours);
					this.grid.append(hours);
				}
			},
			row_labels: function() {
				var im = this.getInstanceManager();
				var categories = et2_selectbox.cat_options({
						_type:'select-cat',
						getInstanceManager: function() {return im;}
					},{application: 'calendar'});

				var labels = [];
				if(!app.calendar.state.cat_id ||
					app.calendar.state.cat_id.toString() === '' ||
					app.calendar.state.cat_id.toString() == '0'
				)
				{
					app.calendar.state.cat_id = '';
					labels.push({id:'',value:'',label: egw.lang('none'), main: '', data: {}});
					labels = labels.concat(categories);
				}
				else
				{
					var cat_id = app.calendar.state.cat_id;
					if(typeof cat_id == 'string')
					{
						cat_id = cat_id.split(',');
					}
					for(var i = 0; i < cat_id.length; i++)
					{
						// Find label for that category
						for(var j = 0; j < categories.length; j++)
						{
							if(categories[j].value == cat_id[i])
							{
								categories[j].id = categories[j].value;
								labels.push(categories[j]);
								break;
							}
						}

						// Get its children immediately
						egw.json(
							'EGroupware\\Api\\Etemplate\\Widget\\Select::ajax_get_options',
							['select-cat',',,,calendar,'+cat_id[i]],
							function(data) {
								labels = labels.concat(data);
							}
						).sendRequest(false);
					}
				}

				for(var i = labels.length -1; i >= 0; i--)
				{
					labels[i].id = labels[i].value;
					labels[i].data = {
						cat_id: labels[i].id,
						main: labels[i].value==labels[i].main
					};
					if(labels[i].children && labels[i].children.length)
					{
						labels[i].data.has_children = true;
					}
				}
				return labels;
			},
			group: function(labels, rows, event) {
				var cats = event.category;
				if(typeof event.category === 'string')
				{
					cats = cats.split(',');
				}
				for(var cat = 0; cat < cats.length; cat++)
				{
					var label_index = false;
					var category = cats[cat] ? parseInt(cats[cat],10) : false;
					if(category == 0 || !category) category = '';
					for(var i = 0; i < labels.length; i++)
					{
						if(labels[i].id == category)
						{
							// If there's no cat filter, only show the top level
							if(!app.calendar.state.cat_id)
							{
								for(var j = 0; j < labels.length; j++)
								{
									if(labels[j].id == labels[i].main)
									{
										label_index = j;
										break;
									}
								}
								break;
							}
							label_index = i;
							break;
						}
					}
					if(typeof rows[label_index] === 'undefined')
					{
						rows[label_index] = [];
					}
					if(rows[label_index].indexOf(event) === -1)
					{
						rows[label_index].push(event);
					}
				}
			},
			draw_row: function(sort_key, label, events) {
				if(['cat','both'].indexOf(egw.preference('planner_show_empty_rows','calendar')) !== -1 || events.length)
				{
					return this._drawRow(sort_key, label,events,this.options.start_date, this.options.end_date);
				}
			}
		}
	},

	/**
	 * Something changed, and the planner needs to be re-drawn.  We wait a bit to
	 * avoid re-drawing twice if start and end date both changed, then recreate.
	 *
	 * @param {boolean} trigger =false Trigger an event once things are done.
	 *	Waiting until invalidate completes prevents 2 updates when changing the date range.
	 * @returns {undefined}
	 */
	invalidate: function(trigger) {

		// Busy
		if(!this.doInvalidate) return;

		// Not yet ready
		if(!this.options.start_date || !this.options.end_date) return;

		// Wait a bit to see if anything else changes, then re-draw the days
		if(this.update_timer !== null)
		{
			window.clearTimeout(this.update_timer);
		}
		this.update_timer = window.setTimeout(jQuery.proxy(function() {
			this.widget.doInvalidate = false;

			// Show AJAX loader
			this.widget.loader.show();

			this.widget.value = this.widget._fetch_data();

			this.widget._drawGrid();

			if(this.trigger)
			{
				this.widget.change();
			}
			this.widget.update_timer = null;
			this.widget.doInvalidate = true;

			window.setTimeout(jQuery.proxy(function() {if(this.loader) this.loader.hide();},this.widget),500);
		},{widget:this,"trigger":trigger}),ET2_GRID_INVALIDATE_TIMEOUT);
	},

	detachFromDOM: function() {
		// Remove the binding to the change handler
		jQuery(this.div).off("change.et2_calendar_timegrid");

		this._super.apply(this, arguments);
	},

	attachToDOM: function() {
		this._super.apply(this, arguments);

		// Add the binding for the event change handler
		jQuery(this.div).on("change.et2_calendar_timegrid", '.calendar_calEvent', this, function(e) {
			// Make sure function gets a reference to the widget
			var args = Array.prototype.slice.call(arguments);
			if(args.indexOf(this) == -1) args.push(this);

			return e.data.event_change.apply(e.data, args);
		});

		// Add the binding for the change handler
		jQuery(this.div).on("change.et2_calendar_timegrid", '*:not(.calendar_calEvent)', this, function(e) {
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
		this.rows.empty()
			.append(this.grid);
		this.grid.empty();

		var grouper = this.groupers[isNaN(this.options.group_by) ? this.options.group_by : 'category'];
		if(!grouper) return;

		// Headers
		this.headers.empty();
		this.headerTitle.text(grouper.title.apply(this));
		grouper.headers.apply(this);
		this.grid.find('*').contents().filter(function(){
			return this.nodeType === 3;
		}).remove();

		// Get the rows / labels
		var labels = grouper.row_labels.call(this);

		// Group the events
		var events = {};
		for(var i = 0; i < this.value.length; i++)
		{
			grouper.group.call(this, labels, events, this.value[i]);
		}

		// Set height for rows
		this.rows.height(this.div.height() - this.headers.outerHeight());

		// Draw the rows
		for(var key in labels)
		{
			if (!labels.hasOwnProperty(key)) continue;

			// Skip sub-categories (events are merged into top level)
			if(this.options.group_by == 'category' &&
				(!app.calendar.state.cat_id || app.calendar.state.cat_id == '') &&
				labels[key].id != labels[key].main
			)
			{
				continue;
			}
			var row = grouper.draw_row.call(this,labels[key].id, labels[key].label, events[key] || []);

			// Add extra data for clicking on row
			if(row)
			{
				for(var extra in labels[key].data)
				{
					row.getDOMNode().dataset[extra] = labels[key].data[extra];
				}
			}
		}

		// Adjust header if there's a scrollbar
		if(this.rows.children().last().length)
		{
			this.gridHeader.css('margin-right', (this.rows.width() - this.rows.children().last().width()) + 'px');
		}
		this.value = [];
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
				id: 'planner_row_'+key,
				label: label,
				start_date: start,
				end_date: end,
				value: events,
				readonly: this.options.readonly
			},this);


		if(this.isInTree())
		{
			row.doLoadingFinished();
		}

		// Add actual events
		row._update_events(events);

		return row;
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

		var title = this.egw().lang(date('F',start))+' '+date('Y',start)+' - '+
			this.egw().lang(date('F',end))+' '+date('Y',end);

		content += '<div class="calendar_plannerMonthScale th et2_link" style="left: 0; width: 100%;">'+
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
		var end = new Date(start);
		end.setUTCDate(end.getUTCDate()+days);
		var t = new Date(start.valueOf());
		for(var left = 0,i = 0; i < days;t.setUTCDate(1),t.setUTCMonth(t.getUTCMonth()+1),left += days_in_month*day_width,i += days_in_month)
		{
			var u = new Date(t.getUTCFullYear(),t.getUTCMonth()+1,0,-t.getTimezoneOffset()/60);
			days_in_month =  1+ ((u-t) / (24*3600*1000));

			var first = new Date(t.getUTCFullYear(),t.getUTCMonth(),1,-t.getTimezoneOffset()/60);
			if(days_in_month <= 0) break;

			if (i + days_in_month > days)
			{
				days_in_month = days - i;
			}
			var title = this.egw().lang(date('F',new Date(t.valueOf() + t.getTimezoneOffset() * 60 * 1000)));
			if (days_in_month > 10)
			{
				title += '</span> <span class="et2_clickable et2_link" data-sortby="month">'+t.getUTCFullYear();
			}
			else if (days_in_month < 5)
			{
				title = '&nbsp;';
			}
			content += '<div class="calendar_plannerMonthScale" data-date="'+first.toJSON()+
				'" style="left: '+left+'%; width: '+(day_width*days_in_month)+'%;"><span'+
				' data-planner_view="month" class="et2_clickable et2_link">'+
				title+"</span></div>";
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

		var content = '<div class="calendar_plannerScale" data-planner_view="week">';
		var state = '';

		// we're not using UTC so date() formatting function works
		var t = new Date(start.valueOf());

		// Make sure we're lining up on the week
		var week_end = app.calendar.date.end_of_week(start);
		var days_in_week = Math.floor(((week_end-start ) / (24*3600*1000))+1);
		var week_width = 100 / days * (days <= 7 ? days : days_in_week);
		for(var left = 0,i = 0; i < days; t.setUTCDate(t.getUTCDate() + 7),left += week_width)
		{
			// Avoid overflow at the end
			if(days - i < 7)
			{
				days_in_week = days-i;
			}
			var usertime = new Date(t.valueOf());
			if(start.getTimezoneOffset() < 0)
			{
				// Gets the right week # east of GMT.  West does not need it(?)
				usertime.setUTCMinutes(usertime.getUTCMinutes() - start.getTimezoneOffset());
			}

			week_width = 100 / days * Math.min(days, days_in_week);

			var title = this.egw().lang('Week')+' '+app.calendar.date.week_number(usertime);

			if(start.getTimezoneOffset() > 0)
			{
				// Gets the right week start west of GMT
				usertime.setUTCMinutes(usertime.getUTCMinutes() +start.getTimezoneOffset());
			}
			state = app.calendar.date.start_of_week(usertime);
			state.setUTCHours(0);
			state.setUTCMinutes(0);
			state = state.toJSON();

			if(days_in_week > 1 || days == 1)
			{
				content += '<div class="calendar_plannerWeekScale et2_clickable et2_link" data-date=\'' + state + '\' style="left: '+left+'%; width: '+week_width+'%;">'+title+"</div>";
			}
			i+= days_in_week;
			if(days_in_week != 7)
			{
				t.setUTCDate(t.getUTCDate() - (7 - days_in_week));
				days_in_week = 7;
			}
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
		var content = '<div class="calendar_plannerScale'+(days > 3 ? 'Day' : '')+'" data-planner_view="day" >';

		// we're not using UTC so date() formatting function works
		var t = new Date(start.valueOf() + start.getTimezoneOffset() * 60 * 1000);
		for(var left = 0,i = 0; i < days; t.setDate(t.getDate()+1),left += day_width,++i)
		{
			if(!this.options.show_weekend && [0,6].indexOf(t.getDay()) !== -1 ) continue;
			var holidays = [];
			var tempDate = new Date(t);
			tempDate.setMinutes(tempDate.getMinutes()-start.getTimezoneOffset());
			var day_class = this.day_class_holiday(tempDate,holidays);
			var title = '';
			var state = '';

			if (days <= 3)
			{
				title = this.egw().lang(date('l',t))+', '+date('j',t)+'. '+this.egw().lang(date('F',t));
			}
			else if (days <= 7)
			{
				title = this.egw().lang(date('l',t))+' '+date('j',t);
			}
			else
			{
				title = this.egw().lang(date('D',t)).substr(0,2)+'<br />'+date('j',t);
			}
			state = new Date(t.valueOf() - start.getTimezoneOffset() * 60 * 1000).toJSON();

			content += '<div class="calendar_plannerDayScale et2_clickable et2_link '+ day_class+
				'" data-date=\'' + state +'\''+
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
			var t = new Date(start.getUTCFullYear(),start.getUTCMonth(),start.getUTCDate(),-start.getTimezoneOffset()/60);
			var s = new Date(start);
			s.setUTCHours(23);
			s.setUTCMinutes(59);
			s.setUTCSeconds(59);
			hours = Math.ceil((s.getTime() - t.getTime()) / 3600000);
		}
		var cell_width = 100 / hours * decr;

		var content = '<div class="calendar_plannerScale" data-planner_view="day">';

		// we're not using UTC so date() formatting function works
		var t = new Date(start.valueOf() + start.getTimezoneOffset() * 60 * 1000);
		for(var left = 0,i = 0; i < hours; left += cell_width,i += decr)
		{
			if(!this.options.show_weekend && [0,6].indexOf(t.getDay()) !== -1 ) continue;
			var title = date(egw.preference('timeformat','calendar') == 12 ? 'ha' : 'H',t);

			content += '<div class="calendar_plannerHourScale et2_link" data-date="' + t.toJSON() +'" style="left: '+left+'%; width: '+(cell_width)+'%;">'+title+"</div>";
			t.setHours(t.getHours()+decr);
		}
		content += "</div>";		// end of plannerScale

		return content;
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
		var holidays = et2_calendar_view.get_holidays(this,date.getUTCFullYear());

		// Pass a string rather than the date object, to make sure it doesn't get changed
		this.date_helper.set_value(date.toJSON());
		var date_key = ''+this.date_helper.get_year() + sprintf('%02d',this.date_helper.get_month()) + sprintf('%02d',this.date_helper.get_date());
		if(holidays && holidays[date_key])
		{
			holidays = holidays[date_key];
			for(var i = 0; i < holidays.length; i++)
			{
				if (typeof holidays[i]['birthyear'] !== 'undefined')
				{
					day_class += ' calendar_calBirthday ';

					holiday_list.push(holidays[i]['name']);
				}
				else
				{
					day_class += 'calendar_calHoliday ';

					holiday_list.push(holidays[i]['name']);
				}
			}
		}
		holidays = holiday_list.join(',');
		var today = new Date();
		if(date_key === ''+today.getFullYear()+
			sprintf("%02d",today.getMonth()+1)+
			sprintf("%02d",today.getDate())
		)
		{
			day_class += "calendar_calToday ";
		}
		if(date.getUTCDay() == 0 || date.getUTCDay() == 6)
		{
			day_class += "calendar_weekend ";
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
		if(!this._actionObject)
		{
			// Get the parent?  Might be a grid row, might not.  Either way, it is
			// just a container with no valid actions
			var objectManager = egw_getObjectManager(this.getInstanceManager().app,true,1);
			objectManager = objectManager.getObjectById(this.getInstanceManager().uniqueId,2) || objectManager;
			var parent = objectManager.getObjectById(this.id,3) || objectManager.getObjectById(this._parent.id,3) || objectManager;
			if(!parent)
			{
				debugger;
				egw.debug('error','No parent objectManager found');
				return;
			}

			for(var i = 0; i < parent.children.length; i++)
			{
				var parent_finder = jQuery('#'+this.div.id, parent.children[i].iface.doGetDOMNode());
				if(parent_finder.length > 0)
				{
					parent = parent.children[i];
					break;
				}
			}
		}

		// This binds into the egw action system.  Most user interactions (drag to move, resize)
		// are handled internally using jQuery directly.
		var widget_object = this._actionObject || parent.getObjectById(this.id);

		var aoi = new et2_action_object_impl(this,this.getDOMNode());

		/**
		 * Determine if we allow a dropped event to use the invite/change actions,
		 * and enable or disable them appropriately
		 * 
		 * @param {egwAction} action
		 * @param {et2_calendar_event} event The event widget being dragged
		 * @param {egwActionObject} target Planner action object
		 */
		var _invite_enabled = function(action, event, target)
		{
			var event = event.iface.getWidget();
			var planner = target.iface.getWidget() || false;
			//debugger;
			if(event === planner || !event || !planner ||
				!event.options || !event.options.value.participants || !planner.options.owner
			)
			{
				return false;
			}
			var owner_match = false;
			var own_row = false;

			for(var id in event.options.value.participants)
			{
				planner.iterateOver(function(row) {
					// Check scroll section or header section
					if(row.div.hasClass('drop-hover') || row.div.has(':hover'))
					{
						owner_match = owner_match || row.node.dataset[planner.options.group_by] === ''+id;
						own_row = (row === event.getParent());
					}
				}, this, et2_calendar_planner_row);

			}
			var enabled = !owner_match &&
				// Not inside its own row
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
			if(event.type === 'drop')
			{
				this.getWidget()._event_drop.call(jQuery('.calendar_d-n-d_timeCounter',_data.ui.helper)[0],this.getWidget(),event, _data.ui);
			}
			var drag_listener = function(event, ui) {
				aoi.getWidget()._drag_helper(jQuery('.calendar_d-n-d_timeCounter',ui.helper)[0],{
						top:ui.position.top,
						left: ui.position.left - jQuery(this).parent().offset().left
					},0);
			};
			var time = jQuery('.calendar_d-n-d_timeCounter',_data.ui.helper);
			switch(_event)
			{
				// Triggered once, when something is dragged into the timegrid's div
				case EGW_AI_DRAG_OVER:
					// Listen to the drag and update the helper with the time
					// This part lets us drag between different timegrids
					_data.ui.draggable.on('drag.et2_timegrid'+widget_object.id, drag_listener);
					_data.ui.draggable.on('dragend.et2_timegrid'+widget_object.id, function() {
						_data.ui.draggable.off('drag.et2_timegrid' + widget_object.id);
					});
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
					_data.ui.draggable.off('drag.et2_timegrid'+widget_object.id);
					// Remove any highlighted time squares
					jQuery('[data-date]',this.doGetDOMNode()).removeClass("ui-state-active");

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
				this._actionManager || parent.manager.getActionById(this.id) || parent.manager
			),EGW_AO_FLAG_IS_CONTAINER);
		}
		else
		{
			widget_object.setAOI(aoi);
		}
		// Go over the widget & add links - this is where we decide which actions are
		// 'allowed' for this widget at this time
		var action_links = this._get_action_links(actions);

		this._init_links_dnd(widget_object.manager, action_links);

		//widget_object.updateActionLinks(action_links);
		this._actionObject = widget_object;
	},

	/**
	 * Automatically add dnd support for linking
	 *
	 * @param {type} mgr
	 * @param {type} actionLinks
	 */
	_init_links_dnd: function(mgr,actionLinks) {

		if (this.options.readonly) return;
		
		var self = this;

		var drop_action = mgr.getActionById('egw_link_drop');
		var drop_change_participant = mgr.getActionById('change_participant');
		var drop_invite = mgr.getActionById('invite');
		var drag_action = mgr.getActionById('egw_link_drag');

		// Check if this app supports linking
		if(!egw.link_get_registry(this.dataStorePrefix || 'calendar', 'query') ||
			egw.link_get_registry(this.dataStorePrefix || 'calendar', 'title'))
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
				if(links.length && dropped && dropped.iface.getWidget() && dropped.iface.getWidget().instanceOf(et2_calendar_event))
				{
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
				}
			},true);

			drop_action.acceptedTypes = ['default','link'];
			drop_action.hideOnDisabled = true;

			// Create the drop action for moving events between planner rows
			var invite_action = function(action, source, target) {

				// Extract link IDs
				var links = [];
				var id = '';
				for(var i = 0; i < source.length; i++)
				{
					// Check for no ID (invalid) or same manager (dragging an event)
					if(!source[i].id) continue;
					if(source[i].manager === target.manager)
					{

						// Find the row, could have dropped on an event
						var row = target.iface.getWidget();
						while(target.parent && row.instanceOf && !row.instanceOf(et2_calendar_planner_row))
						{
							target = target.parent;
							row = target.iface.getWidget();
						}

						// Leave the helper there until the update is done
						var loading = action.ui.helper.clone(true).appendTo(jQuery('body'));

						// and add a loading icon so user knows something is happening
						if(jQuery('.calendar_timeDemo',loading).length == 0)
						{
							jQuery('.calendar_calEventHeader',loading).addClass('loading');
						}
						else
						{
							jQuery('.calendar_timeDemo',loading).after('<div class="loading"></div>');
						}

						var event_data = egw.dataGetUIDdata(source[i].id).data;
						et2_calendar_event.recur_prompt(event_data, function(button_id) {
							if(button_id === 'cancel' || !button_id)
							{
								return;
							}
							var add_owner = jQuery.extend([],row.node.dataset.participants);

							egw().json('calendar.calendar_uiforms.ajax_invite', [
									button_id==='series' ? event_data.id : event_data.app_id,
									add_owner,
									action.id === 'change_participant' ?
										jQuery.extend([],source[i].iface.getWidget().getParent().node.dataset.participants) :
										[]
								],
								function() { loading.remove();}
							).sendRequest(true);
						});
						// Ok, stop.
						return false;
					}
				}
			};

			drop_change_participant = mgr.addAction('drop', 'change_participant', egw.lang('Move to'), egw.image('participant'), invite_action,true);
			drop_change_participant.acceptedTypes = ['calendar'];
			drop_change_participant.hideOnDisabled = true;

			drop_invite = mgr.addAction('drop', 'invite', egw.lang('Invite'), egw.image('participant'), invite_action,true);
			drop_invite.acceptedTypes = ['calendar'];
			drop_invite.hideOnDisabled = true;
		}
		if(actionLinks.indexOf(drop_action.id) < 0)
		{
			actionLinks.push(drop_action.id);
		}
		actionLinks.push(drop_invite.id);
		actionLinks.push(drop_change_participant.id);
		
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
				// As we wanted to have a general defaul helper interface, we return null here and not using customize helper for links
				// TODO: Need to decide if we need to create a customized helper interface for links anyway
				//return helper;
				return null;
			},true);
		}
		// The planner itself is not draggable, the action is there for the children
		if(false && actionLinks.indexOf(drag_action.id) < 0)
		{
			actionLinks.push(drag_action.id);
		}
		drag_action.set_dragType(['link','calendar']);
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
	 * Show the current time while dragging
	 * Used for resizing as well as drag & drop
	 *
	 * @param {type} element
	 * @param {type} position
	 * @param {type} height
	 */
	_drag_helper: function(element, position ,height)
	{
		var time = this._get_time_from_position(position.left, position.top);
		element.dropEnd = time;
		var formatted_time = jQuery.datepicker.formatTime(
			egw.preference("timeformat") === "12" ? "h:mmtt" : "HH:mm",
			{
				hour: time.getUTCHours(),
				minute: time.getUTCMinutes(),
				seconds: 0,
				timezone: 0
			},
			{"ampm": (egw.preference("timeformat") === "12")}
		);

		element.innerHTML = '<div class="calendar_d-n-d_timeCounter"><span class="calendar_timeDemo" >'+formatted_time+'</span></div>';

		//jQuery(element).width(jQuery(helper).width());
	},

	/**
	 * Handler for dropping an event on the timegrid
	 *
	 * @param {type} planner
	 * @param {type} event
	 * @param {type} ui
	 */
	_event_drop: function(planner, event,ui) {
		var e = new jQuery.Event('change');
		e.originalEvent = event;
		e.data = {start: 0};
		if (typeof this.dropEnd != 'undefined')
		{
			var drop_date = this.dropEnd.toJSON() ||false;

			var event_data = planner._get_event_info(ui.draggable);
			var event_widget = planner.getWidgetById(event_data.widget_id);
			if(event_widget)
			{
				event_widget._parent.date_helper.set_value(drop_date);
				event_widget.options.value.start = new Date(event_widget._parent.date_helper.getValue());

				// Leave the helper there until the update is done
				var loading = ui.helper.clone().appendTo(ui.helper.parent());
				// and add a loading icon so user knows something is happening
				jQuery('.calendar_timeDemo',loading).after('<div class="loading"></div>');

				event_widget.recur_prompt(function(button_id) {
					if(button_id === 'cancel' || !button_id) return;
					//Get infologID if in case if it's an integrated infolog event
					if (event_data.app === 'infolog')
					{
						// If it is an integrated infolog event we need to edit infolog entry
						egw().json('stylite_infolog_calendar_integration::ajax_moveInfologEvent',
							[event_data.id, event_widget.options.value.start||false],
							function() {loading.remove();}
						).sendRequest(true);
					}
					else
					{
						//Edit calendar event
						egw().json('calendar.calendar_uiforms.ajax_moveEvent', [
								button_id==='series' ? event_data.id : event_data.app_id,event_data.owner,
								event_widget.options.value.start,
								planner.options.owner||egw.user('account_id')
							],
							function() { loading.remove();}
						).sendRequest(true);
					}
				});
			}
		}
	},

	/**
	 * Use the egw.data system to get data from the calendar list for the
	 * selected time span.
	 *
	 */
	_fetch_data: function()
	{
		var value = [];
		var fetch = false;
		this.doInvalidate = false;

		for(var i = 0; i < this.registeredCallbacks.length; i++)
		{
			egw.dataUnregisterUID(this.registeredCallbacks[i],false,this);
		}
		this.registeredCallbacks.splice(0,this.registeredCallbacks.length);

		// Remember previous day to avoid multi-days duplicating
		var last_data = [];

		var t = new Date(this.options.start_date);
		var end = new Date(this.options.end_date);
		do
		{
			// Cache is by date (and owner, if seperate)
			var date = t.getUTCFullYear() + sprintf('%02d',t.getUTCMonth()+1) + sprintf('%02d',t.getUTCDate());
			var cache_id = app.classes.calendar._daywise_cache_id(date, this.options.owner);

			if(egw.dataHasUID(cache_id))
			{
				var c = egw.dataGetUIDdata(cache_id);
				if(c.data && c.data !== null)
				{
					// There is data, pass it along now
					for(var j = 0; j < c.data.length; j++)
					{
						if(last_data.indexOf(c.data[j]) === -1 && egw.dataHasUID('calendar::'+c.data[j]))
						{
							value.push(egw.dataGetUIDdata('calendar::'+c.data[j]).data);
						}
					}
					last_data = c.data;
				}
			}
			else
			{
				fetch = true;
				// Assume it's empty, if there is data it will be filled later
				egw.dataStoreUID(cache_id, []);
			}
			this.registeredCallbacks.push(cache_id);
			egw.dataRegisterUID(cache_id, function(data) {
				if(data && data.length)
				{
					// If displaying by category, we need the infolog (or other app) categories too
					var im = this.getInstanceManager();
					for(var i = 0; i < data.length && this.options.group_by == 'category'; i++)
					{
						var event = egw.dataGetUIDdata('calendar::'+data[i]);
						if(event && event.data && event.data.app)
						{
							// Fake it to use the cache / call
							et2_selectbox.cat_options({
								_type:'select-cat',
								getInstanceManager: function() {return im;}
							}, {application:event.data.app||'calendar'});

							// Get CSS too
							egw.includeCSS('/api/categories.php?app='+event.data.app);
						}
					}

					this.invalidate(false);
				}
			}, this, this.getInstanceManager().execId,this.id);

			t.setUTCDate(t.getUTCDate() + 1);
		}
		while(t < end);
		// Need to get some more from the server
		if(fetch && app.calendar)
		{
			app.calendar._fetch_data({
				first: this.options.start_date,
				last: this.options.end_date,
				owner: this.options.owner,
				filter: this.options.filter
			}, this.getInstanceManager());
		}

		this.doInvalidate = true;
		return value;
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

		this._super.apply(this, arguments);

		// Planner uses an array, not map
		var val = this.value;
		var array = [];
		Object.keys(this.value).forEach(function (key) {
			array.push(val[key]);
		});
		this.value = array;
	},

	/**
	 * Change the start date
	 * Planner view uses a date object internally
	 *
	 * @param {string|number|Date} new_date New starting date
	 * @returns {undefined}
	 */
	set_start_date: function set_start_date(new_date)
	{
		this._super.apply(this, arguments);
		this.options.start_date = new Date(this.options.start_date);
	},

	/**
	 * Change the end date
	 * Planner view uses a date object internally
	 *
	 * @param {string|number|Date} new_date New end date
	 * @returns {undefined}
	 */
	set_end_date: function set_end_date(new_date)
	{
		this._super.apply(this, arguments);
		this.options.end_date = new Date(this.options.end_date);
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
	 * Turn on or off the visibility of weekends
	 *
	 * @param {boolean} weekends
	 */
	set_show_weekend: function(weekends)
	{
		weekends = weekends ? true : false;
		if(this.options.show_weekend !== weekends)
		{
			this.options.show_weekend = weekends;
			if(this.isAttached())
			{
				this.invalidate();
			}
		}
	},

	/**
	 * Call change handler, if set
	 *
	 * @param {type} event
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
	 *
	 * @param {type} event
	 * @param {type} dom_node
	 */
	event_change: function(event, dom_node) {
		if (this.onevent_change)
		{
			var event_data = this._get_event_info(dom_node);
			var event_widget = this.getWidgetById(event_data.widget_id);
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
	 * @returns {boolean} Continue processing event (true) or stop (false)
	 */
	click: function(_ev)
	{
		var result = true;

		// Drag to create in progress
		if(this.drag_create.start !== null) return;
		
		// Is this click in the event stuff, or in the header?
		if(!this.options.readonly && this.gridHeader.has(_ev.target).length === 0 && !jQuery(_ev.target).hasClass('calendar_plannerRowHeader'))
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
			else if (!event.id)
			{
				// Clicked in row, but not on an event
				// Default handler to open a new event at the selected time
				if(this.options.group_by == 'month')
				{
					var date = this._get_time_from_position(_ev.clientX, _ev.clientY);
				}
				else
				{
					var date = this._get_time_from_position(_ev.offsetX, _ev.offsetY);
				}
				var row = jQuery(_ev.target).closest('.calendar_plannerRowWidget');
				var data = row.length ? row[0].dataset : {};
				this.egw().open(null, 'calendar', 'add', jQuery.extend({
					start: date.toJSON(),
					hour: date.getUTCHours(),
					minute: date.getUTCMinutes()
				},data) , '_blank');
				return false;
			}
			return result;
		}
		else if (this.gridHeader.has(_ev.target).length > 0 && !jQuery.isEmptyObject(_ev.target.dataset) ||
			jQuery(_ev.target).hasClass('calendar_plannerRowHeader') && !jQuery.isEmptyObject(_ev.target.dataset))
		{
			// Click on a header, we can go there
			_ev.data = jQuery.extend({},_ev.target.parentNode.dataset, _ev.target.dataset);
			for(var key in _ev.data)
			{
				if(!_ev.data[key])
				{
					delete _ev.data[key];
				}
			}
			app.calendar.update_state(_ev.data);
		}
		else if (!this.options.readonly)
		{
			// Default handler to open a new event at the selected time
			// TODO: Determine date / time more accurately from position
			this.egw().open(null, 'calendar', 'add', {
				date: _ev.target.dataset.date || this.options.start_date.toJSON(),
				hour: _ev.target.dataset.hour || this.options.day_start,
				minute: _ev.target.dataset.minute || 0
			} , '_blank');
			return false;
		}
	},

	/**
	 * Get time from position
	 *
	 * @param {number} x
	 * @param {number} y
	 * @returns {Date|Boolean} A time for the given position, or false if one
	 *	could not be determined.
	 */
	_get_time_from_position: function(x,y) {

		x = Math.round(x);
		y = Math.round(y);

		// Relative horizontal position, as a percentage
		var rel_x = Math.min(x / jQuery('.calendar_eventRows',this.div).width(),1);

		// Relative time, in minutes from start
		var rel_time = 0;

		// Simple math, the x is offset from start date
		if(this.options.group_by !== 'month')
		{
			rel_time = (new Date(this.options.end_date) - new Date(this.options.start_date))*rel_x/1000;
			this.date_helper.set_value(this.options.start_date.toJSON());
		}
		else
		{
			// Find the correct row so we know which month, then get the offset
			var hidden_nodes = [];
			var row = null;
			// Hide any drag or tooltips that may interfere
			do
			{
				row = document.elementFromPoint(x, y);
				if(this.div.has(row).length == 0)
				{
					hidden_nodes.push(jQuery(row).hide());
				}
				else
				{
					break;
				}
			} while(row.nodeName !== 'BODY');
			// Restore hidden nodes
			for(var i = 0; i < hidden_nodes.length; i++)
			{
				hidden_nodes[i].show();
			}
			row = jQuery(row).closest('.calendar_plannerRowWidget');


			var row_widget = null;
			for(var i = 0; i < this._children.length && row.length > 0; i++)
			{
				if(this._children[i].div[0] == row[0])
				{
					row_widget = this._children[i];
					break;
				}
			}
			if(row_widget)
			{
				// Not sure where the extra -1 and +2 are coming from, but it makes it work out
				// in FF & Chrome
				rel_x = Math.min((x-row_widget.rows.offset().left-1)/(row_widget.rows.width()+2),1);

				// 2678400 is the number of seconds in 31 days
				rel_time = (2678400)*rel_x;
				this.date_helper.set_value(row_widget.options.start_date.toJSON());
			}
			else
			{
				return false;
			}
		}
		if(rel_time < 0) return false;

		var interval = egw.preference('interval','calendar') || 30;
		this.date_helper.set_minutes(Math.round(rel_time / (60 * interval))*interval);

		return new Date(this.date_helper.getValue());
	},

	/**
	 * Mousedown handler to support drag to create
	 *
	 * @param {jQuery.Event} event
	 */
	_mouse_down: function(event)
	{
		// Get time at mouse
		if(this.options.group_by === 'month')
		{
			var time = this._get_time_from_position(event.clientX, event.clientY);
		}
		else
		{
			var time = this._get_time_from_position(event.offsetX, event.offsetY);
		}
		if(!time) return false;

		this.div.css('cursor', 'ew-resize');

		// Find the correct row so we know the parent
		var row = event.target.closest('.calendar_plannerRowWidget');
		for(var i = 0; i < this._children.length && row; i++)
		{
			if(this._children[i].div[0] === row)
			{
				this.drag_create.parent = this._children[i];
				// Clear cached events for re-layout
				this._children[i]._cached_rows = [];
				break;
			}
		}
		return this._drag_create_start(jQuery.extend({},this.drag_create.parent.node.dataset,{date: time.toJSON()}));
	},

	/**
	 * Mouseup handler to support drag to create
	 *
	 * @param {jQuery.Event} event
	 */
	_mouse_up: function(event)
	{
		// Get time at mouse
		if(this.options.group_by === 'month')
		{
			var time = this._get_time_from_position(event.clientX, event.clientY);
		}
		else
		{
			var time = this._get_time_from_position(event.offsetX, event.offsetY);
		}

		return this._drag_create_end({date: time.toJSON()});
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
		this.div = jQuery(_nodes[0]);

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
	resize: function ()
	{
		// Take the whole tab height
		var height = Math.min(jQuery(this.getInstanceManager().DOMContainer).height(),jQuery(this.getInstanceManager().DOMContainer).parent().innerHeight());

		// Allow for toolbar
		height -= jQuery('#calendar-toolbar',this.div.parents('.egw_fw_ui_tab_content')).outerHeight(true);

		this.options.height = height;
		this.div.css('height', this.options.height);
		// Set height for rows
		this.rows.height(this.div.height() - this.headers.outerHeight());
	}
});}).call(this);
et2_register_widget(et2_calendar_planner, ["calendar-planner"]);