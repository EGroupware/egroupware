/**
 * EGroupware eTemplate2 - Details WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */

import {Et2Details} from "../Et2Details/Et2Details";
import {css} from "lit";
import shoelace from "../../Styles/shoelace";
import {property} from "lit/decorators/property.js";
import {customElement} from "lit/decorators/custom-element.js";

/**
 * Groupbox shows content in a box with a summary
 */
@customElement("et2-groupbox")
export class Et2Groupbox extends Et2Details
{
	static get styles()
	{
		return [
			...super.styles,
			shoelace,
			css`
			slot[name="collapse-icon"], slot[name="expand-icon"] {
				display: none;
			}
			details {
				position: relative;
				padding-top: .5rem;
				margin: 2px;
				margin-top: .5rem;
			}
			summary {
                position: absolute;
                pointer-events: none;
				width: fit-content;
				line-height: 0;
				top: -.5rem;
				left: .5rem;
				background: var(--sl-color-neutral-0);
			}
			.details {
				border-color: var(--sl-color-neutral-500);
				border-width: 2px;
			}
			`,
		];
	}

	constructor()
	{
		super();
		// groupbox is always open
		this.open = true;
	}
}