import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {Et2Nextmatch} from "../Et2Nextmatch";

/**
 * Contract under test:
 * - `Et2NextmatchAutoRefresh` only polls while its nextmatch is actually visible. Three
 *   independent things can hide it: the browser tab/window being backgrounded
 *   (`document.hidden`), its EGroupware app tab not being the active one, and the element
 *   simply not being rendered - hidden behind another of the app's own views, in an inactive
 *   tab panel, in a collapsed section. The last one fires neither `hide`/`show` nor
 *   `visibilitychange`, which is why the controller also watches its own box.
 * - Resuming from a pause does one immediate refresh, because rows can have gone stale while
 *   the timer was stopped - but only if a timer was ever actually armed. A grid that merely
 *   had not been rendered yet when it first loaded must not fire an extra full reload on top
 *   of the load it just did.
 *
 * Setup strategy:
 * - Render a real `et2-nextmatch`, stub `egw().preference()` to return an interval, and drive
 *   the controller's own visibility re-check directly rather than waiting on a ResizeObserver
 *   frame - the observer is only the trigger, `shouldRun` is the behaviour.
 * - Stub the host's `refresh()` so a tick is observable without a server.
 *
 * Pass criteria:
 * - The timer exists exactly when the grid is visible, and the immediate refresh on resume
 *   happens only after the timer had really been running.
 */

const egwStub = {
	lang: (label : string) => label,
	image: () => "",
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	preference: (_key? : string) => null as any,
	set_preference: () => {},
	app_name: () => "addressbook",
	link: (url : string) => url,
	uid: () => "nm-autorefresh-test",
	debug: () => {}
};
window.egw = function() { return egwStub; } as any;
Object.assign(window.egw, egwStub);

/**
 * Render a nextmatch with an autorefresh interval in its preference, and return it along
 * with its (private) autorefresh controller and a stub standing in for a poll tick.
 */
async function createNextmatch(interval : number | null = 30)
{
	// No row template on purpose: it would send the widget looking for an .xet over the
	// network, and none of this is about row rendering.
	const el = new Et2Nextmatch();
	document.body.append(el);
	await el.updateComplete;

	// Et2Widget.egw() resolves through window['egw'] itself, not the egwStub reference, so
	// the override has to land on the object .egw() actually returns.
	const liveEgw = (el as any).egw();
	const originalPreference = liveEgw.preference;
	liveEgw.preference = (key? : string) => /-autorefresh$/.test(String(key)) ? interval : null;

	const controller = (el as any)._autoRefresh;
	const refreshStub = sinon.stub(el, "refresh").returns(undefined as any);

	const cleanup = () =>
	{
		liveEgw.preference = originalPreference;
		refreshStub.restore();
		el.remove();
	};
	return {el, controller, refreshStub, cleanup};
}

/** Is a poll timer currently armed? */
const isRunning = (controller : any) => controller.timer !== null;

describe("Et2NextmatchAutoRefresh visibility gating", () =>
{
	it("polls while the grid is rendered and its interval preference is set", async() =>
	{
		const {controller, cleanup} = await createNextmatch(30);
		try
		{
			controller.restart();
			assert.isTrue(isRunning(controller), "a rendered, visible grid with an interval should be polling");
		}
		finally
		{
			cleanup();
		}
	});

	it("does not poll when the app opted out with disable_autorefresh", async() =>
	{
		const {el, controller, cleanup} = await createNextmatch(30);
		try
		{
			el.settings = {disable_autorefresh: true};
			controller.restart();
			assert.isFalse(isRunning(controller), "disable_autorefresh should win over any stored interval");
		}
		finally
		{
			cleanup();
		}
	});

	it("stops polling once the grid stops being rendered, with no hide/show or visibilitychange", async() =>
	{
		const {el, controller, cleanup} = await createNextmatch(30);
		try
		{
			controller.restart();
			assert.isTrue(isRunning(controller), "test setup: should be polling before being hidden");

			// Exactly what an app that swaps between several of its own views in one tab does.
			// Neither the app-tab hide/show events nor document.visibilitychange fire for this.
			el.style.display = "none";
			controller.syncVisibility();

			assert.isFalse(isRunning(controller), "a display:none grid must not keep polling");
		}
		finally
		{
			cleanup();
		}
	});

	it("resumes with one immediate refresh when a grid that had been polling is shown again", async() =>
	{
		const {el, controller, refreshStub, cleanup} = await createNextmatch(30);
		try
		{
			controller.restart();
			el.style.display = "none";
			controller.syncVisibility();
			refreshStub.resetHistory();

			el.style.display = "";
			controller.syncVisibility();

			assert.isTrue(isRunning(controller), "showing the grid again should re-arm the timer");
			assert.isTrue(refreshStub.calledOnce, "rows may have gone stale while stopped, so resuming refreshes once");
		}
		finally
		{
			cleanup();
		}
	});

	it("does not refresh on first becoming visible if it had never polled", async() =>
	{
		const {el, controller, refreshStub, cleanup} = await createNextmatch(30);
		try
		{
			// Loaded while hidden - the grid's own load already fetched current rows, so
			// becoming visible must not stack another full reload on top of it.
			el.style.display = "none";
			controller.restart();
			assert.isFalse(isRunning(controller), "test setup: should not be polling while hidden");
			refreshStub.resetHistory();

			el.style.display = "";
			controller.syncVisibility();

			assert.isTrue(isRunning(controller), "becoming visible should start polling");
			assert.isTrue(refreshStub.notCalled, "nothing went stale, so there is nothing to catch up on");
		}
		finally
		{
			cleanup();
		}
	});

	it("keeps polling a grid that is merely scrolled out of view", async() =>
	{
		const {el, controller, cleanup} = await createNextmatch(30);
		try
		{
			controller.restart();
			// Still rendered, just somewhere nobody is looking. This is the case a
			// ResizeObserver deliberately does not report, unlike an IntersectionObserver.
			el.style.position = "absolute";
			el.style.top = "-10000px";
			controller.syncVisibility();

			assert.isTrue(isRunning(controller), "off-screen but rendered should still poll");
		}
		finally
		{
			cleanup();
		}
	});
});
