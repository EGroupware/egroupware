import {css, CSSResultArray, html, LitElement} from "@lion/core";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import shoelace from "../Styles/shoelace";

/**
 * Widget for the actual content of a dialog, used when we're not doing a template
 *
 */
export class Et2DialogContent extends Et2Widget(LitElement)
{
	static styles : CSSResultArray = [
		shoelace,
		css`
			:host {
			display: block;
			min-width: 300px;
			min-height: 32px;
			}
			.dialog {
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
			dialogType: Number,
			icon: String,
			value: Object
		}
	}

	/**
	 * Details for dialog type options
	 */
	private readonly _dialogTypes : any = [
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
		this.dialogType = 0;
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
		let icon = this.icon || this.parentNode.egw().image(this._dialogTypes[this.dialogType]) || "";
		return html`
            <div class="dialog ${this._dialogTypes[this.dialogType]}">
                <img class="dialog_icon" src=${icon}/>
                <slot>Empty dialog - add some content</slot>
            </div>
		`;
	}
}

customElements.define("et2-dialog-content", Et2DialogContent);