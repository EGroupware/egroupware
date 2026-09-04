/**
 * EGroupware eTemplate2 - Hidden input widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 */

import {css, html, LitElement} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";

/**
 * A genuine <input type="hidden"> - no label, no help text, no form-control chrome.
 *
 * Unlike <et2-textbox type="hidden">, which still builds (and merely hides via CSS)
 * a full Shoelace sl-input, this never renders anything but the hidden input itself.
 */
@customElement("et2-hidden")
export class Et2Hidden extends Et2InputWidget(LitElement)
{
	static get styles()
	{
		return [
			...super.styles,
			css`
				:host {
					display: none;
				}
			`,
		];
	}

	@property()
	value = "";

	/**
	 * A hidden input carries no user-editable state, so there is nothing for
	 * `readonly` to protect: it only ever arrives here inherited from a template
	 * whose readonlys use `__ALL__`. Et2InputWidget would then answer null, which
	 * silently loses server-provided values (eg. the ajax url an app-box tab
	 * loader reads from its "<tab>_iframe_load" widget), so hand out the value
	 * regardless. `disabled` still hides it, as for any input.
	 */
	getValue(submit_value? : boolean)
	{
		return this.disabled ? null : this.value;
	}

	render()
	{
		return html`
			<input type="hidden" .value=${this.value ?? ""}>`;
	}
}
