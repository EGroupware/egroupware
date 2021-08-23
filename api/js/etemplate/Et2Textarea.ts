/**
 * EGroupware eTemplate2 - Textbox widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css, html} from "../../../node_modules/@lion/core/index.js"
import {LionTextarea} from "../../../node_modules/@lion/textarea/index.js"
import {Et2InputWidget} from "./et2_core_inputWidget";
import {Et2Widget} from "./Et2Widget";


export class Et2Textarea extends Et2InputWidget(Et2Widget(LionTextarea))
{
	static get styles()
	{
		return [
			...super.styles,
			css`
			:host {
				display: flex;
				flex-direction: column;
				width: 100%;
				height: 100%;
            }
			`,
		];
	}

	static get properties()
	{
		return {
			...super.properties,
			/**
			 * Specify the width of the text area.
			 * If not set, it will expand to fill the space available.
			 */
			width: {type: String, reflect: true},
			/**
			 * Specify the height of the text area.
			 * If not set, it will expand to fill the space available.
			 */
			height: {type: String, reflect: true},
			onkeypress: Function,
		}
	}

	constructor()
	{
		super();
	}

	connectedCallback()
	{
		super.connectedCallback();
	}

	set width(value)
	{
		if(this._inputNode)
		{
			this._inputNode.style.width = value;
		}
		this.resizeTextarea();
	}

	set height(value)
	{
		if(this._inputNode)
		{
			this._inputNode.style.height = value;
		}
		this.resizeTextarea();
	}
}

customElements.define("et2-textarea", Et2Textarea);
