/*
 * Egroupware Calendar event widget
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

import {et2_register_widget, et2_widget, WidgetConfig} from "../../api/js/etemplate/et2_core_widget";
import {et2_valueWidget} from "../../api/js/etemplate/et2_core_valueWidget";
import {ClassWithAttributes} from "../../api/js/etemplate/et2_core_inheritance";
import {et2_action_object_impl, et2_DOMWidget} from "../../api/js/etemplate/et2_core_DOMWidget";
import {et2_calendar_daycol} from "./et2_widget_daycol";
import {et2_calendar_planner_row} from "./et2_widget_planner_row";
import {et2_IDetachedDOM} from "../../api/js/etemplate/et2_core_interfaces";
import {et2_activateLinks, et2_insertLinkText, et2_no_init} from "../../api/js/etemplate/et2_core_common";
import {egw_getAppObjectManager, egwActionObject} from '../../api/js/egw_action/egw_action.js';
import {egw} from "../../api/js/jsapi/egw_global";
import {et2_container} from "../../api/js/etemplate/et2_core_baseWidget";
import {Et2Dialog} from "../../api/js/etemplate/Et2Dialog/Et2Dialog";
import {formatDate, formatTime} from "../../api/js/etemplate/Et2Date/Et2Date";
import {ColorTranslator} from "colortranslator";
import {StaticOptions} from "../../api/js/etemplate/Et2Select/StaticOptions";
import {Et2Select} from "../../api/js/etemplate/Et2Select/Et2Select";
import {SelectOption} from "../../api/js/etemplate/Et2Select/FindSelectOptions";
import {CalendarApp} from "./app";

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
 */
export class et2_calendar_event extends et2_valueWidget implements et2_IDetachedDOM
{

		static readonly _attributes: any = {
				"value": {
						type: "any",
						default: et2_no_init
				},
				"onclick": {
						"description": "JS code which is executed when the element is clicked. " +
								"If no handler is provided, or the handler returns true and the event is not read-only, the " +
								"event will be opened according to calendar settings."
				}
		};
		private div: JQuery;
		private title: JQuery;
		private body: JQuery;
		private icons: JQuery;
		private _need_actions_linked: boolean = false;
		private _actionObject: egwActionObject;

		/**
		 * Constructor
		 */
		constructor(_parent, _attrs?: WidgetConfig, _child?: object)
		{
				// Call the inherited constructor
				super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_calendar_event._attributes, _child || {}));

				const event = this;

				// Main container
				this.div = jQuery(document.createElement("div"))
						.addClass("calendar_calEvent")
						.addClass(this.options.class)
						.css('width', this.options.width)
						.on('mouseenter', function ()
						{
								// Bind actions on first mouseover for faster creation
								if (event._need_actions_linked)
								{
										event._copy_parent_actions();
								}
								// Tooltip
								if (!event._tooltipElem)
								{
										event.options.statustext_html = true;
										event.set_statustext(event._tooltip());
										if (event.statustext)
										{
												return event.div.trigger('mouseenter');
										}
								}
								// Hacky to remove egw's tooltip border and let the mouse in
								window.setTimeout(function ()
								{
										jQuery('body .egw_tooltip')
											.css('border', 'none')
											.on('mouseenter', function()
											{
												if(event.div)
												{
													event.div.off('mouseleave.tooltip');
												}

												jQuery('body.egw_tooltip').remove();
												jQuery('body').append(this);
												jQuery(this).stop(true).fadeTo(400, 1)
													.on('mouseleave', function()
													{
														jQuery(this).fadeOut('400', function()
														{
															jQuery(this).remove();
															// Set up to work again
															event.set_statustext(event._tooltip());
														});
													});
											});

								}, 105);
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
		}

		doLoadingFinished()
		{
				super.doLoadingFinished();

				// Already know what is needed to hook to cache
				if (this.options.value && this.options.value.row_id)
				{
						egw.dataRegisterUID(
								'calendar::' + this.options.value.row_id,
								this._UID_callback,
								this,
								this.getInstanceManager().execId,
								this.id
						);
				}
				return true;
		}

		destroy()
		{
				super.destroy();

				if (this._actionObject)
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
				if (this.options.value)
				{
						const old_app_id = this.options.value.row_id;
						egw.dataUnregisterUID('calendar::' + old_app_id, null, this);
				}
		}

		set_value(_value)
		{
				// Un-register for updates
				if (this.options.value)
				{
						var old_id = this.options.value.row_id;
						if (!_value || !_value.row_id || old_id !== _value.row_id)
						{
								egw.dataUnregisterUID('calendar::' + old_id, null, this);
						}
				}
				this.options.value = _value;

				// Register for updates
				const id = this.options.value.row_id;
				if (!old_id || old_id !== id)
				{
						egw.dataRegisterUID('calendar::' + id, this._UID_callback, this, this.getInstanceManager().execId, this.id);
				}
				if (_value && !egw.dataHasUID('calendar::' + id))
				{
						egw.dataStoreUID('calendar::' + id, _value);
				}
		}

		/**
		 * Callback for changes in cached data
		 */
		_UID_callback(event)
		{
				// Copy to avoid changes, which may cause nm problems
				const value = event === null ? null : jQuery.extend({}, event);
				let parent = <et2_DOMWidget>this.getParent();
				let parent_owner = parent.getDOMNode(parent).dataset['owner'] || parent.getParent().options.owner;
				if (parent_owner.indexOf(',') >= 0)
				{
						parent_owner = parent_owner.split(',');
				}

				// Make sure id is a string, check values
				if (value)
				{
						this._values_check(value);
				}

				// Check for changing days in the grid view
				let state = this.getInstanceManager().app_obj.calendar.getState() || app.calendar.getState();
				if (!this._sameday_check(value) || !this._status_check(value, state.status_filter, parent_owner))
				{
						// May need to update parent to remove out-of-view events
						parent.removeChild(this);
						if (event === null && parent && parent.instanceOf(et2_calendar_daycol))
						{
								(<et2_calendar_daycol>parent)._out_of_view();
						}

						// This should now cease to exist, as new events have been created
						this.destroy();
						return;
				}

				// Copy to avoid changes, which may cause nm problems
				this.options.value = jQuery.extend({}, value);

				if (this.getParent().options.date)
				{
						this.options.value.date = this.getParent().options.date;
				}

				// Let parent position - could also be et2_calendar_planner_row
				(<et2_calendar_daycol>this.getParent()).position_event(this);

				// Parent may remove this if the date isn't the same
				if (this.getParent())
				{
						this._update();
				}
		}

		/**
		 * Draw the event
		 */
		_update()
		{

			// Update to reflect new information
			const event = this.options.value;

			const id = event.row_id ? event.row_id : event.id + (event.recur_type ? ':' + event.recur_date : '');
			const formatted_start = event.start.toJSON();

			this.set_id('event_' + id);
			if(this._actionObject)
			{
				this._actionObject.id = 'calendar::' + id;
			}

			this._need_actions_linked = !this.options.readonly;

			// Make sure category stuff is there by faking a call to cache
			let so = new StaticOptions();
			so.cached_server_side(<Et2Select><unknown>{
				nodeName: "ET2-SELECT-CAT_RO",
				egw: () => this.egw()
			}, "select-cat", ",,,calendar", true);


			// Need cleaning? (DnD helper removes content)
			// @ts-ignore
			if(!this.div.has(this.title).length)
			{
				this.div
					.empty()
					.append(this.title)
					.append(this.body);
			}

			let tooltip = jQuery(this._tooltip()).text();
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
						.attr('data-resize', event.whole_day ? 'WD' : '' + (event.recur_type ? 'S' : ''))
						.attr('data-priority', event.priority)
						// Accessibility
						.attr("tabindex", 0)
						.attr("aria-label", tooltip)
						// Remove any category classes
						.removeClass(function (index, css)
						{
								return (css.match(/(^|\s)cat_\S+/g) || []).join(' ');
						})
						// Remove any status classes
						.removeClass(function (index, css)
						{
								return (css.match(/calendar_calEvent\S+/g) || []).join(' ');
						})
						.removeClass('calendar_calEventSmall')
						.addClass(event.class)
						.toggleClass('calendar_calEventPrivate', typeof event.private !== 'undefined' && event.private);
				this.options.class = event.class;
				const status_class = this._status_class();

				// Add category classes, if real categories are set
				if (event.category && event.category != '0')
				{
						const cats = event.category.split(',');
						for (let i = 0; i < cats.length; i++)
						{
								this.div.addClass('cat_' + cats[i]);
						}
				}

				this.div.toggleClass('calendar_calEventUnknown', event.participants[egw.user('account_id')] ? event.participants[egw.user('account_id')][0] === 'U' : false);
				this.div.addClass(status_class);

				this.body.toggleClass('calendar_calEventBodySmall', event.whole_day_on_top || false);

				// Header
				const title = !event.is_private ? egw.htmlspecialchars(event['title']) : egw.lang('private');

				this.title
						.html('<span class="calendar_calTimespan">' + this._get_timespan(event) + '<br /></span>')
						.append('<span class="calendar_calEventTitle">' + title + '</span>');

				// Colors - don't make them transparent if there is no color
				const bg_color = new ColorTranslator(this.div.css('background-color'));
				if (bg_color.RGBA != 'rgb(0,0,0,0)')
				{
						// Most statuses use colored borders
						this.div.css('border-color', bg_color.RGBA);
				}

				this.icons.appendTo(this.title)
						.html(this._icons().join(''));

				// Body
				if (event.whole_day_on_top)
				{
						this.body.html(title);
				}
				else
				{
					// @ts-ignore
					const start_time = formatTime(event.start).trim();

					this.body
						.html('<span class="calendar_calEventTitle">' + title + '</span>')
						.append('<span class="calendar_calTimespan">' + start_time + '</span>');
					if(this.options.value.description.trim())
					{
						this.body
							.append('<p>' + egw.htmlspecialchars(this.options.value.description) + '</p>');
					}
				}

				// Clear tooltip for regeneration
				this.set_statustext('');

				// Height specific section
				// This can take an unreasonable amount of time if parent is hidden
				if (jQuery((<et2_DOMWidget>this.getParent()).getDOMNode(this)).is(':visible'))
				{
						this._small_size();
				}
		}

		/**
		 * Calculate display variants for when event is too short for full display
		 *
		 * Display is based on the number of visible lines, calculated off the header
		 * height:
		 * 1 - show just the event title, with ellipsis
		 * 2 - Show timespan and title, with ellipsis
		 * > 4 - Show description as well, truncated to fit
		 */
		_small_size()
		{

				if (this.options.value.whole_day_on_top) return;

				// Skip for planner view, it's always small
				if (this.getParent() && this.getParent().instanceOf(et2_calendar_planner_row)) return;

				// Pre-calculation reset
				this.div.removeClass('calendar_calEventSmall');
				this.body.css('height', 'auto');

				const line_height = parseFloat(this.div.css('line-height'));
				let visible_lines = Math.floor(this.div.innerHeight() / line_height);

				if(!this.title[0].clientHeight)
				{
					// Handle sizing while hidden, such as when calendar is not the active tab
					visible_lines = 1;
				}
				visible_lines = Math.max(1, visible_lines);

				if (this.getParent() && this.getParent().instanceOf(et2_calendar_daycol))
				{
						this.div.toggleClass('calendar_calEventSmall', visible_lines < 4);
						this.div
								.attr('data-visible_lines', visible_lines);
				}
				else if (this.getParent() && this.getParent().instanceOf(et2_calendar_planner_row))
				{
						// Less than 8 hours is small
						this.div.toggleClass('calendar_calEventSmall', this.options.value.end.valueOf() - this.options.value.start.valueOf() < 28800000);
				}


				if (this.body.height() > this.div.height() - this.title.height() && visible_lines >= 4)
				{
						this.body.css('height', Math.floor((visible_lines - 1) * line_height - this.title.height()) + 'px');
				}
				else
				{
						this.body.css('height', '');
				}
		}

		/**
		 * Examines the participants & returns CSS classname for status
		 *
		 * @returns {String}
		 */
		_status_class()
		{
				let status_class = 'calendar_calEventAllAccepted';
				for (let id in this.options.value.participants)
				{
						let status = this.options.value.participants[id];

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
		}

		/**
		 * Create tooltip shown on hover
		 *
		 * @return {String}
		 */
		_tooltip()
		{
			if(!this.div || !this.options.value || !this.options.value.app_id)
			{
				return '';
			}

			const border = this.div.css('borderTopColor');
			const bg_color = this.div.css('background-color');
			const header_color = this.title.css('color');
			const timespan = this._get_timespan(this.options.value);
			const parent = this.getParent() instanceof et2_calendar_daycol ? (<et2_calendar_daycol>this.getParent()) : (<et2_calendar_planner_row>this.getParent());

			const start = parent.date_helper(this.options.value.start);
			const end = parent.date_helper(this.options.value.end);

			const times = !this.options.value.multiday ?
						  '<span class="calendar_calEventLabel">' + this.egw().lang('Time') + '</span>: ' + timespan :
						  '<span class="calendar_calEventLabel">' + this.egw().lang('Start') + '</span>: ' + start + ' ' +
							  '<span class="calendar_calEventLabel">' + this.egw().lang('End') + '</span>: ' + end;
			let cat_label : (string | string[]) = '';
			if(this.options.value.category)
			{
				let so = new StaticOptions();
				let options = <SelectOption[]>so.cached_server_side(<Et2Select><unknown>{
					nodeName: "ET2-SELECT-CAT_RO",
					egw: () => this.egw()
				}, "select-cat", ",,,calendar", false) || [];
				cat_label = options.find((o) => o.value == this.options.value.category)?.label || "";
			}

				// Activate links in description
				let description_node = document.createElement("p");
				description_node.className = "calendar_calEvent_description";
				et2_insertLinkText(
					et2_activateLinks(this.options.value.description), description_node, '_blank'
				);

				// Location + Videoconference
				let location = '';
				if (this.options.value.location || this.options.value['##videoconference'])
				{
						location = '<p>';
						let location_node = document.createElement("span");
						location_node.className = "calendar_calEventLabel";
						et2_insertLinkText(et2_activateLinks(
							this.egw().lang('Location') + ': ' +
							egw.htmlspecialchars(this.options.value.location)), location_node, '_blank');
						location += location_node.outerHTML;

						if (this.options.value['##videoconference'])
						{
								// Click handler is set in _bind_videoconference()
								location += (this.options.value.location.trim() ? '<br />' : '') +
										'<span data-videoconference="' + this.options.value['##videoconference'] +
										'" data-id="' + this.options.value['id'] + '" data-title="' + this.options.value['title'] +
										'" data-start="' + this.options.value['start'].toJSON() + '" data-end="' + this.options.value['end'].toJSON() + '">' +
										this.egw().lang('Video conference') +
										'<img src="' + this.egw().image('videoconference', 'calendar') + '"/></span>';
								this._bind_videoconference();
						}
						location += '</p>';
				}

				// Participants
				let participants = '';
				if (this.options.value.participant_types[''])
				{
						participants += this.options.value.participant_types[''].join("<br />");
				}
				for (let type_name in this.options.value.participant_types)
				{
						if (type_name)
						{
								participants += '</p><p><span class="calendar_calEventLabel">' + type_name + ':</span><br />';
								participants += this.options.value.participant_types[type_name].join("<br />");
						}
				}

				return '<div class="calendar_calEventTooltip ' + this._status_class() + ' ' + this.options.class +
					'" style="border-color: ' + border + '; background-color: ' + bg_color + ';">' +
					'<div class="calendar_calEventHeaderSmall">' +
					'<span style="color:' + header_color + '">' + timespan + '</span>' +
					this.icons[0].outerHTML +
					'</div>' +
					'<div class="calendar_calEventBody">' +
					'<h1 class="calendar_calEventTitle">' + egw.htmlspecialchars(this.options.value.title) + '</h1><br>' +
					description_node.outerHTML +
					'<p style="margin: 2px 0px;">' + times + '</p>' +
					location +
					(cat_label ? '<p><h2 class="calendar_calEventLabel">' + this.egw().lang('Category') + ': </h2>' + cat_label + '</p>' : '') +
					'<p><h2 class="calendar_calEventLabel">' + this.egw().lang('Participants') + ': </h2><br />' +
					participants + '</p>' + this._participant_summary(this.options.value.participants) +
						'</div>' +
						'</div>';
		}

		/**
		 * Generate participant summary line
		 *
		 * @returns {String}
		 */
		_participant_summary(participants)
		{
				if (Object.keys(this.options.value.participants).length < 2)
				{
						return '';
				}

				const participant_status = {A: 0, R: 0, T: 0, U: 0, D: 0};
				const status_label = {A: 'accepted', R: 'rejected', T: 'tentative', U: 'unknown', D: 'delegated'};
				const participant_summary = Object.keys(this.options.value.participants).length + ' ' + this.egw().lang('Participants') + ': ';
				const status_totals = [];

				for (let id in this.options.value.participants)
				{
						var status = this.options.value.participants[id].substr(0, 1);
						participant_status[status]++;
				}
				for (let status in participant_status)
				{
						if (participant_status[status] > 0)
						{
								status_totals.push(participant_status[status] + ' ' + this.egw().lang(status_label[status]));
						}
				}
				return participant_summary + status_totals.join(', ');
		}

		/**
		 * Get actual icons from list
		 */
		_icons(): string[]
		{
				const icons = [];

				if (this.options.value.is_private)
				{
						// Hide everything
						icons.push('<img src="' + this.egw().image('private', 'calendar') + '" title="' + this.egw().lang('private event') + '"/>');
				}
				else
				{
						if (this.options.value.icons)
						{
								jQuery.extend(icons, this.options.value.icons);
						}
						else if (this.options.value.app !== 'calendar')
						{
								let app_icon = "" + (egw.link_get_registry(this.options.value.app, 'icon') || (this.options.value.app + '/navbar'));
								icons.push('<img src="' + this.egw().image(app_icon) + '" title="' + this.egw().lang(this.options.value.app) + '"/>');
						}
						if (this.options.value.priority == 3)
						{
								icons.push('<img src="' + this.egw().image('high', 'calendar') + '" title="' + this.egw().lang('high priority') + '"/>');
						}
						if (this.options.value.public == '0')
						{
								// Show private flag
								icons.push('<img src="' + this.egw().image('private', 'calendar') + '" title="' + this.egw().lang('private event') + '"/>');
						}
						if (this.options.value['recur_type'])
						{
								icons.push('<img src="' + this.egw().image('recur', 'calendar') + '" title="' + this.egw().lang('recurring event') + '"/>');
						}
						// icons for single user, multiple users or group(s) and resources
						const single = '<img src="' + this.egw().image('single', 'calendar') + '" title="' + this.egw().lang("single participant") + '"/>';
						const multiple = '<img src="' + this.egw().image('users', 'calendar') + '" title="' + this.egw().lang("multiple participants") + '"/>';
						for (const uid in this.options.value['participants'])
						{
								// @ts-ignore
								if (Object.keys(this.options.value.participants).length == 1 && !isNaN(uid))
								{
										icons.push(single);
										break;
								}
								// @ts-ignore
								if (!isNaN(uid) && icons.indexOf(multiple) === -1)
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

						if (this.options.value.alarm && !jQuery.isEmptyObject(this.options.value.alarm) && !this.options.value.is_private)
						{
								icons.push('<img src="' + this.egw().image('notification_message') + '" title="' + this.egw().lang('alarm') + '"/>');
						}
						if (this.options.value.participants[egw.user('account_id')] && this.options.value.participants[egw.user('account_id')][0] == 'U')
						{
								icons.push('<img src="' + this.egw().image('needs-action', 'calendar') + '" title="' + this.egw().lang('Needs action') + '"/>');
						}
						if (this.options.value["##videoconference"])
						{
								icons.push('<img src="' + this.egw().image('videoconference', 'calendar') + '" title="' + this.egw().lang('video conference') + '"/>');
						}
				}

				// Always include non-blocking, regardless of privacy
				if (this.options.value.non_blocking)
				{
						icons.push('<img src="' + this.egw().image('nonblocking', 'calendar') + '" title="' + this.egw().lang('non blocking') + '"/>');
				}
				return icons;
		}

		/**
		 * Bind the click handler for opening the video conference
		 *
		 * Tooltips are placed in the DOM directly in the body, managed by egw.
		 */
		_bind_videoconference()
		{
				let vc_event = 'click.calendar_videoconference';
				jQuery('body').off(vc_event)
						.on(vc_event, '[data-videoconference]', function (event)
						{
								let data = egw.dataGetUIDdata("calendar::" + this.dataset.id);
								app.calendar.joinVideoConference(this.dataset.videoconference, data.data || this.dataset);
						});
		}

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
		_get_timespan(event)
		{
				let timespan = '';
				if (event['start_m'] === 0 && event['end_m'] >= 24 * 60 - 1)
				{
						if (event['end_m'] > 24 * 60)
						{
								// @ts-ignore
							timespan = formatTime(event.start)
								// @ts-ignore
								.trim() + ' - ' + formatTime(event.end).trim();
						}
						else
						{
								timespan = this.egw().lang('Whole day');
						}
				}
				else
				{
						let duration: string | number = event.multiday ?
																						(event.end - event.start) / 60000 :
																						(event.end_m - event.start_m);
						duration = Math.floor(duration / 60) + this.egw().lang('h') + (duration % 60 ? duration % 60 : '');

						// @ts-ignore
					timespan = formatTime(event.start).trim();

						// @ts-ignore
					timespan += ' - ' + formatTime(event.end);

						timespan += ': ' + duration;
				}
				return timespan;
		}

		/**
		 * Make sure event data has all proper values, and format them as expected
		 * @param {Object} event
		 */
		_values_check(event)
		{
				// Make sure ID is a string
				if (event.id)
				{
						event.id = '' + event.id;
				}

				// Parent might be a daycol or a planner_row
				let parent = <et2_calendar_daycol>this.getParent();

				// Use dates as objects
				if (typeof event.start !== 'object')
				{
					event.start = parent.date_helper(event.start);
				}
				if (typeof event.end !== 'object')
				{
					event.end = parent.date_helper(event.end)
				}

				// We need minutes for durations
				if (typeof event.start_m === 'undefined')
				{
						event.start_m = event.start.getUTCHours() * 60 + event.start.getUTCMinutes();
						event.end_m = event.end.getUTCHours() * 60 + event.end.getUTCMinutes();
				}
				if (typeof event.multiday === 'undefined')
				{
						event.multiday = (event.start.getUTCFullYear() !== event.end.getUTCFullYear() ||
								event.start.getUTCMonth() !== event.end.getUTCMonth() ||
								event.start.getUTCDate() != event.end.getUTCDate());
				}
				if (!event.start.getUTCHours() && !event.start.getUTCMinutes() && event.end.getUTCHours() == 23 && event.end.getUTCMinutes() == 59)
				{
						event.whole_day_on_top = (event.non_blocking && event.non_blocking != '0');
				}
		}

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
		_sameday_check(event)
		{
				// Event somehow got orphaned, or deleted
				if (!this.getParent() || event === null)
				{
						return false;
				}

				// Also check participants against owner
				const owner_match = et2_calendar_event.owner_check(event, this.getParent());

				// Simple, same day
				if (owner_match && this.options.value.date && event.date == this.options.value.date)
				{
						return true;
				}

				// Multi-day non-recurring event spans days - date does not match
				const event_start = new Date(event.start);
				const event_end = new Date(event.end);
				const parent = this.getParent();
				if (owner_match && (parent instanceof et2_calendar_daycol) && parent.getDate() >= event_start && parent.getDate() <= event_end)
				{
						return true;
				}

				// Delete all old actions
				if (this._actionObject)
				{
						this._actionObject.clear();
						this._actionObject.unregisterActions();
						this._actionObject = null;
				}

				// Update daywise caches
				const new_cache_id = CalendarApp._daywise_cache_id(event.date, this.getParent().options.owner);
				let new_daywise: any = egw.dataGetUIDdata(new_cache_id);
				new_daywise = new_daywise && new_daywise.data ? new_daywise.data : [];
				let old_cache_id = '';
				if (this.options.value && this.options.value.date)
				{
						old_cache_id = CalendarApp._daywise_cache_id(this.options.value.date, parent.options.owner);
				}

				if (new_cache_id != old_cache_id)
				{
						let old_daywise: any = egw.dataGetUIDdata(old_cache_id);
						old_daywise = old_daywise && old_daywise.data ? old_daywise.data : [];
						old_daywise.splice(old_daywise.indexOf(this.options.value.row_id), 1);
						egw.dataStoreUID(old_cache_id, old_daywise);

						if (new_daywise.indexOf(event.row_id) < 0)
						{
								new_daywise.push(event.row_id);
						}
						if (egw.dataHasUID(new_cache_id))
						{
								egw.dataStoreUID(new_cache_id, new_daywise);
						}
				}

				return false;
		}

		/**
		 * Check that the event passes the given status filter.
		 * Status filter is set in the sidebox and used when fetching several events, but if user changes their status
		 * for an event, it may no longer match and have to be removed.
		 *
		 * @param event
		 * @param filter
		 * @param owner The owner of the target / parent, not the event owner
		 * @private
		 */
		_status_check(event, filter: string, owner: string | string[]): boolean
		{
				if (!owner || !event)
				{
						return false;
				}

				// If we're doing a bunch, just one passing is enough
				if (typeof owner !== "string")
				{
						let pass = false;
						for (let j = 0; j < owner.length && pass == false; j++)
						{
								pass = pass || this._status_check(event, filter, owner[j]);
						}
						return pass;
				}

				// Show also events just owned by selected user
				// Group members can be owner too, those get handled when we check group memberships below
				if (filter == 'owner' && owner == event.owner)
				{
						return true;
				}

				// Get the relevant participant
				let participant = event.participants[owner];

				// If filter says don't look in groups, skip it all
				if (!participant && filter === 'no-enum-groups')
				{
						return false;
				}

				// Couldn't find the current owner in the participant list, check groups & resources
				if (!participant)
				{
						let options: any = null;
						if (app.calendar && app.calendar.sidebox_et2 && app.calendar.sidebox_et2.getWidgetById('owner'))
						{
								options = app.calendar.sidebox_et2.getWidgetById('owner').select_options
						}
						if ((isNaN(parseInt(owner)) || parseInt(owner) < 0) && options && typeof options.find == "function")
						{
								let resource = options.find(function (element)
								{
									return element.value == owner;
								}) || {};
								let matching_participant = typeof resource.resources == "undefined" ?
																					 resource :
																					 resource?.resources.filter(id => typeof event.participants[id] != "undefined");
								if (matching_participant.length > 0)
								{
										return this._status_check(event, filter, matching_participant);
								}
								else if (filter == 'owner' && resource && resource.resources && resource.resources.indexOf(event.owner))
								{
										// owner param was a group but event is owned by someone in that group
										return true;
								}
						}
				}

				let status = et2_calendar_event.split_status(participant);

				switch (filter)
				{
						default:
						case 'all':
								return true;
						case 'default': // Show all status, but rejected
								return status !== 'R';
						case 'accepted': //Show only accepted events
								return status === 'A'
						case 'unknown': // Show only invitations, not yet accepted or rejected
								return status === 'U';
						case 'tentative': // Show only tentative accepted events
								return status === 'T';
						case 'delegated': // Show only delegated events
								return status === 'D';
						case 'rejected': // Show only rejected events
								return status === 'R';
						// Handled above
						//case 'owner': // Show also events just owned by selected user
						case 'hideprivate': // Show all events, as if they were private
								// handled server-side
								return true;
						case 'showonlypublic': // Show only events flagged as public, -not checked as private
								return event.public == '1';
						// Handled above
						// case 'no-enum-groups': // Do not include events of group members
						case 'not-unknown': // Show all status, but unknown
								return status !== 'U';
						case 'deleted': // Show events that have been deleted
								return event.deleted;
				}
		}

		attachToDOM()
		{
				let result = super.attachToDOM();

				// Remove the binding for the click handler, unless there's something
				// custom here.
				if (!this.onclick)
				{
						jQuery(this.node).off("click");
				}
				return result;
		}

		/**
		 * Click handler calling custom handler set via onclick attribute to this.onclick.
		 * All other handling is done by the timegrid widget.
		 *
		 * @param {Event} _ev
		 * @returns {boolean}
		 */
		click(_ev)
		{
				let result = true;
				if (typeof this.onclick == 'function')
				{
						// Make sure function gets a reference to the widget, splice it in as 2. argument if not
						const args = Array.prototype.slice.call(arguments);
						if (args.indexOf(this) == -1) args.splice(1, 0, this);

						result = this.onclick.apply(this, args);
				}
				return result;
		}

		/**
		 * Show the recur prompt for this event
		 *
		 * Calls et2_calendar_event.recur_prompt with this event's value.
		 *
		 * @param {et2_calendar_event~prompt_callback} callback
		 * @param {Object} [extra_data]
		 */
		recur_prompt(callback, extra_data)
		{
				et2_calendar_event.recur_prompt(this.options.value, callback, extra_data);
		}

		/**
		 * Show the series split prompt for this event
		 *
		 * Calls et2_calendar_event.series_split_prompt with this event's value.
		 *
		 * @param {et2_calendar_event~prompt_callback} callback
		 */
		series_split_prompt(callback)
		{
				et2_calendar_event.series_split_prompt(this.options.value, this.options.value.recur_date, callback);
		}

		/**
		 * Copy the actions set on the parent, apply them to self
		 *
		 * This can take a while to do, so we try to do it only when needed - on mouseover
		 */
		_copy_parent_actions()
		{
			// Copy actions set in parent
			if(!this.options.readonly && this.getParent() && !this.getParent().options.readonly)
			{
				let action_parent : et2_widget = this;
				while(action_parent != null && !action_parent.options.actions &&
					!(action_parent instanceof et2_container)
					)
				{
					action_parent = action_parent.getParent();
				}
				try
				{
					this._link_actions(action_parent.options.actions || {});
					this._need_actions_linked = false;
				}
				catch(e)
				{
					// something went wrong, but keep quiet about it
				}
			}
		}

		/**
		 * Link the actions to the DOM nodes / widget bits.
		 *
		 * @param {object} actions {ID: {attributes..}+} map of egw action information
		 */
		_link_actions(actions)
		{
				if (!this._actionObject)
				{
						// Get the top level element - timegrid or so
						var objectManager = this.getParent()._actionObject || this.getParent().getParent()._actionObject ||
								egw_getAppObjectManager(true).getObjectById(this.getParent().getParent().getParent().id) || egw_getAppObjectManager(true);
						this._actionObject = objectManager.getObjectById('calendar::' + this.options.value.row_id);
				}

				if (this._actionObject == null)
				{
						// Add a new container to the object manager which will hold the widget
						// objects
						this._actionObject = objectManager.insertObject(false, new egwActionObject(
								'calendar::' + this.options.value.row_id, objectManager, et2_calendar_event.et2_event_action_object_impl(this, this.getDOMNode()),
								this._actionManager || objectManager.manager.getActionById('calendar::' + this.options.value.row_id) || objectManager.manager
						));
				}
				else
				{
						this._actionObject.setAOI(et2_calendar_event.et2_event_action_object_impl(this, this.getDOMNode(this)));
				}

				// Delete all old objects
				this._actionObject.clear();
				this._actionObject.unregisterActions();

				// Go over the widget & add links - this is where we decide which actions are
				// 'allowed' for this widget at this time
				const action_links = this._get_action_links(actions);
				action_links.push('egw_link_drag');
				action_links.push('egw_link_drop');
				if (this._actionObject.parent.getActionLink('invite'))
				{
						action_links.push('invite');
				}
				this._actionObject.updateActionLinks(action_links);
		}

		/**
		 * Code for implementing et2_IDetachedDOM
		 *
		 * @param {array} _attrs array to add further attributes to
		 */
		getDetachedAttributes(_attrs)
		{

		}

		getDetachedNodes()
		{
				return [this.getDOMNode()];
		}

		setDetachedAttributes(_nodes, _values)
		{

		}

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
		 * @param {boolean} [owner_too] - Include the event owner in consideration, or only
		 *	event participants
		 *
		 * @return {boolean} Should the event be displayed
		 */
		static owner_check(event, parent, owner_too?)
		{
				let owner_match = true;
				let state = (parent.getInstanceManager ? parent.getInstanceManager().app_obj.calendar.state : false) || app.calendar?.state || {}
				if (typeof owner_too === 'undefined' && state.status_filter)
				{
						owner_too = state.status_filter === 'owner';
				}
				let options: any = null;
				if (app.calendar && app.calendar.sidebox_et2 && app.calendar.sidebox_et2.getWidgetById('owner'))
				{
					options = app.calendar.sidebox_et2.getWidgetById('owner').select_options;
				}
				else
				{
						options = parent.getArrayMgr("sel_options").getRoot().getEntry('owner');
				}
				if (event.participants && typeof parent.options.owner != 'undefined' && parent.options.owner.length > 0)
				{
					var parent_owner = jQuery.extend([], typeof parent.options.owner !== 'object' ?
						[parent.options.owner] :
														 parent.options.owner);
					owner_match = false;
					if(!options)
					{
						// Could not find the owner options.  Probably on home, just let it go.
						owner_match = true;
					}
					const length = parent_owner.length;
					for(var i = 0; i < length; i++)
					{
						// Handle groups & grouped resources like mailing lists, they won't match so
						// we need the list - pull it from sidebox owner
						if((isNaN(parent_owner[i]) || parent_owner[i] < 0) && options && typeof options.find == "function")
						{
							var resource = options.find(function(element)
							{
								return element.value == parent_owner[i];
							}) || {};
							if(resource && resource.resources)
							{
								parent_owner.splice(i, 1);
								i--;
								parent_owner = parent_owner.concat(resource.resources);

							}
						}
					}
					let participants = jQuery.extend([], Object.keys(event.participants));
						for (var i = 0; i < participants.length; i++)
						{
								const id = participants[i];
								// Expand group invitations
								if (parseInt(id) < 0)
								{
										// Add in groups, if we can get them from options, great
										var resource;
										if (options && options.find && (resource = options.find(function (element)
										{
											return element.value === id;
										})) && resource.resources)
										{
												participants = participants.concat(resource.resources);
										}
										else
										{
												// Add in groups, if we can get them (this is asynchronous)
												egw.accountData(id, 'account_id', true, function (members)
												{
														participants = participants.concat(Object.keys(members));
												}, this);
										}
								}
								if (parent.options.owner == id ||
										parent_owner.indexOf &&
										parent_owner.indexOf(id) >= 0)
								{
										owner_match = true;
										break;
								}
						}
				}
				if (owner_too && !owner_match)
				{
						owner_match = (parent.options.owner == event.owner ||
								parent_owner.indexOf &&
								parent_owner.indexOf(event.owner) >= 0);
				}
				return owner_match;
		}

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
		public static recur_prompt(event_data, callback?, extra_data?)
		{
				let egw;
				const edit_id = event_data.app_id;
				const edit_date = event_data.start;

				// seems window.opener somehow in certain conditions could be from different origin
				// we try to catch the exception and in this case retrieve the egw object from current window.
				try
				{
						egw = this.egw ? (typeof this.egw == 'function' ? this.egw() : this.egw) : window.opener && typeof window.opener.egw != 'undefined' ? window.opener.egw('calendar') : window.egw('calendar');
				}
				catch (e)
				{
						egw = window.egw('calendar');
				}

				const that = this;

				const extra_params = extra_data && typeof extra_data == 'object' ? extra_data : {};
				extra_params.date = edit_date.toJSON ? edit_date.toJSON() : edit_date;
				if (typeof callback != 'function')
				{
						callback = function (_button_id)
						{
								switch (_button_id)
								{
										case 'exception':
												extra_params.exception = '1';
												egw.open(edit_id, event_data.app || 'calendar', 'edit', extra_params);
												break;
										case 'series':
										case 'single':
												egw.open(edit_id, event_data.app || 'calendar', 'edit', extra_params);
												break;
										case 'cancel':
										default:
												break;
								}
						};
				}
				if (parseInt(event_data.recur_type))
				{
					const buttons = [
						{
							label: egw.lang("Edit exception"),
							id: "exception",
							class: "ui-priority-primary",
							default: true,
							image: 'edit'
						},
						{label: egw.lang("Edit series"), id: "series", image: 'recur'},
						{label: egw.lang("Cancel"), id: "cancel", image: 'cancel'}
					];
					Et2Dialog.show_dialog(
						function(button_id)
						{
							callback.call(that, button_id, event_data);
						},
						(!event_data.is_private ? event_data['title'] : egw.lang('private')) + "\n" +
						egw.lang("Do you want to edit this event as an exception or the whole series?"),
						"This event is part of a series", {}, buttons, Et2Dialog.QUESTION_MESSAGE,
						"", egw
					);
				}
				else
				{
						callback.call(this, 'single', event_data);
				}
		}

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
		public static series_split_prompt(event_data, instance_date, callback)
		{
				let egw;
				// seems window.opener somehow in certian conditions could be from different origin
				// we try to catch the exception and in this case retrieve the egw object from current window.
				try
				{
					egw = this.egw ? (typeof this.egw == 'function' ? this.egw() : this.egw) : window.opener && typeof window.opener.egw != 'undefined' ? window.opener.egw('calendar') : window.egw('calendar');
				}
				catch(e)
				{
					egw = window.egw('calendar');
				}

			const that = this;

			if(typeof instance_date == 'string')
			{
				instance_date = new Date(instance_date);
			}

			// Check for modifying a series that started before today
			const tempDate = new Date();
			const today = new Date(tempDate.getFullYear(), tempDate.getMonth(), tempDate.getDate(), tempDate.getHours(), -tempDate.getTimezoneOffset(), tempDate.getSeconds());
			const termination_date = instance_date < today ? egw.lang('today') : formatDate(instance_date);

			if(parseInt(event_data.recur_type))
			{
				Et2Dialog.show_dialog(
					function(button_id)
					{
						callback.call(that, button_id, event_data);
					},
					(!event_data.is_private ? event_data['title'] : egw.lang('private')) + "\n" +
					egw.lang("Do you really want to change the start of this series? If you do, the original series will be terminated as of %1 and a new series for the future reflecting your changes will be created.", termination_date),
					"This event is part of a series", {}, Et2Dialog.BUTTONS_OK_CANCEL, Et2Dialog.WARNING_MESSAGE
				);
			}
		}

		public static drag_helper(event, ui)
		{
				ui.helper.width(ui.width());
		}

		/**
		 * splits the combined status, quantity and role
		 *
		 * @param {string} status - combined value, O: status letter: U, T, A, R
		 * @param {int} [quantity] - quantity
		 * @param {string} [role]
		 * @return string status U, T, A or R, same as $status parameter on return
		 */
		public static split_status(status, quantity?, role?)
		{
				quantity = 1;
				role = 'REQ-PARTICIPANT';
				//error_log(__METHOD__.__LINE__.array2string($status));
				let matches = null;
				if (typeof status === 'string' && status.length > 1)
				{
						matches = status.match(/^.([0-9]*)(.*)$/gi);
				}
				if (matches)
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
		public static et2_event_action_object_impl(widget, node)
		{
				const aoi = new et2_action_object_impl(widget, node).getAOI();

				// _outerCall may be used to determine, whether the state change has been
				// evoked from the outside and the stateChangeCallback has to be called
				// or not.
				aoi.doSetState = function (_state, _outerCall)
				{
				};

				return aoi;
		}
}

et2_register_widget(et2_calendar_event, ["calendar-event"]);