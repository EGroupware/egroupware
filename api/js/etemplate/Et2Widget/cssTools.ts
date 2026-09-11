/**
 * Per-page-load cache of loaded stylesheets, keyed by URL.
 *
 * Holds the in-flight promise, not just the settled sheet, so concurrent callers
 * for the same URL share one request instead of racing.  Every et2-nextmatch loads
 * its app's row CSS, and row templates load their own <styles src=...>, so a page
 * with several lists would otherwise issue the same revalidation request once per
 * widget and keep a separate parsed copy of the same CSS alive for each.
 */
const stylesheetCache : Map<string, Promise<CSSStyleSheet | null>> = new Map();

/**
 * Load a CSS stylesheet from a URL into a CSSStyleSheet object
 *
 * You can use this function to load CSS into a widget's shadow DOM.
 * Use sparingly.  Prefer loading CSS through the framework and setting widget
 * styles directly or via import.
 *
 * The returned sheet is shared between all callers for the same URL - safe for
 * adoptedStyleSheets, which is designed around sheets adopted into many roots, but
 * it must not be mutated by a caller.
 *
 * @param {string} url
 * @return {Promise<CSSStyleSheet | null>}
 */
export function loadStylesheet(url : string) : Promise<CSSStyleSheet | null>
{
	const cached = stylesheetCache.get(url);
	if(cached)
	{
		return cached;
	}
	const pending = fetchStylesheet(url);
	stylesheetCache.set(url, pending);
	// A 404 (null) is a real, stable answer worth caching - eg. the templates/mobile/app.css
	// probe that legitimately misses - but a network failure is not, so let it be retried.
	// This derived promise handles its own rejection; `pending` is handled by the caller.
	pending.catch(() => stylesheetCache.delete(url));
	return pending;
}

/**
 * Drop cached stylesheets so the next load re-fetches.  Intended for tests and for
 * callers that know a stylesheet changed within this page's lifetime.
 *
 * @param {string} url Only this URL, or every cached URL when omitted.
 */
export function clearStylesheetCache(url? : string) : void
{
	if(typeof url === "string")
	{
		stylesheetCache.delete(url);
		return;
	}
	stylesheetCache.clear();
}

async function fetchStylesheet(url : string) : Promise<CSSStyleSheet | null>
{
	// "no-cache" (not "no-store") - still revalidates against the server's ETag/Last-Modified
	// (a fast 304 if unchanged), it just refuses to trust a cached response's max-age blindly.
	// These URLs have no cache-busting version query param (unlike most other template assets, which get one from PHP's CssIncludes::tags()),
	// so a long-lived Cache-Control (seen in practice: "max-age=864000, public") would otherwise serve a stale copy for days after the underlying file changes.
	// The module-level cache above is not in tension with that: it only spans one page
	// load, so a reload still revalidates.
	const response = await fetch(url, {credentials: "same-origin", cache: "no-cache"});

	if(response.status === 404)
	{
		return null;
	}
	if(!response.ok)
	{
		console.warn(`Failed to load CSS ${url}: ${response.status}`);
		return null;
	}

	const sheet = new CSSStyleSheet();
	await sheet.replace(await response.text());
	return sheet;
}
