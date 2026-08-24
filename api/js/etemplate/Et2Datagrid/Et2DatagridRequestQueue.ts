import {Et2Dialog} from "../Et2Dialog/Et2Dialog";
import type {Et2DatagridDataProvider, Et2DatagridPageResult} from "./Et2Datagrid.types";

type Et2DatagridFetchPromise = Promise<Et2DatagridPageResult> & { abort? : () => void };

export interface Et2DatagridRequestQueueHost extends HTMLElement
{
	dataProvider? : Et2DatagridDataProvider | null;
	egw() : any;
	requestUpdate() : void;
	_requestDispatchDelayMs : number;
	_isEmbeddedInitialLoading() : boolean;
}

/**
 * Owns *whether/when* Et2Datagrid dispatches a chunk fetch: debounced FIFO
 * queueing of chunk requests, placeholder-row bookkeeping while they're
 * pending, in-flight tracking, and the "still waiting?" slow-fetch dialog.
 *
 * Deliberately does not own fetching itself or row storage - the host
 * (`_fetchPage()`/`_processQueuedRequests()`) owns *what happens* with a
 * fetch's result (`this.rows`, `this._rowsByIndex`, `this.total`, etc).
 */
export class Et2DatagridRequestQueue
{
	private host : Et2DatagridRequestQueueHost;

	private _pendingPlaceholderCount : number = 0;
	private _pendingPlaceholderRequests : Map<string, { start : number; requestedCount : number }> = new Map();
	private _inFlightRequestKeys : Set<string> = new Set();
	private _queuedRequestTimer : number | null = null;
	private _queuedRequests : Map<string, { start : number; requestedCount : number; requestKey : string }> = new Map();

	private _slowFetchDialog : Et2Dialog | null = null;
	private _slowFetchTimers : Set<number> = new Set();
	private _inFlightFetchPromises : Set<Et2DatagridFetchPromise> = new Set();

	private static readonly SLOW_FETCH_TIMEOUT_MS = 30000;

	constructor(host : Et2DatagridRequestQueueHost)
	{
		this.host = host;
	}

	get pendingPlaceholderCount() : number
	{
		return this._pendingPlaceholderCount;
	}

	/** Highest row index (exclusive) reserved by any still-pending placeholder request. */
	get pendingPlaceholderExtent() : number
	{
		return Math.max(
			0,
			...Array.from(this._pendingPlaceholderRequests.values()).map((request) => request.start + request.requestedCount)
		);
	}

	get inFlightCount() : number
	{
		return this._inFlightRequestKeys.size;
	}

	get queuedCount() : number
	{
		return this._queuedRequests.size;
	}

	get pendingPlaceholderRequestCount() : number
	{
		return this._pendingPlaceholderRequests.size;
	}

	/** Whether a chunk request is already queued or in flight (excludes host-owned "completed" tracking). */
	isPendingOrQueued(requestKey : string) : boolean
	{
		return this._inFlightRequestKeys.has(requestKey) || this._queuedRequests.has(requestKey);
	}

	/**
	 * Build a deterministic key for one fetch request using range + provider query signature.
	 */
	requestKey(start : number, requestedCount : number) : string
	{
		const querySignature = this.host.dataProvider?.getQuerySignature?.() || "";
		return `${start}:${requestedCount}:${querySignature}`;
	}

	/**
	 * Queue a chunk request once and reserve placeholder capacity for its expected rows.
	 */
	queueRequest(start : number, requestedCount : number, requestKey : string) : void
	{
		if(this._queuedRequests.has(requestKey) || this._inFlightRequestKeys.has(requestKey))
		{
			return;
		}
		this._queuedRequests.set(requestKey, {start, requestedCount, requestKey});
		this._pendingPlaceholderRequests.set(requestKey, {start, requestedCount});
		this._pendingPlaceholderCount += this.host._isEmbeddedInitialLoading() ? Math.min(requestedCount, 1) : requestedCount;
		this.host.requestUpdate();
	}

	/**
	 * Debounce queued-request dispatch so fast scrolling can coalesce bursts. When the
	 * debounce fires, drains every currently-queued request in FIFO order, marks each
	 * in flight, then invokes `onDispatch` once per entry so the host can start the
	 * actual fetch.
	 */
	scheduleProcessing(onDispatch : (start : number, requestedCount : number, requestKey : string) => void) : void
	{
		if(this._queuedRequestTimer !== null)
		{
			window.clearTimeout(this._queuedRequestTimer);
		}
		this._queuedRequestTimer = window.setTimeout(() => this.flush(onDispatch), this.host._requestDispatchDelayMs);
	}

	/**
	 * Immediately drain every currently-queued request in FIFO order, marking each in
	 * flight before invoking `onDispatch` once per entry. Used both by the debounce
	 * timer armed in scheduleProcessing() and directly where the host needs to force
	 * dispatch without waiting for the debounce.
	 */
	flush(onDispatch : (start : number, requestedCount : number, requestKey : string) => void) : void
	{
		this._queuedRequestTimer = null;
		if(!this._queuedRequests.size)
		{
			return;
		}
		const selected = Array.from(this._queuedRequests.values());
		for(const entry of selected)
		{
			this._queuedRequests.delete(entry.requestKey);
			this._inFlightRequestKeys.add(entry.requestKey);
			onDispatch(entry.start, entry.requestedCount, entry.requestKey);
		}
	}

	/** Mark a request key in flight directly, bypassing the normal queue/dispatch path. */
	markInFlight(requestKey : string) : void
	{
		this._inFlightRequestKeys.add(requestKey);
	}

	/**
	 * Starts this fetch's own 30s timer. If it fires and the fetch is still pending,
	 * show the shared dialog - but only if none is already showing (a second slow
	 * fetch's timer firing while one dialog is already up just no-ops; that fetch is
	 * still in _inFlightFetchPromises, so the existing dialog's "give up" covers it too).
	 */
	/** Whether a fetch promise is still tracked as in flight (false once settled or given up on). */
	isTrackedFetch(fetchPromise : Et2DatagridFetchPromise) : boolean
	{
		return this._inFlightFetchPromises.has(fetchPromise);
	}

	trackFetch(fetchPromise : Et2DatagridFetchPromise) : void
	{
		this._inFlightFetchPromises.add(fetchPromise);
		const timer = window.setTimeout(() =>
		{
			this._slowFetchTimers.delete(timer);
			if(!this._inFlightFetchPromises.has(fetchPromise) || this._slowFetchDialog)
			{
				return;	// already settled/discarded, or another dialog is already asking
			}
			this._slowFetchDialog = this._showSlowFetchDialog(() => this._giveUpOnPendingFetches());
		}, Et2DatagridRequestQueue.SLOW_FETCH_TIMEOUT_MS);
		this._slowFetchTimers.add(timer);
	}

	/**
	 * Normal settle path (success or a real error) - a no-op if this fetch was already
	 * removed by giving up. If this was the last fetch pending and a dialog is still up
	 * (unanswered), its question is now moot - dismiss it.
	 */
	untrackFetch(fetchPromise : Et2DatagridFetchPromise) : void
	{
		this._inFlightFetchPromises.delete(fetchPromise);
		if(this._inFlightFetchPromises.size === 0 && this._slowFetchDialog)
		{
			this._slowFetchDialog.destroy();
			this._slowFetchDialog = null;
		}
	}

	/**
	 * "Give up": abort everything currently in flight and forget about it immediately -
	 * no separate bookkeeping of what was aborted, just remove it so it's plain
	 * untracked state, exactly as if it had never been requested. Any other fetch's
	 * still-pending timer will find it's no longer tracked when it fires and no-op.
	 */
	private _giveUpOnPendingFetches() : void
	{
		const pending = Array.from(this._inFlightFetchPromises);
		this._inFlightFetchPromises.clear();
		for(const fetchPromise of pending)
		{
			fetchPromise.abort?.();
		}
	}

	/**
	 * Show a "still waiting?" confirmation after a slow fetch. Yes/dismiss = keep
	 * waiting, No = give up and abort every fetch currently in flight for this grid.
	 *
	 * Answered via getComplete() rather than the constructor `callback` param: callback
	 * only fires on an actual button click, but _slowFetchDialog must be cleared on
	 * EVERY dismissal path (X, Escape, backdrop click too) or the single-dialog guard
	 * in trackFetch() would permanently block any future dialog for this grid the
	 * first time someone dismisses one without clicking Yes/No. getComplete() resolves
	 * on every close path, covering all of them.
	 */
	private _showSlowFetchDialog(onGiveUp : () => void) : Et2Dialog
	{
		const dialog = Et2Dialog.show_dialog(
			undefined,
			this.host.egw().lang("This request is taking longer than expected. Keep waiting?"),
			this.host.egw().lang("Still working"),
			{},
			Et2Dialog.BUTTONS_YES_NO,
			Et2Dialog.WARNING_MESSAGE,
			undefined,
			this.host.egw()
		);
		dialog.getComplete().then(([button_id] : [number, object]) =>
		{
			this._slowFetchDialog = null;
			if(button_id === Et2Dialog.NO_BUTTON)
			{
				onGiveUp();
			}
			// YES_BUTTON, or dismissed via X/escape/backdrop (button_id null): keep
			// waiting - the safe default, no-op.
		});
		return dialog;
	}

	/** Forget a request's in-flight/placeholder bookkeeping (settled, or never dispatched). */
	forgetRequest(requestKey : string) : void
	{
		this._inFlightRequestKeys.delete(requestKey);
		this._pendingPlaceholderRequests.delete(requestKey);
	}

	/** Release placeholder capacity reserved for a request that has now settled. */
	releasePlaceholder(requestedCount : number) : void
	{
		this._pendingPlaceholderCount = Math.max(0, this._pendingPlaceholderCount - requestedCount);
	}

	/** Forget in-flight keys without waiting for their fetches to settle (see Et2Datagrid.reload()). */
	clearInFlight() : void
	{
		this._inFlightRequestKeys.clear();
	}

	/**
	 * Drop queued (not yet dispatched) requests and clear any scheduled dispatch timer.
	 */
	clear() : void
	{
		this._queuedRequests.clear();
		this._pendingPlaceholderRequests.clear();
		this._pendingPlaceholderCount = 0;
		if(this._queuedRequestTimer !== null)
		{
			window.clearTimeout(this._queuedRequestTimer);
			this._queuedRequestTimer = null;
		}
	}

	/** Full teardown on host disconnect: stop timers and drop any open dialog/in-flight tracking. */
	dispose() : void
	{
		for(const timer of this._slowFetchTimers)
		{
			window.clearTimeout(timer);
		}
		this._slowFetchTimers.clear();
		this._slowFetchDialog?.destroy?.();
		this._slowFetchDialog = null;
		this._inFlightFetchPromises.clear();
	}
}
