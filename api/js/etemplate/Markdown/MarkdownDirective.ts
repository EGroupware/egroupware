/**
 * EGroupware eTemplate2 - markdown rendering
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */

import {html} from "lit";
import {Directive, directive} from "lit/directive.js";
import {unsafeHTML} from "lit/directives/unsafe-html.js";
import MarkdownIt from "markdown-it";
import DOMPurify from "../Et2Image/dompurify-shim";

/**
 * Every tag the full markdown syntax can produce.
 *
 * "s" is what markdown-it emits for ~~strikethrough~~, not "del" - del/ins only come from
 * plugins we do not load.
 */
export const SUPPORTED_TAGS = ["p", "br", "hr", "h1", "h2", "h3", "h4", "h5", "h6", "em", "strong",
	"s", "code", "pre", "blockquote", "ul", "ol", "li", "a", "img",
	"table", "thead", "tbody", "tr", "th", "td"];

/**
 * Attributes we let through.  target and rel are added by the link_open rule below and are not in
 * DOMPurify's defaults, so they need ADD_ATTR rather than this list.
 *
 * class, style and start look risky but are not reachable from user text: markdown-it only emits
 * class="language-..." on a fenced block (and HTML-escapes the info string), style="text-align:..."
 * on a table cell, and a numeric start on an ordered list.  The tests assert that surface.
 */
const ALLOWED_ATTR = ["href", "src", "alt", "title", "class", "style", "start"];

/**
 * Modelled on the mail body sanitizer in mail/js/jmap.ts - the "[^a-z]" alternation is what keeps
 * relative and fragment URLs ("/index.php?...", "#top") alive.
 *
 * data: is absent here, which stops it being used as an href.  DOMPurify still allows it for an
 * img src (its DATA_URI_TAGS bypass this regex), and that is fine: markdown-it's own validateLink
 * only lets data:image/(gif|png|jpeg|webp) through, so an inline image can only ever be raster -
 * never SVG, which is the format that could carry script.
 */
const ALLOWED_URI = /^(?:(?:https?|mailto|tel):|[^a-z]|[a-z+.\-]+(?:[^a-z+.\-:]|$))/i;

let parser : MarkdownIt = null;

/**
 * The shared parser, built on first use.
 *
 * html:false is the primary defence - raw HTML in the source is escaped, never parsed - and
 * DOMPurify is defence in depth rather than the only line of defence.  Everything else is on, so
 * the full markdown syntax works: links, images, autolinks, reference links, tables, code, ...
 */
function getParser() : MarkdownIt
{
	if(!parser)
	{
		parser = new MarkdownIt({html: false, linkify: true, breaks: true, typographer: false});

		// Harden links at the token level rather than with a DOMPurify hook.  Hooks are global to
		// the module, so one installed here would also rewrite Et2Image's SVG and the mail bodies
		// sanitized through the same DOMPurify - and only from the first markdown render onwards,
		// making that output depend on load order.
		const defaultLinkOpen = parser.renderer.rules.link_open ??
			((tokens, idx, options, env, self) => self.renderToken(tokens, idx, options));

		parser.renderer.rules.link_open = (tokens, idx, options, env, self) =>
		{
			const href = tokens[idx].attrGet("href") ?? "";
			// Leave mailto: alone so it behaves like any other mail link
			if(!href.startsWith("mailto:"))
			{
				tokens[idx].attrSet("target", "_blank");
				tokens[idx].attrSet("rel", "noopener noreferrer");
			}
			return defaultLinkOpen(tokens, idx, options, env, self);
		};

		// Record which source line each block came from, so the preview can be clicked to put
		// the caret back in the right place.  Off unless env.sourceMap asks for it
		parser.core.ruler.push("et2_source_line", state =>
		{
			if(!state.env?.sourceMap)
			{
				return;
			}
			for(const token of state.tokens)
			{
				// nesting 1 is an opening tag, 0 a self-contained block such as a fence or hr
				if(token.map && token.nesting >= 0)
				{
					token.attrSet("data-source-line", String(token.map[0]));
				}
			}
		});
	}
	return parser;
}

/**
 * Options for markdownToHtml().
 */
export interface MarkdownRenderOptions
{
	/**
	 * Add data-source-line to every block, mapping it back to its line in the source.
	 * Only the editor needs this; the display path leaves it off.
	 */
	sourceMap?: boolean;
}

/**
 * Parse markdown and sanitize the result.  Safe to hand to unsafeHTML().
 *
 * data-source-line survives DOMPurify on its own: ALLOW_DATA_ATTR defaults to true, so data-*
 * is permitted alongside the explicit ALLOWED_ATTR list, and event handlers are still stripped.
 *
 * @param value markdown source
 * @param options see MarkdownRenderOptions
 * @returns sanitized HTML, or "" for an empty value
 */
export function markdownToHtml(value : string, options : MarkdownRenderOptions = {}) : string
{
	if(!value)
	{
		return "";
	}
	return DOMPurify.sanitize(getParser().render(value, {sourceMap: options.sourceMap === true}), {
		ALLOWED_TAGS: SUPPORTED_TAGS,
		ALLOWED_ATTR: ALLOWED_ATTR,
		ADD_ATTR: ["target", "rel"],
		ALLOWED_URI_REGEXP: ALLOWED_URI
	});
}

/**
 * Renders markdown in a template position.
 *
 * Most widgets should use Et2MarkdownMixin instead, which adds the `markdown` property and gets
 * caching via MarkdownController.  This directive is for callers that just want the transform.
 *
 * @example
 * render()
 * {
 *     return html`${markdown(this.value)}`;
 * }
 */
class MarkdownDirective extends Directive
{
	render(value : string)
	{
		return html`${unsafeHTML(markdownToHtml(value))}`;
	}
}

export const markdown = directive(MarkdownDirective);
