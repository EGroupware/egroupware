import {css, CSSResultArray, html, LitElement} from "@lion/core";
import {Et2Widget} from "../Et2Widget/Et2Widget";

/**
 * Widget for the actual content of a dialog, used when we're not doing a template
 *
 */
export class Et2DialogContent extends Et2Widget(LitElement)
{
	static styles : CSSResultArray = [
		css`
			:host {
			display: block;
			min-width: 200px;
			min-height: 60px;
			}
			.dialog {
				padding: 5px;
			}
			.dialog_icon {
				margin-right: 2ex;
				vertical-align: middle;
			}
			`
	];


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

	/**
	 * Block until after the paint - This is needed to deal with children not fully "done" before the OverlayController
	 * tries to do things with them
	 *
	 * @returns {Promise<any>}
	 */
	async performUpdate()
	{
		await new Promise((resolve) => setTimeout(() => resolve()));
		return super.performUpdate();
	}

	render()
	{
		let icon = this.icon || this.parentNode.egw().image(this._dialog_types[this.dialog_type]) || "";
		return html`
            <div class="dialog ${this._dialog_types[this.dialog_type]}">
                <img class="dialog_icon" src=${icon}/>
                <slot>Empty dialog - add some content</slot>
            </div>
		`;
	}
}

customElements.define("et2-dialog-content", Et2DialogContent);