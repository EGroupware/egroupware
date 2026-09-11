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

	render()
	{
		return html`
			<input type="hidden" .value=${this.value ?? ""}>`;
	}
}
