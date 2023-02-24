/**
 * EGroupware - Calendar - Javascript UI
 *
 * @link https://www.egroupware.org
 * @package calendar
 * @author Hadi Nategh	<hn-AT-egroupware.org>
 * @author Nathan Gray
 * @copyright (c) 2008-21 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

/*egw:uses
	/api/js/jsapi/egw_app.js;
	/etemplate/js/etemplate2.js;
	/calendar/js/View.js;
	/calendar/js/et2_widget_owner.js;
	/calendar/js/et2_widget_timegrid.js;
	/calendar/js/et2_widget_planner.js;
	/vendor/bower-asset/jquery-touchswipe/jquery.touchSwipe.js;
*/

import {EgwApp, PushData} from "../../api/js/jsapi/egw_app";
import {etemplate2} from "../../api/js/etemplate/etemplate2";
import {et2_container} from "../../api/js/etemplate/et2_core_baseWidget";
import {et2_date} from "../../api/js/etemplate/et2_widget_date";
import {day, day4, listview, month, planner, week, weekN} from "./View";
import {et2_calendar_view} from "./et2_widget_view";
import {et2_calendar_timegrid} from "./et2_widget_timegrid";
import {et2_calendar_daycol} from "./et2_widget_daycol";
import {et2_calendar_planner} from "./et2_widget_planner";
import {et2_calendar_planner_row} from "./et2_widget_planner_row";
import {et2_calendar_event} from "./et2_widget_event";
import {Et2Dialog} from "../../api/js/etemplate/Et2Dialog/Et2Dialog";
import {et2_valueWidget} from "../../api/js/etemplate/et2_core_valueWidget";
import {et2_button} from "../../api/js/etemplate/et2_widget_button";
import {et2_selectbox} from "../../api/js/etemplate/et2_widget_selectbox";
import {et2_widget} from "../../api/js/etemplate/et2_core_widget";
import {et2_nextmatch} from "../../api/js/etemplate/et2_extension_nextmatch";
import {et2_iframe} from "../../api/js/etemplate/et2_widget_iframe";
// @ts-ignore
import {date} from "../../api/js/etemplate/lib/date.js";
import {sprintf} from "../../api/js/egw_action/egw_action_common.js";
import {egw_registerGlobalShortcut, egw_unregisterGlobalShortcut} from "../../api/js/egw_action/egw_keymanager.js";
import {egw, egw_getFramework} from "../../api/js/jsapi/egw_global";
import {et2_number} from "../../api/js/etemplate/et2_widget_number";
import {et2_template} from "../../api/js/etemplate/et2_widget_template";
import {et2_checkbox} from "../../api/js/etemplate/et2_widget_checkbox";
import {et2_grid} from "../../api/js/etemplate/et2_widget_grid";
import {Et2Textbox} from "../../api/js/etemplate/Et2Textbox/Et2Textbox";
import "./SidemenuDate";
import {formatDate, formatTime, parseDate} from "../../api/js/etemplate/Et2Date/Et2Date";
import {EGW_KEY_PAGE_DOWN, EGW_KEY_PAGE_UP} from "../../api/js/egw_action/egw_action_constants";
import {nm_action} from "../../api/js/etemplate/et2_extension_nextmatch_actions";
import flatpickr from "flatpickr";
import Sortable from 'sortablejs/modular/sortable.complete.esm.js';
import {tapAndSwipe} from "../../api/js/tapandswipe";
import {CalendarOwner} from "./CalendarOwner";
import {et2_IInput} from "../../api/js/etemplate/et2_core_interfaces";
import {Et2DateTime} from "../../api/js/etemplate/Et2Date/Et2DateTime";

/**
 * UI for calendar
 *
 * Calendar has multiple different views of the same data.  All the templates
 * for the different view are loaded at the start, then the view objects
 * in CalendarApp.views are used to manage the different views.
 * update_state() is used to change the state between the different views, as
 * well as adjust only a single part of the state while keeping the rest unchanged.
 *
 * The event widgets (and the nextmatch) get the data from egw.data, and they
 * register update callbacks to automatically update when the data changes.  This
 * means that when we update something on the server, to update the UI we just need
 * to send back the new data and if the event widget still exists it will update
 * itself.  See calendar_uiforms->ajax_status().
 *
 * To reduce server calls, we also keep a map of day => event IDs.  This allows
 * us to quickly change views (week to day, for example) without requesting additional
 * data from the server.  We keep that map as long as only the date (and a few
 * others - see update_state()) changes.  If user or any of the other filters are
 * changed, we discard the daywise cache and ask the server for the filtered events.
 *
 */
export class CalendarApp extends EgwApp
{

	/**
	 * Needed for JSON callback
	 */
	public readonly prefix = 'calendar';

	/**
	 * etemplate for the sidebox filters
	 */
	sidebox_et2: et2_container = null;

	/**
	 * Current internal state
	 *
	 * If you need to change state, you can pass just the fields to change to
	 * update_state().
	 */
	state = {
		date: new Date(),
		view: egw.preference('saved_states','calendar') ?
			// @ts-ignore
			egw.preference('saved_states','calendar').view :
			egw.preference('defaultcalendar','calendar') || 'day',
		owner: egw.user('account_id'),
		keywords: '',
		last: undefined,
		first: undefined,
		include_videocalls: false
	};

	/**
	 * These are the keys we keep to set & remember the status, others are discarded
	 */
	static readonly states_to_save = ['owner','status_filter','filter','cat_id','view','sortby','planner_view','weekend'];

	// If you are in one of these views and select a date in the sidebox, the view
	// will change as needed to show the date.  Other views will only change the
	// date in the current view.
	public readonly sidebox_changes_views = ['day','week','month'];

	static views = {
		day: day,
		day4: day4,
		week: week,
		weekN: weekN,
		month: month,
		planner: planner,
		listview: listview
	};

	/**
	 * This is the data cache prefix for the daywise event index cache
	 * Daywise cache IDs look like: calendar_daywise::20150101 and
	 * contain a list of event IDs for that day (or empty array)
	 */
	public static readonly DAYWISE_CACHE_ID = 'calendar_daywise';

	// Calendar allows other apps to hook into the sidebox.  We keep these etemplates
	// up to date as state is changed.
	sidebox_hooked_templates = [];

	// List of queries in progress, to prevent home from requesting the same thing
	_queries_in_progress = [];

	// Calendar-wide autorefresh
	_autorefresh_timer : number = null;

	// Flag if the state is being updated
	private state_update_in_progress: boolean = false;

	// Data for quick add dialog
	private quick_add: any;

	//
	private _grants : any;
	/**
	 * Constructor
	 *
	 */
	constructor()
	{
		/*
		// categories have nothing to do with calendar, but eT2 objects loads calendars app.js
		if (window.framework && framework.applications.calendar.browser &&
			framework.applications.calendar.browser.currentLocation.match('menuaction=preferences\.preferences_categories_ui\.index'))
		{
			this._super.apply(this, arguments);
			return;
		}
		else// make calendar object available, even if not running in top window, as sidebox does
		if (window.top !== window && !egw(window).is_popup() && window.top.app.calendar)
		{
			window.app.calendar = window.top.app.calendar;
			return;
		}
		else if (window.top == window && !egw(window).is_popup())
		{
			// Show loading div
			egw.loading_prompt(
				this.appname,true,egw.lang('please wait...'),
				typeof framework !== 'undefined' ? framework.applications.calendar.tab.contentDiv : false,
				egwIsMobile()?'horizental':'spinner'
			);
		}
	*/
		// call parent
		super('calendar');

		// Scroll
		jQuery(jQuery.proxy(this._scroll, this));
		jQuery.extend(this.state, this.egw.preference('saved_states', 'calendar'));

		// Set custom color for events without category
		if(this.egw.preference('no_category_custom_color', 'calendar'))
		{
			this.egw.css(
				'.calendar_calEvent:not([class*="cat_"])',
				'background-color: '+this.egw.preference('no_category_custom_color','calendar')+' !important'
			);
		}
	}

	/**
	 * Destructor
	 */
	destroy()
	{
		// call parent
		super.destroy(this.appname);

		// remove top window reference
		// @ts-ignore
		if (window.top !== window && window.top.app.calendar === this)
		{
			// @ts-ignore
			delete window.top.app.calendar;
		}
		jQuery('body').off('.calendar');

		if(this.sidebox_et2)
		{
			var date = <et2_date>this.sidebox_et2.getWidgetById('date');
			jQuery(window).off('resize.calendar'+date.dom_id);
		}
		this.sidebox_hooked_templates = [];

		egw_unregisterGlobalShortcut(EGW_KEY_PAGE_UP, false, false, false);
		egw_unregisterGlobalShortcut(EGW_KEY_PAGE_DOWN, false, false, false);

		// Stop autorefresh
		if(this._autorefresh_timer)
		{
			window.clearInterval(this._autorefresh_timer);
			this._autorefresh_timer = null;
		}
	}

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().
	 *
	 * @param {etemplate2} _et2 newly ready et2 object
	 * @param {string} _name name of template
	 */
	et2_ready(_et2 : etemplate2, _name)
	{
		// call parent
		super.et2_ready(_et2, _name);

		// Avoid many problems with home
		if(_et2.app !== 'calendar' || _name == 'admin.categories.index')
		{
			egw.loading_prompt(this.appname,false);
			return;
		}

		// Re-init sidebox, since it was probably initialized too soon
		var sidebox = jQuery('#favorite_sidebox_'+this.appname);
		if(sidebox.length == 0 && egw_getFramework() != null)
		{
			// Force rollup to load owner widget, it leaves it out otherwise
			new CalendarOwner();
			// Force rollup to load planner widget, it leaves it out otherwise
			new et2_calendar_planner(_et2.widgetContainer, {});

			var egw_fw = egw_getFramework();
			sidebox = jQuery('#favorite_sidebox_' + this.appname, egw_fw.sidemenuDiv);
		}

		var content = this.et2.getArrayMgr('content');

		switch (_name)
		{
			case 'calendar.sidebox':
				this.sidebox_et2 = _et2.widgetContainer;
				this.sidebox_hooked_templates.push(this.sidebox_et2);
				jQuery(_et2.DOMContainer).hide();

				this._setup_sidebox_filters();

				this.state = content.data;
				if(typeof this.state.date == "string" && this.state.date.length == 8)
				{
					this.state.date = parseDate(this.state.date, "Ymd");
				}
				break;

			case 'calendar.add':
				this.et2.getWidgetById('title').focus();
			// Fall through to get all the edit stuff too
			case 'calendar.edit':
				if (typeof content.data['conflicts'] == 'undefined')
				{
					//Check if it's fallback from conflict window or it's from edit window
					if (content.data['button_was'] != 'freetime')
					{
						this.set_enddate_visibility();
						this.check_recur_type();
						this.edit_start_change(null, this.et2.getWidgetById('start'));
						if(this.et2.getWidgetById('recur_exception'))
						{
							this.et2.getWidgetById('recur_exception').set_disabled(!content.data.recur_exception ||
								typeof content.data.recur_exception[0] == 'undefined');
						}
					}
					else
					{
						this.freetime_search();
					}
					//send Syncronus ajax request to the server to unlock the on close entry
					//set onbeforeunload with json request to send request when the window gets close by X button
					if (content.data.lock_token)
					{
						window.addEventListener("beforeunload", this._unlock.bind(this));
					}
				}
				this.alarm_custom_date();

				// If title is pre-filled for a new (no ID) event, highlight it
				if(content.data && !content.data.id && content.data.title)
				{
					(<Et2Textbox><unknown>this.et2.getWidgetById('title')).focus();
				}

				// Disable loading prompt (if loaded nopopup)
				egw.loading_prompt(this.appname,false);
				break;

			case 'calendar.freetimesearch':
				this.set_enddate_visibility();
				break;
			case 'calendar.list':
				// Wait until _et2_view_init is done
				window.setTimeout(jQuery.proxy(function() {
					this.filter_change();
				},this),0);
				break;
			case 'calendar.category_report':
				this.category_report_init();
				break;
		}

		// Record the templates for the views so we can switch between them
		this._et2_view_init(_et2,_name);
	}

	/**
	 * Observer method receives update notifications from all applications
	 *
	 * App is responsible for only reacting to "messages" it is interested in!
	 *
	 * Calendar binds listeners to the data cache, so if the data is updated, the widget
	 * will automatically update itself.
	 *
	 * @param {string} _msg message (already translated) to show, eg. 'Entry deleted'
	 * @param {string} _app application name
	 * @param {(string|number)} _id id of entry to refresh or null
	 * @param {string} _type either 'update', 'edit', 'delete', 'add' or null
	 * - update: request just modified data from given rows.  Sorting is not considered,
	 *		so if the sort field is changed, the row will not be moved.
	 * - edit: rows changed, but sorting may be affected.  Requires full reload.
	 * - delete: just delete the given rows clientside (no server interaction neccessary)
	 * - add: requires full reload for proper sorting
	 * @param {string} _msg_type 'error', 'warning' or 'success' (default)
	 * @param {object|null} _links app => array of ids of linked entries
	 * or null, if not triggered on server-side, which adds that info
	 * @return {false|*} false to stop regular refresh, thought all observers are run
	 */
	observer(_msg, _app, _id, _type, _msg_type, _links)
	{
		var do_refresh = false;
		if(this.state.view === 'listview')
		{
			// @ts-ignore
			CalendarApp.views.listview.etemplates[0].widgetContainer.getWidgetById('nm').refresh(_id,_type);
		}
		switch(_app)
		{
			case 'infolog':
				jQuery('.calendar_calDayTodos')
					.find('a')
					.each(function(i,a : HTMLAnchorElement){
						var match = a.href.split(/&info_id=/);
						if (match && typeof match[1] !="undefined")
						{
							if (match[1]== _id)	do_refresh = true;
						}
					});

				// Unfortunately we do not know what type this infolog is here,
				// but we can tell if it's turned off entirely
				if(egw.preference('calendar_integration','infolog') !== '0')
				{
					if (jQuery('div [data-app="infolog"][data-app_id="'+_id+'"]').length > 0) do_refresh = true;
					switch (_type)
					{
						case 'add':
							do_refresh = true;
							break;
					}
				}
				if (do_refresh)
				{
					// Discard cache
					this._clear_cache();

					// Calendar is the current application, refresh now
					if(framework.activeApp.appName === this.appname)
					{
						this.setState({state: this.state});
					}
					// Bind once to trigger a refresh when tab is activated again
					else if(framework.applications.calendar && framework.applications.calendar.tab &&
						framework.applications.calendar.tab.contentDiv)
					{
						jQuery(framework.applications.calendar.tab.contentDiv)
							.off('show.calendar')
							.one('show.calendar',
								jQuery.proxy(function() {this.setState({state: this.state});},this)
							);
					}
				}
				break;
			case 'calendar':
				// Regular refresh
				let event = null;
				if(_id)
				{
					event = egw.dataGetUIDdata('calendar::'+_id);
				}
				if(event && event.data && event.data.date || _type === 'delete')
				{
					// Intelligent refresh without reloading everything
					var recurrences = Object.keys(egw.dataSearchUIDs(new RegExp('^calendar::'+_id+':')));
					var ids = event && event.data && event.data.recur_type && typeof _id === 'string' && _id.indexOf(':') < 0 || recurrences.length ?
						recurrences :
						['calendar::'+_id];

					if(_type === 'delete')
					{
						for(var i in ids)
						{
							egw.dataStoreUID(ids[i], null);
						}
					}
					// Updates are handled by events themselves through egw.data
					else if (_type !== 'update')
					{
						this._update_events(this.state, ids);
					}
					return false;
				}
				else
				{
					this._clear_cache();

					// Force redraw to current state
					this.setState({state: this.state});
					return false;
				}
				break;
			default:
				return undefined;
		}
	}
	/**
	 * Handle a push notification about entry changes from the websocket
	 *
	 * @param  pushData
	 * @param {string} pushData.app application name
	 * @param {(string|number)} pushData.id id of entry to refresh or null
	 * @param {string} pushData.type either 'update', 'edit', 'delete', 'add' or null
	 * - update: request just modified data from given rows.  Sorting is not considered,
	 *		so if the sort field is changed, the row will not be moved.
	 * - edit: rows changed, but sorting may be affected.  Requires full reload.
	 * - delete: just delete the given rows clientside (no server interaction neccessary)
	 * - add: ask server for data, add in intelligently
	 * @param {object|null} pushData.acl Extra data for determining relevance.  eg: owner or responsible to decide if update is necessary
	 * @param {number} pushData.account_id User that caused the notification
	 */
	push(pushData)
	{
		switch (pushData.app)
		{
			case "calendar":
				if(pushData.type === 'delete')
				{
					return super.push(pushData);
				}
				return this.push_calendar(pushData);
			default:
				if(jQuery.extend([],egw.preference("integration_toggle","calendar")).indexOf(pushData.app) >= 0)
				{
					if(pushData.app == "infolog")
					{
						return this.push_infolog(pushData);
					}
					else
					{
						// Modify the pushData so it looks like one of ours
						let integrated_pushData = jQuery.extend(pushData, {
							id: pushData.app+pushData.id,
							app: this.appname
						});
						if(integrated_pushData.type == "delete" || egw.dataHasUID(this.uid(integrated_pushData)))
						{
							// Super always looks at this.et2, make sure it finds listview
							let old_et2 = this.et2;
							this.et2 = (<etemplate2> CalendarApp.views.listview.etemplates[0]).widgetContainer;
							super.push(integrated_pushData);
							this.et2 = old_et2;
						}
						// Ask for the real data, we don't have it.  This also updates views that don't use nextmatch.
						this._fetch_data(this.state,undefined,0,[integrated_pushData.id]);
					}
				}
		}
	}

	/**
	 * Handle a push about infolog
	 *
	 * @param pushData
	 */
	private push_infolog(pushData : PushData)
	{
		// Check if we have access
		if(!this._push_grant_check(pushData, ["info_owner","info_responsible"],"infolog"))
		{
			return;
		}

		// check visibility - grants is ID => permission of people we're allowed to see
		let infolog_grants = egw.grants(pushData.app);

		// Filter what's allowed down to those we care about
		let filtered = Object.keys(infolog_grants).filter(account => this.state.owner.indexOf(account) >= 0);

		let owner_check = filtered.filter(function(value) {
			return pushData.acl.info_owner == value || pushData.acl.info_responsible.indexOf(value) >= 0;
		})
		if(!owner_check || owner_check.length == 0)
		{
			// The owner is not in the list of what we care about
			return;
		}

		// Only need to update the list if we're on that view
		let update_list = this.state.view == "day";

		// Delete, just pull it out of the list
		if(update_list && pushData.type == "delete")
		{
			jQuery('.calendar_calDayTodos')
				.find('a')
				.each(function (i, a: HTMLAnchorElement)
			      {
				      var match = a.href.split(/&info_id=/);
				      if(match && typeof match[1] != "undefined" && match[1] == pushData.id)
				      {
	                    jQuery(a).parentsUntil("tbody").remove();
				      }
			      }
				);
		}

		// Refresh todos if we're there - add, update or edit doesn't matter
		if(update_list)
		{
			this.egw.jsonq('calendar_uiviews::ajax_get_todos', [this.state.date, this.state.owner[0]], function (data)
			{
				this.getWidgetById('label').set_value(data.label || '');
				this.getWidgetById('todos').set_value({content: data.todos || ''});
			}, (<etemplate2>CalendarApp.views.day.etemplates[1]).widgetContainer);
		}
		else
		{
			// Only care about certain infolog types, or already loaded (type may have changed)
			let types = (<string>egw.preference('calendar_integration', 'infolog'))?.split(",") || [];
			let info_uid = this.appname + "::" + pushData.app + pushData.id;
			if(types.indexOf(pushData.acl.info_type) >= 0 || this.egw.dataHasUID(info_uid))
			{
				if(pushData.type === 'delete')
				{
					return this.egw.dataStoreUID(info_uid, null);
				}

				// Calendar is the current application, refresh now
				if(framework.activeApp.appName === this.appname)
				{
					this._fetch_data(this.state,undefined,0,[pushData.app+pushData.id]);
				}
				// Bind once to trigger a refresh when tab is activated again
				else if(framework.applications.calendar && framework.applications.calendar.tab &&
					framework.applications.calendar.tab.contentDiv)
				{
					jQuery(framework.applications.calendar.tab.contentDiv)
						.off('show.calendar')
						.one('show.calendar',
						    function ()
							{
							  this.setState({state: this.state});
							}.bind(this)
						);
				}
			}
		}

	}

	/**
	 * Handle a push from calendar
	 *
	 * @param pushData
	 */
	private push_calendar(pushData)
	{
		// pushData does not contain everything, just the minimum.  See calendar_hooks::search_link().
		let cal_event = pushData.acl || {};

		// check visibility - grants is ID => permission of people we're allowed to see
		let owners = [];
		if(typeof this._grants === 'undefined')
		{
			this._grants = egw.grants(this.appname);
		}
		// Filter what's allowed down to those we care about
		let filtered = Object.keys(this._grants).filter(account => this.state.owner.indexOf(account) >= 0);

		// Check if we're interested in displaying by owner / participant
		let owner_check = et2_calendar_event.owner_check(
			cal_event,
			// Fake the required widget since we don't actually have it right now
			jQuery.extend({},
				{options: {owner: filtered}},
				this.et2
			)
		);
		if(!owner_check)
		{
			// The owner is not in the list of what we're allowed / care about
			return;
		}

		// Check if we're interested by date?
		if(cal_event.end <= new Date(this.state.first).valueOf() /1000 || cal_event.start > new Date(this.state.last).valueOf()/1000)
		{
			// The event is outside our current view, but check if it's just one of a recurring event
			if(!cal_event.range_end && !cal_event.range_start ||
				cal_event.range_end <= new Date(this.state.first).valueOf() / 1000 ||
				cal_event.range_start > new Date(this.state.last).valueOf() / 1000)
			{
				return;
			}
		}

		// Do we already have "fresh" data?  Most user actions give fresh data in response
		let existing = egw.dataGetUIDdata('calendar::' + pushData.id);
		if(existing && Math.abs(existing.timestamp - new Date().valueOf()) < 1000)
		{
			// Update directly
			this._update_events(this.state, ['calendar::' + pushData.id]);
			return;
		}

		// Ask for the real data, we don't have it
		let process_data = (data) =>
		{
			// Store it, which will call all registered listeners
			egw.dataStoreUID(data.uid, data.data);

			// Any existing events were updated.  Run this to catch new events or events moved into view
			this._update_events(this.state, [data.uid]);
		}
		egw.jsonq("calendar.calendar_ui.ajax_get", [[pushData.id]]).then((data) =>
		{
			if(typeof data.uid !== "undefined")
			{
				return process_data(data)
			}
			for(let e of data)
			{
				process_data(e);
			}
		});
	}

	/**
	 * Link hander for jDots template to just reload our iframe, instead of reloading whole admin app
	 *
	 * @param {String} _url
	 * @return {boolean|string} true, if linkHandler took care of link, false for default processing or url to navigate to
	 */
	linkHandler(_url)
	{
		if (_url == 'about:blank' || _url.match('menuaction=preferences\.preferences_categories_ui\.index'))
		{
			return false;
		}
		if (_url.match('menuaction=calendar\.calendar_uiviews\.'))
		{
			var view = _url.match(/calendar_uiviews\.([^&?]+)/);
			view = view && view.length > 1 ? view[1] : null;

			// Get query
			var q : any = {};
			_url.split('?')[1].split('&').forEach(function(i){
				q[i.split('=')[0]]=unescape(i.split('=')[1]);
			});
			delete q.ajax;
			delete q.menuaction;
			if(!view && q.view || q.view != view && view == 'index') view = q.view;

			// No specific view requested, looks like a reload from framework
			if(this.sidebox_et2 && typeof view === 'undefined')
			{
				this._clear_cache();
				this.setState({state: this.state});
				return false;
			}

			if (this.sidebox_et2 && typeof CalendarApp.views[view] == 'undefined' && view != 'index')
			{
				if(q.owner)
				{
					q.owner = q.owner.split(',');
					q.owner = q.owner.reduce(function(p,c) {if(p.indexOf(c)<0) p.push(c);return p;},[]);
					q.owner = q.owner.join(',');
				}
				q.menuaction = 'calendar.calendar_uiviews.index';
				(<et2_iframe><unknown>this.sidebox_et2.getWidgetById('iframe')).set_src(egw.link('/index.php',q));
				jQuery(this.sidebox_et2.parentNode).show();
				return true;
			}
			// Known AJAX view
			else if(CalendarApp.views[view])
			{
				// Reload of known view?
				if(view == 'index')
				{
					var pref = <any>this.egw.preference('saved_states','calendar');
					view = pref.view || 'day';
				}
				// View etemplate not loaded
				if(typeof CalendarApp.views[view].etemplates[0] == 'string')
				{
					return _url + '&ajax=true';
				}
				// Already loaded, we'll just apply any variables to our current state
				var set = jQuery.extend({view: view},q);
				this.update_state(set);
				return true;
			}
		}
		else if (this.sidebox_et2)
		{
			var iframe = <et2_iframe><unknown>this.sidebox_et2.getWidgetById('iframe');
			if(!iframe)
			{
				return false;
			}
			iframe.set_src(_url);
			jQuery(this.sidebox_et2.parentNode).show();
			// Hide other views
			for(var _view in CalendarApp.views)
			{
				for(var i = 0; i < CalendarApp.views[_view].etemplates.length; i++)
				{
					jQuery(CalendarApp.views[_view].etemplates[i].DOMContainer).hide();
				}
			}
			this.state.view = '';
			return true;
		}
		// can not load our own index page, has to be done by framework
		return false;
	}

	/**
	 * Handle actions from the toolbar
	 *
	 * @param {egwAction} action Action from the toolbar
	 */
	toolbar_action(action)
	{
		// Most can just provide state change data
		if(action.data && action.data.state)
		{
			var state = jQuery.extend({},action.data.state);
			if(state.view == 'planner' && app.calendar.state.view != 'planner') {
				state.planner_view = app.calendar.state.view;
			}
			this.update_state(state);
		}
		// Special handling
		switch(action.id)
		{
			case 'add':
				// Default date/time to start of next hour
				var tempDate = new Date();
				if(tempDate.getMinutes() !== 0)
				{
					tempDate.setHours(tempDate.getHours()+1);
					tempDate.setMinutes(0);
				}
				var today = new Date(tempDate.getFullYear(), tempDate.getMonth(), tempDate.getDate(),tempDate.getHours(),-tempDate.getTimezoneOffset(),0);
				return egw.open(null,"calendar","add", {start: today});
			case 'weekend':
				this.update_state({weekend: action.checked});
				break;
			case 'today':
				var tempDate = new Date();
				var today = new Date(tempDate.getFullYear(), tempDate.getMonth(), tempDate.getDate(),0,-tempDate.getTimezoneOffset(),0);
				var change = {date: today.toJSON()};
				app.calendar.update_state(change);
				break;
			case 'next':
			case 'previous':
				var delta = action.id == 'previous' ? -1 : 1;
				var view = CalendarApp.views[this.state.view] || false;
				var start = new Date(this.state.date);
				if (view)
				{
					start = view.scroll(delta);
					app.calendar.update_state({date:this.date.toString(start)});
				}
				break;
		}
	}

	/**
	 * Handle the video call toggle from the toolbar
	 *
	 * @param action
	 */
	toolbar_videocall_toggle_action(action : egwAction)
	{
		let videocall_category = egw.config("status_cat_videocall","status");
		let callback = function() {
			this.update_state({include_videocalls: action.checked});
		}.bind(this);
		this.toolbar_integration_action(action, [],null,callback);
	}

	/**
	 * Handle integration actions from the toolbar
	 *
	 * @param action {egwAction} Integration action from the toolbar
	 */
	toolbar_integration_action(action : egwAction, selected: egwActionObject[], target: egwActionObject, callback? : CallableFunction)
	{
		let app = action.id.replace("integration_","");
		let integration_preference = <string[]> egw.preference("integration_toggle","calendar");
		if(typeof integration_preference === "undefined")
		{
			integration_preference = [];
		}
		if(typeof integration_preference == "string")
		{
			integration_preference = integration_preference.split(",");
		}
		// Make sure it's an array, not an object
		integration_preference = jQuery.extend([],integration_preference);

		if(action.checked)
		{
			if(integration_preference.indexOf(app) === -1)
			{
				integration_preference.push(app);
			}

			// After the preference change is done, get new info which should now include the app
			callback = callback ? callback : function()
			{
				this._fetch_data(this.state);
			}.bind(this);
		}
		else
		{
			const index = integration_preference.indexOf(app);
			if (index > -1) {
				integration_preference.splice(index, 1);
			}

			// Clear any events from that app
			this._clear_cache(app);
		}
		if(action.id == "integration_projectmanager" &&
			this.egw.preference("calendar_integration","projectmanager").indexOf("#") == 0)
		{
			if(!action.checked)
			{
				// Still need to re-fetch in this case, clearing won't bring the others back
				callback = function()
				{
					this._fetch_data(this.state);
				}.bind(this);
			}
			else
			{
				// Clear all events, we're filtering real events now
				callback = function()
				{
					this._clear_cache();

					// Force redraw to current state
					this.setState({state: this.state});
				}.bind(this);
			}
		}
		if(typeof callback === "undefined")
		{
			callback = function() {};
		}

		egw.set_preference("calendar","integration_toggle",integration_preference, callback);
	}

	/**
	 * Set the app header
	 *
	 * Because the toolbar takes some vertical space and has some horizontal space,
	 * we don't use the system app header, but our own that is in the toolbar
	 *
	 * @param {string} header Text to display
	 */
	set_app_header( header)
	{
		var template = etemplate2.getById('calendar-toolbar');
		var widget = template ? template.widgetContainer.getWidgetById('app_header') : false;
		if(widget)
		{
			widget.set_value(header);
			egw_app_header('','calendar');
		}
		else
		{
			egw_app_header(header,'calendar');
		}
	}

	/**
	 * Setup and handle sortable calendars.
	 *
	 * You can only sort calendars if there is more than one owner, and the calendars
	 * are not combined (many owners, multi-week or month views)
	 * @returns {undefined}
	 */
	_sortable( )
	{
		// Calender current state
		let state = this.getState();
		// Day / month sortables
		let daily = document.querySelector('#calendar-view_view .calendar_calGridHeader > div');
		let weekly = document.querySelector('#calendar-view_view tbody');
		let sortable = state.view == 'day' ? daily : weekly;

		let sortablejs = Sortable.create(sortable, {
			ghostClass: 'srotable_cal_wk_ph',
			draggable: state.view == 'day'? '.calendar_calDayColHeader' : '.view_row',
			handle: state.view == 'day'? '.calendar_calToday' : '.calendar_calGridHeader',
			animation: 100,
			filter: state.view == 'day'? '.calendar_calTimeGridScroll' : '.calendar_calDayColHeader',
			dataIdAttr: 'data-owner',
			direction: state.view == 'day'? 'horizental' : 'vertical',
			sort: state.owner.length > 1 && (
				state.view == 'day' && state.owner.length < parseInt(''+egw.preference('day_consolidate','calendar')) ||
				(state.view == 'week' || state.view == 'day4') && state.owner.length < parseInt(''+egw.preference('week_consolidate','calendar'))), // enable/disable sort
			onStart: function (event)
			{
				// Put owners into row IDs
				CalendarApp.views[app.calendar.state.view].etemplates[0].widgetContainer.iterateOver(function(widget) {
					if(widget.options.owner && !widget.disabled)
					{
						widget.div.parents('tr').attr('data-owner',widget.options.owner);
					}
					else
					{
						widget.div.parents('tr').removeAttr('data-owner');
					}
				},this,et2_calendar_timegrid);
			},
			onSort: function(event)
			{
				let state = app.calendar.getState();
				if (state && typeof state.owner !== 'undefined')
				{
					let sortedArr = sortablejs.toArray();
					// No duplicates, no empties
					sortedArr = sortedArr.filter(function(value, index, self) {
						return value !== '' && self.indexOf(value) === index && !isNaN(value);
					});

					let parent = null;
					let children = [];
					if(state.view == 'day')
					{
						// If in day view, the days need to be re-ordered, avoiding
						// the current sort order
						CalendarApp.views.day.etemplates[0].widgetContainer.iterateOver(function(widget) {
							let idx = sortedArr.indexOf(widget.options.owner.toString());
							// Move the event holding div
							widget.set_left((parseInt(widget.options.width) * idx) + 'px');
							// Re-order the children, or it won't stay
							parent = widget._parent;
							children.splice(idx,0,widget);
						},this,et2_calendar_daycol);
						parent.day_widgets.sort(function(a,b) {
							return children.indexOf(a) - children.indexOf(b);
						});
					}
					else
					{
						// Re-order the children, or it won't stay
						CalendarApp.views.day.etemplates[0].widgetContainer.iterateOver(function(widget) {
							parent = widget._parent;
							let idx = sortedArr.indexOf(widget.options.owner);
							children.splice(idx,0,widget);
							widget.resize();
						},this,et2_calendar_timegrid);
					}
					parent._children.sort(function(a,b) {
						return children.indexOf(a) - children.indexOf(b);
					});
					// Directly update, since there is no other changes needed,
					// and we don't want the current sort order applied
					app.calendar.state.owner = sortedArr;
					parent.options.owner = sortedArr;
				}
			}
		});
	}

	/**
	 * Unlock the event before closing the popup
	 *
	 * @private
	 */
	private _unlock () {
		const content = this.et2.getArrayMgr('content');
		this.egw.json('calendar.calendar_uiforms.ajax_unlock',
		              [content.data.id, content.data.lock_token],null,this,"keepalive",null).sendRequest();
	}

	/**
	 * Bind scroll event
	 * When the user scrolls, we'll move enddate - startdate days
	 */
	_scroll( )
	{
		/**
		 * Function we can pass all this off to
		 *
		 * @param {String} direction up, down, left or right
		 * @param {number} delta Integer for how many we're moving, should be +/- 1
		 */
		var scroll_animate = function(direction, delta)
		{
			// Scrolling too fast?
			if(app.calendar._scroll_disabled) return;

			// Find the template
			var id = jQuery(this).closest('.et2_container').attr('id');
			if(id)
			{
				var template = etemplate2.getById(id);
			}
			else
			{
				template = CalendarApp.views[app.calendar.state.view].etemplates[0];
			}
			if(!template) return;

			// Prevent scrolling too fast
			app.calendar._scroll_disabled = true;

			// Animate the transition, if possible
			var widget = null;
			template.widgetContainer.iterateOver(function(w) {
				if (w.getDOMNode() == this) widget = w;
			},this,et2_widget);
			if(widget == null)
			{
				template.widgetContainer.iterateOver(function(w) {
					widget = w;
				},this, et2_calendar_timegrid);
				if(widget == null) return;
			}
			/* Disabled
			 *
			// We clone the nodes so we can animate the transition
			var original = jQuery(widget.getDOMNode()).closest('.et2_grid');
			var cloned = original.clone(true).attr("id","CLONE");

			// Moving this stuff around scrolls things around too
			// We need this later
			var scrollTop = jQuery('.calendar_calTimeGridScroll',original).scrollTop();

			// This is to hide the scrollbar
			var wrapper = original.parent();
			if(direction == "right" || direction == "left")
			{
				original.css({"display":"inline-block","width":original.width()+"px"});
				cloned.css({"display":"inline-block","width":original.width()+"px"});
			}
			else
			{
				original.css("height",original.height() + "px");
				cloned.css("height",original.height() + "px");
			}
			var original_size = {height: wrapper.parent().css('height'), width: wrapper.parent().css('width')};
			wrapper.parent().css({overflow:'hidden', height:original.outerHeight()+"px", width:original.outerWidth() + "px"});
			wrapper.height(direction == "up" || direction == "down" ? 2 * original.outerHeight()  : original.outerHeight());
			wrapper.width(direction == "left" || direction == "right" ? 2 * original.outerWidth() : original.outerWidth());

			// Re-scroll to previous to avoid "jumping"
			jQuery('.calendar_calTimeGridScroll',original).scrollTop(scrollTop);
			switch(direction)
			{
				case "up":
				case "left":
					// Scrolling up
					// Apply the reverse quickly, then let it animate as the changes are
					// removed, leaving things where they should be.

					original.parent().append(cloned);
					// Makes it jump to destination
					wrapper.css({
						"transition-duration": "0s",
						"transition-delay": "0s",
						"transform": direction == "up" ? "translateY(-50%)" : "translateX(-50%)"
					});
					// Stop browser from caching style by forcing reflow
					if(wrapper[0]) wrapper[0].offsetHeight;

					wrapper.css({
						"transition-duration": "",
						"transition-delay": ""
					});
					break;
				case "down":
				case "right":
					// Scrolling down
					original.parent().prepend(cloned);
					break;
			}
			// Scroll clone to match to avoid "jumping"
			jQuery('.calendar_calTimeGridScroll',cloned).scrollTop(scrollTop);

			// Remove
			var remove = function() {
				// Starting animation
				wrapper.addClass("calendar_slide");
				var translate = direction == "down" ? "translateY(-50%)" : (direction == "right" ? "translateX(-50%)" : "");
				wrapper.css({"transform": translate});
				window.setTimeout(function() {

					cloned.remove();

					// Makes it jump to destination
					wrapper.css({
						"transition-duration": "0s",
						"transition-delay": "0s"
					});

					// Clean up from animation
					wrapper
						.removeClass("calendar_slide")
						.css({"transform": '',height: '', width:'',overflow:''});
					wrapper.parent().css({overflow: '', width: original_size.width, height: original_size.height});
					original.css("display","");
					if(wrapper.length)
					{
						wrapper[0].offsetHeight;
					}
					wrapper.css({
						"transition-duration": "",
						"transition-delay": ""
					});

					// Re-scroll to start of day
					template.widgetContainer.iterateOver(function(w) {
						w.resizeTimes();
					},this, et2_calendar_timegrid);

					window.setTimeout(function() {
						if(app.calendar)
						{
							app.calendar._scroll_disabled = false;
						}
					}, 100);
				},2000);
			}
			// If detecting the transition end worked, we wouldn't need to use a timeout.
			window.setTimeout(remove,100);
			*/
		   window.setTimeout(function() {
				if(app.calendar)
				{
					app.calendar._scroll_disabled = false;
				}
			}, 2000);
			// Get the view to calculate - this actually loads the new data
			// Using a timeout make it a little faster (in Chrome)
			window.setTimeout(function() {
				var view = CalendarApp.views[app.calendar.state.view] || false;
				var start = new Date(app.calendar.state.date);
				if (view && view.etemplates.indexOf(template) !== -1)
				{
					start = view.scroll(delta);
					app.calendar.update_state({date:app.calendar.date.toString(start)});
				}
				else
				{
					// Home - always 1 week
					// TODO
					return false;
				}
			},0);
		};

		// Bind only once, to the whole thing
		/* Disabled
		jQuery('body').off('.calendar')
			//.on('wheel','.et2_container:#calendar-list,#calendar-sidebox)',
			.on('wheel.calendar','.et2_container .calendar_calTimeGrid, .et2_container .calendar_plannerWidget',
				function(e)
				{
					// Consume scroll if in the middle of something
					if(app.calendar._scroll_disabled) return false;

					// Ignore if they're going the other way
					var direction = e.originalEvent.deltaY > 0 ? 1 : -1;
					var at_bottom = direction !== -1;
					var at_top = direction !== 1;

					jQuery(this).children(":not(.calendar_calGridHeader)").each(function() {
						// Check for less than 2px from edge, as sometimes we can't scroll anymore, but still have
						// 2px left to go
						at_bottom = at_bottom && Math.abs(this.scrollTop - (this.scrollHeight - this.offsetHeight)) <= 2;
					}).each(function() {
						at_top = at_top && this.scrollTop === 0;
					});
					if(!at_bottom && !at_top) return;

					e.preventDefault();

					scroll_animate.call(this, direction > 0 ? "down" : "up", direction);

					return false;
				}
			);
		*/
		if(typeof framework !== 'undefined' && framework.applications.calendar && framework.applications.calendar.tab)
		{
			let swipe = new tapAndSwipe(framework.applications.calendar.tab.contentDiv, {
					//Generic swipe handler for all directions
					swipe:function(event, direction, distance, fingerCount) {
						if(direction == "up" || direction == "down")
						{
							if(fingerCount <= 1) return;
							let at_bottom = direction !== -1;
							let at_top = direction !== 1;

							jQuery(this.element).children(":not(.calendar_calGridHeader)").each(function() {
								// Check for less than 2px from edge, as sometimes we can't scroll anymore, but still have
								// 2px left to go
								at_bottom = at_bottom && Math.abs(this.scrollTop - (this.scrollHeight - this.offsetHeight)) <= 2;
							}).each(function() {
								at_top = at_top && this.scrollTop === 0;
							});
						}

						let delta = direction == "down" || direction == "right" ? -1 : 1;
						// But we animate in the opposite direction to the swipe
						let opposite = {"down": "up", "up": "down", "left": "right", "right": "left"};
						direction = opposite[direction];
						scroll_animate.call(jQuery(event.target).closest('.calendar_calTimeGrid, .calendar_plannerWidget')[0], direction, delta);
						return false;
					},
					threshold: 100,
					allowScrolling: 'vertical'
				});

			// Page up & page down
			egw_registerGlobalShortcut(EGW_KEY_PAGE_UP, false, false, false, function()
			{
				if(app.calendar.state.view == 'listview')
				{
					return false;
				}
				scroll_animate.call(this, "up", -1);
				return true;
			}, this);
			egw_registerGlobalShortcut(EGW_KEY_PAGE_DOWN, false, false, false, function() {
				if(app.calendar.state.view == 'listview')
				{
					return false;
				}
				scroll_animate.call(this,"down", 1);
				return true;
			}, this);
		}
	}

	/**
	 * Handler for changes generated by internal user interactions, like
	 * drag & drop inside calendar and resize.
	 *
	 * @param {Event} event
	 * @param {et2_calendar_event} widget Widget for the event
	 * @param {string} dialog_button - 'single', 'series', or 'exception', based on the user's answer
	 *	in the popup
	 * @returns {undefined}
	 */
	event_change(event, widget, dialog_button)
	{
		// Add loading spinner - not visible if the body / gradient is there though
		widget.div.addClass('loading');

		// Integrated infolog event
		//Get infologID if in case if it's an integrated infolog event
		if (widget.options.value.app == 'infolog')
		{
			// If it is an integrated infolog event we need to edit infolog entry
			egw().json(
				'stylite_infolog_calendar_integration::ajax_moveInfologEvent',
				[widget.options.value.app_id, widget.options.value.start, widget.options.value.duration],
				// Remove loading spinner
				function() {if(widget.div) widget.div.removeClass('loading');}
			).sendRequest();
		}
		else
		{
			var _send = function() {
				egw().json(
					'calendar.calendar_uiforms.ajax_moveEvent',
					[
						dialog_button == 'exception' ? widget.options.value.app_id : widget.options.value.id,
						widget.options.value.owner,
						widget.options.value.start,
						widget.options.value.owner,
						widget.options.value.duration,
						dialog_button == 'series' ? widget.options.value.start : null
					],
					// Remove loading spinner
					function() {if(widget && widget.div) widget.div.removeClass('loading');}
				).sendRequest(true);
			};
			if(dialog_button == 'series' && widget.options.value.recur_type)
			{
				widget.series_split_prompt(function(_button_id)
					{
						if(_button_id == Et2Dialog.OK_BUTTON)
						{
							_send();
						}
					}
				);
			}
			else
			{
				_send();
			}
		}
	}

	/**
	 * open the freetime search popup
	 *
	 * @param {string} _link
	 */
	freetime_search_popup(_link)
	{
		this.egw.open_link(_link,'ft_search','700x500') ;
	}

	/**
	 * send an ajax request to server to set the freetimesearch window content
	 *
	 */
	freetime_search()
	{
		var content = this.et2.getArrayMgr('content').data;
		content['start'] = this.et2.getValueById('start');
		content['end'] = this.et2.getValueById('end');
		content['duration'] = this.et2.getValueById('duration');

		var request = this.egw.json('calendar.calendar_uiforms.ajax_freetimesearch', [content],null,null,null,null);
		request.sendRequest();
	}

	/**
	 * Function for disabling the recur_data multiselect box
	 *
	 */
	check_recur_type()
	{
		var recurType = <et2_selectbox> this.et2.getWidgetById('recur_type');
		var recurData = <et2_selectbox> this.et2.getWidgetById('recur_data');

		if(recurType && recurData)
		{
			recurData.set_disabled(recurType.get_value() != 2 && recurType.get_value() != 4);
		}
	}

	/**
	 * Actions for when the user changes the event start date in edit dialog
	 *
	 * @returns {undefined}
	 */
	edit_start_change(input, widget)
	{
		if(!widget)
		{
			widget = etemplate2.getById('calendar-edit').widgetContainer.getWidgetById('start');
		}

		// Update settings for querying participants
		this.edit_update_participant(widget);

		// Update recurring date limit, if not set it can't be before start
		if(widget)
		{
			var recur_end = widget.getRoot().getWidgetById('recur_enddate');
			if(recur_end && recur_end.getValue && !recur_end.getValue())
			{
				recur_end.set_min(widget.getValue());
			}

			// Update end date, min duration is 1 minute
			let end = <et2_date>widget.getRoot().getWidgetById('end');
			let start_time = new Date(widget.getValue());
			let end_time = new Date(end.getValue());
			if(end.getValue() && end_time <= start_time)
			{
				start_time.setMinutes(start_time.getMinutes() + 1);
				end.set_value(start_time);
			}
		}
		// Update currently selected alarm time
		this.alarm_custom_date();

	}

	/**
	 * Show/Hide end date, for both edit and freetimesearch popups,
	 * based on if "use end date" selected or not.
	 *
	 */
	set_enddate_visibility()
	{
		let duration = <Et2Select>this.et2.getWidgetById('duration');
		let start = <Et2DateTime>this.et2.getWidgetById('start');
		let end = <Et2DateTime>this.et2.getWidgetById('end');
		let content = this.et2.getArrayMgr('content').data;

		if(duration != null && end != null)
		{
			end.set_disabled(duration.get_value() !== '');
			end.classList.toggle("hideme", end.disabled);

			// Only set end date if not provided, adding seconds fails with DST
			// @ts-ignore
			if (!end.disabled && !content.end)
			{
				end.set_value(start.get_value());
				// @ts-ignore
				if (typeof content.duration != 'undefined') end.set_value("+"+content.duration);
			}
		}
		this.edit_update_participant(start);
	}

	/**
	 * Update query parameters for participants
	 *
	 * This allows for resource conflict checking
	 *
	 * @param {DOMNode|et2_widget} input Either the input node, or the widget
	 * @param {et2_widget} [widget] If input is an input node, widget will have
	 *	the widget, otherwise it will be undefined.
	 */
	edit_update_participant(input, widget?)
	{
		if(typeof widget === 'undefined')
		{
			widget = input;
		}
		var content = widget.getInstanceManager().getValues(widget.getRoot());
		var participant = <CalendarOwner>widget.getRoot().getWidgetById('participant');
		if(!participant)
		{
			return;
		}

		participant.searchOptions = {
			exec: {
				start: content.start,
				end: content.end,
				duration: content.duration,
				whole_day: content.whole_day,
			}
		};
	}

	/**
	 * handles actions selectbox in calendar edit popup
	 *
	 * @param {mixed} _event
	 * @param {et2_base_widget} widget "actions selectBox" in edit popup window
	 */
	actions_change(_event, widget)
	{
		var event = this.et2.getArrayMgr('content').data;
		if (widget)
		{
			var id = this.et2.getArrayMgr('content').data['id'];
			switch (widget.get_value())
			{
				case 'print':
					this.egw.open_link('calendar.calendar_uiforms.edit&cal_id='+id+'&print=1','_blank','700x700');
					break;
				case 'mail':
					this.egw.json('calendar.calendar_uiforms.ajax_custom_mail', [event, !event['id'], false],null,null,null,null).sendRequest();
					this.et2._inst.submit();
					break;
				case 'sendrequest':
					this.egw.json('calendar.calendar_uiforms.ajax_custom_mail', [event, !event['id'], true],null,null,null,null).sendRequest();
					this.et2._inst.submit();
					break;
				case 'infolog':
					this.egw.open_link('infolog.infolog_ui.edit&action=calendar&action_id='+(jQuery.isPlainObject(event)?event['id']:event),'_blank','700x600','infolog');
					this.et2._inst.submit();
					break;
				case 'ical':
					this.et2._inst.postSubmit();
					break;
				default:
					this.et2._inst.submit();
			}
		}
	}

	/**
	 * open mail compose popup window
	 *
	 * @param {Array} vars
	 * @todo need to provide right mail compose from server to custom_mail function
	 */
	custom_mail (vars)
	{
		this.egw.open_link(this.egw.link("/index.php",vars),'_blank','700x700');
	}

	/**
	 * control delete_series popup visibility
	 *
	 * @param {et2_widget} widget
	 * @param {Array} exceptions an array contains number of exception entries
	 *
	 */
	delete_btn(widget,exceptions)
	{
		var content = this.et2.getArrayMgr('content').data;

		if (exceptions)
		{
			var buttons = [
				{
					button_id: 'keep',
					title: this.egw.lang('All exceptions are converted into single events.'),
					label: this.egw.lang('Keep exceptions'),
					id: 'button[delete_keep_exceptions]',
					image: 'keep', "default": true
				},
				{
					button_id: 'delete',
					title: this.egw.lang('The exceptions are deleted together with the series.'),
					label: this.egw.lang('Delete exceptions'),
					id: 'button[delete_exceptions]',
					image: 'delete'
				},
				{
					button_id: 'cancel',
					label: this.egw.lang('Cancel'),
					id: 'dialog[cancel]',
					image: 'cancel'
				}

			];
			var self = this;
			Et2Dialog.show_dialog
			(
					function(_button_id)
					{
						if (_button_id != 'dialog[cancel]')
						{
							widget.getRoot().getWidgetById('delete_exceptions').set_value(_button_id == 'button[delete_exceptions]');
							widget.getInstanceManager().submit('button[delete]');
							return true;
						}
						else
						{
							return false;
						}
					},
				"Do you want to keep the series exceptions in your calendar?",
				"This event is part of a series", {}, buttons, Et2Dialog.WARNING_MESSAGE
			);
		}
		else if (content['recur_type'] !== 0)
		{
			Et2Dialog.confirm(widget, 'Delete this series of recurring events', 'Delete Series');
		}
		else
		{
			Et2Dialog.confirm(widget, 'Delete this event', 'Delete');
		}
	}

	/**
	 * On change participant event, try to set add button status based on
	 * participant field value. Additionally, disable/enable quantity field
	 * if there's none resource value or there are more than one resource selected.
	 *
	 */
	participantOnChange()
	{
		var add = <et2_button>this.et2.getWidgetById('add');
		var quantity = <et2_number>this.et2.getWidgetById('quantity');
		var participant = <Et2CalendarOwner>this.et2.getWidgetById('participant');

		// array of participants
		let value = participant.value;

		add.disabled = (value.length <= 0);

		quantity.set_readonly(false);

		// number of resources
		var nRes = 0;

		for (var i=0;i<value.length;i++)
		{
			if (!value[i].match(/\D/ig) || nRes)
			{
				quantity.set_readonly(true);
				quantity.set_value(1);
			}
			nRes++;
		}
	}

	/**
	 * print_participants_status(egw,widget)
	 * Handle to apply changes from status in print popup
	 *
	 * @param {mixed} _event
	 * @param {et2_base_widget} widget widget "status" in print popup window
	 *
	 */
	print_participants_status(_event, widget)
	{
		if (widget && window.opener)
		{
			//Parent popup window
			var editPopWindow = window.opener;

			if (editPopWindow)
			{
				//Update paretn popup window
				editPopWindow.etemplate2.getByApplication('calendar')[0].widgetContainer.getWidgetById(widget.id).set_value(widget.get_value());
			}
			this.et2._inst.submit();

			editPopWindow.opener.egw_refresh('status changed','calendar');
		}
		else if (widget)
		{
			window.egw_refresh(this.egw.lang('The original popup edit window is closed! You need to close the print window and reopen the entry again.'),'calendar');
		}
	}

	/**
	 * Handles to select freetime, and replace the selected one on Start,
	 * and End date&time in edit calendar entry popup.
	 *
	 * @param {mixed} _event
	 * @param {et2_base_widget} _widget widget "select button" in freetime search popup window
	 *
	 */
	freetime_select(_event, _widget)
	{
		if (_widget)
		{
			var content = this.et2._inst.widgetContainer.getArrayMgr('content').data;
			// Make the Id from selected button by checking the index
			var selectedId = _widget.id.match(/^select\[([0-9])\]$/i)[1];

			var sTime = <et2_date> this.et2.getWidgetById(selectedId+'start');

			//check the parent window is still open before to try to access it
			if (window.opener && sTime)
			{
				var editWindowObj = window.opener.etemplate2.getByApplication('calendar')[0];
				if (typeof editWindowObj != "undefined")
				{
					var startTime = <et2_date> editWindowObj.widgetContainer.getWidgetById('start');
					var endTime = <et2_date> editWindowObj.widgetContainer.getWidgetById('end');
					if (startTime && endTime)
					{
						startTime.set_value(sTime.get_value());
						endTime.set_value(sTime.get_value());
						endTime.set_value('+'+content['duration']);
					}
				}
			}
			else
			{
				alert(this.egw.lang('The original calendar edit popup is closed!'));
			}
		}
		egw(window).close();
	}

	/**
	 * show/hide the filter of nm list in calendar listview
	 *
	 */
	filter_change()
	{
		const view = <et2_container>(<etemplate2> CalendarApp.views['listview'].etemplates[0]).widgetContainer || null;
		const nm = view ? <et2_nextmatch>view.getWidgetById('nm') : null;
		const filter = view && nm ? <et2_selectbox>nm.getWidgetById('filter') : null;
		const dates = view ? <et2_template> view.getWidgetById('calendar.list.dates') : null;

		// Update state when user changes it
		if(view && filter)
		{
			app.calendar.state.filter = filter.getValue();
			// Change sort order for before - this is just the UI, server does the query
			if(app.calendar.state.filter == 'before')
			{
				nm.sortBy('cal_start',false, false);
			}
			else
			{
				nm.sortBy('cal_start',true, false);
			}
		}
		else
		{
			delete app.calendar.state.filter;
		}
		if (filter && dates)
		{
			dates.set_disabled(filter.getValue() !== "custom");
			if (filter.getValue() == "custom" && !this.state_update_in_progress)
			{
				// Copy state dates over, without causing [another] state update
				var actual = this.state_update_in_progress;
				this.state_update_in_progress = true;
				(<et2_date>view.getWidgetById('startdate')).set_value(app.calendar.state.first);
				(<et2_date>view.getWidgetById('enddate')).set_value(app.calendar.state.last);
				this.state_update_in_progress = actual;

				jQuery((<et2_date>view.getWidgetById('startdate')).getDOMNode()).find('input').focus();
			}
		}
	}

	/**
	 * Application links from non-list events
	 *
	 * The ID looks like calendar::<id> or calendar::<id>:<recurrence_date>
	 * For processing the links:
	 *	'$app' gets replaced with 'calendar'
	 *	'$id' gets replaced with <id>
	 *	'$app_id gets replaced with <id>:<recurrence_date>
	 *
	 * Use either $id or $app_id depending on if you want the series [beginning]
	 * or a particular date.
	 *
	 * @param {egwAction} _action
	 * @param {egwActionObject[]} _events
	 */
	action_open(_action, _events)
	{
		let app, id, app_id;
		// Try to get better by going straight for the data
		let data = egw.dataGetUIDdata(_events[0].id);
		if(data && data.data)
		{
			app = data.data.app;
			app_id = data.data.app_id;
			id = data.data.id;
		}
		else
		{
			// Try to set some reasonable values from the ID
			id = _events[0].id.split('::');
			app = id[0];
			app_id = id[1];
			if(app_id && app_id.indexOf(':'))
			{
				let split = id[1].split(':');
				id = split[0];
			}
			else
			{
				id = app_id;
			}
		}

		if(_action.data.open)
		{
			var open = JSON.parse(_action.data.open) || {};
			var extra = open.extra || '';

			extra = extra.replace(/(\$|%24)app/,app).replace(/(\$|%24)app_id/,app_id)
					.replace(/(\$|%24)id/,id);

			// Get a little smarter with the context
			if(!extra)
			{
				var context : any = {};
				if(egw.dataGetUIDdata(_events[0].id) && egw.dataGetUIDdata(_events[0].id).data)
				{
					// Found data in global cache
					context = egw.dataGetUIDdata(_events[0].id).data;
					extra = {};
				}
				else if (_events[0].iface.getWidget() && _events[0].iface.getWidget()._get_time_from_position &&
						_action.menu_context && _action.menu_context.event
				)
				{
					// Non-row space in planner
					// Context menu has position information, but target is not what we expact
					let target = jQuery('.calendar_plannerGrid',_action.menu_context.event.currentTarget);
					var y = _action.menu_context.event.pageY - target.offset().top;
					var x = _action.menu_context.event.pageX - target.offset().left;
					var date = _events[0].iface.getWidget()._get_time_from_position(x, y);
					if(date)
					{
						context.start = date.toJSON();
					}
				}
				else if (_events[0].iface.getWidget() && _events[0].iface.getWidget().instanceOf(et2_calendar_planner_row))
				{
					// Empty space on a planner row
					var widget = _events[0].iface.getWidget();
					var parent = widget.getParent();
					if(parent.options.group_by == 'month')
					{
						var date = parent._get_time_from_position(_action.menu_context.event.clientX, _action.menu_context.event.clientY);
					}
					else
					{
						var date = parent._get_time_from_position(_action.menu_context.event.offsetX, _action.menu_context.event.offsetY);
					}
					if(date)
					{
						context.start = date.toJSON();
					}
					jQuery.extend(context, widget.getDOMNode().dataset);

				}
				else if (_events[0].iface.getWidget() && _events[0].iface.getWidget().instanceOf(et2_valueWidget))
				{
					// Able to extract something from the widget
					context = _events[0].iface.getWidget().getValue ?
						_events[0].iface.getWidget().getValue() :
						_events[0].iface.getWidget().options.value || {};
					extra = {};
				}
				// Try to pull whatever we can from the event
				else if (jQuery.isEmptyObject(context) && _action.menu_context && (_action.menu_context.event.target))
				{
					let target = _action.menu_context.event.target;
					while(target != null && target.parentNode && jQuery.isEmptyObject(target.dataset))
					{
						target = target.parentNode;
					}

					context = extra = jQuery.extend({},target.dataset);
					var owner = jQuery(target).closest('[data-owner]').get(0);
					if(owner && owner.dataset.owner && owner.dataset.owner != this.state.owner)
					{
						extra.owner = owner.dataset.owner.split(',');
					}
				}
				if(context.date) extra.date = context.date;
				if(context.app) extra.app = context.app;
				if(context.app_id) extra.app_id = context.app_id;
			}

			this.egw.open(open.id_data||'',open.app,open.type,extra ? extra : context);
		}
		else if (_action.data.url)
		{
			var url = _action.data.url;
			url = url.replace(/(\$|%24)app_id/,app_id)
			         .replace(/(\$|%24)app/,app)
			         .replace(/(\$|%24)id/,id);
			this.egw.open_link(url, _action.data.target, _action.data.popup);
		}
	}

	/**
	 * Check to see if we know how to convert this entry to the given app
	 *
	 * The current entry may not be an actual calendar event, it may be some other app
	 * that is participating via integration hook.  This is determined by checking the
	 * hooks defined for <appname>_set, indicating that the app knows how to provide
	 * information for that application
	 *
	 * @param {egwAction} _action
	 * @param {egwActionObject[]} _events
	 */
	action_convert_enabled_check(_action, _events) : boolean
	{
		let supported_apps = _action.data.convert_apps || [];
		let entry = egw.dataGetUIDdata(_events[0].id);

		if(supported_apps && entry && entry.data)
		{
			return supported_apps.length > 0 && supported_apps.indexOf(entry.data.app) >= 0;
		}
		return true;
	}

	/**
	 * Context menu action (on a single event) in non-listview to generate ical
	 *
	 * Since nextmatch is all ready to handle that, we pass it through
	 *
	 * @param {egwAction} _action
	 * @param {egwActionObject[]} _events
	 */
	ical(_action, _events)
	{
		// Send it through nextmatch
		_action.data.nextmatch = etemplate2.getById('calendar-list').widgetContainer.getWidgetById('nm');
		var ids = {ids:[]};
		for(var i = 0; i < _events.length; i++)
		{
			ids.ids.push(_events[i].id);
		}
		nm_action(_action, _events, null, ids);
	}

	/**
	 * Change status (via AJAX)
	 *
	 * @param {egwAction} _action
	 * @param {egwActionObject} _events
	 */
	status(_action, _events)
	{
		// Should be a single event, but we'll do it for all
		for(var i = 0; i < _events.length; i++)
		{
			var event_widget = _events[i].iface.getWidget() || false;
			if(!event_widget) continue;

			event_widget.recur_prompt(jQuery.proxy(function(button_id,event_data) {
				switch(button_id)
				{
					case 'exception':
						egw().json(
							'calendar.calendar_uiforms.ajax_status',
							[event_data.app_id, egw.user('account_id'), _action.data.id]
						).sendRequest(true);
						break;
					case 'series':
					case 'single':
						egw().json(
							'calendar.calendar_uiforms.ajax_status',
							[event_data.id, egw.user('account_id'), _action.data.id]
						).sendRequest(true);
						break;
					case 'cancel':
					default:
						break;
				}
			},this));
		}

	}

	/**
	 * this function try to fix ids which are from integrated apps
	 *
	 * @param {egwAction} _action
	 * @param {egwActionObject[]} _senders
	 */
	cal_fix_app_id(_action, _senders)
	{
		var app = 'calendar';
		var id = _senders[0].id;
		var matches = id.match(/^(?:calendar::)?([0-9]+)(:([0-9]+))?$/);
		if (matches)
		{
			id = matches[1];
		}
		else
		{
			matches = id.match(/^([a-z_-]+)([0-9]+)/i);
			if (matches)
			{
				app = matches[1];
				id = matches[2];
			}
		}
		var backup_url = _action.data.url;

		_action.data.url = _action.data.url.replace(/(\$|%24)id/,id);
		_action.data.url = _action.data.url.replace(/(\$|%24)app/,app);

		nm_action(_action, _senders,false,{ids:[id]});

		_action.data.url = backup_url;	// restore url
	}

	/**
	 * Open a smaller dialog/popup to add a new entry
	 *
	 * This is opened inside a dialog widget, not a popup.  This causes issues
	 * with the submission, handling of the response, and cleanup.  We're doing this because the server sets a lot
	 * of default values, but this could probably also be done more cleanly by getting the values via AJAX and passing
	 * them into the dialog
	 *
	 * @param {Object} options Array of values for new
	 * @param {et2_calendar_event} event Placeholder showing where new event goes
	 */
	add(options, event)
	{
		if(this.egw.preference('new_event_dialog', 'calendar') === 'edit')
		{
			// We lose control after this, so remove the placeholder now
			if(event && event.destroy)
			{
				event.destroy();
			}
			// Set this to open the add template in a popup
			//options.template = 'calendar.add';
			return this.egw.open(null, 'calendar', 'edit', options, '_blank', 'calendar');
		}
		// Hold on to options, may have to pass them into edit (rather than all, just send what the programmer wanted)
		this.quick_add = options;

		// Open dialog to use as target
		let add_dialog = Et2Dialog.show_dialog(null, '', 'Add new event', null, [], Et2Dialog.PLAIN_MESSAGE, "", this.egw);
		add_dialog.id = "quick_add";
		// Position by the event
		if(event)
		{
			add_dialog.config = {...add_dialog.config, referenceNode: event.getDOMNode()};
		}

		// Call the server, get it into the dialog
		options = jQuery.extend({menuaction: 'calendar.calendar_uiforms.ajax_add', template: 'calendar.add'}, options);
		this.egw.json(
			this.egw.link('/json.php', options),
			[options],
			function(data)
			{
				if(data.type)
				{
					return false;
				}
				var content = {
					html: data[0],
					js: ''
				};

				egw_seperateJavaScript(content);

				// Check for right template in the response
				if(content.html.indexOf('calendar-add') <= 0)
				{
					return false;
				}
				// Insert the content into the correct place
				add_dialog._contentNode.replaceChildren();
				add_dialog._contentNode.insertAdjacentHTML("beforeend", content.html);
				let template = add_dialog.querySelector("[id='calendar-add']");
				if(template)
				{
					template.addEventListener("load", add_dialog._adoptTemplateButtons);
				}
			}
		).sendRequest();

		add_dialog.addEventListener("close", (ev) =>
		{
			// Wait a bit to make sure etemplate button finishes processing, or it will error
			window.setTimeout(function()
			{
				if(event && event.destroy)
				{
					event.destroy();
				}
				let template = etemplate2.getById('calendar-add');
				if(template && template.name === 'calendar.add')
				{
					template.clear();
					delete app.calendar.quick_add;
				}
				else if(template || (template = etemplate2.getById("calendar-conflicts")))
				{
					// Open conflicts
					var data = jQuery.extend(
						{menuaction: 'calendar.calendar_uiforms.ajax_conflicts'},
						template.widgetContainer.getArrayMgr('content').data,
						app.calendar.quick_add
					);

					egw.openPopup(
						egw.link(
							'/index.php',
							data
						),
						850, 300,
						'conflicts', 'calendar'
					);

					delete app.calendar.quick_add;

					// Do not submit this etemplate
					return false;
				}

			  }.bind({dialog: add_dialog, event: ev}), 1000);
		});
	}

	/**
	 * Callback for save button in add dialog
	 *
	 * @param {Event} event
	 * @param {et2_button} widget
	 * @returns {Boolean}
	 */
	add_dialog_save(event, widget)
	{
		// Include all sent values so we can pass on things that we don't have UI widgets for
		this.quick_add = this._add_dialog_values(widget);
		widget.getInstanceManager().isInvalid().then((invalid) =>
		{
			if(invalid.filter((widget) => widget).length == 0)
			{
				// Close the dialog, if everything is OK
				(<Et2Dialog><unknown>document.querySelector('et2-dialog#quick_add')).hide();
			}
		});

		// Mess with opener so update opener in response works
		window.opener = window;
		window.setTimeout(function() {window.opener = null;}, 1000);

		// Proceed with submit, though it will fail if something is invalid
		return true;
	}


	/**
	 * Callback for edit button in add dialog
	 *
	 * @param {Event} event
	 * @param {et2_button} widget
	 * @returns {Boolean}
	 */
	add_dialog_edit(event, widget)
	{
		var title=widget.getRoot().getWidgetById('title');
		let options = jQuery.extend(this.quick_add, this._add_dialog_values(widget));

		// Open regular edit
		egw.open(null,'calendar','edit',options);

		// Close the dialog
		(<Et2Dialog><unknown>document.querySelector('et2-dialog#quick_add')).close()

		// Do not submit this etemplate
		return false;
	}

	/**
	 * Include some additional values so we can pass on things that we don't have
	 * UI widgets for in the add template
	 *
	 * @param {et2_widget} widget
	 * @returns {Object}
	 */
	_add_dialog_values(widget)
	{
		// Some select things to pass on, since not everything will fit
		var mgr = widget.getRoot().getArrayMgr('content');
		var values : any = {
			owner: typeof mgr.getEntry('owner') == 'object' ? mgr.getEntry('owner') : (mgr.getEntry('owner')+'').split(','),
			participants: [],
			whole_day: mgr.getEntry('whole_day')
		};
		if(mgr.getEntry('link_to') && typeof mgr.getEntry('link_to').to_id === 'object')
		{
			var links = mgr.getEntry('link_to').to_id;

			values.link_app = [];
			values.link_id = [];
			for( var id in links)
			{
				values.link_app.push(links[id].app);
				values.link_id.push(links[id].id);
			}
		}
		for(var id in mgr.getEntry('participants'))
		{
			var participant = mgr.getEntry('participants')[id];
			if (participant && participant.uid)
			{
				values.participants.push(participant.uid);
			}
		}
		let send = jQuery.extend(
				values,
				widget.getInstanceManager().getValues(widget.getRoot())
		);
		// Don't need the checkbox
		delete send.new_event_dialog;

		return send;
	}

	/**
	 * Open calendar entry, taking into accout the calendar integration of other apps
	 *
	 * calendar_uilist::get_rows sets var js_calendar_integration object
	 *
	 * @param _action
	 * @param _senders
	 *
	 */
	cal_open(_action, _senders)
	{
		// Try for easy way - find a widget
		if(_senders[0].iface.getWidget)
		{
			var widget = _senders[0].iface.getWidget();
			return widget.recur_prompt();
		}

		// Nextmatch in list view does not have a widget, but we can pull
		// the data by ID
		// Check for series
		var id = _senders[0].id;
		var data = egw.dataGetUIDdata(id);
		if (data && data.data)
		{
			et2_calendar_event.recur_prompt(data.data);
			return;
		}
		var matches = id.match(/^(?:calendar::)?([0-9]+):([0-9]+)$/);

		// Check for other app integration data sent from server
		var backup = _action.data;
		if(_action.parent.data && _action.parent.data.nextmatch)
		{
			var js_integration_data = _action.parent.data.nextmatch.options.settings.js_integration_data || this.et2.getArrayMgr('content').data.nm.js_integration_data;
			if(typeof js_integration_data == 'string')
			{
				js_integration_data = JSON.parse(js_integration_data);
			}
		}
		matches = id.match(/^calendar::([a-z_-]+)([0-9]+)/i);
		if (matches && js_integration_data && js_integration_data[matches[1]])
		{
			var app = matches[1];
			_action.data.url = window.egw_webserverUrl+'/index.php?';
			var get_params = js_integration_data[app].edit;
			get_params[js_integration_data[app].edit_id] = matches[2];
			for(var name in get_params)
				_action.data.url += name+"="+encodeURIComponent(get_params[name])+"&";

			if (js_integration_data[app].edit_popup)
			{
				egw.open_link(_action.data.url,'_blank',js_integration_data[app].edit_popup,app);

				_action.data = backup;	// restore url, width, height, nm_action
				return;
			}
		}
		else
		{
			// Other app integration using link registry
			var data = egw.dataGetUIDdata(_senders[0].id);
			if(data && data.data)
			{
				return egw.open(data.data.app_id, data.data.app, 'edit');
			}
		}
		// Regular, single event
		egw.open(id.replace(/^calendar::/g,''),'calendar','edit');
	}

	/**
	 * Delete (a single) calendar entry over ajax.
	 *
	 * Used for the non-list views
	 *
	 * @param {egwAction} _action
	 * @param {egwActionObject} _events
	 */
	delete(_action, _events)
	{
		// Should be a single event, but we'll do it for all
		for(var i = 0; i < _events.length; i++)
		{
			var event_widget = _events[i].iface.getWidget() || false;
			if(!event_widget) continue;

			event_widget.recur_prompt(jQuery.proxy(function(button_id,event_data) {
				switch(button_id)
				{
					case 'exception':
						egw().json(
							'calendar.calendar_uiforms.ajax_delete',
							[event_data.app_id]
						).sendRequest(true);
						break;
					case 'series':
					case 'single':
						egw().json(
							'calendar.calendar_uiforms.ajax_delete',
							[event_data.id]
						).sendRequest(true);
						break;
					case 'cancel':
					default:
						break;
				}
			},this));
		}
	}

	/**
	 * Delete calendar entry, asking if you want to delete series or exception
	 *
	 * Used for nextmatch
	 *
	 * @param _action
	 * @param _senders
	 */
	cal_delete(_action, _senders)
	{
		let all = _action.parent.data.nextmatch?.getSelection().all;
		let no_notifications = _action.parent.getActionById("no_notifications")?.checked;
		let matches = false;
		let ids = [];
		let cal_event = this.egw.dataGetUIDdata(_senders[0].id);

		// Loop so we ask if any of the selected entries is part of a series
		for(var i = 0; i < _senders.length; i++)
		{
			var id = _senders[i].id;
			if(!matches)
			{
				matches = id.match(/^(?:calendar::)?([0-9]+):([0-9]+)$/);
			}
			ids.push(id.split("::").pop());
		}
		if (matches)
		{
			// At least one event is a series, use its data to trigger the prompt
			let cal_event = this.egw.dataGetUIDdata( matches[0]);
		}
		et2_calendar_event.recur_prompt(cal_event.data,function(button_id,event_data) {
			switch(button_id)
			{
				case 'single':
				case 'exception':
					// Just this one, handle in the normal way but over AJAX
					egw.json("calendar.calendar_uilist.ajax_action",[_action.id, ids, all, no_notifications]).sendRequest(true);
					break;
				case 'series':
					// No recurrences, handle in the normal way but over AJAX
					egw.json("calendar.calendar_uilist.ajax_action",["delete_series", ids, all, no_notifications]).sendRequest(true);
					break;
				case 'cancel':
				default:
					break;
			}
		}.bind(this) );

	}

	/**
	 * Confirmation dialog for moving a series entry
	 *
	 * @param {object} _DOM
	 * @param {et2_widget} _button button Save | Apply
	 */
	move_edit_series(_DOM,_button)
	{
		var content : any = this.et2.getArrayMgr('content').data;
		var start_date = this.et2.getValueById('start');
		var end_date = this.et2.getValueById('end');
		var whole_day = <et2_checkbox> this.et2.getWidgetById('whole_day');
		var duration = ''+this.et2.getValueById('duration');
		var is_whole_day = whole_day && whole_day.get_value() == whole_day.options.selected_value;
		var button = _button;
		var that = this;

		let instance_date_regex = window.location.search.match(/date=(\d{4}-\d{2}-\d{2}(?:.+Z)?)/);
		let instance_date;
		if(instance_date_regex && instance_date_regex.length && instance_date_regex[1])
		{
			instance_date = new Date(unescape(instance_date_regex[1]));
			instance_date.setUTCMinutes(instance_date.getUTCMinutes() +instance_date.getTimezoneOffset());
		}
		if (typeof content != 'undefined' && content.id != null &&
			typeof content.recur_type != 'undefined' && content.recur_type != null && content.recur_type != 0
		)
		{
			if (content.start != start_date ||
				content.whole_day != is_whole_day ||
				(duration && ''+content.duration != duration ||
				// End date might ignore seconds, and be 59 seconds off for all day events
				!duration && Math.abs(new Date(end_date) - new Date(content.end)) > 60000)
			)
			{
				et2_calendar_event.series_split_prompt(
					content, instance_date, function(_button_id)
					{
						if(_button_id == Et2Dialog.OK_BUTTON)
						{
							that.et2.getInstanceManager().submit(button);

						}
					}
				);
			}
			else
			{
				return true;
			}
		}
		else
		{
			return true;
		}
	}

	/**
	 * Send a mail  or meeting request to event participants
	 *
	 * @param {egwAction} _action
	 * @param {egwActionObject[]} _selected
	 */
	action_mail(_action, _selected)
	{
		var data = egw.dataGetUIDdata(_selected[0].id) || {data:{}};
		var event = data.data;
		this.egw.json('calendar.calendar_uiforms.ajax_custom_mail',
			[event, false, _action.id==='sendrequest'],
			null,null,null,null
		).sendRequest();
	}

	/**
	 * Sidebox merge
	 *
	 * Manage the state and pass the request to the correct place.  Since the nextmatch
	 * and the sidebox have different ideas of the 'current' timespan (sidebox
	 * always has a start and end date) we need to call merge on the nextmatch
	 * if the current view is listview, so the user gets the results they expect.
	 *
	 * @param {Event} event UI event
	 * @param {et2_widget} widget Should be the merge selectbox
	 */
	sidebox_merge(event, widget)
	{
		if(!widget || !widget.getValue()) return false;

		if(this.state.view == 'listview')
		{
			// If user is looking at the list, pretend they used the context
			// menu and process it through the nextmatch
			var nm = etemplate2.getById('calendar-list').widgetContainer.getWidgetById('nm') || false;
			var selected = nm ? nm.controller._objectManager.getSelectedLinks() : [];
			var action = nm.controller._actionManager.getActionById('document_'+widget.getValue());
			if(nm && (!selected || !selected.length))
			{
				nm.controller._selectionMgr.selectAll(true);
			}
			if(action && selected)
			{
				super.merge(action, selected);
			}
		}
		else
		{
			// Set the hidden inputs to the current time span & submit
			widget.getRoot().getWidgetById('first').set_value(app.calendar.state.first);
			widget.getRoot().getWidgetById('last').set_value(app.calendar.state.last);

			let vars = {
				menuaction: 'calendar.calendar_merge.merge_entries',
				document: widget.getValue(),
				merge: 'calendar_merge',
				pdf: false,
				select_all: false,
				id: JSON.stringify({
					first: app.calendar.state.first,
					last: app.calendar.state.last,
					date: app.calendar.state.first,
					view: app.calendar.state.view
				})
			};
			egw.open_link(egw.link('/index.php', vars), '_blank');
		}
		widget.set_value('');

		return false;
	}

	/**
	 * Method to set state for JSON requests (jdots ajax_exec or et2 submits can NOT use egw.js script tag)
	 *
	 * @param {object} _state
	 */
	set_state(_state)
	{
		if (typeof _state == 'object')
		{
			// If everything is loaded, handle the changes
			if(this.sidebox_et2 !== null)
			{
				this.update_state(_state);
			}
			else
			{
				// Things aren't loaded yet, just set it
				this.state = _state;
			}
		}
	}

	/**
	 * Change only part of the current state.
	 *
	 * The passed state options (filters) are merged with the current state, so
	 * this is the one that should be used for most calls, as setState() requires
	 * the complete state.
	 *
	 * @param {Object} _set New settings
	 */
	update_state(_set)
	{
		// Make sure we're running in top window
		// @ts-ignore
		if(window !== window.top && window.top.app.calendar)
		{
			// @ts-ignore
			return window.top.app.calendar.update_state(_set);
		}
		if(this.state_update_in_progress) return;

		var changed = [];
		var new_state = jQuery.extend({}, this.state);
		if (typeof _set === 'object')
		{
			for(var s in _set)
			{
				if (new_state[s] !== _set[s] && (typeof new_state[s] == 'string' || typeof new_state[s] !== 'string' && new_state[s]+'' !== _set[s]+''))
				{
					changed.push(s + ': ' + new_state[s] + ' -> ' + _set[s]);
					new_state[s] = _set[s];
				}
			}
		}
		if(changed.length && !this.state_update_in_progress)
		{
			// This activates calendar app if you call setState from a different app
			// such as home.  If we change state while not active, sizing is wrong.
			if(typeof framework !== 'undefined' && framework.applications.calendar && framework.applications.calendar.hasSideboxMenuContent)
			{
				framework.setActiveApp(framework.applications.calendar);
			}

			console.log('Calendar state changed',changed.join("\n"));
			// Log
			this.egw.debug('navigation','Calendar state changed', changed.join("\n"));
			this.setState({state: new_state});
		}
	}

	/**
	 * Return state object defining current view
	 *
	 * Called by favorites to query current state.
	 *
	 * @return {object} description
	 */
	getState()
	{
		var state = jQuery.extend({},this.state);

		if (!state)
		{
			var egw_script_tag = document.getElementById('egw_script_id');
			let tag_state = egw_script_tag.getAttribute('data-calendar-state');
			state = tag_state ? JSON.parse(tag_state) : {};
		}

		// Don't store current user in state to allow admins to create favourites for all
		// Should make no difference for normal users.
		if(state.owner == egw.user('account_id'))
		{
			// 0 is always the current user, so if an admin creates a default favorite,
			// it will work for other users too.
			state.owner = 0;
		}

		// Keywords are only for list view
		if(state.view == 'listview')
		{
			var listview : et2_nextmatch = typeof CalendarApp.views.listview.etemplates[0] !== 'string' &&
				CalendarApp.views.listview.etemplates[0].widgetContainer &&
				<et2_nextmatch> CalendarApp.views.listview.etemplates[0].widgetContainer.getWidgetById('nm');
			if(listview && listview.activeFilters && listview.activeFilters.search)
			{
				state.keywords = listview.activeFilters.search;
			}
		}

		// Don't store date or first and last
		delete state.date;
		delete state.first;
		delete state.last;
		delete state.startdate;
		delete state.enddate;
		delete state.start_date;
		delete state.end_date;

		return state;
	}

	/**
	 * Set a state previously returned by getState
	 *
	 * Called by favorites to set a state saved as favorite.
	 *
	 * @param {object} state containing "name" attribute to be used as "favorite" GET parameter to a nextmatch
	 */
	setState(state)
	{
		// State should be an object, not a string, but we'll parse
		if(typeof state == "string")
		{
			if(state.indexOf('{') != -1 || state =='null')
			{
				state = JSON.parse(state);
			}
		}
		if(typeof state.state !== 'object' || !state.state.view)
		{
			state.state = {view: 'week'};
		}
		// States with no name (favorites other than No filters) default to
		// today.  Applying a favorite should keep the current date.
		if(!state.state.date)
		{
			state.state.date = state.name ? this.state.date : new Date();
		}
		if(typeof state.state.weekend == 'undefined')
		{
			state.state.weekend = true;
		}

		// Hide other views
		var view = CalendarApp.views[state.state.view];
		for(var _view in CalendarApp.views)
		{
			if(state.state.view != _view && CalendarApp.views[_view])
			{
				for(var i = 0; i < CalendarApp.views[_view].etemplates.length; i++)
				{
					if(typeof CalendarApp.views[_view].etemplates[i] !== 'string' &&
						view.etemplates.indexOf(CalendarApp.views[_view].etemplates[i]) == -1)
					{
						jQuery(CalendarApp.views[_view].etemplates[i].DOMContainer).hide();
					}
				}
			}
		}
		if(this.sidebox_et2)
		{
			jQuery(this.sidebox_et2.getInstanceManager().DOMContainer).hide();
		}

		// Check for valid cache
		var cachable_changes = ['date','weekend','view','days','planner_view','sortby'];
		// @ts-ignore
		let keys = (Object.keys(this.state).concat(Object.keys(state.state))).filter(
			function(value, index, self)
			{
				return self.indexOf(value) === index;
			}
		);
		for(var i = 0; i < keys.length; i++)
		{
			var s = keys[i];
			if (this.state[s] !== state.state[s])
			{
				if(cachable_changes.indexOf(s) === -1)
				{
					// Expire daywise cache
					var daywise = egw.dataKnownUIDs(CalendarApp.DAYWISE_CACHE_ID);

					// Can't delete from here, as that would disconnect the existing widgets listening
					for(var i = 0; i < daywise.length; i++)
					{
						egw.dataStoreUID(CalendarApp.DAYWISE_CACHE_ID + '::' + daywise[i],null);
					}
					break;
				}
			}
		}

		// Check for a supported client-side view
		if(CalendarApp.views[state.state.view] &&
			// Check that the view is instanciated
			typeof CalendarApp.views[state.state.view].etemplates[0] !== 'string' && CalendarApp.views[state.state.view].etemplates[0].widgetContainer
		)
		{
			// Doing an update - this includes the selected view, and the sidebox
			// We set a flag to ignore changes from the sidebox which would
			// cause infinite loops.
			this.state_update_in_progress = true;

			// Sanitize owner so it's always an array
			if(state.state.owner === null || !state.state.owner ||
				(typeof state.state.owner.length != 'undefined' && state.state.owner.length == 0)
			)
			{
				state.state.owner = undefined;
			}
			switch(typeof state.state.owner)
			{
				case 'undefined':
					state.state.owner = [this.egw.user('account_id')];
					break;
				case 'string':
					state.state.owner = state.state.owner.split(',');
					break;
				case 'number':
					state.state.owner = [state.state.owner];
					break;
				case 'object':
					// An array-like Object or an Array?
					if(!state.state.owner.filter)
					{
						state.state.owner = jQuery.map(state.state.owner, function(owner) {return owner;});
					}
			}
			if(state.state.owner.indexOf('0') >= 0)
			{
				state.state.owner[state.state.owner.indexOf('0')] = this.egw.user('account_id');
			}
			// Remove duplicates
			state.state.owner = state.state.owner.filter(function(value, index, self)
			{
				return self.indexOf(value) === index;
			});
			// Make sure they're all strings
			state.state.owner = state.state.owner.map(function(owner) { return '' + owner;});

			// Show the correct number of grids
			var grid_count = 0;
			switch(state.state.view)
			{
				case 'day':
					grid_count = 1;
					break;
				case 'day4':
				case 'week':
					grid_count = state.state.owner.length >= parseInt(''+this.egw.preference('week_consolidate','calendar')) ? 1 : state.state.owner.length;
					break;
				case 'weekN':
					grid_count = parseInt(''+this.egw.preference('multiple_weeks','calendar')) || 3;
					break;
				// Month is calculated individually for the month
			}

			var grid = view.etemplates[0].widgetContainer.getWidgetById('view');

			// Show the templates for the current view
			// Needs to be visible while updating so sizing works
			for(var i = 0; i < view.etemplates.length; i++)
			{
				jQuery(view.etemplates[i].DOMContainer).show();
			}

			/*
			If the count is different, we need to have the correct number
			If the count is > 1, it's either because there are multiple date spans (weekN, month) and we need the correct span
			per row, or there are multiple owners and we need the correct owner per row.
			*/
			if(grid)
			{
				// Show loading div to hide redrawing
				egw.loading_prompt(
					this.appname,true,egw.lang('please wait...'),
					typeof framework !== 'undefined' ? framework.applications.calendar.tab.contentDiv : false,
					egwIsMobile()?'horizontal':'spinner'
				);

				var loading = false;


				var value = [];
				state.state.first = view.start_date(state.state).toJSON();
				// We'll modify this one, so it needs to be a new object
				var date = new Date(state.state.first);

				// Hide all but the first day header
				jQuery(grid.getDOMNode()).toggleClass(
					'hideDayColHeader',
					state.state.view == 'week' || state.state.view == 'day4'
				);

				// Determine the different end date & varying values
				switch(state.state.view)
				{
					case 'month':
						var end = state.state.last = view.end_date(state.state);
						grid_count = Math.ceil((end - date.valueOf()) / (1000 * 60 * 60 * 24) / 7);
						// fall through
					case 'weekN':
						for(var week = 0; week < grid_count; week++)
						{
							var val = {
								id: CalendarApp._daywise_cache_id(date,state.state.owner),
								start_date: date.toJSON(),
								end_date: new Date(date.toJSON()),
								owner: state.state.owner
							};
							val.end_date.setUTCHours(24*7-1);
							val.end_date.setUTCMinutes(59);
							val.end_date.setUTCSeconds(59);
							val.end_date = val.end_date.toJSON();
							value.push(val);
							date.setUTCHours(24*7);
						}
						state.state.last=val.end_date;
						break;
					case 'day':
						var end = state.state.last = view.end_date(state.state).toJSON();
							value.push({
							id: CalendarApp._daywise_cache_id(date,state.state.owner),
								start_date: state.state.first,
								end_date: state.state.last,
								owner: view.owner(state.state)
							});
						break;
					default:
						var end = state.state.last = view.end_date(state.state).toJSON();
						for(let owner = 0; owner < grid_count && owner < state.state.owner.length; owner++)
						{
							var _owner = grid_count > 1 ? state.state.owner[owner] || 0 : state.state.owner;
							value.push({
								id: CalendarApp._daywise_cache_id(date,_owner),
								start_date: date,
								end_date: end,
								owner: _owner
							});
						}
						break;
				}
				// If we have cached data for the timespan, pass it along
				// Single day with multiple owners still needs owners split to satisfy
				// caching keys, otherwise they'll fetch & cache consolidated
				if(state.state.view == 'day' && state.state.owner.length < parseInt(''+this.egw.preference('day_consolidate','calendar')))
				{
					var day_value = [];
					for(var i = 0; i < state.state.owner.length; i++)
					{
						day_value.push({
							start_date: state.state.first,
							end_date: state.state.last,
							owner: state.state.owner[i]
						});
					}
					loading = this._need_data(day_value,state.state);
				}
				else
				{
					loading = this._need_data(value,state.state);
				}

				var row_index = 0;

				// Find any matching, existing rows - they can be kept
				grid.iterateOver(function(widget) {
					for(var i = 0; i < value.length; i++)
					{
						if(widget.id == value[i].id)
						{
							// Keep it, but move it
							if(i > row_index)
							{
								for(var j = i-row_index; j > 0; j--)
								{
									// Move from the end to the start
									grid._children.unshift(grid._children.pop());

									// Swap DOM nodes
									var a = grid._children[0].getDOMNode().parentNode.parentNode;
									let a_scroll = jQuery('.calendar_calTimeGridScroll',a).scrollTop();
									var b = grid._children[1].getDOMNode().parentNode.parentNode;
									a.parentNode.insertBefore(a,b);

									// Moving nodes changes scrolling, so set it back
									jQuery('.calendar_calTimeGridScroll',a).scrollTop(a_scroll);
								}
							}
							else if (row_index > i)
							{
								// Swap DOM nodes
								var a = grid._children[row_index].getDOMNode().parentNode.parentNode;
								let a_scroll = jQuery('.calendar_calTimeGridScroll',a).scrollTop();
								var b = grid._children[i].getDOMNode().parentNode.parentNode;

								// Simple scroll forward, put top on the bottom
								// This makes it faster if they scroll back next
								if(i==0 && row_index == 1)
								{
									jQuery(b).appendTo(b.parentNode);
									grid._children.push(grid._children.shift());
								}
								else
								{
									grid._children.splice(i,0,widget);
									grid._children.splice(row_index+1,1);
									a.parentNode.insertBefore(a,b);
								}

								// Moving nodes changes scrolling, so set it back
								jQuery('.calendar_calTimeGridScroll',a).scrollTop(a_scroll);
							}
							break;
						}
					}
					row_index++;
				},this,et2_calendar_view);
				row_index = 0;

				// Set rows that need it
				var was_disabled = [];
				grid.iterateOver(function(widget) {
					was_disabled[row_index] = false;
					if(row_index < value.length)
					{
						was_disabled[row_index] = widget.options.disabled;
						widget.set_disabled(false);
					}
					else
					{
						widget.set_disabled(true);
					}
					row_index++;
				},this, et2_calendar_view);
				row_index = 0;
				grid.iterateOver(function(widget) {
					if(row_index >= value.length) return;

					// Clear height to make sure there's correct calculations
					widget.div.css("height","");

					if(widget.set_show_weekend)
					{
						widget.set_show_weekend(view.show_weekend(state.state));
					}
					if(widget.set_granularity)
					{
						if(widget.loader) widget.loader.show();
						widget.set_granularity(view.granularity(state.state));
					}
					if(widget.id == value[row_index].id &&
						widget.get_end_date().getUTCFullYear() == value[row_index].end_date.substring(0,4) &&
						widget.get_end_date().getUTCMonth()+1 == value[row_index].end_date.substring(5,7) &&
						widget.get_end_date().getUTCDate() == value[row_index].end_date.substring(8,10)
					)
					{
						// Do not need to re-set this row, but we do need to re-do
						// the times, as they may have changed
						widget.resizeTimes();
						window.setTimeout(jQuery.proxy(widget.set_header_classes, widget),0);

						// If disabled while the daycols were loaded, they won't load their events
						for(var day = 0; was_disabled[row_index] && day < widget.day_widgets.length; day++)
						{
							egw.dataStoreUID(
									widget.day_widgets[day].registeredUID,
								egw.dataGetUIDdata(widget.day_widgets[day].registeredUID).data
							);
						}
						widget.set_owner(value[row_index].owner);

						// Hide loader
						widget.loader.hide();
						row_index++;
						return;
					}
					if(widget.set_value)
					{
						widget.set_value(value[row_index++]);
					}
				},this, et2_calendar_view);
			}
			else if(state.state.view !== 'listview')
			{
				// Simple, easy case - just one widget for the selected time span. (planner)
				// Update existing view's special attribute filters, defined in the view list
				for(let updater of view.getAllFuncs(view))
				{
					if(typeof view[updater] === 'function')
					{
						let value = view[updater].call(this, state.state);
						if(updater === 'start_date')
						{
							state.state.first = this.date.toString(value);
						}
						if(updater === 'end_date')
						{
							state.state.last = this.date.toString(value);
						}

						// Set value
						for(var i = 0; i < view.etemplates.length; i++)
						{
							view.etemplates[i].widgetContainer.iterateOver(function(widget)
							{
								if(typeof widget['set_' + updater] === 'function')
								{
									widget['set_' + updater](value);
								}
							}, this, et2_calendar_view);
						}
					}
				}
				let value = [{start_date: state.state.first, end_date: state.state.last}];
				loading = this._need_data(value,state.state);
			}
			// Include first & last dates in state, mostly for server side processing
			if(state.state.first && state.state.first.toJSON) state.state.first = state.state.first.toJSON();
			if(state.state.last && state.state.last.toJSON) state.state.last = state.state.last.toJSON();

			// Toggle todos
			if((state.state.view == 'day' || this.state.view == 'day') && jQuery(view.etemplates[0].DOMContainer).is(':visible'))
			{
				if(state.state.view == 'day' && state.state.owner.length === 1 && !isNaN(state.state.owner) && state.state.owner[0] >= 0 && !egwIsMobile()
				// Check preferences and permissions
					&& egw.user('apps')['infolog'] && egw.preference('cal_show','infolog') !== '0'
				)
				{
					// Set width to 70%, otherwise if a scrollbar is needed for the view, it will conflict with the todo list
					jQuery((<etemplate2>CalendarApp.views.day.etemplates[0]).DOMContainer).css("width","70%");
					jQuery(view.etemplates[1].DOMContainer).css({"left":"70%"});
					// TODO: Maybe some caching here
					this.egw.jsonq('calendar_uiviews::ajax_get_todos', [state.state.date, state.state.owner[0]], function(data) {
						this.getWidgetById('label').set_value(data.label||'');
						this.getWidgetById('todos').set_value({content:data.todos||''});
					},view.etemplates[1].widgetContainer);
					view.etemplates[0].resize();
				}
				else
				{
					jQuery((<etemplate2>CalendarApp.views.day.etemplates[1]).DOMContainer).css("left","100%");
					jQuery((<etemplate2>CalendarApp.views.day.etemplates[1]).DOMContainer).hide();
					jQuery((<etemplate2>CalendarApp.views.day.etemplates[0]).DOMContainer).css("width","100%");
					view.etemplates[0].widgetContainer.iterateOver(function(w) {
						w.set_width('100%');
					},this,et2_calendar_timegrid);
				}
			}
			else if(jQuery(view.etemplates[0].DOMContainer).is(':visible'))
			{
				jQuery(view.etemplates[0].DOMContainer).css("width","");
				view.etemplates[0].widgetContainer.iterateOver(function(w) {
					w.set_width('100%');
				},this,et2_calendar_timegrid);
			}

			// List view (nextmatch) has slightly different fields
			if(state.state.view === 'listview')
			{
				state.state.startdate = state.state.date;
				if(state.state.startdate.toJSON)
				{
					state.state.startdate = state.state.startdate.toJSON();
				}

				if(state.state.end_date)
				{
					state.state.enddate = state.state.end_date;
				}
				if(state.state.enddate && state.state.enddate.toJSON)
				{
					state.state.enddate = state.state.enddate.toJSON();
				}
				state.state.col_filter = {participant: state.state.owner};
				state.state.search = state.state.keywords ? state.state.keywords : state.state.search;
				delete state.state.keywords;

				var nm = view.etemplates[0].widgetContainer.getWidgetById('nm');

				// 'Custom' filter needs an end date
				if(nm.activeFilters.filter === 'custom' && !state.state.end_date)
				{
					state.state.enddate = state.state.last;
				}
				if(state.state.enddate && state.state.startdate && state.state.startdate > state.state.enddate)
				{
					state.state.enddate = state.state.startdate;
				}
				nm.applyFilters(state.state);

				// Try to keep last value up to date with what's in nextmatch
				if(nm.activeFilters.enddate)
				{
					this.state.last = nm.activeFilters.enddate;
				}
				// Updates the display of start & end date
				this.filter_change();
			}
			else
			{
				// Turn off nextmatch's automatic stuff - it won't work while it
				// is hidden, and can cause an infinite loop as it tries to layout.
				// (It will automatically re-start when shown)
				try
				{
					var nm = (<etemplate2>CalendarApp.views.listview.etemplates[0]).widgetContainer.getWidgetById('nm');
					nm.controller._grid.doInvalidate = false;
				} catch (e) {}
				// Other views do not search
				delete state.state.keywords;
			}
			this.state = jQuery.extend({},state.state);

			/* Update re-orderable calendars */
			this._sortable();

			/* Update sidebox widgets to show current value*/
			if(this.sidebox_hooked_templates.length)
			{
				for(var j = 0; j < this.sidebox_hooked_templates.length; j++)
				{
					var sidebox = this.sidebox_hooked_templates[j];
					// Remove any destroyed or not valid templates
					if(!sidebox.getInstanceManager || !sidebox.getInstanceManager())
					{
						this.sidebox_hooked_templates.splice(j,1,0);
						continue;
					}
					sidebox.iterateOver(function(widget) {
						if(widget.id == 'view')
						{
							// View widget has a list of state settings, which require special handling
							for(var i = 0; i < widget.options.select_options.length; i++)
							{
								var option_state = JSON.parse(widget.options.select_options[i].value) || [];
								var match = true;
								for(var os_key in option_state)
								{
									// Sometimes an optional state variable is not yet defined (sortby, days, etc)
									match = match && (option_state[os_key] == this.state[os_key] || typeof this.state[os_key] == 'undefined');
								}
								if(match)
								{
									widget.set_value(widget.options.select_options[i].value);
									return;
								}
							}
						}
						else if (widget.id == 'keywords')
						{
							widget.set_value('');
						}
						else if(typeof state.state[widget.id] !== 'undefined' && state.state[widget.id] != widget.getValue())
						{
							// Update widget.  This may trigger an infinite loop of
							// updates, so we do it after changing this.state and set a flag
							try
							{
								widget.set_value(state.state[widget.id]);
							}
							catch(e)
							{
								widget.set_value('');
							}
						}
						else if(typeof widget.set_value == "function" && typeof state.state[widget.id] == 'undefined')
						{
							// No value, clear it
							widget.set_value('');
						}
					}, this, et2_IInput);
				}
			}

			// If current state matches a favorite, highlight it
			this.highlight_favorite();

			// Update app header
			this.set_app_header(view.header(state.state));

			// Reset auto-refresh timer
			this._set_autorefresh();

			// Sidebox is updated, we can clear the flag
			this.state_update_in_progress = false;

			// Update saved state in preferences
			var save = {};
			for(var i = 0; i < CalendarApp.states_to_save.length; i++)
			{
				save[CalendarApp.states_to_save[i]] = this.state[CalendarApp.states_to_save[i]];
			}
			egw.set_preference('calendar','saved_states', save);

			// Trigger resize to get correct sizes, as they may have sized while
			// hidden
			for(var i = 0; i < view.etemplates.length; i++)
			{
				view.etemplates[i].resize();
			}

			// If we need to fetch data from the server, it will hide the loader
			// when done but if everything is in the cache, hide from here.
			if(!loading)
			{
				window.setTimeout(jQuery.proxy(function() {

					egw.loading_prompt(this.appname,false);
				},this),500);
			}

			return;
		}
		// old calendar state handling on server-side (incl. switching to and from listview)
		var menuaction = 'calendar.calendar_uiviews.index';
		if (typeof state.state != 'undefined' && (typeof state.state.view == 'undefined' || state.state.view == 'listview'))
		{
			if (state.name)
			{
				// 'blank' is the special name for no filters, send that instead of the nice translated name
				state.state.favorite = jQuery.isEmptyObject(state) || jQuery.isEmptyObject(state.state || state.filter) ? 'blank' : state.name.replace(/[^A-Za-z0-9-_]/g, '_');
				// set date for "No Filter" (blank) favorite to todays date
				if(state.state.favorite == 'blank')
				{
					state.state.date = formatDate(new Date, {dateFormat: 'yymmdd'});
				}
			}
			menuaction = 'calendar.calendar_uilist.listview';
			state.state.ajax = 'true';
			// check if we already use et2 / are in listview
			if (this.et2 || etemplate2 && etemplate2.getByApplication('calendar'))
			{
				// current calendar-code can set regular calendar states only via a server-request :(
				// --> check if we only need to set something which can be handeled by nm internally
				// or we need a redirect
				// ToDo: pass them via nm's get_rows call to server (eg. by passing state), so we dont need a redirect
				var current_state = this.getState();
				var need_redirect = false;
				for(var attr in current_state)
				{
					switch(attr)
					{
						case 'cat_id':
						case 'owner':
						case 'filter':
							if (state.state[attr] != current_state[attr])
							{
								need_redirect = true;
								// reset of attributes managed on server-side
								if (state.state.favorite === 'blank')
								{
									switch(attr)
									{
										case 'cat_id':
											state.state.cat_id = 0;
											break;
										case 'owner':
											state.state.owner = egw.user('account_id');
											break;
										case 'filter':
											state.state.filter = 'default';
											break;
									}
								}
								break;
							}
							break;

						case 'view':
							// "No filter" (blank) favorite: if not in listview --> stay in that view
							if (state.state.favorite === 'blank' && current_state.view != 'listview')
							{
								menuaction = 'calendar.calendar_uiviews.index';
								delete state.state.ajax;
								need_redirect = true;
							}
					}
				}
				if (!need_redirect)
				{
					return super.setState([state]);
				}
			}
		}
		// setting internal state now, that linkHandler does not intercept switching from listview to any old view
		this.state = jQuery.extend({},state.state);
		if(this.sidebox_et2)
		{
			jQuery(this.sidebox_et2.getInstanceManager().DOMContainer).show();
		}

		var query = jQuery.extend({menuaction: menuaction},state.state||{});

		// prepend an owner 0, to reset all owners and not just set given resource type
		if(typeof query.owner != 'undefined')
		{
			query.owner = '0,'+ (typeof query.owner == 'object' ? query.owner.join(',') : (''+query.owner).replace('0,',''));
		}

		this.egw.open_link(this.egw.link('/index.php',query), 'calendar');

		// Stop the normal bubbling if this is called on click
		return false;
	}

	/**
	 * Check to see if any of the selected is an event widget
	 * Used to separate grid actions from event actions
	 *
	 * @param {egwAction} _action
	 * @param {egwActioObject[]} _selected
	 * @returns {boolean} Is any of the selected an event widget
	 */
	is_event(_action, _selected)
	{
		var is_widget = false;
		for(var i = 0; i < _selected.length; i++)
		{
			if(_selected[i].iface.getWidget() && _selected[i].iface.getWidget().instanceOf(et2_calendar_event))
			{
				is_widget = true;
			}

			// Also check classes, usually indicating permission
			if(_action.data && _action.data.enableClass)
			{
				is_widget = is_widget && (jQuery( _selected[i].iface.getDOMNode()).hasClass(_action.data.enableClass));
			}
			if(_action.data && _action.data.disableClass)
			{
				is_widget = is_widget && !(jQuery( _selected[i].iface.getDOMNode()).hasClass(_action.data.disableClass));
			}

		}
		return is_widget;
	}

	/**
	 * Enable/Disable custom Date-time for set Alarm
	 *
	 * @param {widget object} _widget new_alarm[options] selectbox
	 */
	alarm_custom_date (selectbox? : HTMLInputElement, _widget? : et2_selectbox)
	{
		var alarm_date = this.et2.getInputWidgetById('new_alarm[date]');
		var alarm_options = _widget || this.et2.getInputWidgetById('new_alarm[options]');
		var start = <et2_date>this.et2.getInputWidgetById('start');

		if (alarm_date && alarm_options && start)
		{
			if (alarm_options.getValue() != '0')
			{
				alarm_date.set_class('calendar_alarm_date_display');
			}
			else
			{
				alarm_date.set_class('');
			}
			var startDate = typeof start.getValue != 'undefined'?start.getValue():start.value;
			if (startDate)
			{
				var date = new Date(startDate);
				date.setTime(date.getTime() - 1000 * parseInt(alarm_options.getValue()));
				alarm_date.set_value(date);
			}
		}
	}

	/**
	 * Set alarm options based on WD/Regular event user preferences
	 * Gets fired by wholeday checkbox.  This is mainly for display purposes,
	 * the default alarm is calculated on the server as well.
	 *
	 * @param {egw object} _egw
	 * @param {widget object} _widget whole_day checkbox
	 */
	set_alarmOptions_WD (_egw,_widget)
	{
		var alarm = <et2_grid> this.et2.getWidgetById('alarm');
		if (!alarm) return;	// no default alarm
		var content = this.et2.getArrayMgr('content').data;
		var start = <et2_date> this.et2.getWidgetById('start');
		var self= this;
		var time = alarm.cells[1][0].widget;
		var event = alarm.cells[1][1].widget;
		// Convert a seconds of time to a translated label
		var _secs_to_label = function (_secs)
		{
			var label='';
			if (_secs < 3600)
			{
				label = self.egw.lang('%1 minutes', _secs/60);
			}
			else if(_secs < 86400)
			{
				label = self.egw.lang('%1 hours', _secs/3600);
			}
			else
			{
				label = self.egw.lang('%1 days', _secs/(3600*24));
			}
			return label;
		};
		if(content['alarm'] && typeof content['alarm'] && typeof content['alarm'][1]['default'] == 'undefined')
		{
			// user deleted alarm --> nothing to do
		}
		else
		{
			var def_alarm = this.egw.preference(_widget.get_value() === "true" ?
				'default-alarm-wholeday' : 'default-alarm', 'calendar');
			if (!def_alarm && def_alarm !== 0)	// no alarm
			{
				jQuery('#calendar-edit_alarm > tbody :nth-child(1)').hide();
			}
			else
			{
				jQuery('#calendar-edit_alarm > tbody :nth-child(1)').show();
				start.set_hours(0);
				start.set_minutes(0);
				time.set_value(start.get_value());
				time.set_value(new Date(new Date(start.get_value()).valueOf() - (60*def_alarm*1000)).toJSON());
				event.set_value(_secs_to_label(60 * def_alarm));
			}
		}
	}


	/**
	 * Clear all calendar data from egw.data cache
	 */
	_clear_cache( integration_app?:string )
	{
		// Full refresh, clear the caches
		var events = egw.dataKnownUIDs('calendar');
		for(var i = 0; i < events.length; i++)
		{
			let event_data = egw.dataGetUIDdata("calendar::" + events[i]).data || {app: "calendar"};
			if(!integration_app || integration_app && event_data && event_data.app === integration_app)
			{
				// Remove entry
				egw.dataStoreUID("calendar::" + events[i], null);
				// Delete from cache
				egw.dataDeleteUID('calendar::' + events[i]);
			}
		}
		// If just removing one app, leave the columns alone
		if(integration_app) return;

		var daywise = egw.dataKnownUIDs(CalendarApp.DAYWISE_CACHE_ID);
		for(var i = 0; i < daywise.length; i++)
		{
			// Empty to clear existing widgets
			egw.dataStoreUID(CalendarApp.DAYWISE_CACHE_ID + '::' + daywise[i], null);
		}
	}

	/**
	 * Take the date range(s) in the value and decide if we need to fetch data
	 * for the date ranges, or if they're already cached fill them in.
	 *
	 * @param {Object} value
	 * @param {Object} state
	 *
	 * @return {boolean} Data was requested
	 */
	_need_data(value, state)
	{
		var need_data = false;

		// Determine if we're showing multiple owners seperate or consolidated
		var seperate_owners = false;
		var last_owner = value.length ? value[0].owner || 0 : 0;
		for(var i = 0; i < value.length && !seperate_owners; i++)
		{
			seperate_owners = seperate_owners || (last_owner !== value[i].owner);
		}

		for(var i = 0; i < value.length; i++)
		{
			var t = new Date(value[i].start_date);
			var end = new Date(value[i].end_date);
			do
			{
				// Cache is by date (and owner, if seperate)
				var date = t.getUTCFullYear() + sprintf('%02d',t.getUTCMonth()+1) + sprintf('%02d',t.getUTCDate());
				var cache_id = CalendarApp._daywise_cache_id(date, seperate_owners && value[i].owner ? value[i].owner : state.owner||false);

				if(egw.dataHasUID(cache_id))
				{
					var c = egw.dataGetUIDdata(cache_id);
					if(c.data && c.data !== null)
					{
						// There is data, pass it along now
						value[i][date] = [];
						for(var j = 0; j < c.data.length; j++)
						{
							if(egw.dataHasUID('calendar::'+c.data[j]))
							{
								value[i][date].push(egw.dataGetUIDdata('calendar::'+c.data[j]).data);
							}
							else
							{
								need_data = true;
							}
						}
					}
					else
					{
						need_data = true;
						// Assume it's empty, if there is data it will be filled later
						egw.dataStoreUID(cache_id, []);
					}
				}
				else
				{
					need_data = true;
					// Assume it's empty, if there is data it will be filled later
					egw.dataStoreUID(cache_id, []);
				}
				t.setUTCDate(t.getUTCDate() + 1);
			}
			while(t < end);

			// Some data is missing for the current owner, go get it
			if(need_data && seperate_owners)
			{
				this._fetch_data(
					jQuery.extend({}, state, {owner: value[i].owner, selected_owners: state.owner}),
					this.sidebox_et2 ? null : this.et2.getInstanceManager()
				);
				need_data = false;
			}
		}

		// Some data was missing, go get it
		if(need_data && !seperate_owners)
		{
			this._fetch_data(
				state,
				this.sidebox_et2 ? null : this.et2.getInstanceManager()
			);
		}

		return need_data;
	}

	/**
	 * Use the egw.data system to get data from the calendar list for the
	 * selected time span.
	 *
	 * As long as the other filters are the same (category, owner, status) we
	 * cache the data.
	 *
	 * @param {Object} state
	 * @param {etemplate2} [instance] If the full calendar app isn't loaded
	 *	(home app), pass a different instance to use it to get the data
	 * @param {number} [start] Result offset.  Internal use only
	 * @param {string[]} [specific_ids] Only request the given IDs
	 */
	_fetch_data(state, instance, start?, specific_ids?)
	{
		if(!this.sidebox_et2 && !instance)
		{
			return;
		}

		if(typeof start === 'undefined')
		{
			start = 0;
		}

		// Category needs to be false if empty, not an empty array or string
		var cat_id = state.cat_id ? state.cat_id : false;
		if(cat_id && typeof cat_id.join != 'undefined')
		{
			if(cat_id.join('') == '') cat_id = false;
		}
		// Make sure cat_id reaches to server in array format
		if (cat_id && typeof cat_id == 'string' && cat_id != "0") cat_id = cat_id.split(',');

		var query = jQuery.extend({}, {
			get_rows: 'calendar.calendar_uilist.get_rows',
			row_id:'row_id',
			startdate:state.first ||  state.date,
			enddate:state.last,
			col_filter: {
				// Participant must be an array or it won't work
				participant: (typeof state.owner == 'string' || typeof state.owner == 'number' ? [state.owner] : state.owner),
				include_videocalls: state.include_videocalls
			},
			filter:'custom', // Must be custom to get start & end dates
			status_filter: state.status_filter,
			cat_id: cat_id,
			csv_export: false,
			selected_owners: state.selected_owners
		});
		// Show ajax loader
		if(typeof framework !== 'undefined')
		{
			framework.applications.calendar.sidemenuEntry.showAjaxLoader();
		}

		if(state.view === 'planner' && state.sortby === 'user')
		{
			query.order = 'participants';
		}
		else if (state.view === 'planner' && state.sortby === 'category')
		{
			query.order = 'categories';
		}

		// Already in progress?
		var query_string = JSON.stringify(query);
		if(this._queries_in_progress.indexOf(query_string) != -1)
		{
			return;
		}
		this._queries_in_progress.push(query_string);

		this.egw.dataFetch(
			instance ? instance.etemplate_exec_id :
				this.sidebox_et2.getInstanceManager().etemplate_exec_id,
			specific_ids ? {refresh: specific_ids} : {start: start, num_rows:400},
			query,
			this.appname,
			function calendar_handleResponse(data) {
				var idx = this._queries_in_progress.indexOf(query_string);
				if(idx >= 0)
				{
					this._queries_in_progress.splice(idx,1);
				}
				//console.log(data);

				// Look for any updated select options
				if(data.rows && data.rows.sel_options && this.sidebox_et2)
				{
					for(var field in data.rows.sel_options)
					{
						var widget = this.sidebox_et2.getWidgetById(field);
						if(widget && widget.set_select_options)
						{
							// Merge in new, update label of existing
							for(var i in data.rows.sel_options[field])
							{
								var found = false;
								var option = data.rows.sel_options[field][i];
								for(var j in widget.select_options)
								{
									if(option.value == widget.select_options[j].value)
									{
										widget.select_options[j].label = option.label;
										found = true;
										break;
									}
								}
								if(!found)
								{
									if(!widget.select_options.push)
									{
										widget.select_options = [];
									}
									widget.select_options.push(option);
								}
							}
							var in_progress = app.calendar.state_update_in_progress;
							app.calendar.state_update_in_progress = true;
							widget.set_select_options(widget.select_options);
							widget.set_value(widget.getValue());

							app.calendar.state_update_in_progress = in_progress;
						}
					}
				}

				if(data.order && data.total)
				{
					this._update_events(state, data.order);
				}

				// More rows?
				if(data.order.length + start < data.total)
				{
					// Wait a bit, let UI do something.
					window.setTimeout( function() {
						app.calendar._fetch_data(state, instance, start + data.order.length);
					}, 100);
				}
				// Hide AJAX loader
				else if(typeof framework !== 'undefined')
				{
					framework.applications.calendar.sidemenuEntry.hideAjaxLoader();
					egw.loading_prompt('calendar', false)

				}
			}, this, null
		);
	}

	/**
	 * We have a list of calendar UIDs of events that need updating.
	 * Public wrapper for _update_events so we can call it from server
	 */
	update_events(uids : string[])
	{
		return this._update_events(this.state, uids);
	}

	/**
	 * We have a list of calendar UIDs of events that need updating.
	 *
	 * The event data should already be in the egw.data cache, we just need to
	 * figure out where they need to go, and update the needed parent objects.
	 *
	 * Already existing events will have already been updated by egw.data
	 * callbacks.
	 *
	 * @param {Object} state Current state for update, used to determine what to update
	 * @param data
	 */
	_update_events( state, data)
	{
		var updated_days = {};

		// Events can span for longer than we are showing
		var first = new Date(state.first);
		var last = new Date(state.last);
		var bounds = {
			first: ''+first.getUTCFullYear() + sprintf('%02d',first.getUTCMonth()+1) + sprintf('%02d',first.getUTCDate()),
			last: ''+last.getUTCFullYear() + sprintf('%02d',last.getUTCMonth()+1) + sprintf('%02d',last.getUTCDate())
		};
		// Seperate owners, or consolidated?
		var multiple_owner = typeof state.owner != 'string' &&
			state.owner.length > 1 &&
			(state.view == 'day' && state.owner.length < parseInt(''+this.egw.preference('day_consolidate','calendar')) ||
			['week','day4'].indexOf(state.view) !== -1 && state.owner.length < parseInt(''+this.egw.preference('week_consolidate','calendar')));


		for(var i = 0; i < data.length; i++)
		{
			var record = this.egw.dataGetUIDdata(data[i]);
			if(record && record.data)
			{
				if(typeof updated_days[record.data.date] === 'undefined')
				{
					// Check to make sure it's in range first, record.data.date is start date
					// and could be before our start
					if(record.data.date >= bounds.first && record.data.date <= bounds.last ||
					// Or it's for a day we already have
						typeof this.egw.dataGetUIDdata('calendar_daywise::'+record.data.date) !== 'undefined'
					)
					{
						updated_days[record.data.date] = [];
					}
				}
				if(typeof updated_days[record.data.date] != 'undefined')
				{
					// Copy, to avoid unwanted changes by reference
					updated_days[record.data.date].push(record.data.row_id);
				}

				// Check for multi-day events listed once
				// Date must stay a string or we might cause problems with nextmatch
				var dates = {
					start: typeof record.data.start === 'string' ? record.data.start : record.data.start.toJSON(),
					end: typeof record.data.end === 'string' ? record.data.end : record.data.end.toJSON()
				};
				if(dates.start.substr(0,10) !== dates.end.substr(0,10))
				{
					var end = new Date(Math.min(new Date(record.data.end).valueOf(), new Date(state.last).valueOf()));
					end.setUTCHours(23);
					end.setUTCMinutes(59);
					end.setUTCSeconds(59);
					var t = new Date(Math.max(new Date(record.data.start).valueOf(), new Date(state.first).valueOf()));

					do
					{
						var expanded_date = ''+t.getUTCFullYear() + sprintf('%02d',t.getUTCMonth()+1) + sprintf('%02d',t.getUTCDate());

						// Avoid events ending at midnight having a 0 length event the next day
						if(t.toJSON().substr(0,10) === dates.end.substr(0,10) && dates.end.substr(11,8) === '00:00:00') break;

						if(typeof(updated_days[expanded_date]) === 'undefined')
						{
							// Check to make sure it's in range first, expanded_date could be after our end
							if(expanded_date >= bounds.first && expanded_date <= bounds.last)
							{
								updated_days[expanded_date] = [];
							}
						}
						if(record.data.date !== expanded_date && typeof updated_days[expanded_date] !== 'undefined')
						{
							// Copy, to avoid unwanted changes by reference
							updated_days[expanded_date].push(record.data.row_id);
						}
						t.setUTCDate(t.getUTCDate() + 1);
					}
					while(end >= t )
				}
			}
		}

		// Now we know which days changed, so we pass it on
		for(var day in updated_days)
		{
			// Might be split by user, so we have to check that too
			for(var i = 0; i < (typeof state.owner == 'object' ? state.owner.length : 1); i++)
			{
				var owner = multiple_owner ? state.owner[i] : state.owner;
				var cache_id = CalendarApp._daywise_cache_id(day, owner);
				if(egw.dataHasUID(cache_id))
				{
					// Don't lose any existing data, just append
					var c = egw.dataGetUIDdata(cache_id);
					if(c.data && c.data !== null)
					{
						// Avoid duplicates
						var data = c.data.concat(updated_days[day]).filter(function(value, index, self) {
							return self.indexOf(value) === index;
						});
						this.egw.dataStoreUID(cache_id,data);
					}
				}
				else
				{
					this.egw.dataStoreUID(cache_id, updated_days[day]);
				}
				if(!multiple_owner)
				{
					break;
				}
			}
		}

		egw.loading_prompt(this.appname,false);
	}

	/**
	 * Some handy date calculations
	 * All take either a Date object or full date with timestamp (Z)
	 */
	date = {
		toString: function(date) : string
		{
			// Ensure consistent formatting using UTC, avoids problems with comparison
			// and timezones
			if(typeof date === 'string') date = new Date(date);
			return date.getUTCFullYear() +'-'+
				sprintf("%02d",date.getUTCMonth()+1) + '-'+
				sprintf("%02d",date.getUTCDate()) + 'T'+
				sprintf("%02d",date.getUTCHours()) + ':'+
				sprintf("%02d",date.getUTCMinutes()) + ':'+
				sprintf("%02d",date.getUTCSeconds()) + 'Z';
		},

		/**
		* Formats one or two dates (range) as long date (full monthname), optionaly with a time
		*
		* Take care of any timezone issues before you pass the dates in.
		*
		* @param {Date} first first date
		* @param {Date} last =0 last date for range, or false for a single date
		* @param {boolean} display_time =false should a time be displayed too
		* @param {boolean} display_day =false should a day-name prefix the date, eg. monday June 20, 2006
		* @return string with formatted date
		*/
		long_date: function(first, last, display_time, display_day) : string
		{
			if(!first) return '';
			if(typeof first === 'string')
			{
				first = new Date(first);
			}
			var first_format = new Date(first.valueOf() + first.getTimezoneOffset() * 60 * 1000);

			if(typeof last == 'string' && last)
			{
				last = new Date(last);
			}
			if(!last || typeof last !== 'object')
			{
				 last = false;
			}
			if(last)
			{
				var last_format = new Date(last.valueOf() + last.getTimezoneOffset() * 60 * 1000);
			}

			if(!display_time)
			{
				display_time = false;
			}
			if(!display_day)
			{
				display_day = false;
			}

			var range = '';

			var datefmt = egw.preference('dateformat');
			var timefmt = egw.preference('timeformat') === '12' ? 'h:i a' : 'H:i';

			var month_before_day = datefmt[0].toLowerCase() == 'm' ||
				datefmt[2].toLowerCase() == 'm' && datefmt[4] == 'd';

			if (display_day)
			{
				range = flatpickr.formatDate(first_format, 'l') + (datefmt[0] != 'd' ? ' ' : ', ');
			}
			for (var i = 0; i < 5; i += 2)
			{
				switch(datefmt[i])
				{
					case 'd':
						range += first.getUTCDate()+ (datefmt[1] == '.' ? '.' : '');
						if (last && (first.getUTCMonth() != last.getUTCMonth() || first.getUTCFullYear() != last.getUTCFullYear()))
						{
							if (!month_before_day)
							{
								range += " " + egw.lang(flatpickr.formatDate(first_format, "F"));
							}
							if (first.getFullYear() != last.getFullYear() && datefmt[0] != 'Y')
							{
								range += (datefmt[0] != 'd' ? ', ' : ' ') + first.getFullYear();
							}
							if (display_time)
							{
								range += ' ' + formatTime(first_format);
							}
							if (!last)
							{
								return range;
							}
							range += ' - ';

							if (first.getFullYear() != last.getFullYear() && datefmt[0] == 'Y')
							{
								range += last.getUTCFullYear() + ', ';
							}

							if (month_before_day)
							{
								range += egw.lang(flatpickr.formatDate(last_format, 'l'));
							}
						}
						else if (last)
						{
							if (display_time)
							{
								range += ' ' + formatTime(last_format);
							}
							if(last)
							{
								range += ' - ';
							}
						}
						if(last)
						{
							range += ' ' + last.getUTCDate() + (datefmt[1] == '.' ? '.' : '');
						}
						break;
					case 'm':
					case 'M':
						range += ' ' + egw.lang(flatpickr.formatDate(month_before_day || !last ? first_format : last_format, "F")) + ' ';
						break;
					case 'Y':
						if (datefmt[0] != 'm')
						{
							range += ' ' + (datefmt[0] == 'Y' ? first.getUTCFullYear()+(datefmt[2] == 'd' ? ', ' : ' ') : last.getUTCFullYear()+' ');
						}
						break;
				}
			}
			if (display_time && last)
			{
				range += ' ' + formatTime(last_format);
			}
			if (datefmt[4] == 'Y' && datefmt[0] == 'm')
			{
				 range += ', ' + last.getUTCFullYear();
			}
			return range;
		},
		/**
		* Calculate iso8601 week-number, which is defined for Monday as first day of week only
		*
		* We adjust the day, if user prefs want a different week-start-day
		*
		* @param {string|Date} _date
		* @return string
		*/
		week_number: function(_date)
		{
			var d = new Date(_date);
			var day = d.getUTCDay();


			// if week does not start Monday and date is Sunday --> add one day
			if (egw.preference('weekdaystarts','calendar') != 'Monday' && !day)
			{
				d.setUTCDate(d.getUTCDate() + 1);
			}
			// if week does start Saturday and $time is Saturday --> add two days
			else if (egw.preference('weekdaystarts','calendar') == 'Saturday' && day == 6)
			{
				d.setUTCDate(d.getUTCDate() + 2);
			}

			return flatpickr.formatDate(new Date(d.valueOf() + d.getTimezoneOffset() * 60 * 1000), "W");
		},
		start_of_week: function(date)
		{
			var d = new Date(date);
			var day = d.getUTCDay();
			var diff = 0;
			switch(egw.preference('weekdaystarts','calendar'))
			{
				case 'Saturday':
					diff = day === 6 ? 0 : day === 0 ? -1 : -(day + 1);
					break;
				case 'Monday':
					diff = day === 0 ? -6 : 1-day;
					break;
				case 'Sunday':
				default:
					diff = -day;
			}
			d.setUTCDate(d.getUTCDate() + diff);
			return d;
		},
		end_of_week: function(date)
		{
			var d = this.start_of_week(date);
			d.setUTCDate(d.getUTCDate() + 6);
			return d;
		}
	};

	/**
	 * The sidebox filters use some non-standard and not-exposed options.  They
	 * are set up here.
	 *
	 */
	_setup_sidebox_filters()
	{

	}

	/**
	 * Record view templates so we can quickly switch between them.
	 *
	 * @param {etemplate2} _et2 etemplate2 template that was just loaded
	 * @param {String} _name Name of the template
	 */
	_et2_view_init(_et2, _name)
	{
		var hidden = typeof this.state.view !== 'undefined';
		var all_loaded = this.sidebox_et2 !== null;

		// Avoid home portlets using our templates, and get them right
		if(_et2.uniqueId.indexOf('portlet') === 0) return;
		if(_et2.uniqueId === 'calendar-add') return;

		// Flag to make sure we don't hide non-view templates
		var view_et2 = false;

		for(var view in CalendarApp.views)
		{
			var index = CalendarApp.views[view].etemplates.indexOf(_name);
			if(index > -1)
			{
				view_et2 = true;
				CalendarApp.views[view].etemplates[index] = _et2;
				// If a template disappears, we want to release it
				jQuery(_et2.DOMContainer).one('clear',jQuery.proxy(function() {
					this.view.etemplates[this.index] = _name;
				},jQuery.extend({},{view: CalendarApp.views[view], index: ""+index, name: _name})));

				if(this.state.view === view)
				{
					hidden = false;
				}
			}
			CalendarApp.views[view].etemplates.forEach(function(et) {all_loaded = all_loaded && typeof et !== 'string';});
		}

		// Add some extras to the nextmatch so it can keep the dates in sync with
		// those in the sidebox calendar.  Care must be taken to not trigger any
		// sort of refresh or update, as that may resulte in infinite loops so these
		// are only used for the 'week' and 'month' filters, and we just update the
		// date range
		if(_name == 'calendar.list')
		{
			var nm = _et2.widgetContainer.getWidgetById('nm');
			if(nm)
			{
				// Avoid unwanted refresh immediately after load
				nm.controller._grid.doInvalidate = false;

				// Preserve pre-set search
				if(nm.activeFilters.search)
				{
					this.state.keywords = nm.activeFilters.search;
				}
				// Bind to keep search up to date
				jQuery(nm.getWidgetById('search').getDOMNode()).on('change', function() {
					app.calendar.state.search = jQuery('input',this).val();
				});
				nm.set_startdate = jQuery.proxy(function(date) {
					this.state.first = this.date.toString(new Date(date));
				},this);
				nm.set_enddate = jQuery.proxy(function(date) {
					this.state.last = this.date.toString(new Date(date));
				},this);
			}
		}

		// Start hidden, except for current view
		if(view_et2)
		{
			if(hidden)
			{
				jQuery(_et2.DOMContainer).hide();
			}
		}
		else
		{
			var app_name = _name.split('.')[0];
			if(app_name && app_name != 'calendar' && egw.app(app_name))
			{
				// A template from another application?  Keep it up to date as state changes
				this.sidebox_hooked_templates.push(_et2.widgetContainer);
				// If it leaves (or reloads) remove it
				jQuery(_et2.DOMContainer).one('clear',jQuery.proxy(function() {
					if(app.calendar)
					{
						app.calendar.sidebox_hooked_templates.splice(this,1,0);
					}
				},this.sidebox_hooked_templates.length -1));
			}
		}
		if(all_loaded)
		{
			jQuery(window).trigger('resize');
			this.setState({state:this.state});

			// Hide loader after 1 second as a fallback, it will also be hidden
			// after loading is complete.
			window.setTimeout(jQuery.proxy(function() {
				egw.loading_prompt(this.appname,false);
			}, this),1000);

			// Start calendar-wide autorefresh timer to include more than just nm
			this._set_autorefresh();
		}
	}

	/**
	 * Set a refresh timer that works for the current view.
	 * The nextmatch goes into an infinite loop if we let it autorefresh while
	 * hidden.
	 */
	_set_autorefresh( )
	{
		// Listview not loaded
		if(typeof CalendarApp.views.listview.etemplates[0] == 'string') return;

		var nm = <et2_nextmatch> CalendarApp.views.listview.etemplates[0].widgetContainer.getWidgetById('nm');
		// nextmatch missing
		if(!nm) return;

		var refresh_preference = "nextmatch-" + nm.options.settings.columnselection_pref + "-autorefresh";
		var time = this.egw.preference(refresh_preference, 'calendar');

		if(this.state.view == 'listview' && time)
		{
			nm._set_autorefresh(time);
			return;
		}
		else
		{
			nm._set_autorefresh(0);
		}
		var self = this;
		var refresh = function() {
			// Deleted events are not coming properly, so clear it all
			self._clear_cache();
			// Force redraw to current state
			self.setState({state: self.state});

			// This is a fast update, but misses deleted events
			//app.calendar._fetch_data(app.calendar.state);
		};

		// Start / update timer
		if (this._autorefresh_timer)
		{
			window.clearInterval(this._autorefresh_timer);
			this._autorefresh_timer = null;
		}
		if(time > 0)
		{
			this._autorefresh_timer = setInterval(jQuery.proxy(refresh, this), time * 1000);
		}

		// Bind to tab show/hide events, so that we don't bother refreshing in the background
		jQuery(nm.getInstanceManager().DOMContainer.parentNode).on('hide.calendar', jQuery.proxy(function(e) {
			// Stop
			window.clearInterval(this._autorefresh_timer);
			jQuery(e.target).off(e);

			if(!time) return;

			// If the autorefresh time is up, bind once to trigger a refresh
			// (if needed) when tab is activated again
			this._autorefresh_timer = setTimeout(jQuery.proxy(function() {
				// Check in case it was stopped / destroyed since
				if(!this._autorefresh_timer) return;

				jQuery(nm.getInstanceManager().DOMContainer.parentNode).one('show.calendar',
					// Important to use anonymous function instead of just 'this.refresh' because
					// of the parameters passed
					jQuery.proxy(function() {refresh();},this)
				);
			},this), time*1000);
		},this));
		jQuery(nm.getInstanceManager().DOMContainer.parentNode).on('show.calendar', jQuery.proxy(function(e) {
			// Start normal autorefresh timer again
			this._set_autorefresh(this.egw.preference(refresh_preference, 'calendar'));
			jQuery(e.target).off(e);
		},this));
	}

	/**
	 * Initialization function in order to set/unset
	 * categories status.
	 *
	 */
	category_report_init ()
	{
		var content = this.et2.getArrayMgr('content').data;
		for (var i=1;i<content.grid.length;i++)
		{
			if (content.grid[i] != null) this.category_report_enable({id:i+'', checked:content.grid[i]['enable']});
		}
	}

	/**
	 * Set/unset selected category's row
	 *
	 * @param {type} _widget
	 * @returns {undefined}
	 */
	category_report_enable (_widget)
	{
		var widgets = ['[user]','[weekend]','[holidays]','[min_days]'];
		var row_id = _widget.id.match(/\d+/);
		var w = {};
		for (var i=0;i<widgets.length;i++)
		{
			w = this.et2.getWidgetById(row_id+widgets[i]);
			if (w) w.set_readonly(!_widget.checked);
		}
	}

	/**
	 * submit function for report button
	 */
	category_report_submit ()
	{
		this.et2._inst.postSubmit();
	}

	/**
	 * Function to enable/disable categories
	 *
	 * @param {object} _widget select all checkbox
	 */
	category_report_selectAll (_widget)
	{
		var content = this.et2.getArrayMgr('content').data;
		var checkbox = null;
		var grid_index = typeof content.grid.length !='undefined'? content.grid : Object.keys(content.grid);
		for (var i=1;i< grid_index.length;i++)
		{
			if (content.grid[i] != null)
			{
				checkbox = <et2_checkbox> this.et2.getWidgetById(i+'[enable]');
				if (checkbox)
				{
					checkbox.set_value(_widget.checked);
					this.category_report_enable({id:checkbox.id, checked:checkbox.get_value()});
				}
			}
		}
	}

	/**
	 * Create a cache ID for the daywise cache
	 *
	 * @param {String|Date} date If a string, date should be in Ymd format
	 * @param {String|integer|String[]} owner
	 * @returns {String} Cache ID
	 */
	public static _daywise_cache_id(date, owner)
	{
		if(typeof date === 'object')
		{
			date =  date.getUTCFullYear() + sprintf('%02d',date.getUTCMonth()+1) + sprintf('%02d',date.getUTCDate());
		}

		// If the owner is not set, 0, or the current user, don't bother adding it
		var _owner = (owner && owner.toString() != '0') ? owner.toString() : '';
		if(_owner == egw.user('account_id'))
		{
			_owner = '';
		}
		return CalendarApp.DAYWISE_CACHE_ID+'::'+date+(_owner ? '-' + _owner : '');
	}

	/**
	 * Videoconference checkbox checked
	 */
	public videoconferenceOnChange(event)
	{
		let widget = <et2_checkbox> this.et2.getWidgetById('videoconference');

		if (widget && widget.get_value())
		{
			// notify all participants
			(<et2_selectbox> this.et2.getWidgetById('participants[notify_externals]')).set_value('yes');

			// add alarm for all participants 5min before videoconference
			let start = new Date(widget.getRoot().getValueById('start'));
			let alarms = this.et2.getArrayMgr('content').getEntry('alarm') || {};
			for(let alarm of alarms)
			{
				// Check for already existing alarm
				if(!alarm || typeof alarm != "object" || !alarm.all) continue;
				let alarm_time = new Date(alarm.time);
				if(start.getTime() - alarm_time.getTime() == 5 * 60 * 1000)
				{
					// Alarm exists
					return;
				}
			}
			(<et2_selectbox> this.et2.getWidgetById('new_alarm[options]')).set_value('300');
			(<et2_selectbox> this.et2.getWidgetById('new_alarm[owner]')).set_value('0');	// all participants
			(<et2_button> this.et2.getWidgetById('button[add_alarm]')).click(event);
		}
	}

	public isVideoConference(_action, _selected)
	{
		let data = egw.dataGetUIDdata(_selected[0].id);
		return data && data.data ? data.data['##videoconference'] : false;
	}

	/**
	 * Action handler for join videoconference context menu
	 *
	 * @param _action
	 * @param _sender
	 */
	public videoConferenceAction(_action : egwAction, _sender : egwActionObject[])
	{
		let data = egw.dataGetUIDdata(_sender[0].id)['data'];
		switch(_action.id)
		{
			case 'recordings':
				app.status.videoconference_getRecordings(data['##videoconference'],{cal_id: data['id'], title:data['title']});
				break;
			case 'join':
				return this.joinVideoConference(data['##videoconference'], data);
		}
	}

	/**
	 * Join a videoconference
	 *
	 * Using the videoconference tag/ID, generate the URL and open it via JSON
	 *
	 * @param {string} videoconference
	 */
	public joinVideoConference(videoconference, _data)
	{
		return egw.json(
			"EGroupware\\Status\\Videoconference\\Call::ajax_genMeetingUrl",
			[videoconference,
				{
					name:egw.user('account_fullname'),
					account_id:egw.user('account_id'),
					email:egw.user('account_email'),
					cal_id:_data.id,
					title: _data.title
				},
			    // Dates are user time, but we told javascript it was UTC.
			    // Send just the timestamp (as a string) with no timezone
				( typeof _data.start != "string" ? _data.start.toJSON() : _data.start).slice(0,-1),
                ( typeof _data.end != "string" ? _data.end.toJSON() : _data.end).slice(0,-1),
				{
					participants:Object.keys(_data.participants).filter(v => {return v.match(/^[0-9|e|c]/)})
				}], function(_value){
					if (_value)
					{
						if (_value.err) egw.message(_value.err, 'error');
						if(_value.url) egw.top.app.status.openCall(_value.url);
					}
			}).sendRequest();
	}
}

if(typeof app.classes.calendar == "undefined")
{
	app.classes.calendar = CalendarApp;
}