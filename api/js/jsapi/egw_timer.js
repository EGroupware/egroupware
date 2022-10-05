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
		// initiate overall timer
		startTimer(overall, _state.overall?.start, _state.overall?.offset);	// to show offset / paused time
		if (_state.overall?.paused)
		{
			stopTimer(overall, true);
		}
		else if (!_state.overall?.start)
		{
			stopTimer(overall);
		}

		// initiate specific timer, only if running or paused
		if (_state.specific?.start || _state.specific?.paused)
		{
			startTimer(specific, _state.specific?.start, _state.specific?.offset);	// to show offset / paused time
			if (_state.specific?.paused)
			{
				stopTimer(specific, true);
			}
			else if (!_state.specific?.start)
			{
				stopTimer(specific);
			}
		}
	}

	/**
	 * Get state of timer
	 * @param string _action last action
	 * @returns {{action: string, overall: {}, specific: {}, ts: Date}}
	 */
	function getState(_action)
	{
		return {
			action: _action,
			ts: new Date(),
			overall: overall,
			specific: specific
		}
	}

	/**
	 * Run timer action eg. start/stop
	 *
	 * @param {string} _action
	 * @param {string} _time
	 */
	function timerAction(_action, _time)
	{
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
		// persist state
		egw.request('timesheet.EGroupware\\Timesheet\\Events.ajax_event', [getState(_action)]).then(() => {
			if (_action === 'specific-stop')
			{
				egw.open(null, 'timesheet', 'add', {events: 'specific'});
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
		dialog._overlayContentNode.querySelectorAll('et2-button').forEach(button =>
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
		_node.textContent = sprintf('%d%s%02d', Math.round(diff / 60), sep, diff % 60);
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
			const specific_timer = dialog._overlayContentNode.querySelector('div#_specific_timer');
			const overall_timer = dialog._overlayContentNode.querySelector('div#_overall_timer');
			if (specific_timer) updateTimer(specific_timer, specific);
			if (overall_timer) updateTimer(overall_timer, overall);
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
		// update _timer state object
		if (_start)
		{
			_timer.start = new Date(_start);
		}
		else if(typeof _timer.start === 'undefined')
		{
			_timer.start = new Date();
		}
		if (_offset || _timer.offset && _timer.paused)
		{
			_timer.start.setMilliseconds(_timer.start.getMilliseconds()-(_offset || _timer.offset));
		}
		_timer.offset = 0;	// it's now set in start-time
		_timer.paused = false;

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
	 */
	function stopTimer(_timer, _pause, _time)
	{
		const time = _time ? new Date(_time) : new Date();
		// update _timer state object
		_timer.paused = _pause || false;
		if (_timer.start)
		{
			_timer.offset = time.valueOf() - _timer.start.valueOf();
			_timer.start = undefined;
		}
		// update timer display
		updateTimer(timer, _timer);

		// if dialog is shown, update its timer(s) too
		if (dialog)
		{
			const specific_timer = dialog._overlayContentNode.querySelector('div#_specific_timer');
			const overall_timer = dialog?._overlayContentNode.querySelector('div#_overall_timer');
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

	return {
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
				// Pass egw in the constructor
				dialog = new Et2Dialog(egw);

				// Set attributes.  They can be set in any way, but this is convenient.
				dialog.transformAttributes({
					// If you use a template, the second parameter will be the value of the template, as if it were submitted.
					callback: (button_id, value) =>		// return false to prevent dialog closing
					{
						if (button_id !== 'close') {
							timerAction(button_id.replace(/_([a-z]+)\[([a-z]+)\]/, '$1-$2'),
								// eT2 operates in user-time, while timers here always operate in UTC
								value.time ? new Date((new Date(value.time)).valueOf() + egw.getTimezoneOffset() * 60000) : undefined);
							setButtonState();
							return false;
						}
						dialog = undefined;
					},
					title: 'Start & stop timer',
					template: egw.webserverUrl + '/timesheet/templates/default/timer.xet',
					buttons: [
						{label: egw.lang("Close"), id: "close", default: true, image: "cancel"},
					],
					value: {
						content: {
							disable: state.disable.join(':')
						},
						sel_options: {}
					}
				});
				// Add to DOM, dialog will auto-open
				document.body.appendChild(dialog);
				dialog.getUpdateComplete().then(() => {
					// enable/disable buttons based on timer state
					setButtonState();
					// update timers in dialog
					update();
					// set current time for overwrite time input (eT2 operates in user-time!)
					//let now = new Date((new Date).valueOf() - egw.getTimezoneOffset() * 60000);
					//dialog._overlayContentNode.querySelector('et2-date-time').value = now;
				});
			});
		}
	};
});