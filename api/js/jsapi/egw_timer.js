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
	 * Set state of timer
	 *
	 * @param _state
	 */
	function setState(_state)
	{
		// initiate overall timr
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
			startTimer(specific, _state.specific?.start, _state.specifc?.offset);	// to show offset / paused time
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
	 */
	function timerAction(_action)
	{
		switch(_action)
		{
			case 'overall-start':
				startTimer(overall);
				break;

			case 'overall-pause':
				stopTimer(overall,true);
				if (specific?.start) stopTimer(specific, true);
				break;

			case 'overall-stop':
				stopTimer(overall);
				if (specific?.start) stopTimer(specific);
				break;

			case 'specific-start':
				if (overall?.paused) startTimer(overall);
				startTimer(specific);
				break;

			case 'specific-pause':
				stopTimer(specific,true);
				break;

			case 'specific-stop':
				stopTimer(specific);
				break;
		}
		// persist state
		egw.request('timesheet.timesheet_bo.ajax_event', [getState(_action)])
	}

	/**
	 * Enable/disable menu items based on timer state
	 */
	function setMenuState()
	{
		const menu = document.querySelector('et2-select#timer_selectbox').menu;
		// disable not matching / available menu-items
		menu.getAllItems('et2-selectbox#timer_selecbox sl-menu-item').forEach(item =>
		{
			if (item.value.substring(0, 8) === 'overall-')
			{
				// timer running: disable only start, enable pause and stop
				if (overall?.start)
				{
					item.disabled = item.value === 'overall-start';
				}
				// timer paused: disable pause, enable start and stop
				else if (overall?.paused)
				{
					item.disabled = item.value === 'overall-pause';
				}
				// timer stopped: disable stop and pause, enable start
				else
				{
					item.disabled = item.value !== 'overall-start';
				}
			}
			else if (item.value.substring(0, 9) === 'specific-')
			{
				// timer running: disable only start, enable pause and stop
				if (specific?.start)
				{
					item.disabled = item.value === 'specific-start';
				}
				// timer paused: disable pause, enable start and stop
				else if (specific?.paused)
				{
					item.disabled = item.value === 'specific-pause';
				}
				// timer stopped: disable stop and pause, enable start
				else
				{
					item.disabled = item.value !== 'specific-start';
				}
			}
		});
	}

	/**
	 * Start given timer
	 *
	 * @param object _timer
	 * @param string|undefined _start to initialise with time different from current time
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
		if (_offset || _timer.offset)
		{
			_timer.start.setMilliseconds(_timer.start.getMilliseconds()-(_offset || _timer.offset));
		}
		_timer.offset = 0;	// it's now set in start-time
		_timer.paused = false;

		// only initiate periodic update, for specific timer, or overall, when specific is not started or paused
		if (_timer === specific || _timer === overall && !(specific?.start || specific?.paused))
		{
			const update = () => {
				let diff = Math.round(((new Date()).valueOf() - _timer.start.valueOf()) / 1000.0);
				const sep = diff % 2 ? ' ' : ':';
				diff = Math.round(diff / 60.0);
				timer.textContent = sprintf('%d%s%02d', Math.round(diff / 60), sep, diff % 60);
			}
			timer.classList.add('running');
			timer.classList.remove('paused');
			timer.classList.toggle('overall', _timer === overall);
			update();
			if (timer_interval) {
				window.clearInterval(timer_interval);
			}
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
	 */
	function stopTimer(_timer, _pause)
	{
		// stop periodic update
		if (timer_interval)
		{
			window.clearInterval(timer_interval);
		}

		// update timer of stopped/paused state
		timer.classList.remove('running');
		timer.classList.toggle('paused', _pause || false);
		timer.textContent = timer.textContent.replace(' ', ':');

		// update _timer state object
		_timer.paused = _pause || false;
		if (_timer.start)
		{
			_timer.offset = (new Date()).valueOf() - _timer.start.valueOf();
			_timer.start = undefined;
		}

		// if specific timer is stopped AND overall timer is running or paused, re-start overall to display it again
		if (!_pause && _timer === specific && (overall.start || overall.paused))
		{
			const paused = overall.paused;
			startTimer(overall);
			if (paused) stopTimer(overall, true);
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
			if (timer && timer.getAttribute('data-state'))
			{
				setState(JSON.parse(timer.getAttribute('data-state')));
			}

			// create selectbox / menu
			const select = document.createElement('et2-select');
			select.id = 'timer_selectbox';
			timer_container.append(select);

			// bind change handler
			select.addEventListener('change', () =>
			{
				if (select.value) timerAction(select.value);
				select.value = '';
			});

			select.addEventListener('sl-hide', (e) => {
				if (e.currentTarget.nodeName === 'ET2-SELECT')
				{
					e.stopImmediatePropagation();
				}
			});
			// bind click handler
			timer_container.addEventListener('click', (ev) =>
			{
				ev.stopImmediatePropagation();
				if (select.dropdown.open)
				{
					select.dropdown.hide();
				}
				else
				{
					setMenuState();
					select.dropdown.show();
				}
			});
			// need to load timesheet translations for app-names
			this.langRequire(window, [{app: 'timesheet', lang: this.preference('lang')}], () =>
			{
				select.select_options = [
					{ value:'', label: this.lang('Select one...')},
					{ value: 'specific-start', label: this.lang('Start specific time'), icon: 'timesheet/play-blue'},
					{ value: 'specific-pause', label: this.lang('Pause specific time'), icon: 'timesheet/pause-orange'},
					{ value: 'specific-stop', label: this.lang('Stop specific time'), icon: 'timesheet/stop'},
					{ value: 'overall-start', label: this.lang('Start working time'), icon: 'timesheet/play'},
					{ value: 'overall-pause', label: this.lang('Pause working time'), icon: 'timesheet/pause-orange'},
					{ value: 'overall-stop', label: this.lang('Stop working time'), icon: 'timesheet/stop'},
				];
				select.updateComplete.then(() =>
				{
					select.dropdown.trigger.style.visibility = 'hidden';
					select.dropdown.trigger.style.height = '0px';
				});
			});
		}
	};
});