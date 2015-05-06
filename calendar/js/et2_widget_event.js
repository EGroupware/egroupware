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

		// Main container
		this.div = $j(document.createElement("div"))
			.addClass("calendar_calEvent")
			.css('width',this.options.width);
		this.title = $j(document.createElement('div'))
			.addClass("calendar_calEventHeader")
			.appendTo(this.div);
		this.body = $j(document.createElement('div'))
			.addClass("calendar_calEventBody")
			.appendTo(this.div);

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
	},

	destroy: function() {
		this._super.apply(this, arguments);
	},

	set_value: function(_value) {
		this.options.value = _value;
		this._update(this.options.value);
	},

	_update: function(event) {

		var eventId = event.id.match(/-?\d+\.?\d*/g)[0];
		var appName = event.id.replace(/-?\d+\.?\d*/g,'');
		var app_id = event.app_id ? event.app_id : event.id + (event.recur_type ? ':'+event.recur_date : '')

		this.div
			.attr('data-draggable-id',event['id']+'_O'+event.owner+'_C'+(event.owner<0?'group'+Math.abs(event.owner):event.owner))
		
			// Put everything we need for basic interaction here, so it's available immediately
			.attr('data-id', eventId || event.id)
			.attr('data-app', appName || 'calendar')
			.attr('data-app_id', app_id)
			.attr('data-start', event.start)
			.attr('data-recur_type', event.recur_type)
		
			.toggleClass('calendar_calEventPrivate', event.private)
			// Remove any category classes
			.removeClass(function(index, css) {
				return (css.match (/(^|\s)cat_\S+/g) || []).join(' ');
			});
		if(event.category)
		{
			this.div.addClass('cat_' + event.category);
		}
		this.div.css('border-color', this.div.css('background-color'));

		this.div.toggleClass('calendar_calEventUnknown', event.participants[egw.user('account_id')][0] == 'U');

		this.title.toggle(!event.whole_day_on_top);
		this.body.toggleClass('calendar_calEventBodySmall', event.whole_day_on_top || false);
		
		// Header
		var title = !event.is_private ? event['title'] : egw.lang('private');
		var small_height = event['end_m']-event['start_m'] < 2*this._parent.display_settings.granularity ||
			event['end_m'] <= this._parent.display_settings.wd_start || event['start_m'] >= this._parent.display_settings.wd_end;

		this.title.text(small_height ? title : this._get_timespan(event))
			// Set title color based on background brightness
			.css('color', jQuery.Color(this.div.css('background-color')).lightness() > 0.5 ? 'black':'white');

		// Body
		if(event.whole_day_on_top)
		{
			this.body.html(title);
		}
		else
		{
			this.body.html('<span class="calendar_calEventTitle">'+title+'</span>')
		}
		this.body
			// Set background color to a lighter version of the header color
			.css('background-color',jQuery.Color(this.div.css('background-color')).lightness("+=0.3"));

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
					egw.preference("timeformat") === 12 ? "h:mmtt" : "HH:mm",
					{
						hour: event.start_m / 60,
						minute: event.start_m % 60,
						seconds: 0,
						timezone: 0
					},
					{"ampm": (egw.preference("timeformat") === "12")}
				).trim()+' - '+jQuery.datepicker.formatTime(
					egw.preference("timeformat") === 12 ? "h:mmtt" : "HH:mm",
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
				timespan = egw.lang('all day');
			}
		}
		else
		{
			var duration = event.end_m - event.start_m;
			if (event.end_m === 24*60-1) ++duration;
			duration = Math.floor(duration/60) + this.egw().lang('h')+(duration%60 ? duration%60 : '');

			timespan = jQuery.datepicker.formatTime(
				egw.preference("timeformat") === 12 ? "h:mmtt" : "HH:mm",
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
	
	_edit: function()
	{
		if(this.options.value.recur_type)
		{
			var edit_id = this.options.value.id;
			var edit_date = this.options.value.start;
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
			this.egw().open(this.options.value.id, 'calendar','edit');
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
et2_register_widget(et2_calendar_event, ["calendar-event"]);