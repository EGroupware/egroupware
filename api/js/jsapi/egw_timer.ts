/**
 * EGroupware clientside API object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 */

import './egw_core';
import {sprintf} from "../egw_action/egw_action_common";

interface TimerState
{
	start? : Date;
	paused? : boolean;
	offset? : number;
	started? : Date;
	started_id? : string;
	last? : Date;
	id? : string;
	app_id? : string;
}

interface TimerServerState
{
	disable : string[];
	overall : {
		start? : string | Date;
		offset? : number;
		started? : string | Date;
		started_id? : string;
		paused? : boolean;
		last? : string | Date;
		id? : string;
	};
	specific : {
		start? : string | Date;
		offset? : number;
		started? : string | Date;
		started_id? : string;
		paused? : boolean;
		last? : string | Date;
		id? : string;
		app_id? : string;
	};
}

export interface TimerModule
{
	/**
	 * Change/overwrite time
	 *
	 * @param _ev
	 * @param _widget
	 */
	change_timer(_ev : PointerEvent, _widget : any) : void;

	/**
	 * Start, Pause or Stop clicked in timer-dialog
	 *
	 * @param _ev
	 * @param _button
	 */
	timer_button(_ev : Event, _button : any) : boolean;

	/**
	 * Start timer for given app and id
	 *
	 * @param _action
	 * @param _senders
	 */
	start_timer(_action : any, _senders : any[]) : void;

	/**
	 * Create timer in top-menu
	 *
	 * @param _parent parent to create selectbox in
	 */
	add_timer(_parent : string) : void;

	/**
	 * Ask user to stop working time
	 *
	 * @returns resolved once user answered, to continue logout
	 */
	onLogout_timer() : Promise<void>;
}

declare global
{
	interface IegwGlobal extends TimerModule
	{
	}
}

/**
 * Format a time according to user preference
 *
 * Cant import from DateTime.ts, gives an error ;)
 *
 * Stateless (only touches its own params plus bare egw.preference()), so
 * stays a plain module-scope function rather than a class member.
 *
 * @param date
 * @param options object containing attribute timeFormat=12|24, default user preference
 */
function formatTime(date : Date, options? : {timeFormat? : string})
{
	if(!date || !(date instanceof Date))
	{
		return "";
	}
	let _value = '';

	let timeformat = options?.timeFormat || egw.preference("timeformat") || "24";
	let hours = (timeformat == "12" && date.getUTCHours() > 12) ? (date.getUTCHours() - 12) : date.getUTCHours();
	if(timeformat == "12" && hours == 0)
	{
		// 00:00 is 12:00 am
		hours = 12;
	}

	_value = (timeformat == "24" && hours < 10 ? "0" : "") + hours + ":" +
		(date.getUTCMinutes() < 10 ? "0" : "") + (date.getUTCMinutes()) +
		(timeformat == "24" ? "" : (date.getUTCHours() < 12 ? " am" : " pm"));

	return _value;
}

/**
 * Format a UTC time according to user preference
 *
 * Stateless, stays module-scope like formatTime().
 *
 * @param date
 */
function formatUTCTime(date : Date)
{
	// eT2 operates in user-time, while timers here always operate in UTC
	return formatTime(new Date(date.valueOf() - egw.getTimezoneOffset() * 60000));
}

/**
 * Get start, pause and stop time of timer to display in UI
 *
 * Stateless (only touches its own _timer param plus bare
 * egw.getTimezoneOffset()), stays module-scope too.
 *
 * @param _timer
 * @return with attributes start, pause, stop
 */
function getTimes(_timer : TimerState)
{
	const started = _timer.started ? new Date(_timer.started.valueOf() - egw.getTimezoneOffset() * 60000) : undefined;
	const last = _timer.last ? new Date(_timer.last.valueOf() - egw.getTimezoneOffset() * 60000) : undefined;
	return {
		start: started,
		paused: _timer.paused ? last : undefined,
		stop: !_timer.start && !_timer.paused ? last : undefined
	};
}

class Timer implements TimerModule
{
	/**
	 * Overall timer state
	 */
	#overall : TimerState = {};
	/**
	 * Specific timer state
	 */
	#specific : TimerState = {};
	/**
	 * Disable config with values "overall", "specific" or "overwrite"
	 */
	#disable : string[] = [];
	/**
	 * Timer container in top-menu
	 */
	#timerNode : HTMLElement = document.querySelector('#topmenu_timer');
	/**
	 * Reference from setInterval to stop periodic update
	 */
	#timerInterval : number;
	/**
	 * Reference to open dialog or undefined if not open
	 */
	#dialog : any;

	/**
	 * Set state of timer
	 *
	 * @param _state
	 */
	private setState(_state : TimerServerState)
	{
		this.#disable = _state.disable;
		// initiate overall timer
		this.startTimer(this.#overall, _state.overall?.start, _state.overall?.offset);	// to show offset / paused time
		this.#overall.started = _state.overall?.started ? new Date(_state.overall.started) : undefined;
		this.#overall.started_id = _state.overall?.started_id;
		if (_state.overall?.paused)
		{
			this.stopTimer(this.#overall, true);
		}
		else if (!_state.overall?.start)
		{
			this.stopTimer(this.#overall);
		}
		this.#overall.last = _state.overall.last ? new Date(_state.overall.last) : undefined;
		this.#overall.id = _state.overall?.id;

		// initiate specific timer, only if running or paused
		if (_state.specific?.start || _state.specific?.paused)
		{
			this.startTimer(this.#specific, _state.specific?.start, _state.specific?.offset, _state.specific.app_id);	// to show offset / paused time
			this.#specific.started = _state.specific?.started ? new Date(_state.specific.started) : undefined;
			this.#specific.started_id = _state.specific?.started_id;
			this.#specific.id = _state.specific.id;
			if (_state.specific?.paused)
			{
				this.stopTimer(this.#specific, true);
			}
			else if (!_state.specific?.start)
			{
				this.stopTimer(this.#specific);
			}
		}
		this.#specific.last = _state.specific.last ? new Date(_state.specific.last) : undefined;
		this.#specific.id = _state.specific?.id;
	}

	/**
	 * Get state of timer
	 * @param _action last action
	 * @param _time time to report
	 */
	private getState(_action : string, _time? : string | Date)
	{
		return {
			action: _action,
			ts: new Date(_time || new Date),
			overall: this.#overall,
			specific: this.#specific
		}
	}

	/**
	 * Run timer action eg. start/stop
	 *
	 * @param _action
	 * @param _time
	 * @param _app_id
	 * @return Promise from egw.request() to wait for state being persisted on server
	 * @throws string error-message
	 */
	private timerAction(_action : string, _time? : string | Date, _app_id? : string)
	{
		const [type, action] = _action.split('-');
		switch(_action)
		{
			case 'overall-start':
				this.startTimer(this.#overall, _time);
				break;

			case 'overall-pause':
				this.stopTimer(this.#overall,true, _time);
				if (this.#specific?.start) this.stopTimer(this.#specific, true, _time);
				break;

			case 'overall-stop':
				this.stopTimer(this.#overall, false, _time);
				if (this.#specific?.start) this.stopTimer(this.#specific, false, _time);
				break;

			case 'specific-start':
				if (this.#overall?.paused) this.startTimer(this.#overall, _time);
				this.startTimer(this.#specific, _time);
				break;

			case 'specific-pause':
				this.stopTimer(this.#specific,true, _time);
				break;

			case 'specific-stop':
				this.stopTimer(this.#specific, false, _time);
				break;
		}
		// set _app_id on timer, if specified
		if (_app_id && type === 'specific')
		{
			this.#specific.app_id = _app_id;
		}
		// persist state
		return egw.request('timesheet.EGroupware\\Timesheet\\Events.ajax_event', [this.getState(_action, _time)]).then((tse_id) =>
		{
			const timer = type === 'specific' ? this.#specific : this.#overall;
			// do NOT set/change timer.id, if a paused timer get stopped (to show and update paused time, not irrelevant stop)
			if (timer.start || typeof timer.paused !== 'undefined')
			{
				timer.id = tse_id;
			}
			if (action === 'start')
			{
				timer.started_id = tse_id;
			}
			if (_action === 'specific-stop')
			{
				let type : 'add' | 'edit' = 'add';
				let extra : {events : string, ts_id? : string} = {events: 'specific'};
				if (this.#specific.app_id && this.#specific.app_id.substring(0, 11) === 'timesheet::')
				{
					extra.ts_id = this.#specific.app_id.substring(11);
					type = 'edit';
				}
				egw.open(null, 'timesheet', type, extra);

				// unset the app_id and the tse_id to not associate the next start with it
				this.#specific.app_id = undefined;
			}
		});
	}

	/**
	 * Enable/disable buttons based on timer state
	 */
	private setButtonState()
	{
		if (!this.#dialog) return;

		// disable not matching / available menu-items
		this.#dialog.querySelectorAll('et2-button').forEach(button =>
		{
			if (button.id.substring(0, 7) === 'overall')
			{
				// timer running: disable only start, enable pause and stop
				if (this.#overall?.start)
				{
					button.disabled = button.id === 'overall[start]';
				}
				// timer paused: disable pause, enable start and stop
				else if (this.#overall?.paused)
				{
					button.disabled = button.id === 'overall[pause]';
				}
				// timer stopped: disable stop and pause, enable start
				else
				{
					button.disabled = button.id !== 'overall[start]';
				}
			}
			else if (button.id.substring(0, 8) === 'specific')
			{
				// timer running: disable only start, enable pause and stop
				if (this.#specific?.start)
				{
					button.disabled = button.id === 'specific[start]';
				}
				// timer paused: disable pause, enable start and stop
				else if (this.#specific?.paused)
				{
					button.disabled = button.id === 'specific[pause]';
				}
				// timer stopped: disable stop and pause, enable start
				else
				{
					button.disabled = button.id !== 'specific[start]';
				}
			}
		});
	}

	/**
	 * Update the timer DOM node according to _timer state
	 *
	 * @param _node
	 * @param _timer
	 */
	private updateTimer(_node : HTMLElement, _timer : TimerState)
	{
		let sep = ':';
		let diff = Math.round((_timer.offset || 0) / 60000.0)
		if (_timer.start)
		{
			const now = Math.round((new Date()).valueOf() / 1000.0);
			sep = now % 2 ? ' ' : ':';
			diff = Math.round((now - Math.round(_timer.start.valueOf() / 1000.0)) / 60.0);
		}
		_node.textContent = (<any>sprintf)('%d%s%02d', (diff / 60)|0, sep, diff % 60);
		// set CSS classes accordingly
		_node.classList.toggle('running', !!_timer.start);
		_node.classList.toggle('paused', _timer.paused || false);
		_node.classList.toggle('overall', _timer === this.#overall);
	}

	/**
	 * Update all timers: topmenu and dialog (if open)
	 *
	 * A private arrow field, not a regular method: passed bare to
	 * window.setInterval() in startTimer() below, so it needs a stable,
	 * already-`this`-bound reference (same reasoning as egw_ready.ts's
	 * #readyEventHandler).
	 */
	#update = () =>
	{
		// topmenu only shows either specific, if running or paused, or the overall timer
		this.updateTimer(this.#timerNode, this.#specific.start || this.#specific.paused ? this.#specific : this.#overall);

		// if dialog is open, it shows both timers
		if (this.#dialog)
		{
			const specific_timer = this.#dialog.querySelector('div#_specific_timer');
			const overall_timer = this.#dialog.querySelector('div#_overall_timer');
			if (specific_timer)
			{
				this.updateTimer(specific_timer, this.#specific);
			}
			if (overall_timer)
			{
				this.updateTimer(overall_timer, this.#overall);
			}
		}
	}

	/**
	 * Start given timer
	 *
	 * @param _timer
	 * @param _start to initialise with time different from current time
	 * @param _offset to set an offset
	 */
	private startTimer(_timer : TimerState, _start? : string | Date, _offset? : number, _app_id? : string)
	{
		_timer.started = _start ? new Date(_start) : new Date();
		_timer.started.setSeconds(0, 0);	// only use full minutes, as this is what we display
		if (_timer.last && _timer.started.valueOf() < _timer.last.valueOf())
		{
			throw egw.lang('Start-time can not be before last stop- or pause-time %1!', formatUTCTime(_timer.last));
		}
		// update _timer state object
		_timer.start = new Date(<any>(_timer.last = _timer.started));

		if (_offset || _timer.offset && _timer.paused)
		{
			_timer.start.setMilliseconds(_timer.start.getMilliseconds()-(_offset || _timer.offset));
		}
		_timer.offset = 0;	// it's now set in start-time
		_timer.paused = false;
		_timer.app_id = undefined;

		// update now
		this.#update();

		// initiate periodic update, if not already runing
		if (!this.#timerInterval)
		{
			this.#timerInterval = window.setInterval(this.#update, 1000);
		}
	}

	/**
	 * Stop or pause timer
	 *
	 * If specific timer is stopped, it will automatically display the overall timer, if running or paused
	 *
	 * @param _timer
	 * @param _pause true: pause, else: stop
	 * @param _time stop-time, default current time
	 * @throws string error-message when timer.start < _time
	 */
	private stopTimer(_timer : TimerState, _pause? : boolean, _time? : string | Date)
	{
		const time = _time ? new Date(_time) : new Date();
		time.setSeconds(0, 0);	// only use full minutes, as this is what we display
		if (_timer.last && time.valueOf() < _timer.last.valueOf())
		{
			const last_time = formatUTCTime(_timer.last);
			if (_timer.start)
			{
				throw egw.lang('Stop- or pause-time can not be before the start-time %1!', last_time);
			}
			else
			{
				throw egw.lang('Start-time can not be before last stop- or pause-time %1!', last_time);
			}
		}
		// update _timer state object
		if (_timer.start)
		{
			_timer.offset = time.valueOf() - _timer.start.valueOf();
			_timer.start = undefined;
		}
		// if we stop an already paused timer, we keep the paused event as last, not the stop
		if (_timer.paused)
		{
			_timer.paused = _pause || undefined;
		}
		else
		{
			_timer.last = time;
			_timer.paused = _pause || false;
		}
		// update timer display
		this.updateTimer(this.#timerNode, _timer);

		// if dialog is shown, update its timer(s) too
		if (this.#dialog)
		{
			const specific_timer = this.#dialog.querySelector('div#_specific_timer');
			const overall_timer = this.#dialog?.querySelector('div#_overall_timer');
			if (specific_timer && _timer === this.#specific)
			{
				this.updateTimer(specific_timer, this.#specific)
			}
			if (overall_timer && _timer === this.#overall)
			{
				this.updateTimer(overall_timer, this.#overall);
			}
		}

		// stop periodic update, only if NO more timer is running
		if (this.#timerInterval && !this.#specific.start && !this.#overall.start)
		{
			window.clearInterval(this.#timerInterval);
			this.#timerInterval = undefined;
		}
	}

	/**
	 * Open the timer dialog to start/stop timers
	 *
	 * @param _title default "Start & stop timer"
	 */
	private timerDialog(_title? : string)
	{
		// Pass egw in the constructor
		this.#dialog = new (<any>window).Et2Dialog(egw);

		// Set attributes.  They can be set in any way, but this is convenient.
		this.#dialog.transformAttributes({
			// If you use a template, the second parameter will be the value of the template, as if it were submitted.
			callback: (button_id, value) =>		// return false to prevent dialog closing
			{
				this.#dialog = undefined;
			},
			id: "timer_dialog",
			title: _title || 'Start & stop timer',
			template: egw.webserverUrl + '/timesheet/templates/default/timer.xet',
			buttons: [
				{label: egw.lang("Close"), id: "close", default: true, image: "cancel"},
			],
			value: {
				content: {
					disable: this.#disable.join(':'),
					times: {
						specific: getTimes(this.#specific),
						overall: getTimes(this.#overall)
					}
				},
				sel_options: {}
			}
		});
		// Add to DOM, dialog will auto-open
		document.body.appendChild(this.#dialog);
		this.#dialog.updateComplete.then(() =>
		{
			// enable/disable buttons based on timer state
			this.setButtonState();
			// update timers in dialog
			this.#update();
		});
	}

	/**
	 * Update times displayed under buttons
	 */
	private updateTimes()
	{
		if (!this.#dialog) return;

		const times = {
			specific: getTimes(this.#specific),
			overall: getTimes(this.#overall)
		};

		// disable not matching / available menu-items
		this.#dialog.querySelectorAll('et2-date-time-today').forEach(_widget =>
		{
			const [, timer, action] = _widget.id.match(/times\[([^\]]+)\]\[([^\]]+)\]/);
			_widget.value = times[timer][action];
		});
	}

	/**
	 * Change/overwrite time
	 *
	 * Only touches this instance's own state via the private helpers above
	 * (called as this.xxx(), correctly resolving since this is a plain arrow
	 * field), no dynamic dispatch through `this` - arrow field.
	 *
	 * @param _ev
	 * @param _widget
	 */
	change_timer = (_ev : PointerEvent, _widget : any) =>
	{
		// if there is no value, or timer overwrite is disabled --> ignore click
		if (!_widget?.value || this.#disable.indexOf('overwrite') !== -1) {
			return;
		}
		const [, which, action] = _widget.id.match(/times\[([^\]]+)\]\[([^\]]+)\]/);
		const timer = which === 'overall' ? this.#overall : this.#specific;
		const tse_id = timer[action === 'start' ? 'started_id' : 'id'];
		const dialog : any = new (<any>window).Et2Dialog(egw);

		// Set attributes.  They can be set in any way, but this is convenient.
		dialog.transformAttributes({
			callback: (_button, _values) => {
				const change = (new Date(_widget.value)).valueOf() - (new Date(_values.time)).valueOf();
				if (_button === (<any>window).Et2Dialog.OK_BUTTON && change)
				{
					_widget.value = _values.time;
					timer[action === 'start' ? 'started' : action] = new Date((new Date(_values.time)).valueOf() + egw.getTimezoneOffset() * 60000);
					// for a stopped or paused timer, we need to adjust the offset (duration) and the displayed timer too
					if (timer.offset)
					{
						timer.offset -= action === 'start' ? -change : change;
						this.#update();
						// for stop/pause set last time, otherwise we might not able to start again directly after
						if (action !== 'start')
						{
							timer.last = new Date(<any>timer[action]);
						}
					}
					// for a running timer, we need to adjust the (virtual) start too
					else if (timer.start)
					{
						timer.start = new Date(timer.start.valueOf() - change);
						// for running timer set last time, otherwise we might not able to stop directly after
						timer.last = new Date(timer.start);
					}
					egw.request('timesheet.EGroupware\\Timesheet\\Events.ajax_updateTime',
						[tse_id, new Date((new Date(_values.time)).valueOf() + egw.getTimezoneOffset() * 60000)])
				}
			},
			title: egw.lang('Change time'),
			template: 'timesheet.timer.change',
			buttons: (<any>window).Et2Dialog.BUTTONS_OK_CANCEL,
			value: {
				content: { time: _widget.value }
			}
		});
		// Add to DOM, dialog will auto-open
		document.body.appendChild(dialog);
	}

	/**
	 * Start, Pause or Stop clicked in timer-dialog
	 *
	 * Only touches this instance's own state, no dynamic dispatch - arrow
	 * field.
	 *
	 * @param _ev
	 * @param _button
	 */
	timer_button = (_ev : Event, _button : any) =>
	{
		const value = this.#dialog.value;
		try {
			this.timerAction(_button.id.replace(/^([a-z]+)\[([a-z]+)\]$/, '$1-$2'),
				// eT2 operates in user-time, while timers here always operate in UTC
				value.time ? new Date((new Date(value.time)).valueOf() + egw.getTimezoneOffset() * 60000) : undefined);
		}
		catch (e) {
			(<any>window).Et2Dialog.alert(e, egw.lang('Invalid Input'), (<any>window).Et2Dialog.ERROR_MESSAGE);
		}
		this.setButtonState();
		this.updateTimes();
		return false;
	}

	/**
	 * Start timer for given app and id
	 *
	 * Only touches this instance's own state, no dynamic dispatch - arrow
	 * field.
	 *
	 * @param _action
	 * @param _senders
	 */
	start_timer = (_action : any, _senders : any[]) =>
	{
		if (_action.parent.data.nextmatch?.getSelection().all || _senders.length !== 1)
		{
			egw.message(egw.lang('You must select a single entry!'), 'error');
			return;
		}
		// timer already running, ask user if he wants to associate it with the entry, or cancel
		if (this.#specific.start || this.#specific.paused)
		{
			(<any>window).Et2Dialog.show_dialog((_button) => {
					if (_button === (<any>window).Et2Dialog.OK_BUTTON)
					{
						if (this.#specific.paused)
						{
							this.timerAction('specific-start', undefined, _senders[0].id);
						}
						else
						{
							this.#specific.app_id = _senders[0].id;
							egw.request('timesheet.EGroupware\\Timesheet\\Events.ajax_updateAppId', [this.#specific.id, this.#specific.app_id]);
						}
					}
				},
				egw.lang('Do you want to associate it with the selected %1 entry?', egw.lang(_senders[0].id.split('::')[0])),
				egw.lang('Timer already running or paused'), {},
				(<any>window).Et2Dialog.BUTTONS_OK_CANCEL, (<any>window).Et2Dialog.QUESTION_MESSAGE, undefined, egw);
			return;
		}
		this.timerAction('specific-start', undefined, _senders[0].id);
	}

	/**
	 * Create timer in top-menu
	 *
	 * `this.preference(...)` dispatches to whichever egw(app,wnd) instance
	 * the caller used, so this needs dynamic `this` - hence the
	 * `self`-capture for reaching this instance's own state and private
	 * helpers (setState()/timerDialog()/timerAction()).
	 *
	 * @param _parent parent to create selectbox in
	 */
	add_timer = ((self : Timer) => function(this : any, _parent : string)
	{
		const timer_container = document.getElementById(_parent);
		if (!timer_container) return;

		// set state if given
		const timer = document.getElementById('topmenu_timer');
		const state : TimerServerState = timer && timer.getAttribute('data-state') ? JSON.parse(timer.getAttribute('data-state')) : undefined;
		if (timer && state)
		{
			self.setState(state);
		}

		// bind click handler
		timer_container.addEventListener('click', (ev) => {
			self.timerDialog();
		});

		// check if overall working time is not disabled
		if (state && state.disable.indexOf('overall') === -1)
		{
			// we need to wait that all JS is loaded
			(<any>window).egw_ready.then(() => { window.setTimeout(() =>
			{
				// check if we should ask on login to start working time
				this.preference('workingtime_session', 'timesheet', true).then(pref =>
				{
					if (pref === 'no') return;

					// overall timer not running, ask to start
					if (self.#overall && !self.#overall.start && !(<any>state.overall).dont_ask)
					{
						(<any>window).Et2Dialog.show_dialog((button) => {
							if (button === (<any>window).Et2Dialog.YES_BUTTON)
							{
								self.timerAction('overall-start');
							}
							else
							{
								egw.request('EGroupware\\Timesheet\\Events::ajax_dontAskAgainWorkingTime', <any>(button !== (<any>window).Et2Dialog.NO_BUTTON));
							}
						}, 'Do you want to start your working time?', 'Working time', {}, 		[
							{button_id: (<any>window).Et2Dialog.YES_BUTTON, label: egw.lang('yes'), id: 'dialog[yes]', image: 'check', "default": true},
							{button_id: (<any>window).Et2Dialog.NO_BUTTON, label: egw.lang('no'), id: 'dialog[no]', image: 'cancel'},
							{button_id: "dont_ask_again", label: egw.lang("Don't ask again!"), id: 'dialog[dont_ask_again]', image:'save', align: "right"}
						]);
					}
					// overall timer running for more than 16 hours, ask to stop
					else if (self.#overall?.start && (((new Date()).valueOf() - self.#overall.start.valueOf()) / 3600000) >= 16)
					{
						self.timerDialog('Forgot to switch off working time?');
					}
				});

			}, 2000)});
		}
	})(this);

	/**
	 * Ask user to stop working time
	 *
	 * Only touches this instance's own state, no dynamic dispatch - arrow
	 * field.
	 *
	 * @returns resolved once user answered, to continue logout
	 */
	onLogout_timer = () : Promise<void> =>
	{
		let promise : Promise<void>;
		if (this.#overall.start || this.#overall.paused)
		{
			promise = new Promise((_resolve, _reject) =>
			{
				(<any>window).Et2Dialog.show_dialog((button) => {
					if (button === (<any>window).Et2Dialog.YES_BUTTON)
					{
						this.timerAction('overall-stop').then(_resolve);
					}
					else
					{
						_resolve();
					}
				}, 'Do you want to stop your working time?', 'Working time', {}, (<any>window).Et2Dialog.BUTTONS_YES_NO);
			});
		}
		else
		{
			promise = Promise.resolve();
		}
		return promise;
	}
}

egw.extend('timer', egw.MODULE_GLOBAL, () => new Timer());
