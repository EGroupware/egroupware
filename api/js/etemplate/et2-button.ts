/**
 * EGroupware eTemplate2 - Button widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import BXButton from "../../../node_modules/carbon-web-components/es/components/button/button"
import {css} from "../../../node_modules/lit-element/lit-element.js";
import {Et2InputWidget} from "./et2_core_inputWidget";
import {Et2Widget} from "./et2_core_inheritance";

export class Et2Button extends Et2InputWidget(Et2Widget(BXButton))
{
    static get properties() {
        return {
            image: {type: String}
        }
    }
    static get styles()
    {
        debugger;
        return [
            super.styles,
            css`
            /* Custom CSS */
            `
        ];
    }
    constructor()
    {
        super();
        this.image = '';
    }

    connectedCallback() {
        super.connectedCallback();

        this.classList.add("et2_button")
        debugger;
        if(this.image)
        {
            let icon = document.createElement("img");
            icon.src = egw.image(this.image);
            icon.slot="icon";
            this.appendChild(icon);
        }
    }
}
customElements.define("et2-button",Et2Button);
