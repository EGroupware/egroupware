/**
 * EGroupware eTemplate2 - Category Tag WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */
import {css, html, TemplateResult} from "@lion/core";
import shoelace from "../../Styles/shoelace";
import {Et2Tag} from "./Et2Tag";

/**
 * Tag is usually used in a Et2CategorySelect with multiple=true, but there's no reason it can't go anywhere
 */
export class Et2CategoryTag extends Et2Tag
{
	private value : string;

	static get styles()
	{
		return [
			super.styles,
			shoelace, css`
			.tag {
				gap: var(--sl-spacing-2x-small);
				/* --category-color is passed through in _styleTemplate() */
				border-left: 6px solid var(--category-color, transparent);
			}
		`];
	}

	constructor(...args : [])
	{
		super(...args);
	}

	/**
	 * Due to how the scoping / encapulation works, we need to re-assign the category color
	 * variable here so it can be passed through.  .cat_# {--category-color} is not visible.
	 *
	 * @returns {TemplateResult}
	 * @protected
	 */
	protected _styleTemplate() : TemplateResult
	{
		let cat_var = "var(--cat-" + this.value + "-color)"
		// @formatter:off
		return html`<style>.tag { --category-color: ${cat_var}}</style>`;
		//@formatter:on
	}
}

customElements.define("et2-category-tag", Et2CategoryTag);