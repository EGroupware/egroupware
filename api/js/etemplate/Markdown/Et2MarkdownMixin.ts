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
 * Turns markdown on or off for every field at once.  Unset - the shipped state - leaves each
 * field as its template has it.
 */
const MARKDOWN_PREFERENCE = "markdown";

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
			// super.styles may be absent or a single CSSResult - same guard SelectSearchMixin uses
			return [
				// @ts-ignore superclass is only typed as Constructor<LitElement>, which has no styles
				...(super.styles ? (Symbol.iterator in Object(super.styles) ? super.styles : [super.styles]) : []),
				markdownStyles
			];
		}

		/**
		 * Parse the value as markdown and render it as styled HTML instead of plain text.
		 *
		 * What the template asked for, unless the user's Markdown preference says otherwise -
		 * see connectedCallback() below.
		 *
		 * If you enable this on a widget whose value is a translated UI phrase: egw().lang() runs
		 * before parsing, so markdown syntax needs to be included in the translated text
		 */
		@property({type: Boolean, reflect: true})
		markdown = false;

		protected _markdownController = new MarkdownController(this);

		connectedCallback()
		{
			super.connectedCallback();

			// The preference has three states, and only two of them are an answer: "on" turns
			// markdown on everywhere, "off" turns it off everywhere - a field that asks for
			// markdown itself included - and anything else leaves whatever the template set.
			// Which is simply what is already here: Et2Widget's transformAttributes() runs before
			// the widget is connected.
			//
			// "anything else" rather than "unset" on purpose: choosing "use default" in the
			// preferences UI stores the literal string "default", so an untouched preference can
			// read back as "", null, undefined OR "default".  Only a real choice may decide.
			//
			// egw() is reached through a cast, NOT a `declare egw` field: this project's babel
			// transform compiles a declared field into a real own property holding undefined,
			// which shadows the inherited method - see the HasEgwAndValue docblock in
			// Et2MarkdownEditMixin for the app-wide breakage that caused.
			const egw = (<any>this).egw?.();
			// egw() can hand back a partial object with no preference() on it, and this runs for
			// every widget the mixin is on - an et2-description in any template included - so a
			// missing preference() has to leave the field alone, never throw out of connectedCallback
			const preference = typeof egw?.preference === "function" ?
							   egw.preference(MARKDOWN_PREFERENCE, "common") : null;

			if(preference === "on" || preference === "off")
			{
				this.markdown = preference === "on";
			}
		}

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
