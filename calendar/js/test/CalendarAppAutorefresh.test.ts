import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import "./CalendarAppImportStub";
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import type {CalendarApp} from "../app";

/** See ComposeMeetingMail.test.ts for why app.ts is imported through its explicit source path. */
const APP_SOURCE = '/calendar/js/app.ts';

/**
 * Contract: CalendarApp._set_autorefresh() - which runs on every state change and on every
 * autorefresh tick - leaves exactly one pair of hide/show listeners on the calendar tab, however
 * often it is called, and the pause/resume behaviour they implement is unchanged.
 *
 * Why it matters: each call used to add a fresh hide and show listener, held until the tab was
 * actually hidden or shown, and each stale "show" then ran its own _set_autorefresh() and added
 * more. A calendar left open on a day or week view collected them on every navigation and tick,
 * each keeping the whole app alive, and coming back to the tab ran all of them.
 *
 * Setup: a bare Object.create(CalendarApp.prototype) with the few fields and the list nextmatch
 * _set_autorefresh() reads, over a plain element standing in for the tab; fake timers.
 *
 * Pass criteria: addEventListener is called twice in total across many calls; a hide that outlasts
 * the interval followed by a show refreshes once, a short hide does not refresh; destroy-time
 * unbinding removes both listeners.
 *
 * Environment: no network.
 */
describe('CalendarApp._set_autorefresh()', () =>
{
	let app : any;
	let tab : HTMLElement;
	let addSpy : sinon.SinonSpy;
	let removeSpy : sinon.SinonSpy;
	let clock : sinon.SinonFakeTimers;
	let seconds : number;
	let CalendarAppClass : typeof CalendarApp;

	before(async function()
	{
		this.timeout(10000);
		CalendarAppClass = (await import(APP_SOURCE)).CalendarApp;
	});

	beforeEach(() =>
	{
		clock = sinon.useFakeTimers();
		seconds = 60;
		tab = document.createElement('div');
		addSpy = sinon.spy(tab, 'addEventListener');
		removeSpy = sinon.spy(tab, 'removeEventListener');

		app = Object.create(CalendarAppClass.prototype);
		Object.assign(app, {
			appname: 'calendar',
			state: {view: 'week'},
			egw: {preference: () => seconds},
			_autorefresh_timer: null,
			_autorefresh_tab: null,
			_autorefresh_stale: false,
			_autorefresh_refresh: sinon.stub(),
		});
		Object.defineProperty(app, 'listNextmatch', {
			get: () => ({
				settings: {columnselection_pref: 'calendar-list'},
				template: 'calendar.list',
				getInstanceManager: () => ({DOMContainer: {parentNode: tab}}),
			})
		});
	});

	afterEach(() =>
	{
		app._unbindAutorefreshTabEvents();
		window.clearInterval(app._autorefresh_timer);
		clock.restore();
	});

	it('binds the tab listeners once, however often it is called', () =>
	{
		for(let i = 0; i < 10; i++)
		{
			app._set_autorefresh();
		}

		assert.equal(addSpy.callCount, 2, 'one hide and one show, not a pair per call');
		assert.deepEqual(addSpy.args.map(a => a[0]).sort(), ['hide', 'show']);
	});

	it('does not add more listeners when the tab is shown again', () =>
	{
		app._set_autorefresh();
		tab.dispatchEvent(new Event('hide'));
		tab.dispatchEvent(new Event('show'));
		tab.dispatchEvent(new Event('hide'));
		tab.dispatchEvent(new Event('show'));

		assert.equal(addSpy.callCount, 2);
	});

	it('refreshes on show after a hide that outlasted the interval, and only then', () =>
	{
		app._set_autorefresh();

		tab.dispatchEvent(new Event('hide'));
		clock.tick(10 * 1000);
		tab.dispatchEvent(new Event('show'));
		assert.equal(app._autorefresh_refresh.callCount, 0, 'a short hide does not refresh');

		tab.dispatchEvent(new Event('hide'));
		clock.tick(61 * 1000);
		tab.dispatchEvent(new Event('show'));
		assert.equal(app._autorefresh_refresh.callCount, 1, 'a long hide refreshes once on show');

		tab.dispatchEvent(new Event('hide'));
		tab.dispatchEvent(new Event('show'));
		assert.equal(app._autorefresh_refresh.callCount, 1, 'and is not remembered for the next show');
	});

	it('keeps ticking while shown, and stops while hidden', () =>
	{
		app._set_autorefresh();
		clock.tick(60 * 1000);
		assert.equal(app._autorefresh_refresh.callCount, 1);

		tab.dispatchEvent(new Event('hide'));
		clock.tick(30 * 1000);
		assert.equal(app._autorefresh_refresh.callCount, 1, 'no ticks while the tab is hidden');
	});

	it('starts no timer, and still binds nothing extra, when autorefresh is off', () =>
	{
		seconds = 0;
		app._set_autorefresh();
		tab.dispatchEvent(new Event('hide'));
		clock.tick(10 * 60 * 1000);
		tab.dispatchEvent(new Event('show'));

		assert.equal(app._autorefresh_refresh.callCount, 0);
		assert.equal(addSpy.callCount, 2);
	});

	it('unbinds both listeners', () =>
	{
		app._set_autorefresh();
		app._unbindAutorefreshTabEvents();

		assert.deepEqual(removeSpy.args.map(a => a[0]).sort(), ['hide', 'show']);
		assert.isNull(app._autorefresh_tab);
	});
});
