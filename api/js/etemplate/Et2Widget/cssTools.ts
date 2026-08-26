/**
 * Load a CSS stylesheet from a URL into a CSSStyleSheet object
 *
 * You can use this function to load CSS into a widget's shadow DOM.
 * Use sparingly.  Prefer loading CSS through the framework and setting widget
 * styles directly or via import.
 *
 * @param {string} url
 * @return {Promise<CSSStyleSheet | null>}
 */
export async function loadStylesheet(url : string) : Promise<CSSStyleSheet | null>
{
	// "no-cache" (not "no-store") - still revalidates against the server's ETag/Last-Modified
	// (a fast 304 if unchanged), it just refuses to trust a cached response's max-age blindly.
	// These URLs have no cache-busting version query param (unlike most other template assets, which get one from PHP's CssIncludes::tags()),
	// so a long-lived Cache-Control (seen in practice: "max-age=864000, public") would otherwise serve a stale copy for days after the underlying file changes.
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