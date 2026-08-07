/**
 * Test harness for the "notification" module (egw_notification.js) -
 * MODULE_WND_LOCAL.
 *
 * Wraps the browser Notification API, which isn't available/controllable
 * in a real, deterministic way in a headless test - `env.NotificationStub`
 * replaces `window.Notification` with a minimal, spyable stand-in before
 * the module loads (the module reads `Notification.permission` once at
 * MODULE_WND_LOCAL factory time, so the stub must be in place first).
 */
import {createEgwCoreEnv, EgwCoreEnv} from "./EgwCoreHarness";
import * as sinon from "sinon";

export interface NotificationInstance
{
	title : string;
	options : any;
	close : sinon.SinonStub;
	onclick : any;
	onshow : any;
	onclose : any;
	onerror : any;
}

export interface EgwNotificationEnv extends EgwCoreEnv
{
	instances : NotificationInstance[];
	permission : string;
}

export async function createEgwNotificationEnv(initialPermission : string = 'granted') : Promise<EgwNotificationEnv>
{
	const base = await createEgwCoreEnv(Object.assign({webserverUrl: 'https://example.test'}));
	const env = base as EgwNotificationEnv;

	env.instances = [];
	env.permission = initialPermission;

	env.egw.extend('debug', env.egw.MODULE_GLOBAL, () => ({
		debug: () => {},
		debug_level: () => 0
	}));

	env.egw.extend('misc-stub', env.egw.MODULE_GLOBAL, () => ({
		app_name: () => 'testapp',
		preference: () => 'en',
		image: () => 'icon.png'
	}));

	const NotificationStub : any = function(this : NotificationInstance, title : string, options : any)
	{
		this.title = title;
		this.options = options;
		this.close = sinon.stub();
		env.instances.push(this);
	};
	NotificationStub.requestPermission = sinon.stub().callsFake((cb : Function) =>
	{
		// Real Notification.requestPermission() is asynchronous - a microtask
		// delay here matters for a real quirk in notification(): it does NOT
		// wait for this before creating a Notification instance.
		Promise.resolve().then(() => cb(env.permission));
	});
	Object.defineProperty(NotificationStub, 'permission', {
		get: () => env.permission
	});
	(env.window as any).Notification = NotificationStub;

	await loadScript(env.window.document, '/api/js/jsapi/egw_notification.ts', 'module');

	return env;
}

function loadScript(doc : Document, src : string, type? : string) : Promise<void>
{
	return new Promise((resolve, reject) =>
	{
		const script = doc.createElement('script');
		if (type) script.type = type;
		script.src = src;
		script.onload = () => resolve();
		script.onerror = () => reject(new Error('Failed to load '+src));
		doc.head.appendChild(script);
	});
}
