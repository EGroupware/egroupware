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
	/etemplate/js/et2_core_valueWidget;
*/

import {et2_createWidget, et2_widget, WidgetConfig} from "../../api/js/etemplate/et2_core_widget";
import {et2_valueWidget} from "../../api/js/etemplate/et2_core_valueWidget";
import {ClassWithAttributes} from "../../api/js/etemplate/et2_core_inheritance";
import {et2_calendar_event} from "./et2_widget_event";
import {formatDate} from "../../api/js/etemplate/Et2Date/Et2Date";

/**
 * Parent class for the various calendar views to reduce copied code
 *
 *
 * et2_calendar_view is responsible for its own loader div, which is displayed while
 * the times & days are redrawn.
 *
 * @augments et2_valueWidget
 */
export class et2_calendar_view extends et2_valueWidget
{
	static readonly _attributes : any = {
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
	};
	protected dataStorePrefix: string = 'calendar';
	protected loader: JQuery;
	protected div: JQuery;
	protected now_div: JQuery;

	protected update_timer: number = null;
	protected now_timer: number = null;
	protected drag_create: { parent: et2_widget; start: any; end: any; event: et2_calendar_event };
	protected value: any;

	protected _actionObject: egwActionObject;

	/**
	 * Constructor
	 *
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_calendar_view._attributes, _child || {}));

		this.loader = jQuery('<div class="egw-loading-prompt-container ui-front loading"></div>');
		this.now_div = jQuery('<div class="calendar_now"/>');
		this.update_timer = null;
		this.now_timer = null;

		// Used to support dragging on empty space to create an event
		this.drag_create = {
			start: null,
			end: null,
			parent: null,
			event: null
		};
	}

	destroy() {
		super.destroy();

		// Stop the invalidate timer
		if(this.update_timer)
		{
			window.clearTimeout(this.update_timer);
		}

		// Stop the 'now' line
		if(this.now_timer)
		{
			window.clearInterval(this.now_timer);
		}
	}

	doLoadingFinished( )
	{
		super.doLoadingFinished();
		this.loader.hide(0).prependTo(this.div);
		this.div.append(this.now_div);
		if(this.options.owner) this.set_owner(this.options.owner);

		// Start moving 'now' line
		this.now_timer = window.setInterval(this._updateNow.bind(this), 60000);
		return true;
	}

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
	invalidate(trigger_event) {
		// If this wasn't a stub, we'd set this.update_timer
	}

	/**
	 * Returns the current start date
	 *
	 * @returns {Date}
	 *
	 * @memberOf et2_calendar_view
	 */
	get_start_date() {
		return new Date(this.options.start_date);
	}

	/**
	 * Returns the current start date
	 *
	 * @returns {Date}
	 *
	 * @memberOf et2_calendar_view
	 */
	get_end_date() {
		return new Date(this.options.end_date);
	}

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
	set_start_date(new_date)
	{
		if(!new_date || new_date === null)
		{
			new_date = new Date();
		}

		let old_date = this.options.start_date;
		this.options.start_date = this.date_helper(new_date)

		if(old_date !== this.options.start_date && this.isAttached())
		{
			this.invalidate(true);
		}
	}

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
	set_end_date(new_date)
	{
		if(!new_date || new_date === null)
		{
			new_date = new Date();
		}

		let old_date = this.options.end_date;
		this.options.end_date = this.date_helper(new_date);

		if(old_date !== this.options.end_date && this.isAttached())
		{
			this.invalidate(true);
		}
	}

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
	set_owner(_owner)
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
	}

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
	set_value(events)
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
			window.setTimeout(jQuery.proxy(function() {this.loader.hide();}, this), 200);
		}
	}


	/**
	 * Parse something that we think is a date into an actual date.
	 *
	 * The passed value could be a Date, or a string in an unknown format, or a timestamp
	 *
	 * @param {Date | string | number} _value
	 */
	date_helper(_value : Date | string | number)
	{
		if(_value === null || _value === "" || _value === undefined || _value === 0)
		{
			return undefined;
		}
		if(_value instanceof Date)
		{
			// Return a copy, because often modifications are made after
			return new Date(_value.getTime());
		}
		let date = new Date();
		if(typeof _value === "string" && _value.length == 8)
		{
			// Ymd format: 20000101
			date.setFullYear(parseInt(_value.substring(0, 4)));
			// Avoid overflow into next month since it already has a value
			date.setDate(1);
			date.setMonth(parseInt(_value.substring(4, 6)) - 1);
			date.setDate(parseInt(_value.substring(6, 8)));
			date.setUTCHours(0);
			date.setUTCMinutes(0);
			date.setUTCSeconds(0);
			date.setUTCMilliseconds(0);
		}
		// Check for full timestamp
		else if(typeof _value == 'string' && _value.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2})\:(\d{2})\:(\d{2})(?:\.\d{3})?(?:Z|[+-](\d{2})\:(\d{2})|)$/))
		{
			date = new Date(_value);
		}
		// timestamp in usertime
		else if(typeof _value == 'number' || typeof _value == 'string' && !isNaN(<number><unknown>_value) && _value[0] != '+' && _value[0] != '-')
		{
			date = new Date((typeof _value == "number" ? _value : parseInt(_value)) * 1000);
		}
		// @ts-ignore
		else if(typeof _value == 'object' && typeof _value.date !== "undefined")
		{
			// @ts-ignore
			return this.date_helper(_value.date);
		}
		// @ts-ignore
		else if(typeof _value == 'object' && typeof _value.valueOf !== "undefined")
		{
			date = _value;
		}
		else
		{
			// string starting with + or - --> add/substract number of seconds from current value
			date.setTime(date.getTime() + 1000 * parseInt("" + _value));
		}

		return date;
	}

	_createNamespace()
	{
		return true;
	}

	/**
	 * Update the 'now' line
	 *
	 * Here we just do some limit checks and return the current date/time.
	 * Extending widgets should handle position.
	 *
	 * @private
	 */
	public _updateNow()
	{
		var tempDate = new Date();
		var now = new Date(tempDate.getFullYear(), tempDate.getMonth(), tempDate.getDate(),tempDate.getHours(),tempDate.getMinutes()-tempDate.getTimezoneOffset(),0);

		now = this.date_helper(now.toJSON());
		if(this.get_start_date() <= now && this.get_end_date() >= now)
		{
			return now;
		}

		this.now_div.hide();
		return false;
	}

	/**
	 * Calendar supports many different owner types, including users & resources.
	 * This translates an ID to a user-friendly name.
	 *
	 * @param {string} user
	 * @returns {string}
	 *
	 * @memberOf et2_calendar_view
	 */
	_get_owner_name(user) {
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
			var accounts = <any[]>egw.accounts('both');
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
			let options = false;
			if(app.calendar && app.calendar.sidebox_et2 && app.calendar.sidebox_et2.getWidgetById('owner'))
			{
				options = app.calendar.sidebox_et2.getWidgetById('owner').select_options;
			}
			else
			{
				options = this.getArrayMgr("sel_options").getRoot().getEntry('owner');
			}
			if(options && options.find)
			{
				var found = options.find(function(element) {return element.value == user;}) || {};
				if(found && found.label && found.label !== user)
				{
					label = found.label;
				}
			}
			if(!label)
			{
				// No sidebox?  Must be in home or sitemgr (no caching) - ask directly
				label = '?';
				egw.jsonq('calendar_owner_etemplate_widget::ajax_owner',user,function(data) {
					et2_calendar_view.owner_name_cache[user] = data;
					this.invalidate(true);

					// Set owner to make sure labels get set
					if(this.owner && typeof this.owner.set_value === 'function')
					{
						this.owner.set_value(data);
					}
				}.bind(this), this);
			}
		}
		if(label)
		{
			et2_calendar_view.owner_name_cache[user] = label;
		}
		return label;
	}

	/**
	 * Find the event information linked to a given DOM node
	 *
	 * @param {HTMLElement} dom_node - It should have something to do with an event
	 * @returns {Object}
	 */
	_get_event_info(dom_node)
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
	}

	/**
	 * Starting (mousedown) handler to support drag to create
	 *
	 * Extending classes need to set this.drag_create.parent, which is the
	 * parent container (child of extending class) that will directly hold the
	 * event.
	 *
	 * @param {String} start Date string (JSON format)
	 */
	_drag_create_start(start)
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
	}

	/**
	 * Create or update an event used for feedback while dragging on empty space,
	 * so user can see something is happening
	 */
	_drag_create_event()
	{
		if(!this.drag_create.parent || !this.drag_create.start)
		{
			return;
		}
		if(!this.drag_create.event)
		{
			var value = jQuery.extend({},
				this.drag_create.start,
				this.drag_create.end,
				{
					start: this.drag_create.start.date,
					end: this.drag_create.end && this.drag_create.end.date || this.drag_create.start.date,
					date: "" + formatDate(this.date_helper(this.drag_create.start.date), {dateFormat: "Ymd"}),
					title: '',
					description: '',
					owner: this.options.owner,
					participants: this.options.owner,
					app: 'calendar',
					whole_day_on_top: this.drag_create.start.whole_day
				}
			);
			this.drag_create.event = <et2_calendar_event>et2_createWidget('calendar-event',{
				id:'event_drag',
				value: value
			},this.drag_create.parent);
			this.drag_create.event._values_check(value);
			this.drag_create.event.doLoadingFinished();
		}

	}

	_drag_update_event()
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
		this.drag_create.event._update();
		this.drag_create.parent.position_event(this.drag_create.event);
	}

	/**
	 * Ending (mouseup) handler to support drag to create
	 *
	 * @param {String} end Date string (JSON format)
	 */
	_drag_create_end(end?)
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
			let app_calendar = this.getInstanceManager().app_obj.calendar || app.calendar;
			if (this.drag_create.parent && this.drag_create.parent.options.owner !== app_calendar.state.owner && !options.owner)
			{
				options.owner = this.drag_create.parent.options.owner;
			}

			// Remove empties
			for(var key in options)
			{
				if(!options[key])
				{
					delete options[key];
				}
				if(options[key] instanceof Date)
				{
					options[key] = options[key].toJSON();
				}
			}
			app.calendar.add(options, this.drag_create.event);

			// Wait a bit, having these stops the click
			window.setTimeout(jQuery.proxy(function() {
				this.drag_create.start = null;
				this.drag_create.end = null;
				this.drag_create.parent = null;
				if(this.drag_create.event)
				{
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

	/**
	 * Check if the view should be consolidated into one, or listed seperately
	 * based on the user's preferences
	 *
	 * @param {string[]} owners List of owners
	 * @param {string} view Name of current view (day, week)
	 * @returns {boolean} True of only one is needed, false if each owner needs
	 *	to be listed seperately.
	 */
	static is_consolidated(owners, view)
	{
		// Seperate owners, or consolidated?
		return !(
			owners.length > 1 &&
			(view === 'day' && owners.length < parseInt(''+egw.preference('day_consolidate','calendar')) ||
			view === 'week' && owners.length < parseInt(''+egw.preference('week_consolidate','calendar')))
		);
	}

	/**
	 * Cache to map owner & resource IDs to names, helps cut down on server requests
	 */
	static owner_name_cache = {};
}