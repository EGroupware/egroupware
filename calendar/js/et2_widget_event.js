/*
 * Egroupware Calendar event widget
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
*/

/**
 * Class for a single event, displayed in a timegrid
 *
 *
 * @augments et2_valueWidget
 */
var et2_calendar_event = et2_valueWidget.extend([et2_IDetachedDOM],
{

	attributes: {
		"value": {
			type: "any",
			default: et2_no_init
		},
		"onclick": {
			"description": "JS code which is executed when the element is clicked. " +
				"If no handler is provided, or the handler returns true and the event is not read-only, the " +
				"event will be opened according to calendar settings."
		}
	},

	/**
	 * Constructor
	 *
	 * @memberOf et2_calendar_daycol
	 */
	init: function() {
		this._super.apply(this, arguments);

		var event = this;
		
		// Main container
		this.div = $j(document.createElement("div"))
			.addClass("calendar_calEvent")
			.addClass(this.options.class)
			.css('width',this.options.width)
			.on('mouseenter', function() {
				// Hacky to remove egw's tooltip border and let the mouse in
				window.setTimeout(function() {
					$j('body .egw_tooltip')
						.css('border','none')
						.on('mouseenter', function() {
							event.div.off('mouseleave.tooltip');
							$j('body.egw_tooltip').remove();
							$j('body').append(this);
							$j(this).stop(true).fadeTo(400, 1)
								.on('mouseleave', function() {
									$j(this).fadeOut('400', function() {
										$j(this).remove();
										// Set up to work again
										event.set_statustext(event._tooltip());
									});
								});
						});

				},105);
			});
		this.title = $j(document.createElement('div'))
			.addClass("calendar_calEventHeader")
			.appendTo(this.div);
		this.body = $j(document.createElement('div'))
			.addClass("calendar_calEventBody")
			.appendTo(this.div);
		this.icons = $j(document.createElement('div'))
			.addClass("calendar_calEventIcons")
			.appendTo(this.title);

		this.setDOMNode(this.div[0]);
	},

	doLoadingFinished: function() {
		this._super.apply(this, arguments);

		// Parent will have everything we need, just load it from there
		if(this.title.text() == '' && this.options.date &&
			this._parent && this._parent.instanceOf(et2_calendar_timegrid))
		{
			// Forces an update
			var date = this.options.date;
			this.options.date = '';
			this.set_date(date);
		}
		if(this.options.value && this.options.value.row_id)
		{
			egw.dataRegisterUID(
				'calendar::'+this.options.value.row_id,
				this._UID_callback ,
				this,
				this.getInstanceManager().execId,
				this.id
			);
		}
	},

	destroy: function() {
		this._super.apply(this, arguments);

		if(this._actionObject)
		{
			this._actionObject.remove();
			this._actionObject = null;
		}
		
		this.div.off();
		this.title.remove();
		this.title = null;
		this.body.remove();
		this.body = null;
		this.icons = null;
		this.div.remove();
		this.div = null;

		$j('body.egw_tooltip').remove();
		
		// Unregister, or we'll continue to be notified...
		if(this.options.value)
		{
			var old_app_id = this.options.value.app_id;
			egw.dataUnregisterUID('calendar::'+old_app_id,false,this);
		}
	},

	set_value: function(_value) {
		// Un-register for updates
		if(this.options.value)
		{
			var old_id = this.options.value.row_id;
			if(!_value || !_value.row_id || old_id !== _value.row_id)
			{
				egw.dataUnregisterUID('calendar::'+old_id,false,this);
			}
		}
		this.options.value = _value;

		// Register for updates
		var id = this.options.value.row_id;
		if(!old_id || old_id !== id)
		{
			egw.dataRegisterUID('calendar::'+id, this._UID_callback ,this,this.getInstanceManager().execId,this.id);
		}
		if(_value && !egw.dataHasUID('calendar::'+id))
		{
			egw.dataStoreUID('calendar::'+id, _value);
		}
	},
	
	_UID_callback: function _UID_callback(event) {
		// Make sure id is a string
		this._values_check(event);

		// Check for changing days in the grid view
		if(!this._sameday_check(event))
		{
			// This should now cease to exist, as new events have been created
			this.free();
			return;
		}

		// Copy to avoid changes, which may cause nm problems
		this.options.value = jQuery.extend({},event);

		if(this._parent.options.date)
		{
			this.options.value.date = this._parent.options.date;
		}

		// Let parent position
		this._parent.position_event(this);

		// Parent may remove this if the date isn't the same
		if(this._parent)
		{
			// This gives some slight speed enhancements over doing it immediately,
			// but it looks weird
			/*
			window.setTimeout(jQuery.proxy(function() {
				if(this.options) this._update(this.options.value);
			},this),100);
			*/
			this._update(this.options.value);
		}
	},

	_update: function(event) {

		// Copy new information
		this.options.value = event;

		var id = event.row_id ? event.row_id : event.id + (event.recur_type ? ':'+event.recur_date : '');
		this._parent.date_helper.set_value(event.start.valueOf ? new Date(event.start) : event.start);
		var formatted_start = this._parent.date_helper.getValue();

		this.set_id('event_' + id);
		if(this._actionObject)
		{
			this._actionObject.id = 'calendar::' + id;
		}

		// Copy actions set in parent
		this._link_actions(this._parent._parent._parent.options.actions||{});

		// Make sure category stuff is there
		// Fake it to use the cache / call - if already there, these will return
		// immediately.
		var im = this.getInstanceManager();
		et2_selectbox.cat_options({
			_type:'select-cat',
			getInstanceManager: function() {return im}
		}, {application:event.app||'calendar'});

		// Get CSS too
		egw.includeCSS('/phpgwapi/categories.php?app='+event.app);

		// DOM nodes
		this.div
			// Empty & re-append to make sure dnd helpers are gone
			.empty()
			.append(this.title)
			.append(this.body)

			// Let timegrid always get the drag
			.droppable('option','greedy',false)
		
			// Set full day flag
			.attr('data-full_day', event.whole_day_on_top)
		
			// Put everything we need for basic interaction here, so it's available immediately
			.attr('data-id', event.id)
			.attr('data-app', event.app || 'calendar')
			.attr('data-app_id', event.app_id)
			.attr('data-start', formatted_start)
			.attr('data-owner', event.owner)
			.attr('data-recur_type', event.recur_type)
			.attr('data-resize', event.whole_day ? 'WD' : '' + (event.recur_type ? 'S':''))
			// Remove any category classes
			.removeClass(function(index, css) {
				return (css.match (/(^|\s)cat_\S+/g) || []).join(' ');
			})
			// Remove any status classes
			.removeClass(function(index, css) {
				return (css.match(/calendar_calEvent\S+/g) || []).join(' ');
			})
			// Remove any resize classes, the handles are gone due to empty()
			.removeClass('ui-resizable')
			.addClass(event.class)
			.toggleClass('calendar_calEventPrivate', typeof event.private !== 'undefined' && event.private);
		this.options.class = event.class;
		var status_class = this._status_class();

		// Add category classes, if real categories are set
		if(event.category && event.category != '0')
		{
			var cats = event.category.split(',');
			for(var i = 0; i < cats.length; i++)
			{
				this.div.addClass('cat_' + cats[i]);
			}
		}

		this.div.toggleClass('calendar_calEventUnknown', event.participants[egw.user('account_id')] ? event.participants[egw.user('account_id')][0] === 'U' : false);
		this.div.addClass(status_class);

		this.title.toggle(!event.whole_day_on_top);
		this.body.toggleClass('calendar_calEventBodySmall', event.whole_day_on_top || false);
		
		// Header
		var title = !event.is_private ? event['title'] : egw.lang('private');
		// If there isn't enough height for header + 1 line in the body, it's small
		var small_height = this.div.innerHeight() <= this.title.height() * 2;

		this.div.attr('data-title', title);
		this.title.text(small_height ? title : this._get_timespan(event));
		
		// Colors - don't make them transparent if there is no color
		if(jQuery.Color("rgba(0,0,0,0)").toRgbaString() != jQuery.Color(this.div,'background-color').toRgbaString())
		{
			// Most statuses use colored borders
			this.div.css('border-color',status_class === 'calendar_calEventAllAccepted' ? this.div.css('background-color') : '');

			// Set title color based on background brightness
			this.title
				.css('background-color', this.div.css('background-color'))
				.css('color', jQuery.Color(this.div.css('background-color')).lightness() > 0.45 ? 'black':'white');
		}

		this.icons.appendTo(this.title)
			.html(this._icons());
		
		// Body
		if(event.whole_day_on_top)
		{
			this.body.html(title);
		}
		else
		{
			this.body
				.html('<span class="calendar_calEventTitle">'+title+'</span>')
				.append('<span class="calendar_calTimespan">'+this._get_timespan(event) + '</span>')
				.append('<p>'+this.options.value.description+'</p>');
		}
		this.body
			// Set background color to a lighter version of the header color
			.css('background-color',jQuery.Color(this.title.css('background-color')).lightness(
				Math.max(0.8, parseFloat(jQuery.Color(this.title.css('background-color')).lightness()))
			));

		this.set_statustext(this._tooltip());
	},

	/**
	 * Examines the participants & returns CSS classname for status
	 * 
	 * @returns {String}
	 */
	_status_class: function() {
		var status_class = 'calendar_calEventAllAccepted';
		for(var id in this.options.value.participants)
		{
			var status = this.options.value.participants[id];

			status = et2_calendar_event.split_status(status);

			switch (status)
			{
				case 'A':
				case '':	// app without status
					break;
				case 'U':
					status_class = 'calendar_calEventSomeUnknown';
					return status_class;	// break for
				default:
					status_class = 'calendar_calEventAllAnswered';
					break;
			}
		}
		return status_class;
	},

	_tooltip: function() {
		if(!this.div) return '';
		
		var border = this.div.css('borderTopColor');
		var bg_color = this.div.css('background-color');
		var header_color = this.title.css('color');

		this._parent.date_helper.set_value(this.options.value.start.valueOf ? new Date(this.options.value.start) : this.options.value.start);
		var start = this._parent.date_helper.input_date.val();
		this._parent.date_helper.set_value(this.options.value.end.valueOf ? new Date(this.options.value.end) : this.options.value.end);
		var end = this._parent.date_helper.input_date.val();

		var times = !this.options.value.multiday ?
			'<span class="calendar_calEventLabel">'+this.egw().lang('Time')+'</span>:' + this._get_timespan(this.options.value) :
			'<span class="calendar_calEventLabel">'+this.egw().lang('Start') + '</span>:' +start+
			'<span class="calendar_calEventLabel">'+this.egw().lang('End') + '</span>:' + end
		var cat = et2_createWidget('select-cat',{'readonly':true},this);
		cat.set_value(this.options.value.category);
		var cat_label = this.options.value.category.indexOf(',') <= 0 ? cat.span.text() : [];
		if(typeof cat_label != 'string')
		{
			cat.span.children().each(function() {
				cat_label.push($j(this).text());
			});
			cat_label = cat_label.join(', ');
		}
		cat.destroy();
		
		return '<div class="calendar_calEventTooltip ' + this._status_class() + '" style="border-color: '+border+'; background: '+bg_color+';">'+
			'<div class="calendar_calEventHeaderSmall" style="background-color: '+this.title.css('background-color')+';">'+
				'<font style="color:'+header_color+'">'+this._get_timespan(this.options.value)+'</font>'+
				this.icons[0].outerHTML+
			'</div>'+
			'<div class="calendar_calEventBodySmall" style="background-color: '+
				jQuery.Color(this.title.css('background-color')).lightness("0.9") + '">'+
				'<p style="margin: 0px;">'+
				'<span class="calendar_calEventTitle">'+this.div.attr('data-title')+'</span><br>'+
				this.options.value.description+'</p>'+
				'<p style="margin: 2px 0px;">'+times+'</p>'+
				(this.options.value.location ? '<p><span class="calendar_calEventLabel">'+this.egw().lang('Location') + '</span>:' + this.options.value.location+'</p>' : '')+
				(cat_label ? '<p><span class="calendar_calEventLabel">'+this.egw().lang('Category') + '</span>:' + cat_label +'</p>' : '')+
				'<p><span class="calendar_calEventLabel">'+this.egw().lang('Participants')+'</span>:<br />'+
					(this.options.value.parts ? this.options.value.parts.replace("\n","<br />"):'')+'</p>'+
			'</div>'+
		'</div>';
	},

	/**
	 * Get actual icons from list
	 * @returns {undefined}
	 */
	_icons: function() {
		var icons = [];

		if(this.options.value.is_private)
		{
			// Hide everything
			icons.push('<img src="'+this.egw().image('private','calendar')+'"/>');
		}
		else
		{
			if(this.options.value.app !== 'calendar')
			{
				icons.push('<img src="'+this.egw().image('navbar',this.options.value.app)+'" title="'+this.egw().lang(this.options.value.app)+'"/>');
			}
			if(this.options.value.priority == 3)
			{
				icons.push('<img src="'+this.egw().image('high','calendar')+'" title="'+this.egw().lang('high priority')+'"/>');
			}
			if(this.options.value.public == '0')
			{
				// Show private flag
				icons.push('<img src="'+this.egw().image('private','calendar')+'"/>');
			}
			if(this.options.value['recur_type'])
			{
				icons.push('<img src="'+this.egw().image('recur','calendar')+'" title="'+this.egw().lang('recurring event')+'"/>');
			}
			// icons for single user, multiple users or group(s) and resources
			var single = '<img src="'+this.egw().image('single','calendar')+'" title="'+'"/>';
			var multiple = '<img src="'+this.egw().image('users','calendar')+'" title="'+'"/>';
			for(var uid in this.options.value['participants'])
			{
				if(Object.keys(this.options.value.participants).length == 1 && !isNaN(uid))
				{
					icons.push(single);
					break;
				}
				if(!isNaN(uid) && icons.indexOf(multiple) === -1)
				{
					icons.push(multiple);
				}
				/*
				 * TODO: resource icons
				elseif(!isset($icons[$uid[0]]) && isset($this->bo->resources[$uid[0]]) && isset($this->bo->resources[$uid[0]]['icon']))
				{
				 	$icons[$uid[0]] = html::image($this->bo->resources[$uid[0]]['app'],
				 		($this->bo->resources[$uid[0]]['icon'] ? $this->bo->resources[$uid[0]]['icon'] : 'navbar'),
				 		lang($this->bo->resources[$uid[0]]['app']),
				 		'width="16px" height="16px"');
				}
				*/
			}

			if(this.options.value.non_blocking)
			{
				icons.push('<img src="'+this.egw().image('nonblocking','calendar')+'" title="'+this.egw().lang('non blocking')+'"/>');
			}
			if(this.options.value.alarm && !jQuery.isEmptyObject(this.options.value.alarm) && !this.options.value.is_private)
			{
				icons.push('<img src="'+this.egw().image('alarm','calendar')+'" title="'+this.egw().lang('alarm')+'"/>');
			}
			if(this.options.value.participants[egw.user('account_id')] && this.options.value.participants[egw.user('account_id')][0] == 'U')
			{
				icons.push('<img src="'+this.egw().image('needs-action','calendar')+'" title="'+this.egw().lang('Needs action')+'"/>');
			}
		}
		return icons;
	},

	/**
	 * Get a text representation of the timespan of the event.  Either start
	 * - end, or 'all day'
	 *
	 * @param {Object} event Event to get the timespan for
	 * @param {number} event.start_m Event start, in minutes from midnight
	 * @param {number} event.end_m Event end, in minutes from midnight
	 *
	 * @return {string} Timespan
	 */
	_get_timespan: function(event) {
		var timespan = '';
		if (event['start_m'] === 0 && event['end_m'] >= 24*60-1)
		{
			if (event['end_m'] > 24*60)
			{
				timespan = jQuery.datepicker.formatTime(
					egw.preference("timeformat") === "12" ? "h:mmtt" : "HH:mm",
					{
						hour: event.start_m / 60,
						minute: event.start_m % 60,
						seconds: 0,
						timezone: 0
					},
					{"ampm": (egw.preference("timeformat") === "12")}
				).trim()+' - '+jQuery.datepicker.formatTime(
					egw.preference("timeformat") === "12" ? "h:mmtt" : "HH:mm",
					{
						hour: event.end_m / 60,
						minute: event.end_m % 60,
						seconds: 0,
						timezone: 0
					},
					{"ampm": (egw.preference("timeformat") === "12")}
				).trim();
			}
			else
			{
				timespan = egw.lang('Whole day');
			}
		}
		else
		{
			var duration = event.multiday ? 
				(event.end - event.start) / 60000 :
				(event.end_m - event.start_m);
			if (event.end_m === 24*60-1) ++duration;
			duration = Math.floor(duration/60) + this.egw().lang('h')+(duration%60 ? duration%60 : '');

			timespan = jQuery.datepicker.formatTime(
				egw.preference("timeformat") === "12" ? "h:mmtt" : "HH:mm",
				{
					hour: event.start_m / 60,
					minute: event.start_m % 60,
					seconds: 0,
					timezone: 0
				},
				{"ampm": (egw.preference("timeformat") === "12")}
			).trim();

			timespan += ' ' + duration;
		}
		return timespan;
	},
	
	/**
	 * Make sure event data has all proper values, and format them as expected
	 * @param {Object} event
	 */
	_values_check: function _values_check(event)
	{
		// Make sure ID is a string
		if(event.id)
		{
			event.id = ''+event.id;
		}

		// Use dates as objects
		if(typeof event.start !== 'object')
		{
			this._parent.date_helper.set_value(event.start);
			event.start = new Date(this._parent.date_helper.getValue());
		}
		if(typeof event.end !== 'object')
		{
			this._parent.date_helper.set_value(event.end);
			event.end = new Date(this._parent.date_helper.getValue());
		}
		
		// We need minutes for durations
		if(typeof event.start_m === 'undefined')
		{
			event.start_m = event.start.getUTCHours() * 60 + event.start.getUTCMinutes();
			event.end_m = event.end.getUTCHours() * 60 + event.end.getUTCMinutes();
		}
		if(typeof event.multiday === 'undefined')
		{
			event.multiday = (event.start.getUTCFullYear() !== event.end.getUTCFullYear() ||
				event.start.getUTCMonth() !== event.end.getUTCMonth() ||
				event.start.getUTCDate() != event.end.getUTCDate());
		}
		if(!event.start.getUTCHours() && !event.start.getUTCMinutes() && event.end.getUTCHours() == 23 && event.end.getUTCMinutes() == 59)
		{
			event.whole_day_on_top = (event.non_blocking && event.non_blocking != '0');
		}
	},
	_sameday_check: function(event)
	{
		// Event somehow got orphaned, or deleted
		if(!this._parent || event === null)
		{
			return false;
		}

		// Simple, same day
		if(this.options.value.date && event.date == this.options.value.date)
		{
			return true;
		}

		// Multi-day non-recurring event spans days - date does not match
		var event_start = new Date(event.start);
		var event_end = new Date(event.end);
		if(this._parent.date >= event_start && this._parent.date <= event_end)
		{
			return true;
		}

		// Delete all old actions
		this._actionObject.clear();
		this._actionObject.unregisterActions();
		this._actionObject = null;

		// Update daywise caches
		var new_cache_id = app.classes.calendar._daywise_cache_id(event.date,this._parent.options.owner);
		var new_daywise = egw.dataGetUIDdata(new_cache_id);
		new_daywise = new_daywise ? new_daywise.data : [];
		var old_cache_id = false;
		if(this.options.value && this.options.value.date)
		{
			old_cache_id = app.classes.calendar._daywise_cache_id(this.options.value.date,this._parent.options.owner);
			var old_daywise = egw.dataGetUIDdata(old_cache_id);
			old_daywise = old_daywise ? old_daywise.data : [];
			old_daywise.splice(old_daywise.indexOf(this.options.value.id),1);
			egw.dataStoreUID(old_cache_id,old_daywise);
		}
		if (new_daywise.indexOf(event.id) < 0)
		{
			new_daywise.push(event.id);
		}
		egw.dataStoreUID(new_cache_id,new_daywise);

		return false;
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
	},

	/**
	 * Click handler calling custom handler set via onclick attribute to this.onclick.
	 * All other handling is done by the timegrid widget.
	 *
	 * @param {Event} _ev
	 * @returns {boolean}
	 */
	click: function(_ev) {
		var result = true;
		if(typeof this.onclick == 'function')
		{
			// Make sure function gets a reference to the widget, splice it in as 2. argument if not
			var args = Array.prototype.slice.call(arguments);
			if(args.indexOf(this) == -1) args.splice(1, 0, this);

			result = this.onclick.apply(this, args);
		}
		return result;
	},

	/**
	 * Show the recur prompt for this event
	 *
	 * @param {function} callback
	 * @param {Object} [extra_data]
	 */
	recur_prompt: function(callback, extra_data)
	{
		et2_calendar_event.recur_prompt(this.options.value,callback,extra_data);
	},

	/**
	 * Show the series split prompt for this event
	 *
	 * @param {function} callback
	 */
	series_split_prompt: function(callback)
	{
		et2_calendar_event.series_split_prompt(this.options.value,this.options.value.recur_date, callback);
	},

	/**
	 * Link the actions to the DOM nodes / widget bits.
	 *
	 * @param {object} actions {ID: {attributes..}+} map of egw action information
	 */
	_link_actions: function(actions)
	{
		if(!this._actionObject)
		{
			// Get the top level element - timegrid or so
			var objectManager = this.getParent().getParent()._actionObject ||
			   egw_getAppObjectManager(true).getObjectById(this._parent._parent._parent.id) || egw_getAppObjectManager(true);
			this._actionObject = objectManager.getObjectById('calendar::'+this.id);
		}

		if (this._actionObject == null) {
			// Add a new container to the object manager which will hold the widget
			// objects
			this._actionObject = objectManager.insertObject(false, new egwActionObject(
				'calendar::'+this.id, objectManager, new et2_event_action_object_impl(this,this.getDOMNode()),
				this._actionManager || objectManager.manager.getActionById(this.id) || objectManager.manager
			));
		}
		else
		{
			this._actionObject.setAOI(new et2_event_action_object_impl(this, this.getDOMNode()));
		}

		// Delete all old objects
		this._actionObject.clear();
		this._actionObject.unregisterActions();

		// Go over the widget & add links - this is where we decide which actions are
		// 'allowed' for this widget at this time
		var action_links = this._get_action_links(actions);
		action_links.push('egw_link_drag');
		action_links.push('egw_link_drop');
		this._actionObject.updateActionLinks(action_links);
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
et2_register_widget(et2_calendar_event, ["calendar-event"]);

// Static class stuff
/**
 * Recur prompt
 * If the event is recurring, asks the user if they want to edit the event as 
 * an exception, or change the whole series.  Then the callback is called.
 *
 * @param {Object} event_data - Event information
 * @param {string} event_data.id - Unique ID for the event, possibly with a timestamp
 * @param {string|Date} event_data.start - Start date/time for the event
 * @param {number} event_data.recur_type - Recur type, or 0 for a non-recurring event
 * @param {Function} [callback] - Callback is called with the button (exception, series, single or cancel) and the event data.
 * @param {Object} [extra_data] - Additional data passed to the callback, used as extra parameters for default callback
 * 
 * @augments {et2_calendar_event}
 */
et2_calendar_event.recur_prompt = function(event_data, callback, extra_data)
{
	var edit_id = event_data.app_id;
	var edit_date = event_data.start;
	var egw = this.egw ? (typeof this.egw == 'function' ? this.egw() : this.egw) : (window.opener || window).egw;
	var that = this;

	var extra_params = extra_data && typeof extra_data == 'object' ? extra_data : {};
	extra_params.date = edit_date.toJSON ? edit_date.toJSON() : edit_date;
	if(typeof callback != 'function')
	{
		callback = function(_button_id)
		{
			switch(_button_id)
			{
				case 'exception':
					extra_params.exception = '1';
					egw.open(edit_id, event_data.app||'calendar', 'edit', extra_params);
					break;
				case 'series':
				case 'single':
					egw.open(edit_id, event_data.app||'calendar', 'edit', extra_params);
					break;
				case 'cancel':
				default:
					break;
			}
		};
	}
	if(parseInt(event_data.recur_type))
	{
		var buttons = [
			{text: egw.lang("Edit exception"), id: "exception", class: "ui-priority-primary", "default": true},
			{text: egw.lang("Edit series"), id:"series"},
			{text: egw.lang("Cancel"), id:"cancel"}
		];
		et2_dialog.show_dialog(
			function(button_id) {callback.call(that, button_id, event_data);},
			(!event_data.is_private ? event_data['title'] : egw.lang('private')) + "\n" +
			egw.lang("Do you want to edit this event as an exception or the whole series?"),
			egw.lang("This event is part of a series"), {}, buttons, et2_dialog.QUESTION_MESSAGE
		);
	}
	else
	{
		callback.call(this,'single',event_data);
	}
};

/**
 * Split series prompt
 *
 * If the event is recurring and the user adjusts the time or duration, we may need
 * to split the series, ending the current one and creating a new one with the changes.
 * This prompts the user if they really want to do that.
 *
 * @param {Object} event_data - Event information
 * @param {string} event_data.id - Unique ID for the event, possibly with a timestamp
 * @param {string|Date} instance_date - The date of the edited instance of the event
 * @param {Function} [callback] - Callback is called with the button (ok or cancel) and the event data.
 *
 * @augments {et2_calendar_event}
 */
et2_calendar_event.series_split_prompt = function(event_data, instance_date, callback)
{
	var egw = this.egw ? (typeof this.egw == 'function' ? this.egw() : this.egw) : (window.opener || window).egw;
	var that = this;

	if(typeof instance_date == 'string')
	{
		instance_date = new Date(instance_date);
	}

	// Check for modifying a series that started before today
	var tempDate = new Date();
	var today = new Date(tempDate.getFullYear(), tempDate.getMonth(), tempDate.getDate(),tempDate.getHours(),-tempDate.getTimezoneOffset(),tempDate.getSeconds());
	var termination_date = instance_date < today ? egw.lang('today') : date(egw.preference('dateformat'),instance_date);

	if(parseInt(event_data.recur_type))
	{
		et2_dialog.show_dialog(
			function(button_id) {callback.call(that, button_id, event_data);},
			(!event_data.is_private ? event_data['title'] : egw.lang('private')) + "\n" +
			egw.lang("Do you really want to change the start of this series? If you do, the original series will be terminated as of %1 and a new series for the future reflecting your changes will be created.", termination_date),
			egw.lang("This event is part of a series"), {}, et2_dialog.BUTTONS_OK_CANCEL , et2_dialog.WARNING_MESSAGE
		);
	}
};

et2_calendar_event.drag_helper = function(event,ui) {
	ui.helper.width(ui.width());
};
/**
* splits the combined status, quantity and role
*
* @param {string} status - combined value, O: status letter: U, T, A, R
* @param {int} [quantity] - quantity
* @param {string} [role] 
* @return string status U, T, A or R, same as $status parameter on return
*/
et2_calendar_event.split_status = function(status,quantity,role)
{
	quantity = 1;
	role = 'REQ-PARTICIPANT';
	//error_log(__METHOD__.__LINE__.array2string($status));
	var matches = null;
	if (typeof status === 'string' && status.length > 1)
	{
		matches = status.match(/^.([0-9]*)(.*)$/gi);
	}
	if(matches)
	{
		if (parseInt(matches[1]) > 0) quantity = parseInt(matches[1]);
		if (matches[2]) role = matches[2];
		status = status[0];
	}
	else if (status === true)
	{
		status = 'U';
	}
	return status;
}

/**
 * The egw_action system requires an egwActionObjectInterface Interface implementation
 * to tie actions to DOM nodes.  This one can be used by any widget.
 *
 * The class extension is different than the widgets
 *
 * @param {et2_DOMWidget} widget
 * @param {Object} node
 *
 */
function et2_event_action_object_impl(widget, node)
{
	var aoi = new et2_action_object_impl(widget, node);

// _outerCall may be used to determine, whether the state change has been
// evoked from the outside and the stateChangeCallback has to be called
// or not.
	aoi.doSetState = function(_state, _outerCall) {
	};

	return aoi;
};
