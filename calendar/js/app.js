/**
 * EGroupware - Calendar - Javascript UI
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Hadi Nategh	<hn-AT-stylite.de>
 * @copyright (c) 2008-13 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/*egw:uses
	/etemplate/js/etemplate2.js;
	/calendar/js/et2_widget_timegrid.js;
	/calendar/js/et2_widget_planner.js;
*/

/**
 * UI for calendar
 *
 * @augments AppJS
 */
app.classes.calendar = AppJS.extend(
{
	/**
	 * application name
	 */
	appname: 'calendar',

	/**
	 * etemplate for the sidebox filters
	 */
	sidebox_et2: null,

	/**
	 * etemplates and settings for the different views some (day view)
	 * use more than one template, some use the same template as others,
	 * most need different handling for their various attributes.
	 * 
	 * Attributes are setter: function to calculate value
	 */
	views: {
		day: {
			etemplates: ['calendar.view','calendar.todo'],
			set_start_date: function(state) {
				return state.date ? new Date(state.date) : new Date();
			},
			set_end_date: function(state) {
				var d =  state.date ? new Date(state.date) : new Date();
				d.setUTCHours(23);
				return d;
			},
			set_owner: function(state) {
				return state.owner || 0;
			},
			set_show_weekend: function(state)
			{
				state.days = '1';
				return parseInt(egw.preference('days_in_weekview','calendar')) == 7;
			}
		},
		day4: {
			etemplates: ['calendar.view'],
			set_start_date: function(state) {
				return state.date ? new Date(state.date) : new Date();
			},
			set_end_date: function(state) {
				var d = state.date ? new Date(state.date) : new Date();
				d.setUTCHours(24*4-1);
				return d;
			},
			set_owner: function(state) {
				return state.owner || 0;
			},
			set_show_weekend: function(state)
			{
				state.days = '4';
				return parseInt(egw.preference('days_in_weekview','calendar')) == 7;
			}
		},
		week: {
			etemplates: ['calendar.view'],
			set_start_date: function(state) {
				return app.calendar.date.start_of_week(state.date || new Date());
			},
			set_end_date: function(state) {
				var d = app.calendar.date.start_of_week(state.date || new Date());
				// Always 7 days, we just turn weekends on or off
				d.setUTCHours(24*7-1);
				return d;
			},
			set_owner: function(state) {
				return state.owner || 0;
			},
			set_show_weekend: function(state)
			{
				state.days = '' + (state.days >= 5 ? state.days : egw.preference('days_in_weekview','calendar') || 7);
				return parseInt(state.days) == 7;
			}
		},
		weekN: {
			etemplates: ['calendar.view'],
			set_start_date: function(state) {
				return app.calendar.date.start_of_week(state.date || new Date());
			},
			set_end_date: function(state) {
				var d = app.calendar.date.start_of_week(state.date || new Date());
				// Always 7 days, we just turn weekends on or off
				d.setUTCHours(24*7-1);
				return d;
			},
			set_show_weekend: function(state)
			{
				state.days = '' + (state.days >= 5 ? state.days : egw.preference('days_in_weekview','calendar') || 7);
				return parseInt(state.days) == 7;
			}
		},
		month: {
			etemplates: ['calendar.view'],
			set_start_date: function(state) {
				var d = state.date ? new Date(state.date) : new Date();
				d.setUTCDate(1);
				d.setUTCHours(0);
				d.setUTCMinutes(0);
				d.setUTCSeconds(0);
				state.date = d.toJSON();
				return app.calendar.date.start_of_week(d);
			},
			set_end_date: function(state) {
				var d = state.date ? new Date(state.date) : new Date();
				d = new Date(d.getFullYear(),d.getUTCMonth() + 1, 0);
				var week_start = app.calendar.date.start_of_week(d);
				if(week_start < d) week_start.setUTCHours(24*7);
				week_start.setUTCHours(week_start.getUTCHours()-1);
				return week_start;
			},
		},
		
		planner: {
			etemplates: ['calendar.planner'],
			set_group_by: function(state) {
				return state.cat_id? state.cat_id : (state.sortby ? state.sortby : 0);
			},
			set_start_date: function(state) {
				var d = state.date ? new Date(state.date) : new Date();
				if(state.sortby && state.sortby === 'month')
				{
					d.setUTCDate(1);
				}
				else if (!state.planner_days)
				{
					if(d.getUTCDate() < 15)
					{
						d.setUTCDate(1);
						return app.calendar.date.start_of_week(d);
					}
					else
					{
						return app.calendar.date.start_of_week(d);
					}
				}
				return d;
			},
			set_end_date: function(state) {
				var d = state.date ? new Date(state.date) : new Date();
				if(state.sortby && state.sortby === 'month')
				{
					d.setUTCDate(0);
					d.setUTCFullYear(d.getUTCFullYear() + 1);
				}
				else if (state.planner_days)
				{
					d.setUTCDate(d.getUTCDate() + parseInt(state.planner_days)-1);
				}
				else if (app.calendar.state.last)
				{
					d = new Date(app.calendar.state.last);
				}
				else if (!state.planner_days)
				{
					if (d.getUTCDate() < 15)
					{
						d.setUTCDate(0);
						d.setUTCMonth(d.getUTCMonth()+1);
						d = app.calendar.date.end_of_week(d);
					}
					else
					{
						d.setUTCMonth(d.getUTCMonth()+1);
						d = app.calendar.date.end_of_week(d);
					}
				}
				return d;
			},
			set_owner: function(state) {
				return state.owner || 0;
			}
		},
		
		listview: {
			etemplates: ['calendar.list'],
			set_start_date: function(state)
			{
				var d = state.date ? new Date(state.date) : new Date();
				return d;
			}
		}
	},

	/**
	 * Current internal state
	 */
	state: {
		date: new Date(),
		view: egw.preference('defaultcalendar','calendar') || 'day',
		owner: egw.user('account_id'),
		days: egw.preference('days_in_weekview','calendar')
	},

	/**
	 * Constructor
	 *
	 * @memberOf app.calendar
	 */
	init: function()
	{
		// make calendar object available, even if not running in top window, as sidebox does
		if (window.top !== window && !egw(window).is_popup() && window.top.app.calendar)
		{
			window.app.calendar = window.top.app.calendar;
			return;
		}

		// call parent
		this._super.apply(this, arguments);

		//Drag_n_Drop (need to wait for DOM ready to init dnd)
		jQuery(jQuery.proxy(this.drag_n_drop,this));

		// Scroll
		jQuery(jQuery.proxy(this._scroll,this));
	},

	/**
	 * Destructor
	 */
	destroy: function()
	{
		// call parent
		this._super.apply(this, arguments);

		// remove top window reference
		if (window.top !== window && window.top.app.calendar === this)
		{
			delete window.top.app.calendar;
		}
		jQuery(egw_getFramework().applications.calendar.tab.contentDiv).off();
	},

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().
	 *
	 * @param {etemplate2} _et2 newly ready et2 object
	 * @param {string} _name name of template
	 */
	et2_ready: function(_et2, _name)
	{
		// call parent
		this._super.apply(this, arguments);

		// Re-init sidebox, since it was probably initialized too soon
		var sidebox = jQuery('#favorite_sidebox_'+this.appname);
		if(sidebox.length == 0 && egw_getFramework() != null)
		{
			var egw_fw = egw_getFramework();
			sidebox= $j('#favorite_sidebox_'+this.appname,egw_fw.sidemenuDiv);
		}
		this._init_sidebox(sidebox);

		var content = this.et2.getArrayMgr('content');

		switch (_name)
		{
			case 'calendar.sidebox':
				this.sidebox_et2 = _et2.widgetContainer;
				$j(_et2.DOMContainer).hide();
				this._setup_sidebox_filters();
				break;
			
			case 'calendar.edit':
				if (typeof content.data['conflicts'] == 'undefined')
				{
					$j(document.getElementById('calendar-edit_calendar-delete_series')).hide();
					//Check if it's fallback from conflict window or it's from edit window
					if (content.data['button_was'] != 'freetime')
					{
						this.set_enddate_visibility();
						this.check_recur_type();
						this.et2.getWidgetById('recur_exception').set_disabled(!content.data.recur_exception ||
							typeof content.data.recur_exception[0] == 'undefined');
					}
					else
					{
						this.freetime_search();
					}
					//send Syncronus ajax request to the server to unlock the on close entry
					//set onbeforeunload with json request to send request when the window gets close by X button
					window.onbeforeunload = function () {
						this.egw.json('calendar.calendar_uiforms.ajax_unlock'
						, [content.data['id'],content.data['lock_token']],null,true,null,null).sendRequest(true);
					};
				}
				this.alarm_custom_date();
				break;

			case 'calendar.freetimesearch':
				this.set_enddate_visibility();
				break;
			case 'home.legacy':
				break;
			case 'calendar.list':
				this.filter_change();
				// Fall through
			default:
				var hidden = typeof this.state.view !== 'undefined';
				var all_loaded = true;
				// Record the templates for the views so we can switch between them
				for(var view in this.views)
				{
					var index = this.views[view].etemplates.indexOf(_name)
					if(index > -1)
					{
						this.views[view].etemplates[index] = _et2;
						// If a template disappears, we want to release it
						$j(_et2.DOMContainer).one('clear',jQuery.proxy(function() {
							this.view[index] = _name;
						},{view: this.views[view], index: index, name: _name}));
					
						if(this.state.view === view)
						{
							hidden = false;
						}
					}
					this.views[view].etemplates.forEach(function(et) {all_loaded = all_loaded && typeof et !== 'string';});
				}
				
				// Start hidden, except for current view
				if(hidden)
				{
					$j(_et2.DOMContainer).hide();
				}
				if(all_loaded)
				{
					this.setState({state:this.state});
				}

		}
	},

	/**
	 * Observer method receives update notifications from all applications
	 *
	 * App is responsible for only reacting to "messages" it is interested in!
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
	observer: function(_msg, _app, _id, _type, _msg_type, _links)
	{
		var do_refresh = false;
		switch(_app)
		{
			case 'infolog':
			{
				jQuery('.calendar_calDayTodos')
					.find('a')
					.each(function(i,a){
						var match = a.href.split(/&info_id=/);
						if (match && typeof match[1] !="undefined")
						{
							if (match[1]== _id)	do_refresh = true;
						}
					});
				if (jQuery('div [id^="infolog'+_id+'"],div [id^="drag_infolog'+_id+'"]').length > 0) do_refresh = true;
				switch (_type)
				{
					case 'add':
						do_refresh = true;
						break;
				}
				if (do_refresh)
				{
					if (typeof this.et2 != 'undefined' && this.et2 !=null)
					{
						this.egw.refresh(_msg, 'calendar');
					}
					else
					{
						var iframe = parent.jQuery(parent.document).find('.egw_fw_content_browser_iframe');
						var calTab = iframe.parentsUntil(jQuery('.egw_fw_ui_tab_content'),'.egw_fw_ui_tab_content');

						if (!calTab.is(':visible'))
						{
							// F.F can not handle to style correctly an iframe which is hidden (display:none), therefore we need to
							// bind a handler to refresh the calendar views after it shows up
							iframe.one('show',function(){egw_refresh('','calendar');});
						}
						else
						{
							//window.location.reload();
							window.egw_refresh('refreshing calendar','calendar');
						}
					}
				}
			}
			break;
		}
	},

	/**
	 * Link hander for jDots template to just reload our iframe, instead of reloading whole admin app
	 *
	 * @param {String} _url
	 * @return {boolean|string} true, if linkHandler took care of link, false for default processing or url to navigate to
	 */
	linkHandler: function(_url)
	{
		if (_url.match('menuaction=calendar.calendar_uiviews.index'))
		{
			var state = this.getState();
			if (state.view == 'listview')
			{
				return _url.replace(/menuaction=[^&]+/, 'menuaction=calendar.calendar_uilist.listview&ajax=true');
			}
			else if (this.sidebox_et2 && typeof this.views[state.view] == 'undefined')
			{
				this.sidebox_et2.getWidgetById('iframe').set_src(_url);
				this.sidebox
				return true;
			}
		}
		else if (_url.indexOf('menuaction=calendar.calendar_uiviews') >= 0)
		{
			this.sidebox_et2.getWidgetById('iframe').set_src(_url);
			return true;
		}
		// can not load our own index page, has to be done by framework
		return false;
	},

	/**
	 * Drag and Drop
	 *
	 *
	 */
	drag_n_drop: function()
	{
		var that = this;
		
		//jQuery Calendar Event selector
		var $iframeBody = jQuery("body")
			//mouseover event handler for calendar tooltip
			.on("mouseover", "div[data-tooltip]",function(){
				var $ttp = jQuery(this);
				//Check if the tooltip is already initialized
				if (!$ttp.data('uiTooltip'))
				{
					$ttp.tooltip({
						items: "[data-tooltip]",
						show: false,
						content: function()
						{
							var elem = $ttp;
							if (elem.is("[data-tooltip]"))
								return this.getAttribute('data-tooltip') ;
						},
						track:true,

						open: function(event,ui){
							ui.tooltip.removeClass("ui-tooltip");
							ui.tooltip.addClass("calendar_uitooltip");
							if (this.scrollHeight > this.clientHeight)
							{
								// bind on tooltip close event
								$ttp.on("tooltipclose", function (event, ui){
									// bind hover handler on tooltip helper in order to be able to freeze the tooltip and scrolling
									ui.tooltip.hover(
										function () {
											var $ttp_helper = jQuery(this);
											if (this.scrollHeight > this.clientHeight)	$ttp_helper.stop(true).fadeTo(100, 1);
										},
										function () {
											var $ttp_helper = jQuery(this);
											$ttp_helper.fadeOut("100", function(){$ttp_helper.remove();});
										}
									);
								});
							}
						}
					});
				}
				else
				{
					$ttp.tooltip('enable');
				}
			})

			// mousedown event handler for calendar tooltip to remove disable tooltip
			.on("mousedown", "div[data-tooltip]", function(){
				var $ttp = jQuery(this);
				// Make sure the tooltip initialized before calling it
				if ($ttp.data('uiTooltip'))
				{
					$ttp.tooltip("disable");
				}
			})

			//Click event handler for integrated apps
			.on("click","div.calendar_plannerEvent",function(ev){
				var eventId = ev.currentTarget.getAttribute('data-date').split("|")[1];
				var startDate = ev.currentTarget.getAttribute('data-date').split("|")[0];
				var recurrFlag = ev.currentTarget.getAttribute('data-date').split("|")[2];
				if (recurrFlag == "n")
				{
					egw.open(eventId,'calendar','edit');
				}
				else
				{
					that.edit_series(eventId,startDate);
				}
			})
	},

	/**
	 * Setup and handle sortable calendars.
	 *
	 * You can only sort calendars if there is more than one owner, and the calendars
	 * are not combined (many owners, multi-week or month views)
	 * @returns {undefined}
	 */
	_sortable: function() {
		// Calender current state
		var state = this.getState();

		var sortable = jQuery('#calendar-view_view tbody');
		if(!sortable.sortable('instance'));
		{
			jQuery('#calendar-view_view tbody').sortable({
				cancel: "#divAppboxHeader, .calendar_calWeekNavHeader, .calendar_plannerHeader",
				handle: '.calendar_calGridHeader',
				//placeholder: "srotable_cal_wk_ph",
				axis:"y",
				revert: true,
				helper:"clone",
				create: function ()
				{
					var $sortItem = jQuery(this);
					var options = {};
					switch (state.view)
					{
						case "day":
							options = {
								placeholder:"srotable_cal_day_ph",
								axis:"x"
							};
							$sortItem.sortable('option', options);
							break;
						case "week":
							options = {
								placeholder:"srotable_cal_wk_ph",
								axis:"y"
							};
							$sortItem.sortable('option', options);
							break;
					}
				},
				start: function ()
				{
					// Put owners into row IDs
					app.calendar.views[state.view].etemplates[0].widgetContainer.iterateOver(function(widget) {
						widget.div.parents('tr').attr('data-owner',widget.options.owner);
					},this,et2_calendar_timegrid)
				},
				stop: function ()
				{
				},
				update: function ()
				{
					if (state && typeof state.owner !== 'undefined')
					{
						var sortedArr = sortable.sortable('toArray', {attribute:"data-owner"});
						// Directly update, since there is no other changes needed,
						// and we don't want the current sort order applied
						app.calendar.state.owner = sortedArr;
					}
				}
			});
		}

		// Enable or disable
		if(state.view == 'weekN' || state.view === 'month' || state.owner.length == 1 || state.owner.length > egw.config('calview_no_consolidate','phpgwapi'))
		{
			sortable.sortable('disable');
		}
		else
		{
			sortable.sortable('enable');
		}
	},

	/**
	 * Bind scroll event
	 * When the user scrolls, we'll move enddate - startdate days
	 */
	_scroll: function() {
		// Bind only once, to the whole tab
		jQuery(egw_getFramework().applications.calendar.tab.contentDiv)
			.on('wheel','.et2_container:not(#calendar-list)',
				function(e)
				{
					e.preventDefault();
					var direction = e.originalEvent.deltaY > 0 ? 1 : -1;
					var delta = 1;
					var start = new Date(app.calendar.state.date);
					var end = null;

					// Get the view to calculate
					if (app.calendar.views && app.calendar.state.view && app.calendar.views[app.calendar.state.view].set_end_date)
					{
						if(direction > 0)
						{
							start = app.calendar.views[app.calendar.state.view].set_end_date({date:start});
						}
						else
						{
							start = app.calendar.views[app.calendar.state.view].set_start_date({date:start});
						}
						start.setUTCDate(start.getUTCDate()+direction);
						end = app.calendar.views[app.calendar.state.view].set_end_date({date:start});
					}
					// Calculate the current difference, and move
					else if(app.calendar.state.first && app.calendar.state.last)
					{
						start = new Date(app.calendar.state.first);
						end = new Date(app.calendar.state.last);
						// Get the number of days
						delta = (Math.round(Math.max(1,end - start)/(24*3600*1000)))*24*3600*1000
						// Adjust
						start = new Date(start.valueOf() + (delta * direction ));
						end = new Date(end.valueOf() + (delta * direction));
					}
					
					app.calendar.update_state({date: start});

					return false;
				}
			);
	},

	/**
	 * Function to help calendar resizable event, to fetch the right droppable cell
	 *
	 * @param {int} _X position left of draggable element
	 * @param {int} _Y position top of draggable element
	 *
	 * @return {jquery object|boolean} return selected jquery if not return false
	 */
	resizeHelper: function(_X,_Y)
	{
		var $drops = jQuery("div[id^='drop_']");
		var top = Math.round(_Y);
		var left = Math.round(_X);
		for (var i=0;i < $drops.length;i++)
		{
			if (top >= Math.round($drops[i].getBoundingClientRect().top)
					&& top <= Math.round($drops[i].getBoundingClientRect().bottom)
					&& left >= Math.round($drops[i].getBoundingClientRect().left)
					&& left <= Math.round($drops[i].getBoundingClientRect().right))
				return $drops[i];
		}
		return false;
	},

	/**
	 * Convert AM/PM dateTime format to 24h
	 *
	 * @param {string} _date dnd date format: dateTtime{am|pm}, eg. 121214T1205 am
	 *
	 * @return {string} 24h format date
	 */
	cal_dnd_tZone_converter : function (_date)
	{
		var date = _date;
		if (_date !='undefined')
		{
			var tZone = _date.split('T')[1];
			if (tZone.search('am') > 0)
			{
				tZone = tZone.replace(' am','');
				var tAm = tZone.substr(0,2);
				if (tAm == '12')
				{
					tZone = tZone.replace('12','00');
				}
				date = _date.split('T')[0] + 'T' + tZone;
			}
			if (tZone.search('pm') > 0)
			{
				var pmTime = tZone.replace(' pm','');
				var H = parseInt(pmTime.substring(0,2)) + 12;
				pmTime = H.toString() + pmTime.substr(2,2);
				date = _date.split('T')[0] + 'T' + pmTime;
			}

		}
		return date;
	},

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
	event_change: function(event, widget, dialog_button)
	{
		egw().json(
			'calendar.calendar_uiforms.ajax_moveEvent',
			[widget.id, widget.options.value.owner, widget.options.value.start, widget.options.value.owner, widget.options.value.duration]
		).sendRequest();
	},

	/**
	 * This function tries to recognise the type of dropped event, and sends relative request to server accordingly
	 *	-ATM we have three different requests:
	 *		-1. Event part of series
	 *		-2. Single Event (Normall Cal Event)
	 *		-3. Integrated Infolog Event
	 *
	 * @param {string} _id dragged event id
	 * @param {array} _date array of date,hour, and minute of dropped cell
	 * @param {string} _duration description
	 * @param {string} _eventFlag Flag to distinguish whether the event is Whole Day, Series, or Single
	 *	- S represents Series
	 *	- WD represents Whole Day
	 *	- WDS represents Whole Day Series (recurrent whole day event)
	 *	- '' represents Single
	 */
	dropEvent : function(_id, _date, _duration, _eventFlag)
	{
		var eventId = _id.substring(_id.lastIndexOf("drag_")+5,_id.lastIndexOf("_O"));
		var calOwner = _id.substring(_id.lastIndexOf("_O")+2,_id.lastIndexOf("_C"));
		var eventOwner = _id.substring(_id.lastIndexOf("_C")+2,_id.lastIndexOf(""));
		var date = this.cal_dnd_tZone_converter(_date);

		if (_eventFlag == 'S')
		{
			et2_dialog.show_dialog(function(_button_id)
			{
				if (_button_id == et2_dialog.OK_BUTTON)
				{
					egw().json('calendar.calendar_uiforms.ajax_moveEvent', [eventId, calOwner, date, eventOwner, _duration]).sendRequest();
				}
			},this.egw.lang("Do you really want to change the start of this series? If you do, the original series will be terminated as of today and a new series for the future reflecting your changes will be created."),
			this.egw.lang("This event is part of a series"), {}, et2_dialog.BUTTONS_OK_CANCEL , et2_dialog.WARNING_MESSAGE);
		}
		else
		{
			//Get infologID if in case if it's an integrated infolog event
			var infolog_id = eventId.split('infolog')[1];

			if (infolog_id)
			{
				// If it is an integrated infolog event we need to edit infolog entry
				egw().json('stylite_infolog_calendar_integration::ajax_moveInfologEvent', [infolog_id, date,_duration]).sendRequest();
			}
			else
			{
				//Edit calendar event
				egw().json('calendar.calendar_uiforms.ajax_moveEvent',[eventId,	calOwner, date,	eventOwner,	_duration]).sendRequest();
			}
		}
	},

	/**
	 * open the freetime search popup
	 *
	 * @param {string} _link
	 */
	freetime_search_popup: function(_link)
	{
		this.egw.open_link(_link,'ft_search','700x500') ;
	},

	/**
	 * send an ajax request to server to set the freetimesearch window content
	 *
	 */
	freetime_search: function()
	{
		var content = this.et2.getArrayMgr('content').data;
		content['start'] = this.et2.getWidgetById('start').get_value();
		content['end'] = this.et2.getWidgetById('end').get_value();
		content['duration'] = this.et2.getWidgetById('duration').get_value();

		var request = this.egw.json('calendar.calendar_uiforms.ajax_freetimesearch', [content],null,null,null,null);
		request.sendRequest();
	},

	/**
	 * Function for disabling the recur_data multiselect box
	 *
	 */
	check_recur_type: function()
	{
		var recurType = this.et2.getWidgetById('recur_type');
		var recurData = this.et2.getWidgetById('recur_data');

		if(recurType && recurData)
		{
			recurData.set_disabled(recurType.get_value() != 2);
		}
	},

	/**
	 * Show/Hide end date, for both edit and freetimesearch popups,
	 * based on if "use end date" selected or not.
	 *
	 */
	set_enddate_visibility: function()
	{
		var duration = this.et2.getWidgetById('duration');
		var start = this.et2.getWidgetById('start');
		var end = this.et2.getWidgetById('end');
		var content = this.et2.getArrayMgr('content').data;

		if (typeof duration != 'undefined' && typeof end != 'undefined')
		{
			end.set_disabled(duration.get_value()!=='');
			if (!end.disabled )
			{
				end.set_value(start.get_value());
				if (typeof content.duration != 'undefined') end.set_value("+"+content.duration);
			}
		}
	},

	/**
	 * handles actions selectbox in calendar edit popup
	 *
	 * @param {mixed} _event
	 * @param {et2_base_widget} widget "actions selectBox" in edit popup window
	 */
	actions_change: function(_event, widget)
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
					this.egw.open_link('infolog.infolog_ui.edit&action=calendar&action_id='+($j.isPlainObject(event)?event['id']:event),'_blank','700x600','infolog');
					this.et2._inst.submit();
					break;
				case 'ical':
					this.et2._inst.postSubmit();
					break;
				default:
					this.et2._inst.submit();
			}
		}
	},

	/**
	 * open mail compose popup window
	 *
	 * @param {Array} vars
	 * @todo need to provide right mail compose from server to custom_mail function
	 */
	custom_mail: function (vars)
	{
		this.egw.open_link(this.egw.link("/index.php",vars),'_blank','700x700');
	},

	/**
	 * control delete_series popup visibility
	 *
	 * @param {et2_widget} widget
	 * @param {Array} exceptions an array contains number of exception entries
	 *
	 */
	delete_btn: function(widget,exceptions)
	{
		var content = this.et2.getArrayMgr('content').data;

		if (exceptions)
		{
			var buttons = [
				{
					button_id: 'keep',
					statustext:'All exceptions are converted into single events.',
					text: 'Keep exceptions',
					id: 'button[delete_keep_exceptions]',
					image: 'keep', "default":true
				},
				{
					button_id: 'delete',
					statustext:'The exceptions are deleted together with the series.',
					text: 'Delete exceptions',
					id: 'button[delete_exceptions]',
					image: 'delete'
				},
				{
					button_id: 'cancel',
					text: 'Cancel',
					id: 'dialog[cancel]',
					image: 'cancel'
				}

			];
			var self = this;
			et2_dialog.show_dialog
			(
					function(_button_id)
					{
						if (_button_id != 'dialog[cancel]')
						{
							self.et2._inst.submit(_button_id);
							return true;
						}
						else
						{
							return false;
						}
					},
					this.egw.lang("Do you want to keep the series exceptions in your calendar?"),
					this.egw.lang("This event is part of a series"), {}, buttons , et2_dialog.WARNING_MESSAGE
			);
		}
		else if (content['recur_type'] !== 0)
		{
			et2_dialog.confirm(widget,'Delete this series of recuring events','Delete Series');
		}
		else
		{
			et2_dialog.confirm(widget,'Delete this event','Delete');
		}
	},

	/**
	 * print_participants_status(egw,widget)
	 * Handle to apply changes from status in print popup
	 *
	 * @param {mixed} _event
	 * @param {et2_base_widget} widget widget "status" in print popup window
	 *
	 */
	print_participants_status: function(_event, widget)
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
	},

	/**
	 * In edit popup, search for calendar participants.
	 * Resources need to have the start & duration (etc.)
	 * passed along in the query.
	 *
	 * @param {Object} request
	 * @param {et2_link_entry} widget
	 *
	 * @returns {boolean} True to continue with the search
	 */
	edit_participant_search: function(request, widget)
	{
		if(widget.app_select.val() == 'resources')
		{
			// Resources search is expecting exec
			var values = widget.getInstanceManager().getValues(widget.getRoot());
			if(typeof request.options != 'object' || request.options == null)
			{
				request.options = {};
			}
			request.options.exec = {
				start: values.start,
				end: values.end,
				duration: values.duration,
				participants: values.participants,
				recur_type: values.recur_type,
				event_id: values.link_to.to_id, // cal_id, if available
				show_conflict: (egw.preference('defaultresource_sel','calendar') == 'resources_without_conflict') ? '0' : '1'
			};
			if(values.whole_day)
			{
				request.options.exec.whole_date = true;
			}
		}
		return true;
	},
	
	/**
	 * Handles to select freetime, and replace the selected one on Start,
	 * and End date&time in edit calendar entry popup.
	 *
	 * @param {mixed} _event
	 * @param {et2_base_widget} _widget widget "select button" in freetime search popup window
	 *
	 */
	freetime_select: function(_event, _widget)
	{
		if (_widget)
		{
			var content = this.et2._inst.widgetContainer.getArrayMgr('content').data;
			// Make the Id from selected button by checking the index
			var selectedId = _widget.id.match(/^select\[([0-9])\]$/i)[1];

			var sTime = this.et2.getWidgetById(selectedId+'start');

			//check the parent window is still open before to try to access it
			if (window.opener && sTime)
			{
				var editWindowObj = window.opener.etemplate2.getByApplication('calendar')[0];
				if (typeof editWindowObj != "undefined")
				{
					var startTime = editWindowObj.widgetContainer.getWidgetById('start');
					var endTime = editWindowObj.widgetContainer.getWidgetById('end');
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
	},

	/**
	 * show/hide the filter of nm list in calendar listview
	 *
	 */
	filter_change: function()
	{
		var filter = this.et2 ? this.et2.getWidgetById('filter') : null;
		var dates = this.et2 ? this.et2.getWidgetById('calendar.list.dates') : null;

		if (filter && dates)
		{
			dates.set_disabled(filter.value !== "custom");
		}
	},

	/**
	 * Change status (via AJAX)
	 *
	 * @param {egwAction} _action
	 * @param {egwActionObject} _events
	 */
	status: function(_action, _events)
	{
		// Should be a single event, but we'll do it for all
		for(var i = 0; i < _events.length; i++)
		{
			var event_widget = _events[i].iface.getWidget() || false;
			if(!event_widget) continue;

			event_widget.recur_prompt(jQuery.proxy(function(button_id,event_data) {
				console.log(event_data.title, '  ', event_data.start, ' Status change ', _action.data.id, ' Button: ', button_id );
				switch(button_id)
				{
					case 'exception':

						break;
					case 'series':
					case 'single':
						this.egw.open(event_data.id, event_data.app||'calendar', 'edit', {date:event_data.start});
						break;
					case 'cancel':
					default:
						break;
				}
			},this));
		}

	},

	/**
	 * this function try to fix ids which are from integrated apps
	 *
	 * @param {egw_action} _action
	 * @param {Array} _senders
	 */
	cal_fix_app_id: function(_action, _senders)
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

		nm_action(_action, _senders);

		_action.data.url = backup_url;	// restore url
	},

	/**
	 * Open calendar entry, taking into accout the calendar integration of other apps
	 *
	 * calendar_uilist::get_rows sets var js_calendar_integration object
	 *
	 * @param _action
	 * @param _senders
	 *
	 */
	cal_open: function(_action, _senders)
	{
		
		var js_integration_data = _action.parent.data.nextmatch.options.settings.js_integration_data || this.et2.getArrayMgr('content').data.nm.js_integration_data;
		var id = _senders[0].id;
		var matches = id.match(/^(?:calendar::)?([0-9]+):([0-9]+)$/);
		var backup = _action.data;
		if (matches)
		{
			this.edit_series(matches[1],matches[2]);
			return;
		}
		matches = id.match(/^([a-z_-]+)([0-9]+)/i);
		if (matches)
		{
			var app = matches[1];
			_action.data.url = window.egw_webserverUrl+'/index.php?';
			var get_params = js_integration_data[app].edit;
			get_params[js_integration_data[app].edit_id] = matches[2];
			for(var name in get_params)
				_action.data.url += name+"="+encodeURIComponent(get_params[name])+"&";

			if (js_integration_data[app].edit_popup)
			{
				matches = js_integration_data[app].edit_popup.match(/^(.*)x(.*)$/);
				if (matches)
				{
					_action.data.width = matches[1];
					_action.data.height = matches[2];
				}
				else
				{
					_action.data.nm_action = 'location';
				}
			}
		}
		egw.open(id.replace(/^calendar::/g,''),'calendar','edit');
		_action.data = backup;	// restore url, width, height, nm_action
	},

	/**
	 * Delete calendar entry, asking if you want to delete series or exception
	 *
	 *
	 * @param _action
	 * @param _senders
	 */
	cal_delete: function(_action, _senders)
	{
		var backup = _action.data;
		var matches = false;

		// Loop so we ask if any of the selected entries is part of a series
		for(var i = 0; i < _senders.length; i++)
		{
			var id = _senders[i].id;
			if(!matches)
			{
				matches = id.match(/^(?:calendar::)?([0-9]+):([0-9]+)$/);
			}
		}
		if (matches)
		{
			var popup = jQuery('#calendar-list_delete_popup').get(0);
			if (typeof popup != 'undefined')
			{
				// nm action - show popup
				nm_open_popup(_action,_senders);
			}
			return;
		}

		nm_action(_action, _senders);
	},

	/**
	 * Confirmation dialog for moving a series entry
	 *
	 * @param {object} _DOM
	 * @param {et2_widget} _button button Save | Apply
	 */
	move_edit_series: function(_DOM,_button)
	{
		var content = this.et2.getArrayMgr('content').data;
		var start_date = this.et2.getWidgetById('start').get_value();
		var whole_day = this.et2.getWidgetById('whole_day');
		var is_whole_day = whole_day && whole_day.get_value() == whole_day.options.selected_value;
		var button = _button;
		var that = this;
		if (typeof content != 'undefined' && content.id != null &&
			typeof content.recur_type != 'undefined' && content.recur_type != null && content.recur_type != 0
		)
		{
			if (content.start != start_date || content.whole_day != is_whole_day)
			{
				et2_dialog.show_dialog(function(_button_id)
					{
						if (_button_id == et2_dialog.OK_BUTTON)
						{
							that.et2._inst.submit(button);

						}
						else
						{
							return false;
						}
					},
					this.egw.lang("Do you really want to change the start of this series? If you do, the original series will be terminated as of today and a new series for the future reflecting your changes will be created."),
					this.egw.lang("This event is part of a series"), {}, et2_dialog.BUTTONS_OK_CANCEL , et2_dialog.WARNING_MESSAGE);
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
	},

	/**
	 * Create edit exception dialog for recurrence entries
	 *
	 * @param {object} event
	 * @param {string} id cal_id
	 * @param {integer} date timestamp
	 */
	edit_series: function(event,id,date)
	{
		// Coming from list, there is no event
		if(arguments.length == 2)
		{
			date = id;
			id = event;
			event = null;
		}
		var edit_id = id;
		var edit_date = date;
		var that = this;
		var buttons = [
			{text: this.egw.lang("Edit exception"), id: "exception", class: "ui-priority-primary", "default": true},
			{text: this.egw.lang("Edit series"), id:"series"},
			{text: this.egw.lang("Cancel"), id:"cancel"}
		];
		et2_dialog.show_dialog(function(_button_id)
		{
			switch(_button_id)
			{
				case 'exception':
					that.egw.open(edit_id, 'calendar', 'edit', '&date='+edit_date+'&exception=1');
					break;
				case 'series':
					that.egw.open(edit_id, 'calendar', 'edit', '&date='+edit_date);
					break;
				case 'cancel':

				default:
					break;
			}
		},this.egw.lang("Do you want to edit this event as an exception or the whole series?"),
		this.egw.lang("This event is part of a series"), {}, buttons, et2_dialog.WARNING_MESSAGE);
	},

	/**
	 * Method to set state for JSON requests (jdots ajax_exec or et2 submits can NOT use egw.js script tag)
	 *
	 * @param {object} _state
	 */
	set_state: function(_state)
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
	},

	/**
	 * Change only part of the current state.
	 * 
	 * The passed state options (filters) are merged with the current state, so
	 * this is the one that should be used for most calls, as setState() requires
	 * the complete state.
	 * 
	 * @param {Object} _set New settings
	 */
	update_state: function(_set)
	{
		// Make sure we're running in top window
		if(window !== window.top)
		{
			return window.top.app.calendar.update_state(_set);
		}
		var changed = [];
		var new_state = jQuery.extend({}, this.state);
		if (typeof _set == 'object')
		{
			for(var s in _set)
			{
				if (new_state[s] !== _set[s])
				{
					changed.push(s + ': ' + new_state[s] + ' -> ' + _set[s]);
					new_state[s] = _set[s];
				}
			}
		}
		if(changed.length && !this.state_update_in_progress)
		{
			console.log('Calendar state changed',changed.join("\n"));
			// Log
			this.egw.debug('navigation','Calendar state changed', changed.join("\n"));
			this.setState({state: new_state});
		}
	},

	/**
	 * Return state object defining current view
	 *
	 * Called by favorites to query current state.
	 *
	 * @return {object} description
	 */
	getState: function()
	{
		var state = this.state;

		if (!state)
		{
			var egw_script_tag = document.getElementById('egw_script_id');
			state = egw_script_tag.getAttribute('data-calendar-state');
			state = state ? JSON.parse(state) : {};
		}

		// Make sure date is consitantly a string, in case it needs to be passed to server
		if(state.date.toJSON)
		{
			state.state = state.date.toJSON();
		}

		// Don't store current user in state to allow admins to create favourites for all
		// Should make no difference for normal users.
		if(state.owner == egw.user('account_id'))
		{
			// 0 is always the current user, so if an admin creates a default favorite,
			// it will work for other users too.
			state.owner = 0;
		}
		// Don't store first and last
		delete state.first;
		delete state.last;
		
		return state;
	},

	/**
	 * Set a state previously returned by getState
	 *
	 * Called by favorites to set a state saved as favorite.
	 *
	 * @param {object} state containing "name" attribute to be used as "favorite" GET parameter to a nextmatch
	 */
	setState: function(state)
	{
		// State should be an object, not a string, but we'll parse
		if(typeof state == "string")
		{
			if(state.indexOf('{') != -1 || state =='null')
			{
				state = JSON.parse(state);
			}
		}
		if(typeof state.state != 'object' || !state.state.view)
		{
			state.state = {view: 'week'};
		}
		if(!state.state.date)
		{
			state.state.date = new Date();
		}


		// Hide other views
		for(var _view in this.views)
		{
			if(state.state.view != _view && this.views[_view])
			{
				for(var i = 0; i < this.views[_view].etemplates.length; i++)
				{
					$j(this.views[_view].etemplates[i].DOMContainer).hide();
				}
			}
		}
		if(this.sidebox_et2)
		{
			$j(this.sidebox_et2.getInstanceManager().DOMContainer).hide();
		}
		
		// Check for a supported client-side view
		if(this.views[state.state.view] &&
			// Check that the view is instanciated
			typeof this.views[state.state.view].etemplates[0] !== 'string' && this.views[state.state.view].etemplates[0].widgetContainer
		)
		{
			// Doing an update - this includes the selected view, and the sidebox
			// We set a flag to ignore changes from the sidebox which would
			// cause infinite loops.
			this.state_update_in_progress = true;
			
			var view = this.views[state.state.view];

			// Sanitize owner
			switch(typeof state.state.owner)
			{
				case 'undefined':
					state.state.owner = this.egw.user('account_id');
					break;
				case 'string':
					state.state.owner = state.state.owner.split(',');
					break;
				case 'number':
					state.state.owner = [state.state.owner];
					break;
			}
			// Keep sort order
			if(typeof this.state.owner === 'object')
			{
				var owner = [];
				this.state.owner.forEach(function(key) {
					var found = false;
					state.state.owner = state.state.owner.filter(function(item) {
						if(!found && item == key) {
							owner.push(item);
							found = true;
							return false;
						} else
							return true;
					});
				});
				// Add in any new owners
				state.state.owner = owner.concat(state.state.owner);
			}


			// Show the correct number of grids
			var grid_count = state.state.view === 'weekN' ? parseInt(this.egw.preference('multiple_weeks','calendar')) || 3 :
				state.state.view === 'month' ? 0 : // Calculate based on weeks in the month
				state.state.owner.length > (this.egw.config('calview_no_consolidate','phpgwapi') || 5) ? 1 : state.state.owner.length;

			var grid = this.views[this.state.view] ? this.views[this.state.view].etemplates[0].widgetContainer.getWidgetById('view') : false;

			/*
			If the count is different, we need to have the correct number (just remove all & re-create)
			If the count is > 1, it's either because there are multiple date spans (weekN, month) and we need the correct span
			per row, or there are multiple owners and we need the correct owner per row.
			*/
			if(grid && (grid_count !== grid._children.length || grid_count > 1))
			{
				// Need to redo the number of grids
				var value = [];
				state.state.first = view.set_start_date(state.state).toJSON();
				// We'll modify this one, so it needs to be a new object
				var date = new Date(state.state.first);

				// Determine the different end date
				switch(state.state.view)
				{
					case 'month':
						var end = state.state.last = view.set_end_date(state.state);
						grid_count = Math.ceil((end - date) / (1000 * 60 * 60 * 24) / 7);
						// fall through
					case 'weekN':
						for(var week = 0; week < grid_count; week++)
						{
							var val = {
								id: ""+date.getUTCFullYear() + sprintf("%02d",date.getUTCMonth()) + sprintf("%02d",date.getUTCDate()),
								start_date: new Date(date),
								end_date: new Date(date),
								owner: state.state.owner
							};
							val.end_date.setUTCHours(24*7-1);
							value.push(val);
							date.setUTCHours(24*7);
						}
						state.state.last=val.end_date.toJSON();
						break;
					default:
						var end = state.state.last = view.set_end_date(state.state);
						for(var owner = 0; owner < grid_count && owner < state.state.owner.length; owner++)
						{
							value.push({
								id: ""+date.getUTCFullYear() + sprintf("%02d",date.getUTCMonth()) + sprintf("%02d",date.getUTCDate()),
								start_date: date,
								end_date: end,
								owner: state.state.owner[owner] || 0
							});
						}
						break;
				}
				if(grid)
				{
					grid.set_value(
						{content: value}
					);
				}
			}
			else
			{
				// Simple, easy case - just one widget for the selected time span.
				// Update existing view's special attribute filters, defined in the view list
				for(var updater in view)
				{
					if(typeof view[updater] === 'function')
					{
						var value = view[updater].call(this,state.state);
						if(updater === 'set_start_date') state.state.first = value.toJSON();
						if(updater === 'set_end_date') state.state.last = value.toJSON();

						// Set value
						for(var i = 0; i < view.etemplates.length; i++)
						{
							view.etemplates[i].widgetContainer.iterateOver(function(widget) {
								if(typeof widget[updater] === 'function')
								{
									widget[updater](value);
								}
							}, this, et2_valueWidget);
						}
					}
				}
			}
			// Include first & last dates in state, mostly for server side processing
			if(state.state.first && state.state.first.toJSON) state.state.first = state.state.first.toJSON()
			if(state.state.last && state.state.last.toJSON) state.state.last = state.state.last.toJSON()

			// Show the templates for the current view
			for(var i = 0; i < view.etemplates.length; i++)
			{
				$j(view.etemplates[i].DOMContainer).show();
			}
			// Toggle todos
			if(state.state.view == 'day')
			{
				if(state.state.owner.length !== 1)
				{
					$j(view.etemplates[1].DOMContainer).hide();
					view.etemplates[0].widgetContainer.set_width("");
				}
				else
				{
					view.etemplates[0].widgetContainer.set_width("70%");
					// TODO: Maybe some caching here
					this.egw.jsonq('calendar_uiviews::ajax_get_todos', [state.state.date, state.state.owner[0]], function(data) {
						this.getWidgetById('label').set_value(data.label||'');
						this.getWidgetById('todos').set_value(data.todos||'');
					},view.etemplates[1].widgetContainer)
				}
			}
			else
			{
				view.etemplates[0].widgetContainer.set_width("");
			}
			this.state = jQuery.extend({},state.state);

			if(state.state.view === 'listview')
			{
				state.state.startdate = state.state.date;
				state.state.col_filter = {participant: state.state.owner};
				var nm = this.views[_view].etemplates[0].widgetContainer.getWidgetById('nm');
				nm.applyFilters(state.state);
			}

			/* Update re-orderable calendars */
			this._sortable();
			
			/* Update sidebox widgets to show current value*/
			this.sidebox_et2.iterateOver(function(widget) {
				if(widget.id == 'view')
				{
					// View widget has a list of state settings, which require special handling
					for(var i = 0; i < widget.options.select_options.length; i++)
					{
						var option_state = JSON.parse(widget.options.select_options[i].value) || [];
						var match = true;
						for(var os_key in option_state)
						{
							match = match && option_state[os_key] == this.state[os_key];
						}
						if(match)
						{
							widget.set_value(widget.options.select_options[i].value);
							return;
						}
					}
				}
				else if(typeof state.state[widget.id] !== 'undefined' && state.state[widget.id] != widget.getValue())
				{
					// Update widget.  This may trigger an infinite loop of
					// updates, so we do it after changing this.state and set a flag
					widget.set_value(state.state[widget.id]);
				}
			},this,et2_valueWidget);

			// If current state matches a favorite, hightlight it
			this.highlight_favorite();

			// Sidebox is updated, we can clear the flag
			this.state_update_in_progress = false;

			// Show / Hide weekends in sidebox calendar based on if weekends should be shown
			egw.css('#'+this.sidebox_et2.getWidgetById('date').input_date.attr('id') + ' .ui-datepicker-week-end',
				(parseInt(this.state.days && this.state.days > 1 ? this.state.days: egw.preference('days_in_weekview','calendar'))) === 5 ? 'display: none;' : 'display: table-cell;');

			return;
		}
		// old calendar state handling on server-side (incl. switching to and from listview)
		var menuaction = 'calendar.calendar_uiviews.index';
		if (typeof state.state != 'undefined' && (typeof state.state.view == 'undefined' || state.state.view == 'listview'))
		{
			if (state.name)
			{
				// 'blank' is the special name for no filters, send that instead of the nice translated name
				state.state.favorite = jQuery.isEmptyObject(state) || jQuery.isEmptyObject(state.state||state.filter) ? 'blank' : state.name.replace(/[^A-Za-z0-9-_]/g, '_');
				// set date for "No Filter" (blank) favorite to todays date
				if (state.state.favorite == 'blank')
					state.state.date = jQuery.datepicker.formatDate('yymmdd', new Date);
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
					return this._super.apply(this, [state]);
				}
			}
		}
		// setting internal state now, that linkHandler does not intercept switching from listview to any old view
		this.state = jQuery.extend({},state.state);
		$j(this.sidebox_et2.getInstanceManager().DOMContainer).show();

		var query = jQuery.extend({menuaction: menuaction},state.state||{});

		// prepend an owner 0, to reset all owners and not just set given resource type
		if(typeof query.owner != 'undefined')
		{
			query.owner = '0,'+ query.owner;
		}

		this.egw.open_link(this.egw.link('/index.php',query), 'calendar');

		// Stop the normal bubbling if this is called on click
		return false;
	},

	/**
	 * Check to see if any of the selected is an event widget
	 * Used to separate grid actions from event actions
	 * 
	 * @param {egwAction} _egw
	 * @param {egwActioObject[]} _widget
	 * @returns {boolean} Is any of the selected an event widget
	 */
	is_event: function(_action, _selected)
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
				is_widget = is_widget && ($j( _selected[i].iface.getDOMNode()).hasClass(_action.data.enableClass))
			}
			if(_action.data && _action.data.disableClass)
			{
				is_widget = is_widget && !($j( _selected[i].iface.getDOMNode()).hasClass(_action.data.disableClass))
			}

		}
		return is_widget;
	},

	/**
	 * Enable/Disable custom Date-time for set Alarm
	 *
	 * @param {egw object} _egw
	 * @param {widget object} _widget new_alarm[options] selectbox
	 */
	alarm_custom_date: function (_egw,_widget)
	{
		var alarm_date = this.et2.getWidgetById('new_alarm[date]');
		var alarm_options = _widget || this.et2.getWidgetById('new_alarm[options]');
		var start = this.et2.getWidgetById('start');

		if (alarm_date && alarm_options
					&& start)
		{
			if (alarm_options.get_value() != '0')
			{
				alarm_date.set_class('calendar_alarm_date_display');
			}
			else
			{
				alarm_date.set_class('');
			}
			var startDate = typeof start.get_value != 'undefined'?start.get_value():start.value;
			if (startDate)
			{
				var date = new Date(startDate);
				date.setTime(date.getTime() - 1000 * parseInt(alarm_options.get_value()));
				alarm_date.set_value(date);
			}
		}
	},

	/**
	 * Set alarm options based on WD/Regular event user preferences
	 * Gets fired by wholeday checkbox
	 *
	 * @param {egw object} _egw
	 * @param {widget object} _widget whole_day checkbox
	 */
	set_alarmOptions_WD: function (_egw,_widget)
	{
		var alarm = this.et2.getWidgetById('alarm');
		if (!alarm) return;	// no default alarm
		var content = this.et2.getArrayMgr('content').data;
		var start = this.et2.getWidgetById('start');
		var self= this;
		var time = alarm.cells[1][0].widget;
		var event = alarm.cells[1][1].widget;
		// Convert a seconds of time to a translated label
		var _secs_to_label = function (_secs)
		{
			var label='';
			if (_secs <= 3600)
			{
				label = self.egw.lang('%1 minutes', _secs/60);
			}
			else if(_secs <= 86400)
			{
				label = self.egw.lang('%1 hours', _secs/3600);
			}
			return label;
		};
		if (typeof content['alarm'][1]['default'] == 'undefined')
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
				time.set_value('-'+(60 * def_alarm));
				event.set_value(_secs_to_label(60 * def_alarm));
			}
		}
	},

	/**
	 * Some handy date calculations
	 * All take either a Date object or full date with timestamp (Z)
	 */
	date: {
		start_of_week: function(date)
		{
			var d = new Date(date);
			var day = d.getUTCDay();
			var diff = 0;
			switch(egw.preference('weekdaystarts','calendar'))
			{
				case 'Saturday':
					diff = day === 6 ? 0 : day === 0 ? -1 : day + 1;
					break;
				case 'Monday':
					diff = day === 0 ? 1 : 1-day;
					break;
				case 'Sunday':
				default:
					diff = -day;
			}
			d.setUTCHours(24*diff);
			return d;
		},
		end_of_week: function(date)
		{
			var d = app.calendar.date.start_of_week(date);
			d.setUTCHours(24*7);
			return d;
		}
	},

	/**
	 * The sidebox filters use some non-standard and not-exposed options.  They
	 * are set up here.
	 *
	 */
	_setup_sidebox_filters: function()
	{
		// Further date customizations
		var date = this.sidebox_et2.getWidgetById('date');
		if(date)
		{
			date.input_date.datepicker("option", {
				showButtonPanel:	false,
				// TODO: We could include tooltips for holidays
			})
		}
		// Show / Hide weekends based on preference of weekends should be shown
		egw.css('#'+date.input_date.attr('id') + ' .ui-datepicker-week-end', 
			egw.preference('days_in_weekview', 'calendar') === "5" ? 'display: none;' : 'display: table-cell;'
		);


		// Clickable week numbers
		date.input_date.on('mouseenter','.ui-datepicker-week-col', function() {
				$j(this).siblings().find('a').addClass('ui-state-hover');
			})
			.on('mouseleave','.ui-datepicker-week-col', function() {
				$j(this).siblings().find('a').removeClass('ui-state-hover');
			})
			.on('click', '.ui-datepicker-week-col', function() {
				// Fake a click event on the first day to get the updated date
				$j(this).next().click();

				// Set to week view
				app.calendar.update_state({view: 'week', date: date.getValue()});
			});
		
	}
});
