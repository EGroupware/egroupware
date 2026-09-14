import {assert} from "@open-wc/testing";
import * as sinon from "sinon";

// Import the TypeScript source explicitly, not a potentially stale app.js.
const APP_SOURCE = '/timesheet/js/app.ts';
const WIDGET_SOURCE = '/api/js/etemplate/Et2Widget/Et2Widget.ts';

/**
 * Exercise the real date-change handler with fixed widget values and a stubbed AJAX response.
 * An empty day must not discard the only time anchor; an existing end time or last entry
 * must keep its previous behavior. No database, real clock or application constructor is used.
 */
describe('TimesheetApp.ts_start_changed()', () =>
{
	let TimesheetApp : any;
	let ui : any;
	let widgets : any;
	let values : any;
	let sandbox : sinon.SinonSandbox;
	let request : sinon.SinonStub;

	before(async function()
	{
		this.timeout(10000);
		const globals : any = window;
		globals.app = globals.app || {classes: {}};
		globals.app.classes = globals.app.classes || {};
		globals.framework.setSidebox = globals.framework.setSidebox || (() => {});
		globals.egw.registerJSONPlugin = globals.egw.registerJSONPlugin || (() => {});
		// Break the legacy widget / Et2Widget import cycle before loading app.ts.
		await import(WIDGET_SOURCE);
		await import(APP_SOURCE);
		TimesheetApp = globals.app.classes.timesheet;
	});

	beforeEach(() =>
	{
		sandbox = sinon.createSandbox();
		request = sandbox.stub((<any>window).egw, 'request').resolves(null);
		widgets = {
			start_time: {value: '1970-01-01T02:10:00Z', disabled: false},
			end_time: {value: ''}
		};
		values = {ts_id: '', ts_owner: 1};
		ui = Object.create(TimesheetApp.prototype);
		ui.egw = {preference: sandbox.stub().returns('start_time'), loading_prompt: sandbox.stub()};
		ui.et2 = {
			getValueById: id => values[id],
			getWidgetById: id => widgets[id]
		};
	});

	afterEach(() => sandbox.restore());

	for(const scenario of [
		{name: 'keeps the start on an empty day when end is blank', lastEnd: null, end: '', start: '1970-01-01T02:10:00Z'},
		{name: 'clears start on an empty day when end is populated', lastEnd: null, end: '1970-01-01T12:00:00Z', start: ''},
		{name: 'continues from a midnight last end and clears end', lastEnd: '00:00', end: '1970-01-01T12:00:00Z', start: '1970-01-01T00:00:00Z'}
	])
	{
		it(scenario.name, async() =>
		{
			widgets.end_time.value = scenario.end;
			let resolveLookup : (value : string | null) => void;
			request.returns(new Promise(resolve => { resolveLookup = resolve; }));
			const finished = new Promise<void>(resolve =>
			{
				ui.egw.loading_prompt.callsFake((_id, loading) => { if(!loading) resolve(); });
			});

			ui.ts_start_changed(null, {getValue: () => '2026-09-13T00:00:00Z'});
			assert.isTrue(request.calledOnceWith('timesheet.timesheet_ui.ajax_get_last_end', [1, '2026-09-13T00:00:00Z']));
			assert.isTrue(widgets.start_time.disabled, 'Disable start until the lookup completes');
			assert.isTrue(ui.egw.loading_prompt.calledWith('ts_start_changed', true, '', widgets.start_time));
			resolveLookup(scenario.lastEnd);
			await finished;

			assert.strictEqual(widgets.start_time.value, scenario.start, scenario.name);
			assert.strictEqual(widgets.end_time.value, scenario.lastEnd ? '' : scenario.end);
			assert.isFalse(widgets.start_time.disabled, 'Re-enable start after the lookup');
		});
	}
});
