/**
 * EGroupware eTemplate2 - Textbox widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css, html} from "@lion/core";
import {LionTextarea} from "@lion/textarea";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {Et2Widget} from "../Et2Widget/Et2Widget";


export class Et2Textarea extends Et2InputWidget(LionTextarea)
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
            /* Get text area to fill its space */
            .form-field__group-two {
            	height: 100%;
            	display: flex;
            	flex-direction: column;
            }
            .input-group {
            	height: 100%;
            	display: flex;
            }
            .input-group__container {
            	width: 100%;
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
			width: {type: String},
			/**
			 * Specify the height of the text area.
			 * If not set, it will expand to fill the space available.
			 */
			height: {type: String},
			onkeypress: Function,
		}
	}

	constructor()
	{
		super();
		this.rows = "";
	}

	connectedCallback()
	{
		super.connectedCallback();
		if(this._width && this._inputNode)
		{
			this._inputNode.style.width = this._width;
		}
		if(this._height && this._inputNode)
		{
			this._inputNode.style.height = this._height;
		}
	}

	/**
	 * Use width and height attributes to affect style
	 * It would be better to deprecate these and just use CSS
	 *
	 * @param value
	 */
	set width(value)
	{

		let oldValue = this._width;

		this._width = value;

		this.requestUpdate("width", oldValue);
	}

	set height(value)
	{
		let oldValue = this._height;

		this._height = value;

		this.requestUpdate("height", oldValue);
	}

	/** Override some parent stuff to get sizing how we like it **/
	setTextareaMaxHeight()
	{
		this._inputNode.style.maxHeight = 'inherit';
	}

	__initializeAutoresize()
	{
		return;
	}

	__startAutoresize()
	{
		return;
	}
}

// @ts-ignore TypeScript is not recognizing that Et2Textarea is a LitElement
customElements.define("et2-textarea", Et2Textarea);
