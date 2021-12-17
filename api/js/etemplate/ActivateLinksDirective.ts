import {Directive, directive, html, repeat} from "@lion/core";
import {et2_activateLinks} from "./et2_core_common";

/**
 * Activates links in text
 *
 * @example
 * this.value = "This text has links to https://www.egroupware.org";
 * ...
 * render()
 * {
 *     return html`activateLinks(this.value)`;
 * }
 * renders as:
 * <a href="https://www.egroupware.org" target="_blank">egroupware.org</a>
 */
class ActivateLinksDirective extends Directive
{
	render(text_with_urls, _target)
	{
		let list = et2_activateLinks(text_with_urls);

		return html`${repeat(list, (item, index) =>
		{
			// No urls in this section
			if(typeof item == "string" || typeof item == "number")
			{
				// Just text.  Easy (framework handles \n)
				return item;
			}
			// Url found, deal with it
			else if(item && item.text)
			{
				if(!item.href)
				{
					console.warn("et2_activateLinks gave bad data", item, _target);
					item.href = "";
				}
				let click = null;
				let target = null;

				// open mailto links depending on preferences in mail app
				if(item.href.substr(0, 7) == "mailto:" &&
					egw.user('apps').mail &&
					egw.preference('force_mailto', 'addressbook') != '1')
				{
					click = function(event)
					{
						egw.open_link(this);
						return false;
					}.bind(item.href);
					item.href = "#";
				}

				if(typeof _target != "undefined" && _target && _target != "_self" && item.href.substr(0, 7) != "mailto:")
				{
					target = _target;
				}
				return html`<a href="${item.href}" @click=${click} target="${target}">${item.text}</a>`;
			}
		})}`;
	}

}

export const activateLinks = directive(ActivateLinksDirective);