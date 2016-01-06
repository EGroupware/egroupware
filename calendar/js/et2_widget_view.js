/* 
 * Egroupware
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package 
 * @subpackage 
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

"use strict";

/*egw:uses
	/etemplate/js/et2_core_valueWidget;
*/

/**
 * Parent class for the various calendar views to reduce copied code
 *
 * @augments et2_valueWidget
 */
var et2_calendar_view = et2_valueWidget.extend(
{
	createNamespace: true,
	
	attributes: {
		owner: {
			name: "Owner",
			type: "any", // Integer, or array of integers, or string like r13 (resources, addressbook)
			default: 0,
			description: "Account ID number of the calendar owner, if not the current user"
		},
		start_date: {
			name: "Start date",
			type: "any"
		},
		end_date: {
			name: "End date",
			type: "any"
		},
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

		this.loader = $j('<div class="egw-loading-prompt-container ui-front loading"></div>');
	},

	destroy: function destroy() {
		this._super.apply(this, arguments);

		// date_helper has no parent, so we must explicitly remove it
		this.date_helper.destroy();
		this.date_helper = null;
	},

	doLoadingFinished: function() {
		this._super.apply(this, arguments);
		this.loader.hide(0).prependTo(this.div);
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
	invalidate: function invalidate(trigger_event) {},

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
	 * @param {string|number|Date} new_date New starting date
	 * @returns {undefined}
	 *
	 * @memberOf et2_calendar_view
	 */
	set_start_date: function set_start_date(new_date)
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
	 *
	 * @memberOf et2_calendar_view
	 */
	set_end_date: function set_end_date(new_date)
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
	 * Set which users to display
	 *
	 * @param {number|number[]|string|string[]} _owner Account ID
	 *
	 * @memberOf et2_calendar_view
	 */
	set_owner: function set_owner(_owner)
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
	 * Calendar supports many different owner types, including users & resources.
	 * This translates an ID to a user-friendly name.
	 *
	 * @param {string} user
	 * @returns {string}
	 *
	 * @memberOf et2_calendar_view
	 */
	_get_owner_name: function _get_owner_name(user) {
		if(parseInt(user) === 0)
		{
			// 0 means current user
			user = egw.user('account_id');
		}
		if (isNaN(user)) // resources or contact
		{
			var application = 'home-accounts';
			switch(user[0])
			{
				case 'c':
					application = 'addressbook';
					break;
				case 'r':
					application = 'resources';
					break;
			}
			// This might not have a value right away
			// send an empty function or it won't ask the server
			user = egw.link_title(application,user.match(/\d+/)[0], function() {}, this);
		}
		else	// users
		{
			user = parseInt(user)
			var accounts = egw.accounts('both');
			for(var j = 0; j < accounts.length; j++)
			{
				if(accounts[j].value === user)
				{
					user = accounts[j].label;
					break;
				}
			}
		}
		return user;
	}
});