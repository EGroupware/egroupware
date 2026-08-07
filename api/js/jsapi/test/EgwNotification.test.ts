/**
 * Tests for egw_notification.js ("notification" module) - MODULE_WND_LOCAL.
 *
 * No prior test coverage. See EgwNotificationHarness for how it's loaded
 * and how the browser Notification API is stubbed.
 */
import {assert} from "@open-wc/testing";
import {createEgwNotificationEnv, EgwNotificationEnv} from "./EgwNotificationHarness";

function wait(ms : number = 0) : Promise<void>
{
	return new Promise(resolve => setTimeout(resolve, ms));
}

describe('egw_notification.js (notification)', () =>
{
	let env : EgwNotificationEnv;

	afterEach(() =>
	{
		env.destroy();
	});

	describe('with permission already granted', () =>
	{
		beforeEach(async() =>
		{
			env = await createEgwNotificationEnv('granted');
		});

		it('loads and registers the expected methods', () =>
		{
			const instance = env.egw('testapp', env.window);
			assert.isFunction(instance.notification);
			assert.isFunction(instance.checkNotification);
			assert.isFunction(instance.killAliveNotifications);
		});

		it('creates a Notification with the given title and default options', () =>
		{
			env.egw('testapp', env.window).notification('Hello', {});

			assert.equal(env.instances.length, 1);
			assert.equal(env.instances[0].title, 'Hello');
			assert.equal(env.instances[0].options.dir, 'ltr');
			assert.equal(env.instances[0].options.tag, 'testapp');
			assert.equal(env.instances[0].options.requireInteraction, false);
		});

		it('uses given options over the defaults', () =>
		{
			env.egw('testapp', env.window).notification('Hello', {
				dir: 'rtl', tag: 'mytag', body: 'body text', requireInteraction: true
			});

			assert.equal(env.instances[0].options.dir, 'rtl');
			assert.equal(env.instances[0].options.tag, 'mytag');
			assert.equal(env.instances[0].options.body, 'body text');
			assert.equal(env.instances[0].options.requireInteraction, true);
		});

		it('wires up given onclick/onshow/onclose callbacks', () =>
		{
			const onclick = () => {};
			const onshow = () => {};
			const onclose = () => {};
			env.egw('testapp', env.window).notification('Hello', {onclick, onshow, onclose});

			assert.strictEqual(env.instances[0].onclick, onclick);
			assert.strictEqual(env.instances[0].onshow, onshow);
			assert.strictEqual(env.instances[0].onclose, onclose);
		});

		it('defaults onerror to a function (that logs via egw.debug) when none is given', () =>
		{
			env.egw('testapp', env.window).notification('Hello', {});
			assert.isFunction(env.instances[0].onerror);
		});

		it('checkNotification() returns true when permission is granted', () =>
		{
			assert.isTrue(env.egw('testapp', env.window).checkNotification());
		});

		it('killAliveNotifications() closes all tracked notifications and clears the list', () =>
		{
			const instance = env.egw('testapp', env.window);
			instance.notification('First', {});
			instance.notification('Second', {});
			assert.equal(env.instances.length, 2);

			instance.killAliveNotifications();

			assert.isTrue(env.instances[0].close.calledOnce);
			assert.isTrue(env.instances[1].close.calledOnce);

			// calling again must be a no-op (list was cleared) - close() not called a second time
			instance.killAliveNotifications();
			assert.isTrue(env.instances[0].close.calledOnce);
			assert.isTrue(env.instances[1].close.calledOnce);
		});

		it('killAliveNotifications() with nothing alive does not throw', () =>
		{
			assert.doesNotThrow(() => env.egw('testapp', env.window).killAliveNotifications());
		});
	});

	describe('with permission not yet decided ("default")', () =>
	{
		beforeEach(async() =>
		{
			env = await createEgwNotificationEnv('default');
		});

		it('KNOWN QUIRK: creates a Notification synchronously without waiting for requestPermission to resolve', async() =>
		{
			// notification() calls Notification.requestPermission(cb) but does
			// NOT wait for cb to fire before continuing - it constructs a
			// Notification immediately regardless of whether permission is
			// actually granted yet.
			env.egw('testapp', env.window).notification('Hello', {});

			assert.equal(env.instances.length, 1, 'a Notification is created synchronously, before permission is confirmed');

			await wait(10);
			// once requestPermission's callback fires with 'granted', notification()
			// calls itself again - so granted permission produces a SECOND,
			// duplicate Notification for the same original call.
			assert.equal(env.instances.length, 1,
				'requestPermission was stubbed to resolve with "default" again, not "granted", so no recursive call happens');
		});

		it('KNOWN QUIRK: if the user grants permission, a duplicate Notification is created via the recursive retry', async() =>
		{
			env.permission = 'granted'; // what the user will "answer" once asked
			env.egw('testapp', env.window).notification('Hello', {});

			assert.equal(env.instances.length, 1);

			await wait(10);

			assert.equal(env.instances.length, 2, 'notification() called itself again once requestPermission resolved to "granted"');
			assert.equal(env.instances[1].title, 'Hello');
		});

		it('checkNotification() requests permission and returns false until it is granted', () =>
		{
			assert.isFalse(env.egw('testapp', env.window).checkNotification());
		});
	});

	describe('without Notification support', () =>
	{
		beforeEach(async() =>
		{
			env = await createEgwNotificationEnv('granted');
			delete (env.window as any).Notification;
		});

		it('notification() returns false and creates nothing', () =>
		{
			const result = env.egw('testapp', env.window).notification('Hello', {});
			assert.isFalse(result);
			assert.equal(env.instances.length, 0);
		});

		it('checkNotification() returns false', () =>
		{
			assert.isFalse(env.egw('testapp', env.window).checkNotification());
		});
	});
});
