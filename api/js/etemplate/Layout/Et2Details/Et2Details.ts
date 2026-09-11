/**
 * EGroupware eTemplate2 - Details WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */

import {Et2Widget} from "../../Et2Widget/Et2Widget";
import {html} from "lit";
import {SlDetails} from "@shoelace-style/shoelace";
import shoelace from "../../Styles/shoelace";
import {property} from "lit/decorators/property.js";
import {customElement} from "lit/decorators/custom-element.js";
import {classMap} from "lit/directives/class-map.js";

import styles from "./Et2Details.styles";
/**
 * Details show a brief summary and expand to show additional content
 *
 * @slot - The details’ main content.
 * @slot summary - The details’ summary. Alternatively, you can use the summary attribute.
 * @slot expand-icon - Optional expand icon to use instead of the default. Works best with <sl-icon>.
 * @slot collapse-icon - Optional collapse icon to use instead of the default. Works best with <sl-icon>.
 *
 * @csspart base - Component wrapper
 * @csspart header - Header content
 * @csspart summary-icon - expand / collapse icon wrapper
 * @csspart content - The details' main content
 */
@customElement("et2-details")
export class Et2Details extends Et2Widget(SlDetails)
{
	static get styles()
	{
		return [
			...super.styles,
			shoelace,
			styles,
		];
	}

	/**
	 * Toggle when hover over
	 */
	@property({type: Boolean})
	toggleOnHover = false;

	/**
	 * Makes details content fixed position to break out of the container
	 */
	@property({type: Boolean})
	hoist = false;

	/**
	 * set toggle alignment either to left or right. Default is right alignment.
	 */
	@property({type: String})
	toggleAlign : "right" | "left" = "right";

	/**
	 * Overlay summary container with the details container when in open state
	 */
	@property({type: Boolean})
	overlaySummaryOnOpen = false;

	/**
	 * Group multiple details together so only one can be open at once
	 * @type {string}
	 */
	@property({type: String})
	accordionGroup : string;

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

	constructor()
	{
		super();
		this.handleAccordionOpen = this.handleAccordionOpen.bind(this);
		this._mouseOutEvent = this._mouseOutEvent.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();

		if(this.accordionGroup)
		{
			window.document.addEventListener("sl-show", this.handleAccordionOpen);
		}

		this.updateComplete.then(() => {
			if (this.toggleOnHover) {
				this.addEventListener("mouseover", this.show);
				window.document.addEventListener('mouseout', this._mouseOutEvent);
			}
		});
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();

		window.document.removeEventListener("sl-show", this.handleAccordionOpen);
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

	handleAccordionOpen(event)
	{
		if(event.target !== this && this.accordionGroup && event.target.accordionGroup == this.accordionGroup)
		{
			this.hide();
		}
	}

	render()
	{
		const isRtl = this.matches(':dir(rtl)');

		return html`
            <div
                    part="base"
                    class=${classMap({
                        details: true,
                        'details--open': this.open,
                        'details--disabled': this.disabled,
                        'details--rtl': isRtl,
                        'details--overlay-summary': this.overlaySummaryOnOpen,
                        'hoist': this.hoist
                    })}
            >
                <summary
                        part="header"
                        id="header"
                        class="details__header"
                        role="button"
                        aria-expanded=${this.open ? 'true' : 'false'}
                        aria-controls="content"
                        aria-disabled=${this.disabled ? 'true' : 'false'}
                        tabindex=${this.disabled ? '-1' : '0'}
                        @click=${this.handleSummaryClick}
                        @keydown=${this.handleSummaryKeyDown}
                >
                    <slot name="summary" part="summary" class="details__summary">${this.summary}</slot>

                    <span part="summary-icon" class=${classMap({
                        "details__summary-icon": true,
                        "details__summary-icon--left-aligned": this.toggleAlign == "left"
                    })}>
						<slot name="expand-icon">
							<sl-icon library="system" name=${isRtl ? 'chevron-left' : 'chevron-right'}></sl-icon>
						</slot>
						<slot name="collapse-icon">
							<sl-icon library="system" name=${isRtl ? 'chevron-left' : 'chevron-right'}></sl-icon>
						</slot>
					</span>
                </summary>
                <div class=${classMap({
                    details__body: true,
                    overlaySummaryLeftAligned: this.overlaySummaryOnOpen && this.toggleAlign === 'left',
                    overlaySummaryRightAligned: this.overlaySummaryOnOpen && this.toggleAlign !== 'left',
                })} role="region" aria-labelledby="header">
                    <slot part="content" id="content" class="details__content"></slot>
                </div>
            </div>
		`;
	}

}