/**
 * EGroupware eTemplate2 - styles for rendered markdown
 *
 * markdown.less is the single source of truth, compiled to markdown.css.  That one file feeds
 * every consumer, so there is nothing to keep in sync:
 *  - shadow DOM (Et2Ai, Et2HtmlAreaReadonly): markdownStyles below, inlined into the bundle as a
 *    string at build time and picked up by Et2MarkdownMixin's static styles
 *  - light DOM (Et2Description): addMarkdownStyles() below
 *
 * unsafeCSS is safe here: the input is a checked-in stylesheet, never user content.  A plain
 * `css` tag can't be used - lit adopts shadow styles via CSSStyleSheet.replaceSync(), which
 * silently DROPS @import rules, so `css\`@import "./markdown.css"\`` yields an empty sheet.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */

import {unsafeCSS} from "lit";
import markdownCss from "./markdown.css";

export const markdownStyles = unsafeCSS(markdownCss);

/**
 * Id of the <style> carrying markdownStyles, so each document or shadow root gets it only once
 */
export const MARKDOWN_STYLES_ID = "et2-markdown-styles";

/**
 * Put the markdown styles into the given document or shadow root, once.
 *
 * A widget rendering markdown into its own shadow DOM is already covered by Et2MarkdownMixin's
 * static styles.  One rendering into its LIGHT DOM is not: the markup is then styled by the tree
 * the widget sits in, and that tree is not always the document.  Et2Datagrid builds its rows
 * inside its own shadow root, which no document stylesheet can reach - so an et2-description in
 * a nextmatch row got no markdown styling at all and fell back to the browser's defaults.
 *
 * A <style> element, not adoptedStyleSheets, for the same reason as
 * Et2Description.addLinkStyles(): Et2Datagrid replaces its shadow root's adoptedStyleSheets
 * wholesale, which would drop a sheet added here.
 */
export function addMarkdownStyles(root : Node)
{
	const target = root instanceof ShadowRoot ? root : (root instanceof Document ? root.head : null);
	if(!target || (root as Document | ShadowRoot).getElementById(MARKDOWN_STYLES_ID))
	{
		return;
	}
	const style = document.createElement("style");
	style.id = MARKDOWN_STYLES_ID;
	style.textContent = markdownStyles.cssText;
	// First, so app CSS loaded before us still wins over equal specificity
	target.prepend(style);
}

export default markdownStyles;
