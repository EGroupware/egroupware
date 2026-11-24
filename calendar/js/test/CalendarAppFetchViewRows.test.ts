import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import "./CalendarAppImportStub";
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import type {CalendarApp} from "../app";

/** See ComposeMeetingMail.test.ts for why app.ts is imported through its explicit source path. */
const APP_SOURCE = '/calendar/js/app.ts';

/**
 * Contract: CalendarApp._fetchViewRows(), the egw.dataRegisterFetch() callback of the day, week, month
 * and planner views, answers only their fetches, and answers them from calendar_uiviews::ajax_get_rows().
 *
 * Why it matters: the views have no nextmatch, so their fetch must never reach the nextmatch's
 * ajax_get_rows, which rejects a form name without a widget.  The list's nextmatch shares the
 * "calendar" prefix and has to keep its regular fetch.
 *
 * Setup: a bare Object.create(CalendarApp.prototype) with a stubbed egw.request().
 *
 * Pass criteria: a fetch of another widget gets false and sends nothing; a fetch of the views sends
 * exec_id, range and filters to calendar_uiviews::ajax_get_rows and resolves to its result; a missing
 * or failed answer resolves to an empty result instead of falling back.
 *
 * Environment: no network.
 */
describe('CalendarApp._fetchViewRows()', () =>
{
	let app : any;
	let request : sinon.SinonStub;
	let CalendarAppClass : typeof CalendarApp;

	before(async function()
	{
		this.timeout(10000);
		CalendarAppClass = (await import(APP_SOURCE)).CalendarApp;
	});

	beforeEach(() =>
	{
		request = sinon.stub();
		app = Object.create(CalendarAppClass.prototype);
		Object.assign(app, {appname: 'calendar', egw: {request}});
	});

	it('leaves fetches of other widgets, eg. the list, to the nextmatch', () =>
	{
		assert.isFalse(app._fetchViewRows('exec', {start: 0, num_rows: 50}, {}, 'calendar-list_nm'));
		assert.isFalse(request.called);
	});

	it('fetches the views from calendar_uiviews::ajax_get_rows', async() =>
	{
		const answer = {order: ['1:2'], data: {'1:2': {id: '1'}}, total: 1, rows: {}};
		request.resolves(answer);
		const range = {start: 0, num_rows: 400};
		const filters = {filter: 'custom', status_filter: 'default'};

		const result = await app._fetchViewRows('exec_id', range, filters, 'calendar', ['x'], 123);

		assert.isTrue(request.calledOnceWithExactly('calendar_uiviews::ajax_get_rows', ['exec_id', range, filters]));
		assert.deepEqual(result, answer);
	});

	it('answers with an empty result if the request fails', async() =>
	{
		const empty = {order: [], data: {}, total: 0};

		request.resolves(undefined);
		assert.deepEqual(await app._fetchViewRows('exec_id', {start: 0}, {}, 'calendar'), empty);

		request.rejects(new Error('network'));
		assert.deepEqual(await app._fetchViewRows('exec_id', {start: 0}, {}, 'calendar'), empty);
	});
});
