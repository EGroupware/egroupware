/**
 * EGroupware eTemplate2 - styles for rendered markdown
 *
 * markdown.less is the single source of truth, compiled to markdown.css.  That one file feeds
 * both consumers, so there is nothing to keep in sync:
 *  - shadow DOM (Et2Ai): this module, inlined into the bundle as a string at build time
 *  - light DOM (Et2Description): kdots/css/src/widgets.less @imports the .less directly
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

export default markdownStyles;
