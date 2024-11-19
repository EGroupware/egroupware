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

				.details {
					height: 100%;
                    position: relative;
					display: flex;
					flex-direction: column;
                }

				.details__body {
					flex: 1 1 auto;
					position: relative;
				}

				.summaryOnTop .details__body {
					height: 100% !important;
					position: relative;
				}

				.details__content {
					overflow: hidden;
					overflow-y: auto;
					height: 100%;
				}

				summary {
					flex: 0 0 auto;
                    pointer-events: none;
                }

				.details.summaryOnTop > summary {
                    position: absolute;
                    pointer-events: none;
                    width: fit-content;
                    line-height: 0;
                    top: -.5rem;
                    left: .5rem;
                    background: var(--sl-color-neutral-0);
                }

				.details.summaryOnTop {
                    padding-top: .5rem;
                    margin-top: .5rem;
					height: auto;
					overflow: visible;
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

		this.shadowRoot.querySelector('.details').classList.toggle('summaryOnTop', !this.summaryInside);
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
			this.shadowRoot.querySelector('.details').classList.toggle('summaryOnTop', !this.summaryInside);
		}
	}
}