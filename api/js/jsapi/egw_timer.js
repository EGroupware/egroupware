/**
 * EGroupware clientside API object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 */

import './egw_core.js';
import {sprintf} from "../egw_action/egw_action_common";

egw.extend('timer', egw.MODULE_GLOBAL, function()
{
	"use strict";

	/**
	 * Overall timer state
	 */
	let overall = {};
	/**
	 * Specific timer state
	 */
	let specific = {};
	/**
	 * Disable config with values "overall", "specific" or "overwrite"
	 * @type {string[]}
	 */
	let disable = [];
	/**
	 * Timer container in top-menu
	 * @type {Element}
	 */
	const timer = document.querySelector('#topmenu_timer');
	/**
	 * Reference from setInterval to stop periodic update
	 */
	let timer_interval;
	/**
	 * Reference to open dialog or undefined if not open
	 * @type {Et2-dialog}
	 */
	let dialog;

	/**
	 * Set state of timer
	 *
	 * @param _state
	 */
	function setState(_state)
	{
		disable = _state.disable;
		// initiate overall timer
		startTimer(overall, _state.overall?.start, _state.overall?.offset);	// to show offset / paused time
		overall.started = _state.overall?.started ? new Date(_state.overall.started) : undefined;
		overall.started_id = _state.overall?.started_id;
		if (_state.overall?.paused)
		{
			stopTimer(overall, true);
		}
		else if (!_state.overall?.start)
		{
			stopTimer(overall);
		}
		overall.last = _state.overall.last ? new Date(_state.overall.last) : undefined;
		overall.id = _state.overall?.id;

		// initiate specific timer, only if running or paused
		if (_state.specific?.start || _state.specific?.paused)
		{
			startTimer(specific, _state.specific?.start, _state.specific?.offset, _state.specific.app_id);	// to show offset / paused time
			specific.started = _state.specific?.started ? new Date(_state.specific.started) : undefined;
			specific.started_id = _state.specific?.started_id;
			specific.id = _state.specific.id;
			if (_state.specific?.paused)
			{
				stopTimer(specific, true);
			}
			else if (!_state.specific?.start)
			{
				stopTimer(specific);
			}
		}
		specific.last = _state.specific.last ? new Date(_state.specific.last) : undefined;
		specific.id = _state.specific?.id;
	}

	/**
	 * Get state of timer
	 * @param string _action last action
	 * @param string|Date|undefined _time time to report
	 * @returns {{action: string, overall: {}, specific: {}, ts: Date}}
	 */
	function getState(_action, _time)
	{
		return {
			action: _action,
			ts: new Date(_time || new Date),
			overall: overall,
			specific: specific
		}
	}

	/**
	 * Run timer action eg. start/stop
	 *
	 * @param {string} _action
	 * @param {string} _time
	 * @param {string} _app_id
	 * @return Promise from egw.request() to wait for state being persisted on server
	 * @throws string error-message
	 */
	function timerAction(_action, _time, _app_id)
	{
		const [type, action] = _action.split('-');
		switch(_action)
		{
			case 'overall-start':
				startTimer(overall, _time);
				break;

			case 'overall-pause':
				stopTimer(overall,true, _time);
				if (specific?.start) stopTimer(specific, true, _time);
				break;

			case 'overall-stop':
				stopTimer(overall, false, _time);
				if (specific?.start) stopTimer(specific, false, _time);
				break;

			case 'specific-start':
				if (overall?.paused) startTimer(overall, _time);
				startTimer(specific, _time);
				break;

			case 'specific-pause':
				stopTimer(specific,true, _time);
				break;

			case 'specific-stop':
				stopTimer(specific, false, _time);
				break;
		}
		// set _app_id on timer, if specified
		if (_app_id && type === 'specific')
		{
			specific.app_id = _app_id;
		}
		// persist state
		return egw.request('timesheet.EGroupware\\Timesheet\\Events.ajax_event', [getState(_action, _time)]).then((tse_id) =>
		{
			const timer = type === 'specific' ? specific : overall;
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
				let type = 'add';
				let extra = {events: 'specific'};
				if (specific.app_id && specific.app_id.substring(0, 11) === 'timesheet::')
				{
					extra.ts_id = specific.app_id.substring(11);
					type = 'edit';
				}
				egw.open(null, 'timesheet', type, extra);

				// unset the app_id and the tse_id to not associate the next start with it
				specific.app_id = undefined;
			}
		});
	}

	/**
	 * Enable/disable buttons based on timer state
	 */
	function setButtonState()
	{
		if (!dialog) return;

		// disable not matching / available menu-items
		dialog.querySelectorAll('et2-button').forEach(button =>
		{
			if (button.id.substring(0, 7) === 'overall')
			{
				// timer running: disable only start, enable pause and stop
				if (overall?.start)
				{
					button.disabled = button.id === 'overall[start]';
				}
				// timer paused: disable pause, enable start and stop
				else if (overall?.paused)
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
				if (specific?.start)
				{
					button.disabled = button.id === 'specific[start]';
				}
				// timer paused: disable pause, enable start and stop
				else if (specific?.paused)
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
	 * @param {DOMNode} _node
	 * @param {object} _timer
	 */
	function updateTimer(_node, _timer)
	{
		let sep = ':';
		let diff = Math.round((_timer.offset || 0) / 60000.0)
		if (_timer.start)
		{
			const now = Math.round((new Date()).valueOf() / 1000.0);
			sep = now % 2 ? ' ' : ':';
			diff = Math.round((now - Math.round(_timer.start.valueOf() / 1000.0)) / 60.0);
		}
		_node.textContent = sprintf('%d%s%02d', (diff / 60)|0, sep, diff % 60);
		// set CSS classes accordingly
		_node.classList.toggle('running', !!_timer.start);
		_node.classList.toggle('paused', _timer.paused || false);
		_node.classList.toggle('overall', _timer === overall);
	}

	/**
	 * Update all timers: topmenu and dialog (if open)
	 */
	function update()
	{
		// topmenu only shows either specific, if running or paused, or the overall timer
		updateTimer(timer, specific.start || specific.paused ? specific : overall);

		// if dialog is open, it shows both timers
		if (dialog)
		{
			const specific_timer = dialog.querySelector('div#_specific_timer');
			const overall_timer = dialog.querySelector('div#_overall_timer');
			if (specific_timer)
			{
				updateTimer(specific_timer, specific);
			}
			if (overall_timer)
			{
				updateTimer(overall_timer, overall);
			}
		}
	}


	/**
	 * Start given timer
	 *
	 * @param object _timer
	 * @param string|Date|undefined _start to initialise with time different from current time
	 * @param number|undefined _offset to set an offset
	 */
	function startTimer(_timer, _start, _offset)
	{
		_timer.started = _start ? new Date(_start) : new Date();
		_timer.started.setSeconds(0);	// only use full minutes, as this is what we display
		if (_timer.last && _timer.started.valueOf() < _timer.last.valueOf())
		{
			throw egw.lang('Start-time can not be before last stop- or pause-time %1!', formatUTCTime(_timer.last));
		}
		// update _timer state object
		_timer.start = new Date(_timer.last = _timer.started);

		if (_offset || _timer.offset && _timer.paused)
		{
			_timer.start.setMilliseconds(_timer.start.getMilliseconds()-(_offset || _timer.offset));
		}
		_timer.offset = 0;	// it's now set in start-time
		_timer.paused = false;
		_timer.app_id = undefined;

		// update now
		update();

		// initiate periodic update, if not already runing
		if (!timer_interval)
		{
			timer_interval = window.setInterval(update, 1000);
		}
	}

	/**
	 * Stop or pause timer
	 *
	 * If specific timer is stopped, it will automatically display the overall timer, if running or paused
	 *
	 * @param object _timer
	 * @param bool|undefined _pause true: pause, else: stop
	 * @param string|Date|undefined _time stop-time, default current time
	 * @throws string error-message when timer.start < _time
	 */
	function stopTimer(_timer, _pause, _time)
	{
		const time = _time ? new Date(_time) : new Date();
		time.setSeconds(0);	// only use full minutes, as this is what we display
		if (time.valueOf() < _timer.last.valueOf())
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
			if (time.valueOf() < _timer.start.valueOf())
			{
			}
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
		updateTimer(timer, _timer);

		// if dialog is shown, update its timer(s) too
		if (dialog)
		{
			const specific_timer = dialog.querySelector('div#_specific_timer');
			const overall_timer = dialog?.querySelector('div#_overall_timer');
			if (specific_timer && _timer === specific)
			{
				updateTimer(specific_timer, specific)
			}
			if (overall_timer && _timer === overall)
			{
				updateTimer(overall_timer, overall);
			}
		}

		// stop periodic update, only if NO more timer is running
		if (timer_interval && !specific.start && !overall.start)
		{
			window.clearInterval(timer_interval);
			timer_interval = undefined;
		}
	}

	/**
	 * Format a time according to user preference
	 *
	 * Cant import from DateTime.ts, gives an error ;)
	 *
	 * @param {Date} date
	 * @param {Object|undefined} options object containing attribute timeFormat=12|24, default user preference
	 * @returns {string}
	 */
	function formatTime(date, options)
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
	 * @param {Date} date
	 * @returns {string}
	 */
	function formatUTCTime(date)
	{
		// eT2 operates in user-time, while timers here always operate in UTC
		return formatTime(new Date(date.valueOf() - egw.getTimezoneOffset() * 60000));
	}

	/**
	 * Open the timer dialog to start/stop timers
	 *
	 * @param {string} _title default "Start & stop timer"
	 */
	function timerDialog(_title)
	{
		// Pass egw in the constructor
		dialog = new Et2Dialog(egw);

		// Set attributes.  They can be set in any way, but this is convenient.
		dialog.transformAttributes({
			// If you use a template, the second parameter will be the value of the template, as if it were submitted.
			callback: (button_id, value) =>		// return false to prevent dialog closing
			{
				dialog = undefined;
			},
			id: "timer_dialog",
			title: _title || 'Start & stop timer',
			template: egw.webserverUrl + '/timesheet/templates/default/timer.xet',
			buttons: [
				{label: egw.lang("Close"), id: "close", default: true, image: "cancel"},
			],
			value: {
				content: {
					disable: disable.join(':'),
					times: {
						specific: getTimes(specific),
						overall: getTimes(overall)
					}
				},
				sel_options: {}
			}
		});
		// Add to DOM, dialog will auto-open
		document.body.appendChild(dialog);
		dialog.updateComplete.then(() =>
		{
			// enable/disable buttons based on timer state
			setButtonState();
			// update timers in dialog
			update();
		});
	}

	/**
	 * Update times displayed under buttons
	 */
	function updateTimes()
	{
		if (!dialog) return;

		const times = {
			specific: getTimes(specific),
			overall: getTimes(overall)
		};

		// disable not matching / available menu-items
		dialog.querySelectorAll('et2-date-time-today').forEach(_widget =>
		{
			const [, timer, action] = _widget.id.match(/times\[([^\]]+)\]\[([^\]]+)\]/);
			_widget.value = times[timer][action];
		});
	}

	/**
	 * Get start, pause and stop time of timer to display in UI
	 *
	 * @param {Object} _timer
	 * @return {Object} with attributes start, pause, stop
	 */
	function getTimes(_timer)
	{
		const started = _timer.started ? new Date(_timer.started.valueOf() - egw.getTimezoneOffset() * 60000) : undefined;
		const last = _timer.last ? new Date(_timer.last.valueOf() - egw.getTimezoneOffset() * 60000) : undefined;
		return {
			start: started,
			paused: _timer.paused ? last : undefined,
			stop: !_timer.start && !_timer.paused ? last : undefined
		};
	}

	return {
		/**
		 * Change/overwrite time
		 *
		 * @param {PointerEvent} _ev
		 * @param {Et2DateTimeToday} _widget
		 */
		change_timer: function(_ev, _widget)
		{
			// if there is no value, or timer overwrite is disabled --> ignore click
			if (!_widget?.value || disable.indexOf('overwrite') !== -1) {
				return;
			}
			const [, which, action] = _widget.id.match(/times\[([^\]]+)\]\[([^\]]+)\]/);
			const timer = which === 'overall' ? overall : specific;
			const tse_id = timer[action === 'start' ? 'started_id' : 'id'];
			const dialog = new Et2Dialog(egw);

			// Set attributes.  They can be set in any way, but this is convenient.
			dialog.transformAttributes({
				callback: (_button, _values) => {
					const change = (new Date(_widget.value)).valueOf() - (new Date(_values.time)).valueOf();
					if (_button === Et2Dialog.OK_BUTTON && change)
					{
						_widget.value = _values.time;
						timer[action === 'start' ? 'started' : action] = new Date((new Date(_values.time)).valueOf() + egw.getTimezoneOffset() * 60000);
						// for a stopped or paused timer, we need to adjust the offset (duration) and the displayed timer too
						if (timer.offset)
						{
							timer.offset -= action === 'start' ? -change : change;
							update();
							// for stop/pause set last time, otherwise we might not able to start again directly after
							if (action !== 'start')
							{
								timer.last = new Date(timer[action]);
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
				buttons: Et2Dialog.BUTTONS_OK_CANCEL,
				value: {
					content: { time: _widget.value }
				}
			});
			// Add to DOM, dialog will auto-open
			document.body.appendChild(dialog);
		},

		/**
		 * Start, Pause or Stop clicked in timer-dialog
		 *
		 * @param {Event} _ev
		 * @param {Et2Button} _button
		 */
		timer_button: function(_ev, _button)
		{
			const value = dialog.value;
			try {
				timerAction(_button.id.replace(/^([a-z]+)\[([a-z]+)\]$/, '$1-$2'),
					// eT2 operates in user-time, while timers here always operate in UTC
					value.time ? new Date((new Date(value.time)).valueOf() + egw.getTimezoneOffset() * 60000) : undefined);
			}
			catch (e) {
				Et2Dialog.alert(e, egw.lang('Invalid Input'), Et2Dialog.ERROR_MESSAGE);
			}
			setButtonState();
			updateTimes();
			return false;
		},

		/**
		 * Start timer for given app and id
		 *
		 * @param {Object} _action
		 * @param {Array} _senders
		 */
		start_timer: function(_action, _senders)
		{
			if (_action.parent.data.nextmatch?.getSelection().all || _senders.length !== 1)
			{
				egw.message(egw.lang('You must select a single entry!'), 'error');
				return;
			}
			// timer already running, ask user if he wants to associate it with the entry, or cancel
			if (specific.start || specific.paused)
			{
				Et2Dialog.show_dialog((_button) => {
						if (_button === Et2Dialog.OK_BUTTON)
						{
							if (specific.paused)
							{
								timerAction('specific-start', undefined, _senders[0].id);
							}
							else
							{
								specific.app_id = _senders[0].id;
								egw.request('timesheet.EGroupware\\Timesheet\\Events.ajax_updateAppId', [specific.id, specific.app_id]);
							}
						}
					},
					egw.lang('Do you want to associate it with the selected %1 entry?', egw.lang(_senders[0].id.split('::')[0])),
					egw.lang('Timer already running or paused'), {},
					Et2Dialog.BUTTONS_OK_CANCEL, Et2Dialog.QUESTION_MESSAGE, undefined, egw);
				return;
			}
			timerAction('specific-start', undefined, _senders[0].id);
		},

		/**
		 * Create timer in top-menu
		 *
		 * @param {string} _parent parent to create selectbox in
		 */
		add_timer: function(_parent)
		{
			const timer_container = document.getElementById(_parent);
			if (!timer_container) return;

			// set state if given
			const timer = document.getElementById('topmenu_timer');
			const state = timer && timer.getAttribute('data-state') ? JSON.parse(timer.getAttribute('data-state')) : undefined;
			if (timer && state)
			{
				setState(state);
			}

			// bind click handler
			timer_container.addEventListener('click', (ev) => {
				timerDialog();
			});

			// check if overall working time is not disabled
			if (state.disable.indexOf('overall') === -1)
			{
				// we need to wait that all JS is loaded
				window.egw_ready.then(() => { window.setTimeout(() =>
				{
					// check if we should ask on login to start working time
					this.preference('workingtime_session', 'timesheet', true).then(pref =>
					{
						if (pref === 'no') return;

						// overall timer not running, ask to start
						if (overall && !overall.start && !state.overall.dont_ask)
						{
							Et2Dialog.show_dialog((button) => {
								if (button === Et2Dialog.YES_BUTTON)
								{
									timerAction('overall-start');
								}
								else
								{
									egw.request('EGroupware\\Timesheet\\Events::ajax_dontAskAgainWorkingTime', button !== Et2Dialog.NO_BUTTON);
								}
							}, 'Do you want to start your working time?', 'Working time', {}, 		[
								{button_id: Et2Dialog.YES_BUTTON, label: egw.lang('yes'), id: 'dialog[yes]', image: 'check', "default": true},
								{button_id: Et2Dialog.NO_BUTTON, label: egw.lang('no'), id: 'dialog[no]', image: 'cancel'},
								{button_id: "dont_ask_again", label: egw.lang("Don't ask again!"), id: 'dialog[dont_ask_again]', image:'save', align: "right"}
							]);
						}
						// overall timer running for more than 16 hours, ask to stop
						else if (overall?.start && (((new Date()).valueOf() - overall.start.valueOf()) / 3600000) >= 16)
						{
							timerDialog('Forgot to switch off working time?');
						}
					});

				}, 2000)});
			}
		},

		/**
		 * Ask user to stop working time
		 *
		 * @returns {Promise<void>} resolved once user answered, to continue logout
		 */
		onLogout_timer: function()
		{
			let promise;
			if (overall.start || overall.paused)
			{
				promise = new Promise((_resolve, _reject) =>
				{
					Et2Dialog.show_dialog((button) => {
						if (button === Et2Dialog.YES_BUTTON)
						{
							timerAction('overall-stop').then(_resolve);
						}
						else
						{
							_resolve();
						}
					}, 'Do you want to stop your working time?', 'Working time', {}, Et2Dialog.BUTTONS_YES_NO);
				});
			}
			else
			{
				promise = Promise.resolve();
			}
			return promise;
		}
	};
});