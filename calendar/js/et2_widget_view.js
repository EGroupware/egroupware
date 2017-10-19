/*
 * Egroupware
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package
 * @subpackage
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

/*egw:uses
	/etemplate/js/et2_core_valueWidget;
*/

/**
 * Parent class for the various calendar views to reduce copied code
 *
 *
 * et2_calendar_view is responsible for its own loader div, which is displayed while
 * the times & days are redrawn.
 *
 * @augments et2_valueWidget
 */
var et2_calendar_view = (function(){ "use strict"; return et2_valueWidget.extend(
{
	createNamespace: true,

	attributes: {
		owner: {
			name: "Owner",
			type: "any", // Integer, or array of integers, or string like r13 (resources, addressbook)
			default: [egw.user('account_id')],
			description: "Account ID number of the calendar owner, if not the current user"
		},
		start_date: {
			name: "Start date",
			type: "any"
		},
		end_date: {
			name: "End date",
			type: "any"
		}
	},

	/**
	 * Constructor
	 *
	 * @memberOf et2_calendar_view
	 * @constructor
	 */
	init: function init() {
		this._super.apply(this, arguments);

		// Used for its date calculations
		this.date_helper = et2_createWidget('date-time',{},null);
		this.date_helper.loadingFinished();

		this.loader = jQuery('<div class="egw-loading-prompt-container ui-front loading"></div>');
		this.update_timer = null;

		// Used to support dragging on empty space to create an event
		this.drag_create = {
			start: null,
			end: null,
			parent: null,
			event: null
		};
	},

	destroy: function destroy() {
		this._super.apply(this, arguments);

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
		this.loader.hide(0).prependTo(this.div);
		if(this.options.owner) this.set_owner(this.options.owner);
	},

	/**
	 * Something changed, and the view need to be re-drawn.  We wait a bit to
	 * avoid re-drawing twice if start and end date both changed, then recreate
	 * as needed.
	 *
	 * @param {boolean} [trigger_event=false] Trigger an event once things are done.
	 *	Waiting until invalidate completes prevents 2 updates when changing the date range.
	 * @returns {undefined}
	 *
	 * @memberOf et2_calendar_view
	 */
	invalidate: function invalidate(trigger_event) {
		// If this wasn't a stub, we'd set this.update_timer
	},

	/**
	 * Returns the current start date
	 *
	 * @returns {Date}
	 *
	 * @memberOf et2_calendar_view
	 */
	get_start_date: function get_start_date() {
		return new Date(this.options.start_date);
	},

	/**
	 * Returns the current start date
	 *
	 * @returns {Date}
	 *
	 * @memberOf et2_calendar_view
	 */
	get_end_date: function get_end_date() {
		return new Date(this.options.end_date);
	},

	/**
	 * Change the start date
	 *
	 * Changing the start date will invalidate the display, and it will be redrawn
	 * after a timeout.
	 *
	 * @param {string|number|Date} new_date New starting date.  Strings can be in
	 *	any format understood by et2_widget_date, or Ymd (eg: 20160101).
	 * @returns {undefined}
	 *
	 * @memberOf et2_calendar_view
	 */
	set_start_date: function set_start_date(new_date)
	{
		if(!new_date || new_date === null)
		{
			new_date = new Date();
		}

		// Use date widget's existing functions to deal
		if(typeof new_date === "object" || typeof new_date === "string" && new_date.length > 8)
		{
			this.date_helper.set_value(new_date);
		}
		else if(typeof new_date === "string")
		{
			this.date_helper.set_year(new_date.substring(0,4));
			// Avoid overflow into next month, since we re-use date_helper
			this.date_helper.set_date(1);
			this.date_helper.set_month(new_date.substring(4,6));
			this.date_helper.set_date(new_date.substring(6,8));
		}

		var old_date = this.options.start_date;
		this.options.start_date = new Date(this.date_helper.getValue());

		if(old_date !== this.options.start_date && this.isAttached())
		{
			this.invalidate(true);
		}
	},

	/**
	 * Change the end date
	 *
	 * Changing the end date will invalidate the display, and it will be redrawn
	 * after a timeout.
	 *
	 * @param {string|number|Date} new_date - New end date.  Strings can be in
	 *	any format understood by et2_widget_date, or Ymd (eg: 20160101).
	 * @returns {undefined}
	 *
	 * @memberOf et2_calendar_view
	 */
	set_end_date: function set_end_date(new_date)
	{
		if(!new_date || new_date === null)
		{
			new_date = new Date();
		}
		// Use date widget's existing functions to deal
		if(typeof new_date === "object" || typeof new_date === "string" && new_date.length > 8)
		{
			this.date_helper.set_value(new_date);
		}
		else if(typeof new_date === "string")
		{
			this.date_helper.set_year(new_date.substring(0,4));
			// Avoid overflow into next month, since we re-use date_helper
			this.date_helper.set_date(1);
			this.date_helper.set_month(new_date.substring(4,6));
			this.date_helper.set_date(new_date.substring(6,8));
		}

		var old_date = this.options.end_date;
		this.options.end_date = new Date(this.date_helper.getValue());

		if(old_date !== this.options.end_date && this.isAttached())
		{
			this.invalidate(true);
		}
	},

	/**
	 * Set which users to display
	 *
	 * Changing the owner will invalidate the display, and it will be redrawn
	 * after a timeout.
	 *
	 * @param {number|number[]|string|string[]} _owner - Owner ID, which can
	 *	be an account ID, a resource ID (as defined in calendar_bo, not
	 *	necessarily an entry from the resource app), or a list containing a
	 *	combination of both.
	 *
	 * @memberOf et2_calendar_view
	 */
	set_owner: function set_owner(_owner)
	{
		var old = this.options.owner;

		// 0 means current user, but that causes problems for comparison,
		// so we'll just switch to the actual ID
		if(_owner == '0')
		{
			_owner = [egw.user('account_id')];
		}
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
		if(this.isAttached() && (
			typeof old === "number" && typeof _owner === "number" && old !== this.options.owner ||
			// Array of ids will not compare as equal
			((typeof old === 'object' || typeof _owner === 'object') && old.toString() !== _owner.toString()) ||
			// Strings
			typeof old === 'string' && ''+old !== ''+this.options.owner
		))
		{
			this.invalidate(true);
		}
	},

	/**
	 * Provide specific data to be displayed.
	 * This is a way to set start and end dates, owner and event data in one call.
	 *
	 * If events are not provided in the array,
	 * @param {Object[]} events Array of events, indexed by date in Ymd format:
	 *	{
	 *		20150501: [...],
	 *		20150502: [...]
	 *	}
	 *	Days should be in order.
	 *  {string|number|Date} events.start_date - New start date
	 *  {string|number|Date} events.end_date - New end date
	 *  {number|number[]|string|string[]} event.owner - Owner ID, which can
	 *	be an account ID, a resource ID (as defined in calendar_bo, not
	 *	necessarily an entry from the resource app), or a list containing a
	 *	combination of both.
	 */
	set_value: function set_value(events)
	{
		if(typeof events !== 'object') return false;

		if(events.length && events.length > 0 || !jQuery.isEmptyObject(events))
		{
			this.set_disabled(false);
		}
		if(events.id)
		{
			this.set_id(events.id);
			delete events.id;
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
		// set_owner() wants start_date set to get the correct week number
		// for the corner label
		if(events.owner)
		{
			this.set_owner(events.owner);
			delete events.owner;
		}

		this.value = events || {};

		// None of the above changed anything, hide the loader
		if(!this.update_timer)
		{
			window.setTimeout(jQuery.proxy(function() {this.loader.hide();},this),200);
		}
	},

	/**
	 * Calendar supports many different owner types, including users & resources.
	 * This translates an ID to a user-friendly name.
	 *
	 * @param {string} user
	 * @returns {string}
	 *
	 * @memberOf et2_calendar_view
	 */
	_get_owner_name: function _get_owner_name(user) {
		var label = undefined;
		if(parseInt(user) === 0)
		{
			// 0 means current user
			user = egw.user('account_id');
		}
		if(et2_calendar_view.owner_name_cache[user])
		{
			return et2_calendar_view.owner_name_cache[user];
		}
		if (!isNaN(user))
		{
			user = parseInt(user);
			var accounts = egw.accounts('both');
			for(var j = 0; j < accounts.length; j++)
			{
				if(accounts[j].value === user)
				{
					label = accounts[j].label;
					break;
				}
			}
		}
		if(typeof label === 'undefined')
		{
			// Not found?  Ask the sidebox owner widget (it gets updated) or the original arrayMgr
			var options = false
			if(app.calendar && app.calendar.sidebox_et2 && app.calendar.sidebox_et2.getWidgetById('owner'))
			{
				options = app.calendar.sidebox_et2.getWidgetById('owner').taglist.getSelection();
			}
			else
			{
				options = this.getArrayMgr("sel_options").getRoot().getEntry('owner');
			}
			if(options && options.find)
			{
				var found = options.find(function(element) {return element.id == user;}) || {};
				if(found && found.label)
				{
					label = found.label;
				}
			}
			if(!label)
			{
				// No sidebox?  Must be in home or sitemgr (no caching) - ask directly
				label = '?';
				egw.json('calendar_owner_etemplate_widget::ajax_owner',user,function(data) {label = data;}, this).sendRequest();
			}
		}
		if(label)
		{
			et2_calendar_view.owner_name_cache[user] = label;
		}
		return label;
	},

	/**
	 * Find the event information linked to a given DOM node
	 *
	 * @param {HTMLElement} dom_node - It should have something to do with an event
	 * @returns {Object}
	 */
	_get_event_info: function _get_event_info(dom_node)
	{
		// Determine as much relevant info as can be found
		var event_node = jQuery(dom_node).closest('[data-id]',this.div)[0];
		var day_node = jQuery(event_node).closest('[data-date]',this.div)[0];

		var result = jQuery.extend({
				event_node: event_node,
				day_node: day_node
			},
			event_node ? event_node.dataset : {},
			day_node ? day_node.dataset : {}
		);

		// Widget ID should be the DOM node ID without the event_ prefix
		if(event_node && event_node.id)
		{
			var widget_id = event_node.id || '';
			widget_id = widget_id.split('event_');
			widget_id.shift();
			result.widget_id = 'event_' + widget_id.join('');
		}
		return result;
	},

	/**
	 * Starting (mousedown) handler to support drag to create
	 *
	 * Extending classes need to set this.drag_create.parent, which is the
	 * parent container (child of extending class) that will directly hold the
	 * event.
	 *
	 * @param {String} start Date string (JSON format)
	 */
	_drag_create_start: function(start)
	{
		this.drag_create.start = jQuery.extend({},start);
		if(!this.drag_create.start.date)
		{
			this.drag_create.start = null;
		}
		this.drag_create.end = start;

		// Clear some stuff, if last time did not complete
		if(this.drag_create.event)
		{
			if(this.drag_create.event.destroy)
			{
				this.drag_create.event.destroy();
			}
			this.drag_create.event = null;
		}
		// Wait a bit before adding an "event", it may be just a click
		window.setTimeout(jQuery.proxy(function() {
			// Create event
			this._drag_create_event();
		}, this), 250);
	},

	/**
	 * Create or update an event used for feedback while dragging on empty space,
	 * so user can see something is happening
	 */
	_drag_create_event: function()
	{
		if(!this.drag_create.parent || !this.drag_create.start)
		{
			return;
		}
		if(!this.drag_create.event)
		{
			this.date_helper.set_value(this.drag_create.start.date);
			var value = jQuery.extend({},
				this.drag_create.start,
				this.drag_create.end,
				{
					start: this.drag_create.start.date,
					end: this.drag_create.end && this.drag_create.end.date || this.drag_create.start.date,
					date:  ""+this.date_helper.get_year()+
						sprintf("%02d",this.date_helper.get_month())+
						sprintf("%02d",this.date_helper.get_date()),
					title: '',
					description: '',
					owner: this.options.owner,
					participants: this.options.owner,
					app: 'calendar',
					whole_day_on_top: this.drag_create.start.whole_day
				}
			);
			this.drag_create.event = et2_createWidget('calendar-event',{
				id:'event_drag',
				value: value
			},this.drag_create.parent);
			this.drag_create.event._values_check(value);
			this.drag_create.event.doLoadingFinished();
		}

	},

	_drag_update_event: function()
	{
		if(!this.drag_create.event || !this.drag_create.start || !this.drag_create.end
			|| !this.drag_create.parent || !this.drag_create.event._type)
		{
			return;
		}
		else if (this.drag_create.end)
		{
			this.drag_create.event.options.value.end = this.drag_create.end.date;
			this.drag_create.event._values_check(this.drag_create.event.options.value);
		}
		this.drag_create.event._update()
		this.drag_create.parent.position_event(this.drag_create.event);
	},

	/**
	 * Ending (mouseup) handler to support drag to create
	 *
	 * @param {String} end Date string (JSON format)
	 */
	_drag_create_end: function(end)
	{
		this.div.css('cursor','');
		if(typeof end === 'undefined')
		{
			end = {};
		}

		if(this.drag_create.start && end.date &&
			JSON.stringify(this.drag_create.start.date) !== JSON.stringify(end.date))
		{
			// Drag from start to end, open dialog
			var options = {
				start: this.drag_create.start.date < end.date ? this.drag_create.start.date : end.date,
				end: this.drag_create.start.date < end.date ? end.date : this.drag_create.start.date
			};

			// Whole day needs to go from 00:00 to 23:59
			if(end.whole_day || this.drag_create.start.whole_day)
			{
				var start = new Date(options.start);
				start.setUTCHours(0);
				start.setUTCMinutes(0);
				options.start = start.toJSON();

				var end = new Date(options.end);
				end.setUTCHours(23);
				end.setUTCMinutes(59);
				options.end = end.toJSON();
			}

			// Add anything else that was set, but not date
			jQuery.extend(options,this.drag_create.start, end);
			delete(options.date);

			// Make sure parent is set, if needed
			if (this.drag_create.parent && this.drag_create.parent.options.owner !== app.calendar.state.owner && !options.owner)
			{
				options.owner = this.drag_create.parent.options.owner;
			}

			// Remove empties
			for(var key in options)
			{
				if(!options[key]) delete options[key];
			}
			this.egw().open(null, 'calendar', 'add', options, '_blank');

			// Wait a bit, having these stops the click
			window.setTimeout(jQuery.proxy(function() {
				this.drag_create.start = null;
				this.drag_create.end = null;
				this.drag_create.parent = null;
				if(this.drag_create.event)
				{
					if(this.drag_create.event.destroy)
					{
						this.drag_create.event.destroy();
					}
					this.drag_create.event = null;
				}
			},this),100);

			return false;
		}

		this.drag_create.start = null;
		this.drag_create.end = null;
		this.drag_create.parent = null;
		if(this.drag_create.event)
		{
			try
			{
				if(this.drag_create.event.destroy)
				{
					this.drag_create.event.destroy();
				}
			} catch(e) {}
			this.drag_create.event = null;
		}
		return true;
	}

});}).call(this);

// Static class stuff
jQuery.extend(et2_calendar_view,
{
	/**
	 * Check if the view should be consolidated into one, or listed seperately
	 * based on the user's preferences
	 *
	 * @param {string[]} owners List of owners
	 * @param {string} view Name of current view (day, week)
	 * @returns {boolean} True of only one is needed, false if each owner needs
	 *	to be listed seperately.
	 */
	is_consolidated: function is_consolidated(owners, view)
	{
		// Seperate owners, or consolidated?
		return !(
			owners.length > 1 &&
			(view === 'day' && owners.length < parseInt(egw.preference('day_consolidate','calendar')) ||
			view === 'week' && owners.length < parseInt(egw.preference('week_consolidate','calendar')))
		);
	},

	/**
	 * Cache to map owner & resource IDs to names, helps cut down on server requests
	 */
	owner_name_cache: {},

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
		var view = egw.window.et2_calendar_view ? egw.window.et2_calendar_view : this;

		// No country selected causes error, so skip if it's missing
		if(!view || !egw.preference('country','common')) return {};

		var cache = view.holiday_cache[year];
		if (typeof cache == 'undefined')
		{
			// Fetch with json instead of jsonq because there may be more than
			// one widget listening for the response by the time it gets back,
			// and we can't do that when it's queued.
			view.holiday_cache[year] = jQuery.getJSON(
				egw.link('/calendar/holidays.php', {year: year})
			);
		}
		cache = view.holiday_cache[year];
		if(typeof cache.done == 'function')
		{
			// pending, wait for it
			cache.done(jQuery.proxy(function(response) {
				view.holiday_cache[this.year] = response||undefined;

				egw.window.setTimeout(jQuery.proxy(function() {
					// Make sure widget hasn't been destroyed while we wait
					if(typeof this.widget.free == 'undefined')
					{
						this.widget.day_class_holiday();
					}
				},this),1);
			},{widget:widget,year:year}))
			.fail(jQuery.proxy(function() {
				view.holiday_cache[this.year] = undefined;
			}, {widget: widget, year: year}));
			return {};
		}
		else
		{
			return cache;
		}
	}
});
