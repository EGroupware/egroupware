/**
 * Suppress the benign "ResizeObserver loop completed with undelivered notifications" error.
 *
 * A connected `et2-datagrid` runs @lit-labs/virtualizer, whose measure-then-resize cycle trips this
 * browser-level error intermittently.  It is noise - nothing is broken by it - but web-test-runner
 * treats an uncaught window error as a test failure, so a test that renders a real datagrid fails
 * at random without this.  (Observed here as roughly one run in three.)
 *
 * The same suppression is inline in `Et2Datagrid/test/Et2Datagrid.test.ts`; this is the same thing
 * factored out for the history-log tests, which render a real datagrid for the same reason.
 *
 * The ResizeObserver stub only works because @lit-labs/virtualizer captured the real
 * `window.ResizeObserver` when its module was imported, long before this runs - so the virtualizer
 * instances under test are never actually measuring against the stub.  If that ever changes, a
 * virtualizer here would silently stop observing size changes.
 */

let errorHandler : ((event : ErrorEvent) => void) | null = null;
let rejectionHandler : ((event : PromiseRejectionEvent) => void) | null = null;
let originalResizeObserver : typeof window.ResizeObserver | undefined;
let originalWindowOnError : OnErrorEventHandler | null = null;

const NOISE = "ResizeObserver loop completed with undelivered notifications";

/** Call from a `before()` hook. */
export function silenceResizeObserverNoise()
{
	originalResizeObserver = window.ResizeObserver;
	originalWindowOnError = window.onerror;

	class ResizeObserverStub
	{
		observe() {}

		unobserve() {}

		disconnect() {}
	}

	window.ResizeObserver = ResizeObserverStub as any;

	errorHandler = (event : ErrorEvent) =>
	{
		if(String(event?.message || "").includes(NOISE))
		{
			event.preventDefault();
			event.stopImmediatePropagation?.();
		}
	};
	window.addEventListener("error", errorHandler, true);

	window.onerror = (message, source, lineno, colno, error) =>
	{
		if(String(message || error?.message || "").includes(NOISE))
		{
			return true;
		}
		if(typeof originalWindowOnError === "function")
		{
			return originalWindowOnError.call(window, message, source, lineno, colno, error);
		}
		return false;
	};

	rejectionHandler = (event : PromiseRejectionEvent) =>
	{
		if(String((event?.reason && (event.reason.message || event.reason)) || "").includes(NOISE))
		{
			event.preventDefault();
		}
	};
	window.addEventListener("unhandledrejection", rejectionHandler, true);
}

/** Call from an `after()` hook. */
export function restoreResizeObserverNoise()
{
	if(errorHandler)
	{
		window.removeEventListener("error", errorHandler, true);
		errorHandler = null;
	}
	if(rejectionHandler)
	{
		window.removeEventListener("unhandledrejection", rejectionHandler, true);
		rejectionHandler = null;
	}
	if(originalResizeObserver)
	{
		window.ResizeObserver = originalResizeObserver;
	}
	window.onerror = originalWindowOnError;
	originalWindowOnError = null;
}
