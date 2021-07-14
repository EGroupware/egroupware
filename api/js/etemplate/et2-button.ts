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
    private _icon: HTMLImageElement;
    static get properties() {
        return {
            image: {type: String},
            onclick: {type: Function}
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

        // Property default values
        this.image = '';

        // Create icon since BXButton puts it as child, we put it as attribute
        this._icon = document.createElement("img");
        this._icon.slot="icon";
        // Do not add this._icon here, no children can be added in constructor

        this.onclick = () => {
            debugger;
            this.getInstanceManager().submit(this);
        };
    }

    connectedCallback() {
        super.connectedCallback();

        this.classList.add("et2_button")
        debugger;
        if(this.image)
        {
            this._icon.src = egw.image(this.image);
            this.appendChild(this._icon);
        }
    }
}
customElements.define("et2-button",Et2Button);
