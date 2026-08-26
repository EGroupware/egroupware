/**
 * EGroupware eTemplate2 - markdown rendering mixin
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */

import {type CSSResultGroup, LitElement} from "lit";
import {property} from "lit/decorators/property.js";
import {dedupeMixin} from "@open-wc/dedupe-mixin";
import {MarkdownController} from "./MarkdownController";
import {markdownStyles} from "./Markdown.styles";

type Constructor<T = {}> = new (...args : any[]) => T;

/**
 * Adds opt-in markdown rendering to a widget.
 *
 * Supplies the two things MarkdownController structurally cannot - the reactive property and the
 * stylesheet - and delegates rendering to it.  Apply the mixin, then call _markdownTemplate() from
 * the component's own template wherever it would otherwise interpolate the value as plain text.
 *
 * @example
 * export class Et2Example extends Et2MarkdownMixin(Et2Widget(LitElement))
 * {
 *     render()
 *     {
 *         return html`${this._markdownTemplate(this.value)}`;
 *     }
 * }
 */
export const Et2MarkdownMixin = dedupeMixin(<T extends Constructor<LitElement>>(superclass : T) =>
{
	class Et2Markdown extends superclass
	{
		static get styles() : CSSResultGroup
		{
			// super.styles may be absent or a single CSSResult - same guard Et2WithSearchMixin uses
			return [
				// @ts-ignore superclass is only typed as Constructor<LitElement>, which has no styles
				...(super.styles ? (Symbol.iterator in Object(super.styles) ? super.styles : [super.styles]) : []),
				markdownStyles
			];
		}

		/**
		 * Parse the value as markdown and render it as styled HTML instead of plain text.
		 *
		 * If you enable this on a widget whose value is a translated UI phrase: egw().lang() runs
		 * before parsing, so markdown syntax needs to be included in the translated text
		 */
		@property({type: Boolean, reflect: true})
		markdown = false;

		protected _markdownController = new MarkdownController(this);

		/**
		 * Render `value` as markdown when enabled, plain text otherwise.
		 * Call from the component's own template.
		 *
		 * @param value markdown source
		 */
		protected _markdownTemplate(value : string)
		{
			return this._markdownController.render(value);
		}
	}

	return Et2Markdown;
});
