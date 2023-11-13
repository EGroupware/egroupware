/**
 * EGroupware eTemplate2 - Duration date widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

import {css, html, LitElement} from "lit";
import {ButtonMixin} from "./ButtonMixin";

/**
 * Up / Down spinner buttons are used to adjust a value by a set amount
 *
 * @event et2-scroll Emitted when one of the buttons is clicked.  Check event.detail for direction.  1 for up, -1 for down.
 *
 * example:
 * Add the scroll into an input, then catch the et2-scroll event to adjust the value:
 * <et2-button-scroll slot="suffix" @et2-scroll=${this.handleScroll}></et2-button-scroll>
 *
 * handleScroll(e) {
 * 	this.value = "" + (this.valueAsNumber + e.detail * (parseFloat(this.step) || 1));
 * }
 */
export class Et2ButtonScroll extends ButtonMixin(LitElement)
{
	static get styles()
	{
		return [
			...(super.styles ? (Array.isArray(super.styles) ? super.styles : [super.styles]) : []),
			css`
			  /* Scroll buttons */

			  .et2-button-scroll {
				display: flex;
				flex-direction: column;
				width: calc(var(--sl-input-height-medium) / 2);
			  }

			  et2-button-icon {
				font-size: 85%;
				height: calc(var(--sl-input-height-medium) / 2);
				/* Override spacing in sl-icon-button */
				--sl-spacing-x-small: 3px;
			  }
			`,
		];
	}

	constructor()
	{
		super();
		this.handleClick = this.handleClick.bind(this);
	}

	/**
	 * Catch clicks on buttons and dispatch an et2-scroll event with the direction included
	 *
	 * @param e
	 * @private
	 */
	private handleClick(e)
	{
		const direction = parseInt(e.target.dataset.direction || "1") || 0;
		e.stopPropagation();

		this.dispatchEvent(new CustomEvent("et2-scroll", {bubbles: true, detail: direction}));
	}

	render()
	{
		// No spinner buttons on mobile
		if(typeof egwIsMobile == "function" && egwIsMobile())
		{
			return '';
		}

		return html`
            <div class="et2-button-scroll"
                 part="form-control"
                 exportparts="button:button"
                 slot="suffix"
                 @click=${this.handleClick}
            >
                <et2-button-icon
                        noSubmit
                        data-direction="1"
                        image="chevron-up"
                        part="button"
                >↑
                </et2-button-icon>
                <et2-button-icon
                        noSubmit
                        data-direction="-1"
                        image="chevron-down"
                        part="button"
                >↓
                </et2-button-icon>
            </div>`;
	}
}

if(typeof customElements.get("et2-button-scroll") == "undefined")
{
	customElements.define("et2-button-scroll", Et2ButtonScroll);
}