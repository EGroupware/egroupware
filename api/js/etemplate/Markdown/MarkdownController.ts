/**
 * EGroupware eTemplate2 - markdown rendering controller
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */

import {html, nothing, type ReactiveController, type ReactiveControllerHost} from "lit";
import {unsafeHTML} from "lit/directives/unsafe-html.js";
import {markdownToHtml} from "./MarkdownDirective";

/**
 * Host requirements: a LitElement with a `markdown` flag.
 */
type MarkdownHost = ReactiveControllerHost & Element & {markdown? : boolean};

/**
 * Renders a widget's value as markdown.
 *
 * A controller cannot declare a reactive property or contribute static styles, so the host owns
 * the `markdown` property and the stylesheet - Et2MarkdownMixin does both.  This controller owns
 * the rendering and caches the parsed result, so a recycled nextmatch cell doesn't re-parse on
 * every update.
 *
 */
export class MarkdownController implements ReactiveController
{
	host : MarkdownHost;

	private _cachedSource : string = null;
	private _cachedHtml : string = null;

	constructor(host : MarkdownHost)
	{
		(this.host = host).addController(this);
	}

	hostDisconnected()
	{
		// Don't hold a parsed copy of a detached row's content
		this._cachedSource = this._cachedHtml = null;
	}

	/**
	 * Parsed HTML for `value`, or null when markdown is off.
	 * Exposed for hosts that need the string rather than a template.
	 *
	 * @param value markdown source
	 */
	toHtml(value : string) : string
	{
		if(!this.host.markdown || !value)
		{
			return null;
		}
		if(value !== this._cachedSource)
		{
			this._cachedSource = value;
			this._cachedHtml = markdownToHtml(value);
		}
		return this._cachedHtml;
	}

	/**
	 * Render `value` as markdown when the host has it enabled, plain text otherwise.
	 *
	 * @param value markdown source
	 */
	render(value : string)
	{
		if(!value)
		{
			return nothing;
		}
		const rendered = this.toHtml(value);
		if(rendered === null)
		{
			return html`${value}`;
		}
		return html`<div class="et2_markdown" part="markdown">${unsafeHTML(rendered)}</div>`;
	}
}
