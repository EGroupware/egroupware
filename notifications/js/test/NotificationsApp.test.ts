/**
 * Tests for NotificationsApp (notifications/js/app.ts), the plain-DOM/TS port of the old
 * notifications/js/notificationajaxpopup.js jQuery script - see
 * doc/ai/projects/push-fallback-longpoll.md Phase 1.
 *
 * Following the same approach as api/js/jsapi/test/EgwAppPushGrantCheck.test.ts: methods are
 * exercised directly against the class with a minimal fake `this` (a plain object cast to
 * NotificationsApp), never via `new NotificationsApp()` - the real constructor wires up sidebox/
 * mailvelope/DOM listeners via EgwApp's own constructor, which needs a framework we don't have
 * here and isn't what these tests are about.
 *
 * Covered: the pure data-shaping logic (getTimeLabel, getData, findParent), append()'s parent/
 * child grouping + total/unseen accounting, counterUpdate()'s badge math, update_message_status(),
 * tabToggle(), and run_notifications()'s poll/backoff/stop-while-push-available scheduling,
 * including the mailCheckInterval keep-alive cadence (see doc/ai/projects/
 * push-fallback-longpoll.md's "Mail notification-check polling" follow-up note: neither Dovecot's
 * nor JMAP's mail-server push currently triggers notification_check_mailbox()'s own
 * notify_folders check, only polling does, so this keeps running - at a slower cadence - even
 * once general push is available). egw.pushAvailable()/onPushAvailabilityChange() themselves are
 * tested separately in egw_json.ts's own test file, not here.
 *
 * NOT covered: display()/clickOnMessage()/nav_button()/collapseMessage() and friends - these only
 * build/rearrange DOM from already-tested state (this.notifymessages) and are exercised manually/
 * via the browser instead (see the doc's Phase 1 section). Also not covered: the constructor's
 * `this.egw.onPushAvailabilityChange((available) => { if(!available) this.run_notifications(); })`
 * subscription itself - it's one line of glue calling an already-tested method, and the
 * constructor needs the same framework this file otherwise avoids constructing at all.
 */
import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import "./NotificationsAppTestSetup";
import {NotificationsApp, NotifyMessage} from "../app";

/**
 * Build a minimal fake `this` for calling NotificationsApp methods directly, without going
 * through the real (framework-dependent) constructor.
 */
function createFakeApp(overrides : any = {}) : NotificationsApp
{
	const egw = Object.assign({
		lang: (label : string, ...args : string[]) => args.length ? `${label} ${args.join(' ')}` : label,
		checkNotification: () => false,
		preference: () => undefined,
		json: () => ({sendRequest: sinon.spy()}),
		request: sinon.spy(),
		notification: sinon.spy(),
		message: sinon.spy(),
		// false by default: most tests exercise the polling path, not the "push already handles
		// it" one - see the dedicated pushAvailable() tests under run_notifications() below
		pushAvailable: () => false,
		onPushAvailabilityChange: () => () => {}
	}, overrides.egw || {});

	return <NotificationsApp><unknown>Object.assign(Object.create(NotificationsApp.prototype), {
		notifymessages: {},
		_currentRawData: [],
		pollInterval: 60,
		currentInterval: 60,
		timeoutId: 0,
		filter: '',
		total: 0,
		popupOpen: false,
		// null by default: most tests exercise either the plain-poll or the
		// fully-stopped-while-available path, not the mail-check-keep-alive one - see the
		// dedicated tests under run_notifications() below
		mailCheckInterval: null
	}, overrides, {egw});
}

describe("NotificationsApp.getTimeLabel()", () =>
{
	const current = {date: "2026-09-20 15:00:00"};

	it("buckets an entry from earlier today as TODAY", () =>
	{
		assert.equal(NotificationsApp.prototype.getTimeLabel.call(null, "2026-09-20 10:00:00", current), 0);
	});

	it("buckets an entry from yesterday, same time-of-day offset, as YESTERDAY", () =>
	{
		assert.equal(NotificationsApp.prototype.getTimeLabel.call(null, "2026-09-19 08:00:00", current), 1);
	});

	it("buckets an older entry from the same month as THIS_MONTH", () =>
	{
		assert.equal(NotificationsApp.prototype.getTimeLabel.call(null, "2026-09-01 08:00:00", current), 2);
	});

	it("buckets an entry from the previous month as LAST_MONTH", () =>
	{
		assert.equal(NotificationsApp.prototype.getTimeLabel.call(null, "2026-08-15 08:00:00", current), 3);
	});

	it("falls back to '' for an entry more than a month old", () =>
	{
		assert.equal(NotificationsApp.prototype.getTimeLabel.call(null, "2026-01-01 08:00:00", current), '');
	});
});

describe("NotificationsApp.getData()", () =>
{
	it("extracts message text, title, icon and data-* attributes from the linked entry", () =>
	{
		const html = '<div data-id="42" data-app="infolog"><img src="infolog/icon.png">Some infolog entry</div>';
		const data = NotificationsApp.prototype.getData.call(null, html);

		assert.equal(data.message, "Some infolog entry");
		assert.equal(data.title, "Some infolog entry");
		assert.equal(data.icon, "infolog/icon.png");
		assert.equal(data.id, "42");
		assert.equal(data.app, "infolog");
	});

	it("supports a data-url link (no data-id) for entries with no direct app/id", () =>
	{
		const data = NotificationsApp.prototype.getData.call(null, '<div data-url="https://example.invalid/">External link</div>');

		assert.equal(data.url, "https://example.invalid/");
		assert.isUndefined(data.id);
	});

	it("returns just the plain text when the message has no data-id/data-url link at all", () =>
	{
		const data = NotificationsApp.prototype.getData.call(null, '<b>Plain message</b>');

		assert.equal(data.message, "Plain message");
		assert.isUndefined(data.title);
		assert.isUndefined(data.id);
	});

	it("merges extra_data on top of, without being overridden by, attributes found in the message", () =>
	{
		const data = NotificationsApp.prototype.getData.call(null,
			'<div data-id="42" data-app="infolog">msg</div>', {app: "infolog", account_id: 5});

		assert.equal(data.account_id, 5);
		assert.equal(data.app, "infolog");
	});
});

describe("NotificationsApp.findParent()", () =>
{
	it("finds the existing notification with matching data.id/data.app", () =>
	{
		const app = createFakeApp({
			notifymessages: {
				"7": <NotifyMessage>{data: {id: "42", app: "infolog"}, created: '', current: {date: ''}, extra_data: {}}
			}
		});

		assert.equal(app.findParent("42", "infolog"), "7");
	});

	it("returns undefined when nothing matches", () =>
	{
		const app = createFakeApp();

		assert.isUndefined(app.findParent("42", "infolog"));
	});
});

describe("NotificationsApp.append()", () =>
{
	function row(id : string, appName : string, entryId : string, status? : string, extra : any = {})
	{
		return {
			id, status,
			message: `<div data-id="${entryId}" data-app="${appName}">msg ${id}</div>`,
			created: "2026-09-20 10:00:00",
			current: {date: "2026-09-20 15:00:00"},
			extra_data: Object.assign({app: appName}, extra)
		};
	}

	it("stores one notification per row, keyed by row id", () =>
	{
		const app = createFakeApp();

		app.append([row("1", "infolog", "100"), row("2", "calendar", "200")], false, 2);

		assert.equal(Object.keys((<any>app).notifymessages).length, 2);
		assert.equal((<any>app).total, 2);
	});

	it("groups a later row for the same app/entry as a CHILD of the first, not a new top-level entry", () =>
	{
		const app = createFakeApp();

		app.append([row("1", "infolog", "100"), row("2", "infolog", "100")], false, 2);

		const messages = (<any>app).notifymessages;
		assert.equal(Object.keys(messages).length, 1);
		assert.property(messages["1"].children, "2");
	});

	it("does NOT group a row carrying egw_pr_notify - it always gets its own top-level entry", () =>
	{
		const app = createFakeApp();

		app.append([row("1", "infolog", "100"), row("2", "infolog", "100", undefined, {egw_pr_notify: 1})], false, 2);

		assert.equal(Object.keys((<any>app).notifymessages).length, 2);
	});

	it("marks every row with no status as DISPLAYED and reports it as unseen", () =>
	{
		const updateStatusCalls : any[] = [];
		const app = createFakeApp({
			egw: {json: (method : string, params : any[]) => { updateStatusCalls.push({method, params}); return {sendRequest: sinon.spy()}; }}
		});

		app.append([row("1", "infolog", "100")], false, 1);

		assert.isTrue(updateStatusCalls.some((c) => c.method === "notifications.notifications_ajax.update_status" &&
			c.params[0][0] === "1" && c.params[1] === "DISPLAYED"));
	});

	it("is a no-op when called again with the exact same rows (by id) - avoids reprocessing HTML", () =>
	{
		const rows = [row("1", "infolog", "100", "SEEN")];
		const app = createFakeApp();

		app.append(rows, false, 1);
		(<any>app).notifymessages["1"].status = "MUTATED_TO_PROVE_NO_REPROCESSING";
		app.append(rows, false, 1);

		assert.equal((<any>app).notifymessages["1"].status, "MUTATED_TO_PROVE_NO_REPROCESSING");
	});
});

describe("NotificationsApp.counterUpdate()", () =>
{
	let header : HTMLElement, topmenu : HTMLElement;

	beforeEach(() =>
	{
		header = document.createElement('div');
		header.id = 'egwpopup_header';
		header.appendChild(document.createTextNode(''));
		topmenu = document.createElement('div');
		topmenu.id = 'topmenu_info_notifications';
		document.body.append(header, topmenu);
	});

	afterEach(() =>
	{
		header.remove();
		topmenu.remove();
	});

	it("counts only messages whose status isn't SEEN", () =>
	{
		const app = createFakeApp({
			total: 2,
			notifymessages: {
				"1": {status: "DISPLAYED", extra_data: {app: "infolog"}, created: '', current: {date: ''}, data: {}},
				"2": {status: "SEEN", extra_data: {app: "infolog"}, created: '', current: {date: ''}, data: {}}
			}
		});

		assert.equal(app.counterUpdate(), 1);
	});

	it("writes the total count into the popup header title, regardless of seen/unseen", () =>
	{
		const app = createFakeApp({total: 3});

		app.counterUpdate();

		assert.include(header.childNodes[0].textContent, "3");
	});

	it("returns 0 and doesn't blow up when there are no messages at all", () =>
	{
		const app = createFakeApp();

		assert.equal(app.counterUpdate(), 0);
	});
});

describe("NotificationsApp.update_message_status()", () =>
{
	let message : HTMLElement;

	beforeEach(() =>
	{
		message = document.createElement('div');
		message.id = 'egwpopup_message_1';
		document.body.appendChild(message);
	});

	afterEach(() => message.remove());

	it("adds egwpopup_message_seen when marked SEEN", () =>
	{
		const app = createFakeApp({notifymessages: {"1": {status: "DISPLAYED", created: '', current: {date: ''}, extra_data: {}, data: {}}}});

		app.update_message_status("1", "SEEN", true);

		assert.isTrue(message.classList.contains('egwpopup_message_seen'));
		assert.equal((<any>app).notifymessages["1"].status, "SEEN");
	});

	it("removes egwpopup_message_seen again when marked DISPLAYED/UNSEEN", () =>
	{
		message.classList.add('egwpopup_message_seen');
		const app = createFakeApp({notifymessages: {"1": {status: "SEEN", created: '', current: {date: ''}, extra_data: {}, data: {}}}});

		app.update_message_status("1", "DISPLAYED", true);

		assert.isFalse(message.classList.contains('egwpopup_message_seen'));
	});
});

describe("NotificationsApp.tabToggle()", () =>
{
	it("returns true, sets the app filter and shows the popup when a matching notification exists", () =>
	{
		const toggle = sinon.spy(), display = sinon.spy();
		const app = createFakeApp({
			notifymessages: {"1": {extra_data: {app: "infolog"}, created: '', current: {date: ''}, data: {}}},
			toggle, display
		});

		assert.isTrue(app.tabToggle("infolog"));
		assert.equal((<any>app).filter, "infolog");
		assert.isTrue(toggle.calledOnce);
		assert.isTrue(display.calledOnce);
	});

	it("returns false and touches nothing when no notification matches that app", () =>
	{
		const toggle = sinon.spy();
		const app = createFakeApp({notifymessages: {}, toggle});

		assert.isFalse(app.tabToggle("infolog"));
		assert.isFalse(toggle.called);
	});
});

describe("NotificationsApp.run_notifications()", () =>
{
	it("doesn't even poll the server at all while egw.pushAvailable() already says push is working", () =>
	{
		const json = sinon.spy();
		const app = createFakeApp({egw: {pushAvailable: () => true, json}});

		app.run_notifications();

		assert.isFalse(json.called);
	});

	it("keeps polling at the (slower) mailCheckInterval cadence, not pollInterval, when push is available but a mail account still needs the notify_folders check", async() =>
	{
		const clock = sinon.useFakeTimers();
		try
		{
			const app = createFakeApp({
				pollInterval: 60,
				mailCheckInterval: 180,
				egw: {
					pushAvailable: () => true,
					json: (_m : string, _p : any[], cb : Function) =>
					{
						cb({});
						return {sendRequest: (_close : boolean, _method : string, _cb : Function) => {}};
					}
				}
			});
			const setTimeoutSpy = sinon.spy(window, 'setTimeout');

			app.run_notifications();
			await clock.tickAsync(0);

			assert.isTrue(setTimeoutSpy.calledWith(sinon.match.func, 180 * 1000),
				'must poll at mailCheckInterval (180s), not pollInterval (60s) or not at all');
			setTimeoutSpy.restore();
		}
		finally { clock.restore(); }
	});

	it("stops rescheduling once the response comes back after push has meanwhile become available", async() =>
	{
		const clock = sinon.useFakeTimers();
		try
		{
			let pushAvailable = false;
			const app = createFakeApp({
				egw: {
					pushAvailable: () => pushAvailable,
					json: (_m : string, _p : any[], cb : Function) =>
					{
						// push comes up while the (already in-flight) poll request is still pending
						pushAvailable = true;
						cb({});
						return {sendRequest: (_close : boolean, _method : string, _cb : Function) => {}};
					}
				}
			});
			const setTimeoutSpy = sinon.spy(window, 'setTimeout');

			app.run_notifications();
			await clock.tickAsync(0);

			assert.isFalse(setTimeoutSpy.called);
			setTimeoutSpy.restore();
		}
		finally { clock.restore(); }
	});

	it("reschedules at the normal poll interval on a successful, non-push response", async() =>
	{
		const clock = sinon.useFakeTimers();
		try
		{
			const app = createFakeApp({
				pollInterval: 60,
				egw: {json: (_m : string, _p : any[], cb : Function) =>
				{
					cb({});
					return {sendRequest: (_close : boolean, _method : string, _cb : Function) => {}};
				}}
			});
			const setTimeoutSpy = sinon.spy(window, 'setTimeout');

			app.run_notifications();
			await clock.tickAsync(0);

			assert.isTrue(setTimeoutSpy.calledWith(sinon.match.func, 60 * 1000));
			setTimeoutSpy.restore();
		}
		finally { clock.restore(); }
	});

	it("doubles the retry interval on a failed request, instead of hammering the server", async() =>
	{
		const clock = sinon.useFakeTimers();
		try
		{
			const app = createFakeApp({
				currentInterval: 60,
				egw: {json: (_m : string, _p : any[], _cb : Function) =>
					({sendRequest: (_close : boolean, _method : string, errCb : Function) => errCb({})})}
			});
			const setTimeoutSpy = sinon.spy(window, 'setTimeout');

			app.run_notifications();
			await clock.tickAsync(0);

			assert.equal((<any>app).currentInterval, 120);
			assert.isTrue(setTimeoutSpy.calledWith(sinon.match.func, 120 * 1000));
			setTimeoutSpy.restore();
		}
		finally { clock.restore(); }
	});
});
