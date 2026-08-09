/**
 * Tests for egw_timer.js ("timer" module) - MODULE_GLOBAL.
 *
 * No test coverage existed before this file. See EgwTimerHarness for how
 * it's loaded and stubbed. The state-machine tests use sinon fake timers
 * anchored at epoch 0 (1970-01-01T00:00:00.000Z, an even second so the
 * display separator's odd/even-second flip is deterministic) so elapsed
 * durations and the topmenu display text are exact, not approximate.
 *
 * `timerDialog()` itself isn't part of the module's public API - it's only
 * reachable through add_timer()'s topmenu click listener - so state-machine
 * tests call add_timer() then dispatch a click to get a dialog open before
 * driving `timer_button()`.
 */
import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {createEgwTimerEnv, EgwTimerEnv} from "./EgwTimerHarness";

async function flushMicrotasks() : Promise<void>
{
	await Promise.resolve();
	await Promise.resolve();
}

function openDialog(env : EgwTimerEnv) : any
{
	env.topmenu.dispatchEvent(new (env.window as any).Event('click', {bubbles: true}));
	return env.window.document.querySelector('et2-dialog-timer-stub');
}

describe('egw_timer.js (timer)', () =>
{
	describe('basic loading', () =>
	{
		let env : EgwTimerEnv;

		afterEach(() =>
		{
			if (env) env.destroy();
		});

		it('loads and registers the expected methods', async() =>
		{
			env = await createEgwTimerEnv({topmenuState: {disable: [], overall: {}, specific: {}}});
			const instance = env.egw();
			assert.isFunction(instance.change_timer);
			assert.isFunction(instance.timer_button);
			assert.isFunction(instance.start_timer);
			assert.isFunction(instance.add_timer);
			assert.isFunction(instance.onLogout_timer);
		});
	});

	describe('state machine (via add_timer -> click -> timer_button)', () =>
	{
		let env : EgwTimerEnv;
		let clock : sinon.SinonFakeTimers;

		beforeEach(async() =>
		{
			env = await createEgwTimerEnv({topmenuState: {disable: ['overall'], overall: {}, specific: {}}});
			clock = sinon.useFakeTimers({
				now: 0,
				toFake: ['Date', 'setTimeout', 'clearTimeout', 'setInterval', 'clearInterval'],
				global: env.window as any
			});
			env.egw().add_timer('topmenu_timer');
			openDialog(env);
			await flushMicrotasks();
		});

		afterEach(() =>
		{
			clock.restore();
			env.destroy();
		});

		it('overall-start marks the topmenu running, persists via egw.request, and displays elapsed time', async() =>
		{
			env.egw().timer_button({}, {id: 'overall[start]'});
			await flushMicrotasks();

			assert.isTrue(env.topmenu.classList.contains('running'));
			assert.isTrue(env.topmenu.classList.contains('overall'));
			assert.equal(env.topmenu.textContent, '0:00');
			assert.isTrue(env.stubs.request.calledOnce);
			assert.equal(env.stubs.request.firstCall.args[0], 'timesheet.EGroupware\\Timesheet\\Events.ajax_event');
			assert.equal(env.stubs.request.firstCall.args[1][0].action, 'overall-start');

			// 90s later: 1.5min rounds to 2, at an even epoch-second (':' separator)
			await clock.tickAsync(90000);
			assert.equal(env.topmenu.textContent, '0:02');
		});

		it('overall-pause stops the running display and also pauses a running specific timer', async() =>
		{
			env.egw().timer_button({}, {id: 'overall[start]'});
			await flushMicrotasks();
			env.egw().timer_button({}, {id: 'specific[start]'});
			await flushMicrotasks();

			env.egw().timer_button({}, {id: 'overall[pause]'});
			await flushMicrotasks();

			assert.isFalse(env.topmenu.classList.contains('running'));
			assert.isTrue(env.topmenu.classList.contains('paused'));
			assert.equal(env.stubs.request.lastCall.args[1][0].action, 'overall-pause');
		});

		it('specific-start does NOT auto-start overall when overall is fully stopped', async() =>
		{
			env.egw().timer_button({}, {id: 'specific[start]'});
			await flushMicrotasks();

			// only one persisted action - specific-start - overall was left alone
			assert.equal(env.stubs.request.callCount, 1);
			assert.equal(env.stubs.request.firstCall.args[1][0].action, 'specific-start');
		});

		it('specific-start DOES auto-restart overall when overall was paused', async() =>
		{
			env.egw().timer_button({}, {id: 'overall[start]'});
			await flushMicrotasks();
			env.egw().timer_button({}, {id: 'overall[pause]'});
			await flushMicrotasks();
			env.stubs.request.resetHistory();

			env.egw().timer_button({}, {id: 'specific[start]'});
			await flushMicrotasks();

			assert.equal(env.stubs.request.callCount, 1, 'only ONE persisted action for a single button click');
			assert.equal(env.stubs.request.firstCall.args[1][0].action, 'specific-start');
			assert.isTrue(env.topmenu.classList.contains('running'));
		});

		it('specific-stop opens a new timesheet entry when there is no associated app_id', async() =>
		{
			env.egw().timer_button({}, {id: 'specific[start]'});
			await flushMicrotasks();
			env.egw().timer_button({}, {id: 'specific[stop]'});
			await flushMicrotasks();

			assert.isTrue(env.stubs.open.calledOnce);
			assert.deepEqual(env.stubs.open.firstCall.args, [null, 'timesheet', 'add', {events: 'specific'}]);
		});

		it('specific-stop opens the existing timesheet entry for edit when app_id is a timesheet::', async() =>
		{
			env.egw().start_timer({parent: {data: {}}}, [{id: 'timesheet::42'}]);
			await flushMicrotasks();
			env.egw().timer_button({}, {id: 'specific[stop]'});
			await flushMicrotasks();

			assert.isTrue(env.stubs.open.calledOnce);
			assert.deepEqual(env.stubs.open.firstCall.args, [null, 'timesheet', 'edit', {events: 'specific', ts_id: '42'}]);
		});

		it('KNOWN BUG: pausing/stopping before ever starting throws a raw TypeError ' +
			'(_timer.last is undefined) instead of the friendly lang() message', async() =>
		{
			env.egw().timer_button({}, {id: 'overall[pause]'});
			await flushMicrotasks();

			assert.isTrue(env.Et2DialogStub.alert.calledOnce);
			// instanceof TypeError would fail cross-realm (the error is thrown inside
			// the iframe, so it's an instance of ITS OWN TypeError constructor).
			assert.equal(env.Et2DialogStub.alert.firstCall.args[0].constructor.name, 'TypeError');
		});

		it('an explicit start time before the last stop/pause raises a friendly, translated error', async() =>
		{
			env.egw().timer_button({}, {id: 'overall[start]'});
			await flushMicrotasks();
			env.egw().timer_button({}, {id: 'overall[stop]'});
			await flushMicrotasks();

			// Simulate the dialog's time widget holding an edited value 1 minute
			// before the stop time just recorded (epoch 0).
			const dialog = env.window.document.querySelector('et2-dialog-timer-stub') as any;
			dialog.value = {time: new Date(-60000).toISOString()};
			env.egw().timer_button({}, {id: 'overall[start]'});
			await flushMicrotasks();

			assert.isTrue(env.Et2DialogStub.alert.calledOnce);
			assert.equal(env.Et2DialogStub.alert.firstCall.args[0],
				'Start-time can not be before last stop- or pause-time 00:00!');
		});
	});

	describe('setButtonState()', () =>
	{
		let env : EgwTimerEnv;
		let clock : sinon.SinonFakeTimers;

		beforeEach(async() =>
		{
			env = await createEgwTimerEnv({topmenuState: {disable: ['overall'], overall: {}, specific: {}}});
			clock = sinon.useFakeTimers({now: 0, toFake: ['Date'], global: env.window as any});
			env.egw().add_timer('topmenu_timer');
		});

		afterEach(() =>
		{
			clock.restore();
			env.destroy();
		});

		function addButton(dialog : any, id : string) : HTMLElement
		{
			const btn = env.window.document.createElement('et2-button');
			btn.id = id;
			dialog.appendChild(btn);
			return btn;
		}

		it('disables only overall[start] while the overall timer is stopped', async() =>
		{
			const dialog = openDialog(env);
			const start = addButton(dialog, 'overall[start]');
			const pause = addButton(dialog, 'overall[pause]');
			const stop = addButton(dialog, 'overall[stop]');
			await flushMicrotasks();

			assert.isFalse((start as any).disabled);
			assert.isTrue((pause as any).disabled);
			assert.isTrue((stop as any).disabled);
		});

		it('disables only specific[start] while the specific timer is running', async() =>
		{
			const dialog = openDialog(env);
			const start = addButton(dialog, 'specific[start]');
			const pause = addButton(dialog, 'specific[pause]');
			const stop = addButton(dialog, 'specific[stop]');
			await flushMicrotasks();

			env.egw().timer_button({}, {id: 'specific[start]'});
			await flushMicrotasks();

			assert.isTrue((start as any).disabled);
			assert.isFalse((pause as any).disabled);
			assert.isFalse((stop as any).disabled);
		});
	});

	describe('change_timer()', () =>
	{
		let env : EgwTimerEnv;

		afterEach(() =>
		{
			if (env) env.destroy();
		});

		it('is a no-op when the widget has no value', async() =>
		{
			env = await createEgwTimerEnv({topmenuState: {disable: [], overall: {}, specific: {}}});
			env.egw().change_timer({}, {value: null, id: 'times[overall][start]'});
			assert.equal(env.window.document.querySelectorAll('et2-dialog-timer-stub').length, 0);
		});

		it('is a no-op when "overwrite" is disabled', async() =>
		{
			env = await createEgwTimerEnv({topmenuState: {disable: ['overwrite'], overall: {}, specific: {}}});
			env.egw().add_timer('topmenu_timer');
			env.egw().change_timer({}, {value: '2024-01-01 10:00', id: 'times[overall][start]'});
			// only the dialog opened by add_timer's wiring (none, since no click) - none at all here
			assert.equal(env.window.document.querySelectorAll('et2-dialog-timer-stub').length, 0);
		});

		it('opens a confirm dialog seeded with the widget value', async() =>
		{
			env = await createEgwTimerEnv({topmenuState: {disable: [], overall: {}, specific: {}}});
			env.egw().change_timer({}, {value: '2024-01-01 10:00:00', id: 'times[overall][start]'});

			const dialog = env.window.document.querySelector('et2-dialog-timer-stub') as any;
			assert.isNotNull(dialog);
			assert.equal(dialog.attrs.value.content.time, '2024-01-01 10:00:00');
			assert.equal(dialog.attrs.title, 'Change time');
		});
	});

	describe('start_timer()', () =>
	{
		let env : EgwTimerEnv;

		afterEach(() =>
		{
			if (env) env.destroy();
		});

		it('rejects multi-selection with an error message and does not start anything', async() =>
		{
			env = await createEgwTimerEnv({topmenuState: {disable: [], overall: {}, specific: {}}});
			env.egw().start_timer({parent: {data: {}}}, [{id: 'a'}, {id: 'b'}]);

			assert.isTrue(env.stubs.message.calledOnce);
			assert.equal(env.stubs.message.firstCall.args[1], 'error');
			assert.equal(env.stubs.request.callCount, 0);
		});

		it('starts the specific timer directly when none is running', async() =>
		{
			env = await createEgwTimerEnv({topmenuState: {disable: [], overall: {}, specific: {}}});
			env.egw().start_timer({parent: {data: {}}}, [{id: 'infolog::1'}]);
			await flushMicrotasks();

			assert.equal(env.stubs.request.firstCall.args[1][0].action, 'specific-start');
		});

		it('asks for confirmation before re-associating an already-running specific timer', async() =>
		{
			env = await createEgwTimerEnv({topmenuState: {disable: [], overall: {}, specific: {}}});
			env.egw().start_timer({parent: {data: {}}}, [{id: 'infolog::1'}]);
			await flushMicrotasks();
			env.stubs.request.resetHistory();

			env.egw().start_timer({parent: {data: {}}}, [{id: 'infolog::2'}]);

			assert.isTrue(env.Et2DialogStub.show_dialog.calledOnce);
			assert.equal(env.stubs.request.callCount, 0, 'nothing persisted until the user confirms');
		});
	});

	describe('onLogout_timer()', () =>
	{
		let env : EgwTimerEnv;

		afterEach(() =>
		{
			if (env) env.destroy();
		});

		it('resolves immediately without a dialog when overall is not running', async() =>
		{
			env = await createEgwTimerEnv({topmenuState: {disable: [], overall: {}, specific: {}}});
			await env.egw().onLogout_timer();

			assert.isFalse(env.Et2DialogStub.show_dialog.called);
		});

		it('shows a confirm dialog and stops the timer on YES', async() =>
		{
			env = await createEgwTimerEnv({topmenuState: {disable: [], overall: {}, specific: {}}});
			env.egw().add_timer('topmenu_timer');
			openDialog(env);
			await flushMicrotasks();
			env.egw().timer_button({}, {id: 'overall[start]'});
			await flushMicrotasks();
			env.stubs.request.resetHistory();

			const logoutPromise = env.egw().onLogout_timer();
			assert.isTrue(env.Et2DialogStub.show_dialog.calledOnce);

			const callback = env.Et2DialogStub.show_dialog.firstCall.args[0];
			callback(env.Et2DialogStub.YES_BUTTON);
			await logoutPromise;

			assert.equal(env.stubs.request.firstCall.args[1][0].action, 'overall-stop');
		});
	});

	describe('add_timer()', () =>
	{
		let env : EgwTimerEnv;

		afterEach(() =>
		{
			if (env) env.destroy();
		});

		it('does nothing if the named parent element does not exist', async() =>
		{
			env = await createEgwTimerEnv({topmenuState: {disable: [], overall: {}, specific: {}}});
			assert.doesNotThrow(() => env.egw().add_timer('nonexistent'));
		});

		it('wires a click on the parent to open the timer dialog', async() =>
		{
			env = await createEgwTimerEnv({topmenuState: {disable: ['overall'], overall: {}, specific: {}}});
			env.egw().add_timer('topmenu_timer');

			assert.equal(env.window.document.querySelectorAll('et2-dialog-timer-stub').length, 0);
			openDialog(env);
			assert.equal(env.window.document.querySelectorAll('et2-dialog-timer-stub').length, 1);
		});

		it('asks to start working time after a delay when overall is not running and not disabled', async() =>
		{
			env = await createEgwTimerEnv({topmenuState: {disable: [], overall: {}, specific: {}}});
			const clock = sinon.useFakeTimers({toFake: ['setTimeout', 'clearTimeout'], global: env.window as any});
			env.stubs.preference.returns(Promise.resolve('yes'));

			env.egw().add_timer('topmenu_timer');
			await flushMicrotasks();
			await clock.tickAsync(2000);
			await flushMicrotasks();

			assert.isTrue(env.Et2DialogStub.show_dialog.calledOnce);
			clock.restore();
		});

		it('does not ask to start working time when the preference opts out', async() =>
		{
			env = await createEgwTimerEnv({topmenuState: {disable: [], overall: {}, specific: {}}});
			const clock = sinon.useFakeTimers({toFake: ['setTimeout', 'clearTimeout'], global: env.window as any});
			env.stubs.preference.returns(Promise.resolve('no'));

			env.egw().add_timer('topmenu_timer');
			await flushMicrotasks();
			await clock.tickAsync(2000);
			await flushMicrotasks();

			assert.isFalse(env.Et2DialogStub.show_dialog.called);
			clock.restore();
		});
	});
});
