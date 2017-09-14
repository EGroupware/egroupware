/*
 * Egroupware Calendar event widget
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */


/*egw:uses
	/etemplate/js/et2_core_valueWidget;
*/

/**
 * Class for a single event, displayed in either the timegrid or planner view
 *
 * It is possible to directly provide all information directly, but calendar
 * uses egw.data for caching, so ID is all that is needed.
 *
 * Note that there are several pieces of information that have 'ID' in them:
 * - row_id - used by both et2_calendar_event and the nextmatch to uniquely
 *	identify a particular entry or entry ocurrence
 * - id - Recurring events may have their recurrence as a timestamp after their ID,
 *	such as '194:1453318200', or not.  It's usually (always?) the same as row ID.
 * - app_id - the ID according to the source application.  For calendar, this
 *	is the same as ID (but always with the recurrence), for other apps this is
 *	usually just an integer.  With app_id and app, you should be able to call
 *	egw.open() and get the specific entry.
 * - Events from other apps will have their app name prepended to their ID, such
 *	as 'infolog123', so app_id and id will be different for these events
 * - Cache ID is the same as other apps, and looks like 'calendar::<row_id>'
 * - The DOM ID for the containing div is event_<row_id>
 *
 * Events are expected to be added to either et2_calendar_daycol or
 * et2_calendar_planner_row rather than either et2_calendar_timegrid or
 * et2_calendar_planner directly.
 *
 *
 * @augments et2_valueWidget
 */
var et2_calendar_event = (function(){ "use strict"; return et2_valueWidget.extend([et2_IDetachedDOM],
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
		this.div = jQuery(document.createElement("div"))
			.addClass("calendar_calEvent")
			.addClass(this.options.class)
			.css('width',this.options.width)
			.on('mouseenter', function() {
				// Bind actions on first mouseover for faster creation
				if(event._need_actions_linked)
				{
					event._copy_parent_actions();
				}
				// Tooltip
				if(!event._tooltipElem)
				{
					event.options.statustext_html = true;
					event.set_statustext(event._tooltip());
					return event.div.trigger('mouseenter');
				}
				// Hacky to remove egw's tooltip border and let the mouse in
				window.setTimeout(function() {
					jQuery('body .egw_tooltip')
						.css('border','none')
						.on('mouseenter', function() {
							event.div.off('mouseleave.tooltip');
							jQuery('body.egw_tooltip').remove();
							jQuery('body').append(this);
							jQuery(this).stop(true).fadeTo(400, 1)
								.on('mouseleave', function() {
									jQuery(this).fadeOut('400', function() {
										jQuery(this).remove();
										// Set up to work again
										event.set_statustext(event._tooltip());
									});
								});
						});

				},105);
			});
		this.title = jQuery(document.createElement('div'))
			.addClass("calendar_calEventHeader")
			.appendTo(this.div);
		this.body = jQuery(document.createElement('div'))
			.addClass("calendar_calEventBody")
			.appendTo(this.div);
		this.icons = jQuery(document.createElement('div'))
			.addClass("calendar_calEventIcons")
			.appendTo(this.title);

		this.setDOMNode(this.div[0]);

		this._need_actions_linked = false;
	},

	doLoadingFinished: function() {
		this._super.apply(this, arguments);

		// Already know what is needed to hook to cache
		if(this.options.value && this.options.value.row_id)
		{
			egw.dataRegisterUID(
				'calendar::'+this.options.value.row_id,
				this._UID_callback,
				this,
				this.getInstanceManager().execId,
				this.id
			);
		}
		return true;
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

		jQuery('body.egw_tooltip').remove();

		// Unregister, or we'll continue to be notified...
		if(this.options.value)
		{
			var old_app_id = this.options.value.row_id;
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

	/**
	 * Callback for changes in cached data
	 */
	_UID_callback: function _UID_callback(event) {
		// Copy to avoid changes, which may cause nm problems
		var value = event === null ? null : jQuery.extend({},event);

		// Make sure id is a string, check values
		if(value)
		{
			this._values_check(value);
		}

		// Check for changing days in the grid view
		if(!this._sameday_check(value))
		{
			// May need to update parent to remove out-of-view events
			var parent = this._parent;
			this._parent.removeChild(this);
			if(event === null && parent && parent._out_of_view)
			{
				parent._out_of_view();
			}

			// This should now cease to exist, as new events have been created
			this.free();
			return;
		}

		// Copy to avoid changes, which may cause nm problems
		this.options.value = jQuery.extend({},value);

		if(this._parent.options.date)
		{
			this.options.value.date = this._parent.options.date;
		}

		// Let parent position
		this._parent.position_event(this);

		// Parent may remove this if the date isn't the same
		if(this._parent)
		{
			this._update();
		}
	},

	/**
	 * Draw the event
	 */
	_update: function() {

		// Update to reflect new information
		var event = this.options.value;

		var id = event.row_id ? event.row_id : event.id + (event.recur_type ? ':'+event.recur_date : '');
		var formatted_start = event.start.toJSON();

		this.set_id('event_' + id);
		if(this._actionObject)
		{
			this._actionObject.id = 'calendar::' + id;
		}

		this._need_actions_linked = true;

		// Make sure category stuff is there
		// Fake it to use the cache / call - if already there, these will return
		// immediately.
		var im = this.getInstanceManager();
		et2_selectbox.cat_options({
			_type:'select-cat',
			getInstanceManager: function() {return im}
		}, {application:event.app||'calendar'});

		// Get CSS too
		egw.includeCSS('/api/categories.php?app='+event.app);

		// Need cleaning? (DnD helper removes content)
		if(!this.div.has(this.title).length)
		{
			this.div
				.empty()
				.append(this.title)
				.append(this.body);
		}
		if(!this._parent.options.readonly && !this.options.readonly && this.div.droppable('instance'))
		{
			this.div
				// Let timegrid always get the drag
				.droppable('option','greedy',false);
		}
		// DOM nodes
		this.div
			// Set full day flag
			.attr('data-full_day', event.whole_day)

			// Put everything we need for basic interaction here, so it's available immediately
			.attr('data-id', event.id)
			.attr('data-app', event.app || 'calendar')
			.attr('data-app_id', event.app_id)
			.attr('data-start', formatted_start)
			.attr('data-owner', event.owner)
			.attr('data-recur_type', event.recur_type)
			.attr('data-resize', event.whole_day ? 'WD' : '' + (event.recur_type ? 'S':''))
			.attr('data-priority', event.priority)
			// Remove any category classes
			.removeClass(function(index, css) {
				return (css.match (/(^|\s)cat_\S+/g) || []).join(' ');
			})
			// Remove any status classes
			.removeClass(function(index, css) {
				return (css.match(/calendar_calEvent\S+/g) || []).join(' ');
			})
			.removeClass('calendar_calEventSmall')
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

		this.body.toggleClass('calendar_calEventBodySmall', event.whole_day_on_top || false);

		// Header
		var title = !event.is_private ? egw.htmlspecialchars(event['title']) : egw.lang('private');

		this.title
			.html('<span class="calendar_calTimespan">'+this._get_timespan(event) + '<br /></span>')
			.append('<span class="calendar_calEventTitle">'+title+'</span>')

		// Colors - don't make them transparent if there is no color
		if(jQuery.Color("rgba(0,0,0,0)").toRgbaString() != jQuery.Color(this.div,'background-color').toRgbaString())
		{
			// Most statuses use colored borders
			this.div.css('border-color',status_class === 'calendar_calEventAllAccepted' ? this.div.css('background-color') : '');
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
			var start_time = jQuery.datepicker.formatTime(
				egw.preference("timeformat") === "12" ? "h:mmtt" : "HH:mm",
				{
					hour: event.start_m / 60,
					minute: event.start_m % 60,
					seconds: 0,
					timezone: 0
				},
				{"ampm": (egw.preference("timeformat") === "12")}
			).trim();

			this.body
				.html('<span class="calendar_calEventTitle">'+title+'</span>')
				.append('<span class="calendar_calTimespan">'+start_time + '</span>');
			if(this.options.value.description.trim())
			{
				this.body
					.append('<p>'+egw.htmlspecialchars(this.options.value.description)+'</p>');
			}
		}

		// Clear tooltip for regeneration
		this.set_statustext('');

		// Height specific section
		// This can take an unreasonable amount of time if parent is hidden
		if(this._parent.div.is(':visible'))
		{
			this._small_size();
		}
	},

	/**
	 * Calculate display variants for when event is too short for full display
	 *
	 * Display is based on the number of visible lines, calculated off the header
	 * height:
	 * 1 - show just the event title, with ellipsis
	 * 2 - Show timespan and title, with ellipsis
	 * > 4 - Show description as well, truncated to fit
	 */
	_small_size: function() {

		if(this.options.value.whole_day_on_top) return;

		// Skip for planner view, it's always small
		if(this._parent && this._parent.instanceOf(et2_calendar_planner_row)) return;

		// Pre-calculation reset
		this.div.removeClass('calendar_calEventSmall');
		this.body.css('height', 'auto');

		var line_height = parseFloat(this.div.css('line-height'));
		var visible_lines = Math.floor(this.div.innerHeight() / line_height);

		if(!this.title.height())
		{
			// Handle sizing while hidden, such as when calendar is not the active tab
			visible_lines = Math.floor(egw.getHiddenDimensions(this.div).h / egw.getHiddenDimensions(this.title).h);
		}
		visible_lines = Math.max(1,visible_lines);

		if(this.getParent() && this.getParent().instanceOf(et2_calendar_daycol))
		{
			this.div.toggleClass('calendar_calEventSmall',visible_lines < 4);
			this.div
				.attr('data-visible_lines', visible_lines);
		}
		else if (this.getParent() && this.getParent().instanceOf(et2_calendar_planner_row))
		{
			// Less than 8 hours is small
			this.div.toggleClass('calendar_calEventSmall',this.options.value.end.valueOf() - this.options.value.start.valueOf() < 28800000);
		}


		if(this.body.height() > this.div.height() - this.title.height() && visible_lines >= 4)
		{
			this.body.css('height', Math.floor((visible_lines-1)*line_height - this.title.height()) + 'px');
		}
		else
		{
			this.body.css('height', '');
		}
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

	/**
	 * Create tooltip shown on hover
	 *
	 * @return {String}
	 */
	_tooltip: function() {
		if(!this.div || !this.options.value || !this.options.value.app_id) return '';

		var border = this.div.css('borderTopColor');
		var bg_color = this.div.css('background-color');
		var header_color = this.title.css('color');
		var timespan = this._get_timespan(this.options.value);

		this._parent.date_helper.set_value(this.options.value.start.valueOf ? new Date(this.options.value.start) : this.options.value.start);
		var start = this._parent.date_helper.input_date.val();
		this._parent.date_helper.set_value(this.options.value.end.valueOf ? new Date(this.options.value.end) : this.options.value.end);
		var end = this._parent.date_helper.input_date.val();

		var times = !this.options.value.multiday ?
			'<span class="calendar_calEventLabel">'+this.egw().lang('Time')+'</span>:' + timespan :
			'<span class="calendar_calEventLabel">'+this.egw().lang('Start') + '</span>:' +start+ ' ' +
			'<span class="calendar_calEventLabel">'+this.egw().lang('End') + '</span>:' + end;
		var cat_label = '';
		if(this.options.value.category)
		{
			var cat = et2_createWidget('select-cat',{'readonly':true},this);
			cat.set_value(this.options.value.category);
			cat_label = this.options.value.category.indexOf(',') <= 0 ? cat.span.text() : [];
			if(typeof cat_label != 'string')
			{
				cat.span.children().each(function() {
					cat_label.push(jQuery(this).text());
				});
				cat_label = cat_label.join(', ');
			}
			cat.destroy();
		}
		var participants = '';
		if(this.options.value.participant_types[''])
		{
			participants += this.options.value.participant_types[''].join("<br />");
		}
		for(var type_name in this.options.value.participant_types)
		{
			if(type_name)
			{
				participants += '</p><p><span class="calendar_calEventLabel">'+type_name+'</span>:<br />';
				participants += this.options.value.participant_types[type_name].join("<br />");
			}
		}

		return '<div class="calendar_calEventTooltip ' + this._status_class() +' '+ this.options.class +
			'" style="border-color: '+border+'; background-color: '+bg_color+';">'+
			'<div class="calendar_calEventHeaderSmall">'+
				'<font style="color:'+header_color+'">'+timespan+'</font>'+
				this.icons[0].outerHTML+
			'</div>'+
			'<div class="calendar_calEventBody">'+
				'<p style="margin: 0px;">'+
				'<span class="calendar_calEventTitle">'+egw.htmlspecialchars(this.options.value.title)+'</span><br>'+
				egw.htmlspecialchars(this.options.value.description)+'</p>'+
				'<p style="margin: 2px 0px;">'+times+'</p>'+
				(this.options.value.location ? '<p><span class="calendar_calEventLabel">'+this.egw().lang('Location') + '</span>:' +
				egw.htmlspecialchars(this.options.value.location)+'</p>' : '')+
				(cat_label ? '<p><span class="calendar_calEventLabel">'+this.egw().lang('Category') + '</span>:' + cat_label +'</p>' : '')+
				'<p><span class="calendar_calEventLabel">'+this.egw().lang('Participants')+'</span>:<br />'+
					participants + '</p>'+ this._participant_summary(this.options.value.participants) +
			'</div>'+
		'</div>';
	},

	/**
	 * Generate participant summary line
	 *
	 * @returns {String}
	 */
	_participant_summary: function(participants)
	{
		if( Object.keys(this.options.value.participants).length < 2)
		{
			return '';
		}

		var participant_status = {A: 0, R: 0, T: 0, U: 0, D: 0};
		var status_label = {A: 'accepted', R: 'rejected', T: 'tentative', U: 'unknown', D: 'delegated'};
		var participant_summary = Object.keys(this.options.value.participants).length + ' ' + this.egw().lang('Participants')+': ';
		var status_totals = [];

		for(var id in this.options.value.participants)
		{
			var status = this.options.value.participants[id].substr(0,1);
			participant_status[status]++;
		}
		for(var status in participant_status)
		{
			if(participant_status[status] > 0)
			{
				status_totals.push(participant_status[status] + ' ' + this.egw().lang(status_label[status]));
			}
		}
		return participant_summary + status_totals.join(', ');
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
				timespan = this.egw().lang('Whole day');
			}
		}
		else
		{
			var duration = event.multiday ?
				(event.end - event.start) / 60000 :
				(event.end_m - event.start_m);
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

			timespan += ' - ' + jQuery.datepicker.formatTime(
				egw.preference("timeformat") === "12" ? "h:mmtt" : "HH:mm",
				{
					hour: event.end_m / 60,
					minute: event.end_m % 60,
					seconds: 0,
					timezone: 0
				},
				{"ampm": (egw.preference("timeformat") === "12")}
			).trim();

			timespan += ': ' + duration;
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

	/**
	 * Check to see if the provided event information is for the same date as
	 * what we're currently expecting, and that it has not been changed.
	 *
	 * If the date has changed, we adjust the associated daywise caches to move
	 * the event's ID to where it should be.  This check allows us to be more
	 * directly reliant on the data cache, and less on any other control logic
	 * elsewhere first.
	 *
	 * @param {Object} event Map of event data from cache
	 * @param {string} event.date For non-recurring, single day events, this is
	 *	the date the event is on.
	 * @param {string} event.start Start of the event (used for multi-day events)
	 * @param {string} event.end End of the event (used for multi-day events)
	 *
	 * @return {Boolean} Provided event data is for the same date
	 */
	_sameday_check: function(event)
	{
		// Event somehow got orphaned, or deleted
		if(!this._parent || event === null)
		{
			return false;
		}

		// Also check participants against owner
		var owner_match = et2_calendar_event.owner_check(event, this._parent);

		// Simple, same day
		if(owner_match && this.options.value.date && event.date == this.options.value.date)
		{
			return true;
		}

		// Multi-day non-recurring event spans days - date does not match
		var event_start = new Date(event.start);
		var event_end = new Date(event.end);
		if(owner_match && this._parent.date >= event_start && this._parent.date <= event_end)
		{
			return true;
		}

		// Delete all old actions
		if(this._actionObject)
		{
			this._actionObject.clear();
			this._actionObject.unregisterActions();
			this._actionObject = null;
		}

		// Update daywise caches
		var new_cache_id = app.classes.calendar._daywise_cache_id(event.date,this._parent.options.owner);
		var new_daywise = egw.dataGetUIDdata(new_cache_id);
		new_daywise = new_daywise && new_daywise.data ? new_daywise.data : [];
		var old_cache_id = false;
		if(this.options.value && this.options.value.date)
		{
			old_cache_id = app.classes.calendar._daywise_cache_id(this.options.value.date,this._parent.options.owner);
		}

		if(new_cache_id != old_cache_id)
		{
			var old_daywise = egw.dataGetUIDdata(old_cache_id);
			old_daywise = old_daywise && old_daywise.data ? old_daywise.data : [];
			old_daywise.splice(old_daywise.indexOf(this.options.value.row_id),1);
			egw.dataStoreUID(old_cache_id,old_daywise);

			if (new_daywise.indexOf(event.row_id) < 0)
			{
				new_daywise.push(event.row_id);
			}
			if(new_daywise.data !== null)
			{
				egw.dataStoreUID(new_cache_id,new_daywise);
			}
		}

		return false;
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
	 * Calls et2_calendar_event.recur_prompt with this event's value.
	 *
	 * @param {et2_calendar_event~prompt_callback} callback
	 * @param {Object} [extra_data]
	 */
	recur_prompt: function(callback, extra_data)
	{
		et2_calendar_event.recur_prompt(this.options.value,callback,extra_data);
	},

	/**
	 * Show the series split prompt for this event
	 *
	 * Calls et2_calendar_event.series_split_prompt with this event's value.
	 *
	 * @param {et2_calendar_event~prompt_callback} callback
	 */
	series_split_prompt: function(callback)
	{
		et2_calendar_event.series_split_prompt(this.options.value,this.options.value.recur_date, callback);
	},

	/**
	 * Copy the actions set on the parent, apply them to self
	 *
	 * This can take a while to do, so we try to do it only when needed - on mouseover
	 */
	_copy_parent_actions: function()
	{
		// Copy actions set in parent
		if(!this.options.readonly && !this._parent.options.readonly)
		{
			var action_parent = this;
			while(action_parent != null && !action_parent.options.actions &&
				!action_parent.instanceOf(et2_container)
			)
			{
				action_parent = action_parent.getParent();
			}
			try {
				this._link_actions(action_parent.options.actions||{});
				this._need_actions_linked = false;
			} catch (e) {
				// something went wrong, but keep quiet about it
			}
		}
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
			var objectManager = this.getParent()._actionObject || this.getParent().getParent()._actionObject ||
			   egw_getAppObjectManager(true).getObjectById(this._parent._parent._parent.id) || egw_getAppObjectManager(true);
			this._actionObject = objectManager.getObjectById('calendar::'+this.options.value.row_id);
		}

		if (this._actionObject == null) {
			// Add a new container to the object manager which will hold the widget
			// objects
			this._actionObject = objectManager.insertObject(false, new egwActionObject(
				'calendar::'+this.options.value.row_id, objectManager, new et2_event_action_object_impl(this,this.getDOMNode()),
				this._actionManager || objectManager.manager.getActionById('calendar::'+this.options.value.row_id) || objectManager.manager
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
		if(this._actionObject.parent.getActionLink('invite'))
		{
			action_links.push('invite');
		}
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
});}).call(this);
et2_register_widget(et2_calendar_event, ["calendar-event"]);

// Static class stuff
/**
 * Check event owner against a parent object
 *
 * As an event is edited, its participants may change.  Also, as the state
 * changes we may change which events are displayed and show the same event
 * in several places for different users.  Here we check the event participants
 * against an owner value (which may be an array) to see if the event should be
 * displayed or included.
 *
 * @param {Object} event - Event information
 * @param {et2_widget_daycol|et2_widget_planner_row} parent - potential parent object
 *	that has an owner option
 * @param {boolean} owner_too - Include the event owner in consideration, or only
 *	event participants
 *
 * @return {boolean} Should the event be displayed
 */
et2_calendar_event.owner_check = function owner_check(event, parent, owner_too)
{
	var owner_match = true;
	if(typeof owner_too === 'undefined' && app.calendar.state.status_filter)
	{
		owner_too = app.calendar.state.status_filter === 'owner';
	}
	var options = false;
	if(app.calendar && app.calendar.sidebox_et2 && app.calendar.sidebox_et2.getWidgetById('owner'))
	{
		options = app.calendar.sidebox_et2.getWidgetById('owner').taglist.getSelection();
	}
	else
	{
		options = parent.getArrayMgr("sel_options").getRoot().getEntry('owner');
	}
	if(event.participants && typeof parent.options.owner != 'undefined' && parent.options.owner.length > 0)
	{
		var parent_owner = jQuery.extend([], typeof parent.options.owner !== 'object' ?
			[parent.options.owner] :
			parent.options.owner);
		owner_match = false;
		var length = parent_owner.length;
		for(var i = 0; i < length; i++ )
		{
			// Handle grouped resources like mailing lists, they won't match so
			// we need the list - pull it from sidebox owner
			if(isNaN(parent_owner[i]) && options && options.find)
			{
				var resource = options.find(function(element) {return element.id == parent_owner[i];}) || {};
				if(resource && resource.resources)
				{
					parent_owner.splice(i,1);
					parent_owner = parent_owner.concat(resource.resources);
					continue;
				}
			}

			if (parseInt(parent_owner[i]) < 0)
			{
				// Add in groups, if we can get them (this is syncronous)
				egw.accountData(parent_owner[i],'account_id',true,function(members) {
					parent_owner = parent_owner.concat(Object.keys(members));
				});
			}
		}
		var participants = jQuery.extend([],Object.keys(event.participants));
		for(var i = 0; i < participants.length; i++ )
		{
			var id = participants[i];
			// Expand group invitations
			if (parseInt(id) < 0)
			{
				// Add in groups, if we can get them (this is syncronous)
				egw.accountData(id,'account_id',true,function(members) {
					participants = participants.concat(Object.keys(members));
				});
			}
			if(parent.options.owner == id ||
				parent_owner.indexOf &&
				parent_owner.indexOf(id) >= 0)
			{
				owner_match = true;
				break;
			}
		}
	}
	if(owner_too && !owner_match)
	{
		owner_match = (parent.options.owner == event.owner ||
			parent_owner.indexOf &&
			parent_owner.indexOf(event.owner) >= 0);
	}
	return owner_match;
};

/**
 * @callback et2_calendar_event~prompt_callback
 * @param {string} button_id - One of ok, exception, series, single or cancel
 *	depending on which buttons are on the prompt
 * @param {Object} event_data - Event information - whatever you passed in to
 *	the prompt.
 */
/**
 * Recur prompt
 * If the event is recurring, asks the user if they want to edit the event as
 * an exception, or change the whole series.  Then the callback is called.
 *
 * If callback is not provided, egw.open() will be used to open an edit dialog.
 *
 * If you call this on a single (non-recurring) event, the callback will be
 * executed immediately, with the passed button_id as 'single'.
 *
 * @param {Object} event_data - Event information
 * @param {string} event_data.id - Unique ID for the event, possibly with a
 *	timestamp
 * @param {string|Date} event_data.start - Start date/time for the event
 * @param {number} event_data.recur_type - Recur type, or 0 for a non-recurring event
 * @param {et2_calendar_event~prompt_callback} [callback] - Callback is
 *	called with the button (exception, series, single or cancel) and the event
 *	data.
 * @param {Object} [extra_data] - Additional data passed to the callback, used
 *	as extra parameters for default callback
 *
 * @augments {et2_calendar_event}
 */
et2_calendar_event.recur_prompt = function(event_data, callback, extra_data)
{
	var edit_id = event_data.app_id;
	var edit_date = event_data.start;

	// seems window.opener somehow in certian conditions could be from different origin
	// we try to catch the exception and in this case retrive the egw object from current window.
	try {
		var egw = this.egw ? (typeof this.egw == 'function' ? this.egw() : this.egw) : window.opener && typeof window.opener.egw != 'undefined' ? window.opener.egw('calendar'):window.egw('calendar');
	}
	catch(e){
		var egw = window.egw('calendar');
	}

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
 * There is no default callback, and nothing happens if you call this on a
 * single (non-recurring) event
 *
 * @param {Object} event_data - Event information
 * @param {string} event_data.id - Unique ID for the event, possibly with a timestamp
 * @param {string|Date} instance_date - The date of the edited instance of the event
 * @param {et2_calendar_event~prompt_callback} callback - Callback is
 *	called with the button (ok or cancel) and the event data.
 * @augments {et2_calendar_event}
 */
et2_calendar_event.series_split_prompt = function(event_data, instance_date, callback)
{
	// seems window.opener somehow in certian conditions could be from different origin
	// we try to catch the exception and in this case retrive the egw object from current window.
	try {
		var egw = this.egw ? (typeof this.egw == 'function' ? this.egw() : this.egw) : window.opener && typeof window.opener.egw != 'undefined' ? window.opener.egw('calendar'):window.egw('calendar');
	}
	catch(e){
		var egw = window.egw('calendar');
	}

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
 * to tie actions to DOM nodes.  I'm not sure if we need this.
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
