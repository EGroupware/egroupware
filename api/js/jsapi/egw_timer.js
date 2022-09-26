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
	 * Timer state
	 */
	let timer_start;
	let timer_offset = 0;
	let timer_paused = false;

	const timer = document.querySelector('#topmenu_timer');
	let timer_interval;

	function setState(_state)
	{
		timer_start = _state.start ? new Date(_state.start) : undefined;
		timer_offset = _state.offset || 0;
		if (timer_offset && timer_start)
		{
			timer_start.setMilliseconds(timer_start.getMilliseconds()-timer_offset);
		}
		if (timer_start || _state.paused)
		{
			startTimer();

			if (_state.paused) stopTimer(true);	// sets timer_paused
		}
	}

	function getState()
	{
		return {
			start: timer_start,
			offset: timer_offset,
			paused: timer_paused
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
				startTimer();
				break;

			case 'overall-pause':
				stopTimer(true);
				break;

			case 'overall-stop':
				stopTimer();
				break;
		}
		// persist state
		let state = getState();
		state.action = _action;
		egw.request('timesheet.timesheet_bo.ajax_event', [state])
	}

	function startTimer()
	{
		timer_paused = false;
		timer_start = new Date();
		if (timer_offset > 0)
		{
			timer_start.setMilliseconds(timer_start.getMilliseconds()-timer_offset);
		}
		const update = () =>
		{
			if (!timer_start)
			{
				timer.textContent = '0:00';
			}
			else if (timer_paused)
			{
				// do nothing
			}
			else
			{
				let diff = Math.round(((new Date()).valueOf() - timer_start.valueOf())/1000.0);
				const sep = diff % 2 ? ' ' : ':';
				diff = Math.round(diff / 60.0);
				timer.textContent = sprintf('%d%s%02d', Math.round(diff/60), sep, diff % 60);
			}
		}
		timer.classList.add('running');
		timer.classList.remove('paused');
		update();
		timer_interval = window.setInterval(update, 1000);
	}

	function stopTimer(_pause)
	{
		if (timer_interval)
		{
			window.clearInterval(timer_interval);
		}
		timer.classList.remove('running');
		timer.textContent = timer.textContent.replace(' ', ':');

		if (_pause)
		{
			timer.classList.add('paused');
			timer_paused = true;
		}
		timer_offset = (new Date()).valueOf() - timer_start.valueOf();
		if (!_pause)
		{
			timer_start = undefined;
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
			const select = document.createElement('et2-select', {id: 'timer_selectbox'});
			timer_container.append(select);

			// bind change handler
			select.addEventListener('change', () =>
			{
				if (select.value) timerAction(select.value);
				select.value = '';
			});
			// bind click handler
			timer_container.addEventListener('click', (ev) =>
			{
				select.dropdown.open = !select.dropdown.open ? true : false;
				ev.stopPropagation();
			});
			// need to load timesheet translations for app-names
			this.langRequire(window, [{app: 'timesheet', lang: this.preference('lang')}], () =>
			{
				select.select_options = [
					{ value:'', label: this.lang('Select one...')},
					{ value: 'overall-start', label: this.lang('Start working time')},
					{ value: 'overall-pause', label: this.lang('Pause working time')},
					{ value: 'overall-stop', label: this.lang('Stop working time')},
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