/**
 * EGroupware eTemplate2 - Details WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */

import {Et2Widget} from "../../Et2Widget/Et2Widget";
import {css} from "@lion/core";
import {SlDetails} from "@shoelace-style/shoelace";
import shoelace from "../../Styles/shoelace";

export class Et2Details extends Et2Widget(SlDetails)
{
	static get styles()
	{
		return [
			...super.styles,
			shoelace,
			css`
            :host {
				display: block;
            }
            
			:host([align="right"]) > div {
				justify-content: flex-end;
			}
			:host([align="left"]) > div {
				justify-content: flex-start;
			}
			/* CSS for child elements */
            ::slotted(*) {
            	flex: 1 1 auto;
            }
            ::slotted(img),::slotted(et2-image) {
            	/* Stop images from growing.  In general we want them to stay */
            	flex-grow: 0;
            }
            ::slotted([align="left"]) {
            	margin-right: auto;
            	order: -1;
            }
            ::slotted([align="right"]) {
            	margin-left: auto;
            	order: 1;
            }

              .details.hoist {
                position: relative;
              }

              .details.hoist .details__body {
                position: absolute;
                z-index: var(--sl-z-index-drawer);
                background: var(--sl-color-neutral-0);
                box-shadow: var(--sl-shadow-large);
                width: 100%;
                min-width: fit-content;
                border-radius: var(--sl-border-radius-small);
                border: 1px solid var(--sl-color-neutral-200);
                max-height: 15em;
                overflow-y: auto;
              }
              .details.hoist .details__body.overlaySummaryLeftAligned {
				top: 0;
				left: 2em;
				width: calc(100% - 2em);
			  }
              .details.hoist .details__body.overlaySummaryRightAligned {
                top: 0;
              }
			`,
		];
	}

	static get properties()
	{
		return {
			...super.properties,

			/**
			 * Toggle when hover over
			 */
			toggleOnHover: {
				type: Boolean
			},

			/**
			 * Makes details content fixed position to break out of the container
			 */
			hoist: {
				type: Boolean
			},

			/**
			 * set toggle alignment either to left or right. Default is right alignment.
			 */
			toggleAlign: {
				type: String
			},

			/**
			 * Overlay summary container with the details container when in open state
			 */
			overlaySummaryOnOpen: {
				type: Boolean
			}
		}
	}

	/**
	 * List of properties that get translated
	 * Done separately to not interfere with properties - if we re-define label property,
	 * labels go missing.
	 */
	static get translate()
	{
		return {
			...super.translate,
			summary: true
		}
	}


	constructor(...args : any[])
	{
		super();
		this.toggleOnHover = false;
		this.toggleAlign = 'right';
		this.hoist = false;
		this.overlaySummaryOnOpen = false;
	}

	connectedCallback()
	{
		super.connectedCallback();

		this.updateComplete.then(() => {
			if (this.toggleOnHover) {
				this.addEventListener("mouseover", this.show);
				window.document.addEventListener('mouseout', this._mouseOutEvent.bind(this));
			}
			if (this.hoist)
			{
				this.shadowRoot.querySelector('.details').classList.add('hoist');
			}
			if (this.toggleAlign === 'left')
			{
				this.shadowRoot.querySelector('.details__summary-icon').style.order = -1;
			}
			if (this.overlaySummaryOnOpen)
			{
				this.shadowRoot.querySelector('.details__body').classList.add(this.toggleAlign === 'left' ?
					'overlaySummaryLeftAligend' : 'overlaySummaryRightAligned');
			}
		});
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		window.document.removeEventListener('mouseout', this._mouseOutEvent);
	}

	/**
	 * Handle mouse out event for hiding out details
	 * @param event
	 */
	_mouseOutEvent(event)
	{
		if (!this.getDOMNode().contains(event.relatedTarget)) this.hide();
	}
}
customElements.define("et2-details", Et2Details);
