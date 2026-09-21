/**
 * EGroupware - Notifications - Javascript UI
 *
 * @link https://www.egroupware.org
 * @package notifications
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

import {EgwApp} from '../../api/js/jsapi/egw_app';
import {Et2Dialog} from '../../api/js/etemplate/Et2Dialog/Et2Dialog';
// egw/app/framework are ambient globals (declare global {} in egw_global.d.ts, unconditionally
// included via tsconfig's "**/*.d.ts") - no import needed or possible.

/**
 * Time-range labels used to group messages in the popup list, oldest-message-first bucket wins
 */
export enum TimeLabel
{
	TODAY = 0,
	YESTERDAY = 1,
	THIS_MONTH = 2,
	LAST_MONTH = 3
}

/**
 * Priority a message can request via extra_data.egw_pr_notify - only HIGH has behaviour (auto-pop)
 */
export enum NotifyPriority
{
	HIGH = 1,
	MEDIUM = 2,
	LOW = 3
}

/**
 * One notification message, as delivered by notifications_ajax::get_notifications()/get_egwpopup()
 * (server-side row from egw_notificationpopup, HTML-rendered server-side into `message`)
 */
export interface NotifyMessage
{
	message : string;
	data : {
		app? : string;
		id? : string | number;
		url? : string;
		popup? : string;
		title? : string;
		message? : string;
		icon? : string;
		actions? : NotifyAction[];
		[key : string] : any;
	};
	status? : string;
	created : string;
	current : { date : string };
	extra_data : { [key : string] : any };
	id? : string | number;
	children? : { [id : string] : NotifyMessage };
}

interface NotifyAction
{
	onExecute : string;
	icon : string;
	caption : string;
}

/**
 * Raw row shape as sent by the server (notifications_ajax::get_egwpopup())
 */
interface NotifyRow
{
	id : string | number;
	message : string;
	status? : string;
	created : string;
	current : { date : string };
	extra_data : { [key : string] : any };
	actions? : NotifyAction[];
}

/**
 * Client-side of the notifications app - polls (or gets pushed, see egw.pushAvailable() and
 * run_notifications() below) for pending messages and renders the navbar bell + popup list.
 *
 * Unlike every other app.ts this one has no owning template/tab - it is bootstrapped directly by
 * notifications/inc/hook_after_navbar.inc.php's always-emitted include of this bundle on every
 * page, once the (async, preference-gated) bootstrap block at the bottom of this file decides the
 * user actually wants popup notifications. See doc/ai/projects/push-fallback-longpoll.md Phase 1.
 *
 * app.notifications.append() and app.notifications.tabToggle() are called from outside this file
 * (server-side push resp. kdots' EgwFramework.ts) - do not rename/re-sign them.
 */
export class NotificationsApp extends EgwApp
{
	private notifymessages : { [id : string] : NotifyMessage } = {};
	private _currentRawData : NotifyRow[] = [];

	/**
	 * Interval set by user (admin config popup_poll_interval, data-poll-interval attribute)
	 */
	private pollInterval = 60;

	/**
	 * Current interval, doubled on each failed request, reset implicitly on success
	 */
	private currentInterval = 60;

	private timeoutId = 0;

	/**
	 * Currently displayed app-filter, set by tabToggle(), cleared again once the popup closes
	 */
	private filter = '';

	/**
	 * Total number of (unseen+seen) messages last reported by the server
	 */
	private total = 0;

	private popupOpen = false;

	/**
	 * Unsubscribes from egw.onPushAvailabilityChange() - see the constructor and destroy()
	 */
	private unsubscribePush : () => void;

	/**
	 * Seconds between keep-alive polls while egw.pushAvailable() is true, or null if none are
	 * needed - server-computed (mail_hooks::needsNotificationCheckPolling(), via
	 * hook_after_navbar.inc.php's data-mail-check-interval attribute): does the user have a mail
	 * account with notify_folders configured? If so, run_notifications() must keep calling
	 * notifications.notifications_ajax.get_notifications() (which runs the check_notify hook)
	 * even once general push handles delivery, since neither Dovecot's nor JMAP's mail-server
	 * push currently triggers that notify_folders check itself - only polling does, independent
	 * of whether push is otherwise available. See doc/ai/projects/push-fallback-longpoll.md's
	 * "Mail notification-check polling" follow-up note.
	 */
	private mailCheckInterval : number | null = null;

	constructor()
	{
		super('notifications');

		const script = document.getElementById('notifications_script_id');
		this.currentInterval = this.pollInterval = parseInt(script?.getAttribute('data-poll-interval') || '60') || 60;
		this.mailCheckInterval = parseInt(script?.getAttribute('data-mail-check-interval') || '') || null;

		document.getElementById('notificationbell')?.addEventListener('click', () => this.toggle());

		const header = document.getElementById('egwpopup_header');
		if(header)
		{
			(<HTMLElement>header).style.cursor = 'pointer';
			header.setAttribute('title', this.egw.lang('Refresh Notifications'));
			header.addEventListener('click', () => this.run_notifications());
			header.querySelector('.button_right_toggle')?.setAttribute('title', this.egw.lang('close'));
		}

		// Static UI handlers live here, not the bootstrap block at the bottom of this file, since
		// this class can also get constructed by egw_json.ts's applyFunc() (a push message calling
		// app.notifications.append()/tabToggle() arriving before that block's async preference
		// check finishes) - wherever construction happens first must wire everything up.
		const topmenuNotifications = document.getElementById('topmenu_info_notifications');
		topmenuNotifications?.addEventListener('click', () => this.toggle());
		document.querySelectorAll('#egwpopup .button_right_toggle').forEach(
			(el) => el.addEventListener('click', () => this.toggle()));
		document.querySelector('#egwpopup .egwpopup_deleteall')?.addEventListener('click', (_ev) =>
		{
			_ev.stopPropagation();
			Et2Dialog.show_dialog((_button) =>
				{
					if(_button == Et2Dialog.YES_BUTTON) this.delete_all();
				},
				this.egw.lang('Are you sure you want to delete all notifications?'),
				this.egw.lang('Delete notifications'),
				null, Et2Dialog.BUTTONS_YES_NO, Et2Dialog.WARNING_MESSAGE, undefined, this.egw
			);
		});
		document.querySelector('#egwpopup .egwpopup_seenall')?.addEventListener('click', () => this.mark_all_seen());

		// Push dropping is the one transition we can't just wait for the next scheduled poll to
		// notice - run_notifications() itself skips polling outright while push is available (see
		// its own doc comment), so nothing would ever reschedule us again once it stops being
		// available unless we react to the transition here.
		this.unsubscribePush = this.egw.onPushAvailabilityChange((available) =>
		{
			if(!available) this.run_notifications();
		});

		this.timeoutId = this._setTimeout(10);	// defer first poll
	}

	destroy(_app)
	{
		this.unsubscribePush?.();
		super.destroy(_app);
	}

	/**
	 * Poll now, then reschedule - unless egw.pushAvailable() (see egw_json.ts's
	 * onPushAvailabilityChange()/pushAvailable(), doc/ai/projects/push-fallback-longpoll.md
	 * Phase 2) says a real push connection is currently delivering instead AND mailCheckInterval
	 * says we don't need this poll for anything else either, in which case this doesn't even
	 * poll at all. If push is available but mailCheckInterval IS set, this keeps polling - just
	 * at that slower cadence instead of pollInterval, since delivery itself is already handled
	 * and all that's still needed is to keep triggering the check_notify hook. The constructor's
	 * onPushAvailabilityChange() subscription is what resumes fast polling again once/if push
	 * stops being available - nothing else would, since a poll that never runs can't reschedule
	 * itself.
	 */
	run_notifications()
	{
		if(this.egw.pushAvailable() && !this.mailCheckInterval) return;

		this.get_notifications().then((_data) =>
		{
			window.clearTimeout(this.timeoutId);
			const pushAvailable = this.egw.pushAvailable();
			if(pushAvailable && !this.mailCheckInterval) return;
			this.check_browser_notify();
			this.timeoutId = this._setTimeout(pushAvailable ? this.mailCheckInterval : this.pollInterval);
		}, () =>
		{
			window.clearTimeout(this.timeoutId);
			this.currentInterval *= 2;
			this.timeoutId = this._setTimeout(this.currentInterval);
		});
	}

	/**
	 * Poll server for new notifications
	 */
	get_notifications() : Promise<any>
	{
		return new Promise((_resolve, _reject) =>
		{
			this.egw.json(
				"notifications.notifications_ajax.get_notifications", [],
				(_data) =>
				{
					_resolve(_data);
					this.check_browser_notify();
				}).sendRequest(true, 'POST', (_err) =>
			{
				if(_err && _err.statusText) this.egw.message(_err.statusText);
				_reject();
			});
		});
	}

	/**
	 * Poll server in given frequency via Ajax
	 */
	private _setTimeout(_i : number) : number
	{
		return window.setTimeout(() => this.run_notifications(), _i * 1000);
	}

	/**
	 * Check to see if browser supports / allows desktop notifications
	 */
	check_browser_notify()
	{
		return this.egw.checkNotification();
	}

	/**
	 * This function gets created time and current time then finds out the time range of the event.
	 */
	getTimeLabel(_created : string | Date, _current : { date : string }) : TimeLabel | ''
	{
		const created = typeof _created == 'string' ? new Date(_created) : _created;
		const current = new Date(_current.date);
		const time_diff = ((<any>current) - (<any>created)) / 1000;
		let result : TimeLabel | '' = '';
		if(time_diff < current.getHours() * 3600)
		{
			result = TimeLabel.TODAY;
		}
		else if((time_diff > current.getHours() * 3600) &&
			(time_diff < (current.getHours() * 3600 + 86400)))
		{
			result = TimeLabel.YESTERDAY;
		}
		else if(current.getFullYear() == created.getFullYear() &&
			(current.getMonth() - created.getMonth()) == 0 &&
			time_diff > (current.getHours() * 3600 + 86400))
		{
			result = TimeLabel.THIS_MONTH;
		}
		else if(current.getFullYear() == created.getFullYear() &&
			(current.getMonth() - created.getMonth()) == 1)
		{
			result = TimeLabel.LAST_MONTH;
		}
		return result;
	}

	/**
	 * Display notifications window: (re)renders #egwpopup_list from this.notifymessages
	 */
	display()
	{
		const list = document.getElementById('egwpopup_list');
		if(!list) return;

		// Preserve already popped-open (expanded) notifications across a re-render
		const popped : string[] = [];
		list.querySelectorAll('.egwpopup_expanded').forEach((item) =>
		{
			popped.push(item.id.replace('egwpopup_message_', ''));
		});

		list.replaceChildren();

		const timeLabels : { [key in TimeLabel]?: HTMLElement } = {};
		const timeLabelText = {
			[TimeLabel.TODAY]: this.egw.lang('today'),
			[TimeLabel.YESTERDAY]: this.egw.lang('yesterday'),
			[TimeLabel.THIS_MONTH]: this.egw.lang('this month'),
			[TimeLabel.LAST_MONTH]: this.egw.lang('last month')
		};

		// reverse order to get the latest messages at the top
		const ids = Object.keys(this.notifymessages).reverse();
		for(const id of ids)
		{
			const notification = this.notifymessages[id];
			const message_id = 'egwpopup_message_' + id;
			const time_label = this.getTimeLabel(notification.created, notification.current);

			if(document.getElementById(message_id))
			{
				this.update_message_status(id, notification.status, true);
				continue;
			}
			if(this.filter && notification.data.app != this.filter) continue;

			if(time_label !== '' && !timeLabels[time_label])
			{
				const label = document.createElement('div');
				label.className = 'egwpopup_time_label';
				label.textContent = timeLabelText[time_label];
				list.appendChild(label);
				timeLabels[time_label] = label;
			}

			const message = document.createElement('div');
			message.className = 'egwpopup_message';
			message.id = message_id;
			message.dataset.entryid = String(notification.data.id ?? '');
			message.dataset.appname = String(notification.data.app ?? '');

			const inner = document.createElement('div');
			inner.className = 'egwpopup_message_inner_container';
			inner.innerHTML = notification.message;
			if(notification.children)
			{
				const childIds = Object.keys(notification.children);
				childIds.forEach((c, index) =>
				{
					if(index < childIds.length - 2) return;
					inner.innerHTML += notification.children[c].message;
				});
			}
			message.appendChild(inner);

			const moreInfo = document.createElement('div');
			moreInfo.className = 'egwpopup_message_more_info';
			moreInfo.textContent = this.egw.lang('More info') + '...';
			message.appendChild(moreInfo);

			const topToolbar = document.createElement('div');
			topToolbar.className = 'egwpopup_message_top_toolbar';

			const date = document.createElement('span');
			date.className = 'egwpopup_message_date';
			date.textContent = notification.created;
			topToolbar.appendChild(date);

			if(notification.data.id || notification.data.url)
			{
				const openEntry = document.createElement('span');
				openEntry.className = 'egwpopup_message_open bi-search';
				openEntry.title = this.egw.lang('open notified entry');
				openEntry.addEventListener('click', (e) => this.open_entry(message, e));
				topToolbar.insertBefore(openEntry, topToolbar.firstChild);
			}

			const navPrev = document.createElement('span');
			navPrev.className = 'egwpopup_nav_prev';
			navPrev.title = this.egw.lang('previous');
			navPrev.addEventListener('click', (e) => this.nav_button(message, 'prev', e));
			topToolbar.insertBefore(navPrev, topToolbar.firstChild);

			const navNext = document.createElement('span');
			navNext.className = 'egwpopup_nav_next';
			navNext.title = this.egw.lang('next');
			navNext.addEventListener('click', (e) => this.nav_button(message, 'next', e));
			topToolbar.insertBefore(navNext, topToolbar.firstChild);

			const del = document.createElement('span');
			del.className = 'egwpopup_delete';
			del.title = this.egw.lang('delete this message');
			del.addEventListener('click', (e) => this.button_delete(message, e));
			topToolbar.insertBefore(del, topToolbar.firstChild);

			const mark = document.createElement('span');
			mark.className = 'egwpopup_mark';
			topToolbar.insertBefore(mark, topToolbar.firstChild);

			const collapse = document.createElement('span');
			collapse.className = 'egwpopup_collapse';
			collapse.addEventListener('click', (e) => this.collapseMessage(message, e));
			topToolbar.insertBefore(collapse, topToolbar.firstChild);

			if(notification.status != 'SEEN')
			{
				mark.title = this.egw.lang('mark as read');
				mark.addEventListener('click', (e) => this.message_seen(message, e));
			}

			// Activate links rendered into the message by the server
			message.querySelectorAll('div[data-id],div[data-url]').forEach((link : HTMLElement) =>
			{
				link.classList.add('et2_link');
				link.addEventListener('click', () =>
				{
					if(link.dataset.id)
					{
						this.egw.open(link.dataset.id, link.dataset.app);
					}
					else
					{
						this.egw.open_link(link.dataset.url, '_blank', link.dataset.popup);
					}
				});
			});

			if(notification.data.actions && notification.data.actions.length > 0)
			{
				const actionsContainer = document.createElement('div');
				actionsContainer.className = 'egwpopup_actions_container';
				for(const action of notification.data.actions)
				{
					const func = new Function(action.onExecute);
					const icon = this.egw.image(action.icon, notification.data.app);
					const bootstrap = icon?.match(/\/node_modules\/bootstrap-icons\/icons\/([^.]+)\.svg/);

					const button = document.createElement('button');
					button.className = 'et2_button';
					if(bootstrap)
					{
						button.classList.add('bi-' + bootstrap[1]);
					}
					else
					{
						button.style.backgroundImage = 'url(' + icon + ')';
					}
					button.textContent = action.caption;
					button.addEventListener('click', () => func.call(this, message));
					actionsContainer.insertBefore(button, actionsContainer.firstChild);
				}
				message.appendChild(actionsContainer);
			}
			message.insertBefore(topToolbar, message.firstChild);
			list.appendChild(message);

			message.addEventListener('click', (e) => this.clickOnMessage(message, e));
			this.update_message_status(id, notification.status, true);

			if(notification.extra_data && !notification.status && notification.extra_data.egw_pr_notify)
			{
				switch(notification.extra_data.egw_pr_notify)
				{
					case NotifyPriority.HIGH:
						const status_app = (<any>window).app?.status;
						if(notification.extra_data.videoconference && notification.extra_data['alarm-offset'] <= 300 &&
							status_app && typeof status_app.scheduled_receivedCall == 'function')
						{
							status_app.scheduled_receivedCall({
								url: notification.extra_data.videoconference,
								account_id: notification.extra_data.account_id,
								avatar: 'account:' + notification.extra_data.account_id,
								title: notification.data.title,
								owner: notification.extra_data.name
							});
						}
						else
						{
							this.toggle(true);
						}
						popped.push(String(id));
						break;
					case NotifyPriority.MEDIUM:
					case NotifyPriority.LOW:
					// Could be defined with all sort of stuff
				}
			}
		}

		// re-pop messages that were expanded before this re-render
		popped.forEach((id) => document.getElementById('egwpopup_message_' + id)?.dispatchEvent(new MouseEvent('click', {bubbles: true})));

		this.counterUpdate();
	}

	/**
	 * Opens the relevant entry from a clicked message
	 */
	open_entry(_node : HTMLElement, _event : Event)
	{
		_event.stopPropagation();
		this.message_seen(_node, _event);
		const id = _node.id.replace(/egwpopup_message_/ig, '');
		const data = this.notifymessages[id]?.data;
		if(data)
		{
			if(data.id)
			{
				this.egw.open(data.id, data.app);
			}
			else
			{
				this.egw.open_link(data.url, '_blank', data.popup);
			}
		}
	}

	/**
	 * Reposition the expanded message back in the list & removes the clone node
	 */
	collapseMessage(_node : HTMLElement, _event : Event)
	{
		_event.stopPropagation();
		const cloned = _node.previousElementSibling;
		if(cloned && cloned.id == _node.id + '_expanded')
		{
			cloned.remove();
		}
		_node.classList.remove('egwpopup_expanded');
		_node.style.zIndex = '0';
		this.checkNavButtonStatus();
		this.egw.loading_prompt('popup_notifications', false);
	}

	/**
	 * Expand a clicked message into bigger view
	 */
	clickOnMessage(_node : HTMLElement, _event : Event)
	{
		// Do not run the click handler if it's already expanded, or a button inside was clicked
		if(_node.classList.contains('egwpopup_expanded') || (<HTMLElement>_event.target).classList?.contains('et2_button')) return;

		this.message_seen(_node, _event);
		const clone = <HTMLElement>_node.cloneNode();
		clone.id = _node.id + '_expanded';
		clone.classList.add('egwpopup_message_clone');
		_node.parentNode.insertBefore(clone, _node);

		const zindex = document.querySelectorAll('.egwpopup_expanded').length;
		_node.classList.add('egwpopup_expanded');
		_node.style.zIndex = String(zindex + 1);
		this.checkNavButtonStatus();

		const popup = document.getElementById('egwpopup');
		if(popup && getComputedStyle(popup).display !== 'none' &&
			!(typeof egwIsMobile == "function" && egwIsMobile()))
		{
			this.egw.loading_prompt('popup_notifications', true);
		}
	}

	nav_button(_node : HTMLElement, _direction : 'prev' | 'next', _event : Event)
	{
		const expanded = Array.from(document.querySelectorAll('.egwpopup_expanded'));
		const messages = Array.from(document.querySelectorAll('.egwpopup_message')).filter(
			(m) => !m.classList.contains('egwpopup_message_clone'));

		expanded.forEach((item : HTMLElement) => this.collapseMessage(item, _event));

		const current = messages.findIndex((m) => m.id == _node.id);
		const target = _direction === 'prev' ? messages[current - 1] : messages[current + 1];
		(<HTMLElement>target)?.click();
	}

	checkNavButtonStatus()
	{
		const expanded = Array.from(document.querySelectorAll<HTMLElement>('.egwpopup_expanded'));
		const messages = Array.from(document.querySelectorAll<HTMLElement>('.egwpopup_message')).filter(
			(m) => !m.classList.contains('egwpopup_message_clone'));
		if(expanded.length === 0) return;

		expanded.forEach((item) => item.classList.remove('egwpopup_nav_disable'));

		let top = expanded[0];
		expanded.forEach((item) =>
		{
			if(parseInt(item.style.zIndex || '0') > parseInt(top.style.zIndex || '0'))
			{
				top = item;
			}
		});

		if(top === messages[0])
		{
			top.querySelector('.egwpopup_nav_prev')?.classList.add('egwpopup_nav_disable');
		}
		if(top === messages[messages.length - 1])
		{
			top.querySelector('.egwpopup_nav_next')?.classList.add('egwpopup_nav_disable');
		}
	}

	/**
	 * Display or hide the notification-bell icon
	 */
	bell(mode : 'active' | 'inactive')
	{
		const bell = <HTMLElement>document.getElementById('notificationbell');
		if(bell) bell.style.display = mode == 'active' ? 'inline' : 'none';
	}

	/**
	 * Callback for OK button: confirms message on server and hides display
	 */
	message_seen(_node : HTMLElement, _event : Event)
	{
		_event.stopPropagation();
		const id = _node.id.replace(/egwpopup_message_/ig, '');
		const notification = this.notifymessages[id];
		if(notification && notification.status != 'SEEN')
		{
			this.egw.json("notifications.notifications_ajax.update_status", [[notification], "SEEN"]).sendRequest(true);
			this.update_message_status(id, "SEEN");
			if(notification.extra_data.onSeenAction)
			{
				const func = new Function(notification.extra_data.onSeenAction);
				func.apply(this, [notification.extra_data]);
			}
		}
	}

	mark_all_seen()
	{
		if(!this.notifymessages || Object.keys(this.notifymessages).length == 0) return false;

		this.egw.json("notifications.notifications_ajax.update_status", [this.notifymessages, "SEEN"]).sendRequest(true);
		for(const id in this.notifymessages)
		{
			this.update_message_status(id, "SEEN");
		}
	}

	update_message_status(_id : string, _status : string, _noCounterUpdate? : boolean)
	{
		const message = document.getElementById('egwpopup_message_' + _id);
		this.notifymessages[_id].status = _status;
		if(message)
		{
			switch(_status)
			{
				case 'SEEN':
					message.classList.add('egwpopup_message_seen');
					break;
				case 'UNSEEN':
				case 'DISPLAYED':
					message.classList.remove('egwpopup_message_seen');
					break;
			}
		}
		if(!_noCounterUpdate) this.counterUpdate();
	}

	delete_all()
	{
		if(!this.notifymessages || Object.keys(this.notifymessages).length == 0) return false;

		const app_ids : { [app : string] : (string | number)[] } = {};
		for(const id in this.notifymessages)
		{
			const notification = this.notifymessages[id];
			if(notification.data?.id && notification.data.app)
			{
				(app_ids[notification.data.app] ??= []).push(notification.data.id);
			}
		}
		this.egw.request("notifications.notifications_ajax.delete_message", [Object.keys(this.notifymessages), app_ids]);
		this.notifymessages = {};
		this._currentRawData = [];	// otherwise a response from delete_message/get_notifications might not get parsed
		this.total = 0;
		this.counterUpdate();
		document.getElementById('egwpopup_list')?.replaceChildren();
		this.egw.loading_prompt('popup_notifications', false);
		this.bell('inactive');
	}

	/**
	 * Callback for close button: delete a single message
	 */
	button_delete(_node : HTMLElement, _event : Event)
	{
		_event.stopPropagation();
		const id = _node.id.replace(/egwpopup_message_/ig, '');
		const notification = this.notifymessages[id];
		const app_ids : { [app : string] : (string | number)[] } = {};
		if(notification.data?.id) app_ids[notification.data.app] = [notification.data.id];

		this.egw.request("notifications.notifications_ajax.delete_message", [[id], app_ids]);

		const next = _node.nextElementSibling as HTMLElement;
		let keepLoadingPrompt = false;
		delete this.notifymessages[id];
		this.total -= 1;
		this.counterUpdate();
		if(next && /egwpopup_message_/.test(next.id) && _node.classList.contains('egwpopup_expanded'))
		{
			next.click();
			keepLoadingPrompt = true;
		}
		// try to close the dialog if expanded before hiding it
		this.collapseMessage(_node, _event);
		if(keepLoadingPrompt && !(typeof egwIsMobile == "function" && egwIsMobile()))
		{
			this.egw.loading_prompt('popup_notifications', true);
		}
		_node.remove();
		this.bell('inactive');
	}

	/**
	 * Find all children ids from notifications
	 */
	findAllChildrenIds() : (string | number)[]
	{
		let ids : (string | number)[] = [];
		for(const i in this.notifymessages)
		{
			ids = ids.concat(this.findChildrenIds(this.notifymessages[i]));
		}
		return ids;
	}

	/**
	 * Find children ids of a given (parent) notification
	 */
	findChildrenIds(_notification : NotifyMessage) : string[]
	{
		if(_notification.children)
		{
			return Object.keys(_notification.children);
		}
		return [];
	}

	/**
	 * Find a potential parent notification for a given entry
	 */
	findParent(_id : string | number, _app : string) : string | undefined
	{
		if(!_id && !_app) return undefined;
		for(const i in this.notifymessages)
		{
			if(this.notifymessages[i].data.id == _id && this.notifymessages[i].data.app == _app)
			{
				return i;
			}
		}
		return undefined;
	}

	/**
	 * Add message(s) to internal display-queue
	 *
	 * Called both by the regular polling response (notifications_ajax::get_notifications()) and by
	 * server-side push (Api\Json\Push, incl. the SQL fallback replay - see
	 * doc/ai/projects/push-fallback-longpoll.md) - keep this name/signature stable.
	 */
	append(_rawData : NotifyRow[], _browser_notify? : boolean, _total? : number)
	{
		const hasUnseen : (string | number)[] = [];
		_rawData = _rawData || [];

		// Don't reprocess identical data - rendering the HTML content can get expensive
		if(this._currentRawData.length && this._currentRawData.length == _rawData.length &&
			!_rawData.some((d, i) => d.id != this._currentRawData[i].id))
		{
			return;
		}
		this._currentRawData = _rawData;
		const old_notifymessages = this.notifymessages;
		this.notifymessages = {};
		const browser_notify = _browser_notify || this.check_browser_notify();
		this.total = _total || 0;

		for(const row of _rawData)
		{
			const data = this.getData(row.message, row.extra_data);
			const parent = this.findParent(data.id, data.app);
			if(parent && typeof row.extra_data.egw_pr_notify == 'undefined')
			{
				if(parent == String(row.id)) continue;
				this.notifymessages[parent].children ??= {};
				this.notifymessages[parent].children[row.id] = {
					message: row.message,
					data: data,
					status: row.status,
					created: row.created,
					current: row.current,
					extra_data: row.extra_data
				};
				if(row.actions && row.actions.length > 0)
				{
					this.notifymessages[parent].children[row.id].data.actions = row.actions;
				}
				continue;
			}

			// Prevent the same thing popping up multiple times
			this.notifymessages[row.id] = {
				message: row.message,
				data: data,
				status: row.status,
				created: row.created,
				current: row.current,
				extra_data: row.extra_data,
				id: row.id
			};
			if(row.actions && row.actions.length > 0)
			{
				this.notifymessages[row.id].data.actions = row.actions;
			}

			// Notification API
			if(browser_notify && !row.status)
			{
				this.egw.notification(data.title, {
					tag: data.app + ":" + row.id,
					body: data.message,
					icon: data.icon,
					requireInteraction: true,
					onclose: (e) =>
					{
						const id = (<any>e.target).tag.split(":");
						this.egw.json("notifications.notifications_ajax.update_status", [[id[1]], 'DISPLAYED']).sendRequest();
					},
					onclick: (e) =>
					{
						const id = (<any>e.target).tag.split(":");
						const notify = this.notifymessages[id[1]];
						if(!notify)
						{
							(<any>e.target).close();
							return;
						}
						if(notify.data?.id)
						{
							this.egw.open(notify.data.id, notify.data.app);
						}
						else if(notify.data)
						{
							this.egw.open_link(notify.data.url, '_blank', notify.data.popup);
						}
						(<any>e.target).close();
					}
				});
			}
			if(!row.status)
			{
				this.egw.json("notifications.notifications_ajax.update_status", [[row.id], 'DISPLAYED']).sendRequest();
				hasUnseen.push(row.id);
			}
		}

		const egwpopup = document.getElementById('egwpopup');
		switch(this.egw.preference('egwpopup_verbosity', 'notifications'))
		{
			case 'low':
				if(Object.keys(this.notifymessages).length > 0 && this.counterUpdate() > 0)
				{
					this.bell('active');
				}
				break;
			case 'high':
				if(hasUnseen.length > 0)
				{
					alert(this.egw.lang('EGroupware has notifications for you'));
					this.egw.json("notifications.notifications_ajax.update_status", [hasUnseen, 'DISPLAYED']).sendRequest();
				}
				if(egwpopup && egwpopup.style.display != 'none')
				{
					this.display();
				}
				else
				{
					this.counterUpdate();
				}
				break;
			case 'medium':
				if(egwpopup && egwpopup.style.display != 'none' &&
					Object.keys(old_notifymessages).length != Object.keys(this.notifymessages).length)
				{
					this.display();
				}
				else
				{
					this.counterUpdate();
				}
		}
	}

	/**
	 * Extract useful data out of a server-rendered HTML message
	 */
	getData(_message : string, _extra_data? : { [key : string] : any }) : NotifyMessage['data']
	{
		const dom = new DOMParser().parseFromString(_message, 'text/html');
		const link = dom.querySelector<HTMLElement>('div[data-id],div[data-url]');

		const data : NotifyMessage['data'] = {
			message: dom.body.textContent,
			title: link?.textContent,
			icon: link?.querySelector('img')?.getAttribute('src')
		};
		return Object.assign(data, link ? {...link.dataset} : {}, _extra_data || {});
	}

	/**
	 * Called from kdots' EgwFramework.ts when a per-app tab notification badge is clicked -
	 * filters the popup to that app's messages and opens it, if there's at least one
	 *
	 * @return true if a matching notification was found (and the popup opened for it)
	 */
	tabToggle(_appname : string) : boolean
	{
		for(const i in this.notifymessages)
		{
			if(this.notifymessages[i].extra_data.app == _appname)
			{
				this.filter = _appname;
				this.toggle();
				this.display();
				return true;
			}
		}
		return false;
	}

	/**
	 * Toggle the notifications popup open/closed
	 *
	 * @param _stat true: keep the popup open (used when auto-popping a HIGH priority message)
	 */
	toggle(_stat? : boolean)
	{
		const popup = document.getElementById('egwpopup');
		const counter = document.getElementById('topmenu_info_notifications');
		if(!popup) return;

		if(!_stat) this.display();

		if(!this.popupOpen)
		{
			const outsideClick = (e : MouseEvent) =>
			{
				const target = <Node>e.target;
				if(!(counter?.contains(target) || counter === target) &&
					!(popup.contains(target) || popup === target))
				{
					document.body.removeEventListener('click', outsideClick);
					this.filter = '';
					this.popupOpen = false;
					popup.style.display = 'none';
					this.egw.loading_prompt('popup_notifications', false);
				}
			};
			document.body.addEventListener('click', outsideClick);
			this.egw.loading_prompt('popup_notifications', document.querySelectorAll('#egwpopup_list .egwpopup_expanded').length > 0);
		}
		else
		{
			this.filter = '';
			this.egw.loading_prompt('popup_notifications', false);
			if(_stat) return;
		}

		this.popupOpen = !this.popupOpen;
		popup.style.display = this.popupOpen ? '' : 'none';
	}

	/**
	 * Set new state of notifications counter (bell badge + per-tab badges)
	 *
	 * @return number of unseen messages
	 */
	counterUpdate() : number
	{
		const fw = typeof framework !== 'undefined' ? framework : undefined;
		const topmenu = document.getElementById('topmenu_info_notifications');
		let counter = 0;
		const apps : { [app : string] : number } = {};

		const header = document.getElementById('egwpopup_header');
		if(header?.childNodes[0]) header.childNodes[0].textContent = this.egw.lang("Notifications") + " (" + this.total + ")";
		if(topmenu) topmenu.title = this.egw.lang('total') + ":" + this.total;

		for(const id in this.notifymessages)
		{
			const app = this.notifymessages[id].extra_data.app;
			if(typeof apps[app] == 'undefined') apps[app] = 0;
			if(this.notifymessages[id].status != 'SEEN')
			{
				counter++;
				apps[app] += 1;
			}
		}
		if(counter > 0)
		{
			for(const app in apps)
			{
				fw?.tabNotification?.(app, apps[app]);
			}
			topmenu?.classList.add('egwpopup_notify');
			fw?.topmenu_info_notify?.('notifications', true, counter, this.egw.lang('You have %1 unread notifications', counter));
		}
		else
		{
			fw?.openApplications?.forEach((app) => fw.tabNotification?.(app.name, 0));
			fw?.topmenu_info_notify?.('notifications', false);
		}
		return counter;
	}
}

app.classes.notifications = NotificationsApp;

// Bootstrap: gated behind the user's notification_chain preference (and langRequire), same as the
// jQuery script this replaces - if popup notifications are off, we hide the bell and never
// construct the app, so no polling happens either.
//
// window.app.notifications can already exist by the time this runs: a push message
// (app.notifications.append()/tabToggle()) arriving first is auto-constructed on demand by
// egw_json.ts's applyFunc() (same "not yet instantiated" path every other app.ts goes through) -
// harmless here, since a push only ever gets sent to an account whose notification_chain already
// includes the popup channel (see notifications_popup::send()), ie. one that would pass the
// preference check below anyway. Either way, only ever construct once.
(<any>window).egw_ready.then(() =>
{
	const langRequireAttr = document.getElementById('notifications_script_id')?.getAttribute('data-langRequire');
	const langRequire = langRequireAttr ? JSON.parse(langRequireAttr) : {};

	Promise.all([
		egw.langRequire(window, [langRequire]),
		egw.preference('notification_chain', 'notifications', true)
	]).then(() =>
	{
		switch(egw.preference('notification_chain', 'notifications'))
		{
			case 'popup_only':
			case 'popup_and_email':
			case 'popup_or_email':
			case 'all':
				break;
			default:
				document.getElementById('topmenu_info_notifications')?.style.setProperty('display', 'none');
				return;
		}

		window.onbeforeunload = () =>
		{
			if(typeof egw.killAliveNotifications == 'function') egw.killAliveNotifications();
		};

		window.app.notifications ||= new NotificationsApp();
	});
});
