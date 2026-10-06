import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import "./CalendarAppImportStub";
import "../../../api/js/etemplate/Et2Widget/Et2Widget";

/**
 * Contract: a planner row releases every cache registration it made when it is destroyed.
 *
 * Why it matters: the planner registers each row's data callback once per day it shows, for every
 * row it draws, on every redraw (navigation, filter change). Only the planner's own callbacks were
 * unregistered, so each redraw left (users x days) registrations behind, each holding a destroyed
 * row and its DOM - and keeping that day's cached data from ever being evicted.
 *
 * Setup: a bare Object.create(et2_calendar_planner_row.prototype), the parent widget class's
 * destroy() stubbed out, and window.egw's dataRegisterUID / dataUnregisterUID spied on.
 *
 * Pass criteria: every uid passed to registerUID() is unregistered, for this row, by destroy(),
 * and a second destroy() unregisters nothing more.
 *
 * Environment: no network.
 */
const ROW_SOURCE = '/calendar/js/et2_widget_planner_row.ts';

describe('et2_calendar_planner_row registrations', () =>
{
	let row : any;
	let RowClass : any;
	let egwStub : any;
	let originalDestroy : sinon.SinonStub;

	before(async function()
	{
		this.timeout(10000);
		RowClass = (await import(ROW_SOURCE)).et2_calendar_planner_row;
	});

	beforeEach(() =>
	{
		egwStub = (<any>window).egw;
		// the test harness's egw has neither, so define them (and take them away again)
		egwStub.dataRegisterUID = sinon.stub();
		egwStub.dataUnregisterUID = sinon.stub();
		originalDestroy = sinon.stub(Object.getPrototypeOf(RowClass.prototype), 'destroy');

		row = Object.create(RowClass.prototype);
		row._registered_uids = [];
	});

	afterEach(() =>
	{
		sinon.restore();
		delete egwStub.dataRegisterUID;
		delete egwStub.dataUnregisterUID;
	});

	it('unregisters, for itself, every uid it registered', () =>
	{
		row.registerUID('calendar::20261005:7');
		row.registerUID('calendar::20261006:7');

		assert.equal(egwStub.dataRegisterUID.callCount, 2);
		assert.isTrue(egwStub.dataRegisterUID.calledWith('calendar::20261005:7', row._data_callback, row));

		row.destroy();

		assert.equal(egwStub.dataUnregisterUID.callCount, 2);
		assert.isTrue(egwStub.dataUnregisterUID.calledWith('calendar::20261005:7', null, row));
		assert.isTrue(egwStub.dataUnregisterUID.calledWith('calendar::20261006:7', null, row));
		assert.isTrue(originalDestroy.calledOnce, 'the inherited destroy() still runs');
	});

	it('has nothing left to unregister the second time', () =>
	{
		row.registerUID('calendar::20261005:7');
		row.destroy();
		row.destroy();

		assert.equal(egwStub.dataUnregisterUID.callCount, 1);
	});
});
