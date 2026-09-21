/**
 * Test harness for the "json"/"jsonq" modules (egw_json.js, egw_jsonq.js).
 *
 * Loads the REAL, unmodified egw_json.js and egw_jsonq.js on top of the
 * Layer 1 core environment (see EgwCoreHarness for why an iframe per test).
 * Three things they need that aren't part of the composition engine:
 *
 * 1. jQuery and egw_utils.js (for `ajaxUrl`) - real runtime dependencies,
 *    loaded/imported for real since they're lightweight and self-contained
 *    (egw_utils.js only imports egw_core.js, already loaded).
 * 2. `egw.debug` - same no-op stand-in used by the data.js harness.
 * 3. `window.fetch` - json_request.sendRequest()'s async path calls the
 *    window's real fetch(). A controllable fake replaces it so tests never
 *    hit the network, while still exercising the real request/response
 *    handling code around it.
 * 4. egw_json.js has a side-effect-only `import './egw.js'` (just to
 *    guarantee `window.egw` exists, which this harness already
 *    guarantees). Redirected via a per-document import map to an empty
 *    stub, same as EgwDataHarness - see EgwJsStub.ts.
 * 5. A handful of the built-in response plugins (message/css/redirect) call
 *    methods from OTHER modules (egw_message.js's message(), egw_files.js's
 *    includeCSS()) or bare globals (egw_insertJS, egw_appWindowOpen) not
 *    loaded here - stubbed as sinon spies via `env.stubs.*` so tests can
 *    assert on them without pulling in those modules' own dependencies.
 */
import {createEgwCoreEnv, EgwCoreEnv} from "./EgwCoreHarness";
import * as sinon from "sinon";

export interface FakeFetchCall
{
	url : string;
	init : any;
	resolve(json : any) : void;
	resolveNotOk(status : number, body? : any) : void;
	/**
	 * Resolves this fetch as a text/event-stream response (egw_push_fallback.ts's SSE probe,
	 * Phase 6) and returns a controller to feed it bytes over time, the way a real stream would -
	 * unlike resolve()/resolveNotOk(), the body isn't known/fixed at call time.
	 */
	resolveStream() : FakeSseStream;
	reject(err : any) : void;
}

/**
 * Feeds a fake text/event-stream response's body incrementally, as a real one would - each
 * push() call is one more chunk a ReadableStream reader's .read() yields; end() makes the next
 * (or a pending) .read() resolve {done: true}, same as the stream actually closing.
 */
export interface FakeSseStream
{
	push(_text : string) : void;
	end() : void;
}

function createFakeSseStream(signal? : AbortSignal) : {response : any, stream : FakeSseStream}
{
	const encoder = new TextEncoder();
	const queue : {value? : Uint8Array, done : boolean}[] = [];
	let waitingResolve : ((_result : any) => void) | null = null;
	let waitingReject : ((_err : any) => void) | null = null;
	let ended = false;

	function deliver(item : {value? : Uint8Array, done : boolean})
	{
		if(waitingResolve)
		{
			const resolve = waitingResolve;
			waitingResolve = waitingReject = null;
			resolve(item);
		}
		else
		{
			queue.push(item);
		}
	}

	// A real fetch()'s body reader rejects a pending (or future) .read() once the request's
	// AbortController fires - egw_push_fallback.ts's ack-timeout relies on exactly that to break
	// out of its read loop when nothing ever arrives, so the fake needs to do the same.
	signal?.addEventListener('abort', () =>
	{
		if(waitingReject)
		{
			const reject = waitingReject;
			waitingResolve = waitingReject = null;
			reject(new DOMException('The operation was aborted.', 'AbortError'));
		}
	});

	const stream : FakeSseStream = {
		push(text : string)
		{
			deliver({value: encoder.encode(text), done: false});
		},
		end()
		{
			ended = true;
			deliver({value: undefined, done: true});
		}
	};

	const response = {
		ok: true, status: 200,
		headers: {get: (h : string) => h.toLowerCase() === 'content-type' ? 'text/event-stream' : null},
		body: {
			getReader: () => ({
				read() : Promise<any>
				{
					if(queue.length > 0) return Promise.resolve(queue.shift());
					if(ended) return Promise.resolve({value: undefined, done: true});
					if(signal?.aborted) return Promise.reject(new DOMException('The operation was aborted.', 'AbortError'));
					return new Promise((resolve, reject) => { waitingResolve = resolve; waitingReject = reject; });
				}
			})
		}
	};

	return {response, stream};
}

/**
 * Stands in for a real WebSocket - openWebSocket() (egw_json.ts) only ever touches .send()/
 * .close() and the four on*() handlers, so that's all this implements. Tests drive it by calling
 * onopen/onmessage/onerror/onclose directly (as if the browser had), NOT by making close()
 * actually schedule an async onclose itself - keeps tests synchronous and in full control of
 * exactly which transition happens when.
 */
export class FakeWebSocket
{
	static readonly CONNECTING = 0;
	static readonly OPEN = 1;
	static readonly CLOSING = 2;
	static readonly CLOSED = 3;

	readyState = FakeWebSocket.CONNECTING;
	onopen : any;
	onmessage : any;
	onerror : any;
	onclose : any;
	/** every .send() payload, in order */
	sent : string[] = [];
	/** true once .close() has been called - egw_json.ts never inspects this, it's for assertions */
	closeCalled = false;

	constructor(public url : string)
	{
	}

	send(data : string)
	{
		this.sent.push(data);
	}

	close()
	{
		this.closeCalled = true;
	}
}

export interface EgwJsonEnv extends EgwCoreEnv
{
	/** every window.fetch() call made by json_request.sendRequest(), in order */
	fetchCalls : FakeFetchCall[];
	/** every `new WebSocket(...)` openWebSocket() created, in order (incl. reconnect attempts) */
	webSockets : FakeWebSocket[];
	stubs : {
		message : sinon.SinonStub;
		includeCSS : sinon.SinonStub;
	};
}

export async function createEgwJsonEnv(prefs : object = {}) : Promise<EgwJsonEnv>
{
	const base = await createEgwCoreEnv(Object.assign({webserverUrl: 'https://example.test'}, prefs));
	const env = base as EgwJsonEnv;
	env.fetchCalls = [];
	env.webSockets = [];
	(env.window as any).WebSocket = class extends FakeWebSocket
	{
		constructor(url : string)
		{
			super(url);
			env.webSockets.push(this);
		}
	};

	await loadScript(env.window.document, '/vendor/bower-asset/jquery/dist/jquery.min.js');

	env.egw.extend('debug', env.egw.MODULE_GLOBAL, () => ({
		debug: () => {},
		debug_level: () => 0
	}));

	env.stubs = {
		message: sinon.stub(),
		includeCSS: sinon.stub()
	};
	env.egw.extend('stubs', env.egw.MODULE_GLOBAL, () => ({
		message: env.stubs.message,
		includeCSS: env.stubs.includeCSS,
		// handleError()'s user-facing message text; identity is enough here
		lang: (msg : string) => msg
	}));
	// bare globals the "assign"/"redirect" built-in plugins call, normally
	// defined in jsapi.js (not loaded here)
	(env.window as any).egw_insertJS = sinon.stub();
	(env.window as any).egw_appWindowOpen = sinon.stub();

	env.window.fetch = (url : string, init : any) : Promise<any> =>
	{
		return new Promise((resolve, reject) =>
		{
			env.fetchCalls.push({
				url, init,
				resolve(json : any)
				{
					resolve({ok: true, status: 200, headers: {get: () => null}, json: () => Promise.resolve(json)});
				},
				resolveNotOk(status : number, body : any = {})
				{
					resolve({
						ok: false, status, statusText: 'Error',
						headers: {get: (h : string) => h === 'Content-Type' ? 'application/json' : 'Thu, 1 Jan 1970 00:00:00 GMT'},
						json: () => Promise.resolve(body)
					});
				},
				resolveStream() : FakeSseStream
				{
					const {response, stream} = createFakeSseStream(init?.signal);
					resolve(response);
					return stream;
				},
				reject
			});
		});
	};

	const importMap = env.window.document.createElement('script');
	importMap.type = 'importmap';
	importMap.textContent = JSON.stringify({
		imports: {'/api/js/jsapi/egw.js': '/api/js/jsapi/test/EgwJsStub.ts'}
	});
	env.window.document.head.appendChild(importMap);

	await loadScript(env.window.document, '/api/js/jsapi/egw_json.ts', 'module');
	await loadScript(env.window.document, '/api/js/jsapi/egw_jsonq.ts', 'module');

	return env;
}

export function loadScript(doc : Document, src : string, type? : string) : Promise<void>
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
