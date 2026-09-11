/**
 * Tests for egw_calendar.js ("calendar" module) - MODULE_GLOBAL.
 *
 * No prior test coverage. See EgwCalendarHarness for how it's loaded.
 */
import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {createEgwCalendarEnv, EgwCalendarEnv} from "./EgwCalendarHarness";

describe('egw_calendar.js (calendar)', () =>
{
	let env : EgwCalendarEnv;

	beforeEach(async() =>
	{
		env = await createEgwCalendarEnv();
	});

	afterEach(() =>
	{
		env.destroy();
	});

	it('loads and registers the expected methods', () =>
	{
		const instance = env.egw();
		assert.isFunction(instance.calendar);
		assert.isFunction(instance.time);
		assert.isFunction(instance.dateTimeFormat);
		assert.isFunction(instance.getTimezoneOffset);
		assert.isFunction(instance.week_start);
		assert.isFunction(instance.holidays);
	});

	describe('calendar() / time()', () =>
	{
		it('calendar() just alerts that jQueryUI datepicker is no longer supported', () =>
		{
			const alertStub = sinon.stub(env.window, 'alert');
			env.egw().calendar(null, null, null, null);
			assert.isTrue(alertStub.calledOnceWith('jQueryUI datepicker is no longer supported!'));
		});

		it('time() just alerts that jQueryUI datepicker is no longer supported', () =>
		{
			const alertStub = sinon.stub(env.window, 'alert');
			env.egw().time(null, null, null);
			assert.isTrue(alertStub.calledOnceWith('jQueryUI datepicker is no longer supported!'));
		});
	});

	describe('dateTimeFormat()', () =>
	{
		it('transforms a typical PHP date/time format to the jQuery datepicker equivalent', () =>
		{
			assert.equal(env.egw().dateTimeFormat('Y-m-d H:i:s'), 'yy-mm-dd hh:mm:ss');
		});

		it('transforms a European-style date-only format', () =>
		{
			assert.equal(env.egw().dateTimeFormat('d.m.Y'), 'dd.mm.yy');
		});

		it('leaves M (month name) unchanged', () =>
		{
			assert.equal(env.egw().dateTimeFormat('M Y'), 'M yy');
		});
	});

	describe('getTimezoneOffset()', () =>
	{
		it('returns parseInt of the "timezoneoffset" preference when it is set', () =>
		{
			env.prefs.timezoneoffset = '120';
			assert.equal(env.egw().getTimezoneOffset(), 120);
		});

		it('falls back to the browser\'s own timezone offset when the preference is not a number', () =>
		{
			// no preference set -> egw.preference() returns undefined -> isNaN(undefined) is true
			assert.equal(env.egw().getTimezoneOffset(), new Date().getTimezoneOffset());
		});
	});

	describe('week_start()', () =>
	{
		// 2024-01-10 is a Wednesday (UTC)
		const wednesday = '2024-01-10T00:00:00Z';

		it('defaults to Sunday as the start of the week', () =>
		{
			const start = env.egw().week_start(wednesday);
			assert.equal(start.toISOString().substring(0, 10), '2024-01-07'); // Sunday
		});

		it('uses Monday as the start of the week when preferred', () =>
		{
			env.prefs.weekdaystarts = 'Monday';
			const start = env.egw().week_start(wednesday);
			assert.equal(start.toISOString().substring(0, 10), '2024-01-08'); // Monday
		});

		it('uses Saturday as the start of the week when preferred', () =>
		{
			env.prefs.weekdaystarts = 'Saturday';
			const start = env.egw().week_start(wednesday);
			assert.equal(start.toISOString().substring(0, 10), '2024-01-06'); // Saturday
		});

		it('a date that IS the configured week-start day is left unchanged', () =>
		{
			env.prefs.weekdaystarts = 'Monday';
			const monday = '2024-01-08T00:00:00Z';
			const start = env.egw().week_start(monday);
			assert.equal(start.toISOString().substring(0, 10), '2024-01-08');
		});
	});

	describe('holidays()', () =>
	{
		it('resolves to an empty object without fetching when no country preference is set', async() =>
		{
			const result = await env.egw().holidays(2024);
			assert.deepEqual(result, {});
			assert.isFalse(env.fetchStub.called);
		});

		it('fetches from calendar/holidays.php and resolves with the parsed JSON when a country is set', async() =>
		{
			env.prefs.country = 'de';
			const holidayData = {'20241225': [{day: 25, month: 12, name: 'Christmas'}]};
			env.fetchStub.resolves({json: () => Promise.resolve(holidayData)});

			const result = await env.egw().holidays(2024);

			assert.deepEqual(result, holidayData);
			assert.isTrue(env.fetchStub.calledOnce);
			assert.include(env.fetchStub.firstCall.args[0], '/calendar/holidays.php');
			assert.include(env.fetchStub.firstCall.args[0], 'year=2024');
		});

		it('caches per year - a second call for the same year does not fetch again', async() =>
		{
			env.prefs.country = 'de';
			env.fetchStub.resolves({json: () => Promise.resolve({})});

			await env.egw().holidays(2024);
			await env.egw().holidays(2024);

			assert.isTrue(env.fetchStub.calledOnce);
		});

		it('fetches separately for a different year', async() =>
		{
			env.prefs.country = 'de';
			env.fetchStub.resolves({json: () => Promise.resolve({})});

			await env.egw().holidays(2024);
			await env.egw().holidays(2025);

			assert.isTrue(env.fetchStub.calledTwice);
		});
	});
});
