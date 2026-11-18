/**
 * EGroupware eTemplate2 - Url r/o widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Ralf Becker
 */

/* eslint-disable import/no-extraneous-dependencies */
import {Et2Description} from "../Et2Description/Et2Description";
import {css, TemplateResult} from "lit";
import {Et2Url} from "./Et2Url";

/**
 * @customElement et2-url_ro
 */
export class Et2UrlReadonly extends Et2Description
{
	constructor()
	{
		super();
		/*
		 * Never render markdown: our value is a URL, email address or phone number, not prose.
		 *
		 * The user's markdown preference ("on") switches markdown on for every Et2Description,
		 * and markdown linkifies the value into a real <a href="mailto:...">.
		 * A click then ran our own onclick (compose in EGroupware) AND the anchor's default action,
		 * so the OS mailto handler opened as well.
		 */
		this.noMarkdown = true;
	}

	static get styles()
	{
		return [
			...super.styles,
			css`
			  
			a {
				cursor: pointer;
				color: var(--primary-background-color) !important;
			    text-decoration: none !important;
			}`
		];
	}

	// Don't try to translate URLs (or sub-classes)
	noLang = true;

	transformAttributes(attrs)
	{
		if (typeof attrs.onclick === 'undefined')
		{
			// Use the clicked widget, not the template widget that created this handler.
			attrs.onclick = function(event)
			{
				const widget = event?.currentTarget || this;
				if(widget.value)
				{
					Et2Url.action(widget.value);
				}
			}
		}
		super.transformAttributes(attrs);
	}

	/**
	 * Override parent render so we can have the special case where label is used as link text
	 *
	 * @returns {TemplateResult<1>}
	 * @protected
	 */
	render() : TemplateResult<1>
	{
		if(this.label && !this.href && this.value)
		{
			// We have label & value, use label as link text
			return this.wrapLink(this.value, this.label);
		}
		return super.render();
	}
}
// @ts-ignore TypeScript is not recognizing that this is a LitElement
customElements.define("et2-url_ro", Et2UrlReadonly);
