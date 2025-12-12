import {html, LitElement, nothing, PropertyValues, TemplateResult} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {join} from "lit/directives/join.js";
import {property} from "lit/decorators/property.js";
import {SlAlert} from "@shoelace-style/shoelace";
import {egw} from "../../api/js/jsapi/egw_global";
import {Et2Checkbox} from "../../api/js/etemplate/Et2Checkbox/Et2Checkbox";
import {activateLinks} from "../../api/js/etemplate/ActivateLinksDirective";

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

	// Handle some HTML in the message, like links & newlines
	// You can create & toast the egw-message directly for more flexibility
	private HREF_REG = /<a href="([^"]+)">([^<]+)<\/a>/img;
	private NEWLINE_REG = /<\/?(p|br)\s*\/?>\n?/ig;


	public willUpdate(changedProperties : PropertyValues<this>)
	{
		super.willUpdate(changedProperties);
		if(changedProperties.has("message"))
		{
			// Decode HTML entities in the message through textarea
			// The browser automatically interprets and decodes the entities.
			const textarea = document.createElement('textarea');
			textarea.innerHTML = this.message;
			this.message = textarea.value;
		}
	}

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

	public close() : Promise<void>
	{
		return this.hide();
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

	/* Handle newlines and links in the message */
	private _messageTemplate(message)
	{
		// Convert newlines to <br> tags
		const br2br = (str) =>
		{
			const split = (str ?? "").split(this.NEWLINE_REG).filter(s => !["", "p", "br"].includes(s.trim()));
			return html`${join(split, html`<br>`)}`;
		}
		const matches = this.HREF_REG.exec(this.message);
		if(matches)
		{
			// Activate 1 anchor tag
			const parts = this.message.split(matches[0]);
			const href = matches[1]; //html_entity_decode(matches[1]);
			message = html`
                ${br2br(parts[0])}
                <a href="${matches[1]}"
                   target="${href.indexOf(this.egw.webserverUrl) != 0 ? '_blank' : "_self"}">${matches[2]}</a>
                ${br2br(parts[1])}`;
		}
		else
		{
			message = html`${activateLinks(message, '_self')}`;
		}

		return message;
	}

	render()
	{
		const icon = EgwFrameworkMessage.ICON_MAP[this.type] ?? "info-circle";
		const variant = EgwFrameworkMessage.TYPE_MAP[this.type] ?? "success";
		const duration = this.type == "success" && !this.duration ? 5000 : this.duration;

		// Handle anchor links in message
		const message = this._messageTemplate(this.message);

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
                ${message}
                <slot></slot>
                ${discard}
            </sl-alert>
		`;
	}
}