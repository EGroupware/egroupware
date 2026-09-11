/**
 * Test harness for the "timer" module (egw_timer.js) - MODULE_GLOBAL.
 *
 * egw_timer.js captures `document.querySelector('#topmenu_timer')` once, at
 * module-registration time - the fixture element must exist in the DOM
 * BEFORE the script is loaded. `sprintf` is imported for real from
 * egw_action_common.ts (lightweight, no side effects). Everything else it
 * touches - egw.request()/open()/message()/lang()/preference()/
 * getTimezoneOffset(), and the Et2Dialog global - is stubbed, mirroring
 * EgwOpenHarness's approach: real dialog rendering/templates have nothing
 * to do with the timer state machine this module implements.
 *
 * `lang()`'s stub does real %1/%2/... substitution (not just an identity
 * passthrough) since several code paths build user-facing error messages
 * this way and tests want to assert on the substituted text.
 *
 * `getTimezoneOffset()` defaults to 0 (UTC == local) unless a test
 * overrides `env.stubs.getTimezoneOffset`, to keep date math in tests
 * simple and deterministic.
 */
import {createEgwCoreEnv, EgwCoreEnv} from "./EgwCoreHarness";
import * as sinon from "sinon";

export interface EgwTimerEnv extends EgwCoreEnv
{
	topmenu : HTMLElement;
	stubs : {
		request : sinon.SinonStub;
		open : sinon.SinonStub;
		message : sinon.SinonStub;
		lang : sinon.SinonStub;
		preference : sinon.SinonStub;
		getTimezoneOffset : sinon.SinonStub;
	};
	Et2DialogStub : any;
}

export async function createEgwTimerEnv(options : {topmenuState? : object} = {}) : Promise<EgwTimerEnv>
{
	const base = await createEgwCoreEnv({webserverUrl: 'https://example.test'});
	const env = base as EgwTimerEnv;
	const doc = env.window.document;

	env.topmenu = doc.createElement('div');
	env.topmenu.id = 'topmenu_timer';
	if (typeof options.topmenuState !== 'undefined')
	{
		env.topmenu.setAttribute('data-state', JSON.stringify(options.topmenuState));
	}
	doc.body.appendChild(env.topmenu);

	env.stubs = {
		request: sinon.stub().resolves('tse-id-123'),
		open: sinon.stub(),
		message: sinon.stub(),
		lang: sinon.stub().callsFake((msg : string, ...args : any[]) =>
			msg.replace(/%(\d+)/g, (_, n) => args[parseInt(n) - 1] ?? '')),
		preference: sinon.stub().returns(undefined),
		getTimezoneOffset: sinon.stub().returns(0)
	};

	env.egw.extend('timer-test-stub', env.egw.MODULE_GLOBAL, () => ({
		request: env.stubs.request,
		open: env.stubs.open,
		message: env.stubs.message,
		lang: env.stubs.lang,
		preference: env.stubs.preference,
		getTimezoneOffset: env.stubs.getTimezoneOffset
	}));

	(env.window as any).egw_ready = Promise.resolve();

	const HTMLElementInWindow : any = (env.window as any).HTMLElement;
	const Et2DialogStub : any = class extends HTMLElementInWindow
	{
		attrs : any;
		value : any = {};
		updateComplete : Promise<void> = Promise.resolve();
		constructor(_egw? : any)
		{
			super();
		}
		transformAttributes(attrs : any)
		{
			this.attrs = attrs;
			if (typeof attrs.value !== 'undefined') Object.assign(this, {value: attrs.value});
		}
	};
	Et2DialogStub.show_dialog = sinon.stub();
	Et2DialogStub.alert = sinon.stub();
	Et2DialogStub.BUTTONS_OK = 'ok';
	Et2DialogStub.BUTTONS_OK_CANCEL = 'ok_cancel';
	Et2DialogStub.BUTTONS_YES_NO = 'yes_no';
	Et2DialogStub.WARNING_MESSAGE = 'warning';
	Et2DialogStub.ERROR_MESSAGE = 'error';
	Et2DialogStub.QUESTION_MESSAGE = 'question';
	Et2DialogStub.OK_BUTTON = 'ok';
	Et2DialogStub.CANCEL_BUTTON = 'cancel';
	Et2DialogStub.YES_BUTTON = 'yes';
	Et2DialogStub.NO_BUTTON = 'no';
	env.window.customElements.define('et2-dialog-timer-stub', Et2DialogStub);
	(env.window as any).Et2Dialog = Et2DialogStub;
	env.Et2DialogStub = Et2DialogStub;

	await loadScript(doc, '/api/js/jsapi/egw_timer.ts', 'module');

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
