import {Et2Widget} from "../Et2Widget/Et2Widget";
import {css, html, LitElement} from "@lion/core";

/**
 * Widget for the actual content of a dialog, used when we're not doing a template
 *
 */
export class Et2DialogContent extends Et2Widget(LitElement)
{
	get styles()
	{
		return [
			...super.styles,
			css`
			:host {
			display: block;
			
	min-width: 200px;
			}
.dialog-title {
font-size: 120%;
}`
		]
	}

	get properties()
	{
		return {
			...super.properties(),

			message: String,
			dialog_type: Number,
			icon: String,
			value: Object
		}
	}

	/**
	 * Details for dialog type options
	 */
	private readonly _dialog_types : any = [
		//PLAIN_MESSAGE: 0
		"",
		//INFORMATION_MESSAGE: 1,
		"dialog_info",
		//QUESTION_MESSAGE: 2,
		"dialog_help",
		//WARNING_MESSAGE: 3,
		"dialog_warning",
		//ERROR_MESSAGE: 4,
		"dialog_error"
	];

	constructor()
	{
		super();

		this.icon = "";
		this.dialog_type = 0;
	}


	render()
	{
		let icon = this.icon || this.egw().image(this._dialog_types[this.dialog_type]) || "";
		return html`
            <div class="dialog ${this._dialog_types[this.dialog_type]}">
                <img ?src=${icon}/>
                <slot>Empty dialog - add some content</slot>
            </div>
		`;
	}
}

customElements.define("et2-dialog-content", Et2DialogContent);