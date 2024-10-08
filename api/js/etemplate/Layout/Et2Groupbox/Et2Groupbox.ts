/**
 * EGroupware eTemplate2 - Details WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */

import {Et2Details} from "../Et2Details/Et2Details";
import {css, PropertyValues} from "lit";
import shoelace from "../../Styles/shoelace";
import {property} from "lit/decorators/property.js";
import {customElement} from "lit/decorators/custom-element.js";

/**
 * Groupbox shows content in a box with a summary
 */
@customElement("et2-groupbox")
export class Et2Groupbox extends Et2Details
{
	/**
	 * Where to show the summary: false (default) summary is shown on top border, true: summary is shown inside
	 */
	@property({type: Boolean})
	summaryInside = false;

	static get styles()
	{
		return [
			...super.styles,
			shoelace,
			css`
				:host {
					height: auto;
				}

				slot[name="collapse-icon"], slot[name="expand-icon"] {
                    display: none;
                }

                details {
					height: 100%;
                    position: relative;
                    margin: 2px;
                }

				/* work around details not respecting flex
				height is 100% - header - border radius
				 */

				.details__body {
					height: calc(100% - 2em - var(--sl-border-radius-medium)) !important;
					position: relative;
				}

				.summaryOnTop .details__body {
					height: 100% !important;
					position: relative;
				}

				.details__content {
					overflow: hidden;
					overflow-y: auto;
					height: calc(100% - 2 * var(--sl-spacing-medium));
				}

				/* end workaround */

				summary {
                    pointer-events: none;
                }

                /*.details {
					border-color: var(--sl-color-neutral-500);
					border-width: 2px;
				}*/

                details.summaryOnTop > summary {
                    position: absolute;
                    pointer-events: none;
                    width: fit-content;
                    line-height: 0;
                    top: -.5rem;
                    left: .5rem;
                    background: var(--sl-color-neutral-0);
                }

                details.summaryOnTop {
                    padding-top: .5rem;
                    margin-top: .5rem;
					height: auto;
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

	firstUpdated(changedProperties: PropertyValues)
	{
		super.firstUpdated(changedProperties);

		this.shadowRoot.querySelector('details').classList.toggle('summaryOnTop', !this.summaryInside);
	}

	/**
	 * A property has changed, and we want to make adjustments to other things
	 * based on that
	 *
	 * @param  changedProperties
	 */
	updated(changedProperties: PropertyValues)
	{
		super.updated(changedProperties);

		if (changedProperties.has('summaryInside'))
		{
			this.shadowRoot.querySelector('details').classList.toggle('summaryOnTop', !this.summaryInside);
		}
	}
}