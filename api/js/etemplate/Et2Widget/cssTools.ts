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
	const response = await fetch(url, {credentials: "same-origin"});

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