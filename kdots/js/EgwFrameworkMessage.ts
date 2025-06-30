import {html, LitElement, nothing, TemplateResult} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {SlAlert} from "@shoelace-style/shoelace";
import {egw} from "../../api/js/jsapi/egw_global";
import {Et2Checkbox} from "../../api/js/etemplate/Et2Checkbox/Et2Checkbox";

/**
 * @summary System message
 *
 * @dependency sl-alert
 * @dependency sl-icon
 *
 * @slot - Content
 * @slot icon - An icon to show in the message
 *
 * @csspart base - Wraps it all.
 * @csspart icon -
 */
@customElement('egw-message')
export class EgwFrameworkMessage extends LitElement
{
	/**
	 * The type of message
	 * @type {  "help" | "info" | "error" | "warning" | "success"}
	 */
	@property()
	type : "help" | "info" | "error" | "warning" | "success" = "success";

	/**
	 * Message
	 *
	 * @type {string}
	 */
	@property()
	message : string = "";
	/**
	 * Enables a close button that allows the user to dismiss the message
	 * @type {boolean}
	 */
	@property()
	closable = true;

	/**
	 * Length of time, in seconds, before the message closes automatically.
	 * Success messages close in 5s, for other types the default is never close.
	 * @type {number}
	 */
	@property()
	duration : number = null;

	/**
	 * Unique string id (appname:id) in order to register the message as discardable.
	 * Discardable messages offer a checkbox to never be shown again.
	 * If no appname given, the id will be prefixed with current app. The discardID will be stored in local storage.
	 *
	 * @type {string}
	 */
	@property()
	discard : string = "";

	// Map types to icons
	private static ICON_MAP = {
		help: "question-circle",
		error: "exclamation-octagon",
		warning: "exclamation-triangle",
		success: "check2-circle",
		info: "info-circle",
	};

	// Map our message types to shoelace variants
	private static TYPE_MAP = {info: "success", warning: "warning", error: "danger"};

	private __alert : SlAlert;

	/**
	 * Check if a message has been discarded
	 * @param {string} discardId
	 * @returns {boolean}
	 */
	public static isDiscarded(discardId : string) : boolean
	{
		let discardAppName = "";
		if(discardId)
		{
			let discardID = discardId.split(':');
			if(discardID.length < 2)
			{
				discardId = window.egw.app_name() + ":" + discardID.pop();
			}
			discardAppName = discardID.length > 1 ? discardID[0] : window.egw.app_name();
		}

		const discarded = JSON.parse(window.egw.getLocalStorageItem(discardAppName, 'discardedMsgs'));
		if(Array.isArray(discarded))
		{
			return discarded.includes(discardId);
		}
		return false;
	};

	/**
	 * Display the alert as a toast notification
	 * The returned promise will resolve after the message is hidden.
	 *
	 * @returns {Promise<void>}
	 */
	public toast() : Promise<void>
	{
		this.__alert = this.alert;
		this.updateComplete.then(() =>
		{
			this.remove();
		})
		return this.alert.toast();
	}

	/**
	 * Show the message
	 * @returns {() => Promise<void>}
	 */
	public show() : () => Promise<void>
	{
		return this.alert.show;
	}

	/**
	 * Hide the message
	 * @returns {Promise<void>}
	 */
	public hide() : Promise<void>
	{
		return this.alert.hide();
	}

	get alert() : SlAlert { return this.shadowRoot?.querySelector("sl-alert") as SlAlert ?? this.__alert; }

	get egw() : typeof egw
	{
		return window.egw
	}

	protected handleHide(e)
	{
		// Store user's discard choice, if it was offered
		const check = <Et2Checkbox>this.alert.querySelector("#discard");
		if(this.discard && check && check.value && !EgwFrameworkMessage.isDiscarded(this.discard))
		{
			const discardAppName = this.discard.split(":").shift();
			let discarded : string | string[] = this.egw.getLocalStorageItem(discardAppName, 'discardedMsgs');
			if(!discarded)
			{
				discarded = [this.discard];
			}
			else
			{
				discarded = <string[]>JSON.parse(discarded)
				discarded.push(this.discard);
			}
			this.egw.setLocalStorageItem(discardAppName, 'discardedMsgs', JSON.stringify(discarded));
		}
		this.updateComplete.then(() =>
		{
			this.dispatchEvent(new CustomEvent("sl-hide"));
		});
	}

	render()
	{
		const icon = EgwFrameworkMessage.ICON_MAP[this.type] ?? "info-circle";
		const variant = EgwFrameworkMessage.TYPE_MAP[this.type] ?? "success";
		const duration = this.type == "success" && !this.duration ? 5000 : this.duration;

		let discard : symbol | TemplateResult = nothing;
		if(this.discard && EgwFrameworkMessage.isDiscarded(this.discard))
		{
			// Don't show discarded messages
			return nothing;
		}
		else if(this.discard)
		{
			discard = html`
                <et2-checkbox
                        id="discard"
                        label="${this.egw.lang("Don't show this again")}"
                ></et2-checkbox>
			`;
		}
		return html`
            <sl-alert
                    variant=${variant}
                    ?closable=${this.closable}
                    ?countdown=${duration > 5000}
                    duration=${duration || nothing}
                    @sl-hide=${this.handleHide}
            >
                <sl-icon name=${icon} slot="icon"></sl-icon>
                <et2-description activateLinks value=${this.message}></et2-description>
                ${discard}
            </sl-alert>
		`;
	}
}