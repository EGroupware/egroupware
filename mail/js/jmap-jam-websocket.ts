/**
 * jmap-jam extension - JMAP over WebSocket (RFC 8887) transport
 *
 * JamWebSocketClient extends jmap-jam's JamClient with a persistent WebSocket transport,
 * transparently used instead of plain HTTP requests whenever the server advertises support
 * (the session's "urn:ietf:params:jmap:websocket" capability) - falls back to jmap-jam's normal
 * fetch()-based HTTP transport otherwise, or while (re)connecting. node_modules/jmap-jam itself is
 * untouched by this file, so it could plausibly be upstreamed later.
 *
 * jmap-jam's request()/requestMany() call the global fetch() directly with no injectable transport
 * seam, and the small amount of glue logic they run before that (capability inference,
 * per-invocation error handling, and for requestMany() the Proxy-based draft/result-reference
 * builder) lives in modules jmap-jam doesn't re-export from its public entry point - so both methods
 * are fully overridden below, reimplementing that glue rather than reusing jmap-jam's own
 * (inaccessible) copies. See doc/ai/projects/mail-jmap-jam-websocket.md for the design rationale.
 *
 * onPush() additionally exposes RFC 8887's WebSocket-native push channel (WebSocketPushEnable/
 * WebSocketPushDisable/StateChange) - purely additive and forward-looking, unrelated to and not a
 * replacement for EGroupware's existing server-side JMAP push (mail_ui::ajax_enablePush(),
 * api/jmapPush.php, the Dovecot mailbox-metadata push-token mechanism), which this file doesn't
 * touch. There is no HTTP-transport equivalent of onPush() - jmap-jam's own connectEventSource() is
 * the unrelated, unaffected way to get push over HTTP.
 *
 * Not wired into MailJmap/mail-app yet - see that doc for the full design rationale and phasing.
 *
 * @link: https://www.egroupware.org
 * @author EGroupware GmbH [info@egroupware.org]
 * @copyright (c) 2026 by EGroupware GmbH <info-AT-egroupware.org>
 * @package mail
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

import JamClient from "jmap-jam";
import type {
	ClientConfig,
	GetArgs,
	GetResponseData,
	LocalInvocation,
	Meta,
	Methods,
	Requests,
	RequestOptions
} from "jmap-jam";

// jmap-jam re-exports its own public types from "jmap-jam" (see imports above), but not the two
// small RFC 8620 shapes below - and "jmap-rfc-types" (the package they actually come from) ships
// .ts sources with no compiled .d.ts, which this repo's tsconfig (moduleResolution: "node") can't
// resolve at all (confirmed: the same failure already exists for jmap-jam's own .d.ts, which
// references "jmap-rfc-types" internally - pre-existing, unrelated to this file). Declared locally
// instead of importing them.

/** [RFC 8620 §3.2] */
type Invocation<T = unknown> = [name : string, argsOrResponse : T, methodCallId : string];

/** [RFC 8620 §3.6.1] */
type ProblemDetails =
{
	type : string;
	status? : number;
	detail? : string;
	instance? : string;
	methodCallId? : string;
	limit? : string;
};

const WEBSOCKET_CAPABILITY = "urn:ietf:params:jmap:websocket";

// Capped reconnect backoff (ms) - after the last delay is used up without a successful reconnect,
// the transport gives up for good and stays on HTTP for the rest of this client's lifetime (a fresh
// successful connection resets the counter, so a later transient blip gets the full budget again).
const RECONNECT_DELAYS_MS = [1000, 5000, 30000];

type WebSocketRequestFrame =
{
	"@type" : "Request";
	id : string;
	using : string[];
	methodCalls : Invocation[];
	createdIds? : Record<string, string>;
};

type WebSocketResponseFrame =
{
	"@type" : "Response";
	requestId? : string;
	methodResponses : Invocation[];
	sessionState : string;
	createdIds? : Record<string, string>;
};

type WebSocketRequestErrorFrame = ProblemDetails &
{
	"@type" : "RequestError";
	requestId? : string;
};

type WebSocketPushEnableFrame =
{
	"@type" : "WebSocketPushEnable";
	dataTypes : string[] | null;
	pushState? : string;
};

type WebSocketPushDisableFrame =
{
	"@type" : "WebSocketPushDisable";
};

type WebSocketStateChangeFrame =
{
	"@type" : "StateChange";
	changed : Record<string, Record<string, string>>;
	pushState? : string;
};

/** Payload handed to onPush() callbacks - the RFC 8887 StateChange frame, minus its transport-only "@type". */
export type StateChange =
{
	changed : Record<string, Record<string, string>>;
	pushState? : string;
};

type PendingEntry =
{
	resolve : (frame : WebSocketResponseFrame) => void;
	reject : (error : ProblemDetails | Error) => void;
};

export type JamWebSocketClientConfig = ClientConfig &
{
	/**
	 * Override the wss:// URL used for the WebSocket transport - normally discovered from the
	 * session's "urn:ietf:params:jmap:websocket" capability. Mainly a seam for a future non-Stalwart
	 * (e.g. local IMAP shim) WebSocket endpoint - not otherwise used yet.
	 */
	webSocketUrl? : string;
	/**
	 * dataTypes sent with WebSocketPushEnable once at least one onPush() callback is registered -
	 * "*" (the default) for every JMAP data type, or an explicit list of type names (e.g.
	 * ["Email", "Mailbox"]) to narrow it. Has no effect if onPush() is never called.
	 */
	pushDataTypes? : "*" | string[];
	/**
	 * Optional transform applied to the wss:// URL (whether discovered from the session capability
	 * or given via webSocketUrl above) immediately before every connect/reconnect attempt.
	 * Deliberately generic - RFC 8887 assumes the WebSocket handshake is authenticated the same way
	 * as any other HTTP request (a header), but browsers cannot set custom headers on a WebSocket
	 * handshake, so a real deployment typically needs *some* out-of-band way to carry credentials
	 * (e.g. a token appended as a query parameter, converted back into a header by a reverse proxy).
	 * This file has no opinion on which convention a given server/proxy expects - that's entirely up
	 * to the caller.
	 */
	transformWebSocketUrl? : (url : string) => string;
};

/**
 * Reimplementation of jmap-jam's own (unexported) capabilities.ts::getCapabilitiesForMethodCalls() -
 * see this file's docblock for why it can't just be imported.
 */
function getCapabilitiesForMethodCalls(availableCapabilities : ReadonlyMap<string, string>, methodNames : Iterable<string>) : Set<string>
{
	const capabilities = new Set<string>(["urn:ietf:params:jmap:core"]);
	for (const method of methodNames)
	{
		const entity = /^(\w+)\//u.exec(method)?.[1];
		const capability = entity && availableCapabilities.get(entity);
		if (capability)
		{
			capabilities.add(capability);
		}
	}
	return capabilities;
}

/** Reimplementation of jmap-jam's own (unexported) helpers.ts::getErrorFromInvocation(). */
function getErrorFromInvocation(invocation : Invocation) : ProblemDetails | null
{
	return invocation[0] === "error" ? (invocation[1] as ProblemDetails) : null;
}

// --- requestMany() draft/result-reference support ------------------------------------------------
//
// Reimplementation of jmap-jam's own (unexported) request-drafts.ts - a caller passes a function
// building one InvocationDraft-equivalent ("Draft" here) per named method call via a Proxy-based
// {entity}.{operation}(args) DSL, optionally chaining .$ref(path) off an earlier call's Draft to
// produce a JMAP result reference (RFC 8620 §3.7) instead of a literal value. See this file's
// docblock for why this can't just be imported from jmap-jam.
//
// Typing note: jmap-jam's real DraftsProxy/InvocationDraft precisely infer each call's argument and
// response types (via helper types WithRefValues/WithoutRefValues, also unexported) - reproducing
// that here would mean rederiving a fair amount of type machinery for a mail-app integration that
// doesn't exist yet (Phase 4). DraftsProxy below keeps the useful part (entity/operation name
// autocomplete, via the public Requests type) but leaves arguments and results loosely typed rather
// than silently claiming a precision this file doesn't actually verify.

const REF = Symbol("JamWebSocketClient result reference");

type DraftInvocation = [method : string, args : Record<string, unknown>];

type RefValue = {[REF] : {path : string; invocation : DraftInvocation}};

function isRefValue(value : unknown) : value is RefValue
{
	return typeof value === "object" && value !== null && REF in (value as object);
}

class Draft
{
	#invocation : DraftInvocation;

	constructor(invocation : DraftInvocation)
	{
		this.#invocation = invocation;
	}

	get invocation() : DraftInvocation
	{
		return this.#invocation;
	}

	/** Reference this call's result as an argument to a later call in the same requestMany() batch. */
	$ref(path : string) : RefValue
	{
		return {[REF] : {path, invocation : this.#invocation}};
	}
}

/**
 * What a draftsFn callback sees for each {entity}.{operation}(args) call - deliberately just the
 * public $ref() surface (a plain structural type, not the Draft class itself), so this type doesn't
 * collide with jmap-jam's own same-shaped-but-differently-branded InvocationDraft class when
 * TypeScript checks that this override is compatible with JamClient.requestMany().
 */
export type DraftHandle = {$ref : (path : string) => unknown};

export type DraftsProxy = {
	[Entity in keyof Requests as Entity extends `${infer EntityName}/${string}` ? EntityName : never] : {
		[Method in Entity as Method extends `${string}/${infer MethodName}` ? MethodName : never] :
			(args : Record<string, unknown>) => DraftHandle;
	};
};

function createDraftsProxy() : DraftsProxy
{
	return new Proxy({} as DraftsProxy, {
		get : (_, entity : string) => new Proxy({}, {
			get : (__, operation : string) => (args : Record<string, unknown>) => new Draft([`${entity}/${operation}`, args])
		})
	});
}

/**
 * Reimplementation of jmap-jam's own (unexported) request-drafts.ts::buildRequestsFromDrafts()/
 * InvocationDraft.createInvocationsFromDrafts() - turns the caller's draftsFn into concrete
 * JMAP Invocations, resolving each $ref() into a RFC 8620 §3.7 ResultReference. A ref can only
 * resolve to a call declared *earlier* in the same drafts object - the same restriction jmap-jam's
 * own implementation has (and the only order JMAP result references are meaningful in).
 */
function buildRequestsFromDrafts(draftsFn : (proxy : DraftsProxy) => Record<string, DraftHandle>) : {methodCalls : Invocation[]; methodNames : Set<string>}
{
	// createDraftsProxy() only ever hands out real Draft instances - DraftHandle is a narrower public
	// view of the same objects, see its docblock above.
	const drafts = draftsFn(createDraftsProxy()) as Record<string, Draft>;

	const methodNames = new Set<string>();
	const invocationToId = new Map<DraftInvocation, string>();

	const methodCalls : Invocation[] = Object.entries(drafts).map(([id, draft]) =>
	{
		const [method, inputArgs] = draft.invocation;
		invocationToId.set(draft.invocation, id);
		methodNames.add(method);

		const args = Object.fromEntries(Object.entries(inputArgs).map(([key, value]) =>
		{
			if (isRefValue(value))
			{
				const {invocation, path} = value[REF];
				return [`#${key}`, {name : invocation[0], resultOf : invocationToId.get(invocation), path}];
			}
			return [key, value];
		}));

		return [method, args, id] as Invocation;
	});

	return {methodCalls, methodNames};
}

export class JamWebSocketClient<Config extends JamWebSocketClientConfig = JamWebSocketClientConfig> extends JamClient<Config>
{
	#webSocketUrl : string | undefined;
	#pushDataTypes : "*" | string[];
	#transformWebSocketUrl : ((url : string) => string) | undefined;
	#socket : WebSocket | null = null;
	#connecting : Promise<void> | null = null;
	#pending = new Map<string, PendingEntry>();
	#nextId = 1;
	#reconnectAttempt = 0;
	#reconnectTimer : number | undefined;
	#closedByUs = false;
	#pushCallbacks = new Set<(change : StateChange) => void>();
	#pushEnabled = false;
	#lastPushState : string | undefined;

	constructor(config : Config)
	{
		super(config);
		this.#webSocketUrl = config.webSocketUrl;
		this.#pushDataTypes = config.pushDataTypes ?? "*";
		this.#transformWebSocketUrl = config.transformWebSocketUrl;

		this.session.then((session) =>
		{
			const capabilities = session.capabilities as Record<string, unknown>;
			if (this.#webSocketUrl || capabilities?.[WEBSOCKET_CAPABILITY])
			{
				this.#connect();
			}
		});
	}

	/**
	 * "websocket" once a connection is open and usable, "http" otherwise (not yet connected,
	 * unsupported, or lost and not yet reconnected) - for observability/debugging only; request()
	 * itself checks readiness the same way before deciding which transport to use.
	 */
	get transport() : "websocket" | "http"
	{
		if (typeof WebSocket === "undefined" || !this.#socket)
		{
			return "http";
		}
		return this.#socket.readyState === WebSocket.OPEN ? "websocket" : "http";
	}

	/**
	 * Close the WebSocket transport for good (e.g. the client is being disposed of). Subsequent
	 * requests fall back to plain HTTP; does not affect the underlying HTTP session/token.
	 */
	close() : void
	{
		this.#closedByUs = true;
		window.clearTimeout(this.#reconnectTimer);
		this.#socket?.close();
	}

	/**
	 * Register a callback for JMAP push notifications (RFC 8887 StateChange frames), sent by the
	 * server after a WebSocketPushEnable this method sends automatically on first registration.
	 * Purely additive/forward-looking - see this file's docblock for how this relates (or rather,
	 * doesn't) to EGroupware's existing server-side JMAP push. Only ever fires while
	 * `transport === "websocket"` - there is no HTTP-transport equivalent, so a callback registered
	 * while stuck on HTTP (or before the WebSocket first connects) simply won't be called until/unless
	 * a connection is established. Returns an unsubscribe function; once the last callback
	 * unsubscribes, a WebSocketPushDisable is sent.
	 */
	onPush(callback : (change : StateChange) => void) : () => void
	{
		this.#pushCallbacks.add(callback);
		this.#sendPushEnableIfNeeded();

		return () =>
		{
			this.#pushCallbacks.delete(callback);
			if (this.#pushCallbacks.size === 0)
			{
				this.#sendPushDisable();
			}
		};
	}

	#sendPushEnableIfNeeded() : void
	{
		if (this.#pushEnabled || this.#pushCallbacks.size === 0 || this.transport !== "websocket")
		{
			return;
		}
		const frame : WebSocketPushEnableFrame =
		{
			"@type" : "WebSocketPushEnable",
			dataTypes : this.#pushDataTypes === "*" ? null : this.#pushDataTypes,
			pushState : this.#lastPushState
		};
		this.#socket!.send(JSON.stringify(frame));
		this.#pushEnabled = true;
	}

	#sendPushDisable() : void
	{
		if (!this.#pushEnabled)
		{
			return;
		}
		this.#pushEnabled = false;
		if (this.transport === "websocket")
		{
			const frame : WebSocketPushDisableFrame = {"@type" : "WebSocketPushDisable"};
			this.#socket!.send(JSON.stringify(frame));
		}
	}

	async #connect() : Promise<void>
	{
		if (this.#connecting)
		{
			return this.#connecting;
		}
		if (typeof WebSocket === "undefined")
		{
			return;
		}

		this.#connecting = this.#attemptConnect()
			.catch((e) =>
			{
				console.error("JamWebSocketClient: failed to establish the WebSocket transport, staying on HTTP for now", e);
			})
			.finally(() =>
			{
				this.#connecting = null;
			});

		return this.#connecting;
	}

	async #attemptConnect() : Promise<void>
	{
		const session = await this.session;
		const capability = (session.capabilities as Record<string, {url? : string}>)?.[WEBSOCKET_CAPABILITY];
		let url = this.#webSocketUrl ?? capability?.url;
		if (!url)
		{
			return;
		}
		if (this.#transformWebSocketUrl)
		{
			url = this.#transformWebSocketUrl(url);
		}

		await new Promise<void>((resolve, reject) =>
		{
			let socket : WebSocket;
			try
			{
				socket = new WebSocket(url, "jmap");
			}
			catch (e)
			{
				reject(e);
				return;
			}

			socket.addEventListener("open", () =>
			{
				this.#socket = socket;
				this.#reconnectAttempt = 0;
				// A fresh connection needs push re-enabling if there are still active onPush()
				// callbacks from before this (re)connect - includes #lastPushState, so the server can
				// immediately deliver anything that changed while disconnected.
				this.#sendPushEnableIfNeeded();
				resolve();
			}, {once : true});

			socket.addEventListener("message", (event : MessageEvent) => this.#handleMessage(event));
			socket.addEventListener("close", () => this.#handleClose());
			// "close" always follows "error" for a WebSocket, and #handleClose() does the actual
			// bookkeeping (including scheduling a reconnect) - this listener exists only so a failed
			// handshake rejects this promise instead of hanging until some unrelated timeout.
			socket.addEventListener("error", (event) => reject(event), {once : true});
		});
	}

	#handleMessage(event : MessageEvent) : void
	{
		let frame : {"@type" : string; requestId? : string};
		try
		{
			frame = JSON.parse(event.data);
		}
		catch (e)
		{
			console.error("JamWebSocketClient: received a non-JSON WebSocket frame", event.data, e);
			return;
		}

		if (frame["@type"] === "StateChange")
		{
			// Unsolicited (no requestId) - only ever arrives after a WebSocketPushEnable, see onPush().
			const stateChange = frame as unknown as WebSocketStateChangeFrame;
			this.#lastPushState = stateChange.pushState;
			this.#pushCallbacks.forEach((callback) => callback({changed : stateChange.changed, pushState : stateChange.pushState}));
			return;
		}

		const entry = frame.requestId ? this.#pending.get(frame.requestId) : undefined;
		if (!entry)
		{
			console.warn("JamWebSocketClient: received a WebSocket frame with no matching pending request", frame);
			return;
		}
		this.#pending.delete(frame.requestId!);

		if (frame["@type"] === "RequestError")
		{
			// Strip the transport-only fields so the rejected value matches the plain ProblemDetails
			// shape jmap-jam's HTTP path throws - callers shouldn't have to care which transport ran.
			const {"@type": _type, requestId: _requestId, ...problemDetails} = frame as WebSocketRequestErrorFrame;
			entry.reject(problemDetails);
		}
		else if (frame["@type"] === "Response")
		{
			entry.resolve(frame as unknown as WebSocketResponseFrame);
		}
		else
		{
			console.warn("JamWebSocketClient: received an unexpected WebSocket frame type", frame["@type"]);
		}
	}

	#handleClose() : void
	{
		this.#socket = null;
		// A new connection always starts unenabled - the "open" handler re-sends WebSocketPushEnable
		// (with #lastPushState) if there are still active onPush() callbacks once reconnected.
		this.#pushEnabled = false;

		// A lost response for a non-idempotent call (e.g. an Email/set create) can't be safely
		// assumed not to have reached the server - reject in-flight requests rather than silently
		// re-issuing them over HTTP. See doc/ai/projects/mail-jmap-jam-websocket.md, "Decisions".
		const pending = Array.from(this.#pending.values());
		this.#pending.clear();
		pending.forEach((entry) => entry.reject(
			new Error("JamWebSocketClient: WebSocket connection closed before a response was received")
		));

		if (this.#closedByUs)
		{
			return;
		}
		if (this.#reconnectAttempt >= RECONNECT_DELAYS_MS.length)
		{
			console.error("JamWebSocketClient: giving up on the WebSocket transport after repeated failures, staying on HTTP");
			return;
		}
		const delay = RECONNECT_DELAYS_MS[this.#reconnectAttempt++];
		this.#reconnectTimer = window.setTimeout(() => this.#connect(), delay);
	}

	/** Sends one WebSocketRequestFrame and resolves/rejects once its matching frame arrives - shared by request() and requestMany(). */
	#send(id : string, frame : WebSocketRequestFrame) : Promise<WebSocketResponseFrame>
	{
		return new Promise((resolve, reject) =>
		{
			this.#pending.set(id, {resolve, reject});
			this.#socket!.send(JSON.stringify(frame));
		});
	}

	/**
	 * Send a JMAP request containing a single method call - transparent drop-in replacement for
	 * JamClient.request(): uses the WebSocket transport when it's currently open, otherwise falls
	 * straight back to the inherited HTTP implementation. Never falls back mid-flight (a request
	 * already sent over the WebSocket is never silently retried over HTTP) - see #handleClose().
	 *
	 * Deliberately NOT declared `async` (just returns a Promise from one of two branches) - this
	 * repo's real Babel build (see rollup.config.js: preset-env + @babel/plugin-transform-class-
	 * properties) has two distinct miscompilation bugs for an *async* method combined with private
	 * class elements, found while testing this live:
	 *   1. An async method that both calls super.xxx() *and* references any #privateField/#privateMethod
	 *      of its own: the `_superprop_getRequest = () => super.request` binding needed for the
	 *      super call ends up nested inside the _asyncToGenerator() wrapper instead of the real
	 *      class-method scope, which is invalid (`super` can't cross a non-arrow function boundary) -
	 *      Rollup's own re-parse of the output then fails outright with "'super' keyword outside a
	 *      method".
	 *   2. An ordinary (non-#) async method that references a true #privateField captures `this`
	 *      *inside* the generator function instead of outside, and that generator is then invoked
	 *      bare (no receiver) - so `this` is `undefined` at runtime and every `this.#field` access
	 *      throws "Private element is not present on this object". This one doesn't fail the build -
	 *      it only fails live, in the browser, which is how it was actually found.
	 * Neither is a `tsc` or even a standalone babel.transform()-throws problem - both produce output
	 * that looks fine until something either re-parses it (bug 1) or runs it (bug 2). True
	 * `#`-private *methods* (see requestOverWebSocket() below) use a different, unaffected
	 * compilation pattern - confirmed via minimal repros - so the fix is: keep this dispatcher
	 * non-async and free of any # token, and do the actual work in a real `#`-private method.
	 */
	request<
		Method extends Methods,
		Args extends GetArgs<Method, Args>,
		Data extends GetResponseData<Method, Args>
	>(
		[method, args] : LocalInvocation<Method, Args>,
		options? : RequestOptions
	) : Promise<[Data, Meta]>
	{
		if (this.transport !== "websocket")
		{
			return super.request([method, args], options);
		}
		return this.#requestOverWebSocket(method, args, options);
	}

	/** The WebSocket-transport half of request() - see that method's docblock for why this is a true #private method, and why request() itself isn't async. */
	async #requestOverWebSocket(method : string, args : unknown, options? : RequestOptions) : Promise<[any, Meta]>
	{
		const {using = [], createdIds : createdIdsInput} = options ?? {};
		const id = `R${this.#nextId++}`;
		const frame : WebSocketRequestFrame =
		{
			"@type" : "Request",
			id,
			using : [...getCapabilitiesForMethodCalls(this.capabilities, [method]), ...using],
			methodCalls : [[method, args, "r1"]],
			createdIds : createdIdsInput
		};

		const response = await this.#send(id, frame);
		const [methodResponse] = response.methodResponses;
		const error = getErrorFromInvocation(methodResponse);
		if (error)
		{
			throw error;
		}

		return [
			methodResponse[1],
			{
				sessionState : response.sessionState,
				createdIds : response.createdIds,
				// No real Fetch Response exists for a WebSocket frame. Meta.response is typed as a
				// non-optional Response in jmap-jam; nothing in this codebase reads it today (checked
				// mail/js/jmap.ts), so this deviation is documented here rather than worked around.
				response : undefined as unknown as Response
			}
		];
	}

	/**
	 * Send a batch of method calls built via a draftsFn callback (the same {entity}.{operation}(args)
	 * / .$ref() DSL as JamClient.requestMany()) - transparent drop-in replacement, WebSocket when
	 * open, otherwise the inherited HTTP implementation. Mirrors JamClient.requestMany()'s
	 * all-or-nothing error handling exactly: if *any* method call in the batch comes back as an
	 * "error" pseudo-invocation, the whole call rejects with the array of ProblemDetails rather than
	 * returning partial results - callers written against the HTTP behavior don't need to change.
	 *
	 * Deliberately NOT declared `async` and free of any # token, same reason as request() above -
	 * see its docblock for why.
	 */
	// Return type is deliberately `Promise<any>`, not the precise `Promise<[{...}, Meta]>` tuple
	// jmap-jam's own requestMany() infers: that inference is built on DraftsProxy/InvocationDraft/Ref
	// types unexported from jmap-jam (see this file's docblock), and TypeScript's override-
	// compatibility check for a generic base method rejects any concrete replacement type here
	// (even one built from `any`) once the base's return type is itself a mapped type over an
	// unconstrained generic parameter. Callers still get normal `const [{x}] = await ...`
	// destructuring - only the compile-time shape check on the result is lost.
	requestMany(
		draftsFn : (proxy : DraftsProxy) => Record<string, DraftHandle>,
		options : RequestOptions = {}
	) : Promise<any>
	{
		if (this.transport !== "websocket")
		{
			// jmap-jam's own requestMany() calls draftsFn with its own InvocationDraft-based proxy,
			// unrelated to this file's - draftsFn itself doesn't care which Proxy calls it (both just
			// implement {entity}.{operation}(args) + $ref()), only the static DraftsProxy types
			// differ, hence the cast.
			return super.requestMany(draftsFn as any, options);
		}
		return this.#requestManyOverWebSocket(draftsFn, options);
	}

	/** The WebSocket-transport half of requestMany() - see request()'s docblock for why this is a true #private method, and why requestMany() itself isn't async. */
	async #requestManyOverWebSocket(
		draftsFn : (proxy : DraftsProxy) => Record<string, DraftHandle>,
		options : RequestOptions
	) : Promise<[Record<string, any>, Meta]>
	{
		const {using = [], createdIds : createdIdsInput} = options;
		const {methodCalls, methodNames} = buildRequestsFromDrafts(draftsFn);
		const id = `R${this.#nextId++}`;
		const frame : WebSocketRequestFrame =
		{
			"@type" : "Request",
			id,
			using : [...getCapabilitiesForMethodCalls(this.capabilities, methodNames), ...using],
			methodCalls,
			createdIds : createdIdsInput
		};

		const response = await this.#send(id, frame);

		const errors = response.methodResponses
			.map((invocation) => getErrorFromInvocation(invocation))
			.filter((error) : error is ProblemDetails => error !== null);
		if (errors.length > 0)
		{
			throw errors;
		}

		const result = Object.fromEntries(
			response.methodResponses.map(([, data, callId]) => [callId, data])
		);

		return [
			result,
			{
				sessionState : response.sessionState,
				createdIds : response.createdIds,
				response : undefined as unknown as Response
			}
		];
	}
}

export default JamWebSocketClient;
