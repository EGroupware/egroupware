/**
 * EGroupware eTemplate2 - Textbox widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css} from "@lion/core";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {SlTextarea} from "@shoelace-style/shoelace";
import shoelace from "../Styles/shoelace";


export class Et2Textarea extends Et2InputWidget(SlTextarea)
{
	static get styles()
	{
		return [
			...shoelace,
			...super.styles,
			css`
			:host {
				display: flex;
				flex-direction: column;
				width: 100%;
				height: 100%;
            }
            .textarea--resize-vertical .textarea__control {
            	height: 100%;
            }
            :host::part(form-control) {
    			height: 100%;
    			align-items: stretch !important;
			}
            :host::part(base) {
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
		if(this.__width && this._inputNode)
		{
			this._inputNode.style.width = this.__width;
		}
		if(this.__height && this._inputNode)
		{
			this._inputNode.style.height = this.__height;
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

		let oldValue = this.__width;

		this.__width = value;

		this.requestUpdate("width", oldValue);
	}

	set height(value)
	{
		let oldValue = this.__height;

		this.__height = value;

		this.requestUpdate("height", oldValue);
	}

	/** Override some parent stuff to get sizing how we like it **/
	setTextareaMaxHeight()
	{
		this._inputNode.style.maxHeight = 'inherit';
	}

	get _inputNode()
	{
		return this.shadowRoot?.querySelector("textarea");
	}
}

customElements.define("et2-textarea", Et2Textarea);
