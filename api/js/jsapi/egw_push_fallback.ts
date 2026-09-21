/**
 * EGroupware clientside API object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 */

import './egw_json';

/**
 * Generic fallback message-delivery driver for installs with no working real-time push (no
 * swoolepush daemon, or one this particular browser can't reach) - see
 * doc/ai/projects/push-fallback-longpoll.md Phases 3 and 6.
 *
 * While egw.pushAvailable() says push is NOT working, tries SSE first (a single request racing
 * an "ack" chunk against a short timeout - see #trySse()), falling back to the bounded long-poll
 * at Api\Json\Push::ajax_poll() (api/src/Json/Push.php) if that doesn't survive this instance's
 * proxy/CDN chain end-to-end. Either way, every push-carried message (entry-change broadcasts,
 * notification popups, import progress, ...) reaches this document through the exact same
 * response-handling path a real push message or a normal ajax response would, since ajax_poll()
 * just replays whatever notifications_push (the SQL fallback PushBackend) has queued - nothing
 * new to dispatch on the client side.
 *
 * Lives here (api/js/jsapi), not the notifications app, and runs for every logged-in user
 * regardless of which apps they have - notifications/js/app.ts used to own an equivalent poll
 * loop by itself (gated behind the notifications app being enabled) and now just consumes what
 * this delivers, via the same egw.pushAvailable()/onPushAvailabilityChange() signal.
 *
 * One instance per window/popup, matching egw_json.ts's one-websocket-per-window granularity -
 * see this class's own constructor, instantiated once at the bottom of this file.
 */
class PushFallback
{
	/**
	 * Menuaction for Api\Json\Push::ajax_poll() - the double-colon form resolves $appName to
	 * 'api' server-side (Api\Json\Request::handleRequest()), so it needs no app permission.
	 */
	static readonly MENUACTION = 'EGroupware\\Api\\Json\\Push::ajax_poll';

	static readonly MIN_RETRY_MS = 2000;
	static readonly MAX_RETRY_MS = 60000;

	/**
	 * Cadence used instead of immediately re-issuing, when the server reports it degraded this
	 * call because its concurrency budget was already exhausted (Push::ajax_poll()'s
	 * {degraded: true}, see doc/ai/projects/push-fallback-longpoll.md Phase 4) - immediately
	 * re-issuing against an endpoint that just responded instantly *because* it's over budget
	 * would only add to that same load in a tight loop.
	 */
	static readonly DEGRADED_RETRY_MS = 15000;

	/**
	 * How long to wait for the SSE "ack" chunk before deciding this path doesn't survive
	 * end-to-end (most likely an intermediate proxy/CDN silently buffering the whole response)
	 * and falling back to plain long-polling - see doc/ai/projects/push-fallback-longpoll.md
	 * Phase 6.
	 */
	static readonly SSE_ACK_TIMEOUT_MS = 3000;

	#running = false;
	#retryMs = PushFallback.MIN_RETRY_MS;
	#request : any = null;
	#retryTimer : any = null;
	#sseAbort : AbortController = null;

	/**
	 * True from the moment our own SSE ack arrives until the stream ends - see the
	 * onPushAvailabilityChange() listener's own docs in the constructor for why this exists.
	 */
	#sseActive = false;

	/**
	 * Once an SSE attempt fails to even produce the ack within SSE_ACK_TIMEOUT_MS, don't keep
	 * re-trying it for the rest of this page's lifetime - a page reload (which any relevant
	 * server/proxy config change already requires to be noticed anyway, same as any other build
	 * or config change) is what re-evaluates this.
	 */
	#sseUnsupported = false;

	constructor()
	{
		// Same guard as egw.js's build-epoch poll, and for the same reason (found live
		// 2026-09-2x): there is no session on the login page at all, so ajax_poll() there just
		// hits json.php's login_redirect() path every time, which apply()s
		// 'framework.callOnLogout' (thrown - the login page never loads the framework at all)
		// and then redirects back to /login.php - reloading the page, restarting this class, and
		// repeating forever. Submitting the login form is itself a full page navigation anyway,
		// so there's nothing a push-fallback connection could usefully do here regardless.
		if((<any>window).egw_appName === 'login') return;

		(<any>window).egw_ready.then(() =>
		{
			if(!egw.pushAvailable())
			{
				this.#start();
			}
			egw.onPushAvailabilityChange((available) =>
			{
				// Our OWN successful SSE connection reports itself through this exact same
				// signal (egw.notePushConnected(), so every other consumer sees it exactly like
				// the websocket) - without the #sseActive guard, that report would immediately
				// reach this very listener and call #stop() on the connection that just
				// succeeded, a self-abort feedback loop found while testing this. Only an
				// availability change we did NOT cause ourselves should stop us.
				if(available)
				{
					if(!this.#sseActive) this.#stop();
				}
				else
				{
					this.#start();
				}
			});
		});
	}

	#start()
	{
		if(this.#running) return;
		this.#running = true;
		if(this.#sseUnsupported)
		{
			this.#poll();
		}
		else
		{
			this.#trySse();
		}
	}

	#stop()
	{
		this.#running = false;
		if(this.#retryTimer)
		{
			window.clearTimeout(this.#retryTimer);
			this.#retryTimer = null;
		}
		this.#request?.abort?.();
		this.#request = null;
		this.#sseAbort?.abort();
		this.#sseAbort = null;
	}

	/**
	 * Wraps a response callback so it only ever runs once per response - egw_json.ts's
	 * handleResponse() can invoke the callback TWICE for one response: once via the 'data'
	 * plugin (with the real payload, eg. {degraded: true}) when a `Response::data(...)` entry is
	 * present, and unconditionally again via its own generic "last dispatched entry" fallback
	 * whenever the response also carries any non-data entry (eg. a delivered message) - which
	 * Push::ajax_poll()'s degraded path can produce (it still calls notifications_push::get()
	 * before adding the degraded flag). The 'data' plugin's invocation always runs first if it
	 * runs at all, so keeping only the FIRST call is correct - found while wiring up Phase 6's
	 * reuse of this same response for its non-SSE fallback case, but pre-existing since Phase 4.
	 */
	#once(_handler : (_data : any) => void) : (_data : any) => void
	{
		let handled = false;
		return (_data : any) =>
		{
			if(handled) return;
			handled = true;
			_handler(_data);
		};
	}

	/**
	 * Shared between the plain long-poll's own response and SSE's "server chose not to stream"
	 * fallback response - decides whether to reissue immediately or back off for
	 * DEGRADED_RETRY_MS, per Phase 4's {degraded: true} signal.
	 */
	#handlePollResponse(_data : any)
	{
		this.#retryMs = PushFallback.MIN_RETRY_MS;
		if(!this.#running) return;

		if(_data && _data.degraded)
		{
			this.#retryTimer = window.setTimeout(() => this.#poll(), PushFallback.DEGRADED_RETRY_MS);
		}
		else
		{
			this.#poll();
		}
	}

	/**
	 * Classic bounded long-poll: immediately re-issues on every successful return (delivered or
	 * genuinely empty after the server's own bounded wait), backs off exponentially on a
	 * failure, and waits DEGRADED_RETRY_MS instead of reissuing immediately when told the server
	 * degraded this call (see #handlePollResponse()).
	 */
	#poll()
	{
		if(!this.#running) return;

		this.#request = egw.json(PushFallback.MENUACTION, [], this.#once((_data) => this.#handlePollResponse(_data)))
			.sendRequest(true, 'POST', () =>
		{
			// server/network error - back off instead of hammering an endpoint that's failing.
			// Use the CURRENT delay first, then double it for next time - not the other way
			// round, or the very first retry would already wait double MIN_RETRY_MS.
			const delay = this.#retryMs;
			this.#retryMs = Math.min(this.#retryMs * 2, PushFallback.MAX_RETRY_MS);
			if(this.#running)
			{
				this.#retryTimer = window.setTimeout(() => this.#poll(), delay);
			}
		});
	}

	/**
	 * Attempts SSE: issues ONE request with sse=true and races its outcome against
	 * SSE_ACK_TIMEOUT_MS, per doc/ai/projects/push-fallback-longpoll.md Phase 6 - whichever of
	 * these happens first decides what follows, no separate probe step:
	 * - an "ack" chunk arrives -> commit to SSE for the rest of this page's lifetime, marking
	 *   egw.pushAvailable() true (a working SSE stream is, to every other consumer,
	 *   indistinguishable from the websocket).
	 * - a normal (non-stream) response arrives first (degraded or not) -> handled exactly like a
	 *   plain long-poll response, via the exact same #handlePollResponse()/#once() as #poll().
	 * - neither arrives in time -> the connection is silently hanging somewhere in the stack
	 *   (most likely a buffering proxy/CDN) - abort it and fall back to plain long-polling for
	 *   the rest of this page's lifetime.
	 */
	async #trySse()
	{
		if(!this.#running) return;

		const controller = new AbortController();
		this.#sseAbort = controller;
		const ackTimer = window.setTimeout(() => controller.abort(), PushFallback.SSE_ACK_TIMEOUT_MS);
		const jsonRequest = egw.json(PushFallback.MENUACTION, [], this.#once((_data) => this.#handlePollResponse(_data)));

		let response : Response;
		try
		{
			response = await window.fetch(egw.ajaxUrl(PushFallback.MENUACTION), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({request: {parameters: [true]}}),
				signal: controller.signal
			});
		}
		catch(_e)
		{
			window.clearTimeout(ackTimer);
			this.#sseAbort = null;

			if(controller.signal.aborted)
			{
				// our OWN ack-timeout fired - that's the actual "doesn't survive end-to-end"
				// signal this method exists to detect, distinct from an unrelated network error
				this.#sseUnsupported = true;
				if(this.#running) this.#poll();
			}
			else
			{
				// a genuine, unrelated network error - SSE itself isn't ruled out by this, so
				// back off and retry SSE again, exactly like #poll() backs off and retries itself
				const delay = this.#retryMs;
				this.#retryMs = Math.min(this.#retryMs * 2, PushFallback.MAX_RETRY_MS);
				if(this.#running)
				{
					this.#retryTimer = window.setTimeout(() => this.#trySse(), delay);
				}
			}
			return;
		}

		const isSse = (response.headers.get('content-type') || '').startsWith('text/event-stream');
		if(!isSse)
		{
			window.clearTimeout(ackTimer);
			this.#sseAbort = null;
			this.#sseUnsupported = true;
			const parsed = await response.json();
			jsonRequest.handleResponse(parsed);
			return;
		}

		// A SEPARATE, callback-less request for dispatching stream chunks - NOT jsonRequest above:
		// handleResponse() invokes its callback generically for any response carrying a non-'data'
		// entry (a delivered message qualifies), which would otherwise call #handlePollResponse()
		// - meaningful for jsonRequest's own (non-streaming) response, wrong for a message that
		// arrives *while already streaming*, where it would start a redundant plain long-poll
		// alongside the perfectly good SSE stream. Found while testing this exact scenario.
		const streamRequest = egw.json(PushFallback.MENUACTION, [], null);
		const reader = response.body.getReader();
		const decoder = new TextDecoder();
		let buffer = '';
		let ackReceived = false;

		try
		{
			while(this.#running)
			{
				const {value, done} = await reader.read();
				if(done) break;

				if(!ackReceived)
				{
					ackReceived = true;
					window.clearTimeout(ackTimer);
					this.#sseActive = true;
					egw.notePushConnected();
				}

				buffer += decoder.decode(value, {stream: true});
				let boundary : number;
				while((boundary = buffer.indexOf('\n\n')) !== -1)
				{
					this.#handleSseMessage(streamRequest, buffer.slice(0, boundary));
					buffer = buffer.slice(boundary + 2);
				}
			}
		}
		catch(_e)
		{
			// aborted (ack never arrived, or #stop() was called) or a genuine network error
		}
		finally
		{
			this.#sseAbort = null;
		}

		if(!ackReceived)
		{
			// nothing arrived at all within the timeout - this path doesn't survive end-to-end
			this.#sseUnsupported = true;
			if(this.#running) this.#poll();
			return;
		}

		// the stream ended (the server's own bounded AJAX_POLL_SSE_MAX_WAIT_SECONDS, or a
		// dropped connection) after having worked at least once - reconnect directly, no need to
		// re-probe the ack timeout on a path that already proved itself. notePushStopped(), not
		// notePushDisconnected(): the latter starts websocket's blip-tolerance grace period,
		// which this doesn't need (this method already manages its own reconnect immediately
		// below) and which would leave pushAvailable() reporting true for
		// PUSH_UNAVAILABLE_GRACE_MS after a connection that, unlike a dropped websocket, isn't
		// going to silently reconnect on its own within that window - #start()'s own #trySse()
		// call handles the actual reconnect.
		this.#sseActive = false;
		egw.notePushStopped();
		if(this.#running) this.#trySse();
	}

	/**
	 * Parses one "event: ...\ndata: ..." SSE message (already split on the blank-line boundary
	 * by the caller) and, if it carries a real payload (the initial "event: ack" one doesn't),
	 * dispatches it through the given request's handleResponse() - the exact same response-
	 * handling path a normal ajax response goes through.
	 */
	#handleSseMessage(_request : any, _raw : string)
	{
		let data : string = null;
		for(const line of _raw.split('\n'))
		{
			if(line.startsWith('data:'))
			{
				data = line.slice(5).trim();
			}
		}
		if(!data) return;

		let parsed : any;
		try
		{
			parsed = JSON.parse(data);
		}
		catch(_e)
		{
			console.error('egw_push_fallback.ts: failed to parse SSE message', _e, _raw);
			return;
		}
		if(parsed && parsed.response)
		{
			_request.handleResponse(parsed);
		}
	}
}

new PushFallback();
